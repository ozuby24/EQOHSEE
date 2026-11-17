<?php

namespace App\Support;

use App\Models\{ActivityLog, EnvAudit, EnvAuditRtp, EnvAuditScore};

/**
 * Aturan Rencana Tindak Perbaikan (RTP) Audit Kinerja Lingkungan,
 * mengikuti Audit Lingkungan DBEST (Penilai::perbaruiTemuan dan
 * Acuan::bolehNaik).
 *
 *  - Temuan terbit untuk kriteria yang nilai VERIFIKASI-nya ≤ 2. Nilai
 *    kosong (belum diverifikasi) bukan temuan.
 *  - Nilai naik menjadi 3: RTP yang belum disentuh dihapus; yang sudah
 *    diisi auditee ditutup dengan catatan, rencananya tetap terbaca.
 *  - Nilai turun lagi: RTP yang dulu ditutup karena nilainya naik dibuka
 *    kembali. RTP yang ditutup karena DISETUJUI auditor tidak ikut dibuka
 *    — menyimpan ulang nilai tidak boleh membatalkan keputusan verifikasi
 *    diam-diam.
 *  - Nilai kriteria ber-RTP hanya boleh dinaikkan bila diverifikasi paling
 *    lambat pada target selesai RTP (bolehNaik). Menurunkan selalu boleh.
 */
final class RtpLingkungan
{
    /** Nilai verifikasi tertinggi yang masih menerbitkan temuan. */
    public const AMBANG_TEMUAN = 2;

    public const NILAI_MAKS = 3;

    public static function temuan(?int $verifikasi): bool
    {
        return $verifikasi !== null && $verifikasi <= self::AMBANG_TEMUAN;
    }

    /** Arti singkat tiap nilai 0–3 (tangga nilai lembar audit). */
    public static function artiNilai(): array
    {
        return [
            0 => 'Tidak ada bukti',
            1 => 'Ada, tetapi tidak memadai',
            2 => 'Ada, sebagian belum sesuai',
            3 => 'Bukti lengkap & sesuai',
        ];
    }

    /**
     * Boleh-tidaknya nilai kriteria ber-RTP dinaikkan pada tanggal
     * verifikasi $hariIni: ya bila belum lewat target selesai, atau bila
     * RTP-nya sudah disetujui paling lambat pada target itu.
     */
    public static function bolehNaik(?string $target, string $hariIni, ?string $disetujuiPada = null): bool
    {
        $t = substr((string) $target, 0, 10);
        if ($t === '' || substr($hariIni, 0, 10) <= $t) return true;

        return (string) $disetujuiPada !== '' && substr((string) $disetujuiPada, 0, 10) <= $t;
    }

    /** Uraian kriteria per kode — teks temuan bawaan. */
    public static function uraian(string $kode): string
    {
        static $peta = [];
        $bagian = (string) strtok($kode, '.');
        if (!isset($peta[$bagian])) {
            $peta[$bagian] = array_column(AuditLingkungan::kriteria($bagian), null, 'kode');
        }
        $k = $peta[$bagian][$kode] ?? null;

        return $k ? trim(implode(' — ', array_filter([(string) ($k['sub'] ?? ''), (string) ($k['uraian'] ?? '')]))) : $kode;
    }

    /**
     * Terbitkan, buka ulang, atau tutup RTP satu kriteria menurut nilai
     * verifikasinya yang baru saja disimpan.
     */
    public static function perbarui(EnvAudit $audit, EnvAuditScore $skor): ?EnvAuditRtp
    {
        $v   = $skor->verifikasi;
        $rtp = EnvAuditRtp::where('audit_id', $audit->id)->where('kode', $skor->kode)->first();
        $hariIni = Waktu::kini()->toDateString();

        // Belum diverifikasi: belum ada yang bisa disebut temuan.
        if ($v === null) {
            if ($rtp && $rtp->kosong()) $rtp->delete();

            return $rtp?->exists ? $rtp : null;
        }

        if (self::temuan($v)) {
            if (!$rtp) {
                $rtp = new EnvAuditRtp([
                    'audit_id' => $audit->id,
                    'kode'     => $skor->kode,
                    'status'   => 'buka',
                    'temuan'   => self::uraian($skor->kode),
                ]);
            } elseif ($rtp->status === 'selesai' && $rtp->catatan_penutup && $rtp->verifikasi !== EnvAuditRtp::DISETUJUI) {
                // Dulu ditutup karena nilainya naik, sekarang turun lagi:
                // dibuka kembali dengan rencana lamanya utuh.
                $rtp->status          = $rtp->rencana ? 'proses' : 'buka';
                $rtp->tanggal_selesai = null;
                $rtp->catatan_penutup = null;
            }
            $rtp->nilai = (int) $v;
            $rtp->save();

            return $rtp;
        }

        // Nilai penuh (3): temuan gugur.
        if (!$rtp) return null;
        if ($rtp->kosong()) {
            $rtp->delete();

            return null;
        }
        if ($rtp->status !== 'selesai') {
            $rtp->status          = 'selesai';
            $rtp->tanggal_selesai = $hariIni;
            $rtp->catatan_penutup = 'Ditutup otomatis: nilai verifikasi kriteria '.$rtp->kode.' menjadi '.self::NILAI_MAKS
                .' pada '.Waktu::kini()->translatedFormat('d M Y').(auth()->user() ? ' oleh '.auth()->user()->name : '').'.';
            $rtp->save();
        }

        return $rtp;
    }

    /**
     * Nilai verifikasi yang boleh disimpan untuk kriteria ber-RTP: kenaikan
     * sesudah target selesai ditolak (nilai lama dipertahankan) kecuali RTP
     * disetujui paling lambat pada targetnya. Mengembalikan [nilai, ditahan].
     *
     * @return array{0: ?int, 1: bool}
     */
    public static function saring(?EnvAuditRtp $rtp, ?int $lama, ?int $baru): array
    {
        if (!$rtp || $baru === null || $lama === null || $baru <= $lama) return [$baru, false];

        $boleh = self::bolehNaik($rtp->target_tanggal?->toDateString(), Waktu::kini()->toDateString(),
            $rtp->verifikasi === EnvAuditRtp::DISETUJUI ? $rtp->diverifikasi_pada?->toDateString() : null);

        return $boleh ? [$baru, false] : [$lama, true];
    }

    /** Catat ke log aktivitas dengan nama audit yang terbaca. */
    public static function catat(string $aksi, EnvAudit $audit, EnvAuditRtp $rtp): void
    {
        ActivityLog::write($aksi, $audit->kode.' · '.$rtp->kode, 'audit-lingkungan');
    }
}
