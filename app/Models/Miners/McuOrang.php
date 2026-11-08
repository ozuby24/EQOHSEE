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

    /** Jenis pemeriksaan — `statusmcu` Safe Track. */
    public const JENIS_MCU = ['Awal', 'Berkala', 'Khusus'];

    /** Tingkat risiko pekerjaan — `levelresiko` Safe Track. */
    public const LEVEL_RISIKO = ['Rendah', 'Sedang', 'Tinggi'];

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
            'bulan_berlaku'    => 'integer',
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

    /** Mine Permit yang berdasar MCU ini. */
    public function permit(): HasMany
    {
        return $this->hasMany(Permit::class, 'mcu_orang_id')->orderByDesc('id');
    }

    /* ═══════════ aturan Safe Track ═══════════ */

    /**
     * Masa berlaku bawaan saat manpower ditambahkan: satu tahun sejak
     * tanggal pemeriksaan (`masaBerlaku()` mcu_detailsController).
     *
     * Tanggal yang DIKIRIM dihormati bila memang tanggal dan tidak lebih
     * awal daripada tanggal pemeriksaannya — "kedaluwarsa sebelum
     * diperiksa" hampir pasti salah ketik (tertukar dengan tanggal
     * lahir), dan dibiarkan jatuh ke bawaan daripada menyimpan tanggal
     * yang jelas keliru. Dokter menetapkan masa yang sebenarnya (3, 6,
     * atau 12 bulan) saat menyetujui suratnya.
     */
    public static function masaBerlaku(?\DateTimeInterface $periksa, ?\DateTimeInterface $kirim = null): ?\Illuminate\Support\Carbon
    {
        $periksa = $periksa ? \Illuminate\Support\Carbon::instance($periksa)->startOfDay() : null;

        if ($kirim) {
            $kirim = \Illuminate\Support\Carbon::instance($kirim)->startOfDay();

            if ($periksa === null || $kirim->gte($periksa)) return $kirim;
        }

        return $periksa?->copy()->addYear();
    }

    /**
     * Nomor induk SEMENTARA — `MCU-` + sepuluh angka terakhir NIK.
     *
     * MCU langkah paling awal; nomor induk yang sebenarnya baru ada saat
     * Mine Permit terbit. Berbasis NIK supaya satu orang selalu mendapat
     * nomor sementara yang sama walau MCU-nya diajukan berulang.
     */
    public static function nomorSementara(?string $nik): string
    {
        $nik = preg_replace('/[^0-9A-Za-z]/', '', (string) $nik);

        return $nik === ''
            ? 'MCU-'.strtoupper(\Illuminate\Support\Str::random(8))
            : 'MCU-'.strtoupper(substr($nik, -10));
    }

    /** Hasilnya belum ditetapkan dokter — kosong, atau "Belum Dinilai". */
    public function belumDinilai(): bool
    {
        return $this->hasil_id === null || (bool) $this->hasil?->belum();
    }

    /** Hasilnya "Rujukan". */
    public function dirujuk(): bool
    {
        return mb_strtolower(trim((string) $this->hasil?->nama)) === 'rujukan';
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
