<?php

namespace App\Support;

use App\Models\Meranti as M;
use App\Support\Meranti\{Geo, Hitung, Katalog};

/**
 * Data contoh MERANTI — Reklamasi & Nursery.
 *
 * Polanya mengikuti lembar MERANTI: jenis tanaman,
 * material persemaian, pelaksana in-house dan vendor, project revegetasi
 * sampai penanaman RDAS, empat blok dengan target P0/P1/P2, dan progres
 * mingguan yang dibangkitkan dari target itu — sehingga Aktual Pencapaian
 * memperlihatkan blok yang sudah lewat target, yang tepat, dan yang
 * tertinggal. Satu bibit sengaja berstok nol supaya peringatan stok habis
 * di dasbor ikut tampil.
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

        /* ---- data master ---- */
        $tanaman = [
            ['Centrosema', 'Centrosema pubescens', 'Cover Crop'], ['Calopogonium', 'Calopogonium mucunoides', 'Cover Crop'],
            ['Sengon Laut', 'Falcataria moluccana', 'Pioneer'], ['Sengon Buto', 'Enterolobium cyclocarpum', 'Pioneer'],
            ['Trembesi', 'Albizia saman', 'Pioneer'], ['Jabon', 'Neolamarckia cadamba', 'Pioneer'], ['Akasia', 'Acacia mangium', 'Pioneer'],
            ['Johar', 'Senna siamea', 'Pioneer'], ['Kahoi', 'Shorea balangeran', 'Lokal'], ['Jelutung', 'Dyera costulata', 'Lokal'],
            ['Ulin', 'Eusideroxylon zwageri', 'Lokal'], ['Mahoni', 'Swietenia macrophylla', 'MPTS'], ['Kelor', 'Moringa oleifera', 'MPTS'],
        ];
        $buat(M\Tanaman::class, array_map(fn ($t) => ['nama_tanaman' => $t[0], 'nama_latin' => $t[1], 'golongan' => $t[2]], $tanaman));

        $buat(M\Project::class, [
            ['kode_project' => 'PRJ-01', 'nama_project' => 'Revegetasi', 'keterangan' => 'Penanaman area reklamasi dengan tanaman pioneer, lokal, dan sisipan'],
            ['kode_project' => 'PRJ-02', 'nama_project' => 'Pemeliharaan', 'keterangan' => 'Penyulaman tanaman mati, pemupukan, pendangiran, dan penyiangan gulma'],
            ['kode_project' => 'PRJ-03', 'nama_project' => 'Pengayaan', 'keterangan' => 'Penambahan jenis tanaman sisipan lokal'],
            ['kode_project' => 'PRJ-04', 'nama_project' => 'Event', 'keterangan' => 'Penanaman di luar project reklamasi'],
            ['kode_project' => 'PRJ-05', 'nama_project' => 'Penanaman RDAS', 'keterangan' => 'Penanaman rehabilitasi DAS'],
        ]);
        $pelaksana = [['EMC', 'In House'], ['Kelompok Tani Hijau', 'In House'], ['Tim Persemaian', 'In House'], ['CV Rimba Lestari', 'Vendor'], ['PT Tunas Alam', 'Vendor'], ['CV Bumi Asri', 'Vendor']];
        $buat(M\Pelaksana::class, array_map(fn ($p) => ['nama_pelaksana' => $p[0], 'kategori' => $p[1]], $pelaksana));
        $buat(M\ProjectVendor::class, [
            ['kode_project_vendor' => 'PV-01', 'nama_vendor' => 'CV Rimba Lestari', 'nama_project' => 'Revegetasi'],
            ['kode_project_vendor' => 'PV-02', 'nama_vendor' => 'CV Rimba Lestari', 'nama_project' => 'Pengayaan'],
            ['kode_project_vendor' => 'PV-03', 'nama_vendor' => 'PT Tunas Alam', 'nama_project' => 'Penanaman RDAS'],
        ]);

        /* ---- nursery ---- */
        $stok = [['Sengon Laut', 5730, 'Pioneer'], ['Kayu Putih', 1163, 'Pioneer'], ['Kelor', 1110, 'MPTS'], ['Mahoni', 840, 'MPTS'],
            ['Jabon', 655, 'Pioneer'], ['Kahoi', 420, 'Lokal'], ['Akasia', 380, 'Pioneer'], ['Trembesi', 190, 'Pioneer'], ['Ulin', 7, 'Lokal'], ['Merbau', 0, 'Lokal']];
        $buat(M\StokBibit::class, array_map(fn ($s) => ['nama_bibit' => $s[0], 'stok' => $s[1], 'golongan' => $s[2]], $stok));
        $buat(M\BibitMasuk::class, [
            ['tanggal' => $tgl(52), 'nama_bibit' => 'Sengon Laut', 'jumlah' => 2500, 'sumber' => 'Pengadaan CV Rimba Lestari', 'keterangan' => 'Polybag 15×15'],
            ['tanggal' => $tgl(24), 'nama_bibit' => 'Kahoi', 'jumlah' => 300, 'sumber' => 'Persemaian KPH', 'keterangan' => 'Jenis lokal sisipan'],
            ['tanggal' => $tgl(9), 'nama_bibit' => 'Mahoni', 'jumlah' => 600, 'sumber' => 'Hasil semai sendiri', 'keterangan' => null],
        ]);
        $buat(M\BibitKeluar::class, [
            ['tanggal' => $tgl(40), 'nama_bibit' => 'Sengon Laut', 'jumlah' => 1200, 'tujuan' => 'Blok 2', 'keterangan' => 'P0 revegetasi'],
            ['tanggal' => $tgl(18), 'nama_bibit' => 'Mahoni', 'jumlah' => 350, 'tujuan' => 'Blok 3', 'keterangan' => 'Pengayaan'],
            ['tanggal' => $tgl(6), 'nama_bibit' => 'Kahoi', 'jumlah' => 120, 'tujuan' => 'Blok 1', 'keterangan' => 'Penyulaman'],
        ]);
        $buat(M\BibitKembali::class, [['tanggal' => $tgl(15), 'nama_bibit' => 'Mahoni', 'jumlah' => 40, 'keterangan' => 'Sisa penanaman Blok 3']]);
        $buat(M\BibitMati::class, [
            ['tanggal' => $tgl(33), 'nama_bibit' => 'Kelor', 'jumlah' => 25, 'penyebab' => 'Kekeringan'],
            ['tanggal' => $tgl(11), 'nama_bibit' => 'Jabon', 'jumlah' => 12, 'penyebab' => 'Serangan hama ulat'],
        ]);
        $buat(M\BibitRusak::class, [['tanggal' => $tgl(20), 'nama_bibit' => 'Sengon Laut', 'jumlah' => 30, 'penyebab' => 'Polybag sobek saat angkut']]);

        /* ---- material ---- */
        $material = [['Pupuk TSP', 100, 'Kg'], ['Sarung Tangan Kain', 140, 'Pack'], ['Benih Kelor', 500, 'Gram'], ['Benih Mahoni', 5, 'Kg'],
            ['Cover Crop MB', 100, 'Kg'], ['Benih Eucalyptus', 2, 'Kg'], ['Pupuk MKP', 4, 'Kg'], ['Benih Vertiver', 1, 'Kg'],
            ['Polybag Uk.20x20 cm', 4, 'Kg'], ['Polybag Uk.15x15 cm', 23, 'Kg'], ['Kawat Duri', 3, 'Roll'], ['Ayakan Benih', 2, 'Pcs'],
            ['Fumyco (Pupuk Mikoriza)', 66, 'Kg'], ['CBA Stick', 11, 'Botol']];
        $buat(M\StokMaterial::class, array_map(fn ($m) => ['nama_material' => $m[0], 'material_stok' => $m[1], 'nama_satuan' => $m[2]], $material));
        $buat(M\MaterialMasuk::class, [
            ['tanggal' => $tgl(45), 'nama_material' => 'Pupuk TSP', 'jumlah' => 200, 'satuan' => 'Kg', 'sumber' => 'Gudang site'],
            ['tanggal' => $tgl(12), 'nama_material' => 'Polybag Uk.15x15 cm', 'jumlah' => 25, 'satuan' => 'Kg', 'sumber' => 'Pembelian lokal'],
        ]);
        $buat(M\MaterialKeluar::class, [
            ['tanggal' => $tgl(30), 'nama_material' => 'Pupuk TSP', 'jumlah' => 100, 'satuan' => 'Kg', 'tujuan' => 'Blok 2'],
            ['tanggal' => $tgl(8), 'nama_material' => 'Fumyco (Pupuk Mikoriza)', 'jumlah' => 14, 'satuan' => 'Kg', 'tujuan' => 'Blok 3'],
        ]);

        /* ---- rehabilitasi DAS: target dan progres mingguan ---- */
        // Rasio capaian per blok: Blok 1 tertinggal, Blok 2 tepat, Blok 3 lewat target, Blok 4 baru mulai.
        $blok = ['Blok 1' => [10, 8, 8, 0.62], 'Blok 2' => [12, 10, 10, 1.0], 'Blok 3' => [15, 12, 12, 1.12], 'Blok 4' => [9, 6, 6, 0.3]];
        $pelaksanaBlok = ['Blok 1' => 'Kelompok Tani Hijau', 'Blok 2' => 'CV Rimba Lestari', 'Blok 3' => 'Tim Persemaian', 'Blok 4' => 'PT Tunas Alam'];
        foreach ([$th - 1, $th] as $tahun) {
            $berjalan = $tahun === $th;
            $bulanAkhir = $berjalan ? max(1, (int) $k->month) : 12;
            foreach ($blok as $nama => [$p0, $p1, $p2, $rasio]) {
                foreach (['P0' => $p0, 'P1' => $p1, 'P2' => $p2] as $pkj => $luas) {
                    $this->merantiBuat(M\TargetBlok::class, ['nama_blok' => $nama, 'pekerjaan' => $pkj, 'luas_target' => $luas, 'tahun' => $tahun,
                        'keterangan' => $berjalan ? 'Rencana tahun berjalan' : null]);

                    // Realisasi disebar ke bulan-bulan berjalan, dua minggu sekali.
                    $sisa = round($luas * ($berjalan ? $rasio : min(1.0, $rasio + 0.2)), 2);
                    $langkah = max(1, intdiv($bulanAkhir, 4));
                    $bulanIsi = range(1, $bulanAkhir, $langkah);
                    foreach ($bulanIsi as $i => $bln) {
                        $bagian = $i === count($bulanIsi) - 1 ? $sisa : round($sisa / (count($bulanIsi) - $i), 2);
                        $sisa = round($sisa - $bagian, 2);
                        if ($bagian <= 0) continue;
                        $minggu = 1 + ($i % 4);
                        $this->merantiBuat(M\Progres::class, [
                            'tahun' => $tahun, 'bulan' => Katalog::BULAN[$bln - 1], 'minggu' => 'Minggu '.$minggu,
                            'tanggal' => sprintf('%04d-%02d-%02d', $tahun, $bln, min(28, $minggu * 7 - 2)), 'pelaksana' => $pelaksanaBlok[$nama],
                            'pekerjaan' => $pkj, 'nama_blok' => $nama, 'luas_realisasi' => $bagian,
                            'keterangan' => ['P0' => 'Penanaman', 'P1' => 'Pemeliharaan I', 'P2' => 'Pemeliharaan II'][$pkj].' '.$nama,
                        ]);
                    }
                }
            }
        }

        $buat(M\ArsipCapaian::class, [
            ['nama_project' => 'Revegetasi', 'kategori_pelaksana' => 'Vendor', 'pelaksana' => 'CV Rimba Lestari', 'tahun' => $th, 'bulan' => Katalog::BULAN[max(0, $k->month - 2)], 'week' => 'W2', 'file' => null, 'penginput' => 'Admin EMC'],
            ['nama_project' => 'Pemeliharaan', 'kategori_pelaksana' => 'In House', 'pelaksana' => 'EMC', 'tahun' => $th, 'bulan' => Katalog::BULAN[$k->month - 1], 'week' => 'W1', 'file' => null, 'penginput' => 'Admin EMC'],
            ['nama_project' => 'Pengayaan', 'kategori_pelaksana' => 'Vendor', 'pelaksana' => 'CV Rimba Lestari', 'tahun' => $th - 1, 'bulan' => 'Juli', 'week' => 'W5', 'file' => null, 'penginput' => 'Admin EMC'],
        ]);
        $buat(M\Penanaman::class, [
            ['tanggal' => $tgl(40), 'nama_bibit' => 'Sengon Laut', 'golongan' => 'Pioneer', 'jumlah' => 1200, 'nama_project' => 'Revegetasi', 'nama_kegiatan' => 'Revegetasi '.$th, 'pelaksana' => 'CV Rimba Lestari', 'nama_blok' => 'Blok 2', 'penginput' => 'Mandor lapangan'],
            ['tanggal' => $tgl(18), 'nama_bibit' => 'Mahoni', 'golongan' => 'MPTS', 'jumlah' => 310, 'nama_project' => 'Pengayaan', 'nama_kegiatan' => 'Pengayaan '.$th, 'pelaksana' => 'Tim Persemaian', 'nama_blok' => 'Blok 3', 'penginput' => 'Mandor EMC'],
            ['tanggal' => $tgl(6), 'nama_bibit' => 'Kahoi', 'golongan' => 'Lokal', 'jumlah' => 120, 'nama_project' => 'Pemeliharaan', 'nama_kegiatan' => 'Penyulaman Blok 1', 'pelaksana' => 'Kelompok Tani Hijau', 'nama_blok' => 'Blok 1', 'penginput' => 'Mandor EMC'],
        ]);

        /* ---- reklamasi ---- */
        $buat(M\RencanaReklamasi::class, [
            ['tahun' => $th - 2, 'luas_bukaan_ha' => 45.5, 'luas_reklamasi_ha' => 30.2, 'jumlah_bibit_rencana' => 18000, 'biaya_rencana' => 2_750_000_000, 'keterangan' => 'Tahap I'],
            ['tahun' => $th - 1, 'luas_bukaan_ha' => 52, 'luas_reklamasi_ha' => 41.8, 'jumlah_bibit_rencana' => 24500, 'biaya_rencana' => 3_200_000_000, 'keterangan' => 'Tahap II'],
            ['tahun' => $th, 'luas_bukaan_ha' => 60, 'luas_reklamasi_ha' => 48.5, 'jumlah_bibit_rencana' => 30000, 'biaya_rencana' => 3_850_000_000, 'keterangan' => 'Rencana tahun berjalan'],
        ]);
        foreach ([['Blok 2', $th - 1, 88, 82, 75], ['Blok 3', $th - 1, 90, 85, 80], ['Blok 1', $th - 2, 70, 58, 55]] as [$b, $t, $p, $r, $s]) {
            $this->merantiBuat(M\Penilaian::class, ['blok' => $b, 'tahun' => $t, 'nilai_penatagunaan' => $p, 'nilai_revegetasi' => $r, 'nilai_penyelesaian' => $s] + Hitung::matrik17($p, $r, $s));
        }
        $buat(M\Jaminan::class, [
            ['periode' => ($th - 2).'-'.($th + 2), 'jenis' => 'Jaminan Reklamasi', 'nilai' => 14_500_000_000, 'tanggal_penempatan' => ($th - 2).'-02-15', 'status' => 'Sebagian Dicairkan'],
            ['periode' => (string) ($th - 2), 'jenis' => 'Jaminan Pascatambang', 'nilai' => 8_200_000_000, 'tanggal_penempatan' => ($th - 2).'-03-10', 'status' => 'Ditempatkan'],
        ]);

        /* ---- administrasi ---- */
        $buat(M\Surat::class, [
            ['nomor_surat' => '021/EMC/'.$th, 'jenis' => 'Keluar', 'tanggal' => $tgl(35), 'perihal' => 'Permohonan penilaian keberhasilan reklamasi', 'dari_kepada' => 'Inspektur Tambang'],
            ['nomor_surat' => 'S.118/BPDAS/'.$th, 'jenis' => 'Masuk', 'tanggal' => $tgl(21), 'perihal' => 'Jadwal monitoring rehabilitasi DAS', 'dari_kepada' => 'BPDAS Mahakam Berau'],
        ]);
        $periode = 'Triwulan '.(int) ceil($k->month / 3).' '.$th;
        $buat(M\Periode::class, [
            ['nama_periode' => $periode, 'tanggal_mulai' => $k->copy()->firstOfQuarter()->toDateString(), 'tanggal_selesai' => $k->copy()->lastOfQuarter()->toDateString(), 'status' => 'Aktif'],
            ['nama_periode' => 'Triwulan sebelumnya', 'tanggal_mulai' => $k->copy()->subQuarter()->firstOfQuarter()->toDateString(), 'tanggal_selesai' => $k->copy()->subQuarter()->lastOfQuarter()->toDateString(), 'status' => 'Tutup'],
        ]);
        $buat(M\PengajuanDana::class, [
            ['nomor' => 'PD-'.$th.'-014', 'project' => 'Revegetasi', 'periode' => $periode, 'nilai' => 185_000_000, 'status' => 'Disetujui'],
            ['nomor' => 'PD-'.$th.'-015', 'project' => 'Pemeliharaan', 'periode' => $periode, 'nilai' => 64_500_000, 'status' => 'Diajukan'],
        ]);
        $buat(M\BeritaAcara::class, [
            ['nomor' => 'BA-'.$th.'-07', 'judul' => 'Serah terima bibit ke CV Rimba Lestari — Blok 2', 'tanggal' => $tgl(40), 'terkait' => 'Bibit Keluar · Blok 2'],
            ['nomor' => 'BA-'.$th.'-08', 'judul' => 'Pemeriksaan hasil P1 Blok 3', 'tanggal' => $tgl(14), 'terkait' => 'Progres Mingguan · Blok 3'],
        ]);

        /* ---- peta spasial: empat blok dan dua titik tanam ---- */
        [$lng0, $lat0] = [116.9512, -0.6218];
        foreach (array_keys($blok) as $i => $nama) {
            $cx = $lng0 + ($i % 2) * 0.0092 + intdiv($i, 2) * 0.0031;
            $cy = $lat0 + intdiv($i, 2) * 0.0074;
            $r = 0.0029 + $i * 0.0004;
            $cincin = [];
            for ($j = 0; $j < 9; $j++) {
                $sudut = $j / 9 * 2 * M_PI;
                $jari = $r * (1 + 0.16 * sin(3 * $sudut + $i));
                $cincin[] = [round($cx + $jari * cos($sudut) * 1.2, 6), round($cy + $jari * sin($sudut), 6)];
            }
            $cincin[] = $cincin[0];
            $this->merantiBuat(M\Peta::class, ['nama_fitur' => $nama, 'kategori' => 'Blok', 'petak' => 'Petak '.($i + 1), 'year' => $th - ($i === 3 ? 0 : 1),
                'luas_ha' => round(Geo::luasHa($cincin), 4), 'geojson' => json_encode(['type' => 'Polygon', 'coordinates' => [$cincin]]), 'sumber_file' => 'contoh-blok.geojson']);
        }
        foreach ([['Titik tanam Ulin', 0.0021, 0.0012], ['Titik tanam Kahoi', 0.0108, 0.0081]] as [$nama, $dx, $dy]) {
            $this->merantiBuat(M\Peta::class, ['nama_fitur' => $nama, 'kategori' => 'Titik Tanam', 'year' => $th,
                'geojson' => json_encode(['type' => 'Point', 'coordinates' => [round($lng0 + $dx, 6), round($lat0 + $dy, 6)]]), 'sumber_file' => 'contoh-titik.geojson']);
        }

        /* ---- log & setelan ---- */
        foreach ([[3, 'Tambah', 'Stok Bibit', 'Mahoni · 600 batang masuk'], [2, 'Ubah', 'Target Blok (Rencana)', 'Blok 3 P1 → 12 ha'], [1, 'Tambah', 'Progres Mingguan', 'Blok 2 P2']] as [$h, $aksi, $modul, $detail]) {
            M\Log::withoutGlobalScopes()->create(['company_id' => $this->c->id, 'user_id' => $this->pengaju?->id, 'waktu' => $k->copy()->subDays($h),
                'pengguna' => $this->pengaju?->name ?? 'Admin EMC', 'aksi' => $aksi, 'modul' => $modul, 'detail' => $detail]);
        }
        M\Setelan::withoutGlobalScopes()->create(['company_id' => $this->c->id, 'folder_drive' => null, 'catatan' => 'Isi tautan folder Google Drive arsip capaian di halaman Arsip File Capaian.']);
    }
}
