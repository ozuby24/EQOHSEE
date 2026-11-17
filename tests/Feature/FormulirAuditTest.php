<?php

namespace Tests\Feature;

use App\Models\{SmkpAudit, SmkpFinding};
use App\Support\Smkp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/** Paket formulir audit SMKP (lampiran Kepdirjen 185.K/37.04/DJB/2019). */
class FormulirAuditTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanGrup();
    }

    private function audit($c = null, array $hasil = []): SmkpAudit
    {
        return SmkpAudit::withoutGlobalScopes()->create([
            'company_id' => ($c ?? $this->induk)->id, 'tahun' => 2026, 'status' => 'berjalan', 'tahap' => 2,
            'ketua_auditor' => 'Ketua Uji', 'hasil' => $hasil,
        ]);
    }

    /** Sub-elemen I.1 dinilai separuh agar menjadi ketidaksesuaian minor. */
    private function hasilSeparuh(): array
    {
        $sub = Smkp::elemen()[0]['sub'][0];
        $b   = Smkp::butirSub($sub)[0];

        return [$b['kode'] => ['v' => intdiv((int) $b['maks'], 2) ?: 1]];
    }

    public function test_halaman_formulir_menampilkan_tujuh_formulir_dan_status(): void
    {
        $a = $this->audit();
        $a->findings()->create(['kode_kriteria' => 'I.1', 'jenis' => 'kritikal', 'uraian' => 'Kebijakan belum ditetapkan', 'status' => 'Open']);

        $this->actingAs($this->auditor($this->induk))->get("/smkp/{$a->id}/formulir")
            ->assertOk()
            ->assertInertia(fn (Inertia $p) => $p->component('Smkp/Formulir')
                ->has('kriteria', 7)
                ->where('ringkas.ketidaksesuaian', 1)
                ->where('ringkas.kategori.kritikal', 1));

        // Audit perusahaan di luar jangkauan tidak terlihat.
        $lain = $this->audit($this->sister);
        $this->actingAs($this->auditor($this->induk))->get("/smkp/{$lain->id}/formulir")->assertNotFound();
    }

    public function test_simpan_kesesuaian_dan_rekomendasi_membuang_baris_tidak_sah(): void
    {
        $a = $this->audit();

        $this->actingAs($this->auditor($this->induk))->post("/smkp/{$a->id}/formulir", [
            'kesesuaian' => [
                ['elemen' => 'I', 'sub' => 'I.1', 'keterangan' => 'Kebijakan K3 ditandatangani direksi.'],
                ['elemen' => 'II', 'sub' => 'I.1', 'keterangan' => 'Sub-elemen salah elemen.'],   // sub tidak cocok → dikosongkan
                ['elemen' => 'IX', 'sub' => '', 'keterangan' => 'Elemen tidak dikenal.'],         // dibuang
                ['elemen' => 'III', 'sub' => '', 'keterangan' => '   '],                         // tanpa uraian → dibuang
                ['elemen' => 'II', 'sub' => 'II.2', 'subsub' => 'II.2.1', 'keterangan' => 'Praktik terbaik 1.'],
                ['elemen' => 'II', 'sub' => 'II.2', 'subsub' => 'III.2.1', 'keterangan' => 'Sub-sub salah sub.'],  // → dikosongkan
                ['elemen' => 'II', 'sub' => '', 'subsub' => 'II.2.1', 'keterangan' => 'Sub-sub tanpa sub.'],       // → dikosongkan
            ],
            'rekomendasi' => [['elemen' => 'VII', 'sub' => 'VII.1', 'uraian' => 'Tinjauan manajemen per semester.']],
        ])->assertRedirect();

        $a->refresh();
        $this->assertSame([
            ['elemen' => 'I', 'sub' => 'I.1', 'subsub' => '', 'keterangan' => 'Kebijakan K3 ditandatangani direksi.'],
            ['elemen' => 'II', 'sub' => '', 'subsub' => '', 'keterangan' => 'Sub-elemen salah elemen.'],
            ['elemen' => 'II', 'sub' => 'II.2', 'subsub' => 'II.2.1', 'keterangan' => 'Praktik terbaik 1.'],
            ['elemen' => 'II', 'sub' => 'II.2', 'subsub' => '', 'keterangan' => 'Sub-sub salah sub.'],
            ['elemen' => 'II', 'sub' => '', 'subsub' => '', 'keterangan' => 'Sub-sub tanpa sub.'],
        ], $a->kesesuaian);
        $this->assertCount(1, $a->rekomendasi);

        // Auditi hanya membaca.
        $this->actingAs($this->auditi($this->induk))->post("/smkp/{$a->id}/formulir", ['kesesuaian' => []])->assertForbidden();
    }

    public function test_baris_berisi_tanpa_elemen_ditolak_bukan_dibuang_diam_diam(): void
    {
        $a = $this->audit();
        $a->forceFill(['kesesuaian' => [['elemen' => 'I', 'sub' => '', 'subsub' => '', 'keterangan' => 'Isian lama.']]])->save();

        $this->actingAs($this->auditor($this->induk))->from("/smkp/{$a->id}/formulir")->post("/smkp/{$a->id}/formulir", [
            'kesesuaian' => [
                ['elemen' => 'I', 'sub' => '', 'keterangan' => 'Kebijakan K3 ditandatangani direksi.'],
                ['elemen' => '', 'sub' => '', 'keterangan' => 'Lupa memilih elemen.'],
                ['elemen' => '', 'sub' => '', 'keterangan' => ''],                       // benar-benar kosong → boleh dibuang
            ],
            'rekomendasi' => [['elemen' => '', 'sub' => '', 'uraian' => 'Rekomendasi tanpa elemen.']],
        ])->assertRedirect("/smkp/{$a->id}/formulir")
            ->assertSessionHasErrors(['kesesuaian.1.elemen', 'rekomendasi.0.elemen'])
            ->assertSessionDoesntHaveErrors(['kesesuaian.0.elemen', 'kesesuaian.2.elemen'])
            ->assertSessionMissing('ok');

        /* Tidak ada yang tersimpan sebagian. */
        $this->assertSame('Isian lama.', $a->fresh()->kesesuaian[0]['keterangan']);
        $this->assertCount(1, $a->fresh()->kesesuaian);

        /* Baris kosong tetap dibuang tanpa galat. */
        $this->post("/smkp/{$a->id}/formulir", [
            'kesesuaian' => [
                ['elemen' => 'I', 'sub' => '', 'keterangan' => 'Kebijakan K3 ditandatangani direksi.'],
                ['elemen' => '', 'sub' => '', 'keterangan' => '   '],
            ],
        ])->assertSessionHasNoErrors()->assertSessionHas('ok');
        $this->assertCount(1, $a->fresh()->kesesuaian);
    }

    public function test_temuan_diangkat_bernomor_dan_isian_formulir_tersimpan(): void
    {
        $a = $this->audit(null, $this->hasilSeparuh());
        $u = $this->auditor($this->induk);

        $this->actingAs($u)->post("/smkp/{$a->id}/temuan/angkat")->assertRedirect();
        $t = SmkpFinding::withoutGlobalScopes()->where('audit_id', $a->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/^IU-(MYR|MNR)-01-'.$a->tahun.'$/', $t->nomor, 'Nomor: (kode perusahaan)-(kategori)-01-(tahun).');

        $this->actingAs($u)->put("/smkp/{$a->id}/temuan/{$t->id}", [
            'nomor' => 'KTS-07', 'jenis' => 'kritikal', 'uraian' => 'Kebijakan belum dikomunikasikan',
            'lokasi' => 'Kantor tambang · Dept. OHSE', 'bukti' => 'Tidak ada daftar hadir sosialisasi',
            'deskripsi' => 'Kebijakan wajib dikomunikasikan ke seluruh pekerja',
            'akar_masalah' => 'Belum ada jadwal sosialisasi', 'tindakan' => 'Sosialisasi di safety talk',
            'bukti_perbaikan' => 'Daftar hadir', 'penanggung_jawab' => 'Kepala OHSE',
            'target_selesai' => '2026-12-01', 'status' => 'Closed', 'tanggal_selesai' => now()->subDays(3)->toDateString(),
            'verifikasi' => 'Efektif', 'nomor_tindak_lanjut' => 'TL-07',
        ])->assertRedirect();

        $t->refresh();
        $this->assertSame('kritikal', $t->jenis);
        $this->assertSame(now()->subDays(3)->toDateString(), $t->tanggal_selesai->toDateString());
        $this->assertNull($t->nomor_tindak_lanjut, 'Nomor tindak lanjut tidak diisi terpisah.');

        // Nomor tindak lanjut = nomor ketidaksesuaian; deskripsi otomatis dari kriteria tersedia untuk formulir.
        $this->actingAs($u)->get("/smkp/{$a->id}/formulir/ketidaksesuaian/cetak")->assertInertia(fn (Inertia $p) => $p
            ->where('temuan.0.nomor', 'KTS-07')->where('temuan.0.nomorTindakLanjut', 'KTS-07')
            ->where('temuan.0.deskripsi', 'Kebijakan wajib dikomunikasikan ke seluruh pekerja')
            ->where('temuan.0.deskripsiOtomatis', 'Ditemukan bukti kebijakan belum dikomunikasikan, berdasarkan tidak ada daftar hadir sosialisasi di kantor tambang · Dept. OHSE, sehingga tidak sesuai dengan '.Smkp::acuanTemuan($t->kode_kriteria).'.'));
        $this->actingAs($u)->get("/smkp/{$a->id}/temuan")->assertInertia(fn (Inertia $p) => $p
            ->where('temuan.0.acuan', Smkp::acuanTemuan($t->kode_kriteria))->where('temuan.0.namaKriteria', fn ($n) => $n !== '')
            ->missing('temuan.0.nomor_tindak_lanjut'));

        // Kategori di luar daftar ditolak; membuka kembali menghapus tanggal perbaikan.
        $this->actingAs($u)->put("/smkp/{$a->id}/temuan/{$t->id}", ['jenis' => 'ringan', 'status' => 'Open'])->assertSessionHasErrors('jenis');
        $this->actingAs($u)->put("/smkp/{$a->id}/temuan/{$t->id}", ['status' => 'In Progress'])->assertRedirect();
        $this->assertNull($t->refresh()->tanggal_selesai);
    }

    public function test_deskripsi_temuan_disusun_dari_uraian_bukti_lokasi_dan_referensi(): void
    {
        $this->assertSame(
            'Ditemukan bukti PT Allied Indo Coal Jaya belum melakukannya komunikasi kebijakan perusahaan, berdasarkan tinjauan dokumen dan rekaman di kantor Tambang, sehingga tidak sesuai dengan Sub Elemen I.4 – Komunikasi Kebijakan.',
            Smkp::deskripsiTemuan('I.4', 'PT Allied Indo Coal Jaya belum melakukannya komunikasi kebijakan perusahaan', 'Tinjauan dokumen dan rekaman', 'Kantor Tambang'),
        );
        // Sub-sub elemen menyebut sub elemennya; tanda baca penutup & baris baru dirapikan; bagian kosong dilewati.
        $this->assertSame(
            'Ditemukan bukti penilaian risiko belum melibatkan pekerja di pit Utara, sehingga tidak sesuai dengan Sub Elemen II.2 – Manajemen Risiko, Sub-sub Elemen II.2.1 – Komunikasi dan Konsultasi Risiko.',
            Smkp::deskripsiTemuan('II.2.1', "Penilaian risiko\nbelum melibatkan pekerja.", '  ', 'Pit Utara'),
        );
        // Uraian bawaan (nama kriteria) bukan pernyataan masalah; singkatan tidak dikecilkan.
        $this->assertSame('Ditemukan ketidaksesuaian, berdasarkan SOP belum ada, sehingga tidak sesuai dengan Sub Elemen I.1 – Penyusunan Kebijakan.',
            Smkp::deskripsiTemuan('I.1', 'Penyusunan Kebijakan', 'SOP belum ada', null));

        // Rekapitulasi/laporan tidak menambah "Hal ini tidak sesuai dengan …" kedua kalinya.
        $d = Smkp::deskripsiTemuan('I.4', 'Belum ada sosialisasi', 'Daftar hadir', 'Kantor');
        $this->assertSame($d, Smkp::uraianTemuan('I.4', $d, 'Belum ada sosialisasi'));
        $this->assertSame('Belum ada sosialisasi. Hal ini tidak sesuai dengan Sub Elemen I.4 Komunikasi Kebijakan.', Smkp::uraianTemuan('I.4', '', 'Belum ada sosialisasi'));
    }

    public function test_setiap_formulir_dapat_dicetak(): void
    {
        $a = $this->audit(null, $this->hasilSeparuh());
        $a->update(['kesesuaian' => [['elemen' => 'I', 'sub' => 'I.2', 'keterangan' => 'Sesuai']]]);
        $a->findings()->create(['kode_kriteria' => 'I.1', 'jenis' => 'minor', 'uraian' => 'Uji', 'status' => 'Open', 'nomor' => 'KTS-01']);
        $u = $this->auditi($this->induk);   // hak baca cukup untuk mencetak

        $halaman = [
            'penilaian' => 'Print/Smkp/FormulirPenilaian', 'kesesuaian' => 'Print/Smkp/FormulirKesesuaian',
            'ketidaksesuaian' => 'Print/Smkp/FormulirKetidaksesuaian', 'rekapitulasi' => 'Print/Smkp/FormulirRekapitulasi',
            'rekomendasi' => 'Print/Smkp/FormulirRekomendasi', 'tindak-lanjut' => 'Print/Smkp/FormulirTindakLanjut',
        ];
        foreach ($halaman as $jenis => $komponen) {
            $this->actingAs($u)->get("/smkp/{$a->id}/formulir/{$jenis}/cetak")
                ->assertOk()->assertInertia(fn (Inertia $p) => $p->component($komponen)->has('dok.nomor'));
        }

        $a->update(['kesesuaian' => [['elemen' => 'I', 'sub' => 'I.2', 'keterangan' => 'Sesuai'], ['elemen' => 'II', 'sub' => 'II.2', 'subsub' => 'II.2.1', 'keterangan' => 'Praktik']]]);
        $this->actingAs($u)->get("/smkp/{$a->id}/formulir/kesesuaian/cetak")
            ->assertInertia(fn (Inertia $p) => $p->where('baris.0.subNama', fn ($n) => $n !== '')->where('baris.0.subsub', '')
                ->where('baris.1.subsub', 'II.2.1')->where('baris.1.subsubNama', fn ($n) => $n !== '')
                ->where('ttd.bisaSunting', false)->where('ttd.gambar.ketua', null));
        $this->actingAs($u)->get("/smkp/{$a->id}/formulir/rekapitulasi/cetak")
            ->assertInertia(fn (Inertia $p) => $p->where('temuan.0.nomor', 'KTS-01')->where('temuan.0.labelKategori', 'Minor'));
        $this->actingAs($u)->get("/smkp/{$a->id}/formulir/penilaian/cetak")
            ->assertInertia(fn (Inertia $p) => $p->has('elemen', 7)->has('total.skor'));
        $this->actingAs($u)->get("/smkp/{$a->id}/formulir/tidak-ada/cetak")->assertNotFound();
        $this->actingAs($u)->get("/smkp/{$a->id}/rencana/cetak")
            ->assertInertia(fn (Inertia $p) => $p->component('Print/Smkp/RencanaAudit')->has('kriteriaDaftar', 7)->has('bawaan.dokumentasi'));
    }

    public function test_tanggal_dan_tanda_tangan_digital_formulir(): void
    {
        Storage::fake('local');
        $a = $this->audit();
        $t = $a->findings()->create(['kode_kriteria' => 'I.1', 'jenis' => 'minor', 'uraian' => 'Uji', 'status' => 'Open']);
        $u = $this->auditor($this->induk);
        $url = "/smkp/{$a->id}/formulir";

        $this->actingAs($u)->post("{$url}/tanggal", ['bagian' => 'kesesuaian', 'pihak' => 'ktt', 'tanggal' => '2026-10-20'])->assertRedirect();
        $this->actingAs($u)->post("{$url}/tanggal", ['bagian' => "ketidaksesuaian.{$t->id}.perbaikan", 'pihak' => 'ktt', 'tanggal' => '2026-10-21'])->assertRedirect();
        $this->actingAs($u)->post("{$url}/tanggal", ['bagian' => 'lain', 'pihak' => 'ktt', 'tanggal' => '2026-10-20'])->assertSessionHasErrors('bagian');
        $this->actingAs($u)->post("{$url}/tanggal", ['bagian' => 'kesesuaian', 'pihak' => 'x', 'tanggal' => '20-10-2026'])->assertSessionHasErrors(['pihak', 'tanggal']);

        // Bukan gambar (isi tidak cocok ekstensi) ditolak — berkas nyata agar jenis isi dibaca finfo.
        $palsu = tempnam(sys_get_temp_dir(), 'ttd');
        file_put_contents($palsu, '<?php echo 1;');
        $this->actingAs($u)->post("{$url}/ttd", ['pihak' => 'ketua', 'berkas' => new UploadedFile($palsu, 'ttd.png', null, null, true)])
            ->assertSessionHasErrors('berkas');
        @unlink($palsu);
        $this->assertArrayNotHasKey('gambar', $a->fresh()->ttd_formulir);
        $this->actingAs($u)->post("{$url}/ttd", ['pihak' => 'ketua', 'berkas' => UploadedFile::fake()->image('ttd.png', 300, 100)])->assertRedirect();
        $lama = $a->fresh()->ttd_formulir['gambar']['ketua'];
        $this->actingAs($u)->post("{$url}/ttd", ['pihak' => 'ketua', 'berkas' => UploadedFile::fake()->image('ttd.jpg', 300, 100)])->assertRedirect();
        $baru = $a->fresh()->ttd_formulir['gambar']['ketua'];
        Storage::disk('local')->assertMissing($lama);
        Storage::disk('local')->assertExists($baru);
        $this->assertStringStartsWith(SmkpAudit::folderTtd($a->id).'/ketua-', $baru);

        $this->actingAs($u)->get("{$url}/kesesuaian/cetak")->assertInertia(fn (Inertia $p) => $p
            ->where('ttd.tanggal.kesesuaian.ktt.nilai', '2026-10-20')
            ->where('ttd.tanggal.kesesuaian.ktt.teks', '20 Oktober 2026')
            ->where('ttd.gambar.ketua', fn ($g) => str_starts_with($g, "/smkp/{$a->id}/formulir/ttd/ketua?v="))
            ->where('ttd.gambar.ktt', null)->where('ttd.bisaSunting', true));
        $this->actingAs($u)->get("{$url}/ketidaksesuaian/cetak")
            ->assertInertia(fn (Inertia $p) => $p->where('ttd.tanggal', fn ($tg) => collect($tg)->toArray()["ketidaksesuaian.{$t->id}.perbaikan"]['ktt']['nilai'] === '2026-10-21'));

        // Gambar dapat dilihat siapa pun yang menjangkau audit; auditi tidak dapat mengubahnya.
        $auditi = $this->auditi($this->induk);
        $this->actingAs($auditi)->get("{$url}/ttd/ketua")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->actingAs($auditi)->get("{$url}/ttd/ktt")->assertNotFound();
        $this->actingAs($auditi)->post("{$url}/tanggal", ['bagian' => 'kesesuaian', 'pihak' => 'ktt', 'tanggal' => null])->assertForbidden();
        $this->actingAs($auditi)->delete("{$url}/ttd/ketua")->assertForbidden();
        $lain = $this->audit($this->sister);
        $this->actingAs($this->auditor($this->induk))->get("/smkp/{$lain->id}/formulir/ttd/ketua")->assertNotFound();

        // Tanggal kosong menghapus isian; hapus tanda tangan menghapus berkasnya.
        $this->actingAs($u)->post("{$url}/tanggal", ['bagian' => 'kesesuaian', 'pihak' => 'ktt', 'tanggal' => ''])->assertRedirect();
        $this->actingAs($u)->delete("{$url}/ttd/ketua")->assertRedirect();
        Storage::disk('local')->assertMissing($baru);
        $this->assertSame(['tanggal' => ["ketidaksesuaian.{$t->id}.perbaikan" => ['ktt' => '2026-10-21']]], $a->fresh()->ttd_formulir);
    }

    public function test_tanda_tangan_tindak_lanjut_dibubuhkan_sendiri_setelah_syaratnya_terpenuhi(): void
    {
        Storage::fake('local');
        $a = $this->audit();
        $t = $a->findings()->create(['kode_kriteria' => 'I.4', 'jenis' => 'mayor', 'uraian' => 'Kebijakan belum dikomunikasikan', 'status' => 'Open', 'nomor' => 'KTS-05']);
        $auditor = $this->auditor($this->induk);
        $auditi = $this->auditi($this->induk);
        $url = "/smkp/{$a->id}/formulir";
        $perbaikan = "ketidaksesuaian.{$t->id}.perbaikan";
        $verifikasi = "ketidaksesuaian.{$t->id}.verifikasi";

        // Auditi mengunggah tanda tangannya sendiri (KTT), bukan milik auditor.
        $this->actingAs($auditi)->post("{$url}/ttd", ['pihak' => 'ktt', 'berkas' => UploadedFile::fake()->image('a.png', 300, 100)])->assertRedirect();
        $this->actingAs($auditi)->post("{$url}/ttd", ['pihak' => 'ketua', 'berkas' => UploadedFile::fake()->image('b.png', 300, 100)])->assertForbidden();
        $this->actingAs($auditor)->post("{$url}/ttd", ['pihak' => 'ketua', 'berkas' => UploadedFile::fake()->image('c.png', 300, 100)])->assertRedirect();

        // Belum ada tindak lanjut: tanda tangan perbaikan belum dapat dibubuhkan, dan tidak tampil otomatis.
        $this->actingAs($auditi)->post("{$url}/ttd/bubuh", ['bagian' => $perbaikan, 'ttd' => true])->assertSessionHasErrors('ttd');
        $this->actingAs($auditi)->get("{$url}/ketidaksesuaian/cetak")->assertInertia(fn (Inertia $p) => $p
            ->where('ttd.bubuh', fn ($b) => collect($b)->isEmpty())->where('ttd.bisaBubuh', fn ($b) => collect($b)->filter()->keys()->all() === ['ktt', 'pjo'])
            ->where('ttd.bisaGambar', fn ($b) => collect($b)->filter()->keys()->all() === ['ktt', 'pjo'])
            ->where('temuan.0.syaratTtd.perbaikan', 'Menunggu tindak lanjut auditi.'));

        $t->tindakLanjut()->create(['user_id' => $auditi->id, 'tindakan' => 'Sosialisasi kebijakan', 'lampiran' => [['nama' => 'daftar-hadir.pdf', 'path' => 'x/y.pdf', 'mime' => 'application/pdf', 'ukuran' => 10]]]);

        // Hanya auditi yang membubuhkan tanda tangan perbaikan; tanggalnya terisi hari ini.
        $this->actingAs($auditor)->post("{$url}/ttd/bubuh", ['bagian' => $perbaikan, 'ttd' => true])->assertForbidden();
        $this->actingAs($auditi)->post("{$url}/ttd/bubuh", ['bagian' => $perbaikan, 'ttd' => true])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($a->fresh()->ttd_formulir['bubuh'][$perbaikan]['ktt']);
        $this->assertSame(\App\Support\Waktu::kini()->toDateString(), $a->fresh()->ttd_formulir['tanggal'][$perbaikan]['ktt']);
        $this->actingAs($auditi)->post("{$url}/tanggal", ['bagian' => $perbaikan, 'pihak' => 'ktt', 'tanggal' => '2026-10-20'])->assertRedirect();
        $this->actingAs($auditi)->post("{$url}/tanggal", ['bagian' => $verifikasi, 'pihak' => 'ketua', 'tanggal' => '2026-10-20'])->assertForbidden();

        // Auditor menandatangani verifikasi setelah memverifikasi bukti tindak lanjut.
        $this->actingAs($auditi)->post("{$url}/ttd/bubuh", ['bagian' => $verifikasi, 'ttd' => true])->assertForbidden();
        $this->actingAs($auditor)->post("{$url}/ttd/bubuh", ['bagian' => $verifikasi, 'ttd' => true])
            ->assertSessionHasErrors(['ttd' => 'Verifikasi bukti tindak lanjut belum diisi.']);
        $t->update(['verifikasi' => 'Daftar hadir sosialisasi sesuai.']);
        $this->actingAs($auditor)->post("{$url}/ttd/bubuh", ['bagian' => $verifikasi, 'ttd' => true])->assertSessionHasNoErrors();

        $this->actingAs($auditor)->get("{$url}/ketidaksesuaian/cetak")->assertInertia(fn (Inertia $p) => $p
            ->where('ttd.bubuh', fn ($b) => collect($b)->map(fn ($x) => (array) $x)->toArray() === [$perbaikan => ['ktt' => true], $verifikasi => ['ketua' => true]])
            ->where('temuan.0.syaratTtd', ['perbaikan' => null, 'verifikasi' => null])
            ->where('temuan.0.lampiran.0.nama', 'daftar-hadir.pdf')
            ->where('temuan.0.lampiran.0.url', fn ($u) => str_ends_with($u, "/temuan/{$t->id}/tindak-lanjut/".$t->tindakLanjut()->value('id').'/berkas/0?lihat=1')));

        // Membatalkan menghapus tanda tangan beserta tanggalnya.
        $this->actingAs($auditi)->post("{$url}/ttd/bubuh", ['bagian' => $perbaikan, 'ttd' => false])->assertRedirect();
        $isi = $a->fresh()->ttd_formulir;
        $this->assertArrayNotHasKey($perbaikan, $isi['bubuh']);
        $this->assertArrayNotHasKey($perbaikan, $isi['tanggal'] ?? []);

        // Bagian lain tidak dapat "dibubuhkan"; audit perusahaan lain tidak terjangkau.
        $this->actingAs($auditor)->post("{$url}/ttd/bubuh", ['bagian' => 'kesesuaian', 'ttd' => true])->assertSessionHasErrors('bagian');
        $lain = $this->audit($this->sister);
        $this->actingAs($auditi)->post("/smkp/{$lain->id}/formulir/ttd/bubuh", ['bagian' => $perbaikan, 'ttd' => true])->assertNotFound();
    }

    public function test_tanda_tangan_digital_di_pengesahan_rencana_berita_acara_dan_laporan(): void
    {
        Storage::fake('local');
        $a = $this->audit();
        $a->update(['rencana' => ['pengesahan' => [
            'ktt' => ['nama' => 'KTT Uji'], 'pjo' => ['nama' => 'PJO Uji'], 'ketua' => ['nama' => 'Ketua Uji'],
        ]]]);
        $auditor = $this->auditor($this->induk);
        $auditi = $this->auditi($this->induk);

        // PJO (pihak auditi) dapat diunggah auditi; ketua tim hanya oleh auditor.
        $this->actingAs($auditi)->post("/smkp/{$a->id}/formulir/ttd", ['pihak' => 'ketua', 'berkas' => UploadedFile::fake()->image('x.png', 300, 100)])->assertForbidden();
        $this->actingAs($auditi)->post("/smkp/{$a->id}/formulir/ttd", ['pihak' => 'pjo', 'berkas' => UploadedFile::fake()->image('p.png', 300, 100)])->assertRedirect();
        $this->actingAs($auditor)->post("/smkp/{$a->id}/formulir/ttd", ['pihak' => 'ketua', 'berkas' => UploadedFile::fake()->image('k.png', 300, 100)])->assertRedirect();

        $this->actingAs($auditor)->get("/smkp/{$a->id}/rencana/cetak")->assertOk()->assertInertia(fn (Inertia $p) => $p
            ->where('pihak', fn ($t) => collect($t)->pluck('kunci')->all() === ['ktt', 'pjo', 'ketua'])
            ->where('ttd.gambar.pjo', fn ($g) => str_contains($g, "/formulir/ttd/pjo?v="))
            ->where('ttd.gambar.ketua', fn ($g) => str_contains($g, "/formulir/ttd/ketua?v="))
            ->where('ttd.gambar.ktt', null)->where('ttd.bisaGambar.ktt', true));
        foreach (["/smkp/{$a->id}/berita-acara", "/smkp/{$a->id}/laporan"] as $url) {
            $this->actingAs($auditi)->get($url)->assertOk()->assertInertia(fn (Inertia $p) => $p
                ->where('ttd.gambar.ketua', fn ($g) => is_string($g))
                ->where('ttd.bisaGambar', fn ($b) => collect($b)->filter()->keys()->all() === ['ktt', 'pjo']));
        }
        $this->actingAs($auditor)->get("/smkp/{$a->id}/formulir/ttd/pjo")->assertOk();
    }

    public function test_tahap1_menetapkan_jenis_perizinan_dan_alamat_untuk_lampiran(): void
    {
        $a = $this->audit();
        $this->induk->update(['izin_type' => 'IUP', 'address' => 'Alamat Induk']);
        $u = $this->auditor($this->induk);

        // Tanpa isian Tahap I: data perusahaan dipakai.
        $this->actingAs($u)->get("/smkp/{$a->id}/berita-acara")->assertInertia(fn (Inertia $p) => $p
            ->where('audit.perusahaan.izin', 'IUP')->where('audit.perusahaan.alamat', 'Alamat Induk'));

        $this->actingAs($u)->post("/smkp/{$a->id}/tahap-1", ['permulaan' => ['izin' => 'IUP Operasi Produksi', 'alamat' => "Jl. Tambang 1\nKalimantan Timur"]])
            ->assertSessionHasNoErrors();
        $this->assertSame('IUP Operasi Produksi', $a->fresh()->permulaan['izin']);

        $this->actingAs($u)->get("/smkp/{$a->id}/tahap-1")->assertInertia(fn (Inertia $p) => $p
            ->where('isian.permulaan.izin', 'IUP Operasi Produksi')->where('dataPerusahaan.izin', 'IUP'));
        foreach (["/smkp/{$a->id}/berita-acara", "/smkp/{$a->id}/laporan", "/smkp/{$a->id}/rencana/cetak"] as $url) {
            $this->actingAs($u)->get($url)->assertInertia(fn (Inertia $p) => $p
                ->where('audit.perusahaan.izin', 'IUP Operasi Produksi')->where('audit.perusahaan.alamat', "Jl. Tambang 1\nKalimantan Timur"));
        }
        $this->actingAs($u)->post("/smkp/{$a->id}/tahap-1", ['permulaan' => ['izin' => str_repeat('x', 151)]])->assertSessionHasErrors('permulaan.izin');
    }

    public function test_berita_acara_tahap1_mengikuti_format_resmi_se_2022(): void
    {
        $a = $this->audit();
        $this->induk->update(['address' => 'Jl. Kantor Pusat 1', 'location' => 'Site Tambang Sebuku']);
        $a->update(['rencana' => ['nomor' => 'JU-HSE-IV.059', 'tugas' => [
            ['nama' => 'Lead Uji', 'peran' => 'Lead Auditor', 'registrasi' => '160/AUD-SMKP/37.04/DBT/2025'],
            ['nama' => 'Auditor Uji', 'peran' => 'Auditor', 'registrasi' => '161/AUD-SMKP/37.04/DBT/2025'],
        ]], 'risiko' => ['present' => [['kegiatan' => 'Hauling', 'risiko' => 'Tabrakan unit', 'nilai' => '9'], ['kegiatan' => '', 'risiko' => '', 'nilai' => '']], 'future' => []]]);
        $u = $this->auditor($this->induk);

        $this->actingAs($u)->post("/smkp/{$a->id}/tahap-1", [
            'permulaan' => [
                'periode' => '1 Januari – 31 Desember 2025', 'sifat_kontak' => 'formal', 'tanggal_kelayakan' => '2026-10-02',
                'layak' => 'layak', 'tanggal_ba' => '2026-10-02', 'bukti_integritas' => '', 'form_rapat' => 'SILO/HSE/Saf./08/03/Form/08',
            ],
            'persiapan' => [
                'tren' => ['lokasi' => ['Jalan hauling', '  ', 'Workshop'], 'asing' => ['dibuang']],
                'peralatan' => [['jenis' => 'Alat angkut', 'pa' => '85 %', 'ma' => '90 %'], ['jenis' => '', 'pa' => '', 'ma' => '']],
                'kli' => [['indikator' => 'Inspeksi rutin', 'status' => '85 %']],
                'audit_lalu' => ['nilai' => '78,5', 'asing' => 'x'],
                'masukan' => [['sumber' => 'Masukan dari Manajemen', 'isi' => 'Lengkapi JSA'], ['sumber' => 'Masukan dari Manajemen', 'isi' => '']],
            ],
        ])->assertSessionHasNoErrors();

        $p = $a->fresh()->permulaan;
        $this->assertSame(['Jalan hauling', 'Workshop'], $p['persiapan']['tren']['lokasi']);
        $this->assertArrayNotHasKey('asing', $p['persiapan']['tren']);
        $this->assertSame([['jenis' => 'Alat angkut', 'pa' => '85 %', 'ma' => '90 %']], $p['persiapan']['peralatan']);
        $this->assertSame(['nilai' => '78,5', 'kritikal' => '', 'mayor' => '', 'minor' => '', 'pembina' => ''], $p['persiapan']['audit_lalu']);
        $this->assertSame([['sumber' => 'Masukan dari Manajemen', 'isi' => 'Lengkapi JSA']], $p['persiapan']['masukan']);

        $this->actingAs($u)->get("/smkp/{$a->id}/berita-acara")->assertOk()->assertInertia(fn (Inertia $pg) => $pg
            ->component('Print/Smkp/BeritaAcara')
            ->where('periode', '1 Januari – 31 Desember 2025')
            ->where('permulaan.layak', 'layak')->where('permulaan.sifat_kontak', 'formal')
            ->where('persiapan.kli.0.indikator', 'Inspeksi rutin')
            ->where('risiko.present', [['kegiatan' => 'Hauling', 'risiko' => 'Tabrakan unit', 'nilai' => '9']])
            // Bawaan: sertifikat teregistrasi tiap auditor; site office dari lokasi perusahaan.
            ->where('buktiIntegritas', 'Sertifikat yang teregistrasi Nomor Register: 160/AUD-SMKP/37.04/DBT/2025 (Lead Auditor) dan 161/AUD-SMKP/37.04/DBT/2025 (Auditor)')
            ->where('alamatSite', 'Site Tambang Sebuku')->where('nomorForm.rapat', 'SILO/HSE/Saf./08/03/Form/08')
            ->where('nomorForm.rencana', 'JU-HSE-IV.059')->where('nomorForm.kesesuaian', 'FRM/IU/OHSE/022')
            ->where('kelayakan.kerjasama', 'Kerjasama dari Auditi')
            // Perusahaan pertambangan: Ketua Tim Audit dan KTT saja.
            ->where('pihak', fn ($ph) => collect($ph)->pluck('kunci')->all() === ['ketua', 'ktt']));

        // Perusahaan jasa pertambangan: PJO di antara Ketua Tim Audit dan KTT.
        $j = $this->audit($this->pjp);
        $this->actingAs($this->auditor($this->pjp))->get("/smkp/{$j->id}/berita-acara")->assertOk()->assertInertia(fn (Inertia $pg) => $pg
            ->where('pihak', fn ($ph) => collect($ph)->pluck('kunci')->all() === ['ketua', 'pjo', 'ktt'])
            ->where('periode', fn ($v) => $v !== ''));

        // Nilai pilihan di luar daftar ditolak; data trend paling banyak 20 baris.
        $this->actingAs($u)->post("/smkp/{$a->id}/tahap-1", ['permulaan' => ['sifat_kontak' => 'lisan', 'layak' => 'mungkin']])
            ->assertSessionHasErrors(['permulaan.sifat_kontak', 'permulaan.layak']);
        $this->actingAs($u)->post("/smkp/{$a->id}/tahap-1", ['persiapan' => ['tren' => ['lokasi' => array_fill(0, 21, 'x')]]])
            ->assertSessionHasErrors('persiapan.tren.lokasi');
    }

    public function test_tahap1_mengusulkan_data_audit_internal_tahun_sebelumnya(): void
    {
        $a = $this->audit();
        $u = $this->auditor($this->induk);
        $this->actingAs($u)->get("/smkp/{$a->id}/tahap-1")->assertInertia(fn (Inertia $p) => $p->where('saranAuditLalu', null));

        $lalu = SmkpAudit::withoutGlobalScopes()->create(['company_id' => $this->induk->id, 'tahun' => 2025, 'status' => 'selesai', 'tahap' => 3]);
        $lalu->findings()->create(['kode_kriteria' => 'I.1', 'jenis' => 'mayor', 'uraian' => 'A', 'status' => 'Closed']);
        $lalu->findings()->create(['kode_kriteria' => 'I.2', 'jenis' => 'mayor', 'uraian' => 'B', 'status' => 'Open']);

        $this->actingAs($u)->get("/smkp/{$a->id}/tahap-1")->assertInertia(fn (Inertia $p) => $p
            ->where('saranAuditLalu.tahun', 2025)
            ->where('saranAuditLalu.isi.mayor', '1 dari 2 ketidaksesuaian selesai (Closed)')
            ->where('saranAuditLalu.isi.kritikal', 'Tidak ada ketidaksesuaian')
            ->where('saranAuditLalu.isi.nilai', ''));

        // Auditi tidak menerima usulan (hanya tim audit yang mengisi Tahap I).
        $this->actingAs($this->auditi($this->induk))->get("/smkp/{$a->id}/tahap-1")->assertInertia(fn (Inertia $p) => $p->where('saranAuditLalu', null));
    }

    public function test_laporan_audit_mengikuti_format_kepdirjen_185(): void
    {
        $a = $this->audit(null, ['I.1' => ['v' => 1], 'I.4' => ['v' => 0], 'III.3' => ['v' => 'N/A']]);
        $a->update([
            'tanggal_mulai' => '2026-10-05', 'tanggal_selesai' => '2026-10-23',
            'permulaan' => ['periode' => '1 Januari – 31 Desember 2025'],
            'kesesuaian' => [['elemen' => 'III', 'sub' => 'III.2', 'subsub' => 'III.2.1', 'keterangan' => 'KTT telah disahkan KaIT.']],
            'rencana' => ['tugas' => [
                ['nama' => 'Lead Uji', 'peran' => 'Lead Auditor', 'registrasi' => '160/AUD'],
                ['nama' => 'Auditor Satu', 'peran' => 'Auditor', 'registrasi' => '161/AUD'],
                ['nama' => 'Pemandu Uji', 'peran' => 'Pemandu', 'registrasi' => ''],
                ['nama' => 'Auditor Dua', 'peran' => 'Auditor', 'registrasi' => '162/AUD'],
            ]],
        ]);
        $u = $this->auditor($this->induk);
        $pt = $this->induk->name;

        // Isian laporan: baris kosong dibuang, kunci asing diabaikan.
        $this->actingAs($u)->post("/smkp/{$a->id}/laporan", [
            'status_perusahaan' => 'PMDN', 'wiup' => '3113733032014014', 'asing' => 'x',
            'pekerja' => [['jabatan' => 'Pengawas', 'jumlah' => '8'], ['jabatan' => '', 'jumlah' => '']],
            'area' => ['Area Front Penambangan', ' ', 'Area Workshop'],
            'tempat' => 'Padang', 'tanggal' => '2026-10-23',
        ])->assertRedirect("/smkp/{$a->id}/laporan/isian");
        $l = $a->fresh()->laporan;
        $this->assertSame([['jabatan' => 'Pengawas', 'jumlah' => '8']], $l['pekerja']);
        $this->assertSame(['Area Front Penambangan', 'Area Workshop'], $l['area']);
        $this->assertArrayNotHasKey('asing', $l);

        $this->actingAs($u)->get("/smkp/{$a->id}/laporan/isian")->assertOk()->assertInertia(fn (Inertia $p) => $p
            ->component('Smkp/Laporan')->where('isian.tempat', 'Padang')->where('otomatis.tim', 4)->where('otomatis.praktik', 1));

        $this->actingAs($u)->get("/smkp/{$a->id}/laporan")->assertOk()->assertInertia(fn (Inertia $p) => $p
            ->component('Print/Smkp/LaporanAudit')
            ->where('periode', '1 Januari – 31 Desember 2025')
            // Gambaran penerapan: uraian tingkat nilai rubrik dengan nama perusahaan; N/A tidak dapat diaplikasikan.
            ->where('penerapan.0.sub', fn ($sub) => collect($sub)->firstWhere('kode', 'I.4')['teks'] === "{$pt} belum melakukan komunikasi kebijakan."
                && str_starts_with(collect($sub)->firstWhere('kode', 'I.1')['teks'], "{$pt} telah melakukan tinjauan awal"))
            ->where('penerapan', fn ($el) => str_ends_with(collect(collect($el)->firstWhere('kode', 'III')['sub'])->firstWhere('kode', 'III.3')['teks'], "tidak dapat diaplikasikan di {$pt}."))
            ->where('praktik.0.kriteria', fn ($k) => str_starts_with($k, 'Sub Sub Elemen III.2.1 – '))
            // Penanda tangan: ketua & anggota (slot auditor2, auditor3); pemandu tercantum tanpa tanda tangan.
            ->where('tim', fn ($t) => collect($t)->pluck('kunci')->all() === ['ketua', 'auditor2', null, 'auditor3'])
            ->where('pihakPjo', [])->where('hitung', ['kritikal' => 0, 'mayor' => 0, 'minor' => 0]));

        // Perusahaan jasa: PJO turut menandatangani laporan.
        $j = $this->audit($this->pjp);
        $this->actingAs($this->auditor($this->pjp))->get("/smkp/{$j->id}/laporan")->assertOk()->assertInertia(fn (Inertia $p) => $p
            ->where('jasa', true)->where('pihakPjo.0.kunci', 'pjo'));

        // Auditi tidak dapat mengubah isian laporan maupun tanda tangan anggota tim audit.
        $auditi = $this->auditi($this->induk);
        $this->actingAs($auditi)->post("/smkp/{$a->id}/laporan", ['tempat' => 'X'])->assertForbidden();
        Storage::fake('local');
        $this->actingAs($auditi)->post("/smkp/{$a->id}/formulir/ttd", ['pihak' => 'auditor2', 'berkas' => UploadedFile::fake()->image('a.png', 300, 100)])->assertForbidden();
        $this->actingAs($u)->post("/smkp/{$a->id}/formulir/ttd", ['pihak' => 'auditor2', 'berkas' => UploadedFile::fake()->image('a.png', 300, 100)])->assertRedirect();
        $this->actingAs($u)->get("/smkp/{$a->id}/laporan")->assertInertia(fn (Inertia $p) => $p->where('ttd.gambar.auditor2', fn ($g) => is_string($g)));
    }

    public function test_rencana_menyimpan_isian_formulir_resmi(): void
    {
        $a = $this->audit();

        $this->actingAs($this->auditor($this->induk))->post("/smkp/{$a->id}/rencana", [
            'jenis_audit' => 'Audit Internal SMKP Mineral dan Batubara', 'alamat' => 'Indarung, Padang',
            'pengecualian' => 'Area pelabuhan', 'dokumentasi' => "Manual SMKP\nProsedur K3", 'distribusi' => 'Pimpinan perusahaan',
            'metode_sampel' => [
                'I.1' => ['metode' => 'Tinjauan dokumen', 'sampel' => 'Kebijakan 2026'],
                'IV.2' => ['metode' => '', 'sampel' => ''],          // kosong → dibuang
                'XX.9' => ['metode' => 'Wawancara', 'sampel' => '1'], // kode tidak dikenal → dibuang
            ],
        ])->assertSessionHasNoErrors();

        $r = $a->refresh()->rencana;
        $this->assertSame('Area pelabuhan', $r['pengecualian']);
        $this->assertSame(['I.1' => ['metode' => 'Tinjauan dokumen', 'sampel' => 'Kebijakan 2026']], $r['metode_sampel']);

        $this->get("/smkp/{$a->id}/rencana")->assertInertia(fn (Inertia $p) => $p
            ->where('isian.pengecualian', 'Area pelabuhan')->has('kriteriaDaftar', 7)->has('jenisAudit', 2));
    }

    /**
     * Kop formulir audit SMKP per perusahaan (porting Aurorasys). Bawaannya
     * tetap penomoran EQOHSEE (FRM/{prefiks}/OHSE/{urut}); kode yang diisi
     * administrator mengganti nomornya menjadi bentuk Aurorasys
     * {kode singkat}-{kode}.
     */
    public function test_kop_formulir_mengikuti_pengaturan_tiap_perusahaan(): void
    {
        $adm = $this->superadmin();
        $this->actingAs($adm)->put("/admin/companies/{$this->induk->id}", [
            'name' => $this->induk->name, 'kategori' => 'induk', 'code' => 'SPX', 'doc_no_prefix' => 'IU', 'doc_revisi' => 3,
            'kop_formulir' => [
                'formulir-kesesuaian' => ['kode' => 'K3-F-021', 'judul' => 'FORM KESESUAIAN AUDIT SMKP', 'jenis' => ''],
                'tidak-dikenal'       => ['kode' => 'X'],
                'laporan-energi'      => ['kode' => 'Y'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->induk->refresh();
        $this->assertSame(['formulir-kesesuaian' => ['judul' => 'FORM KESESUAIAN AUDIT SMKP', 'kode' => 'K3-F-021']], $this->induk->kop_formulir);

        $a = $this->audit();
        $this->actingAs($adm)->get("/smkp/{$a->id}/formulir/kesesuaian/cetak")->assertInertia(fn (Inertia $p) => $p
            ->where('dok.nomor', 'SPX-K3-F-021')->where('dok.judul', 'FORM KESESUAIAN AUDIT SMKP')->where('dok.revisi', '03'));

        // Formulir lain tetap memakai penomoran bawaan EQOHSEE.
        $this->actingAs($adm)->get("/smkp/{$a->id}/formulir/rekapitulasi/cetak")->assertInertia(fn (Inertia $p) => $p
            ->where('dok.nomor', 'FRM/IU/OHSE/012'));

        // Perusahaan lain tidak terpengaruh.
        $b = $this->audit($this->pjp);
        $this->actingAs($adm)->get("/smkp/{$b->id}/formulir/kesesuaian/cetak")->assertInertia(fn (Inertia $p) => $p
            ->where('dok.judul', 'FORMULIR KESESUAIAN AUDIT PENERAPAN SMKP'));

        // Formulir admin perusahaan membawa isian kop untuk tiap formulir SMKP.
        $this->actingAs($adm)->get("/admin/companies/{$this->induk->id}/edit")->assertInertia(fn (Inertia $p) => $p
            ->component('Admin/Perusahaan/Form')
            ->where('awal.kop_formulir.formulir-kesesuaian.kode', 'K3-F-021')
            ->where('awal.kop_formulir.berita-acara.kode', '')
            ->where('daftarFormulir.berita-acara.nomor', 'FRM/IU/OHSE/001')
            ->has('daftarFormulir', 10)
            ->has('opsi.kategori', 4));

        // Simpan tanpa kop_formulir tidak menghapus penyesuaian yang ada.
        $this->actingAs($adm)->put("/admin/companies/{$this->induk->id}", ['name' => $this->induk->name, 'doc_no_prefix' => 'IU'])
            ->assertRedirect();
        $this->assertSame('K3-F-021', $this->induk->refresh()->kop_formulir['formulir-kesesuaian']['kode']);
    }
}
