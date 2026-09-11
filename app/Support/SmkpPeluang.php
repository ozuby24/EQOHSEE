<?php

namespace App\Support;

use App\Models\SmkpAudit;

/**
 * Butir mana yang berhak memperoleh OFI, dan mengapa hanya yang itu.
 *
 * OFI — Opportunity For Improvement — bukan temuan ringan. Ia kebalikan
 * temuan: catatan bagi butir yang sudah MEMENUHI SELURUH kriteria,
 * berisi peluang melampauinya. Karena itu syaratnya satu dan keras,
 * capaian 100%.
 *
 * SYARATNYA DIJAGA DI SERVER, BUKAN DI LAYAR. Formulirnya memang hanya
 * menawarkan butir yang berhak, tetapi penjagaan yang hanya ada di
 * peramban dilewati satu permintaan yang disusun tangan — dan lembar
 * OFI yang memuat butir bernilai 40% adalah lembar yang menyatakan
 * kebalikan dari keadaan sebenarnya, ditandatangani ketua tim auditor,
 * dan diserahkan kepada Inspektur Tambang.
 *
 * DUA LINGKUP, DAN KEDUANYA SAH.
 *
 * Sub-elemen berincian dinilai lewat rinciannya. Sebuah rincian dapat
 * sempurna sementara sub-elemennya belum — dan peluang perbaikan atas
 * rincian itu tetap sah dicatat. Sebaliknya sub-elemen yang seluruh
 * rinciannya sempurna juga berhak, dengan peluang yang berbicara
 * tentang sistemnya alih-alih satu langkah di dalamnya.
 *
 * Sub-elemen yang seluruh butirnya N/A TIDAK berhak. Capaiannya nol
 * karena tidak ada yang dinilai, bukan karena buruk — tetapi ia juga
 * bukan "sudah sempurna", dan menawarkan peluang perbaikan atas sesuatu
 * yang tidak berlaku bagi perusahaan itu hanya membuang waktu
 * pembacanya.
 */
final class SmkpPeluang
{
    /**
     * Seluruh butir yang berhak memperoleh OFI pada sebuah audit.
     *
     * @return list<array{kode:string,lingkup:string,nama:string,elemen:string,
     *                    elemen_nama:string,sub:?string,capaian:float,maks:int}>
     */
    public static function berhak(SmkpAudit $audit): array
    {
        $hasil = (array) ($audit->hasil ?? []);
        $out   = [];

        foreach (Smkp::elemen() as $e) {
            foreach ($e['sub'] as $s) {
                $rekap = Smkp::rekapSub($s, $hasil);

                /* Seluruhnya N/A: tidak ada yang dinilai, jadi tidak ada
                   yang sempurna. Diperiksa lebih dahulu supaya capaian
                   nol yang berasal dari pembagi kosong tidak sempat
                   dibandingkan dengan ambang. */
                if ($rekap['berlaku'] > 0 && self::penuh($rekap['capaian'])) {
                    $out[] = [
                        'kode'        => $s['kode'],
                        'lingkup'     => 'sub',
                        'nama'        => $s['nama'],
                        'elemen'      => $e['kode'],
                        'elemen_nama' => $e['nama'],
                        'sub'         => null,
                        'capaian'     => round($rekap['capaian'] * 100, 1),
                        'maks'        => $rekap['maks'],
                    ];
                }

                /* Rincian hanya disebut bila sub-elemennya memang
                   berincian. Sub-elemen tanpa rincian dinilai langsung,
                   dan butirnya adalah sub-elemen itu sendiri —
                   menyebutnya dua kali membuat lembar OFI memuat baris
                   kembar dengan kode yang sama persis. */
                if (empty($s['subsub'])) continue;

                foreach (Smkp::butirSub($s) as $b) {
                    $v    = Smkp::nilaiButir($hasil, $b['kode']);
                    $maks = (int) ($b['maks'] ?? 0);

                    if (!is_numeric($v) || $maks <= 0) continue;

                    $capaian = (float) $v / $maks;
                    if (!self::penuh($capaian)) continue;

                    $out[] = [
                        'kode'        => $b['kode'],
                        'lingkup'     => 'subsub',
                        'nama'        => $b['nama'] ?? '',
                        'elemen'      => $e['kode'],
                        'elemen_nama' => $e['nama'],
                        'sub'         => $s['kode'].' '.$s['nama'],
                        'capaian'     => round($capaian * 100, 1),
                        'maks'        => $maks,
                    ];
                }
            }
        }

        return $out;
    }

    /** @return array<string,array> kode → baris berhak */
    public static function perKode(SmkpAudit $audit): array
    {
        return array_column(self::berhak($audit), null, 'kode');
    }

    /**
     * Butir ini berhak memperoleh OFI pada audit ini?
     *
     * Dipakai penjagaan sisi server sebelum menyimpan. Jawaban `false`
     * untuk kode yang tidak dikenal sama sekali, jadi pemanggilnya tidak
     * perlu memeriksa dua hal berbeda.
     */
    public static function bolehkan(SmkpAudit $audit, string $kode): bool
    {
        return isset(self::perKode($audit)[$kode]);
    }

    /**
     * Capaian dianggap penuh?
     *
     * Dibandingkan dengan toleransi, bukan dengan `=== 1.0`. Capaian
     * dihitung sebagai pembagian pecahan; sub-elemen berbutir banyak
     * yang seluruhnya bernilai maksimum dapat menghasilkan
     * 0.9999999999999999 pada aritmetika pecahan biner — dan
     * perbandingan persis akan menolaknya tanpa satu pun tanda mengapa.
     */
    private static function penuh(float $capaian): bool
    {
        return $capaian >= 1.0 - 1e-9;
    }
}
