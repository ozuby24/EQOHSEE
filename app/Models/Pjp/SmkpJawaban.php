<?php

namespace App\Models\Pjp;

use App\Models\Concerns\BerindukPerusahaan;
use Illuminate\Database\Eloquent\Model;

/**
 * Jawaban satu PJP atas satu butir daftar periksa.
 *
 * Batas perusahaannya menumpang induknya lewat BerindukPerusahaan:
 * jawaban tidak berkolom company_id sendiri, dan memang tidak perlu —
 * pemiliknya tidak pernah berbeda dari PJP yang dijawab.
 */
class SmkpJawaban extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'pjp';

    /**
     * Pilihan bagi kategori LEGALITAS — ada atau tidak ada dokumennya.
     *
     * Terpisah dari NILAI di bawah karena pertanyaannya memang lain:
     * "apakah SIUP-nya ada" tidak punya jawaban "cukup memadai tetapi
     * perlu perbaikan".
     */
    public const JAWABAN = [
        'ya'    => 'Y',
        'tidak' => 'T',
        'na'    => 'N/A',
    ];

    /**
     * Pilihan bagi kategori berbobot A–P.
     *
     * 'na' BUKAN sinonim NULL. 'na' berarti butirnya dikeluarkan dari
     * penyebut — persyaratannya tidak berlaku bagi jenis pekerjaan itu —
     * sedangkan NULL berarti belum dinilai siapa pun dan tetap menyumbang
     * bobot penuh dengan skor nol. Menyamakan keduanya membuat PJP yang
     * daftar periksanya kosong sama sekali memperoleh nilai sempurna,
     * sebab seluruh penyebutnya ikut hilang.
     */
    public const NILAI = [
        '0'  => '0 — Tidak ada / tidak tersedia / tidak dijelaskan',
        '1'  => '1 — Persyaratan belum terpenuhi',
        '2'  => '2 — Persyaratan cukup memadai tetapi perlu perbaikan',
        '3'  => '3 — Persyaratan sudah memadai',
        'na' => 'N/A — Persyaratan tidak berlaku',
    ];

    /** Nilai tertinggi satu butir, dipakai sebagai penyebut skor. */
    public const NILAI_PENUH = 3;

    protected $table = 'pjp_smkp_jawaban';

    protected $fillable = ['pjp_id', 'item_id', 'jawaban', 'nilai', 'penjelasan'];

    public function pjp()
    {
        return $this->belongsTo(Pjp::class, 'pjp_id');
    }

    public function item()
    {
        return $this->belongsTo(SmkpItem::class, 'item_id');
    }
}
