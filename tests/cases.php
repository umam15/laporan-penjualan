<?php
declare(strict_types=1);

/** Kasus tes. Dimuat oleh tests/run.php (helper: test, check, same, skip, runPhp, resetAuth). */

function appRoot(): string
{
    return dirname(__DIR__);
}

// ---------------------------------------------------------------- Versi

test('VERSION berformat x.y.z', function () {
    $v = trim((string) file_get_contents(appRoot() . '/VERSION'));
    check((bool) preg_match('/^\d+\.\d+\.\d+$/', $v), "VERSION tidak valid: $v");
});

test('VERSION sama dengan versi teratas di CHANGELOG.md', function () {
    $v   = trim((string) file_get_contents(appRoot() . '/VERSION'));
    $log = (string) file_get_contents(appRoot() . '/CHANGELOG.md');
    check((bool) preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', $log, $m), 'tidak ada heading versi di CHANGELOG.md');
    same($v, $m[1], 'VERSION vs CHANGELOG');
});

test('APP_VERSION dibaca dari VERSION (tidak di-hardcode)', function () {
    $src = (string) file_get_contents(appRoot() . '/bootstrap.php');
    check(str_contains($src, "/VERSION'"), 'bootstrap.php tidak membaca berkas VERSION');
    check(!preg_match("/define\('APP_VERSION',\s*'\d/", $src), 'APP_VERSION masih di-hardcode');
});

// ---------------------------------------------------------------- CsvLocale

test('CsvLocale: kode valid dan tidak valid', function () {
    check(CsvLocale::isValid('id') && CsvLocale::isValid('en'));
    check(!CsvLocale::isValid('zz') && !CsvLocale::isValid(null) && !CsvLocale::isValid(''));
});

test('CsvLocale: format angka id dan en', function () {
    same('1.234,50', CsvLocale::number(CsvLocale::profile('id'), 1234.5, 2));
    same('1234.50', CsvLocale::number(CsvLocale::profile('en'), 1234.5, 2));
});

test('CsvLocale: format tanggal id dan en', function () {
    $raw = '2026-07-01 08:00:44.864257';
    same('01/07/2026 08:00:44', CsvLocale::date(CsvLocale::profile('id'), $raw));
    same('2026-07-01 08:00:44', CsvLocale::date(CsvLocale::profile('en'), $raw));
});

// ---------------------------------------------------------------- CSRF

test('Csrf: token 64 hex, stabil dalam satu sesi, ada di field form', function () {
    unset($_SESSION['csrf_token']);
    $t = Csrf::token();
    check((bool) preg_match('/^[0-9a-f]{64}$/', $t), 'token bukan 64 hex');
    same($t, Csrf::token(), 'token berubah dalam sesi yang sama');
    check(str_contains(Csrf::field(), $t), 'field tidak memuat token');
});

test('Csrf: POST tanpa/salah token ditolak, token benar dan GET lolos', function () {
    $lib  = appRoot() . '/lib/Csrf.php';
    $make = static fn(string $method, string $post): string => runPhp(
        "require '$lib'; \$_SERVER['REQUEST_METHOD']='$method'; \$_SESSION=['csrf_token'=>'abc']; \$_POST=$post; Csrf::guard(); echo 'LOLOS';"
    );
    $tanpa = $make('POST', '[]');
    check(!str_contains($tanpa, 'LOLOS') && str_contains($tanpa, 'ditolak'), 'POST tanpa token lolos');
    $salah = $make('POST', "['csrf_token'=>'xyz']");
    check(!str_contains($salah, 'LOLOS'), 'POST token salah lolos');
    check(str_contains($make('POST', "['csrf_token'=>'abc']"), 'LOLOS'), 'POST token benar ditolak');
    check(str_contains($make('GET', '[]'), 'LOLOS'), 'GET ditolak');
});

// ---------------------------------------------------------------- Auth

$_SERVER['REMOTE_ADDR'] = '203.0.113.9';

test('Auth: password tersimpan sebagai hash, login benar/salah', function () {
    Auth::createUser('alice', 'rahasia-123', 'admin');
    $hash = (string) Database::sqlite()->query("SELECT password_hash FROM users WHERE username='alice'")->fetchColumn();
    check($hash !== 'rahasia-123' && password_verify('rahasia-123', $hash), 'password tidak di-hash');
    check(!Auth::attempt('alice', 'salah'), 'login password salah berhasil');
    check(Auth::attempt('alice', 'rahasia-123'), 'login password benar gagal');
    resetAuth();
    check(Auth::check() && Auth::isAdmin(), 'sesi admin tidak terbentuk');
});

test('Auth: login dibatasi setelah 5 kegagalan per username', function () {
    check(!Auth::isThrottled('ghost'), 'langsung terkunci');
    for ($i = 0; $i < 5; $i++) {
        Auth::attempt('ghost', 'x');
    }
    check(Auth::isThrottled('ghost'), 'tidak terkunci setelah 5 kegagalan');
    check(!Auth::isThrottled('orang-lain'), 'username lain ikut terkunci');
});

test('Auth: akun nonaktif tidak bisa login', function () {
    Auth::createUser('carol', 'rahasia-456', 'staff');
    Database::sqlite()->exec("UPDATE users SET active = 0 WHERE username = 'carol'");
    check(!Auth::attempt('carol', 'rahasia-456'), 'akun nonaktif bisa login');
});

test('Auth: sesi idle melewati batas dicabut', function () {
    Auth::attempt('alice', 'rahasia-123');
    resetAuth();
    check(Auth::check(), 'sesi awal tidak valid');
    $_SESSION['last_seen'] = time() - Auth::IDLE_TIMEOUT - 5;
    resetAuth();
    check(!Auth::check(), 'sesi idle masih berlaku');
});

test('Auth: ganti password mencabut sesi lama', function () {
    Auth::createUser('bob', 'rahasia-789', 'staff');
    Auth::attempt('bob', 'rahasia-789');
    $id = (int) Database::sqlite()->query("SELECT id FROM users WHERE username='bob'")->fetchColumn();
    $svLama = (int) $_SESSION['sv'];
    Auth::setPassword($id, 'baru-12345');
    resetAuth();
    check(Auth::check(), 'sesi pengganti password ikut tercabut');
    $_SESSION['sv'] = $svLama; // meniru sesi lama di perangkat lain
    resetAuth();
    check(!Auth::check(), 'sesi lama masih berlaku setelah ganti password');
});

// ---------------------------------------------------------------- Settings

test('Settings: tampilan Jenis default kode dan nilai tak valid jatuh ke kode', function () {
    same('kode', Settings::getJenisDisplay());
    Settings::set('jenis_display', 'nama');
    same('nama', Settings::getJenisDisplay());
    Settings::set('jenis_display', 'ngawur');
    same('kode', Settings::getJenisDisplay());
});

test('Settings: restore backup menolak nilai jenis_display tak valid', function () {
    Settings::set('jenis_display', 'kode');
    Settings::importBackupSettings(['jenis_display' => 'xxx']);
    check(Settings::get('jenis_display') !== 'xxx', 'nilai tak valid tersimpan');
    Settings::importBackupSettings(['jenis_display' => 'nama']);
    same('nama', Settings::getJenisDisplay());
    Settings::set('jenis_display', 'kode');
});

test('SalesReport: validasi rentang tanggal', function () {
    same(null, SalesReport::validateRange('2026-10-01', '2026-10-31'));
    check(is_string(SalesReport::validateRange('2026-10-31', '2026-10-01')), 'rentang terbalik lolos');
    check(is_string(SalesReport::validateRange('bukan-tanggal', '2026-10-01')), 'format salah lolos');
});

// ------------------------------------------------- Integrasi PostgreSQL (opsional)

function pgSetup(): void
{
    $host = getenv('LP_TEST_PG_HOST');
    if ($host === false || $host === '') {
        skip('LP_TEST_PG_HOST tidak diisi');
    }
    Settings::setDbOverrides([
        'db_host' => $host,
        'db_port' => getenv('LP_TEST_PG_PORT') ?: '5432',
        'db_name' => getenv('LP_TEST_PG_DB') ?: 'lp',
        'db_user' => getenv('LP_TEST_PG_USER') ?: 'lp',
        'db_pass' => getenv('LP_TEST_PG_PASS') ?: 'lp',
    ]);
}

/** @return array<string,string> kodeitem => nilai kolom Jenis pada pratinjau */
function jenisPerItem(): array
{
    $out = [];
    foreach (SalesReport::preview('2026-10-01', '2026-10-31', 'id', 50)['rows'] as $r) {
        $out[$r['kodeitem']] = $r['jenis'];
    }
    return $out;
}

test('PG: kolom Jenis mode kode (default)', function () {
    pgSetup();
    Settings::set('jenis_display', 'kode');
    same(['A001' => 'BOLT', 'B001' => 'OIL', 'C001' => 'ZZZ', 'D001' => '', 'X999' => ''], jenisPerItem());
});

test('PG: kolom Jenis mode nama, cadangan ke kode bila nama kosong', function () {
    pgSetup();
    Settings::set('jenis_display', 'nama');
    same(['A001' => 'BAUT MUR', 'B001' => 'OIL', 'C001' => 'ZZZ', 'D001' => '', 'X999' => ''], jenisPerItem());
    Settings::set('jenis_display', 'kode');
});

test('PG: CSV punya kolom Jenis tepat setelah Nama Item', function () {
    pgSetup();
    ob_start();
    SalesReport::streamCsv('2026-10-01', '2026-10-31', 'id');
    $csv = (string) ob_get_clean();
    $header = explode("\n", ltrim($csv, "\xEF\xBB\xBF"))[0];
    $cols = str_getcsv(trim($header), ';');
    $i = array_search('Nama Item', $cols, true);
    check($i !== false, 'kolom Nama Item tidak ada');
    same('Jenis', $cols[$i + 1] ?? null, 'kolom setelah Nama Item');
    same(14, count($cols), 'jumlah kolom CSV');
});
