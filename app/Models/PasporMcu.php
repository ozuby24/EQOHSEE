<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;
use App\Support\Authority;
use Illuminate\Database\Eloquent\Model;

/**
 * Pemeriksaan kesehatan berkala.
 *
 * Yang disimpan hanya KESIMPULAN kelayakan kerjanya, bukan rincian
 * medisnya. Rincian medis adalah rekam medis: ia punya aturan
 * kerahasiaannya sendiri dan tidak boleh terbaca oleh setiap admin HSE
 * yang membuka daftar pekerja.
 */
class PasporMcu extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'paspor';

    protected $table = 'paspor_mcu';

    protected $fillable = [
        'paspor_id', 'mcu_pengajuan_id', 'tgl_periksa', 'tgl_expired',
        'penyelenggara', 'nomor', 'jenis', 'hasil', 'pembatasan',
        'rujukan', 'outstanding', 'berkas',

        /* Dibaca dari D'Best. `usia` disimpan apa adanya, bukan dihitung
           dari tanggal lahir: yang tercetak pada surat MCU adalah usia
           saat pemeriksaan, dan menghitungnya ulang tahun depan memberi
           angka yang berbeda dari suratnya. */
        'usia', 'mcu_berikutnya', 'status_verifikasi',
        'catatan_kontraktor', 'remarks',

        /* Seberapa dekat orangnya ke batas kelayakan — terpisah dari
           hasilnya, lihat Authority::LEVEL_RISIKO. */
        'level_risiko',

        /* Surat rujukannya sendiri. `rujukan` menjawab "dirujuk ke
           mana", kolom ini menjawab "mana suratnya" — keduanya
           berpasangan, bukan salah satu. */
        'berkas_rujukan',
    ];

    protected function casts(): array
    {
        return [
            'tgl_periksa'    => 'date',
            'tgl_expired'    => 'date',
            'outstanding'    => 'date',
            'mcu_berikutnya' => 'date',
            'usia'           => 'integer',
        ];
    }

    public function paspor() { return $this->belongsTo(Paspor::class); }

    public function pengajuan()
    {
        return $this->belongsTo(McuPengajuan::class, 'mcu_pengajuan_id');
    }

    public function keadaan(): string    { return Authority::keadaan($this->tgl_expired); }

    /**
     * Sudah diperiksa kebenarannya, bukan sekadar sudah masuk.
     *
     * Berkas yang baru diunggah kontraktor dan berkas yang sudah
     * diverifikasi paramedis sama-sama "ada". Tanpa pemisahan ini
     * keduanya terbaca sama sahnya — dan yang belum diverifikasi
     * justru yang paling perlu dilihat.
     */
    public function terverifikasi(): bool
    {
        return $this->status_verifikasi === Authority::MCU_TERVERIFIKASI;
    }
    public function keterangan(): string { return Authority::keterangan($this->tgl_expired); }

    /** Hasilnya membolehkan bekerja — terpisah dari masa berlakunya. */
    public function hasilLayak(): bool
    {
        return in_array($this->hasil, Authority::MCU_LAYAK, true);
    }

    /**
     * Boleh bekerja, tetapi dekat ke batasnya.
     *
     * Sengaja menuntut KEDUANYA benar. Pekerja yang sudah Unfit bukan
     * "risiko tinggi" melainkan sudah tidak bekerja — memasukkannya ke
     * sini menggabungkan orang yang perlu diawasi dengan orang yang
     * sudah dihentikan, dan daftar gabungan itu tidak dapat ditindak
     * dengan satu cara yang sama.
     */
    public function risikoPerluPerhatian(): bool
    {
        return $this->hasilLayak()
            && Authority::risikoPerluPerhatian($this->level_risiko);
    }

    /**
     * Rujukan medis yang tanggal tindak lanjutnya sudah lewat.
     *
     * Hasil "Fit With Note" yang dirujuk tetapi tidak pernah ditagih
     * adalah catatan yang sudah lengkap di berkas dan tidak pernah
     * terjadi di kenyataan. Yang membuatnya tertagih adalah tanggal —
     * karena itu rujukan tanpa tanggal ikut terhitung tertunggak di
     * sini, bukan diabaikan.
     */
    public function rujukanTertunggak(): bool
    {
        if (blank($this->rujukan)) return false;

        return blank($this->outstanding)
            || Authority::sisaHari($this->outstanding) < 0;
    }
}
