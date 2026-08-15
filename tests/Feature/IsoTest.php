<?php

namespace Tests\Feature;

use App\Models\{Document, User};
use App\Support\{Iso, Pillars};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Pemenuhan klausul ISO oleh dokumen terkendali.
 *
 * Yang diuji terutama arah sebaliknya dari register: bukan "dokumen apa
 * yang kita punya", melainkan "klausul mana yang belum punya dokumen" —
 * sebab itulah yang ditanyakan auditor.
 */
class IsoTest extends TestCase
{
    use RefreshDatabase;

    private function masuk(): void
    {
        $this->actingAs(User::factory()->create());
    }

    private function dokumen(array $atribut = []): Document
    {
        return Document::create(array_merge([
            'kode'   => 'PR-'.fake()->unique()->numberBetween(100, 999),
            'judul'  => 'Prosedur Identifikasi Bahaya',
            'jenis'  => 'Prosedur',
            'status' => 'berlaku',
            'revisi' => 0,
        ], $atribut));
    }

    /* ---------- registry ---------- */

    public function test_empat_standar_terdaftar_dengan_klausulnya(): void
    {
        // Lewat kodeSah(), bukan array_keys(): kode ISO seluruhnya angka dan
        // PHP mengubah kunci array numerik menjadi integer.
        $this->assertSame(['9001','14001','45001','50001'], Iso::kodeSah());

        foreach (Iso::semua() as $kode => $s) {
            $this->assertNotEmpty(Iso::butir($kode), "Standar {$kode} tanpa butir klausul.");
            $this->assertNotEmpty($s['judul']);
        }
    }

    public function test_judul_bab_tidak_ikut_dihitung_sebagai_butir(): void
    {
        // "4" adalah judul bab, bukan persyaratan yang dapat dipenuhi sebuah
        // dokumen. Ikut menghitungnya membuat celah tampak lebih besar.
        $nomor = array_column(Iso::butir('45001'), 'no');

        $this->assertNotContains('4', $nomor);
        $this->assertContains('4.1', $nomor);
    }

    public function test_tiap_standar_terhubung_ke_aspek_yang_terdaftar(): void
    {
        foreach (Iso::semua() as $kode => $s) {
            $this->assertNotNull(Pillars::get($s['pilar']), "Standar {$kode} menunjuk aspek yang tidak ada.");
            $this->assertSame(Pillars::color($s['pilar']), Iso::warna($kode));
        }
    }

    public function test_bab_diambil_dari_nomor_klausul(): void
    {
        $this->assertSame('8', Iso::bab('8.1.2'));
        $this->assertSame('10', Iso::bab('10.2'));
    }

    /* ---------- cakupan ---------- */

    public function test_tanpa_dokumen_seluruh_klausul_terhitung_celah(): void
    {
        $c = Iso::cakupan('9001', []);

        $this->assertSame(0, $c['tercakup']);
        $this->assertSame(0.0, $c['rasio']);
        $this->assertCount($c['butir'], $c['celah']);
    }

    public function test_klausul_yang_punya_dokumen_keluar_dari_daftar_celah(): void
    {
        $c = Iso::cakupan('45001', ['6.1.2' => 2, '8.2' => 1]);

        $this->assertSame(2, $c['tercakup']);
        $this->assertNotContains('6.1.2', array_column($c['celah'], 'no'));
        $this->assertNotContains('8.2',   array_column($c['celah'], 'no'));
    }

    /* ---------- pemetaan dokumen ---------- */

    public function test_menyimpan_dokumen_ikut_menyimpan_klausulnya(): void
    {
        $this->masuk();

        $this->post(route('dokumen.store'), [
            'kode'   => 'PR-HSE-01',
            'judul'  => 'Prosedur Identifikasi Bahaya',
            'jenis'  => 'Prosedur',
            'status' => 'berlaku',
            'iso'    => ['45001' => ['6.1.2', '8.1.2']],
        ])->assertRedirect();

        $doc = Document::where('kode', 'PR-HSE-01')->firstOrFail();
        $this->assertSame(['45001' => ['6.1.2','8.1.2']], $doc->klausul());
    }

    public function test_klausul_asing_ditolak(): void
    {
        // Formulir dikirim ulang di luar antarmuka bisa membawa nomor yang
        // tidak ada pada standar; menyimpannya membuat cakupan berbohong.
        $this->masuk();
        $doc = $this->dokumen();

        $this->put(route('dokumen.update', $doc), [
            'kode'   => $doc->kode,
            'judul'  => $doc->judul,
            'jenis'  => $doc->jenis,
            'status' => $doc->status,
            'iso'    => ['45001' => ['6.1.2', '99.9'], '12345' => ['1.1']],
        ]);

        $this->assertSame(['45001' => ['6.1.2']], $doc->fresh()->klausul());
    }

    public function test_menyimpan_ulang_membuang_klausul_yang_dicabut(): void
    {
        $this->masuk();
        $doc = $this->dokumen();
        $doc->isoMap()->create(['standar' => '45001', 'klausul' => '8.2']);

        $this->put(route('dokumen.update', $doc), [
            'kode'   => $doc->kode,
            'judul'  => $doc->judul,
            'jenis'  => $doc->jenis,
            'status' => $doc->status,
            'iso'    => ['45001' => ['6.1.2']],
        ]);

        $this->assertSame(['45001' => ['6.1.2']], $doc->fresh()->klausul());
    }

    public function test_menghapus_dokumen_ikut_menghapus_pemetaannya(): void
    {
        $doc = $this->dokumen();
        $doc->isoMap()->create(['standar' => '45001', 'klausul' => '8.2']);

        $doc->delete();

        $this->assertDatabaseCount('document_iso', 0);
    }

    /* ---------- halaman ---------- */

    public function test_halaman_standar_menampilkan_celah_dan_dokumennya(): void
    {
        $this->masuk();
        $doc = $this->dokumen(['kode' => 'PR-K3-01', 'judul' => 'Prosedur HIRADC']);
        $doc->isoMap()->create(['standar' => '45001', 'klausul' => '6.1.2']);

        $p = $this->get(route('iso.show', '45001'))->assertOk()->viewData('page')['props'];

        // Klausul dibawa bersama dokumen yang memenuhinya, jadi yang
        // diperiksa di sini pasangannya — bukan sekadar keberadaan teks.
        $butir = collect($p['bab'])->flatMap(fn ($b) => $b['klausul']);

        $terisi = $butir->firstWhere('no', '6.1.2');
        $this->assertSame('PR-K3-01', $terisi['dokumen'][0]['kode']);
        $this->assertSame('Prosedur HIRADC', $terisi['dokumen'][0]['judul']);

        $this->assertTrue($butir->contains(fn ($k) => $k['dokumen'] === []),
            'Klausul tanpa dokumen harus tetap terbawa supaya celahnya terbaca.');
        $this->assertGreaterThan(0, $p['cakupan']['celah']);
    }

    public function test_seluruh_halaman_iso_terbuka(): void
    {
        $this->masuk();

        $this->get(route('iso.index'))->assertOk();
        foreach (Iso::kodeSah() as $kode) {
            $this->get(route('iso.show', $kode))->assertOk();
            $this->get(route('iso.cetak', $kode))->assertOk();
        }
    }

    public function test_standar_tak_dikenal_menghasilkan_404(): void
    {
        $this->masuk();

        $this->get(route('iso.show', '99999'))->assertNotFound();
        $this->get(route('iso.cetak', '99999'))->assertNotFound();
    }

    public function test_matriks_cetak_berkop_dokumen_terkendali(): void
    {
        $this->masuk();

        $this->get(route('iso.cetak', '9001'))->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->component('Print/Iso')
                ->where('kode', '9001')->where('dok.nomor', 'EQ-OHSE-II.012')->has('perBab')->has('cakupan')
        );
    }

    /* ---------- struktur dokumen ---------- */

    public function test_piramida_menampilkan_enam_tingkat_dan_yang_kosong(): void
    {
        $this->masuk();
        $this->dokumen(['jenis' => 'Prosedur']);

        $p = $this->get(route('dokumen.piramida'))->assertOk()->viewData('page')['props'];

        $this->assertSame(\App\Support\Dokumen::JENIS, array_column($p['tingkat'], 'jenis'));

        $prosedur = collect($p['tingkat'])->firstWhere('jenis', 'Prosedur');
        $this->assertSame(1, $prosedur['total']);

        $this->assertTrue(collect($p['tingkat'])->contains(fn ($t) => $t['total'] === 0),
            'Tingkat yang kosong justru yang paling berguna dilihat; ia harus tetap terbawa.');
    }

    public function test_daftar_induk_memuat_dokumen_dan_berkop(): void
    {
        $this->masuk();
        $this->dokumen(['kode' => 'MN-01', 'judul' => 'Manual Sistem Manajemen', 'jenis' => 'Manual']);

        $this->get(route('dokumen.daftar-induk'))
            ->assertOk()
            ->assertSee('MN-01')
            ->assertSee('Manual Sistem Manajemen')
            ->assertSee('OHSE-II.001');
    }

    public function test_daftar_induk_bertambah_lembar_mengikuti_jumlah_dokumen(): void
    {
        $this->masuk();

        $this->get(route('dokumen.daftar-induk'))->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->component('Print/Dokumen')->has('documents', 0)
        );

        for ($i = 0; $i < 20; $i++) $this->dokumen();

        $this->get(route('dokumen.daftar-induk'))->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->component('Print/Dokumen')->has('documents', 20)
        );
    }
}
