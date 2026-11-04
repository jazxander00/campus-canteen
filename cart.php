<?php
/**
 * Cart + checkout.
 *
 * The browser keeps a display copy in localStorage, but every price, stock
 * check and total is recalculated here from the database. The client can
 * never decide what an order costs.
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

$maxLineQty = 20;
$maxLines = 20;

// ---------------------------------------------------------------------------
// Place order
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    require_login();
    verify_csrf();

    $userId = (int) $_SESSION['user_id'];
    $cart = json_decode((string) ($_POST['cart_data'] ?? '[]'), true);

    if (!is_array($cart) || $cart === []) {
        set_flash('error', 'Your cart is empty.');
        redirect('cart.php');
    }
    if (count($cart) > $maxLines) {
        set_flash('error', 'Too many different items in one order.');
        redirect('cart.php');
    }

    // ---- Validate every line against the database -------------------------
    $itemStmt = $conn->prepare(
        "SELECT id, store_id, name, price, price_regular, price_medium, price_large, stock, status
           FROM menu_items
          WHERE id = ? AND status <> 'Archived'"
    );
    $linesByStore = [];
    $inTransaction = false;

    try {
        foreach ($cart as $rawLine) {
            $itemId = (int) ($rawLine['id'] ?? 0);
            $qty = (int) ($rawLine['quantity'] ?? 0);
            $size = (string) ($rawLine['size'] ?? 'Standard');

            if ($itemId <= 0 || $qty <= 0 || $qty > $maxLineQty) {
                throw new RuntimeException('One of the items has an invalid quantity.');
            }

            $itemStmt->bind_param('i', $itemId);
            $itemStmt->execute();
            $item = $itemStmt->get_result()->fetch_assoc();

            if (!$item) {
                throw new RuntimeException('An item in your cart is no longer available.');
            }

            $storeId = (int) $item['store_id'];
            $store = get_store($conn, $storeId);
            if (!$store) {
                throw new RuntimeException('That shop is no longer available.');
            }
            if (!store_is_open($store)) {
                throw new RuntimeException($store['name'] . ' is currently closed.');
            }

            if ((int) $item['stock'] < $qty) {
                throw new RuntimeException(
                    'Only ' . (int) $item['stock'] . ' × ' . $item['name'] . ' left in stock.'
                );
            }

            $linesByStore[$storeId][] = [
                'id'    => $itemId,
                'name'  => $item['name'],
                'qty'   => $qty,
                'size'  => normalize_size($storeId, $size),
                'price' => price_for_size($item, $size),
                'stock' => (int) $item['stock'],
            ];
        }
        $itemStmt->close();

        // ---- Write the orders ---------------------------------------------
        $conn->begin_transaction();
        $inTransaction = true;

        $orderStmt = $conn->prepare(
            "INSERT INTO orders (user_id, store_id, total_amount, status) VALUES (?, ?, ?, 'Pending')"
        );
        $itemInsert = $conn->prepare(
            'INSERT INTO order_items (order_id, menu_item_id, quantity, price, size) VALUES (?, ?, ?, ?, ?)'
        );
        $stockUpdate = $conn->prepare(
            'UPDATE menu_items SET stock = stock - ? WHERE id = ? AND stock >= ?'
        );

        foreach ($linesByStore as $storeId => $lines) {
            $total = 0.0;
            foreach ($lines as $line) {
                $total += $line['price'] * $line['qty'];
            }

            $orderStmt->bind_param('iid', $userId, $storeId, $total);
            $orderStmt->execute();
            $orderId = $orderStmt->insert_id;

            foreach ($lines as $line) {
                $itemInsert->bind_param(
                    'iiids',
                    $orderId,
                    $line['id'],
                    $line['qty'],
                    $line['price'],
                    $line['size']
                );
                $itemInsert->execute();

                // Conditional update = atomic stock check, no overselling.
                $stockUpdate->bind_param('iii', $line['qty'], $line['id'], $line['qty']);
                $stockUpdate->execute();
                if ($stockUpdate->affected_rows !== 1) {
                    throw new RuntimeException('Sorry, ' . $line['name'] . ' just sold out.');
                }
            }
        }

        $orderStmt->close();
        $itemInsert->close();
        $stockUpdate->close();
        $conn->commit();
        $inTransaction = false;

        set_flash('success', 'Order placed! Show your order number at the counter for pickup.');
        redirect('my_orders.php?placed=1');
    } catch (Throwable $e) {
        if ($inTransaction) {
            $conn->rollback();
        }
        set_flash('error', $e->getMessage());
        redirect('cart.php');
    }
}

$loggedIn = is_logged_in();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your cart — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php require __DIR__ . '/header.php'; ?>

<main class="container">
    <?= render_flashes() ?>

    <div class="page-head">
        <a class="muted" href="index.php">← Back to shops</a>
        <h1 class="page-title">Your cart</h1>
    </div>

    <section class="cart-card">
        <div id="cartLines"><p class="muted">Loading your cart…</p></div>
        <div class="cart-total" id="cartTotal" hidden>
            <span>Total</span>
            <span id="cartTotalValue">₱0.00</span>
        </div>

        <?php if (!$loggedIn): ?>
            <div class="alert alert-info" style="margin-top:1rem">
                Please <a href="login.php">log in</a> or <a href="register.php">create an account</a> to place your order.
            </div>
        <?php endif; ?>

        <form method="post" id="checkoutForm" hidden>
            <?= csrf_field() ?>
            <input type="hidden" name="cart_data" id="cartData">
            <input type="hidden" name="place_order" value="1">
            <button type="submit" class="btn btn-primary btn-block" style="margin-top:1.25rem"
                    <?= $loggedIn ? '' : 'disabled' ?>>
                Place pickup order
            </button>
        </form>
    </section>
</main>

<?php require __DIR__ . '/footer.php'; ?>

<script>
(function () {
    var linesEl = document.getElementById('cartLines');
    var totalEl = document.getElementById('cartTotal');
    var totalValueEl = document.getElementById('cartTotalValue');
    var form = document.getElementById('checkoutForm');
    var dataInput = document.getElementById('cartData');

    function readCart() {
        try { return JSON.parse(localStorage.getItem('canteen_cart') || '[]'); }
        catch (err) { return []; }
    }

    function saveCart(cart) {
        localStorage.setItem('canteen_cart', JSON.stringify(cart));
    }

    function render() {
        var cart = readCart();
        linesEl.innerHTML = '';

        if (!cart.length) {
            linesEl.innerHTML = '<div class="empty-state"><p>Your cart is empty.</p>'
                + '<a class="btn btn-primary" href="index.php">Browse shops</a></div>';
            totalEl.hidden = true;
            form.hidden = true;
            return;
        }

        var total = 0;

        cart.forEach(function (line, index) {
            total += Number(line.price) * Number(line.quantity);

            var row = document.createElement('div');
            row.className = 'cart-line';

            var info = document.createElement('div');
            var name = document.createElement('strong');
            name.textContent = line.name + (line.size && line.size !== 'Standard' ? ' — ' + line.size : '');
            var price = document.createElement('small');
            price.textContent = '₱' + Number(line.price).toFixed(2) + ' each';
            info.appendChild(name);
            info.appendChild(price);

            var actions = document.createElement('div');
            actions.style.display = 'flex';
            actions.style.alignItems = 'center';
            actions.style.gap = '0.5rem';

            var minus = document.createElement('button');
            minus.type = 'button';
            minus.className = 'btn btn-secondary btn-sm';
            minus.textContent = '−';
            minus.addEventListener('click', function () { changeQty(index, -1); });

            var qty = document.createElement('strong');
            qty.textContent = line.quantity;

            var plus = document.createElement('button');
            plus.type = 'button';
            plus.className = 'btn btn-secondary btn-sm';
            plus.textContent = '+';
            plus.addEventListener('click', function () { changeQty(index, 1); });

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-danger btn-sm';
            remove.textContent = 'Remove';
            remove.addEventListener('click', function () {
                var next = readCart();
                next.splice(index, 1);
                saveCart(next);
                render();
            });

            actions.appendChild(minus);
            actions.appendChild(qty);
            actions.appendChild(plus);
            actions.appendChild(remove);

            row.appendChild(info);
            row.appendChild(actions);
            linesEl.appendChild(row);
        });

        totalEl.hidden = false;
        totalValueEl.textContent = '₱' + total.toFixed(2);
        dataInput.value = JSON.stringify(cart);
        form.hidden = false;
    }

    function changeQty(index, delta) {
        var cart = readCart();
        if (!cart[index]) return;
        cart[index].quantity = Math.max(1, Math.min(20, Number(cart[index].quantity) + delta));
        saveCart(cart);
        render();
    }

    render();
})();
</script>
</body>
</html>
