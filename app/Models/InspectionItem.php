<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionItem extends Model
{
    protected $fillable = ['inspection_id','template_item_id','kelompok','uraian','acuan','kondisi','risiko','temuan','tindakan','foto','hazard_report_id','order_index'];

    protected function casts(): array { return ['foto' => 'array']; }

    public function inspection(): BelongsTo   { return $this->belongsTo(Inspection::class); }
    public function hazardReport(): BelongsTo { return $this->belongsTo(HazardReport::class); }
}
