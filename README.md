# Laporan Penjualan

Aplikasi web PHP sederhana (tanpa framework) untuk mengunduh **Laporan Penjualan**
dalam format CSV dari aplikasi POS iPos5, mengambil data langsung dari database transaksi PostgreSQL,
dengan autentikasi staf dan pengaturan yang bisa diubah admin.

## Kebutuhan Server

- PHP 8.0+ dengan ekstensi: `pdo_pgsql`, `pdo_sqlite`
- Web server (Apache/Nginx) yang bisa menjalankan PHP
- Folder `data/` harus **writable** oleh web server (untuk `settings.db`)

## Struktur

```
config.php          # lokasi settings.db (TIDAK berisi kredensial database lagi sejak v1.1.1)
bootstrap.php       # inisialisasi session, autoload, koneksi DB
login.php / logout.php  # login juga menangani setup akun admin pertama (lihat Instalasi)
index.php           # dashboard: filter tanggal + tombol unduh
export.php          # endpoint streaming CSV
admin_settings.php  # admin: filter kantor/pelanggan/kode item, tarif pajak, koneksi database
admin_users.php     # admin: kelola user staf
profile.php         # user: ganti password sendiri
lib/
  Database.php     # koneksi PDO ke PostgreSQL & SQLite (skema settings.db dibuat otomatis)
  Auth.php         # login/session + setup admin pertama
  CsvLocale.php    # profil format locale CSV (delimiter, desimal, tanggal)
  Settings.php     # baca/tulis pengaturan di settings.db
  SalesReport.php  # query & perhitungan laporan, output CSV
partials/nav.php
assets/style.css
data/               # tempat settings.db dibuat otomatis (dilindungi .htaccess)
```

## Instalasi

1. Upload seluruh folder ke web server.
2. Pastikan folder `data/` writable: `chmod 775 data`
3. Buka `https://domain-anda/login.php` di browser. Karena `data/settings.db`
   belum ada, aplikasi otomatis membuat tabel `users` & `settings` beserta
   nilai pengaturan default (tarif pajak 0.5%, semua filter kosong = tanpa
   batasan) saat halaman ini pertama kali diakses — tidak perlu menjalankan
   file instalasi terpisah.
4. Karena belum ada user sama sekali, halaman `login.php` akan menampilkan
   form **"Buat Akun Admin Pertama"** alih-alih form login biasa. Isi
   username & password (min. 8 karakter) — tidak ada lagi username/password
   default seperti versi lama. Setelah dibuat, Anda langsung login sebagai
   admin.
5. Sebagai admin, buka menu **Pengaturan** untuk mengatur filter kantor,
   pelanggan, prefix kode item, sumber & tarif pajak keluaran, format CSV
   (locale) default, dan **kredensial koneksi database PostgreSQL** (wajib
   diisi di sini — lihat bagian berikutnya). Buka menu **User** untuk
   menambah akun staf lain.

Catatan: form "Buat Akun Admin Pertama" hanya muncul selama tabel `users`
masih kosong. Begitu satu user (admin mana pun) berhasil dibuat, `login.php`
otomatis kembali menampilkan form login biasa untuk semua orang — termasuk
kalau `data/settings.db` dihapus lagi nanti, form setup ini akan otomatis
muncul kembali di percobaan akses berikutnya.

## Pengaturan Database (sejak v1.1.1)

Sejak v1.1.1, `config.php` **tidak lagi menyimpan kredensial PostgreSQL**
sama sekali (hanya lokasi `settings.db`). Semua koneksi ke database transaksi
diatur admin langsung lewat menu **Pengaturan**, tanpa perlu mengedit file
di server:

- Host, Port, Nama Database, User, dan Password disimpan di `data/settings.db`
  lewat `Settings::setDbOverrides()` — bukan di `config.php`.
- Sebelum disimpan, aplikasi **menguji koneksi** ke database dengan nilai
  baru terlebih dahulu; bila gagal, pengaturan lama tetap dipakai.
- Field **Password** pada form selalu kosong saat halaman dibuka (password
  tersimpan tidak pernah ditampilkan ulang). Kosongkan field ini saat
  menyimpan bila hanya ingin mengubah Host/Port/Nama Database/User tanpa
  mengubah password.
- Sampai diisi lewat menu Pengaturan, koneksi database belum berfungsi
  (tidak ada nilai bawaan lagi di `config.php`) — ini disengaja, agar
  kredensial produksi tidak pernah ikut tersimpan di source code.

## Cara Pakai (Staf)

1. Login.
2. Di Dashboard, pilih rentang tanggal (default: awal bulan berjalan s.d. hari
   ini) dan format CSV (locale) bila ingin berbeda dari default admin.
3. Klik **Unduh CSV** — file `laporan_penjualan_YYYY-MM-DD_sd_YYYY-MM-DD.csv`
   akan terunduh otomatis.

## Format Locale CSV

Ada dua pilihan format, bisa diset sebagai default oleh admin di menu
**Pengaturan**, dan tetap bisa di-override staf per-download lewat dropdown
**Format CSV (locale)** di Dashboard (parameter `locale` pada `export.php`):

| Locale | Delimiter kolom | Desimal | Ribuan | Format tanggal |
|---|---|---|---|---|
| `id` — Indonesia (default) | `;` | `,` | `.` | `d/m/Y H:i:s` |
| `en` — Internasional | `,` | `.` | (tanpa pemisah) | `Y-m-d H:i:s` |

Gunakan `id` bila file akan dibuka di Microsoft Excel versi Indonesia (regional
setting koma sebagai desimal). Gunakan `en` bila file akan diimpor ke sistem
lain atau dibuka di Excel/tools berlocale internasional. Kedua format tetap
memakai BOM UTF-8 di awal file agar karakter khusus terbaca benar.

## Kolom Laporan

| Kolom | Keterangan |
|---|---|
| No Transaksi | `tbl_ikhd.notransaksi` |
| Tanggal | `tbl_ikhd.tanggal` |
| Dept | Nama kantor (`tbl_kantor.namakantor`) dari `tbl_ikhd.kodekantor` |
| Kode Pelanggan | `tbl_ikhd.kodesupel` |
| Jumlah Item | Kuantitas baris item, **bersih setelah dikurangi retur** |
| Kode Item | `tbl_ikdt.kodeitem` |
| Nama Item | `tbl_item.namaitem` |
| Pokok | HPP baris (net retur). Diutamakan dari `tbl_ikdt.hppdasar`; jika kosong/0, dihitung dari rata-rata biaya lapisan `tbl_item_ik` (lihat catatan di bawah) |
| Total | Nilai jual baris (net retur), dari `tbl_ikdt.total` |
| Pajak Keluaran | `Total x tarif pajak` (lihat "Sumber Tarif Pajak" di bawah) |
| Laba Kotor | `Total - Pokok` |
| Proc Laba (%) | `Laba Kotor / Pokok x 100` |
| GPM | `Laba Kotor / Total x 100` |

## Sumber Tarif Pajak

Di menu **Pengaturan**, admin bisa memilih dari mana tarif pajak (kolom "Pajak
Keluaran") diambil — ada tiga opsi:

- **Manual** (default) — satu tarif tetap (mis. 0,5%) dari kolom "Tarif Pajak
  Keluaran Manual (%)", dipakai sama untuk semua baris laporan, apa pun nilai
  pajak yang tersimpan di transaksi.
- **Otomatis dari data transaksi (dengan cadangan ke tarif manual)** — tarif
  diambil per baris dari kolom `pajak` pada detail transaksi penjualan
  (`tbl_ikdt.pajak`). Jika baris tidak punya nilai pajak (kosong/0), aplikasi
  jatuh ke tarif pajak header transaksi (`tbl_ikhd.prpajak`), lalu ke tarif
  manual sebagai cadangan terakhir bila keduanya juga kosong/0.
- **Hanya dari data transaksi (tanpa cadangan tarif manual)** — urutan
  pencariannya sama (`tbl_ikdt.pajak` baris, lalu `tbl_ikhd.prpajak` header),
  tetapi **tidak pernah** jatuh ke tarif manual. Bila baris & header
  sama-sama kosong/0, Pajak Keluaran baris tersebut dilaporkan sebagai 0,
  meskipun kolom "Tarif Pajak Keluaran Manual (%)" diisi. Cocok dipakai bila
  Anda ingin laporan benar-benar mencerminkan apa yang tercatat di sistem
  transaksi, termasuk baris yang memang tidak dikenai pajak.

Kolom `pajak` dengan pola nilai yang sama juga tersedia pada detail transaksi
pembelian (`tbl_imdt.pajak`) — logika pencarian/fallback di atas bisa dipakai
ulang bila laporan pembelian dibuat di kemudian hari.

## Asumsi & Logika Perhitungan Penting

Karena skema database tidak memiliki dokumentasi bisnis eksplisit, aplikasi
ini mengambil sejumlah asumsi berikut (silakan sesuaikan `lib/SalesReport.php`
bila berbeda dengan kebijakan internal):

1. **Transaksi penjualan** yang dihitung: tipe `JL`, `KSR`, `TRBCIMPJL`,
   `TRBCIMPKSR` pada `tbl_ikhd`. Dokumen retur (`RJ`, dst.) tidak diambil
   sebagai baris terpisah karena jumlah & nilai retur per baris **sudah
   terekam langsung** pada `tbl_ikdt.jmlretur` (hasil proses fungsi
   `hpp_ik_retur*` di database), sehingga cukup dikurangkan di baris asal.
2. **Kuantitas dasar (base unit)** per baris = `jumlah * jmlkonversi`.
   Kuantitas bersih = `base_qty - jmlretur` (retur tersimpan dalam satuan
   dasar). Proporsi bersih ini juga dipakai untuk memproporsikan kolom
   `Total` agar konsisten net-retur.
3. **Pokok (HPP)** = kuantitas bersih (base unit) × biaya per unit. Skema
   database ini punya **dua jalur** penyimpanan HPP yang saling eksklusif:
   - `fx_hpp_ik(...)` menulis langsung ke `tbl_ikdt.hppdasar`.
   - `hpp_ik(...)` / `hpp_ik_v3(...)` **tidak** mengisi `tbl_ikdt.hppdasar`,
     melainkan mencatat tiap lapisan pembelian yang dipakai (FIFO/LIFO/AVG
     sesuai `tbl_item.hppsys`) ke tabel `tbl_item_ik`
     (`iddetailtrs = tbl_ikdt.iddetail`).

   Jika instalasi Anda memakai jalur kedua, `tbl_ikdt.hppdasar` akan selalu
   0/kosong — sehingga aplikasi ini memakai `tbl_ikdt.hppdasar` bila > 0, dan
   sebagai **fallback** menghitung rata-rata biaya per unit dari
   `SUM(jumlahdasar × hargadasar) / SUM(jumlahdasar)` pada `tbl_item_ik` untuk
   baris terkait. Kedua jalur murni **membaca** nilai yang sudah dihitung &
   disimpan oleh fungsi HPP di database — aplikasi ini tidak menghitung ulang
   HPP dari nol.
4. Baris yang seluruh kuantitasnya sudah diretur (`jmlretur >= base_qty`)
   dilewati (tidak muncul di laporan) karena tidak ada penjualan riil.
5. **Proc Laba (%)** didefinisikan sebagai `Laba Kotor / Pokok x 100` (markup
   terhadap harga pokok), sedangkan **GPM** adalah margin terhadap penjualan
   (`Laba Kotor / Total x 100`). Rumus ini disamakan dengan laporan acuan
   `xReport.csv` (Analisa Laba Jual Detail) dari sistem kasir — sebelumnya
   kedua rumus ini tertukar di kode dan sudah diperbaiki. Jika definisi
   internal perusahaan Anda ternyata berbeda, cukup ubah rumus di
   `SalesReport::rows()`.

## Keamanan

- Password di-hash dengan `password_hash()` (bcrypt).
- Session-based auth, admin-only pages diproteksi dengan `Auth::requireAdmin()`.
- Semua query ke PostgreSQL menggunakan **prepared statement** (parameter
  ter-bind), termasuk daftar filter (IN list) — aman dari SQL injection.
- `data/settings.db` diberi `.htaccess` (`Require all denied`) agar tidak bisa
  diunduh langsung lewat browser. Jika server Anda pakai Nginx, tambahkan
  aturan setara di konfigurasi vhost, misalnya:

  ```
  location ~* /data/.*\.(db)$ { deny all; }
  location ~* /lib/.*\.php$ { deny all; }
  ```

- Koneksi ke database PostgreSQL bersifat **read-only** dari sisi aplikasi
  (tidak ada query INSERT/UPDATE/DELETE ke database transaksi).
