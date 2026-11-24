<?php

namespace App\Support;

use App\Models\Rdas\{Blok as RdasBlok, Kegiatan as RdasKegiatan, Kejadian as RdasKejadian, Laporan as RdasLaporan, Pantau as RdasPantau,
    Penilaian as RdasPenilaian, Petak as RdasPetak, Ppkh as RdasPpkh};
use App\Support\Rdas\{Katalog as RdasKatalog, Kepatuhan as RdasKepatuhan};
use Illuminate\Support\Carbon;

/**
 * Data contoh Rehabilitasi DAS.
 *
 * Disusun meniru keadaan yang dipaparkan BPDAS Mahakam Berau (November
 * 2026): satu PPKH jangka panjang yang lokasinya sudah ditetapkan penuh
 * dengan blok di setiap fase — diserahterimakan penuh, diserahterimakan
 * bertahap sesudah kebakaran, sedang dinilai tim (SPT 25–27 November),
 * P2, P0, dan baru RKP — serta satu PPKH jangka 5 tahun yang batas ½N + 1-
 * nya tinggal kurang dari 180 hari, sebagian lokasinya belum ditetapkan,
 * persen tumbuhnya di bawah 75%, geotag-nya masih berduplikasi, dan
 * sudah menerima teguran tertulis I.
 *
 * Petak Ukur, kegiatan, dan biaya dibangkitkan dari pola tanam dan biaya
 * satuan per hektar, bukan diketik, sehingga setiap angka di dasbor dapat
 * ditelusuri ke barisnya.
 */
trait ContohRdas
{
    private const KELAS_RDAS = [
        \App\Models\Rdas\Ppkh::class, \App\Models\Rdas\Blok::class, \App\Models\Rdas\Petak::class, \App\Models\Rdas\Pantau::class,
        \App\Models\Rdas\Kegiatan::class, \App\Models\Rdas\Penilaian::class, \App\Models\Rdas\Laporan::class, \App\Models\Rdas\Kejadian::class,
    ];

    /** Biaya satuan (Rp) dan HOK per hektar atau per batang. */
    private const BIAYA_RDAS = [
        'persiapan_lahan' => ['per_ha' => 2_600_000, 'hok_ha' => 8], 'pengadaan_bibit' => ['per_btg' => 7_500, 'hok_ha' => 1],
        'pemasangan_ajir' => ['per_ha' => 1_100_000, 'hok_ha' => 4], 'penanaman' => ['per_ha' => 3_200_000, 'hok_ha' => 10],
        'penyiangan' => ['per_ha' => 1_800_000, 'hok_ha' => 6], 'pemupukan' => ['per_ha' => 1_350_000, 'hok_ha' => 3],
        'penyulaman' => ['per_btg' => 9_500, 'hok_ha' => 2], 'sekat_bakar' => ['per_ha' => 650_000, 'hok_ha' => 2],
        'patroli' => ['per_ha' => 120_000, 'hok_ha' => 0.4], 'geotagging' => ['per_btg' => 450, 'hok_ha' => 1.5],
        'pemasangan_patok' => ['per_ha' => 250_000, 'hok_ha' => 0.5],
    ];

    private int $nomorPu = 0;

    private function rdas(): int
    {
        $awal = $this->hitungMilik(self::KELAS_RDAS);
        $this->rdasIsi();

        return $this->hitungMilik(self::KELAS_RDAS) - $awal;
    }

    private function rdasIsi(): void
    {
        $kom = mb_strtolower((string) $this->c->commodity);
        $wil = match (true) {
            str_contains($kom, 'nikel') => ['prov' => 'Sulawesi Tenggara', 'kab' => ['Konawe', 'Konawe Utara', 'Kolaka Utara'], 'bpdas' => 'BPDAS Sampara',
                'das' => [['DAS Konaweha', 'Sub-DAS Lahumbuti'], ['DAS Konaweha', 'Sub-DAS Aopa'], ['DAS Lasolo', 'Sub-DAS Lalindu'], ['DAS Lasolo', 'Sub-DAS Landawe']],
                'pemangku' => ['KPH Unit XXIII Konawe', 'KPH Laiwoi Utara'], 'lokal' => ['eboni', 'nyatoh', 'matoa'], 'kegiatan' => 'Operasi produksi pertambangan nikel'],
            str_contains($kom, 'emas') => ['prov' => 'Maluku Utara', 'kab' => ['Halmahera Utara', 'Halmahera Timur', 'Halmahera Barat'], 'bpdas' => 'BPDAS Ake Malamo',
                'das' => [['DAS Kao', 'Sub-DAS Toliwang'], ['DAS Kao', 'Sub-DAS Bobale'], ['DAS Tabobo', 'Sub-DAS Ake Jodo'], ['DAS Akelamo', 'Sub-DAS Ake Gita']],
                'pemangku' => ['KPH Halmahera Utara', 'KPH Halmahera Timur'], 'lokal' => ['matoa', 'nyatoh', 'meranti'], 'kegiatan' => 'Operasi produksi pertambangan emas'],
            str_contains($kom, 'gamping'), str_contains($kom, 'kapur') => ['prov' => 'Sumatera Barat', 'kab' => ['Padang Pariaman', 'Solok', 'Pesisir Selatan'], 'bpdas' => 'BPDAS Agam Kuantan',
                'das' => [['DAS Batang Anai', 'Sub-DAS Batang Lareh'], ['DAS Kuranji', 'Sub-DAS Batang Limau Manis'], ['DAS Batang Arau', 'Sub-DAS Lubuk Paraku'], ['DAS Batang Anai', 'Sub-DAS Batang Kandis']],
                'pemangku' => ['KPH Padang Pariaman', 'KPHL Solok'], 'lokal' => ['meranti', 'kapur', 'nyatoh'], 'kegiatan' => 'Operasi produksi pertambangan batu gamping'],
            default => ['prov' => 'Kalimantan Timur', 'kab' => ['Kutai Kartanegara', 'Kutai Timur', 'Berau'], 'bpdas' => 'BPDAS Mahakam Berau',
                'das' => [['DAS Mahakam', 'Sub-DAS Belayan'], ['DAS Mahakam', 'Sub-DAS Kedang Kepala'], ['DAS Santan', 'Sub-DAS Santan Hulu'], ['DAS Berau', 'Sub-DAS Kelay']],
                'pemangku' => ['KPH Kendilo', 'KPHP Santan', 'KPH Berau Barat'], 'lokal' => ['meranti', 'kapur', 'ulin'], 'kegiatan' => 'Operasi produksi pertambangan batubara'],
        };
        [$lat0, $lng0] = $this->titikDasar();
        $kini = $this->kini->copy()->startOfDay();
        $skala = 0.85 + $this->acak('rd-skala') * 0.3;
        $kontraktor = ['PT Rimba Lestari Nusantara', 'CV Hijau Wana Mandiri', 'Koperasi Tani Hutan Sejahtera', 'PT Wana Hijau Abadi'];

        /* ─────────── PPKH ─────────── */
        $ppkh1 = $this->ppkhContoh('PPKH-01', 'SK.'.(380 + (int) ($this->acak('rd-sk1') * 90)).'/MENLHK/SETJEN/PLA.0/3/2019', '2019-03-12', 20,
            $wil, round(1450 * $skala, 2), round(1380 * $skala, 2), 'ditetapkan', 'disetujui', round(860 * $skala, 2), round(512 * $skala, 2), 83.4);
        $ppkh2 = $this->ppkhContoh('PPKH-02', 'SK.'.(610 + (int) ($this->acak('rd-sk2') * 90)).'/MENLHK/SETJEN/PLA.0/11/2023', $kini->copy()->subYears(3)->subMonths(6)->addDays(176)->toDateString(), 5,
            $wil, round(455 * $skala, 2), round(420 * $skala, 2), 'diajukan', 'sidang_1', round(210 * $skala, 2), round(38 * $skala, 2), null);

        /* ─────────── lokasi (blok) ─────────── */
        // ppkh, kode, nama, das-idx, fungsi, ekosistem, luas, bulan sejak tanam (null = belum), punya RKP, tumbuh dasar, kontraktor, geotag (rasio, duplikat)
        $rancang = [
            [$ppkh1, 'RDAS-01', 'Hulu', 0, 'HL', 'daratan', 320, 60, true, 87.5, 0, [1.0, 0]],
            [$ppkh1, 'RDAS-02', 'Lereng', 1, 'HPT', 'daratan', 280, 47, true, 81.5, 1, [1.0, 0]],
            [$ppkh1, 'RDAS-03', 'Riparian', 1, 'HL', 'riparian', 260, 37, true, 83.0, 2, [1.0, 0]],
            [$ppkh1, 'RDAS-04', 'Koridor', 2, 'HP', 'daratan', 240, 24, true, 80.5, 0, [0.62, 0]],
            [$ppkh1, 'RDAS-05', 'Sempadan', 3, 'HL', 'riparian', 180, 11, true, 88.0, 3, [0.0, 0]],
            [$ppkh1, 'RDAS-06', 'Pengembangan', 3, 'APL', 'daratan', 100, null, true, null, 1, [0.0, 0]],
            [$ppkh2, 'RDAS-07', 'Perbukitan', 2, 'HPT', 'daratan', 160, 33, true, 71.0, 1, [0.92, 1840]],
            [$ppkh2, 'RDAS-08', 'Tambahan', 0, 'HP', 'daratan', 140, null, false, null, 2, [0.0, 0]],
        ];
        $blok = [];
        foreach ($rancang as $i => [$pp, $kode, $nm, $di, $fungsi, $eko, $luas, $umur, $rkp, $dasar, $ki, $geo]) {
            $luas = round($luas * $skala, 2);
            [$das, $subDas] = $wil['das'][$di];
            $tanam = $umur === null ? null : $kini->copy()->subMonths($umur)->startOfMonth()->addDays(9 + (int) ($this->acak('rd-tg'.$i) * 12));
            $sk = ($tanam ?? $kini->copy()->subMonths(5))->copy()->subMonths($rkp ? 8 : 0)->subDays(20);
            $komposisi = $this->komposisiContoh($wil['lokal'], $i, $kode === 'RDAS-07');
            $kerapatan = $eko === 'riparian' ? 833 : 625;
            [$latB, $lngB] = [$lat0 + 0.11 + ($i % 4) * 0.055 + ($i >= 4 ? 0.07 : 0), $lng0 - 0.16 + intdiv($i, 2) * 0.06 + ($i % 2) * 0.035];
            $b = $this->baru(RdasBlok::class, [
                'ppkh_id' => $pp->id, 'kode' => $kode, 'nama' => $nm.' '.str_replace(['Sub-DAS ', 'DAS '], '', $subDas).' — '.['Desa Long Beleh', 'Desa Muara Kaman Ulu', 'Desa Sebuntal', 'Desa Tanjung Batu', 'Desa Sepaso', 'Desa Merasa', 'Desa Kelinjau', 'Desa Batu Lepoq'][$i],
                'sk_penlok_nomor' => 'SK.'.(200 + $i * 37).'/PDASRH/RHL/DAS.'.($i + 1).'/'.$sk->format('n/Y'), 'sk_penlok_tanggal' => $sk->toDateString(),
                'das' => $das, 'sub_das' => $subDas, 'kabupaten' => $wil['kab'][$i % count($wil['kab'])], 'kecamatan' => null,
                'desa' => ['Long Beleh', 'Muara Kaman Ulu', 'Sebuntal', 'Tanjung Batu', 'Sepaso', 'Merasa', 'Kelinjau', 'Batu Lepoq'][$i],
                'fungsi_kawasan' => $fungsi, 'pemangku' => $fungsi === 'APL' ? 'Pemerintah Desa' : $wil['pemangku'][$i % count($wil['pemangku'])],
                'ekosistem' => $eko, 'luas_ha' => $luas, 'jarak_tanam' => $kerapatan === 833 ? '3 × 4 m' : '4 × 4 m', 'kerapatan' => $kerapatan,
                'komposisi' => $komposisi,
                'rkp_nomor' => $rkp ? 'RKP-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT).'/'.$pp->kode.'/'.$sk->copy()->addMonths(5)->format('Y') : null,
                'rkp_disahkan_at' => $rkp ? $sk->copy()->addMonths($tanam ? 5 : 2)->toDateString() : null,
                'tanggal_tanam' => $tanam?->toDateString(), 'kontraktor' => $kontraktor[$ki],
                'anggaran_rp' => round($luas * (24_500_000 + $this->acak('rd-ang'.$i) * 3_500_000), -6),
                'lat' => round($latB, 6), 'lng' => round($lngB, 6),
                'geotag_total' => (int) round($luas * $kerapatan * $geo[0]), 'geotag_duplikat' => $geo[1],
            ]);
            $blok[$kode] = ['m' => $b, 'tanam' => $tanam, 'dasar' => $dasar, 'umur' => $umur, 'pusat' => [$latB, $lngB]];
        }

        /* ─────────── petak, Petak Ukur, kegiatan ─────────── */
        foreach ($blok as $kode => $x) {
            $b = $x['m'];
            $np = max(2, (int) ceil($b->luas_ha / 42));
            $kolom = (int) ceil(sqrt($np));
            $sisi = sqrt($b->luas_ha / $np * 10_000) / 111_320;          // derajat
            $luasPetak = $this->bagiLuas($b->luas_ha, $np, $kode);
            for ($j = 0; $j < $np; $j++) {
                $cLat = $x['pusat'][0] - intdiv($j, $kolom) * $sisi * 1.08;
                $cLng = $x['pusat'][1] + ($j % $kolom) * $sisi * 1.08;
                $petak = $this->baru(RdasPetak::class, [
                    'blok_id' => $b->id, 'kode' => sprintf('P%02d', $j + 1), 'luas_ha' => $luasPetak[$j],
                    'kelerengan' => ['datar', 'landai', 'landai', 'agak_curam', 'curam'][(int) floor($this->acak("rd-lr{$kode}{$j}") * 5)],
                    'poligon' => $this->poligonContoh($cLat, $cLng, $sisi * sqrt($luasPetak[$j] / ($b->luas_ha / $np)), "{$kode}{$j}"),
                    'lat' => round($cLat, 7), 'lng' => round($cLng, 7),
                    'bibit_tanam' => $x['tanam'] ? (int) round($luasPetak[$j] * $b->kerapatan) : 0,
                    'bibit_sulam' => 0,
                ]);
                if ($x['tanam']) $this->pantauContoh($petak, $b, $x['tanam'], $x['dasar'], $j, $kini);
            }
            if ($x['tanam']) $this->kegiatanContoh($b, $x['tanam'], $kini);
        }

        /* ─────────── penilaian & serah terima ─────────── */
        $nilaiTim = ['Kepala '.$wil['bpdas'], 'Kepala '.$wil['bpdas'].' (Plh.)'];
        $b1 = $blok['RDAS-01']['m'];
        $this->penilaianContoh($b1, 1, $b1->luas_ha, $blok['RDAS-01']['tanam']->copy()->addMonths(38), $nilaiTim[0], 'serah_terima', 84.6, 538);
        $b2 = $blok['RDAS-02']['m'];
        $tahap1 = round($b2->luas_ha * 0.54, 2);
        $this->penilaianContoh($b2, 1, $tahap1, $blok['RDAS-02']['tanam']->copy()->addMonths(37), $nilaiTim[0], 'serah_terima', 82.1, 516,
            'Serah terima bertahap: '.number_format($b2->luas_ha - $tahap1, 2, ',', '.').' ha terdampak kebakaran dipelihara lanjutan (penanaman ulang).');
        $b3 = $blok['RDAS-03']['m'];
        $this->penilaianContoh($b3, 1, $b3->luas_ha, $kini->copy()->subDays(28), $nilaiTim[1], 'tim_dibentuk', null, null,
            'SPT tim penilai 25–27 '.$kini->translatedFormat('F Y').'.', $kini->copy()->addDay(), $kini->copy()->addDays(3));

        /* ─────────── laporan semesteran ─────────── */
        foreach ([$ppkh1, $ppkh2] as $pi => $pp) {
            for ($th = (int) $pp->tanggal_sk->format('Y'); $th <= (int) $kini->format('Y'); $th++) {
                foreach ([1, 2] as $s) {
                    $batas = RdasKepatuhan::batasLaporan($th, $s);
                    if ($batas->lt($pp->tanggal_sk) || $batas->gt($kini->copy()->addMonths(8))) continue;
                    $lewat = $batas->lt($kini);
                    $telat = $this->acak("rd-lap{$pi}{$th}{$s}") < 0.12;
                    $belum = $pi === 1 && $th === (int) $kini->format('Y') && $s === 1;   // PPKH-02 belum melapor semester I
                    $kirim = ! $lewat || $belum ? null : $batas->copy()->subDays($telat ? -12 : 4 + (int) ($this->acak("rd-lk{$pi}{$th}{$s}") * 20));
                    $this->baru(RdasLaporan::class, ['ppkh_id' => $pp->id, 'tahun' => $th, 'semester' => $s, 'batas' => $batas->toDateString(),
                        'dikirim_at' => $kirim?->toDateString(), 'tanda_terima' => $kirim ? 'TT-'.$th.'/'.str_pad((string) (120 + $s * 7 + $pi * 31), 4, '0', STR_PAD_LEFT).'/BPDAS' : null]);
                }
            }
        }

        /* ─────────── kejadian & sanksi ─────────── */
        $bakar = $blok['RDAS-02']['tanam']->copy()->addMonths(33);
        $this->baru(RdasKejadian::class, ['ppkh_id' => $ppkh1->id, 'blok_id' => $b2->id, 'user_id' => $this->peninjau?->id, 'jenis' => 'kebakaran',
            'tanggal' => $bakar->toDateString(), 'luas_terdampak_ha' => round(($b2->luas_ha - $tahap1) * 0.5, 2),
            'uraian' => 'Kebakaran permukaan merambat dari kebun warga pada puncak kemarau; regu karhutla dan 2 unit pompa portabel dikerahkan, api padam dalam 9 jam.',
            'strategi' => 'parsial', 'ba_nomor' => 'BA.07/RDAS/'.$bakar->format('m/Y'), 'dilaporkan_at' => $bakar->copy()->addDays(2)->toDateString(), 'status' => 'selesai']);
        $this->baru(RdasKejadian::class, ['ppkh_id' => $ppkh1->id, 'blok_id' => $blok['RDAS-04']['m']->id, 'user_id' => $this->peninjau?->id, 'jenis' => 'ternak',
            'tanggal' => $kini->copy()->subDays(24)->toDateString(), 'luas_terdampak_ha' => 3.5,
            'uraian' => 'Sapi warga masuk petak P03–P04 dan merusak ± 1.900 tanaman muda; pagar kawat diperbaiki bersama kelompok tani.',
            'dilaporkan_at' => $kini->copy()->subDays(21)->toDateString(), 'status' => 'ditangani']);
        $this->baru(RdasKejadian::class, ['ppkh_id' => $ppkh2->id, 'user_id' => $this->peninjau?->id, 'jenis' => 'teguran_1',
            'tanggal' => $kini->copy()->subMonths(6)->toDateString(),
            'uraian' => 'Teguran tertulis I Kepala '.$wil['bpdas'].': '.number_format($ppkh2->kewajiban_ha - $blok['RDAS-07']['m']->luas_ha - $blok['RDAS-08']['m']->luas_ha, 1, ',', '.').' ha belum diajukan penetapan lokasinya dan persen tumbuh RDAS-07 di bawah 75%.',
            'ba_nomor' => 'S.'.(400 + (int) ($this->acak('rd-teg') * 300)).'/BPDAS/RHL/'.$kini->copy()->subMonths(6)->format('n/Y'), 'status' => 'ditangani']);

        foreach ($blok as $x) RdasKepatuhan::sinkronFase($x['m']->fresh());
    }

    private function ppkhContoh(string $kode, string $sk, string $tgl, int $jangka, array $wil, float $luas, float $pak, string $lokasi, string $rurh, float $terganggu, float $direklamasi, ?float $nilai): RdasPpkh
    {
        return $this->baru(RdasPpkh::class, [
            'user_id' => $this->peninjau?->id, 'kode' => $kode, 'nomor_sk' => $sk, 'tanggal_sk' => $tgl, 'jangka_tahun' => $jangka,
            'berlaku_sampai' => Carbon::parse($tgl)->addYears($jangka)->toDateString(), 'tujuan' => 'komersial', 'kegiatan' => $wil['kegiatan'],
            'provinsi' => $wil['prov'], 'kabupaten' => $wil['kab'][0], 'bpdas' => $wil['bpdas'], 'luas_ppkh_ha' => $luas,
            'nomor_pak' => 'PAK.'.(100 + strlen($sk) * 3).'/PKTL/PPKH/PLA.0/'.Carbon::parse($tgl)->addMonths(4)->format('n/Y'), 'luas_pak_ha' => $pak, 'rasio' => 1,
            'kewajiban_ha' => $pak, 'batas_selesai' => RdasKepatuhan::batasSelesai($tgl, $jangka)->toDateString(), 'status_lokasi' => $lokasi,
            'rurh_status' => $rurh, 'luas_terganggu_ha' => $terganggu, 'luas_direklamasi_ha' => $direklamasi, 'nilai_reklamasi' => $nilai,
        ]);
    }

    /** Komposisi jenis: lokal berdaur panjang, cepat tumbuh, dan MPTS. RDAS-07 sengaja kurang jenis lokal. */
    private function komposisiContoh(array $lokal, int $i, bool $kurangLokal): array
    {
        if ($kurangLokal) {
            return [['jenis' => $lokal[0], 'persen' => 20], ['jenis' => $lokal[1], 'persen' => 10], ['jenis' => 'sengon', 'persen' => 35],
                    ['jenis' => 'jabon', 'persen' => 15], ['jenis' => 'durian', 'persen' => 10], ['jenis' => 'kemiri', 'persen' => 10]];
        }
        $cepat = [['sengon', 'jabon'], ['jabon', 'gmelina'], ['sengon', 'trembesi']][$i % 3];
        $mpts = [['durian', 'petai'], ['durian', 'cempedak'], ['kemiri', 'aren'], ['petai', 'durian']][$i % 4];

        return [['jenis' => $lokal[0], 'persen' => 25], ['jenis' => $lokal[1], 'persen' => 15], ['jenis' => $lokal[2], 'persen' => 10],
                ['jenis' => $cepat[0], 'persen' => 20], ['jenis' => $cepat[1], 'persen' => 10], ['jenis' => $mpts[0], 'persen' => 10], ['jenis' => $mpts[1], 'persen' => 10]];
    }

    /** Membagi luas blok ke petak dengan variasi kecil; jumlahnya tepat sama. */
    private function bagiLuas(float $luas, int $np, string $kunci): array
    {
        $bobot = array_map(fn ($j) => 0.8 + $this->acak("rd-bg{$kunci}{$j}") * 0.4, range(0, $np - 1));
        $jml = array_sum($bobot);
        $hasil = array_map(fn ($w) => round($luas * $w / $jml, 2), $bobot);
        $hasil[$np - 1] = round($luas - array_sum(array_slice($hasil, 0, $np - 1)), 2);

        return $hasil;
    }

    /** Poligon segi enam tak beraturan di sekitar pusat — mirip batas petak hasil delineasi. */
    private function poligonContoh(float $lat, float $lng, float $sisi, string $kunci): array
    {
        $titik = [];
        for ($k = 0; $k < 6; $k++) {
            $t = M_PI / 3 * $k + M_PI / 6;
            $r = $sisi * 0.56 * (0.86 + $this->acak("rd-pg{$kunci}{$k}") * 0.22);
            $titik[] = [round($lat + $r * sin($t), 7), round($lng + $r * cos($t) * 1.12, 7)];
        }

        return $titik;
    }

    /** Petak Ukur di akhir setiap periode yang sudah lewat. */
    private function pantauContoh(RdasPetak $petak, RdasBlok $b, Carbon $tanam, float $dasar, int $j, Carbon $kini): void
    {
        $periode = [['p0', 11, 4.0], ['p1', 23, 1.5], ['p2', 35, 0.0], ['penilaian', 37, 0.3]];
        $jumlahPu = (int) max(1, ceil($petak->luas_ha * RdasKatalog::IS_PCT / 100 / RdasKatalog::LUAS_PU_HA));
        $perPu = (int) round($b->kerapatan * RdasKatalog::LUAS_PU_HA);
        $tinggi = ['p0' => 55, 'p1' => 140, 'p2' => 245, 'penilaian' => 290];
        // Petak tertentu sengaja lemah: lereng curam / kena ternak / riparian tergenang.
        $lemah = ($b->kode === 'RDAS-07' && $j % 2 === 0) || ($b->kode === 'RDAS-02' && $j >= 4) || ($b->kode === 'RDAS-04' && in_array($j, [2, 3], true));
        foreach ($periode as [$per, $bulan, $tambah]) {
            $tgl = $tanam->copy()->addMonths($bulan)->addDays((int) ($this->acak("rd-pt{$b->kode}{$petak->kode}{$per}") * 15));
            if ($tgl->gt($kini)) break;
            $pct = min(97.0, $dasar + $tambah + ($this->acak("rd-pv{$b->kode}{$petak->kode}{$per}") - 0.5) * 7 - ($lemah ? 11 : 0));
            $diamati = $jumlahPu * $perPu;
            $hidup = (int) round($diamati * $pct / 100);
            $merana = (int) round($hidup * (0.025 + $this->acak("rd-mr{$b->kode}{$petak->kode}{$per}") * 0.03));
            $h = RdasKepatuhan::pantau($diamati, $hidup - $merana, $merana, null, $b->kerapatan, $petak->luas_ha, $jumlahPu);
            $this->nomorPu++;
            $this->baru(RdasPantau::class, [
                'petak_id' => $petak->id, 'blok_id' => $b->id, 'user_id' => $this->peninjau?->id,
                'kode' => sprintf('PU-%s-%04d', $tgl->format('Y'), $this->nomorPu), 'tanggal' => $tgl->toDateString(), 'periode' => $per,
                'jumlah_pu' => $jumlahPu, 'luas_pu_ha' => RdasKatalog::LUAS_PU_HA, 'intensitas_pct' => $h['intensitas_pct'],
                'diamati' => $diamati, 'sehat' => $h['sehat'], 'merana' => $merana, 'mati' => $h['mati'],
                'persen_tumbuh' => $h['persen_tumbuh'], 'sehat_ha' => $h['sehat_ha'], 'hidup_ha' => $h['hidup_ha'],
                'tinggi_cm' => round($tinggi[$per] * (0.85 + $this->acak("rd-tn{$b->kode}{$petak->kode}{$per}") * 0.3), 1),
                'diameter_mm' => round($tinggi[$per] * 0.13 * (0.85 + $this->acak("rd-dm{$b->kode}{$petak->kode}{$per}") * 0.3), 1),
                'status' => $h['status'], 'kebutuhan_sulam' => $h['kebutuhan_sulam'],
                'pengamat' => ['Andi Pratama, S.Hut.', 'Siti Nurhaliza, S.Hut.', 'Rizal Effendi'][(crc32($b->kode.$petak->kode) + strlen($per)) % 3],
            ]);
            if ($h['kebutuhan_sulam'] > 0 && $per !== 'penilaian') {
                RdasPetak::withoutGlobalScopes()->whereKey($petak->id)->increment('bibit_sulam', (int) round($h['kebutuhan_sulam'] * 0.8));
            }
        }
        RdasKepatuhan::sinkronPetak($petak->fresh());
    }

    /** Log kegiatan per fase dengan biaya satuan; patroli bulanan 12 bulan terakhir. */
    private function kegiatanContoh(RdasBlok $b, Carbon $tanam, Carbon $kini): void
    {
        $luas = $b->luas_ha;
        $batang = (int) round($luas * $b->kerapatan);
        $rencana = [
            ['persiapan_lahan', -45, 'P0', $luas, 0], ['pengadaan_bibit', -20, 'P0', 0, $batang], ['pemasangan_ajir', -12, 'P0', $luas, 0],
            ['penanaman', 0, 'P0', $luas, $batang], ['penyiangan', 120, 'P0', $luas, 0], ['pemupukan', 150, 'P0', $luas, 0],
            ['penyiangan', 400, 'P1', $luas, 0], ['penyulaman', 420, 'P1', $luas * 0.15, (int) ($batang * 0.12)], ['pemupukan', 450, 'P1', $luas, 0],
            ['sekat_bakar', 560, 'P1', $luas, 0], ['penyiangan', 640, 'P1', $luas, 0],
            ['penyiangan', 760, 'P2', $luas, 0], ['penyulaman', 790, 'P2', $luas * 0.08, (int) ($batang * 0.06)], ['geotagging', 980, 'P2', $luas, $batang],
            ['pemasangan_patok', 1010, 'P2', $luas, 0], ['penyiangan', 1040, 'P2', $luas, 0],
        ];
        // Patroli pengamanan bulanan selama blok belum diserahterimakan.
        for ($m = 11; $m >= 0; $m--) {
            $t = $kini->copy()->subMonths($m)->startOfMonth()->addDays(6 + (crc32($b->kode) % 7));
            if ($t->gt($tanam->copy()->addDays(60)) && $t->lt($kini)) $rencana[] = ['patroli', (int) $tanam->diffInDays($t), 'X', $luas, 0];
        }
        foreach ($rencana as $k => [$jenis, $hari, $fase, $l, $bibit]) {
            $tgl = $tanam->copy()->addDays($hari + (int) ($this->acak("rd-kg{$b->kode}{$k}") * 10));
            if ($tgl->gt($kini)) continue;
            if ($b->kode === 'RDAS-01' && $tgl->gt($tanam->copy()->addMonths(44))) continue;     // sudah BAST
            $umurBln = $tanam->diffInMonths($tgl);
            $faseNyata = $fase !== 'X' ? $fase : ($umurBln < 12 ? 'P0' : ($umurBln < 24 ? 'P1' : 'P2'));
            $u = self::BIAYA_RDAS[$jenis];
            $biaya = isset($u['per_btg']) ? $bibit * $u['per_btg'] : $l * $u['per_ha'];
            $baru = $tgl->gte($kini->copy()->subDays(10));
            $this->baru(RdasKegiatan::class, [
                'blok_id' => $b->id, 'user_id' => $baru ? ($this->pengaju?->id ?? $this->peninjau?->id) : $this->peninjau?->id,
                'tanggal' => $tgl->toDateString(), 'jenis' => $jenis, 'fase' => $faseNyata, 'luas_ha' => round($l, 2), 'bibit' => $bibit,
                'hok' => (int) round(max(1, $u['hok_ha'] * max($l, $bibit / max(1, $b->kerapatan)))), 'biaya_rp' => round($biaya, -3),
                'pelaksana' => $b->kontraktor, 'lat' => $b->lat, 'lng' => $b->lng,
                'status' => $baru ? 'diajukan' : 'diverifikasi', 'verifikator' => $baru ? null : ($this->peninjau?->name ?? 'Petugas Lingkungan'),
                'diverifikasi_at' => $baru ? null : $tgl->copy()->addDays(3),
            ]);
        }
    }

    private function penilaianContoh(RdasBlok $b, int $tahap, float $luas, Carbon $ajuan, string $ketua, string $status, ?float $tumbuh, ?int $sehat,
                                     ?string $catatan = null, ?Carbon $mulai = null, ?Carbon $selesai = null): void
    {
        $sk = $ajuan->copy()->addDays(13 + (int) ($this->acak('rd-sk'.$b->kode) * 30));
        if ($sk->gt($this->kini)) $sk = $this->kini->copy()->subDays(3);
        $nilai = $sk->copy()->addDays(30);
        $bast = $nilai->copy()->addDays(45 + (int) ($this->acak('rd-bast'.$b->kode) * 40));
        $this->baru(RdasPenilaian::class, [
            'blok_id' => $b->id, 'kode' => sprintf('PNL-%s-%03d', $ajuan->format('Y'), $b->id % 1000), 'tahap' => $tahap,
            'luas_diajukan_ha' => $luas, 'luas_berhasil_ha' => $status === 'serah_terima' ? $luas : null, 'status' => $status,
            'diajukan_at' => $ajuan->toDateString(), 'sk_tim_nomor' => 'SK.'.(30 + crc32($b->kode) % 70).'/TKTRH/RHL/'.$sk->format('n/Y'), 'sk_tim_at' => $sk->toDateString(),
            'jadwal_mulai' => ($mulai ?? $nilai)->toDateString(), 'jadwal_selesai' => ($selesai ?? $nilai->copy()->addDays(2))->toDateString(),
            'ketua_tim' => $ketua, 'persen_tumbuh' => $tumbuh, 'sehat_ha' => $sehat,
            'prasyarat' => RdasKepatuhan::kelayakan($b)['cek'],
            'bast_nomor' => $status === 'serah_terima' ? 'BAST.'.(10 + crc32($b->kode) % 80).'/PDASRH/'.$bast->format('n/Y') : null,
            'bast_at' => $status === 'serah_terima' ? $bast->toDateString() : null, 'catatan' => $catatan,
        ]);
    }
}
