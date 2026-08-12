<?php
namespace App\Models;

use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy(MilikPerusahaan::class)]
class HazardReport extends Model
{
    protected $fillable = [
        'kode','user_id','pelapor_nama','pelapor_nrp','pelapor_perusahaan','pelapor_departemen','pelapor_jabatan',
        'company_id','terlapor','tanggal','waktu','lokasi','risiko','kategori','deskripsi',
        'unsafe_action','unsafe_condition','hirarki','rekomendasi','status',
        'foto','foto_tindaklanjut','catatan_penutupan','closed_by','closed_at',
    ];

    protected function casts(): array
    {
        return ['tanggal'=>'date', 'closed_at'=>'datetime', 'foto'=>'array', 'foto_tindaklanjut'=>'array'];
    }

    /**
     * unsafe_action & unsafe_condition kini berupa DAFTAR (bisa banyak butir).
     * Accessor ini aman untuk data lama yang masih berupa teks biasa.
     */
    public function getUnsafeActionListAttribute(): array  { return $this->uraikan($this->unsafe_action); }
    public function getUnsafeConditionListAttribute(): array { return $this->uraikan($this->unsafe_condition); }

    private function uraikan(?string $v): array
    {
        if (!$v) return [];
        $j = json_decode($v, true);
        return is_array($j) ? $j : [$v];      // data lama = teks tunggal
    }

    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function closer(): BelongsTo  { return $this->belongsTo(User::class, 'closed_by'); }

    public function golongan(): string { return \App\Support\Hazard::golongan($this->pelapor_jabatan); }

    public static function kodeBaru(): string
    {
        return sprintf('HR-%s-%04d', date('Y'), static::whereYear('created_at', date('Y'))->count() + 1);
    }
}
