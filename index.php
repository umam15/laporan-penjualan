<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$defaultStart = date('Y-m-01'); // awal bulan ini
$defaultEnd   = date('Y-m-d');  // hari ini

$startDate = $_GET['start'] ?? $defaultStart;
$endDate   = $_GET['end'] ?? $defaultEnd;
$locale    = $_GET['locale'] ?? Settings::getCsvLocale();
if (!CsvLocale::isValid($locale)) {
    $locale = Settings::getCsvLocale();
}

$taxRate = Settings::getTaxRate();
$taxSource = Settings::getTaxSource();
$taxSourceLabel = Settings::getTaxSourceLabel();
$skipZeroTaxDbOnly = Settings::getDatabaseOnlySkipZeroTax();
$kantorFilter = Settings::getKantorFilter();
$pelangganFilter = Settings::getPelangganFilter();
$itemPrefixFilter = Settings::getItemPrefixFilter();

// Admin selalu melihat semua baris di kartu "Filter & Pengaturan Aktif".
// Untuk staf (non-admin), tampilkan hanya field yang diizinkan lewat
// Settings::getDashboardVisibleFields() (diatur di admin_settings.php).
$isAdmin = Auth::isAdmin();
$showField = static function (string $field) use ($isAdmin): bool {
    return $isAdmin || Settings::isDashboardFieldVisible($field);
};

// Cek cepat koneksi ke database transaksi (PostgreSQL) sebelum staf mencoba
// mengunduh. Tanpa ini, staf baru tahu koneksi bermasalah setelah klik
// "Unduh CSV" dan mendapat pesan generik dari export.php.
$dbConnected = true;
$dbErrorDetail = '';
try {
    Database::pgsql()->query('SELECT 1');
} catch (Throwable $e) {
    $dbConnected = false;
    $dbErrorDetail = $e->getMessage();
    error_log('Dashboard: koneksi database transaksi gagal: ' . $dbErrorDetail);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard - Laporan Penjualan</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include __DIR__ . '/partials/nav.php'; ?>

<div class="container">
    <h1>Download Laporan Penjualan</h1>

    <?php if (!$dbConnected): ?>
    <div class="alert warning">
        <strong>⚠ Tidak dapat terhubung ke database transaksi.</strong>
        Unduh laporan belum bisa dilakukan saat ini. Silakan coba lagi beberapa saat lagi,
        atau hubungi administrator bila masalah berlanjut.
        <?php if ($isAdmin): ?>
            <br>
            <span class="hint-inline">Detail teknis: <?= htmlspecialchars($dbErrorDetail) ?></span>
            — periksa <a href="admin_settings.php">Pengaturan Database</a>.
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <form method="get" action="export.php" class="card">
        <div class="form-row">
            <div>
                <label>Tanggal Awal</label>
                <input type="date" name="start" value="<?= htmlspecialchars($startDate) ?>" required <?= $dbConnected ? '' : 'disabled' ?>>
            </div>
            <div>
                <label>Tanggal Akhir</label>
                <input type="date" name="end" value="<?= htmlspecialchars($endDate) ?>" required <?= $dbConnected ? '' : 'disabled' ?>>
            </div>
        </div>
        <label>Format CSV (locale)</label>
        <select name="locale" <?= $dbConnected ? '' : 'disabled' ?>>
            <?php foreach (CsvLocale::options() as $code => $label): ?>
            <option value="<?= htmlspecialchars($code) ?>" <?= $locale === $code ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="hint">Menentukan delimiter kolom, pemisah desimal/ribuan, dan format tanggal pada file CSV.</p>

        <button type="submit" <?= $dbConnected ? '' : 'disabled' ?>>⬇ Unduh CSV</button>
        <p class="hint">Default: awal bulan ini sampai hari ini. Ubah sesuai kebutuhan lalu klik unduh.</p>
    </form>

    <div class="card info">
        <h3>Filter &amp; Pengaturan Aktif</h3>
        <ul>
            <?php if ($showField('tax_rate')): ?>
            <li><strong>Tarif Pajak Keluaran:</strong> <?= htmlspecialchars((string) $taxRate) ?>%<?= $taxSource === 'database_only' ? ' (cadangan, mungkin tak dipakai)' : '' ?></li>
            <?php endif; ?>
            <?php if ($showField('tax_source')): ?>
            <li>
                <strong>Sumber Tarif Pajak:</strong> <?= htmlspecialchars($taxSourceLabel) ?>
                <?php if ($taxSource === 'database_only' && $skipZeroTaxDbOnly): ?>
                <span class="hint-inline">(baris tanpa pajak tidak diunduh)</span>
                <?php endif; ?>
            </li>
            <?php endif; ?>
            <?php if ($showField('kantor_filter')): ?>
            <li><strong>Filter Kantor:</strong> <?= $kantorFilter ? htmlspecialchars(implode(', ', $kantorFilter)) : 'Semua kantor' ?></li>
            <?php endif; ?>
            <?php if ($showField('pelanggan_filter')): ?>
            <li><strong>Filter Pelanggan:</strong> <?= $pelangganFilter ? htmlspecialchars(implode(', ', $pelangganFilter)) : 'Semua pelanggan' ?></li>
            <?php endif; ?>
            <?php if ($showField('item_prefix_filter')): ?>
            <li><strong>Filter Kode Item (prefix):</strong> <?= $itemPrefixFilter ? htmlspecialchars(implode(', ', $itemPrefixFilter)) : 'Semua item' ?></li>
            <?php endif; ?>
            <?php if (!$isAdmin && empty(Settings::getDashboardVisibleFields())): ?>
            <li>(Tidak ada info pengaturan yang ditampilkan untuk akun Anda.)</li>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
            <li><a href="admin_settings.php">Ubah pengaturan ini →</a></li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="card info">
        <h3>Catatan Perhitungan</h3>
        <ul>
            <li>Data mencakup seluruh transaksi penjualan pada rentang tanggal terpilih, termasuk transaksi antar cabang.</li>
            <li>Jumlah Item, Pokok, dan Total sudah dihitung <strong>bersih setelah dikurangi retur</strong>.</li>
            <li>Pajak Keluaran = Total x tarif pajak pada pengaturan.</li>
            <li>Laba Kotor = Total - Pokok. Persentase Laba = Laba Kotor / Pokok x 100. Margin Laba Kotor = Laba Kotor / Total x 100.</li>
        </ul>
    </div>
</div>
</body>
</html>
