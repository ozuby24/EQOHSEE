<?php

namespace App\Support\Miners;

use App\Models\Miners\McuOrang;
use App\Models\Miners\Pekerja;
use App\Models\Miners\Permit;
use App\Models\Miners\Simper;
use App\Support\Authority;
use Illuminate\Support\Collection;

/**
 * Pemantauan masa berlaku berkas kelayakan — di atas data MINERS.
 *
 * Menggantikan App\Support\PemantauanBerkas, yang mengerjakan hal yang
 * sama di atas tabel `paspor_*` lama dan sudah dibuang bersama
 * perpindahan ini. Keduanya menjawab pertanyaan yang sama, ditanyakan
 * tiap pagi di gerbang: siapa yang hari ini tidak boleh masuk, dan
 * siapa yang minggu depan tidak boleh masuk kalau tidak ada yang
 * mengurusnya sekarang.
 *
 * ── Kenapa ada dua, dan kenapa yang ini yang dipakai dasbor ──
 *
 * Modul Miners menggantikan modul Authority lama, tetapi dasbor masih
 * membaca `paspor`. Akibatnya dasbor menyebut 35 tenaga kerja sementara
 * modul Miners menyebut 42 — satu situs, dua angka, dua layar
 * berurutan. Angka dasbor itu pula yang dipakai memperkirakan mandays
 * audit, jadi selisihnya tidak berhenti sebagai kejanggalan tampilan.
 *
 * ── Tangga keadaannya sengaja MENGIKUTI Authority, bukan Keadaan ──
 *
 * App\Support\Miners\Keadaan punya empat keadaan (berlaku, mendekati,
 * habis, belum) yang dipakai layar-layar Miners. Dasbor menggambar pita
 * Authority yang lebih halus — habis, mendesak (≤30 hari), dekat (31–60),
 * panjang — dan pita itu sudah dihafal orang yang membacanya tiap pagi.
 * Menukar tangganya sekalian akan mengubah arti warna pada satu-satunya
 * layar yang dibuka semua orang. Yang diganti di sini SUMBER DATANYA,
 * bukan bahasa visualnya.
 *
 * ── Yang dipantau tanggal EFEKTIF ──
 *
 * Mine Permit berpijak pada MCU dan SIMPER pada SIM kepolisian; kartu
 * tidak dapat hidup lebih lama daripada dasarnya. Model Miners sudah
 * menghitungnya lewat habisEfektif(), dan itulah yang dipakai — bukan
 * `berlaku_sampai` yang tercetak.
 */
final class PemantauanMiners
{
    public const MCU    = 'MCU';
    public const PERMIT = 'Mine Permit';
    public const SIMPER = 'SIMPER';

    /** Jenis berkas yang dipantau, berurut sebagaimana rantainya. */
    public const JENIS = [self::MCU, self::PERMIT, self::SIMPER];

    /**
     * Pekerja beserta berkas yang dibutuhkan pemantauan, sudah dimuat.
     *
     * Dipusatkan di sini supaya pemanggilnya tidak perlu mengingat
     * relasi mana yang dibaca — yang terlewat tidak memulangkan galat,
     * hanya satu kueri tambahan per pekerja per relasi.
     *
     * @return Collection<int,Pekerja>
     */
    public static function muat(): Collection
    {
        return Pekerja::with([
            'company',
            'jabatan',
            'mcu' => fn ($q) => $q->orderByDesc('tanggal_periksa'),
            'permit.mcuOrang',
            'simper.permit.mcuOrang',
        ])->get();
    }

    /**
     * Baris pemantauan untuk sekumpulan pekerja.
     *
     * @param  Collection<int,Pekerja>  $orang
     * @return list<array<string,mixed>>
     */
    public static function baris(Collection $orang, ?string $jenis = null): array
    {
        $keluar = [];

        foreach ($orang as $p) {
            /* MCU dihitung dari yang TERAKHIR saja, bukan dari seluruh
               riwayatnya. Pemeriksaan tahun lalu memang sudah habis masa
               berlakunya, dan menghitungnya sebagai baris "habis"
               membuat setiap orang yang rajin MCU tampak paling
               bermasalah — persis kebalikan dari yang sebenarnya. */
            if ($jenis === null || $jenis === self::MCU) {
                if ($m = $p->mcu->sortByDesc('tanggal_periksa')->first()) {
                    $keluar[] = self::barisMcu($p, $m);
                }
            }

            if ($jenis === null || $jenis === self::PERMIT) {
                foreach ($p->permit as $k) {
                    if (!self::terpakai($k->status)) continue;

                    $keluar[] = self::barisKartu($p, self::PERMIT, $k->no_registrasi,
                        $k->tanggal, $k->berlaku_sampai, $k->habisEfektif(),
                        $k->gugurKarenaMcu() ? 'MCU' : null);
                }
            }

            if ($jenis === null || $jenis === self::SIMPER) {
                foreach ($p->simper as $k) {
                    if (!self::terpakai($k->status)) continue;

                    $keluar[] = self::barisKartu($p, self::SIMPER, $k->no_simper,
                        $k->tanggal, $k->berlaku_sampai, $k->habisEfektif(),
                        $k->penyebabHabis());
                }
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
     * Status kartu yang benar-benar menjadi izin.
     *
     * Draf, pengajuan yang masih menunggu, yang ditolak, dan yang sudah
     * dicabut bukan izin. Menghitungnya sebagai kartu aktif membuat
     * jumlah pemegang izin lebih besar daripada yang sebenarnya boleh
     * masuk — dan selisih itu justru pada orang-orang yang berkasnya
     * belum beres.
     *
     * Nilainya SATU, 'terbit', dan itu bukan tebakan: Permit::berlaku()
     * dan Simper::berlaku() keduanya menolak apa pun selain itu.
     * Menerima daftar yang lebih longgar di sini akan membuat pemantauan
     * menghitung kartu yang model pemiliknya sendiri anggap belum
     * berlaku.
     */
    public const TERBIT = 'terbit';

    private static function terpakai(?string $status): bool
    {
        return $status === self::TERBIT;
    }

    /** @return array<string,mixed> */
    private static function barisMcu(Pekerja $p, McuOrang $m): array
    {
        $habis = $m->berlaku_sampai;

        return self::orang($p) + [
            'id'          => 'mcu-'.$m->id,
            'jenis'       => self::MCU,
            'nomor'       => null,
            'tglTerbit'   => $m->tanggal_periksa?->toDateString(),

            /* MCU tidak berdasar berkas lain — MCU-lah yang menjadi
               dasar bagi Mine Permit, bukan sebaliknya — sehingga
               tanggal efektifnya sama dengan yang tercetak. */
            'tglTercetak' => $habis?->toDateString(),
            'tglEfektif'  => $habis?->toDateString(),
            'dibatasiDasar' => false,
            'namaDasar'     => null,

            /* Hasil pemeriksaannya, bukan hanya tanggalnya. MCU yang
               masih berlaku tetapi berhasil "unfit" tetap melarang orang
               bekerja, dan pita hijau tanpa keterangan ini membuatnya
               terbaca sebagai aman. */
            'hasilLayak'  => $m->layak(),

            'keadaan'      => $keadaan = Authority::keadaanKartu($habis),
            'keadaanLabel' => Authority::LABEL_KARTU[$keadaan] ?? $keadaan,
            'sisaHari'     => Authority::sisaHari($habis),
        ];
    }

    /** @return array<string,mixed> */
    private static function barisKartu(
        Pekerja $p, string $jenis, ?string $nomor,
        $terbit, $tercetak, $efektif, ?string $namaDasar,
    ): array {
        return self::orang($p) + [
            'id'        => mb_strtolower($jenis).'-'.$nomor,
            'jenis'     => $jenis,
            'nomor'     => $nomor,
            'tglTerbit' => $terbit?->toDateString(),

            /* Dua tanggal berdampingan, dan keduanya perlu. Yang
               tercetak adalah yang tertulis di kartunya; yang efektif
               adalah yang benar-benar berlaku. Menyembunyikan salah
               satunya membuat selisihnya tampak seperti salah catat. */
            'tglTercetak' => $tercetak?->toDateString(),
            'tglEfektif'  => $efektif?->toDateString(),

            'dibatasiDasar' => $namaDasar !== null,
            'namaDasar'     => $namaDasar,
            'hasilLayak'    => null,

            'keadaan'      => $keadaan = Authority::keadaanKartu($efektif),
            'keadaanLabel' => Authority::LABEL_KARTU[$keadaan] ?? $keadaan,
            'sisaHari'     => Authority::sisaHari($efektif),
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
    private static function orang(Pekerja $p): array
    {
        return [
            'orangId'    => $p->id,
            'nama'       => $p->nama,
            'nik'        => $p->nik,
            'jabatan'    => $p->jabatan?->nama,
            'perusahaan' => $p->company?->name,

            'statusOrang' => $p->status,
            'orangAktif'  => $p->aktif(),
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
        $per = array_fill_keys([
            Authority::HABIS, Authority::MENDESAK, Authority::DEKAT,
            Authority::PANJANG, Authority::TAK_BERTANGGAL,
        ], 0);

        foreach ($baris as $b) {
            $per[$b['keadaan']] = ($per[$b['keadaan']] ?? 0) + 1;
        }

        $total = count($baris);

        /* Jumlah ORANG, bukan jumlah berkas. Satu orang memegang MCU,
           Mine Permit, dan kerap SIMPER pula — menghitung barisnya
           membuat tiga puluh pekerja terbaca sebagai delapan puluh
           tenaga kerja, dan angka itu dipakai menghitung mandays. */
        $orang = [];
        foreach ($baris as $b) $orang[$b['orangId']] = $b['orangAktif'];

        $aktif = count(array_filter($orang));

        return [
            'total' => $total,

            'manpower'         => count($orang),
            'manpowerAktif'    => $aktif,
            'manpowerNonaktif' => count($orang) - $aktif,

            'aktif' => $total - $per[Authority::HABIS],
            'habis' => $per[Authority::HABIS],

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
                'orang' => [],
            ];

            $per[$nama]['total']++;
            $per[$nama]['orang'][$b['orangId']] = $b['orangAktif'];

            if ($b['keadaan'] === Authority::HABIS) $per[$nama]['habis']++;
            else                                    $per[$nama]['aktif']++;

            if (in_array($b['keadaan'], [Authority::MENDESAK, Authority::DEKAT], true)) {
                $per[$nama]['mendekati']++;
            }
        }

        foreach ($per as $nama => $isi) {
            $aktif = count(array_filter($isi['orang']));

            $per[$nama]['manpower']         = count($isi['orang']);
            $per[$nama]['manpowerAktif']    = $aktif;
            $per[$nama]['manpowerNonaktif'] = count($isi['orang']) - $aktif;

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
