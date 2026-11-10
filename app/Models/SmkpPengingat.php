<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pengingat batas waktu tindak lanjut yang sudah diproses untuk sebuah
 * temuan (App\Services\PengingatTindakLanjut). Tidak bercakupan sendiri:
 * selalu dibaca lewat temuannya. `target` disimpan sebagai teks Y-m-d (tanpa
 * cast tanggal) agar kunci unik sama persis di SQLite maupun MySQL.
 */
class SmkpPengingat extends Model
{
    protected $table = 'smkp_pengingat';

    protected $fillable = ['finding_id', 'target', 'jadwal', 'penerima', 'dikirim_pada'];

    protected function casts(): array
    {
        return ['dikirim_pada' => 'datetime'];
    }

    public function temuan(): BelongsTo
    {
        return $this->belongsTo(SmkpFinding::class, 'finding_id');
    }
}
