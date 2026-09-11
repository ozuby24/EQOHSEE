<?php

namespace App\Models\Miners;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Miners\Concerns\PunyaAlur;
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use App\Support\Miners\Acuan;
use App\Support\Waktu;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Mine Permit — kartu yang dibawa ke gerbang.
 *
 * MENYIMPAN DUA DOKUMEN YANG MENDASARINYA sebagai kunci, bukan
 * menyimpulkannya dari tanggal. "Hasil MCU mana yang menjadi dasar
 * kartu ini" adalah pertanyaan pertama saat sebuah kartu dipersoalkan;
 * disimpulkan dari tanggal, jawabannya berubah begitu MCU berikutnya
 * terbit — dan berubah justru pada kartu yang sedang dipersoalkan.
 *
 * MASA BERLAKU MENGIKUTI SOP, BUKAN MENGIKUTI MCU.
 *
 * Project1 menyalin `mine_permits.tgl_expired = mcu_details.nom`,
 * sehingga permit berlaku sampai MCU berikutnya. SOP menetapkan lain:
 * permit berlaku sampai 31 Desember tahun berjalan, dan MCU yang
 * kedaluwarsa MENCABUT permit lebih awal. Dokumen kesesuaian di
 * Project1 sendiri menandai perilakunya sebagai bertentangan dengan SOP
 * dan menyarankan yang dipakai di sini.
 *
 * Akibatnya nyata: MCU bulan Maret memberi permit sampai Maret tahun
 * berikutnya pada Project1, padahal menurut SOP permit itu habis 31
 * Desember — sembilan bulan lebih longgar daripada yang diizinkan, pada
 * dokumen yang diperiksa Inspektur Tambang.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Permit extends Model
{
    use BerpemilikPerusahaan;
    use PunyaAlur;

    protected static string $jenisDokumen = 'permit';

    public const STATUS = [
        'draf'     => 'Draf',
        'diajukan' => 'Diajukan',
        'ohse'     => 'Verifikasi OHSE',
        'ktt'      => 'Menunggu Pengesahan KTT',
        'terbit'   => 'Terbit',
        'ditolak'  => 'Ditolak',
        'dicabut'  => 'Dicabut',
    ];

    /**
     * Zona akses — AREA MANA yang boleh dimasuki, SOP butir 5.7.
     *
     * UNRESTRICTED LEBIH LUAS DARIPADA RESTRICTED, meski namanya
     * terbaca sebaliknya oleh telinga Indonesia: Restricted di sini
     * berarti "dibatasi pada perkantoran", Unrestricted berarti "boleh
     * masuk area operasional sesuai yang diberikan". Daftar ini pernah
     * ditulis terbalik di sini — Unrestricted sebagai "area umum di
     * luar tambang aktif" — dan terbalik begitu ia menukar kewenangan
     * tersempit dengan yang terluas pada kartu yang dibaca petugas pos.
     */
    public const CAKUPAN = Acuan::ZONA_AKSES;

    /**
     * Warna strip kartu — UNIT APA yang boleh dikendarai.
     *
     * BUKAN peruntukan orangnya. Daftar ini semula berbunyi "putih =
     * karyawan tetap, hijau = kontraktor, biru = tamu" — arti yang
     * tidak ada pada satu pun SOP, dan yang membuat seorang kontraktor
     * pemegang SIMPER dump truck tercetak hijau, warna yang di gerbang
     * berarti "hanya light vehicle". Yang benar ada pada
     * 004-SPM-006.SOP-OHSE butir 7–8 dan disalin ke Acuan.
     */
    public const WARNA = Acuan::WARNA_KARTU;

    protected $table = 'mnr_permit';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draf'];

    protected function casts(): array
    {
        return [
            'tanggal'        => 'date',
            'berlaku_sampai' => 'date',
            'tanggal_cabut'  => 'date',
        ];
    }

    /* ═══════════ relasi ═══════════ */

    public function company(): BelongsTo     { return $this->belongsTo(Company::class); }
    public function kontraktor(): BelongsTo  { return $this->belongsTo(Company::class, 'kontraktor_id'); }
    public function user(): BelongsTo        { return $this->belongsTo(User::class); }
    public function pekerja(): BelongsTo     { return $this->belongsTo(Pekerja::class, 'pekerja_id'); }
    public function mcuOrang(): BelongsTo    { return $this->belongsTo(McuOrang::class, 'mcu_orang_id'); }
    public function induksiOrang(): BelongsTo{ return $this->belongsTo(InduksiOrang::class, 'induksi_orang_id'); }
    public function tipe(): BelongsTo        { return $this->belongsTo(TipePermit::class, 'tipe_permit_id'); }
    public function kategori(): BelongsTo    { return $this->belongsTo(KategoriPermit::class, 'kategori_permit_id'); }

    public function berkas(): HasMany
    {
        return $this->hasMany(PermitBerkas::class, 'permit_id')->orderBy('jenis');
    }

    public function simper(): HasMany
    {
        return $this->hasMany(Simper::class, 'permit_id')->orderByDesc('tanggal');
    }

    /* ═══════════ masa berlaku ═══════════ */

    /**
     * Tanggal habis menurut SOP, beserta dari mana ia berasal.
     *
     * Tipe yang punya `hari_berlaku` — Visitor 7 hari, Temporary sebulan —
     * dihitung dari tanggal terbit. Sisanya mengikuti aturan tahunan:
     * 31 Desember tahun terbit.
     *
     * @return array{0:\Illuminate\Support\Carbon,1:string}
     */
    public static function hitungBerlaku(?TipePermit $tipe, ?Carbon $terbit = null): array
    {
        $terbit ??= Waktu::kini();

        $hari = $tipe?->hari_berlaku;

        /* NULL yang membedakan, bukan nol. `$hari ? … : …` memperlakukan
           0 sama dengan NULL, dan tipe permit berhari nol — izin sehari
           untuk kunjungan satu hari — akan diam-diam berlaku sampai 31
           Desember. Sepuluh bulan lebih panjang daripada yang tertulis
           di formulirnya, pada dokumen yang dibaca petugas pos. */
        return $hari === null
            ? [$terbit->copy()->endOfYear()->startOfDay(), 'tahunan']
            : [$terbit->copy()->addDays((int) $hari)->startOfDay(), 'tipe'];
    }

    /**
     * Kapan kartu ini sesungguhnya berhenti berlaku.
     *
     * YANG MANA PUN YANG LEBIH DAHULU antara masa berlakunya sendiri dan
     * masa berlaku MCU-nya. SOP: "MCU ulang paling lambat 2 minggu
     * sebelum periode MCU tahunan berakhir, jika belum maka Mine Permit
     * dan SIMPER akan dicabut dan tidak ada toleransi."
     *
     * Dihitung, bukan disimpan: MCU dapat kedaluwarsa kapan saja tanpa
     * ada yang menyentuh baris permit, dan kolom yang disimpan akan
     * menyatakan kartu masih berlaku sampai seseorang menyegarkannya.
     */
    public function habisEfektif(): ?Carbon
    {
        $sendiri = $this->berlaku_sampai;
        $mcu     = $this->mcuOrang?->berlaku_sampai;

        if ($sendiri === null) return $mcu;
        if ($mcu === null)     return $sendiri;

        return $mcu->lt($sendiri) ? $mcu : $sendiri;
    }

    public function berlaku(): bool
    {
        if ($this->status !== 'terbit') return false;

        $habis = $this->habisEfektif();

        return $habis === null || $habis->gte(Waktu::kini()->startOfDay());
    }

    public function sisaHari(): ?int
    {
        $habis = $this->habisEfektif();

        return $habis === null
            ? null
            : (int) Waktu::kini()->startOfDay()->diffInDays($habis, false);
    }

    /** Dicabut karena MCU-nya habis lebih dahulu. */
    public function gugurKarenaMcu(): bool
    {
        $mcu = $this->mcuOrang?->berlaku_sampai;

        return $mcu !== null
            && $mcu->lt(Waktu::kini()->startOfDay())
            && ($this->berlaku_sampai === null || $this->berlaku_sampai->gte(Waktu::kini()->startOfDay()));
    }

    /* ═══════════ kueri ═══════════ */

    public function scopeTerbit(Builder $q): Builder
    {
        return $q->where('status', 'terbit');
    }
}
