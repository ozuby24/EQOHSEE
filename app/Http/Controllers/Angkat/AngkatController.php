<?php

namespace App\Http\Controllers\Angkat;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Energi\BantuEnergi;
use App\Models\ActivityLog;
use App\Models\Angkat\{Alat, Gear, Jejak, Kajian, Rencana, Riksa};
use App\Support\Angkat\{KajianTeknis, Katalog, Rencanakan, RiksaUji, Simulasi};
use App\Support\Perusahaan;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Lifting & Rigging Management — pengangkatan di tambang dari register
 * PAA sampai pengangkatan selesai.
 *
 * Alurnya: crane & alat bantu terdaftar dengan masa riksa ujinya →
 * rencana pengangkatan dihitung (rigging, kapasitas, landasan, ruang
 * bebas, angin, SUTT) dan disimulasikan 4 fase → kajian teknis 6 pilar
 * → diajukan → disetujui (KTT untuk pengangkatan kritis) → dilaksanakan.
 * Alat yang tidak layak digunakan tidak bisa masuk ke rencana mana pun.
 */
class AngkatController extends Controller
{
    use BantuEnergi;

    /* ═══════════ dasbor ═══════════ */

    public function index(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $kini = now();
        $per = fn ($q) => $q->when($c, fn ($q) => $q->where('company_id', $c));

        $awal = $kini->copy()->subMonths(11)->startOfMonth();
        $rencana = $per(Rencana::query())->where(fn ($q) => $q->where('created_at', '>=', $awal)->orWhere('jadwal_at', '>=', $awal))
            ->with('kajian')->get();
        $alat = $per(Alat::query())->get();
        $gear = $per(Gear::query())->get();
        $riksa = $per(Riksa::query())->where('diperiksa_at', '>=', $kini->copy()->subMonths(11)->startOfMonth())->get();

        $bulanIni = $rencana->filter(fn (Rencana $r) => $r->jadwal_at && $r->jadwal_at->isSameMonth($kini));
        $aktif = $rencana->whereNotIn('status', ['batal']);
        $menunggu = $rencana->where('status', 'diajukan');
        $layakAngkat = $aktif->where('putusan', '!=', 'nogo');

        // Tren 12 bulan menurut klasifikasi + rerata utilisasi.
        $tren = [];
        for ($i = 11; $i >= 0; $i--) {
            $b = $kini->copy()->subMonths($i)->startOfMonth();
            $m = $aktif->filter(fn (Rencana $r) => $r->jadwal_at && $r->jadwal_at->isSameMonth($b));
            $tren[] = ['label' => $b->translatedFormat('M y'),
                'rutin' => $m->where('klasifikasi', 'rutin')->count(), 'rekayasa' => $m->where('klasifikasi', 'rekayasa')->count(),
                'kritis' => $m->where('klasifikasi', 'kritis')->count(),
                'util' => ($u = $m->where('putusan', '!=', 'nogo'))->count() ? round($u->avg('utilisasi_pct'), 1) : null];
        }

        // Sebaran utilisasi (histogram).
        $kelas = [[0, 25, '< 25%'], [25, 50, '25–50%'], [50, 65, '50–65%'], [65, 75, '65–75%'], [75, 85, '75–85%'], [85, 999, '> 85%']];
        $histogram = collect($kelas)->map(fn ($k) => ['label' => $k[2],
            'jumlah' => $aktif->filter(fn ($r) => $r->utilisasi_pct >= $k[0] && $r->utilisasi_pct < $k[1])->count()])->all();

        // Radar rerata skor 6 pilar dari kajian rencana aktif.
        $pilar = collect(Katalog::PILAR)->map(function ($p, $kode) use ($aktif) {
            $s = $aktif->map(fn ($r) => collect($r->kajian?->pilar ?? [])->firstWhere('kode', $kode)['skor'] ?? null)->filter(fn ($x) => $x !== null);
            return ['kode' => $kode, 'nama' => $p['nama'], 'skor' => $s->count() ? round($s->avg(), 1) : 0];
        })->values()->all();

        // Register: status efektif.
        $statusAlat = $alat->groupBy(fn (Alat $a) => $a->statusEfektif())->map->count();
        $statusGear = $gear->groupBy(fn (Gear $g) => $g->statusEfektif())->map->count();
        $gearJenis = collect(Katalog::GEAR)->map(fn ($g, $k) => ['label' => $g['label'],
            'layak' => $gear->where('jenis', $k)->filter(fn ($x) => $x->statusEfektif() === 'layak')->count(),
            'segera' => $gear->where('jenis', $k)->filter(fn ($x) => $x->statusEfektif() === 'segera')->count(),
            'kedaluwarsa' => $gear->where('jenis', $k)->filter(fn ($x) => $x->statusEfektif() === 'kedaluwarsa')->count(),
            'karantina' => $gear->where('jenis', $k)->filter(fn ($x) => in_array($x->statusEfektif(), ['karantina', 'afkir'], true))->count(),
        ])->values()->all();

        // Pareto penyebab tidak layak riksa uji 12 bulan.
        $sebab = [];
        foreach ($riksa->where('putusan', 'tidak_layak') as $x) {
            foreach ($x->butir as $b) {
                if (($b['status'] ?? null) === 'gagal' && ($b['kritis'] ?? false)) $sebab[$b['label']] = ($sebab[$b['label']] ?? 0) + 1;
            }
            if ($x->ndt_hasil === 'ada_cacat') $sebab['NDT menemukan retak/cacat'] = ($sebab['NDT menemukan retak/cacat'] ?? 0) + 1;
            foreach (Katalog::UKUR as $k => $u) {
                $v = $x->ukur[$k] ?? null;
                $batas = $k === 'defleksi_mm' ? (Katalog::UJI[$x->kategori_uji]['defleksi_mm'] ?? 0) : $u['batas'];
                if ($v !== null && (float) $v > $batas) $sebab[$u['label']] = ($sebab[$u['label']] ?? 0) + 1;
            }
        }
        arsort($sebab);

        // Jatuh tempo riksa ≤ 45 hari, dan jadwal pengangkatan 14 hari ke depan.
        $tempo = collect()
            ->merge($alat->filter(fn ($a) => in_array($a->statusEfektif(), ['segera', 'kedaluwarsa'], true))
                ->map(fn (Alat $a) => ['objek' => 'alat', 'id' => $a->id, 'kode' => $a->kode, 'nama' => $a->nama, 'status' => $a->statusEfektif(), 'tanggal' => $a->suket_berlaku?->toDateString()]))
            ->merge($gear->filter(fn ($g) => in_array($g->statusEfektif(), ['segera', 'kedaluwarsa'], true))
                ->map(fn (Gear $g) => ['objek' => 'gear', 'id' => $g->id, 'kode' => $g->kode, 'nama' => $g->nama, 'status' => $g->statusEfektif(), 'tanggal' => $g->uji_berikut?->toDateString()]))
            ->sortBy('tanggal')->take(8)->values()->all();
        $jadwal = $per(Rencana::query())->whereIn('status', ['diajukan', 'disetujui'])
            ->whereBetween('jadwal_at', [$kini->copy()->startOfDay(), $kini->copy()->addDays(14)->endOfDay()])
            ->orderBy('jadwal_at')->take(8)->get()->map(fn (Rencana $r) => $this->ringkas($r))->all();

        $sebar = $aktif->sortByDesc('jadwal_at')->take(60)->map(fn (Rencana $r) => ['id' => $r->id, 'kode' => $r->kode, 'judul' => $r->judul,
            'x' => $r->radius_m, 'y' => $r->berat_kotor_t, 'util' => $r->utilisasi_pct, 'putusan' => $r->putusan, 'klasifikasi' => $r->klasifikasi])->values()->all();

        $alatLayak = ($statusAlat['layak'] ?? 0) + ($statusAlat['segera'] ?? 0);
        $gearLayak = ($statusGear['layak'] ?? 0) + ($statusGear['segera'] ?? 0);

        return Inertia::render('Angkat/Dasbor', [
            'ringkas' => [
                'bulan_ini' => $bulanIni->whereNotIn('status', ['batal'])->count(),
                'kritis_bulan' => $bulanIni->where('klasifikasi', 'kritis')->whereNotIn('status', ['batal'])->count(),
                'selesai_bulan' => $bulanIni->where('status', 'selesai')->count(),
                'menunggu' => $menunggu->count(), 'menunggu_ktt' => $menunggu->where('klasifikasi', 'kritis')->count(),
                // NO-GO tidak pernah diangkat — utilisasinya bukan utilisasi lapangan.
                'util_rata' => $layakAngkat->count() ? round($layakAngkat->avg('utilisasi_pct'), 1) : null,
                'util_maks' => $layakAngkat->max('utilisasi_pct'),
                'nogo' => $aktif->where('putusan', 'nogo')->count(),
                'alat' => $alat->count(), 'alat_layak' => $alatLayak,
                'alat_pct' => $alat->count() ? round($alatLayak / $alat->count() * 100, 1) : null,
                'gear' => $gear->count(), 'gear_layak' => $gearLayak,
                'gear_pct' => $gear->count() ? round($gearLayak / $gear->count() * 100, 1) : null,
                'karantina' => ($statusGear['karantina'] ?? 0) + ($statusAlat['karantina'] ?? 0),
                'kedaluwarsa' => ($statusGear['kedaluwarsa'] ?? 0) + ($statusAlat['kedaluwarsa'] ?? 0),
                'segera' => ($statusGear['segera'] ?? 0) + ($statusAlat['segera'] ?? 0),
                'riksa_12' => $riksa->count(),
                'tolak_12' => $riksa->count() ? round($riksa->where('putusan', 'tidak_layak')->count() / $riksa->count() * 100, 1) : null,
                'skor_kajian' => round(collect($pilar)->avg('skor'), 1),
            ],
            'tren' => $tren, 'histogram' => $histogram, 'pilar' => $pilar,
            'klasifikasi' => collect(Katalog::KLASIFIKASI)->map(fn ($k, $kode) => ['kode' => $kode, 'label' => $k['label'], 'nilai' => $aktif->where('klasifikasi', $kode)->count()])->values()->all(),
            'statusRencana' => collect(Katalog::STATUS)->map(fn ($l, $k) => ['kode' => $k, 'label' => $l, 'nilai' => $rencana->where('status', $k)->count()])->values()->all(),
            'gearJenis' => $gearJenis,
            'sebab' => collect($sebab)->take(7)->map(fn ($n, $l) => ['label' => $l, 'jumlah' => $n])->values()->all(),
            'tempo' => $tempo, 'jadwal' => $jadwal, 'sebar' => $sebar,
            'menunggu' => $menunggu->sortBy('jadwal_at')->take(6)->map(fn (Rencana $r) => $this->ringkas($r))->values()->all(),
            'perusahaan' => $c, 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    /* ═══════════ daftar rencana ═══════════ */

    public function daftar(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $q = Rencana::query()->when($c, fn ($q) => $q->where('company_id', $c))->with('kajian');
        if ($request->filled('status')) $q->where('status', $request->string('status'));
        if ($request->filled('klasifikasi')) $q->where('klasifikasi', $request->string('klasifikasi'));
        if ($request->filled('q')) {
            $kata = '%'.$request->string('q').'%';
            $q->where(fn ($w) => $w->where('kode', 'like', $kata)->orWhere('judul', 'like', $kata)->orWhere('lokasi', 'like', $kata));
        }

        $semua = Rencana::query()->when($c, fn ($q) => $q->where('company_id', $c));

        return Inertia::render('Angkat/Daftar', [
            'rencana' => $q->orderByDesc('jadwal_at')->orderByDesc('id')->take(200)->get()->map(fn (Rencana $r) => $this->ringkas($r))->all(),
            'hitungStatus' => (clone $semua)->selectRaw('status, count(*) n')->groupBy('status')->pluck('n', 'status'),
            'saring' => $request->only(['status', 'klasifikasi', 'q']),
            'pilihan' => Katalog::pilihan(),
            'perusahaan' => $c, 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    /* ═══════════ rencana & simulasi ═══════════ */

    public function baru(Request $request)
    {
        $c = $this->perusahaanAktif($request);

        return Inertia::render('Angkat/Rencana', [
            'rencana' => ['judul' => '', 'lokasi' => '', 'area' => Katalog::AREA[0], 'alat_id' => null, 'gear' => [],
                'jadwal_at' => now()->addDay()->setTime(8, 0)->format('Y-m-d\TH:i'), 'masukan' => Simulasi::BAWAAN] + array_fill_keys(self::ORANG, ''),
            'kodeBerikut' => $this->kodeBaru($c),
            'register' => $this->register($c),
            'pilihan' => Katalog::pilihan(),
            'awal' => Simulasi::hitung(Simulasi::BAWAAN),
        ]);
    }

    public function ubah(Rencana $rencana)
    {
        abort_unless($rencana->terbuka(), 403, 'Rencana yang sudah diajukan tidak dapat diubah. Tarik kembali atau buat versi baru.');
        $alat = $rencana->alat_id ? Alat::find($rencana->alat_id) : null;

        return Inertia::render('Angkat/Rencana', [
            'rencana' => ['id' => $rencana->id, 'judul' => $rencana->judul, 'lokasi' => $rencana->lokasi, 'area' => $rencana->area,
                'alat_id' => $rencana->alat_id, 'gear' => $rencana->gear ?? [], 'catatan' => $rencana->catatan,
                'jadwal_at' => $rencana->jadwal_at?->format('Y-m-d\TH:i'), 'masukan' => $rencana->masukan] + $rencana->only(self::ORANG),
            'kodeBerikut' => $rencana->kode,
            'register' => $this->register($rencana->company_id),
            'pilihan' => Katalog::pilihan(),
            'awal' => Simulasi::hitung($rencana->masukan, $alat?->spek()),
        ]);
    }

    /** Pratinjau langsung untuk form — tanpa menyimpan. */
    public function hitung(Request $request)
    {
        $m = $request->validate($this->aturanMasukan(true));
        $alat = $request->filled('alat_id') ? Alat::find($request->integer('alat_id')) : null;
        $hasil = Simulasi::hitung($m, $alat?->spek());
        $gear = Gear::whereIn('id', (array) $request->input('gear', []))->get();
        $kajian = KajianTeknis::nilai($hasil, $request->only(self::ORANG), $alat, $gear, []);

        return response()->json(['hasil' => $hasil, 'kajian' => ['skor' => $kajian['skor'], 'putusan' => $kajian['putusan'],
            'penghenti' => $kajian['penghenti'], 'pilar' => collect($kajian['pilar'])->map(fn ($p) => \Illuminate\Support\Arr::only($p, ['kode', 'nama', 'skor']))->all()]]);
    }

    public function simpan(Request $request)
    {
        $data = $this->pemilik($request->validate($this->aturan()));
        $u = $request->user();
        if ($u->isAdmin() && empty($data['company_id'])) $data['company_id'] = Perusahaan::terpilih($u) ?? $u->company_id;
        [$alat, $gear] = $this->periksaRegister($data);

        $r = new Rencana(['user_id' => $u->id, 'company_id' => $data['company_id'] ?? null, 'kode' => $this->kodeBaru($data['company_id'] ?? null), 'status' => 'draf']);
        $this->isi($r, $data, $alat);
        $r->save();
        KajianTeknis::kaji($r, [], $u->name);
        Jejak::catat($r, 'dibuat', 'Rencana dibuat: '.$r->judul.' — '.Katalog::KLASIFIKASI[$r->klasifikasi]['label'].', utilisasi '.$r->utilisasi_pct.'%.');
        ActivityLog::write('Buat rencana pengangkatan', $r->kode.' — '.$r->judul, 'angkat');

        return redirect()->route('angkat.rincian', $r)->with('ok', 'Rencana '.$r->kode.' tersimpan sebagai draf. Lengkapi kajian 6 pilar lalu ajukan.');
    }

    public function perbarui(Request $request, Rencana $rencana)
    {
        abort_unless($rencana->terbuka(), 403, 'Rencana yang sudah diajukan tidak dapat diubah.');
        $data = $request->validate($this->aturan());
        unset($data['company_id']);
        $data['company_id'] = $rencana->company_id;
        [$alat] = $this->periksaRegister($data);

        $this->isi($rencana, $data, $alat);
        $rencana->status = 'draf';
        $rencana->save();
        KajianTeknis::kaji($rencana);
        Jejak::catat($rencana, 'diubah', 'Rencana diubah dan disimulasikan ulang — '.$rencana->putusan.', skor '.$rencana->skor.'.');

        return redirect()->route('angkat.rincian', $rencana)->with('ok', 'Rencana diperbarui, disimulasikan ulang, dan kajian dihitung ulang.');
    }

    /* ═══════════ rincian, kajian, alur ═══════════ */

    public function rincian(Rencana $rencana)
    {
        $kajian = $rencana->kajian ?? KajianTeknis::kaji($rencana);
        // Register bisa berubah sejak kajian terakhir: SUKET habis, sling dikarantina.
        if ($rencana->terbuka() || $rencana->status === 'diajukan') $kajian = KajianTeknis::kaji($rencana);
        $alat = $rencana->alat_id ? Alat::find($rencana->alat_id) : null;
        $gear = Gear::whereIn('id', $rencana->gear ?? [])->get();

        return Inertia::render('Angkat/Rincian', [
            'rencana' => $this->ringkas($rencana) + $rencana->only([...self::ORANG, 'catatan', 'catatan_persetujuan', 'lokasi', 'area'])
                + ['disetujui_oleh' => $rencana->disetujui_oleh, 'disetujui_at' => $rencana->disetujui_at?->toIso8601String(),
                   'selesai_at' => $rencana->selesai_at?->toIso8601String(), 'pembuat' => $rencana->pembuat?->name,
                   'bisa_ubah' => $rencana->terbuka()],
            'hasil' => $rencana->hasil,
            'beban' => Katalog::BEBAN[$rencana->kategori_beban] ?? null,
            'kajian' => $kajian->only(['kode', 'pilar', 'skor', 'putusan', 'kesimpulan', 'peninjau']) + ['ditinjau_at' => $kajian->ditinjau_at?->toIso8601String()],
            'alat' => $alat ? $this->ringkasAlat($alat) : null,
            'gear' => $gear->map(fn (Gear $g) => $this->ringkasGear($g))->all(),
            'hambatan' => $this->hambatan($rencana, $kajian),
            'jejak' => Jejak::where('rencana_id', $rencana->id)->orderByDesc('created_at')->orderByDesc('id')->take(30)->get()
                ->map(fn (Jejak $j) => ['aksi' => $j->aksi, 'oleh' => $j->oleh, 'ringkas' => $j->ringkas, 'waktu' => $j->created_at?->toIso8601String()])->all(),
            'klasifikasi' => Katalog::KLASIFIKASI,
            'pembuatSendiri' => $rencana->user_id === auth()->id(),
        ]);
    }

    public function kajian(Request $request, Rencana $rencana)
    {
        abort_if(in_array($rencana->status, ['selesai', 'batal'], true), 403);
        $manual = collect(Katalog::PILAR)->flatMap(fn ($p) => $p['butir'])->where('oto', false)->pluck('kode')->all();
        $data = $request->validate([
            'manual' => ['array'],
            'manual.*' => [Rule::in(['penuh', 'sebagian', 'tidak', 'belum'])],
        ]);
        $jawab = collect($data['manual'] ?? [])->only($manual)->reject(fn ($v) => $v === 'belum')->all();
        $k = KajianTeknis::kaji($rencana, $jawab, $request->user()->name);
        Jejak::catat($rencana, 'dikaji', 'Kajian 6 pilar dinilai: '.str_replace('_', ' ', $k->putusan).' (skor '.$k->skor.').');

        return back()->with('ok', 'Kajian teknis tersimpan: '.str_replace('_', ' ', $k->putusan).', skor '.$k->skor.'.');
    }

    public function status(Request $request, Rencana $rencana)
    {
        $data = $request->validate([
            'aksi' => ['required', Rule::in(['ajukan', 'tarik', 'setujui', 'tolak', 'selesai', 'batal'])],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        $u = $request->user();
        $aksi = $data['aksi'];
        $catatan = trim((string) ($data['catatan'] ?? ''));
        $sah = ['ajukan' => ['draf', 'ditolak'], 'tarik' => ['diajukan'], 'setujui' => ['diajukan'], 'tolak' => ['diajukan'],
                'selesai' => ['disetujui'], 'batal' => ['draf', 'diajukan', 'disetujui', 'ditolak']][$aksi];
        if (! in_array($rencana->status, $sah, true)) {
            return back()->withErrors(['aksi' => 'Rencana berstatus '.strtolower(Katalog::STATUS[$rencana->status]).' tidak dapat di-'.$aksi.'.']);
        }

        if (in_array($aksi, ['ajukan', 'setujui'], true)) {
            $hambat = $this->hambatan($rencana, KajianTeknis::kaji($rencana));
            if ($hambat) return back()->withErrors(['aksi' => 'Belum dapat di'.($aksi === 'ajukan' ? 'ajukan' : 'setujui').': '.implode(' ', $hambat)]);
        }
        if ($aksi === 'setujui' && $rencana->user_id === $u->id) {
            return back()->withErrors(['aksi' => 'Rencana tidak dapat disetujui oleh pembuatnya sendiri — minta pengawas lain'.($rencana->klasifikasi === 'kritis' ? ' atau KTT' : '').'.']);
        }
        if ($aksi === 'tolak' && $catatan === '') {
            return back()->withErrors(['catatan' => 'Tuliskan alasan penolakan supaya pembuat rencana tahu apa yang harus diperbaiki.']);
        }

        $label = ['ajukan' => 'diajukan', 'tarik' => 'ditarik kembali', 'setujui' => 'disetujui', 'tolak' => 'ditolak', 'selesai' => 'selesai dilaksanakan', 'batal' => 'dibatalkan'][$aksi];
        match ($aksi) {
            'ajukan' => $rencana->update(['status' => 'diajukan']),
            'tarik' => $rencana->update(['status' => 'draf']),
            'setujui' => $rencana->update(['status' => 'disetujui', 'disetujui_oleh' => $u->name.($rencana->klasifikasi === 'kritis' ? ' (KTT)' : ''),
                'disetujui_at' => now(), 'catatan_persetujuan' => $catatan ?: null]),
            'tolak' => $rencana->update(['status' => 'ditolak', 'catatan_persetujuan' => $catatan]),
            'selesai' => $rencana->update(['status' => 'selesai', 'selesai_at' => now()]),
            'batal' => $rencana->update(['status' => 'batal']),
        };
        Jejak::catat($rencana, $aksi, 'Rencana '.$label.($catatan ? ': '.$catatan : '.'));
        ActivityLog::write('Rencana pengangkatan '.$label, $rencana->kode, 'angkat');

        return back()->with('ok', 'Rencana '.$rencana->kode.' '.$label.'.');
    }

    /* ═══════════ register PAA & alat bantu ═══════════ */

    public function peralatan(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $alat = Alat::query()->when($c, fn ($q) => $q->where('company_id', $c))->orderBy('kode')->get();
        $gear = Gear::query()->when($c, fn ($q) => $q->where('company_id', $c))->orderBy('jenis')->orderBy('kode')->get();
        $dipakai = Rencana::query()->when($c, fn ($q) => $q->where('company_id', $c))->whereIn('status', ['diajukan', 'disetujui'])->get(['gear', 'alat_id']);
        $gearDipakai = $dipakai->flatMap(fn ($r) => $r->gear ?? [])->countBy();

        return Inertia::render('Angkat/Peralatan', [
            'alat' => $alat->map(fn (Alat $a) => $this->ringkasAlat($a) + ['rencana_aktif' => $dipakai->where('alat_id', $a->id)->count()])->all(),
            'gear' => $gear->map(fn (Gear $g) => $this->ringkasGear($g) + ['rencana_aktif' => $gearDipakai[$g->id] ?? 0])->all(),
            'pilihan' => Katalog::pilihan(),
            'tab' => $request->query('tab') === 'gear' ? 'gear' : 'alat',
            'perusahaan' => $c, 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    public function simpanAlat(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'nama' => ['required', 'string', 'max:160'],
            'jenis' => ['required', Rule::in(array_keys(Katalog::ALAT))],
            'pabrikan' => ['nullable', 'string', 'max:80'], 'model' => ['nullable', 'string', 'max:80'],
            'tahun' => ['nullable', 'integer', 'min:1970', 'max:'.(now()->year + 1)],
            'nomor_seri' => ['nullable', 'string', 'max:60'],
            'kapasitas_t' => ['required', 'numeric', 'min:0.5', 'max:2000'],
            'boom_maks_m' => ['nullable', 'numeric', 'min:1', 'max:200'],
            'lokasi' => ['nullable', 'string', 'max:150'],
            'operator_nama' => ['nullable', 'string', 'max:120'], 'operator_sio' => ['nullable', 'string', 'max:80'],
            'sio_berlaku' => ['nullable', 'date'],
        ]));
        $u = $request->user();
        if ($u->isAdmin() && empty($data['company_id'])) $data['company_id'] = Perusahaan::terpilih($u) ?? $u->company_id;
        $kat = Katalog::ALAT[$data['jenis']];
        $a = Alat::create($data + [
            'user_id' => $u->id, 'kode' => $this->kodeAlat($data['company_id'] ?? null, $kat['kategori']),
            'berat_crane_t' => $kat['berat_t'] * $data['kapasitas_t'] / max(1, $kat['kapasitas_t']),
            'boom_maks_m' => $data['boom_maks_m'] ?? $kat['boom_m'], 'berat_hook_t' => $kat['hook_t'],
            'status' => 'layak',
        ]);
        ActivityLog::write('Daftarkan pesawat angkat', $a->kode.' — '.$a->nama, 'angkat');

        return redirect()->route('angkat.riksa.baru', ['objek' => 'alat', 'id' => $a->id])
            ->with('ok', $a->kode.' terdaftar tanpa SUKET — catat riksa uji pertama agar layak digunakan.');
    }

    public function simpanGear(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'kode' => ['required', 'string', 'max:50'],
            'nama' => ['required', 'string', 'max:160'],
            'jenis' => ['required', Rule::in(array_keys(Katalog::GEAR))],
            'wll_t' => ['required', 'numeric', 'min:0.1', 'max:1000'],
            'diameter_mm' => ['nullable', 'numeric', 'min:1', 'max:500'],
            'panjang_m' => ['nullable', 'numeric', 'min:0.1', 'max:200'],
            'jumlah_kaki' => ['nullable', 'integer', 'min:1', 'max:4'],
            'pabrikan' => ['nullable', 'string', 'max:80'],
            'lokasi_simpan' => ['nullable', 'string', 'max:120'],
        ]));
        $u = $request->user();
        if ($u->isAdmin() && empty($data['company_id'])) $data['company_id'] = Perusahaan::terpilih($u) ?? $u->company_id;
        if (Gear::withoutGlobalScopes()->where('company_id', $data['company_id'] ?? null)->where('kode', $data['kode'])->exists()) {
            return back()->withErrors(['kode' => 'Nomor seri '.$data['kode'].' sudah terdaftar.']);
        }
        $g = Gear::create($data + ['sf' => Katalog::GEAR[$data['jenis']]['sf'], 'jumlah_kaki' => $data['jumlah_kaki'] ?? 1, 'status' => 'layak']);
        ActivityLog::write('Daftarkan alat bantu angkat', $g->kode.' — '.$g->nama, 'angkat');

        return redirect()->route('angkat.riksa.baru', ['objek' => 'gear', 'id' => $g->id])
            ->with('ok', $g->kode.' terdaftar — catat riksa uji (proof load) agar dapat dipilih di rencana.');
    }

    public function afkir(Request $request, Gear $gear)
    {
        $gear->update(['status' => 'afkir', 'kode_warna' => 'MERAH']);
        ActivityLog::write('Afkir alat bantu angkat', $gear->kode, 'angkat');

        return back()->with('ok', $gear->kode.' diafkirkan dan ditarik dari peredaran.');
    }

    /* ═══════════ riksa uji ═══════════ */

    public function riksa(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $q = Riksa::query()->when($c, fn ($q) => $q->where('company_id', $c))->with(['alat:id,kode,nama', 'gear:id,kode,nama']);
        if ($request->filled('putusan')) $q->where('putusan', $request->string('putusan'));
        $semua = Riksa::query()->when($c, fn ($q) => $q->where('company_id', $c))->where('diperiksa_at', '>=', now()->subMonths(11)->startOfMonth())->get();

        $bulan = [];
        for ($i = 11; $i >= 0; $i--) {
            $b = now()->subMonths($i)->startOfMonth();
            $m = $semua->filter(fn ($r) => $r->diperiksa_at->isSameMonth($b));
            $bulan[] = ['label' => $b->translatedFormat('M y'), 'layak' => $m->where('putusan', 'layak')->count(), 'tidak' => $m->where('putusan', 'tidak_layak')->count()];
        }

        return Inertia::render('Angkat/Riksa', [
            'riksa' => $q->orderByDesc('diperiksa_at')->orderByDesc('id')->take(150)->get()->map(fn (Riksa $r) => $this->ringkasRiksa($r))->all(),
            'bulan' => $bulan,
            'perKategori' => collect(Katalog::UJI)->map(fn ($u, $k) => ['label' => $u['label'], 'layak' => $semua->where('kategori_uji', $k)->where('putusan', 'layak')->count(),
                'tidak' => $semua->where('kategori_uji', $k)->where('putusan', 'tidak_layak')->count()])->values()->all(),
            'saring' => $request->only(['putusan']),
            'perusahaan' => $c, 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    public function formRiksa(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $objek = $request->query('objek') === 'gear' ? 'gear' : 'alat';
        $id = $request->integer('id');
        $target = $id ? ($objek === 'alat' ? Alat::find($id) : Gear::find($id)) : null;
        $kategori = $target ? ($objek === 'alat' ? 'crane' : (Katalog::GEAR[$target->jenis]['uji'] ?? 'wire_rope')) : null;

        return Inertia::render('Angkat/FormRiksa', [
            'objek' => $objek,
            'target' => $target ? ($objek === 'alat' ? $this->ringkasAlat($target) : $this->ringkasGear($target)) : null,
            'kategori' => $kategori,
            'kategoriLabel' => $kategori ? Katalog::UJI[$kategori]['label'] : null,
            'butir' => $kategori ? Katalog::BUTIR[$kategori] : [],
            'beban' => $target ? RiksaUji::beban($kategori, $objek === 'alat' ? $target->kapasitas_t : $target->wll_t) : null,
            'ukur' => $kategori ? collect(Katalog::UKUR)->filter(fn ($u) => in_array($kategori, $u['berlaku'], true))
                ->map(fn ($u, $k) => ['kode' => $k, 'label' => $u['label'], 'satuan' => $u['satuan'],
                    'batas' => $k === 'defleksi_mm' ? Katalog::UJI[$kategori]['defleksi_mm'] : $u['batas']])->values()->all() : [],
            'register' => $this->register($c, true),
            'pilihan' => Katalog::pilihan(),
            'pemeriksa' => $request->user()->name,
        ]);
    }

    public function pratinjauRiksa(Request $request)
    {
        $d = $request->validate([
            'kategori_uji' => ['required', Rule::in(array_keys(Katalog::UJI))],
            'periksa' => ['array'], 'periksa.*' => [Rule::in(['lulus', 'gagal', 'na'])],
            'ukur' => ['array'], 'ukur.*' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'ndt_metode' => ['nullable', Rule::in(array_keys(Katalog::NDT))],
            'ndt_hasil' => ['nullable', Rule::in(['tanpa_indikasi', 'ada_cacat'])],
        ]);

        return response()->json(RiksaUji::nilai($d + ['periksa' => []]));
    }

    public function simpanRiksa(Request $request)
    {
        $d = $request->validate([
            'objek' => ['required', Rule::in(['alat', 'gear'])],
            'id' => ['required', 'integer'],
            'jenis' => ['required', Rule::in(array_keys(Katalog::JENIS_RIKSA))],
            'pemeriksa' => ['required', 'string', 'max:120'],
            'skp_nomor' => ['required', 'string', 'max:80'],
            'pjk3' => ['nullable', 'string', 'max:150'],
            'periksa' => ['required', 'array'], 'periksa.*' => ['required', Rule::in(['lulus', 'gagal', 'na'])],
            'ukur' => ['array'], 'ukur.*' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'ndt_metode' => ['required', Rule::in(array_keys(Katalog::NDT))],
            'ndt_hasil' => ['required', Rule::in(['tanpa_indikasi', 'ada_cacat'])],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        $target = $d['objek'] === 'alat' ? Alat::findOrFail($d['id']) : Gear::findOrFail($d['id']);
        $kategori = $d['objek'] === 'alat' ? 'crane' : (Katalog::GEAR[$target->jenis]['uji'] ?? 'wire_rope');
        $wajib = array_column(Katalog::BUTIR[$kategori], 'kode');
        $belum = array_diff($wajib, array_keys($d['periksa']));
        if ($belum) return back()->withErrors(['periksa' => count($belum).' butir belum dinilai: '.implode(', ', $belum).'.']);

        $r = RiksaUji::catat($target, $d, $request->user()->id);
        ActivityLog::write('Riksa uji PAA', $r->nomor.' — '.($target->kode).' '.$r->putusan, 'angkat');

        return redirect()->route('angkat.riksa.lihat', $r)->with($r->putusan === 'layak' ? 'ok' : 'galat',
            $r->putusan === 'layak' ? 'Riksa uji '.$r->nomor.': LAYAK DIGUNAKAN sampai '.$r->berlaku_sampai?->translatedFormat('d M Y').'.'
                : 'Riksa uji '.$r->nomor.': TIDAK LAYAK DIGUNAKAN — '.$target->kode.' dikarantina.');
    }

    public function lihatRiksa(Riksa $riksa)
    {
        $riksa->load(['alat', 'gear']);

        return Inertia::render('Angkat/LaporanRiksa', [
            'riksa' => $this->ringkasRiksa($riksa) + $riksa->only(['skp_nomor', 'pjk3', 'uji_statis_pct', 'beban_statis_t', 'uji_dinamis_pct',
                'beban_dinamis_t', 'tahan_menit', 'ukur', 'ndt_metode', 'ndt_hasil', 'butir', 'alasan']),
            'target' => $riksa->alat ? $this->ringkasAlat($riksa->alat) : ($riksa->gear ? $this->ringkasGear($riksa->gear) : null),
            'ukurLabel' => Katalog::UKUR, 'uji' => Katalog::UJI[$riksa->kategori_uji] ?? null,
            'riwayat' => Riksa::query()->where($riksa->alat_id ? 'alat_id' : 'gear_id', $riksa->alat_id ?? $riksa->gear_id)
                ->orderByDesc('diperiksa_at')->orderByDesc('id')->take(12)->get()->map(fn (Riksa $r) => $this->ringkasRiksa($r))->all(),
        ]);
    }

    /* ═══════════ ekspor ═══════════ */

    public function csv(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $baris = Rencana::query()->when($c, fn ($q) => $q->where('company_id', $c))->with('kajian')->orderBy('kode')->get();
        $kolom = ['kode', 'judul', 'jenis_beban', 'klasifikasi', 'alat', 'lokasi', 'jadwal', 'berat_bersih_t', 'berat_kotor_t', 'radius_m',
                  'kapasitas_t', 'utilisasi_pct', 'tegangan_sling_t', 'tekanan_tanah_kn_m2', 'putusan_simulasi', 'skor_simulasi', 'skor_kajian', 'status', 'disetujui_oleh'];

        return response()->streamDownload(function () use ($baris, $kolom) {
            $f = fopen('php://output', 'w');
            fputcsv($f, $kolom);
            foreach ($baris as $r) {
                fputcsv($f, [$r->kode, $r->judul, $r->kategori_beban, $r->klasifikasi, $r->jenis_alat, $r->lokasi, $r->jadwal_at?->format('Y-m-d H:i'),
                    $r->berat_bersih_t, $r->berat_kotor_t, $r->radius_m, $r->kapasitas_t, $r->utilisasi_pct, $r->tegangan_maks_t, $r->tekanan_tanah,
                    $r->putusan, $r->skor, $r->kajian?->skor, $r->status, $r->disetujui_oleh]);
            }
            fclose($f);
        }, 'rencana-pengangkatan-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ═══════════ bantuan ═══════════ */

    private const ORANG = Rencanakan::ORANG;

    /** Isi kolom rencana dari masukan + simulasi. */
    private function isi(Rencana $r, array $data, ?Alat $alat): void
    {
        $r->fill(Rencanakan::kolom($data, $alat));
    }

    /**
     * Crane dan alat bantu yang dipilih harus milik perusahaan yang sama
     * dan LAYAK DIGUNAKAN. Yang dikarantina atau lewat masa uji ditolak
     * di sini — bukan sekadar diberi peringatan.
     */
    private function periksaRegister(array $data): array
    {
        $alat = null;
        if (! empty($data['alat_id'])) {
            $alat = Alat::find($data['alat_id']);
            if (! $alat || (! empty($data['company_id']) && (int) $alat->company_id !== (int) $data['company_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages(['alat_id' => 'Crane tidak ditemukan di register perusahaan ini.']);
            }
            if (! $alat->bolehDipakai()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['alat_id' => $alat->kode.' '.($alat->statusEfektif() === 'karantina' ? 'dikarantina' : 'SUKET-nya kedaluwarsa').' — tidak layak digunakan.']);
            }
        }
        $gear = Gear::whereIn('id', $data['gear'] ?? [])->get();
        if ($gear->count() !== count(array_unique($data['gear'] ?? []))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['gear' => 'Sebagian alat bantu tidak ditemukan di register.']);
        }
        $tolak = $gear->reject(fn (Gear $g) => $g->bolehDipakai() && (empty($data['company_id']) || (int) $g->company_id === (int) $data['company_id']));
        if ($tolak->isNotEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['gear' => $tolak->map(fn ($g) => $g->kode.' ('.$g->statusEfektif().')')->implode(', ').' tidak layak digunakan — pilih alat bantu lain.']);
        }

        return [$alat, $gear];
    }

    /** Alasan rencana belum boleh diajukan/disetujui. Kosong berarti boleh. */
    private function hambatan(Rencana $r, Kajian $k): array
    {
        $h = [];
        if ($r->putusan === 'nogo') $h[] = 'Simulasi NO-GO — ubah radius, crane, atau rigging.';
        if ($k->putusan === 'tidak_memenuhi') $h[] = 'Kajian 6 pilar tidak memenuhi.';
        $alat = $r->alat_id ? Alat::withoutGlobalScopes()->find($r->alat_id) : null;
        if ($alat && ! $alat->bolehDipakai()) $h[] = $alat->kode.' tidak layak digunakan ('.$alat->statusEfektif().').';
        $gear = Gear::withoutGlobalScopes()->whereIn('id', $r->gear ?? [])->get()->reject->bolehDipakai();
        if ($gear->isNotEmpty()) $h[] = 'Alat bantu tidak layak digunakan: '.$gear->pluck('kode')->implode(', ').'.';
        if ($r->klasifikasi === 'kritis' && ($k->manual === null || count(array_filter($k->manual)) < collect(Katalog::PILAR)->flatMap(fn ($p) => $p['butir'])->where('oto', false)->count())) {
            $h[] = 'Pengangkatan kritis: seluruh butir kajian yang dinilai peninjau wajib diisi.';
        }

        return $h;
    }

    private function register(?int $c, bool $semua = false): array
    {
        $alat = Alat::query()->when($c, fn ($q) => $q->where('company_id', $c))->orderBy('kode')->get();
        $gear = Gear::query()->when($c, fn ($q) => $q->where('company_id', $c))->orderBy('jenis')->orderBy('kode')->get();

        return [
            'alat' => $alat->map(fn (Alat $a) => $this->ringkasAlat($a) + ['spek' => $a->spek()])->filter(fn ($a) => $semua || $a['boleh'])->values()->all(),
            'alatTolak' => $alat->reject->bolehDipakai()->count(),
            'gear' => $gear->map(fn (Gear $g) => $this->ringkasGear($g))->filter(fn ($g) => $semua || $g['boleh'])->values()->all(),
            'gearTolak' => $gear->reject->bolehDipakai()->count(),
        ];
    }

    private function ringkas(Rencana $r): array
    {
        return [
            'id' => $r->id, 'kode' => $r->kode, 'judul' => $r->judul, 'lokasi' => $r->lokasi, 'area' => $r->area,
            'kategori_beban' => $r->kategori_beban, 'beban_label' => $r->labelBeban(),
            'klasifikasi' => $r->klasifikasi, 'jenis_alat' => $r->jenis_alat, 'alat_label' => Katalog::ALAT[$r->jenis_alat]['label'] ?? $r->jenis_alat,
            'jadwal_at' => $r->jadwal_at?->toIso8601String(),
            'berat_bersih_t' => $r->berat_bersih_t, 'berat_kotor_t' => $r->berat_kotor_t, 'radius_m' => $r->radius_m,
            'kapasitas_t' => $r->kapasitas_t, 'utilisasi_pct' => $r->utilisasi_pct, 'tegangan_maks_t' => $r->tegangan_maks_t,
            'tekanan_tanah' => $r->tekanan_tanah, 'putusan' => $r->putusan, 'skor' => $r->skor,
            'status' => $r->status, 'status_label' => Katalog::STATUS[$r->status] ?? $r->status,
            'kajian_skor' => $r->relationLoaded('kajian') ? $r->kajian?->skor : null,
            'kajian_putusan' => $r->relationLoaded('kajian') ? $r->kajian?->putusan : null,
        ];
    }

    private function ringkasAlat(Alat $a): array
    {
        return [
            'id' => $a->id, 'kode' => $a->kode, 'nama' => $a->nama, 'jenis' => $a->jenis, 'jenis_label' => $a->labelJenis(),
            'pabrikan' => $a->pabrikan, 'model' => $a->model, 'tahun' => $a->tahun, 'kapasitas_t' => $a->kapasitas_t,
            'boom_maks_m' => $a->boom_maks_m, 'lokasi' => $a->lokasi, 'suket_nomor' => $a->suket_nomor,
            'suket_berlaku' => $a->suket_berlaku?->toDateString(), 'operator_nama' => $a->operator_nama, 'operator_sio' => $a->operator_sio,
            'sio_berlaku' => $a->sio_berlaku?->toDateString(),
            'status' => $a->statusEfektif(), 'boleh' => $a->bolehDipakai(),
        ];
    }

    private function ringkasGear(Gear $g): array
    {
        return [
            'id' => $g->id, 'kode' => $g->kode, 'nama' => $g->nama, 'jenis' => $g->jenis, 'jenis_label' => $g->labelJenis(),
            'wll_t' => $g->wll_t, 'sf' => $g->sf, 'diameter_mm' => $g->diameter_mm, 'panjang_m' => $g->panjang_m, 'jumlah_kaki' => $g->jumlah_kaki,
            'lokasi_simpan' => $g->lokasi_simpan, 'kode_warna' => $g->kode_warna,
            'uji_terakhir' => $g->uji_terakhir?->toDateString(), 'uji_berikut' => $g->uji_berikut?->toDateString(),
            'status' => $g->statusEfektif(), 'boleh' => $g->bolehDipakai(),
        ];
    }

    private function ringkasRiksa(Riksa $r): array
    {
        $t = $r->relationLoaded('alat') && $r->alat ? $r->alat : ($r->relationLoaded('gear') ? $r->gear : null);

        return [
            'id' => $r->id, 'nomor' => $r->nomor, 'objek' => $r->objek, 'kategori_uji' => $r->kategori_uji,
            'kategori_label' => Katalog::UJI[$r->kategori_uji]['label'] ?? $r->kategori_uji,
            'jenis' => $r->jenis, 'jenis_label' => Katalog::JENIS_RIKSA[$r->jenis] ?? $r->jenis,
            'target_kode' => $t?->kode, 'target_nama' => $t?->nama, 'target_id' => $r->alat_id ?? $r->gear_id,
            'pemeriksa' => $r->pemeriksa, 'swl_t' => $r->swl_t, 'beban_statis_t' => $r->beban_statis_t,
            'putusan' => $r->putusan, 'kode_warna' => $r->kode_warna, 'alasan' => $r->alasan,
            'diperiksa_at' => $r->diperiksa_at?->toDateString(), 'berlaku_sampai' => $r->berlaku_sampai?->toDateString(),
        ];
    }

    private function kodeBaru(?int $c): string
    {
        $th = now()->format('Y');
        $n = Rencana::withoutGlobalScopes()->where('company_id', $c)->where('kode', 'like', "LP-{$th}-%")->count() + 1;
        do {
            $kode = sprintf('LP-%s-%03d', $th, $n++);
        } while (Rencana::withoutGlobalScopes()->where('company_id', $c)->where('kode', $kode)->exists());

        return $kode;
    }

    private function kodeAlat(?int $c, string $kategori): string
    {
        $awal = ['mobile' => 'CRN-MOB', 'crawler' => 'CRN-CRW', 'overhead' => 'OHC', 'truk' => 'CRN-TRK', 'tandem' => 'CRN-TDM', 'hoist' => 'HST'][$kategori] ?? 'PAA';
        $n = Alat::withoutGlobalScopes()->where('company_id', $c)->where('kode', 'like', $awal.'-%')->count() + 1;
        do {
            $kode = sprintf('%s-%02d', $awal, $n++);
        } while (Alat::withoutGlobalScopes()->where('company_id', $c)->where('kode', $kode)->exists());

        return $kode;
    }

    private function aturanMasukan(bool $pratinjau = false): array
    {
        $w = $pratinjau ? 'sometimes' : 'required';

        return [
            'jenis_alat' => [$w, Rule::in(array_keys(Katalog::ALAT))],
            'kategori_beban' => [$w, Rule::in(array_keys(Katalog::BEBAN))],
            'tanah' => [$w, Rule::in(array_keys(Katalog::TANAH))],
            'berat_bersih_t' => [$w, 'numeric', 'min:0.1', 'max:1000'],
            'berat_rigging_t' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'daf' => ['nullable', 'numeric', 'min:1', 'max:2'],
            'radius_m' => [$w, 'numeric', 'min:1', 'max:150'],
            'boom_m' => [$w, 'numeric', 'min:3', 'max:200'],
            'tinggi_rintangan_m' => ['nullable', 'numeric', 'min:0', 'max:150'],
            'tinggi_beban_m' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'tinggi_rigging_m' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'angin_kmh' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'luas_mat_m2' => ['nullable', 'numeric', 'min:0.5', 'max:100'],
            'kemiringan_pct' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'jarak_sutt_m' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'jumlah_kaki' => ['nullable', 'integer', 'min:1', 'max:4'],
            'sudut_sling' => ['nullable', 'numeric', 'min:15', 'max:90'],
            'jenis_sling' => ['nullable', Rule::in(['wire_rope', 'rantai', 'webbing'])],
            'hitch' => ['nullable', Rule::in(array_keys(Katalog::HITCH))],
            'cog_offset_pct' => ['nullable', 'numeric', 'min:0', 'max:35'],
            'wll_sling_t' => ['nullable', 'numeric', 'min:0.5', 'max:1000'],
            'wll_shackle_t' => ['nullable', 'numeric', 'min:0.5', 'max:1000'],
            'spreader' => ['boolean'], 'blind_lift' => ['boolean'], 'di_atas_fasilitas' => ['boolean'],
            'alat_id' => ['nullable', 'integer'],
            'gear' => ['array'], 'gear.*' => ['integer'],
        ];
    }

    private function aturan(): array
    {
        return $this->aturanMasukan() + [
            'company_id' => ['nullable', 'exists:companies,id'],
            'judul' => ['required', 'string', 'max:180'],
            'lokasi' => ['required', 'string', 'max:150'],
            'area' => ['nullable', 'string', 'max:60'],
            'jadwal_at' => ['nullable', 'date'],
            'operator_nama' => ['nullable', 'string', 'max:120'], 'operator_sio' => ['nullable', 'string', 'max:80'],
            'rigger_nama' => ['nullable', 'string', 'max:120'], 'rigger_sertifikat' => ['nullable', 'string', 'max:80'],
            'signalman_nama' => ['nullable', 'string', 'max:120'], 'pengawas_nama' => ['nullable', 'string', 'max:120'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
