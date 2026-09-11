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

    protected $attributes = ['status' => 'draf'];

    protected function casts(): array
    {
        return [
            'tanggal'                => 'date',
            'berlaku_sampai'         => 'date',
            'simpol_berlaku_sampai'  => 'date',
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
     * Kapan SIMPER ini sesungguhnya berhenti berlaku.
     *
     * Yang paling awal di antara tiga: masa berlakunya sendiri, masa
     * berlaku permitnya, dan masa berlaku SIMPOL. Ketiganya dapat habis
     * tanpa ada yang menyentuh baris ini, jadi dihitung — bukan
     * disimpan. Kolom yang disimpan akan menyatakan kartu masih berlaku
     * sampai seseorang menyegarkannya, dan yang menyegarkannya bukan
     * orang yang berdiri di gerbang.
     */
    public function habisEfektif(): ?Carbon
    {
        $calon = array_filter([
            $this->berlaku_sampai,
            $this->simpol_berlaku_sampai,
            $this->permit?->habisEfektif(),
        ]);

        if ($calon === []) return null;

        usort($calon, fn (Carbon $a, Carbon $b) => $a <=> $b);

        return $calon[0];
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

    /**
     * Apa yang lebih dahulu menghentikannya — untuk dijelaskan di layar.
     *
     * "Kartu habis 12 Maret" tidak memberi tahu apa yang harus
     * diperbarui; "SIMPOL habis 12 Maret" memberi tahu.
     */
    public function penyebabHabis(): ?string
    {
        $habis = $this->habisEfektif();
        if ($habis === null) return null;

        if ($this->simpol_berlaku_sampai?->eq($habis)) return 'simpol';
        if ($this->permit?->habisEfektif()?->eq($habis)) return 'permit';

        return 'simper';
    }
}
