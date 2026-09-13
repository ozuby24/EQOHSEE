<?php

namespace Tests\Feature;

use App\Models\{SmkpAudit, SmkpFinding, User};
use App\Support\Smkp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kolom jenis temuan hanya menyimpan kode, tidak pernah label.
 *
 * Aplikasi menulis 'mayor' dan 'minor'; sebagian sumber lain menulis
 * "Ketidaksesuaian Mayor". Keduanya sah dibaca manusia dan keduanya
 * tersimpan tanpa satu pun galat — tetapi setiap hitungan yang memakai
 * `where('jenis','mayor')` menghasilkan NOL atas tabel yang berisi
 * belasan temuan, dan seluruhnya jatuh ke keranjang "observasi".
 *
 * Terukur pada data contoh sebelum perbaikan ini: satu audit dengan satu
 * temuan mayor dan satu minor terbaca "0 mayor · 0 minor · 2 observasi"
 * pada rekapitulasi dan dasbor performa.
 */
class JenisTemuanSmkpTest extends TestCase
{
    use RefreshDatabase;

    private function audit(): SmkpAudit
    {
        $this->actingAs(User::factory()->create(['email_verified_at' => now()]));

        return SmkpAudit::create(['tahun' => 2026, 'status' => 'draft']);
    }

    public function test_label_panjang_diseragamkan_menjadi_kode(): void
    {
        $a = $this->audit();

        $t = $a->findings()->create([
            'kode_kriteria' => 'I.1',
            'jenis'         => 'Ketidaksesuaian Mayor',
            'uraian'        => 'Uji',
            'status'        => 'Open',
        ]);

        $this->assertSame('mayor', $t->fresh()->jenis);
        $this->assertSame('mayor', DB::table('smkp_findings')->where('id', $t->id)->value('jenis'),
            'Yang tersimpan di kolomnya pun harus kode, bukan hanya yang dibaca kembali.');
    }

    public function test_jenis_di_luar_ketidaksesuaian_menjadi_obs(): void
    {
        $a = $this->audit();

        foreach (['Observasi', 'catatan lapangan', ''] as $masuk) {
            $t = $a->findings()->create([
                'kode_kriteria' => 'I.1', 'jenis' => $masuk,
                'uraian' => 'Uji', 'status' => 'Open',
            ]);

            $this->assertSame('obs', $t->fresh()->jenis, "Masukan: \"$masuk\"");
        }
    }

    /** Kode yang sudah benar tidak diubah. */
    public function test_kode_yang_sudah_benar_dibiarkan(): void
    {
        $a = $this->audit();

        foreach (['mayor', 'minor', 'obs'] as $kode) {
            $t = $a->findings()->create([
                'kode_kriteria' => 'I.1', 'jenis' => $kode,
                'uraian' => 'Uji', 'status' => 'Open',
            ]);

            $this->assertSame($kode, $t->fresh()->jenis);
        }
    }

    /**
     * Dan hitungannya benar — inilah yang sebenarnya rusak.
     *
     * Uji di atas hanya memeriksa isi kolomnya. Yang membuat cacatnya
     * terasa adalah hitungan yang membacanya, dan hitungan itulah yang
     * dulu selamanya nol.
     */
    public function test_rekapitulasi_menghitung_mayor_dan_minor(): void
    {
        $a = $this->audit();

        foreach ([['Ketidaksesuaian Mayor', 2], ['Ketidaksesuaian Minor', 3], ['Observasi', 1]] as [$jenis, $n]) {
            for ($i = 0; $i < $n; $i++) {
                $a->findings()->create([
                    'kode_kriteria' => 'I.1', 'jenis' => $jenis,
                    'uraian' => 'Uji', 'status' => 'Open',
                ]);
            }
        }

        $t = $a->findings();

        $this->assertSame(2, (clone $t)->where('jenis', 'mayor')->count());
        $this->assertSame(3, (clone $t)->where('jenis', 'minor')->count());
        $this->assertSame(1, (clone $t)->whereNotIn('jenis', ['mayor', 'minor'])->count());
    }

    /** Data contoh menulis kode, bukan label. */
    public function test_data_contoh_menulis_kode(): void
    {
        $isi = file_get_contents(app_path('Support/DataContoh.php'));

        $this->assertStringNotContainsString("'Ketidaksesuaian Mayor'", $isi);
        $this->assertStringNotContainsString("'Ketidaksesuaian Minor'", $isi);
    }

    /** Dan normalisatornya mengenali keduanya. */
    public function test_normalisator_mengenali_kode_maupun_label(): void
    {
        $this->assertSame('mayor', Smkp::kodeJenis('mayor'));
        $this->assertSame('mayor', Smkp::kodeJenis('Ketidaksesuaian Mayor'));
        $this->assertSame('mayor', Smkp::kodeJenis('KETIDAKSESUAIAN MAYOR'));
        $this->assertSame('minor', Smkp::kodeJenis('Ketidaksesuaian Minor'));
        $this->assertSame('obs',   Smkp::kodeJenis(null));
    }
}
