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
    /* Rahasia dan kode pemulihan dua faktor TIDAK PERNAH ikut
       terserialisasi. Halaman Inertia mengirim seluruh prop ke peramban,
       dan satu $request->user() yang lolos ke prop membocorkan rahasia
       yang membuat lapisan keduanya tidak berarti apa-apa — tanpa gejala
       apa pun, karena situsnya tetap bekerja persis sama. */
    protected $hidden = [
        'password', 'remember_token', 'kode_verifikasi',
        'dua_faktor_rahasia', 'dua_faktor_pemulihan',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'kode_verifikasi_at' => 'datetime',
            'masuk_terakhir_at'  => 'datetime',

            /* Sengaja TIDAK ikut $fillable. Penandanya hanya boleh
               dipasang oleh rute penyelesaian tur, bukan ikut terbawa
               pembaruan profil mana pun — akun yang belum pernah
               melihat pengenalannya tidak boleh diam-diam terhitung
               sudah. */
            'tur_selesai_pada'  => 'datetime',
            'password'          => 'hashed',

            /* Tersandi di basis data. Cadangan yang bocor tidak boleh
               memberi siapa pun kemampuan membuat kode yang sah. */
            'dua_faktor_rahasia'   => 'encrypted',
            'dua_faktor_pemulihan' => 'encrypted:array',
            'dua_faktor_aktif_at'  => 'datetime',
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

    /* ═══════════ peran audit SMKP (porting Aurorasys) ═══════════
     *
     * Kolom `audit_role` yang sudah ada dipakai ulang, tidak ditambah
     * kolom baru: 'auditor' membuka penyuntingan audit, nilai lain —
     * 'company' ("Perusahaan" di Admin → Pengguna) maupun kosong — berarti
     * auditi: membaca audit perusahaannya, mengunggah bukti, dan
     * menyampaikan tindak lanjut temuan.
     *
     * Pengguna lama dipetakan sekali lewat migrasi
     * 2026_11_07_000003_pindah_data_smkp_aurorasys, agar yang sebelumnya
     * dapat menyunting SMKP tidak terkunci. */

    public const ROLE_AUDITOR = 'auditor';
    public const ROLE_AUDITI  = 'company';

    /** Label peran untuk tampilan; kuncinya yang disimpan. */
    public const ROLES = [
        self::ROLE_AUDITOR => 'Auditor',
        self::ROLE_AUDITI  => 'Auditi (unggah bukti & tindak lanjut)',
    ];

    /** @var array{untuk:string,isi:array<int>}|null */
    private ?array $jangkauanMemo = null;

    /** Auditi: melihat data perusahaannya dan menyampaikan tindak lanjut temuan. */
    public function isAuditi(): bool
    {
        return !$this->bisaAudit();
    }

    /** Boleh mengisi dan mengubah audit SMKP — administrator atau auditor. */
    public function bisaAudit(): bool
    {
        return $this->isAdmin() || $this->audit_role === self::ROLE_AUDITOR;
    }

    public function labelPeran(): string
    {
        if ($this->isAdmin()) return 'Administrator';

        return $this->audit_role === self::ROLE_AUDITOR ? self::ROLES[self::ROLE_AUDITOR] : 'Auditi';
    }

    /**
     * Perusahaan yang terjangkau pengguna ini pada modul audit SMKP.
     *
     * null berarti seluruh perusahaan: administrator yang tidak sedang
     * melihat satu perusahaan (App\Support\Perusahaan::terpilih). Selain
     * itu: perusahaan penempatannya sendiri beserta perusahaan yang
     * `parent_id`-nya menunjuk ke sana — pemegang IUP menjangkau audit
     * PJP yang bekerja di wilayah izinnya, PJP hanya dirinya sendiri.
     * Administrator yang sedang melihat satu perusahaan dibatasi persis
     * seperti pengguna perusahaan itu. Tanpa penempatan, tidak ada yang
     * terjangkau — lebih baik daftar kosong yang jelas sebabnya daripada
     * data perusahaan lain yang ikut terbaca.
     *
     * @return array<int>|null
     */
    public function jangkauan(): ?array
    {
        $milik = $this->isAdmin() ? \App\Support\Perusahaan::terpilih($this) : (int) $this->company_id;
        if ($this->isAdmin() && $milik === null) return null;
        if (!$milik) return [];

        // Diingat per objek pengguna (sekali per permintaan), dan ikut
        // berganti bila penempatan/pilihannya berubah.
        $kunci = ($this->isAdmin() ? 'lihat:' : 'milik:').$milik;
        if (($this->jangkauanMemo['untuk'] ?? null) !== $kunci) {
            $this->jangkauanMemo = [
                'untuk' => $kunci,
                'isi'   => array_values(array_unique(array_merge(
                    [$milik],
                    Company::withoutGlobalScopes()->where('parent_id', $milik)->pluck('id')->map(fn ($id) => (int) $id)->all(),
                ))),
            ];
        }

        return $this->jangkauanMemo['isi'];
    }

    /** Apakah perusahaan tertentu berada dalam jangkauan pengguna. */
    public function menjangkau(?int $companyId): bool
    {
        $j = $this->jangkauan();

        return $j === null || ($companyId !== null && in_array($companyId, $j, true));
    }

    // Relasi
    public function company(): BelongsTo     { return $this->belongsTo(Company::class); }
    public function enrollments(): HasMany   { return $this->hasMany(Enrollment::class); }
    public function certificates(): HasMany  { return $this->hasMany(Certificate::class); }
    public function quizAttempts(): HasMany  { return $this->hasMany(QuizAttempt::class); }
    public function moduleCompletions(): HasMany { return $this->hasMany(ModuleCompletion::class); }
}
