<?php
/**
 * Site header. Expects $conn (from db.php) to be available.
 */
require_once __DIR__ . '/db.php';

$navStores = get_stores($conn);
$navRole = current_role();
$navLoggedIn = is_logged_in();
?>
<header class="site-header">
    <a class="brand" href="index.php">🍴 <?= e(APP_NAME) ?></a>

    <nav class="site-nav">
        <a href="index.php">Home</a>

        <div class="nav-dropdown">
            <button type="button" class="nav-dropbtn">Menu ▾</button>
            <div class="dropdown-menu">
                <?php foreach ($navStores as $navStore): ?>
                    <a href="menu.php?store_id=<?= (int) $navStore['id'] ?>"><?= e($navStore['name']) ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <a href="cart.php">Cart<span id="cart-count" class="cart-count" hidden>0</span></a>
        <a href="my_orders.php">My Orders</a>

        <?php if (is_staff()): ?>
            <a class="nav-cta" href="staff_menu.php">Staff Dashboard</a>
        <?php endif; ?>

        <?php if ($navLoggedIn): ?>
            <span class="nav-user">Hi, <?= e($_SESSION['user_name'] ?? 'there') ?></span>
            <form method="post" action="logout.php" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="link-button">Log out</button>
            </form>
        <?php else: ?>
            <a href="login.php">Log in</a>
            <a class="nav-cta" href="register.php">Sign up</a>
        <?php endif; ?>
    </nav>
</header>

<script>
(function () {
    var badge = document.getElementById('cart-count');
    if (!badge) return;
    try {
        var cart = JSON.parse(localStorage.getItem('canteen_cart') || '[]');
        var count = cart.reduce(function (sum, item) { return sum + (Number(item.quantity) || 0); }, 0);
        if (count > 0) { badge.textContent = count; badge.hidden = false; }
    } catch (err) { /* ignore corrupt storage */ }
})();
</script>
