<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class SopEvaluation extends Model {
    protected $fillable = ['procedure_id','title','description','passing_score','duration_minutes','is_active','position'];
    protected function casts(): array { return ['is_active' => 'boolean']; }
    public function procedure(): BelongsTo { return $this->belongsTo(Procedure::class); }
    public function questions(): HasMany   { return $this->hasMany(SopEvaluationQuestion::class,'evaluation_id')->orderBy('order_index'); }
    public function attempts(): HasMany    { return $this->hasMany(SopEvaluationAttempt::class,'evaluation_id'); }
}
