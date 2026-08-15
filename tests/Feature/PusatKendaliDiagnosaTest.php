<?php

namespace Tests\Feature;

use App\Models\{ActivityLog, Company, User};
use App\Support\Diagnosa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, Cache};
use Tests\TestCase;

/**
 * Halaman diagnosa dan tombol perbaikannya.
 *
 * Tombol-tombol di halaman ini menjalankan perintah artisan di server.
 * Dua hal yang dijaga: hanya administrator yang dapat menekannya, dan
 * hanya perintah yang memang terdaftar yang dapat berjalan — nama aksi
 * datang dari URL, dan URL datang dari siapa saja.
 */
class PusatKendaliDiagnosaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        /* Pemetaan skema di-memo untuk seumur proses. Antar uji itu
           membuat hasilnya bergantung pada urutan berjalan — cacat yang
           muncul dan hilang sendiri, dan yang paling mahal dikejar. */
        Diagnosa::lupakanSkema();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    /* ---------- hak akses ---------- */

    public function test_bukan_admin_tidak_dapat_membuka_diagnosa(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.system.diagnosa'))
            ->assertForbidden();
    }

    public function test_bukan_admin_tidak_dapat_menjalankan_perbaikan(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.system.perbaiki', 'tautan-storage'))
            ->assertForbidden();
    }

    public function test_tamu_dialihkan_ke_halaman_masuk(): void
    {
        $this->get(route('admin.system.diagnosa'))->assertRedirect(route('login'));
    }

    /* ---------- halaman ---------- */

    public function test_halaman_memuat_hasil_dan_ringkasannya(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.system.diagnosa'))
            ->assertOk()
            ->assertInertia(function ($p) {
                $props = $p->toArray()['props'];

                $this->assertNotEmpty($props['hasil']);
                $this->assertSame(count($props['hasil']), array_sum($props['ringkas']));
                $this->assertNotEmpty($props['perbaikan']);
            });
    }

    public function test_pusat_kendali_menyebut_ringkasan_diagnosa(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.system'))
            ->assertOk()
            ->assertInertia(function ($p) {
                $d = $p->toArray()['props']['diagnosa'];

                $this->assertArrayHasKey('ringkas', $d);
                $this->assertArrayHasKey(Diagnosa::GAWAT, $d['ringkas']);
                $this->assertSame(route('admin.system.diagnosa'), $d['url']);
            });
    }

    /* ---------- simpanan lencana ---------- */

    /**
     * Lencana Pusat Kendali membaca simpanan supaya halaman itu tidak
     * ikut menanggung ongkos pemeriksaan penuh. Simpanan itu harus
     * dibuang oleh setiap tindakan yang dapat mengubah jawabannya —
     * lencana yang tetap merah sesudah perbaikannya berhasil membuat
     * orang mengulangi perbaikan yang sudah bekerja.
     */
    public function test_lencana_memakai_simpanan(): void
    {
        Cache::forget(Diagnosa::KUNCI_RINGKAS);

        $this->actingAs($this->admin)->get(route('admin.system'))->assertOk();

        $this->assertNotNull(Cache::get(Diagnosa::KUNCI_RINGKAS),
            'Ringkasan tidak disimpan, sehingga tiap kunjungan menghitung ulang seluruhnya.');
    }

    public function test_perbaikan_membuang_simpanan_lencana(): void
    {
        Cache::put(Diagnosa::KUNCI_RINGKAS, ['gawat' => 99], 300);

        $this->actingAs($this->admin)
            ->post(route('admin.system.perbaiki', 'tautan-storage'));

        $this->assertNull(Cache::get(Diagnosa::KUNCI_RINGKAS),
            'Simpanan lencana masih memuat keadaan sebelum perbaikan.');
    }

    public function test_pemeliharaan_membuang_simpanan_lencana(): void
    {
        Cache::put(Diagnosa::KUNCI_RINGKAS, ['gawat' => 99], 300);

        $this->actingAs($this->admin)
            ->post(route('admin.system.maintenance', 'cache'));

        $this->assertNull(Cache::get(Diagnosa::KUNCI_RINGKAS));
    }

    public function test_muat_data_contoh_membuang_simpanan_lencana(): void
    {
        $c = Company::create(['name' => 'PT Contoh', 'demo' => true]);
        User::factory()->create(['company_id' => $c->id]);

        Cache::put(Diagnosa::KUNCI_RINGKAS, ['gawat' => 99], 300);

        $this->actingAs($this->admin)->post(route('admin.system.demo.muat', $c));

        $this->assertNull(Cache::get(Diagnosa::KUNCI_RINGKAS));
    }

    /**
     * Halaman diagnosa selalu menghitung ulang, dan menyegarkan
     * lencananya sekalian — keduanya tidak boleh menyebut angka yang
     * berbeda pada saat yang sama.
     */
    public function test_halaman_diagnosa_menyegarkan_lencana(): void
    {
        Cache::put(Diagnosa::KUNCI_RINGKAS, ['gawat' => 99], 300);

        $this->actingAs($this->admin)
            ->get(route('admin.system.diagnosa'))
            ->assertInertia(function ($p) {
                $ringkas = $p->toArray()['props']['ringkas'];

                $this->assertSame($ringkas, Cache::get(Diagnosa::KUNCI_RINGKAS),
                    'Lencana dan halaman diagnosa menyebut angka yang berbeda.');
                $this->assertNotSame(99, $ringkas['gawat'] ?? null);
            });
    }

    /* ---------- perbaikan ---------- */

    /**
     * Nama aksinya datang dari URL, dan URL datang dari siapa saja.
     * Yang tidak terdaftar harus berhenti sebagai 404, bukan diteruskan
     * ke Artisan.
     */
    public function test_aksi_yang_tidak_terdaftar_ditolak(): void
    {
        foreach (['db:wipe', 'migrate:fresh', 'sembarang', '../../etc/passwd'] as $aksi) {
            $this->actingAs($this->admin)
                ->post(route('admin.system.perbaiki', ['aksi' => $aksi]))
                ->assertNotFound();
        }
    }

    public function test_pasang_tautan_storage_berjalan_dan_tercatat(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.system.perbaiki', 'tautan-storage'))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('ok');

        $this->assertTrue(ActivityLog::where('action', 'Perbaikan sistem')
            ->where('detail', 'Pasang tautan storage')->exists());
    }

    public function test_coba_ulang_antrean_gagal_berjalan(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.system.perbaiki', 'antrean-ulang'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('ok');
    }

    public function test_migrasi_dijalankan_dengan_paksa(): void
    {
        Artisan::shouldReceive('call')->once()
            ->with('migrate', ['--force' => true])->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('Nothing to migrate.');

        $this->actingAs($this->admin)
            ->post(route('admin.system.perbaiki', 'migrasi'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('ok');
    }

    /**
     * Log dipotong, bukan dihapus. Menghapus berkas yang sedang terbuka
     * membuat penulisan berikutnya masuk ke berkas yang tidak lagi
     * punya nama — lognya seolah berhenti sama sekali.
     */
    public function test_potong_log_mengosongkan_tanpa_menghapus_berkasnya(): void
    {
        $berkas = storage_path('logs/laravel.log');
        $adaSebelumnya = is_file($berkas);
        $isiLama = $adaSebelumnya ? file_get_contents($berkas) : null;

        file_put_contents($berkas, str_repeat("baris uji\n", 100));

        try {
            $this->actingAs($this->admin)
                ->post(route('admin.system.perbaiki', 'potong-log'))
                ->assertSessionHasNoErrors();

            $this->assertTrue(is_file($berkas), 'Berkas log ikut terhapus.');
            $this->assertSame('', file_get_contents($berkas));
        } finally {
            if ($adaSebelumnya) file_put_contents($berkas, $isiLama);
            else @unlink($berkas);
        }
    }

    /**
     * Perbaikan yang gagal harus mengatakan sebabnya, bukan diam.
     * Halaman ini dibuka orang yang sedang mencari tahu.
     */
    public function test_perbaikan_yang_gagal_melaporkan_sebabnya(): void
    {
        Artisan::shouldReceive('call')
            ->andThrow(new \RuntimeException('sambungan basis data putus'));

        $this->actingAs($this->admin)
            ->post(route('admin.system.perbaiki', 'migrasi'))
            ->assertSessionHasErrors('perbaikan');

        $this->assertTrue(ActivityLog::where('action', 'Perbaikan sistem gagal')->exists());
    }
}
