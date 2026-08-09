<?php
/**
 * Unified admin navigation bar. Include right after <body> on admin pages:
 *   <?php include __DIR__ . '/nav.php'; ?>
 * Pure HTML/CSS (details dropdowns) — no JS dependency; hidden when printing.
 */
$navCurrent = basename($_SERVER['PHP_SELF']);
$navGroups = [
    'Catalog' => [
        'catalog-dashboard.php' => 'Catalog Manager',
        'products.php' => 'Products',
        'print-item-tickets.php' => 'Item Tickets',
    ],
    'Inventory' => [
        'warehouse-map.php' => 'Warehouse 2D Map',
        'warehouse-3d.php' => 'Warehouse 3D',
        'scan.php' => 'Scan Console',
        'receive-stock.php' => 'Receive Stock',
        'inventory-batches.php' => 'Batches & Expiry',
        'cycle-count-dashboard.php' => 'Cycle Counts & Loss',
        'print-bin-labels.php' => 'Bin Labels',
    ],
    'Purchasing' => [
        'po-manage.php' => 'Purchase Orders',
        'create-po.php' => 'New PO',
        'reorder-alerts.php' => 'Reorder Alerts',
        'suppliers.php' => 'Suppliers',
        'supplier-scorecard.php' => 'Supplier Scorecard',
    ],
    'Orders' => [
        'orders.php' => 'All Orders',
        'returns.php' => 'Returns / RMA',
        'shipping-management.php' => 'Shipping',
        'shipping-rates.php' => 'Shipping Rates',
    ],
    'Sellers' => [
        'merchants.php' => 'Sellers',
        'merchant-credibility.php' => 'Credibility',
        'merchant-payouts.php' => 'Payouts',
    ],
    'Finance' => [
        'accounting-hub.php' => 'Accounting Hub',
        'financial-statements.php' => 'Statements',
        'financial-kpis.php' => 'KPIs',
        'tax-report.php' => 'Tax Report',
        'bank-reconciliation.php' => 'Bank Reconciliation',
        'oxxo-payments.php' => 'OXXO Payments',
        'accounts-payable.php' => 'A/P',
        'accounts-receivable.php' => 'A/R',
    ],
    'Marketing' => [
        'marketing.php' => 'Marketing Hub',
    ],
    'Engineering' => [
        'engineering-board.php' => 'PM Board (Kanban + Gantt)',
        'work-order.php' => 'Work Order Costing',
        '../engineering-dashboard.php' => 'Engineer Tasks',
        '../sales-dashboard.php' => 'Sales Quotes',
        '../gantt-view.php' => 'Legacy Gantt',
    ],
    'HR' => [
        'hr-dashboard.php' => 'Employees & Hiring',
        'hr-payroll.php' => 'Payroll',
        'hr-leave.php' => 'Leave & Overtime',
    ],
    'Site' => [
        'chat.php' => 'Team Chat',
        'users.php' => 'Customers',
        'audit-log.php' => 'Audit Log',
        'cms-dashboard.php' => 'CMS',
        'homepage-manager.php' => 'Homepage',
        'settings.php' => 'Settings',
    ],
];

// Hide nav groups a scoped staff member can't open (full admins see all).
$navScope = function_exists('getUserScope') ? getUserScope() : 'all';
$navGroupScope = [
    'Catalog' => 'catalog', 'Inventory' => 'warehouse', 'Purchasing' => 'purchasing',
    'Orders' => 'orders', 'Sellers' => 'sellers', 'Finance' => 'finance', 'HR' => 'hr',
    'Engineering' => 'engineering', 'Marketing' => 'marketing', 'Site' => 'all',
];
if ($navScope !== 'all') {
    $navGroups = array_filter($navGroups, function ($g) use ($navGroupScope, $navScope) {
        return ($navGroupScope[$g] ?? 'all') === $navScope;
    }, ARRAY_FILTER_USE_KEY);
}
?>
<style>
    .vd-nav details { position: relative; }
    .vd-nav details > summary { list-style: none; cursor: pointer; }
    .vd-nav details > summary::-webkit-details-marker { display: none; }
    .vd-nav details[open] > .vd-menu { display: block; }
    .vd-menu { position: absolute; left: 0; top: 100%; z-index: 60; min-width: 13rem; }
    @media print { .vd-nav { display: none !important; } }
</style>
<nav class="vd-nav bg-gray-900 text-gray-100 no-print">
    <div class="max-w-7xl mx-auto px-4 flex items-center gap-1 h-12 text-sm">
        <a href="dashboard.php" class="font-bold text-white mr-3 whitespace-nowrap">Vent<span class="text-blue-400">Depot</span> Admin</a>
        <a href="dashboard.php" class="px-3 py-1.5 rounded hover:bg-gray-700 <?= $navCurrent==='dashboard.php'?'bg-gray-700':'' ?>">Dashboard</a>
        <?php if (getUserScope() === 'all'): ?>
        <a href="executive.php" class="px-3 py-1.5 rounded hover:bg-gray-700 <?= $navCurrent==='executive.php'?'bg-gray-700':'' ?>">Executive</a>
        <?php endif; ?>
        <?php foreach ($navGroups as $group => $links): ?>
            <?php $active = array_key_exists($navCurrent, $links); ?>
            <details <?= $active ? 'open' : '' ?>>
                <summary class="px-3 py-1.5 rounded hover:bg-gray-700 <?= $active?'bg-gray-700 text-white':'' ?>"><?= $group ?> ▾</summary>
                <div class="vd-menu bg-white text-gray-800 rounded-lg shadow-xl py-1 mt-1">
                    <?php foreach ($links as $file => $label): ?>
                        <a href="<?= $file ?>" class="block px-4 py-1.5 hover:bg-blue-50 <?= $navCurrent===$file?'text-blue-600 font-semibold':'' ?>"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endforeach; ?>
        <div class="ml-auto flex items-center gap-2 whitespace-nowrap">
            <!-- Global search: orders, products, users, suppliers, POs, tickets -->
            <div class="relative">
                <input id="vdGlobalSearch" placeholder="🔍 order #, SKU, email…" autocomplete="off"
                       class="bg-gray-800 text-gray-100 placeholder-gray-500 rounded px-3 py-1.5 w-44 focus:w-64 transition-all focus:outline-none focus:ring-1 focus:ring-blue-500 text-sm">
                <div id="vdGlobalResults" class="hidden absolute right-0 top-full mt-1 w-96 max-w-[90vw] bg-white text-gray-800 rounded-lg shadow-2xl border border-gray-100 z-[70] max-h-[70vh] overflow-y-auto"></div>
            </div>
            <a href="../index.php" class="px-3 py-1.5 rounded hover:bg-gray-700 text-gray-300">View store ↗</a>
            <a href="../logout.php" class="px-3 py-1.5 rounded bg-gray-700 hover:bg-gray-600">Logout</a>
        </div>
    </div>
</nav>
<script>
// close open dropdowns when clicking elsewhere
document.addEventListener('click', function (e) {
    document.querySelectorAll('.vd-nav details[open]').forEach(function (d) {
        if (!d.contains(e.target)) d.removeAttribute('open');
    });
    var res = document.getElementById('vdGlobalResults');
    if (res && !res.contains(e.target) && e.target.id !== 'vdGlobalSearch') res.classList.add('hidden');
});

// global admin search (debounced)
(function () {
    var input = document.getElementById('vdGlobalSearch');
    var panel = document.getElementById('vdGlobalResults');
    if (!input) return;
    var t = null;
    function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
    input.addEventListener('input', function () {
        clearTimeout(t);
        var q = input.value.trim();
        if (q.length < 2) { panel.classList.add('hidden'); return; }
        t = setTimeout(function () {
            fetch('api/global-search.php?q=' + encodeURIComponent(q))
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d.groups || !d.groups.length) {
                        panel.innerHTML = '<div class="px-4 py-3 text-sm text-gray-400">No matches for “' + esc(q) + '”</div>';
                    } else {
                        panel.innerHTML = d.groups.map(function (g) {
                            return '<div class="px-4 pt-2 pb-1 text-[10px] uppercase tracking-wide text-gray-400 border-t first:border-0">' + esc(g.label) + '</div>'
                                 + g.items.map(function (it) {
                                       return '<a href="' + esc(it.url) + '" class="block px-4 py-1.5 hover:bg-blue-50">'
                                            + '<div class="text-sm text-gray-800">' + esc(it.title) + '</div>'
                                            + '<div class="text-xs text-gray-400">' + esc(it.sub) + '</div></a>';
                                   }).join('');
                        }).join('');
                    }
                    panel.classList.remove('hidden');
                });
        }, 250);
    });
})();
</script>
