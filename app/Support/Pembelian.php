<?php

namespace App\Support;

use App\Models\Pembelian\{Item, Lisensi, Pesanan, Produk};
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Aturan pembelian: menyusun tagihan, dan menerbitkan lisensinya.
 *
 * ── HARGA DIAMBIL DARI BASIS DATA, TITIK ──
 *
 * `buat()` hanya menerima daftar ID produk dan jumlahnya. Harga tidak
 * pernah ikut dikirim dari layar, dan bahkan tidak dibaca dari
 * permintaan sama sekali. Sekali harga boleh datang dari klien, memesan
 * sepuluh aplikasi seharga nol rupiah cukup dengan menyunting satu
 * medan tersembunyi — dan tagihannya akan terlihat wajar sepenuhnya di
 * layar admin, sebab angka nol itu memang yang tersimpan.
 *
 * ── LISENSI TERBIT SEKALI, DAN HANYA SESUDAH LUNAS ──
 *
 * `terbitkanLisensi()` melewati produk yang lisensinya sudah ada pada
 * pesanan itu. Tanpa itu, menekan tombol verifikasi dua kali — atau
 * menyegarkan halaman sesudahnya — menerbitkan dua lisensi untuk satu
 * pembayaran, dan yang kedua tidak dapat dibedakan dari yang sah.
 */
final class Pembelian
{
    /** @return array<string, list<Produk>> katalog per jenis */
    public static function katalog(): array
    {
        return Produk::aktif()->orderBy('urutan')->orderBy('nama')->get()
            ->groupBy('jenis')->map->values()->all();
    }

    /**
     * Susun tagihan dari daftar produk.
     *
     * @param  array<int,int>  $jumlahPerProduk  id produk => banyaknya
     */
    public static function buat(array $pembeli, array $jumlahPerProduk, ?User $oleh = null): Pesanan
    {
        $pesanan = new Pesanan($pembeli);
        $pesanan->dibuat_oleh = $oleh?->getKey();
        $pesanan->save();

        /* Nomor terbit SESUDAH tersimpan supaya idnya sudah ada.
           Nomor yang dibentuk sebelum penyimpanan harus menebak id
           berikutnya, dan tebakan itu meleset begitu dua orang memesan
           pada detik yang sama. */
        NomorRegister::terbitkan($pesanan, 'no_pesanan',
            fn ($p) => NomorRegister::berawalan('INV', $p->id));

        foreach ($jumlahPerProduk as $produkId => $jumlah) {
            $jumlah = max(1, (int) $jumlah);
            $produk = Produk::aktif()->find($produkId);

            /* Produk yang tidak aktif atau tidak ada DILEWATI diam-diam,
               bukan membatalkan seluruh pesanan. Katalog dapat berubah
               di antara saat halaman dibuka dan saat tombolnya ditekan,
               dan menggugurkan seluruh pesanan karena satu butir yang
               baru saja dinonaktifkan menghukum pembeli atas keputusan
               penjualnya. */
            if (! $produk) continue;

            Item::create([
                'pesanan_id' => $pesanan->id,
                'produk_id'  => $produk->id,
                'nama'       => $produk->nama,
                'harga'      => $produk->harga,
                'masa_bulan' => $produk->masa_bulan,
                'jumlah'     => $jumlah,
                'subtotal'   => $produk->harga * $jumlah,
            ]);
        }

        $pesanan->hitungUlang();

        return $pesanan->refresh();
    }

    /**
     * Kirim tagihan: statusnya berpindah, dan tenggatnya mulai berjalan.
     *
     * Pesanan tanpa satu baris pun tidak dapat dikirim. Tagihan nol
     * rupiah yang dapat "dibayar" akan menerbitkan lisensi tanpa uang
     * masuk — dan karena angkanya memang nol, tidak ada yang janggal
     * terlihat di layar mana pun.
     */
    public static function kirim(Pesanan $pesanan): bool
    {
        if ($pesanan->items()->count() < 1) return false;
        if ($pesanan->status !== Pesanan::DRAF) return false;

        $pesanan->forceFill([
            'status' => Pesanan::MENUNGGU_BAYAR,
            'kedaluwarsa_pada' => now()->addHours(
                max(1, (int) config('pembelian.kedaluwarsa_jam', 48))),
        ])->save();

        return true;
    }

    /**
     * Tandai lunas dan terbitkan lisensinya.
     *
     * Pemanggilnya WAJIB sudah memastikan orang yang menekan berhak.
     * Kelas ini tidak memeriksa peran: memeriksanya di dua tempat
     * membuat salah satunya tertinggal saat aturannya berubah, dan yang
     * tertinggal biasanya yang tidak pernah dibaca lagi.
     */
    public static function tandaiLunas(Pesanan $pesanan, User $oleh): void
    {
        $pesanan->forceFill([
            'status' => Pesanan::LUNAS,
            'diverifikasi_oleh' => $oleh->getKey(),
            'diverifikasi_pada' => now(),
            'alasan_tolak' => null,
        ])->save();

        self::terbitkanLisensi($pesanan);
    }

    public static function tolak(Pesanan $pesanan, User $oleh, string $alasan): void
    {
        $pesanan->forceFill([
            'status' => Pesanan::DITOLAK,
            'diverifikasi_oleh' => $oleh->getKey(),
            'diverifikasi_pada' => now(),
            'alasan_tolak' => $alasan,
        ])->save();
    }

    /**
     * Terbitkan satu lisensi per baris tagihan, sekali saja.
     *
     * @return int jumlah lisensi baru
     */
    public static function terbitkanLisensi(Pesanan $pesanan): int
    {
        $sudah = $pesanan->lisensi()->pluck('produk_id')->filter()->all();
        $n = 0;

        foreach ($pesanan->items()->with('produk')->get() as $item) {
            if ($item->produk_id === null) continue;
            if (in_array($item->produk_id, $sudah, true)) continue;

            $mulai = Carbon::today();

            Lisensi::create([
                'pesanan_id'  => $pesanan->id,
                'produk_id'   => $item->produk_id,
                'company_id'  => $pesanan->company_id,
                'kunci'       => self::kunci(),
                'modul_kunci' => $item->produk?->modul_kunci,
                'mulai'       => $mulai,

                /* Nol bulan berarti selamanya, dan itu ditulis sebagai
                   null — bukan sebagai tanggal yang sangat jauh. Tanggal
                   jauh tetap tanggal, dan suatu hari ia lewat. */
                'berakhir'    => $item->masa_bulan > 0
                    ? $mulai->copy()->addMonthsNoOverflow($item->masa_bulan)
                    : null,
                'aktif'       => true,
            ]);

            $sudah[] = $item->produk_id;
            $n++;
        }

        return $n;
    }

    /**
     * Kunci lisensi yang dapat dibacakan lewat telepon.
     *
     * Tanpa huruf I, O, angka 1 dan 0 — keempatnya tertukar saat
     * didikte, dan kunci yang salah satu hurufnya keliru tidak dapat
     * dibedakan dari kunci palsu oleh orang yang menerimanya.
     */
    public static function kunci(): string
    {
        $abjad = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $blok = [];

        for ($b = 0; $b < 4; $b++) {
            $s = '';
            for ($i = 0; $i < 4; $i++) $s .= $abjad[random_int(0, strlen($abjad) - 1)];
            $blok[] = $s;
        }

        return implode('-', $blok);
    }

    /**
     * Keterangan tujuan pembayaran untuk satu tagihan.
     *
     * QRIS dinamis dicoba lebih dulu; bila kode statisnya belum diatur
     * atau tidak lolos pemeriksaan CRC, yang dipulangkan menyebutkan
     * SEBABNYA. Layar yang hanya menerima "tidak ada QR" akan menggambar
     * kotak kosong, dan yang membacanya menyimpulkan aplikasinya rusak
     * alih-alih konfigurasinya belum diisi.
     *
     * @return array<string,mixed>
     */
    public static function tujuanBayar(Pesanan $pesanan): array
    {
        $statis = trim((string) config('pembelian.qris_statis', ''));
        $bank   = config('pembelian.bank', []);

        $qris = [
            'ada'      => false,
            'payload'  => null,
            'dinamis'  => false,
            'merchant' => null,
            'sebab'    => null,
        ];

        if ($statis === '') {
            $qris['sebab'] = 'Kode QRIS belum diatur. Isi QRIS_STATIS pada berkas .env '
                .'dengan kode QRIS statis milik merchant.';
        } elseif (! Qris::sah($statis)) {
            $qris['sebab'] = 'Kode QRIS pada konfigurasi tidak lolos pemeriksaan CRC — '
                .'biasanya karena terpotong saat disalin. Salin ulang seluruh kodenya.';
        } else {
            $dinamis = Qris::dinamis($statis, (int) $pesanan->total);

            $qris = [
                'ada'      => true,
                'payload'  => $dinamis ?? $statis,
                'dinamis'  => $dinamis !== null,
                'merchant' => Qris::merchant($statis),
                'sebab'    => $dinamis === null
                    ? 'Nominal tidak dapat disisipkan ke kode ini — masukkan nominalnya sendiri saat memindai.'
                    : null,
            ];
        }

        return [
            'qris' => $qris,
            'bank' => [
                'nama'      => $bank['nama'] ?? null,
                'rekening'  => ($bank['rekening'] ?? '') !== '' ? $bank['rekening'] : null,
                'atasNama'  => ($bank['atas_nama'] ?? '') !== '' ? $bank['atas_nama'] : null,
                'ada'       => ($bank['rekening'] ?? '') !== '',
            ],
        ];
    }
}
