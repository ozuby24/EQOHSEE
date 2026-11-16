<?php

namespace App\Http\Controllers;

use App\Models\{EnvAudit, EnvAuditRtp, EnvAuditScore};
use App\Support\{Berkas, RtpLingkungan, Waktu};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Rencana Tindak Perbaikan (RTP) Audit Kinerja Lingkungan — mengikuti
 * Audit Lingkungan DBEST.
 *
 * Auditee mengisi rencana, PIC, dan target, lalu mengunggah bukti
 * penutupan — mengunggah berarti MENGAJUKAN verifikasi. Auditor
 * (gerbang `audit`) memutuskan dari kartu RTP itu sendiri: Setujui
 * sekaligus memberi nilai verifikasi baru (temuan ditutup) atau Tolak
 * beserta alasannya. Nilai di lembar audit, ringkasan skor, dan status
 * temuan ikut berubah tanpa membuka lembar penilaian.
 *
 * Setiap RTP dicari LEWAT auditnya: EnvAudit terikat
 * Scopes\MilikPerusahaan, sehingga RTP perusahaan lain berakhir 404.
 */
class RtpLingkunganController extends Controller
{
    public function index(Request $request, EnvAudit $audit)
    {
        $status = (string) $request->query('status', 'aktif');
        $rtp = $audit->rtp()->orderBy('nilai')->orderBy('kode')->get();
        $bisaVerifikasi = (bool) $request->user()?->bisaAudit();

        $hitung = [
            'aktif'    => $rtp->where('status', '!=', 'selesai')->count(),
            'menunggu' => $rtp->where('verifikasi', EnvAuditRtp::MENUNGGU)->where('status', '!=', 'selesai')->count(),
            'telat'    => $rtp->filter->terlambat()->count(),
            'selesai'  => $rtp->where('status', 'selesai')->count(),
            'semua'    => $rtp->count(),
        ];

        $tampil = $rtp->filter(fn (EnvAuditRtp $r) => match ($status) {
            'menunggu' => $r->verifikasi === EnvAuditRtp::MENUNGGU && $r->status !== 'selesai',
            'telat'    => $r->terlambat(),
            'selesai'  => $r->status === 'selesai',
            'semua'    => true,
            default    => $r->status !== 'selesai',
        })->values();

        $skor = EnvAuditScore::where('audit_id', $audit->id)->whereIn('kode', $tampil->pluck('kode'))->pluck('verifikasi', 'kode');

        return Inertia::render('AuditLingkungan/Rtp', [
            'judul'    => 'Rencana Tindak Perbaikan',
            'subjudul' => $audit->kode.' · '.$audit->judul,
            'audit'    => ['id' => $audit->id, 'kode' => $audit->kode, 'judul' => $audit->judul, 'perusahaan' => $audit->company?->name],
            'saring'   => ['status' => array_key_exists($status, $hitung) ? $status : 'aktif'],
            'hitung'   => $hitung,
            'arti'     => RtpLingkungan::artiNilai(),
            'bisaVerifikasi' => $bisaVerifikasi,
            'hariIni'  => Waktu::kini()->toDateString(),
            'rtp'      => $tampil->map(fn (EnvAuditRtp $r) => [
                'id'        => $r->id,
                'kode'      => $r->kode,
                'temuan'    => (string) $r->temuan,
                'nilaiAwal' => $r->nilai,
                'nilaiKini' => isset($skor[$r->kode]) ? (int) $skor[$r->kode] : null,
                'rencana'   => (string) $r->rencana,
                'pic'       => (string) $r->pic,
                'target'    => $r->target_tanggal?->toDateString(),
                'status'    => $r->status,
                'labelStatus' => EnvAuditRtp::STATUS[$r->status] ?? $r->status,
                'terlambat' => $r->terlambat(),
                'selesai'   => $r->tanggal_selesai?->translatedFormat('d M Y'),
                'penutup'   => (string) $r->catatan_penutup,
                'verifikasi' => $r->verifikasi,
                'catatanVerifikasi' => (string) $r->catatan_verifikasi,
                'verifikator' => (string) $r->verifikator,
                'diverifikasi' => $r->diverifikasi_pada?->timezone(Waktu::zona())->translatedFormat('d M Y H:i'),
                'diajukan'  => $r->diajukan_pada?->timezone(Waktu::zona())->translatedFormat('d M Y H:i'),
                'bukti'     => collect((array) $r->bukti)->values()->map(fn ($j, $i) => [
                    'nama' => (string) (((array) $r->bukti_nama)[$i] ?? basename((string) $j)),
                    'url'  => Berkas::terbuka($j),
                ])->all(),
                // Target yang sudah lewat hanya dapat diubah auditor.
                'targetTerkunci' => !$bisaVerifikasi && $r->target_tanggal && $r->target_tanggal->toDateString() < Waktu::kini()->toDateString(),
                'bolehNaik' => RtpLingkungan::bolehNaik($r->target_tanggal?->toDateString(), Waktu::kini()->toDateString(),
                    $r->verifikasi === EnvAuditRtp::DISETUJUI ? $r->diverifikasi_pada?->toDateString() : null),
                'urlKriteria' => route('audit-lingkungan.bagian', [$audit, strtok($r->kode, '.')]).'#k-'.str_replace('.', '-', $r->kode),
            ])->all(),
            'tautan' => [
                'ikhtisar' => route('audit-lingkungan.show', $audit),
                'rtp'      => route('audit-lingkungan.rtp', $audit),
            ],
        ]);
    }

    /** Auditee (atau auditor) mengisi rencana, PIC, dan target selesai. */
    public function simpan(Request $request, EnvAudit $audit, int $rtp)
    {
        $r = $audit->rtp()->findOrFail($rtp);
        abort_if($r->status === 'selesai', 422, 'RTP sudah ditutup.');

        $d = $request->validate([
            'rencana' => ['nullable', 'string', 'max:3000'],
            'pic'     => ['nullable', 'string', 'max:150'],
            'target'  => ['nullable', 'date'],
        ]);

        $baru = $d['target'] ?? null;
        $lama = $r->target_tanggal?->toDateString();
        if ($baru !== $lama && !$request->user()->bisaAudit() && $lama && $lama < Waktu::kini()->toDateString()) {
            throw ValidationException::withMessages(['target' => 'Target selesai yang sudah lewat hanya dapat diubah auditor.']);
        }

        $r->fill([
            'rencana' => $d['rencana'] ?? null,
            'pic'     => $d['pic'] ?? null,
            'target_tanggal' => $baru,
            'user_id' => $request->user()->id,
        ]);
        if ($r->status === 'buka' && trim((string) $r->rencana) !== '') $r->status = 'proses';
        $r->save();
        RtpLingkungan::catat('Simpan RTP', $audit, $r);

        return back()->with('ok', "RTP {$r->kode} tersimpan.");
    }

    /** Unggah bukti penutupan — sekaligus mengajukan verifikasi. */
    public function bukti(Request $request, EnvAudit $audit, int $rtp)
    {
        $r = $audit->rtp()->findOrFail($rtp);
        abort_if($r->status === 'selesai', 422, 'RTP sudah ditutup.');

        $request->validate(['berkas' => ['required', 'array', 'max:6'], 'berkas.*' => Berkas::ATURAN_DOKUMEN], [], ['berkas.*' => 'bukti']);

        $berkas = array_values((array) $r->bukti);
        $nama   = array_pad(array_values((array) $r->bukti_nama), count($berkas), '');
        foreach ((array) $request->file('berkas') as $f) {
            if (!$j = Berkas::simpan($f, 'audit-lingkungan/rtp')) continue;
            $berkas[] = $j;
            $nama[]   = mb_substr(basename((string) $f->getClientOriginalName()), 0, 150);
        }
        $r->forceFill([
            'bukti' => $berkas, 'bukti_nama' => $nama,
            'verifikasi' => EnvAuditRtp::MENUNGGU, 'diajukan_pada' => now(),
            'status' => 'proses',
        ])->save();
        RtpLingkungan::catat('Ajukan verifikasi RTP', $audit, $r);

        return back()->with('ok', "Bukti RTP {$r->kode} terunggah dan diajukan untuk diverifikasi.");
    }

    /** Hapus satu bukti (sebelum disetujui). */
    public function hapusBukti(Request $request, EnvAudit $audit, int $rtp)
    {
        $r = $audit->rtp()->findOrFail($rtp);
        abort_if($r->status === 'selesai', 422, 'RTP sudah ditutup.');
        $i = (int) $request->validate(['indeks' => ['required', 'integer', 'min:0']])['indeks'];

        $berkas = array_values((array) $r->bukti);
        $nama   = array_values((array) $r->bukti_nama);
        abort_unless(isset($berkas[$i]), 404);
        Berkas::buang($berkas[$i]);
        array_splice($berkas, $i, 1);
        if (array_key_exists($i, $nama)) array_splice($nama, $i, 1);

        $r->forceFill(['bukti' => $berkas ?: null, 'bukti_nama' => $nama ?: null]
            + ($berkas ? [] : ['verifikasi' => null, 'diajukan_pada' => null]))->save();

        return back()->with('ok', 'Bukti dihapus.');
    }

    /**
     * Keputusan auditor: Setujui (nilai verifikasi baru, temuan ditutup)
     * atau Tolak (alasan wajib; auditee memperbaiki dan mengunggah ulang).
     *
     * Kenaikan nilai sesudah target selesai tidak diterapkan (bolehNaik):
     * RTP tetap dapat disetujui, tetapi nilainya bertahan.
     */
    public function verifikasi(Request $request, EnvAudit $audit, int $rtp)
    {
        $r = $audit->rtp()->findOrFail($rtp);

        $d = $request->validate([
            'keputusan' => ['required', Rule::in([EnvAuditRtp::DISETUJUI, EnvAuditRtp::DITOLAK])],
            'nilai'     => ['nullable', 'integer', 'min:0', 'max:'.RtpLingkungan::NILAI_MAKS, Rule::requiredIf($request->input('keputusan') === EnvAuditRtp::DISETUJUI)],
            'catatan'   => ['nullable', 'string', 'max:2000', Rule::requiredIf($request->input('keputusan') === EnvAuditRtp::DITOLAK)],
        ], ['catatan.required' => 'Tulis alasan penolakan agar auditee tahu yang harus diperbaiki.']);

        $u = $request->user();
        $r->forceFill([
            'verifikasi'         => $d['keputusan'],
            'catatan_verifikasi' => $d['catatan'] ?? null,
            'verifikator_id'     => $u->id,
            'verifikator'        => $u->name,
            'diverifikasi_pada'  => now(),
        ]);

        $pesan = "RTP {$r->kode} ditolak — auditee diminta memperbaiki.";
        if ($d['keputusan'] === EnvAuditRtp::DITOLAK) {
            $r->status = 'proses';
            $r->save();
            RtpLingkungan::catat('Tolak RTP', $audit, $r);

            return back()->with('ok', $pesan);
        }

        // Disetujui: tutup temuannya, lalu terapkan nilai verifikasi baru.
        $skor = EnvAuditScore::firstOrNew(['audit_id' => $audit->id, 'kode' => $r->kode]);
        $lama = $skor->verifikasi;
        $baru = (int) $d['nilai'];
        $naikDitahan = $lama !== null && $baru > $lama && !RtpLingkungan::bolehNaik(
            $r->target_tanggal?->toDateString(), Waktu::kini()->toDateString());

        $r->status = 'selesai';
        $r->tanggal_selesai = Waktu::kini()->toDateString();
        $r->catatan_penutup = null;
        $r->save();

        if (!$naikDitahan) {
            $skor->verifikasi = $baru;
            $skor->save();
            // Nilai disetujui ≤ 2 tidak membuka ulang RTP yang disetujui
            // (RtpLingkungan::perbarui) — temuan baru lahir hanya dari
            // penilaian ulang di lembar audit.
            RtpLingkungan::perbarui($audit, $skor);
        }
        RtpLingkungan::catat('Setujui RTP', $audit, $r);

        return back()->with('ok', $naikDitahan
            ? "RTP {$r->kode} disetujui. Nilai tidak dinaikkan karena verifikasi melewati target selesai."
            : "RTP {$r->kode} disetujui, nilai verifikasi menjadi {$baru}.");
    }
}
