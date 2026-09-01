<?php

namespace App\Http\Controllers;

use App\Support\DeretBulan;

use App\Models\{ActivityLog, Company, HazardReport, User};
use App\Support\{Db, Hazard, Identitas};
use Illuminate\Http\Request;
use App\Support\Berkas;

class HazardController extends Controller
{
    /* ---------- Monitor: daftar + filter ---------- */
    public function index(Request $request)
    {
        $f = [
            'q'         => trim((string) $request->get('q')),
            'bulan'     => $request->get('bulan'),
            'kategori'  => $request->get('kategori'),
            'risiko'    => $request->get('risiko'),
            'status'    => $request->get('status'),
            'perusahaan'=> $request->get('perusahaan'),
        ];

        $reports = HazardReport::with('company')
            ->when($f['q'], fn($b) => $b->where(fn($w) => $w
                ->where('kode','like',"%{$f['q']}%")
                ->orWhere('deskripsi','like',"%{$f['q']}%")
                ->orWhere('lokasi','like',"%{$f['q']}%")
                ->orWhere('pelapor_nama','like',"%{$f['q']}%")))
            ->when($f['bulan'],      fn($b) => $b->whereRaw(Db::ym('tanggal') . ' = ?', [$f['bulan']]))
            ->when($f['kategori'],   fn($b) => $b->where('kategori', $f['kategori']))
            ->when($f['risiko'],     fn($b) => $b->where('risiko', $f['risiko']))
            ->when($f['status'],     fn($b) => $b->where('status', $f['status']))
            ->when($f['perusahaan'], fn($b) => $b->where('company_id', $f['perusahaan']))
            ->latest('tanggal')->latest('id')
            ->paginate(15)->withQueryString();

        $semua = HazardReport::query();
        $stat = [
            'total'  => (clone $semua)->count(),
            'open'   => (clone $semua)->where('status','Open')->count(),
            'proses' => (clone $semua)->where('status','In Progress')->count(),
            'closed' => (clone $semua)->where('status','Closed')->count(),
            'tinggi' => (clone $semua)->where('risiko','Tinggi')->where('status','<>','Closed')->count(),
        ];

        $bulanOpsi = HazardReport::selectRaw(Db::ym('tanggal') . ' as b')
                        ->whereNotNull('tanggal')->distinct()->orderByDesc('b')->pluck('b');

        /* Ringkasan WhatsApp disusun di server, bukan di peramban: isinya
           mengikuti hasil saringan yang sama dengan daftar di layar, dan
           menyusunnya ulang di sisi klien berarti dua tempat yang harus
           sepakat tentang apa yang sedang tersaring. */
        $ringkasWa = "*Rekap Hazard Report — EQOHSEE*\n\n"
            ."Total: {$stat['total']} · Open: {$stat['open']} · Proses: {$stat['proses']} · Closed: {$stat['closed']}\n"
            ."Risiko tinggi belum tutup: {$stat['tinggi']}\n\n"
            .$reports->take(10)->map(fn ($x) => "• [{$x->kode}] {$x->risiko} — "
                .\Illuminate\Support\Str::limit($x->deskripsi, 60)
                ." (📍".($x->lokasi ?: '-').", ".($x->company?->name ?: $x->terlapor ?: '-').", {$x->status})")->implode("\n")
            ."\n\nMohon ditindaklanjuti sesuai PIC masing-masing.";

        return \Inertia\Inertia::render('Hazard/Monitor', [
            'judul'    => 'Monitor Hazard Report',
            'subjudul' => 'Laporan bahaya dari seluruh lokasi kerja',

            'stat'   => $stat,
            'saring' => $f,
            'adaSaringan' => collect($f)->filter()->isNotEmpty(),

            'opsi' => [
                'risiko'   => Hazard::RISIKO,
                'status'   => Hazard::STATUS,
                'kategori' => Hazard::KATEGORI,
                'bulan'    => $bulanOpsi->map(fn ($b) => [
                    'nilai' => $b,
                    'label' => \Carbon\Carbon::parse($b.'-01')->translatedFormat('F Y'),
                ])->all(),
                'perusahaan' => Company::orderBy('name')->get(['id', 'name'])
                    ->map(fn ($c) => ['id' => $c->id, 'nama' => $c->name])->all(),
            ],

            'laporan' => array_map(fn (HazardReport $r) => [
                'id'          => $r->id,
                'kode'        => $r->kode,
                'risiko'      => $r->risiko,
                'warnaRisiko' => Hazard::WARNA_RISIKO[$r->risiko] ?? '#a8a29e',
                'status'      => $r->status,
                'warnaStatus' => Hazard::WARNA_STATUS[$r->status] ?? '#a8a29e',
                'kategori'    => $r->kategori,
                'deskripsi'   => $r->deskripsi,
                'lokasi'      => $r->lokasi ?: null,
                'pelapor'     => $r->pelapor_nama,
                'tanggal'     => $r->tanggal?->format('d M Y'),
                'tujuan'      => $r->company?->name ?: ($r->terlapor ?: null),
                'terlapor'    => $r->terlapor && $r->company ? $r->terlapor : null,
                'foto'        => Berkas::url($r, 'hzd', 0),
                'url'         => route('hazard.show', $r),
            ], $reports->items()),

            'halaman' => [
                'kini'   => $reports->currentPage(),
                'akhir'  => $reports->lastPage(),
                'total'  => $reports->total(),
                'tautan' => array_map(fn ($t) => [
                    'label' => $t['label'],
                    'url'   => $t['url'],
                    'aktif' => (bool) $t['active'],
                ], $reports->linkCollection()->all()),
            ],

            'tautan' => [
                'buat'     => route('hazard.create'),
                'csv'      => route('hazard.ekspor.csv', $request->query()),
                'cetak'    => route('hazard.ekspor.cetak', $request->query()),
                'wa'       => \App\Support\Ekspor::waLink($ringkasWa),
                'pengingat'=> route('hazard.pengingat'),
            ],
        ]);
    }

    /* ---------- Form laporan ---------- */
    public function create()
    {
        $me = auth()->user();

        return \Inertia\Inertia::render('Hazard/Buat', [
            'judul'    => 'Buat Laporan Bahaya',
            'subjudul' => 'Laporkan temuan agar dapat ditindaklanjuti',

            /* Isian pelapor sudah terisi dari akun yang sedang masuk.
               Mengetik ulang identitas sendiri pada tiap laporan bukan
               hanya merepotkan — di situlah ejaan mulai bervariasi, dan
               satu orang pecah menjadi beberapa di perhitungan KPI. */
            'awal' => [
                'pelapor_nama'       => $me->name,
                'pelapor_nrp'        => $me->employee_id,
                'pelapor_jabatan'    => $me->position,
                'pelapor_departemen' => $me->department,
                'pelapor_perusahaan' => $me->company?->name,
                'company_id'         => $me->company_id ? (string) $me->company_id : '',
                'tanggal'            => now()->toDateString(),
                'waktu'              => now()->format('H:i'),
                'risiko'             => 'Sedang',
            ],

            'opsi' => [
                'risiko'          => Hazard::RISIKO,
                'kategori'        => Hazard::KATEGORI,
                'lokasi'          => Hazard::LOKASI,
                'hirarki'         => Hazard::HIRARKI,
                'unsafeAction'    => Hazard::UNSAFE_ACTION,
                'unsafeCondition' => Hazard::UNSAFE_CONDITION,
                'perusahaan'      => Company::orderBy('name')->get(['id', 'name'])
                    ->map(fn ($c) => ['id' => $c->id, 'nama' => $c->name])->all(),
            ],

            'manpower' => $this->manpower(),

            'tautan' => ['simpan' => route('hazard.store'), 'batal' => route('hazard.index')],
        ]);
    }

    /** Daftar man power: pengguna terdaftar + pelapor yang pernah mengisi laporan. */
    private function manpower()
    {
        $daftar = User::with('company')->orderBy('name')->get()->map(fn($u) => [
            'id' => 'u'.$u->id, 'nama' => $u->name, 'nrp' => $u->employee_id,
            'dept' => $u->department, 'jabatan' => $u->position,
            'perusahaan' => $u->company?->name,
        ]);

        $lama = HazardReport::select('pelapor_nama','pelapor_nrp','pelapor_departemen','pelapor_jabatan','pelapor_perusahaan')
            ->whereNotNull('pelapor_nama')->get()
            ->unique(fn($r) => mb_strtolower($r->pelapor_nama).'|'.$r->pelapor_nrp)
            ->reject(fn($r) => $daftar->contains(fn($d) =>
                mb_strtolower($d['nama']) === mb_strtolower($r->pelapor_nama)))
            ->values()
            ->map(fn($r, $i) => [
                'id' => 'h'.$i, 'nama' => $r->pelapor_nama, 'nrp' => $r->pelapor_nrp,
                'dept' => $r->pelapor_departemen, 'jabatan' => $r->pelapor_jabatan,
                'perusahaan' => $r->pelapor_perusahaan,
            ]);

        return $daftar->concat($lama)->values();
    }

    public function store(Request $request)
    {
        $d = $this->validasi($request);
        $me = auth()->user();

        // Lokasi "Lainnya" → pakai isian bebas
        if (($d['lokasi'] ?? null) === 'Lainnya' && !empty($d['lokasi_lain'])) {
            $d['lokasi'] = $d['lokasi_lain'];
        }
        unset($d['lokasi_lain']);

        // Daftar bentuk unsafe disimpan sebagai JSON
        $d['unsafe_action']    = !empty($d['unsafe_action'])    ? json_encode(array_values($d['unsafe_action']),    JSON_UNESCAPED_UNICODE) : null;
        $d['unsafe_condition'] = !empty($d['unsafe_condition']) ? json_encode(array_values($d['unsafe_condition']), JSON_UNESCAPED_UNICODE) : null;

        $d['kode']       = HazardReport::kodeBaru();
        $d['user_id']    = $me->id;
        $d['foto']       = $this->simpanFoto($request, 'foto');
        $d['status']     = 'Open';

        $r = HazardReport::create($d);
        ActivityLog::write('Buat hazard report', $r->kode.' — '.$r->lokasi, 'hazrep');

        return redirect()->route('hazard.show', $r)->with('ok', 'Laporan '.$r->kode.' terkirim.');
    }

    public function show(Request $request, HazardReport $hazard)
    {
        $hazard->load(['company', 'user', 'closer']);


        return \Inertia\Inertia::render('Hazard/Detail', [
            'judul'    => 'Laporan '.$hazard->kode,
            'subjudul' => $hazard->lokasi ?: 'Rincian laporan bahaya',

            'r' => [
                'id'        => $hazard->id,
                'kode'      => $hazard->kode,
                'risiko'    => $hazard->risiko,
                'warnaRisiko' => Hazard::WARNA_RISIKO[$hazard->risiko] ?? '#a8a29e',
                'status'    => $hazard->status,
                'warnaStatus' => Hazard::WARNA_STATUS[$hazard->status] ?? '#a8a29e',
                'kategori'  => $hazard->kategori,
                'deskripsi' => $hazard->deskripsi,
                'rekomendasi' => $hazard->rekomendasi,
                'hirarki'   => $hazard->hirarki,
                'lokasi'    => $hazard->lokasi,
                'tanggal'   => $hazard->tanggal?->format('d M Y'),
                'waktu'     => $hazard->waktu,

                'pelapor' => [
                    'nama'       => $hazard->user?->name ?: $hazard->pelapor_nama,
                    'nrp'        => $hazard->pelapor_nrp,
                    'jabatan'    => $hazard->user?->position ?: $hazard->pelapor_jabatan,
                    'departemen' => $hazard->pelapor_departemen,
                    'perusahaan' => $hazard->pelapor_perusahaan,
                ],

                'tujuan'   => $hazard->company?->name ?: ($hazard->terlapor ?: null),
                'terlapor' => $hazard->terlapor,

                // Daftar, bukan teks tunggal — data lama masih berupa teks
                // biasa dan accessor-nya sudah menyeragamkan keduanya.
                'unsafeAction'    => $hazard->unsafe_action_list,
                'unsafeCondition' => $hazard->unsafe_condition_list,

                'foto'            => Berkas::daftarUrl($hazard, 'hzd'),
                'fotoTindakLanjut' => Berkas::daftarUrl($hazard, 'hzt'),

                'catatanPenutupan' => $hazard->catatan_penutupan,
                'penutup'  => $hazard->closer?->name,
                'ditutup'  => $hazard->closed_at?->format('d M Y H:i'),
            ],

            'opsi'  => ['status' => Hazard::STATUS],
            'admin' => (bool) $request?->user()?->isAdmin(),

            'tautan' => [
                'kembali' => route('hazard.index'),
                'tindak'  => route('hazard.follow', $hazard),
                'hapus'   => route('hazard.destroy', $hazard),
            ],
        ]);
    }

    /* ---------- Tindak lanjut ---------- */
    public function follow(Request $request, HazardReport $hazard)
    {
        $d = $request->validate([
            'status'            => ['required','in:Open,In Progress,Closed'],
            'catatan_penutupan' => ['nullable','string','max:2000'],
        ]);

        $baru = $this->simpanFoto($request, 'foto_tindaklanjut');
        if ($baru) $d['foto_tindaklanjut'] = array_merge((array) $hazard->foto_tindaklanjut, $baru);

        if ($d['status'] === 'Closed' && $hazard->status !== 'Closed') {
            $d['closed_by'] = auth()->id();
            $d['closed_at'] = now();
        }

        $hazard->update($d);
        ActivityLog::write('Tindak lanjut hazard', $hazard->kode.' → '.$d['status'], 'hazrep');

        return back()->with('ok', 'Tindak lanjut tersimpan.');
    }

    public function destroy(HazardReport $hazard)
    {
        $kode = $hazard->kode;
        $hazard->delete();
        ActivityLog::write('Hapus hazard report', $kode, 'hazrep');
        return redirect()->route('hazard.index')->with('ok', 'Laporan dihapus.');
    }

    /* ---------- Analitik: KPI golongan + distribusi + tren ---------- */
    public function analytics(Request $request)
    {
        $bulan = $request->get('bulan');
        // user dimuat sekaligus: identitas dan jabatan terkini dibaca dari
        // sana, dan tanpa eager load itu menjadi satu kueri per laporan.
        $data = HazardReport::with('user')
            ->when($bulan, fn($b) => $b->whereRaw(Db::ym('tanggal') . ' = ?', [$bulan]))->get();

        /* Jumlah bulan yang benar-benar berisi laporan — pengali target.
           Dihitung lewat pluck, bukan ->distinct()->count(): count()
           menimpa SELECT dengan count(*) sehingga DISTINCT atas ekspresi
           bulan ikut hilang, dan yang terhitung menjadi jumlah BARIS.
           Akibatnya target tiap orang ikut membesar setiap ada laporan
           baru, dan capaian semua orang merosot tanpa sebab. */
        $bulanAktif = $bulan ? 1 : max(1, HazardReport::selectRaw(Db::ym('tanggal') . ' as b')
                        ->whereNotNull('tanggal')->distinct()->pluck('b')->count());

        /* KPI per orang.
           Dikelompokkan lewat Identitas, bukan langsung dari teksnya: satu
           orang yang mengetik namanya sedikit berbeda pada tiga laporan
           akan terhitung sebagai tiga orang, dan karena target dijumlahkan
           per orang, targetnya ikut tiga kali lipat sementara laporannya
           tetap tiga — capaiannya ambruk jadi sepertiga tanpa satu pun
           galat muncul.

           Nama dan jabatan diambil dari akunnya bila ada. Teks pada
           laporan adalah salinan saat laporan dibuat; orang yang berganti
           jabatan akan menyeret jabatan lamanya — beserta target lama —
           di seluruh laporan terdahulunya. */
        $perOrang = [];
        foreach ($data as $r) {
            $key = Identitas::kunci($r->user_id, $r->pelapor_nrp, $r->pelapor_nama);

            $jabatan = $r->user?->position ?: $r->pelapor_jabatan;

            $perOrang[$key] ??= [
                'nama' => $r->user?->name ?: $r->pelapor_nama, 'jabatan' => $jabatan,
                'gol'  => Hazard::golongan($jabatan),
                'target' => Hazard::target($jabatan) * $bulanAktif,
                'aktual' => 0,
            ];
            $perOrang[$key]['aktual']++;
        }
        uasort($perOrang, fn($a,$b) => $b['aktual'] <=> $a['aktual']);

        // rekap per golongan
        $perGolongan = [];
        foreach ($perOrang as $o) {
            $g = $o['gol'];
            $perGolongan[$g] ??= ['target'=>0,'aktual'=>0,'orang'=>0,'tercapai'=>0];
            $perGolongan[$g]['target'] += $o['target'];
            $perGolongan[$g]['aktual'] += $o['aktual'];
            $perGolongan[$g]['orang']++;
            if ($o['aktual'] >= $o['target']) $perGolongan[$g]['tercapai']++;
        }

        $hitung = fn(string $kolom) => $data->groupBy($kolom)->map->count()->sortDesc();

        /* Tren 12 bulan. Kuncinya dibangun DeretBulan, bukan
           `now()->subMonths($i)`: yang kedua meluap pada tanggal 29–31
           dan membuat dua bulan berbagi satu kunci, sehingga larik
           berkuncinya menyusut dari dua belas menjadi tujuh baris. */
        $tren = [];
        foreach (DeretBulan::kunciMundur(now(), 12) as $k) {
            $tren[$k] = HazardReport::whereRaw(Db::ym('tanggal') . ' = ?', [$k])->count();
        }

        /* Capaian dihitung di sini, bukan di tampilan. Pembagian target yang
           bernilai nol harus dijaga sekali saja — ditulis ulang di tiap tabel
           yang menampilkannya, satu di antaranya cepat atau lambat lupa. */
        $persen = fn (int $aktual, int $target) => $target ? (int) round($aktual / $target * 100) : 0;

        $golongan = [];
        foreach ($perGolongan as $nama => $g) {
            $golongan[] = [
                'nama'     => $nama,
                'orang'    => $g['orang'],
                'target'   => $g['target'],
                'aktual'   => $g['aktual'],
                'tercapai' => $g['tercapai'],
                'pct'      => $persen($g['aktual'], $g['target']),
            ];
        }

        $maksTren = max(array_values($tren) ?: [1]) ?: 1;

        $sebaran = fn (string $judul, $dist) => [
            'judul' => $judul,
            'maks'  => $dist->max() ?: 1,
            'baris' => $dist->map(fn ($v, $k) => ['label' => $k ?: '—', 'nilai' => $v])->values()->all(),
        ];

        return \Inertia\Inertia::render('Hazard/Analitik', [
            'judul'    => 'Analitik & KPI',
            'subjudul' => 'Capaian pelaporan bahaya terhadap targetnya',

            'bulan'      => $bulan,
            'bulanAktif' => $bulanAktif,
            'total'      => $data->count(),

            'opsiBulan' => HazardReport::selectRaw(Db::ym('tanggal') . ' as b')
                ->whereNotNull('tanggal')->distinct()->orderByDesc('b')->pluck('b')
                ->map(fn ($b) => [
                    'nilai' => $b,
                    'label' => \Carbon\Carbon::parse($b.'-01')->translatedFormat('F Y'),
                ])->all(),

            'golongan' => $golongan,

            'tren' => collect($tren)->map(fn ($v, $k) => [
                'label' => \Carbon\Carbon::parse($k.'-01')->translatedFormat('M'),
                'nilai' => $v,
                'maks'  => $maksTren,
            ])->values()->all(),

            'sebaran' => [
                $sebaran('Status',         $hitung('status')),
                $sebaran('Risiko',         $hitung('risiko')),
                $sebaran('Kategori',       $hitung('kategori')),
                $sebaran('Lokasi teratas', $hitung('lokasi')->take(8)),
            ],

            'pelapor' => array_values(array_map(fn ($o) => [
                'nama'    => $o['nama'],
                'jabatan' => $o['jabatan'] ?: null,
                'gol'     => $o['gol'],
                'target'  => $o['target'],
                'aktual'  => $o['aktual'],
                'pct'     => $persen($o['aktual'], $o['target']),
            ], $perOrang)),
        ]);
    }

    /* ---------- bantu ---------- */
    private function validasi(Request $r): array
    {
        return $r->validate([
            'pelapor_nama'       => ['required','string','max:150'],
            'pelapor_nrp'        => ['nullable','string','max:50'],
            'pelapor_perusahaan' => ['nullable','string','max:150'],
            'pelapor_departemen' => ['nullable','string','max:100'],
            'pelapor_jabatan'    => ['required','string','max:100'],
            'company_id'         => ['required','exists:companies,id'],
            'terlapor'           => ['nullable','string','max:150'],
            'tanggal'            => ['required','date'],
            'waktu'              => ['nullable'],
            'lokasi'             => ['nullable','string','max:200'],
            'risiko'             => ['required','in:Rendah,Sedang,Tinggi'],
            'kategori'           => ['required','string','max:100'],
            'deskripsi'          => ['required','string','max:3000'],
            'unsafe_action'      => ['nullable','array'],
            'unsafe_action.*'    => ['string','max:200'],
            'unsafe_condition'   => ['nullable','array'],
            'unsafe_condition.*' => ['string','max:200'],
            'lokasi_lain'        => ['nullable','string','max:200'],
            'hirarki'            => ['nullable','string','max:50'],
            'rekomendasi'        => ['nullable','string','max:2000'],
        ]);
    }

    /**
     * Simpan foto laporan, sesudah memeriksanya.
     *
     * Pemeriksaannya ada di sini, bukan di blok validate tiap pemanggil.
     * Sebelumnya kedua pemanggil tidak memvalidasinya sama sekali —
     * `$request->file('foto')` langsung tersimpan, apa pun isinya — dan
     * pemanggil ketiga akan ditulis dengan menyalin salah satu dari
     * keduanya. Aturan yang diletakkan di jalur yang WAJIB dilewati
     * tidak dapat terlewat dengan cara itu.
     */
    private function simpanFoto(Request $r, string $field): array
    {
        if (!$r->hasFile($field)) return [];

        $r->validate(
            [$field.'.*' => Berkas::ATURAN_GAMBAR],
            [],
            [$field.'.*' => 'foto'],
        );

        return Berkas::simpanBanyak($r->file($field), 'hazard');
    }
}
