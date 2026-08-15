<?php

namespace App\Support;

use App\Models\LedakRencana;
use App\Models\LedakTitik;
use App\Models\LedakUkur;
use Illuminate\Support\Collection;

/**
 * Peringatan pengeboran dan peledakan.
 *
 * Waktunya berbeda dari seluruh modul lain, dan itu memecah peringatan
 * di sini menjadi dua jenis yang tidak boleh dicampur:
 *
 *  - SEBELUM peledakan: perkiraan getaran melampaui ambang, geometri
 *    menyimpang, radius pengamanan kurang. Semuanya masih dapat diubah,
 *    dan justru itulah gunanya — peringatan yang datang setelah
 *    tombolnya ditekan tidak lagi mencegah apa pun.
 *
 *  - SESUDAH peledakan: misfire, flyrock, getaran terukur melampaui
 *    ambang. Yang pertama menuntut tindakan dalam hitungan jam, sebab
 *    bahan peledak yang gagal meledak tertinggal di dalam tumpukan
 *    material dan alat gali berikutnya yang akan menemukannya.
 *
 * Misfire dan flyrock diperingatkan sejak masih draf, tanpa menunggu
 * tinjauan — sama seperti gejala lapangan pada modul kestabilan lereng.
 */
final class PeringatanPeledakan
{
    public const TINGGI = 'tinggi';
    public const SEDANG = 'sedang';

    /**
     * @param Collection<int,LedakRencana> $rencana
     * @param Collection<int,LedakTitik>   $titik
     * @param Collection<int,LedakUkur>    $ukur
     * @param array{k:?float,beta:?float,dapatDipakai:bool,n:int,alasan:string} $tetapan
     * @return list<array{kode:string,level:string,judul:string,ket:string,saran:string}>
     */
    public static function susun(
        Collection $rencana, Collection $titik, Collection $ukur, array $tetapan
    ): array {
        $p = [];

        foreach (self::sesudahLedak($rencana) as $q) $p[] = $q;
        foreach (self::sebelumLedak($rencana, $titik, $tetapan) as $q) $p[] = $q;
        foreach (self::getaranTerukur($ukur) as $q) $p[] = $q;
        foreach (self::mutuAcuan($titik, $tetapan) as $q) $p[] = $q;

        return $p;
    }

    /** @param Collection<int,LedakRencana> $rencana */
    private static function sesudahLedak(Collection $rencana): array
    {
        $p = [];

        foreach ($rencana as $r) {
            $h = $r->hasil;
            if (!$h) continue;

            // Sengaja tidak menunggu status disetujui.
            if ($h->ada_misfire) {
                $n = $h->misfire_lubang ?: 0;
                $p[] = [
                    'kode'  => 'misfire-'.$r->id,
                    'level' => self::TINGGI,
                    'judul' => "{$r->kode}: misfire".($n ? " pada {$n} lubang" : ''),
                    'ket'   => 'Bahan peledak yang gagal meledak tertinggal di dalam tumpukan material'
                               .($h->sudahDisetujui() ? '.' : ' — laporannya belum ditinjau, dan peringatan ini sengaja tidak menunggu.'),
                    'saran' => 'Amankan area, larang penggalian pada tumpukan itu, dan tangani sesuai prosedur penanganan misfire oleh juru ledak.',
                ];
            }

            if ($h->ada_flyrock) {
                $jarak = $h->flyrock_jarak_m;
                $p[] = [
                    'kode'  => 'flyrock-'.$r->id,
                    'level' => self::TINGGI,
                    'judul' => "{$r->kode}: batu terlempar"
                               .($jarak ? ' sejauh '.number_format($jarak, 0).' m' : ''),
                    'ket'   => 'Lemparan batu di luar rencana. Sebab tersering: stemming kurang atau burden terlalu tipis.',
                    'saran' => 'Tinjau ulang geometri dan pengisian sebelum peledakan berikutnya; periksa kembali radius pengamanan yang dipakai.',
                ];
            }
        }

        return $p;
    }

    /**
     * @param Collection<int,LedakRencana> $rencana
     * @param Collection<int,LedakTitik>   $titik
     */
    private static function sebelumLedak(Collection $rencana, Collection $titik, array $tetapan): array
    {
        $p = [];

        // Hanya yang belum diledakkan: peringatan pencegahan pada
        // peledakan yang sudah terjadi hanya menambah kebisingan.
        foreach ($rencana->filter(fn (LedakRencana $r) => $r->hasil === null) as $r) {
            foreach ($r->penyimpanganGeometri() as $s) {
                $p[] = [
                    'kode'  => 'geometri-'.$r->id.'-'.md5($s['hal']),
                    'level' => self::TINGGI,
                    'judul' => "{$r->kode}: {$s['hal']} = {$s['nilai']}",
                    'ket'   => $s['akibat'].' Anjuran: '.$s['anjuran'].'.',
                    'saran' => 'Perbaiki rancangan sebelum pengisian dimulai; sesudah lubang terisi, geometri tidak lagi dapat diubah.',
                ];
            }

            foreach ($r->perkiraanGetaran($titik, $tetapan) as $g) {
                if (!$g['lampaui']) continue;

                $p[] = [
                    'kode'  => 'getaran-perkiraan-'.$r->id.'-'.md5($g['titik']),
                    'level' => self::TINGGI,
                    'judul' => "{$r->kode}: perkiraan getaran di {$g['titik']} "
                               .number_format((float) $g['perkiraan'], 1).' mm/s',
                    'ket'   => 'Melampaui ambang '.number_format($g['ambang'], 1).' mm/s pada jarak '
                               .number_format($g['jarak_m'], 0).' m'
                               .($tetapan['dapatDipakai'] ? ' (tetapan situs).' : ' (tetapan umum, situs belum terkalibrasi).'),
                    'saran' => $g['isiMaks'] !== null
                        ? 'Turunkan isi per tundaan menjadi paling banyak '
                          .number_format((float) $g['isiMaks'], 1).' kg, atau tambah jumlah tundaan.'
                        : 'Turunkan isi per tundaan atau tambah jumlah tundaan.',
                ];
            }
        }

        return $p;
    }

    /** @param Collection<int,LedakUkur> $ukur */
    private static function getaranTerukur(Collection $ukur): array
    {
        $lewat = $ukur->filter(fn (LedakUkur $u) => $u->melampaui());
        if ($lewat->isEmpty()) return [];

        $p = [];
        foreach ($lewat->groupBy('ledak_titik_id') as $baris) {
            $satu = $baris->first();
            $tertinggi = $baris->max('ppv_mm_s');

            $p[] = [
                'kode'  => 'getaran-terukur-'.$satu->ledak_titik_id,
                'level' => self::TINGGI,
                'judul' => ($satu->titik?->nama ?? 'Titik').': getaran terukur melampaui ambang',
                'ket'   => $baris->count().' pengukuran melampaui; tertinggi '
                           .number_format((float) $tertinggi, 2).' mm/s terhadap ambang '
                           .number_format((float) ($satu->titik?->ambang() ?? 0), 1).' mm/s.',
                'saran' => 'Laporkan sesuai ketentuan, dan pakai pengukuran ini untuk mengalibrasi ulang tetapan situs sebelum peledakan berikutnya.',
            ];
        }

        return $p;
    }

    /** @param Collection<int,LedakTitik> $titik */
    private static function mutuAcuan(Collection $titik, array $tetapan): array
    {
        $p = [];

        if ($titik->isEmpty()) {
            $p[] = [
                'kode'  => 'tanpa-titik',
                'level' => self::TINGGI,
                'judul' => 'Belum ada titik terlindung terdaftar',
                'ket'   => 'Tanpa daftar bangunan beserta jaraknya, perkiraan getaran tidak dapat dihitung sama sekali.',
                'saran' => 'Daftarkan permukiman, bangunan peka, dan infrastruktur di sekitar area peledakan.',
            ];
        }

        $tanpaAmbang = $titik->filter(fn (LedakTitik $t) => !$t->ambangDitetapkan());
        if ($tanpaAmbang->isNotEmpty()) {
            $p[] = [
                'kode'  => 'ambang-bawaan',
                'level' => self::SEDANG,
                'judul' => $tanpaAmbang->count().' titik memakai ambang bawaan',
                'ket'   => 'Ambang bawaan menurut jenis bangunan bukan ambang yang mengikat; yang mengikat ditetapkan izin lingkungan.',
                'saran' => 'Isikan ambang dari dokumen izin beserta dasar hukumnya.',
            ];
        }

        if (!$tetapan['dapatDipakai']) {
            $p[] = [
                'kode'  => 'tetapan-belum-terkalibrasi',
                'level' => self::SEDANG,
                'judul' => 'Perkiraan getaran memakai tetapan umum',
                'ket'   => $tetapan['alasan'].' Tetapan umum meleset jauh antar jenis batuan, '
                           .'dan meleset ke arah yang tidak dapat ditebak.',
                'saran' => 'Pasang alat ukur getaran pada beberapa peledakan berikutnya; '
                           .Peledakan::MIN_TITIK_KALIBRASI.' pengukuran sudah cukup untuk mulai mengalibrasi.',
            ];
        }

        return $p;
    }
}
