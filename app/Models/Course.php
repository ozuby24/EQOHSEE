<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Contoh model modul LMS. Model lain (Module, Material, Quiz, QuizQuestion,
// Enrollment, Certificate, Procedure, SopEvaluation, dst.) mengikuti pola yang sama.
class Course extends Model
{
    protected $fillable = ['title','description','category','image','cert_template','auto_certificate',
                           'access_code','require_code','require_evaluation'];

    protected function casts(): array
    {
        return ['auto_certificate' => 'boolean', 'require_code' => 'boolean', 'require_evaluation' => 'boolean'];
    }

    /** Kode akses acak yang mudah dibaca (tanpa huruf/angka rancu). */
    public static function kodeBaru(int $n = 6): string
    {
        $c = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $k = '';
        for ($i = 0; $i < $n; $i++) $k .= $c[random_int(0, strlen($c) - 1)];
        return $k;
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('order_index');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class)->orderBy('order_index');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
