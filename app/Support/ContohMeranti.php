<?php

namespace App\Support;

use App\Models\Meranti as M;
use App\Support\Meranti\{Geo, Hitung, Katalog};

/**
 * Data contoh MERANTI — Reklamasi & Nursery.
 *
 * Polanya mengikuti lembar MERANTI dan cukup lengkap untuk demo:
 * persemaian dengan 18 jenis bibit dan mutasi 12 bulan, gudang material,
 * enam blok rehabilitasi DAS dengan target P0/P1/P2 dua tahun dan progres
 * mingguan yang mengikuti musim (penanaman di musim hujan, pemeliharaan
 * sepanjang tahun), reklamasi lima tahun dengan penilaian Matrik 17 yang
 * memuat ketiga kategori, administrasi, dan peta blok di atas lahan teras.
 *
 * Aktual Pencapaian sengaja beragam: blok yang lewat target, yang tepat,
 * dan yang tertinggal; satu bibit berstok nol supaya peringatan stok habis
 * ikut tampil. Semua angka tetap untuk satu perusahaan (acak() berkunci),
 * jadi memuat ulang tidak mengubah apa pun.
 */
trait ContohMeranti
{
    private const KELAS_MERANTI = [
        M\Tanaman::class, M\StokBibit::class, M\BibitMasuk::class, M\BibitKeluar::class, M\BibitKembali::class, M\BibitMati::class,
        M\BibitRusak::class, M\StokMaterial::class, M\MaterialMasuk::class, M\MaterialKeluar::class, M\Progres::class, M\TargetBlok::class,
        M\ArsipCapaian::class, M\Penanaman::class, M\RencanaReklamasi::class, M\Penilaian::class, M\Jaminan::class, M\Surat::class,
        M\PengajuanDana::class, M\BeritaAcara::class, M\Periode::class, M\Project::class, M\Pelaksana::class, M\ProjectVendor::class,
        M\Peta::class, M\Log::class, M\Setelan::class,
    ];

    /** Batas enam blok rehabilitasi DAS — poligon mengikuti lahan teras di citra satelit. */
    private const BLOK_MERANTI = [
        'Blok 1' => [[116.972058, -0.63522], [116.973023, -0.633611], [116.97474, -0.633031], [116.976027, -0.633396], [116.975706, -0.63522], [116.975491, -0.637044], [116.974955, -0.638653], [116.973882, -0.63994], [116.972809, -0.639082], [116.97238, -0.637366]],
        'Blok 2' => [[116.976027, -0.635113], [116.9771, -0.63419], [116.978817, -0.633611], [116.980748, -0.63404], [116.981499, -0.635434], [116.98107, -0.637366], [116.979997, -0.639082], [116.977959, -0.639618], [116.976349, -0.63876], [116.975641, -0.637044]],
        'Blok 3' => [[116.975062, -0.63994], [116.976242, -0.639683], [116.977959, -0.640262], [116.979675, -0.64155], [116.979353, -0.643373], [116.978281, -0.644661], [116.976135, -0.644704], [116.975384, -0.643159], [116.975491, -0.641228]],
        'Blok 4' => [[116.983967, -0.639082], [116.985898, -0.63876], [116.987507, -0.63994], [116.988258, -0.641764], [116.987507, -0.643481], [116.985791, -0.644124], [116.984396, -0.643159], [116.983709, -0.641228]],
        'Blok 5' => [[116.983216, -0.648952], [116.984932, -0.648416], [116.986649, -0.649274], [116.987507, -0.650883], [116.987078, -0.653029], [116.985362, -0.654101], [116.983581, -0.653715], [116.982937, -0.651527]],
        'Blok 6' => [[116.987722, -0.650132], [116.989331, -0.649553], [116.990941, -0.650196], [116.991799, -0.651913], [116.99137, -0.654101], [116.989653, -0.655003], [116.987936, -0.654316], [116.987293, -0.652385]],
    ];

    private function meranti(): int
    {
        $awal = $this->hitungMilikMeranti();
        $this->merantiIsi();

        return $this->hitungMilikMeranti() - $awal;
    }

    private function hitungMilikMeranti(): int
    {
        return array_sum(array_map(fn ($k) => $k::withoutGlobalScopes()->where('company_id', $this->c->id)->count(), self::KELAS_MERANTI));
    }

    private function merantiBuat(string $kelas, array $data): void
    {
        $kelas::withoutGlobalScopes()->create($data + ['company_id' => $this->c->id, 'user_id' => $this->pengaju?->id]);
    }

    private function merantiIsi(): void
    {
        $k = $this->kini->copy();
        $th = (int) $k->year;
        $tgl = fn (int $hariLalu) => $k->copy()->subDays($hariLalu)->toDateString();
        $buat = fn (string $kelas, array $baris) => array_map(fn ($b) => $this->merantiBuat($kelas, $b), $baris);
        $bulat = fn (float $v, int $ke = 10) => (int) max($ke, round($v / $ke) * $ke);

        /* ---- data master ---- */
        $tanaman = [
            ['Centrosema', 'Centrosema pubescens', 'Cover Crop'], ['Calopogonium', 'Calopogonium mucunoides', 'Cover Crop'],
            ['Mucuna', 'Mucuna bracteata', 'Cover Crop'], ['Akar Wangi', 'Chrysopogon zizanioides', 'Cover Crop'],
            ['Sengon Laut', 'Falcataria moluccana', 'Pioneer'], ['Sengon Buto', 'Enterolobium cyclocarpum', 'Pioneer'],
            ['Trembesi', 'Albizia saman', 'Pioneer'], ['Jabon', 'Neolamarckia cadamba', 'Pioneer'], ['Akasia', 'Acacia mangium', 'Pioneer'],
            ['Johar', 'Senna siamea', 'Pioneer'], ['Gmelina', 'Gmelina arborea', 'Pioneer'], ['Kayu Putih', 'Melaleuca cajuputi', 'Pioneer'],
            ['Meranti Merah', 'Shorea leprosula', 'Lokal'], ['Kahoi', 'Shorea balangeran', 'Lokal'], ['Bangkirai', 'Shorea laevis', 'Lokal'],
            ['Jelutung', 'Dyera costulata', 'Lokal'], ['Ulin', 'Eusideroxylon zwageri', 'Lokal'], ['Merbau', 'Intsia bijuga', 'Lokal'],
            ['Mahoni', 'Swietenia macrophylla', 'MPTS'], ['Kelor', 'Moringa oleifera', 'MPTS'], ['Durian', 'Durio zibethinus', 'MPTS'],
            ['Petai', 'Parkia speciosa', 'MPTS'], ['Nangka', 'Artocarpus heterophyllus', 'MPTS'],
        ];
        $buat(M\Tanaman::class, array_map(fn ($t) => ['nama_tanaman' => $t[0], 'nama_latin' => $t[1], 'golongan' => $t[2]], $tanaman));
        $golongan = array_column($tanaman, 2, 0);

        $buat(M\Project::class, [
            ['kode_project' => 'PRJ-01', 'nama_project' => 'Revegetasi', 'keterangan' => 'Penanaman area reklamasi dengan tanaman pioneer, lokal, dan sisipan'],
            ['kode_project' => 'PRJ-02', 'nama_project' => 'Pemeliharaan', 'keterangan' => 'Penyulaman tanaman mati, pemupukan, pendangiran, dan penyiangan gulma'],
            ['kode_project' => 'PRJ-03', 'nama_project' => 'Pengayaan', 'keterangan' => 'Penambahan jenis tanaman sisipan lokal'],
            ['kode_project' => 'PRJ-04', 'nama_project' => 'Event', 'keterangan' => 'Penanaman di luar project reklamasi'],
            ['kode_project' => 'PRJ-05', 'nama_project' => 'Penanaman RDAS', 'keterangan' => 'Penanaman rehabilitasi DAS'],
        ]);
        $pelaksana = [['EMC', 'In House'], ['Tim Persemaian', 'In House'], ['KTH Sumber Rejeki', 'In House'], ['Kelompok Tani Hijau', 'In House'],
            ['CV Rimba Lestari', 'Vendor'], ['PT Tunas Alam', 'Vendor'], ['CV Bumi Asri', 'Vendor'], ['CV Hijau Wana Mandiri', 'Vendor']];
        $buat(M\Pelaksana::class, array_map(fn ($p) => ['nama_pelaksana' => $p[0], 'kategori' => $p[1]], $pelaksana));
        $buat(M\ProjectVendor::class, [
            ['kode_project_vendor' => 'PV-01', 'nama_vendor' => 'CV Rimba Lestari', 'nama_project' => 'Revegetasi'],
            ['kode_project_vendor' => 'PV-02', 'nama_vendor' => 'CV Rimba Lestari', 'nama_project' => 'Pengayaan'],
            ['kode_project_vendor' => 'PV-03', 'nama_vendor' => 'PT Tunas Alam', 'nama_project' => 'Penanaman RDAS'],
            ['kode_project_vendor' => 'PV-04', 'nama_vendor' => 'CV Bumi Asri', 'nama_project' => 'Pemeliharaan'],
            ['kode_project_vendor' => 'PV-05', 'nama_vendor' => 'CV Hijau Wana Mandiri', 'nama_project' => 'Penanaman RDAS'],
            ['kode_project_vendor' => 'PV-06', 'nama_vendor' => 'CV Hijau Wana Mandiri', 'nama_project' => 'Pemeliharaan'],
        ]);

        /* ---- nursery: stok dan mutasi 12 bulan ---- */
        $stok = [['Sengon Laut', 6840], ['Akasia', 3920], ['Jabon', 3150], ['Meranti Merah', 2640], ['Mahoni', 2150], ['Kahoi', 1980],
            ['Trembesi', 1480], ['Gmelina', 1260], ['Kayu Putih', 1163], ['Kelor', 1110], ['Johar', 960], ['Bangkirai', 740],
            ['Durian', 640], ['Petai', 580], ['Jelutung', 520], ['Nangka', 430], ['Ulin', 7], ['Merbau', 0]];
        $buat(M\StokBibit::class, array_map(fn ($s) => ['nama_bibit' => $s[0], 'stok' => $s[1], 'golongan' => $golongan[$s[0]]], $stok));

        $jenisMasuk = ['Sengon Laut', 'Meranti Merah', 'Akasia', 'Mahoni', 'Jabon', 'Kahoi', 'Gmelina', 'Durian', 'Bangkirai', 'Trembesi', 'Petai', 'Jelutung'];
        $sumber = [['Hasil semai sendiri', 'Media tanah + kompos 3:1'], ['Persemaian KPH Santan', 'Jenis lokal sisipan'], ['Pengadaan CV Rimba Lestari', 'Polybag 15×15'],
            ['Bantuan BPDAS Mahakam Berau', 'Program Kebun Bibit Rakyat'], ['Cabutan alam sekitar blok', 'Disapih ke polybag'], ['Kebun Bibit Desa Sebulu', 'Kerja sama desa']];
        $masuk = [];
        for ($i = 0; $i < 30; $i++) {
            $j = $jenisMasuk[$i % count($jenisMasuk)];
            [$s, $ket] = $sumber[($i * 5) % count($sumber)];
            $besar = in_array($j, ['Sengon Laut', 'Akasia', 'Jabon'], true) ? 1800 : 600;
            $masuk[] = ['tanggal' => $tgl(3 + $i * 12), 'nama_bibit' => $j, 'jumlah' => $bulat($besar * (0.5 + $this->acak("mr-bm$i"))), 'sumber' => $s, 'keterangan' => $ket];
        }
        $buat(M\BibitMasuk::class, $masuk);

        $jenisKeluar = ['Sengon Laut', 'Akasia', 'Meranti Merah', 'Jabon', 'Mahoni', 'Kahoi', 'Gmelina', 'Trembesi', 'Durian', 'Bangkirai', 'Petai', 'Kelor'];
        $tujuanKet = ['P0 revegetasi', 'Penyulaman tanaman mati', 'Pengayaan jenis lokal', 'P0 penanaman RDAS'];
        $keluar = [];
        for ($i = 0; $i < 36; $i++) {
            $j = $jenisKeluar[($i * 7) % count($jenisKeluar)];
            $besar = in_array($j, ['Sengon Laut', 'Akasia', 'Jabon'], true) ? 1400 : 450;
            $keluar[] = ['tanggal' => $tgl(2 + $i * 10), 'nama_bibit' => $j, 'jumlah' => $bulat($besar * (0.45 + $this->acak("mr-bk$i"))),
                'tujuan' => 'Blok '.(1 + ($i * 5) % 6), 'keterangan' => $tujuanKet[$i % count($tujuanKet)]];
        }
        $buat(M\BibitKeluar::class, $keluar);

        $buat(M\BibitKembali::class, array_map(fn ($b) => ['tanggal' => $tgl($b[0]), 'nama_bibit' => $b[1], 'jumlah' => $b[2], 'keterangan' => $b[3]], [
            [9, 'Mahoni', 40, 'Sisa penanaman Blok 3'], [27, 'Sengon Laut', 120, 'Lubang tanam Blok 4 belum siap'], [64, 'Meranti Merah', 35, 'Sisa pengayaan Blok 2'],
            [118, 'Akasia', 80, 'Hujan deras, penanaman ditunda'], [176, 'Durian', 18, 'Sisa penanaman Blok 5'], [241, 'Jabon', 60, 'Sisa penanaman Blok 6'],
        ]));
        $buat(M\BibitMati::class, array_map(fn ($b) => ['tanggal' => $tgl($b[0]), 'nama_bibit' => $b[1], 'jumlah' => $b[2], 'penyebab' => $b[3]], [
            [6, 'Jabon', 12, 'Serangan hama ulat'], [19, 'Kelor', 25, 'Kekeringan'], [38, 'Meranti Merah', 14, 'Busuk akar'],
            [57, 'Sengon Laut', 46, 'Jamur daun'], [83, 'Kahoi', 9, 'Kekeringan'], [112, 'Akasia', 31, 'Tergenang saat hujan deras'],
            [149, 'Ulin', 6, 'Busuk akar'], [187, 'Petai', 11, 'Serangan rayap'], [226, 'Durian', 8, 'Kekeringan'], [268, 'Gmelina', 17, 'Jamur daun'],
        ]));
        $buat(M\BibitRusak::class, array_map(fn ($b) => ['tanggal' => $tgl($b[0]), 'nama_bibit' => $b[1], 'jumlah' => $b[2], 'penyebab' => $b[3]], [
            [11, 'Sengon Laut', 30, 'Polybag sobek saat angkut'], [44, 'Mahoni', 12, 'Patah batang saat bongkar muat'], [96, 'Akasia', 22, 'Media tanam tumpah'],
            [133, 'Meranti Merah', 7, 'Tertimpa paranet roboh'], [205, 'Jabon', 15, 'Polybag sobek saat angkut'], [290, 'Trembesi', 9, 'Patah batang saat bongkar muat'],
        ]));

        /* ---- material ---- */
        $material = [['Pupuk NPK 16-16-16', 420, 'Kg'], ['Pupuk TSP', 100, 'Kg'], ['Kompos', 1250, 'Kg'], ['Dolomit', 380, 'Kg'],
            ['Fumyco (Pupuk Mikoriza)', 66, 'Kg'], ['Polybag Uk.15x15 cm', 23, 'Kg'], ['Polybag Uk.20x20 cm', 4, 'Kg'], ['Benih Kelor', 500, 'Gram'],
            ['Benih Mahoni', 5, 'Kg'], ['Benih Eucalyptus', 2, 'Kg'], ['Cover Crop MB', 100, 'Kg'], ['Benih Vertiver', 1, 'Kg'],
            ['Ajir Bambu', 2600, 'Pcs'], ['Paranet 65%', 6, 'Roll'], ['Kawat Duri', 3, 'Roll'], ['Herbisida Sistemik', 24, 'Liter'],
            ['CBA Stick', 11, 'Botol'], ['Sarung Tangan Kain', 140, 'Pack']];
        $buat(M\StokMaterial::class, array_map(fn ($m) => ['nama_material' => $m[0], 'material_stok' => $m[1], 'nama_satuan' => $m[2]], $material));
        $satuan = array_column($material, 2, 0);
        $pakai = [['Pupuk NPK 16-16-16', 150], ['Kompos', 500], ['Pupuk TSP', 100], ['Ajir Bambu', 900], ['Polybag Uk.15x15 cm', 10], ['Dolomit', 120],
            ['Fumyco (Pupuk Mikoriza)', 20], ['Herbisida Sistemik', 10], ['Cover Crop MB', 40], ['Paranet 65%', 2]];
        $mm = $mk = [];
        for ($i = 0; $i < 16; $i++) {
            [$nm, $jml] = $pakai[($i * 3) % count($pakai)];
            $mm[] = ['tanggal' => $tgl(5 + $i * 21), 'nama_material' => $nm, 'jumlah' => round($jml * (1.2 + $this->acak("mr-mm$i")), 0), 'satuan' => $satuan[$nm],
                'sumber' => ['Gudang site', 'Pembelian lokal', 'Pengadaan pusat', 'Toko tani Tenggarong'][$i % 4]];
        }
        for ($i = 0; $i < 20; $i++) {
            [$nm, $jml] = $pakai[($i * 7) % count($pakai)];
            $mk[] = ['tanggal' => $tgl(3 + $i * 17), 'nama_material' => $nm, 'jumlah' => round($jml * (0.4 + 0.6 * $this->acak("mr-mk$i")), 0), 'satuan' => $satuan[$nm],
                'tujuan' => $i % 5 === 4 ? 'Persemaian' : 'Blok '.(1 + ($i * 5) % 6)];
        }
        $buat(M\MaterialMasuk::class, $mm);
        $buat(M\MaterialKeluar::class, $mk);

        /* ---- rehabilitasi DAS: target dan progres mingguan ---- */
        // Target per blok [P0, P1, P2] (ha) tahun lalu dan tahun berjalan, dan capaian tahun berjalan sampai minggu ini:
        // Blok 3 lewat target, Blok 2 tepat, Blok 4 dan Blok 6 (blok baru) tertinggal.
        $target = [
            'Blok 1' => [[7.0, 11.0, 4.5], [4.5, 7.0, 11.0], [0.96, 0.88, 0.79]],
            'Blok 2' => [[10.5, 15.0, 6.0], [6.0, 10.5, 15.0], [1.04, 0.98, 0.93]],
            'Blok 3' => [[8.0, 5.5, 2.0], [6.5, 8.0, 5.5], [1.10, 1.06, 1.02]],
            'Blok 4' => [[5.5, 3.0, 0.0], [12.0, 5.5, 3.0], [0.52, 0.38, 0.27]],
            'Blok 5' => [[9.0, 7.0, 3.0], [8.0, 9.0, 7.0], [0.91, 0.95, 0.86]],
            'Blok 6' => [[5.0, 2.5, 0.0], [13.5, 5.0, 2.5], [0.67, 0.74, 0.58]],
        ];
        $pelaksanaBlok = ['Blok 1' => 'KTH Sumber Rejeki', 'Blok 2' => 'CV Rimba Lestari', 'Blok 3' => 'Tim Persemaian',
            'Blok 4' => 'PT Tunas Alam', 'Blok 5' => 'CV Hijau Wana Mandiri', 'Blok 6' => 'CV Bumi Asri'];
        // Musim: penanaman (P0) di musim hujan, pemeliharaan sepanjang tahun dengan puncak sesudah tanam.
        $musim = [
            'P0' => [1.0, 1.1, 0.9, 0.5, 0.2, 0, 0, 0, 0.3, 0.9, 1.2, 1.2],
            'P1' => [0.3, 0.7, 1.0, 1.0, 0.9, 0.6, 0.5, 0.8, 1.0, 0.9, 0.6, 0.3],
            'P2' => [0.2, 0.4, 0.7, 0.9, 1.0, 0.8, 0.6, 0.9, 1.0, 0.8, 0.5, 0.2],
        ];
        $mingguKerja = ['P0' => [1, 3], 'P1' => [2], 'P2' => [4]];
        $uraian = ['P0' => 'Penanaman', 'P1' => 'Pemeliharaan I — penyiangan & pemupukan', 'P2' => 'Pemeliharaan II — penyulaman & pendangiran'];

        foreach ([$th - 1, $th] as $tahun) {
            $berjalan = $tahun === $th;
            foreach ($target as $nama => [$lalu, $kini, $rasio]) {
                foreach (Katalog::PEKERJAAN as $p => $pkj) {
                    $luas = $berjalan ? $kini[$p] : $lalu[$p];
                    if ($luas <= 0) continue;
                    $this->merantiBuat(M\TargetBlok::class, ['nama_blok' => $nama, 'pekerjaan' => $pkj, 'luas_target' => $luas, 'tahun' => $tahun,
                        'keterangan' => 'Rencana '.$tahun.' · '.$pelaksanaBlok[$nama]]);

                    // Slot mingguan yang sudah lewat, berbobot musim dan sedikit acak.
                    $slot = [];
                    foreach (range(1, 12) as $bln) {
                        if ($musim[$pkj][$bln - 1] <= 0) continue;
                        foreach ($mingguKerja[$pkj] as $minggu) {
                            $tanggal = sprintf('%04d-%02d-%02d', $tahun, $bln, $minggu * 7 - 3);
                            if ($tanggal > $k->toDateString()) continue;
                            $slot[] = [$bln, $minggu, $tanggal, $musim[$pkj][$bln - 1] * (0.7 + 0.6 * $this->acak("mr-pg$tahun$nama$pkj$bln$minggu"))];
                        }
                    }
                    $total = round($luas * ($berjalan ? $rasio[$p] : 0.94 + 0.1 * $this->acak("mr-rl$nama$pkj")), 2);
                    $bobot = array_sum(array_column($slot, 3)) ?: 1;
                    $sisa = $total;
                    foreach ($slot as $i => [$bln, $minggu, $tanggal, $w]) {
                        $bagian = $i === count($slot) - 1 ? round($sisa, 2) : round($total * $w / $bobot, 2);
                        $sisa = round($sisa - $bagian, 2);
                        if ($bagian <= 0) continue;
                        $this->merantiBuat(M\Progres::class, [
                            'tahun' => $tahun, 'bulan' => Katalog::BULAN[$bln - 1], 'minggu' => 'Minggu '.$minggu, 'tanggal' => $tanggal,
                            'pelaksana' => $pelaksanaBlok[$nama], 'pekerjaan' => $pkj, 'nama_blok' => $nama, 'luas_realisasi' => $bagian,
                            'keterangan' => $uraian[$pkj].' '.$nama,
                        ]);
                    }
                }
            }
        }

        $arsip = [];
        $proyekArsip = [['Penanaman RDAS', 'Vendor', 'PT Tunas Alam'], ['Pemeliharaan', 'In House', 'EMC'], ['Revegetasi', 'Vendor', 'CV Rimba Lestari'],
            ['Penanaman RDAS', 'Vendor', 'CV Hijau Wana Mandiri'], ['Pengayaan', 'In House', 'Tim Persemaian'], ['Pemeliharaan', 'Vendor', 'CV Bumi Asri']];
        for ($i = 0; $i < 12; $i++) {
            [$pr, $kat, $pl] = $proyekArsip[$i % count($proyekArsip)];
            $t = $k->copy()->subWeeks($i * 2);
            $arsip[] = ['nama_project' => $pr, 'kategori_pelaksana' => $kat, 'pelaksana' => $pl, 'tahun' => (int) $t->year,
                'bulan' => Katalog::BULAN[$t->month - 1], 'week' => 'W'.min(5, (int) ceil($t->day / 7)), 'file' => null, 'penginput' => $i % 3 === 0 ? 'Admin EMC' : 'Mandor lapangan'];
        }
        $buat(M\ArsipCapaian::class, $arsip);

        $tanam = [];
        $jenisTanam = ['Sengon Laut', 'Meranti Merah', 'Akasia', 'Kahoi', 'Jabon', 'Mahoni', 'Durian', 'Bangkirai', 'Gmelina', 'Petai', 'Trembesi', 'Jelutung'];
        $proyekTanam = [['Penanaman RDAS', 'P0 RDAS'], ['Revegetasi', 'Revegetasi'], ['Pengayaan', 'Pengayaan jenis lokal'], ['Pemeliharaan', 'Penyulaman']];
        for ($i = 0; $i < 24; $i++) {
            $j = $jenisTanam[($i * 5) % count($jenisTanam)];
            $blok = 'Blok '.(1 + ($i * 7) % 6);
            [$pr, $keg] = $proyekTanam[$i % count($proyekTanam)];
            $besar = $golongan[$j] === 'Pioneer' ? 1100 : 380;
            $tanam[] = ['tanggal' => $tgl(4 + $i * 13), 'nama_bibit' => $j, 'golongan' => $golongan[$j], 'jumlah' => $bulat($besar * (0.5 + $this->acak("mr-tn$i"))),
                'nama_project' => $pr, 'nama_kegiatan' => $keg.' '.$blok, 'pelaksana' => $pelaksanaBlok[$blok], 'nama_blok' => $blok,
                'penginput' => $i % 2 ? 'Mandor lapangan' : 'Mandor EMC'];
        }
        $buat(M\Penanaman::class, $tanam);

        /* ---- reklamasi: lima tahun rencana, penilaian Matrik 17, jaminan ---- */
        $buat(M\RencanaReklamasi::class, array_map(fn ($r) => ['tahun' => $th - $r[0], 'luas_bukaan_ha' => $r[1], 'luas_reklamasi_ha' => $r[2],
            'jumlah_bibit_rencana' => $r[3], 'biaya_rencana' => $r[4], 'keterangan' => $r[5]], [
            [4, 38.0, 24.5, 15300, 2_100_000_000, 'Tahap I — disposal utara'], [3, 42.5, 31.0, 19400, 2_450_000_000, 'Tahap II — disposal utara & lereng timur'],
            [2, 45.5, 36.8, 23000, 2_750_000_000, 'Tahap III — lereng timur'], [1, 52.0, 44.6, 27900, 3_200_000_000, 'Tahap IV — area pit lama'],
            [0, 60.0, 48.5, 30300, 3_850_000_000, 'Rencana tahun berjalan'],
        ]));
        foreach ([['Blok 1', 3, 72, 66, 60], ['Blok 1', 2, 84, 80, 74], ['Blok 2', 2, 90, 86, 80], ['Blok 3', 2, 87, 83, 79], ['Blok 1', 1, 92, 88, 82],
            ['Blok 2', 1, 95, 90, 86], ['Blok 3', 1, 88, 84, 78], ['Blok 5', 1, 86, 80, 74], ['Blok 4', 1, 58, 52, 46]] as [$b, $mundur, $p, $r, $s]) {
            $this->merantiBuat(M\Penilaian::class, ['blok' => $b, 'tahun' => $th - $mundur, 'nilai_penatagunaan' => $p, 'nilai_revegetasi' => $r, 'nilai_penyelesaian' => $s] + Hitung::matrik17($p, $r, $s));
        }
        $buat(M\Jaminan::class, [
            ['periode' => ($th - 4).'-'.$th, 'jenis' => 'Jaminan Reklamasi', 'nilai' => 14_500_000_000, 'tanggal_penempatan' => ($th - 4).'-02-15', 'status' => 'Sebagian Dicairkan'],
            ['periode' => ($th + 1).'-'.($th + 5), 'jenis' => 'Jaminan Reklamasi', 'nilai' => 16_800_000_000, 'tanggal_penempatan' => $tgl(65), 'status' => 'Ditempatkan'],
            ['periode' => (string) ($th - 4), 'jenis' => 'Jaminan Pascatambang', 'nilai' => 8_200_000_000, 'tanggal_penempatan' => ($th - 4).'-03-10', 'status' => 'Ditempatkan'],
            ['periode' => ($th - 9).'-'.($th - 5), 'jenis' => 'Jaminan Reklamasi', 'nilai' => 9_600_000_000, 'tanggal_penempatan' => ($th - 9).'-04-02', 'status' => 'Dicairkan'],
        ]);

        /* ---- administrasi ---- */
        $buat(M\Surat::class, array_map(fn ($s) => ['nomor_surat' => $s[0], 'jenis' => $s[1], 'tanggal' => $tgl($s[2]), 'perihal' => $s[3], 'dari_kepada' => $s[4]], [
            ['047/EMC/'.$th, 'Keluar', 4, 'Laporan progres rehabilitasi DAS bulan '.Katalog::BULAN[max(0, $k->month - 2)], 'BPDAS Mahakam Berau'],
            ['S.214/BPDAS/'.$th, 'Masuk', 12, 'Jadwal monitoring dan evaluasi rehabilitasi DAS', 'BPDAS Mahakam Berau'],
            ['041/EMC/'.$th, 'Keluar', 26, 'Permohonan penilaian keberhasilan reklamasi Blok 3', 'Inspektur Tambang'],
            ['522/DISHUT/'.$th, 'Masuk', 39, 'Undangan rapat koordinasi pengendalian karhutla', 'Dinas Kehutanan Provinsi Kalimantan Timur'],
            ['036/EMC/'.$th, 'Keluar', 47, 'Penyampaian rencana reklamasi tahun '.($th + 1), 'Direktur Teknik dan Lingkungan Minerba'],
            ['S.188/KPH/'.$th, 'Masuk', 61, 'Ketersediaan bibit jenis lokal untuk pengayaan', 'KPH Santan'],
            ['029/EMC/'.$th, 'Keluar', 78, 'Permintaan penawaran pekerjaan pemeliharaan P2', 'CV Bumi Asri'],
            ['S.143/BPDAS/'.$th, 'Masuk', 95, 'Hasil verifikasi geotagging tanaman Blok 1 dan Blok 2', 'BPDAS Mahakam Berau'],
            ['021/EMC/'.$th, 'Keluar', 128, 'Laporan semester I pelaksanaan rehabilitasi DAS', 'Direktorat Jenderal PDASRH'],
            ['014/EMC/'.$th, 'Keluar', 171, 'Penempatan jaminan reklamasi periode '.($th + 1).'–'.($th + 5), 'Inspektur Tambang'],
        ]));
        $triwulan = (int) ceil($k->month / 3);
        $periode = array_map(fn ($q) => 'Triwulan '.['I', 'II', 'III', 'IV'][$q - 1].' '.$th, range(1, 4));
        $buat(M\Periode::class, array_map(fn ($q) => [
            'nama_periode' => $periode[$q - 1],
            'tanggal_mulai' => $k->copy()->setDate($th, ($q - 1) * 3 + 1, 1)->toDateString(),
            'tanggal_selesai' => $k->copy()->setDate($th, $q * 3, 1)->endOfMonth()->toDateString(),
            'status' => $q === $triwulan ? 'Aktif' : 'Tutup',
        ], range(1, $triwulan)));
        $buat(M\PengajuanDana::class, array_map(fn ($p) => ['nomor' => 'PD-'.$th.'-'.$p[0], 'project' => $p[1], 'periode' => $periode[min($triwulan, $p[2]) - 1], 'nilai' => $p[3], 'status' => $p[4]], [
            ['007', 'Penanaman RDAS', 1, 412_000_000, 'Dibayar'], ['008', 'Pemeliharaan', 1, 96_500_000, 'Dibayar'], ['009', 'Revegetasi', 2, 238_000_000, 'Dibayar'],
            ['010', 'Pengayaan', 2, 57_800_000, 'Ditolak'], ['011', 'Penanaman RDAS', 3, 365_000_000, 'Disetujui'], ['012', 'Pemeliharaan', 3, 88_200_000, 'Dibayar'],
            ['013', 'Revegetasi', 4, 185_000_000, 'Disetujui'], ['014', 'Pemeliharaan', 4, 64_500_000, 'Diajukan'], ['015', 'Penanaman RDAS', 4, 291_000_000, 'Diajukan'],
        ]));
        $buat(M\BeritaAcara::class, array_map(fn ($b) => ['nomor' => 'BA-'.$th.'-'.$b[0], 'judul' => $b[1], 'tanggal' => $tgl($b[2]), 'terkait' => $b[3]], [
            ['12', 'Serah terima bibit ke PT Tunas Alam — Blok 4', 5, 'Bibit Keluar · Blok 4'], ['11', 'Pemeriksaan hasil P2 Blok 2', 16, 'Progres Mingguan · Blok 2'],
            ['10', 'Opname stok persemaian triwulan III', 58, 'Stok Bibit'], ['09', 'Serah terima pekerjaan P1 Blok 5', 73, 'Progres Mingguan · Blok 5'],
            ['08', 'Pemeriksaan hasil P1 Blok 3', 101, 'Progres Mingguan · Blok 3'], ['07', 'Serah terima bibit ke CV Rimba Lestari — Blok 2', 134, 'Bibit Keluar · Blok 2'],
            ['06', 'Penilaian lapangan keberhasilan reklamasi Blok 1', 169, 'Penilaian Keberhasilan · Blok 1'], ['05', 'Pemusnahan bibit rusak', 212, 'Bibit Rusak'],
        ]));

        /* ---- peta spasial: batas area, enam blok, persemaian, dan titik tanam ---- */
        foreach (self::BLOK_MERANTI as $nama => $cincin) {
            $cincin[] = $cincin[0];
            $luas = round(Geo::luasHa($cincin), 4);
            $this->merantiBuat(M\Peta::class, ['nama_fitur' => $nama, 'kategori' => 'Blok', 'petak' => (int) ceil($luas / 3).' petak',
                'year' => in_array($nama, ['Blok 4', 'Blok 6'], true) ? $th : $th - 1, 'luas_ha' => $luas,
                'geojson' => json_encode(['type' => 'Polygon', 'coordinates' => [$cincin]]), 'sumber_file' => 'blok-rdas.kml']);
        }
        foreach ([
            ['Batas Area Reklamasi', 'Batas Area', [[116.971307, -0.633289], [116.974955, -0.631894], [116.98225, -0.631572], [116.9874, -0.636293], [116.989975, -0.639511], [116.992979, -0.642944], [116.992979, -0.655818], [116.982679, -0.655603], [116.981821, -0.647235], [116.977959, -0.645519], [116.974525, -0.645519], [116.971736, -0.641228]]],
            ['Persemaian Sentral', 'Lainnya', [[116.970019, -0.630757], [116.97135, -0.630628], [116.971478, -0.631701], [116.970148, -0.63183]]],
        ] as [$nama, $kat, $cincin]) {
            $cincin[] = $cincin[0];
            $this->merantiBuat(M\Peta::class, ['nama_fitur' => $nama, 'kategori' => $kat, 'year' => $th, 'luas_ha' => round(Geo::luasHa($cincin), 4),
                'geojson' => json_encode(['type' => 'Polygon', 'coordinates' => [$cincin]]), 'sumber_file' => 'batas-area.kmz']);
        }
        foreach ([['Titik tanam Meranti Merah', 116.974096, -0.636293], ['Titik tanam Ulin', 116.978817, -0.636507], ['Titik tanam Kahoi', 116.97753, -0.642515],
            ['Titik tanam Bangkirai', 116.986113, -0.641657], ['Titik tanam Durian', 116.985254, -0.651312], ['Titik tanam Jelutung', 116.989546, -0.65217]] as [$nama, $x, $y]) {
            $this->merantiBuat(M\Peta::class, ['nama_fitur' => $nama, 'kategori' => 'Titik Tanam', 'year' => $th,
                'geojson' => json_encode(['type' => 'Point', 'coordinates' => [$x, $y]]), 'sumber_file' => 'titik-tanam.geojson']);
        }

        /* ---- log & setelan ---- */
        $nama = $this->pengaju?->name ?? 'Admin EMC';
        foreach ([[0, 'Tambah', 'Progres Mingguan', 'Blok 2 P2 — Minggu 4 November'], [0, 'Tambah', 'Bibit Keluar', 'Sengon Laut → Blok 4'],
            [1, 'Ubah', 'Stok Bibit', 'Meranti Merah · opname persemaian'], [2, 'Tambah', 'Bibit Masuk', 'Meranti Merah · hasil semai sendiri'],
            [3, 'Tambah', 'Berita Acara', 'BA serah terima bibit Blok 4'], [5, 'Ubah', 'Target Blok (Rencana)', 'Blok 6 P0 → 13,5 ha'],
            [6, 'Tambah', 'Bibit Mati', 'Jabon · serangan hama ulat'], [8, 'Tambah', 'Pengajuan Dana', 'PD-'.$th.'-015 Penanaman RDAS'],
            [10, 'Tambah', 'Surat Keluar/Masuk', 'Laporan progres rehabilitasi DAS ke BPDAS'], [13, 'Ubah', 'Penilaian Keberhasilan', 'Blok 3 — skor dihitung ulang'],
            [16, 'Tambah', 'Peta Spasial', 'Unggah blok-rdas.kml — 6 fitur'], [21, 'Tambah', 'Material Keluar', 'Pupuk NPK 16-16-16 → Blok 5'],
            [27, 'Ubah', 'Jaminan Reklamasi', 'Penempatan jaminan '.($th + 1).'–'.($th + 5)], [34, 'Tambah', 'Arsip File Capaian', 'Penanaman RDAS · PT Tunas Alam']]
            as [$h, $aksi, $modul, $detail]) {
            M\Log::withoutGlobalScopes()->create(['company_id' => $this->c->id, 'user_id' => $this->pengaju?->id, 'waktu' => $k->copy()->subDays($h)->subMinutes(23 + 47 * $h),
                'pengguna' => $h % 3 === 2 ? 'Mandor lapangan' : $nama, 'aksi' => $aksi, 'modul' => $modul, 'detail' => $detail]);
        }
        M\Setelan::withoutGlobalScopes()->create(['company_id' => $this->c->id, 'folder_drive' => null, 'catatan' => 'Isi tautan folder Google Drive arsip capaian di halaman Arsip File Capaian.']);
    }
}
