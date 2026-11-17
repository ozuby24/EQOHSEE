<?php

namespace App\Models\Investigasi;

use App\Models\Concerns\BerindukPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Pembelajaran yang diterbitkan dari satu investigasi.
 *
 * Yang dibaca site lain bukan seluruh berkas investigasi, melainkan satu
 * paragraf ini. Tanpa pembelajaran yang disebarkan, penyebab yang sama
 * akan ditemukan lagi di tempat lain dari awal.
 */
class Pembelajaran extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'investigasi';

    protected $table = 'inv_pembelajaran';

    protected $fillable = [
        'investigasi_id', 'judul', 'ringkasan', 'pesan_kunci',
        'diterbitkan_pada', 'diterbitkan_oleh',
    ];

    protected function casts(): array
    {
        return ['diterbitkan_pada' => 'date'];
    }

    public function investigasi() { return $this->belongsTo(Investigasi::class, 'investigasi_id'); }
    public function penerbit()    { return $this->belongsTo(User::class, 'diterbitkan_oleh'); }
}
