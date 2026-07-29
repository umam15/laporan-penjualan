# Laporan Penjualan

Aplikasi web PHP sederhana (tanpa framework) untuk mengunduh **Laporan Penjualan**
dalam format CSV dari aplikasi POS iPos5, mengambil data langsung dari database
transaksi PostgreSQL, dengan autentikasi staf dan pengaturan yang bisa diubah admin.

## Kebutuhan

- Docker & Docker Compose, **atau**
- PHP 8.0+ (ekstensi `pdo_pgsql`, `pdo_sqlite`) + web server, dengan folder `data/`
  writable oleh web server

## Struktur

```
config.php          # lokasi settings.db (tidak berisi kredensial database)
bootstrap.php        # inisialisasi session, autoload, koneksi DB
login.php / logout.php
index.php            # dashboard: filter tanggal + tombol unduh
export.php           # endpoint streaming CSV
admin_settings.php   # admin: filter data, tarif pajak, koneksi database
admin_users.php       # admin: kelola user staf
profile.php           # user: ganti password sendiri
lib/                  # Database, Auth, CsvLocale, Settings, SalesReport
partials/, assets/
data/                 # tempat settings.db dibuat otomatis (dilindungi .htaccess)
```

## Instalasi (Docker)

1. `docker compose up -d --build`
2. Buka `http://localhost:8080/login.php`. Karena `data/settings.db` belum ada,
   tabel & pengaturan default dibuat otomatis saat halaman pertama diakses.
3. Karena belum ada user, `login.php` menampilkan form **"Buat Akun Admin
   Pertama"**. Isi username & password (min. 8 karakter), lalu Anda otomatis
   login sebagai admin.
4. Sebagai admin, buka menu **Pengaturan** untuk mengisi filter, tarif pajak,
   format CSV default, dan **kredensial koneksi PostgreSQL** (wajib, lihat
   bagian di bawah). Buka menu **User** untuk menambah staf lain.
5. Data (`settings.db`) tersimpan di volume `app_data` sehingga tetap ada
   walau container dihapus/dibangun ulang. Ganti port di `docker-compose.yml`
   bila `8080` sudah dipakai.

Catatan: form setup admin hanya muncul selama tabel `users` masih kosong, dan
akan muncul lagi otomatis kalau `settings.db` dihapus.

## Instalasi (Manual, tanpa Docker)

1. Upload seluruh folder ke web server, arahkan document root ke folder ini.
2. `chmod 775 data`
3. Ikuti langkah 2–4 pada bagian Instalasi (Docker) di atas.

## Pengaturan Database

Sejak v1.1.1, `config.php` **tidak menyimpan kredensial PostgreSQL**. Semua
koneksi ke database transaksi diatur admin lewat menu **Pengaturan**, tersimpan
di `data/settings.db` (bukan di source code):

- Sebelum disimpan, aplikasi menguji koneksi dengan nilai baru; bila gagal,
  pengaturan lama tetap dipakai.
- Field Password selalu kosong saat form dibuka — kosongkan bila tidak ingin
  mengubah password yang sudah tersimpan.
- Sampai diisi lewat Pengaturan, koneksi database belum berfungsi (disengaja,
  agar kredensial produksi tidak pernah ikut ke source code).

## Cara Pakai (Staf)

1. Login.
2. Di Dashboard, pilih rentang tanggal (default: awal bulan berjalan s.d. hari
   ini) dan format CSV (locale) bila ingin berbeda dari default admin.
3. Klik **Unduh CSV**.

## Format Locale CSV

| Locale | Delimiter | Desimal | Ribuan | Format tanggal |
|---|---|---|---|---|
| `id` — Indonesia (default) | `;` | `,` | `.` | `d/m/Y H:i:s` |
| `en` — Internasional | `,` | `.` | (tanpa pemisah) | `Y-m-d H:i:s` |

Gunakan `id` untuk Excel berlocale Indonesia, `en` untuk sistem/tools
internasional. Keduanya memakai BOM UTF-8 di awal file.

## Kolom Laporan

| Kolom | Keterangan |
|---|---|
| No Transaksi | `tbl_ikhd.notransaksi` |
| Tanggal | `tbl_ikhd.tanggal` |
| Dept | Nama kantor (`tbl_kantor.namakantor`) dari `tbl_ikhd.kodekantor` |
| Kode Pelanggan | `tbl_ikhd.kodesupel` |
| Jumlah Item | Kuantitas baris, bersih setelah dikurangi retur |
| Kode Item | `tbl_ikdt.kodeitem` |
| Nama Item | `tbl_item.namaitem` |
| Pokok | HPP baris (net retur); dari `tbl_ikdt.hppdasar`, fallback rata-rata biaya lapisan `tbl_item_ik` |
| Total | Nilai jual baris (net retur), `tbl_ikdt.total` |
| Pajak Keluaran | `Total x tarif pajak` (lihat Sumber Tarif Pajak) |
| Laba Kotor | `Total - Pokok` |
| Proc Laba (%) | `Laba Kotor / Pokok x 100` |
| GPM | `Laba Kotor / Total x 100` |

## Sumber Tarif Pajak

Diatur admin di menu **Pengaturan**, tiga opsi:

- **Manual** (default) — satu tarif tetap untuk semua baris.
- **Otomatis dari data transaksi (dengan cadangan manual)** — ambil
  `tbl_ikdt.pajak`, fallback ke `tbl_ikhd.prpajak`, lalu ke tarif manual bila
  keduanya kosong/0.
- **Hanya dari data transaksi (tanpa cadangan)** — urutan sama tapi tidak
  pernah jatuh ke tarif manual; hasil 0 bila baris & header kosong/0.

## Asumsi & Logika Perhitungan

Karena skema database tidak punya dokumentasi bisnis eksplisit, aplikasi ini
mengambil asumsi berikut (sesuaikan `lib/SalesReport.php` bila berbeda):

1. Transaksi yang dihitung: tipe `JL`, `KSR`, `TRBCIMPJL`, `TRBCIMPKSR` pada
   `tbl_ikhd`. Retur tidak diambil sebagai baris terpisah karena sudah
   terekam di `tbl_ikdt.jmlretur` dan dikurangkan di baris asal.
2. Kuantitas dasar = `jumlah * jmlkonversi`; bersih = `base_qty - jmlretur`.
   Proporsi ini juga dipakai untuk memproporsikan `Total`.
3. **Pokok** = kuantitas bersih × biaya per unit, diutamakan dari
   `tbl_ikdt.hppdasar`; jika kosong/0 (skema pakai `hpp_ik`/`hpp_ik_v3`),
   dihitung dari `SUM(jumlahdasar × hargadasar) / SUM(jumlahdasar)` pada
   `tbl_item_ik`. Aplikasi ini murni membaca nilai HPP yang sudah dihitung
   database, tidak menghitung ulang dari nol.
4. Baris yang seluruh kuantitasnya sudah diretur (`jmlretur >= base_qty`)
   dilewati.
5. **Proc Laba (%)** = markup terhadap Pokok, **GPM** = margin terhadap Total
   — disamakan dengan laporan acuan `xReport.csv` (Analisa Laba Jual Detail).

## Backup & Restore Pengaturan

Di menu **Pengaturan**, admin bisa mengunduh seluruh isi `settings.db` (filter,
tarif pajak, format CSV, field Dashboard, termasuk kredensial database) sebagai
satu file JSON, dan memulihkannya kembali lewat form upload di kartu yang sama.
Restore menimpa nilai yang sedang aktif. File backup **tidak** berisi akun/
password user staf — simpan file ini di tempat aman karena memuat kredensial
database.

## Keamanan

- Password di-hash `password_hash()` (bcrypt); auth berbasis session.
- Semua query PostgreSQL memakai prepared statement (aman dari SQL injection).
- `data/settings.db` diproteksi `.htaccess` (`Require all denied`). Untuk
  Nginx, tambahkan aturan setara:
  ```
  location ~* /data/.*\.(db)$ { deny all; }
  location ~* /lib/.*\.php$ { deny all; }
  ```
- Koneksi ke database PostgreSQL bersifat read-only dari sisi aplikasi.
