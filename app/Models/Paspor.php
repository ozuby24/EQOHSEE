<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Authority;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Berkas kelayakan kerja satu orang.
 *
 * Menyatukan tiga hal yang selama ini tersimpan di tempat berbeda:
 * kompetensi, MCU, dan kartu masuk tambang. Satu layar menjawab
 * pertanyaan gerbang — boleh atau tidak orang ini bekerja hari ini —
 * dan menyebut sebabnya bila tidak.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Paspor extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'paspor';

    protected $fillable = [
        'company_id', 'user_id', 'nomor_register', 'nama', 'nik', 'jabatan',
        'departemen', 'klasifikasi', 'status', 'tgl_bergabung', 'foto', 'catatan',
    ];

    protected function casts(): array
    {
        return ['tgl_bergabung' => 'date'];
    }

    /* ═══════════ relasi ═══════════ */

    public function user()    { return $this->belongsTo(User::class); }
    public function company() { return $this->belongsTo(Company::class); }

    public function sertifikat()
    {
        return $this->hasMany(PasporSertifikat::class)->orderBy('tgl_expired');
    }

    public function mcu()
    {
        return $this->hasMany(PasporMcu::class)->orderByDesc('tgl_periksa');
    }

    public function kartu()
    {
        return $this->hasMany(PasporKartu::class)->orderByDesc('tgl_terbit');
    }

    /* ═══════════ yang berlaku sekarang ═══════════ */

    /**
     * MCU TERAKHIR, bukan MCU yang masih berlaku.
     *
     * Bedanya penting: bila MCU terakhir sudah kadaluarsa, yang harus
     * tampil adalah MCU kadaluarsa itu — bukan MCU sebelumnya yang
     * kebetulan belum lewat karena masa berlakunya lebih panjang, dan
     * bukan pula kosong. Orangnya memang tidak layak, dan layarnya
     * harus mengatakan itu.
     */
    public function mcuTerakhir(): ?PasporMcu
    {
        return $this->relationLoaded('mcu')
            ? $this->mcu->sortByDesc('tgl_periksa')->first()
            : $this->mcu()->first();
    }

    public function kartuTerakhir(): ?PasporKartu
    {
        return $this->relationLoaded('kartu')
            ? $this->kartu->sortByDesc('tgl_terbit')->first()
            : $this->kartu()->first();
    }

    /* ═══════════ kelayakan ═══════════ */

    /** @return array{layak:bool, sebab:list<string>} */
    public function kelayakan(): array
    {
        $m = $this->mcuTerakhir();
        $k = $this->kartuTerakhir();

        return Authority::kelayakan(
            $m?->tgl_expired?->toDateString(),
            $m?->hasil,
            $k?->tgl_expired?->toDateString(),
        );
    }

    /**
     * Keadaan paling mendesak di antara SELURUH berkasnya.
     *
     * Orang yang MCU-nya aman tetapi satu sertifikatnya kadaluarsa
     * adalah orang yang perlu ditindak, dan daftar yang menampilkan
     * keadaan terbaiknya akan menyembunyikan itu.
     */
    public function keadaanTerburuk(): string
    {
        $tanggal = collect()
            ->merge($this->sertifikat->pluck('tgl_expired'))
            ->merge($this->mcu->pluck('tgl_expired'))
            ->merge($this->kartu->pluck('tgl_expired'))
            ->filter();

        if ($tanggal->isEmpty()) return Authority::TAK_BERTANGGAL;

        return $tanggal
            ->map(fn ($t) => Authority::keadaan($t))
            ->sortBy(fn ($k) => Authority::URUT[$k] ?? 99)
            ->first();
    }

    public function labelKlasifikasi(): ?string
    {
        return $this->klasifikasi
            ? (Authority::KLASIFIKASI[$this->klasifikasi] ?? $this->klasifikasi)
            : null;
    }
}
