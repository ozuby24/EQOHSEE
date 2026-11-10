<?php

namespace Tests\Feature;

use App\Services\Asisten\{GagalAsisten, KlienAi, KlienEqohsee, KonfigurasiAsisten, Lampiran, Permintaan};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Penyesuai Asisten AI audit SMKP ke sambungan AI EQOHSEE
 * (App\Support\Ai): permintaan berskema JSON + lampiran diteruskan ke
 * penyedia yang dipilih di Pusat Kendali, jawabannya diurai, galatnya
 * diterjemahkan menjadi GagalAsisten. Pengganti uji klien Claude/OpenAI/
 * Gemini milik Aurorasys — penyedianya sendiri diuji App\Support\Ai.
 */
class AsistenSambunganTest extends TestCase
{
    use RefreshDatabase;

    private const GEMINI = 'generativelanguage.googleapis.com/*';

    protected function setUp(): void
    {
        parent::setUp();
        config(['asisten.aktif' => true, 'asisten.ulang' => 0, 'bantuan.ai.kunci' => 'kunci-uji', 'bantuan.ai.model' => 'gemini-uji']);
    }

    private function permintaan(array $lampiran = []): Permintaan
    {
        return new Permintaan('butir', 'Instruksi sistem uji.', 'Isi tugas uji.',
            ['type' => 'object', 'properties' => ['nilai_saran' => ['type' => 'integer']], 'required' => ['nilai_saran']], $lampiran);
    }

    private static function jawabGemini(string $teks, string $berhenti = 'STOP'): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => $teks]]], 'finishReason' => $berhenti]]];
    }

    public function test_kontainer_memakai_klien_eqohsee_dan_status_mengikuti_sambungan_ai(): void
    {
        $this->assertInstanceOf(KlienEqohsee::class, app(KlienAi::class));
        $this->assertTrue(KonfigurasiAsisten::aktif());
        $this->assertSame('gemini-uji', KonfigurasiAsisten::model());

        config(['asisten.aktif' => false]);
        $this->assertFalse(KonfigurasiAsisten::aktif(), 'Sakelar khusus asisten audit tetap berlaku.');

        config(['asisten.aktif' => true, 'bantuan.ai.kunci' => null]);
        $this->assertFalse(KonfigurasiAsisten::aktif(), 'Tanpa kunci sambungan AI, asisten nonaktif.');
    }

    public function test_skema_dan_lampiran_diteruskan_lalu_jawaban_json_diurai(): void
    {
        Http::fake([self::GEMINI => Http::response(self::jawabGemini("```json\n{\"nilai_saran\": 3}\n```"))]);

        $pdf = new Lampiran('notulen.pdf', 'application/pdf', '%PDF-1.4 isi');
        $docx = new Lampiran('prosedur.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, 'Isi prosedur IBPR');

        $j = (new KlienEqohsee(maksToken: 8000, jeda: 30, ulang: 0))->minta($this->permintaan([$pdf, $docx]));

        $this->assertSame(['nilai_saran' => 3], $j->data);
        $this->assertSame('gemini-uji', $j->model);

        Http::assertSent(function (Request $r) {
            $b = $r->data();
            $sistem = $b['systemInstruction']['parts'][0]['text'];
            $bagian = $b['contents'][0]['parts'];

            return str_contains($r->url(), 'models/gemini-uji:generateContent')
                && str_contains($sistem, 'Instruksi sistem uji.')
                && str_contains($sistem, '"nilai_saran"')                      // skema ikut instruksi
                && $b['generationConfig']['responseMimeType'] === 'application/json'
                && $b['generationConfig']['maxOutputTokens'] === 8000
                && $bagian[0]['inlineData'] === ['mimeType' => 'application/pdf', 'data' => base64_encode('%PDF-1.4 isi')]
                && str_contains(end($bagian)['text'], 'Isi tugas uji.')
                && str_contains(end($bagian)['text'], '=== Berkas: prosedur.docx (teks hasil ekstraksi) ===')
                && str_contains(end($bagian)['text'], 'Isi prosedur IBPR');
        });
    }

    public function test_galat_penyedia_diterjemahkan(): void
    {
        $klien = new KlienEqohsee(ulang: 0);
        $kasus = [
            [Http::response(['error' => ['message' => 'kuota']], 429), 'sibuk', 503],
            [Http::response(['error' => ['message' => 'kunci']], 401), 'kunci', 502],
            [Http::response(['error' => ['message' => 'model']], 404), 'permintaan', 502],
            [Http::response(self::jawabGemini('{"nilai_saran": 1', 'MAX_TOKENS')), 'terpotong', 502],
            [Http::response(self::jawabGemini('bukan json sama sekali')), 'rusak', 502],
            [Http::response(self::jawabGemini('[1, 2, 3]')), 'rusak', 502],
        ];

        foreach ($kasus as [$jawab, $jenis, $status]) {
            Http::fake([self::GEMINI => $jawab]);
            try {
                $klien->minta($this->permintaan());
                $this->fail("Galat {$jenis} tidak dilempar.");
            } catch (GagalAsisten $e) {
                $this->assertSame([$jenis, $status], [$e->jenis, $e->status]);
                $this->assertStringNotContainsString('kunci-uji', $e->getMessage());
            }
            Http::swap(new \Illuminate\Http\Client\Factory());
        }
    }

    public function test_tanpa_kunci_tidak_menghubungi_penyedia(): void
    {
        config(['bantuan.ai.kunci' => null]);
        Http::fake();

        try {
            (new KlienEqohsee())->minta($this->permintaan());
            $this->fail('Asisten tanpa kunci harus nonaktif.');
        } catch (GagalAsisten $e) {
            $this->assertSame(['nonaktif', 503], [$e->jenis, $e->status]);
        }
        Http::assertNothingSent();
    }
}
