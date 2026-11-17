<?php

namespace Tests\Feature;

use App\Models\{Pjp, PjpLaporan, User};
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Ketepatan waktu, jendela triwulan, dan skor kepatuhan pelaporan.
 */
class PjpPelaporanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /* ---------- ketepatan waktu ---------- */

    public function test_diunggah_pada_tanggal_batas_masih_tepat_waktu(): void
    {
        $laporan = PjpLaporan::factory()->for(Pjp::factory())->create([
            'created_at' => Carbon::parse('2026-09-03'),
        ]);

        $this->assertTrue($laporan->tepat_waktu);
    }

    public function test_diunggah_sehari_setelah_batas_sudah_terlambat(): void
    {
        $laporan = PjpLaporan::factory()->for(Pjp::factory())->create([
            'created_at' => Carbon::parse('2026-09-04'),
        ]);

        $this->assertFalse($laporan->tepat_waktu);
    }

    /**
     * Aplikasi menyimpan waktu dalam UTC sementara aturan ini menilai
     * tanggal kalender di lokasi tambang (WITA, +08). Tanpa pemindahan
     * zona, kedua batas di bawah jatuh ke tanggal yang salah — dan
     * keduanya gagal tanpa galat, hanya sebagai lencana hijau atau merah
     * yang tampak wajar.
     */
    public function test_ketepatan_waktu_dinilai_menurut_zona_setempat(): void
    {
        config(['waktu.zona' => 'Asia/Makassar']);

        // 3 September 23.00 WITA = 3 Sep 15.00 UTC — masih tanggal 3 setempat.
        $masihTanggalTiga = PjpLaporan::factory()->for(Pjp::factory())->create([
            'created_at' => Carbon::parse('2026-09-03 15:00:00', 'UTC'),
        ]);
        $this->assertTrue($masihTanggalTiga->tepat_waktu);

        // 4 September 06.00 WITA = 3 Sep 22.00 UTC — sudah tanggal 4 setempat.
        $sudahTanggalEmpat = PjpLaporan::factory()->for(Pjp::factory())->create([
            'created_at' => Carbon::parse('2026-09-03 22:00:00', 'UTC'),
        ]);
        $this->assertFalse(
            $sudahTanggalEmpat->tepat_waktu,
            'Tanggal UTC yang masih 3 tidak boleh menutupi tanggal setempat yang sudah 4.',
        );
    }

    /* ---------- jendela triwulan ---------- */

    public function test_triwulan_hanya_terbuka_pada_januari_april_juli_oktober(): void
    {
        foreach (['2026-04-15', '2026-07-01', '2026-10-31', '2027-01-01'] as $tanggal) {
            $this->assertTrue(PjpLaporan::triwulanSedangDibuka(Carbon::parse($tanggal)), $tanggal);
        }

        foreach (['2026-09-08', '2026-03-31', '2026-12-25'] as $tanggal) {
            $this->assertFalse(PjpLaporan::triwulanSedangDibuka(Carbon::parse($tanggal)), $tanggal);
        }
    }

    public function test_unggah_triwulan_ditolak_di_luar_bulan_yang_dibuka(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-08'));

        $pjp = Pjp::factory()->create();

        $this->post(route('pjp.laporan.simpan', $pjp), [
            'jenis' => 'laporan_triwulan',
            'file'  => UploadedFile::fake()->create('laporan.pdf', 100),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('pjp_laporans', 0);
    }

    public function test_unggah_triwulan_diterima_saat_bulan_dibuka(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-05'));

        $pjp = Pjp::factory()->create();

        $this->post(route('pjp.laporan.simpan', $pjp), [
            'jenis' => 'laporan_triwulan',
            'file'  => UploadedFile::fake()->create('laporan.pdf', 100),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('pjp_laporans', 1);
    }

    /* ---------- skor kepatuhan ---------- */

    public function test_null_saat_belum_pernah_mengunggah_apa_pun(): void
    {
        // Bukan 0: belum ada datanya berbeda dari sudah diperiksa dan gagal.
        $this->assertNull(Pjp::factory()->create()->pelaporanScore());
    }

    public function test_hanya_memakai_rate_tepat_waktu_saat_belum_ada_yang_dinilai(): void
    {
        $pjp = Pjp::factory()->create();

        PjpLaporan::factory()->for($pjp)->create(['created_at' => now()->startOfMonth()->addDay()]);
        PjpLaporan::factory()->for($pjp)->create(['created_at' => now()->startOfMonth()->addDays(10)]);

        // 1 dari 2 tepat waktu; tidak ada yang dinilai isinya.
        $this->assertSame(50.0, $pjp->pelaporanScore());
    }

    public function test_rata_rata_tepat_waktu_dan_kesesuaian_saat_sudah_dinilai(): void
    {
        $pjp = Pjp::factory()->create();

        PjpLaporan::factory()->for($pjp)->create([
            'created_at' => now()->startOfMonth()->addDay(), 'kesesuaian_isi' => 'sesuai',
        ]);
        PjpLaporan::factory()->for($pjp)->create([
            'created_at' => now()->startOfMonth()->addDay(), 'kesesuaian_isi' => 'tidak_sesuai',
        ]);

        // Tepat waktu 2/2 = 100; sesuai 1/2 = 50; rata-rata 75.
        $this->assertSame(75.0, $pjp->pelaporanScore());
    }

    public function test_dokumen_yang_belum_dinilai_tidak_ikut_rate_kesesuaian(): void
    {
        $pjp = Pjp::factory()->create();

        PjpLaporan::factory()->for($pjp)->create([
            'created_at' => now()->startOfMonth()->addDay(), 'kesesuaian_isi' => 'sesuai',
        ]);
        PjpLaporan::factory()->for($pjp)->create([
            'created_at' => now()->startOfMonth()->addDay(), 'kesesuaian_isi' => null,
        ]);

        // Tepat waktu 2/2 = 100; kesesuaian hanya dari 1 yang dinilai = 100.
        $this->assertSame(100.0, $pjp->pelaporanScore());
    }

    /* ---------- kepemilikan dokumen ---------- */

    public function test_dokumen_milik_pjp_lain_tidak_dapat_dinilai_atau_dihapus(): void
    {
        $pjp  = Pjp::factory()->create();
        $lain = Pjp::factory()->create();

        $laporan = PjpLaporan::factory()->for($lain)->create();

        $this->patch(route('pjp.laporan.nilai', ['pjp' => $pjp->id, 'laporan' => $laporan->id]),
            ['kesesuaian_isi' => 'sesuai'])->assertNotFound();

        $this->delete(route('pjp.laporan.hapus', ['pjp' => $pjp->id, 'laporan' => $laporan->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('pjp_laporans', ['id' => $laporan->id, 'kesesuaian_isi' => null]);
    }

    /* ---------- tunggakan bulanan ---------- */

    public function test_tunggakan_kosong_sebelum_tanggal_batas_terlewati(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-02'));

        Pjp::factory()->create();

        $this->assertCount(0, Pjp::belumLaporanBulananBulanIni());
    }

    public function test_yang_belum_melapor_muncul_setelah_tanggal_batas(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10'));

        $belum = Pjp::factory()->create(['nama_perusahaan' => 'PT Belum Lapor']);
        $nonaktif = Pjp::factory()->create(['nama_perusahaan' => 'PT Nonaktif', 'status' => 'tidak_aktif']);

        $sudah = Pjp::factory()->create(['nama_perusahaan' => 'PT Sudah Lapor']);
        PjpLaporan::factory()->for($sudah)->create([
            'jenis' => 'laporan_bulanan',
            'created_at' => Carbon::parse('2026-09-02'),
        ]);

        $nama = Pjp::belumLaporanBulananBulanIni()->pluck('nama_perusahaan')->all();

        $this->assertContains($belum->nama_perusahaan, $nama);
        $this->assertNotContains($sudah->nama_perusahaan, $nama, 'Yang sudah melapor tepat waktu tidak menunggak.');
        $this->assertNotContains($nonaktif->nama_perusahaan, $nama, 'PJP tidak aktif tidak ikut ditagih.');
    }

    public function test_melapor_lewat_tanggal_batas_tetap_terhitung_menunggak(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-10'));

        $telat = Pjp::factory()->create(['nama_perusahaan' => 'PT Telat Lapor']);
        PjpLaporan::factory()->for($telat)->create([
            'jenis' => 'laporan_bulanan',
            'created_at' => Carbon::parse('2026-09-08'),
        ]);

        $this->assertContains(
            $telat->nama_perusahaan,
            Pjp::belumLaporanBulananBulanIni()->pluck('nama_perusahaan')->all(),
        );
    }
}
