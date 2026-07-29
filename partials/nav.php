<?php
/** @var array|null $user */
$user = Auth::user();
$currentPage = basename($_SERVER['SCRIPT_NAME']);
?>
<nav class="navbar">
    <div class="brand">📊 Laporan Penjualan</div>
    <div class="links">
        <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">Dashboard</a>
        <?php if (Auth::isAdmin()): ?>
            <a href="admin_settings.php" class="<?= $currentPage === 'admin_settings.php' ? 'active' : '' ?>">Pengaturan</a>
            <a href="admin_users.php" class="<?= $currentPage === 'admin_users.php' ? 'active' : '' ?>">User</a>
        <?php endif; ?>
        <a href="profile.php" class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>">Profil</a>
        <span class="user">👤 <?= htmlspecialchars($user['username'] ?? '') ?> (<?= htmlspecialchars($user['role'] ?? '') ?>)</span>
        <a href="logout.php" class="logout">Keluar</a>
        <span class="app-version">v<?= htmlspecialchars(APP_VERSION) ?></span>
    </div>
</nav>
