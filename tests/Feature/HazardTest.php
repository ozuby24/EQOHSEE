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

    /* ══════════════ analitik ══════════════ */

    public function test_analitik_dirender_inertia(): void
    {
        $this->masuk();

        $this->get('/hazard/analitik')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Hazard/Analitik')
            ->has('golongan')->has('tren')->has('sebaran')->has('pelapor'));
    }

    public function test_capaian_dihitung_terhadap_target_golongan(): void
    {
        $c = $this->perusahaan();
        $this->masuk();

        // Jabatan di luar daftar khusus bertarget 4 laporan per bulan.
        $this->laporan($c, ['pelapor_nama' => 'Budi', 'pelapor_jabatan' => 'Operator']);

        $props = $this->get('/hazard/analitik')->assertOk()->viewData('page')['props'];

        $this->assertSame(1, $props['pelapor'][0]['aktual']);
        $this->assertSame(4, $props['pelapor'][0]['target']);
        $this->assertSame(25, $props['pelapor'][0]['pct']);
    }

    public function test_tren_selalu_dua_belas_bulan_meski_kosong(): void
    {
        // Sumbu yang memendek mengikuti data membuat dua kunjungan pada
        // halaman yang sama tampak seperti rentang waktu yang berbeda.
        $this->masuk();

        $tren = $this->get('/hazard/analitik')->assertOk()->viewData('page')['props']['tren'];

        $this->assertCount(12, $tren);
    }

    public function test_golongan_menjumlahkan_target_seluruh_orangnya(): void
    {
        $c = $this->perusahaan();
        $this->masuk();

        $this->laporan($c, ['pelapor_nama' => 'Budi',  'pelapor_nrp' => 'A1', 'pelapor_jabatan' => 'Operator']);
        $this->laporan($c, ['pelapor_nama' => 'Cakra', 'pelapor_nrp' => 'A2', 'pelapor_jabatan' => 'Operator']);

        $g = $this->get('/hazard/analitik')->assertOk()->viewData('page')['props']['golongan'][0];

        $this->assertSame(2, $g['orang']);
        $this->assertSame(8, $g['target'], 'Dua orang bertarget 4 menjadi 8, bukan tetap 4.');
        $this->assertSame(2, $g['aktual']);
    }

    public function test_target_mengikuti_jumlah_bulan_bukan_jumlah_laporan(): void
    {
        /* Pengali target adalah banyaknya bulan yang berisi laporan. Sempat
           terhitung dari jumlah baris karena ->distinct()->count() menimpa
           SELECT dengan count(*) sehingga DISTINCT atas ekspresi bulannya
           hilang. Akibatnya target tiap orang membesar setiap ada laporan
           baru, dan capaian semua orang merosot tanpa sebab. */
        $c = $this->perusahaan();
        $this->masuk();

        foreach (range(1, 5) as $i) {
            $this->laporan($c, [
                'pelapor_nama' => 'Budi', 'pelapor_nrp' => 'A1',
                'pelapor_jabatan' => 'Operator',
                'tanggal' => now()->startOfMonth()->addDays($i)->toDateString(),
            ]);
        }

        $o = $this->get('/hazard/analitik')->assertOk()->viewData('page')['props']['pelapor'][0];

        $this->assertSame(4, $o['target'], 'Lima laporan dalam satu bulan tetap satu bulan.');
        $this->assertSame(5, $o['aktual']);
    }

    public function test_satu_orang_dengan_ejaan_berbeda_tidak_terpecah(): void
    {
        /* Nama diketik di lapangan, dan satu orang yang sama muncul
           sebagai "Budi Santoso", "budi santoso", dan "Budi  Santoso".
           Terhitung mentah, ketiganya menjadi TIGA orang — dan karena
           target dijumlahkan per orang, targetnya ikut tiga kali lipat
           sementara laporannya tetap tiga. Capaian orang itu, beserta
           capaian golongannya, ambruk menjadi sepertiga tanpa satu pun
           galat muncul. */
        $c = $this->perusahaan();
        $this->masuk();

        foreach (['Budi Santoso', 'budi santoso', 'Budi  Santoso '] as $ejaan) {
            $this->laporan($c, ['pelapor_nama' => $ejaan, 'pelapor_jabatan' => 'Operator']);
        }

        $props = $this->get('/hazard/analitik')->assertOk()->viewData('page')['props'];

        $this->assertCount(1, $props['pelapor'], 'Satu orang harus tetap satu baris.');
        $this->assertSame(3, $props['pelapor'][0]['aktual']);
        $this->assertSame(4, $props['pelapor'][0]['target'], 'Target tidak boleh ikut berlipat.');
        $this->assertSame(1, $props['golongan'][0]['orang']);
    }

    public function test_nrp_menyatukan_orang_meski_namanya_ditulis_lain(): void
    {
        // NRP diketik sekali dan jarang berubah — lebih dapat dipercaya
        // daripada nama.
        $c = $this->perusahaan();
        $this->masuk();

        $this->laporan($c, ['pelapor_nama' => 'B. Santoso',   'pelapor_nrp' => 'NRP-001', 'pelapor_jabatan' => 'Operator']);
        $this->laporan($c, ['pelapor_nama' => 'Budi Santoso', 'pelapor_nrp' => 'nrp001',  'pelapor_jabatan' => 'Operator']);

        $props = $this->get('/hazard/analitik')->assertOk()->viewData('page')['props'];

        $this->assertCount(1, $props['pelapor']);
        $this->assertSame(2, $props['pelapor'][0]['aktual']);
    }

    public function test_jabatan_terkini_dipakai_bukan_yang_tersalin_di_laporan(): void
    {
        /* Teks pada laporan adalah salinan saat laporan dibuat. Orang yang
           berganti jabatan akan menyeret jabatan lamanya — beserta target
           lama — di seluruh laporan terdahulunya. */
        $c = $this->perusahaan();
        $this->masuk();

        $orang = User::factory()->create(['company_id' => $c->id, 'position' => 'Manager']);

        $this->laporan($c, [
            'user_id' => $orang->id,
            'pelapor_nama' => $orang->name,
            'pelapor_jabatan' => 'Operator',   // jabatan lama, tersalin di laporan
        ]);

        $baris = $this->get('/hazard/analitik')->assertOk()->viewData('page')['props']['pelapor'][0];

        $this->assertSame('Manager', $baris['jabatan']);
        $this->assertSame(\App\Support\Hazard::target('Manager'), $baris['target']);
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
