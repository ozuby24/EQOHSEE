<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Pjp\{Evaluasi, Laporan, Pjp, SmkpItem, SmkpJawaban, SmkpKategori};
use App\Models\User;
use App\Support\Berkas;
use App\Support\Pjp\DaftarPeriksaSmkp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Penjagaan modul PJP: siapa boleh masuk, siapa boleh melihat apa.
 *
 * Kit asalnya berdiri tanpa satu pun lapis autentikasi dan tanpa batas
 * perusahaan — seluruh berkas mitra terbuka bagi siapa pun yang tahu
 * alamatnya, dan dokumen laporannya tergeletak di disk publik. Ketiga
 * kelemahan itu tidak menimbulkan galat apa pun; yang menjaganya
 * sekarang adalah berkas ini, bukan ingatan.
 */
class PjpAksiTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;
    private Company $b;
    private User $orangA;
    private User $adminA;

    protected function setUp(): void
    {
        parent::setUp();

        DaftarPeriksaSmkp::pasang();
        Storage::fake(Berkas::TERTUTUP);

        $this->a = Company::create(['name' => 'PT Alpha']);
        $this->b = Company::create(['name' => 'PT Beta']);

        $this->orangA = User::factory()->create([
            'company_id' => $this->a->id, 'email_verified_at' => now(),
        ]);

        /* Penghapusan berjaga `can:admin`, jadi uji yang memang menguji
           penghapusan memakai akun ini. Yang menguji batas perusahaan
           tetap memakai $orangA — batasnya harus berlaku bagi keduanya. */
        $this->adminA = User::factory()->create([
            'company_id' => $this->a->id, 'email_verified_at' => now(), 'is_admin' => true,
        ]);
    }

    private function mitra(Company $c, array $ganti = []): Pjp
    {
        return Pjp::withoutGlobalScopes()->create($ganti + [
            'company_id' => $c->id,
            'nama_perusahaan' => 'PT Mitra '.$c->name,
        ]);
    }

    private function laporan(Pjp $pjp): Laporan
    {
        return Laporan::withoutGlobalScopes()->create([
            'pjp_id' => $pjp->id, 'jenis' => 'laporan_bulanan',
            'file_path' => 'pjp/'.$pjp->id.'/x.pdf', 'file_name' => 'x.pdf', 'file_size' => 10,
        ]);
    }

    /* ═══════════ pintu masuk ═══════════ */

    #[Test]
    public function seluruh_halaman_pjp_menuntut_login(): void
    {
        $pjp = $this->mitra($this->a);

        foreach ([
            '/pjp', '/pjp/daftar', '/pjp/baru', '/pjp/csv', '/pjp/cetak',
            "/pjp/{$pjp->id}", "/pjp/{$pjp->id}/ubah", "/pjp/{$pjp->id}/checklist",
        ] as $jalur) {
            $this->get($jalur)->assertRedirect('/login');
        }

        $this->post('/pjp/baru', ['nama_perusahaan' => 'PT Selundup', 'status' => 'aktif'])
            ->assertRedirect('/login');

        $this->assertDatabaseMissing('pjp_perusahaan', ['nama_perusahaan' => 'PT Selundup']);
    }

    #[Test]
    public function halaman_utama_terbuka_bagi_yang_sudah_masuk(): void
    {
        $this->actingAs($this->orangA);

        $this->get('/pjp')->assertOk();
        $this->get('/pjp/daftar')->assertOk();
        $this->get('/pjp/baru')->assertOk();
        $this->get('/pjp/cetak')->assertOk();
        $this->get('/pjp/csv')->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    /* ═══════════ batas perusahaan ═══════════ */

    #[Test]
    public function daftar_hanya_memuat_mitra_perusahaan_sendiri(): void
    {
        $milikA = $this->mitra($this->a, ['nama_perusahaan' => 'PT Punya Alpha']);
        $milikB = $this->mitra($this->b, ['nama_perusahaan' => 'PT Punya Beta']);

        $this->actingAs($this->orangA);

        $this->get('/pjp/daftar')
            ->assertSee('PT Punya Alpha')
            ->assertDontSee('PT Punya Beta');

        $this->assertSame([$milikA->id], Pjp::pluck('id')->all());
        $this->assertNotContains($milikB->id, Pjp::pluck('id')->all());
    }

    #[Test]
    public function berkas_mitra_perusahaan_lain_tidak_dapat_dibuka(): void
    {
        $milikB = $this->mitra($this->b);

        $this->actingAs($this->orangA);

        $this->get("/pjp/{$milikB->id}")->assertNotFound();
        $this->get("/pjp/{$milikB->id}/checklist")->assertNotFound();
        $this->put("/pjp/{$milikB->id}", ['nama_perusahaan' => 'Dibajak', 'status' => 'aktif'])
            ->assertNotFound();
        $this->delete("/pjp/{$milikB->id}")->assertNotFound();

        $this->assertSame('PT Mitra PT Beta', $milikB->fresh()->nama_perusahaan);
    }

    #[Test]
    public function dokumen_mitra_perusahaan_lain_tidak_dapat_dihapus(): void
    {
        /* Rutenya mengikat anaknya langsung. Tanpa BerindukPerusahaan
           pada Laporan, pengikatan itu tidak pernah menyentuh mitranya
           sama sekali — dan dokumen laporan perusahaan lain dapat
           dihapus oleh siapa pun yang menebak nomornya. */
        $milikB   = $this->mitra($this->b);
        $laporanB = $this->laporan($milikB);

        $this->actingAs($this->orangA);

        $this->delete("/pjp/{$milikB->id}/laporan/{$laporanB->id}")->assertNotFound();

        $this->assertNotNull(Laporan::withoutGlobalScopes()->find($laporanB->id));
    }

    #[Test]
    public function jawaban_daftar_periksa_mitra_lain_tidak_ikut_terbaca(): void
    {
        $milikB = $this->mitra($this->b);
        $butir  = SmkpItem::orderBy('id')->first();

        SmkpJawaban::withoutGlobalScopes()->create([
            'pjp_id' => $milikB->id, 'item_id' => $butir->id, 'nilai' => '3',
        ]);

        $this->actingAs($this->orangA);

        $this->assertSame(0, SmkpJawaban::count());
    }

    #[Test]
    public function dokumen_mitra_perusahaan_lain_tidak_dapat_disajikan(): void
    {
        /* Rute penyaji berkas mengikat LAPORANNYA saja — tidak ada
           {pjp} di alamatnya, jadi pengikatan Pjp yang berlingkup
           perusahaan tidak ikut menjaga di sini. Satu-satunya yang
           menjaga adalah BerindukPerusahaan pada Laporan, dan tanpa itu
           seluruh dokumen mitra perusahaan lain terbaca oleh siapa pun
           yang menebak nomornya. */
        $milikA = $this->mitra($this->a);
        $milikB = $this->mitra($this->b);

        $this->actingAs($this->orangA);

        $this->post("/pjp/{$milikA->id}/laporan", [
            'jenis'  => 'laporan_bulanan',
            'berkas' => UploadedFile::fake()->create('punya-alpha.pdf', 50, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $punyaA = Laporan::withoutGlobalScopes()->where('pjp_id', $milikA->id)->firstOrFail();

        $punyaB = Laporan::withoutGlobalScopes()->create([
            'pjp_id' => $milikB->id, 'jenis' => 'laporan_bulanan',
            'file_path' => 'pjp/'.$milikB->id.'/rahasia.pdf',
            'file_name' => 'rahasia.pdf', 'file_size' => 10,
        ]);

        Storage::disk(Berkas::TERTUTUP)->put($punyaB->file_path, 'isi rahasia');

        $this->get("/berkas/pjl/{$punyaA->id}")->assertOk();
        $this->get("/berkas/pjl/{$punyaB->id}")->assertNotFound();
        $this->get("/berkas/pjl/{$punyaB->id}/unduh")->assertNotFound();
    }

    /* ═══════════ anak milik induk yang benar ═══════════ */

    #[Test]
    public function dokumen_milik_mitra_lain_dalam_perusahaan_yang_sama_ditolak(): void
    {
        /* Batas perusahaan tidak menutup ini: keduanya milik PT Alpha.
           Yang menutupnya adalah pemeriksaan bahwa anak yang diikat rute
           memang milik induk yang diikat rute. */
        $satu = $this->mitra($this->a, ['nama_perusahaan' => 'PT Satu']);
        $dua  = $this->mitra($this->a, ['nama_perusahaan' => 'PT Dua']);

        $laporanDua = $this->laporan($dua);

        $evaluasiDua = Evaluasi::withoutGlobalScopes()->create([
            'pjp_id' => $dua->id, 'tahun' => 2026, 'semester' => 1,
            'skor_teknis' => 80, 'skor_keselamatan_kesehatan' => 80, 'skor_lingkungan' => 80,
        ]);

        /* Sengaja sebagai administrator: penghapusan memang haknya, jadi
           yang tersisa sebagai penjaga hanyalah pemeriksaan induk-anak.
           Diuji sebagai pengguna biasa, `can:admin` akan menolak lebih
           dahulu dan pemeriksaan yang dimaksud tidak pernah dijalankan. */
        $this->actingAs($this->adminA);

        $this->delete("/pjp/{$satu->id}/laporan/{$laporanDua->id}")->assertNotFound();
        $this->patch("/pjp/{$satu->id}/laporan/{$laporanDua->id}", ['kesesuaian_isi' => 'sesuai'])
            ->assertNotFound();
        $this->delete("/pjp/{$satu->id}/evaluasi/{$evaluasiDua->id}")->assertNotFound();

        $this->assertNotNull(Laporan::withoutGlobalScopes()->find($laporanDua->id));
        $this->assertNull(Laporan::withoutGlobalScopes()->find($laporanDua->id)->kesesuaian_isi);
        $this->assertNotNull(Evaluasi::withoutGlobalScopes()->find($evaluasiDua->id));
    }

    #[Test]
    public function pengguna_biasa_tidak_dapat_menghapus_apa_pun(): void
    {
        /* Layarnya menyembunyikan tombol hapus dari yang bukan admin,
           dan penjagaan yang hanya ada di peramban dilewati satu
           permintaan yang disusun tangan. Yang hilang lewat celah itu
           bukan data sepele: berkas pemantauan mitra beserta jawaban
           daftar periksa yang disusun berbulan-bulan, dan dokumen yang
           dapat diminta Inspektur Tambang. */
        $pjp      = $this->mitra($this->a);
        $laporan  = $this->laporan($pjp);

        $evaluasi = Evaluasi::withoutGlobalScopes()->create([
            'pjp_id' => $pjp->id, 'tahun' => 2026, 'semester' => 1,
            'skor_teknis' => 80, 'skor_keselamatan_kesehatan' => 80, 'skor_lingkungan' => 80,
        ]);

        $this->actingAs($this->orangA);

        $this->delete("/pjp/{$pjp->id}/laporan/{$laporan->id}")->assertForbidden();
        $this->delete("/pjp/{$pjp->id}/evaluasi/{$evaluasi->id}")->assertForbidden();
        $this->delete("/pjp/{$pjp->id}")->assertForbidden();

        $this->assertSame(1, Pjp::withoutGlobalScopes()->count());
        $this->assertSame(1, Laporan::withoutGlobalScopes()->count());
        $this->assertSame(1, Evaluasi::withoutGlobalScopes()->count());
    }

    /* ═══════════ unggahan ═══════════ */

    #[Test]
    public function dokumen_tersimpan_pada_disk_tertutup_bukan_disk_publik(): void
    {
        $pjp = $this->mitra($this->a);

        $this->actingAs($this->orangA);

        $this->post("/pjp/{$pjp->id}/laporan", [
            'jenis'  => 'laporan_bulanan',
            'berkas' => UploadedFile::fake()->create('laporan-agustus.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $laporan = Laporan::withoutGlobalScopes()->firstOrFail();

        Storage::disk(Berkas::TERTUTUP)->assertExists($laporan->file_path);
        $this->assertStringStartsWith("pjp/{$pjp->id}/", $laporan->file_path);
        $this->assertSame('laporan-agustus.pdf', $laporan->file_name);
    }

    #[Test]
    public function berkas_berakhiran_php_ditolak(): void
    {
        $pjp = $this->mitra($this->a);

        $this->actingAs($this->orangA);

        $this->post("/pjp/{$pjp->id}/laporan", [
            'jenis'  => 'laporan_bulanan',
            'berkas' => UploadedFile::fake()->create('sisip.php', 10),
        ])->assertSessionHasErrors('berkas');

        $this->assertSame(0, Laporan::withoutGlobalScopes()->count());
    }

    #[Test]
    public function berkas_svg_ditolak(): void
    {
        /* SVG adalah XML yang boleh memuat <script>, dan berkas ini
           disajikan dari domain yang sama dengan aplikasinya — skripnya
           berjalan di dalam asal yang sama, dengan akses ke kuki sesi
           siapa pun yang membukanya. Yang menolaknya di sini hanya
           aturan validasi: daftar akhiran terlarang pada Berkas tidak
           menyebut svg, sebab ia menjaga hal lain.

           Dokumen berkala mitra adalah tempat yang nyaman untuk
           menaruhnya: diunggah pihak luar, dibuka orang dalam. */
        $pjp = $this->mitra($this->a);

        $this->actingAs($this->orangA);

        $this->post("/pjp/{$pjp->id}/laporan", [
            'jenis'  => 'laporan_bulanan',
            'berkas' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'),
        ])->assertSessionHasErrors('berkas');

        $this->assertSame(0, Laporan::withoutGlobalScopes()->count());
    }

    #[Test]
    public function laporan_triwulan_ditolak_di_luar_bulan_yang_dibuka(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-08 03:00:00', 'UTC'));

        $pjp = $this->mitra($this->a);
        $this->actingAs($this->orangA);

        $this->post("/pjp/{$pjp->id}/laporan", [
            'jenis'  => 'laporan_triwulan',
            'berkas' => UploadedFile::fake()->create('tw.pdf', 100, 'application/pdf'),
        ])->assertSessionHasErrors('berkas');

        $this->assertSame(0, Laporan::withoutGlobalScopes()->count());

        Carbon::setTestNow();
    }

    #[Test]
    public function laporan_triwulan_diterima_saat_bulannya_dibuka(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-05 03:00:00', 'UTC'));

        $pjp = $this->mitra($this->a);
        $this->actingAs($this->orangA);

        $this->post("/pjp/{$pjp->id}/laporan", [
            'jenis'  => 'laporan_triwulan',
            'berkas' => UploadedFile::fake()->create('tw.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Laporan::withoutGlobalScopes()->count());

        Carbon::setTestNow();
    }

    /* ═══════════ daftar periksa ═══════════ */

    #[Test]
    public function menyimpan_daftar_periksa_menimpa_bukan_menggandakan(): void
    {
        $pjp   = $this->mitra($this->a);
        $butir = SmkpItem::orderBy('id')->first();

        $this->actingAs($this->orangA);

        $kirim = fn (string $nilai) => $this->post("/pjp/{$pjp->id}/checklist", [
            'jawaban' => [['item_id' => $butir->id, 'nilai' => $nilai, 'jawaban' => 'ya']],
        ]);

        $kirim('1')->assertSessionHasNoErrors();
        $kirim('3')->assertSessionHasNoErrors();

        $this->assertSame(1, SmkpJawaban::withoutGlobalScopes()->count());
        $this->assertSame('3', SmkpJawaban::withoutGlobalScopes()->first()->nilai);
    }

    #[Test]
    public function nilai_di_luar_daftar_ditolak(): void
    {
        $pjp   = $this->mitra($this->a);
        $butir = SmkpItem::orderBy('id')->first();

        $this->actingAs($this->orangA);

        $this->post("/pjp/{$pjp->id}/checklist", [
            'jawaban' => [['item_id' => $butir->id, 'nilai' => '9']],
        ])->assertSessionHasErrors('jawaban.0.nilai');

        $this->assertSame(0, SmkpJawaban::withoutGlobalScopes()->count());
    }

    /* ═══════════ penghapusan ═══════════ */

    #[Test]
    public function menghapus_mitra_ikut_membuang_berkas_dan_seluruh_anaknya(): void
    {
        $pjp = $this->mitra($this->a);

        $this->actingAs($this->orangA);

        $this->post("/pjp/{$pjp->id}/laporan", [
            'jenis'  => 'laporan_bulanan',
            'berkas' => UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $jalur = Laporan::withoutGlobalScopes()->firstOrFail()->file_path;
        Storage::disk(Berkas::TERTUTUP)->assertExists($jalur);

        $this->actingAs($this->adminA);

        Evaluasi::withoutGlobalScopes()->create([
            'pjp_id' => $pjp->id, 'tahun' => 2026, 'semester' => 1,
            'skor_teknis' => 70, 'skor_keselamatan_kesehatan' => 70, 'skor_lingkungan' => 70,
        ]);

        $this->delete("/pjp/{$pjp->id}")->assertRedirect('/pjp/daftar');

        $this->assertSame(0, Pjp::withoutGlobalScopes()->count());
        $this->assertSame(0, Laporan::withoutGlobalScopes()->count());
        $this->assertSame(0, Evaluasi::withoutGlobalScopes()->count());

        /* Berkasnya ikut dibuang. Baris yang hilang tanpa berkasnya
           meninggalkan dokumen mitra di disk selamanya — tidak terlihat
           di layar mana pun, dan tetap dapat dibaca siapa pun yang
           mengakses disknya. */
        Storage::disk(Berkas::TERTUTUP)->assertMissing($jalur);
    }

    /* ═══════════ data acuan ═══════════ */

    #[Test]
    public function memasang_ulang_daftar_periksa_tidak_menggandakan_dan_tidak_menghapus_jawaban(): void
    {
        $pjp   = $this->mitra($this->a);
        $butir = SmkpItem::orderBy('id')->first();

        SmkpJawaban::withoutGlobalScopes()->create([
            'pjp_id' => $pjp->id, 'item_id' => $butir->id, 'nilai' => '3',
        ]);

        DaftarPeriksaSmkp::pasang();
        DaftarPeriksaSmkp::pasang();

        $this->assertSame(17, SmkpKategori::count());
        $this->assertSame(126, SmkpItem::count());

        /* Yang paling mahal bila salah: jawaban menunjuk butirnya lewat
           kunci asing yang cascade on delete. Penyemai yang mengosongkan
           tabel butir lebih dahulu akan menghapus pekerjaan berbulan-
           bulan tanpa satu galat pun. */
        $this->assertSame(1, SmkpJawaban::withoutGlobalScopes()->count());
        $this->assertSame($butir->id, SmkpJawaban::withoutGlobalScopes()->first()->item_id);
    }

    #[Test]
    public function bobot_kategori_berbobot_berjumlah_seratus_tujuh_puluh_delapan(): void
    {
        $this->assertSame(178, (int) SmkpKategori::where('kode', '!=', SmkpKategori::LEGALITAS)->sum('bobot'));
        $this->assertSame(4, (int) SmkpKategori::where('kode', SmkpKategori::LEGALITAS)->sum('bobot'));
    }
}
