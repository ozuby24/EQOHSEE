<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Authority — mesin masa berlaku, dipakai ketiga jenis berkas.
 *
 * Kompetensi, MCU, dan kartu masuk tambang adalah tiga hal yang sangat
 * berbeda, tetapi pertanyaan yang diajukan kepada ketiganya sama persis:
 * berapa hari lagi, dan apakah sudah waktunya bertindak. Ambangnya
 * ditulis SATU KALI di sini supaya ketiganya tidak pelan-pelan
 * berbeda — yang selalu terjadi ketika angka 90 diketik ulang di tiga
 * berkas.
 *
 * EMPAT TINGKAT, dan alasan batasnya:
 *
 *   AMAN      > 180 hari   belum perlu apa-apa
 *   PERHATIAN 91–180 hari  mulai dijadwalkan; pelatihan ulang dan MCU
 *                          punya antrean, dan 6 bulan adalah waktu
 *                          yang wajar untuk mendapat slotnya
 *   SEGERA    31–90 hari   sudah harus ada tanggal, bukan niat
 *   KRITIS    ≤ 30 hari    termasuk yang sudah lewat
 *
 * YANG BELUM PUNYA TANGGAL BUKAN YANG AMAN. Berkas tanpa tanggal
 * kadaluarsa memulangkan keadaan TAK_BERTANGGAL, bukan AMAN — sebab
 * "tidak diketahui kapan habis" dan "masih lama habisnya" adalah dua
 * keadaan yang berlawanan artinya, dan menyamakannya membuat orang yang
 * datanya belum lengkap tampil sebagai orang yang paling siap.
 */
final class Authority
{
    public const AMAN      = 'aman';
    public const PERHATIAN = 'perhatian';
    public const SEGERA    = 'segera';
    public const KRITIS    = 'kritis';

    /** Tidak punya tanggal kadaluarsa sama sekali. */
    public const TAK_BERTANGGAL = 'tak-bertanggal';

    public const BATAS_AMAN      = 180;
    public const BATAS_PERHATIAN = 90;
    public const BATAS_KRITIS    = 30;

    /** Urut dari yang paling mendesak — dipakai mengurutkan daftar. */
    public const URUT = [
        self::KRITIS         => 0,
        self::SEGERA         => 1,
        self::PERHATIAN      => 2,
        self::TAK_BERTANGGAL => 3,
        self::AMAN           => 4,
    ];

    /** Klasifikasi orang menurut Kepdirjen 185.K/37.04/DJB/2019. */
    public const KLASIFIKASI = [
        'PO'  => 'Pengawas Operasional',
        'PT'  => 'Pengawas Teknis',
        'TTK' => 'Tenaga Teknik Khusus',
    ];

    public const HASIL_MCU = ['Fit', 'Fit With Note', 'Temporary Unfit', 'Unfit'];

    /** Hasil MCU yang berarti orangnya BOLEH bekerja. */
    public const MCU_LAYAK = ['Fit', 'Fit With Note'];

    public const JENIS_MCU = ['Awal', 'Berkala', 'Khusus', 'Purna'];

    /**
     * Jenis kartu tinggal di App\Support\AlurMiner, bukan di sini.
     *
     * Di sana ia berdampingan dengan urutan dan syarat penerbitannya —
     * dan daftar jenis yang terpisah dari aturannya adalah persis yang
     * dulu membuat "ID Card" dan "Mine Permit" hidup berdampingan tanpa
     * seorang pun tahu mana yang mendahului mana.
     */
    public const JENIS_KARTU = AlurMiner::JENIS_KARTU;

    /**
     * Sebab sebuah kartu diterbitkan.
     *
     * D'Best memisahkan ketiganya menjadi tiga formulir tersendiri. Di
     * sini satu tabel dengan penanda, sebab medan yang diisi sama persis
     * dan yang berbeda hanya sebabnya — dan tiga formulir yang isinya
     * sama adalah tiga tempat yang harus diubah bersamaan tiap kali satu
     * medan bertambah.
     */
    public const SEBAB_KARTU = ['Terbit', 'Perpanjangan', 'Peningkatan golongan'];

    public const JENIS_INDUKSI = ['Awal', 'Penyegaran', 'Perpanjangan', 'Tamu'];

    public const HASIL_INDUKSI = ['Lulus', 'Tidak Lulus', 'Mengulang'];

    /* ═══════════ sisa hari dan keadaannya ═══════════ */

    /**
     * Sisa hari sampai kadaluarsa; negatif berarti sudah lewat.
     *
     * Dihitung dari AWAL HARI kedua sisinya. Tanpa itu, sertifikat yang
     * habis hari ini memulangkan pecahan hari yang membulat menjadi 0
     * atau 1 tergantung jam berapa halamannya dibuka — dan angka yang
     * berubah sepanjang hari tidak dapat dipakai memutuskan apa pun.
     */
    public static function sisaHari($tgl, ?Carbon $kini = null): ?int
    {
        if (blank($tgl)) return null;

        $kini = ($kini ?: Carbon::now())->copy()->startOfDay();

        return (int) $kini->diffInDays(Carbon::parse($tgl)->startOfDay(), false);
    }

    public static function keadaan($tgl, ?Carbon $kini = null): string
    {
        $sisa = self::sisaHari($tgl, $kini);

        if ($sisa === null)                    return self::TAK_BERTANGGAL;
        if ($sisa <= self::BATAS_KRITIS)       return self::KRITIS;
        if ($sisa <= self::BATAS_PERHATIAN)    return self::SEGERA;
        if ($sisa <= self::BATAS_AMAN)         return self::PERHATIAN;

        return self::AMAN;
    }

    /** Nama keadaan sebagaimana dibaca orang, bukan kode internalnya. */
    public static function label(string $keadaan): string
    {
        return [
            self::AMAN           => 'Aman',
            self::PERHATIAN      => 'Perhatian',
            self::SEGERA         => 'Segera',
            self::KRITIS         => 'Kritis',
            self::TAK_BERTANGGAL => 'Tanpa tanggal',
        ][$keadaan] ?? $keadaan;
    }

    /**
     * Keterangan sisa waktu dalam kalimat, bukan angka bertanda.
     *
     * "-14" menuntut pembacanya menafsirkan sendiri; "lewat 14 hari"
     * tidak. Pada layar yang dibaca cepat di gerbang, selisih itu
     * menentukan.
     */
    public static function keterangan($tgl, ?Carbon $kini = null): string
    {
        $sisa = self::sisaHari($tgl, $kini);

        if ($sisa === null) return 'tanggal belum diisi';
        if ($sisa < 0)      return 'lewat '.abs($sisa).' hari';
        if ($sisa === 0)    return 'habis hari ini';

        return $sisa.' hari lagi';
    }

    /**
     * Ringkasan sekumpulan tanggal menjadi hitungan per keadaan.
     *
     * @param  iterable<mixed>  $tanggal
     * @return array<string,int>
     */
    public static function ringkas(iterable $tanggal, ?Carbon $kini = null): array
    {
        $r = array_fill_keys(array_keys(self::URUT), 0);

        foreach ($tanggal as $t) $r[self::keadaan($t, $kini)]++;

        return $r;
    }

    /* ═══════════ kelayakan kerja ═══════════ */

    /**
     * Boleh bekerja hari ini?
     *
     * Tiga syarat yang harus berlaku BERSAMAAN. Yang mana yang gagal
     * ikut dipulangkan — "tidak layak" tanpa sebab memaksa pengawas
     * membuka tiga halaman untuk mencarinya sendiri, di gerbang, sambil
     * antrean memanjang.
     *
     * INDUKSI, MCU, dan kartu masuk WAJIB; kompetensi tidak selalu —
     * pekerja umum tanpa kompetensi khusus tetap boleh masuk. Yang tidak
     * boleh adalah mengerjakan pekerjaan yang menuntut sertifikat
     * tertentu, dan itu diperiksa izin kerjanya, bukan di sini.
     *
     * BELUM ADA sama beratnya dengan KADALUARSA, dan itu disengaja:
     * gerbang tidak dapat meloloskan orang dengan alasan datanya belum
     * dimasukkan. Sebabnya tetap dibedakan dalam kalimat supaya yang
     * membacanya tahu apakah yang kurang itu pemeriksaannya atau
     * pencatatannya.
     *
     * KARTU YANG BELUM DISETUJUI BUKAN KARTU. Sejak penerbitan kartu
     * punya alur persetujuan, baris yang masih draf hanyalah pengajuan —
     * memperlakukannya sebagai kartu berlaku membuat alurnya tidak ada
     * gunanya, sebab siapa pun dapat meloloskan dirinya sendiri hanya
     * dengan mengisi formulir.
     *
     * @param  ?string  $kartuTgl     kadaluarsa kartu yang SUDAH DISETUJUI
     * @param  ?string  $kartuStatus  status kartu terakhir, dipakai hanya
     *                                untuk menjelaskan ketiadaan di atas
     * @return array{layak:bool, sebab:list<string>}
     */
    public static function kelayakan(
        ?string $mcuTgl = null,
        ?string $mcuHasil = null,
        ?string $kartuTgl = null,
        ?string $kartuStatus = null,
        ?string $induksiTgl = null,
        ?Carbon $kini = null,
    ): array {
        $sebab = [];

        if (blank($induksiTgl)) {
            $sebab[] = 'induksi belum ada';
        } elseif (self::sisaHari($induksiTgl, $kini) < 0) {
            $sebab[] = 'induksi kadaluarsa';
        }

        if (blank($mcuTgl)) {
            $sebab[] = 'MCU belum ada';
        } elseif (self::sisaHari($mcuTgl, $kini) < 0) {
            $sebab[] = 'MCU kadaluarsa';
        }

        if (filled($mcuHasil) && !in_array($mcuHasil, self::MCU_LAYAK, true)) {
            $sebab[] = 'hasil MCU '.$mcuHasil;
        }

        if (blank($kartuTgl)) {
            $sebab[] = match ($kartuStatus) {
                Alur::DRAF     => 'kartu masuk masih draf',
                Alur::DIAJUKAN => 'kartu masuk menunggu persetujuan',
                Alur::DITOLAK  => 'pengajuan kartu masuk ditolak',
                default        => 'kartu masuk belum ada',
            };
        } elseif (self::sisaHari($kartuTgl, $kini) < 0) {
            $sebab[] = 'kartu masuk kadaluarsa';
        }

        return ['layak' => $sebab === [], 'sebab' => $sebab];
    }
}
