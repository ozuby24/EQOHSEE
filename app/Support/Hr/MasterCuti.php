<?php

namespace App\Support\Hr;

use Illuminate\Support\Facades\DB;

/**
 * Jenis cuti awal bersama — isinya pasal undang-undang.
 *
 * SELURUHNYA TANPA PEMILIK, sehingga perusahaan yang baru dipasang
 * langsung punya daftar pilih tanpa seorang pun mengetiknya lebih
 * dahulu. Yang PKB-nya lebih longgar menyunting lamanya; yang
 * disunting adalah barisnya, bukan salinannya.
 *
 * IDEMPOTEN, DIKENALI LEWAT `kunci`. Alasannya sama persis dengan
 * MasterMiners dan MasterRoster: dikenali lewat nama, satu penggantian
 * nama melahirkan kembar pada tiap penerapan — dan pengajuan cuti yang
 * sudah ada menunjuk ke baris yang lama.
 *
 * ANGKANYA BUKAN KARANGAN. Tiap baris di bawah menyebut pasalnya, dan
 * pasal itu ikut tersimpan: yang menolak sebuah pengajuan harus dapat
 * menunjukkan dasarnya, dan "sistem menolak" bukan dasar.
 */
final class MasterCuti
{
    /**
     * kunci, kode, nama, dasar, hari, potong saldo, berbayar,
     * perlu bukti, akrual, carry-over maks, keadaan roster.
     *
     * CUTI TAHUNAN SATU-SATUNYA YANG MEMOTONG SALDO. Pasal 93 ayat (4)
     * menyebut izin khusus sebagai upah yang tetap dibayar, bukan cuti
     * tahunan yang dipakai — dipotong, seorang yang ayahnya meninggal
     * kehilangan dua hari cuti tahunannya.
     *
     * @var list<array{0:string,1:string,2:string,3:string,4:?int,5:bool,6:bool,7:bool,8:bool,9:int,10:string}>
     */
    public const JENIS = [
        ['tahunan', 'CT', 'Cuti tahunan',
         'UU 13/2003 pasal 79 ayat (2) huruf c', 12, true, true, false, true, 6, 'cuti'],

        /* Lamanya NULL: sepanjang surat dokternya. Dipatok, pekerja
           yang dirawat sepuluh hari harus mengajukan tiga kali. */
        ['sakit', 'CS', 'Sakit dengan surat dokter',
         'UU 13/2003 pasal 93 ayat (2) huruf a', null, false, true, true, false, 0, 'sakit'],

        ['melahirkan', 'CM', 'Istirahat melahirkan',
         'UU 13/2003 pasal 82 ayat (1)', 90, false, true, true, false, 0, 'cuti'],

        ['keguguran', 'CG', 'Istirahat keguguran',
         'UU 13/2003 pasal 82 ayat (2)', 45, false, true, true, false, 0, 'cuti'],

        ['haid', 'CH', 'Cuti haid hari pertama dan kedua',
         'UU 13/2003 pasal 81 ayat (1)', 2, false, true, false, false, 0, 'izin'],

        ['menikah', 'IM', 'Pekerja menikah',
         'UU 13/2003 pasal 93 ayat (4) huruf a', 3, false, true, false, false, 0, 'izin'],

        ['menikahkan-anak', 'IA', 'Menikahkan anak',
         'UU 13/2003 pasal 93 ayat (4) huruf b', 2, false, true, false, false, 0, 'izin'],

        ['khitan-anak', 'IK', 'Mengkhitankan atau membaptiskan anak',
         'UU 13/2003 pasal 93 ayat (4) huruf c dan d', 2, false, true, false, false, 0, 'izin'],

        ['istri-melahirkan', 'IL', 'Istri melahirkan atau keguguran',
         'UU 13/2003 pasal 93 ayat (4) huruf e', 2, false, true, false, false, 0, 'izin'],

        ['duka-inti', 'ID', 'Suami/istri, orang tua/mertua, anak, atau menantu meninggal',
         'UU 13/2003 pasal 93 ayat (4) huruf f', 2, false, true, false, false, 0, 'izin'],

        ['duka-serumah', 'IR', 'Anggota keluarga dalam satu rumah meninggal',
         'UU 13/2003 pasal 93 ayat (4) huruf g', 1, false, true, false, false, 0, 'izin'],

        /* Tanpa upah, dan karena itu tidak memotong saldo pula:
           saldonya memang tidak dipakai. */
        ['tanpa-upah', 'ITU', 'Izin tanpa upah',
         'Kesepakatan kerja / PKB', null, false, false, false, false, 0, 'izin'],
    ];

    /** @return array<string,int> nama tabel => jumlah baris sesudahnya */
    public static function pasang(): array
    {
        $saat = now();

        foreach (self::JENIS as $i => [
            $kunci, $kode, $nama, $dasar, $hari, $potong, $bayar, $bukti, $akrual, $carry, $keadaan,
        ]) {
            $ada = DB::table('hr_jenis_cuti')->whereNull('company_id')
                ->where('kunci', $kunci)->first();

            $acuan = [
                'kode'            => $kode,
                'dasar'           => $dasar,
                'hari'            => $hari,
                'potong_saldo'    => $potong,
                'berbayar'        => $bayar,
                'perlu_bukti'     => $bukti,
                'akrual'          => $akrual,
                'carry_over_maks' => $carry,
                'keadaan_roster'  => $keadaan,
                'urutan'          => ($i + 1) * 10,
            ];

            if ($ada) {
                /* NAMANYA TIDAK IKUT DIPERBARUI — ia mungkin sudah
                   disunting perusahaan yang memakainya. Yang diperbarui
                   hanya angka dan sifatnya, yang memang berasal dari
                   daftar ini. */
                DB::table('hr_jenis_cuti')->where('id', $ada->id)
                    ->update($acuan + ['updated_at' => $saat]);

                continue;
            }

            DB::table('hr_jenis_cuti')->insert($acuan + [
                'company_id' => null,
                'kunci'      => $kunci,
                'nama'       => $nama,
                'aktif'      => true,
                'created_at' => $saat,
                'updated_at' => $saat,
            ]);
        }

        return ['hr_jenis_cuti' => (int) DB::table('hr_jenis_cuti')->count()];
    }
}
