<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Reklamasi;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu petak lahan terganggu.
 *
 * Menyimpan tahapan terjauh yang dicapainya, bukan luas per tahapan.
 * Tahapan reklamasi bertingkat, dan luas terpisah untuk tiap tahapan
 * mengundang penjumlahan yang menghitung petak yang sama berkali-kali.
 */
#[ScopedBy(MilikPerusahaan::class)]
class LingkunganArea extends Model
{
    use BerpemilikPerusahaan;

    public const JENIS = ['bukaan', 'timbunan', 'jalan', 'fasilitas', 'kolam'];

    protected $fillable = [
        'company_id', 'user_id', 'kode', 'nama', 'jenis', 'luas_ha', 'tahap',
        'tanggal_buka', 'tanggal_selesai_tambang', 'rencana_selesai_reklamasi',
        'pohon_rencana', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'luas_ha'                   => 'float',
            'tanggal_buka'              => 'date',
            'tanggal_selesai_tambang'   => 'date',
            'rencana_selesai_reklamasi' => 'date',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function kemajuan(): HasMany  { return $this->hasMany(ReklamasiKemajuan::class); }

    /** Masih ditambang selama tanggal berhentinya belum diisi. */
    public function masihDitambang(): bool
    {
        return $this->tanggal_selesai_tambang === null;
    }

    /**
     * Lamanya petak ini menganggur, hari. Null selama masih ditambang.
     *
     * Petak yang sudah tuntas direklamasi berhenti menua: jamnya
     * dihentikan pada saat tuntas, bukan terus berjalan sampai hari ini.
     */
    public function mengangurHari(): ?int
    {
        if ($this->masihDitambang() || Reklamasi::selesai($this->tahap)) return null;

        return (int) $this->tanggal_selesai_tambang->startOfDay()->diffInDays(now()->startOfDay());
    }

    /** Sisa hari terhadap rencana penyelesaian reklamasi; negatif berarti terlewat. */
    public function sisaHariRencana(): ?int
    {
        if (!$this->rencana_selesai_reklamasi || Reklamasi::selesai($this->tahap)) return null;

        return (int) round(now()->startOfDay()->diffInDays(
            $this->rencana_selesai_reklamasi->startOfDay(), false
        ));
    }

    /** Kemajuan disetujui terakhir — sumber tingkat tumbuh yang berlaku. */
    public function kemajuanTerakhir(): ?ReklamasiKemajuan
    {
        return $this->kemajuan
            ->whereIn('status', \App\Support\Alur::terhitung())
            ->sortByDesc(fn (ReklamasiKemajuan $k) => $k->tanggal?->toDateString())
            ->first();
    }

    public function toView(): array
    {
        $terakhir = $this->kemajuanTerakhir();

        return [
            'id'      => $this->id,
            'kode'    => $this->kode,
            'nama'    => $this->nama,
            'jenis'   => $this->jenis,
            'luas_ha' => $this->luas_ha,

            'tahap'      => $this->tahap,
            'tahapLabel' => Reklamasi::TAHAP[$this->tahap] ?? $this->tahap,
            'berikutnya' => Reklamasi::berikutnya($this->tahap),
            'selesai'    => Reklamasi::selesai($this->tahap),

            'masihDitambang'  => $this->masihDitambang(),
            'mengangurHari'   => $this->mengangurHari(),
            'sisaHariRencana' => $this->sisaHariRencana(),

            'tanggal_buka'              => $this->tanggal_buka?->toDateString(),
            'tanggal_selesai_tambang'   => $this->tanggal_selesai_tambang?->toDateString(),
            'rencana_selesai_reklamasi' => $this->rencana_selesai_reklamasi?->toDateString(),

            'pohon_rencana' => $this->pohon_rencana,
            'pohonDitanam'  => (int) $this->kemajuan
                ->whereIn('status', \App\Support\Alur::terhitung())->sum('pohon_ditanam'),
            'tingkatTumbuh' => $terakhir?->tingkat_tumbuh_persen,
            'tumbuhTanggal' => $terakhir?->tanggal?->toDateString(),

            'catatan' => $this->catatan,
        ];
    }

    /** Bentuk ringkas untuk NeracaLahan. */
    public function untukNeraca(): array
    {
        return [
            'luas_ha'         => $this->luas_ha,
            'tahap'           => $this->tahap,
            'menganggur_hari' => $this->mengangurHari(),
        ];
    }
}
