<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
Auth::requireAdmin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $role = (($_POST['role'] ?? 'staff') === 'admin') ? 'admin' : 'staff';

        if ($username === '' || strlen($password) < 8) {
            $error = 'Username wajib diisi dan password minimal 8 karakter.';
        } else {
            try {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                Database::sqlite()
                    ->prepare('INSERT INTO users (username, password_hash, role) VALUES (?, ?, ?)')
                    ->execute([$username, $hash, $role]);
                $message = 'User berhasil ditambahkan.';
            } catch (Throwable $e) {
                $error = 'Gagal menambahkan user (kemungkinan username sudah dipakai).';
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        Database::sqlite()->prepare('UPDATE users SET active = 1 - active WHERE id = ?')->execute([$id]);
        $message = 'Status user diperbarui.';
    } elseif ($action === 'reset_password') {
        $id = (int) ($_POST['id'] ?? 0);
        $newPassword = (string) ($_POST['new_password'] ?? '');
        if (strlen($newPassword) < 8) {
            $error = 'Password baru minimal 8 karakter.';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            Database::sqlite()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
            $message = 'Password berhasil direset.';
        }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($id === (int) Auth::user()['id']) {
            $error = 'Tidak bisa menghapus akun yang sedang digunakan.';
        } else {
            Database::sqlite()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            $message = 'User berhasil dihapus.';
        }
    }
}

$users = Database::sqlite()->query('SELECT id, username, role, active, created_at FROM users ORDER BY id')->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manajemen User - Laporan Penjualan</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include __DIR__ . '/partials/nav.php'; ?>
<div class="container">
    <h1>Manajemen User Staf</h1>
    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <div class="card">
        <h3>Tambah User Baru</h3>
        <form method="post">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
                <div>
                    <label>Username</label>
                    <input type="text" name="username" required>
                </div>
                <div>
                    <label>Password</label>
                    <input type="password" name="password" required minlength="8">
                </div>
                <div>
                    <label>Role</label>
                    <select name="role">
                        <option value="staff">Staf</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <button type="submit">Tambah User</button>
        </form>
    </div>

    <div class="card">
        <h3>Daftar User</h3>
        <div class="table-responsive">
        <table>
            <tr><th>Username</th><th>Role</th><th>Status</th><th>Dibuat</th><th>Aksi</th></tr>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td><?= htmlspecialchars($u['role']) ?></td>
                <td><?= $u['active'] ? 'Aktif' : 'Nonaktif' ?></td>
                <td><?= htmlspecialchars($u['created_at']) ?></td>
                <td class="actions">
                    <form method="post" class="inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <button type="submit"><?= $u['active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                    </form>
                    <form method="post" class="inline">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <input type="password" name="new_password" placeholder="Password baru" minlength="8" required style="width:130px">
                        <button type="submit">Reset</button>
                    </form>
                    <form method="post" class="inline" onsubmit="return confirm('Hapus user ini?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                        <button type="submit" class="danger">Hapus</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        </div>
    </div>
</div>
</body>
</html>
