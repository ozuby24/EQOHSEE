<?php

namespace Tests\Feature;

use App\Models\{SmkpAudit, SmkpFinding, SmkpTindakLanjut, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/** Auditi menyampaikan tindak lanjut temuan beserta lampiran bukti; auditor memverifikasi. */
class TindakLanjutSmkpTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    private SmkpAudit $audit;
    private SmkpFinding $temuan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanGrup();

        $this->audit = SmkpAudit::withoutGlobalScopes()->create([
            'company_id' => $this->induk->id, 'tahun' => 2026, 'status' => 'berjalan', 'tahap' => 2, 'hasil' => [],
        ]);
        $this->temuan = SmkpFinding::withoutGlobalScopes()->create([
            'audit_id' => $this->audit->id, 'kode_kriteria' => 'I.1', 'jenis' => 'mayor',
            'uraian' => 'Kebijakan belum disosialisasikan', 'status' => 'Open',
        ]);
    }

    private function url(string $akhir = ''): string
    {
        return "/smkp/{$this->audit->id}/temuan/{$this->temuan->id}/tindak-lanjut{$akhir}";
    }

    private function kirim(User $u, array $lampiran = [], array $isi = [])
    {
        return $this->actingAs($u)->from("/smkp/{$this->audit->id}/temuan")->post($this->url(), $isi + [
            'akar_masalah' => 'Belum ada jadwal sosialisasi', 'tindakan' => 'Sosialisasi ke seluruh pekerja',
            'bukti_perbaikan' => 'Daftar hadir', 'penanggung_jawab' => 'Dept. OHSE', 'lampiran' => $lampiran,
        ]);
    }

    public function test_auditi_mengirim_tindak_lanjut_dengan_lampiran(): void
    {
        $u = $this->auditi($this->induk);
        $this->kirim($u, [
            UploadedFile::fake()->image('foto rambu.jpg', 640, 480),
            UploadedFile::fake()->create('berita-acara.pdf', 120, 'application/pdf'),
        ])->assertRedirect("/smkp/{$this->audit->id}/temuan")->assertSessionHasNoErrors();

        $t = $this->temuan->fresh();
        $this->assertSame(['In Progress', 'Sosialisasi ke seluruh pekerja', 'Belum ada jadwal sosialisasi', 'Dept. OHSE'],
            [$t->status, $t->tindakan, $t->akar_masalah, $t->penanggung_jawab]);

        $k = SmkpTindakLanjut::sole();
        $this->assertSame($u->id, $k->user_id);
        $this->assertCount(2, $k->lampiran);
        $this->assertSame(['foto rambu.jpg', 'berita-acara.pdf'], array_column($k->lampiran, 'nama'));
        foreach ($k->lampiran as $l) {
            $this->assertStringStartsWith("tindak-lanjut/{$this->audit->id}/{$this->temuan->id}/", $l['path']);
            Storage::disk('local')->assertExists($l['path']);
        }
        $this->assertDatabaseHas('activity_log', ['action' => 'Tindak lanjut temuan', 'user_id' => $u->id]);

        // Kiriman kedua tidak mengubah status yang sudah In Progress dan tampil paling atas.
        $this->kirim($u, [], ['tindakan' => 'Susulan: uji pemahaman'])->assertSessionHasNoErrors();
        $this->actingAs($u)->get("/smkp/{$this->audit->id}/temuan?buka={$this->temuan->id}")->assertInertia(fn (Inertia $p) => $p
            ->component('Smkp/Temuan')
            ->where('bisaTindakLanjut', true)->where('bisaSunting', false)->where('buka', $this->temuan->id)
            ->has('temuan.0.kiriman', 2)
            ->where('temuan.0.kiriman.0.tindakan', 'Susulan: uji pemahaman')
            ->where('temuan.0.kiriman.1.lampiran.0.nama', 'foto rambu.jpg')
            ->where('temuan.0.kiriman.1.lampiran.0.gambar', true)
            ->where('temuan.0.kiriman.1.bisaHapus', true));
    }

    public function test_berkas_berbahaya_atau_berlebihan_ditolak(): void
    {
        $u = $this->auditi($this->induk);
        $tolak = [
            UploadedFile::fake()->create('skrip.php', 1, 'application/x-php'),
            UploadedFile::fake()->create('halaman.html', 1, 'text/html'),
            UploadedFile::fake()->create('gambar.svg', 1, 'image/svg+xml'),
            UploadedFile::fake()->create('menyamar.pdf', 1, 'text/html'),      // nama aman, isi bukan PDF
            UploadedFile::fake()->create('besar.pdf', 10241, 'application/pdf'),
        ];
        foreach ($tolak as $f) {
            $this->kirim($u, [$f])->assertSessionHasErrors('lampiran.0');
        }
        $this->kirim($u, array_map(fn ($i) => UploadedFile::fake()->create("b{$i}.pdf", 1, 'application/pdf'), range(1, 6)))
            ->assertSessionHasErrors('lampiran');
        $this->kirim($u, [], ['tindakan' => ''])->assertSessionHasErrors('tindakan');

        $this->assertSame(0, SmkpTindakLanjut::count());
        $this->assertSame('Open', $this->temuan->fresh()->status);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_hanya_auditi_dalam_jangkauan_yang_dapat_mengirim(): void
    {
        // Perusahaan lain: temuan tidak terlihat sama sekali.
        $this->kirim($this->auditi($this->sister))->assertNotFound();
        $this->kirim($this->auditi(null))->assertNotFound();
        // Auditor dan administrator memakai formulir temuan lengkap, bukan jalur auditi.
        $this->kirim($this->auditor($this->induk))->assertForbidden();
        $this->kirim($this->admin())->assertForbidden();

        // Auditi PJP tidak menjangkau audit induknya.
        $this->kirim($this->auditi($this->pjp))->assertNotFound();
        $this->assertSame(0, SmkpTindakLanjut::count());
    }

    public function test_temuan_yang_sudah_ditutup_tidak_dapat_ditindaklanjuti_lagi(): void
    {
        $this->temuan->update(['status' => 'Closed', 'tanggal_selesai' => '2026-06-01']);
        $this->kirim($this->auditi($this->induk), [UploadedFile::fake()->create('a.pdf', 1, 'application/pdf')])
            ->assertSessionHasErrors('tindakan');

        $this->assertSame(0, SmkpTindakLanjut::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_lampiran_hanya_diunduh_dalam_jangkauan(): void
    {
        $u = $this->auditi($this->induk);
        $this->kirim($u, [
            UploadedFile::fake()->image('foto.png', 40, 40),
            UploadedFile::fake()->create('ba.pdf', 4, 'application/pdf'),
        ]);
        $k = SmkpTindakLanjut::sole();

        $r = $this->actingAs($u)->get($this->url("/{$k->id}/berkas/1"))->assertOk();
        $this->assertStringContainsString('attachment', (string) $r->headers->get('Content-Disposition'));
        $this->assertStringContainsString('ba.pdf', (string) $r->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));

        // Pratinjau langsung hanya untuk gambar; PDF tetap diunduh.
        $g = $this->actingAs($u)->get($this->url("/{$k->id}/berkas/0?lihat=1"))->assertOk();
        $this->assertSame('image/png', $g->headers->get('Content-Type'));
        $this->assertStringNotContainsString('attachment', (string) $g->headers->get('Content-Disposition'));
        $p = $this->actingAs($u)->get($this->url("/{$k->id}/berkas/1?lihat=1"))->assertOk();
        $this->assertStringContainsString('attachment', (string) $p->headers->get('Content-Disposition'));

        $this->actingAs($this->auditor($this->induk))->get($this->url("/{$k->id}/berkas/0"))->assertOk();
        $this->actingAs($this->auditi($this->sister))->get($this->url("/{$k->id}/berkas/0"))->assertNotFound();
        $this->actingAs($u)->get($this->url("/{$k->id}/berkas/9"))->assertNotFound();

        // Kiriman milik temuan lain tidak dapat dibaca lewat temuan ini.
        $lain = SmkpFinding::withoutGlobalScopes()->create([
            'audit_id' => $this->audit->id, 'kode_kriteria' => 'I.2', 'jenis' => 'minor', 'uraian' => 'Lain', 'status' => 'Open',
        ]);
        $this->actingAs($u)->get("/smkp/{$this->audit->id}/temuan/{$lain->id}/tindak-lanjut/{$k->id}/berkas/0")->assertNotFound();
    }

    public function test_kiriman_ditarik_pengirimnya_atau_auditor_beserta_berkasnya(): void
    {
        $u = $this->auditi($this->induk);
        $this->kirim($u, [UploadedFile::fake()->create('a.pdf', 1, 'application/pdf')]);
        $k = SmkpTindakLanjut::sole();
        $path = $k->lampiran[0]['path'];

        $this->actingAs($this->auditi($this->induk))->delete($this->url("/{$k->id}"))->assertForbidden();
        $this->actingAs($u)->delete($this->url("/{$k->id}"))->assertRedirect();
        $this->assertSame(0, SmkpTindakLanjut::count());
        Storage::disk('local')->assertMissing($path);

        // Setelah ditutup, pengirim tidak dapat menarik kirimannya; auditor tetap bisa.
        $this->kirim($u);
        $k = SmkpTindakLanjut::sole();
        $this->temuan->update(['status' => 'Closed']);
        $this->actingAs($u)->delete($this->url("/{$k->id}"))->assertForbidden();
        $this->actingAs($this->auditor($this->induk))->delete($this->url("/{$k->id}"))->assertRedirect();
        $this->assertSame(0, SmkpTindakLanjut::count());
    }

    public function test_menghapus_temuan_atau_audit_membersihkan_berkas(): void
    {
        $u = $this->auditi($this->induk);
        $this->kirim($u, [UploadedFile::fake()->create('a.pdf', 1, 'application/pdf')]);
        $this->assertCount(1, Storage::disk('local')->allFiles());

        $this->actingAs($this->auditor($this->induk))->delete("/smkp/{$this->audit->id}/temuan/{$this->temuan->id}")->assertRedirect();
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame(0, SmkpTindakLanjut::count());

        $t2 = SmkpFinding::withoutGlobalScopes()->create([
            'audit_id' => $this->audit->id, 'kode_kriteria' => 'I.3', 'jenis' => 'minor', 'uraian' => 'X', 'status' => 'Open',
        ]);
        $this->actingAs($u)->post("/smkp/{$this->audit->id}/temuan/{$t2->id}/tindak-lanjut", [
            'tindakan' => 'Perbaikan', 'lampiran' => [UploadedFile::fake()->create('b.pdf', 1, 'application/pdf')],
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->admin())->delete("/smkp/{$this->audit->id}")->assertRedirect('/smkp');
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_daftar_tindak_lanjut_mengikuti_jangkauan_dan_saringan(): void
    {
        $milikSister = SmkpAudit::withoutGlobalScopes()->create(['company_id' => $this->sister->id, 'tahun' => 2026, 'status' => 'berjalan', 'tahap' => 2, 'hasil' => []]);
        SmkpFinding::withoutGlobalScopes()->create(['audit_id' => $milikSister->id, 'kode_kriteria' => 'II.1', 'jenis' => 'minor', 'uraian' => 'Sister', 'status' => 'Open']);
        SmkpFinding::withoutGlobalScopes()->create(['audit_id' => $this->audit->id, 'kode_kriteria' => 'III.1', 'jenis' => 'minor', 'uraian' => 'Sudah ditutup', 'status' => 'Closed']);

        $u = $this->auditi($this->induk);
        $this->actingAs($u)->get('/smkp/tindak-lanjut')->assertInertia(fn (Inertia $p) => $p
            ->component('Smkp/TindakLanjut')
            ->where('bisaKirim', true)->where('status', 'terbuka')
            ->where('daftar.total', 1)->where('daftar.data.0.kode', 'I.1')
            ->where('daftar.data.0.url', fn ($url) => str_ends_with($url, "/smkp/{$this->audit->id}/temuan?buka={$this->temuan->id}"))
            ->where('jumlah.terbuka', 1)->where('jumlah.Closed', 1)->where('jumlah.semua', 2));

        $this->actingAs($u)->get('/smkp/tindak-lanjut?status=Closed')->assertInertia(fn (Inertia $p) => $p
            ->where('daftar.total', 1)->where('daftar.data.0.kode', 'III.1'));
        $this->actingAs($u)->get('/smkp/tindak-lanjut?status=semua&q=sosialisasi')->assertInertia(fn (Inertia $p) => $p
            ->where('daftar.total', 1)->where('jumlah.semua', 1));
        $this->actingAs($u)->get('/smkp/tindak-lanjut?status=asal')->assertInertia(fn (Inertia $p) => $p->where('status', 'terbuka'));

        $this->actingAs($this->auditor($this->induk))->get('/smkp/tindak-lanjut')->assertInertia(fn (Inertia $p) => $p
            ->where('bisaKirim', false)->where('daftar.total', 1));
        $this->actingAs($this->admin())->get('/smkp/tindak-lanjut?status=semua')->assertInertia(fn (Inertia $p) => $p
            ->where('daftar.total', 3));
    }

    /** Berkas sungguhan di disk (bukan UploadedFile::fake) agar deteksi isi ikut diuji. */
    private function berkasAsli(string $nama, string $isi): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'uji');
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

    public function test_dokumen_office_dikenali_dari_strukturnya(): void
    {
        $u = $this->auditi($this->induk);
        $ole = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 504);

        // DOCX yang entri pertamanya bukan [Content_Types].xml, dokumen OLE lama,
        // dan OOXML berkata sandi (wadah OLE) — semuanya dokumen Office asli.
        $diterima = [
            $this->berkasAsli('laporan mac.docx', $this->zip(['customXml/item1.xml' => '<a/>', 'docProps/app.xml' => '<b/>', '[Content_Types].xml' => '<Types/>', 'word/document.xml' => '<w/>'])),
            $this->berkasAsli('lama.doc', $ole),
            $this->berkasAsli('bersandi.xlsx', $ole),
        ];
        $this->kirim($u, $diterima)->assertSessionHasNoErrors();
        $this->assertSame(['laporan mac.docx', 'lama.doc', 'bersandi.xlsx'], array_column(SmkpTindakLanjut::sole()->lampiran, 'nama'));

        // ZIP sembarang atau teks yang dinamai dokumen Office ditolak, dengan nama berkasnya.
        $this->kirim($u, [$this->berkasAsli('arsip.docx', $this->zip(['a.txt' => 'x']))])
            ->assertSessionHasErrors(['lampiran.0' => '"arsip.docx": isinya tidak sesuai jenis berkasnya — unggah PDF, gambar, atau dokumen Office.']);
        $this->kirim($u, [$this->berkasAsli('palsu.doc', 'bukan dokumen')])->assertSessionHasErrors('lampiran.0');
        $this->kirim($u, [$this->berkasAsli('gambar.png', '<svg xmlns="http://www.w3.org/2000/svg"/>')])->assertSessionHasErrors('lampiran.0');
        $this->assertSame(1, SmkpTindakLanjut::count());
    }

    public function test_nama_unduhan_selalu_berekstensi(): void
    {
        $u = $this->auditi($this->induk);
        $this->kirim($u, [UploadedFile::fake()->create(str_repeat('Panjang', 30).'.pdf', 1, 'application/pdf')]);
        $nama = SmkpTindakLanjut::sole()->lampiran[0]['nama'];
        $this->assertStringEndsWith('.pdf', $nama);
        $this->assertLessThanOrEqual(144, mb_strlen($nama));
    }

    public function test_kuota_lampiran_per_temuan_dan_harian(): void
    {
        $u = $this->auditi($this->induk);
        $pdf = fn () => [UploadedFile::fake()->create('a.pdf', 1, 'application/pdf')];

        // Temuan ini sudah menampung lampiran sebesar kuotanya.
        $this->temuan->tindakLanjut()->create(['user_id' => null, 'tindakan' => 'lama',
            'lampiran' => [['path' => 'x', 'nama' => 'x.pdf', 'mime' => 'application/pdf', 'ukuran' => \App\Http\Controllers\TindakLanjutController::KUOTA_TEMUAN]]]);
        $this->kirim($u, $pdf())->assertSessionHasErrors('lampiran');
        // Tanpa lampiran tetap boleh menyampaikan tindak lanjut.
        $this->kirim($u)->assertSessionHasNoErrors();

        // Kuota harian dihitung dari seluruh kiriman pengguna itu dalam 24 jam.
        $lain = SmkpFinding::withoutGlobalScopes()->create(['audit_id' => $this->audit->id, 'kode_kriteria' => 'I.4', 'jenis' => 'minor', 'uraian' => 'Lain', 'status' => 'Open']);
        $lain->tindakLanjut()->create(['user_id' => $u->id, 'tindakan' => 'hari ini',
            'lampiran' => [['path' => 'y', 'nama' => 'y.pdf', 'mime' => 'application/pdf', 'ukuran' => \App\Http\Controllers\TindakLanjutController::KUOTA_HARIAN]]]);
        $this->actingAs($u)->post("/smkp/{$this->audit->id}/temuan/{$lain->id}/tindak-lanjut", ['tindakan' => 'Baru', 'lampiran' => $pdf()])
            ->assertSessionHasErrors('lampiran');
    }

    public function test_menarik_kiriman_memulihkan_isian_temuan(): void
    {
        $u = $this->auditi($this->induk);
        $this->kirim($u, [], ['tindakan' => 'Pertama', 'akar_masalah' => 'Akar pertama']);
        $this->kirim($u, [], ['tindakan' => 'SALAH KIRIM', 'akar_masalah' => 'Rahasia']);
        [$terbaru, $pertama] = $this->temuan->tindakLanjut()->get()->all();

        $this->actingAs($u)->delete($this->url("/{$terbaru->id}"))->assertRedirect();
        $t = $this->temuan->fresh();
        $this->assertSame(['Pertama', 'Akar pertama', 'In Progress'], [$t->tindakan, $t->akar_masalah, $t->status]);

        $this->actingAs($u)->delete($this->url("/{$pertama->id}"))->assertRedirect();
        $t = $this->temuan->fresh();
        $this->assertSame([null, null, 'Open'], [$t->tindakan, $t->akar_masalah, $t->status]);
    }

    public function test_id_kiriman_tak_wajar_menjadi_404(): void
    {
        $u = $this->auditi($this->induk);
        $this->actingAs($u)->get($this->url('/99999999999999999999999/berkas/0'))->assertNotFound();
        $this->actingAs($u)->delete($this->url('/99999999999999999999999'))->assertNotFound();
        $this->actingAs($u)->get($this->url('/1/berkas/99999'))->assertNotFound();
    }
}
