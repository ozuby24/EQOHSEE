<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, EnvAudit, EnvAuditSertifikat, Signatory};
use App\Support\{KopDokumen, SertifikatLingkungan};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Sertifikat Penghargaan Kinerja Lingkungan: terbit, dicetak, dicabut,
 * dan diverifikasi siapa pun lewat QR-nya.
 */
class SertifikatLingkunganController extends Controller
{
    public function terbitkan(Request $request, EnvAudit $audit)
    {
        $d = $request->validate([
            'terbit'       => ['required', 'date'],
            'berlaku'      => ['nullable', 'date', 'after_or_equal:terbit'],
            'tempat'       => ['nullable', 'string', 'max:120'],
            'signatory_id' => ['nullable', 'integer'],
        ], [], [
            'terbit' => 'tanggal terbit', 'berlaku' => 'tanggal berlaku', 'signatory_id' => 'penanda tangan',
        ]);

        /* Penanda tangan harus yang boleh dipakai perusahaan ini —
           milik sendiri atau milik bersama. Nama orang dari perusahaan
           lain tidak boleh tercetak pada sertifikat yang tidak pernah
           ia setujui. */
        $ttd = null;
        if (!empty($d['signatory_id'])) {
            $ttd = Signatory::untukPerusahaan($audit->company_id)->whereKey($d['signatory_id'])->first();

            if (!$ttd) {
                throw ValidationException::withMessages(['signatory_id' => 'Penanda tangan tidak dikenal untuk perusahaan ini.']);
            }
        }

        $s = DB::transaction(function () use ($audit, $d, $ttd) {
            /* Dikunci: dua tombol "terbitkan" yang ditekan bersamaan
               tidak boleh menghasilkan dua sertifikat berlaku. */
            EnvAudit::whereKey($audit->id)->lockForUpdate()->first();

            $audit->load(['scores', 'company.owner']);
            $skor  = $audit->skor();
            $aktif = $audit->sertifikatAktif()->first();

            $k = SertifikatLingkungan::kelayakan($audit, $skor, $aktif);
            if (!$k['layak']) return $k['alasan'];

            $terbit = \Illuminate\Support\Carbon::parse($d['terbit']);
            $prefiks = KopDokumen::prefiksDari(SertifikatLingkungan::penerbit($audit) ?? $audit->company?->name);

            $s = EnvAuditSertifikat::create([
                'audit_id'     => $audit->id,
                'company_id'   => $audit->company_id,
                'user_id'      => auth()->id(),
                'signatory_id' => $ttd?->id,
                'nomor'        => EnvAuditSertifikat::nomorBaru($prefiks, $terbit->year),
                'kode'         => EnvAuditSertifikat::kodeBaru(),
                'terbit'       => $terbit->toDateString(),
                'berlaku'      => $d['berlaku'] ?? null,
                'tempat'       => $d['tempat'] ?? null,
                'predikat'     => $skor['predikat']['nama'],
                'peringkat'    => $skor['peringkat']['nama'],
                'skor'         => $skor['akhir'],
                'data'         => SertifikatLingkungan::potret($audit, $skor, $ttd),
            ]);

            /* Audit yang sertifikatnya terbit adalah audit yang selesai. */
            if ($audit->status !== 'Selesai') $audit->update(['status' => 'Selesai']);

            return $s;
        });

        if (is_string($s)) return back()->with('galat', $s);

        ActivityLog::write('Terbitkan sertifikat lingkungan', $s->nomor.' — '.$audit->kode, 'lingkungan');

        return redirect()->route('audit-lingkungan.show', $audit)
            ->with('ok', 'Sertifikat '.$s->nomor.' terbit — predikat '.$s->predikat.', peringkat '.$s->peringkat.'.');
    }

    /** Lembar sertifikat, siap dicetak A4 lanskap. */
    public function lihat(EnvAuditSertifikat $sertifikat)
    {
        $sertifikat->load(['company.owner', 'signatory', 'audit']);

        return Inertia::render('AuditLingkungan/Sertifikat', [
            'judul' => 'Sertifikat '.$sertifikat->nomor,
            's'     => SertifikatLingkungan::lembar($sertifikat),
            'kembali' => $sertifikat->audit
                ? route('audit-lingkungan.show', $sertifikat->audit)
                : route('audit-lingkungan.index'),
        ]);
    }

    public function cabut(Request $request, EnvAuditSertifikat $sertifikat)
    {
        $d = $request->validate(['alasan' => ['required', 'string', 'min:5', 'max:500']], [], ['alasan' => 'alasan pencabutan']);

        if ($sertifikat->dicabut_at) {
            return back()->with('galat', 'Sertifikat '.$sertifikat->nomor.' sudah dicabut.');
        }

        $sertifikat->update(['dicabut_at' => now(), 'alasan_cabut' => $d['alasan']]);

        ActivityLog::write('Cabut sertifikat lingkungan', $sertifikat->nomor.' — '.$d['alasan'], 'lingkungan');

        return back()->with('ok', 'Sertifikat '.$sertifikat->nomor.' dicabut. Halaman verifikasinya kini menyatakan DICABUT.');
    }

    /**
     * Halaman verifikasi publik — tujuan pemindaian QR.
     *
     * Hanya kode acak yang diterima, bukan nomor sertifikat: nomornya
     * berurutan, dan menerima nomor berarti siapa pun dapat menelusuri
     * satu per satu perusahaan mana mendapat skor berapa.
     *
     * Yang ditampilkan hanya yang tercetak di lembarnya. Profil,
     * kontak, dan nilai per kriteria tetap di dalam aplikasi.
     */
    public function verifikasi(string $kode)
    {
        $bersih = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $kode));

        $s = strlen($bersih) === 12
            ? EnvAuditSertifikat::withoutGlobalScopes()->with('company.owner')->where('kode', $bersih)->first()
            : null;

        return Inertia::render('AuditLingkungan/Verifikasi', [
            'kode' => $kode,
            'catatanKaki' => 'Halaman verifikasi keaslian Sertifikat Penghargaan Kinerja Lingkungan.',
            's' => $s ? array_intersect_key(SertifikatLingkungan::lembar($s), array_flip([
                'nomor', 'kode', 'terbit', 'berlaku', 'tempat', 'predikat', 'bintang', 'peringkat',
                'peringkatKriteria', 'warna', 'tema', 'skor', 'perusahaan', 'penerbit', 'lokasi', 'tahun',
                'ttdNama', 'ttdJabatan', 'status', 'dicabutPada', 'alasanCabut', 'logoPenerbit', 'logoPenerima',
            ])) : null,
        ]);
    }
}
