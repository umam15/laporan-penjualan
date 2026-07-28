<?php
declare(strict_types=1);

/**
 * Laporan Penjualan (untuk keperluan pajak).
 *
 * Sumber data:
 *  - tbl_ikhd (header transaksi penjualan) & tbl_ikdt (detail item)
 *  - tbl_item (nama item), tbl_kantor (nama dept/kantor)
 *  - tbl_item_ik (lapisan biaya FIFO/LIFO/AVG per baris penjualan)
 *
 * Asumsi perhitungan (karena skema tidak mendokumentasikan secara eksplisit):
 *  - Tipe transaksi yang dihitung sebagai "penjualan": JL, KSR, dan varian
 *    transfer beda cabang (TRBCIMPJL, TRBCIMPKSR). Retur (RJ, dst) TIDAK
 *    diambil sebagai baris terpisah karena retur per baris sudah terekam
 *    pada kolom tbl_ikdt.jmlretur (dalam satuan dasar / base unit).
 *  - Kuantitas dasar (base unit) per baris = jumlah * jmlkonversi.
 *    Kuantitas bersih (net retur) = base_qty - jmlretur (tidak boleh < 0).
 *  - Proporsi bersih = net_base_qty / base_qty digunakan untuk memproporsikan
 *    kolom "total" (nilai jual) baris tersebut agar ikut bersih dari retur.
 *  - Kolom Pokok (HPP) = net_base_qty x biaya-per-unit. Skema DB ini punya DUA
 *    jalur penyimpanan HPP yang saling eksklusif tergantung fungsi mana yang
 *    dipanggil saat transaksi disimpan:
 *      1) fx_hpp_ik(...) - menulis langsung ke tbl_ikdt.hppdasar (biaya
 *         rata-rata sederhana per baris).
 *      2) hpp_ik(...) / hpp_ik_v3(...) - TIDAK mengisi tbl_ikdt.hppdasar,
 *         melainkan mencatat setiap "lapisan" pembelian yang dipakai untuk
 *         memenuhi baris penjualan tsb ke tabel tbl_item_ik (kolom
 *         iddetailtrs = tbl_ikdt.iddetail, jumlahdasar & hargadasar per
 *         lapisan sesuai FIFO/LIFO/AVG dari tbl_item.hppsys).
 *    Karena instalasi ini ternyata memakai jalur (2) - terbukti dari
 *    tbl_ikdt.hppdasar yang selalu 0 pada data - Pokok akan KOSONG bila hanya
 *    membaca tbl_ikdt.hppdasar. Oleh karena itu aplikasi ini memakai
 *    tbl_ikdt.hppdasar bila > 0, dan sebagai fallback menghitung biaya
 *    rata-rata per unit dari SUM(jumlahdasar x hargadasar) / SUM(jumlahdasar)
 *    pada tbl_item_ik untuk baris terkait. Tidak ada HPP yang dihitung ulang
 *    di aplikasi ini - keduanya murni membaca nilai yang sudah dihitung &
 *    disimpan oleh fungsi-fungsi HPP di database.
 *  - Pajak Keluaran = Total(bersih) x tarif pajak. Tarif pajak bisa berasal dari
 *    tiga sumber tergantung pengaturan admin (Settings::getTaxSource()):
 *      - 'manual' (default): satu tarif tetap dari pengaturan (Settings::getTaxRate(),
 *        default 0.5%), dipakai sama untuk semua baris.
 *      - 'database': tarif diambil langsung dari kolom pajak yang sudah tersimpan
 *        pada transaksi itu sendiri saat transaksi dibuat, yaitu tbl_ikdt.pajak
 *        (persentase per baris item penjualan). Jika kolom itu kosong/0, jatuh ke
 *        tbl_ikhd.prpajak (persentase pajak header transaksi), lalu ke tarif
 *        manual sebagai cadangan terakhir bila keduanya kosong.
 *      - 'database_only': urutan pencarian sama persis dengan 'database'
 *        (tbl_ikdt.pajak baris -> tbl_ikhd.prpajak header), TAPI TIDAK PERNAH
 *        jatuh ke tarif manual. Bila baris & header sama-sama kosong/0, tarif
 *        pajak baris tersebut dianggap 0, meskipun tarif manual di pengaturan
 *        diisi.
 *    Kolom pajak dengan pola nilai yang sama juga ada pada tabel detail
 *    transaksi pembelian (tbl_imdt.pajak), sehingga logika di atas bisa
 *    dipakai ulang bila laporan pembelian dibuat di kemudian hari.
 *  - Opsi tambahan khusus 'database_only' (Settings::getDatabaseOnlySkipZeroTax()):
 *    bila diaktifkan oleh admin, baris yang tarif pajaknya 0 (baris & header
 *    transaksi sama-sama kosong/0) TIDAK diikutsertakan sama sekali dalam data
 *    yang diunduh (dilewati/skip), alih-alih ikut terunduh dengan Pajak
 *    Keluaran = 0. Defaultnya nonaktif (perilaku lama tetap dipertahankan).
 *    Tidak berpengaruh sama sekali untuk Sumber Tarif Pajak 'manual'/'database'.
 *  - Laba Kotor = Total(bersih) - Pokok(bersih).
 *  - Proc Laba (%) = Laba Kotor / Pokok x 100 (markup terhadap harga pokok).
 *  - GPM = Laba Kotor / Total x 100 (margin terhadap penjualan / gross profit margin).
 *    Rumus ini disamakan dengan acuan xReport.csv (laporan analisa laba jual detail).
 */
class SalesReport
{
    /** Tipe transaksi tbl_ikhd yang dianggap sebagai penjualan */
    private const SALES_TYPES = ['JL', 'KSR', 'TRBCIMPJL', 'TRBCIMPKSR'];

    /**
     * @return array{0: string, 1: array} [$sql, $params]
     */
    public static function buildQuery(string $startDate, string $endDate): array
    {
        $params = [
            ':start' => $startDate . ' 00:00:00',
            ':end'   => $endDate . ' 23:59:59',
        ];

        $typePlaceholders = [];
        foreach (self::SALES_TYPES as $i => $t) {
            $ph = ":tipe{$i}";
            $typePlaceholders[] = $ph;
            $params[$ph] = $t;
        }

        $where = [
            'h.tanggal BETWEEN :start AND :end',
            'h.tipe IN (' . implode(',', $typePlaceholders) . ')',
        ];

        // Filter kantor (setting admin)
        $kantorList = Settings::getKantorFilter();
        if (!empty($kantorList)) {
            $ph = [];
            foreach ($kantorList as $i => $k) {
                $key = ":kantor{$i}";
                $ph[] = $key;
                $params[$key] = $k;
            }
            $where[] = 'h.kodekantor IN (' . implode(',', $ph) . ')';
        }

        // Filter pelanggan (setting admin)
        $pelangganList = Settings::getPelangganFilter();
        if (!empty($pelangganList)) {
            $ph = [];
            foreach ($pelangganList as $i => $p) {
                $key = ":pel{$i}";
                $ph[] = $key;
                $params[$key] = $p;
            }
            $where[] = 'h.kodesupel IN (' . implode(',', $ph) . ')';
        }

        // Filter prefix kode item (setting admin), 1-2 karakter awal, dipisah koma
        $prefixList = Settings::getItemPrefixFilter();
        if (!empty($prefixList)) {
            $ors = [];
            foreach ($prefixList as $i => $p) {
                $key = ":prefix{$i}";
                $ors[] = "d.kodeitem LIKE {$key}";
                $params[$key] = $p . '%';
            }
            $where[] = '(' . implode(' OR ', $ors) . ')';
        }

        $sql = "
            SELECT
                h.notransaksi,
                h.tanggal,
                h.kodekantor,
                COALESCE(k.namakantor, h.kodekantor) AS namakantor,
                h.kodesupel,
                d.nobaris,
                d.kodeitem,
                COALESCE(i.namaitem, d.kodeitem) AS namaitem,
                d.jumlah,
                d.satuan,
                COALESCE(d.jmlkonversi, 1) AS jmlkonversi,
                COALESCE(d.hppdasar, 0) AS hppdasar,
                COALESCE(ik.gross_qty, 0) AS ik_gross_qty,
                COALESCE(ik.gross_cost, 0) AS ik_gross_cost,
                COALESCE(d.total, 0) AS total,
                COALESCE(d.jmlretur, 0) AS jmlretur,
                COALESCE(d.pajak, 0) AS pajak_baris,
                COALESCE(h.prpajak, 0) AS pajak_header
            FROM tbl_ikhd h
            INNER JOIN tbl_ikdt d ON d.notransaksi = h.notransaksi
            LEFT JOIN tbl_item i ON i.kodeitem = d.kodeitem
            LEFT JOIN tbl_kantor k ON k.kodekantor = h.kodekantor
            LEFT JOIN (
                SELECT
                    iddetailtrs,
                    SUM(jumlahdasar) AS gross_qty,
                    SUM(jumlahdasar * hargadasar) AS gross_cost
                FROM tbl_item_ik
                GROUP BY iddetailtrs
            ) ik ON ik.iddetailtrs = d.iddetail
            WHERE " . implode(' AND ', $where) . '
            ORDER BY h.tanggal, h.notransaksi, d.nobaris, d.iddetail
        ';

        return [$sql, $params];
    }

    /**
     * Generator baris laporan yang sudah dihitung (net retur, pokok, pajak, laba).
     */
    public static function rows(string $startDate, string $endDate): \Generator
    {
        [$sql, $params] = self::buildQuery($startDate, $endDate);
        $stmt = Database::pgsql()->prepare($sql);
        $stmt->execute($params);

        $manualTaxRate     = Settings::getTaxRate(); // persen, mis. 0.5 => 0.5%
        $taxSource         = Settings::getTaxSource(); // 'manual', 'database', atau 'database_only'
        $skipZeroTaxDbOnly = Settings::getDatabaseOnlySkipZeroTax(); // hanya dipakai bila $taxSource === 'database_only'

        while ($r = $stmt->fetch()) {
            $jumlah      = (float) $r['jumlah'];
            $jmlkonversi = (float) $r['jmlkonversi'];
            if ($jmlkonversi <= 0) {
                $jmlkonversi = 1;
            }
            $hppdasarIkdt = (float) $r['hppdasar'];
            $ikGrossQty   = (float) $r['ik_gross_qty'];
            $ikGrossCost  = (float) $r['ik_gross_cost'];
            $totalGross   = (float) $r['total'];
            $jmlretur     = (float) $r['jmlretur']; // dalam satuan dasar

            // Tarif pajak yang dipakai untuk baris ini, tergantung Settings::getTaxSource():
            //  - 'manual'        : selalu $manualTaxRate.
            //  - 'database'      : tbl_ikdt.pajak (baris) -> tbl_ikhd.prpajak (header) ->
            //                      $manualTaxRate sebagai cadangan terakhir.
            //  - 'database_only' : tbl_ikdt.pajak (baris) -> tbl_ikhd.prpajak (header) -> 0
            //                      (TIDAK pernah jatuh ke tarif manual, walau diisi).
            if ($taxSource === 'database' || $taxSource === 'database_only') {
                $pajakBaris  = (float) $r['pajak_baris'];
                $pajakHeader = (float) $r['pajak_header'];
                if ($pajakBaris > 0) {
                    $taxRate = $pajakBaris;
                } elseif ($pajakHeader > 0) {
                    $taxRate = $pajakHeader;
                } elseif ($taxSource === 'database') {
                    $taxRate = $manualTaxRate;
                } else {
                    $taxRate = 0.0;
                }
            } else {
                $taxRate = $manualTaxRate;
            }

            // Opsi khusus 'database_only': lewati (jangan unduh) baris yang tarif
            // pajaknya 0 (baris & header transaksi sama-sama kosong/0), bila admin
            // mengaktifkan Settings::getDatabaseOnlySkipZeroTax(). Tidak berlaku
            // untuk 'manual'/'database' karena keduanya selalu > 0 lewat cadangan.
            if ($taxSource === 'database_only' && $skipZeroTaxDbOnly && $taxRate <= 0.0) {
                continue;
            }

            // Biaya per unit (base unit): utamakan tbl_ikdt.hppdasar (jalur fx_hpp_ik).
            // Jika kosong/0 (instalasi memakai jalur hpp_ik/hpp_ik_v3 + tbl_item_ik),
            // fallback ke rata-rata biaya lapisan FIFO/LIFO/AVG dari tbl_item_ik agar
            // Pokok tidak selalu tampil kosong.
            $hppPerUnit = $hppdasarIkdt;
            if ($hppPerUnit <= 0 && $ikGrossQty > 0) {
                $hppPerUnit = $ikGrossCost / $ikGrossQty;
            }

            $baseGrossQty = $jumlah * $jmlkonversi;
            $netBaseQty   = max($baseGrossQty - $jmlretur, 0.0);

            // Lewati baris yang seluruhnya sudah diretur (tidak ada penjualan riil)
            if ($netBaseQty <= 0) {
                continue;
            }

            $proportion = $baseGrossQty > 0 ? ($netBaseQty / $baseGrossQty) : 0.0;

            $netQty   = $jumlah * $proportion;      // kuantitas net retur, dalam satuan transaksi
            $netTotal = $totalGross * $proportion;  // nilai jual net retur
            $pokok    = $netBaseQty * $hppPerUnit;  // HPP net retur (dari histori pembelian & stok)

            $pajakKeluaran = $netTotal * ($taxRate / 100);
            $labaKotor     = $netTotal - $pokok;
            $procLaba      = $pokok != 0 ? ($labaKotor / $pokok) * 100 : 0.0;
            $gpm           = $netTotal != 0 ? ($labaKotor / $netTotal) * 100 : 0.0;

            yield [
                'notransaksi'    => $r['notransaksi'],
                'tanggal'        => $r['tanggal'],
                'dept'           => $r['namakantor'],
                'kodesupel'      => $r['kodesupel'],
                'jumlah_item'    => $netQty,
                'kodeitem'       => $r['kodeitem'],
                'namaitem'       => $r['namaitem'],
                'pokok'          => $pokok,
                'total'          => $netTotal,
                'pajak_keluaran' => $pajakKeluaran,
                'laba_kotor'     => $labaKotor,
                'proc_laba'      => $procLaba,
                'gpm'            => $gpm,
            ];
        }
    }

    /**
     * Kirim laporan langsung sebagai file CSV ke browser (streaming, hemat memori).
     *
     * @param string $localeCode Kode format locale CSV ('id' atau 'en'). Menentukan
     *                            delimiter kolom, pemisah desimal/ribuan, dan format
     *                            tanggal. Lihat CsvLocale.
     */
    public static function streamCsv(string $startDate, string $endDate, string $localeCode = 'id'): void
    {
        $profile = CsvLocale::profile(CsvLocale::isValid($localeCode) ? $localeCode : 'id');
        $delimiter = $profile['delimiter'];

        $filename = 'laporan_penjualan_' . $startDate . '_sd_' . $endDate . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // BOM UTF-8 agar Excel membaca karakter dengan benar
        fwrite($out, "\xEF\xBB\xBF");

        fputcsv($out, [
            'No Transaksi', 'Tanggal', 'Dept', 'Kode Pelanggan', 'Jumlah Item',
            'Kode Item', 'Nama Item', 'Pokok', 'Total', 'Pajak Keluaran',
            'Laba Kotor', 'Proc Laba (%)', 'GPM',
        ], $delimiter);

        $count = 0;
        foreach (self::rows($startDate, $endDate) as $row) {
            fputcsv($out, [
                $row['notransaksi'],
                CsvLocale::date($profile, $row['tanggal']),
                $row['dept'],
                $row['kodesupel'],
                CsvLocale::number($profile, $row['jumlah_item'], 3),
                $row['kodeitem'],
                $row['namaitem'],
                CsvLocale::number($profile, $row['pokok'], 2),
                CsvLocale::number($profile, $row['total'], 2),
                CsvLocale::number($profile, $row['pajak_keluaran'], 2),
                CsvLocale::number($profile, $row['laba_kotor'], 2),
                CsvLocale::number($profile, $row['proc_laba'], 2),
                CsvLocale::number($profile, $row['gpm'], 2),
            ], $delimiter);
            $count++;
        }

        if ($count === 0) {
            // baris info agar file tidak terlihat "kosong/rusak"
            fputcsv($out, ['(Tidak ada data penjualan pada rentang tanggal & filter yang dipilih)'], $delimiter);
        }

        fclose($out);
    }
}
