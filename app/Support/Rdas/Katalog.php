<?php

namespace App\Support\Rdas;

/**
 * Acuan Rehabilitasi DAS.
 *
 * Ambang di sini adalah ambang peraturan dan pedoman penilaian, bukan
 * selera: luas kewajiban 1:1 luas PAK, usia tanaman minimal 36 bulan
 * dengan keberhasilan ≥ 75% (Permen LHK P.59/2019; Bimtek BPDAS Mahakam
 * Berau 2026), kerapatan 625 batang/ha (jarak 4 × 4 m), Petak Ukur dengan
 * intensitas sampling 5%, geotagging 100% tanaman tanpa duplikasi untuk
 * penilaian berbasis self-declare.
 */
final class Katalog
{
    public const TUMBUH_MIN = 75.0;        // berhasil
    public const TUMBUH_AMAN = 80.0;       // di bawah ini: peringatan
    public const SEHAT_HA_MIN = 500;       // batang sehat per hektar
    public const KERAPATAN = 625;          // 4 × 4 m
    public const USIA_MIN_BULAN = 36;      // usia tanaman saat dinilai
    public const IS_PCT = 5.0;             // intensitas sampling Petak Ukur
    public const LUAS_PU_HA = 0.1;         // satu PU lingkaran r = 17,8 m
    public const LOKAL_MIN_PCT = 40;       // jenis lokal berdaur panjang
    public const CEPAT_MAKS_PCT = 60;      // jenis cepat tumbuh
    public const PERINGATAN_HARI = 180;    // sebelum batas waktu penyelesaian

    /** Fase blok. Warna dipakai peta, grafik, dan lencana. */
    public const FASE = [
        'penetapan' => ['label' => 'Lokasi ditetapkan', 'pendek' => 'Penlok', 'warna' => '#94A3B8'],
        'rkp' => ['label' => 'RKP disahkan', 'pendek' => 'RKP', 'warna' => '#A78BFA'],
        'p0' => ['label' => 'P0 — penanaman', 'pendek' => 'P0', 'warna' => '#F59E0B'],
        'p1' => ['label' => 'P1 — pemeliharaan tahun ke-1', 'pendek' => 'P1', 'warna' => '#84CC16'],
        'p2' => ['label' => 'P2 — pemeliharaan tahun ke-2', 'pendek' => 'P2', 'warna' => '#22C55E'],
        'siap_nilai' => ['label' => 'Usia ≥ 36 bulan — siap dinilai', 'pendek' => 'Siap dinilai', 'warna' => '#0EA5E9'],
        'penilaian' => ['label' => 'Dalam penilaian tim', 'pendek' => 'Penilaian', 'warna' => '#2563EB'],
        'serah_terima' => ['label' => 'Diserahterimakan (BAST)', 'pendek' => 'BAST', 'warna' => '#15803D'],
    ];

    public const FUNGSI = [
        'HL' => 'Hutan Lindung', 'HP' => 'Hutan Produksi', 'HPT' => 'Hutan Produksi Terbatas',
        'HPK' => 'Hutan Produksi yang dapat Dikonversi', 'KSA' => 'Kawasan Suaka Alam', 'KPA' => 'Kawasan Pelestarian Alam',
        'APL' => 'Areal Penggunaan Lain (lahan kritis)',
    ];

    public const EKOSISTEM = [
        'daratan' => 'Daratan (lahan kering)', 'riparian' => 'Riparian / sempadan sungai',
        'mangrove' => 'Mangrove & sempadan pantai', 'gambut' => 'Gambut',
    ];

    public const KELERENGAN = ['datar' => 'Datar 0–8%', 'landai' => 'Landai 8–15%', 'agak_curam' => 'Agak curam 15–25%', 'curam' => 'Curam > 25%'];

    /** Jenis tanaman. kategori: lokal (berdaur panjang), cepat (cepat tumbuh), mpts. */
    public const JENIS = [
        'meranti' => ['nama' => 'Meranti merah', 'latin' => 'Shorea leprosula', 'kategori' => 'lokal'],
        'kapur' => ['nama' => 'Kapur', 'latin' => 'Dryobalanops lanceolata', 'kategori' => 'lokal'],
        'ulin' => ['nama' => 'Ulin', 'latin' => 'Eusideroxylon zwageri', 'kategori' => 'lokal'],
        'bengkirai' => ['nama' => 'Bengkirai', 'latin' => 'Shorea laevis', 'kategori' => 'lokal'],
        'belangeran' => ['nama' => 'Belangeran', 'latin' => 'Shorea balangeran', 'kategori' => 'lokal'],
        'nyatoh' => ['nama' => 'Nyatoh', 'latin' => 'Palaquium spp.', 'kategori' => 'lokal'],
        'eboni' => ['nama' => 'Eboni', 'latin' => 'Diospyros celebica', 'kategori' => 'lokal'],
        'matoa' => ['nama' => 'Matoa', 'latin' => 'Pometia pinnata', 'kategori' => 'lokal'],
        'rhizophora' => ['nama' => 'Bakau', 'latin' => 'Rhizophora apiculata', 'kategori' => 'lokal'],
        'avicennia' => ['nama' => 'Api-api', 'latin' => 'Avicennia marina', 'kategori' => 'lokal'],
        'sengon' => ['nama' => 'Sengon', 'latin' => 'Falcataria moluccana', 'kategori' => 'cepat'],
        'jabon' => ['nama' => 'Jabon merah', 'latin' => 'Anthocephalus macrophyllus', 'kategori' => 'cepat'],
        'gmelina' => ['nama' => 'Gmelina', 'latin' => 'Gmelina arborea', 'kategori' => 'cepat'],
        'trembesi' => ['nama' => 'Trembesi', 'latin' => 'Samanea saman', 'kategori' => 'cepat'],
        'durian' => ['nama' => 'Durian', 'latin' => 'Durio zibethinus', 'kategori' => 'mpts'],
        'petai' => ['nama' => 'Petai', 'latin' => 'Parkia speciosa', 'kategori' => 'mpts'],
        'kemiri' => ['nama' => 'Kemiri', 'latin' => 'Aleurites moluccanus', 'kategori' => 'mpts'],
        'aren' => ['nama' => 'Aren', 'latin' => 'Arenga pinnata', 'kategori' => 'mpts'],
        'cempedak' => ['nama' => 'Cempedak', 'latin' => 'Artocarpus integer', 'kategori' => 'mpts'],
    ];

    public const KATEGORI_JENIS = ['lokal' => 'Lokal berdaur panjang', 'cepat' => 'Cepat tumbuh', 'mpts' => 'MPTS (buah/getah)'];

    /** Jenis kegiatan lapangan menurut fase. */
    public const KEGIATAN = [
        'persiapan_lahan' => ['label' => 'Persiapan lahan & PLTB', 'fase' => 'P0', 'ikon' => 'shovel'],
        'pengadaan_bibit' => ['label' => 'Pengadaan & angkut bibit', 'fase' => 'P0', 'ikon' => 'sprout'],
        'pemasangan_ajir' => ['label' => 'Pemasangan ajir & lubang tanam', 'fase' => 'P0', 'ikon' => 'ruler'],
        'penanaman' => ['label' => 'Penanaman', 'fase' => 'P0', 'ikon' => 'tree-deciduous'],
        'penyiangan' => ['label' => 'Penyiangan & pendangiran', 'fase' => 'P1', 'ikon' => 'leaf'],
        'pemupukan' => ['label' => 'Pemupukan', 'fase' => 'P1', 'ikon' => 'droplets'],
        'penyulaman' => ['label' => 'Penyulaman', 'fase' => 'P1', 'ikon' => 'sprout'],
        'sekat_bakar' => ['label' => 'Sekat bakar & sarpras karhutla', 'fase' => 'P1', 'ikon' => 'flame'],
        'patroli' => ['label' => 'Patroli pengamanan', 'fase' => 'P2', 'ikon' => 'shield-check'],
        'geotagging' => ['label' => 'Geotagging tanaman', 'fase' => 'P2', 'ikon' => 'map-pin'],
        'pemasangan_patok' => ['label' => 'Pemasangan patok & papan nama', 'fase' => 'P2', 'ikon' => 'flag'],
    ];

    /** Gangguan dan tangga sanksi. tahap > 0 = sanksi administratif. */
    public const KEJADIAN = [
        'kebakaran' => ['label' => 'Kebakaran (karhutla)', 'tahap' => 0, 'ikon' => 'flame'],
        'banjir' => ['label' => 'Banjir / genangan', 'tahap' => 0, 'ikon' => 'waves'],
        'kekeringan' => ['label' => 'Kemarau panjang', 'tahap' => 0, 'ikon' => 'sun'],
        'ternak' => ['label' => 'Gangguan ternak / satwa', 'tahap' => 0, 'ikon' => 'triangle-alert'],
        'perambahan' => ['label' => 'Perambahan / konflik tenurial', 'tahap' => 0, 'ikon' => 'users'],
        'teguran_1' => ['label' => 'Teguran tertulis I Kepala BPDAS', 'tahap' => 1, 'ikon' => 'file-text'],
        'teguran_2' => ['label' => 'Teguran tertulis II Kepala BPDAS', 'tahap' => 1, 'ikon' => 'file-text'],
        'teguran_3' => ['label' => 'Teguran tertulis III Kepala BPDAS', 'tahap' => 1, 'ikon' => 'file-text'],
        'pembatalan_lokasi' => ['label' => 'Rekomendasi pembatalan lokasi ke Dirjen PDASRH', 'tahap' => 2, 'ikon' => 'ban'],
        'pencabutan_ppkh' => ['label' => 'Rekomendasi pencabutan PPKH ke Dirjen Planologi', 'tahap' => 3, 'ikon' => 'shield-x'],
        'penindakan_hukum' => ['label' => 'Rekomendasi penindakan hukum ke Dirjen Gakkum', 'tahap' => 4, 'ikon' => 'gavel'],
    ];

    /** Exit strategy bila tanaman terdampak kebakaran — kewajiban tidak gugur. */
    public const STRATEGI = [
        'replanting' => 'Pemeliharaan lanjutan (penanaman ulang)',
        'relokasi' => 'Pengusulan relokasi',
        'parsial' => 'Serah terima bertahap (parsial)',
    ];

    public const PENILAIAN = [
        'diajukan' => 'Permohonan diajukan', 'tim_dibentuk' => 'SK tim penilai terbit', 'dinilai' => 'Penilaian lapangan',
        'berhasil' => 'Berhasil — menunggu BAST', 'tidak_berhasil' => 'Tidak berhasil — pemeliharaan lanjutan',
        'ditunda' => 'Ditunda', 'serah_terima' => 'Diserahterimakan (BAST)',
    ];

    public const RURH = [
        'belum' => 'Belum diajukan', 'telaah' => 'Telaah awal', 'sidang_1' => 'Sidang I', 'sidang_2' => 'Sidang II', 'disetujui' => 'RURH disetujui',
    ];

    public const PERIODE = ['p0' => 'Akhir P0', 'p1' => 'Akhir P1', 'p2' => 'Akhir P2', 'penilaian' => 'Pra-penilaian', 'khusus' => 'Pasca gangguan'];

    public const BPDAS = ['BPDAS Mahakam Berau', 'BPDAS Barito', 'BPDAS Kahayan', 'BPDAS Sampara', 'BPDAS Palu Poso', 'BPDAS Ake Malamo', 'BPDAS Agam Kuantan', 'BPDAS Musi'];

    public static function pilihan(): array
    {
        $daftar = fn (array $a) => collect($a)->map(fn ($v, $k) => ['nilai' => $k, 'label' => is_array($v) ? ($v['label'] ?? $v['nama']) : $v] + (is_array($v) ? $v : []))->values()->all();

        return [
            'fase' => $daftar(self::FASE), 'fungsi' => $daftar(self::FUNGSI), 'ekosistem' => $daftar(self::EKOSISTEM),
            'kelerengan' => $daftar(self::KELERENGAN), 'jenis' => $daftar(self::JENIS), 'kategoriJenis' => self::KATEGORI_JENIS,
            'kegiatan' => $daftar(self::KEGIATAN), 'kejadian' => $daftar(self::KEJADIAN), 'strategi' => $daftar(self::STRATEGI),
            'penilaian' => $daftar(self::PENILAIAN), 'rurh' => $daftar(self::RURH), 'periode' => $daftar(self::PERIODE),
            'bpdas' => self::BPDAS,
            'ambang' => ['tumbuh_min' => self::TUMBUH_MIN, 'tumbuh_aman' => self::TUMBUH_AMAN, 'sehat_ha_min' => self::SEHAT_HA_MIN,
                'kerapatan' => self::KERAPATAN, 'usia_min_bulan' => self::USIA_MIN_BULAN, 'is_pct' => self::IS_PCT, 'luas_pu_ha' => self::LUAS_PU_HA,
                'lokal_min' => self::LOKAL_MIN_PCT, 'cepat_maks' => self::CEPAT_MAKS_PCT],
        ];
    }
}
