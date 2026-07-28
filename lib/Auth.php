<?php
declare(strict_types=1);

/**
 * Autentikasi staf berbasis session, dengan data user disimpan di settings.db (SQLite).
 */
class Auth
{
    /** Jumlah user terdaftar (peran apa pun). Dipakai login.php untuk cek setup awal. */
    public static function userCount(): int
    {
        return (int) Database::sqlite()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    }

    /** Buat user baru. $role harus 'admin' atau 'staff'. */
    public static function createUser(string $username, string $password, string $role = 'staff'): void
    {
        if (!in_array($role, ['admin', 'staff'], true)) {
            $role = 'staff';
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        Database::sqlite()
            ->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)')
            ->execute([$username, $hash, $role]);
    }

    public static function attempt(string $username, string $password): bool
    {
        $stmt = Database::sqlite()->prepare(
            'SELECT * FROM users WHERE username = :u AND active = 1'
        );
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            unset($user['password_hash']);
            $_SESSION['user'] = $user;
            session_regenerate_id(true);
            return true;
        }
        return false;
    }

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    public static function isAdmin(): bool
    {
        return self::check() && (($_SESSION['user']['role'] ?? '') === 'admin');
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: login.php');
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();
        if (!self::isAdmin()) {
            http_response_code(403);
            die('Akses ditolak. Halaman ini hanya untuk admin.');
        }
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
