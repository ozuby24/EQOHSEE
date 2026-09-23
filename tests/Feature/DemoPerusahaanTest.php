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
     * Kelas risikonya harus ejaan yang dikenal tabel mandays.
     *
     * 'Sedang' alih-alih 'Menengah' tidak menimbulkan galat apa pun:
     * SmkpTahap jatuh ke kolom cadangan dan menagih hari kerja kelas
     * TERTINGGI kepada perusahaan kelas menengah, pada tiap lembar
     * rencana audit yang tercetak.
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
     * jumlah pekerja dan kelas risikonya seragam menghasilkan lima
     * rencana audit yang identik — dan kolom tabel mandays yang salah
     * tetap tidak akan terlihat.
     */
    public function test_profilnya_menghasilkan_hari_kerja_audit_yang_berbeda(): void
    {
        $this->artisan('demo:pasang', ['--tanpa-isi' => true])->assertSuccessful();

        $dasar = Company::withoutGlobalScopes()->whereNotNull('code')->get()
            ->map(fn (Company $c) => SmkpTahap::mandays([
                'jumlah_pekerja' => (int) $c->workers_employee + (int) $c->workers_sub,
                'kelas_risiko'   => $c->risk_class,
            ])['dasar'])->unique()->values();

        $this->assertGreaterThanOrEqual(4, $dasar->count(),
            'Lima profil hanya menghasilkan '.$dasar->count().' dasar hari kerja yang '
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

        // 40 pekerja kelas Rendah: baris 26–45 menagih 4 hari.
        $this->assertSame(40, $audit->mandays()['pekerja']);
        $this->assertSame(4, $audit->mandays()['dasar']);
    }
}
