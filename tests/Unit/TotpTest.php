<?php

namespace Tests\Unit;

use App\Support\Totp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Mesin TOTP, dicocokkan ke tabel resmi RFC 6238.
 *
 * Ini berkas uji yang paling penting di antara semua yang menyangkut
 * dua faktor, karena ia satu-satunya yang dapat membuktikan mesinnya
 * BENAR — bukan sekadar taat asas dengan dirinya sendiri.
 *
 * Kode yang salah hitung tetap lolos uji yang membandingkannya dengan
 * hasil fungsi yang sama: ia menghasilkan enam angka, menerima enam
 * angka itu kembali, dan seluruh alur masuknya bekerja mulus di layar.
 * Yang tidak bekerja hanya satu hal — Google Authenticator di ponsel
 * penggunanya, yang menghitung menurut RFC dan karena itu memberi angka
 * yang berbeda. Gejalanya: semua orang yang memasang dua faktor
 * langsung terkunci di luar, dan tidak ada satu pun uji yang gagal.
 *
 * Tabel di bawah disalin dari RFC 6238 Appendix B (varian SHA-1).
 */
class TotpTest extends TestCase
{
    /** Rahasia uji RFC 6238: ASCII "12345678901234567890" dalam base32. */
    private const RAHASIA = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    /**
     * @return array<string, array{0: int, 1: string}>
     */
    public static function tabelRfc(): array
    {
        /* RFC menerbitkan kode delapan angka; yang dipakai di sini enam,
           jadi yang dicocokkan enam angka TERAKHIR-nya — persis yang
           dihitung aplikasi autentikator mana pun. */
        return [
            '1970-01-01 00:00:59' => [59, '287082'],          // 94287082
            '2005-03-18 01:58:29' => [1111111109, '081804'],  // 07081804
            '2005-03-18 01:58:31' => [1111111111, '050471'],  // 14050471
            '2009-02-13 23:31:30' => [1234567890, '005924'],  // 89005924
            '2033-05-18 03:33:20' => [2000000000, '279037'],  // 69279037
            '2603-10-11 11:33:20' => [20000000000, '353130'], // 65353130
        ];
    }

    #[DataProvider('tabelRfc')]
    public function test_cocok_dengan_tabel_rfc_6238(int $waktu, string $harapan): void
    {
        $this->assertSame($harapan, Totp::kode(self::RAHASIA, $waktu),
            "Kode pada detik {$waktu} tidak sama dengan yang diterbitkan RFC 6238. "
            .'Mesin ini akan berbeda dengan setiap aplikasi autentikator di dunia.');
    }

    /* ═══════════ rahasianya ═══════════ */

    public function test_rahasia_baru_berbentuk_base32_dan_tidak_berulang(): void
    {
        $a = Totp::rahasiaBaru();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $a,
            'Rahasia di luar abjad base32 tidak dapat dipindai aplikasi autentikator.');

        $kumpulan = [];
        for ($i = 0; $i < 50; $i++) $kumpulan[] = Totp::rahasiaBaru();

        $this->assertCount(50, array_unique($kumpulan),
            'Rahasia berulang; sumber acaknya tidak benar-benar acak.');
    }

    /* ═══════════ toleransi jam ═══════════ */

    /**
     * Jam yang meleset satu langkah masih diterima.
     *
     * Tanpa toleransi, kode yang diketik tepat saat pergantian langkah
     * ditolak walau benar — acak, kira-kira satu dari sepuluh percobaan,
     * dan mustahil ditiru oleh orang yang dimintai tolong.
     */
    public function test_jam_meleset_satu_langkah_masih_diterima(): void
    {
        $waktu = 1_700_000_000;

        foreach ([-Totp::LANGKAH, 0, Totp::LANGKAH] as $geser) {
            $kode = Totp::kode(self::RAHASIA, $waktu + $geser);

            $this->assertNotNull(Totp::cocok(self::RAHASIA, $kode, null, $waktu),
                "Kode dari geseran {$geser} detik ditolak.");
        }
    }

    public function test_jam_meleset_dua_langkah_ditolak(): void
    {
        $waktu = 1_700_000_000;

        foreach ([-2 * Totp::LANGKAH, 2 * Totp::LANGKAH] as $geser) {
            $kode = Totp::kode(self::RAHASIA, $waktu + $geser);

            $this->assertNull(Totp::cocok(self::RAHASIA, $kode, null, $waktu),
                "Kode dari geseran {$geser} detik diterima; jendela tebakannya terlalu lebar.");
        }
    }

    /* ═══════════ sekali pakai ═══════════ */

    /**
     * Kode yang sudah terpakai tidak berlaku lagi.
     *
     * Satu kode hidup sampai satu setengah menit dengan toleransi ini.
     * Tanpa catatan langkah terakhir, kode yang terbaca dari balik bahu
     * — atau dari layar yang terlanjur dibagikan saat rapat daring —
     * dapat dipakai lagi selama sisa umurnya.
     */
    public function test_kode_yang_sudah_terpakai_ditolak(): void
    {
        $waktu = 1_700_000_000;
        $kode  = Totp::kode(self::RAHASIA, $waktu);

        $langkah = Totp::cocok(self::RAHASIA, $kode, null, $waktu);
        $this->assertNotNull($langkah);

        $this->assertNull(Totp::cocok(self::RAHASIA, $kode, $langkah, $waktu),
            'Kode yang sudah ditukar masih diterima untuk kedua kalinya.');
    }

    public function test_langkah_sesudahnya_tetap_diterima(): void
    {
        $waktu   = 1_700_000_000;
        $langkah = intdiv($waktu, Totp::LANGKAH);

        $berikut = Totp::kode(self::RAHASIA, $waktu + Totp::LANGKAH);

        $this->assertSame($langkah + 1,
            Totp::cocok(self::RAHASIA, $berikut, $langkah, $waktu),
            'Kode berikutnya ikut tertolak oleh catatan langkah terakhir.');
    }

    /* ═══════════ bentuk yang diketik orang ═══════════ */

    public function test_spasi_dan_tanda_hubung_diabaikan(): void
    {
        $waktu = 1_700_000_000;
        $kode  = Totp::kode(self::RAHASIA, $waktu);

        $berspasi = substr($kode, 0, 3).' '.substr($kode, 3);

        $this->assertNotNull(Totp::cocok(self::RAHASIA, $berspasi, null, $waktu),
            'Kode yang disalin apa adanya dari layar ("123 456") ditolak.');
    }

    public function test_kode_yang_panjangnya_salah_ditolak(): void
    {
        foreach (['', '12345', '1234567', 'abcdef'] as $salah) {
            $this->assertNull(Totp::cocok(self::RAHASIA, $salah, null, 1_700_000_000));
        }
    }

    public function test_rahasia_huruf_kecil_dan_berpadding_tetap_terbaca(): void
    {
        $waktu = 1_700_000_000;
        $benar = Totp::kode(self::RAHASIA, $waktu);

        $this->assertSame($benar, Totp::kode(strtolower(self::RAHASIA), $waktu));
        $this->assertSame($benar, Totp::kode(self::RAHASIA.'======', $waktu));
    }

    /* ═══════════ alamat QR ═══════════ */

    public function test_uri_memuat_yang_dibutuhkan_aplikasi(): void
    {
        $uri = Totp::uri(self::RAHASIA, 'budi@contoh.test', 'EQOHSEE');

        $this->assertStringStartsWith('otpauth://totp/EQOHSEE:budi%40contoh.test?', $uri);

        parse_str(parse_url($uri, PHP_URL_QUERY) ?: '', $bagian);

        $this->assertSame(self::RAHASIA, $bagian['secret']);
        $this->assertSame('EQOHSEE', $bagian['issuer'], 'issuer hilang; sebagian aplikasi menampilkannya tanpa nama.');
        $this->assertSame('SHA1', $bagian['algorithm']);
        $this->assertSame((string) Totp::ANGKA, $bagian['digits']);
        $this->assertSame((string) Totp::LANGKAH, $bagian['period']);
    }
}
