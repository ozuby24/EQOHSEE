<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Company, SmkpAudit, SmkpFinding};
use App\Support\Smkp;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Audit SMKP Minerba — 7 elemen sesuai Kepdirjen 185.K/37.04/DJB/2019.
 *
 * Alur: buat periode audit → nilai tiap kriteria per elemen → temuan
 * (ketidaksesuaian) diangkat jadi CAR → rekap skor & laporan.
 */
class SmkpController extends Controller
{
    /* ---------- Daftar periode audit ---------- */
    public function index()
    {
        $audits = SmkpAudit::with('company')
            ->withCount(['findings', 'findings as findings_open_count' => fn($b) => $b->where('status','<>','Closed')])
            ->orderByDesc('tahun')->orderByDesc('id')
            ->paginate(15);

        return view('smkp.index', [
            'audits' => $audits,
            'meta'   => Smkp::meta(),
        ]);
    }

    public function create()
    {
        return view('smkp.form', [
            'audit'     => new SmkpAudit(['tahun' => now()->year]),
            'companies' => Company::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $d = $this->validasi($request);
        $d['user_id'] = auth()->id();
        $d['hasil'] ??= [];

        $audit = SmkpAudit::create($d);
        ActivityLog::write('Buat audit SMKP', $audit->judul ?: ('Audit '.$audit->tahun), 'smkp');

        return redirect()->route('smkp.show', $audit)->with('ok','Periode audit dibuat.');
    }

    public function edit(SmkpAudit $smkp)
    {
        return view('smkp.form', [
            'audit'     => $smkp,
            'companies' => Company::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, SmkpAudit $smkp)
    {
        $smkp->update($this->validasi($request, $smkp));
        return redirect()->route('smkp.show', $smkp)->with('ok','Periode audit diperbarui.');
    }

    public function destroy(SmkpAudit $smkp)
    {
        $nama = $smkp->judul ?: ('Audit '.$smkp->tahun);
        $smkp->delete();
        ActivityLog::write('Hapus audit SMKP', $nama, 'smkp');
        return redirect()->route('smkp.index')->with('ok','Periode audit dihapus.');
    }

    /* ---------- Ringkasan satu audit ---------- */
    public function show(SmkpAudit $smkp)
    {
        return view('smkp.show', [
            'audit'  => $smkp,
            'rekap'  => $smkp->rekap(),
            'elemen' => Smkp::elemen(),
            'temuan' => $smkp->findings()->orderByRaw(Smkp::urutJenisSql())->get(),
        ]);
    }

    /* ---------- Formulir penilaian per elemen ---------- */
    public function nilai(SmkpAudit $smkp, string $elemen)
    {
        $ref = collect(Smkp::elemen())->firstWhere('kode', $elemen);
        abort_if(!$ref, 404, 'Elemen tidak dikenal.');

        return view('smkp.nilai', [
            'audit'     => $smkp,
            'elemen'    => $ref,
            'rekap'     => Smkp::rekapElemen($ref, $smkp->hasil ?? []),
            'semua'     => Smkp::elemen(),
        ]);
    }

    /** Simpan penilaian satu elemen; kriteria di elemen lain tidak tersentuh. */
    public function simpanNilai(Request $request, SmkpAudit $smkp, string $elemen)
    {
        $ref = collect(Smkp::elemen())->firstWhere('kode', $elemen);
        abort_if(!$ref, 404, 'Elemen tidak dikenal.');

        $hasil = $smkp->hasil ?? [];
        $masuk = (array) $request->input('k', []);

        foreach ($ref['sub'] as $sub) {
            foreach (Smkp::butirSub($sub) as $b) {
                $kode  = $b['kode'];
                $baris = $masuk[$kode] ?? null;
                if (!is_array($baris)) continue;

                $v = $baris['v'] ?? null;

                if ($v === '' || $v === null) {
                    unset($hasil[$kode]);                 // kembali ke "belum dinilai"
                    continue;
                }

                if (is_string($v) && strcasecmp($v, Smkp::NA) === 0) {
                    $nilai = Smkp::NA;                    // di luar lingkup perusahaan
                } elseif (is_numeric($v)) {
                    // Nilai dijepit ke rentang butir; formulir yang dikirim
                    // langsung tidak boleh menaikkan capaian melebihi maksimum.
                    $nilai = max(0, min((int) $v, (int) $b['maks']));
                } else {
                    continue;                             // abaikan masukan asing
                }

                $hasil[$kode] = [
                    'v'     => $nilai,
                    'ket'   => mb_substr(trim((string) ($baris['ket']   ?? '')), 0, 2000),
                    'bukti' => mb_substr(trim((string) ($baris['bukti'] ?? '')), 0, 500),
                ];
            }
        }

        $smkp->update(['hasil' => $hasil]);

        if ($smkp->status === 'draft') $smkp->update(['status' => 'berjalan']);

        return redirect()->route('smkp.nilai', [$smkp, $elemen])
            ->with('ok', 'Penilaian elemen '.$ref['kode'].' tersimpan.');
    }

    /* ---------- Temuan / CAR ---------- */
    public function temuan(SmkpAudit $smkp)
    {
        return view('smkp.temuan', [
            'audit'    => $smkp,
            'temuan'   => $smkp->findings()->latest('id')->get(),
            'usulan'   => $this->usulanTemuan($smkp),
        ]);
    }

    /** Ketidaksesuaian dari formulir penilaian yang belum diangkat jadi CAR. */
    private function usulanTemuan(SmkpAudit $smkp): array
    {
        $sudah = $smkp->findings()->pluck('kode_kriteria')->all();
        return array_values(array_filter(
            Smkp::temuan($smkp->hasil ?? []),
            fn($t) => !in_array($t['kode'], $sudah, true)
        ));
    }

    /** Angkat seluruh ketidaksesuaian yang belum jadi CAR sekaligus. */
    public function angkatTemuan(SmkpAudit $smkp)
    {
        $n = 0;
        foreach ($this->usulanTemuan($smkp) as $t) {
            $smkp->findings()->create([
                'kode_kriteria' => $t['kode'],
                'jenis'         => $t['jenis'],
                'uraian'        => $t['uraian'],
                'akar_masalah'  => $t['ket'] ?: null,
                'status'        => 'Open',
            ]);
            $n++;
        }

        return back()->with('ok', $n ? "$n temuan diangkat menjadi tindakan perbaikan." : 'Tidak ada temuan baru.');
    }

    public function simpanTemuan(Request $request, SmkpAudit $smkp, SmkpFinding $temuan)
    {
        abort_if($temuan->audit_id !== $smkp->id, 404);

        $d = $request->validate([
            'akar_masalah'     => ['nullable','string','max:2000'],
            'tindakan'         => ['nullable','string','max:2000'],
            'penanggung_jawab' => ['nullable','string','max:150'],
            'target_selesai'   => ['nullable','date'],
            'status'           => ['required','in:Open,In Progress,Closed'],
            'verifikasi'       => ['nullable','string','max:2000'],
        ]);

        if ($d['status'] === 'Closed' && $temuan->status !== 'Closed') {
            $d['tanggal_selesai'] = now();
        } elseif ($d['status'] !== 'Closed') {
            $d['tanggal_selesai'] = null;
        }

        $temuan->update($d);
        return back()->with('ok','Tindakan perbaikan tersimpan.');
    }

    public function hapusTemuan(SmkpAudit $smkp, SmkpFinding $temuan)
    {
        abort_if($temuan->audit_id !== $smkp->id, 404);
        $temuan->delete();
        return back()->with('ok','Temuan dihapus.');
    }

    /* ---------- Laporan siap cetak ---------- */
    public function laporan(SmkpAudit $smkp)
    {
        return view('smkp.laporan', [
            'audit'  => $smkp,
            'rekap'  => $smkp->rekap(),
            'elemen' => Smkp::elemen(),
            'temuan' => $smkp->findings()->orderByRaw(Smkp::urutJenisSql())->get(),
            'meta'   => Smkp::meta(),
        ]);
    }

    /* ---------- bantu ---------- */
    private function validasi(Request $r, ?SmkpAudit $abaikan = null): array
    {
        // Satu periode audit per perusahaan per tahun.
        $unik = Rule::unique('smkp_audits', 'tahun')
            ->where('company_id', $r->input('company_id') ?: null)
            ->ignore($abaikan?->id);

        return $r->validate([
            'company_id'      => ['nullable','exists:companies,id'],
            'tahun'           => ['required','integer','min:2000','max:2100', $unik],
            'judul'           => ['nullable','string','max:200'],
            'status'          => ['required','in:draft,berjalan,selesai'],
            'tanggal_mulai'   => ['nullable','date'],
            'tanggal_selesai' => ['nullable','date','after_or_equal:tanggal_mulai'],
            'ketua_auditor'   => ['nullable','string','max:150'],
        ], [
            'tahun.unique' => 'Periode audit tahun ini sudah ada untuk perusahaan tersebut.',
        ]);
    }
}
