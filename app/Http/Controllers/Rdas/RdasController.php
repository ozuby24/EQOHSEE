<?php

namespace App\Http\Controllers\Rdas;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Energi\BantuEnergi;
use App\Models\ActivityLog;
use App\Models\Rdas\{Blok, Kegiatan, Kejadian, Laporan, Pantau, Penilaian, Petak, Ppkh};
use App\Support\Perusahaan;
use App\Support\Rdas\{Katalog, Kepatuhan};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Rehabilitasi DAS — kewajiban penanaman pemegang PPKH dari penetapan
 * lokasi sampai serah terima tanaman.
 *
 * Alurnya mengikuti lima langkah BPDAS: pengajuan & penetapan lokasi →
 * Rencana Kegiatan Penanaman → penanaman & pemeliharaan (P0, P1, P2) →
 * penilaian keberhasilan → serah terima. Petak Ukur menentukan persen
 * tumbuh; usia tanaman, geotag, dan gangguan menentukan apakah blok boleh
 * diajukan untuk dinilai.
 */
class RdasController extends Controller
{
    use BantuEnergi;

    /* ═══════════ dasbor ═══════════ */

    public function index(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $per = fn ($q) => $q->when($c, fn ($q) => $q->where('company_id', $c));
        $ppkh = $per(Ppkh::query())->orderBy('kode')->get();
        $blok = $this->blokSinkron($per(Blok::query())->with(['petak', 'penilaian', 'ppkh'])->get());
        $petak = $blok->flatMap->petak;
        $kini = now();

        $ringkas = $ppkh->map(fn (Ppkh $p) => ['id' => $p->id, 'kode' => $p->kode, 'nomor_sk' => $p->nomor_sk] + Kepatuhan::ringkasPpkh($p, $blok->where('ppkh_id', $p->id)));
        $kew = $ringkas->sum('kewajiban');
        $tumbuh = Kepatuhan::rataPetak($petak, 'persen_tumbuh');

        // Luas per fase.
        $fase = collect(Katalog::FASE)->map(fn ($f, $k) => ['kode' => $k, 'label' => $f['pendek'], 'warna' => $f['warna'],
            'luas' => round($blok->where('fase', $k)->sum('luas_ha'), 1), 'blok' => $blok->where('fase', $k)->count()])->values()->all();

        // Tren persen tumbuh per kuartal (rata-rata tertimbang jumlah diamati).
        $pantau = $per(Pantau::query())->where('tanggal', '>=', $kini->copy()->subMonths(35)->startOfQuarter())->get();
        $tren = [];
        for ($i = 11; $i >= 0; $i--) {
            $awal = $kini->copy()->subQuarters($i)->startOfQuarter();
            $m = $pantau->filter(fn ($x) => $x->tanggal->between($awal, $awal->copy()->endOfQuarter()));
            $n = $m->sum('diamati');
            $tren[] = ['label' => 'K'.$awal->quarter.' '.$awal->format('y'), 'tumbuh' => $n ? round($m->sum(fn ($x) => $x->persen_tumbuh * $x->diamati) / $n, 1) : null,
                'pu' => $m->count(), 'sulam' => $m->where('status', 'sulam')->count()];
        }

        // Kegiatan 12 bulan: HOK, bibit, biaya per fase.
        $kegiatan = $per(Kegiatan::query())->where('tanggal', '>=', $kini->copy()->subMonths(11)->startOfMonth())->where('status', '!=', 'ditolak')->get();
        $bulan = [];
        for ($i = 11; $i >= 0; $i--) {
            $b = $kini->copy()->subMonths($i)->startOfMonth();
            $m = $kegiatan->filter(fn ($x) => $x->tanggal->isSameMonth($b));
            $bulan[] = ['label' => $b->translatedFormat('M y'), 'p0' => (int) $m->where('fase', 'P0')->sum('hok'), 'p1' => (int) $m->where('fase', 'P1')->sum('hok'),
                'p2' => (int) $m->where('fase', 'P2')->sum('hok'), 'bibit' => (int) $m->sum('bibit'), 'biaya' => round($m->sum('biaya_rp') / 1e6, 1)];
        }

        // Histogram persen tumbuh petak.
        $kelas = [[0, 60, '< 60%'], [60, 75, '60–75%'], [75, 80, '75–80%'], [80, 90, '80–90%'], [90, 101, '≥ 90%']];
        $histogram = collect($kelas)->map(fn ($k) => ['label' => $k[2], 'petak' => $petak->filter(fn ($p) => $p->persen_tumbuh !== null && $p->persen_tumbuh >= $k[0] && $p->persen_tumbuh < $k[1])->count(),
            'luas' => round($petak->filter(fn ($p) => $p->persen_tumbuh !== null && $p->persen_tumbuh >= $k[0] && $p->persen_tumbuh < $k[1])->sum('luas_ha'), 1)])->all();

        // Komposisi jenis tertimbang luas.
        $komposisi = [];
        foreach ($blok as $b) {
            foreach ($b->komposisi ?? [] as $k) {
                $komposisi[$k['jenis']] = ($komposisi[$k['jenis']] ?? 0) + $b->luas_ha * ($k['persen'] ?? 0) / 100 * $b->kerapatan;
            }
        }
        arsort($komposisi);

        // Biaya vs fisik per blok (biaya terverifikasi vs anggaran).
        $biaya = $per(Kegiatan::query())->where('status', 'diverifikasi')->selectRaw('blok_id, sum(biaya_rp) b')->groupBy('blok_id')->pluck('b', 'blok_id');
        $serapan = $blok->filter(fn ($b) => $b->anggaran_rp > 0)->sortBy('kode')->map(fn (Blok $b) => [
            'kode' => $b->kode, 'nama' => $b->nama, 'id' => $b->id,
            'serapan' => round(($biaya[$b->id] ?? 0) / $b->anggaran_rp * 100, 1),
            'fisik' => $this->fisik($b),
        ])->values()->all();

        $laporan = $per(Laporan::query())->with('ppkh:id,kode')->get();
        $kejadian = $per(Kejadian::query())->where('status', '!=', 'selesai')->with(['ppkh:id,kode', 'blok:id,kode'])->orderByDesc('tanggal')->get();

        return Inertia::render('Rdas/Dasbor', [
            'ringkas' => [
                'kewajiban' => round($kew, 1), 'ditetapkan' => round($ringkas->sum('ditetapkan'), 1), 'ditanam' => round($ringkas->sum('ditanam'), 1),
                'bast' => round($ringkas->sum('bast'), 1),
                'ditetapkan_pct' => $kew ? round($ringkas->sum('ditetapkan') / $kew * 100, 1) : null,
                'ditanam_pct' => $kew ? round($ringkas->sum('ditanam') / $kew * 100, 1) : null,
                'bast_pct' => $kew ? round($ringkas->sum('bast') / $kew * 100, 1) : null,
                'ppkh' => $ppkh->count(), 'blok' => $blok->count(), 'petak' => $petak->count(),
                'tumbuh' => $tumbuh, 'sehat_ha' => Kepatuhan::rataPetak($petak, 'sehat_ha'),
                'petak_sulam' => $petak->where('status_tumbuh', 'sulam')->count(),
                'luas_sulam' => round($petak->where('status_tumbuh', 'sulam')->sum('luas_ha'), 1),
                'siap_nilai' => $blok->where('fase', 'siap_nilai')->count(),
                'penilaian' => $blok->where('fase', 'penilaian')->count(),
                'batang' => (int) $petak->sum('bibit_tanam'),
                'laporan_terlambat' => $laporan->filter(fn ($l) => ! $l->dikirim_at && $l->keadaan() === 'terlambat')->count(),
                'laporan_tempo' => $laporan->filter(fn ($l) => $l->keadaan() === 'jatuh_tempo')->count(),
                'kejadian' => $kejadian->count(),
                'batas_terdekat' => $ringkas->sortBy('sisa_hari')->first(),
            ],
            'perPpkh' => $ringkas->values()->all(), 'fase' => $fase, 'tren' => $tren, 'bulan' => $bulan, 'histogram' => $histogram,
            'komposisi' => collect($komposisi)->take(8)->map(fn ($v, $k) => ['jenis' => $k, 'label' => Katalog::JENIS[$k]['nama'] ?? $k,
                'kategori' => Katalog::JENIS[$k]['kategori'] ?? 'lokal', 'batang' => (int) round($v)])->values()->all(),
            'serapan' => $serapan,
            'peringatan' => $this->peringatan($blok, $ringkas, $laporan, $kejadian),
            'peta' => $this->fitur($blok),
            'perusahaan' => $c, 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    /* ═══════════ peta ═══════════ */

    public function peta(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $blok = $this->blokSinkron(Blok::query()->when($c, fn ($q) => $q->where('company_id', $c))->with(['petak', 'penilaian', 'ppkh'])->get());

        return Inertia::render('Rdas/Peta', [
            'fitur' => $this->fitur($blok),
            'blok' => $blok->sortBy('kode')->map(fn (Blok $b) => $this->ringkasBlok($b))->values()->all(),
            'pilihan' => Katalog::pilihan(),
            'perusahaan' => $c, 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    public function geojson(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $blok = $this->blokSinkron(Blok::query()->when($c, fn ($q) => $q->where('company_id', $c))->with(['petak', 'penilaian', 'ppkh'])->get());
        $fitur = [];
        foreach ($blok as $b) {
            foreach ($b->petak as $p) {
                $geom = $p->poligon
                    ? ['type' => 'Polygon', 'coordinates' => [array_map(fn ($t) => [$t[1], $t[0]], array_merge($p->poligon, [$p->poligon[0]]))]]
                    : ['type' => 'Point', 'coordinates' => [$p->lng, $p->lat]];
                $fitur[] = ['type' => 'Feature', 'geometry' => $geom, 'properties' => [
                    'petak' => $p->kode, 'blok' => $b->kode, 'nama_blok' => $b->nama, 'ppkh' => $b->ppkh?->kode, 'das' => $b->das, 'sub_das' => $b->sub_das,
                    'fungsi' => $b->fungsi_kawasan, 'luas_ha' => $p->luas_ha, 'fase' => $b->fase, 'tanggal_tanam' => $b->tanggal_tanam?->toDateString(),
                    'persen_tumbuh' => $p->persen_tumbuh, 'sehat_ha' => $p->sehat_ha, 'status' => $p->status_tumbuh]];
            }
        }

        return response()->json(['type' => 'FeatureCollection', 'features' => $fitur], 200, [
            'Content-Disposition' => 'attachment; filename="rehab-das-'.now()->format('Ymd').'.geojson"',
        ]);
    }

    /* ═══════════ kewajiban PPKH ═══════════ */

    public function ppkh(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $ppkh = Ppkh::query()->when($c, fn ($q) => $q->where('company_id', $c))->orderBy('kode')->get();
        $blok = $this->blokSinkron(Blok::query()->when($c, fn ($q) => $q->where('company_id', $c))->with(['petak', 'penilaian'])->get());

        return Inertia::render('Rdas/Kewajiban', [
            'ppkh' => $ppkh->map(fn (Ppkh $p) => $this->ringkasPpkh($p) + Kepatuhan::ringkasPpkh($p, $blok->where('ppkh_id', $p->id))
                + ['blok' => $blok->where('ppkh_id', $p->id)->count(),
                   'fase' => collect(Katalog::FASE)->map(fn ($f, $k) => round($blok->where('ppkh_id', $p->id)->where('fase', $k)->sum('luas_ha'), 1))->all()])->values()->all(),
            'pilihan' => Katalog::pilihan(),
            'perusahaan' => $c, 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    public function simpanPpkh(Request $request)
    {
        $data = $this->pemilik($request->validate($this->aturanPpkh()));
        $u = $request->user();
        if ($u->isAdmin() && empty($data['company_id'])) $data['company_id'] = Perusahaan::terpilih($u) ?? $u->company_id;
        $p = Ppkh::create($this->isiPpkh($data) + ['user_id' => $u->id, 'kode' => $this->kodePpkh($data['company_id'] ?? null)]);
        $this->jadwalLaporan($p);
        ActivityLog::write('Daftarkan PPKH Rehab DAS', $p->kode.' — '.$p->nomor_sk, 'rdas');

        return redirect()->route('rdas.ppkh.rincian', $p)->with('ok', $p->kode.' terdaftar: kewajiban '.number_format($p->kewajiban_ha, 2, ',', '.').' ha, batas penyelesaian '.$p->batas_selesai->translatedFormat('d M Y').'.');
    }

    public function perbaruiPpkh(Request $request, Ppkh $ppkh)
    {
        $data = $request->validate($this->aturanPpkh());
        unset($data['company_id']);
        $ppkh->update($this->isiPpkh($data));
        $this->jadwalLaporan($ppkh);

        return back()->with('ok', 'Data PPKH diperbarui; kewajiban dan batas waktu dihitung ulang.');
    }

    public function rincianPpkh(Ppkh $ppkh)
    {
        $blok = $this->blokSinkron(Blok::where('ppkh_id', $ppkh->id)->with(['petak', 'penilaian', 'ppkh'])->orderBy('kode')->get());

        return Inertia::render('Rdas/Ppkh', [
            'ppkh' => $this->ringkasPpkh($ppkh) + Kepatuhan::ringkasPpkh($ppkh, $blok) + $ppkh->only(['tanggal_sk', 'jangka_tahun', 'tujuan', 'luas_ppkh_ha', 'nomor_pak',
                'luas_pak_ha', 'rasio', 'catatan', 'rurh_status', 'luas_terganggu_ha', 'luas_direklamasi_ha', 'nilai_reklamasi', 'status_lokasi'])
                + ['tanggal_sk' => $ppkh->tanggal_sk->toDateString(), 'berlaku_sampai' => $ppkh->berlaku_sampai->toDateString(), 'predikat' => $ppkh->predikatReklamasi()],
            'blok' => $blok->map(fn (Blok $b) => $this->ringkasBlok($b) + ['layak' => Kepatuhan::kelayakan($b)['layak']])->values()->all(),
            'laporan' => Laporan::where('ppkh_id', $ppkh->id)->orderByDesc('tahun')->orderByDesc('semester')->get()
                ->map(fn (Laporan $l) => ['id' => $l->id, 'tahun' => $l->tahun, 'semester' => $l->semester, 'batas' => $l->batas->toDateString(),
                    'dikirim_at' => $l->dikirim_at?->toDateString(), 'tanda_terima' => $l->tanda_terima, 'keadaan' => $l->keadaan()])->all(),
            'kejadian' => Kejadian::where('ppkh_id', $ppkh->id)->with('blok:id,kode')->orderByDesc('tanggal')->orderByDesc('id')->get()
                ->map(fn (Kejadian $k) => $this->ringkasKejadian($k))->all(),
            'fitur' => $this->fitur($blok),
            'pilihan' => Katalog::pilihan(),
        ]);
    }

    public function reklamasi(Request $request, Ppkh $ppkh)
    {
        $data = $request->validate([
            'rurh_status' => ['required', Rule::in(array_keys(Katalog::RURH))],
            'luas_terganggu_ha' => ['nullable', 'numeric', 'min:0'], 'luas_direklamasi_ha' => ['nullable', 'numeric', 'min:0'],
            'nilai_reklamasi' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $ppkh->update($data);

        return back()->with('ok', 'Status reklamasi hutan diperbarui.');
    }

    /* ═══════════ blok ═══════════ */

    public function baruBlok(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $ppkh = Ppkh::query()->when($c, fn ($q) => $q->where('company_id', $c))->orderBy('kode')->get(['id', 'kode', 'nomor_sk', 'bpdas', 'kabupaten', 'kewajiban_ha']);
        $pilih = $request->integer('ppkh') ?: $ppkh->first()?->id;

        return Inertia::render('Rdas/FormBlok', [
            'blok' => ['ppkh_id' => $pilih, 'nama' => '', 'das' => '', 'sub_das' => '', 'fungsi_kawasan' => 'HL', 'ekosistem' => 'daratan', 'luas_ha' => 100,
                'jarak_tanam' => '4 × 4 m', 'kerapatan' => Katalog::KERAPATAN, 'anggaran_rp' => 0,
                'komposisi' => [['jenis' => 'meranti', 'persen' => 30], ['jenis' => 'kapur', 'persen' => 15], ['jenis' => 'sengon', 'persen' => 25], ['jenis' => 'durian', 'persen' => 15], ['jenis' => 'petai', 'persen' => 15]]],
            'ppkh' => $ppkh, 'pilihan' => Katalog::pilihan(),
        ]);
    }

    public function simpanBlok(Request $request)
    {
        $data = $request->validate($this->aturanBlok());
        $ppkh = Ppkh::findOrFail($data['ppkh_id']);
        $b = Blok::create($data + ['company_id' => $ppkh->company_id, 'kode' => $this->kodeBlok($ppkh)]);
        Kepatuhan::sinkronFase($b);
        if (filled($b->sk_penlok_nomor) && $ppkh->status_lokasi !== 'ditetapkan') $ppkh->update(['status_lokasi' => 'ditetapkan']);
        ActivityLog::write('Daftarkan blok Rehab DAS', $b->kode.' — '.$b->nama, 'rdas');

        return redirect()->route('rdas.blok.rincian', $b)->with('ok', 'Blok '.$b->kode.' tersimpan. Tambahkan petak tanam beserta petanya.');
    }

    public function ubahBlok(Blok $blok)
    {
        $ppkh = Ppkh::where('company_id', $blok->company_id)->orderBy('kode')->get(['id', 'kode', 'nomor_sk', 'bpdas', 'kabupaten', 'kewajiban_ha']);

        return Inertia::render('Rdas/FormBlok', [
            'blok' => $blok->only(['id', 'ppkh_id', 'nama', 'sk_penlok_nomor', 'das', 'sub_das', 'kabupaten', 'kecamatan', 'desa', 'fungsi_kawasan', 'pemangku',
                'ekosistem', 'luas_ha', 'jarak_tanam', 'kerapatan', 'komposisi', 'rkp_nomor', 'kontraktor', 'anggaran_rp', 'lat', 'lng', 'catatan'])
                + ['sk_penlok_tanggal' => $blok->sk_penlok_tanggal?->toDateString(), 'rkp_disahkan_at' => $blok->rkp_disahkan_at?->toDateString(),
                   'tanggal_tanam' => $blok->tanggal_tanam?->toDateString(), 'kode' => $blok->kode],
            'ppkh' => $ppkh, 'pilihan' => Katalog::pilihan(),
        ]);
    }

    public function perbaruiBlok(Request $request, Blok $blok)
    {
        abort_if($blok->fase === 'serah_terima', 403, 'Blok yang sudah diserahterimakan tidak dapat diubah.');
        $data = $request->validate($this->aturanBlok());
        unset($data['ppkh_id']);
        $blok->update($data);
        Kepatuhan::sinkronFase($blok->fresh());

        return redirect()->route('rdas.blok.rincian', $blok)->with('ok', 'Blok diperbarui; fase dihitung ulang dari tanggal tanam.');
    }

    public function rincianBlok(Blok $blok)
    {
        $blok->load(['petak', 'penilaian', 'ppkh']);
        Kepatuhan::sinkronFase($blok);
        $pantau = Pantau::where('blok_id', $blok->id)->with('petak:id,kode')->orderBy('tanggal')->get();
        $kegiatan = Kegiatan::where('blok_id', $blok->id)->with('petak:id,kode')->orderByDesc('tanggal')->orderByDesc('id')->get();

        // Tren per periode pemantauan: rata-rata tertimbang diamati.
        $tren = $pantau->groupBy(fn ($p) => $p->tanggal->format('Y-m'))->map(fn ($g, $k) => ['label' => Carbon::createFromFormat('Y-m', $k)->translatedFormat('M y'),
            'tumbuh' => round($g->sum(fn ($x) => $x->persen_tumbuh * $x->diamati) / max(1, $g->sum('diamati')), 1),
            'sehat_ha' => round($g->avg('sehat_ha')), 'tinggi' => $g->whereNotNull('tinggi_cm')->count() ? round($g->avg('tinggi_cm')) : null])->values()->all();

        return Inertia::render('Rdas/Blok', [
            'blok' => $this->ringkasBlok($blok) + $blok->only(['sk_penlok_nomor', 'kecamatan', 'desa', 'pemangku', 'jarak_tanam', 'kerapatan', 'rkp_nomor',
                'kontraktor', 'anggaran_rp', 'geotag_total', 'geotag_duplikat', 'catatan', 'ekosistem'])
                + ['sk_penlok_tanggal' => $blok->sk_penlok_tanggal?->toDateString(), 'rkp_disahkan_at' => $blok->rkp_disahkan_at?->toDateString(),
                   'ppkh_kode' => $blok->ppkh->kode, 'ppkh_id' => $blok->ppkh_id, 'bpdas' => $blok->ppkh->bpdas, 'komposisi' => $blok->komposisi ?? [],
                   'komposisi_cek' => Kepatuhan::komposisi($blok->komposisi), 'target_batang' => (int) round($blok->luas_ha * $blok->kerapatan),
                   'fisik' => $this->fisik($blok)],
            'kelayakan' => Kepatuhan::kelayakan($blok),
            'petak' => $blok->petak->sortBy('kode')->map(fn (Petak $p) => $p->only(['id', 'kode', 'luas_ha', 'kelerengan', 'lat', 'lng', 'bibit_tanam', 'bibit_sulam',
                'persen_tumbuh', 'sehat_ha', 'status_tumbuh']) + ['dipantau_at' => $p->dipantau_at?->toDateString(), 'berpeta' => ! empty($p->poligon) || $p->lat !== null])->values()->all(),
            'pantau' => $pantau->sortByDesc('tanggal')->take(40)->map(fn (Pantau $p) => $p->only(['id', 'kode', 'periode', 'jumlah_pu', 'diamati', 'sehat', 'merana', 'mati',
                'persen_tumbuh', 'sehat_ha', 'tinggi_cm', 'status', 'kebutuhan_sulam', 'pengamat']) + ['tanggal' => $p->tanggal->toDateString(), 'petak' => $p->petak?->kode])->values()->all(),
            'tren' => $tren,
            'kegiatan' => $kegiatan->take(60)->map(fn (Kegiatan $k) => $this->ringkasKegiatan($k))->values()->all(),
            'rekapKegiatan' => collect(Katalog::KEGIATAN)->map(fn ($v, $k) => ['jenis' => $k, 'label' => $v['label'],
                'luas' => round($kegiatan->where('jenis', $k)->where('status', 'diverifikasi')->sum('luas_ha'), 1),
                'hok' => (int) $kegiatan->where('jenis', $k)->where('status', 'diverifikasi')->sum('hok'),
                'biaya' => (float) $kegiatan->where('jenis', $k)->where('status', 'diverifikasi')->sum('biaya_rp')])->filter(fn ($r) => $r['hok'] || $r['biaya'])->values()->all(),
            'penilaian' => $blok->penilaian->sortBy('id')->map(fn (Penilaian $p) => $this->ringkasPenilaian($p))->values()->all(),
            'luasBast' => round($blok->penilaian->where('status', 'serah_terima')->sum('luas_berhasil_ha'), 2),
            'fitur' => $this->fitur(collect([$blok])),
            'pilihan' => Katalog::pilihan(),
            'pengguna' => auth()->user()->name,
        ]);
    }

    public function geotag(Request $request, Blok $blok)
    {
        $data = $request->validate(['geotag_total' => ['required', 'integer', 'min:0'], 'geotag_duplikat' => ['required', 'integer', 'min:0', 'lte:geotag_total']]);
        $blok->update($data);

        return back()->with('ok', 'Data geotagging diperbarui.');
    }

    /* ═══════════ petak ═══════════ */

    public function simpanPetak(Request $request, Blok $blok)
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:40', Rule::unique('rdas_petak', 'kode')->where('blok_id', $blok->id)],
            'luas_ha' => ['required', 'numeric', 'min:0.01', 'max:5000'],
            'kelerengan' => ['nullable', Rule::in(array_keys(Katalog::KELERENGAN))],
            'lat' => ['nullable', 'numeric', 'between:-90,90'], 'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'poligon' => ['nullable', 'string', 'max:20000'],
            'bibit_tanam' => ['nullable', 'integer', 'min:0'],
        ]);
        $poligon = $this->bacaPoligon($data['poligon'] ?? null);
        if (filled($data['poligon'] ?? null) && ! $poligon) {
            return back()->withErrors(['poligon' => 'Poligon tidak terbaca. Tempel GeoJSON Polygon atau daftar "lat, lng" per baris (minimal 3 titik).']);
        }
        if ($poligon && (($data['lat'] ?? null) === null || ($data['lng'] ?? null) === null)) {
            $data['lat'] = round(array_sum(array_column($poligon, 0)) / count($poligon), 7);
            $data['lng'] = round(array_sum(array_column($poligon, 1)) / count($poligon), 7);
        }
        Petak::create(array_merge($data, ['company_id' => $blok->company_id, 'blok_id' => $blok->id, 'poligon' => $poligon,
            'bibit_tanam' => (int) ($data['bibit_tanam'] ?? 0)]));

        return back()->with('ok', 'Petak '.$data['kode'].' ditambahkan.');
    }

    /* ═══════════ Petak Ukur ═══════════ */

    public function formPantau(Petak $petak)
    {
        $blok = Blok::with('ppkh')->findOrFail($petak->blok_id);
        $usia = $blok->usiaBulan();

        return Inertia::render('Rdas/Pantau', [
            'petak' => $petak->only(['id', 'kode', 'luas_ha', 'persen_tumbuh', 'sehat_ha', 'status_tumbuh']),
            'blok' => $this->ringkasBlok($blok) + ['kerapatan' => $blok->kerapatan],
            'periode' => $usia === null ? 'p0' : ($usia < 12 ? 'p0' : ($usia < 24 ? 'p1' : ($usia < 36 ? 'p2' : 'penilaian'))),
            'puMinimum' => (int) max(1, ceil($petak->luas_ha * Katalog::IS_PCT / 100 / Katalog::LUAS_PU_HA)),
            'riwayat' => Pantau::where('petak_id', $petak->id)->orderByDesc('tanggal')->take(8)->get()
                ->map(fn (Pantau $p) => $p->only(['kode', 'periode', 'persen_tumbuh', 'sehat_ha', 'status', 'tinggi_cm']) + ['tanggal' => $p->tanggal->toDateString()])->all(),
            'pilihan' => Katalog::pilihan(),
            'pengamat' => auth()->user()->name,
        ]);
    }

    public function hitungPantau(Request $request)
    {
        $d = $request->validate([
            'diamati' => ['required', 'integer', 'min:0'], 'sehat' => ['required', 'integer', 'min:0'], 'merana' => ['required', 'integer', 'min:0'],
            'kerapatan' => ['nullable', 'integer', 'min:100', 'max:5000'], 'luas_petak' => ['nullable', 'numeric', 'min:0'], 'jumlah_pu' => ['nullable', 'integer', 'min:0'],
        ]);

        return response()->json(Kepatuhan::pantau((int) $d['diamati'], (int) $d['sehat'], (int) $d['merana'], null, (int) ($d['kerapatan'] ?? Katalog::KERAPATAN),
            (float) ($d['luas_petak'] ?? 0), (int) ($d['jumlah_pu'] ?? 0)));
    }

    public function simpanPantau(Request $request, Petak $petak)
    {
        $d = $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'periode' => ['required', Rule::in(array_keys(Katalog::PERIODE))],
            'jumlah_pu' => ['required', 'integer', 'min:1', 'max:5000'],
            'diamati' => ['required', 'integer', 'min:1'], 'sehat' => ['required', 'integer', 'min:0'], 'merana' => ['required', 'integer', 'min:0'],
            'tinggi_cm' => ['nullable', 'numeric', 'min:0', 'max:5000'], 'diameter_mm' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'pengamat' => ['required', 'string', 'max:120'], 'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($d['sehat'] + $d['merana'] > $d['diamati']) {
            return back()->withErrors(['diamati' => 'Jumlah sehat + merana melebihi jumlah tanaman yang diamati.']);
        }
        $blok = Blok::findOrFail($petak->blok_id);
        $h = Kepatuhan::pantau((int) $d['diamati'], (int) $d['sehat'], (int) $d['merana'], null, $blok->kerapatan, $petak->luas_ha, (int) $d['jumlah_pu']);
        $p = Pantau::create([
            'company_id' => $petak->company_id, 'petak_id' => $petak->id, 'blok_id' => $blok->id, 'user_id' => $request->user()->id,
            'kode' => $this->kodePantau($petak->company_id, Carbon::parse($d['tanggal'])), 'luas_pu_ha' => Katalog::LUAS_PU_HA,
            'intensitas_pct' => $h['intensitas_pct'], 'mati' => $h['mati'], 'persen_tumbuh' => $h['persen_tumbuh'], 'sehat_ha' => $h['sehat_ha'],
            'hidup_ha' => $h['hidup_ha'], 'status' => $h['status'], 'kebutuhan_sulam' => $h['kebutuhan_sulam'],
        ] + $d);
        Kepatuhan::sinkronPetak($petak);
        ActivityLog::write('Petak Ukur Rehab DAS', $p->kode.' '.$petak->kode.' '.$p->persen_tumbuh.'%', 'rdas');

        return redirect()->route('rdas.blok.rincian', $blok)->with($p->status === 'sulam' ? 'galat' : 'ok',
            'Petak Ukur '.$p->kode.': persen tumbuh '.number_format($p->persen_tumbuh, 1, ',', '.').'%'
            .($p->status === 'sulam' ? ' — di bawah 75%, wajib penyulaman ±'.number_format($p->kebutuhan_sulam, 0, ',', '.').' bibit.' : '.'));
    }

    /* ═══════════ kegiatan ═══════════ */

    public function simpanKegiatan(Request $request, Blok $blok)
    {
        $d = $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'jenis' => ['required', Rule::in(array_keys(Katalog::KEGIATAN))],
            'petak_id' => ['nullable', 'integer'],
            'luas_ha' => ['nullable', 'numeric', 'min:0', 'max:5000'], 'bibit' => ['nullable', 'integer', 'min:0'], 'hok' => ['nullable', 'integer', 'min:0'],
            'biaya_rp' => ['nullable', 'numeric', 'min:0'], 'pelaksana' => ['nullable', 'string', 'max:150'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'], 'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        if (! empty($d['petak_id']) && ! Petak::where('blok_id', $blok->id)->whereKey($d['petak_id'])->exists()) {
            return back()->withErrors(['petak_id' => 'Petak tidak termasuk blok ini.']);
        }
        $fase = strtoupper(in_array($blok->fase, ['p0', 'p1', 'p2'], true) ? $blok->fase : Katalog::KEGIATAN[$d['jenis']]['fase']);
        Kegiatan::create(['company_id' => $blok->company_id, 'blok_id' => $blok->id, 'user_id' => $request->user()->id, 'fase' => $fase,
            'status' => 'diajukan', 'pelaksana' => $d['pelaksana'] ?? $blok->kontraktor] + array_filter($d, fn ($v) => $v !== null));

        return back()->with('ok', 'Kegiatan tercatat dan menunggu verifikasi petugas lingkungan.');
    }

    public function verifikasiKegiatan(Request $request, Kegiatan $kegiatan)
    {
        $d = $request->validate(['aksi' => ['required', Rule::in(['terima', 'tolak'])], 'catatan' => ['nullable', 'string', 'max:1000']]);
        abort_if($kegiatan->status !== 'diajukan', 403, 'Kegiatan ini sudah diverifikasi.');
        if ($kegiatan->user_id === $request->user()->id) {
            return back()->withErrors(['aksi' => 'Kegiatan tidak dapat diverifikasi oleh yang mencatatnya sendiri.']);
        }
        if ($d['aksi'] === 'tolak' && blank($d['catatan'] ?? null)) {
            return back()->withErrors(['catatan' => 'Tuliskan alasan penolakan.']);
        }
        $kegiatan->update(['status' => $d['aksi'] === 'terima' ? 'diverifikasi' : 'ditolak', 'verifikator' => $request->user()->name,
            'diverifikasi_at' => now(), 'catatan' => $d['catatan'] ?? $kegiatan->catatan]);

        // Bibit yang ditanam/disulam yang diverifikasi menambah catatan petaknya.
        if ($d['aksi'] === 'terima' && $kegiatan->petak_id && in_array($kegiatan->jenis, ['penanaman', 'penyulaman'], true)) {
            Petak::whereKey($kegiatan->petak_id)->increment($kegiatan->jenis === 'penanaman' ? 'bibit_tanam' : 'bibit_sulam', $kegiatan->bibit);
        }

        return back()->with('ok', 'Kegiatan '.($d['aksi'] === 'terima' ? 'diverifikasi.' : 'ditolak.'));
    }

    /* ═══════════ penilaian & serah terima ═══════════ */

    public function penilaian(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $blok = $this->blokSinkron(Blok::query()->when($c, fn ($q) => $q->where('company_id', $c))->with(['petak', 'penilaian', 'ppkh'])->orderBy('kode')->get());
        $daftar = Penilaian::query()->when($c, fn ($q) => $q->where('company_id', $c))->with('blok.ppkh')->orderByDesc('diajukan_at')->orderByDesc('id')->get();

        return Inertia::render('Rdas/Penilaian', [
            'penilaian' => $daftar->map(fn (Penilaian $p) => $this->ringkasPenilaian($p) + ['blok' => $p->blok ? $this->ringkasBlok($p->blok) : null])->values()->all(),
            'kandidat' => $blok->whereIn('fase', ['p2', 'siap_nilai'])->map(fn (Blok $b) => $this->ringkasBlok($b) + ['kelayakan' => Kepatuhan::kelayakan($b)])->values()->all(),
            'corong' => collect(Katalog::PENILAIAN)->map(fn ($l, $k) => ['kode' => $k, 'label' => $l, 'jumlah' => $daftar->where('status', $k)->count(),
                'luas' => round($daftar->where('status', $k)->sum(fn ($p) => $p->luas_berhasil_ha ?? $p->luas_diajukan_ha), 1)])->values()->all(),
            'pilihan' => Katalog::pilihan(),
            'perusahaan' => $c, 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    public function ajukanPenilaian(Request $request, Blok $blok)
    {
        $blok->load(['petak', 'penilaian']);
        $sudah = $blok->penilaian->where('status', 'serah_terima')->sum('luas_berhasil_ha');
        $d = $request->validate([
            'luas_diajukan_ha' => ['required', 'numeric', 'min:0.01', 'max:'.max(0.01, round($blok->luas_ha - $sudah, 2))],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        if ($blok->penilaian->whereIn('status', Penilaian::AKTIF)->isNotEmpty()) {
            return back()->withErrors(['luas_diajukan_ha' => 'Masih ada permohonan penilaian yang berjalan untuk blok ini.']);
        }
        $k = Kepatuhan::kelayakan($blok);
        if (! $k['layak']) {
            return back()->withErrors(['luas_diajukan_ha' => 'Belum memenuhi syarat: '.implode('; ', $k['penghenti']).'.']);
        }
        $p = Penilaian::create(['company_id' => $blok->company_id, 'blok_id' => $blok->id, 'kode' => $this->kodePenilaian($blok->company_id),
            'tahap' => $blok->penilaian->where('status', 'serah_terima')->count() + 1, 'status' => 'diajukan', 'diajukan_at' => now()->toDateString(),
            'prasyarat' => $k['cek'], 'persen_tumbuh' => $k['persen_tumbuh'], 'sehat_ha' => $k['sehat_ha']] + $d);
        Kepatuhan::sinkronFase($blok);
        ActivityLog::write('Ajukan penilaian Rehab DAS', $p->kode.' '.$blok->kode, 'rdas');

        return back()->with('ok', 'Permohonan penilaian '.$p->kode.' diajukan untuk '.number_format($p->luas_diajukan_ha, 2, ',', '.').' ha.');
    }

    public function perbaruiPenilaian(Request $request, Penilaian $penilaian)
    {
        $aksi = $request->validate(['aksi' => ['required', Rule::in(['tim', 'hasil', 'tunda', 'lanjut', 'bast'])]])['aksi'];
        $sah = ['tim' => ['diajukan', 'ditunda'], 'hasil' => ['tim_dibentuk', 'dinilai'], 'tunda' => ['diajukan', 'tim_dibentuk', 'dinilai'],
                'lanjut' => ['ditunda'], 'bast' => ['berhasil']][$aksi];
        if (! in_array($penilaian->status, $sah, true)) {
            return back()->withErrors(['aksi' => 'Permohonan berstatus "'.Katalog::PENILAIAN[$penilaian->status].'" tidak dapat diproses dengan langkah ini.']);
        }

        match ($aksi) {
            'tim' => $penilaian->update($request->validate([
                'sk_tim_nomor' => ['required', 'string', 'max:150'], 'sk_tim_at' => ['required', 'date'], 'ketua_tim' => ['required', 'string', 'max:150'],
                'jadwal_mulai' => ['nullable', 'date'], 'jadwal_selesai' => ['nullable', 'date', 'after_or_equal:jadwal_mulai'],
            ]) + ['status' => 'tim_dibentuk']),
            'hasil' => (function () use ($request, $penilaian) {
                $d = $request->validate([
                    'persen_tumbuh' => ['required', 'numeric', 'min:0', 'max:100'], 'sehat_ha' => ['required', 'numeric', 'min:0', 'max:5000'],
                    'luas_berhasil_ha' => ['required', 'numeric', 'min:0', 'max:'.$penilaian->luas_diajukan_ha], 'catatan' => ['nullable', 'string', 'max:2000'],
                ]);
                // Berhasil bila tim mendapati ≥ 75% dan ≥ 500 batang sehat/ha pada luas yang dinilai berhasil.
                $ok = $d['persen_tumbuh'] >= Katalog::TUMBUH_MIN && $d['sehat_ha'] >= Katalog::SEHAT_HA_MIN && $d['luas_berhasil_ha'] > 0;
                $penilaian->update(array_merge($d, ['status' => $ok ? 'berhasil' : 'tidak_berhasil', 'luas_berhasil_ha' => $ok ? $d['luas_berhasil_ha'] : 0]));
            })(),
            'tunda' => $penilaian->update(['status' => 'ditunda', 'catatan' => $request->validate(['catatan' => ['required', 'string', 'max:2000']])['catatan']]),
            'lanjut' => $penilaian->update(['status' => $penilaian->sk_tim_nomor ? 'tim_dibentuk' : 'diajukan']),
            'bast' => $penilaian->update($request->validate(['bast_nomor' => ['required', 'string', 'max:150'], 'bast_at' => ['required', 'date']]) + ['status' => 'serah_terima']),
        };
        $blok = Blok::findOrFail($penilaian->blok_id);
        Kepatuhan::sinkronFase($blok);
        ActivityLog::write('Penilaian Rehab DAS '.$aksi, $penilaian->kode, 'rdas');

        return back()->with($penilaian->status === 'tidak_berhasil' ? 'galat' : 'ok', match ($penilaian->status) {
            'tim_dibentuk' => 'SK tim penilai dicatat.',
            'berhasil' => 'Penilaian berhasil untuk '.number_format($penilaian->luas_berhasil_ha, 2, ',', '.').' ha — lanjutkan ke BAST.',
            'tidak_berhasil' => 'Penilaian tidak berhasil — blok kembali ke pemeliharaan lanjutan.',
            'ditunda' => 'Penilaian ditunda.',
            'serah_terima' => 'BAST '.$penilaian->bast_nomor.' tercatat; '.number_format($penilaian->luas_berhasil_ha, 2, ',', '.').' ha diserahterimakan.',
            default => 'Status penilaian diperbarui.',
        });
    }

    /* ═══════════ laporan & kejadian ═══════════ */

    public function kirimLaporan(Request $request, Laporan $laporan)
    {
        $laporan->update($request->validate(['dikirim_at' => ['required', 'date', 'before_or_equal:today'], 'tanda_terima' => ['nullable', 'string', 'max:150']]));

        return back()->with('ok', 'Laporan semester '.$laporan->semester.' tahun '.$laporan->tahun.' dicatat terkirim.');
    }

    public function simpanKejadian(Request $request, Ppkh $ppkh)
    {
        $d = $request->validate([
            'jenis' => ['required', Rule::in(array_keys(Katalog::KEJADIAN))], 'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'blok_id' => ['nullable', 'integer'], 'luas_terdampak_ha' => ['nullable', 'numeric', 'min:0'], 'uraian' => ['required', 'string', 'max:2000'],
            'strategi' => ['nullable', Rule::in(array_keys(Katalog::STRATEGI))], 'ba_nomor' => ['nullable', 'string', 'max:150'],
            'dilaporkan_at' => ['nullable', 'date'],
        ]);
        if (! empty($d['blok_id']) && ! Blok::where('ppkh_id', $ppkh->id)->whereKey($d['blok_id'])->exists()) {
            return back()->withErrors(['blok_id' => 'Blok tidak termasuk PPKH ini.']);
        }
        $sanksi = (Katalog::KEJADIAN[$d['jenis']]['tahap'] ?? 0) > 0;
        Kejadian::create(['company_id' => $ppkh->company_id, 'ppkh_id' => $ppkh->id, 'user_id' => $request->user()->id,
            'status' => $sanksi ? 'ditangani' : 'terbuka'] + array_filter($d, fn ($v) => $v !== null));
        if (! $sanksi && ! empty($d['blok_id'])) Kepatuhan::sinkronFase(Blok::find($d['blok_id']));

        return back()->with('ok', Katalog::KEJADIAN[$d['jenis']]['label'].' dicatat.'
            .($d['jenis'] === 'kebakaran' && empty($d['dilaporkan_at']) ? ' Laporkan ke BPDAS dan buat Berita Acara kejadian.' : ''));
    }

    public function perbaruiKejadian(Request $request, Kejadian $kejadian)
    {
        $d = $request->validate([
            'status' => ['required', Rule::in(['terbuka', 'ditangani', 'selesai'])], 'strategi' => ['nullable', Rule::in(array_keys(Katalog::STRATEGI))],
            'ba_nomor' => ['nullable', 'string', 'max:150'], 'dilaporkan_at' => ['nullable', 'date'],
        ]);
        if ($kejadian->jenis === 'kebakaran' && $d['status'] === 'selesai' && (empty($d['dilaporkan_at'] ?? $kejadian->dilaporkan_at) || empty($d['strategi'] ?? $kejadian->strategi))) {
            return back()->withErrors(['status' => 'Kebakaran baru dapat ditutup setelah dilaporkan ke BPDAS dan exit strategy dipilih.']);
        }
        $kejadian->update(array_filter($d, fn ($v) => $v !== null));

        return back()->with('ok', 'Kejadian diperbarui.');
    }

    public function acuan()
    {
        return Inertia::render('Rdas/Acuan', ['pilihan' => Katalog::pilihan()]);
    }

    public function csv(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $blok = $this->blokSinkron(Blok::query()->when($c, fn ($q) => $q->where('company_id', $c))->with(['petak', 'penilaian', 'ppkh'])->orderBy('kode')->get());
        $kolom = ['ppkh', 'blok', 'nama', 'das', 'sub_das', 'fungsi_kawasan', 'luas_ha', 'sk_penlok', 'rkp', 'tanggal_tanam', 'fase', 'persen_tumbuh', 'sehat_ha',
                  'geotag_unik', 'luas_bast_ha', 'anggaran_rp', 'kontraktor'];

        return response()->streamDownload(function () use ($blok, $kolom) {
            $f = fopen('php://output', 'w');
            fputcsv($f, $kolom);
            foreach ($blok as $b) {
                fputcsv($f, [$b->ppkh?->kode, $b->kode, $b->nama, $b->das, $b->sub_das, $b->fungsi_kawasan, $b->luas_ha, $b->sk_penlok_nomor, $b->rkp_nomor,
                    $b->tanggal_tanam?->toDateString(), $b->fase, Kepatuhan::rataPetak($b->petak, 'persen_tumbuh'), Kepatuhan::rataPetak($b->petak, 'sehat_ha'),
                    max(0, $b->geotag_total - $b->geotag_duplikat), $b->penilaian->where('status', 'serah_terima')->sum('luas_berhasil_ha'), $b->anggaran_rp, $b->kontraktor]);
            }
            fclose($f);
        }, 'rehab-das-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ═══════════ bantuan ═══════════ */

    /** @param Collection<int,Blok> $blok */
    private function blokSinkron(Collection $blok): Collection
    {
        foreach ($blok as $b) {
            $b->setRelation('penilaianTerakhir', $b->penilaian->sortBy('id')->last());
            Kepatuhan::sinkronFase($b);
        }

        return $blok;
    }

    /** Persen fisik: luas penanaman terverifikasi terhadap luas blok, lalu tahap pemeliharaan. */
    private function fisik(Blok $b): float
    {
        return match ($b->fase) {
            'penetapan' => 5.0, 'rkp' => 10.0, 'p0' => 40.0, 'p1' => 60.0, 'p2' => 80.0, 'siap_nilai' => 90.0, 'penilaian' => 95.0, 'serah_terima' => 100.0, default => 0.0,
        };
    }

    /** Fitur peta: satu per petak, membawa warna fase dan warna persen tumbuh. */
    private function fitur(Collection $blok): array
    {
        $out = [];
        foreach ($blok as $b) {
            foreach ($b->petak as $p) {
                $out[] = ['id' => $p->id, 'blok_id' => $b->id, 'kode' => $p->kode, 'blok' => $b->kode, 'nama' => $b->nama, 'ppkh' => $b->ppkh?->kode,
                    'luas' => $p->luas_ha, 'fase' => $b->fase, 'tumbuh' => $p->persen_tumbuh, 'sehat_ha' => $p->sehat_ha, 'status' => $p->status_tumbuh,
                    'poligon' => $p->poligon, 'lat' => $p->lat, 'lng' => $p->lng, 'das' => $b->das, 'fungsi' => $b->fungsi_kawasan];
            }
        }

        return $out;
    }

    private function peringatan(Collection $blok, Collection $ringkas, Collection $laporan, Collection $kejadian): array
    {
        $a = [];
        foreach ($ringkas as $r) {
            if ($r['waktu'] !== 'aman' && $r['bast_pct'] < 100) {
                $a[] = ['tingkat' => $r['waktu'] === 'lewat' ? 'kritis' : 'waspada', 'ikon' => 'calendar-clock', 'judul' => $r['kode'].' — batas penyelesaian '.($r['sisa_hari'] < 0 ? 'lewat '.abs($r['sisa_hari']).' hari' : $r['sisa_hari'].' hari lagi'),
                    'rinci' => 'Baru '.$r['bast_pct'].'% kewajiban diserahterimakan. Berakhirnya PPKH tidak membebaskan dari utang kewajiban.', 'url' => '/rehab-das/kewajiban/'.$r['id']];
            }
            if ($r['belum_ditetapkan'] > 0) {
                $a[] = ['tingkat' => 'waspada', 'ikon' => 'map-pin', 'judul' => $r['kode'].' — '.number_format($r['belum_ditetapkan'], 1, ',', '.').' ha belum ditetapkan lokasinya',
                    'rinci' => 'Ajukan lokasi tambahan ke BPDAS — jangan tertinggal di garis start.', 'url' => '/rehab-das/kewajiban/'.$r['id']];
            }
            if ($r['sanksi_tahap'] > 0) {
                $a[] = ['tingkat' => 'kritis', 'ikon' => 'gavel', 'judul' => $r['kode'].' — sanksi administratif tahap '.$r['sanksi_tahap'],
                    'rinci' => 'Tindak lanjuti sebelum naik ke tahap berikutnya.', 'url' => '/rehab-das/kewajiban/'.$r['id']];
            }
        }
        foreach ($blok as $b) {
            $sulam = $b->petak->where('status_tumbuh', 'sulam');
            if ($sulam->isNotEmpty() && $b->fase !== 'serah_terima') {
                $a[] = ['tingkat' => 'kritis', 'ikon' => 'sprout', 'judul' => $b->kode.' — '.$sulam->count().' petak tumbuh < 75%',
                    'rinci' => $sulam->pluck('kode')->implode(', ').' · wajib penyulaman sebelum diajukan penilaian.', 'url' => '/rehab-das/blok/'.$b->id];
            }
            if ($b->fase === 'siap_nilai') {
                $k = Kepatuhan::kelayakan($b);
                $a[] = ['tingkat' => $k['layak'] ? 'info' : 'waspada', 'ikon' => 'clipboard-check', 'judul' => $b->kode.' — usia ≥ 36 bulan'.($k['layak'] ? ', siap diajukan penilaian' : ', prasyarat belum lengkap'),
                    'rinci' => $k['layak'] ? 'Seluruh prasyarat terpenuhi.' : implode('; ', $k['penghenti']), 'url' => '/rehab-das/blok/'.$b->id];
            }
        }
        foreach ($laporan->filter(fn ($l) => in_array($l->keadaan(), ['terlambat', 'jatuh_tempo'], true) && ! $l->dikirim_at) as $l) {
            $a[] = ['tingkat' => $l->keadaan() === 'terlambat' ? 'kritis' : 'waspada', 'ikon' => 'file-text', 'judul' => $l->ppkh?->kode.' — laporan semester '.$l->semester.' '.$l->tahun.($l->keadaan() === 'terlambat' ? ' terlambat' : ' jatuh tempo'),
                'rinci' => 'Batas '.$l->batas->translatedFormat('d M Y').'.', 'url' => '/rehab-das/kewajiban/'.$l->ppkh_id];
        }
        foreach ($kejadian as $k) {
            if ((Katalog::KEJADIAN[$k->jenis]['tahap'] ?? 0) > 0) continue;
            $a[] = ['tingkat' => 'kritis', 'ikon' => Katalog::KEJADIAN[$k->jenis]['ikon'] ?? 'triangle-alert', 'judul' => ($k->blok?->kode ?? $k->ppkh?->kode).' — '.Katalog::KEJADIAN[$k->jenis]['label'].' '.number_format($k->luas_terdampak_ha, 1, ',', '.').' ha',
                'rinci' => ($k->dilaporkan_at ? 'Dilaporkan ke BPDAS.' : 'Belum dilaporkan ke BPDAS.').($k->strategi ? ' Exit strategy: '.Katalog::STRATEGI[$k->strategi].'.' : ' Exit strategy belum dipilih.'),
                'url' => '/rehab-das/kewajiban/'.$k->ppkh_id];
        }
        $urut = ['kritis' => 0, 'waspada' => 1, 'info' => 2];
        usort($a, fn ($x, $y) => $urut[$x['tingkat']] <=> $urut[$y['tingkat']]);

        return array_slice($a, 0, 12);
    }

    private function ringkasPpkh(Ppkh $p): array
    {
        return ['id' => $p->id, 'kode' => $p->kode, 'nomor_sk' => $p->nomor_sk, 'kegiatan' => $p->kegiatan, 'provinsi' => $p->provinsi, 'kabupaten' => $p->kabupaten,
            'bpdas' => $p->bpdas, 'berlaku_sampai' => $p->berlaku_sampai->toDateString(), 'batas_selesai' => $p->batas_selesai->toDateString(),
            'jangka_tahun' => $p->jangka_tahun, 'status_lokasi' => $p->status_lokasi, 'rurh_status' => $p->rurh_status, 'nilai_reklamasi' => $p->nilai_reklamasi,
            'predikat' => $p->predikatReklamasi()];
    }

    private function ringkasBlok(Blok $b): array
    {
        $petak = $b->relationLoaded('petak') ? $b->petak : collect();

        return [
            'id' => $b->id, 'kode' => $b->kode, 'nama' => $b->nama, 'das' => $b->das, 'sub_das' => $b->sub_das, 'kabupaten' => $b->kabupaten,
            'fungsi_kawasan' => $b->fungsi_kawasan, 'luas_ha' => $b->luas_ha, 'fase' => $b->fase,
            'tanggal_tanam' => $b->tanggal_tanam?->toDateString(), 'usia_bulan' => $b->usiaBulan(),
            'persen_tumbuh' => Kepatuhan::rataPetak($petak, 'persen_tumbuh'), 'sehat_ha' => Kepatuhan::rataPetak($petak, 'sehat_ha'),
            'petak' => $petak->count(), 'petak_sulam' => $petak->where('status_tumbuh', 'sulam')->count(),
            'luas_bast' => $b->relationLoaded('penilaian') ? round($b->penilaian->where('status', 'serah_terima')->sum('luas_berhasil_ha'), 2) : null,
            'kontraktor' => $b->kontraktor, 'lat' => $b->lat, 'lng' => $b->lng,
        ];
    }

    private function ringkasKegiatan(Kegiatan $k): array
    {
        return $k->only(['id', 'jenis', 'fase', 'luas_ha', 'bibit', 'hok', 'biaya_rp', 'pelaksana', 'status', 'verifikator', 'catatan'])
            + ['tanggal' => $k->tanggal->toDateString(), 'petak' => $k->petak?->kode, 'label' => Katalog::KEGIATAN[$k->jenis]['label'] ?? $k->jenis,
               'milik_saya' => $k->user_id === auth()->id()];
    }

    private function ringkasPenilaian(Penilaian $p): array
    {
        return $p->only(['id', 'kode', 'tahap', 'luas_diajukan_ha', 'luas_berhasil_ha', 'status', 'sk_tim_nomor', 'ketua_tim', 'persen_tumbuh', 'sehat_ha', 'bast_nomor', 'catatan'])
            + ['diajukan_at' => $p->diajukan_at?->toDateString(), 'sk_tim_at' => $p->sk_tim_at?->toDateString(), 'jadwal_mulai' => $p->jadwal_mulai?->toDateString(),
               'jadwal_selesai' => $p->jadwal_selesai?->toDateString(), 'bast_at' => $p->bast_at?->toDateString(), 'status_label' => Katalog::PENILAIAN[$p->status] ?? $p->status,
               'umur_hari' => $p->diajukan_at ? (int) $p->diajukan_at->diffInDays(now()) : null];
    }

    private function ringkasKejadian(Kejadian $k): array
    {
        return $k->only(['id', 'jenis', 'luas_terdampak_ha', 'uraian', 'strategi', 'ba_nomor', 'status'])
            + ['tanggal' => $k->tanggal->toDateString(), 'dilaporkan_at' => $k->dilaporkan_at?->toDateString(), 'blok' => $k->blok?->kode,
               'label' => Katalog::KEJADIAN[$k->jenis]['label'] ?? $k->jenis, 'tahap' => Katalog::KEJADIAN[$k->jenis]['tahap'] ?? 0];
    }

    /** Poligon dari GeoJSON (Polygon/Feature/FeatureCollection) atau daftar "lat, lng" per baris. */
    private function bacaPoligon(?string $teks): ?array
    {
        if (blank($teks)) return null;
        $j = json_decode($teks, true);
        if (is_array($j)) {
            $g = $j['type'] ?? null;
            $koor = match ($g) {
                'Polygon' => $j['coordinates'][0] ?? null,
                'Feature' => $j['geometry']['coordinates'][0] ?? null,
                'FeatureCollection' => $j['features'][0]['geometry']['coordinates'][0] ?? null,
                default => null,
            };
            $titik = collect($koor ?? [])->filter(fn ($t) => is_array($t) && count($t) >= 2 && is_numeric($t[0]) && is_numeric($t[1]))
                ->map(fn ($t) => [round((float) $t[1], 7), round((float) $t[0], 7)])->values()->all();
        } else {
            $titik = collect(preg_split('/\R/', $teks))->map(fn ($l) => preg_split('/[\s,;]+/', trim($l)))->filter(fn ($p) => count($p) >= 2 && is_numeric($p[0]) && is_numeric($p[1]))
                ->map(fn ($p) => [round((float) $p[0], 7), round((float) $p[1], 7)])->values()->all();
        }
        if (count($titik) > 3 && $titik[0] === end($titik)) array_pop($titik);
        $valid = collect($titik)->every(fn ($t) => abs($t[0]) <= 90 && abs($t[1]) <= 180);

        return count($titik) >= 3 && $valid ? $titik : null;
    }

    private function jadwalLaporan(Ppkh $p): void
    {
        for ($th = (int) $p->tanggal_sk->format('Y'); $th <= (int) now()->format('Y'); $th++) {
            foreach ([1, 2] as $s) {
                $batas = Kepatuhan::batasLaporan($th, $s);
                if ($batas->lt($p->tanggal_sk) || $batas->gt(now()->addMonths(8))) continue;
                Laporan::withoutGlobalScopes()->firstOrCreate(['ppkh_id' => $p->id, 'tahun' => $th, 'semester' => $s],
                    ['company_id' => $p->company_id, 'batas' => $batas->toDateString()]);
            }
        }
    }

    private function isiPpkh(array $d): array
    {
        $rasio = (float) ($d['rasio'] ?? 1);
        $sk = Carbon::parse($d['tanggal_sk']);

        return $d + [
            'rasio' => $rasio, 'kewajiban_ha' => round((float) $d['luas_pak_ha'] * $rasio, 2),
            'berlaku_sampai' => $sk->copy()->addYears((int) $d['jangka_tahun'])->toDateString(),
            'batas_selesai' => Kepatuhan::batasSelesai($sk, (int) $d['jangka_tahun'])->toDateString(),
        ];
    }

    private function kodePpkh(?int $c): string
    {
        $n = Ppkh::withoutGlobalScopes()->where('company_id', $c)->count() + 1;
        do { $k = sprintf('PPKH-%02d', $n++); } while (Ppkh::withoutGlobalScopes()->where('company_id', $c)->where('kode', $k)->exists());

        return $k;
    }

    private function kodeBlok(Ppkh $p): string
    {
        $n = Blok::withoutGlobalScopes()->where('company_id', $p->company_id)->count() + 1;
        do { $k = sprintf('RDAS-%02d', $n++); } while (Blok::withoutGlobalScopes()->where('company_id', $p->company_id)->where('kode', $k)->exists());

        return $k;
    }

    private function kodePantau(?int $c, Carbon $t): string
    {
        $n = Pantau::withoutGlobalScopes()->where('company_id', $c)->whereYear('tanggal', $t->year)->count() + 1;
        do { $k = sprintf('PU-%s-%04d', $t->format('Y'), $n++); } while (Pantau::withoutGlobalScopes()->where('company_id', $c)->where('kode', $k)->exists());

        return $k;
    }

    private function kodePenilaian(?int $c): string
    {
        $n = Penilaian::withoutGlobalScopes()->where('company_id', $c)->count() + 1;
        do { $k = sprintf('PNL-%s-%03d', now()->format('Y'), $n++); } while (Penilaian::withoutGlobalScopes()->where('company_id', $c)->where('kode', $k)->exists());

        return $k;
    }

    private function aturanPpkh(): array
    {
        return [
            'company_id' => ['nullable', 'exists:companies,id'],
            'nomor_sk' => ['required', 'string', 'max:150'], 'tanggal_sk' => ['required', 'date'], 'jangka_tahun' => ['required', 'integer', 'min:1', 'max:100'],
            'tujuan' => ['required', Rule::in(['komersial', 'non_komersial'])], 'kegiatan' => ['required', 'string', 'max:150'],
            'provinsi' => ['required', 'string', 'max:60'], 'kabupaten' => ['nullable', 'string', 'max:80'], 'bpdas' => ['required', 'string', 'max:100'],
            'luas_ppkh_ha' => ['required', 'numeric', 'min:0.01'], 'nomor_pak' => ['nullable', 'string', 'max:150'], 'luas_pak_ha' => ['required', 'numeric', 'min:0.01'],
            'rasio' => ['nullable', 'numeric', 'min:1', 'max:10'], 'status_lokasi' => ['nullable', Rule::in(['belum_diajukan', 'diajukan', 'ditetapkan'])],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function aturanBlok(): array
    {
        return [
            'ppkh_id' => ['required', 'integer', Rule::exists('rdas_ppkh', 'id')],
            'nama' => ['required', 'string', 'max:150'], 'sk_penlok_nomor' => ['nullable', 'string', 'max:150'], 'sk_penlok_tanggal' => ['nullable', 'date'],
            'das' => ['required', 'string', 'max:80'], 'sub_das' => ['nullable', 'string', 'max:80'], 'kabupaten' => ['nullable', 'string', 'max:80'],
            'kecamatan' => ['nullable', 'string', 'max:80'], 'desa' => ['nullable', 'string', 'max:120'],
            'fungsi_kawasan' => ['required', Rule::in(array_keys(Katalog::FUNGSI))], 'pemangku' => ['nullable', 'string', 'max:150'],
            'ekosistem' => ['required', Rule::in(array_keys(Katalog::EKOSISTEM))], 'luas_ha' => ['required', 'numeric', 'min:0.1', 'max:100000'],
            'jarak_tanam' => ['nullable', 'string', 'max:20'], 'kerapatan' => ['required', 'integer', 'min:100', 'max:5000'],
            'komposisi' => ['nullable', 'array'], 'komposisi.*.jenis' => ['required', Rule::in(array_keys(Katalog::JENIS))], 'komposisi.*.persen' => ['required', 'numeric', 'min:0', 'max:100'],
            'rkp_nomor' => ['nullable', 'string', 'max:150'], 'rkp_disahkan_at' => ['nullable', 'date'], 'tanggal_tanam' => ['nullable', 'date', 'before_or_equal:today'],
            'kontraktor' => ['nullable', 'string', 'max:150'], 'anggaran_rp' => ['nullable', 'numeric', 'min:0'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'], 'lng' => ['nullable', 'numeric', 'between:-180,180'], 'catatan' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
