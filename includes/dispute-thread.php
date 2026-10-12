<?php
/**
 * Shared dispute view (buyer / seller / admin). Expects:
 *   $d     — DisputeService::get() row
 *   $as    — 'buyer' | 'seller' | 'admin' (the viewer)
 * Renders header facts, disputed items and the message thread. Action forms
 * (reply / escalate / resolve) are rendered by each page.
 */
$statusLabels = [
    'open'             => ['Awaiting seller', 'bg-yellow-100 text-yellow-800'],
    'seller_responded' => ['Seller responded', 'bg-blue-100 text-blue-800'],
    'under_review'     => ['Under review by VentDepot', 'bg-purple-100 text-purple-800'],
    'resolved_buyer'   => ['Resolved — buyer', 'bg-green-100 text-green-800'],
    'resolved_seller'  => ['Resolved — seller', 'bg-gray-200 text-gray-800'],
    'closed'           => ['Closed', 'bg-gray-200 text-gray-700'],
];
[$stLabel, $stClass] = $statusLabels[$d['status']] ?? [$d['status'], 'bg-gray-100 text-gray-700'];
$roleNames = ['buyer' => 'Buyer', 'seller' => 'Seller', 'admin' => 'VentDepot'];
$disputedTotal = 0;
foreach ($d['items'] as $it) { $disputedTotal += $it['quantity'] * $it['price_at_purchase']; }
?>
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Dispute #<?= (int) $d['id'] ?></h1>
            <p class="text-gray-600 text-sm mt-1">
                Order #<?= str_pad((int) $d['order_id'], 6, '0', STR_PAD_LEFT) ?>
                · Seller: <?= htmlspecialchars($d['seller_name']) ?>
                · Opened <?= date('M j, Y', strtotime($d['created_at'])) ?>
            </p>
        </div>
        <span class="px-3 py-1 rounded-full text-sm font-medium <?= $stClass ?>"><?= htmlspecialchars($stLabel) ?></span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 text-sm">
        <div><div class="text-gray-500">Reason</div><div class="font-medium"><?= htmlspecialchars(DisputeService::REASONS[$d['reason']] ?? $d['reason']) ?></div></div>
        <div><div class="text-gray-500">Buyer is asking for</div><div class="font-medium">
            <?= htmlspecialchars(DisputeService::OUTCOMES[$d['requested_outcome']] ?? $d['requested_outcome']) ?>
            <?php if ($d['amount_requested'] !== null): ?> ($<?= number_format($d['amount_requested'], 2) ?>)<?php endif; ?>
        </div></div>
        <div><div class="text-gray-500">Disputed items</div><div class="font-medium">$<?= number_format($disputedTotal, 2) ?></div></div>
    </div>

    <?php if ($d['status'] === 'open'): ?>
        <p class="mt-4 text-sm <?= $d['seller_overdue'] ? 'text-red-600' : 'text-gray-600' ?>">
            Seller response due <?= date('M j, Y g:ia', strtotime($d['seller_due_at'])) ?><?= $d['seller_overdue'] ? ' — overdue' : '' ?>.
        </p>
    <?php endif; ?>

    <?php if (in_array($d['status'], ['resolved_buyer', 'resolved_seller', 'closed'], true) && $d['resolution_notes']): ?>
        <div class="mt-4 p-4 bg-gray-50 border border-gray-200 rounded">
            <div class="text-sm font-semibold text-gray-800 mb-1">Decision</div>
            <div class="text-sm text-gray-700"><?= nl2br(htmlspecialchars($d['resolution_notes'])) ?></div>
            <?php if ($d['return_request_id']): ?>
                <div class="text-sm text-gray-700 mt-2">
                    Refund of $<?= number_format((float) $d['return_refund_amount'], 2) ?> —
                    <?= $d['return_status'] === 'refunded' ? 'refunded' : 'approved, being processed' ?>.
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <table class="w-full text-sm mt-6">
        <thead><tr class="text-left text-gray-500 border-b"><th class="py-2">Item</th><th class="py-2 text-right">Qty</th><th class="py-2 text-right">Price</th></tr></thead>
        <tbody>
        <?php foreach ($d['items'] as $it): ?>
            <tr class="border-b border-gray-100">
                <td class="py-2"><?= htmlspecialchars($it['name']) ?></td>
                <td class="py-2 text-right"><?= (int) $it['quantity'] ?></td>
                <td class="py-2 text-right">$<?= number_format($it['price_at_purchase'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">Conversation</h2>
    <div class="space-y-4">
        <?php foreach ($d['messages'] as $m): $mine = $m['author_role'] === $as; ?>
            <div class="flex <?= $mine ? 'justify-end' : 'justify-start' ?>">
                <div class="max-w-xl">
                    <div class="text-xs text-gray-500 mb-1 <?= $mine ? 'text-right' : '' ?>">
                        <?= $roleNames[$m['author_role']] ?? $m['author_role'] ?> · <?= date('M j, g:ia', strtotime($m['created_at'])) ?>
                    </div>
                    <div class="rounded-lg px-4 py-2 text-sm <?= $m['author_role'] === 'admin' ? 'bg-purple-50 border border-purple-200 text-gray-900' : ($mine ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-900') ?>">
                        <?= nl2br(htmlspecialchars($m['body'])) ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
