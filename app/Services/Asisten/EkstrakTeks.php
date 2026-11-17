<?php

namespace App\Services\Asisten;

use DOMDocument;
use ZipArchive;

/**
 * Pembaca isi berkas kantor tanpa pustaka tambahan: teks dari DOCX, PPTX,
 * XLSX, CSV, dan TXT (untuk dikirim ke model AI sebagai teks), serta tabel
 * dari XLSX/CSV (untuk impor metode & sampel Rencana Audit).
 *
 * Berkas datang dari pengguna, jadi pembacaannya defensif: entri ZIP yang
 * terlalu besar (bom ZIP) dilewati, XML diurai tanpa jaringan dan tanpa
 * mengganti entitas, dan hasilnya dipotong pada batas karakter.
 */
final class EkstrakTeks
{
    /** Ukuran maksimum satu entri XML di dalam paket Office (setelah dibuka). */
    private const MAKS_ENTRI = 25 * 1024 * 1024;

    /** Jumlah maksimum lembar/slide yang dibaca dari satu paket. */
    private const MAKS_BAGIAN = 60;

    /** Teks berkas; null bila jenisnya tidak didukung (mis. DOC/XLS lama) atau tidak terbaca. */
    public static function dari(string $path, string $ext, int $maksKarakter = 60000): ?string
    {
        $teks = match (strtolower($ext)) {
            'docx'        => self::docx($path),
            'pptx'        => self::pptx($path),
            'xlsx'        => self::xlsxTeks($path),
            'csv', 'txt'  => self::polos($path),
            default       => null,
        };
        if ($teks === null) return null;

        $teks = trim((string) preg_replace("/[ \t]+\n/u", "\n", (string) preg_replace("/\n{3,}/u", "\n\n", $teks)));
        if ($teks === '') return null;

        return mb_strlen($teks) > $maksKarakter ? mb_substr($teks, 0, $maksKarakter)."\n[… teks dipotong]" : $teks;
    }

    /**
     * Baris tabel dari lembar pertama XLSX atau dari CSV (pemisah koma,
     * titik koma, atau tab dikenali otomatis). Sel kosong menjadi ''.
     *
     * @return list<list<string>>|null
     */
    public static function tabel(string $path, string $ext, int $maksBaris = 2000): ?array
    {
        return match (strtolower($ext)) {
            'xlsx'       => self::xlsxBaris($path, 0, $maksBaris),
            'csv', 'txt' => self::csvBaris($path, $maksBaris),
            default      => null,
        };
    }

    /* ---------------- DOCX ---------------- */

    private static function docx(string $path): ?string
    {
        $zip = self::buka($path);
        if (!$zip) return null;

        $bagian = ['word/document.xml'];
        for ($i = 0; $i < $zip->numFiles && count($bagian) < self::MAKS_BAGIAN; $i++) {
            $n = (string) $zip->getNameIndex($i);
            if (preg_match('#^word/(header|footer)\d*\.xml$#', $n)) $bagian[] = $n;
        }

        $out = [];
        foreach ($bagian as $n) {
            $xml = self::entri($zip, $n);
            if ($xml === null) continue;
            // Akhir paragraf/baris tabel menjadi baris baru, akhir sel menjadi
            // pemisah kolom. Paragraf terakhir sebuah sel dan sel terakhir
            // sebuah baris tidak ikut dipisah, agar satu baris tabel tetap
            // satu baris teks ("Tanggal | Peserta"), bukan terpecah per sel.
            $xml = (string) preg_replace(['#</w:p>\s*</w:tc>#', '#</w:tc>\s*</w:tr>#'], ['</w:tc>', '</w:tr>'], $xml);
            $xml = str_replace(['</w:p>', '</w:tr>', '<w:br/>', '<w:cr/>'], ["\n", "\n", "\n", "\n"], $xml);
            $xml = str_replace(['</w:tc>', '<w:tab/>'], [' | ', "\t"], $xml);
            $out[] = self::tanpaTag($xml);
        }
        $zip->close();

        return $out ? implode("\n", $out) : null;
    }

    /* ---------------- PPTX ---------------- */

    private static function pptx(string $path): ?string
    {
        $zip = self::buka($path);
        if (!$zip) return null;

        $slide = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $n = (string) $zip->getNameIndex($i);
            if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', $n, $m)) $slide[(int) $m[1]] = $n;
        }
        ksort($slide);

        $out = [];
        foreach (array_slice($slide, 0, self::MAKS_BAGIAN, true) as $no => $n) {
            $xml = self::entri($zip, $n);
            if ($xml === null) continue;
            $xml = str_replace(['</a:p>', '<a:br/>'], ["\n", "\n"], $xml);
            $isi = trim(self::tanpaTag($xml));
            if ($isi !== '') $out[] = "[Slide {$no}]\n{$isi}";
        }
        $zip->close();

        return $out ? implode("\n\n", $out) : null;
    }

    /* ---------------- XLSX ---------------- */

    private static function xlsxTeks(string $path): ?string
    {
        $zip = self::buka($path);
        if (!$zip) return null;
        $lembar = self::daftarLembar($zip);
        $zip->close();

        $out = [];
        // Indeks urut, bukan kunci jalur berkas lembar — xlsxBaris memilih lembar menurut urutannya.
        foreach (array_values(array_slice($lembar, 0, 10)) as $i => $nama) {
            $baris = self::xlsxBaris($path, $i, 1500);
            if (!$baris) continue;
            $out[] = "[Lembar {$nama}]\n".implode("\n", array_map(fn ($r) => implode(' | ', $r), $baris));
        }

        return $out ? implode("\n\n", $out) : null;
    }

    /** @return list<list<string>>|null */
    private static function xlsxBaris(string $path, int $indeksLembar, int $maksBaris): ?array
    {
        $zip = self::buka($path);
        if (!$zip) return null;

        $bersama = [];
        $ss = self::entri($zip, 'xl/sharedStrings.xml');
        if ($ss !== null && ($dom = self::dom($ss))) {
            foreach ($dom->getElementsByTagName('si') as $si) {
                $t = '';
                foreach ($si->getElementsByTagName('t') as $x) $t .= $x->textContent;
                $bersama[] = $t;
            }
        }

        $berkasLembar = array_keys(self::daftarLembar($zip))[$indeksLembar] ?? null;
        $xml = $berkasLembar ? self::entri($zip, $berkasLembar) : null;
        $zip->close();
        if ($xml === null || !($dom = self::dom($xml))) return null;

        $baris = [];
        foreach ($dom->getElementsByTagName('row') as $row) {
            if (count($baris) >= $maksBaris) break;
            $sel = [];
            foreach ($row->getElementsByTagName('c') as $c) {
                $kolom = self::indeksKolom((string) $c->getAttribute('r'));
                $jenis = (string) $c->getAttribute('t');
                $v = $c->getElementsByTagName('v')->item(0)?->textContent;
                $nilai = match ($jenis) {
                    's'         => $bersama[(int) $v] ?? '',
                    'inlineStr' => (string) ($c->getElementsByTagName('t')->item(0)?->textContent ?? ''),
                    'b'         => $v === '1' ? 'TRUE' : 'FALSE',
                    default     => (string) ($v ?? ''),
                };
                $sel[$kolom ?? count($sel)] = trim($nilai);
            }
            if (!$sel) continue;
            $maks = max(array_keys($sel));
            $isi = [];
            for ($k = 0; $k <= min($maks, 50); $k++) $isi[] = $sel[$k] ?? '';
            if (implode('', $isi) !== '') $baris[] = $isi;
        }

        return $baris;
    }

    /** Berkas XML lembar kerja berurutan seperti di buku kerja → nama lembar. @return array<string,string> */
    private static function daftarLembar(ZipArchive $zip): array
    {
        $wb = self::entri($zip, 'xl/workbook.xml');
        $rel = self::entri($zip, 'xl/_rels/workbook.xml.rels');
        $target = [];
        if ($rel !== null && ($d = self::dom($rel))) {
            foreach ($d->getElementsByTagName('Relationship') as $r) {
                $t = ltrim((string) $r->getAttribute('Target'), '/');
                $target[(string) $r->getAttribute('Id')] = str_starts_with($t, 'xl/') ? $t : 'xl/'.$t;
            }
        }

        $out = [];
        if ($wb !== null && ($d = self::dom($wb))) {
            foreach ($d->getElementsByTagName('sheet') as $s) {
                $id = $s->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
                if (isset($target[$id])) $out[$target[$id]] = (string) $s->getAttribute('name');
            }
        }

        return $out ?: (self::entri($zip, 'xl/worksheets/sheet1.xml') !== null ? ['xl/worksheets/sheet1.xml' => 'Sheet1'] : []);
    }

    private static function indeksKolom(string $ref): ?int
    {
        if (!preg_match('/^([A-Z]+)\d*$/', strtoupper($ref), $m)) return null;
        $n = 0;
        foreach (str_split($m[1]) as $h) $n = $n * 26 + (ord($h) - 64);

        return $n - 1;
    }

    /* ---------------- CSV / TXT ---------------- */

    private static function polos(string $path): ?string
    {
        $isi = @file_get_contents($path, false, null, 0, 5 * 1024 * 1024);

        return $isi === false ? null : self::utf8($isi);
    }

    /** @return list<list<string>>|null */
    private static function csvBaris(string $path, int $maksBaris): ?array
    {
        $isi = self::polos($path);
        if ($isi === null) return null;

        $baris = preg_split("/\r\n|\n|\r/", $isi) ?: [];
        $contoh = implode("\n", array_slice($baris, 0, 5));
        $pemisah = collect([';', ',', "\t"])->sortByDesc(fn ($p) => substr_count($contoh, $p))->first();

        $out = [];
        foreach ($baris as $b) {
            if (trim($b) === '') continue;
            $out[] = array_map(fn ($x) => trim((string) $x), str_getcsv($b, $pemisah, '"', ''));
            if (count($out) >= $maksBaris) break;
        }

        return $out;
    }

    private static function utf8(string $isi): string
    {
        $isi = preg_replace('/^\xEF\xBB\xBF/', '', $isi) ?? $isi;
        if (str_starts_with($isi, "\xFF\xFE") || str_starts_with($isi, "\xFE\xFF")) {
            $isi = (string) mb_convert_encoding(substr($isi, 2), 'UTF-8', str_starts_with($isi, "\xFF\xFE") ? 'UTF-16LE' : 'UTF-16BE');
        } elseif (!mb_check_encoding($isi, 'UTF-8')) {
            $isi = (string) mb_convert_encoding($isi, 'UTF-8', 'Windows-1252');
        }

        return $isi;
    }

    /* ---------------- bantu ---------------- */

    private static function buka(string $path): ?ZipArchive
    {
        if (!class_exists(ZipArchive::class)) return null;
        $zip = new ZipArchive();

        return $zip->open($path, ZipArchive::RDONLY) === true ? $zip : null;
    }

    private static function entri(ZipArchive $zip, string $nama): ?string
    {
        $stat = $zip->statName($nama);
        if ($stat === false || ($stat['size'] ?? 0) > self::MAKS_ENTRI) return null;
        $isi = $zip->getFromName($nama);

        return $isi === false ? null : $isi;
    }

    private static function dom(string $xml): ?DOMDocument
    {
        $d = new DOMDocument();
        $lama = libxml_use_internal_errors(true);
        $ok = $d->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($lama);

        return $ok ? $d : null;
    }

    private static function tanpaTag(string $xml): string
    {
        return html_entity_decode(strip_tags($xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
