<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jawaban sebuah PJP atas satu pertanyaan checklist.
 *
 * Dua kolom penilaian yang berbeda gunanya: `jawaban` (Y/T/N-A) dipakai
 * kategori LEGALITAS sebagai gerbang lulus/belum, sedangkan `nilai`
 * (0–3 atau na) yang menentukan skor pada kategori berbobot A–P.
 */
class SmkpChecklistAnswer extends Model
{
    use BerindukPerusahaan;

    /** Pemiliknya ada pada induknya; lihat BerindukPerusahaan. */
    protected static string $indukPerusahaan = 'pjp';

    public const JAWABAN = [
        'ya'    => 'Y',
        'tidak' => 'T',
        'na'    => 'N/A',
    ];

    /**
     * Skala penilaian dari dokumen prakualifikasi. Kuncinya string,
     * termasuk angkanya — 'na' hidup pada kolom yang sama dengan '0'–'3',
     * jadi kolomnya memang tidak dapat bertipe angka.
     */
    public const NILAI = [
        '0'  => '0 - Tidak ada / tidak tersedia / tidak dijelaskan',
        '1'  => '1 - Persyaratan belum terpenuhi',
        '2'  => '2 - Persyaratan cukup memadai tetapi perlu perbaikan',
        '3'  => '3 - Persyaratan sudah memadai',
        'na' => 'N/A - Persyaratan tidak berlaku',
    ];

    /** Nilai yang mengeluarkan sebuah item dari pembilang DAN penyebut skor. */
    public const NILAI_NA = 'na';

    protected $fillable = ['pjp_id', 'smkp_checklist_item_id', 'jawaban', 'nilai', 'penjelasan'];

    protected function casts(): array
    {
        return ['pjp_id' => 'integer', 'smkp_checklist_item_id' => 'integer'];
    }

    public function pjp(): BelongsTo
    {
        return $this->belongsTo(Pjp::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SmkpChecklistItem::class, 'smkp_checklist_item_id');
    }
}
