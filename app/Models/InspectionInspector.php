<?php
namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionInspector extends Model
{
    use BerindukPerusahaan;

    /** Pemiliknya ada pada induknya; lihat BerindukPerusahaan. */
    protected static string $indukPerusahaan = 'inspection';

    protected $fillable = ['inspection_id','user_id','nama','jabatan','peran'];

    public function inspection(): BelongsTo { return $this->belongsTo(Inspection::class); }
    public function user(): BelongsTo       { return $this->belongsTo(User::class); }

    public function golongan(): string { return \App\Support\Hazard::golongan($this->jabatan); }
}
