<?php
declare(strict_types=1);

/**
 * Autentikasi staf berbasis session, dengan data user disimpan di settings.db (SQLite).
 *
 * Sesi hanya menyimpan id user + penanda; peran dan status aktif dibaca ulang
 * dari database pada setiap permintaan (lihat user()), sehingga user yang
 * dinonaktifkan/dihapus atau yang password-nya direset langsung kehilangan akses.
 */
class Auth
{
    /** Sesi berakhir bila tidak ada aktivitas selama ini (detik). */
    public const IDLE_TIMEOUT = 3600;
    /** Sesi berakhir paling lama sekian detik sejak login, seaktif apa pun. */
    public const ABSOLUTE_TIMEOUT = 43200;

    /** Batas gagal login per username / per IP dalam jendela FAIL_WINDOW. */
    private const MAX_FAIL_USER = 5;
    private const MAX_FAIL_IP   = 20;
    private const FAIL_WINDOW   = 900; // detik (15 menit)

    private static bool $resolved = false;
    private static ?array $current = null;

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

    /**
     * Ganti password user dan cabut semua sesi miliknya (session_version naik).
     * Bila yang diganti adalah akun yang sedang login, sesi ini tetap hidup.
     */
    public static function setPassword(int $id, string $newPassword): void
    {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $pdo = Database::sqlite();
        $pdo->prepare('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?')
            ->execute([$hash, $id]);

        if ((int) ($_SESSION['uid'] ?? 0) === $id) {
            $stmt = $pdo->prepare('SELECT session_version FROM users WHERE id = ?');
            $stmt->execute([$id]);
            $_SESSION['sv'] = (int) $stmt->fetchColumn();
            session_regenerate_id(true);
        }
    }

    /** Jumlah admin aktif selain user $excludeId (untuk menjaga admin terakhir). */
    public static function activeAdminCountExcluding(int $excludeId): int
    {
        $stmt = Database::sqlite()->prepare(
            "SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1 AND id <> ?"
        );
        $stmt->execute([$excludeId]);
        return (int) $stmt->fetchColumn();
    }

    /** True bila percobaan login dari username/IP ini sudah melewati batas. */
    public static function isThrottled(string $username): bool
    {
        $pdo   = Database::sqlite();
        $since = time() - self::FAIL_WINDOW;
        $pdo->prepare('DELETE FROM login_attempts WHERE attempted_at < ?')->execute([$since]);

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE username = ? AND attempted_at >= ?');
        $stmt->execute([self::attemptKey($username), $since]);
        if ((int) $stmt->fetchColumn() >= self::MAX_FAIL_USER) {
            return true;
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at >= ?');
        $stmt->execute([self::clientIp(), $since]);
        return (int) $stmt->fetchColumn() >= self::MAX_FAIL_IP;
    }

    public static function attempt(string $username, string $password): bool
    {
        $stmt = Database::sqlite()->prepare(
            'SELECT id, username, password_hash, session_version FROM users WHERE username = :u AND active = 1'
        );
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            unset($_SESSION['csrf_token']);
            $now = time();
            $_SESSION['uid']       = (int) $user['id'];
            $_SESSION['sv']        = (int) $user['session_version'];
            $_SESSION['login_at']  = $now;
            $_SESSION['last_seen'] = $now;
            self::$resolved = false;

            Database::sqlite()
                ->prepare('DELETE FROM login_attempts WHERE username = ?')
                ->execute([self::attemptKey($username)]);
            return true;
        }

        Database::sqlite()
            ->prepare('INSERT INTO login_attempts (username, ip, attempted_at) VALUES (?, ?, ?)')
            ->execute([self::attemptKey($username), self::clientIp(), time()]);
        return false;
    }

    /**
     * User yang sedang login (id, username, role, active) atau null.
     * Divalidasi ulang ke database sekali per permintaan: akun harus masih ada
     * dan aktif, versi sesi harus cocok, dan batas idle/mutlak belum terlewati.
     */
    public static function user(): ?array
    {
        if (self::$resolved) {
            return self::$current;
        }
        self::$resolved = true;
        self::$current  = null;

        $uid = $_SESSION['uid'] ?? null;
        if (!is_int($uid)) {
            return null;
        }

        $now = time();
        if ($now - (int) ($_SESSION['login_at'] ?? 0) > self::ABSOLUTE_TIMEOUT
            || $now - (int) ($_SESSION['last_seen'] ?? 0) > self::IDLE_TIMEOUT
        ) {
            self::forget();
            return null;
        }

        $stmt = Database::sqlite()->prepare(
            'SELECT id, username, role, active, session_version FROM users WHERE id = ?'
        );
        $stmt->execute([$uid]);
        $row = $stmt->fetch();

        if (!$row || (int) $row['active'] !== 1 || (int) $row['session_version'] !== (int) ($_SESSION['sv'] ?? -1)) {
            self::forget();
            return null;
        }

        $_SESSION['last_seen'] = $now;
        unset($row['session_version']);
        return self::$current = $row;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user !== null && $user['role'] === 'admin';
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
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        self::$resolved = true;
        self::$current  = null;
    }

    /**
     * Lepas status login dari sesi (kedaluwarsa/dicabut) tetapi biarkan sesi
     * tetap aktif dengan ID baru, agar token CSRF form login tetap bisa disimpan.
     */
    private static function forget(): void
    {
        unset($_SESSION['uid'], $_SESSION['sv'], $_SESSION['login_at'], $_SESSION['last_seen'], $_SESSION['csrf_token']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    private static function attemptKey(string $username): string
    {
        return mb_strtolower(mb_substr($username, 0, 100));
    }

    private static function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }
}
