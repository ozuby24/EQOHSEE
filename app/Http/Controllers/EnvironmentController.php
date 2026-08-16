<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Company, LingkunganArea, LingkunganPantau, LingkunganParameter, ReklamasiKemajuan, TindakLanjut};
use App\Support\{Alur, KopDokumen, NeracaLahan, PeringatanLingkungan, Reklamasi};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Pengelolaan Lingkungan dan Reklamasi.
 *
 * Tahapan petak berpindah hanya lewat laporan kemajuan yang disetujui.
 * Menyediakan tombol untuk mengubah tahapan langsung akan membuat neraca
 * lahan bergeser tanpa ada yang meninjau dasarnya — dan neraca itulah
 * yang masuk ke laporan triwulan kepada inspektur tambang.
 */
class EnvironmentController extends Controller
{
    /** Biaya reklamasi per hektare, dipakai menghitung kecukupan jaminan. */
    private const BIAYA_PER_HA_BAWAAN = 30_000_000.0;

    public function index(Request $r)    { return $this->halaman($r, 'dashboard'); }
    public function lahan(Request $r)    { return $this->halaman($r, 'lahan'); }
    public function pemantauan(Request $r) { return $this->halaman($r, 'pemantauan'); }
    public function baku(Request $r)     { return $this->halaman($r, 'baku'); }

    /* ---------- petak lahan ---------- */

    public function simpanArea(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id'   => ['nullable', 'exists:companies,id'],
            'kode'         => ['required', 'string', 'max:40'],
            'nama'         => ['required', 'string', 'max:150'],
            'jenis'        => ['required', Rule::in(LingkunganArea::JENIS)],
            'luas_ha'      => ['required', 'numeric', 'min:0.0001', 'max:1000000'],
            'tanggal_buka' => ['nullable', 'date'],
            'tanggal_selesai_tambang'   => ['nullable', 'date', 'after_or_equal:tanggal_buka'],
            'rencana_selesai_reklamasi' => ['nullable', 'date'],
            'pohon_rencana' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'catatan'       => ['nullable', 'string', 'max:3000'],
        ]));

        $data['user_id'] = auth()->id();
        $area = LingkunganArea::create($data);
        ActivityLog::write('Daftarkan petak lahan', $area->kode.' — '.$area->nama, 'lingkungan');

        return back()->with('ok', 'Petak lahan tersimpan.');
    }

    public function ubahArea(Request $request, LingkunganArea $area)
    {
        // Tahapan sengaja tidak ada di sini: ia hanya berpindah lewat
        // laporan kemajuan yang disetujui.
        $area->update($request->validate([
            'nama'    => ['required', 'string', 'max:150'],
            'luas_ha' => ['required', 'numeric', 'min:0.0001', 'max:1000000'],
            'tanggal_selesai_tambang'   => ['nullable', 'date'],
            'rencana_selesai_reklamasi' => ['nullable', 'date'],
            'pohon_rencana' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'catatan'       => ['nullable', 'string', 'max:3000'],
        ]));

        return back()->with('ok', 'Petak diperbarui.');
    }

    public function hapusArea(LingkunganArea $area)
    {
        ActivityLog::write('Hapus petak lahan', $area->kode, 'lingkungan');
        $area->delete();

        return back()->with('ok', 'Petak dihapus.');
    }

    /* ---------- kemajuan reklamasi ---------- */

    public function simpanKemajuan(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id'         => ['nullable', 'exists:companies,id'],
            'lingkungan_area_id' => ['required', 'exists:lingkungan_areas,id'],
            'tanggal'            => ['required', 'date'],
            'tahap'              => ['required', Rule::in(array_keys(Reklamasi::TAHAP))],
            'luas_ha'            => ['required', 'numeric', 'min:0', 'max:1000000'],
            'pohon_ditanam'      => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'tingkat_tumbuh_persen' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'catatan'            => ['nullable', 'string', 'max:3000'],
        ]));

        $area = LingkunganArea::findOrFail($data['lingkungan_area_id']);

        // Melompati tahapan bukan percepatan: tanah pucuk yang ditebar di
        // atas lahan yang belum ditata akan tergerus pada hujan pertama.
        if (!Reklamasi::bolehKe($area->tahap, $data['tahap'])) {
            return back()->withErrors([
                'tahap' => 'Petak ini pada tahapan "'.(Reklamasi::TAHAP[$area->tahap] ?? $area->tahap)
                           .'". Tahapan berikutnya yang wajar adalah "'
                           .(Reklamasi::TAHAP[Reklamasi::berikutnya($area->tahap)] ?? '—').'".',
            ]);
        }

        // Luas yang dilaporkan tidak boleh melebihi luas petaknya sendiri.
        if ($data['luas_ha'] > $area->luas_ha) {
            return back()->withErrors([
                'luas_ha' => 'Luas yang dilaporkan melebihi luas petak ('.$area->luas_ha.' ha).',
            ]);
        }

        $data['user_id'] = auth()->id();
        $k = ReklamasiKemajuan::create($data);

        ActivityLog::write('Catat kemajuan reklamasi',
            $area->kode.' · '.(Reklamasi::TAHAP[$k->tahap] ?? $k->tahap), 'lingkungan');

        return back()->with('ok', 'Kemajuan tersimpan sebagai draf. Tahapan petak berpindah setelah disetujui.');
    }

    public function hapusKemajuan(ReklamasiKemajuan $kemajuan)
    {
        if ($kemajuan->sudahDisetujui()) {
            return back()->withErrors(['alur' => 'Kemajuan yang sudah disetujui tidak dapat dihapus.']);
        }

        $kemajuan->delete();

        return back()->with('ok', 'Kemajuan dihapus.');
    }

    /* ---------- alur tinjauan: kemajuan ---------- */

    public function ajukanKemajuan(ReklamasiKemajuan $kemajuan)
    {
        return $this->jalankan($kemajuan, fn () => $kemajuan->ajukan(),
            'Ajukan kemajuan reklamasi', 'Kemajuan diajukan untuk ditinjau.');
    }

    public function setujuiKemajuan(ReklamasiKemajuan $kemajuan)
    {
        $hasil = $this->jalankan($kemajuan, fn () => $kemajuan->setujui(),
            'Setujui kemajuan reklamasi', 'Kemajuan disetujui dan tahapan petak diperbarui.');

        // Tahapan petak baru berpindah di sini — sesudah ditinjau, dan
        // hanya bila memang maju. Kemajuan lama yang disetujui belakangan
        // tidak boleh menarik mundur petak yang sudah lebih jauh.
        $kemajuan->refresh();
        if ($kemajuan->sudahDisetujui()) {
            $area = $kemajuan->area;
            if ($area && Reklamasi::urutan($kemajuan->tahap) > Reklamasi::urutan($area->tahap)) {
                $area->update(['tahap' => $kemajuan->tahap]);
            }
        }

        return $hasil;
    }

    public function tolakKemajuan(Request $request, ReklamasiKemajuan $kemajuan)
    {
        $alasan = $request->validate([
            'alasan_tolak' => ['required', 'string', 'min:5', 'max:1000'],
        ])['alasan_tolak'];

        return $this->jalankan($kemajuan, fn () => $kemajuan->tolak($alasan),
            'Tolak kemajuan reklamasi', 'Kemajuan ditolak dan dikembalikan kepada pengaju.');
    }

    /* ---------- baku mutu ---------- */

    public function simpanParameter(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'kode'       => ['required', 'string', 'max:40'],
            'nama'       => ['required', 'string', 'max:150'],
            'media'      => ['required', Rule::in(LingkunganParameter::MEDIA)],
            'satuan'     => ['nullable', 'string', 'max:30'],
            'batas_min'  => ['nullable', 'numeric', 'min:-1000000', 'max:100000000'],
            'batas_maks' => ['nullable', 'numeric', 'min:-1000000', 'max:100000000', 'gte:batas_min'],
            'acuan'      => ['nullable', 'string', 'max:200'],
            'aktif'      => ['nullable', 'boolean'],
        ]));

        // Parameter tanpa ambang mana pun tidak pernah dapat dilanggar;
        // menyimpannya diam-diam membuat titik penaatan tampak taat
        // padahal tidak ada yang dibandingkan.
        // Kunci yang tidak dikirim tidak muncul di data tervalidasi;
        // membacanya langsung menjatuhkan permintaan alih-alih menolaknya.
        $data['batas_min']  ??= null;
        $data['batas_maks'] ??= null;

        if ($data['batas_min'] === null && $data['batas_maks'] === null) {
            return back()->withErrors([
                'batas_maks' => 'Isi setidaknya satu ambang. Tanpa ambang, hasil ujinya tidak dapat dinilai taat atau tidak.',
            ]);
        }

        LingkunganParameter::updateOrCreate(
            ['company_id' => $data['company_id'] ?? null, 'kode' => $data['kode']],
            $data,
        );

        ActivityLog::write('Tetapkan baku mutu', $data['kode'].' — '.$data['nama'], 'lingkungan');

        return back()->with('ok', 'Baku mutu tersimpan.');
    }

    public function hapusParameter(LingkunganParameter $parameter)
    {
        $parameter->delete();

        return back()->with('ok', 'Parameter dihapus.');
    }

    /* ---------- pemantauan ---------- */

    public function simpanPantau(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id'              => ['nullable', 'exists:companies,id'],
            'lingkungan_parameter_id' => ['required', 'exists:lingkungan_parameters,id'],
            'titik'                   => ['required', 'string', 'max:100'],
            'tanggal'                 => ['required', 'date'],
            'nilai'                   => ['required', 'numeric', 'min:-1000000', 'max:100000000'],
            'laboratorium'            => ['nullable', 'string', 'max:150'],
            'catatan'                 => ['nullable', 'string', 'max:2000'],
        ]));

        $data['user_id'] = auth()->id();
        $x = LingkunganPantau::create($data);

        ActivityLog::write('Catat hasil uji lingkungan',
            $x->titik.' · '.($x->parameter?->kode ?? ''), 'lingkungan');

        return back()->with('ok', $x->melanggar()
            ? 'Hasil uji tersimpan sebagai draf — nilainya melampaui baku mutu.'
            : 'Hasil uji tersimpan sebagai draf.');
    }

    public function hapusPantau(LingkunganPantau $pantau)
    {
        if ($pantau->sudahDisetujui()) {
            return back()->withErrors(['alur' => 'Hasil uji yang sudah disetujui tidak dapat dihapus.']);
        }

        $pantau->delete();

        return back()->with('ok', 'Hasil uji dihapus.');
    }

    public function ajukanPantau(LingkunganPantau $pantau)  { return $this->jalankan($pantau, fn () => $pantau->ajukan(), 'Ajukan hasil uji', 'Hasil uji diajukan untuk ditinjau.'); }
    public function setujuiPantau(LingkunganPantau $pantau) { return $this->jalankan($pantau, fn () => $pantau->setujui(), 'Setujui hasil uji', 'Hasil uji disetujui.'); }

    public function tolakPantau(Request $request, LingkunganPantau $pantau)
    {
        $alasan = $request->validate([
            'alasan_tolak' => ['required', 'string', 'min:5', 'max:1000'],
        ])['alasan_tolak'];

        return $this->jalankan($pantau, fn () => $pantau->tolak($alasan),
            'Tolak hasil uji', 'Hasil uji ditolak dan dikembalikan kepada pengaju.');
    }

    private function jalankan($baris, callable $aksi, string $peristiwa, string $pesan)
    {
        try {
            $aksi();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['alur' => $e->getMessage()]);
        }

        ActivityLog::write($peristiwa, (string) ($baris->id ?? ''), 'lingkungan');

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

        $data['modul'] = 'lingkungan';
        $data['user_id'] = auth()->id();

        TindakLanjut::create($data);

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
        $d = $this->kumpulkan($request, $dari, $sampai);

        $perusahaan = auth()->user()?->company ?: Company::first();

        return Inertia::render('Print/Lingkungan', [
            'dok'    => KopDokumen::untuk('laporan-lingkungan', $perusahaan),
            'dari'   => $dari->toDateString(),
            'sampai' => $sampai->toDateString(),

            'dasar' => [
                'area'          => $d['area']->count(),
                'kemajuan'      => $d['semuaKemajuan']->count(),
                'disetujui'     => $d['kemajuan']->count(),
                'belumDitinjau' => $d['semuaKemajuan']->count() - $d['kemajuan']->count(),
            ],

            'neraca'  => $d['neraca'],
            'nisbah'  => $d['nisbah'],
            'jaminan' => $d['jaminan'],
            'area'    => $d['area']->map(fn (LingkunganArea $a) => $a->toView())->values(),
            'pantau'  => $d['pantau']->map(fn (LingkunganPantau $x) => $x->toView())->values(),
            'tindak'  => TindakLanjut::modul('lingkungan')->terbukaSaja()->urutMendesak()->get()
                ->map(fn (TindakLanjut $t) => $t->toView())->values(),

            'kembali' => route('lingkungan.index', ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()]),
        ]);
    }

    /* ---------- halaman ---------- */

    private function halaman(Request $request, string $mode)
    {
        [$dari, $sampai] = $this->rentang($request);
        $d = $this->kumpulkan($request, $dari, $sampai);

        $tindak = TindakLanjut::with('sumber')->modul('lingkungan')->urutMendesak()->get();

        return Inertia::render('Lingkungan/Halaman', [
            'mode'   => $mode,
            'dari'   => $dari->toDateString(),
            'sampai' => $sampai->toDateString(),

            'neraca'  => $d['neraca'],
            'nisbah'  => $d['nisbah'],
            'jaminan' => $d['jaminan'],
            'proyeksi'=> $d['proyeksi'],

            'alerts' => PeringatanLingkungan::susun(
                $d['area'], $d['pantau'], $d['neraca'], $d['nisbah'], $d['jaminan'],
            ),

            'area'      => $d['area']->map(fn (LingkunganArea $a) => $a->toView())->values(),
            'kemajuan'  => $d['semuaKemajuan']->map(fn (ReklamasiKemajuan $k) => $k->toView())->values(),
            'parameter' => $d['parameter']->map(fn (LingkunganParameter $p) => $p->toView())->values(),
            'pantauSemua' => $d['semuaPantau']->map(fn (LingkunganPantau $x) => $x->toView())->values(),

            'tindak' => $tindak->map(fn (TindakLanjut $t) => $t->toView())->values(),
            'kodeDitangani' => $tindak->filter(fn (TindakLanjut $t) => $t->terbuka())
                ->pluck('kode_pemicu')->filter()->unique()->values(),

            'companies' => Company::query()
                ->when(!auth()->user()?->isAdmin(), fn ($q) => $q->whereKey(auth()->user()?->company_id))
                ->orderBy('name')->get(['id', 'name']),

            'opsi' => [
                'jenis'  => LingkunganArea::JENIS,
                'tahap'  => Reklamasi::TAHAP,
                'media'  => LingkunganParameter::MEDIA,
                'statusTindak'    => TindakLanjut::STATUS,
                'prioritasTindak' => TindakLanjut::PRIORITAS,
                'tumbuhMinimum'   => NeracaLahan::TUMBUH_MINIMUM_PERSEN,
                'tunggakanWajar'  => NeracaLahan::TUNGGAKAN_WAJAR_HARI,
                'biayaPerHa'      => self::BIAYA_PER_HA_BAWAAN,
            ],

            'tautan' => [
                'dashboard'  => route('lingkungan.index'),
                'lahan'      => route('lingkungan.lahan'),
                'pemantauan' => route('lingkungan.pemantauan'),
                'baku'       => route('lingkungan.baku'),
                'cetak'      => route('lingkungan.cetak'),
                'areaSimpan' => route('lingkungan.area.simpan'),
                'areaUbah'   => route('lingkungan.area.ubah', ['area' => '__ID__']),
                'areaHapus'  => route('lingkungan.area.hapus', ['area' => '__ID__']),
                'kemajuanSimpan' => route('lingkungan.kemajuan.simpan'),
                'kemajuanHapus'  => route('lingkungan.kemajuan.hapus', ['kemajuan' => '__ID__']),
                'kemajuanAjukan' => route('lingkungan.kemajuan.ajukan', ['kemajuan' => '__ID__']),
                'kemajuanSetujui'=> route('lingkungan.kemajuan.setujui', ['kemajuan' => '__ID__']),
                'kemajuanTolak'  => route('lingkungan.kemajuan.tolak', ['kemajuan' => '__ID__']),
                'parameterSimpan'=> route('lingkungan.parameter.simpan'),
                'parameterHapus' => route('lingkungan.parameter.hapus', ['parameter' => '__ID__']),
                'pantauSimpan'   => route('lingkungan.pantau.simpan'),
                'pantauHapus'    => route('lingkungan.pantau.hapus', ['pantau' => '__ID__']),
                'pantauAjukan'   => route('lingkungan.pantau.ajukan', ['pantau' => '__ID__']),
                'pantauSetujui'  => route('lingkungan.pantau.setujui', ['pantau' => '__ID__']),
                'pantauTolak'    => route('lingkungan.pantau.tolak', ['pantau' => '__ID__']),
                'tindakSimpan'   => route('lingkungan.tindak.simpan'),
                'tindakUbah'     => route('lingkungan.tindak.ubah', ['tindak' => '__ID__']),
            ],
        ]);
    }

    /** Kumpulan data yang dipakai halaman maupun laporan. */
    private function kumpulkan(Request $request, Carbon $dari, Carbon $sampai): array
    {
        $area = LingkunganArea::with('kemajuan')->orderBy('kode')->get();

        $semuaKemajuan = ReklamasiKemajuan::with('area')
            ->whereBetween('tanggal', [$dari, $sampai])->orderByDesc('tanggal')->get();
        $kemajuan = $semuaKemajuan->whereIn('status', Alur::terhitung());

        $parameter = LingkunganParameter::orderBy('media')->orderBy('kode')->get();

        $semuaPantau = LingkunganPantau::with('parameter')
            ->whereBetween('tanggal', [$dari, $sampai])->orderByDesc('tanggal')->get();
        $pantau = $semuaPantau->whereIn('status', Alur::terhitung());

        $neraca = NeracaLahan::susun($area->map(fn (LingkunganArea $a) => $a->untukNeraca())->all());

        // Bukaan dan penyelesaian pada periode ini, untuk nisbahnya.
        $dibuka = (float) $area->filter(fn (LingkunganArea $a) => $a->tanggal_buka
            && $a->tanggal_buka->between($dari, $sampai))->sum('luas_ha');

        $diselesaikan = (float) $kemajuan
            ->filter(fn (ReklamasiKemajuan $k) => Reklamasi::selesai($k->tahap))->sum('luas_ha');

        $biaya = (float) ($request->float('biaya_per_ha') ?: self::BIAYA_PER_HA_BAWAAN);
        $ditempatkan = (float) $request->float('jaminan');

        return [
            'area'          => $area,
            'semuaKemajuan' => $semuaKemajuan,
            'kemajuan'      => $kemajuan,
            'parameter'     => $parameter,
            'semuaPantau'   => $semuaPantau,
            'pantau'        => $pantau,
            'neraca'        => $neraca,
            'nisbah'        => NeracaLahan::nisbah($diselesaikan, $dibuka),
            'jaminan'       => NeracaLahan::jaminan($ditempatkan, (float) $neraca['tunggakan'], $biaya),
            'proyeksi'      => NeracaLahan::tahunMenutupTunggakan(
                (float) $neraca['tunggakan'],
                $this->lajuBersihTahunan($diselesaikan, $dibuka, $dari, $sampai),
            ),
        ];
    }

    /**
     * Laju bersih penutupan tunggakan, hektare per tahun.
     *
     * Bersih berarti dikurangi bukaan baru: reklamasi 40 ha setahun tidak
     * menutup apa pun bila 40 ha baru dibuka pada tahun yang sama.
     */
    private function lajuBersihTahunan(float $diselesaikan, float $dibuka, Carbon $dari, Carbon $sampai): float
    {
        $hari = max(1, (int) $dari->diffInDays($sampai) + 1);

        return ($diselesaikan - $dibuka) * (365 / $hari);
    }

    /** @return array{0:Carbon,1:Carbon} */
    private function rentang(Request $request): array
    {
        $dari = $request->date('dari') ?: now()->startOfYear();
        $sampai = $request->date('sampai') ?: now()->endOfYear();

        return $dari->greaterThan($sampai) ? [$sampai, $dari] : [$dari, $sampai];
    }

}
