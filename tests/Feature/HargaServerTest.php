<?php

namespace Tests\Feature;

use App\Console\Commands\PasangKatalog;
use App\Models\Pembelian\{Item, Pesanan, Produk};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Server Professional yang spesifikasinya dipilih pembeli.
 *
 * Keputusan pemiliknya: dasar 1 vCPU, RAM 1 GB, NVMe 60 GB, HTTPS dan
 * seterusnya Rp 3.000.000 setahun; setiap vCPU tambahan Rp 500.000,
 * setiap GB RAM tambahan Rp 500.000. Lima aplikasi pada 4 vCPU dan RAM
 * 4 GB karena itu Rp 6.000.000.
 *
 * Sama seperti harga bertingkat, yang paling dijaga adalah bahwa angka
 * yang dibaca pembeli di layar sama dengan yang ditagihkan.
 */
class HargaServerTest extends TestCase
{
    use RefreshDatabase;

    private function server(): Produk
    {
        return Produk::create([
            'kode' => 'LAYANAN-PRO', 'nama' => 'Professional — server, hosting & perpanjangan',
            'jenis' => Produk::LAYANAN, 'harga' => 3_000_000,
            'harga_cpu' => 500_000, 'harga_ram' => 500_000,
            'masa_bulan' => 12, 'aktif' => true, 'urutan' => 5,
        ]);
    }

    public function test_dasar_tiga_juta_dan_tiap_tambahan_lima_ratus_ribu(): void
    {
        $p = $this->server();

        $this->assertSame(3_000_000, $p->hargaSatuan(1, 1));
        $this->assertSame(3_500_000, $p->hargaSatuan(2, 1));
        $this->assertSame(4_000_000, $p->hargaSatuan(1, 3));
        $this->assertSame(6_000_000, $p->hargaSatuan(4, 4), 'Lima aplikasi pada 4 vCPU / 4 GB semestinya Rp 6 juta.');
        $this->assertSame(12_000_000, $p->subtotal(2, 4, 4), 'Dua tahun dikalikan banyaknya.');
    }

    public function test_spesifikasi_di_luar_batas_ditolak_bukan_dipotong(): void
    {
        $p = $this->server();

        foreach ([[0, 1], [Produk::CPU_MAKS + 1, 1], [1, 0], [1, Produk::RAM_MAKS + 1]] as [$cpu, $ram]) {
            try {
                $p->hargaSatuan($cpu, $ram);
                $this->fail("Spesifikasi {$cpu} vCPU / {$ram} GB diterima.");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_pesanan_menyimpan_spesifikasi_dan_harganya(): void
    {
        $p = $this->server();

        $this->post('/katalog/pesan', [
            'pembeli_nama' => 'Calon Pembeli',
            'produk'       => [$p->id => 1],
            'spesifikasi'  => [$p->id => ['cpu' => 4, 'ram' => 4]],
        ])->assertSessionHasNoErrors();

        $item = Item::firstOrFail();

        $this->assertSame(6_000_000, $item->harga, 'Harga satu butir harus sesuai spesifikasinya.');
        $this->assertSame(6_000_000, $item->subtotal);
        $this->assertSame([4, 4, 500_000, 500_000], [$item->cpu, $item->ram, $item->harga_cpu, $item->harga_ram]);
        $this->assertStringContainsString('4 vCPU · RAM 4 GB', $item->nama);
        $this->assertSame(6_000_000, Pesanan::firstOrFail()->total);
    }

    /** Harga dihitung peladen dari tarifnya — layar hanya mengirim banyaknya vCPU dan GB. */
    public function test_harga_kiriman_layar_diabaikan(): void
    {
        $p = $this->server();

        $this->post('/katalog/pesan', [
            'pembeli_nama' => 'Calon Pembeli',
            'produk'       => [$p->id => 1],
            'spesifikasi'  => [$p->id => ['cpu' => 2, 'ram' => 2, 'harga' => 1]],
            'total'        => 1,
        ]);

        $this->assertSame(4_000_000, Pesanan::firstOrFail()->total);
    }

    public function test_spesifikasi_di_luar_batas_ditolak_saat_memesan(): void
    {
        $p = $this->server();

        $this->post('/katalog/pesan', [
            'pembeli_nama' => 'Calon Pembeli',
            'produk'       => [$p->id => 1],
            'spesifikasi'  => [$p->id => ['cpu' => Produk::CPU_MAKS + 1, 'ram' => 1]],
        ])->assertSessionHasErrors('spesifikasi.'.$p->id.'.cpu');

        $this->assertSame(0, Pesanan::count());
    }

    /** Tanpa spesifikasi, server dipesan pada dasarnya — bukan ditolak. */
    public function test_tanpa_spesifikasi_dipesan_pada_dasarnya(): void
    {
        $p = $this->server();

        $this->post('/katalog/pesan', ['pembeli_nama' => 'Calon Pembeli', 'produk' => [$p->id => 1]]);

        $this->assertSame([1, 1, 3_000_000], [Item::first()->cpu, Item::first()->ram, Item::first()->subtotal]);
    }

    /** Butir yang tidak dapat diatur mengabaikan spesifikasi yang terkirim. */
    public function test_butir_lain_tidak_terpengaruh_spesifikasi(): void
    {
        $web = Produk::create(['kode' => 'WEBSITE', 'nama' => 'Website', 'jenis' => Produk::WEBSITE,
            'harga' => 69_000_000, 'harga_tambahan' => 3_000_000, 'masa_bulan' => 12, 'aktif' => true, 'urutan' => 0]);

        $this->post('/katalog/pesan', [
            'pembeli_nama' => 'Calon Pembeli',
            'produk'       => [$web->id => 2],
            'spesifikasi'  => [$web->id => ['cpu' => 4, 'ram' => 4]],
        ]);

        $item = Item::firstOrFail();
        $this->assertSame(72_000_000, $item->subtotal);
        $this->assertNull($item->cpu);
        $this->assertSame('Website', $item->nama);
    }

    /** Tarif yang diubah kemudian tidak mengubah tagihan yang sudah terbit. */
    public function test_tagihan_lama_tidak_berubah_saat_tarif_berubah(): void
    {
        $p = $this->server();
        $this->post('/katalog/pesan', [
            'pembeli_nama' => 'Calon Pembeli', 'produk' => [$p->id => 1],
            'spesifikasi' => [$p->id => ['cpu' => 2, 'ram' => 2]],
        ]);

        $p->update(['harga_cpu' => 900_000, 'harga_ram' => 900_000]);

        $item = Item::firstOrFail()->refresh();
        $this->assertSame([4_000_000, 500_000, 500_000], [$item->subtotal, $item->harga_cpu, $item->harga_ram]);
    }

    public function test_katalog_dan_migrasi_memasang_tarif_yang_sama(): void
    {
        $this->artisan('pembelian:katalog')->assertSuccessful();

        $layanan = Produk::where('kode', 'LAYANAN-PRO')->firstOrFail();
        $this->assertSame([3_000_000, 500_000, 500_000], [$layanan->harga, $layanan->harga_cpu, $layanan->harga_ram]);
        $this->assertSame(500_000, PasangKatalog::HARGA_PER_CPU);

        // Pemasangan lama: barisnya ada tanpa tarif tambahan.
        $layanan->update(['harga_cpu' => null, 'harga_ram' => null]);
        $migrasi = require database_path('migrations/2026_11_26_200003_harga_server_dapat_diatur.php');
        \Illuminate\Support\Facades\Schema::table('beli_produk', fn ($t) => $t->dropColumn(['harga_cpu', 'harga_ram']));
        \Illuminate\Support\Facades\Schema::table('beli_item', fn ($t) => $t->dropColumn(['harga_cpu', 'harga_ram', 'cpu', 'ram']));
        $migrasi->up();

        $this->assertSame([500_000, 500_000], [$layanan->fresh()->harga_cpu, $layanan->fresh()->harga_ram]);
    }

    /** Layar memberi angka yang sama dengan peladen untuk setiap spesifikasi. */
    public function test_hitungan_php_dan_typescript_sama(): void
    {
        $node = trim((string) shell_exec('command -v node 2>/dev/null'));
        if ($node === '') $this->markTestSkipped('node tidak tersedia di mesin ini.');

        $p = $this->server();

        $ts = file_get_contents(resource_path('js/hargaBertingkat.ts'));
        $js = preg_replace('/:\s*number\s*\|\s*null\s*\|\s*undefined/', '', $ts);
        $js = preg_replace('/:\s*(number|string\s*\|\s*null)/', '', $js);
        $js = str_replace('export function', 'function', $js);
        $js = preg_replace('/rupiah:\s*\(n\)\s*=>\s*string,/', 'rupiah,', $js);

        $kasus = [[1, 1, 1], [2, 1, 1], [1, 3, 1], [4, 4, 1], [8, 16, 1], [4, 4, 2], [3, 5, 3]];

        $skrip = $js."\nconsole.log(JSON.stringify(["
            .implode(',', array_map(fn ($k) => "subtotalServer(3000000, 500000, 500000, {$k[0]}, {$k[1]}, {$k[2]})", $kasus))
            .']));';

        $tmp = tempnam(sys_get_temp_dir(), 'hs').'.mjs';
        file_put_contents($tmp, $skrip);
        $keluar = shell_exec(escapeshellcmd($node).' '.escapeshellarg($tmp).' 2>&1');
        @unlink($tmp);

        $dariJs = json_decode(trim((string) $keluar), true);
        $this->assertIsArray($dariJs, "hargaBertingkat.ts tidak dapat dijalankan: {$keluar}");

        $dariPhp = array_map(fn ($k) => $p->subtotal($k[2], $k[0], $k[1]), $kasus);

        $this->assertSame($dariPhp, $dariJs, 'Layar dan tagihan memberi harga server yang berbeda.');
    }
}
