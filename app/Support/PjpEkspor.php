<?php

namespace App\Support;

use App\Models\Pjp;
use App\Models\PjpLaporan;

/**
 * Bentuk baris ekspor data Perusahaan Jasa Pertambangan.
 *
 * Terpisah dari pengendali supaya judul kolom dan isinya tidak dapat
 * berselisih: keduanya ditulis berdampingan di sini, dan uji membaca
 * fungsi yang sama dengan yang dipakai berkas unduhan.
 */
final class PjpEkspor
{
    /** @return string[] */
    public static function judul(): array
    {
        return [
            'Nama Perusahaan',
            'NIB',
            'Penanggung Jawab',
            'Alamat',
            'Status',
            'Skor Persyaratan PJP (%)',
            'Skor Kepatuhan Pelaporan (%)',
            'Skor Evaluasi Terakhir',
            'Catatan',
            'Terdaftar Sejak',
        ];
    }

    /**
     * Satu baris ekspor.
     *
     * Seluruh kolom skor ditulis sebagai TEKS, bukan angka mentah —
     * termasuk nol. Sebuah nol numerik ditulis PhpSpreadsheet (dan
     * dibaca Excel dari CSV) sebagai sel kosong, sehingga PJP yang
     * benar-benar berskor 0% tidak dapat dibedakan dari PJP yang datanya
     * memang belum ada. Keduanya justru dua keadaan yang paling penting
     * dibedakan di berkas ini, dan bedanya tidak terlihat sampai ada
     * yang membuka berkasnya dan salah menyimpulkan.
     *
     * @return array<int,string|null>
     */
    public static function baris(Pjp $pjp): array
    {
        $pelaporan = $pjp->pelaporanScore();
        $evaluasi  = $pjp->evaluasis()->first();

        return [
            $pjp->nama_perusahaan,
            $pjp->nib,
            $pjp->penanggung_jawab,
            $pjp->alamat,
            Pjp::STATUS[$pjp->status] ?? $pjp->status,
            $pjp->smkpScore()['persentase'].'%',
            $pelaporan !== null ? $pelaporan.'%' : 'Belum ada laporan',
            $evaluasi !== null ? $evaluasi->skor_rata_rata.'' : 'Belum dievaluasi',
            $pjp->catatan,
            $pjp->created_at?->format('d-m-Y'),
        ];
    }

    /** Nama berkas unduhan, tanpa akhiran. */
    public static function namaBerkas(): string
    {
        return 'perusahaan-jasa-pertambangan-'.now()->format('Ymd');
    }

    /**
     * Label jenis dokumen — dipakai berkas cetak dan ekspor agar
     * keduanya menyebut nama jenis yang sama persis dengan formulir unggah.
     */
    public static function jenisLaporan(): array
    {
        return PjpLaporan::JENIS;
    }
}
