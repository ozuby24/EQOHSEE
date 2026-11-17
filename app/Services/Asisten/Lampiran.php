<?php

namespace App\Services\Asisten;

/**
 * Satu berkas yang ikut dikirim ke model AI bersama permintaan.
 *
 * PDF dan gambar dikirim utuh (biner, base64) — ketiga penyedia membaca
 * keduanya langsung, termasuk hasil pindaian. DOCX/XLSX/PPTX/CSV/TXT
 * dikirim sebagai teks hasil ekstraksi. Format lama (DOC/XLS/PPT) tidak
 * dapat dibaca; pemanggil menyebutnya sebagai berkas yang dilewati.
 */
final class Lampiran
{
    public const BINER = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    /**
     * Total biner per permintaan sebelum base64. Base64 menambah 4/3, jadi
     * 13 MB menjadi ±18 MB — masih di bawah batas 20 MB per permintaan Gemini
     * bersama teks hasil ekstraksi dan prompt.
     */
    public const MAKS_BINER_TOTAL = 13 * 1024 * 1024;

    /** Gambar terbesar yang dikirim (Claude menolak gambar di atas 5 MB setelah base64). */
    public const MAKS_GAMBAR = 3_500_000;

    /** Sisi terpanjang gambar yang diperkecil — cukup untuk membaca dokumen & foto lapangan. */
    private const SISI_GAMBAR = 2000;

    /** Teks hasil ekstraksi per berkas dan per permintaan (karakter). */
    public const MAKS_TEKS_BERKAS = 60000;
    public const MAKS_TEKS_TOTAL  = 150000;

    public function __construct(
        public readonly string $nama,
        public readonly string $mime,
        public readonly ?string $isi = null,
        public readonly ?string $teks = null,
    ) {}

    /**
     * Baca berkas di disk. Null bila jenisnya tidak didukung atau isinya
     * tidak terbaca; pemanggil memutuskan cara melaporkannya.
     */
    public static function dariBerkas(string $path, string $nama, ?string $mime): ?self
    {
        if (!is_file($path) || !is_readable($path)) return null;
        $ext = strtolower(pathinfo($nama, PATHINFO_EXTENSION));
        $mime = (string) $mime;

        if (in_array($mime, self::BINER, true)) {
            $isi = @file_get_contents($path);
            if ($isi === false || $isi === '') return null;

            // Foto ponsel sering di atas batas per gambar: perkecil dulu.
            if (str_starts_with($mime, 'image/') && strlen($isi) > self::MAKS_GAMBAR && ($kecil = self::perkecil($isi))) {
                return new self($nama, 'image/jpeg', $kecil);
            }

            return new self($nama, $mime, $isi);
        }

        $teks = EkstrakTeks::dari($path, $ext, self::MAKS_TEKS_BERKAS);

        return $teks === null ? null : new self($nama, $mime ?: 'text/plain', null, $teks);
    }

    public function biner(): bool
    {
        return $this->isi !== null && $this->isi !== '';
    }

    public function gambar(): bool
    {
        return $this->biner() && str_starts_with($this->mime, 'image/');
    }

    public function base64(): string
    {
        return base64_encode((string) $this->isi);
    }

    /** Beban terhadap batas: byte biner, atau karakter teks. */
    public function ukuran(): int
    {
        return $this->biner() ? strlen((string) $this->isi) : mb_strlen((string) $this->teks);
    }

    /** Label pembuka untuk berkas, dipakai sebelum gambar/teks agar model tahu nama berkasnya. */
    public function label(): string
    {
        return 'Berkas: '.$this->nama;
    }

    /** Teks hasil ekstraksi dibungkus penanda awal/akhir berkas. */
    public function teksBerbingkai(): string
    {
        return "=== Berkas: {$this->nama} (teks hasil ekstraksi) ===\n".$this->teks."\n=== Akhir berkas {$this->nama} ===";
    }

    /**
     * Pilih lampiran yang muat dalam batas total biner dan teks, berurutan.
     *
     * @param  list<self> $daftar
     * @return array{0: list<self>, 1: list<string>} [diambil, nama yang dilewati karena batas]
     */
    public static function muat(array $daftar): array
    {
        $ambil = $lewat = [];
        $biner = $teks = 0;
        foreach ($daftar as $l) {
            $n = $l->ukuran();
            $lebih = $l->gambar() && $n > self::MAKS_GAMBAR;
            if ($lebih || ($l->biner() ? $biner + $n > self::MAKS_BINER_TOTAL : $teks + $n > self::MAKS_TEKS_TOTAL)) {
                $lewat[] = $l->nama;
                continue;
            }
            $l->biner() ? $biner += $n : $teks += $n;
            $ambil[] = $l;
        }

        return [$ambil, $lewat];
    }

    /**
     * Perkecil gambar ke sisi terpanjang SISI_GAMBAR px sebagai JPEG di atas
     * latar putih (PNG/WEBP transparan tetap terbaca). Null bila GD tidak
     * tersedia, gambar tak terbaca/terlalu besar untuk dibuka, atau hasilnya
     * masih melampaui batas.
     */
    private static function perkecil(string $isi): ?string
    {
        if (!function_exists('imagecreatefromstring')) return null;
        $ukuran = @getimagesizefromstring($isi);
        if (!$ukuran || $ukuran[0] < 1 || $ukuran[1] < 1 || $ukuran[0] * $ukuran[1] > 40_000_000) return null;

        $asal = @imagecreatefromstring($isi);
        if (!$asal) return null;
        [$w, $h] = [imagesx($asal), imagesy($asal)];
        $skala = min(1, self::SISI_GAMBAR / max($w, $h));
        [$bw, $bh] = [max(1, (int) round($w * $skala)), max(1, (int) round($h * $skala))];

        $baru = imagecreatetruecolor($bw, $bh);
        imagefill($baru, 0, 0, imagecolorallocate($baru, 255, 255, 255));
        imagecopyresampled($baru, $asal, 0, 0, 0, 0, $bw, $bh, $w, $h);
        ob_start();
        imagejpeg($baru, null, 82);
        $hasil = (string) ob_get_clean();

        return $hasil !== '' && strlen($hasil) <= self::MAKS_GAMBAR ? $hasil : null;
    }
}
