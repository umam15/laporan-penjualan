<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

// Logout hanya lewat POST (token CSRF sudah diperiksa di bootstrap), agar tidak
// bisa dipicu dari tautan/gambar di situs lain.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

Auth::logout();
header('Location: login.php');
exit;
