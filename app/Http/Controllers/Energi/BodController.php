<?php

namespace App\Http\Controllers\Energi;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, EnergyBaseline, TindakLanjut, User};
use App\Models\Energi\{Laporan, Site};
use App\Support\Energi\{KendaliDireksi, Kewajiban, Pelaporan};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Executive Energy & Carbon Control Tower (PRD §6, FSD §9).
 *
 * BOD membaca, tidak mengubah data aktivitas. Yang dapat dilakukan dari
 * dasbor: memberi instruksi (TindakLanjut), menyetujui target tahunan,
 * menjalankan simulasi, dan membekukan board pack.
 */
class BodController extends Controller
{
    use BantuEnergi;

    public function index(Request $request)
    {
        $f = $this->filter($request);
        [$dari, $sampai, $periode] = KendaliDireksi::rentang($f);
        $saring = KendaliDireksi::saring($f);
        $perusahaan = KendaliDireksi::perusahaan();

        $k = KendaliDireksi::kartu($dari, $sampai, $saring);
        $petaKepatuhan = KendaliDireksi::petaKepatuhan($saring['company_id'] ?? null
            ? array_values(array_filter($perusahaan, fn ($c) => $c['id'] === $saring['company_id'])) : $perusahaan);
        $kewajibanFokus = $petaKepatuhan[0]['kewajiban'] ?? Kewajiban::daftar($saring['company_id'] ?? $request->user()->company_id);
        $companyKonteks = $saring['company_id'] ?? $request->user()->company_id;

        return Inertia::render('Energi/Bod', [
            'filter' => $f + ['periode' => $periode, 'dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()],
            'pilihan' => ['periode' => KendaliDireksi::PERIODE, 'perusahaan' => $perusahaan,
                          'site' => Site::orderBy('nama')->get(['id', 'nama', 'company_id'])],
            'kartu' => $k['kartu'], 'mutu' => $k['mutu'], 'ringkas' => $k['ringkas'], 'produksi' => $k['produksi'],
            'tren' => KendaliDireksi::tren($k['baris'], $dari, $sampai, $saring, $k['baseline']),
            'jalur' => KendaliDireksi::jalur($saring['company_id'] ?? null),
            'kepatuhan' => $petaKepatuhan,
            'statusKepatuhan' => Kewajiban::STATUS,
            'peringkat' => KendaliDireksi::peringkatUnit($dari, $sampai, $saring),
            'benchmark' => count($perusahaan) > 1 ? KendaliDireksi::benchmark($perusahaan, $dari, $sampai) : [],
            'peringatan' => KendaliDireksi::peringatan($sampai, $saring, $kewajibanFokus, $k['kartu']),
            'portofolio' => KendaliDireksi::portofolio($saring),
            'eksposur' => KendaliDireksi::eksposur($k['ringkas']['total'], $sampai, $companyKonteks,
                $request->filled('harga') ? (float) $request->input('harga') : null),
            'drill' => KendaliDireksi::drill($k['baris'], 'grup', null, $dari, $sampai),
            'instruksi' => KendaliDireksi::instruksi($saring),
            'usulanTarget' => EnergyBaseline::where('status', 'usulan')->orderBy('tahun')->get()->map(fn ($b) => [
                'id' => $b->id, 'tahun' => $b->tahun, 'baseline_gj_ton' => $b->baseline_gj_ton, 'target_gj_ton' => $b->target_gj_ton,
                'target_tco2e_ton' => $b->target_tco2e_ton, 'catatan' => $b->catatan,
                'model' => $b->model_enb ? ['r2' => $b->model_enb['r2'] ?? null, 'cv_rmse' => $b->model_enb['cv_rmse'] ?? null,
                                            'diterima' => $b->model_enb['diterima'] ?? false] : null,
            ])->values(),
            'pic' => User::orderBy('name')->limit(500)->get(['id', 'name']),
            'boleh' => ['instruksi' => $request->user()->can('energi.instruksi'), 'target' => $request->user()->can('energi.target'),
                        'pack' => $request->user()->can('energi.bod')],
            'tautan' => ['drill' => route('energi.bod.drill'), 'simulasi' => route('energi.bod.simulasi'),
                         'layar' => route('energi.bod.layar', $request->query())],
        ]);
    }

    /** Mode layar besar — tanpa kendali ubah, disegarkan berkala (BOD-15). */
    public function layar(Request $request)
    {
        $f = $this->filter($request);
        [$dari, $sampai, $periode] = KendaliDireksi::rentang($f);
        $saring = KendaliDireksi::saring($f);
        $k = KendaliDireksi::kartu($dari, $sampai, $saring);

        return Inertia::render('Energi/BodLayar', [
            'filter' => $f + ['periode' => $periode, 'dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()],
            'kartu' => $k['kartu'], 'mutu' => $k['mutu'],
            'tren' => KendaliDireksi::tren($k['baris'], $dari, $sampai, $saring, $k['baseline']),
            'peringatan' => KendaliDireksi::peringatan($sampai, $saring, Kewajiban::daftar($saring['company_id'] ?? $request->user()->company_id), $k['kartu']),
            'portofolio' => KendaliDireksi::portofolio($saring),
            'kembali' => route('energi.bod.index', $request->query()),
        ]);
    }

    /** Drill-down satu simpul (JSON). */
    public function drill(Request $request)
    {
        $f = $this->filter($request);
        [$dari, $sampai] = KendaliDireksi::rentang($f);
        $d = $request->validate([
            'tingkat' => ['required', Rule::in(['grup', 'perusahaan', 'site', 'kelompok', 'sumber', 'harian'])],
            'kunci'   => ['nullable', 'string', 'max:200'],
        ]);

        $baris = $d['tingkat'] === 'harian' ? [] : \App\Support\Energi\Inventaris::hitung($dari, $sampai, KendaliDireksi::saring($f))['baris'];

        return response()->json(['tingkat' => $d['tingkat'], 'kunci' => $d['kunci'] ?? null,
                                 'anak' => KendaliDireksi::drill($baris, $d['tingkat'], $d['kunci'] ?? null, $dari, $sampai)]);
    }

    public function simulasi(Request $request)
    {
        $f = $this->filter($request);
        [$dari, $sampai] = KendaliDireksi::rentang($f);
        $saring = KendaliDireksi::saring($f);
        $s = $request->validate([
            'produksi' => ['nullable', 'numeric', 'between:-100,500'], 'l_bcm' => ['nullable', 'numeric', 'between:-100,500'],
            'harga_solar' => ['nullable', 'numeric', 'between:-100,500'], 'fame' => ['nullable', 'numeric', 'between:0,100'],
            'pln' => ['nullable', 'numeric', 'min:0', 'max:100'], 'genset' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'plts' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $r = \App\Support\Energi\Inventaris::ringkas(\App\Support\Energi\Inventaris::hitung($dari, $sampai, $saring)['baris']);

        return response()->json(KendaliDireksi::simulasi($r, $sampai, $saring['company_id'] ?? $request->user()->company_id, $s));
    }

    /** Instruksi BOD → TindakLanjut dengan PIC & tenggat (BOD-11). */
    public function instruksi(Request $request)
    {
        abort_unless($request->user()->can('energi.instruksi'), 403);
        $d = $request->validate([
            'judul'     => ['required', 'string', 'max:190'],
            'isi'       => ['required', 'string', 'max:2000'],
            'prioritas' => ['required', Rule::in(TindakLanjut::PRIORITAS)],
            'pic'       => ['required', 'string', 'max:150'],
            'tenggat'   => ['required', 'date', 'after_or_equal:today'],
            'konteks'   => ['nullable', 'string', 'max:500'],
            'company_id'=> ['nullable', 'exists:companies,id'],
        ]);

        $c = $request->user()->isAdmin() ? ($d['company_id'] ?? $request->user()->company_id) : $request->user()->company_id;

        TindakLanjut::create([
            'company_id' => $c, 'user_id' => $request->user()->id, 'modul' => 'energi', 'kode_pemicu' => 'BOD',
            'judul' => $d['judul'], 'kategori' => 'instruksi_bod', 'prioritas' => $d['prioritas'], 'status' => 'rencana',
            'penanggung_jawab' => $d['pic'], 'target_selesai' => $d['tenggat'],
            'uraian' => $d['isi'].(!empty($d['konteks']) ? "\n\nKonteks dasbor: ".$d['konteks'] : ''),
        ]);
        ActivityLog::write('Instruksi BOD energi', $d['judul'], 'energi');

        return back()->with('ok', 'Instruksi dicatat sebagai tindak lanjut untuk '.$d['pic'].'.');
    }

    public function ubahInstruksi(Request $request, TindakLanjut $tindak)
    {
        abort_unless($request->user()->can('energi.instruksi') && $tindak->modul === 'energi', 403);
        $d = $request->validate(['status' => ['required', Rule::in(TindakLanjut::STATUS)], 'tenggat' => ['nullable', 'date']]);

        $tindak->update(['status' => $d['status'], 'target_selesai' => $d['tenggat'] ?? $tindak->target_selesai,
                         'selesai_pada' => $d['status'] === 'selesai' ? now()->toDateString() : null]);

        return back()->with('ok', 'Instruksi diperbarui.');
    }

    /** Persetujuan target tahunan oleh BOD (BOD-17). */
    public function setujuiTarget(Request $request, EnergyBaseline $baseline)
    {
        abort_unless($request->user()->can('energi.target'), 403);
        $d = $request->validate(['putusan' => ['required', Rule::in(['setujui', 'tolak'])], 'catatan' => ['nullable', 'string', 'max:1000']]);

        $baseline->update($d['putusan'] === 'setujui'
            ? ['status' => 'disetujui_bod', 'disetujui_oleh' => $request->user()->id, 'disetujui_pada' => now()]
            : ['status' => 'ditolak', 'catatan' => trim(($baseline->catatan ?? '')."\nDitolak BOD: ".($d['catatan'] ?? '-'))]);
        ActivityLog::write(($d['putusan'] === 'setujui' ? 'Setujui' : 'Tolak').' target energi BOD', 'Tahun '.$baseline->tahun, 'energi');

        return back()->with('ok', 'Target '.$baseline->tahun.($d['putusan'] === 'setujui' ? ' disetujui direksi.' : ' dikembalikan ke Manajer Energi.'));
    }

    /** Board pack — snapshot beku dengan nomor dokumen (BOD-13). */
    public function boardPack(Request $request)
    {
        abort_unless($request->user()->can('energi.bod'), 403);
        $f = $this->filter($request);
        [$dari, $sampai] = KendaliDireksi::rentang($f);
        $saring = KendaliDireksi::saring($f);
        $c = $saring['company_id'] ?? $request->user()->company_id;

        $snap = Pelaporan::susun('BOD_PACK', $c, $dari, $sampai);
        $k = KendaliDireksi::kartu($dari, $sampai, $saring);
        $snap['kartu'] = $k['kartu'];
        $snap['mutu'] = $k['mutu'];
        $snap['filter'] = $f;
        $snap['portofolio'] = KendaliDireksi::portofolio($saring);
        $snap = json_decode(json_encode($snap), true);

        $company = $c ? \App\Models\Company::withoutGlobalScopes()->find($c) : null;
        $l = Laporan::create([
            'company_id' => $c, 'templat' => 'BOD_PACK', 'tahun' => $sampai->year,
            'periode_mulai' => $dari->toDateString(), 'periode_sampai' => $sampai->toDateString(), 'versi' => 1,
            'status' => 'terbit', 'judul' => 'Board Pack Energi & Karbon '.$dari->format('d/m/Y').' – '.$sampai->format('d/m/Y'),
            'nomor_dokumen' => \App\Support\KopDokumen::untuk('laporan-bod-energi', $company)['nomor'] ?: null,
            'snapshot' => $snap, 'hash_snapshot' => Laporan::hash($snap),
            'disusun_oleh' => $request->user()->id, 'diterbitkan_oleh' => $request->user()->id, 'diterbitkan_pada' => now(),
        ]);
        ActivityLog::write('Board pack energi', $l->judul, 'energi');

        return redirect()->route('energi.lapor.show', $l)->with('ok', 'Board pack dibekukan dengan tanggal data '.$sampai->format('d/m/Y').'.');
    }

    private function filter(Request $request): array
    {
        $f = $request->validate([
            'periode' => ['nullable', Rule::in(array_keys(KendaliDireksi::PERIODE))],
            'dari' => ['nullable', 'date'], 'sampai' => ['nullable', 'date'],
            'company' => ['nullable', 'integer'], 'site' => ['nullable', 'integer'],
        ]);

        // Perusahaan di alamat hanya boleh yang memang dapat dilihat pengguna ini.
        if (!empty($f['company']) && !in_array((int) $f['company'], array_column(KendaliDireksi::perusahaan(), 'id'), true)) {
            unset($f['company']);
        }

        return array_filter($f, fn ($v) => $v !== null && $v !== '');
    }
}
