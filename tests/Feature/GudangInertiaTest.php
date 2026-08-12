<?php

namespace Tests\Feature;

use App\Models\{GudangBarang, GudangLokasi, GudangMutasi, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Gudang (Inertia).
 *
 * Yang dijaga di sini bukan rupa halamannya melainkan bentuk muatannya:
 * sisi Vue menggambar angka stok apa adanya, jadi kunci yang berganti
 * nama atau hilang tidak menimbulkan galat — hanya kolom kosong yang
 * tampak wajar.
 */
class GudangInertiaTest extends TestCase
{
    use RefreshDatabase;

    private function masuk(bool $admin = true): User
    {
        $u = User::factory()->create(['is_admin' => $admin]);
        $this->actingAs($u);

        return $u;
    }

    private function barang(array $x = []): GudangBarang
    {
        static $n = 0;
        $n++;

        return GudangBarang::create(array_merge([
            'kode' => "BRG-{$n}", 'nama' => "Barang {$n}",
            'kategori' => 'material', 'satuan' => 'pcs', 'stok_min' => 0,
        ], $x));
    }

    private function props(string $rute, array $kueri = []): array
    {
        return $this->get(route($rute, $kueri))->assertOk()->viewData('page')['props'];
    }

    /* ══════════════ semua halaman dirender Inertia ══════════════ */

    public function test_tiap_halaman_gudang_dirender_inertia(): void
    {
        $this->masuk();

        $peta = [
            'gudang.index'        => 'Gudang/Index',
            'gudang.barang'       => 'Gudang/Barang',
            'gudang.barang.baru'  => 'Gudang/BarangForm',
            'gudang.mutasi'       => 'Gudang/Mutasi',
            'gudang.opname'       => 'Gudang/Opname',
            'gudang.lokasi'       => 'Gudang/Lokasi',
            'gudang.b3'           => 'Gudang/B3',
            'gudang.laporan'      => 'Gudang/Laporan',
        ];

        foreach ($peta as $rute => $komponen) {
            $this->get(route($rute))->assertOk()->assertInertia(
                fn (AssertableInertia $p) => $p->component($komponen)->has('judul')->has('subjudul'),
            );
        }
    }

    /* ══════════════ ringkasan ══════════════ */

    public function test_beranda_membawa_stok_dan_status_tiap_barang_kritis(): void
    {
        $this->masuk();
        $b = $this->barang(['nama' => 'Filter Oli', 'stok_min' => 10]);
        GudangMutasi::create(['barang_id' => $b->id, 'jenis' => 'masuk', 'jumlah' => 4,
                              'tanggal' => now()->toDateString()]);

        $kritis = collect($this->props('gudang.index')['kritis'])->firstWhere('nama', 'Filter Oli');

        $this->assertNotNull($kritis, 'Barang di bawah batas minimum harus muncul sebagai kritis.');
        $this->assertSame(4.0, $kritis['stok']);
        $this->assertSame(10.0, $kritis['stokMin']);
        $this->assertSame('menipis', $kritis['status']['kode']);
    }

    public function test_pelanggaran_penyimpanan_membawa_nama_lokasinya(): void
    {
        // Tanpa nama lokasi, peringatannya menyebut dua bahan tanpa
        // memberi tahu rak mana yang harus dibereskan.
        $this->masuk();

        $l = GudangLokasi::create(['kode' => 'GD-1', 'nama' => 'Gudang Kimia', 'jenis' => 'b3']);

        foreach ([['Asam Sulfat', 'korosif'], ['Serbuk Magnesium', 'reaktif_air']] as [$nama, $kelas]) {
            $b = $this->barang(['nama' => $nama, 'kategori' => 'b3', 'kelas_b3' => $kelas, 'lokasi_id' => $l->id]);
            GudangMutasi::create(['barang_id' => $b->id, 'jenis' => 'masuk', 'jumlah' => 5,
                                  'tanggal' => now()->toDateString()]);
        }

        $langgar = $this->props('gudang.index')['langgar'];

        $this->assertCount(1, $langgar);
        $this->assertSame('Gudang Kimia', $langgar[0]['lokasi']);
        $this->assertNotEmpty($langgar[0]['alasan']);
    }

    /* ══════════════ daftar barang ══════════════ */

    public function test_penyaring_status_dikerjakan_di_server(): void
    {
        // Statusnya diturunkan dari mutasi, bukan disimpan sebagai kolom;
        // menyaringnya di peramban berarti dua penerapan aturan yang sama.
        $this->masuk();

        $habis = $this->barang(['nama' => 'Sudah Habis']);
        $ada   = $this->barang(['nama' => 'Masih Ada']);
        GudangMutasi::create(['barang_id' => $ada->id, 'jenis' => 'masuk', 'jumlah' => 20,
                              'tanggal' => now()->toDateString()]);

        $nama = collect($this->props('gudang.barang', ['status' => 'habis'])['barang'])->pluck('nama');

        $this->assertContains('Sudah Habis', $nama);
        $this->assertNotContains('Masih Ada', $nama);
    }

    public function test_bukan_admin_tidak_diberi_tautan_ubah(): void
    {
        $this->masuk(admin: false);
        $this->barang();

        $this->assertFalse($this->props('gudang.barang')['bolehUbah']);
        $this->assertFalse($this->props('gudang.mutasi')['bolehCatat']);
        $this->assertFalse($this->props('gudang.opname')['bolehCatat']);
    }

    /* ══════════════ formulir barang ══════════════ */

    public function test_formulir_baru_memberi_nilai_awal_yang_masuk_akal(): void
    {
        $this->masuk();

        $p = $this->props('gudang.barang.baru');

        $this->assertFalse($p['tersimpan']);
        $this->assertSame('material', $p['awal']['kategori']);
        $this->assertSame('pcs', $p['awal']['satuan']);
        $this->assertTrue($p['awal']['aktif']);
    }

    public function test_formulir_ubah_membawa_isian_yang_tersimpan(): void
    {
        $this->masuk();
        $b = $this->barang(['nama' => 'Solar', 'kategori' => 'b3', 'kelas_b3' => 'mudah_menyala',
                            'satuan' => 'liter']);

        $p = $this->get(route('gudang.barang.edit', $b))->assertOk()->viewData('page')['props'];

        $this->assertTrue($p['tersimpan']);
        $this->assertSame('Solar', $p['awal']['nama']);
        $this->assertSame('mudah_menyala', $p['awal']['kelas_b3']);
        $this->assertSame('liter', $p['awal']['satuan']);
    }

    /* ══════════════ mutasi & opname ══════════════ */

    public function test_pilihan_barang_pada_mutasi_membawa_sisa_stoknya(): void
    {
        // Batas pengeluaran perlu diketahui sebelum menekan simpan, bukan
        // setelah permintaannya ditolak.
        $this->masuk();
        $b = $this->barang(['nama' => 'Grease']);
        GudangMutasi::create(['barang_id' => $b->id, 'jenis' => 'masuk', 'jumlah' => 12,
                              'tanggal' => now()->toDateString()]);

        $pilihan = collect($this->props('gudang.mutasi')['barang'])->firstWhere('nama', 'Grease');

        $this->assertSame(12.0, $pilihan['stok']);
    }

    public function test_opname_membawa_stok_buku_tiap_barang(): void
    {
        $this->masuk();
        $b = $this->barang(['nama' => 'Sarung Tangan']);
        GudangMutasi::create(['barang_id' => $b->id, 'jenis' => 'masuk', 'jumlah' => 30,
                              'tanggal' => now()->toDateString()]);

        $baris = collect($this->props('gudang.opname')['barang'])->firstWhere('nama', 'Sarung Tangan');

        $this->assertSame(30.0, $baris['buku']);
    }

    /* ══════════════ B3 ══════════════ */

    public function test_matriks_pantangan_disusun_di_server(): void
    {
        // Menyusunnya di Vue berarti menyalin aturan pantangan ke
        // TypeScript; salinan yang tertinggal melaporkan aman untuk
        // pasangan yang justru berbahaya.
        $this->masuk();

        $p = $this->props('gudang.b3');
        $kelas = array_column($p['matriks'], 'kelas');

        $this->assertCount(count($p['judulKolom']), $p['matriks'], 'Matriksnya harus persegi.');

        $i = array_search('pengoksidasi', $kelas, true);
        $j = array_search('mudah_menyala', $kelas, true);

        $this->assertNotEmpty($p['matriks'][$i]['sel'][$j]['alasan'],
            'Pengoksidasi dan bahan mudah menyala berpantangan.');
        $this->assertTrue($p['matriks'][$i]['sel'][$i]['sama'],
            'Kotak diagonal adalah kelas yang sama dengan dirinya.');
    }
}
