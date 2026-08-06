<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InspectionInspector extends Model
{
    protected $fillable = ['inspection_id','user_id','nama','jabatan','peran'];

    public function inspection(): BelongsTo { return $this->belongsTo(Inspection::class); }
    public function user(): BelongsTo       { return $this->belongsTo(User::class); }

    public function golongan(): string { return \App\Support\Hazard::golongan($this->jabatan); }
}
