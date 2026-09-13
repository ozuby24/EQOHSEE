<?php

namespace Tests\Feature;

use App\Models\{Company, Pjp, PjpEvaluasi, PjpLaporan, User};
use App\Support\PjpEkspor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * CRUD, ekspor, penghapusan berkas, dan batas perusahaan modul PJP.
 */
class PjpModulTest extends TestCase
{
    use RefreshDatabase;

    private function masuk(array $atribut = ['is_admin' => true]): User
    {
        $u = User::factory()->create($atribut);
        $this->actingAs($u);

        return $u;
    }

    /* ---------- daftar & formulir ---------- */

    public function test_beranda_modul_mengirim_ringkasan(): void
    {
        $this->masuk();
        Pjp::factory()->create(['status' => 'aktif']);
        Pjp::factory()->create(['status' => 'perlu_tindak_lanjut']);

        $this->get(route('pjp.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Pjp/Beranda')
                ->where('ringkas.total', 2)
                ->where('ringkas.aktif', 1)
                ->where('ringkas.perluTindakLanjut', 1)
                ->where('ringkas.tidakAktif', 0));
    }

    /**
     * Bilah status menggambar tiga segmen tetap; kunci yang hilang saat
     * kosong membuat segmennya lenyap tanpa ada yang menandainya.
     */
    public function test_hitungan_status_selalu_memuat_seluruh_kunci(): void
    {
        $this->masuk();

        $this->assertSame(
            ['aktif' => 0, 'perlu_tindak_lanjut' => 0, 'tidak_aktif' => 0],
            Pjp::statusCountsFor(),
        );
    }

    public function test_pjp_tersimpan_lewat_formulir(): void
    {
        $this->masuk();

        $this->post(route('pjp.simpan'), [
            'nama_perusahaan' => 'PT Jasa Tambang Sejahtera',
            'nib' => '1234567890123',
            'penanggung_jawab' => 'Budi',
            'status' => 'aktif',
        ])->assertRedirect();

        $this->assertDatabaseHas('pjps', ['nama_perusahaan' => 'PT Jasa Tambang Sejahtera']);
    }

    public function test_status_di_luar_daftar_ditolak(): void
    {
        $this->masuk();

        $this->post(route('pjp.simpan'), [
            'nama_perusahaan' => 'PT Status Ngawur',
            'status' => 'entah',
        ])->assertSessionHasErrors('status');

        $this->assertDatabaseCount('pjps', 0);
    }

    public function test_penyaring_nama_dan_status_bekerja_pada_daftar(): void
    {
        $this->masuk();

        Pjp::factory()->create(['nama_perusahaan' => 'PT Borneo Jaya', 'status' => 'aktif']);
        Pjp::factory()->create(['nama_perusahaan' => 'PT Sulawesi Mandiri', 'status' => 'tidak_aktif']);

        $this->get(route('pjp.daftar', ['cari' => 'Borneo']))
            ->assertInertia(fn (Assert $page) => $page->has('pjps.data', 1)
                ->where('pjps.data.0.nama_perusahaan', 'PT Borneo Jaya'));

        $this->get(route('pjp.daftar', ['status' => 'tidak_aktif']))
            ->assertInertia(fn (Assert $page) => $page->has('pjps.data', 1)
                ->where('pjps.data.0.nama_perusahaan', 'PT Sulawesi Mandiri'));
    }

    /* ---------- penghapusan ---------- */

    public function test_hapus_pjp_ikut_menghapus_berkas_dan_data_turunannya(): void
    {
        Storage::fake('public');
        $this->masuk();

        $pjp = Pjp::factory()->create();

        $path = UploadedFile::fake()->create('laporan.pdf', 100)->store("pjp-laporan/{$pjp->id}", 'public');
        PjpLaporan::factory()->for($pjp)->create(['file_path' => $path]);
        PjpEvaluasi::factory()->for($pjp)->create();

        Storage::disk('public')->assertExists($path);

        $this->delete(route('pjp.hapus', $pjp))->assertRedirect(route('pjp.daftar'));

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('pjps', ['id' => $pjp->id]);
        $this->assertDatabaseCount('pjp_laporans', 0);
        $this->assertDatabaseCount('pjp_evaluasis', 0);
    }

    /* ---------- ekspor ---------- */

    /**
     * Regresi: nol ditulis sebagai teks '0%', bukan angka mentah.
     * Excel membaca nol numerik dari CSV sebagai sel kosong, sehingga PJP
     * yang benar-benar berskor 0% tidak dapat dibedakan dari yang datanya
     * memang belum ada — dua keadaan yang justru paling penting dibedakan.
     */
    public function test_skor_nol_diekspor_sebagai_teks_bukan_angka(): void
    {
        $this->masuk();

        $baris = PjpEkspor::baris(Pjp::factory()->create());

        [, , , , , $persyaratan, $pelaporan, $evaluasi] = $baris;

        $this->assertSame('0%', $persyaratan);
        $this->assertIsString($persyaratan);
        $this->assertSame('Belum ada laporan', $pelaporan);
        $this->assertSame('Belum dievaluasi', $evaluasi);
    }

    public function test_judul_dan_baris_ekspor_sama_panjang(): void
    {
        $this->masuk();

        $this->assertCount(
            count(PjpEkspor::judul()),
            PjpEkspor::baris(Pjp::factory()->create()),
            'Judul kolom dan isinya harus sejajar, kalau tidak seluruh berkas bergeser.',
        );
    }

    public function test_ekspor_mengunduh_csv_mengikuti_penyaring(): void
    {
        $this->masuk();

        Pjp::factory()->create(['nama_perusahaan' => 'PT Ikut Saring', 'status' => 'aktif']);
        Pjp::factory()->create(['nama_perusahaan' => 'PT Tidak Ikut', 'status' => 'tidak_aktif']);

        $isi = $this->get(route('pjp.ekspor', ['status' => 'aktif']))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('PT Ikut Saring', $isi);
        $this->assertStringNotContainsString('PT Tidak Ikut', $isi);
    }

    /* ---------- lembar cetak ---------- */

    public function test_lembar_cetak_terbuka_dengan_kop_dokumen(): void
    {
        $this->masuk();
        $pjp = Pjp::factory()->create();

        $this->get(route('pjp.cetak', $pjp))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Print/Pjp')
                ->has('dok.nomor')
                ->where('pjp.nama_perusahaan', $pjp->nama_perusahaan));
    }

    /* ---------- batas perusahaan ---------- */

    public function test_pengguna_tidak_melihat_pjp_perusahaan_lain(): void
    {
        $a = Company::create(['name' => 'PT Pemegang Izin A', 'code' => 'PIA']);
        $b = Company::create(['name' => 'PT Pemegang Izin B', 'code' => 'PIB']);

        Pjp::factory()->create(['company_id' => $a->id, 'nama_perusahaan' => 'PJP milik A']);
        Pjp::factory()->create(['company_id' => $b->id, 'nama_perusahaan' => 'PJP milik B']);

        $this->masuk(['is_admin' => false, 'company_id' => $a->id]);

        $this->assertSame(['PJP milik A'], Pjp::pluck('nama_perusahaan')->all());
    }

    public function test_hitungan_status_ikut_terbatas_per_perusahaan(): void
    {
        $a = Company::create(['name' => 'PT Pemegang Izin A', 'code' => 'PIA']);
        $b = Company::create(['name' => 'PT Pemegang Izin B', 'code' => 'PIB']);

        Pjp::factory()->create(['company_id' => $a->id, 'status' => 'aktif']);
        Pjp::factory()->count(2)->create(['company_id' => $b->id, 'status' => 'aktif']);

        $this->masuk(['is_admin' => false, 'company_id' => $a->id]);

        $this->assertSame(1, Pjp::statusCountsFor()['aktif']);
    }

    /**
     * Dokumen dan evaluasi tidak punya company_id sendiri — batasnya
     * diwarisi dari PJP induknya lewat BerindukPerusahaan. Tanpa itu,
     * pengikatan model pada rute `{laporan}` menjangkau baris milik
     * perusahaan lain tanpa pernah menyentuh induknya.
     */
    public function test_dokumen_milik_perusahaan_lain_tidak_terjangkau(): void
    {
        $a = Company::create(['name' => 'PT Pemegang Izin A', 'code' => 'PIA']);
        $b = Company::create(['name' => 'PT Pemegang Izin B', 'code' => 'PIB']);

        $pjpB = Pjp::factory()->create(['company_id' => $b->id]);
        $laporanB = PjpLaporan::factory()->for($pjpB)->create();

        $this->masuk(['is_admin' => false, 'company_id' => $a->id]);

        $this->assertNull(PjpLaporan::find($laporanB->id));
        $this->assertSame(0, PjpEvaluasi::count());
    }

    public function test_pengguna_biasa_tidak_dapat_menulis_atas_nama_perusahaan_lain(): void
    {
        $a = Company::create(['name' => 'PT Pemegang Izin A', 'code' => 'PIA']);
        $b = Company::create(['name' => 'PT Pemegang Izin B', 'code' => 'PIB']);

        $this->masuk(['is_admin' => false, 'company_id' => $a->id]);

        // Formulir menyebut perusahaan B; server tetap memaksakan A.
        $this->post(route('pjp.simpan'), [
            'nama_perusahaan' => 'PT Coba Titip',
            'status' => 'aktif',
            'company_id' => $b->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('pjps', ['nama_perusahaan' => 'PT Coba Titip', 'company_id' => $a->id]);
    }
}
