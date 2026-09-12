<?php

namespace App\Models\Hr;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Miners\Blok;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Mesin absensi di lapangan.
 *
 * BERTOKEN SENDIRI, bukan memakai akun pengguna. Alat pindai tidak
 * punya orang yang masuk ke dalamnya; ia mengirim ke endpoint dengan
 * kuncinya sendiri, dan begitulah push SDK ZKTeco dan Hikvision
 * bekerja. Dipaksa memakai token pengguna, kredensial seorang manusia
 * harus ditanam di dalam alat yang dipasang di pos jaga — dan yang
 * mencabut alat itu dari dindingnya membawa pulang akun seseorang.
 */
#[ScopedBy(MilikPerusahaan::class)]
class MesinAbsensi extends Model
{
    use BerpemilikPerusahaan;

    public const MEREK = [
        'zkteco'    => 'ZKTeco',
        'hikvision' => 'Hikvision',
        'lainnya'   => 'Lainnya',
    ];

    protected $table = 'hr_mesin_absensi';

    protected $guarded = ['id'];

    /**
     * Hash token TIDAK PERNAH ikut terserialkan.
     *
     * Halaman daftar mesin mengirim seluruh kolomnya ke peramban, dan
     * hash yang sampai ke sana dapat diserang tanpa batas kecepatan di
     * mesin penyerang sendiri.
     */
    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return ['terakhir_hubung' => 'datetime', 'aktif' => 'boolean'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function blok(): BelongsTo    { return $this->belongsTo(Blok::class, 'blok_id'); }

    public function jejak(): HasMany
    {
        return $this->hasMany(AbsensiJejak::class, 'mesin_id');
    }

    /**
     * Terbitkan token baru; kembalikan yang TERANG sekali saja.
     *
     * Yang tersimpan hanyalah hash-nya. Alat yang dicuri dari pos jaga
     * membawa tokennya, dan yang tersimpan di sini tidak boleh dapat
     * dibaca balik oleh siapa pun yang membuka basis datanya. Hilang
     * berarti diterbitkan ulang — memang begitu seharusnya.
     */
    public function terbitkanToken(): string
    {
        $terang = Str::random(48);

        $this->forceFill(['token_hash' => Hash::make($terang)])->save();

        return $terang;
    }

    public function tokenCocok(string $terang): bool
    {
        return $this->token_hash !== null && Hash::check($terang, $this->token_hash);
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif', true);
    }
}
