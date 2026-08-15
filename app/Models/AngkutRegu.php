<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Concerns\Ditinjau;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Alur;
use App\Support\Angkutan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu regu angkut: satu alat muat beserta truk yang melayaninya, pada
 * satu shift dan satu rute.
 *
 * Angka yang disimpan hanya besaran mentah — jumlah unit, menit tiap
 * komponen edar, rit, ton, jam. Match factor, produktivitas, dan porsi
 * antre seluruhnya dihitung saat dibaca; angka turunan yang ikut
 * disimpan cepat atau lambat berselisih dengan sumbernya, dan tidak ada
 * cara bagi pembacanya untuk tahu mana yang benar.
 */
#[ScopedBy(MilikPerusahaan::class)]
class AngkutRegu extends Model
{
    use BerpemilikPerusahaan;
    use Ditinjau;

    public const SHIFT    = ['1', '2', '3'];
    public const MATERIAL = ['batubara', 'overburden', 'lainnya'];

    /** `status` sengaja tidak ada di sini — ia hanya berpindah lewat Ditinjau. */
    protected $fillable = [
        'company_id', 'user_id', 'alat_muat_id', 'kode', 'tanggal', 'shift',
        'pit', 'tujuan', 'material', 'jumlah_alat_muat', 'jumlah_truk', 'jarak_km',
        'waktu_muat_menit', 'waktu_angkut_menit', 'waktu_tumpah_menit',
        'waktu_kembali_menit', 'waktu_antre_menit',
        'ritase', 'tonase', 'jam_kerja', 'jam_delay', 'batas_kecepatan_kmh', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'             => 'date',
            'jarak_km'            => 'float',
            'waktu_muat_menit'    => 'float',
            'waktu_angkut_menit'  => 'float',
            'waktu_tumpah_menit'  => 'float',
            'waktu_kembali_menit' => 'float',
            'waktu_antre_menit'   => 'float',
            'ritase'              => 'integer',
            'tonase'              => 'float',
            'jam_kerja'           => 'float',
            'jam_delay'           => 'float',
            'batas_kecepatan_kmh' => 'float',
            'jumlah_truk'         => 'integer',
            'jumlah_alat_muat'    => 'integer',
            'diajukan_pada'       => 'datetime',
            'ditinjau_pada'       => 'datetime',
        ];
    }

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function alatMuat(): BelongsTo { return $this->belongsTo(AngkutAlat::class, 'alat_muat_id'); }
    public function muatan(): HasMany     { return $this->hasMany(AngkutMuatan::class, 'angkut_regu_id'); }

    /* ---------- turunan ---------- */

    public function waktuEdar(): ?float
    {
        return Angkutan::waktuEdar(
            $this->waktu_muat_menit, $this->waktu_angkut_menit,
            $this->waktu_tumpah_menit, $this->waktu_kembali_menit,
        );
    }

    public function waktuEdarNyata(): ?float
    {
        return Angkutan::waktuEdarNyata(
            $this->waktu_muat_menit, $this->waktu_angkut_menit,
            $this->waktu_tumpah_menit, $this->waktu_kembali_menit, $this->waktu_antre_menit,
        );
    }

    /**
     * Match factor regu ini.
     *
     * Penyebutnya waktu edar TANPA antre. Lihat App\Support\Angkutan
     * untuk alasannya; ringkasnya, memakai waktu edar nyata membuat
     * armada yang kelebihan truk selalu terbaca seimbang.
     */
    public function matchFactor(): ?float
    {
        return Angkutan::matchFactor(
            $this->jumlah_truk, (float) $this->waktu_muat_menit,
            max(1, (int) $this->jumlah_alat_muat), (float) $this->waktuEdar(),
        );
    }

    public function porsiAntre(): ?float
    {
        return Angkutan::porsiAntre(
            $this->waktu_muat_menit, $this->waktu_angkut_menit,
            $this->waktu_tumpah_menit, $this->waktu_kembali_menit, $this->waktu_antre_menit,
        );
    }

    public function kecepatanRata(): ?float
    {
        return Angkutan::kecepatanRata(
            (float) $this->jarak_km, $this->waktu_angkut_menit, $this->waktu_kembali_menit,
        );
    }

    public function utilisasi(): ?float
    {
        return Angkutan::utilisasi($this->jam_kerja, $this->jam_delay);
    }

    /** Muatan rata-rata nyata, ton per rit. */
    public function muatanRata(): ?float
    {
        return $this->ritase > 0 ? round($this->tonase / $this->ritase, 2) : null;
    }

    public function ritaseTeoritis(): ?int
    {
        $per = Angkutan::ritaseTeoritis($this->jam_kerja, $this->waktuEdarNyata());

        return $per === null ? null : $per * max(1, (int) $this->jumlah_truk);
    }

    /** Tonase yang hilang karena seluruh truk mengantre sepanjang shift. */
    public function tonaseHilangAntre(): ?float
    {
        $rata = $this->muatanRata();
        if ($rata === null) return null;

        return Angkutan::tonaseHilangAntre(
            (float) $this->waktu_antre_menit * $this->ritase, $this->waktuEdar(), $rata,
        );
    }

    /**
     * Kepatuhan muatan regu ini, dinilai dari penimbangannya sendiri.
     *
     * Tiap penimbangan dinormalkan lebih dulu menjadi persen terhadap
     * kapasitas truknya masing-masing, lalu dinilai terhadap nominal
     * 100. Akibatnya angka `rata` dan `tertinggi` yang keluar bersatuan
     * PERSEN, bukan ton — dan itu memang yang dimaksud: satu regu dapat
     * memuat beberapa tipe truk sekaligus, dan menilai 88 ton terhadap
     * kapasitas rata-rata armada campuran tidak menjawab pertanyaan
     * apa pun.
     */
    public function kepatuhanMuatan(): array
    {
        $per = [];
        foreach ($this->muatan as $m) {
            $nominal = (float) ($m->alat?->kapasitas_ton ?? 0);
            if ($nominal <= 0) continue;

            $per[] = $m->muatan_ton / $nominal * 100;
        }

        return Angkutan::kepatuhanMuatan($per, 100.0);
    }

    public function melampauiBatasKecepatan(): bool
    {
        $k = $this->kecepatanRata();

        return $k !== null && $this->batas_kecepatan_kmh !== null
            && $this->batas_kecepatan_kmh > 0 && $k > $this->batas_kecepatan_kmh;
    }

    public function toView(): array
    {
        $mf = $this->matchFactor();

        return [
            'id'       => $this->id,
            'kode'     => $this->kode,
            'tanggal'  => $this->tanggal?->toDateString(),
            'tanggalLabel' => $this->tanggal?->format('d M Y'),
            'shift'    => $this->shift,
            'pit'      => $this->pit,
            'tujuan'   => $this->tujuan,
            'material' => $this->material,

            'alatMuat'   => $this->alatMuat?->kode,
            'alatMuatId' => $this->alat_muat_id,
            'jumlahAlatMuat' => $this->jumlah_alat_muat,
            'jumlahTruk' => $this->jumlah_truk,
            'jarak'      => $this->jarak_km,

            'edar' => [
                'muat'    => $this->waktu_muat_menit,
                'angkut'  => $this->waktu_angkut_menit,
                'tumpah'  => $this->waktu_tumpah_menit,
                'kembali' => $this->waktu_kembali_menit,
                'antre'   => $this->waktu_antre_menit,
                'produktif' => $this->waktuEdar(),
                'nyata'   => $this->waktuEdarNyata(),
                'rincian' => Angkutan::rincianEdar(
                    $this->waktu_muat_menit, $this->waktu_angkut_menit,
                    $this->waktu_tumpah_menit, $this->waktu_kembali_menit, $this->waktu_antre_menit,
                ),
            ],

            'matchFactor' => $mf,
            'bacaMf'      => Angkutan::bacaMatchFactor($mf),
            'porsiAntre'  => $this->porsiAntre(),
            'kecepatan'   => $this->kecepatanRata(),
            'batasKecepatan' => $this->batas_kecepatan_kmh,
            'lampauiKecepatan' => $this->melampauiBatasKecepatan(),
            'utilisasi'   => $this->utilisasi(),

            'ritase'         => $this->ritase,
            'ritaseTeoritis' => $this->ritaseTeoritis(),
            'tonase'         => $this->tonase,
            'muatanRata'     => $this->muatanRata(),
            'jamKerja'       => $this->jam_kerja,
            'jamDelay'       => $this->jam_delay,
            'hilangAntre'    => $this->tonaseHilangAntre(),

            'muatan'    => $this->muatan->map(fn (AngkutMuatan $m) => $m->toView())->values(),
            'kepatuhan' => $this->kepatuhanMuatan(),

            'status'      => $this->status,
            'statusLabel' => Alur::LABEL[$this->status] ?? $this->status,
            'catatan'     => $this->catatan,

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
