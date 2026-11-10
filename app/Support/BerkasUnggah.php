<?php

namespace App\Support;

use Illuminate\Http\{Request, UploadedFile};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alat bantu bersama untuk berkas unggahan pengguna di disk privat 'local'
 * (lampiran tindak lanjut, bukti kelengkapan audit): nama tampilan yang
 * aman, nama berkas untuk pesan galat, sisa disk, dan pengiriman berkas
 * dengan header yang mencegah peramban menjalankan isinya.
 */
final class BerkasUnggah
{
    /** Gambar boleh ditampilkan langsung (pratinjau); lainnya selalu diunduh. */
    public const PRATINJAU = ['image/jpeg', 'image/png', 'image/webp'];

    /** Sisa disk minimum yang dijaga untuk basis data & log. */
    public const SISA_DISK_MIN = 1024 * 1024 * 1024;

    private const AMAN = ['X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => 'sandbox', 'Cache-Control' => 'private, no-store'];

    /**
     * Nama asli tanpa jalur dan karakter kendali — hanya untuk tampilan & nama unduhan; ekstensi selalu dipertahankan.
     * Tanpa basename()/pathinfo() yang bergantung locale server, dan tanpa pengarah teks (bidi) yang dapat
     * menyamarkan ekstensi di layar, mis. "tagihan‮fdp.jpg" tampil seolah-olah "tagihangpj.pdf".
     */
    public static function namaAman(string $nama, string $ext, string $cadangan = 'lampiran'): string
    {
        $nama = Str::afterLast(str_replace('\\', '/', mb_scrub($nama, 'UTF-8')), '/');
        $dasar = str_contains($nama, '.') ? Str::beforeLast($nama, '.') : $nama;
        $dasar = trim((string) preg_replace('/[\p{Cc}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]+/u', '', $dasar), " .");

        return (trim(Str::limit($dasar, 140, '')) ?: $cadangan).'.'.$ext;
    }

    /** Nama atribut validasi berupa nama berkasnya, bukan "berkas.2". @return array<string,string> */
    public static function namaAtribut(Request $request, string $kolom): array
    {
        $nama = [];
        foreach ((array) $request->file($kolom, []) as $i => $f) {
            if ($f instanceof UploadedFile) $nama["{$kolom}.{$i}"] = '"'.Str::limit($f->getClientOriginalName(), 60).'"';
        }

        return $nama;
    }

    /** Jumlah byte berkas yang sedang diunggah pada satu kolom. */
    public static function ukuranKiriman(Request $request, string $kolom): int
    {
        $n = 0;
        foreach ((array) $request->file($kolom, []) as $f) $n += $f instanceof UploadedFile ? (int) $f->getSize() : 0;

        return $n;
    }

    /** Disk server masih menyisakan ruang aman setelah $baru byte ditulis. */
    public static function diskCukup(int $baru): bool
    {
        $sisa = @disk_free_space(storage_path('app'));

        return $sisa === false || $sisa - $baru >= self::SISA_DISK_MIN;
    }

    /** Kirim berkas: gambar boleh tampil langsung bila diminta (?lihat), selain itu selalu diunduh. */
    public static function kirim(?string $path, ?string $nama, ?string $mime, bool $lihat): Response
    {
        $disk = Storage::disk('local');
        abort_unless($path && $disk->exists($path), 404);

        if ($lihat && in_array($mime, self::PRATINJAU, true)) {
            // BinaryFileResponse bawaannya "public": kembalikan ke private agar proksi bersama tidak menyimpannya.
            return response()->file($disk->path($path), self::AMAN + ['Content-Type' => $mime])->setPrivate();
        }

        return $disk->download($path, $nama ?: basename($path), self::AMAN);
    }

    public static function mb(int $byte): string
    {
        return round($byte / 1048576).' MB';
    }
}
