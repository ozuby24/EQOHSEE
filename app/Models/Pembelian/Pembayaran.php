<?php

namespace App\Models\Pembelian;

use Illuminate\Database\Eloquent\Model;

/** Satu kiriman bukti bayar. Yang ditolak pun disimpan — ia bagian riwayat. */
class Pembayaran extends Model
{
    protected $table = 'beli_pembayaran';

    public const QRIS     = 'qris';
    public const TRANSFER = 'transfer';

    public const METODE = [self::QRIS => 'QRIS', self::TRANSFER => 'Transfer bank'];

    protected $fillable = [
        'pesanan_id', 'metode', 'jumlah', 'atas_nama', 'tanggal_bayar', 'bukti', 'catatan',
    ];

    protected function casts(): array
    {
        return ['jumlah' => 'integer', 'tanggal_bayar' => 'date'];
    }

    public function pesanan() { return $this->belongsTo(Pesanan::class, 'pesanan_id'); }
}
