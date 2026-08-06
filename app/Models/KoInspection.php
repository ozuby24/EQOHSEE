<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KoInspection extends Model
{
    protected $table = 'ko_inspections';

    protected $fillable = [
        'ko_object_id','ko_safeguard_id','ko_personnel_id','jenis','tanggal','hasil',
        'nilai_ukur','berikutnya','catatan','lampiran','user_id',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'berikutnya' => 'date'];
    }

    public function object()    { return $this->belongsTo(KoObject::class, 'ko_object_id'); }
    public function safeguard() { return $this->belongsTo(KoSafeguard::class, 'ko_safeguard_id'); }
    public function personnel() { return $this->belongsTo(KoPersonnel::class, 'ko_personnel_id'); }
    public function user()      { return $this->belongsTo(User::class); }
}
