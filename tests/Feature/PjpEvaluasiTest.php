<?php

namespace Tests\Feature;

use App\Models\{Pjp, PjpEvaluasi, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Evaluasi kinerja: satu baris per (PJP, tahun, semester).
 */
class PjpEvaluasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_skor_rata_rata_dihitung_dari_tiga_aspek(): void
    {
        $pjp = Pjp::factory()->create();

        $this->post(route('pjp.evaluasi.simpan', $pjp), [
            'tahun' => 2026, 'semester' => 1,
            'skor_teknis' => 90, 'skor_keselamatan_kesehatan' => 80, 'skor_lingkungan' => 70,
        ])->assertSessionHasNoErrors();

        $this->assertSame(80.0, $pjp->evaluasis()->first()->skor_rata_rata);
    }

    public function test_mengisi_ulang_semester_yang_sama_menimpa_bukan_menggandakan(): void
    {
        $pjp = Pjp::factory()->create();

        foreach ([50, 90] as $skor) {
            $this->post(route('pjp.evaluasi.simpan', $pjp), [
                'tahun' => 2026, 'semester' => 1,
                'skor_teknis' => $skor, 'skor_keselamatan_kesehatan' => $skor, 'skor_lingkungan' => $skor,
            ])->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('pjp_evaluasis', 1);
        $this->assertSame(90.0, $pjp->evaluasis()->first()->skor_rata_rata);
    }

    public function test_semester_berbeda_menjadi_baris_terpisah(): void
    {
        $pjp = Pjp::factory()->create();

        foreach ([1, 2] as $semester) {
            $this->post(route('pjp.evaluasi.simpan', $pjp), [
                'tahun' => 2026, 'semester' => $semester,
                'skor_teknis' => 90, 'skor_keselamatan_kesehatan' => 90, 'skor_lingkungan' => 90,
            ])->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('pjp_evaluasis', 2);
    }

    /**
     * Relasi mengurutkan turun supaya "evaluasi terakhir" benar-benar yang
     * terakhir menurut periodenya, bukan menurut urutan penyimpanannya.
     */
    public function test_evaluasi_terakhir_diambil_dari_periode_terbaru(): void
    {
        $pjp = Pjp::factory()->create();

        // Sengaja disimpan tidak berurutan.
        PjpEvaluasi::factory()->for($pjp)->create(['tahun' => 2026, 'semester' => 2, 'skor_teknis' => 60,
            'skor_keselamatan_kesehatan' => 60, 'skor_lingkungan' => 60]);
        PjpEvaluasi::factory()->for($pjp)->create(['tahun' => 2025, 'semester' => 1, 'skor_teknis' => 90,
            'skor_keselamatan_kesehatan' => 90, 'skor_lingkungan' => 90]);

        $terakhir = $pjp->evaluasis()->first();

        $this->assertSame(2026, $terakhir->tahun);
        $this->assertSame(2, $terakhir->semester);
    }

    public function test_skor_di_luar_nol_sampai_seratus_ditolak(): void
    {
        $pjp = Pjp::factory()->create();

        $this->post(route('pjp.evaluasi.simpan', $pjp), [
            'tahun' => 2026, 'semester' => 1,
            'skor_teknis' => 120, 'skor_keselamatan_kesehatan' => 80, 'skor_lingkungan' => 70,
        ])->assertSessionHasErrors('skor_teknis');

        $this->assertDatabaseCount('pjp_evaluasis', 0);
    }

    public function test_evaluasi_milik_pjp_lain_tidak_dapat_dihapus(): void
    {
        $pjp  = Pjp::factory()->create();
        $lain = Pjp::factory()->create();

        $evaluasi = PjpEvaluasi::factory()->for($lain)->create();

        $this->delete(route('pjp.evaluasi.hapus', ['pjp' => $pjp->id, 'evaluasi' => $evaluasi->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('pjp_evaluasis', ['id' => $evaluasi->id]);
    }
}
