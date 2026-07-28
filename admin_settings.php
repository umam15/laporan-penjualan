<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
Auth::requireAdmin();

$message = '';
$error = '';
$dbMessage = '';
$dbError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'db') {
    $dbDefault = Database::getConfig()['pgsql'] ?? [];
    $storedPass = Settings::get('db_pass', $dbDefault['pass'] ?? '');

    $dbHost = trim((string) ($_POST['db_host'] ?? ''));
    $dbPort = trim((string) ($_POST['db_port'] ?? ''));
    $dbName = trim((string) ($_POST['db_name'] ?? ''));
    $dbUser = trim((string) ($_POST['db_user'] ?? ''));
    $dbPassInput = (string) ($_POST['db_pass'] ?? '');
    // Password kosong di form = "jangan ubah", tetap pakai password tersimpan.
    $dbPass = $dbPassInput === '' ? (string) $storedPass : $dbPassInput;

    if ($dbHost === '' || $dbPort === '' || $dbName === '' || $dbUser === '') {
        $dbError = 'Host, Port, Nama Database, dan User wajib diisi.';
    } elseif (!ctype_digit($dbPort)) {
        $dbError = 'Port harus berupa angka.';
    } else {
        try {
            Database::testPgsqlConnection([
                'host' => $dbHost, 'port' => $dbPort, 'dbname' => $dbName,
                'user' => $dbUser, 'pass' => $dbPass,
            ]);
            Settings::setDbOverrides([
                'db_host' => $dbHost, 'db_port' => $dbPort, 'db_name' => $dbName,
                'db_user' => $dbUser, 'db_pass' => $dbPassInput,
            ]);
            $dbMessage = 'Pengaturan database berhasil disimpan & koneksi teruji.';
        } catch (Throwable $e) {
            $dbError = 'Gagal terhubung ke database, pengaturan TIDAK disimpan: ' . $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? 'general') === 'general') {
    Settings::set('kantor_filter', trim((string) ($_POST['kantor_filter'] ?? '')));
    Settings::set('pelanggan_filter', trim((string) ($_POST['pelanggan_filter'] ?? '')));
    Settings::set('item_prefix_filter', trim((string) ($_POST['item_prefix_filter'] ?? '')));

    $taxRaw = str_replace(',', '.', trim((string) ($_POST['tax_rate'] ?? '0.5')));
    $csvLocaleRaw = trim((string) ($_POST['csv_locale'] ?? 'id'));
    $taxSourceRaw = trim((string) ($_POST['tax_source'] ?? 'manual'));
    $skipZeroTaxRaw = isset($_POST['database_only_skip_zero_tax']) ? '1' : '0';

    $dashboardFieldsRaw = $_POST['dashboard_visible_fields'] ?? [];
    if (!is_array($dashboardFieldsRaw)) {
        $dashboardFieldsRaw = [];
    }
    $dashboardFieldsSelected = array_values(array_intersect(
        $dashboardFieldsRaw,
        array_keys(Settings::DASHBOARD_FIELDS)
    ));

    if (!is_numeric($taxRaw) || (float) $taxRaw < 0) {
        $error = 'Tarif pajak harus berupa angka >= 0.';
    } elseif (!CsvLocale::isValid($csvLocaleRaw)) {
        $error = 'Format CSV (locale) tidak valid.';
    } elseif (!in_array($taxSourceRaw, Settings::TAX_SOURCES, true)) {
        $error = 'Sumber tarif pajak tidak valid.';
    } else {
        Settings::set('tax_rate', (string) (float) $taxRaw);
        Settings::set('csv_locale', $csvLocaleRaw);
        Settings::set('tax_source', $taxSourceRaw);
        Settings::set('database_only_skip_zero_tax', $skipZeroTaxRaw);
        Settings::set('dashboard_visible_fields', implode(',', $dashboardFieldsSelected));
        $message = 'Pengaturan berhasil disimpan.';
    }
}

$kantorFilter     = Settings::get('kantor_filter', '');
$pelangganFilter  = Settings::get('pelanggan_filter', '');
$itemPrefixFilter = Settings::get('item_prefix_filter', '');
$taxRate          = Settings::getTaxRate();
$csvLocale        = Settings::getCsvLocale();
$taxSource        = Settings::getTaxSource();
$skipZeroTaxDbOnly = Settings::getDatabaseOnlySkipZeroTax();
$dashboardVisibleFields = Settings::getDashboardVisibleFields();

// Nilai tampilan form Pengaturan Database: pakai override tersimpan di
// settings.db (config.php sejak v1.1.1 tidak lagi menyimpan kredensial
// pgsql, jadi hanya dipakai sbg fallback kosong). Password TIDAK pernah
// ditampilkan kembali di form, demi keamanan.
$dbConfigDefault = Database::getConfig()['pgsql'] ?? [];
$dbOverrides      = Settings::getDbOverrides();
$dbHostVal        = $dbOverrides['db_host'] ?? ($dbConfigDefault['host'] ?? '');
$dbPortVal        = $dbOverrides['db_port'] ?? ($dbConfigDefault['port'] ?? '');
$dbNameVal        = $dbOverrides['db_name'] ?? ($dbConfigDefault['dbname'] ?? '');
$dbUserVal        = $dbOverrides['db_user'] ?? ($dbConfigDefault['user'] ?? '');
$dbUsingOverride  = $dbOverrides['db_host'] !== null;

// Daftar kantor sebagai referensi bagi admin saat mengisi filter
$kantorList = [];
try {
    $kantorList = Database::pgsql()
        ->query('SELECT kodekantor, namakantor FROM tbl_kantor ORDER BY kodekantor')
        ->fetchAll();
} catch (Throwable $e) {
    $error = $error ?: 'Tidak dapat mengambil daftar kantor dari database: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pengaturan - Laporan Penjualan</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include __DIR__ . '/partials/nav.php'; ?>
<div class="container">
    <h1>Pengaturan Laporan</h1>
    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" class="card">
        <input type="hidden" name="form" value="general">
        <label>Filter Kantor</label>
        <input type="text" name="kantor_filter" value="<?= htmlspecialchars($kantorFilter) ?>" placeholder="cth: KTR01,KTR02">
        <p class="hint">Kode kantor, pisahkan dengan koma. Kosongkan untuk mengizinkan semua kantor.</p>

        <label>Filter Pelanggan</label>
        <input type="text" name="pelanggan_filter" value="<?= htmlspecialchars($pelangganFilter) ?>" placeholder="cth: PL001,PL002">
        <p class="hint">Kode pelanggan, pisahkan dengan koma. Kosongkan untuk mengizinkan semua pelanggan.</p>

        <label>Filter Kode Item</label>
        <input type="text" name="item_prefix_filter" value="<?= htmlspecialchars($itemPrefixFilter) ?>" placeholder="cth: 10,11,2A">
        <p class="hint">1 atau 2 karakter awal kode item, pisahkan dengan koma. Kosongkan untuk mengizinkan semua item.</p>

        <label>Sumber Tarif Pajak</label>
        <div class="radio-group">
            <label class="radio-inline">
                <input type="radio" name="tax_source" value="manual" <?= $taxSource === 'manual' ? 'checked' : '' ?>>
                <span>Manual <span class="hint-inline">— selalu pakai tarif tetap di bawah.</span></span>
            </label>
            <label class="radio-inline">
                <input type="radio" name="tax_source" value="database" <?= $taxSource === 'database' ? 'checked' : '' ?>>
                <span>Otomatis <span class="hint-inline">— ambil dari transaksi, cadangan ke tarif manual bila kosong.</span></span>
            </label>
            <label class="radio-inline">
                <input type="radio" name="tax_source" value="database_only" <?= $taxSource === 'database_only' ? 'checked' : '' ?>>
                <span>Transaksi <span class="hint-inline">— ambil dari transaksi saja, tanpa cadangan.</span></span>
            </label>
        </div>

        <div id="skip-zero-tax-wrap">
            <label class="checkbox-inline">
                <input type="checkbox" name="database_only_skip_zero_tax" id="skip-zero-tax"
                    <?= $skipZeroTaxDbOnly ? 'checked' : '' ?>
                    <?= $taxSource === 'database_only' ? '' : 'disabled' ?>>
                Jangan unduh baris yang pajaknya 0 <span class="hint-inline">(khusus sumber "Transaksi")</span>
            </label>
        </div>

        <label>Tarif Pajak Keluaran Manual (%)</label>
        <input type="text" name="tax_rate" value="<?= htmlspecialchars((string) $taxRate) ?>" placeholder="0.5">
        <p class="hint">Contoh: isi "0.5" untuk 0,5%. Dipakai bila Sumber Tarif Pajak = Manual, atau sebagai cadangan bila = Otomatis.</p>

        <label>Format CSV (locale) Default</label>
        <select name="csv_locale">
            <?php foreach (CsvLocale::options() as $code => $label): ?>
            <option value="<?= htmlspecialchars($code) ?>" <?= $csvLocale === $code ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
        <p class="hint">Pengguna masih bisa mengganti pilihan ini per-download di Dashboard.</p>

        <label>Tampilkan di Dashboard untuk Staf</label>
        <div class="radio-group">
            <?php foreach (Settings::DASHBOARD_FIELDS as $fieldKey => $fieldLabel): ?>
            <label class="checkbox-inline">
                <input type="checkbox" name="dashboard_visible_fields[]" value="<?= htmlspecialchars($fieldKey) ?>"
                    <?= in_array($fieldKey, $dashboardVisibleFields, true) ? 'checked' : '' ?>>
                <?= htmlspecialchars($fieldLabel) ?>
            </label>
            <?php endforeach; ?>
        </div>
        <p class="hint">Centang baris yang boleh dilihat staf di Dashboard. Admin selalu melihat semua baris.</p>

        <button type="submit">Simpan Pengaturan</button>
    </form>

    <div class="card">
        <h3>Pengaturan Database</h3>
        <p class="hint">
            Koneksi ke database transaksi (PostgreSQL).
            <?= $dbUsingOverride
                ? 'Saat ini memakai pengaturan kustom di bawah.'
                : 'Belum pernah diatur — isi Host, Port, Nama Database, User, dan Password di bawah ini untuk menghubungkan aplikasi ke database transaksi.' ?>
        </p>
        <?php if ($dbMessage): ?><div class="alert success"><?= htmlspecialchars($dbMessage) ?></div><?php endif; ?>
        <?php if ($dbError): ?><div class="alert error"><?= htmlspecialchars($dbError) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="form" value="db">

            <label>Host</label>
            <input type="text" name="db_host" value="<?= htmlspecialchars((string) $dbHostVal) ?>" placeholder="cth: 127.0.0.1" required>

            <label>Port</label>
            <input type="text" name="db_port" value="<?= htmlspecialchars((string) $dbPortVal) ?>" placeholder="cth: 5432" required>

            <label>Nama Database</label>
            <input type="text" name="db_name" value="<?= htmlspecialchars((string) $dbNameVal) ?>" required>

            <label>User</label>
            <input type="text" name="db_user" value="<?= htmlspecialchars((string) $dbUserVal) ?>" required>

            <label>Password</label>
            <input type="password" name="db_pass" value="" autocomplete="new-password" placeholder="Kosongkan bila tidak ingin mengubah password">
            <p class="hint">Password tersimpan tidak ditampilkan ulang di sini. Kosongkan field ini untuk tetap memakai password yang sudah tersimpan.</p>

            <button type="submit">Simpan &amp; Uji Koneksi</button>
        </form>
        <p class="hint">Koneksi akan diuji terlebih dahulu sebelum disimpan. Bila gagal terhubung, pengaturan lama tetap dipakai.</p>
    </div>

    <div class="card info">
        <h3>Referensi Kode Kantor</h3>
        <?php if (empty($kantorList)): ?>
            <p>Tidak ada data kantor atau gagal memuat.</p>
        <?php else: ?>
        <div class="table-responsive">
        <table>
            <tr><th>Kode</th><th>Nama Kantor</th></tr>
            <?php foreach ($kantorList as $k): ?>
            <tr><td><?= htmlspecialchars($k['kodekantor']) ?></td><td><?= htmlspecialchars($k['namakantor']) ?></td></tr>
            <?php endforeach; ?>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
    var radios = document.querySelectorAll('input[name="tax_source"]');
    var checkbox = document.getElementById('skip-zero-tax');
    if (!checkbox || !radios.length) return;

    function sync() {
        var selected = document.querySelector('input[name="tax_source"]:checked');
        var isDbOnly = !!selected && selected.value === 'database_only';
        checkbox.disabled = !isDbOnly;
    }

    radios.forEach(function (r) { r.addEventListener('change', sync); });
})();
</script>
</body>
</html>
