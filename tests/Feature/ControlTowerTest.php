<?php

namespace Tests\Feature;

use App\Models\{Company, MineOperationalRecord, MineOperationalTarget, User};
use App\Support\KelengkapanShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Ruang kendali operasi: kelengkapan data, ramalan, dan peringatan.
 *
 * Yang diuji terutama adalah hal yang membedakan ruang kendali dari
 * papan angka — apakah ia tahu angkanya boleh dipercaya, dan apakah
 * peringatannya menyebutkan tindakan.
 */
class ControlTowerTest extends TestCase
{
    use RefreshDatabase;

    private function baris(array $tanggalShift): Collection
    {
        return collect($tanggalShift)->map(fn ($ts) => (object) [
            'tanggal' => Carbon::parse($ts[0]),
            'shift'   => $ts[1],
            'status'  => $ts[2] ?? 'disetujui',
        ]);
    }

    private function kelengkapan(Collection $rows, string $kini): KelengkapanShift
    {
        return new KelengkapanShift(
            $rows,
            Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'),
            ['siang', 'malam'], Carbon::parse($kini),
        );
    }

    /* ---------- kelengkapan ---------- */

    public function test_hari_yang_belum_tiba_tidak_ikut_ditagih(): void
    {
        $k = $this->kelengkapan($this->baris([]), '2026-08-03');

        // Tiga hari berjalan × dua shift, bukan 31 hari × dua.
        $this->assertSame(6, $k->shiftWajib());
    }

    public function test_membedakan_belum_dilaporkan_dari_belum_ditinjau(): void
    {
        $k = $this->kelengkapan($this->baris([
            ['2026-08-01', 'siang'],
            ['2026-08-01', 'malam', 'draf'],     // dilaporkan, belum ditinjau
            ['2026-08-02', 'siang'],
            // 2 Agustus malam tidak ada laporannya sama sekali
        ]), '2026-08-02');

        $this->assertSame(4, $k->shiftWajib());
        $this->assertSame(3, $k->shiftTerlapor());
        $this->assertSame(2, $k->shiftDisetujui());
        $this->assertSame(1, $k->belumDilaporkan(), 'Ditagih ke pengawas lapangan.');
        $this->assertSame(1, $k->menungguTinjauan(), 'Ditagih ke peninjau.');
        $this->assertSame(50.0, $k->persen());
    }

    public function test_menyebutkan_shift_mana_yang_bolong(): void
    {
        $k = $this->kelengkapan($this->baris([
            ['2026-08-01', 'siang'],
            ['2026-08-02', 'siang'],
        ]), '2026-08-02');

        $this->assertSame([
            ['tanggal' => '2026-08-01', 'shift' => 'malam'],
            ['tanggal' => '2026-08-02', 'shift' => 'malam'],
        ], $k->daftarBolong());
    }

    public function test_periode_lengkap_seratus_persen(): void
    {
        $k = $this->kelengkapan($this->baris([
            ['2026-08-01', 'siang'], ['2026-08-01', 'malam'],
        ]), '2026-08-01');

        $this->assertSame(100.0, $k->persen());
        $this->assertSame(0, $k->belumDilaporkan());
        $this->assertSame([], $k->daftarBolong());
    }

    /* ---------- halaman ---------- */

    private function siapkan(): array
    {
        $company = Company::create(['name' => 'Tambang Uji']);
        $operator = User::factory()->create(['company_id' => $company->id]);
        $ktt = User::factory()->create(['company_id' => $company->id, 'lms_role' => 'ktt']);

        MineOperationalTarget::create([
            'company_id' => $company->id, 'tahun' => 2026, 'bulan' => 8,
            'target_produksi_ton' => 31000, 'target_overburden_bcm' => 62000,
        ]);

        return [$company, $operator, $ktt];
    }

    private function props(User $u): array
    {
        return $this->actingAs($u)
            ->get(route('operasi.index', ['dari' => '2026-08-01', 'sampai' => '2026-08-31']))
            ->assertOk()->viewData('page')['props'];
    }

    public function test_halaman_mengirim_ramalan_dan_kelengkapan(): void
    {
        [$company, $operator] = $this->siapkan();

        $props = $this->props($operator);

        $this->assertArrayHasKey('ramalan', $props);
        $this->assertArrayHasKey('kelengkapan', $props);
        $this->assertSame(31, $props['ramalan']['hariTotal']);
    }

    public function test_data_yang_belum_ditinjau_memunculkan_peringatan_tinjauan(): void
    {
        [$company, $operator] = $this->siapkan();

        MineOperationalRecord::create([
            'company_id' => $company->id, 'tanggal' => '2026-08-01', 'shift' => 'siang',
            'material' => 'Batubara', 'produksi_ton' => 1000, 'overburden_bcm' => 2000,
            'jarak_angkut_km' => 3, 'jam_operasi' => 10, 'jam_delay' => 1,
        ]);

        $kode = collect($this->props($operator)['alerts'])->pluck('kode');

        $this->assertTrue($kode->contains('shift-menunggu-tinjauan'),
            'Data yang tertahan di tinjauan harus ditagih.');
    }

    public function test_setiap_peringatan_menyebutkan_tindakan(): void
    {
        [$company, $operator] = $this->siapkan();

        foreach ($this->props($operator)['alerts'] as $a) {
            $this->assertArrayHasKey('kode', $a);
            $this->assertArrayHasKey('saran', $a);
            $this->assertNotEmpty($a['saran'],
                "Peringatan '{$a['kode']}' tidak menyebutkan apa yang harus dikerjakan.");
        }
    }

    public function test_tanpa_data_terhitung_peringatan_lain_tidak_ikut_berisik(): void
    {
        [$company, $operator] = $this->siapkan();

        $kode = collect($this->props($operator)['alerts'])->pluck('kode');

        $this->assertTrue($kode->contains('tanpa-data'));
        $this->assertFalse($kode->contains('capaian-produksi'),
            'Capaian tidak berarti apa-apa selama belum ada data.');
    }
}
