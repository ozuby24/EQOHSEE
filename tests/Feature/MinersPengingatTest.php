<?php

namespace Tests\Feature;

use App\Models\Miners\McuOrang;
use App\Models\Miners\Simper;
use App\Notifications\PemberitahuanMiners;
use App\Support\Miners\PengingatMasaBerlaku;
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\SiapanMiners;
use Tests\TestCase;

/**
 * Pengingat masa berlaku (Alert:MasaBerlaku Safe Track) dan deteksi SIM
 * massal (masa-berlaku:deteksi-sim) — yang kedua hanya MENGUSULKAN.
 */
class MinersPengingatTest extends TestCase
{
    use RefreshDatabase;
    use SiapanMiners;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkan();
        Notification::fake();
    }

    #[Test]
    public function ambang_mengikuti_zona(): void
    {
        $this->assertNull(PengingatMasaBerlaku::ambang(61));
        $this->assertSame(60, PengingatMasaBerlaku::ambang(60));
        $this->assertSame(30, PengingatMasaBerlaku::ambang(30));
        $this->assertSame(30, PengingatMasaBerlaku::ambang(0));
        $this->assertSame(0, PengingatMasaBerlaku::ambang(-1));
        $this->assertNull(PengingatMasaBerlaku::ambang(null));
    }

    #[Test]
    public function pengingat_terkirim_sekali_per_dokumen_dan_ambang(): void
    {
        $m = $this->mcuSelesai($this->mitra, 'Fit', Waktu::kini()->addDays(20)->toDateString());
        $this->mcuSelesai($this->mitra, 'Fit', Waktu::kini()->addDays(200)->toDateString());

        $this->artisan('miners:pengingat')->assertSuccessful();
        Notification::assertSentOnDemandTimes(PemberitahuanMiners::class, 1);
        Notification::assertSentOnDemand(PemberitahuanMiners::class, function ($n, $ch, $notifiable) {
            return in_array($this->ohse->email, (array) $notifiable->routes['mail'], true)
                && collect($n->baris)->contains(fn ($b) => str_contains($b, '≤ 30 hari'));
        });
        $this->assertSame(1, DB::table('mnr_pengingat')->where('ambang', 30)->count());

        /* Dijalankan ulang hari yang sama: tidak ada surel kedua. */
        $this->artisan('miners:pengingat')->assertSuccessful();
        Notification::assertSentOnDemandTimes(PemberitahuanMiners::class, 1);

        /* Lewat masa berlaku: ambang berikutnya terkirim sekali lagi. */
        McuOrang::withoutGlobalScopes()->whereKey($m->id)->update(['berlaku_sampai' => Waktu::kini()->subDay()->toDateString()]);
        $this->artisan('miners:pengingat')->assertSuccessful();
        Notification::assertSentOnDemandTimes(PemberitahuanMiners::class, 2);
        $this->assertSame(1, DB::table('mnr_pengingat')->where('ambang', 0)->count());
    }

    #[Test]
    public function uji_coba_tidak_mengirim_dan_tidak_mencatat(): void
    {
        $this->mcuSelesai($this->mitra, 'Fit', Waktu::kini()->addDays(5)->toDateString());

        $this->artisan('miners:pengingat --uji-coba')->assertSuccessful();
        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('mnr_pengingat')->count());
    }

    #[Test]
    public function pengingat_terjadwal_setiap_pagi(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('miners:pengingat')->assertSuccessful();
    }

    #[Test]
    public function deteksi_sim_massal_hanya_mengusulkan(): void
    {
        $tahun = (int) Waktu::kini()->format('Y') + 2;
        $lama  = $this->simperTerbit();
        $diisi = $this->simperTerbit();

        Storage::disk('local')->put('miners/simper/lama.pdf', $this->pdfTeks("Berlaku s/d 12-03-{$tahun}")->getContent());
        Simper::withoutGlobalScopes()->whereKey($lama->id)->update(['simpol_berlaku_sampai' => null, 'simpol_sumber' => null, 'berkas_simpol' => 'miners/simper/lama.pdf']);
        Simper::withoutGlobalScopes()->whereKey($diisi->id)->update(['berkas_simpol' => 'miners/simper/lama.pdf']);
        $tanggalDiisi = Simper::withoutGlobalScopes()->find($diisi->id)->simpol_berlaku_sampai->toDateString();

        $this->artisan('miners:deteksi-sim')->assertSuccessful();

        $lama = Simper::withoutGlobalScopes()->find($lama->id);
        $this->assertNull($lama->simpol_berlaku_sampai, 'Tanggal SIM tidak boleh ditulis oleh deteksi massal.');
        $this->assertSame("{$tahun}-03-12", $lama->simpol_meta['usulan']['tanggal']);
        $this->assertSame($tanggalDiisi, Simper::withoutGlobalScopes()->find($diisi->id)->simpol_berlaku_sampai->toDateString());

        /* Usulan tampil di detail SIMPER. */
        $this->actingAs($this->admin)->get("/miners/simper/{$lama->id}")->assertOk()
            ->assertInertia(fn ($p) => $p->where('simper.usulanSim.tanggal', "{$tahun}-03-12"));
    }
}
