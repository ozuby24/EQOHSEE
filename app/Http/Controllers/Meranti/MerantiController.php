<?php

namespace App\Http\Controllers\Meranti;

use App\Http\Controllers\Controller;
use App\Models\Meranti as M;
use App\Support\Meranti\{Geo, Hitung, Katalog};
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;

/**
 * MERANTI — Monitoring, Evaluation of Reclamation and Nursery Information.
 *
 * Dasbor analitik, dua puluh lima daftar modul (nursery, material,
 * rehabilitasi DAS, reklamasi, administrasi, data master), Aktual
 * Pencapaian P0/P1/P2 per blok, dan Peta Spasial dari KML/KMZ/GeoJSON.
 * Kolom tiap modul dibaca dari App\Support\Meranti\Katalog; perhitungan
 * dari App\Support\Meranti\Hitung — keduanya dipindahkan dari Code.gs.
 */
class MerantiController extends Controller
{
    use BantuMeranti;

    /* ═══════════ dasbor ═══════════ */

    public function index(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $ambil = fn (string $kelas) => $this->kueri($kelas, $c)->get();
        $progres = $ambil(M\Progres::class);

        $tahun = (string) $request->query('tahun', '');
        if ($tahun === '') $tahun = (string) ($progres->max('tahun') ?? 'semua');

        $d = Hitung::dasbor([
            'stok_bibit' => $ambil(M\StokBibit::class), 'stok_material' => $ambil(M\StokMaterial::class),
            'progres' => $progres, 'target' => $ambil(M\TargetBlok::class), 'vendor' => $ambil(M\ProjectVendor::class),
            'rencana' => $ambil(M\RencanaReklamasi::class), 'penilaian' => $ambil(M\Penilaian::class),
        ], $tahun);

        // Mutasi nursery 30 hari terakhir.
        $sejak = now()->subDays(30)->toDateString();
        $mutasi = [];
        foreach (['bibit_masuk' => 'Masuk', 'bibit_keluar' => 'Keluar', 'bibit_kembali' => 'Kembali', 'bibit_mati' => 'Mati', 'bibit_rusak' => 'Rusak'] as $k => $label) {
            $mutasi[] = ['kunci' => $k, 'label' => $label, 'slug' => Katalog::modul($k)['slug'],
                'jumlah' => (int) $this->kueri(Katalog::modul($k)['kelas'], $c)->where('tanggal', '>=', $sejak)->sum('jumlah')];
        }

        $jaminan = $ambil(M\Jaminan::class);
        $pengajuan = $ambil(M\PengajuanDana::class);
        $bibitHabis = $this->kueri(M\StokBibit::class, $c)->where(fn ($q) => $q->whereNull('stok')->orWhere('stok', '<=', 0))->orderBy('nama_bibit')->pluck('nama_bibit');

        return Inertia::render('Meranti/Dasbor', [
            'subjudul' => 'Ringkasan monitoring reklamasi & pembibitan '.$this->namaPerusahaan($c).'.',
            'dasbor' => $d,
            'mutasi' => $mutasi,
            'jaminan' => [
                'total' => (float) $jaminan->sum('nilai'),
                'perStatus' => $jaminan->groupBy(fn ($j) => $j->status ?: 'Tanpa status')->map(fn ($g, $s) => ['label' => $s, 'nilai' => (float) $g->sum('nilai'), 'n' => $g->count()])->values()->all(),
            ],
            'pengajuan' => [
                'total' => (float) $pengajuan->sum('nilai'), 'n' => $pengajuan->count(),
                'menunggu' => $pengajuan->where('status', 'Diajukan')->count(),
            ],
            'periodeAktif' => $this->kueri(M\Periode::class, $c)->where('status', 'Aktif')->orderByDesc('tanggal_mulai')->first(['nama_periode', 'tanggal_mulai', 'tanggal_selesai']),
            'bibitHabis' => $bibitHabis->all(),
            'peta' => $this->fitur($this->kueri(M\Peta::class, $c)->orderBy('id')->get(), 160),
            'ubin' => $this->ubinPeta(),
            'jumlahModul' => collect(Katalog::semua())->map(fn ($m, $k) => ['kunci' => $k, 'judul' => $m['judul'], 'slug' => $m['slug'], 'grup' => $m['grup'], 'ikon' => $m['ikon'],
                'n' => $this->kueri($m['kelas'], $c)->count()])->values()->all(),
            'izin' => $this->izin(),
            'perusahaan' => $c, 'namaPerusahaan' => $this->namaPerusahaan($c), 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    /* ═══════════ daftar modul generik ═══════════ */

    public function daftar(Request $request, string $modul)
    {
        $m = Katalog::modul($modul) ?? abort(404);
        $c = $this->perusahaanAktif($request);

        $q = $this->kueri($m['kelas'], $c)->with('user:id,name');
        if ($modul !== 'progres_mingguan') {
            foreach ($m['urut'] ?? [] as [$kolom, $arah]) $q->orderBy($kolom, $arah);
        }
        $baris = $q->limit(5000)->get();
        if ($modul === 'progres_mingguan') $baris = Hitung::urutProgres($baris);

        $kolom = array_column(array_filter($m['kolom'], fn ($k) => $k['daftar'] ?? true), 'k');

        return Inertia::render('Meranti/Modul', [
            'judul' => $m['judul'], 'subjudul' => $m['ket'],
            'modul' => Katalog::untukHalaman($modul),
            'baris' => $baris->map(fn ($b) => $this->barisHalaman($b, $kolom, $modul))->values()->all(),
            'saran' => $this->saran($m, $c),
            'izin' => $this->izin($m),
            'nav' => $this->navModul(),
            'setelan' => $modul === 'actual_capaian' ? ['folder_drive' => $this->kueri(M\Setelan::class, $c)->value('folder_drive')] : null,
            'perusahaan' => $c, 'namaPerusahaan' => $this->namaPerusahaan($c), 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    private function barisHalaman($b, array $kolom, string $modul): array
    {
        $d = ['id' => $b->id];
        foreach ($kolom as $k) {
            $v = $b->getAttribute($k);
            $d[$k] = $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
        }
        $d['_oleh'] = $b->user?->name;
        $d['_dibuat'] = $b->created_at?->format('Y-m-d H:i');
        $d['_impor'] = $b->asal_ref !== null;
        if ($modul === 'peta_spasial') $d['_geometri'] = Geo::untukPeta($b->geojson)['type'] ?? null;

        return $d;
    }

    /** Isian saran (datalist) yang dipakai kolom modul ini. */
    private function saran(array $m, ?int $c): array
    {
        $perlu = array_unique(array_filter(array_column($m['kolom'], 'saran')));
        $nilai = fn (string $kelas, string $kolom) => $this->kueri($kelas, $c)->whereNotNull($kolom)->distinct()->orderBy($kolom)->limit(400)->pluck($kolom);
        $keluar = [];

        foreach ($perlu as $s) {
            $daftar = match ($s) {
                'tanaman' => $nilai(M\Tanaman::class, 'nama_tanaman')->merge($nilai(M\StokBibit::class, 'nama_bibit')),
                'blok' => $nilai(M\TargetBlok::class, 'nama_blok')->merge($nilai(M\Progres::class, 'nama_blok'))->merge($nilai(M\Penilaian::class, 'blok')),
                'pelaksana' => $nilai(M\Pelaksana::class, 'nama_pelaksana')->merge($nilai(M\Progres::class, 'pelaksana')),
                'project' => $nilai(M\Project::class, 'nama_project'),
                'material' => $nilai(M\StokMaterial::class, 'nama_material'),
                'satuan' => collect(Katalog::SATUAN)->merge($nilai(M\StokMaterial::class, 'nama_satuan')),
                'periode' => $nilai(M\Periode::class, 'nama_periode'),
                default => collect(),
            };
            $keluar[$s] = $daftar->map(fn ($v) => trim((string) $v))->filter()->unique(fn ($v) => mb_strtolower($v))
                ->sort(fn ($a, $b) => Hitung::urutBlok($a, $b))->values()->all();
        }

        return $keluar;
    }

    public function simpan(Request $request, string $modul)
    {
        $m = Katalog::modul($modul) ?? abort(404);
        Gate::authorize(($m['master'] ?? false) ? 'meranti.master' : 'meranti.tambah');

        $data = $this->isian($request, $modul);
        $c = $this->perusahaanSimpan($request);
        $b = $m['kelas']::create(array_merge($data, ['company_id' => $c, 'user_id' => $request->user()->id]));
        $this->catat($request, $c, 'Tambah', $m['judul'], '#'.$b->id.' · '.$b->namaBaris());

        return back()->with('ok', $m['judul'].': '.$b->namaBaris().' tersimpan.');
    }

    public function perbarui(Request $request, string $modul, int $id)
    {
        $m = Katalog::modul($modul) ?? abort(404);
        Gate::authorize(($m['master'] ?? false) ? 'meranti.master' : 'meranti.ubah');

        $b = $m['kelas']::query()->whereKey($id)->firstOrFail();
        $b->fill($this->isian($request, $modul))->save();
        $this->catat($request, $b->company_id, 'Ubah', $m['judul'], '#'.$b->id.' · '.$b->namaBaris());

        return back()->with('ok', $m['judul'].': '.$b->namaBaris().' diperbarui.');
    }

    public function hapus(Request $request, string $modul, int $id)
    {
        $m = Katalog::modul($modul) ?? abort(404);
        Gate::authorize(($m['master'] ?? false) ? 'meranti.master' : 'meranti.hapus');

        $b = $m['kelas']::query()->whereKey($id)->firstOrFail();
        $nama = $b->namaBaris();
        $b->delete();
        $this->catat($request, $b->company_id, 'Hapus', $m['judul'], '#'.$id.' · '.$nama);

        return back()->with('ok', $m['judul'].': '.$nama.' dihapus.');
    }

    /** Isian formulir tervalidasi, ditambah kolom hitung (Matrik 17). */
    private function isian(Request $request, string $modul): array
    {
        $m = Katalog::modul($modul);
        $data = $request->validate(Katalog::aturan($modul), [], collect($m['kolom'])->pluck('l', 'k')->all());

        foreach (Katalog::kolomIsian($modul) as $k) {
            $v = $data[$k['k']] ?? null;
            if ($k['t'] === 'angka' && $v !== null && $v !== '') $data[$k['k']] = ($k['des'] ?? 2) === 0 ? (int) round((float) $v) : (float) $v;
            if (is_string($v)) $data[$k['k']] = trim($v) === '' ? null : trim($v);
        }

        if (($m['hitung'] ?? null) === 'matrik17') {
            $data = array_merge($data, Hitung::matrik17($data['nilai_penatagunaan'] ?? 0, $data['nilai_revegetasi'] ?? 0, $data['nilai_penyelesaian'] ?? 0));
        }
        if (array_key_exists('penginput', $data) && blank($data['penginput'])) $data['penginput'] = mb_substr((string) $request->user()?->name, 0, 100);
        if ($modul === 'peta_spasial' && filled($data['geojson'] ?? null)) {
            try {
                $gj = json_decode($data['geojson'], true);
                $g = is_array($gj) ? Geo::dariGeojson($gj) : [];
            } catch (RuntimeException) {
                $g = [];
            }
            if (!$g) throw ValidationException::withMessages(['geojson' => 'GeoJSON tidak dapat dibaca atau tidak berisi geometri.']);
            $data['geojson'] = json_encode($g[0]['geometry'], JSON_UNESCAPED_UNICODE);
            if (blank($data['luas_ha'] ?? null) && $g[0]['geometry']['type'] === 'Polygon') $data['luas_ha'] = round(Geo::luasHa($g[0]['geometry']['coordinates'][0]), 6);
        }

        return $data;
    }

    /** Ekspor CSV sebuah modul — kolom berlabel seperti tabel MERANTI. */
    public function csv(Request $request, string $modul)
    {
        $m = Katalog::modul($modul) ?? abort(404);
        $c = $this->perusahaanAktif($request);
        $q = $this->kueri($m['kelas'], $c);
        foreach ($m['urut'] ?? [] as [$kolom, $arah]) if ($modul !== 'progres_mingguan') $q->orderBy($kolom, $arah);
        $baris = $q->get();
        if ($modul === 'progres_mingguan') $baris = Hitung::urutProgres($baris);
        $kolom = array_filter($m['kolom'], fn ($k) => ($k['daftar'] ?? true) || $modul === 'peta_spasial');

        return response()->streamDownload(function () use ($baris, $kolom) {
            $f = fopen('php://output', 'w');
            fwrite($f, "\xEF\xBB\xBF");
            fputcsv($f, array_merge(['ID'], array_map(fn ($k) => $k['l'].(isset($k['satuan']) ? ' ('.$k['satuan'].')' : ''), $kolom), ['Waktu Input']));
            foreach ($baris as $b) {
                $r = [$b->id];
                foreach ($kolom as $k) {
                    $v = $b->getAttribute($k['k']);
                    $r[] = $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v;
                }
                $r[] = $b->created_at?->format('Y-m-d H:i');
                fputcsv($f, $r);
            }
            fclose($f);
        }, 'meranti-'.$m['slug'].'-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ═══════════ aktual pencapaian ═══════════ */

    public function capaian(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $progres = $this->kueri(M\Progres::class, $c)->get();
        $target = $this->kueri(M\TargetBlok::class, $c)->get();
        $tahun = (string) $request->query('tahun', '');
        if ($tahun === '') $tahun = (string) ($progres->max('tahun') ?? $target->max('tahun') ?? 'Semua');

        return Inertia::render('Meranti/Capaian', [
            'subjudul' => '% capaian P0 · P1 · P2 per blok — Σ realisasi Progres Mingguan ÷ Σ Target Blok.',
            'capaian' => Hitung::capaian($progres, $target, $tahun),
            'pekerjaan' => Katalog::LABEL_PEKERJAAN,
            'izin' => $this->izin(),
            'perusahaan' => $c, 'namaPerusahaan' => $this->namaPerusahaan($c), 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    /* ═══════════ peta spasial ═══════════ */

    public function peta(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $baris = $this->kueri(M\Peta::class, $c)->orderBy('id')->get();

        return Inertia::render('Meranti/Peta', [
            'subjudul' => 'Blok, petak, batas area, dan titik tanam dari KML, KMZ, atau GeoJSON.',
            'fitur' => $this->fitur($baris),
            'ringkas' => [
                'fitur' => $baris->count(), 'luas' => round((float) $baris->sum('luas_ha'), 2),
                'kategori' => $baris->groupBy(fn ($b) => $b->kategori ?: 'Lainnya')->map(fn ($g, $k) => ['label' => $k, 'n' => $g->count(), 'luas' => round((float) $g->sum('luas_ha'), 2)])->values()->all(),
                'sumber' => $baris->groupBy(fn ($b) => $b->sumber_file ?: 'Manual')->map(fn ($g, $k) => ['label' => $k, 'n' => $g->count()])->values()->all(),
            ],
            'kategori' => Katalog::KATEGORI_PETA,
            'ubin' => $this->ubinPeta(),
            'izin' => $this->izin(Katalog::modul('peta_spasial')),
            'perusahaan' => $c, 'namaPerusahaan' => $this->namaPerusahaan($c), 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    /**
     * Sumber ubin peta satelit (config/meranti.php) — null bila dimatikan,
     * dan halaman lalu hanya menawarkan peta skema. Host yang sama menjadi
     * kelonggaran CSP di TajukKeamanan.
     */
    private function ubinPeta(): ?array
    {
        $k = config('meranti.peta');
        if (!($k['aktif'] ?? false) || blank($k['satelit'] ?? null)) return null;

        return [
            'satelit' => $k['satelit'], 'label' => $k['label'] ?: null, 'medan' => $k['medan'] ?: null,
            'atribusi' => $k['atribusi'], 'atribusiMedan' => $k['atribusi_medan'] ?? null, 'zoomMaks' => (int) ($k['zoom_maks'] ?? 19),
        ];
    }

    /** @param Collection<int, M\Peta> $baris */
    private function fitur(Collection $baris, ?int $maksTitik = null): array
    {
        return $baris->map(fn (M\Peta $b) => [
            'id' => $b->id, 'nama' => $b->nama_fitur, 'kategori' => $b->kategori, 'petak' => $b->petak, 'week' => $b->week,
            'month' => $b->month, 'year' => $b->year, 'luas' => $b->luas_ha, 'sumber' => $b->sumber_file,
            'geometri' => Geo::untukPeta($b->geojson, $maksTitik),
        ])->filter(fn ($f) => $f['geometri'] !== null)->values()->all();
    }

    public function unggahPeta(Request $request)
    {
        Gate::authorize('meranti.tambah');
        $request->validate([
            'berkas' => ['required', 'file', 'max:20480'],
            'kategori' => ['nullable', 'in:'.implode(',', Katalog::KATEGORI_PETA)],
        ], [], ['berkas' => 'berkas peta']);

        $berkas = $request->file('berkas');
        $nama = $berkas->getClientOriginalName();
        if (!preg_match('/\.(kml|kmz|geojson|json|xml)$/i', $nama)) {
            return back()->withErrors(['berkas' => 'Format tidak dikenali. Gunakan .kml, .kmz, atau .geojson.']);
        }

        try {
            $fitur = Geo::bacaBerkas($berkas->getRealPath(), $nama);
        } catch (RuntimeException $e) {
            return back()->withErrors(['berkas' => $e->getMessage()]);
        }
        if (!$fitur) return back()->withErrors(['berkas' => 'Tidak ada placemark atau geometri pada berkas.']);

        $c = $this->perusahaanSimpan($request);
        $mulai = (int) $this->kueri(M\Peta::class, $c)->max('id') + 1;
        foreach ($fitur as $i => $f) {
            $d = Geo::keBaris($f, $nama, $mulai + $i);
            if ($request->filled('kategori')) $d['kategori'] = $request->input('kategori');
            M\Peta::create($d + ['company_id' => $c, 'user_id' => $request->user()->id]);
        }
        $this->catat($request, $c, 'Tambah', 'Peta Spasial', $nama.' | '.count($fitur).' fitur');

        return back()->with('ok', count($fitur).' fitur dari '.$nama.' tersimpan di Peta Spasial.');
    }

    public function geojson(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $fitur = $this->kueri(M\Peta::class, $c)->orderBy('id')->get()->map(function (M\Peta $b) {
            $g = $b->geojson ? json_decode($b->geojson, true) : null;

            return $g ? ['type' => 'Feature', 'geometry' => $g, 'properties' => [
                'id' => $b->id, 'nama_fitur' => $b->nama_fitur, 'kategori' => $b->kategori, 'petak' => $b->petak, 'week' => $b->week,
                'month' => $b->month, 'year' => $b->year, 'luas_ha' => $b->luas_ha, 'sumber_file' => $b->sumber_file,
            ]] : null;
        })->filter()->values()->all();

        return response()->json(['type' => 'FeatureCollection', 'features' => $fitur], 200, [
            'Content-Disposition' => 'attachment; filename="meranti-peta-spasial-'.now()->format('Ymd').'.geojson"',
        ], JSON_UNESCAPED_UNICODE);
    }
}
