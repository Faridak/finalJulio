<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

requireRole('admin');
$uid = (int) $_SESSION['user_id'];
$notice = '';

// Profile save (multipart: nickname + avatar upload)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'profile') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) { http_response_code(403); die('CSRF mismatch'); }
    $nick = trim(mb_substr($_POST['nickname'] ?? '', 0, 40));
    if ($nick !== '') $pdo->prepare("UPDATE users SET chat_nickname = ? WHERE id = ?")->execute([$nick, $uid]);

    if (!empty($_FILES['avatar']['tmp_name'])) {
        $types = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $errs = Security::validateFileUpload($_FILES['avatar'], array_keys($types), 2 * 1024 * 1024);
        if (!$errs) {
            $ext = $types[mime_content_type($_FILES['avatar']['tmp_name'])];
            $path = 'uploads/avatars/u' . $uid . '-' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], __DIR__ . '/../' . $path)) {
                store_media_file($path, __DIR__ . '/../' . $path);   // mirror to object storage when configured
                $pdo->prepare("UPDATE users SET chat_avatar = ? WHERE id = ?")->execute([$path, $uid]);
            }
        }
    }
    header('Location: chat.php'); exit;
}

$meProfile = $pdo->prepare("SELECT COALESCE(chat_nickname, SUBSTRING_INDEX(email,'@',1)) nick, chat_avatar FROM users WHERE id = ?");
$meProfile->execute([$uid]);
$meProfile = $meProfile->fetch(PDO::FETCH_ASSOC);
$csrf = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Chat - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-6xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold text-gray-900">Team Chat</h1>
        <!-- My profile: nickname + avatar -->
        <form method="POST" enctype="multipart/form-data" class="flex items-center gap-2 text-sm bg-white rounded-lg shadow px-3 py-2">
            <?= Security::getCSRFInput() ?><input type="hidden" name="action" value="profile">
            <?php if (!empty($meProfile['chat_avatar'])): ?>
                <img src="../<?= htmlspecialchars($meProfile['chat_avatar']) ?>" class="w-8 h-8 rounded-full object-cover">
            <?php else: ?>
                <span class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold"><?= strtoupper(mb_substr($meProfile['nick'], 0, 1)) ?></span>
            <?php endif; ?>
            <input name="nickname" value="<?= htmlspecialchars($meProfile['nick']) ?>" placeholder="Nickname" class="px-2 py-1 border rounded w-32">
            <label class="text-xs text-gray-500 cursor-pointer">📷<input type="file" name="avatar" accept="image/*" class="hidden" onchange="this.form.submit()"></label>
            <button class="bg-gray-800 text-white text-xs px-2 py-1 rounded">Save</button>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-4" style="height:calc(100vh - 200px);min-height:420px">
        <!-- Sidebar -->
        <aside class="bg-white rounded-lg shadow flex flex-col overflow-hidden">
            <div class="px-4 py-3 border-b font-semibold text-gray-700 text-sm">Channels</div>
            <div id="channelList" class="flex-1 overflow-y-auto text-sm"></div>
            <form id="newChannelForm" class="p-2 border-t flex gap-1">
                <input id="newChannelName" placeholder="new channel…" class="flex-1 px-2 py-1 border rounded text-xs">
                <button class="bg-gray-800 text-white text-xs px-2 rounded">＋</button>
            </form>
            <div class="px-4 py-2 border-t font-semibold text-gray-700 text-sm">Direct messages</div>
            <div id="staffList" class="overflow-y-auto text-sm max-h-40"></div>
        </aside>

        <!-- Conversation -->
        <main class="md:col-span-3 bg-white rounded-lg shadow flex flex-col overflow-hidden">
            <div id="convTitle" class="px-4 py-3 border-b font-semibold text-gray-800">Select a channel</div>
            <div id="messages" class="flex-1 overflow-y-auto p-4 space-y-2"></div>
            <form id="sendForm" class="p-3 border-t flex gap-2">
                <input id="msgBody" autocomplete="off" placeholder="Type a message…" class="flex-1 px-3 py-2 border rounded-lg" disabled>
                <button id="sendBtn" class="bg-blue-600 text-white px-5 rounded-lg" disabled>Send</button>
            </form>
        </main>
    </div>
</div>

<script>
const CSRF = <?= json_encode($csrf) ?>;
let ME = 0, current = 0, lastId = 0, pollTimer = null;
const $ = id => document.getElementById(id);

async function api(action, body = {}) {
    const r = await fetch('api/chat.php', {method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify(Object.assign({action, csrf_token: CSRF}, body))});
    return r.json();
}
function esc(s){ const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

async function bootstrap() {
    const d = await api('bootstrap');
    ME = d.me;
    $('channelList').innerHTML = d.channels.map(c => {
        const label = c.type === 'direct' ? '@' + (c.dm_with || 'dm').split('@')[0] : '# ' + (c.name || '');
        const unread = c.unread > 0 ? `<span class="bg-red-500 text-white text-xs rounded-full px-1.5">${c.unread}</span>` : '';
        return `<button data-cid="${c.id}" data-label="${esc(label)}" class="chan w-full text-left px-4 py-2 hover:bg-blue-50 flex justify-between items-center ${c.id===current?'bg-blue-50 font-semibold':''}">
            <span class="truncate">${esc(label)}</span>${unread}</button>`;
    }).join('');
    $('staffList').innerHTML = d.staff.map(u => {
        const av = u.chat_avatar
            ? `<img src="../${esc(u.chat_avatar)}" class="w-5 h-5 rounded-full object-cover inline-block mr-1">`
            : `<span class="w-5 h-5 rounded-full bg-gray-300 text-gray-700 text-[10px] inline-flex items-center justify-center mr-1 font-bold">${esc(u.nick.charAt(0).toUpperCase())}</span>`;
        return `<button data-uid="${u.id}" class="dm w-full text-left px-4 py-1.5 hover:bg-blue-50 text-gray-600 flex items-center">${av}${esc(u.nick)}</button>`;
    }).join('');
    document.querySelectorAll('.chan').forEach(b => b.onclick = () => openChannel(+b.dataset.cid, b.dataset.label));
    document.querySelectorAll('.dm').forEach(b => b.onclick = async () => {
        const r = await api('start_dm', {user_id: +b.dataset.uid});
        if (r.channel_id) { await bootstrap(); openChannel(r.channel_id, b.textContent.trim()); }
    });
}

async function openChannel(cid, label) {
    current = cid; lastId = 0;
    $('convTitle').textContent = label;
    $('messages').innerHTML = '';
    $('msgBody').disabled = false; $('sendBtn').disabled = false; $('msgBody').focus();
    await poll(true);
    if (pollTimer) clearInterval(pollTimer);
    pollTimer = setInterval(() => poll(false), 3000);
    bootstrap(); // refresh unread badges
}

async function poll(initial) {
    if (!current) return;
    const d = await api('history', {channel_id: current, after_id: lastId});
    if (!d.messages || !d.messages.length) return;
    for (const m of d.messages) {
        lastId = Math.max(lastId, +m.id);
        const mine = +m.user_id === ME;
        const el = document.createElement('div');
        el.className = 'flex items-end gap-2 ' + (mine ? 'justify-end' : 'justify-start');
        const avatar = mine ? '' : (m.chat_avatar
            ? `<img src="../${esc(m.chat_avatar)}" class="w-7 h-7 rounded-full object-cover flex-shrink-0">`
            : `<span class="w-7 h-7 rounded-full bg-gray-300 text-gray-700 text-xs flex items-center justify-center flex-shrink-0 font-bold">${esc((m.nick||'?').charAt(0).toUpperCase())}</span>`);
        el.innerHTML = `${avatar}<div class="max-w-[75%] rounded-2xl px-3 py-2 ${mine ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-800'}">
            ${mine ? '' : `<div class="text-xs font-semibold text-blue-700">${esc(m.nick || '')}</div>`}
            <div class="text-sm whitespace-pre-wrap break-words">${esc(m.body)}</div>
            <div class="text-[10px] opacity-60 text-right">${m.d} ${m.t}</div></div>`;
        $('messages').appendChild(el);
    }
    $('messages').scrollTop = $('messages').scrollHeight;
}

$('sendForm').onsubmit = async e => {
    e.preventDefault();
    const body = $('msgBody').value.trim();
    if (!body || !current) return;
    $('msgBody').value = '';
    await api('send', {channel_id: current, body});
    poll(false);
};
$('newChannelForm').onsubmit = async e => {
    e.preventDefault();
    const name = $('newChannelName').value.trim();
    if (!name) return;
    $('newChannelName').value = '';
    const r = await api('create_channel', {name});
    await bootstrap();
    if (r.channel_id) openChannel(r.channel_id, '# ' + name);
};

bootstrap();
</script>
</body>
</html>
