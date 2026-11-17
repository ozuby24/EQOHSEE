<?php

namespace Tests\Feature;

use App\Models\Pembelian\{Item, Produk};
use App\Support\Pembelian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Harga bertingkat, dan paket layanan tahunan.
 *
 * Yang dijaga di sini bukan "diskonnya jalan" melainkan sesuatu yang
 * lebih sulit terlihat: angka yang dibaca pembeli di layar dan angka
 * yang benar-benar ditagihkan kepadanya HARUS sama. Keduanya dihitung
 * di dua bahasa berbeda, oleh dua berkas berbeda, dan tidak ada satu
 * pun galat yang akan muncul bila keduanya mulai berbeda — pembelinya
 * hanya menerima tagihan yang tidak ia setujui.
 */
class HargaBertingkatTest extends TestCase
{
    use RefreshDatabase;

    private function paketWebsite(int $harga = 25_000_000, ?int $tambahan = 2_000_000): Produk
    {
        return Produk::create([
            'kode' => 'WEBSITE', 'nama' => 'Website EQOHSEE', 'jenis' => Produk::WEBSITE,
            'harga' => $harga, 'harga_tambahan' => $tambahan,
            'masa_bulan' => 0, 'aktif' => true, 'urutan' => 0,
        ]);
    }

    private function pembeli(): array
    {
        return ['pembeli_nama' => 'Pak Budi', 'pembeli_perusahaan' => 'PT Uji'];
    }

    /* ═══════════ aturan hitungnya ═══════════ */

    public function test_website_kedua_dan_seterusnya_lebih_murah(): void
    {
        $p = $this->paketWebsite();

        $this->assertSame(25_000_000, $p->subtotal(1));
        $this->assertSame(27_000_000, $p->subtotal(2));
        $this->assertSame(29_000_000, $p->subtotal(3));
        $this->assertSame(33_000_000, $p->subtotal(5));
    }

    /**
     * Tanpa harga tambahan, hitungannya persis seperti sebelum kolom ini
     * ada. Ini yang menjaga seluruh katalog lama tidak berubah nilainya
     * hanya karena satu kolom ditambahkan.
     */
    public function test_tanpa_harga_tambahan_tetap_dikalikan(): void
    {
        $p = $this->paketWebsite(tambahan: null);

        $this->assertSame(50_000_000, $p->subtotal(2));
        $this->assertSame(75_000_000, $p->subtotal(3));
    }

    /**
     * Nol BUKAN null.
     *
     * Nol adalah harga yang sah untuk butir tambahan — gratis. Kalau
     * keduanya disamakan, "website kedua gratis" berubah menjadi
     * "website kedua harga penuh", dan selisihnya dua puluh lima juta
     * per baris tanpa satu pun tanda di layar.
     */
    public function test_harga_tambahan_nol_berarti_gratis_bukan_penuh(): void
    {
        $p = $this->paketWebsite(tambahan: 0);

        $this->assertSame(25_000_000, $p->subtotal(3),
            'Harga tambahan nol diperlakukan seperti tidak diatur.');
    }

    public function test_jumlah_nol_atau_negatif_tetap_dihitung_satu(): void
    {
        $p = $this->paketWebsite();

        $this->assertSame(25_000_000, $p->subtotal(0));
        $this->assertSame(25_000_000, $p->subtotal(-3));
    }

    /* ═══════════ sampai ke tagihan ═══════════ */

    public function test_tagihan_memakai_harga_bertingkat(): void
    {
        $p = $this->paketWebsite();

        $pesanan = Pembelian::buat($this->pembeli(), [$p->id => 3]);

        $this->assertSame(29_000_000, (int) $pesanan->total,
            'Tagihannya tidak memakai harga website kedua dan seterusnya.');

        $item = $pesanan->items()->first();

        $this->assertSame(29_000_000, $item->subtotal);
        $this->assertSame(2_000_000, $item->harga_tambahan,
            'Harga tambahan tidak disalin ke baris tagihan; subtotalnya '
            .'tidak akan dapat diterangkan begitu daftar harganya berubah.');
    }

    /**
     * Tagihan yang sudah terbit TIDAK ikut berubah.
     *
     * Ini alasan harga_tambahan disalin ke barisnya, sama seperti harga
     * dan nama. Tanpa salinan itu, menurunkan harga bulan depan diam-diam
     * mengubah nilai tagihan yang sudah dikirim — termasuk yang sudah
     * dibayar, yang berubah menjadi lebih bayar tanpa ada yang
     * melakukan apa pun.
     */
    public function test_mengubah_daftar_harga_tidak_mengubah_tagihan_lama(): void
    {
        $p = $this->paketWebsite();
        $pesanan = Pembelian::buat($this->pembeli(), [$p->id => 3]);

        $p->update(['harga' => 40_000_000, 'harga_tambahan' => 9_000_000]);

        $item = $pesanan->refresh()->items()->first();

        $this->assertSame(25_000_000, $item->harga);
        $this->assertSame(2_000_000, $item->harga_tambahan);
        $this->assertSame(29_000_000, $item->subtotal);
        $this->assertSame(29_000_000, (int) $pesanan->total);
    }

    /* ═══════════ paket layanan tahunan ═══════════ */

    public function test_paket_layanan_terpasang_lewat_perintah_katalog(): void
    {
        $this->artisan('pembelian:katalog')->assertSuccessful();

        $l = Produk::where('kode', 'LAYANAN-PRO')->first();

        $this->assertNotNull($l, 'Paket layanan tahunan tidak terpasang.');
        $this->assertSame(Produk::LAYANAN, $l->jenis);
        $this->assertSame(3_000_000, $l->harga);
        $this->assertSame(12, $l->masa_bulan);
        $this->assertTrue($l->aktif, 'Paket layanan terpasang tetapi tidak aktif — tidak akan muncul di katalog.');

        foreach (['1 vCPU', 'RAM 1 GB', 'NVMe 60 GB'] as $spek) {
            $this->assertStringContainsString($spek, (string) $l->keterangan,
                "Spesifikasi peladen '{$spek}' tidak disebut — pembeli tidak tahu apa yang ia sewa.");
        }

        $this->assertStringContainsString('seluruh website', (string) $l->keterangan,
            'Keterangannya tidak menyebut bahwa biayanya untuk seluruh akun, bukan per website. '
            .'Pelanggan dengan tiga website akan menyangka ia ditagih tiga kali.');
    }

    /**
     * Layanan TIDAK bertingkat.
     *
     * Ia satu peladen untuk seluruh akun; memesan dua berarti memesan
     * dua tahun atau dua peladen, dan keduanya berharga penuh.
     */
    public function test_layanan_tidak_bertingkat(): void
    {
        $this->artisan('pembelian:katalog')->assertSuccessful();

        $l = Produk::where('kode', 'LAYANAN-PRO')->firstOrFail();

        $this->assertFalse($l->bertingkat());
        $this->assertSame(6_000_000, $l->subtotal(2));
    }

    public function test_menjalankan_ulang_katalog_tidak_menimpa_harga_layanan(): void
    {
        $this->artisan('pembelian:katalog')->assertSuccessful();

        Produk::where('kode', 'LAYANAN-PRO')->update(['harga' => 4_500_000]);

        $this->artisan('pembelian:katalog')->assertSuccessful();

        $this->assertSame(4_500_000, Produk::where('kode', 'LAYANAN-PRO')->value('harga'),
            'Menjalankan ulang perintah katalog mengembalikan harga yang sudah diubah pemiliknya.');
    }

    public function test_katalog_mengirim_layanan_dan_harga_tambahan(): void
    {
        $this->artisan('pembelian:katalog')->assertSuccessful();
        Produk::where('kode', 'WEBSITE')->update(['harga' => 25_000_000, 'aktif' => true]);

        $isi = $this->get('/katalog')->assertOk()->getContent();

        $this->assertStringContainsString('harga_tambahan', $isi,
            'Halaman etalase tidak menerima harga tambahan, sehingga totalnya '
            .'hanya bisa dikalikan — dan akan berbeda dari tagihan yang terbit.');
    }

    /* ═══════════ layar dan tagihan tidak boleh berbeda ═══════════ */

    /**
     * Tidak ada layar yang mengalikan harga dengan banyaknya sendiri.
     *
     * Penjagaan terpenting di berkas ini, dan satu-satunya yang menahan
     * bentuk kegagalan yang paling mahal: pembeli membaca Rp 29 juta,
     * menekan pesan, lalu menerima tagihan Rp 75 juta. Tidak ada galat,
     * tidak ada baris log — keduanya bekerja persis seperti yang
     * ditulis.
     */
    public function test_tidak_ada_layar_yang_mengalikan_harganya_sendiri(): void
    {
        $halaman = ['Katalog.vue', 'Etalase.vue'];
        $langgar = [];

        foreach ($halaman as $berkas) {
            $utuh = file_get_contents(resource_path("js/Pages/Pembelian/{$berkas}"));

            /* Komentarnya dibuang lebih dulu.
             *
               Berkas ini penuh komentar berbaris banyak, yang tiap
               barisnya diawali bintang — dan kata "harganya" pada satu
               baris komentar diikuti bintang pembuka baris berikutnya
               terbaca persis seperti perkalian. Tanpa pembuangan ini
               ujinya menuduh kedua halaman melanggar padahal tidak,
               dan tuduhan palsu mengajari pembacanya mematikan
               penjagaannya. */
            $isi = preg_replace(
                ['#/\*.*?\*/#s', '#<!--.*?-->#s', '#//[^\n]*#'],
                '',
                $utuh,
            ) ?? $utuh;

            /* SETIAP perkalian yang menyentuh harga, bukan pola
               tertentu.
             *
               Mula-mula yang dicari pola `harga * pilih`, dan itu tidak
               menangkap `p.harga * (pilih[p.id] ?? 0)` — bentuk yang
               justru tertulis di berkasnya sebelum diperbaiki. Ujinya
               lulus atas halaman yang mengalikan sendiri, yaitu persis
               keadaan yang ia seharusnya cegah. Sekarang tidak ada
               perkalian apa pun atas harga yang boleh lewat: satu-satunya
               tempat mengalikan ada di hargaBertingkat.ts. */
            if (preg_match('/\bharga\w*\s*\*/i', $isi, $cocok)) {
                $langgar[] = $berkas.' ('.trim($cocok[0]).')';
            }

            $this->assertStringContainsString('subtotalBertingkat', $utuh,
                "{$berkas} tidak memakai subtotalBertingkat; totalnya dihitung "
                .'dengan aturan yang berbeda dari yang menagih.');
        }

        $this->assertSame([], $langgar,
            'Halaman ini mengalikan harga dengan banyaknya sendiri, melewati harga '
            .'bertingkat: '.implode(', ', $langgar));
    }

    /**
     * Aturan di kedua bahasa memberi hasil yang sama.
     *
     * Dicocokkan dengan menjalankan berkas TypeScript-nya sungguhan,
     * bukan dengan membaca ulang rumusnya — rumus yang dibaca ulang
     * oleh uji adalah rumus yang diuji terhadap dirinya sendiri.
     */
    public function test_hitungan_php_dan_typescript_sama(): void
    {
        $node = trim((string) shell_exec('command -v node 2>/dev/null'));

        if ($node === '') $this->markTestSkipped('node tidak tersedia di mesin ini.');

        $p = $this->paketWebsite();
        $berkas = resource_path('js/hargaBertingkat.ts');

        /* Berkas TS-nya dibaca, bagian fungsinya dipotong menjadi JS
           polos — tanpa anotasi tipe — lalu dijalankan node. */
        $ts = file_get_contents($berkas);
        $js = preg_replace('/:\s*number\s*\|\s*null\s*\|\s*undefined/', '', $ts);
        $js = preg_replace('/:\s*(number|string\s*\|\s*null)/', '', $js);
        $js = str_replace(['export function'], ['function'], $js);
        $js = preg_replace('/rupiah:\s*\(n\)\s*=>\s*string,/', 'rupiah,', $js);

        $uji = [];
        foreach ([1, 2, 3, 7] as $n) $uji[] = $n;

        $skrip = $js."\nconsole.log(JSON.stringify(["
            .implode(',', array_map(
                fn ($n) => "subtotalBertingkat(25000000, 2000000, {$n})", $uji))
            ."]));";

        $tmp = tempnam(sys_get_temp_dir(), 'hb').'.mjs';
        file_put_contents($tmp, $skrip);
        $keluar = shell_exec(escapeshellcmd($node).' '.escapeshellarg($tmp).' 2>&1');
        @unlink($tmp);

        $dariJs = json_decode(trim((string) $keluar), true);

        $this->assertIsArray($dariJs,
            "Berkas hargaBertingkat.ts tidak dapat dijalankan: {$keluar}");

        $dariPhp = array_map(fn ($n) => $p->subtotal($n), $uji);

        $this->assertSame($dariPhp, $dariJs,
            'Hitungan di layar berbeda dari hitungan yang menagih. Pembeli akan '
            .'membaca satu angka lalu menerima tagihan yang lain.');
    }
}
