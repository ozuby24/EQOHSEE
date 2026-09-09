<?php

namespace App\Console\Commands;

use App\Models\Pembelian\Produk;
use App\Support\{Menu, Modules};
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

        /* Keterangan jualnya diambil dari Modules — daftar yang sama
           dengan yang menggambar halaman depan — supaya katalog dan
           beranda tidak pernah menceritakan aplikasi yang sama dengan
           kalimat berbeda.

           Pencocokannya dikerjakan Modules::perKunciMenu() — satu tempat
           saja, dipakai bersama halaman katalog publik. Disalin ke sini,
           salah satunya akan tertinggal saat aturan pencocokannya
           berubah, dan yang tertinggal biasanya yang tidak pernah dibaca
           lagi. */
        $jual = Modules::perKunciMenu();

        $urutan = 10;
        $hidup  = ['WEBSITE'];

        foreach (Menu::all() as $kunci => $modul) {
            /* Dasbor dan Admin tidak dijual terpisah: keduanya bagian
               dari kerangka aplikasi, bukan aplikasi tersendiri.
               Menjualnya berarti menjanjikan sesuatu yang tetap ada
               meski tidak dibeli.

               'pembelian' ikut dikecualikan karena alasan yang lain lagi:
               ia adalah lorong menuju pembayaran, bukan barang. Menjualnya
               berarti menagih orang untuk hak membayar. */
            if (in_array($kunci, ['dasbor', 'admin', 'personalia', 'pembelian'], true)) continue;

            $hidup[] = 'APP-'.strtoupper($kunci);

            $ada += $this->pasang('APP-'.strtoupper($kunci), [
                /* Nama jualnya dari Modules bila ada — "Authority —
                   Kelayakan Kerja" lebih menjelaskan apa yang dibeli
                   daripada label bilah samping "Miners". */
                'nama'        => $jual[$kunci]['nama'] ?? $modul['label'],
                'jenis'       => Produk::APLIKASI,
                'modul_kunci' => $kunci,
                'keterangan'  => $jual[$kunci]['ket'] ?? null,
                'urutan'      => $urutan,
            ], $baru);

            $urutan += 10;
        }

        /* ── MODUL YANG SUDAH TIDAK ADA BERHENTI DIJUAL ──

           Kebalikan dari pemasangan, dan yang lebih berbahaya dari
           keduanya: modul yang dibuang dari menu tetap muncul di katalog
           dengan harga terpasang, sehingga orang membayar sesuatu yang
           tidak ada lagi. Tidak dihapus — pesanan lama menunjuk barisnya,
           dan menghapusnya membuat tagihan yang sudah terbit kehilangan
           nama barangnya. Cukup dinonaktifkan: tidak muncul di katalog,
           tidak dapat dipesan, riwayatnya utuh. */
        $usang = Produk::whereNotIn('kode', $hidup)->get();

        $dibuang = [];
        $dimatikan = [];

        foreach ($usang as $p) {
            /* Yang belum pernah dipesan DIHAPUS. Ia tidak pernah menjadi
               bagian dari riwayat siapa pun, dan membiarkannya berarti
               daftar harga penjual perlahan berisi baris-baris yang tidak
               dapat dijelaskan kepada orang yang membacanya. */
            if ($p->items()->count() === 0) {
                $dibuang[] = $p->kode;
                $p->delete();

                continue;
            }

            /* Yang pernah dipesan hanya dinonaktifkan. Menghapusnya
               membuat tagihan yang sudah terbit kehilangan rujukan
               barangnya — dan tagihan tanpa nama barang adalah tagihan
               yang tidak dapat dipertanggungjawabkan kepada pembelinya. */
            if ($p->aktif) {
                $dimatikan[] = $p->kode;
                $p->update(['aktif' => false]);
            }
        }

        $this->info("Katalog terpasang: {$baru} butir baru, {$ada} sudah ada.");

        if ($dibuang) {
            $this->warn('Dibuang karena modulnya sudah tidak ada dan belum pernah dipesan: '
                .implode(', ', $dibuang).'.');
        }

        if ($dimatikan) {
            $this->warn('Dinonaktifkan karena modulnya sudah tidak ada, '
                .'tetapi pernah dipesan sehingga barisnya dipertahankan: '
                .implode(', ', $dimatikan).'.');
        }

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
                'keterangan'  => $data['keterangan'],
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
