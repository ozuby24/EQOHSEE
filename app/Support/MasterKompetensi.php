<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Master 51 jenis kompetensi pertambangan.
 *
 * Sumber: sheet SUMMARY pada "Master List Sertifikasi PO/PT/TTK",
 * mengacu SK Dirjen Minerba 185.K/37.04/DJB/2019.
 *
 * KENAPA DAFTAR PILIH, BUKAN KETIK BEBAS. Pada aplikasi asalnya
 * kolom ini teks bebas, dan satu kompetensi yang sama tercatat sebagai
 * beberapa nama berbeda — "POP", "P.O.P", "Pengawas Operasional
 * Pertama", "pop". Rekap yang mengelompokkan menurut nama lalu
 * menghitungnya sebagai empat kompetensi berbeda, masing-masing satu
 * orang, dan tidak satu pun mencapai jumlah minimum yang dituntut
 * regulasi. Angkanya salah tanpa satu pun galat muncul.
 *
 * Ditanam sebagai baris ber-company_id NULL: daftarnya berasal dari
 * regulasi nasional dan sama bagi setiap perusahaan. Perusahaan yang
 * perlu menambah jenisnya sendiri membuat baris ber-company_id, dan
 * penanaman ini tidak akan menyentuhnya.
 */
final class MasterKompetensi
{
    /**
     * nama => lembaga penerbit.
     *
     * Lembaga null berarti sertifikasi sistem yang lembaganya
     * bermacam-macam (ISO), bukan lembaga yang belum diketahui.
     *
     * @var list<array{0:string,1:string|null}>
     */
    public const DAFTAR = [
            ['Pengawas Operasional Pertama (POP)', 'BNSP'],
            ['Pengawas Operasional Madya (POM)', 'BNSP'],
            ['Pengawas Operasional Utama (POU)', 'BNSP'],
            ['Juru Ukur', 'ESDM'],
            ['Juru las', 'BNSP'],
            ['Juru Bor', 'ESDM'],
            ['Juru Derek', 'ESDM'],
            ['Juru Rawat (STR) (BTCLS/ATCLS)', 'KEMENKES'],
            ['Sertifikasi HIPERKES', 'KEMENAKER'],
            ['Juru Langsir', 'ESDM'],
            ['Petugas Radiasi', 'ESDM'],
            ['Ahli Listrik', 'KEMENAKER'],
            ['Teknisi Listrik', 'BNSP'],
            ['Petugas P3K', 'KEMENAKER'],
            ['Ahli K3 Kebakaran Kelas A', 'KEMENAKER'],
            ['Ahli K3 Kebakaran Kelas B', 'KEMENAKER'],
            ['Ahli K3 Kebakaran Kelas C', 'KEMENAKER'],
            ['Ahli K3 Kebakaran Kelas D', 'KEMENAKER'],
            ['Training Rescue', 'BASARNAS'],
            ['HIMU', 'KEMENAKER'],
            ['HIMA', 'KEMENAKER'],
            ['HIU', 'KEMENAKER'],
            ['AHLI K3 LINGKER', 'KEMENAKER'],
            ['Loading Master', 'BNSP'],
            ['Rigger', 'BNSP'],
            ['SIO Kelas 1 ( Surat Ijin Operator ) beban > 50 Ton', 'KEMENAKER'],
            ['SIO Kelas 2 ( Surat Ijin Operator ) beban 25 - 50 Ton', 'KEMENAKER'],
            ['SIO Kelas 3 ( Surat Ijin Operator ) beban < 25 Ton', 'KEMENAKER'],
            ['KJL', 'ESDM'],
            ['KPP Madya', 'ESDM'],
            ['KPP Pratama', 'ESDM'],
            ['Sertifikasi Petugas Genset', 'KEMENAKER'],
            ['AK3U KEMENAKER', 'KEMENAKER'],
            ['AK3U BNSP', 'BNSP'],
            ['Petugas K3 Pertambangan', 'ESDM'],
            ['TOT LV 4', 'BNSP'],
            ['TOT LV 6', 'BNSP'],
            ['Implementasi SMKP', 'ESDM'],
            ['Auditor SMKP', 'ESDM'],
            ['SMK3', 'KEMENAKER'],
            ['ISO 14001', null],
            ['ISO 9001', null],
            ['Petugas Commissioning', 'ESDM'],
            ['PPPA', 'BNSP'],
            ['POIPAL', 'BNSP'],
            ['PCUA', 'BNSP'],
            ['PPPU', 'BNSP'],
            ['POIPU', 'BNSP'],
            ['PLB3', 'BNSP'],
            ['OPLB3', 'BNSP'],
            ['Lainnya', 'BNSP'],
    ];

    /**
     * Tanam atau perbarui seluruh daftar.
     *
     * Aman diulang: dicocokkan menurut nama pada lingkup bersama
     * (company_id NULL), jadi menjalankannya dua kali tidak
     * menggandakan apa pun — dan tidak menghapus jenis tambahan milik
     * perusahaan.
     */
    public static function tanam(): int
    {
        $n = 0;

        /* Lewat query builder, BUKAN model.
           
           KompetensiJenis memakai BerpemilikPerusahaan, yang mengisi
           company_id dari pengguna yang sedang masuk. Ditanam lewat
           model sementara seorang admin perusahaan A sedang masuk,
           kelima puluh satu barisnya menjadi MILIK perusahaan A — dan
           perusahaan B melihat daftar pilih kompetensinya kosong.
           Masternya nasional; ia tidak boleh ikut kepemilikan siapa pun
           yang kebetulan menjalankannya. */
        foreach (self::DAFTAR as $i => [$nama, $lembaga]) {
            DB::table('kompetensi_jenis')->updateOrInsert(
                ['company_id' => null, 'nama' => $nama],
                [
                    'lembaga'    => $lembaga,
                    'aktif'      => true,
                    'urutan'     => $i + 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
            $n++;
        }

        return $n;
    }
}
