<?php

namespace App\Models\Investigasi;

use App\Models\Concerns\BerindukPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu tindakan perbaikan (CAPA).
 *
 * `hierarki_id` menyebut tingkat pengendaliannya, dan itu bukan hiasan:
 * investigasi yang seluruh tindakannya berupa "briefing ulang" dan
 * "pasang rambu" adalah investigasi yang tidak mengubah apa pun di
 * lapangan, dan satu-satunya cara melihatnya dari jauh adalah dengan
 * mencatat tingkat tiap tindakan.
 */
class Tindakan extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'temuan';

    protected $table = 'inv_tindakan';

    /**
     * Urutan status, dan urutannya bermakna.
     *
     * "selesai" dinyatakan pelaksananya; "diverifikasi" dinyatakan orang
     * lain. Menyatukan keduanya berarti pelaksana memverifikasi
     * pekerjaannya sendiri — dan verifikasi semacam itu tidak pernah
     * menemukan apa pun.
     */
    public const STATUS = [
        'terbuka'      => 'Terbuka',
        'berjalan'     => 'Berjalan',
        'selesai'      => 'Selesai',
        'diverifikasi' => 'Diverifikasi',
        'ditutup'      => 'Ditutup',
    ];

    protected $fillable = [
        'no_tindakan', 'temuan_id', 'hierarki_id', 'uraian', 'pic_id', 'pic_nama',
        'tenggat', 'status', 'selesai_pada', 'diverifikasi_oleh', 'diverifikasi_pada',
        'catatan_verifikasi', 'efektif',
    ];

    protected function casts(): array
    {
        return [
            'tenggat'           => 'date',
            'selesai_pada'      => 'date',
            'diverifikasi_pada' => 'date',
            'efektif'           => 'boolean',
        ];
    }

    public function temuan()    { return $this->belongsTo(Temuan::class, 'temuan_id'); }
    public function hierarki()  { return $this->belongsTo(HierarkiKendali::class, 'hierarki_id'); }
    public function pic()       { return $this->belongsTo(User::class, 'pic_id'); }
    public function verifikator() { return $this->belongsTo(User::class, 'diverifikasi_oleh'); }

    /**
     * Sudah lewat tenggat tanpa selesai.
     *
     * Yang sudah "selesai" tidak dihitung terlambat meski tanggalnya
     * lewat: pekerjaannya sudah dikerjakan, dan menandainya merah
     * selamanya membuat daftar merah berhenti dibaca.
     */
    public function terlambat(): bool
    {
        return $this->tenggat
            && in_array($this->status, ['terbuka', 'berjalan'], true)
            && $this->tenggat->isPast();
    }

    public function telatHari(): ?int
    {
        return $this->terlambat() ? $this->tenggat->diffInDays(now()) : null;
    }
}
