<?php

namespace App\Support;

/**
 * Hitungan pendukung pemantauan kestabilan lereng.
 *
 * Modul ini adalah alat bantu keputusan, bukan pengganti penilaian
 * geoteknik oleh tenaga kompeten. Batas itu menentukan apa yang boleh
 * dihitung di sini dan apa yang tidak:
 *
 *  - Yang dihitung adalah hal yang terukur di lapangan dan punya cara
 *    baku: laju perpindahan, kecenderungannya, perkiraan waktu runtuh
 *    dari kebalikan laju, dan selisih geometri terbangun terhadap
 *    rancangan.
 *
 *  - Yang TIDAK dihitung adalah faktor keamanan lereng pit secara
 *    keseluruhan. Angka itu menuntut model bidang gelincir, parameter
 *    kuat geser dari uji laboratorium, dan muka air tanah yang
 *    terverifikasi; menghasilkannya dari beberapa kolom borang akan
 *    tampak berwibawa justru pada saat ia paling tidak layak dipercaya.
 *    Faktor keamanan rancangan diisikan sebagai data dari kajian yang
 *    sudah ada, bukan diturunkan ulang di sini.
 *
 * Faktor keamanan lereng tunggal pada timbunan memang dihitung, dengan
 * model lereng tak hingga yang dinyatakan terang-terangan sebagai
 * penyaring awal — lihat fkPenyaring().
 */
final class Kestabilan
{
    /**
     * Ambang laju perpindahan bawaan, milimeter per hari.
     *
     * Angka ini TARP bawaan, bukan ketentuan. Tiap lereng punya perilaku
     * sendiri: batuan kuat berkekar rapat bergerak beberapa milimeter
     * sebelum runtuh, sedangkan timbunan tanah dapat merayap puluhan
     * milimeter sehari selama berbulan-bulan tanpa gagal. Karena itu
     * tiap lereng boleh menetapkan ambangnya sendiri, dan angka ini
     * hanya dipakai ketika belum ditetapkan.
     */
    public const AMBANG_WASPADA = 2.0;
    public const AMBANG_SIAGA   = 10.0;
    public const AMBANG_AWAS    = 50.0;

    /** Titik minimum sebelum kecenderungan boleh disebut ada. */
    public const MIN_TITIK_TREN = 4;

    /**
     * Laju perpindahan antar pembacaan berurutan, mm/hari.
     *
     * Perpindahannya kumulatif, jadi lajunya adalah selisih dibagi
     * selang harinya — bukan nilai bacaan itu sendiri. Pembacaan pada
     * hari yang sama dilewati: pembaginya nol.
     *
     * @param  list<array{tanggal:string,perpindahan_mm:float}> $bacaan urut menaik menurut tanggal
     * @return list<array{tanggal:string,hari:float,laju:float}>
     */
    public static function laju(array $bacaan): array
    {
        $hasil = [];
        $awal = null;

        for ($i = 1; $i < count($bacaan); $i++) {
            $t0 = strtotime($bacaan[$i - 1]['tanggal']);
            $t1 = strtotime($bacaan[$i]['tanggal']);
            $awal ??= $t0;

            $selangHari = ($t1 - $t0) / 86400;
            if ($selangHari <= 0) continue;

            $hasil[] = [
                'tanggal' => $bacaan[$i]['tanggal'],
                'hari'    => round(($t1 - $awal) / 86400, 4),
                'laju'    => round(($bacaan[$i]['perpindahan_mm'] - $bacaan[$i - 1]['perpindahan_mm']) / $selangHari, 4),
            ];
        }

        return $hasil;
    }

    /**
     * Kecenderungan gerakan: melambat, tetap, atau menderas.
     *
     * Dibandingkan rata-rata laju pada paruh akhir terhadap paruh awal.
     * Gerakan yang melambat (regressive) umumnya menuju keseimbangan
     * baru; yang menderas (progressive) adalah yang menuju runtuh, dan
     * hanya pada keadaan itulah perkiraan waktu runtuh punya arti.
     *
     * @param  list<array{laju:float}> $laju
     * @return array{arah:string,awal:?float,akhir:?float}
     */
    public static function tren(array $laju): array
    {
        $n = count($laju);
        if ($n < self::MIN_TITIK_TREN) {
            return ['arah' => 'belum-cukup', 'awal' => null, 'akhir' => null];
        }

        $potong = intdiv($n, 2);
        $awal  = array_slice($laju, 0, $potong);
        $akhir = array_slice($laju, $potong);

        $rerata = fn (array $a) => count($a) ? array_sum(array_column($a, 'laju')) / count($a) : 0.0;

        $a = $rerata($awal);
        $b = $rerata($akhir);

        // Selisih di bawah sepersepuluh disebut tetap, bukan berubah:
        // pembacaan prisma membawa derau, dan menyebut setiap riak
        // sebagai perubahan arah membuat peringatannya berhenti dibaca.
        $ambangDerau = max(abs($a) * 0.1, 0.05);

        $arah = match (true) {
            $b - $a >  $ambangDerau => 'menderas',
            $a - $b >  $ambangDerau => 'melambat',
            default                 => 'tetap',
        };

        return ['arah' => $arah, 'awal' => round($a, 3), 'akhir' => round($b, 3)];
    }

    /**
     * Perkiraan waktu runtuh dengan metode kebalikan laju (Fukuzono).
     *
     * Menjelang runtuh, kebalikan laju (1/v) menurun mendekati lurus ke
     * arah nol; perpotongannya dengan sumbu waktu adalah perkiraan saat
     * runtuh. Metode ini baku pada pemantauan lereng tambang justru
     * karena tidak menuntut parameter kekuatan batuan sama sekali — ia
     * membaca perilaku lereng itu sendiri.
     *
     * Yang dikembalikan null berarti "tidak dapat diperkirakan", dan itu
     * hasil yang sah. Perkiraan hanya diberikan bila gerakannya memang
     * menderas dan titiknya cukup; memaksakan angka pada gerakan yang
     * melambat menghasilkan tanggal yang jauh di masa depan dan terbaca
     * sebagai jaminan aman — kebalikan dari yang dimaksud.
     *
     * @param  list<array{hari:float,laju:float}> $laju
     * @return array{hari:?float,r2:?float,dapatDipakai:bool,alasan:string}
     */
    public static function kebalikanLaju(array $laju): array
    {
        // Laju nol atau mundur tidak punya kebalikan yang berarti.
        $titik = [];
        foreach ($laju as $l) {
            if ($l['laju'] > 0) $titik[] = ['x' => $l['hari'], 'y' => 1 / $l['laju']];
        }

        if (count($titik) < self::MIN_TITIK_TREN) {
            return ['hari' => null, 'r2' => null, 'dapatDipakai' => false,
                    'alasan' => 'Titik pembacaan bergerak belum cukup untuk menarik garis.'];
        }

        ['m' => $m, 'b' => $b, 'r2' => $r2] = Regresi::lurus($titik);

        if ($m >= 0) {
            return ['hari' => null, 'r2' => round($r2, 3), 'dapatDipakai' => false,
                    'alasan' => 'Kebalikan laju tidak menurun — gerakannya tidak menuju runtuh.'];
        }

        $xNol   = -$b / $m;
        $xAkhir = end($titik)['x'];
        $sisa   = $xNol - $xAkhir;

        if ($sisa < 0) {
            return ['hari' => 0.0, 'r2' => round($r2, 3), 'dapatDipakai' => true,
                    'alasan' => 'Garisnya sudah melewati nol — perlakukan sebagai mendesak.'];
        }

        return [
            'hari' => round($sisa, 2),
            'r2'   => round($r2, 3),
            // R² rendah berarti titiknya berserak dan garisnya tidak
            // mewakili apa pun. Angkanya tetap ditampilkan, tetapi
            // ditandai tidak dapat dipakai sebagai dasar keputusan.
            'dapatDipakai' => $r2 >= 0.7,
            'alasan' => $r2 >= 0.7
                ? 'Sebaran titik cukup rapat pada garisnya.'
                : 'Sebaran titik terlalu berserak; perkiraan ini belum layak jadi dasar keputusan.',
        ];
    }

    /**
     * Tingkat kewaspadaan dari laju terakhir.
     *
     * Ambangnya boleh ditetapkan per lereng; yang di sini bawaan.
     */
    public static function tingkat(?float $laju, ?float $waspada = null, ?float $siaga = null, ?float $awas = null): string
    {
        if ($laju === null) return 'tanpa-data';

        $waspada ??= self::AMBANG_WASPADA;
        $siaga   ??= self::AMBANG_SIAGA;
        $awas    ??= self::AMBANG_AWAS;

        return match (true) {
            $laju >= $awas    => 'awas',
            $laju >= $siaga   => 'siaga',
            $laju >= $waspada => 'waspada',
            default           => 'normal',
        };
    }

    /**
     * Faktor keamanan penyaring untuk lereng tunggal, model lereng tak hingga.
     *
     * FK = [c' + (γ·z − γw·hw)·cos²β·tanφ'] / (γ·z·sinβ·cosβ)
     *
     * Berlaku ketika bidang gelincirnya sejajar muka lereng dan jauh
     * lebih panjang daripada dalamnya — timbunan dan lapukan seragam
     * mendekati keadaan itu. Untuk lereng keseluruhan dengan bidang
     * gelincir melingkar atau terkendali struktur, model ini TIDAK
     * berlaku, dan hasilnya di sana tidak berarti apa-apa.
     *
     * Karena itu namanya penyaring: gunanya menandai lereng yang perlu
     * dilihat lebih dulu oleh orang yang berwenang, bukan menyatakan
     * lereng itu aman.
     *
     * @param float $c     kohesi efektif, kPa
     * @param float $phi   sudut gesek dalam efektif, derajat
     * @param float $gamma berat isi tanah, kN/m³
     * @param float $z     dalam bidang gelincir, m
     * @param float $beta  sudut lereng, derajat
     * @param float $hw    tinggi muka air di atas bidang gelincir, m
     */
    public static function fkPenyaring(
        float $c, float $phi, float $gamma, float $z, float $beta, float $hw = 0.0
    ): ?float {
        if ($z <= 0 || $gamma <= 0 || $beta <= 0 || $beta >= 90) return null;

        $b   = deg2rad($beta);
        $ph  = deg2rad($phi);
        $gw  = 9.81;                       // berat isi air, kN/m³
        $hw  = max(0.0, min($hw, $z));     // air tidak melebihi dalam bidangnya

        $pendorong = $gamma * $z * sin($b) * cos($b);
        if ($pendorong <= 0) return null;

        // Tegangan normal efektif: beban total dikurangi tekanan air pori.
        $normalEfektif = ($gamma * $z - $gw * $hw) * cos($b) ** 2;
        $penahan = $c + max(0.0, $normalEfektif) * tan($ph);

        return round($penahan / $pendorong, 3);
    }
}
