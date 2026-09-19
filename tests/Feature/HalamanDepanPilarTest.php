<?php

namespace Tests\Feature;

use App\Support\{Media, Pillars};
use Tests\TestCase;

/**
 * Halaman depan: galeri video dan bagian delapan aspek.
 *
 * Keduanya pernah rusak dengan cara yang sama — tanpa galat, tanpa uji
 * yang gagal, hanya halaman yang bekerja persis seperti yang ditulis
 * dan menceritakan hal yang keliru kepada calon pembeli.
 */
class HalamanDepanPilarTest extends TestCase
{
    private function landing(): string
    {
        return file_get_contents(resource_path('js/Pages/Landing.vue'));
    }

    /* ═══════════ galeri video ═══════════ */

    /**
     * Tiap kartu memutar videonya SENDIRI.
     *
     * Semula tombol pada keenam kartu memanggil `videoTerbuka = true`,
     * dan modalnya selalu memutar `hero.video` dengan judul yang dipaku
     * "Operasional Tambang". Keenam videonya ada dan berbeda; tidak satu
     * pun pernah terpakai.
     *
     * Yang membacanya tidak melihat galat. Ia melihat enam janji berbeda
     * yang semuanya membuka rekaman yang sama, menyimpulkan videonya
     * memang cuma satu, lalu berhenti menekan tombolnya.
     */
    public function test_tiap_kartu_galeri_memutar_videonya_sendiri(): void
    {
        $isi = $this->landing();

        $this->assertStringContainsString("putar(g.judul, g.videoUrl", $isi,
            'Tombol galeri tidak meneruskan video kartunya sendiri.');

        $this->assertStringNotContainsString('videoTerbuka', $isi,
            'Penanda lama videoTerbuka masih ada — modalnya kembali memutar satu video '
            .'untuk semua kartu.');
    }

    /**
     * Pemutarnya tidak boleh kembali dipaku ke video hero.
     *
     * Dicari pada blok pemutarnya saja: `hero.video` memang sah dipakai
     * pada latar belakang kepala halaman, dan melarangnya di seluruh
     * berkas akan menjatuhkan uji ini atas pemakaian yang benar.
     */
    public function test_pemutar_tidak_dipaku_ke_video_hero(): void
    {
        $isi = $this->landing();
        $awal = strpos($isi, 'jual-pemutar');

        $this->assertNotFalse($awal, 'Blok pemutar video tidak ditemukan.');

        $blok = substr($isi, $awal);

        $this->assertStringNotContainsString('hero.video', $blok,
            'Pemutar video kembali memakai hero.video, bukan video kartu yang ditekan.');

        $this->assertStringContainsString('videoAktif.src', $blok);
    }

    /** Videonya memang ada — enam berkas berbeda, bukan satu. */
    public function test_galeri_membawa_video_yang_berbeda(): void
    {
        $video = collect(Media::galeri())->pluck('video')->filter()->values();

        $this->assertGreaterThan(1, $video->count(),
            'Galeri hanya punya satu video; tidak ada yang perlu dibedakan.');

        $this->assertSame($video->count(), $video->unique()->count(),
            'Ada kartu galeri yang berbagi berkas video yang sama.');
    }

    /* ═══════════ kalimat tiap aspek ═══════════ */

    /**
     * `ket` menyebut pekerjaannya, bukan cita-citanya.
     *
     * Sempat berisi semboyan — "Zero compromise, zero tolerance", "Jaga
     * alam untuk masa depan", "Solusi andal & efisien" — kalimat yang
     * dapat dipasang pada perusahaan mana pun di industri mana pun tanpa
     * satu kata berubah, dan karena itu tidak memberi tahu pembacanya
     * apa pun.
     *
     * Aturannya dapat diperiksa: tiap kata penting pada `ket` harus
     * dapat ditemukan lagi pada `cakupan` aspek itu sendiri. Semboyan
     * tidak akan pernah lolos, sebab ia memang tidak menyebut satu pun
     * hal yang benar-benar dikerjakan.
     */
    public function test_ket_tiap_aspek_menyebut_cakupannya(): void
    {
        /* Kata tugas dibuang: ia muncul di mana-mana dan akan
           meloloskan semboyan hanya karena memakai kata "dan". */
        $tugas = ['dan', 'yang', 'per', 'di', 'ke', 'dari', 'untuk', 'pada',
                  'atau', 'satu', 'tiap', 'dengan', 'sebelum', 'jadi'];

        $akar = function (string $teks) use ($tugas): array {
            $kata = preg_split('/[^\p{L}]+/u', mb_strtolower($teks), -1, PREG_SPLIT_NO_EMPTY) ?: [];

            return array_values(array_filter($kata,
                fn ($k) => mb_strlen($k) > 3 && ! in_array($k, $tugas, true)));
        };

        foreach (Pillars::all() as $slug => $p) {
            $cakupan = mb_strtolower(collect($p['cakupan'])->flatten()->implode(' ').' '.$p['ringkas']);
            $penting = $akar($p['ket']);

            $this->assertNotEmpty($penting, "ket '{$slug}' tidak memuat satu kata penting pun.");

            $cocok = array_filter($penting, function ($k) use ($cakupan) {
                /* Dicocokkan pada AKARNYA: "pemeriksaan" pada ket dan
                   "diperiksa" pada cakupan adalah hal yang sama, dan uji
                   yang menuntut ejaan persis akan menolak kalimat yang
                   justru ditulis dengan baik. */
                return str_contains($cakupan, mb_substr($k, 0, 5));
            });

            $this->assertGreaterThanOrEqual(2, count($cocok),
                "ket aspek '{$slug}' — \"{$p['ket']}\" — nyaris tidak menyebut satu pun hal "
                .'yang ada pada cakupannya. Kalimat semacam itu terbaca sebagai semboyan: '
                .'ia dapat dipasang pada perusahaan mana pun tanpa satu kata berubah.');
        }
    }

    /**
     * Tidak ada semboyan berbahasa Inggris yang tersisa.
     *
     * Halaman ini seluruhnya berbahasa Indonesia. Satu baris Inggris di
     * tengahnya tidak terbaca sebagai gaya, melainkan sebagai kalimat
     * yang disalin dari tempat lain.
     */
    public function test_tidak_ada_semboyan_inggris_pada_ket(): void
    {
        $semboyan = ['zero compromise', 'zero tolerance', 'best practice',
                     'world class', 'excellence', 'beyond compliance'];

        foreach (Pillars::all() as $slug => $p) {
            foreach ($semboyan as $s) {
                $this->assertStringNotContainsString($s, mb_strtolower($p['ket']),
                    "ket aspek '{$slug}' memuat semboyan '{$s}'.");
            }
        }
    }
}
