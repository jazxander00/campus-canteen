<?php
/**
 * Returns the "My Orders" cards as an HTML fragment (used for live polling).
 */

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (!is_logged_in()) {
    http_response_code(401);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

echo student_order_cards($conn, (int) $_SESSION['user_id']);
