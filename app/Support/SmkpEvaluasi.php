<?php

namespace App\Support;

use App\Models\{SmkpAudit, SmkpFinding};
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Evaluasi satu audit SMKP: capaian elemen & sub elemen (dibandingkan
 * dengan audit tahun lalu perusahaan yang sama), peta panas sub elemen,
 * celah poin terbesar, sebaran kategori, temuan & tindak lanjut, kinerja
 * keselamatan terhadap rata-rata nasional Surat Edaran, hasil penilaian
 * yang tidak sejalan antar elemen terkait, dan catatan evaluasi otomatis.
 *
 * Dihitung murni dari data audit yang sudah diinput — tidak ada angka
 * yang diketik ulang. Teks evaluasi dikembalikan sebagai ruas
 * [['t' => ..] | ['b' => ..]] supaya halaman menampilkannya ter-escape.
 */
class SmkpEvaluasi
{
    public static function susun(SmkpAudit $a, ?SmkpAudit $lalu, Collection $temuan, string $hariIni): array
    {
        $hasil = (array) ($a->hasil ?? []);
        $rekap = Smkp::rekap($hasil);
        $rLalu = $lalu ? Smkp::rekap((array) ($lalu->hasil ?? [])) : null;
        if ($rLalu && $rLalu['dinilai'] <= 0) $rLalu = null;

        $elemen = [];
        $peta = [];
        $celah = [];
        $kategori = ['kesesuaian' => 0, 'minor' => 0, 'mayor' => 0, 'na' => 0, 'belum' => 0];
        foreach (Smkp::elemen() as $e) {
            $re = $rekap['elemen'][$e['kode']];
            $reLalu = $rLalu['elemen'][$e['kode']] ?? null;
            $capaian = $re['dinilai'] > 0 && $re['berlaku'] > 0 ? round($re['capaian'] * 100, 1) : null;
            $capaianLalu = $reLalu && $reLalu['dinilai'] > 0 && $reLalu['berlaku'] > 0 ? round($reLalu['capaian'] * 100, 1) : null;

            $subs = [];
            foreach ($e['sub'] as $s) {
                $rs = $re['sub'][$s['kode']];
                $k = self::kategoriSub($rs);
                $kategori[$k]++;
                $cs = $rs['dinilai'] > 0 && $rs['berlaku'] > 0 ? round($rs['capaian'] * 100, 1) : null;
                $rsLalu = $reLalu['sub'][$s['kode']] ?? null;
                $subs[] = [
                    'k' => $s['kode'], 'n' => $s['nama'], 'c' => $cs, 'kat' => $k,
                    'nilai' => round((float) $rs['nilai'], 2), 'maks' => (int) $rs['maks'],
                    'lalu' => $rsLalu && $rsLalu['dinilai'] > 0 && $rsLalu['berlaku'] > 0 ? round($rsLalu['capaian'] * 100, 1) : null,
                ];
                if ($rs['dinilai'] > 0 && $rs['maks'] > 0 && $rs['nilai'] < $rs['maks']) {
                    $celah[] = [
                        'k' => $s['kode'], 'n' => $s['nama'], 'el' => $e['kode'], 'kat' => $k,
                        'hilang' => round($rs['maks'] - $rs['nilai'], 2), 'maks' => (int) $rs['maks'], 'c' => $cs,
                    ];
                }
            }
            $elemen[] = [
                'k' => $e['kode'], 'n' => $e['nama'], 'bobot' => (float) $e['bobot'],
                'c' => $capaian, 'lalu' => $capaianLalu,
                'nilai' => round((float) $re['nilai'], 2), 'maks' => (int) $re['maks'],
                'kontribusi' => round((float) $re['skor'], 2),
            ];
            $peta[] = ['k' => $e['kode'], 'n' => $e['nama'], 'sub' => $subs];
        }
        usort($celah, fn ($x, $y) => [$y['hilang'], Smkp::urutanKode($x['k'])] <=> [$x['hilang'], Smkp::urutanKode($y['k'])]);

        $kinerja = self::kinerja($a);

        $out = [
            'ringkas' => [
                'skor' => $rekap['dinilai'] > 0 ? round((float) $rekap['skor'], 2) : null,
                'tingkat' => $rekap['tingkat'],
                'nilai' => round((float) $rekap['nilai'], 2), 'maks' => (int) $rekap['maks'],
                'dinilai' => (int) $rekap['dinilai'], 'berlaku' => (int) $rekap['berlaku'],
                'lalu' => $rLalu ? ['tahun' => (int) $lalu->tahun, 'skor' => round((float) $rLalu['skor'], 2), 'id' => $lalu->id] : null,
            ],
            'kategoriSub' => $kategori,
            'elemen' => $elemen,
            'peta' => $peta,
            'celah' => array_slice($celah, 0, 10),
            'temuan' => self::temuan($temuan, $hariIni),
            'kinerja' => $kinerja,
            'keterkaitan' => self::tidakSejalan($rekap, $hasil),
            'formulir' => [
                'kesesuaian' => count(SmkpKesesuaian::daftar($a)),
                'otomatis' => count(SmkpKesesuaian::otomatis($a)),
                'ofi' => count(SmkpKesesuaian::ofi($a)),
            ],
        ];
        $out['catatan'] = self::catatan($out);

        return $out;
    }

    /** Kategori sub elemen untuk peta panas: kesesuaian | minor | mayor | na | belum. */
    private static function kategoriSub(array $rs): string
    {
        if ($rs['berlaku'] <= 0) return 'na';
        if ($rs['dinilai'] < $rs['berlaku']) return 'belum';
        $k = (string) ($rs['kategori']['kode'] ?? '');

        return in_array($k, ['kesesuaian', 'minor', 'mayor'], true) ? $k : 'belum';
    }

    private static function temuan(Collection $temuan, string $hariIni): array
    {
        $kini = Carbon::parse($hariIni)->startOfDay();
        $jenis = [];
        foreach (array_keys(SmkpFinding::KATEGORI) as $j) $jenis[$j] = ['Open' => 0, 'In Progress' => 0, 'Closed' => 0];
        $terbuka = [];
        $hariTutup = [];
        $tindakLanjut = 0;
        foreach ($temuan as $t) {
            $st = in_array($t->status, SmkpFinding::STATUS, true) ? $t->status : 'Open';
            if (isset($jenis[$t->jenis])) $jenis[$t->jenis][$st]++;
            $tindakLanjut += (int) ($t->tindak_lanjut_count ?? 0);
            if ($st === 'Closed') {
                if ($t->tanggal_selesai && $t->created_at) $hariTutup[] = max(0, Carbon::parse($t->created_at)->startOfDay()->diffInDays(Carbon::parse($t->tanggal_selesai)->startOfDay()));
                continue;
            }
            $batas = $t->target_selesai ? Carbon::parse($t->target_selesai)->startOfDay() : null;
            $terbuka[] = [
                'id' => $t->id, 'nomor' => (string) ($t->nomor ?: '—'), 'kode' => (string) $t->kode_kriteria,
                'jenis' => $t->jenis, 'status' => $st,
                'batas' => $batas?->toDateString(),
                'hari' => $batas ? (int) $kini->diffInDays($batas, false) : null,
            ];
        }
        usort($terbuka, fn ($x, $y) => [$x['hari'] ?? PHP_INT_MAX] <=> [$y['hari'] ?? PHP_INT_MAX]);

        $total = $temuan->count();
        $tutup = $temuan->where('status', 'Closed')->count();

        return [
            'total' => $total,
            'tutup' => $tutup,
            'persenTutup' => $total ? round($tutup / $total * 100, 1) : null,
            'telat' => count(array_filter($terbuka, fn ($t) => $t['hari'] !== null && $t['hari'] < 0)),
            'hariTutup' => $hariTutup ? round(array_sum($hariTutup) / count($hariTutup), 1) : null,
            'tindakLanjut' => $tindakLanjut,
            'jenis' => $jenis,
            'terbuka' => array_slice($terbuka, 0, 8),
        ];
    }

    /** FR, SR, ASR, MFR perusahaan terhadap rata-rata nasional SE yang berlaku untuk tahun audit. */
    private static function kinerja(SmkpAudit $a): array
    {
        $n = SmkpTahap::rataNasional((int) $a->tahun);
        $k = (array) ($a->kinerja ?? []);
        $nilai = [];
        foreach (SmkpTahap::NILAI_NASIONAL as $kunci => $label) {
            $v = SmkpTahap::bilangan($k[$kunci] ?? '');
            $nilai[] = ['k' => $kunci, 'n' => $label, 'v' => $v, 'nasional' => (float) $n[$kunci],
                'atas' => $v === null ? null : $v > (float) $n[$kunci]];
        }
        $kejadian = [];
        foreach (['cidera_ringan' => 'Cidera ringan', 'cidera_berat' => 'Cidera berat', 'mati' => 'Mati', 'berbahaya' => 'Kejadian berbahaya', 'kptk' => 'Penyakit tenaga kerja'] as $kunci => $label) {
            $kejadian[] = ['k' => $kunci, 'n' => $label, 'v' => SmkpTahap::bilangan($k[$kunci] ?? '')];
        }

        return [
            'nilai' => $nilai,
            'kejadian' => $kejadian,
            'sumber' => $n['sumber'], 'tahunSe' => (int) $n['tahun'],
            'saran' => SmkpTahap::saranFaktor($k, (int) $a->tahun),
        ];
    }

    /** Pasangan elemen terkait yang hasilnya tidak sejalan: Kesesuaian di satu sisi, Mayor di sisi lain. */
    private static function tidakSejalan(array $rekap, array $hasil): array
    {
        $out = [];
        $lihat = [];
        foreach (Smkp::elemen() as $e) {
            foreach ($e['sub'] as $s) {
                $kat = SmkpKeterkaitan::kategoriTuntas($rekap['elemen'][$e['kode']]['sub'][$s['kode']]);
                if (!$kat) continue;
                foreach (SmkpKeterkaitan::denganStatus($s['kode'], $rekap, $hasil, $kat) as $t) {
                    if (empty($t['periksa'])) continue;
                    foreach ($t['status'] as $st) {
                        if (($st['kategori'] ?? null) !== ($kat === 'kesesuaian' ? 'mayor' : 'kesesuaian')) continue;
                        $pasang = [$s['kode'], $st['kode']];
                        sort($pasang);
                        $kunci = implode('|', $pasang);
                        if (isset($lihat[$kunci])) continue;
                        $lihat[$kunci] = true;
                        $out[] = ['a' => $s['kode'], 'b' => $st['kode'], 'label' => $t['label'], 'kategoriA' => $kat, 'kategoriB' => $st['kategori']];
                    }
                }
            }
        }

        return array_slice($out, 0, 8);
    }

    /** Catatan evaluasi otomatis dari angka di atas — ruas teks/tebal, bukan HTML. */
    private static function catatan(array $d): array
    {
        $c = [];
        $r = $d['ringkas'];
        if ($r['skor'] !== null) {
            $teks = [['t' => 'Tingkat pencapaian penerapan SMKP '], ['b' => self::persen($r['skor'])], ['t' => ' ('.$r['tingkat']['label'].')']];
            if ($r['lalu']) {
                $sel = round($r['skor'] - $r['lalu']['skor'], 2);
                $teks[] = ['t' => $sel >= 0 ? ', naik ' : ', turun '];
                $teks[] = ['b' => self::persen(abs($sel)).' poin'];
                $teks[] = ['t' => ' dari audit '.$r['lalu']['tahun'].'.'];
            } else {
                $teks[] = ['t' => '.'];
            }
            $c[] = ['ikon' => 'target', 'nada' => $r['skor'] >= 85 ? 'baik' : ($r['skor'] >= 60 ? 'sedang' : 'buruk'), 'ruas' => $teks];
        }

        $dinilai = array_values(array_filter($d['elemen'], fn ($e) => $e['c'] !== null));
        if ($dinilai) {
            usort($dinilai, fn ($x, $y) => $x['c'] <=> $y['c']);
            $rendah = $dinilai[0];
            $c[] = ['ikon' => 'peringatan', 'nada' => $rendah['c'] >= 85 ? 'baik' : 'sedang', 'ruas' => [
                ['t' => 'Capaian terendah di '], ['b' => 'Elemen '.$rendah['k'].' '.$rendah['n']], ['t' => ' ('.self::persen($rendah['c']).') — prioritaskan program perbaikannya.'],
            ]];
            $naik = array_values(array_filter($d['elemen'], fn ($e) => $e['c'] !== null && $e['lalu'] !== null));
            if ($naik) {
                usort($naik, fn ($x, $y) => ($y['c'] - $y['lalu']) <=> ($x['c'] - $x['lalu']));
                $atas = $naik[0];
                $bawah = end($naik);
                if ($atas['c'] - $atas['lalu'] > 0) {
                    $c[] = ['ikon' => 'tren', 'nada' => 'baik', 'ruas' => [['t' => 'Kenaikan terbesar: '], ['b' => 'Elemen '.$atas['k']], ['t' => ' dari '.self::persen($atas['lalu']).' menjadi '.self::persen($atas['c']).'.']]];
                }
                if ($bawah['c'] - $bawah['lalu'] < 0) {
                    $c[] = ['ikon' => 'panah-bawah', 'nada' => 'buruk', 'ruas' => [['t' => 'Penurunan terbesar: '], ['b' => 'Elemen '.$bawah['k']], ['t' => ' dari '.self::persen($bawah['lalu']).' menjadi '.self::persen($bawah['c']).'.']]];
                }
            }
        }

        if ($d['celah']) {
            $g = $d['celah'][0];
            $c[] = ['ikon' => 'kurang', 'nada' => 'sedang', 'ruas' => [
                ['t' => 'Poin hilang terbanyak di '], ['b' => $g['k'].' '.$g['n']], ['t' => ' ('.self::angka($g['hilang']).' dari '.$g['maks'].' poin).'],
            ]];
        }

        $t = $d['temuan'];
        if ($t['total'] > 0) {
            $ruas = [['t' => 'Temuan: '], ['b' => $t['total'].' ketidaksesuaian'], ['t' => ', '.$t['tutup'].' ditutup ('.self::persen($t['persenTutup']).')']];
            if ($t['telat']) {
                $ruas[] = ['t' => '; '];
                $ruas[] = ['b' => $t['telat'].' melewati batas waktu'];
            }
            $ruas[] = ['t' => '.'];
            $c[] = ['ikon' => 'clipboard-check', 'nada' => $t['telat'] ? 'buruk' : ($t['tutup'] === $t['total'] ? 'baik' : 'sedang'), 'ruas' => $ruas];
        }

        $atasNasional = array_values(array_filter($d['kinerja']['nilai'], fn ($k) => $k['atas'] === true));
        if ($atasNasional) {
            $c[] = ['ikon' => 'aktivitas', 'nada' => 'buruk', 'ruas' => [
                ['b' => implode(', ', array_map(fn ($k) => strtoupper($k['k']), $atasNasional))],
                ['t' => ' perusahaan di atas rata-rata nasional ('.$d['kinerja']['sumber'].') — tinjau program pencegahan kecelakaan dan kesehatan kerja.'],
            ]];
        } elseif (array_filter($d['kinerja']['nilai'], fn ($k) => $k['atas'] === false)) {
            $c[] = ['ikon' => 'shield-check', 'nada' => 'baik', 'ruas' => [['t' => 'Kinerja keselamatan yang diisi '], ['b' => 'tidak melebihi rata-rata nasional'], ['t' => ' ('.$d['kinerja']['sumber'].').']]];
        }

        if ($d['keterkaitan']) {
            $c[] = ['ikon' => 'cabang', 'nada' => 'sedang', 'ruas' => [
                ['b' => count($d['keterkaitan']).' pasangan elemen terkait'], ['t' => ' bernilai tidak sejalan (Kesesuaian ↔ Mayor) — periksa kembali bukti penilaiannya.'],
            ]];
        }

        if ($d['formulir']['ofi'] > 0) {
            $c[] = ['ikon' => 'kilau', 'nada' => 'baik', 'ruas' => [['b' => $d['formulir']['ofi'].' peluang perbaikan (OFI)'], ['t' => ' tercatat untuk praktik yang sudah sesuai.']]];
        }

        return $c;
    }

    private static function persen(?float $v): string
    {
        return $v === null ? '—' : self::angka($v).'%';
    }

    private static function angka(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');
    }
}
