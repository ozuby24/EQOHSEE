<?php

namespace App\Support;

/**
 * Kuesioner PTPKKP — 30 butir yang memetakan langsung ke 30 item ber-metode KS.
 *
 * Kategori mengikuti istilah yang sudah dipakai EQOHSEE:
 *   'pimpinan' → entitas KS "Management" (27 butir)
 *   'pekerja'  → entitas KS "Employee"   (3 butir)
 *
 * Jawaban disimpan di tpkkp_responses.answers dengan KODE ITEM sebagai kunci,
 * mis. ['2.2.5' => 4, '2.6.9' => 3].
 */
class TpkkpKuesioner
{
    private static ?array $ref = null;

    public static function ref(): array
    {
        return self::$ref ??= json_decode(
            file_get_contents(resource_path('data/tpkkp/kuesioner.json')), true
        );
    }

    public static function skala(): array   { return self::ref()['scale'] ?? []; }
    public static function identitas(): array { return self::ref()['identity'] ?? []; }

    /** @return array<string,array{label:string,hint:string,positions:list<string>}> */
    public static function kelompokJabatan(): array
    {
        return self::identitas()['positionGroups'] ?? [];
    }

    /**
     * Kuesioner mana yang seharusnya diisi orang berjabatan ini.
     *
     * ALUR DIBALIK dari yang sebelumnya. Dulu responden memilih sendiri
     * kuesionernya dari dua tab, dan pekerja tambang berulang kali
     * mengisi kuesioner pimpinan unit kerja — bukan karena lalai
     * melainkan karena tab pertama yang terlihat memang itu. Jawabannya
     * masuk sebagai persepsi pimpinan atas dirinya sendiri, dan tidak
     * ada satu pun tanda bahwa itu terjadi.
     *
     * Dicocokkan dua tahap: daftar resmi lebih dulu, lalu kata kunci
     * bagi jabatan yang diketik bebas atau berasal dari data lama.
     *
     * BAWAANNYA "pekerja", dan itu keputusan yang disengaja. Jabatan tak
     * dikenal yang jatuh ke kuesioner pekerja hanya kehilangan sebagian
     * pertanyaan; yang jatuh ke kuesioner pimpinan MENCEMARI penilaian
     * kepemimpinan dengan jawaban orang yang tidak memimpin siapa pun.
     */
    public static function kategoriUntukJabatan(?string $jabatan): ?string
    {
        $mentah = trim((string) $jabatan);

        if ($mentah === '') return null;

        $samakan = fn (string $x) => preg_replace('/\s+/u', ' ', mb_strtolower(trim($x), 'UTF-8'));
        $kunci   = $samakan($mentah);

        foreach (self::kelompokJabatan() as $kode => $kel) {
            foreach ($kel['positions'] ?? [] as $j) {
                if ($samakan($j) === $kunci) return $kode;
            }
        }

        /* Kata kunci pengawas ke atas. Foreman dan Officer adalah batas
           bawahnya — merekalah jabatan terendah yang mengawasi orang. */
        $pimpinan = '/(direktur|general manager|project manager|\bmanager\b|manajer|superi?n?tendent'
            .'|supervisor|dokter|foreman|leading hand|group leader|officer|paramedic|kepala'
            .'|\bktt\b|\bpjo\b|pengawas|head|chief|coordinator|koordinator|section|superior)/u';

        return preg_match($pimpinan, $kunci) ? 'pimpinan' : 'pekerja';
    }

    /** @return array<string,array> kategori => ['label','entity','questions'] */
    public static function kategori(): array { return self::ref()['kategori'] ?? []; }

    public static function punya(string $cat): bool
    {
        return isset(self::kategori()[$cat]);
    }

    public static function butir(string $cat): array
    {
        return self::kategori()[$cat]['questions'] ?? [];
    }

    public static function entitas(string $cat): ?string
    {
        return self::kategori()[$cat]['entity'] ?? null;
    }

    public static function label(string $cat): string
    {
        return self::kategori()[$cat]['label'] ?? $cat;
    }

    /** Kode item yang sah untuk satu kategori — dipakai saat validasi kiriman. */
    public static function kodeSah(string $cat): array
    {
        return array_column(self::butir($cat), 'code');
    }

    /** Butir dikelompokkan per parameter, untuk tampilan formulir. */
    public static function butirPerParam(string $cat): array
    {
        $out = [];
        foreach (self::butir($cat) as $b) {
            $out[$b['param']] ??= ['code' => $b['param'], 'name' => $b['paramName'], 'items' => []];
            $out[$b['param']]['items'][] = $b;
        }
        return array_values($out);
    }

    /* ================= rekap ================= */

    /** Rerata seluruh jawaban satu kategori (skala 1–5). */
    public static function rerata($rows): ?float
    {
        $sum = 0; $n = 0;
        foreach ($rows as $r) {
            foreach ((array) $r->answers as $v) {
                if (is_numeric($v)) { $sum += (int) $v; $n++; }
            }
        }
        return $n ? round($sum / $n, 2) : null;
    }

    /** Rerata per parameter — bentuk keluaran dipertahankan agar tampilan admin lama tetap jalan. */
    public static function rerataParam($rows, string $cat): array
    {
        $out = [];
        foreach (self::butirPerParam($cat) as $p) {
            $kode = array_column($p['items'], 'code');
            $sum = 0; $n = 0;
            foreach ($rows as $r) {
                foreach ((array) $r->answers as $code => $v) {
                    if (is_numeric($v) && in_array((string) $code, $kode, true)) { $sum += (int) $v; $n++; }
                }
            }
            $out[] = [
                'code'   => $p['code'],
                'name'   => $p['name'],
                'rerata' => $n ? round($sum / $n, 2) : null,
                'pct'    => $n ? round($sum / $n / 5 * 100) : null,
                'n'      => $n,
            ];
        }
        return $out;
    }

    /* ================= penarikan ke skor KS ================= */

    /**
     * Hitung rerata per kode item per entitas dari kumpulan respons.
     *
     * @return array{nilai: array<string,array<string,float>>, jumlah: array<string,array<string,int>>}
     */
    public static function agregat($rows): array
    {
        $sum = []; $cnt = [];

        foreach ($rows as $r) {
            $ent = self::entitas((string) $r->cat);
            if (!$ent) continue;

            foreach ((array) $r->answers as $code => $v) {
                if (!is_numeric($v)) continue;
                $v = (int) $v;
                if ($v < 1 || $v > 5) continue;

                $sum[$code][$ent] = ($sum[$code][$ent] ?? 0) + $v;
                $cnt[$code][$ent] = ($cnt[$code][$ent] ?? 0) + 1;
            }
        }

        $nilai = [];
        foreach ($sum as $code => $perEnt) {
            foreach ($perEnt as $ent => $s) {
                $nilai[$code][$ent] = round($s / $cnt[$code][$ent], 2);
            }
        }

        return ['nilai' => $nilai, 'jumlah' => $cnt];
    }

    /**
     * Tulis hasil agregat ke scores['KS'] milik satu penilaian.
     * Hanya kode yang memang ber-metode KS di instrumen yang ditulis.
     *
     * @return array{ditulis: int, entitas: int, dilewati: array<string>}
     */
    public static function tulisKeSkor(array $scores, array $agregat): array
    {
        $sah = [];
        foreach (Tpkkp::allItems() as $it) {
            if (in_array('KS', $it['methods'], true)) $sah[] = $it['code'];
        }

        $entSah   = Tpkkp::methods()['KS']['entities'] ?? [];
        $ditulis  = 0;
        $selEnt   = 0;
        $dilewati = [];

        $scores['KS'] ??= [];

        foreach ($agregat['nilai'] as $code => $perEnt) {
            if (!in_array((string) $code, $sah, true)) { $dilewati[] = (string) $code; continue; }

            $rec = $scores['KS'][$code] ?? ['v' => null, 'e' => [], 'ket' => ''];
            $rec['e'] ??= [];

            foreach ($perEnt as $ent => $v) {
                if (!in_array($ent, $entSah, true)) continue;
                $rec['e'][$ent] = $v;
                $selEnt++;
            }

            $n = 0;
            foreach (($agregat['jumlah'][$code] ?? []) as $j) $n += $j;
            $rec['ket'] = 'Dari kuesioner · ' . $n . ' jawaban · ' . now()->format('d M Y H:i');

            $scores['KS'][$code] = $rec;
            $ditulis++;
        }

        return ['scores' => $scores, 'ditulis' => $ditulis, 'entitas' => $selEnt, 'dilewati' => $dilewati];
    }
}
