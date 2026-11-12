<?php

namespace Tests\Feature;

use App\Models\Miners\Pekerja;
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\SiapanMiners;
use Tests\TestCase;

/**
 * Monitoring Masa Berlaku (MasaBerlakuController@monitoring Safe Track):
 * kartu status per modul memakai zona yang sama dengan daftar lain,
 * MCU hanya menghitung karyawan aktif, klik zona menyaring daftar, dan
 * unduhan Excel mengikuti saringan.
 */
class MinersMonitoringTest extends TestCase
{
    use RefreshDatabase;
    use SiapanMiners;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkan();
    }

    #[Test]
    public function kartu_status_menghitung_zona_tiap_modul(): void
    {
        $habis = $this->mcuSelesai($this->mitra, 'Fit', Waktu::kini()->subDays(3)->toDateString());
        $this->mcuSelesai($this->mitra, 'Fit', Waktu::kini()->addDays(10)->toDateString());
        $this->simperTerbit();
        /* Orang tidak aktif tidak masuk zona MCU. */
        $lama = $this->mcuSelesai($this->mitra, 'Fit', Waktu::kini()->subDays(30)->toDateString());
        Pekerja::withoutGlobalScopes()->whereKey($lama->pekerja_id)->update(['status' => 'nonaktif']);

        $this->actingAs($this->admin)->get('/miners/kedaluwarsa')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Miners/Kedaluwarsa')
                ->where('ringkas.mcu.expired', 1)
                ->where('ringkas.mcu.kritis', 1)
                ->where('ringkas.mcu.aman', 1)
                ->where('ringkas.mcu.total', 3)
                ->where('ringkas.permit.total', 1)
                ->where('ringkas.simper.aman', 1)
                ->where('manpower.nonaktif', 1)
                /* Perlu Tindak Lanjut: yang lewat lebih dulu. */
                ->where('daftar.data.0.pekerja_id', $habis->pekerja_id)
                ->where('daftar.data.0.zona', 'expired')
                ->has('rekap.mcu.0.nonaktif'));
    }

    #[Test]
    public function klik_zona_menyaring_modul_dan_zona(): void
    {
        $this->mcuSelesai($this->mitra, 'Fit', Waktu::kini()->subDays(3)->toDateString());
        $this->simperTerbit();

        $this->actingAs($this->admin)->get('/miners/kedaluwarsa?jenis=simper&zona=aman')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('disaring', true)
                ->has('daftar.data', 1)
                ->where('daftar.data.0.jenis', 'simper'));
    }

    #[Test]
    public function unduhan_excel_mengikuti_saringan(): void
    {
        $this->simperTerbit();

        $r = $this->actingAs($this->admin)->get('/miners/kedaluwarsa/ekspor?jenis=simper');
        $r->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $r->headers->get('Content-Type'));
    }

    #[Test]
    public function mitra_hanya_melihat_perusahaannya(): void
    {
        $this->mcuSelesai($this->iup, 'Fit', Waktu::kini()->subDays(3)->toDateString());
        $this->mcuSelesai($this->mitra, 'Fit', Waktu::kini()->subDays(3)->toDateString());

        $this->actingAs($this->pengaju)->get('/miners/kedaluwarsa')->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('ringkas.mcu.expired', 1)->has('perusahaan', 1));
    }

    #[Test]
    public function pilihan_perusahaan_saringan_berbentuk_nilai_label_dan_menyaring(): void
    {
        /* SaringBar membaca {nilai, label}; Monitoring dulu mengirim
           {id, name} sehingga pilihannya satu "undefined" (uji E2E). */
        foreach (['/miners', '/miners/kedaluwarsa', '/miners/riwayat/mine-permit', '/miners/riwayat/mine-license'] as $url) {
            $opsi = $this->actingAs($this->admin)->get($url)->assertOk()->viewData('page')['props']['perusahaan'];
            $this->assertNotEmpty($opsi, $url);
            foreach ($opsi as $o) $this->assertSame(['nilai', 'label'], array_keys((array) $o), $url);
            $this->assertContains($this->mitra->id, array_column($opsi, 'nilai'), $url);
        }

        $this->mcuSelesai($this->iup, 'Fit', Waktu::kini()->subDays(3)->toDateString());
        $milik = $this->mcuSelesai($this->mitra, 'Fit', Waktu::kini()->subDays(3)->toDateString());

        $this->get('/miners/kedaluwarsa?perusahaan='.$this->mitra->id)->assertOk()
            ->assertInertia(fn (Assert $p) => $p->has('daftar.data', 1)
                ->where('daftar.data.0.pekerja_id', $milik->pekerja_id));
    }
}
