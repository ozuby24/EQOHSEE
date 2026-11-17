<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, KoObject, P2hPeriksa, P2hUnit};
use App\Support\{Berkas, P2h, Waktu};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * P2H di versi web: daftar unit yang wajib diperiksa, catatan P2H
 * terbaru, dan pelepasan tahan. Pengisiannya ada di mode lapangan —
 * P2H diisi di samping unitnya, bukan di meja.
 */
class P2hController extends Controller
{
    public function index(Request $request)
    {
        $kini = Waktu::kini();
        $shift = P2h::shiftSekarang($kini);
        $u = $request->user();

        $unit = P2hUnit::with(['terakhir', 'objek'])->orderByDesc('aktif')->orderByDesc('status')->orderBy('kode')->get();

        $periksa = P2hPeriksa::with(['unit', 'workOrder'])->latest('id')->limit(40)->get();

        return Inertia::render('Maintenance/P2h', [
            'judul'    => 'P2H Unit',
            'subjudul' => 'Pemeriksaan pra-operasi tiap shift — diisi dari mode lapangan',
            'unit' => $unit->map(fn (P2hUnit $x) => [
                'id' => $x->id, 'kode' => $x->kode, 'nama' => $x->nama, 'jenis' => $x->jenis,
                'jenisNama' => $x->namaJenis(), 'keterangan' => $x->keterangan, 'hm' => $x->hm,
                'status' => $x->status, 'aktif' => $x->aktif,
                'ko_object_id' => $x->ko_object_id, 'objek' => $x->objek?->kode,
                'ditahanSejak' => Waktu::lokal($x->ditahan_sejak)?->format('d M Y H:i'),
                'ditahanKarena' => $x->ditahan_karena,
                'dilepas' => $x->dilepas_pada ? Waktu::lokal($x->dilepas_pada)->format('d M Y H:i').' — '.$x->catatan_lepas : null,
                'sudahShiftIni' => $x->terakhir && $x->terakhir->tanggal?->isSameDay($kini) && $x->terakhir->shift === $shift,
                'terakhir' => $x->terakhir ? $x->terakhir->tanggal?->translatedFormat('j M').' · '.(P2h::SHIFT[$x->terakhir->shift] ?? '').' · '.$x->terakhir->hasil : null,
            ])->values(),
            'periksa' => $periksa->map(fn (P2hPeriksa $p) => [
                'id' => $p->id, 'tanggal' => $p->tanggal?->translatedFormat('j M Y'),
                'shift' => P2h::SHIFT[$p->shift] ?? $p->shift, 'unit' => $p->unit?->kode,
                'operator' => $p->operator, 'hm' => $p->hm, 'hasil' => $p->hasil,
                'ok' => $p->jumlah_ok, 'tidak' => $p->jumlah_tidak, 'na' => $p->jumlah_na,
                'gagal' => collect($p->jawaban)->where('nilai', P2h::TIDAK)
                    ->map(fn ($j) => ($j['kritis'] ? '[KRITIS] ' : '').$j['uraian'])->values(),
                'catatan' => $p->catatan,
                'foto' => Berkas::daftarUrl($p, 'p2h'),
                'wo' => $p->workOrder ? ($p->workOrder->nomor ?: 'WO-'.$p->workOrder->id).' · '.$p->workOrder->status : null,
            ])->values(),
            'jenis'   => P2h::JENIS,
            'objek'   => KoObject::orderBy('kode')->get(['id', 'kode', 'nama'])->map(fn ($o) => ['id' => $o->id, 'nama' => "{$o->kode} · {$o->nama}"])->values(),
            'ringkas' => [
                'unit'     => $unit->where('aktif', true)->count(),
                'ditahan'  => $unit->where('aktif', true)->where('status', P2h::DITAHAN)->count(),
                'belum'    => $unit->where('aktif', true)->where('status', P2h::LAIK)
                    ->filter(fn ($x) => !($x->terakhir && $x->terakhir->tanggal?->isSameDay($kini) && $x->terakhir->shift === $shift))->count(),
                'shift'    => P2h::SHIFT[$shift],
            ],
            'dapatMelepas' => $u->isAdmin() || $u->isKtt() || $u->isOhse(),
            'tautan' => [
                'simpan'   => route('maintenance.p2h.simpan'),
                'ubah'     => route('maintenance.p2h.ubah', ['unit' => '__ID__']),
                'lepas'    => route('lapangan.p2h.lepas', ['unit' => '__ID__']),
                'lapangan' => route('lapangan.p2h'),
            ],
        ]);
    }

    public function simpan(Request $request)
    {
        $d = $this->validasi($request);
        $unit = P2hUnit::create($d + ['status' => P2h::LAIK, 'aktif' => true]);
        ActivityLog::write('Tambah unit P2H', $unit->kode, 'maintenance');

        return back()->with('ok', "Unit {$unit->kode} ditambahkan.");
    }

    public function ubah(Request $request, P2hUnit $unit)
    {
        $d = $this->validasi($request, $unit) + ['aktif' => $request->boolean('aktif', true)];
        $unit->update($d);
        ActivityLog::write('Ubah unit P2H', $unit->kode, 'maintenance');

        return back()->with('ok', "Unit {$unit->kode} diperbarui.");
    }

    private function validasi(Request $request, ?P2hUnit $unit = null): array
    {
        $perusahaan = $unit?->company_id ?? $request->user()->company_id;

        return $request->validate([
            'kode'  => ['required', 'string', 'max:30',
                Rule::unique('p2h_unit', 'kode')->where('company_id', $perusahaan)->ignore($unit?->id)],
            'nama'  => ['required', 'string', 'max:120'],
            'jenis' => ['required', Rule::in(array_keys(P2h::JENIS))],
            'keterangan'   => ['nullable', 'string', 'max:150'],
            'hm'           => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'ko_object_id' => ['nullable', 'integer', Rule::exists('ko_objects', 'id')->where('company_id', $perusahaan)],
        ]);
    }
}
