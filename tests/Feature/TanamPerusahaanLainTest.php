<?php

namespace Tests\Feature;

use App\Models\{Company, Document, Inspection, SmkpAudit, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menanam data ke perusahaan lain lewat `company_id` di borang.
 *
 * Ini kebocoran TULIS, bukan baca, dan itu sebabnya seluruh penjagaan
 * yang sudah ada tidak menangkapnya:
 *
 * - Scope MilikPerusahaan menjaga pembacaan. Baris yang tertanam di
 *   perusahaan lain justru tidak terlihat oleh yang menanamnya — dan
 *   tetap terlihat oleh korbannya.
 * - BerpemilikPerusahaan sengaja tidak menimpa company_id yang disebut
 *   tegas, sebab pemuat data contoh dan perintah konsol memang perlu
 *   menyebutkannya.
 *
 * Sungguh terjadi sebelum penjaga di kelas dasar Controller ada:
 * pengguna biasa perusahaan A mengirim company_id perusahaan B ke
 * penyimpanan Dokumen dan Inspeksi, dan barisnya tersimpan sebagai
 * milik B. Pada modul dokumen terkendali akibatnya bukan sekadar data
 * nyasar — daftar induk milik B bertambah satu prosedur yang tidak
 * pernah dibuat siapa pun di sana, dan pada daftar itulah audit
 * eksternal bersandar.
 *
 * Uji ini memakai borang sungguhan lewat HTTP, bukan memanggil
 * `pemilik()` langsung. Yang perlu dibuktikan adalah jalannya tertutup,
 * bukan bahwa fungsinya ada.
 */
class TanamPerusahaanLainTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;
    private Company $b;
    private User $biasa;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Company::create(['name' => 'PT A']);
        $this->b = Company::create(['name' => 'PT B']);

        $this->biasa = User::factory()->create([
            'company_id'        => $this->a->id,
            'is_admin'          => false,
            'email_verified_at' => now(),
        ]);
    }

    public function test_dokumen_tidak_dapat_ditanam_ke_perusahaan_lain(): void
    {
        $this->actingAs($this->biasa)->post(route('dokumen.store'), [
            'kode' => 'X-1', 'judul' => 'Dokumen Uji',
            'jenis' => 'Prosedur', 'revisi' => 0, 'status' => 'draft',
            'company_id' => $this->b->id,
        ]);

        $dok = Document::withoutGlobalScopes()->where('kode', 'X-1')->first();

        /* Uji ini tidak berarti apa-apa bila borangnya memang ditolak
           karena sebab lain — periksa dulu barisnya benar-benar ada. */
        $this->assertNotNull($dok, 'Dokumennya tidak tersimpan sama sekali; uji ini tidak menguji apa pun.');

        $this->assertSame($this->a->id, $dok->company_id,
            'Dokumen tertanam di perusahaan lain. Daftar induk dokumen terkendali '
            .'perusahaan itu kini memuat baris yang tidak dibuat siapa pun di sana.');
    }

    public function test_inspeksi_tidak_dapat_ditanam_ke_perusahaan_lain(): void
    {
        $this->actingAs($this->biasa)->post(route('inspeksi.store'), [
            'judul' => 'Inspeksi Uji', 'tanggal' => now()->toDateString(),
            'status' => 'Berjalan', 'company_id' => $this->b->id,
        ]);

        $ins = Inspection::withoutGlobalScopes()->where('judul', 'Inspeksi Uji')->first();

        $this->assertNotNull($ins, 'Inspeksinya tidak tersimpan sama sekali.');
        $this->assertSame($this->a->id, $ins->company_id, 'Inspeksi tertanam di perusahaan lain.');
    }

    /**
     * Batas "satu audit SMKP per perusahaan per tahun" tidak dapat
     * dilewati dengan menyebut perusahaan lain.
     *
     * Aturan keunikannya dahulu dibangun dari company_id mentah milik
     * permintaan. Pengguna biasa cukup mengirim perusahaan lain:
     * keunikannya diperiksa terhadap perusahaan itu — yang memang belum
     * punya audit tahun tersebut — lalu barisnya tetap tersimpan atas
     * nama perusahaannya sendiri. Hasilnya dua audit pada tahun yang
     * sama, tepat yang dilarang aturannya.
     */
    public function test_keunikan_audit_smkp_tidak_dapat_dilewati(): void
    {
        SmkpAudit::withoutGlobalScopes()->create([
            'company_id' => $this->a->id, 'tahun' => 2026,
            'judul' => 'Audit pertama', 'status' => 'berjalan', 'tahap' => 1,
        ]);

        $this->actingAs($this->biasa)->post(route('smkp.store'), [
            'tahun' => 2026, 'judul' => 'Audit selundupan', 'status' => 'berjalan',
            'company_id' => $this->b->id,
        ]);

        $this->assertSame(1,
            SmkpAudit::withoutGlobalScopes()->where('company_id', $this->a->id)->where('tahun', 2026)->count(),
            'Batas satu audit per tahun terlewati dengan menyebut perusahaan lain.');

        $this->assertSame(0,
            SmkpAudit::withoutGlobalScopes()->where('company_id', $this->b->id)->count(),
            'Audit tertanam di perusahaan lain.');
    }

    /**
     * Administrator TETAP boleh menyebut perusahaan.
     *
     * Tanpa ini, penambalan di atas akan menutup jalan yang memang
     * harus terbuka: administrator EQOHSEE mengelola banyak perusahaan
     * dan perlu membuatkan data atas nama salah satunya.
     */
    public function test_administrator_tetap_dapat_menyebut_perusahaan(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);

        $this->actingAs($admin)->post(route('dokumen.store'), [
            'kode' => 'ADM-1', 'judul' => 'Dibuatkan admin',
            'jenis' => 'Prosedur', 'revisi' => 0, 'status' => 'draft',
            'company_id' => $this->b->id,
        ]);

        $dok = Document::withoutGlobalScopes()->where('kode', 'ADM-1')->first();

        $this->assertNotNull($dok);
        $this->assertSame($this->b->id, $dok->company_id,
            'Administrator kehilangan kemampuan membuatkan data atas nama perusahaan.');
    }

    /**
     * Penjaganya ada di kelas DASAR, bukan disalin per controller.
     *
     * Sebelumnya memang disalin — sepuluh kali, dengan dua ejaan
     * berbeda — dan sembilan controller lain tidak kebagian. Salinan
     * yang muncul lagi adalah tanda bahwa pola lamanya kembali, dan
     * bersamanya kemungkinan controller berikutnya kembali terlewat.
     */
    public function test_penjaga_tidak_disalin_ulang_per_controller(): void
    {
        $salinan = [];

        foreach (glob(app_path('Http/Controllers/**/*.php')) + glob(app_path('Http/Controllers/*.php')) as $f) {
            if (basename($f) === 'Controller.php') continue;

            if (str_contains((string) file_get_contents($f), 'function pemilik(array')) {
                $salinan[] = basename($f);
            }
        }

        $this->assertSame([], $salinan,
            'pemilik() disalin lagi ke controller: '.implode(', ', $salinan)
            .'. Pakai yang dari kelas dasar — penjaga yang harus diingat untuk disalin '
            .'adalah penjaga yang akan terlewat.');
    }
}
