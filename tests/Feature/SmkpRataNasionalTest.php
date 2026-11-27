<?php

namespace Tests\Feature;

use App\Models\{SmkpAudit, SmkpRataNasional};
use App\Support\SmkpTahap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/**
 * Rata-rata nasional FR/SR/ASR/MFR Surat Edaran dan saran faktor
 * penyesuaian Tahap I (port DBEST #23).
 */
class SmkpRataNasionalTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    protected function setUp(): void
    {
        parent::setUp();
        SmkpTahap::lupakanRataNasional();
    }

    public function test_audit_memakai_se_terbaru_sampai_tahun_auditnya(): void
    {
        $this->assertSame(2022, SmkpTahap::rataNasional(2024)['tahun']);
        $this->assertSame(2025, SmkpTahap::rataNasional(2026)['tahun']);
        $this->assertSame(2022, SmkpTahap::rataNasional(2019)['tahun'], 'Audit yang lebih tua memakai SE terlama.');
        $this->assertSame(0.09, SmkpTahap::rataNasional(2025)['fr']);
    }

    public function test_bilangan_gaya_indonesia(): void
    {
        $this->assertSame(0.09, SmkpTahap::bilangan('0,09'));
        $this->assertSame(1234.5, SmkpTahap::bilangan('1.234,5'));
        $this->assertSame(133.91, SmkpTahap::bilangan('133.91'));
        $this->assertNull(SmkpTahap::bilangan(''));
        $this->assertNull(SmkpTahap::bilangan('belum ada'));
    }

    public function test_saran_faktor_dari_data_kinerja(): void
    {
        $s = SmkpTahap::saranFaktor(['fr' => '0,85', 'sr' => '140', 'asr' => '120', 'mfr' => '600', 'kptk' => '0', 'pak' => ''], 2026);
        $this->assertSame(['kecelakaan' => true, 'penyakit' => false, 'pak' => false], $s, 'FR & SR di atas nasional; ASR di bawah.');

        $s = SmkpTahap::saranFaktor(['fr' => '0,85', 'sr' => '', 'kptk' => '2'], 2026);
        $this->assertNull($s['kecelakaan'], 'SR kosong: belum dapat disarankan.');
        $this->assertNull($s['penyakit']);
        $this->assertTrue($s['pak']);
    }

    public function test_administrator_menambah_menimpa_dan_menghapus(): void
    {
        $this->siapkanGrup();
        $adm = $this->admin();

        $this->actingAs($adm)->post('/smkp/acuan/rata-nasional', [
            'tahun' => 2026, 'nomor' => 'B-1234/MB.07/DBT.KP/2026', 'tanggal' => '2026-11-20',
            'fr' => '0,07', 'sr' => '1.120,5', 'asr' => '410', 'mfr' => '455,25',
        ])->assertRedirect()->assertSessionHasNoErrors();

        SmkpTahap::lupakanRataNasional();
        $r = SmkpTahap::rataNasional(2026);
        $this->assertSame([2026, 0.07, 1120.5, 455.25, true, false], [$r['tahun'], $r['fr'], $r['sr'], $r['mfr'], $r['diubah'], $r['bawaan']]);

        // Isian bertahun bawaan menimpa bawaannya; menghapusnya mengembalikan bawaan.
        $this->post('/smkp/acuan/rata-nasional', ['tahun' => 2025, 'nomor' => 'SE ralat', 'fr' => '0,1', 'sr' => '130', 'asr' => '450', 'mfr' => '480'])->assertSessionHasNoErrors();
        SmkpTahap::lupakanRataNasional();
        $this->assertSame(0.1, SmkpTahap::daftarRataNasional()[2025]['fr']);
        $this->delete('/smkp/acuan/rata-nasional/2025')->assertRedirect();
        SmkpTahap::lupakanRataNasional();
        $this->assertSame(0.09, SmkpTahap::daftarRataNasional()[2025]['fr']);
        $this->assertSame(1, SmkpRataNasional::count());

        $this->post('/smkp/acuan/rata-nasional', ['tahun' => 2026, 'nomor' => '', 'fr' => 'x'])
            ->assertSessionHasErrors(['nomor', 'fr', 'sr', 'asr', 'mfr']);

        // Auditor bukan administrator tidak dapat mengubah acuan nasional.
        $this->actingAs($this->auditor($this->induk))->post('/smkp/acuan/rata-nasional', [
            'tahun' => 2027, 'nomor' => 'X', 'fr' => '1', 'sr' => '1', 'asr' => '1', 'mfr' => '1',
        ])->assertForbidden();
    }

    public function test_halaman_acuan_dan_tahap_satu_membawa_rata_nasional(): void
    {
        $this->siapkanGrup();
        $u = $this->auditor($this->induk);
        $a = SmkpAudit::withoutGlobalScopes()->create(['company_id' => $this->induk->id, 'tahun' => 2026, 'status' => 'berjalan', 'tahap' => 1]);

        $this->actingAs($u)->get('/smkp/acuan')->assertInertia(fn (Inertia $p) => $p->component('Smkp/Acuan')
            ->has('rataNasional', 2)->where('bisaKelola', false));
        $this->get("/smkp/{$a->id}/tahap-1")->assertInertia(fn (Inertia $p) => $p->component('Smkp/Tahap1')
            ->where('rataNasional.tahun', 2025)->where('rataNasional.nomor', 'B-9669/MB.07/DBT.KP/2025'));
    }

    /** Layar memberi saran dan pembacaan angka yang sama dengan server. */
    public function test_saran_php_dan_typescript_sama(): void
    {
        $node = trim((string) shell_exec('command -v node 2>/dev/null'));
        if ($node === '') $this->markTestSkipped('node tidak tersedia di mesin ini.');

        $kasus = [
            ['fr' => '0,85', 'sr' => '140', 'asr' => '120', 'mfr' => '600', 'kptk' => '0', 'pak' => ''],
            ['fr' => '0,09', 'sr' => '133,91', 'asr' => '1.452,19', 'mfr' => '482,72', 'kptk' => '', 'pak' => '0,5'],
            ['fr' => '', 'sr' => '9', 'asr' => 'x', 'mfr' => '1', 'kptk' => '', 'pak' => ''],
        ];
        $n = SmkpTahap::rataNasional(2026);

        // Skrip sementara ada di luar proyek: typescript dicari dari folder proyek (cwd), bukan dari /tmp.
        $skrip = "const ts = require(require.resolve('typescript', { paths: [process.cwd()] })); const fs = require('fs');"
            ."const src = fs.readFileSync(".json_encode(resource_path('js/smkp/lib/kinerja.ts')).", 'utf8');"
            ."const js = ts.transpileModule(src, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 } }).outputText;"
            ."const m = { exports: {} }; new Function('module', 'exports', js)(m, m.exports);"
            ."const k = ".json_encode($kasus)."; const n = ".json_encode(['fr' => $n['fr'], 'sr' => $n['sr'], 'asr' => $n['asr'], 'mfr' => $n['mfr']]).";"
            ."console.log(JSON.stringify(k.map((x) => [m.exports.saranFaktor(x, n), ['fr','sr','asr','mfr'].map((j) => m.exports.bilangan(x[j]))])));";

        $tmp = tempnam(sys_get_temp_dir(), 'rn').'.cjs';
        file_put_contents($tmp, $skrip);
        $keluar = shell_exec('cd '.escapeshellarg(base_path()).' && '.escapeshellcmd($node).' '.escapeshellarg($tmp).' 2>&1');
        @unlink($tmp);

        $dariJs = json_decode(trim((string) $keluar), true);
        $this->assertIsArray($dariJs, "kinerja.ts tidak dapat dijalankan: {$keluar}");

        $dariPhp = array_map(fn ($x) => [SmkpTahap::saranFaktor($x, 2026), array_map(fn ($j) => SmkpTahap::bilangan($x[$j]), ['fr', 'sr', 'asr', 'mfr'])], $kasus);
        $this->assertEquals($dariPhp, $dariJs, 'Saran faktor di layar dan server berbeda.');
    }
}
