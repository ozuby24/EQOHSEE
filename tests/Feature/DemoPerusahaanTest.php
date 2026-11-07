<?php

namespace Tests\Feature;

use App\Console\Commands\PasangDemo;
use App\Models\{Company, SmkpAudit, User};
use App\Support\SmkpTahap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Perintah `demo:pasang` — beberapa perusahaan contoh sekaligus.
 *
 * Satu perusahaan contoh membuat seluruh hitungan yang membaca profil
 * perusahaan terlihat benar tanpa pernah diuji: tidak ada pembanding.
 * Yang dijaga berkas ini karena itu bukan "perintahnya berjalan",
 * melainkan bahwa profil-profilnya benar-benar BERBEDA satu sama lain
 * dan bahwa menjalankannya berulang tidak merusak apa pun.
 */
class DemoPerusahaanTest extends TestCase
{
    use RefreshDatabase;

    public function test_membuat_lima_perusahaan_bertanda_contoh(): void
    {
        $this->artisan('demo:pasang', ['--tanpa-isi' => true])->assertSuccessful();

        $c = Company::withoutGlobalScopes()->whereNotNull('code')->get();

        $this->assertCount(5, $c);
        $this->assertTrue($c->every(fn (Company $x) => (bool) $x->demo),
            'Perusahaan yang tidak bertanda contoh tidak dapat dimuati DataContoh '
            .'— penjagaan tiga lapisnya menolak sasaran yang salah.');
    }

    /**
     * Kelas risikonya harus ejaan yang dikenal modul audit SMKP
     * (SmkpTahap::kelasRisiko — Rendah, Sedang, Tinggi). Ejaan lain
     * ditolak validasi profil perusahaan dan Tahap I audit.
     */
    public function test_kelas_risiko_tiap_profil_dikenal_tabel_mandays(): void
    {
        $this->artisan('demo:pasang', ['--tanpa-isi' => true])->assertSuccessful();

        foreach (Company::withoutGlobalScopes()->whereNotNull('code')->get() as $c) {
            $this->assertContains($c->risk_class, SmkpTahap::kelasRisiko(),
                $c->name.' memakai kelas risiko yang tidak ada pada tabel mandays.');
        }
    }

    /**
     * Profilnya BERJAUHAN, bukan lima salinan bernama berbeda.
     *
     * Inilah satu-satunya alasan perintah ini ada. Lima perusahaan yang
     * jumlah pekerja dan kelas risikonya seragam menghasilkan lima Tahap I
     * audit yang identik.
     */
    public function test_profilnya_berjauhan(): void
    {
        $this->artisan('demo:pasang', ['--tanpa-isi' => true])->assertSuccessful();

        $profil = Company::withoutGlobalScopes()->whereNotNull('code')->get()
            ->map(fn (Company $c) => ((int) $c->workers_employee + (int) $c->workers_sub).'|'.$c->risk_class)
            ->unique()->values();

        $this->assertGreaterThanOrEqual(4, $profil->count(),
            'Lima profil hanya '.$profil->count().' kombinasi pekerja & kelas risiko yang '
            .'berbeda; profilnya terlalu mirip untuk membuktikan apa pun.');
    }

    /**
     * Dua akun per perusahaan, dan yang satu boleh meninjau yang lain.
     *
     * Akun PENJAJAL tidak dihitung. Ia menumpang di perusahaan contoh
     * pertama supaya halamannya tidak kosong, tetapi ia bukan bagian
     * dari pasangan pengaju-peninjau yang diuji di sini — dan menghitungnya
     * membuat uji ini mengabarkan "perusahaan pertama punya tiga akun"
     * setiap kali, sebuah kegagalan yang tidak menunjuk apa pun yang rusak.
     */
    public function test_tiap_perusahaan_punya_pengaju_dan_peninjau(): void
    {
        $this->artisan('demo:pasang', ['--tanpa-isi' => true])->assertSuccessful();

        foreach (Company::withoutGlobalScopes()->whereNotNull('code')->get() as $c) {
            $u = User::withoutGlobalScopes()->where('company_id', $c->id)
                ->whereNotIn('email', [PasangDemo::PENJAJAL_ADMIN, PasangDemo::PENJAJAL_BIASA])
                ->get();

            $this->assertCount(2, $u, $c->name.' tidak punya dua akun.');
            $this->assertTrue($u->contains(fn (User $x) => $x->isKtt() || $x->isAdmin()),
                $c->name.' tidak punya peninjau; seluruh datanya akan tersimpan '
                .'sebagai draf dan tidak masuk hitungan KPI.');
        }
    }

    public function test_dijalankan_ulang_tidak_menggandakan(): void
    {
        $this->artisan('demo:pasang', ['--tanpa-isi' => true])->assertSuccessful();
        $this->artisan('demo:pasang', ['--tanpa-isi' => true])->assertSuccessful();

        $this->assertSame(5, Company::withoutGlobalScopes()->whereNotNull('code')->count());

        /* Dua akun kali lima perusahaan. Akun penjajal dikecualikan dengan
           alasan yang sama seperti di atas: ia satu baris tetap, bukan
           bagian dari hitungan per perusahaan yang sedang dijaga di sini —
           yang dijaga adalah bahwa pemasangan kedua tidak MENGGANDAKAN. */
        $this->assertSame(10, User::withoutGlobalScopes()->whereNotNull('company_id')
            ->whereNotIn('email', [PasangDemo::PENJAJAL_ADMIN, PasangDemo::PENJAJAL_BIASA])
            ->count());

        /* Dan penjajalnya sendiri juga tidak berganda. */
        $this->assertSame(1, User::withoutGlobalScopes()
            ->where('email', PasangDemo::PENJAJAL_ADMIN)->count());
    }

    /**
     * Sandi yang sudah diganti orang TIDAK dikembalikan ke bawaannya.
     *
     * Perintah ini dijalankan ulang tiap kali data contohnya disegarkan.
     * Menimpa sandi setiap kali berarti akun yang sandinya sudah diganti
     * kembali ke `rahasia123` diam-diam — pada pemasangan demo yang
     * terbuka ke jaringan, itu bukan lagi sekadar akun contoh.
     */
    public function test_sandi_yang_sudah_diganti_tidak_ditimpa(): void
    {
        $this->artisan('demo:pasang', ['--tanpa-isi' => true])->assertSuccessful();

        $u = User::withoutGlobalScopes()->where('email', 'ktt.cdi@contoh.test')->firstOrFail();
        $u->forceFill(['password' => Hash::make('sandi-baru-yang-panjang')])->save();

        $this->artisan('demo:pasang', ['--tanpa-isi' => true])->assertSuccessful();

        $this->assertTrue(Hash::check('sandi-baru-yang-panjang', $u->fresh()->password));
    }

    /**
     * Perusahaan contoh berlogo, tetapi logo yang sudah diganti lewat
     * Profil Perusahaan tidak ditimpa saat perintahnya dijalankan ulang.
     */
    public function test_logo_contoh_terpasang_tanpa_menimpa_logo_pilihan(): void
    {
        $this->artisan('demo:pasang', ['--tanpa-isi' => true, '--hanya' => 'CDI'])->assertSuccessful();

        $c = Company::withoutGlobalScopes()->where('code', 'CDI')->firstOrFail();
        $this->assertSame('logo/contoh-cdi.png', $c->logo);
        Storage::disk('public')->assertExists('logo/contoh-cdi.png');

        $c->forceFill(['logo' => 'logo/unggahan-sendiri.png'])->save();
        $this->artisan('demo:pasang', ['--tanpa-isi' => true, '--hanya' => 'CDI'])->assertSuccessful();

        $this->assertSame('logo/unggahan-sendiri.png', $c->fresh()->logo);
    }

    public function test_hanya_mengerjakan_kode_yang_diminta(): void
    {
        $this->artisan('demo:pasang', ['--tanpa-isi' => true, '--hanya' => 'ABG'])
            ->assertSuccessful();

        $this->assertSame(1, Company::withoutGlobalScopes()->whereNotNull('code')->count());
        $this->assertNotNull(Company::withoutGlobalScopes()->where('code', 'ABG')->first());
    }

    public function test_kode_asing_gagal_alih_alih_diam(): void
    {
        $this->artisan('demo:pasang', ['--tanpa-isi' => true, '--hanya' => 'ZZZ'])
            ->assertFailed();

        $this->assertSame(0, Company::withoutGlobalScopes()->whereNotNull('code')->count());
    }

    /**
     * Pemuatan penuh satu perusahaan — yang paling kecil, supaya uji ini
     * tetap dapat dijalankan seharian.
     *
     * Yang dibuktikan bukan jumlah barisnya melainkan bahwa isinya
     * SAMPAI ke perusahaan itu dan audit SMKP-nya mewarisi profilnya.
     */
    public function test_memuat_isi_satu_perusahaan(): void
    {
        $this->artisan('demo:pasang', ['--hanya' => 'ABG'])->assertSuccessful();

        $c = Company::withoutGlobalScopes()->where('code', 'ABG')->firstOrFail();

        $audit = SmkpAudit::withoutGlobalScopes()->where('company_id', $c->id)->first();

        $this->assertNotNull($audit, 'Audit SMKP tidak ikut terisi.');

        // Tahap I mewarisi profilnya: 40 pekerja kelas Rendah.
        $this->assertSame('40', $audit->permulaan['jumlah_pekerja']);
        $this->assertSame('Rendah', $audit->permulaan['kelas_risiko']);
    }
}
