<?php

namespace App\Support\Lca;

use App\Models\Lca\Faktor;

/**
 * Pustaka faktor dampak bawaan untuk produk tambang.
 *
 * Nilainya titik berangkat yang wajar dari sumber yang disebut — IPCC
 * 2006/AR6, faktor grid ESDM, Ecoinvent 3.10, DEFRA/GLEC — BUKAN angka
 * resmi siap lapor. Setiap baris bertanda `perlu_cocok` sampai petugas
 * mencocokkannya dengan dokumen sumbernya; studi yang memakai faktor
 * belum dicocokkan diberi tahu pada daftar periksa ISO 14044.
 *
 * Dipasang `lca:pasang` (dan oleh data contoh), aman diulang: baris yang
 * sudah ada diperbarui nilainya hanya bila masih `perlu_cocok` — faktor
 * yang sudah dicocokkan orang tidak ditimpa.
 */
final class Pustaka
{
    /** kode, nama, kategori, tahap, scope, satuan, gwp, ap, ep, wsf, ced, odp, sumber, tahun, wilayah, catatan */
    public const FAKTOR = [
        // ── energi & bahan bakar
        ['EF-SOLAR-B40', 'Solar B40 alat berat (pembakaran + hulu)', 'energi', 'ekstraksi', 1, 'L', 2.21, 0.0068, 0.00092, 0.026, 37.4, 6.0e-8, 'IPCC 2006 Vol.2 · hulu Ecoinvent 3.10 · FAME 40%', 2025, 'Indonesia', 'CO₂ biogenik FAME tidak dihitung dalam GWP fosil.'],
        ['EF-SOLAR-B0', 'Solar B0 / HSD (pembakaran + hulu)', 'energi', 'ekstraksi', 1, 'L', 3.21, 0.0075, 0.00095, 0.012, 42.8, 7.0e-8, 'IPCC 2006 Vol.2 · Ecoinvent 3.10', 2024, 'Indonesia', null],
        ['EF-BENSIN', 'Bensin kendaraan ringan', 'energi', 'ekstraksi', 1, 'L', 2.78, 0.0031, 0.00041, 0.010, 38.1, 8.0e-8, 'IPCC 2006 · Ecoinvent 3.10', 2024, 'Indonesia', null],
        ['EF-LPG', 'LPG dapur & pemanas', 'energi', 'olah', 1, 'kg', 3.10, 0.0012, 0.00011, 0.004, 52.0, 2.0e-8, 'IPCC 2006 · Ecoinvent 3.10', 2024, 'Indonesia', null],
        ['EF-EL-KAL', 'Listrik PLN sistem Kalimantan', 'energi', 'olah', 2, 'kWh', 1.02, 0.0061, 0.00062, 0.021, 11.4, 4.0e-8, 'Faktor emisi grid ESDM (sistem Kalimantan) + hulu', 2023, 'ID-Kalimantan', 'Cocokkan dengan penetapan Ditjen Gatrik terbaru untuk sistem setempat.'],
        ['EF-EL-SUL', 'Listrik PLN sistem Sulawesi bagian selatan', 'energi', 'olah', 2, 'kWh', 0.68, 0.0038, 0.00041, 0.034, 8.6, 3.0e-8, 'Faktor emisi grid ESDM (Sulbagsel) + hulu', 2023, 'ID-Sulawesi', null],
        ['EF-EL-JAMALI', 'Listrik PLN Jawa-Madura-Bali', 'energi', 'olah', 2, 'kWh', 0.87, 0.0042, 0.00051, 0.018, 9.8, 4.0e-8, 'Faktor emisi grid ESDM (Jamali) + hulu', 2023, 'ID-Jawa-Bali', null],
        ['EF-EL-SUM', 'Listrik PLN sistem Sumatera', 'energi', 'olah', 2, 'kWh', 0.92, 0.0048, 0.00055, 0.020, 10.2, 4.0e-8, 'Faktor emisi grid ESDM (Sumatera) + hulu', 2023, 'ID-Sumatera', null],
        ['EF-EL-CAPTIVE', 'Listrik PLTU captive kawasan (smelter)', 'energi', 'olah', 2, 'kWh', 1.15, 0.0094, 0.00071, 0.025, 12.6, 4.0e-8, 'IPCC 2006 · efisiensi PLTU 33%', 2024, 'ID-Maluku-Sulawesi', null],
        ['EF-EL-PLTS', 'Listrik PLTS atap / lahan', 'energi', 'olah', 2, 'kWh', 0.045, 0.0003, 0.00004, 0.002, 4.1, 1.0e-8, 'IPCC AR6 · Ecoinvent 3.10', 2024, 'Indonesia', null],
        ['EF-BB-DRYER', 'Batubara untuk dryer / kiln', 'energi', 'olah', 1, 'kg', 2.42, 0.0185, 0.0014, 0.012, 24.5, 3.0e-8, 'IPCC AR6 · ESDM 2024', 2024, 'Indonesia', null],

        // ── bahan peledak & material
        ['EF-ANFO', 'Bahan peledak ANFO (produksi + detonasi)', 'peledak', 'ekstraksi', 1, 'kg', 2.15, 0.012, 0.0045, 0.060, 25.0, 1.0e-8, 'Ecoinvent 3.10 amonium nitrat · IPCC 2006 Vol.3', 2024, 'Global', 'Termasuk N₂O pabrik asam nitrat di hulu.'],
        ['EF-EMULSI', 'Bahan peledak emulsi', 'peledak', 'ekstraksi', 1, 'kg', 1.95, 0.011, 0.0042, 0.070, 23.0, 1.0e-8, 'Ecoinvent 3.10', 2024, 'Global', null],
        ['EF-BAN-OTR', 'Ban OTR alat berat (karet & baja)', 'material', 'ekstraksi', 3, 'kg', 2.90, 0.0110, 0.0012, 0.150, 85.0, 1.2e-7, 'Ecoinvent 3.10 · ETRMA', 2024, 'Global', null],
        ['EF-PELUMAS', 'Pelumas & oli mesin', 'material', 'ekstraksi', 3, 'L', 1.35, 0.0052, 0.0006, 0.020, 48.0, 1.0e-7, 'Ecoinvent 3.10', 2024, 'Global', null],
        ['EF-BAJA', 'Baja suku cadang & konstruksi', 'material', 'olah', 3, 'kg', 2.05, 0.0072, 0.0016, 0.045, 22.0, 6.0e-8, 'worldsteel 2023 · Ecoinvent 3.10', 2023, 'Global', null],
        ['EF-SEMEN', 'Semen portland (pondasi, shotcrete)', 'material', 'olah', 3, 'kg', 0.84, 0.0021, 0.0002, 0.031, 3.95, 2.0e-8, 'GCCA · KLHK 2024', 2024, 'Indonesia', null],
        ['EF-KAPUR', 'Kapur tohor (penetral air asam tambang)', 'material', 'olah', 3, 'kg', 1.20, 0.0016, 0.0002, 0.015, 5.6, 1.0e-8, 'Ecoinvent 3.10 · kalsinasi 0,785 kg CO₂/kg', 2024, 'Indonesia', null],
        ['EF-NACN', 'Natrium sianida (pengolahan emas)', 'material', 'olah', 3, 'kg', 4.60, 0.0210, 0.0031, 0.210, 78.0, 3.0e-7, 'Ecoinvent 3.10', 2024, 'Global', null],
        ['EF-KOAGULAN', 'Koagulan PAC / tawas (IPAL & sediment pond)', 'material', 'olah', 3, 'kg', 0.55, 0.0024, 0.0003, 0.030, 7.2, 1.0e-8, 'Ecoinvent 3.10', 2024, 'Global', null],

        // ── transportasi
        ['EF-TRUK-JALAN', 'Truk tronton jalan umum 20–32 t', 'transportasi', 'angkut', 3, 'tkm', 0.105, 0.00055, 0.00012, 0.003, 1.52, 1.0e-8, 'DEFRA 2025 · GLEC v3', 2025, 'Indonesia', null],
        ['EF-TONGKANG', 'Tongkang 300 ft + tugboat', 'transportasi', 'distribusi', 3, 'tkm', 0.028, 0.00041, 0.00006, 0.001, 0.40, 1.0e-9, 'GLEC v3 inland/coastal barge', 2025, 'Indonesia', null],
        ['EF-KAPAL-CURAH', 'Kapal curah Supramax', 'transportasi', 'distribusi', 3, 'tkm', 0.0085, 0.00019, 0.00003, 0.0004, 0.12, 1.0e-9, 'IMO 4th GHG Study · GLEC v3', 2025, 'Global', null],
        ['EF-KAPAL-KONT', 'Kapal kontainer', 'transportasi', 'distribusi', 3, 'tkm', 0.016, 0.00038, 0.00005, 0.001, 0.24, 1.0e-9, 'IMO · DEFRA 2025', 2025, 'Global', null],

        // ── kemasan, air, limbah, lahan
        ['EF-BIGBAG', 'Jumbo bag polipropilena', 'kemasan', 'distribusi', 3, 'kg', 2.10, 0.0068, 0.0006, 0.090, 76.0, 1.0e-7, 'Ecoinvent 3.10', 2024, 'Global', null],
        ['EF-AIR', 'Air proses & penyiraman jalan', 'air', 'olah', 3, 'm3', 0.34, 0.0012, 0.00028, 1.45, 4.2, 1.0e-8, 'WULCA AWARE ID', 2024, 'Indonesia', null],
        ['EF-TPA', 'Limbah domestik ke TPA', 'limbah', 'akhir', 3, 'kg', 0.58, 0.0009, 0.0021, 0.015, 0.85, 2.0e-8, 'IPCC Waste Model · KLHK', 2024, 'Indonesia', null],
        ['EF-B3-INSIN', 'Limbah B3 ke insinerator berizin', 'limbah', 'akhir', 3, 'kg', 1.45, 0.0035, 0.0004, 0.010, 6.5, 2.0e-8, 'Ecoinvent 3.10', 2024, 'Indonesia', null],
        ['EF-OLI-REK', 'Oli bekas diregenerasi (kredit)', 'limbah', 'akhir', 3, 'L', -0.62, -0.0021, -0.0002, -0.005, -22.0, 0.0, 'ISO 14044 perluasan sistem', 2024, 'Indonesia', 'Kredit: angka negatif mengurangi total.'],
        ['EF-SKRAP-REK', 'Skrap logam dijual daur ulang (kredit)', 'limbah', 'akhir', 3, 'kg', -1.20, -0.0040, -0.0008, -0.020, -12.0, 0.0, 'ISO 14044 perluasan sistem · worldsteel', 2024, 'Indonesia', 'Kredit: angka negatif mengurangi total.'],
        ['EF-LAHAN', 'Pembukaan lahan hutan sekunder (hilang stok karbon)', 'lahan', 'ekstraksi', 1, 'ha', 400000.0, 0.0, 0.0, 0.0, 0.0, 0.0, 'IPCC 2006 Vol.4 · stok ±110 tC/ha', 2024, 'Indonesia', 'Diamortisasi 20 tahun: masukkan luas dibuka per tahun ÷ 20, lalu bagi per unit fungsional.'],
        ['EF-REKLAMASI', 'Reklamasi & revegetasi (serapan per ha-tahun, kredit)', 'lahan', 'akhir', 1, 'ha', -18000.0, 0.0, 0.0, 0.0, 0.0, 0.0, 'IPCC 2006 Vol.4 · ±5 tC/ha/tahun', 2024, 'Indonesia', 'Kredit: angka negatif mengurangi total.'],
        ['EF-BAKAR-BB', 'Pembakaran batubara GAR 4.200 di PLTU pembeli', 'pembakaran', 'akhir', 3, 'kg', 1.54, 0.0120, 0.0004, 0.0, 0.0, 0.0, 'IPCC 2006 Vol.2 · NCV 16 GJ/t', 2024, 'Global', 'Hanya untuk batas cradle-to-grave (Scope 3 kategori 11 pembeli).'],
    ];

    public static function pasang(): int
    {
        /* Tanpa event: hook BerpemilikPerusahaan menempelkan perusahaan
           pengguna yang sedang masuk ke baris ber-company_id NULL, dan
           pustaka bersama yang dipasang dari tombol "muat data contoh"
           akan diam-diam menjadi milik perusahaan administrator. */
        return Faktor::withoutEvents(fn () => self::pasangTanpaEvent());
    }

    private static function pasangTanpaEvent(): int
    {
        $n = 0;
        foreach (self::FAKTOR as $f) {
            [$kode, $nama, $kat, $tahap, $scope, $satuan, $gwp, $ap, $ep, $wsf, $ced, $odp, $sumber, $tahun, $wilayah, $catatan] = $f;
            $ada = Faktor::withoutGlobalScopes()->whereNull('company_id')->where('kode', $kode)->first();
            if ($ada && !$ada->perlu_cocok) continue;

            Faktor::withoutGlobalScopes()->updateOrCreate(['company_id' => null, 'kode' => $kode], [
                'nama' => $nama, 'kategori' => $kat, 'tahap_bawaan' => $tahap, 'scope' => $scope, 'satuan' => $satuan,
                'gwp' => $gwp, 'ap' => $ap, 'ep' => $ep, 'wsf' => $wsf, 'ced' => $ced, 'odp' => $odp,
                'sumber' => $sumber, 'tahun' => $tahun, 'wilayah' => $wilayah, 'catatan' => $catatan,
                'perlu_cocok' => true, 'aktif' => true,
            ]);
            $n++;
        }

        return $n;
    }
}
