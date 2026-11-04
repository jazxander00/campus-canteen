<?php
/**
 * Shared helpers. Loaded automatically by db.php.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Output / navigation
// ---------------------------------------------------------------------------

/** Escape for HTML output. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Redirect and stop. */
function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

/** Format a number as pesos. */
function money(float|int|string|null $amount): string
{
    return '₱' . number_format((float) $amount, 2);
}

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Abort the request unless a valid token was posted. */
function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

// ---------------------------------------------------------------------------
// Auth guards
// ---------------------------------------------------------------------------

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_role(): string
{
    return strtolower((string) ($_SESSION['role'] ?? 'guest'));
}

function is_staff(): bool
{
    return in_array(current_role(), ['staff', 'admin'], true);
}

function current_store_id(): ?int
{
    $id = $_SESSION['store_id'] ?? null;

    return $id !== null ? (int) $id : null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
}

function require_staff(): void
{
    require_login();
    if (!is_staff()) {
        http_response_code(403);
        exit('403 — You do not have access to this page.');
    }
}

// ---------------------------------------------------------------------------
// Flash messages
// ---------------------------------------------------------------------------

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** @return array<int, array{type:string, message:string}> */
function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $flashes;
}

function render_flashes(): string
{
    $html = '';
    foreach (take_flashes() as $flash) {
        $type = in_array($flash['type'], ['success', 'error', 'info'], true) ? $flash['type'] : 'info';
        $html .= '<div class="alert alert-' . $type . '">' . e($flash['message']) . '</div>';
    }

    return $html;
}

// ---------------------------------------------------------------------------
// Stores / menu
// ---------------------------------------------------------------------------

/** @return array<int, array<string, mixed>> */
function get_stores(mysqli $conn): array
{
    $result = $conn->query('SELECT * FROM shops ORDER BY id ASC');

    return $result->fetch_all(MYSQLI_ASSOC);
}

/** @return array<string, mixed>|null */
function get_store(mysqli $conn, int $storeId): ?array
{
    $stmt = $conn->prepare('SELECT * FROM shops WHERE id = ?');
    $stmt->bind_param('i', $storeId);
    $stmt->execute();
    $store = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $store ?: null;
}

function store_is_open(array $store): bool
{
    return strcasecmp(trim((string) ($store['status'] ?? 'Closed')), 'Open') === 0;
}

/**
 * A drink store sells three sizes; everything else has a single price.
 */
function store_has_sizes(int $storeId): bool
{
    return in_array($storeId, [4, 5], true);
}

/** Price for a menu item at the requested serving size. Never trusts the client. */
function price_for_size(array $item, string $size): float
{
    $size = strtolower(trim($size));

    if (str_starts_with($size, 'medium') && (float) ($item['price_medium'] ?? 0) > 0) {
        return (float) $item['price_medium'];
    }
    if (str_starts_with($size, 'large') && (float) ($item['price_large'] ?? 0) > 0) {
        return (float) $item['price_large'];
    }
    if ((float) ($item['price_regular'] ?? 0) > 0) {
        return (float) $item['price_regular'];
    }

    return (float) $item['price'];
}

/** Canonical, stored label for a size. */
function normalize_size(int $storeId, string $size): string
{
    if (!store_has_sizes($storeId)) {
        return 'Standard';
    }

    $size = strtolower(trim($size));
    if (str_starts_with($size, 'medium')) {
        return 'Medium (16oz)';
    }
    if (str_starts_with($size, 'large')) {
        return 'Large (22oz)';
    }

    return 'Regular (12oz)';
}

// ---------------------------------------------------------------------------
// Uploads
// ---------------------------------------------------------------------------

/**
 * Validate and store an uploaded image in /uploads.
 * Returns the public path, or null when no file was sent.
 *
 * @throws RuntimeException when the file is rejected.
 */
function handle_image_upload(array $file): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The image could not be uploaded. Please try again.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid upload.');
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        throw new RuntimeException('Image is too large (maximum 2 MB).');
    }

    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        throw new RuntimeException('That file is not a valid image.');
    }

    $allowed = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    ];
    if (!isset($allowed[$info[2]])) {
        throw new RuntimeException('Only JPG, PNG, GIF or WEBP images are allowed.');
    }

    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Upload folder is not writable.');
    }

    $name = bin2hex(random_bytes(10)) . '.' . $allowed[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Could not save the uploaded image.');
    }

    return 'uploads/' . $name;
}

// ---------------------------------------------------------------------------
// Orders
// ---------------------------------------------------------------------------

function order_status_class(string $status): string
{
    return match (strtolower($status)) {
        'preparing' => 'status-preparing',
        'ready'     => 'status-ready',
        'completed' => 'status-completed',
        'cancelled' => 'status-cancelled',
        default     => 'status-pending',
    };
}

function is_valid_order_status(string $status): bool
{
    return in_array($status, ORDER_STATUSES, true);
}

/**
 * HTML cards for "My Orders". Used by my_orders.php and the polling endpoint.
 */
function student_order_cards(mysqli $conn, int $userId): string
{
    $stmt = $conn->prepare(
        'SELECT id, total_amount, status, created_at, store_id
           FROM orders
          WHERE user_id = ?
          ORDER BY id DESC
          LIMIT 20'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (!$orders) {
        return '<div class="empty-state"><h3>No orders yet</h3>'
            . '<p>Browse a shop and add something delicious to your cart.</p>'
            . '<a class="btn btn-primary" href="index.php">Browse shops</a></div>';
    }

    $itemStmt = $conn->prepare(
        'SELECT oi.quantity, oi.price, oi.size, mi.name
           FROM order_items oi
           JOIN menu_items mi ON mi.id = oi.menu_item_id
          WHERE oi.order_id = ?'
    );
    $storeStmt = $conn->prepare('SELECT name FROM shops WHERE id = ?');

    $html = '';
    foreach ($orders as $order) {
        $itemStmt->bind_param('i', $order['id']);
        $itemStmt->execute();
        $items = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $storeStmt->bind_param('i', $order['store_id']);
        $storeStmt->execute();
        $store = $storeStmt->get_result()->fetch_assoc();

        $status = (string) $order['status'];
        $canCancel = is_logged_in() && in_array($status, CANCELLABLE_STATUSES, true);

        $html .= '<article class="order-card">';
        $html .= '<header class="order-card-head">';
        $html .= '<div><strong>Order #' . (int) $order['id'] . '</strong>'
            . '<div class="muted">' . e($store['name'] ?? 'Campus Canteen') . ' &bull; '
            . e(date('M j, Y g:i A', strtotime((string) $order['created_at']))) . '</div></div>';
        $html .= '<span class="status-badge ' . order_status_class($status) . '">' . e($status) . '</span>';
        $html .= '</header>';

        $html .= '<ul class="order-items">';
        foreach ($items as $item) {
            $label = $item['name'];
            if (!empty($item['size']) && $item['size'] !== 'Standard') {
                $label .= ' — ' . $item['size'];
            }
            $html .= '<li><span>' . e($label) . ' × ' . (int) $item['quantity'] . '</span>'
                . '<span>' . money((float) $item['price'] * (int) $item['quantity']) . '</span></li>';
        }
        $html .= '</ul>';

        $html .= '<footer class="order-card-foot">';
        $html .= '<span class="muted">Total</span><strong>' . money($order['total_amount']) . '</strong>';
        $html .= '</footer>';

        if ($canCancel) {
            $html .= '<form method="post" action="my_orders.php" class="inline-form"'
                . ' onsubmit="return confirm(\'Cancel this order?\');">'
                . csrf_field()
                . '<input type="hidden" name="order_id" value="' . (int) $order['id'] . '">'
                . '<button type="submit" name="cancel_order" class="btn btn-danger btn-sm">Cancel order</button>'
                . '</form>';
        }

        $html .= '</article>';
    }

    $itemStmt->close();
    $storeStmt->close();

    return $html;
}
