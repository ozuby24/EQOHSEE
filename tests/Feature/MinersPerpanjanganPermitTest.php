<?php

namespace Tests\Feature;

use App\Models\Miners\{McuOrang, Permit, PermitPerpanjangan, RiwayatPerpanjangan, Skema};
use App\Notifications\PemberitahuanMiners;
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Concerns\SiapanMiners;
use Tests\TestCase;

/**
 * Perpanjangan Mine Permit — skenario tests/perpanjangan.js Safe Track
 * (bagian Permit): rantai pengaju → dokter → HSE → KTT, penulisan balik
 * MCU pada langkah dokter, tanggal kartu berpindah sesudah KTT, riwayat.
 */
class MinersPerpanjanganPermitTest extends TestCase
{
    use RefreshDatabase;
    use SiapanMiners;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkan();
    }

    private function ajukan(Permit $p, array $ganti = [])
    {
        return $this->actingAs($this->pengaju)->post('/miners/perpanjangan-permit', $ganti + [
            'permit_id'    => $p->id,
            'tanggal'      => Waktu::kini()->toDateString(),
            'berlaku_baru' => '2028-01-31',
            'tanggal_mcu'  => Waktu::kini()->toDateString(),
            'berkas_form'  => $this->dokumenPdf('form.pdf'),
            'berkas_mcu'   => $this->dokumenPdf('sertifikat.pdf'),
        ]);
    }

    public function test_kartu_yang_dapat_diperpanjang(): void
    {
        $terbit = $this->permitTerbit();
        $expired = $this->permitTerbit();
        $expired->update(['status' => 'kedaluwarsa']);
        $nonaktif = $this->permitTerbit();
        $nonaktif->update(['aktif' => false]);
        $draf = $this->permitTerbit();
        $draf->update(['status' => 'draf']);

        $calon = fn () => collect($this->actingAs($this->pengaju)->get('/miners/perpanjangan-permit')
            ->viewData('page')['props']['calon'])->pluck('id')->all();

        $this->assertContains($terbit->id, $calon());
        $this->assertContains($expired->id, $calon(), 'Kartu yang sungguh expired hilang dari menu perpanjangan.');
        $this->assertNotContains($nonaktif->id, $calon());
        $this->assertNotContains($draf->id, $calon());

        $this->ajukan($terbit)->assertSessionHasNoErrors();
        $this->assertNotContains($terbit->id, $calon(), 'Kartu dengan perpanjangan berjalan ditawarkan lagi.');

        $this->ajukan($draf)->assertSessionHasErrors('permit_id');
    }

    public function test_perpanjangan_dari_ujung_ke_ujung(): void
    {
        Notification::fake();
        config(['miners.surel.ohse' => 'ohse@contoh.test']);

        $o = $this->mcuSelesai($this->mitra, 'Fit', Waktu::kini()->addDays(5)->toDateString());
        $p = $this->permitTerbit($o);
        $p->update(['status' => 'kedaluwarsa']);
        $lama = $p->berlaku_sampai->toDateString();

        $this->ajukan($p)->assertSessionHasNoErrors();

        $x = PermitPerpanjangan::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->assertSame(['pengaju', 'dokter', 'ohse', 'ktt'], $x->alur->pluck('peran')->all());
        $this->assertSame([$this->pengaju->id, $this->dokter->id, $this->ohse->id, $this->ktt->id],
            $x->alur->pluck('ditugaskan_user_id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame('diperiksa', $x->status, 'Pengajuan tidak langsung menunggu dokter.');
        Notification::assertSentOnDemand(PemberitahuanMiners::class);

        /* HSE belum gilirannya. */
        $this->actingAs($this->ohse)->post("/miners/perpanjangan-permit/{$x->id}/tindak", ['keadaan' => 'setuju'])
            ->assertSessionHasErrors('keadaan');

        /* Dokter: MCU DITULIS BALIK, tanggal kartu belum berpindah. */
        $this->actingAs($this->dokter)->post("/miners/perpanjangan-permit/{$x->id}/tindak", ['keadaan' => 'setuju'])
            ->assertSessionHasNoErrors();

        $o->refresh();
        $this->assertSame('2028-01-31', $o->berlaku_sampai->toDateString(), 'Register MCU tidak ikut diperbarui dokter.');
        $this->assertSame(Waktu::kini()->toDateString(), $o->tanggal_periksa->toDateString());
        $this->assertSame($lama, $p->fresh()->berlaku_sampai->toDateString(), 'Tanggal kartu berpindah sebelum KTT.');
        $this->assertSame($lama, $p->fresh()->habisEfektif()->toDateString(),
            'Kartu yang ikut MCU sudah membaca MCU baru dari dokter — izin diperpanjang tanpa HSE dan KTT.');

        /* Sinkron malam pun tidak memindahkannya selama pengajuan berjalan. */
        \App\Support\Miners\SinkronMasaBerlaku::permitDariMcu();
        $this->assertSame($lama, $p->fresh()->berlaku_sampai->toDateString(), 'Sinkron malam memindahkan kartu yang sedang diperpanjang.');

        /* HSE: kartu belum berpindah, giliran KTT dibuka. */
        $this->actingAs($this->ohse)->post("/miners/perpanjangan-permit/{$x->id}/tindak", ['keadaan' => 'setuju'])->assertSessionHasNoErrors();
        $this->assertSame('ktt', $x->fresh()->status);
        $this->assertSame($lama, $p->fresh()->berlaku_sampai->toDateString());

        /* KTT: tanggal berpindah, sumber perpanjangan, expired kembali terbit, riwayat tercatat. */
        $this->actingAs($this->ktt)->post("/miners/perpanjangan-permit/{$x->id}/tindak", ['keadaan' => 'setuju'])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame('selesai', $x->fresh()->status);
        $this->assertSame('2028-01-31', $p->berlaku_sampai->toDateString());
        $this->assertSame('perpanjangan', $p->sumber_berlaku);
        $this->assertSame('terbit', $p->status);

        $r = RiwayatPerpanjangan::query()->where('jenis', 'permit')->where('dokumen_id', $p->id)->sole();
        $this->assertSame(1, $r->urutan);
        $this->assertSame('2028-01-31', $r->berlaku_baru->toDateString());
        $this->assertSame($lama, $r->berlaku_lama->toDateString(), 'Riwayat mencatat tanggal baru sebagai tanggal lama.');
        $this->assertSame('2028-01-31', $p->habisEfektif()->toDateString());
        $this->assertSame($x->id, (int) $r->perpanjangan_id);

        /* Layar rincian membawa riwayatnya. */
        $this->actingAs($this->pengaju)->get("/miners/perpanjangan-permit/{$x->id}")
            ->assertInertia(fn (AssertableInertia $h) => $h->component('Miners/PerpanjanganRincian')->has('riwayat', 1));
    }

    public function test_tolak_beralasan_dan_dapat_diajukan_ulang(): void
    {
        $p = $this->permitTerbit();
        $this->ajukan($p);
        $x = PermitPerpanjangan::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->actingAs($this->dokter)->post("/miners/perpanjangan-permit/{$x->id}/tindak", ['keadaan' => 'tolak'])
            ->assertSessionHasErrors('keadaan');
        $this->post("/miners/perpanjangan-permit/{$x->id}/tindak", ['keadaan' => 'tolak', 'catatan' => 'Sertifikat MCU tidak terbaca.'])
            ->assertSessionHasNoErrors();
        $this->assertSame('ditolak', $x->fresh()->status);
        $this->assertSame(0, RiwayatPerpanjangan::query()->count(), 'Penolakan tercatat sebagai perpanjangan.');

        $this->actingAs($this->pengaju)->post("/miners/perpanjangan-permit/{$x->id}/ajukan-ulang")->assertSessionHasNoErrors();
        $this->assertSame('diperiksa', $x->fresh()->status);
    }

    public function test_skema_belum_lengkap_menolak_sebelum_menyimpan(): void
    {
        Skema::query()->update(['dokter_user_id' => null]);

        $this->ajukan($this->permitTerbit())->assertSessionHasErrors('permit_id');
        $this->assertSame(0, PermitPerpanjangan::withoutGlobalScopes()->count());
    }
}
