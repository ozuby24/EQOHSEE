<?php

namespace App\Support;

use App\Models\SmkpAudit;

/**
 * Formulir Kesesuaian otomatis dan OFI dari kesesuaian (port DBEST #23).
 *
 * Formulir Kesesuaian (Formulir 3) tersusun sendiri dari penilaian: setiap
 * butir yang mencapai nilai maksimum (kategori Kesesuaian) menjadi satu
 * uraian. Keterangan bawaannya diambil dari deskripsi tingkat nilai
 * tertinggi kriteria Kepdirjen 185 dengan subjek nama perusahaan
 * ("PT X telah menetapkan …"). Auditor dapat:
 *   - menyunting keterangannya,
 *   - mengeluarkannya dari formulir dan laporan, dan
 *   - menjadikannya Peluang untuk Perbaikan (OFI, Formulir 6) dengan
 *     justifikasi: sudah sesuai kriteria, tetapi masih dapat ditingkatkan.
 * Uraian kesesuaian tambahan dan rekomendasi/OFI lain tetap diisi manual.
 *
 * Penyimpanan, kolom smkp_audits.kesesuaian (daftar baris):
 *   - suntingan butir otomatis: ['kode' => 'II.5', 'otomatis' => true,
 *     'keterangan' => ?, 'keluar' => bool, 'ofi' => bool, 'justifikasi' => ?]
 *   - uraian tambahan auditor: ['elemen', 'sub', 'subsub', 'keterangan']
 * Kolom smkp_audits.rekomendasi berisi rekomendasi/OFI tambahan auditor.
 *
 * Bukan App\Support\Kesesuaian — itu pemeriksa kesesuaian isi antarmodul.
 */
class SmkpKesesuaian
{
    public const MAKS_TEKS = 3000;

    /** Butir berkategori Kesesuaian (capaian 100%) pada penilaian, urut Lampiran II. */
    public static function butir(array $hasil): array
    {
        $out = [];
        foreach (Smkp::butir() as $b) {
            $v = Smkp::nilaiSetara($hasil, $b);
            if (!is_float($v) || (float) $b['maks'] <= 0) continue;
            if (Smkp::kategoriDari($v / (float) $b['maks'])['kode'] === 'kesesuaian') $out[] = $b;
        }

        return $out;
    }

    /** Elemen, sub elemen, dan sub-sub elemen sebuah butir. */
    public static function letak(array $b): array
    {
        return [
            'elemen' => (string) $b['elemen'],
            'sub'    => (string) $b['sub'],
            'subsub' => $b['kode'] !== $b['sub'] ? (string) $b['kode'] : '',
        ];
    }

    /**
     * Keterangan bawaan: deskripsi nilai tertinggi kriteria resmi dengan
     * subjek nama perusahaan. "Terdapat bukti yang menunjukkan telah
     * terdapat KTT …" menjadi "PT X telah memiliki KTT …".
     */
    public static function bawaan(array $b, string $pt, bool $jasa = false): string
    {
        $pt = trim($pt) !== '' ? trim($pt) : 'Perusahaan';
        $t  = trim((string) Smkp::uraianPenerapan($b['kode'], (float) $b['maks'], $pt, $jasa));
        if ($t === '') {
            return "{$pt} telah memenuhi kriteria {$b['kode']} {$b['nama']} dengan nilai ".(int) $b['maks'].' dari '.(int) $b['maks'].'.';
        }

        $ganti = [
            '/^Terdapat bukti yang menunjukkan(?: bahwa)?\s*:\s*/u'           => " telah memenuhi kondisi berikut:\n",
            '/^Terdapat bukti yang menunjukkan(?: bahwa)? telah terdapat\s+/u' => ' telah memiliki ',
            '/^Terdapat bukti yang menunjukkan(?: bahwa)? telah\s+/u'          => ' telah ',
        ];
        foreach ($ganti as $pola => $sesudah) {
            // Disambung manual: "$" dan "\" pada nama perusahaan bukan rujukan kelompok preg_replace.
            if (preg_match($pola, $t)) return $pt.$sesudah.(string) preg_replace($pola, '', $t, 1);
        }

        return $t;
    }

    /** Suntingan butir otomatis yang tersimpan: kode → isian. */
    public static function suntingan(array $kesesuaian): array
    {
        $out = [];
        foreach ($kesesuaian as $r) {
            $r = (array) $r;
            if (!empty($r['otomatis']) && isset($r['kode'])) $out[(string) $r['kode']] = $r;
        }

        return $out;
    }

    /** Uraian kesesuaian tambahan (diisi manual) yang tersimpan. */
    public static function tambahan(array $kesesuaian): array
    {
        $out = [];
        foreach ($kesesuaian as $r) {
            $r = (array) $r;
            if (!empty($r['otomatis']) || trim((string) ($r['keterangan'] ?? '')) === '') continue;
            $out[] = [
                'elemen'     => (string) ($r['elemen'] ?? ''),
                'sub'        => (string) ($r['sub'] ?? ''),
                'subsub'     => (string) ($r['subsub'] ?? ''),
                'keterangan' => trim((string) $r['keterangan']),
            ];
        }

        return $out;
    }

    public static function namaPerusahaan(SmkpAudit $a): string
    {
        return trim((string) $a->company?->name) ?: 'Perusahaan';
    }

    /** Perusahaan jasa pertambangan (IUJP): butir II.5 dan III.3 memakai rubrik versi IUJP. */
    public static function jasa(SmkpAudit $a): bool
    {
        return $a->company?->kategori === 'pjp';
    }

    /**
     * Uraian otomatis dari penilaian beserta suntingannya. Tiap baris:
     * kode, nama, maks, elemen, sub, subsub, keterangan (yang dipakai),
     * bawaan, diubah, keluar, ofi, justifikasi, catatan & bukti penilaian,
     * manual (sudah ada uraian tambahan berkode sama — uraian otomatisnya
     * tidak dicetak).
     */
    public static function otomatis(SmkpAudit $a): array
    {
        $hasil = (array) ($a->hasil ?? []);
        $simpan = (array) ($a->kesesuaian ?? []);
        $pt = self::namaPerusahaan($a);
        $jasa = self::jasa($a);
        $ubah = self::suntingan($simpan);

        // Uraian tambahan berkode sama (mis. diketik sebelum formulir otomatis) menggantikan uraian otomatisnya.
        $manual = [];
        foreach (self::tambahan($simpan) as $t) $manual[self::kodeBaris($t)] = true;

        $out = [];
        foreach (self::butir($hasil) as $b) {
            $o = $ubah[$b['kode']] ?? [];
            $bawaan = self::bawaan($b, $pt, $jasa);
            $ket = trim((string) ($o['keterangan'] ?? ''));
            $out[] = self::letak($b) + [
                'kode'        => $b['kode'],
                'nama'        => $b['nama'],
                'maks'        => (int) $b['maks'],
                'keterangan'  => $ket !== '' ? $ket : $bawaan,
                'bawaan'      => $bawaan,
                'diubah'      => $ket !== '' && $ket !== $bawaan,
                'keluar'      => !empty($o['keluar']),
                'ofi'         => !empty($o['ofi']),
                'justifikasi' => trim((string) ($o['justifikasi'] ?? '')),
                'catatan'     => trim((string) ($hasil[$b['kode']]['ket'] ?? '')),
                'bukti'       => trim((string) ($hasil[$b['kode']]['bukti'] ?? '')),
                'manual'      => isset($manual[$b['kode']]),
            ];
        }

        return $out;
    }

    /** Kode terdalam sebuah baris (sub-sub, sub, atau elemen) untuk pengurutan. */
    private static function kodeBaris(array $r): string
    {
        foreach (['subsub', 'sub', 'elemen'] as $k) {
            if (trim((string) ($r[$k] ?? '')) !== '') return (string) $r[$k];
        }

        return '';
    }

    /** Urut Lampiran II; baris berkode sama tetap pada urutan masuknya. */
    private static function urut(array $baris): array
    {
        $kunci = [];
        foreach (array_values($baris) as $i => $r) $kunci[$i] = [Smkp::urutanKode(self::kodeBaris($r)), $i];
        $baris = array_values($baris);
        uksort($baris, fn ($x, $y) => $kunci[$x] <=> $kunci[$y]);

        return array_values($baris);
    }

    /**
     * Seluruh uraian Formulir Kesesuaian (cetak, laporan, hitungan): butir
     * otomatis yang tidak dikeluarkan auditor ditambah uraian tambahan, urut
     * Lampiran II. Baris: elemen, sub, subsub, keterangan, kode|null.
     */
    public static function daftar(SmkpAudit $a): array
    {
        $out = [];
        foreach (self::otomatis($a) as $r) {
            if (!$r['keluar'] && !$r['manual'] && $r['keterangan'] !== '') {
                $out[] = ['elemen' => $r['elemen'], 'sub' => $r['sub'], 'subsub' => $r['subsub'], 'keterangan' => $r['keterangan'], 'kode' => $r['kode']];
            }
        }
        foreach (self::tambahan((array) ($a->kesesuaian ?? [])) as $r) $out[] = $r + ['kode' => null];

        return self::urut($out);
    }

    /**
     * Seluruh Rekomendasi & OFI (Formulir 6): OFI dari kesesuaian yang
     * berjustifikasi — selama butirnya masih Kesesuaian — ditambah
     * rekomendasi/OFI yang diisi manual. Baris: elemen, sub, subsub,
     * uraian, kode|null, dari ('kesesuaian'|'manual').
     */
    public static function ofi(SmkpAudit $a): array
    {
        $out = [];
        foreach (self::otomatis($a) as $r) {
            if ($r['ofi'] && $r['justifikasi'] !== '') {
                $out[] = ['elemen' => $r['elemen'], 'sub' => $r['sub'], 'subsub' => $r['subsub'], 'uraian' => $r['justifikasi'], 'kode' => $r['kode'], 'dari' => 'kesesuaian'];
            }
        }
        foreach ((array) ($a->rekomendasi ?? []) as $r) {
            $r = (array) $r;
            if (trim((string) ($r['uraian'] ?? '')) === '') continue;
            $out[] = [
                'elemen' => (string) ($r['elemen'] ?? ''),
                'sub'    => (string) ($r['sub'] ?? ''),
                'subsub' => (string) ($r['subsub'] ?? ''),
                'uraian' => trim((string) $r['uraian']),
                'kode'   => null,
                'dari'   => 'manual',
            ];
        }

        return self::urut($out);
    }

    /**
     * Gabungkan isian butir otomatis dari formulir dengan suntingan
     * tersimpan. $isian: daftar [kode, masuk, keterangan, ofi, justifikasi].
     * Butir yang tidak tampil di formulir (mis. kini bukan Kesesuaian)
     * suntingannya dipertahankan; suntingan yang tidak mengubah apa pun
     * tidak disimpan.
     *
     * @return array{0: array<int, array>, 1: string[]} [baris suntingan, kode OFI tanpa justifikasi]
     */
    public static function gabungSuntingan(SmkpAudit $a, array $isian): array
    {
        $lama = self::suntingan((array) ($a->kesesuaian ?? []));
        $kini = array_column(self::otomatis($a), null, 'kode');

        $tanpaJustifikasi = [];
        foreach ($isian as $v) {
            $v = (array) $v;
            $kode = (string) ($v['kode'] ?? '');
            if (!isset($kini[$kode])) continue;

            $teks = fn (string $k) => mb_substr(str_replace(["\r\n", "\r"], "\n", trim(is_scalar($v[$k] ?? null) ? (string) $v[$k] : '')), 0, self::MAKS_TEKS);
            $ket  = $teks('keterangan');
            $just = $teks('justifikasi');
            $ofi  = filter_var($v['ofi'] ?? false, FILTER_VALIDATE_BOOLEAN);
            // Isian JSON selalu membawa "masuk"; yang tidak membawanya dianggap tetap masuk.
            $masuk = !array_key_exists('masuk', $v) || filter_var($v['masuk'], FILTER_VALIDATE_BOOLEAN);

            $baris = [
                'kode'        => $kode,
                'otomatis'    => true,
                'keterangan'  => $ket !== '' && $ket !== $kini[$kode]['bawaan'] ? $ket : '',
                'keluar'      => !$masuk,
                'ofi'         => $ofi,
                'justifikasi' => $just,
            ];
            if ($ofi && $just === '') $tanpaJustifikasi[] = $kode;
            if ($baris['keterangan'] === '' && $masuk && !$ofi && $just === '') {
                unset($lama[$kode]);
                continue;
            }
            $lama[$kode] = $baris;
        }

        return [array_values($lama), $tanpaJustifikasi];
    }
}
