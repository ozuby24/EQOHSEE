<?php

namespace App\Models\Miners;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu langkah persetujuan, untuk SELURUH jenis dokumen Miners.
 *
 * Project1 punya SEMBILAN tabel alur yang isinya identik — mcu_flows,
 * induction_flows, mine_permit_flows, form_simper_flows, workflows,
 * workflowadds, workflowexps, workflowextends, flowsimpers — masing-masing
 * dengan `seq` dan `lastaction` yang sama persis. Sembilan salinan satu
 * aturan berarti perubahan alur dikerjakan sembilan kali, dan yang
 * tertinggal tidak menimbulkan galat: hanya satu jenis dokumen yang
 * diam-diam berhenti pada langkah yang salah. Terjadi di sana — SOP
 * mewajibkan pengesahan KTT pada Mine Permit, dan langkah itu ada pada
 * jalur SIMPER tetapi tidak pernah ditambahkan pada jalur permit.
 *
 * BERELASI POLIMORFIK LEWAT DUA KOLOM, bukan lewat morphTo Laravel.
 * Yang disimpan `dokumen` berupa kata pendek ('permit'), bukan nama
 * kelas: nama kelas yang tersimpan di basis data mengunci letak berkas
 * PHP-nya selamanya, dan memindahkan satu model ke ruang nama lain
 * akan membuat seluruh riwayat persetujuannya tidak dapat dibaca.
 */
class Alur extends Model
{
    /** Urutan peran menurut SOP 007-SOP-OHSE. */
    public const PERAN = [
        'pjo'    => 'PJO Mitra Kerja',
        'dokter' => 'Dokter Pemeriksa',
        'ohse'   => 'OHSE',
        'ktt'    => 'Kepala Teknik Tambang',
    ];

    public const KEADAAN = [
        'menunggu'     => 'Menunggu',
        'setuju'       => 'Disetujui',
        'tolak'        => 'Ditolak',
        'dikembalikan' => 'Dikembalikan',
    ];

    /**
     * Langkah tiap jenis dokumen, berurutan.
     *
     * MCU lewat dokter lebih dahulu — dialah yang menyatakan hasilnya —
     * baru OHSE. Mine Permit dan SIMPER berakhir pada KTT: pengesahan
     * KTT diwajibkan SOP, dan pada Project1 langkah itu ada pada SIMPER
     * tetapi TIDAK PERNAH ada pada Mine Permit.
     */
    public const LANGKAH = [
        'mcu'     => ['pjo', 'dokter', 'ohse'],
        'induksi' => ['pjo', 'ohse'],
        'permit'  => ['pjo', 'ohse', 'ktt'],
        'simper'  => ['pjo', 'ohse', 'ktt'],
        'ajuan'   => ['pjo', 'ohse', 'ktt'],
    ];

    protected $table = 'mnr_alur';

    protected $guarded = ['id'];

    protected $attributes = ['keadaan' => 'menunggu', 'urutan' => 1];

    protected function casts(): array
    {
        return ['bertindak_pada' => 'datetime', 'urutan' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUntuk(Builder $q, string $dokumen, int $id): Builder
    {
        return $q->where('dokumen', $dokumen)->where('dokumen_id', $id)->orderBy('urutan');
    }

    public function menunggu(): bool
    {
        return $this->keadaan === 'menunggu';
    }

    public function peranLabel(): string
    {
        return self::PERAN[$this->peran] ?? $this->peran;
    }
}
