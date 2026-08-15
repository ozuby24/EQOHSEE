<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

use App\Support\Gudang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ScopedBy(MilikPerusahaan::class)]
class GudangBarang extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'gudang_barang';

    protected $fillable = [
        'company_id','kode','nama','kategori','satuan','stok_min','lokasi_id',
        'kelas_b3','wujud','un_number','msds','wajib_msds',
        'masa_pakai_bulan','ukuran','part_number','merk',
        'foto','keterangan','aktif',
    ];

    protected function casts(): array
    {
        return [
            'stok_min'         => 'float',
            'wajib_msds'       => 'boolean',
            'aktif'            => 'boolean',
            'masa_pakai_bulan' => 'integer',
        ];
    }

    public function lokasi() { return $this->belongsTo(GudangLokasi::class, 'lokasi_id'); }
    public function mutasi(): HasMany { return $this->hasMany(GudangMutasi::class, 'barang_id')->orderBy('tanggal')->orderBy('id'); }

    /* Turunan — dihitung dari mutasi, tidak pernah disimpan. */
    public function getStokAttribute(): float  { return Gudang::stok($this); }
    public function getStatusAttribute(): array { return Gudang::statusStok($this); }
}
