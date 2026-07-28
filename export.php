<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$start  = $_GET['start'] ?? null;
$end    = $_GET['end'] ?? null;
$locale = $_GET['locale'] ?? Settings::getCsvLocale();

if (!$start || !$end
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)
) {
    http_response_code(400);
    die('Parameter tanggal tidak valid. Gunakan format YYYY-MM-DD.');
}

if ($start > $end) {
    http_response_code(400);
    die('Tanggal awal tidak boleh lebih besar dari tanggal akhir.');
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
