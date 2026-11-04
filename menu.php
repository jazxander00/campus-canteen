<?php
/**
 * Store menu page.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

$storeId = isset($_GET['store_id']) ? (int) $_GET['store_id'] : 0;
$store = $storeId > 0 ? get_store($conn, $storeId) : null;

if (!$store) {
    http_response_code(404);
    $pageTitle = 'Shop not found';
    require __DIR__ . '/header.php';
    echo '<main class="container"><div class="empty-state"><h3>Shop not found</h3>'
        . '<p>That shop does not exist.</p><a class="btn btn-primary" href="index.php">Back to shops</a></div></main>';
    require __DIR__ . '/footer.php';
    exit;
}

// ---------------------------------------------------------------------------
// Open / close toggle (owner staff or admin only)
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_store_id'])) {
    require_staff();
    verify_csrf();

    $targetId = (int) $_POST['toggle_store_id'];
    $target = get_store($conn, $targetId);
    $allowed = $target && (current_role() === 'admin' || current_store_id() === $targetId);

    if (!$allowed) {
        set_flash('error', 'You can only change the status of your own shop.');
    } else {
        $newStatus = store_is_open($target) ? 'Closed' : 'Open';
        $stmt = $conn->prepare('UPDATE shops SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $newStatus, $targetId);
        $stmt->execute();
        $stmt->close();
        set_flash('success', $target['name'] . ' is now ' . $newStatus . '.');
    }

    redirect('menu.php?store_id=' . $targetId);
}

// ---------------------------------------------------------------------------
// Menu items
// ---------------------------------------------------------------------------
$stmt = $conn->prepare(
    "SELECT * FROM menu_items
      WHERE store_id = ? AND status <> 'Archived'
      ORDER BY id ASC"
);
$stmt->bind_param('i', $storeId);
$stmt->execute();
$menuItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$isOpen = store_is_open($store);
$hasSizes = store_has_sizes($storeId);
$canToggle = is_staff() && (current_role() === 'admin' || current_store_id() === $storeId);
$totalStock = array_sum(array_map(static fn ($i) => (int) $i['stock'], $menuItems));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($store['name']) ?> — Menu</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php require __DIR__ . '/header.php'; ?>

<main class="container">
    <?= render_flashes() ?>

    <section class="store-banner">
        <div>
            <a class="muted" href="index.php">← Back to shops</a>
            <h1 class="page-title"><?= e($store['name']) ?></h1>
            <span class="muted">📍 <?= e($store['location']) ?> &bull; <?= e($store['category']) ?>
                &bull; <?= $totalStock ?> item(s) in stock</span>
        </div>

        <div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap">
            <span class="status-badge <?= $isOpen ? 'badge-open' : 'badge-closed' ?>">
                <?= $isOpen ? 'Open' : 'Closed' ?>
            </span>

            <?php if ($canToggle): ?>
                <form method="post" class="inline-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="toggle_store_id" value="<?= $storeId ?>">
                    <button type="submit" class="btn btn-sm <?= $isOpen ? 'btn-danger' : 'btn-secondary' ?>">
                        Set <?= $isOpen ? 'closed' : 'open' ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!$isOpen): ?>
        <div class="alert alert-info">
            This shop is currently closed. You can browse the menu, but orders are disabled until it reopens.
        </div>
    <?php endif; ?>

    <?php if (!$menuItems): ?>
        <div class="empty-state">
            <h3>No menu items yet</h3>
            <p>This shop has not published any items.</p>
        </div>
    <?php else: ?>
        <div class="menu-grid">
            <?php foreach ($menuItems as $item): ?>
                <?php
                    $itemId = (int) $item['id'];
                    $stock = (int) $item['stock'];
                    $available = $isOpen && $stock > 0 && strcasecmp((string) $item['status'], 'Available') === 0;

                    $sizes = [];
                    if ($hasSizes) {
                        $regular = (float) ($item['price_regular'] ?: $item['price']);
                        if ($regular > 0) {
                            $sizes['Regular (12oz)'] = $regular;
                        }
                        if ((float) $item['price_medium'] > 0) {
                            $sizes['Medium (16oz)'] = (float) $item['price_medium'];
                        }
                        if ((float) $item['price_large'] > 0) {
                            $sizes['Large (22oz)'] = (float) $item['price_large'];
                        }
                    }
                    if (!$sizes) {
                        $sizes['Standard'] = (float) ($item['price_regular'] ?: $item['price']);
                    }

                    $firstPrice = reset($sizes);
                ?>
                <article class="menu-card"
                         data-id="<?= $itemId ?>"
                         data-name="<?= e($item['name']) ?>"
                         data-store="<?= $storeId ?>"
                         data-stock="<?= $stock ?>">
                    <img src="<?= e($item['image_url'] ?: 'images/placeholder.jpg') ?>"
                         alt="<?= e($item['name']) ?>" loading="lazy">

                    <div class="menu-body">
                        <h3><?= e($item['name']) ?></h3>
                        <p><?= e($item['description'] ?: 'Freshly prepared in-store.') ?></p>

                        <?php if ($hasSizes): ?>
                            <select class="size-select" aria-label="Serving size for <?= e($item['name']) ?>">
                                <?php foreach ($sizes as $label => $price): ?>
                                    <option value="<?= e($label) ?>" data-price="<?= $price ?>">
                                        <?= e($label) ?> — <?= money($price) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>

                        <div class="menu-foot">
                            <span class="price" data-price="<?= $firstPrice ?>">
                                <?= money($firstPrice) ?>
                            </span>

                            <?php if ($available): ?>
                                <button type="button" class="btn btn-primary btn-sm add-btn">Add to cart</button>
                            <?php else: ?>
                                <button type="button" class="btn btn-secondary btn-sm" disabled>
                                    <?= !$isOpen ? 'Shop closed' : 'Out of stock' ?>
                                </button>
                            <?php endif; ?>
                        </div>

                        <?php if ($stock > 0 && $stock <= 5 && $isOpen): ?>
                            <span class="muted">Only <?= $stock ?> left</span>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php require __DIR__ . '/footer.php'; ?>

<script>
(function () {
    function readCart() {
        try { return JSON.parse(localStorage.getItem('canteen_cart') || '[]'); }
        catch (err) { return []; }
    }

    function saveCart(cart) {
        localStorage.setItem('canteen_cart', JSON.stringify(cart));
    }

    // Update the displayed price when a size is chosen.
    document.querySelectorAll('.menu-card').forEach(function (card) {
        var select = card.querySelector('.size-select');
        var priceEl = card.querySelector('.price');
        if (!select) return;

        select.addEventListener('change', function () {
            var option = select.options[select.selectedIndex];
            priceEl.textContent = '₱' + Number(option.dataset.price).toFixed(2);
        });
    });

    document.querySelectorAll('.add-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            var card = button.closest('.menu-card');
            var select = card.querySelector('.size-select');
            var size = 'Standard';
            var price = Number(card.querySelector('.price').dataset.price);

            if (select) {
                size = select.value;
                price = Number(select.options[select.selectedIndex].dataset.price);
            }

            var id = Number(card.dataset.id);
            var cartKey = id + '-' + size;
            var cart = readCart();
            var existing = cart.find(function (line) { return line.cartItemId === cartKey; });
            var inCart = cart
                .filter(function (line) { return line.id === id; })
                .reduce(function (sum, line) { return sum + Number(line.quantity); }, 0);

            if (inCart >= Number(card.dataset.stock)) {
                alert('Sorry, only ' + card.dataset.stock + ' of this item are available.');
                return;
            }

            if (existing) {
                existing.quantity += 1;
            } else {
                cart.push({
                    cartItemId: cartKey,
                    id: id,
                    name: card.dataset.name,
                    size: size,
                    price: price,
                    quantity: 1,
                    store_id: Number(card.dataset.store)
                });
            }

            saveCart(cart);
            window.location.href = 'cart.php';
        });
    });
})();
</script>
</body>
</html>
