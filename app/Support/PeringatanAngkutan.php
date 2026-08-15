<?php

namespace App\Support;

use App\Models\AngkutAlat;
use App\Models\AngkutMuatan;
use App\Models\AngkutRegu;
use Illuminate\Support\Collection;

/**
 * Peringatan pengangkutan dan pengaturan armada.
 *
 * Pembagiannya mengikuti pembagian yang sudah dipakai modul kestabilan
 * lereng dan peledakan, dan alasannya sama:
 *
 *  - Muatan berlebih adalah PEMBACAAN ALAT. Ia benar tanpa menunggu
 *    siapa pun meninjaunya, dan akibatnya jatuh pada rem truk yang
 *    sedang menuruni jalan angkut hari ini. Karena itu ia diperingatkan
 *    sejak penimbangannya masuk.
 *
 *  - Match factor, porsi antre, dan utilisasi adalah ANGKA TURUNAN dari
 *    waktu yang dilaporkan sendiri. Satu salah ketik — 0,8 menit alih-
 *    alih 8 menit — sudah cukup melahirkan peringatan yang tidak ada
 *    kejadiannya. Karena itu semuanya menunggu tinjauan.
 *
 * Kecepatan rata-rata sengaja ditempatkan di kelompok kedua meskipun
 * akibatnya soal keselamatan. Ia bukan pembacaan speedometer melainkan
 * hasil bagi jarak dengan menit yang diketik seseorang, dan satu digit
 * yang tertinggal menghasilkan angka yang menuduh pengemudi atas
 * kejadian yang tidak pernah ada.
 */
final class PeringatanAngkutan
{
    public const TINGGI = 'tinggi';
    public const SEDANG = 'sedang';

    /**
     * @param Collection<int,AngkutRegu>   $regu     seluruh regu pada rentang
     * @param Collection<int,AngkutMuatan> $muatan   penimbangan pada rentang
     * @param Collection<int,AngkutAlat>   $alat
     * @return list<array{kode:string,level:string,judul:string,ket:string,saran:string}>
     */
    public static function susun(Collection $regu, Collection $muatan, Collection $alat): array
    {
        $p = [];

        foreach (self::muatanBerlebih($muatan) as $q) $p[] = $q;

        $sah = $regu->whereIn('status', Alur::terhitung());

        foreach (self::keseimbangan($sah) as $q) $p[] = $q;
        foreach (self::antre($sah) as $q) $p[] = $q;
        foreach (self::kecepatan($sah) as $q) $p[] = $q;
        foreach (self::mutuAcuan($regu, $alat, $muatan) as $q) $p[] = $q;

        return $p;
    }

    /* ═══════════ pembacaan alat: tidak menunggu tinjauan ═══════════ */

    /** @param Collection<int,AngkutMuatan> $muatan */
    private static function muatanBerlebih(Collection $muatan): array
    {
        $p = [];

        $puncak = $muatan->filter(fn (AngkutMuatan $m) => $m->melampauiPuncak());

        foreach ($puncak->groupBy('angkut_alat_id') as $baris) {
            $satu = $baris->first();
            $tertinggi = $baris->map(fn (AngkutMuatan $m) => $m->persen())->max();

            $p[] = [
                'kode'  => 'muatan-puncak-'.$satu->angkut_alat_id,
                'level' => self::TINGGI,
                'judul' => ($satu->alat?->kode ?? 'Truk').': muatan melampaui 120% kapasitas nominal',
                'ket'   => $baris->count().' rit di atas batas mutlak; tertinggi '
                           .number_format((float) $tertinggi, 1).'% dari '
                           .number_format((float) ($satu->alat?->kapasitas_ton ?? 0), 1).' ton. '
                           .'Kelebihan muatan diserap retarder saat menuruni jalan angkut, '
                           .'dan tidak terasa oleh pengemudinya sampai retardernya tidak lagi cukup.',
                'saran' => 'Periksa pengisian di muka gali dan setelan payload meter; '
                           .'batasi unit ini pada rute datar sampai penimbangannya kembali normal.',
            ];
        }

        return $p;
    }

    /* ═══════════ angka turunan: menunggu tinjauan ═══════════ */

    /** @param Collection<int,AngkutRegu> $regu */
    private static function keseimbangan(Collection $regu): array
    {
        $p = [];

        foreach ($regu as $r) {
            $mf = $r->matchFactor();
            if ($mf === null || $mf <= 0) continue;

            $baca = Angkutan::bacaMatchFactor($mf);
            if ($baca['kelas'] === 'seimbang' || $baca['kelas'] === 'tak-diketahui') continue;

            // Berapa truk yang membuatnya seimbang. Angka ini yang
            // dipakai orang, bukan match factor-nya.
            $pas = (int) round($r->jumlah_truk / $mf);
            $selisih = $r->jumlah_truk - $pas;

            $p[] = [
                'kode'  => 'match-factor-'.$r->id,
                'level' => self::SEDANG,
                'judul' => "{$r->kode}: match factor ".number_format($mf, 2).' — '.strtolower($baca['label']),
                'ket'   => $baca['ket'],
                'saran' => $selisih > 0
                    ? "Kurangi {$selisih} truk dari regu ini, atau tambah satu alat muat; "
                      ."{$pas} truk sudah menghabiskan kapasitas gali yang ada."
                    : abs($selisih).' truk lagi masih terserap alat muat yang sama.',
            ];
        }

        return $p;
    }

    /** @param Collection<int,AngkutRegu> $regu */
    private static function antre(Collection $regu): array
    {
        $p = [];

        foreach ($regu as $r) {
            $porsi = $r->porsiAntre();
            if ($porsi === null || $porsi <= Angkutan::ANTRE_WAJAR_PERSEN) continue;

            $hilang = $r->tonaseHilangAntre();

            $p[] = [
                'kode'  => 'antre-'.$r->id,
                'level' => self::SEDANG,
                'judul' => "{$r->kode}: ".number_format($porsi, 1).'% waktu edar habis mengantre',
                'ket'   => 'Batas wajar '.number_format(Angkutan::ANTRE_WAJAR_PERSEN, 0).'%. '
                           .($hilang !== null
                               ? 'Setara '.number_format($hilang, 0).' ton yang tidak terangkut sepanjang shift.'
                               : 'Truk berdiri dengan mesin hidup tanpa menghasilkan tonase.'),
                'saran' => 'Periksa keseimbangan armada, kesiapan muka gali, dan pengaturan giliran masuk; '
                           .'antre yang panjang juga berarti kendaraan menumpuk di area alat gali bekerja.',
            ];
        }

        return $p;
    }

    /** @param Collection<int,AngkutRegu> $regu */
    private static function kecepatan(Collection $regu): array
    {
        $p = [];

        foreach ($regu->filter(fn (AngkutRegu $r) => $r->melampauiBatasKecepatan()) as $r) {
            $p[] = [
                'kode'  => 'kecepatan-'.$r->id,
                'level' => self::TINGGI,
                'judul' => "{$r->kode}: kecepatan rata-rata ".number_format((float) $r->kecepatanRata(), 1)
                           .' km/jam melampaui batas '.number_format((float) $r->batas_kecepatan_kmh, 0).' km/jam',
                'ket'   => 'Rata-rata yang sudah melewati batas berarti sebagian besar perjalanan '
                           .'memang di atas batas — angka ini tidak dapat dibuat setinggi itu oleh '
                           .'satu pelanggaran sesaat.',
                'saran' => 'Periksa waktu tempuh yang dilaporkan, lalu tinjau perilaku berkendara dan '
                           .'kelayakan rambu di rute ini bersama pengawas.',
            ];
        }

        return $p;
    }

    /* ═══════════ kelengkapan acuan ═══════════ */

    /**
     * @param Collection<int,AngkutRegu>   $regu
     * @param Collection<int,AngkutAlat>   $alat
     * @param Collection<int,AngkutMuatan> $muatan
     */
    private static function mutuAcuan(Collection $regu, Collection $alat, Collection $muatan): array
    {
        $p = [];

        $tanpaKapasitas = $alat->filter(
            fn (AngkutAlat $a) => $a->truk() && !$a->kapasitasDitetapkan()
        );

        if ($tanpaKapasitas->isNotEmpty()) {
            $p[] = [
                'kode'  => 'kapasitas-kosong',
                'level' => self::TINGGI,
                'judul' => $tanpaKapasitas->count().' truk belum punya kapasitas nominal',
                'ket'   => 'Tanpa kapasitas nominal, muatannya tetap tercatat tetapi tidak ada pembagi '
                           .'bagi kaidah 10/10/20 — muatan berlebih pada unit ini tidak akan pernah '
                           .'ketahuan, dan tidak ada galat apa pun yang menandainya.',
                'saran' => 'Isikan kapasitas nominal dari spesifikasi pabrikan: '
                           .$tanpaKapasitas->take(5)->pluck('kode')->implode(', ').'.',
            ];
        }

        $menunggu = $regu->filter(fn (AngkutRegu $r) => $r->menungguTinjauan());
        if ($menunggu->isNotEmpty()) {
            $p[] = [
                'kode'  => 'menunggu-tinjauan',
                'level' => self::SEDANG,
                'judul' => $menunggu->count().' regu menunggu tinjauan',
                'ket'   => 'Selama belum ditinjau, angkanya tidak masuk KPI dan tidak melahirkan '
                           .'peringatan keseimbangan armada.',
                'saran' => 'Tinjau catatan shift agar indikator armada mencerminkan seluruh regu yang bekerja.',
            ];
        }

        // Regu yang bekerja tanpa satu pun penimbangan. Bukan pelanggaran,
        // tetapi berarti kepatuhan muatannya tidak diketahui — dan tidak
        // diketahui berbeda dari patuh.
        $tanpaTimbang = $regu->whereIn('status', Alur::terhitung())
            ->filter(fn (AngkutRegu $r) => $r->ritase > 0
                && $muatan->where('angkut_regu_id', $r->id)->isEmpty());

        if ($tanpaTimbang->isNotEmpty()) {
            $p[] = [
                'kode'  => 'tanpa-timbang',
                'level' => self::SEDANG,
                'judul' => $tanpaTimbang->count().' regu tanpa satu pun penimbangan',
                'ket'   => 'Kepatuhan muatannya tidak diketahui, dan tidak diketahui bukan berarti patuh.',
                'saran' => 'Masukkan hasil jembatan timbang atau payload meter, sekurangnya sebagai contoh acak; '
                           .Angkutan::MIN_MUATAN_KEBIJAKAN.' penimbangan sudah cukup untuk menilai sebarannya.',
            ];
        }

        $tanpaBatas = $regu->filter(
            fn (AngkutRegu $r) => $r->jarak_km > 0
                && ($r->batas_kecepatan_kmh === null || $r->batas_kecepatan_kmh <= 0)
        );

        if ($tanpaBatas->isNotEmpty()) {
            $p[] = [
                'kode'  => 'batas-kecepatan-kosong',
                'level' => self::SEDANG,
                'judul' => $tanpaBatas->count().' rute belum punya batas kecepatan',
                'ket'   => 'Batas kecepatan jalan angkut adalah peraturan situs, bukan tetapan di dalam '
                           .'aplikasi — selama kosong, kecepatan rata-rata hanya ditampilkan dan tidak dinilai.',
                'saran' => 'Isikan batas dari peraturan lalu lintas tambang yang berlaku di rute tersebut.',
            ];
        }

        return $p;
    }
}
