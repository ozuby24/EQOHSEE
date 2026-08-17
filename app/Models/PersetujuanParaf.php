<?php

namespace App\Models;

use App\Support\Tahap;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu paraf pada satu tahap: tanda seseorang sudah melihat.
 *
 * BUKAN persetujuan. Yang menerbitkan kartu maupun meloloskan pengajuan
 * MCU hanya tahap penentu (OHSE), dan tahap itu tidak diparaf melainkan
 * diputus lewat alur biasa. Paraf tidak pernah menyentuh kolom status —
 * pemisahan itulah yang membuat "terlihat bertingkat, sesungguhnya satu
 * yang memutuskan" menjadi keadaan yang dijaga struktur datanya, bukan
 * kesepakatan yang hanya berlaku selama tidak ada yang lupa.
 */
class PersetujuanParaf extends Model
{
    protected $table = 'persetujuan_paraf';

    protected $fillable = ['tahap', 'user_id', 'nama', 'jabatan', 'catatan'];

    public function subjek() { return $this->morphTo(); }
    public function user()   { return $this->belongsTo(User::class); }

    public function label(): string
    {
        return Tahap::label($this->tahap);
    }
}
