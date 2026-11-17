<?php

namespace App\Services\Asisten;

use App\Models\SmkpAudit;
use App\Support\{Smkp, SmkpTahap};

/**
 * Konteks audit yang dikirim ke model — hanya yang membantu menilai:
 * jenis izin, komoditas, kategori, skala, dan tahap. Nama perusahaan,
 * nama orang, surel, dan kontak sengaja tidak ikut.
 */
final class KonteksAudit
{
    public static function smkp(SmkpAudit $a): string
    {
        $c = $a->company;
        $p = (array) ($a->permulaan ?? []);

        $baris = array_filter([
            'Kategori perusahaan' => $c?->labelKategori(),
            'Jenis izin'          => $c?->izin_type,
            'Komoditas'           => $c?->commodity,
            'Kelas risiko'        => ($p['kelas_risiko'] ?? '') ?: $c?->risk_class,
            'Jumlah pekerja'      => ($p['jumlah_pekerja'] ?? '') ?: ($c && $c->totalPekerja() > 0 ? (string) $c->totalPekerja() : null),
            'Tahun audit'         => (string) $a->tahun,
            'Tahap audit'         => SmkpTahap::labelTahap((int) $a->tahap),
        ], fn ($v) => trim((string) $v) !== '');

        return implode("\n", array_map(fn ($k, $v) => "- {$k}: {$v}", array_keys($baris), $baris));
    }

    /**
     * Letak satu butir yang dapat dinilai.
     *
     * @return array{elemen:array,sub:array,butir:array}|null
     */
    public static function butir(string $kode): ?array
    {
        foreach (Smkp::elemen() as $e) {
            foreach ($e['sub'] as $s) {
                foreach (Smkp::butirSub($s) as $b) {
                    if ($b['kode'] === $kode) return ['elemen' => $e, 'sub' => $s, 'butir' => $b];
                }
            }
        }

        return null;
    }

    /** @return array{elemen:array,sub:array}|null */
    public static function sub(string $kode): ?array
    {
        foreach (Smkp::elemen() as $e) {
            foreach ($e['sub'] as $s) {
                if ($s['kode'] === $kode) return ['elemen' => $e, 'sub' => $s];
            }
        }

        return null;
    }

    /**
     * Nilai tersimpan sebuah butir untuk prompt. Nilai pada skala lama atau di
     * atas maksimum ditulis apa adanya beserta keterangannya — bukan "4 dari
     * 2" yang bertentangan dengan kriteria penilaiannya.
     */
    public static function nilaiTersimpan(array $hasil, array $b): string
    {
        $u = Smkp::perluDinilaiUlang([$b['kode'] => $hasil[$b['kode']] ?? null])[$b['kode']] ?? null;
        if (!$u) return self::nilai($hasil[$b['kode']]['v'] ?? null, (int) $b['maks']);

        $v = rtrim(rtrim(number_format($u['v'], 2, ',', ''), '0'), ',');
        return match (true) {
            (bool) $u['maks_lama'] => "{$v} dari skala lama 0-{$u['maks_lama']} (sebelum nilai maksimum dikoreksi menjadi {$u['maks']}; perlu dinilai ulang)",
            (bool) $u['koreksi']   => "{$v} dari {$u['maks']}, diberikan sebelum deskripsi tingkatnya dikoreksi Daftar Koreksi dan Klarifikasi Lampiran II no. {$u['koreksi']} (perlu diperiksa ulang)",
            default                => "{$v}, di atas nilai maksimum {$u['maks']} (perlu dinilai ulang)",
        };
    }

    /** Nilai butir sebagai teks untuk prompt: "3 dari 4", "N/A", atau "belum dinilai". */
    public static function nilai(mixed $v, int $maks): string
    {
        if ($v === null || $v === '') return 'belum dinilai';
        if (is_string($v) && strcasecmp($v, Smkp::NA) === 0) return 'N/A (tidak berlaku)';

        return (int) $v.' dari '.$maks;
    }
}
