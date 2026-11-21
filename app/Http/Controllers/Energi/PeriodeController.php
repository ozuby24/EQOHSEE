<?php

namespace App\Http\Controllers\Energi;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, EnergyFuelLog};
use App\Models\Energi\{Koreksi, Periode};
use App\Support\Energi\{AlurPeriode, Anomali};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Verifikasi data harian, kunci periode bulanan, dan koreksi (PRD E5, FSD §7, §8.2).
 */
class PeriodeController extends Controller
{
    use BantuEnergi;

    public function index(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $tahun = $request->integer('tahun') ?: now()->year;
        $bulan = $request->integer('bulan') ?: now()->month;

        $periode = Periode::untuk($c, $tahun, $bulan);
        $mulai = $periode->mulai();
        $sampai = $periode->sampai();

        // Kalender kelengkapan setahun: tiap bulan satu sel.
        $kalender = [];
        foreach (range(1, 12) as $b) {
            $m = Carbon::create($tahun, $b, 1);
            if ($m->gt(now())) { $kalender[] = ['bulan' => $b, 'label' => $m->translatedFormat('M'), 'keadaan' => 'mendatang']; continue; }

            $p = Periode::withoutGlobalScopes()->where('company_id', $c)->where('tahun', $tahun)->where('bulan', $b)->first();
            $l = AlurPeriode::kelengkapan($c, $m, $m->copy()->endOfMonth()->startOfDay());
            $belum = 0;
            foreach (array_keys(AlurPeriode::MODEL) as $j) {
                $belum += AlurPeriode::kueri($j, $c, $m, $m->copy()->endOfMonth())->whereIn('status', ['draf', 'diajukan', 'ditolak'])->count();
            }

            $kalender[] = ['bulan' => $b, 'label' => $m->translatedFormat('M'), 'kelengkapan' => $l['persen'], 'belum' => $belum,
                'keadaan' => $p?->terkunci() ? 'terkunci' : (($l['persen'] ?? 0) < 50 ? 'kosong' : ($belum > 0 ? 'belum' : 'lengkap'))];
        }

        $antre = [];
        foreach (AlurPeriode::MODEL as $jenis => [$kelas, $label]) {
            $q = AlurPeriode::kueri($jenis, $c, $mulai, $sampai)->whereIn('status', ['diajukan', 'ditolak', 'draf'])->orderBy('tanggal');
            if ($kelas === EnergyFuelLog::class) $q->with('equipment:id,kode,nama');

            foreach ($q->limit(300)->get() as $b) {
                $antre[] = ['jenis' => $jenis, 'label' => $label, 'id' => $b->id, 'tanggal' => $b->tanggal->toDateString(),
                            'status' => $b->status, 'alasan_tolak' => $b->alasan_tolak, 'sumber_data' => $b->sumber_data,
                            'uraian' => $this->uraian($jenis, $b)];
            }
        }

        $anomali = collect(Anomali::bbm($sampai->copy()->min(now()->startOfDay()), 30, $c))
            ->filter(fn ($a) => $a['tanggal'] >= $mulai->toDateString() && $a['tanggal'] <= $sampai->toDateString())
            ->values()->all();

        return Inertia::render('Energi/Periode', [
            'tahun' => $tahun, 'bulan' => $bulan,
            'periode' => ['id' => $periode->id, 'label' => $periode->label(), 'status' => $periode->status,
                          'dikunci_pada' => $periode->dikunci_pada?->format('d/m/Y H:i'), 'pengunci' => $periode->pengunci?->name,
                          'kelengkapan_persen' => $periode->kelengkapan_persen],
            'syarat' => $periode->terkunci() ? null : AlurPeriode::syaratKunci($periode),
            'kalender' => $kalender,
            'antre' => $antre,
            'anomali' => $anomali,
            'koreksi' => Koreksi::with(['pengaju:id,name', 'pemutus:id,name', 'periode'])->orderByDesc('id')->limit(50)->get()->map(fn (Koreksi $k) => [
                'id' => $k->id, 'periode' => $k->periode?->label(), 'subjek' => class_basename($k->subjek_type).' #'.$k->subjek_id,
                'nilai_lama' => $k->nilai_lama, 'nilai_baru' => $k->nilai_baru, 'alasan' => $k->alasan, 'status' => $k->status,
                'pengaju' => $k->pengaju?->name, 'pemutus' => $k->pemutus?->name, 'catatan_putusan' => $k->catatan_putusan,
                'diajukan_pada' => $k->created_at?->format('d/m/Y'),
            ])->values(),
            'kolomKoreksi' => collect(AlurPeriode::KOLOM_KOREKSI)->mapWithKeys(fn ($k, $kelas) => [array_search($kelas, array_map(fn ($m) => $m[0], AlurPeriode::MODEL), true) => $k]),
            'boleh' => ['verifikasi' => $request->user()->can('energi.verifikasi'), 'kunci' => $request->user()->can('energi.setujui'),
                        'koreksi' => $request->user()->can('energi.verifikasi'), 'putus' => $request->user()->can('energi.setujui')],
            'perusahaan' => $c, 'namaPerusahaan' => $this->namaPerusahaan($c), 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    /** Verifikasi atau tolak massal (FSD §8.2): satu centang untuk satu tanggal penuh. */
    public function verifikasi(Request $request)
    {
        abort_unless($request->user()->can('energi.verifikasi'), 403);
        $d = $request->validate([
            'aksi'  => ['required', Rule::in(['verifikasi', 'tolak'])],
            'alasan'=> ['required_if:aksi,tolak', 'nullable', 'string', 'max:500'],
            'baris' => ['required', 'array', 'min:1', 'max:1000'],
            'baris.*.jenis' => ['required', Rule::in(array_keys(AlurPeriode::MODEL))],
            'baris.*.id'    => ['required', 'integer'],
        ], ['alasan.required_if' => 'Penolakan wajib disertai alasan, supaya penginput tahu apa yang harus dibetulkan.']);

        $n = 0;
        foreach ($d['baris'] as $b) {
            [$kelas] = AlurPeriode::MODEL[$b['jenis']];
            // Lewat model ber-scope: baris perusahaan lain tidak ditemukan.
            $m = $kelas::find($b['id']);
            if (!$m || in_array($m->status, ['terkunci', 'terverifikasi'], true) && $d['aksi'] === 'verifikasi') continue;
            if ($m->status === 'terkunci') continue;

            $m->update($d['aksi'] === 'verifikasi'
                ? ['status' => 'terverifikasi', 'diverifikasi_oleh' => $request->user()->id, 'diverifikasi_pada' => now(), 'alasan_tolak' => null]
                : ['status' => 'ditolak', 'alasan_tolak' => $d['alasan']]);
            $n++;
        }

        return back()->with('ok', $d['aksi'] === 'verifikasi' ? "{$n} baris terverifikasi." : "{$n} baris ditolak dan dikembalikan ke penginput.");
    }

    public function kunci(Request $request, Periode $periode)
    {
        abort_unless($request->user()->can('energi.setujui'), 403);
        if ($periode->terkunci()) return back()->with('ok', 'Periode sudah terkunci.');

        $s = AlurPeriode::syaratKunci($periode);
        if (!$s['boleh']) {
            $gagal = collect($s['syarat'])->where('ok', false)->pluck('rincian')->implode(' ');

            return back()->withErrors(['periode' => 'Periode belum dapat dikunci: '.$gagal]);
        }

        AlurPeriode::kunci($periode, $request->user()->id);

        return back()->with('ok', 'Periode '.$periode->label().' terkunci. Perubahan berikutnya hanya lewat koreksi.');
    }

    /** Ajukan koreksi atas baris terkunci. */
    public function ajukanKoreksi(Request $request)
    {
        abort_unless($request->user()->can('energi.verifikasi'), 403);
        $d = $request->validate([
            'jenis'  => ['required', Rule::in(array_keys(AlurPeriode::MODEL))],
            'id'     => ['required', 'integer'],
            'nilai'  => ['required', 'array', 'min:1'],
            'nilai.*'=> ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'alasan' => ['required', 'string', 'max:2000'],
        ]);

        [$kelas] = AlurPeriode::MODEL[$d['jenis']];
        $baris = $kelas::findOrFail($d['id']);
        abort_unless($baris->status === 'terkunci', 422, 'Koreksi hanya untuk baris pada periode terkunci; baris lain cukup diubah langsung.');

        $boleh = AlurPeriode::KOLOM_KOREKSI[$kelas];
        $baru = array_intersect_key($d['nilai'], array_flip($boleh));
        abort_if($baru === [], 422, 'Tidak ada kolom yang boleh dikoreksi.');

        $c = $baris->perusahaanCatatan();
        $p = Periode::untuk($c, $baris->tanggal->year, $baris->tanggal->month);

        Koreksi::withoutGlobalScopes()->create([
            'company_id' => $c, 'periode_id' => $p->id, 'subjek_type' => $kelas, 'subjek_id' => $baris->id,
            'nilai_lama' => array_intersect_key($baris->only($boleh), $baru), 'nilai_baru' => $baru,
            'alasan' => $d['alasan'], 'status' => 'diajukan', 'diajukan_oleh' => $request->user()->id,
        ]);

        return back()->with('ok', 'Koreksi diajukan — menunggu persetujuan Manajer Energi/Petugas GRK/KTT.');
    }

    public function putusKoreksi(Request $request, Koreksi $koreksi)
    {
        abort_unless($request->user()->can('energi.setujui'), 403);
        abort_unless($koreksi->status === 'diajukan', 422, 'Koreksi ini sudah diputus.');
        abort_if(!$request->user()->isAdmin() && (int) $koreksi->diajukan_oleh === (int) $request->user()->id, 403,
            'Koreksi diputus oleh orang lain daripada pengajunya.');

        $d = $request->validate(['putusan' => ['required', Rule::in(['setujui', 'tolak'])], 'catatan' => ['nullable', 'string', 'max:500']]);

        if ($d['putusan'] === 'setujui') {
            AlurPeriode::setujuiKoreksi($koreksi, $request->user()->id, $d['catatan'] ?? null);

            return back()->with('ok', 'Koreksi diterapkan. Laporan terbit yang memakai periode ini ditandai perlu revisi.');
        }

        $koreksi->update(['status' => 'ditolak', 'diputus_oleh' => $request->user()->id, 'diputus_pada' => now(),
                          'catatan_putusan' => $d['catatan'] ?? null]);
        ActivityLog::write('Tolak koreksi energi', class_basename($koreksi->subjek_type).' #'.$koreksi->subjek_id, 'energi');

        return back()->with('ok', 'Koreksi ditolak.');
    }

    private function uraian(string $jenis, $b): string
    {
        $n = fn ($v, $d = 1) => number_format((float) $v, $d, ',', '.');

        return match ($jenis) {
            'bbm'      => ($b->equipment?->kode ?? 'Unit').' — '.$n($b->liter).' L, '.$n($b->hm).' HM',
            'listrik'  => (\App\Support\Energi::AREA[$b->area] ?? $b->area).' ('.$b->sumber.') — '.$n($b->kwh, 0).' kWh',
            'lain'     => \App\Support\Energi\Katalog::label($b->jenis).' — '.$n($b->jumlah).' '.$b->satuan,
            'produksi' => $n($b->ton, 0).' ton, '.$n($b->bcm, 0).' BCM',
            'rekon'    => 'Disalurkan '.$n($b->disalurkan_liter, 0).' L',
        };
    }
}
