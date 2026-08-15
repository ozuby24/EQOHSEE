<?php

namespace App\Support;

/**
 * Hitungan pengendalian biaya operasi penambangan.
 *
 * Batasnya dinyatakan lebih dulu, sebab tanpa itu modul ini mudah
 * disalahpahami: yang dikerjakan di sini adalah AKUNTANSI MANAJEMEN
 * untuk mengendalikan operasi, bukan pembukuan. Angkanya tidak mengikat
 * siapa pun dan tidak menggantikan catatan keuangan; gunanya menjawab
 * pertanyaan pengawas produksi — mengapa biaya bulan ini naik, dan
 * bagian mana yang masih dapat dikendalikan dari pit.
 *
 * Pertanyaan itu tidak terjawab oleh selisih anggaran biasa. Biaya yang
 * melampaui anggaran karena material yang dipindahkan lebih banyak
 * adalah kabar yang berbeda sama sekali dari biaya yang melampaui
 * anggaran pada volume yang sama — yang pertama sering justru
 * diinginkan, yang kedua selalu perlu dijelaskan. Karena itu seluruh
 * selisih di sini dipecah dua, dan hanya bagian keduanya yang boleh
 * dibaca sebagai kinerja.
 *
 * Denominator produksinya sengaja tidak dicatat ulang di modul ini.
 * Tonase dan overburden diambil dari catatan Mine Operations yang sudah
 * disetujui; angka produksi yang diketik dua kali di dua modul akan
 * berselisih cepat atau lambat, dan biaya per ton yang memakai
 * denominator salah terlihat persis seperti biaya per ton yang benar.
 */
final class Biaya
{
    /* ═══════════ biaya satuan ═══════════ */

    /**
     * Biaya per satuan produksi.
     *
     * Volume nol tidak menghasilkan angka tak hingga melainkan null:
     * bulan tanpa produksi memang tidak punya biaya satuan, dan
     * menampilkannya sebagai 0 justru membalik maknanya menjadi
     * "sangat murah".
     */
    public static function perSatuan(float $rupiah, ?float $volume): ?float
    {
        if ($volume === null || $volume <= 0) return null;

        return round($rupiah / $volume, 2);
    }

    /**
     * Selisih nisbah kupas yang sudah membuat biaya per ton tidak
     * sebanding lagi, persen.
     *
     * Biaya per ton batubara turun dengan sendirinya ketika nisbah kupas
     * turun, tanpa satu pun perbaikan di lapangan. Selama nisbahnya
     * bergerak sebesar ini, dua angka biaya per ton tidak boleh
     * dibandingkan seolah setara.
     */
    public const AMBANG_SELISIH_NISBAH_PERSEN = 10.0;

    public static function nisbahKupas(?float $overburdenBcm, ?float $batubaraTon): ?float
    {
        if (!$batubaraTon || $batubaraTon <= 0 || $overburdenBcm === null) return null;

        return round($overburdenBcm / $batubaraTon, 3);
    }

    public static function selisihNisbah(?float $rencana, ?float $nyata): ?float
    {
        if (!$rencana || $rencana <= 0 || $nyata === null) return null;

        return round(($nyata - $rencana) / $rencana * 100, 2);
    }

    /* ═══════════ pemecahan selisih ═══════════ */

    /**
     * Selisih anggaran dipecah menjadi bagian volume dan bagian tarif.
     *
     * Anggaran diluweskan lebih dulu ke volume yang benar-benar terjadi:
     *
     *   anggaranLuwes = (anggaran / volumeRencana) · volumeNyata
     *   selisihVolume = anggaranLuwes − anggaran
     *   selisihTarif  = nyata − anggaranLuwes
     *
     * Keduanya berjumlah tepat selisih totalnya, dan itu bukan kebetulan
     * melainkan syarat: pemecahan yang tidak menutup berarti ada bagian
     * biaya yang tidak dapat dijelaskan oleh keduanya, dan bagian itulah
     * yang akan dipakai berdebat.
     *
     * Tanda positif berarti melampaui anggaran. Untuk bagian TARIF itu
     * selalu perlu dijelaskan; untuk bagian VOLUME belum tentu — memindah
     * material lebih banyak memang menghabiskan uang lebih banyak, dan
     * membacanya sebagai pemborosan adalah kekeliruan yang paling sering
     * terjadi pada laporan biaya tambang.
     *
     * @return array{anggaran:float,nyata:float,anggaranLuwes:?float,total:float,
     *               volume:?float,tarif:?float,unitAnggaran:?float,unitNyata:?float,
     *               dapatDipecah:bool,alasan:string}
     */
    public static function varians(
        float $anggaranRp, ?float $volumeRencana,
        float $nyataRp, ?float $volumeNyata
    ): array {
        $dasar = [
            'anggaran' => round($anggaranRp, 2),
            'nyata'    => round($nyataRp, 2),
            'total'    => round($nyataRp - $anggaranRp, 2),
            'unitAnggaran' => self::perSatuan($anggaranRp, $volumeRencana),
            'unitNyata'    => self::perSatuan($nyataRp, $volumeNyata),
        ];

        if (!$volumeRencana || $volumeRencana <= 0 || !$volumeNyata || $volumeNyata <= 0) {
            return $dasar + [
                'anggaranLuwes' => null, 'volume' => null, 'tarif' => null,
                'dapatDipecah' => false,
                'alasan' => 'Volume rencana atau volume nyata belum ada; selisih hanya dapat dibaca sebagai satu angka.',
            ];
        }

        $unit  = $anggaranRp / $volumeRencana;
        $luwes = $unit * $volumeNyata;

        return $dasar + [
            'anggaranLuwes' => round($luwes, 2),
            'volume'        => round($luwes - $anggaranRp, 2),
            'tarif'         => round($nyataRp - $luwes, 2),
            'dapatDipecah'  => true,
            'alasan'        => 'Selisih dipecah terhadap anggaran yang diluweskan ke volume nyata.',
        ];
    }

    /**
     * Selisih pada akun bersatuan dipecah menjadi harga dan pemakaian.
     *
     *   selisihHarga   = (hargaNyata − hargaRencana) · kuantitasNyata
     *   selisihPakai   = (kuantitasNyata − kuantitasRencana) · hargaRencana
     *
     * Pembagian ini yang paling sering diperlukan pada solar dan ban.
     * Harga solar naik bukan karena ada yang boros di pit, dan menagih
     * pengawas produksi atas kenaikan itu hanya mengajarkan bahwa angka
     * biaya tidak perlu ditanggapi. Yang dapat dikendalikan dari pit
     * adalah pemakaiannya.
     *
     * @return array{harga:?float,pakai:?float,total:float,dapatDipecah:bool,alasan:string}
     */
    public static function variansHargaPakai(
        ?float $hargaRencana, ?float $kuantitasRencana,
        ?float $hargaNyata, ?float $kuantitasNyata
    ): array {
        $kosong = [
            'harga' => null, 'pakai' => null,
            'total' => round(((float) $hargaNyata * (float) $kuantitasNyata)
                             - ((float) $hargaRencana * (float) $kuantitasRencana), 2),
            'dapatDipecah' => false,
            'alasan' => 'Harga atau kuantitas belum lengkap; selisih tidak dapat dipisahkan.',
        ];

        if (!$hargaRencana || $hargaRencana <= 0 || $kuantitasRencana === null
            || !$hargaNyata || $hargaNyata <= 0 || $kuantitasNyata === null) {
            return $kosong;
        }

        $harga = ($hargaNyata - $hargaRencana) * $kuantitasNyata;
        $pakai = ($kuantitasNyata - $kuantitasRencana) * $hargaRencana;

        return [
            'harga' => round($harga, 2),
            'pakai' => round($pakai, 2),
            'total' => round($harga + $pakai, 2),
            'dapatDipecah' => true,
            'alasan' => 'Harga di luar kendali pit; pemakaian di dalamnya.',
        ];
    }

    /* ═══════════ serapan dan proyeksi ═══════════ */

    /** Serapan anggaran, persen. */
    public static function serapan(float $realisasi, ?float $anggaran): ?float
    {
        if (!$anggaran || $anggaran <= 0) return null;

        return round($realisasi / $anggaran * 100, 2);
    }

    /**
     * Selisih serapan biaya terhadap kemajuan produksi yang masih wajar,
     * dalam poin persen.
     */
    public const AMBANG_SERAPAN_MENDAHULUI = 10.0;

    /**
     * Bacaan serapan.
     *
     * Dibandingkan dengan kemajuan PRODUKSI, bukan dengan berjalannya
     * waktu. Anggaran yang terserap 60% pada bulan keenam terdengar tepat
     * dan sering keliru: bila produksinya baru 45%, uangnya berjalan
     * lebih cepat daripada materialnya. Kalender tidak menghasilkan ton.
     */
    public static function bacaSerapan(?float $serapanBiaya, ?float $kemajuanProduksi): array
    {
        if ($serapanBiaya === null || $kemajuanProduksi === null) {
            return ['kelas' => 'tak-diketahui', 'label' => 'Belum dapat dinilai',
                    'ket' => 'Anggaran atau rencana produksi belum lengkap.'];
        }

        $beda = $serapanBiaya - $kemajuanProduksi;

        if ($beda > self::AMBANG_SERAPAN_MENDAHULUI) {
            return ['kelas' => 'mendahului', 'label' => 'Biaya mendahului produksi',
                    'ket' => 'Serapan anggaran '.number_format($beda, 1)
                             .' poin di atas kemajuan produksi; pada laju ini anggaran habis sebelum sasaran tercapai.'];
        }

        if ($beda < -self::AMBANG_SERAPAN_MENDAHULUI) {
            return ['kelas' => 'tertinggal', 'label' => 'Biaya tertinggal dari produksi',
                    'ket' => 'Serapan anggaran jauh di bawah kemajuan produksi — periksa apakah ada biaya yang belum tercatat, '
                             .'sebab hemat yang tidak dapat dijelaskan biasanya berarti pencatatan yang tertinggal.'];
        }

        return ['kelas' => 'sepadan', 'label' => 'Sepadan dengan produksi',
                'ket' => 'Serapan anggaran berjalan sepadan dengan kemajuan produksi.'];
    }

    /**
     * Proyeksi realisasi sampai akhir tahun.
     *
     * Memakai laju rata-rata bulan yang sudah lengkap. Sederhana dengan
     * sengaja: model yang lebih rumit menuntut asumsi musim dan rencana
     * pekerjaan besar yang tidak dimiliki modul ini, dan proyeksi yang
     * terlihat canggih tetapi asumsinya tersembunyi lebih menyesatkan
     * daripada laju rata-rata yang dapat dihitung ulang siapa pun.
     */
    public static function proyeksiTahunan(float $realisasi, int $bulanLengkap): ?float
    {
        if ($bulanLengkap <= 0 || $bulanLengkap > 12) return null;

        return round($realisasi / $bulanLengkap * 12, 2);
    }

    /** Selisih tarif yang sudah pantas ditanyakan, persen dari anggaran. */
    public const AMBANG_VARIANS_TARIF_PERSEN = 5.0;

    /**
     * Apakah bagian tarif sudah cukup besar untuk ditanyakan.
     *
     * Diukur relatif terhadap anggarannya, bukan dalam rupiah: selisih
     * lima puluh juta pada akun bahan bakar adalah derau, dan pada akun
     * alat tulis adalah kesalahan pencatatan.
     */
    public static function tarifMenonjol(?float $variansTarif, float $anggaran): bool
    {
        if ($variansTarif === null || $anggaran <= 0) return false;

        return abs($variansTarif) / $anggaran * 100 > self::AMBANG_VARIANS_TARIF_PERSEN;
    }
}
