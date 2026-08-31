<?php

namespace App\Models\Investigasi;

use App\Models\Concerns\BerindukPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu bukti, beserta sidik jarinya.
 *
 * `sha256` dihitung sekali saat berkasnya masuk lalu tidak pernah
 * dihitung ulang. Gunanya bukan mencegah penggantian — siapa pun yang
 * dapat mengunggah dapat mengunggah yang lain — tetapi membuat
 * penggantian TERLIHAT: berkas yang isinya berubah punya sidik jari
 * yang berbeda dari yang tercatat, dan selisihnya adalah pertanyaan
 * yang harus dijawab seseorang.
 *
 * Bukti yang sudah dikunci tidak dapat dihapus maupun diganti
 * berkasnya. Pada berkas yang dapat diminta Inspektur Tambang,
 * kemampuan menghapus bukti sesudah kesimpulan ditulis adalah lubang
 * yang tidak dapat dijelaskan kepada siapa pun.
 */
class Bukti extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'investigasi';

    protected $table = 'inv_bukti';

    /** Jenis bukti yang dikenali. Kuncinya tersimpan di kolom `jenis`. */
    public const JENIS = [
        'foto'        => 'Foto',
        'video'       => 'Video',
        'dokumen'     => 'Dokumen',
        'cctv'        => 'Rekaman CCTV',
        'pernyataan'  => 'Pernyataan',
        'fisik'       => 'Barang bukti fisik',
    ];

    protected $fillable = [
        'no_bukti', 'investigasi_id', 'jenis', 'judul', 'keterangan', 'sumber',
        'dikumpulkan_pada', 'berkas', 'mime', 'sha256',
        'dikunci', 'dikunci_pada', 'dikunci_oleh', 'dikumpulkan_oleh',
    ];

    protected function casts(): array
    {
        return [
            'dikumpulkan_pada' => 'date',
            'dikunci'          => 'boolean',
            'dikunci_pada'     => 'datetime',
        ];
    }

    public function investigasi() { return $this->belongsTo(Investigasi::class, 'investigasi_id'); }
    public function pengunci()    { return $this->belongsTo(User::class, 'dikunci_oleh'); }
    public function pengumpul()   { return $this->belongsTo(User::class, 'dikumpulkan_oleh'); }

    /** Delapan huruf pertama sidik jarinya — cukup untuk dibandingkan mata. */
    public function sidikPendek(): ?string
    {
        return $this->sha256 ? substr($this->sha256, 0, 16) : null;
    }
}
