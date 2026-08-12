<?php

namespace Tests\Feature;

use App\Http\Controllers\PersonaliaController as Personalia;
use App\Models\{Company, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Personalia (Inertia).
 *
 * Modul kedua yang dipindah ke Vue, dan yang pertama membawa unggahan
 * berkas. Yang dijaga di sini bukan tampilannya melainkan hal-hal yang
 * rusak tanpa suara: daftar medan yang dipakai layar harus sama dengan
 * yang diterima validasi, dan direktori tidak boleh membocorkan kontak
 * lintas perusahaan lewat prop meskipun tidak tergambar di layar.
 */
class PersonaliaInertiaTest extends TestCase
{
    use RefreshDatabase;

    private function perusahaan(array $x = []): Company
    {
        return Company::create(array_merge(['name' => 'PT Tambang Uji', 'code' => 'PTU'], $x));
    }

    private function admin(?Company $p = null): User
    {
        $u = User::factory()->create(['is_admin' => true, 'company_id' => $p?->id]);
        $this->actingAs($u);

        return $u;
    }

    /* ══════════════ dirender Inertia ══════════════ */

    public function test_ketiga_halaman_dirender_inertia(): void
    {
        $this->admin($this->perusahaan());

        foreach ([
            'personalia.index'      => 'Personalia/Profil',
            'personalia.perusahaan' => 'Personalia/Perusahaan',
            'personalia.direktori'  => 'Personalia/Direktori',
        ] as $rute => $komponen) {
            $this->get(route($rute))->assertOk()->assertInertia(
                fn (AssertableInertia $p) => $p->component($komponen)->has('judul')->has('subjudul')
            );
        }
    }

    /* ══════════════ data diri ══════════════ */

    /**
     * Medan yang digambar layar sama dengan yang diterima validasi.
     *
     * Daftar medan sempat tertulis dua kali — sekali di aturan validasi,
     * sekali di perulangan yang menggambar isian. Medan yang hanya ada di
     * layar akan diisi orang lalu dibuang diam-diam saat disimpan.
     */
    public function test_setiap_medan_yang_digambar_benar_benar_tersimpan(): void
    {
        $this->admin();

        $kirim = [];
        foreach ($this->props('personalia.index')['medan'] as $m) {
            $kirim[$m['nama']] = $m['tipe'] === 'email' ? 'uji@tambang.test' : "isi-{$m['nama']}";
        }

        $this->post(route('personalia.simpan'), $kirim)->assertRedirect()->assertSessionHasNoErrors();

        $isian = $this->props('personalia.index')['isian'];

        foreach ($kirim as $k => $v) {
            $this->assertSame($v, $isian[$k], "Medan {$k} digambar tapi tidak tersimpan.");
        }
    }

    public function test_isian_tidak_pernah_null(): void
    {
        // v-model pada null membuat input tak terkendali, dan nilainya
        // hilang begitu orang mengetik lalu menghapusnya kembali.
        $this->admin();

        foreach ($this->props('personalia.index')['isian'] as $k => $v) {
            $this->assertIsString($v, "isian.{$k} bukan string.");
        }
    }

    public function test_avatar_dikirim_sebagai_alamat_siap_pakai(): void
    {
        // Halaman Vue tidak punya helper asset(); jalur mentah akan
        // tergambar sebagai gambar rusak tanpa galat apa pun.
        Storage::fake('public');
        $this->admin();

        $this->assertNull($this->props('personalia.index')['avatar']);

        $this->post(route('personalia.simpan'), [
            'name'   => 'Uji Coba',
            'email'  => 'uji@tambang.test',
            'avatar' => UploadedFile::fake()->image('foto.jpg', 200, 200),
        ])->assertRedirect();

        $this->assertStringStartsWith('http', $this->props('personalia.index')['avatar']);
    }

    /* ══════════════ perusahaan ══════════════ */

    public function test_medan_perusahaan_yang_digambar_benar_benar_tersimpan(): void
    {
        $p = $this->perusahaan();
        $this->admin($p);

        $kirim = [];
        foreach ($this->props('personalia.perusahaan')['medan'] as $m) {
            $kirim[$m['nama']] = $m['nama'] === 'pic_email' ? 'pic@tambang.test' : "isi-{$m['nama']}";
        }

        $this->post(route('personalia.perusahaan.simpan'), $kirim)
             ->assertRedirect()->assertSessionHasNoErrors();

        $isian = $this->props('personalia.perusahaan')['isian'];

        foreach ($kirim as $k => $v) {
            $this->assertSame($v, $isian[$k], "Medan perusahaan {$k} digambar tapi tidak tersimpan.");
        }
    }

    /**
     * Tombol hapus logo hanya muncul untuk logo milik perusahaan ini.
     *
     * effectiveLogo() dapat memulangkan logo bawaan yang bukan miliknya.
     * Menawarkan tombol hapus untuk logo semacam itu menjanjikan sesuatu
     * yang tidak akan terjadi — logonya tetap di layar setelah diklik.
     */
    public function test_logo_bawaan_tidak_menawarkan_tombol_hapus(): void
    {
        Storage::fake('public');
        $p = $this->perusahaan();
        $this->admin($p);

        $awal = $this->props('personalia.perusahaan');
        $this->assertFalse($awal['logoSendiri']);

        $this->post(route('personalia.perusahaan.simpan'), [
            'name' => $p->name,
            'logo' => UploadedFile::fake()->image('logo.png', 120, 120),
        ])->assertRedirect();

        $this->assertTrue($this->props('personalia.perusahaan')['logoSendiri']);
    }

    public function test_bukan_pic_dan_bukan_admin_tidak_boleh_menyunting(): void
    {
        $p = $this->perusahaan(['pic_email' => 'pic@tambang.test']);
        $this->actingAs(User::factory()->create([
            'is_admin' => false, 'company_id' => $p->id, 'email' => 'bukanpic@tambang.test',
        ]));

        $this->get(route('personalia.perusahaan'))->assertOk()->assertInertia(
            fn (AssertableInertia $x) => $x->where('bisaSunting', false)
        );

        $this->post(route('personalia.perusahaan.simpan'), ['name' => 'Ganti'])->assertForbidden();
    }

    /* ══════════════ kendali administrator ══════════════ */

    /**
     * Identitas perusahaan hanya boleh disentuh administrator.
     *
     * Isian mati di layar bukan penjagaan — kiriman tetap bisa disusun
     * tangan. Yang diuji di sini kirimannya, bukan tampilannya.
     */
    public function test_medan_identitas_dibuang_dari_kiriman_bukan_admin(): void
    {
        $p = $this->perusahaan(['pic_email' => 'pic@tambang.test']);
        $this->actingAs(User::factory()->create([
            'is_admin' => false, 'company_id' => $p->id, 'email' => 'pic@tambang.test',
        ]));

        $kirim = ['ktt' => 'Boleh Diubah'];
        foreach (Personalia::medanAdmin() as $k) $kirim[$k] = 'DICOBA';

        $this->post(route('personalia.perusahaan.simpan'), $kirim)
             ->assertRedirect()->assertSessionHasNoErrors();

        $p->refresh();

        $this->assertSame('Boleh Diubah', $p->ktt);

        foreach (Personalia::medanAdmin() as $k) {
            $this->assertNotSame('DICOBA', $p->{$k}, "Medan {$k} seharusnya khusus administrator.");
        }
    }

    public function test_medan_khusus_admin_ditandai_pada_prop(): void
    {
        // Layar memakainya untuk mematikan isian. Kalau penandanya hilang,
        // PIC mengetik sesuatu yang lalu dibuang server tanpa keterangan.
        $this->admin($this->perusahaan());

        $khusus = [];
        foreach ($this->props('personalia.perusahaan')['medan'] as $m) {
            if ($m['khususAdmin']) $khusus[] = $m['nama'];
        }

        $this->assertSame(Personalia::medanAdmin(), $khusus);
        $this->assertNotEmpty($khusus);
    }

    /**
     * Perusahaan yang baru dibuat langsung menjadi yang dibuka.
     *
     * Kirimannya sengaja membawa alamat asal ?perusahaan= milik perusahaan
     * lama, persis seperti dari peramban. Tanpa itu ujinya lulus karena
     * alasan yang salah: back() memulangkan alamat kosong, dan kueri lama
     * yang mengalahkan pilihan barulah yang tidak pernah terjadi.
     */
    public function test_perusahaan_baru_langsung_menjadi_yang_dibuka(): void
    {
        $lama = $this->perusahaan(['name' => 'PT Lama']);
        $this->admin($lama);

        $this->from(route('personalia.perusahaan', ['perusahaan' => $lama->id]))
             ->post(route('personalia.perusahaan.tambah'), ['name' => 'PT Baru', 'code' => 'BRU'])
             ->assertRedirect();

        $this->assertDatabaseHas('companies', ['name' => 'PT Baru', 'code' => 'BRU']);
        $this->assertSame('PT Baru', $this->props('personalia.perusahaan')['nama'],
            'Perusahaan baru dibuat tapi layar tetap memperlihatkan yang lama.');
    }

    public function test_bukan_admin_tidak_dapat_menambah_perusahaan(): void
    {
        $p = $this->perusahaan(['pic_email' => 'pic@tambang.test']);
        $this->actingAs(User::factory()->create([
            'is_admin' => false, 'company_id' => $p->id, 'email' => 'pic@tambang.test',
        ]));

        $this->post(route('personalia.perusahaan.tambah'), ['name' => 'PT Selundupan'])
             ->assertForbidden();

        $this->assertDatabaseMissing('companies', ['name' => 'PT Selundupan']);
    }

    public function test_admin_menyunting_perusahaan_yang_sedang_dibuka(): void
    {
        $a = $this->perusahaan(['name' => 'PT Satu']);
        $b = $this->perusahaan(['name' => 'PT Dua']);

        $this->admin($a);

        $this->assertSame('PT Dua', $this->props('personalia.perusahaan', ['perusahaan' => $b->id])['nama']);

        // Yang tersimpan harus perusahaan yang sedang dibuka, bukan
        // perusahaan tempat akun administratornya sendiri bernaung.
        $this->post(route('personalia.perusahaan.simpan'), ['name' => 'PT Dua', 'ktt' => 'KTT Dua'])
             ->assertRedirect();

        $this->assertSame('KTT Dua', $b->refresh()->ktt);
        $this->assertNull($a->refresh()->ktt);
    }

    public function test_pemakai_biasa_tidak_dapat_membuka_perusahaan_lain_lewat_kueri(): void
    {
        // Diabaikan, bukan ditolak: tautan yang dibagikan admin tidak boleh
        // menjatuhkan orang lain ke halaman galat.
        $milik = $this->perusahaan(['name' => 'PT Milik']);
        $lain  = $this->perusahaan(['name' => 'PT Lain']);

        $this->actingAs(User::factory()->create(['is_admin' => false, 'company_id' => $milik->id]));

        $p = $this->props('personalia.perusahaan', ['perusahaan' => $lain->id]);

        $this->assertSame('PT Milik', $p['nama']);
        $this->assertSame([], $p['daftar'], 'Daftar perusahaan tidak boleh terkirim ke pemakai biasa.');
    }

    public function test_admin_dapat_menetapkan_perusahaan_seorang_pengguna(): void
    {
        $p = $this->perusahaan();
        $this->admin();

        $orang = User::factory()->create(['company_id' => null]);

        $this->post(route('personalia.direktori.perusahaan', $orang), ['company_id' => $p->id])
             ->assertRedirect();

        $this->assertSame($p->id, $orang->refresh()->company_id);

        // Dan dapat melepasnya kembali.
        $this->post(route('personalia.direktori.perusahaan', $orang), ['company_id' => null])
             ->assertRedirect();

        $this->assertNull($orang->refresh()->company_id);
    }

    public function test_bukan_admin_tidak_dapat_memindahkan_siapa_pun(): void
    {
        // Perusahaan menentukan data siapa yang boleh dilihat seseorang.
        // Kalau pemakai bisa memindahkan dirinya, batas itu tidak ada.
        $a = $this->perusahaan(['name' => 'PT Satu']);
        $b = $this->perusahaan(['name' => 'PT Dua']);

        $orang = User::factory()->create(['is_admin' => false, 'company_id' => $a->id]);
        $this->actingAs($orang);

        $this->post(route('personalia.direktori.perusahaan', $orang), ['company_id' => $b->id])
             ->assertForbidden();

        $this->assertSame($a->id, $orang->refresh()->company_id);
    }

    public function test_daftar_perusahaan_tidak_terkirim_ke_direktori_pemakai_biasa(): void
    {
        $p = $this->perusahaan();
        $this->actingAs(User::factory()->create(['is_admin' => false, 'company_id' => $p->id]));

        $props = $this->props('personalia.direktori');

        $this->assertFalse($props['admin']);
        $this->assertSame([], $props['daftar']);
    }

    /* ══════════════ direktori ══════════════ */

    public function test_pencarian_dikerjakan_server_bukan_disaring_di_peramban(): void
    {
        // Hanya satu halaman yang dimuat, jadi menyaring di peramban hanya
        // menyaring sisa yang kebetulan ikut terbawa.
        $p = $this->perusahaan();
        $this->admin($p);

        User::factory()->create(['company_id' => $p->id, 'name' => 'Budi Hartono']);
        User::factory()->create(['company_id' => $p->id, 'name' => 'Siti Rahayu']);

        $nama = array_column($this->props('personalia.direktori', ['cari' => 'Budi'])['orang'], 'nama');

        $this->assertContains('Budi Hartono', $nama);
        $this->assertNotContains('Siti Rahayu', $nama);
    }

    public function test_pencarian_juga_menjangkau_jabatan_dan_departemen(): void
    {
        $p = $this->perusahaan();
        $this->admin($p);

        User::factory()->create(['company_id' => $p->id, 'name' => 'Andi', 'position' => 'Safety Officer']);
        User::factory()->create(['company_id' => $p->id, 'name' => 'Rina', 'department' => 'Produksi']);

        foreach (['Safety' => 'Andi', 'Produksi' => 'Rina'] as $kata => $harap) {
            $nama = array_column($this->props('personalia.direktori', ['cari' => $kata])['orang'], 'nama');

            $this->assertContains($harap, $nama, "Pencarian '{$kata}' tidak menemukan {$harap}.");
        }
    }

    public function test_nama_perusahaan_hanya_terkirim_kepada_admin(): void
    {
        // Bagi pemakai biasa seluruh isi direktori satu perusahaan, jadi
        // kolomnya tidak berarti apa-apa — dan tetap terkirim berarti
        // data yang tidak dipakai ikut melintas ke peramban.
        $p = $this->perusahaan();

        User::factory()->create(['company_id' => $p->id, 'name' => 'Rekan Satu']);

        $this->actingAs(User::factory()->create(['is_admin' => false, 'company_id' => $p->id]));

        foreach ($this->props('personalia.direktori')['orang'] as $o) {
            $this->assertNull($o['perusahaan']);
        }

        $this->admin($p);

        $ada = array_filter(array_column($this->props('personalia.direktori')['orang'], 'perusahaan'));
        $this->assertNotEmpty($ada);
    }

    public function test_tautan_halaman_membawa_alamat_dan_penanda_aktif(): void
    {
        $p = $this->perusahaan();
        $this->admin($p);

        User::factory()->count(30)->create(['company_id' => $p->id]);

        $h = $this->props('personalia.direktori')['halaman'];

        $this->assertGreaterThan(1, $h['akhir']);
        $this->assertCount(24, $this->props('personalia.direktori')['orang']);

        $aktif = array_filter($h['tautan'], fn ($t) => $t['aktif']);
        $this->assertCount(1, $aktif, 'Penanda halaman aktif harus tepat satu.');
    }

    public function test_pencarian_ikut_terbawa_saat_berpindah_halaman(): void
    {
        // Tanpa withQueryString(), halaman kedua memulangkan seluruh orang
        // dan hasil pencariannya lenyap tanpa penjelasan.
        $p = $this->perusahaan();
        $this->admin($p);

        User::factory()->count(30)->create(['company_id' => $p->id, 'department' => 'Produksi']);

        $h = $this->props('personalia.direktori', ['cari' => 'Produksi'])['halaman'];

        foreach ($h['tautan'] as $t) {
            if ($t['url']) $this->assertStringContainsString('cari=Produksi', $t['url']);
        }
    }

    /* ══════════════ daftar medan ══════════════ */

    public function test_daftar_medan_berasal_dari_satu_sumber(): void
    {
        $this->admin($this->perusahaan());

        $this->assertSame(
            array_column(Personalia::MEDAN, 0),
            array_column($this->props('personalia.index')['medan'], 'nama')
        );

        $this->assertSame(
            array_column(Personalia::MEDAN_PERUSAHAAN, 0),
            array_column($this->props('personalia.perusahaan')['medan'], 'nama')
        );
    }

    private function props(string $rute, array $kueri = []): array
    {
        return $this->get(route($rute, $kueri))->assertOk()->viewData('page')['props'];
    }
}
