<?php

namespace Tests\Feature;

use App\Models\{Company, SmkpAudit, SmkpBukti, SmkpOfi, User};
use App\Support\{Berkas, Smkp, SmkpPeluang};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Bukti berkas per butir, dan peluang perbaikan atas butir sempurna.
 *
 * Dua penjagaan yang tidak menimbulkan galat bila lepas, dan keduanya
 * berakhir pada berkas yang diserahkan kepada Inspektur Tambang:
 * bukti audit perusahaan lain yang terbaca oleh siapa pun yang menebak
 * nomornya, dan lembar OFI yang menyatakan butir bernilai 40% sebagai
 * "sudah memenuhi seluruh kriteria".
 */
class SmkpBuktiOfiTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;
    private Company $b;
    private User $orangA;
    private User $adminA;
    private SmkpAudit $audit;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Berkas::TERTUTUP);

        $this->a = Company::create(['name' => 'PT Alpha']);
        $this->b = Company::create(['name' => 'PT Beta']);

        $this->orangA = User::factory()->create([
            'company_id' => $this->a->id, 'email_verified_at' => now(),
        ]);

        $this->adminA = User::factory()->create([
            'company_id' => $this->a->id, 'email_verified_at' => now(), 'is_admin' => true,
        ]);

        $this->audit = $this->auditMilik($this->a, 2026);
    }

    private function auditMilik(Company $c, int $tahun, array $hasil = []): SmkpAudit
    {
        return SmkpAudit::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'tahun' => $tahun, 'status' => 'berjalan', 'hasil' => $hasil,
        ]);
    }

    /** Kode butir pertama pada sebuah sub-elemen berincian. */
    private function subBerincian(): array
    {
        foreach (Smkp::elemen() as $e) {
            foreach ($e['sub'] as $s) {
                if (! empty($s['subsub'])) return [$e, $s];
            }
        }

        $this->fail('Tidak ada sub-elemen berincian pada acuan.');
    }

    /** Isi seluruh butir sebuah sub-elemen dengan nilai maksimum. */
    private function isiPenuh(SmkpAudit $audit, array $sub): void
    {
        $hasil = (array) ($audit->hasil ?? []);

        foreach (Smkp::butirSub($sub) as $b) {
            $hasil[$b['kode']] = ['v' => (int) $b['maks'], 'ket' => '', 'bukti' => ''];
        }

        $audit->update(['hasil' => $hasil]);
        $audit->refresh();
    }

    /* ═══════════ bukti berkas ═══════════ */

    #[Test]
    public function bukti_tersimpan_pada_disk_tertutup_bukan_disk_publik(): void
    {
        $kode = Smkp::butir()[0]['kode'];

        $this->actingAs($this->orangA)
            ->post("/smkp/{$this->audit->id}/bukti", [
                'kode'    => $kode,
                'catatan' => 'SOP-HSE-012 rev.3',
                'berkas'  => UploadedFile::fake()->create('sop-hse-012.pdf', 200, 'application/pdf'),
            ])->assertSessionHasNoErrors();

        $bukti = SmkpBukti::withoutGlobalScopes()->firstOrFail();

        Storage::disk(Berkas::TERTUTUP)->assertExists($bukti->file_path);
        $this->assertStringStartsWith("smkp/{$this->audit->id}/", $bukti->file_path);
        $this->assertSame($kode, $bukti->kode);
        $this->assertSame('SOP-HSE-012 rev.3', $bukti->catatan);
    }

    #[Test]
    public function berkas_bukti_lebih_dari_sepuluh_megabita_ditolak(): void
    {
        /* Batasnya 10 MB, lebih ketat daripada dokumen biasa. Satu audit
           menyentuh 349 butir; berkas 20 MB pada satu butir hampir
           selalu berarti seluruh bundel dokumen diunggah ke tempat yang
           tidak akan dicari siapa pun ketika bundel itu dibutuhkan. */
        $this->assertSame(10240, Berkas::MAKS_BUKTI_KB);

        $this->actingAs($this->orangA)
            ->post("/smkp/{$this->audit->id}/bukti", [
                'kode'   => Smkp::butir()[0]['kode'],
                'berkas' => UploadedFile::fake()->create('bundel.pdf', 10241, 'application/pdf'),
            ])->assertSessionHasErrors('berkas');

        $this->assertSame(0, SmkpBukti::withoutGlobalScopes()->count());

        /* Tepat di batas tetap diterima — batas yang menolak nilainya
           sendiri membuat pesan "paling besar 10 MB" berbohong. */
        $this->actingAs($this->orangA)
            ->post("/smkp/{$this->audit->id}/bukti", [
                'kode'   => Smkp::butir()[0]['kode'],
                'berkas' => UploadedFile::fake()->create('pas.pdf', 10240, 'application/pdf'),
            ])->assertSessionHasNoErrors();

        $this->assertSame(1, SmkpBukti::withoutGlobalScopes()->count());
    }

    #[Test]
    public function bukti_berakhiran_php_dan_svg_ditolak(): void
    {
        foreach ([['sisip.php', 10], ['logo.svg', 4]] as [$nama, $kb]) {
            $this->actingAs($this->orangA)
                ->post("/smkp/{$this->audit->id}/bukti", [
                    'kode'   => Smkp::butir()[0]['kode'],
                    'berkas' => UploadedFile::fake()->create($nama, $kb),
                ])->assertSessionHasErrors('berkas');
        }

        $this->assertSame(0, SmkpBukti::withoutGlobalScopes()->count());
    }

    #[Test]
    public function kode_butir_yang_tidak_dikenal_ditolak(): void
    {
        /* Kode datang dari peramban. Diterima apa adanya, bukti akan
           melekat pada butir yang tidak pernah digambar halaman mana
           pun — hilang tanpa terhapus. */
        $this->actingAs($this->orangA)
            ->post("/smkp/{$this->audit->id}/bukti", [
                'kode'   => 'XI.9.9',
                'berkas' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
            ])->assertSessionHasErrors('kode');

        $this->assertSame(0, SmkpBukti::withoutGlobalScopes()->count());
    }

    #[Test]
    public function bukti_audit_perusahaan_lain_tidak_dapat_disajikan(): void
    {
        /* Rute penyaji berkas mengikat BARIS BUKTI saja — tidak ada
           {smkp} di alamatnya, jadi pengikatan audit yang berlingkup
           perusahaan tidak ikut menjaga. Satu-satunya yang menjaga
           adalah BerindukPerusahaan pada SmkpBukti. */
        $auditB = $this->auditMilik($this->b, 2026);

        $punyaB = SmkpBukti::withoutGlobalScopes()->create([
            'audit_id' => $auditB->id, 'kode' => Smkp::butir()[0]['kode'],
            'file_path' => 'smkp/'.$auditB->id.'/rahasia.pdf',
            'file_name' => 'rahasia.pdf', 'file_size' => 10,
        ]);

        Storage::disk(Berkas::TERTUTUP)->put($punyaB->file_path, 'isi rahasia');

        $this->actingAs($this->orangA)
            ->post("/smkp/{$this->audit->id}/bukti", [
                'kode'   => Smkp::butir()[0]['kode'],
                'berkas' => UploadedFile::fake()->create('punya-alpha.pdf', 50, 'application/pdf'),
            ])->assertSessionHasNoErrors();

        $punyaA = SmkpBukti::withoutGlobalScopes()->where('audit_id', $this->audit->id)->firstOrFail();

        $this->actingAs($this->orangA)->get("/berkas/smb/{$punyaA->id}")->assertOk();
        $this->actingAs($this->orangA)->get("/berkas/smb/{$punyaB->id}")->assertNotFound();
    }

    #[Test]
    public function bukti_milik_audit_lain_tidak_dapat_dihapus_lewat_audit_ini(): void
    {
        /* Batas perusahaan tidak menutup ini: kedua audit milik PT Alpha,
           hanya tahunnya berbeda. Yang menutupnya adalah pemeriksaan
           bahwa anak yang diikat rute memang milik induk yang diikat
           rute. */
        $lain = $this->auditMilik($this->a, 2025);

        $bukti = SmkpBukti::withoutGlobalScopes()->create([
            'audit_id' => $lain->id, 'kode' => Smkp::butir()[0]['kode'],
            'file_path' => 'smkp/'.$lain->id.'/x.pdf', 'file_name' => 'x.pdf', 'file_size' => 10,
        ]);

        $this->actingAs($this->adminA)
            ->delete("/smkp/{$this->audit->id}/bukti/{$bukti->id}")
            ->assertNotFound();

        $this->assertNotNull(SmkpBukti::withoutGlobalScopes()->find($bukti->id));
    }

    #[Test]
    public function menghapus_bukti_ikut_membuang_berkasnya(): void
    {
        $this->actingAs($this->orangA)
            ->post("/smkp/{$this->audit->id}/bukti", [
                'kode'   => Smkp::butir()[0]['kode'],
                'berkas' => UploadedFile::fake()->create('a.pdf', 20, 'application/pdf'),
            ])->assertSessionHasNoErrors();

        $bukti = SmkpBukti::withoutGlobalScopes()->firstOrFail();
        Storage::disk(Berkas::TERTUTUP)->assertExists($bukti->file_path);

        $this->actingAs($this->adminA)
            ->delete("/smkp/{$this->audit->id}/bukti/{$bukti->id}")
            ->assertRedirect();

        $this->assertSame(0, SmkpBukti::withoutGlobalScopes()->count());

        /* Baris yang hilang tanpa berkasnya meninggalkan dokumen audit
           di disk selamanya — tidak terlihat di layar mana pun. */
        Storage::disk(Berkas::TERTUTUP)->assertMissing($bukti->file_path);
    }

    #[Test]
    public function pengguna_biasa_tidak_dapat_menghapus_bukti(): void
    {
        $bukti = SmkpBukti::withoutGlobalScopes()->create([
            'audit_id' => $this->audit->id, 'kode' => Smkp::butir()[0]['kode'],
            'file_path' => 'smkp/x.pdf', 'file_name' => 'x.pdf', 'file_size' => 10,
        ]);

        $this->actingAs($this->orangA)
            ->delete("/smkp/{$this->audit->id}/bukti/{$bukti->id}")
            ->assertForbidden();

        $this->assertNotNull(SmkpBukti::withoutGlobalScopes()->find($bukti->id));
    }

    /* ═══════════ peluang perbaikan ═══════════ */

    #[Test]
    public function hanya_butir_bercapaian_penuh_yang_berhak_memperoleh_ofi(): void
    {
        [, $sub] = $this->subBerincian();
        $butir   = Smkp::butirSub($sub);

        /* Satu butir bernilai penuh, sisanya nol: rinciannya berhak,
           sub-elemennya tidak. */
        $hasil = [];
        foreach ($butir as $i => $b) {
            $hasil[$b['kode']] = ['v' => $i === 0 ? (int) $b['maks'] : 0];
        }
        $this->audit->update(['hasil' => $hasil]);

        $berhak = array_column(SmkpPeluang::berhak($this->audit->fresh()), 'kode');

        $this->assertContains($butir[0]['kode'], $berhak);
        $this->assertNotContains($sub['kode'], $berhak, 'Sub-elemen belum sempurna tetapi ikut ditawarkan.');
        $this->assertNotContains($butir[1]['kode'], $berhak);
    }

    #[Test]
    public function sub_elemen_yang_seluruh_rinciannya_penuh_ikut_berhak(): void
    {
        [, $sub] = $this->subBerincian();
        $this->isiPenuh($this->audit, $sub);

        $berhak = array_column(SmkpPeluang::berhak($this->audit), 'kode');

        $this->assertContains($sub['kode'], $berhak);
        foreach (Smkp::butirSub($sub) as $b) $this->assertContains($b['kode'], $berhak);
    }

    #[Test]
    public function sub_elemen_yang_seluruhnya_na_tidak_berhak(): void
    {
        /* Capaiannya nol karena tidak ada yang dinilai, bukan karena
           buruk — tetapi ia juga bukan "sudah sempurna". Menawarkan
           peluang perbaikan atas sesuatu yang tidak berlaku bagi
           perusahaan itu hanya membuang waktu pembacanya. */
        [, $sub] = $this->subBerincian();

        $hasil = [];
        foreach (Smkp::butirSub($sub) as $b) $hasil[$b['kode']] = ['v' => Smkp::NA];
        $this->audit->update(['hasil' => $hasil]);

        $berhak = array_column(SmkpPeluang::berhak($this->audit->fresh()), 'kode');

        $this->assertNotContains($sub['kode'], $berhak);
    }

    #[Test]
    public function ofi_atas_butir_yang_belum_sempurna_ditolak_server(): void
    {
        /* Formulirnya memang hanya menawarkan butir yang berhak, tetapi
           penjagaan yang hanya ada di peramban dilewati satu permintaan
           yang disusun tangan — dan lembar OFI yang memuat butir
           bernilai 40% menyatakan kebalikan dari keadaan sebenarnya,
           ditandatangani ketua tim, lalu diserahkan ke Inspektur
           Tambang. */
        [, $sub] = $this->subBerincian();

        $this->actingAs($this->orangA)
            ->post("/smkp/{$this->audit->id}/ofi", [
                'kode'   => $sub['kode'],
                'uraian' => 'Peluang yang dikarang atas butir yang belum sempurna.',
            ])->assertSessionHasErrors('kode');

        $this->assertSame(0, SmkpOfi::withoutGlobalScopes()->count());
    }

    #[Test]
    public function ofi_tersimpan_dan_menimpa_bukan_menggandakan(): void
    {
        [, $sub] = $this->subBerincian();
        $this->isiPenuh($this->audit, $sub);

        $kirim = fn (string $uraian) => $this->actingAs($this->orangA)
            ->post("/smkp/{$this->audit->id}/ofi", [
                'kode' => $sub['kode'], 'uraian' => $uraian, 'status' => 'terbuka',
            ]);

        $kirim('Sudah memenuhi seluruhnya; pertimbangkan otomasi pemantauan.')->assertSessionHasNoErrors();
        $kirim('Versi kedua dari uraian yang sama.')->assertSessionHasNoErrors();

        $this->assertSame(1, SmkpOfi::withoutGlobalScopes()->count());

        $ofi = SmkpOfi::withoutGlobalScopes()->first();
        $this->assertSame('Versi kedua dari uraian yang sama.', $ofi->uraian);
        $this->assertSame('sub', $ofi->lingkup);
    }

    #[Test]
    public function lembar_ofi_tetap_menyebut_butir_yang_berhenti_sempurna(): void
    {
        [, $sub] = $this->subBerincian();
        $this->isiPenuh($this->audit, $sub);

        $this->actingAs($this->orangA)->post("/smkp/{$this->audit->id}/ofi", [
            'kode' => $sub['kode'], 'uraian' => 'Peluang atas butir sempurna.',
        ])->assertSessionHasNoErrors();

        /* Nilainya diturunkan sesudah OFI dicatat. Barisnya harus tetap
           ada dan DITANDAI: lembar yang menyusut sendiri antar cetakan
           membuat penyusunnya mengira ada yang hilang. */
        $butir = Smkp::butirSub($sub)[0];
        $hasil = (array) $this->audit->fresh()->hasil;
        $hasil[$butir['kode']] = ['v' => 0];
        $this->audit->update(['hasil' => $hasil]);

        $props = $this->actingAs($this->orangA)
            ->get("/smkp/{$this->audit->id}/ofi")
            ->assertOk()->viewData('page')['props'];

        $this->assertCount(1, $props['baris']);
        $this->assertTrue($props['baris'][0]['gugur'], 'Butir yang berhenti sempurna tidak ditandai.');
        $this->assertNotContains($sub['kode'], array_column($props['berhak'], 'kode'));
    }

    #[Test]
    public function ofi_audit_lain_tidak_dapat_dihapus_lewat_audit_ini(): void
    {
        $lain = $this->auditMilik($this->a, 2025);

        $ofi = SmkpOfi::withoutGlobalScopes()->create([
            'audit_id' => $lain->id, 'kode' => 'I.1', 'uraian' => 'x',
        ]);

        $this->actingAs($this->adminA)
            ->delete("/smkp/{$this->audit->id}/ofi/{$ofi->id}")
            ->assertNotFound();

        $this->assertNotNull(SmkpOfi::withoutGlobalScopes()->find($ofi->id));
    }

    #[Test]
    public function ofi_perusahaan_lain_tidak_terbaca(): void
    {
        $auditB = $this->auditMilik($this->b, 2026);

        SmkpOfi::withoutGlobalScopes()->create([
            'audit_id' => $auditB->id, 'kode' => 'I.1', 'uraian' => 'milik beta',
        ]);

        $this->actingAs($this->orangA);

        $this->assertSame(0, SmkpOfi::count());
        $this->get("/smkp/{$auditB->id}/ofi")->assertNotFound();
    }

    #[Test]
    public function ekspor_ofi_berupa_csv(): void
    {
        [, $sub] = $this->subBerincian();
        $this->isiPenuh($this->audit, $sub);

        $this->actingAs($this->orangA)->post("/smkp/{$this->audit->id}/ofi", [
            'kode' => $sub['kode'], 'uraian' => 'Peluang atas butir sempurna.',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->orangA)
            ->get("/smkp/{$this->audit->id}/ofi/ekspor")
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($this->orangA)
            ->get("/smkp/{$this->audit->id}/ofi/cetak")
            ->assertOk();
    }
}
