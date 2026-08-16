<?php
namespace App\Models;
use App\Models\Concerns\BerindukPerusahaan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
/**
 * Evaluasi SOP, mengikuti perusahaan PROSEDURNYA.
 *
 * Tidak punya company_id sendiri, dan memang tidak perlu: satu evaluasi
 * selalu milik satu prosedur, dan kepemilikannya tidak pernah berbeda
 * dari prosedur itu. Sejak prosedur melekat perusahaan, evaluasinya
 * ikut — kalau tidak, evaluasi atas prosedur perusahaan lain akan
 * terbaca dan dikerjakan oleh siapa pun.
 *
 * `procedure_id` bersifat NOT NULL, jadi tidak ada baris yatim yang
 * dapat tersembunyi oleh penyaring ini.
 */
class SopEvaluation extends Model {
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'procedure';

    protected $fillable = ['procedure_id','title','description','passing_score','duration_minutes','is_active','position'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function procedure(): BelongsTo { return $this->belongsTo(Procedure::class); }
    public function questions(): HasMany   { return $this->hasMany(SopEvaluationQuestion::class,'evaluation_id')->orderBy('order_index'); }
    public function attempts(): HasMany    { return $this->hasMany(SopEvaluationAttempt::class,'evaluation_id'); }
}
