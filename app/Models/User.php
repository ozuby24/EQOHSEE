<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password',
        // peran terpadu
        'is_admin', 'lms_role', 'audit_role', 'company_id',
        // profil (dulu tabel 'profiles')
        'avatar', 'employee_id', 'position', 'department', 'phone', 'active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_admin'          => 'boolean',
            'active'            => 'boolean',
        ];
    }

    // Helper peran
    public function isAdmin(): bool   { return (bool) $this->is_admin; }
    public function isTrainer(): bool { return $this->lms_role === 'trainer'; }
    public function isKtt(): bool     { return $this->lms_role === 'ktt'; }

    // Relasi
    public function company(): BelongsTo     { return $this->belongsTo(Company::class); }
    public function enrollments(): HasMany   { return $this->hasMany(Enrollment::class); }
    public function certificates(): HasMany  { return $this->hasMany(Certificate::class); }
    public function quizAttempts(): HasMany  { return $this->hasMany(QuizAttempt::class); }
    public function moduleCompletions(): HasMany { return $this->hasMany(ModuleCompletion::class); }
}
