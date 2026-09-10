<?php

namespace App\Models\Pjp;

use App\Models\Concerns\BerindukPerusahaan;
use App\Support\Waktu;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Satu dokumen berkala yang dikirim perusahaan jasa.
 *
 * Batas perusahaannya menumpang induknya — lihat SmkpJawaban untuk
 * alasannya. Berkasnya sendiri disimpan pada disk TERTUTUP dan hanya
 * dapat dibuka lewat rute berjaga; lihat App\Support\Berkas.
 */
class Laporan extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'pjp';

    public const JENIS = [
        'spip'             => 'Data SPIP (Sarana, Prasarana, Instalasi & Peralatan)',
        'tsp'              => 'Target Sasaran Program (TSP)',
        'laporan_bulanan'  => 'Laporan Bulanan',
        'laporan_triwulan' => 'Laporan Triwulan',
    ];

    public const KESESUAIAN = [
        'sesuai'       => 'Sesuai',
        'tidak_sesuai' => 'Tidak Sesuai',
    ];

    /** Tanggal terakhir pengiriman dokumen tiap bulannya. */
    public const BATAS_TANGGAL = 3;

    /**
     * Bulan dibukanya pengiriman Laporan Triwulan.
     *
     * TW4 jatuh pada Januari — bulan berikutnya, bukan Desember — sebab
     * yang dilaporkan adalah triwulan yang baru saja selesai.
     */
    public const BULAN_TRIWULAN = [
        4  => 'TW1 (April)',
        7  => 'TW2 (Juli)',
        10 => 'TW3 (Oktober)',
        1  => 'TW4 (Januari)',
    ];

    protected $table = 'pjp_laporan';

    protected $fillable = [
        'pjp_id', 'jenis', 'periode',
        'file_path', 'file_name', 'file_size',
        'catatan', 'kesesuaian_isi',
    ];

    protected $appends = ['tepat_waktu'];

    public function pjp()
    {
        return $this->belongsTo(Pjp::class, 'pjp_id');
    }

    /**
     * Terkirim pada tanggal 1–3 MENURUT ZONA TAMBANG, bukan menurut UTC.
     *
     * Penyimpanan tetap UTC dan itu benar, tetapi menilainya dengan hari
     * UTC menggeser penilaian kepatuhan sebanyak zonanya. Pada WITA
     * (+8) yang dikirim tanggal 4 pukul 07.00 pagi tersimpan sebagai
     * tanggal 3 pukul 23.00 UTC — dan dinilai tepat waktu padahal
     * terlambat sehari. Kesalahannya diam: angka kepatuhan naik, tidak
     * ada baris yang tampak salah, dan yang membaca menyimpulkan
     * mitranya lebih patuh daripada yang sebenarnya.
     */
    protected function tepatWaktu(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->created_at !== null
                && Waktu::lokal($this->created_at)->day <= self::BATAS_TANGGAL,
        );
    }

    /** Bulan ini termasuk bulan pengiriman Laporan Triwulan? */
    public static function triwulanSedangDibuka(?Carbon $saat = null): bool
    {
        $saat ??= Waktu::kini();

        return array_key_exists($saat->month, self::BULAN_TRIWULAN);
    }

    public static function labelTriwulan(int $bulan): ?string
    {
        return self::BULAN_TRIWULAN[$bulan] ?? null;
    }
}
