<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

use Illuminate\Database\Eloquent\Model;

#[ScopedBy(MilikPerusahaan::class)]
class GudangMutasi extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'gudang_mutasi';

    protected $fillable = [
        'company_id','barang_id','jenis','tanggal','nomor','jumlah','stok_fisik',
        'pihak','penerima_id','kadaluarsa','batch','keterangan','user_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'    => 'date',
            'kadaluarsa' => 'date',
            'jumlah'     => 'float',
            'stok_fisik' => 'float',
        ];
    }

    public function barang()   { return $this->belongsTo(GudangBarang::class, 'barang_id'); }
    public function penerima() { return $this->belongsTo(User::class, 'penerima_id'); }
    public function user()     { return $this->belongsTo(User::class); }
}
