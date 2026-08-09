<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'services/OxxoPaymentService.php';

if (!isLoggedIn()) { header('Location: login.php'); exit; }
$orderId = (int) ($_GET['order_id'] ?? 0);

// Must be the customer's own order.
$o = $pdo->prepare("SELECT o.id, o.total, o.payment_status FROM orders o WHERE o.id = ? AND o.user_id = ?");
$o->execute([$orderId, $_SESSION['user_id']]);
$order = $o->fetch(PDO::FETCH_ASSOC);
if (!$order) { header('Location: orders.php'); exit; }

$v = $pdo->prepare("SELECT * FROM oxxo_payments WHERE order_id = ?");
$v->execute([$orderId]);
$voucher = $v->fetch(PDO::FETCH_ASSOC);
if (!$voucher) { header('Location: checkout.php?order_id=' . $orderId); exit; }

$isPaid = $voucher['status'] === 'paid' || $order['payment_status'] === 'paid';
$ref = $voucher['reference'];
$refPretty = trim(chunk_split($ref, 4, ' '));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago OXXO — Pedido #<?= $orderId ?></title>
    <link rel="stylesheet" href="public/vendor/css/tailwind.min.css">
    <script src="public/vendor/js/JsBarcode.all.min.js"></script>
    <style>@media print { .no-print { display: none } body { background: #fff } }</style>
</head>
<body class="bg-gray-50">
    <div class="no-print"><?php include 'includes/navigation.php'; ?></div>

    <div class="max-w-md mx-auto px-4 py-10">
        <?php if ($isPaid): ?>
            <div class="bg-white rounded-2xl shadow p-8 text-center">
                <div class="text-5xl mb-3">✅</div>
                <h1 class="text-2xl font-bold text-gray-900">¡Pago recibido!</h1>
                <p class="text-gray-500 mt-2">Tu pedido #VD-<?= str_pad($orderId, 6, '0', STR_PAD_LEFT) ?> está en proceso.</p>
                <a href="order-details.php?id=<?= $orderId ?>" class="inline-block mt-5 bg-blue-600 text-white px-6 py-2 rounded-lg">Ver mi pedido</a>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-2xl shadow overflow-hidden">
                <div class="bg-red-600 text-white px-6 py-4 flex items-center justify-between">
                    <span class="text-2xl font-black tracking-tight">OXXO</span>
                    <span class="text-sm">Ficha de pago en efectivo</span>
                </div>
                <div class="p-6 text-center">
                    <p class="text-sm text-gray-500">Monto a pagar</p>
                    <p class="text-4xl font-bold text-gray-900 mb-4">$<?= number_format($order['total'], 2) ?> <span class="text-base text-gray-400">USD</span></p>

                    <svg id="barcode" class="mx-auto"></svg>
                    <p class="font-mono text-xl font-bold tracking-widest mt-2"><?= htmlspecialchars($refPretty) ?></p>

                    <div class="text-left bg-gray-50 rounded-lg p-4 mt-5 text-sm text-gray-600 space-y-1">
                        <p>1. Muestra este código de barras (o dicta la referencia) en la caja de <b>cualquier OXXO</b>.</p>
                        <p>2. Paga en efectivo el monto exacto.</p>
                        <p>3. Guarda tu ticket — tu pedido se procesa al confirmarse el pago.</p>
                    </div>
                    <p class="text-xs text-red-600 mt-3">⏳ Vence: <?= date('d/M/Y H:i', strtotime($voucher['expires_at'])) ?></p>

                    <div class="flex gap-2 mt-5 no-print">
                        <button onclick="window.print()" class="flex-1 bg-gray-800 text-white py-2.5 rounded-lg">🖨 Imprimir ficha</button>
                        <a href="orders.php" class="flex-1 border border-gray-300 text-gray-700 py-2.5 rounded-lg text-center">Mis pedidos</a>
                    </div>
                </div>
            </div>
            <script>JsBarcode('#barcode', <?= json_encode($ref) ?>, {format: 'CODE128', height: 70, displayValue: false, margin: 0});</script>
        <?php endif; ?>
    </div>
</body>
</html>
