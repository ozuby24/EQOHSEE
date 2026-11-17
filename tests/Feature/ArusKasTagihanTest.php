<?php

namespace Tests\Feature;

use App\Models\Pembelian\{Pesanan, Produk};
use App\Models\User;
use App\Support\Pembelian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel arus kas pada halaman tagihan.
 *
 * Yang dijaga di sini satu kalimat: angka uang di halaman ini harus
 * benar apa pun yang sedang dilakukan orang di halaman itu. Panel uang
 * yang berubah mengikuti penyaring, atau yang menghitung tagihan mati
 * sebagai piutang, tidak memberi galat apa pun — ia hanya memberi angka
 * yang salah kepada orang yang sedang memutuskan uang.
 */
class ArusKasTagihanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Penghitung kode produk.
     *
     * Semula 'W'.random_int(1000, 9999), dan itu membuat berkas ini
     * gagal sesekali dengan galat UNIQUE pada beli_produk.kode —
     * sembilan ribu kemungkinan, beberapa produk per uji, jadi dua di
     * antaranya bertabrakan cepat atau lambat. Kegagalan yang muncul
     * satu kali dari sekian puluh jalan adalah yang paling mahal:
     * yang membacanya menyalahkan perubahan yang sedang dikerjakan,
     * bukan uji yang memang goyah.
     *
     * Berurutan, jadi tidak pernah bertabrakan dan tidak pernah
     * bergantung pada keberuntungan.
     */
    private static int $nomorProduk = 0;

    private function produk(int $harga): Produk
    {
        return Produk::create([
            'kode' => 'W'.(++self::$nomorProduk), 'nama' => 'Website', 'jenis' => Produk::WEBSITE,
            'harga' => $harga, 'masa_bulan' => 12, 'aktif' => true, 'urutan' => 0,
        ]);
    }

    private function tagihan(int $harga, string $status, ?string $kedaluwarsa = null): Pesanan
    {
        $p = Pembelian::buat(['pembeli_nama' => 'Pembeli'], [$this->produk($harga)->id => 1]);

        $p->forceFill([
            'status' => $status,
            'kedaluwarsa_pada' => $kedaluwarsa,
            'diverifikasi_pada' => $status === Pesanan::LUNAS ? now() : null,
        ])->save();

        return $p;
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    }

    /* ═══════════ hitungannya ═══════════ */

    public function test_pendapatan_hanya_dari_yang_lunas(): void
    {
        $this->tagihan(10_000_000, Pesanan::LUNAS);
        $this->tagihan(5_000_000,  Pesanan::LUNAS);
        $this->tagihan(99_000_000, Pesanan::MENUNGGU_BAYAR, now()->addDay());
        $this->tagihan(77_000_000, Pesanan::DITOLAK);

        $k = Pembelian::arusKas();

        $this->assertSame(15_000_000, $k['masuk']['nilai']);
        $this->assertSame(2, $k['masuk']['jumlah']);
    }

    /**
     * Tagihan yang lewat tenggat BUKAN piutang.
     *
     * Di basis data statusnya masih menunggu_bayar sampai pembersihnya
     * dijalankan, tetapi daftar di bawah panel ini sudah menuliskannya
     * KEDALUWARSA — lihat Pesanan::keadaan(). Menghitungnya sebagai uang
     * yang akan masuk membuat panelnya berselisih dengan tabel tepat di
     * bawahnya, dan yang membacanya akan mempercayai angka yang lebih
     * besar.
     */
    public function test_tagihan_lewat_tenggat_tidak_dihitung_sebagai_piutang(): void
    {
        $this->tagihan(8_000_000,  Pesanan::MENUNGGU_BAYAR, now()->addDay());
        $this->tagihan(50_000_000, Pesanan::MENUNGGU_BAYAR, now()->subDay());

        $k = Pembelian::arusKas();

        $this->assertSame(8_000_000, $k['ditunggu']['nilai'],
            'Tagihan yang sudah lewat tenggat ikut dihitung sebagai uang yang akan masuk.');

        $this->assertSame(50_000_000, $k['hangus']['nilai'],
            'Tagihan yang lewat tenggat tidak muncul sebagai tidak tertagih.');
    }

    /**
     * Menunggu verifikasi TETAP dihitung sebagai akan masuk.
     *
     * Uangnya boleh jadi sudah ditransfer; yang belum hanya pemeriksaan
     * buktinya. Mengeluarkannya dari piutang membuat uang yang nyaris
     * pasti masuk menghilang dari panel.
     */
    public function test_menunggu_verifikasi_dihitung_sebagai_akan_masuk(): void
    {
        $this->tagihan(12_000_000, Pesanan::MENUNGGU_VERIFIKASI);

        $this->assertSame(12_000_000, Pembelian::arusKas()['ditunggu']['nilai']);
    }

    public function test_tanpa_tenggat_tetap_dihitung_sebagai_akan_masuk(): void
    {
        $this->tagihan(4_000_000, Pesanan::MENUNGGU_BAYAR, null);

        $this->assertSame(4_000_000, Pembelian::arusKas()['ditunggu']['nilai']);
    }

    /**
     * Bulan ini dihitung dari TANGGAL VERIFIKASI, bukan tanggal terbit.
     *
     * Tagihan yang terbit Desember dan dibayar Januari adalah pendapatan
     * Januari. Memakai tanggal terbitnya memindahkan uang ke bulan yang
     * tidak pernah menerimanya, dan laporan bulanannya tidak akan pernah
     * cocok dengan rekening koran.
     */
    public function test_bulan_ini_memakai_tanggal_verifikasi(): void
    {
        $lama = $this->tagihan(9_000_000, Pesanan::LUNAS);
        $lama->forceFill(['diverifikasi_pada' => now()->subMonths(2)])->save();

        $baru = $this->tagihan(3_000_000, Pesanan::LUNAS);

        $k = Pembelian::arusKas();

        $this->assertSame(12_000_000, $k['masuk']['nilai'], 'Pendapatan seluruhnya ikut terpotong.');
        $this->assertSame(3_000_000,  $k['bulanIni']['nilai'],
            'Tagihan yang diverifikasi dua bulan lalu ikut terhitung bulan ini.');
    }

    public function test_tanpa_tagihan_semuanya_nol_bukan_galat(): void
    {
        $k = Pembelian::arusKas();

        foreach (['masuk', 'ditunggu', 'bulanIni', 'hangus'] as $kunci) {
            $this->assertSame(0, $k[$kunci]['nilai'], "{$kunci} bukan nol pada basis data kosong.");
            $this->assertSame(0, $k[$kunci]['jumlah']);
        }
    }

    /* ═══════════ tidak terpengaruh penyaring ═══════════ */

    /**
     * Penjagaan terpenting di berkas ini.
     *
     * Ringkasannya semula dihitung dari baris yang sudah disaring, jadi
     * menyaring "Lunas" menampilkan "Menunggu bayar 0" padahal ada
     * tujuh. Sekarang angkanya dihitung dari seluruh tagihan, dan itu
     * harus tetap begitu — termasuk untuk angka uangnya.
     */
    public function test_angka_tidak_berubah_saat_daftar_disaring(): void
    {
        $this->tagihan(10_000_000, Pesanan::LUNAS);
        $this->tagihan(20_000_000, Pesanan::MENUNGGU_BAYAR, now()->addDay());
        $this->tagihan(30_000_000, Pesanan::MENUNGGU_VERIFIKASI);

        $admin = $this->admin();

        $tanpaSaring = $this->actingAs($admin)->get('/pembelian/tagihan')
            ->assertOk()->viewData('page')['props'];

        foreach ([Pesanan::LUNAS, Pesanan::MENUNGGU_BAYAR, Pesanan::MENUNGGU_VERIFIKASI] as $status) {
            $disaring = $this->actingAs($admin)->get('/pembelian/tagihan?status='.$status)
                ->assertOk()->viewData('page')['props'];

            $this->assertSame($tanpaSaring['arusKas'], $disaring['arusKas'],
                "Panel arus kas berubah ketika daftar disaring '{$status}'.");

            $this->assertSame($tanpaSaring['ringkas'], $disaring['ringkas'],
                "Ubin jumlah berubah ketika daftar disaring '{$status}'.");
        }
    }

    public function test_halaman_menampilkan_nominal_pendapatan(): void
    {
        $this->tagihan(15_000_000, Pesanan::LUNAS);

        $props = $this->actingAs($this->admin())->get('/pembelian/tagihan')
            ->assertOk()->viewData('page')['props'];

        $masuk = collect($props['arusKas'])->firstWhere('label', 'Pendapatan masuk');

        $this->assertNotNull($masuk, 'Panel tidak memuat pendapatan masuk.');
        $this->assertSame(15_000_000, $masuk['nilai']);
        $this->assertTrue($masuk['utama'], 'Pendapatan tidak ditonjolkan di antara ubin lainnya.');
    }

    /**
     * Ringkasan jumlah juga memakai keadaan yang ditampilkan.
     *
     * "Menunggu bayar" pada ubin harus berarti hal yang sama dengan
     * "Menunggu pembayaran" pada tabelnya. Kalau ubinnya menghitung
     * status mentah sedangkan tabelnya menuliskan kedaluwarsa, keduanya
     * bercerita berbeda tentang baris yang sama.
     */
    public function test_ubin_menunggu_bayar_tidak_menghitung_yang_kedaluwarsa(): void
    {
        $this->tagihan(1_000_000, Pesanan::MENUNGGU_BAYAR, now()->addDay());
        $this->tagihan(2_000_000, Pesanan::MENUNGGU_BAYAR, now()->subDay());

        $this->assertSame(1, Pembelian::jumlahPerKeadaan()[Pesanan::MENUNGGU_BAYAR]);
    }
}
