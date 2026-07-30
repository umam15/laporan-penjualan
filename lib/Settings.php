<?php
declare(strict_types=1);

/**
 * Penyimpanan pengaturan aplikasi (key-value) di settings.db.
 * Dipakai untuk: filter kantor, filter pelanggan, filter prefix kode item,
 * dan tarif pajak keluaran.
 */
class Settings
{
    private static function db(): PDO
    {
        return Database::sqlite();
    }

    public static function get(string $key, $default = null)
    {
        $stmt = self::db()->prepare('SELECT value FROM settings WHERE key = :k');
        $stmt->execute([':k' => $key]);
        $v = $stmt->fetchColumn();
        return $v === false ? $default : $v;
    }

    public static function set(string $key, string $value): void
    {
        $stmt = self::db()->prepare(
            'INSERT INTO settings (key, value) VALUES (:k, :v)
             ON CONFLICT(key) DO UPDATE SET value = excluded.value'
        );
        $stmt->execute([':k' => $key, ':v' => $value]);
    }

    public static function all(): array
    {
        $stmt = self::db()->query('SELECT key, value FROM settings');
        $res = [];
        foreach ($stmt->fetchAll() as $row) {
            $res[$row['key']] = $row['value'];
        }
        return $res;
    }

    private static function csvToArray(?string $csv): array
    {
        if ($csv === null || trim($csv) === '') {
            return [];
        }
        $parts = array_map('trim', explode(',', $csv));
        return array_values(array_filter($parts, fn($p) => $p !== ''));
    }

    /** @return string[] daftar kode kantor yang diizinkan. Kosong = semua kantor. */
    public static function getKantorFilter(): array
    {
        return self::csvToArray(self::get('kantor_filter', ''));
    }

    /** @return string[] daftar kode pelanggan yang diizinkan. Kosong = semua pelanggan. */
    public static function getPelangganFilter(): array
    {
        return self::csvToArray(self::get('pelanggan_filter', ''));
    }

    /** @return string[] daftar prefix (1-2 karakter awal) kode item yang diizinkan. Kosong = semua item. */
    public static function getItemPrefixFilter(): array
    {
        return self::csvToArray(self::get('item_prefix_filter', ''));
    }

    /** @return float tarif pajak keluaran dalam persen, mis. 0.5 berarti 0.5% */
    public static function getTaxRate(): float
    {
        return (float) self::get('tax_rate', '0.5');
    }

    /**
     * @return string sumber tarif pajak, salah satu dari:
     *   - 'manual'        : selalu pakai tax_rate tetap dari pengaturan ini.
     *   - 'database'      : ambil dari kolom pajak pada transaksi
     *                        penjualan/pembelian di database (tbl_ikdt.pajak,
     *                        fallback tbl_ikhd.prpajak), dan bila baris/header
     *                        transaksi sama-sama kosong/0, fallback terakhir
     *                        ke tax_rate manual.
     *   - 'database_only' : sama seperti 'database', TAPI tanpa fallback ke
     *                        tax_rate manual sama sekali — murni dari kolom
     *                        pajak transaksi. Bila baris & header transaksi
     *                        sama-sama kosong/0, pajak baris tersebut dianggap
     *                        0 (bukan memakai tarif manual).
     */
    public const TAX_SOURCES = ['manual', 'database', 'database_only'];

    public static function getTaxSource(): string
    {
        $v = (string) self::get('tax_source', 'manual');
        return in_array($v, self::TAX_SOURCES, true) ? $v : 'manual';
    }

    /** @return string label ramah-pengguna untuk nilai Settings::getTaxSource(), dipakai di Dashboard. */
    public static function getTaxSourceLabel(): string
    {
        $labels = [
            'manual'        => 'Manual',
            'database'      => 'Otomatis',
            'database_only' => 'Transaksi',
        ];
        return $labels[self::getTaxSource()] ?? $labels['manual'];
    }

    /** @return string kode locale format CSV default: 'id' atau 'en'. */
    public static function getCsvLocale(): string
    {
        $v = self::get('csv_locale', 'id');
        return CsvLocale::isValid($v) ? $v : 'id';
    }

    /**
     * @return bool Hanya berlaku bila getTaxSource() === 'database_only'.
     *   true  : baris transaksi yang tarif pajaknya kosong/0 (baris & header
     *           sama-sama tidak punya nilai pajak) TIDAK diikutsertakan sama
     *           sekali dalam data yang diunduh (CSV) — baris tsb dilewati.
     *   false : baris tsb tetap diikutsertakan dalam data yang diunduh,
     *           dengan Pajak Keluaran dilaporkan sebagai 0 (perilaku lama).
     *   Tidak berpengaruh sama sekali bila Sumber Tarif Pajak = 'manual' atau
     *   'database' (keduanya selalu punya nilai pajak > 0 lewat cadangan).
     */
    public static function getDatabaseOnlySkipZeroTax(): bool
    {
        return self::get('database_only_skip_zero_tax', '0') === '1';
    }

    /**
     * Daftar field yang bisa ditampilkan/disembunyikan pada kartu "Filter &
     * Pengaturan Aktif" di Dashboard (index.php) untuk staf (non-admin).
     * key => label yang dipakai di form pengaturan admin.
     */
    public const DASHBOARD_FIELDS = [
        'tax_rate'           => 'Tarif Pajak Keluaran',
        'tax_source'         => 'Sumber Tarif Pajak',
        'kantor_filter'      => 'Filter Kantor',
        'pelanggan_filter'   => 'Filter Pelanggan',
        'item_prefix_filter' => 'Filter Kode Item (prefix)',
    ];

    /**
     * @return string[] daftar key field (lihat DASHBOARD_FIELDS) yang ditampilkan
     *   ke staf pada kartu "Filter & Pengaturan Aktif" di Dashboard. Default:
     *   semua field ditampilkan (perilaku lama). Tidak berpengaruh untuk admin —
     *   admin selalu melihat semua field apa pun isi setting ini.
     */
    public static function getDashboardVisibleFields(): array
    {
        $raw = self::get('dashboard_visible_fields', null);
        // Belum pernah diatur -> default semua field tampil (backward compatible).
        if ($raw === null) {
            return array_keys(self::DASHBOARD_FIELDS);
        }
        $selected = self::csvToArray($raw);
        return array_values(array_intersect($selected, array_keys(self::DASHBOARD_FIELDS)));
    }

    /**
     * @param string $field salah satu key dari DASHBOARD_FIELDS.
     * @return bool true bila field tsb harus ditampilkan ke staf di Dashboard.
     */
    public static function isDashboardFieldVisible(string $field): bool
    {
        return in_array($field, self::getDashboardVisibleFields(), true);
    }

    /**
     * Key pengaturan koneksi database PostgreSQL yang bisa ditimpa lewat
     * halaman Pengaturan (admin_settings.php). Bila sebuah key belum pernah
     * diatur (NULL di settings.db), nilai dari config.php dipakai sebagai
     * cadangan — lihat Database::pgsql().
     */
    public const DB_FIELDS = [
        'db_host' => 'Host',
        'db_port' => 'Port',
        'db_name' => 'Nama Database',
        'db_user' => 'User',
        'db_pass' => 'Password',
    ];

    /**
     * @return array<string,?string> Nilai koneksi database yang TERSIMPAN di
     *   settings.db saja (tanpa fallback ke config.php). Key yang belum
     *   pernah diatur bernilai null.
     */
    public static function getDbOverrides(): array
    {
        $res = [];
        foreach (array_keys(self::DB_FIELDS) as $key) {
            $res[$key] = self::get($key, null);
        }
        return $res;
    }

    /**
     * Simpan pengaturan koneksi database. Field yang nilainya string kosong
     * ('') TIDAK ditimpa (khusus dipakai untuk db_pass agar admin bisa
     * mengosongkan form password tanpa menghapus password yang tersimpan).
     *
     * @param array<string,string> $values key => value, key harus salah satu dari DB_FIELDS.
     * @param string[] $skipIfEmpty daftar key yang dilewati bila value-nya kosong.
     */
    public static function setDbOverrides(array $values, array $skipIfEmpty = ['db_pass']): void
    {
        foreach ($values as $key => $value) {
            if (!array_key_exists($key, self::DB_FIELDS)) {
                continue;
            }
            if ($value === '' && in_array($key, $skipIfEmpty, true)) {
                continue;
            }
            self::set($key, $value);
        }
    }

    /**
     * Validator per key untuk restore backup JSON (lihat importBackupSettings()).
     * Key yang tidak terdaftar di sini tidak akan pernah ditulis lewat restore,
     * meski ada di file backup — mencegah key sembarangan/tidak dikenal masuk
     * ke settings.db lewat file yang diunggah admin.
     *
     * @return array<string, callable(string): bool>
     */
    private static function restoreValidators(): array
    {
        return [
            'kantor_filter'              => static fn(string $v): bool => true,
            'pelanggan_filter'           => static fn(string $v): bool => true,
            'item_prefix_filter'         => static fn(string $v): bool => true,
            'tax_rate'                   => static fn(string $v): bool => is_numeric($v) && (float) $v >= 0,
            'tax_source'                 => static fn(string $v): bool => in_array($v, self::TAX_SOURCES, true),
            'database_only_skip_zero_tax' => static fn(string $v): bool => in_array($v, ['0', '1'], true),
            'csv_locale'                 => static fn(string $v): bool => CsvLocale::isValid($v),
            'dashboard_visible_fields'   => static fn(string $v): bool => true,
            'db_host'                    => static fn(string $v): bool => true,
            'db_port'                    => static fn(string $v): bool => $v === '' || ctype_digit($v),
            'db_name'                    => static fn(string $v): bool => true,
            'db_user'                    => static fn(string $v): bool => true,
            'db_pass'                    => static fn(string $v): bool => true,
        ];
    }

    /**
     * Validasi & terapkan isi `settings` dari file backup JSON ke settings.db.
     * Dipakai oleh admin_settings.php saat admin mengunggah file restore.
     *
     * Hanya key yang: (1) dikenal (ada di restoreValidators()), DAN
     * (2) nilainya scalar dan lolos validator tipe/format key tsb,
     * yang benar-benar ditulis. Key lain (tidak dikenal, tipe salah, atau
     * format tidak valid) diabaikan dan dilaporkan lewat 'skipped', bukan
     * menimpa settings.db dengan data yang berpotensi merusak/tidak sah.
     *
     * @param array $settings data mentah dari $data['settings'] hasil json_decode file backup.
     * @return array{restored:int, skipped:string[]}
     */
    public static function importBackupSettings(array $settings): array
    {
        $validators = self::restoreValidators();
        $restored = 0;
        $skipped = [];

        foreach ($settings as $key => $value) {
            if (!is_string($key) || $key === '' || !is_scalar($value)) {
                $skipped[] = is_string($key) && $key !== '' ? $key : '(key tidak valid)';
                continue;
            }
            if (!isset($validators[$key])) {
                $skipped[] = $key;
                continue;
            }
            $strValue = (string) $value;
            if (!$validators[$key]($strValue)) {
                $skipped[] = $key;
                continue;
            }
            self::set($key, $strValue);
            $restored++;
        }

        return ['restored' => $restored, 'skipped' => $skipped];
    }
}

