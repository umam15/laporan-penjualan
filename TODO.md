# TODO

Rencana kerja yang masih terbuka, diurutkan menurut rencana rilis. Rilis yang
sudah terbit ada di [CHANGELOG.md](CHANGELOG.md).

## Keamanan lanjutan (1.4.2)

- Batasi pembuatan admin pertama setelah instalasi.
- Backup pengaturan tanpa password database.
- Koneksi database transaksi read-only dengan `statement_timeout`.
- Netralkan awalan rumus (`=`, `+`, `-`, `@`) pada nilai CSV.
- Header keamanan (CSP dan lain-lain).
- Escape `LIKE` dan validasi filter; aturan username seragam; validasi sebelum
  menyimpan; restore backup lebih ketat.

## Data laporan (1.5.0, perlu keputusan pemilik data)

- Sumber HPP dan pajak tanpa cadangan berantai.
- Tipe angka, dan apakah `biayalain` termasuk penjualan.
- Arti tipe transaksi `IK`.

## Performa (1.5.x)

- Ukur baseline waktu dan memori ekspor besar.
- Kurangi memori ekspor, kunci sesi lebih singkat, pratinjau tanpa query ganda
  dan pembatas permintaan, cache `Settings`, SQLite WAL + `busy_timeout`.

## Infrastruktur dan dokumen (1.5.x)

- Naik ke PHP 8.3+ (PHP 8.2 berakhir dukungan 31 Des 2026), `HEALTHCHECK`,
  `php.ini` produksi.
- README tanpa istilah teknis untuk staf; rapikan `docs/`.
