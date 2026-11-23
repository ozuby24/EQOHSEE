<?php

namespace App\Support;

use App\Models\EnergyProduction;
use App\Models\Lca\{Faktor as LcaFaktor, Jejak as LcaJejak, Studi as LcaStudi};
use App\Models\Perancah\{Unit as PerancahUnit};
use App\Support\Lca\{Mesin as LcaMesin, Pustaka as LcaPustaka};
use App\Support\Perancah\{Katalog as PerancahKatalog, Penanda};
use Illuminate\Support\Carbon;

/**
 * Data contoh Scaffolding Management dan Life Cycle Assessment.
 *
 * Dipisah dari DataContoh supaya berkas induknya tidak bertambah dua
 * ratus baris lagi; dipakai sebagai trait sehingga memakai pembantu dan
 * batas perusahaan yang SAMA (`baru`, `acak`, `$this->c`).
 *
 * Perancah: delapan belas unit di zona tambang dengan seluruh keadaan
 * yang perlu diperiksa tampilannya — hijau, kuning bersyarat, merah
 * karena kajian (ikatan kurang, tanah lunak), merah karena inspeksi
 * gagal, kedaluwarsa, jatuh tempo, dan yang sudah dibongkar — lengkap
 * dengan riwayat inspeksi mingguan 12 minggu untuk tren dan Pareto.
 *
 * LCA: empat studi per perusahaan menurut komoditasnya. Inventori studi
 * utama DITARIK dari data Energy Performance tahun lalu (solar, listrik,
 * ANFO, produksi), lalu dilengkapi baris material, angkutan, limbah,
 * dan lahan — sehingga angka per ton di LCA dan intensitas di Energy
 * berasal dari catatan yang sama.
 */
trait ContohPerancahLca
{
    /* ═══════════════════ Scaffolding ═══════════════════ */

    /** Jumlah baris milik perusahaan ini pada kelas-kelas yang disebut. */
    private function hitungMilik(array $kelas): int
    {
        return array_sum(array_map(fn ($k) => $k::withoutGlobalScopes()->where('company_id', $this->c->id)->count(), $kelas));
    }

    private const KELAS_PERANCAH = [
        \App\Models\Perancah\Unit::class, \App\Models\Perancah\Hitung::class, \App\Models\Perancah\Kajian::class,
        \App\Models\Perancah\Inspeksi::class, \App\Models\Perancah\Tag::class,
    ];

    private const KELAS_LCA = [
        \App\Models\Lca\Faktor::class, \App\Models\Lca\Studi::class, \App\Models\Lca\Inventori::class,
        \App\Models\Lca\Hasil::class, \App\Models\Lca\Skenario::class, \App\Models\Lca\Jejak::class,
    ];

    /**
     * Jumlah yang dilaporkan dihitung dari BASIS DATA, bukan dijumlah
     * tangan: pemeriksaan penumpukan membandingkannya persis dengan yang
     * dibuang pada pemuatan berikutnya, dan hitungan tangan atas rantai
     * kaji → inspeksi → tag selalu meleset beberapa baris.
     */
    private function perancah(): int
    {
        $awal = $this->hitungMilik(self::KELAS_PERANCAH);
        $this->perancahIsi();

        return $this->hitungMilik(self::KELAS_PERANCAH) - $awal;
    }

    private function lca(): int
    {
        $awal = $this->hitungMilik(self::KELAS_LCA);
        $this->lcaIsi();

        return $this->hitungMilik(self::KELAS_LCA) - $awal;
    }

    private function perancahIsi(): void
    {
        $kom = mb_strtolower((string) $this->c->commodity);
        $zonaProses = match (true) {
            str_contains($kom, 'nikel') => 'Smelter & Pengolahan',
            str_contains($kom, 'emas') => 'Pabrik Pengolahan (CIL)',
            str_contains($kom, 'gamping'), str_contains($kom, 'kapur') => 'Crusher & Kiln',
            default => 'CHPP & Crusher',
        };
        [$lat0, $lng0] = $this->titikDasar();
        $pusat = [
            $zonaProses => [0.0000, 0.0000], 'Konveyor Overland' => [0.0042, 0.0061], 'Workshop & Fuel Bay' => [-0.0051, -0.0038],
            'Pelabuhan & Jetty' => [0.0095, 0.0128], 'Power House' => [-0.0018, 0.0057], 'Fuel Farm & Tangki' => [-0.0062, 0.0019],
            'Kantor, Mess & Fasilitas' => [-0.0089, -0.0081],
        ];

        // judul, zona, peralatan, jenis, kelas, P, L, T, dek, tanah, ikatan, angin, guard, toe, outrigger, skenario
        $daftar = [
            ['Penggantian liner chute transfer TP-3', $zonaProses, 'TP-3', 'ringlock', 'berat', 6.0, 1.8, 9.5, 2, 'beton', 4, 22, true, true, false, 'hijau'],
            ['Perbaikan screen deck primary crusher', $zonaProses, 'CR-01', 'tube_coupler', 'berat', 4.8, 1.5, 6.0, 1, 'beton', 2, 18, true, true, false, 'hijau'],
            ['Perawatan sprocket stacker-reclaimer', $zonaProses, 'SR-01', 'ringlock', 'sedang', 7.0, 1.8, 12.0, 2, 'beton', 6, 24, true, true, false, 'jatuh_tempo'],
            ['Pemasangan sensor level silo produk', $zonaProses, 'SILO-2', 'ringlock', 'ringan', 4.0, 1.5, 18.0, 3, 'beton', 10, 26, true, true, false, 'kuning'],
            ['Pengelasan rangka hopper ROM', $zonaProses, 'ROM-1', 'ringlock', 'sedang', 8.0, 2.0, 7.0, 2, 'beton', 4, 20, true, true, false, 'gagal'],
            ['Pemeliharaan idler & belt conveyor CV-07', 'Konveyor Overland', 'CV-07', 'frame', 'sedang', 9.0, 1.2, 4.0, 1, 'padat', 2, 25, true, true, false, 'hijau'],
            ['Akses inspeksi gantry conveyor km 4', 'Konveyor Overland', 'CV-OL4', 'ringlock', 'sedang', 12.0, 1.5, 14.0, 2, 'padat', 6, 30, true, true, false, 'tidak_layak'],
            ['Pemasangan pompa & pipa settling pond', 'Konveyor Overland', 'SP-03', 'tube_coupler', 'berat', 6.0, 1.5, 5.5, 2, 'lunak', 2, 20, true, true, false, 'tidak_layak'],
            ['Pengecatan tangki solar FT-02', 'Fuel Farm & Tangki', 'FT-02', 'ringlock', 'ringan', 10.0, 1.2, 12.0, 1, 'beton', 6, 20, true, true, false, 'hijau'],
            ['Kalibrasi level gauge tangki FT-01', 'Fuel Farm & Tangki', 'FT-01', 'ringlock', 'ringan', 6.0, 1.2, 11.0, 1, 'beton', 6, 22, true, true, false, 'kedaluwarsa'],
            ['Perbaikan atap workshop alat berat', 'Workshop & Fuel Bay', 'WS-A', 'ringlock', 'sedang', 14.0, 1.8, 11.0, 1, 'beton', 9, 20, true, true, false, 'hijau'],
            ['Mobile tower penggantian lampu sorot workshop', 'Workshop & Fuel Bay', 'WS-LP', 'mobile', 'ringan', 2.5, 1.35, 6.0, 1, 'beton', 0, 15, true, true, true, 'hijau'],
            ['Perbaikan fender & bollard jetty', 'Pelabuhan & Jetty', 'JT-02', 'kantilever', 'sedang', 6.0, 1.2, 4.5, 1, 'beton', 4, 38, true, true, false, 'kuning'],
            ['Inspeksi boom shiploader', 'Pelabuhan & Jetty', 'SL-01', 'ringlock', 'sedang', 8.0, 1.8, 16.5, 2, 'beton', 10, 48, true, true, false, 'kuning'],
            ['Overhaul genset 1.000 kVA', 'Power House', 'GS-03', 'tube_coupler', 'berat', 5.0, 1.5, 5.0, 1, 'beton', 0, 12, true, true, false, 'hijau'],
            ['Penggantian insulasi pipa exhaust', 'Power House', 'PH-EX', 'ringlock', 'sedang', 6.0, 1.2, 8.0, 1, 'beton', 4, 16, true, true, false, 'kedaluwarsa_tag'],
            ['Perbaikan talang & kanopi mess', 'Kantor, Mess & Fasilitas', 'MS-B', 'frame', 'ringan', 12.0, 1.2, 4.5, 1, 'aspal', 2, 18, true, true, false, 'hijau'],
            ['Penggantian panel atap TPS limbah B3', 'Kantor, Mess & Fasilitas', 'TPS-B3', 'frame', 'ringan', 8.0, 1.2, 4.5, 1, 'padat', 2, 18, true, true, false, 'dibongkar'],
        ];

        $inspektur = [['Agus Setiawan', 'SIO-SCF-2024-1183'], ['Rina Marlina', 'BNSP-SCF-2025-0412'], ['Dedi Kurniawan', 'SIO-SCF-2023-0877']];
        $kontraktor = ['PT Karya Baja Mandiri', 'PT Prima Scaffolding Nusantara', 'CV Teknik Andalan'];
        $n = 0;

        foreach ($daftar as $i => [$judul, $zona, $alat, $jenis, $kelas, $p, $l, $t, $dek, $tanah, $ikat, $angin, $guard, $toe, $out, $nasib]) {
            [$dLat, $dLng] = $pusat[$zona] ?? [0, 0];
            $umur = 14 + (int) round($this->acak('pr-umur'.$i) * 64);    // 2–11 minggu berdiri
            $pasang = $this->kini->copy()->subDays($umur)->setTime(7, 30);

            $u = $this->baru(PerancahUnit::class, [
                'user_id' => $this->pengaju?->getKey(),
                'kode' => sprintf('SCF-%s-%03d', $this->kini->format('Y'), $i + 1), 'judul' => $judul,
                'jenis' => $jenis, 'kelas_beban' => $kelas, 'panjang_m' => $p, 'lebar_m' => $l, 'tinggi_m' => $t, 'jumlah_dek' => $dek,
                'zona' => $zona, 'peralatan_ref' => $alat,
                'nomor_izin' => sprintf('PTW-%s-%04d', $this->kini->format('y'), 3100 + $i * 7),
                'lat' => round($lat0 + $dLat + ($this->acak('pr-la'.$i) - 0.5) * 0.0024, 7),
                'lng' => round($lng0 + $dLng + ($this->acak('pr-ln'.$i) - 0.5) * 0.0024, 7),
                'elevasi_m' => round(($this->acak('pr-el'.$i)) * 12, 1),
                'jenis_tanah' => $tanah, 'jumlah_ikatan' => $ikat, 'angin_kmh' => $angin,
                'ada_guardrail' => $guard, 'ada_toeboard' => $toe, 'ada_outrigger' => $out,
                'kontraktor' => $kontraktor[$i % 3], 'scaffolder_nama' => ['Budi Santoso', 'Hendra Wijaya', 'Yusuf Rahman'][$i % 3],
                'scaffolder_sertifikat' => sprintf('SIO-SCF-%d-%04d', 2023 + $i % 3, 2200 + $i * 13),
                'tanggal_pasang' => $pasang->toDateString(),
                'rencana_bongkar' => $pasang->copy()->addDays($umur + 21 + $i)->toDateString(),
                'status_tag' => 'merah',
            ]);
            $n++;

            $kaj = Penanda::kaji($u, 'Ir. Tri Handayani — HSE Engineer');
            Penanda::terbitkan($u->fresh(), $kaj, null, 'Pendaftaran perancah', $pasang);
            $n += 3;

            // Inspeksi mingguan sejak dipasang.
            $pemeriksa = $inspektur[$i % 3];
            $jadwal = [];
            for ($h = 0; $h <= $umur; $h += 7) $jadwal[] = $pasang->copy()->addDays($h)->setTime(8, 0);
            if ($nasib === 'kedaluwarsa' || $nasib === 'kedaluwarsa_tag') array_pop($jadwal);           // inspeksi terakhir terlewat
            if ($nasib === 'kedaluwarsa' && count($jadwal) > 1) array_pop($jadwal);
            if ($nasib === 'jatuh_tempo') {
                $batasJt = $this->kini->copy()->subDays(6)->setTime(9, 0);
                $jadwal = [...array_filter($jadwal, fn ($w) => $w->lt($batasJt->copy()->subDay())), $batasJt];
            }
            $jadwal = array_values(array_filter($jadwal, fn ($w) => $w->lte($this->kini)));

            foreach ($jadwal as $k => $waktu) {
                $terakhir = $k === count($jadwal) - 1;
                $isian = [];
                foreach (PerancahKatalog::PERIKSA as $b) $isian[$b['kode']] = $b['kode'] === 'INS-04' && $jenis !== 'mobile' ? 'na' : 'lulus';

                // Temuan yang lazim, ditebar deterministik: plank, toe board, housekeeping, barikade, ikatan.
                $r = $this->acak("pr-ins{$i}-{$k}");
                $syarat = null;
                $perbaikan = null;
                if ($r < 0.10) { $isian['INS-12'] = 'gagal'; $perbaikan = 'Material sisa pengelasan dibersihkan dari dek.'; }
                elseif ($r < 0.17) { $isian['INS-16'] = 'gagal'; $perbaikan = 'Barikade drop zone dipasang ulang.'; }
                elseif ($r < 0.22 && !$terakhir) { $isian['INS-09'] = 'gagal'; $perbaikan = 'Celah plank > 25 mm dirapatkan dan dikunci.'; }
                elseif ($r < 0.26 && !$terakhir) { $isian['INS-14'] = 'gagal'; $perbaikan = 'Toe board sisi timur dipasang kembali.'; }
                if ($terakhir && $nasib === 'gagal') {
                    $isian['INS-09'] = 'gagal'; $isian['INS-13'] = 'gagal';
                    $perbaikan = 'Plank bergeser di lift 3 dan mid rail sisi utara lepas setelah bongkar-muat material. Kunci plank, pasang ulang mid rail, lalu inspeksi ulang.';
                }
                if ($terakhir && $nasib === 'tidak_layak' && $i % 2 === 0) {
                    $isian['INS-08'] = 'gagal';
                    $perbaikan = 'Ikatan ke struktur gantry baru 6 dari 8 titik yang dihitung; tambah 2 titik dan kaji ulang.';
                }
                if ($nasib === 'kuning' && $terakhir) $syarat = 'Wajib full body harness 100% tie-off di atas lift 3; maksimal 2 orang per bay.';

                Penanda::inspeksi($u->fresh(), [
                    'jenis' => $k === 0 ? 'awal' : ($this->acak("pr-jn{$i}-{$k}") < 0.12 ? 'pasca_cuaca' : 'mingguan'),
                    'inspektur' => $pemeriksa[0], 'inspektur_sertifikat' => $pemeriksa[1],
                    'periksa' => $isian, 'syarat_khusus' => $syarat, 'tindakan_perbaikan' => $perbaikan,
                ], $this->pengaju?->getKey(), $waktu);
                $n += 2;
            }

            if ($nasib === 'kedaluwarsa_tag') { Penanda::kedaluwarsakan($u->fresh()); $n++; }
            if ($nasib === 'dibongkar') {
                Penanda::bongkar($u->fresh());
                $u->forceFill(['dibongkar_at' => $this->kini->copy()->subDays(2)])->save();
            }
        }
    }

    /** Titik dasar situs menurut komoditas (dekat wilayah tambangnya). */
    private function titikDasar(): array
    {
        $kom = mb_strtolower((string) $this->c->commodity);
        return match (true) {
            str_contains($kom, 'nikel') && str_contains(mb_strtolower($this->c->name), 'sulawesi') => [-2.5531, 121.3692],
            str_contains($kom, 'nikel') => [-3.6812, 122.4127],
            str_contains($kom, 'emas') => [1.0614, 127.8547],
            str_contains($kom, 'gamping'), str_contains($kom, 'kapur') => [-0.9472, 100.4561],
            default => [-2.9734, 115.4521],
        };
    }

    /* ═══════════════════ Life Cycle Assessment ═══════════════════ */

    private function lcaIsi(): void
    {
        LcaPustaka::pasang();
        $kom = mb_strtolower((string) $this->c->commodity);
        $profil = match (true) {
            str_contains($kom, 'nikel') => 'nikel',
            str_contains($kom, 'emas') => 'emas',
            str_contains($kom, 'gamping'), str_contains($kom, 'kapur') => 'gamping',
            default => 'batubara',
        };
        [$wilayah, $listrik] = match ($profil) {
            'nikel' => ['ID-SUL', 'EF-EL-SUL'], 'emas' => ['ID-MA', 'EF-EL-CAPTIVE'], 'gamping' => ['ID-SUM', 'EF-EL-SUM'], default => ['ID-KAL', 'EF-EL-KAL'],
        };

        $lalu = $this->kini->copy()->subYear();
        $mulai = $lalu->copy()->startOfYear();
        $selesai = $lalu->copy()->endOfYear();
        $tonLalu = (float) EnergyProduction::withoutGlobalScopes()->where('company_id', $this->c->id)
            ->whereBetween('tanggal', [$mulai->toDateString(), $selesai->toDateString()])->sum('ton');
        $f = fn (string $kode) => LcaFaktor::withoutGlobalScopes()->where('kode', $kode)
            ->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $this->c->id))->orderByDesc('company_id')->value('id');
        $pengaju = $this->pengaju?->getKey();
        $peninjau = $this->peninjau?->getKey();
        $n = 0;

        // Faktor khusus perusahaan — memperlihatkan bahwa pustaka dapat ditambah.
        $this->baru(LcaFaktor::class, [
            'kode' => 'EF-'.$this->c->code.'-JALAN', 'nama' => 'Truk kontraktor jalan hauling khusus (data perusahaan)', 'kategori' => 'transportasi',
            'tahap_bawaan' => 'angkut', 'scope' => 3, 'satuan' => 'tkm', 'gwp' => 0.092, 'ap' => 0.0005, 'ep' => 0.0001, 'wsf' => 0.002,
            'ced' => 1.34, 'odp' => 1.0e-8, 'sumber' => 'Pengukuran BBM armada kontraktor '.$lalu->format('Y'), 'tahun' => (int) $lalu->format('Y'),
            'wilayah' => $wilayah, 'perlu_cocok' => false, 'aktif' => true,
        ]);
        $n++;
        if ($profil === 'gamping') {
            $this->baru(LcaFaktor::class, [
                'kode' => 'EF-'.$this->c->code.'-KALSINASI', 'nama' => 'Emisi proses kalsinasi CaCO₃ → CaO', 'kategori' => 'material',
                'tahap_bawaan' => 'olah', 'scope' => 1, 'satuan' => 'kg', 'gwp' => 0.785, 'sumber' => 'Stoikiometri IPCC 2006 Vol.3 §2.3', 'tahun' => 2024,
                'wilayah' => $wilayah, 'perlu_cocok' => false, 'aktif' => true,
            ]);
            $n++;
        }

        $studi = match ($profil) {
            'nikel' => [
                ['Bijih nikel saprolit FOB tongkang', 'Saprolit Ni 1,8%', '1', 'wmt bijih', 1000, 'cradle_to_gate', 'terbit', true, [
                    ['angkut', 'EF-'.$this->c->code.'-JALAN', 'Angkut ROM ke stockpile pelabuhan', 1, 'ton', 18, 2, 2], ['distribusi', 'EF-TONGKANG', 'Tongkang ke smelter mitra', 1, 'ton', 210, 2, 2]]],
                ['Nikel pig iron (RKEF) per ton NPI', 'NPI 10% Ni', '1', 'ton NPI', 1000, 'cradle_to_gate', 'tinjau', false, [
                    ['ekstraksi', 'EF-SOLAR-B40', 'Solar penambangan 9,5 wmt bijih', 48, 'L', null, 2, 3], ['angkut', 'EF-TONGKANG', 'Tongkang bijih ke smelter', 9.5, 'ton', 210, 2, 3],
                    ['olah', 'EF-EL-CAPTIVE', 'Listrik RKEF (PLTU captive)', 3650, 'kWh', null, 2, 2], ['olah', 'EF-BB-DRYER', 'Batubara reduktor & rotary dryer', 640, 'kg', null, 2, 2],
                    ['olah', 'EF-KAPUR', 'Kapur fluks', 40, 'kg', null, 3, 3], ['akhir', 'EF-B3-INSIN', 'Debu EAF ke pengolah berizin', 12, 'kg', null, 3, 3]]],
            ],
            'emas' => [
                ['Doré bullion per kg emas', 'Doré Au 85%', '1', 'kg Au', 740000, 'cradle_to_gate', 'terbit', true, [
                    ['olah', 'EF-NACN', 'Natrium sianida leaching (0,6 kg/t bijih)', 444, 'kg', null, 2, 2], ['olah', 'EF-KAPUR', 'Kapur pengatur pH (1,4 kg/t)', 1036, 'kg', null, 2, 2],
                    ['olah', 'EF-BAJA', 'Bola giling baja (0,9 kg/t)', 666, 'kg', null, 2, 2], ['olah', 'EF-AIR', 'Air proses (0,45 m³/t)', 333, 'm3', null, 2, 2]]],
                ['Perak doré sebagai produk samping (alokasi ekonomi)', 'Doré Ag', '1', 'kg Ag', 52000, 'cradle_to_gate', 'tinjau', false, [
                    ['olah', 'EF-NACN', 'Sianida teralokasi ke perak', 31, 'kg', null, 3, 3], ['olah', 'EF-EL-CAPTIVE', 'Listrik pabrik teralokasi', 2100, 'kWh', null, 3, 3]]],
            ],
            'gamping' => [
                ['Batu kapur split 1–2 cm', 'Split 1–2', '1', 'ton batu kapur', 1000, 'cradle_to_gate', 'terbit', true, [
                    ['angkut', 'EF-'.$this->c->code.'-JALAN', 'Truk ke pelanggan semen', 1, 'ton', 35, 2, 2]]],
                ['Kapur tohor (quicklime) per ton CaO', 'CaO 90%', '1', 'ton CaO', 1000, 'cradle_to_gate', 'tinjau', false, [
                    ['ekstraksi', 'EF-SOLAR-B40', 'Solar penambangan 1,8 t batu kapur', 2.6, 'L', null, 2, 2],
                    ['olah', 'EF-'.$this->c->code.'-KALSINASI', 'Emisi proses kalsinasi', 1000, 'kg', null, 1, 1], ['olah', 'EF-BB-DRYER', 'Batubara kiln tegak', 165, 'kg', null, 2, 2],
                    ['olah', $listrik, 'Listrik kiln & crusher', 42, 'kWh', null, 2, 2], ['distribusi', 'EF-BIGBAG', 'Jumbo bag', 1.6, 'kg', null, 2, 2]]],
            ],
            default => [
                ['Batubara GAR 4.200 FOB tongkang', 'Batubara GAR 4.200', '1', 'ton batubara', 1000, 'cradle_to_gate', 'terbit', true, [
                    ['angkut', 'EF-'.$this->c->code.'-JALAN', 'Truk jalan hauling ke pelabuhan', 1, 'ton', 62, 2, 2], ['distribusi', 'EF-TONGKANG', 'Tongkang ke titik transhipment', 1, 'ton', 280, 2, 2]]],
                ['Batubara GAR 4.200 sampai PLTU pembeli (cradle-to-grave)', 'Batubara GAR 4.200', '1', 'ton batubara', 1000, 'cradle_to_grave', 'tinjau', false, [
                    ['ekstraksi', 'EF-SOLAR-B40', 'Solar penambangan (rerata IUP)', 3.1, 'L', null, 3, 3], ['angkut', 'EF-TRUK-JALAN', 'Truk ke pelabuhan', 1, 'ton', 62, 3, 3],
                    ['distribusi', 'EF-TONGKANG', 'Tongkang ke transhipment', 1, 'ton', 280, 2, 3], ['distribusi', 'EF-KAPAL-CURAH', 'Supramax ke pelabuhan pembeli', 1, 'ton', 2300, 3, 3],
                    ['akhir', 'EF-BAKAR-BB', 'Pembakaran di PLTU pembeli', 1000, 'kg', null, 2, 3]]],
            ],
        };

        foreach ($studi as $si => [$judul, $produk, $fu, $fuLabel, $aliran, $batas, $status, $energi, $baris]) {
            $s = $this->baru(LcaStudi::class, [
                'user_id' => $pengaju, 'kode' => sprintf('LCA-%s-%03d', $this->kini->format('Y'), $si + 1), 'versi' => '1.0',
                'judul' => $judul.' — '.$lalu->format('Y'), 'produk' => $produk, 'sku' => null, 'fasilitas' => $this->c->name.' · '.($this->c->location ?: 'site utama'),
                'wilayah' => $wilayah, 'fu_nilai' => (float) $fu, 'fu_label' => $fuLabel, 'aliran_ref_kg' => $aliran,
                'batas_sistem' => $batas, 'metode' => 'IPCC_2021_GWP100', 'alokasi' => $si === 1 && $profil === 'emas' ? 'ekonomi' : 'massa', 'cutoff_pct' => 1,
                'periode_mulai' => $mulai->toDateString(), 'periode_selesai' => $selesai->toDateString(),
                'volume_tahunan' => $energi ? max(1, round($profil === 'emas' ? $tonLalu / 740 : $tonLalu)) : ($profil === 'nikel' ? 120000 : ($profil === 'emas' ? 900 : 1500000)),
                'tujuan' => 'Menghitung jejak lingkungan produk untuk deklarasi kepada pembeli, persiapan EU CBAM dan PROPER, serta memilih program dekarbonisasi yang paling berdampak.',
                'audiens' => 'Pembeli, direksi, verifikator PROPER', 'status' => 'draf',
            ]);
            $n++;
            if ($energi) $n += LcaMesin::imporEnergi($s, $listrik)['dibuat'];

            // Baris umum untuk studi berbasis data Energi.
            $umum = $energi ? [
                ['ekstraksi', 'EF-BAN-OTR', 'Ban OTR alat berat (umur 6.000 jam)', $profil === 'emas' ? 120 : 0.055, 'kg', null, 2, 2],
                ['ekstraksi', 'EF-PELUMAS', 'Pelumas & oli mesin', $profil === 'emas' ? 95 : 0.042, 'L', null, 1, 2],
                ['ekstraksi', 'EF-LAHAN', 'Pembukaan lahan (diamortisasi 20 tahun)', $profil === 'emas' ? 0.012 : 0.0000032, 'ha', null, 3, 3],
                ['olah', 'EF-AIR', 'Air penyiraman jalan & proses', $profil === 'emas' ? 260 : 0.11, 'm3', null, 2, 2],
                ['olah', 'EF-KAPUR', 'Kapur penetral air asam tambang', $profil === 'emas' ? 380 : 0.028, 'kg', null, 2, 3],
                ['akhir', 'EF-TPA', 'Limbah domestik camp ke TPA', $profil === 'emas' ? 55 : 0.021, 'kg', null, 3, 3],
                ['akhir', 'EF-OLI-REK', 'Oli bekas ke pengolah berizin (kredit)', $profil === 'emas' ? 60 : 0.026, 'L', null, 2, 2],
                ['akhir', 'EF-SKRAP-REK', 'Skrap logam dijual daur ulang (kredit)', $profil === 'emas' ? 70 : 0.035, 'kg', null, 2, 3],
                ['akhir', 'EF-REKLAMASI', 'Reklamasi & revegetasi (serapan)', $profil === 'emas' ? 0.009 : 0.0000022, 'ha', null, 3, 3],
            ] : [];

            foreach ([...$baris, ...$umum] as $bi => [$tahap, $kode, $nama, $jumlah, $satuan, $jarak, $p1, $p2]) {
                $fid = $f($kode);
                if (!$fid || $jumlah <= 0) continue;
                LcaMesin::simpanItem($s, ['faktor_id' => $fid, 'tahap' => $tahap, 'nama' => $nama, 'jumlah' => $jumlah, 'satuan' => $satuan,
                    'jarak_km' => $jarak, 'sumber_data' => $p1 <= 2 ? 'primer' : 'sekunder',
                    'p_reliabilitas' => $p1, 'p_kelengkapan' => $p2, 'p_temporal' => 1 + ($bi % 2), 'p_geografis' => $p1, 'p_teknologi' => $p2]);
                $n++;
            }

            $s = LcaMesin::hitungUlang($s->fresh());
            $s->forceFill(['baseline_gwp' => round($s->total_gwp * 1.09, 3), 'target_gwp' => round($s->total_gwp * 0.72, 3)])->save();

            if ($energi) {
                foreach ([['PLTS 30% & efisiensi BBM 8%', ['ebt' => 30, 'bbm' => 8]], ['Logistik & material rendah karbon', ['logistik' => 20, 'daur_ulang' => 25, 'limbah' => 50]],
                          ['Peta jalan 2030 — gabungan', ['ebt' => 60, 'bbm' => 15, 'logistik' => 25, 'daur_ulang' => 30, 'limbah' => 70]]] as [$nm, $t]) {
                    LcaMesin::simpanSkenario($s, $nm, $t, 'Skenario contoh untuk tinjauan direksi.');
                    $n++;
                }
            }

            $jejak = [['buat', 'Studi dibuat: '.$s->judul, 40], ...($energi ? [['impor_energi', 'Inventori ditarik dari Energy Performance '.$lalu->format('Y'), 39]] : []),
                ['tambah_baris', 'Baris material, angkutan, limbah & lahan dilengkapi', 37]];
            if (in_array($status, ['tinjau', 'terbit'], true)) $jejak[] = ['ajukan', 'Status → Dalam tinjauan', 30];
            if ($status === 'terbit') {
                $jejak[] = ['verifikasi', 'Status → Terverifikasi: tinjauan kritis ISO 14044 lulus', 21];
                $jejak[] = ['terbitkan', 'Status → Terbit', 18];
            }
            foreach ($jejak as [$aksi, $ringkas, $hari]) {
                LcaJejak::withoutGlobalScopes()->create(['company_id' => $this->c->id, 'studi_id' => $s->id,
                    'user_id' => in_array($aksi, ['verifikasi', 'terbitkan'], true) ? ($peninjau ?? $pengaju) : $pengaju,
                    'aksi' => $aksi, 'ringkas' => $ringkas, 'created_at' => $this->kini->copy()->subDays($hari)]);
                $n++;
            }

            $s->forceFill(['status' => $status] + ($status === 'terbit' ? [
                'pemverifikasi' => $this->peninjau?->name ?? 'Verifikator LCA independen', 'diverifikasi_at' => $this->kini->copy()->subDays(21),
                'catatan_tinjau' => 'Batas sistem dan alokasi sesuai; faktor grid perlu dicocokkan ulang saat penetapan baru terbit.',
            ] : []))->save();

            // Versi 1.1 (draf) dari studi utama: revisi dengan PLTS yang sudah beroperasi.
            if ($energi) {
                $v = $s->replicate(['status', 'pemverifikasi', 'diverifikasi_at', 'catatan_tinjau']);
                $v->fill(['versi' => '1.1', 'status' => 'draf', 'induk_id' => $s->id, 'judul' => $judul.' — revisi PLTS '.$this->kini->format('Y')])->save();
                foreach (\App\Models\Lca\Inventori::withoutGlobalScopes()->where('studi_id', $s->id)->get() as $item) {
                    $x = $item->replicate(); $x->studi_id = $v->id;
                    if (str_contains($item->nama, 'PLN')) { foreach (LcaMesin::KOLOM as $k) $x->{$k} = $item->{$k} * 0.7; }
                    $x->save();
                    $n++;
                }
                LcaMesin::hitungUlang($v);
                LcaJejak::withoutGlobalScopes()->create(['company_id' => $this->c->id, 'studi_id' => $v->id, 'user_id' => $pengaju,
                    'aksi' => 'versi', 'ringkas' => 'Versi 1.1 dibuat dari v1.0 — listrik grid turun 30% karena PLTS', 'created_at' => $this->kini->copy()->subDays(6)]);
                $n += 2;
            }
        }

        // Studi draf ketiga: kajian awal dengan data sekunder (DQR lemah) — memperlihatkan butir ISO yang belum lulus.
        $d = $this->baru(LcaStudi::class, [
            'user_id' => $pengaju, 'kode' => sprintf('LCA-%s-%03d', $this->kini->format('Y'), 3), 'versi' => '1.0',
            'judul' => 'Kajian awal produk baru — data sekunder', 'produk' => match ($profil) { 'nikel' => 'Bijih limonit untuk HPAL', 'emas' => 'Konsentrat tembaga-emas', 'gamping' => 'Kapur pertanian (dolomit)', default => 'Batubara GAR 3.400 (kalori rendah)' },
            'fasilitas' => $this->c->name, 'wilayah' => $wilayah, 'fu_nilai' => 1, 'fu_label' => 'ton produk', 'aliran_ref_kg' => 1000,
            'batas_sistem' => 'cradle_to_gate', 'metode' => 'IPCC_2021_GWP100', 'alokasi' => 'massa', 'cutoff_pct' => 1,
            'periode_mulai' => $this->kini->copy()->startOfYear()->toDateString(), 'periode_selesai' => $this->kini->copy()->endOfYear()->toDateString(),
            'volume_tahunan' => 250000, 'status' => 'draf', 'tujuan' => null,
        ]);
        $n++;
        foreach ([['ekstraksi', 'EF-SOLAR-B40', 'Solar penambangan (perkiraan)', 2.4, 'L'], ['angkut', 'EF-TRUK-JALAN', 'Angkutan ke pelabuhan', 1, 'ton', 40],
                  ['olah', $listrik, 'Listrik pengolahan (perkiraan)', 4.5, 'kWh']] as $b) {
            LcaMesin::simpanItem($d, ['faktor_id' => $f($b[1]), 'tahap' => $b[0], 'nama' => $b[2], 'jumlah' => $b[3], 'satuan' => $b[4], 'jarak_km' => $b[5] ?? null,
                'sumber_data' => 'sekunder', 'p_reliabilitas' => 4, 'p_kelengkapan' => 4, 'p_temporal' => 3, 'p_geografis' => 3, 'p_teknologi' => 4]);
            $n++;
        }
        LcaJejak::withoutGlobalScopes()->create(['company_id' => $this->c->id, 'studi_id' => $d->id, 'user_id' => $pengaju, 'aksi' => 'buat',
            'ringkas' => 'Studi dibuat: '.$d->judul, 'created_at' => $this->kini->copy()->subDays(3)]);
    }
}
