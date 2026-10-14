<?php
declare(strict_types=1);

/**
 * Perlindungan CSRF terpusat berbasis token sesi.
 *
 * - Semua permintaan POST diverifikasi otomatis oleh bootstrap.php (Csrf::guard()).
 * - Setiap <form method="post"> wajib menyertakan <?= Csrf::field() ?>.
 */
class Csrf
{
    private const KEY = 'csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::KEY]) || !is_string($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    /** Input hidden siap pakai untuk form POST. */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::token(), ENT_QUOTES) . '">';
    }

    /** Tolak (403) setiap POST tanpa token sesi yang cocok. */
    public static function guard(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            return;
        }
        $sent = $_POST[self::KEY] ?? '';
        $known = $_SESSION[self::KEY] ?? '';
        if (!is_string($sent) || !is_string($known) || $known === '' || !hash_equals($known, $sent)) {
            http_response_code(403);
            die('Permintaan ditolak (token keamanan tidak valid). Muat ulang halaman lalu coba lagi.');
        }
    }
}
