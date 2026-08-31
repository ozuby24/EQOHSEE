<?php

namespace App\Models\Investigasi;

use App\Models\Concerns\BerindukPerusahaan;
use App\Models\User;
use App\Support\Investigasi\TahapInvestigasi;
use Illuminate\Database\Eloquent\Model;

/**
 * Berkas investigasi satu kecelakaan.
 *
 * Bernomor sendiri, terpisah dari nomor insidennya: INC-2026-0011 dan
 * INV-2026-0009 pada kejadian yang sama. Keduanya dirujuk di dokumen
 * berbeda oleh orang berbeda, dan satu nomor untuk dua benda membuat
 * "sudah sampai mana INC-2026-0011" menjadi pertanyaan yang punya dua
 * jawaban.
 */
class Investigasi extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'insiden';

    protected $table = 'inv_investigasi';

    protected $fillable = [
        'no_investigasi', 'insiden_id', 'ketua_id', 'prioritas', 'target_selesai',
        'tahap', 'status', 'tujuan', 'ruang_lingkup', 'metode', 'ditutup_pada',
    ];

    /**
     * Nilai awal yang ikut ADA DI MEMORI, bukan hanya di basis data.
     *
     * Kolomnya memang punya default, tetapi default basis data baru
     * terbaca sesudah barisnya dimuat ulang. Berkas yang baru dibuka
     * lalu langsung digambar karena itu punya `tahap` bernilai null —
     * dan rel tahap yang membandingkan namanya menggambar enam bulatan
     * tanpa satu pun menyala, sementara daftar "apa yang kurang"
     * mengembalikan kosong. Layarnya tampak benar dan tidak menuntut
     * apa-apa; yang membukanya menyimpulkan berkasnya sudah lengkap.
     */
    protected $attributes = [
        'tahap'     => 'perencanaan',
        'status'    => 'berjalan',
        'prioritas' => 'sedang',
    ];

    protected function casts(): array
    {
        return ['target_selesai' => 'date', 'ditutup_pada' => 'datetime'];
    }

    /* ═══════════ relasi ═══════════ */

    public function insiden() { return $this->belongsTo(Insiden::class, 'insiden_id'); }
    public function ketua()   { return $this->belongsTo(User::class, 'ketua_id'); }

    public function tim()       { return $this->hasMany(Tim::class, 'investigasi_id'); }
    public function bukti()     { return $this->hasMany(Bukti::class, 'investigasi_id')->orderBy('id'); }
    public function kronologi() { return $this->hasMany(Kronologi::class, 'investigasi_id')->orderBy('urutan')->orderBy('waktu'); }
    public function analisis()  { return $this->hasMany(Analisis::class, 'investigasi_id'); }
    public function akar()      { return $this->hasMany(AkarMasalah::class, 'investigasi_id')->orderBy('urutan')->orderBy('id'); }
    public function temuan()    { return $this->hasMany(Temuan::class, 'investigasi_id')->orderBy('urutan')->orderBy('id'); }
    public function wawancara() { return $this->hasMany(Wawancara::class, 'investigasi_id')->orderByDesc('tanggal'); }
    public function pembelajaran() { return $this->hasMany(Pembelajaran::class, 'investigasi_id')->orderByDesc('id'); }

    /**
     * Seluruh tindakan perbaikan lintas temuan.
     *
     * Lewat hasManyThrough, bukan lewat memuat temuan lalu menjumlahkan
     * di PHP: syarat berpindah tahap menanyakan "berapa tindakan yang
     * belum diverifikasi", dan pertanyaan itu ditanyakan pada tiap
     * penggambaran layar.
     */
    public function tindakanSemua()
    {
        return $this->hasManyThrough(
            Tindakan::class, Temuan::class,
            'investigasi_id', 'temuan_id', 'id', 'id',
        );
    }

    /* ═══════════ keadaan ═══════════ */

    public function level(): ?string
    {
        return $this->insiden?->level_investigasi;
    }

    public function berjalan(): bool
    {
        return $this->status === 'berjalan';
    }

    public function sudahDitutup(): bool
    {
        return $this->status === 'ditutup';
    }

    /** Syarat yang belum terpenuhi untuk meninggalkan tahap sekarang. */
    public function yangKurang(): array
    {
        return TahapInvestigasi::yangKurang($this);
    }

    public function bolehMaju(): bool
    {
        return TahapInvestigasi::bolehMaju($this);
    }
}
