<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, TpkkpAssessment};
use App\Support\Tpkkp;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TpkkpController extends Controller
{
    /* ================= dasar ================= */

    private function aktif(Request $request): TpkkpAssessment
    {
        $tahun = (int) ($request->get('tahun') ?? session('tpkkp_tahun') ?? now()->year);
        if ($tahun < 2000 || $tahun > 2100) $tahun = (int) now()->year;
        session(['tpkkp_tahun' => $tahun]);

        return TpkkpAssessment::forYear($tahun);
    }

    private function base(Request $request): array
    {
        $a      = $this->aktif($request);
        $hasil  = Tpkkp::totalCalc($a->scores ?? []);
        $tahunn = TpkkpAssessment::orderByDesc('tahun')->pluck('tahun')->all();

        return [$a, $hasil, $tahunn];
    }

    private function guard(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Hanya admin yang boleh mengubah penilaian.');
    }

    private function log(string $aksi, string $ket = ''): void
    {
        try {
            ActivityLog::create([
                'user_id' => auth()->id(),
                'module'  => 'tpkkp',
                'action'  => $aksi,
                'detail'  => $ket,
            ]);
        } catch (\Throwable $e) {
            // pencatatan gagal tidak boleh menggagalkan aksi
        }
    }

    /* ================= 1. Beranda ================= */

    public function index(Request $request)
    {
        [$a, $hasil, $tahunn] = $this->base($request);

        return view('tpkkp.beranda', [
            'a'       => $a,
            'hasil'   => $hasil,
            'tahunn'  => $tahunn,
            'metode'  => Tpkkp::methodTotals($a->scores ?? []),
            'sebaran' => Tpkkp::distribution($a->scores ?? []),
            'gaps'    => Tpkkp::gaps($a->scores ?? [], 10),
        ]);
    }

    /* ================= 2. Profil ================= */

    public function profile(Request $request)
    {
        [$a, $hasil, $tahunn] = $this->base($request);

        return view('tpkkp.profil', ['a' => $a, 'hasil' => $hasil, 'tahunn' => $tahunn]);
    }

    public function saveProfile(Request $request)
    {
        $this->guard();
        $a = $this->aktif($request);

        $d = $request->validate([
            'judul'     => ['nullable', 'string', 'max:200'],
            'status'    => ['nullable', 'in:draft,aktif,selesai'],
            'organisasi'=> ['nullable', 'string', 'max:200'],
            'site'      => ['nullable', 'string', 'max:200'],
            'komoditas' => ['nullable', 'string', 'max:100'],
            'ktt'       => ['nullable', 'string', 'max:200'],
            'basis'     => ['nullable', 'string', 'max:200'],
        ]);

        $a->judul  = $d['judul']  ?: $a->judul;
        $a->status = $d['status'] ?: $a->status;
        $a->profil = array_merge($a->profil ?? [], [
            'organisasi' => $d['organisasi'] ?? '',
            'site'       => $d['site'] ?? '',
            'komoditas'  => $d['komoditas'] ?? '',
            'ktt'        => $d['ktt'] ?? '',
            'basis'      => $d['basis'] ?? '',
            'periode'    => $a->tahun,
        ]);
        $a->save();

        $this->log('profil.simpan', 'Periode ' . $a->tahun);

        return back()->with('ok', 'Profil penilaian tersimpan.');
    }

    /* ================= 3. Penilaian ================= */

    public function assess(Request $request)
    {
        [$a, $hasil, $tahunn] = $this->base($request);

        $M  = Tpkkp::methods();
        $m  = $request->get('m');
        if (!isset($M[$m])) $m = array_key_first($M);

        $daftarParam = [];
        foreach (Tpkkp::indicators() as $ind) {
            foreach ($ind['params'] as $par) {
                $n = 0;
                foreach ($par['items'] as $it) if (in_array($m, $it['methods'], true)) $n++;
                if ($n) $daftarParam[] = ['code' => $par['code'], 'name' => $par['name'], 'n' => $n];
            }
        }

        $p = $request->get('p');
        if (!$p || !collect($daftarParam)->firstWhere('code', $p)) {
            $p = $daftarParam[0]['code'] ?? null;
        }

        $items = [];
        if ($p) {
            $par = Tpkkp::paramByCode($p);
            foreach ($par['items'] ?? [] as $it) {
                if (in_array($m, $it['methods'], true)) $items[] = $it;
            }
        }

        /* Halaman ini dirender Vue lewat Inertia — halaman pertama yang
           dipindah. Alasannya bukan Blade tidak sanggup, melainkan
           bentuk datanya: 194 item dengan nilai per entitas lebih wajar
           hidup sebagai state di peramban daripada dirender ulang dari
           server setiap kali satu angka berubah. Di sini capaian tiap
           item ikut terhitung ulang seketika, yang pada versi Blade baru
           terlihat setelah disimpan dan halaman dimuat ulang. */
        $entitas = $a->entitiesOf($m);
        $target  = Tpkkp::target();

        $daftarItem = [];
        foreach ($items as $it) {
            $rub = Tpkkp::rubrikFor($m, $it['code']);

            $nilai = [];
            if ($entitas) {
                foreach ($entitas as $ent) $nilai[$ent] = $a->cell($m, $it['code'], $ent);
            } else {
                $nilai['_'] = $a->cell($m, $it['code']);
            }

            $daftarItem[] = [
                'kode'   => $it['code'],
                'nama'   => $it['name'],
                'metode' => $it['methods'],
                'maks'   => $it['max'],
                'nilai'  => $nilai,
                'ket'    => $a->ket($m, $it['code']),
                'target' => isset($target[$it['code']][$m]) ? trim($target[$it['code']][$m]) : null,
                'rubrik' => collect($rub['l'] ?? [])->map(fn ($teks, $i) => [
                    'tingkat' => $i + 1,
                    'teks'    => $teks,
                    'warna'   => Tpkkp::levelHex($i + 1),
                ])->values()->all(),
            ];
        }

        $par = $p ? Tpkkp::paramByCode($p) : null;

        return Inertia::render('Tpkkp/Penilaian', [
            /* Judul bilah atas ditentukan di sini, sejajar dengan
               @yield('subjudul') pada halaman Blade. */
            'judul'       => 'PTPKKP — Penilaian',
            'subjudul'    => "Tingkat kematangan keselamatan, periode {$a->tahun}",

            'tahun'       => $a->tahun,
            'metode'      => collect($M)->map(fn ($x, $k) => [
                'kode'         => $k,
                'nama'         => $x['name'],
                'labelEntitas' => $x['entityLabel'] ?? '',
            ])->values()->all(),
            'metodeAktif' => $m,
            'parameter'   => collect($daftarParam)->map(fn ($x) => [
                'kode' => $x['code'], 'nama' => $x['name'], 'jumlah' => $x['n'],
            ])->all(),
            'paramAktif'  => $p,
            'entitas'     => array_values($entitas),
            'items'       => $daftarItem,
            'bisaSunting' => $request->user()->isAdmin(),
            'paramBobot'  => (float) ($par['weight'] ?? 0),
            'paramTarget' => (float) (Tpkkp::paramTargets()[$p] ?? 0),

            /* Ambang kategori dikirim dari sini, tidak ditulis ulang di
               sisi Vue. Menyalinnya ke peramban berarti dua daftar ambang
               yang harus diubah bersama — dan yang tertinggal tidak
               menimbulkan galat, hanya lencana yang menyebut tingkat
               kematangan yang salah. Sudah pernah terjadi: salinan yang
               ditulis dengan tangan memakai batas dan nama tingkat yang
               tidak ada di acuan sama sekali. */
            'ambang'      => collect(Tpkkp::ref()['thresholds'])->map(fn ($t, $i) => [
                'batas' => (float) $t['lt'],
                'label' => $t['label'],
                'warna' => Tpkkp::levelHex($i + 1),
            ])->values()->all(),
        ]);
    }

    public function saveAssess(Request $request)
    {
        $this->guard();
        $a = $this->aktif($request);

        $m = (string) $request->input('metode');
        abort_unless(isset(Tpkkp::methods()[$m]), 422, 'Metode tidak dikenal.');

        $scores = $a->scores ?? [];
        $scores[$m] ??= [];

        $nilai = $request->input('n', []);   // n[kode][entitas] atau n[kode][_]
        $ket   = $request->input('ket', []); // ket[kode]
        $ubah  = 0;

        foreach ($nilai as $code => $cells) {
            if (!Tpkkp::itemByCode((string) $code)) continue;

            $rec = $scores[$m][$code] ?? ['v' => null, 'e' => [], 'ket' => ''];
            $rec['e'] ??= [];

            foreach ((array) $cells as $ent => $v) {
                $v = ($v === '' || $v === null) ? null : (int) $v;
                if ($v !== null && ($v < 1 || $v > 5)) $v = null;

                if ($ent === '_') {
                    $rec['v'] = $v;
                } elseif ($v === null) {
                    unset($rec['e'][$ent]);
                } else {
                    $rec['e'][$ent] = $v;
                }
                $ubah++;
            }

            $rec['ket'] = trim((string) ($ket[$code] ?? ''));

            $kosong = ($rec['v'] ?? null) === null && empty($rec['e']) && $rec['ket'] === '';
            if ($kosong) unset($scores[$m][$code]);
            else         $scores[$m][$code] = $rec;
        }

        $a->scores = $scores;
        $a->save();

        $this->log('penilaian.simpan', "Metode $m · $ubah sel");

        return back()->with('ok', "Nilai metode {$m} tersimpan.");
    }

    /* ================= 4. Rekapitulasi ================= */

    public function rekap(Request $request)
    {
        /* Halaman kedua yang dipindah ke Vue — sengaja dipilih karena
           terhubung langsung dengan Formulir Nilai: alur wajarnya isi
           nilai lalu cek rekapnya, dan baru dengan dua halaman Inertia
           yang saling terkait perpindahan ANTARA keduanya bisa instan.
           Satu halaman saja tidak cukup untuk itu — jalan masuknya tetap
           lewat bilah samping Blade, yang selalu memuat ulang penuh. */
        $a = $this->aktif($request);

        $mCo    = Tpkkp::perCompanyMethods();
        $daftar = $a->entitiesOf('TD');
        $co     = $request->get('entitas');
        if ($co && !in_array($co, $daftar, true)) $co = null;

        return Inertia::render('Tpkkp/Rekap', [
            'judul'    => 'PTPKKP — Rekapitulasi',
            'subjudul' => "Nilai per parameter dan per perusahaan, periode {$a->tahun}",
            'tahun'    => $a->tahun,

            /* Ditutup dalam closure DAN totalCalc() dipanggil DI DALAM
               closure-nya, bukan sebelum render() dipanggil. Kalau
               totalCalc() dijalankan lebih dulu lalu hasilnya dibungkus
               closure, closure-nya hanya menunda pemetaan larik —
               penghitungan yang sesungguhnya sudah kadung terjadi. Saat
               orang cuma mengganti perusahaan, Inertia meminta reload
               sebagian lewat `only`, dan prop ini tidak bergantung pada
               perusahaan yang dipilih — tidak ada alasan menghitungnya
               ulang setiap kali orang sekadar berpindah perusahaan. */
            'hasil' => fn () => (function () use ($a) {
                $hasil = Tpkkp::totalCalc($a->scores ?? []);

                return [
                    'skor'      => $hasil['score'],
                    'target'    => $hasil['target'],
                    'indikator' => collect($hasil['indicators'])->map(fn ($ind) => [
                        'kode' => $ind['code'], 'nama' => $ind['name'], 'bobot' => $ind['weight'],
                        'nilai' => $ind['score'], 'target' => $ind['target'], 'kategori' => $ind['category'],
                        'parameter' => collect($ind['params'])->map(fn ($p) => [
                            'kode' => $p['code'], 'nama' => $p['name'],
                            'nilai' => $p['nilai'], 'maks' => $p['max'], 'rasio' => $p['ratio'],
                            'bobot' => $p['weight'], 'skor' => $p['score'], 'target' => $p['target'],
                            'kategori' => $p['category'],
                        ])->values(),
                    ])->values(),
                ];
            })(),

            'perusahaan'   => fn () => array_values($daftar),
            'entitasAktif' => $co,
            'metodePerusahaan' => $mCo,

            'rincian' => fn () => $co
                ? collect(Tpkkp::companyBreakdown($a->scores ?? [], $co, $mCo))->map(fn ($ind) => [
                    'kode' => $ind['code'], 'nama' => $ind['name'], 'rerata' => $ind['avg'],
                    'parameter' => collect($ind['params'])->map(fn ($p) => [
                        'kode' => $p['code'], 'nama' => $p['name'],
                        'rerata' => $p['avg'], 'jumlah' => $p['count'],
                    ])->values(),
                ])->values()
                : null,

            'lemah' => fn () => $co
                ? collect(Tpkkp::companyGaps($a->scores ?? [], $co, $mCo, 10))->map(fn ($g) => [
                    'kode' => $g['code'], 'nama' => $g['name'], 'rerata' => $g['avg'],
                ])->values()
                : null,

            /* Warna lencana kategori dicari lewat label, bukan ditulis
               ulang sebagai peta warna baru di sisi Vue — .l() label yang
               sama dipakai App\Support\Tpkkp untuk seluruh aplikasi. */
            'ambang' => collect(Tpkkp::LV)->map(fn ($label, $i) => [
                'label' => $label, 'warna' => Tpkkp::levelHex($i + 1),
            ])->values()->all(),
        ]);
    }

    /* ================= 5. Visualisasi ================= */

    public function visual(Request $request)
    {
        [$a, $hasil, $tahunn] = $this->base($request);

        return view('tpkkp.visual', [
            'a'       => $a,
            'hasil'   => $hasil,
            'tahunn'  => $tahunn,
            'metode'  => Tpkkp::methodTotals($a->scores ?? []),
            'sebaran' => Tpkkp::distribution($a->scores ?? []),
        ]);
    }

    /* ================= 6. Program improvement ================= */

    public function program(Request $request)
    {
        [$a, $hasil, $tahunn] = $this->base($request);

        // saran dari parameter dengan selisih target terbesar
        $saran = [];
        foreach ($hasil['indicators'] as $ind) {
            foreach ($ind['params'] as $p) {
                if ($p['score'] === null || $p['target'] === null) continue;
                $saran[] = ['code' => $p['code'], 'name' => $p['name'], 'gap' => $p['score'] - $p['target']];
            }
        }
        usort($saran, fn ($x, $y) => $x['gap'] <=> $y['gap']);

        return view('tpkkp.program', [
            'a'      => $a,
            'hasil'  => $hasil,
            'tahunn' => $tahunn,
            'saran'  => array_slice($saran, 0, 8),
        ]);
    }

    public function storeProgram(Request $request)
    {
        $this->guard();
        $a = $this->aktif($request);

        $d = $request->validate([
            'param'   => ['required', 'string', 'max:10'],
            'opsi'    => ['required', 'string', 'max:2000'],
            'durasi'  => ['nullable', 'string', 'max:100'],
            'sasaran' => ['nullable', 'string', 'max:500'],
            'target'  => ['nullable', 'string', 'max:100'],
        ]);

        $rows   = $a->programs ?? [];
        $rows[] = $d + [
            'id'       => 'p' . now()->timestamp . rand(10, 99),
            'remarks'  => '',
            'status'   => 'Rencana',
            'progress' => 0,
        ];
        $a->programs = $rows;
        $a->save();

        $this->log('program.tambah', $d['param']);

        return back()->with('ok', 'Program ditambahkan.');
    }

    public function updateProgramStatus(Request $request, string $id)
    {
        $this->guard();
        $a = $this->aktif($request);

        $d = $request->validate([
            'status'   => ['required', 'in:Rencana,Berjalan,Selesai,Ditunda'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $rows = $a->programs ?? [];
        foreach ($rows as &$r) {
            if (($r['id'] ?? null) === $id) {
                $r['status']   = $d['status'];
                $r['progress'] = (int) ($d['progress'] ?? $r['progress'] ?? 0);
            }
        }
        unset($r);

        $a->programs = $rows;
        $a->save();

        return back()->with('ok', 'Status program diperbarui.');
    }

    public function destroyProgram(Request $request, string $id)
    {
        $this->guard();
        $a = $this->aktif($request);

        $a->programs = array_values(array_filter(
            $a->programs ?? [],
            fn ($r) => ($r['id'] ?? null) !== $id
        ));
        $a->save();

        return back()->with('ok', 'Program dihapus.');
    }

    /* ================= 7. Sampling ================= */

    public function sampling(Request $request)
    {
        [$a, $hasil, $tahunn] = $this->base($request);

        $s   = $a->sampling ?? TpkkpAssessment::samplingSeed();
        $pop = $s['populasi'] ?? [];
        $e   = (float) ($s['e'] ?? 0.05);

        $strata = [];
        foreach ($pop as $k => $v) {
            if ($k === 'Total') continue;
            $strata[] = ['j' => $k, 'N' => (int) $v];
        }

        return view('tpkkp.sampling', [
            'a'       => $a,
            'hasil'   => $hasil,
            'tahunn'  => $tahunn,
            'e'       => $e,
            'strata'  => $strata,
            'alokasi' => Tpkkp::strataAlloc($strata, $e),
            'rencana' => Tpkkp::samplingRef(),
        ]);
    }

    public function saveSampling(Request $request)
    {
        $this->guard();
        $a = $this->aktif($request);

        $d = $request->validate([
            'e'   => ['required', 'numeric', 'min:0.01', 'max:0.2'],
            'N'   => ['array'],
            'N.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $s = $a->sampling ?? TpkkpAssessment::samplingSeed();
        $s['e'] = (float) $d['e'];
        foreach ((array) ($d['N'] ?? []) as $k => $v) {
            $s['populasi'][$k] = (int) $v;
        }
        $a->sampling = $s;
        $a->save();

        $this->log('sampling.simpan');

        return back()->with('ok', 'Perhitungan sampel tersimpan.');
    }

    /* ================= 8. Referensi ================= */

    public function metode(Request $request)
    {
        [$a, $hasil, $tahunn] = $this->base($request);

        return view('tpkkp.metode', [
            'a'       => $a,
            'hasil'   => $hasil,
            'tahunn'  => $tahunn,
            'metode'  => Tpkkp::methodTotals($a->scores ?? []),
            'info'    => Tpkkp::methods(),
        ]);
    }

    public function tentang(Request $request)
    {
        [$a, $hasil, $tahunn] = $this->base($request);

        return view('tpkkp.tentang', [
            'a'      => $a,
            'hasil'  => $hasil,
            'tahunn' => $tahunn,
            'meta'   => Tpkkp::meta(),
        ]);
    }
}
