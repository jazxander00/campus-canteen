<?php
/**
 * Database + session bootstrap.
 *
 * Every page starts with:  require __DIR__ . '/db.php';
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// ---------------------------------------------------------------------------
// Session hardening. Must happen before session_start().
// ---------------------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // 'secure' => true, // enable once the site is served over HTTPS
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();
}

// ---------------------------------------------------------------------------
// MySQLi connection. Exceptions instead of silent warnings.
// ---------------------------------------------------------------------------
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    http_response_code(500);
    error_log('DB connection failed: ' . $e->getMessage());

    $hint = is_local()
        ? ' Start MySQL in the XAMPP Control Panel and import <code>database/campus_canteen.sql</code>.'
        : '';

    exit(
        '<!doctype html><meta charset="utf-8"><title>Database error</title>'
        . '<div style="font-family:system-ui;max-width:560px;margin:4rem auto;padding:2rem;'
        . 'border:1px solid #e5e7eb;border-radius:12px">'
        . '<h2 style="color:#991b1b;margin-top:0">Cannot connect to the database</h2>'
        . '<p>Check <code>config.php</code> / your environment variables.' . $hint . '</p>'
        . '</div>'
    );
}

require_once __DIR__ . '/functions.php';
