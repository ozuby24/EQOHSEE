<?php

namespace App\Support;

use App\Models\{KoObject, KoPersonnel};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * KO / SPIP — mesin aturan, dipindahkan persis dari Code.gs + Index.html sumber.
 *
 *   kadaluarsa   = tgl_sertifikasi + interval_tahun
 *   status objek = Breakdown             → Dalam Perbaikan
 *                  sisa hari < 0         → Kadaluarsa
 *                  sisa hari ≤ warnDays  → Akan Jatuh Tempo
 *                  selain itu            → Layak
 *   PM terlewat  = pm_berikutnya < hari ini
 *
 * Indeks KO = rerata lima sub-elemen (Kepmen ESDM 1827 K/2018 · Kepdirjen 185.K/2019).
 */
class Ko
{
    public const KATEGORI  = ['Sarana', 'Prasarana', 'Instalasi', 'Peralatan'];
    public const KRITIS    = ['Tinggi', 'Sedang', 'Rendah'];
    public const OPERASI   = ['Aktif', 'Standby', 'Breakdown'];
    public const INTERVAL  = [1, 2, 3, 5, 10];

    public const ST_LAYAK  = 'Layak';
    public const ST_TEMPO  = 'Akan Jatuh Tempo';
    public const ST_EXPIRE = 'Kadaluarsa';
    public const ST_REPAIR = 'Dalam Perbaikan';

    public const STATUS = [self::ST_LAYAK, self::ST_TEMPO, self::ST_EXPIRE, self::ST_REPAIR];

    public const WARNA = [
        self::ST_LAYAK  => '#16A34A',
        self::ST_TEMPO  => '#D97706',
        self::ST_EXPIRE => '#D92D20',
        self::ST_REPAIR => '#667085',
    ];

    public const PENGAMAN_STATUS = ['Berfungsi', 'Perlu Perbaikan', 'Tidak Berfungsi'];
    public const AKSI_STATUS     = ['Terbuka', 'Berjalan', 'Selesai', 'Dibatalkan'];
    public const KAJIAN_STATUS   = ['Berjalan', 'Dilaporkan', 'Selesai'];

    private static ?array $set = null;

    /* ================= pengaturan ================= */

    public static function settings(): array
    {
        if (self::$set !== null) return self::$set;

        $bawaan = [
            'ko_warn_days'    => 90,
            'ko_target_layak' => 95,
            'ko_target_pmc'   => 90,
            'ko_iv_peralatan' => 3,
            'ko_iv_instalasi' => 5,
        ];

        try {
            $rows = DB::table('app_settings')->whereIn('key', array_keys($bawaan))->pluck('value', 'key');
            foreach ($rows as $k => $v) {
                $d = json_decode((string) $v, true);
                if (is_numeric($d)) $bawaan[$k] = (int) $d;
            }
        } catch (\Throwable $e) {
            // tabel belum ada — pakai bawaan
        }

        return self::$set = $bawaan;
    }

    public static function warnDays(): int    { return self::settings()['ko_warn_days']; }
    public static function targetLayak(): int { return self::settings()['ko_target_layak']; }
    public static function targetPmc(): int   { return self::settings()['ko_target_pmc']; }

    public static function simpanSettings(array $baru): void
    {
        foreach ($baru as $k => $v) {
            if (!array_key_exists($k, self::settings())) continue;
            DB::table('app_settings')->updateOrInsert(
                ['key' => $k],
                ['value' => json_encode((int) $v), 'updated_at' => now(), 'created_at' => now()]
            );
        }
        self::$set = null;
    }

    /** Interval bawaan menurut kategori. */
    public static function intervalBawaan(string $kategori): int
    {
        return $kategori === 'Instalasi'
            ? self::settings()['ko_iv_instalasi']
            : self::settings()['ko_iv_peralatan'];
    }

    /* ================= aturan objek ================= */

    public static function kadaluarsa(KoObject $o): ?Carbon
    {
        if (!$o->tgl_sertifikasi) return null;
        return Carbon::parse($o->tgl_sertifikasi)->addYears(max(1, (int) $o->interval_tahun));
    }

    /** Sisa hari sampai kadaluarsa; null bila belum bersertifikat. */
    public static function sisaHari(KoObject $o): ?int
    {
        $x = self::kadaluarsa($o);
        return $x ? (int) round(now()->startOfDay()->diffInDays($x->startOfDay(), false)) : null;
    }

    public static function status(KoObject $o): string
    {
        if ($o->status_operasi === 'Breakdown') return self::ST_REPAIR;

        $d = self::sisaHari($o);
        if ($d === null) return self::ST_EXPIRE;      // belum bersertifikat = tidak laik
        if ($d < 0) return self::ST_EXPIRE;
        if ($d <= self::warnDays()) return self::ST_TEMPO;

        return self::ST_LAYAK;
    }

    public static function pmTerlewat(KoObject $o): bool
    {
        if (!$o->pm_berikutnya) return false;
        return Carbon::parse($o->pm_berikutnya)->startOfDay()->lt(now()->startOfDay());
    }

    public static function sisaPm(KoObject $o): ?int
    {
        if (!$o->pm_berikutnya) return null;
        return (int) round(now()->startOfDay()->diffInDays(Carbon::parse($o->pm_berikutnya)->startOfDay(), false));
    }

    public static function warna(string $status): string
    {
        return self::WARNA[$status] ?? '#667085';
    }

    /* ================= hitungan ringkas ================= */

    /**
     * @param  \Illuminate\Support\Collection<int,KoObject>  $objek  sudah memuat safeguards & reviews
     */
    public static function hitung($objek, $tenaga = null): array
    {
        $total   = max(1, $objek->count());
        $byStat  = array_fill_keys(self::STATUS, 0);
        $overdue = 0;
        $pgTot = 0; $pgOk = 0;
        $kj = 0; $kjLap = 0;

        foreach ($objek as $o) {
            $byStat[self::status($o)]++;
            if (self::pmTerlewat($o)) $overdue++;

            foreach ($o->safeguards ?? [] as $p) {
                $pgTot++;
                if ($p->status === 'Berfungsi') $pgOk++;
            }
            foreach ($o->reviews ?? [] as $r) {
                $kj++;
                if ($r->status === 'Dilaporkan') $kjLap++;
            }
        }

        $tenaga ??= KoPersonnel::all();
        $tnTot = $tenaga->count();
        $tnAktif = $tenaga->filter(
            fn ($t) => $t->tgl_kadaluarsa && Carbon::parse($t->tgl_kadaluarsa)->gte(now()->startOfDay())
        )->count();

        return [
            'total'    => $objek->count(),
            'byStat'   => $byStat,
            'overdue'  => $overdue,
            'pmc'      => (int) round(($objek->count() - $overdue) / $total * 100),
            'pgTot'    => $pgTot,
            'pgOk'     => $pgOk,
            'pgPct'    => $pgTot ? (int) round($pgOk / $pgTot * 100) : 100,
            'kjTot'    => $kj,
            'kjLap'    => $kjLap,
            'kjPct'    => $kj ? (int) round($kjLap / $kj * 100) : 100,
            'tnTot'    => $tnTot,
            'tnAktif'  => $tnAktif,
            'tnPct'    => $tnTot ? (int) round($tnAktif / $tnTot * 100) : 100,
            'layakPct' => (int) round($byStat[self::ST_LAYAK] / $total * 100),
        ];
    }

    /** Lima sub-elemen KO + indeks gabungan. */
    public static function subElemen(array $c): array
    {
        $items = [
            ['n' => 1, 'nama' => 'Pemeliharaan / Perawatan SPIP', 'pct' => $c['pmc'],
             'ket' => ($c['total'] - $c['overdue']) . ' dari ' . $c['total'] . ' objek PM sesuai jadwal'],
            ['n' => 2, 'nama' => 'Pengamanan Instalasi', 'pct' => $c['pgPct'],
             'ket' => $c['pgOk'] . ' dari ' . $c['pgTot'] . ' perangkat pengaman berfungsi'],
            ['n' => 3, 'nama' => 'Kelayakan SPIP', 'pct' => $c['layakPct'],
             'ket' => $c['byStat'][self::ST_LAYAK] . ' dari ' . $c['total'] . ' objek bersertifikat layak'],
            ['n' => 4, 'nama' => 'Kompetensi Tenaga Teknik', 'pct' => $c['tnPct'],
             'ket' => $c['tnAktif'] . ' dari ' . $c['tnTot'] . ' sertifikat kompetensi aktif'],
            ['n' => 5, 'nama' => 'Evaluasi Kajian Teknis', 'pct' => $c['kjPct'],
             'ket' => $c['kjLap'] . ' dari ' . $c['kjTot'] . ' kajian dilaporkan ke KaIT'],
        ];

        $idx = (int) round(array_sum(array_column($items, 'pct')) / count($items));

        return ['items' => $items, 'indeks' => $idx, 'level' => self::level($idx)];
    }

    public static function level(int $pct): string
    {
        if ($pct >= 90) return 'Sangat Baik';
        if ($pct >= 80) return 'Baik';
        if ($pct >= 70) return 'Cukup';
        return 'Perlu Perbaikan';
    }

    public static function warnaPersen(int $pct): string
    {
        if ($pct >= 90) return '#16A34A';
        if ($pct >= 80) return '#0B6E99';
        if ($pct >= 70) return '#D97706';
        return '#D92D20';
    }

    /* ================= peringatan ================= */

    /**
     * Peringatan terurut prioritas — bahan tindak lanjut.
     * pr 0 kadaluarsa · 1 jatuh tempo & pengaman rusak · 2 PM terlewat
     */
    public static function peringatan($objek): array
    {
        $out = [];

        foreach ($objek as $o) {
            $st = self::status($o);
            $d  = self::sisaHari($o);

            if ($st === self::ST_EXPIRE) {
                $out[] = ['pr' => 0, 'objek' => $o, 'sumber' => 'Sertifikat',
                          'jenis' => 'Sertifikat kadaluarsa', 'hari' => $d,
                          'ket' => $o->kode . ' · ' . ($o->lokasi ?: '—')];
            } elseif ($st === self::ST_TEMPO) {
                $out[] = ['pr' => 1, 'objek' => $o, 'sumber' => 'Sertifikat',
                          'jenis' => 'Sertifikat akan jatuh tempo', 'hari' => $d,
                          'ket' => $o->kode . ' · ' . ($o->lokasi ?: '—')];
            }

            if (self::pmTerlewat($o) && $o->status_operasi !== 'Breakdown') {
                $out[] = ['pr' => 2, 'objek' => $o, 'sumber' => 'Perawatan',
                          'jenis' => 'Perawatan terlewat jadwal', 'hari' => self::sisaPm($o),
                          'ket' => $o->kode . ' · ' . ($o->pm_jenis ?: 'PM')];
            }

            foreach ($o->safeguards ?? [] as $p) {
                if ($p->status !== 'Berfungsi') {
                    $out[] = ['pr' => 1, 'objek' => $o, 'pengaman' => $p, 'sumber' => 'Pengaman',
                              'jenis' => 'Pengaman ' . strtolower($p->status), 'hari' => null,
                              'ket' => $o->kode . ' · ' . $p->nama];
                }
            }
        }

        usort($out, fn ($a, $b) => [$a['pr'], $a['hari'] ?? 999] <=> [$b['pr'], $b['hari'] ?? 999]);

        return $out;
    }
}
