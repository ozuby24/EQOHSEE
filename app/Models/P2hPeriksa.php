<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu P2H yang terkirim. Jawabannya salinan utuh, termasuk teks butirnya. */
#[ScopedBy(MilikPerusahaan::class)]
class P2hPeriksa extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'p2h_periksa';

    protected $fillable = [
        'company_id', 'p2h_unit_id', 'user_id', 'work_order_id', 'klien_id',
        'operator', 'shift', 'tanggal', 'hm', 'jawaban',
        'jumlah_ok', 'jumlah_tidak', 'jumlah_na', 'hasil', 'catatan', 'foto',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'hm' => 'float', 'jawaban' => 'array', 'foto' => 'array'];
    }

    public function unit(): BelongsTo      { return $this->belongsTo(P2hUnit::class, 'p2h_unit_id'); }
    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
    public function workOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class); }
}
