<?php

namespace App\Models\Investigasi;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu sel matriks risiko 5×5.
 *
 * Disimpan sebagai baris, bukan sebagai rumus di dalam kode. Bedanya
 * menentukan ketika seseorang bertanya "mengapa kejadian ini L3":
 * jawabannya dapat ditunjuk pada satu baris yang dapat dibaca dan
 * diaudit, bukan pada percabangan if yang hanya dapat dibaca pemrogram.
 */
class MatriksRisiko extends Model
{
    protected $table = 'inv_matriks_risiko';

    protected $fillable = ['kemungkinan', 'keparahan', 'skor', 'pita', 'level_investigasi'];
}
