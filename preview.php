<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
Auth::requireLogin();

header('Content-Type: application/json; charset=UTF-8');

$start  = $_GET['start'] ?? null;
$end    = $_GET['end'] ?? null;
$locale = $_GET['locale'] ?? Settings::getCsvLocale();

if (!$start || !$end
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)
    || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)
) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Parameter tanggal tidak valid. Gunakan format YYYY-MM-DD.']);
    exit;
}

if ($start > $end) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Tanggal awal tidak boleh lebih besar dari tanggal akhir.']);
    exit;
}

if (!CsvLocale::isValid($locale)) {
    http_response_code(400);
    echo json_encode([
        'ok'    => false,
        'error' => 'Parameter locale tidak valid. Pilih salah satu: ' . implode(', ', array_keys(CsvLocale::options())) . '.',
    ]);
    exit;
}

try {
    // 50 baris pertama cukup untuk pratinjau tanpa membebani browser; ringkasan
    // total tetap dihitung dari SELURUH baris (lihat SalesReport::preview()).
    $preview = SalesReport::preview($start, $end, $locale, 50);
    echo json_encode(['ok' => true] + $preview, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Pratinjau laporan gagal: ' . $e->getMessage());
    echo json_encode(['ok' => false, 'error' => 'Terjadi kesalahan saat membuat pratinjau. Silakan hubungi administrator.']);
}
exit;
