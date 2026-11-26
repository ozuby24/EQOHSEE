<?php

namespace Tests\Feature;

use App\Models\Pembelian\{Item, Lisensi, Pesanan, Produk};
use App\Support\Pembelian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Model harga yang tidak menyesatkan.
 *
 * Keputusan pemiliknya: website SEKALI BAYAR dan permanen; yang tahunan
 * hanya server (mulai Rp 3.000.000) dan pemeliharaan Rp 2.000.000 untuk
 * setiap kelipatan tiga modul, di luar server. Satu sampai tiga modul
 * bersama server dasar: Rp 5.000.000 setahun.
 */
class ModelHargaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('pembelian:katalog')->assertSuccessful();
        Produk::where('kode', 'WEBSITE')->update(['harga' => 69_000_000, 'aktif' => true]);
    }

    private function produk(string $kode): Produk
    {
        return Produk::where('kode', $kode)->firstOrFail();
    }

    /** Dua aplikasi satuan pertama yang ada di katalog, dihargai dan diaktifkan. */
    private function aplikasi(int $n): array
    {
        $daftar = Produk::where('jenis', Produk::APLIKASI)->orderBy('urutan')->take($n)->get();
        foreach ($daftar as $p) $p->update(['harga' => 10_000_000, 'aktif' => true]);

        return $daftar->all();
    }

    private function pesan(array $produk, array $lebih = []): Pesanan
    {
        $this->post('/katalog/pesan', $lebih + ['pembeli_nama' => 'Calon Pembeli', 'produk' => $produk])
            ->assertSessionHasNoErrors();

        return Pesanan::latest('id')->firstOrFail();
    }

    private function pemeliharaan(Pesanan $p): ?Item
    {
        return $p->items()->where('produk_id', $this->produk('PEMELIHARAAN')->id)->first();
    }

    public function test_website_sekali_bayar_dan_permanen(): void
    {
        $web = $this->produk('WEBSITE');

        $this->assertSame(0, $web->masa_bulan);
        $this->assertSame('Sekali bayar · permanen', $web->masaBerlaku());
    }

    public function test_migrasi_menjadikan_website_lama_permanen(): void
    {
        $this->produk('WEBSITE')->update(['masa_bulan' => 12]);

        $migrasi = require database_path('migrations/2026_11_26_200004_website_permanen_dan_pemeliharaan.php');
        $migrasi->up();

        $this->assertSame(0, $this->produk('WEBSITE')->masa_bulan);
    }

    public function test_pemeliharaan_terpasang_dua_juta_per_tiga_modul(): void
    {
        $p = $this->produk('PEMELIHARAAN');

        $this->assertSame([2_000_000, 3, 12, true], [$p->harga, $p->modul_per_blok, $p->masa_bulan, $p->aktif]);
        $this->assertSame('Per tahun', $p->masaBerlaku());

        foreach ([0 => 0, 1 => 1, 3 => 1, 4 => 2, 6 => 2, 7 => 3, 29 => 10] as $modul => $blok) {
            $this->assertSame($blok, $p->blokPemeliharaan($modul), "{$modul} modul");
        }
    }

    /** 1–3 modul bersama server dasar: Rp 5.000.000 setahun, di luar harga modulnya. */
    public function test_tiga_modul_dan_server_lima_juta_setahun(): void
    {
        $app = $this->aplikasi(3);
        $server = $this->produk('LAYANAN-PRO');

        $pesanan = $this->pesan([$app[0]->id => 1, $app[1]->id => 1, $app[2]->id => 1, $server->id => 1]);

        $pelihara = $this->pemeliharaan($pesanan);
        $this->assertNotNull($pelihara, 'Pemeliharaan tidak ikut ditagihkan.');
        $this->assertSame(2_000_000, $pelihara->subtotal);
        $this->assertStringContainsString('3 modul', $pelihara->nama);

        $tahunan = $pelihara->subtotal + $pesanan->items()->where('produk_id', $server->id)->value('subtotal');
        $this->assertSame(5_000_000, $tahunan);
        $this->assertSame(30_000_000 + 5_000_000, $pesanan->total);
    }

    public function test_empat_modul_naik_menjadi_dua_kelipatan(): void
    {
        $app = $this->aplikasi(4);

        $pesanan = $this->pesan(collect($app)->mapWithKeys(fn ($p) => [$p->id => 1])->all());

        $this->assertSame(4_000_000, $this->pemeliharaan($pesanan)->subtotal);
        $this->assertSame(2, $this->pemeliharaan($pesanan)->jumlah);
    }

    /** Paket menyeluruh membawa seluruh modulnya; aplikasi yang ikut dipilih tidak dihitung lagi. */
    public function test_paket_menyeluruh_tidak_menghitung_modul_dua_kali(): void
    {
        $web = $this->produk('WEBSITE');
        $app = $this->aplikasi(2);

        $pesanan = $this->pesan([$web->id => 1, $app[0]->id => 1, $app[1]->id => 1]);

        $modul = Pembelian::modulPaket();
        $this->assertSame(2_000_000 * (int) ceil($modul / 3), $this->pemeliharaan($pesanan)->subtotal);
        $this->assertStringContainsString($modul.' modul', $this->pemeliharaan($pesanan)->nama);
    }

    /** Banyaknya kelipatan tidak pernah diterima dari layar. */
    public function test_pemeliharaan_kiriman_layar_diabaikan(): void
    {
        $app = $this->aplikasi(6);
        $pelihara = $this->produk('PEMELIHARAAN');

        $produk = collect($app)->mapWithKeys(fn ($p) => [$p->id => 1])->all() + [$pelihara->id => 1];
        $pesanan = $this->pesan($produk);

        $this->assertSame(1, $pesanan->items()->where('produk_id', $pelihara->id)->count(), 'Pemeliharaan tertagih dua kali.');
        $this->assertSame(4_000_000, $this->pemeliharaan($pesanan)->subtotal, '6 modul semestinya dua kelipatan.');
    }

    public function test_perpanjangan_server_saja_tanpa_pemeliharaan(): void
    {
        $pesanan = $this->pesan([$this->produk('LAYANAN-PRO')->id => 1]);

        $this->assertNull($this->pemeliharaan($pesanan));
        $this->assertSame(3_000_000, $pesanan->total);
    }

    public function test_lisensi_website_tidak_berakhir(): void
    {
        $pesanan = $this->pesan([$this->produk('WEBSITE')->id => 1]);

        Pembelian::terbitkanLisensi($pesanan);

        $this->assertNull(Lisensi::where('produk_id', $this->produk('WEBSITE')->id)->value('berakhir'));
    }

    public function test_etalase_menerima_tarif_pemeliharaan_dan_banyaknya_modul_paket(): void
    {
        $this->get('/katalog')->assertOk()->assertInertia(fn ($h) => $h
            ->where('pemeliharaan.harga', 2_000_000)
            ->where('pemeliharaan.modulPerBlok', 3)
            ->where('modulPaket', Pembelian::modulPaket())
            ->where('paket.masa', 'Sekali bayar · permanen'));
    }

    public function test_hitungan_php_dan_typescript_sama(): void
    {
        $node = trim((string) shell_exec('command -v node 2>/dev/null'));
        if ($node === '') $this->markTestSkipped('node tidak tersedia di mesin ini.');

        $p = $this->produk('PEMELIHARAAN');

        $ts = file_get_contents(resource_path('js/hargaBertingkat.ts'));
        $js = preg_replace('/:\s*number\s*\|\s*null\s*\|\s*undefined/', '', $ts);
        $js = preg_replace('/:\s*(number|string\s*\|\s*null)/', '', $js);
        $js = str_replace('export function', 'function', $js);
        $js = preg_replace('/rupiah:\s*\(n\)\s*=>\s*string,/', 'rupiah,', $js);

        $modul = [0, 1, 2, 3, 4, 6, 7, 29, 58];
        $skrip = $js."\nconsole.log(JSON.stringify(["
            .implode(',', array_map(fn ($m) => "subtotalPemeliharaan(2000000, 3, {$m})", $modul)).']));';

        $tmp = tempnam(sys_get_temp_dir(), 'mh').'.mjs';
        file_put_contents($tmp, $skrip);
        $keluar = shell_exec(escapeshellcmd($node).' '.escapeshellarg($tmp).' 2>&1');
        @unlink($tmp);

        $dariJs = json_decode(trim((string) $keluar), true);
        $this->assertIsArray($dariJs, "hargaBertingkat.ts tidak dapat dijalankan: {$keluar}");

        $dariPhp = array_map(fn ($m) => $p->harga * $p->blokPemeliharaan($m), $modul);
        $this->assertSame($dariPhp, $dariJs, 'Layar dan tagihan memberi biaya pemeliharaan yang berbeda.');
    }
}
