<?php

namespace App\Http\Controllers\Perancah;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Energi\BantuEnergi;
use App\Models\ActivityLog;
use App\Models\Perancah\{Inspeksi, Kajian, Tag, Unit};
use App\Support\Perancah\{Kalkulator, Katalog, Kelayakan, Penanda};
use App\Support\Perusahaan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Scaffolding Management — perancah dari rancangan sampai dibongkar.
 *
 * Alurnya satu arah dan setiap langkah meninggalkan jejak: spesifikasi &
 * lokasi → kalkulator BoM & beban → kajian kelayakan lima kriteria →
 * inspeksi 16 butir → scafftag yang warnanya diputuskan sistem. Tidak
 * ada tombol yang membuat tag hijau; satu-satunya jalan ke hijau adalah
 * kajian layak DAN inspeksi yang lulus penuh.
 */
class PerancahController extends Controller
{
    use BantuEnergi;

    /* ═══════════ dasbor ═══════════ */

    public function index(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $unit = $this->unitAktif($c);
        $sekarang = now();

        $status = $unit->groupBy(fn (Unit $u) => $u->statusEfektif())->map->count();
        $aktif = $unit->count();
        $layakPakai = ($status['hijau'] ?? 0) + ($status['kuning'] ?? 0);

        $jatuhTempo = $unit->filter(fn (Unit $u) => in_array($u->statusEfektif(), ['hijau', 'kuning'], true)
            && $u->inspeksi_berikut && $u->inspeksi_berikut->lte($sekarang->copy()->addHours(48)))->count();

        $insp = Inspeksi::query()->when($c, fn ($q) => $q->where('company_id', $c))
            ->where('diperiksa_at', '>=', $sekarang->copy()->subWeeks(12)->startOfWeek())->get();
        $insp30 = $insp->where('diperiksa_at', '>=', $sekarang->copy()->subDays(30));
        $tolak = $insp30->count() ? round($insp30->where('putusan', 'bahaya')->count() / $insp30->count() * 100, 1) : null;

        $sf = $unit->map(fn (Unit $u) => $u->hitung?->faktor_keamanan)->filter();

        // Tren 12 minggu: jumlah inspeksi & persen lulus penuh.
        $tren = [];
        for ($i = 11; $i >= 0; $i--) {
            $awal = $sekarang->copy()->subWeeks($i)->startOfWeek();
            $akhir = $awal->copy()->endOfWeek();
            $m = $insp->filter(fn ($x) => $x->diperiksa_at->between($awal, $akhir));
            $tren[] = ['label' => $awal->translatedFormat('d M'), 'jumlah' => $m->count(),
                       'aman' => $m->where('putusan', 'aman')->count(), 'bahaya' => $m->where('putusan', 'bahaya')->count(),
                       'lulus' => $m->count() ? round($m->where('putusan', 'aman')->count() / $m->count() * 100, 1) : null];
        }

        // Pareto butir gagal 90 hari.
        $gagal = [];
        foreach ($insp->where('diperiksa_at', '>=', $sekarang->copy()->subDays(90)) as $x) {
            foreach ($x->hasil as $b) {
                if (($b['status'] ?? null) === 'gagal') $gagal[$b['kode']] = ($gagal[$b['kode']] ?? 0) + 1;
            }
        }
        arsort($gagal);
        $label = collect(Katalog::PERIKSA)->keyBy('kode');
        $pareto = collect($gagal)->take(8)->map(fn ($n, $k) => ['kode' => $k, 'label' => $label[$k]['label'] ?? $k,
            'kritis' => (bool) ($label[$k]['kritis'] ?? false), 'jumlah' => $n])->values()->all();

        // Per zona, per status efektif.
        $zona = $unit->groupBy('zona')->map(fn (Collection $g, $z) => [
            'zona' => $z, 'total' => $g->count(),
            'hijau' => $g->filter(fn ($u) => $u->statusEfektif() === 'hijau')->count(),
            'kuning' => $g->filter(fn ($u) => $u->statusEfektif() === 'kuning')->count(),
            'merah' => $g->filter(fn ($u) => in_array($u->statusEfektif(), ['merah', 'kedaluwarsa'], true))->count(),
        ])->sortByDesc('total')->values()->all();

        $perJenis = $unit->groupBy('jenis')->map(fn ($g, $j) => ['label' => Katalog::JENIS[$j]['label'] ?? $j, 'nilai' => $g->count()])->values()->all();

        // Material terpasang — inventaris di lapangan saat ini.
        $material = [
            'pipa_m' => round($unit->sum(fn ($u) => $u->hitung?->panjang_pipa_m ?? 0)),
            'plank' => (int) $unit->sum(fn ($u) => $u->hitung?->plank ?? 0),
            'coupler' => (int) $unit->sum(fn ($u) => $u->hitung?->coupler ?? 0),
            'berat_t' => round($unit->sum(fn ($u) => $u->hitung?->beban_mati_kg ?? 0) / 1000, 1),
            'luas_dek' => round($unit->sum(fn ($u) => $u->hitung?->luas_dek_m2 ?? 0)),
        ];

        $perlu = $unit->filter(fn (Unit $u) => in_array($u->statusEfektif(), ['merah', 'kedaluwarsa'], true)
                || ($u->inspeksi_berikut && $u->inspeksi_berikut->lte($sekarang->copy()->addHours(48))))
            ->sortBy(fn (Unit $u) => [$u->statusEfektif() === 'kedaluwarsa' ? 0 : ($u->statusEfektif() === 'merah' ? 1 : 2), $u->inspeksi_berikut?->timestamp ?? 0])
            ->take(8)->map(fn (Unit $u) => $this->ringkas($u))->values()->all();

        return Inertia::render('Perancah/Dasbor', [
            'ringkas' => [
                'aktif' => $aktif, 'hijau' => $status['hijau'] ?? 0, 'kuning' => $status['kuning'] ?? 0,
                'merah' => $status['merah'] ?? 0, 'kedaluwarsa' => $status['kedaluwarsa'] ?? 0,
                'kepatuhan' => $aktif ? round($layakPakai / $aktif * 100, 1) : null,
                'jatuh_tempo' => $jatuhTempo, 'tolak_30' => $tolak, 'inspeksi_30' => $insp30->count(),
                'sf_rata' => $sf->count() ? round($sf->avg(), 2) : null, 'sf_min' => $sf->count() ? round($sf->min(), 2) : null,
                'tinggi_maks' => $unit->max('tinggi_m'), 'perlu_pe' => $unit->filter(fn ($u) => $u->kajianTerakhir?->perlu_pe)->count(),
                'dibongkar_bulan' => Unit::query()->when($c, fn ($q) => $q->where('company_id', $c))
                    ->where('status_tag', 'dibongkar')->where('dibongkar_at', '>=', $sekarang->copy()->startOfMonth())->count(),
            ],
            'tren' => $tren, 'pareto' => $pareto, 'zona' => $zona, 'perJenis' => $perJenis,
            'material' => $material, 'perlu' => $perlu,
            'titik' => $unit->map(fn (Unit $u) => $this->ringkas($u))->values()->all(),
            'perusahaan' => $c, 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    /* ═══════════ daftar & peta ═══════════ */

    public function peta(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $semua = Unit::query()->when($c, fn ($q) => $q->where('company_id', $c))
            ->with(['hitung', 'kajianTerakhir', 'inspeksiTerakhir'])->orderBy('kode')->get();

        return Inertia::render('Perancah/Peta', [
            'unit' => $semua->map(fn (Unit $u) => $this->ringkas($u))->values()->all(),
            'zona' => array_values(array_unique([...Katalog::ZONA, ...$semua->pluck('zona')->all()])),
            'saring' => $request->only(['status', 'zona', 'q']),
            'perusahaan' => $c, 'daftarPerusahaan' => $this->pilihanPerusahaan($request),
        ]);
    }

    /* ═══════════ kalkulator & spesifikasi ═══════════ */

    public function baru(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $u = new Unit([
            'jenis' => 'ringlock', 'kelas_beban' => 'sedang', 'panjang_m' => 6, 'lebar_m' => 1.8, 'tinggi_m' => 8,
            'jumlah_dek' => 1, 'zona' => Katalog::ZONA[0], 'jenis_tanah' => 'beton', 'jumlah_ikatan' => 2,
            'angin_kmh' => 20, 'ada_guardrail' => true, 'ada_toeboard' => true, 'ada_outrigger' => false,
            'tanggal_pasang' => now()->toDateString(),
        ]);

        return Inertia::render('Perancah/Form', [
            'unit' => $this->isianForm($u), 'kodeBerikut' => $this->kodeBaru($c),
            'pusat' => $this->pusatPeta($c), 'pilihan' => Katalog::pilihan(),
        ]);
    }

    public function ubah(Unit $unit)
    {
        abort_if($unit->status_tag === 'dibongkar', 403, 'Perancah yang sudah dibongkar tidak dapat diubah.');

        return Inertia::render('Perancah/Form', [
            'unit' => $this->isianForm($unit), 'kodeBerikut' => $unit->kode,
            'pusat' => $this->pusatPeta($unit->company_id), 'pilihan' => Katalog::pilihan(),
        ]);
    }

    /** Pratinjau langsung untuk form: hitungan + kajian, tanpa menyimpan. */
    public function hitung(Request $request)
    {
        $s = $request->validate($this->aturanSpek(true));
        $h = Kalkulator::hitung($s);

        return response()->json(['hitung' => $h, 'kajian' => Kelayakan::nilai($s, $h)]);
    }

    public function simpan(Request $request)
    {
        $data = $this->pemilik($request->validate($this->aturan()));
        $u = $request->user();
        if ($u->isAdmin() && empty($data['company_id'])) {
            $data['company_id'] = Perusahaan::terpilih($u) ?? $u->company_id;
        }
        $data['user_id'] = $u->id;
        $data['kode'] = $this->kodeBaru($data['company_id'] ?? null);

        $unit = Unit::create($data);
        Penanda::kaji($unit, $u->name);
        Penanda::terbitkan($unit->fresh(), $unit->kajianTerakhir()->first(), null, 'Pendaftaran perancah');
        ActivityLog::write('Daftarkan perancah', $unit->kode.' — '.$unit->judul, 'perancah');

        return redirect()->route('perancah.rincian', $unit)->with('ok',
            'Perancah '.$unit->kode.' terdaftar dengan tag MERAH sampai inspeksi awal dilakukan.');
    }

    public function perbarui(Request $request, Unit $unit)
    {
        abort_if($unit->status_tag === 'dibongkar', 403, 'Perancah yang sudah dibongkar tidak dapat diubah.');

        $data = $request->validate($this->aturan());
        unset($data['company_id']);
        $unit->update($data);

        /* Spesifikasi berubah berarti perancah yang dikaji kemarin bukan
           perancah yang berdiri hari ini. Kajian dibuat ulang dan tagnya
           kembali merah sampai diinspeksi lagi — inspeksi sesudah
           modifikasi adalah kewajiban, bukan pilihan. */
        $kaj = Penanda::kaji($unit, $request->user()->name);
        Penanda::terbitkan($unit->fresh(), $kaj, null, 'Modifikasi spesifikasi');
        ActivityLog::write('Ubah perancah', $unit->kode, 'perancah');

        return redirect()->route('perancah.rincian', $unit)->with('ok',
            'Spesifikasi diperbarui dan dikaji ulang. Tag kembali MERAH sampai inspeksi sesudah modifikasi.');
    }

    /* ═══════════ rincian ═══════════ */

    public function rincian(Unit $unit)
    {
        $unit->load(['hitung', 'kajianTerakhir', 'tagAktif']);

        return Inertia::render('Perancah/Rincian', [
            'unit' => $this->ringkas($unit) + [
                'panjang_m' => $unit->panjang_m, 'lebar_m' => $unit->lebar_m, 'tinggi_m' => $unit->tinggi_m,
                'jumlah_dek' => $unit->jumlah_dek, 'elevasi_m' => $unit->elevasi_m,
                'jenis_tanah' => Katalog::TANAH[$unit->jenis_tanah]['label'] ?? $unit->jenis_tanah,
                'jumlah_ikatan' => $unit->jumlah_ikatan, 'angin_kmh' => $unit->angin_kmh,
                'ada_guardrail' => $unit->ada_guardrail, 'ada_toeboard' => $unit->ada_toeboard, 'ada_outrigger' => $unit->ada_outrigger,
                'kontraktor' => $unit->kontraktor, 'scaffolder_nama' => $unit->scaffolder_nama,
                'scaffolder_sertifikat' => $unit->scaffolder_sertifikat,
                'tanggal_pasang' => $unit->tanggal_pasang?->toDateString(), 'rencana_bongkar' => $unit->rencana_bongkar?->toDateString(),
                'catatan' => $unit->catatan,
            ],
            'hitung' => $unit->hitung,
            'kajian' => $unit->kajianTerakhir,
            'tag' => $unit->tagAktif ? $this->tagUntukTampil($unit->tagAktif, $unit) : null,
            'inspeksi' => Inspeksi::where('unit_id', $unit->id)->orderByDesc('diperiksa_at')->take(20)->get()
                ->map(fn (Inspeksi $i) => ['id' => $i->id, 'jenis' => Katalog::JENIS_INSPEKSI[$i->jenis] ?? $i->jenis,
                    'inspektur' => $i->inspektur, 'sertifikat' => $i->inspektur_sertifikat, 'putusan' => $i->putusan,
                    'lulus' => $i->lulus, 'gagal_kritis' => $i->gagal_kritis, 'gagal_minor' => $i->gagal_minor,
                    'diperiksa_at' => $i->diperiksa_at?->toIso8601String(), 'berlaku_sampai' => $i->berlaku_sampai?->toIso8601String(),
                    'syarat' => $i->syarat_khusus, 'perbaikan' => $i->tindakan_perbaikan,
                    'gagal' => collect($i->hasil)->where('status', 'gagal')->pluck('label')->values()->all()])->all(),
            'riwayatTag' => Tag::where('unit_id', $unit->id)->orderByDesc('terbit_at')->orderByDesc('id')->take(20)->get()
                ->map(fn (Tag $t) => ['kode' => $t->kode, 'warna' => $t->warna, 'label' => $t->label, 'aktif' => $t->aktif,
                    'terbit_at' => $t->terbit_at?->toIso8601String(), 'kedaluwarsa_at' => $t->kedaluwarsa_at?->toIso8601String(),
                    'oleh' => $t->terbit_oleh])->all(),
            'riwayatKajian' => Kajian::where('unit_id', $unit->id)->orderByDesc('ditinjau_at')->orderByDesc('id')->take(10)->get(['id', 'putusan', 'skor', 'ditinjau_at', 'peninjau']),
        ]);
    }

    public function kajiUlang(Request $request, Unit $unit)
    {
        abort_if($unit->status_tag === 'dibongkar', 403);
        $k = Penanda::kaji($unit, $request->user()->name);

        /* Kajian ulang yang berubah menjadi TIDAK LAYAK langsung
           menurunkan tag aktif; yang tetap layak tidak menyentuh tag
           yang sudah berlaku. */
        if ($k->putusan === 'tidak_layak' && $unit->status_tag !== 'merah') {
            Penanda::terbitkan($unit->fresh(), $k, null, $request->user()->name);
        }

        return back()->with('ok', 'Kajian kelayakan diperbarui: '.str_replace('_', ' ', $k->putusan).' (skor '.$k->skor.').');
    }

    public function bongkar(Request $request, Unit $unit)
    {
        abort_if($unit->status_tag === 'dibongkar', 403);
        Penanda::bongkar($unit);
        ActivityLog::write('Bongkar perancah', $unit->kode, 'perancah');

        return redirect()->route('perancah.peta')->with('ok', 'Perancah '.$unit->kode.' dicatat sudah dibongkar.');
    }

    /* ═══════════ inspeksi ═══════════ */

    public function formInspeksi(Unit $unit)
    {
        abort_if($unit->status_tag === 'dibongkar', 403, 'Perancah yang sudah dibongkar tidak dapat diinspeksi.');
        $unit->load(['hitung', 'kajianTerakhir', 'inspeksiTerakhir']);

        return Inertia::render('Perancah/Inspeksi', [
            'unit' => $this->ringkas($unit),
            'kajian' => $unit->kajianTerakhir?->only(['putusan', 'skor', 'rekomendasi', 'perlu_pe']),
            'terakhir' => $unit->inspeksiTerakhir ? collect($unit->inspeksiTerakhir->hasil)->pluck('status', 'kode') : null,
            'pilihan' => Katalog::pilihan(),
            'pemeriksa' => ['nama' => auth()->user()->name],
        ]);
    }

    public function simpanInspeksi(Request $request, Unit $unit)
    {
        abort_if($unit->status_tag === 'dibongkar', 403);

        $kode = array_column(Katalog::PERIKSA, 'kode');
        $data = $request->validate([
            'jenis' => ['required', Rule::in(array_keys(Katalog::JENIS_INSPEKSI))],
            'inspektur' => ['required', 'string', 'max:120'],
            'inspektur_sertifikat' => ['required', 'string', 'max:80'],
            'periksa' => ['required', 'array'],
            'periksa.*' => ['required', Rule::in(['lulus', 'gagal', 'na'])],
            'syarat_khusus' => ['nullable', 'string', 'max:1000'],
            'tindakan_perbaikan' => ['nullable', 'string', 'max:2000'],
        ]);

        // Seluruh butir wajib dinilai; butir kritis tidak boleh dilewati sebagai N/A kecuali yang memang bersyarat.
        $belum = array_diff($kode, array_keys($data['periksa']));
        if ($belum) return back()->withErrors(['periksa' => count($belum).' butir belum dinilai: '.implode(', ', $belum).'.']);
        if (($data['periksa']['INS-04'] ?? null) === 'na' && $unit->jenis === 'mobile') {
            return back()->withErrors(['periksa' => 'INS-04 wajib dinilai untuk mobile tower.']);
        }
        if (collect($data['periksa'])->contains('gagal') && blank($data['tindakan_perbaikan'] ?? null)) {
            return back()->withErrors(['tindakan_perbaikan' => 'Tuliskan tindakan perbaikan untuk butir yang gagal.']);
        }

        $tag = Penanda::inspeksi($unit, $data, $request->user()->id);
        ActivityLog::write('Inspeksi perancah', $unit->kode.' → '.$tag->kode, 'perancah');

        return redirect()->route('perancah.tag', $tag)->with('ok',
            'Inspeksi tersimpan. Scafftag '.$tag->kode.' terbit: '.$tag->label.'.');
    }

    /* ═══════════ tag ═══════════ */

    public function tag(Tag $tag)
    {
        $unit = Unit::with('hitung')->findOrFail($tag->unit_id);

        return Inertia::render('Perancah/Tag', [
            'tag' => $this->tagUntukTampil($tag, $unit),
            'unit' => $this->ringkas($unit),
            'inspeksi' => $tag->inspeksi_id ? Inspeksi::find($tag->inspeksi_id)?->only(['inspektur', 'inspektur_sertifikat', 'jenis', 'lulus', 'gagal_kritis', 'gagal_minor']) : null,
        ]);
    }

    /* ═══════════ ekspor ═══════════ */

    public function geojson(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $fitur = Unit::query()->when($c, fn ($q) => $q->where('company_id', $c))
            ->whereNotNull('lat')->whereNotNull('lng')->with('hitung')->orderBy('kode')->get()
            ->map(fn (Unit $u) => ['type' => 'Feature',
                'geometry' => ['type' => 'Point', 'coordinates' => [$u->lng, $u->lat, $u->elevasi_m]],
                'properties' => collect($this->ringkas($u))->except(['lat', 'lng'])->all()])->values();

        return response()->json(['type' => 'FeatureCollection', 'features' => $fitur], 200, [
            'Content-Disposition' => 'attachment; filename="perancah-'.now()->format('Ymd').'.geojson"',
        ]);
    }

    public function csv(Request $request)
    {
        $c = $this->perusahaanAktif($request);
        $baris = Unit::query()->when($c, fn ($q) => $q->where('company_id', $c))->with('hitung')->orderBy('kode')->get();
        $kolom = ['kode', 'judul', 'zona', 'peralatan', 'izin_kerja', 'jenis', 'kelas_beban', 'dimensi', 'status', 'kode_tag',
                  'inspeksi_berikut', 'faktor_keamanan', 'rasio_hb', 'lat', 'lng', 'kontraktor'];

        return response()->streamDownload(function () use ($baris, $kolom) {
            $f = fopen('php://output', 'w');
            fputcsv($f, $kolom);
            foreach ($baris as $u) {
                fputcsv($f, [$u->kode, $u->judul, $u->zona, $u->peralatan_ref, $u->nomor_izin, $u->jenis, $u->kelas_beban,
                    $u->dimensi(), $u->statusEfektif(), $u->kode_tag, $u->inspeksi_berikut?->format('Y-m-d H:i'),
                    $u->hitung?->faktor_keamanan, $u->hitung?->rasio_hb, $u->lat, $u->lng, $u->kontraktor]);
            }
            fclose($f);
        }, 'perancah-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /* ═══════════ bantuan ═══════════ */

    /** @return Collection<int,Unit> */
    private function unitAktif(?int $c): Collection
    {
        return Unit::query()->when($c, fn ($q) => $q->where('company_id', $c))
            ->where('status_tag', '!=', 'dibongkar')
            ->with(['hitung', 'kajianTerakhir', 'inspeksiTerakhir'])->orderBy('kode')->get();
    }

    /** Bentuk ringkas satu unit untuk daftar, peta, dan kartu. */
    private function ringkas(Unit $u): array
    {
        return [
            'id' => $u->id, 'kode' => $u->kode, 'judul' => $u->judul, 'zona' => $u->zona,
            'peralatan' => $u->peralatan_ref, 'izin' => $u->nomor_izin,
            'jenis' => $u->jenis, 'jenis_label' => Katalog::JENIS[$u->jenis]['label'] ?? $u->jenis,
            'kelas' => $u->kelas_beban, 'swl' => $u->swl(), 'dimensi' => $u->dimensi(), 'tinggi' => $u->tinggi_m,
            'status' => $u->statusEfektif(), 'kode_tag' => $u->kode_tag,
            'inspeksi_berikut' => $u->inspeksi_berikut?->toIso8601String(),
            'sf' => $u->relationLoaded('hitung') ? $u->hitung?->faktor_keamanan : null,
            'hb' => $u->relationLoaded('hitung') ? $u->hitung?->rasio_hb : null,
            'putusan' => $u->relationLoaded('kajianTerakhir') ? $u->kajianTerakhir?->putusan : null,
            'perlu_pe' => $u->relationLoaded('kajianTerakhir') ? (bool) $u->kajianTerakhir?->perlu_pe : false,
            'inspeksi_terakhir' => $u->relationLoaded('inspeksiTerakhir') ? $u->inspeksiTerakhir?->diperiksa_at?->toIso8601String() : null,
            'lat' => $u->lat, 'lng' => $u->lng, 'kontraktor' => $u->kontraktor,
        ];
    }

    private function tagUntukTampil(Tag $t, Unit $u): array
    {
        return [
            'kode' => $t->kode, 'warna' => $t->kedaluwarsa() ? 'merah' : $t->warna,
            'label' => $t->kedaluwarsa() ? Penanda::LABEL['kedaluwarsa'] : $t->label,
            'kedaluwarsa' => $t->kedaluwarsa(), 'aktif' => $t->aktif,
            'swl' => $t->swl_kg_m2, 'syarat' => $t->syarat_khusus, 'alasan' => $t->alasan_tolak,
            'oleh' => $t->terbit_oleh, 'terbit_at' => $t->terbit_at?->toIso8601String(),
            'kedaluwarsa_at' => $t->kedaluwarsa_at?->toIso8601String(),
            'verifikasi' => route('perancah.verifikasi', $t->token),
            'unit' => ['kode' => $u->kode, 'judul' => $u->judul, 'zona' => $u->zona, 'peralatan' => $u->peralatan_ref,
                       'izin' => $u->nomor_izin, 'dimensi' => $u->dimensi(), 'jenis' => Katalog::JENIS[$u->jenis]['label'] ?? $u->jenis,
                       'dek' => $u->jumlah_dek, 'lat' => $u->lat, 'lng' => $u->lng, 'kontraktor' => $u->kontraktor],
        ];
    }

    private function isianForm(Unit $u): array
    {
        return $u->only([
            'id', 'judul', 'jenis', 'kelas_beban', 'panjang_m', 'lebar_m', 'tinggi_m', 'jumlah_dek', 'zona', 'peralatan_ref',
            'nomor_izin', 'lat', 'lng', 'elevasi_m', 'jenis_tanah', 'jumlah_ikatan', 'angin_kmh', 'ada_guardrail',
            'ada_toeboard', 'ada_outrigger', 'kontraktor', 'scaffolder_nama', 'scaffolder_sertifikat', 'catatan',
        ]) + ['tanggal_pasang' => $u->tanggal_pasang?->toDateString(), 'rencana_bongkar' => $u->rencana_bongkar?->toDateString()];
    }

    /** Titik tengah peta: rata-rata unit yang ada, atau titik bawaan. */
    private function pusatPeta(?int $c): array
    {
        $q = Unit::query()->when($c, fn ($q) => $q->where('company_id', $c))->whereNotNull('lat');
        return $q->exists() ? ['lat' => round((float) $q->avg('lat'), 6), 'lng' => round((float) $q->avg('lng'), 6)]
                            : ['lat' => -2.5, 'lng' => 115.5];
    }

    private function kodeBaru(?int $c): string
    {
        $th = now()->format('Y');
        $n = Unit::withoutGlobalScopes()->where('company_id', $c)->where('kode', 'like', "SCF-{$th}-%")->count() + 1;
        do {
            $kode = sprintf('SCF-%s-%03d', $th, $n++);
        } while (Unit::withoutGlobalScopes()->where('company_id', $c)->where('kode', $kode)->exists());

        return $kode;
    }

    private function aturanSpek(bool $pratinjau = false): array
    {
        $wajib = $pratinjau ? 'sometimes' : 'required';

        return [
            'jenis' => [$wajib, Rule::in(array_keys(Katalog::JENIS))],
            'kelas_beban' => [$wajib, Rule::in(array_keys(Katalog::KELAS))],
            'panjang_m' => [$wajib, 'numeric', 'min:0.6', 'max:200'],
            'lebar_m' => [$wajib, 'numeric', 'min:0.6', 'max:30'],
            'tinggi_m' => [$wajib, 'numeric', 'min:1', 'max:80'],
            'jumlah_dek' => [$wajib, 'integer', 'min:1', 'max:20'],
            'jenis_tanah' => [$wajib, Rule::in(array_keys(Katalog::TANAH))],
            'jumlah_ikatan' => ['nullable', 'integer', 'min:0', 'max:500'],
            'angin_kmh' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'ada_guardrail' => ['boolean'], 'ada_toeboard' => ['boolean'], 'ada_outrigger' => ['boolean'],
        ];
    }

    private function aturan(): array
    {
        return $this->aturanSpek() + [
            'company_id' => ['nullable', 'exists:companies,id'],
            'judul' => ['required', 'string', 'max:180'],
            'zona' => ['required', 'string', 'max:120'],
            'peralatan_ref' => ['nullable', 'string', 'max:80'],
            'nomor_izin' => ['nullable', 'string', 'max:60'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'elevasi_m' => ['nullable', 'numeric', 'min:-500', 'max:5000'],
            'kontraktor' => ['nullable', 'string', 'max:150'],
            'scaffolder_nama' => ['nullable', 'string', 'max:120'],
            'scaffolder_sertifikat' => ['nullable', 'string', 'max:80'],
            'tanggal_pasang' => ['nullable', 'date'],
            'rencana_bongkar' => ['nullable', 'date', 'after_or_equal:tanggal_pasang'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
