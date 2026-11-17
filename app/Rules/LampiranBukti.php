<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use ZipArchive;

/**
 * Lampiran bukti tindak lanjut: isi berkas harus cocok dengan ekstensinya.
 *
 * Deteksi jenis isi (finfo) tidak selalu mengenali dokumen Office — DOCX
 * yang entri ZIP pertamanya bukan [Content_Types].xml terbaca sebagai
 * octet-stream, dokumen berkata sandi sebagai "encrypted". Karena itu
 * dokumen Office diperiksa dari strukturnya: wadah OLE (DOC/XLS/PPT dan
 * OOXML berkata sandi) atau paket ZIP Office Open XML.
 */
class LampiranBukti implements ValidationRule
{
    public const EKSTENSI = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];

    private const GAMBAR = ['image/jpeg', 'image/png', 'image/webp'];
    private const OLE = "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Berkas yang gagal terunggah ditangani aturan 'file'.
        if (!$value instanceof UploadedFile || !$value->isValid()) return;

        if (!$this->cocok(strtolower($value->getClientOriginalExtension()), (string) $value->getMimeType(), (string) $value->getRealPath())) {
            $fail(':attribute: isinya tidak sesuai jenis berkasnya — unggah PDF, gambar, atau dokumen Office.');
        }
    }

    private function cocok(string $ext, string $mime, string $path): bool
    {
        return match ($ext) {
            'pdf'                 => $mime === 'application/pdf',
            'jpg', 'jpeg', 'png', 'webp' => in_array($mime, self::GAMBAR, true),
            'doc', 'xls', 'ppt'   => $this->ole($path),
            'docx', 'xlsx', 'pptx' => str_starts_with($mime, 'application/vnd.openxmlformats-officedocument.')
                                      || $this->paketOffice($path) || $this->ole($path),
            default               => false,
        };
    }

    private function ole(string $path): bool
    {
        $h = @fopen($path, 'rb');
        if (!$h) return false;
        $awal = (string) fread($h, 8);
        fclose($h);

        return $awal === self::OLE;
    }

    private function paketOffice(string $path): bool
    {
        if (!class_exists(ZipArchive::class)) return false;
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) return false;
        $ada = $zip->locateName('[Content_Types].xml') !== false;
        $zip->close();

        return $ada;
    }
}
