<?php

namespace App\Models\Energi;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\User;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Data aktivitas Scope 3 menurut kategori GHG Protocol Value Chain Standard. */
#[ScopedBy(MilikPerusahaan::class)]
class Scope3 extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'energi_scope3';

    public const KATEGORI = [
        1 => 'Barang & jasa yang dibeli', 2 => 'Barang modal',
        3 => 'Kegiatan terkait bahan bakar & energi (hulu)', 4 => 'Angkutan & distribusi hulu',
        5 => 'Limbah operasi', 6 => 'Perjalanan dinas', 7 => 'Komuter karyawan',
        8 => 'Aset sewaan hulu', 9 => 'Angkutan & distribusi hilir', 10 => 'Pengolahan produk terjual',
        11 => 'Penggunaan produk terjual', 12 => 'Pengolahan akhir umur produk terjual',
        13 => 'Aset sewaan hilir', 14 => 'Waralaba', 15 => 'Investasi',
    ];

    public const METODE = [
        'spend' => 'Berbasis belanja', 'average_data' => 'Data rata-rata',
        'supplier_specific' => 'Spesifik pemasok', 'distance' => 'Berbasis jarak',
    ];

    protected $fillable = ['company_id', 'tahun', 'bulan', 'kategori', 'subkategori', 'metode', 'kuantitas',
                           'satuan', 'faktor_kgco2e', 'sumber_faktor', 'mitra', 'kualitas_data', 'status',
                           'diinput_oleh', 'catatan'];

    protected function casts(): array
    {
        return ['tahun' => 'integer', 'bulan' => 'integer', 'kategori' => 'integer', 'kuantitas' => 'float',
                'faktor_kgco2e' => 'float', 'kualitas_data' => 'integer'];
    }

    public function penginput(): BelongsTo { return $this->belongsTo(User::class, 'diinput_oleh'); }

    public function tco2e(): float { return $this->kuantitas * $this->faktor_kgco2e / 1000; }
}
