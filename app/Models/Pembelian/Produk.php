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

    protected $fillable = [
        'kode', 'nama', 'jenis', 'modul_kunci', 'keterangan',
        'harga', 'harga_tambahan', 'masa_bulan', 'aktif', 'urutan',
    ];

    protected function casts(): array
    {
        return [
            'harga'          => 'integer',
            'harga_tambahan' => 'integer',
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
    public function subtotal(int $jumlah): int
    {
        $jumlah = max(1, $jumlah);

        if (! $this->bertingkat()) return $this->harga * $jumlah;

        return $this->harga + ($this->harga_tambahan * ($jumlah - 1));
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
        if ($this->masa_bulan === 0)  return 'Berlaku selamanya';

        if ($this->masa_bulan === 12) {
            /* Layanan diperpanjang, aplikasi berlaku. Kalimat yang sama
               untuk keduanya membuat biaya yang datang lagi tiap tahun
               terbaca seperti pembelian sekali jalan. */
            return $this->jenis === self::LAYANAN ? 'Per tahun' : 'Berlaku 1 tahun';
        }

        return 'Berlaku '.$this->masa_bulan.' bulan';
    }
}
