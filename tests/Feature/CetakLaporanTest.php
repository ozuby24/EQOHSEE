<?php

namespace Tests\Feature;

use App\Models\{Company, MineOperationalRecord, MineOperationalTarget, MinerbaConservationRecord, TindakLanjut, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Laporan siap cetak untuk Operasi dan Konservasi.
 *
 * Yang paling penting diuji bukan tata letaknya melainkan isinya: laporan
 * tidak boleh memuat angka yang belum ditinjau. Laporan diedarkan,
 * dibubuhi tanda tangan, dan dikutip pada rapat, sementara draf masih
 * dapat berubah tanpa jejak — yang beredar kemudian tidak lagi cocok
 * dengan yang tersimpan, dan tidak seorang pun tahu mana yang benar.
 */
class CetakLaporanTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $operator;
    private User $ktt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create(['name' => 'Tambang Uji']);
        $this->operator = User::factory()->create(['company_id' => $this->company->id]);
        $this->ktt = User::factory()->create(['company_id' => $this->company->id, 'lms_role' => 'ktt']);
    }

    private function shift(string $tanggal, string $shift, float $ton, bool $setujui): MineOperationalRecord
    {
        $r = MineOperationalRecord::create([
            'company_id' => $this->company->id, 'tanggal' => $tanggal, 'shift' => $shift,
            'pit' => 'Pit A', 'material' => 'Batubara', 'produksi_ton' => $ton,
            'overburden_bcm' => $ton * 2, 'jarak_angkut_km' => 3,
            'jam_operasi' => 10, 'jam_delay' => 1,
        ]);

        if ($setujui) {
            $r->ajukan($this->operator);
            $r->setujui($this->ktt);
        }

        return $r;
    }

    /* ---------- Operasi ---------- */

    public function test_laporan_operasi_hanya_memuat_yang_disetujui(): void
    {
        $this->shift('2026-08-01', 'siang', 1000, true);
        $this->shift('2026-08-01', 'malam', 9999, false);   // draf, tidak boleh terhitung

        $this->actingAs($this->operator)
            ->get(route('operasi.cetak', ['dari' => '2026-08-01', 'sampai' => '2026-08-31']))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Print/Operasi')
                ->where('ringkas.produksi', 1000)
                ->where('ringkas.jumlah_shift', 1)
                ->has('records', 1));
    }

    public function test_laporan_operasi_menyebutkan_dasar_penyusunannya(): void
    {
        $this->shift('2026-08-01', 'siang', 1000, true);
        $this->shift('2026-08-01', 'malam', 500, false);

        $props = $this->actingAs($this->operator)
            ->get(route('operasi.cetak', ['dari' => '2026-08-01', 'sampai' => '2026-08-01']))
            ->assertOk()->viewData('page')['props'];

        $this->assertSame(2, $props['kelengkapan']['shiftWajib']);
        $this->assertSame(1, $props['kelengkapan']['shiftDisetujui']);
        $this->assertSame(1, $props['kelengkapan']['menungguTinjauan'] + $props['kelengkapan']['belumDilaporkan'],
            'Laporan harus mengaku berapa yang tidak ikut dihitung.');
    }

    public function test_laporan_operasi_membawa_kop_dokumen_terkendali(): void
    {
        $this->actingAs($this->operator)->get(route('operasi.cetak'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Print/Operasi')
                ->where('dok.jenis', 'LAPORAN')
                ->where('dok.judul', 'LAPORAN KINERJA OPERASI PENAMBANGAN')
                ->has('dok.nomor')->has('dok.revisi')
                ->has('dok.divisi')->has('dok.departemen'));
    }

    public function test_laporan_operasi_memuat_tindak_lanjut_yang_masih_terbuka(): void
    {
        TindakLanjut::create([
            'company_id' => $this->company->id, 'modul' => 'operasi',
            'judul' => 'Turunkan delay pemuatan', 'prioritas' => 'tinggi', 'status' => 'berjalan',
        ]);
        TindakLanjut::create([
            'company_id' => $this->company->id, 'modul' => 'operasi',
            'judul' => 'Sudah ditutup', 'prioritas' => 'rendah', 'status' => 'selesai',
        ]);

        $this->actingAs($this->operator)->get(route('operasi.cetak'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Print/Operasi')->has('tindak', 1));
    }

    public function test_target_ikut_terbaca_pada_laporan_operasi(): void
    {
        MineOperationalTarget::create([
            'company_id' => $this->company->id, 'tahun' => 2026, 'bulan' => 8,
            'target_produksi_ton' => 2000, 'target_overburden_bcm' => 4000,
        ]);
        $this->shift('2026-08-05', 'siang', 1000, true);

        $this->actingAs($this->operator)
            ->get(route('operasi.cetak', ['dari' => '2026-08-01', 'sampai' => '2026-08-31']))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('ringkas.target_produksi', 2000)
                ->where('ringkas.capaian_produksi', 50));
    }

    /* ---------- Konservasi ---------- */

    private function catatan(float $aktual, bool $setujui): MinerbaConservationRecord
    {
        $r = MinerbaConservationRecord::create([
            'company_id' => $this->company->id, 'periode' => '2026-03-31',
            'lokasi' => 'Pit A', 'komoditas' => 'Batubara', 'satuan' => 'ton',
            'target_produksi' => 1000, 'produksi_aktual' => $aktual, 'material_digali' => 1200,
            'recovery_percent' => 80, 'kehilangan_material' => 40, 'dilusi' => 20, 'stok_akhir' => 100,
        ]);

        if ($setujui) {
            $r->ajukan($this->operator);
            $r->setujui($this->ktt);
        }

        return $r;
    }

    public function test_laporan_konservasi_hanya_memuat_yang_disetujui(): void
    {
        $this->catatan(900, true);
        $this->catatan(5000, false);   // draf

        $this->actingAs($this->operator)->get(route('konservasi.cetak', ['tahun' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Print/Konservasi')
                ->where('ringkas.produksi_aktual', 900)
                ->where('ringkas.jumlah_record', 1)
                ->where('ringkas.belum_ditinjau', 1)
                ->has('records', 1));
    }

    public function test_laporan_konservasi_membawa_kop_dokumen_terkendali(): void
    {
        $this->actingAs($this->operator)->get(route('konservasi.cetak'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Print/Konservasi')
                ->where('dok.jenis', 'LAPORAN')
                ->where('dok.judul', 'LAPORAN KONSERVASI MINERAL DAN BATUBARA')
                ->has('dok.nomor'));
    }

    public function test_laporan_konservasi_menghitung_recovery_dari_yang_disetujui(): void
    {
        $this->catatan(900, true);      // 900 dari 1.200 tergali = 75 %

        $this->actingAs($this->operator)->get(route('konservasi.cetak', ['tahun' => 2026]))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('ringkas.recovery', 75)
                ->where('ringkas.porsi_kehilangan', fn ($v) => abs((float) $v - 40 / 1200 * 100) < 0.001));
    }

    /* ---------- batas per perusahaan ---------- */

    public function test_laporan_tidak_memuat_data_perusahaan_lain(): void
    {
        $this->shift('2026-08-01', 'siang', 1000, true);

        $lain = Company::create(['name' => 'Tambang Lain']);
        $orangLain = User::factory()->create(['company_id' => $lain->id]);

        $this->actingAs($orangLain)
            ->get(route('operasi.cetak', ['dari' => '2026-08-01', 'sampai' => '2026-08-31']))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('ringkas.produksi', 0)->has('records', 0));
    }

    public function test_laporan_menuntut_login(): void
    {
        $this->get(route('operasi.cetak'))->assertRedirect();
        $this->get(route('konservasi.cetak'))->assertRedirect();
    }
}
