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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * SIMPER — izin mengemudikan unit di dalam wilayah tambang.
 *
 * MENEMPEL PADA PERMIT, bukan langsung pada orangnya. Permit dicabut
 * berarti SIMPER ikut gugur, dan hubungan itu harus terbaca dari
 * barisnya sendiri — bukan disimpulkan dengan mencari permit yang
 * kebetulan milik orang yang sama.
 *
 * TIGA SUMBER MASA BERLAKU, DAN YANG PALING AWAL YANG MENANG:
 * masa berlakunya sendiri, masa berlaku permitnya, dan masa berlaku SIM
 * Kepolisian. SOP: "SIMPOL habis → SIMPER otomatis tidak berlaku."
 */
#[ScopedBy(MilikPerusahaan::class)]
class Simper extends Model
{
    use BerpemilikPerusahaan;
    use PunyaAlur;

    protected static string $jenisDokumen = 'simper';

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
     * Lima kelas SIMPER dan kelas SIM Kepolisian yang dikenali.
     *
     * KEDUANYA DIBACA DARI ACUAN, bukan diketik ulang di sini. Pada
     * Project1 daftar kelas SIMPER diketik ulang sebagai <option> di
     * lima berkas Blade, dan hasilnya persis yang dapat diduga: satu
     * berkas menawarkan kelas yang tidak ada di berkas lain, dan kartu
     * terbit dengan kelas yang tidak dikenali layar pemantauannya.
     *
     * Kelas SIMPOL di sini sebelumnya memuat B2 dan C — dua kelas yang
     * TIDAK ADA pada matriks SOP. Menawarkan kelas yang tidak diakui
     * berarti operator dapat memilih SIM yang tidak pernah dicocokkan
     * dengan golongan unitnya, dan pemeriksaan kecocokan itu justru
     * alasan matriksnya ada.
     */
    public const KELAS = Acuan::KELAS_SIMPER;

    public const JENIS_SIMPOL = Acuan::KELAS_SIMPOL;

    protected $table = 'mnr_simper';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draf', 'aktif' => true];

    /**
     * Jenis SIM — pilihan `jenis_sim` Safe Track, termasuk SIO bagi yang
     * tidak memegang SIM kepolisian. Nilai lama ('A', 'B1', …) tetap sah.
     */
    public const JENIS_SIM = ['SIM A', 'SIM B1', 'SIM B1 Umum', 'SIM B2 Umum', 'SIO sertifikat/Kompetensi'];

    public const SUMBER_SIM = ['auto' => 'terbaca otomatis', 'manual' => 'diisi manual', 'perpanjangan' => 'perpanjangan'];

    protected static function booted(): void
    {
        /* Nomor `SIMPER-00{id}` Safe Track, bila tidak diisi. */
        static::created(function (self $s) {
            if (blank($s->no_simper)) {
                $s->forceFill(['no_simper' => 'SIMPER-00'.$s->getKey()])->saveQuietly();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'tanggal'                => 'date',
            'berlaku_sampai'         => 'date',
            'simpol_berlaku_sampai'  => 'date',
            'tanggal_nonaktif'       => 'date',
            'aktif'                  => 'boolean',
            'simpol_meta'            => 'array',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function permit(): BelongsTo  { return $this->belongsTo(Permit::class, 'permit_id'); }
    public function pekerja(): BelongsTo { return $this->belongsTo(Pekerja::class, 'pekerja_id'); }

    public function unit(): HasMany
    {
        return $this->hasMany(SimperUnit::class, 'simper_id')->orderBy('id');
    }

    public function ajuan(): HasMany
    {
        return $this->hasMany(SimperAjuan::class, 'simper_id')->orderByDesc('tanggal');
    }

    /** @return array<string,string> kode kelas => label, siap jadi daftar pilih. */
    public static function pilihanKelas(): array
    {
        return array_map(fn (array $k) => $k['label'], self::KELAS);
    }

    /* ═══════════ masa berlaku ═══════════ */

    /**
     * Tanggal habis SIMPER = TANGGAL SIM/SIMPOL-nya (Safe Track:
     * `sim_expired`), keputusan pemilik. Bukan lagi tanggal tersempit
     * dari 31 Desember, SIM, dan Mine Permit.
     *
     * Baris lama tanpa tanggal SIM tetap memakai `berlaku_sampai`-nya,
     * supaya tidak ada kartu lama yang mendadak terbaca "belum diisi".
     */
    public function habisEfektif(): ?Carbon
    {
        return $this->simpol_berlaku_sampai ?? $this->berlaku_sampai;
    }

    /** Terbit, aktif, dan tanggal SIM-nya belum lewat. */
    public function berlaku(): bool
    {
        if ($this->status !== 'terbit' || ! $this->aktif) return false;

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

    /**
     * Boleh diajukan perpanjangan — `masterdatauserext` Safe Track:
     * SIMPER disetujui, aktif, dan tidak sedang punya perpanjangan yang
     * berjalan. Safe Track tidak membatasi jendela waktunya.
     */
    public function bolehDiperpanjang(): bool
    {
        if ($this->status !== 'terbit' || ! $this->aktif) return false;

        return ! $this->ajuan()->where('jenis', 'perpanjangan')
            ->whereIn('status', SimperAjuan::BERJALAN)->exists();
    }

    /** Sumber tanggal SIM — 'auto' | 'manual' | 'perpanjangan'. */
    public function penyebabHabis(): ?string
    {
        if ($this->habisEfektif() === null) return null;

        return $this->simpol_berlaku_sampai ? 'simpol' : 'simper';
    }

    /**
     * Unit dari Penambahan Unit yang sudah DISETUJUI penuh — baris baru
     * pada pengajuannya (bukan salinan unit lama). Safe Track tidak
     * menggabungkannya ke `form_simper_details`; kartu dan rincian
     * membacanya dari sini.
     *
     * @return \Illuminate\Support\Collection<int,SimperAjuanUnit>
     */
    public function unitTambahan(): \Illuminate\Support\Collection
    {
        return SimperAjuanUnit::query()->with(['kendaraan', 'jenisUnitBanyak', 'jenisUnit'])
            ->whereNull('simper_unit_id')
            ->whereIn('ajuan_id', SimperAjuan::withoutGlobalScopes()->where('simper_id', $this->id)
                ->where('jenis', 'penambahan')->where('status', 'selesai')->select('id'))
            ->orderBy('id')->get();
    }

    /**
     * Syarat "Sending" — berkas wajib Safe Track dan sedikitnya satu unit.
     *
     * @return list<string>
     */
    public function syaratKirim(): array
    {
        $kurang = [];

        foreach (['berkas_simpol' => 'berkas SIM', 'berkas_ddt' => 'file DDT',
                  'berkas_permohonan' => 'permohonan SIMPER', 'berkas_pengalaman' => 'pengalaman kerja'] as $k => $label) {
            if (blank($this->$k)) $kurang[] = $label;
        }

        if (! $this->unit()->exists()) $kurang[] = 'sedikitnya satu unit';

        return $kurang;
    }

    /** IUP penerbit — dari Mine Permit dasarnya. */
    public function iupPenerbitAwal(): ?int
    {
        if ($this->iup_id) return (int) $this->iup_id;

        $iup = $this->permit_id
            ? Permit::withoutGlobalScopes()->whereKey($this->permit_id)->value('iup_id')
            : null;

        return $iup ? (int) $iup : \App\Support\Miners\Penyetuju::iupDari(auth()->user()?->company_id);
    }
}
