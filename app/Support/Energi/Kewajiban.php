<?php

namespace App\Support\Energi;

use App\Models\Energi\{Audit, Laporan, Pengaturan, Personel, Rekomendasi, Tinjauan};
use Illuminate\Support\Carbon;

/**
 * Kalender kepatuhan energi & GRK satu perusahaan (FSD §5.7, §12, BOD-06).
 *
 * DITURUNKAN, tidak disimpan. Status tiap kewajiban dibaca dari kenyataan —
 * laporan yang sudah terbit dan disampaikan, audit yang sudah selesai,
 * sertifikat yang masih berlaku — sehingga tidak ada tabel kewajiban yang
 * dapat bertuliskan "selesai" sementara laporannya tidak pernah dikirim.
 *
 * Status: selesai · proses · belum · terlambat · tidak_berlaku.
 */
final class Kewajiban
{
    public const STATUS = [
        'selesai' => 'Patuh', 'proses' => 'Dalam proses', 'belum' => 'Belum',
        'terlambat' => 'Terlambat', 'tidak_berlaku' => 'Tidak berlaku',
    ];

    /** @return list<array<string,mixed>> */
    public static function daftar(?int $companyId, ?Carbon $kini = null): array
    {
        $kini ??= now()->startOfDay();
        $t = $kini->year;
        $atur = Pengaturan::untuk($companyId);
        $ambang = Ambang::status($companyId, $t, $kini);
        $wajib = $ambang['status'] === 'WAJIB';

        $laporan = Laporan::withoutGlobalScopes()
            ->where(fn ($q) => $companyId === null ? $q->whereNull('company_id') : $q->where('company_id', $companyId))
            ->get()->groupBy(fn ($l) => $l->templat.'|'.$l->tahun);

        $statusLaporan = function (string $templat, int $tahun, Carbon $tenggat, bool $berlaku) use ($laporan, $kini) {
            if (!$berlaku) return ['tidak_berlaku', null];

            $ada = $laporan->get($templat.'|'.$tahun, collect());
            $terbit = $ada->first(fn ($l) => $l->status === 'terbit' && $l->disampaikan_pada);
            if ($terbit) return ['selesai', $terbit->id];

            $proses = $ada->first(fn ($l) => in_array($l->status, ['draf', 'diajukan', 'disetujui', 'terbit', 'perlu_revisi'], true));
            if ($kini->gt($tenggat)) return ['terlambat', $proses?->id];

            return [$proses ? 'proses' : 'belum', $proses?->id];
        };

        $out = [];
        $tambah = function (string $kode, string $judul, string $dasar, ?Carbon $tenggat, string $status, ?string $templat, ?int $laporanId, ?int $tahunLapor, ?string $ket = null) use (&$out, $kini) {
            $out[] = [
                'kode' => $kode, 'judul' => $judul, 'dasar' => $dasar,
                'tenggat' => $tenggat?->toDateString(),
                'sisa_hari' => $tenggat ? (int) $kini->diffInDays($tenggat, false) : null,
                'status' => $status, 'status_label' => self::STATUS[$status],
                'templat' => $templat, 'laporan_id' => $laporanId, 'tahun_lapor' => $tahunLapor, 'keterangan' => $ket,
            ];
        };

        /* Laporan manajemen energi tahun lalu — 30 Juni tahun berjalan. */
        $tg = Carbon::create($t, 6, 30);
        [$s, $id] = $statusLaporan('ESDM_ME', $t - 1, $tg, $wajib);
        $tambah('ESDM_ME', 'Laporan Manajemen Energi '.($t - 1).' ke Menteri ESDM', 'PP 33/2023; Permen ESDM 8/2025 Ps. 17',
            $tg, $s, 'ESDM_ME', $id, $t - 1, $wajib ? null : 'Konsumsi '.($t - 1).' di bawah ambang '.number_format($ambang['ambang'], 0, ',', '.').' TOE.');

        /* Monev tahunan Manajer Energi ke manajemen puncak. */
        $monev = Tinjauan::withoutGlobalScopes()->where('company_id', $companyId)->where('tahun', $t)->where('jenis', 'monev')->exists();
        $tg = Carbon::create($t, 12, 31);
        $tambah('ESDM_MONEV', 'Monev manajemen energi '.$t.' ke manajemen puncak', 'Permen ESDM 8/2025 Ps. 10',
            $tg, !$wajib ? 'tidak_berlaku' : ($monev ? 'selesai' : 'belum'), 'ESDM_MONEV', null, $t);

        /* Inventarisasi GRK tahun lalu — paling lambat Maret. */
        $tg = Carbon::create($t, 3, 31);
        [$s, $id] = $statusLaporan('GRK_PU', $t - 1, $tg, true);
        $tambah('GRK_PU', 'Inventarisasi emisi GRK '.($t - 1), 'Perpres 110/2025 Ps. 11 ayat (1) huruf e, Ps. 14', $tg, $s, 'GRK_PU', $id, $t - 1);

        /* Audit energi ≥1× per 3 tahun, mencakup ≥1 SEU. */
        $audit = Audit::withoutGlobalScopes()->where('company_id', $companyId)->where('status', 'selesai')
            ->whereNotNull('selesai')->orderByDesc('selesai')->first();
        $tg = $audit ? $audit->selesai->copy()->addYears(3) : $kini->copy();
        $sAudit = !$wajib ? 'tidak_berlaku' : (!$audit ? ($kini->gte($tg) ? 'terlambat' : 'belum')
            : ($kini->gt($tg) ? 'terlambat' : (empty($audit->seu) ? 'proses' : 'selesai')));
        $tambah('AUDIT_ENERGI', 'Audit energi berkala (≥1×/3 tahun)', 'Permen ESDM 8/2025 Ps. 13', $tg, $sAudit, null, null, null,
            $audit ? 'Audit terakhir selesai '.$audit->selesai->translatedFormat('d F Y').(empty($audit->seu) ? ' — belum mencakup SEU.' : '.') : 'Belum ada audit energi tercatat.');

        /* Rekomendasi audit dilaksanakan ≤3 tahun. */
        $lewat = Rekomendasi::withoutGlobalScopes()->where('company_id', $companyId)
            ->whereIn('status', ['terbuka', 'ditindaklanjuti'])->whereDate('tenggat', '<', $kini->toDateString())->count();
        $dekat = Rekomendasi::withoutGlobalScopes()->where('company_id', $companyId)
            ->whereIn('status', ['terbuka', 'ditindaklanjuti'])->orderBy('tenggat')->value('tenggat');
        $tambah('REKOMENDASI', 'Pelaksanaan rekomendasi audit energi', 'Permen ESDM 8/2025 Ps. 16',
            $dekat ? Carbon::parse($dekat) : null, !$wajib ? 'tidak_berlaku' : ($lewat ? 'terlambat' : ($dekat ? 'proses' : 'selesai')),
            null, null, null, $lewat ? "{$lewat} rekomendasi lewat tenggat." : null);

        /* Manajer energi bersertifikat. */
        $manajer = Personel::withoutGlobalScopes()->where('company_id', $companyId)->where('peran', 'manajer_energi')
            ->where('aktif', true)->get();
        $sah = $manajer->filter(fn ($m) => $m->sah($kini));
        $habis = $sah->min('berlaku_sampai');
        $tambah('SERT_MANAJER', 'Manajer Energi bersertifikat kompetensi', 'Permen ESDM 8/2025 Ps. 4',
            $habis ? Carbon::parse($habis) : null,
            !$wajib ? 'tidak_berlaku' : ($sah->isEmpty() ? 'terlambat' : ($kini->diffInDays(Carbon::parse($habis), false) <= 60 ? 'proses' : 'selesai')),
            null, null, null, $sah->isEmpty() ? 'Belum ada manajer energi bersertifikat yang berlaku.' : $sah->pluck('nama')->join(', '));

        /* PROPER — tanggal penyampaian diatur admin per siklus. */
        if ($atur->tanggal_proper) {
            [$s, $id] = $statusLaporan('PROPER', $t - 1, $atur->tanggal_proper, true);
            $tambah('PROPER', 'Data energi & emisi untuk PROPER', 'Permen LHK 1/2021; Permen LH/BPLH 7/2025', $atur->tanggal_proper, $s, 'PROPER', $id, $t - 1);
        }

        /* Emiten: laporan keberlanjutan (POJK 51/2017) dan PSPK 2 mulai tahun buku 2027. */
        $tgLt = $atur->tanggal_laporan_tahunan ?? Carbon::create($t, 4, 30);
        [$s, $id] = $statusLaporan('OJK_LK', $t - 1, $tgLt, (bool) $atur->emiten);
        $tambah('OJK_LK', 'Kinerja energi & emisi pada Laporan Keberlanjutan '.($t - 1), 'POJK 51/POJK.03/2017; SEOJK 16/2021', $tgLt, $s, 'OJK_LK', $id, $t - 1);

        [$s, $id] = $statusLaporan('PSPK2', $t - 1, $tgLt, (bool) $atur->emiten && $t - 1 >= 2027);
        $tambah('PSPK2', 'Pengungkapan iklim PSPK 2 tahun buku '.($t - 1), 'PSPK 2 (IFRS S2), berlaku periode ≥ 1 Jan 2027', $tgLt, $s, 'PSPK2', $id, $t - 1,
            !$atur->emiten ? 'Perusahaan bukan emiten.' : ($t - 1 < 2027 ? 'Pertama berlaku untuk tahun buku 2027 (dilaporkan 2028).' : null));

        return $out;
    }

    /** Kewajiban yang perlu diingatkan pada hari ini: H-60, H-30, H-7, H-1, dan yang terlambat. */
    public static function perluDiingatkan(array $daftar): array
    {
        return array_values(array_filter($daftar, fn ($k) => in_array($k['status'], ['belum', 'proses', 'terlambat'], true)
            && $k['sisa_hari'] !== null && (in_array($k['sisa_hari'], [60, 30, 7, 1], true) || $k['sisa_hari'] < 0)));
    }
}
