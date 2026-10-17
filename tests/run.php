<?php
declare(strict_types=1);

/**
 * Runner tes tanpa dependensi (tanpa Composer/PHPUnit).
 *
 *   php tests/run.php
 *
 * Tes yang butuh PostgreSQL hanya jalan bila LP_TEST_PG_HOST diisi
 * (lihat tests/seed.sql dan .github/workflows/ci.yml); selain itu dilewati.
 * Semua tes memakai SQLite sementara, tidak menyentuh data/settings.db.
 */

ob_start(); // cegah "headers already sent" saat tes memakai session/CSV

$root = dirname(__DIR__);
$tmp  = sys_get_temp_dir() . '/lp-tests-' . bin2hex(random_bytes(4));
mkdir($tmp, 0777, true);
register_shutdown_function(static function () use ($tmp): void {
    foreach (glob($tmp . '/*') ?: [] as $f) {
        @unlink($f);
    }
    @rmdir($tmp);
});

ini_set('session.use_cookies', '0');
ini_set('session.use_only_cookies', '0');
ini_set('session.cache_limiter', '');
session_save_path($tmp);
session_start();

foreach (['Database', 'Auth', 'Csrf', 'CsvLocale', 'Settings', 'SalesReport'] as $lib) {
    require $root . '/lib/' . $lib . '.php';
}
Database::init(['sqlite_path' => $tmp . '/settings.db']);

class SkipTest extends Exception
{
}

/** @var array<int,array{0:string,1:callable}> */
$GLOBALS['tests'] = [];

function test(string $name, callable $fn): void
{
    $GLOBALS['tests'][] = [$name, $fn];
}

function check(bool $cond, string $msg = 'kondisi tidak terpenuhi'): void
{
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

function same(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            ($msg !== '' ? $msg . ': ' : '') . 'harapan ' . var_export($expected, true) . ', hasil ' . var_export($actual, true)
        );
    }
}

function skip(string $reason): never
{
    throw new SkipTest($reason);
}

/** Jalankan potongan PHP di proses terpisah (untuk kode yang memakai die/exit). */
function runPhp(string $code): string
{
    $cmd = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($code) . ' 2>&1';
    return (string) shell_exec($cmd);
}

/** Auth menyimpan hasil per permintaan di properti statis; reset antar tes. */
function resetAuth(): void
{
    $p = new ReflectionProperty(Auth::class, 'resolved');
    $p->setValue(null, false);
}

require __DIR__ . '/cases.php';

$pass = 0;
$fail = 0;
$skip = 0;
$report = [];
foreach ($GLOBALS['tests'] as [$name, $fn]) {
    try {
        $fn();
        $pass++;
        $report[] = "  ok    $name";
    } catch (SkipTest $e) {
        $skip++;
        $report[] = "  skip  $name ({$e->getMessage()})";
    } catch (Throwable $e) {
        $fail++;
        $report[] = "  GAGAL $name: {$e->getMessage()}";
    }
}

ob_end_clean();
echo implode("\n", $report), "\n";
echo "\nLulus: $pass, gagal: $fail, dilewati: $skip\n";
exit($fail > 0 ? 1 : 0);
