<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Suku cadang yang dipakai sebuah perintah kerja.
 *
 * Tidak ber-scope perusahaan sendiri: ia selalu diakses lewat perintah
 * kerjanya, dan perintah kerja itulah yang sudah terbatas per
 * perusahaan. Memasang scope kedua di sini justru menyembunyikan baris
 * yang perusahaannya belum terisi dari perintah kerja yang seharusnya
 * memilikinya.
 */
class WorkOrderPart extends Model
{
    protected $fillable = [
        'work_order_id', 'gudang_barang_id', 'nama', 'jumlah', 'satuan', 'harga_satuan',
    ];

    protected function casts(): array
    {
        return ['jumlah' => 'float', 'harga_satuan' => 'float'];
    }

    public function workOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class); }
    public function barang(): BelongsTo    { return $this->belongsTo(GudangBarang::class, 'gudang_barang_id'); }

    public function subtotal(): float
    {
        return round($this->jumlah * $this->harga_satuan, 2);
    }
}
