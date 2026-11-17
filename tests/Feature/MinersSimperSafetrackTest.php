<?php

namespace Tests\Feature;

use App\Models\Miners\{JenisUnit, Kendaraan, Simper, SimperUnit};
use App\Support\Miners\{DeteksiMasaSim, Keadaan};
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\SiapanMiners;
use Tests\TestCase;

/**
 * SIMPER mengikuti Safe Track (tests/simper-kartu.js): dari Mine Permit
 * terbit, satu per orang, berkas wajib, unit bertipe banyak + authority,
 * pengaju → HSE → KTT, masa berlaku = tanggal SIM, nonaktif beralasan.
 */
class MinersSimperSafetrackTest extends TestCase
{
    use RefreshDatabase;
    use SiapanMiners;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkan();
    }

    #[Test]
    public function simper_dibuat_dari_permit_terbit_dan_hanya_satu_per_orang(): void
    {
        $p = $this->permitTerbit();

        $this->actingAs($this->pengaju)->post('/miners/simper', $this->isianSimper($p))->assertSessionHasNoErrors();

        $s = Simper::withoutGlobalScopes()->sole();
        $this->assertSame('draf', $s->status);
        $this->assertSame('SIMPER-00'.$s->id, $s->no_simper);
        $this->assertSame('manual', $s->simpol_sumber);
        $this->assertNotNull($s->berkas_ddt);

        $this->post('/miners/simper', $this->isianSimper($p))->assertSessionHasErrors('permit_id');
        $this->assertSame(1, Simper::withoutGlobalScopes()->count());
    }

    #[Test]
    public function permit_belum_terbit_dan_berkas_wajib_ditolak(): void
    {
        $p = $this->permitTerbit();
        $p->forceFill(['status' => 'ohse'])->save();

        $this->actingAs($this->pengaju)->post('/miners/simper', $this->isianSimper($p))->assertSessionHasErrors('permit_id');

        $p->forceFill(['status' => 'terbit'])->save();
        $this->post('/miners/simper', $this->isianSimper($p, ['berkas_ddt' => null, 'berkas_pengalaman' => null]))
            ->assertSessionHasErrors(['berkas_ddt', 'berkas_pengalaman']);
    }

    #[Test]
    public function tanggal_sim_terbaca_dari_berkas_bila_kosong_dan_ketikan_menang(): void
    {
        $tahun = Waktu::kini()->addYears(3)->year;
        $p = $this->permitTerbit();

        $this->actingAs($this->pengaju)->post('/miners/simper', $this->isianSimper($p, [
            'simpol_berlaku_sampai' => '',
            'berkas_simpol'         => $this->pdfTeks("Berlaku s/d 12-03-{$tahun}"),
        ]))->assertSessionHasNoErrors();

        $s = Simper::withoutGlobalScopes()->sole();
        $this->assertSame("{$tahun}-03-12", $s->simpol_berlaku_sampai->toDateString());
        $this->assertSame('auto', $s->simpol_sumber);

        $hasil = DeteksiMasaSim::dariBerkas($this->pdfTeks("Masa berlaku 01-02-{$tahun}"));
        $this->assertSame('tinggi', $hasil['keyakinan']);

        $this->postJson('/miners/simper/deteksi-sim', ['berkas' => $this->pdfTeks("Valid until 05-06-{$tahun}")])
            ->assertOk()->assertJsonPath('tanggal', "{$tahun}-06-05");

        $this->postJson('/miners/simper/deteksi-sim', ['berkas' => $this->pdfTeks('tanpa tanggal sama sekali di sini')])
            ->assertOk()->assertJsonPath('tanggal', null);
    }

    #[Test]
    public function unit_bertipe_banyak_dan_hanya_admin_mengubahnya_sesudah_terbit(): void
    {
        $p = $this->permitTerbit();
        $this->actingAs($this->pengaju)->post('/miners/simper', $this->isianSimper($p));
        $s = Simper::withoutGlobalScopes()->sole();

        $k = Kendaraan::withoutGlobalScopes()->create(['nama' => 'Dump Truck', 'kode' => 'DT', 'aktif' => true]);
        $j1 = JenisUnit::withoutGlobalScopes()->create(['nama' => 'HD 785', 'aktif' => true]);
        $j2 = JenisUnit::withoutGlobalScopes()->create(['nama' => 'Volvo 40T', 'aktif' => true]);

        $this->post("/miners/simper/{$s->id}/unit", ['kendaraan_id' => $k->id, 'authority' => 'F'])
            ->assertSessionHasErrors(['jenis_unit_ids', 'berkas_teori', 'berkas_praktek']);

        $this->post("/miners/simper/{$s->id}/unit", [
            'kendaraan_id' => $k->id, 'jenis_unit_ids' => [$j1->id, $j2->id], 'authority' => 'P', 'nilai_p2h' => 80, 'nilai_praktek' => 75,
            'berkas_teori' => $this->dokumenPdf('t.pdf'), 'berkas_praktek' => $this->dokumenPdf('p.pdf'),
        ])->assertSessionHasNoErrors();

        $u = SimperUnit::withoutGlobalScopes()->sole();
        $this->assertSame(2, $u->jenisUnitBanyak()->count());
        $this->assertSame('P', $u->authority);

        /* Kirim → HSE → KTT. */
        $this->post("/miners/simper/{$s->id}/kirim")->assertSessionHasNoErrors();
        $this->actingAs($this->ohse)->post("/miners/simper/{$s->id}/tindak", ['keadaan' => 'setuju'])->assertSessionHasNoErrors();
        $this->assertSame('ktt', $s->fresh()->status);
        $this->actingAs($this->ktt)->post("/miners/simper/{$s->id}/tindak", ['keadaan' => 'setuju'])->assertSessionHasNoErrors();
        $this->assertSame('terbit', $s->fresh()->status);

        $ubah = ['kendaraan_id' => $k->id, 'jenis_unit_ids' => [$j1->id], 'authority' => 'F'];
        $this->actingAs($this->pengaju)->put("/miners/simper/{$s->id}/unit/{$u->id}", $ubah)->assertForbidden();
        $this->actingAs($this->admin)->put("/miners/simper/{$s->id}/unit/{$u->id}", $ubah)->assertSessionHasNoErrors();
        $this->assertSame('F', $u->fresh()->authority);
        $this->assertSame(1, $u->jenisUnitBanyak()->count());
    }

    #[Test]
    public function kirim_ditahan_tanpa_unit(): void
    {
        $this->actingAs($this->pengaju)->post('/miners/simper', $this->isianSimper($this->permitTerbit()));
        $s = Simper::withoutGlobalScopes()->sole();

        $this->post("/miners/simper/{$s->id}/kirim")->assertSessionHasErrors('kirim');
        $this->assertSame('draf', $s->fresh()->status);
    }

    #[Test]
    public function masa_berlaku_ikut_tanggal_sim_bukan_permit_dan_nonaktif_beralasan(): void
    {
        $p = $this->permitTerbit();
        $p->forceFill(['berlaku_sampai' => Waktu::kini()->addMonth(), 'sumber_berlaku' => 'manual'])->save();

        $s = $this->simperTerbit($p);
        $this->assertSame(Waktu::kini()->addYears(2)->toDateString(), $s->fresh()->habisEfektif()->toDateString());
        $this->assertSame(Keadaan::BERLAKU, Keadaan::simper($s));

        $this->actingAs($this->pengaju)->post("/miners/simper/{$s->id}/status", ['aktif' => 0, 'alasan' => 'x'])->assertForbidden();
        $this->actingAs($this->admin)->post("/miners/simper/{$s->id}/status", ['aktif' => 0])->assertSessionHasErrors('alasan');
        $this->post("/miners/simper/{$s->id}/status", ['aktif' => 0, 'alasan' => 'Mutasi'])->assertSessionHasNoErrors();

        $s->refresh();
        $this->assertFalse($s->berlaku());
        $this->assertSame(Keadaan::HABIS, Keadaan::simper($s));

        $this->post("/miners/simper/{$s->id}/masa-berlaku", ['sumber' => 'manual', 'tanggal' => '2031-01-31'])->assertSessionHasNoErrors();
        $this->assertSame('2031-01-31', $s->fresh()->simpol_berlaku_sampai->toDateString());
    }

    #[Test]
    public function rincian_dan_daftar_memakai_halaman_baru(): void
    {
        $s = $this->simperTerbit();
        $s->forceFill(['kelas' => 'F'])->save();

        $this->actingAs($this->admin)->get("/miners/simper/{$s->id}")->assertOk()
            ->assertInertia(fn (AssertableInertia $h) => $h->component('Miners/SimperRincian')
                ->where('simper.kelasLama', 'SIMPER-F')
                ->where('unit.0.authority', 'F')
                ->where('unit.0.jenis.0', 'Volvo 40T'));

        $this->get('/miners/riwayat/mine-license')->assertOk()
            ->assertInertia(fn (AssertableInertia $h) => $h->component('Miners/Simper')->where('mode', 'riwayat')->where('baris.total', 1));
    }

    #[Test]
    public function berkas_sim_terjaga_berkas_ddt_terbuka_bagi_pemilik(): void
    {
        $s = $this->simperTerbit();

        $this->actingAs($this->admin)->get("/miners/simper/{$s->id}")
            ->assertInertia(fn (AssertableInertia $h) => $h->where('simper.berkas.0.url', fn ($u) => $u !== null));

        $this->actingAs($this->pengaju)->get("/miners/simper/{$s->id}")
            ->assertInertia(fn (AssertableInertia $h) => $h->where('simper.berkas.0.url', null)
                ->where('simper.berkas.0.ada', true)
                ->where('simper.berkas.1.url', fn ($u) => $u !== null));
    }
}
