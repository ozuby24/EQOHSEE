<?php

namespace App\Http\Controllers;

use App\Rules\DalamPerusahaan;

use App\Models\{ActivityLog, AngkutAlat, AngkutMuatan, AngkutRegu, Company, TindakLanjut};
use App\Support\{Alur, Angkutan, KopDokumen, PeringatanAngkutan};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Pengangkutan dan pengaturan armada (dispatch).
 *
 * Menjawab pertanyaan yang tidak dijawab Mine Operations: bukan berapa
 * yang terangkut, melainkan bagaimana. Tonase yang dicatat di sini
 * adalah bagian dari tonase pit, bukan tambahannya — keduanya sengaja
 * tidak pernah dijumlahkan menjadi satu angka.
 */
class DispatchController extends Controller
{
    public function index(Request $r)  { return $this->halaman($r, 'dashboard'); }
    public function regu(Request $r)   { return $this->halaman($r, 'regu'); }
    public function armada(Request $r) { return $this->halaman($r, 'armada'); }
    public function muatan(Request $r) { return $this->halaman($r, 'muatan'); }

    /* ---------- armada ---------- */

    public function simpanAlat(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id'          => ['nullable', 'exists:companies,id'],
            'kode'                => ['required', 'string', 'max:40'],
            'nama'                => ['nullable', 'string', 'max:150'],
            'kelas'               => ['required', Rule::in(AngkutAlat::KELAS)],
            'tipe'                => ['nullable', 'string', 'max:100'],
            'kapasitas_ton'       => ['nullable', 'numeric', 'min:0.1', 'max:2000'],
            'kapasitas_bucket_m3' => ['nullable', 'numeric', 'min:0.1', 'max:200'],
            'faktor_isi'          => ['nullable', 'numeric', 'min:0.1', 'max:1.5'],
            'ko_object_id'        => ['nullable', new DalamPerusahaan('ko_objects')],
            'catatan'             => ['nullable', 'string', 'max:2000'],
        ]));

        AngkutAlat::updateOrCreate(
            ['company_id' => $data['company_id'] ?? null, 'kode' => $data['kode']],
            $data + ['aktif' => true],
        );

        ActivityLog::write('Daftarkan unit armada angkut', $data['kode'], 'angkutan');

        return back()->with('ok', 'Unit armada tersimpan.');
    }

    public function hapusAlat(AngkutAlat $alat)
    {
        $alat->delete();

        return back()->with('ok', 'Unit dihapus.');
    }

    /* ---------- regu angkut ---------- */

    public function simpanRegu(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id'          => ['nullable', 'exists:companies,id'],
            'kode'                => ['required', 'string', 'max:40'],
            'tanggal'             => ['required', 'date'],
            'shift'               => ['required', Rule::in(AngkutRegu::SHIFT)],
            'pit'                 => ['nullable', 'string', 'max:100'],
            'tujuan'              => ['nullable', 'string', 'max:100'],
            'material'            => ['required', Rule::in(AngkutRegu::MATERIAL)],
            'alat_muat_id'        => ['nullable', 'exists:angkut_alats,id'],
            'jumlah_alat_muat'    => ['required', 'integer', 'min:1', 'max:20'],
            'jumlah_truk'         => ['required', 'integer', 'min:1', 'max:200'],
            'jarak_km'            => ['nullable', 'numeric', 'min:0', 'max:200'],

            'waktu_muat_menit'    => ['required', 'numeric', 'min:0.1', 'max:240'],
            'waktu_angkut_menit'  => ['required', 'numeric', 'min:0.1', 'max:600'],
            'waktu_tumpah_menit'  => ['nullable', 'numeric', 'min:0', 'max:240'],
            'waktu_kembali_menit' => ['required', 'numeric', 'min:0.1', 'max:600'],
            'waktu_antre_menit'   => ['nullable', 'numeric', 'min:0', 'max:600'],

            'ritase'              => ['required', 'integer', 'min:0', 'max:100000'],
            'tonase'              => ['required', 'numeric', 'min:0', 'max:10000000'],
            'jam_kerja'           => ['required', 'numeric', 'min:0', 'max:24'],
            'jam_delay'           => ['nullable', 'numeric', 'min:0', 'max:24'],
            'batas_kecepatan_kmh' => ['nullable', 'numeric', 'min:1', 'max:120'],
            'catatan'             => ['nullable', 'string', 'max:3000'],
        ]));

        // Jam kerja dan delay bersama-sama tidak boleh melebihi satu
        // shift penuh. Tanpa penjagaan ini, utilisasi terbaca wajar
        // padahal jam terjadwalnya mustahil.
        $terjadwal = $data['jam_kerja'] + ($data['jam_delay'] ?? 0);
        if ($terjadwal > 24) {
            return back()->withErrors([
                'jam_kerja' => 'Jam kerja ditambah jam delay melebihi 24 jam ('.round($terjadwal, 2).' jam).',
            ]);
        }

        // Alat muat yang ditunjuk harus benar-benar alat muat. Menunjuk
        // truk sebagai alat muat menghasilkan match factor yang terbaca
        // wajar dan tidak bermakna apa pun.
        if (!empty($data['alat_muat_id'])) {
            $alat = AngkutAlat::find($data['alat_muat_id']);
            if ($alat && $alat->kelas !== 'alat-muat') {
                return back()->withErrors([
                    'alat_muat_id' => 'Unit yang ditunjuk bukan alat muat.',
                ]);
            }
        }

        $data['user_id'] = auth()->id();
        $data['waktu_antre_menit'] ??= 0;
        $data['jam_delay'] ??= 0;

        $r = AngkutRegu::create($data);
        ActivityLog::write('Catat regu angkut', $r->kode, 'angkutan');

        return back()->with('ok', 'Catatan regu tersimpan sebagai draf.');
    }

    public function hapusRegu(AngkutRegu $regu)
    {
        if ($regu->sudahDisetujui()) {
            return back()->withErrors(['alur' => 'Catatan yang sudah disetujui tidak dapat dihapus.']);
        }

        $regu->delete();

        return back()->with('ok', 'Catatan regu dihapus.');
    }

    public function ajukan(AngkutRegu $regu)  { return $this->jalankan($regu, fn () => $regu->ajukan(), 'Ajukan regu angkut', 'Catatan diajukan untuk ditinjau.'); }
    public function setujui(AngkutRegu $regu) { return $this->jalankan($regu, fn () => $regu->setujui(), 'Setujui regu angkut', 'Catatan disetujui dan masuk hitungan KPI.'); }

    public function tolak(Request $request, AngkutRegu $regu)
    {
        $alasan = $request->validate(['alasan_tolak' => ['required', 'string', 'min:5', 'max:1000']])['alasan_tolak'];

        return $this->jalankan($regu, fn () => $regu->tolak($alasan),
            'Tolak regu angkut', 'Catatan ditolak dan dikembalikan kepada pengaju.');
    }

    /* ---------- penimbangan ---------- */

    public function simpanMuatan(Request $request, AngkutRegu $regu)
    {
        $data = $this->pemilik($request->validate([
            'company_id'     => ['nullable', 'exists:companies,id'],
            'angkut_alat_id' => ['required', 'exists:angkut_alats,id'],
            'rit_ke'         => ['nullable', 'integer', 'min:1', 'max:10000'],
            'muatan_ton'     => ['required', 'numeric', 'min:0.1', 'max:2000'],
            'waktu_timbang'  => ['nullable', 'date'],
            'sumber'         => ['nullable', 'string', 'max:80'],
        ]));

        $data['angkut_regu_id'] = $regu->id;

        $m = AngkutMuatan::create($data);
        $m->load('alat');

        // Penimbangan sengaja tetap dapat ditambahkan pada regu yang
        // sudah disetujui: ia bukan bagian dari angka yang ditinjau,
        // melainkan pembacaan alat yang menilai angka itu.
        return back()->with('ok', $m->melampauiPuncak()
            ? 'Penimbangan tersimpan. Muatan di atas 120% kapasitas langsung masuk ke peringatan.'
            : 'Penimbangan tersimpan.');
    }

    public function hapusMuatan(AngkutMuatan $muatan)
    {
        $muatan->delete();

        return back()->with('ok', 'Penimbangan dihapus.');
    }

    private function jalankan($baris, callable $aksi, string $peristiwa, string $pesan)
    {
        try {
            $aksi();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['alur' => $e->getMessage()]);
        }

        ActivityLog::write($peristiwa, (string) ($baris->kode ?? $baris->id ?? ''), 'angkutan');

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

        $data['modul'] = 'angkutan';
        $data['user_id'] = auth()->id();
        TindakLanjut::create($data);

        return back()->with('ok', 'Tindak lanjut ditambahkan.');
    }

    public function ubahTindakLanjut(Request $request, TindakLanjut $tindak)
    {
        $status = $request->validate(['status' => ['required', Rule::in(TindakLanjut::STATUS)]])['status'];

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
        $d = $this->kumpulkan($dari, $sampai);

        $perusahaan = $this->perusahaanKop();

        return Inertia::render('Print/Angkutan', [
            'dok'    => KopDokumen::untuk('laporan-angkutan', $perusahaan),
            'dari'   => $dari->toDateString(),
            'sampai' => $sampai->toDateString(),

            'ringkas'   => $d['ringkas'],
            'kepatuhan' => $d['kepatuhan'],
            'regu'      => $d['sah']->map(fn (AngkutRegu $r) => $r->toView())->values(),
            'alat'      => $d['alat']->map(fn (AngkutAlat $a) => $a->toView())->values(),
            'tindak'    => TindakLanjut::modul('angkutan')->terbukaSaja()->urutMendesak()->get()
                ->map(fn (TindakLanjut $t) => $t->toView())->values(),

            'kembali' => route('angkutan.index', ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()]),
        ]);
    }

    /* ---------- halaman ---------- */

    private function halaman(Request $request, string $mode)
    {
        [$dari, $sampai] = $this->rentang($request);
        $d = $this->kumpulkan($dari, $sampai);

        $tindak = TindakLanjut::with('sumber')->modul('angkutan')->urutMendesak()->get();

        return Inertia::render('Angkutan/Halaman', [
            'mode'   => $mode,
            'dari'   => $dari->toDateString(),
            'sampai' => $sampai->toDateString(),

            'ringkas'   => $d['ringkas'],
            'kepatuhan' => $d['kepatuhan'],
            'alerts'    => PeringatanAngkutan::susun($d['regu'], $d['muatan'], $d['alat']),

            'regu'   => $d['regu']->map(fn (AngkutRegu $r) => $r->toView())->values(),
            'alat'   => $d['alat']->map(fn (AngkutAlat $a) => $a->toView())->values(),
            'muatan' => $d['muatan']->map(fn (AngkutMuatan $m) => $m->toView())->values(),
            'tindak' => $tindak->map(fn (TindakLanjut $t) => $t->toView())->values(),
            'kodeDitangani' => $tindak->filter(fn (TindakLanjut $t) => $t->terbuka())
                ->pluck('kode_pemicu')->filter()->unique()->values(),

            'companies' => Company::query()
                ->when(!auth()->user()?->isAdmin(), fn ($q) => $q->whereKey(auth()->user()?->company_id))
                ->orderBy('name')->get(['id', 'name']),

            'opsi' => [
                'kelas'           => AngkutAlat::KELAS,
                'shift'           => AngkutRegu::SHIFT,
                'material'        => AngkutRegu::MATERIAL,
                'statusTindak'    => TindakLanjut::STATUS,
                'prioritasTindak' => TindakLanjut::PRIORITAS,
                'mf'              => ['bawah' => Angkutan::MF_BAWAH, 'atas' => Angkutan::MF_ATAS],
                'antreWajar'      => Angkutan::ANTRE_WAJAR_PERSEN,
                'muatan'          => [
                    'rata'   => Angkutan::PAYLOAD_RATA_MAKS,
                    'lebih'  => Angkutan::PAYLOAD_LEBIH,
                    'porsi'  => Angkutan::PAYLOAD_PORSI_MAKS,
                    'puncak' => Angkutan::PAYLOAD_PUNCAK,
                    'minimum'=> Angkutan::MIN_MUATAN_KEBIJAKAN,
                ],
            ],

            'tautan' => [
                'dashboard' => route('angkutan.index'),
                'regu'      => route('angkutan.regu'),
                'armada'    => route('angkutan.armada'),
                'muatan'    => route('angkutan.muatan'),
                'cetak'     => route('angkutan.cetak'),

                'alatSimpan' => route('angkutan.alat.simpan'),
                'alatHapus'  => route('angkutan.alat.hapus', ['alat' => '__ID__']),

                'reguSimpan' => route('angkutan.regu.simpan'),
                'reguHapus'  => route('angkutan.regu.hapus', ['regu' => '__ID__']),
                'reguAjukan' => route('angkutan.ajukan', ['regu' => '__ID__']),
                'reguSetujui'=> route('angkutan.setujui', ['regu' => '__ID__']),
                'reguTolak'  => route('angkutan.tolak', ['regu' => '__ID__']),

                'muatanSimpan' => route('angkutan.muatan.simpan', ['regu' => '__ID__']),
                'muatanHapus'  => route('angkutan.muatan.hapus', ['muatan' => '__ID__']),

                'tindakSimpan' => route('angkutan.tindak.simpan'),
                'tindakUbah'   => route('angkutan.tindak.ubah', ['tindak' => '__ID__']),
            ],
        ]);
    }

    private function kumpulkan(Carbon $dari, Carbon $sampai): array
    {
        $regu = AngkutRegu::with(['alatMuat', 'muatan.alat'])
            ->whereBetween('tanggal', [$dari, $sampai])
            ->orderByDesc('tanggal')->orderBy('shift')->get();

        $sah  = $regu->whereIn('status', Alur::terhitung());
        $alat = AngkutAlat::where('aktif', true)->orderBy('kelas')->orderBy('kode')->get();

        $muatan = AngkutMuatan::with(['alat', 'regu'])
            ->whereIn('angkut_regu_id', $regu->pluck('id'))
            ->orderByDesc('id')->get();

        return [
            'regu'      => $regu,
            'sah'       => $sah,
            'alat'      => $alat,
            'muatan'    => $muatan,
            'ringkas'   => $this->ringkas($regu, $sah),
            'kepatuhan' => $this->kepatuhan($muatan),
        ];
    }

    /**
     * Kepatuhan muatan seluruh rentang, dari seluruh penimbangan.
     *
     * Dinilai gabungan dan bukan per regu, sebab kaidah 10% adalah
     * pernyataan tentang sebaran — dan sebaran dari enam penimbangan
     * satu shift tidak dapat menjawabnya. Yang per regu tetap ada di
     * barisnya masing-masing, untuk menelusuri asalnya.
     */
    private function kepatuhan(Collection $muatan): array
    {
        $persen = [];
        foreach ($muatan as $m) {
            $p = $m->persen();
            if ($p !== null) $persen[] = $p;
        }

        return Angkutan::kepatuhanMuatan($persen, 100.0);
    }

    /**
     * @param Collection<int,AngkutRegu> $regu
     * @param Collection<int,AngkutRegu> $sah
     */
    private function ringkas(Collection $regu, Collection $sah): array
    {
        $mf = $sah->map(fn (AngkutRegu $r) => $r->matchFactor())->filter(fn ($x) => $x !== null);
        $antre = $sah->map(fn (AngkutRegu $r) => $r->porsiAntre())->filter(fn ($x) => $x !== null);

        $jamKerja = (float) $sah->sum('jam_kerja');
        $jamDelay = (float) $sah->sum('jam_delay');

        return [
            'regu'        => $regu->count(),
            'disetujui'   => $sah->count(),
            'menunggu'    => $regu->filter(fn (AngkutRegu $r) => $r->menungguTinjauan())->count(),
            'ritase'      => (int) $sah->sum('ritase'),
            'tonase'      => round((float) $sah->sum('tonase'), 2),
            'jamKerja'    => round($jamKerja, 2),
            'jamDelay'    => round($jamDelay, 2),
            'utilisasi'   => Angkutan::utilisasi($jamKerja, $jamDelay),
            'mfRata'      => $mf->isNotEmpty() ? round($mf->avg(), 3) : null,
            'antreRata'   => $antre->isNotEmpty() ? round($antre->avg(), 2) : null,
            'takSeimbang' => $sah->filter(function (AngkutRegu $r) {
                $kelas = Angkutan::bacaMatchFactor($r->matchFactor())['kelas'];

                return $kelas === 'lebih-truk' || $kelas === 'kurang-truk';
            })->count(),
            'hilangAntre' => round((float) $sah->sum(fn (AngkutRegu $r) => (float) $r->tonaseHilangAntre()), 2),
            'lampauiKecepatan' => $sah->filter(fn (AngkutRegu $r) => $r->melampauiBatasKecepatan())->count(),
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
