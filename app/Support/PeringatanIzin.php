<?php

namespace App\Support;

use App\Models\IzinKerja;
use Illuminate\Support\Collection;

/**
 * Peringatan izin kerja aman.
 *
 * Seluruh peringatan di sini berdiri di atas WAKTU, bukan di atas
 * status. Itulah yang membedakannya dari modul lain: sebuah izin dapat
 * berstatus disetujui — sah, ditandatangani, lengkap — dan pada saat
 * yang sama tidak lagi boleh dipakai, sebab jamnya sudah lewat atau uji
 * gasnya sudah basi. Peringatan yang hanya membaca status tidak akan
 * pernah menemukan keadaan itu.
 *
 * Tidak satu pun peringatan di sini menunggu tinjauan. Yang diperiksa
 * adalah izin yang SUDAH diterbitkan; menunda peringatannya sampai ada
 * yang meninjau berarti menunggu sampai pekerjaannya selesai.
 */
final class PeringatanIzin
{
    public const TINGGI = 'tinggi';
    public const SEDANG = 'sedang';

    /**
     * @param Collection<int,IzinKerja> $izin
     * @param array<string,array<string,mixed>> $ambang
     * @return list<array{kode:string,level:string,judul:string,ket:string,saran:string}>
     */
    public static function susun(Collection $izin, array $ambang, bool $ambangDitetapkan, int $tanpaSyarat = 0): array
    {
        $p = [];

        foreach (self::lewatWaktu($izin) as $q) $p[] = $q;
        foreach (self::ujiGas($izin, $ambang) as $q) $p[] = $q;
        foreach (self::bentrok($izin) as $q) $p[] = $q;
        foreach (self::mutuAcuan($izin, $ambangDitetapkan, $tanpaSyarat) as $q) $p[] = $q;

        return $p;
    }

    /** @param Collection<int,IzinKerja> $izin */
    private static function lewatWaktu(Collection $izin): array
    {
        $p = [];

        foreach ($izin->filter(fn (IzinKerja $i) => $i->lewatBelumDitutup()) as $i) {
            $lewat = abs((int) $i->keadaanWaktu()['sisaMenit']);

            $p[] = [
                'kode'  => 'belum-ditutup-'.$i->id,
                'level' => self::TINGGI,
                'judul' => "{$i->nomor}: lewat waktu {$lewat} menit dan belum ditutup",
                'ket'   => 'Pekerjaannya mungkin sudah selesai, mungkin masih berjalan — dan tidak ada yang tahu '
                           .'yang mana. Selama belum ditutup, '.$i->lokasi.' tercatat masih di bawah izin, '
                           .'sehingga izin berikutnya diterbitkan di atas keadaan yang dikira aman.',
                'saran' => 'Hubungi '.($i->pengawas_lapangan ?: 'pengawas lapangan')
                           .', pastikan area sudah bersih dan orang sudah keluar, lalu tutup izinnya.',
            ];
        }

        return $p;
    }

    /** @param Collection<int,IzinKerja> $izin */
    private static function ujiGas(Collection $izin, array $ambang): array
    {
        $p = [];

        foreach ($izin->filter(fn (IzinKerja $i) => $i->sedangBerlaku() && $i->perluUjiGas()) as $i) {
            $uji = $i->ujiTerakhir();

            if (!$uji) {
                $p[] = [
                    'kode'  => 'gas-tak-ada-'.$i->id,
                    'level' => self::TINGGI,
                    'judul' => "{$i->nomor}: berlaku tanpa satu pun uji gas",
                    'ket'   => 'Jenis izin ini menuntut uji gas, dan tidak ada pengukuran sama sekali pada berkasnya.',
                    'saran' => 'Hentikan pekerjaan sampai atmosfer diukur dan hasilnya dicatat di sini.',
                ];
                continue;
            }

            if (!$uji->segar(null, $i->batasUji())) {
                $usia = Izin::usiaUji($uji->waktu_uji);
                $p[] = [
                    'kode'  => 'gas-basi-'.$i->id,
                    'level' => self::TINGGI,
                    'judul' => "{$i->nomor}: uji gas terakhir sudah ".number_format((float) $usia, 0).' menit',
                    'ket'   => 'Batasnya '.$i->batasUji().' menit. Kadar gas berubah mengikuti ventilasi dan '
                               .'pekerjaannya; angka yang benar tadi tidak menyatakan apa pun tentang keadaan sekarang.',
                    'saran' => 'Ukur ulang sebelum pekerjaan dilanjutkan, dan ulangi setiap kali regu keluar-masuk.',
                ];
                continue;
            }

            $h = $uji->periksa($ambang);

            if (!$h['lulus']) {
                $keluar = collect($h['rinci'])->where('keadaan', 'di-luar-ambang')
                    ->map(fn ($r) => $r['nama'].' '.$r['nilai'].' '.$r['satuan'])->implode(', ');

                $p[] = [
                    'kode'  => 'gas-luar-ambang-'.$i->id,
                    'level' => self::TINGGI,
                    'judul' => "{$i->nomor}: bacaan gas di luar ambang",
                    'ket'   => $keluar.'. Izin ini sedang berlaku.',
                    'saran' => 'Hentikan pekerjaan, keluarkan orang, dan ventilasi ulang sebelum diukur kembali.',
                ];
            }

            if (!$h['lengkap']) {
                $p[] = [
                    'kode'  => 'gas-tak-lengkap-'.$i->id,
                    'level' => self::SEDANG,
                    'judul' => "{$i->nomor}: ada parameter gas yang belum diukur",
                    'ket'   => 'Parameter yang tidak diukur bukan parameter yang aman — formulir yang seluruhnya '
                               .'hijau di sini memberi rasa aman yang berasal dari ketiadaan datanya.',
                    'saran' => 'Lengkapi pengukuran seluruh parameter pada uji berikutnya.',
                ];
            }
        }

        return $p;
    }

    /**
     * Izin yang tidak boleh berjalan bersamaan di lokasi yang sama.
     *
     * @param Collection<int,IzinKerja> $izin
     */
    private static function bentrok(Collection $izin): array
    {
        $p = [];

        // Hanya yang sudah diterbitkan dan belum ditutup: dua draf yang
        // bertabrakan belum menjadi kejadian, dan menandainya lebih dulu
        // hanya melatih orang mengabaikan peringatan.
        $hidup = $izin->filter(fn (IzinKerja $i) => $i->sudahDisetujui() && !$i->sudahDitutup())->values();

        foreach ($hidup as $x => $a) {
            foreach ($hidup->slice($x + 1) as $b) {
                if (mb_strtolower(trim((string) $a->lokasi)) !== mb_strtolower(trim((string) $b->lokasi))) continue;
                if (!Izin::bentrok($a->jenis, $b->jenis)) continue;
                if (!Izin::tumpangTindih($a->mulai, $a->selesai, $b->mulai, $b->selesai)) continue;

                $p[] = [
                    'kode'  => 'bentrok-'.min($a->id, $b->id).'-'.max($a->id, $b->id),
                    'level' => self::TINGGI,
                    'judul' => "{$a->nomor} dan {$b->nomor} berbarengan di {$a->lokasi}",
                    'ket'   => Izin::alasanBentrok($a->jenis, $b->jenis)
                               .' Keduanya sah bila dilihat sendiri-sendiri, dan hanya berbahaya bila dilihat bersama.',
                    'saran' => 'Atur giliran agar keduanya tidak berjalan bersamaan, atau tinjau ulang salah satu '
                               .'bersama penerbit izin — penyaring ini menandai, bukan memutuskan.',
                ];
            }
        }

        return $p;
    }

    /** @param Collection<int,IzinKerja> $izin */
    private static function mutuAcuan(Collection $izin, bool $ambangDitetapkan, int $tanpaSyarat): array
    {
        $p = [];

        $menunggu = $izin->filter(fn (IzinKerja $i) => $i->menungguTinjauan());
        if ($menunggu->isNotEmpty()) {
            $mendesak = $menunggu->filter(fn (IzinKerja $i) => $i->mulai && $i->mulai->isPast());

            $p[] = [
                'kode'  => 'menunggu-penerbitan',
                'level' => $mendesak->isNotEmpty() ? self::TINGGI : self::SEDANG,
                'judul' => $menunggu->count().' izin menunggu penerbitan'
                           .($mendesak->isNotEmpty() ? ', '.$mendesak->count().' di antaranya sudah lewat jam mulainya' : ''),
                'ket'   => $mendesak->isNotEmpty()
                    ? 'Izin yang jam mulainya sudah lewat tetapi belum diterbitkan berarti pekerjaannya '
                      .'belum boleh dimulai — atau sudah dimulai tanpa izin.'
                    : 'Selama belum diterbitkan, pekerjaannya belum boleh dimulai.',
                'saran' => 'Tinjau permohonan yang jam mulainya paling dekat lebih dulu.',
            ];
        }

        if (!$ambangDitetapkan) {
            $p[] = [
                'kode'  => 'ambang-gas-bawaan',
                'level' => self::SEDANG,
                'judul' => 'Ambang gas masih memakai nilai bawaan',
                'ket'   => 'Nilai bawaan lazim dipakai luas, tetapi yang mengikat adalah prosedur perusahaan '
                           .'dan ketentuan yang berlaku di wilayahnya.',
                'saran' => 'Isikan ambang O₂, LEL, CO, dan H₂S dari prosedur ruang terbatas perusahaan beserta acuannya.',
            ];
        }

        if ($tanpaSyarat > 0) {
            $p[] = [
                'kode'  => 'jenis-tanpa-syarat',
                'level' => self::SEDANG,
                'judul' => $tanpaSyarat.' jenis izin belum punya daftar periksa',
                'ket'   => 'Izin jenis itu dapat diterbitkan tanpa satu pun syarat wajib yang menghalanginya — '
                           .'penerbitan menjadi tanda tangan, bukan pemeriksaan.',
                'saran' => 'Susun daftar periksa tiap jenis dari prosedur izin kerja perusahaan.',
            ];
        }

        return $p;
    }
}
