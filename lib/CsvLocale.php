<?php
declare(strict_types=1);

/**
 * Profil format locale untuk export CSV.
 *
 * Beberapa aplikasi spreadsheet (mis. Excel versi Indonesia) mengharapkan
 * pemisah desimal koma (,) dan delimiter kolom titik-koma (;), sementara
 * aplikasi/locale internasional (Excel US, tools non-Excel, sistem lain)
 * lazimnya memakai titik (.) sebagai desimal dan koma (,) sebagai delimiter.
 * Kelas ini mengumpulkan aturan tersebut supaya SalesReport tinggal pakai.
 */
class CsvLocale
{
    private const PROFILES = [
        'id' => [
            'label'          => 'Indonesia (desimal koma, pemisah ;)',
            'delimiter'      => ';',
            'decimal_sep'    => ',',
            'thousand_sep'   => '.',
            'date_format'    => 'd/m/Y H:i:s',
        ],
        'en' => [
            'label'          => 'Internasional (desimal titik, pemisah ,)',
            'delimiter'      => ',',
            'decimal_sep'    => '.',
            'thousand_sep'   => '',
            'date_format'    => 'Y-m-d H:i:s',
        ],
    ];

    public static function isValid(?string $code): bool
    {
        return $code !== null && isset(self::PROFILES[$code]);
    }

    /** @return array{label:string, delimiter:string, decimal_sep:string, thousand_sep:string, date_format:string} */
    public static function profile(string $code): array
    {
        return self::PROFILES[$code] ?? self::PROFILES['id'];
    }

    /** @return array<string,string> daftar kode => label, untuk dropdown pilihan. */
    public static function options(): array
    {
        $out = [];
        foreach (self::PROFILES as $code => $p) {
            $out[$code] = $p['label'];
        }
        return $out;
    }

    /** Format angka sesuai profil locale. */
    public static function number(array $profile, float $value, int $decimals): string
    {
        return number_format($value, $decimals, $profile['decimal_sep'], $profile['thousand_sep']);
    }

    /** Format tanggal (string dari DB, mis. "2026-07-01 08:00:44.864257") sesuai profil locale. */
    public static function date(array $profile, string $rawDate): string
    {
        try {
            $dt = new DateTime($rawDate);
            return $dt->format($profile['date_format']);
        } catch (Throwable $e) {
            return $rawDate; // fallback: tampilkan apa adanya jika parsing gagal
        }
    }
}
