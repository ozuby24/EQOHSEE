<?php

namespace App\Support;

/**
 * QRIS — membaca kode statis merchant, dan menurunkannya jadi dinamis.
 *
 * ── APA YANG DIKERJAKAN, DAN APA YANG TIDAK ──
 *
 * Kelas ini TIDAK MEMBUAT kode QRIS. Kode QRIS memuat NMID — nomor
 * merchant yang diterbitkan acquirer — dan tidak ada cara sah
 * mengarangnya. Yang dikerjakan di sini hanya mengolah kode yang SUDAH
 * dimiliki merchant: kode statis dari bank atau penyedia pembayaran,
 * ditempel apa adanya ke konfigurasi.
 *
 * Kalau konfigurasinya kosong, layarnya menyebut kosong. Menggambar QR
 * dari teks kosong menghasilkan gambar yang terlihat seperti QR rusak,
 * dan yang memindainya menyimpulkan ponselnya yang bermasalah.
 *
 * ── MENGAPA PERLU DIUBAH JADI DINAMIS ──
 *
 * Kode statis tidak memuat nominal; pembeli mengetiknya sendiri saat
 * memindai. Yang terjadi kemudian sudah dapat ditebak: angkanya
 * meleset, pembayarannya masuk dengan nilai yang tidak sama dengan
 * tagihannya, dan seseorang harus menelusuri selisihnya secara manual.
 *
 * Menyisipkan nominal ke dalam payload menghapus seluruh peluang itu.
 * Aturannya dari spesifikasi EMVCo:
 *
 *   tag 01  metode inisiasi — "11" statis, "12" dinamis
 *   tag 54  nominal transaksi
 *   tag 63  CRC, SELALU paling akhir, dihitung termasuk "6304"
 *
 * ── MENGAPA GAGAL DENGAN MEMULANGKAN NULL ──
 *
 * `dinamis()` memulangkan null bila kode masukannya tidak lolos
 * pemeriksaan CRC-nya sendiri, atau bila hasil olahannya tidak dapat
 * dibaca ulang. Pemanggilnya lalu menampilkan kode statis apa adanya
 * beserta nominal tertulis.
 *
 * Itu disengaja, dan arahnya penting: QR yang salah nominal LEBIH
 * BERBAHAYA daripada QR tanpa nominal. Yang tanpa nominal membuat
 * pembeli mengetik angka — merepotkan, tetapi ia melihat angkanya. Yang
 * salah nominal dibayar tanpa seorang pun membacanya lagi.
 */
final class Qris
{
    /** Metode inisiasi. */
    public const STATIS  = '11';
    public const DINAMIS = '12';

    public const TAG_METODE  = '01';
    public const TAG_NOMINAL = '54';
    public const TAG_CRC     = '63';

    /**
     * CRC16/CCITT-FALSE — polinomial 0x1021, awal 0xFFFF.
     *
     * Tanpa pembalikan bit dan tanpa XOR akhir. Ketiga varian CRC16 lain
     * menghasilkan angka yang sama-sama terlihat masuk akal, dan yang
     * salah tidak ketahuan sampai kodenya dipindai orang.
     */
    public static function crc16(string $data): string
    {
        $crc = 0xFFFF;

        foreach (str_split($data) as $ch) {
            $crc ^= ord($ch) << 8;

            for ($i = 0; $i < 8; $i++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Uraikan payload menjadi daftar TLV tingkat teratas.
     *
     * Memulangkan null bila bentuknya tidak utuh — panjang yang
     * menunjuk ke luar teks, atau tag yang terpotong. Payload cacat yang
     * diteruskan diam-diam akan menghasilkan kode yang tidak dapat
     * dipindai, dan sebabnya tidak akan terlihat dari mana pun.
     *
     * @return list<array{tag:string, nilai:string}>|null
     */
    public static function urai(string $payload): ?array
    {
        $out = [];
        $i = 0;
        $n = strlen($payload);

        while ($i < $n) {
            if ($i + 4 > $n) return null;

            $tag = substr($payload, $i, 2);
            $len = substr($payload, $i + 2, 2);

            if (! ctype_digit($tag) || ! ctype_digit($len)) return null;

            $len = (int) $len;

            if ($i + 4 + $len > $n) return null;

            $out[] = ['tag' => $tag, 'nilai' => substr($payload, $i + 4, $len)];
            $i += 4 + $len;
        }

        return $out ?: null;
    }

    /** Susun kembali daftar TLV menjadi payload, tanpa CRC. */
    public static function susun(array $tlv): string
    {
        $s = '';

        foreach ($tlv as $t) {
            $s .= $t['tag'].str_pad((string) strlen($t['nilai']), 2, '0', STR_PAD_LEFT).$t['nilai'];
        }

        return $s;
    }

    /**
     * Apakah payload lolos pemeriksaan CRC-nya sendiri.
     *
     * Ini gerbang pertama sebelum apa pun diolah. Kode yang salah salin
     * — terpotong saat ditempel ke .env, atau kemasukan spasi — gagal di
     * sini, bukan nanti di tangan pembeli.
     */
    public static function sah(string $payload): bool
    {
        $payload = trim($payload);

        if (strlen($payload) < 8) return false;

        $posisi = strrpos($payload, self::TAG_CRC.'04');

        if ($posisi === false || $posisi !== strlen($payload) - 8) return false;

        $tanpaCrc  = substr($payload, 0, $posisi + 4);
        $tertulis  = strtoupper(substr($payload, $posisi + 4));

        return self::crc16($tanpaCrc) === $tertulis;
    }

    /**
     * Turunkan kode statis menjadi kode dinamis bernominal.
     *
     * Urutan tag aslinya DIPERTAHANKAN, bukan disusun ulang menurut
     * nomornya. Menyusun ulang memang menghasilkan payload yang secara
     * spesifikasi tetap benar, tetapi ia mengubah bagian kode yang tidak
     * ada urusannya dengan nominal — dan bila ada yang meleset,
     * penyebabnya menjadi mustahil dipersempit.
     *
     * Nominal disisipkan sesudah tag mata uang (53) bila ada; kalau
     * tidak, sebelum tag negara (58); kalau tidak juga, di akhir sebelum
     * CRC.
     *
     * @param  int  $jumlah  rupiah penuh, tanpa sen
     */
    public static function dinamis(string $statis, int $jumlah): ?string
    {
        $statis = trim($statis);

        if ($jumlah <= 0 || ! self::sah($statis)) return null;

        $tanpaCrc = substr($statis, 0, -8);
        $tlv = self::urai($tanpaCrc);

        if ($tlv === null) return null;

        /* Nominal ditulis sebagai bilangan bulat. Rupiah tidak dipakai
           sampai sen di sini, dan ".00" yang tidak perlu memperpanjang
           payload tanpa menambah arti. */
        $nominal = (string) $jumlah;

        if (strlen($nominal) > 13) return null;   // batas tag 54 menurut EMVCo

        $baru = [];
        $sudahSisip = false;

        foreach ($tlv as $t) {
            /* Nominal lama dibuang, bukan dipertahankan: kode yang sudah
               bernominal lalu disisipi nominal kedua memuat dua tag 54,
               dan yang dipakai pemindai bergantung pada aplikasinya. */
            if ($t['tag'] === self::TAG_NOMINAL) continue;

            if ($t['tag'] === self::TAG_METODE) {
                $baru[] = ['tag' => self::TAG_METODE, 'nilai' => self::DINAMIS];

                continue;
            }

            /* Sebelum tag negara — hanya bila belum tersisip sesudah 53. */
            if (! $sudahSisip && $t['tag'] === '58') {
                $baru[] = ['tag' => self::TAG_NOMINAL, 'nilai' => $nominal];
                $sudahSisip = true;
            }

            $baru[] = $t;

            if (! $sudahSisip && $t['tag'] === '53') {
                $baru[] = ['tag' => self::TAG_NOMINAL, 'nilai' => $nominal];
                $sudahSisip = true;
            }
        }

        if (! $sudahSisip) $baru[] = ['tag' => self::TAG_NOMINAL, 'nilai' => $nominal];

        /* Tag 01 boleh tidak ada pada sebagian kode statis. Kalau memang
           tidak ada, ia ditambahkan — kode dinamis tanpa penanda dinamis
           dapat dipindai berulang kali oleh pembeli yang sama. */
        $adaMetode = false;
        foreach ($baru as $t) if ($t['tag'] === self::TAG_METODE) $adaMetode = true;

        if (! $adaMetode) {
            array_splice($baru, 1, 0, [['tag' => self::TAG_METODE, 'nilai' => self::DINAMIS]]);
        }

        $isi = self::susun($baru).self::TAG_CRC.'04';
        $hasil = $isi.self::crc16($isi);

        /* Dibaca ulang sebelum dipulangkan. Bila hasil olahannya sendiri
           tidak lolos, yang dipulangkan null dan pemanggilnya jatuh ke
           kode statis — bukan kode dinamis yang tidak dapat dipindai. */
        if (! self::sah($hasil) || self::nominal($hasil) !== $nominal) return null;

        return $hasil;
    }

    /** Nominal yang tertulis di dalam payload, atau null bila tanpa nominal. */
    public static function nominal(string $payload): ?string
    {
        if (! self::sah($payload)) return null;

        foreach (self::urai(substr($payload, 0, -8)) ?? [] as $t) {
            if ($t['tag'] === self::TAG_NOMINAL) return $t['nilai'];
        }

        return null;
    }

    /**
     * Nama merchant seperti tertulis di kodenya sendiri (tag 59).
     *
     * Ditampilkan di bawah QR supaya pembeli dapat mencocokkan nama yang
     * muncul di aplikasinya dengan yang tertulis di halaman. Kalau
     * keduanya berbeda, ia berhenti sebelum membayar — dan itu satu-
     * satunya pemeriksaan yang benar-benar dapat dilakukan pembeli.
     */
    public static function merchant(string $payload): ?string
    {
        if (! self::sah($payload)) return null;

        foreach (self::urai(substr($payload, 0, -8)) ?? [] as $t) {
            if ($t['tag'] === '59') return $t['nilai'] !== '' ? $t['nilai'] : null;
        }

        return null;
    }
}
