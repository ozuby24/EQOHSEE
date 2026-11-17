<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `demo:pasang --hapus` — membersihkan pratinjau sampai habis.
 *
 * `--kosongkan` hanya mengosongkan isinya; perusahaannya dan akun-akunnya
 * tetap tinggal. Itu memang yang dimaui saat menyiapkan ulang pratinjau,
 * tetapi bukan saat membersihkan setelahnya — terutama pada pemasangan
 * yang dipakai sungguhan, tempat perusahaan karangan menumpuk di pemilih
 * perusahaan dan akun berkata sandi contoh yang seragam dan lemah tetap
 * dapat masuk.
 *
 * Terukur sebelum ini dijaga: menghapus dua perusahaan contoh
 * meninggalkan DUA BELAS akun pekerja Miners tanpa perusahaan,
 * seluruhnya masih aktif. `DataContoh::buang()` memang tidak menyentuh
 * tabel users, dan menghapus perusahaannya hanya mengosongkan
 * company_id mereka — sesudah itu tidak ada lagi cara menghubungkan
 * mereka dengan perusahaan mana pun.
 */
class HapusDemoTest extends TestCase
{
    use RefreshDatabase;

    /** Satu perusahaan penjaga supaya yang diuji bukan yang terakhir. */
    private function penjaga(): Company
    {
        return Company::create(['name' => 'PT Penjaga', 'code' => 'PJG']);
    }

    public function test_menghapus_perusahaan_contoh_beserta_seluruh_akunnya(): void
    {
        $this->penjaga();

        $this->artisan('demo:pasang --hanya=ABG')->assertSuccessful();

        $c = Company::where('code', 'ABG')->firstOrFail();
        $this->assertGreaterThan(0, User::where('company_id', $c->id)->count());

        $this->artisan('demo:pasang --hanya=ABG --hapus --paksa')->assertSuccessful();

        $this->assertNull(Company::where('code', 'ABG')->first(),
            'Perusahaan contohnya masih ada sesudah --hapus.');

        $this->assertSame(0, User::where('email', 'like', '%@contoh.test')->count(),
            'Akun contoh tertinggal — dan akun yang tertinggal masih dapat masuk '
            .'dengan sandi contoh yang seragam dan lemah.');

        $this->assertSame(0, User::whereNull('company_id')->count(),
            'Akun tertinggal tanpa perusahaan: company_id-nya dikosongkan saat '
            .'perusahaannya dihapus, bukan akunnya yang ikut dibuang.');
    }

    /**
     * Perusahaan yang BUKAN contoh tidak boleh tersentuh.
     *
     * Penjaganya dua dan harus terpenuhi bersama — kodenya ada dalam
     * daftar profil, DAN barisnya bertanda perusahaan contoh. Yang
     * pertama saja tidak cukup: perusahaan sungguhan boleh saja
     * kebetulan berkode sama.
     */
    public function test_perusahaan_yang_bukan_contoh_tidak_disentuh(): void
    {
        $this->penjaga();

        $asli = Company::create(['name' => 'PT Tambang Sungguhan', 'code' => 'ABG', 'demo' => false]);
        $user = User::factory()->create(['company_id' => $asli->id]);

        $this->artisan('demo:pasang --hanya=ABG --hapus --paksa')->assertSuccessful();

        $this->assertNotNull($asli->fresh(), 'Perusahaan sungguhan ikut terhapus.');
        $this->assertNotNull($user->fresh(), 'Pengguna perusahaan sungguhan ikut terhapus.');
    }

    /**
     * Perusahaan sungguhan tidak boleh DITIMPA, bukan hanya tidak
     * dihapus.
     *
     * Barisnya dicari menurut `code`, dan kode itu tidak dijamin milik
     * data contoh. Tanpa penjagaan ini, menjalankan `demo:pasang` pada
     * pemasangan yang dipakai sungguhan — yang perusahaannya kebetulan
     * berkode CDI, BMU, SNP, HBS, atau ABG — menimpa namanya, lokasinya,
     * komoditasnya, kelas risikonya, jumlah pekerjanya, prefiks
     * dokumennya, KTT dan PJO-nya, lalu menandainya sebagai perusahaan
     * contoh dan mengisinya dengan data karangan.
     *
     * Tidak ada galat yang muncul dari itu; yang terlihat hanyalah
     * perusahaan yang mendadak berganti identitas.
     */
    public function test_perusahaan_sungguhan_tidak_ditimpa_oleh_pemasangan(): void
    {
        $asli = Company::create([
            'name' => 'PT Tambang Sungguhan', 'code' => 'ABG', 'demo' => false,
            'commodity' => 'Batubara', 'location' => 'Kalimantan Selatan',
        ]);

        $this->artisan('demo:pasang --hanya=ABG')
            ->expectsOutputToContain('DILEWATI')
            ->assertSuccessful();

        $segar = $asli->fresh();

        $this->assertSame('PT Tambang Sungguhan', $segar->name, 'Nama perusahaan sungguhan tertimpa.');
        $this->assertSame('Batubara', $segar->commodity, 'Komoditas perusahaan sungguhan tertimpa.');
        $this->assertSame('Kalimantan Selatan', $segar->location, 'Lokasi perusahaan sungguhan tertimpa.');
        $this->assertFalse((bool) $segar->demo, 'Perusahaan sungguhan ditandai sebagai perusahaan contoh.');
        $this->assertSame(0, User::where('email', 'like', '%@contoh.test')->count(),
            'Akun contoh dibuatkan untuk perusahaan sungguhan.');
    }

    /**
     * Perusahaan terakhir dibiarkan.
     *
     * Aplikasi tanpa satu pun perusahaan bukan keadaan yang dapat
     * dipakai: pemilih perusahaannya kosong dan setiap halaman kehilangan
     * lingkup datanya. Lebih baik satu perusahaan contoh tertinggal —
     * dan disebutkan — daripada pemasangan yang tidak dapat dibuka.
     */
    public function test_perusahaan_terakhir_tidak_ikut_dihapus(): void
    {
        $this->artisan('demo:pasang --hanya=ABG')->assertSuccessful();

        $this->artisan('demo:pasang --hanya=ABG --hapus --paksa')
            ->expectsOutputToContain('perusahaan terakhir')
            ->assertSuccessful();

        $this->assertNotNull(Company::where('code', 'ABG')->first());
    }
}
