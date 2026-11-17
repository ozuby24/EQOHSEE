<?php

namespace App\Support\Investigasi;

use Illuminate\Support\Facades\DB;

/**
 * Master data modul Investigasi — kerangka resmi, bukan karangan sendiri.
 *
 * Ditaruh di kelas ini alih-alih di seeder karena dipakai dua kali: saat
 * pemasangan pertama, dan saat isinya perlu diperbarui — taksonomi
 * bertambah, batas hari berubah karena revisi regulasi — tanpa migrasi
 * baru. Penyimpanannya IDEMPOTEN berdasar kode: baris yang sudah ada
 * diperbarui, tidak digandakan, dan tidak ada yang dihapus.
 *
 * Idempotensinya bukan kerapian. Perintah pemasangan akan dijalankan
 * lagi setiap kali aplikasinya dipasang ulang atau diperbarui, dan
 * seeder yang menggandakan membuat kamus 252 butir menjadi 504 —
 * lalu rekap "penyebab terbanyak" menghitung tiap penyebab dua kali.
 *
 * ACUAN REGULASI — jangan diubah tanpa memeriksa teks resminya:
 *   · Kepmen ESDM 1827 K/30/MEM/2018 Lampiran III — batas hari cidera.
 *   · Kepdirjen Minerba 185/2019 — lima kriteria kecelakaan tambang,
 *     kewajiban penyelidikan KTT/PTL, dan tabel hari hilang.
 */
final class MasterInvestigasi
{
    /**
     * Klasifikasi cedera.
     *
     * Menggabungkan istilah regulasi Indonesia (ringan/berat/mati)
     * dengan istilah statistik industri (near miss, FAI, MTI, RWC, LTI).
     * Keduanya dipakai bersamaan di lapangan: yang pertama untuk laporan
     * ke Inspektur Tambang, yang kedua untuk menghitung FR/SR dan
     * membandingkannya dengan kontraktor lain.
     */
    public static function klasifikasiCedera(): array
    {
        return [
            ['kode' => 'near_miss', 'nama' => 'Nyaris Celaka (Near Miss)', 'hari_min' => null, 'hari_maks' => null, 'hari_hilang_standar' => 0, 'urutan' => 1,
             'keterangan' => 'Kejadian yang berpotensi mencederai tetapi tidak menimbulkan cedera maupun kerusakan.'],
            ['kode' => 'fai', 'nama' => 'Pertolongan Pertama (FAI)', 'hari_min' => 0, 'hari_maks' => 1, 'hari_hilang_standar' => 0, 'urutan' => 2,
             'keterangan' => 'Cukup ditangani dengan pertolongan pertama; pekerja kembali bertugas pada hari yang sama.'],
            ['kode' => 'ringan', 'nama' => 'Cidera Ringan', 'hari_min' => 2, 'hari_maks' => 20, 'hari_hilang_standar' => null, 'urutan' => 3,
             'keterangan' => 'Kepmen ESDM 1827/2018: tidak mampu melakukan tugas semula lebih dari 1 hari dan kurang dari 3 minggu, termasuk hari Minggu dan hari libur.'],
            ['kode' => 'mti', 'nama' => 'Perawatan Medis (MTI)', 'hari_min' => null, 'hari_maks' => null, 'hari_hilang_standar' => null, 'urutan' => 4,
             'keterangan' => 'Memerlukan perawatan medis melebihi pertolongan pertama tanpa kehilangan hari kerja.'],
            ['kode' => 'rwc', 'nama' => 'Pembatasan Kerja (RWC)', 'hari_min' => null, 'hari_maks' => null, 'hari_hilang_standar' => null, 'urutan' => 5,
             'keterangan' => 'Pekerja masih bekerja tetapi dengan tugas atau jam yang dibatasi.'],
            ['kode' => 'berat', 'nama' => 'Cidera Berat', 'hari_min' => 21, 'hari_maks' => null, 'hari_hilang_standar' => null, 'urutan' => 6,
             'keterangan' => 'Kepmen ESDM 1827/2018: tidak mampu bertugas 3 minggu atau lebih, atau cacat tetap, atau keretakan tengkorak/tulang punggung/pinggul, pendarahan dalam, dan sejenisnya.'],
            ['kode' => 'lti', 'nama' => 'Kehilangan Hari Kerja (LTI)', 'hari_min' => 1, 'hari_maks' => null, 'hari_hilang_standar' => null, 'urutan' => 7,
             'keterangan' => 'Cedera yang menyebabkan pekerja kehilangan sedikitnya satu hari kerja penuh.'],
            ['kode' => 'cacat_tetap', 'nama' => 'Cacat Tetap', 'hari_min' => null, 'hari_maks' => null, 'hari_hilang_standar' => 6000, 'urutan' => 8,
             'keterangan' => 'Cacat tetap total. Hari hilang dihitung setara 6.000 hari sesuai tabel Kepdirjen Minerba 185/2019, bukan hari absen sebenarnya.'],
            ['kode' => 'mati', 'nama' => 'Mati', 'hari_min' => null, 'hari_maks' => null, 'hari_hilang_standar' => 6000, 'urutan' => 9,
             'keterangan' => 'Kecelakaan yang mengakibatkan kematian. Hari hilang setara 6.000 hari.'],
        ];
    }

    /**
     * Klasifikasi menurut regulasi.
     *
     * Kecelakaan tambang dan kejadian berbahaya sama-sama wajib
     * dilaporkan ke Kepala Inspektur Tambang; penyakit akibat kerja
     * mengikuti jalur pelaporan tersendiri.
     */
    public static function klasifikasiRegulasi(): array
    {
        return [
            ['kode' => 'kecelakaan_tambang', 'nama' => 'Kecelakaan Tambang', 'wajib_lapor_kait' => true, 'urutan' => 1,
             'keterangan' => 'Memenuhi kelima kriteria Kepdirjen Minerba 185/2019. Wajib dilaporkan ke Kepala Inspektur Tambang dan diselidiki KTT/PTL.'],
            ['kode' => 'kejadian_berbahaya', 'nama' => 'Kejadian Berbahaya', 'wajib_lapor_kait' => true, 'urutan' => 2,
             'keterangan' => 'Kejadian yang dapat membahayakan jiwa atau menghambat produksi, meskipun tidak ada korban.'],
            ['kode' => 'pak', 'nama' => 'Penyakit Akibat Kerja (PAK)', 'wajib_lapor_kait' => true, 'urutan' => 3,
             'keterangan' => 'Penyakit yang timbul karena hubungan kerja atau paparan di tempat kerja.'],
            ['kode' => 'kejadian_akibat_pak', 'nama' => 'Kejadian Akibat PAK', 'wajib_lapor_kait' => true, 'urutan' => 4,
             'keterangan' => 'Kejadian yang dipicu oleh penyakit akibat kerja yang sudah diderita pekerja.'],
            ['kode' => 'bukan_kecelakaan_tambang', 'nama' => 'Bukan Kecelakaan Tambang', 'wajib_lapor_kait' => false, 'urutan' => 5,
             'keterangan' => 'Tidak memenuhi kelima kriteria. Tetap diinvestigasi internal; bila menimpa pekerja non-tambang, ikuti jalur Disnaker/BPJS.'],
        ];
    }

    public static function jenisInsiden(): array
    {
        return [
            ['kode' => 'cedera',     'nama' => 'Cedera Pekerja',                         'kelompok' => 'injury',      'urutan' => 1],
            ['kode' => 'nyaris',     'nama' => 'Nyaris Celaka',                          'kelompok' => 'injury',      'urutan' => 2],
            ['kode' => 'kerusakan',  'nama' => 'Kerusakan Properti / Unit',              'kelompok' => 'property',    'urutan' => 3],
            ['kode' => 'tabrakan',   'nama' => 'Tabrakan / Insiden Lalu Lintas Tambang', 'kelompok' => 'property',    'urutan' => 4],
            ['kode' => 'kebakaran',  'nama' => 'Kebakaran / Peledakan',                  'kelompok' => 'process',     'urutan' => 5],
            ['kode' => 'longsor',    'nama' => 'Longsor / Kegagalan Lereng',             'kelompok' => 'process',     'urutan' => 6],
            ['kode' => 'lingkungan', 'nama' => 'Pencemaran Lingkungan',                  'kelompok' => 'environment', 'urutan' => 7],
            ['kode' => 'keamanan',   'nama' => 'Gangguan Keamanan',                      'kelompok' => 'security',    'urutan' => 8],
            ['kode' => 'pak',        'nama' => 'Penyakit Akibat Kerja',                  'kelompok' => 'injury',      'urutan' => 9],
        ];
    }

    public static function hierarkiKendali(): array
    {
        return [
            ['kode' => 'eliminasi',     'nama' => 'Eliminasi',                   'tingkat' => 1, 'keterangan' => 'Menghilangkan sumber bahaya sepenuhnya. Paling efektif dan paling permanen.'],
            ['kode' => 'substitusi',    'nama' => 'Substitusi',                  'tingkat' => 2, 'keterangan' => 'Mengganti dengan bahan, alat, atau proses yang lebih rendah risikonya.'],
            ['kode' => 'rekayasa',      'nama' => 'Rekayasa Teknis',             'tingkat' => 3, 'keterangan' => 'Pengaman fisik, interlock, ventilasi, pemisahan pejalan kaki dari unit.'],
            ['kode' => 'administratif', 'nama' => 'Pengendalian Administratif',  'tingkat' => 4, 'keterangan' => 'Prosedur, izin kerja, pelatihan, rambu, rotasi kerja.'],
            ['kode' => 'apd',           'nama' => 'Alat Pelindung Diri',         'tingkat' => 5, 'keterangan' => 'Lapis terakhir. Tidak menghilangkan bahaya, hanya mengurangi akibatnya pada satu orang.'],
        ];
    }

    /**
     * Matriks risiko 5×5.
     *
     * Pita dan level dihitung dari skor, dengan SATU PENGECUALIAN yang
     * disengaja: keparahan 5 selalu menjadi L4 berapa pun
     * kemungkinannya. Kejadian fatal yang "kecil kemungkinannya" tetap
     * menuntut investigasi penuh — itulah seluruh alasan investigasi
     * kejadian berpotensi tinggi ada.
     */
    public static function matriksRisiko(): array
    {
        $baris = [];

        for ($k = 1; $k <= 5; $k++) {          // kemungkinan
            for ($p = 1; $p <= 5; $p++) {      // keparahan
                $skor = $k * $p;

                if ($p === 5)          { $pita = 'kritis'; $level = 'L4'; }
                elseif ($skor >= 15)   { $pita = 'kritis'; $level = 'L4'; }
                elseif ($skor >= 9)    { $pita = 'tinggi'; $level = 'L3'; }
                elseif ($skor >= 4)    { $pita = 'sedang'; $level = 'L2'; }
                else                   { $pita = 'rendah'; $level = 'L1'; }

                $baris[] = [
                    'kemungkinan' => $k, 'keparahan' => $p, 'skor' => $skor,
                    'pita' => $pita, 'level_investigasi' => $level,
                ];
            }
        }

        return $baris;
    }

    /* ═══════════ pemasangan ═══════════ */

    /**
     * Pasang seluruh master. Aman dijalankan berulang.
     *
     * @return array<string,int> nama tabel => jumlah baris sesudahnya
     */
    public static function pasang(): array
    {
        $hasil = [];

        $hasil['inv_jenis_insiden']        = self::simpan('inv_jenis_insiden', self::jenisInsiden(), ['kode']);
        $hasil['inv_klasifikasi_cedera']   = self::simpan('inv_klasifikasi_cedera', self::klasifikasiCedera(), ['kode']);
        $hasil['inv_klasifikasi_regulasi'] = self::simpan('inv_klasifikasi_regulasi', self::klasifikasiRegulasi(), ['kode']);
        $hasil['inv_hierarki_kendali']     = self::simpan('inv_hierarki_kendali', self::hierarkiKendali(), ['kode']);
        $hasil['inv_matriks_risiko']       = self::simpan('inv_matriks_risiko', self::matriksRisiko(), ['kemungkinan', 'keparahan']);
        $hasil['inv_taksonomi']            = KamusScat::pasang();
        $hasil['inv_wawancara_pertanyaan'] = PanduanWawancara::pasang();

        return $hasil;
    }

    /**
     * Simpan sekumpulan baris secara idempoten.
     *
     * Kunci pengenalnya disebut eksplisit, bukan ditebak dari kolom
     * pertama: matriks risiko dikenali dari PASANGAN kemungkinan dan
     * keparahan, dan menebaknya sebagai satu kolom akan membuat lima
     * baris menimpa satu sama lain.
     *
     * @param  list<array<string,mixed>>  $baris
     * @param  list<string>  $kunci
     */
    private static function simpan(string $tabel, array $baris, array $kunci): int
    {
        foreach ($baris as $b) {
            $cari = [];
            foreach ($kunci as $k) $cari[$k] = $b[$k];

            DB::table($tabel)->updateOrInsert(
                $cari,
                array_diff_key($b, $cari) + ['updated_at' => now(), 'created_at' => now()],
            );
        }

        return DB::table($tabel)->count();
    }
}
