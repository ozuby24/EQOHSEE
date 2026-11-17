<?php

namespace App\Models\Hr;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Miners\Pekerja;
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Collection;

/**
 * Satu kontrak kerja, dan tempatnya di dalam rantai perpanjangan.
 *
 * JENISNYA MENENTUKAN ATURAN YANG BERLAKU, dan keempatnya berbeda
 * jauh. PKWT jangka waktu tunduk pada batas lima tahun dan wajib punya
 * alasan pasal 5. PKWT selesainya pekerjaan tidak punya tanggal akhir
 * sama sekali — yang mengakhirinya adalah pekerjaannya, dan batasan
 * "selesai" itu wajib tertulis. PKWT harian berubah menjadi PKWTT demi
 * hukum bila orangnya bekerja terlalu sering. PKWTT tidak berakhir dan
 * tidak berhak atas uang kompensasi.
 *
 * Disatukan menjadi satu "kontrak" tanpa jenis, keempat aturan itu
 * runtuh menjadi satu aturan yang salah untuk tiga di antaranya.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Kontrak extends Model
{
    use BerpemilikPerusahaan;

    public const JENIS = [
        'pkwt_jangka'  => 'PKWT — jangka waktu',
        'pkwt_selesai' => 'PKWT — selesainya pekerjaan',
        'pkwt_harian'  => 'PKWT — harian lepas',
        'pkwtt'        => 'PKWTT — waktu tidak tertentu',
    ];

    /** Jenis yang tunduk pada batas lima tahun dan berhak kompensasi. */
    public const PKWT = ['pkwt_jangka', 'pkwt_selesai', 'pkwt_harian'];

    /** Alasan yang sah bagi PKWT jangka waktu — PP 35/2021 pasal 5. */
    public const ALASAN = [
        'sekali_selesai' => 'Pekerjaan yang sekali selesai atau sementara sifatnya',
        'tidak_lama'     => 'Pekerjaan yang penyelesaiannya diperkirakan tidak terlalu lama',
        'musiman'        => 'Pekerjaan yang sifatnya musiman',
        'produk_baru'    => 'Produk baru, kegiatan baru, atau produk tambahan yang masih dijajaki',
    ];

    public const STATUS = [
        'draft'      => 'Draf',
        'berjalan'   => 'Berjalan',
        'selesai'    => 'Selesai',
        'diputus'    => 'Diputus di tengah',
        'jadi_pkwtt' => 'Berubah menjadi PKWTT',
    ];

    /** Status yang berarti kontraknya masih mengikat hari ini. */
    public const HIDUP = ['berjalan'];

    protected $table = 'hr_kontrak';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draft', 'urutan' => 1, 'masa_percobaan_hari' => 0];

    protected function casts(): array
    {
        return [
            'mulai'                    => 'date',
            'selesai'                  => 'date',
            'ditandatangani_pada'      => 'date',
            'dicatatkan_pada'          => 'date',
            'masa_percobaan_hari'      => 'integer',
            'urutan'                   => 'integer',
            'kompensasi_upah'          => 'float',
            'kompensasi_bulan'         => 'float',
            'kompensasi_nilai'         => 'float',
            'kompensasi_dihitung_pada' => 'datetime',
            'kompensasi_dibayar_pada'  => 'date',
        ];
    }

    /* ═══════════ relasi ═══════════ */

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function pekerja(): BelongsTo { return $this->belongsTo(Pekerja::class, 'pekerja_id'); }
    public function induk(): BelongsTo   { return $this->belongsTo(self::class, 'induk_id'); }
    public function pembuat(): BelongsTo { return $this->belongsTo(User::class, 'dibuat_oleh'); }

    public function perpanjangan(): HasMany
    {
        return $this->hasMany(self::class, 'induk_id');
    }

    /* ═══════════ sifat ═══════════ */

    public function pkwt(): bool
    {
        return in_array($this->jenis, self::PKWT, true);
    }

    public function hidup(): bool
    {
        return in_array($this->status, self::HIDUP, true);
    }

    /**
     * Kontrak paling awal pada rantai ini.
     *
     * Ditelusuri lewat relasi, bukan lewat kolom "asal_id" yang
     * disimpan: kolom semacam itu benar sampai seseorang menyisipkan
     * perpanjangan di tengah, dan sesudah itu ia salah tanpa pernah
     * berubah.
     */
    public function akar(): self
    {
        $k = $this;
        $lihat = [$k->id => true];

        while ($k->induk_id !== null) {
            $induk = $k->induk;

            // Rantai yang menunjuk dirinya sendiri tidak mungkin ada
            // lewat antarmuka, tetapi pernah ada lewat impor data. Tanpa
            // penjaga ini yang terjadi adalah kalang tak berujung —
            // halaman yang menggantung, bukan galat yang terbaca.
            if ($induk === null || isset($lihat[$induk->id])) break;

            $lihat[$induk->id] = true;
            $k = $induk;
        }

        return $k;
    }

    /**
     * Seluruh kontrak pada rantai ini, dari yang paling awal.
     *
     * @return Collection<int, self>
     */
    public function rantai(): Collection
    {
        $akar = $this->akar();

        $semua = static::where('pekerja_id', $this->pekerja_id)
            ->orderBy('mulai')->orderBy('id')->get();

        $rantai = collect([$akar]);
        $lihat  = [$akar->id => true];

        // Ditelusuri turun berulang kali: satu induk boleh punya lebih
        // dari satu baris anak bila ada yang dibatalkan lalu diganti.
        do {
            $tumbuh = false;
            foreach ($semua as $k) {
                if (isset($lihat[$k->id]) || $k->induk_id === null) continue;
                if (! isset($lihat[$k->induk_id])) continue;

                $rantai->push($k);
                $lihat[$k->id] = true;
                $tumbuh = true;
            }
        } while ($tumbuh);

        return $rantai->sortBy([['mulai', 'asc'], ['id', 'asc']])->values();
    }

    /* ═══════════ lingkup ═══════════ */

    public function scopePkwt(Builder $q): Builder
    {
        return $q->whereIn('jenis', self::PKWT);
    }

    public function scopeHidup(Builder $q): Builder
    {
        return $q->whereIn('status', self::HIDUP);
    }

    /**
     * Kontrak yang berakhir di dalam rentang, INKLUSIF kedua ujungnya.
     *
     * Alasannya sama dengan Roster::scopeAntara: kolomnya bertipe DATE
     * tetapi menyimpan "2026-09-22 00:00:00", dan sebagai perbandingan
     * teks "2026-09-22 00:00:00" <= "2026-09-22" bernilai SALAH. Yang
     * hilang adalah kontrak yang justru berakhir pada hari terakhir
     * rentang yang ditanyakan.
     */
    public function scopeBerakhirAntara(Builder $q, string $dari, string $sampai): Builder
    {
        return $q->whereNotNull('selesai')
            ->where('selesai', '>=', $dari)
            ->where('selesai', '<=', $sampai.' 23:59:59');
    }
}
