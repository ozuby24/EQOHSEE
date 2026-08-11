<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use App\Support\{Tema, Waktu, WarnaLogo};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PersonaliaTest extends TestCase
{
    use RefreshDatabase;

    private function perusahaan(array $x = []): Company
    {
        return Company::create(array_merge(['name' => 'PT Tambang Uji', 'code' => 'PTU'], $x));
    }

    /* ---------- data diri ---------- */

    public function test_pengguna_dapat_menyimpan_data_dirinya(): void
    {
        $u = User::factory()->create(['name' => 'Febrianto']);
        $this->actingAs($u);

        $this->post(route('personalia.simpan'), [
            'name'       => 'Febrianto Ardiansyah',
            'email'      => $u->email,
            'position'   => 'Safety Officer',
            'department' => 'HSE',
            'phone'      => '081234567890',
        ])->assertRedirect();

        $u->refresh();
        $this->assertSame('Febrianto Ardiansyah', $u->name);
        $this->assertSame('Safety Officer', $u->position);
    }

    public function test_surel_yang_sudah_dipakai_orang_lain_ditolak(): void
    {
        $lain = User::factory()->create(['email' => 'sudah@ada.test']);
        $u    = User::factory()->create();
        $this->actingAs($u);

        $this->post(route('personalia.simpan'), ['name' => 'Nama', 'email' => $lain->email])
             ->assertSessionHasErrors('email');
    }

    public function test_mengganti_surel_membatalkan_verifikasinya(): void
    {
        // Alamat baru belum terbukti milik orang yang sama.
        $u = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($u);

        $this->post(route('personalia.simpan'), ['name' => $u->name, 'email' => 'baru@eqohsee.test']);

        $this->assertNull($u->refresh()->email_verified_at);
    }

    public function test_foto_profil_tersimpan_dan_dapat_dihapus(): void
    {
        Storage::fake('public');
        $u = User::factory()->create();
        $this->actingAs($u);

        $this->post(route('personalia.simpan'), [
            'name'   => $u->name,
            'email'  => $u->email,
            'avatar' => UploadedFile::fake()->image('saya.jpg', 300, 300),
        ]);

        $u->refresh();
        $this->assertNotNull($u->avatar);
        Storage::disk('public')->assertExists($u->avatar);

        $lama = $u->avatar;
        $this->delete(route('personalia.avatar.hapus'));

        $this->assertNull($u->refresh()->avatar);
        Storage::disk('public')->assertMissing($lama);
    }

    /* ---------- perusahaan ---------- */

    public function test_admin_dapat_menyunting_perusahaannya(): void
    {
        $p = $this->perusahaan();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'company_id' => $p->id]));

        $this->post(route('personalia.perusahaan.simpan'), [
            'name' => 'PT Tambang Uji Sejahtera', 'location' => 'Kalimantan Timur',
        ])->assertRedirect();

        $this->assertSame('PT Tambang Uji Sejahtera', $p->refresh()->name);
    }

    public function test_pengguna_biasa_tidak_dapat_menyunting_perusahaan(): void
    {
        $p = $this->perusahaan();
        $this->actingAs(User::factory()->create(['is_admin' => false, 'company_id' => $p->id]));

        $this->post(route('personalia.perusahaan.simpan'), ['name' => 'Diubah Diam-diam'])
             ->assertForbidden();

        $this->assertSame('PT Tambang Uji', $p->refresh()->name);
    }

    public function test_pic_perusahaan_boleh_merawat_datanya_sendiri(): void
    {
        // PIC mengurus datanya tanpa harus jadi administrator seluruh aplikasi.
        $p = $this->perusahaan(['pic_email' => 'pic@tambang.test']);
        $this->actingAs(User::factory()->create([
            'is_admin' => false, 'company_id' => $p->id, 'email' => 'pic@tambang.test',
        ]));

        $this->post(route('personalia.perusahaan.simpan'), ['name' => 'PT Tambang Uji Baru'])
             ->assertRedirect();

        $this->assertSame('PT Tambang Uji Baru', $p->refresh()->name);
    }

    public function test_halaman_perusahaan_terbuka_walau_akun_belum_tertaut(): void
    {
        // Akun tanpa perusahaan harus mendapat penjelasan, bukan galat.
        $this->actingAs(User::factory()->create(['company_id' => null]));

        $this->get(route('personalia.perusahaan'))
             ->assertOk()
             ->assertSee('belum terhubung ke perusahaan');
    }

    /* ---------- warna dari logo ---------- */

    public function test_warna_diambil_dari_logo_yang_diunggah(): void
    {
        Storage::fake('public');
        $p = $this->perusahaan();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'company_id' => $p->id]));

        $this->post(route('personalia.perusahaan.simpan'), [
            'name' => $p->name,
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]);

        $p->refresh();
        $this->assertNotNull($p->logo);
        Storage::disk('public')->assertExists($p->logo);
    }

    public function test_warna_khas_bukan_warna_terbanyak(): void
    {
        // Logo umumnya berlatar putih, jadi warna terbanyak justru latar
        // yang harus dibuang — bukan warna yang orang sebut sebagai
        // "warna logonya".
        $berkas = $this->logoPalsu(240, 240, [255, 255, 255], [200, 60, 40], 40);

        $w = WarnaLogo::dari($berkas);
        @unlink($berkas);

        $this->assertNotNull($w);
        [$r, $g, $b] = sscanf($w['terang'], '#%02x%02x%02x');
        $this->assertGreaterThan($g, $r, 'Warna khas seharusnya condong merah, bukan putih.');
        $this->assertGreaterThan($b, $r);
    }

    public function test_logo_tanpa_warna_tidak_memaksakan_warna(): void
    {
        // Logo hitam-putih tidak punya warna khas; memaksakan satu warna
        // dari abu-abu menghasilkan tebakan, bukan identitas.
        $berkas = $this->logoPalsu(120, 120, [255, 255, 255], [30, 30, 30], 30);

        $this->assertNull(WarnaLogo::dari($berkas));
        @unlink($berkas);
    }

    public function test_menghapus_logo_mengembalikan_warna_bawaan(): void
    {
        Storage::fake('public');
        $p = $this->perusahaan(['logo' => 'logo/x.png', 'theme_color' => '#B03A28', 'theme_dark' => '#3A1710']);
        $this->actingAs(User::factory()->create(['is_admin' => true, 'company_id' => $p->id]));

        $this->delete(route('personalia.logo.hapus'))->assertRedirect();

        $p->refresh();
        $this->assertNull($p->theme_color);
        $this->assertNull($p->logo);
    }

    /* ---------- tema ---------- */

    public function test_tema_perusahaan_dipakai_bila_ada_dan_bawaan_bila_tidak(): void
    {
        $p = $this->perusahaan(['theme_color' => '#B03A28', 'theme_dark' => '#3A1710']);
        $berwarna = User::factory()->create(['company_id' => $p->id]);
        $polos    = User::factory()->create(['company_id' => $this->perusahaan(['name' => 'PT Lain'])->id]);

        $this->assertSame('#B03A28', Tema::aksen($berwarna));
        $this->assertSame(Tema::BAWAAN_TERANG, Tema::aksen($polos));
        $this->assertSame(Tema::BAWAAN_TERANG, Tema::aksen(null));
    }

    public function test_warna_tidak_sah_tidak_pernah_masuk_ke_halaman(): void
    {
        // Nilai yang tersuntik ke atribut style adalah jalan masuk ke
        // halaman; hanya hex 6 digit yang boleh lewat.
        $p = $this->perusahaan(['theme_color' => 'red;} body{display:none']);
        $u = User::factory()->create(['company_id' => $p->id]);

        $this->assertSame(Tema::BAWAAN_TERANG, Tema::aksen($u));
        $this->assertStringNotContainsString('display:none', Tema::gaya($u));
    }

    public function test_pilihan_tema_tersimpan_di_akun(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);

        $this->post(route('personalia.tema'), ['tema' => 'gelap'])->assertRedirect();
        $this->assertSame('gelap', $u->refresh()->tema);

        // Nilai di luar dua pilihan itu ditolak, bukan disimpan apa adanya.
        $this->post(route('personalia.tema'), ['tema' => 'pelangi'])->assertSessionHasErrors('tema');
    }

    public function test_tema_pilihan_tertanam_pada_elemen_akar(): void
    {
        $this->actingAs(User::factory()->create(['tema' => 'gelap']));

        $this->get('/dashboard')->assertOk()->assertSee('data-tema="gelap"', false);
    }

    /* ---------- sapaan ---------- */

    public function test_sapaan_mengikuti_jam_setempat_bukan_jam_server(): void
    {
        // Pukul 18.00 WITA adalah 10.00 UTC. Menghitung dari jam server
        // membuat sore hari disapa "Selamat pagi" — salah delapan jam, di
        // bagian halaman yang paling pertama dibaca orang.
        config(['waktu.zona' => 'Asia/Makassar']);
        Carbon::setTestNow(Carbon::parse('2026-08-11 10:03:00', 'UTC'));

        $this->assertSame('Selamat sore', Waktu::sapaan());
        $this->assertSame(18, (int) Waktu::kini()->format('G'));

        Carbon::setTestNow();
    }

    public function test_sapaan_menyebut_bagian_hari_yang_benar(): void
    {
        config(['waktu.zona' => 'UTC']);

        foreach ([
            '06:00' => 'Selamat pagi',
            '12:00' => 'Selamat siang',
            '17:00' => 'Selamat sore',
            '21:00' => 'Selamat malam',
        ] as $jam => $harap) {
            Carbon::setTestNow(Carbon::parse("2026-08-11 {$jam}:00", 'UTC'));
            $this->assertSame($harap, Waktu::sapaan(), "Pukul {$jam} seharusnya '{$harap}'.");
        }

        Carbon::setTestNow();
    }

    /* ---------- halaman ---------- */

    public function test_seluruh_halaman_personalia_terbuka(): void
    {
        $p = $this->perusahaan();
        $this->actingAs(User::factory()->create(['company_id' => $p->id]));

        foreach (['personalia.index', 'personalia.perusahaan', 'personalia.direktori'] as $r) {
            $this->get(route($r))->assertOk();
        }
    }

    public function test_direktori_tidak_membocorkan_rekan_perusahaan_lain(): void
    {
        $a = $this->perusahaan(['name' => 'PT Satu']);
        $b = $this->perusahaan(['name' => 'PT Dua']);

        User::factory()->create(['company_id' => $b->id, 'name' => 'Orang Perusahaan Lain']);
        $this->actingAs(User::factory()->create(['company_id' => $a->id, 'is_admin' => false]));

        $this->get(route('personalia.direktori'))
             ->assertOk()
             ->assertDontSee('Orang Perusahaan Lain');
    }

    public function test_tamu_tidak_dapat_membuka_personalia(): void
    {
        $this->get(route('personalia.index'))->assertRedirect(route('login'));
    }

    /**
     * Membuat PNG sederhana: latar seluas kanvas, satu kotak berwarna di
     * tengah. Bentuk seperti inilah yang paling menyerupai logo sungguhan
     * — sedikit warna di atas latar yang mendominasi.
     */
    private function logoPalsu(int $w, int $h, array $latar, array $warna, int $kotak): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, ...$latar));

        $c = imagecolorallocate($img, ...$warna);
        $x = (int) (($w - $kotak) / 2);
        $y = (int) (($h - $kotak) / 2);
        imagefilledrectangle($img, $x, $y, $x + $kotak, $y + $kotak, $c);

        $berkas = tempnam(sys_get_temp_dir(), 'logo').'.png';
        imagepng($img, $berkas);
        imagedestroy($img);

        return $berkas;
    }
}
