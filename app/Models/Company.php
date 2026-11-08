<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
class Company extends Model {
    /**
     * Kategori perusahaan pada modul audit SMKP (porting Aurorasys):
     * menentukan pengesah Rencana Audit (PJO wajib bagi PJP), distribusi
     * laporan, dan urutan pilihan perusahaan.
     */
    public const KATEGORI = [
        'induk'   => 'Pemegang IUP',
        'pjp'     => 'Perusahaan Jasa Pertambangan',
        'sister'  => 'Sister Company',
        'lainnya' => 'Lainnya',
    ];

    protected $fillable = ['name','demo','code','kategori','parent','izin_type','commodity','location','address','ktt','pjo','workers_employee','workers_sub','risk_class','logo','theme_color','theme_dark','doc_no_prefix','parent_id','pic_name','pic_email','pic_phone','divisi','departemen', 'dept_kode','doc_terbit','doc_setuju','doc_revisi','kop_formulir'];

    protected function casts(): array
    {
        return ['doc_terbit' => 'date', 'doc_setuju' => 'date', 'demo' => 'boolean', 'kop_formulir' => 'array'];
    }

    /**
     * Kategori yang belum ditetapkan diturunkan dari data yang sudah ada,
     * sehingga perusahaan yang dibuat modul lain (data contoh, Admin →
     * Perusahaan) tetap berkategori: IUJP atau perusahaan yang bernaung di
     * bawah IUP lain → PJP, selain itu pemegang IUP. Kategori yang sudah
     * diisi tidak pernah ditimpa.
     */
    protected static function booted(): void
    {
        static::saving(function (Company $c) {
            if (blank($c->kategori)) $c->kategori = $c->kategoriTurunan();
        });
    }

    public function kategoriTurunan(): string
    {
        return $this->isJasa() || $this->parent_id ? 'pjp' : 'induk';
    }

    public function users(): HasMany       { return $this->hasMany(User::class); }
    public function owner()                { return $this->belongsTo(Company::class, 'parent_id'); }
    public function subsidiaries(): HasMany{ return $this->hasMany(Company::class, 'parent_id'); }

    /* Nama relasi yang dipakai modul SMKP (Aurorasys) untuk hal yang sama. */
    public function induk(): BelongsTo      { return $this->belongsTo(Company::class, 'parent_id'); }
    public function mitra(): HasMany        { return $this->hasMany(Company::class, 'parent_id'); }
    public function smkpAudits(): HasMany   { return $this->hasMany(SmkpAudit::class); }

    public function labelKategori(): string
    {
        return self::KATEGORI[$this->kategori] ?? 'Lainnya';
    }

    /**
     * Alamat publik logo, atau null bila belum diunggah. Perusahaan jasa
     * memakai logo pemilik/IUP-nya bila belum punya sendiri — sama dengan
     * kop dokumen (KopDokumen::untuk).
     */
    public function logoUrl(): ?string
    {
        return \App\Support\Berkas::terbuka($this->effectiveLogo());
    }

    public function inisial(): string
    {
        if ($this->code) return mb_strtoupper(mb_substr($this->code, 0, 3));

        $abaikan = ['pt', 'cv', 'tbk', 'persero'];
        $huruf = '';
        foreach (preg_split('/\s+/', trim((string) $this->name)) as $kata) {
            $bersih = preg_replace('/[^A-Za-z]/', '', $kata);
            if ($bersih === '' || in_array(strtolower($bersih), $abaikan, true)) continue;
            $huruf .= strtoupper($bersih[0]);
        }

        return substr($huruf, 0, 3) ?: 'PT';
    }

    public function totalPekerja(): int
    {
        return $this->totalWorkers();
    }

    /** Ringkasan untuk pilihan formulir di sisi Vue. */
    public function pilihan(): array
    {
        return ['id' => $this->id, 'nama' => $this->name, 'kategori' => $this->kategori];
    }

    /** Perusahaan jasa (IUJP) memakai logo pemilik/IUP-nya. */
    public function effectiveLogo(): ?string
    {
        if ($this->logo) return $this->logo;
        return $this->owner?->logo;
    }

    /** Nama pemilik izin yang dicetak pada sertifikat. */
    public function ownerName(): string
    {
        return $this->owner?->name ?: ($this->parent ?: $this->name);
    }

    /** Nomor WhatsApp dinormalkan ke format internasional tanpa tanda. */
    public function waNumber(): ?string
    {
        return \App\Support\Ekspor::nomorWa($this->pic_phone);
    }

    public function isJasa(): bool
    {
        return str_contains(strtoupper((string) $this->izin_type), 'IUJP');
    }
    /* relasi assessment dilepas: penilaian TPKKP kini per periode, bukan per perusahaan */
    public function totalWorkers(): int    { return (int) $this->workers_employee + (int) $this->workers_sub; }
}
