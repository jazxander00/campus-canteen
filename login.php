<?php
/**
 * Login.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (is_logged_in()) {
    redirect(is_staff() ? 'staff_menu.php' : 'index.php');
}

$error = '';
$email = '';

// Simple brute-force guard: 5 failures, then a 5 minute cool-down.
$_SESSION['login_attempts'] = $_SESSION['login_attempts'] ?? 0;
$_SESSION['login_locked_until'] = $_SESSION['login_locked_until'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (time() < (int) $_SESSION['login_locked_until']) {
        $wait = (int) ceil(((int) $_SESSION['login_locked_until'] - time()) / 60);
        $error = "Too many failed attempts. Try again in about {$wait} minute(s).";
    } elseif ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = $conn->prepare(
            'SELECT id, full_name, email, password, role, store_id FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            // Upgrade old plain-text passwords transparently.
            if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $update = $conn->prepare('UPDATE users SET password = ? WHERE id = ?');
                $update->bind_param('si', $hash, $user['id']);
                $update->execute();
                $update->close();
            }

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['role'] = strtolower($user['role']);
            $_SESSION['store_id'] = $user['store_id'] !== null ? (int) $user['store_id'] : null;
            unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);

            redirect(is_staff() ? 'staff_menu.php' : 'index.php');
        }

        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= 5) {
            $_SESSION['login_locked_until'] = time() + 300;
            $_SESSION['login_attempts'] = 0;
            $error = 'Too many failed attempts. Please wait 5 minutes.';
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$flashes = take_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">

<div class="auth-shell">
    <section class="auth-hero">
        <span class="pill">🍴 <?= e(APP_NAME) ?></span>
        <h1>Good food.<br>Better days.</h1>
        <p>Order your favourite meals from the campus canteen — faster, easier and more convenient.</p>
    </section>

    <section class="auth-card">
        <h2>Welcome back</h2>
        <p class="muted">Log in to continue ordering.</p>

        <?php foreach ($flashes as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['reset'])): ?>
            <div class="alert alert-success">Password updated. You can log in now.</div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>"
                       autocomplete="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       autocomplete="current-password" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Log in →</button>
        </form>

        <p class="auth-foot">
            <a href="forgot_password.php">Forgot password?</a><br>
            New here? <a href="register.php">Create a student account</a>
        </p>
    </section>
</div>

</body>
</html>
