<?php

namespace App\Support\Miners;

use App\Support\Waktu;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Baca masa berlaku SIM/SIMPOL dari berkasnya — SimExpiryDetector Safe Track.
 *
 * Urutannya: teks PDF (smalot/pdfparser, lalu pembacaan aliran mentah
 * bila pustakanya gagal), lalu OCR `tesseract` HANYA bila binernya ada di
 * server. Tanpa keduanya hasilnya "tidak terbaca" dan pengguna mengisi
 * tangan — deteksi ini USULAN, tidak pernah menimpa yang diketik orang.
 *
 * Tanggal di samping label ("berlaku s/d", "masa berlaku", "valid until",
 * "exp") = keyakinan tinggi; tanpa label diambil tanggal masa depan
 * terjauh = keyakinan sedang. Tanggal di luar −5..+12 tahun dibuang.
 */
final class DeteksiMasaSim
{
    public const EKSTENSI = ['pdf', 'jpg', 'jpeg', 'png', 'tif', 'tiff', 'bmp'];

    public const MAKS_TAHUN_KE_DEPAN = 12;

    private const BULAN = [
        'januari' => 1, 'jan' => 1, 'februari' => 2, 'pebruari' => 2, 'feb' => 2, 'peb' => 2,
        'maret' => 3, 'mar' => 3, 'april' => 4, 'apr' => 4, 'mei' => 5, 'juni' => 6, 'jun' => 6,
        'juli' => 7, 'jul' => 7, 'agustus' => 8, 'agu' => 8, 'ags' => 8, 'aug' => 8,
        'september' => 9, 'sept' => 9, 'sep' => 9, 'oktober' => 10, 'okt' => 10, 'oct' => 10,
        'november' => 11, 'nopember' => 11, 'nov' => 11, 'nop' => 11, 'desember' => 12, 'des' => 12, 'dec' => 12,
    ];

    /** @return array{tanggal:?string,keyakinan:string,metode:string,cuplikan:?string,pesan:string} */
    public static function dariBerkas(UploadedFile $f): array
    {
        return self::deteksi($f->getRealPath(), $f->getClientOriginalName());
    }

    /** Berkas yang sudah tersimpan di disk tertutup. */
    public static function dariJalur(?string $jalur): array
    {
        if (! $jalur || ! Storage::disk(\App\Support\Berkas::TERTUTUP)->exists($jalur)) return self::gagal();

        return self::deteksi(Storage::disk(\App\Support\Berkas::TERTUTUP)->path($jalur), $jalur);
    }

    /** @return array{tanggal:?string,keyakinan:string,metode:string,cuplikan:?string,pesan:string} */
    public static function deteksi(?string $path, ?string $nama = null): array
    {
        if (! $path || ! is_file($path) || ! is_readable($path)) return self::gagal();

        $jenis = self::jenis($path, $nama);
        if ($jenis === null) return self::gagal();

        $teks = '';
        $metode = 'tidak_terbaca';

        if ($jenis === 'pdf' && self::cukup($teks = self::teksPdf($path))) $metode = 'pdf-teks';

        if (! self::cukup($teks) && self::cukup($ocr = self::teksOcr($path))) {
            $teks = $ocr;
            $metode = 'ocr';
        }

        if (! self::cukup($teks)) return self::gagal();

        $temuan = self::cariTanggal($teks);

        if ($temuan === null) {
            return ['metode' => $metode, 'cuplikan' => self::cuplikan($teks),
                'pesan' => 'Berkas terbaca, tetapi tanggal masa berlaku tidak ditemukan. Silakan isi manual.'] + self::gagal();
        }

        return [
            'tanggal'   => $temuan['tanggal']->toDateString(),
            'keyakinan' => $temuan['keyakinan'],
            'metode'    => $metode,
            'cuplikan'  => $temuan['konteks'],
            'pesan'     => $temuan['keyakinan'] === 'tinggi'
                ? 'Masa berlaku terbaca otomatis: '.$temuan['tanggal']->format('d-m-Y').'.'
                : 'Perkiraan masa berlaku: '.$temuan['tanggal']->format('d-m-Y').'. Mohon dicek sebelum disimpan.',
        ];
    }

    /** Apakah OCR tersedia di server ini. */
    public static function adaOcr(): bool
    {
        return self::tesseract() !== null;
    }

    /** @return array{tanggal:null,keyakinan:string,metode:string,cuplikan:null,pesan:string} */
    private static function gagal(): array
    {
        return ['tanggal' => null, 'keyakinan' => 'tidak_ada', 'metode' => 'tidak_terbaca', 'cuplikan' => null,
            'pesan' => 'Masa berlaku tidak terbaca dari berkas. Silakan isi tanggalnya secara manual.'];
    }

    private static function jenis(string $path, ?string $nama): ?string
    {
        if (str_starts_with((string) @file_get_contents($path, false, null, 0, 5), '%PDF')) return 'pdf';

        $mime = function_exists('mime_content_type') ? (string) @mime_content_type($path) : '';
        if ($mime === 'application/pdf') return 'pdf';
        if (str_starts_with($mime, 'image/')) return 'gambar';

        $ext = strtolower(pathinfo($nama ?? $path, PATHINFO_EXTENSION));

        return $ext === 'pdf' ? 'pdf' : (in_array($ext, self::EKSTENSI, true) ? 'gambar' : null);
    }

    private static function cukup(?string $t): bool
    {
        return is_string($t) && strlen((string) preg_replace('/\s+/', '', $t)) >= 12;
    }

    private static function teksPdf(string $path): string
    {
        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            try {
                $teks = (new \Smalot\PdfParser\Parser())->parseFile($path)->getText();
                if (self::cukup($teks)) return $teks;
            } catch (\Throwable) {
                /* PDF rusak atau terenkripsi — coba aliran mentah di bawah. */
            }
        }

        $isi = (string) @file_get_contents($path);
        $potongan = [];

        if (preg_match_all('/stream\r?\n?(.*?)\r?\n?endstream/s', $isi, $c)) {
            foreach ($c[1] as $aliran) {
                $mentah = @gzuncompress($aliran);
                if ($mentah === false) $mentah = @gzinflate($aliran);
                if ($mentah === false) $mentah = $aliran;
                if (str_contains($mentah, 'Tj') || str_contains($mentah, 'TJ')) $potongan[] = $mentah;
            }
        }

        $keluar = [];
        $konten = implode("\n", $potongan);

        if (preg_match_all('/\((?:\\\\.|[^\\\\()])*\)/s', $konten, $c)) {
            foreach ($c[0] as $lit) {
                $keluar[] = str_replace(['\\(', '\\)', '\\\\', '\\n', '\\r', '\\t'], ['(', ')', '\\', ' ', ' ', ' '], substr($lit, 1, -1));
            }
        }

        return (string) preg_replace('/\s+/', ' ', implode(' ', $keluar));
    }

    private static function teksOcr(string $path): string
    {
        $biner = self::tesseract();
        if ($biner === null || ! function_exists('shell_exec')) return '';

        $keluar = @shell_exec(escapeshellarg($biner).' '.escapeshellarg($path).' stdout -l ind+eng 2>/dev/null');

        if (! self::cukup($keluar)) {
            $keluar = @shell_exec(escapeshellarg($biner).' '.escapeshellarg($path).' stdout 2>/dev/null');
        }

        return is_string($keluar) ? $keluar : '';
    }

    private static function tesseract(): ?string
    {
        static $cache = false;
        if ($cache !== false) return $cache;

        foreach (['/usr/bin/tesseract', '/usr/local/bin/tesseract', '/opt/homebrew/bin/tesseract'] as $k) {
            if (is_file($k) && is_executable($k)) return $cache = $k;
        }

        return $cache = null;
    }

    /** @return array{tanggal:Carbon,keyakinan:string,konteks:string}|null */
    private static function cariTanggal(string $teks): ?array
    {
        $t = mb_strtolower($teks);
        $t = (string) preg_replace('/[ \t]+/', ' ', str_replace(['—', '–', '−'], '-', $t));

        $label = '(?:berlaku\s*(?:s\/?d|sampai|hingga|sd)?|masa\s*berlaku|valid\s*(?:until|thru|to)?|expired?|exp)';
        $pola  = self::pola();

        if (preg_match_all('/'.$label.'[^0-9]{0,24}('.$pola.')/iu', $t, $c, PREG_SET_ORDER)) {
            foreach ($c as $set) {
                $tgl = self::keTanggal($set[1]);
                if ($tgl && self::masukAkal($tgl)) {
                    return ['tanggal' => $tgl, 'keyakinan' => 'tinggi', 'konteks' => self::cuplikan($set[0])];
                }
            }
        }

        $hariIni  = Waktu::kini()->startOfDay();
        $terpilih = null;

        if (preg_match_all('/'.$pola.'/iu', $t, $semua)) {
            foreach ($semua[0] as $mentah) {
                $tgl = self::keTanggal($mentah);
                if ($tgl && self::masukAkal($tgl) && $tgl->gt($hariIni) && (! $terpilih || $tgl->gt($terpilih[0]))) {
                    $terpilih = [$tgl, $mentah];
                }
            }
        }

        return $terpilih ? ['tanggal' => $terpilih[0], 'keyakinan' => 'sedang', 'konteks' => self::cuplikan($terpilih[1])] : null;
    }

    private static function pola(): string
    {
        $bulan = implode('|', array_keys(self::BULAN));

        return '(?:\d{1,2}\s*[-\/.]\s*\d{1,2}\s*[-\/.]\s*\d{2,4}|\d{4}\s*[-\/.]\s*\d{1,2}\s*[-\/.]\s*\d{1,2}|\d{1,2}\s+(?:'.$bulan.')\s+\d{4})';
    }

    private static function keTanggal(string $mentah): ?Carbon
    {
        $s = strtolower(trim((string) preg_replace('/\s+/', ' ', $mentah)));

        if (preg_match('/^(\d{1,2})\s+([a-z]+)\s+(\d{4})$/u', $s, $m)) {
            return isset(self::BULAN[$m[2]]) ? self::buat((int) $m[3], self::BULAN[$m[2]], (int) $m[1]) : null;
        }

        $b = preg_split('/\s*[-\/.]\s*/', $s);
        if (! $b || count($b) !== 3) return null;

        if (strlen($b[0]) === 4) return self::buat((int) $b[0], (int) $b[1], (int) $b[2]);

        $tahun = (int) $b[2];
        if ($tahun < 100) $tahun += 2000;

        return self::buat($tahun, (int) $b[1], (int) $b[0]);
    }

    private static function buat(int $th, int $bl, int $hr): ?Carbon
    {
        return checkdate($bl, $hr, $th) ? Carbon::create($th, $bl, $hr)->startOfDay() : null;
    }

    private static function masukAkal(Carbon $t): bool
    {
        $kini = Waktu::kini()->startOfDay();

        return $t->gt($kini->copy()->subYears(5)) && $t->lt($kini->copy()->addYears(self::MAKS_TAHUN_KE_DEPAN));
    }

    private static function cuplikan(string $t): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/', ' ', $t)), 0, 120);
    }
}
