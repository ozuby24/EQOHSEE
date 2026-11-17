<?php

namespace Tests\Feature;

use App\Models\Miners\{JenisUnit, Kendaraan, RiwayatPerpanjangan, SimperAjuan, SimperAjuanUnit};
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\SiapanMiners;
use Tests\TestCase;

/**
 * SIMPER Lanjutan mengikuti Safe Track (tests/penambahan-ktt.js,
 * tests/perpanjangan.js): penambahan menyalin unit lama dan butuh HSE →
 * KTT; upgrade mengubah authority sesudah HSE; perpanjangan dokter → HSE
 * → KTT dengan tanggal SIMPER ditahan sampai KTT.
 */
class MinersSimperLanjutanTest extends TestCase
{
    use RefreshDatabase;
    use SiapanMiners;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkan();
    }

    #[Test]
    public function penambahan_menyalin_unit_lama_dan_unit_baru_tampil_sesudah_ktt(): void
    {
        $s = $this->simperTerbit();

        $this->actingAs($this->pengaju)->post('/miners/simper-lanjutan', [
            'jenis' => 'penambahan', 'simper_id' => $s->id,
            'berkas_evaluasi' => $this->dokumenPdf('e.pdf'), 'berkas_pengalaman' => $this->dokumenPdf('p.pdf'),
        ])->assertSessionHasNoErrors();

        $a = SimperAjuan::withoutGlobalScopes()->sole();
        $this->assertSame('SIMPER-ADD00'.$a->id, $a->no_registrasi);
        $this->assertSame(1, SimperAjuanUnit::withoutGlobalScopes()->where('ajuan_id', $a->id)->whereNotNull('simper_unit_id')->count());

        /* Belum ada unit baru → belum dapat dikirim. */
        $this->post("/miners/simper-lanjutan/{$a->id}/kirim")->assertSessionHasErrors('kirim');

        $k = Kendaraan::withoutGlobalScopes()->create(['nama' => 'Light Vehicle', 'kode' => 'LV', 'aktif' => true]);
        $j = JenisUnit::withoutGlobalScopes()->create(['nama' => 'Hilux', 'aktif' => true]);
        $this->post("/miners/simper-lanjutan/{$a->id}/unit", [
            'kendaraan_id' => $k->id, 'jenis_unit_ids' => [$j->id], 'authority' => 'F',
            'berkas_teori' => $this->dokumenPdf('t.pdf'), 'berkas_praktek' => $this->dokumenPdf('k.pdf'),
        ])->assertSessionHasNoErrors();

        $this->post("/miners/simper-lanjutan/{$a->id}/kirim")->assertSessionHasNoErrors();
        $this->assertSame(0, $s->fresh()->unitTambahan()->count());

        $this->actingAs($this->ohse)->post("/miners/simper-lanjutan/{$a->id}/tindak", ['keadaan' => 'setuju'])->assertSessionHasNoErrors();
        $this->assertSame('ktt', $a->fresh()->status);
        $this->actingAs($this->ktt)->post("/miners/simper-lanjutan/{$a->id}/tindak", ['keadaan' => 'setuju'])->assertSessionHasNoErrors();

        $this->assertSame('selesai', $a->fresh()->status);
        $this->assertSame([$k->id], $s->fresh()->unitTambahan()->pluck('kendaraan_id')->map(fn ($v) => (int) $v)->all());

        $this->actingAs($this->admin)->get("/miners/simper/{$s->id}")
            ->assertInertia(fn (AssertableInertia $h) => $h->has('unitTambahan', 1)->has('unit', 1));
    }

    #[Test]
    public function upgrade_menulis_authority_baru_sesudah_hse(): void
    {
        $s = $this->simperTerbit();
        $unit = $s->unit()->first();
        $unit->update(['authority' => 'P']);

        $this->actingAs($this->pengaju)->post('/miners/simper-lanjutan', ['jenis' => 'upgrade', 'simper_id' => $s->id])->assertSessionHasNoErrors();
        $a = SimperAjuan::withoutGlobalScopes()->sole();

        $this->post("/miners/simper-lanjutan/{$a->id}/unit", ['simper_unit_id' => $unit->id, 'authority' => 'F'])
            ->assertSessionHasErrors(['berkas_upgrade', 'berkas_ddt', 'berkas_jam']);

        $this->post("/miners/simper-lanjutan/{$a->id}/unit", [
            'simper_unit_id' => $unit->id, 'authority' => 'F',
            'berkas_upgrade' => $this->dokumenPdf('u.pdf'), 'berkas_ddt' => $this->dokumenPdf('d.pdf'), 'berkas_jam' => $this->dokumenPdf('j.pdf'),
        ])->assertSessionHasNoErrors();

        $this->post("/miners/simper-lanjutan/{$a->id}/kirim")->assertSessionHasNoErrors();
        $this->assertSame('P', $unit->fresh()->authority);

        $this->actingAs($this->ohse)->post("/miners/simper-lanjutan/{$a->id}/tindak", ['keadaan' => 'setuju'])->assertSessionHasNoErrors();

        $this->assertSame('selesai', $a->fresh()->status);
        $this->assertSame('F', $unit->fresh()->authority);
    }

    #[Test]
    public function perpanjangan_dokter_menulis_mcu_dan_tanggal_simper_ditahan_sampai_ktt(): void
    {
        $s = $this->simperTerbit();
        $lama = $s->simpol_berlaku_sampai->toDateString();
        $baru = Waktu::kini()->addYears(5)->toDateString();

        $isian = fn () => [
            'jenis' => 'perpanjangan', 'simper_id' => $s->id, 'no_simpol_baru' => 'SIM-BARU-9',
            'simpol_berlaku_sampai' => $baru, 'tanggal_mcu' => Waktu::kini()->toDateString(),
            'berkas_simpol' => $this->dokumenPdf('s.pdf'), 'berkas_mcu' => $this->dokumenPdf('m.pdf'), 'berkas_form' => $this->dokumenPdf('f.pdf'),
        ];
        $this->actingAs($this->pengaju)->post('/miners/simper-lanjutan', $isian())->assertSessionHasNoErrors();

        $a = SimperAjuan::withoutGlobalScopes()->sole();

        /* Tidak boleh dua perpanjangan berjalan sekaligus. */
        $this->post('/miners/simper-lanjutan', $isian())->assertSessionHasErrors('simper_id');

        $this->post("/miners/simper-lanjutan/{$a->id}/kirim")->assertSessionHasNoErrors();
        $this->actingAs($this->dokter)->post("/miners/simper-lanjutan/{$a->id}/tindak", ['keadaan' => 'setuju'])->assertSessionHasNoErrors();

        $this->assertSame($baru, $s->permit->mcuOrang->fresh()->berlaku_sampai->toDateString());
        $this->assertSame($lama, $s->fresh()->habisEfektif()->toDateString());

        $this->actingAs($this->ohse)->post("/miners/simper-lanjutan/{$a->id}/tindak", ['keadaan' => 'setuju']);
        $this->assertSame($lama, $s->fresh()->habisEfektif()->toDateString());

        $this->actingAs($this->ktt)->post("/miners/simper-lanjutan/{$a->id}/tindak", ['keadaan' => 'setuju'])->assertSessionHasNoErrors();

        $s->refresh();
        $this->assertSame($baru, $s->habisEfektif()->toDateString());
        $this->assertSame('perpanjangan', $s->simpol_sumber);
        $this->assertSame('SIM-BARU-9', $s->no_simpol);

        $h = RiwayatPerpanjangan::query()->where('jenis', 'simper')->sole();
        $this->assertSame($lama, $h->berlaku_lama->toDateString());
        $this->assertSame('SIM-LAMA-1', $h->data_lama['simpol']);
    }

    #[Test]
    public function daftar_lanjutan_dan_simper_nonaktif_tidak_dapat_diajukan(): void
    {
        $s = $this->simperTerbit();
        $s->update(['aktif' => false, 'alasan_nonaktif' => 'Mutasi']);

        $this->actingAs($this->pengaju)->get('/miners/daftar/perpanjangan')->assertOk()
            ->assertInertia(fn (AssertableInertia $h) => $h->component('Miners/SimperLanjutan')->where('jenis', 'perpanjangan')->has('calon', 0));

        $this->post('/miners/simper-lanjutan', ['jenis' => 'penambahan', 'simper_id' => $s->id,
            'berkas_evaluasi' => $this->dokumenPdf(), 'berkas_pengalaman' => $this->dokumenPdf()])->assertSessionHasErrors('simper_id');
    }
}
