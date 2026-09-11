<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Peluang perbaikan (Opportunity For Improvement) atas butir yang SUDAH
 * sempurna.
 *
 * Berdiri sendiri dari SmkpFinding, dan pemisahannya bukan soal
 * kerapian. Temuan mencatat ketidaksesuaian: ia menurunkan nilai,
 * menuntut akar masalah, tindakan, penanggung jawab, dan tenggat. OFI
 * kebalikannya — hanya boleh lahir dari capaian 100%, tidak menurunkan
 * apa pun, dan tidak wajib dikerjakan.
 *
 * Ditumpangkan pada tabel temuan, setiap rekap ketidaksesuaian harus
 * menyaringnya lebih dahulu; rekap yang lupa menyaring melaporkan
 * perusahaan yang justru paling patuh sebagai punya belasan temuan.
 */
class SmkpOfi extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'audit';

    public const STATUS = [
        'terbuka'          => 'Terbuka',
        'ditindaklanjuti'  => 'Ditindaklanjuti',
        'ditutup'          => 'Ditutup',
    ];

    /**
     * Lingkup butir yang dilekati.
     *
     * Sub-elemen dan rinciannya dibedakan karena luas peluangnya memang
     * berbeda: "V.5 sudah sempurna, pertimbangkan otomasi" berbicara
     * tentang satu sistem; "V.5.2 sudah sempurna" berbicara tentang satu
     * langkah di dalamnya. Lembar OFI yang tidak membedakan keduanya
     * menyerahkan penafsirannya kepada pembacanya.
     */
    public const LINGKUP = [
        'sub'    => 'Sub-elemen',
        'subsub' => 'Rincian sub-elemen',
    ];

    protected $table = 'smkp_ofi';

    protected $fillable = [
        'audit_id', 'kode', 'lingkup', 'uraian', 'saran',
        'penanggung_jawab', 'target', 'status', 'user_id',
    ];

    /**
     * Status awal ikut ada DI MEMORI, bukan hanya di basis data.
     *
     * Default kolom baru terbaca sesudah barisnya dimuat ulang, sehingga
     * baris yang baru dibuat lalu langsung dikirim ke layar punya
     * `status` bernilai null — dan lencana statusnya menggambar kotak
     * kosong tanpa satu galat pun.
     */
    protected $attributes = ['status' => 'terbuka', 'lingkup' => 'sub'];

    protected function casts(): array
    {
        return ['target' => 'date'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(SmkpAudit::class, 'audit_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
