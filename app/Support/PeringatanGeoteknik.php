<?php

namespace App\Support;

use App\Models\GeoBacaan;
use App\Models\GeoLereng;
use Illuminate\Support\Collection;

/**
 * Peringatan pemantauan kestabilan lereng.
 *
 * Sebentuk dengan modul lain, tetapi dengan satu perbedaan yang
 * disengaja. Di modul lain, peringatan disusun dari data yang sudah
 * disetujui. Di sini ada dua sumber:
 *
 *  - Gerakan dihitung dari bacaan yang sudah ditinjau. Laju yang
 *    diturunkan dari salah baca prisma akan mengosongkan pit tanpa
 *    sebab, dan peringatan palsu yang berulang mengajari orang untuk
 *    mengabaikan peringatan berikutnya.
 *
 *  - Gejala yang dilaporkan pengamat — retakan baru, gugur batu,
 *    rembesan — memicu peringatan sejak masih draf. Menahan tanda
 *    bahaya sampai ada yang sempat menyetujuinya adalah kekeliruan yang
 *    tidak dapat diperbaiki setelah lerengnya runtuh.
 *
 * Seluruh peringatan di sini menyarankan pemeriksaan oleh tenaga
 * kompeten, bukan tindakan teknis tertentu. Modul ini tidak berwenang
 * menyatakan sebuah lereng aman atau harus dikosongkan.
 */
final class PeringatanGeoteknik
{
    public const TINGGI = 'tinggi';
    public const SEDANG = 'sedang';

    /** Ambang perkiraan waktu runtuh yang dianggap mendesak, hari. */
    private const TTF_MENDESAK_HARI = 7.0;

    /** Faktor keamanan rancangan minimum yang lazim dipakai lereng keseluruhan. */
    private const FK_LAZIM = 1.3;

    /**
     * @param Collection<int,GeoLereng> $lerengs
     * @param Collection<int,GeoBacaan> $semuaBacaan seluruh bacaan pada periode, termasuk draf
     * @return list<array{kode:string,level:string,judul:string,ket:string,saran:string}>
     */
    public static function susun(Collection $lerengs, Collection $semuaBacaan, array $kelengkapan): array
    {
        if ($lerengs->isEmpty()) {
            return [[
                'kode'  => 'tanpa-lereng',
                'level' => self::TINGGI,
                'judul' => 'Belum ada lereng terdaftar',
                'ket'   => 'Tidak ada yang dipantau, sehingga tidak ada yang dapat diperingatkan.',
                'saran' => 'Daftarkan sektor lereng beserta geometri rancangan dan acuan kajian geotekniknya.',
            ]];
        }

        $p = [];

        foreach (self::gejalaDilaporkan($semuaBacaan) as $g) $p[] = $g;
        foreach ($lerengs as $l) foreach (self::perLereng($l, $semuaBacaan) as $q) $p[] = $q;
        foreach (self::mutuData($kelengkapan) as $m) $p[] = $m;

        return $p;
    }

    /**
     * Gejala yang dilihat langsung di lapangan.
     *
     * Sengaja dibaca dari seluruh bacaan, termasuk yang masih draf.
     * Inilah satu-satunya peringatan pada seluruh aplikasi yang tidak
     * menunggu persetujuan, dan alasannya ada di keterangan kelas ini.
     */
    private static function gejalaDilaporkan(Collection $semuaBacaan): array
    {
        $gejala = $semuaBacaan->filter(fn (GeoBacaan $b) => $b->ada_gejala);

        if ($gejala->isEmpty()) return [];

        $p = [];

        foreach ($gejala->groupBy('geo_lereng_id') as $bacaan) {
            $terbaru = $bacaan->sortByDesc(fn (GeoBacaan $b) => $b->tanggal?->toDateString())->first();
            $kode    = $terbaru->lereng?->kode ?? 'Lereng';
            $belum   = $bacaan->reject(fn (GeoBacaan $b) => $b->sudahDisetujui())->count();

            $p[] = [
                'kode'  => 'gejala-lapangan-'.$terbaru->geo_lereng_id,
                'level' => self::TINGGI,
                'judul' => "{$kode}: gejala terlihat di lapangan ({$bacaan->count()} laporan)",
                'ket'   => trim(($terbaru->gejala ?: 'Pengamat menandai adanya gejala.')
                           .($belum > 0 ? " — {$belum} di antaranya belum ditinjau, dan peringatan ini sengaja tidak menunggu." : '')),
                'saran' => 'Periksa langsung bersama tenaga kompeten geoteknik sebelum pekerjaan di bawah lereng dilanjutkan.',
            ];
        }

        return $p;
    }

    /** @param Collection<int,GeoBacaan> $semuaBacaan */
    private static function perLereng(GeoLereng $l, Collection $semuaBacaan): array
    {
        $p = [];
        $milik = $semuaBacaan->where('geo_lereng_id', $l->id);
        $gerak = $l->gerakan($milik);

        /* ---------- gerakan ---------- */

        if (in_array($gerak['tingkat'], ['siaga', 'awas'], true)) {
            $p[] = [
                'kode'  => 'laju-'.$gerak['tingkat'].'-'.$l->id,
                'level' => self::TINGGI,
                'judul' => "{$l->kode} bergerak ".number_format((float) $gerak['laju'], 1).' mm/hari',
                'ket'   => 'Melampaui ambang '.$gerak['tingkat'].' lereng ini'
                           .($gerak['tren']['arah'] === 'menderas' ? ', dan lajunya masih menderas.' : '.'),
                'saran' => 'Laporkan kepada Kepala Teknik Tambang dan tenaga kompeten geoteknik; tinjau ulang izin kerja di bawah lereng ini.',
            ];
        } elseif ($gerak['tingkat'] === 'waspada') {
            $p[] = [
                'kode'  => 'laju-waspada-'.$l->id,
                'level' => self::SEDANG,
                'judul' => "{$l->kode} bergerak ".number_format((float) $gerak['laju'], 1).' mm/hari',
                'ket'   => 'Masih di bawah ambang siaga, tetapi sudah di atas laju wajarnya.',
                'saran' => 'Rapatkan jadwal pembacaan pada lereng ini.',
            ];
        }

        // Gerakan yang menderas berarti sesuatu berubah, dan itu layak
        // dilihat bahkan ketika lajunya sendiri masih kecil.
        if ($gerak['tren']['arah'] === 'menderas' && $gerak['tingkat'] === 'normal') {
            $p[] = [
                'kode'  => 'tren-menderas-'.$l->id,
                'level' => self::SEDANG,
                'judul' => "{$l->kode}: laju gerakan mulai menderas",
                'ket'   => 'Naik dari '.number_format((float) $gerak['tren']['awal'], 2)
                           .' ke '.number_format((float) $gerak['tren']['akhir'], 2).' mm/hari, meski keduanya masih rendah.',
                'saran' => 'Amati beberapa pembacaan berikutnya; percepatan lebih berarti daripada besarnya angka.',
            ];
        }

        $ttf = $gerak['ttf'];
        if ($ttf['dapatDipakai'] && $ttf['hari'] !== null && $ttf['hari'] <= self::TTF_MENDESAK_HARI) {
            $p[] = [
                'kode'  => 'ttf-dekat-'.$l->id,
                'level' => self::TINGGI,
                'judul' => "{$l->kode}: kebalikan laju menunjuk ".number_format((float) $ttf['hari'], 1).' hari lagi',
                'ket'   => 'Perkiraan dari kecenderungan gerakan (R² '.number_format((float) $ttf['r2'], 2)
                           .'), bukan hasil kajian kestabilan. Angkanya indikatif.',
                'saran' => 'Bawa segera ke tenaga kompeten geoteknik untuk menetapkan tindakan; jangan jadikan angka ini satu-satunya dasar.',
            ];
        }

        /* ---------- geometri ---------- */

        foreach ($l->penyimpanganGeometri() as $s) {
            $p[] = [
                'kode'  => 'geometri-'.$l->id.'-'.str_replace(' ', '-', strtolower($s['hal'])),
                'level' => self::TINGGI,
                'judul' => "{$l->kode}: {$s['hal']} menyimpang dari rancangan",
                'ket'   => "Terbangun {$s['aktual']}{$s['satuan']} terhadap rancangan {$s['rencana']}{$s['satuan']}.",
                'saran' => 'Lereng yang keluar dari geometri rancangan sudah keluar dari dasar kajiannya; mintakan tinjauan ulang.',
            ];
        }

        /* ---------- acuan kajian ---------- */

        $sisa = $l->sisaHariKajian();
        if ($sisa !== null && $sisa < 0) {
            $p[] = [
                'kode'  => 'kajian-kedaluwarsa-'.$l->id,
                'level' => self::TINGGI,
                'judul' => "{$l->kode}: kajian geoteknik terlewat ".abs($sisa).' hari',
                'ket'   => 'Acuan faktor keamanan pada halaman ini berasal dari kajian yang sudah lewat masa tinjauannya.',
                'saran' => 'Jadwalkan tinjauan ulang kajian kestabilan lereng.',
            ];
        }

        if ($l->fk_rencana === null && $l->status === 'aktif') {
            $p[] = [
                'kode'  => 'tanpa-acuan-fk-'.$l->id,
                'level' => self::SEDANG,
                'judul' => "{$l->kode} belum punya acuan faktor keamanan",
                'ket'   => 'Tanpa angka rancangan, pembacaan pada lereng ini tidak dapat dibandingkan dengan apa pun.',
                'saran' => 'Isikan faktor keamanan dan penyusun kajiannya dari dokumen kajian geoteknik yang berlaku.',
            ];
        } elseif ($l->fk_rencana !== null && $l->fk_rencana < self::FK_LAZIM) {
            $p[] = [
                'kode'  => 'fk-rendah-'.$l->id,
                'level' => self::SEDANG,
                'judul' => "{$l->kode} dirancang pada faktor keamanan {$l->fk_rencana}",
                'ket'   => 'Di bawah '.self::FK_LAZIM.' yang lazim dipakai untuk lereng keseluruhan.',
                'saran' => 'Pastikan angka ini memang ditetapkan kajiannya, bukan salah isi; lereng ber-FK rendah menuntut pemantauan lebih rapat.',
            ];
        }

        /* ---------- instrumen ---------- */

        if ($l->instrumen->isEmpty() && $l->status === 'aktif') {
            $p[] = [
                'kode'  => 'tanpa-instrumen-'.$l->id,
                'level' => self::TINGGI,
                'judul' => "{$l->kode} tidak punya alat pantau",
                'ket'   => 'Gerakan pada lereng ini tidak akan pernah terbaca lebih awal.',
                'saran' => 'Pasang prisma atau alat pantau lain, atau arsipkan lereng ini bila memang tidak lagi aktif.',
            ];
        } elseif ($l->instrumenRusak() > 0) {
            $p[] = [
                'kode'  => 'instrumen-rusak-'.$l->id,
                'level' => self::TINGGI,
                'judul' => "{$l->instrumenRusak()} alat pantau {$l->kode} rusak",
                'ket'   => $l->instrumenSiap().' dari '.$l->instrumen->count().' alat masih membaca.',
                'saran' => 'Perbaiki atau ganti; lereng yang berhenti terpantau tampak tenang justru karena tidak ada yang membacanya.',
            ];
        }

        return $p;
    }

    private static function mutuData(array $kelengkapan): array
    {
        $p = [];

        if (($kelengkapan['belumDilaporkan'] ?? 0) > 0) {
            $n = $kelengkapan['belumDilaporkan'];
            $p[] = [
                'kode'  => 'pembacaan-bolong',
                'level' => $n > 3 ? self::TINGGI : self::SEDANG,
                'judul' => "{$n} pembacaan belum masuk",
                'ket'   => 'Laju dan kecenderungan dihitung dari deret waktu; lubang pada deretnya membuat percepatan terbaca lebih landai daripada sebenarnya.',
                'saran' => 'Tagih pembacaan kepada pengawas geoteknik.',
            ];
        }

        if (($kelengkapan['menungguTinjauan'] ?? 0) > 0) {
            $p[] = [
                'kode'  => 'pembacaan-menunggu-tinjauan',
                'level' => self::SEDANG,
                'judul' => "{$kelengkapan['menungguTinjauan']} pembacaan menunggu tinjauan",
                'ket'   => 'Belum ikut dalam hitungan laju. Gejala lapangan pada bacaan ini tetap diperingatkan tanpa menunggu.',
                'saran' => 'Minta Kepala Teknik Tambang meninjau pembacaan yang tertahan.',
            ];
        }

        return $p;
    }
}
