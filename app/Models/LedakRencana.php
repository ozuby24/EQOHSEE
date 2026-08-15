<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Concerns\Ditinjau;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Peledakan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Rancangan satu peledakan.
 *
 * Alur tinjauan di sini berbeda maknanya dari modul lain. Pada produksi
 * dan lingkungan, tinjauan menjaga angka yang keluar dari perusahaan;
 * di sini ia adalah persetujuan atas pekerjaan yang BELUM dilakukan.
 * Rancangan yang disetujui berarti boleh diledakkan — dan itulah satu-
 * satunya alur di aplikasi ini yang persetujuannya mendahului
 * pekerjaannya, bukan menyusulnya.
 */
#[ScopedBy(MilikPerusahaan::class)]
class LedakRencana extends Model
{
    use BerpemilikPerusahaan;
    use Ditinjau;

    public const POLA = ['selang-seling', 'persegi'];

    protected $fillable = [
        'company_id', 'user_id', 'kode', 'lokasi', 'tanggal_rencana', 'jenis_batuan',
        'faktor_batuan', 'diameter_lubang_mm', 'burden_m', 'spasi_m', 'kedalaman_m',
        'subdrill_m', 'stemming_m', 'tinggi_jenjang_m', 'jumlah_lubang', 'pola',
        'bahan_peledak', 'kekuatan_relatif', 'isi_per_lubang_kg', 'isi_per_tunda_kg',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_rencana'    => 'date',
            'faktor_batuan'      => 'float',
            'diameter_lubang_mm' => 'float',
            'burden_m'           => 'float',
            'spasi_m'            => 'float',
            'kedalaman_m'        => 'float',
            'subdrill_m'         => 'float',
            'stemming_m'         => 'float',
            'tinggi_jenjang_m'   => 'float',
            'kekuatan_relatif'   => 'float',
            'isi_per_lubang_kg'  => 'float',
            'isi_per_tunda_kg'   => 'float',
            'diajukan_pada'      => 'datetime',
            'ditinjau_pada'      => 'datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function hasil(): HasOne      { return $this->hasOne(LedakHasil::class, 'ledak_rencana_id'); }
    public function ukur(): HasMany      { return $this->hasMany(LedakUkur::class, 'ledak_rencana_id'); }

    /** Total bahan peledak seluruh peledakan, kg. */
    public function totalBahanPeledak(): float
    {
        return round($this->isi_per_lubang_kg * $this->jumlah_lubang, 2);
    }

    /** Volume batuan yang direncanakan terbongkar, m³. */
    public function volumeRencana(): ?float
    {
        $per = Peledakan::volumePerLubang($this->burden_m, $this->spasi_m, $this->tinggi_jenjang_m);

        return $per === null ? null : round($per * $this->jumlah_lubang, 2);
    }

    /**
     * Powder factor rencana.
     *
     * Memakai volume rencana; setelah peledakan terjadi, angka nyatanya
     * dihitung ulang dari volume yang benar-benar terbongkar — keduanya
     * sengaja tidak dicampur, sebab selisih di antaranya justru yang
     * menunjukkan seberapa jauh hasilnya menyimpang dari rancangan.
     */
    public function powderFactorRencana(): ?float
    {
        $v = $this->volumeRencana();

        return $v === null ? null : Peledakan::powderFactor($this->totalBahanPeledak(), $v);
    }

    public function powderFactorNyata(): ?float
    {
        $v = (float) ($this->hasil?->volume_bcm ?? 0);

        return $v > 0 ? Peledakan::powderFactor($this->totalBahanPeledak(), $v) : null;
    }

    public function penyimpanganGeometri(): array
    {
        return Peledakan::periksaGeometri(
            $this->burden_m, $this->spasi_m, $this->stemming_m,
            $this->diameter_lubang_mm, $this->subdrill_m, $this->tinggi_jenjang_m,
        );
    }

    public function radiusLemparan(): ?float
    {
        return Peledakan::radiusLemparan($this->diameter_lubang_mm);
    }

    public function fragmentasi(): ?float
    {
        $per = Peledakan::volumePerLubang($this->burden_m, $this->spasi_m, $this->tinggi_jenjang_m);

        return $per === null ? null
            : Peledakan::x50($per, $this->isi_per_lubang_kg, $this->faktor_batuan, $this->kekuatan_relatif);
    }

    /**
     * Perkiraan getaran pada tiap titik terlindung.
     *
     * @param  \Illuminate\Support\Collection<int,LedakTitik> $titik
     * @param  array{k:?float,beta:?float,dapatDipakai:bool} $tetapan
     * @return list<array<string,mixed>>
     */
    public function perkiraanGetaran($titik, array $tetapan): array
    {
        $k = $tetapan['dapatDipakai'] ? $tetapan['k'] : null;
        $beta = $tetapan['dapatDipakai'] ? $tetapan['beta'] : null;

        $hasil = [];
        foreach ($titik as $t) {
            $jarak = (float) ($this->ukur->firstWhere('ledak_titik_id', $t->id)?->jarak_m ?? 0);
            if ($jarak <= 0) continue;

            $ambang = $t->ambang();
            $ppv = Peledakan::ppvPerkiraan($jarak, $this->isi_per_tunda_kg, $k, $beta);

            $hasil[] = [
                'titik'    => $t->nama,
                'jarak_m'  => $jarak,
                'ambang'   => $ambang,
                'perkiraan'=> $ppv,
                'lampaui'  => $ppv !== null && $ppv > $ambang,
                'isiMaks'  => Peledakan::isiMaksPerTunda($jarak, $ambang, $k, $beta),
            ];
        }

        return $hasil;
    }

    public function toView(array $tetapan = ['dapatDipakai' => false, 'k' => null, 'beta' => null]): array
    {
        return [
            'id'      => $this->id,
            'kode'    => $this->kode,
            'lokasi'  => $this->lokasi,
            'tanggal' => $this->tanggal_rencana?->toDateString(),
            'tanggalLabel' => $this->tanggal_rencana?->format('d M Y'),
            'jenis_batuan' => $this->jenis_batuan,

            'geometri' => [
                'diameter' => $this->diameter_lubang_mm,
                'burden'   => $this->burden_m,
                'spasi'    => $this->spasi_m,
                'kedalaman'=> $this->kedalaman_m,
                'subdrill' => $this->subdrill_m,
                'stemming' => $this->stemming_m,
                'jenjang'  => $this->tinggi_jenjang_m,
                'lubang'   => $this->jumlah_lubang,
                'pola'     => $this->pola,
            ],
            'penyimpangan' => $this->penyimpanganGeometri(),

            'bahan' => [
                'jenis'        => $this->bahan_peledak,
                'kekuatan'     => $this->kekuatan_relatif,
                'perLubang'    => $this->isi_per_lubang_kg,
                'perTunda'     => $this->isi_per_tunda_kg,
                'total'        => $this->totalBahanPeledak(),
            ],

            'volumeRencana'   => $this->volumeRencana(),
            'pfRencana'       => $this->powderFactorRencana(),
            'pfNyata'         => $this->powderFactorNyata(),
            'fragmentasi'     => $this->fragmentasi(),
            'radiusLemparan'  => $this->radiusLemparan(),

            'status'      => $this->status,
            'statusLabel' => \App\Support\Alur::LABEL[$this->status] ?? $this->status,
            'catatan'     => $this->catatan,

            'hasil' => $this->hasil?->toView(),

            'alur' => [
                'dapatDiubah'   => $this->dapatDiubah(),
                'dapatDiajukan' => $this->dapatDiubah(),
                'dapatDitinjau' => $this->dapatDitinjauOleh(auth()->user()),
                'pengaju'       => $this->pengaju?->name,
                'peninjau'      => $this->peninjau?->name,
                'alasanTolak'   => $this->alasan_tolak,
            ],
        ];
    }
}
