# Code Review & Optimization Plan

> Status: Review only — belum ada perubahan pada source code aplikasi.
> Tanggal review: 26 September 2026
> Branch: main

Dokumen ini berisi hasil review kode dan rencana optimasi. Implementasi dilakukan
terpisah setelah baseline performa diukur dan rencana disetujui.

## 1. Ringkasan

Fondasi aplikasi sudah cukup baik:

- query database menggunakan prepared statement;
- export CSV menggunakan generator/streaming sehingga tidak menampung seluruh hasil di RAM;
- preview dan export menggunakan logika laporan yang sama;
- konfigurasi aplikasi dan settings dipisahkan;
- password user menggunakan password hashing;
- PostgreSQL digunakan sebagai sumber data transaksi;
- Docker digunakan sebagai salah satu target deployment.

Area yang paling berpotensi menjadi bottleneck adalah query laporan penjualan,
terutama agregasi tabel tbl_item_ik.

Tidak ada rekomendasi di dokumen ini yang dianggap sudah diterapkan.

## 2. Temuan Performa

### P0 — Agregasi tbl_item_ik

Query laporan mengagregasi tbl_item_ik berdasarkan iddetailtrs untuk mendapatkan
jumlah dan biaya HPP.

Bentuk saat ini secara konsep:

    SELECT iddetailtrs,
           SUM(jumlahdasar) AS gross_qty,
           SUM(jumlahdasar * hargadasar) AS gross_cost
    FROM tbl_item_ik
    GROUP BY iddetailtrs

Risiko:

- histori tbl_item_ik dapat jauh lebih besar daripada periode laporan;
- database dapat mengagregasi banyak data yang akhirnya tidak digunakan;
- dampaknya akan semakin terasa ketika histori transaksi membesar.

Rencana:

1. ukur query plan terlebih dahulu;
2. uji lookup per iddetailtrs menggunakan LATERAL;
3. bandingkan dengan query agregasi saat ini;
4. pilih berdasarkan EXPLAIN (ANALYZE, BUFFERS), bukan asumsi.

### P1 — Rentang tanggal

Pertimbangkan pola half-open interval:

    tanggal >= :start
    AND tanggal < :endExclusive

Contoh periode September:

    2026-09-01 00:00:00 <= tanggal < 2026-10-01 00:00:00

Keuntungannya adalah timestamp dengan pecahan detik tidak berisiko terlewat karena
batas 23:59:59.

Belum diterapkan pada source code.

## 3. Index PostgreSQL

Periksa index yang sudah tersedia sebelum membuat index baru.

Prioritas tinggi:

    tbl_item_ik(iddetailtrs)
    tbl_ikdt(notransaksi)

Periksa juga index atau unique constraint untuk:

    tbl_item(kodeitem)
    tbl_kantor(kodekantor)

Untuk tbl_ikhd, kandidat awal adalah index pada tanggal. Composite index yang
menggabungkan tanggal, tipe, kantor, dan pelanggan jangan dibuat otomatis.
Ukur selectivity dan query plan terlebih dahulu.

Hindari index duplikat.

## 4. Preview vs Export

SalesReport::preview() menggunakan generator laporan yang sama dengan export CSV.
Ini baik untuk menjaga konsistensi hasil.

Namun preview tetap melakukan iterasi seluruh hasil ketika menghitung total ringkasan.

Untuk dataset besar, opsi optimasi:

1. query preview hanya mengambil jumlah baris yang ditampilkan;
2. query agregat kedua menghitung total;
3. filter bisnis kedua query harus identik;
4. hasil preview harus dibandingkan dengan CSV.

Jangan mengubah pola ini sebelum benchmark menunjukkan bahwa preview memang menjadi
bottleneck.

## 5. Memory dan CSV

Pertahankan pola:

    database cursor -> generator -> fputcsv()

Hindari fetchAll() atau array besar untuk export.

Uji minimal:

- 1.000 baris;
- 10.000 baris;
- 100.000+ baris bila dataset memungkinkan.

Catat peak memory PHP, execution time, total response time, dan ukuran CSV.

## 6. Keamanan

### P1 — CSRF

Form POST yang mengubah state perlu perlindungan CSRF terpusat.

Target:

- token disimpan pada session;
- token dimasukkan ke form;
- token diverifikasi sebelum operasi POST;
- operasi admin tidak dapat dijalankan hanya dengan mengetahui URL endpoint.

### P1 — Session

Review session untuk memastikan production menggunakan:

- HttpOnly;
- SameSite yang sesuai;
- Secure ketika HTTPS;
- session regeneration setelah login;
- idle timeout dan/atau absolute timeout.

Pertimbangkan invalidasi session ketika user dinonaktifkan.

### P1 — PostgreSQL least privilege

Jika aplikasi hanya membaca data transaksi, akun PostgreSQL aplikasi sebaiknya
memiliki permission minimum yang diperlukan, idealnya read-only.

## 7. Validasi Input

Prepared statement mengurangi risiko SQL injection pada nilai query, tetapi validasi
input tetap diperlukan.

Periksa:

- tanggal awal dan akhir;
- filter kantor;
- filter pelanggan;
- prefix kode item;
- tarif pajak;
- locale;
- konfigurasi koneksi database;
- nama user dan role.

Validasi juga membantu mencegah input yang menghasilkan query terlalu besar.

## 8. Settings SQLite

Settings relatif kecil, tetapi key yang sama dapat dibaca beberapa kali dalam satu
request.

Optimasi yang dapat diuji:

- cache setting selama satu request; atau
- load seluruh settings sekali ke memory request.

Jangan menggunakan cache persisten tanpa invalidasi karena administrator dapat
mengubah settings.

## 9. PostgreSQL Connection

Jangan langsung mengaktifkan persistent connection.

PDO connection biasa lebih sederhana. Persistent connection hanya perlu diuji bila
profiling membuktikan connection setup merupakan bottleneck.

## 10. Synology DSM dan PHP

Docker dan PHP native Synology DSM harus diperlakukan sebagai target runtime berbeda.

Sebelum perubahan kompatibilitas PHP native, periksa versi aktual di NAS:

    php -v
    php -m

Extension minimum yang perlu diverifikasi:

    PDO
    pdo_pgsql
    pdo_sqlite

Jangan menyesuaikan source berdasarkan asumsi versi PHP DSM.

## 11. Docker

Sebelum tuning image:

1. catat versi image PHP;
2. catat extension;
3. ukur response time;
4. ukur memory;
5. ukur waktu query PostgreSQL.

Optimasi image harus diuji karena extension PHP dapat bergantung pada package OS.

## 12. Backup settings.db

settings.db berisi konfigurasi aplikasi dan data akun yang sensitif.

Backup SQLite aktif sebaiknya memakai mekanisme backup SQLite, bukan sekadar menyalin
file ketika database sedang ditulis.

Contoh:

    sqlite3 settings.db ".backup 'settings-backup.db'"

Backup harus memiliki permission yang sesuai.

## 13. Benchmark Baseline

Sebelum optimasi kode, buat baseline untuk:

| Skenario | Periode |
|---|---|
| Kecil | 1 hari |
| Normal | 1 bulan |
| Besar | 3–12 bulan |
| Filter | kantor/pelanggan/prefix item |

Catat:

- PostgreSQL execution time;
- total response time;
- rows returned;
- shared buffers read/hit;
- peak PHP memory;
- ukuran CSV;
- waktu export.

Gunakan:

    EXPLAIN (ANALYZE, BUFFERS)

Simpan hasil sebelum perubahan.

## 14. Regression Checklist

Setiap optimasi query laporan wajib menguji:

- [ ] transaksi awal periode;
- [ ] transaksi akhir periode;
- [ ] timestamp dengan pecahan detik;
- [ ] retur parsial;
- [ ] retur penuh;
- [ ] HPP dari tbl_ikdt.hppdasar;
- [ ] fallback HPP dari tbl_item_ik;
- [ ] pajak manual;
- [ ] pajak database;
- [ ] database_only;
- [ ] filter kantor;
- [ ] filter pelanggan;
- [ ] filter prefix item;
- [ ] preview;
- [ ] CSV locale Indonesia;
- [ ] CSV locale Inggris;
- [ ] dataset kosong.

Hasil sebelum dan sesudah harus dibandingkan secara numerik.

## 15. Urutan Implementasi

| Prioritas | Perubahan | Tujuan |
|---|---|---|
| P0 | Benchmark + EXPLAIN | Menentukan bottleneck nyata |
| P0 | Verifikasi index tbl_item_ik(iddetailtrs) | Lookup HPP |
| P0 | Uji optimasi agregasi HPP | Mengurangi scan/agregasi |
| P1 | Half-open date range | Range timestamp |
| P1 | CSRF protection | Keamanan POST |
| P1 | PostgreSQL least privilege | Defense in depth |
| P1 | Session lifecycle | Keamanan session |
| P2 | Optimasi preview | Dataset besar |
| P2 | Cache Settings per request | Mengurangi query SQLite |
| P3 | Docker/PHP tuning | Efisiensi deployment |

## 16. Prinsip Perubahan

Sebelum setiap optimasi:

1. buat baseline;
2. ubah satu area;
3. jalankan regression test;
4. bandingkan hasil laporan;
5. bandingkan query plan;
6. bandingkan penggunaan resource;
7. commit perubahan secara terpisah.

Jangan melakukan banyak perubahan performa sekaligus tanpa benchmark karena akan sulit
menentukan perubahan mana yang benar-benar memberikan dampak.

## Status

Dokumen ini adalah hasil review dan rencana optimasi awal.

Source code aplikasi **belum diubah sebagai bagian dari review ini**.
