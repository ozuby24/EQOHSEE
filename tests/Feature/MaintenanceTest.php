<?php

namespace Tests\Feature;

use App\Models\{Company, KoObject, MineOperationalRecord, User, WorkOrder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Pusat Pemeliharaan dan Keandalan.
 *
 * Alat tidak didaftarkan ulang di modul ini; ia memakai registri
 * Keselamatan Operasi. Yang ditambahkan adalah catatan gangguan, dan
 * dari situlah MTBF, MTTR, ketersediaan, serta tunggakan dihitung.
 */
class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Tambang Uji']);
        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        Carbon::setTestNow('2026-08-31 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function alat(string $kode, string $kritikalitas = 'Tinggi', ?string $pmBerikutnya = null): KoObject
    {
        return KoObject::create([
            'company_id' => $this->company->id, 'kode' => $kode, 'nama' => "Unit {$kode}",
            'kategori' => 'Alat Angkut', 'kritikalitas' => $kritikalitas,
            'status_operasi' => 'Beroperasi', 'pm_berikutnya' => $pmBerikutnya,
        ]);
    }

    private function wo(array $ganti = []): WorkOrder
    {
        return WorkOrder::create(array_merge([
            'company_id' => $this->company->id, 'jenis' => 'korektif', 'prioritas' => 'sedang',
            'status' => 'selesai', 'gejala' => 'Overheat',
            'dilaporkan_pada' => '2026-08-01 06:00:00',
            'mulai_pada' => '2026-08-01 14:00:00',
            'selesai_pada' => '2026-08-01 18:00:00',
        ], $ganti));
    }

    private function props(string $rute = 'maintenance.index'): array
    {
        return $this->actingAs($this->user)
            ->get(route($rute, ['dari' => '2026-08-01', 'sampai' => '2026-08-31']))
            ->assertOk()->viewData('page')['props'];
    }

    /* ---------- memakai registri KO ---------- */

    public function test_alat_diambil_dari_registri_keselamatan_operasi(): void
    {
        $this->alat('DT-005');

        $opsi = $this->props()['objekOpsi'];

        $this->assertCount(1, $opsi);
        $this->assertSame('DT-005', $opsi[0]['kode'],
            'Modul ini tidak boleh punya registri alat sendiri.');
    }

    public function test_perintah_kerja_tertaut_ke_alat_registri(): void
    {
        $alat = $this->alat('EX-001');

        $this->actingAs($this->user)->post(route('maintenance.simpan'), [
            'ko_object_id' => $alat->id, 'jenis' => 'korektif', 'prioritas' => 'kritis',
            'gejala' => 'Bucket retak', 'dilaporkan_pada' => '2026-08-05 07:00:00',
        ])->assertSessionHasNoErrors();

        $this->assertSame($alat->id, WorkOrder::firstOrFail()->ko_object_id);
    }

    /* ---------- keandalan ---------- */

    public function test_waktu_menunggu_dipisahkan_dari_waktu_mengerjakan(): void
    {
        $this->alat('DT-001');
        $this->wo();   // lapor 06:00, mulai 14:00, selesai 18:00

        $k = $this->props()['keandalan'];

        $this->assertSame(12.0, $k['jamHenti'], 'Sejak berhenti berproduksi sampai jalan lagi.');
        $this->assertSame(4.0, $k['jamPerbaikan'], 'Lama pengerjaannya saja.');
        $this->assertSame(8.0, $k['jamMenunggu'], 'Sisanya habis menunggu — bukan salah bengkel.');
    }

    public function test_perawatan_berkala_tidak_dihitung_sebagai_kegagalan(): void
    {
        $this->alat('DT-001');
        $this->wo(['jenis' => 'preventif']);

        $k = $this->props()['keandalan'];

        $this->assertSame(0, $k['kegagalan'],
            'MTBF tidak boleh memburuk justru ketika perawatan dijalankan rajin.');
        $this->assertNull($k['mtbf']);
        $this->assertGreaterThan(0, $k['jamHenti'], 'Tetapi alatnya memang berhenti.');
    }

    public function test_ketersediaan_dihitung_terhadap_jumlah_unit(): void
    {
        $this->alat('DT-001');
        $this->alat('DT-002');
        $this->wo();   // 12 jam henti

        // 2 unit × 31 hari × 24 jam = 1.488 jam tersedia.
        $k = $this->props()['keandalan'];

        $this->assertSame(1488.0, $k['jamTersedia']);
        $this->assertEqualsWithDelta(99.19, $k['ketersediaan'], 0.05);
    }

    public function test_order_yang_belum_ditutup_terus_menghitung_waktu_henti(): void
    {
        $this->alat('DT-001');
        $this->wo([
            'status' => 'dibuka',
            'dilaporkan_pada' => '2026-08-31 00:00:00',
            'mulai_pada' => null, 'selesai_pada' => null,
        ]);

        $k = $this->props()['keandalan'];

        $this->assertEqualsWithDelta(12.0, $k['jamHenti'], 0.1,
            'Menghitungnya nol sampai ditutup membuat ketersediaan paling bagus justru saat tunggakan menumpuk.');
    }

    /* ---------- kepatuhan PM ---------- */

    public function test_kepatuhan_pm_dibaca_dari_registri_ko(): void
    {
        $this->alat('DT-001', 'Tinggi', '2026-09-30');   // belum lewat
        $this->alat('DT-002', 'Tinggi', '2026-08-01');   // sudah lewat
        $this->alat('DT-003');                            // tanpa jadwal

        $pm = $this->props()['pm'];

        $this->assertSame(2, $pm['berjadwal']);
        $this->assertSame(1, $pm['terlambat']);
        $this->assertSame(50.0, $pm['persen']);
        $this->assertSame(1, $pm['tanpaJadwal'], 'Yang tanpa jadwal dilaporkan terpisah.');
    }

    /* ---------- biaya ---------- */

    public function test_biaya_per_ton_hanya_memakai_produksi_yang_disetujui(): void
    {
        $this->alat('DT-001');
        $this->wo(['biaya' => 10_000_000]);

        $ktt = User::factory()->create(['company_id' => $this->company->id, 'lms_role' => 'ktt']);

        $disetujui = MineOperationalRecord::create([
            'company_id' => $this->company->id, 'tanggal' => '2026-08-10', 'shift' => 'siang',
            'material' => 'Batubara', 'produksi_ton' => 1000, 'overburden_bcm' => 2000,
            'jarak_angkut_km' => 3, 'jam_operasi' => 10, 'jam_delay' => 1,
        ]);
        $disetujui->ajukan($this->user);
        $disetujui->setujui($ktt);

        // Draf: tidak boleh ikut menurunkan biaya per ton.
        MineOperationalRecord::create([
            'company_id' => $this->company->id, 'tanggal' => '2026-08-11', 'shift' => 'siang',
            'material' => 'Batubara', 'produksi_ton' => 9000, 'overburden_bcm' => 2000,
            'jarak_angkut_km' => 3, 'jam_operasi' => 10, 'jam_delay' => 1,
        ]);

        $biaya = $this->props()['biaya'];

        $this->assertSame(1000.0, $biaya['tonDasar']);
        $this->assertSame(10_000.0, $biaya['perTon']);
    }

    public function test_suku_cadang_ikut_masuk_biaya(): void
    {
        $this->alat('DT-001');
        $wo = $this->wo(['biaya' => 1_000_000]);

        $this->actingAs($this->user)->post(route('maintenance.part', $wo), [
            'nama' => 'Filter oli', 'jumlah' => 4, 'satuan' => 'pcs', 'harga_satuan' => 250_000,
        ])->assertSessionHasNoErrors();

        $biaya = $this->props()['biaya'];

        $this->assertSame(2_000_000.0, $biaya['total']);
        $this->assertSame(1_000_000.0, $biaya['sukuCadang']);
    }

    /* ---------- perpindahan status ---------- */

    public function test_status_mencatat_stempel_waktunya_sendiri(): void
    {
        $this->alat('DT-001');
        $wo = $this->wo(['status' => 'dibuka', 'mulai_pada' => null, 'selesai_pada' => null]);

        $this->actingAs($this->user)->put(route('maintenance.status', $wo), ['status' => 'dikerjakan']);
        $this->assertNotNull($wo->fresh()->mulai_pada, 'Yang diketik terpisah akan kosong pada sebagian besar baris.');

        $this->actingAs($this->user)->put(route('maintenance.status', $wo), ['status' => 'selesai']);
        $this->assertNotNull($wo->fresh()->selesai_pada);
    }

    public function test_membuka_kembali_menghapus_jejak_penyelesaian(): void
    {
        $this->alat('DT-001');
        $wo = $this->wo();

        $this->actingAs($this->user)->put(route('maintenance.status', $wo), ['status' => 'dibuka']);

        $this->assertNull($wo->fresh()->selesai_pada,
            'Alat yang masih di bengkel tidak boleh terbaca sudah jalan.');
    }

    /* ---------- tunggakan & batas perusahaan ---------- */

    public function test_tunggakan_membedakan_prioritas_kritis(): void
    {
        $this->alat('DT-001');
        $this->wo(['status' => 'dibuka', 'prioritas' => 'kritis', 'selesai_pada' => null]);
        $this->wo(['status' => 'dibuka', 'prioritas' => 'rendah', 'selesai_pada' => null]);

        $t = $this->props()['tunggakan'];

        $this->assertSame(2, $t['jumlah']);
        $this->assertSame(1, $t['kritis']);
    }

    public function test_tidak_terlihat_oleh_perusahaan_lain(): void
    {
        $this->alat('DT-001');
        $this->wo();

        $lain = User::factory()->create(['company_id' => Company::create(['name' => 'Lain'])->id]);

        $props = $this->actingAs($lain)->get(route('maintenance.index'))
            ->assertOk()->viewData('page')['props'];

        $this->assertCount(0, $props['orders']);
    }
}
