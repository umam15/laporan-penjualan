<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current  = (string) ($_POST['current_password'] ?? '');
    $new      = (string) ($_POST['new_password'] ?? '');
    $confirm  = (string) ($_POST['confirm_password'] ?? '');

    $stmt = Database::sqlite()->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([Auth::user()['id']]);
    $u = $stmt->fetch();

    if (!$u || !password_verify($current, $u['password_hash'])) {
        $error = 'Password saat ini salah.';
    } elseif (strlen($new) < 8) {
        $error = 'Password baru minimal 8 karakter.';
    } elseif ($new !== $confirm) {
        $error = 'Konfirmasi password tidak sama.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        Database::sqlite()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $u['id']]);
        $message = 'Password berhasil diubah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Profil Saya - Laporan Penjualan</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php include __DIR__ . '/partials/nav.php'; ?>
<div class="container">
    <h1>Ganti Password</h1>
    <?php if ($message): ?><div class="alert success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post" class="card">
        <label>Password Saat Ini</label>
        <input type="password" name="current_password" required>
        <label>Password Baru</label>
        <input type="password" name="new_password" required minlength="8">
        <label>Konfirmasi Password Baru</label>
        <input type="password" name="confirm_password" required minlength="8">
        <button type="submit">Simpan</button>
    </form>
</div>
</body>
</html>
