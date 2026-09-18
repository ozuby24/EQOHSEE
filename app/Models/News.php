<?php
namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected $fillable = [
        'title', 'excerpt', 'content', 'published_at', 'company_id',
        'cover', 'lampiran', 'lampiran_nama',
    ];

    protected function casts(): array { return ['published_at' => 'date']; }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    /** Siapa saja yang sudah membuka pengumuman ini. */
    public function bacaan(): HasMany { return $this->hasMany(NewsRead::class); }

    /**
     * Ringkasan untuk daftar dan pop-out.
     *
     * Yang ditulis penulisnya lebih dulu; potongan otomatis hanya
     * cadangan. Potongan otomatis kerap terputus di tengah angka atau
     * nama lokasi, dan pada pengumuman kalimat pertama itulah yang
     * paling banyak dibaca — sering satu-satunya yang dibaca.
     */
    public function ringkasan(int $batas = 220): string
    {
        $ditulis = trim((string) $this->excerpt);

        return $ditulis !== ''
            ? $ditulis
            : \Illuminate\Support\Str::limit(strip_tags((string) $this->content), $batas);
    }
}
