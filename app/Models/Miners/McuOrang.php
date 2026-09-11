<?php

namespace App\Models\Miners;

use App\Models\Concerns\BerindukPerusahaan;
use App\Support\Miners\Acuan;
use App\Support\Waktu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Hasil MCU satu orang di dalam satu surat.
 *
 * IDENTITASNYA DISALIN, bukan hanya ditunjuk. Surat yang sudah dikirim
 * ke klinik tidak boleh berubah isinya ketika nama atau jabatan orangnya
 * disunting setahun kemudian — yang dipegang klinik adalah kertas, dan
 * kertas itu tidak ikut berubah. Penunjuk ke `pekerja_id` tetap ada
 * untuk menelusuri riwayat orangnya.
 */
class McuOrang extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'mcu';

    /**
     * Berapa lama hasil MCU berlaku — satu tahun menurut SOP.
     *
     * Angkanya dibaca dari App\Support\Miners\Acuan, bukan diketik
     * ulang di sini. Angka SOP yang disalin ke beberapa tempat akan
     * berbeda cepat atau lambat, dan yang berbeda tidak menimbulkan
     * galat — hanya dua layar yang menghitung masa berlaku tidak sama
     * untuk orang yang sama.
     */
    public const BULAN_BERLAKU = Acuan::MASA['mcu_bulan'];

    /**
     * Ambang pengingat MCU ulang.
     *
     * SOP: "MCU ulang paling lambat 2 minggu sebelum periode MCU tahunan
     * berakhir, jika belum maka Mine Permit dan SIMPER akan dicabut dan
     * tidak ada toleransi."
     */
    public const HARI_PERINGATAN = Acuan::MASA['peringatan_mcu_hari'];

    protected $table = 'mnr_mcu_orang';

    protected $guarded = ['id'];

    protected $attributes = ['aktif' => true];

    protected function casts(): array
    {
        return [
            'tanggal_periksa'  => 'date',
            'tanggal_berikut'  => 'date',
            'berlaku_sampai'   => 'date',
            'tenggat_rujukan'  => 'date',
            'tanggal_nonaktif' => 'date',
            'aktif'            => 'boolean',
            'usia'             => 'integer',
        ];
    }

    public function mcu(): BelongsTo        { return $this->belongsTo(Mcu::class, 'mcu_id'); }
    public function pekerja(): BelongsTo    { return $this->belongsTo(Pekerja::class, 'pekerja_id'); }
    public function departemen(): BelongsTo { return $this->belongsTo(Departemen::class, 'departemen_id'); }
    public function hasil(): BelongsTo      { return $this->belongsTo(HasilMcu::class, 'hasil_id'); }

    public function rujukan(): HasMany
    {
        return $this->hasMany(McuRujukan::class, 'mcu_orang_id')->orderByDesc('tanggal_surat');
    }

    /* ═══════════ keadaan ═══════════ */

    /** Hasilnya membolehkan Mine Permit terbit. */
    public function layak(): bool
    {
        return (bool) $this->hasil?->layak;
    }

    /**
     * Sudah lewat masa berlakunya menurut waktu tambang.
     *
     * Dibandingkan di zona tambang, bukan UTC. Kartu yang habis pada
     * tanggal 1 pukul 00.00 waktu setempat masih terbaca berlaku selama
     * delapan jam bila dibandingkan dengan UTC — delapan jam yang
     * dipakai orang masuk ke area tambang.
     */
    public function kedaluwarsa(): bool
    {
        return $this->berlaku_sampai !== null
            && $this->berlaku_sampai->lt(Waktu::kini()->startOfDay());
    }

    /** Sisa hari sampai habis; negatif berarti sudah lewat. */
    public function sisaHari(): ?int
    {
        return $this->berlaku_sampai === null
            ? null
            : (int) Waktu::kini()->startOfDay()->diffInDays($this->berlaku_sampai, false);
    }

    /** Sudah masuk ambang pengingat SOP — dua minggu sebelum berakhir. */
    public function mendekatiHabis(): bool
    {
        $sisa = $this->sisaHari();

        return $sisa !== null && $sisa >= 0 && $sisa <= self::HARI_PERINGATAN;
    }

    /* ═══════════ kueri ═══════════ */

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif', true);
    }

    /**
     * Hasil terbaru yang masih berlaku bagi seorang pekerja.
     *
     * Dipakai saat menerbitkan Mine Permit: yang menjadi dasar kartu
     * adalah hasil TERBARU yang masih berlaku, bukan hasil mana pun yang
     * kebetulan ditemukan lebih dulu.
     */
    public static function berlakuUntuk(int $pekerjaId): ?self
    {
        return static::query()
            ->where('pekerja_id', $pekerjaId)
            ->where('aktif', true)
            ->whereDate('berlaku_sampai', '>=', Waktu::kini()->toDateString())
            ->orderByDesc('berlaku_sampai')
            ->first();
    }
}
