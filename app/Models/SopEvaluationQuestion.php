<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
// PENTING: correct_index TIDAK BOLEH dikirim ke browser.
// Penilaian dilakukan di SopController@grade (server-side).
class SopEvaluationQuestion extends Model {
    protected $fillable = ['evaluation_id','question','options','correct_index','order_index'];
    protected $hidden   = ['correct_index'];
    protected function casts(): array { return ['options' => 'array']; }
    public function evaluation(): BelongsTo { return $this->belongsTo(SopEvaluation::class,'evaluation_id'); }
}
