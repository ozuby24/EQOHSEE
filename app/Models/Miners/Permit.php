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
 * MASA BERLAKU MENGIKUTI MCU — aturan Safe Track (keputusan pemilik,
 * 7 Nov 2026). `berlaku_sampai` = masa berlaku MCU dasarnya
 * (`sumber_berlaku = mcu`), diselaraskan setiap malam oleh
 * `miners:sinkron`; admin dapat MENGUNCI tanggal tertentu (`manual`),
 * dan perpanjangan yang disetujui menulis tanggal barunya
 * (`perpanjangan`). Aturan lama — 31 Desember atau jumlah hari tipe
 * permit — tidak dipakai lagi; tanggal lama kartu yang sudah ada
 * tersimpan di `berlaku_asal`.
 *
 * TIGA PEMILIK, TIGA ARTI. `iup_id` IUP penerbit (pemegang izin tambang
 * tempat kartu berlaku), `kontraktor_id` IUJP pemberi kerja (kosong bagi
 * karyawan IUP sendiri), `company_id` pemberi kerjanya — yang menentukan
 * siapa melihat barisnya, sama seperti COALESCE(contractor_id, corps_id)
 * Safe Track.
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

        /* Ditandai `miners:sinkron` saat tanggalnya lewat — status 4
           Safe Track. Perpanjangan yang disetujui mengembalikannya ke
           terbit. */
        'kedaluwarsa' => 'Expired',

        /* Status lama EQOHSEE — kartu kini dinonaktifkan lewat `aktif`. */
        'dicabut'  => 'Dicabut',
    ];

    /** Status yang berarti kartunya pernah terbit dan punya masa berlaku. */
    public const TERBIT = ['terbit', 'kedaluwarsa'];

    /** Asal tanggal habis — `expired_source` Safe Track. */
    public const SUMBER = [
        'mcu'          => 'ikut MCU',
        'manual'       => 'dikunci manual',
        'perpanjangan' => 'perpanjangan',
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

    protected $attributes = ['status' => 'draf', 'aktif' => true];

    /** Jenis kartu — `typecard` Safe Track. */
    public const JENIS_KARTU = ['Mine Permit', 'Magazine', 'Blasting'];

    protected function casts(): array
    {
        return [
            'tanggal'          => 'date',
            'berlaku_sampai'   => 'date',
            'tanggal_cabut'    => 'date',
            'tanggal_nonaktif' => 'date',
            'aktif'            => 'boolean',
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

    public function perpanjangan(): HasMany
    {
        return $this->hasMany(PermitPerpanjangan::class, 'permit_id')->orderByDesc('tanggal');
    }

    /* ═══════════ masa berlaku ═══════════ */

    /**
     * Kapan kartu ini berhenti berlaku — `tgl_expired ?: nom` Safe Track.
     *
     * Kartu yang MENGIKUTI MCU membaca tanggal MCU-nya LANGSUNG, bukan
     * salinan yang disimpan: perpanjangan dan ralat tanggal MCU berlaku
     * seketika, tidak menunggu sinkron malam. Kunci manual dan tanggal
     * perpanjangan dibaca dari kolomnya sendiri.
     */
    public function habisEfektif(): ?Carbon
    {
        $mcu = $this->mcuOrang?->berlaku_sampai;

        if (in_array($this->sumber_berlaku, [null, 'mcu'], true) && $mcu) {
            return $mcu;
        }

        return $this->berlaku_sampai ?? $mcu;
    }

    /** Tanggal yang SEHARUSNYA tersimpan bila kartu ini mengikuti MCU. */
    public function tanggalDariMcu(): ?Carbon
    {
        return $this->mcuOrang?->berlaku_sampai;
    }

    public function berlaku(): bool
    {
        if (! in_array($this->status, self::TERBIT, true) || ! $this->aktif) return false;

        $habis = $this->habisEfektif();

        return $habis === null || Waktu::sisaHari($habis) >= 0;
    }

    public function sisaHari(): ?int
    {
        return Waktu::sisaHari($this->habisEfektif());
    }

    /* ═══════════ nomor ═══════════ */

    /**
     * Nomor registrasi — `{IUP}.{IUJP}.{Ymd}00{id}` Safe Track.
     *
     * Bagian yang memang tidak ada DILEWATI, bukan diisi tanda hubung:
     * karyawan IUP tidak punya bagian kontraktor, dan nomornya memang
     * lebih pendek satu ruas — IBP.2026090600352 di samping
     * IBP.PTP.2026090600353. Itu keterangan yang terbaca langsung dari
     * nomornya, bukan nomor yang cacat. Unik karena memuat id barisnya.
     */
    public function susunNomor(): string
    {
        $bagian = array_values(array_filter([
            trim((string) Company::query()->whereKey($this->iup_id)->value('code')),
            trim((string) ($this->kontraktor_id ? Company::query()->whereKey($this->kontraktor_id)->value('code') : '')),
        ], fn ($b) => $b !== ''));

        $tanggal = ($this->tanggal ?? Waktu::kini())->format('Ymd');

        return ($bagian ? implode('.', $bagian).'.' : '').$tanggal.'00'.$this->getKey();
    }

    /* ═══════════ kueri ═══════════ */

    public function scopeTerbit(Builder $q): Builder
    {
        return $q->where('status', 'terbit');
    }

    /** Kartu aktif — belum dinonaktifkan admin. */
    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif', true);
    }
}
