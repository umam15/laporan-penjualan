# Kontribusi

Terima kasih sudah mau membantu. Dokumen ini singkat; istilah bisnis ada di
[GLOSARIUM.md](GLOSARIUM.md) dan rencana kerja di [TODO.md](TODO.md).

## Aturan utama

- **Database transaksi hanya dibaca.** Aplikasi ini tidak boleh menulis, mengubah,
  atau menghapus data di PostgreSQL transaksi.
- **Jangan commit rahasia**: kredensial database, `data/*.db`, `.env`, berkas
  `.zip`. Semuanya sudah ada di `.gitignore`.
- Ubah satu hal per commit. Awali pesan dengan `feat:`, `fix:`, `docs:`,
  `security:`, `test:`, atau `chore:`.

## Menjalankan tes

Tes memakai runner PHP sendiri (tanpa Composer):

```bash
php tests/run.php
```

Tes yang butuh PostgreSQL hanya jalan bila `LP_TEST_PG_HOST` diisi dan data uji
dari `tests/seed.sql` sudah dimuat. Tanpa itu, tes tersebut dilewati. Cara paling
mudah meniru CI ada di `.github/workflows/ci.yml` (Docker + PostgreSQL
sementara). Tes tidak menyentuh `data/settings.db`.

Setiap perubahan perilaku sebaiknya disertai tes di `tests/cases.php`.

## Versi dan rilis

1. Naikkan angka di berkas `VERSION` (sumber tunggal).
2. Tambah heading `## [x.y.z]` di paling atas `CHANGELOG.md` dengan angka yang
   sama. Tes memeriksa keduanya selalu sama.
3. Push ke `main`. Workflow *Auto Release* membuat tag dan rilis GitHub dari
   `CHANGELOG.md`.

Selama belum siap dirilis, tulis perubahan di bawah heading
`## [Belum dirilis]`; heading itu tidak memicu rilis.

## Continuous Integration

Setiap push ke `main` dan setiap pull request menjalankan `CI`: sintaks PHP,
tes, build Docker, dan pemeriksaan isi image (tidak boleh memuat `tests/`,
`docs/`, `.git`, `*.db`, dan sejenisnya). Perbaiki CI sampai hijau sebelum
menggabungkan perubahan.

Action GitHub dipin ke SHA commit. Saat memperbarui, pilih rilis yang sudah
berumur beberapa minggu dan ganti SHA beserta komentar versinya.
