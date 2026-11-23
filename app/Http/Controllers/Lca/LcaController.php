<?php

namespace App\Http\Controllers\Lca;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Energi\BantuEnergi;
use App\Models\ActivityLog;
use App\Models\Lca\{Faktor, Hasil, Inventori, Jejak, Skenario, Studi};
use App\Support\Lca\{Katalog, Mesin};
use App\Support\Perusahaan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Life Cycle Assessment — studi daur hidup produk tambang.
 *
 * Empat fase ISO 14040/14044 dipetakan ke empat bagian halaman studi:
 * goal & scope (kepala studi), inventori (LCI, lima tahap), dampak
 * (LCIA enam kategori + hotspot), dan interpretasi (skenario &
 * tinjauan kritis). Studi terverifikasi dikunci; perubahan sesudahnya
 * dibuat sebagai versi baru yang menunjuk induknya.
 */
class LcaController extends Controller
{
    use BantuEnergi;

    /* ═══════════ dasbor ═══════════ */

    public function index(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $semua = Studi::query()->when($c, fn ($q) => $q->where('company_id', $c))->orderByDesc('updated_at')->get();
        $pilih = $request->integer('studi') ?: null;
        $utama = $pilih ? $semua->firstWhere('id', $pilih) : ($semua->firstWhere('status', 'terbit') ?? $semua->firstWhere('status', 'terverifikasi') ?? $semua->first());

        $hasil = $utama ? Hasil::where('studi_id', $utama->id)->get() : collect();
        $perTahap = collect(Katalog::TAHAP)->map(fn ($t, $k) => [
            'tahap' => $k, 'label' => $t['label'], 'pendek' => $t['pendek'], 'warna' => $t['warna'],
            'gwp' => (float) ($hasil->where('kategori', 'gwp')->firstWhere('tahap', $k)?->nilai ?? 0),
            'porsi' => (float) ($hasil->where('kategori', 'gwp')->firstWhere('tahap', $k)?->porsi ?? 0),
        ])->values()->all();
        $dampak = collect(Katalog::DAMPAK)->map(fn ($d, $k) => [
            'kategori' => $k, 'kode' => $d['kode'], 'nama' => $d['nama'], 'satuan' => $d['satuan'],
            'nilai' => (float) ($utama?->{'total_'.$k} ?? 0),
            'normal' => (float) $hasil->where('kategori', $k)->sum('normal'),
        ])->values()->all();

        $kodeListrik = $this->kodeListrik($c);

        return Inertia::render('Lca/Dasbor', [
            'utama' => $utama ? $this->ringkas($utama) : null,
            'perTahap' => $perTahap, 'dampak' => $dampak,
            'hotspot' => $utama ? Mesin::hotspot($utama, 6) : [],
            'portofolio' => $semua->where('status', '!=', 'arsip')->map(fn (Studi $s) => $this->ringkas($s) + [
                'tahap' => Hasil::where('studi_id', $s->id)->where('kategori', 'gwp')->pluck('nilai', 'tahap'),
            ])->values()->all(),
            'status' => collect(Katalog::STATUS)->map(fn ($v, $k) => ['status' => $k, 'label' => $v['label'], 'jumlah' => $semua->where('status', $k)->count()])->values()->all(),
            'skenarioTerbaik' => $utama ? Skenario::where('studi_id', $utama->id)->orderByDesc('reduksi_pct')->first() : null,
            'tren' => Mesin::trenEnergi($c, $kodeListrik),
            'emisiTahunan' => round($semua->whereIn('status', ['terverifikasi', 'terbit'])->sum(fn ($s) => $s->tonTahunan()), 1),
            'harga' => Katalog::HARGA,
            'pilihanStudi' => $semua->map(fn ($s) => ['id' => $s->id, 'label' => $s->kode.' v'.$s->versi.' · '.$s->produk])->values()->all(),
            'perusahaan' => $c, 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    /* ═══════════ daftar & buat studi ═══════════ */

    public function studi(Request $request)
    {
        $c = $this->perusahaanAktif($request);

        return Inertia::render('Lca/Studi', [
            'studi' => Studi::query()->when($c, fn ($q) => $q->where('company_id', $c))->orderByDesc('updated_at')->get()
                ->map(fn (Studi $s) => $this->ringkas($s) + ['baris' => Inventori::where('studi_id', $s->id)->count()])->values()->all(),
            'pilihan' => Katalog::pilihan(),
            'kodeBerikut' => $this->kodeBaru($c),
            'perusahaan' => $c, 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    public function simpan(Request $request)
    {
        $data = $this->pemilik($request->validate($this->aturan()));
        $u = $request->user();
        if ($u->isAdmin() && empty($data['company_id'])) $data['company_id'] = Perusahaan::terpilih($u) ?? $u->company_id;
        $data += ['user_id' => $u->id, 'kode' => $this->kodeBaru($data['company_id'] ?? null), 'versi' => '1.0', 'status' => 'draf'];

        $s = Studi::create($data);
        Mesin::hitungUlang($s);
        Jejak::catat($s, 'buat', 'Studi dibuat: '.$s->judul);
        ActivityLog::write('Buat studi LCA', $s->kode.' — '.$s->judul, 'lca');

        return redirect()->route('lca.rincian', $s)->with('ok', 'Studi '.$s->kode.' dibuat. Isi inventorinya per tahap, atau tarik dari Energy Performance.');
    }

    public function perbarui(Request $request, Studi $studi)
    {
        abort_if($studi->terkunci(), 403, 'Studi terverifikasi dikunci. Buat versi baru untuk mengubahnya.');
        $data = $request->validate($this->aturan());
        unset($data['company_id']);
        $lama = $studi->only(array_keys($data));
        $studi->update($data);
        Jejak::catat($studi, 'ubah_scope', 'Goal & scope diperbarui', $lama, $data);

        return back()->with('ok', 'Goal & scope diperbarui.');
    }

    /* ═══════════ rincian ═══════════ */

    public function rincian(Studi $studi)
    {
        $inv = Inventori::where('studi_id', $studi->id)->with(['faktor' => fn ($q) => $q->withoutGlobalScopes()])->orderBy('tahap')->orderByDesc('gwp')->get();
        $hasil = Hasil::where('studi_id', $studi->id)->get();

        return Inertia::render('Lca/Rincian', [
            'studi' => $this->ringkas($studi) + $studi->only(['tujuan', 'audiens', 'sku', 'fasilitas', 'wilayah', 'aliran_ref_kg', 'metode', 'alokasi',
                'cutoff_pct', 'catatan_tinjau', 'pemverifikasi']) + [
                'periode_mulai' => $studi->periode_mulai?->toDateString(), 'periode_selesai' => $studi->periode_selesai?->toDateString(),
                'diverifikasi_at' => $studi->diverifikasi_at?->toIso8601String(), 'terkunci' => $studi->terkunci(),
                'induk' => $studi->induk_id ? Studi::find($studi->induk_id)?->only(['id', 'kode', 'versi']) : null,
                'total' => collect(Mesin::KOLOM)->mapWithKeys(fn ($k) => [$k => (float) $studi->{'total_'.$k}]),
            ],
            'inventori' => $inv->map(fn (Inventori $i) => $i->only(['id', 'tahap', 'nama', 'jumlah', 'satuan', 'jumlah_normal', 'jarak_km',
                'daur_ulang_pct', 'alokasi_pct', 'gwp', 'ap', 'ep', 'wsf', 'ced', 'odp', 'dqr', 'ketidakpastian', 'sumber_data', 'catatan',
                'p_reliabilitas', 'p_kelengkapan', 'p_temporal', 'p_geografis', 'p_teknologi'])
                + ['faktor' => $i->faktor?->only(['kode', 'nama', 'satuan', 'gwp', 'perlu_cocok', 'sumber'])])->values()->all(),
            'hasil' => $hasil->map->only(['kategori', 'tahap', 'nilai', 'porsi', 'normal'])->values()->all(),
            'hotspot' => Mesin::hotspot($studi),
            'skenario' => Skenario::where('studi_id', $studi->id)->orderByDesc('reduksi_pct')->get(),
            'iso' => Mesin::periksaIso($studi),
            'siap' => Mesin::siap($studi),
            'jejak' => Jejak::where('studi_id', $studi->id)->orderByDesc('created_at')->orderByDesc('id')->take(30)->get()
                ->map(fn (Jejak $j) => ['aksi' => $j->aksi, 'ringkas' => $j->ringkas, 'pada' => $j->created_at?->toIso8601String(),
                    'oleh' => $j->user_id ? \App\Models\User::withoutGlobalScopes()->find($j->user_id)?->name : 'Sistem'])->all(),
            'versi' => Studi::where('kode', $studi->kode)->orderBy('id')->get(['id', 'versi', 'status', 'total_gwp'])->all(),
            'faktor' => Faktor::query()->where('aktif', true)->orderBy('kategori')->orderBy('nama')
                ->get(['id', 'kode', 'nama', 'kategori', 'tahap_bawaan', 'satuan', 'gwp', 'perlu_cocok', 'company_id'])->all(),
            'pilihan' => Katalog::pilihan(),
            'boleh' => ['verifikasi' => $studi->user_id !== auth()->id() || auth()->user()->isAdmin()],
        ]);
    }

    /* ═══════════ inventori ═══════════ */

    public function pratinjau(Request $request, Studi $studi)
    {
        $d = $request->validate($this->aturanItem());
        $f = Faktor::query()->findOrFail($d['faktor_id']);

        return response()->json(Mesin::pratinjau($f, $d) + ['faktor' => $f->only(['kode', 'nama', 'satuan', 'gwp'])]);
    }

    public function simpanItem(Request $request, Studi $studi)
    {
        abort_if($studi->terkunci(), 403, 'Studi terverifikasi dikunci.');
        $d = $request->validate($this->aturanItem());
        $i = Mesin::simpanItem($studi, $d);
        Jejak::catat($studi, 'tambah_baris', 'Tambah '.$i->nama.' ('.number_format($i->gwp, 3, ',', '.').' kg CO₂e)', null, $i->only(['tahap', 'nama', 'jumlah', 'satuan', 'gwp']));

        return back()->with('ok', 'Baris inventori ditambahkan: '.number_format($i->gwp, 3, ',', '.').' kg CO₂e per unit fungsional.');
    }

    public function hapusItem(Studi $studi, Inventori $item)
    {
        abort_if($studi->terkunci() || $item->studi_id !== $studi->id, 403);
        Jejak::catat($studi, 'hapus_baris', 'Hapus '.$item->nama, $item->only(['tahap', 'nama', 'jumlah', 'satuan', 'gwp']));
        $item->delete();
        Mesin::hitungUlang($studi);

        return back()->with('ok', 'Baris dihapus dan dampak dihitung ulang.');
    }

    public function imporEnergi(Request $request, Studi $studi)
    {
        abort_if($studi->terkunci(), 403);
        $kode = $request->validate(['listrik' => ['nullable', 'string', 'max:60']])['listrik'] ?? $this->kodeListrik($studi->company_id);
        $h = Mesin::imporEnergi($studi, $kode);
        Jejak::catat($studi, 'impor_energi', $h['catatan']);

        return back()->with($h['dibuat'] ? 'ok' : 'galat', $h['catatan']);
    }

    /* ═══════════ skenario ═══════════ */

    public function simulasi(Request $request, Studi $studi)
    {
        return response()->json(Mesin::simulasi($studi, $request->validate($this->aturanTuas())));
    }

    public function simpanSkenario(Request $request, Studi $studi)
    {
        $d = $request->validate($this->aturanTuas() + ['nama' => ['required', 'string', 'max:150'], 'deskripsi' => ['nullable', 'string', 'max:1000']]);
        $sk = Mesin::simpanSkenario($studi, $d['nama'], $d, $d['deskripsi'] ?? null);
        Jejak::catat($studi, 'skenario', 'Skenario "'.$sk->nama.'" disimpan: −'.number_format($sk->reduksi_pct, 1, ',', '').'%');

        return back()->with('ok', 'Skenario disimpan: turun '.number_format($sk->reduksi_pct, 1, ',', '').'%.');
    }

    public function hapusSkenario(Studi $studi, Skenario $skenario)
    {
        abort_if($skenario->studi_id !== $studi->id, 404);
        $skenario->delete();

        return back()->with('ok', 'Skenario dihapus.');
    }

    /* ═══════════ alur verifikasi ═══════════ */

    public function status(Request $request, Studi $studi)
    {
        $d = $request->validate(['aksi' => ['required', Rule::in(['ajukan', 'kembalikan', 'verifikasi', 'terbitkan', 'arsipkan'])],
                                 'catatan' => ['nullable', 'string', 'max:2000']]);
        $u = $request->user();

        [$dari, $ke] = match ($d['aksi']) {
            'ajukan' => [['draf'], 'tinjau'], 'kembalikan' => [['tinjau'], 'draf'], 'verifikasi' => [['tinjau'], 'terverifikasi'],
            'terbitkan' => [['terverifikasi'], 'terbit'], 'arsipkan' => [['terbit', 'terverifikasi', 'draf'], 'arsip'],
        };
        if (!in_array($studi->status, $dari, true)) return back()->with('galat', 'Status studi tidak memungkinkan langkah ini.');
        if (in_array($d['aksi'], ['ajukan', 'verifikasi'], true) && !Mesin::siap($studi)) {
            return back()->with('galat', 'Butir wajib tinjauan ISO 14044 belum lulus.');
        }
        if ($d['aksi'] === 'verifikasi' && $studi->user_id === $u->id && !$u->isAdmin()) {
            return back()->with('galat', 'Pembuat studi tidak dapat memverifikasi studinya sendiri.');
        }
        if ($d['aksi'] === 'kembalikan' && blank($d['catatan'] ?? null)) {
            return back()->with('galat', 'Tuliskan catatan tinjauan saat mengembalikan studi.');
        }

        $studi->forceFill(['status' => $ke] + match ($d['aksi']) {
            'verifikasi' => ['pemverifikasi' => $u->name, 'diverifikasi_at' => now(), 'catatan_tinjau' => $d['catatan'] ?? $studi->catatan_tinjau],
            'kembalikan' => ['catatan_tinjau' => $d['catatan']],
            default => [],
        })->save();
        Jejak::catat($studi, $d['aksi'], 'Status → '.Katalog::STATUS[$ke]['label'].(filled($d['catatan'] ?? null) ? ': '.$d['catatan'] : ''));

        return back()->with('ok', 'Status studi: '.Katalog::STATUS[$ke]['label'].'.');
    }

    /** Versi baru dari studi terkunci: inventori disalin apa adanya, berikut salinan faktornya. */
    public function versi(Studi $studi)
    {
        $baru = DB::transaction(function () use ($studi) {
            $maks = Studi::where('kode', $studi->kode)->get()->max(fn ($s) => (float) $s->versi);
            $b = $studi->replicate(['status', 'pemverifikasi', 'diverifikasi_at', 'catatan_tinjau']);
            $b->fill(['versi' => number_format($maks + 0.1, 1, '.', ''), 'status' => 'draf', 'induk_id' => $studi->id, 'user_id' => auth()->id()])->save();
            foreach (Inventori::where('studi_id', $studi->id)->get() as $i) {
                $x = $i->replicate(); $x->studi_id = $b->id; $x->save();
            }
            Mesin::hitungUlang($b);
            Jejak::catat($b, 'versi', 'Versi '.$b->versi.' dibuat dari v'.$studi->versi);

            return $b;
        });

        return redirect()->route('lca.rincian', $baru)->with('ok', 'Versi '.$baru->versi.' dibuat sebagai draf.');
    }

    public function csv(Studi $studi)
    {
        $inv = Inventori::where('studi_id', $studi->id)->with(['faktor' => fn ($q) => $q->withoutGlobalScopes()])->orderBy('tahap')->get();

        return response()->streamDownload(function () use ($studi, $inv) {
            $f = fopen('php://output', 'w');
            fputcsv($f, ['studi', 'versi', 'unit_fungsional', 'tahap', 'baris', 'jumlah', 'satuan', 'faktor', 'gwp_kgco2e', 'ap', 'ep', 'wsf_m3', 'ced_mj', 'odp', 'dqr', 'sumber_data']);
            foreach ($inv as $i) {
                fputcsv($f, [$studi->kode, $studi->versi, $studi->fu_nilai.' '.$studi->fu_label, $i->tahap, $i->nama, $i->jumlah, $i->satuan,
                    $i->faktor?->kode, $i->gwp, $i->ap, $i->ep, $i->wsf, $i->ced, $i->odp, $i->dqr, $i->sumber_data]);
            }
            fclose($f);
        }, $studi->kode.'-v'.$studi->versi.'-inventori.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ═══════════ faktor ═══════════ */

    public function faktor(Request $request)
    {
        return Inertia::render('Lca/Faktor', [
            'faktor' => Faktor::query()->orderBy('kategori')->orderBy('nama')->get()->map(fn (Faktor $f) => $f->toArray() + [
                'bersama' => $f->company_id === null,
                'dipakai' => Inventori::withoutGlobalScopes()->where('faktor_id', $f->id)->count(),
            ])->values()->all(),
            'pilihan' => Katalog::pilihan(),
        ]);
    }

    public function simpanFaktor(Request $request)
    {
        $d = $this->pemilik($request->validate($this->aturanFaktor()));
        $u = $request->user();
        if ($u->isAdmin() && empty($d['company_id'])) $d['company_id'] = Perusahaan::terpilih($u) ?? $u->company_id;
        abort_if(Faktor::withoutGlobalScopes()->where('company_id', $d['company_id'] ?? null)->where('kode', $d['kode'])->exists(), 422, 'Kode faktor sudah dipakai.');
        Faktor::create($d + ['perlu_cocok' => false, 'aktif' => true]);

        return back()->with('ok', 'Faktor '.$d['kode'].' ditambahkan.');
    }

    public function cocokkanFaktor(Request $request, Faktor $faktor)
    {
        $faktor->forceFill(['perlu_cocok' => false])->save();
        ActivityLog::write('Cocokkan faktor LCA', $faktor->kode, 'lca');

        return back()->with('ok', 'Faktor '.$faktor->kode.' ditandai sudah dicocokkan dengan sumbernya.');
    }

    /* ═══════════ bantuan ═══════════ */

    private function ringkas(Studi $s): array
    {
        return [
            'id' => $s->id, 'kode' => $s->kode, 'versi' => $s->versi, 'judul' => $s->judul, 'produk' => $s->produk,
            'fu' => rtrim(rtrim(number_format($s->fu_nilai, 3, ',', '.'), '0'), ',').' '.$s->fu_label, 'fu_nilai' => $s->fu_nilai, 'fu_label' => $s->fu_label,
            'batas' => $s->batas_sistem, 'batas_label' => Katalog::BATAS[$s->batas_sistem] ?? $s->batas_sistem,
            'status' => $s->status, 'status_label' => Katalog::STATUS[$s->status]['label'] ?? $s->status,
            'gwp' => $s->total_gwp, 'ced' => $s->total_ced, 'wsf' => $s->total_wsf, 'ap' => $s->total_ap, 'ep' => $s->total_ep, 'odp' => $s->total_odp,
            'dqr' => $s->dqr, 'mutu' => Katalog::mutu($s->dqr), 'baseline' => $s->baseline_gwp, 'target' => $s->target_gwp,
            'volume' => $s->volume_tahunan, 'ton_tahun' => $s->tonTahunan(), 'fasilitas' => $s->fasilitas,
            'periode' => $s->periode_mulai?->translatedFormat('M Y').' – '.$s->periode_selesai?->translatedFormat('M Y'),
            'diperbarui' => $s->updated_at?->toIso8601String(),
        ];
    }

    private function kodeBaru(?int $c): string
    {
        $th = now()->format('Y');
        $n = Studi::withoutGlobalScopes()->where('company_id', $c)->where('kode', 'like', "LCA-{$th}-%")->distinct()->count('kode') + 1;
        do { $kode = sprintf('LCA-%s-%03d', $th, $n++); } while (Studi::withoutGlobalScopes()->where('company_id', $c)->where('kode', $kode)->exists());

        return $kode;
    }

    /** Faktor grid sesuai wilayah studi utama perusahaan; bawaan Kalimantan. */
    private function kodeListrik(?int $c): string
    {
        $w = Studi::withoutGlobalScopes()->when($c, fn ($q) => $q->where('company_id', $c))->latest('id')->value('wilayah') ?? '';
        return match (true) {
            str_starts_with($w, 'ID-SUL'), str_starts_with($w, 'ID-SN') => 'EF-EL-SUL',
            str_starts_with($w, 'ID-SUM'), str_starts_with($w, 'ID-SB') => 'EF-EL-SUM',
            str_starts_with($w, 'ID-JW') => 'EF-EL-JAMALI',
            str_starts_with($w, 'ID-MA') => 'EF-EL-CAPTIVE',
            default => 'EF-EL-KAL',
        };
    }

    private function aturan(): array
    {
        return [
            'company_id' => ['nullable', 'exists:companies,id'],
            'judul' => ['required', 'string', 'max:180'], 'produk' => ['required', 'string', 'max:150'],
            'sku' => ['nullable', 'string', 'max:80'], 'fasilitas' => ['required', 'string', 'max:150'],
            'wilayah' => ['required', 'string', 'max:40'],
            'fu_nilai' => ['required', 'numeric', 'gt:0', 'max:1000000'], 'fu_label' => ['required', 'string', 'max:100'],
            'aliran_ref_kg' => ['required', 'numeric', 'gt:0'],
            'batas_sistem' => ['required', Rule::in(array_keys(Katalog::BATAS))],
            'metode' => ['required', Rule::in(array_keys(Katalog::METODE))],
            'alokasi' => ['required', Rule::in(array_keys(Katalog::ALOKASI))],
            'cutoff_pct' => ['required', 'numeric', 'min:0', 'max:10'],
            'periode_mulai' => ['required', 'date'], 'periode_selesai' => ['required', 'date', 'after_or_equal:periode_mulai'],
            'volume_tahunan' => ['required', 'numeric', 'min:0'],
            'baseline_gwp' => ['nullable', 'numeric'], 'target_gwp' => ['nullable', 'numeric'],
            'tujuan' => ['nullable', 'string', 'max:3000'], 'audiens' => ['nullable', 'string', 'max:150'],
        ];
    }

    private function aturanItem(): array
    {
        return [
            'faktor_id' => ['required', 'integer', 'exists:lca_faktor,id'],
            'tahap' => ['required', Rule::in(array_keys(Katalog::TAHAP))],
            'nama' => ['nullable', 'string', 'max:180'],
            'jumlah' => ['required', 'numeric', 'gt:0', 'max:1000000000'],
            'satuan' => ['required', 'string', 'max:20'],
            'jarak_km' => ['nullable', 'numeric', 'min:0', 'max:50000'],
            'daur_ulang_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'alokasi_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'p_reliabilitas' => ['required', 'integer', 'between:1,5'], 'p_kelengkapan' => ['required', 'integer', 'between:1,5'],
            'p_temporal' => ['required', 'integer', 'between:1,5'], 'p_geografis' => ['required', 'integer', 'between:1,5'],
            'p_teknologi' => ['required', 'integer', 'between:1,5'],
            'sumber_data' => ['nullable', Rule::in(['primer', 'sekunder'])],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function aturanTuas(): array
    {
        return ['ebt' => ['nullable', 'numeric', 'min:0', 'max:100'], 'bbm' => ['nullable', 'numeric', 'min:0', 'max:30'],
                'logistik' => ['nullable', 'numeric', 'min:0', 'max:40'], 'daur_ulang' => ['nullable', 'numeric', 'min:0', 'max:60'],
                'limbah' => ['nullable', 'numeric', 'min:0', 'max:90']];
    }

    private function aturanFaktor(): array
    {
        return [
            'company_id' => ['nullable', 'exists:companies,id'],
            'kode' => ['required', 'string', 'max:60', 'regex:/^[A-Z0-9-]+$/'], 'nama' => ['required', 'string', 'max:180'],
            'kategori' => ['required', Rule::in(array_keys(Katalog::KATEGORI_FAKTOR))],
            'tahap_bawaan' => ['required', Rule::in(array_keys(Katalog::TAHAP))], 'scope' => ['required', 'integer', 'between:1,3'],
            'satuan' => ['required', 'string', 'max:20'],
            'gwp' => ['required', 'numeric'], 'ap' => ['nullable', 'numeric'], 'ep' => ['nullable', 'numeric'],
            'wsf' => ['nullable', 'numeric'], 'ced' => ['nullable', 'numeric'], 'odp' => ['nullable', 'numeric'],
            'sumber' => ['required', 'string', 'max:160'], 'tahun' => ['required', 'integer', 'between:1990,2100'],
            'wilayah' => ['required', 'string', 'max:60'], 'catatan' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
