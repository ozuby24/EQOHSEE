<?php

namespace App\Support;

/**
 * Standar ISO dan struktur klausulnya.
 *
 * Yang dimuat di sini hanya nomor dan judul klausul sebagai indeks
 * penelusuran — teks persyaratannya berhak cipta dan tetap harus dipegang
 * organisasi dalam salinan resminya. Gunanya: memetakan dokumen terkendali
 * ke klausul yang dipenuhinya, sehingga celah pemenuhan terlihat sebelum
 * auditor eksternal yang menemukannya.
 *
 * Tiap standar terhubung ke salah satu dari delapan aspek EQOHSEE, agar
 * warna dan penempatannya tidak ditetapkan dua kali.
 */
final class Iso
{
    private static ?array $ref = null;

    private static function ref(): array
    {
        if (self::$ref === null) {
            $berkas = resource_path('data/iso/standar.json');
            self::$ref = is_file($berkas)
                ? (json_decode(file_get_contents($berkas), true) ?: [])
                : [];
        }
        return self::$ref;
    }

    /** @return array<string,array<string,mixed>> berkunci kode standar */
    public static function semua(): array
    {
        $out = [];
        foreach (self::ref()['standar'] ?? [] as $s) {
            $out[$s['kode']] = $s;
        }
        return $out;
    }

    public static function get(string $kode): ?array
    {
        return self::semua()[$kode] ?? null;
    }

    public static function catatan(): string
    {
        return (string) (self::ref()['catatan'] ?? '');
    }

    /**
     * Kode standar yang sah — dipakai validasi agar isian asing ditolak.
     *
     * Dikembalikan sebagai teks: kode ISO seluruhnya berupa angka, dan PHP
     * mengubah kunci array numerik menjadi integer. Tanpa pemaksaan ini,
     * perbandingan ketat terhadap masukan formulir (yang selalu teks)
     * selalu gagal.
     */
    public static function kodeSah(): array
    {
        return array_map('strval', array_keys(self::semua()));
    }

    /**
     * Klausul sebuah standar, hanya yang berupa butir persyaratan.
     *
     * Nomor tanpa titik ("4", "5") adalah judul bab, bukan butir yang dapat
     * dipenuhi sebuah dokumen. Memasukkannya ke hitungan cakupan akan
     * membuat celah tampak lebih besar daripada yang sebenarnya.
     */
    public static function butir(string $kode): array
    {
        return array_values(array_filter(
            self::get($kode)['klausul'] ?? [],
            fn ($k) => str_contains($k['no'], '.')
        ));
    }

    /** Nomor bab tempat sebuah klausul bernaung: "8.1.2" → "8". */
    public static function bab(string $no): string
    {
        return explode('.', $no)[0];
    }

    /** Judul bab dari daftar klausul standar. */
    public static function judulBab(string $kode, string $bab): string
    {
        foreach (self::get($kode)['klausul'] ?? [] as $k) {
            if ($k['no'] === $bab) return $k['judul'];
        }
        return 'Bab '.$bab;
    }

    /** Warna standar, diambil dari aspek yang ditopangnya. */
    public static function warna(string $kode): string
    {
        $pilar = self::get($kode)['pilar'] ?? null;
        return $pilar ? Pillars::color($pilar) : '#0F766E';
    }

    /**
     * Cakupan sebuah standar terhadap dokumen yang sudah dipetakan.
     *
     * @param array<string,int> $jumlahPerKlausul nomor klausul => banyak dokumen
     * @return array{butir:int,tercakup:int,celah:array<int,array<string,string>>,rasio:float}
     */
    public static function cakupan(string $kode, array $jumlahPerKlausul): array
    {
        $butir    = self::butir($kode);
        $celah    = [];
        $tercakup = 0;

        foreach ($butir as $k) {
            if (($jumlahPerKlausul[$k['no']] ?? 0) > 0) $tercakup++;
            else $celah[] = $k;
        }

        return [
            'butir'    => count($butir),
            'tercakup' => $tercakup,
            'celah'    => $celah,
            // Pembagian yang habis menghasilkan int di PHP; dipaksa float
            // agar pemanggilnya tidak perlu menebak jenis nilainya.
            'rasio'    => count($butir) ? (float) ($tercakup / count($butir)) : 0.0,
        ];
    }

    /**
     * Klausul yang dikelompokkan per bab, siap ditampilkan berurutan.
     *
     * @return array<string,array{judul:string,klausul:array}>
     */
    public static function perBab(string $kode): array
    {
        $out = [];
        foreach (self::butir($kode) as $k) {
            $bab = self::bab($k['no']);
            $out[$bab] ??= ['judul' => self::judulBab($kode, $bab), 'klausul' => []];
            $out[$bab]['klausul'][] = $k;
        }
        return $out;
    }
}
