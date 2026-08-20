<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, \App\Models\Concerns\VerifikasiKode;

    /**
     * Membajak notifikasi verifikasi bawaan.
     *
     * Laravel mengirim tautan bertanda tangan lewat metode ini begitu
     * peristiwa Registered terjadi. Tautan hanya bekerja bila surelnya
     * dibuka di peramban yang sama dengan tempat mendaftar; surel kerja
     * sering dibuka di ponsel lain atau di peramban dalaman sebuah
     * aplikasi, dan sesi di sana kosong.
     *
     * Dibajak di sini, bukan dengan mematikan pendengarnya: alur bawaan
     * tetap utuh — termasuk pada pengiriman ulang — dan tidak ada jalur
     * yang diam-diam masih mengirim tautan lama.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\KodeVerifikasiEmail($this->buatKodeVerifikasi()));
    }

    protected $fillable = [
        'name', 'email', 'password',
        // peran terpadu
        'is_admin', 'lms_role', 'audit_role', 'ohse_role', 'company_id',
        // profil (dulu tabel 'profiles')
        'avatar', 'employee_id', 'position', 'department', 'phone', 'active',
        'whatsapp', 'bio', 'tema',
    ];

    // Kode verifikasi tidak pernah ikut terserialisasi — ia rahasia
    // sekali pakai, dan halaman Inertia mengirim seluruh prop ke peramban.
    protected $hidden = ['password', 'remember_token', 'kode_verifikasi'];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'kode_verifikasi_at' => 'datetime',
            'masuk_terakhir_at'  => 'datetime',
            'password'          => 'hashed',
            'is_admin'          => 'boolean',
            'active'            => 'boolean',
        ];
    }

    // Helper peran
    public function isAdmin(): bool   { return (bool) $this->is_admin; }
    public function isTrainer(): bool { return $this->lms_role === 'trainer'; }
    public function isKtt(): bool     { return $this->lms_role === 'ktt'; }

    /**
     * Tim OHSE — satu-satunya yang memutuskan di modul Authority.
     *
     * Dipisah dari isKtt(): rantai paraf kartu masuk dan MCU sengaja
     * berhenti di OHSE saja, bukan di setiap penanda tangan dokumen.
     */
    public function isOhse(): bool    { return $this->ohse_role === 'ohse'; }

    /**
     * Paramedis — yang membaca hasil pemeriksaan, bukan yang memutuskan.
     *
     * Menumpang pada kolom yang sama dengan isOhse(), sehingga keduanya
     * saling meniadakan. Itu disengaja: tahap paramedis ada supaya yang
     * membaca hasil pemeriksaan bukan orang yang memutuskan
     * kelayakannya, dan satu orang yang memegang kedua peran dapat
     * memaraf tahap paramedis lalu menyetujui pengajuannya sendiri
     * sebagai OHSE.
     */
    public function isParamedis(): bool { return $this->ohse_role === 'paramedis'; }

    // Relasi
    public function company(): BelongsTo     { return $this->belongsTo(Company::class); }
    public function enrollments(): HasMany   { return $this->hasMany(Enrollment::class); }
    public function certificates(): HasMany  { return $this->hasMany(Certificate::class); }
    public function quizAttempts(): HasMany  { return $this->hasMany(QuizAttempt::class); }
    public function moduleCompletions(): HasMany { return $this->hasMany(ModuleCompletion::class); }
}
