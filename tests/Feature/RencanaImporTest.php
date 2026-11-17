<?php

namespace Tests\Feature;

use App\Models\SmkpAudit;
use App\Services\Asisten\{GagalAsisten, Jawaban, KlienAi, Permintaan};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/**
 * Pakai ulang Rencana Audit: akses & jangkauan, daftar & isi audit sumber,
 * templat CSV, impor CSV/XLSX (kepala, pemisah, kode dalam sel nama), dan
 * ekstraksi DOCX/PDF lewat Asisten AI (KlienAi tiruan — tanpa jaringan).
 */
class RencanaImporTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    private SmkpAudit $audit;

    /** Tiruan KlienAi: mencatat permintaan dan menjawab data tetap. */
    private object $palsu;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanGrup();

        // Asisten aktif: sambungan AI EQOHSEE (App\Support\Ai) berkunci.
        config(['asisten.aktif' => true, 'asisten.batas_per_jam' => 30, 'bantuan.ai.kunci' => 'kunci-uji']);

        $this->audit = $this->buatAudit($this->induk, 2026, [
            'metode_sampel' => ['I.1' => ['metode' => 'Tinjauan dokumen', 'sampel' => '=SUM(A1)']],
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

                return new Jawaban($this->data, 'claude-opus-5-5', 900, 300);
            }
        };
        $this->app->instance(KlienAi::class, $this->palsu);
    }

    private function buatAudit($company, int $tahun, ?array $rencana = null, ?array $risiko = null): SmkpAudit
    {
        return SmkpAudit::withoutGlobalScopes()->create([
            'company_id' => $company->id, 'tahun' => $tahun, 'status' => 'berjalan', 'tahap' => 1,
            'rencana' => $rencana, 'risiko' => $risiko,
        ]);
    }

    private function url(string $akhir = '', ?SmkpAudit $a = null): string
    {
        return '/smkp/'.($a ?? $this->audit)->id.'/rencana'.$akhir;
    }

    private function urlAi(?SmkpAudit $a = null): string
    {
        return '/asisten/smkp/'.($a ?? $this->audit)->id.'/rencana';
    }

    /** Berkas sungguhan di disk agar deteksi isi (finfo, struktur ZIP) ikut diuji. */
    private function berkas(string $nama, string $isi): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'rnc');
        file_put_contents($path, $isi);

        return new UploadedFile($path, $nama, null, null, true);
    }

    private function zip(array $entri): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $z = new \ZipArchive();
        $z->open($path, \ZipArchive::OVERWRITE);
        foreach ($entri as $n => $isi) $z->addFromString($n, $isi);
        $z->close();

        return (string) file_get_contents($path);
    }

    private function docx(string $teks): string
    {
        return $this->zip([
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
            'word/document.xml'   => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
                .'<w:p><w:r><w:t>'.htmlspecialchars($teks).'</w:t></w:r></w:p></w:body></w:document>',
        ]);
    }

    /** XLSX kecil: baris kepala & satu baris dari shared strings, satu baris inlineStr. */
    private function xlsx(): string
    {
        $ns = 'xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"';
        $nsR = 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';
        $ss = ['Kode', 'Kriteria', 'Metode Audit', 'Sampel', 'II.2', 'Manajemen Risiko', 'Tinjauan dokumen & wawancara', '5 register risiko 2026'];

        return $this->zip([
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>',
            'xl/workbook.xml' => "<?xml version=\"1.0\"?><workbook {$ns} {$nsR}><sheets><sheet name=\"Metode\" sheetId=\"1\" r:id=\"rId1\"/></sheets></workbook>",
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>',
            'xl/sharedStrings.xml' => "<?xml version=\"1.0\"?><sst {$ns}>".implode('', array_map(fn ($t) => '<si><t>'.htmlspecialchars($t).'</t></si>', $ss)).'</sst>',
            'xl/worksheets/sheet1.xml' => "<?xml version=\"1.0\"?><worksheet {$ns}><sheetData>"
                .'<row r="1"><c r="A1" t="s"><v>0</v></c><c r="B1" t="s"><v>1</v></c><c r="C1" t="s"><v>2</v></c><c r="D1" t="s"><v>3</v></c></row>'
                .'<row r="2"><c r="A2" t="s"><v>4</v></c><c r="B2" t="s"><v>5</v></c><c r="C2" t="s"><v>6</v></c><c r="D2" t="s"><v>7</v></c></row>'
                .'<row r="3"><c r="A3" t="inlineStr"><is><t>v.6</t></is></c><c r="C3" t="inlineStr"><is><t>Wawancara</t></is></c><c r="D3"><v>3</v></c></row>'
                .'</sheetData></worksheet>',
        ]);
    }

    private function impor(UploadedFile $f, $u = null): TestResponse
    {
        return $this->actingAs($u ?? $this->auditor())->post($this->url('/impor'), ['berkas' => $f], ['Accept' => 'application/json']);
    }

    private function ekstrak(UploadedFile $f, $u = null): TestResponse
    {
        return $this->actingAs($u ?? $this->auditor())->post($this->urlAi(), ['berkas' => $f], ['Accept' => 'application/json']);
    }

    /* ================= akses & jangkauan ================= */

    public function test_auditi_dan_tamu_ditolak_di_semua_rute(): void
    {
        $lain = $this->buatAudit($this->induk, 2025, ['tujuan' => 'Audit tahun lalu']);
        $auditi = $this->auditi($this->induk);

        $this->getJson($this->url('/sumber'))->assertUnauthorized();
        $this->actingAs($auditi)->getJson($this->url('/sumber'))->assertForbidden();
        $this->actingAs($auditi)->getJson($this->url('/sumber/'.$lain->id))->assertForbidden();
        $this->actingAs($auditi)->get($this->url('/templat'))->assertForbidden();
        $this->impor($this->berkas('m.csv', "Kode;Metode\nI.1;Wawancara"), $auditi)->assertForbidden();
        $this->ekstrak($this->berkas('r.docx', $this->docx('Metode')), $auditi)->assertForbidden();

        $this->assertSame([], $this->palsu->diminta);
    }

    public function test_audit_di_luar_jangkauan_404(): void
    {
        $sister = $this->buatAudit($this->sister, 2025, ['tujuan' => 'Rahasia sister']);
        $luar = $this->auditor($this->sister);

        // Audit tujuan di luar jangkauan.
        $this->actingAs($luar)->getJson($this->url('/sumber'))->assertNotFound();
        $this->actingAs($luar)->get($this->url('/templat'))->assertNotFound();
        $this->impor($this->berkas('m.csv', "I.1;Wawancara;1"), $luar)->assertNotFound();
        $this->ekstrak($this->berkas('r.docx', $this->docx('Metode')), $luar)->assertNotFound();

        // Audit sumber di luar jangkauan, atau audit itu sendiri.
        $u = $this->auditor($this->induk);
        $this->actingAs($u)->getJson($this->url('/sumber/'.$sister->id))->assertNotFound();
        $this->actingAs($u)->getJson($this->url('/sumber/'.$this->audit->id))->assertNotFound();

        $this->assertSame([], $this->palsu->diminta);
    }

    /* ================= daftar & isi sumber ================= */

    public function test_daftar_sumber_dalam_jangkauan_dan_urutannya(): void
    {
        $ms = ['I.1' => ['metode' => 'Tinjauan dokumen', 'sampel' => ''], 'II.2' => ['metode' => '', 'sampel' => '3 register'], 'XX.1' => ['metode' => 'Asing', 'sampel' => '']];
        $induk2024 = $this->buatAudit($this->induk, 2024, ['metode_sampel' => $ms]);
        $induk2025 = $this->buatAudit($this->induk, 2025, ['tujuan' => 'Menilai penerapan SMKP', 'tugas' => [['nama' => 'Rina', 'peran' => 'Ketua'], ['nama' => '']]]);
        $pjp2026   = $this->buatAudit($this->pjp, 2026, null, ['present' => [['kegiatan' => 'Peledakan', 'risiko' => 'Flyrock', 'nilai' => '16']], 'future' => []]);
        $this->buatAudit($this->induk, 2023);                                            // tanpa isi rencana
        $this->buatAudit($this->induk, 2022, ['tujuan' => '', 'metode_sampel' => []]);   // rencana kosong
        $this->buatAudit($this->sister, 2025, ['tujuan' => 'Di luar jangkauan']);

        $res = $this->actingAs($this->auditor($this->induk))->getJson($this->url('/sumber'))->assertOk()
            ->assertJsonPath('total', 51);

        $this->assertSame([$induk2025->id, $induk2024->id, $pjp2026->id], array_column($res->json('sumber'), 'id'));
        $this->assertSame([true, true, false], array_column($res->json('sumber'), 'samaPerusahaan'));
        $res->assertJsonPath('sumber.1.terisi', 2)
            ->assertJsonPath('sumber.0.tim', 1)
            ->assertJsonPath('sumber.2.risiko', 1)
            ->assertJsonPath('sumber.2.perusahaan', 'PT Jasa Uji')
            ->assertJsonPath('sumber.0.judul', 'Audit SMKP 2025');
    }

    public function test_salin_hanya_bagian_yang_layak_dipakai_ulang(): void
    {
        $sumber = $this->buatAudit($this->induk, 2025, [
            'nomor' => 'SP-OHSE-IV.059', 'tanggal_mulai' => '2025-03-01', 'tanggal_selesai' => '2025-03-05',
            'alamat' => 'Jl. Tambang 1', 'jenis_audit' => 'Audit eksternal',
            'tujuan' => 'Menilai penerapan SMKP', 'ruang_lingkup' => 'Seluruh area IUP', 'metode' => 'Wawancara dan observasi',
            'sampel' => 'Berbasis risiko', 'dokumentasi' => "Manual SMKP\nProsedur", 'pengecualian' => '',
            'metode_sampel' => ['I.1' => ['metode' => 'Tinjauan dokumen', 'sampel' => 'Kebijakan K3'], 'XX.9' => ['metode' => 'Asing'], 'II.1' => ['metode' => '', 'sampel' => '']],
            'susunan' => [['kegiatan' => 'Rapat pembukaan']],
            'tugas' => [['nama' => 'Rina', 'peran' => 'Ketua Tim Audit', 'registrasi' => '001/AUD', 'lingkup' => 'Elemen I–IV'], ['nama' => '', 'peran' => '']],
            'pengesahan' => ['ktt' => ['nama' => 'Budi', 'jabatan' => 'KTT', 'tanggal' => '2025-02-20']],
        ], [
            'present' => [['kegiatan' => 'Peledakan', 'risiko' => 'Flyrock', 'nilai' => '16'], ['kegiatan' => '', 'risiko' => '', 'nilai' => '']],
            'future'  => [['kegiatan' => 'Pembukaan pit baru', 'risiko' => 'Longsor', 'nilai' => '12']],
        ]);

        $res = $this->actingAs($this->auditor())->getJson($this->url('/sumber/'.$sumber->id))->assertOk()
            ->assertJsonPath('sumber.id', $sumber->id)
            ->assertJsonPath('isi.metode_sampel', ['I.1' => ['metode' => 'Tinjauan dokumen', 'sampel' => 'Kebijakan K3']])
            ->assertJsonPath('isi.tujuan', 'Menilai penerapan SMKP')
            ->assertJsonPath('isi.jenis_audit', 'Audit eksternal')
            ->assertJsonPath('isi.dokumentasi', "Manual SMKP\nProsedur")
            ->assertJsonPath('isi.tugas', [['nama' => 'Rina', 'peran' => 'Ketua Tim Audit', 'registrasi' => '001/AUD', 'lingkup' => 'Elemen I–IV']])
            ->assertJsonPath('isi.risiko.present', [['kegiatan' => 'Peledakan', 'risiko' => 'Flyrock', 'nilai' => '16']])
            ->assertJsonPath('isi.risiko.future.0.risiko', 'Longsor');

        foreach (['nomor', 'tanggal_mulai', 'tanggal_selesai', 'alamat', 'susunan', 'pengesahan'] as $k) {
            $res->assertJsonMissingPath("isi.{$k}");
        }

        // Rencana tanpa metode & sampel tetap berbentuk objek, bukan daftar.
        $kosong = $this->buatAudit($this->induk, 2024, ['tujuan' => 'x']);
        $this->assertSame('{}', json_encode($this->actingAs($this->auditor())->getJson($this->url('/sumber/'.$kosong->id))->getData()->isi->metode_sampel));
    }

    /* ================= templat & impor tabel ================= */

    public function test_templat_csv_berisi_nilai_tersimpan(): void
    {
        $res = $this->actingAs($this->auditor())->get($this->url('/templat'))->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('metode-sampel-audit-smkp-2026.csv', (string) $res->headers->get('Content-Disposition'));

        $isi = $res->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $isi);
        $baris = preg_split('/\r\n/', rtrim(substr($isi, 3), "\r\n"));
        $this->assertCount(52, $baris);
        $this->assertSame('Kode;Kriteria;Metode Audit;Sampel', $baris[0]);

        // Nilai yang diawali "=" diberi "'" agar tidak dijalankan sebagai rumus.
        $this->assertSame(['I.1', 'Penyusunan Kebijakan', 'Tinjauan dokumen', "'=SUM(A1)"], str_getcsv($baris[1], ';', '"', ''));
        $this->assertSame(['VII.6', 'Penggunaan Tinjauan Hasil dari Tindak Lanjut Rencana Perbaikan dalam Penentuan Kebijakan', '', ''], str_getcsv($baris[51], ';', '"', ''));

        // Templat yang diunggah kembali utuh: penanda rumus dibuang lagi.
        $this->impor($this->berkas('templat.csv', $isi))->assertOk()
            ->assertJsonPath('baris', [['kode' => 'I.1', 'metode' => 'Tinjauan dokumen', 'sampel' => '=SUM(A1)']])
            ->assertJsonPath('kosong', 50)
            ->assertJsonPath('dilewati', 0);
    }

    public function test_impor_csv_titik_koma_dengan_bom_dan_kepala(): void
    {
        $csv = "\xEF\xBB\xBFKode;Kriteria;Metode Audit;Sampel\r\n"
            ."ii.3;Identifikasi peraturan;Wawancara;3 orang\r\n"
            ."I.1;Penyusunan Kebijakan;\"Tinjauan dokumen; wawancara\";2 dokumen kebijakan\r\n"
            ."XX.9;Tidak dikenal;Wawancara;1\r\n"
            ."II.2.1;Butir, bukan sub-elemen;Observasi;1\r\n"
            ."I;Kebijakan;;\r\n"
            ."I.2;Isi Kebijakan;;\r\n"
            ."I.1;Ganda;Observasi;9\r\n";

        $this->impor($this->berkas('metode.csv', $csv))->assertOk()
            ->assertJsonPath('sumber', 'tabel')
            ->assertJsonPath('berkas', 'metode.csv')
            ->assertJsonPath('baris', [
                ['kode' => 'I.1', 'metode' => 'Tinjauan dokumen; wawancara', 'sampel' => '2 dokumen kebijakan'],
                ['kode' => 'II.3', 'metode' => 'Wawancara', 'sampel' => '3 orang'],
            ])
            ->assertJsonPath('dilewati', 3)
            ->assertJsonPath('kosong', 1);
    }

    public function test_impor_csv_koma_kepala_bebas_urutan_dan_tanpa_kepala(): void
    {
        // Koma, kolom kepala diacak, huruf besar-kecil bebas.
        $this->impor($this->berkas('a.csv', "\xEF\xBB\xBFSAMPEL,metode audit,kode\n3 izin kerja,Observasi lapangan,IV.1\n"))->assertOk()
            ->assertJsonPath('baris', [['kode' => 'IV.1', 'metode' => 'Observasi lapangan', 'sampel' => '3 izin kerja']]);

        // Tanpa kepala, tiga kolom, kode dan nama dalam satu sel.
        $this->impor($this->berkas('b.csv', "I.1 Penyusunan Kebijakan;Tinjauan dokumen;Kebijakan 2025\nVII.6. Penggunaan Tinjauan;Wawancara;Direksi\n"))->assertOk()
            ->assertJsonPath('baris.0', ['kode' => 'I.1', 'metode' => 'Tinjauan dokumen', 'sampel' => 'Kebijakan 2025'])
            ->assertJsonPath('baris.1.kode', 'VII.6');

        // Tanpa kepala, empat kolom: kode, kriteria, metode, sampel.
        $this->impor($this->berkas('c.txt', "I.3\tPenetapan Kebijakan\tTinjauan dokumen\tSK penetapan\n"))->assertOk()
            ->assertJsonPath('baris', [['kode' => 'I.3', 'metode' => 'Tinjauan dokumen', 'sampel' => 'SK penetapan']]);

        // Kepala tanpa kolom kode: kode dibaca dari kolom kriteria.
        $this->impor($this->berkas('d.csv', "No;Sub-elemen;Metode;Sampel\n1;III.10 Pendidikan dan Pelatihan;Wawancara;5 sertifikat\n"))->assertOk()
            ->assertJsonPath('baris', [['kode' => 'III.10', 'metode' => 'Wawancara', 'sampel' => '5 sertifikat']]);

        // Teks panjang dijepit ke 300 karakter seperti validasi simpan.
        $res = $this->impor($this->berkas('e.csv', 'I.4;'.str_repeat('a', 400).";x\n"))->assertOk();
        $this->assertSame(300, mb_strlen($res->json('baris.0.metode')));

        // Tidak satu pun kode dikenali.
        $this->impor($this->berkas('f.csv', "Nama;Nilai\nBudi;3\n"))->assertUnprocessable()
            ->assertJsonPath('jenis', 'kosong');
    }

    public function test_impor_xlsx(): void
    {
        $this->impor($this->berkas('metode lama.xlsx', $this->xlsx()))->assertOk()
            ->assertJsonPath('baris', [
                ['kode' => 'II.2', 'metode' => 'Tinjauan dokumen & wawancara', 'sampel' => '5 register risiko 2026'],
                ['kode' => 'V.6', 'metode' => 'Wawancara', 'sampel' => '3'],
            ]);

        // Berekstensi XLSX tetapi bukan paket Office.
        $this->impor($this->berkas('palsu.xlsx', 'bukan zip'))->assertUnprocessable()->assertJsonValidationErrors('berkas');
    }

    public function test_dokumen_docx_pdf_diarahkan_ke_asisten_dan_jenis_lain_ditolak(): void
    {
        $this->impor($this->berkas('rencana.docx', $this->docx('I.1 Tinjauan dokumen')))->assertUnprocessable()
            ->assertJsonPath('jenis', 'ai')
            ->assertJsonPath('pesan', fn ($p) => str_starts_with($p, 'Dokumen DOCX/PDF dibaca dengan Asisten AI'));
        $this->impor($this->berkas('rencana.pdf', "%PDF-1.4\n%%EOF"))->assertUnprocessable()->assertJsonPath('jenis', 'ai');
        $this->impor($this->berkas('gambar.png', 'xx'))->assertUnprocessable()->assertJsonValidationErrors('berkas');
        $this->actingAs($this->auditor())->post($this->url('/impor'), [], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('berkas');

        $this->assertSame([], $this->palsu->diminta);
    }

    /* ================= ekstraksi Asisten AI ================= */

    public function test_ekstrak_docx_dengan_asisten(): void
    {
        $this->palsu->data = [
            'baris' => [
                ['kode' => 'II.3', 'metode' => str_repeat('w', 400), 'sampel' => "Register peraturan\n2 izin"],
                ['kode' => 'I.1', 'metode' => 'Tinjauan dokumen', 'sampel' => 'Kebijakan K3 2025'],
                ['kode' => 'XX.1', 'metode' => 'Karangan', 'sampel' => '1'],
                ['kode' => 'I.1', 'metode' => 'Ganda', 'sampel' => ''],
                ['kode' => 'III.1', 'metode' => '', 'sampel' => ''],
                'bukan objek',
            ],
            'catatan' => 'Dokumen Rencana Audit 2025; elemen VII tidak tercantum.',
        ];

        $res = $this->ekstrak($this->berkas('Rencana Audit 2025.docx', $this->docx('Elemen I memakai tinjauan dokumen kebijakan')))->assertOk()
            ->assertJsonPath('sumber', 'ai')
            ->assertJsonPath('model', 'claude-opus-5-5')
            ->assertJsonPath('berkas', 'Rencana Audit 2025.docx')
            ->assertJsonPath('catatan', 'Dokumen Rencana Audit 2025; elemen VII tidak tercantum.')
            ->assertJsonPath('baris.0', ['kode' => 'I.1', 'metode' => 'Tinjauan dokumen', 'sampel' => 'Kebijakan K3 2025'])
            ->assertJsonPath('baris.1.kode', 'II.3')
            ->assertJsonPath('baris.1.sampel', 'Register peraturan; 2 izin')
            ->assertJsonCount(2, 'baris');
        $this->assertSame(300, mb_strlen($res->json('baris.1.metode')));

        $this->assertCount(1, $this->palsu->diminta);
        $p = $this->palsu->diminta[0];
        $this->assertSame('rencana', $p->tugas);
        $this->assertCount(1, $p->lampiran);
        $this->assertFalse($p->lampiran[0]->biner());
        $this->assertStringContainsString('Elemen I memakai tinjauan dokumen kebijakan', (string) $p->lampiran[0]->teks);
        $this->assertStringContainsString('- I.1 Penyusunan Kebijakan', $p->pesan);
        $this->assertStringNotContainsString('PT Induk Uji', $p->pesan);
        $this->assertCount(51, $p->skema['properties']['baris']['items']['properties']['kode']['enum']);
        $this->assertSame(['kode', 'metode', 'sampel'], $p->skema['properties']['baris']['items']['required']);
        $this->assertFalse($p->skema['properties']['baris']['items']['additionalProperties']);
    }

    public function test_ekstrak_pdf_dikirim_biner_dan_isi_diperiksa(): void
    {
        $this->palsu->data = ['baris' => [], 'catatan' => 'Tidak ada tabel metode & sampel.'];
        $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";

        $this->ekstrak($this->berkas('rencana.pdf', $pdf))->assertOk()
            ->assertJsonPath('baris', [])
            ->assertJsonPath('catatan', 'Tidak ada tabel metode & sampel.');
        $this->assertTrue($this->palsu->diminta[0]->lampiran[0]->biner());
        $this->assertSame('application/pdf', $this->palsu->diminta[0]->lampiran[0]->mime);

        // Isi tidak cocok dengan ekstensinya, atau jenis yang tidak dibaca asisten.
        $this->ekstrak($this->berkas('palsu.pdf', 'bukan pdf'))->assertUnprocessable()->assertJsonValidationErrors('berkas');
        $this->ekstrak($this->berkas('foto.jpg', 'xx'))->assertUnprocessable()->assertJsonValidationErrors('berkas');
        $this->assertCount(1, $this->palsu->diminta);
    }

    public function test_ekstrak_berkas_tanpa_teks_tidak_dikirim_ke_model(): void
    {
        $this->ekstrak($this->berkas('kosong.docx', $this->zip(['[Content_Types].xml' => '<Types/>', 'word/document.xml' => '<w:document/>'])))
            ->assertUnprocessable()->assertJsonPath('jenis', 'kosong');
        $this->assertSame([], $this->palsu->diminta);
    }

    public function test_ekstrak_saat_asisten_nonaktif_503(): void
    {
        config(['asisten.aktif' => false, 'asisten.kunci' => null]);

        $this->ekstrak($this->berkas('r.docx', $this->docx('Metode')))
            ->assertStatus(503)->assertJsonPath('jenis', 'nonaktif');
        $this->assertSame([], $this->palsu->diminta);

        // Impor tabel tidak bergantung pada asisten.
        $this->impor($this->berkas('m.csv', "I.1;Wawancara;1\n"))->assertOk();
    }
}
