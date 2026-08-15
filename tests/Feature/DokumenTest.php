<?php

namespace Tests\Feature;

use App\Models\{Document, User};
use App\Support\Dokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DokumenTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function dokumen(array $ganti = []): Document
    {
        return Document::create(array_merge([
            'kode' => 'SOP-K3-001', 'judul' => 'Prosedur LOTO',
            'jenis' => 'Prosedur', 'status' => 'berlaku', 'revisi' => 0,
        ], $ganti));
    }

    public function test_register_dapat_dibuka(): void
    {
        $this->actingAs($this->admin())->get(route('dokumen.index'))->assertOk();
    }

    public function test_dokumen_dapat_didaftarkan_dan_revisi_awal_tercatat(): void
    {
        $this->actingAs($this->admin())->post(route('dokumen.store'), [
            'kode' => 'SOP-K3-001', 'judul' => 'Prosedur LOTO',
            'jenis' => 'Prosedur', 'status' => 'berlaku',
        ])->assertRedirect();

        $d = Document::first();
        $this->assertNotNull($d);

        // Riwayat tidak boleh berlubang: revisi awal ikut tercatat.
        $this->assertSame(1, $d->revisions()->count());
        $this->assertSame(0, $d->revisions()->first()->revisi);
    }

    public function test_kode_dokumen_harus_unik(): void
    {
        $this->dokumen();

        $this->actingAs($this->admin())->post(route('dokumen.store'), [
            'kode' => 'SOP-K3-001', 'judul' => 'Lain', 'jenis' => 'Prosedur', 'status' => 'draft',
        ])->assertSessionHasErrors('kode');
    }

    public function test_jenis_di_luar_daftar_ditolak(): void
    {
        $this->actingAs($this->admin())->post(route('dokumen.store'), [
            'kode' => 'X-1', 'judul' => 'Uji', 'jenis' => 'Buku Resep', 'status' => 'draft',
        ])->assertSessionHasErrors('jenis');
    }

    public function test_revisi_menaikkan_nomor_dan_menambah_riwayat(): void
    {
        $d = $this->dokumen(['revisi' => 2, 'status' => 'draft']);

        $this->actingAs($this->admin())->post(route('dokumen.revisi', $d), [
            'ringkasan_perubahan' => 'Menambahkan langkah verifikasi isolasi.',
        ])->assertRedirect();

        $d->refresh();
        $this->assertSame(3, $d->revisi);
        $this->assertSame('berlaku', $d->status);
        $this->assertSame(3, $d->revisions()->first()->revisi);
        $this->assertStringContainsString('verifikasi isolasi', $d->revisions()->first()->ringkasan_perubahan);
    }

    public function test_revisi_tanpa_ringkasan_ditolak(): void
    {
        $d = $this->dokumen();

        $this->actingAs($this->admin())->post(route('dokumen.revisi', $d), [])
            ->assertSessionHasErrors('ringkasan_perubahan');

        $this->assertSame(0, $d->refresh()->revisi);
    }

    public function test_berkas_lama_tetap_tersimpan_di_riwayat_setelah_revisi(): void
    {
        Storage::fake('public');
        $d = $this->dokumen(['berkas' => 'dokumen/lama.pdf']);
        $d->revisions()->create(['revisi' => 0, 'berkas' => 'dokumen/lama.pdf', 'tanggal' => now()]);

        $this->actingAs($this->admin())->post(route('dokumen.revisi', $d), [
            'ringkasan_perubahan' => 'Ganti berkas.',
            'berkas' => UploadedFile::fake()->create('baru.pdf', 12),
        ])->assertRedirect();

        $d->refresh();
        $this->assertNotSame('dokumen/lama.pdf', $d->berkas, 'Berkas berjalan harus yang baru.');
        $this->assertSame('dokumen/lama.pdf',
            $d->revisions()->where('revisi', 0)->first()->berkas,
            'Berkas revisi lama harus tetap tercatat untuk jejak audit.');
    }

    public function test_perlu_tinjau_hanya_untuk_dokumen_berlaku_yang_lewat_tempo(): void
    {
        $lewat  = $this->dokumen(['kode'=>'A-1','tanggal_tinjau'=>now()->subDay()]);
        $depan  = $this->dokumen(['kode'=>'A-2','tanggal_tinjau'=>now()->addYear()]);
        $draft  = $this->dokumen(['kode'=>'A-3','status'=>'draft','tanggal_tinjau'=>now()->subDay()]);
        $tanpa  = $this->dokumen(['kode'=>'A-4','tanggal_tinjau'=>null]);

        $this->assertTrue($lewat->perluTinjau());
        $this->assertFalse($depan->perluTinjau());
        $this->assertFalse($draft->perluTinjau(), 'Dokumen draft belum berlaku, jadi belum perlu ditinjau.');
        $this->assertFalse($tanpa->perluTinjau());
    }

    public function test_saring_lewat_jatuh_tempo(): void
    {
        $this->dokumen(['kode'=>'A-1','tanggal_tinjau'=>now()->subDay()]);
        $this->dokumen(['kode'=>'A-2','tanggal_tinjau'=>now()->addYear()]);

        $this->actingAs($this->admin())
            ->get(route('dokumen.index', ['tinjau' => 'lewat']))
            ->assertOk()
            ->assertSee('A-1')
            ->assertDontSee('A-2');
    }

    public function test_urutan_jenis_berlaku_lintas_mesin_basis_data(): void
    {
        // CASE WHEN adalah SQL baku; ekspresi ini harus dapat dijalankan
        // apa pun mesin basis datanya, bukan hanya MySQL.
        $this->dokumen(['kode'=>'F-1','jenis'=>'Formulir']);
        $this->dokumen(['kode'=>'K-1','jenis'=>'Kebijakan']);

        $urut = Document::orderByRaw(Dokumen::urutJenisSql())->pluck('kode')->all();

        $this->assertSame(['K-1','F-1'], $urut, 'Kebijakan berada di puncak piramida dokumen.');
    }

    public function test_halaman_dokumen_dapat_dirender(): void
    {
        $d = $this->dokumen(['tanggal_tinjau' => now()->subDay()]);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('dokumen.show', $d))->assertOk()->assertSee('Prosedur LOTO');
        $this->actingAs($admin)->get(route('dokumen.edit', $d))->assertOk();
        $this->actingAs($admin)->get(route('dokumen.create'))->assertOk();
    }

    public function test_tamu_tidak_dapat_mengakses(): void
    {
        $this->get(route('dokumen.index'))->assertRedirect(route('login'));
    }

    /**
     * Nilai saringan yang dikirim halaman harus berupa teks, bukan null.
     *
     * `Request::get()` mengembalikan null ketika parameternya tidak ada.
     * Null itu terkirim ke <select> yang pilihan pertamanya bernilai "",
     * dan v-model tidak pernah mencocokkan keduanya — selectedIndex
     * menjadi −1 dan kotaknya tampil KOSONG alih-alih "Semua jenis".
     * Tidak ada galat yang muncul; saringannya hanya terlihat seperti
     * belum jadi, dan itulah yang membuatnya lolos sekian lama.
     */
    public function test_nilai_saringan_berupa_teks_agar_pilihan_bawaan_terpilih(): void
    {
        $this->actingAs($this->admin());

        $f = $this->get(route('dokumen.index'))
            ->assertOk()->viewData('page')['props']['f'];

        foreach (['q', 'jenis', 'status', 'departemen', 'tinjau'] as $kunci) {
            $this->assertIsString($f[$kunci],
                "Saringan '{$kunci}' bukan teks; <select> tidak akan memilih pilihan bawaannya.");
        }
    }
}
