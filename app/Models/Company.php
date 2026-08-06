<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Company extends Model {
    protected $fillable = ['name','code','parent','izin_type','commodity','location','address','ktt','pjo','workers_employee','workers_sub','risk_class','logo','doc_no_prefix','parent_id','pic_name','pic_email','pic_phone'];

    public function users(): HasMany       { return $this->hasMany(User::class); }
    public function owner()                { return $this->belongsTo(Company::class, 'parent_id'); }
    public function subsidiaries(): HasMany{ return $this->hasMany(Company::class, 'parent_id'); }

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
        $n = preg_replace('/\D/', '', (string) $this->pic_phone);
        if (!$n) return null;
        if (str_starts_with($n, '0'))  $n = '62'.substr($n, 1);
        if (!str_starts_with($n, '62')) $n = '62'.$n;
        return $n;
    }

    public function isJasa(): bool
    {
        return str_contains(strtoupper((string) $this->izin_type), 'IUJP');
    }
    /* relasi assessment dilepas: penilaian TPKKP kini per periode, bukan per perusahaan */
    public function totalWorkers(): int    { return (int) $this->workers_employee + (int) $this->workers_sub; }
}
