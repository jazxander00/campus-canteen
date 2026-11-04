<?php
/**
 * Step 2 of the password reset: choose a new password using a valid token.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$error = '';
$done = false;

/**
 * @return array<string, mixed>|null
 */
function find_reset(mysqli $conn, string $token): ?array
{
    if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
        return null;
    }

    $hash = hash('sha256', $token);
    $stmt = $conn->prepare(
        'SELECT pr.id, pr.user_id, pr.expires_at, pr.used_at, u.email
           FROM password_resets pr
           JOIN users u ON u.id = pr.user_id
          WHERE pr.token_hash = ?
          LIMIT 1'
    );
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || $row['used_at'] !== null || strtotime((string) $row['expires_at']) < time()) {
        return null;
    }

    return $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    $reset = find_reset($conn, $token);

    if (!$reset) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'The two passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $update = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
        $update->bind_param('si', $hash, $reset['user_id']);
        $update->execute();
        $update->close();

        $mark = $conn->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
        $mark->bind_param('i', $reset['id']);
        $mark->execute();
        $mark->close();

        $done = true;
    }
} else {
    if (!find_reset($conn, $token)) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose a new password — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">

<div class="auth-shell">
    <section class="auth-hero">
        <span class="pill">🍴 <?= e(APP_NAME) ?></span>
        <h1>Choose a new<br>password.</h1>
        <p>Use at least eight characters. A long passphrase is easier to remember than a short password.</p>
    </section>

    <section class="auth-card">
        <h2>New password</h2>
        <p class="muted">You will be asked to log in again afterwards.</p>

        <?php if ($done): ?>
            <div class="alert alert-success">
                Your password has been changed.
                <a href="login.php?reset=1">Log in</a>
            </div>
        <?php elseif ($error !== ''): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
            <p class="auth-foot"><a href="forgot_password.php">Request a new link</a></p>
        <?php else: ?>
            <form method="post" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <div class="form-group">
                    <label for="password">New password</label>
                    <input type="password" id="password" name="password"
                           autocomplete="new-password" minlength="8" required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm new password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           autocomplete="new-password" minlength="8" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Save password</button>
            </form>
        <?php endif; ?>
    </section>
</div>

</body>
</html>
