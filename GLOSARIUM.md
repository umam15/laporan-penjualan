# Glosarium

Istilah yang dipakai di aplikasi dan laporan. Angka dan nama kolom mengikuti
README bagian *Kolom Laporan* dan *Asumsi & Logika Perhitungan*.

| Istilah | Arti |
|---|---|
| Pokok (HPP) | Harga pokok baris: kuantitas bersih x biaya per unit. Dibaca dari `tbl_ikdt.hppdasar`; bila kosong/0, dari rata-rata biaya lapisan `tbl_item_ik`. Aplikasi tidak menghitung ulang HPP. |
| Total | Nilai jual baris setelah dikurangi retur (`tbl_ikdt.total`). |
| Laba Kotor | `Total - Pokok`. |
| Proc Laba (%) | Markup terhadap Pokok: `Laba Kotor / Pokok x 100`. |
| GPM | Margin kotor terhadap Total: `Laba Kotor / Total x 100`. |
| Pajak Keluaran | `Total x tarif pajak`; sumber tarif dipilih admin (manual, otomatis dengan cadangan, atau hanya dari data). |
| Retur | Barang dikembalikan. Terekam di `tbl_ikdt.jmlretur` dan mengurangi kuantitas serta Total baris asal; bukan baris terpisah. |
| Kuantitas dasar / bersih | Dasar = `jumlah x jmlkonversi`; bersih = dasar dikurangi retur. |
| Tipe transaksi | Kode pada `tbl_ikhd.tipe`. Yang dihitung sebagai penjualan: `JL`, `KSR`, `TRBCIMPJL`, `TRBCIMPKSR`. Arti singkatan lengkapnya belum dipastikan; tipe `IK` masih menunggu keputusan (lihat [TODO.md](TODO.md)). |
| Dept | Nama kantor (`tbl_kantor.namakantor`) dari `tbl_ikhd.kodekantor`. |
| Jenis | Pengelompokan item: kode di `tbl_item.jenis`, nama di `tbl_itemjenis.ketjenis`. Admin memilih tampil kode (default) atau nama. |
| Locale CSV | Aturan format angka/tanggal/pemisah pada ekspor CSV: `id` (desimal koma, pemisah `;`) atau `en` (desimal titik, pemisah `,`). |
| BOM | Tanda urutan byte UTF-8 di awal CSV agar Excel membaca huruf dengan benar. |
| Pengaturan | Menu admin; nilainya tersimpan di `data/settings.db` (SQLite), bukan di source code. |
| Batas login | Pembatasan percobaan login gagal per username dan per alamat IP dalam jendela waktu tertentu. |
