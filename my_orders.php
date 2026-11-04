<?php
/**
 * Customer order history + live status.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_login();

$userId = (int) $_SESSION['user_id'];

// ---------------------------------------------------------------------------
// Cancel an order (only your own, only while still Pending)
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    verify_csrf();

    $orderId = (int) ($_POST['order_id'] ?? 0);
    $inTransaction = false;

    try {
        $conn->begin_transaction();
        $inTransaction = true;

        $stmt = $conn->prepare(
            'SELECT id, status FROM orders WHERE id = ? AND user_id = ? FOR UPDATE'
        );
        $stmt->bind_param('ii', $orderId, $userId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            throw new RuntimeException('Order not found.');
        }
        if (!in_array($order['status'], CANCELLABLE_STATUSES, true)) {
            throw new RuntimeException('This order can no longer be cancelled. Please ask the staff.');
        }

        // Put the reserved stock back on the shelf.
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

        $update = $conn->prepare("UPDATE orders SET status = 'Cancelled' WHERE id = ?");
        $update->bind_param('i', $orderId);
        $update->execute();
        $update->close();

        $conn->commit();
        $inTransaction = false;
        set_flash('success', 'Order #' . $orderId . ' was cancelled.');
    } catch (Throwable $e) {
        if ($inTransaction) {
            $conn->rollback();
        }
        set_flash('error', $e->getMessage());
    }

    redirect('my_orders.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php require __DIR__ . '/header.php'; ?>

<main class="container">
    <?= render_flashes() ?>

    <div class="page-head">
        <div>
            <h1 class="page-title">My Orders</h1>
            <span class="muted">Statuses update automatically every 10 seconds.</span>
        </div>
        <a class="btn btn-primary btn-sm" href="index.php">Order again</a>
    </div>

    <div id="ordersList">
        <?= student_order_cards($conn, $userId) ?>
    </div>
</main>

<?php require __DIR__ . '/footer.php'; ?>

<?php if (isset($_GET['placed'])): ?>
<script>
    localStorage.removeItem('canteen_cart');
</script>
<?php endif; ?>

<script>
(function () {
    var target = document.getElementById('ordersList');
    if (!target) return;

    function refresh() {
        if (document.hidden) return;
        fetch('get_student_orders_ajax.php', { headers: { 'X-Requested-With': 'fetch' } })
            .then(function (response) { return response.ok ? response.text() : ''; })
            .then(function (html) { if (html) target.innerHTML = html; })
            .catch(function () { /* keep the last good render */ });
    }

    setInterval(refresh, 10000);
})();
</script>
</body>
</html>
