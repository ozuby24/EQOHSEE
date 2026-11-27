<?php

namespace Tests\Feature;

use App\Models\SmkpAudit;
use App\Support\SmkpSampel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/**
 * Kalkulator jumlah sampel Rencana Audit (port DBEST #23).
 */
class SmkpSampelTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    public function test_jumlah_sampel_tiap_cara(): void
    {
        $this->assertSame(55, SmkpSampel::hitungSampel('slovin', 120, 10), '120 ÷ (1 + 120 × 0,01) = 54,5 → 55.');
        $this->assertSame(286, SmkpSampel::hitungSampel('slovin', 1000, 5));
        $this->assertSame(55, SmkpSampel::hitungSampel('slovin', 120), 'e bawaan 10%.');
        $this->assertSame(11, SmkpSampel::hitungSampel('akar', 120));
        $this->assertSame(11, SmkpSampel::hitungSampel('akar', 121));
        $this->assertSame(1, SmkpSampel::hitungSampel('akar', 1));
        $this->assertSame(3, SmkpSampel::hitungSampel('triangulasi', 50));
        $this->assertSame(2, SmkpSampel::hitungSampel('triangulasi', 2), 'Tidak pernah melebihi populasi.');
        $this->assertSame(7, SmkpSampel::hitungSampel('satu', 7));
        $this->assertSame(40, SmkpSampel::hitungSampel('sensus', 40));
        $this->assertSame(12, SmkpSampel::hitungSampel('persen', 120, 10));
        $this->assertSame(13, SmkpSampel::hitungSampel('persen', 125, 10), '12,5 dibulatkan ke atas.');
        $this->assertSame(120, SmkpSampel::hitungSampel('manual', 120, 200), 'Ditetapkan auditor dijepit ke N.');

        $this->assertNull(SmkpSampel::hitungSampel('manual', 120, null));
        $this->assertNull(SmkpSampel::hitungSampel('slovin', 0));
        $this->assertNull(SmkpSampel::hitungSampel('ngawur', 100));
    }

    public function test_baris_baku_dan_pembacaannya(): void
    {
        $this->assertSame('Jumlah sampel: 55 dari populasi 120 manpower (Slovin, e 10%)', SmkpSampel::barisJumlah('slovin', 120, 'manpower', 10));
        $this->assertSame('Jumlah sampel: 10 dari populasi 128 unit HD (persentase 7,5%)', SmkpSampel::barisJumlah('persen', 128, 'unit (HD)', '7,5'), 'Kurung pada objek diganti spasi.');

        $j = SmkpSampel::bacaJumlah('Jumlah sampel: 55 dari populasi 120 manpower (Slovin, e 10%)');
        $this->assertSame(['n' => 55, 'populasi' => 120, 'objek' => 'manpower', 'cara' => 'slovin', 'param' => '10'], $j);
        $this->assertNull(SmkpSampel::bacaJumlah('Daftar hadir sosialisasi IBPR'));
    }

    /** Angka yang tidak sesuai caranya dibetulkan; yang ditetapkan auditor dibiarkan. */
    public function test_normalisasi_membetulkan_angka_yang_tidak_sesuai(): void
    {
        $this->assertSame('Jumlah sampel: 55 dari populasi 120 manpower (Slovin, e 10%)',
            SmkpSampel::normalJumlah('jumlah sampel 40 dari populasi 120 manpower (slovin, e 10%)'));
        $this->assertSame('Jumlah sampel: 40 dari populasi 120 dokumen (ditetapkan auditor)',
            SmkpSampel::normalJumlah('Jumlah sampel: 40 dari populasi 120 dokumen (ditetapkan auditor)'));

        $teks = "Purposive Sampling\n- Jumlah sampel: 9 dari populasi 120 unit (best practice √N)\nWawancara 2 orang:";
        $this->assertSame("Purposive Sampling\n- Jumlah sampel: 11 dari populasi 120 unit (best practice √N)\nWawancara 2 orang:",
            SmkpSampel::normalSampel($teks));
    }

    public function test_rencana_tersimpan_dengan_jumlah_sampel_dihitung_ulang(): void
    {
        $this->siapkanGrup();
        $a = SmkpAudit::withoutGlobalScopes()->create(['company_id' => $this->induk->id, 'tahun' => 2026, 'status' => 'berjalan', 'tahap' => 1]);

        $this->actingAs($this->auditor($this->induk))->post("/smkp/{$a->id}/rencana", [
            'metode_sampel' => ['I.1' => ['metode' => 'Tinjauan dokumen, wawancara', 'sampel' => "Daftar hadir\nJumlah sampel: 3 dari populasi 120 manpower (Slovin, e 10%)"]],
        ])->assertSessionHasNoErrors();

        $this->assertSame("Daftar hadir\nJumlah sampel: 55 dari populasi 120 manpower (Slovin, e 10%)",
            $a->fresh()->rencana['metode_sampel']['I.1']['sampel']);
    }

    /** Layar memberi angka dan baris yang sama dengan server. */
    public function test_hitungan_php_dan_typescript_sama(): void
    {
        $node = trim((string) shell_exec('command -v node 2>/dev/null'));
        if ($node === '') $this->markTestSkipped('node tidak tersedia di mesin ini.');

        $kasus = [
            ['slovin', 120, 10], ['slovin', 1000, 5], ['slovin', 7, '2,5'], ['akar', 120, null], ['akar', 1, null], ['akar', 9999999, null],
            ['triangulasi', 2, null], ['satu', 7, null], ['sensus', 40, null], ['persen', 125, 10], ['persen', 33, '7,5'],
            ['manual', 120, 200], ['manual', 120, 15], ['slovin', 0, null],
        ];

        $skrip = "const ts = require('typescript'); const fs = require('fs');"
            ."const src = fs.readFileSync(".json_encode(resource_path('js/smkp/lib/jumlahSampel.ts')).", 'utf8');"
            ."const js = ts.transpileModule(src, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText;"
            ."const m = { exports: {} }; new Function('module', 'exports', js)(m, m.exports);"
            ."const k = ".json_encode($kasus).";"
            ."console.log(JSON.stringify(k.map(([c, N, p]) => [m.exports.hitungSampel(c, N, p), m.exports.barisJumlah(c, N, 'manpower', p)])));";

        $tmp = tempnam(sys_get_temp_dir(), 'smp').'.cjs';
        file_put_contents($tmp, $skrip);
        $keluar = shell_exec('cd '.escapeshellarg(base_path()).' && '.escapeshellcmd($node).' '.escapeshellarg($tmp).' 2>&1');
        @unlink($tmp);

        $dariJs = json_decode(trim((string) $keluar), true);
        $this->assertIsArray($dariJs, "jumlahSampel.ts tidak dapat dijalankan: {$keluar}");

        $dariPhp = array_map(fn ($k) => [SmkpSampel::hitungSampel($k[0], $k[1], $k[2]), SmkpSampel::barisJumlah($k[0], $k[1], 'manpower', $k[2])], $kasus);
        $this->assertSame($dariPhp, $dariJs, 'Kalkulator di layar dan server memberi jumlah sampel yang berbeda.');
    }
}
