<?php

namespace Tests\Feature;

use App\Models\{Course, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengelolaan kursus dari sisi admin.
 *
 * Rute `courses/create` pernah tertutup oleh `courses/{course}` yang
 * terdaftar lebih dulu: "create" terbaca sebagai id kursus, pengikatan
 * modelnya gagal, dan tombol "Tambah Kursus" berujung 404. Urutan
 * pendaftaran rute tidak terlihat pada `route:list` — perintah itu
 * mengurutkan keluarannya menurut abjad, sehingga `courses/create`
 * tampil lebih dulu justru ketika ia terdaftar belakangan.
 */
class KursusKelolaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function biasa(): User
    {
        return User::factory()->create(['is_admin' => false]);
    }

    public function test_admin_dapat_membuka_formulir_kursus_baru(): void
    {
        $this->actingAs($this->admin());

        $this->get('/courses/create')
            ->assertOk()
            ->assertSee('Judul');
    }

    public function test_formulir_kursus_baru_tidak_terbaca_sebagai_kursus(): void
    {
        $this->actingAs($this->admin());

        // Bila 'create' tertangkap sebagai id, halamannya 404 — bukan 200
        // dan bukan pula halaman detail kursus mana pun.
        $this->get('/courses/create')->assertOk()->assertDontSee('Mulai Belajar');
    }

    public function test_admin_dapat_menyimpan_kursus_baru(): void
    {
        $this->actingAs($this->admin());

        $this->post('/courses', [
            'title'       => 'Dasar Ventilasi Tambang Bawah Tanah',
            'description' => 'Pengantar aliran udara dan pengendalian gas.',
            'category'    => 'Wajib',
        ])->assertRedirect();

        $this->assertDatabaseHas('courses', ['title' => 'Dasar Ventilasi Tambang Bawah Tanah']);
    }

    public function test_admin_dapat_menyunting_kursus(): void
    {
        $this->actingAs($this->admin());
        $c = Course::create(['title' => 'Kursus Lama', 'description' => 'Uraian.', 'category' => 'Wajib']);

        $this->get("/courses/{$c->id}/edit")->assertOk()->assertSee('Kursus Lama');
    }

    public function test_pengguna_biasa_tidak_dapat_membuka_formulir(): void
    {
        $this->actingAs($this->biasa());

        $this->get('/courses/create')->assertForbidden();
    }

    public function test_daftar_kursus_menuntun_ke_formulir_yang_benar(): void
    {
        $this->actingAs($this->admin());

        // Tombol pada katalog harus menunjuk alamat yang sungguh terdaftar.
        $this->get('/courses')
            ->assertOk()
            ->assertSee(route('courses.create'), false);
    }
}
