<?php

namespace App\Support;

/**
 * Rubrik penilaian butir kriteria SMKP.
 *
 * Auditor mengisi angka 0..maks pada tiap butir. Tanpa rubrik, angka itu
 * ditafsirkan sendiri oleh masing-masing auditor — dan dua auditor yang
 * memeriksa bukti yang sama lalu memberi 2 dan 4 menghasilkan tingkat
 * penerapan yang berbeda untuk perusahaan yang sama. Rubrik yang terbaca
 * di sebelah angkanya adalah yang membuat penilaian dapat diulang, dan
 * karena itu dapat dipertanggungjawabkan ke inspektur tambang.
 *
 * Sumber: Lampiran II Kepdirjen 185.K/37.04/DJB/2019, seluruh 100 butir.
 * Halaman rubrik disimpan terpisah dari halaman kriteria di elemen.json —
 * keduanya halaman berbeda pada lampiran yang sama, dan auditor memakai
 * keduanya.
 *
 * DUA HAL YANG SENGAJA TIDAK DIRAPIKAN:
 *
 * 1. Nama tingkat (Tidak Ada, Kurang, Cukup, Baik, Sangat Baik) dipetakan
 *    dari nilai MUTLAK, bukan dari persentase capaian. Akibatnya butir
 *    bernilai maksimum 2 mencapai capaian penuh pada tingkat bernama
 *    "Cukup". Itu memang bentuk skalanya pada lampiran; yang menentukan
 *    kategori temuan bukan namanya melainkan capaiannya, dan kategori itu
 *    ditampilkan berdampingan supaya tidak ada yang salah baca.
 *
 * 2. Enam butir bernilai maksimum 4 hanya punya bunyi rubrik sampai nilai
 *    2 atau 3 pada lampiran. Selisihnya tidak ditambal karangan dan nilai
 *    maksimumnya tidak diturunkan — pembaginya tetap mengikuti tabel
 *    kriteria. Tingkat yang tak berbunyi ditandai apa adanya, sehingga
 *    auditor yang memberi nilai di sana tahu ia melakukannya tanpa acuan.
 */
final class SmkpRubrik
{
    /** Butir yang bunyi rubriknya tidak lengkap sampai nilai maksimumnya. */
    public const TANGGA_TAK_LENGKAP = ['III.2.1', 'III.2.2', 'III.2.3', 'III.12.2', 'III.12.4', 'IV.5.1'];

    private static ?array $ref = null;

    public static function ref(): array
    {
        return self::$ref ??= json_decode(
            file_get_contents(resource_path('data/smkp/rubrik.json')), true
        ) ?: [];
    }

    public static function sumber(): string
    {
        return (string) (self::ref()['meta']['sumber'] ?? '');
    }

    /** Nama tingkat menurut nilai mutlak, sesuai skala pada lampiran. */
    public static function skala(): array
    {
        return (array) (self::ref()['skala'] ?? []);
    }

    /** Bunyi rubrik sebuah butir: kunci nilai → paragraf. */
    public static function butir(?string $kode): array
    {
        return $kode === null ? [] : (array) (self::ref()['butir'][$kode]['skala'] ?? []);
    }

    /** Halaman rubrik butir pada lampiran — berbeda dari halaman kriterianya. */
    public static function halaman(?string $kode): ?string
    {
        return $kode === null ? null : (self::ref()['butir'][$kode]['ref'] ?? null);
    }

    public static function ada(?string $kode): bool
    {
        return self::butir($kode) !== [];
    }

    /** Berapa butir yang bunyi rubriknya lengkap sampai nilai maksimumnya. */
    public static function jumlahLengkap(): int
    {
        $n = 0;

        foreach (Smkp::butir() as $b) {
            if (count(self::butir($b['kode'])) === (int) $b['maks'] + 1) $n++;
        }

        return $n;
    }

    /**
     * Tangga nilai sebuah butir, dari yang tertinggi ke terendah.
     *
     * Urutan menurun mengikuti cara membacanya, bukan selera: yang dicari
     * auditor adalah nilai TERTINGGI yang buktinya terpenuhi, jadi ia
     * membaca dari atas lalu turun sampai menemukan yang cocok.
     *
     * Tiap anak tangga membawa kategori temuannya sekaligus — akibat sebuah
     * angka terhadap kategori adalah bagian dari keputusan memilih angka
     * itu, dan menyembunyikannya sampai setelah nilai tersimpan membuat
     * auditor menilai tanpa tahu apa yang sedang ia nyatakan.
     *
     * $kode null menghasilkan tangga tanpa bunyi rubrik — dipakai bila
     * butirnya memang belum tercantum pada berkas rubrik.
     *
     * @return list<array{nilai:int,persen:float,label:string,ket:string,ada:bool,kategori:array}>
     */
    public static function tangga(?string $kode, int $maks): array
    {
        $bunyi = self::butir($kode);
        $skala = self::skala();
        $out   = [];

        for ($n = $maks; $n >= 0; $n--) {
            $persen = $maks > 0 ? $n / $maks * 100 : 0.0;
            $teks   = $bunyi[(string) $n] ?? null;

            $out[] = [
                'nilai'    => $n,
                'persen'   => round($persen, 1),
                'label'    => (string) ($skala[$n] ?? ''),
                'ket'      => (string) ($teks ?? ''),
                'ada'      => $teks !== null && trim((string) $teks) !== '',
                'kategori' => Smkp::kategoriDari($persen / 100),
            ];
        }

        return $out;
    }
}
