<?php

namespace Tests\Feature;

use App\Models\{Document, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Register dokumen & ISO (Inertia).
 *
 * Menjaga bentuk muatannya, bukan rupanya: sisi Vue menggambar penanda
 * masa tinjau dan centang klausul apa adanya, jadi kunci yang berganti
 * nama tidak menimbulkan galat — hanya penanda yang diam-diam hilang.
 */
class DokumenInertiaTest extends TestCase
{
    use RefreshDatabase;

    private function masuk(bool $admin = true): User
    {
        $u = User::factory()->create(['is_admin' => $admin]);
        $this->actingAs($u);

        return $u;
    }

    private function dokumen(array $x = []): Document
    {
        return Document::create(array_merge([
            'kode'   => 'PR-'.fake()->unique()->numberBetween(100, 999),
            'judul'  => 'Prosedur Identifikasi Bahaya',
            'jenis'  => 'Prosedur',
            'status' => 'berlaku',
            'revisi' => 0,
        ], $x));
    }

    private function props(string $rute, array $p = []): array
    {
        return $this->get(route($rute, $p))->assertOk()->viewData('page')['props'];
    }

    public function test_halaman_dokumen_dan_iso_dirender_inertia(): void
    {
        $this->masuk();
        $d = $this->dokumen();

        $peta = [
            route('dokumen.index')       => 'Dokumen/Index',
            route('dokumen.create')      => 'Dokumen/Form',
            route('dokumen.piramida')    => 'Dokumen/Piramida',
            route('dokumen.show', $d)    => 'Dokumen/Detail',
            route('dokumen.edit', $d)    => 'Dokumen/Form',
            route('iso.index')           => 'Iso/Index',
            route('iso.show', '45001')   => 'Iso/Detail',
        ];

        foreach ($peta as $url => $komponen) {
            $this->get($url)->assertOk()->assertInertia(
                fn (AssertableInertia $p) => $p->component($komponen)->has('judul')->has('subjudul'),
            );
        }
    }

    public function test_penanda_masa_tinjau_dihitung_di_server(): void
    {
        // Vue menggambar penandanya apa adanya. Menghitung "lewat tempo"
        // di peramban berarti zona waktu peramban ikut menentukan, dan
        // dokumen yang sama bisa terbaca berbeda oleh dua orang.
        $this->masuk();
        $this->dokumen(['kode' => 'PR-LEWAT', 'tanggal_tinjau' => now()->subDays(5)]);
        $this->dokumen(['kode' => 'PR-SEGERA', 'tanggal_tinjau' => now()->addDays(7)]);
        $this->dokumen(['kode' => 'PR-AMAN', 'tanggal_tinjau' => now()->addYear()]);

        $baris = collect($this->props('dokumen.index')['dokumen'])->keyBy('kode');

        $this->assertTrue($baris['PR-LEWAT']['perluTinjau']);
        $this->assertFalse($baris['PR-LEWAT']['segeraTinjau']);

        $this->assertTrue($baris['PR-SEGERA']['segeraTinjau']);
        $this->assertFalse($baris['PR-SEGERA']['perluTinjau']);

        $this->assertFalse($baris['PR-AMAN']['perluTinjau']);
        $this->assertFalse($baris['PR-AMAN']['segeraTinjau']);
    }

    public function test_formulir_membawa_klausul_yang_sudah_tercentang(): void
    {
        $this->masuk();
        $d = $this->dokumen();
        $d->isoMap()->create(['standar' => '45001', 'klausul' => '6.1.2']);

        $p = $this->get(route('dokumen.edit', $d))->assertOk()->viewData('page')['props'];

        $this->assertTrue($p['tersimpan']);
        $this->assertSame(['6.1.2'], ((array) $p['isoAwal'])['45001']);

        // Seluruh butir tiap standar ikut terbawa: memuatnya belakangan
        // lewat permintaan kedua berarti isian yang sudah diketik hilang
        // saat pengguna menunggu.
        $kode = array_column($p['standar'], 'kode');
        $this->assertSame(['9001', '14001', '45001', '50001'], $kode);
        $this->assertNotEmpty($p['standar'][0]['butir']);
    }

    public function test_formulir_baru_tidak_membawa_klausul_apa_pun(): void
    {
        $this->masuk();

        $p = $this->props('dokumen.create');

        $this->assertFalse($p['tersimpan']);
        $this->assertSame([], (array) $p['isoAwal']);
        $this->assertSame('draft', $p['awal']['status']);
    }

    public function test_riwayat_revisi_terbawa_ke_halaman_rinci(): void
    {
        $this->masuk();
        $d = $this->dokumen();
        $d->revisions()->create([
            'revisi' => 0, 'ringkasan_perubahan' => 'Penerbitan awal.',
            'tanggal' => now(), 'oleh' => 'Pengendali Dokumen',
        ]);

        $p = $this->get(route('dokumen.show', $d))->assertOk()->viewData('page')['props'];

        $this->assertCount(1, $p['riwayat']);
        $this->assertSame('Penerbitan awal.', $p['riwayat'][0]['ringkasan']);
        $this->assertSame('Rev. 01', $p['d']['revisiBerikut']);
    }

    public function test_bukan_admin_tidak_diberi_tombol_hapus(): void
    {
        $this->masuk(admin: false);
        $d = $this->dokumen();

        $p = $this->get(route('dokumen.show', $d))->assertOk()->viewData('page')['props'];

        $this->assertFalse($p['bolehHapus']);
    }

    public function test_tautan_unduh_hanya_ada_bila_berkasnya_ada(): void
    {
        $this->masuk();

        $tanpa = $this->dokumen();
        $this->assertNull(
            $this->get(route('dokumen.show', $tanpa))->viewData('page')['props']['tautan']['unduh'],
        );

        $dengan = $this->dokumen(['berkas' => 'dokumen/contoh.pdf']);
        $this->assertNotNull(
            $this->get(route('dokumen.show', $dengan))->viewData('page')['props']['tautan']['unduh'],
        );
    }
}
