<?php
declare(strict_types=1);

session_start();
date_default_timezone_set('Asia/Jakarta');

error_reporting(E_ALL);
ini_set('display_errors', '0'); // set ke '1' sementara saat debugging

$config = require __DIR__ . '/config.php';

require __DIR__ . '/lib/Database.php';
require __DIR__ . '/lib/Auth.php';
require __DIR__ . '/lib/CsvLocale.php';
require __DIR__ . '/lib/Settings.php';
require __DIR__ . '/lib/SalesReport.php';

Database::init($config);
