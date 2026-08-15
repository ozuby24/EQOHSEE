<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;

use Illuminate\Database\Eloquent\Model;

class KoSafeguard extends Model
{
    use BerindukPerusahaan;

    /** Pemiliknya ada pada induknya; lihat BerindukPerusahaan. */
    protected static string $indukPerusahaan = 'object';

    protected $table = 'ko_safeguards';

    protected $fillable = [
        'ko_object_id','nama','spesifikasi','status','tgl_periksa','catatan','sigap_asset_id',
    ];

    protected function casts(): array
    {
        return ['tgl_periksa' => 'date'];
    }

    public function object()      { return $this->belongsTo(KoObject::class, 'ko_object_id'); }
    public function inspections() { return $this->hasMany(KoInspection::class); }

    public function getBerfungsiAttribute(): bool { return $this->status === 'Berfungsi'; }
}
