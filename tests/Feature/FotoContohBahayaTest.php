<?php

namespace Tests\Feature;

use App\Models\{Company, HazardReport};
use App\Support\{Berkas, DataContoh};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Foto contoh pada laporan bahaya.
 *
 * Monitor bahaya kini punya dua kolom foto, dan keduanya hanya berguna
 * bila ada yang mengisinya. Data contoh yang tidak berfoto sama sekali
 * membuat fitur yang paling diminta terlihat rusak justru pada akun
 * yang dibuat untuk mencobanya.
 */
class FotoContohBahayaTest extends TestCase
{
    use RefreshDatabase;

    private function pasang(): Company
    {
        $this->artisan('demo:pasang --hanya=ABG')->assertSuccessful();

        return Company::withoutGlobalScopes()->where('code', 'ABG')->firstOrFail();
    }

    private function bahaya(Company $c)
    {
        return HazardReport::withoutGlobalScopes()->where('company_id', $c->id)->get();
    }

    public function test_laporan_contoh_punya_foto_temuan(): void
    {
        $b = $this->bahaya($this->pasang());

        $this->assertGreaterThan(0, $b->count(), 'Tidak ada laporan bahaya contoh.');

        $this->assertTrue($b->every(fn ($x) => !empty($x->foto)),
            'Ada laporan bahaya contoh tanpa foto temuan — kolom Foto Temuan pada '
            .'monitor terlihat kosong pada akun yang dibuat untuk mencobanya.');
    }

    /**
     * Yang masih Open sengaja BELUM punya foto tindak lanjut.
     *
     * Kolom itu ada justru untuk memperlihatkan selisihnya: mana yang
     * sudah ada buktinya, mana yang belum. Bila seluruh contohnya punya
     * kedua-duanya, kolom yang dibuat untuk memperlihatkan selisih tidak
     * pernah memperlihatkan apa pun, dan tidak ada yang tahu ia bekerja.
     */
    public function test_yang_masih_open_belum_punya_bukti_tindak_lanjut(): void
    {
        $b = $this->bahaya($this->pasang());

        $open  = $b->where('status', 'Open');
        $usai  = $b->where('status', '!=', 'Open');

        $this->assertGreaterThan(0, $open->count(), 'Tidak ada contoh berstatus Open.');
        $this->assertGreaterThan(0, $usai->count(), 'Tidak ada contoh yang sudah ditangani.');

        $this->assertTrue($open->every(fn ($x) => empty($x->foto_tindaklanjut)),
            'Laporan yang masih Open sudah punya foto tindak lanjut; kolom bukti '
            .'perbaikan tidak lagi membedakan apa pun.');

        $this->assertTrue($usai->every(fn ($x) => !empty($x->foto_tindaklanjut)),
            'Laporan yang sudah ditangani tidak punya bukti foto.');
    }

    /** Berkasnya benar-benar ada di disk, bukan sekadar jalur di basis data. */
    public function test_berkasnya_benar_benar_tersimpan(): void
    {
        $b = $this->bahaya($this->pasang())->first();

        foreach ((array) $b->foto as $jalur) {
            $this->assertTrue(Storage::disk(Berkas::TERTUTUP)->exists($jalur),
                "Jalur foto {$jalur} tercatat tetapi berkasnya tidak ada; rute "
                .'penyaji memulangkan 404 dan kolomnya terlihat kosong tanpa galat.');
        }
    }

    /**
     * Dijalankan ulang tidak menggandakan berkasnya.
     *
     * Namanya ditentukan dari id baris, tidak diacak. Dengan nama acak,
     * tiap pemasangan ulang meninggalkan satu rombongan berkas yang
     * barisnya sudah tidak ada — tidak menimbulkan galat, tidak terlihat
     * di mana pun, hanya memakan disk selamanya.
     */
    public function test_pemasangan_ulang_tidak_menumpuk_berkas(): void
    {
        $this->pasang();
        $pertama = count(Storage::disk(Berkas::TERTUTUP)->files('hazard/contoh'));

        $this->pasang();
        $kedua = count(Storage::disk(Berkas::TERTUTUP)->files('hazard/contoh'));

        $this->assertSame($pertama, $kedua,
            "Pemasangan kedua menambah berkas ({$pertama} → {$kedua}) alih-alih menimpa.");
    }

    /** Membuang data contohnya ikut membuang berkasnya. */
    public function test_membuang_data_contoh_ikut_membuang_berkasnya(): void
    {
        $c = $this->pasang();

        $this->assertGreaterThan(0, count(Storage::disk(Berkas::TERTUTUP)->files('hazard/contoh')));

        DataContoh::buang($c);

        $sisa = array_filter(
            Storage::disk(Berkas::TERTUTUP)->files('hazard/contoh'),
            fn ($j) => str_starts_with(basename($j), $c->id.'-'),
        );

        $this->assertSame([], array_values($sisa),
            'Berkas foto contoh tertinggal sesudah datanya dibuang. Barisnya sudah '
            .'hilang, jadi tidak ada lagi yang menghubungkan berkas itu dengan '
            .'perusahaan mana pun — dan tidak ada lagi yang tahu boleh menghapusnya.');
    }
}
