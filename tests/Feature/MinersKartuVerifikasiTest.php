<?php

namespace Tests\Feature;

use App\Models\Miners\{Permit, Simper};
use App\Support\Miners\Kartu\{DataKartu, RincianVerifikasi, SkemaCetak, WarnaAkses};
use App\Support\Miners\Verifikasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\SiapanMiners;
use Tests\TestCase;

/**
 * Kartu CR80 dan verifikasi publik mengikuti Safe Track (tests/simper-kartu.js,
 * tests/verifikasi-kartu.js).
 */
class MinersKartuVerifikasiTest extends TestCase
{
    use RefreshDatabase;
    use SiapanMiners;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkan();
        Storage::fake('public');
    }

    private function permitBerfoto(): Permit
    {
        $p = $this->permitTerbit();
        $p->forceFill(['foto' => 'miners/permit/foto/a.jpg'])->save();

        return $p;
    }

    #[Test]
    public function data_kartu_memuat_unit_warna_akses_ktt_dan_qr(): void
    {
        $p = $this->permitBerfoto();
        $s = $this->simperTerbit($p);

        Storage::disk('local')->put('miners/ttd/ktt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $this->iup->forceFill(['ktt' => 'Ir. Kepala Uji', 'ttd_ktt' => 'miners/ttd/ktt.png', 'channel_darurat' => 'Radio CH 7'])->save();

        $k = DataKartu::dariPermit($p->fresh());

        $this->assertTrue($k['punya_simper']);
        $this->assertSame('merah', $k['warna_akses']);
        $this->assertSame('DT Volvo 40T', $k['unit'][0]['nama']);
        $this->assertSame('F', $k['unit'][0]['kelas']);
        $this->assertCount(DataKartu::BARIS_UNIT, $k['unit']);
        $this->assertSame('IR. KEPALA UJI', $k['nama_ktt']);
        $this->assertStringStartsWith('data:image/png;base64,', $k['ttd_ktt']);
        $this->assertSame(route('verifikasi.kartu', Verifikasi::kode('MP', $p->id)), $k['verifikasi']);
        $this->assertMatchesRegularExpression('#^\d{4}/TBU/\d{2}/\d{4}$#', $k['no_register']);

        /* Warna yang dinyatakan pada permit menang; tanpa SIMPER = putih. */
        $p->forceFill(['kode_warna' => 'biru'])->save();
        $this->assertSame('biru', DataKartu::dariPermit($p->fresh())['warna_akses']);
        $this->assertSame('putih', WarnaAkses::untukKartu([], false));
        $this->assertSame('hijau', WarnaAkses::golongkan('Light Vehicle Hilux'));
    }

    #[Test]
    public function cetak_kartu_daftar_lembar_dan_tata_cetak(): void
    {
        $a = $this->permitBerfoto();
        $b = $this->permitTerbit();   // tanpa foto

        $this->actingAs($this->admin)->get('/miners/daftar/cetak-kartu')->assertOk()
            ->assertInertia(fn (AssertableInertia $h) => $h->component('Miners/CetakKartu')->has('baris', 2));

        $this->get("/miners/kartu/{$a->id}")->assertOk()->assertHeader('Cache-Control')
            ->assertInertia(fn (AssertableInertia $h) => $h->component('Print/KartuCetak')->where('k.id', $a->id));

        $this->get('/miners/kartu/lembar?'.http_build_query(['id' => [$a->id, $b->id], 'skema' => 'bolak-balik']))->assertOk()
            ->assertInertia(fn (AssertableInertia $h) => $h->component('Print/KartuLembar')
                ->has('kartu', 1)->has('dilewati', 1)->where('skema.skema', 'bolak-balik')->where('petak.per_lembar', 8));

        $this->post('/miners/kartu/tata-cetak', ['nama' => 'Mitra', 'skema' => 'depan', 'kertas' => 'a4', 'orientasi' => 'portrait',
            'sela' => 5, 'sela_lipat' => 4, 'margin' => 10, 'garis_potong' => 0])->assertSessionHasNoErrors();
        $this->assertSame('depan', SkemaCetak::tersimpan($this->admin)['skema']);

        /* Pengaju biasa hanya mencetak kartunya sendiri dan tidak mengubah tata cetak. */
        $lain = \App\Models\User::factory()->create(['company_id' => $this->mitra->id]);
        $this->actingAs($lain)->get("/miners/kartu/{$a->id}")->assertNotFound();
        $this->actingAs($this->pengaju)->post('/miners/kartu/tata-cetak', ['skema' => 'lipat', 'kertas' => 'a4', 'orientasi' => 'portrait',
            'sela' => 5, 'sela_lipat' => 4, 'margin' => 10, 'garis_potong' => 1])->assertForbidden();
    }

    #[Test]
    public function petak_a4_mendatar_lipat_sesuai_safe_track(): void
    {
        $p = SkemaCetak::petak(SkemaCetak::rapikan([]));

        $this->assertSame(2, $p['kolom']);   // (297-20+8)/(112+8)
        $this->assertSame(2, $p['baris']);   // (210-20+8)/(86+8)
    }

    #[Test]
    public function verifikasi_publik_tanpa_login_dengan_data_tersamar(): void
    {
        $p = $this->permitBerfoto();
        $s = $this->simperTerbit($p);
        $s->forceFill(['no_simpol' => '1234-5678-9999'])->save();

        $this->get('/v/'.Verifikasi::kode('MP', $p->id))->assertOk()
            ->assertInertia(fn (AssertableInertia $h) => $h->component('Verifikasi/Kartu')->where('sah', true)
                ->where('r.keadaan', 'berlaku')
                ->where('r.simper.sim', '••••••••••9999')
                ->where('r.simper.unit.0.kelas', 'F')
                ->missing('r.telepon'));

        $this->get('/v/'.Verifikasi::kode('SP', $s->id))->assertOk()->assertInertia(fn ($h) => $h->where('r.jenis', 'SIMPER'));

        /* Tanda tangan dipalsu → 404; kartu nonaktif → keadaan nonaktif. */
        $this->get('/v/MP'.$p->id.'.0000000000')->assertNotFound();
        $s->update(['aktif' => false]);
        $p->update(['aktif' => false]);
        $this->assertSame('nonaktif', RincianVerifikasi::dariKode(Verifikasi::kode('MP', $p->id))['keadaan']);
    }

    #[Test]
    public function identitas_perusahaan_mengunggah_logo_grup_dan_ttd_tertutup(): void
    {
        $this->actingAs($this->admin)->post("/miners/master/perusahaan/{$this->iup->id}", [
            '_method' => 'put', 'ktt' => 'Ir. Uji',
            'logo_grup' => UploadedFile::fake()->image('logo.png'), 'ttd_ktt' => UploadedFile::fake()->image('ttd.png'),
        ])->assertSessionHasNoErrors();

        $c = $this->iup->fresh();
        Storage::disk('public')->assertExists($c->logo_grup);
        Storage::disk('local')->assertExists($c->ttd_ktt);
        Storage::disk('public')->assertMissing($c->ttd_ktt);
    }
}
