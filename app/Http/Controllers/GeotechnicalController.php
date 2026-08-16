<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Company, GeoBacaan, GeoInstrumen, GeoLereng, TindakLanjut};
use App\Support\{Alur, KelengkapanShift, KopDokumen, Kestabilan, PeringatanGeoteknik};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Pemantauan Kestabilan Lereng.
 *
 * Alat bantu keputusan, bukan pengganti penilaian geoteknik oleh tenaga
 * kompeten. Yang disediakan modul ini adalah pengamatan yang terkumpul
 * rapi, laju yang terhitung seragam, dan penyimpangan geometri yang
 * terlihat lebih awal — bahan untuk mengambil keputusan, bukan
 * keputusannya.
 *
 * Karena itu tidak ada satu pun tempat di sini yang menyatakan sebuah
 * lereng aman. Yang ada adalah tempat mencatat siapa yang menyatakannya,
 * kapan, dan berdasarkan kajian yang mana.
 */
class GeotechnicalController extends Controller
{
    public function index(Request $r)     { return $this->halaman($r, 'dashboard'); }
    public function bacaan(Request $r)    { return $this->halaman($r, 'bacaan'); }
    public function lereng(Request $r)    { return $this->halaman($r, 'lereng'); }

    /* ---------- lereng ---------- */

    public function simpanLereng(Request $request)
    {
        $data = $this->pemilik($request->validate($this->aturanLereng()));
        $data['user_id'] = auth()->id();

        $lereng = GeoLereng::create($data);
        ActivityLog::write('Daftarkan lereng pantau', $lereng->kode.' — '.$lereng->nama, 'geoteknik');

        return back()->with('ok', 'Lereng tersimpan.');
    }

    public function ubahLereng(Request $request, GeoLereng $lereng)
    {
        $lereng->update($this->pemilik($request->validate($this->aturanLereng())));
        ActivityLog::write('Ubah lereng pantau', $lereng->kode, 'geoteknik');

        return back()->with('ok', 'Lereng diperbarui.');
    }

    public function hapusLereng(GeoLereng $lereng)
    {
        ActivityLog::write('Hapus lereng pantau', $lereng->kode, 'geoteknik');
        $lereng->delete();

        return back()->with('ok', 'Lereng dihapus.');
    }

    private function aturanLereng(): array
    {
        return [
            'company_id' => ['nullable', 'exists:companies,id'],
            'kode'       => ['required', 'string', 'max:40'],
            'nama'       => ['required', 'string', 'max:150'],
            'jenis'      => ['required', Rule::in(GeoLereng::JENIS)],
            'lokasi'     => ['nullable', 'string', 'max:150'],
            'litologi'   => ['nullable', 'string', 'max:150'],

            'tinggi_rencana_m'         => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'sudut_rencana_deg'        => ['nullable', 'numeric', 'min:0', 'max:89.9'],
            'tinggi_jenjang_rencana_m' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'lebar_berm_rencana_m'     => ['nullable', 'numeric', 'min:0', 'max:200'],

            'tinggi_aktual_m'          => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'sudut_aktual_deg'         => ['nullable', 'numeric', 'min:0', 'max:89.9'],
            'tinggi_jenjang_aktual_m'  => ['nullable', 'numeric', 'min:0', 'max:200'],
            'lebar_berm_aktual_m'      => ['nullable', 'numeric', 'min:0', 'max:200'],

            'fk_rencana'           => ['nullable', 'numeric', 'min:0.1', 'max:10'],
            'ppa_rencana_persen'   => ['nullable', 'numeric', 'min:0', 'max:100'],
            'kajian_oleh'          => ['nullable', 'string', 'max:150'],
            'kajian_tanggal'       => ['nullable', 'date'],
            'interval_kajian_hari' => ['nullable', 'integer', 'min:1', 'max:3650'],

            // Ambang TARP ditetapkan bertingkat; menerimanya terbalik
            // membuat lereng melewati "awas" sebelum "waspada".
            'ambang_waspada_mm_hari' => ['nullable', 'numeric', 'min:0.01', 'max:10000'],
            'ambang_siaga_mm_hari'   => ['nullable', 'numeric', 'min:0.01', 'max:10000', 'gt:ambang_waspada_mm_hari'],
            'ambang_awas_mm_hari'    => ['nullable', 'numeric', 'min:0.01', 'max:10000', 'gt:ambang_siaga_mm_hari'],

            'status'  => ['required', Rule::in(GeoLereng::STATUS)],
            'catatan' => ['nullable', 'string', 'max:3000'],
        ];
    }

    /* ---------- instrumen ---------- */

    public function simpanInstrumen(Request $request, GeoLereng $lereng)
    {
        $data = $request->validate([
            'kode'               => ['required', 'string', 'max:40'],
            'jenis'              => ['required', Rule::in(GeoInstrumen::JENIS)],
            'status'             => ['required', Rule::in(GeoInstrumen::STATUS)],
            'elevasi_m'          => ['nullable', 'numeric', 'min:-1000', 'max:10000'],
            'kalibrasi_terakhir' => ['nullable', 'date'],
        ]);

        if ($lereng->instrumen()->where('kode', $data['kode'])->exists()) {
            return back()->withErrors(['kode' => 'Kode alat ini sudah dipakai pada lereng yang sama.']);
        }

        $lereng->instrumen()->create($data);

        return back()->with('ok', 'Alat pantau ditetapkan pada lereng ini.');
    }

    public function ubahInstrumen(Request $request, GeoInstrumen $instrumen)
    {
        $instrumen->update($request->validate([
            'status' => ['required', Rule::in(GeoInstrumen::STATUS)],
        ]));

        return back()->with('ok', 'Status alat diperbarui.');
    }

    /* ---------- pembacaan ---------- */

    public function simpanBacaan(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id'       => ['nullable', 'exists:companies,id'],
            'geo_lereng_id'    => ['required', 'exists:geo_lerengs,id'],
            'geo_instrumen_id' => ['nullable', 'exists:geo_instrumens,id'],
            'tanggal'          => ['required', 'date'],
            'perpindahan_mm'   => ['required', 'numeric', 'min:-100000', 'max:1000000'],
            'retakan_mm'       => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'muka_air_m'       => ['nullable', 'numeric', 'min:-1000', 'max:10000'],
            'curah_hujan_mm'   => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'ada_gejala'       => ['nullable', 'boolean'],
            'gejala'           => ['nullable', 'string', 'max:2000'],
            'catatan'          => ['nullable', 'string', 'max:2000'],
        ]));

        // Alat harus milik lereng yang sama; tanpa ini, perpindahan satu
        // lereng dapat masuk ke deret waktu lereng lain dan lajunya
        // melompat tanpa ada yang benar-benar bergerak.
        if (!empty($data['geo_instrumen_id'])) {
            $milik = GeoInstrumen::where('id', $data['geo_instrumen_id'])
                ->where('geo_lereng_id', $data['geo_lereng_id'])->exists();

            if (!$milik) {
                return back()->withErrors(['geo_instrumen_id' => 'Alat itu tidak terpasang pada lereng yang dipilih.']);
            }

            $ada = GeoBacaan::where('geo_instrumen_id', $data['geo_instrumen_id'])
                ->whereDate('tanggal', $data['tanggal'])->exists();

            if ($ada) {
                return back()->withErrors([
                    'tanggal' => 'Pembacaan alat ini pada tanggal tersebut sudah ada. Ubah yang sudah tersimpan.',
                ]);
            }
        }

        // Gejala yang diuraikan tetapi tidak ditandai tidak akan pernah
        // memicu peringatan; ditandai di sini alih-alih diam-diam hilang.
        if (!empty($data['gejala'])) $data['ada_gejala'] = true;

        $data['user_id'] = auth()->id();
        $bacaan = GeoBacaan::create($data);

        ActivityLog::write('Catat pembacaan lereng',
            $bacaan->lereng?->kode.' · '.$bacaan->tanggal->format('Y-m-d'), 'geoteknik');

        return back()->with('ok', $bacaan->ada_gejala
            ? 'Pembacaan tersimpan sebagai draf. Gejala yang ditandai sudah langsung masuk ke peringatan.'
            : 'Pembacaan tersimpan sebagai draf.');
    }

    public function hapusBacaan(GeoBacaan $bacaan)
    {
        if ($bacaan->sudahDisetujui()) {
            return back()->withErrors(['alur' => 'Pembacaan yang sudah disetujui tidak dapat dihapus.']);
        }

        ActivityLog::write('Hapus pembacaan lereng', $bacaan->tanggal->format('Y-m-d'), 'geoteknik');
        $bacaan->delete();

        return back()->with('ok', 'Pembacaan dihapus.');
    }

    /* ---------- alur tinjauan ---------- */

    public function ajukan(GeoBacaan $bacaan)  { return $this->jalankan($bacaan, fn () => $bacaan->ajukan(), 'Ajukan pembacaan lereng', 'Pembacaan diajukan untuk ditinjau.'); }
    public function setujui(GeoBacaan $bacaan) { return $this->jalankan($bacaan, fn () => $bacaan->setujui(), 'Setujui pembacaan lereng', 'Pembacaan disetujui dan kini ikut dihitung.'); }

    public function tolak(Request $request, GeoBacaan $bacaan)
    {
        $alasan = $request->validate([
            'alasan_tolak' => ['required', 'string', 'min:5', 'max:1000'],
        ])['alasan_tolak'];

        return $this->jalankan($bacaan, fn () => $bacaan->tolak($alasan),
            'Tolak pembacaan lereng', 'Pembacaan ditolak dan dikembalikan kepada pengaju.');
    }

    private function jalankan(GeoBacaan $b, callable $aksi, string $peristiwa, string $pesan)
    {
        try {
            $aksi();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['alur' => $e->getMessage()]);
        }

        ActivityLog::write($peristiwa, $b->lereng?->kode.' · '.$b->tanggal?->format('Y-m-d'), 'geoteknik');

        return back()->with('ok', $pesan);
    }

    /* ---------- tindak lanjut ---------- */

    public function simpanTindakLanjut(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id'       => ['nullable', 'exists:companies,id'],
            'kode_pemicu'      => ['nullable', 'string', 'max:60'],
            'judul'            => ['required', 'string', 'max:200'],
            'prioritas'        => ['required', Rule::in(TindakLanjut::PRIORITAS)],
            'penanggung_jawab' => ['nullable', 'string', 'max:150'],
            'target_selesai'   => ['nullable', 'date'],
            'uraian'           => ['nullable', 'string', 'max:3000'],
        ]));

        $data['modul'] = 'geoteknik';
        $data['user_id'] = auth()->id();

        TindakLanjut::create($data);
        ActivityLog::write('Tambah tindak lanjut geoteknik', $data['judul'], 'geoteknik');

        return back()->with('ok', 'Tindak lanjut ditambahkan.');
    }

    public function ubahTindakLanjut(Request $request, TindakLanjut $tindak)
    {
        $status = $request->validate([
            'status' => ['required', Rule::in(TindakLanjut::STATUS)],
        ])['status'];

        $tindak->update([
            'status' => $status,
            'selesai_pada' => $status === 'selesai' ? now()->toDateString() : null,
        ]);

        return back()->with('ok', 'Status tindak lanjut diperbarui.');
    }

    /* ---------- laporan ---------- */

    public function cetak(Request $request)
    {
        [$dari, $sampai] = $this->rentang($request);

        $lerengs = GeoLereng::with(['instrumen', 'bacaan'])->where('status', 'aktif')->orderBy('kode')->get();
        $semua = GeoBacaan::with(['lereng', 'instrumen'])
            ->whereBetween('tanggal', [$dari, $sampai])->orderBy('tanggal')->get();
        $sah = $semua->whereIn('status', Alur::terhitung());

        $perusahaan = auth()->user()?->company ?: Company::first();

        return Inertia::render('Print/Geoteknik', [
            'dok'    => KopDokumen::untuk('laporan-geoteknik', $perusahaan),
            'dari'   => $dari->toDateString(),
            'sampai' => $sampai->toDateString(),

            'dasar' => [
                'lereng'        => $lerengs->count(),
                'bacaan'        => $semua->count(),
                'disetujui'     => $sah->count(),
                'belumDitinjau' => $semua->count() - $sah->count(),
            ],

            'ringkas' => $this->ringkas($lerengs, $sah),
            'lereng'  => $lerengs->map(fn (GeoLereng $l) => $l->toView($sah->where('geo_lereng_id', $l->id)))->values(),
            'bacaan'  => $sah->map(fn (GeoBacaan $b) => $b->toView())->values(),
            'tindak'  => TindakLanjut::modul('geoteknik')->terbukaSaja()->urutMendesak()->get()
                ->map(fn (TindakLanjut $t) => $t->toView())->values(),

            'kembali' => route('geoteknik.index', ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()]),
        ]);
    }

    /* ---------- halaman ---------- */

    private function halaman(Request $request, string $mode)
    {
        [$dari, $sampai] = $this->rentang($request);

        $lerengs = GeoLereng::with(['instrumen', 'bacaan'])->orderBy('kode')->get();
        $aktif = $lerengs->where('status', 'aktif');

        $semua = GeoBacaan::with(['lereng', 'instrumen'])
            ->whereBetween('tanggal', [$dari, $sampai])->orderByDesc('tanggal')->get();
        $sah = $semua->whereIn('status', Alur::terhitung());

        // Satu alat satu pembacaan per hari; kelengkapannya diukur
        // dengan alat yang sama seperti shift produksi, dengan "shift"
        // berupa daftar alat yang sedang memantau.
        $alat = $aktif->flatMap(fn (GeoLereng $l) => $l->instrumen->where('status', 'siap')->pluck('id'))
            ->map(fn ($i) => (string) $i)->values()->all();

        $kelengkapan = (new KelengkapanShift(
            $semua->whereNotNull('geo_instrumen_id')->map(fn (GeoBacaan $b) => (object) [
                'tanggal' => $b->tanggal, 'shift' => (string) $b->geo_instrumen_id, 'status' => $b->status,
            ]),
            $dari->copy(), $sampai->copy(),
            $alat ?: ['0'],
        ))->toArray();

        $tindak = TindakLanjut::with('sumber')->modul('geoteknik')->urutMendesak()->get();

        return Inertia::render('Geoteknik/Halaman', [
            'mode'   => $mode,
            'dari'   => $dari->toDateString(),
            'sampai' => $sampai->toDateString(),

            'ringkas'     => $this->ringkas($aktif, $sah),
            'kelengkapan' => $kelengkapan,

            // Peringatan menerima seluruh bacaan, bukan hanya yang sah:
            // gejala lapangan sengaja tidak menunggu persetujuan.
            'alerts' => PeringatanGeoteknik::susun($aktif, $semua, $kelengkapan),

            'lereng' => $lerengs->map(fn (GeoLereng $l) => $l->toView($sah->where('geo_lereng_id', $l->id)) + [
                'instrumenDaftar' => $l->instrumen->map(fn (GeoInstrumen $i) => $i->toView())->values(),
            ])->values(),

            'bacaan' => $semua->map(fn (GeoBacaan $b) => $b->toView())->values(),
            'tindak' => $tindak->map(fn (TindakLanjut $t) => $t->toView())->values(),
            'kodeDitangani' => $tindak->filter(fn (TindakLanjut $t) => $t->terbuka())
                ->pluck('kode_pemicu')->filter()->unique()->values(),

            'companies' => Company::query()
                ->when(!auth()->user()?->isAdmin(), fn ($q) => $q->whereKey(auth()->user()?->company_id))
                ->orderBy('name')->get(['id', 'name']),

            'opsi' => [
                'jenis'          => GeoLereng::JENIS,
                'statusLereng'   => GeoLereng::STATUS,
                'jenisInstrumen' => GeoInstrumen::JENIS,
                'statusInstrumen'=> GeoInstrumen::STATUS,
                'statusTindak'   => TindakLanjut::STATUS,
                'prioritasTindak'=> TindakLanjut::PRIORITAS,
                'ambangBawaan'   => [
                    'waspada' => Kestabilan::AMBANG_WASPADA,
                    'siaga'   => Kestabilan::AMBANG_SIAGA,
                    'awas'    => Kestabilan::AMBANG_AWAS,
                ],
            ],

            'tautan' => [
                'dashboard' => route('geoteknik.index'),
                'bacaan'    => route('geoteknik.bacaan'),
                'lereng'    => route('geoteknik.lereng'),
                'cetak'     => route('geoteknik.cetak'),
                'lerengSimpan'    => route('geoteknik.lereng.simpan'),
                'lerengUbah'      => route('geoteknik.lereng.ubah', ['lereng' => '__ID__']),
                'lerengHapus'     => route('geoteknik.lereng.hapus', ['lereng' => '__ID__']),
                'instrumenSimpan' => route('geoteknik.instrumen.simpan', ['lereng' => '__ID__']),
                'instrumenUbah'   => route('geoteknik.instrumen.ubah', ['instrumen' => '__ID__']),
                'bacaanSimpan'    => route('geoteknik.bacaan.simpan'),
                'bacaanHapus'     => route('geoteknik.bacaan.hapus', ['bacaan' => '__ID__']),
                'ajukan'          => route('geoteknik.ajukan', ['bacaan' => '__ID__']),
                'setujui'         => route('geoteknik.setujui', ['bacaan' => '__ID__']),
                'tolak'           => route('geoteknik.tolak', ['bacaan' => '__ID__']),
                'tindakSimpan'    => route('geoteknik.tindak.simpan'),
                'tindakUbah'      => route('geoteknik.tindak.ubah', ['tindak' => '__ID__']),
            ],
        ]);
    }

    /**
     * @param Collection<int,GeoLereng> $lerengs
     * @param Collection<int,GeoBacaan> $sah
     */
    private function ringkas(Collection $lerengs, Collection $sah): array
    {
        $tingkat = ['awas' => 0, 'siaga' => 0, 'waspada' => 0, 'normal' => 0, 'tanpa-data' => 0];
        $lajuTertinggi = null;
        $ttfTerdekat = null;

        foreach ($lerengs as $l) {
            $g = $l->gerakan($sah->where('geo_lereng_id', $l->id));
            $tingkat[$g['tingkat']] = ($tingkat[$g['tingkat']] ?? 0) + 1;

            if ($g['laju'] !== null && ($lajuTertinggi === null || $g['laju'] > $lajuTertinggi)) {
                $lajuTertinggi = $g['laju'];
            }

            $t = $g['ttf'];
            if ($t['dapatDipakai'] && $t['hari'] !== null
                && ($ttfTerdekat === null || $t['hari'] < $ttfTerdekat)) {
                $ttfTerdekat = $t['hari'];
            }
        }

        return [
            'lereng'          => $lerengs->count(),
            'tingkat'         => $tingkat,
            'lajuTertinggi'   => $lajuTertinggi,
            'ttfTerdekat'     => $ttfTerdekat,
            'bacaan'          => $sah->count(),
            'gejala'          => $sah->where('ada_gejala', true)->count(),
            'instrumenRusak'  => (int) $lerengs->sum(fn (GeoLereng $l) => $l->instrumenRusak()),
            'penyimpangan'    => (int) $lerengs->sum(fn (GeoLereng $l) => count($l->penyimpanganGeometri())),
        ];
    }

    /** @return array{0:Carbon,1:Carbon} */
    private function rentang(Request $request): array
    {
        $dari = $request->date('dari') ?: now()->startOfMonth();
        $sampai = $request->date('sampai') ?: now()->endOfMonth();

        return $dari->greaterThan($sampai) ? [$sampai, $dari] : [$dari, $sampai];
    }

}
