<?php

namespace App\Http\Controllers;

use App\Models\{KompetensiJenis, Paspor, PasporKartu, PasporMcu, PasporSertifikat};
use App\Rules\DalamPerusahaan;
use App\Models\ActivityLog as Jejak;
use App\Support\Authority;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Authority — berkas kelayakan kerja.
 *
 * Halaman ini menjawab satu pertanyaan yang ditanyakan setiap pagi di
 * gerbang: boleh atau tidak orang ini bekerja hari ini. Jawabannya
 * menuntut tiga hal berlaku bersamaan — MCU, kartu masuk, dan (untuk
 * pekerjaan tertentu) kompetensi — yang selama ini tersimpan di tiga
 * aplikasi berbeda sehingga tidak ada satu layar pun yang dapat
 * menjawabnya.
 */
class AuthorityController extends Controller
{
    /* ═══════════ daftar & ringkasan ═══════════ */

    public function index(Request $request)
    {
        $orang = Paspor::with(['sertifikat', 'mcu', 'kartu'])
            ->when($cari = trim((string) $request->get('q')), fn ($q) => $q->where(
                fn ($w) => $w->where('nama', 'like', "%{$cari}%")
                    ->orWhere('nik', 'like', "%{$cari}%")
                    ->orWhere('jabatan', 'like', "%{$cari}%")))
            ->when($request->get('klas'), fn ($q, $k) => $q->where('klasifikasi', $k))
            ->orderBy('nama')
            ->get();

        /* Disaring SESUDAH dimuat, bukan lewat kueri: keadaan terburuk
           seseorang dihitung dari tiga tabel anaknya sekaligus, dan
           menuliskannya sebagai SQL menghasilkan kueri yang tidak dapat
           dibaca siapa pun enam bulan lagi. Jumlah orang per perusahaan
           berukuran ratusan, bukan jutaan. */
        if ($keadaan = $request->get('keadaan')) {
            $orang = $orang->filter(fn (Paspor $p) => $p->keadaanTerburuk() === $keadaan)->values();
        }

        return Inertia::render('Authority/Halaman', $this->bersama() + [
            'mode'   => 'daftar',
            'orang'  => $orang->map(fn (Paspor $p) => $this->baris($p))->values(),
            'saring' => [
                'q' => $cari, 'klas' => $request->get('klas'), 'keadaan' => $keadaan,
            ],
            'ringkas' => $this->ringkasan(),
        ]);
    }

    public function show(Paspor $paspor)
    {
        $paspor->load(['sertifikat.jenis', 'mcu', 'kartu', 'user', 'company']);

        return Inertia::render('Authority/Halaman', $this->bersama() + [
            'mode'   => 'rincian',
            'p'      => $this->baris($paspor) + [
                'departemen'    => $paspor->departemen,
                'nomorRegister' => $paspor->nomor_register,
                'tglBergabung'  => $paspor->tgl_bergabung?->toDateString(),
                'catatan'       => $paspor->catatan,
            ],
            'sertifikat' => $paspor->sertifikat->map(fn (PasporSertifikat $s) => [
                'id' => $s->id, 'nama' => $s->nama, 'lembaga' => $s->lembaga,
                'nomor' => $s->nomor,
                'tglTerbit' => $s->tgl_terbit?->toDateString(),
                'tglExpired' => $s->tgl_expired?->toDateString(),
                'keadaan' => $s->keadaan(), 'keterangan' => $s->keterangan(),
                'dariLms' => $s->certificate_id !== null,
            ])->values(),
            'mcu' => $paspor->mcu->map(fn (PasporMcu $m) => [
                'id' => $m->id, 'tglPeriksa' => $m->tgl_periksa?->toDateString(),
                'tglExpired' => $m->tgl_expired?->toDateString(),
                'penyelenggara' => $m->penyelenggara, 'jenis' => $m->jenis,
                'hasil' => $m->hasil, 'pembatasan' => $m->pembatasan,
                'keadaan' => $m->keadaan(), 'keterangan' => $m->keterangan(),
                'hasilLayak' => $m->hasilLayak(),
            ])->values(),
            'kartu' => $paspor->kartu->map(fn (PasporKartu $k) => [
                'id' => $k->id, 'jenis' => $k->jenis, 'nomor' => $k->nomor,
                'tglTerbit' => $k->tgl_terbit?->toDateString(),
                'tglExpired' => $k->tgl_expired?->toDateString(),
                'golongan' => $k->golongan, 'area' => $k->area,
                'keadaan' => $k->keadaan(), 'keterangan' => $k->keterangan(),
            ])->values(),
        ]);
    }

    /* ═══════════ simpan ═══════════ */

    public function store(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id'     => ['nullable', 'exists:companies,id'],
            'nama'           => ['required', 'string', 'max:150'],
            'nik'            => ['nullable', 'string', 'max:40'],
            'jabatan'        => ['nullable', 'string', 'max:120'],
            'departemen'     => ['nullable', 'string', 'max:120'],
            'klasifikasi'    => ['nullable', Rule::in(array_keys(Authority::KLASIFIKASI))],
            'nomor_register' => ['nullable', 'string', 'max:60'],
            'tgl_bergabung'  => ['nullable', 'date'],
            'status'         => ['nullable', Rule::in(['aktif', 'cuti', 'keluar'])],
            'catatan'        => ['nullable', 'string', 'max:2000'],
        ]));

        $p = Paspor::create($data);

        Jejak::write('Tambah paspor kerja', $p->nama, 'authority');

        return back()->with('ok', 'Paspor '.$p->nama.' dibuat.');
    }

    public function update(Request $request, Paspor $paspor)
    {
        $paspor->update($request->validate([
            'nama'           => ['required', 'string', 'max:150'],
            'nik'            => ['nullable', 'string', 'max:40'],
            'jabatan'        => ['nullable', 'string', 'max:120'],
            'departemen'     => ['nullable', 'string', 'max:120'],
            'klasifikasi'    => ['nullable', Rule::in(array_keys(Authority::KLASIFIKASI))],
            'nomor_register' => ['nullable', 'string', 'max:60'],
            'tgl_bergabung'  => ['nullable', 'date'],
            'status'         => ['nullable', Rule::in(['aktif', 'cuti', 'keluar'])],
            'catatan'        => ['nullable', 'string', 'max:2000'],
        ]));

        Jejak::write('Ubah paspor kerja', $paspor->nama, 'authority');

        return back()->with('ok', 'Paspor diperbarui.');
    }

    public function destroy(Paspor $paspor)
    {
        $nama = $paspor->nama;
        $paspor->delete();

        Jejak::write('Hapus paspor kerja', $nama, 'authority');

        return redirect()->route('authority.index')->with('ok', 'Paspor '.$nama.' dihapus.');
    }

    /* ═══════════ sertifikat ═══════════ */

    public function simpanSertifikat(Request $request, Paspor $paspor)
    {
        $data = $request->validate([
            'kompetensi_jenis_id' => ['nullable', new DalamPerusahaan('kompetensi_jenis')],
            'nama'        => ['required', 'string', 'max:200'],
            'lembaga'     => ['nullable', 'string', 'max:80'],
            'nomor'       => ['nullable', 'string', 'max:80'],
            'tgl_terbit'  => ['nullable', 'date'],
            'tgl_expired' => ['nullable', 'date'],
            'catatan'     => ['nullable', 'string', 'max:1000'],
        ]);

        $paspor->sertifikat()->create($data);

        Jejak::write('Tambah sertifikat', $paspor->nama.' — '.$data['nama'], 'authority');

        return back()->with('ok', 'Sertifikat ditambahkan.');
    }

    public function hapusSertifikat(Paspor $paspor, PasporSertifikat $sertifikat)
    {
        abort_unless($sertifikat->paspor_id === $paspor->id, 404);

        $sertifikat->delete();

        return back()->with('ok', 'Sertifikat dihapus.');
    }

    /* ═══════════ MCU ═══════════ */

    public function simpanMcu(Request $request, Paspor $paspor)
    {
        $data = $request->validate([
            'tgl_periksa'   => ['required', 'date'],
            'tgl_expired'   => ['nullable', 'date', 'after_or_equal:tgl_periksa'],
            'penyelenggara' => ['nullable', 'string', 'max:150'],
            'jenis'         => ['required', Rule::in(['Awal', 'Berkala', 'Khusus', 'Purna'])],
            'hasil'         => ['required', Rule::in(Authority::HASIL_MCU)],
            'pembatasan'    => ['nullable', 'string', 'max:500'],
        ]);

        $paspor->mcu()->create($data);

        Jejak::write('Catat MCU', $paspor->nama.' — '.$data['hasil'], 'authority');

        return back()->with('ok', 'Hasil MCU tersimpan.');
    }

    public function hapusMcu(Paspor $paspor, PasporMcu $mcu)
    {
        abort_unless($mcu->paspor_id === $paspor->id, 404);

        $mcu->delete();

        return back()->with('ok', 'Catatan MCU dihapus.');
    }

    /* ═══════════ kartu masuk ═══════════ */

    public function simpanKartu(Request $request, Paspor $paspor)
    {
        $data = $request->validate([
            'jenis'       => ['required', Rule::in(Authority::JENIS_KARTU)],
            'nomor'       => ['nullable', 'string', 'max:80'],
            'tgl_terbit'  => ['nullable', 'date'],
            'tgl_expired' => ['nullable', 'date'],
            'golongan'    => ['nullable', 'string', 'max:80'],
            'area'        => ['nullable', 'string', 'max:150'],
            'catatan'     => ['nullable', 'string', 'max:1000'],
        ]);

        $paspor->kartu()->create($data);

        Jejak::write('Terbitkan kartu masuk', $paspor->nama.' — '.$data['jenis'], 'authority');

        return back()->with('ok', 'Kartu masuk tersimpan.');
    }

    public function hapusKartu(Paspor $paspor, PasporKartu $kartu)
    {
        abort_unless($kartu->paspor_id === $paspor->id, 404);

        $kartu->delete();

        return back()->with('ok', 'Kartu dihapus.');
    }

    /* ═══════════ pendukung ═══════════ */

    /** @return array<string,mixed> */
    private function bersama(): array
    {
        return [
            'judul'    => 'Authority — Kelayakan Kerja',
            'subjudul' => 'Kompetensi, MCU, dan kartu masuk tambang dalam satu berkas per orang',
            'opsi' => [
                'klasifikasi' => Authority::KLASIFIKASI,
                'hasilMcu'    => Authority::HASIL_MCU,
                'jenisKartu'  => Authority::JENIS_KARTU,
                'jenisMcu'    => ['Awal', 'Berkala', 'Khusus', 'Purna'],
                'kompetensi'  => KompetensiJenis::aktif()->orderBy('urutan')
                    ->get(['id', 'nama', 'lembaga']),
            ],
        ];
    }

    /** Bentuk satu baris orang, sama untuk daftar maupun rincian. */
    private function baris(Paspor $p): array
    {
        $m = $p->mcuTerakhir();
        $k = $p->kartuTerakhir();
        $kelayakan = $p->kelayakan();

        return [
            'id'          => $p->id,
            'nama'        => $p->nama,
            'nik'         => $p->nik,
            'jabatan'     => $p->jabatan,
            'klasifikasi' => $p->klasifikasi,
            'klasLabel'   => $p->labelKlasifikasi(),
            'status'      => $p->status,

            'layak'       => $kelayakan['layak'],
            'sebab'       => $kelayakan['sebab'],
            'keadaan'     => $p->keadaanTerburuk(),

            'jumlahSertifikat' => $p->sertifikat->count(),

            'mcu' => $m ? [
                'tglExpired' => $m->tgl_expired?->toDateString(),
                'hasil'      => $m->hasil,
                'keadaan'    => $m->keadaan(),
                'keterangan' => $m->keterangan(),
            ] : null,

            'kartu' => $k ? [
                'jenis'      => $k->jenis,
                'tglExpired' => $k->tgl_expired?->toDateString(),
                'keadaan'    => $k->keadaan(),
                'keterangan' => $k->keterangan(),
            ] : null,
        ];
    }

    /**
     * Ringkasan seluruh berkas menurut keadaan masa berlakunya.
     *
     * Dihitung dari KETIGA jenis berkas sekaligus, bukan hanya
     * sertifikat: yang menahan orang di gerbang paling sering justru
     * MCU dan kartu masuk, bukan kompetensinya.
     *
     * @return array<string,mixed>
     */
    private function ringkasan(): array
    {
        $orang = Paspor::with(['sertifikat', 'mcu', 'kartu'])->get();

        $tglSertifikat = $orang->flatMap(fn ($p) => $p->sertifikat->pluck('tgl_expired'));
        $tglMcu        = $orang->map(fn ($p) => $p->mcuTerakhir()?->tgl_expired)->filter();
        $tglKartu      = $orang->map(fn ($p) => $p->kartuTerakhir()?->tgl_expired)->filter();

        $takLayak = $orang->filter(fn (Paspor $p) => !$p->kelayakan()['layak']);

        return [
            'orang'      => $orang->count(),
            'layak'      => $orang->count() - $takLayak->count(),
            'takLayak'   => $takLayak->count(),
            'sertifikat' => $tglSertifikat->count(),

            'perKeadaan' => [
                'sertifikat' => Authority::ringkas($tglSertifikat),
                'mcu'        => Authority::ringkas($tglMcu),
                'kartu'      => Authority::ringkas($tglKartu),
            ],

            'perKlasifikasi' => collect(Authority::KLASIFIKASI)
                ->map(fn ($label, $kode) => [
                    'kode'  => $kode,
                    'label' => $label,
                    'orang' => $orang->where('klasifikasi', $kode)->count(),
                ])->values(),
        ];
    }
}
