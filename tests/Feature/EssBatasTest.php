<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Hr\{Absensi, Kontrak, SlipGaji, PeriodeGaji};
use App\Models\Miners\Pekerja;
use App\Models\User;
use App\Support\Hr\Ess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Batas data layanan mandiri: hanya baris SAYA.
 *
 * Seluruh berkas ini menguji apa yang TIDAK BOLEH terjadi, dan
 * kegagalannya punya bentuk yang sama: tidak ada galat, tidak ada
 * peringatan, hanya baris orang lain yang ikut terbaca — dengan nama
 * orang itu tercetak di atasnya, pada halaman yang berjudul "Slip Gaji
 * Saya".
 *
 * Yang paling mudah dilakukan seseorang adalah mengubah satu angka pada
 * bilah alamat. Itu tidak menuntut alat apa pun, tidak meninggalkan
 * jejak yang mencurigakan, dan pada `find($id)` polos — berhasil.
 */
class EssBatasTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private Company $lain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->c    = Company::create(['name' => 'PT Satu']);
        $this->lain = Company::create(['name' => 'PT Dua']);
    }

    private function orang(Company $c, string $nama, ?User $u = null): Pekerja
    {
        return Pekerja::withoutGlobalScopes()->create([
            'company_id'    => $c->id,
            'nama'          => $nama,
            'no_registrasi' => 'REG-'.uniqid(),
            'status'        => 'aktif',
            'user_id'       => $u?->id,
        ]);
    }

    private function absen(Pekerja $p, string $tanggal): Absensi
    {
        return Absensi::withoutGlobalScopes()->create([
            'company_id' => $p->company_id,
            'pekerja_id' => $p->id,
            'tanggal'    => $tanggal,
            'keadaan'    => 'hadir',
            'jam'        => 8,
        ]);
    }

    /* ═══════════════════ tautan akun ═══════════════════ */

    #[Test]
    public function test_akun_menemukan_pekerjanya(): void
    {
        $u = User::factory()->create(['company_id' => $this->c->id]);
        $p = $this->orang($this->c, 'Saya', $u);

        $this->assertSame($p->id, Ess::pekerja($u)?->id);
        $this->assertTrue(Ess::ada($u));
    }

    #[Test]
    public function test_akun_tanpa_pekerja_memulangkan_null(): void
    {
        // Keadaan yang sah dan sering: admin, auditor, dan pengawas
        // kantor memang bukan pekerja tambang mana pun.
        $u = User::factory()->create(['company_id' => $this->c->id, 'is_admin' => true]);

        $this->assertNull(Ess::pekerja($u));
        $this->assertFalse(Ess::ada($u));
    }

    #[Test]
    public function test_satu_akun_tidak_dapat_menunjuk_dua_pekerja(): void
    {
        $u = User::factory()->create(['company_id' => $this->c->id]);
        $this->orang($this->c, 'Pertama', $u);

        // Tanpa keunikan, `pekerja()` memilih sembarang dari beberapa
        // kemungkinan — dan yang membuka slip gajinya membaca slip
        // rekannya. Keunikannya ditegakkan basis data, bukan hanya
        // disepakati kode.
        $this->expectException(QueryException::class);

        $this->orang($this->c, 'Kedua', $u);
    }

    #[Test]
    public function test_beberapa_pekerja_boleh_tanpa_akun(): void
    {
        // Sebagian besar pekerja tambang tidak pernah punya akun
        // aplikasi. Keunikan yang ikut melarang NULL berulang akan
        // menolak pekerja kedua yang didaftarkan.
        $this->orang($this->c, 'Tanpa Akun A');
        $this->orang($this->c, 'Tanpa Akun B');

        $this->assertSame(2, Pekerja::withoutGlobalScopes()->whereNull('user_id')->count());
    }

    /* ═══════════════════ penyaringan ═══════════════════ */

    #[Test]
    public function test_hanya_baris_saya_yang_terbaca(): void
    {
        $u    = User::factory()->create(['company_id' => $this->c->id]);
        $saya = $this->orang($this->c, 'Saya', $u);
        $rekan = $this->orang($this->c, 'Rekan');

        $this->absen($saya, '2026-09-01');
        $this->absen($saya, '2026-09-02');
        $this->absen($rekan, '2026-09-01');

        $this->assertSame(2, Ess::milik(Absensi::query(), $saya)->count(),
            'Baris rekan sekerja ikut terbaca pada halaman "milik saya".');
    }

    #[Test]
    public function test_batas_perusahaan_ikut_dipasang(): void
    {
        $u    = User::factory()->create(['company_id' => $this->c->id]);
        $saya = $this->orang($this->c, 'Saya', $u);

        /* Baris perusahaan lain yang kebetulan ber-pekerja_id sama.
           Itu bukan kemungkinan yang dibuat-buat: id berulang antar
           perusahaan adalah keadaan biasa sesudah pemulihan data
           sebagian, dan `pekerja_id` saja meloloskannya. */
        Absensi::withoutGlobalScopes()->create([
            'company_id' => $this->lain->id,
            'pekerja_id' => $saya->id,
            'tanggal'    => '2026-09-01',
            'keadaan'    => 'hadir',
            'jam'        => 8,
        ]);

        $this->assertSame(0, Ess::milik(Absensi::query(), $saya)->count(),
            'Baris perusahaan lain lolos karena hanya pekerja_id yang disaring.');
    }

    #[Test]
    public function test_penyaringnya_tidak_bergantung_pada_lingkup_global(): void
    {
        $u    = User::factory()->create(['company_id' => $this->c->id]);
        $saya = $this->orang($this->c, 'Saya', $u);
        $rekan = $this->orang($this->c, 'Rekan');

        $this->absen($saya, '2026-09-01');
        $this->absen($rekan, '2026-09-01');

        // Dipanggil atas kueri yang lingkup globalnya SUDAH dilepas —
        // persis bentuk yang dipakai di beberapa tempat lain dalam
        // aplikasi ini. Penyaring yang menumpang lingkup global akan
        // meloloskan seluruh rekan sekerja di sini.
        $this->assertSame(1,
            Ess::milik(Absensi::withoutGlobalScopes(), $saya)->count(),
            'Penyaring milik-saya menumpang lingkup global.');
    }

    /* ═══════════════════ rincian per id ═══════════════════ */

    #[Test]
    public function test_id_milik_orang_lain_tidak_dapat_dibuka(): void
    {
        $u     = User::factory()->create(['company_id' => $this->c->id]);
        $saya  = $this->orang($this->c, 'Saya', $u);
        $rekan = $this->orang($this->c, 'Rekan');

        $punyaRekan = $this->absen($rekan, '2026-09-01');

        // Mengetik satu angka lain pada bilah alamat tidak menuntut
        // alat apa pun. `find($id)` polos berhasil; `satu()` harus
        // memulangkan null.
        $this->assertNull(Ess::satu(Absensi::query(), $saya, $punyaRekan->id),
            'Baris milik rekan terbuka dengan mengubah id di alamat.');
    }

    #[Test]
    public function test_id_milik_sendiri_tetap_dapat_dibuka(): void
    {
        $u    = User::factory()->create(['company_id' => $this->c->id]);
        $saya = $this->orang($this->c, 'Saya', $u);

        $punyaSaya = $this->absen($saya, '2026-09-01');

        $this->assertSame($punyaSaya->id, Ess::satu(Absensi::query(), $saya, $punyaSaya->id)?->id);
    }

    #[Test]
    public function test_slip_gaji_rekan_tidak_terbaca(): void
    {
        $u     = User::factory()->create(['company_id' => $this->c->id]);
        $saya  = $this->orang($this->c, 'Saya', $u);
        $rekan = $this->orang($this->c, 'Rekan');

        $periode = PeriodeGaji::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'tahun' => 2026, 'bulan' => 9, 'status' => 'terhitung',
        ]);

        $slipRekan = SlipGaji::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'periode_id' => $periode->id,
            'pekerja_id' => $rekan->id, 'pokok' => 9_000_000, 'neto' => 8_000_000,
        ]);

        // Slip gaji adalah kegagalan terburuk yang mungkin terjadi di
        // sini: ia memuat upah, potongan, dan status PTKP seseorang.
        $this->assertSame(0, Ess::milik(SlipGaji::query(), $saya)->count());
        $this->assertNull(Ess::satu(SlipGaji::query(), $saya, $slipRekan->id));
    }

    #[Test]
    public function test_kontrak_rekan_tidak_terbaca(): void
    {
        $u     = User::factory()->create(['company_id' => $this->c->id]);
        $saya  = $this->orang($this->c, 'Saya', $u);
        $rekan = $this->orang($this->c, 'Rekan');

        Kontrak::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'pekerja_id' => $rekan->id,
            'nomor' => 'PKWT/1', 'jenis' => 'pkwt_jangka', 'alasan' => 'tidak_lama',
            'mulai' => '2026-01-01', 'selesai' => '2026-12-31', 'status' => 'berjalan',
        ]);

        $this->assertSame(0, Ess::milik(Kontrak::query(), $saya)->count());
    }
}
