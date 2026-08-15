<?php
namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionTemplateItem extends Model
{
    use BerindukPerusahaan;

    /** Pemiliknya ada pada induknya; lihat BerindukPerusahaan. */
    protected static string $indukPerusahaan = 'template';

    protected $fillable = ['template_id','kelompok','uraian','acuan','risiko_default','order_index'];

    public function template(): BelongsTo { return $this->belongsTo(InspectionTemplate::class, 'template_id'); }
}
