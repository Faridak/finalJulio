<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';
require_once 'services/EmailService.php';

$done = false; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) { http_response_code(419); die('CSRF mismatch'); }
    if (!Security::checkRateLimit('password_reset', 5, 3600)) {
        $error = 'Too many reset requests — try again later.';
    } else {
        $email = trim($_POST['email'] ?? '');
        // Anti-enumeration: always show success; only actually send if the account exists.
        $u = $pdo->prepare("SELECT id, email FROM users WHERE email = ?");
        $u->execute([$email]);
        if ($user = $u->fetch(PDO::FETCH_ASSOC)) {
            $token = bin2hex(random_bytes(32));
            $pdo->prepare("INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))")
                ->execute([$user['email'], hash('sha256', $token)]);
            $base = rtrim($_ENV['APP_URL'] ?? ((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF'])), '/');
            $link = $base . '/reset-password.php?token=' . $token . '&email=' . urlencode($user['email']);
            $html = '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto">'
                  . '<h2 style="color:#2563eb">Reset your VentDepot password</h2>'
                  . '<p>We received a request to reset the password for this account. This link is valid for <b>1 hour</b>:</p>'
                  . '<p><a href="' . htmlspecialchars($link) . '" style="background:#2563eb;color:#fff;padding:12px 22px;border-radius:8px;text-decoration:none;display:inline-block">Choose a new password</a></p>'
                  . '<p style="color:#888;font-size:12px">If you didn\'t request this, you can safely ignore this email — your password stays the same.</p></div>';
            (new EmailService($pdo))->queue($user['email'], null, 'Reset your VentDepot password', $html, 'password_reset', 'user', $user['id']);
            Security::logSecurityEvent('password_reset_requested', ['email' => $user['email']]);
        }
        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - VentDepot</title>
    <link rel="stylesheet" href="public/vendor/css/tailwind.min.css">
</head>
<body class="bg-gray-50">
    <div class="max-w-md mx-auto px-4 py-16">
        <a href="index.php" class="block text-center text-2xl font-bold text-blue-600 mb-6">VentDepot</a>
        <div class="bg-white rounded-2xl shadow p-8">
            <?php if ($done): ?>
                <div class="text-center">
                    <div class="text-4xl mb-3">📬</div>
                    <h1 class="text-xl font-bold text-gray-900">Check your email</h1>
                    <p class="text-gray-500 text-sm mt-2">If an account exists for that address, we've sent a link to reset your password. The link expires in 1 hour.</p>
                    <a href="login.php" class="inline-block mt-5 text-blue-600 hover:underline text-sm">← Back to sign in</a>
                </div>
            <?php else: ?>
                <h1 class="text-xl font-bold text-gray-900 mb-1">Forgot your password?</h1>
                <p class="text-gray-500 text-sm mb-5">Enter your account email and we'll send you a reset link.</p>
                <?php if ($error): ?><div class="bg-red-50 border border-red-300 text-red-700 px-3 py-2 rounded mb-3 text-sm"><?= htmlspecialchars($error) ?></div><?php endif; ?>
                <form method="POST" class="space-y-4">
                    <?= Security::getCSRFInput() ?>
                    <input type="email" name="email" required placeholder="you@example.com"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg">Send reset link</button>
                </form>
                <a href="login.php" class="block text-center mt-4 text-sm text-gray-500 hover:text-blue-600">← Back to sign in</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
