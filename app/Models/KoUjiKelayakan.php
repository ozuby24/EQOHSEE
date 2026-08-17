<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;
use App\Models\Concerns\Ditinjau;
use App\Support\Alur;
use App\Support\Authority;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu uji kelayakan sebuah unit SPIP.
 *
 * Sebelum ini, unit hanya menyimpan SATU tanggal sertifikasi. Tiap kali
 * diuji ulang, tanggal itu ditimpa dan uji sebelumnya hilang — dan yang
 * hilang bukan sekadar riwayat: ketika inspektur meminta bukti bahwa
 * sebuah alat angkat sudah diuji tiga tahun berturut-turut, yang dapat
 * ditunjukkan hanya yang terakhir.
 *
 * MERK, TIPE, DAN NOMOR SERI DISALIN, bukan dirujuk ke unitnya. Unit
 * yang kemudian diganti mesinnya atau dikoreksi datanya tidak boleh
 * mengubah bunyi sertifikat yang sudah terbit dan sudah diperiksa
 * inspektur.
 *
 * "LAYAK BERSYARAT" ADALAH LAYAK, dengan syarat yang harus terbaca.
 * Memperlakukannya sebagai tidak layak menghentikan alat yang
 * sebenarnya boleh dipakai; memperlakukannya sama dengan layak penuh
 * membuat syaratnya tidak pernah dibaca siapa pun. Karena itu
 * syaratnya wajib diisi bila hasilnya itu — diperiksa di controller,
 * bukan diserahkan pada ingatan.
 */
class KoUjiKelayakan extends Model
{
    use BerindukPerusahaan;
    use Ditinjau;

    protected static string $indukPerusahaan = 'objek';

    protected $table = 'ko_uji_kelayakan';

    /** `status` sengaja tidak dapat diisi massal — lihat trait Ditinjau. */
    protected $fillable = [
        'ko_object_id', 'company_id', 'nomor', 'merk', 'tipe', 'nomor_seri',
        'tgl_inspeksi', 'tgl_expired', 'pemeriksa', 'lembaga', 'lokasi_uji',
        'hasil', 'syarat', 'temuan', 'rekomendasi', 'berkas',
    ];

    protected function casts(): array
    {
        return ['tgl_inspeksi' => 'date', 'tgl_expired' => 'date'];
    }

    public const HASIL = ['Layak', 'Layak Bersyarat', 'Tidak Layak'];

    /** Hasil yang berarti unitnya BOLEH dioperasikan. */
    public const HASIL_LOLOS = ['Layak', 'Layak Bersyarat'];

    public function objek()   { return $this->belongsTo(KoObject::class, 'ko_object_id'); }
    public function company() { return $this->belongsTo(Company::class); }

    /**
     * Yang memutuskan hanya OHSE — sama dengan modul Miners.
     *
     * Uji kelayakan menerbitkan izin operasi sebuah alat; wewenangnya
     * sama beratnya dengan menerbitkan kartu masuk seseorang, dan
     * memisahkan keduanya hanya akan melahirkan dua daftar peninjau yang
     * cepat atau lambat berselisih.
     */
    public function dapatDitinjauOleh(?User $u): bool
    {
        if (!\App\Support\Tahap::penentu($u)) return false;
        if (!$this->menungguTinjauan())       return false;

        return $this->diajukan_oleh !== $u?->getKey();
    }

    public function lolos(): bool
    {
        return in_array($this->hasil, self::HASIL_LOLOS, true);
    }

    public function keadaan(): string    { return Authority::keadaan($this->tgl_expired); }
    public function keterangan(): string { return Authority::keterangan($this->tgl_expired); }

    public function scopeDisetujui(Builder $q): Builder
    {
        return $q->where('status', Alur::DISETUJUI);
    }
}
