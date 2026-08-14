<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy(MilikPerusahaan::class)]
class MinerbaConservationAction extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'konservasi_minerba_actions';

    protected $fillable = [
        'company_id', 'record_id', 'judul', 'kategori', 'prioritas', 'status',
        'penanggung_jawab', 'target_selesai', 'uraian', 'user_id',
    ];

    protected function casts(): array
    {
        return ['target_selesai' => 'date'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function record(): BelongsTo { return $this->belongsTo(MinerbaConservationRecord::class, 'record_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
