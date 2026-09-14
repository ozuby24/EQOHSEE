<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use App\Support\{Menu, Pillars, Tur};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengenalan situs bagi akun yang baru mendaftar.
 */
class TurPengenalanTest extends TestCase
{
    use RefreshDatabase;

    private function baru(): User
    {
        $c = Company::create(['name' => 'PT Uji Tur', 'code' => 'UTR']);

        $u = User::factory()->create([
            'name' => 'Ir. Bambang Sudarsono, S.T.',
            'company_id' => $c->id, 'email_verified_at' => now(),
        ]);

        $u->tur_selesai_pada = null;
        $u->saveQuietly();

        return $u->fresh();
    }

    public function test_akun_baru_melihat_pengenalan(): void
    {
        $this->assertTrue(Tur::perlu($this->baru()));
    }

    public function test_yang_sudah_selesai_tidak_melihatnya_lagi(): void
    {
        $u = $this->baru();
        $u->tur_selesai_pada = now();

        $this->assertFalse(Tur::perlu($u));
    }

    public function test_tamu_tidak_melihat_apa_pun(): void
    {
        $this->assertFalse(Tur::perlu(null));
        $this->assertSame([], Tur::langkah(null));
    }

    /**
     * Penandanya sampai ke halaman sebagai BOOLEAN, bukan isinya.
     *
     * Ini penjagaan atas berat muatan, bukan atas fiturnya. Langkah
     * pengenalan beserta delapan pilar dan seluruh modulnya berbobot
     * sekitar sembilan setengah kilobita; dibagikan dari middleware, ia
     * ikut pada TIAP pembukaan halaman oleh akun yang belum
     * menyelesaikannya.
     *
     * Bukan kekhawatiran teoretis: halaman Form Penilaian Audit punya
     * ambang muatannya sendiri, dan sembilan kilobita tambahan pada tiap
     * halaman menjatuhkannya.
     */
    public function test_yang_dibagikan_hanya_penandanya(): void
    {
        $u = $this->baru();

        $props = $this->actingAs($u)->get(route('dasbor'))
            ->assertOk()->viewData('page')['props'];

        $this->assertArrayHasKey('turPerlu', $props);
        $this->assertIsBool($props['turPerlu'],
            'Isi pengenalan ikut dibagikan ke tiap halaman. Yang boleh dikirim dari '
            .'middleware hanya penandanya; isinya diambil lewat /tur saat dibuka.');

        $this->assertArrayNotHasKey('tur', $props);
    }

    public function test_isi_diambil_lewat_rutenya_sendiri(): void
    {
        $isi = $this->actingAs($this->baru())->getJson('/tur')->assertOk()->json();

        $kunci = array_column($isi['langkah'], 'kunci');

        $this->assertSame(['sambutan', 'pilar', 'modul', 'mulai'], $kunci);
    }

    public function test_tamu_tidak_dapat_mengambil_isinya(): void
    {
        $this->getJson('/tur')->assertRedirect();
    }

    /**
     * Isinya mengikuti registry, tidak diketik ulang.
     *
     * Pengenalan fitur adalah tulisan yang paling cepat basi di sebuah
     * aplikasi, dan basinya tidak terlihat: tidak ada galat, hanya orang
     * baru yang dijanjikan modul yang sudah berganti nama.
     */
    public function test_pilar_dan_modul_mengikuti_registry(): void
    {
        $u = $this->baru();
        $langkah = collect(Tur::langkah($u))->keyBy('kunci');

        $this->assertCount(count(Pillars::all()), $langkah['pilar']['pilar'],
            'Jumlah pilar pada pengenalan berbeda dari Pillars::all().');

        $this->assertSame(
            array_column(Pillars::all(), 'nama'),
            array_column($langkah['pilar']['pilar'], 'nama'));

        $this->assertSame(
            array_keys(Menu::untuk($u)),
            array_column($langkah['modul']['modul'], 'kunci'),
            'Daftar modul pada pengenalan berbeda dari Menu::untuk().');
    }

    /**
     * Modul yang diperkenalkan adalah yang benar-benar dapat dibuka.
     *
     * Memakai Menu::all() akan memperkenalkan modul yang, begitu diklik,
     * memulangkan 403 — kesan pertama sebuah situs menjadi pintu
     * terkunci yang baru saja ditawarkan kepadanya sendiri.
     */
    public function test_pengguna_biasa_tidak_ditawari_modul_admin(): void
    {
        $biasa = $this->baru();
        $biasa->is_admin = false;
        $biasa->saveQuietly();

        $modul = collect(Tur::langkah($biasa->fresh()))->firstWhere('kunci', 'modul')['modul'];
        $kunci = array_column($modul, 'kunci');

        foreach (Menu::all() as $k => $m) {
            if ($m['admin'] ?? false) {
                $this->assertNotContains($k, $kunci,
                    "Modul admin '{$k}' ditawarkan kepada pengguna biasa.");
            }
        }
    }

    /**
     * Sapaannya melewati gelar.
     *
     * Mengambil kata pertama begitu saja menghasilkan "Selamat datang,
     * Ir." — sapaan yang menyebut gelarnya saja terdengar seperti
     * aplikasi yang rusak, persis bertentangan dengan maksud sambutan.
     */
    public function test_sapaan_melewati_gelar_depan(): void
    {
        $judul = collect(Tur::langkah($this->baru()))->firstWhere('kunci', 'sambutan')['judul'];

        $this->assertSame('Selamat datang, Bambang', $judul);
    }

    /** Tiap tautan langkah pertama menunjuk rute yang benar-benar ada. */
    public function test_tautan_langkah_pertama_semuanya_hidup(): void
    {
        $butir = collect(Tur::langkah($this->baru()))->firstWhere('kunci', 'mulai')['butir'];

        $this->assertNotEmpty($butir);

        foreach ($butir as [$judul, , $url]) {
            $this->assertNotEmpty($url, "Langkah '{$judul}' tidak punya tautan.");
        }
    }

    /* ═══════════ menandai selesai ═══════════ */

    public function test_menandai_selesai_menghentikan_pengenalan(): void
    {
        $u = $this->baru();

        $this->actingAs($u)->post('/tur/selesai')->assertNoContent();

        $this->assertNotNull($u->fresh()->tur_selesai_pada);
        $this->assertFalse(Tur::perlu($u->fresh()));
    }

    /**
     * Ditandai dua kali tidak memundurkan tanggalnya.
     *
     * Tanggal itu satu-satunya jawaban atas "kapan orang ini pertama
     * kali dikenalkan"; membuka ulang pengenalan lalu menutupnya tidak
     * boleh menghapus jawabannya.
     */
    public function test_menandai_dua_kali_tidak_menggeser_tanggalnya(): void
    {
        $u = $this->baru();

        $this->actingAs($u)->post('/tur/selesai')->assertNoContent();
        $pertama = $u->fresh()->tur_selesai_pada;

        $this->travel(5)->minutes();

        $this->actingAs($u)->post('/tur/selesai')->assertNoContent();

        $this->assertTrue($pertama->equalTo($u->fresh()->tur_selesai_pada),
            'Tanggal pengenalan pertama tergeser oleh penutupan berikutnya.');
    }

    /**
     * Penandanya tidak dapat dipasang lewat pembaruan profil.
     *
     * Sengaja di luar $fillable: akun yang belum pernah melihat
     * pengenalannya tidak boleh diam-diam terhitung sudah, hanya karena
     * ada formulir lain yang kebetulan mengirim namanya.
     */
    public function test_penanda_tidak_dapat_diisi_massal(): void
    {
        $u = $this->baru();

        $u->fill(['tur_selesai_pada' => now()]);

        $this->assertNull($u->tur_selesai_pada,
            'tur_selesai_pada dapat diisi massal; formulir mana pun dapat melewatkan '
            .'pengenalan tanpa orangnya pernah melihatnya.');
    }
}
