<?php
/**
 * Application configuration.
 *
 * Values can be overridden with environment variables so the same code
 * works on a laptop (XAMPP) and on a real server without editing files.
 */

declare(strict_types=1);

define('APP_NAME', 'Campus Canteen');
define('APP_ENV', getenv('APP_ENV') ?: 'local'); // 'local' | 'production'

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'campus_canteen');

/** Prefix prepended to outgoing e-mail links, e.g. http://localhost/campus_canteen */
define('APP_URL', rtrim(getenv('APP_URL') ?: 'http://localhost/campus_canteen', '/'));

define('MAX_UPLOAD_BYTES', 2 * 1024 * 1024); // 2 MB

/** Order lifecycle. The first entry is the status of a new order. */
const ORDER_STATUSES = ['Pending', 'Preparing', 'Ready', 'Completed', 'Cancelled'];

/** Statuses a customer is allowed to cancel from. */
const CANCELLABLE_STATUSES = ['Pending'];

/** Menu item statuses. */
const ITEM_STATUSES = ['Available', 'Out of Stock', 'Archived'];

function is_local(): bool
{
    return APP_ENV !== 'production';
}
