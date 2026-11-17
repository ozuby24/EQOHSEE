<?php

namespace Tests\Feature;

use App\Http\Controllers\BuktiAuditController;
use App\Models\{SmkpAudit, SmkpBukti, User};
use App\Rules\LampiranBukti;
use App\Support\Smkp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/**
 * Bukti kelengkapan per butir: auditi mengunggah sesuai dokumen yang diminta
 * Rencana Audit, auditor/administrator memverifikasi lalu menilai butirnya.
 */
class BuktiAuditTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    private const PDF = "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n";

    private SmkpAudit $audit;

    /** Satu audit per perusahaan per tahun: audit tambahan memakai tahun berikutnya. */
    private int $tahun = 2026;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanGrup();
        $this->audit = $this->buatAudit($this->induk->id);
    }

    private function buatAudit(int $companyId, string $status = 'berjalan'): SmkpAudit
    {
        return SmkpAudit::withoutGlobalScopes()->create([
            'company_id' => $companyId, 'tahun' => $this->tahun++, 'status' => $status, 'tahap' => 2, 'hasil' => [],
            'rencana' => ['metode_sampel' => [
                'II.2' => ['metode' => 'Telaah dokumen & wawancara', 'sampel' => 'SOP identifikasi bahaya, 3 laporan inspeksi'],
                'I.1'  => ['metode' => ' ', 'sampel' => ''],   // kosong: tidak dianggap diminta
            ]],
        ]);
    }

    private function pdf(string $nama = 'sop.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, self::PDF);
    }

    private function png(string $nama = 'foto.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, $this->isiPng());
    }

    /** PNG asli 4×4 piksel dari GD. */
    private function isiPng(): string
    {
        $g = imagecreatetruecolor(4, 4);
        ob_start();
        imagepng($g);

        return (string) ob_get_clean();
    }

    /** Berkas sungguhan di disk: jenis isinya dideteksi finfo, bukan ditebak dari nama seperti berkas tiruan. */
    private function asli(string $nama, string $isi): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bukti');
        file_put_contents($path, $isi);

        return new UploadedFile($path, $nama, null, null, true);
    }

    private function url(?SmkpAudit $a = null, string $akhir = ''): string
    {
        return '/smkp/'.($a ?? $this->audit)->id.'/bukti'.$akhir;
    }

    private function unggah(User $u, array $berkas, string $kode = 'II.2.1', ?SmkpAudit $a = null, array $isi = [])
    {
        $a ??= $this->audit;

        return $this->actingAs($u)->from("/smkp/{$a->id}/elemen/II")
            ->post($this->url($a), $isi + ['kode' => $kode, 'berkas' => $berkas]);
    }

    /** Baris bukti langsung di basis data (tanpa melewati unggahan) beserta berkasnya di disk. */
    private function bukti(?User $u, string $status = SmkpBukti::MENUNGGU, array $isi = [], ?SmkpAudit $a = null): SmkpBukti
    {
        $a ??= $this->audit;
        $path = SmkpBukti::folder($a->id).'/'.uniqid('uji', true).'.pdf';
        Storage::disk('local')->put($path, self::PDF);

        return SmkpBukti::create($isi + [
            'audit_id' => $a->id, 'kode' => 'II.2.1', 'user_id' => $u?->id, 'path' => $path,
            'nama' => 'sop.pdf', 'mime' => 'application/pdf', 'ukuran' => strlen(self::PDF), 'status' => $status,
        ]);
    }

    /* ---------------- unggah ---------------- */

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get($this->url())->assertRedirect('/login');
        $this->post($this->url(), ['kode' => 'II.2.1', 'berkas' => [$this->pdf()]])->assertRedirect('/login');
        $this->assertSame(0, SmkpBukti::count());
    }

    public function test_auditi_mengunggah_bukti_yang_menunggu_verifikasi(): void
    {
        $u = $this->auditi($this->induk);
        $this->unggah($u, [$this->pdf('SOP Identifikasi Bahaya.pdf'), $this->png('foto rambu.png')], 'II.2.1', null, ['keterangan' => '  Revisi 2026  '])
            ->assertRedirect("/smkp/{$this->audit->id}/elemen/II")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('ok', '2 berkas bukti II.2.1 terunggah — menunggu verifikasi auditor.');

        $b = SmkpBukti::orderBy('id')->get();
        $this->assertCount(2, $b);
        $this->assertSame(['SOP Identifikasi Bahaya.pdf', 'foto rambu.png'], $b->pluck('nama')->all());
        $this->assertSame(['application/pdf', 'image/png'], $b->pluck('mime')->all());
        foreach ($b as $x) {
            $this->assertSame([$this->audit->id, 'II.2.1', $u->id, SmkpBukti::MENUNGGU, 'Revisi 2026', null, null],
                [$x->audit_id, $x->kode, $x->user_id, $x->status, $x->keterangan, $x->diverifikasi_oleh, $x->diverifikasi_pada]);
            // Nama acak di folder audit: nama asli tidak pernah menjadi jalur berkas.
            $this->assertMatchesRegularExpression('#^bukti-audit/'.$this->audit->id.'/[A-Za-z0-9]{40}\.(pdf|png)$#', $x->path);
            Storage::disk('local')->assertExists($x->path);
            $this->assertSame(Storage::disk('local')->size($x->path), $x->ukuran);
        }
        $this->assertSame(self::PDF, Storage::disk('local')->get($b[0]->path));
        $this->assertDatabaseHas('activity_log', ['action' => 'Unggah bukti audit', 'user_id' => $u->id, 'module' => 'smkp']);
    }

    public function test_jangkauan_perusahaan_untuk_unggah(): void
    {
        // Auditi induk menjangkau audit PJP anaknya.
        $auditPjp = $this->buatAudit($this->pjp->id);
        $this->unggah($this->auditi($this->induk), [$this->pdf()], 'II.2.1', $auditPjp)->assertSessionHasNoErrors();
        $this->unggah($this->auditi($this->pjp), [$this->pdf()], 'II.2.2', $auditPjp)->assertSessionHasNoErrors();
        $this->assertSame(2, SmkpBukti::where('audit_id', $auditPjp->id)->count());

        // Di luar jangkauan: audit tidak terlihat sama sekali.
        foreach ([$this->auditi($this->pjp), $this->auditi($this->sister), $this->auditi(null), $this->auditor($this->sister)] as $u) {
            $this->unggah($u, [$this->pdf()])->assertNotFound();
            $this->actingAs($u)->get($this->url())->assertNotFound();
        }
        $this->assertSame(0, SmkpBukti::where('audit_id', $this->audit->id)->count());
        $this->assertSame([], Storage::disk('local')->allFiles(SmkpBukti::folder($this->audit->id)));
    }

    public function test_unggahan_auditor_dan_administrator_langsung_diterima(): void
    {
        foreach ([$this->auditor($this->induk), $this->admin()] as $u) {
            $this->unggah($u, [$this->pdf()], 'II.2.3')->assertSessionHasNoErrors()
                ->assertSessionHas('ok', '1 berkas bukti II.2.3 terunggah.');
            $b = SmkpBukti::latest('id')->first();
            $this->assertSame([SmkpBukti::DITERIMA, $u->id, $u->id], [$b->status, $b->user_id, $b->diverifikasi_oleh]);
            $this->assertNotNull($b->diverifikasi_pada);
        }
    }

    public function test_unggahan_tidak_sah_ditolak_tanpa_meninggalkan_berkas(): void
    {
        $u = $this->auditi($this->induk);

        $this->unggah($u, [$this->pdf()], 'IX.99.9')->assertSessionHasErrors(['kode' => 'Butir kriteria tidak dikenal.']);
        $this->unggah($u, [$this->pdf()], 'II.2')->assertSessionHasErrors('kode');   // kode sub-elemen, bukan butir
        $this->unggah($u, [])->assertSessionHasErrors(['berkas' => 'Pilih berkas bukti yang akan diunggah.']);
        $this->unggah($u, array_map(fn ($i) => $this->pdf("b{$i}.pdf"), range(1, 6)))
            ->assertSessionHasErrors(['berkas' => 'Paling banyak 5 berkas sekali unggah.']);
        $this->unggah($u, [$this->pdf()], 'II.2.1', null, ['keterangan' => str_repeat('x', 501)])->assertSessionHasErrors('keterangan');
        $this->unggah($u, [UploadedFile::fake()->create('besar.pdf', BuktiAuditController::MAKS_KB + 1, 'application/pdf')])
            ->assertSessionHasErrors(['berkas.0' => '"besar.pdf" lebih dari 10 MB.']);

        // Ekstensi berbahaya, dan isi yang tidak cocok dengan ekstensinya.
        $this->unggah($u, [$this->asli('skrip.php', '<?php echo 1;')])->assertSessionHasErrors('berkas.0');
        $this->unggah($u, [$this->asli('gambar.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')])->assertSessionHasErrors('berkas.0');
        $this->unggah($u, [$this->pdf('baik.pdf'), $this->asli('menyamar.pdf', '<html><script>alert(1)</script></html>')])
            ->assertSessionHasErrors(['berkas.1' => '"menyamar.pdf": isinya tidak sesuai jenis berkasnya — unggah PDF, gambar, atau dokumen Office.'])
            ->assertSessionDoesntHaveErrors('berkas.0');
        $this->unggah($u, [$this->asli('foto.jpg', self::PDF)])->assertSessionHasErrors('berkas.0');

        $this->assertSame(0, SmkpBukti::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_isi_asli_terdeteksi_dari_isinya(): void
    {
        $u = $this->auditi($this->induk);
        $this->unggah($u, [$this->asli('laporan.pdf', self::PDF), $this->asli('foto.jpg', $this->isiPng())])
            ->assertSessionHasNoErrors();

        // Mime tersimpan hasil deteksi isi: PNG yang dinamai .jpg tetap image/png.
        $this->assertSame(['application/pdf', 'image/png'], SmkpBukti::orderBy('id')->pluck('mime')->all());
        $this->assertSame(['laporan.pdf', 'foto.jpg'], SmkpBukti::orderBy('id')->pluck('nama')->all());
    }

    public function test_kuota_butir_audit_dan_harian(): void
    {
        $u = $this->auditi($this->induk);

        // Butir II.2.1 sudah hampir penuh: berkas PDF kecil pun melampauinya, butir lain masih menerima.
        $this->bukti(null, SmkpBukti::DITERIMA, ['ukuran' => BuktiAuditController::KUOTA_BUTIR - 10]);
        $this->unggah($u, [$this->pdf()])->assertSessionHasErrors('berkas');
        $this->assertStringContainsString('batas 100 MB', session('errors')->first('berkas'));
        $this->unggah($u, [$this->pdf()], 'II.2.2')->assertSessionHasNoErrors();

        // Kuota seluruh audit.
        SmkpBukti::query()->delete();
        $this->bukti(null, SmkpBukti::DITERIMA, ['kode' => 'I.1', 'ukuran' => BuktiAuditController::KUOTA_AUDIT]);
        $this->unggah($u, [$this->pdf()])->assertSessionHasErrors('berkas');
        $this->assertStringContainsString('Bukti audit ini sudah mencapai batas', session('errors')->first('berkas'));

        // Kuota harian per pengguna, dihitung lintas audit.
        SmkpBukti::query()->delete();
        $lain = $this->buatAudit($this->pjp->id);
        $this->bukti($u, SmkpBukti::MENUNGGU, ['ukuran' => BuktiAuditController::KUOTA_HARIAN], $lain);
        $this->unggah($u, [$this->pdf()])->assertSessionHasErrors('berkas');
        $this->assertStringContainsString('Batas unggah harian', session('errors')->first('berkas'));
        // Pengguna lain tidak terkena kuota harian orang tersebut.
        $this->unggah($this->auditi($this->induk), [$this->pdf()])->assertSessionHasNoErrors();
        // Unggahan kemarin tidak lagi dihitung.
        SmkpBukti::where('user_id', $u->id)->update(['created_at' => now()->subDays(2)]);
        $this->unggah($u, [$this->pdf()])->assertSessionHasNoErrors();
    }

    public function test_audit_selesai_menolak_auditi_tetapi_tidak_auditor(): void
    {
        $this->audit->update(['status' => 'selesai']);
        $auditi = $this->auditi($this->induk);

        $this->unggah($auditi, [$this->pdf()])->assertSessionHasErrors(['berkas' => 'Audit sudah selesai — bukti tidak dapat ditambahkan lagi.']);
        $this->assertSame(0, SmkpBukti::count());
        $this->actingAs($auditi)->get("/smkp/{$this->audit->id}/elemen/II")
            ->assertInertia(fn (Inertia $p) => $p->where('bukti.bisaUnggah', false));
        $this->actingAs($auditi)->get($this->url())->assertInertia(fn (Inertia $p) => $p->where('bisaUnggah', false));

        $auditor = $this->auditor($this->induk);
        $this->unggah($auditor, [$this->pdf()])->assertSessionHasNoErrors();
        $this->assertSame(1, SmkpBukti::count());
        $this->actingAs($auditor)->get("/smkp/{$this->audit->id}/elemen/II")
            ->assertInertia(fn (Inertia $p) => $p->where('bukti.bisaUnggah', true));
    }

    public function test_nama_berkas_dibersihkan_dan_ekstensi_dipertahankan(): void
    {
        $u = $this->auditi($this->induk);
        $panjang = str_repeat('Laporan Inspeksi Tambang ', 20);
        $this->unggah($u, [
            $this->pdf("C:\\Users\\budi\\Dokumen\\laporan\x07 bulanan.PDF"),
            $this->pdf('../../etc/passwd.pdf'),
            $this->pdf('.pdf'),
            $this->pdf("tagihan\u{202E}fdp.png.pdf"),
            $this->pdf($panjang.'.pdf'),
        ])->assertSessionHasNoErrors();

        $b = SmkpBukti::orderBy('id')->get();
        $this->assertSame(['laporan bulanan.pdf', 'passwd.pdf', 'bukti.pdf', 'tagihanfdp.png.pdf'], $b->take(4)->pluck('nama')->all());
        $nama = $b[4]->nama;
        $this->assertStringStartsWith('Laporan Inspeksi Tambang', $nama);
        $this->assertStringEndsWith('.pdf', $nama);
        $this->assertLessThanOrEqual(145, mb_strlen($nama));   // kolom nama 200 karakter

        // Nama berkas di disk selalu acak dengan ekstensi kecil.
        foreach ($b as $x) $this->assertMatchesRegularExpression('#^bukti-audit/\d+/[A-Za-z0-9]{40}\.pdf$#', $x->path);
    }

    public function test_gagal_menyimpan_baris_tidak_meninggalkan_berkas(): void
    {
        // Baris kedua gagal disimpan: transaksi batal dan berkas yang sudah tertulis ikut dihapus.
        $n = 0;
        SmkpBukti::creating(function () use (&$n) {
            if (++$n === 2) throw new \RuntimeException('Basis data menolak.');
        });

        $this->unggah($this->auditi($this->induk), [$this->pdf('a.pdf'), $this->pdf('b.pdf')])->assertServerError();
        $this->assertSame(0, SmkpBukti::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_nama_multibita_panjang_muat_di_kolom(): void
    {
        $this->unggah($this->auditi($this->induk), [$this->pdf(str_repeat('laporan K3 tambang ', 6).str_repeat('日本語', 100).'.pdf')])
            ->assertSessionHasNoErrors();
        $nama = SmkpBukti::sole()->nama;
        $this->assertStringEndsWith('.pdf', $nama);
        $this->assertLessThanOrEqual(200, mb_strlen($nama));
        $this->assertTrue(mb_check_encoding($nama, 'UTF-8'));
    }

    /* ---------------- verifikasi ---------------- */

    public function test_auditor_dan_administrator_memverifikasi_bukti(): void
    {
        $b = $this->bukti($this->auditi($this->induk));
        $auditor = $this->auditor($this->induk);
        $verif = fn (User $u, array $isi) => $this->actingAs($u)->from($this->url())->post($this->url(null, "/{$b->id}/verifikasi"), $isi);

        $verif($auditor, ['status' => SmkpBukti::DITERIMA])->assertRedirect($this->url())->assertSessionHasNoErrors();
        $b->refresh();
        $this->assertSame([SmkpBukti::DITERIMA, $auditor->id, null], [$b->status, $b->diverifikasi_oleh, $b->catatan]);
        $this->assertNotNull($b->diverifikasi_pada);
        $this->assertDatabaseHas('activity_log', ['action' => 'Verifikasi bukti audit', 'user_id' => $auditor->id]);

        // Ditolak wajib disertai catatan perbaikan.
        $verif($auditor, ['status' => SmkpBukti::DITOLAK])->assertSessionHasErrors(['catatan' => 'Tuliskan apa yang perlu diperbaiki auditi.']);
        $verif($auditor, ['status' => SmkpBukti::DITOLAK, 'catatan' => '   '])->assertSessionHasErrors('catatan');
        $this->assertSame(SmkpBukti::DITERIMA, $b->fresh()->status);

        $admin = $this->admin();
        $verif($admin, ['status' => SmkpBukti::DITOLAK, 'catatan' => ' Halaman tanda tangan belum ada. '])->assertSessionHasNoErrors();
        $b->refresh();
        $this->assertSame([SmkpBukti::DITOLAK, 'Halaman tanda tangan belum ada.', $admin->id], [$b->status, $b->catatan, $b->diverifikasi_oleh]);

        // Kembali menunggu: jejak verifikator dihapus.
        $verif($auditor, ['status' => SmkpBukti::MENUNGGU])->assertSessionHasNoErrors();
        $b->refresh();
        $this->assertSame([SmkpBukti::MENUNGGU, null, null, null], [$b->status, $b->diverifikasi_oleh, $b->diverifikasi_pada, $b->catatan]);

        $verif($auditor, ['status' => 'hapus'])->assertSessionHasErrors('status');
        $verif($auditor, ['status' => SmkpBukti::DITOLAK, 'catatan' => str_repeat('x', 1001)])->assertSessionHasErrors('catatan');
    }

    public function test_auditi_tidak_dapat_memverifikasi_dan_bukti_audit_lain_404(): void
    {
        $b = $this->bukti($this->auditi($this->induk));
        $this->actingAs($this->auditi($this->induk))->post($this->url(null, "/{$b->id}/verifikasi"), ['status' => SmkpBukti::DITERIMA])
            ->assertForbidden();
        // Auditor di luar jangkauan perusahaan: audit tidak terlihat.
        $this->actingAs($this->auditor($this->sister))->post($this->url(null, "/{$b->id}/verifikasi"), ['status' => SmkpBukti::DITERIMA])
            ->assertNotFound();

        // Berkas milik audit lain tidak dapat dijangkau lewat audit ini.
        $lain = $this->buatAudit($this->induk->id);
        $this->actingAs($this->auditor($this->induk))->post($this->url($lain, "/{$b->id}/verifikasi"), ['status' => SmkpBukti::DITERIMA])
            ->assertNotFound();
        $this->assertSame(SmkpBukti::MENUNGGU, $b->fresh()->status);
    }

    /* ---------------- hapus ---------------- */

    public function test_pengunggah_menarik_berkasnya_selama_belum_diterima(): void
    {
        $pemilik = $this->auditi($this->induk);
        $rekan = $this->auditi($this->induk);
        $menunggu = $this->bukti($pemilik);
        $ditolak = $this->bukti($pemilik, SmkpBukti::DITOLAK, ['catatan' => 'Buram']);
        $diterima = $this->bukti($pemilik, SmkpBukti::DITERIMA);
        $hapus = fn (User $u, SmkpBukti $b) => $this->actingAs($u)->from($this->url())->delete($this->url(null, "/{$b->id}"));

        // Rekan sekantor tidak boleh menghapus berkas orang lain; pemilik tidak boleh menghapus yang sudah diterima.
        $hapus($rekan, $menunggu)->assertForbidden();
        $hapus($pemilik, $diterima)->assertForbidden();
        Storage::disk('local')->assertExists([$menunggu->path, $diterima->path]);

        $hapus($pemilik, $menunggu)->assertRedirect($this->url())->assertSessionHas('ok', 'Berkas bukti dihapus.');
        $hapus($pemilik, $ditolak)->assertRedirect($this->url());
        $this->assertModelMissing($menunggu);
        $this->assertModelMissing($ditolak);
        Storage::disk('local')->assertMissing([$menunggu->path, $ditolak->path]);
        $this->assertDatabaseHas('activity_log', ['action' => 'Hapus bukti audit', 'user_id' => $pemilik->id]);

        // Auditor boleh menghapus apa saja, termasuk yang sudah diterima.
        $hapus($this->auditor($this->induk), $diterima)->assertRedirect($this->url());
        $this->assertModelMissing($diterima);
        Storage::disk('local')->assertMissing($diterima->path);
    }

    public function test_audit_selesai_mengunci_berkas_auditi(): void
    {
        $pemilik = $this->auditi($this->induk);
        $b = $this->bukti($pemilik);
        $this->audit->update(['status' => 'selesai']);

        $this->actingAs($pemilik)->delete($this->url(null, "/{$b->id}"))->assertForbidden();
        $this->actingAs($pemilik)->get("/smkp/{$this->audit->id}/elemen/II?sub=II.2")
            ->assertInertia(fn (Inertia $p) => $p->where('sub.1.butir.0.berkas.0.bisaHapus', false));

        $this->actingAs($this->admin())->delete($this->url(null, "/{$b->id}"))->assertRedirect();
        $this->assertModelMissing($b);
    }

    public function test_hapus_bukti_audit_lain_atau_id_tak_wajar_404(): void
    {
        $pemilik = $this->auditi($this->induk);
        $lain = $this->buatAudit($this->induk->id);
        $b = $this->bukti($pemilik, SmkpBukti::MENUNGGU, [], $lain);

        $this->actingAs($pemilik)->delete($this->url(null, "/{$b->id}"))->assertNotFound();
        $this->actingAs($this->admin())->delete($this->url(null, "/{$b->id}"))->assertNotFound();
        $this->actingAs($pemilik)->delete($this->url(null, '/99999999999999999999999'))->assertNotFound();
        $this->actingAs($pemilik)->get($this->url(null, '/99999999999999999999999/berkas'))->assertNotFound();
        $this->actingAs($pemilik)->delete($this->url(null, '/abc'))->assertNotFound();
        $this->assertModelExists($b);
    }

    /* ---------------- unduh ---------------- */

    public function test_unduhan_berkas_dengan_header_aman(): void
    {
        $u = $this->auditi($this->induk);
        $this->unggah($u, [$this->pdf('Laporan Inspeksi.pdf'), $this->png('foto rambu.png')])->assertSessionHasNoErrors();
        [$pdf, $png] = SmkpBukti::orderBy('id')->get()->all();

        $aman = function ($r) {
            $r->assertOk()
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Content-Security-Policy', 'sandbox');
            $cc = (string) $r->headers->get('Cache-Control');
            $this->assertStringContainsString('no-store', $cc);
            $this->assertStringContainsString('private', $cc);

            return $r;
        };

        // PDF selalu diunduh sebagai lampiran, juga bila diminta ?lihat=1.
        foreach (['', '?lihat=1'] as $q) {
            $r = $aman($this->actingAs($u)->get($this->url(null, "/{$pdf->id}/berkas{$q}")));
            $this->assertStringStartsWith('attachment;', (string) $r->headers->get('Content-Disposition'));
            $this->assertStringContainsString('Laporan Inspeksi.pdf', (string) $r->headers->get('Content-Disposition'));
            $this->assertSame(self::PDF, $r->streamedContent());
        }

        // Gambar tampil langsung hanya bila diminta; tanpa ?lihat tetap diunduh.
        $r = $aman($this->actingAs($u)->get($this->url(null, "/{$png->id}/berkas?lihat=1")));
        $r->assertHeader('Content-Type', 'image/png');
        $this->assertStringNotContainsString('attachment', (string) $r->headers->get('Content-Disposition'));
        $r = $aman($this->actingAs($u)->get($this->url(null, "/{$png->id}/berkas")));
        $this->assertStringStartsWith('attachment;', (string) $r->headers->get('Content-Disposition'));

        // Auditor induk pun dapat mengunduh; perusahaan lain tidak.
        $this->actingAs($this->auditor($this->induk))->get($this->url(null, "/{$pdf->id}/berkas"))->assertOk();
        $this->actingAs($this->auditi($this->sister))->get($this->url(null, "/{$pdf->id}/berkas"))->assertNotFound();
    }

    public function test_unduhan_bukti_audit_lain_atau_berkas_hilang_404(): void
    {
        $u = $this->auditi($this->induk);
        $lain = $this->buatAudit($this->induk->id);
        $b = $this->bukti($u, SmkpBukti::MENUNGGU, [], $lain);
        $this->actingAs($u)->get($this->url(null, "/{$b->id}/berkas"))->assertNotFound();
        $this->actingAs($u)->get($this->url($lain, "/{$b->id}/berkas"))->assertOk();

        Storage::disk('local')->delete($b->path);
        $this->actingAs($u)->get($this->url($lain, "/{$b->id}/berkas"))->assertNotFound();
    }

    public function test_berkas_tanpa_jenis_terdeteksi_selalu_diunduh(): void
    {
        $u = $this->auditi($this->induk);
        $b = $this->bukti($u, SmkpBukti::DITERIMA, ['mime' => null, 'nama' => 'foto.png']);
        $r = $this->actingAs($u)->get($this->url(null, "/{$b->id}/berkas?lihat=1"))->assertOk();
        $this->assertStringStartsWith('attachment;', (string) $r->headers->get('Content-Disposition'));
        $this->assertFalse($b->gambar());
    }

    /* ---------------- hapus audit ---------------- */

    public function test_menghapus_audit_membersihkan_folder_bukti(): void
    {
        $u = $this->auditi($this->induk);
        $lain = $this->buatAudit($this->induk->id);
        $this->unggah($u, [$this->pdf(), $this->png()])->assertSessionHasNoErrors();
        $this->unggah($u, [$this->pdf()], 'II.2.1', $lain)->assertSessionHasNoErrors();
        $this->assertCount(2, Storage::disk('local')->allFiles(SmkpBukti::folder($this->audit->id)));

        // Hanya administrator yang boleh menghapus audit.
        $this->actingAs($this->auditor($this->induk))->delete("/smkp/{$this->audit->id}")->assertForbidden();
        $this->actingAs($this->admin())->delete("/smkp/{$this->audit->id}")->assertRedirect('/smkp');

        Storage::disk('local')->assertMissing(SmkpBukti::folder($this->audit->id));
        $this->assertSame(0, SmkpBukti::where('audit_id', $this->audit->id)->count());
        // Audit lain tidak tersentuh.
        $this->assertCount(1, Storage::disk('local')->allFiles(SmkpBukti::folder($lain->id)));
        $this->assertSame(1, SmkpBukti::where('audit_id', $lain->id)->count());
    }

    /* ---------------- halaman Penilaian ---------------- */

    public function test_halaman_penilaian_memuat_permintaan_rencana_dan_berkas(): void
    {
        $pemilik = $this->auditi($this->induk);
        $rekan = $this->auditi($this->induk);
        $auditor = $this->auditor($this->induk);
        $milikPemilik = $this->bukti($pemilik, SmkpBukti::MENUNGGU, ['nama' => 'pertama.pdf']);
        $milikRekan = $this->bukti($rekan, SmkpBukti::DITOLAK, ['nama' => 'kedua.pdf', 'catatan' => 'Buram']);
        $milikAuditor = $this->bukti($auditor, SmkpBukti::DITERIMA, ['nama' => 'ketiga.pdf', 'diverifikasi_oleh' => $auditor->id, 'diverifikasi_pada' => now()]);
        $this->bukti($pemilik, SmkpBukti::MENUNGGU, ['kode' => 'I.1']);   // elemen lain: tidak ikut dimuat

        $this->actingAs($pemilik)->get("/smkp/{$this->audit->id}/elemen/II?sub=II.2")->assertOk()->assertInertia(fn (Inertia $p) => $p
            ->component('Smkp/Nilai')
            ->where('pilihSub', 'II.2')
            ->where('sub.0.kode', 'II.1')->where('sub.0.diminta', null)
            ->where('sub.1.kode', 'II.2')
            ->where('sub.1.diminta', ['metode' => 'Telaah dokumen & wawancara', 'sampel' => 'SOP identifikasi bahaya, 3 laporan inspeksi'])
            ->where('sub.1.butir.0.kode', 'II.2.1')
            // Terbaru di atas; auditi hanya dapat menarik berkasnya sendiri yang belum diterima.
            ->has('sub.1.butir.0.berkas', 3)
            ->where('sub.1.butir.0.berkas.0.id', $milikAuditor->id)->where('sub.1.butir.0.berkas.0.bisaHapus', false)
            ->where('sub.1.butir.0.berkas.0.verifikator', $auditor->name)
            ->where('sub.1.butir.0.berkas.1.id', $milikRekan->id)->where('sub.1.butir.0.berkas.1.bisaHapus', false)
            ->where('sub.1.butir.0.berkas.1.catatan', 'Buram')
            ->where('sub.1.butir.0.berkas.2.id', $milikPemilik->id)->where('sub.1.butir.0.berkas.2.bisaHapus', true)
            ->where('sub.1.butir.0.berkas.2.oleh', $pemilik->name)
            ->where('sub.1.butir.0.berkas.2.url', route('smkp.bukti.unduh', [$this->audit->id, $milikPemilik->id]))
            ->where('sub.1.butir.1.berkas', [])
            ->where('bukti.bisaUnggah', true)
            ->where('bukti.batas', ['jumlah' => BuktiAuditController::MAKS_BERKAS, 'kb' => BuktiAuditController::MAKS_KB, 'ekstensi' => LampiranBukti::EKSTENSI])
            ->where('bukti.adaRencana', true));

        // Auditor dapat menghapus semuanya; sub dari elemen lain atau tak dikenal tidak dipilih.
        $this->actingAs($auditor)->get("/smkp/{$this->audit->id}/elemen/II?sub=I.1")->assertInertia(fn (Inertia $p) => $p
            ->where('pilihSub', null)
            ->where('sub.1.butir.0.berkas.0.bisaHapus', true)
            ->where('sub.1.butir.0.berkas.2.bisaHapus', true));
        $this->actingAs($auditor)->get("/smkp/{$this->audit->id}/elemen/II?sub[]=II.2")->assertOk()
            ->assertInertia(fn (Inertia $p) => $p->where('pilihSub', null));
        // Isian kosong di Rencana Audit tidak dihitung sebagai permintaan.
        $this->actingAs($auditor)->get("/smkp/{$this->audit->id}/elemen/I")->assertInertia(fn (Inertia $p) => $p
            ->where('sub.0.kode', 'I.1')->where('sub.0.diminta', null)->has('sub.0.butir.0.berkas', 1));
    }

    public function test_tanpa_rencana_audit_tidak_ada_permintaan(): void
    {
        $this->audit->update(['rencana' => null]);
        $this->actingAs($this->auditi($this->induk))->get("/smkp/{$this->audit->id}/elemen/II")->assertInertia(fn (Inertia $p) => $p
            ->where('bukti.adaRencana', false)->where('sub.1.diminta', null)->where('pilihSub', null));
    }

    /* ---------------- halaman Bukti ---------------- */

    public function test_halaman_bukti_merangkum_permintaan_dan_antrean(): void
    {
        $auditi = $this->auditi($this->induk);
        $auditor = $this->auditor($this->induk);
        $pertama = $this->bukti($auditi);
        $this->bukti($auditi);
        $this->bukti($auditi, SmkpBukti::DITOLAK, ['catatan' => 'Buram']);
        $this->bukti($auditor, SmkpBukti::DITERIMA, ['kode' => 'II.2.2']);
        $this->bukti($auditi, SmkpBukti::MENUNGGU, [], $this->buatAudit($this->induk->id));   // audit lain

        $this->actingAs($auditor)->get($this->url())->assertOk()->assertInertia(fn (Inertia $p) => $p
            ->component('Smkp/Bukti')
            ->where('audit.id', $this->audit->id)
            ->has('elemen', count(Smkp::elemen()))
            ->where('elemen.1.kode', 'II')
            ->where('elemen.1.sub.1.kode', 'II.2')
            ->where('elemen.1.sub.1.diminta.metode', 'Telaah dokumen & wawancara')
            ->where('elemen.1.sub.0.diminta', null)
            ->where('elemen.0.sub.0.diminta', null)
            ->where('elemen.1.sub.1.butir.0', ['kode' => 'II.2.1', 'nama' => Smkp::butirSub(Smkp::elemen()[1]['sub'][1])[0]['nama'],
                'menunggu' => 2, 'diterima' => 0, 'ditolak' => 1])
            ->where('elemen.1.sub.1.butir.1.diterima', 1)
            ->where('elemen.1.sub.1.url', route('smkp.nilai', [$this->audit->id, 'II', 'sub' => 'II.2']))
            // Antrean: hanya yang menunggu di audit ini, terlama lebih dulu.
            ->has('antrean', 2)
            ->where('antrean.0.id', $pertama->id)
            ->where('antrean.0.status', SmkpBukti::MENUNGGU)
            ->where('antrean.0.url', route('smkp.bukti.unduh', [$this->audit->id, $pertama->id]))
            ->where('antrean.0.tautan', route('smkp.nilai', [$this->audit->id, 'II', 'sub' => 'II.2']).'#butir-II.2.1')
            ->where('bisaSunting', true)->where('bisaUnggah', true)
            ->where('rencanaUrl', route('smkp.rencana', $this->audit->id)));

        // Auditi melihat kemajuan unggahan, tanpa antrean verifikasi.
        $this->actingAs($auditi)->get($this->url())->assertOk()->assertInertia(fn (Inertia $p) => $p
            ->component('Smkp/Bukti')
            ->where('antrean', [])
            ->where('bisaSunting', false)->where('bisaUnggah', true)
            ->where('elemen.1.sub.1.butir.0.menunggu', 2));
    }
}
