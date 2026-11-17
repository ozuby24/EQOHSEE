<?php

namespace App\Models\Investigasi;

use App\Models\Concerns\BerindukPerusahaan;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu jawaban wawancara.
 *
 * Bunyi pertanyaannya IKUT DISALIN, bukan hanya dirujuk. Bank soal boleh
 * diperbaiki kapan saja; berita acara wawancara yang sudah
 * ditandatangani tidak boleh ikut berubah bunyinya karena seseorang
 * memperbaiki ejaan di master setahun kemudian.
 */
class WawancaraJawaban extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'wawancara';

    protected $table = 'inv_wawancara_jawaban';

    protected $fillable = ['wawancara_id', 'pertanyaan_id', 'pertanyaan_teks', 'jawaban'];

    public function wawancara()  { return $this->belongsTo(Wawancara::class, 'wawancara_id'); }
    public function pertanyaan() { return $this->belongsTo(WawancaraPertanyaan::class, 'pertanyaan_id'); }
}
