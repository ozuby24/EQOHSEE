<?php

namespace App\Http\Controllers;

use App\Models\{KompetensiJenis, McuPengajuan, Paspor, PasporInduksi,
    PasporKartu, PasporMcu, PasporSertifikat};
use App\Rules\DalamPerusahaan;
use App\Models\ActivityLog as Jejak;
use App\Support\Alur;
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
        $orang = Paspor::with(['sertifikat', 'mcu', 'kartu', 'induksi'])
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
        $paspor->load(['sertifikat.jenis', 'mcu.pengajuan', 'kartu', 'induksi', 'user', 'company']);

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
                'nomor' => $m->nomor,
                'hasil' => $m->hasil, 'pembatasan' => $m->pembatasan,
                'rujukan' => $m->rujukan,
                'outstanding' => $m->outstanding?->toDateString(),
                'tertunggak' => $m->rujukanTertunggak(),
                'keadaan' => $m->keadaan(), 'keterangan' => $m->keterangan(),
                'hasilLayak' => $m->hasilLayak(),
                'pengajuan' => $m->pengajuan ? [
                    'id' => $m->pengajuan->id, 'nomor' => $m->pengajuan->nomor_register,
                ] : null,
            ])->values(),
            'kartu' => $paspor->kartu->map(fn (PasporKartu $k) => $this->barisKartu($k))->values(),
            'induksi' => $paspor->induksi->map(fn (PasporInduksi $i) => [
                'id' => $i->id, 'jenis' => $i->jenis,
                'nomorRegistrasi' => $i->nomor_registrasi,
                'tanggal' => $i->tanggal?->toDateString(),
                'tglExpired' => $i->tgl_expired?->toDateString(),
                'pemberi' => $i->pemberi, 'lokasi' => $i->lokasi,
                'nilai' => $i->nilai, 'hasil' => $i->hasil, 'lulus' => $i->lulus(),
                'keadaan' => $i->keadaan(), 'keterangan' => $i->keterangan(),
            ])->values(),
        ]);
    }

    /* ═══════════ pengajuan MCU: satu surat, banyak nama ═══════════ */

    /**
     * Daftar surat pengajuan MCU.
     *
     * Terpisah dari daftar orang karena yang ditanyakan memang berbeda:
     * daftar orang menjawab "siapa yang tidak layak hari ini", daftar
     * ini menjawab "surat mana yang hasilnya belum kembali dari klinik".
     */
    public function mcuIndex(Request $request)
    {
        $pengajuan = McuPengajuan::with(['hasil.paspor', 'pengaju', 'peninjau'])
            ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->get();

        return Inertia::render('Authority/Halaman', $this->bersama() + [
            'mode'   => 'mcu',
            'saring' => ['status' => $request->get('status')],
            'pengajuan' => $pengajuan->map(fn (McuPengajuan $m) => [
                'id'      => $m->id,
                'nomor'   => $m->nomor_register,
                'tanggal' => $m->tanggal?->toDateString(),
                'kepada'  => $m->kepada,
                'judul'   => $m->judul,
                'jenis'   => $m->jenis,
                'catatan' => $m->catatan,

                'status'      => $m->status,
                'statusLabel' => Alur::LABEL[$m->status] ?? $m->status,
                'dapatDiubah' => $m->dapatDiubah(),
                'dapatDitinjau' => $m->dapatDitinjauOleh($request->user()),
                'alasanTolak' => $m->alasan_tolak,
                'pengaju'     => $m->pengaju?->name,
                'peninjau'    => $m->peninjau?->name,

                'jumlah'       => $m->hasil->count(),
                'belumKembali' => $m->belumKembali(),
                'nama' => $m->hasil->map(fn (PasporMcu $h) => [
                    'id'         => $h->id,
                    'pasporId'   => $h->paspor_id,
                    'nama'       => $h->paspor?->nama,
                    'hasil'      => $h->hasil,
                    'tglPeriksa' => $h->tgl_periksa?->toDateString(),
                    'tglExpired' => $h->tgl_expired?->toDateString(),
                    'rujukan'    => $h->rujukan,
                    'tertunggak' => $h->rujukanTertunggak(),
                ])->values(),
            ])->values(),
            'ringkasMcu' => [
                'total'    => $pengajuan->count(),
                'menunggu' => $pengajuan->where('status', Alur::DIAJUKAN)->count(),
                'belumKembali' => $pengajuan->sum(fn (McuPengajuan $m) => $m->belumKembali()),
            ],
        ]);
    }

    public function mcuStore(Request $request)
    {
        $data = $this->pemilik($request->validate([
            'company_id'     => ['nullable', 'exists:companies,id'],
            'nomor_register' => ['nullable', 'string', 'max:60'],
            'tanggal'        => ['required', 'date'],
            'kepada'         => ['nullable', 'string', 'max:150'],
            'judul'          => ['nullable', 'string', 'max:200'],
            'jenis'          => ['required', Rule::in(Authority::JENIS_MCU)],
            'catatan'        => ['nullable', 'string', 'max:2000'],
        ]));

        $data['user_id'] = $request->user()?->getKey();

        $m = McuPengajuan::create($data);

        Jejak::write('Buat pengajuan MCU', $m->nomor_register ?? '#'.$m->id, 'authority');

        return back()->with('ok', 'Pengajuan MCU dibuat sebagai draf.');
    }

    public function mcuUpdate(Request $request, McuPengajuan $pengajuan)
    {
        abort_unless($pengajuan->dapatDiubah(), 422,
            'Pengajuan yang sudah diajukan tidak dapat diubah.');

        $pengajuan->update($request->validate([
            'nomor_register' => ['nullable', 'string', 'max:60'],
            'tanggal'        => ['required', 'date'],
            'kepada'         => ['nullable', 'string', 'max:150'],
            'judul'          => ['nullable', 'string', 'max:200'],
            'jenis'          => ['required', Rule::in(Authority::JENIS_MCU)],
            'catatan'        => ['nullable', 'string', 'max:2000'],
        ]));

        Jejak::write('Ubah pengajuan MCU', $pengajuan->nomor_register ?? '#'.$pengajuan->id, 'authority');

        return back()->with('ok', 'Pengajuan diperbarui.');
    }

    public function mcuDestroy(McuPengajuan $pengajuan)
    {
        abort_unless($pengajuan->dapatDiubah(), 422,
            'Pengajuan yang sudah diajukan tidak dapat dihapus.');

        $nomor = $pengajuan->nomor_register ?? '#'.$pengajuan->id;
        $pengajuan->delete();

        Jejak::write('Hapus pengajuan MCU', $nomor, 'authority');

        return redirect()->route('authority.mcu.index')->with('ok', 'Pengajuan dihapus.');
    }

    /**
     * Menambahkan satu nama ke dalam surat pengajuan.
     *
     * Barisnya dibuat TANPA hasil: yang diajukan adalah permintaan
     * periksa, dan hasilnya baru ada setelah kliniknya memeriksa. Mengisi
     * hasil di muka berarti menebak, dan tebakan yang tersimpan tidak
     * dapat dibedakan dari hasil sungguhan begitu halamannya ditutup.
     */
    public function mcuTambahNama(Request $request, McuPengajuan $pengajuan)
    {
        abort_unless($pengajuan->dapatDiubah(), 422,
            'Nama hanya dapat ditambahkan selagi pengajuan masih draf.');

        $data = $request->validate([
            'paspor_id'   => ['required', new DalamPerusahaan('paspor')],
            'tgl_periksa' => ['nullable', 'date'],
        ]);

        /* Satu orang tidak dimasukkan dua kali ke surat yang sama.
           Kliniknya akan menagih biaya dua kali, dan hasilnya yang
           kembali satu — menyisakan satu baris kosong yang tampak seperti
           pemeriksaan yang belum selesai selamanya. */
        if ($pengajuan->hasil()->where('paspor_id', $data['paspor_id'])->exists()) {
            return back()->withErrors(['paspor_id' => 'Nama ini sudah ada dalam pengajuan.']);
        }

        $pengajuan->hasil()->create([
            'paspor_id'     => $data['paspor_id'],
            'tgl_periksa'   => $data['tgl_periksa'] ?? $pengajuan->tanggal?->toDateString(),
            'penyelenggara' => $pengajuan->kepada,
            'jenis'         => $pengajuan->jenis,
        ]);

        return back()->with('ok', 'Nama ditambahkan ke pengajuan.');
    }

    public function mcuHapusNama(McuPengajuan $pengajuan, PasporMcu $mcu)
    {
        abort_unless($mcu->mcu_pengajuan_id === $pengajuan->id, 404);
        abort_unless($pengajuan->dapatDiubah(), 422,
            'Nama hanya dapat dihapus selagi pengajuan masih draf.');

        $mcu->delete();

        return back()->with('ok', 'Nama dikeluarkan dari pengajuan.');
    }

    /** Mengisi hasil yang kembali dari klinik, per nama. */
    public function mcuIsiHasil(Request $request, McuPengajuan $pengajuan, PasporMcu $mcu)
    {
        abort_unless($mcu->mcu_pengajuan_id === $pengajuan->id, 404);

        $mcu->update($request->validate([
            'tgl_periksa' => ['required', 'date'],
            'tgl_expired' => ['nullable', 'date', 'after_or_equal:tgl_periksa'],
            'nomor'       => ['nullable', 'string', 'max:80'],
            'hasil'       => ['required', Rule::in(Authority::HASIL_MCU)],
            'pembatasan'  => ['nullable', 'string', 'max:500'],
            'rujukan'     => ['nullable', 'string', 'max:200'],
            'outstanding' => ['nullable', 'date'],
        ]));

        Jejak::write('Isi hasil MCU', $mcu->paspor?->nama.' — '.$mcu->hasil, 'authority');

        return back()->with('ok', 'Hasil MCU tersimpan.');
    }

    /* ═══════════ induksi ═══════════ */

    public function simpanInduksi(Request $request, Paspor $paspor)
    {
        $data = $request->validate([
            'nomor_registrasi' => ['nullable', 'string', 'max:60'],
            'jenis'       => ['required', Rule::in(Authority::JENIS_INDUKSI)],
            'tanggal'     => ['required', 'date'],
            'tgl_expired' => ['nullable', 'date', 'after_or_equal:tanggal'],
            'pemberi'     => ['nullable', 'string', 'max:150'],
            'lokasi'      => ['nullable', 'string', 'max:150'],
            'nilai'       => ['nullable', 'integer', 'min:0', 'max:100'],
            'hasil'       => ['required', Rule::in(Authority::HASIL_INDUKSI)],
            'catatan'     => ['nullable', 'string', 'max:1000'],
        ]);

        $paspor->induksi()->create($data);

        Jejak::write('Catat induksi', $paspor->nama.' — '.$data['jenis'], 'authority');

        return back()->with('ok', 'Induksi tercatat.');
    }

    public function hapusInduksi(Paspor $paspor, PasporInduksi $induksi)
    {
        abort_unless($induksi->paspor_id === $paspor->id, 404);

        $induksi->delete();

        return back()->with('ok', 'Catatan induksi dihapus.');
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
        $paspor->kartu()->create($this->aturanKartu($request));

        Jejak::write('Ajukan kartu masuk', $paspor->nama, 'authority');

        return back()->with('ok', 'Pengajuan kartu dibuat sebagai draf.');
    }

    public function ubahKartu(Request $request, Paspor $paspor, PasporKartu $kartu)
    {
        abort_unless($kartu->paspor_id === $paspor->id, 404);
        abort_unless($kartu->dapatDiubah(), 422,
            'Kartu yang sudah diajukan tidak dapat diubah.');

        $kartu->update($this->aturanKartu($request));

        return back()->with('ok', 'Pengajuan kartu diperbarui.');
    }

    public function hapusKartu(Paspor $paspor, PasporKartu $kartu)
    {
        abort_unless($kartu->paspor_id === $paspor->id, 404);

        $kartu->delete();

        return back()->with('ok', 'Kartu dihapus.');
    }

    /** @return array<string,mixed> */
    private function aturanKartu(Request $request): array
    {
        return $request->validate([
            'jenis'        => ['required', Rule::in(Authority::JENIS_KARTU)],
            'sebab_terbit' => ['required', Rule::in(Authority::SEBAB_KARTU)],
            'nomor'        => ['nullable', 'string', 'max:80'],
            'tgl_terbit'   => ['nullable', 'date'],
            'tgl_expired'  => ['nullable', 'date'],
            'golongan'     => ['nullable', 'string', 'max:80'],
            'area'         => ['nullable', 'string', 'max:150'],

            'sim_polisi'         => ['nullable', 'string', 'max:40'],
            'sim_polisi_expired' => ['nullable', 'date'],
            'pengalaman_kerja'   => ['nullable', 'string', 'max:150'],
            'berkas_induksi'     => ['nullable', 'string', 'max:255'],
            'berkas_ddt'         => ['nullable', 'string', 'max:255'],
            'email_atasan'       => ['nullable', 'email', 'max:150'],

            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /* ═══════════ alur persetujuan ═══════════ */

    /**
     * Mengajukan kartu, setelah syaratnya diperiksa.
     *
     * Pemeriksaan syarat dilakukan DI SINI, bukan saat menyimpan draf.
     * Draf memang boleh setengah jadi — memaksa seluruh berkas lengkap
     * sebelum apa pun boleh disimpan membuat orang menyimpannya di luar
     * sistem sampai lengkap, dan yang tersimpan di luar sistem tidak
     * pernah kembali masuk.
     */
    public function ajukanKartu(Paspor $paspor, PasporKartu $kartu)
    {
        abort_unless($kartu->paspor_id === $paspor->id, 404);

        if ($kurang = $kartu->syaratKurang()) {
            return back()->withErrors([
                'kartu' => 'Belum dapat diajukan — '.implode(', ', $kurang).'.',
            ]);
        }

        $kartu->ajukan();

        Jejak::write('Ajukan kartu masuk', $paspor->nama.' — '.$kartu->jenis, 'authority');

        return back()->with('ok', 'Pengajuan kartu dikirim untuk ditinjau.');
    }

    public function tinjauKartu(Request $request, Paspor $paspor, PasporKartu $kartu)
    {
        abort_unless($kartu->paspor_id === $paspor->id, 404);

        return $this->tinjau($request, $kartu, 'kartu masuk', $paspor->nama);
    }

    public function ajukanMcu(McuPengajuan $pengajuan)
    {
        if ($pengajuan->hasil()->doesntExist()) {
            return back()->withErrors([
                'mcu' => 'Pengajuan tanpa satu nama pun tidak dapat dikirim.',
            ]);
        }

        $pengajuan->ajukan();

        Jejak::write('Ajukan MCU', $pengajuan->nomor_register ?? '#'.$pengajuan->id, 'authority');

        return back()->with('ok', 'Pengajuan MCU dikirim untuk ditinjau.');
    }

    public function tinjauMcu(Request $request, McuPengajuan $pengajuan)
    {
        return $this->tinjau($request, $pengajuan, 'pengajuan MCU',
            $pengajuan->nomor_register ?? '#'.$pengajuan->id);
    }

    /**
     * Setujui, tolak, atau tarik — satu jalur untuk kedua model.
     *
     * Haknya tidak diperiksa di sini melainkan di dalam trait Ditinjau,
     * yang melempar bila peninjaunya adalah pengajunya sendiri. Menyalin
     * pemeriksaan itu ke controller akan melahirkan salinan kedua yang
     * cepat atau lambat berselisih dengan aslinya.
     */
    private function tinjau(Request $request, $model, string $apa, string $sebutan)
    {
        $data = $request->validate([
            'aksi'   => ['required', Rule::in(['setujui', 'tolak', 'tarik'])],
            'alasan' => ['required_if:aksi,tolak', 'nullable', 'string', 'max:1000'],
        ]);

        try {
            match ($data['aksi']) {
                'setujui' => $model->setujui(),
                'tolak'   => $model->tolak($data['alasan']),
                'tarik'   => $model->tarik(),
            };
        } catch (\RuntimeException $e) {
            return back()->withErrors(['alur' => $e->getMessage()]);
        }

        Jejak::write(ucfirst($data['aksi']).' '.$apa, $sebutan, 'authority');

        return back()->with('ok', ucfirst($apa).' '.$data['aksi'].'.');
    }

    /* ═══════════ pendukung ═══════════ */

    /** @return array<string,mixed> */
    private function bersama(): array
    {
        return [
            'judul'    => 'Authority — Kelayakan Kerja',
            'subjudul' => 'Kompetensi, MCU, dan kartu masuk tambang dalam satu berkas per orang',
            'opsi' => [
                'klasifikasi'  => Authority::KLASIFIKASI,
                'hasilMcu'     => Authority::HASIL_MCU,
                'jenisKartu'   => Authority::JENIS_KARTU,
                'jenisMcu'     => Authority::JENIS_MCU,
                'sebabKartu'   => Authority::SEBAB_KARTU,
                'jenisInduksi' => Authority::JENIS_INDUKSI,
                'hasilInduksi' => Authority::HASIL_INDUKSI,
                'statusAlur'   => Alur::LABEL,
                'kompetensi'   => KompetensiJenis::aktif()->orderBy('urutan')
                    ->get(['id', 'nama', 'lembaga']),

                /* Daftar orang untuk memilih nama saat menyusun surat
                   pengajuan MCU. Hanya id dan nama — halaman ini tidak
                   perlu seluruh berkasnya, dan mengirimkannya berarti
                   memuat tiga tabel anak bagi setiap orang hanya untuk
                   mengisi sebuah menu pilihan. */
                'orang' => Paspor::orderBy('nama')->get(['id', 'nama', 'nik', 'jabatan']),
            ],
        ];
    }

    /** Bentuk satu baris kartu, dipakai daftar maupun rincian. */
    private function barisKartu(PasporKartu $k): array
    {
        return [
            'id' => $k->id, 'jenis' => $k->jenis, 'nomor' => $k->nomor,
            'sebabTerbit' => $k->sebab_terbit,
            'tglTerbit' => $k->tgl_terbit?->toDateString(),
            'tglExpired' => $k->tgl_expired?->toDateString(),
            'golongan' => $k->golongan, 'area' => $k->area,
            'keadaan' => $k->keadaan(), 'keterangan' => $k->keterangan(),

            'simPolisi' => $k->sim_polisi,
            'simPolisiExpired' => $k->sim_polisi_expired?->toDateString(),
            'pengalamanKerja' => $k->pengalaman_kerja,
            'berkasInduksi' => $k->berkas_induksi,
            'berkasDdt' => $k->berkas_ddt,
            'emailAtasan' => $k->email_atasan,

            'status'        => $k->status,
            'statusLabel'   => Alur::LABEL[$k->status] ?? $k->status,
            'dapatDiubah'   => $k->dapatDiubah(),
            'dapatDitinjau' => $k->dapatDitinjauOleh(auth()->user()),
            'alasanTolak'   => $k->alasan_tolak,
            'syaratKurang'  => $k->syaratKurang(),
            'berlaku'       => $k->sudahDisetujui(),
        ];
    }

    /** Bentuk satu baris orang, sama untuk daftar maupun rincian. */
    private function baris(Paspor $p): array
    {
        $m = $p->mcuTerakhir();
        $k = $p->kartuTerakhir();
        $i = $p->induksiBerlaku();
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
                'status'     => $k->status,
                'berlaku'    => $k->sudahDisetujui(),
            ] : null,

            'induksi' => $i ? [
                'jenis'      => $i->jenis,
                'tglExpired' => $i->tgl_expired?->toDateString(),
                'keadaan'    => $i->keadaan(),
                'keterangan' => $i->keterangan(),
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
        $orang = Paspor::with(['sertifikat', 'mcu', 'kartu', 'induksi'])->get();

        $tglSertifikat = $orang->flatMap(fn ($p) => $p->sertifikat->pluck('tgl_expired'));
        $tglMcu        = $orang->map(fn ($p) => $p->mcuTerakhir()?->tgl_expired)->filter();
        $tglKartu      = $orang->map(fn ($p) => $p->kartuBerlaku()?->tgl_expired)->filter();
        $tglInduksi    = $orang->map(fn ($p) => $p->induksiBerlaku()?->tgl_expired)->filter();

        $takLayak = $orang->filter(fn (Paspor $p) => !$p->kelayakan()['layak']);

        /* Rujukan medis yang tanggal tindak lanjutnya sudah lewat.
           Dihitung terpisah dari kelayakan karena ia tidak menahan orang
           di gerbang — tetapi ia menahan orang dari sembuh, dan tanpa
           satu angka yang menyebutkannya tidak ada yang pernah
           menagihnya. */
        $tertunggak = $orang->sum(
            fn (Paspor $p) => $p->mcu->filter(fn (PasporMcu $m) => $m->rujukanTertunggak())->count()
        );

        return [
            'orang'      => $orang->count(),
            'layak'      => $orang->count() - $takLayak->count(),
            'takLayak'   => $takLayak->count(),
            'sertifikat' => $tglSertifikat->count(),
            'rujukanTertunggak' => $tertunggak,
            'kartuMenunggu' => $orang->sum(
                fn (Paspor $p) => $p->kartu->where('status', Alur::DIAJUKAN)->count()
            ),

            'perKeadaan' => [
                'sertifikat' => Authority::ringkas($tglSertifikat),
                'mcu'        => Authority::ringkas($tglMcu),
                'kartu'      => Authority::ringkas($tglKartu),
                'induksi'    => Authority::ringkas($tglInduksi),
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
