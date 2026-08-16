<?php
namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengumuman, melekat pada perusahaan yang menerbitkannya.
 *
 * Berbeda dari Course, Quiz, Procedure, dan InspectionTemplate — yang
 * sengaja TIDAK dibatasi perusahaan karena keempatnya adalah pustaka
 * pelatihan yang memang dipakai bersama seluruh pemasangan. Pengumuman
 * bukan pustaka: ia menyebut nama orang, jadwal, dan kejadian di satu
 * lokasi kerja, dan tidak ada alasan perusahaan lain membacanya.
 *
 * `company_id` boleh kosong, dan yang kosong terbaca oleh semua —
 * itulah cara pengumuman se-pemasangan dibuat, dan itu pula sebabnya
 * berita lama tidak perlu ditebak pemiliknya.
 */
#[ScopedBy(MilikPerusahaan::class)]
class News extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'news';

    protected $fillable = ['title', 'content', 'published_at', 'company_id'];

    protected function casts(): array { return ['published_at' => 'date']; }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
