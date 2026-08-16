<?php
namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Prosedur, melekat pada perusahaan yang memberlakukannya.
 *
 * Berbeda dari Course dan Quiz, yang sengaja dipakai bersama. Garis
 * pemisahnya: MATERI PELATIHAN sama bagi siapa pun yang belajar,
 * sedangkan PROSEDUR menyebut nama jabatan, batas kewenangan, dan
 * urutan kerja yang berlaku di satu perusahaan saja. Perusahaan lain
 * tidak terikat olehnya, dan prosedur orang lain yang terbaca sebagai
 * milik sendiri adalah kesalahan yang mahal pada audit.
 *
 * `company_id` boleh kosong, dan yang kosong terbaca oleh semua —
 * begitulah prosedur baku se-pemasangan dibuat, dan itu pula sebabnya
 * prosedur lama tidak perlu ditebak pemiliknya.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Procedure extends Model
{
    use BerpemilikPerusahaan;

    protected $fillable = ['code', 'title', 'category', 'description', 'url', 'position', 'company_id'];

    public function company(): BelongsTo     { return $this->belongsTo(Company::class); }
    public function evaluations(): HasMany   { return $this->hasMany(SopEvaluation::class); }
    public function documents(): HasMany     { return $this->hasMany(Document::class); }
}
