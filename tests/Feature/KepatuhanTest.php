<?php

namespace Tests\Feature;

use App\Models\{CompliancePoint, ComplianceRecap, ComplianceSubject, Company, User};
use App\Support\{Kepatuhan, PemecahPeraturan};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Identifikasi dan evaluasi pemenuhan.
 *
 * Yang dijaga di sini terutama satu hal: ARTI angkanya. Persentase
 * pemenuhan dibawa ke rapat, dikirim ke pemegang IUP, dan ditempel di
 * laporan audit — dan angka yang menghitung "belum dinilai" sebagai
 * "tidak berlaku" memulangkan register setengah jadi sebagai register
 * yang selesai.
 */
class KepatuhanTest extends TestCase
{
    use RefreshDatabase;

    private static int $n = 0;

    private function perusahaan(): Company
    {
        $i = ++self::$n;

        return Company::create(['name' => "PT Uji Patuh {$i}", 'code' => "PP{$i}"]);
    }

    private function masuk(?Company $c = null): User
    {
        $u = User::factory()->create(['is_admin' => true, 'company_id' => $c?->id]);
        $this->actingAs($u);

        return $u;
    }

    private function subjek(Company $c, array $x = []): ComplianceSubject
    {
        return ComplianceSubject::create(array_merge([
            'company_id' => $c->id,
            'sumber'     => 'Peraturan',
            'kode'       => 'S'.(++self::$n),
            'jenis'      => 'Undang-Undang',
            'nomor'      => 'Undang-Undang Nomor '.self::$n.' Tahun 1970',
            'judul'      => 'Keselamatan Kerja',
            'aspek'      => 'safety',
            'tahun'      => 2026,
            'status'     => 'Tetap',
        ], $x));
    }

    /** @param array<int,string|null> $status */
    private function butir(ComplianceSubject $s, array $status): void
    {
        foreach ($status as $i => $st) {
            $s->points()->create([
                'penunjuk'    => 'Pasal '.($i + 1),
                'rangkuman'   => 'Kewajiban ke-'.($i + 1),
                'status'      => $st,
                'order_index' => $i + 1,
            ]);
        }
    }

    /* ══════════════ arti angkanya ══════════════ */

    /**
     * N/A bukan pembagi; belum dinilai pun bukan, tetapi keduanya
     * TIDAK disatukan.
     *
     * N/A adalah keputusan penilai bahwa butir itu tidak mengikat
     * kegiatan perusahaan. Belum dinilai adalah pekerjaan yang belum
     * dikerjakan. Menghitung N/A sebagai pembagi membuat perusahaan
     * yang separuh pasalnya memang tidak berlaku selamanya terbaca
     * lima puluh persen; menyatukan keduanya membuat register yang
     * belum disentuh terbaca sebagai register yang sudah selesai.
     */
    public function test_persentase_hanya_membagi_yang_benar_benar_dinilai(): void
    {
        $r = Kepatuhan::rekap(['Comply', 'Comply', 'Comply', 'Comply', 'Not Comply', 'N/A', null]);

        $this->assertSame(4, $r['comply']);
        $this->assertSame(1, $r['notComply']);
        $this->assertSame(1, $r['na']);
        $this->assertSame(1, $r['belum'], 'Butir tanpa status terhitung sebagai N/A.');
        $this->assertSame(5, $r['dinilai']);
        $this->assertSame(80.0, $r['persen'], 'N/A atau belum dinilai ikut jadi pembagi.');
    }

    /**
     * Belum ada yang dinilai memulangkan null, BUKAN nol.
     *
     * Nol berarti "seluruhnya tidak comply" — pernyataan yang jauh
     * lebih keras daripada "belum ada yang dinilai", dan register yang
     * baru dibuka tidak boleh tampil sebagai kegagalan total.
     */
    public function test_belum_ada_yang_dinilai_bukan_nol_persen(): void
    {
        $this->assertNull(Kepatuhan::rekap([null, null, 'N/A'])['persen']);
        $this->assertSame(0.0, Kepatuhan::rekap(['Not Comply', 'Not Comply'])['persen'],
            'Seluruhnya not comply seharusnya nol persen, bukan null.');
    }

    public function test_butir_baru_tersimpan_belum_dinilai_bukan_na(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $s = $this->subjek($c);

        $this->post(route('kepatuhan.butir.store', $s), ['penunjuk' => 'Pasal 3 Ayat (1)'])
             ->assertRedirect();

        $this->assertNull($s->points()->first()->status,
            'Butir baru lahir dengan status N/A — "tidak berlaku" dan "belum dinilai" tersatukan.');
    }

    /**
     * Draf hasil rangkuman TIDAK ikut dihitung.
     *
     * Rangkuman mesin salah dengan cara yang meyakinkan. Angka
     * pemenuhan yang separuhnya berasal dari pasal yang belum pernah
     * dibaca manusia lebih buruk daripada tidak ada angka, karena ia
     * tetap ditandatangani.
     */
    public function test_draf_tidak_ikut_menentukan_angka(): void
    {
        $c = $this->perusahaan();

        $tetap = $this->subjek($c);
        $this->butir($tetap, ['Comply', 'Comply']);

        $draf = $this->subjek($c, ['status' => 'Draf', 'dari_ai' => true]);
        $this->butir($draf, ['Not Comply', 'Not Comply', 'Not Comply']);

        $r = Kepatuhan::ringkas(ComplianceSubject::withoutGlobalScopes()->with('points')->get());

        $this->assertSame(2, $r['total'], 'Butir milik draf ikut terhitung.');
        $this->assertSame(100.0, $r['persen']);
        $this->assertSame(1, $r['draf']);
    }

    public function test_subjek_tanpa_butir_dilaporkan_terpisah(): void
    {
        $c = $this->perusahaan();

        $terisi = $this->subjek($c);
        $this->butir($terisi, ['Comply']);

        $kosong = $this->subjek($c, ['nomor' => 'Permen ESDM Nomor 26 Tahun 2018']);

        $r = Kepatuhan::ringkas(ComplianceSubject::withoutGlobalScopes()->with('points')->get());

        $this->assertCount(1, $r['kosong'],
            'Kewajiban tanpa butir tidak disebut, padahal ia tidak ikut menentukan persentase.');
        $this->assertSame($kosong->id, $r['kosong'][0]->id);
    }

    /* ══════════════ halaman ══════════════ */

    public function test_dasbor_menyajikan_ringkasan_dan_daftar_kerja(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $s = $this->subjek($c);
        $this->butir($s, ['Comply', 'Comply', 'Comply', 'Comply', 'Not Comply', 'N/A', null]);

        $s->points()->where('status', 'Not Comply')->update([
            'tindak_lanjut' => 'Pengadaan rambu', 'pic' => 'Dept. K3', 'target' => '2026-08-31',
        ]);

        $this->get(route('kepatuhan.dasbor', ['tahun' => 2026]))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Kepatuhan/Dasbor')
                /* 80, bukan 80.0: lewat JSON, float yang bulat
                   pulang sebagai integer. */
                ->where('ringkas.persen', 80)
                ->where('ringkas.belum', 1)
                ->where('ringkas.na', 1)
                ->has('menunggu', 1)
                ->where('menunggu.0.pic', 'Dept. K3')
                ->etc());
    }

    public function test_register_menyaring_menurut_aspek(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->subjek($c, ['aspek' => 'safety']);
        $this->subjek($c, ['aspek' => 'environment']);

        $this->get(route('kepatuhan.index', ['tahun' => 2026, 'aspek' => 'safety']))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->has('daftar', 1)->etc());
    }

    public function test_perusahaan_lain_tidak_dapat_membuka_penilaiannya(): void
    {
        $tetangga = $this->perusahaan();
        $s = $this->subjek($tetangga);

        $this->actingAs(User::factory()->create([
            'is_admin' => false, 'company_id' => $this->perusahaan()->id,
        ]));

        $this->get(route('kepatuhan.show', $s))->assertNotFound();
    }

    public function test_tamu_tidak_dapat_membuka_register(): void
    {
        $this->get(route('kepatuhan.index'))->assertRedirect(route('login'));
    }

    public function test_butir_perusahaan_lain_tidak_dapat_dinilai(): void
    {
        $tetangga = $this->perusahaan();
        $s = $this->subjek($tetangga);
        $this->butir($s, [null]);
        $b = $s->points()->first();

        $this->actingAs(User::factory()->create([
            'is_admin' => false, 'company_id' => $this->perusahaan()->id,
        ]));

        $this->put(route('kepatuhan.butir.update', $b), [
            'penunjuk' => 'Pasal 1', 'status' => 'Comply',
        ])->assertNotFound();

        $this->assertNull($b->fresh()->status);
    }

    /**
     * Salinan ke tahun lain membawa butirnya TANPA penilaiannya.
     *
     * Yang comply tahun lalu belum tentu comply tahun ini. Register
     * baru yang lahir sudah seratus persen comply adalah register yang
     * tidak akan pernah diperiksa ulang — dan itu persis kebalikan dari
     * gunanya evaluasi tahunan.
     */
    public function test_salinan_ke_tahun_lain_mengosongkan_penilaiannya(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $s = $this->subjek($c);
        $this->butir($s, ['Comply', 'Not Comply']);

        $s->points()->where('status', 'Not Comply')
          ->update(['tindak_lanjut' => 'Sudah dijadwalkan', 'pic' => 'Dept. K3']);

        $this->post(route('kepatuhan.salin', $s), ['tahun' => 2027])->assertRedirect();

        $baru = ComplianceSubject::withoutGlobalScopes()->where('tahun', 2027)->firstOrFail();

        $this->assertSame(2, $baru->points()->count(), 'Butirnya tidak ikut tersalin.');
        $this->assertSame(0, $baru->points()->whereNotNull('status')->count(),
            'Penilaian tahun lalu ikut tersalin ke tahun baru.');
        $this->assertSame(0, $baru->points()->whereNotNull('tindak_lanjut')->count());
        $this->assertSame('Tetap', $baru->status);
    }

    public function test_rekap_bulanan_tersimpan_sebagai_potret(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->post(route('kepatuhan.rekap.simpan'), [
            'bulan' => 3, 'tahun' => 2026, 'sumber' => 'Peraturan',
            'comply' => 10, 'not_comply' => 5, 'na' => 2, 'belum' => 1,
            'evaluasi' => 'Audit internal SMK3 selesai.', 'company_id' => $c->id,
        ])->assertRedirect();

        $r = ComplianceRecap::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(10, $r->comply);
        $this->assertSame(66.7, $r->persen(), 'Persentase rekap memakai rumus yang berbeda.');
    }

    /**
     * Halaman rekap terbuka meski belum ada satu bulan pun yang terisi.
     *
     * Itu keadaan yang dilihat orang PERTAMA yang membukanya, bukan
     * keadaan luar biasa — dan halaman yang gagal justru di situ adalah
     * halaman yang tidak pernah berhasil dipakai siapa pun.
     */
    public function test_rekap_terbuka_saat_belum_ada_satu_bulan_pun(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->get(route('kepatuhan.rekap', ['tahun' => 2026]))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('bulan', 12)
                ->where('bulan.0.terekap', false)
                ->where('bulan.0.persen', null)
                ->etc());
    }

    public function test_lembar_cetak_memuat_seluruh_butirnya(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $s = $this->subjek($c);
        $this->butir($s, ['Comply', 'Not Comply', 'N/A', null]);

        $this->get(route('kepatuhan.lembar', $s))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Print/KepatuhanLembar')
                ->has('dok')
                ->has('butir', 4)
                ->where('rekap.belum', 1)
                ->etc());
    }

    /* ══════════════ pemecah naskah ══════════════ */

    /**
     * Ayat dipecah TERPISAH dari pasalnya.
     *
     * Sebuah pasal kerap memuat satu ayat yang sudah dipenuhi dan satu
     * lagi yang belum. Penilaian tunggal atas keduanya tidak dapat
     * menyatakan mana yang mana, dan auditor yang meminta bukti ayat
     * (2) mendapat jawaban tentang ayat (1).
     */
    public function test_naskah_dipecah_per_pasal_dan_ayat(): void
    {
        $naskah = <<<'TXT'
        UNDANG-UNDANG NOMOR 1 TAHUN 1970

        Menimbang: bahwa setiap tenaga kerja berhak atas perlindungan.

        Pasal 3

        (1) Ditetapkan syarat-syarat keselamatan kerja untuk mencegah kecelakaan.
        (2) Syarat-syarat tersebut dapat diubah sesuai perkembangan teknologi.

        Pasal 9

        Pengurus wajib menunjukkan dan menjelaskan kondisi bahaya kepada tenaga kerja baru.
        TXT;

        $butir = PemecahPeraturan::pecah($naskah);

        $this->assertCount(3, $butir);
        $this->assertSame('Pasal 3 Ayat (1)', $butir[0]['penunjuk']);
        $this->assertSame('Pasal 3 Ayat (2)', $butir[1]['penunjuk']);
        $this->assertSame('Pasal 9', $butir[2]['penunjuk']);
        $this->assertStringContainsString('mencegah kecelakaan', $butir[0]['isi']);
    }

    /**
     * "Pasal" di tengah kalimat tidak memotong naskahnya.
     *
     * Rujukan silang — "sebagaimana dimaksud dalam Pasal 5" — muncul di
     * hampir setiap peraturan. Pemotongan yang tidak membedakannya dari
     * judul bagian memecah satu pasal menjadi selusin serpihan, dan
     * register yang dihasilkannya tidak dapat dibaca siapa pun.
     */
    public function test_rujukan_pasal_di_tengah_kalimat_tidak_memotong(): void
    {
        $naskah = "Pasal 7\n\nKetentuan sebagaimana dimaksud dalam Pasal 5 berlaku juga di sini.";

        $butir = PemecahPeraturan::pecah($naskah);

        $this->assertCount(1, $butir,
            'Rujukan "Pasal 5" di tengah kalimat ikut memotong naskahnya.');
        $this->assertSame('Pasal 7', $butir[0]['penunjuk']);
    }

    public function test_naskah_tanpa_penanda_pasal_tidak_memulangkan_butir_palsu(): void
    {
        $this->assertSame([], PemecahPeraturan::pecah('Sekadar catatan rapat tanpa pasal apa pun.'));
        $this->assertSame([], PemecahPeraturan::pecah('   '));
    }

    public function test_hasil_rangkuman_tersimpan_sebagai_draf_yang_belum_dinilai(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->post(route('kepatuhan.rangkum.simpan'), [
            'sumber' => 'Peraturan', 'nomor' => 'Permen ESDM Nomor 26 Tahun 2018',
            'judul'  => 'Pelaksanaan Kaidah Pertambangan yang Baik',
            'tahun'  => 2026, 'aspek' => 'safety', 'company_id' => $c->id,
            'dari_ai' => true,
            'butir' => [
                ['penunjuk' => 'Pasal 3 Ayat (1)', 'rangkuman' => 'Wajib kaidah teknik.'],
                ['penunjuk' => 'Pasal 13 Ayat (1)', 'rangkuman' => 'Wajib mengangkat KTT.'],
            ],
        ])->assertRedirect();

        $s = ComplianceSubject::withoutGlobalScopes()->where('nomor', 'like', 'Permen%')->firstOrFail();

        $this->assertSame('Draf', $s->status, 'Hasil rangkuman langsung berstatus Tetap.');
        $this->assertTrue($s->dari_ai);
        $this->assertSame(2, $s->points()->count());
        $this->assertSame(0, $s->points()->whereNotNull('status')->count(),
            'Butir hasil rangkuman sudah bernilai sebelum dibaca siapa pun.');
    }

    /* ══════════════ pustaka daftar periksa ══════════════ */

    public function test_pustaka_terbit_lengkap_dan_belum_dinilai(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->post(route('kepatuhan.pustaka.terbitkan'), [
            'kunci' => 'gap-45001-2027', 'tahun' => 2026, 'company_id' => $c->id,
        ])->assertRedirect();

        $s = ComplianceSubject::withoutGlobalScopes()
            ->where('nomor', 'like', 'ISO 45001:2027%')->firstOrFail();

        $harus = count(\App\Support\PustakaKepatuhan::satu('gap-45001-2027')['butir']);

        $this->assertSame($harus, $s->points()->count(),
            'Daftar periksa tidak tersalin seluruhnya.');
        $this->assertSame(0, $s->points()->whereNotNull('status')->count(),
            'Butir pustaka lahir sudah bernilai — daftar periksa yang tidak akan pernah dibaca.');
        $this->assertSame('Tetap', $s->status);
        $this->assertSame('45001', $s->iso_kode);
    }

    public function test_pustaka_yang_sudah_terbit_ditandai_agar_tidak_berganda(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->post(route('kepatuhan.pustaka.terbitkan'), [
            'kunci' => 'dokumen-wajib-smkp', 'tahun' => 2026, 'company_id' => $c->id,
        ])->assertRedirect();

        $this->get(route('kepatuhan.pustaka', ['tahun' => 2026, 'perusahaan' => $c->id]))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Kepatuhan/Pustaka')
                ->where('pustaka.1.sudah', fn ($v) => $v !== null)
                ->where('pustaka.0.sudah', null)
                ->etc());
    }

    public function test_pustaka_yang_tidak_dikenal_ditolak(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->post(route('kepatuhan.pustaka.terbitkan'), [
            'kunci' => 'entah-apa', 'tahun' => 2026,
        ])->assertSessionHasErrors('kunci');
    }

    /**
     * Tiap butir pustaka punya penunjuk dan uraian yang terisi.
     *
     * Butir berpenunjuk kosong tampil sebagai baris tanpa judul di
     * tengah daftar lima puluh baris, dan yang menilainya tidak dapat
     * mengetahui klausul mana yang sedang dinilainya.
     */
    public function test_tiap_butir_pustaka_terisi(): void
    {
        foreach (\App\Support\PustakaKepatuhan::semua() as $kunci => $p) {
            $this->assertNotEmpty($p['butir'], "Pustaka '{$kunci}' tidak punya satu butir pun.");

            foreach ($p['butir'] as $i => $b) {
                $this->assertNotSame('', trim($b[0]), "Pustaka '{$kunci}' butir ke-".($i + 1).' tanpa penunjuk.');
                $this->assertNotSame('', trim($b[1]), "Pustaka '{$kunci}' butir ke-".($i + 1).' tanpa uraian.');
            }

            $penunjuk = array_column($p['butir'], 0);

            $this->assertSame(count($penunjuk), count(array_unique($penunjuk)),
                "Pustaka '{$kunci}' punya penunjuk kembar — dua baris menunjuk butir yang sama.");
        }
    }

    /* ══════════════ aspek ══════════════ */

    /**
     * Aspek memakai kunci pilar, bukan taksonomi tersendiri.
     *
     * Daftar aspek sendiri berarti dua tempat yang harus disamakan
     * setiap kali warna pilarnya berubah — dan yang satu pasti
     * tertinggal, sehingga lencana aspek di register berwarna berbeda
     * dari lencana pilar yang sama di halaman lain.
     */
    public function test_aspek_memakai_kunci_pilar_yang_sudah_ada(): void
    {
        foreach (Kepatuhan::ASPEK as $a) {
            $this->assertNotNull(\App\Support\Pillars::get($a),
                "Aspek '{$a}' bukan kunci pilar yang dikenali.");
            $this->assertNotSame('#78716C', Kepatuhan::warnaAspek($a),
                "Aspek '{$a}' jatuh ke warna cadangan; warnanya tidak terbaca dari pilar.");
        }

        $this->assertSame('Umum', Kepatuhan::namaAspek(null));
    }
}
