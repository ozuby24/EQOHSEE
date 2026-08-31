<?php

namespace App\Models\Investigasi;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Catatan langkah pada satu berkas investigasi.
 *
 * Terpisah dari ActivityLog aplikasi: yang dicatat di sini bukan "siapa
 * membuka halaman apa" melainkan langkah yang mengubah berkas yang dapat
 * diminta Inspektur Tambang — bukti dikunci, tahap dimajukan,
 * investigasi ditutup. Menyelipkannya ke log umum membuatnya tenggelam
 * di antara ribuan baris yang tidak ada hubungannya.
 */
class Jejak extends Model
{
    protected $table = 'inv_jejak';

    protected $fillable = ['investigasi_id', 'insiden_id', 'user_id', 'aksi', 'keterangan'];

    public function user() { return $this->belongsTo(User::class); }

    public static function catat(string $aksi, ?string $keterangan = null, ?int $investigasiId = null, ?int $insidenId = null): void
    {
        static::create([
            'investigasi_id' => $investigasiId,
            'insiden_id'     => $insidenId,
            'user_id'        => auth()->id(),
            'aksi'           => $aksi,
            'keterangan'     => $keterangan,
        ]);
    }
}
