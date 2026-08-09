<?php
// A/B test CTA click counter (beacon).
session_start();
require_once __DIR__ . '/../config/database.php';
$t = (int) ($_GET['t'] ?? 0);
$v = ($_GET['v'] ?? '') === 'b' ? 'b' : 'a';
// one click per session per test
if ($t && empty($_SESSION["ab_clicked_$t"]) && ($_SESSION['ab_variant'] ?? '') === $v) {
    $pdo->exec("UPDATE ab_tests SET clicks_$v = clicks_$v + 1 WHERE id = $t AND status='running'");
    $_SESSION["ab_clicked_$t"] = 1;
}
header('Content-Type: application/json');
echo '{"ok":true}';
