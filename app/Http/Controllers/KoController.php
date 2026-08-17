<?php

namespace App\Http\Controllers;

use App\Rules\DalamPerusahaan;

use App\Models\{ActivityLog, Company, KoAction, KoInspection, KoObject, KoPersonnel,
    KoReview, KoSafeguard, KoUjiKelayakan, KoUnitMaster, User};
use App\Support\Alur;
use App\Support\Ko;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class KoController extends Controller
{
    /* ================= dasar ================= */

    /** superadmin/admin EQOHSEE melihat semua; selain itu terbatas perusahaannya. */
    private function lingkup()
    {
        $u = auth()->user();
        $q = KoObject::query()->with(['company', 'safeguards', 'reviews']);

        if (!$u->isAdmin() && $u->company_id) $q->where('company_id', $u->company_id);

        if ($u->isAdmin()) {
            $co = request('perusahaan');
            if ($co && $co !== 'ALL') $q->where('company_id', (int) $co);
        }

        return $q;
    }

    private function tenagaLingkup()
    {
        $u = auth()->user();
        $q = KoPersonnel::query()->with('company');

        if (!$u->isAdmin() && $u->company_id) $q->where('company_id', $u->company_id);

        if ($u->isAdmin()) {
            $co = request('perusahaan');
            if ($co && $co !== 'ALL') $q->where('company_id', (int) $co);
        }

        return $q;
    }

    /** Boleh menambah/mengubah data KO. */
    private function bolehUbah(): bool
    {
        $u = auth()->user();
        return $u->isAdmin() || $u->ko_role === 'pengawas';
    }

    private function pastikanUbah(): void
    {
        abort_unless($this->bolehUbah(), 403, 'Peran Anda hanya boleh membaca data KO.');
    }

    private function pastikanHapus(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Hanya admin yang boleh menghapus.');
    }

    private function bersama(): array
    {
        return [
            'perusahaan' => Company::orderBy('name')->get(),
            'bolehUbah'  => $this->bolehUbah(),
            'set'        => Ko::settings(),
        ];
    }

    private function catat(string $aksi, string $ket = ''): void
    {
        try { ActivityLog::write($aksi, $ket, 'ko'); } catch (\Throwable $e) {}
    }

    /* ================= 1. Dashboard ================= */

    public function index()
    {
        $objek  = $this->denganStatus($this->lingkup()->get());
        $tenaga = $this->tenagaLingkup()->get();
        $c      = Ko::hitung($objek, $tenaga);

        return Inertia::render('Ko/Halaman', $this->bersama() + ['mode' => 'dashboard',
            'c'          => $c,
            'sub'        => Ko::subElemen($c),
            'peringatan' => array_slice(Ko::peringatan($objek), 0, 12),
            'objek'      => $objek,
            'aksiTerbuka'=> KoAction::whereIn('status', ['Terbuka', 'Berjalan'])->count(),
        ]);
    }

    /* ================= 2. Register objek ================= */

    public function register(Request $r)
    {
        $q = $this->lingkup();

        if ($cari = trim((string) $r->get('q'))) {
            $q->where(function ($w) use ($cari) {
                foreach (['kode', 'nama', 'jenis', 'merk', 'serial_number', 'lokasi'] as $k) {
                    $w->orWhere($k, 'like', "%{$cari}%");
                }
            });
        }
        if ($kat = $r->get('kat'))  $q->where('kategori', $kat);
        if ($ops = $r->get('ops'))  $q->where('status_operasi', $ops);

        $objek = $this->denganStatus($q->orderBy('kode')->get());

        if ($st = $r->get('st')) {
            $objek = $objek->filter(fn ($o) => Ko::status($o) === $st)->values();
        }

        return Inertia::render('Ko/Halaman', $this->bersama() + ['mode' => 'register',
            'objek' => $objek,
            'q'     => $cari ?? '',
            'kat'   => $kat, 'ops' => $ops, 'st' => $st,
        ]);
    }

    public function create()
    {
        $this->pastikanUbah();

        return Inertia::render('Ko/Halaman', $this->bersama() + ['mode' => 'form', 'o' => new KoObject(['interval_tahun' => 3])]);
    }

    public function edit(KoObject $objek)
    {
        $this->pastikanUbah();

        return Inertia::render('Ko/Halaman', $this->bersama() + ['mode' => 'form', 'o' => $objek]);
    }

    public function store(Request $r)
    {
        $this->pastikanUbah();
        $d = $this->validasiObjek($r);

        $o = KoObject::create($d);
        $this->catat('Tambah objek KO', $o->kode . ' — ' . $o->nama);

        return redirect()->route('ko.show', $o)->with('ok', 'Objek ' . $o->kode . ' ditambahkan.');
    }

    public function update(Request $r, KoObject $objek)
    {
        $this->pastikanUbah();
        $d = $this->validasiObjek($r, $objek->id);

        $objek->update($d);
        $this->catat('Ubah objek KO', $objek->kode . ' — ' . $objek->nama);

        return redirect()->route('ko.show', $objek)->with('ok', 'Objek ' . $objek->kode . ' diperbarui.');
    }

    public function destroy(KoObject $objek)
    {
        $this->pastikanHapus();

        $kode = $objek->kode;
        $objek->delete();
        $this->catat('Hapus objek KO', $kode);

        return redirect()->route('ko.register')->with('ok', 'Objek ' . $kode . ' dihapus.');
    }

    private function validasiObjek(Request $r, ?int $abaikan = null): array
    {
        $unik = 'unique:ko_objects,kode' . ($abaikan ? ',' . $abaikan : '');

        return $this->pemilik($r->validate([
            'kode'            => ['required', 'string', 'max:50', $unik],
            'nama'            => ['required', 'string', 'max:200'],
            'kategori'        => ['required', 'in:' . implode(',', Ko::KATEGORI)],
            'jenis'           => ['nullable', 'string', 'max:100'],
            'ko_unit_master_id' => ['nullable', new DalamPerusahaan('ko_unit_master')],
            'merk'            => ['nullable', 'string', 'max:150'],
            'serial_number'   => ['nullable', 'string', 'max:100'],
            'lokasi'          => ['nullable', 'string', 'max:150'],
            'company_id'      => ['nullable', 'exists:companies,id'],
            'kritikalitas'    => ['required', 'in:' . implode(',', Ko::KRITIS)],
            'status_operasi'  => ['required', 'in:' . implode(',', Ko::OPERASI)],
            'tgl_sertifikasi' => ['nullable', 'date'],
            'interval_tahun'  => ['required', 'integer', 'in:' . implode(',', Ko::INTERVAL)],
            'no_sertifikat'   => ['nullable', 'string', 'max:100'],
            'lembaga_uji'     => ['nullable', 'string', 'max:150'],
            'lapor_kait'      => ['nullable', 'boolean'],
            'pm_jenis'        => ['nullable', 'string', 'max:150'],
            'pm_terakhir'     => ['nullable', 'date'],
            'pm_berikutnya'   => ['nullable', 'date'],
            'keterangan'      => ['nullable', 'string', 'max:2000'],
        ])) + ['lapor_kait' => (bool) $r->boolean('lapor_kait')];
    }

    /* ================= 3. Rincian objek ================= */

    public function show(KoObject $objek)
    {
        $objek->load(['company', 'safeguards', 'reviews.personnel', 'inspections.personnel', 'actions.pic']);
        $objek->setAttribute('status_ko', Ko::status($objek));

        return Inertia::render('Ko/Halaman', $this->bersama() + ['mode' => 'rincian',
            'o'      => $objek,
            'tenaga' => $this->tenagaLingkup()->orderBy('nama')->get(),
            'user'   => User::orderBy('name')->get(),
            'sigapAda' => Schema::hasTable('sigap_assets'),
        ]);
    }

    /* ================= 4. Kelayakan ================= */

    public function kelayakan()
    {
        $objek = $this->denganStatus(
            $this->lingkup()->with(['uji', 'unitMaster'])->orderBy('kode')->get()
        )->sortBy(fn ($o) => Ko::sisaHari($o) ?? -99999)->values();

        /* Selisih antara tanggal sertifikasi unit dan uji terakhirnya
           ditampilkan sebagai temuan. Salah satu dari dua hal sedang
           terjadi — kolomnya disunting tangan tanpa uji, atau ada uji
           yang disetujui tetapi gagal memperbarui unitnya — dan keduanya
           perlu dilihat orang. */
        $berselisih = $objek->filter(fn (KoObject $o) => $o->sertifikasiBerselisih())
            ->map(fn (KoObject $o) => [
                'id' => $o->id, 'kode' => $o->kode, 'nama' => $o->nama,
                'unit'  => $o->kadaluarsa?->toDateString(),
                'uji'   => $o->ujiTerakhir()?->tgl_expired?->toDateString(),
            ])->values();

        return Inertia::render('Ko/Halaman', $this->bersama() + ['mode' => 'kelayakan',
            'objek' => $objek,
            'c'     => Ko::hitung($objek, $this->tenagaLingkup()->get()),
            'berselisih' => $berselisih,
        ]);
    }

    /* ================= 4b. Daftar acuan SPIP ================= */

    /**
     * Master jenis unit — daftar yang dipelihara pemakainya sendiri.
     *
     * Gunanya menyeragamkan penulisan. Tanpa daftar ini "Dump Truck",
     * "Dumptruck", dan "DT" adalah tiga jenis berbeda di mata sistem:
     * rekap per jenis tidak dapat dipercaya dan penyaringan kehilangan
     * sebagian barisnya — tanpa galat, hanya angka yang salah.
     */
    public function unit(Request $r)
    {
        $master = KoUnitMaster::withCount('objek')
            ->orderBy('urutan')->orderBy('kode')->get();

        return Inertia::render('Ko/Unit', $this->bersama() + ['mode' => 'unit',
            'unit' => $master->map(fn (KoUnitMaster $u) => [
                'id' => $u->id, 'kode' => $u->kode, 'unit' => $u->unit,
                'kategori' => $u->kategori, 'interval' => $u->interval_tahun,
                'keterangan' => $u->keterangan, 'aktif' => $u->aktif,
                'urutan' => $u->urutan, 'dipakai' => $u->objek_count,

                /* Baris milik bersama (company_id NULL) tidak boleh
                   disunting satu perusahaan — perubahannya akan terlihat
                   oleh semua. */
                'bersama' => $u->company_id === null,
            ])->values(),

            /* Unit yang jenisnya masih diketik bebas. Disebut jumlahnya
               supaya penyeragaman punya sasaran yang terlihat, bukan
               sekadar anjuran. */
            'belumTertaut' => $this->lingkup()->whereNull('ko_unit_master_id')->count(),
        ]);
    }

    public function simpanUnit(Request $r)
    {
        $this->pastikanUbah();

        $data = $r->validate([
            'kode'           => ['required', 'string', 'max:30'],
            'unit'           => ['required', 'string', 'max:150'],
            'kategori'       => ['nullable', Rule::in(Ko::KATEGORI)],
            'interval_tahun' => ['required', 'integer', 'min:1', 'max:20'],
            'keterangan'     => ['nullable', 'string', 'max:500'],
            'urutan'         => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $milik = auth()->user()?->company_id;

        /* Kode unik per pemilik. Diperiksa di sini juga, bukan hanya
           mengandalkan indeks basis data: pelanggaran indeks memulangkan
           galat SQL mentah yang tidak berarti apa-apa bagi pemakainya. */
        $bentrok = KoUnitMaster::withoutGlobalScopes()
            ->where('company_id', $milik)->where('kode', $data['kode'])->exists();

        if ($bentrok) {
            return back()->withErrors(['kode' => 'Kode "'.$data['kode'].'" sudah dipakai.']);
        }

        KoUnitMaster::create($data);

        $this->catat('Tambah jenis unit SPIP', $data['kode'].' — '.$data['unit']);

        return back()->with('ok', 'Jenis unit ditambahkan.');
    }

    public function ubahUnit(Request $r, KoUnitMaster $unit)
    {
        $this->pastikanUbah();

        /* Baris milik bersama tidak disunting satu perusahaan:
           perubahannya terlihat oleh semua, dan tidak ada satu pun
           perusahaan yang berhak memutuskannya sendiri. */
        abort_if($unit->company_id === null && !auth()->user()?->isAdmin(), 403,
            'Jenis unit milik bersama hanya dapat diubah administrator.');

        $unit->update($r->validate([
            'kode'           => ['required', 'string', 'max:30'],
            'unit'           => ['required', 'string', 'max:150'],
            'kategori'       => ['nullable', Rule::in(Ko::KATEGORI)],
            'interval_tahun' => ['required', 'integer', 'min:1', 'max:20'],
            'keterangan'     => ['nullable', 'string', 'max:500'],
            'aktif'          => ['nullable', 'boolean'],
            'urutan'         => ['nullable', 'integer', 'min:0', 'max:999'],
        ]));

        return back()->with('ok', 'Jenis unit diperbarui.');
    }

    /**
     * Menonaktifkan, bukan menghapus, bila sudah dipakai.
     *
     * Menghapus jenis yang sudah menaut ke unit akan memutus tautannya
     * menjadi NULL — dan unit-unit itu diam-diam kembali tanpa jenis,
     * persis keadaan yang hendak dihilangkan daftar ini.
     */
    public function hapusUnit(KoUnitMaster $unit)
    {
        $this->pastikanHapus();

        abort_if($unit->company_id === null && !auth()->user()?->isAdmin(), 403,
            'Jenis unit milik bersama hanya dapat diubah administrator.');

        if ($unit->objek()->exists()) {
            $unit->update(['aktif' => false]);

            return back()->with('ok',
                'Jenis unit masih dipakai, jadi dinonaktifkan — bukan dihapus. '
                .'Ia tidak lagi muncul sebagai pilihan baru, tetapi unit yang sudah '
                .'menautnya tetap punya jenis.');
        }

        $unit->delete();

        return back()->with('ok', 'Jenis unit dihapus.');
    }

    /* ================= 4c. Uji kelayakan ================= */

    public function uji(Request $r)
    {
        $q = KoUjiKelayakan::with(['objek', 'company', 'pengaju', 'peninjau'])
            ->whereIn('ko_object_id', $this->lingkup()->select('ko_objects.id'));

        if ($st = $r->get('st'))       $q->where('status', $st);
        if ($h  = $r->get('hasil'))    $q->where('hasil', $h);

        $baris = $q->orderByDesc('tgl_inspeksi')->get();

        return Inertia::render('Ko/Uji', $this->bersama() + ['mode' => 'uji',
            'uji' => $baris->map(fn (KoUjiKelayakan $u) => [
                'id'        => $u->id,
                'objekId'   => $u->ko_object_id,
                'kode'      => $u->objek?->kode,
                'namaObjek' => $u->objek?->nama,
                'perusahaan' => $u->company?->name,
                'nomor'     => $u->nomor,
                'merk'      => $u->merk, 'tipe' => $u->tipe, 'seri' => $u->nomor_seri,
                'tglInspeksi' => $u->tgl_inspeksi?->toDateString(),
                'tglExpired'  => $u->tgl_expired?->toDateString(),
                'pemeriksa' => $u->pemeriksa, 'lembaga' => $u->lembaga,
                'hasil'     => $u->hasil, 'lolos' => $u->lolos(),
                'syarat'    => $u->syarat, 'temuan' => $u->temuan,
                'rekomendasi' => $u->rekomendasi,
                'keadaan'   => $u->keadaan(), 'keterangan' => $u->keterangan(),

                'status'        => $u->status,
                'statusLabel'   => Alur::LABEL[$u->status] ?? $u->status,
                'dapatDiubah'   => $u->dapatDiubah(),
                'dapatDitinjau' => $u->dapatDitinjauOleh($r->user()),
                'alasanTolak'   => $u->alasan_tolak,
            ])->values(),

            'st'    => $st,
            'hasil' => $h,
            'objek' => $this->lingkup()->orderBy('kode')->get(['id', 'kode', 'nama', 'merk', 'serial_number']),

            'ringkasUji' => [
                'total'    => $baris->count(),
                'menunggu' => $baris->where('status', Alur::DIAJUKAN)->count(),
                'tidakLolos' => $baris->filter(
                    fn ($u) => $u->sudahDisetujui() && !$u->lolos())->count(),
                'bersyarat' => $baris->where('hasil', 'Layak Bersyarat')
                    ->filter(fn ($u) => $u->sudahDisetujui())->count(),
            ],
        ]);
    }

    public function simpanUji(Request $r)
    {
        $this->pastikanUbah();

        $data = $r->validate([
            'ko_object_id' => ['required', new DalamPerusahaan('ko_objects')],
            'nomor'        => ['nullable', 'string', 'max:80'],
            'merk'         => ['nullable', 'string', 'max:100'],
            'tipe'         => ['nullable', 'string', 'max:100'],
            'nomor_seri'   => ['nullable', 'string', 'max:100'],
            'tgl_inspeksi' => ['required', 'date'],
            'tgl_expired'  => ['nullable', 'date', 'after_or_equal:tgl_inspeksi'],
            'pemeriksa'    => ['nullable', 'string', 'max:150'],
            'lembaga'      => ['nullable', 'string', 'max:150'],
            'lokasi_uji'   => ['nullable', 'string', 'max:150'],
            'hasil'        => ['required', Rule::in(KoUjiKelayakan::HASIL)],

            /* Syarat WAJIB bila hasilnya bersyarat. "Layak Bersyarat"
               tanpa syarat yang tertulis adalah izin operasi penuh yang
               menyamar sebagai izin bersyarat — dan yang membacanya di
               lapangan tidak punya cara tahu apa yang harus dijaga. */
            'syarat'       => ['required_if:hasil,Layak Bersyarat', 'nullable', 'string', 'max:1000'],
            'temuan'       => ['nullable', 'string', 'max:2000'],
            'rekomendasi'  => ['nullable', 'string', 'max:2000'],
        ], [
            'syarat.required_if' => 'Hasil "Layak Bersyarat" harus menyebutkan syaratnya.',
        ]);

        $objek = KoObject::findOrFail($data['ko_object_id']);

        /* Merk dan seri disalin dari unitnya bila tidak diisi — sebagai
           NILAI, bukan rujukan. Unit yang kemudian dikoreksi datanya
           tidak boleh mengubah bunyi sertifikat yang sudah terbit. */
        $data['merk']       = $data['merk']       ?? $objek->merk;
        $data['nomor_seri'] = $data['nomor_seri'] ?? $objek->serial_number;
        $data['company_id']   = $objek->company_id;

        /* Tanggal kadaluarsa diusulkan dari interval jenis unitnya bila
           kosong. Diusulkan di sini, bukan dibiarkan kosong: uji tanpa
           tanggal habis tidak pernah muncul di daftar yang akan jatuh
           tempo, dan alat yang tidak pernah muncul tidak pernah diuji
           ulang. */
        /* `?? null`, bukan langsung $data['tgl_expired']: aturan
           'nullable' tidak membuat kuncinya bila medannya sama sekali
           tidak dikirim, dan blank() atas kunci yang tidak ada adalah
           galat — bukan false. */
        if (blank($data['tgl_expired'] ?? null)) {
            $tahun = $objek->unitMaster?->interval_tahun ?? $objek->interval_tahun ?? 1;

            $data['tgl_expired'] = Carbon::parse($data['tgl_inspeksi'])
                ->addYears((int) $tahun)->toDateString();
        }

        KoUjiKelayakan::create($data);

        $this->catat('Catat uji kelayakan', $objek->kode.' — '.$data['hasil']);

        return back()->with('ok', 'Uji kelayakan tersimpan sebagai draf.');
    }

    public function hapusUji(KoUjiKelayakan $uji)
    {
        $this->pastikanHapus();

        abort_unless($uji->dapatDiubah(), 422,
            'Uji yang sudah diajukan tidak dapat dihapus.');

        $uji->delete();

        return back()->with('ok', 'Uji kelayakan dihapus.');
    }

    public function ajukanUji(KoUjiKelayakan $uji)
    {
        $this->pastikanUbah();

        $uji->ajukan();

        return back()->with('ok', 'Uji kelayakan dikirim untuk ditinjau.');
    }

    /**
     * Menyetujui uji ikut memperbarui sertifikasi unitnya.
     *
     * Di sinilah kedua sumber disatukan: ko_objects.tgl_sertifikasi
     * tetap menjadi "keadaan sekarang" yang dibaca Ko::status() di lima
     * halaman, dan uji yang disetujui yang memperbaruinya. Tanpa
     * penyatuan ini, mencatat uji baru tidak akan mengubah status unit
     * sama sekali — orang akan mengisi formulirnya, melihat statusnya
     * tetap "Kadaluarsa", dan menyimpulkan fiturnya rusak.
     *
     * HANYA YANG LOLOS yang memperbarui. Uji dengan hasil "Tidak Layak"
     * memang seharusnya meninggalkan unitnya kadaluarsa — memperbarui
     * tanggalnya justru akan memperpanjang izin operasi alat yang baru
     * saja dinyatakan tidak layak.
     */
    public function tinjauUji(Request $r, KoUjiKelayakan $uji)
    {
        $data = $r->validate([
            'aksi'   => ['required', Rule::in(['setujui', 'tolak', 'tarik'])],
            'alasan' => ['required_if:aksi,tolak', 'nullable', 'string', 'max:1000'],
        ]);

        try {
            match ($data['aksi']) {
                'setujui' => $uji->setujui(),
                'tolak'   => $uji->tolak($data['alasan']),
                'tarik'   => $uji->tarik(),
            };
        } catch (\RuntimeException $e) {
            return back()->withErrors(['alur' => $e->getMessage()]);
        }

        if ($data['aksi'] === 'setujui' && $uji->lolos()) {
            $this->selaraskanSertifikasi($uji);
        }

        $this->catat(ucfirst($data['aksi']).' uji kelayakan',
            $uji->objek?->kode.' — '.$uji->hasil);

        return back()->with('ok', 'Uji kelayakan '.$data['aksi'].'.');
    }

    /**
     * Menyalin hasil uji ke kolom sertifikasi unitnya.
     *
     * Hanya bila uji ini memang yang TERBARU. Menyetujui uji lama yang
     * tertunda — biasa terjadi ketika berkas menyusul berbulan-bulan —
     * tidak boleh memundurkan sertifikasi unit yang sudah diperbarui
     * uji sesudahnya.
     */
    private function selaraskanSertifikasi(KoUjiKelayakan $uji): void
    {
        $terbaru = $uji->objek?->uji()->disetujui()->first();

        if (!$terbaru || $terbaru->getKey() !== $uji->getKey()) return;

        $uji->objek->update([
            'tgl_sertifikasi' => $uji->tgl_inspeksi,
            'no_sertifikat'   => $uji->nomor ?: $uji->objek->no_sertifikat,
            'lembaga_uji'     => $uji->lembaga ?: $uji->objek->lembaga_uji,

            /* Interval dihitung dari selisih tanggalnya yang
               sesungguhnya, bukan diambil dari bawaan jenis unit:
               lembaga uji dapat menetapkan masa berlaku yang berbeda,
               dan yang berlaku adalah yang tertulis di sertifikatnya. */
            'interval_tahun'  => $uji->tgl_expired
                ? max(1, (int) round($uji->tgl_inspeksi->diffInDays($uji->tgl_expired) / 365))
                : $uji->objek->interval_tahun,
        ]);
    }

    /* ================= 5. Perawatan ================= */

    public function perawatan()
    {
        $objek = $this->denganStatus($this->lingkup()->orderBy('kode')->get())
            ->sortBy(fn ($o) => Ko::sisaPm($o) ?? 99999)->values();

        return Inertia::render('Ko/Halaman', $this->bersama() + ['mode' => 'perawatan',
            'objek' => $objek,
            'c'     => Ko::hitung($objek, $this->tenagaLingkup()->get()),
        ]);
    }

    public function catatPm(Request $r, KoObject $objek)
    {
        $this->pastikanUbah();

        $d = $r->validate([
            'tanggal'    => ['required', 'date'],
            'berikutnya' => ['nullable', 'date'],
            'hasil'      => ['nullable', 'string', 'max:100'],
            'catatan'    => ['nullable', 'string', 'max:1000'],
            'ko_personnel_id' => ['nullable', new DalamPerusahaan('ko_personnel')],
        ]);

        KoInspection::create($d + [
            'ko_object_id' => $objek->id,
            'jenis'        => 'PM',
            'user_id'      => auth()->id(),
        ]);

        $objek->update([
            'pm_terakhir'   => $d['tanggal'],
            'pm_berikutnya' => $d['berikutnya'] ?? $objek->pm_berikutnya,
        ]);

        $this->catat('Catat perawatan', $objek->kode);

        return back()->with('ok', 'Perawatan ' . $objek->kode . ' dicatat.');
    }

    /* ================= 6. Pengaman ================= */

    public function pengaman()
    {
        $objek = $this->denganStatus($this->lingkup()->orderBy('kode')->get())->values();

        return Inertia::render('Ko/Halaman', $this->bersama() + ['mode' => 'pengaman',
            'objek' => $objek,
            'c'     => Ko::hitung($this->lingkup()->get(), $this->tenagaLingkup()->get()),
            'sigapAda' => Schema::hasTable('sigap_assets'),
        ]);
    }

    public function simpanPengaman(Request $r, KoObject $objek)
    {
        $this->pastikanUbah();

        $d = $r->validate([
            'id'          => ['nullable', 'exists:ko_safeguards,id'],
            'nama'        => ['required', 'string', 'max:150'],
            'spesifikasi' => ['nullable', 'string', 'max:150'],
            'status'      => ['required', 'in:' . implode(',', Ko::PENGAMAN_STATUS)],
            'tgl_periksa' => ['nullable', 'date'],
            'catatan'     => ['nullable', 'string', 'max:1000'],
        ]);

        $p = $d['id']
            ? KoSafeguard::where('ko_object_id', $objek->id)->findOrFail($d['id'])
            : new KoSafeguard(['ko_object_id' => $objek->id]);

        $p->fill(collect($d)->except('id')->all());
        $p->save();

        if ($p->tgl_periksa) {
            KoInspection::create([
                'ko_object_id'    => $objek->id,
                'ko_safeguard_id' => $p->id,
                'jenis'           => 'Pengaman',
                'tanggal'         => $p->tgl_periksa,
                'hasil'           => $p->status,
                'nilai_ukur'      => $p->spesifikasi,
                'catatan'         => $p->catatan,
                'user_id'         => auth()->id(),
            ]);
        }

        $this->catat('Simpan pengaman', $objek->kode . ' — ' . $p->nama);

        return back()->with('ok', 'Perangkat pengaman tersimpan.');
    }

    public function hapusPengaman(KoObject $objek, KoSafeguard $pengaman)
    {
        $this->pastikanHapus();
        abort_unless($pengaman->ko_object_id === $objek->id, 404);

        $nama = $pengaman->nama;
        $pengaman->delete();
        $this->catat('Hapus pengaman', $objek->kode . ' — ' . $nama);

        return back()->with('ok', 'Perangkat pengaman dihapus.');
    }

    /* ================= 7. Kajian teknis ================= */

    public function kajian()
    {
        $kajian = KoReview::with(['object.company', 'personnel'])
            ->whereIn('ko_object_id', $this->lingkup()->select('ko_objects.id'))
            ->latest('tanggal')->get();

        return Inertia::render('Ko/Halaman', $this->bersama() + ['mode' => 'kajian',
            'kajian' => $kajian,
            'objek'  => $this->lingkup()->orderBy('kode')->get(),
            'tenaga' => $this->tenagaLingkup()->orderBy('nama')->get(),
        ]);
    }

    public function simpanKajian(Request $r)
    {
        $this->pastikanUbah();

        $d = $r->validate([
            'id'              => ['nullable', 'exists:ko_reviews,id'],
            'ko_object_id'    => ['required', new DalamPerusahaan('ko_objects')],
            'judul'           => ['required', 'string', 'max:200'],
            'pemicu'          => ['nullable', 'string', 'max:100'],
            'tanggal'         => ['nullable', 'date'],
            'ko_personnel_id' => ['nullable', new DalamPerusahaan('ko_personnel')],
            'status'          => ['required', 'in:' . implode(',', Ko::KAJIAN_STATUS)],
            'tgl_lapor'       => ['nullable', 'date'],
            'ringkasan'       => ['nullable', 'string', 'max:2000'],
        ]);

        $k = $d['id'] ? KoReview::findOrFail($d['id']) : new KoReview();
        $k->fill(collect($d)->except('id')->all());

        if ($k->ko_personnel_id) {
            $k->oleh = optional(KoPersonnel::find($k->ko_personnel_id))->nama;
        }
        $k->save();

        $this->catat('Simpan kajian teknis', $k->judul);

        return back()->with('ok', 'Kajian teknis tersimpan.');
    }

    public function hapusKajian(KoReview $kajian)
    {
        $this->pastikanHapus();

        $j = $kajian->judul;
        $kajian->delete();
        $this->catat('Hapus kajian teknis', $j);

        return back()->with('ok', 'Kajian dihapus.');
    }

    /* ================= 8. Tenaga teknis ================= */

    public function tenaga()
    {
        return Inertia::render('Ko/Halaman', $this->bersama() + ['mode' => 'tenaga',
            'tenaga' => $this->tenagaLingkup()->orderBy('nama')->get(),
            'user'   => User::orderBy('name')->get(),
        ]);
    }

    public function simpanTenaga(Request $r)
    {
        $this->pastikanUbah();

        $d = $this->pemilik($r->validate([
            'id'             => ['nullable', 'exists:ko_personnel,id'],
            'nama'           => ['required', 'string', 'max:150'],
            'jabatan'        => ['nullable', 'string', 'max:150'],
            'company_id'     => ['nullable', 'exists:companies,id'],
            'sertifikasi'    => ['nullable', 'string', 'max:150'],
            'no_sertifikat'  => ['nullable', 'string', 'max:100'],
            'tgl_kadaluarsa' => ['nullable', 'date'],
            'user_id'        => ['nullable', 'exists:users,id'],
        ]));

        $t = $d['id'] ? KoPersonnel::findOrFail($d['id']) : new KoPersonnel();
        $t->fill(collect($d)->except('id')->all());
        $t->save();

        $this->catat('Simpan tenaga teknis', $t->nama);

        return back()->with('ok', 'Data tenaga teknis tersimpan.');
    }

    public function hapusTenaga(KoPersonnel $tenaga)
    {
        $this->pastikanHapus();

        $n = $tenaga->nama;
        $tenaga->delete();
        $this->catat('Hapus tenaga teknis', $n);

        return back()->with('ok', 'Tenaga teknis dihapus.');
    }

    /* ================= 9. Tindak lanjut ================= */

    public function tindak(Request $r)
    {
        $q = KoAction::with(['object.company', 'safeguard', 'pic'])
            ->whereIn('ko_object_id', $this->lingkup()->select('ko_objects.id'));

        if ($st = $r->get('st')) $q->where('status', $st);

        return Inertia::render('Ko/Halaman', $this->bersama() + ['mode' => 'tindak',
            'aksi'  => $q->orderByRaw("CASE status WHEN 'Terbuka' THEN 0 WHEN 'Berjalan' THEN 1 ELSE 2 END")
                         ->orderBy('target_tgl')->get(),
            'st'    => $st,
            'objek' => $this->lingkup()->orderBy('kode')->get(),
            'user'  => User::orderBy('name')->get(),
        ]);
    }

    /** Membuat tindak lanjut dari peringatan yang belum punya tindak lanjut terbuka. */
    public function tarikPeringatan()
    {
        $this->pastikanUbah();

        $objek = $this->lingkup()->get();
        $baru  = 0;

        foreach (Ko::peringatan($objek) as $p) {
            $o  = $p['objek'];
            $pg = $p['pengaman'] ?? null;

            $ada = KoAction::where('ko_object_id', $o->id)
                ->where('sumber', $p['sumber'])
                ->where('ko_safeguard_id', $pg?->id)
                ->whereIn('status', ['Terbuka', 'Berjalan'])
                ->exists();

            if ($ada) continue;

            KoAction::create([
                'ko_object_id'    => $o->id,
                'ko_safeguard_id' => $pg?->id,
                'sumber'          => $p['sumber'],
                'uraian'          => $p['jenis'] . ' — ' . $p['ket'],
                'prioritas'       => $p['pr'] === 0 ? 'Tinggi' : ($p['pr'] === 1 ? 'Sedang' : 'Rendah'),
                'target_tgl'      => now()->addDays($p['pr'] === 0 ? 7 : 30)->toDateString(),
                'status'          => 'Terbuka',
            ]);
            $baru++;
        }

        $this->catat('Tarik peringatan jadi tindak lanjut', $baru . ' item');

        return back()->with('ok', $baru
            ? $baru . ' tindak lanjut dibuat dari peringatan.'
            : 'Semua peringatan sudah punya tindak lanjut terbuka.');
    }

    public function simpanTindak(Request $r)
    {
        $this->pastikanUbah();

        $d = $r->validate([
            'id'           => ['nullable', 'exists:ko_actions,id'],
            'ko_object_id' => ['required', new DalamPerusahaan('ko_objects')],
            'sumber'       => ['required', 'string', 'max:50'],
            'uraian'       => ['required', 'string', 'max:2000'],
            'prioritas'    => ['required', 'in:Tinggi,Sedang,Rendah'],
            'pic_user_id'  => ['nullable', 'exists:users,id'],
            'pic_nama'     => ['nullable', 'string', 'max:150'],
            'target_tgl'   => ['nullable', 'date'],
            'status'       => ['required', 'in:' . implode(',', Ko::AKSI_STATUS)],
            'tgl_selesai'  => ['nullable', 'date'],
            'tindakan'     => ['nullable', 'string', 'max:2000'],
        ]);

        $a = $d['id'] ? KoAction::findOrFail($d['id']) : new KoAction();
        $a->fill(collect($d)->except('id')->all());

        if ($a->status === 'Selesai' && !$a->tgl_selesai) $a->tgl_selesai = now()->toDateString();
        $a->save();

        $this->catat('Simpan tindak lanjut KO', $a->uraian);

        return back()->with('ok', 'Tindak lanjut tersimpan.');
    }

    public function hapusTindak(KoAction $tindak)
    {
        $this->pastikanHapus();

        $tindak->delete();
        $this->catat('Hapus tindak lanjut KO');

        return back()->with('ok', 'Tindak lanjut dihapus.');
    }

    /* ================= 10. Pengaturan ================= */

    public function pengaturan()
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        return Inertia::render('Ko/Halaman', $this->bersama() + ['mode' => 'pengaturan']);
    }

    public function simpanPengaturan(Request $r)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $d = $r->validate([
            'ko_warn_days'    => ['required', 'integer', 'min:1', 'max:365'],
            'ko_target_layak' => ['required', 'integer', 'min:1', 'max:100'],
            'ko_target_pmc'   => ['required', 'integer', 'min:1', 'max:100'],
            'ko_iv_peralatan' => ['required', 'integer', 'in:' . implode(',', Ko::INTERVAL)],
            'ko_iv_instalasi' => ['required', 'integer', 'in:' . implode(',', Ko::INTERVAL)],
        ]);

        Ko::simpanSettings($d);
        $this->catat('Ubah pengaturan KO', 'Ambang jatuh tempo: ' . $d['ko_warn_days'] . ' hari');

        return back()->with('ok', 'Pengaturan KO tersimpan.');
    }

    private function denganStatus($objek)
    {
        return $objek->each(fn ($o) => $o->setAttribute('status_ko', Ko::status($o)));
    }
}
