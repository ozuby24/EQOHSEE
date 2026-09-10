<?php

namespace App\Models\Pjp;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu kategori daftar periksa prakualifikasi SMKP — A sampai P, ditambah
 * LEGALITAS.
 *
 * Data acuan, bukan data perusahaan: tidak berkolom company_id dan tidak
 * dapat disunting lewat layar mana pun. Isinya lampiran Kepdirjen
 * Minerba 185/2019 yang berlaku sama bagi setiap pemegang IUP.
 */
class SmkpKategori extends Model
{
    /** Kategori syarat wajib, dijawab Y/T dan TIDAK ikut skor 178. */
    public const LEGALITAS = 'LEGALITAS';

    protected $table = 'pjp_smkp_kategori';

    protected $fillable = ['kode', 'nama', 'bobot', 'urutan'];

    public function items()
    {
        return $this->hasMany(SmkpItem::class, 'kategori_id')->orderBy('urutan');
    }

    /**
     * Kategori berbobot A–P beserta butirnya, dimuat SEKALI per permintaan.
     *
     * Yang membuat ingatan ini perlu bukan penghematan yang samar.
     * Skor SMKP dihitung per PJP, dan halaman daftar menghitungnya untuk
     * setiap baris: tanpa ingatan ini, halaman berisi tujuh PJP membaca
     * tabel kategori dan tabel butir tujuh kali masing-masing — dengan
     * jawaban yang persis sama tiap kalinya, sebab isinya memang tidak
     * bergantung pada PJP mana pun. Diukur pada kit asalnya: 36 kueri
     * untuk 7 PJP.
     *
     * Ingatannya sengaja hanya sepanjang satu permintaan. Data acuan ini
     * berubah ketika `pjp:pasang` dijalankan ulang, dan cache yang lebih
     * panjang membuat perubahan itu tidak terlihat sampai seseorang
     * membersihkannya — pada halaman yang menampilkan angka kepatuhan,
     * itu jenis kesalahan yang tidak menimbulkan galat.
     */
    public static function berbobot(): Collection
    {
        return static::$berbobot ??= static::query()
            ->where('kode', '!=', self::LEGALITAS)
            ->orderBy('urutan')
            ->with('items')
            ->get();
    }

    /** Butir syarat wajib legalitas, dimuat sekali per permintaan. */
    public static function butirLegalitas(): Collection
    {
        return static::$butirLegalitas ??= SmkpItem::query()
            ->whereHas('kategori', fn ($q) => $q->where('kode', self::LEGALITAS))
            ->orderBy('urutan')
            ->get();
    }

    /**
     * Buang ingatan di atas.
     *
     * Dipanggil `pjp:pasang` sesudah memasang ulang daftar periksanya,
     * dan oleh uji yang menyemai master di tengah jalan. Tanpa ini, uji
     * yang menyemai sesudah pemanggilan pertama akan menghitung skor
     * dengan daftar kosong dan lulus dengan angka yang salah.
     */
    public static function lupakanIngatan(): void
    {
        static::$berbobot = null;
        static::$butirLegalitas = null;
    }

    private static ?Collection $berbobot = null;

    private static ?Collection $butirLegalitas = null;
}
