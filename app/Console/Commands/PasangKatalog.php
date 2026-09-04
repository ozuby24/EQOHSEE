<?php

namespace App\Console\Commands;

use App\Models\Pembelian\Produk;
use App\Support\Menu;
use Illuminate\Console\Command;

/**
 * Memasang katalog jual: platformnya, dan tiap aplikasi di dalamnya.
 *
 * ── DITURUNKAN DARI MENU, BUKAN DIKETIK ULANG ──
 *
 * Daftar aplikasinya dibaca dari App\Support\Menu — sumber yang sama
 * dengan yang menggambar bilah samping. Diketik ulang di sini, katalog
 * dan aplikasinya akan berbeda isi cepat atau lambat: modul baru
 * ditambahkan ke menu dan lupa dimasukkan ke katalog, atau modul yang
 * dibuang tetap terjual. Yang kedua lebih buruk — orang membayar
 * sesuatu yang tidak ada.
 *
 * ── HARGANYA TIDAK DITEBAK ──
 *
 * Butir baru dipasang dengan harga NOL dan ditandai tidak aktif. Harga
 * adalah keputusan dagang, bukan keputusan pemrogram, dan angka contoh
 * yang lupa diganti adalah angka yang benar-benar ditagihkan kepada
 * pelanggan. Nol yang tidak aktif tidak dapat terjual tanpa sengaja.
 *
 * Menjalankan ulang TIDAK menimpa harga yang sudah diisi — lihat
 * updateOrCreate di bawah: kolom harga hanya diisi saat barisnya baru.
 */
class PasangKatalog extends Command
{
    protected $signature = 'pembelian:katalog';

    protected $description = 'Pasang katalog produk dari daftar modul aplikasi';

    public function handle(): int
    {
        $baru = 0;
        $ada  = 0;

        /* Paket menyeluruh. Tidak menunjuk satu modul pun — ia mewakili
           platformnya sebagai satu kesatuan. */
        $ada += $this->pasang('WEBSITE', [
            'nama'        => 'Website EQOHSEE — paket menyeluruh',
            'jenis'       => Produk::WEBSITE,
            'modul_kunci' => null,
            'keterangan'  => 'Seluruh aplikasi di dalamnya, pembaruan, dan pendampingan pemasangan.',
            'urutan'      => 0,
        ], $baru);

        $urutan = 10;

        foreach (Menu::all() as $kunci => $modul) {
            /* Dasbor dan Admin tidak dijual terpisah: keduanya bagian
               dari kerangka aplikasi, bukan aplikasi tersendiri.
               Menjualnya berarti menjanjikan sesuatu yang tetap ada
               meski tidak dibeli. */
            if (in_array($kunci, ['dasbor', 'admin', 'personalia'], true)) continue;

            $ada += $this->pasang('APP-'.strtoupper($kunci), [
                'nama'        => $modul['label'],
                'jenis'       => Produk::APLIKASI,
                'modul_kunci' => $kunci,
                'keterangan'  => null,
                'urutan'      => $urutan,
            ], $baru);

            $urutan += 10;
        }

        $this->info("Katalog terpasang: {$baru} butir baru, {$ada} sudah ada.");

        if ($baru > 0) {
            $this->warn('Butir baru berharga nol dan belum aktif. '
                .'Isi harganya lebih dulu, lalu aktifkan — butir tak aktif tidak muncul di katalog.');
        }

        return self::SUCCESS;
    }

    /** @return int 1 bila barisnya memang sudah ada sebelumnya */
    private function pasang(string $kode, array $data, int &$baru): int
    {
        $lama = Produk::where('kode', $kode)->first();

        if ($lama) {
            /* Yang diperbarui hanya keterangannya. Harga, keaktifan, dan
               masa berlakunya milik pemakainya — menjalankan ulang
               perintah ini tidak boleh mengembalikan harga ke nol dan
               diam-diam mematikan seluruh katalog. */
            $lama->update([
                'nama'        => $data['nama'],
                'jenis'       => $data['jenis'],
                'modul_kunci' => $data['modul_kunci'],
                'urutan'      => $data['urutan'],
            ]);

            return 1;
        }

        Produk::create($data + [
            'kode'       => $kode,
            'harga'      => 0,
            'masa_bulan' => 12,
            'aktif'      => false,
        ]);

        $baru++;

        return 0;
    }
}
