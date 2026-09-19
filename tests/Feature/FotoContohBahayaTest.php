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
 * Monitor bahaya punya dua kolom foto, dan keduanya hanya berguna bila
 * ada yang mengisinya. Data contoh yang tidak berfoto sama sekali
 * membuat fitur itu terlihat rusak justru pada akun yang dibuat untuk
 * mencobanya — dan "terlihat rusak" di sini tidak menimbulkan galat
 * apa pun yang bisa menegur siapa pun.
 *
 * Penyalinan berkasnya sendiri ada di DataContoh::fotoBahaya(); yang
 * dijaga berkas ini adalah hasilnya: ada fotonya, berkasnya benar-benar
 * tersimpan, tidak menumpuk saat dimuat ulang, dan ikut terbuang
 * bersama datanya.
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

    /** Folder foto contoh satu perusahaan, sebagaimana disusun DataContoh. */
    private function folder(Company $c): string
    {
        return 'hazard/contoh/'.$c->id;
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
     * Bukti perbaikan HANYA pada yang sudah ditutup.
     *
     * Kolom foto tindak lanjut ada justru untuk memperlihatkan
     * selisihnya: mana yang sudah ada buktinya, mana yang belum. Bila
     * seluruh contohnya punya kedua-duanya, kolom yang dibuat untuk
     * memperlihatkan selisih tidak pernah memperlihatkan apa pun, dan
     * tidak ada yang tahu ia bekerja.
     */
    public function test_hanya_yang_sudah_ditutup_punya_bukti_perbaikan(): void
    {
        $b = $this->bahaya($this->pasang());

        $tutup = $b->where('status', 'Closed');
        $belum = $b->where('status', '!=', 'Closed');

        $this->assertGreaterThan(0, $tutup->count(), 'Tidak ada contoh berstatus Closed.');
        $this->assertGreaterThan(0, $belum->count(), 'Tidak ada contoh yang belum ditutup.');

        $this->assertTrue($tutup->every(fn ($x) => !empty($x->foto_tindaklanjut)),
            'Laporan yang sudah ditutup tidak punya foto perbaikan.');

        $this->assertTrue($belum->every(fn ($x) => empty($x->foto_tindaklanjut)),
            'Laporan yang belum ditutup sudah punya foto perbaikan; kolom bukti '
            .'perbaikan tidak lagi membedakan apa pun.');
    }

    /** Berkasnya benar-benar ada di diska, bukan sekadar jalur di basis data. */
    public function test_berkasnya_benar_benar_tersimpan(): void
    {
        $b = $this->bahaya($this->pasang())->first();

        $jalur = array_merge((array) $b->foto, (array) $b->foto_tindaklanjut);

        $this->assertNotEmpty($jalur);

        foreach ($jalur as $j) {
            $this->assertTrue(Storage::disk(Berkas::TERTUTUP)->exists($j),
                "Jalur foto {$j} tercatat tetapi berkasnya tidak ada; rute penyaji "
                .'memulangkan 404 dan kolomnya terlihat kosong tanpa satu pun galat.');
        }
    }

    /**
     * Dijalankan ulang tidak menggandakan berkasnya.
     *
     * Namanya tetap, tidak diacak. Dengan nama acak, tiap pemasangan
     * ulang meninggalkan satu rombongan berkas yang barisnya sudah tidak
     * ada — tidak menimbulkan galat, tidak terlihat di mana pun, dan
     * memakan diska selamanya.
     */
    public function test_pemasangan_ulang_tidak_menumpuk_berkas(): void
    {
        $c = $this->pasang();
        $pertama = count(Storage::disk(Berkas::TERTUTUP)->files($this->folder($c)));

        $this->pasang();
        $kedua = count(Storage::disk(Berkas::TERTUTUP)->files($this->folder($c)));

        $this->assertGreaterThan(0, $pertama);
        $this->assertSame($pertama, $kedua,
            "Pemasangan kedua menambah berkas ({$pertama} → {$kedua}) alih-alih menimpa.");
    }

    /** Membuang data contohnya ikut membuang berkasnya. */
    public function test_membuang_data_contoh_ikut_membuang_berkasnya(): void
    {
        $c = $this->pasang();

        $this->assertGreaterThan(0,
            count(Storage::disk(Berkas::TERTUTUP)->files($this->folder($c))));

        DataContoh::buang($c);

        $this->assertSame([], Storage::disk(Berkas::TERTUTUP)->files($this->folder($c)),
            'Berkas foto contoh tertinggal sesudah datanya dibuang. Barisnya sudah '
            .'hilang, jadi tidak ada lagi yang menghubungkan berkas itu dengan '
            .'perusahaan mana pun — dan tidak ada lagi yang tahu boleh menghapusnya.');
    }
}
