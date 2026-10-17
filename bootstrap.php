<?php
declare(strict_types=1);

/**
 * Versi aplikasi, ditampilkan di navbar. Sumber tunggal: berkas VERSION
 * (dijaga sama dengan CHANGELOG.md oleh tests/cases.php).
 */
define('APP_VERSION', is_readable(__DIR__ . '/VERSION') ? trim((string) file_get_contents(__DIR__ . '/VERSION')) : 'dev');

// Hardening cookie session: HttpOnly + SameSite=Lax. Secure hanya bila request
// memang HTTPS, sehingga deployment HTTP lokal/Docker tetap berfungsi.
if (session_status() === PHP_SESSION_NONE) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'secure'   => $https,
        'samesite' => 'Lax',
    ]);
    session_start();
}
date_default_timezone_set('Asia/Jakarta');

error_reporting(E_ALL);
ini_set('display_errors', '0'); // set ke '1' sementara saat debugging

$config = require __DIR__ . '/config.php';

require __DIR__ . '/lib/Database.php';
require __DIR__ . '/lib/Auth.php';
require __DIR__ . '/lib/Csrf.php';
require __DIR__ . '/lib/CsvLocale.php';
require __DIR__ . '/lib/Settings.php';
require __DIR__ . '/lib/SalesReport.php';

Database::init($config);
Csrf::guard(); // semua POST wajib membawa token CSRF sesi
