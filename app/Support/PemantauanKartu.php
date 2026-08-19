<?php

namespace App\Support;

use App\Models\Paspor;
use App\Models\PasporKartu;
use Illuminate\Support\Collection;

/**
 * Pemantauan masa berlaku Mine Permit dan SIMPER.
 *
 * Satu pertanyaan yang ditanyakan tiap pagi di gerbang: siapa yang hari
 * ini tidak boleh masuk, dan siapa yang minggu depan tidak boleh masuk
 * kalau tidak ada yang mengurusnya sekarang.
 *
 * YANG DIPANTAU TANGGAL EFEKTIF, BUKAN YANG TERCETAK. Kartu berpijak
 * pada berkas lain — permit pada MCU, SIMPER pada SIM kepolisian — dan
 * tidak dapat hidup lebih lama daripada dasarnya. Memantau tanggal yang
 * tercetak membuat kartu yang MCU-nya sudah habis tetap terhitung
 * "panjang", dan orangnya lolos gerbang dengan kartu yang secara resmi
 * masih berlaku tetapi secara medis tidak lagi berdasar.
 *
 * YANG DIHITUNG HANYA KARTU YANG SUDAH DISETUJUI. Draf dan pengajuan
 * yang masih menunggu bukan izin; menghitungnya sebagai kartu aktif
 * membuat jumlah pemegang izin lebih besar daripada yang sebenarnya
 * boleh masuk — dan selisih itu justru pada orang-orang yang berkasnya
 * belum beres.
 */
final class PemantauanKartu
{
    /** Jenis kartu yang dipantau. Visitor tidak: ia tidak berdasar apa pun. */
    public const JENIS = [AlurMiner::KARTU_PERMIT, AlurMiner::KARTU_LICENSE];

    /**
     * Baris pemantauan untuk sekumpulan orang.
     *
     * @param  Collection<int,Paspor>  $orang
     * @return list<array<string,mixed>>
     */
    public static function baris(Collection $orang, ?string $jenis = null): array
    {
        $keluar = [];

        foreach ($orang as $p) {
            foreach ($p->kartu as $k) {
                if (!in_array($k->jenis, self::JENIS, true)) continue;
                if ($jenis !== null && $k->jenis !== $jenis) continue;
                if (!$k->sudahDisetujui()) continue;

                $keluar[] = self::satu($p, $k);
            }
        }

        /* Diurutkan dari yang paling mendesak, bukan dari yang paling
           baru. Daftar yang diurutkan waktu membuat yang sudah habis
           tenggelam di antara yang masih panjang. */
        usort($keluar, function (array $a, array $b) {
            $ua = Authority::URUT_KARTU[$a['keadaan']] ?? 9;
            $ub = Authority::URUT_KARTU[$b['keadaan']] ?? 9;

            return $ua !== $ub ? $ua <=> $ub : ($a['sisaHari'] ?? 99999) <=> ($b['sisaHari'] ?? 99999);
        });

        return $keluar;
    }

    /** @return array<string,mixed> */
    private static function satu(Paspor $p, PasporKartu $k): array
    {
        $efektif = $k->expiredEfektif();

        return [
            'id'         => $k->id,
            'pasporId'   => $p->id,
            'nama'       => $p->nama,
            'nik'        => $p->nik,
            'jabatan'    => $p->jabatan,
            'perusahaan' => $p->company?->name,

            'jenis'   => $k->jenis,
            'nomor'   => $k->nomor,
            'tglTerbit'  => $k->tgl_terbit?->toDateString(),

            /* Dua tanggal berdampingan, dan keduanya perlu. Yang tercetak
               adalah yang tertulis di kartunya; yang efektif adalah yang
               benar-benar berlaku. Menyembunyikan salah satunya membuat
               selisihnya tampak seperti kesalahan pencatatan. */
            'tglTercetak' => $k->tgl_expired?->toDateString(),
            'tglEfektif'  => $efektif?->toDateString(),

            'dibatasiDasar' => $k->dibatasiDasar(),
            'namaDasar'     => $k->namaDasar(),
            'tglDasar'      => $k->expiredDasar()?->toDateString(),

            'keadaan'      => $keadaan = Authority::keadaanKartu($efektif),
            'keadaanLabel' => Authority::LABEL_KARTU[$keadaan] ?? $keadaan,
            'sisaHari'     => Authority::sisaHari($efektif),
        ];
    }

    /**
     * Ringkasan yang menjawab pertanyaan gerbang.
     *
     * `aktif` menghitung kartu yang masih berlaku hari ini — termasuk
     * yang mendesak, sebab yang tinggal tiga hari tetap sah hari ini.
     * Yang sudah habis TIDAK ikut: itulah gunanya angka ini.
     *
     * @param  list<array<string,mixed>>  $baris
     * @return array<string,mixed>
     */
    public static function ringkas(array $baris): array
    {
        $per = [
            Authority::HABIS          => 0,
            Authority::MENDESAK       => 0,
            Authority::DEKAT          => 0,
            Authority::PANJANG        => 0,
            Authority::TAK_BERTANGGAL => 0,
        ];

        foreach ($baris as $b) {
            $per[$b['keadaan']] = ($per[$b['keadaan']] ?? 0) + 1;
        }

        $total = count($baris);

        return [
            'total'  => $total,
            'aktif'  => $total - $per[Authority::HABIS],
            'habis'  => $per[Authority::HABIS],

            /* "Mendekati" menggabungkan mendesak dan dekat: keduanya
               menuntut tindakan sekarang, dan yang membedakannya hanya
               seberapa cepat. Rinciannya tetap ada di `perKeadaan`. */
            'mendekati' => $per[Authority::MENDESAK] + $per[Authority::DEKAT],

            'tanpaTanggal' => $per[Authority::TAK_BERTANGGAL],
            'perKeadaan'   => $per,

            /* Berapa yang habis karena DASARNYA, bukan karena kartunya.
               Angka ini memisahkan dua pekerjaan yang berbeda:
               memperpanjang kartu, dan memperbarui MCU atau SIM. */
            'dibatasiDasar' => count(array_filter($baris, fn ($b) => $b['dibatasiDasar'])),
        ];
    }

    /**
     * Jumlah pemegang kartu per perusahaan.
     *
     * Pertanyaan yang selalu menyusul angka totalnya: milik siapa. Di
     * tambang dengan belasan mitra kerja, "empat puluh kartu habis"
     * tidak dapat ditindaklanjuti sampai diketahui empat puluh itu
     * tersebar di berapa perusahaan.
     *
     * @param  list<array<string,mixed>>  $baris
     * @return list<array<string,mixed>>
     */
    public static function perPerusahaan(array $baris): array
    {
        $per = [];

        foreach ($baris as $b) {
            $nama = $b['perusahaan'] ?: '— tanpa perusahaan —';

            $per[$nama] ??= [
                'perusahaan' => $nama,
                'total' => 0, 'aktif' => 0, 'habis' => 0, 'mendekati' => 0,
            ];

            $per[$nama]['total']++;

            if ($b['keadaan'] === Authority::HABIS) $per[$nama]['habis']++;
            else                                    $per[$nama]['aktif']++;

            if (in_array($b['keadaan'], [Authority::MENDESAK, Authority::DEKAT], true)) {
                $per[$nama]['mendekati']++;
            }
        }

        /* Yang paling banyak masalahnya di atas — itu yang perlu
           ditelepon lebih dulu. Bukan urut abjad, yang hanya memudahkan
           mencari nama yang sudah diketahui. */
        usort($per, fn ($a, $b) => [$b['habis'], $b['mendekati'], $b['total']]
                              <=> [$a['habis'], $a['mendekati'], $a['total']]);

        return array_values($per);
    }
}
