-- Data uji minimal untuk tes integrasi PostgreSQL (tests/cases.php).
-- Skema ini hanya meniru kolom yang dibaca SalesReport; bukan skema produksi.
CREATE TABLE tbl_kantor (kodekantor text PRIMARY KEY, namakantor text);
CREATE TABLE tbl_itemjenis (jenis text PRIMARY KEY, ketjenis text);
CREATE TABLE tbl_item (kodeitem text PRIMARY KEY, namaitem text, jenis text);
CREATE TABLE tbl_ikhd (notransaksi text PRIMARY KEY, tanggal timestamp, tipe text, kodekantor text, kodesupel text, prpajak numeric);
CREATE TABLE tbl_ikdt (iddetail serial PRIMARY KEY, notransaksi text, nobaris int, kodeitem text, jumlah numeric, satuan text, jmlkonversi numeric, hppdasar numeric, total numeric, jmlretur numeric, pajak numeric);
CREATE TABLE tbl_item_ik (iddetailtrs int, jumlahdasar numeric, hargadasar numeric);

INSERT INTO tbl_kantor VALUES ('K1', 'Kantor Satu');
-- OIL: nama kosong (uji cadangan ke kode); ZZZ sengaja tidak ada di master.
INSERT INTO tbl_itemjenis VALUES ('BOLT', 'BAUT MUR'), ('OIL', '  ');
INSERT INTO tbl_item VALUES
  ('A001', 'Baut 10', 'BOLT'),
  ('B001', 'Oli X', 'OIL'),
  ('C001', 'Item tanpa master', 'ZZZ'),
  ('D001', 'Item jenis kosong', NULL);
INSERT INTO tbl_ikhd VALUES ('JL-1', '2026-10-05 10:00', 'JL', 'K1', 'P1', 0);
-- X999 tidak ada di tbl_item (uji item tanpa master).
INSERT INTO tbl_ikdt (notransaksi, nobaris, kodeitem, jumlah, satuan, jmlkonversi, hppdasar, total, jmlretur, pajak) VALUES
  ('JL-1', 1, 'A001', 2, 'PCS', 1, 100, 300, 0, 0),
  ('JL-1', 2, 'B001', 1, 'PCS', 1, 50, 90, 0, 0),
  ('JL-1', 3, 'C001', 1, 'PCS', 1, 10, 20, 0, 0),
  ('JL-1', 4, 'D001', 1, 'PCS', 1, 10, 20, 0, 0),
  ('JL-1', 5, 'X999', 1, 'PCS', 1, 10, 20, 0, 0);
