<?php

namespace App\Support\Hr;

use App\Support\Miners\Acuan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pola roster awal bersama.
 *
 * Kelima pola di bawah adalah yang disebut PRD dan yang benar-benar
 * dipakai tambang Indonesia. Seluruhnya TANPA PEMILIK, sehingga
 * perusahaan yang baru dipasang langsung punya daftar pilih tanpa
 * seorang pun mengetiknya lebih dahulu.
 *
 * IDEMPOTEN, DIKENALI LEWAT `kunci`. Alasannya sama persis dengan
 * MasterMiners: dikenali lewat kode, satu penggantian nama melahirkan
 * kembar pada tiap deploy — dan `hr_regu.pola_roster_id` menunjuk ke
 * sini, sehingga regu yang sudah berjalan berpindah ke pola kembarnya
 * yang isinya bawaan.
 *
 * 14:7 BERJAM 11 MELAMPAUI BATAS 40 JAM SEMINGGU, dan itu memang
 * demikian adanya — 154 jam per 21 hari setara 51,3 jam seminggu.
 * Justru batas itulah yang dikecualikan Kepmenakertrans 234/2003 bagi
 * sektor ESDM di daerah tertentu. Pola ini sah; yang tidak sah adalah
 * melampaui 11 jam sehari atau 154 jam per 14 hari.
 */
final class MasterRoster
{
    /**
     * kode, nama, kerja, libur, satuan, jam, shift, libur mingguan,
     * jam mulai siang, jam mulai malam, toleransi menit.
     *
     * LIBUR MINGGUAN HANYA PADA POLA BERSATUAN MINGGU, dan itu bukan
     * selera. "10:2 minggu" berarti sepuluh minggu di site lalu dua
     * minggu pulang — di dalam sepuluh minggu itu tetap ada hari libur
     * mingguan. Dimodelkan sebagai tujuh puluh hari kerja tanpa jeda,
     * polanya melanggar batas empat belas hari Kepmenakertrans
     * 234/2003 pada tiap siklusnya, dan aplikasi menandai merah pola
     * yang justru dipakai sungguhan di lapangan.
     *
     * Pola 14:7 berlibur nol: empat belas hari penuh memang dikerjakan
     * berturut-turut, dan itu sah selama tidak melampaui empat belas.
     *
     * JAM MULAI SHIFT IKUT DI SINI, tidak dibiarkan kosong. Kolomnya
     * memang boleh kosong dan PolaRoster memakai bawaan bila demikian —
     * tetapi bawaan 07.00 salah satu jam bagi pola sebelas jam, yang di
     * lapangan mulai 06.00 supaya selesai 17.00. Dibiarkan kosong,
     * seluruh regu lapangan tercatat datang satu jam lebih awal setiap
     * hari, dan tidak satu pun keterlambatan pernah terlihat.
     *
     * @var list<array{0:string,1:string,2:int,3:int,4:string,5:int,6:string,7:int,8:string,9:string,10:int}>
     */
    public const POLA = [
        ['14:7',  'Empat belas hari kerja, tujuh hari libur', 14, 7, 'hari',   11, 'putar', 0, '06:00', '18:00', 15],
        ['10:2',  'Sepuluh minggu di site, dua minggu pulang', 10, 2, 'minggu', 8, 'siang', 1, '07:00', '19:00', 15],
        ['8:2',   'Delapan minggu di site, dua minggu pulang',  8, 2, 'minggu', 8, 'siang', 1, '07:00', '19:00', 15],
        ['6:2',   'Enam minggu di site, dua minggu pulang',     6, 2, 'minggu', 8, 'siang', 1, '07:00', '19:00', 15],
        ['4:1',   'Empat hari kerja, satu hari libur',          4, 1, 'hari',    8, 'siang', 0, '07:00', '19:00', 15],
        ['5:2',   'Lima hari kerja kantor, dua hari libur',     5, 2, 'hari',    8, 'siang', 0, '08:00', '20:00', 20],
    ];

    /** @return array<string,int> nama tabel => jumlah baris sesudahnya */
    public static function pasang(): array
    {
        $saat = now();

        foreach (self::POLA as $i => [$kode, $nama, $kerja, $libur, $satuan, $jam, $shift, $liburMingguan, $mulaiSiang, $mulaiMalam, $toleransi]) {
            $kunci = Str::slug($kode);

            $ada = DB::table('hr_pola_roster')->whereNull('company_id')
                ->where('kunci', $kunci)->first();

            /* Jembatan sekali jalan bagi baris yang sudah ada sejak
               sebelum kolom `kunci` dipakai — sama seperti pada
               MasterMiners. Tanpa itu, pemasangan pertama pada basis
               data yang polanya sudah diketik tangan menggandakan
               seluruh daftarnya. */
            if (! $ada) {
                $ada = DB::table('hr_pola_roster')->whereNull('company_id')
                    ->whereNull('kunci')
                    ->whereRaw('LOWER(kode) = ?', [mb_strtolower($kode)])
                    ->first();
            }

            $acuan = [
                'kerja'  => $kerja,
                'libur'  => $libur,
                'satuan' => $satuan,
                'jam'    => $jam,
                'shift'  => $shift,
                'libur_mingguan'  => $liburMingguan,
                'mulai_siang'     => $mulaiSiang,
                'mulai_malam'     => $mulaiMalam,
                'toleransi_menit' => $toleransi,
                'urutan' => ($i + 1) * 10,
            ];

            if ($ada) {
                /* Namanya TIDAK ikut diperbarui — ia mungkin sudah
                   disunting perusahaan yang memakainya. Yang diperbarui
                   hanya bentuk siklusnya, yang memang berasal dari
                   daftar ini. */
                DB::table('hr_pola_roster')->where('id', $ada->id)
                    ->update($acuan + ['kunci' => $kunci, 'updated_at' => $saat]);

                continue;
            }

            DB::table('hr_pola_roster')->insert($acuan + [
                'company_id' => null,
                'kunci'      => $kunci,
                'kode'       => $kode,
                'nama'       => $nama,
                'aktif'      => true,
                'created_at' => $saat,
                'updated_at' => $saat,
            ]);
        }

        return ['hr_pola_roster' => (int) DB::table('hr_pola_roster')->count()];
    }
}
