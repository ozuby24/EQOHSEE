<?php

namespace Tests\Feature;

use App\Models\{Company, Document, GudangBarang, GudangLokasi, HazardReport,
                Inspection, InspectionItem, Signatory, User};
use App\Support\Berkas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Berkas unggahan yang tidak boleh terbaca tanpa login.
 *
 * Yang diuji di sini bukan bahwa rutenya berjalan melainkan bahwa jalan
 * PINTASNYA sudah tidak ada. Sebelum perbaikan ini pintu depannya memang
 * dijaga — DocumentController::unduh memeriksa hak akses, dan
 * pemeriksaannya benar — tetapi berkasnya sekaligus tergeletak di
 * storage/app/public, yang lewat storage:link dilayani Nginx sendiri.
 * Penjagaan seperti itu tidak perlu ditembus; cukup tidak dilewati.
 *
 * Karena itu tiap unggahan diperiksa DI MANA ia mendarat, bukan hanya
 * apakah rutenya menjawab benar.
 */
class BerkasTertutupTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $pengguna;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->c = Company::create(['name' => 'PT Uji Berkas']);
        $this->pengguna = User::factory()->create([
            'company_id' => $this->c->id,
            'email_verified_at' => now(),
        ]);
    }

    private function gambar(string $nama = 'foto.jpg'): UploadedFile
    {
        return UploadedFile::fake()->image($nama, 40, 40);
    }

    /* ───────── tempat mendarat ───────── */

    public function test_dokumen_mendarat_di_disk_tertutup_bukan_publik(): void
    {
        $jalur = Berkas::simpan(UploadedFile::fake()->create('sop.pdf', 8, 'application/pdf'), 'dokumen');

        $this->assertNotNull($jalur);
        Storage::disk('local')->assertExists($jalur);
        Storage::disk('public')->assertMissing($jalur);
    }

    public function test_tanda_tangan_mendarat_di_disk_tertutup(): void
    {
        $jalur = Berkas::simpan($this->gambar('ttd.png'), 'signatures');

        Storage::disk('local')->assertExists($jalur);
        Storage::disk('public')->assertMissing($jalur);
    }

    public function test_foto_bahaya_mendarat_di_disk_tertutup(): void
    {
        $jalur = Berkas::simpanBanyak([$this->gambar(), $this->gambar()], 'hazard');

        $this->assertCount(2, $jalur);
        foreach ($jalur as $j) {
            Storage::disk('local')->assertExists($j);
            Storage::disk('public')->assertMissing($j);
        }
    }

    /* ───────── akhiran yang dapat dijalankan ───────── */

    public function test_berkas_php_ditolak_walau_pemanggilnya_lupa_memvalidasi(): void
    {
        /* Lapis terakhir, bukan lapis pertama. Yang menjaga sungguh-sungguh
           adalah aturan validasi dan penolakan Nginx — tetapi keduanya
           dipasang per tempat unggahan, dan tempat unggahan berikutnya
           akan ditulis oleh orang yang tidak membaca keduanya. */
        $jahat = UploadedFile::fake()->create('sisip.php', 1, 'text/plain');

        $this->assertNull(Berkas::simpan($jahat, 'hazard'));
        $this->assertSame([], Berkas::simpanBanyak([$jahat], 'hazard'));
    }

    public function test_akhiran_php_bervariasi_ikut_ditolak(): void
    {
        foreach (['a.phtml', 'b.php5', 'c.phar', 'd.htaccess'] as $nama) {
            $this->assertNull(
                Berkas::simpan(UploadedFile::fake()->create($nama, 1, 'text/plain'), 'dokumen'),
                "$nama seharusnya ditolak.",
            );
        }
    }

    /* ───────── penjagaan rute ───────── */

    public function test_tanpa_login_berkas_tidak_dapat_dibaca(): void
    {
        $ttd = Signatory::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'name' => 'KTT', 'title' => 'Kepala',
            'signature' => 'signatures/rahasia.png', 'is_active' => true,
        ]);
        Storage::disk('local')->put('signatures/rahasia.png', 'x');

        $this->get(Berkas::url($ttd, 'ttd'))->assertRedirect('/login');
    }

    public function test_pengguna_perusahaan_lain_tidak_dapat_membaca(): void
    {
        $lain = Company::create(['name' => 'PT Sebelah']);
        $ttd = Signatory::withoutGlobalScopes()->create([
            'company_id' => $lain->id, 'name' => 'KTT Sebelah', 'title' => 'Kepala',
            'signature' => 'signatures/milik-orang-lain.png', 'is_active' => true,
        ]);
        Storage::disk('local')->put('signatures/milik-orang-lain.png', 'x');

        /* 404, bukan 403. Barisnya memang TIDAK DAPAT DITEMUKAN oleh
           pengguna ini — dan 403 akan mengakui bahwa ia ada. */
        $this->actingAs($this->pengguna)
             ->get(route('berkas.sajikan', ['jenis' => 'ttd', 'baris' => $ttd->id]))
             ->assertNotFound();
    }

    public function test_pemilik_dapat_membaca_berkasnya_sendiri(): void
    {
        $ttd = Signatory::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'name' => 'KTT', 'title' => 'Kepala',
            'signature' => 'signatures/punya-sendiri.png', 'is_active' => true,
        ]);
        Storage::disk('local')->put('signatures/punya-sendiri.png', 'isi-gambar');

        $this->actingAs($this->pengguna)
             ->get(Berkas::url($ttd, 'ttd'))
             ->assertOk();
    }

    public function test_jenis_yang_tidak_dikenal_ditolak(): void
    {
        $this->actingAs($this->pengguna)->get('/berkas/env/1')->assertNotFound();
    }

    /* ───────── alamat yang dihasilkan ───────── */

    public function test_alamat_tidak_lagi_menunjuk_ke_storage(): void
    {
        $ttd = Signatory::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'name' => 'KTT', 'title' => 'Kepala',
            'signature' => 'signatures/x.png', 'is_active' => true,
        ]);

        $alamat = Berkas::url($ttd, 'ttd');

        $this->assertStringNotContainsString('/storage/', $alamat);
        $this->assertStringContainsString('/berkas/ttd/', $alamat);
    }

    public function test_baris_tanpa_berkas_tidak_menghasilkan_alamat(): void
    {
        $ttd = Signatory::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'name' => 'KTT', 'title' => 'Kepala',
            'signature' => null, 'is_active' => true,
        ]);

        $this->assertNull(Berkas::url($ttd, 'ttd'));
        $this->assertNull(Berkas::url(null, 'ttd'));
    }

    public function test_foto_berdaftar_menghasilkan_satu_alamat_per_foto(): void
    {
        $h = HazardReport::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'kode' => 'HZ-900', 'pelapor_nama' => 'A',
            'deskripsi' => 'x', 'status' => 'Open', 'risiko' => 'Rendah',
            'tanggal' => now()->toDateString(),
            'foto' => ['hazard/a.jpg', 'hazard/b.jpg'],
        ]);

        $daftar = Berkas::daftarUrl($h, 'hzd');

        $this->assertCount(2, $daftar);
        $this->assertStringContainsString('/berkas/hzd/'.$h->id.'/0', $daftar[0]);
        $this->assertStringContainsString('/berkas/hzd/'.$h->id.'/1', $daftar[1]);
    }

    /* ───────── berkas terbuka tetap terbuka ───────── */

    public function test_logo_dan_sampul_tetap_disajikan_tanpa_login(): void
    {
        /* Menutup yang ini akan merusak halaman yang memang dimaksudkan
           terbuka: logo tergambar di kop surat dan di lembar cetak yang
           dibuka peramban tanpa sesi. */
        $this->assertStringContainsString('/storage/logo/x.png', Berkas::terbuka('logo/x.png'));
        $this->assertNull(Berkas::terbuka(null));
    }

    /* ───────── titik unggah yang sebenarnya ───────── */

    /**
     * Foto bahaya dulu tersimpan TANPA validasi apa pun.
     *
     * Bukan hanya tanpa batas ukuran — tanpa batas JENIS. `$request->
     * file('foto')` langsung masuk ke disk, apa pun isinya, dan disk itu
     * disk publik. Berkas .php yang mendarat di sana berada di bawah akar
     * web lewat storage:link, dan blok `location ~ \.php$` pada Nginx
     * tidak membedakan berkas aplikasi dari berkas unggahan.
     *
     * Alamat acaknya pun tidak menyulitkan siapa pun: halaman rincian
     * menampilkannya kembali kepada yang mengunggah.
     */
    public function test_unggahan_php_ke_laporan_bahaya_ditolak(): void
    {
        $this->actingAs($this->pengguna)
             ->post(route('hazard.store'), [
                 'tanggal'   => now()->toDateString(),
                 'lokasi'    => 'Pit 1',
                 'deskripsi' => 'Uji unggahan berbahaya',
                 'risiko'    => 'Rendah',
                 'kategori'  => 'Unsafe Condition',
                 'pelapor_nama'    => 'Pelapor Uji',
                 'pelapor_jabatan' => 'Operator',
                 'company_id'      => $this->c->id,
                 'foto'      => [UploadedFile::fake()->create('sisip.php', 1, 'text/plain')],
             ])
             ->assertSessionHasErrors();

        $this->assertSame(0, HazardReport::withoutGlobalScopes()->count(),
            'Laporan tersimpan padahal unggahannya ditolak.');
    }

    public function test_unggahan_gambar_ke_laporan_bahaya_diterima(): void
    {
        $this->actingAs($this->pengguna)
             ->post(route('hazard.store'), [
                 'tanggal'   => now()->toDateString(),
                 'lokasi'    => 'Pit 1',
                 'deskripsi' => 'Uji unggahan wajar',
                 'risiko'    => 'Rendah',
                 'kategori'  => 'Unsafe Condition',
                 'pelapor_nama'    => 'Pelapor Uji',
                 'pelapor_jabatan' => 'Operator',
                 'company_id'      => $this->c->id,
                 'foto'      => [$this->gambar()],
             ])
             ->assertSessionHasNoErrors();

        $h = HazardReport::withoutGlobalScopes()->firstOrFail();

        $this->assertCount(1, (array) $h->foto);
        Storage::disk('local')->assertExists(((array) $h->foto)[0]);
        Storage::disk('public')->assertMissing(((array) $h->foto)[0]);
    }

    /**
     * Logo perusahaan tidak lagi menerima SVG.
     *
     * SVG adalah XML yang boleh memuat <script>, disajikan dari domain
     * yang sama dengan aplikasinya, sehingga skripnya berjalan di dalam
     * asal yang sama — dengan akses ke kuki sesi siapa pun yang
     * membukanya. Logo adalah tempat paling nyaman untuk menaruhnya: ia
     * dipasang sekali oleh satu orang, lalu tergambar di halaman semua
     * orang.
     */
    public function test_logo_svg_ditolak(): void
    {
        $svg = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
        );

        $admin = User::factory()->create([
            'company_id' => $this->c->id, 'is_admin' => true, 'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
             ->post(route('personalia.perusahaan.simpan'), [
                 'name' => 'PT Uji Berkas',
                 'logo' => $svg,
             ])
             ->assertSessionHasErrors('logo');
    }
}
