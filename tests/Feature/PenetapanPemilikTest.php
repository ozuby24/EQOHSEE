<?php

namespace Tests\Feature;

use App\Models\{Company, News, Procedure, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penetapan pemilik prosedur dan berita lama.
 *
 * Halaman ini memindahkan data ANTAR perusahaan, jadi yang dijaga bukan
 * kemudahannya melainkan batasnya. Dua batas yang paling menentukan:
 *
 * 1. Hanya administrator. Penghuni satu perusahaan tidak berwenang
 *    memutuskan bahwa sebuah prosedur milik perusahaan lain.
 * 2. Hanya baris yang BELUM bertuan. Memindahkan prosedur dari satu
 *    perusahaan ke perusahaan lain adalah tindakan yang berbeda dan
 *    jauh lebih berbahaya — ia mencabut dokumen dari pemiliknya yang
 *    sah, dan itu tidak pernah merupakan pembetulan data lama.
 */
class PenetapanPemilikTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;
    private Company $b;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Company::create(['name' => 'PT A']);
        $this->b = Company::create(['name' => 'PT B']);
        $this->admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    }

    private function prosedur(?int $company = null, string $kode = 'P-1'): Procedure
    {
        return Procedure::withoutGlobalScopes()->create([
            'code' => $kode, 'title' => 'Prosedur '.$kode, 'company_id' => $company,
        ]);
    }

    /* ═══════════ hak akses ═══════════ */

    public function test_bukan_admin_tidak_dapat_membuka(): void
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]))
            ->get(route('admin.pemilik'))
            ->assertForbidden();
    }

    public function test_bukan_admin_tidak_dapat_menetapkan(): void
    {
        $p = $this->prosedur();

        $this->actingAs(User::factory()->create(['company_id' => $this->a->id, 'email_verified_at' => now()]))
            ->post(route('admin.pemilik.tetapkan'), [
                'jenis' => 'prosedur', 'id' => [$p->id], 'company_id' => $this->a->id,
            ])
            ->assertForbidden();

        $this->assertNull($p->fresh()->company_id,
            'Pengguna biasa berhasil menetapkan pemilik prosedur.');
    }

    /* ═══════════ penetapan ═══════════ */

    public function test_admin_menetapkan_prosedur_yatim(): void
    {
        $p1 = $this->prosedur(null, 'P-1');
        $p2 = $this->prosedur(null, 'P-2');

        $this->actingAs($this->admin)
            ->post(route('admin.pemilik.tetapkan'), [
                'jenis' => 'prosedur', 'id' => [$p1->id, $p2->id], 'company_id' => $this->a->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->a->id, $p1->fresh()->company_id);
        $this->assertSame($this->a->id, $p2->fresh()->company_id);
    }

    public function test_berita_yatim_juga_dapat_ditetapkan(): void
    {
        $n = News::withoutGlobalScopes()->create([
            'title' => 'Pengumuman lama', 'content' => 'x', 'published_at' => now(), 'company_id' => null,
        ]);

        $this->actingAs($this->admin)->post(route('admin.pemilik.tetapkan'), [
            'jenis' => 'berita', 'id' => [$n->id], 'company_id' => $this->b->id,
        ]);

        $this->assertSame($this->b->id, $n->fresh()->company_id);
    }

    /**
     * Yang SUDAH bertuan tidak dapat dipindahkan dari sini.
     *
     * Penyaring ini ada di controller, bukan hanya pada daftar yang
     * digambar peramban. Daftar itu digambar sebelum perintahnya
     * dikirim, dan yang dikirim balik peramban bukan bukti apa pun
     * tentang keadaan baris ketika perintahnya tiba.
     */
    public function test_yang_sudah_bertuan_tidak_dapat_dipindahkan(): void
    {
        $milikA = $this->prosedur($this->a->id, 'PA-1');

        $this->actingAs($this->admin)->post(route('admin.pemilik.tetapkan'), [
            'jenis' => 'prosedur', 'id' => [$milikA->id], 'company_id' => $this->b->id,
        ])->assertSessionHasErrors('pemilik');

        $this->assertSame($this->a->id, $milikA->fresh()->company_id,
            'Prosedur dicabut dari pemiliknya yang sah lewat halaman penetapan.');
    }

    /** Yang bertuan ikut terlindungi meski dikirim bersama yang yatim. */
    public function test_pengiriman_campuran_hanya_menyentuh_yang_yatim(): void
    {
        $yatim  = $this->prosedur(null, 'P-0');
        $milikA = $this->prosedur($this->a->id, 'PA-1');

        $this->actingAs($this->admin)->post(route('admin.pemilik.tetapkan'), [
            'jenis' => 'prosedur', 'id' => [$yatim->id, $milikA->id], 'company_id' => $this->b->id,
        ]);

        $this->assertSame($this->b->id, $yatim->fresh()->company_id);
        $this->assertSame($this->a->id, $milikA->fresh()->company_id,
            'Baris bertuan ikut terbawa karena dikirim bersama yang yatim.');
    }

    /* ═══════════ daftar ═══════════ */

    public function test_daftar_hanya_memuat_yang_yatim(): void
    {
        $this->prosedur(null, 'YATIM');
        $this->prosedur($this->a->id, 'BERTUAN');

        $this->actingAs($this->admin)->get(route('admin.pemilik'))
            ->assertInertia(function ($p) {
                $prosedur = collect($p->toArray()['props']['jenis'])->firstWhere('kunci', 'prosedur');
                $judul    = collect($prosedur['baris'])->pluck('judul');

                $this->assertContains('Prosedur YATIM', $judul);
                $this->assertNotContains('Prosedur BERTUAN', $judul);
                $this->assertSame(1, $prosedur['jumlah']);
            });
    }

    /**
     * Sesudah ditetapkan, hanya perusahaan itu yang melihatnya.
     *
     * Inilah akibat yang sebenarnya diinginkan; tanpa uji ini,
     * penetapannya dapat berhasil menulis kolom tanpa benar-benar
     * mengubah siapa yang dapat membaca.
     */
    public function test_sesudah_ditetapkan_hanya_pemiliknya_yang_melihat(): void
    {
        $p = $this->prosedur(null, 'P-1');

        $this->actingAs($this->admin)->post(route('admin.pemilik.tetapkan'), [
            'jenis' => 'prosedur', 'id' => [$p->id], 'company_id' => $this->a->id,
        ]);

        $orangA = User::factory()->create(['company_id' => $this->a->id]);
        $orangB = User::factory()->create(['company_id' => $this->b->id]);

        $this->actingAs($orangA);
        $this->assertContains('Prosedur P-1', Procedure::query()->pluck('title')->all());

        $this->actingAs($orangB);
        $this->assertNotContains('Prosedur P-1', Procedure::query()->pluck('title')->all());
    }
}
