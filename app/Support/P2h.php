<?php

namespace App\Support;

/**
 * P2H — Pemeriksaan dan Pemeliharaan Harian sebelum unit bekerja.
 *
 * Daftar periksa per jenis unit, dan aturan hasilnya:
 *
 *   - satu butir KRITIS dijawab "tidak"  → unit DITAHAN, perintah kerja
 *     korektif berprioritas kritis terbit untuk mekanik;
 *   - butir non-kritis dijawab "tidak"   → unit laik, butirnya tercatat
 *     sebagai temuan perbaikan;
 *   - seluruh butir wajib dijawab (ok / tidak / N/A) sebelum dikirim.
 *
 * Butir kritis adalah butir yang, bila gagal, membuat unit tidak boleh
 * dioperasikan sama sekali: rem, kemudi, mur roda, sabuk pengaman,
 * alarm mundur, radio. Daftar ini ditulis sebagai kode, bukan tabel yang
 * dapat disunting dari layar — dan teks tiap butir disalin ke catatan
 * pemeriksaannya, jadi mengubah daftar ini kelak tidak mengubah arti
 * P2H yang sudah terkirim.
 */
final class P2h
{
    public const OK    = 'ok';
    public const TIDAK = 'tidak';
    public const NA    = 'na';
    public const NILAI = [self::OK, self::TIDAK, self::NA];

    public const LAIK    = 'laik';
    public const DITAHAN = 'ditahan';

    public const SHIFT = ['pagi' => 'Shift pagi', 'malam' => 'Shift malam'];

    public const JENIS = [
        'dump_truck'    => 'Dump truck',
        'excavator'     => 'Excavator',
        'dozer'         => 'Bulldozer',
        'grader'        => 'Motor grader',
        'light_vehicle' => 'Light vehicle',
        'lainnya'       => 'Unit lainnya',
    ];

    /**
     * Daftar periksa per jenis: kelompok → [kode, uraian, kritis].
     *
     * @return array<string, list<array{0:string,1:string,2:bool}>>
     */
    public static function daftar(string $jenis): array
    {
        $kabin = [
            ['sabuk', 'Sabuk pengaman berfungsi', true],
            ['radio', 'Radio komunikasi berfungsi', true],
            ['kaca', 'Kaca dan spion bersih, tidak retak', false],
        ];
        $alarm = [
            ['lampu', 'Lampu kerja dan rotary lamp', false],
            ['alarm_mundur', 'Alarm mundur berbunyi', true],
            ['klakson', 'Klakson berfungsi', false],
            ['apar', 'APAR terisi, segel utuh', false],
        ];
        $cairan = [
            ['oli_mesin', 'Level oli mesin cukup', false],
            ['coolant', 'Level air radiator cukup', false],
            ['bocor', 'Tidak ada kebocoran oli, hidrolik, atau bahan bakar', false],
        ];

        return match ($jenis) {
            'dump_truck' => [
                'REM & KEMUDI' => [
                    ['rem_servis', 'Rem servis berfungsi normal', true],
                    ['rem_parkir', 'Rem parkir menahan di tanjakan', true],
                    ['retarder', 'Retarder tanpa bunyi abnormal', true],
                    ['kemudi', 'Kemudi responsif, tanpa kelonggaran', true],
                ],
                'BAN & RODA' => [
                    ['ban_tekanan', 'Tekanan dan kondisi keenam ban', false],
                    ['mur_roda', 'Mur roda lengkap, tanpa retak', true],
                    ['ban_sobek', 'Tidak ada sobekan dinding ban', true],
                ],
                'CAIRAN & KEBOCORAN' => $cairan,
                'LAMPU, ALARM & APAR' => $alarm,
                'KABIN' => $kabin,
            ],
            'excavator' => [
                'HIDROLIK & ATTACHMENT' => [
                    ['hidrolik', 'Sistem hidrolik tanpa kebocoran', true],
                    ['pin_bucket', 'Pin dan bushing bucket lengkap', true],
                    ['kuku', 'Kuku dan adaptor bucket utuh', false],
                ],
                'REM & GERAK' => [
                    ['rem_swing', 'Rem swing dan kunci swing berfungsi', true],
                    ['travel', 'Travel dan rem travel berfungsi', true],
                    ['track', 'Track dan undercarriage dalam kondisi baik', false],
                ],
                'CAIRAN & KEBOCORAN' => [$cairan[0], $cairan[1]],
                'LAMPU, ALARM & APAR' => [$alarm[0], ['alarm_travel', 'Alarm travel berbunyi', true], $alarm[3]],
                'KABIN' => [...$kabin, ['tangga', 'Tangga dan pegangan kokoh', false]],
            ],
            'light_vehicle' => [
                'REM & KEMUDI' => [
                    ['rem', 'Rem berfungsi normal', true],
                    ['rem_tangan', 'Rem tangan menahan', true],
                    ['kemudi', 'Kemudi tanpa kelonggaran', true],
                ],
                'BAN & RODA' => [
                    ['ban', 'Kondisi dan tekanan ban, termasuk cadangan', false],
                    ['mur_roda', 'Mur roda lengkap', true],
                ],
                'PERLENGKAPAN TAMBANG' => [
                    ['buggy_whip', 'Bendera buggy whip terpasang', true],
                    ['rotary', 'Rotary lamp menyala', true],
                    ['lampu', 'Lampu utama, sein, dan rem', false],
                    ['klakson', 'Klakson berfungsi', false],
                    ['apar', 'APAR terisi, segel utuh', false],
                    ['p3k', 'Kotak P3K lengkap', false],
                ],
                'CAIRAN' => [$cairan[0], $cairan[1]],
                'KABIN' => $kabin,
            ],
            'dozer', 'grader' => [
                'REM & KEMUDI' => [
                    ['rem', 'Rem dan kunci parkir berfungsi', true],
                    ['kemudi', 'Kemudi atau tuas arah responsif', true],
                ],
                'ALAT KERJA' => [
                    ['hidrolik', 'Sistem hidrolik tanpa kebocoran', true],
                    ['blade', 'Blade dan cutting edge utuh', false],
                    ['track', $jenis === 'dozer' ? 'Track dan undercarriage dalam kondisi baik' : 'Ban dan mur roda lengkap', $jenis !== 'dozer'],
                ],
                'CAIRAN' => [$cairan[0], $cairan[1]],
                'LAMPU, ALARM & APAR' => [$alarm[0], $alarm[1], $alarm[3]],
                'KABIN' => $kabin,
            ],
            default => [
                'KESELAMATAN DASAR' => [
                    ['rem', 'Rem atau pengaman gerak berfungsi', true],
                    ['pengaman', 'Pelindung bagian bergerak terpasang', true],
                    ['bocor', 'Tidak ada kebocoran', false],
                ],
                'LAMPU, ALARM & APAR' => [$alarm[0], $alarm[2], $alarm[3]],
                'OPERATOR' => [$kabin[0], $kabin[1]],
            ],
        };
    }

    /** Daftar datar untuk layar: [kode, kelompok, uraian, kritis]. */
    public static function butir(string $jenis): array
    {
        $out = [];
        foreach (self::daftar($jenis) as $kelompok => $isi) {
            foreach ($isi as [$kode, $uraian, $kritis]) {
                $out[] = ['kode' => $kode, 'kelompok' => $kelompok, 'uraian' => $uraian, 'kritis' => $kritis];
            }
        }
        return $out;
    }

    /**
     * Nilai jawaban terhadap daftar periksanya.
     *
     * Kode yang tidak ada di daftar dibuang; butir yang tidak dijawab
     * membuat hasilnya null (belum lengkap). Teks butir diambil dari
     * DAFTAR, bukan dari kiriman — layar tidak dapat mengganti uraian
     * atau menurunkan butir kritis menjadi biasa.
     *
     * @param  array<string, string>  $jawab  kode => ok|tidak|na
     * @return array{jawaban: list<array>, ok:int, tidak:int, na:int, hasil:?string, kritisGagal: list<string>}
     */
    public static function nilai(string $jenis, array $jawab): array
    {
        $jawaban = [];
        $n = [self::OK => 0, self::TIDAK => 0, self::NA => 0];
        $kritisGagal = [];
        $lengkap = true;

        foreach (self::butir($jenis) as $b) {
            $v = $jawab[$b['kode']] ?? null;
            if (!in_array($v, self::NILAI, true)) { $lengkap = false; $v = null; }
            else $n[$v]++;

            if ($v === self::TIDAK && $b['kritis']) $kritisGagal[] = $b['uraian'];

            $jawaban[] = $b + ['nilai' => $v];
        }

        return [
            'jawaban'     => $jawaban,
            'ok'          => $n[self::OK],
            'tidak'       => $n[self::TIDAK],
            'na'          => $n[self::NA],
            'hasil'       => $lengkap ? ($kritisGagal ? self::DITAHAN : self::LAIK) : null,
            'kritisGagal' => $kritisGagal,
        ];
    }

    /** Shift menurut jam: 06.00–17.59 pagi, sisanya malam. */
    public static function shiftSekarang(?\DateTimeInterface $t = null): string
    {
        $jam = (int) ($t ?? now())->format('G');

        return $jam >= 6 && $jam < 18 ? 'pagi' : 'malam';
    }
}
