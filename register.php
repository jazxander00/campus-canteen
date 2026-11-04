<?php
/**
 * Student registration.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if ($name === '' || $email === '' || $password === '' || $confirm === '') {
        $error = 'Please fill in every field.';
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $error = 'Please enter your full name (2–100 characters).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'That email address is not valid.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'The two passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $conn->prepare(
                "INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'student')"
            );
            $stmt->bind_param('sss', $name, $email, $hash);
            $stmt->execute();
            $stmt->close();

            set_flash('success', 'Account created! You can log in now.');
            redirect('login.php');
        } catch (mysqli_sql_exception $e) {
            $error = $e->getCode() === 1062
                ? 'An account with that email already exists.'
                : 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create account — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="auth-body">

<div class="auth-shell">
    <section class="auth-hero">
        <span class="pill">🍴 <?= e(APP_NAME) ?></span>
        <h1>Join in<br>a minute.</h1>
        <p>Create a student account to order from every stall on campus and track your pickup orders live.</p>
    </section>

    <section class="auth-card">
        <h2>Student registration</h2>
        <p class="muted">It only takes a moment.</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" value="<?= e($name) ?>"
                       autocomplete="name" required>
            </div>

            <div class="form-group">
                <label for="email">Email address</label>
                <input type="email" id="email" name="email" value="<?= e($email) ?>"
                       autocomplete="email" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password"
                       autocomplete="new-password" minlength="8" required>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm password</label>
                <input type="password" id="confirm_password" name="confirm_password"
                       autocomplete="new-password" minlength="8" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">Register as student →</button>
        </form>

        <p class="auth-foot">Already have an account? <a href="login.php">Log in</a></p>
    </section>
</div>

</body>
</html>
