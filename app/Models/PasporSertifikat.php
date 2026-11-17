<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;
use App\Support\Authority;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu sertifikat kompetensi milik satu orang.
 *
 * Masa berlakunya melekat DI SINI, bukan pada orangnya. Seorang
 * pengawas dapat memegang POP yang berlaku sampai 2028 dan Ahli K3
 * Kebakaran yang habis bulan depan; satu tanggal untuk keduanya
 * membuat salah satunya selalu salah.
 */
class PasporSertifikat extends Model
{
    use BerindukPerusahaan;

    /** Batas perusahaannya diwarisi dari paspornya. */
    protected static string $indukPerusahaan = 'paspor';

    protected $table = 'paspor_sertifikat';

    protected $fillable = [
        'paspor_id', 'kompetensi_jenis_id', 'nama', 'lembaga', 'nomor',
        'tgl_terbit', 'tgl_expired', 'berkas', 'catatan', 'certificate_id',
    ];

    protected function casts(): array
    {
        return ['tgl_terbit' => 'date', 'tgl_expired' => 'date'];
    }

    public function paspor()  { return $this->belongsTo(Paspor::class); }
    public function jenis()   { return $this->belongsTo(KompetensiJenis::class, 'kompetensi_jenis_id'); }

    /** Sertifikat yang lahir dari pelatihan LMS, bila memang dari sana. */
    public function certificate() { return $this->belongsTo(Certificate::class); }

    public function keadaan(): string   { return Authority::keadaan($this->tgl_expired); }
    public function sisaHari(): ?int    { return Authority::sisaHari($this->tgl_expired); }
    public function keterangan(): string { return Authority::keterangan($this->tgl_expired); }
}
