<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Company, HazardReport, Inspection, InspectionInspector,
    InspectionItem, InspectionTemplate, User};
use App\Support\{Db, Hazard, Identitas};
use Illuminate\Http\Request;
use App\Support\Berkas;

class InspectionController extends Controller
{
    public function index(Request $request)
    {
        $status   = $request->get('status');
        $template = $request->get('template');

        $inspections = Inspection::with(['company','template','inspectors'])->withCount('items')
            ->when($status,   fn($b) => $b->where('status', $status))
            ->when($template, fn($b) => $b->where('template_id', $template))
            ->latest('tanggal')->paginate(15)->withQueryString();

        return \Inertia\Inertia::render('Inspeksi/Daftar', [
            'judul'    => 'Daftar Inspeksi',
            'subjudul' => 'Pemeriksaan lapangan dan hasilnya',

            'saring' => ['status' => $status, 'template' => $template],
            'opsi'   => [
                'status'   => ['Draft', 'Selesai'],
                'template' => InspectionTemplate::orderBy('nama')->get(['id', 'nama'])
                    ->map(fn ($t) => ['id' => $t->id, 'nama' => $t->nama])->all(),
            ],

            'inspeksi' => array_map(fn (Inspection $i) => [
                'id'        => $i->id,
                'kode'      => $i->kode,
                'judul'     => $i->judul,
                'status'    => $i->status,
                'tanggal'   => $i->tanggal?->format('d M Y'),
                'lokasi'    => $i->lokasi ?: null,
                'template'  => $i->template?->nama,
                'perusahaan'=> $i->company?->name,
                'jumlahItem'=> $i->items_count,
                'inspektur' => $i->inspectors->pluck('nama')->all(),
                'url'       => route('inspeksi.show', $i),
            ], $inspections->items()),

            'halaman' => [
                'kini'   => $inspections->currentPage(),
                'akhir'  => $inspections->lastPage(),
                'total'  => $inspections->total(),
                'tautan' => array_map(fn ($t) => [
                    'label' => $t['label'], 'url' => $t['url'], 'aktif' => (bool) $t['active'],
                ], $inspections->linkCollection()->all()),
            ],

            'tautan' => ['buat' => route('inspeksi.create')],
        ]);
    }

    public function create(Request $request)
    {
        return \Inertia\Inertia::render('Inspeksi/Form', [
            'judul'    => 'Buat Inspeksi',
            'subjudul' => 'Pilih jenis inspeksi; parameternya disalin otomatis',

            'awal' => [
                'judul' => '', 'template_id' => '', 'company_id' => '',
                'tanggal' => now()->toDateString(), 'lokasi' => '', 'catatan' => '',
            ],
            'sunting' => false,
            'opsi' => [
                'perusahaan' => Company::orderBy('name')->get(['id', 'name'])
                    ->map(fn ($c) => ['id' => $c->id, 'nama' => $c->name])->all(),
                'template' => InspectionTemplate::where('is_active', true)->withCount('items')
                    ->orderBy('nama')->get()
                    ->map(fn ($t) => ['id' => $t->id, 'nama' => $t->nama, 'jumlahItem' => $t->items_count])->all(),
            ],
            'tautan' => ['simpan' => route('inspeksi.store'), 'batal' => route('inspeksi.index')],
        ]);
    }

    public function store(Request $request)
    {
        $d = $this->v($request);
        $d['kode']    = Inspection::kodeBaru();
        $d['user_id'] = auth()->id();

        $i = Inspection::create($d);

        // Salin parameter dari template terpilih
        if ($i->template_id) {
            $tpl = InspectionTemplate::with('items')->find($i->template_id);
            foreach ($tpl?->items ?? [] as $it) {
                $i->items()->create([
                    'template_item_id' => $it->id, 'kelompok' => $it->kelompok,
                    'uraian' => $it->uraian, 'acuan' => $it->acuan,
                    'risiko' => $it->risiko_default, 'order_index' => $it->order_index,
                ]);
            }
        }

        // Pembuat otomatis jadi inspektur ketua
        $me = auth()->user();
        $i->inspectors()->create([
            'user_id' => $me->id, 'nama' => $me->name,
            'jabatan' => $me->position, 'peran' => 'Ketua',
        ]);

        ActivityLog::write('Buat inspeksi', $i->kode.' — '.$i->judul, 'hazrep');
        return redirect()->route('inspeksi.show', $i)->with('ok','Inspeksi '.$i->kode.' dibuat.');
    }

    public function show(Inspection $inspeksi)
    {
        $inspeksi->load(['items.hazardReport', 'company', 'user', 'template', 'inspectors']);

        return \Inertia\Inertia::render('Inspeksi/Detail', [
            'judul'    => 'Inspeksi '.$inspeksi->kode,
            'subjudul' => $inspeksi->judul,

            'i' => [
                'id'      => $inspeksi->id,
                'kode'    => $inspeksi->kode,
                'judul'   => $inspeksi->judul,
                'status'  => $inspeksi->status,
                'tanggal' => $inspeksi->tanggal?->format('d M Y'),
                'lokasi'  => $inspeksi->lokasi ?: null,
                'catatan' => $inspeksi->catatan,
                'template'   => $inspeksi->template?->nama,
                'perusahaan' => $inspeksi->company?->name,
                'pembuat'    => $inspeksi->user?->name,
            ],

            'inspektur' => $inspeksi->inspectors->map(fn ($p) => [
                'id'      => $p->id,
                'nama'    => $p->nama,
                'jabatan' => $p->jabatan,
                'peran'   => $p->peran,
            ])->all(),

            /* Item dikirim beserta id-nya sebagai kunci penyimpanan: satu
               kiriman memperbarui banyak baris sekaligus, dan tanpa id
               tiap baris tidak ada yang tahu baris mana yang diubah. */
            'item' => $inspeksi->items->map(fn ($x) => [
                'id'       => $x->id,
                'kelompok' => $x->kelompok,
                'uraian'   => $x->uraian,
                'acuan'    => $x->acuan,
                'kondisi'  => $x->kondisi,
                'risiko'   => $x->risiko,
                'temuan'   => $x->temuan,
                'tindakan' => $x->tindakan,
                'foto'     => Berkas::daftarUrl($x, 'ins'),
                'hazard'   => $x->hazardReport?->kode,
                'urlHazard'=> $x->hazardReport ? route('hazard.show', $x->hazardReport) : null,
                'urlAngkat'=> route('inspeksi.item.angkat', $x),
                'urlHapus' => route('inspeksi.item.destroy', $x),
            ])->all(),

            'opsi' => [
                'kondisi' => Hazard::KONDISI,
                'risiko'  => Hazard::RISIKO,
                'status'  => ['Draft', 'Selesai'],
                'peran'   => ['Ketua', 'Anggota'],
                'kandidat' => User::orderBy('name')->get(['id', 'name', 'position'])
                    ->map(fn ($u) => ['id' => $u->id, 'nama' => $u->name, 'jabatan' => $u->position])->all(),
            ],

            'tautan' => [
                'simpanItem'    => route('inspeksi.items.save', $inspeksi),
                'tambahItem'    => route('inspeksi.item.store', $inspeksi),
                'tambahPetugas' => route('inspeksi.inspector.store', $inspeksi),
                'ubah'          => route('inspeksi.edit', $inspeksi),
                'cetak'         => route('inspeksi.ekspor.cetak'),
                'kembali'       => route('inspeksi.index'),
            ],
        ]);
    }

    public function edit(Inspection $inspeksi)
    {
        return \Inertia\Inertia::render('Inspeksi/Form', [
            'judul'    => 'Ubah Inspeksi '.$inspeksi->kode,
            'subjudul' => $inspeksi->judul,

            'awal' => [
                'judul'       => (string) $inspeksi->judul,
                'template_id' => (string) $inspeksi->template_id,
                'company_id'  => (string) $inspeksi->company_id,
                'tanggal'     => $inspeksi->tanggal?->toDateString() ?? '',
                'lokasi'      => (string) $inspeksi->lokasi,
                'catatan'     => (string) $inspeksi->catatan,
            ],
            'sunting' => true,
            'opsi' => [
                'perusahaan' => Company::orderBy('name')->get(['id', 'name'])
                    ->map(fn ($c) => ['id' => $c->id, 'nama' => $c->name])->all(),
                'template' => InspectionTemplate::where('is_active', true)->withCount('items')
                    ->orderBy('nama')->get()
                    ->map(fn ($t) => ['id' => $t->id, 'nama' => $t->nama, 'jumlahItem' => $t->items_count])->all(),
            ],
            'tautan' => [
                'simpan' => route('inspeksi.update', $inspeksi),
                'batal'  => route('inspeksi.show', $inspeksi),
            ],
        ]);
    }

    public function update(Request $request, Inspection $inspeksi)
    {
        $inspeksi->update($this->v($request));
        return redirect()->route('inspeksi.show', $inspeksi)->with('ok','Inspeksi diperbarui.');
    }

    public function destroy(Inspection $inspeksi)
    {
        $kode = $inspeksi->kode;
        $inspeksi->delete();
        ActivityLog::write('Hapus inspeksi', $kode, 'hazrep');
        return redirect()->route('inspeksi.index')->with('ok','Inspeksi dihapus.');
    }

    /* ---------- Inspektur ---------- */
    public function addInspector(Request $r, Inspection $inspeksi)
    {
        $d = $r->validate([
            'user_id' => ['nullable','exists:users,id'],
            'nama'    => ['required_without:user_id','nullable','string','max:150'],
            'jabatan' => ['nullable','string','max:100'],
            'peran'   => ['nullable','in:Ketua,Anggota'],
        ]);

        if (!empty($d['user_id'])) {
            $u = User::find($d['user_id']);
            $d['nama']    = $u->name;
            $d['jabatan'] = $d['jabatan'] ?: $u->position;
        }
        $d['peran'] = $d['peran'] ?? 'Anggota';

        $inspeksi->inspectors()->create($d);
        return back()->with('ok','Inspektur ditambahkan.');
    }

    public function removeInspector(InspectionInspector $inspector)
    {
        $inspector->delete();
        return back()->with('ok','Inspektur dihapus.');
    }

    /* ---------- Item pemeriksaan ---------- */
    public function saveItems(Request $request, Inspection $inspeksi)
    {
        foreach ((array) $request->input('item', []) as $id => $row) {
            $item = $inspeksi->items()->find($id);
            if (!$item) continue;
            $item->update([
                'kondisi'  => in_array($row['kondisi'] ?? null, Hazard::KONDISI, true) ? $row['kondisi'] : null,
                'risiko'   => in_array($row['risiko'] ?? null, Hazard::RISIKO, true) ? $row['risiko'] : null,
                'temuan'   => $row['temuan']   ?? null,
                'tindakan' => $row['tindakan'] ?? null,
            ]);
        }
        if ($request->filled('status')) $inspeksi->update(['status' => $request->input('status')]);

        return back()->with('ok','Hasil pemeriksaan tersimpan.');
    }

    public function storeItem(Request $request, Inspection $inspeksi)
    {
        $d = $request->validate([
            'uraian'   => ['required','string','max:300'],
            'kelompok' => ['nullable','string','max:100'],
            'kondisi'  => ['nullable','in:Sesuai,Tidak Sesuai,N/A'],
            'risiko'   => ['nullable','in:Rendah,Sedang,Tinggi'],
            'temuan'   => ['nullable','string','max:2000'],
            'tindakan' => ['nullable','string','max:2000'],
        ]);
        $d['order_index'] = (int) $inspeksi->items()->max('order_index') + 1;

        /* Foto butir pemeriksaan sebelumnya tersimpan tanpa diperiksa
           sama sekali — bukan hanya tanpa batas ukuran, melainkan tanpa
           batas JENIS. */
        if ($request->hasFile('foto')) {
            $request->validate(['foto.*' => Berkas::ATURAN_GAMBAR], [], ['foto.*' => 'foto']);
            if ($foto = Berkas::simpanBanyak($request->file('foto'), 'inspeksi')) $d['foto'] = $foto;
        }

        $inspeksi->items()->create($d);
        return back()->with('ok','Item pemeriksaan ditambahkan.');
    }

    public function destroyItem(InspectionItem $item)
    {
        $item->delete();
        return back()->with('ok','Item dihapus.');
    }

    /** Naikkan temuan menjadi Hazard Report */
    public function angkat(InspectionItem $item)
    {
        abort_if($item->hazard_report_id, 409, 'Temuan ini sudah dinaikkan.');

        $ins = $item->inspection;
        $me  = auth()->user();

        $r = HazardReport::create([
            'kode'               => HazardReport::kodeBaru(),
            'user_id'            => $me->id,
            'pelapor_nama'       => $me->name,
            'pelapor_nrp'        => $me->employee_id,
            'pelapor_perusahaan' => $me->company?->name,
            'pelapor_departemen' => $me->department,
            'pelapor_jabatan'    => $me->position,
            'company_id'         => $ins->company_id,
            'terlapor'           => $ins->company?->name,
            'tanggal'            => $ins->tanggal,
            'lokasi'             => $ins->lokasi,
            'risiko'             => $item->risiko ?: 'Sedang',
            'kategori'           => 'Hasil Inspeksi',
            'deskripsi'          => $item->temuan ?: $item->uraian,
            'rekomendasi'        => $item->tindakan,
            'status'             => 'Open',
            'foto'               => $item->foto,
        ]);

        $item->update(['hazard_report_id' => $r->id]);
        ActivityLog::write('Naikkan temuan inspeksi', $ins->kode.' → '.$r->kode, 'hazrep');

        return redirect()->route('hazard.show', $r)->with('ok','Temuan dinaikkan menjadi '.$r->kode.'.');
    }

    /* ---------- KPI Inspeksi (aturan sama dengan Hazard Report) ---------- */
    public function kpi(Request $request)
    {
        $bulan = $request->get('bulan');

        $inspeksi = Inspection::with('inspectors')
            ->when($bulan, fn($b) => $b->whereRaw(Db::ym('tanggal') . ' = ?', [$bulan]))->get();

        /* Lewat pluck, bukan ->distinct()->count(): count() menimpa SELECT
           dengan count(*) sehingga DISTINCT atas ekspresi bulan hilang dan
           yang terhitung menjadi jumlah BARIS. Target tiap orang lalu ikut
           membesar setiap ada inspeksi baru. */
        $bulanAktif = $bulan ? 1 : max(1, Inspection::selectRaw(Db::ym('tanggal') . ' as b')
                        ->whereNotNull('tanggal')->distinct()->pluck('b')->count());

        /* Dikelompokkan lewat Identitas: nama yang diketik berbeda-beda
           untuk orang yang sama memecahnya menjadi beberapa orang, dan
           karena target dijumlahkan per orang, targetnya ikut berlipat
           sementara inspeksinya tetap — capaiannya turun tanpa sebab.
           mb_strtolower saja tidak menutupnya: spasi ganda dan spasi di
           ujung tetap menghasilkan kunci yang berbeda. */
        $perOrang = [];
        foreach ($inspeksi as $ins) {
            foreach ($ins->inspectors as $p) {
                $key = Identitas::kunci($p->user_id, null, $p->nama);
                $perOrang[$key] ??= [
                    'nama' => $p->nama, 'jabatan' => $p->jabatan,
                    'gol' => Hazard::golongan($p->jabatan),
                    'target' => Hazard::target($p->jabatan) * $bulanAktif,
                    'aktual' => 0,
                ];
                $perOrang[$key]['aktual']++;
            }
        }
        uasort($perOrang, fn($a,$b) => $b['aktual'] <=> $a['aktual']);

        $perGolongan = [];
        foreach ($perOrang as $o) {
            $g = $o['gol'];
            $perGolongan[$g] ??= ['target'=>0,'aktual'=>0,'orang'=>0,'tercapai'=>0];
            $perGolongan[$g]['target'] += $o['target'];
            $perGolongan[$g]['aktual'] += $o['aktual'];
            $perGolongan[$g]['orang']++;
            if ($o['aktual'] >= $o['target']) $perGolongan[$g]['tercapai']++;
        }

        // ringkasan temuan
        $items = InspectionItem::whereIn('inspection_id', $inspeksi->pluck('id'))->get();

        $persen = fn (int $a, int $t) => $t ? (int) round($a / $t * 100) : 0;

        return \Inertia\Inertia::render('Inspeksi/Kpi', [
            'judul'    => 'KPI Inspeksi',
            'subjudul' => 'Capaian pelaksanaan inspeksi terhadap targetnya',

            'bulan'      => $bulan,
            'bulanAktif' => $bulanAktif,
            'total'      => $inspeksi->count(),
            'temuan'     => [
                'total'  => $items->count(),
                'sesuai' => $items->where('kondisi', 'Sesuai')->count(),
                'tidak'  => $items->where('kondisi', 'Tidak Sesuai')->count(),
                'naik'   => $items->whereNotNull('hazard_report_id')->count(),
            ],

            'opsiBulan' => Inspection::selectRaw(Db::ym('tanggal') . ' as b')
                ->whereNotNull('tanggal')->distinct()->orderByDesc('b')->pluck('b')
                ->map(fn ($b) => [
                    'nilai' => $b,
                    'label' => \Carbon\Carbon::parse($b.'-01')->translatedFormat('F Y'),
                ])->all(),

            'golongan' => collect($perGolongan)->map(fn ($g, $nama) => [
                'nama'     => $nama,
                'orang'    => $g['orang'],
                'target'   => $g['target'],
                'aktual'   => $g['aktual'],
                'tercapai' => $g['tercapai'],
                'pct'      => $persen($g['aktual'], $g['target']),
            ])->values()->all(),

            'petugas' => array_values(array_map(fn ($o) => [
                'nama'    => $o['nama'],
                'jabatan' => $o['jabatan'] ?: null,
                'gol'     => $o['gol'],
                'target'  => $o['target'],
                'aktual'  => $o['aktual'],
                'pct'     => $persen($o['aktual'], $o['target']),
            ], $perOrang)),
        ]);
    }

    private function v(Request $r): array
    {
        $d = $this->pemilik($r->validate([
            'template_id' => ['nullable','exists:inspection_templates,id'],
            'judul'       => ['required','string','max:200'],
            'jenis'       => ['nullable','in:Harian,Mingguan,Bulanan,Khusus'],
            'company_id'  => ['nullable','exists:companies,id'],
            'lokasi'      => ['nullable','string','max:200'],
            'tanggal'     => ['required','date'],
            'pelaksana'   => ['nullable','string','max:150'],
            'status'      => ['nullable','in:Berjalan,Selesai'],
            'catatan'     => ['nullable','string','max:2000'],
        ]));
        $d['status'] = $d['status'] ?? 'Berjalan';
        return $d;
    }
}
