<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Hr\Kontrak;
use App\Models\Miners\Pekerja;
use App\Models\User;
use App\Support\Perusahaan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pemilih perusahaan di bilah atas.
 *
 * YANG DIUJI DI SINI TERUTAMA ADALAH APA YANG TIDAK BOLEH TERJADI.
 * Pemilihnya hanya menyempitkan — administrator memang sudah
 * menjangkau seluruh perusahaan — sehingga satu-satunya cara ia dapat
 * membahayakan adalah bila nilai di sesi ikut dihormati bagi orang yang
 * bukan administrator.
 *
 * Itu bukan kemungkinan yang dibuat-buat: sesi diwarisi peramban, dan
 * seorang admin yang masuk lalu keluar di komputer bersama meninggalkan
 * nilai itu di sana. Menyembunyikan tombolnya saja tidak menahan apa
 * pun — yang menahan adalah lingkup datanya, dan karena itu ia
 * memeriksa perannya sendiri alih-alih memercayai pemeriksaan di
 * tempat lain.
 */
class PerusahaanDilihatTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;
    private Company $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Company::create(['name' => 'PT Alfa']);
        $this->b = Company::create(['name' => 'PT Beta']);
    }

    private function pekerja(Company $c, string $nama): Pekerja
    {
        return Pekerja::withoutGlobalScopes()->create([
            'company_id'    => $c->id,
            'nama'          => $nama,
            'no_registrasi' => 'REG-'.uniqid(),
            'status'        => 'aktif',
        ]);
    }

    #[Test]
    public function test_admin_yang_memilih_satu_perusahaan_hanya_melihat_perusahaan_itu(): void
    {
        $this->pekerja($this->a, 'Orang Alfa');
        $this->pekerja($this->b, 'Orang Beta');

        $admin = User::factory()->create(['company_id' => $this->a->id, 'is_admin' => true]);

        $this->actingAs($admin);

        // Tanpa memilih: seluruhnya.
        $this->assertSame(2, Pekerja::count(), 'Administrator tidak melihat seluruh perusahaan.');

        $this->assertNull(Perusahaan::pilih($this->b->id, $admin));

        $terlihat = Pekerja::pluck('nama')->all();

        $this->assertSame(['Orang Beta'], $terlihat,
            'Memilih satu perusahaan tidak menyempitkan datanya.');
    }

    #[Test]
    public function test_kembali_ke_semua_perusahaan_memulihkan_jangkauannya(): void
    {
        $this->pekerja($this->a, 'Orang Alfa');
        $this->pekerja($this->b, 'Orang Beta');

        $admin = User::factory()->create(['company_id' => $this->a->id, 'is_admin' => true]);
        $this->actingAs($admin);

        Perusahaan::pilih($this->b->id, $admin);
        $this->assertSame(1, Pekerja::count());

        Perusahaan::pilih(null, $admin);

        $this->assertSame(2, Pekerja::count(),
            'Kembali ke "semua perusahaan" tidak memulihkan jangkauannya.');
    }

    #[Test]
    public function test_pengguna_biasa_tidak_dapat_berpindah_perusahaan(): void
    {
        $orang = User::factory()->create(['company_id' => $this->a->id]);

        $this->assertNotNull(Perusahaan::pilih($this->b->id, $orang),
            'Pengguna biasa diizinkan berpindah perusahaan.');
    }

    #[Test]
    public function test_nilai_sesi_yang_tertinggal_tidak_bekerja_bagi_pengguna_biasa(): void
    {
        $this->pekerja($this->a, 'Orang Alfa');
        $this->pekerja($this->b, 'Orang Beta');

        // Sesi yang ditinggalkan admin di peramban bersama. Ditulis
        // langsung, melewati Perusahaan::pilih — persis seperti yang
        // terjadi ketika akunnya berganti tanpa sesinya diperbarui.
        session([Perusahaan::KUNCI => $this->b->id]);

        $orang = User::factory()->create(['company_id' => $this->a->id]);
        $this->actingAs($orang);

        $terlihat = Pekerja::pluck('nama')->all();

        $this->assertSame(['Orang Alfa'], $terlihat,
            'Nilai sesi yang tertinggal memperlihatkan data perusahaan lain.');

        $this->assertNull(Perusahaan::terpilih($orang),
            'Pengguna biasa memulangkan perusahaan terpilih dari sesi.');
    }

    #[Test]
    public function test_perusahaan_yang_tidak_dikenal_ditolak(): void
    {
        $admin = User::factory()->create(['company_id' => $this->a->id, 'is_admin' => true]);
        $this->actingAs($admin);

        // Disimpan apa adanya, id yang tidak ada menyaring tiap halaman
        // menjadi kosong — dan yang terbaca adalah "belum ada data",
        // bukan "pilihannya salah".
        $this->assertNotNull(Perusahaan::pilih(999999, $admin));

        $this->assertNull(Perusahaan::terpilih($admin));
    }

    #[Test]
    public function test_pengguna_biasa_tidak_mendapat_daftar_pilihan(): void
    {
        $orang = User::factory()->create(['company_id' => $this->a->id]);

        // Daftar berisi satu akan menggambar pemilih yang dapat dibuka
        // dan tidak dapat mengubah apa pun.
        $this->assertSame([], Perusahaan::dapatDipilih($orang));

        $admin = User::factory()->create(['company_id' => $this->a->id, 'is_admin' => true]);

        $this->assertCount(2, Perusahaan::dapatDipilih($admin));
    }

    #[Test]
    public function test_rute_perpindahan_menolak_pengguna_biasa(): void
    {
        $orang = User::factory()->create(['company_id' => $this->a->id]);

        $this->actingAs($orang)
            ->post('/perusahaan-dilihat', ['perusahaan' => $this->b->id])
            ->assertSessionHasErrors('perusahaan');

        $this->assertNull(session(Perusahaan::KUNCI),
            'Rutenya menyimpan pilihan meski penggunanya ditolak.');
    }

    #[Test]
    public function test_baris_tanpa_perusahaan_tetap_terlihat_saat_menyempitkan(): void
    {
        // Baris ber-company_id NULL adalah dokumen induk dan standar
        // bersama. Ikut tersaring, modul yang seluruh barisnya masih
        // NULL akan tampak KOSONG — kegagalan diam yang pernah terjadi
        // sebelumnya pada Energi, Gudang, dan Dokumen.
        Pekerja::withoutGlobalScopes()->create([
            'company_id'    => null,
            'nama'          => 'Tanpa Perusahaan',
            'no_registrasi' => 'REG-NULL',
            'status'        => 'aktif',
        ]);

        $this->pekerja($this->b, 'Orang Beta');

        $admin = User::factory()->create(['company_id' => $this->a->id, 'is_admin' => true]);
        $this->actingAs($admin);

        Perusahaan::pilih($this->b->id, $admin);

        $this->assertEqualsCanonicalizing(['Tanpa Perusahaan', 'Orang Beta'],
            Pekerja::pluck('nama')->all(),
            'Baris tanpa perusahaan ikut hilang saat pandangan disempitkan.');
    }
}
