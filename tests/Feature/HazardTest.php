<?php

namespace Tests\Feature;

use App\Models\{Company, HazardReport, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Hazard Report — monitor dan pengingat tindak lanjut.
 *
 * Isi pesan pengingat diuji di sini karena penyusunannya kini berada di
 * controller. Selama teksnya dirakit di dalam view, satu-satunya cara
 * memeriksanya adalah merender halaman dan mencari potongan kalimat di
 * dalam HTML — yang ikut gagal setiap kali tata letaknya berubah.
 */
class HazardTest extends TestCase
{
    use RefreshDatabase;

    private static int $n = 0;

    private function perusahaan(array $x = []): Company
    {
        $i = ++self::$n;

        return Company::create(array_merge(['name' => "PT Uji {$i}", 'code' => "PU{$i}"], $x));
    }

    private function masuk(bool $admin = true): User
    {
        $u = User::factory()->create(['is_admin' => $admin]);
        $this->actingAs($u);

        return $u;
    }

    private function laporan(Company $c, array $x = []): HazardReport
    {
        return HazardReport::create(array_merge([
            'kode'         => 'HR-'.str_pad((string) ++self::$n, 4, '0', STR_PAD_LEFT),
            'company_id'   => $c->id,
            'pelapor_nama' => 'Pelapor Uji',
            'tanggal'      => now()->toDateString(),
            'lokasi'       => 'Area uji',
            'risiko'       => 'Sedang',
            'kategori'     => 'Unsafe Condition',
            'deskripsi'    => 'Temuan uji coba.',
            'status'       => 'Open',
        ], $x));
    }

    /* ══════════════ monitor ══════════════ */

    public function test_monitor_dirender_inertia(): void
    {
        $this->masuk();

        $this->get('/hazard')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Hazard/Monitor')
            ->has('stat')->has('opsi')->has('laporan')->has('halaman')->has('tautan'));
    }

    public function test_saringan_risiko_menyisakan_yang_cocok_saja(): void
    {
        $c = $this->perusahaan();
        $this->masuk();

        $this->laporan($c, ['risiko' => 'Tinggi', 'deskripsi' => 'Yang tinggi']);
        $this->laporan($c, ['risiko' => 'Rendah', 'deskripsi' => 'Yang rendah']);

        $props = $this->get('/hazard?risiko=Tinggi')->assertOk()->viewData('page')['props'];

        $this->assertSame(['Yang tinggi'], array_column($props['laporan'], 'deskripsi'));
        $this->assertTrue($props['adaSaringan']);
    }

    public function test_warna_risiko_dan_status_datang_dari_server(): void
    {
        // Cetakan, ekspor, dan layar harus memakai warna yang sama; menulis
        // ulang petanya di sisi klien membuat ketiganya berbeda diam-diam.
        $c = $this->perusahaan();
        $this->masuk();
        $this->laporan($c, ['risiko' => 'Tinggi', 'status' => 'Open']);

        $baris = $this->get('/hazard')->assertOk()->viewData('page')['props']['laporan'][0];

        $this->assertSame(\App\Support\Hazard::WARNA_RISIKO['Tinggi'], $baris['warnaRisiko']);
        $this->assertSame(\App\Support\Hazard::WARNA_STATUS['Open'], $baris['warnaStatus']);
    }

    /* ══════════════ pengingat ══════════════ */

    public function test_pengingat_hanya_memuat_perusahaan_yang_punya_temuan_terbuka(): void
    {
        $punya  = $this->perusahaan(['name' => 'PT Punya Temuan']);
        $bersih = $this->perusahaan(['name' => 'PT Sudah Bersih']);

        $this->masuk();
        $this->laporan($punya, ['status' => 'Open']);
        $this->laporan($bersih, ['status' => 'Closed']);

        $props = $this->get('/hazard/pengingat')->assertOk()->viewData('page')['props'];

        $this->assertSame(['PT Punya Temuan'], array_column($props['perusahaan'], 'nama'));
    }

    public function test_pesan_pengingat_menyebut_hitungan_dan_kode_temuan(): void
    {
        $c = $this->perusahaan(['name' => 'PT Tindak Lanjut']);
        $this->masuk();

        $a = $this->laporan($c, ['risiko' => 'Tinggi', 'status' => 'Open']);
        $this->laporan($c, ['risiko' => 'Rendah', 'status' => 'In Progress']);

        $baris = $this->get('/hazard/pengingat')->assertOk()->viewData('page')['props']['perusahaan'][0];

        $this->assertSame(2, $baris['jumlah']);
        $this->assertSame(1, $baris['tinggi']);
        $this->assertStringContainsString('*2 temuan*', $baris['pesan']);
        $this->assertStringContainsString('*1 berisiko tinggi*', $baris['pesan']);
        $this->assertStringContainsString($a->kode, $baris['pesan']);
    }

    public function test_temuan_lewat_dua_pekan_dihitung_terpisah(): void
    {
        // Yang mendesak bukan sekadar jumlahnya, melainkan berapa yang
        // sudah menggantung terlalu lama.
        $c = $this->perusahaan();
        $this->masuk();

        $this->laporan($c, ['tanggal' => now()->subDays(30)->toDateString()]);
        $this->laporan($c, ['tanggal' => now()->toDateString()]);

        $baris = $this->get('/hazard/pengingat')->assertOk()->viewData('page')['props']['perusahaan'][0];

        $this->assertSame(1, $baris['lama']);
        $this->assertStringContainsString('lebih dari 14 hari', $baris['pesan']);
    }

    public function test_tanpa_email_pic_tautan_email_kosong(): void
    {
        // Halaman menawarkan "Isi email PIC" sebagai gantinya; tautan mailto
        // tanpa alamat hanya membuka klien surel yang kosong.
        $c = $this->perusahaan(['pic_email' => null]);
        $this->masuk();
        $this->laporan($c);

        $baris = $this->get('/hazard/pengingat')->assertOk()->viewData('page')['props']['perusahaan'][0];

        $this->assertNull($baris['mail']);
        $this->assertNotEmpty($baris['urlEdit']);
    }

    public function test_tamu_tidak_dapat_membuka_hazard(): void
    {
        $this->get('/hazard')->assertRedirect(route('login'));
        $this->get('/hazard/pengingat')->assertRedirect(route('login'));
    }
}
