<?php
/**
 * Step 1 of the password reset: request a link.
 *
 * A random token is stored (hashed) together with an expiry time. The link is
 * emailed with mail(); while developing locally it is also shown on screen so
 * the flow can be tested without a mail server.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

$message = '';
$resetLink = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim((string) ($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
    } else {
        $stmt = $conn->prepare('SELECT id, email FROM users WHERE email = ? LIMIT 1');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Always answer the same way, so the form cannot be used to discover
        // which email addresses have accounts.
        $message = 'If that email belongs to an account, a reset link has been sent.';

        if ($user) {
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);
            $expires = date('Y-m-d H:i:s', time() + 3600);

            $conn->query('DELETE FROM password_resets WHERE user_id = ' . (int) $user['id']);

            $insert = $conn->prepare(
                'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
            );
            $insert->bind_param('iss', $user['id'], $hash, $expires);
            $insert->execute();
            $insert->close();

            $resetLink = APP_URL . '/reset_password.php?token=' . $token;

            $subject = APP_NAME . ' — password reset';
            $body = "Hello,\n\nUse the link below within one hour to choose a new password:\n\n"
                . $resetLink . "\n\nIf you did not ask for this, you can ignore this email.\n";
            $headers = 'From: no-reply@campus-canteen.local' . "\r\n"
                . 'Content-Type: text/plain; charset=UTF-8';

            @mail($user['email'], $subject, $body, $headers);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot password — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">

<div class="auth-shell">
    <section class="auth-hero">
        <span class="pill">🍴 <?= e(APP_NAME) ?></span>
        <h1>Forgot your<br>password?</h1>
        <p>Enter the email you registered with and we will send you a link to set a new password.</p>
    </section>

    <section class="auth-card">
        <h2>Reset password</h2>
        <p class="muted">The link is valid for one hour.</p>

        <?php if ($message !== ''): ?>
            <div class="alert alert-info"><?= e($message) ?></div>
        <?php endif; ?>

        <?php if ($resetLink !== '' && is_local()): ?>
            <div class="alert alert-success">
                <strong>Development mode:</strong> email sending is disabled on localhost, so use this link:<br>
                <a href="<?= e($resetLink) ?>"><?= e($resetLink) ?></a>
            </div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>"
                       autocomplete="email" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Send reset link</button>
        </form>

        <p class="auth-foot"><a href="login.php">← Back to login</a></p>
    </section>
</div>

</body>
</html>
