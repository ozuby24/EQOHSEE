<?php

namespace App\Support\Investigasi;

use App\Models\Investigasi\WawancaraPertanyaan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Panduan wawancara yang menyesuaikan diri pada peran narasumber.
 *
 * ── MENGAPA PERTANYAANNYA TIDAK SAMA UNTUK SEMUA ORANG ──
 *
 * Satu daftar pertanyaan untuk semua narasumber terlihat adil dan
 * sebenarnya merusak dua hal sekaligus.
 *
 * Pertama, ia membuang waktu: menanyai saksi tidak langsung tentang
 * rincian yang tidak dilihatnya menghasilkan jawaban "kurang tahu"
 * berulang kali, dan berita acara yang isinya "kurang tahu" tidak
 * dipakai siapa pun.
 *
 * Kedua — dan ini yang lebih serius — ia MENGGESER TANGGUNG JAWAB.
 * Menanyai korban mengapa perusahaan tidak mengganti alat yang sudah
 * aus adalah pertanyaan yang tidak dapat dijawabnya, dan satu-satunya
 * jawaban yang mungkin ia berikan terdengar seperti pengakuan. Karena
 * itu pertanyaan bertingkat Eliminasi, Substitusi, dan Rekayasa hanya
 * diajukan kepada pengawas dan manajemen; korban dan saksi langsung
 * ditanya sampai tingkat APD saja.
 *
 * Aturannya bukan sopan santun melainkan hierarki pengendalian: yang
 * ditanya adalah yang punya kewenangan mengubah tingkat itu.
 */
final class PanduanWawancara
{
    public const BERKAS = 'database/data/scat-wawancara.json';

    /**
     * Peran narasumber, dan tingkat hierarki yang boleh ditanyakan.
     *
     * Angkanya mengikuti inv_hierarki_kendali: 1 eliminasi … 5 APD.
     * Saksi tidak langsung tidak ditanyai soal pengendalian sama sekali
     * — ia tidak melihat kejadiannya, dan pendapatnya tentang mengapa
     * pengendaliannya gagal adalah dugaan yang akan tercatat sebagai
     * keterangan.
     */
    public const TINGKAT_PER_PERAN = [
        'korban'               => [5],
        'saksi_langsung'       => [5],
        'saksi_tidak_langsung' => [],
        'pengawas'             => [1, 2, 3, 4, 5],
        'manajemen'            => [1, 2, 3, 4, 5],
    ];

    /** Nama peran di berkas JSON → kunci yang dipakai basis data. */
    private const PETA_PERAN = [
        'korban'             => 'korban',
        'saksiLangsung'      => 'saksi_langsung',
        'saksiTidakLangsung' => 'saksi_tidak_langsung',
        'pengawas'           => 'pengawas',
        'manajemen'          => 'manajemen',
    ];

    private static ?array $isi = null;

    /** @return array<string,mixed> */
    public static function berkas(): array
    {
        if (self::$isi !== null) return self::$isi;

        $jalur = base_path(self::BERKAS);

        if (! is_file($jalur)) {
            throw new RuntimeException('Panduan wawancara tidak ditemukan di '.self::BERKAS);
        }

        $isi = json_decode((string) file_get_contents($jalur), true);

        if (! is_array($isi)) {
            throw new RuntimeException('Panduan wawancara tidak terbaca sebagai JSON yang benar.');
        }

        return self::$isi = $isi;
    }

    /**
     * Pasang bank soal. Aman dijalankan berulang.
     *
     * Kodenya dibentuk dari peran dan nomor urut, bukan dari isi
     * pertanyaannya: memperbaiki ejaan sebuah pertanyaan tidak boleh
     * melahirkan baris baru dan meninggalkan yang lama sebagai kembaran
     * yang tidak pernah dipakai.
     *
     * @return int jumlah pertanyaan sesudah pemasangan
     */
    public static function pasang(): int
    {
        $isi = self::berkas();
        $n   = 0;

        /* Pertanyaan dasar: peran → daftar kalimat. */
        foreach ($isi['pertanyaan_dasar_per_role'] ?? [] as $peranJson => $daftar) {
            $peran = self::PETA_PERAN[$peranJson] ?? $peranJson;
            $i = 1;

            foreach ($daftar as $bunyi) {
                self::simpan(
                    kode: sprintf('D-%s-%02d', $peran, $i),
                    bunyi: (string) $bunyi,
                    peran: $peran,
                    tingkat: null,
                    kataKunci: null,
                    urutan: $n + 1,
                );
                $i++; $n++;
            }
        }

        /* Pertanyaan SCAT: peran → daftar kelompok, tiap kelompok punya
           kategori dan beberapa kalimat. Bentuknya SATU TINGKAT LEBIH
           DALAM daripada pertanyaan dasar, dan menyamakan keduanya
           membuat seluruh kelompok tersimpan sebagai satu baris berisi
           larik — tersimpan tanpa galat, terbaca sebagai pertanyaan
           kosong di layar. */
        foreach ($isi['pertanyaan_scat_per_role'] ?? [] as $peranJson => $kelompok) {
            $peran = self::PETA_PERAN[$peranJson] ?? $peranJson;
            $i = 1;

            foreach ($kelompok as $grup) {
                foreach ($grup['pertanyaan'] ?? [] as $bunyi) {
                    self::simpan(
                        kode: sprintf('S-%s-%02d', $peran, $i),
                        bunyi: (string) $bunyi,
                        peran: $peran,
                        tingkat: null,
                        kataKunci: null,
                        urutan: 200 + $i,
                    );
                    $i++; $n++;
                }
            }
        }

        /* Pertanyaan hierarki pengendalian — bunyinya sama, tetapi yang
           menerimanya berbeda menurut TINGKAT_PER_PERAN. Disimpan satu
           baris per pasangan peran×tingkat supaya penyaringannya
           dikerjakan basis data, bukan percabangan di layar. */
        foreach (['hierarki_pengendalian_lengkap', 'hierarki_pengendalian_apd'] as $kunci) {
            foreach ($isi[$kunci] ?? [] as $butir) {
                $tingkat = self::tingkatDari($butir);

                foreach (self::TINGKAT_PER_PERAN as $peran => $boleh) {
                    if (! in_array($tingkat, $boleh, true)) continue;

                    self::simpan(
                        kode: sprintf('H-%s-%d', $peran, $tingkat),
                        bunyi: (string) (is_array($butir) ? ($butir['pertanyaan'] ?? '') : $butir),
                        peran: $peran,
                        tingkat: $tingkat,
                        kataKunci: null,
                        urutan: 500 + $tingkat,
                    );
                    $n++;
                }
            }
        }

        /* Pertanyaan kontekstual — muncul hanya bila kronologi memuat
           kata kuncinya. Yang disimpan kata kuncinya, bukan hasil
           pencocokannya: kronologi berubah tiap kali disunting. */
        foreach ($isi['pertanyaan_kontekstual'] ?? [] as $i => $butir) {
            foreach ($butir['pertanyaan'] ?? [] as $peranJson => $bunyi) {
                $peran = self::PETA_PERAN[$peranJson] ?? $peranJson;

                self::simpan(
                    kode: sprintf('K-%s-%02d', $peran, $i + 1),
                    bunyi: $bunyi,
                    peran: $peran,
                    tingkat: null,
                    kataKunci: implode('|', $butir['kataKunci'] ?? []),
                    urutan: 900 + $i,
                );
                $n++;
            }
        }

        return WawancaraPertanyaan::count();
    }

    /**
     * Tingkat hierarki sebuah butir panduan.
     *
     * Butirnya boleh berupa string atau larik; yang pertama berarti
     * tingkatnya harus disimpulkan dari kata pembukanya. Menyimpulkannya
     * dari kata memang rapuh — tetapi yang rapuh di sini hanya urutan
     * tampil, bukan siapa yang boleh ditanya, dan itu sudah ditentukan
     * TINGKAT_PER_PERAN.
     */
    private static function tingkatDari(mixed $butir): int
    {
        /* Dibaca dari medan `level` — nama tingkatnya — bukan ditebak
           dari bunyi pertanyaannya. Menebaknya dari bunyi pertanyaan
           terlihat berhasil pada butir Eliminasi, yang memang memuat
           kata "dihilangkan", lalu meleset pada butir Rekayasa yang
           bunyinya menyebut "SOP" dan jatuh ke Administrasi. */
        $nama = strtolower((string) (is_array($butir) ? ($butir['level'] ?? '') : $butir));

        foreach ([1 => 'eliminasi', 2 => 'substitusi', 3 => 'rekayasa', 4 => 'administra', 5 => 'apd'] as $t => $kata) {
            if (str_contains($nama, $kata)) return $t;
        }

        /* Tingkat yang tidak dikenali diperlakukan sebagai APD — lapis
           paling bawah. Menebaknya sebagai Eliminasi akan membuat
           pertanyaan itu diajukan kepada korban, dan itu justru
           kesalahan yang hendak dicegah seluruh berkas ini. */
        return 5;
    }

    private static function simpan(string $kode, string $bunyi, string $peran, ?int $tingkat, ?string $kataKunci, int $urutan): void
    {
        if (trim($bunyi) === '') return;

        DB::table('inv_wawancara_pertanyaan')->updateOrInsert(
            ['kode' => $kode],
            [
                'pertanyaan'       => $bunyi,
                'peran'            => $peran,
                'tingkat_hierarki' => $tingkat,
                'kata_kunci'       => $kataKunci,
                'urutan'           => $urutan,
                'updated_at'       => now(),
                'created_at'       => now(),
            ],
        );
    }

    /**
     * Pertanyaan yang pantas diajukan kepada satu peran.
     *
     * `$kronologi` dipakai menyalakan pertanyaan kontekstual: yang
     * kata kuncinya tidak muncul di kronologi TIDAK ikut ditampilkan.
     * Menampilkan semuanya berarti daftar empat puluh pertanyaan yang
     * dibaca sekali lalu tidak pernah lagi.
     *
     * @return list<array<string,mixed>>
     */
    public static function untuk(string $peran, string $kronologi = ''): array
    {
        $teks = mb_strtolower($kronologi);

        return WawancaraPertanyaan::where('peran', $peran)
            ->orderBy('urutan')->get()
            ->filter(function (WawancaraPertanyaan $p) use ($teks) {
                if (blank($p->kata_kunci)) return true;

                foreach (explode('|', $p->kata_kunci) as $kata) {
                    if ($kata !== '' && str_contains($teks, mb_strtolower($kata))) return true;
                }

                return false;
            })
            ->map(fn (WawancaraPertanyaan $p) => [
                'id'         => $p->id,
                'kode'       => $p->kode,
                'pertanyaan' => $p->pertanyaan,
                'tingkat'    => $p->tingkat_hierarki,
                'kontekstual' => filled($p->kata_kunci),
            ])->values()->all();
    }
}
