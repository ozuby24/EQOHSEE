<?php

namespace Tests\Feature;

use App\Models\{Pjp, PjpEvaluasi, PjpLaporan, SmkpChecklistAnswer, SmkpChecklistCategory,
    SmkpChecklistItem, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Capaian gabungan dan capaian per halaman aspek.
 *
 * Dua hal yang mudah tertukar dan dijaga terpisah di sini:
 *
 *  - `achievement()` adalah skor TERENDAH lintas aspek, dipakai hanya di
 *    beranda modul untuk menjawab "siapa yang paling perlu dikejar".
 *  - `capaian` pada ketiga halaman aspek adalah skor HALAMAN ITU untuk
 *    tiap PJP, bukan sifat PJP-nya. Satu perusahaan muncul di ketiganya
 *    dengan tiga angka berbeda, dan itu memang benar.
 */
class PjpCapaianTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    /**
     * Pembanding skor pada prop Inertia.
     *
     * Angka bulat melewati JSON kehilangan jejak float-nya — 100.0
     * kembali sebagai int 100 — sehingga perbandingan ketat gagal pada
     * nilai yang sebenarnya benar. Yang diuji di sini nilainya, bukan
     * tipe yang tersisa setelah pengangkutan.
     */
    private function skor(float $harapan): \Closure
    {
        return fn ($nilai) => is_numeric($nilai) && abs((float) $nilai - $harapan) < 0.001;
    }

    /** Mengisi seluruh item berbobot dengan satu nilai yang sama. */
    private function isiChecklist(Pjp $pjp, string $nilai): void
    {
        SmkpChecklistItem::query()
            ->whereHas('category', fn ($q) => $q->where('kode', '!=', SmkpChecklistCategory::LEGALITAS))
            ->get()
            ->each(fn (SmkpChecklistItem $item) => SmkpChecklistAnswer::create([
                'pjp_id' => $pjp->id,
                'smkp_checklist_item_id' => $item->id,
                'jawaban' => 'ya',
                'nilai' => $nilai,
            ]));
    }

    public function test_checklist_kosong_menyumbang_nol_bukan_null(): void
    {
        // Karena itu achievement praktis tidak pernah null: skor SMKP
        // selalu berupa angka, bahkan untuk PJP yang baru terdaftar.
        $pjp = Pjp::factory()->create();

        $this->assertSame(0.0, $pjp->smkpScore()['persentase']);
        $this->assertSame(0.0, $pjp->achievement());
    }

    public function test_capaian_gabungan_mengambil_yang_terendah_bukan_rata_rata(): void
    {
        $pjp = Pjp::factory()->create();

        // Checklist penuh: 100%.
        $this->isiChecklist($pjp, '3');

        // Pelaporan: 1 dari 2 tepat waktu = 50%.
        PjpLaporan::factory()->for($pjp)->create(['created_at' => now()->startOfMonth()->addDay()]);
        PjpLaporan::factory()->for($pjp)->create(['created_at' => now()->startOfMonth()->addDays(10)]);

        // Evaluasi: rata-rata 90.
        PjpEvaluasi::factory()->for($pjp)->create([
            'tahun' => 2026, 'semester' => 1,
            'skor_teknis' => 90, 'skor_keselamatan_kesehatan' => 90, 'skor_lingkungan' => 90,
        ]);

        // Rata-rata ketiganya 80 — dan justru itu yang menyembunyikan
        // pelaporan 50%. Yang dipakai adalah nilai terendah.
        $this->assertSame(50.0, $pjp->fresh()->achievement());
    }

    public function test_aspek_tanpa_data_diabaikan_bukan_dihitung_nol(): void
    {
        $pjp = Pjp::factory()->create();
        $this->isiChecklist($pjp, '3');

        // Belum ada laporan dan belum ada evaluasi: keduanya null dan
        // dikeluarkan, sehingga yang tersisa hanya skor checklist.
        $this->assertNull($pjp->pelaporanScore());
        $this->assertSame(100.0, $pjp->fresh()->achievement());
    }

    public function test_perlu_perhatian_hanya_memuat_yang_di_bawah_ambang(): void
    {
        $bagus = Pjp::factory()->create(['nama_perusahaan' => 'PT Capaian Bagus']);
        $this->isiChecklist($bagus, '3');

        $buruk = Pjp::factory()->create(['nama_perusahaan' => 'PT Perlu Dikejar']);
        $this->isiChecklist($buruk, '1');

        $nama = Pjp::perluPerhatian()->pluck('nama_perusahaan')->all();

        $this->assertContains($buruk->nama_perusahaan, $nama);
        $this->assertNotContains($bagus->nama_perusahaan, $nama);
    }

    public function test_perlu_perhatian_diurutkan_dari_yang_terendah(): void
    {
        $sedang = Pjp::factory()->create(['nama_perusahaan' => 'PT Sedang']);
        $this->isiChecklist($sedang, '2');   // ~66,7%

        $rendah = Pjp::factory()->create(['nama_perusahaan' => 'PT Rendah']);
        $this->isiChecklist($rendah, '1');   // ~33,3%

        $urut = Pjp::perluPerhatian()->pluck('nama_perusahaan')->all();

        $this->assertSame(['PT Rendah', 'PT Sedang'], $urut);
    }

    /* ---------- capaian per halaman aspek ---------- */

    public function test_halaman_persyaratan_memakai_skor_checklist(): void
    {
        $pjp = Pjp::factory()->create();
        $this->isiChecklist($pjp, '3');

        $this->get(route('pjp.persyaratan'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Pjp/Persyaratan')
                ->where('pjps.0.capaian', $this->skor(100))
                ->where('pjps.0.smkpPersentase', $this->skor(100)));
    }

    public function test_halaman_pelaporan_memakai_skor_kepatuhan_dan_null_saat_belum_ada(): void
    {
        $pjp = Pjp::factory()->create();
        $this->isiChecklist($pjp, '3');   // tidak boleh memengaruhi halaman ini

        $this->get(route('pjp.pelaporan'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Pjp/Pelaporan')
                ->where('pjps.0.capaian', null));

        PjpLaporan::factory()->for($pjp)->create(['created_at' => now()->startOfMonth()->addDay()]);

        $this->get(route('pjp.pelaporan'))
            ->assertInertia(fn (Assert $page) => $page->where('pjps.0.capaian', $this->skor(100)));
    }

    public function test_halaman_evaluasi_memakai_skor_evaluasi_terakhir(): void
    {
        $pjp = Pjp::factory()->create();
        $this->isiChecklist($pjp, '3');

        $this->get(route('pjp.evaluasi'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Pjp/Evaluasi')
                ->where('pjps.0.capaian', null)
                ->where('pjps.0.evaluasiTerakhir', null));

        PjpEvaluasi::factory()->for($pjp)->create([
            'tahun' => 2026, 'semester' => 1,
            'skor_teknis' => 80, 'skor_keselamatan_kesehatan' => 80, 'skor_lingkungan' => 80,
        ]);

        $this->get(route('pjp.evaluasi'))
            ->assertInertia(fn (Assert $page) => $page->where('pjps.0.capaian', $this->skor(80)));
    }

    /**
     * Riwayat evaluasi diurutkan NAIK supaya garis tren terbaca kiri ke
     * kanan, meski relasinya sendiri mengurutkan turun untuk menjawab
     * "evaluasi terakhir". Keduanya sengaja berbeda arah.
     */
    public function test_riwayat_evaluasi_diurutkan_naik_untuk_grafik_tren(): void
    {
        $pjp = Pjp::factory()->create();

        PjpEvaluasi::factory()->for($pjp)->create(['tahun' => 2026, 'semester' => 2,
            'skor_teknis' => 60, 'skor_keselamatan_kesehatan' => 60, 'skor_lingkungan' => 60]);
        PjpEvaluasi::factory()->for($pjp)->create(['tahun' => 2025, 'semester' => 1,
            'skor_teknis' => 90, 'skor_keselamatan_kesehatan' => 90, 'skor_lingkungan' => 90]);

        $this->get(route('pjp.evaluasi'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pjps.0.riwayatEvaluasi.0.tahun', 2025)
                ->where('pjps.0.riwayatEvaluasi.1.tahun', 2026));
    }

    public function test_setiap_pjp_muncul_di_ketiga_halaman_aspek_sekaligus(): void
    {
        Pjp::factory()->count(3)->create();

        foreach (['pjp.persyaratan', 'pjp.pelaporan', 'pjp.evaluasi'] as $rute) {
            $this->get(route($rute))
                ->assertInertia(fn (Assert $page) => $page->has('pjps', 3));
        }
    }
}
