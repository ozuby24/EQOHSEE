<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, TpkkpAssessment};
use App\Support\Tpkkp;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TpkkpController extends Controller
{
    /**
     * Status program improvement.
     *
     * Satu daftar dipakai aturan validasi sekaligus pilihan yang dikirim
     * ke halaman. Sempat ditulis dua kali — sekali di `in:` dan sekali di
     * pilihan formulir — dan daftar semacam itu diam saja ketika salah
     * satunya bertambah: pilihannya muncul, dipilih orang, lalu ditolak
     * validasi tanpa alasan yang tampak.
     */
    public const STATUS_PROGRAM = ['Rencana', 'Berjalan', 'Selesai', 'Ditunda'];

    /** Batas margin galat Slovin; dipakai validasi sekaligus atribut input. */
    public const E_MIN  = 0.01;
    public const E_MAKS = 0.2;

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
        /* Halaman ketiga yang dipindah ke Vue. Dipilih karena ia pintu
           masuk modul: dari sini chip ke Penilaian dan Rekapitulasi —
           dua halaman Inertia lain — berpindah tanpa memuat ulang, dan
           itulah yang membuat modulnya terasa satu kesatuan alih-alih
           kumpulan halaman yang saling memuat ulang. */
        [$a, $hasil, $tahunn] = $this->base($request);

        $sebaran = Tpkkp::distribution($a->scores ?? []);

        /* Data grafik dibentuk di server, bukan di komponen. Rumusnya —
           capaian dibagi bobot lalu dipersenkan — sama dengan yang dipakai
           versi Blade; menaruhnya di sisi Vue berarti satu lagi rumus yang
           hidup di dua tempat, dan yang seperti itu sudah sekali terbukti
           melenceng tanpa menimbulkan galat. */
        $radar = ['label' => [], 'capaian' => [], 'target' => []];
        foreach ($hasil['indicators'] as $I) {
            $w = $I['weight'] ?: 1;
            $radar['label'][]   = 'Indikator '.$I['code'];
            $radar['capaian'][] = round((($I['score'] ?? 0) / $w) * 100, 1);
            $radar['target'][]  = round((($I['target'] ?? 0) / $w) * 100, 1);
        }

        $tingkat = [];
        foreach (Tpkkp::LV as $i => $nama) {
            $tingkat[] = [
                'nama'   => $nama,
                'warna'  => Tpkkp::levelHex($i + 1),
                'jumlah' => $sebaran[$i + 1] ?? 0,
            ];
        }

        return Inertia::render('Tpkkp/Beranda', [
            'judul'    => 'PTPKKP — Beranda',
            'subjudul' => "Ringkasan capaian, periode {$a->tahun}",
            'picker'   => \App\Support\TpkkpNav::untukInertia($a->tahun, $tahunn),

            'identitas' => [
                'organisasi' => $a->profil['organisasi'] ?? $a->judul,
                'site'       => $a->profil['site'] ?? null,
                'komoditas'  => $a->profil['komoditas'] ?? null,
                'tahun'      => $a->tahun,
            ],

            'hasil' => [
                'skor'        => $hasil['score'],
                'tingkat'     => $hasil['level'],
                'kategori'    => $hasil['category'],
                'target'      => $hasil['target'],
                'selTerisi'   => $hasil['filledCells'],
                'selTotal'    => $hasil['totalCells'],
                'kelengkapan' => $hasil['completeness'],
                'indikator'   => collect($hasil['indicators'])->map(fn ($ind) => [
                    'kode' => $ind['code'], 'nama' => $ind['name'],
                    'skor' => $ind['score'], 'rasio' => $ind['ratio'],
                    'bobot' => $ind['weight'], 'target' => $ind['target'],
                    'kategori' => $ind['category'],
                    'warna' => Tpkkp::levelHex(Tpkkp::level($ind['category'])),
                    'selTerisi' => $ind['filledCells'], 'selTotal' => $ind['totalCells'],
                ])->values()->all(),
            ],

            'metode' => collect(Tpkkp::methodTotals($a->scores ?? []))->map(fn ($m) => [
                'kode' => $m['key'], 'nama' => $m['name'],
                'terisi' => $m['filled'], 'jumlah' => $m['items'], 'rasio' => $m['ratio'],
            ])->values()->all(),

            'tingkat'      => $tingkat,
            'belumLengkap' => $sebaran['none'] ?? 0,
            'totalItem'    => Tpkkp::totalItems(),

            'gaps' => collect(Tpkkp::gaps($a->scores ?? [], 10))->map(fn ($g) => [
                'kode' => $g['code'], 'nama' => $g['name'] ?? '',
                'nilai' => $g['nilai'] ?? 0, 'maks' => $g['max'],
                'kategori' => $g['category'],
                'warna' => Tpkkp::levelHex(Tpkkp::level($g['category'])),
            ])->values()->all(),

            'radar' => $radar,
        ]);
    }

    /* ================= 2. Profil ================= */

    public function profile(Request $request)
    {
        [$a, $hasil, $tahunn] = $this->base($request);

        $p = $a->profil ?? [];

        /* Roster entitas hanya menampilkan metode yang benar-benar punya
           entitas. Metode tanpa entitas dinilai satu angka untuk seluruh
           organisasi, dan judul kosong tanpa isi di bawahnya membuat
           halaman tampak rusak. */
        $roster = [];
        foreach (Tpkkp::methods() as $k => $m) {
            $ents = $a->entitiesOf($k);
            if (!count($ents)) continue;

            $roster[] = [
                'kode'    => $k,
                'label'   => $m['entityLabel'] ?? '',
                'entitas' => array_values($ents),
            ];
        }

        return Inertia::render('Tpkkp/Profil', [
            'judul'    => 'PTPKKP — Profil',
            'subjudul' => "Identitas penilaian dan roster entitas, periode {$a->tahun}",
            'picker'   => \App\Support\TpkkpNav::untukInertia($a->tahun, $tahunn),
            'tahun'    => $a->tahun,

            'isian' => [
                'judul'      => $a->judul ?? '',
                'organisasi' => $p['organisasi'] ?? '',
                'site'       => $p['site'] ?? '',
                'komoditas'  => $p['komoditas'] ?? '',
                'ktt'        => $p['ktt'] ?? '',
                'basis'      => $p['basis'] ?? '',
                'status'     => $a->status ?? 'draft',
            ],

            'roster'      => $roster,
            'bisaSunting' => $request->user()->isAdmin(),
        ]);
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

        // Medannya nullable, jadi yang tidak dikirim sama sekali tidak
        // muncul di hasil validasi — bukan muncul bernilai null. Membacanya
        // langsung membuat kiriman sebagian menjadi galat 500.
        $a->judul  = ($d['judul']  ?? null) ?: $a->judul;
        $a->status = ($d['status'] ?? null) ?: $a->status;
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

            /* Navigasi dalam-halaman PTPKKP. Tanpa ini halaman Vue
               terkirim tanpa jalan keluar selain tombol back peramban —
               persis yang sempat terjadi. */
            'picker'      => \App\Support\TpkkpNav::untukInertia($a->tahun, $tahunn),
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
            'picker'   => \App\Support\TpkkpNav::untukInertia(
                $a->tahun, \App\Support\TpkkpNav::daftarTahun()
            ),
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

        $sebaran = Tpkkp::distribution($a->scores ?? []);

        /* Seluruh data grafik dibentuk di sini, termasuk warnanya. Versi
           Blade mengambil warna donat dari window.eqWarnaLevel — larik
           global yang isinya menyalin Tpkkp::LVHEX. Salinan seperti itu
           diam saja ketika paletnya berubah, dan yang terlihat hanyalah
           satu grafik berwarna beda dari grafik di sebelahnya. */
        $indikator = ['label' => [], 'capaian' => [], 'target' => []];
        $parameter = ['label' => [], 'capaian' => [], 'target' => [], 'warna' => []];

        foreach ($hasil['indicators'] as $I) {
            $indikator['label'][]   = 'Ind. '.$I['code'];
            $indikator['capaian'][] = round($I['score'] ?? 0, 4);
            $indikator['target'][]  = round($I['target'] ?? 0, 4);

            foreach ($I['params'] as $P) {
                $parameter['label'][]   = $P['code'];
                $parameter['capaian'][] = round($P['score'] ?? 0, 4);
                $parameter['target'][]  = round($P['target'] ?? 0, 4);
                $parameter['warna'][]   = Tpkkp::levelHex(Tpkkp::level($P['category']));
            }
        }

        $metode = ['label' => [], 'nilai' => [], 'warna' => []];
        foreach (Tpkkp::methodTotals($a->scores ?? []) as $m) {
            $metode['label'][] = $m['key'];
            $metode['nilai'][] = $m['ratio'] === null ? 0 : round($m['ratio'] * 100, 1);
            $metode['warna'][] = Tpkkp::levelHex(Tpkkp::level($m['category']));
        }

        $donat = ['label' => [], 'nilai' => [], 'warna' => []];
        foreach (Tpkkp::LV as $i => $nama) {
            $donat['label'][] = $nama;
            $donat['nilai'][] = $sebaran[$i + 1] ?? 0;
            $donat['warna'][] = Tpkkp::levelHex($i + 1);
        }
        $donat['label'][] = 'Belum lengkap';
        $donat['nilai'][] = $sebaran['none'] ?? 0;
        $donat['warna'][] = '#e7e5e4';

        return Inertia::render('Tpkkp/Visual', [
            'judul'    => 'PTPKKP — Visualisasi',
            'subjudul' => "Capaian dalam bentuk grafik, periode {$a->tahun}",
            'picker'   => \App\Support\TpkkpNav::untukInertia($a->tahun, $tahunn),
            'indikator'=> $indikator,
            'parameter'=> $parameter,
            'metode'   => $metode,
            'donat'    => $donat,
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

        $program = [];
        foreach ($a->programs ?? [] as $r) {
            // Baris tanpa id tidak bisa diperbarui maupun dihapus — id-nya
            // yang dipakai rute. Barisnya tetap ditampilkan supaya isinya
            // tidak hilang diam-diam, tapi tombolnya disembunyikan.
            $program[] = [
                'id'       => $r['id'] ?? null,
                'param'    => $r['param'] ?? '',
                'opsi'     => $r['opsi'] ?? '',
                'durasi'   => $r['durasi'] ?? '',
                'sasaran'  => $r['sasaran'] ?? '',
                'target'   => $r['target'] ?? '',
                'status'   => $r['status'] ?? self::STATUS_PROGRAM[0],
                'progress' => (int) ($r['progress'] ?? 0),
            ];
        }

        return Inertia::render('Tpkkp/Program', [
            'judul'    => 'PTPKKP — Program Improvement',
            'subjudul' => "Rencana perbaikan atas selisih terhadap target, periode {$a->tahun}",
            'picker'   => \App\Support\TpkkpNav::untukInertia($a->tahun, $tahunn),
            'tahun'    => $a->tahun,
            'saran'    => array_map(fn ($s) => [
                'kode' => $s['code'],
                'nama' => $s['name'],
                'gap'  => round($s['gap'], 4),
            ], array_slice($saran, 0, 8)),
            'program'       => $program,
            'statusPilihan' => self::STATUS_PROGRAM,
            'bisaSunting'   => $request->user()->isAdmin(),
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
            /* Id acak yang sungguh-sungguh acak.

               Sebelumnya: 'p' . timestamp . rand(10, 99). Dua program yang
               ditambahkan dalam DETIK YANG SAMA karena itu bertabrakan
               satu kali dari sembilan puluh — dan menambahkan dua program
               berturut-turut adalah cara normal mengisi rencana perbaikan,
               bukan keadaan langka.

               Akibat tabrakannya tidak berhenti pada id kembar.
               destroyProgram menyaring dengan `!== $id`, sehingga menghapus
               satu program menghapus KEDUANYA; updateProgramStatus
               memperbarui keduanya pula. Kehilangannya diam: tidak ada
               galat, hanya satu baris yang ikut lenyap dari rencana. */
            'id'       => 'p' . bin2hex(random_bytes(8)),
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
            'status'   => ['required', \Illuminate\Validation\Rule::in(self::STATUS_PROGRAM)],
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

        /*
         * Pratinjau: angka dari kueri menimpa yang tersimpan, tanpa
         * menyimpan apa pun. Halaman memanggil ulang dirinya sendiri
         * (partial reload 'alokasi') tiap kali isian berubah, sehingga
         * rumus Slovin tetap hidup di satu tempat saja. Menghitungnya
         * ulang di peramban akan cepat, tapi pembulatannya harus persis
         * sama — dan selisih satu orang antara angka yang tampak saat
         * mengetik dan angka yang tersimpan tidak akan menimbulkan galat
         * apa pun, hanya laporan yang salah.
         */
        foreach ((array) $request->query('N') as $k => $v) {
            if (array_key_exists($k, $pop)) $pop[$k] = max(0, (int) $v);
        }

        if ($request->filled('e')) {
            $e = min(self::E_MAKS, max(self::E_MIN, (float) $request->query('e')));
        }

        $strata = [];
        foreach ($pop as $k => $v) {
            if ($k === 'Total') continue;
            $strata[] = ['j' => $k, 'N' => (int) $v];
        }

        $alokasi = Tpkkp::strataAlloc($strata, $e);

        return Inertia::render('Tpkkp/Sampling', [
            'judul'    => 'PTPKKP — Kalkulator Slovin',
            'subjudul' => "Jumlah sampel dan alokasinya per strata, periode {$a->tahun}",
            'picker'   => \App\Support\TpkkpNav::untukInertia($a->tahun, $tahunn),
            'tahun'    => $a->tahun,

            'strata' => array_map(fn ($s) => ['nama' => $s['j'], 'N' => $s['N']], $strata),
            'e'      => $e,
            'eMin'   => self::E_MIN,
            'eMaks'  => self::E_MAKS,

            'alokasi' => [
                'N'     => $alokasi['N'],
                'n'     => $alokasi['n'],
                'total' => $alokasi['total'],
                'baris' => array_map(fn ($r) => [
                    'nama' => $r['j'], 'N' => $r['N'], 'nh' => $r['nh'],
                ], $alokasi['rows']),
            ],

            'bisaSunting' => $request->user()->isAdmin(),
        ]);
    }

    public function saveSampling(Request $request)
    {
        $this->guard();
        $a = $this->aktif($request);

        $d = $request->validate([
            'e'   => ['required', 'numeric', 'min:' . self::E_MIN, 'max:' . self::E_MAKS],
            'N'   => ['array'],
            'N.*' => ['nullable', 'integer', 'min:0'],
        ]);

        $s = $a->sampling ?? TpkkpAssessment::samplingSeed();
        $s['e'] = (float) $d['e'];

        // Hanya strata yang memang ada. Kunci sembarang dari kiriman akan
        // menjadi baris strata permanen yang tidak pernah diminta siapa
        // pun, dan tidak ada tempat di antarmuka untuk menghapusnya lagi.
        foreach ((array) ($d['N'] ?? []) as $k => $v) {
            if (array_key_exists($k, $s['populasi'] ?? [])) $s['populasi'][$k] = max(0, (int) $v);
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

        $info = Tpkkp::methods();

        return Inertia::render('Tpkkp/Metode', [
            'judul'    => 'PTPKKP — Metode',
            'subjudul' => "Tujuh metode pengukuran dan keterisiannya, periode {$a->tahun}",
            'picker'   => \App\Support\TpkkpNav::untukInertia($a->tahun, $tahunn),

            'metode' => collect(Tpkkp::methodTotals($a->scores ?? []))->map(fn ($m) => [
                'kode'        => $m['key'],
                'nama'        => $m['name'],
                'labelEntitas'=> $info[$m['key']]['entityLabel'] ?? '',
                'entitas'     => array_values($info[$m['key']]['entities'] ?? []),
                'items'       => $m['items'],
                'terisi'      => $m['filled'],
                'kategori'    => $m['category'],
                'warna'       => Tpkkp::levelHex(Tpkkp::level($m['category'])),
                'url'         => route('tpkkp.assess', ['m' => $m['key']]),
            ])->values()->all(),
        ]);
    }

    public function tentang(Request $request)
    {
        [$a, $hasil, $tahunn] = $this->base($request);

        /* Seluruh angka ringkasan dan ambang diturunkan dari acuan, tidak
           satu pun diketik ulang di tampilan. Halaman ini justru yang
           menjelaskan cara nilai dihitung, jadi angka yang menyimpang di
           sini lebih menyesatkan daripada di halaman mana pun. */
        $parameter = 0;
        $daftarIndikator = [];

        foreach (Tpkkp::indicators() as $ind) {
            $parameter += count($ind['params']);

            $daftarIndikator[] = [
                'kode'  => $ind['code'],
                'nama'  => $ind['name'],
                'bobot' => collect($ind['params'])->sum('weight'),
                'parameter' => collect($ind['params'])->map(fn ($p) => [
                    'kode'   => $p['code'],
                    'nama'   => $p['name'],
                    'bobot'  => $p['weight'],
                    'target' => Tpkkp::paramTargets()[$p['code']] ?? 0,
                    'jumlahItem' => count($p['items']),
                ])->values(),
            ];
        }

        return Inertia::render('Tpkkp/Tentang', [
            'judul'    => 'PTPKKP — Instrumen',
            'subjudul' => 'Struktur instrumen, ambang kategori, dan cara nilai dihitung',
            'picker'   => \App\Support\TpkkpNav::untukInertia($a->tahun, $tahunn),

            'meta' => [
                'judul' => Tpkkp::meta()['title'] ?? 'Instrumen PTPKKP',
                'basis' => Tpkkp::meta()['basis'] ?? '',
            ],

            'ringkas' => [
                ['label' => 'Indikator',    'nilai' => (string) count(Tpkkp::indicators())],
                ['label' => 'Parameter',    'nilai' => (string) $parameter],
                ['label' => 'Item',         'nilai' => (string) Tpkkp::totalItems()],
                ['label' => 'Target total', 'nilai' => number_format(Tpkkp::totalTarget(), 2)],
            ],

            'ambang' => collect(Tpkkp::ref()['thresholds'])->map(fn ($t, $i) => [
                'label' => $t['label'],
                'batas' => rtrim(rtrim(number_format((float) $t['lt'], 4, '.', ''), '0'), '.'),
                'warna' => Tpkkp::levelHex($i + 1),
            ])->values()->all(),

            'indikator' => $daftarIndikator,
        ]);
    }
}
