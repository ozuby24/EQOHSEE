<?php

namespace App\Support;

use App\Models\{SmkpAudit, SmkpBukti, SmkpFinding};
use Illuminate\Support\Carbon;

/**
 * Data Dasbor Audit SMKP (desain v2, mengikuti dasbor DBEST).
 *
 * KPI periode, benchmark nilai akhir per perusahaan beserta perubahan
 * terhadap tahun pembanding pilihan, capaian 7 elemen berbobot Kepdirjen
 * 185.K (nilai akhir resmi = Smkp::rekap), temuan per elemen & kategori,
 * tindakan mendesak, analisis temuan & tindak lanjut (Top 10 sub-elemen,
 * status, tren dibuat vs ditutup per bulan/kuartal/tahun), agenda 30 hari,
 * dan sorotan otomatis.
 *
 * Dihitung murni dari koleksi yang diberikan pemanggil — yang sudah
 * tersaring batas perusahaan lewat Scopes\JangkauanAudit — sehingga dapat
 * diuji tanpa menyusun permintaan HTTP, dan tidak pernah membuka audit
 * perusahaan yang tidak boleh dilihat penggunanya.
 *
 * Teks sorotan dikembalikan sebagai ruas [['t' => ..] | ['b' => ..]] supaya
 * halaman menampilkannya sebagai teks (ter-escape), bukan HTML.
 */
class SmkpDasbor
{
    /** Ambang tingkat penerapan Kepdirjen 185.K (sama dengan Smkp::tingkat). */
    public const TARGET = 85;
    public const BATAS  = 60;

    /** Empat babak alur audit. */
    public const LANGKAH = [1 => 'Tahap I', 2 => 'Rencana Audit', 3 => 'Audit Lapangan', 4 => 'Pelaporan'];

    public const BULAN = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /**
     * @param iterable<SmkpAudit>   $audits     audit periode
     * @param iterable<SmkpAudit>   $pembanding audit tahun pembanding
     * @param iterable<SmkpFinding> $temuan     temuan audit periode
     * @param iterable<SmkpBukti>   $bukti      bukti audit periode
     * @param array<int, float|null> $tren      [tahun => rata-rata nilai akhir|null]
     * @param array{audit: iterable<SmkpAudit>, temuan: iterable<SmkpFinding>}|null $lintas audit & temuan lima tahun untuk tren tahunan
     */
    public static function susun(int $tahun, ?int $banding, iterable $audits, iterable $pembanding, iterable $temuan, iterable $bukti, array $tren, string $hariIni, ?array $lintas = null): array
    {
        $audits = collect($audits)->values();
        $temuan = collect($temuan)->values();
        $bukti  = collect($bukti)->values();
        $elemen = self::elemen();

        $temuanAudit = $temuan->groupBy(fn ($t) => (int) $t->audit_id);
        $buktiAudit  = $bukti->groupBy(fn ($b) => (int) $b->audit_id);

        // Nilai tahun pembanding per perusahaan (audit yang sudah dinilai).
        $lalu = [];
        $nilaiBanding = [];
        $elBanding = array_fill(0, count($elemen), []);
        foreach ($pembanding as $a) {
            $r = $a->rekap();
            if ($r['dinilai'] <= 0) continue;
            // Dibulatkan 1 desimal seperti nilai yang tampil, agar rata-rata dapat dicocokkan dengan tabel.
            $lalu[self::kunci($a)] = round((float) $r['skor'], 1);
            $nilaiBanding[] = round((float) $r['skor'], 1);
            foreach ($elemen as $i => $e) {
                $re = $r['elemen'][$e['k']] ?? null;
                if ($re && $re['berlaku'] > 0) $elBanding[$i][] = round($re['capaian'] * 100, 1);
            }
        }

        $pt = [];
        foreach ($audits as $a) {
            $pt[] = self::perusahaan($a, $temuanAudit->get((int) $a->id, collect())->all(), $buktiAudit->get((int) $a->id, collect())->all(), $lalu, $elemen);
        }
        $dinilai = array_values(array_filter($pt, fn ($p) => $p['skor'] !== null));

        $rata = $dinilai ? self::rata(array_column($dinilai, 'skor')) : null;
        $rataBanding = $nilaiBanding ? self::rata($nilaiBanding) : null;
        $elRata = [];
        $elRataBanding = [];
        foreach ($elemen as $i => $e) {
            $v = array_values(array_filter(array_map(fn ($p) => $p['el'][$i], $dinilai), fn ($x) => $x !== null));
            $elRata[] = $v ? round(self::rata($v), 1) : null;
            $elRataBanding[] = $elBanding[$i] ? round(self::rata($elBanding[$i]), 1) : null;
        }

        $sebar = ['Baik' => 0, 'Perlu Perbaikan' => 0, 'Perlu Perhatian Serius' => 0, 'Belum dinilai' => 0];
        $langkah = [1 => 0, 2 => 0, 3 => 0, 4 => 0];
        foreach ($pt as $p) {
            $sebar[$p['tingkat']] = ($sebar[$p['tingkat']] ?? 0) + 1;
            $langkah[$p['langkah']]++;
        }

        $kpiBukti = ['total' => 0, SmkpBukti::DITERIMA => 0, SmkpBukti::MENUNGGU => 0, SmkpBukti::DITOLAK => 0];
        foreach ($bukti as $b) {
            $kpiBukti['total']++;
            if (isset($kpiBukti[(string) $b->status])) $kpiBukti[(string) $b->status]++;
        }

        $hasil = [
            'tahun'      => $tahun,
            'banding'    => $banding,
            'target'     => self::TARGET,
            'batas'      => self::BATAS,
            'elemen'     => $elemen,
            'poinTotal'  => array_sum(array_column($elemen, 'poin')),
            'kategori'   => SmkpFinding::KATEGORI,
            'perusahaan' => $pt,
            'kpi'        => [
                'audit'       => count($pt),
                'langkah'     => $langkah,
                'dinilai'     => count($dinilai),
                'rata'        => $rata !== null ? round($rata, 1) : null,
                'rataBanding' => $rataBanding !== null ? round($rataBanding, 1) : null,
                'temuan'      => self::kpiTemuan($temuan, $hariIni),
                'bukti'       => $kpiBukti,
            ],
            'sebar'         => $sebar,
            'elRata'        => $elRata,
            'elRataBanding' => $elRataBanding,
            'temuanElemen'  => self::temuanElemen($temuan, $elemen),
            'analisis'      => self::analisis($tahun, $audits, $temuan, $lintas ?: ['audit' => $audits, 'temuan' => $temuan], $hariIni),
            'tindakan'      => self::tindakan($temuan, $audits, $hariIni),
            'agenda'        => self::agenda($audits, $temuan, $hariIni),
            'tren'          => ['label' => array_map('strval', array_keys($tren)), 'nilai' => array_values($tren)],
        ];
        $hasil['sorotan'] = self::sorotan($hasil);

        return $hasil;
    }

    /** Tujuh elemen: kode, nama, bobot nilai akhir (%), dan poin maksimal butirnya. */
    public static function elemen(): array
    {
        $out = [];
        foreach (Smkp::elemen() as $e) {
            $r = Smkp::rekapElemen($e, []);
            $out[] = ['k' => $e['kode'], 'n' => $e['nama'], 'bobot' => (int) ($e['bobot'] ?? 0), 'poin' => (int) $r['maksPenuh']];
        }

        return $out;
    }

    /** Babak alur audit 1..4. */
    public static function langkah(SmkpAudit $a): int
    {
        if ($a->status === 'selesai' || (int) $a->tahap >= SmkpTahap::PELAPORAN) return 4;
        if ((int) $a->tahap === SmkpTahap::LAPANGAN) return 3;

        return $a->rekapRencana()['jumlah'] > 0 ? 2 : 1;
    }

    /** Kunci perusahaan lintas tahun: perusahaan yang sama dibandingkan dengan dirinya sendiri. */
    private static function kunci(SmkpAudit $a): string
    {
        return (string) (int) $a->company_id;
    }

    private static function namaPerusahaan(SmkpAudit $a): string
    {
        return (string) ($a->company?->name ?? $a->namaTampil());
    }

    private static function jasa(SmkpAudit $a): bool
    {
        return $a->company?->kategori === 'pjp';
    }

    private static function tanggalPendek($tg): ?string
    {
        if (!$tg) return null;

        return Carbon::parse($tg)->translatedFormat('d M Y');
    }

    private static function perusahaan(SmkpAudit $a, array $temuan, array $bukti, array $lalu, array $elemen): array
    {
        $r = $a->rekap();
        $dinilai = $r['dinilai'] > 0;
        $el = [];
        $elPoin = [];
        foreach ($elemen as $e) {
            $re = $r['elemen'][$e['k']] ?? null;
            $ada = $dinilai && $re && $re['berlaku'] > 0;
            $el[] = $ada ? round($re['capaian'] * 100, 1) : null;
            $elPoin[] = $ada ? [round((float) $re['nilai'], 1), (int) $re['maks']] : null;
        }

        $buka = 0;
        $telat = 0;
        foreach ($temuan as $t) {
            if ($t->status !== 'Closed') $buka++;
            if ($t->terlambat()) $telat++;
        }
        $diterima = 0;
        $menunggu = 0;
        foreach ($bukti as $b) {
            if ($b->status === SmkpBukti::DITERIMA) $diterima++;
            elseif ($b->status === SmkpBukti::MENUNGGU) $menunggu++;
        }

        $langkah = self::langkah($a);
        $lead = '';
        foreach ($a->tim() as $x) {
            if (preg_match('/ketua|lead/i', (string) ($x['peran'] ?? '')) && trim((string) ($x['nama'] ?? '')) !== '') {
                $lead = trim((string) $x['nama']);
                break;
            }
        }
        $kunci = self::kunci($a);
        $jasa = self::jasa($a);

        return [
            'id'       => (int) $a->id,
            'nama'     => self::namaPerusahaan($a),
            'judul'    => $a->namaTampil(),
            'kat'      => $jasa ? 'IUJP' : 'IUP',
            'jenis'    => $jasa ? 'Mitra kerja / IUJP' : 'Pemegang IUP / Utama',
            'status'   => (string) $a->status,
            'langkah'  => $langkah,
            'babak'    => self::LANGKAH[$langkah],
            'skor'     => $dinilai ? round((float) $r['skor'], 1) : null,
            'tingkat'  => $dinilai ? $r['tingkat']['label'] : 'Belum dinilai',
            'poin'     => round((float) $r['nilai'], 1),
            'maks'     => (int) $r['maks'],
            'dinilaiButir' => (int) $r['dinilai'],
            'berlaku'  => (int) $r['berlaku'],
            'el'       => $el,
            'elPoin'   => $elPoin,
            'lalu'     => isset($lalu[$kunci]) ? round($lalu[$kunci], 1) : null,
            'temuan'   => count($temuan),
            'buka'     => $buka,
            'telat'    => $telat,
            'bukti'    => count($bukti),
            'buktiTerima' => $diterima,
            'buktiTunggu' => $menunggu,
            'lead'     => $lead !== '' ? $lead : trim((string) $a->ketua_auditor),
            'lanjut'   => self::lanjut($a, $langkah, $r, $buka, $telat, $menunggu),
            'url'      => [
                'ringkasan' => route('smkp.show', $a->id),
                'nilai'     => route('smkp.nilai', [$a->id, 'I']),
                'temuan'    => route('smkp.temuan', $a->id),
                'laporan'   => route('smkp.laporan', $a->id),
                'evaluasi'  => route('smkp.evaluasi', $a->id),
            ],
        ];
    }

    /** Langkah berikutnya satu audit: ['t' => teks, 'nada' => ''|'merah']. */
    private static function lanjut(SmkpAudit $a, int $langkah, array $r, int $buka, int $telat, int $menunggu): array
    {
        if ($langkah === 4) {
            if ($telat) return ['t' => $telat.' tindak lanjut lewat target', 'nada' => 'merah'];

            return ['t' => $buka ? 'Tindak lanjut '.$buka.' temuan terbuka' : 'Selesai · semua temuan ditutup', 'nada' => ''];
        }
        if ($langkah === 3) {
            if ($r['dinilai'] < $r['berlaku']) return ['t' => 'Penilaian '.$r['dinilai'].'/'.$r['berlaku'].' butir', 'nada' => ''];
            if ($menunggu) return ['t' => 'Verifikasi '.$menunggu.' bukti', 'nada' => ''];

            return ['t' => $telat ? $telat.' tindak lanjut lewat target' : 'Rapat penutupan & laporan', 'nada' => $telat ? 'merah' : ''];
        }
        if ($langkah === 2) {
            $rr = $a->rekapRencana();
            if (!$rr['lengkap']) return ['t' => 'Lengkapi Rencana Audit '.$rr['jumlah'].'/'.$rr['total'].' komponen', 'nada' => ''];
            $mulai = self::tanggalPendek($a->tanggal_mulai);

            return ['t' => 'Mulai audit lapangan'.($mulai ? ' '.$mulai : ''), 'nada' => ''];
        }

        return ['t' => 'Lengkapi Tahap I & susun Rencana Audit', 'nada' => ''];
    }

    private static function kpiTemuan($temuan, string $hariIni): array
    {
        $k = ['total' => 0, 'buka' => 0, 'tutup' => 0, 'telat' => 0, 'bukaJenis' => array_fill_keys(array_keys(SmkpFinding::KATEGORI), 0), 'hariTutup' => null];
        $hari = [];
        foreach ($temuan as $t) {
            $k['total']++;
            if ($t->status === 'Closed') {
                $k['tutup']++;
                if (($d = self::hariTutup($t)) !== null) $hari[] = $d;
                continue;
            }
            $k['buka']++;
            if (isset($k['bukaJenis'][(string) $t->jenis])) $k['bukaJenis'][(string) $t->jenis]++;
            if ($t->terlambat()) $k['telat']++;
        }
        $k['persenTutup'] = $k['total'] ? (int) round($k['tutup'] / $k['total'] * 100) : 0;
        $k['hariTutup'] = $hari ? (int) round(self::rata($hari)) : null;

        return $k;
    }

    /** Lama penutupan temuan (hari) dari dibuat sampai tanggal selesai. */
    private static function hariTutup(SmkpFinding $t): ?int
    {
        $mulai = $t->created_at ? Carbon::parse($t->created_at)->startOfDay() : null;
        $akhir = $t->tanggal_selesai ? Carbon::parse($t->tanggal_selesai)->startOfDay() : null;
        if (!$mulai || !$akhir) return null;

        return max(0, (int) round($mulai->diffInDays($akhir, false)));
    }

    /** Jumlah temuan per elemen (I..VII) per kategori. */
    private static function temuanElemen($temuan, array $elemen): array
    {
        $idx = [];
        foreach ($elemen as $i => $e) $idx[$e['k']] = $i;
        $out = [];
        foreach (array_keys(SmkpFinding::KATEGORI) as $j) $out[$j] = array_fill(0, count($elemen), 0);
        foreach ($temuan as $t) {
            $el = explode('.', (string) $t->kode_kriteria)[0];
            $j = (string) $t->jenis;
            if (isset($idx[$el], $out[$j])) $out[$j][$idx[$el]]++;
        }

        return $out;
    }

    /**
     * Analisis temuan untuk seluruh perusahaan ('semua') dan tiap audit
     * periode (kunci = id audit): Top 10 sub-elemen bertemuan terbanyak,
     * status tindak lanjut (selesai / proses / terlambat), dan tren temuan
     * dibuat vs ditutup + tingkat penyelesaian per bulan, kuartal, dan tahun.
     */
    private static function analisis(int $tahun, $audits, $temuan, array $lintas, string $hariIni): array
    {
        $subNama = [];
        foreach (Smkp::elemen() as $e) {
            foreach ($e['sub'] as $s) $subNama[$s['kode']] = $s['nama'];
        }
        $kunciAudit = [];
        $pilihan = [['k' => 'semua', 'n' => 'Seluruh perusahaan']];
        foreach ($audits as $a) {
            $kunciAudit[(int) $a->id] = self::kunci($a);
            $pilihan[] = ['k' => (string) $a->id, 'n' => self::namaPerusahaan($a)];
        }
        $per = ['semua' => []];
        foreach ($temuan as $t) {
            $per['semua'][] = $t;
            $per[(string) $t->audit_id][] = $t;
        }
        $tahunAudit = [];
        $kunciLintas = [];
        foreach ($lintas['audit'] as $a) {
            $tahunAudit[(int) $a->id] = (int) $a->tahun;
            $kunciLintas[(int) $a->id] = self::kunci($a);
        }
        $daftarTahun = range($tahun - 4, $tahun);
        $lintasPer = ['semua' => []];
        foreach ($lintas['temuan'] as $t) {
            $id = (int) $t->audit_id;
            if (!isset($tahunAudit[$id])) continue;
            $lintasPer['semua'][] = $t;
            foreach ($kunciAudit as $idPeriode => $k) {
                if ($kunciLintas[$id] === $k) $lintasPer[(string) $idPeriode][] = $t;
            }
        }

        $out = ['pilihan' => $pilihan, 'top' => [], 'status' => [], 'tren' => []];
        foreach (array_column($pilihan, 'k') as $k) {
            $kol = $per[$k] ?? [];
            $out['top'][$k] = self::topTemuan($kol, $subNama);
            $out['status'][$k] = self::statusTl($kol);
            $out['tren'][$k] = self::trenTl($kol, $lintasPer[$k] ?? [], $tahunAudit, $tahun, $daftarTahun, $hariIni);
        }

        return $out;
    }

    /** Sepuluh sub-elemen dengan temuan terbanyak (seri keparahan: kritikal, mayor). */
    private static function topTemuan(array $temuan, array $subNama): array
    {
        $g = [];
        foreach ($temuan as $t) {
            $p = explode('.', (string) $t->kode_kriteria);
            if (count($p) < 2) continue;
            $k = $p[0].'.'.$p[1];
            $g[$k] ??= ['k' => $k, 'n' => $subNama[$k] ?? $k, 'total' => 0, 'kritikal' => 0, 'mayor' => 0, 'minor' => 0, 'buka' => 0];
            $g[$k]['total']++;
            if (isset($g[$k][(string) $t->jenis])) $g[$k][(string) $t->jenis]++;
            if ($t->status !== 'Closed') $g[$k]['buka']++;
        }
        $g = array_values($g);
        usort($g, fn ($x, $y) => [$y['total'], $y['kritikal'], $y['mayor'], $x['k']] <=> [$x['total'], $x['kritikal'], $x['mayor'], $y['k']]);

        return array_slice($g, 0, 10);
    }

    /** Status tindak lanjut: selesai (Closed), terlambat (lewat target), proses (sisanya). */
    private static function statusTl(array $temuan): array
    {
        $s = ['selesai' => 0, 'proses' => 0, 'telat' => 0];
        foreach ($temuan as $t) {
            if ($t->status === 'Closed') $s['selesai']++;
            elseif ($t->terlambat()) $s['telat']++;
            else $s['proses']++;
        }

        return $s;
    }

    /**
     * Tren temuan dibuat vs ditutup dan tingkat penyelesaian (%): bulan &
     * kuartal pada periode (menurut tanggal dibuat/ditutup; tingkat =
     * kumulatif ditutup / kumulatif dibuat), tahun = per tahun audit
     * (tingkat = ditutup / temuan tahun itu).
     */
    private static function trenTl(array $temuan, array $lintas, array $tahunAudit, int $tahun, array $daftarTahun, string $hariIni): array
    {
        $akhir = (int) substr($hariIni, 0, 4) === $tahun ? (int) substr($hariIni, 5, 2) : 12;
        // Tanggal sebelum periode masuk Januari; sesudah bulan terakhir masuk bulan terakhir.
        $bulanKe = function ($tanggal) use ($tahun, $akhir) {
            if (!$tanggal) return null;
            $tg = $tanggal instanceof \DateTimeInterface ? $tanggal->format('Y-m-d') : substr((string) $tanggal, 0, 10);
            if (!preg_match('/^(\d{4})-(\d{2})/', $tg, $m)) return null;
            if ((int) $m[1] < $tahun) return 0;

            return (int) $m[1] > $tahun ? $akhir - 1 : min((int) $m[2] - 1, $akhir - 1);
        };
        $dibuat = array_fill(0, 12, 0);
        $ditutup = array_fill(0, 12, 0);
        foreach ($temuan as $t) {
            if (($b = $bulanKe($t->created_at)) !== null) $dibuat[$b]++;
            if ($t->status === 'Closed' && ($b = $bulanKe($t->tanggal_selesai ?: $t->updated_at)) !== null) $ditutup[$b]++;
        }
        $rasio = function (array $buat, array $tutup) {
            $out = [];
            $sb = 0;
            $st = 0;
            foreach ($buat as $i => $v) {
                $sb += $v;
                $st += $tutup[$i];
                $out[] = $sb ? (int) round(min(1, $st / $sb) * 100) : null;
            }

            return $out;
        };
        $bulan = ['label' => array_slice(self::BULAN, 0, $akhir), 'dibuat' => array_slice($dibuat, 0, $akhir), 'ditutup' => array_slice($ditutup, 0, $akhir)];
        $bulan['rasio'] = $rasio($bulan['dibuat'], $bulan['ditutup']);

        $kAkhir = (int) ceil($akhir / 3);
        $kuartal = ['label' => [], 'dibuat' => [], 'ditutup' => []];
        for ($q = 0; $q < $kAkhir; $q++) {
            $kuartal['label'][] = 'K'.($q + 1).' '.$tahun;
            $kuartal['dibuat'][] = array_sum(array_slice($dibuat, $q * 3, 3));
            $kuartal['ditutup'][] = array_sum(array_slice($ditutup, $q * 3, 3));
        }
        $kuartal['rasio'] = $rasio($kuartal['dibuat'], $kuartal['ditutup']);

        $th = ['label' => array_map('strval', $daftarTahun), 'dibuat' => array_fill(0, count($daftarTahun), 0), 'ditutup' => array_fill(0, count($daftarTahun), 0), 'rasio' => []];
        $idx = array_flip($daftarTahun);
        foreach ($lintas as $t) {
            $y = $tahunAudit[(int) $t->audit_id] ?? null;
            if ($y === null || !isset($idx[$y])) continue;
            $th['dibuat'][$idx[$y]]++;
            if ($t->status === 'Closed') $th['ditutup'][$idx[$y]]++;
        }
        foreach ($th['dibuat'] as $i => $v) $th['rasio'][] = $v ? (int) round($th['ditutup'][$i] / $v * 100) : null;

        return ['bulan' => $bulan, 'kuartal' => $kuartal, 'tahun' => $th];
    }

    /** Tiga temuan lewat target (terlama dulu) dan tiga bertenggat <= 7 hari (terdekat dulu), beserta jumlah seluruhnya. */
    private static function tindakan($temuan, $audits, string $hariIni): array
    {
        $nama = [];
        foreach ($audits as $a) $nama[(int) $a->id] = self::namaPerusahaan($a);
        $subNama = [];
        foreach (Smkp::elemen() as $e) {
            $subNama[$e['kode']] = $e['nama'];
            foreach ($e['sub'] as $s) $subNama[$s['kode']] = $s['nama'];
        }

        $telat = [];
        $tenggat = [];
        $hari0 = Carbon::parse($hariIni)->startOfDay();
        foreach ($temuan as $t) {
            if ($t->status === 'Closed' || !$t->target_selesai) continue;
            $tg = Carbon::parse($t->target_selesai)->startOfDay();
            $sel = (int) round($hari0->diffInDays($tg, false));
            $kode = (string) $t->kode_kriteria;
            $p = explode('.', $kode);
            $baris = [
                'nomor'    => trim((string) $t->nomor) !== '' ? (string) $t->nomor : '#'.$t->id,
                'kode'     => $kode,
                'kriteria' => $subNama[count($p) >= 2 ? $p[0].'.'.$p[1] : $p[0]] ?? '',
                'pt'       => $nama[(int) $t->audit_id] ?? '',
                'batas'    => self::tanggalPendek($tg),
                'jenis'    => SmkpFinding::KATEGORI[$t->jenis] ?? (string) $t->jenis,
                'jenisK'   => (string) $t->jenis,
                'hari'     => abs($sel),
                'url'      => route('smkp.temuan', $t->audit_id),
            ];
            if ($sel < 0) $telat[] = $baris;
            elseif ($sel <= 7) $tenggat[] = $baris;
        }
        usort($telat, fn ($x, $y) => $y['hari'] <=> $x['hari']);
        usort($tenggat, fn ($x, $y) => $x['hari'] <=> $y['hari']);

        return [
            'telat' => array_slice($telat, 0, 3), 'jumlahTelat' => count($telat),
            'tenggat' => array_slice($tenggat, 0, 3), 'jumlahTenggat' => count($tenggat),
            // Perusahaan dengan temuan lewat target, terbanyak dulu (untuk sorotan).
            'ptTelat' => self::urutJumlah(array_column($telat, 'pt')),
        ];
    }

    /** Agenda 30 hari: mulai & selesai audit, serta batas tindak lanjut (dikelompokkan per audit & tanggal). */
    private static function agenda($audits, $temuan, string $hariIni): array
    {
        $awal = $hariIni;
        $akhir = Carbon::parse($hariIni)->addDays(30)->toDateString();
        $dalam = fn (string $tg) => $tg !== '' && $tg >= $awal && $tg <= $akhir;
        $tgl = fn ($v) => $v ? Carbon::parse($v)->toDateString() : '';

        $out = [];
        $nama = [];
        foreach ($audits as $a) {
            $nama[(int) $a->id] = self::namaPerusahaan($a);
            $mulai = $tgl($a->tanggal_mulai);
            $selesai = $tgl($a->tanggal_selesai);
            if ($dalam($mulai)) {
                $out[] = ['tg' => $mulai, 'judul' => 'Rapat pembukaan & audit lapangan', 'pt' => $nama[(int) $a->id], 'label' => 'Lapangan', 'nada' => 'teal', 'url' => route('smkp.show', $a->id)];
            }
            if ($dalam($selesai) && $selesai !== $mulai) {
                $out[] = ['tg' => $selesai, 'judul' => 'Rapat penutupan', 'pt' => $nama[(int) $a->id], 'label' => 'Lapangan', 'nada' => 'teal', 'url' => route('smkp.show', $a->id)];
            }
        }
        $batas = [];
        foreach ($temuan as $t) {
            if ($t->status === 'Closed' || !$t->target_selesai) continue;
            $tg = $tgl($t->target_selesai);
            if ($dalam($tg)) {
                $k = $t->audit_id.'|'.$tg;
                $batas[$k] = ($batas[$k] ?? 0) + 1;
            }
        }
        foreach ($batas as $k => $n) {
            [$id, $tg] = explode('|', $k);
            $out[] = ['tg' => $tg, 'judul' => 'Batas tindak lanjut '.$n.' temuan', 'pt' => $nama[(int) $id] ?? '', 'label' => 'Tenggat', 'nada' => 'perb', 'url' => route('smkp.temuan', (int) $id)];
        }
        usort($out, fn ($x, $y) => strcmp($x['tg'], $y['tg']));
        foreach ($out as &$o) {
            $o['hari'] = (int) substr($o['tg'], 8, 2);
            $o['bulan'] = self::BULAN[(int) substr($o['tg'], 5, 2) - 1];
        }
        unset($o);

        return array_slice($out, 0, 5);
    }

    /** Sorotan otomatis: [['ikon' => 'ok'|'info'|'awas', 'ruas' => [['t'=>..]|['b'=>..]]], ..]. */
    private static function sorotan(array $d): array
    {
        $out = [];
        $k = $d['kpi'];
        $f = fn ($v) => number_format((float) $v, 1, ',', '.');

        if ($k['rata'] !== null) {
            $ruas = [['t' => 'Rata-rata penerapan '], ['b' => $f($k['rata']).'%']];
            if ($k['rataBanding'] !== null) {
                $dl = $k['rata'] - $k['rataBanding'];
                $ruas[] = ['t' => ' ('.($dl >= 0 ? '+' : '−').$f(abs($dl)).' p.p vs '.$d['banding'].')'];
            }
            if ($k['rata'] < self::TARGET) {
                $ruas[] = ['t' => ', masih terpaut '];
                $ruas[] = ['b' => $f(self::TARGET - $k['rata']).' p.p'];
                $ruas[] = ['t' => ' dari target kolektif Baik (85%).'];
            } else {
                $ruas[] = ['t' => ', sudah memenuhi target kolektif Baik (85%).'];
            }
            $out[] = ['ikon' => $k['rata'] >= self::TARGET ? 'ok' : 'info', 'ruas' => $ruas];
        }

        $tm = $k['temuan'];
        if ($tm['total']) {
            $ruas = [['t' => 'Tindak lanjut: '], ['b' => $tm['persenTutup'].'%'], ['t' => ' temuan ditutup ('.$tm['tutup'].' dari '.$tm['total'].')']];
            if ($tm['hariTutup'] !== null) $ruas[] = ['t' => ', rata-rata '.$tm['hariTutup'].' hari per temuan'];
            $ruas[] = ['t' => '.'];
            $out[] = ['ikon' => $tm['persenTutup'] >= 50 ? 'ok' : 'info', 'ruas' => $ruas];
        }

        // Elemen dengan capaian rata-rata terendah, beserta bobot dan temuannya.
        $min = null;
        foreach ($d['elRata'] as $i => $v) {
            if ($v !== null && ($min === null || $v < $d['elRata'][$min])) $min = $i;
        }
        if ($min !== null) {
            $e = $d['elemen'][$min];
            $jml = 0;
            foreach ($d['temuanElemen'] as $baris) $jml += $baris[$min];
            $krit = $d['temuanElemen']['kritikal'][$min] ?? 0;
            $out[] = ['ikon' => $d['elRata'][$min] < self::TARGET ? 'awas' : 'ok', 'ruas' => [
                ['t' => 'Fokus prioritas: '], ['b' => 'Elemen '.$e['k'].'. '.$e['n'].' (bobot '.$e['bobot'].'%)'],
                ['t' => ' — rata-rata '.$f($d['elRata'][$min]).'%, '.$jml.' temuan'.($krit ? ' ('.$krit.' kritikal)' : '').'.'],
            ]];
        }

        // Penurunan terbesar terhadap tahun pembanding.
        $turun = null;
        foreach ($d['perusahaan'] as $p) {
            if ($p['skor'] !== null && $p['lalu'] !== null && $p['skor'] < $p['lalu']
                && ($turun === null || $p['skor'] - $p['lalu'] < $turun['skor'] - $turun['lalu'])) {
                $turun = $p;
            }
        }
        if ($turun) {
            $out[] = ['ikon' => 'awas', 'ruas' => [['b' => $turun['nama']], ['t' => ' turun '.$f($turun['lalu'] - $turun['skor']).' p.p dari '.$d['banding'].' ke '.$f($turun['skor']).'% — '.$turun['tingkat'].'.']]];
        }

        if ($tm['telat']) {
            $daftar = $d['tindakan']['ptTelat'];
            $out[] = ['ikon' => 'awas', 'ruas' => [
                ['b' => $tm['telat'].' tindak lanjut lewat target'],
                ['t' => $daftar ? ' ('.implode(', ', array_slice($daftar, 0, 2)).(count($daftar) > 2 ? ', …' : '').')' : ''],
                ['t' => ' — kirim pengingat dari menu Tindak Lanjut Temuan.'],
            ]];
        }

        return array_slice($out, 0, 4);
    }

    /** Nilai unik diurut dari yang paling sering muncul (seri: urutan pertama muncul). */
    private static function urutJumlah(array $v): array
    {
        $n = [];
        foreach ($v as $x) {
            if ((string) $x !== '') $n[$x] = ($n[$x] ?? 0) + 1;
        }
        $urut = array_keys($n);
        $pos = array_flip($urut);
        usort($urut, fn ($a, $b) => [$n[$b], $pos[$a]] <=> [$n[$a], $pos[$b]]);

        return $urut;
    }

    private static function rata(array $v): float
    {
        return $v ? array_sum($v) / count($v) : 0.0;
    }
}
