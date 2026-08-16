<?php

namespace Tests\Feature;

use App\Models\{Company, News, User};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scopes\ScopedBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Mana yang dipakai bersama, dan mana yang melekat perusahaan.
 *
 * Pembagiannya disengaja, bukan kebetulan sejarah, dan justru karena
 * itu perlu dikunci: keduanya sama-sama "tidak menimbulkan galat" bila
 * salah. Pustaka yang tanpa sengaja dibatasi perusahaan membuat kursus
 * lenyap dari semua orang; pengumuman yang tanpa sengaja dibiarkan
 * bersama membuat kabar satu lokasi kerja terbaca seluruh klien.
 *
 * Aturannya satu kalimat: MATERI dipakai bersama, HASIL melekat
 * perusahaan.
 */
class LingkupPustakaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Pustaka pelatihan sengaja tanpa kolom perusahaan.
     *
     * Bila suatu saat memang harus dibatasi, uji ini yang pertama
     * merah — dan itu memang tempat yang benar untuk memutuskannya,
     * sebab keputusannya menyangkut backfill: kursus yang sudah ada
     * harus diberikan kepada siapa.
     */
    public function test_pustaka_pelatihan_dipakai_bersama(): void
    {
        foreach (['courses', 'quizzes', 'procedures', 'inspection_templates'] as $tabel) {
            $this->assertFalse(Schema::hasColumn($tabel, 'company_id'),
                "Tabel {$tabel} kini punya company_id. Itu boleh saja, tetapi bukan "
                ."perubahan yang dapat dilakukan diam-diam: materi pelatihan dipakai "
                ."bersama seluruh perusahaan, dan membatasinya menuntut keputusan "
                ."tentang pemilik baris yang sudah ada.");
        }
    }

    /** Yang melekat perusahaan tetap melekat. */
    public function test_hasil_melekat_perusahaan(): void
    {
        foreach ([
            'certificates', 'documents', 'inspections',
            'hazard_reports', 'ko_objects', 'smkp_audits', 'news',
        ] as $tabel) {
            $this->assertTrue(Schema::hasColumn($tabel, 'company_id'),
                "Tabel {$tabel} kehilangan company_id — datanya akan terbaca lintas perusahaan.");
        }
    }

    /* ═══════════ berita ═══════════ */

    public function test_berita_perusahaan_lain_tidak_terbaca(): void
    {
        $a = Company::create(['name' => 'PT A']);
        $b = Company::create(['name' => 'PT B']);

        News::withoutGlobalScopes()->create([
            'title' => 'Rapat internal A', 'content' => 'x',
            'published_at' => now(), 'company_id' => $a->id,
        ]);
        News::withoutGlobalScopes()->create([
            'title' => 'Rapat internal B', 'content' => 'x',
            'published_at' => now(), 'company_id' => $b->id,
        ]);

        $this->actingAs(User::factory()->create(['company_id' => $a->id, 'is_admin' => false]));

        $judul = News::query()->pluck('title')->all();

        $this->assertContains('Rapat internal A', $judul);
        $this->assertNotContains('Rapat internal B', $judul,
            'Pengumuman perusahaan lain terbaca.');
    }

    /**
     * Berita lama — yang tanpa perusahaan — tetap terbaca semua orang.
     *
     * Inilah sebabnya migrasinya tidak membawa backfill. Menebak
     * pemilik pengumuman lama berarti menyembunyikannya dari yang
     * berhak, dan kegagalan itu tidak menimbulkan galat apa pun.
     */
    public function test_berita_tanpa_perusahaan_tetap_terbaca(): void
    {
        $a = Company::create(['name' => 'PT A']);

        News::withoutGlobalScopes()->create([
            'title' => 'Pengumuman se-pemasangan', 'content' => 'x',
            'published_at' => now(), 'company_id' => null,
        ]);

        $this->actingAs(User::factory()->create(['company_id' => $a->id, 'is_admin' => false]));

        $this->assertContains('Pengumuman se-pemasangan', News::query()->pluck('title')->all());
    }

    public function test_berita_baru_mewarisi_perusahaan_penulisnya(): void
    {
        $a = Company::create(['name' => 'PT A']);

        $this->actingAs(User::factory()->create(['company_id' => $a->id, 'is_admin' => false]));

        $berita = News::create(['title' => 'Baru', 'content' => 'x', 'published_at' => now()]);

        $this->assertSame($a->id, $berita->fresh()->company_id);
    }

    /**
     * Administrator tetap menjangkau seluruh perusahaan.
     *
     * Tanpa ini, pembatasan yang baru dipasang akan memotong halaman
     * kelola berita bagi orang yang justru bertugas mengelolanya.
     */
    public function test_administrator_membaca_berita_seluruh_perusahaan(): void
    {
        $a = Company::create(['name' => 'PT A']);
        $b = Company::create(['name' => 'PT B']);

        foreach ([$a, $b] as $c) {
            News::withoutGlobalScopes()->create([
                'title' => 'Berita '.$c->name, 'content' => 'x',
                'published_at' => now(), 'company_id' => $c->id,
            ]);
        }

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->assertCount(2, News::query()->get());
    }
}
