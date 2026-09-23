<?php

namespace App\Support\Frop;

use App\Models\Frop\{Coaching, Observasi};
use App\Models\Miners\Pekerja;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as TanggalExcel;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Impor berkas kerja "Observasi PTY-CT Loader" (.xlsx).
 *
 * Yang dibaca hanya kolom ISIAN sheet Akumulatif Observasi dan Coaching
 * Log. Kolom berumus — Aktual CT, Status, Ringkasan, Rekomendasi — tidak
 * diambil; Penilaian menghitungnya ulang dari komponen yang diimpor,
 * sehingga kesalahan rumus berkas asalnya tidak ikut pindah.
 *
 * Kolomnya dicari menurut JUDUL, bukan huruf kolom. Berkas kerja
 * seperti ini hidup: satu kolom disisipkan di tengah, dan pembaca yang
 * berpegang pada huruf kolom akan memasukkan Lebar Front ke Spotting
 * tanpa satu pun galat.
 *
 * ── Isian yang harus ditafsirkan ──
 *
 * - Loading time. Sebagian besar tersimpan sebagai durasi m:ss. Tetapi
 *   puluhan baris diketik "1:56" di sel berformat jam, dan Excel
 *   membacanya satu jam lima puluh enam menit. Loading satu hauler
 *   tidak pernah mendekati satu jam; nilai ≥ 1 jam dengan detik nol
 *   dibaca sebagai menit:detik yang tergeser satu satuan.
 * - N Passing tersimpan apa adanya ('5', '5-6'); Penilaian yang
 *   menafsirkannya.
 * - Bucket heap berupa tanda ✅ / ❌.
 *
 * Baris yang sudah ada — tanggal, shift, unit, operator, dan jam
 * observasi yang sama — dilewati, sehingga berkas yang sama boleh
 * diunggah ulang sesudah ditambah baris baru.
 */
final class Impor
{
    /** Judul kolom Akumulatif Observasi (dinormalkan) → nama field. */
    private const KOLOM = [
        'tanggal'                => 'tanggal',
        'shift'                  => 'shift',
        'cn unit'                => 'unit',
        'operator'               => 'operator',
        'gl front'               => 'gl_front',
        'observer'               => 'observer',
        'kondisi mesin'          => 'kondisi_mesin',
        'mode kerja'             => 'mode_kerja',
        'jenis material'         => 'level',
        'material'               => 'material',
        'metode posisi'          => 'metode_posisi',
        'operating condition'    => 'operating_condition',
        'metode loading'         => 'metode_loading',
        'mto'                    => 'mto',
        'tinggi jenjang (m)'     => 'tinggi_jenjang',
        'lebar front (m)'        => 'lebar_front',
        'spotting (s)'           => 'spotting',
        'digging (s)'            => 'digging',
        'swl (s)'                => 'swl',
        'dump (s)'               => 'dump',
        'swe (s)'                => 'swe',
        'plan ct (s)'            => 'plan_ct',
        'loading time (mm:ss)'   => 'loading',
        'n passing'              => 'n_passing',
        'bucket heap'            => 'bucket_heap',
        'jam observasi'          => 'jam_observasi',
        'target pty (bcm)'       => 'target_pty',
        'aktual pty (bcm)'       => 'aktual_pty',
        'catatan / temuan'       => 'temuan',
        'corrective action'      => 'corrective_action',
        'status ca'              => 'status_ca',
        'verified by'            => 'verified_by',
        'cuaca'                  => 'cuaca',
    ];

    private const KOLOM_COACHING = [
        'tanggal coaching'    => 'tanggal',
        'nama operator'       => 'operator',
        'cn unit'             => 'unit',
        'materi coaching'     => 'materi',
        'respons operator'    => 'respons',
        'pic (coach)'         => 'coach',
        'follow up / action'  => 'follow_up',
        'target selesai'      => 'target_selesai',
        'status follow up'    => 'status',
    ];

    /**
     * @return array{observasi:int,coaching:int,dilewati:int,ditolak:list<string>,sheet:list<string>}
     */
    public static function dariBerkas(string $jalur, ?int $companyId, ?int $userId, ?string $sumber = null): array
    {
        $buku = IOFactory::load($jalur);
        $hasil = ['observasi' => 0, 'coaching' => 0, 'dilewati' => 0, 'ditolak' => [], 'sheet' => []];

        $akum = self::sheet($buku, 'akumulatif observasi');
        if (! $akum) {
            $hasil['ditolak'][] = 'Sheet "Akumulatif Observasi" tidak ditemukan di berkas ini.';
            return $hasil;
        }
        $hasil['sheet'][] = $akum->getTitle();

        foreach (self::barisObservasi($akum, $hasil['ditolak']) as $d) {
            if (self::sudahAda($d, $companyId)) { $hasil['dilewati']++; continue; }

            $d['company_id'] = $companyId;
            $d['user_id']    = $userId;
            $d['sumber']     = $sumber;
            $d['pekerja_id'] = self::cariPekerja($d['operator'], $companyId);

            Observasi::create($d);
            $hasil['observasi']++;
        }

        if ($log = self::sheet($buku, 'coaching log')) {
            $hasil['sheet'][] = $log->getTitle();

            foreach (self::barisCoaching($log, $hasil['ditolak']) as $d) {
                $ada = Coaching::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->whereDate('tanggal', $d['tanggal'])
                    ->where('operator', $d['operator'])
                    ->where('materi', $d['materi'])
                    ->exists();
                if ($ada) { $hasil['dilewati']++; continue; }

                $d['company_id']   = $companyId;
                $d['user_id']      = $userId;
                $d['observasi_id'] = Observasi::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->whereDate('tanggal', $d['tanggal'])
                    ->where('operator', $d['operator'])
                    ->value('id');

                Coaching::create($d);
                $hasil['coaching']++;
            }
        }

        $buku->disconnectWorksheets();

        return $hasil;
    }

    /* ═══════════════ Akumulatif Observasi ═══════════════ */

    /** @return list<array<string,mixed>> */
    public static function barisObservasi(Worksheet $ws, array &$ditolak): array
    {
        [$barisJudul, $kolom] = self::judul($ws, self::KOLOM, ['tanggal', 'operator', 'digging (s)']);
        if (! $barisJudul) {
            $ditolak[] = 'Baris judul Akumulatif Observasi (Tanggal, Operator, Digging) tidak ditemukan.';
            return [];
        }

        $out  = [];
        $maks = $ws->getHighestDataRow();

        for ($r = $barisJudul + 1; $r <= $maks; $r++) {
            $sel = fn (string $f) => isset($kolom[$f]) ? $ws->getCell($kolom[$f].$r) : null;
            $isi = fn (string $f) => self::nilai($sel($f));

            $operator = self::teks($isi('operator'));
            if ($operator === null) continue;              // baris kosong / baris rumus tanpa isi

            $tanggal = self::tanggal($isi('tanggal'));
            $level   = strtolower((string) self::teks($isi('level')));
            $unit    = self::teks($isi('unit'));

            if (! $tanggal || ! isset(Penilaian::PLAN_CT[$level]) || ! $unit) {
                $ditolak[] = "Baris {$r} ({$operator}) dilewati: "
                    .(! $tanggal ? 'tanggal tidak terbaca' : (! $unit ? 'CN Unit kosong' : 'jenis material bukan Easy/Average/Severe'));
                continue;
            }

            $verified = self::teks($isi('verified_by')) ?? self::teks($isi('gl_front'));

            $out[] = [
                'tanggal'             => $tanggal,
                'shift'               => is_numeric($isi('shift')) ? (int) $isi('shift') : null,
                'jam_observasi'       => self::teks($isi('jam_observasi')),
                'unit'                => $unit,
                'operator'            => $operator,
                'gl_front'            => self::teks($isi('gl_front')),
                'observer'            => self::teks($isi('observer')),
                'verified_by'         => $verified,
                'kondisi_mesin'       => self::teks($isi('kondisi_mesin')),
                'mode_kerja'          => self::teks($isi('mode_kerja')),
                'level'               => $level,
                'material'            => self::teks($isi('material')),
                'metode_posisi'       => self::teks($isi('metode_posisi')),
                'operating_condition' => self::teks($isi('operating_condition')),
                'metode_loading'      => self::teks($isi('metode_loading')),
                'mto'                 => self::teks($isi('mto')),
                'tinggi_jenjang'      => self::angka($isi('tinggi_jenjang')),
                'lebar_front'         => self::angka($isi('lebar_front')),
                'spotting'            => self::angka($isi('spotting')),
                'digging'             => self::angka($isi('digging')),
                'swl'                 => self::angka($isi('swl')),
                'dump'                => self::angka($isi('dump')),
                'swe'                 => self::angka($isi('swe')),
                /* Plan CT menurut levelnya, bukan hasil rumus sel W:
                   rumus itu jatuh ke 22 detik bila levelnya salah
                   ketik, dan angka itu bukan patokan material mana pun. */
                'plan_ct'             => Penilaian::planCt($level),
                'loading_detik'       => self::loading($sel('loading')),
                'n_passing'           => self::teks($isi('n_passing')),
                'bucket_heap'         => self::heap($isi('bucket_heap')),
                'target_pty'          => is_numeric($isi('target_pty')) ? (int) round((float) $isi('target_pty')) : null,
                'aktual_pty'          => is_numeric($isi('aktual_pty')) ? (int) round((float) $isi('aktual_pty')) : null,
                'temuan'              => self::teks($isi('temuan')),
                'corrective_action'   => self::teks($isi('corrective_action')),
                'status_ca'           => self::statusCa($isi('status_ca')),
                'cuaca'               => self::teks($isi('cuaca')),
            ];
        }

        return $out;
    }

    /* ═══════════════ Coaching Log ═══════════════ */

    /** @return list<array<string,mixed>> */
    public static function barisCoaching(Worksheet $ws, array &$ditolak): array
    {
        [$barisJudul, $kolom] = self::judul($ws, self::KOLOM_COACHING, ['nama operator', 'materi coaching']);
        if (! $barisJudul) return [];

        $out  = [];
        $maks = $ws->getHighestDataRow();

        for ($r = $barisJudul + 1; $r <= $maks; $r++) {
            $isi = fn (string $f) => isset($kolom[$f]) ? self::nilai($ws->getCell($kolom[$f].$r)) : null;

            $operator = self::teks($isi('operator'));
            $materi   = self::teks($isi('materi'));
            if ($operator === null || $materi === null) continue;

            $tanggal = self::tanggal($isi('tanggal'));
            if (! $tanggal) {
                $ditolak[] = "Coaching Log baris {$r} ({$operator}) dilewati: tanggal tidak terbaca";
                continue;
            }

            $out[] = [
                'tanggal'        => $tanggal,
                'operator'       => $operator,
                'unit'           => self::teks($isi('unit')),
                'materi'         => $materi,
                'respons'        => self::teks($isi('respons')),
                'coach'          => self::teks($isi('coach')),
                'follow_up'      => self::teks($isi('follow_up')),
                'target_selesai' => self::tanggal($isi('target_selesai')),
                'status'         => self::statusCa($isi('status')),
            ];
        }

        return $out;
    }

    /* ═══════════════ penafsir sel ═══════════════ */

    private static function sheet($buku, string $nama): ?Worksheet
    {
        foreach ($buku->getWorksheetIterator() as $ws) {
            if (self::normal($ws->getTitle()) === $nama) return $ws;
        }

        return null;
    }

    /**
     * Cari baris judul (dalam 15 baris pertama) yang memuat seluruh
     * judul wajib, lalu petakan field → huruf kolom.
     *
     * @return array{0:?int,1:array<string,string>}
     */
    private static function judul(Worksheet $ws, array $peta, array $wajib): array
    {
        $kolomMaks = $ws->getHighestDataColumn();

        for ($r = 1; $r <= 15; $r++) {
            $ketemu = [];
            foreach ($ws->getRowIterator($r, $r)->current()->getCellIterator('A', $kolomMaks) as $c) {
                $j = self::normal((string) $c->getValue());
                if ($j !== '' && isset($peta[$j]) && ! isset($ketemu[$peta[$j]])) {
                    $ketemu[$peta[$j]] = $c->getColumn();
                    $ketemu['#'.$j] = true;
                }
            }

            if (count(array_filter($wajib, fn ($w) => isset($ketemu['#'.$w]))) === count($wajib)) {
                return [$r, array_filter($ketemu, fn ($k) => $k[0] !== '#', ARRAY_FILTER_USE_KEY)];
            }
        }

        return [null, []];
    }

    public static function normal(string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', mb_strtolower($s)));
    }

    /** Nilai sel: isian apa adanya, atau hasil tersimpan bila selnya berumus. */
    private static function nilai(?Cell $c): mixed
    {
        if (! $c) return null;

        $v = $c->getValue();
        if (is_string($v) && str_starts_with($v, '=')) return $c->getOldCalculatedValue();

        return $v instanceof \PhpOffice\PhpSpreadsheet\RichText\RichText ? $v->getPlainText() : $v;
    }

    private static function teks(mixed $v): ?string
    {
        if ($v === null) return null;
        $t = trim(preg_replace('/\s+/u', ' ', (string) $v));

        return $t === '' ? null : $t;
    }

    private static function angka(mixed $v): ?float
    {
        if (is_numeric($v)) return (float) $v;
        if (is_string($v) && is_numeric($t = str_replace(',', '.', trim($v)))) return (float) $t;

        return null;
    }

    /** Tanggal dari serial Excel atau teks dd/mm/yy(yy). */
    public static function tanggal(mixed $v): ?string
    {
        if (is_numeric($v) && $v > 20000) {
            return CarbonImmutable::instance(TanggalExcel::excelToDateTimeObject((float) $v))->toDateString();
        }
        if ($v instanceof \DateTimeInterface) return CarbonImmutable::instance($v)->toDateString();

        if (is_string($v) && preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2}|\d{4})$#', trim($v), $m)) {
            $th = strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3];
            if (checkdate((int) $m[2], (int) $m[1], $th)) {
                return sprintf('%04d-%02d-%02d', $th, $m[2], $m[1]);
            }
        }

        return null;
    }

    /**
     * Loading time → detik.
     *
     * Sel durasi tersimpan sebagai pecahan hari. Nilai satu jam atau
     * lebih dengan detik nol adalah "m:ss" yang diketik di sel berformat
     * jam — "1:56" dibaca Excel sebagai 01:56:00 — dan digeser kembali
     * satu satuan menjadi 1 menit 56 detik.
     */
    public static function loading(?Cell $c): ?int
    {
        $v = self::nilai($c);

        if (is_string($v)) return Observasi::teksKeDetik($v);
        if (! is_numeric($v)) return null;

        $v = (float) $v;
        if ($v <= 0) return null;

        /* Angka bulat di atas satu adalah detik yang diketik langsung. */
        if ($v >= 1) return (int) round($v);

        $d = (int) round($v * 86400);

        if ($d >= 3600 && $d % 60 === 0) {
            return intdiv($d, 3600) * 60 + intdiv($d % 3600, 60);
        }

        return $d;
    }

    private static function heap(mixed $v): ?bool
    {
        $t = self::normal((string) $v);

        if ($t === '') return null;
        if (str_contains($t, '✅') || in_array($t, ['ya', 'yes', 'heap', 'ok', '1', 'true'], true)) return true;
        if (str_contains($t, '❌') || in_array($t, ['tidak', 'no', 'x', '0', 'false'], true)) return false;

        return null;
    }

    private static function statusCa(mixed $v): string
    {
        $t = self::normal((string) $v);

        return match (true) {
            str_contains($t, 'close')    => 'Closed',
            str_contains($t, 'progress') => 'In Progress',
            default                      => 'Open',
        };
    }

    private static function sudahAda(array $d, ?int $companyId): bool
    {
        return Observasi::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereDate('tanggal', $d['tanggal'])
            ->where('shift', $d['shift'])
            ->where('unit', $d['unit'])
            ->where('operator', $d['operator'])
            ->where('jam_observasi', $d['jam_observasi'])
            ->exists();
    }

    /** Pekerja Miners bernama sama — hanya bila tepat satu. */
    public static function cariPekerja(string $nama, ?int $companyId): ?int
    {
        $id = Pekerja::withoutGlobalScopes()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->whereRaw('LOWER(TRIM(nama)) = ?', [Penilaian::kunciOrang($nama)])
            ->limit(2)->pluck('id');

        return $id->count() === 1 ? $id->first() : null;
    }
}
