<?php

namespace Tests\Feature;

use App\Models\{Company, Pjp, SmkpChecklistAnswer, SmkpChecklistCategory, SmkpChecklistItem};
use App\Support\Pjp\DaftarPeriksaSmkp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `pjp:pasang` — perintah yang dipanggil deploy/deploy.sh tiap deploy.
 *
 * Dua hal dijaga di sini, dan keduanya gagal tanpa menimbulkan galat.
 *
 * PERTAMA, perintahnya harus ADA. Ia sempat terhapus ketika modul PJP
 * ditulis ulang, sementara deploy.sh tetap memanggilnya. Akibatnya bukan
 * sekadar satu langkah yang gagal: deploy.sh berjalan dengan `set -e`,
 * jadi seluruh sisa deploy berhenti di situ — termasuk `route:cache` dan
 * `view:cache` di bawahnya. Servernya lalu menyajikan rute versi LAMA di
 * atas kode versi baru, dan yang terlihat dari luar hanyalah situs yang
 * tidak berubah sama sekali.
 *
 * KEDUA, ia tidak boleh menghapus. `smkp_checklist_answers` menunjuk
 * butirnya lewat kunci asing yang cascade on delete, sehingga penyemai
 * yang mengosongkan tabelnya lebih dahulu akan menghapus jawaban daftar
 * periksa seluruh mitra — dan yang tersisa hanyalah daftar yang kembali
 * kosong dengan skor yang kembali nol.
 */
class PasangPjpTest extends TestCase
{
    use RefreshDatabase;

    public function test_perintah_yang_dipanggil_deploy_benar_benar_ada(): void
    {
        $this->artisan('pjp:pasang')->assertSuccessful();
    }

    /**
     * Nama perintahnya dibaca dari deploy.sh, bukan ditulis ulang di
     * sini — yang ditulis ulang akan tetap cocok dengan dirinya sendiri
     * meski deploy.sh memanggil nama yang lain.
     */
    public function test_setiap_perintah_pasang_di_deploy_sh_terdaftar(): void
    {
        $skrip = file_get_contents(base_path('deploy/deploy.sh'));

        preg_match_all('/php artisan ([a-z]+:[a-z]+)/', $skrip, $m);

        $terdaftar = array_keys(\Artisan::all());
        $hilang    = array_values(array_diff(array_unique($m[1]), $terdaftar));

        sort($hilang);

        $this->assertSame([], $hilang,
            "deploy.sh memanggil perintah yang tidak terdaftar:\n  ".implode("\n  ", $hilang)
            ."\nDengan `set -e`, deploy berhenti di situ — termasuk route:cache dan "
            ."view:cache sesudahnya, sehingga server menyajikan rute lama di atas kode baru.");
    }

    public function test_daftarnya_tetap_utuh_berapa_kali_pun_dipanggil(): void
    {
        DaftarPeriksaSmkp::pasang();
        DaftarPeriksaSmkp::pasang();
        DaftarPeriksaSmkp::pasang();

        $this->assertSame(17, SmkpChecklistCategory::count());
        $this->assertSame(126, SmkpChecklistItem::count());

        $this->assertSame(178, (int) SmkpChecklistItem::query()
            ->join('smkp_checklist_categories as k', 'k.id', '=', 'smkp_checklist_items.smkp_checklist_category_id')
            ->where('k.kode', '!=', 'LEGALITAS')
            ->sum('smkp_checklist_items.bobot'),
            'Bobot A–P berlipat: butirnya tergandakan diam-diam.');
    }

    public function test_jawaban_mitra_selamat_ketika_daftarnya_dipasang_ulang(): void
    {
        $c   = Company::create(['name' => 'PT Situs Uji', 'code' => 'PSU']);
        $pjp = Pjp::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'nama_perusahaan' => 'PT Mitra Lama', 'status' => 'aktif',
        ]);

        $butir = SmkpChecklistItem::orderBy('id')->firstOrFail();

        SmkpChecklistAnswer::withoutGlobalScopes()->create([
            'pjp_id' => $pjp->id, 'smkp_checklist_item_id' => $butir->id,
            'jawaban' => 'ya', 'nilai' => '3', 'penjelasan' => 'Sudah ada SOP-nya.',
        ]);

        DaftarPeriksaSmkp::pasang();

        $jawaban = SmkpChecklistAnswer::withoutGlobalScopes()->first();

        $this->assertNotNull($jawaban,
            'Jawaban terhapus — penyemainya menghapus butir, dan cascade membawa jawabannya.');
        $this->assertSame('Sudah ada SOP-nya.', $jawaban->penjelasan);
        $this->assertSame($butir->id, $jawaban->smkp_checklist_item_id,
            'Butirnya disisipkan ulang dengan id baru; jawaban lama jadi menggantung.');
    }

    /**
     * Daftar yang DIREVISI harus sampai ke server.
     *
     * Inilah yang tidak dapat dikerjakan migrasi: ia berjalan sekali,
     * dan sesudah tercatat, pertanyaan yang dibetulkan atau bobot yang
     * berubah mengikuti regulasi baru tidak akan pernah menyusul.
     */
    public function test_pertanyaan_yang_direvisi_menimpa_yang_lama(): void
    {
        $butir = SmkpChecklistItem::orderBy('id')->firstOrFail();

        $butir->update(['pertanyaan' => 'Teks lama yang sudah dicabut.', 'bobot' => 99]);

        DaftarPeriksaSmkp::pasang();

        $segar = $butir->fresh();

        $this->assertNotSame('Teks lama yang sudah dicabut.', $segar->pertanyaan,
            'Revisi pada checklist-smkp.json tidak sampai ke baris yang sudah ada.');
        $this->assertNotSame(99, (int) $segar->bobot);
        $this->assertSame($butir->id, $segar->id,
            'Barisnya harus DIPERBARUI di tempat, bukan dihapus lalu disisipkan ulang.');
    }
}
