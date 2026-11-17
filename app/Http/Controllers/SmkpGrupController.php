<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, SmkpAudit};
use App\Support\{SmkpTahap, Waktu};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Audit SMKP → Perusahaan Grup (port DBEST #21, khusus administrator).
 *
 * Audit perusahaan lain satu grup — mis. pemegang IUP lain — dengan nama
 * yang diketik manual dan jenis Pemegang IUP / Jasa pertambangan (IUJP).
 * Isi auditnya sama lengkapnya dengan audit biasa (Tahap I sampai Laporan,
 * lewat halaman audit yang sama), tetapi terpisah: tanpa baris perusahaan,
 * tidak ikut Daftar Audit, Dasbor, statistik, ekspor, tindak lanjut lintas
 * audit, lonceng, maupun pengingat email. Ringkasannya hanya di sini.
 */
class SmkpGrupController extends Controller
{
    public function index(Request $request)
    {
        $audit = SmkpAudit::withoutGlobalScopes()->where('lingkup', SmkpAudit::LINGKUP_GRUP)
            ->withCount(['findings', 'findings as findings_open_count' => fn ($b) => $b->where('status', '<>', 'Closed')])
            ->orderByDesc('tahun')->orderBy('perusahaan_luar')->get();

        $baris = $audit->map(function (SmkpAudit $a) {
            $r = $a->rekap();

            return [
                'id' => $a->id, 'perusahaan' => $a->perusahaan_luar, 'jenis' => $a->jenis_luar,
                'labelJenis' => SmkpAudit::JENIS_LUAR[$a->jenis_luar] ?? '—',
                'tahun' => $a->tahun, 'judul' => $a->judul, 'status' => $a->status, 'labelStatus' => SmkpAudit::STATUS[$a->status] ?? $a->status,
                'tahap' => SmkpTahap::labelTahap((int) $a->tahap), 'ketua' => $a->ketua_auditor,
                'mulai' => $a->tanggal_mulai?->toDateString(), 'selesai' => $a->tanggal_selesai?->toDateString(),
                'skor' => $r['dinilai'] ? round((float) $r['skor'], 1) : null, 'tingkat' => $r['dinilai'] ? $r['tingkat']['label'] : 'Belum dinilai',
                'temuan' => (int) $a->findings_count, 'terbuka' => (int) $a->findings_open_count,
            ];
        });

        return Inertia::render('Smkp/Grup', [
            'baris'  => $baris,
            'ringkas' => [
                'audit'   => $baris->count(),
                'dinilai' => $baris->whereNotNull('skor')->count(),
                'rata'    => $baris->whereNotNull('skor')->avg('skor') !== null ? round($baris->whereNotNull('skor')->avg('skor'), 1) : null,
                'terbuka' => $baris->sum('terbuka'),
            ],
            'jenis'  => SmkpAudit::JENIS_LUAR,
            'status' => SmkpAudit::STATUS,
            'ubah'   => (int) $request->query('ubah') ?: null,
            'tahunKini' => (int) Waktu::kini()->year,
        ]);
    }

    public function store(Request $request)
    {
        $d = $this->validasi($request);
        $a = SmkpAudit::withoutGlobalScopes()->create($d + [
            'lingkup' => SmkpAudit::LINGKUP_GRUP, 'company_id' => null, 'user_id' => $request->user()->id,
            'hasil' => [], 'tahap' => SmkpTahap::PERMULAAN,
        ]);
        ActivityLog::write('Buat audit perusahaan grup', $a->perusahaan_luar.' '.$a->tahun, 'smkp');

        return redirect()->route('smkp.show', $a)->with('ok', 'Audit perusahaan grup '.$a->perusahaan_luar.' dibuat.');
    }

    public function update(Request $request, int $grup)
    {
        $a = SmkpAudit::withoutGlobalScopes()->where('lingkup', SmkpAudit::LINGKUP_GRUP)->findOrFail($grup);
        $a->update($this->validasi($request, $a));
        ActivityLog::write('Ubah audit perusahaan grup', $a->perusahaan_luar.' '.$a->tahun, 'smkp');

        return back()->with('ok', 'Audit perusahaan grup diperbarui.');
    }

    private function validasi(Request $r, ?SmkpAudit $abaikan = null): array
    {
        $unik = Rule::unique('smkp_audits', 'tahun')->where('lingkup', SmkpAudit::LINGKUP_GRUP)
            ->where('perusahaan_luar', trim((string) $r->input('perusahaan_luar')))->ignore($abaikan?->id);

        $d = $r->validate([
            'perusahaan_luar' => ['required', 'string', 'max:190'],
            'jenis_luar'      => ['required', Rule::in(array_keys(SmkpAudit::JENIS_LUAR))],
            'tahun'           => ['required', 'integer', 'min:2000', 'max:2100', $unik],
            'judul'           => ['nullable', 'string', 'max:200'],
            'status'          => ['required', Rule::in(array_keys(SmkpAudit::STATUS))],
            'tanggal_mulai'   => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'ketua_auditor'   => ['nullable', 'string', 'max:150'],
        ], ['tahun.unique' => 'Perusahaan grup ini sudah punya audit pada tahun tersebut.']);
        $d['perusahaan_luar'] = trim($d['perusahaan_luar']);

        return $d;
    }
}
