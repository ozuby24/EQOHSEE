<?php

namespace Tests\Feature;

use App\Models\SmkpAudit;
use App\Support\{Smkp, SmkpKeterkaitan};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/**
 * Keterkaitan antar elemen sebagai acuan penilaian (port DBEST #23).
 */
class SmkpKeterkaitanTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    /** Setiap kode di peta keterkaitan ada di rubrik Kepdirjen — kode salah ketik tidak pernah menyala di Penilaian. */
    public function test_setiap_kode_keterkaitan_dikenal_rubrik(): void
    {
        $dikenal = [];
        foreach (Smkp::elemen() as $e) {
            $dikenal[$e['kode']] = true;
            foreach ($e['sub'] as $s) {
                $dikenal[$s['kode']] = true;
                foreach ($s['subsub'] ?? [] as $ss) $dikenal[$ss['kode']] = true;
            }
        }

        $kelompok = SmkpKeterkaitan::data()['kelompok'];
        $this->assertNotEmpty($kelompok);
        foreach ($kelompok as $g) {
            foreach ($g['tautan'] as $t) {
                foreach (array_merge($t['a'], $t['b']) as $k) {
                    $this->assertArrayHasKey($k, $dikenal, "Kode {$k} di keterkaitan.json tidak ada di rubrik.");
                }
            }
        }
    }

    public function test_sub_elemen_menemukan_keterkaitannya_termasuk_lewat_sub_sub(): void
    {
        $this->assertContains('IV.9', array_merge(...array_column(SmkpKeterkaitan::untuk('III.8'), 'terkait')));

        // II.2.4/II.2.5 ↔ V.7: sub elemen II.2 ikut terkait lewat sub-sub elemennya.
        $this->assertContains('V.7', array_merge(...array_column(SmkpKeterkaitan::untuk('II.2'), 'terkait')));

        $this->assertSame([], SmkpKeterkaitan::untuk('ZZ.9'));
    }

    /** Kesesuaian di satu sisi dan Ketidaksesuaian Mayor di sisi dasarnya diperingatkan. */
    public function test_hasil_tidak_sejalan_diperingatkan(): void
    {
        $hasil = [];
        foreach (Smkp::butir() as $b) {
            if ($b['sub'] === 'III.8') $hasil[$b['kode']] = ['v' => $b['maks']];   // penuh → Kesesuaian
            if ($b['sub'] === 'IV.9')  $hasil[$b['kode']] = ['v' => 0];            // nol → Mayor
        }
        $rekap = Smkp::rekap($hasil);
        $this->assertSame('kesesuaian', SmkpKeterkaitan::kategoriTuntas($rekap['elemen']['III']['sub']['III.8']));

        $t = collect(SmkpKeterkaitan::denganStatus('III.8', $rekap, $hasil, 'kesesuaian'))
            ->first(fn ($x) => in_array('IV.9', $x['terkait'], true));
        $this->assertNotNull($t);
        $this->assertNotEmpty($t['periksa']);
        $this->assertStringContainsString('IV.9', $t['periksa'][0]);

        // Belum tuntas dinilai: tidak ada peringatan sama sekali.
        $this->assertSame([], collect(SmkpKeterkaitan::denganStatus('III.8', $rekap, $hasil, null))
            ->pluck('periksa')->flatten()->all());
    }

    public function test_penilaian_dan_acuan_membawa_keterkaitan(): void
    {
        $this->siapkanGrup();
        $adm = $this->admin();
        $a = SmkpAudit::withoutGlobalScopes()->create([
            'company_id' => $this->induk->id, 'tahun' => 2026, 'status' => 'berjalan', 'tahap' => 2, 'hasil' => [],
        ]);

        $this->actingAs($adm)->get("/smkp/{$a->id}/elemen/III")->assertOk()->assertInertia(fn (Inertia $p) => $p
            ->component('Smkp/Nilai')
            ->where('sub', function ($sub) {
                $iii8 = collect($sub)->firstWhere('kode', 'III.8');
                $kait = collect($iii8['keterkaitan'] ?? []);
                $chip = collect($kait->first()['status'] ?? [])->first();
                return $kait->isNotEmpty() && str_contains((string) ($chip['url'] ?? ''), '/smkp/');
            }));

        $this->actingAs($adm)->get('/smkp/acuan')->assertOk()->assertInertia(fn (Inertia $p) => $p
            ->component('Smkp/Acuan')
            ->has('keterkaitan.kelompok', count(SmkpKeterkaitan::data()['kelompok'])));
    }
}
