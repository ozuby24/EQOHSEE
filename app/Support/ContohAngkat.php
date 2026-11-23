<?php

namespace App\Support;

use App\Models\Angkat\{Alat as AngkatAlat, Gear as AngkatGear, Jejak as AngkatJejak, Rencana as AngkatRencana};
use App\Support\Angkat\{KajianTeknis, Katalog as AngkatKatalog, Rencanakan, RiksaUji};
use Illuminate\Support\Carbon;

/**
 * Data contoh Lifting & Rigging Management.
 *
 * Register delapan pesawat angkat dan sembilan belas alat bantu dengan
 * seluruh keadaan yang perlu diperiksa tampilannya — layak, segera
 * riksa, SUKET kedaluwarsa, dikarantina sesudah riksa uji gagal, dan
 * diafkirkan — lengkap dengan riwayat riksa uji 18 bulan (berkala,
 * triwulan, khusus sesudah perbaikan). Statusnya TIDAK ditulis tangan:
 * setiap riksa dicatat lewat RiksaUji::catat dengan tanggalnya sendiri,
 * jadi status akhir register adalah hasil riwayat itu.
 *
 * Rencana pengangkatan 12 bulan ke belakang dan 14 hari ke depan,
 * disusun dari pekerjaan nyata tambang menurut komoditasnya (engine
 * HD785, vessel, crusher, trafo, tangki BBM, man-basket, liner ball
 * mill, elektroda RKEF, kiln tyre), disimulasikan dengan rumus yang
 * sama dengan form, dan dikaji 6 pilar.
 */
trait ContohAngkat
{
    private const KELAS_ANGKAT = [
        \App\Models\Angkat\Alat::class, \App\Models\Angkat\Gear::class, \App\Models\Angkat\Riksa::class,
        \App\Models\Angkat\Rencana::class, \App\Models\Angkat\Kajian::class, \App\Models\Angkat\Jejak::class,
    ];

    private function angkat(): int
    {
        $awal = $this->hitungMilik(self::KELAS_ANGKAT);
        $this->angkatIsi();

        return $this->hitungMilik(self::KELAS_ANGKAT) - $awal;
    }

    private function angkatIsi(): void
    {
        $kom = mb_strtolower((string) $this->c->commodity);
        $profil = match (true) {
            str_contains($kom, 'nikel') => 'nikel',
            str_contains($kom, 'emas') => 'emas',
            str_contains($kom, 'gamping'), str_contains($kom, 'kapur') => 'gamping',
            default => 'batubara',
        };
        $plant = ['batubara' => 'CHPP', 'nikel' => 'Smelter RKEF', 'emas' => 'Pabrik CIL', 'gamping' => 'Crusher & Kiln'][$profil];
        $userId = $this->peninjau?->id;
        $pemeriksa = [['Ir. Hendrawan Kusuma, Ahli K3 PAA', '5/2831/AS.01.02/V/2025', 'PT Sucofindo — PJK3 Riksa Uji PAA'],
                      ['Yohanes Sitompul, Ahli K3 PAA', '5/1749/AS.01.02/II/2024', 'PT Surveyor Indonesia — PJK3'],
                      ['Ratna Dewi, Ahli K3 PAA', '5/3302/AS.01.02/IX/2025', 'PT Biro Klasifikasi Indonesia — PJK3']];

        /* ─────────── register pesawat angkat ─────────── */
        // kode, nama, jenis, pabrikan, model, tahun, kapasitas, boom, lokasi, operator, SIO, nasib
        $alatDaftar = [
            ['CRN-MOB-01', 'Liebherr LTM 1100-5.2 all-terrain', 'mobile_100t', 'Liebherr', 'LTM 1100-5.2', 2019, 100, 52, 'Workshop alat berat', 'Sutrisno Hadi', 'SIO-PAA-KLS1-2023-0412', 'layak'],
            ['CRN-MOB-02', 'Tadano ATF 220G-5 all-terrain', 'mobile_250t', 'Tadano', 'ATF 220G-5', 2021, 220, 68, $plant, 'Made Arsana', 'SIO-PAA-KLS1-2022-1187', 'layak'],
            ['CRN-CRW-01', 'Kobelco CKE1350G crawler lattice', 'crawler_150t', 'Kobelco', 'CKE1350G', 2018, 135, 61, $plant, 'Rahmat Hidayat', 'SIO-PAA-KLS1-2024-0233', 'layak'],
            ['OHC-01', 'Konecranes CXT double girder', 'overhead_40t', 'Konecranes', 'CXT 40t', 2017, 40, 14, 'Engine rebuild bay', 'Andi Saputra', 'SIO-PAA-KLS2-2023-0761', 'segera'],
            ['OHC-02', 'Demag overhead crane plant', 'overhead_40t', 'Demag', 'EKKE 20t', 2020, 20, 16, $plant, 'Yusuf Maulana', 'SIO-PAA-KLS2-2024-0119', 'layak'],
            ['CRN-TRK-01', 'Unic UR-V808 truck-mounted', 'hiab_15t', 'Furukawa Unic', 'UR-V808', 2016, 15, 16, 'Pit penambangan', 'Joko Prasetyo', 'SIO-PAA-KLS3-2022-0905', 'kedaluwarsa'],
            ['CRN-MOB-03', 'Sany STC500E truck crane', 'mobile_50t', 'Sany', 'STC500E', 2015, 50, 40, 'Pelabuhan & jetty', 'Bayu Nugroho', 'SIO-PAA-KLS1-2021-0558', 'karantina'],
            ['HST-01', 'Kito ER2 electric chain hoist', 'hoist_10t', 'Kito', 'ER2-100', 2022, 10, 8, 'Workshop alat berat', 'Andi Saputra', 'SIO-PAA-KLS3-2023-1402', 'layak'],
        ];
        $alat = [];
        foreach ($alatDaftar as $i => [$kode, $nama, $jenis, $pabrik, $model, $th, $kap, $boom, $lokasi, $op, $sio, $nasib]) {
            $kat = AngkatKatalog::ALAT[$jenis];
            $a = $this->baru(AngkatAlat::class, [
                'user_id' => $userId, 'kode' => $kode, 'nama' => $nama, 'jenis' => $jenis, 'pabrikan' => $pabrik, 'model' => $model,
                'tahun' => $th, 'nomor_seri' => strtoupper(substr($pabrik, 0, 3)).'-'.(48120 + $i * 731),
                'kapasitas_t' => $kap, 'berat_crane_t' => round($kat['berat_t'] * $kap / max(1, $kat['kapasitas_t']), 1),
                'boom_maks_m' => $boom, 'berat_hook_t' => $kat['hook_t'], 'lokasi' => $lokasi,
                'operator_nama' => $op, 'operator_sio' => $sio, 'sio_berlaku' => $this->kini->copy()->addMonths(10 + $i)->toDateString(),
                'status' => 'layak',
            ]);
            $this->riwayatRiksa($a, $nasib, 12, $i, $pemeriksa, $userId);
            $alat[$kode] = $a->fresh();
        }

        /* ─────────── register alat bantu ─────────── */
        // kode, nama, jenis, wll, diameter, panjang, kaki, nasib
        $gearDaftar = [
            ['WRS-32-011', 'Wire rope sling IWRC 6×36 Ø32 mm × 6 m, 2 kaki', 'wire_rope', 18, 32, 6, 2, 'layak'],
            ['WRS-28-014', 'Wire rope sling IWRC 6×36 Ø28 mm × 8 m, 2 kaki', 'wire_rope', 13.5, 28, 8, 2, 'layak'],
            ['WRS-40-003', 'Wire rope sling IWRC 6×36 Ø40 mm × 10 m, 4 kaki', 'wire_rope', 25, 40, 10, 4, 'layak'],
            ['WRS-24-020', 'Wire rope sling IWRC 6×36 Ø24 mm × 6 m', 'wire_rope', 8.5, 24, 6, 2, 'segera'],
            ['WRS-20-031', 'Wire rope sling 6×36 Ø20 mm × 4 m', 'wire_rope', 6.5, 20, 4, 1, 'karantina'],
            ['CHN-G100-20-004', 'Alloy chain sling G100 Ø20 mm 4 kaki', 'rantai', 25, 20, 4, 4, 'layak'],
            ['CHN-G80-13-021', 'Alloy chain sling G80 Ø13 mm 2 kaki', 'rantai', 8, 13, 3, 2, 'layak'],
            ['CHN-G100-16-008', 'Alloy chain sling G100 Ø16 mm 4 kaki', 'rantai', 17, 16, 4, 4, 'karantina'],
            ['WEB-10T-044', 'Round sling poliester 10 t × 6 m', 'webbing', 10, null, 6, 1, 'layak'],
            ['WEB-5T-061', 'Webbing sling poliester 5 t × 4 m', 'webbing', 5, null, 4, 1, 'kedaluwarsa'],
            ['WEB-3T-072', 'Webbing sling poliester 3 t × 3 m', 'webbing', 3, null, 3, 1, 'afkir'],
            ['SHK-35-007', 'Shackle bow bolt type 35 t', 'shackle', 35, 57, null, 1, 'layak'],
            ['SHK-25-042', 'Shackle bow bolt type 25 t', 'shackle', 25, 51, null, 1, 'layak'],
            ['SHK-17-051', 'Shackle bow bolt type 17 t', 'shackle', 17, 44, null, 1, 'layak'],
            ['SHK-12-066', 'Shackle bow screw pin 12 t', 'shackle', 12, 38, null, 1, 'karantina'],
            ['SHK-8.5-090', 'Shackle bow bolt type 8,5 t', 'shackle', 8.5, 32, null, 1, 'segera'],
            ['SPB-30-01', 'Spreader beam 30 t × 6 m', 'spreader', 30, null, 6, 2, 'layak'],
            ['SPB-60-02', 'Lifting beam 60 t × 10 m', 'spreader', 60, null, 10, 2, 'layak'],
            ['SPB-10-03', 'Spreader beam adjustable 10 t × 4 m', 'spreader', 10, null, 4, 2, 'segera'],
        ];
        $gear = [];
        $simpan = ['wire_rope' => 'Gudang rigging workshop', 'rantai' => 'Gudang rigging workshop', 'webbing' => 'Kontainer rigging '.$plant,
                   'shackle' => 'Rak shackle workshop', 'spreader' => 'Laydown area '.$plant];
        foreach ($gearDaftar as $i => [$kode, $nama, $jenis, $wll, $dia, $pjg, $kaki, $nasib]) {
            $g = $this->baru(AngkatGear::class, [
                'kode' => $kode, 'nama' => $nama, 'jenis' => $jenis, 'wll_t' => $wll, 'sf' => AngkatKatalog::GEAR[$jenis]['sf'],
                'diameter_mm' => $dia, 'panjang_m' => $pjg, 'jumlah_kaki' => $kaki,
                'pabrikan' => ['wire_rope' => 'Kiswire', 'rantai' => 'Pewag', 'webbing' => 'SpanSet', 'shackle' => 'Crosby', 'spreader' => 'Fabrikasi lokal (gambar disahkan)'][$jenis],
                'lokasi_simpan' => $simpan[$jenis], 'status' => 'layak',
            ]);
            $this->riwayatRiksa($g, $nasib === 'afkir' ? 'karantina' : $nasib, 6, $i + 20, $pemeriksa, $userId);
            if ($nasib === 'afkir') $g->update(['status' => 'afkir', 'kode_warna' => 'MERAH']);
            $gear[$kode] = $g->fresh();
        }

        /* ─────────── rencana pengangkatan ─────────── */
        $this->rencanaAngkat($profil, $plant, $alat, $gear, $userId);
    }

    /**
     * Riwayat riksa uji satu objek, dicatat berurutan supaya status akhir
     * register adalah hasilnya. $bulan = masa berlaku uji berkala.
     */
    private function riwayatRiksa(AngkatAlat|AngkatGear $o, string $nasib, int $bulan, int $urut, array $pemeriksa, ?int $userId): void
    {
        $k = $this->kini->copy()->setTime(9, 0);
        $terakhir = match ($nasib) {
            'segera' => $k->copy()->subMonths($bulan)->addDays(12 + (int) round($this->acak('ag-sg'.$urut) * 14)),
            'kedaluwarsa' => $k->copy()->subMonths($bulan)->subDays(9 + (int) round($this->acak('ag-kd'.$urut) * 20)),
            default => $k->copy()->subDays(25 + (int) round($this->acak('ag-lk'.$urut) * ($bulan * 30 - 70))),
        };

        // Uji berkala mundur sampai ~18 bulan.
        $berkala = [];
        for ($t = $terakhir->copy(); $t->gte($k->copy()->subMonths(19)); $t->subMonths($bulan)) $berkala[] = $t->copy();
        $berkala = array_reverse($berkala);

        $kategori = $o instanceof AngkatAlat ? 'crane' : (AngkatKatalog::GEAR[$o->jenis]['uji'] ?? 'wire_rope');
        $butir = AngkatKatalog::BUTIR[$kategori];
        $kritis = array_values(array_filter($butir, fn ($b) => $b['kritis']));

        foreach ($berkala as $j => $tgl) {
            $p = $pemeriksa[($urut + $j) % count($pemeriksa)];
            $dasar = ['pemeriksa' => $p[0], 'skp_nomor' => $p[1], 'pjk3' => $p[2], 'ndt_metode' => $kategori === 'webbing' ? 'VISUAL' : ($kategori === 'crane' ? 'UT' : 'MPI')];

            // Sesekali gagal lalu diperbaiki (khusus) — bahan Pareto penyebab.
            $gagalDulu = $j < count($berkala) - 1 && $this->acak('ag-gagal'.$urut.'-'.$j) < 0.22;
            if ($gagalDulu) {
                RiksaUji::catat($o, $dasar + ['jenis' => $j === 0 ? 'pertama' : 'berkala'] + $this->temuan($kategori, $kritis, $urut + $j), $userId, $tgl->copy());
                RiksaUji::catat($o, $dasar + ['jenis' => 'khusus', 'periksa' => $this->semuaLulus($butir), 'ndt_hasil' => 'tanpa_indikasi',
                    'catatan' => 'Riksa ulang sesudah komponen diganti dan diperbaiki.'], $userId, $tgl->copy()->addDays(6));
            } else {
                RiksaUji::catat($o, $dasar + ['jenis' => $j === 0 ? 'pertama' : 'berkala', 'periksa' => $this->periksaMinor($butir, $urut + $j),
                    'ukur' => $this->ukurAman($kategori, $urut + $j), 'ndt_hasil' => 'tanpa_indikasi'], $userId, $tgl->copy());
            }

            // Pemeriksaan visual triwulan di antara uji berkala.
            $antara = $tgl->copy()->addMonths(3);
            $berikut = $berkala[$j + 1] ?? null;
            if ($antara->lt($k) && (! $berikut || $antara->lt($berikut)) && ! ($nasib === 'karantina' && $j === count($berkala) - 1)) {
                RiksaUji::catat($o, $dasar + ['jenis' => 'triwulan', 'periksa' => $this->semuaLulus($butir), 'ndt_metode' => 'VISUAL', 'ndt_hasil' => 'tanpa_indikasi'], $userId, $antara);
            }
        }

        // Karantina: temuan kritis pada pemeriksaan terbaru.
        if ($nasib === 'karantina') {
            $p = $pemeriksa[$urut % count($pemeriksa)];
            RiksaUji::catat($o, ['pemeriksa' => $p[0], 'skp_nomor' => $p[1], 'pjk3' => $p[2], 'jenis' => 'khusus',
                'ndt_metode' => $kategori === 'webbing' ? 'VISUAL' : 'MPI'] + $this->temuan($kategori, $kritis, $urut + 7),
                $userId, $k->copy()->subDays(3 + (int) round($this->acak('ag-kr'.$urut) * 12)));
        }
    }

    private function semuaLulus(array $butir): array
    {
        return array_fill_keys(array_column($butir, 'kode'), 'lulus');
    }

    /** Lulus, kadang dengan satu butir minor gagal. */
    private function periksaMinor(array $butir, int $urut): array
    {
        $p = $this->semuaLulus($butir);
        $minor = array_values(array_filter($butir, fn ($b) => ! $b['kritis']));
        if ($minor && $this->acak('ag-minor'.$urut) < 0.3) $p[$minor[$urut % count($minor)]['kode']] = 'gagal';

        return $p;
    }

    private function ukurAman(string $kategori, int $urut): array
    {
        $u = [];
        if (in_array($kategori, ['wire_rope', 'crane'], true)) $u['kawat_putus'] = (int) round($this->acak('ag-kp'.$urut) * 3);
        if (in_array($kategori, ['wire_rope', 'rantai', 'shackle', 'crane'], true)) $u['keausan_pct'] = round(1 + $this->acak('ag-ka'.$urut) * 6, 1);
        if ($kategori === 'rantai') $u['elongasi_pct'] = round(0.3 + $this->acak('ag-el'.$urut) * 1.8, 1);
        $u['defleksi_mm'] = 0;

        return $u;
    }

    /** Temuan yang membuat alat tidak layak — berbeda-beda, menurut kategori. */
    private function temuan(string $kategori, array $kritis, int $urut): array
    {
        $butir = AngkatKatalog::BUTIR[$kategori];
        $p = $this->semuaLulus($butir);
        $ukur = $this->ukurAman($kategori, $urut);
        $ndt = 'tanpa_indikasi';
        $pilih = (int) floor($this->acak('ag-tm'.$urut) * 3);

        if ($pilih === 0 && $kritis) {
            $p[$kritis[min(count($kritis) - 1, 1 + $urut % max(1, count($kritis) - 1))]['kode']] = 'gagal';
        } elseif ($pilih === 1) {
            match ($kategori) {
                'wire_rope', 'crane' => $ukur['kawat_putus'] = 7 + $urut % 4,
                'rantai' => $ukur['elongasi_pct'] = round(3.4 + $this->acak('ag-e'.$urut) * 2, 1),
                'shackle' => $ukur['keausan_pct'] = round(11 + $this->acak('ag-s'.$urut) * 4, 1),
                'webbing' => $p['WB-02'] = 'gagal',
                default => $ukur['defleksi_mm'] = 3.5,
            };
            if (in_array($kategori, ['wire_rope', 'crane'], true)) $p[$kategori === 'crane' ? 'CR-03' : 'WR-02'] = 'gagal';
        } else {
            $ndt = $kategori === 'webbing' ? 'tanpa_indikasi' : 'ada_cacat';
            if ($kategori === 'webbing') $p['WB-03'] = 'gagal';
            elseif ($kritis) $p[$kritis[min(1, count($kritis) - 1)]['kode']] = 'gagal';
        }

        return ['periksa' => $p, 'ukur' => $ukur, 'ndt_hasil' => $ndt,
                'catatan' => 'Alat ditandai MERAH dan dipindahkan ke rak karantina menunggu perbaikan atau afkir.'];
    }

    /**
     * Rencana pengangkatan: tiga sampai empat per bulan selama 12 bulan,
     * ditambah jadwal 14 hari ke depan dengan status yang beragam.
     */
    private function rencanaAngkat(string $profil, string $plant, array $alat, array $gear, ?int $userId): void
    {
        // judul, beban, jenis_alat, kode_alat|null, area, lokasi, bersih, rigging, radius, boom, rintangan, tinggi beban, tinggi rigging,
        // tanah, mat, kaki, sudut, sling, cog, spreader, sutt, angin, fasilitas, blind, wll sling, wll shackle
        $umum = [
            ['Penggantian engine Komatsu HD785-7 unit DT-117', 'komponen_alat_berat', 'mobile_100t', 'CRN-MOB-01', 'Workshop alat berat', 'Workshop bay 3', 11.2, 0.9, 16, 34, 3.0, 2.2, 3.5, 'beton', 4.0, 4, 60, 'wire_rope', 8, false, 40, 18, false, false, 13.5, 17],
            ['Pemasangan final drive PC2000-8', 'komponen_alat_berat', 'overhead_40t', 'OHC-01', 'Workshop alat berat', 'Engine rebuild bay', 6.8, 0.4, 6, 12, 1.5, 1.6, 2.5, 'beton', 4.0, 2, 60, 'rantai', 12, false, 60, 0, false, false, 8, 17],
            ['Penggantian vessel dump truck HD785 DT-122', 'komponen_alat_berat', 'mobile_250t', 'CRN-MOB-02', 'Workshop alat berat', 'Laydown workshop', 18.5, 1.4, 20, 40, 4.0, 3.2, 4.5, 'bench', 9.0, 4, 60, 'wire_rope', 10, false, 45, 22, false, false, 25, 35],
            ['Penggantian bucket PC3000 EX-03', 'komponen_alat_berat', 'crawler_150t', 'CRN-CRW-01', 'Pit penambangan', 'Pit bench RL 45', 22.0, 1.8, 14, 36, 3.0, 3.0, 4.5, 'bench', 10.0, 4, 60, 'wire_rope', 6, false, 60, 24, false, false, 25, 35],
            ['Pemasangan pulley head conveyor CV-07', 'struktur_panjang', 'mobile_100t', 'CRN-MOB-01', $plant.' / CHPP', 'Transfer tower TT-4', 7.5, 0.8, 18, 38, 9.0, 1.6, 3.5, 'beton', 4.0, 2, 90, 'wire_rope', 4, true, 30, 20, false, false, 13.5, 17],
            ['Pemasangan trafo 2.500 kVA gardu GI-3', 'trafo', 'mobile_100t', 'CRN-MOB-01', 'Power house & gardu', 'Gardu induk GI-3', 8.6, 0.6, 14, 32, 2.5, 2.8, 3.0, 'haul_road', 6.0, 4, 60, 'wire_rope', 4, false, 8, 16, false, false, 13.5, 17],
            ['Pemasangan shell tangki BBM 50 KL FT-03', 'tangki', 'tandem_2x100t', null, 'Tangki BBM & fuel station', 'Fuel farm FT-03', 12.0, 1.2, 12, 30, 2.0, 6.0, 4.0, 'bench', 9.0, 4, 60, 'wire_rope', 0, true, 35, 18, false, false, 13.5, 17],
            ['Man-basket penggantian lampu tower LT-12', 'man_basket', 'mobile_50t', null, 'Pit penambangan', 'Lighting tower LT-12', 0.45, 0.6, 14, 30, 18.0, 1.2, 2.5, 'bench', 4.0, 4, 60, 'wire_rope', 0, false, 50, 20, false, false, 6.5, 8.5],
            ['Pemasangan rangka gallery conveyor 24 m', 'struktur_panjang', 'crawler_150t', 'CRN-CRW-01', $plant.' / CHPP', 'Gallery CV-09', 9.4, 1.1, 22, 46, 12.0, 2.0, 5.0, 'bench', 10.0, 4, 90, 'wire_rope', 0, true, 26, 24, false, false, 13.5, 17],
            ['Penggantian pompa dewatering Multiflo MF-420', 'komponen_alat_berat', 'hiab_15t', 'CRN-TRK-01', 'Pit penambangan', 'Sump pit S-2', 4.2, 0.3, 6, 12, 1.0, 1.8, 2.0, 'haul_road', 1.5, 2, 60, 'rantai', 5, false, 60, 26, false, true, 8, 8.5],
            ['Penggantian genset 1.000 kVA GS-03', 'komponen_alat_berat', 'mobile_100t', 'CRN-MOB-01', 'Power house & gardu', 'Power house PH-1', 9.8, 0.7, 16, 34, 4.5, 2.4, 3.0, 'beton', 4.0, 4, 60, 'wire_rope', 6, false, 22, 15, false, false, 13.5, 17],
            ['Penggantian motor hoist workshop', 'komponen_alat_berat', 'hoist_10t', 'HST-01', 'Workshop alat berat', 'Workshop bay 1', 2.2, 0.15, 3, 8, 1.0, 0.9, 1.5, 'beton', 4.0, 1, 90, 'webbing', 0, false, 60, 0, false, false, 5, 8.5],
            ['Penggantian swing gear PC2000 EX-07', 'komponen_alat_berat', 'overhead_40t', 'OHC-01', 'Workshop alat berat', 'Engine rebuild bay', 3.6, 0.3, 5, 12, 1.2, 1.0, 2.0, 'beton', 4.0, 2, 60, 'rantai', 0, false, 60, 0, false, false, 8, 8.5],
            ['Pemasangan girder pipe rack 16,5 t di atas jalur hauling', 'struktur_panjang', 'mobile_100t', 'CRN-MOB-01', 'CHPP / pabrik pengolahan', 'Pipe rack PR-2', 16.5, 1.0, 19, 40, 6.0, 1.2, 4.0, 'haul_road', 6.0, 2, 90, 'wire_rope', 0, true, 30, 20, false, false, 13.5, 17],
            ['Pemasangan bollard & fender jetty', 'struktur_panjang', 'mobile_100t', 'CRN-MOB-01', 'Pelabuhan & jetty', 'Jetty dermaga 2', 3.8, 0.4, 22, 40, 2.0, 1.5, 2.5, 'beton', 4.0, 2, 60, 'wire_rope', 0, false, 60, 22, false, false, 8.5, 12],
        ];
        $khusus = [
            'batubara' => [
                ['Penggantian rotary breaker CHPP RB-01 (drum 28 t)', 'crusher', 'crawler_150t', 'CRN-CRW-01', 'CHPP / pabrik pengolahan', 'CHPP crusher house', 28.0, 2.0, 12, 36, 14.0, 3.5, 5.0, 'beton', 10.0, 4, 60, 'wire_rope', 8, false, 40, 20, true, false, 25, 35],
                ['Penggantian roll double roll crusher DRC-2', 'crusher', 'mobile_100t', 'CRN-MOB-01', 'CHPP / pabrik pengolahan', 'Secondary crusher', 14.5, 1.2, 12, 30, 10.0, 2.0, 4.0, 'beton', 9.0, 4, 60, 'wire_rope', 10, false, 40, 18, true, false, 25, 35],
                ['Penggantian ship loader boom section', 'struktur_panjang', 'crawler_150t', 'CRN-CRW-01', 'Pelabuhan & jetty', 'Ship loader SL-01', 18.0, 2.6, 18, 44, 12.0, 3.0, 6.0, 'beton', 10.0, 4, 90, 'wire_rope', 6, true, 30, 26, false, false, 25, 35],
            ],
            'nikel' => [
                ['Penggantian elektroda tanur RKEF line 2', 'crusher', 'overhead_40t', 'OHC-02', 'CHPP / pabrik pengolahan', 'Smelter RKEF line 2', 12.5, 0.8, 8, 16, 8.0, 3.0, 3.0, 'beton', 4.0, 2, 90, 'rantai', 0, true, 30, 0, true, false, 17, 17],
                ['Pemasangan shell rotary kiln section 5', 'crusher', 'crawler_150t', 'CRN-CRW-01', 'CHPP / pabrik pengolahan', 'Rotary kiln RK-2', 26.0, 2.8, 12, 36, 10.0, 4.5, 6.0, 'beton', 10.0, 4, 90, 'wire_rope', 4, true, 28, 20, true, false, 25, 35],
                ['Penggantian trafo tanur 60 MVA (rangka)', 'trafo', 'mobile_250t', 'CRN-MOB-02', 'Power house & gardu', 'Switchyard smelter', 26.0, 1.6, 10, 30, 3.0, 3.5, 4.0, 'beton', 9.0, 4, 60, 'wire_rope', 4, false, 7, 14, false, false, 25, 35],
            ],
            'emas' => [
                ['Penggantian liner ball mill BM-01', 'crusher', 'overhead_40t', 'OHC-02', 'CHPP / pabrik pengolahan', 'Pabrik CIL — mill house', 4.8, 0.3, 6, 16, 6.0, 1.0, 2.5, 'beton', 4.0, 2, 60, 'rantai', 10, false, 60, 0, true, true, 8, 8.5],
                ['Penggantian pinion & girth gear SAG mill', 'crusher', 'crawler_150t', 'CRN-CRW-01', 'CHPP / pabrik pengolahan', 'SAG mill', 31.0, 2.4, 13, 40, 9.0, 2.2, 5.0, 'beton', 10.0, 4, 60, 'wire_rope', 8, false, 40, 16, true, false, 25, 35],
                ['Pemasangan tangki leach CIL-4 (shell)', 'tangki', 'tandem_2x100t', null, 'CHPP / pabrik pengolahan', 'Leach tank CIL-4', 16.0, 1.4, 14, 34, 4.0, 5.0, 4.5, 'bench', 9.0, 4, 60, 'wire_rope', 0, true, 30, 18, false, false, 17, 25],
            ],
            'gamping' => [
                ['Penggantian kiln tyre section 3', 'crusher', 'crawler_150t', 'CRN-CRW-01', 'CHPP / pabrik pengolahan', 'Kiln KL-1', 32.0, 2.4, 12, 36, 8.0, 3.5, 5.0, 'beton', 10.0, 4, 60, 'wire_rope', 6, false, 32, 20, true, false, 25, 35],
                ['Penggantian mantle cone crusher CC-2', 'crusher', 'mobile_100t', 'CRN-MOB-01', 'CHPP / pabrik pengolahan', 'Secondary crusher', 9.6, 0.6, 12, 30, 8.5, 2.2, 3.5, 'beton', 4.0, 2, 60, 'rantai', 4, false, 30, 20, true, false, 17, 17],
                ['Pemasangan bucket elevator head section', 'struktur_panjang', 'mobile_250t', 'CRN-MOB-02', 'CHPP / pabrik pengolahan', 'Bucket elevator BE-2', 6.5, 0.8, 18, 52, 28.0, 2.5, 4.0, 'beton', 9.0, 2, 90, 'wire_rope', 0, true, 40, 24, false, false, 13.5, 17],
            ],
        ][$profil];

        // Satu rencana yang sengaja NO-GO: crane terlalu kecil pada radius terlalu jauh.
        $nogo = ['Pengangkatan cone crusher 28 t dengan crane 100 t pada radius 22 m', 'crusher', 'mobile_100t', 'CRN-MOB-01', 'CHPP / pabrik pengolahan', 'Primary crusher', 28.0, 2.0, 22, 40, 10.0, 3.0, 4.5, 'beton', 4.0, 4, 45, 'wire_rope', 10, false, 30, 20, true, false, 25, 35];
        $templat = array_merge($umum, $khusus);

        $operator = [['Sutrisno Hadi', 'SIO-PAA-KLS1-2023-0412'], ['Made Arsana', 'SIO-PAA-KLS1-2022-1187'], ['Rahmat Hidayat', 'SIO-PAA-KLS1-2024-0233'], ['Andi Saputra', 'SIO-PAA-KLS2-2023-0761']];
        $rigger = [['Ferdinan Siregar', 'BNSP-RIG-2024-0815'], ['Wahyu Pratama', 'BNSP-RIG-2023-1290'], ['Iwan Setiadi', 'BNSP-RIG-2025-0047']];
        $signal = ['Dimas Ardiansyah', 'Fajar Nugraha', 'Agus Salim'];
        $pengawas = ['Hendra Wijaya (POP)', 'Siti Rahmawati (POM)', 'Rudi Hartono (POP)'];
        $ktt = 'Ir. Bambang Sutrisno';

        // Jadwal: 3–4 per bulan ke belakang, lalu 9 jadwal ke depan.
        $jadwal = [];
        for ($b = 11; $b >= 0; $b--) {
            $bulan = $this->kini->copy()->subMonths($b)->startOfMonth();
            $jumlah = 3 + (int) round($this->acak('ag-jb'.$b));
            for ($x = 0; $x < $jumlah; $x++) {
                $t = $bulan->copy()->addDays(2 + $x * 8 + (int) round($this->acak("ag-hr{$b}-{$x}") * 5))->setTime(7 + ($x % 3) * 2, 30);
                if ($t->lt($this->kini->copy()->subDay())) $jadwal[] = $t;
            }
        }
        foreach ([1, 2, 3, 5, 6, 8, 10, 12, 13] as $d) $jadwal[] = $this->kini->copy()->addDays($d)->setTime(7 + ($d % 3) * 2, 30);

        $gearBoleh = collect($gear)->filter->bolehDipakai();
        $nomor = 0;
        foreach ($jadwal as $i => $tgl) {
            $depan = $tgl->gt($this->kini);
            $t = in_array($i, [5, count($jadwal) - 6], true) ? $nogo : $templat[($i * 7 + 3) % count($templat)];
            [$judul, $beban, $jenisAlat, $kodeAlat, $area, $lokasi, $bersih, $rig, $radius, $boom, $rint, $tb, $tr, $tanah, $mat, $kaki, $sudut, $sling, $cog, $spreader, $sutt, $angin, $fasilitas, $blind, $wllS, $wllK] = $t;

            // Variasi kecil per kejadian supaya titik di grafik tidak bertumpuk.
            $v = $this->acak('ag-v'.$i);
            $bersih = round($bersih * (0.92 + $v * 0.16), 2);
            $radius = round($radius + ($v - 0.5) * 3, 1);
            $angin = round($angin + ($this->acak('ag-a'.$i) - 0.4) * 10);

            $a = $kodeAlat ? ($alat[$kodeAlat] ?? null) : null;
            if ($a && $depan && ! $a->bolehDipakai()) {
                // Crane yang sedang tidak layak diganti crane lain yang layak dan cukup besar.
                $a = collect($alat)->filter(fn ($x) => $x->bolehDipakai() && ! in_array($x->jenis, ['overhead_40t', 'hoist_10t'], true) && $x->kapasitas_t >= $a->kapasitas_t)->sortBy('kapasitas_t')->first();
                $mat = max($mat, 9.0);   // crane pengganti lebih berat: mat outrigger diperbesar
            }
            if ($a && ! $depan && $a->suket_berlaku && $a->suket_berlaku->lt($tgl)) $a = null;

            $pilihGear = [];
            $sl = $gearBoleh->filter(fn ($g) => $g->jenis === $sling && $g->wll_t >= $wllS * 0.9)->sortBy('wll_t')->first()
                ?? $gearBoleh->filter(fn ($g) => in_array($g->jenis, ['wire_rope', 'rantai'], true) && $g->wll_t >= $wllS * 0.9)->sortBy('wll_t')->first();
            if ($sl) { $pilihGear[] = $sl->id; $wllS = $sl->wll_t; $sling = $sl->jenis; }
            $sh = $gearBoleh->filter(fn ($g) => $g->jenis === 'shackle' && $g->wll_t >= $wllK * 0.9)->sortBy('wll_t')->first();
            if ($sh) { $pilihGear[] = $sh->id; $wllK = $sh->wll_t; }
            if ($spreader && ($sp = $gearBoleh->filter(fn ($g) => $g->jenis === 'spreader' && $g->wll_t >= $bersih)->sortBy('wll_t')->first())) $pilihGear[] = $sp->id;

            $op = $operator[$i % count($operator)];
            if ($a?->operator_nama) $op = [$a->operator_nama, $a->operator_sio];
            $rg = $rigger[$i % count($rigger)];
            $lengkap = ! ($depan && $i % 4 === 3);

            $data = [
                'judul' => $judul, 'lokasi' => $lokasi, 'area' => $area, 'jadwal_at' => $tgl,
                'jenis_alat' => $jenisAlat, 'kategori_beban' => $beban, 'tanah' => $tanah,
                'berat_bersih_t' => $bersih, 'berat_rigging_t' => $rig, 'radius_m' => $radius, 'boom_m' => $boom,
                'tinggi_rintangan_m' => $rint, 'tinggi_beban_m' => $tb, 'tinggi_rigging_m' => $tr,
                'angin_kmh' => max(5, $angin), 'luas_mat_m2' => $mat, 'kemiringan_pct' => round(0.2 + $this->acak('ag-m'.$i) * 0.7, 1),
                'jarak_sutt_m' => $sutt, 'jumlah_kaki' => $kaki, 'sudut_sling' => $sudut, 'jenis_sling' => $sling, 'hitch' => $kaki === 1 ? 'vertikal' : 'bridle',
                'cog_offset_pct' => $cog, 'wll_sling_t' => $wllS, 'wll_shackle_t' => $wllK,
                'spreader' => $spreader, 'blind_lift' => $blind, 'di_atas_fasilitas' => $fasilitas,
                'gear' => $pilihGear,
                'operator_nama' => $op[0], 'operator_sio' => $op[1],
                'rigger_nama' => $rg[0], 'rigger_sertifikat' => $lengkap ? $rg[1] : null,
                'signalman_nama' => $signal[$i % count($signal)], 'pengawas_nama' => $lengkap ? $pengawas[$i % count($pengawas)] : null,
                'catatan' => $beban === 'man_basket' ? 'Uji coba 125% tanpa personel dilakukan 30 menit sebelum pengangkatan.' : null,
            ];

            $kolom = Rencanakan::kolom($data, $a);
            $nomor++;
            $kode = sprintf('LP-%s-%03d', $tgl->format('Y'), $nomor);

            // Status menurut waktu dan hasil.
            $status = match (true) {
                $kolom['putusan'] === 'nogo' => $depan ? 'draf' : 'ditolak',
                ! $depan => $this->acak('ag-bt'.$i) < 0.06 ? 'batal' : 'selesai',
                $tgl->lte($this->kini->copy()->addDays(3)) => 'disetujui',
                $i % 3 === 0 => 'draf',
                default => $tgl->lte($this->kini->copy()->addDays(8)) ? 'disetujui' : 'diajukan',
            };
            if ($status === 'disetujui' && $kolom['klasifikasi'] === 'kritis' && $tgl->gt($this->kini->copy()->addDays(2)) && $i % 2) $status = 'diajukan';

            $kritis = $kolom['klasifikasi'] === 'kritis';
            $disetujui = in_array($status, ['disetujui', 'selesai'], true);
            $r = $this->baru(AngkatRencana::class, $kolom + [
                'user_id' => $userId, 'kode' => $kode, 'status' => $status,
                'disetujui_oleh' => $disetujui ? ($kritis ? $ktt.' (KTT)' : $pengawas[($i + 1) % count($pengawas)]) : null,
                'disetujui_at' => $disetujui ? $tgl->copy()->subDay()->setTime(16, 10) : null,
                'catatan_persetujuan' => $status === 'ditolak' ? 'Utilisasi melampaui batas — ganti crane 250 t atau perpendek radius dengan memindahkan posisi crane.'
                    : ($disetujui && $kritis ? 'Disetujui dengan syarat: area eksklusi radius 1,5× tinggi boom, uji angkat 10 cm, angin dipantau tiap 15 menit.' : null),
                'selesai_at' => $status === 'selesai' ? $tgl->copy()->addHours(3 + (int) round($this->acak('ag-sl'.$i) * 4)) : null,
            ]);
            AngkatRencana::withoutGlobalScopes()->whereKey($r->id)->update(['created_at' => $tgl->copy()->subDays(4)->setTime(10, 0), 'updated_at' => $tgl->copy()->subDay()]);

            // Butir lapangan: lengkap untuk yang disetujui/selesai/diajukan kritis; sebagian untuk draf.
            $manual = [];
            foreach (AngkatKatalog::PILAR as $p) {
                foreach ($p['butir'] as $b) {
                    if ($b['oto']) continue;
                    if ($status === 'draf' && $this->acak('ag-mn'.$i.$b['kode']) < 0.5) continue;
                    $manual[$b['kode']] = $this->acak('ag-ms'.$i.$b['kode']) < 0.12 ? 'sebagian' : 'penuh';
                }
            }
            if ($status === 'diajukan' && ! $kritis && $i % 2) unset($manual['P6-4']);
            KajianTeknis::kaji($r, $manual, $status === 'draf' ? null : $pengawas[$i % count($pengawas)]);

            // Jejak alur.
            $jejak = [['dibuat', 'Rencana dibuat: '.$judul.'.', $tgl->copy()->subDays(4)->setTime(10, 0)]];
            if ($status !== 'draf') $jejak[] = ['ajukan', 'Rencana diajukan.', $tgl->copy()->subDays(3)->setTime(9, 20)];
            if ($status === 'ditolak') $jejak[] = ['tolak', 'Rencana ditolak: utilisasi melampaui batas.', $tgl->copy()->subDays(2)->setTime(14, 5)];
            if ($disetujui) $jejak[] = ['setujui', 'Rencana disetujui'.($kritis ? ' KTT' : '').'.', $tgl->copy()->subDay()->setTime(16, 10)];
            if ($status === 'selesai') $jejak[] = ['selesai', 'Rencana selesai dilaksanakan.', $tgl->copy()->addHours(4)];
            if ($status === 'batal') $jejak[] = ['batal', 'Rencana dibatalkan: jadwal shutdown plant digeser.', $tgl->copy()->subDays(1)];
            foreach ($jejak as [$aksi, $ringkas, $waktu]) {
                $this->baru(AngkatJejak::class, ['rencana_id' => $r->id, 'user_id' => $userId, 'aksi' => $aksi,
                    'oleh' => $aksi === 'setujui' && $kritis ? $ktt : ($aksi === 'setujui' ? $pengawas[($i + 1) % count($pengawas)] : ($this->peninjau?->name ?? 'Admin HSE')),
                    'ringkas' => $ringkas, 'created_at' => $waktu]);
            }
        }
    }
}
