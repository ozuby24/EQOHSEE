<?php

namespace Tests\Feature;

use App\Models\{ActivityLog, User};
use App\Support\Keamanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Jejak akses.
 *
 * Yang dijaga di sini dua hal yang saling bertentangan, dan justru
 * karena bertentangan keduanya perlu diuji bersama:
 *
 *   1. Setiap percobaan masuk HARUS tercatat, termasuk yang gagal —
 *      terutama yang gagal, sebab serangan penebakan sandi hampir
 *      seluruhnya berupa kegagalan.
 *   2. Sandinya TIDAK BOLEH ikut tercatat, juga sandi yang salah.
 *      Orang memakai sandi yang mirip di banyak tempat, dan sandi
 *      salah di sini kerap merupakan sandi benar di tempat lain.
 *
 * Pencatat yang lalai melanggar yang pertama tanpa gejala apa pun:
 * tabelnya tetap ada, halamannya tetap terbuka, hanya kosong — dan
 * jejak kosong terlihat persis seperti keadaan aman.
 */
class JejakAksesTest extends TestCase
{
    use RefreshDatabase;

    private function jejak(): \Illuminate\Support\Collection
    {
        return ActivityLog::where('module', Keamanan::MODUL)->get();
    }

    /* ── pencatatan ── */

    public function test_masuk_berhasil_tercatat_dengan_alamat(): void
    {
        $u = User::factory()->create(['password' => bcrypt('sandi-benar-123')]);

        $this->post(route('login'), ['email' => $u->email, 'password' => 'sandi-benar-123'])
            ->assertRedirect();

        $b = $this->jejak()->firstWhere('action', Keamanan::MASUK);

        $this->assertNotNull($b, 'Masuk yang berhasil tidak tercatat sama sekali.');
        $this->assertSame($u->id, $b->user_id);
        $this->assertNotNull($b->ip, 'Jejak tanpa alamat tidak menjawab "dari mana".');
    }

    public function test_masuk_gagal_tercatat_beserta_surel_yang_dicoba(): void
    {
        $u = User::factory()->create(['password' => bcrypt('sandi-benar-123')]);

        $this->post(route('login'), ['email' => $u->email, 'password' => 'tebakan-salah']);

        $b = $this->jejak()->firstWhere('action', Keamanan::MASUK_GAGAL);

        $this->assertNotNull($b, 'Percobaan yang gagal tidak tercatat — justru ini yang paling perlu.');
        $this->assertSame($u->email, $b->detail);
    }

    /**
     * Surel yang tidak terdaftar pun dicatat.
     *
     * Inilah bentuk pemindaian: satu alamat mencoba banyak surel yang
     * tidak satu pun ada. Bila hanya surel terdaftar yang dicatat,
     * pemindaian menjadi tidak terlihat sama sekali.
     */
    public function test_surel_tak_terdaftar_tetap_tercatat(): void
    {
        $this->post(route('login'), ['email' => 'bukan-siapa@contoh.test', 'password' => 'apa-saja']);

        $b = $this->jejak()->firstWhere('action', Keamanan::MASUK_GAGAL);

        $this->assertNotNull($b);
        $this->assertSame('bukan-siapa@contoh.test', $b->detail);
        $this->assertNull($b->user_id);
    }

    public function test_keluar_tercatat(): void
    {
        $u = User::factory()->create();

        $this->actingAs($u)->post(route('logout'));

        $this->assertNotNull($this->jejak()->firstWhere('action', Keamanan::KELUAR));
    }

    /**
     * Penguncian tercatat terpisah dari kegagalan biasa.
     *
     * Terkunci berarti ambangnya sudah tersentuh — peristiwa yang
     * berbeda derajatnya dari satu kali salah ketik, dan yang harus
     * dapat dihitung sendiri.
     */
    public function test_penguncian_tercatat(): void
    {
        $u = User::factory()->create(['password' => bcrypt('sandi-benar-123')]);

        for ($i = 0; $i < 6; $i++) {
            $this->post(route('login'), ['email' => $u->email, 'password' => 'salah']);
        }

        $this->assertNotNull(
            $this->jejak()->firstWhere('action', Keamanan::TERKUNCI),
            'Penguncian tidak tercatat; tekanan tidak akan terlihat sebagai tekanan.',
        );
    }

    /* ── yang tidak boleh ikut ── */

    /**
     * Sandi tidak pernah masuk jejak — juga sandi yang salah.
     *
     * Diperiksa pada SELURUH baris, bukan hanya kolom detail. Kebocoran
     * seperti ini biasanya bukan lewat kolom yang memang dimaksudkan
     * untuk itu, melainkan lewat kolom yang tidak diperhatikan siapa
     * pun.
     */
    public function test_sandi_tidak_pernah_tercatat(): void
    {
        $u = User::factory()->create(['password' => bcrypt('sandi-benar-123')]);

        /* Yang gagal LEBIH DULU, dan yang berhasil sesudahnya.
           Urutan ini bukan gaya penulisan: rute masuk berada di balik
           middleware `guest`, jadi begitu ada yang berhasil, permintaan
           masuk berikutnya dari sesi uji yang sama dipantulkan sebelum
           mencapai controller — tidak ada Auth::attempt, tidak ada
           peristiwa Failed, dan pemeriksaan sandinya menjadi hampa
           sambil tetap berwarna hijau. Versi pertama uji ini persis
           begitu, dan baru ketahuan ketika kebocoran yang sengaja
           dipasang tidak membuatnya merah. */
        $this->post(route('login'), ['email' => $u->email, 'password' => 'RahasiaSalah#9']);

        $this->assertFalse(Auth::check(),
            'Percobaan yang salah justru berhasil masuk; uji ini tidak menguji apa pun.');
        $this->assertNotNull(
            $this->jejak()->firstWhere('action', Keamanan::MASUK_GAGAL),
            'Tidak ada peristiwa gagal yang tercatat — tidak ada apa pun untuk diperiksa kebocorannya.',
        );

        $this->post(route('login'), ['email' => $u->email, 'password' => 'sandi-benar-123']);
        $this->assertAuthenticated();

        $semua = ActivityLog::all()->toJson();

        $this->assertStringNotContainsString('RahasiaSalah#9', $semua,
            'Sandi yang salah ikut tercatat. Orang memakai sandi yang mirip di banyak tempat.');
        $this->assertStringNotContainsString('sandi-benar-123', $semua,
            'Sandi yang benar ikut tercatat.');
    }

    /* ── masuk terakhir ── */

    public function test_masuk_terakhir_terisi_tanpa_menaikkan_updated_at(): void
    {
        $u = User::factory()->create(['password' => bcrypt('sandi-benar-123')]);

        $updatedSemula = $u->fresh()->updated_at;

        $this->travel(2)->minutes();
        $this->post(route('login'), ['email' => $u->email, 'password' => 'sandi-benar-123']);

        $u->refresh();

        $this->assertNotNull($u->masuk_terakhir_at, 'Akun tidur tidak akan pernah terlihat.');
        $this->assertNotNull($u->masuk_terakhir_ip);

        /* updated_at menandai perubahan DATA pengguna. Masuk bukan
           perubahan data pengguna, dan menaikkannya tiap kali orang
           masuk membuat "terakhir diubah" pada daftar pengguna
           berhenti berarti apa pun. */
        $this->assertEquals($updatedSemula, $u->updated_at,
            'Masuk menaikkan updated_at; kolom itu berhenti menandai perubahan data.');
    }

    /* ── atribusi jejak modul lain ── */

    /**
     * ActivityLog::write mengisi alamat sendiri.
     *
     * Ada lebih dari seratus pemanggil di seluruh modul. Bila
     * pengisiannya dibebankan kepada pemanggil, separuh jejaknya akan
     * beralamat dan separuh lagi tidak — persis keadaan yang hendak
     * diperbaiki, tetapi kini terlihat seolah sudah diperbaiki.
     */
    public function test_write_mengisi_alamat_dan_perusahaan_sendiri(): void
    {
        $u = User::factory()->create();

        $this->actingAs($u)->get(route('dashboard'));

        ActivityLog::write('Uji', 'sesuatu', 'lms');

        $b = ActivityLog::where('action', 'Uji')->first();

        $this->assertNotNull($b->ip);
        $this->assertSame($u->company_id, $b->company_id);
    }

    /* ── tekanan ── */

    public function test_tekanan_dihitung_dari_kegagalan_bukan_keberhasilan(): void
    {
        $u = User::factory()->create(['password' => bcrypt('sandi-benar-123')]);

        $this->post(route('login'), ['email' => $u->email, 'password' => 'sandi-benar-123']);
        Auth::logout();

        $this->assertSame(0, Keamanan::gagalSejak(1),
            'Masuk yang BERHASIL ikut terhitung sebagai tekanan.');

        $this->post(route('login'), ['email' => $u->email, 'password' => 'salah']);

        $this->assertSame(1, Keamanan::gagalSejak(1));
    }

    public function test_keadaan_tekanan_mengikuti_ambang(): void
    {
        $this->assertSame('aman',      Keamanan::keadaanTekanan(0));
        $this->assertSame('aman',      Keamanan::keadaanTekanan(Keamanan::ambangPerhatian() - 1));
        $this->assertSame('perhatian', Keamanan::keadaanTekanan(Keamanan::ambangPerhatian()));
        $this->assertSame('gawat',     Keamanan::keadaanTekanan(Keamanan::ambangGawat()));
    }

    /**
     * Alamat yang menekan dibedakan: banyak sasaran vs satu sasaran.
     *
     * Keduanya menuntut jawaban yang berbeda — pemindaian dijawab
     * dengan pemblokiran alamat, penebakan satu akun dijawab dengan
     * menghubungi pemilik akunnya — jadi angka pembedanya harus benar.
     */
    public function test_alamat_menekan_menghitung_sasaran_berbeda(): void
    {
        foreach (['a@contoh.test', 'b@contoh.test', 'c@contoh.test'] as $surel) {
            $this->post(route('login'), ['email' => $surel, 'password' => 'x']);
        }

        $menekan = Keamanan::alamatMenekan(24);

        $this->assertNotEmpty($menekan);
        $this->assertSame(3, $menekan[0]['jumlah']);
        $this->assertSame(3, $menekan[0]['sasaran']);
    }

    /* ── pemangkasan ── */

    public function test_pangkas_hanya_menyentuh_jejak_keamanan_yang_lama(): void
    {
        $lamaKeamanan = ActivityLog::create([
            'module' => Keamanan::MODUL, 'action' => Keamanan::MASUK_GAGAL,
        ]);
        $lamaKeamanan->forceFill(['created_at' => now()->subDays(200)])->saveQuietly();

        $lamaModulLain = ActivityLog::create(['module' => 'lms', 'action' => 'Sesuatu']);
        $lamaModulLain->forceFill(['created_at' => now()->subDays(200)])->saveQuietly();

        $baru = ActivityLog::create([
            'module' => Keamanan::MODUL, 'action' => Keamanan::MASUK,
        ]);

        $this->assertSame(1, Keamanan::pangkasJejak(90));

        $this->assertNull(ActivityLog::find($lamaKeamanan->id));
        $this->assertNotNull(ActivityLog::find($baru->id));

        /* Riwayat persetujuan dokumen masih ditanyakan bertahun
           kemudian; memangkasnya di sini adalah keputusan yang bukan
           urusan pemangkas jejak keamanan. */
        $this->assertNotNull(ActivityLog::find($lamaModulLain->id),
            'Pemangkas keamanan ikut membuang jejak modul lain.');
    }
}
