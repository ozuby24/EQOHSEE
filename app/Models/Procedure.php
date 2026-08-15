<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Procedure extends Model {
    protected $fillable = ['code','title','category','description','url','position'];
    public function evaluations(): HasMany { return $this->hasMany(SopEvaluation::class); }
    public function documents(): HasMany   { return $this->hasMany(Document::class); }
}
