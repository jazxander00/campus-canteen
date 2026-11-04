<?php
/**
 * Home page: list of canteen shops.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

// ---------------------------------------------------------------------------
// Open / close a shop (staff or admin only)
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_store_id'])) {
    require_staff();
    verify_csrf();

    $storeId = (int) $_POST['toggle_store_id'];
    $store = get_store($conn, $storeId);

    $allowed = $store && (current_role() === 'admin' || current_store_id() === $storeId);

    if (!$allowed) {
        set_flash('error', 'You can only change the status of your own shop.');
    } else {
        $newStatus = store_is_open($store) ? 'Closed' : 'Open';
        $stmt = $conn->prepare('UPDATE shops SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $newStatus, $storeId);
        $stmt->execute();
        $stmt->close();
        set_flash('success', $store['name'] . ' is now ' . $newStatus . '.');
    }

    redirect('index.php');
}

// ---------------------------------------------------------------------------
// Data
// ---------------------------------------------------------------------------
try {
    $stores = get_stores($conn);
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    exit(
        '<!doctype html><meta charset="utf-8"><title>Setup required</title>'
        . '<div style="font-family:system-ui;max-width:600px;margin:4rem auto;padding:2rem;'
        . 'border:1px solid #e5e7eb;border-radius:12px">'
        . '<h2 style="color:#991b1b;margin-top:0">Database tables are missing</h2>'
        . '<p>Import <code>database/campus_canteen.sql</code> into the '
        . '<code>campus_canteen</code> database (phpMyAdmin → Import), then reload this page.</p>'
        . '</div>'
    );
}

$isStaffView = is_staff();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(APP_NAME) ?> — Order from campus shops</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php require __DIR__ . '/header.php'; ?>

<main class="container">
    <?= render_flashes() ?>

    <section class="hero">
        <h1>Campus Canteen Shops</h1>
        <p>Browse menus from campus stalls and place pickup orders in seconds.</p>
        <div class="search-box">
            <input type="text" id="storeSearch" placeholder="Search a shop or food category…" autocomplete="off">
        </div>
    </section>

    <?php if (!$stores): ?>
        <div class="empty-state">
            <h3>No shops yet</h3>
            <p>Seed the <code>shops</code> table to get started.</p>
        </div>
    <?php else: ?>
        <div class="shop-grid" id="shopGrid">
            <?php foreach ($stores as $store): ?>
                <?php
                    $storeId = (int) $store['id'];
                    $open = store_is_open($store);
                    $canToggle = $isStaffView
                        && (current_role() === 'admin' || current_store_id() === $storeId);
                ?>
                <article class="shop-card" data-search="<?= e(strtolower($store['name'] . ' ' . $store['category'])) ?>">
                    <div class="shop-media">
                        <img src="<?= e($store['image'] ?: 'images/placeholder.jpg') ?>"
                             alt="<?= e($store['name']) ?>" loading="lazy">
                        <span class="status-badge <?= $open ? 'badge-open' : 'badge-closed' ?>">
                            <?= $open ? 'Open' : 'Closed' ?>
                        </span>
                    </div>
                    <div class="shop-body">
                        <h3><?= e($store['name']) ?></h3>
                        <div class="shop-meta">
                            📍 <?= e($store['location']) ?> &bull; <?= e($store['category']) ?>
                        </div>
                        <div class="shop-actions">
                            <?php if ($canToggle): ?>
                                <form method="post" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="toggle_store_id" value="<?= $storeId ?>">
                                    <button type="submit" class="btn btn-sm <?= $open ? 'btn-danger' : 'btn-secondary' ?>">
                                        Set <?= $open ? 'closed' : 'open' ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="rating">⭐ <?= e($store['rating'] ?: '4.5') ?></span>
                            <?php endif; ?>

                            <a class="btn btn-primary btn-sm" href="menu.php?store_id=<?= $storeId ?>">
                                Browse menu →
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="empty-state" id="noResults" hidden>
            <p>No shops match your search.</p>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/footer.php'; ?>

<script>
(function () {
    var input = document.getElementById('storeSearch');
    var grid = document.getElementById('shopGrid');
    var noResults = document.getElementById('noResults');
    if (!input || !grid) return;

    input.addEventListener('input', function () {
        var term = input.value.toLowerCase().trim();
        var visible = 0;

        grid.querySelectorAll('.shop-card').forEach(function (card) {
            var match = (card.dataset.search || '').indexOf(term) !== -1;
            card.hidden = !match;
            if (match) visible++;
        });

        if (noResults) noResults.hidden = visible !== 0;
    });
})();
</script>
</body>
</html>
