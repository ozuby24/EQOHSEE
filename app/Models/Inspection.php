<?php
namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

#[ScopedBy(MilikPerusahaan::class)]
class Inspection extends Model
{
    use BerpemilikPerusahaan;

    protected $fillable = ['kode','template_id','company_id','user_id','judul','jenis','lokasi','tanggal','pelaksana','status','catatan'];

    protected function casts(): array { return ['tanggal' => 'date']; }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function items(): HasMany      { return $this->hasMany(InspectionItem::class)->orderBy('order_index'); }
    public function template(): BelongsTo { return $this->belongsTo(InspectionTemplate::class, 'template_id'); }
    public function inspectors(): HasMany { return $this->hasMany(InspectionInspector::class); }

    /** Ringkasan hasil pemeriksaan */
    public function ringkas(): array
    {
        $i = $this->items;
        return [
            'total'   => $i->count(),
            'sesuai'  => $i->where('kondisi','Sesuai')->count(),
            'tidak'   => $i->where('kondisi','Tidak Sesuai')->count(),
            'na'      => $i->where('kondisi','N/A')->count(),
            'belum'   => $i->whereNull('kondisi')->count(),
        ];
    }

    public static function kodeBaru(): string
    {
        return sprintf('INS-%s-%04d', date('Y'), static::whereYear('created_at', date('Y'))->count() + 1);
    }
}
