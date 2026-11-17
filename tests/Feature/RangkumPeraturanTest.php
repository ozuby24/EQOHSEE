<?php

namespace Tests\Feature;

use App\Models\{ComplianceSubject, User};
use App\Support\{Ai, AiPenyedia, AnalisisPeraturan, PemecahPeraturan};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as PermintaanHttp;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Http, Log};
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Unggah & Rangkum peraturan, dari naskah sampai analisis AI.
 *
 * Fitur ini sempat "tidak berfungsi" tanpa satu pun galat: hasilnya
 * dititipkan lewat flash yang tidak pernah sampai ke halaman, AI-nya
 * diminta menjawab seratus butir dalam 900 token sehingga jawabannya
 * selalu terpotong, dan naskah lebih dari 120 ayat terpotong diam-diam.
 * Contoh-contoh naskah di bawah meniru bentuk PDF JDIH yang sebenarnya:
 * nomor halaman di tengah ayat, judul BAB yang menempel, rujukan ayat
 * yang terlipat, dan penjelasan yang mengulang seluruh pasal.
 */
class RangkumPeraturanTest extends TestCase
{
    use RefreshDatabase;

    private function masuk(bool $admin = true): User
    {
        $u = User::factory()->create(['is_admin' => $admin]);
        $this->actingAs($u);

        return $u;
    }

    private function aiGemini(): void
    {
        Ai::simpanKunci(AiPenyedia::GEMINI, 'kunci-uji-gemini-123456');
        Ai::simpanPengaturan(AiPenyedia::GEMINI, 'gemini-flash-latest', null);
    }

    private function jawabGemini(array $isi, string $alasan = 'STOP'): array
    {
        return ['candidates' => [[
            'content' => ['parts' => [['text' => json_encode($isi, JSON_UNESCAPED_UNICODE)]]],
            'finishReason' => $alasan,
        ]]];
    }

    private const NASKAH = <<<'TXT'
    PERATURAN MENTERI ENERGI DAN SUMBER DAYA MINERAL
    REPUBLIK INDONESIA
    NOMOR 26 TAHUN 2018
    TENTANG
    PELAKSANAAN KAIDAH PERTAMBANGAN YANG BAIK DAN PENGAWASAN
    PERTAMBANGAN MINERAL DAN BATUBARA
    DENGAN RAHMAT TUHAN YANG MAHA ESA
    MENTERI ENERGI DAN SUMBER DAYA MINERAL REPUBLIK INDONESIA,
    Mengingat : 1. Peraturan Pemerintah Nomor 55 Tahun 2010 tentang Pembinaan;
    MEMUTUSKAN:
    Menetapkan : PERATURAN MENTERI ENERGI DAN SUMBER DAYA MINERAL TENTANG PELAKSANAAN KAIDAH
    PERTAMBANGAN YANG BAIK DAN PENGAWASAN PERTAMBANGAN MINERAL DAN BATUBARA.
    BAB I
    KETENTUAN UMUM
    Pasal 1
    Dalam Peraturan Menteri ini yang dimaksud dengan:
    1. Kepala Teknik Tambang yang selanjutnya disingkat KTT adalah seseorang yang memimpin.
    BAB II
    KAIDAH TEKNIK
    Bagian Kesatu
    Umum
    Pasal 3
    (1) Pemegang IUP wajib melaksanakan kaidah pertambangan yang baik.
    (2) Kaidah pertambangan yang baik sebagaimana dimaksud pada ayat
    (1) meliputi teknik dan tata kelola.
    - 4 -
    (3) Pemegang IUP wajib menyusun laporan
    - 5 -
    tahunan kepada Menteri.
    Pasal 7
    (1) Pemegang IUP wajib mengangkat KTT. (2) KTT wajib memiliki kompetensi.
    Pasal 61
    Peraturan Menteri ini mulai berlaku pada tanggal diundangkan.
    Ditetapkan di Jakarta
    pada tanggal 3 Mei 2018
    MENTERI ENERGI DAN SUMBER DAYA MINERAL,
    PENJELASAN
    Pasal 1
    Cukup jelas.
    TXT;

    /* ═══════════════ pemecah ═══════════════ */

    public function test_pemecah_membersihkan_sisa_tata_letak_pdf(): void
    {
        $b = collect(PemecahPeraturan::pecah(self::NASKAH))->keyBy('penunjuk');

        $this->assertSame(
            ['Pasal 1', 'Pasal 3 Ayat (1)', 'Pasal 3 Ayat (2)', 'Pasal 3 Ayat (3)', 'Pasal 7 Ayat (1)', 'Pasal 7 Ayat (2)', 'Pasal 61'],
            $b->keys()->all(),
            'Penjelasan ikut menggandakan pasal, atau rujukan "ayat (1)" yang terlipat terbaca sebagai ayat baru.');

        // Judul BAB/Bagian tidak menempel pada pasal sebelumnya.
        $this->assertStringNotContainsString('BAB', $b['Pasal 1']['isi']);
        $this->assertStringNotContainsString('Bagian', $b['Pasal 1']['isi']);
        // Rujukan yang terlipat tetap milik ayat (2).
        $this->assertStringContainsString('ayat (1) meliputi teknik', $b['Pasal 3 Ayat (2)']['isi']);
        // Nomor halaman di tengah ayat dibuang.
        $this->assertSame('Pemegang IUP wajib menyusun laporan tahunan kepada Menteri.', $b['Pasal 3 Ayat (3)']['isi']);
        // Blok penetapan tidak ikut ke pasal terakhir.
        $this->assertSame('Peraturan Menteri ini mulai berlaku pada tanggal diundangkan.', $b['Pasal 61']['isi']);
    }

    public function test_pemecah_mengenal_pasal_romawi_peraturan_perubahan(): void
    {
        $b = PemecahPeraturan::pecah("Pasal I\nBeberapa ketentuan diubah.\nPasal II\nMulai berlaku saat diundangkan.");

        $this->assertSame(['Pasal I', 'Pasal II'], array_column($b, 'penunjuk'));
    }

    public function test_identitas_dibaca_dari_kepala_dan_penetapan(): void
    {
        $i = PemecahPeraturan::identitas(self::NASKAH);

        // "Peraturan Pemerintah" di daftar Mengingat tidak menjadikannya PP.
        $this->assertSame('Peraturan Menteri', $i['jenis']);
        $this->assertSame('Permen ESDM Nomor 26 Tahun 2018', $i['nomor']);
        $this->assertSame('Pelaksanaan Kaidah Pertambangan yang Baik dan Pengawasan Pertambangan Mineral dan Batubara', $i['judul']);
        $this->assertSame('2018-05-03', $i['tanggal_terbit']);
        $this->assertSame('Kementerian Energi dan Sumber Daya Mineral', $i['instansi']);
    }

    public function test_identitas_tanpa_halaman_judul_tidak_menebak_nomor(): void
    {
        $tanpaJudul = substr(self::NASKAH, strpos(self::NASKAH, 'Mengingat'));
        $i = PemecahPeraturan::identitas($tanpaJudul);

        $this->assertSame('Peraturan Menteri', $i['jenis'], 'Jenis dari kalimat "Menetapkan :".');
        $this->assertSame('', $i['nomor']);
        $this->assertStringStartsWith('Pelaksanaan Kaidah', $i['judul']);
    }

    /* ═══════════════ baca & pecah ═══════════════ */

    public function test_rangkum_memulangkan_json_langsung_ke_halaman(): void
    {
        $this->masuk();

        $r = $this->postJson(route('kepatuhan.rangkum'), ['teks' => self::NASKAH])->assertOk();

        $r->assertJsonPath('total', 7)
          ->assertJsonPath('butir.0.no', 1)
          ->assertJsonPath('butir.1.penunjuk', 'Pasal 3 Ayat (1)')
          ->assertJsonPath('identitas.nomor', 'Permen ESDM Nomor 26 Tahun 2018')
          ->assertJsonPath('otomatis', false)
          ->assertJsonPath('token', null);
    }

    public function test_rangkum_tanpa_naskah_ditolak_dengan_pesan(): void
    {
        $this->masuk();

        $this->postJson(route('kepatuhan.rangkum'), [])->assertStatus(422)->assertJsonValidationErrors('teks');
    }

    /**
     * Galat validasi tetap JSON, bukan pengalihan.
     *
     * bootstrap/app.php merender galat sebagai JSON hanya untuk api/*;
     * `$request->validate()` di rute web memulangkan pengalihan, yang
     * diikuti fetch lalu terbaca sebagai HTML ber-status 200.
     */
    public function test_berkas_salah_jenis_ditolak_sebagai_json_bukan_pengalihan(): void
    {
        $this->masuk();

        $this->post(route('kepatuhan.rangkum'), ['berkas' => UploadedFile::fake()->image('foto.jpg')],
                    ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('berkas');

        $this->postJson(route('kepatuhan.rangkum.baca'), ['token' => 'bukan-uuid', 'dari' => 1, 'sampai' => 1])
            ->assertStatus(422)->assertJsonValidationErrors('token');
    }

    public function test_rangkum_dari_berkas_txt(): void
    {
        $this->masuk();

        $f = UploadedFile::fake()->createWithContent('permen.txt', self::NASKAH);

        $this->post(route('kepatuhan.rangkum'), ['berkas' => $f], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('total', 7);
    }

    public function test_naskah_panjang_disebut_berapa_yang_terpotong(): void
    {
        $this->masuk();

        $naskah = '';
        for ($i = 1; $i <= PemecahPeraturan::MAKS_BUTIR + 7; $i++) {
            $naskah .= "Pasal {$i}\nPemegang IUP wajib melaksanakan kewajiban ke-{$i}.\n";
        }

        $r = $this->postJson(route('kepatuhan.rangkum'), ['teks' => $naskah])->assertOk();

        $this->assertCount(PemecahPeraturan::MAKS_BUTIR, $r->json('butir'));
        $this->assertSame(PemecahPeraturan::MAKS_BUTIR + 7, $r->json('total'));
        $this->assertStringContainsString('7 butir', implode(' ', $r->json('catatan')));
    }

    /* ═══════════════ analisis AI ═══════════════ */

    public function test_analisis_butir_dipasangkan_menurut_nomor_bukan_urutan(): void
    {
        $this->masuk();
        $this->aiGemini();

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->jawabGemini(['butir' => [
            ['no' => 5, 'kewajiban' => false, 'rangkuman' => 'Definisi KTT.', 'penerapan' => ''],
            ['no' => 3, 'kewajiban' => true, 'rangkuman' => 'Pemegang IUP wajib menerapkan kaidah.', 'penerapan' => 'SOP kaidah teknik.'],
            ['no' => 99, 'kewajiban' => true, 'rangkuman' => 'Butir yang tidak diminta.', 'penerapan' => 'x'],
        ]]))]);

        $r = $this->postJson(route('kepatuhan.rangkum.analisis'), ['butir' => [
            ['no' => 3, 'penunjuk' => 'Pasal 3 Ayat (1)', 'isi' => 'Pemegang IUP wajib melaksanakan kaidah pertambangan.'],
            ['no' => 4, 'penunjuk' => 'Pasal 3 Ayat (2)', 'isi' => 'Kaidah meliputi teknik.'],
            ['no' => 5, 'penunjuk' => 'Pasal 1', 'isi' => 'KTT adalah seseorang yang memimpin.'],
        ]])->assertOk();

        $r->assertJsonPath('ok', true)->assertJsonPath('hilang', [4]);
        $butir = collect($r->json('butir'))->keyBy('no');
        $this->assertSame(['3', '5'], array_map('strval', $butir->keys()->sort()->values()->all()));
        $this->assertTrue($butir[3]['kewajiban']);
        $this->assertFalse($butir[5]['kewajiban']);

        Http::assertSent(function (PermintaanHttp $q) {
            $cfg = $q->data()['generationConfig'] ?? [];

            return ($cfg['responseMimeType'] ?? null) === 'application/json'
                && ($cfg['maxOutputTokens'] ?? 0) >= 8192;
        });
    }

    public function test_jawaban_terpotong_dilaporkan_bukan_dianggap_jawaban(): void
    {
        $this->masuk();
        $this->aiGemini();
        Sleep::fake();

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '{"butir":[{"no":1,"rangk']]], 'finishReason' => 'MAX_TOKENS']],
        ])]);

        $this->postJson(route('kepatuhan.rangkum.analisis'), ['butir' => [
            ['no' => 1, 'penunjuk' => 'Pasal 3', 'isi' => 'Pemegang IUP wajib.'],
        ]])->assertOk()
          ->assertJsonPath('ok', false)
          ->assertJsonPath('hilang', [1])
          ->assertJsonPath('pesan', AnalisisPeraturan::PESAN_SIBUK);
    }

    public function test_analisis_tanpa_kunci_ai_menjawab_409(): void
    {
        $this->masuk();

        $this->postJson(route('kepatuhan.rangkum.analisis'), ['butir' => [
            ['no' => 1, 'penunjuk' => 'Pasal 3', 'isi' => 'Pemegang IUP wajib.'],
        ]])->assertStatus(409)->assertJsonPath('ok', false);
    }

    public function test_giliran_lebih_besar_dari_batas_ditolak(): void
    {
        $this->masuk();
        $this->aiGemini();

        $butir = array_map(fn ($i) => ['no' => $i, 'penunjuk' => "Pasal {$i}", 'isi' => 'Wajib.'],
            range(1, AnalisisPeraturan::PER_GILIRAN + 1));

        $this->postJson(route('kepatuhan.rangkum.analisis'), ['butir' => $butir])->assertStatus(422);
    }

    /**
     * Halaman ini dipakai pengguna biasa. Pesan penyedia menyebut nama
     * penyedia, model, bahkan proyeknya — tidak satu pun boleh sampai ke
     * halaman, juga untuk administrator. Rinciannya hanya di log server.
     */
    public function test_galat_penyedia_tidak_pernah_sampai_ke_halaman(): void
    {
        Sleep::fake();
        Log::spy();
        $this->aiGemini();
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(
            ['error' => ['message' => 'quota gemini-flash-latest proyek-rahasia']], 429)]);
        $isi = ['butir' => [['no' => 1, 'penunjuk' => 'Pasal 3', 'isi' => 'Pemegang IUP wajib.']]];

        foreach ([true, false] as $admin) {
            $this->masuk($admin);
            $r = $this->postJson(route('kepatuhan.rangkum.analisis'), $isi)->assertOk();

            $r->assertJsonPath('ok', false)
              ->assertJsonPath('hilang', [1])
              ->assertJsonPath('pesan', AnalisisPeraturan::PESAN_SIBUK)
              ->assertJsonMissingPath('galat');
            $this->assertDoesNotMatchRegularExpression('/gemini|google|anthropic|claude|openai|gpt|proyek-rahasia|\bAI\b/i', $r->getContent());
        }

        Log::shouldHaveReceived('warning')->withArgs(fn ($pesan, $isi = []) =>
            $pesan === 'Analisis peraturan gagal' && str_contains((string) ($isi['galat'] ?? ''), 'proyek-rahasia'));
    }

    public function test_penyedia_sibuk_diulang_lalu_berhasil(): void
    {
        $this->masuk();
        $this->aiGemini();
        Sleep::fake();

        Http::fake(['generativelanguage.googleapis.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'overloaded']], 503)
            ->push(['error' => ['message' => 'rate']], 429, ['Retry-After' => '5'])
            ->push($this->jawabGemini(['butir' => [
                ['no' => 1, 'kewajiban' => true, 'rangkuman' => 'Wajib kaidah.', 'penerapan' => 'SOP.'],
            ]]))]);

        $this->postJson(route('kepatuhan.rangkum.analisis'), ['butir' => [
            ['no' => 1, 'penunjuk' => 'Pasal 3', 'isi' => 'Pemegang IUP wajib.'],
        ]])->assertOk()->assertJsonPath('ok', true)->assertJsonPath('butir.0.rangkuman', 'Wajib kaidah.');

        Http::assertSentCount(3);
        Sleep::assertSequence([Sleep::for(3)->seconds(), Sleep::for(5)->seconds()]);
    }

    public function test_galat_yang_bukan_sementara_tidak_diulang(): void
    {
        $this->masuk();
        $this->aiGemini();
        Sleep::fake();

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'API key not valid']], 400)]);

        $this->postJson(route('kepatuhan.rangkum.analisis'), ['butir' => [
            ['no' => 1, 'penunjuk' => 'Pasal 3', 'isi' => 'Pemegang IUP wajib.'],
        ]])->assertOk()->assertJsonPath('ok', false);

        Http::assertSentCount(1);
        Sleep::assertNeverSlept();
    }

    public function test_identitas_ai_disaring_ke_daftar_yang_sah(): void
    {
        $this->masuk();
        $this->aiGemini();

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response($this->jawabGemini([
            'jenis' => 'Peraturan Menteri', 'nomor' => 'Permen ESDM Nomor 26 Tahun 2018',
            'judul' => 'Pelaksanaan Kaidah Pertambangan yang Baik', 'tanggal_terbit' => '2018-02-30',
            'instansi' => 'Kementerian ESDM', 'aspek' => 'bukan-aspek',
            'ruang_lingkup' => 'Pemegang IUP dan IUPK.', 'rangkuman' => 'Mengatur kaidah teknik.',
        ]))]);

        $this->postJson(route('kepatuhan.rangkum.identitas'), ['naskah' => self::NASKAH])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('identitas.jenis', 'Peraturan Menteri')
            ->assertJsonPath('identitas.tanggal_terbit', '')   // 30 Februari tidak ada
            ->assertJsonPath('identitas.aspek', '')
            ->assertJsonPath('identitas.ruang_lingkup', 'Pemegang IUP dan IUPK.');
    }

    /* ═══════════════ halaman PDF berupa gambar ═══════════════ */

    /** PDF dua halaman: halaman 1 berteks, halaman 2 kosong (seperti hasil pindaian). */
    private function pdfSeparuhGambar(): string
    {
        $isi1 = "BT /F1 11 Tf 72 740 Td 14 TL (Pasal 1) Tj T* (Pemegang IUP wajib melaksanakan kaidah pertambangan yang baik.) Tj ET";
        $obj = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 6 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << >> /Contents 7 0 R >>',
            "<< /Length ".strlen($isi1)." >>\nstream\n{$isi1}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            "<< /Length 0 >>\nstream\n\nendstream",
        ];

        $pdf = "%PDF-1.4\n";
        $letak = [];
        foreach ($obj as $i => $o) {
            $letak[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$o}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($obj) + 1)."\n0000000000 65535 f \n";
        foreach ($letak as $l) $pdf .= sprintf("%010d 00000 n \n", $l);
        $pdf .= "trailer\n<< /Size ".(count($obj) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    public function test_halaman_gambar_disebut_dan_dibaca_ai_lewat_lampiran_pdf(): void
    {
        $u = $this->masuk();
        Ai::simpanKunci(AiPenyedia::ANTHROPIC, 'kunci-uji-anthropic-1234');
        Ai::simpanPengaturan(AiPenyedia::ANTHROPIC, 'claude-sonnet-5', null);

        $f = UploadedFile::fake()->createWithContent('permen.pdf', $this->pdfSeparuhGambar());
        $r = $this->post(route('kepatuhan.rangkum'), ['berkas' => $f], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(2, $r->json('halaman'));
        $this->assertSame([2], $r->json('halamanGambar'));
        $this->assertNotNull($token = $r->json('token'));
        $this->assertStringContainsString('Pasal 1', $r->json('perHalaman.0'));
        $this->assertStringContainsString('Halaman 2 berupa gambar', implode(' ', $r->json('catatan')));

        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['halaman' => [
                ['no' => 2, 'teks' => "Pasal 2\n(1) Pemegang IUP wajib melapor.\n- 2 -"],
            ]])]],
            'stop_reason' => 'end_turn',
        ])]);

        $this->postJson(route('kepatuhan.rangkum.baca'), ['token' => $token, 'dari' => 2, 'sampai' => 2])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('halaman.2', "Pasal 2\n(1) Pemegang IUP wajib melapor.");

        Http::assertSent(function (PermintaanHttp $q) {
            $isi = $q->data()['messages'][0]['content'] ?? [];

            return is_array($isi)
                && ($isi[0]['type'] ?? '') === 'document'
                && ($isi[0]['source']['media_type'] ?? '') === 'application/pdf'
                && str_starts_with(base64_decode($isi[0]['source']['data'] ?? ''), '%PDF');
        });

        // Token milik pengunggahnya saja.
        $this->masuk();
        $this->postJson(route('kepatuhan.rangkum.baca'), ['token' => $token, 'dari' => 2, 'sampai' => 2])->assertNotFound();

        @unlink(storage_path('app/private/rangkum/'.$u->id.'/'.$token.'.pdf'));
    }

    public function test_tanpa_ai_halaman_gambar_tidak_disimpan_dan_diberi_petunjuk(): void
    {
        $this->masuk();

        $f = UploadedFile::fake()->createWithContent('permen.pdf', $this->pdfSeparuhGambar());
        $r = $this->post(route('kepatuhan.rangkum'), ['berkas' => $f], ['Accept' => 'application/json'])->assertOk();

        $this->assertNull($r->json('token'));
        $this->assertStringContainsString('Pusat Kendali', implode(' ', $r->json('catatan')));
        $this->assertSame('Pasal 1', $r->json('butir.0.penunjuk'));
    }

    /* ═══════════════ halaman gambar dari peramban ═══════════════ */

    /** JPEG 1×1 — cukup untuk memeriksa bentuk permintaannya. */
    private const JPEG = '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAAA//EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AN//Z';

    public function test_halaman_gambar_dari_peramban_dikirim_sebagai_gambar(): void
    {
        $this->masuk();
        Ai::simpanKunci(AiPenyedia::ANTHROPIC, 'kunci-uji-anthropic-1234');
        Ai::simpanPengaturan(AiPenyedia::ANTHROPIC, null, null);

        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['halaman' => [
                ['no' => 1, 'teks' => "Pasal 3\n(1) Pemegang IUP wajib melapor.\n- 3 -"],
            ]])]],
            'stop_reason' => 'end_turn',
        ])]);

        /* Model yang menomori gambarnya mulai 1: jawabannya tetap milik
           halaman 46 yang diminta. */
        $this->postJson(route('kepatuhan.rangkum.gambar'), ['halaman' => [['no' => 46, 'data' => self::JPEG]]])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('halaman.46', "Pasal 3\n(1) Pemegang IUP wajib melapor.");

        Http::assertSent(function (PermintaanHttp $q) {
            $isi = $q->data()['messages'][0]['content'] ?? [];

            return is_array($isi)
                && ($isi[0]['type'] ?? '') === 'image'
                && ($isi[0]['source']['media_type'] ?? '') === 'image/jpeg'
                && ($isi[0]['source']['data'] ?? '') === self::JPEG
                && str_contains($isi[1]['text'] ?? '', 'halaman 46');
        });
    }

    public function test_halaman_gambar_divalidasi(): void
    {
        $this->masuk();
        $this->aiGemini();

        $this->postJson(route('kepatuhan.rangkum.gambar'), ['halaman' => [['no' => 1, 'data' => '<script>']]])
            ->assertStatus(422)->assertJsonValidationErrors('halaman.0.data');

        $tiga = array_map(fn ($i) => ['no' => $i, 'data' => self::JPEG], [1, 2, 3]);
        $this->postJson(route('kepatuhan.rangkum.gambar'), ['halaman' => $tiga])
            ->assertStatus(422)->assertJsonValidationErrors('halaman');

        $this->postJson(route('kepatuhan.rangkum.gambar'), ['halaman' => [['no' => 1, 'data' => str_repeat('A', AnalisisPeraturan::MAKS_GAMBAR + 4)]]])
            ->assertStatus(422);
    }

    public function test_halaman_gambar_tanpa_analisis_otomatis_menjawab_409(): void
    {
        $this->masuk();

        $this->postJson(route('kepatuhan.rangkum.gambar'), ['halaman' => [['no' => 1, 'data' => self::JPEG]]])
            ->assertStatus(409)->assertJsonPath('ok', false);
    }

    /* ═══════════════ bentuk permintaan penyedia lain ═══════════════ */

    public function test_openai_menerima_mode_json_dan_lampiran_berkas(): void
    {
        $m = AiPenyedia::permintaan(AiPenyedia::OPENAI, 'k', 'gpt-4.1', 'peran',
            [['peran' => 'pengguna', 'isi' => 'Salin JSON.']], 4000,
            ['json' => true, 'lampiran' => [['mime' => 'application/pdf', 'data' => 'QUJD', 'nama' => 'a.pdf']]]);

        $this->assertSame(['type' => 'json_object'], $m['badan']['response_format']);
        $pesan = $m['badan']['messages'][1]['content'];
        $this->assertSame('file', $pesan[0]['type']);
        $this->assertSame('data:application/pdf;base64,QUJD', $pesan[0]['file']['file_data']);
        $this->assertSame(['type' => 'text', 'text' => 'Salin JSON.'], $pesan[1]);
    }

    public function test_lampiran_gambar_untuk_openai_dan_gemini(): void
    {
        $lampiran = ['json' => true, 'lampiran' => [['mime' => 'image/jpeg', 'data' => 'QUJD', 'nama' => 'h.jpg']]];

        $o = AiPenyedia::permintaan(AiPenyedia::OPENAI, 'k', 'm', 'peran', [['peran' => 'pengguna', 'isi' => 'Salin.']], 4000, $lampiran);
        $this->assertSame(['type' => 'image_url', 'image_url' => ['url' => 'data:image/jpeg;base64,QUJD']],
            $o['badan']['messages'][1]['content'][0]);

        $g = AiPenyedia::permintaan(AiPenyedia::GEMINI, 'k', 'm', 'peran', [['peran' => 'pengguna', 'isi' => 'Salin.']], 4000, $lampiran);
        $this->assertSame(['inlineData' => ['mimeType' => 'image/jpeg', 'data' => 'QUJD']], $g['badan']['contents'][0]['parts'][0]);
    }

    public function test_tanpa_opsi_permintaan_tetap_seperti_semula(): void
    {
        $m = AiPenyedia::permintaan(AiPenyedia::GEMINI, 'k', 'gemini-flash-latest', 'peran',
            [['peran' => 'pengguna', 'isi' => 'Halo']], 900);

        $this->assertSame(['maxOutputTokens' => 900], $m['badan']['generationConfig']);
        $this->assertSame([['text' => 'Halo']], $m['badan']['contents'][0]['parts']);
    }

    /* ═══════════════ simpan ═══════════════ */

    public function test_simpan_menerima_hingga_batas_butir(): void
    {
        $this->masuk();

        $butir = array_map(fn ($i) => ['penunjuk' => "Pasal {$i}", 'rangkuman' => 'Wajib.', 'penerapan' => ''],
            range(1, 300));

        $this->post(route('kepatuhan.rangkum.simpan'), [
            'sumber' => 'Peraturan', 'nomor' => 'Permen ESDM Nomor 26 Tahun 2018',
            'judul' => 'Kaidah Pertambangan', 'tahun' => 2026, 'dari_ai' => true, 'butir' => $butir,
        ])->assertRedirect();

        $s = ComplianceSubject::firstOrFail();
        $this->assertSame(300, $s->points()->count());
        $this->assertSame('Draf', $s->status);
        $this->assertTrue((bool) $s->dari_ai);
    }

    /** Hanya ya/tidak: penyedia dan model mesinnya tidak pernah dikirim ke halaman. */
    public function test_halaman_unggah_tidak_menyebut_penyedia_maupun_model(): void
    {
        $this->masuk();
        $this->aiGemini();

        $r = $this->get(route('kepatuhan.unggah'))->assertOk()->assertInertia(fn ($p) => $p
            ->component('Kepatuhan/Unggah')
            ->where('otomatis', true)
            ->missing('ai')->missing('aiLabel')
            ->where('batas.perGiliran', AnalisisPeraturan::PER_GILIRAN)
            ->has('tautan.analisis')->has('tautan.identitas')->has('tautan.gambar')->has('tautan.baca'));

        $this->assertDoesNotMatchRegularExpression('/gemini|google gemini|anthropic|claude|openai/i', $r->getContent());
    }

    /** Teks halamannya sendiri pun tidak menyebut AI maupun penyedianya. */
    public function test_tampilan_unggah_tidak_menyebut_ai(): void
    {
        $vue = file_get_contents(resource_path('js/Pages/Kepatuhan/Unggah.vue'));
        $templat = substr($vue, strpos($vue, '<template>'), strrpos($vue, '</template>') - strpos($vue, '<template>'));

        $this->assertDoesNotMatchRegularExpression('/\bAI\b|Claude|Anthropic|OpenAI|Gemini|GPT/', $templat);
    }
}
