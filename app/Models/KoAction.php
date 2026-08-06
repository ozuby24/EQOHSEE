<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class KoAction extends Model
{
    protected $table = 'ko_actions';

    protected $fillable = [
        'ko_object_id','ko_safeguard_id','sumber','uraian','prioritas',
        'pic_user_id','pic_nama','target_tgl','status','tgl_selesai','tindakan',
    ];

    protected function casts(): array
    {
        return ['target_tgl' => 'date', 'tgl_selesai' => 'date'];
    }

    public function object()    { return $this->belongsTo(KoObject::class, 'ko_object_id'); }
    public function safeguard() { return $this->belongsTo(KoSafeguard::class, 'ko_safeguard_id'); }
    public function pic()       { return $this->belongsTo(User::class, 'pic_user_id'); }

    public function getTerbukaAttribute(): bool
    {
        return in_array($this->status, ['Terbuka', 'Berjalan'], true);
    }

    public function getTerlambatAttribute(): bool
    {
        return $this->terbuka && $this->target_tgl
            && Carbon::parse($this->target_tgl)->startOfDay()->lt(now()->startOfDay());
    }
}
