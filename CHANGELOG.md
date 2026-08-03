# Changelog

Semua perubahan penting pada proyek ini didokumentasikan di file ini.

Format mengacu pada [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
dan proyek ini mengikuti [Semantic Versioning](https://semver.org/lang/id/).

## [1.3.0]

### Added
- Tombol **Pratinjau** di Dashboard, di samping tombol "Unduh CSV". Memanggil
  endpoint JSON baru `preview.php` dan menampilkan hasilnya langsung di
  halaman (tanpa reload) sebelum staf memutuskan mengunduh:
  - Tabel hingga **50 baris pertama** dengan kolom yang sama seperti CSV,
    diformat sesuai locale (`id`/`en`) yang dipilih.
  - Ringkasan total (jumlah baris, Pokok, Total, Pajak Keluaran, Laba Kotor)
    dihitung dari **seluruh** baris yang cocok dengan filter tanggal &
    pengaturan admin — bukan hanya baris yang ditampilkan di tabel.
  - Keterangan jumlah baris ditampilkan vs. total, dan pesan bila tidak ada
    data pada rentang yang dipilih.
  - Memakai logika perhitungan (`SalesReport::rows()`) dan pengaturan filter
    yang identik dengan `export.php`, sehingga angka pratinjau selalu
    konsisten dengan file CSV yang akan diunduh.
- `SalesReport::preview()` di `lib/SalesReport.php`.

## [1.2.5]

### Changed
- Default **Tarif Pajak Keluaran Manual** diubah dari `10` (10%) menjadi
  `0` (0%) — berlaku untuk instalasi baru (nilai awal yang dibuat saat
  `settings.db` pertama kali dibuat) serta placeholder di form Pengaturan.
  **Tidak** mengubah nilai pada instalasi yang sudah berjalan; admin yang
  ingin memakai tarif tertentu tetap perlu mengisinya manual lewat menu
  Pengaturan.

## [1.2.4]

### Fixed
- Menyamakan minimal panjang password menjadi **8 karakter** di semua alur
  (sebelumnya `login.php`/README mensyaratkan 8 karakter untuk admin
  pertama, tapi `admin_users.php` — buat user baru & reset password admin
  — dan `profile.php` — ganti password sendiri — hanya mensyaratkan 6).
  Validasi server-side (`strlen`) dan atribut `minlength` pada form
  keduanya diperbarui.

## [1.2.3]

### Added
- Dokumentasi strategi backup rutin `data/settings.db` di `README.md`
  (bagian "Backup Pengaturan & Data"): menjelaskan perbedaan backup JSON
  ad hoc (menu Pengaturan, hanya pengaturan) vs backup file terjadwal
  (seluruh `settings.db`, termasuk akun user), lengkap dengan contoh
  skrip cron memakai `sqlite3 .backup` untuk instalasi Docker maupun
  manual, rekomendasi retensi, dan langkah restore.

## [1.2.2]

### Added
- Dashboard (`index.php`) kini melakukan tes koneksi ringan ke database
  transaksi (PostgreSQL) sebelum halaman dirender. Bila koneksi gagal,
  staf melihat pesan peringatan yang ramah dan form unduh dinonaktifkan
  (sebelumnya kegagalan baru terlihat setelah klik "Unduh CSV" lewat pesan
  generik dari `export.php`). Admin melihat detail teknis error dan tautan
  langsung ke menu **Pengaturan Database**; error asli tetap dicatat ke
  `error_log`.
- Varian `.alert.warning` pada `assets/style.css` untuk banner peringatan
  di atas.
- `TODO.md` berisi daftar tugas pengembangan (keamanan, kualitas kode,
  fitur, deployment/ops) hasil review kode proyek.
- Validasi ketat pada **restore backup JSON** pengaturan
  (`admin_settings.php` + `Settings::importBackupSettings()`): cek
  ekstensi `.json`, batas ukuran file (256 KB), validitas JSON, identitas
  backup (`app`/`type`/`version`), lalu tiap key pengaturan divalidasi
  lewat whitelist tipe/format sebelum ditulis ke `settings.db`. Key yang
  tidak dikenal atau tidak valid diabaikan dan dilaporkan ke admin,
  bukan langsung ditimpa seperti sebelumnya.

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
[1.2.2]: #
[1.2.1]: #
[1.1.1]: #
[1.0.0]: #
