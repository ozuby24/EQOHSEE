<?php

namespace Tests\Feature;

use App\Models\{Company, News, NewsRead, User};
use App\Support\Berkas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pengumuman yang terbuka di tempat, lengkap dengan sampul dan lampiran.
 *
 * Tiga hal yang diuji di sini tidak menimbulkan galat bila lepas:
 *
 *   1. Sampul dan lampiran tersimpan di disk TERTUTUP. Lepas, surat
 *      edaran satu perusahaan terbaca lewat /storage/… oleh perusahaan
 *      lain pada pemasangan yang sama — tanpa login sama sekali.
 *   2. Pop-out membawa isi UTUH bersama halamannya. Lepas, pop-out
 *      terbuka kosong justru di sambungan site yang lambat.
 *   3. "Sudah dibaca" tercatat sekali per orang. Lepas, pengumuman yang
 *      dibuka empat kali oleh sepuluh orang terbaca sebagai empat puluh
 *      pembaca — dan angka itulah yang dipakai memutuskan apakah
 *      briefing perlu diulang.
 */
class PengumumanPopOutTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;
    private User $orang;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Berkas::TERTUTUP);
        Storage::fake(Berkas::TERBUKA);

        $this->a = Company::create(['name' => 'PT Alpha']);

        $this->orang = User::factory()->create([
            'company_id' => $this->a->id, 'email_verified_at' => now(),
        ]);

        $this->admin = User::factory()->create([
            'company_id' => $this->a->id, 'email_verified_at' => now(), 'is_admin' => true,
        ]);
    }

    private function berita(array $isi = []): News
    {
        return News::withoutGlobalScopes()->create($isi + [
            'company_id'   => $this->a->id,
            'title'        => 'Titik kumpul pindah ke sisi barat',
            /* Sengaja LEBIH PANJANG daripada batas potong mana pun yang
               dipakai di aplikasi ini — 220 huruf di daftar, 74 di panel
               dasbor. Isi yang lebih pendek membuat "utuh" dan
               "terpotong" kebetulan sama persis, dan uji yang
               membandingkan keduanya lolos tanpa menguji apa pun. */
            'content'      => "Mulai Senin depan titik kumpul pindah ke sisi barat kantor site.\n\n"
                .str_repeat('Rute evakuasi lama melewati jalur hauling dan tidak dipakai lagi. ', 6),
            'published_at' => now()->toDateString(),
        ]);
    }

    private function props(string $alamat): array
    {
        return $this->get($alamat)->assertOk()->viewData('page')['props'];
    }

    /* ---------- muatan pop-out ---------- */

    public function test_panel_dasbor_membawa_isi_utuh_bukan_sekadar_cuplikan(): void
    {
        /* Pop-out yang masih harus mengambil isinya lewat jaringan gagal
           terbuka persis di tempat aplikasi ini dipakai. Cacat yang sama
           pernah menimpa pop-out pengenalan dan berubah menjadi kotak
           galat yang muncul lagi tiap kali halamannya disegarkan. */
        $n = $this->berita();

        $p = $this->actingAs($this->orang)->props('/lms');

        $this->assertSame($n->content, $p['news'][0]['isi']);

        /* Dan isinya memang berbeda dari ringkasannya. Tanpa pemeriksaan
           ini, pengumuman yang kebetulan lebih pendek daripada batas
           potong membuat keduanya sama — dan uji di atas lolos meski
           yang dikirim hanya cuplikan. */
        $this->assertNotSame($p['news'][0]['ringkasan'], $p['news'][0]['isi'],
            'Isi contoh harus lebih panjang daripada batas potong, kalau tidak uji ini hampa.');
    }

    public function test_daftar_pengumuman_membawa_bentuk_yang_sama_dengan_dasbor(): void
    {
        /* Sebelum App\Support\Pengumuman ada, keduanya menyusun muatannya
           sendiri-sendiri dan sudah berselisih: satu memotong pada 74
           huruf, satunya pada 220, dengan format tanggal yang berlainan.
           Selisih semacam itu tidak pernah tampak sebagai galat — yang
           tampak adalah pengumuman yang "berubah" bila dibuka dari
           tempat lain. */
        $this->berita();

        $dasbor = $this->actingAs($this->orang)->props('/lms')['news'][0];
        $daftar = $this->actingAs($this->orang)->props('/news')['berita'][0];

        foreach (['id', 'judul', 'isi', 'tanggal', 'url', 'urlBaca', 'sudahDibaca'] as $kunci) {
            $this->assertSame($dasbor[$kunci], $daftar[$kunci], "Kunci '$kunci' berselisih.");
        }
    }

    public function test_ringkasan_yang_ditulis_penulis_mengalahkan_potongan_otomatis(): void
    {
        $this->berita(['excerpt' => 'Titik kumpul lama tidak dipakai lagi.']);

        $p = $this->actingAs($this->orang)->props('/news');

        $this->assertSame('Titik kumpul lama tidak dipakai lagi.', $p['berita'][0]['ringkasan']);
    }

    public function test_tanpa_ringkasan_isinya_dipotong_seperti_dulu(): void
    {
        $this->berita(['excerpt' => null, 'content' => str_repeat('a', 400)]);

        $p = $this->actingAs($this->orang)->props('/news');

        $this->assertStringEndsWith('...', $p['berita'][0]['ringkasan']);
        $this->assertLessThan(400, strlen($p['berita'][0]['ringkasan']));
    }

    /* ---------- berkas ---------- */

    public function test_sampul_dan_lampiran_tersimpan_di_disk_tertutup(): void
    {
        $this->actingAs($this->admin)->post('/news', [
            'title'        => 'Denah titik kumpul',
            'published_at' => now()->toDateString(),
            'cover'        => UploadedFile::fake()->image('denah.jpg'),
            'lampiran'     => UploadedFile::fake()->create('denah.pdf', 12, 'application/pdf'),
        ])->assertRedirect(route('news.index'));

        $n = News::withoutGlobalScopes()->firstOrFail();

        $this->assertNotNull($n->cover);
        $this->assertNotNull($n->lampiran);

        Storage::disk(Berkas::TERTUTUP)->assertExists($n->cover);
        Storage::disk(Berkas::TERTUTUP)->assertExists($n->lampiran);

        /* Disk TERBUKA dilayani Nginx langsung lewat /storage/…, tanpa
           melewati penjagaan mana pun. Satu berkas yang tersasar ke sana
           cukup untuk membatalkan seluruh maksud rute berjaga. */
        Storage::disk(Berkas::TERBUKA)->assertMissing($n->cover);
        Storage::disk(Berkas::TERBUKA)->assertMissing($n->lampiran);
    }

    public function test_nama_asli_lampiran_disimpan_agar_terbaca_sebelum_diunduh(): void
    {
        // Laravel menamai ulang berkas unggahan dengan rangkaian acak.
        // "9Xk2mP1q.pdf" pada surat edaran tidak memberi tahu siapa pun
        // apa isinya sebelum diunduh.
        $this->actingAs($this->admin)->post('/news', [
            'title'    => 'Surat edaran',
            'lampiran' => UploadedFile::fake()->create('SE-03 Jalur Hauling.pdf', 8, 'application/pdf'),
        ]);

        $n = News::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('SE-03 Jalur Hauling.pdf', $n->lampiran_nama);
        $this->assertNotSame('SE-03 Jalur Hauling.pdf', basename($n->lampiran));
    }

    public function test_sampul_pengumuman_perusahaan_lain_tidak_dapat_dibuka(): void
    {
        $b = Company::create(['name' => 'PT Beta']);

        $milikB = News::withoutGlobalScopes()->create([
            'company_id' => $b->id, 'title' => 'Rahasia Beta',
            'cover' => 'berita/rahasia.jpg', 'published_at' => now()->toDateString(),
        ]);

        Storage::disk(Berkas::TERTUTUP)->put('berita/rahasia.jpg', 'x');

        $this->actingAs($this->orang)
            ->get(route('berkas.sajikan', ['jenis' => 'brt', 'baris' => $milikB->id]))
            ->assertNotFound();
    }

    public function test_menyunting_tanpa_mengunggah_tidak_menghapus_sampul_lama(): void
    {
        /* Syarat agar formulir edit dapat dipakai membetulkan salah ketik
           pada judul tanpa harus mengunggah ulang gambarnya. */
        $n = $this->berita(['cover' => 'berita/lama.jpg']);

        $this->actingAs($this->admin)->put("/news/{$n->id}", [
            'title'        => 'Judul yang sudah dibetulkan',
            'published_at' => now()->toDateString(),
        ])->assertRedirect(route('news.index'));

        $this->assertSame('berita/lama.jpg', $n->fresh()->cover);
        $this->assertSame('Judul yang sudah dibetulkan', $n->fresh()->title);
    }

    public function test_menghapus_pengumuman_ikut_membuang_berkasnya(): void
    {
        Storage::disk(Berkas::TERTUTUP)->put('berita/sampul.jpg', 'x');
        Storage::disk(Berkas::TERTUTUP)->put('berita/lampiran.pdf', 'x');

        $n = $this->berita(['cover' => 'berita/sampul.jpg', 'lampiran' => 'berita/lampiran.pdf']);

        $this->actingAs($this->admin)->delete("/news/{$n->id}");

        Storage::disk(Berkas::TERTUTUP)->assertMissing('berita/sampul.jpg');
        Storage::disk(Berkas::TERTUTUP)->assertMissing('berita/lampiran.pdf');
    }

    /* ---------- sudah dibaca ---------- */

    public function test_menandai_baca_tercatat_sekali_saja_per_orang(): void
    {
        $n = $this->berita();

        /* Tiap permintaannya harus BERHASIL, bukan sekadar tidak
           menambah baris. Insert biasa akan ditolak kunci uniknya dan
           menjatuhkan permintaan kedua menjadi 500 — barisnya tetap
           satu, sehingga pemeriksaan jumlah saja lolos sementara yang
           menekan tombolnya dua kali melihat halaman galat. */
        foreach (range(1, 3) as $ke) {
            $this->actingAs($this->orang)->post(route('news.baca', $n))
                ->assertRedirect();
        }

        $this->assertSame(1, NewsRead::where('news_id', $n->id)->count());
    }

    public function test_jumlah_dan_penanda_terbaca_ikut_dalam_muatan(): void
    {
        $n = $this->berita();

        $sebelum = $this->actingAs($this->orang)->props('/news')['berita'][0];
        $this->assertFalse($sebelum['sudahDibaca']);
        $this->assertSame(0, $sebelum['jumlahDibaca']);

        $this->actingAs($this->orang)->post(route('news.baca', $n));

        $sesudah = $this->actingAs($this->orang)->props('/news')['berita'][0];
        $this->assertTrue($sesudah['sudahDibaca']);
        $this->assertSame(1, $sesudah['jumlahDibaca']);
    }

    public function test_bacaan_orang_lain_tidak_menandai_pengumuman_sebagai_terbaca(): void
    {
        /* Yang membedakan "dibaca 12 orang" dari "sudah Anda baca".
           Tertukar, seluruh regu melihat pengumuman wajib sebagai sudah
           dibacanya sendiri begitu satu orang membukanya. */
        $n = $this->berita();

        $this->actingAs($this->admin)->post(route('news.baca', $n));

        $p = $this->actingAs($this->orang)->props('/news')['berita'][0];

        $this->assertFalse($p['sudahDibaca']);
        $this->assertSame(1, $p['jumlahDibaca']);
    }

    public function test_menandai_baca_tidak_menuntut_hak_admin(): void
    {
        // Yang menandainya pembacanya, bukan yang menerbitkannya.
        $n = $this->berita();

        $this->actingAs($this->orang)->post(route('news.baca', $n))->assertRedirect();

        $this->assertDatabaseHas('news_reads', [
            'news_id' => $n->id, 'user_id' => $this->orang->id,
        ]);
    }

    public function test_halaman_penuh_tetap_hidup(): void
    {
        /* Alamat pengumuman kerap disalin ke grup pesan dan ditempelkan
           ke notulen. Alamat yang mati karena isinya pindah ke pop-out
           adalah kerugian yang baru terlihat ketika orang mengeluh. */
        $n = $this->berita();

        $p = $this->actingAs($this->orang)->props(route('news.show', $n));

        $this->assertSame($n->title, $p['berita']['judul']);
        $this->assertSame($n->content, $p['berita']['isi']);
    }
}
