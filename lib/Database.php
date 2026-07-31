<?php
declare(strict_types=1);

/**
 * Wrapper sederhana untuk dua koneksi PDO:
 *  - pgsql()  : database transaksi (PostgreSQL, read-only untuk aplikasi ini)
 *  - sqlite() : database lokal untuk user & pengaturan (settings.db)
 */
class Database
{
    private static ?PDO $pgsql = null;
    private static ?PDO $sqlite = null;
    private static array $config = [];

    public static function init(array $config): void
    {
        self::$config = $config;
    }

    /**
     * @return array Konfigurasi mentah dari config.php. Sejak v1.1.1 file
     *     ini tidak lagi berisi kredensial pgsql (hanya sqlite_path), jadi
     *     resolvePgsqlConfig() akan mengandalkan Settings::getDbOverrides().
     */
    public static function getConfig(): array
    {
        return self::$config;
    }

    /**
     * Resolusi konfigurasi koneksi pgsql: utamakan nilai yang diatur admin
     * lewat halaman Pengaturan (tersimpan di settings.db via Settings), dan
     * jatuh ke config.php untuk key yang belum pernah diatur.
     */
    public static function resolvePgsqlConfig(): array
    {
        $default = self::$config['pgsql'] ?? [];
        $override = class_exists('Settings') ? Settings::getDbOverrides() : [];
        return [
            'host'   => ($override['db_host'] ?? '') !== '' ? $override['db_host'] : ($default['host'] ?? ''),
            'port'   => ($override['db_port'] ?? '') !== '' ? $override['db_port'] : ($default['port'] ?? ''),
            'dbname' => ($override['db_name'] ?? '') !== '' ? $override['db_name'] : ($default['dbname'] ?? ''),
            'user'   => ($override['db_user'] ?? '') !== '' ? $override['db_user'] : ($default['user'] ?? ''),
            'pass'   => ($override['db_pass'] ?? '') !== '' ? $override['db_pass'] : ($default['pass'] ?? ''),
        ];
    }

    public static function pgsql(): PDO
    {
        if (self::$pgsql === null) {
            $c = self::resolvePgsqlConfig();
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $c['host'],
                $c['port'],
                $c['dbname']
            );
            self::$pgsql = new PDO($dsn, $c['user'], $c['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$pgsql;
    }

    /**
     * Buat koneksi PDO pgsql baru (tidak memakai/menimpa cache self::$pgsql),
     * dipakai admin_settings.php untuk tombol "Test Koneksi" sebelum
     * menyimpan pengaturan.
     *
     * @throws PDOException bila koneksi gagal.
     */
    public static function testPgsqlConnection(array $c): void
    {
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $c['host'], $c['port'], $c['dbname']);
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $pdo->query('SELECT 1');
    }

    public static function sqlite(): PDO
    {
        if (self::$sqlite === null) {
            $path = self::$config['sqlite_path'];
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $isNew = !file_exists($path);

            self::$sqlite = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            self::$sqlite->exec('PRAGMA foreign_keys = ON;');

            // Skema dibuat otomatis kalau belum ada (mis. deploy pertama kali,
            // atau file settings.db terhapus) - tidak perlu install.php lagi,
            // begitu juga admin pertama: lihat login.php ($isSetup).
            self::$sqlite->exec("CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'staff',
                active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )");
            self::$sqlite->exec("CREATE TABLE IF NOT EXISTS settings (
                key TEXT PRIMARY KEY,
                value TEXT
            )");

            if ($isNew) {
                $defaults = [
                    'kantor_filter'      => '',
                    'pelanggan_filter'   => '',
                    'item_prefix_filter' => '',
                    'tax_rate'           => '0',
                    'csv_locale'         => 'id',
                ];
                $ins = self::$sqlite->prepare(
                    'INSERT INTO settings (key, value) VALUES (:k, :v)'
                );
                foreach ($defaults as $k => $v) {
                    $ins->execute([':k' => $k, ':v' => $v]);
                }
            }
        }
        return self::$sqlite;
    }
}
