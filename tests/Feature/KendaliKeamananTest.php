<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Keamanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kendali sesi dan tajuk keamanan.
 *
 * Bagian yang paling perlu dijaga di sini adalah PEMUTUSAN SESI. Ia
 * satu-satunya jalan di aplikasi ini yang, dengan satu pengenal, dapat
 * mencabut akses orang lain. Penyaring kepemilikannya karena itu diuji
 * dari sisi penyerang: bukan "apakah pemiliknya bisa", melainkan
 * "apakah orang lain tidak bisa".
 */
class KendaliKeamananTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Lingkungan uji memakai penyimpan sesi `array`, sedangkan seluruh
     * kendali di sini hanya berlaku bila sesinya tersimpan di basis
     * data — daftar perangkat memang tidak dapat dibuat dari penyimpan
     * yang tidak menyimpan apa pun. Disetel menyerupai produksi supaya
     * yang diuji adalah perilaku yang sebenarnya berjalan di sana.
     */
    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
    }

    private function sesiPalsu(User $u, string $id = 'sesi-uji'): string
    {
        DB::table('sessions')->insert([
            'id'            => $id,
            'user_id'       => $u->id,
            'ip_address'    => '10.1.2.3',
            'user_agent'    => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120 Safari/537.36',
            'payload'       => '',
            'last_activity' => now()->getTimestamp(),
        ]);

        return $id;
    }

    /* ═══════════ hak akses halaman ═══════════ */

    public function test_bukan_admin_tidak_dapat_membuka_kendali_keamanan(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.keamanan'))
            ->assertForbidden();
    }

    public function test_tamu_dialihkan_ke_halaman_masuk(): void
    {
        $this->get(route('admin.keamanan'))->assertRedirect(route('login'));
        $this->get(route('keamanan.perangkat'))->assertRedirect(route('login'));
    }

    public function test_admin_dapat_membuka_kendali_keamanan(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get(route('admin.keamanan'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Admin/Keamanan'));
    }

    /**
     * Perangkat saya TIDAK menuntut hak admin.
     *
     * Ini bukan kelonggaran yang terlewat, melainkan keputusan: yang
     * menduga sandinya bocor harus dapat memutus perangkat lain saat
     * itu juga. Menunggu izin berarti memberi penyusupnya sesi hidup
     * selama masa tunggu.
     */
    public function test_pengguna_biasa_dapat_membuka_perangkatnya_sendiri(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('keamanan.perangkat'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Akun/Perangkat'));
    }

    /**
     * Halaman perangkat tetap terbuka SESUDAH ada yang benar-benar masuk.
     *
     * Ini bukan pengulangan uji di atasnya. Uji itu memakai pengguna
     * pabrikan yang `masuk_terakhir_at`-nya masih kosong, sehingga
     * operator `?->` memotong rantainya sebelum kolom itu tersentuh.
     * Halamannya baru meledak pada pengguna yang PERNAH masuk — yaitu
     * setiap pengguna sungguhan, dan bukan satu pun pengguna uji.
     *
     * Terjadi sungguhan: tanpa penetapan tipe datetime pada kolom itu,
     * nilainya kembali sebagai teks biasa dan `diffForHumans()`
     * melempar. Seluruh berkas uji ini hijau; halamannya 500 di
     * peramban pada kunjungan pertama sesudah masuk.
     *
     * Nilainya sengaja DITULIS LANGSUNG ke basis data, bukan lewat
     * proses masuk. Masuk di dalam satu proses uji meninggalkan objek
     * Carbon pada model yang masih tersimpan di memori, jadi kolomnya
     * tidak pernah melewati basis data dan tetap berupa Carbon
     * meskipun penetapan tipenya dicabut — uji versi pertama justru
     * begitu, dan tetap hijau ketika penetapan tipenya sengaja
     * dibuang. Yang harus ditiru adalah permintaan BERIKUTNYA, ketika
     * penggunanya dimuat ulang dari basis data sebagai teks.
     */
    public function test_halaman_perangkat_terbuka_bagi_pengguna_yang_pernah_masuk(): void
    {
        $u = User::factory()->create(['email_verified_at' => now()]);

        DB::table('users')->where('id', $u->id)->update([
            'masuk_terakhir_at' => now()->subDay()->toDateTimeString(),
            'masuk_terakhir_ip' => '203.0.113.7',
        ]);

        $segar = User::find($u->id);

        $this->assertNotNull($segar->masuk_terakhir_at,
            'Uji ini tidak berarti apa-apa bila kolom masuk terakhirnya tetap kosong.');

        $this->actingAs($segar)->get(route('keamanan.perangkat'))->assertOk();
    }

    /* ═══════════ pemutusan sesi ═══════════ */

    /**
     * Orang lain TIDAK dapat memutus sesi saya.
     *
     * Diuji dari sisi penyerang. Tanpa penyaring kepemilikan, siapa pun
     * yang mengetahui — atau menebak — pengenal sesi dapat menendang
     * siapa pun keluar, dan pada aplikasi lapangan itu berarti dapat
     * menghentikan pekerjaan orang dari jauh.
     */
    public function test_pengguna_lain_tidak_dapat_memutus_sesi_saya(): void
    {
        $korban    = User::factory()->create();
        $penyerang = User::factory()->create();

        $id = $this->sesiPalsu($korban);

        $this->actingAs($penyerang)
            ->delete(route('keamanan.perangkat.putus'), ['id' => $id])
            ->assertSessionHasErrors('keamanan');

        // Sesi orang lain tidak boleh terputus oleh yang bukan pemiliknya.
        $this->assertDatabaseHas('sessions', ['id' => $id]);
    }

    public function test_pemilik_dapat_memutus_sesinya_sendiri(): void
    {
        $u  = User::factory()->create();
        $id = $this->sesiPalsu($u);

        $this->actingAs($u)
            ->delete(route('keamanan.perangkat.putus'), ['id' => $id])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => $id]);
    }

    /**
     * Penyaring kepemilikan juga hidup di lapisan bawah.
     *
     * Penyaring yang hanya ada di controller dilewati oleh jalan mana
     * pun yang tidak melalui controller itu — perintah artisan, tugas
     * terjadwal, controller berikutnya yang dibuat orang lain.
     */
    public function test_penyaring_kepemilikan_ada_di_lapisan_bawah(): void
    {
        $korban    = User::factory()->create();
        $penyerang = User::factory()->create();

        $id = $this->sesiPalsu($korban);

        $this->assertFalse(Keamanan::putusSesi($id, $penyerang));
        $this->assertDatabaseHas('sessions', ['id' => $id]);

        $this->assertTrue(Keamanan::putusSesi($id, $korban));
    }

    /**
     * "Putus perangkat lain" mempertahankan yang sedang dipakai.
     *
     * Memutus semuanya termasuk yang sekarang melempar orangnya ke
     * halaman masuk tepat ketika ia sedang menangani dugaan
     * pembobolan — dan langkah berikutnya yang harus ia lakukan,
     * mengganti sandi, menuntut dirinya tetap masuk.
     */
    public function test_putus_lain_menyisakan_sesi_yang_sedang_dipakai(): void
    {
        $u = User::factory()->create();

        $this->sesiPalsu($u, 'sesi-lain-1');
        $this->sesiPalsu($u, 'sesi-lain-2');

        $ini = 'sesi-sekarang';
        $this->sesiPalsu($u, $ini);

        $this->assertSame(2, Keamanan::putusSesiLain($u, $ini));

        $this->assertDatabaseHas('sessions', ['id' => $ini]);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-lain-1']);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-lain-2']);
    }

    public function test_putus_lain_tidak_menyentuh_sesi_pengguna_lain(): void
    {
        $saya = User::factory()->create();
        $lain = User::factory()->create();

        $this->sesiPalsu($saya, 'punya-saya');
        $this->sesiPalsu($lain, 'punya-orang-lain');

        Keamanan::putusSesiLain($saya, null);

        $this->assertDatabaseMissing('sessions', ['id' => 'punya-saya']);
        // Memutus perangkat sendiri tidak boleh menyentuh perangkat orang lain.
        $this->assertDatabaseHas('sessions', ['id' => 'punya-orang-lain']);
    }

    /**
     * Sesi yang sedang dipakai tidak dapat diputus dari halaman ini.
     *
     * Bukan larangan teknis melainkan larangan agar orangnya tidak
     * menekan tombol yang tampak seperti tindakan pengamanan padahal
     * hasilnya sekadar keluar — dengan perangkat asing tetap hidup.
     */
    public function test_perangkat_sendiri_tidak_diputus_lewat_tombol_putus(): void
    {
        $u = User::factory()->create();

        $this->actingAs($u)
            ->delete(route('keamanan.perangkat.putus'), ['id' => session()->getId()])
            ->assertSessionHasErrors('keamanan');
    }

    /**
     * Pembaruan pengenal sesi membuang baris yang lama.
     *
     * Diuji pada penyimpan sesinya langsung, bukan lewat dua
     * permintaan HTTP. Alasannya batasan klien uji, dan patut dicatat
     * supaya tidak ada yang mencoba "memperbaiki" uji ini menjadi
     * bentuk yang lebih alami: klien uji Laravel TIDAK membawa kuki
     * sesi dari satu permintaan ke permintaan berikutnya — tiap
     * `$this->get()` memulai sesi baru. Uji versi pertama menghitung
     * baris sebelum dan sesudah masuk, dan angkanya tetap bertambah
     * dengan perbaikan apa pun, sebab yang dihitungnya adalah dua sesi
     * yang memang tidak pernah saling berhubungan.
     *
     * Yang dijamin di sini karena itu satu hal saja, dan hal itu
     * benar: `regenerate(true)` menghapus barisnya. Bahwa controller
     * masuk memanggilnya dengan `true` dijaga oleh pembacaan kode,
     * bukan oleh uji ini.
     */
    public function test_pembaruan_pengenal_sesi_membuang_baris_lama(): void
    {
        $store = app('session.store');

        /* Pengenal harus 40 huruf-angka: Store::setId menolak bentuk
           lain diam-diam dan membangkitkan pengenal acak sebagai
           gantinya, sehingga uji ini memeriksa baris yang tidak pernah
           ditulis. */
        $lama = str_repeat('a', 40);

        $store->setId($lama);
        $store->start();
        $store->put('apa pun', 1);
        $store->save();

        $this->assertSame($lama, $store->getId(), 'Pengenalnya ditolak; uji ini tidak menguji apa pun.');
        $this->assertDatabaseHas('sessions', ['id' => $lama]);

        $store->regenerate(true);
        $store->save();

        $this->assertDatabaseMissing('sessions', ['id' => $lama]);
        $this->assertNotSame($lama, $store->getId());
    }

    /** Controller masuk memang meminta yang lama dibuang. */
    public function test_controller_masuk_membuang_sesi_lama(): void
    {
        $berkas = file_get_contents(
            app_path('Http/Controllers/Auth/AuthenticatedSessionController.php'),
        );

        $this->assertStringContainsString('regenerate(true)', $berkas,
            'Controller masuk kembali memakai regenerate() bawaan, yang meninggalkan '
            .'baris sesi tamu hidup sampai masa berlakunya habis.');
    }

    /* ═══════════ tajuk keamanan ═══════════ */

    public function test_tajuk_keamanan_terpasang_pada_tanggapan(): void
    {
        $r = $this->get('/');

        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $r->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $r->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        $this->assertStringContainsString('camera=()', $r->headers->get('Permissions-Policy'));
    }

    /**
     * HSTS mati secara bawaan.
     *
     * Meneruskan keputusan yang tertulis pada berkas nginx: pemasangan
     * ini pernah kehilangan blok 443-nya satu kali, dan HSTS membuat
     * situs yang kehilangan https tidak dapat dibuka sama sekali —
     * bukan sekadar tidak terenkripsi.
     */
    public function test_hsts_mati_secara_bawaan(): void
    {
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_hsts_hanya_pada_sambungan_https(): void
    {
        config(['keamanan.hsts' => true]);

        // Masih http: memasang tajuk yang diabaikan peramban hanya
        // membuat orang mengira perlindungannya sudah berjalan.
        $this->get('/')->assertHeaderMissing('Strict-Transport-Security');

        $r = $this->get('https://localhost/');
        $r->assertHeader('Strict-Transport-Security');
        $this->assertStringContainsString('max-age=', $r->headers->get('Strict-Transport-Security'));
    }

    /**
     * Halaman yang sudah masuk tidak boleh tersimpan di peramban.
     *
     * Pada komputer bersama di kantor site, tombol "kembali" sesudah
     * orang keluar menampilkan halaman terakhirnya dari simpanan
     * peramban — lengkap dengan datanya — tanpa menyentuh server.
     */
    public function test_halaman_terautentikasi_tidak_disimpan_peramban(): void
    {
        $r = $this->actingAs(User::factory()->create())->get(route('dashboard'));

        $this->assertStringContainsString('no-store', (string) $r->headers->get('Cache-Control'));
    }

    /* ═══════════ tindakan admin ═══════════ */

    public function test_admin_dapat_membersihkan_sesi_basi_tanpa_menyentuh_yang_aktif(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $u     = User::factory()->create();

        $this->sesiPalsu($u, 'masih-hidup');

        DB::table('sessions')->insert([
            'id' => 'sudah-mati', 'user_id' => $u->id, 'ip_address' => '10.0.0.9',
            'user_agent' => 'x', 'payload' => '',
            'last_activity' => now()->subDays(30)->getTimestamp(),
        ]);

        $this->actingAs($admin)->post(route('admin.keamanan.sesi.bersih'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'sudah-mati']);
        // Pembersih sesi basi tidak boleh memutus sesi yang sedang dipakai.
        $this->assertDatabaseHas('sessions', ['id' => 'masih-hidup']);
    }

    public function test_bukan_admin_tidak_dapat_menjalankan_tindakan_keamanan(): void
    {
        $u = User::factory()->create();

        $this->actingAs($u)->post(route('admin.keamanan.sesi.bersih'))->assertForbidden();
        $this->actingAs($u)->post(route('admin.keamanan.jejak.pangkas'))->assertForbidden();
        $this->actingAs($u)->delete(route('admin.keamanan.sesi.putus'), ['id' => 'x'])->assertForbidden();
    }
}
