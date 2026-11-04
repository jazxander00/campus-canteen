<?php
/**
 * Staff dashboard: shop status, menu management and live orders.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_staff();

// ---------------------------------------------------------------------------
// Which store is this user working on?
// ---------------------------------------------------------------------------
$stores = get_stores($conn);

if (!$stores) {
    http_response_code(500);
    exit('No shops exist yet. Import database/campus_canteen.sql first.');
}

$storeIds = array_map(static fn ($s) => (int) $s['id'], $stores);
$store = null;

if (current_role() === 'staff') {
    if (current_store_id() === null) {
        http_response_code(403);
        exit('Your account is not assigned to a shop. Ask the administrator to set store_id.');
    }
    $store = get_store($conn, (int) current_store_id());
} else {
    // Admin: may switch between shops with ?store_id=
    $requested = isset($_GET['store_id']) ? (int) $_GET['store_id'] : null;
    $fallback = current_store_id() ?? $storeIds[0];
    $store = get_store($conn, $requested !== null ? $requested : $fallback);
    if (!$store) {
        $store = get_store($conn, $storeIds[0]);
    }
}

if (!$store) {
    http_response_code(403);
    exit('That shop does not exist.');
}

$storeId = (int) $store['id'];
$_SESSION['store_id'] = $storeId;

$activeTab = $_GET['tab'] ?? 'menu';
if (!in_array($activeTab, ['menu', 'orders', 'history'], true)) {
    $activeTab = 'menu';
}

$editItem = null;
if (isset($_GET['edit_id'])) {
    $editId = (int) $_GET['edit_id'];
    $stmt = $conn->prepare("SELECT * FROM menu_items WHERE id = ? AND store_id = ? AND status <> 'Archived'");
    $stmt->bind_param('ii', $editId, $storeId);
    $stmt->execute();
    $editItem = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

function staff_redirect(int $storeId, string $tab, bool $admin): never
{
    $query = 'staff_menu.php?tab=' . $tab;
    if ($admin) {
        $query .= '&store_id=' . $storeId;
    }
    redirect($query);
}

$isAdmin = current_role() === 'admin';

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    // ---- Shop open / closed ------------------------------------------------
    if ($action === 'store_status') {
        $status = ($_POST['store_status'] ?? '') === 'Open' ? 'Open' : 'Closed';
        $stmt = $conn->prepare('UPDATE shops SET status = ? WHERE id = ?');
        $stmt->bind_param('si', $status, $storeId);
        $stmt->execute();
        $stmt->close();
        set_flash('success', 'Shop status updated to ' . $status . '.');
        staff_redirect($storeId, $activeTab, $isAdmin);
    }

    // ---- Add / update a menu item -----------------------------------------
    if ($action === 'save_item') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $stock = (int) ($_POST['stock'] ?? 0);
        $status = (string) ($_POST['item_status'] ?? 'Available');
        if (!in_array($status, ['Available', 'Out of Stock'], true)) {
            $status = 'Available';
        }

        $hasSizes = store_has_sizes($storeId);
        $priceRegular = $hasSizes ? (float) ($_POST['price_regular'] ?? 0) : (float) ($_POST['price'] ?? 0);
        $priceMedium = $hasSizes ? (float) ($_POST['price_medium'] ?? 0) : 0.0;
        $priceLarge = $hasSizes ? (float) ($_POST['price_large'] ?? 0) : 0.0;
        $price = $hasSizes ? $priceRegular : (float) ($_POST['price'] ?? 0);

        $error = null;
        if ($name === '' || mb_strlen($name) > 255) {
            $error = 'Please enter an item name (maximum 255 characters).';
        } elseif ($price <= 0) {
            $error = 'The price must be greater than zero.';
        } elseif ($stock < 0 || $stock > 100000) {
            $error = 'Stock must be between 0 and 100000.';
        }

        $newImage = null;
        if ($error === null) {
            try {
                $newImage = handle_image_upload($_FILES['item_image'] ?? []);
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }

        if ($error !== null) {
            set_flash('error', $error);
            staff_redirect($storeId, 'menu', $isAdmin);
        }

        if ($itemId > 0) {
            // Update: make sure the item belongs to this store.
            $check = $conn->prepare('SELECT id, image_url FROM menu_items WHERE id = ? AND store_id = ?');
            $check->bind_param('ii', $itemId, $storeId);
            $check->execute();
            $existing = $check->get_result()->fetch_assoc();
            $check->close();

            if (!$existing) {
                set_flash('error', 'That item does not belong to your shop.');
                staff_redirect($storeId, 'menu', $isAdmin);
            }

            if ($newImage !== null) {
                $stmt = $conn->prepare(
                    'UPDATE menu_items
                        SET name = ?, description = ?, price = ?, stock = ?, status = ?,
                            price_regular = ?, price_medium = ?, price_large = ?, image_url = ?
                      WHERE id = ? AND store_id = ?'
                );
                $stmt->bind_param(
                    'ssdisdddsii',
                    $name, $description, $price, $stock, $status,
                    $priceRegular, $priceMedium, $priceLarge, $newImage, $itemId, $storeId
                );
            } else {
                $stmt = $conn->prepare(
                    'UPDATE menu_items
                        SET name = ?, description = ?, price = ?, stock = ?, status = ?,
                            price_regular = ?, price_medium = ?, price_large = ?
                      WHERE id = ? AND store_id = ?'
                );
                $stmt->bind_param(
                    'ssdisdddii',
                    $name, $description, $price, $stock, $status,
                    $priceRegular, $priceMedium, $priceLarge, $itemId, $storeId
                );
            }
            $stmt->execute();
            $stmt->close();

            // Remove the replaced file if nothing else references it.
            if ($newImage !== null && !empty($existing['image_url']) && str_starts_with($existing['image_url'], 'uploads/')) {
                $oldPath = $existing['image_url'];
                $usage = $conn->prepare('SELECT COUNT(*) AS c FROM menu_items WHERE image_url = ?');
                $usage->bind_param('s', $oldPath);
                $usage->execute();
                $count = (int) $usage->get_result()->fetch_assoc()['c'];
                $usage->close();
                if ($count === 0 && is_file(__DIR__ . '/' . $oldPath)) {
                    @unlink(__DIR__ . '/' . $oldPath);
                }
            }

            set_flash('success', '“' . $name . '” was updated.');
        } else {
            $image = $newImage ?? 'images/placeholder.jpg';
            $stmt = $conn->prepare(
                'INSERT INTO menu_items
                    (store_id, name, description, price, stock, status, price_regular, price_medium, price_large, image_url)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'issdisddds',
                $storeId, $name, $description, $price, $stock, $status,
                $priceRegular, $priceMedium, $priceLarge, $image
            );
            $stmt->execute();
            $stmt->close();

            set_flash('success', '“' . $name . '” was added to the menu.');
        }

        staff_redirect($storeId, 'menu', $isAdmin);
    }

    // ---- Archive a menu item (soft delete keeps old orders readable) -------
    if ($action === 'archive_item') {
        $itemId = (int) ($_POST['item_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE menu_items SET status = 'Archived' WHERE id = ? AND store_id = ?");
        $stmt->bind_param('ii', $itemId, $storeId);
        $stmt->execute();
        $stmt->close();
        set_flash('success', 'Item removed from the menu.');
        staff_redirect($storeId, 'menu', $isAdmin);
    }

    // ---- Move an order along ----------------------------------------------
    if ($action === 'order_status') {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $newStatus = (string) ($_POST['order_status'] ?? '');
        $inTransaction = false;

        try {
            if (!is_valid_order_status($newStatus)) {
                throw new RuntimeException('Unknown order status.');
            }

            $conn->begin_transaction();
            $inTransaction = true;

            $stmt = $conn->prepare('SELECT status FROM orders WHERE id = ? AND store_id = ? FOR UPDATE');
            $stmt->bind_param('ii', $orderId, $storeId);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$order) {
                throw new RuntimeException('Order not found for your shop.');
            }
            if ($order['status'] === 'Cancelled') {
                throw new RuntimeException('Cancelled orders cannot be changed.');
            }

            if ($newStatus === 'Cancelled') {
                $restock = $conn->prepare(
                    'UPDATE menu_items mi
                       JOIN (SELECT menu_item_id, SUM(quantity) AS qty
                               FROM order_items
                              WHERE order_id = ?
                              GROUP BY menu_item_id) totals
                         ON totals.menu_item_id = mi.id
                        SET mi.stock = mi.stock + totals.qty'
                );
                $restock->bind_param('i', $orderId);
                $restock->execute();
                $restock->close();
            }

            $update = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $update->bind_param('si', $newStatus, $orderId);
            $update->execute();
            $update->close();

            $conn->commit();
            $inTransaction = false;
            set_flash('success', 'Order #' . $orderId . ' is now ' . $newStatus . '.');
        } catch (Throwable $e) {
            if ($inTransaction) {
                $conn->rollback();
            }
            set_flash('error', $e->getMessage());
        }

        staff_redirect($storeId, 'orders', $isAdmin);
    }
}

// ---------------------------------------------------------------------------
// Data for the page
// ---------------------------------------------------------------------------
$storeStatus = (string) ($store['status'] ?? 'Open');

$stmt = $conn->prepare("SELECT * FROM menu_items WHERE store_id = ? AND status <> 'Archived' ORDER BY id DESC");
$stmt->bind_param('i', $storeId);
$stmt->execute();
$menuItems = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$orders = [];
$orderItems = [];

$orderResult = $conn->prepare(
    'SELECT o.id, o.total_amount, o.status, o.created_at, u.full_name AS customer
       FROM orders o
       JOIN users u ON u.id = o.user_id
      WHERE o.store_id = ?
      ORDER BY o.id DESC
      LIMIT 100'
);
$orderResult->bind_param('i', $storeId);
$orderResult->execute();
$orders = $orderResult->get_result()->fetch_all(MYSQLI_ASSOC);
$orderResult->close();

if ($orders) {
    $orderIds = array_column($orders, 'id');
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));

    $itemStmt = $conn->prepare(
        "SELECT oi.order_id, oi.quantity, oi.price, oi.size, mi.name
           FROM order_items oi
           LEFT JOIN menu_items mi ON mi.id = oi.menu_item_id
          WHERE oi.order_id IN ($placeholders)
          ORDER BY oi.id ASC"
    );
    $itemStmt->bind_param(str_repeat('i', count($orderIds)), ...$orderIds);
    $itemStmt->execute();
    foreach ($itemStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $orderItems[(int) $row['order_id']][] = $row;
    }
    $itemStmt->close();
}

$pendingCount = 0;
foreach ($orders as $order) {
    if (in_array($order['status'], ['Pending', 'Preparing'], true)) {
        $pendingCount++;
    }
}

$salesToday = 0.0;
$salesStmt = $conn->prepare(
    "SELECT COALESCE(SUM(total_amount), 0) AS total
       FROM orders
      WHERE store_id = ? AND status = 'Completed' AND DATE(created_at) = CURDATE()"
);
$salesStmt->bind_param('i', $storeId);
$salesStmt->execute();
$salesToday = (float) $salesStmt->get_result()->fetch_assoc()['total'];
$salesStmt->close();

$shopNames = [];
foreach ($stores as $shop) {
    $shopNames[(int) $shop['id']] = $shop['name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff dashboard — <?= e($store['name']) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="staff-bar">
    <h2>🍴 <?= e(APP_NAME) ?> — <?= e($store['name']) ?></h2>

    <div class="staff-tabs">
        <a href="staff_menu.php?tab=menu<?= $isAdmin ? '&store_id=' . $storeId : '' ?>"
           class="<?= $activeTab === 'menu' ? 'active' : '' ?>">Menu</a>
        <a href="staff_menu.php?tab=orders<?= $isAdmin ? '&store_id=' . $storeId : '' ?>"
           class="<?= $activeTab === 'orders' ? 'active' : '' ?>">
            Orders<?php if ($pendingCount): ?><span class="tab-badge"><?= $pendingCount ?></span><?php endif; ?>
        </a>
        <a href="staff_menu.php?tab=history<?= $isAdmin ? '&store_id=' . $storeId : '' ?>"
           class="<?= $activeTab === 'history' ? 'active' : '' ?>">History &amp; sales</a>
        <a href="index.php">Storefront</a>
        <form method="post" action="logout.php" class="inline-form">
            <?= csrf_field() ?>
            <button type="submit" class="link-button">Log out</button>
        </form>
    </div>
</div>

<main class="container">
    <?= render_flashes() ?>

    <section class="staff-header">
        <div>
            <h1><?= e($store['name']) ?></h1>
            <p>Manage the menu, watch incoming orders and check today's sales.</p>
        </div>

        <div style="display:flex;align-items:center;gap:0.75rem;flex-wrap:wrap">
            <?php if ($isAdmin): ?>
                <form method="get" style="margin:0">
                    <input type="hidden" name="tab" value="<?= e($activeTab) ?>">
                    <select name="store_id" class="size-select" onchange="this.form.submit()">
                        <?php foreach ($shopNames as $id => $name): ?>
                            <option value="<?= $id ?>" <?= $id === $storeId ? 'selected' : '' ?>>
                                <?= e($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php endif; ?>

            <form method="post" class="status-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="store_status">
                <label for="store_status" style="font-size:0.8rem;font-weight:700">Shop</label>
                <select name="store_status" id="store_status">
                    <option value="Open" <?= strcasecmp($storeStatus, 'Open') === 0 ? 'selected' : '' ?>>Open</option>
                    <option value="Closed" <?= strcasecmp($storeStatus, 'Open') === 0 ? '' : 'selected' ?>>Closed</option>
                </select>
                <button type="submit">Update</button>
            </form>
        </div>
    </section>

    <div class="stat-grid">
        <div class="stat-card"><span>Pending orders</span><strong><?= $pendingCount ?></strong></div>
        <div class="stat-card"><span>Sales today</span><strong><?= money($salesToday) ?></strong></div>
        <div class="stat-card"><span>Menu items</span><strong><?= count($menuItems) ?></strong></div>
        <div class="stat-card"><span>Shop status</span><strong><?= e($storeStatus) ?></strong></div>
    </div>

<?php if ($activeTab === 'menu'): ?>
    <section class="card">
        <h2><?= $editItem ? 'Edit item: ' . e($editItem['name']) : 'Add a new item' ?></h2>

        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_item">
            <?php if ($editItem): ?>
                <input type="hidden" name="item_id" value="<?= (int) $editItem['id'] ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="name">Item name</label>
                    <input type="text" id="name" name="name" required maxlength="255"
                           value="<?= e($editItem['name'] ?? '') ?>">
                </div>

                <?php if (store_has_sizes($storeId)): ?>
                    <div class="form-group">
                        <label for="price_regular">Regular price (₱)</label>
                        <input type="number" step="0.01" min="0.01" id="price_regular" name="price_regular"
                               value="<?= e($editItem['price_regular'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="price_medium">Medium price (₱)</label>
                        <input type="number" step="0.01" min="0" id="price_medium" name="price_medium"
                               value="<?= e($editItem['price_medium'] ?? '0') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="price_large">Large price (₱)</label>
                        <input type="number" step="0.01" min="0" id="price_large" name="price_large"
                               value="<?= e($editItem['price_large'] ?? '0') ?>" required>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label for="price">Price (₱)</label>
                        <input type="number" step="0.01" min="0.01" id="price" name="price"
                               value="<?= e($editItem['price'] ?? '') ?>" required>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="stock">Stock</label>
                    <input type="number" min="0" id="stock" name="stock"
                           value="<?= e($editItem['stock'] ?? '50') ?>" required>
                </div>

                <div class="form-group">
                    <label for="item_status">Availability</label>
                    <select id="item_status" name="item_status">
                        <option value="Available" <?= ($editItem['status'] ?? '') === 'Available' ? 'selected' : '' ?>>Available</option>
                        <option value="Out of Stock" <?= ($editItem['status'] ?? '') === 'Out of Stock' ? 'selected' : '' ?>>Out of stock</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="item_image">Photo (JPG, PNG, GIF or WEBP — max 2 MB)</label>
                    <input type="file" id="item_image" name="item_image" accept="image/*">
                </div>

                <div class="form-group" style="grid-column:1/-1">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="2"><?= e($editItem['description'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <?= $editItem ? 'Save changes' : 'Add item' ?>
                </button>
                <?php if ($editItem): ?>
                    <a class="btn btn-ghost" href="staff_menu.php?tab=menu<?= $isAdmin ? '&store_id=' . $storeId : '' ?>">
                        Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="card">
        <h2>Menu items (<?= count($menuItems) ?>)</h2>

        <?php if (!$menuItems): ?>
            <p class="muted">No items yet — add your first one above.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr><th>Item</th><th>Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($menuItems as $item): ?>
                        <?php $lowStock = (int) $item['stock'] <= 5; ?>
                        <tr>
                            <td>
                                <strong><?= e($item['name']) ?></strong><br>
                                <span class="muted"><?= e($item['description'] ?: '—') ?></span>
                            </td>
                            <td>
                                <?php if (store_has_sizes($storeId)): ?>
                                    R <?= money($item['price_regular']) ?><br>
                                    M <?= money($item['price_medium']) ?><br>
                                    L <?= money($item['price_large']) ?>
                                <?php else: ?>
                                    <?= money($item['price']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) $item['stock'] ?><?= $lowStock ? ' ⚠️' : '' ?></td>
                            <td>
                                <span class="status-badge <?= strcasecmp($item['status'], 'Available') === 0 ? 'badge-open' : 'badge-closed' ?>">
                                    <?= e($item['status']) ?>
                                </span>
                            </td>
                            <td style="white-space:nowrap">
                                <a class="btn btn-secondary btn-sm"
                                   href="staff_menu.php?tab=menu&edit_id=<?= (int) $item['id'] ?><?= $isAdmin ? '&store_id=' . $storeId : '' ?>">Edit</a>
                                <form method="post" class="inline-form"
                                      onsubmit="return confirm('Remove <?= e($item['name']) ?> from the menu?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="archive_item">
                                    <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

<?php elseif ($activeTab === 'orders'): ?>
    <section class="card">
        <h2>Incoming orders</h2>

        <?php
        $openOrders = array_filter($orders, static fn ($o) => !in_array($o['status'], ['Completed', 'Cancelled'], true));
        ?>
        <?php if (!$openOrders): ?>
            <p class="muted">No open orders right now.</p>
        <?php endif; ?>

        <?php foreach ($openOrders as $order): ?>
            <?php $lines = $orderItems[(int) $order['id']] ?? []; ?>
            <article class="order-card">
                <header class="order-card-head">
                    <div>
                        <strong>Order #<?= (int) $order['id'] ?> — <?= e($order['customer']) ?></strong>
                        <div class="muted"><?= e(date('M j, Y g:i A', strtotime((string) $order['created_at']))) ?></div>
                    </div>
                    <span class="status-badge <?= order_status_class($order['status']) ?>"><?= e($order['status']) ?></span>
                </header>

                <ul class="order-items">
                    <?php foreach ($lines as $line): ?>
                        <li>
                            <span>
                                <?= e($line['name'] ?? 'Removed item') ?>
                                <?= $line['size'] && $line['size'] !== 'Standard' ? ' — ' . e($line['size']) : '' ?>
                                × <?= (int) $line['quantity'] ?>
                            </span>
                            <span><?= money((float) $line['price'] * (int) $line['quantity']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="order-card-foot">
                    <span class="muted">Total</span>
                    <strong><?= money($order['total_amount']) ?></strong>
                </div>

                <form method="post" class="form-actions" style="justify-content:flex-end">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="order_status">
                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                    <select name="order_status" style="width:auto">
                        <?php foreach (ORDER_STATUSES as $status): ?>
                            <option value="<?= e($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>>
                                <?= e($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm">Update status</button>
                </form>
            </article>
        <?php endforeach; ?>
    </section>

<?php else: ?>
    <section class="card">
        <h2>Completed &amp; cancelled orders</h2>

        <?php
        $closed = array_filter($orders, static fn ($o) => in_array($o['status'], ['Completed', 'Cancelled'], true));
        ?>
        <?php if (!$closed): ?>
            <p class="muted">Nothing here yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                    <tr><th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Placed</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($closed as $order): ?>
                        <?php $lines = $orderItems[(int) $order['id']] ?? []; ?>
                        <tr>
                            <td>#<?= (int) $order['id'] ?></td>
                            <td><?= e($order['customer']) ?></td>
                            <td>
                                <?php foreach ($lines as $line): ?>
                                    <?= e($line['name'] ?? 'Removed item') ?> × <?= (int) $line['quantity'] ?><br>
                                <?php endforeach; ?>
                            </td>
                            <td><?= money($order['total_amount']) ?></td>
                            <td><span class="status-badge <?= order_status_class($order['status']) ?>"><?= e($order['status']) ?></span></td>
                            <td><?= e(date('M j, g:i A', strtotime((string) $order['created_at']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
</main>

<?php require __DIR__ . '/footer.php'; ?>
