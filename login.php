<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

if (Auth::check()) {
    header('Location: index.php');
    exit;
}

// Belum ada user sama sekali (mis. deploy baru / settings.db baru) -> tampilkan
// form untuk membuat admin pertama di halaman ini juga, tanpa perlu install.php.
$isSetup = Auth::userCount() === 0;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isSetup) {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm  = (string) ($_POST['confirm'] ?? '');

        if ($username === '' || !preg_match('/^[A-Za-z0-9_.\-]{3,50}$/', $username)) {
            $error = 'Username wajib diisi (3-50 karakter, huruf/angka/underscore/titik/strip).';
        } elseif (strlen($password) < 8) {
            $error = 'Password minimal 8 karakter.';
        } elseif ($password !== $confirm) {
            $error = 'Konfirmasi password tidak cocok.';
        } else {
            try {
                Auth::createUser($username, $password, 'admin');
                Auth::attempt($username, $password);
                header('Location: index.php');
                exit;
            } catch (Throwable $e) {
                $error = 'Gagal membuat akun (kemungkinan username sudah dipakai).';
            }
        }
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if (Auth::attempt($username, $password)) {
            header('Location: index.php');
            exit;
        }
        $error = 'Username atau password salah.';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $isSetup ? 'Buat Akun Admin' : 'Login' ?> - Laporan Penjualan</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<div class="login-box">
    <h1>Laporan Penjualan</h1>
    <p class="subtitle">Untuk Keperluan Pajak</p>
    <h2><?= $isSetup ? 'Buat Akun Admin Pertama' : 'Login Staf' ?></h2>
    <?php if ($isSetup): ?>
        <p class="hint">Belum ada user sama sekali. Buat akun admin pertama untuk mulai memakai aplikasi
        (termasuk mengatur koneksi database lewat menu Pengaturan).</p>
    <?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
        <label>Username</label>
        <input type="text" name="username" required autofocus minlength="<?= $isSetup ? 3 : 1 ?>" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        <label>Password</label>
        <input type="password" name="password" required minlength="<?= $isSetup ? 8 : 1 ?>">
        <?php if ($isSetup): ?>
        <label>Konfirmasi Password</label>
        <input type="password" name="confirm" required minlength="8">
        <?php endif; ?>
        <button type="submit"><?= $isSetup ? 'Buat Admin & Masuk' : 'Masuk' ?></button>
    </form>
</div>
</body>
</html>
