<?php

namespace Tests\Feature;

use App\Models\{GudangBarang, GudangLokasi, GudangMutasi, User};
use App\Support\Gudang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Gudang & Penyimpanan.
 *
 * Yang diuji terutama saldo stoknya: seluruh halaman menurunkan angkanya
 * dari mutasi yang sama, jadi satu salah tanda akan menyebar ke dashboard,
 * daftar barang, dan laporan sekaligus.
 */
class GudangTest extends TestCase
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

    private function mutasi(GudangBarang $b, string $jenis, float $jumlah, array $x = []): GudangMutasi
    {
        return GudangMutasi::create(array_merge([
            'barang_id' => $b->id, 'jenis' => $jenis, 'jumlah' => $jumlah,
            'tanggal' => now()->toDateString(),
        ], $x));
    }

    /** Satu baris laporan Agustus 2026 sebagaimana dikirim ke Vue. */
    private function barisLaporan(string $nama): array
    {
        $props = $this->get(route('gudang.laporan', ['dari' => '2026-08-01', 'sampai' => '2026-08-31']))
                      ->assertOk()->viewData('page')['props'];

        $baris = collect($props['baris'])->firstWhere('nama', $nama);

        $this->assertNotNull($baris, "Baris laporan untuk '{$nama}' tidak ada.");

        return $baris;
    }

    /* ══════════════ saldo stok ══════════════ */

    public function test_stok_dijumlahkan_dari_mutasinya(): void
    {
        $b = $this->barang();

        $this->mutasi($b, 'masuk', 100);
        $this->mutasi($b, 'keluar', 30);
        $this->mutasi($b, 'masuk', 10);

        $this->assertSame(80.0, Gudang::stok($b->fresh()));
    }

    public function test_barang_rusak_ikut_mengurangi_stok(): void
    {
        // Barang rusak tetap keluar dari rak; tidak menguranginya membuat
        // stok buku lebih besar daripada yang benar-benar ada.
        $b = $this->barang();

        $this->mutasi($b, 'masuk', 50);
        $this->mutasi($b, 'rusak', 8);

        $this->assertSame(42.0, Gudang::stok($b->fresh()));
    }

    public function test_jumlah_selalu_positif_dan_arahnya_dari_jenis(): void
    {
        // Kalau arah ditentukan tanda bilangan, satu tempat yang lupa
        // membalik tanda menghasilkan stok salah tanpa galat apa pun.
        $b = $this->barang();

        $this->mutasi($b, 'masuk', 20);
        $this->mutasi($b, 'keluar', 5);

        foreach ($b->fresh()->mutasi as $m) {
            $this->assertGreaterThan(0, $m->jumlah, 'Jumlah mutasi tidak boleh bertanda minus.');
        }

        $this->assertSame(15.0, Gudang::stok($b->fresh()));
    }

    /* ══════════════ opname ══════════════ */

    public function test_opname_menetapkan_saldo_bukan_menambahnya(): void
    {
        $b = $this->barang();

        $this->mutasi($b, 'masuk', 100);
        $this->mutasi($b, 'opname', 0, ['stok_fisik' => 92]);   // hasil hitung fisik

        // 92, bukan 192 dan bukan 100.
        $this->assertSame(92.0, Gudang::stok($b->fresh()));
    }

    public function test_mutasi_setelah_opname_dihitung_dari_hasil_opname(): void
    {
        $b = $this->barang();

        $this->mutasi($b, 'masuk', 100, ['tanggal' => '2026-08-01']);
        $this->mutasi($b, 'opname', 0, ['tanggal' => '2026-08-05', 'stok_fisik' => 92]);
        $this->mutasi($b, 'keluar', 12, ['tanggal' => '2026-08-07']);

        $this->assertSame(80.0, Gudang::stok($b->fresh()));
    }

    public function test_opname_terakhir_yang_berlaku(): void
    {
        // Koreksi yang lebih baru tidak boleh tertimpa oleh koreksi lama.
        $b = $this->barang();

        $this->mutasi($b, 'masuk', 100, ['tanggal' => '2026-08-01']);
        $this->mutasi($b, 'opname', 0, ['tanggal' => '2026-08-05', 'stok_fisik' => 92]);
        $this->mutasi($b, 'opname', 0, ['tanggal' => '2026-08-09', 'stok_fisik' => 88]);
        $this->mutasi($b, 'masuk', 2,  ['tanggal' => '2026-08-10']);

        $this->assertSame(90.0, Gudang::stok($b->fresh()));
    }

    public function test_stok_tidak_disimpan_di_kolom_mana_pun(): void
    {
        // Saldo yang disimpan adalah ringkasan dari riwayat yang juga
        // tersimpan; dua sumber untuk satu kebenaran pasti berselisih.
        $kolom = \Illuminate\Support\Facades\Schema::getColumnListing('gudang_barang');

        $this->assertNotContains('stok', $kolom);
        $this->assertNotContains('saldo', $kolom);
    }

    /* ══════════════ status stok ══════════════ */

    public function test_status_stok_mengikuti_batas_minimumnya(): void
    {
        $b = $this->barang(['stok_min' => 10]);

        $this->mutasi($b, 'masuk', 50);
        $this->assertSame('aman', Gudang::statusStok($b->fresh())['kode']);

        $this->mutasi($b, 'keluar', 41);
        $this->assertSame('menipis', Gudang::statusStok($b->fresh())['kode']);

        $this->mutasi($b, 'keluar', 9);
        $this->assertSame('habis', Gudang::statusStok($b->fresh())['kode']);
    }

    public function test_barang_tanpa_batas_minimum_tidak_pernah_menipis(): void
    {
        // Batas nol berarti belum ditetapkan, bukan berarti selalu menipis.
        $b = $this->barang(['stok_min' => 0]);
        $this->mutasi($b, 'masuk', 1);

        $this->assertSame('aman', Gudang::statusStok($b->fresh())['kode']);
    }

    /* ══════════════ pantangan penyimpanan B3 ══════════════ */

    public function test_pantangan_berlaku_dua_arah(): void
    {
        // Ditulis satu arah di dalam daftar; pencocokannya harus memeriksa
        // keduanya, kalau tidak separuh pasangan berbahaya lolos.
        $this->assertNotNull(Gudang::pantangan('pengoksidasi', 'mudah_menyala'));
        $this->assertNotNull(Gudang::pantangan('mudah_menyala', 'pengoksidasi'));
    }

    public function test_kelas_yang_tidak_berpantangan_dinyatakan_aman(): void
    {
        $this->assertNull(Gudang::pantangan('iritan', 'berbahaya'));
        $this->assertNull(Gudang::pantangan('beracun', null));
    }

    public function test_penyimpanan_bersama_yang_berbahaya_terdeteksi(): void
    {
        $l = GudangLokasi::create(['kode' => 'GD-B3', 'nama' => 'Gudang B3', 'jenis' => 'b3']);

        $a = $this->barang(['kategori' => 'b3', 'kelas_b3' => 'pengoksidasi', 'lokasi_id' => $l->id, 'nama' => 'Kalium Permanganat']);
        $c = $this->barang(['kategori' => 'b3', 'kelas_b3' => 'mudah_menyala', 'lokasi_id' => $l->id, 'nama' => 'Tiner']);
        $this->mutasi($a, 'masuk', 10);
        $this->mutasi($c, 'masuk', 5);

        $langgar = Gudang::periksaPenyimpanan($l->fresh()->barang()->with('mutasi')->get());

        $this->assertCount(1, $langgar);
        $this->assertStringContainsString('pembakaran', $langgar[0]['alasan']);
    }

    public function test_bahan_yang_stoknya_habis_tidak_dilaporkan_berbahaya(): void
    {
        // Bahan bersaldo nol tidak ada wujudnya di rak; melaporkannya
        // membuat daftar peringatan penuh oleh hal yang tidak ada.
        $l = GudangLokasi::create(['kode' => 'GD-B3B', 'nama' => 'Gudang B3 B', 'jenis' => 'b3']);

        $a = $this->barang(['kategori' => 'b3', 'kelas_b3' => 'pengoksidasi', 'lokasi_id' => $l->id]);
        $c = $this->barang(['kategori' => 'b3', 'kelas_b3' => 'mudah_menyala', 'lokasi_id' => $l->id]);
        $this->mutasi($a, 'masuk', 10);
        $this->mutasi($c, 'masuk', 5);
        $this->mutasi($c, 'keluar', 5);          // tiner habis

        $this->assertSame([], Gudang::periksaPenyimpanan($l->fresh()->barang()->with('mutasi')->get()));
    }

    /* ══════════════ kedaluwarsa ══════════════ */

    public function test_batch_yang_mendekati_kedaluwarsa_terdaftar_lebih_dulu(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-11 08:00', 'UTC'));

        $a = $this->barang(['kategori' => 'b3', 'nama' => 'Bahan Dekat']);
        $b = $this->barang(['kategori' => 'b3', 'nama' => 'Bahan Jauh']);

        $this->mutasi($a, 'masuk', 5, ['kadaluarsa' => '2026-09-01', 'batch' => 'A1']);
        $this->mutasi($b, 'masuk', 5, ['kadaluarsa' => '2026-10-20', 'batch' => 'B1']);

        $daftar = Gudang::kedaluwarsa(GudangBarang::with('mutasi')->get());

        $this->assertCount(2, $daftar);
        $this->assertSame('Bahan Dekat', $daftar[0]['barang']->nama, 'Yang paling dekat harus di atas.');
        $this->assertLessThan($daftar[1]['sisa'], $daftar[0]['sisa']);

        Carbon::setTestNow();
    }

    public function test_batch_yang_sudah_lewat_bernilai_sisa_negatif(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-11 08:00', 'UTC'));

        $b = $this->barang(['kategori' => 'b3']);
        $this->mutasi($b, 'masuk', 5, ['kadaluarsa' => '2026-08-01']);

        $daftar = Gudang::kedaluwarsa(GudangBarang::with('mutasi')->get());

        $this->assertLessThan(0, $daftar[0]['sisa'], 'Batch yang lewat harus bernilai minus, bukan nol.');

        Carbon::setTestNow();
    }

    public function test_barang_habis_tidak_muncul_di_daftar_kedaluwarsa(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-11 08:00', 'UTC'));

        $b = $this->barang(['kategori' => 'b3']);
        $this->mutasi($b, 'masuk', 5, ['kadaluarsa' => '2026-08-20']);
        $this->mutasi($b, 'keluar', 5);

        $this->assertSame([], Gudang::kedaluwarsa(GudangBarang::with('mutasi')->get()));

        Carbon::setTestNow();
    }

    /* ══════════════ ringkasan ══════════════ */

    public function test_ringkasan_menghitung_tiap_keadaan_sekali(): void
    {
        $a = $this->barang(['kategori' => 'b3', 'stok_min' => 5]);
        $b = $this->barang(['kategori' => 'apd', 'stok_min' => 5]);
        $c = $this->barang(['kategori' => 'material']);

        $this->mutasi($a, 'masuk', 100);   // aman
        $this->mutasi($b, 'masuk', 3);     // menipis
        // c tanpa mutasi                  // habis

        $r = Gudang::ringkas(GudangBarang::with('mutasi')->get());

        $this->assertSame(3, $r['jumlah']);
        $this->assertSame(1, $r['habis']);
        $this->assertSame(1, $r['menipis']);
        $this->assertSame(1, $r['aman']);
        $this->assertSame($r['jumlah'], $r['aman'] + $r['menipis'] + $r['habis']);
    }

    public function test_b3_tanpa_msds_terhitung_tersendiri(): void
    {
        // Tanpa LDK, petugas tidak punya rujukan penanganan tumpahan
        // maupun pertolongan pertama.
        $this->barang(['kategori' => 'b3', 'msds' => null]);
        $this->barang(['kategori' => 'b3', 'msds' => 'msds/x.pdf']);
        $this->barang(['kategori' => 'material', 'msds' => null]);

        $r = Gudang::ringkas(GudangBarang::with('mutasi')->get());

        $this->assertSame(1, $r['tanpa_msds'], 'Hanya B3 tanpa LDK yang dihitung.');
    }

    /* ══════════════ saldo bertanggal ══════════════ */

    public function test_saldo_pada_tanggal_hanya_menghitung_mutasi_sampai_hari_itu(): void
    {
        $b = $this->barang();

        $this->mutasi($b, 'masuk', 100, ['tanggal' => '2026-08-01']);
        $this->mutasi($b, 'keluar', 20, ['tanggal' => '2026-08-05']);
        $this->mutasi($b, 'masuk', 50,  ['tanggal' => '2026-08-20']);

        $b = $b->fresh();
        $this->assertSame(100.0, Gudang::stokPada($b, '2026-08-01'));
        $this->assertSame(80.0,  Gudang::stokPada($b, '2026-08-10'));
        $this->assertSame(130.0, Gudang::stokPada($b, '2026-08-31'));
        $this->assertSame(0.0,   Gudang::stokPada($b, '2026-07-31'));
    }

    public function test_saldo_pada_tanggal_menghormati_opname(): void
    {
        $b = $this->barang();

        $this->mutasi($b, 'masuk', 100, ['tanggal' => '2026-08-01']);
        $this->mutasi($b, 'opname', 0,  ['tanggal' => '2026-08-05', 'stok_fisik' => 92]);

        $b = $b->fresh();
        $this->assertSame(100.0, Gudang::stokPada($b, '2026-08-04'));
        $this->assertSame(92.0,  Gudang::stokPada($b, '2026-08-05'));
    }

    /* ══════════════ laporan ══════════════ */

    public function test_baris_laporan_berjumlah_benar(): void
    {
        $this->masuk();
        $b = $this->barang(['nama' => 'Oli Mesin']);

        $this->mutasi($b, 'masuk', 100, ['tanggal' => '2026-07-20']);   // sebelum periode
        $this->mutasi($b, 'masuk', 40,  ['tanggal' => '2026-08-05']);
        $this->mutasi($b, 'keluar', 15, ['tanggal' => '2026-08-07']);

        $x = $this->barisLaporan('Oli Mesin');

        $this->assertSame(100.0, $x['awal']);
        $this->assertSame(40.0,  $x['masuk']);
        $this->assertSame(15.0,  $x['keluar']);
        $this->assertSame(125.0, $x['akhir']);
        $this->assertSame(0.0,   $x['penyesuaian']);
        $this->assertSame($x['akhir'], $x['awal'] + $x['masuk'] - $x['keluar'] + $x['penyesuaian']);
    }

    public function test_opname_di_tengah_periode_muncul_sebagai_penyesuaian(): void
    {
        // Tanpa kolom penyesuaian, barisnya tidak berjumlah dan seluruh
        // laporan jadi dicurigai — padahal angkanya benar.
        $this->masuk();
        $b = $this->barang(['nama' => 'Sarung Tangan']);

        $this->mutasi($b, 'masuk', 100, ['tanggal' => '2026-07-20']);
        $this->mutasi($b, 'keluar', 10, ['tanggal' => '2026-08-03']);
        $this->mutasi($b, 'opname', 0,  ['tanggal' => '2026-08-10', 'stok_fisik' => 85]);

        $x = $this->barisLaporan('Sarung Tangan');

        $this->assertSame(100.0, $x['awal']);
        $this->assertSame(85.0,  $x['akhir']);
        $this->assertSame(-5.0,  $x['penyesuaian'], 'Selisih opname harus tampak, bukan disembunyikan.');
        $this->assertTrue($x['opname']);
        $this->assertSame($x['akhir'], $x['awal'] + $x['masuk'] - $x['keluar'] + $x['penyesuaian']);
    }

    /* ══════════════ halaman ══════════════ */

    private const HALAMAN = ['index', 'barang', 'mutasi', 'opname', 'lokasi', 'b3', 'laporan'];

    public function test_seluruh_halaman_terbuka(): void
    {
        $this->masuk();

        $l = GudangLokasi::create(['kode' => 'GD-1', 'nama' => 'Gudang Utama', 'jenis' => 'umum']);
        $b = $this->barang(['lokasi_id' => $l->id, 'kategori' => 'b3', 'kelas_b3' => 'korosif']);
        $this->mutasi($b, 'masuk', 20, ['kadaluarsa' => '2026-09-30', 'batch' => 'X1']);

        foreach (self::HALAMAN as $h) {
            $this->get(route('gudang.'.$h))
                 ->assertOk()
                 ->assertDontSee('NaN')
                 ->assertDontSee('INF');
        }
    }

    public function test_halaman_terbuka_saat_gudang_masih_kosong(): void
    {
        // Keadaan kosong melewati hampir seluruh perulangan, jadi ia
        // halaman yang berbeda dari keadaan terisi dan perlu diuji sendiri.
        $this->masuk();

        foreach (self::HALAMAN as $h) {
            $this->get(route('gudang.'.$h))->assertOk();
        }
    }

    public function test_tamu_tidak_dapat_membuka_gudang(): void
    {
        $this->get(route('gudang.index'))->assertRedirect(route('login'));
    }

    public function test_pengguna_biasa_tidak_dapat_mencatat_mutasi(): void
    {
        $this->masuk(admin: false);
        $b = $this->barang();

        $this->post(route('gudang.mutasi.simpan'), [
            'barang_id' => $b->id, 'jenis' => 'masuk', 'jumlah' => 5,
            'tanggal' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertSame(0.0, Gudang::stok($b->fresh()));
    }

    public function test_formulir_barang_baru_tidak_terbaca_sebagai_barang(): void
    {
        // 'gudang/barang/baru' harus dicoba sebelum 'gudang/barang/{barang}'.
        $this->masuk();

        $this->get(route('gudang.barang.baru'))->assertOk()->assertSee('Barang Baru');
    }

    /* ══════════════ pencatatan lewat halaman ══════════════ */

    public function test_mutasi_tersimpan_beserta_nomornya(): void
    {
        $this->masuk();
        $b = $this->barang();

        $this->post(route('gudang.mutasi.simpan'), [
            'barang_id' => $b->id, 'jenis' => 'masuk', 'jumlah' => 25,
            'tanggal' => '2026-08-11', 'pihak' => 'CV Pemasok Uji',
        ])->assertRedirect();

        $m = GudangMutasi::first();
        $this->assertStringStartsWith('GDM/', $m->nomor);
        $this->assertSame(25.0, Gudang::stok($b->fresh()));
    }

    public function test_pengeluaran_melebihi_stok_ditolak(): void
    {
        // Stok minus adalah angka yang tidak punya arti fisik dan menutupi
        // kesalahan pencatatan yang sesungguhnya.
        $this->masuk();
        $b = $this->barang();
        $this->mutasi($b, 'masuk', 10);

        $this->post(route('gudang.mutasi.simpan'), [
            'barang_id' => $b->id, 'jenis' => 'keluar', 'jumlah' => 15,
            'tanggal' => now()->toDateString(),
        ])->assertSessionHasErrors('jumlah');

        $this->assertSame(10.0, Gudang::stok($b->fresh()));
    }

    public function test_jumlah_nol_atau_minus_ditolak(): void
    {
        $this->masuk();
        $b = $this->barang();

        foreach ([0, -5] as $jumlah) {
            $this->post(route('gudang.mutasi.simpan'), [
                'barang_id' => $b->id, 'jenis' => 'masuk', 'jumlah' => $jumlah,
                'tanggal' => now()->toDateString(),
            ])->assertSessionHasErrors('jumlah');
        }

        $this->assertSame(0, GudangMutasi::count());
    }

    public function test_opname_tanpa_selisih_tidak_dicatat(): void
    {
        // Mencatatnya membuat riwayat penuh baris yang tidak mengubah apa pun.
        $this->masuk();
        $b = $this->barang();
        $this->mutasi($b, 'masuk', 40);

        $this->post(route('gudang.opname.simpan'), [
            'tanggal' => now()->toDateString(),
            'fisik'   => [$b->id => 40],
        ])->assertRedirect();

        $this->assertSame(0, GudangMutasi::where('jenis', 'opname')->count());
    }

    public function test_opname_berselisih_tercatat_dan_mengoreksi_saldo(): void
    {
        $this->masuk();
        $b = $this->barang();
        $this->mutasi($b, 'masuk', 40);

        $this->post(route('gudang.opname.simpan'), [
            'tanggal' => now()->toDateString(),
            'fisik'   => [$b->id => 37],
        ])->assertRedirect();

        $this->assertSame(1, GudangMutasi::where('jenis', 'opname')->count());
        $this->assertSame(37.0, Gudang::stok($b->fresh()));
    }

    public function test_baris_opname_yang_dikosongkan_dilewati(): void
    {
        $this->masuk();
        $a = $this->barang();
        $c = $this->barang();
        $this->mutasi($a, 'masuk', 10);
        $this->mutasi($c, 'masuk', 10);

        $this->post(route('gudang.opname.simpan'), [
            'tanggal' => now()->toDateString(),
            'fisik'   => [$a->id => 8, $c->id => ''],
        ])->assertRedirect();

        $this->assertSame(8.0,  Gudang::stok($a->fresh()));
        $this->assertSame(10.0, Gudang::stok($c->fresh()), 'Baris kosong tidak boleh mengubah saldo.');
    }

    /* ══════════════ barang ══════════════ */

    public function test_kelas_bahaya_dilepas_bila_kategorinya_bukan_b3(): void
    {
        // Kelas yang menempel pada material membuat matriks pantangan
        // memperingatkan barang yang bukan bahan kimia.
        $this->masuk();

        $this->post(route('gudang.barang.simpan'), [
            'kode' => 'MTR-9', 'nama' => 'Baut Roda', 'kategori' => 'material',
            'satuan' => 'pcs', 'kelas_b3' => 'korosif', 'un_number' => '1234',
        ])->assertRedirect();

        $b = GudangBarang::where('kode', 'MTR-9')->first();
        $this->assertNull($b->kelas_b3);
        $this->assertNull($b->un_number);
    }

    public function test_barang_yang_pernah_bermutasi_dinonaktifkan_bukan_dihapus(): void
    {
        // Menghapusnya ikut membuang riwayat penerimaan dan pengeluaran
        // yang menjadi bukti penelusuran.
        $this->masuk();
        $b = $this->barang();
        $this->mutasi($b, 'masuk', 5);

        $this->delete(route('gudang.barang.hapus', $b))->assertRedirect();

        $this->assertDatabaseHas('gudang_barang', ['id' => $b->id, 'aktif' => false]);
        $this->assertSame(1, GudangMutasi::where('barang_id', $b->id)->count());
    }

    public function test_barang_tanpa_riwayat_boleh_dihapus(): void
    {
        $this->masuk();
        $b = $this->barang();

        $this->delete(route('gudang.barang.hapus', $b))->assertRedirect();

        $this->assertDatabaseMissing('gudang_barang', ['id' => $b->id]);
    }

    public function test_kode_barang_tidak_boleh_kembar(): void
    {
        $this->masuk();
        $this->barang(['kode' => 'SAMA']);

        $this->post(route('gudang.barang.simpan'), [
            'kode' => 'SAMA', 'nama' => 'Barang Lain', 'kategori' => 'material', 'satuan' => 'pcs',
        ])->assertSessionHasErrors('kode');
    }

    /* ══════════════ penyaring ══════════════ */

    public function test_penyaring_status_bekerja_walau_stok_bukan_kolom(): void
    {
        // Status diturunkan dari mutasi, jadi tidak dapat jadi syarat query
        // dan harus disaring setelah pengambilan.
        $this->masuk();

        $aman = $this->barang(['nama' => 'Barang Aman', 'stok_min' => 1]);
        $this->mutasi($aman, 'masuk', 50);
        $this->barang(['nama' => 'Barang Habis']);

        $this->get(route('gudang.barang', ['status' => 'habis']))
             ->assertOk()
             ->assertSee('Barang Habis')
             ->assertDontSee('Barang Aman');
    }

    /* ══════════════ nomor mutasi ══════════════ */

    public function test_nomor_mutasi_berurut_dan_berbeda_per_jenis(): void
    {
        $b = $this->barang();

        $this->assertStringStartsWith('GDM/', Gudang::nomorBerikut('masuk'));
        $this->assertStringStartsWith('GDK/', Gudang::nomorBerikut('keluar'));

        $this->mutasi($b, 'masuk', 1, ['nomor' => Gudang::nomorBerikut('masuk')]);

        // Nomor berikutnya harus maju, bukan mengulang yang sudah dipakai.
        $this->assertStringEndsWith('0002', Gudang::nomorBerikut('masuk'));
        $this->assertStringEndsWith('0001', Gudang::nomorBerikut('keluar'));
    }
}
