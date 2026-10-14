<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
header('Content-Type: application/json; charset=UTF-8');

// Endpoint JSON: sesi habis dijawab 401 (bukan redirect HTML) agar Dashboard bisa menampilkan pesan jelas.
if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Sesi berakhir. Muat ulang halaman dan login kembali.']);
    exit;
}

$start  = $_GET['start'] ?? null;
$end    = $_GET['end'] ?? null;
$locale = $_GET['locale'] ?? Settings::getCsvLocale();
if (!is_string($locale)) {
    $locale = '';
}

$rangeError = SalesReport::validateRange(is_string($start) ? $start : null, is_string($end) ? $end : null);
if ($rangeError !== null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $rangeError]);
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
