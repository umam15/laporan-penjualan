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

        <div class="form-actions">
            <button type="button" id="btnPreview" class="secondary" <?= $dbConnected ? '' : 'disabled' ?>>👁 Pratinjau</button>
            <button type="submit" <?= $dbConnected ? '' : 'disabled' ?>>⬇ Unduh CSV</button>
        </div>
        <p class="hint">Default: awal bulan ini sampai hari ini. Ubah sesuai kebutuhan, klik <strong>Pratinjau</strong> untuk cek data dulu, lalu <strong>Unduh CSV</strong>.</p>
    </form>

    <div class="card" id="previewCard" hidden>
        <h3>Pratinjau Laporan</h3>
        <div id="previewStatus" class="hint"></div>
        <div id="previewResult" hidden>
            <div class="preview-summary" id="previewSummary"></div>
            <div class="table-responsive">
                <table id="previewTable">
                    <thead>
                        <tr>
                            <th>No Transaksi</th>
                            <th>Tanggal</th>
                            <th>Dept</th>
                            <th>Kode Pelanggan</th>
                            <th>Jumlah Item</th>
                            <th>Kode Item</th>
                            <th>Nama Item</th>
                            <th>Pokok</th>
                            <th>Total</th>
                            <th>Pajak Keluaran</th>
                            <th>Laba Kotor</th>
                            <th>Proc Laba (%)</th>
                            <th>GPM</th>
                        </tr>
                    </thead>
                    <tbody id="previewTbody"></tbody>
                </table>
            </div>
            <p class="hint" id="previewNote"></p>
        </div>
    </div>

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

<script>
(function () {
    var form          = document.querySelector('form[action="export.php"]');
    var btnPreview    = document.getElementById('btnPreview');
    var previewCard   = document.getElementById('previewCard');
    var previewStatus = document.getElementById('previewStatus');
    var previewResult = document.getElementById('previewResult');
    var previewSummary= document.getElementById('previewSummary');
    var previewTbody  = document.getElementById('previewTbody');
    var previewNote   = document.getElementById('previewNote');

    if (!form || !btnPreview) {
        return;
    }

    var COLUMNS = [
        'notransaksi', 'tanggal', 'dept', 'kodesupel', 'jumlah_item',
        'kodeitem', 'namaitem', 'pokok', 'total', 'pajak_keluaran',
        'laba_kotor', 'proc_laba', 'gpm'
    ];

    function clearPreview() {
        previewResult.hidden = true;
        previewTbody.innerHTML = '';
        previewSummary.innerHTML = '';
        previewNote.textContent = '';
    }

    function summaryItem(label, value) {
        var span = document.createElement('span');
        span.className = 'preview-summary-item';
        var strong = document.createElement('strong');
        strong.textContent = label + ': ';
        span.appendChild(strong);
        span.appendChild(document.createTextNode(value));
        return span;
    }

    btnPreview.addEventListener('click', function () {
        var start  = form.elements['start'].value;
        var end    = form.elements['end'].value;
        var locale = form.elements['locale'].value;

        if (!start || !end) {
            previewCard.hidden = false;
            clearPreview();
            previewStatus.textContent = 'Isi tanggal awal dan tanggal akhir terlebih dahulu.';
            return;
        }

        previewCard.hidden = false;
        clearPreview();
        previewStatus.textContent = 'Memuat pratinjau…';
        btnPreview.disabled = true;

        var url = 'preview.php?start=' + encodeURIComponent(start) +
                  '&end=' + encodeURIComponent(end) +
                  '&locale=' + encodeURIComponent(locale);

        fetch(url, { credentials: 'same-origin' })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { status: res.status, data: data };
                });
            })
            .then(function (r) {
                btnPreview.disabled = false;
                if (!r.data.ok) {
                    previewStatus.textContent = r.data.error || 'Gagal memuat pratinjau.';
                    return;
                }

                var d = r.data;

                if (d.total_count === 0) {
                    previewStatus.textContent = 'Tidak ada data penjualan pada rentang tanggal & filter yang dipilih.';
                    return;
                }

                previewStatus.textContent = '';

                previewSummary.appendChild(summaryItem('Jumlah Baris', String(d.total_count)));
                previewSummary.appendChild(summaryItem('Total Pokok', d.totals.pokok));
                previewSummary.appendChild(summaryItem('Total Penjualan', d.totals.total));
                previewSummary.appendChild(summaryItem('Total Pajak Keluaran', d.totals.pajak_keluaran));
                previewSummary.appendChild(summaryItem('Total Laba Kotor', d.totals.laba_kotor));

                d.rows.forEach(function (row) {
                    var tr = document.createElement('tr');
                    COLUMNS.forEach(function (col) {
                        var td = document.createElement('td');
                        td.textContent = row[col] !== undefined ? row[col] : '';
                        tr.appendChild(td);
                    });
                    previewTbody.appendChild(tr);
                });

                if (d.truncated) {
                    previewNote.textContent = 'Menampilkan ' + d.shown_count + ' dari ' + d.total_count +
                        ' baris. Klik "Unduh CSV" untuk mendapatkan data lengkap.';
                } else {
                    previewNote.textContent = 'Menampilkan seluruh ' + d.total_count + ' baris yang cocok.';
                }

                previewResult.hidden = false;
            })
            .catch(function () {
                btnPreview.disabled = false;
                previewStatus.textContent = 'Terjadi kesalahan jaringan saat memuat pratinjau. Silakan coba lagi.';
            });
    });
})();
</script>
</body>
</html>
