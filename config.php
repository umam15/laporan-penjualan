<?php
/**
 * Konfigurasi aplikasi.
 * Simpan file ini di luar webroot bila memungkinkan, atau pastikan server
 * dikonfigurasi agar file .php tidak pernah ditampilkan sebagai teks mentah.
 *
 * Sejak v1.1.1, kredensial koneksi PostgreSQL TIDAK LAGI disimpan di sini.
 * Atur Host/Port/Nama Database/User/Password lewat menu admin
 * "Pengaturan" setelah login — nilainya tersimpan di data/settings.db,
 * bukan di file ini. Ini mencegah kredensial produksi ikut ter-commit
 * atau tersebar bersama source code.
 */
return [
    // Lokasi file database SQLite untuk user & pengaturan aplikasi
    'sqlite_path' => __DIR__ . '/data/settings.db',
];
