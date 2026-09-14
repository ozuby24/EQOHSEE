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
     * Akun baru membawa langkahnya bersama halaman.
     *
     * Inilah yang membuat sambutan otomatis tidak dapat gagal dimuat.
     * Semula yang dikirim hanya sebuah boolean dan isinya diambil lewat
     * fetch('/tur'); begitu permintaan itu gagal di produksi, yang
     * dilihat pengguna barunya adalah kotak "Pengenalan gagal dimuat"
     * yang muncul LAGI setiap kali halaman disegarkan.
     *
     * Bahwa muatan ini tidak ikut pada pengguna lain dijaga uji
     * berikutnya.
     */
    public function test_akun_baru_membawa_langkahnya_bersama_halaman(): void
    {
        $u = $this->baru();

        $props = $this->actingAs($u)->get(route('dasbor'))
            ->assertOk()->viewData('page')['props'];

        $this->assertArrayHasKey('tur', $props);
        $this->assertIsArray($props['tur'],
            'Akun baru tidak membawa langkah pengenalannya. Bila isinya harus '
            .'diambil lewat jaringan, satu permintaan yang gagal mengubah sambutan '
            .'menjadi kotak galat yang muncul lagi pada tiap penyegaran halaman.');
    }

    /**
     * Yang sudah selesai tidak membayar apa pun.
     *
     * Langkahnya berbobot sekitar sembilan setengah kilobita. Terkirim
     * kepada semua orang pada tiap pembukaan halaman, ia menjadi ongkos
     * tetap untuk sesuatu yang dibaca sekali seumur akun — dan halaman
     * Form Penilaian Audit punya ambang muatannya sendiri yang akan
     * jatuh karenanya.
     */
    public function test_yang_sudah_selesai_tidak_membawa_muatannya(): void
    {
        $u = $this->baru();
        $u->tur_selesai_pada = now();
        $u->saveQuietly();

        $props = $this->actingAs($u->fresh())->get(route('dasbor'))
            ->assertOk()->viewData('page')['props'];

        $this->assertNull($props['tur'],
            'Langkah pengenalan ikut terkirim kepada pengguna yang sudah '
            .'menyelesaikannya — ongkos tetap untuk sesuatu yang tidak akan dibuka.');
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

    /**
     * Administrator tidak disuruh menghubungi administrator.
     *
     * Administrator lintas perusahaan sengaja tidak terikat perusahaan
     * mana pun. Dengan hanya dua cabang, ia jatuh ke cabang "belum
     * terikat" dan kalimat pertama yang dibacanya adalah nasihat untuk
     * menghubungi dirinya sendiri, tentang keadaan yang memang
     * dikehendaki baginya.
     */
    public function test_sambutan_administrator_tidak_menyuruhnya_menghubungi_administrator(): void
    {
        $a = $this->baru();
        $a->is_admin   = true;
        $a->company_id = null;
        $a->saveQuietly();

        $teks = collect(Tur::langkah($a->fresh()))->firstWhere('kunci', 'sambutan')['teks'];

        $this->assertStringNotContainsString('Hubungi administrator', $teks,
            'Administrator disuruh menghubungi administrator tentang keadaan '
            .'yang justru disengaja baginya.');

        $this->assertStringContainsString('Semua perusahaan', $teks,
            'Sambutan administrator tidak menyebut pemilih perusahaan — '
            .'satu-satunya hal yang menjelaskan mengapa akunnya tanpa perusahaan.');
    }

    /** Pengguna biasa tanpa perusahaan TETAP diberi nasihat yang benar. */
    public function test_pengguna_biasa_tanpa_perusahaan_tetap_diarahkan(): void
    {
        $b = $this->baru();
        $b->is_admin   = false;
        $b->company_id = null;
        $b->saveQuietly();

        $teks = collect(Tur::langkah($b->fresh()))->firstWhere('kunci', 'sambutan')['teks'];

        $this->assertStringContainsString('Hubungi administrator', $teks);
    }

    /** Yang punya perusahaan disebutkan nama perusahaannya. */
    public function test_sambutan_menyebut_nama_perusahaannya(): void
    {
        $teks = collect(Tur::langkah($this->baru()))->firstWhere('kunci', 'sambutan')['teks'];

        $this->assertStringContainsString('PT Uji Tur', $teks);
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
