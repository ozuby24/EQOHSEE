<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Kestabilan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu lereng atau sektor yang dipantau.
 *
 * Membawa rancangannya, geometri terbangunnya, dan acuan dari kajian
 * geoteknik. Kesimpulan tentang aman atau tidaknya tidak diambil di
 * sini — yang disediakan adalah bahan untuk mengambilnya.
 */
#[ScopedBy(MilikPerusahaan::class)]
class GeoLereng extends Model
{
    use BerpemilikPerusahaan;

    public const JENIS  = ['highwall', 'lowwall', 'sidewall', 'timbunan', 'stockpile'];
    public const STATUS = ['aktif', 'arsip'];

    /** Selisih geometri yang masih dianggap wajar terhadap rancangan. */
    private const TOLERANSI_SUDUT_DEG = 2.0;
    private const TOLERANSI_TINGGI_M  = 1.0;
    private const TOLERANSI_BERM_M    = 0.5;

    protected $fillable = [
        'company_id', 'user_id', 'kode', 'nama', 'jenis', 'lokasi', 'litologi',
        'tinggi_rencana_m', 'sudut_rencana_deg', 'tinggi_jenjang_rencana_m', 'lebar_berm_rencana_m',
        'tinggi_aktual_m', 'sudut_aktual_deg', 'tinggi_jenjang_aktual_m', 'lebar_berm_aktual_m',
        'fk_rencana', 'ppa_rencana_persen', 'kajian_oleh', 'kajian_tanggal', 'interval_kajian_hari',
        'ambang_waspada_mm_hari', 'ambang_siaga_mm_hari', 'ambang_awas_mm_hari',
        'status', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'kajian_tanggal'           => 'date',
            'tinggi_rencana_m'         => 'float',
            'sudut_rencana_deg'        => 'float',
            'tinggi_jenjang_rencana_m' => 'float',
            'lebar_berm_rencana_m'     => 'float',
            'tinggi_aktual_m'          => 'float',
            'sudut_aktual_deg'         => 'float',
            'tinggi_jenjang_aktual_m'  => 'float',
            'lebar_berm_aktual_m'      => 'float',
            'fk_rencana'               => 'float',
            'ppa_rencana_persen'       => 'float',
            'ambang_waspada_mm_hari'   => 'float',
            'ambang_siaga_mm_hari'     => 'float',
            'ambang_awas_mm_hari'      => 'float',
        ];
    }

    public function company(): BelongsTo    { return $this->belongsTo(Company::class); }
    public function instrumen(): HasMany    { return $this->hasMany(GeoInstrumen::class); }
    public function bacaan(): HasMany       { return $this->hasMany(GeoBacaan::class); }

    /**
     * Penyimpangan geometri terbangun terhadap rancangan.
     *
     * Ini satu-satunya bagian dari kestabilan lereng yang dapat
     * diperiksa tanpa keahlian geoteknik: cukup diukur, lalu
     * dibandingkan. Lereng yang tergali lebih curam atau lebih tinggi
     * daripada rancangannya sudah keluar dari dasar kajiannya, apa pun
     * angka faktor keamanan yang tertulis di sana.
     *
     * Yang dilaporkan hanya penyimpangan yang memperburuk keadaan.
     * Lereng yang tergali lebih landai daripada rancangan tidak
     * disebut menyimpang — ia berada di sisi yang aman.
     *
     * @return list<array{hal:string,rencana:float,aktual:float,selisih:float,satuan:string}>
     */
    public function penyimpanganGeometri(): array
    {
        $keluar = [];

        $periksa = function (string $hal, ?float $rencana, ?float $aktual, float $toleransi, string $satuan)
        use (&$keluar) {
            if ($rencana === null || $aktual === null) return;

            $selisih = $aktual - $rencana;
            if ($selisih > $toleransi) {
                $keluar[] = ['hal' => $hal, 'rencana' => $rencana, 'aktual' => $aktual,
                             'selisih' => round($selisih, 2), 'satuan' => $satuan];
            }
        };

        $periksa('Sudut lereng',   $this->sudut_rencana_deg,        $this->sudut_aktual_deg,        self::TOLERANSI_SUDUT_DEG, '°');
        $periksa('Tinggi lereng',  $this->tinggi_rencana_m,         $this->tinggi_aktual_m,         self::TOLERANSI_TINGGI_M,  'm');
        $periksa('Tinggi jenjang', $this->tinggi_jenjang_rencana_m, $this->tinggi_jenjang_aktual_m, self::TOLERANSI_TINGGI_M,  'm');

        // Berm terbalik arahnya: yang berbahaya justru yang lebih
        // sempit daripada rancangan, sebab lebar berm itulah yang
        // menangkap material gugur sebelum sampai ke jalan di bawahnya.
        if ($this->lebar_berm_rencana_m !== null && $this->lebar_berm_aktual_m !== null) {
            $kurang = $this->lebar_berm_rencana_m - $this->lebar_berm_aktual_m;
            if ($kurang > self::TOLERANSI_BERM_M) {
                $keluar[] = ['hal' => 'Lebar berm', 'rencana' => $this->lebar_berm_rencana_m,
                             'aktual' => $this->lebar_berm_aktual_m, 'selisih' => round(-$kurang, 2), 'satuan' => 'm'];
            }
        }

        return $keluar;
    }

    /** Sisa hari sampai kajian geoteknik perlu ditinjau ulang; negatif berarti terlewat. */
    public function sisaHariKajian(): ?int
    {
        if (!$this->kajian_tanggal || !$this->interval_kajian_hari) return null;

        return (int) round(now()->startOfDay()->diffInDays(
            $this->kajian_tanggal->copy()->addDays($this->interval_kajian_hari)->startOfDay(), false
        ));
    }

    /** Bacaan disetujui pada satu instrumen, urut menaik — bahan hitungan laju. */
    public function deretBacaan(?Collection $bacaan = null): array
    {
        $b = ($bacaan ?? $this->bacaan)
            ->whereIn('status', \App\Support\Alur::terhitung())
            ->sortBy(fn (GeoBacaan $x) => $x->tanggal?->toDateString())
            ->values();

        return $b->map(fn (GeoBacaan $x) => [
            'tanggal'        => $x->tanggal?->toDateString(),
            'perpindahan_mm' => (float) $x->perpindahan_mm,
        ])->all();
    }

    /**
     * Ringkasan gerakan lereng ini.
     *
     * @return array{laju:?float,tingkat:string,tren:array,ttf:array,titik:int}
     */
    public function gerakan(?Collection $bacaan = null): array
    {
        $deret = $this->deretBacaan($bacaan);
        $laju  = Kestabilan::laju($deret);

        $terakhir = $laju ? end($laju)['laju'] : null;

        return [
            'laju'    => $terakhir,
            'tingkat' => Kestabilan::tingkat(
                $terakhir,
                $this->ambang_waspada_mm_hari,
                $this->ambang_siaga_mm_hari,
                $this->ambang_awas_mm_hari,
            ),
            'tren'  => Kestabilan::tren($laju),
            'ttf'   => Kestabilan::kebalikanLaju($laju),
            'titik' => count($laju),
            'deret' => array_map(fn ($l) => ['tanggal' => $l['tanggal'], 'laju' => $l['laju']], $laju),
        ];
    }

    public function instrumenRusak(): int
    {
        return $this->instrumen->where('status', 'rusak')->count();
    }

    public function instrumenSiap(): int
    {
        return $this->instrumen->where('status', 'siap')->count();
    }

    public function toView(?Collection $bacaan = null): array
    {
        $gerak = $this->gerakan($bacaan);

        return [
            'id'       => $this->id,
            'kode'     => $this->kode,
            'nama'     => $this->nama,
            'jenis'    => $this->jenis,
            'lokasi'   => $this->lokasi,
            'litologi' => $this->litologi,
            'status'   => $this->status,

            'rencana' => [
                'tinggi'  => $this->tinggi_rencana_m,
                'sudut'   => $this->sudut_rencana_deg,
                'jenjang' => $this->tinggi_jenjang_rencana_m,
                'berm'    => $this->lebar_berm_rencana_m,
                'fk'      => $this->fk_rencana,
                'ppa'     => $this->ppa_rencana_persen,
            ],
            'aktual' => [
                'tinggi'  => $this->tinggi_aktual_m,
                'sudut'   => $this->sudut_aktual_deg,
                'jenjang' => $this->tinggi_jenjang_aktual_m,
                'berm'    => $this->lebar_berm_aktual_m,
            ],
            'penyimpangan' => $this->penyimpanganGeometri(),

            'kajian' => [
                'oleh'     => $this->kajian_oleh,
                'tanggal'  => $this->kajian_tanggal?->toDateString(),
                'interval' => $this->interval_kajian_hari,
                'sisaHari' => $this->sisaHariKajian(),
            ],

            'ambang' => [
                'waspada' => $this->ambang_waspada_mm_hari ?? Kestabilan::AMBANG_WASPADA,
                'siaga'   => $this->ambang_siaga_mm_hari   ?? Kestabilan::AMBANG_SIAGA,
                'awas'    => $this->ambang_awas_mm_hari    ?? Kestabilan::AMBANG_AWAS,
                'khusus'  => $this->ambang_waspada_mm_hari !== null,
            ],

            'gerakan'   => $gerak,
            'instrumen' => [
                'jumlah' => $this->instrumen->count(),
                'siap'   => $this->instrumenSiap(),
                'rusak'  => $this->instrumenRusak(),
            ],
            'catatan' => $this->catatan,
        ];
    }
}
