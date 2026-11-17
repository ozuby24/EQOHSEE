<?php

namespace App\Models\Investigasi;

use App\Models\Concerns\BerindukPerusahaan;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu butir penyebab yang dipilih investigator.
 *
 * Tersimpan sebagai KUNCI ASING ke kamus, bukan sebagai teks. Disimpan
 * sebagai teks, rekap "penyebab terbanyak tahun ini" akan memecah satu
 * penyebab menjadi lima karena ejaannya berbeda-beda — dan rekap itulah
 * satu-satunya alasan kamus penyebab ada.
 *
 * `dari_saran` dan `lapis_saran` mencatat apakah butir ini muncul karena
 * disarankan mesin, dan dari lapis mana. Itu yang memungkinkan
 * pertanyaan "apakah mesinnya menolong atau justru menyetir" dijawab
 * dengan angka di kemudian hari, bukan dengan kesan.
 */
class ScatPilihan extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'analisis';

    protected $table = 'inv_scat_pilihan';

    protected $fillable = [
        'analisis_id', 'taksonomi_id', 'dari_saran', 'lapis_saran', 'catatan_lapangan',
    ];

    protected function casts(): array
    {
        return ['dari_saran' => 'boolean'];
    }

    public function analisis()  { return $this->belongsTo(Analisis::class, 'analisis_id'); }
    public function taksonomi() { return $this->belongsTo(Taksonomi::class, 'taksonomi_id'); }
}
