<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KoReview extends Model
{
    protected $table = 'ko_reviews';

    protected $fillable = [
        'ko_object_id','judul','pemicu','tanggal','oleh','status','tgl_lapor',
        'ko_personnel_id','lampiran','ringkasan','hazard_report_id',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'tgl_lapor' => 'date'];
    }

    public function object()    { return $this->belongsTo(KoObject::class, 'ko_object_id'); }
    public function personnel() { return $this->belongsTo(KoPersonnel::class, 'ko_personnel_id'); }
}
