<?php

namespace Tests\Feature;

use App\Models\{ActivityLog, SmkpAudit, SmkpFinding};
use App\Services\Asisten\{GagalAsisten, Jawaban, KlienAi, Permintaan};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/**
 * Asisten AI: akses & jangkauan, validasi, bentuk jawaban ketiga tugas,
 * penerjemahan galat penyedia, batas per jam, log aktivitas, serta bentuk
 * permintaan SDK yang sebenarnya (lewat transport tiruan — tanpa jaringan).
 */
class AsistenTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    private SmkpAudit $audit;
    private SmkpFinding $temuan;

    /** Tiruan KlienAi: mencatat permintaan, menjawab data tetap atau melempar galat. */
    private object $palsu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanGrup();

        // Asisten aktif: sambungan AI EQOHSEE (App\Support\Ai, Gemini) berkunci.
        config(['asisten.aktif' => true, 'asisten.batas_per_jam' => 30,
            'bantuan.ai.kunci' => 'kunci-uji-rahasia', 'bantuan.ai.model' => 'gemini-uji']);

        $this->audit = SmkpAudit::withoutGlobalScopes()->create([
            'company_id' => $this->induk->id, 'tahun' => 2026, 'status' => 'berjalan', 'tahap' => 2,
            'hasil' => [
                'II.2.1' => ['v' => 2, 'ket' => 'Rapat konsultasi risiko belum rutin', 'bukti' => 'Notulen P2K3 Maret'],
                'II.2.2' => ['v' => 1, 'ket' => '', 'bukti' => ''],
            ],
        ]);
        $this->temuan = $this->audit->findings()->create([
            'kode_kriteria' => 'II.2', 'jenis' => 'mayor', 'uraian' => 'Manajemen Risiko', 'status' => 'Open',
        ]);

        $this->palsu = new class implements KlienAi {
            /** @var list<Permintaan> */
            public array $diminta = [];
            public array $data = [];
            public ?GagalAsisten $galat = null;

            public function minta(Permintaan $permintaan): Jawaban
            {
                $this->diminta[] = $permintaan;
                if ($this->galat) throw $this->galat;

                return new Jawaban($this->data, 'claude-opus-5-5', 1234, 567);
            }
        };
        $this->app->instance(KlienAi::class, $this->palsu);
    }

    private function urlButir(?SmkpAudit $a = null): string
    {
        return '/asisten/smkp/'.($a ?? $this->audit)->id.'/butir';
    }

    private function urlTemuan(): string
    {
        return "/asisten/smkp/{$this->audit->id}/temuan/{$this->temuan->id}";
    }

    private function jawabanButir(): array
    {
        return [
            'nilai_saran' => 9, 'tidak_berlaku' => false, 'keyakinan' => 'SEDANG',
            'alasan' => 'Konsultasi risiko ada tetapi belum terjadwal.',
            'bukti_verifikasi' => [
                ['jenis' => 'dokumen', 'uraian' => 'Prosedur komunikasi dan konsultasi risiko'],
                ['jenis' => 'asing', 'uraian' => 'Daftar hadir sosialisasi risiko'],
                ['jenis' => 'wawancara', 'uraian' => '   '],
            ],
            'pertanyaan' => ['Bagaimana hasil konsultasi risiko dikomunikasikan?', ''],
            'draf_temuan' => 'Konsultasi risiko belum dilaksanakan secara terjadwal.',
            'catatan_ringkas' => 'Perlu jadwal konsultasi risiko.',
        ];
    }

    /* ================= aktif / akses / jangkauan ================= */

    public function test_tanpa_kunci_asisten_nonaktif_503(): void
    {
        config(['asisten.aktif' => true, 'bantuan.ai.kunci' => null]);
        $u = $this->auditor();

        $this->actingAs($u)->postJson($this->urlButir(), ['kode' => 'II.2.1'])
            ->assertStatus(503)->assertJson(['jenis' => 'nonaktif'])
            ->assertJsonPath('pesan', fn ($p) => str_contains($p, 'Pusat Kendali → Asisten AI'));
        $this->actingAs($u)->postJson($this->urlTemuan())->assertStatus(503);

        $this->assertSame([], $this->palsu->diminta);
        $this->actingAs($u)->get('/smkp')->assertInertia(fn (Inertia $p) => $p
            ->where('asisten.aktif', false)->where('asisten.model', 'gemini-uji')->missing('asisten.kunci'));
    }

    public function test_status_aktif_dibagikan_tanpa_kunci(): void
    {
        $this->actingAs($this->auditor())->get('/smkp')
            ->assertInertia(fn (Inertia $p) => $p->where('asisten.aktif', true)->where('asisten', fn ($a) => count($a) === 2))
            ->assertDontSee('kunci-uji-rahasia');

        // EQOHSEE: di luar modul audit SMKP nama penyedia & model tidak dikirim ke halaman.
        $this->actingAs($this->auditor())->get('/dashboard')->assertInertia(fn (Inertia $p) => $p->where('asisten', null));
    }

    public function test_tamu_dan_auditi_ditolak(): void
    {
        $this->postJson($this->urlButir(), ['kode' => 'II.2.1'])->assertUnauthorized();

        $auditi = $this->auditi($this->induk);
        $this->actingAs($auditi)->postJson($this->urlButir(), ['kode' => 'II.2.1'])->assertForbidden();
        $this->actingAs($auditi)->postJson($this->urlTemuan())->assertForbidden();

        $this->assertSame([], $this->palsu->diminta);
    }

    public function test_auditor_di_luar_jangkauan_tidak_dapat_bertanya(): void
    {
        $this->palsu->data = $this->jawabanButir();
        $luar = $this->auditor($this->sister);

        $this->actingAs($luar)->postJson($this->urlButir(), ['kode' => 'II.2.1'])->assertNotFound();
        $this->actingAs($luar)->postJson($this->urlTemuan())->assertNotFound();

        // Temuan audit lain tidak dapat dipasangkan ke audit milik sendiri.
        $milikSendiri = SmkpAudit::withoutGlobalScopes()->create(['company_id' => $this->sister->id, 'tahun' => 2026, 'status' => 'berjalan', 'tahap' => 2, 'hasil' => []]);
        $this->actingAs($luar)->postJson("/asisten/smkp/{$milikSendiri->id}/temuan/{$this->temuan->id}")->assertNotFound();

        $this->assertSame([], $this->palsu->diminta);

        // Auditor PJP tidak menjangkau induknya; auditor induk menjangkau PJP-nya.
        $this->actingAs($this->auditor($this->pjp))->postJson($this->urlButir(), ['kode' => 'II.2.1'])->assertNotFound();
        $this->actingAs($this->auditor($this->induk))->postJson($this->urlButir(), ['kode' => 'II.2.1'])->assertOk();
    }

    /* ================= validasi ================= */

    public function test_validasi_masukan(): void
    {
        $u = $this->auditor();

        $this->actingAs($u)->postJson($this->urlButir(), [])->assertUnprocessable()->assertJsonValidationErrors('kode');
        $this->actingAs($u)->postJson($this->urlButir(), ['kode' => 'XX.9'])->assertUnprocessable()->assertJsonValidationErrors('kode');
        // II.2.1 bernilai maksimum 4.
        $this->actingAs($u)->postJson($this->urlButir(), ['kode' => 'II.2.1', 'v' => '5'])->assertUnprocessable()->assertJsonValidationErrors('v');
        $this->actingAs($u)->postJson($this->urlButir(), ['kode' => 'II.2.1', 'v' => 'abc'])->assertUnprocessable()->assertJsonValidationErrors('v');
        $this->actingAs($u)->postJson($this->urlButir(), ['kode' => 'II.2.1', 'ket' => str_repeat('a', 2001)])->assertUnprocessable()->assertJsonValidationErrors('ket');
        $this->actingAs($u)->postJson($this->urlTemuan(), ['tindakan' => str_repeat('a', 2001)])->assertUnprocessable()->assertJsonValidationErrors('tindakan');

        $this->assertSame([], $this->palsu->diminta);
    }

    /* ================= tiga tugas ================= */

    public function test_penilaian_butir_dirapikan_dan_permintaannya_terbatas(): void
    {
        $this->palsu->data = $this->jawabanButir();
        $u = $this->auditor();

        $r = $this->actingAs($u)->postJson($this->urlButir(), [
            'kode' => 'II.2.1', 'v' => '3', 'ket' => 'Catatan rahasia auditor X', 'bukti' => 'SOP-RSK-01',
        ])->assertOk();

        $r->assertJson([
            'tugas' => 'butir', 'kode' => 'II.2.1', 'maks' => 4, 'model' => 'claude-opus-5-5',
            'nilai' => ['saran' => 4, 'na' => false, 'v' => '4', 'keyakinan' => 'sedang'],   // 9 dijepit ke maks 4
            'pertanyaan' => ['Bagaimana hasil konsultasi risiko dikomunikasikan?'],
            'draf_temuan' => 'Konsultasi risiko belum dilaksanakan secara terjadwal.',
            'catatan' => 'Perlu jadwal konsultasi risiko.',
        ]);
        $this->assertSame([
            ['jenis' => 'dokumen', 'label' => 'Dokumen', 'uraian' => 'Prosedur komunikasi dan konsultasi risiko'],
            ['jenis' => 'dokumen', 'label' => 'Dokumen', 'uraian' => 'Daftar hadir sosialisasi risiko'],
        ], $r->json('bukti'));

        $p = $this->palsu->diminta[0];
        $this->assertSame('butir', $p->tugas);
        $this->assertSame('object', $p->skema['type']);
        $this->assertStringContainsString('II.2.1 Komunikasi dan Konsultasi Risiko', $p->pesan);
        $this->assertStringContainsString('3 dari 4', $p->pesan);
        // Kriteria resmi tiap nilai ikut dikirim agar saran berpijak pada Kepdirjen.
        $this->assertStringContainsString('- Nilai 0: Tidak ada bukti', $p->pesan);
        $this->assertStringContainsString('- Nilai 4: Terdapat bukti', $p->pesan);
        $this->assertStringContainsString('hal. 419', $p->pesan);
        $this->assertStringContainsString('SOP-RSK-01', $p->pesan);
        $this->assertStringContainsString('Batu Kapur', $p->pesan);
        $this->assertStringContainsString('185.K/37.04/DJB/2019', $p->sistem);
        // Tanpa nama perusahaan maupun data pribadi.
        $this->assertStringNotContainsString('PT Induk Uji', $p->pesan);
        $this->assertStringNotContainsString($u->email, $p->pesan);
        $this->assertStringNotContainsString($u->name, $p->pesan);

        // Belum menyimpan apa pun ke audit.
        $this->assertSame(2, $this->audit->fresh()->hasil['II.2.1']['v']);
    }

    public function test_nilai_skala_lama_tidak_ditolak_dan_tidak_ditulis_sebagai_nilai_sekarang(): void
    {
        $this->palsu->data = $this->jawabanButir();
        $h = $this->audit->hasil;
        $h['III.2.1'] = ['v' => 4, 'ket' => '', 'bukti' => 'SK KTT'];
        $h['III.2.2'] = ['v' => 3, 'maks_lama' => 4, 'ket' => '', 'bukti' => ''];
        $this->audit->update(['hasil' => $h]);

        // Formulir masih menampilkan nilai lama 4 (maks kini 2): bukan galat 422.
        $this->actingAs($this->auditor())->postJson($this->urlButir(), ['kode' => 'III.2.1', 'v' => '4'])
            ->assertOk()->assertJson(['maks' => 2]);
        $p = $this->palsu->diminta[0]->pesan;
        $this->assertStringContainsString('Nilai auditor saat ini: belum dinilai', $p);
        $this->assertStringContainsString('Nilai tersimpan sebelumnya: 4, di atas nilai maksimum 2', $p);
        $this->assertStringContainsString('III.2.2 Penunjukan Kepala Tambang Bawah Tanah (maks 2): 3 dari skala lama 0-4', $p);
        $this->assertStringNotContainsString('3 dari 2', $p);

        // Nilai lain di atas maksimum tetap ditolak.
        $this->actingAs($this->auditor())->postJson($this->urlButir(), ['kode' => 'III.2.3', 'v' => '3'])->assertStatus(422);
    }

    public function test_butir_tidak_berlaku(): void
    {
        $this->palsu->data = ['tidak_berlaku' => true, 'nilai_saran' => 2] + $this->jawabanButir();

        $this->actingAs($this->auditor())->postJson($this->urlButir(), ['kode' => 'II.2.1', 'v' => 'n/a'])
            ->assertOk()->assertJson(['nilai' => ['saran' => null, 'na' => true, 'v' => 'N/A']]);
        $this->assertStringContainsString('N/A', $this->palsu->diminta[0]->pesan);
    }

    public function test_analisis_temuan_menghasilkan_usulan_isian(): void
    {
        $this->palsu->data = [
            'akar_masalah' => [
                'kategori' => 'metode',
                'lima_mengapa' => [
                    ['tanya' => 'Mengapa konsultasi tidak rutin?', 'jawab' => 'Tidak ada jadwal.'],
                    ['tanya' => 'Mengapa tidak ada jadwal?', 'jawab' => 'Prosedur tidak mengatur frekuensi.'],
                ],
                'pernyataan' => 'Prosedur manajemen risiko tidak menetapkan frekuensi konsultasi.',
            ],
            'koreksi_segera' => 'Adakan konsultasi risiko bulan ini.',
            'korektif'  => [['tindakan' => 'Revisi prosedur manajemen risiko', 'pic' => 'Kepala Teknik Tambang', 'hari' => 30, 'bukti_penutupan' => 'Prosedur revisi']],
            'preventif' => [['tindakan' => 'Masukkan jadwal ke rencana kerja K3', 'pic' => 'Bagian K3 Pertambangan', 'hari' => 999, 'bukti_penutupan' => 'Rencana kerja']],
            'target_hari' => 45,
            'bukti_penutupan' => ['Prosedur revisi yang disahkan', 'Notulen konsultasi'],
            'tingkat_risiko' => 'tinggi', 'alasan_risiko' => 'Risiko tidak terkomunikasikan.',
        ];

        $r = $this->actingAs($this->auditor())->postJson($this->urlTemuan(), ['akar_masalah' => 'Draf auditor'])->assertOk();

        $r->assertJson([
            'tugas' => 'temuan', 'id' => $this->temuan->id, 'kode' => 'II.2',
            'akar' => ['kategori' => 'metode', 'label' => 'Metode'],
            'target' => ['hari' => 45, 'tanggal' => \App\Support\Waktu::kini()->startOfDay()->addDays(45)->toDateString()],
            'risiko' => ['tingkat' => 'tinggi', 'label' => 'Tinggi'],
            'isian' => ['penanggung_jawab' => 'Kepala Teknik Tambang', 'target_selesai' => \App\Support\Waktu::kini()->startOfDay()->addDays(45)->toDateString()],
        ]);
        $this->assertSame(365, $r->json('preventif.0.hari'));
        $this->assertStringContainsString('[Metode] Prosedur manajemen risiko', $r->json('isian.akar_masalah'));
        $this->assertStringContainsString('1. Mengapa konsultasi tidak rutin?', $r->json('isian.akar_masalah'));
        $this->assertStringContainsString('Koreksi segera: Adakan konsultasi', $r->json('isian.tindakan'));
        $this->assertStringContainsString('(PIC: Kepala Teknik Tambang, 30 hari)', $r->json('isian.tindakan'));
        $this->assertStringContainsString('- Notulen konsultasi', $r->json('isian.verifikasi'));
        foreach (['akar_masalah', 'tindakan', 'verifikasi'] as $k) $this->assertLessThanOrEqual(2000, mb_strlen($r->json("isian.$k")));

        $p = $this->palsu->diminta[0]->pesan;
        $this->assertStringContainsString('Ketidaksesuaian Mayor', $p);
        $this->assertStringContainsString('Notulen P2K3 Maret', $p);
        $this->assertStringContainsString('Draf auditor', $p);

        // Formulir temuan tidak berubah sampai auditor menyimpan.
        $this->assertNull($this->temuan->fresh()->akar_masalah);
    }

    /* ================= batas & log ================= */

    public function test_batas_per_jam_ditegakkan(): void
    {
        config(['asisten.batas_per_jam' => 2]);
        $this->palsu->data = $this->jawabanButir();
        $u = $this->auditor();

        $this->actingAs($u)->postJson($this->urlButir(), ['kode' => 'II.2.1'])->assertOk();
        $this->actingAs($u)->postJson($this->urlButir(), ['kode' => 'II.2.1'])->assertOk();
        $this->actingAs($u)->postJson($this->urlButir(), ['kode' => 'II.2.1'])
            ->assertStatus(429)->assertJson(['jenis' => 'batas'])->assertJsonPath('pesan', fn ($p) => str_contains($p, '2 permintaan per jam'));

        // Batasnya per pengguna.
        $this->actingAs($this->auditor())->postJson($this->urlButir(), ['kode' => 'II.2.1'])->assertOk();
        $this->assertCount(3, $this->palsu->diminta);
    }

    public function test_setiap_permintaan_dicatat_tanpa_isi_prompt(): void
    {
        $this->palsu->data = $this->jawabanButir();
        $u = $this->auditor();

        $this->actingAs($u)->postJson($this->urlButir(), ['kode' => 'II.2.1', 'ket' => 'Catatan rahasia auditor X'])->assertOk();

        $log = ActivityLog::where('module', 'asisten')->sole();
        $this->assertSame($u->id, $log->user_id);
        $this->assertSame('Asisten AI · Penilaian butir SMKP', $log->action);
        $this->assertStringContainsString('butir II.2.1', $log->detail);
        $this->assertStringContainsString('model claude-opus-5-5', $log->detail);
        $this->assertStringContainsString('token masuk 1.234 · keluar 567', $log->detail);
        $this->assertStringNotContainsString('rahasia', $log->detail);
        $this->assertStringNotContainsString('Komunikasi dan konsultasi', $log->detail);

        // Kegagalan juga dicatat, dengan jenisnya.
        $this->palsu->galat = GagalAsisten::sibuk();
        $this->actingAs($u)->postJson($this->urlTemuan())->assertStatus(503)->assertJson(['jenis' => 'sibuk']);
        $gagal = ActivityLog::where('module', 'asisten')->latest('id')->first();
        $this->assertSame('Asisten AI · Analisis temuan SMKP', $gagal->action);
        $this->assertStringContainsString('gagal: sibuk', $gagal->detail);
    }


}
