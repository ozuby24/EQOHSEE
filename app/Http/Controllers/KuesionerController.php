<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Company, TpkkpAssessment, TpkkpResponse};
use App\Support\{Tpkkp, TpkkpKuesioner};
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Kuesioner persepsi (Metode B — Skala Likert).
 * Sasaran mengikuti struktur TPKKP:
 *   cat 'pekerja'  → Indikator 1 (Partisipasi Pekerja Tambang)
 *   cat 'pimpinan' → Indikator 2 (Tanggung Jawab Pimpinan Unit Kerja)
 *
 * Halaman pengisian BEBAS AKSES (tanpa login) lewat tautan bertoken,
 * agar mudah disebar ke pekerja & pimpinan unit kerja.
 */
class KuesionerController extends Controller
{
    public const KATEGORI = [
        'pekerja'  => ['label' => 'Pekerja Tambang',      'indicator' => 1],
        'pimpinan' => ['label' => 'Pimpinan Unit Kerja',  'indicator' => 2],
    ];

    /* ============ SISI ADMIN ============ */

    public function admin(Request $request)
    {
        $me = auth()->user();
        $company = (!$me->isAdmin() && $me->company_id)
            ? Company::find($me->company_id)
            : Company::find($request->get('company') ?? session('tpkkp_company')) ?? Company::orderBy('name')->first();
        abort_unless($company, 404);
        session(['tpkkp_company' => $company->id]);

        $token = $this->token($company);
        $companies = $me->isAdmin() ? Company::orderBy('name')->get() : collect([$company]);

        $responses = TpkkpResponse::where('company_id', $company->id)->latest('ts')->get();
        $ringkas = [];
        foreach (self::KATEGORI as $key => $k) {
            $rows = $responses->where('cat', $key);
            $ringkas[$key] = [
                'label'  => $k['label'],
                'jumlah' => $rows->count(),
                'rerata' => TpkkpKuesioner::rerata($rows),
                'params' => TpkkpKuesioner::rerataParam($rows, $key),
            ];
        }

        return view('kuesioner.admin', compact('company','companies','token','responses','ringkas'));
    }

    public function resetToken(Request $request)
    {
        $company = Company::findOrFail($request->input('company_id'));
        $this->setToken($company, Str::random(24));
        ActivityLog::write('Reset tautan kuesioner', $company->name, 'tpkkp');

        return back()->with('ok', 'Tautan kuesioner diperbarui. Tautan lama tidak berlaku lagi.');
    }

    public function destroyResponse(TpkkpResponse $response)
    {
        $response->delete();
        return back()->with('ok', 'Responden dihapus.');
    }

    /* ============ SISI PUBLIK (tanpa login) ============ */

    public function pilih(string $token)
    {
        $company = $this->byToken($token);
        return view('kuesioner.pilih', compact('company','token'));
    }

    public function form(string $token, string $cat)
    {
        $company = $this->byToken($token);
        abort_unless(isset(self::KATEGORI[$cat]), 404);

        abort_unless(TpkkpKuesioner::punya($cat), 404);

        return view('kuesioner.form', [
            'company'  => $company, 'token' => $token, 'cat' => $cat,
            'kategori' => self::KATEGORI[$cat],
            'entitas'  => TpkkpKuesioner::entitas($cat),
            'skala'    => TpkkpKuesioner::skala(),
            'params'   => TpkkpKuesioner::butirPerParam($cat),
        ]);
    }

    public function submit(Request $request, string $token, string $cat)
    {
        $company = $this->byToken($token);
        abort_unless(isset(self::KATEGORI[$cat]), 404);

        $d = $request->validate([
            'nrp'        => ['nullable','string','max:50'],
            'jabatan'    => ['nullable','string','max:100'],
            'dept'       => ['nullable','string','max:100'],
            'perusahaan' => ['nullable','string','max:150'],
            'answers'    => ['required','array','min:1'],
        ]);

        $sah     = TpkkpKuesioner::kodeSah($cat);
        $answers = [];
        foreach ($d['answers'] as $code => $v) {
            if ($v === '' || $v === null) continue;
            if (!in_array((string) $code, $sah, true)) continue;   // buang kode asing
            $answers[(string) $code] = max(1, min(5, (int) $v));
        }
        abort_if(empty($answers), 422, 'Tidak ada jawaban.');

        TpkkpResponse::create([
            'company_id' => $company->id,
            'ext_id'     => (string) Str::uuid(),
            'cat'        => $cat,
            'nrp'        => $d['nrp'] ?? null,
            'jabatan'    => $d['jabatan'] ?? null,
            'dept'       => $d['dept'] ?? null,
            'perusahaan' => $d['perusahaan'] ?? $company->name,
            'answers'    => $answers,
            'ts'         => now(),
        ]);

        return redirect()->route('kuesioner.selesai', $token);
    }

    public function selesai(string $token)
    {
        return view('kuesioner.selesai', ['company' => $this->byToken($token), 'token' => $token]);
    }

    /* ============ BANTU ============ */

    /** Penilaian periode berjalan — token kuesioner menumpang di sini. */
    private function assessment(): TpkkpAssessment
    {
        return TpkkpAssessment::forYear((int) now()->year);
    }

    private function token(Company $c): string
    {
        $a = $this->assessment();
        $t = $a->profil['tokens'][$c->id] ?? null;

        return $t ?: $this->setToken($c, Str::random(24));
    }

    private function setToken(Company $c, string $token): string
    {
        $a = $this->assessment();
        $p = (array) $a->profil;
        $p['tokens'] ??= [];
        $p['tokens'][$c->id] = $token;
        $a->update(['profil' => $p]);

        return $token;
    }

    private function byToken(string $token): Company
    {
        foreach (TpkkpAssessment::all() as $a) {
            foreach ((array) ($a->profil['tokens'] ?? []) as $cid => $t) {
                if ($t === $token && ($c = Company::find($cid))) return $c;
            }
        }
        abort(404, 'Tautan kuesioner tidak valid atau sudah diganti.');
    }

    /* ============ TARIK KE SKOR METODE KS ============ */

    public function tarikKs(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Hanya admin yang boleh menarik skor.');

        $tahun = (int) ($request->get('tahun') ?? session('tpkkp_tahun') ?? now()->year);
        $a     = TpkkpAssessment::forYear($tahun);

        $rows    = TpkkpResponse::all();
        $agregat = TpkkpKuesioner::agregat($rows);
        $hasil   = TpkkpKuesioner::tulisKeSkor($a->scores ?? [], $agregat);

        $a->scores = $hasil['scores'];
        $a->save();

        ActivityLog::write(
            'Tarik kuesioner ke skor KS',
            $hasil['ditulis'] . ' item · ' . $hasil['entitas'] . ' sel entitas · periode ' . $tahun,
            'tpkkp'
        );

        $pesan = "Skor KS diperbarui: {$hasil['ditulis']} item, {$hasil['entitas']} sel entitas.";
        if ($hasil['dilewati']) {
            $pesan .= ' Dilewati (bukan item KS): ' . implode(', ', array_slice($hasil['dilewati'], 0, 8)) . '.';
        }

        return back()->with('ok', $pesan);
    }

    /** Parameter milik satu indikator */
    private function paramsOf(int $indicatorId): array
    {
        foreach (Tpkkp::indicators() as $I) if ($I['id'] === $indicatorId) return $I['params'];
        return [];
    }

    /** Rerata seluruh jawaban (skala 1–5 → persen) */
    private function rerata($rows, int $indicatorId): ?float
    {
        return TpkkpKuesioner::rerata($rows);
    }

    /** Rerata per parameter */
    private function rerataParam($rows, int $indicatorId): array
    {
        $out = [];
        foreach ($this->paramsOf($indicatorId) as $p) {
            $codes = array_column($p['items'] ?? [], 'code');
            $sum = 0; $n = 0;
            foreach ($rows as $r) {
                foreach ((array) $r->answers as $code => $v) {
                    if (in_array($code, $codes, true)) { $sum += (int) $v; $n++; }
                }
            }
            $out[] = [
                'code' => $p['code'], 'name' => $p['name'],
                'rerata' => $n ? round($sum / $n, 2) : null,
                'pct'    => $n ? round($sum / $n / 5 * 100) : null,
                'n' => $n,
            ];
        }
        return $out;
    }
}
