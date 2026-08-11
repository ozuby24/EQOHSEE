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
                $daftarParam[] = ['code' => $P['code'], 'name' => $P['name'], 'n' => count($P['items'])];
            }
        }

        $p = (string) $request->get('p', '');
        if (!$p || !collect($daftarParam)->firstWhere('code', $p)) {
            $p = $daftarParam[0]['code'] ?? '';
        }

        $q     = trim((string) $request->get('q', ''));
        $par   = Tpkkp::paramByCode($p);
        $items = [];

        foreach ($par['items'] ?? [] as $it) {
            if ($q !== ''
                && stripos($it['code'], $q) === false
                && stripos($it['name'], $q) === false) continue;

            $rub = [];
            foreach ($it['methods'] as $m) {
                $r = Tpkkp::rubrikFor($m, $it['code']);
                if ($r) $rub[$m] = $r;
            }
            $items[] = ['item' => $it, 'acuan' => Tpkkp::rubrik()['REF|' . $it['code']] ?? null, 'rubrik' => $rub];
        }

        return view('tpkkp.rubrik', [
            'a' => $a, 'hasil' => $hasil, 'tahunn' => $tahunn,
            'daftarParam' => $daftarParam, 'paramAktif' => $p, 'items' => $items, 'q' => $q,
            'target' => Tpkkp::target(),
        ]);
    }

    /* ---------- Jadwal ---------- */

    public function jadwal(Request $request)
    {
        [$a, $hasil, $tahunn] = $this->base($request);

        $rows = $a->jadwal ?: TpkkpAssessment::jadwalSeed();

        $tahap = [];
        foreach ($rows as $i => $r) {
            $t = $r['tahap'] ?? 'Lainnya';
            $tahap[$t][] = $r + ['idx' => $i];
        }

        $isi = array_values(array_filter($rows, fn ($r) => empty($r['header'])));
        $selesai = count(array_filter($isi, fn ($r) => !empty($r['done'])));

        return view('tpkkp.jadwal', [
            'a' => $a, 'hasil' => $hasil, 'tahunn' => $tahunn,
            'tahap' => $tahap, 'jumlah' => count($isi), 'selesai' => $selesai,
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
        $m   = (string) $request->get('m', 'KS');
        if (!isset($ref[$m]) || !is_array($ref[$m] ?? null)) $m = 'KS';

        return view('tpkkp.sampel', [
            'a' => $a, 'hasil' => $hasil, 'tahunn' => $tahunn,
            'ref' => $ref, 'metodeAktif' => $m,
            'daftarMetode' => array_values(array_filter(
                array_keys($ref),
                fn ($k) => !in_array($k, ['population', 'companies'], true)
            )),
        ]);
    }

    /* ---------- Mitra Kerja & Akses ---------- */

    public function roster(Request $request)
    {
        [$a, $hasil, $tahunn] = $this->base($request);

        return view('tpkkp.roster', compact('a', 'hasil', 'tahunn'));
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

        return view('tpkkp.data', compact('a', 'hasil', 'tahunn'));
    }

    public function ekspor(Request $request)
    {
        $a = TpkkpAssessment::forYear((int) ($request->get('tahun') ?? session('tpkkp_tahun') ?? now()->year));

        $isi = [
            'tahun'    => $a->tahun,
            'judul'    => $a->judul,
            'status'   => $a->status,
            'scores'   => $a->scores   ?? [],
            'roster'   => $a->roster   ?? [],
            'profil'   => $a->profil   ?? [],
            'tim'      => $a->tim      ?? [],
            'programs' => $a->programs ?? [],
            'jadwal'   => $a->jadwal   ?? [],
            'sampling' => $a->sampling ?? [],
            'diekspor' => now()->toIso8601String(),
        ];

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
        foreach (['scores', 'roster', 'profil', 'tim', 'programs', 'jadwal', 'sampling'] as $k) {
            if (isset($isi[$k]) && is_array($isi[$k])) { $a->{$k} = $isi[$k]; $dipakai[] = $k; }
        }
        foreach (['judul', 'status'] as $k) {
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
            file_put_contents(
                $dir . '/tpkkp-' . $a->tahun . '-' . $tanda . '-' . now()->format('Ymd-His') . '.json',
                json_encode($a->only(['tahun', 'scores', 'roster', 'profil', 'tim', 'programs', 'jadwal', 'sampling']),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
        } catch (\Throwable $e) {
            // gagal mencadangkan tidak boleh menghentikan aksi
        }
    }
}
