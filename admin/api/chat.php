<?php
/**
 * Internal staff chat API (polling). Staff = users with role 'admin'.
 *
 * POST JSON { action, csrf_token, ... }:
 *   bootstrap            -> channels (w/ unread counts) + staff directory
 *   history  {channel_id, after_id} -> messages after id (and marks read)
 *   send     {channel_id, body}
 *   start_dm {user_id}   -> returns (or creates) the 1:1 channel
 *   create_channel {name}
 */
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security.php';

header('Content-Type: application/json');
if (!isLoggedIn() || !isStaff()) { http_response_code(403); echo json_encode(['error' => 'Staff only']); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (!Security::validateCSRFToken($in['csrf_token'] ?? '')) { http_response_code(403); echo json_encode(['error' => 'CSRF']); exit; }

$uid = (int) $_SESSION['user_id'];
$action = $in['action'] ?? '';

// Everyone on staff is auto-joined to every group channel.
$pdo->exec("
    INSERT IGNORE INTO chat_members (channel_id, user_id)
    SELECT c.id, $uid FROM chat_channels c WHERE c.type = 'group'
");

function isMember(PDO $pdo, $cid, $uid) {
    $s = $pdo->prepare("SELECT 1 FROM chat_members WHERE channel_id=? AND user_id=?");
    $s->execute([$cid, $uid]);
    return (bool) $s->fetchColumn();
}

switch ($action) {
    case 'bootstrap':
        $ch = $pdo->prepare("
            SELECT c.id, c.name, c.type,
                   (SELECT COUNT(*) FROM chat_messages m WHERE m.channel_id=c.id AND m.id > cm.last_read_id AND m.user_id <> ?) AS unread,
                   (SELECT COALESCE(u.chat_nickname, SUBSTRING_INDEX(u.email,'@',1)) FROM chat_members m2 JOIN users u ON u.id=m2.user_id
                     WHERE m2.channel_id=c.id AND m2.user_id <> ? AND c.type='direct' LIMIT 1) AS dm_with
            FROM chat_channels c JOIN chat_members cm ON cm.channel_id=c.id AND cm.user_id=?
            ORDER BY c.type='group' DESC, c.id
        ");
        $ch->execute([$uid, $uid, $uid]);
        $staff = $pdo->prepare("
            SELECT id, COALESCE(chat_nickname, SUBSTRING_INDEX(email,'@',1)) AS nick, chat_avatar
            FROM users WHERE role IN ('admin','staff') AND id <> ? ORDER BY nick");
        $staff->execute([$uid]);
        $meRow = $pdo->prepare("SELECT COALESCE(chat_nickname, SUBSTRING_INDEX(email,'@',1)) AS nick, chat_avatar FROM users WHERE id = ?");
        $meRow->execute([$uid]);
        echo json_encode(['channels' => $ch->fetchAll(PDO::FETCH_ASSOC), 'staff' => $staff->fetchAll(PDO::FETCH_ASSOC),
                          'me' => $uid, 'profile' => $meRow->fetch(PDO::FETCH_ASSOC)]);
        break;

    case 'save_profile':
        // nickname (multipart form also posts here via chat.php page handler)
        $nick = trim($in['nickname'] ?? '');
        if ($nick === '' || mb_strlen($nick) > 40) { echo json_encode(['error' => 'Nickname must be 1–40 characters']); break; }
        $pdo->prepare("UPDATE users SET chat_nickname = ? WHERE id = ?")->execute([$nick, $uid]);
        echo json_encode(['ok' => true, 'nick' => $nick]);
        break;

    case 'history':
        $cid = (int) ($in['channel_id'] ?? 0);
        $after = (int) ($in['after_id'] ?? 0);
        if (!isMember($pdo, $cid, $uid)) { echo json_encode(['error' => 'Not a member']); break; }
        $ms = $pdo->prepare("
            SELECT m.id, m.user_id, COALESCE(u.chat_nickname, SUBSTRING_INDEX(u.email,'@',1)) AS nick,
                   u.chat_avatar, m.body,
                   DATE_FORMAT(m.created_at, '%H:%i') t, DATE_FORMAT(m.created_at, '%b %e') d
            FROM chat_messages m JOIN users u ON u.id = m.user_id
            WHERE m.channel_id = ? AND m.id > ?
            ORDER BY m.id " . ($after > 0 ? 'ASC' : 'DESC LIMIT 50'));
        $ms->execute([$cid, $after]);
        $rows = $ms->fetchAll(PDO::FETCH_ASSOC);
        if ($after === 0) $rows = array_reverse($rows);
        if ($rows) {
            $maxId = max(array_column($rows, 'id'));
            $pdo->prepare("UPDATE chat_members SET last_read_id = GREATEST(last_read_id, ?) WHERE channel_id=? AND user_id=?")
                ->execute([$maxId, $cid, $uid]);
        }
        echo json_encode(['messages' => $rows]);
        break;

    case 'send':
        $cid = (int) ($in['channel_id'] ?? 0);
        $body = trim($in['body'] ?? '');
        if ($body === '' || mb_strlen($body) > 4000) { echo json_encode(['error' => 'Empty or too long']); break; }
        if (!isMember($pdo, $cid, $uid)) { echo json_encode(['error' => 'Not a member']); break; }
        $pdo->prepare("INSERT INTO chat_messages (channel_id, user_id, body) VALUES (?,?,?)")->execute([$cid, $uid, $body]);
        $mid = (int) $pdo->lastInsertId();
        $pdo->prepare("UPDATE chat_members SET last_read_id = GREATEST(last_read_id, ?) WHERE channel_id=? AND user_id=?")->execute([$mid, $cid, $uid]);
        echo json_encode(['ok' => true, 'id' => $mid]);
        break;

    case 'start_dm':
        $other = (int) ($in['user_id'] ?? 0);
        $chk = $pdo->prepare("SELECT id FROM users WHERE id=? AND role IN ('admin','staff')");
        $chk->execute([$other]);
        if (!$chk->fetchColumn() || $other === $uid) { echo json_encode(['error' => 'Invalid user']); break; }
        $key = 'dm:' . min($uid, $other) . ':' . max($uid, $other);
        $ex = $pdo->prepare("SELECT id FROM chat_channels WHERE dm_key=?");
        $ex->execute([$key]);
        $cid = (int) $ex->fetchColumn();
        if (!$cid) {
            $pdo->prepare("INSERT INTO chat_channels (type, dm_key, created_by) VALUES ('direct', ?, ?)")->execute([$key, $uid]);
            $cid = (int) $pdo->lastInsertId();
            $mem = $pdo->prepare("INSERT IGNORE INTO chat_members (channel_id, user_id) VALUES (?,?)");
            $mem->execute([$cid, $uid]); $mem->execute([$cid, $other]);
        }
        echo json_encode(['channel_id' => $cid]);
        break;

    case 'create_channel':
        $name = trim($in['name'] ?? '');
        if ($name === '' || mb_strlen($name) > 60) { echo json_encode(['error' => 'Bad name']); break; }
        $pdo->prepare("INSERT INTO chat_channels (name, type, created_by) VALUES (?, 'group', ?)")->execute([$name, $uid]);
        $cid = (int) $pdo->lastInsertId();
        // group channels are for everyone — join all staff
        $pdo->exec("INSERT IGNORE INTO chat_members (channel_id, user_id) SELECT $cid, id FROM users WHERE role IN ('admin','staff')");
        echo json_encode(['channel_id' => $cid]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
