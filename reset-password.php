<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$email = trim($_GET['email'] ?? $_POST['email'] ?? '');
$error = ''; $done = false;

// Validate the token (hashed, unexpired, unused, matching email).
function findReset(PDO $pdo, $email, $token)
{
    if ($token === '' || $email === '') return null;
    $s = $pdo->prepare("
        SELECT * FROM password_resets
        WHERE email = ? AND token_hash = ? AND used_at IS NULL AND expires_at > NOW()
        ORDER BY id DESC LIMIT 1");
    $s->execute([$email, hash('sha256', $token)]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

$reset = findReset($pdo, $email, $token);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) { http_response_code(403); die('CSRF mismatch'); }
    if (!$reset) {
        $error = 'This reset link is invalid or has expired. Request a new one.';
    } else {
        $p1 = $_POST['password'] ?? ''; $p2 = $_POST['password2'] ?? '';
        if (strlen($p1) < 8) $error = 'Password must be at least 8 characters.';
        elseif ($p1 !== $p2) $error = 'Passwords do not match.';
        else {
            $pdo->prepare("UPDATE users SET password = ? WHERE email = ?")
                ->execute([password_hash($p1, PASSWORD_DEFAULT), $email]);
            $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")->execute([$reset['id']]);
            // Invalidate any other outstanding tokens for this account.
            $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE email = ? AND used_at IS NULL")->execute([$email]);
            Security::logSecurityEvent('password_reset_completed', ['email' => $email]);
            $done = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - VentDepot</title>
    <link rel="stylesheet" href="public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
    <div class="max-w-md mx-auto px-4 py-16">
        <a href="index.php" class="block text-center text-2xl font-bold text-blue-600 mb-6">VentDepot</a>
        <div class="bg-white rounded-2xl shadow p-8">
            <?php if ($done): ?>
                <div class="text-center">
                    <div class="text-4xl mb-3">✅</div>
                    <h1 class="text-xl font-bold text-gray-900">Password updated</h1>
                    <p class="text-gray-500 text-sm mt-2">You can now sign in with your new password.</p>
                    <a href="login.php" class="inline-block mt-5 bg-blue-600 text-white px-6 py-2.5 rounded-lg font-semibold">Sign in</a>
                </div>
            <?php elseif (!$reset): ?>
                <div class="text-center">
                    <div class="text-4xl mb-3">⛔</div>
                    <h1 class="text-xl font-bold text-gray-900">Link invalid or expired</h1>
                    <p class="text-gray-500 text-sm mt-2">Reset links are valid for 1 hour and can be used once.</p>
                    <a href="forgot-password.php" class="inline-block mt-5 text-blue-600 hover:underline text-sm">Request a new link</a>
                </div>
            <?php else: ?>
                <h1 class="text-xl font-bold text-gray-900 mb-1">Choose a new password</h1>
                <p class="text-gray-500 text-sm mb-5">for <b><?= htmlspecialchars($email) ?></b></p>
                <?php if ($error): ?><div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2 rounded mb-3 text-sm"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <form method="POST" class="space-y-4">
                    <?= Security::getCSRFInput() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">
                    <input type="password" name="password" required minlength="8" placeholder="New password (min. 8 characters)"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <input type="password" name="password2" required minlength="8" placeholder="Repeat new password"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg">Set new password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
<script src="public/vendor/js/motion.min.js" defer></script>
</body>
</html>
