<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$start  = $_GET['start'] ?? null;
$end    = $_GET['end'] ?? null;
$locale = $_GET['locale'] ?? Settings::getCsvLocale();
if (!is_string($locale)) {
    $locale = '';
}

$rangeError = SalesReport::validateRange(is_string($start) ? $start : null, is_string($end) ? $end : null);
if ($rangeError !== null) {
    http_response_code(400);
    die($rangeError);
}

if (!CsvLocale::isValid($locale)) {
    http_response_code(400);
    die('Parameter locale tidak valid. Pilih salah satu: ' . implode(', ', array_keys(CsvLocale::options())) . '.');
}

try {
    SalesReport::streamCsv($start, $end, $locale);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Export laporan gagal: ' . $e->getMessage());
    die('Terjadi kesalahan saat membuat laporan. Silakan hubungi administrator.');
}
exit;
