<?php

namespace App\Models\Pembelian;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu butir yang dapat dibeli: platformnya, atau satu aplikasi di dalamnya.
 *
 * TIDAK BERLINGKUP PERUSAHAAN. Katalog adalah daftar harga penjual,
 * bukan data pelanggan; menyaringnya per perusahaan akan membuat calon
 * pembeli yang belum punya perusahaan melihat katalog kosong.
 */
class Produk extends Model
{
    protected $table = 'beli_produk';

    public const WEBSITE  = 'website';
    public const APLIKASI = 'aplikasi';

    /**
     * Layanan berjalan: server, hosting, dan perpanjangan tahunan.
     *
     * Jenis ketiga, bukan dipaksakan menjadi 'aplikasi'. Aplikasi adalah
     * modul di dalam website yang sekali dibeli menjadi milik pembelinya;
     * layanan adalah biaya yang datang lagi setiap tahun dan berhenti
     * bila tidak dibayar. Menyatukan keduanya membuat halaman katalog
     * menjanjikan "beli aplikasi ini" untuk sesuatu yang sebenarnya
     * sewa — dan itu bukan kekeliruan tampilan melainkan kekeliruan
     * yang baru ketahuan setahun kemudian.
     */
    public const LAYANAN = 'layanan';

    /**
     * Pemeliharaan tahunan: harganya per KELIPATAN modul (modul_per_blok),
     * bukan per butir. Banyaknya kelipatan dihitung peladen dari modul
     * dalam pesanan yang sama — lihat Pembelian::buat().
     */
    public const PEMELIHARAAN = 'pemeliharaan';

    protected $fillable = [
        'kode', 'nama', 'jenis', 'modul_kunci', 'keterangan',
        'harga', 'harga_tambahan', 'harga_cpu', 'harga_ram', 'modul_per_blok', 'masa_bulan', 'aktif', 'urutan',
    ];

    /* ── Server yang dapat diatur ──
       Spesifikasi dasar sudah termasuk dalam `harga`; yang ditagih
       tambahan hanyalah vCPU dan GB RAM di atas dasarnya. Batas atasnya
       batas yang ditawarkan di layar — permintaan di luarnya ditolak,
       bukan dipotong diam-diam. resources/js/hargaBertingkat.ts memakai
       dasar yang sama; uji HargaServerTest menjaga keduanya. */
    public const CPU_DASAR = 1;
    public const RAM_DASAR = 1;   // GB
    public const CPU_MAKS  = 8;
    public const RAM_MAKS  = 16;  // GB

    protected function casts(): array
    {
        return [
            'harga'          => 'integer',
            'harga_tambahan' => 'integer',
            'harga_cpu'      => 'integer',
            'harga_ram'      => 'integer',
            'modul_per_blok' => 'integer',
            'masa_bulan'     => 'integer',
            'aktif'          => 'boolean',
        ];
    }

    /** Butir kedua dan seterusnya punya harganya sendiri. */
    public function bertingkat(): bool
    {
        return $this->harga_tambahan !== null;
    }

    /**
     * Berapa yang ditagihkan untuk sekian banyaknya.
     *
     * SATU-SATUNYA tempat aturan ini ditulis di sisi peladen. Disalin ke
     * controller atau ke Pembelian::buat, salah satunya akan tertinggal
     * saat aturannya berubah — dan yang tertinggal adalah yang menagih
     * orang, bukan yang menampilkan angka di layar.
     */
    public function subtotal(int $jumlah, int $cpu = self::CPU_DASAR, int $ram = self::RAM_DASAR): int
    {
        $jumlah = max(1, $jumlah);

        /* Server yang dapat diatur: harga satu tahun menurut spesifikasinya,
           dikalikan banyaknya. Harga bertingkat tidak berlaku di sini —
           keduanya aturan untuk barang yang berbeda. */
        if ($this->dapatDiatur()) return $this->hargaSatuan($cpu, $ram) * $jumlah;

        if (! $this->bertingkat()) return $this->harga * $jumlah;

        return $this->harga + ($this->harga_tambahan * ($jumlah - 1));
    }

    /**
     * Banyaknya kelipatan pemeliharaan untuk sekian modul: 1–3 modul satu
     * kelipatan, 4–6 dua, dan seterusnya. Nol modul, nol kelipatan.
     */
    public function blokPemeliharaan(int $modul): int
    {
        if ($modul <= 0) return 0;

        return (int) ceil($modul / max(1, (int) ($this->modul_per_blok ?? 1)));
    }

    /** Spesifikasi server (vCPU, RAM) dapat dipilih pembelinya. */
    public function dapatDiatur(): bool
    {
        return $this->harga_cpu !== null || $this->harga_ram !== null;
    }

    /**
     * Harga satu butir menurut spesifikasinya: dasar + vCPU tambahan +
     * GB RAM tambahan. Spesifikasi di luar batas DITOLAK di sini juga,
     * bukan hanya di validasi permintaan — angka yang dipotong diam-diam
     * menagih spesifikasi yang tidak pernah diminta siapa pun.
     */
    public function hargaSatuan(int $cpu = self::CPU_DASAR, int $ram = self::RAM_DASAR): int
    {
        if (! $this->dapatDiatur()) return $this->harga;

        if ($cpu < self::CPU_DASAR || $cpu > self::CPU_MAKS || $ram < self::RAM_DASAR || $ram > self::RAM_MAKS) {
            throw new \InvalidArgumentException("Spesifikasi server di luar batas: {$cpu} vCPU, RAM {$ram} GB.");
        }

        return $this->harga
            + (int) $this->harga_cpu * ($cpu - self::CPU_DASAR)
            + (int) $this->harga_ram * ($ram - self::RAM_DASAR);
    }

    /** Nama butir pada tagihan, lengkap dengan spesifikasi yang dipesan. */
    public function namaDenganSpesifikasi(int $cpu, int $ram): string
    {
        return $this->dapatDiatur()
            ? sprintf('%s (%d vCPU · RAM %d GB)', $this->nama, $cpu, $ram)
            : $this->nama;
    }

    /** Batas dan tarif tambahan yang dikirim ke layar konfigurasi. */
    public function aturanServer(): ?array
    {
        if (! $this->dapatDiatur()) return null;

        return [
            'cpuDasar' => self::CPU_DASAR, 'cpuMaks' => self::CPU_MAKS,
            'ramDasar' => self::RAM_DASAR, 'ramMaks' => self::RAM_MAKS,
            'hargaCpu' => (int) $this->harga_cpu, 'hargaRam' => (int) $this->harga_ram,
        ];
    }

    public function scopeAktif($q) { return $q->where('aktif', true); }

    /**
     * Baris tagihan yang pernah menunjuk produk ini.
     *
     * Dipakai pembelian:katalog untuk memutuskan apakah sebuah produk
     * usang boleh dihapus atau hanya dinonaktifkan: yang pernah dipesan
     * tidak boleh hilang, sebab tagihan tanpa rujukan barangnya adalah
     * tagihan yang tidak dapat dipertanggungjawabkan kepada pembelinya.
     */
    public function items() { return $this->hasMany(Item::class, 'produk_id'); }

    /** Jenisnya sebagai kata yang dapat dibaca orang. */
    public function jenisSebutan(): string
    {
        return match ($this->jenis) {
            self::WEBSITE  => 'Paket website',
            self::LAYANAN  => 'Layanan tahunan',
            default        => 'Aplikasi',
        };
    }

    /** Masa berlaku sebagai kalimat, bukan angka telanjang. */
    public function masaBerlaku(): string
    {
        /* Website: lisensi permanen. Dikatakan sebagai "sekali bayar"
           karena itulah yang ditanyakan pembeli — "berlaku selamanya"
           saja masih menyisakan pertanyaan apakah ada tagihan tahunan
           untuk website itu sendiri. */
        if ($this->masa_bulan === 0 && $this->jenis === self::WEBSITE) return 'Sekali bayar · permanen';

        if ($this->masa_bulan === 0)  return 'Berlaku selamanya';

        if ($this->masa_bulan === 12) {
            /* Layanan diperpanjang, aplikasi berlaku. Kalimat yang sama
               untuk keduanya membuat biaya yang datang lagi tiap tahun
               terbaca seperti pembelian sekali jalan. */
            return in_array($this->jenis, [self::LAYANAN, self::PEMELIHARAAN], true) ? 'Per tahun' : 'Berlaku 1 tahun';
        }

        return 'Berlaku '.$this->masa_bulan.' bulan';
    }
}
