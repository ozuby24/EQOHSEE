<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak aktivitas.
 *
 * Sejak jejak akses ada, tiap baris juga membawa ALAMAT, PERANGKAT, dan
 * PERUSAHAAN. Ketiganya diisi sendiri di sini, bukan oleh pemanggilnya.
 * Itu disengaja: ada lebih dari seratus tempat yang memanggil `write()`
 * di seluruh modul, dan penambahan yang menuntut tiap pemanggil
 * mengirim alamatnya akan menghasilkan jejak yang separuhnya beralamat
 * dan separuhnya tidak — persis keadaan yang hendak diperbaiki.
 *
 * `company_id` diisi dari pengguna yang sedang masuk. Ia disimpan pada
 * barisnya, bukan dibaca ulang lewat relasi pengguna, karena pengguna
 * dapat berpindah perusahaan sesudahnya; yang harus dijawab jejak
 * adalah "atas nama perusahaan mana hal ini dikerjakan waktu itu",
 * bukan "di perusahaan mana orangnya sekarang".
 */
class ActivityLog extends Model
{
    protected $table = 'activity_log';
    protected $fillable = ['user_id','username','module','action','detail','ip','user_agent','company_id'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    /** Catat aktivitas (dipanggil dari controller) */
    public static function write(string $action, ?string $detail = null, string $module = 'lms'): void
    {
        $pengguna = auth()->user();

        static::create([
            'user_id'    => $pengguna?->id,
            'username'   => $pengguna?->name,
            'module'     => $module,
            'action'     => $action,
            'detail'     => $detail,
            'ip'         => \App\Support\Keamanan::alamat(),
            'user_agent' => \App\Support\Keamanan::perangkat(),
            'company_id' => $pengguna?->company_id,
        ]);
    }
}
