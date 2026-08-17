<?php

namespace App\Support;

use App\Models\Paspor;
use App\Models\PasporKartu;

/**
 * Urutan yang harus dilalui seorang pekerja baru, dan penjaganya.
 *
 * EMPAT TAHAP, dan yang ketiga tidak dapat didahulukan:
 *
 *   1. MCU        diajukan, diperiksa, hasilnya kembali dan menyatakan
 *                 layak. Ini yang pertama karena ia yang paling lama:
 *                 kliniknya punya antrean, dan hasilnya baru kembali
 *                 berhari-hari kemudian.
 *
 *   2. INDUKSI    baru masuk akal SESUDAH orangnya dinyatakan sehat.
 *                 Menginduksi orang yang ternyata Unfit adalah setengah
 *                 hari kelas yang terbuang, dan yang lebih buruk:
 *                 induksinya tercatat, sehingga di layar orang itu
 *                 tampak lebih siap daripada sebenarnya.
 *
 *   3. MINE PERMIT diterbitkan OHSE sesudah keduanya lengkap. Inilah
 *                 yang dicetak dan dibawa orangnya ke gerbang.
 *
 *   4. MINE LICENSE hanya bagi yang mengemudi, dan hanya SESUDAH
 *                 Mine Permit terbit. Ia izin TAMBAHAN — bukan
 *                 penggantinya — sebab yang boleh mengemudi di area
 *                 tambang lebih sedikit daripada yang boleh masuk.
 *
 * MENGAPA URUTANNYA DIPAKSAKAN, dan di mana tepatnya.
 *
 * Sebelum ini setiap bagian dapat diisi kapan saja dan dalam urutan apa
 * pun. Akibatnya bukan sekadar membingungkan: Mine Permit dapat terbit
 * untuk orang yang MCU-nya belum pernah ada, dan tidak ada satu pun
 * layar yang menunjukkan bahwa itu yang barusan terjadi. Penjagaannya
 * dipasang saat PENGAJUAN, bukan saat menyimpan draf — draf memang
 * boleh setengah jadi, dan menolak simpanan setengah jadi membuat orang
 * menyimpan datanya di luar sistem sampai lengkap.
 *
 * TIDAK ADA JALAN PINTAS, dan itu disengaja. Pekerja lama yang
 * berkasnya masih di kertas tetap harus dimasukkan MCU dan induksinya
 * lebih dulu. Itu memang pekerjaan tambahan — tetapi Mine Permit yang
 * berdiri tanpa MCU di dalam sistem adalah persis lubang yang hendak
 * ditutup sistem ini, dan tombol "lewati" akan menjadi jalan yang
 * dipakai setiap kali sedang buru-buru.
 */
final class AlurMiner
{
    public const MCU     = 'mcu';
    public const INDUKSI = 'induksi';
    public const PERMIT  = 'permit';
    public const LICENSE = 'license';

    /** Keadaan tiap tahap. */
    public const SELESAI  = 'selesai';
    public const BERJALAN = 'berjalan';   // sudah dimulai, belum tuntas
    public const SIAP     = 'siap';       // syaratnya terpenuhi, belum dimulai
    public const TERKUNCI = 'terkunci';   // syarat sebelumnya belum lengkap

    /** Jenis kartu. Mine Permit dulu, Mine License menyusul. */
    public const KARTU_PERMIT  = 'Mine Permit';
    public const KARTU_LICENSE = 'Mine License';
    public const KARTU_VISITOR = 'Visitor';

    public const JENIS_KARTU = [self::KARTU_PERMIT, self::KARTU_LICENSE, self::KARTU_VISITOR];

    /* ═══════════ syarat tiap tahap ═══════════ */

    /**
     * Boleh mencatat induksi?
     *
     * @return ?string null bila boleh; sebabnya bila tidak
     */
    public static function halanganInduksi(Paspor $p): ?string
    {
        $m = $p->mcuTerakhir();

        if (!$m) {
            return 'MCU belum ada. Induksi dijadwalkan sesudah hasil MCU kembali '
                 .'dan menyatakan layak.';
        }

        if (blank($m->hasil)) {
            return 'Hasil MCU belum kembali dari pemeriksa. '
                 .'Isi hasilnya lebih dulu di Pengajuan MCU.';
        }

        if (!$m->hasilLayak()) {
            return 'Hasil MCU terakhir "'.$m->hasil.'". '
                 .'Induksi menunggu pemeriksaan ulang yang menyatakan layak.';
        }

        if (Authority::sisaHari($m->tgl_expired) < 0) {
            return 'MCU terakhir sudah kadaluarsa ('.$m->keterangan().'). '
                 .'Perlu MCU baru sebelum induksi.';
        }

        return null;
    }

    /**
     * Boleh MENGAJUKAN sebuah kartu?
     *
     * Diperiksa saat pengajuan, bukan saat menyimpan draf.
     *
     * @return list<string> kosong bila boleh
     */
    public static function halanganKartu(PasporKartu $k): array
    {
        $p = $k->paspor;

        if (!$p) return ['Kartu ini tidak terhubung ke pekerja mana pun.'];

        return match ($k->jenis) {
            self::KARTU_VISITOR => self::halanganVisitor($p),
            self::KARTU_LICENSE => self::halanganLicense($p, $k),
            default             => self::halanganPermit($p),
        };
    }

    /** @return list<string> */
    private static function halanganPermit(Paspor $p): array
    {
        $kurang = [];

        if ($sebab = self::halanganInduksi($p)) {
            $kurang[] = $sebab;
        }

        $i = $p->induksiBerlaku();

        if (!$i) {
            $kurang[] = 'Induksi belum ada, atau yang terakhir belum lulus.';
        } elseif (Authority::sisaHari($i->tgl_expired) < 0) {
            $kurang[] = 'Induksi sudah kadaluarsa ('.$i->keterangan().').';
        }

        return $kurang;
    }

    /**
     * Mine License menuntut Mine Permit yang SUDAH TERBIT, bukan sekadar
     * MCU dan induksi.
     *
     * Bedanya penting: seseorang dapat memenuhi MCU dan induksi tetapi
     * pengajuan permitnya masih menunggu keputusan OHSE. Menerbitkan
     * izin mengemudi bagi orang yang izin masuknya belum ada berarti
     * mengizinkan mengemudi di area yang ia sendiri belum boleh masuki.
     *
     * @return list<string>
     */
    private static function halanganLicense(Paspor $p, PasporKartu $k): array
    {
        $kurang = [];

        $permit = $p->kartu->first(
            fn (PasporKartu $x) => $x->jenis === self::KARTU_PERMIT
                && $x->status === Alur::DISETUJUI
                && Authority::sisaHari($x->tgl_expired) >= 0
        );

        if (!$permit) {
            $kurang[] = 'Mine Permit belum terbit atau sudah kadaluarsa. '
                      .'Mine License adalah izin tambahan di atasnya.';
        }

        /* Dokumen tambahan khas pengemudi. Diperiksa dari medan
           kartunya sendiri, sebab ketiganya memang dilampirkan pada
           pengajuan ini — bukan pada berkas orangnya. */
        if (blank($k->sim_polisi)) {
            $kurang[] = 'Nomor SIM kepolisian belum diisi.';
        } elseif ($k->sim_polisi_expired
               && Authority::sisaHari($k->sim_polisi_expired) < 0) {
            $kurang[] = 'SIM kepolisian sudah kadaluarsa.';
        }

        if (blank($k->berkas_ddt)) {
            $kurang[] = 'Sertifikat defensive driving belum dilampirkan.';
        }

        if (blank($k->golongan)) {
            $kurang[] = 'Golongan kendaraan yang dimintakan belum diisi.';
        }

        return $kurang;
    }

    /**
     * Kartu tamu menuntut induksi, TIDAK menuntut MCU.
     *
     * Tamu tidak bekerja; ia berkunjung, ditemani, dan pergi hari itu
     * juga. Menuntutnya MCU berarti tidak ada tamu yang pernah dapat
     * masuk — dan yang terjadi berikutnya adalah orang masuk tanpa
     * kartu sama sekali.
     *
     * @return list<string>
     */
    private static function halanganVisitor(Paspor $p): array
    {
        return $p->induksiBerlaku()
            ? []
            : ['Induksi tamu belum ada. Sesingkat apa pun kunjungannya, '
              .'ia harus tahu bahaya apa yang ada di sana.'];
    }

    /* ═══════════ gambaran tahapan ═══════════ */

    /**
     * Keadaan keempat tahap bagi satu orang, untuk digambar berurutan.
     *
     * @return list<array<string,mixed>>
     */
    public static function tahapan(Paspor $p): array
    {
        $m = $p->mcuTerakhir();
        $i = $p->induksiBerlaku();

        $permit  = self::kartuBerlaku($p, self::KARTU_PERMIT);
        $license = self::kartuBerlaku($p, self::KARTU_LICENSE);

        /* MCU */
        $mcuSelesai = $m && $m->hasilLayak() && Authority::sisaHari($m->tgl_expired) >= 0;
        $mcuJalan   = $m && blank($m->hasil);

        $tahap = [[
            'kode'  => self::MCU,
            'urut'  => 1,
            'label' => 'MCU',
            'terang' => 'Pemeriksaan kesehatan — hasilnya harus kembali dan menyatakan layak',
            'keadaan' => $mcuSelesai ? self::SELESAI : ($mcuJalan ? self::BERJALAN : self::SIAP),
            'ringkas' => $m
                ? ($m->hasil ? $m->hasil.' · '.$m->keterangan() : 'menunggu hasil dari pemeriksa')
                : 'belum diajukan',
            'jalur' => route('miners.mcu.index'),
        ]];

        /* Induksi */
        $halanganInduksi = self::halanganInduksi($p);

        $tahap[] = [
            'kode'  => self::INDUKSI,
            'urut'  => 2,
            'label' => 'Induksi',
            'terang' => 'Pengenalan bahaya dan keadaan darurat, sesudah dinyatakan sehat',
            'keadaan' => $i && Authority::sisaHari($i->tgl_expired) >= 0
                ? self::SELESAI
                : ($halanganInduksi ? self::TERKUNCI : self::SIAP),
            'ringkas' => $i ? $i->jenis.' · '.$i->keterangan() : 'belum ada',
            'sebab'   => $halanganInduksi,
        ];

        /* Mine Permit */
        $halanganPermit = self::halanganPermit($p);

        $tahap[] = [
            'kode'  => self::PERMIT,
            'urut'  => 3,
            'label' => 'Mine Permit',
            'terang' => 'Diverifikasi dan diterbitkan OHSE — inilah yang dicetak dan dibawa ke gerbang',
            'keadaan' => $permit
                ? self::SELESAI
                : ($halanganPermit ? self::TERKUNCI : self::SIAP),
            'ringkas' => $permit
                ? 'terbit · '.$permit->keterangan()
                : (self::adaKartuMenunggu($p, self::KARTU_PERMIT) ? 'menunggu keputusan OHSE' : 'belum diajukan'),
            'sebab' => $halanganPermit ? implode(' ', $halanganPermit) : null,
            'cetak' => $permit ? route('miners.permit.cetak', [$p, $permit]) : null,
        ];

        /* Mine License — opsional */
        $tahap[] = [
            'kode'  => self::LICENSE,
            'urut'  => 4,
            'label' => 'Mine License',
            'terang' => 'Izin mengemudi di area tambang — hanya bagi yang mengemudi, dan hanya di atas Mine Permit',
            'opsional' => true,
            'keadaan' => $license
                ? self::SELESAI
                : ($permit ? self::SIAP : self::TERKUNCI),
            'ringkas' => $license
                ? ($license->golongan ? 'gol. '.$license->golongan.' · ' : '').$license->keterangan()
                : 'belum diajukan',
            'sebab' => $permit ? null : 'Mine Permit belum terbit.',
        ];

        return $tahap;
    }

    private static function kartuBerlaku(Paspor $p, string $jenis): ?PasporKartu
    {
        return $p->kartu->first(
            fn (PasporKartu $k) => $k->jenis === $jenis
                && $k->status === Alur::DISETUJUI
                && Authority::sisaHari($k->tgl_expired) >= 0
        );
    }

    private static function adaKartuMenunggu(Paspor $p, string $jenis): bool
    {
        return $p->kartu->contains(
            fn (PasporKartu $k) => $k->jenis === $jenis && $k->status === Alur::DIAJUKAN
        );
    }

    /**
     * Tahap yang sedang ditunggu orang ini — untuk daftar dan penyaringan.
     *
     * Memulangkan tahap PERTAMA yang belum selesai, mengabaikan yang
     * opsional: seseorang yang sudah punya Mine Permit dan tidak
     * mengemudi bukan orang yang tertahan.
     */
    public static function tahapSekarang(Paspor $p): ?array
    {
        foreach (self::tahapan($p) as $t) {
            if (($t['opsional'] ?? false) || $t['keadaan'] === self::SELESAI) continue;

            return $t;
        }

        return null;
    }
}
