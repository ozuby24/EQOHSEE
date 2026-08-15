<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GudangMutasi extends Model
{
    protected $table = 'gudang_mutasi';

    protected $fillable = [
        'barang_id','jenis','tanggal','nomor','jumlah','stok_fisik',
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
