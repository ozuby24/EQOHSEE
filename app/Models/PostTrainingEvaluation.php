<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class PostTrainingEvaluation extends Model {
    protected $fillable = ['user_id','course_id','enrollment_id','trainer_id','trainer_name','knowledge_score','skill_score','attitude_score','safety_score','overall_score','recommendation','strengths','improvements','notes'];
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function course(): BelongsTo  { return $this->belongsTo(Course::class); }
    public function trainer(): BelongsTo { return $this->belongsTo(User::class,'trainer_id'); }
}
