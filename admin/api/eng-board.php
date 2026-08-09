<?php
/**
 * Engineering PM board API — kanban (tickets + tasks) over the existing
 * quotes/engineering_tasks schema, plus Gantt data.
 *
 * POST JSON { action, csrf_token, ... }:
 *   bootstrap      -> tickets, tasks, engineers
 *   move_task      {task_id, status, sort:[ids in column order]}
 *   move_ticket    {quote_id, status}
 *   assign         {task_id, user_id}
 *   progress       {task_id, pct}
 *   create_task    {quote_id, title, assigned_to, estimated_hours, start_date, end_date, priority}
 *   create_ticket  {product_type, order_id?, priority, timeline}
 */
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/AuditLog.php';

header('Content-Type: application/json');
if (!isLoggedIn() || !isStaff()) { http_response_code(403); echo json_encode(['error' => 'Staff only']); exit; }

$in = json_decode(file_get_contents('php://input'), true) ?: [];
if (!Security::validateCSRFToken($in['csrf_token'] ?? '')) { http_response_code(403); echo json_encode(['error' => 'CSRF']); exit; }

$uid = (int) $_SESSION['user_id'];
$taskStatuses = ['new', 'assigned', 'in_progress', 'review', 'completed'];
$ticketStatuses = ['submitted', 'reviewed', 'assigned', 'in_progress', 'completed'];

switch ($in['action'] ?? '') {
    case 'bootstrap':
        $tickets = $pdo->query("
            SELECT q.id, q.product_type, q.status, q.priority, q.preferred_timeline, q.source_order_id,
                   q.created_at, u.email AS customer,
                   (SELECT COUNT(*) FROM engineering_tasks t WHERE t.quote_id=q.id) task_count,
                   (SELECT COUNT(*) FROM engineering_tasks t WHERE t.quote_id=q.id AND t.status='completed') done_count,
                   (SELECT COALESCE(AVG(t.progress_pct),0) FROM engineering_tasks t WHERE t.quote_id=q.id) avg_progress
            FROM quotes q LEFT JOIN users u ON q.user_id=u.id
            WHERE q.status NOT IN ('cancelled','invoiced')
            ORDER BY q.priority='urgent' DESC, q.priority='high' DESC, q.created_at
        ")->fetchAll(PDO::FETCH_ASSOC);

        $tasks = $pdo->query("
            SELECT t.id, t.quote_id, t.title, t.status, t.priority, t.estimated_hours, t.actual_hours,
                   t.start_date, t.end_date, t.progress_pct, t.sort_order, t.assigned_to,
                   COALESCE(u.chat_nickname, SUBSTRING_INDEX(u.email,'@',1)) AS assignee, u.chat_avatar,
                   q.product_type AS ticket_name
            FROM engineering_tasks t
            LEFT JOIN users u ON t.assigned_to = u.id
            LEFT JOIN quotes q ON t.quote_id = q.id
            WHERE q.status IS NULL OR q.status NOT IN ('cancelled','invoiced')
            ORDER BY t.sort_order, t.id
        ")->fetchAll(PDO::FETCH_ASSOC);

        $engineers = $pdo->query("
            SELECT id, COALESCE(chat_nickname, SUBSTRING_INDEX(email,'@',1)) AS nick
            FROM users WHERE role IN ('admin','staff') ORDER BY nick
        ")->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['tickets' => $tickets, 'tasks' => $tasks, 'engineers' => $engineers, 'me' => $uid]);
        break;

    case 'move_task':
        $tid = (int) ($in['task_id'] ?? 0);
        $status = $in['status'] ?? '';
        if (!in_array($status, $taskStatuses, true)) { echo json_encode(['error' => 'Bad status']); break; }
        $extra = $status === 'completed' ? ", progress_pct=100, actual_hours=COALESCE(NULLIF(actual_hours,0), estimated_hours)" : '';
        $pdo->prepare("UPDATE engineering_tasks SET status=?$extra WHERE id=?")->execute([$status, $tid]);
        foreach (array_values($in['sort'] ?? []) as $i => $id) {
            $pdo->prepare("UPDATE engineering_tasks SET sort_order=? WHERE id=?")->execute([$i, (int) $id]);
        }
        AuditLog::record($pdo, 'eng.task_move', 'task', $tid, "Task #$tid → $status");
        echo json_encode(['ok' => true]);
        break;

    case 'move_ticket':
        $qid = (int) ($in['quote_id'] ?? 0);
        $status = $in['status'] ?? '';
        if (!in_array($status, $ticketStatuses, true)) { echo json_encode(['error' => 'Bad status']); break; }
        $pdo->prepare("UPDATE quotes SET status=? WHERE id=?")->execute([$status, $qid]);
        AuditLog::record($pdo, 'eng.ticket_move', 'quote', $qid, "Ticket #$qid → $status");
        echo json_encode(['ok' => true]);
        break;

    case 'assign':
        $tid = (int) ($in['task_id'] ?? 0);
        $who = (int) ($in['user_id'] ?? 0) ?: null;
        $pdo->prepare("UPDATE engineering_tasks SET assigned_to=?, status=IF(status='new' AND ? IS NOT NULL,'assigned',status) WHERE id=?")
            ->execute([$who, $who, $tid]);
        AuditLog::record($pdo, 'eng.task_assign', 'task', $tid, "Task #$tid assigned to user #$who");
        echo json_encode(['ok' => true]);
        break;

    case 'progress':
        $tid = (int) ($in['task_id'] ?? 0);
        $pct = max(0, min(100, (int) ($in['pct'] ?? 0)));
        $pdo->prepare("UPDATE engineering_tasks SET progress_pct=?, status=IF(?=100,'completed',IF(status='completed','in_progress',status)) WHERE id=?")
            ->execute([$pct, $pct, $tid]);
        echo json_encode(['ok' => true]);
        break;

    case 'create_task':
        $qid = (int) ($in['quote_id'] ?? 0);
        $title = trim($in['title'] ?? '');
        if ($title === '' || !$qid) { echo json_encode(['error' => 'Ticket and title required']); break; }
        $who = (int) ($in['assigned_to'] ?? 0) ?: null;
        $pdo->prepare("
            INSERT INTO engineering_tasks (quote_id, title, description, assigned_to, status, estimated_hours, start_date, end_date, priority)
            VALUES (?,?,?,?,?,?,?,?,?)
        ")->execute([
            $qid, $title, trim($in['description'] ?? '') ?: null, $who,
            $who ? 'assigned' : 'new',
            (float) ($in['estimated_hours'] ?? 4),
            $in['start_date'] ?: date('Y-m-d'),
            $in['end_date'] ?: date('Y-m-d', strtotime('+7 days')),
            in_array($in['priority'] ?? '', ['low','medium','high','urgent'], true) ? $in['priority'] : 'medium',
        ]);
        AuditLog::record($pdo, 'eng.task_create', 'task', $pdo->lastInsertId(), "Task \"$title\" created on ticket #$qid");
        echo json_encode(['ok' => true, 'id' => (int) $pdo->lastInsertId()]);
        break;

    case 'create_ticket':
        $name = trim($in['product_type'] ?? '');
        if ($name === '') { echo json_encode(['error' => 'Ticket name required']); break; }
        $orderId = (int) ($in['order_id'] ?? 0) ?: null;
        $customer = $uid;
        if ($orderId) {
            $o = $pdo->prepare("SELECT user_id FROM orders WHERE id=?");
            $o->execute([$orderId]);
            $customer = (int) ($o->fetchColumn() ?: $uid);
        }
        $pdo->prepare("
            INSERT INTO quotes (user_id, source_order_id, product_type, specifications, quantity, preferred_timeline, status, priority, submitted_at)
            VALUES (?,?,?,?,1,?, 'reviewed', ?, NOW())
        ")->execute([$customer, $orderId, $name, trim($in['specifications'] ?? '') ?: $name,
                     $in['timeline'] ?? '2 weeks',
                     in_array($in['priority'] ?? '', ['low','medium','high','urgent'], true) ? $in['priority'] : 'medium']);
        $qid = (int) $pdo->lastInsertId();
        AuditLog::record($pdo, 'eng.ticket_create', 'quote', $qid, "Ticket \"$name\" created" . ($orderId ? " from order #$orderId" : ''));
        echo json_encode(['ok' => true, 'id' => $qid]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
