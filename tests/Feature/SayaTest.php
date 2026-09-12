<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Hr\{Cuti, JenisCuti, PeriodeGaji, SlipGaji};
use App\Models\Miners\Pekerja;
use App\Models\User;
use App\Support\Hr\{MasterCuti, MasterRoster};
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Layanan mandiri pekerja: halaman "milik saya".
 *
 * Yang diuji terutama adalah PENGIRIMAN, bukan pembacaan — batas
 * pembacaannya sudah dikunci EssBatasTest. Pengiriman punya lubang yang
 * berbeda bentuknya: formulir di peramban dapat disunting, dan kolom
 * tersembunyi yang menyebut `pekerja_id` adalah satu-satunya hal yang
 * memisahkan "mengajukan cuti saya" dari "mengajukan cuti rekan saya,
 * yang memotong saldo rekan saya".
 */
class SayaTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $akun;
    private Pekerja $saya;
    private Pekerja $rekan;

    protected function setUp(): void
    {
        parent::setUp();

        MasterRoster::pasang();
        MasterCuti::pasang();

        $this->c    = Company::create(['name' => 'PT Uji Saya']);
        $this->akun = User::factory()->create(['company_id' => $this->c->id]);

        $this->saya  = $this->orang('Saya', $this->akun);
        $this->rekan = $this->orang('Rekan');
    }

    private function orang(string $nama, ?User $u = null): Pekerja
    {
        return Pekerja::withoutGlobalScopes()->create([
            'company_id'    => $this->c->id,
            'nama'          => $nama,
            'no_registrasi' => 'REG-'.uniqid(),
            'status'        => 'aktif',
            'tanggal_masuk' => Waktu::kini()->copy()->subYears(3)->startOfDay(),
            'user_id'       => $u?->id,
        ]);
    }

    private function tahunan(): JenisCuti
    {
        return JenisCuti::withoutGlobalScopes()->where('kunci', 'tahunan')->firstOrFail();
    }

    /* ═══════════════════ halaman ═══════════════════ */

    #[Test]
    public function test_kelima_halaman_terbuka_bagi_pekerja(): void
    {
        foreach (['', '/kehadiran', '/cuti', '/gaji', '/kontrak'] as $jalur) {
            $this->actingAs($this->akun)
                ->get('/hris/saya'.$jalur)
                ->assertOk();
        }
    }

    #[Test]
    public function test_akun_tanpa_pekerja_mendapat_halaman_kosong_bukan_galat(): void
    {
        $lain = User::factory()->create(['company_id' => $this->c->id, 'is_admin' => true]);

        // Admin, auditor, dan pengawas kantor memang bukan pekerja
        // tambang mana pun. Halaman yang memerah akan membuat mereka
        // mengira ada yang rusak dan melaporkannya.
        $this->actingAs($lain)->get('/hris/saya')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Hris/Saya/Kosong'));
    }

    /* ═══════════════════ pengajuan cuti ═══════════════════ */

    #[Test]
    public function test_pengajuan_tercatat_atas_nama_sendiri(): void
    {
        $mulai = Waktu::kini()->copy()->addDays(7)->toDateString();

        $this->actingAs($this->akun)->post('/hris/saya/cuti', [
            'jenis_cuti_id' => $this->tahunan()->id,
            'mulai'         => $mulai,
            'selesai'       => $mulai,
            'alasan'        => 'Keperluan keluarga.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Cuti::withoutGlobalScopes()
            ->where('pekerja_id', $this->saya->id)->count());
    }

    #[Test]
    public function test_pekerja_id_dari_formulir_diabaikan(): void
    {
        $mulai = Waktu::kini()->copy()->addDays(7)->toDateString();

        /* KOLOM TERSEMBUNYI YANG DISUNTING. Diterima, seorang pekerja
           dapat mengajukan cuti atas nama rekannya — dan cuti itu
           memotong saldo rekannya, disetujui atasan rekannya, dan
           menuliskan hari libur ke kalender regu rekannya. */
        $this->actingAs($this->akun)->post('/hris/saya/cuti', [
            'pekerja_id'    => $this->rekan->id,
            'jenis_cuti_id' => $this->tahunan()->id,
            'mulai'         => $mulai,
            'selesai'       => $mulai,
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, Cuti::withoutGlobalScopes()
            ->where('pekerja_id', $this->rekan->id)->count(),
            'Pengajuan tercatat atas nama rekan sekerja.');

        $this->assertSame(1, Cuti::withoutGlobalScopes()
            ->where('pekerja_id', $this->saya->id)->count());
    }

    #[Test]
    public function test_pengajuan_tunduk_pada_aturan_kebijakan_yang_sama(): void
    {
        $mulai = Waktu::kini()->copy()->addDays(7)->toDateString();

        // Tanggal selesai yang mendahului tanggal mulai ditolak
        // KebijakanCuti, dan layanan mandiri memakai ulang aturan itu
        // apa adanya alih-alih menulis versinya sendiri.
        $this->actingAs($this->akun)->post('/hris/saya/cuti', [
            'jenis_cuti_id' => $this->tahunan()->id,
            'mulai'         => $mulai,
            'selesai'       => Waktu::kini()->copy()->addDays(3)->toDateString(),
        ])->assertSessionHasErrors();

        $this->assertSame(0, Cuti::withoutGlobalScopes()->count());
    }

    /* ═══════════════════ pembatalan ═══════════════════ */

    #[Test]
    public function test_pengajuan_rekan_tidak_dapat_dibatalkan(): void
    {
        $punyaRekan = Cuti::withoutGlobalScopes()->create([
            'company_id'    => $this->c->id,
            'pekerja_id'    => $this->rekan->id,
            'jenis_cuti_id' => $this->tahunan()->id,
            'mulai'         => Waktu::kini()->copy()->addDays(7)->startOfDay(),
            'selesai'       => Waktu::kini()->copy()->addDays(7)->startOfDay(),
            'hari'          => 1,
            'kalender'      => 1,
            'status'        => 'menunggu',
        ]);

        // Id pada alamat adalah hal termudah yang dapat diubah
        // seseorang. Pengikatan model rute polos akan menemukannya.
        $this->actingAs($this->akun)
            ->post("/hris/saya/cuti/{$punyaRekan->id}/batal")
            ->assertSessionHasErrors('cuti');

        $this->assertSame('menunggu', $punyaRekan->fresh()->status,
            'Pengajuan rekan dibatalkan lewat alamat.');
    }

    #[Test]
    public function test_pengajuan_sendiri_dapat_dibatalkan(): void
    {
        $punyaSaya = Cuti::withoutGlobalScopes()->create([
            'company_id'    => $this->c->id,
            'pekerja_id'    => $this->saya->id,
            'jenis_cuti_id' => $this->tahunan()->id,
            'mulai'         => Waktu::kini()->copy()->addDays(7)->startOfDay(),
            'selesai'       => Waktu::kini()->copy()->addDays(7)->startOfDay(),
            'hari'          => 1,
            'kalender'      => 1,
            'status'        => 'menunggu',
        ]);

        $this->actingAs($this->akun)
            ->post("/hris/saya/cuti/{$punyaSaya->id}/batal")
            ->assertSessionHasNoErrors();

        $this->assertSame('dibatalkan', $punyaSaya->fresh()->status);
    }

    /* ═══════════════════ slip gaji ═══════════════════ */

    #[Test]
    public function test_hanya_slip_periode_terkunci_yang_tampil(): void
    {
        foreach ([['terhitung', 8], ['terkunci', 7]] as [$status, $bulan]) {
            $periode = PeriodeGaji::withoutGlobalScopes()->create([
                'company_id' => $this->c->id, 'tahun' => 2026, 'bulan' => $bulan, 'status' => $status,
            ]);

            SlipGaji::withoutGlobalScopes()->create([
                'company_id' => $this->c->id, 'periode_id' => $periode->id,
                'pekerja_id' => $this->saya->id, 'pokok' => 5_000_000, 'neto' => 4_500_000,
            ]);
        }

        /* Periode yang belum dikunci masih pratinjau: angkanya dapat
           berubah saat dihitung ulang, dan angka gaji yang berubah
           sesudah dilihat orangnya adalah cara tercepat kehilangan
           kepercayaan atas seluruh sistem. */
        $this->actingAs($this->akun)->get('/hris/saya/gaji')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->has('slip', 1));
    }
}
