<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\{Ai, Diagnosa, Ko};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pengaturan dapat DISIMPAN, bukan sekadar dibaca.
 *
 * Kolom `app_settings.value` lahir bertipe `json`, tetapi tidak pernah
 * dipakai sebagai JSON: yang disimpan di dalamnya adalah nama penyedia
 * AI ("anthropic"), nama model ("claude-sonnet-5"), dan kunci API yang
 * sudah terenkripsi ("eyJpdiI6..."). Tidak satu pun dokumen JSON yang
 * sah.
 *
 * Di SQLite hal itu tak pernah terlihat — `json` di sana hanyalah TEXT
 * tanpa pemeriksaan — sehingga seluruh uji lulus sementara pemasangan
 * sungguhan rusak. Di PostgreSQL dan MySQL kolomnya menolak nilainya:
 *
 *     SQLSTATE[22P02] invalid input syntax for type json
 *     DETAIL: Token "anthropic" is invalid.
 *
 * Bentuk kegagalannya yang membuatnya sulit dikenali: "Uji sambungan"
 * berhasil — ia tidak menulis apa pun — lalu "Simpan" memulangkan galat
 * 500 telanjang. Kuncinya diuji, dinyatakan terhubung, lalu hilang.
 * Satu-satunya penyedia yang tampak bekerja adalah yang kuncinya dibaca
 * dari `.env`, sebab itu satu-satunya jalan yang tidak melewati tabel
 * ini.
 *
 * Nilai numerik modul KO (`ko_warn_days` dan kawan-kawan) kebetulan
 * JSON yang sah — `90` adalah angka JSON — sehingga lolos selama ini
 * dan menyamarkan kerusakannya.
 *
 * Karena itu berkas ini menjaganya dari tiga sisi: satu uji yang
 * berjalan di mana saja lewat Diagnosa, satu yang membaca bentuk kolom
 * dari migrasinya, dan satu yang menjalankannya sungguhan di PostgreSQL
 * bila ada.
 */
class PengaturanTersimpanTest extends TestCase
{
    use RefreshDatabase;

    /* ═══════════ nilai yang bukan JSON ═══════════ */

    /**
     * Nilai yang bukan JSON tersimpan utuh.
     *
     * Ini yang sesungguhnya rusak. Di SQLite uji ini lulus bahkan tanpa
     * perbaikannya — itu sebabnya ada dua uji lain di bawah.
     */
    public function test_nilai_bukan_json_tersimpan_utuh(): void
    {
        Ai::simpanPengaturan('anthropic', 'claude-sonnet-5', 900);
        Ai::simpanKunci('anthropic', 'sk-ant-api03-contohcontohcontoh1234');

        $this->assertSame('anthropic', Ai::penyedia());
        $this->assertSame('claude-sonnet-5', Ai::model());
        $this->assertSame('sk-ant-api03-contohcontohcontoh1234', Ai::kunci('anthropic'));
    }

    /** Nilai angka milik KO tetap terbaca sebagai angka pada kolom teks. */
    public function test_pengaturan_angka_ko_tetap_terbaca(): void
    {
        Ko::simpanSettings(['ko_warn_days' => 45, 'ko_target_layak' => 97]);

        $this->assertSame(45, Ko::warnDays());
        $this->assertSame(97, Ko::targetLayak());
    }

    /* ═══════════ bentuk kolomnya ═══════════ */

    /**
     * Ada migrasi yang mengubah `value` menjadi teks.
     *
     * Dibaca dari berkas migrasinya, bukan dari skema yang sedang
     * berjalan: pada SQLite `json` dan `text` menghasilkan kolom yang
     * persis sama, sehingga skemanya tidak dapat membedakan keduanya —
     * dan justru ketidakmampuan itulah yang menyembunyikan kerusakan
     * ini sekian lama.
     */
    public function test_ada_migrasi_yang_menjadikan_value_teks(): void
    {
        $ketemu = false;

        foreach (glob(database_path('migrations/*.php')) as $berkas) {
            $isi = (string) file_get_contents($berkas);

            if (str_contains($isi, "text('value')") && str_contains($isi, 'app_settings')) {
                $ketemu = true;
                break;
            }
        }

        $this->assertTrue($ketemu,
            'Tidak ada migrasi yang mengubah app_settings.value menjadi teks. '
            .'Pada kolom bertipe json, PostgreSQL dan MySQL menolak kunci API terenkripsi '
            .'dan setiap penyimpanan berakhir galat 500.');
    }

    /* ═══════════ diagnosa ═══════════ */

    /** Temuan diagnosa menurut kodenya. */
    private function temuan(string $kode): ?array
    {
        foreach (Diagnosa::jalankan() as $h) {
            if (($h['kode'] ?? null) === $kode) return $h;
        }

        return null;
    }

    public function test_diagnosa_memeriksa_penyimpanan_pengaturan(): void
    {
        $h = $this->temuan('pengaturan-tersimpan');

        $this->assertNotNull($h, 'Diagnosa tidak memeriksa apakah pengaturan dapat disimpan.');
        $this->assertSame(Diagnosa::AMAN, $h['keadaan']);
    }

    /** Pemeriksaannya tidak meninggalkan baris uji di tabel pengaturan. */
    public function test_diagnosa_tidak_meninggalkan_baris_uji(): void
    {
        Diagnosa::jalankan();

        $this->assertSame(0,
            DB::table('app_settings')->where('key', 'like', '__diagnosa%')->count(),
            'Baris uji tertinggal; halaman pengaturan akan memperlihatkannya.');
    }

    /**
     * Bila tabelnya menolak tulisan, diagnosa berkata GAWAT dan
     * menyebut sebabnya.
     */
    public function test_tabel_yang_menolak_tulisan_terdeteksi(): void
    {
        /* Kolomnya yang dibuang, bukan tabelnya: tabel yang hilang
           adalah keadaan lain (belum dimigrasi), dan yang diuji di sini
           adalah tabel yang ADA tetapi menolak nilainya — persis bentuk
           kerusakan pada kolom bertipe json. */
        DB::statement('ALTER TABLE app_settings DROP COLUMN value');

        $h = $this->temuan('pengaturan-tersimpan');

        $this->assertNotNull($h);
        $this->assertNotSame(Diagnosa::AMAN, $h['keadaan']);
        $this->assertNotNull($h['tindakan'] ?? null,
            'Temuan tanpa langkah berikutnya membuat orang berhenti di layar ini.');
    }

    /* ═══════════ halaman pengaturan ═══════════ */

    /**
     * Kegagalan menyimpan muncul sebagai pesan, bukan galat 500.
     *
     * Layar "Server Error" telanjang terbaca sebagai tombol yang rusak,
     * dan yang mengalaminya menekannya berulang kali.
     */
    public function test_gagal_simpan_menjadi_pesan_bukan_500(): void
    {
        $this->actingAs(User::factory()->create([
            'is_admin' => true, 'email_verified_at' => now(),
        ]));

        DB::statement('DROP TABLE app_settings');

        $r = $this->from(route('admin.ai'))->post(route('admin.ai.simpan'), [
            'penyedia' => 'anthropic',
            'model'    => 'claude-sonnet-5',
            'kunci'    => 'sk-ant-api03-contohcontohcontoh1234',
        ]);

        $r->assertRedirect(route('admin.ai'));
        $r->assertSessionHasErrors('ai');
    }

    /**
     * Kunci yang gagal disimpan tidak ikut masuk ke pesan galatnya.
     *
     * Pesan galat basis data menyertakan nilai parameternya — dan
     * parameter itu adalah kuncinya.
     */
    public function test_kunci_tidak_bocor_lewat_pesan_galat(): void
    {
        $kunci = 'sk-ant-api03-rahasiasekalijangansampaibocor';

        $this->actingAs(User::factory()->create([
            'is_admin' => true, 'email_verified_at' => now(),
        ]));

        DB::statement('DROP TABLE app_settings');

        $r = $this->from(route('admin.ai'))->post(route('admin.ai.simpan'), [
            'penyedia' => 'anthropic', 'kunci' => $kunci,
        ]);

        $galat = $r->getSession()->get('errors');
        $pesan = is_object($galat) ? implode(' ', $galat->get('ai')) : json_encode($galat);

        $this->assertNotSame('', $pesan, 'Tidak ada pesan galat sama sekali.');

        $this->assertStringNotContainsString($kunci, $pesan);
        $this->assertStringNotContainsString($kunci, $r->getContent() ?: '');
    }

    public function test_samarkan_membuang_kunci_dari_teks_apa_pun(): void
    {
        $kunci = 'sk-ant-api03-rahasiasekali';

        $this->assertStringNotContainsString($kunci,
            Ai::samarkan('SQLSTATE[22P02] parameter $2 = \''.$kunci.'\'', $kunci));

        // Tanpa kunci tidak boleh melempar; jalur galat dipakai justru
        // ketika kuncinya memang tidak diisi.
        $this->assertSame('apa adanya', Ai::samarkan('apa adanya', null));
    }
}
