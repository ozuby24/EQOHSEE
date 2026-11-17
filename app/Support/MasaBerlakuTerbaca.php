<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

/**
 * Membaca masa berlaku dari berkas SIM yang diunggah.
 *
 * MENGUSULKAN, TIDAK MENETAPKAN. Yang dikembalikan selalu disertai
 * dari mana angkanya diambil dan potongan teks yang membuatnya
 * terbaca, supaya orang dapat memeriksanya dalam sedetik. Tanggal
 * kedaluwarsa yang terisi sendiri secara diam-diam lebih buruk
 * daripada kolom kosong: kolom kosong terlihat belum diisi, sedangkan
 * tanggal yang salah terbaca sebagai sudah diperiksa — dan yang
 * memakainya di gerbang tidak punya cara mengetahui bedanya.
 *
 * DUA SUMBER, KEDUANYA PASTI:
 *
 *  1. Nama berkasnya. "SIM_B2_Budi_31-12-2028.jpg" menyimpan tanggalnya
 *     apa adanya, dan begitulah kebanyakan berkas dinamai di lapangan.
 *  2. Teks di dalam PDF-nya, bila `pdftotext` tersedia di server.
 *     Hasil pindaian berupa gambar tidak punya lapisan teks dan tidak
 *     akan terbaca — itu keadaan yang wajar, bukan kegagalan.
 *
 * TIDAK ADA TEBAKAN DI LUAR ITU. Tidak ada OCR, tidak ada model bahasa,
 * tidak ada aturan "SIM berlaku lima tahun sejak terbit" — aturan itu
 * berubah dan pernah berbeda (dahulu mengikuti tanggal lahir), dan
 * tanggal hasil hitungan yang keliru tidak dapat dibedakan dari tanggal
 * hasil bacaan yang benar begitu ia tersimpan.
 */
final class MasaBerlakuTerbaca
{
    /** Kata yang mendahului tanggal berlaku pada dokumen Indonesia. */
    private const KATA = ['berlaku', 's/d', 'sampai', 'valid', 'expired', 'expiry', 'masa berlaku'];

    /**
     * Sejauh mana tanggal masih masuk akal sebagai masa berlaku.
     *
     * Batas bawah menolak tanggal lahir dan tanggal terbit lama; batas
     * atas menolak angka yang kebetulan berbentuk tanggal. SIM Indonesia
     * berlaku lima tahun, jadi lima belas tahun sudah sangat longgar.
     */
    private const MUNDUR_TAHUN = 10;
    private const MAJU_TAHUN   = 15;

    /**
     * @return array{tanggal:string,sumber:string,petikan:string,pasti:bool}|null
     */
    public static function dariUnggahan(UploadedFile $berkas, ?Carbon $kini = null): ?array
    {
        return self::dariNama($berkas->getClientOriginalName(), $kini)
            ?? self::dariIsi($berkas, $kini);
    }

    /**
     * Tanggal yang tertulis pada nama berkasnya.
     *
     * @return array{tanggal:string,sumber:string,petikan:string,pasti:bool}|null
     */
    public static function dariNama(string $nama, ?Carbon $kini = null): ?array
    {
        foreach (self::tanggalDalam($nama, $kini) as $t) {
            return [
                'tanggal' => $t['tanggal'],
                'sumber'  => 'nama berkas',
                'petikan' => $nama,
                'pasti'   => $t['pasti'],
            ];
        }

        return null;
    }

    /**
     * Tanggal yang tertulis di dalam PDF-nya.
     *
     * Baris yang menyebut kata masa berlaku didahulukan. Sebuah SIM
     * memuat beberapa tanggal — terbit, lahir, berlaku — dan mengambil
     * yang pertama ditemukan berarti mengambil tanggal lahir hampir
     * setiap kali.
     *
     * @return array{tanggal:string,sumber:string,petikan:string,pasti:bool}|null
     */
    public static function dariIsi(UploadedFile $berkas, ?Carbon $kini = null): ?array
    {
        $teks = self::teksPdf($berkas);

        if ($teks === null) return null;

        $baris = preg_split('/\R/', $teks) ?: [];

        /* Dua lintasan. Yang pertama hanya pada baris yang menyebut kata
           masa berlaku; yang kedua barulah seluruh teksnya. */
        foreach ([true, false] as $hanyaBerkata) {
            foreach ($baris as $b) {
                $adaKata = self::menyebutMasaBerlaku($b);

                if ($hanyaBerkata && !$adaKata) continue;
                if (!$hanyaBerkata && $adaKata) continue;

                foreach (self::tanggalDalam($b, $kini) as $t) {
                    return [
                        'tanggal' => $t['tanggal'],
                        'sumber'  => $adaKata ? 'teks berkas' : 'teks berkas (tanpa kata "berlaku")',
                        'petikan' => trim(mb_substr(trim($b), 0, 120)),
                        'pasti'   => $t['pasti'] && $adaKata,
                    ];
                }
            }
        }

        return null;
    }

    private static function menyebutMasaBerlaku(string $baris): bool
    {
        $kecil = mb_strtolower($baris);

        foreach (self::KATA as $k) {
            if (str_contains($kecil, $k)) return true;
        }

        return false;
    }

    /**
     * Seluruh tanggal masuk akal di dalam sepotong teks, berurutan.
     *
     * `pasti` menandai bahwa urutan hari dan bulannya tidak dapat
     * tertukar — 31-12 hanya dapat dibaca satu cara, sedangkan 01-02
     * dapat dibaca dua cara. Dokumen Indonesia menulis hari lebih dulu,
     * dan itulah yang dipakai; penandanya ada supaya layar dapat
     * meminta orang memeriksanya.
     *
     * @return list<array{tanggal:string,pasti:bool}>
     */
    private static function tanggalDalam(string $teks, ?Carbon $kini = null): array
    {
        $kini ??= Carbon::now();
        $keluar = [];

        $pola = [
            // 2028-12-31 dan 20281231
            ['/(?<!\d)(20\d{2})[-\/.]?(\d{2})[-\/.]?(\d{2})(?!\d)/', 'tbh'],
            // 31-12-2028
            ['/(?<!\d)(\d{1,2})[-\/.](\d{1,2})[-\/.](20\d{2})(?!\d)/', 'hbt'],
        ];

        foreach ($pola as [$re, $urut]) {
            if (!preg_match_all($re, $teks, $cocok, PREG_SET_ORDER)) continue;

            foreach ($cocok as $c) {
                [$th, $bl, $hr] = $urut === 'tbh'
                    ? [(int) $c[1], (int) $c[2], (int) $c[3]]
                    : [(int) $c[3], (int) $c[2], (int) $c[1]];

                if ($bl < 1 || $bl > 12 || $hr < 1 || $hr > 31) continue;
                if (!checkdate($bl, $hr, $th)) continue;

                $t = Carbon::create($th, $bl, $hr);

                if ($t->lt($kini->copy()->subYears(self::MUNDUR_TAHUN))) continue;
                if ($t->gt($kini->copy()->addYears(self::MAJU_TAHUN)))   continue;

                $keluar[] = [
                    'tanggal' => $t->toDateString(),

                    /* Tertukar hanya mungkin bila keduanya ≤ 12 dan
                       ditulis dengan hari lebih dulu. */
                    'pasti' => $urut === 'tbh' || $hr > 12,
                ];
            }
        }

        return $keluar;
    }

    /**
     * Teks di dalam PDF, bila server punya `pdftotext`.
     *
     * Ketiadaannya bukan galat: pemasangan tanpa poppler-utils tetap
     * berjalan, hanya kehilangan satu dari dua sumber. Yang tidak boleh
     * terjadi adalah unggahan yang gagal karena pembacaannya gagal.
     */
    private static function teksPdf(UploadedFile $berkas): ?string
    {
        if (mb_strtolower($berkas->getClientOriginalExtension()) !== 'pdf') return null;
        if (!self::adaPdftotext()) return null;

        $keluaran = tempnam(sys_get_temp_dir(), 'sim');

        try {
            $perintah = sprintf('pdftotext -q -l 2 %s %s',
                escapeshellarg($berkas->getRealPath()), escapeshellarg($keluaran));

            exec($perintah, $_, $kode);

            return $kode === 0 && is_readable($keluaran) ? (string) file_get_contents($keluaran) : null;
        } finally {
            if (is_file($keluaran)) @unlink($keluaran);
        }
    }

    private static function adaPdftotext(): bool
    {
        static $ada = null;

        if ($ada === null) {
            exec('command -v pdftotext', $_, $kode);
            $ada = $kode === 0;
        }

        return $ada;
    }
}
