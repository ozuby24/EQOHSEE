<?php

namespace App\Http\Controllers;

use App\Models\TpkkpAssessment;
use App\Support\Tpkkp;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Halaman lanjutan TPKKP — hanya membaca hitungan dari App\Support\Tpkkp.
 * Dipisah dari TpkkpController supaya penambahan ini tidak menyentuh alur
 * penilaian yang sudah berjalan.
 */
class TpkkpLanjutController extends Controller
{
    /**
     * Kunci yang dikenali impor.
     *
     * Dipakai tiga kali: menulis berkas ekspor, membaca berkas impor, dan
     * memberi tahu layar kunci apa saja yang akan terpakai sebelum orang
     * menekan "Impor & timpa". Ditulis berulang, ketiganya akan berbeda
     * diam-diam — dan yang paling merugikan adalah layar yang menjanjikan
     * sesuatu yang ternyata dibuang.
     */
    public const KUNCI_LARIK = ['scores', 'roster', 'profil', 'tim', 'programs', 'jadwal', 'sampling'];
    public const KUNCI_TEKS  = ['judul', 'status'];

    private function base(Request $request): array
    {
        $tahun = (int) ($request->get('tahun') ?? session('tpkkp_tahun') ?? now()->year);
        if ($tahun < 2000 || $tahun > 2100) $tahun = (int) now()->year;
        session(['tpkkp_tahun' => $tahun]);

        $a = TpkkpAssessment::forYear($tahun);

        return [$a, Tpkkp::totalCalc($a->scores ?? []), TpkkpAssessment::orderByDesc('tahun')->pluck('tahun')->all()];
    }

    /* ---------- Matriks penilaian ---------- */

    public function matriks(Request $request)
    {
        [$a, $hasil, $tahunn] = $this->base($request);

        /* Seluruh baris dikirim tanpa disaring, lalu pencariannya
           dikerjakan di peramban. Versi Blade menyaring di server dan
           memuat ulang halaman tiap kali kata kuncinya berubah — untuk
           tabel yang seluruhnya sudah ada di memori, itu perjalanan
           bolak-balik yang tidak menghasilkan apa pun selain jeda. */
        $metode = array_keys(Tpkkp::methods());

        $indikator = [];
        foreach ($hasil['indicators'] as $I) {
            $params = [];
            foreach ($I['params'] as $P) {
                $items = [];
                foreach ($P['items'] as $c) {
                    $it = Tpkkp::itemByCode($c['code']);

                    $items[] = [
                        'kode'      => $c['code'],
                        'nama'      => $c['name'],
                        'metode'    => $it['methods'] ?? [],
                        'perMetode' => $c['perMethod'],
                        'nilai'     => $c['nilai'],
                        'maks'      => $c['max'],
                        'capaian'   => $c['achv'],
                        // Kategori hanya bermakna bila seluruh metodenya
                        // sudah dinilai; sebelum itu angkanya masih akan
                        // berubah dan lencananya menyesatkan.
                        'kategori'  => $c['complete'] ? $c['category'] : null,
                        'warna'     => Tpkkp::levelHex(Tpkkp::level($c['category'])),
                    ];
                }

                $params[] = [
                    'kode' => $P['code'], 'nama' => $P['name'],
                    'bobot' => $P['weight'], 'target' => $P['target'],
                    'nilai' => $P['nilai'], 'maks' => $P['max'], 'rasio' => $P['ratio'],
                    'kategori' => $P['category'],
                    'warna' => Tpkkp::levelHex(Tpkkp::level($P['category'])),
                    'items' => $items,
                ];
            }

            $indikator[] = [
                'kode' => $I['code'], 'nama' => $I['name'],
                'bobot' => $I['weight'], 'rasio' => $I['ratio'],
                'kategori' => $I['category'],
                'warna' => Tpkkp::levelHex(Tpkkp::level($I['category'])),
                'parameter' => $params,
            ];
        }

        return Inertia::render('Tpkkp/Matriks', [
            'judul'     => 'PTPKKP — Matriks',
            'subjudul'  => "Seluruh item pengukuran per metode, periode {$a->tahun}",
            'picker'    => \App\Support\TpkkpNav::untukInertia($a->tahun, $tahunn),
            'metode'    => $metode,
            'indikator' => $indikator,
        ]);
    }

    /* ---------- Summary ---------- */

    public function summary(Request $request)
    {
        [$a, $hasil, $tahunn] = $this->base($request);

        $indikator = [];
        foreach ($hasil['indicators'] as $I) {
            $params = [];
            foreach ($I['params'] as $P) {
                $params[] = [
                    'kode' => $P['code'], 'nama' => $P['name'],
                    'bobot' => $P['weight'], 'skor' => $P['score'],
                    'rasio' => $P['ratio'], 'target' => $P['target'],
                    'kategori' => $P['category'],
                    'warna' => Tpkkp::levelHex(Tpkkp::level($P['category'])),

                    /* Selisih dihitung server. Di klien ia harus tahu
                       kapan hasilnya null — capaian atau target yang belum
                       ada bukan berarti selisihnya nol. */
                    'gap' => ($P['score'] === null || $P['target'] === null)
                        ? null : $P['score'] - $P['target'],
                ];
            }

            $indikator[] = [
                'kode' => $I['code'], 'nama' => $I['name'],
                'bobot' => $I['weight'], 'skor' => $I['score'],
                'rasio' => $I['ratio'], 'target' => $I['target'],
                'kategori' => $I['category'],
                'warna' => Tpkkp::levelHex(Tpkkp::level($I['category'])),
                'gap' => $I['score'] === null ? null : $I['score'] - $I['target'],
                'parameter' => $params,
            ];
        }

        return Inertia::render('Tpkkp/Summary', [
            'judul'    => 'PTPKKP — Summary',
            'subjudul' => "Capaian lawan target per parameter, periode {$a->tahun}",
            'picker'   => \App\Support\TpkkpNav::untukInertia($a->tahun, $tahunn),
            'indikator'=> $indikator,
            'total'    => [
                'skor'     => $hasil['score'],
                'rasio'    => $hasil['score'],
                'target'   => $hasil['target'],
                'kategori' => $hasil['category'],
                'warna'    => Tpkkp::levelHex(Tpkkp::level($hasil['category'])),
                'gap'      => $hasil['score'] === null ? null : $hasil['score'] - $hasil['target'],
            ],
        ]);
    }

    /* ---------- Hasil ---------- */

    public function hasil(Request $request)
    {
        [$a, $hasil, $tahunn] = $this->base($request);

        return Inertia::render('Tpkkp/Hasil', [
            'judul'    => 'PTPKKP — Hasil',
            'subjudul' => "Pencapaian per indikator dan per metode, periode {$a->tahun}",
            'picker'   => \App\Support\TpkkpNav::untukInertia($a->tahun, $tahunn),

            /* Rentang kategori diturunkan dari ambang acuan, tidak diketik
               ulang. Versi Blade menuliskannya sebagai teks tetap, dan teks
               tetap seperti itu diam saja ketika ambangnya berubah. */
            'rentang'  => self::rentangKategori(),

            'total' => [
                'skor'     => $hasil['score'],
                'target'   => $hasil['target'],
                'kategori' => $hasil['category'],
                'warna'    => Tpkkp::levelHex(Tpkkp::level($hasil['category'])),
            ],

            'indikator' => collect($hasil['indicators'])->map(fn ($I) => [
                'kode' => $I['code'], 'nama' => $I['name'],
                'bobot' => $I['weight'], 'skor' => $I['score'], 'rasio' => $I['ratio'],
                'kategori' => $I['category'],
                'warna' => Tpkkp::levelHex(Tpkkp::level($I['category'])),
            ])->values()->all(),

            'metode' => collect(Tpkkp::methodTotals($a->scores ?? []))->map(fn ($m) => [
                'kode' => $m['key'], 'nama' => $m['name'],
                'items' => $m['items'], 'terisi' => $m['filled'],
                'maks' => $m['max'], 'jumlah' => $m['sum'], 'rasio' => $m['ratio'],
                'kategori' => $m['category'],
                'warna' => Tpkkp::levelHex(Tpkkp::level($m['category'])),
            ])->values()->all(),
        ]);
    }

    /**
     * Rentang tiap kategori dalam bentuk teks, diturunkan dari ambang.
     *
     * Ambang acuan berupa batas atas tiap tingkat; rentangnya disusun
     * dari batas tingkat sebelumnya sampai batas tingkat itu sendiri.
     */
    private static function rentangKategori(): array
    {
        $out = [];
        $bawah = 0.0;

        foreach (Tpkkp::ref()['thresholds'] as $i => $t) {
            $atas = (float) $t['lt'];
            $terakhir = $i === count(Tpkkp::ref()['thresholds']) - 1;

            $out[] = [
                'teks'     => $terakhir
                    ? sprintf('%s ≤ x ≤ 1,0', number_format($bawah, 1, ',', '.'))
                    : sprintf('%s ≤ x < %s', number_format($bawah, 1, ',', '.'), number_format($atas, 1, ',', '.')),
                'kategori' => $t['label'],
                'warna'    => Tpkkp::levelHex($i + 1),
            ];

            $bawah = $atas;
        }

        // Tingkat pertama tidak punya batas bawah selain nol.
        if (isset($out[0])) {
            $out[0]['teks'] = 'x < '.number_format((float) Tpkkp::ref()['thresholds'][0]['lt'], 1, ',', '.');
        }

        return $out;
    }

    /* ---------- Rubrik Kepdirjen ---------- */

    public function rubrik(Request $request)
    {
        [$a, $hasil, $tahunn] = $this->base($request);

        $daftarParam = [];
        foreach (Tpkkp::indicators() as $I) {
            foreach ($I['params'] as $P) {
                $daftarParam[] = [
                    'kode' => $P['code'], 'nama' => $P['name'], 'jumlah' => count($P['items']),
                    'url'  => route('tpkkp.rubrik', ['p' => $P['code']]),
                ];
            }
        }

        $p = (string) $request->get('p', '');
        if (!$p || !collect($daftarParam)->firstWhere('kode', $p)) {
            $p = $daftarParam[0]['kode'] ?? '';
        }

        /* Item satu parameter dikirim seluruhnya lalu dicari di peramban.
           Versi Blade menyaringnya di server dan memuat ulang halaman tiap
           kali kata kuncinya berubah, padahal isinya sudah ada di memori. */
        $par    = Tpkkp::paramByCode($p);
        $target = Tpkkp::target();
        $items  = [];

        foreach ($par['items'] ?? [] as $it) {
            $acuan = Tpkkp::rubrik()['REF|'.$it['code']] ?? null;

            $rubrik = [];
            foreach ($it['methods'] as $m) {
                $r = Tpkkp::rubrikFor($m, $it['code']);

                // Rubrik metode yang isinya sama persis dengan acuan tidak
                // ditampilkan dua kali — pengulangan itu membuat halaman
                // panjang tanpa menambah satu keterangan pun.
                if ($r && (!$acuan || $r !== $acuan)) {
                    $rubrik[] = ['metode' => $m, 'tingkat' => self::tingkat($r)];
                }
            }

            $items[] = [
                'kode'   => $it['code'],
                'nama'   => $it['name'],
                'metode' => $it['methods'],
                'maks'   => $it['max'],
                'acuan'  => $acuan ? self::tingkat($acuan) : null,
                'rubrik' => $rubrik,
                'target' => collect($target[$it['code']] ?? [])
                    ->map(fn ($teks, $m) => ['metode' => $m, 'teks' => trim($teks)])
                    ->values()->all(),
            ];
        }

        return Inertia::render('Tpkkp/Rubrik', [
            'judul'      => 'PTPKKP — Rubrik',
            'subjudul'   => 'Rubrik acuan Kepdirjen, lima tingkat per item',
            'picker'     => \App\Support\TpkkpNav::untukInertia($a->tahun, $tahunn),
            'parameter'  => $daftarParam,
            'paramAktif' => $p,
            'items'      => $items,
        ]);
    }

    /** Lima tingkat rubrik beserta warnanya. */
    private static function tingkat(array $rub): array
    {
        return collect($rub['l'] ?? [])->map(fn ($teks, $i) => [
            'tingkat' => $i + 1,
            'teks'    => $teks,
            'warna'   => Tpkkp::levelHex($i + 1),
        ])->values()->all();
    }

    /* ---------- Jadwal ---------- */

    public function jadwal(Request $request)
    {
        [$a, $hasil, $tahunn] = $this->base($request);

        $rows = $a->jadwal ?: TpkkpAssessment::jadwalSeed();

        $tahap = [];
        foreach ($rows as $i => $r) {
            if (!empty($r['header'])) continue;

            $mulai = max(1, (int) ($r['start'] ?? 1));
            $akhir = min(30, (int) ($r['end'] ?? 30));

            $tahap[$r['tahap'] ?? 'Lainnya'][] = [
                'idx'      => $i,
                'kegiatan' => $r['kegiatan'] ?? '',
                'keluaran' => $r['output'] ?? '',
                'mulai'    => $mulai,
                'akhir'    => $akhir,

                /* Posisi batang dihitung di server: rumus hari-ke-persen
                   ini sudah ada di versi Blade, dan rumus yang sama hidup
                   di dua tempat sudah sekali terbukti melenceng. */
                'kiri'     => ($mulai - 1) / 30 * 100,
                'lebar'    => max(3, ($akhir - $mulai + 1) / 30 * 100),
                'selesai'  => !empty($r['done']),
            ];
        }

        $daftar = [];
        foreach ($tahap as $nama => $baris) {
            $daftar[] = ['nama' => $nama, 'baris' => $baris];
        }

        return Inertia::render('Tpkkp/Jadwal', [
            'judul'       => 'PTPKKP — Jadwal',
            'subjudul'    => "Rencana penilaian 30 hari, periode {$a->tahun}",
            'picker'      => \App\Support\TpkkpNav::untukInertia($a->tahun, $tahunn),
            'tahun'       => $a->tahun,
            'tahap'       => $daftar,
            'bisaSunting' => $request->user()->isAdmin(),
        ]);
    }

    public function saveJadwal(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Hanya admin yang boleh mengubah jadwal.');

        $a    = TpkkpAssessment::forYear((int) ($request->get('tahun') ?? session('tpkkp_tahun') ?? now()->year));
        $rows = $a->jadwal ?: TpkkpAssessment::jadwalSeed();
        $done = array_map('intval', (array) $request->input('done', []));

        foreach ($rows as $i => $r) {
            if (!empty($r['header'])) continue;
            $rows[$i]['done'] = in_array($i, $done, true);
        }

        $a->jadwal = $rows;
        $a->save();

        return back()->with('ok', 'Jadwal diperbarui.');
    }

    /* ---------- Metode & Sampel ---------- */

    public function sampel(Request $request)
    {
        [$a, $hasil, $tahunn] = $this->base($request);

        $ref = Tpkkp::samplingRef();
        $co  = $ref['companies'] ?? [];

        $daftar = array_values(array_filter(
            array_keys($ref),
            fn ($k) => !in_array($k, ['population', 'companies'], true) && is_array($ref[$k] ?? null)
        ));

        $m = (string) $request->get('m', '');
        if (!in_array($m, $daftar, true)) $m = $daftar[0] ?? '';

        /*
         * Seluruh metode dikirim sekaligus, bukan satu per kunjungan.
         * Datanya kecil (tujuh metode kali delapan perusahaan) dan tetap,
         * jadi berpindah tab tidak perlu menyentuh server sama sekali.
         *
         * Pembulatannya tetap di sini. Angka rencana dibulatkan ke atas
         * per kolom sebelum dijumlah — membulatkan jumlahnya menghasilkan
         * angka yang berbeda, dan yang dipakai lapangan adalah per kolom.
         */
        $metode = [];
        foreach ($daftar as $k) {
            $blok  = $ref[$k];
            $baris = [];

            foreach ($co as $c) {
                $mgm = (float) ($blok['Management'][$c] ?? 0);
                $emp = (float) ($blok['Employee'][$c] ?? 0);

                $baris[] = [
                    'perusahaan' => $c,
                    'mgm'    => $mgm ? (int) ceil($mgm) : null,
                    'emp'    => $emp ? (int) ceil($emp) : null,
                    'jumlah' => ($mgm + $emp) ? (int) ceil($mgm) + (int) ceil($emp) : null,
                ];
            }

            /*
             * Dua total, dan keduanya perlu.
             *
             * Instrumen menyimpan alokasi proporsional dalam pecahan
             * (PT MIK 0,2 orang) beserta totalnya sendiri. Barisnya harus
             * dibulatkan ke atas — tidak ada seperlima orang yang bisa
             * diwawancarai — sehingga jumlah baris selalu lebih besar
             * daripada total pecahan itu, kadang dua kali lipat.
             *
             * Versi sebelumnya hanya menampilkan total instrumen, di baris
             * paling bawah kolom yang isinya baris terbulat. Total yang
             * tidak sama dengan penjumlahan kolomnya di atasnya bukan
             * pembulatan, melainkan angka yang salah dibaca siapa pun yang
             * memeriksanya.
             */
            $tm = isset($blok['totalMgm']) ? (float) $blok['totalMgm'] : null;
            $te = isset($blok['totalEmp']) ? (float) $blok['totalEmp'] : null;

            $metode[] = [
                'kode'  => $k,
                'baris' => $baris,

                // Penjumlahan baris yang benar-benar tampak di tabel.
                'total' => [
                    'mgm'    => array_sum(array_map(fn ($r) => (int) $r['mgm'], $baris)) ?: null,
                    'emp'    => array_sum(array_map(fn ($r) => (int) $r['emp'], $baris)) ?: null,
                    'jumlah' => array_sum(array_map(fn ($r) => (int) $r['jumlah'], $baris)) ?: null,
                ],

                // Total proporsional instrumen, sebelum dibulatkan.
                'acuan' => ($tm === null && $te === null) ? null : [
                    'mgm'    => $tm,
                    'emp'    => $te,
                    'jumlah' => round((float) $tm + (float) $te, 2),
                ],
            ];
        }

        return Inertia::render('Tpkkp/Sampel', [
            'judul'    => 'PTPKKP — Rencana Sampel',
            'subjudul' => "Alokasi responden per perusahaan menurut instrumen, periode {$a->tahun}",
            'picker'   => \App\Support\TpkkpNav::untukInertia($a->tahun, $tahunn),

            'populasi' => [
                'management' => (int) ($ref['population']['Management'] ?? 0),
                'employee'   => (int) ($ref['population']['Employee'] ?? 0),
                'total'      => (int) ($ref['population']['Total'] ?? 0),
            ],
            'metode'      => $metode,
            'metodeAwal'  => $m,
        ]);
    }

    /* ---------- Mitra Kerja & Akses ---------- */

    public function roster(Request $request)
    {
        [$a, $hasil, $tahunn] = $this->base($request);

        $metode = [];
        foreach (Tpkkp::methods() as $k => $m) {
            $label = $m['entityLabel'] ?? '';
            $ents  = $label ? $a->entitiesOf($k) : [];

            $metode[] = [
                'kode'         => $k,
                'nama'         => $m['name'],
                'labelEntitas' => $label,
                'punyaEntitas' => (bool) $label,
                'entitas'      => array_values($ents),
                // Daftar bawaan instrumen, dipakai kembali bila kotaknya
                // dikosongkan. Dikirim supaya layar bisa mengatakan apa
                // yang akan terjadi sebelum orang menekan simpan.
                'bawaan'       => array_values($m['entities'] ?? []),
            ];
        }

        return Inertia::render('Tpkkp/Roster', [
            'judul'    => 'PTPKKP — Mitra & Akses',
            'subjudul' => "Entitas yang dinilai pada tiap metode, periode {$a->tahun}",
            'picker'   => \App\Support\TpkkpNav::untukInertia($a->tahun, $tahunn),
            'tahun'    => $a->tahun,
            'metode'   => $metode,
            'bisaSunting' => $request->user()->isAdmin(),
        ]);
    }

    public function saveRoster(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Hanya admin yang boleh mengubah roster.');

        $a  = TpkkpAssessment::forYear((int) ($request->get('tahun') ?? session('tpkkp_tahun') ?? now()->year));
        $in = (array) $request->input('roster', []);
        $out = [];

        foreach (Tpkkp::methods() as $k => $m) {
            if (!($m['entityLabel'] ?? null)) continue;

            $baris = preg_split('/\r\n|\r|\n/', (string) ($in[$k] ?? ''));
            $bersih = [];
            foreach ($baris as $b) {
                $b = trim($b);
                if ($b !== '' && !in_array($b, $bersih, true)) $bersih[] = $b;
            }
            $out[$k] = $bersih ?: ($m['entities'] ?? []);
        }

        $a->roster = $out;
        $a->save();

        return back()->with('ok', 'Roster entitas tersimpan.');
    }

    /* ---------- Data & Koneksi ---------- */

    public function data(Request $request)
    {
        [$a, $hasil, $tahunn] = $this->base($request);

        return Inertia::render('Tpkkp/Data', [
            'judul'    => 'PTPKKP — Data & Koneksi',
            'subjudul' => "Ekspor, impor, dan pengosongan nilai periode {$a->tahun}",
            'picker'   => \App\Support\TpkkpNav::untukInertia($a->tahun, $tahunn),
            'tahun'    => $a->tahun,

            'ringkas' => [
                ['label' => 'Sel terisi',      'nilai' => $hasil['filledCells'] . ' / ' . $hasil['totalCells']],
                ['label' => 'Kelengkapan',     'nilai' => number_format($hasil['completeness'] * 100, 1) . '%'],
                ['label' => 'Program',         'nilai' => (string) count($a->programs ?? [])],
                ['label' => 'Kegiatan jadwal', 'nilai' => (string) count($a->jadwal ?? [])],
            ],

            'urlEkspor'    => route('tpkkp.data.ekspor', ['tahun' => $a->tahun]),
            'kunciDikenal' => array_merge(self::KUNCI_LARIK, self::KUNCI_TEKS),
            'bisaSunting'  => $request->user()->isAdmin(),
        ]);
    }

    public function ekspor(Request $request)
    {
        $a = TpkkpAssessment::forYear((int) ($request->get('tahun') ?? session('tpkkp_tahun') ?? now()->year));

        // Ditulis dari daftar kunci yang sama dengan yang dibaca impor,
        // supaya berkas hasil ekspor selalu bisa diimpor kembali utuh.
        $isi = ['tahun' => $a->tahun];
        foreach (self::KUNCI_TEKS as $k)  $isi[$k] = $a->{$k};
        foreach (self::KUNCI_LARIK as $k) $isi[$k] = $a->{$k} ?? [];
        $isi['diekspor'] = now()->toIso8601String();

        return response()->json($isi, 200, [
            'Content-Disposition' => 'attachment; filename="tpkkp-' . $a->tahun . '-' . now()->format('Ymd-His') . '.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function impor(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Hanya admin yang boleh mengimpor.');

        $d = $request->validate(['json' => ['required', 'string']]);
        $isi = json_decode($d['json'], true);

        if (!is_array($isi)) {
            return back()->withErrors(['json' => 'JSON tidak bisa dibaca. Pastikan disalin utuh.']);
        }

        $a = TpkkpAssessment::forYear((int) ($request->get('tahun') ?? session('tpkkp_tahun') ?? now()->year));

        $this->cadangkan($a, 'sebelum-impor');

        $dipakai = [];
        foreach (self::KUNCI_LARIK as $k) {
            if (isset($isi[$k]) && is_array($isi[$k])) { $a->{$k} = $isi[$k]; $dipakai[] = $k; }
        }
        foreach (self::KUNCI_TEKS as $k) {
            if (isset($isi[$k]) && is_string($isi[$k])) { $a->{$k} = $isi[$k]; $dipakai[] = $k; }
        }
        $a->save();

        return back()->with('ok', 'Impor selesai. Kunci yang dipakai: ' . (implode(', ', $dipakai) ?: 'tidak ada'));
    }

    public function reset(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Hanya admin yang boleh mengosongkan nilai.');

        $a = TpkkpAssessment::forYear((int) ($request->get('tahun') ?? session('tpkkp_tahun') ?? now()->year));

        $this->cadangkan($a, 'sebelum-reset');

        $a->scores = [];
        $a->save();

        return back()->with('ok', 'Seluruh nilai periode ' . $a->tahun . ' dikosongkan. Salinan tersimpan di storage/app.');
    }

    private function cadangkan(TpkkpAssessment $a, string $tanda): void
    {
        try {
            $dir = storage_path('app');
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            // Akhiran acak, bukan hanya cap waktu: dua pengosongan dalam
            // detik yang sama menghasilkan nama berkas yang sama persis,
            // dan salinan kedua menimpa salinan pertama — justru satu-
            // satunya jalan pulang yang hilang.
            $nama = 'tpkkp-' . $a->tahun . '-' . $tanda . '-'
                  . now()->format('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.json';

            file_put_contents(
                $dir . '/' . $nama,
                json_encode($a->only(['tahun', 'scores', 'roster', 'profil', 'tim', 'programs', 'jadwal', 'sampling']),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        } catch (\Throwable $e) {
            // gagal mencadangkan tidak boleh menghentikan aksi
        }
    }
}
