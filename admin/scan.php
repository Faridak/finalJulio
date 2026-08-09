<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
requireRole('admin');
requireScope('warehouse');
$csrf = Security::generateCSRFToken();
// Optional preselect (e.g. from the 3D bin click: scan.php?mode=count&bin=WH001-...)
$preMode = in_array($_GET['mode'] ?? '', ['in', 'out', 'count'], true) ? $_GET['mode'] : 'in';
$preBin = trim($_GET['bin'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan Console - VentDepot</title>
    <link rel="stylesheet" href="../public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
<?php include __DIR__ . '/nav.php'; ?>
<div class="max-w-3xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold text-gray-900 mb-1">Inventory Scan Console</h1>
    <p class="text-sm text-gray-500 mb-4">Works with any USB/Bluetooth scanner (keyboard-wedge) or a phone scanner app. Scan = type + Enter.</p>

    <!-- Mode tabs -->
    <div class="flex gap-2 mb-4">
        <button data-mode="in"    class="mode px-4 py-2 rounded bg-green-600 text-white">Receive (in)</button>
        <button data-mode="out"   class="mode px-4 py-2 rounded bg-gray-200 text-gray-700">Pick / Ship (out)</button>
        <button data-mode="count" class="mode px-4 py-2 rounded bg-gray-200 text-gray-700">Count a bin</button>
    </div>

    <div class="bg-white rounded-lg shadow p-4">
        <div class="grid grid-cols-3 gap-3 mb-3">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Bin <span id="binReq" class="text-red-500">*</span></label>
                <input id="bin" value="<?= htmlspecialchars($preBin) ?>" placeholder="scan bin QR / code" class="w-full px-3 py-2 border rounded font-mono">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Qty</label>
                <input id="qty" type="number" value="1" min="1" class="w-full px-3 py-2 border rounded">
            </div>
            <div class="flex items-end">
                <span id="modeLabel" class="text-sm font-medium text-green-700">Receiving into bin</span>
            </div>
        </div>
        <label class="block text-xs text-gray-500 mb-1">Item barcode / SKU</label>
        <input id="item" autofocus placeholder="scan item…" class="w-full px-4 py-3 border-2 border-blue-400 rounded text-lg font-mono">

        <!-- Count tally -->
        <div id="countPanel" class="hidden mt-4">
            <div class="flex items-center justify-between mb-2">
                <h3 class="font-semibold text-gray-700">Counted in <span id="countBin" class="font-mono"></span></h3>
                <div>
                    <label class="text-sm mr-2"><input type="checkbox" id="applyAdjust"> apply adjustments</label>
                    <button id="reconcile" class="bg-blue-600 text-white px-4 py-1.5 rounded text-sm">Reconcile</button>
                </div>
            </div>
            <table class="w-full text-sm"><tbody id="countRows"></tbody></table>
        </div>
    </div>

    <!-- Log -->
    <h3 class="font-semibold text-gray-700 mt-6 mb-2">Activity</h3>
    <div id="log" class="space-y-1 text-sm"></div>
</div>

<script>
const CSRF = <?= json_encode($csrf) ?>;
let mode = 'in';
const $ = id => document.getElementById(id);
const counts = {};   // itemCode -> qty (count mode)

function setMode(m){
    mode = m;
    document.querySelectorAll('.mode').forEach(b=>{
        const on = b.dataset.mode===m;
        b.className = 'mode px-4 py-2 rounded ' + (on ? (m==='in'?'bg-green-600':m==='out'?'bg-amber-600':'bg-blue-600')+' text-white' : 'bg-gray-200 text-gray-700');
    });
    $('modeLabel').textContent = m==='in'?'Receiving into bin':m==='out'?'Picking / shipping out':'Counting a bin';
    $('modeLabel').className = 'text-sm font-medium ' + (m==='in'?'text-green-700':m==='out'?'text-amber-700':'text-blue-700');
    $('binReq').style.display = (m==='out') ? 'none' : 'inline';   // bin optional for pick
    $('countPanel').classList.toggle('hidden', m!=='count');
    for(const k in counts) delete counts[k]; renderCounts();
    $('item').focus();
}
document.querySelectorAll('.mode').forEach(b=>b.onclick=()=>setMode(b.dataset.mode));

function logLine(ok, text){
    const d=document.createElement('div');
    d.className = 'px-3 py-2 rounded ' + (ok?'bg-green-50 text-green-800':'bg-red-50 text-red-700');
    d.textContent = text; $('log').prepend(d);
}

async function api(action, body){
    const res = await fetch('api/scan.php', {method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify(Object.assign({action, csrf_token:CSRF}, body))});
    return res.json();
}

function renderCounts(){
    $('countRows').innerHTML = Object.entries(counts).map(([c,q])=>`<tr class="border-t"><td class="py-1 font-mono">${c}</td><td class="text-right">${q}</td></tr>`).join('');
}

$('item').addEventListener('keydown', async (e)=>{
    if(e.key!=='Enter') return;
    const item = $('item').value.trim(); if(!item) return;
    const bin = $('bin').value.trim();
    const qty = parseInt($('qty').value)||1;
    $('item').value='';

    if(mode==='count'){
        if(!bin){ logLine(false,'Scan the bin first.'); return; }
        $('countBin').textContent = bin;
        counts[item] = (counts[item]||0) + qty; renderCounts();
        logLine(true, `counted ${item} × ${qty}`);
        return;
    }
    if(mode==='in' && !bin){ logLine(false,'Scan a bin to receive into.'); return; }

    const r = await api(mode, {item, bin: bin||null, qty});
    if(r.success){
        if(mode==='in') logLine(true, `IN  ${r.sku} (${r.product}) ×${r.qty} → ${r.bin}`);
        else logLine(true, `OUT ${r.sku} (${r.product}) ×${r.qty}` + (r.from&&r.from[0]?` from ${r.from[0].bin_address}`:''));
    } else logLine(false, r.error||'error');
    $('item').focus();
});

$('reconcile').addEventListener('click', async ()=>{
    const bin = $('bin').value.trim(); if(!bin){ logLine(false,'No bin.'); return; }
    const r = await api('count', {bin, items:counts, apply: $('applyAdjust').checked});
    if(!r.success){ logLine(false, r.error); return; }
    if(r.matched){ logLine(true, `✓ ${r.bin} matches system exactly.`); }
    else {
        r.variance.forEach(v=>logLine(false, `Δ ${v.product}: system ${v.system} vs counted ${v.counted} (${v.delta>0?'+':''}${v.delta})`));
        if(r.applied) logLine(true, 'Adjustments applied to match count.');
    }
});

setMode(<?= json_encode($preMode) ?>);
if (<?= json_encode($preBin !== '') ?>) { $('countBin').textContent = <?= json_encode($preBin) ?>; }
</script>
</body>
</html>
