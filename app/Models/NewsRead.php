<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan bahwa satu orang sudah membuka satu pengumuman.
 *
 * Barisnya, bukan penghitungnya. Yang ditanyakan pengawas sesudah
 * menerbitkan pengumuman keselamatan bukan "berapa orang" melainkan
 * "siapa yang belum" — dan pertanyaan kedua tidak dapat dijawab oleh
 * angka pada barisnya sendiri.
 *
 * TIDAK memakai MilikPerusahaan. Batas perusahaannya sudah ditegakkan
 * oleh pengumumannya: baris ini hanya terjangkau lewat News, dan News
 * yang bukan milik perusahaan ini memang tidak dapat ditemukan. Scope
 * kedua di sini akan menjadi salinan aturan yang sama, dan salinan
 * kedua adalah cara paling pasti membuat keduanya berselisih.
 */
class NewsRead extends Model
{
    protected $table = 'news_reads';

    protected $fillable = ['news_id', 'user_id'];

    public function news(): BelongsTo { return $this->belongsTo(News::class); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
