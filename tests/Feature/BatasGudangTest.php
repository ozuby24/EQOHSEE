<?php

namespace Tests\Feature;

use App\Models\{Company, GudangBarang, GudangLokasi, GudangMutasi, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batas data gudang antar perusahaan, diuji lewat rutenya sendiri.
 *
 * Melengkapi BatasPerusahaanTest yang menguji janji dasarnya pada
 * HazardReport. Yang diperiksa di sini adalah jalur yang sempat lolos
 * dari janji itu.
 *
 * Uji ini tidak memeriksa scope-nya terpasang atau tidak — ia memanggil
 * rutenya sebagai pengguna perusahaan lain dan melihat apa yang terjadi.
 * Bedanya penting: sebuah model dapat terlihat aman karena induknya
 * ber-scope, lalu tetap terbuka karena route-nya mengikat ANAKnya
 * langsung dan melewati induk itu sama sekali.
 *
 * Gudang menjadi contoh yang paling terang. `gudang_lokasi` punya
 * company_id dan ber-scope rapi, tetapi `gudang_barang` — tempat
 * register B3, MSDS, dan seluruh persediaan — lahir tanpa kolom
 * perusahaan sama sekali. Akibatnya bukan sekadar daftar yang
 * tercampur: satu perusahaan dapat menyunting dan menghapus barang
 * milik perusahaan lain, dan tidak ada galat apa pun yang menandainya.
 */
class BatasGudangTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;
    private Company $b;
    private User $orangA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Company::create(['name' => 'PT Alpha']);
        $this->b = Company::create(['name' => 'PT Beta']);
        $this->orangA = User::factory()->create(['company_id' => $this->a->id]);
    }

    private function barangMilik(Company $c, string $kode): GudangBarang
    {
        $lokasi = GudangLokasi::create([
            'company_id' => $c->id, 'kode' => 'GD-'.$c->id, 'nama' => 'Gudang '.$c->name,
        ]);

        return GudangBarang::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'lokasi_id' => $lokasi->id,
            'kode' => $kode, 'nama' => 'Solar drum', 'kategori' => 'B3',
            'satuan' => 'drum', 'kelas_b3' => 'Cairan mudah menyala', 'aktif' => true,
        ]);
    }

    /* ---------- daftar ---------- */

    public function test_daftar_barang_tidak_memuat_milik_perusahaan_lain(): void
    {
        $this->barangMilik($this->a, 'BRG-A');
        $this->barangMilik($this->b, 'BRG-B');

        $this->actingAs($this->orangA)->get(route('gudang.barang'))->assertOk();

        $terlihat = GudangBarang::pluck('kode')->all();

        $this->assertContains('BRG-A', $terlihat);
        $this->assertNotContains('BRG-B', $terlihat);
    }

    /* ---------- jalur tulis ---------- */

    /**
     * Barang perusahaan lain ditolak sebelum hak aksesnya sempat
     * ditimbang.
     *
     * Jawabannya 404, bukan 403, dan itu memang yang diinginkan:
     * pengikatan route mencari barang itu lewat scope perusahaan, tidak
     * menemukannya, dan berhenti di situ. 403 akan menjawab pertanyaan
     * yang tidak pernah pantas dijawab — "barang dengan id ini ada,
     * hanya bukan milikmu" — dan jawaban itu cukup untuk memetakan isi
     * gudang perusahaan lain satu per satu.
     *
     * Sebelum kolom perusahaan ada, keduanya menjawab 403 dari
     * `can:admin`: penulisan memang tertutup bagi pengguna biasa, tetapi
     * barangnya sendiri tetap ditemukan.
     */
    public function test_barang_perusahaan_lain_tidak_terjangkau_jalur_tulis(): void
    {
        $milikB = $this->barangMilik($this->b, 'BRG-B');

        $this->actingAs($this->orangA)
            ->put(route('gudang.barang.ubah', $milikB), [
                'kode' => 'BRG-B', 'nama' => 'Diubah orang luar', 'satuan' => 'drum',
            ])
            ->assertNotFound();

        $this->actingAs($this->orangA)
            ->delete(route('gudang.barang.hapus', $milikB))
            ->assertNotFound();

        $this->assertSame('Solar drum', $milikB->fresh()->nama);
    }

    /** Barang sendiri pun tetap tertutup bagi pengguna biasa: tulis hanya untuk admin. */
    public function test_pengguna_biasa_tidak_dapat_menulis_ke_gudang_sendiri(): void
    {
        $milikA = $this->barangMilik($this->a, 'BRG-A');

        $this->actingAs($this->orangA)
            ->put(route('gudang.barang.ubah', $milikA), [
                'kode' => 'BRG-A', 'nama' => 'Diubah pengguna biasa', 'satuan' => 'drum',
            ])
            ->assertForbidden();

        $this->assertSame('Solar drum', $milikA->fresh()->nama);
    }

    /* ---------- mutasi ---------- */

    public function test_mutasi_perusahaan_lain_tidak_terlihat(): void
    {
        $barangA = $this->barangMilik($this->a, 'BRG-A');
        $barangB = $this->barangMilik($this->b, 'BRG-B');

        foreach ([[$barangA, $this->a, 10], [$barangB, $this->b, 99]] as [$brg, $co, $jml]) {
            GudangMutasi::withoutGlobalScopes()->create([
                'company_id' => $co->id, 'barang_id' => $brg->id, 'lokasi_id' => $brg->lokasi_id,
                'jenis' => 'masuk', 'jumlah' => $jml, 'tanggal' => '2026-08-01',
            ]);
        }

        $this->actingAs($this->orangA)->get(route('gudang.mutasi'))->assertOk();

        $jumlah = GudangMutasi::pluck('jumlah')->map(fn ($x) => (int) $x)->all();

        $this->assertContains(10, $jumlah);
        $this->assertNotContains(99, $jumlah);
    }

    /* ---------- administrator tetap menjangkau semuanya ---------- */

    public function test_administrator_tetap_melihat_seluruh_perusahaan(): void
    {
        $this->barangMilik($this->a, 'BRG-A');
        $this->barangMilik($this->b, 'BRG-B');

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        $terlihat = GudangBarang::pluck('kode')->all();

        $this->assertContains('BRG-A', $terlihat);
        $this->assertContains('BRG-B', $terlihat);
    }

    /* ---------- baris milik bersama tetap terlihat ---------- */

    public function test_barang_tanpa_perusahaan_tetap_terlihat_semua_orang(): void
    {
        // company_id NULL berarti belum dimiliki siapa pun — data lama
        // yang lahir sebelum kolomnya ada. Menyembunyikannya membuat
        // seluruh gudang tampak kosong bagi setiap pengguna, dan
        // kegagalan itu diam: tidak ada galat, hanya daftar kosong yang
        // terlihat seperti "memang belum ada datanya".
        GudangBarang::withoutGlobalScopes()->create([
            'company_id' => null, 'kode' => 'BRG-LAMA', 'nama' => 'Barang warisan',
            'kategori' => 'Material', 'satuan' => 'unit', 'aktif' => true,
        ]);

        $this->actingAs($this->orangA);

        $this->assertContains('BRG-LAMA', GudangBarang::pluck('kode')->all());
    }
}
