<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\SiapanMiners;
use Tests\TestCase;

/**
 * Unduhan Excel register/riwayat Miners: kolom = kolom layar, saringan
 * layar diikuti, dan lingkup perusahaan pengguna dihormati.
 */
class MinersEksporTest extends TestCase
{
    use RefreshDatabase;
    use SiapanMiners;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkan();
    }

    /** @return list<list<string>> */
    private function lembar(string $url, $oleh = null): array
    {
        $r = $this->actingAs($oleh ?? $this->admin)->get($url);
        $r->assertOk();
        $jalur = tempnam(sys_get_temp_dir(), 'ek').'.xlsx';
        file_put_contents($jalur, $r->streamedContent());

        return IOFactory::load($jalur)->getActiveSheet()->toArray(null, false, false, false);
    }

    #[Test]
    public function simper_riwayat_mengikuti_saringan(): void
    {
        $a = $this->simperTerbit();
        $this->simperTerbit();

        $isi = $this->lembar('/miners/simper/ekspor?mode=riwayat');
        $this->assertSame('No SIMPER', $isi[0][0]);
        $this->assertCount(3, $isi);

        $isi = $this->lembar('/miners/simper/ekspor?mode=riwayat&cari='.urlencode($a->pekerja->nama));
        $this->assertCount(2, $isi);
    }

    #[Test]
    public function manpower_dan_lanjutan_terunduh(): void
    {
        $this->mcuSelesai($this->mitra);
        $isi = $this->lembar('/miners/ekspor?perusahaan='.$this->mitra->id);
        $this->assertSame('Nama Employee', $isi[0][1]);
        $this->assertCount(2, $isi);

        foreach (['penambahan', 'upgrade', 'perpanjangan'] as $j) {
            $this->assertSame('No Registrasi', $this->lembar('/miners/simper-lanjutan/ekspor?jenis='.$j)[0][0]);
        }
    }

    #[Test]
    public function mitra_tidak_mengunduh_data_perusahaan_lain(): void
    {
        $this->mcuSelesai($this->iup);
        $this->mcuSelesai($this->mitra);

        $isi = $this->lembar('/miners/ekspor', $this->pengaju);
        $this->assertCount(2, $isi, 'Akun mitra hanya melihat manpower perusahaannya.');
    }
}
