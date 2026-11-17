<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Matriks risiko 5×5 untuk laporan bahaya dari lapangan.
 *
 * Pelapor memilih KEMUNGKINAN dan KEPARAHAN (masing-masing 1–5); skornya
 * hasil kali keduanya. Dari skor itu diturunkan dua hal yang sebelumnya
 * harus dihafal pelapor: tingkat risiko, dan batas waktu tindak lanjut.
 *
 * ── EMPAT PITA, TIGA TINGKAT ──
 *
 * Matriksnya punya empat pita (Rendah, Sedang, Tinggi, Ekstrem), tetapi
 * yang disimpan tetap tiga tingkat Hazard::RISIKO. Ekstrem disimpan
 * sebagai Tinggi — KPI, analitik, register, dan ekspor seluruhnya
 * membaca tiga tingkat itu, dan tingkat keempat yang hanya dikenal
 * sebagian halaman adalah laporan yang hilang dari sebagian rekap.
 * Pembedanya tidak hilang: skor dan kedua komponennya ikut tersimpan,
 * dan tenggat Ekstrem lebih ketat (hari itu juga).
 */
final class RisikoLapangan
{
    public const KEMUNGKINAN = [1 => 'Sangat jarang', 2 => 'Jarang', 3 => 'Mungkin', 4 => 'Sering', 5 => 'Hampir pasti'];
    public const KEPARAHAN   = [1 => 'Ringan', 2 => 'Sedang', 3 => 'Serius', 4 => 'Berat', 5 => 'Fatal'];

    /**
     * Pita matriks: batas bawah skor, tingkat tersimpan, tenggat (hari), dan tindakannya.
     *
     * @var array<string, array{min:int, risiko:string, hari:int, nama:string, tindakan:string}>
     */
    public const PITA = [
        'ekstrem' => ['min' => 15, 'risiko' => 'Tinggi', 'hari' => 0, 'nama' => 'Ekstrem',
                      'tindakan' => 'Hentikan pekerjaan di area terdampak · tindak lanjut hari ini, laporkan ke KTT'],
        'tinggi'  => ['min' => 8,  'risiko' => 'Tinggi', 'hari' => 1, 'nama' => 'Tinggi',
                      'tindakan' => 'Masuk daftar tindakan pengawas · tindak lanjut ≤ 24 jam'],
        'sedang'  => ['min' => 4,  'risiko' => 'Sedang', 'hari' => 3, 'nama' => 'Sedang',
                      'tindakan' => 'Perlu pengendalian tambahan · tindak lanjut ≤ 72 jam'],
        'rendah'  => ['min' => 1,  'risiko' => 'Rendah', 'hari' => 7, 'nama' => 'Rendah',
                      'tindakan' => 'Kendalikan lewat prosedur rutin · tindak lanjut ≤ 7 hari'],
    ];

    public static function pita(int $kemungkinan, int $keparahan): string
    {
        $skor = self::skor($kemungkinan, $keparahan);

        foreach (self::PITA as $k => $p) {
            if ($skor >= $p['min']) return $k;
        }

        return 'rendah';
    }

    public static function skor(int $kemungkinan, int $keparahan): int
    {
        return max(1, min(5, $kemungkinan)) * max(1, min(5, $keparahan));
    }

    /** Tingkat yang disimpan pada kolom `risiko`. */
    public static function risiko(int $kemungkinan, int $keparahan): string
    {
        return self::PITA[self::pita($kemungkinan, $keparahan)]['risiko'];
    }

    /** Batas akhir tindak lanjut, dihitung dari tanggal temuan. */
    public static function batasAkhir(int $kemungkinan, int $keparahan, Carbon $tanggal): Carbon
    {
        return $tanggal->copy()->startOfDay()->addDays(self::PITA[self::pita($kemungkinan, $keparahan)]['hari']);
    }

    /** Untuk layar: seluruh pita, supaya warna dan kalimatnya satu sumber. */
    public static function untukLayar(): array
    {
        return [
            'kemungkinan' => self::KEMUNGKINAN,
            'keparahan'   => self::KEPARAHAN,
            'pita'        => array_map(fn ($p) => [
                'min' => $p['min'], 'nama' => $p['nama'], 'risiko' => $p['risiko'],
                'hari' => $p['hari'], 'tindakan' => $p['tindakan'],
            ], self::PITA),
        ];
    }
}
