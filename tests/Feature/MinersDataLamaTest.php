<?php

namespace Tests\Feature;

use App\Models\Miners\Kendaraan;
use App\Models\Miners\Pekerja;
use App\Models\Miners\Permit;
use App\Models\Miners\Simper;
use App\Models\Miners\SimperUnit;
use App\Support\Miners\DataLama;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\SiapanMiners;
use Tests\TestCase;

/**
 * Upload Data Lama (DataLamaMinePermitImport / DataLamaSimperImport Safe
 * Track): templat, pratinjau tanpa menyimpan, laporan per baris, dan
 * penanda asal.
 */
class MinersDataLamaTest extends TestCase
{
    use RefreshDatabase;
    use SiapanMiners;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkan();
    }

    /** @param list<list<string>> $baris */
    private function excel(string $jenis, array $baris): UploadedFile
    {
        $b = new Spreadsheet();
        $l = $b->getActiveSheet();
        $l->fromArray(DataLama::KOLOM[$jenis], null, 'A1');
        foreach ($baris as $i => $isi) {
            foreach ($isi as $k => $v) $l->setCellValueExplicit([$k + 1, $i + 2], $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
        $jalur = tempnam(sys_get_temp_dir(), 'dl').'.xlsx';
        (new Xlsx($b))->save($jalur);

        return new UploadedFile($jalur, 'data.xlsx', null, null, true);
    }

    private function barisPermit(string $nik, string $pt = 'PT Mitra Uji'): array
    {
        return [$nik, 'Pekerja '.$nik, 'Operator', 'Produksi', $pt, 'O', '0800', '2026-01-15', '2030-01-15', 'FULL', ''];
    }

    #[Test]
    public function hanya_admin_dan_hse_iup(): void
    {
        $this->actingAs($this->ohse)->get('/miners/data-lama')->assertOk();
        $this->actingAs($this->ohse)->get('/miners/data-lama/templat/permit')->assertOk();
        $this->actingAs($this->pengaju)->get('/miners/data-lama')->assertForbidden();
        $this->actingAs($this->pengaju)->post('/miners/data-lama/permit', ['berkas' => $this->excel('permit', [$this->barisPermit('1111')])])
            ->assertForbidden();
    }

    #[Test]
    public function pratinjau_tidak_menyimpan(): void
    {
        $this->actingAs($this->ohse)->from('/miners/data-lama')
            ->post('/miners/data-lama/permit', ['berkas' => $this->excel('permit', [$this->barisPermit('1111')]), 'pratinjau' => '1'])
            ->assertRedirect('/miners/data-lama')
            ->assertSessionHas('dataLama', fn ($h) => $h['pratinjau'] && count($h['berhasil']) === 1);

        $this->assertFalse(Pekerja::withoutGlobalScopes()->where('nik', '1111')->exists());
    }

    #[Test]
    public function permit_lama_membuat_rantai_lengkap_bertanda_asal(): void
    {
        $this->actingAs($this->ohse)->from('/miners/data-lama')->post('/miners/data-lama/permit', ['berkas' => $this->excel('permit', [
            $this->barisPermit('1111'),
            $this->barisPermit('1111'),                    // dobel dalam satu berkas
            $this->barisPermit('2222', 'PT Tidak Ada'),    // perusahaan tak dikenal
            ['', 'Tanpa NIK'],
        ])])->assertSessionHas('dataLama', function ($h) {
            $sebab = collect($h['gagal'])->pluck('sebab', 'baris');

            return count($h['berhasil']) === 1 && $h['berhasil'][0]['baris'] === 2
                && str_contains($sebab[3], 'sudah ada') && str_contains($sebab[4], 'tidak ditemukan') && str_contains($sebab[5], 'wajib');
        });

        $pk = Pekerja::withoutGlobalScopes()->where('nik', '1111')->firstOrFail();
        $this->assertSame(DataLama::ASAL, $pk->asal);
        $p = Permit::withoutGlobalScopes()->where('pekerja_id', $pk->id)->firstOrFail();
        $this->assertSame('terbit', $p->status);
        $this->assertSame(DataLama::ASAL, $p->asal);
        $this->assertSame('2030-01-15', $p->habisEfektif()?->toDateString());
        $this->assertSame($this->iup->id, (int) $p->iup_id);
        $this->assertTrue($p->mcuOrang->hasil->layak);
        $this->assertSame('lulus', $p->induksiOrang->status);
    }

    #[Test]
    public function simper_lama_membuat_simper_dan_unit(): void
    {
        Kendaraan::withoutGlobalScopes()->create(['nama' => 'Excavator', 'kode' => 'EX', 'aktif' => true]);
        $this->actingAs($this->ohse)->post('/miners/data-lama/permit', ['berkas' => $this->excel('permit', [$this->barisPermit('1111')])]);

        $this->actingAs($this->ohse)->from('/miners/data-lama')->post('/miners/data-lama/simper', ['berkas' => $this->excel('simper', [
            ['1111', 'SIM-9', '2029-03-20', 'Excavator', 'F'],
            ['1111', '', '', 'Excavator', 'F'],      // sudah tercatat
            ['1111', '', '', 'Excavator', 'Z'],      // kelas salah
            ['9999', '', '', '', ''],                // belum punya permit
        ])])->assertSessionHas('dataLama', fn ($h) => count($h['berhasil']) === 1 && count($h['gagal']) === 3);

        $s = Simper::withoutGlobalScopes()->where('no_simpol', 'SIM-9')->firstOrFail();
        $this->assertSame('2029-03-20', $s->habisEfektif()?->toDateString());
        $this->assertSame(DataLama::ASAL, $s->asal);
        $this->assertSame(1, SimperUnit::withoutGlobalScopes()->where('simper_id', $s->id)->count());
    }
}
