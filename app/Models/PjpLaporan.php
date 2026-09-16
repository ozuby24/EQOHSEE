<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PjpLaporan extends Model
{
    use HasFactory;

    public const JENIS = [
        'spip' => 'Data SPIP (Sarana, Prasarana, Instalasi & Peralatan)',
        'tsp' => 'Target Sasaran Program (TSP)',
        'laporan_bulanan' => 'Laporan Bulanan',
        'laporan_triwulan' => 'Laporan Triwulan',
    ];

    public const KESESUAIAN = [
        'sesuai' => 'Sesuai',
        'tidak_sesuai' => 'Tidak Sesuai',
    ];

    /**
     * Batas tanggal pengiriman dokumen setiap bulannya (semua jenis dokumen).
     */
    public const BATAS_TANGGAL_LAPORAN = 3;

    /**
     * Bulan-bulan dibukanya pengiriman Laporan Triwulan: TW1=April, TW2=Juli,
     * TW3=Oktober, TW4=Januari.
     */
    public const BULAN_TRIWULAN_DIBUKA = [
        4 => 'TW1 (April)',
        7 => 'TW2 (Juli)',
        10 => 'TW3 (Oktober)',
        1 => 'TW4 (Januari)',
    ];

    protected $fillable = [
        'pjp_id',
        'jenis',
        'periode',
        'file_path',
        'file_name',
        'file_size',
        'catatan',
        'kesesuaian_isi',
    ];

    protected $appends = ['tepat_waktu'];

    public function pjp(): BelongsTo
    {
        return $this->belongsTo(Pjp::class);
    }

    protected function tepatWaktu(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->created_at->day <= self::BATAS_TANGGAL_LAPORAN,
        );
    }

    public static function triwulanSedangDibuka(?Carbon $tanggal = null): bool
    {
        $tanggal ??= now();

        return array_key_exists($tanggal->month, self::BULAN_TRIWULAN_DIBUKA);
    }

    public static function triwulanLabelUntukBulan(int $bulan): ?string
    {
        return self::BULAN_TRIWULAN_DIBUKA[$bulan] ?? null;
    }
}
