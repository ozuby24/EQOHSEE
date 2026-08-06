<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class SopEvaluationAttempt extends Model {
    protected $fillable = ['user_id','evaluation_id','procedure_id','score','total','correct','passed','answers'];
    protected function casts(): array { return ['answers' => 'array', 'passed' => 'boolean']; }
    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function evaluation(): BelongsTo { return $this->belongsTo(SopEvaluation::class,'evaluation_id'); }
}
