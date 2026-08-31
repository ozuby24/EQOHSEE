<?php

namespace App\Models\Investigasi;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu pertanyaan pada bank soal wawancara.
 *
 * `peran` dan `tingkat_hierarki` menentukan kepada siapa pertanyaan ini
 * pantas diajukan. Korban ditanya soal APD; menanyainya mengapa
 * perusahaan tidak mengganti alatnya bukan hanya sia-sia — ia menggeser
 * tanggung jawab organisasi kepada orang yang baru saja celaka.
 */
class WawancaraPertanyaan extends Model
{
    protected $table = 'inv_wawancara_pertanyaan';

    protected $fillable = ['kode', 'pertanyaan', 'peran', 'tingkat_hierarki', 'kata_kunci', 'urutan'];
}
