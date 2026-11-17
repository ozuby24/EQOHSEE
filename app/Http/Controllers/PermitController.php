<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Company, IzinAmbang, IzinGas, IzinKerja, IzinPeriksa, IzinSyarat, TindakLanjut};
use App\Support\{Izin, KopDokumen, PeringatanIzin};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Izin kerja aman (permit to work).
 *
 * Persetujuan di sini ADALAH izinnya: yang disetujui berarti pekerjaan
 * boleh dimulai. Karena itu penerbitan dijaga lebih ketat daripada
 * persetujuan mana pun di aplikasi ini — syarat wajib yang belum
 * terpenuhi dan uji gas yang basi menghalanginya, bukan sekadar
 * memperingatkan.
 */
class PermitController extends Controller
{
    public function index(Request $r)  { return $this->halaman($r, 'dashboard'); }
    public function daftar(Request $r) { return $this->halaman($r, 'daftar'); }
    public function syarat(Request $r) { return $this->halaman($r, 'syarat'); }
    public function ambang(Request $r) { return $this->halaman($r, 'ambang'); }

    /* ---------- daftar periksa ---------- */

    public function simpanSyarat(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'jenis'      => ['required', Rule::in(Izin::JENIS)],
            'teks'       => ['required', 'string', 'max:500'],
            'urutan'     => ['nullable', 'integer', 'min:0', 'max:999'],
            'wajib'      => ['nullable', 'boolean'],
        ]));

        $data['urutan'] ??= 0;
        $data['wajib'] = (bool) ($data['wajib'] ?? true);

        IzinSyarat::create($data + ['aktif' => true]);
        ActivityLog::write('Tambah syarat izin kerja', $data['jenis'], 'izin');

        return back()->with('ok', 'Syarat ditambahkan.');
    }

    public function hapusSyarat(IzinSyarat $syarat)
    {
        $syarat->delete();

        return back()->with('ok', 'Syarat dihapus. Izin yang sudah terbit tidak berubah — teks syaratnya tersalin di berkasnya.');
    }

    /* ---------- ambang gas ---------- */

    public function simpanAmbang(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'parameter'  => ['required', Rule::in(array_keys(Izin::ambangBawaan()))],
            'batas_min'  => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'batas_maks' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'satuan'     => ['nullable', 'string', 'max:20'],
            'acuan'      => ['nullable', 'string', 'max:200'],
        ]));

        if (($data['batas_min'] ?? null) !== null && ($data['batas_maks'] ?? null) !== null
            && $data['batas_min'] > $data['batas_maks']) {
            return back()->withErrors(['batas_min' => 'Batas bawah melebihi batas atas.']);
        }

        IzinAmbang::updateOrCreate(
            ['company_id' => $data['company_id'] ?? null, 'parameter' => $data['parameter']],
            $data,
        );

        ActivityLog::write('Tetapkan ambang gas', $data['parameter'], 'izin');

        return back()->with('ok', 'Ambang gas tersimpan.');
    }

    public function hapusAmbang(IzinAmbang $ambang)
    {
        $ambang->delete();

        return back()->with('ok', 'Ambang dihapus; parameter itu kembali memakai nilai bawaan.');
    }

    /* ---------- izin ---------- */

    public function simpanIzin(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id'        => ['nullable', 'exists:companies,id'],
            'nomor'             => ['required', 'string', 'max:60'],
            'jenis'             => ['required', Rule::in(Izin::JENIS)],
            'lokasi'            => ['required', 'string', 'max:150'],
            'uraian'            => ['required', 'string', 'max:3000'],
            'pelaksana'         => ['nullable', 'string', 'max:150'],
            'jumlah_pekerja'    => ['nullable', 'integer', 'min:1', 'max:500'],
            'pengawas_lapangan' => ['nullable', 'string', 'max:150'],
            'mulai'             => ['required', 'date'],
            'selesai'           => ['required', 'date', 'after:mulai'],
            'batas_uji_menit'   => ['nullable', 'integer', 'min:5', 'max:720'],
            'catatan'           => ['nullable', 'string', 'max:3000'],
        ]));

        $data['user_id'] = auth()->id();

        $izin = IzinKerja::create($data);

        // Daftar periksa disalin saat izin dibuat, bukan dirujuk. Daftar
        // syarat berubah seiring waktu, dan izin yang sudah terbit harus
        // tetap terbaca dengan syarat yang berlaku SAAT itu.
        foreach (IzinSyarat::where('jenis', $izin->jenis)->where('aktif', true)
                     ->orderBy('urutan')->orderBy('id')->get() as $s) {
            IzinPeriksa::create([
                'company_id'     => $izin->company_id,
                'izin_kerja_id'  => $izin->id,
                'izin_syarat_id' => $s->id,
                'teks'           => $s->teks,
                'wajib'          => $s->wajib,
                'terpenuhi'      => false,
            ]);
        }

        ActivityLog::write('Ajukan izin kerja', $izin->nomor.' — '.$izin->jenis, 'izin');

        return back()->with('ok', 'Izin tersimpan sebagai draf beserta daftar periksanya.');
    }

    public function hapusIzin(IzinKerja $izin)
    {
        if ($izin->sudahDisetujui()) {
            return back()->withErrors(['alur' => 'Izin yang sudah diterbitkan tidak dapat dihapus.']);
        }

        $izin->delete();

        return back()->with('ok', 'Izin dihapus.');
    }

    public function ubahPeriksa(Request $request, IzinPeriksa $periksa)
    {
        if ($periksa->izin?->sudahDisetujui()) {
            return back()->withErrors([
                'alur' => 'Izin sudah diterbitkan; daftar periksanya adalah dasar penerbitan dan tidak dapat diubah.',
            ]);
        }

        $data = $request->validate([
            'terpenuhi'  => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $periksa->update($data);

        return back()->with('ok', 'Daftar periksa diperbarui.');
    }

    public function ajukan(IzinKerja $izin)
    {
        return $this->jalankan($izin, fn () => $izin->ajukan(),
            'Ajukan izin kerja', 'Izin diajukan untuk diterbitkan.');
    }

    /**
     * Menerbitkan izin.
     *
     * Di sinilah penjagaan yang paling penting pada modul ini. Syarat
     * wajib yang belum terpenuhi dan uji gas yang basi MENGHALANGI
     * penerbitan, bukan memperingatkannya — izin yang terbit di atas
     * daftar periksa kosong adalah tanda tangan, bukan pemeriksaan, dan
     * berkasnya nanti terbaca seolah pemeriksaannya pernah dilakukan.
     */
    public function terbitkan(IzinKerja $izin)
    {
        $izin->load(['periksa', 'gas']);

        $siap = $izin->siapDiterbitkan($this->ambangBerlaku());

        if (!$siap['boleh']) {
            return back()->withErrors(['alur' => 'Izin belum dapat diterbitkan: '.implode(' ', $siap['alasan'])]);
        }

        return $this->jalankan($izin, fn () => $izin->setujui(),
            'Terbitkan izin kerja', 'Izin diterbitkan — pekerjaan boleh dimulai dalam masa berlakunya.');
    }

    public function tolak(Request $request, IzinKerja $izin)
    {
        $alasan = $request->validate(['alasan_tolak' => ['required', 'string', 'min:5', 'max:1000']])['alasan_tolak'];

        return $this->jalankan($izin, fn () => $izin->tolak($alasan),
            'Tolak izin kerja', 'Izin ditolak dan dikembalikan kepada pemohon.');
    }

    /**
     * Menutup izin.
     *
     * Penutupan bukan perpindahan status melainkan penandaan tersendiri:
     * izin tetap berstatus diterbitkan setelah ditutup, dan yang
     * membedakannya hanya kolom ini. Karena itu ia dapat dilakukan
     * setelah masa berlakunya lewat — memang justru itu yang paling
     * sering terjadi, dan memaksanya dilakukan tepat waktu hanya membuat
     * orang menutup izin lebih awal daripada pekerjaannya selesai.
     */
    public function tutup(Request $request, IzinKerja $izin)
    {
        if (!$izin->sudahDisetujui()) {
            return back()->withErrors(['alur' => 'Hanya izin yang sudah diterbitkan yang dapat ditutup.']);
        }

        if ($izin->sudahDitutup()) {
            return back()->withErrors(['alur' => 'Izin ini sudah ditutup.']);
        }

        $data = $request->validate([
            'catatan_penutupan' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $izin->forceFill([
            'ditutup_pada'      => now(),
            'ditutup_oleh'      => auth()->id(),
            'catatan_penutupan' => $data['catatan_penutupan'],
        ])->save();

        ActivityLog::write('Tutup izin kerja', $izin->nomor, 'izin');

        return back()->with('ok', 'Izin ditutup.');
    }

    /* ---------- uji gas ---------- */

    public function simpanGas(Request $request, IzinKerja $izin)
    {
        $data = $this->pemilik($request->validate([
            'company_id' => ['nullable', 'exists:companies,id'],
            'waktu_uji'  => ['required', 'date'],
            'o2'         => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lel'        => ['nullable', 'numeric', 'min:0', 'max:100'],
            'co'         => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'h2s'        => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'alat'       => ['nullable', 'string', 'max:100'],
            'petugas'    => ['nullable', 'string', 'max:150'],
            'catatan'    => ['nullable', 'string', 'max:1000'],
        ]));

        // Pengukuran bertanggal masa depan bukan pengukuran. Menerimanya
        // membuka izin atas dasar angka yang belum pernah diambil.
        if (Carbon::parse($data['waktu_uji'])->greaterThan(now()->addMinutes(5))) {
            return back()->withErrors(['waktu_uji' => 'Waktu uji berada di masa depan.']);
        }

        $data['izin_kerja_id'] = $izin->id;

        // Uji gas tetap dapat ditambahkan pada izin yang sudah terbit:
        // pengukuran ulang di tengah pekerjaan justru yang diharapkan,
        // dan menutupnya berarti memaksa orang bekerja dengan angka lama.
        $g = IzinGas::create($data);
        $h = $g->periksa($this->ambangBerlaku());

        return back()->with('ok', $h['lulus']
            ? 'Uji gas tersimpan.'
            : 'Uji gas tersimpan. Ada bacaan di luar ambang — langsung masuk ke peringatan.');
    }

    public function hapusGas(IzinGas $gas)
    {
        $gas->delete();

        return back()->with('ok', 'Uji gas dihapus.');
    }

    private function jalankan($baris, callable $aksi, string $peristiwa, string $pesan)
    {
        try {
            $aksi();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['alur' => $e->getMessage()]);
        }

        ActivityLog::write($peristiwa, (string) ($baris->nomor ?? $baris->id ?? ''), 'izin');

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

        $data['modul'] = 'izin';
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

        return Inertia::render('Print/Izin', [
            'dok'    => KopDokumen::untuk('laporan-izin-kerja', $perusahaan),
            'dari'   => $dari->toDateString(),
            'sampai' => $sampai->toDateString(),

            'ringkas' => $d['ringkas'],
            'ambang'  => $d['ambang'],
            'izin'    => $d['izin']->map(fn (IzinKerja $i) => $i->toView($d['ambang']))->values(),
            'tindak'  => TindakLanjut::modul('izin')->terbukaSaja()->urutMendesak()->get()
                ->map(fn (TindakLanjut $t) => $t->toView())->values(),

            'kembali' => route('izin.index', ['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString()]),
        ]);
    }

    /* ---------- halaman ---------- */

    private function halaman(Request $request, string $mode)
    {
        [$dari, $sampai] = $this->rentang($request);
        $d = $this->kumpulkan($dari, $sampai);

        $tindak = TindakLanjut::with('sumber')->modul('izin')->urutMendesak()->get();

        return Inertia::render('Izin/Halaman', [
            'mode'   => $mode,
            'dari'   => $dari->toDateString(),
            'sampai' => $sampai->toDateString(),

            'ringkas' => $d['ringkas'],
            'ambang'  => $d['ambang'],
            'alerts'  => PeringatanIzin::susun($d['izin'], $d['ambang'], $d['ambangDitetapkan'], $d['tanpaSyarat']),

            'izin'   => $d['izin']->map(fn (IzinKerja $i) => $i->toView($d['ambang']))->values(),
            'syarat' => IzinSyarat::orderBy('jenis')->orderBy('urutan')->orderBy('id')->get()
                ->map(fn (IzinSyarat $s) => $s->toView())->values(),
            'daftarAmbang' => IzinAmbang::orderBy('parameter')->get()
                ->map(fn (IzinAmbang $a) => $a->toView())->values(),

            'tindak' => $tindak->map(fn (TindakLanjut $t) => $t->toView())->values(),
            'kodeDitangani' => $tindak->filter(fn (TindakLanjut $t) => $t->terbuka())
                ->pluck('kode_pemicu')->filter()->unique()->values(),

            'companies' => Company::query()
                ->when(!auth()->user()?->isAdmin(), fn ($q) => $q->whereKey(auth()->user()?->company_id))
                ->orderBy('name')->get(['id', 'name']),

            'opsi' => [
                'jenis'           => Izin::JENIS,
                'wajibUjiGas'     => Izin::WAJIB_UJI_GAS,
                'batasUjiBawaan'  => Izin::USIA_UJI_GAS_MENIT,
                'parameterGas'    => array_keys(Izin::ambangBawaan()),
                'statusTindak'    => TindakLanjut::STATUS,
                'prioritasTindak' => TindakLanjut::PRIORITAS,
            ],

            'tautan' => [
                'dashboard' => route('izin.index'),
                'daftar'    => route('izin.daftar'),
                'syarat'    => route('izin.syarat'),
                'ambang'    => route('izin.ambang'),
                'cetak'     => route('izin.cetak'),

                'izinSimpan'    => route('izin.simpan'),
                'izinHapus'     => route('izin.hapus', ['izin' => '__ID__']),
                'izinAjukan'    => route('izin.ajukan', ['izin' => '__ID__']),
                'izinTerbitkan' => route('izin.terbitkan', ['izin' => '__ID__']),
                'izinTolak'     => route('izin.tolak', ['izin' => '__ID__']),
                'izinTutup'     => route('izin.tutup', ['izin' => '__ID__']),

                'periksaUbah' => route('izin.periksa.ubah', ['periksa' => '__ID__']),
                'gasSimpan'   => route('izin.gas.simpan', ['izin' => '__ID__']),
                'gasHapus'    => route('izin.gas.hapus', ['gas' => '__ID__']),

                'syaratSimpan' => route('izin.syarat.simpan'),
                'syaratHapus'  => route('izin.syarat.hapus', ['syarat' => '__ID__']),
                'ambangSimpan' => route('izin.ambang.simpan'),
                'ambangHapus'  => route('izin.ambang.hapus', ['ambang' => '__ID__']),

                'tindakSimpan' => route('izin.tindak.simpan'),
                'tindakUbah'   => route('izin.tindak.ubah', ['tindak' => '__ID__']),
            ],
        ]);
    }

    /** @return array<string,array<string,mixed>> */
    private function ambangBerlaku(): array
    {
        return IzinAmbang::all()->pipe(fn ($t) => IzinAmbang::berlaku($t));
    }

    private function kumpulkan(Carbon $dari, Carbon $sampai): array
    {
        // Disaring pada masa berlakunya, bukan pada tanggal dibuatnya:
        // yang dicari orang adalah izin yang berlaku hari itu.
        $izin = IzinKerja::with(['periksa', 'gas', 'penutup'])
            ->where('mulai', '<=', $sampai->copy()->endOfDay())
            ->where('selesai', '>=', $dari->copy()->startOfDay())
            ->orderByDesc('mulai')->get();

        $tersimpan = IzinAmbang::all();
        $ambang = IzinAmbang::berlaku($tersimpan);

        $jenisBersyarat = IzinSyarat::where('aktif', true)->distinct()->pluck('jenis')->all();

        return [
            'izin'   => $izin,
            'ambang' => $ambang,
            'ambangDitetapkan' => $tersimpan->isNotEmpty(),
            'tanpaSyarat' => count(array_diff(Izin::JENIS, $jenisBersyarat)),
            'ringkas' => [
                'total'      => $izin->count(),
                'berlaku'    => $izin->filter(fn (IzinKerja $i) => $i->sedangBerlaku())->count(),
                'menunggu'   => $izin->filter(fn (IzinKerja $i) => $i->menungguTinjauan())->count(),
                'terbit'     => $izin->filter(fn (IzinKerja $i) => $i->sudahDisetujui())->count(),
                'ditutup'    => $izin->filter(fn (IzinKerja $i) => $i->sudahDitutup())->count(),
                'belumTutup' => $izin->filter(fn (IzinKerja $i) => $i->lewatBelumDitutup())->count(),
                'gasBasi'    => $izin->filter(
                    fn (IzinKerja $i) => $i->sedangBerlaku() && $i->perluUjiGas() && !$i->ujiMasihSegar()
                )->count(),
            ],
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
