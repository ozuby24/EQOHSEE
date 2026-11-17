<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;
use App\Support\Authority;
use Illuminate\Database\Eloquent\Model;

/**
 * Induksi keselamatan — syarat pertama sebelum seseorang masuk area.
 *
 * Mendahului MCU maupun kartu masuk: orang yang belum diinduksi tidak
 * boleh berada di area tambang sekalipun sehat dan berkartu, sebab ia
 * belum diberi tahu bahaya apa yang ada di sana dan harus berbuat apa
 * bila terjadi keadaan darurat.
 *
 * Disimpan sebagai riwayat, bukan satu baris yang ditimpa. Induksi
 * penyegaran adalah kejadian tersendiri dengan tanggal dan nilainya
 * sendiri; menimpanya menghapus bukti bahwa yang sebelumnya pernah
 * diberikan — dan bukti itulah yang diminta saat inspeksi.
 */
class PasporInduksi extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'paspor';

    protected $table = 'paspor_induksi';

    protected $fillable = [
        'paspor_id', 'nomor_registrasi', 'jenis', 'tanggal', 'tgl_expired',
        'pemberi', 'lokasi', 'nilai', 'hasil', 'berkas', 'catatan',

        /* Berkas permohonan induksinya, dan MCU yang mendasarinya —
           keduanya dibaca dari D'Best. */
        'berkas_permohonan', 'paspor_mcu_id',

        /* Surat pengajuan yang melahirkannya, bila ada. Boleh NULL:
           induksi susulan untuk satu pekerja baru tidak lahir dari
           surat mana pun. */
        'induksi_pengajuan_id',
    ];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'tgl_expired' => 'date', 'nilai' => 'integer'];
    }

    public function paspor() { return $this->belongsTo(Paspor::class); }

    public function pengajuan()
    {
        return $this->belongsTo(InduksiPengajuan::class, 'induksi_pengajuan_id');
    }

    /**
     * MCU yang menjadi dasar induksi ini, bila dicatat.
     *
     * Nullable dengan sengaja: riwayat lama tidak menyimpannya, dan
     * menolak baris yang tidak punya berarti membuang catatan sah hanya
     * karena dibuat sebelum aturannya ada.
     */
    public function mcuDasar() { return $this->belongsTo(PasporMcu::class, 'paspor_mcu_id'); }

    public function keadaan(): string    { return Authority::keadaan($this->tgl_expired); }
    public function keterangan(): string { return Authority::keterangan($this->tgl_expired); }

    /**
     * Induksi yang hasilnya meloloskan.
     *
     * "Mengulang" bukan kelulusan yang tertunda melainkan ketidaklulusan
     * yang sopan: orangnya belum boleh masuk sampai pengulangannya
     * selesai dan tercatat sebagai baris tersendiri.
     */
    public function lulus(): bool
    {
        return $this->hasil === 'Lulus';
    }
}
