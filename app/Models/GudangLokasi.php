<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GudangLokasi extends Model
{
    protected $table = 'gudang_lokasi';

    protected $fillable = [
        'kode','nama','jenis','lokasi','penanggung_jawab','company_id',
        'berventilasi','tahan_api','ada_tanggul','ada_apar','ada_eyewash',
        'suhu_maks','keterangan',
    ];

    protected function casts(): array
    {
        return [
            'berventilasi' => 'boolean',
            'tahan_api'    => 'boolean',
            'ada_tanggul'  => 'boolean',
            'ada_apar'     => 'boolean',
            'ada_eyewash'  => 'boolean',
            'suhu_maks'    => 'float',
        ];
    }

    public function company() { return $this->belongsTo(Company::class); }
    public function barang(): HasMany { return $this->hasMany(GudangBarang::class, 'lokasi_id')->orderBy('nama'); }
}
