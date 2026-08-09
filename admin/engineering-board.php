<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

requireRole('admin');
$csrf = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Engineering Board - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
    <script src="../public/vendor/js/Sortable.min.js"></script>
    <style>
        .kcol { min-height: 12rem; }
        .kcard { cursor: grab; }
        .kcard.sortable-ghost { opacity: .4; }
        .gantt-grid { display: grid; }
    </style>
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-[90rem] mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
        <h1 class="text-2xl font-bold text-gray-900">Engineering Board</h1>
        <div class="flex gap-1 text-sm">
            <button data-tab="tasks"   class="tab bg-blue-600 text-white px-4 py-1.5 rounded">Task Kanban</button>
            <button data-tab="tickets" class="tab bg-white border px-4 py-1.5 rounded">Tickets</button>
            <button data-tab="gantt"   class="tab bg-white border px-4 py-1.5 rounded">Gantt</button>
        </div>
    </div>

    <!-- Quick create -->
    <div class="grid md:grid-cols-2 gap-3 mb-5 text-sm">
        <form id="newTicketForm" class="bg-white rounded-lg shadow p-3 flex flex-wrap gap-2 items-end">
            <b class="w-full text-gray-700">New ticket</b>
            <input id="tkName" required placeholder="Work title (e.g. Custom duct assembly)" class="px-2 py-1.5 border rounded flex-1 min-w-[12rem]">
            <input id="tkOrder" placeholder="Order # (optional)" class="px-2 py-1.5 border rounded w-32">
            <select id="tkPriority" class="px-2 py-1.5 border rounded"><option>medium</option><option>high</option><option>urgent</option><option>low</option></select>
            <button class="bg-indigo-600 text-white px-3 py-1.5 rounded">Create</button>
        </form>
        <form id="newTaskForm" class="bg-white rounded-lg shadow p-3 flex flex-wrap gap-2 items-end">
            <b class="w-full text-gray-700">New task</b>
            <select id="tskTicket" class="px-2 py-1.5 border rounded max-w-[11rem]"></select>
            <input id="tskTitle" required placeholder="Task title" class="px-2 py-1.5 border rounded flex-1 min-w-[10rem]">
            <select id="tskAssignee" class="px-2 py-1.5 border rounded max-w-[9rem]"><option value="">unassigned</option></select>
            <input id="tskStart" type="date" class="px-2 py-1.5 border rounded">
            <input id="tskEnd" type="date" class="px-2 py-1.5 border rounded">
            <input id="tskHours" type="number" step="0.5" value="4" title="Estimated hours" class="px-2 py-1.5 border rounded w-16">
            <button class="bg-blue-600 text-white px-3 py-1.5 rounded">Add</button>
        </form>
    </div>

    <!-- TASKS kanban -->
    <div id="view-tasks" class="view grid grid-cols-2 md:grid-cols-5 gap-3"></div>

    <!-- TICKETS kanban -->
    <div id="view-tickets" class="view hidden grid grid-cols-2 md:grid-cols-5 gap-3"></div>

    <!-- GANTT -->
    <div id="view-gantt" class="view hidden bg-white rounded-lg shadow p-4 overflow-x-auto"></div>
</div>

<script>
const CSRF = <?= json_encode($csrf) ?>;
const TASK_COLS   = [['new','Backlog','bg-gray-100'],['assigned','To Do','bg-blue-50'],['in_progress','In Progress','bg-amber-50'],['review','Review','bg-purple-50'],['completed','Done','bg-green-50']];
const TICKET_COLS = [['submitted','Submitted','bg-gray-100'],['reviewed','Reviewed','bg-blue-50'],['assigned','Assigned','bg-indigo-50'],['in_progress','In Progress','bg-amber-50'],['completed','Completed','bg-green-50']];
const PRI = {urgent:'bg-red-100 text-red-700', high:'bg-orange-100 text-orange-700', medium:'bg-blue-100 text-blue-700', low:'bg-gray-100 text-gray-600'};
let DATA = null;
const $ = id => document.getElementById(id);
const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };

async function api(action, body = {}) {
    const r = await fetch('api/eng-board.php', {method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify(Object.assign({action, csrf_token: CSRF}, body))});
    return r.json();
}

function taskCard(t) {
    const av = t.assignee
        ? (t.chat_avatar ? `<img src="../${esc(t.chat_avatar)}" class="w-5 h-5 rounded-full object-cover">`
                         : `<span class="w-5 h-5 rounded-full bg-gray-300 text-[10px] flex items-center justify-center font-bold">${esc(t.assignee.charAt(0).toUpperCase())}</span>`)
        : '<span class="text-xs text-gray-400">unassigned</span>';
    return `<div class="kcard bg-white rounded-lg shadow p-3 mb-2" data-id="${t.id}">
        <div class="text-xs text-gray-400 truncate">#${t.quote_id} ${esc(t.ticket_name || '')}</div>
        <div class="text-sm font-medium text-gray-800">${esc(t.title)}</div>
        <div class="flex items-center justify-between mt-2">
            <span class="px-1.5 py-0.5 rounded text-[10px] ${PRI[t.priority] || PRI.medium}">${esc(t.priority)}</span>
            <div class="flex items-center gap-1">${av}
                <select onchange="assign(${t.id}, this.value)" onclick="event.stopPropagation()" class="text-[10px] border rounded w-5 opacity-60" title="reassign">
                    <option value=""></option>${DATA.engineers.map(e => `<option value="${e.id}" ${e.id==t.assigned_to?'selected':''}>${esc(e.nick)}</option>`).join('')}
                </select></div>
        </div>
        <div class="mt-2 flex items-center gap-2">
            <input type="range" min="0" max="100" step="10" value="${t.progress_pct}" class="flex-1 h-1"
                   onchange="setProgress(${t.id}, this.value)" onclick="event.stopPropagation()">
            <span class="text-[10px] text-gray-500 w-8">${t.progress_pct}%</span>
        </div>
        <div class="text-[10px] text-gray-400 mt-1">${esc(t.start_date||'')} → ${esc(t.end_date||'')} · ${t.estimated_hours}h est</div>
    </div>`;
}

function renderTasks() {
    $('view-tasks').innerHTML = TASK_COLS.map(([key, label, bg]) => {
        const cards = DATA.tasks.filter(t => t.status === key);
        return `<div class="${bg} rounded-xl p-2">
            <div class="flex justify-between px-1 mb-2"><b class="text-sm text-gray-700">${label}</b><span class="text-xs text-gray-400">${cards.length}</span></div>
            <div class="kcol" data-status="${key}">${cards.map(taskCard).join('')}</div></div>`;
    }).join('');
    document.querySelectorAll('#view-tasks .kcol').forEach(col => {
        new Sortable(col, {group: 'tasks', animation: 150, onEnd: async ev => {
            const taskId = +ev.item.dataset.id;
            const status = ev.to.dataset.status;
            const sort = [...ev.to.querySelectorAll('.kcard')].map(c => +c.dataset.id);
            await api('move_task', {task_id: taskId, status, sort});
            refresh();
        }});
    });
}

function renderTickets() {
    $('view-tickets').innerHTML = TICKET_COLS.map(([key, label, bg]) => {
        const cards = DATA.tickets.filter(t => t.status === key);
        return `<div class="${bg} rounded-xl p-2">
            <div class="flex justify-between px-1 mb-2"><b class="text-sm text-gray-700">${label}</b><span class="text-xs text-gray-400">${cards.length}</span></div>
            <div class="kcol" data-status="${key}">${cards.map(q => `
                <div class="kcard bg-white rounded-lg shadow p-3 mb-2" data-id="${q.id}">
                    <div class="text-sm font-medium text-gray-800">#${q.id} ${esc(q.product_type)}
                        <a href="work-order.php?quote=${q.id}" onclick="event.stopPropagation()" class="text-blue-600 text-xs float-right" title="costing / invoice">💲</a></div>
                    <div class="text-xs text-gray-400 truncate">${esc(q.customer || '')}${q.source_order_id ? ' · order #' + q.source_order_id : ''}</div>
                    <div class="flex items-center justify-between mt-2">
                        <span class="px-1.5 py-0.5 rounded text-[10px] ${PRI[q.priority] || PRI.medium}">${esc(q.priority)}</span>
                        <span class="text-[10px] text-gray-500">${q.done_count}/${q.task_count} tasks</span>
                    </div>
                    <div class="mt-1 bg-gray-200 rounded h-1.5"><div class="bg-green-500 h-1.5 rounded" style="width:${Math.round(q.avg_progress)}%"></div></div>
                </div>`).join('')}</div></div>`;
    }).join('');
    document.querySelectorAll('#view-tickets .kcol').forEach(col => {
        new Sortable(col, {group: 'tickets', animation: 150, onEnd: async ev => {
            await api('move_ticket', {quote_id: +ev.item.dataset.id, status: ev.to.dataset.status});
            refresh();
        }});
    });
}

function renderGantt() {
    const tasks = DATA.tasks.filter(t => t.start_date && t.end_date);
    if (!tasks.length) { $('view-gantt').innerHTML = '<p class="text-gray-400 text-sm">No dated tasks yet.</p>'; return; }
    const min = new Date(Math.min(...tasks.map(t => new Date(t.start_date))));
    const max = new Date(Math.max(...tasks.map(t => new Date(t.end_date))));
    const days = Math.max(7, Math.round((max - min) / 864e5) + 1);
    const today = Math.round((new Date().setHours(0,0,0,0) - min) / 864e5);
    const COLOR = {new:'bg-gray-400', assigned:'bg-blue-500', in_progress:'bg-amber-500', review:'bg-purple-500', completed:'bg-green-500'};

    let head = `<div class="gantt-grid text-[10px] text-gray-400 mb-1" style="grid-template-columns:14rem repeat(${days},minmax(1.4rem,1fr))"><div></div>`;
    for (let i = 0; i < days; i++) { const d = new Date(+min + i * 864e5);
        head += `<div class="text-center ${i===today?'text-red-600 font-bold':''}">${d.getDate()===1||i===0 ? d.toLocaleString('en',{month:'short'})+' ' : ''}${d.getDate()}</div>`; }
    head += '</div>';

    const rows = tasks.map(t => {
        const s = Math.max(0, Math.round((new Date(t.start_date) - min) / 864e5));
        const e = Math.min(days - 1, Math.round((new Date(t.end_date) - min) / 864e5));
        return `<div class="gantt-grid items-center mb-1" style="grid-template-columns:14rem repeat(${days},minmax(1.4rem,1fr))">
            <div class="text-xs text-gray-700 pr-2 truncate">${esc(t.title)} <span class="text-gray-400">· ${esc(t.assignee || '—')}</span></div>
            <div class="relative h-5 rounded" style="grid-column:${s + 2} / ${e + 3}">
                <div class="absolute inset-0 ${COLOR[t.status]} rounded opacity-30"></div>
                <div class="absolute inset-y-0 left-0 ${COLOR[t.status]} rounded" style="width:${t.progress_pct}%"></div>
                <div class="absolute inset-0 flex items-center justify-center text-[9px] text-gray-800">${t.progress_pct}%</div>
            </div></div>`;
    }).join('');

    $('view-gantt').innerHTML = `<div class="min-w-[56rem]">${head}${rows}</div>
        <div class="flex gap-3 mt-3 text-[10px] text-gray-500">${Object.entries(COLOR).map(([k,c]) => `<span class="flex items-center gap-1"><span class="w-3 h-3 rounded ${c}"></span>${k.replace('_',' ')}</span>`).join('')}
        <span class="ml-auto">solid = progress · faded = planned span · red date = today</span></div>`;
}

window.assign = async (taskId, userId) => { await api('assign', {task_id: taskId, user_id: userId ? +userId : 0}); refresh(); };
window.setProgress = async (taskId, pct) => { await api('progress', {task_id: taskId, pct: +pct}); refresh(); };

async function refresh() {
    DATA = await api('bootstrap');
    $('tskTicket').innerHTML = DATA.tickets.map(q => `<option value="${q.id}">#${q.id} ${esc(q.product_type)}</option>`).join('');
    $('tskAssignee').innerHTML = '<option value="">unassigned</option>' + DATA.engineers.map(e => `<option value="${e.id}">${esc(e.nick)}</option>`).join('');
    renderTasks(); renderTickets(); renderGantt();
}

document.querySelectorAll('.tab').forEach(b => b.onclick = () => {
    document.querySelectorAll('.tab').forEach(x => x.className = 'tab bg-white border px-4 py-1.5 rounded');
    b.className = 'tab bg-blue-600 text-white px-4 py-1.5 rounded';
    document.querySelectorAll('.view').forEach(v => v.classList.add('hidden'));
    $('view-' + b.dataset.tab).classList.remove('hidden');
});

$('newTicketForm').onsubmit = async e => {
    e.preventDefault();
    await api('create_ticket', {product_type: $('tkName').value, order_id: +$('tkOrder').value || 0, priority: $('tkPriority').value});
    $('tkName').value = ''; $('tkOrder').value = '';
    refresh();
};
$('newTaskForm').onsubmit = async e => {
    e.preventDefault();
    await api('create_task', {quote_id: +$('tskTicket').value, title: $('tskTitle').value, assigned_to: +$('tskAssignee').value || 0,
        estimated_hours: +$('tskHours').value, start_date: $('tskStart').value, end_date: $('tskEnd').value});
    $('tskTitle').value = '';
    refresh();
};

refresh();
</script>
</body>
</html>
