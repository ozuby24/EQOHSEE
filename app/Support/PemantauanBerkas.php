<?php

namespace App\Support;

use App\Models\Paspor;
use App\Models\PasporKartu;
use App\Models\PasporMcu;
use Illuminate\Support\Collection;

/**
 * Pemantauan masa berlaku MCU, Mine Permit, dan SIMPER.
 *
 * Satu pertanyaan yang ditanyakan tiap pagi di gerbang: siapa yang hari
 * ini tidak boleh masuk, dan siapa yang minggu depan tidak boleh masuk
 * kalau tidak ada yang mengurusnya sekarang.
 *
 * KETIGANYA DIPANTAU BERSAMA karena ketiganya satu rantai. MCU
 * mendasari Mine Permit, dan SIM kepolisian mendasari SIMPER; memantau
 * kartunya saja membuat MCU yang tinggal seminggu tidak terlihat sampai
 * ia menjatuhkan kartunya. Yang dipantau di sini bukan tiga daftar
 * berdampingan melainkan satu daftar dengan tiga jenis berkas.
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
final class PemantauanBerkas
{
    /** MCU bukan kartu, tetapi masa berlakunya dipantau dengan cara yang sama. */
    public const MCU = 'MCU';

    /** Status kepegawaian yang dihitung sebagai tenaga kerja aktif. */
    public const ORANG_AKTIF = 'aktif';

    /** Jenis berkas yang dipantau. Visitor tidak: ia tidak berdasar apa pun. */
    public const JENIS = [self::MCU, AlurMiner::KARTU_PERMIT, AlurMiner::KARTU_LICENSE];

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
            /* MCU dihitung dari yang TERAKHIR saja, bukan dari seluruh
               riwayatnya. Pemeriksaan tahun lalu memang sudah habis
               masa berlakunya, dan menghitungnya sebagai baris "habis"
               membuat setiap orang yang rajin MCU tampak paling
               bermasalah — persis kebalikan dari yang sebenarnya. */
            if ($jenis === null || $jenis === self::MCU) {
                if ($m = $p->mcuTerakhir()) $keluar[] = self::satuMcu($p, $m);
            }

            foreach ($p->kartu as $k) {
                if (!in_array($k->jenis, self::JENIS, true)) continue;
                if ($jenis !== null && $k->jenis !== $jenis) continue;
                if (!$k->sudahDisetujui()) continue;

                /* Orangnya dipasang balik ke kartunya. Tanggal efektif
                   Mine Permit dihitung dari MCU orang itu lewat
                   $kartu->paspor — relasi yang TIDAK ikut terisi saat
                   kartunya dimuat sebagai anak. Tanpa baris ini, dua
                   kueri tambahan per kartu (paspornya, lalu MCU-nya),
                   dan keduanya mengambil baris yang sudah ada di
                   memori. */
                $k->setRelation('paspor', $p);

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

    /**
     * Satu baris MCU.
     *
     * Tidak berdasar berkas lain — MCU-lah yang menjadi dasar bagi Mine
     * Permit, bukan sebaliknya — sehingga tanggal efektifnya sama dengan
     * yang tercetak.
     *
     * @return array<string,mixed>
     */
    private static function satuMcu(Paspor $p, PasporMcu $m): array
    {
        return self::orang($p) + [
            'id'     => 'mcu-'.$m->id,
            'jenis'  => self::MCU,
            'nomor'  => $m->nomor,
            'tglTerbit' => $m->tgl_periksa?->toDateString(),

            'tglTercetak' => $m->tgl_expired?->toDateString(),
            'tglEfektif'  => $m->tgl_expired?->toDateString(),

            'dibatasiDasar' => false,
            'namaDasar'     => null,
            'tglDasar'      => null,

            /* Hasil pemeriksaannya, bukan hanya tanggalnya. MCU yang
               masih berlaku tetapi berhasil "unfit" tetap melarang orang
               bekerja, dan pita hijau tanpa keterangan ini membuatnya
               terbaca sebagai aman. */
            'hasil'      => $m->hasil,
            'hasilLayak' => $m->hasilLayak(),

            'keadaan'      => $keadaan = Authority::keadaanKartu($m->tgl_expired),
            'keadaanLabel' => Authority::LABEL_KARTU[$keadaan] ?? $keadaan,
            'sisaHari'     => Authority::sisaHari($m->tgl_expired),
        ];
    }

    /**
     * Bagian barisnya yang menyebut ORANGNYA, bukan berkasnya.
     *
     * Termasuk status kepegawaian. Orang yang sudah keluar tetap
     * memegang kartu yang tercatat, dan menghitungnya bersama yang aktif
     * membuat jumlah "kartu habis" membengkak oleh nama-nama yang memang
     * tidak akan diperpanjang lagi.
     *
     * @return array<string,mixed>
     */
    private static function orang(Paspor $p): array
    {
        return [
            'pasporId'   => $p->id,
            'nama'       => $p->nama,
            'nik'        => $p->nik,
            'jabatan'    => $p->jabatan,
            'perusahaan' => $p->company?->name,

            'statusOrang' => $p->status,
            'orangAktif'  => $p->status === self::ORANG_AKTIF,
        ];
    }

    /** @return array<string,mixed> */
    private static function satu(Paspor $p, PasporKartu $k): array
    {
        $efektif = $k->expiredEfektif();

        return self::orang($p) + [
            'id'      => $k->id,
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

        /* Jumlah ORANG, bukan jumlah berkas. Satu orang memegang MCU,
           Mine Permit, dan kerap SIMPER pula — menghitung barisnya
           membuat tiga puluh pekerja terbaca sebagai delapan puluh
           tenaga kerja, dan angka itu dipakai menghitung mandays. */
        $orang = [];
        foreach ($baris as $b) $orang[$b['pasporId']] = $b['orangAktif'];

        return [
            'total'  => $total,

            'manpower'      => count($orang),
            'manpowerAktif' => count(array_filter($orang)),
            'manpowerNonaktif' => count($orang) - count(array_filter($orang)),

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
     * Nama zona D'Best untuk sebuah keadaan.
     *
     * Ambangnya sudah sama — expired / ≤30 / 31–60 / >60 / belum
     * bertanggal — yang berbeda hanya namanya. Dipetakan di satu tempat
     * supaya layar dan server tidak pernah menyebut zona yang berbeda
     * untuk baris yang sama.
     */
    public const ZONA = [
        Authority::HABIS          => 'expired',
        Authority::MENDESAK       => 'kritis',
        Authority::DEKAT          => 'waspada',
        Authority::PANJANG        => 'aman',
        Authority::TAK_BERTANGGAL => 'kosong',
    ];

    /**
     * Berapa baris pada tiap zona, ditambah totalnya.
     *
     * Zona yang KOSONG tetap disebut dengan nilai nol. Kartu zona yang
     * menghilang saat tidak ada isinya membuat deretnya berubah-ubah
     * lebar tiap kali data berubah, dan yang membaca kehilangan tempat
     * yang sudah dihafalnya.
     *
     * @param  list<array<string,mixed>>  $baris
     * @return array<string,int>
     */
    public static function perZona(array $baris): array
    {
        $per = array_fill_keys(array_values(self::ZONA), 0);

        foreach ($baris as $b) {
            $z = self::ZONA[$b['keadaan']] ?? 'kosong';
            $per[$z]++;
        }

        return ['total' => count($baris)] + $per;
    }

    /**
     * Menyaring baris pada satu zona.
     *
     * Zona yang tidak dikenal TIDAK menyaring apa pun — alamat yang
     * disusun tangan atau tautan lama yang menyebut zona yang sudah
     * dihapus tetap memperlihatkan seluruh daftar, bukan daftar kosong
     * yang terbaca sebagai "tidak ada data".
     *
     * @param  list<array<string,mixed>>  $baris
     * @return list<array<string,mixed>>
     */
    public static function saringZona(array $baris, ?string $zona): array
    {
        if (!$zona || !in_array($zona, self::ZONA, true)) return $baris;

        return array_values(array_filter(
            $baris,
            fn ($b) => (self::ZONA[$b['keadaan']] ?? 'kosong') === $zona,
        ));
    }

    /**
     * Ringkasan satu jenis berkas, siap dipasang di daftarnya sendiri.
     *
     * Daftar Mine Permit menjawab "berkas apa saja yang ada"; yang
     * ditanyakan di sebelahnya selalu "lalu berapa yang bermasalah, dan
     * milik siapa". Menjawabnya menuntut pindah halaman, dan yang
     * berpindah halaman hanya orang yang sudah tahu ada yang salah.
     *
     * @param  Collection<int,Paspor>  $orang
     * @return array<string,mixed>
     */
    public static function untukDaftar(Collection $orang, string $jenis): array
    {
        $baris = self::baris($orang, $jenis);

        $ringkas = self::ringkas($baris);

        return [
            'jenis'         => $jenis,
            'ringkas'       => $ringkas,
            'perPerusahaan' => self::perPerusahaan($baris),

            /* Bentuk D'Best: hitungan per zona untuk kartu penyaring,
               dan jumlah ORANG terpisah dari jumlah berkas. */
            'zona'     => self::perZona($baris),
            'manpower' => [
                'total'    => $ringkas['manpower'],
                'aktif'    => $ringkas['manpowerAktif'],
                'nonaktif' => $ringkas['manpowerNonaktif'],
            ],
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
                'orang' => [],
            ];

            $per[$nama]['total']++;
            $per[$nama]['orang'][$b['pasporId']] = $b['orangAktif'];

            if ($b['keadaan'] === Authority::HABIS) $per[$nama]['habis']++;
            else                                    $per[$nama]['aktif']++;

            if (in_array($b['keadaan'], [Authority::MENDESAK, Authority::DEKAT], true)) {
                $per[$nama]['mendekati']++;
            }
        }

        /* Pertanyaan yang selalu menyusul: berapa ORANGNYA. "Dua belas
           berkas habis" pada mitra dengan empat pekerja dan pada mitra
           dengan empat puluh pekerja adalah dua keadaan yang sama sekali
           berbeda, dan angka berkasnya sendiri tidak membedakannya. */
        foreach ($per as $nama => $baris_) {
            $per[$nama]['manpower']      = count($baris_['orang']);
            $per[$nama]['manpowerAktif'] = count(array_filter($baris_['orang']));
            $per[$nama]['manpowerNonaktif'] =
                count($baris_['orang']) - count(array_filter($baris_['orang']));

            unset($per[$nama]['orang']);
        }

        /* Yang paling banyak masalahnya di atas — itu yang perlu
           ditelepon lebih dulu. Bukan urut abjad, yang hanya memudahkan
           mencari nama yang sudah diketahui. */
        usort($per, fn ($a, $b) => [$b['habis'], $b['mendekati'], $b['total']]
                              <=> [$a['habis'], $a['mendekati'], $a['total']]);

        return array_values($per);
    }
}
