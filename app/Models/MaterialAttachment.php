<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Berkas pendamping satu materi — SOP, lembar periksa, berkas latihan.
 *
 * Berupa TAUTAN, bukan unggahan, dan itu mengikuti cara materi sendiri
 * bekerja: `materials.url` pun sejak awal sebuah tautan. Berkas yang
 * memang milik perusahaan dan perlu dijaga punya modulnya sendiri —
 * Dokumen — lengkap dengan rute berjaganya; menyalin mekanisme itu ke
 * sini akan melahirkan tempat penyimpanan kedua dengan aturan akses
 * yang harus dijaga selaras selamanya.
 */
class MaterialAttachment extends Model
{
    protected $fillable = ['material_id', 'title', 'url', 'order_index'];

    public function material(): BelongsTo { return $this->belongsTo(Material::class); }
}
