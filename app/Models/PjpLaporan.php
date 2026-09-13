<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;
use App\Support\Waktu;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Dokumen kepatuhan yang diunggah sebuah PJP.
 *
 * Aturan waktunya tinggal di sini sebagai tetapan model, bukan di
 * pengendali: tanggal batas dan bulan triwulan dibaca oleh pengendali
 * unggah DAN oleh tampilan yang menyembunyikan formulirnya. Dua salinan
 * aturan akan berselisih cepat atau lambat, dan yang tampak hanyalah
 * formulir terbuka yang unggahannya selalu ditolak.
 */
class PjpLaporan extends Model
{
    use BerindukPerusahaan;
    use HasFactory;

    /** Pemiliknya ada pada induknya; lihat BerindukPerusahaan. */
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

    /** Tanggal batas pengiriman tiap bulan — berlaku untuk semua jenis dokumen. */
    public const BATAS_TANGGAL_LAPORAN = 3;

    /** Bulan dibukanya Laporan Triwulan: TW1 April, TW2 Juli, TW3 Oktober, TW4 Januari. */
    public const BULAN_TRIWULAN_DIBUKA = [
        4  => 'TW1 (April)',
        7  => 'TW2 (Juli)',
        10 => 'TW3 (Oktober)',
        1  => 'TW4 (Januari)',
    ];

    protected $fillable = [
        'pjp_id', 'jenis', 'periode', 'file_path', 'file_name',
        'file_size', 'catatan', 'kesesuaian_isi',
    ];

    protected $appends = ['tepat_waktu'];

    protected function casts(): array
    {
        /*
         * pjp_id dicasting tegas ke integer. Pengendali membandingkan
         * `$laporan->pjp_id === $pjp->id` secara ketat untuk memastikan
         * dokumen memang milik PJP pada alamatnya; bila driver basis
         * data mengembalikan angka sebagai string, perbandingan itu
         * gagal dan permintaan yang sah dijawab 404.
         */
        return ['pjp_id' => 'integer', 'file_size' => 'integer'];
    }

    public function pjp(): BelongsTo
    {
        return $this->belongsTo(Pjp::class);
    }

    /**
     * Tepat waktu bila diunggah pada atau sebelum tanggal batas.
     *
     * Dihitung dari created_at, bukan disimpan sebagai kolom. Kolom
     * tersimpan akan berbohong begitu tanggal batasnya pernah diubah:
     * baris lama tetap membawa penilaian menurut aturan yang sudah tidak
     * berlaku, dan tidak ada yang menandai bahwa keduanya berbeda.
     *
     * Tanggalnya dibaca menurut zona SETEMPAT, bukan UTC tempat ia
     * disimpan. Aplikasi menyimpan waktu dalam UTC dengan sengaja; yang
     * dinilai aturan ini adalah tanggal kalender di lokasi tambang.
     * Tanpa pemindahan zona, unggahan pada 4 September pukul 06.00 WITA
     * tercatat sebagai 3 September UTC dan dinilai TEPAT WAKTU padahal
     * terlambat — dan sebaliknya, unggahan 3 September pukul 23.00 WITA
     * terbaca 4 September dan dihukum terlambat padahal tidak. Keduanya
     * salah tanpa menimbulkan galat: yang terlihat hanya lencana hijau
     * atau merah yang tampak wajar.
     */
    protected function tepatWaktu(): Attribute
    {
        return Attribute::make(
            get: fn () => Waktu::lokal($this->created_at)?->day <= self::BATAS_TANGGAL_LAPORAN,
        );
    }

    /**
     * Apakah jendela Laporan Triwulan sedang terbuka?
     *
     * Bulannya juga dibaca menurut zona setempat, dengan alasan yang sama
     * seperti tepat_waktu — dan di sini akibatnya lebih tajam: pada
     * pergantian bulan, unggahan yang sah dapat DITOLAK server karena
     * jam UTC masih menunjuk bulan sebelumnya.
     */
    public static function triwulanSedangDibuka(?Carbon $tanggal = null): bool
    {
        $bulan = $tanggal !== null
            ? $tanggal->month
            : Waktu::kini()->month;

        return array_key_exists($bulan, self::BULAN_TRIWULAN_DIBUKA);
    }

    public static function triwulanLabelUntukBulan(int $bulan): ?string
    {
        return self::BULAN_TRIWULAN_DIBUKA[$bulan] ?? null;
    }

    /** Daftar bulan triwulan sebagai satu kalimat, untuk pesan galat dan tampilan. */
    public static function bulanTriwulanDibuka(): string
    {
        return implode(', ', self::BULAN_TRIWULAN_DIBUKA);
    }
}
