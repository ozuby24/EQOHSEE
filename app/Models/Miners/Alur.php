<?php

namespace App\Models\Miners;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu langkah persetujuan, untuk SELURUH jenis dokumen Miners.
 *
 * Project1 (Safe Track) punya sembilan tabel alur yang isinya identik —
 * mcu_flows, induction_flows, mine_permit_flows, form_simper_flows, … —
 * masing-masing dengan `seq` dan `lastaction` yang sama persis. Di sini
 * cukup satu tabel; yang DIIKUTI dari Safe Track adalah ATURANNYA:
 * penyetuju bernama per IUP, langkah pengaju yang disetujui oleh
 * tindakan "Kirim", dan rantai yang berbeda per jenis dokumen.
 *
 * BERELASI POLIMORFIK LEWAT DUA KOLOM, bukan lewat morphTo Laravel.
 * Yang disimpan `dokumen` berupa kata pendek ('permit'), bukan nama
 * kelas: nama kelas yang tersimpan di basis data mengunci letak berkas
 * PHP-nya selamanya.
 *
 * DUA ORANG PADA SATU LANGKAH. `ditugaskan_user_id` adalah orang yang
 * gilirannya — penyetuju bernama pada skema IUP-nya. `user_id` adalah
 * orang yang benar-benar MENINDAK. Biasanya sama; berbeda ketika admin
 * bertindak atas nama penyetuju yang berhalangan, dan riwayatnya harus
 * tetap dapat membedakan keduanya.
 */
class Alur extends Model
{
    /**
     * Peran pada alur — SkemaPersetujuan::PERAN Safe Track, ditambah
     * pengaju yang bukan peran skema melainkan pembuat dokumennya.
     */
    public const PERAN = [
        'pengaju' => 'Pengaju',
        'dokter'  => 'Dokter Pemeriksa MCU',
        'ohse'    => 'HSE (Verifikasi)',
        'ktt'     => 'Kepala Teknik Tambang',
    ];

    /**
     * Kata peran yang dicetak di atas nama organisasi pada judul kolom
     * tabel persetujuan — SEBUTAN_PERAN Safe Track.
     *
     * Diperlukan karena dua kolom dapat berakhir dengan organisasi yang
     * SAMA: ketika pengajunya orang IUP sendiri, kolom pengaju dan kolom
     * verifikasi sama-sama berbunyi "HSE PT X".
     */
    public const SEBUTAN = [
        'pengaju' => 'Pengaju',
        'dokter'  => 'Pemeriksa',
        'ohse'    => 'Verifikasi',
        'ktt'     => 'Persetujuan',
    ];

    public const KEADAAN = [
        'menunggu'     => 'Menunggu',
        'setuju'       => 'Disetujui',
        'tolak'        => 'Ditolak',
        'dikembalikan' => 'Dikembalikan',
    ];

    /**
     * Rantai tiap jenis dokumen, berurutan — RANTAI Safe Track.
     *
     *   MCU          pengaju → dokter          (dokter langkah terakhir)
     *   Induksi      pengaju → HSE
     *   Mine Permit  pengaju → HSE → KTT
     *   SIMPER       pengaju → HSE → KTT
     *   Penambahan   pengaju → HSE → KTT
     *   Upgrade      pengaju → HSE
     *   Perpanjangan pengaju → dokter → HSE → KTT   (permit dan SIMPER)
     *
     * MCU BERHENTI DI DOKTER. Yang dinyatakan sebuah MCU adalah layak
     * atau tidaknya seseorang bekerja menurut pemeriksaan kesehatan —
     * penilaian medis. Langkah HSE sesudahnya di rantai lama tidak
     * menambahkan keputusan apa pun.
     *
     * PERPANJANGAN LEWAT DOKTER, penerbitan baru tidak: perpanjangan
     * memang mengklaim ada pemeriksaan MCU baru di baliknya.
     */
    public const LANGKAH = [
        'mcu'                 => ['pengaju', 'dokter'],
        'induksi'             => ['pengaju', 'ohse'],
        'permit'              => ['pengaju', 'ohse', 'ktt'],
        'simper'              => ['pengaju', 'ohse', 'ktt'],
        'penambahan'          => ['pengaju', 'ohse', 'ktt'],
        'upgrade'             => ['pengaju', 'ohse'],
        'perpanjangan'        => ['pengaju', 'dokter', 'ohse', 'ktt'],
        'perpanjangan_permit' => ['pengaju', 'dokter', 'ohse', 'ktt'],
    ];

    protected $table = 'mnr_alur';

    protected $guarded = ['id'];

    protected $attributes = ['keadaan' => 'menunggu', 'urutan' => 1];

    protected function casts(): array
    {
        return ['bertindak_pada' => 'datetime', 'urutan' => 'integer'];
    }

    /** Yang menindak langkah ini. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Yang gilirannya — penyetuju bernama pada skema IUP. */
    public function ditugaskan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditugaskan_user_id');
    }

    public function scopeUntuk(Builder $q, string $dokumen, int $id): Builder
    {
        return $q->where('dokumen', $dokumen)->where('dokumen_id', $id)->orderBy('urutan');
    }

    /**
     * Hanya langkah yang SEDANG giliran: masih menunggu dan seluruh
     * langkah sebelumnya sudah disetujui.
     *
     * Ditulis sebagai subkueri, bukan dihitung di memori, karena
     * lonceng tugas memakainya pada setiap halaman — memuat seluruh
     * alur untuk menjawab satu angka akan dibayar tiap permintaan.
     */
    public function scopeGiliran(Builder $q): Builder
    {
        $t = $this->getTable();

        return $q->where("{$t}.keadaan", 'menunggu')
            ->whereNotExists(fn ($s) => $s->from($t.' as sebelum')
                ->whereColumn('sebelum.dokumen', "{$t}.dokumen")
                ->whereColumn('sebelum.dokumen_id', "{$t}.dokumen_id")
                ->whereColumn('sebelum.urutan', '<', "{$t}.urutan")
                ->where('sebelum.keadaan', '!=', 'setuju'));
    }

    public function menunggu(): bool
    {
        return $this->keadaan === 'menunggu';
    }

    public function peranLabel(): string
    {
        return self::PERAN[$this->peran] ?? $this->peran;
    }

    /** @return list<string> rantai peran untuk sebuah kunci rantai. */
    public static function rantai(string $kunci): array
    {
        return self::LANGKAH[$kunci] ?? self::LANGKAH['permit'];
    }
}
