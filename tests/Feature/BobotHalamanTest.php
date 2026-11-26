<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTML halaman harus tetap ringan.
 *
 * HTML halaman yang memerlukan masuk tidak pernah disimpan peramban
 * (no-store), jadi setiap bita di dalamnya terunduh ulang pada setiap
 * pemuatan penuh. Lapisan gaya bersama dulu disisipkan sebaris — HTML
 * halaman masuk 207 KB, 187 KB di antaranya CSS — dan di sambungan site
 * tambang itulah yang membuat tiap halaman terasa lambat. Kini ia berkas
 * ber-hash yang disimpan setahun; uji ini menjaga agar tidak kembali.
 */
class BobotHalamanTest extends TestCase
{
    use RefreshDatabase;

    public function test_lapisan_gaya_bersama_ditautkan_bukan_disisipkan(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<link[^>]+eq-visual[^>]*\.css#', $html,
            'Lapisan gaya bersama tidak lagi ditautkan.');

        preg_match_all('#<style\b[^>]*>(.*?)</style>#s', $html, $m);
        $sebaris = array_sum(array_map('strlen', $m[1]));

        $this->assertLessThan(5 * 1024, $sebaris,
            'Ada '.round($sebaris / 1024).' KB CSS sebaris di HTML — terunduh ulang setiap pemuatan.');
    }

    public function test_html_halaman_masuk_tetap_ringan(): void
    {
        $ukuran = strlen($this->get('/login')->assertOk()->getContent());

        $this->assertLessThan(60 * 1024, $ukuran,
            'HTML halaman masuk '.round($ukuran / 1024).' KB — sesuatu yang besar kembali disisipkan.');
    }
}
