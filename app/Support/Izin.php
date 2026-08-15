<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Izin kerja aman (permit to work).
 *
 * Sama seperti peledakan, ini alur yang persetujuannya MENDAHULUI
 * pekerjaannya — dan di sini persetujuan itu adalah izinnya sendiri.
 * Perbedaannya, izin kerja punya satu sifat yang tidak dimiliki modul
 * mana pun di aplikasi ini: ia KEDALUWARSA. Izin yang benar pada pukul
 * delapan pagi tidak lagi benar pada pukul lima sore, sebab keadaan yang
 * diperiksa saat menerbitkannya sudah berubah.
 *
 * Dari sifat itu lahir tiga hal yang dijaga di sini, dan ketiganya
 * adalah kegagalan yang berulang di lapangan, bukan kemungkinan
 * teoretis:
 *
 * 1. Izin lewat waktu tetapi belum ditutup. Pekerjaannya mungkin sudah
 *    selesai, mungkin masih berjalan — dan tidak ada yang tahu yang
 *    mana. Area itu tercatat masih di bawah izin, sehingga izin
 *    berikutnya diterbitkan di atas keadaan yang dikira aman.
 *
 * 2. Uji gas yang basi. Kadar gas di ruang terbatas berubah dalam
 *    hitungan menit ketika ventilasi berhenti atau pekerjaan panas
 *    dimulai. Pengukuran tiga jam lalu tidak menyatakan apa pun tentang
 *    keadaan sekarang, tetapi angkanya tetap terlihat menenangkan di
 *    formulir.
 *
 * 3. Dua izin yang tidak boleh berjalan bersamaan di tempat yang sama.
 *    Pengelasan di dekat ruang terbatas yang sedang dimasuki orang, atau
 *    pengangkatan beban di atas kepala regu lain — keduanya sah bila
 *    dilihat sendiri-sendiri, dan hanya berbahaya bila dilihat bersama.
 *
 * Yang TIDAK dikerjakan di sini: menilai apakah pekerjaannya aman.
 * Aplikasi memeriksa kelengkapan, kesegaran, dan tumpang tindih —
 * penilaian atas bahayanya tetap milik penerbit izin dan pengawas yang
 * berdiri di lokasi.
 */
final class Izin
{
    /**
     * Jenis izin kerja khusus.
     *
     * Daftarnya tertutup karena tiap jenis membawa aturan berbeda di
     * dalam kode ini (uji gas wajib, kebentrokan). Syarat pemeriksaannya
     * yang terbuka — itu data, dan berbeda tiap perusahaan.
     */
    public const JENIS = [
        'panas', 'ruang-terbatas', 'ketinggian', 'penggalian',
        'listrik', 'pengangkatan', 'radiografi',
    ];

    /**
     * Jenis yang menuntut uji gas sebelum diterbitkan.
     *
     * Ruang terbatas jelas. Pekerjaan panas ikut, sebab sumber nyala
     * pada atmosfer yang mengandung uap bahan bakar tidak menuntut ruang
     * tertutup untuk menyala.
     */
    public const WAJIB_UJI_GAS = ['panas', 'ruang-terbatas'];

    /**
     * Umur maksimum uji gas, menit.
     *
     * Angka acuan yang lazim dipakai, bukan ketentuan: kadar gas berubah
     * mengikuti ventilasi dan pekerjaannya, sehingga situs dengan risiko
     * lebih tinggi sepatutnya memendekkannya. Uji ulang setelah setiap
     * jeda kerja adalah praktik yang lebih aman daripada mengandalkan
     * umur maksimum mana pun.
     */
    public const USIA_UJI_GAS_MENIT = 120;

    /**
     * Ambang gas bawaan.
     *
     * Dipakai HANYA selama perusahaan belum menetapkan ambangnya
     * sendiri, dan ketiadaan itu ditandai di tampilan. Nilainya lazim
     * dipakai luas, tetapi yang mengikat adalah prosedur perusahaan dan
     * ketentuan yang berlaku di wilayahnya.
     *
     * @return array<string,array{min:?float,maks:?float,satuan:string,nama:string}>
     */
    public static function ambangBawaan(): array
    {
        return [
            'o2'  => ['min' => 19.5, 'maks' => 23.5, 'satuan' => '%',   'nama' => 'Oksigen'],
            'lel' => ['min' => null, 'maks' => 10.0, 'satuan' => '%LEL','nama' => 'Gas mudah terbakar'],
            'co'  => ['min' => null, 'maks' => 25.0, 'satuan' => 'ppm', 'nama' => 'Karbon monoksida'],
            'h2s' => ['min' => null, 'maks' => 10.0, 'satuan' => 'ppm', 'nama' => 'Hidrogen sulfida'],
        ];
    }

    public static function perluUjiGas(string $jenis): bool
    {
        return in_array($jenis, self::WAJIB_UJI_GAS, true);
    }

    /* ═══════════ masa berlaku ═══════════ */

    /**
     * Keadaan izin terhadap waktu sekarang.
     *
     * Sengaja dipisahkan dari status alur. Sebuah izin dapat berstatus
     * "disetujui" dan sekaligus sudah lewat waktunya, dan justru
     * gabungan itulah yang berbahaya — pada tampilan ia terlihat sah.
     */
    public static function keadaanWaktu(?Carbon $mulai, ?Carbon $selesai, ?Carbon $sekarang = null): array
    {
        $sekarang ??= Carbon::now();

        if (!$mulai || !$selesai) {
            return ['kelas' => 'tak-diketahui', 'label' => 'Waktu belum lengkap', 'sisaMenit' => null];
        }

        if ($sekarang->lessThan($mulai)) {
            return ['kelas' => 'belum-mulai', 'label' => 'Belum mulai',
                    'sisaMenit' => (int) $sekarang->diffInMinutes($mulai, false)];
        }

        if ($sekarang->greaterThan($selesai)) {
            return ['kelas' => 'lewat', 'label' => 'Lewat waktu',
                    'sisaMenit' => -(int) $selesai->diffInMinutes($sekarang, false)];
        }

        return ['kelas' => 'berlaku', 'label' => 'Sedang berlaku',
                'sisaMenit' => (int) $sekarang->diffInMinutes($selesai, false)];
    }

    /** Dua rentang waktu bersinggungan. */
    public static function tumpangTindih(
        ?Carbon $mulaiA, ?Carbon $selesaiA, ?Carbon $mulaiB, ?Carbon $selesaiB
    ): bool {
        if (!$mulaiA || !$selesaiA || !$mulaiB || !$selesaiB) return false;

        // Bersentuhan ujung tidak dihitung tumpang tindih: satu izin
        // berakhir tepat saat yang lain mulai adalah pergantian giliran,
        // bukan dua pekerjaan berbarengan.
        return $mulaiA->lessThan($selesaiB) && $mulaiB->lessThan($selesaiA);
    }

    /* ═══════════ kebentrokan jenis ═══════════ */

    /**
     * Pasangan jenis yang tidak boleh berjalan bersamaan di satu lokasi.
     *
     * '*' berarti bentrok dengan jenis apa pun.
     *
     * Daftarnya sengaja pendek. Penyaring yang menandai terlalu banyak
     * akan dimatikan orang, dan penyaring yang dimatikan tidak menjaga
     * apa pun. Yang masuk hanya yang akibatnya langsung dan tidak
     * bergantung penilaian: percikan pada atmosfer yang sedang dimasuki
     * orang, dan beban tergantung di atas kepala regu lain.
     *
     * Ini penyaring, bukan keputusan. Dua izin yang tidak tercantum di
     * sini bisa saja tetap tidak boleh berbarengan — yang menilai adalah
     * penerbit izin, bukan daftar ini.
     */
    public const BENTROK = [
        ['panas', 'ruang-terbatas'],
        ['pengangkatan', '*'],
    ];

    public static function bentrok(string $a, string $b): bool
    {
        foreach (self::BENTROK as [$x, $y]) {
            if ($y === '*') {
                // Beban tergantung bentrok dengan pekerjaan lain di
                // bawahnya, termasuk pengangkatan lain — tetapi bukan
                // dengan dirinya sendiri pada izin yang sama.
                if ($a === $x || $b === $x) return true;
                continue;
            }

            if (($a === $x && $b === $y) || ($a === $y && $b === $x)) return true;
        }

        return false;
    }

    /** Alasan kebentrokan, untuk ditulis apa adanya pada peringatan. */
    public static function alasanBentrok(string $a, string $b): string
    {
        if ($a === 'pengangkatan' || $b === 'pengangkatan') {
            return 'Beban tergantung berada di atas pekerjaan lain di lokasi yang sama.';
        }

        return 'Sumber nyala bekerja pada atmosfer yang sedang dimasuki orang.';
    }

    /* ═══════════ uji gas ═══════════ */

    /** Umur pengukuran dalam menit. */
    public static function usiaUji(?Carbon $waktuUji, ?Carbon $sekarang = null): ?int
    {
        if (!$waktuUji) return null;

        $sekarang ??= Carbon::now();

        return (int) $waktuUji->diffInMinutes($sekarang, false);
    }

    public static function ujiSegar(?Carbon $waktuUji, ?Carbon $sekarang = null, ?int $batasMenit = null): bool
    {
        $usia = self::usiaUji($waktuUji, $sekarang);
        if ($usia === null) return false;

        // Pengukuran bertanggal masa depan bukan pengukuran yang segar
        // melainkan salah ketik, dan menerimanya sebagai segar membuka
        // izin atas dasar angka yang belum pernah diambil.
        if ($usia < 0) return false;

        return $usia <= ($batasMenit ?? self::USIA_UJI_GAS_MENIT);
    }

    /**
     * Periksa satu set bacaan gas terhadap ambang.
     *
     * Parameter yang tidak diukur dilaporkan sebagai TIDAK DIUKUR, bukan
     * sebagai lulus. Perbedaan itu menentukan: formulir yang menandai
     * semuanya hijau padahal H2S tidak pernah diukur memberi rasa aman
     * yang justru berasal dari ketiadaan datanya.
     *
     * @param  array<string,float|null> $bacaan
     * @param  array<string,array{min:?float,maks:?float,satuan:string,nama:string}> $ambang
     * @return array{lulus:bool,lengkap:bool,rinci:list<array<string,mixed>>}
     */
    public static function periksaGas(array $bacaan, array $ambang): array
    {
        $rinci = [];
        $lulus = true;
        $lengkap = true;

        foreach ($ambang as $kode => $a) {
            $nilai = $bacaan[$kode] ?? null;

            if ($nilai === null) {
                $lengkap = false;
                $rinci[] = ['kode' => $kode, 'nama' => $a['nama'], 'nilai' => null,
                            'satuan' => $a['satuan'], 'min' => $a['min'], 'maks' => $a['maks'],
                            'keadaan' => 'tidak-diukur'];
                continue;
            }

            $nilai = (float) $nilai;
            $keluar = ($a['min'] !== null && $nilai < $a['min'])
                   || ($a['maks'] !== null && $nilai > $a['maks']);

            if ($keluar) $lulus = false;

            $rinci[] = ['kode' => $kode, 'nama' => $a['nama'], 'nilai' => $nilai,
                        'satuan' => $a['satuan'], 'min' => $a['min'], 'maks' => $a['maks'],
                        'keadaan' => $keluar ? 'di-luar-ambang' : 'aman'];
        }

        return ['lulus' => $lulus, 'lengkap' => $lengkap, 'rinci' => $rinci];
    }
}
