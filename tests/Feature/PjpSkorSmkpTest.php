<?php

namespace Tests\Feature;

use App\Models\{Pjp, SmkpChecklistAnswer, SmkpChecklistCategory, SmkpChecklistItem, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aturan hitung skor checklist prakualifikasi SMKP.
 *
 * Seluruhnya aturan yang rusak tanpa bunyi: skornya tetap keluar sebagai
 * angka yang masuk akal, tetap tergambar sebagai bilah berwarna, dan
 * tidak ada yang menandai bahwa artinya sudah berubah. Karena itu
 * nilainya dikunci di sini, bukan diperiksa dengan mata.
 */
class PjpSkorSmkpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_data_acuan_terisi_tujuh_belas_kategori_dan_126_pertanyaan(): void
    {
        $this->assertSame(17, SmkpChecklistCategory::count());
        $this->assertSame(126, SmkpChecklistItem::count());

        // Bobot kategori A–P berjumlah 178; LEGALITAS berdiri di luar itu.
        $this->assertSame(178, (int) SmkpChecklistItem::query()
            ->whereHas('category', fn ($q) => $q->where('kode', '!=', SmkpChecklistCategory::LEGALITAS))
            ->sum('bobot'));
    }

    public function test_skor_nol_persen_saat_belum_ada_jawaban(): void
    {
        $skor = Pjp::factory()->create()->smkpScore();

        $this->assertSame(0.0, $skor['persentase']);
        $this->assertGreaterThan(0, $skor['total_bobot']);
    }

    public function test_skor_seratus_persen_saat_seluruh_item_bernilai_maksimal(): void
    {
        $pjp = Pjp::factory()->create();

        SmkpChecklistItem::query()
            ->whereHas('category', fn ($q) => $q->where('kode', '!=', SmkpChecklistCategory::LEGALITAS))
            ->get()
            ->each(fn (SmkpChecklistItem $item) => SmkpChecklistAnswer::create([
                'pjp_id' => $pjp->id,
                'smkp_checklist_item_id' => $item->id,
                'jawaban' => 'ya',
                'nilai' => '3',
            ]));

        $this->assertSame(100.0, $pjp->smkpScore()['persentase']);
    }

    public function test_item_bernilai_na_keluar_dari_pembilang_dan_penyebut(): void
    {
        $pjp = Pjp::factory()->create();

        $kategori = SmkpChecklistCategory::where('kode', '!=', SmkpChecklistCategory::LEGALITAS)->first();
        $items = $kategori->items;

        // Seluruh item N/A kecuali satu yang bernilai penuh.
        foreach ($items as $i => $item) {
            SmkpChecklistAnswer::create([
                'pjp_id' => $pjp->id,
                'smkp_checklist_item_id' => $item->id,
                'jawaban' => $i === 0 ? 'ya' : 'na',
                'nilai' => $i === 0 ? '3' : 'na',
            ]);
        }

        $rincian = collect($pjp->smkpCategoryBreakdown())->firstWhere('kode', $kategori->kode);

        // Hanya bobot satu item yang dinilai, jadi capaiannya penuh.
        $this->assertSame($items->first()->bobot, $rincian['bobot_dinilai']);
        $this->assertSame(100.0, $rincian['persentase']);
    }

    public function test_item_yang_belum_dijawab_tetap_menyumbang_bobot_dengan_skor_nol(): void
    {
        $pjp = Pjp::factory()->create();

        $kategori = SmkpChecklistCategory::where('kode', '!=', SmkpChecklistCategory::LEGALITAS)->first();
        $bobotPenuh = $kategori->items->sum('bobot');

        $rincian = collect($pjp->smkpCategoryBreakdown())->firstWhere('kode', $kategori->kode);

        $this->assertSame($bobotPenuh, $rincian['bobot_dinilai']);
        $this->assertSame(0.0, $rincian['persentase']);
    }

    public function test_legalitas_dihitung_terpisah_dan_tidak_masuk_rincian_skor(): void
    {
        $pjp = Pjp::factory()->create();

        $items = SmkpChecklistCategory::where('kode', SmkpChecklistCategory::LEGALITAS)->first()->items;
        $this->assertGreaterThan(0, $items->count());

        SmkpChecklistAnswer::create([
            'pjp_id' => $pjp->id,
            'smkp_checklist_item_id' => $items->first()->id,
            'jawaban' => 'ya',
            'nilai' => '3',
        ]);

        $status = $pjp->smkpLegalitasStatus();

        $this->assertSame($items->count(), $status['total']);
        $this->assertSame(1, $status['lengkap']);

        $this->assertArrayNotHasKey(
            SmkpChecklistCategory::LEGALITAS,
            array_column($pjp->smkpCategoryBreakdown(), null, 'kode'),
            'Kategori legalitas tidak boleh ikut pada rincian skor berbobot.',
        );
    }

    /**
     * Label kategori risiko menyatakan tingkat risiko pekerjaan yang LAYAK
     * dipercayakan, bukan tingkat bahaya PJP-nya. Karena itu skor tertinggi
     * berlabel "Kritis" — terbalik dari dugaan yang wajar, dan justru itu
     * yang membuatnya mudah "diperbaiki" menjadi salah.
     */
    public function test_label_kategori_risiko_mengikuti_ambang_dokumen_acuan(): void
    {
        $this->assertSame('Kritis',       Pjp::kategoriRisiko(100));
        $this->assertSame('Kritis',       Pjp::kategoriRisiko(75.1));
        $this->assertSame('Tinggi',       Pjp::kategoriRisiko(75));
        $this->assertSame('Tinggi',       Pjp::kategoriRisiko(55));
        $this->assertSame('Sedang',       Pjp::kategoriRisiko(54.9));
        $this->assertSame('Sedang',       Pjp::kategoriRisiko(36));
        $this->assertSame('Rendah',       Pjp::kategoriRisiko(35.9));
        $this->assertSame('Rendah',       Pjp::kategoriRisiko(20));
        $this->assertSame('Sangat Rendah', Pjp::kategoriRisiko(19.9));
        $this->assertSame('Sangat Rendah', Pjp::kategoriRisiko(0));
    }

    public function test_checklist_tersimpan_lewat_titik_akhir_dan_mengubah_skor(): void
    {
        $pjp = Pjp::factory()->create();

        $items = SmkpChecklistItem::query()
            ->whereHas('category', fn ($q) => $q->where('kode', '!=', SmkpChecklistCategory::LEGALITAS))
            ->get();

        $this->post(route('pjp.checklist.simpan', $pjp), [
            'jawaban' => $items->map(fn (SmkpChecklistItem $i) => [
                'item_id' => $i->id, 'jawaban' => 'ya', 'nilai' => '3', 'penjelasan' => '',
            ])->all(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(100.0, $pjp->fresh()->smkpScore()['persentase']);
        $this->assertSame($items->count(), $pjp->smkpChecklistAnswers()->count());
    }

    public function test_mengisi_ulang_checklist_menimpa_bukan_menggandakan(): void
    {
        $pjp = Pjp::factory()->create();
        $item = SmkpChecklistItem::query()
            ->whereHas('category', fn ($q) => $q->where('kode', '!=', SmkpChecklistCategory::LEGALITAS))
            ->first();

        foreach (['3', '1'] as $nilai) {
            $this->post(route('pjp.checklist.simpan', $pjp), [
                'jawaban' => [['item_id' => $item->id, 'jawaban' => 'ya', 'nilai' => $nilai]],
            ])->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('smkp_checklist_answers', 1);
        $this->assertSame('1', $pjp->smkpChecklistAnswers()->first()->nilai);
    }
}
