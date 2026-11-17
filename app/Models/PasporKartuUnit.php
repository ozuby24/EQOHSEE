<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;
use App\Support\LampiranMiners;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu unit yang boleh dikemudikan pemegang SIMPER, beserta penilaiannya.
 *
 * SIMPER dinilai PER UNIT, bukan per orang. Seorang operator dapat lulus
 * untuk Excavator PC 200 dan belum lulus untuk PC 500 — dua baris, satu
 * kartu. Nilai P2H, nilai praktek, dan keempat berkasnya melekat pada
 * unitnya, bukan pada kartunya.
 *
 * Sebelumnya seluruhnya bertumpuk menjadi satu baris per kartu. Yang
 * hilang bukan kerapian melainkan dasar izinnya: kartu yang menyebut
 * "Excavator" tanpa merinci tipe membolehkan orang mengemudikan unit
 * yang tidak pernah diujikan kepadanya, dan tidak ada satu pun catatan
 * yang menunjukkan itu terjadi.
 */
class PasporKartuUnit extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'kartu';

    protected $table = 'paspor_kartu_unit';

    protected $fillable = [
        'paspor_kartu_id', 'ko_unit_master_id', 'authority', 'jenis_unit', 'type_merk',
        'nilai_p2h', 'nilai_praktek',
        'berkas_rambu', 'berkas_teori', 'hasil_praktek', 'evaluasi', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'nilai_p2h'     => 'integer',
            'nilai_praktek' => 'integer',
        ];
    }

    public function kartu(): BelongsTo
    {
        return $this->belongsTo(PasporKartu::class, 'paspor_kartu_id');
    }

    public function unitMaster(): BelongsTo
    {
        return $this->belongsTo(KoUnitMaster::class, 'ko_unit_master_id');
    }

    /**
     * Nama unit yang ditampilkan.
     *
     * Master dipakai bila ada padanannya; bila tidak, teks bebas yang
     * diketik. Unit sewa dan unit subkontraktor kerap belum terdaftar,
     * dan menolak barisnya berarti orang yang sudah diuji tidak dapat
     * dicatat sama sekali.
     */
    public function namaUnit(): string
    {
        return $this->unitMaster?->unit ?? ($this->jenis_unit ?: '—');
    }

    /**
     * Apakah kedua nilainya sudah memenuhi ambang.
     *
     * Ambangnya 70 — sama dengan ambang kelulusan praktik mengemudi yang
     * dipakai modul pelatihan. Nilai yang belum diisi TIDAK dianggap
     * lulus: kekosongan berarti belum diuji, dan memperlakukannya sebagai
     * lulus persis cara kartu terbit tanpa dasar.
     */
    public function lulus(): bool
    {
        return $this->nilai_p2h !== null
            && $this->nilai_praktek !== null
            && $this->nilai_p2h >= 70
            && $this->nilai_praktek >= 70;
    }

    /** @return array<string,mixed> */
    public function toView(): array
    {
        return [
            'id'           => $this->id,
            'authority'    => $this->authority,
            'unit'         => $this->namaUnit(),
            'typeMerk'     => $this->type_merk,
            'nilaiP2h'     => $this->nilai_p2h,
            'nilaiPraktek' => $this->nilai_praktek,
            'lulus'        => $this->lulus(),
            'catatan'      => $this->catatan,

            /* Keempat berkas ujinya, masing-masing beserta ALAMAT
               MEMBUKANYA. Bentuk lama hanya menyalin jalurnya sebagai
               teks, sehingga yang tergambar di layar adalah nama berkas
               yang tidak dapat ditekan — dan nama berkas yang tidak
               dapat ditekan tidak dapat dibedakan dari salah ketik.

               `berkasKurang` menyebut yang WAJIB tetapi belum ada. Unit
               yang tercantum pada SIMPER tanpa lembar teori dan praktik
               adalah kewenangan mengemudi yang dasarnya tidak pernah
               diperiksa. */
            'berkas'       => LampiranMiners::unitUntukLayar($this),
            'berkasKurang' => LampiranMiners::unitKurang($this),
        ];
    }
}
