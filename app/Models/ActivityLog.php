<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $table = 'activity_log';
    protected $fillable = ['user_id','username','module','action','detail'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** Catat aktivitas (dipanggil dari controller) */
    public static function write(string $action, ?string $detail = null, string $module = 'lms'): void
    {
        static::create([
            'user_id'  => auth()->id(),
            'username' => auth()->user()?->name,
            'module'   => $module,
            'action'   => $action,
            'detail'   => $detail,
        ]);
    }
}
