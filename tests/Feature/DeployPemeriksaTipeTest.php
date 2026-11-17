<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Pemeriksaan tipe saat deploy harus muat di VPS berjatah 1 GB.
 *
 * `vue-tsc --noEmit` sekali jalan memerlukan ±1,2 GB heap pada ukuran
 * proyek sekarang, dan deploy berhenti dengan "JavaScript heap out of
 * memory" — tanpa satu pun galat tipe. Deploy memakai pemeriksa bergilir
 * (tools/periksa-tipe.mjs); uji ini menjaga supaya ia tidak kembali ke
 * pemanggilan sekali jalan pada penyuntingan berikutnya.
 */
class DeployPemeriksaTipeTest extends TestCase
{
    public function test_deploy_memakai_pemeriksa_tipe_bergilir(): void
    {
        $skrip = file_get_contents(base_path('deploy/deploy.sh'));

        $this->assertStringContainsString('node tools/periksa-tipe.mjs', $skrip);
        $this->assertStringNotContainsString('npm run --silent typecheck', $skrip,
            'Deploy kembali memanggil vue-tsc sekali jalan, yang tidak muat di VPS 1 GB.');
        $this->assertStringContainsString('EQOHSEE_TIPE_MB', $skrip);
    }

    public function test_pemeriksa_bergilir_ada_dan_membelah_giliran_yang_kehabisan_memori(): void
    {
        $alat = file_get_contents(base_path('tools/periksa-tipe.mjs'));

        $this->assertStringContainsString('EQOHSEE_TIPE_MB', $alat);
        $this->assertStringContainsString('dibelah dua', $alat);
        // deploy.sh membedakan kehabisan memori dari galat tipe lewat kalimat ini.
        $this->assertStringContainsString('JavaScript heap out of memory', $alat);
    }
}
