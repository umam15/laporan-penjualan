# Changelog

Semua perubahan penting pada proyek ini didokumentasikan di file ini.

Format mengacu pada [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
dan proyek ini mengikuti [Semantic Versioning](https://semver.org/lang/id/).

## [Unreleased]

### Added
- (isi di sini perubahan yang belum dirilis)

## [1.2.1]

### Added
- Menambahkan `CHANGELOG.md` untuk mendokumentasikan riwayat perubahan proyek.
- Menampilkan versi aplikasi (mis. `v1.2.1`) di navbar, diambil dari
  konstanta `APP_VERSION` pada `bootstrap.php`.

## [1.1.1]

### Changed
- Kredensial koneksi PostgreSQL (host, port, nama database, user, password)
  tidak lagi disimpan di `config.php`. Semua diatur lewat menu admin
  **Pengaturan** dan tersimpan di `data/settings.db`, sehingga kredensial
  produksi tidak pernah ikut ter-*commit* atau tersebar bersama source code.

## [1.0.0]

Rilis awal aplikasi.

### Added
- Unduh **Laporan Penjualan** dalam format CSV dari POS iPos5, diambil
  langsung dari database transaksi PostgreSQL.
- Autentikasi staf berbasis session, dengan hash password `bcrypt`.
- Setup awal otomatis: form "Buat Akun Admin Pertama" saat tabel `users`
  masih kosong.
- Menu admin **Pengaturan**: filter data, tarif pajak, format CSV default,
  dan koneksi database PostgreSQL (dengan uji koneksi sebelum disimpan).
- Menu admin **User**: kelola akun staf.
- Halaman **Profil**: staf bisa ganti password sendiri.
- Dashboard staf: filter rentang tanggal (default awal bulan s.d. hari ini)
  dan pilihan format CSV (locale) per unduhan.
- Dua format locale CSV: `id` (Indonesia, delimiter `;`) dan `en`
  (internasional, delimiter `,`), keduanya dengan BOM UTF-8.
- Tiga sumber tarif pajak: manual, otomatis dari data transaksi dengan
  cadangan manual, atau otomatis tanpa cadangan.
- Kolom laporan lengkap: No Transaksi, Tanggal, Dept, Kode Pelanggan, Jumlah
  Item, Kode Item, Nama Item, Pokok, Total, Pajak Keluaran, Laba Kotor,
  Proc Laba (%), dan GPM.
- Backup & restore pengaturan (termasuk kredensial database) sebagai file
  JSON lewat menu Pengaturan.
- Proteksi `data/settings.db` dan `lib/*.php` lewat `.htaccess`.
- Dukungan Docker (`Dockerfile` + `docker-compose.yml`, PHP 8.2 + Apache).

[Unreleased]: #
[1.2.1]: #
[1.1.1]: #
[1.0.0]: #
