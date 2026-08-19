<?php

namespace App\Http\Controllers;

use App\Models\{Company, KompetensiJenis, McuPengajuan, MinersCampaign, MinersCuti,
    MinersCutiJatah, MinersFieldBreak, Paspor, PasporInduksi, PasporKartuUnit,
    PasporKartu, PasporMcu, PasporSertifikat};
use App\Rules\DalamPerusahaan;
use App\Models\ActivityLog as Jejak;
use App\Support\Alur;
use App\Support\AlurMiner;
use App\Support\Authority;
use App\Support\Berkas;
use App\Support\MasaBerlakuTerbaca;
use App\Support\PemantauanBerkas;
use App\Support\JatahCuti;
use App\Support\KopDokumen;
use App\Support\Tahap;
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
class MinersController extends Controller
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

        return Inertia::render('Miners/Halaman', $this->bersama() + [
            'mode'   => 'daftar',
            'orang'  => $orang->map(fn (Paspor $p) => $this->baris($p))->values(),
            'saring' => [
                'q' => $cari, 'klas' => $request->get('klas'), 'keadaan' => $keadaan,
            ],
            'ringkas' => $this->ringkasan(),
        ]);
    }

    /**
     * Pemantauan masa berlaku Mine Permit dan SIMPER.
     *
     * Halaman tersendiri, bukan kolom tambahan pada daftar orang.
     * Pertanyaannya berbeda: daftar orang menjawab "siapa saja pekerja
     * kita", yang ini menjawab "siapa yang hari ini tidak boleh masuk".
     * Keduanya dibaca orang yang berbeda pada waktu yang berbeda.
     */
    public function kedaluwarsa(Request $request)
    {
        $jenis = $request->get('jenis');
        if (!in_array($jenis, PemantauanBerkas::JENIS, true)) $jenis = null;

        $status = in_array($st = $request->get('status'), ['aktif', 'cuti', 'keluar'], true) ? $st : null;

        $orang = Paspor::with(['kartu', 'mcu', 'company'])
            ->when($cari = trim((string) $request->get('q')), fn ($q) => $q->where(
                fn ($w) => $w->where('nama', 'like', "%{$cari}%")
                    ->orWhere('nik', 'like', "%{$cari}%")
                    ->orWhere('jabatan', 'like', "%{$cari}%")))
            ->when($request->get('perusahaan'), fn ($q, $c) => $q->where('company_id', $c))
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('nama')
            ->get();

        $baris = PemantauanBerkas::baris($orang, $jenis);

        /* Ringkasan dihitung SEBELUM penyaring keadaan dipasang.
           Sesudahnya, memilih "habis" akan membuat kartu ringkasannya
           menyebut 100% habis — angka yang benar untuk daftar yang
           tersaring dan menyesatkan sebagai gambaran keadaan. */
        $ringkas      = PemantauanBerkas::ringkas($baris);
        $perPerusahaan = PemantauanBerkas::perPerusahaan($baris);

        if ($keadaan = $request->get('keadaan')) {
            $baris = array_values(array_filter($baris, fn ($b) => $b['keadaan'] === $keadaan));
        }

        /* Judul halaman ditulis SEBELUM bersama(). Operator + memakai
           nilai dari operan KIRI bila kuncinya bertabrakan, jadi urutan
           terbalik membuat judul modul menimpa judul halaman ini tanpa
           galat apa pun — yang terlihat hanya bilah atas yang menyebut
           halaman lain. */
        return Inertia::render('Miners/Kedaluwarsa', [
            'judul'    => 'Miners — Masa Berlaku Berkas',
            'subjudul' => 'MCU, Mine Permit, dan SIMPER yang perlu diurus',
        ] + $this->bersama() + [
            'baris'         => $baris,
            'ringkas'       => $ringkas,
            'perPerusahaan' => $perPerusahaan,

            'saring' => [
                'q'          => $cari,
                'jenis'      => $jenis,
                'keadaan'    => $keadaan,
                'perusahaan' => $request->get('perusahaan'),
                'status'     => $status,
            ],

            'opsiJenis'   => PemantauanBerkas::JENIS,
            'opsiStatus'  => ['aktif' => 'Aktif', 'cuti' => 'Cuti', 'keluar' => 'Sudah keluar'],
            'opsiKeadaan' => collect(Authority::LABEL_KARTU)
                ->map(fn ($label, $kode) => ['kode' => $kode, 'label' => $label])
                ->values()->all(),
            'opsiPerusahaan' => Company::query()
                ->when(!auth()->user()?->isAdmin(), fn ($q) => $q->whereKey(auth()->user()?->company_id))
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Paspor $paspor)
    {
        $paspor->load(['sertifikat.jenis', 'mcu.pengajuan', 'kartu.paraf', 'kartu.unit.unitMaster', 'induksi', 'user', 'company']);

        return Inertia::render('Miners/Halaman', $this->bersama() + [
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
            /* Orangnya dipasang balik ke tiap kartunya: keadaan kartu
               dihitung atas tanggal efektif, yang bagi Mine Permit
               diambil dari MCU orang itu lewat $kartu->paspor — relasi
               yang tidak ikut terisi saat kartunya dimuat sebagai anak.
               Tanpa ini, dua kueri tambahan per kartu untuk mengambil
               baris yang sudah ada di memori. */
            'kartu' => $paspor->kartu
                ->each(fn (PasporKartu $k) => $k->setRelation('paspor', $paspor))
                ->map(fn (PasporKartu $k) => $this->barisKartu($k))->values(),

            /* Urutan tahapannya digambar di layar rincian. Tanpa gambar
               itu, orang menebak sendiri apa yang harus dikerjakan
               berikutnya — dan tebakan yang salah menghasilkan formulir
               yang ditolak tanpa ia tahu mengapa. */
            'tahapan' => AlurMiner::tahapan($paspor),
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

    /* ═══════════ dasbor ═══════════ */

    /**
     * Ringkasan seluruh modul dalam satu layar.
     *
     * Dua bagian, dan urutannya disengaja. Yang pertama adalah APA YANG
     * MENUNGGU SAYA — pertanyaan yang dibawa orang saat membuka sistem
     * pagi hari, dan satu-satunya bagian yang menuntut tindakan. Yang
     * kedua barulah jumlah keseluruhan.
     *
     * Angka besar tanpa tautan tidak dibuat. Sebuah kartu bertuliskan
     * "3.959 MCU" yang tidak dapat ditekan hanya memberi tahu bahwa
     * datanya banyak; yang dicari pembacanya selalu barisnya.
     */
    public function dasbor(Request $request)
    {
        $u = $request->user();

        $orang = Paspor::with(['sertifikat', 'mcu', 'kartu', 'induksi', 'fieldBreak', 'cuti'])->get();

        $takLayak = $orang->filter(fn (Paspor $p) => !$p->kelayakan()['layak']);

        /* Yang menunggu keputusan SAYA — bukan yang menunggu keputusan
           siapa pun. Bagi yang bukan OHSE angkanya nol, dan itu jawaban
           yang benar: pengajuan itu memang bukan urusannya. */
        $mcuMenunggu = McuPengajuan::with(['pengaju'])->menunggu()->get()
            ->filter(fn (McuPengajuan $m) => $m->dapatDitinjauOleh($u));

        $kartuMenunggu = PasporKartu::with('paspor')->menunggu()->get()
            ->filter(fn (PasporKartu $k) => $k->dapatDitinjauOleh($u));

        $fbMenunggu = MinersFieldBreak::with('paspor')->menunggu()->get()
            ->filter(fn (MinersFieldBreak $f) => $f->dapatDitinjauOleh($u));

        $cutiMenunggu = MinersCuti::with('paspor')->menunggu()->get()
            ->filter(fn (MinersCuti $c) => $c->dapatDitinjauOleh($u));

        $campaignMenunggu = MinersCampaign::menunggu()->get()
            ->filter(fn (MinersCampaign $c) => $c->dapatDitinjauOleh($u));

        /* Siapa yang tidak ada di lokasi hari ini. Terpisah dari
           kelayakan, dan disebut terpisah di layar: mereka bukan
           masalah, hanya sedang tidak di sini. */
        $pergi = $orang->map(fn (Paspor $p) => $p->kehadiran() + ['nama' => $p->nama, 'id' => $p->id])
            ->filter(fn ($k) => $k['pergi'])->values();

        /* Rujukan medis yang tanggal tindak lanjutnya lewat. Tidak
           menahan orang di gerbang, tetapi menahan orang dari sembuh —
           dan tanpa satu angka yang menyebutnya, tidak ada yang
           menagihnya. */
        $tertunggak = $orang->sum(
            fn (Paspor $p) => $p->mcu->filter(fn (PasporMcu $m) => $m->rujukanTertunggak())->count()
        );

        $kritis = fn (iterable $tgl) => collect($tgl)->filter()
            ->filter(fn ($t) => in_array(Authority::keadaan($t),
                [Authority::KRITIS, Authority::SEGERA], true))->count();

        return Inertia::render('Miners/Dasbor', [
            'judul'    => 'Miners — Ringkasan',
            'subjudul' => 'MCU, induksi, kartu masuk, dan kompetensi dalam satu layar',

            'menunggu' => [
                'mcu' => $mcuMenunggu->map(fn (McuPengajuan $m) => [
                    'id'     => $m->id,
                    'nomor'  => $m->nomor_register ?? '#'.$m->id,
                    'judul'  => $m->judul,
                    'jumlah' => $m->hasil()->count(),
                    'pengaju' => $m->pengaju?->name,
                    'tanggal' => $m->tanggal?->toDateString(),
                    'tertinggal' => $m->parafTertinggal(),
                ])->values(),
                'kartu' => $kartuMenunggu->map(fn (PasporKartu $k) => [
                    'id'       => $k->id,
                    'pasporId' => $k->paspor_id,
                    'nama'     => $k->paspor?->nama,
                    'jenis'    => $k->jenis,
                    'sebab'    => $k->sebab_terbit,
                    'tertinggal' => $k->parafTertinggal(),
                ])->values(),

                /* Ketiga jenis lain tanpa rantai paraf: field break,
                   cuti, dan campaign langsung ke OHSE. Rantai tiga meja
                   untuk permintaan pulang dua minggu adalah upacara,
                   bukan pengendalian. */
                'lain' => collect()
                    ->merge($fbMenunggu->map(fn ($f) => [
                        'jalur' => '/miners/field-break', 'apa' => 'Field break',
                        'sebutan' => $f->paspor?->nama,
                        'terang' => $f->mulai?->toDateString().' → '.$f->selesai?->toDateString(),
                    ]))
                    ->merge($cutiMenunggu->map(fn ($c) => [
                        'jalur' => '/miners/cuti', 'apa' => 'Cuti '.$c->jenis,
                        'sebutan' => $c->paspor?->nama,
                        'terang' => $c->jumlah_hari.' hari · '.$c->mulai?->toDateString(),
                    ]))
                    ->merge($campaignMenunggu->map(fn ($c) => [
                        'jalur' => '/miners/campaign', 'apa' => 'Campaign',
                        'sebutan' => $c->judul, 'terang' => $c->jenis,
                    ]))
                    ->values(),

                /* Disebut tegas bila memang nol dan orangnya bukan
                   penentu — supaya "kosong" tidak terbaca sebagai
                   "rusak". */
                'sayaPenentu' => Tahap::penentu($u),

                /* Tidak ada seorang pun bertanda OHSE adalah kebuntuan
                   yang TIDAK TERLIHAT dari mana pun: seluruh pengajuan
                   menumpuk pada status "menunggu tinjauan" dan tombol
                   setujuinya tidak pernah muncul bagi siapa pun.
                   Disebutkan di sini karena dasbor adalah satu-satunya
                   layar yang pasti dibuka orang tiap pagi. */
                'adaOhse' => \App\Models\User::query()
                    ->where('ohse_role', 'ohse')
                    ->when($u?->company_id && !$u->isAdmin(),
                        fn ($q) => $q->where('company_id', $u->company_id))
                    ->exists(),
            ],

            /* Kartu angka. Tiap satu punya tautan ke barisnya. */
            'kartu' => [
                [
                    'label' => 'Orang terdaftar', 'nilai' => $orang->count(),
                    'jalur' => route('miners.index'), 'nada' => 'netral',
                ],
                [
                    'label' => 'Tidak boleh bekerja', 'nilai' => $takLayak->count(),
                    'jalur' => route('miners.index', ['keadaan' => '']), 'nada' => 'gawat',
                ],
                [
                    'label' => 'Hasil MCU', 'nilai' => $orang->sum(fn ($p) => $p->mcu->count()),
                    'jalur' => route('miners.mcu.index'), 'nada' => 'netral',
                ],
                [
                    'label' => 'Induksi', 'nilai' => $orang->sum(fn ($p) => $p->induksi->count()),
                    'jalur' => route('miners.index'), 'nada' => 'netral',
                ],
                [
                    'label' => 'Kartu masuk', 'nilai' => $orang->sum(fn ($p) => $p->kartu->count()),
                    'jalur' => route('miners.index'), 'nada' => 'netral',
                ],
                [
                    'label' => 'Sertifikat kompetensi',
                    'nilai' => $orang->sum(fn ($p) => $p->sertifikat->count()),
                    'jalur' => route('miners.index'), 'nada' => 'netral',
                ],
                [
                    'label' => 'Rujukan tertunggak', 'nilai' => $tertunggak,
                    'jalur' => route('miners.mcu.index'), 'nada' => 'serius',
                ],
                [
                    'label' => 'Tidak di lokasi hari ini', 'nilai' => $pergi->count(),
                    'jalur' => route('miners.fieldBreak.index'), 'nada' => 'netral',
                ],
                [
                    'label' => 'Campaign tayang',
                    'nilai' => MinersCampaign::tayang()->count(),
                    'jalur' => route('miners.campaign.index'), 'nada' => 'netral',
                ],
                [
                    'label' => 'Berkas segera habis',
                    'nilai' => $kritis($orang->flatMap(fn ($p) => $p->sertifikat->pluck('tgl_expired')))
                             + $kritis($orang->map(fn ($p) => $p->mcuTerakhir()?->tgl_expired))
                             + $kritis($orang->map(fn ($p) => $p->kartuBerlaku()?->tgl_expired))
                             + $kritis($orang->map(fn ($p) => $p->induksiBerlaku()?->tgl_expired)),
                    'jalur' => route('miners.index', ['keadaan' => Authority::KRITIS]),
                    'nada'  => 'ingat',
                ],
            ],

            /* Yang paling mendesak, langsung dengan namanya — supaya
               dasbornya dapat ditindaklanjuti tanpa membuka halaman
               lain lebih dulu. */
            'mendesak' => $takLayak->take(8)->map(fn (Paspor $p) => [
                'id'    => $p->id,
                'nama'  => $p->nama,
                'jabatan' => $p->jabatan,
                'sebab' => $p->kelayakan()['sebab'],
            ])->values(),

            'pergi' => $pergi,
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
        $pengajuan = McuPengajuan::with(['hasil.paspor', 'pengaju', 'peninjau', 'paraf'])
            ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->get();

        return Inertia::render('Miners/Halaman', $this->bersama() + [
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
                'sebabTakTinjau' => Tahap::sebabTakDapatMemutuskan(
                    $request->user(), $m->status, $m->diajukan_oleh),
                'alasanTolak' => $m->alasan_tolak,
                'pengaju'     => $m->pengaju?->name,
                'peninjau'    => $m->peninjau?->name,

                'rantai'     => $m->rantaiTahap(),
                'tertinggal' => $m->parafTertinggal(),
                'dapatParaf' => $m->menungguTinjauan(),

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

            /* Daftar di halaman ini berisi PENGAJUAN MCU — surat yang
               dikirim ke klinik. Yang tidak dijawabnya: dari seluruh
               pekerja, berapa yang MCU-nya masih berlaku hari ini.
               Keduanya perlu, dan keduanya sering tertukar. */
            'pemantauan' => PemantauanBerkas::untukDaftar(
                $this->orangPemantauan(), PemantauanBerkas::MCU),
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

        Jejak::write('Buat pengajuan MCU', $m->nomor_register ?? '#'.$m->id, 'miners');

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

        Jejak::write('Ubah pengajuan MCU', $pengajuan->nomor_register ?? '#'.$pengajuan->id, 'miners');

        return back()->with('ok', 'Pengajuan diperbarui.');
    }

    public function mcuDestroy(McuPengajuan $pengajuan)
    {
        abort_unless($pengajuan->dapatDiubah(), 422,
            'Pengajuan yang sudah diajukan tidak dapat dihapus.');

        $nomor = $pengajuan->nomor_register ?? '#'.$pengajuan->id;
        $pengajuan->delete();

        Jejak::write('Hapus pengajuan MCU', $nomor, 'miners');

        return redirect()->route('miners.mcu.index')->with('ok', 'Pengajuan dihapus.');
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

        Jejak::write('Isi hasil MCU', $mcu->paspor?->nama.' — '.$mcu->hasil, 'miners');

        return back()->with('ok', 'Hasil MCU tersimpan.');
    }

    /* ═══════════ induksi ═══════════ */

    public function simpanInduksi(Request $request, Paspor $paspor)
    {
        /* Induksi baru masuk akal SESUDAH orangnya dinyatakan sehat.
           Menginduksi orang yang ternyata Unfit adalah setengah hari
           kelas yang terbuang — dan yang lebih buruk, induksinya
           tercatat sehingga di layar ia tampak lebih siap daripada
           sebenarnya. */
        $paspor->load(['mcu', 'induksi', 'kartu']);

        if ($sebab = AlurMiner::halanganInduksi($paspor)) {
            return back()->withErrors(['induksi' => $sebab]);
        }

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

        Jejak::write('Catat induksi', $paspor->nama.' — '.$data['jenis'], 'miners');

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

        Jejak::write('Tambah paspor kerja', $p->nama, 'miners');

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

        Jejak::write('Ubah paspor kerja', $paspor->nama, 'miners');

        return back()->with('ok', 'Paspor diperbarui.');
    }

    public function destroy(Paspor $paspor)
    {
        $nama = $paspor->nama;
        $paspor->delete();

        Jejak::write('Hapus paspor kerja', $nama, 'miners');

        return redirect()->route('miners.index')->with('ok', 'Paspor '.$nama.' dihapus.');
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

        Jejak::write('Tambah sertifikat', $paspor->nama.' — '.$data['nama'], 'miners');

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

        Jejak::write('Catat MCU', $paspor->nama.' — '.$data['hasil'], 'miners');

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

        Jejak::write('Ajukan kartu masuk', $paspor->nama, 'miners');

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
            'berkas_sim'         => ['nullable', 'string', 'max:255'],
            'sim_polisi_expired' => ['nullable', 'date'],
            'pengalaman_kerja'   => ['nullable', 'string', 'max:150'],
            'berkas_induksi'     => ['nullable', 'string', 'max:255'],
            'berkas_ddt'         => ['nullable', 'string', 'max:255'],
            'email_atasan'       => ['nullable', 'email', 'max:150'],

            /* Tercetak pada kartunya sendiri, jadi harus ada sebelum
               kartunya dapat dicetak. */
            'golongan_darah'  => ['nullable', 'string', 'max:5'],
            'telepon'         => ['nullable', 'string', 'max:30'],
            'kontak_darurat'  => ['nullable', 'string', 'max:120'],

            /* Lampiran syarat Mine Permit. Keempatnya diperiksa SEBELUM
               permit terbit — bukan berkas pelengkap. */
            'berkas_ktp'        => ['nullable', 'string', 'max:255'],
            'berkas_permohonan' => ['nullable', 'string', 'max:255'],
            'berkas_spdk'       => ['nullable', 'string', 'max:255'],
            'berkas_dept'       => ['nullable', 'string', 'max:255'],
            'berkas_lotto'      => ['nullable', 'string', 'max:255'],
            'berkas_blasting'   => ['nullable', 'string', 'max:255'],

            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /**
     * Unggah berkas SIM, lalu usulkan masa berlakunya.
     *
     * MENGUSULKAN, TIDAK MENGISI. Yang dikembalikan tanggalnya beserta
     * dari mana ia terbaca dan potongan teks yang membuatnya terbaca —
     * layar yang memakainya wajib memperlihatkan ketiganya. Tanggal
     * kedaluwarsa yang terisi diam-diam lebih buruk daripada kolom
     * kosong: kolom kosong terlihat belum diisi, sedangkan tanggal yang
     * salah terbaca sebagai sudah diperiksa, dan yang memakainya di
     * gerbang tidak punya cara mengetahui bedanya.
     *
     * Gagal membaca BUKAN gagal mengunggah. Hasil pindaian berupa
     * gambar tidak punya lapisan teks dan tidak akan pernah terbaca;
     * berkasnya tetap tersimpan dan tanggalnya diisi tangan.
     */
    public function unggahSim(Request $request)
    {
        $request->validate([
            'berkas' => ['required', 'file', 'max:8192', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $berkas = $request->file('berkas');
        $jalur  = Berkas::simpan($berkas, 'miners/sim');

        if (!$jalur) {
            return response()->json(['pesan' => 'Berkas tidak dapat disimpan.'], 422);
        }

        $terbaca = MasaBerlakuTerbaca::dariUnggahan($berkas);

        return response()->json([
            'jalur'   => $jalur,
            'nama'    => $berkas->getClientOriginalName(),
            'terbaca' => $terbaca,
        ]);
    }

    /* ═══════════ unit SIMPER ═══════════ */

    /**
     * SIMPER dinilai PER UNIT, bukan per orang.
     *
     * Seorang operator dapat lulus untuk Excavator PC 200 dan belum
     * lulus untuk PC 500. Menyimpannya sebagai satu baris per kartu
     * membuat kartu yang menyebut "Excavator" membolehkan unit yang
     * tidak pernah diujikan kepadanya.
     *
     * Hanya boleh ditambahkan selama kartunya masih dapat diubah:
     * menambah unit pada kartu yang sudah disetujui berarti memperluas
     * kewenangan tanpa melewati peninjauan yang menyetujuinya.
     */
    public function simpanUnitKartu(Request $request, Paspor $paspor, PasporKartu $kartu)
    {
        abort_unless($kartu->paspor_id === $paspor->id, 404);
        abort_unless($kartu->dapatDiubah(), 422,
            'Kartu yang sudah diajukan tidak dapat ditambah unitnya.');

        $kartu->unit()->create($this->aturanUnitKartu($request));

        Jejak::write('Tambah unit SIMPER', $paspor->nama, 'miners');

        return back()->with('ok', 'Unit ditambahkan.');
    }

    public function ubahUnitKartu(Request $request, Paspor $paspor, PasporKartu $kartu, PasporKartuUnit $unit)
    {
        abort_unless($kartu->paspor_id === $paspor->id, 404);
        abort_unless($unit->paspor_kartu_id === $kartu->id, 404);
        abort_unless($kartu->dapatDiubah(), 422,
            'Kartu yang sudah diajukan tidak dapat diubah unitnya.');

        $unit->update($this->aturanUnitKartu($request));

        return back()->with('ok', 'Unit diperbarui.');
    }

    public function hapusUnitKartu(Paspor $paspor, PasporKartu $kartu, PasporKartuUnit $unit)
    {
        abort_unless($kartu->paspor_id === $paspor->id, 404);
        abort_unless($unit->paspor_kartu_id === $kartu->id, 404);
        abort_unless($kartu->dapatDiubah(), 422,
            'Kartu yang sudah diajukan tidak dapat diubah unitnya.');

        $unit->delete();

        return back()->with('ok', 'Unit dihapus.');
    }

    /** @return array<string,mixed> */
    private function aturanUnitKartu(Request $request): array
    {
        return $request->validate([
            /* Master unit dipakai bila ada padanannya, tetapi tidak
               diwajibkan: unit sewa dan unit subkontraktor kerap belum
               terdaftar. */
            'ko_unit_master_id' => ['nullable', new DalamPerusahaan('ko_unit_master')],

            'authority'  => ['nullable', 'string', 'max:10'],
            'jenis_unit' => ['nullable', 'string', 'max:120'],
            'type_merk'  => ['nullable', 'string', 'max:200'],

            /* Nilai 0..100. Dibiarkan kosong berarti BELUM DIUJI, dan itu
               berbeda dari nilai nol — yang pertama menunggu, yang kedua
               gagal. */
            'nilai_p2h'     => ['nullable', 'integer', 'min:0', 'max:100'],
            'nilai_praktek' => ['nullable', 'integer', 'min:0', 'max:100'],

            'berkas_rambu'  => ['nullable', 'string', 'max:255'],
            'berkas_teori'  => ['nullable', 'string', 'max:255'],
            'hasil_praktek' => ['nullable', 'string', 'max:255'],
            'evaluasi'      => ['nullable', 'string', 'max:255'],

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

        /* Relasi dimuat lebih dulu: penjaganya membaca MCU, induksi, dan
           kartu lain milik orang yang sama. Tanpa ini tiap pemeriksaan
           menembak kueri sendiri-sendiri — dan yang lebih penting,
           kartu() yang belum dimuat memulangkan koleksi kosong pada
           beberapa jalur, sehingga Mine License lolos karena Mine
           Permit-nya "tidak ada". */
        $kartu->setRelation('paspor', $paspor->load(['mcu', 'induksi', 'kartu']));

        if ($kurang = $kartu->syaratKurang()) {
            return back()->withErrors([
                'kartu' => 'Belum dapat diajukan. '.implode(' ', $kurang),
            ]);
        }

        $kartu->ajukan();

        Jejak::write('Ajukan kartu masuk', $paspor->nama.' — '.$kartu->jenis, 'miners');

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

        Jejak::write('Ajukan MCU', $pengajuan->nomor_register ?? '#'.$pengajuan->id, 'miners');

        return back()->with('ok', 'Pengajuan MCU dikirim untuk ditinjau.');
    }

    public function tinjauMcu(Request $request, McuPengajuan $pengajuan)
    {
        return $this->tinjau($request, $pengajuan, 'pengajuan MCU',
            $pengajuan->nomor_register ?? '#'.$pengajuan->id);
    }

    /* ═══════════ field break ═══════════ */

    /**
     * Siapa yang sedang pergi, dan siapa yang akan pergi.
     *
     * Pertanyaan yang dijawab halaman ini bukan "berapa banyak field
     * break tahun ini" melainkan "berapa orang yang HARI INI tidak ada
     * di lokasi" — angka yang dipakai membagi pekerjaan besok pagi.
     */
    public function fieldBreak(Request $request)
    {
        $baris = MinersFieldBreak::with(['paspor', 'pengganti', 'pengaju'])
            ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('mulai')->get();

        return Inertia::render('Miners/FieldBreak', [
            'judul'    => 'Miners — Field Break',
            'subjudul' => 'Giliran pulang pada pola kerja rotasi — bukan cuti, tidak memotong jatah',
        ] + $this->bersama() + [
            'saring'   => ['status' => $request->get('status')],

            'baris' => $baris->map(fn (MinersFieldBreak $f) => [
                'id'        => $f->id,
                'pasporId'  => $f->paspor_id,
                'nama'      => $f->paspor?->nama,
                'jabatan'   => $f->paspor?->jabatan,
                'pola'      => $f->pola,
                'jenis'     => $f->jenis,
                'mulai'     => $f->mulai?->toDateString(),
                'selesai'   => $f->selesai?->toDateString(),
                'kembali'   => $f->kembali_aktual?->toDateString(),
                'hari'      => $f->jumlahHari(),
                'telat'     => $f->telat(),
                'lokasi'    => $f->lokasi_tujuan,
                'pengganti' => $f->pengganti?->nama,
                'catatan'   => $f->catatan,

                'sedangPergi'   => $f->sedangPergi(),
                'status'        => $f->status,
                'statusLabel'   => Alur::LABEL[$f->status] ?? $f->status,
                'dapatDiubah'   => $f->dapatDiubah(),
                'dapatDitinjau' => $f->dapatDitinjauOleh($request->user()),
                'alasanTolak'   => $f->alasan_tolak,
            ])->values(),

            'ringkas' => [
                'sedangPergi' => $baris->filter(fn ($f) => $f->sedangPergi())->count(),
                'menunggu'    => $baris->where('status', Alur::DIAJUKAN)->count(),
                'telat'       => $baris->filter(fn ($f) => $f->telat() !== null)->count(),
            ],
        ]);
    }

    public function fieldBreakStore(Request $request)
    {
        $data = $this->aturanFieldBreak($request);

        MinersFieldBreak::create($data);

        Jejak::write('Buat field break', Paspor::find($data['paspor_id'])?->nama ?? '', 'miners');

        return back()->with('ok', 'Field break dibuat sebagai draf.');
    }

    public function fieldBreakUpdate(Request $request, MinersFieldBreak $fieldBreak)
    {
        abort_unless($fieldBreak->dapatDiubah(), 422,
            'Field break yang sudah diajukan tidak dapat diubah.');

        $fieldBreak->update($this->aturanFieldBreak($request));

        return back()->with('ok', 'Field break diperbarui.');
    }

    /**
     * Mencatat kepulangan yang sebenarnya.
     *
     * Terpisah dari ubah biasa, dan boleh dilakukan SETELAH disetujui:
     * ini kejadian yang terjadi sesudah persetujuan, bukan penyuntingan
     * isi pengajuannya. Tanpa jalur tersendiri, kepulangan yang meleset
     * dari rencana tidak dapat dicatat sama sekali — padahal selisih
     * itulah yang ingin diketahui.
     */
    public function fieldBreakKembali(Request $request, MinersFieldBreak $fieldBreak)
    {
        $data = $request->validate([
            'kembali_aktual' => ['required', 'date', 'after_or_equal:'.$fieldBreak->mulai->toDateString()],
        ]);

        $fieldBreak->update($data);

        Jejak::write('Catat kembali dari field break', $fieldBreak->paspor?->nama ?? '', 'miners');

        return back()->with('ok', 'Tanggal kembali tercatat.');
    }

    public function fieldBreakDestroy(MinersFieldBreak $fieldBreak)
    {
        abort_unless($fieldBreak->dapatDiubah(), 422,
            'Field break yang sudah diajukan tidak dapat dihapus.');

        $fieldBreak->delete();

        return back()->with('ok', 'Field break dihapus.');
    }

    public function fieldBreakAjukan(MinersFieldBreak $fieldBreak)
    {
        $fieldBreak->ajukan();

        return back()->with('ok', 'Field break dikirim untuk ditinjau.');
    }

    public function fieldBreakTinjau(Request $request, MinersFieldBreak $fieldBreak)
    {
        return $this->tinjau($request, $fieldBreak, 'field break',
            $fieldBreak->paspor?->nama ?? '#'.$fieldBreak->id);
    }

    /** @return array<string,mixed> */
    private function aturanFieldBreak(Request $request): array
    {
        return $request->validate([
            'paspor_id'     => ['required', new DalamPerusahaan('paspor')],
            'pola'          => ['nullable', 'string', 'max:20'],
            'jenis'         => ['required', Rule::in(MinersFieldBreak::JENIS)],
            'mulai'         => ['required', 'date'],
            'selesai'       => ['required', 'date', 'after_or_equal:mulai'],
            'lokasi_tujuan' => ['nullable', 'string', 'max:150'],
            'pengganti_id'  => ['nullable', new DalamPerusahaan('paspor'), 'different:paspor_id'],
            'catatan'       => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /* ═══════════ cuti tahunan ═══════════ */

    public function cuti(Request $request)
    {
        $tahun = (int) ($request->get('tahun') ?: now()->year);

        $baris = MinersCuti::with(['paspor', 'pengganti'])
            ->where('tahun', $tahun)
            ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('mulai')->get();

        /* Saldo dihitung untuk SETIAP orang, bukan hanya yang sudah
           pernah mengambil cuti. Orang yang jatahnya utuh justru yang
           paling sering ditanyakan menjelang akhir tahun — dialah yang
           jatahnya akan hangus. */
        $orang = Paspor::orderBy('nama')->get();

        return Inertia::render('Miners/Cuti', [
            'judul'    => 'Miners — Cuti Tahunan',
            'subjudul' => 'Jatah, pengajuan, dan sisa cuti per orang',
        ] + $this->bersama() + [
            'tahun'    => $tahun,
            'saring'   => ['status' => $request->get('status')],

            'baris' => $baris->map(fn (MinersCuti $c) => [
                'id'       => $c->id,
                'pasporId' => $c->paspor_id,
                'nama'     => $c->paspor?->nama,
                'jenis'    => $c->jenis,
                'memotong' => $c->memotongJatah(),
                'mulai'    => $c->mulai?->toDateString(),
                'selesai'  => $c->selesai?->toDateString(),
                'hari'     => $c->jumlah_hari,
                'alamat'   => $c->alamat_cuti,
                'kontak'   => $c->kontak,
                'pengganti' => $c->pengganti?->nama,
                'alasan'   => $c->alasan,

                'sedangCuti'    => $c->sedangCuti(),
                'status'        => $c->status,
                'statusLabel'   => Alur::LABEL[$c->status] ?? $c->status,
                'dapatDiubah'   => $c->dapatDiubah(),
                'dapatDitinjau' => $c->dapatDitinjauOleh($request->user()),
                'alasanTolak'   => $c->alasan_tolak,
            ])->values(),

            /* Saldo seluruh orang diambil sekaligus. Memanggil
               JatahCuti::hitung() per orang berarti dua kueri per orang,
               dan halaman ini memang menghitung untuk SETIAP orang. */
            'saldo' => (function () use ($orang, $tahun) {
                $saldo = JatahCuti::hitungBanyak($orang, $tahun);

                return $orang->map(fn (Paspor $p) => [
                    'id' => $p->id, 'nama' => $p->nama, 'jabatan' => $p->jabatan,
                ] + $saldo[$p->getKey()])->values();
            })(),

            'ringkas' => [
                'sedangCuti' => $baris->filter(fn ($c) => $c->sedangCuti())->count(),
                'menunggu'   => $baris->where('status', Alur::DIAJUKAN)->count(),
                'hariTerpakai' => (int) $baris->where('status', Alur::DISETUJUI)
                    ->filter(fn ($c) => $c->memotongJatah())->sum('jumlah_hari'),
            ],
        ]);
    }

    public function cutiStore(Request $request)
    {
        $data = $request->validate([
            'paspor_id'    => ['required', new DalamPerusahaan('paspor')],
            'jenis'        => ['required', Rule::in(MinersCuti::JENIS)],
            'mulai'        => ['required', 'date'],
            'selesai'      => ['required', 'date', 'after_or_equal:mulai'],
            'jumlah_hari'  => ['nullable', 'integer', 'min:1', 'max:365'],
            'alamat_cuti'  => ['nullable', 'string', 'max:255'],
            'kontak'       => ['nullable', 'string', 'max:60'],
            'pengganti_id' => ['nullable', new DalamPerusahaan('paspor'), 'different:paspor_id'],
            'alasan'       => ['nullable', 'string', 'max:1000'],
        ]);

        $p = Paspor::findOrFail($data['paspor_id']);

        $data['tahun'] = (int) \Illuminate\Support\Carbon::parse($data['mulai'])->year;

        /* Jumlah hari yang tidak diisi diusulkan dari selisih tanggal.
           Diusulkan, bukan dipaksakan: pengaju yang tahu ada hari libur
           di tengahnya tetap dapat menurunkannya. */
        $data['jumlah_hari'] ??= MinersCuti::hariKalender($data['mulai'], $data['selesai']);

        /* Jatah diperiksa DI SINI, saat draf dibuat — bukan saat
           disetujui. Menolak di akhir berarti pengaju sudah menyusun
           rencana, memberi tahu keluarganya, dan menunggu berhari-hari
           sebelum diberi tahu bahwa jatahnya memang tidak pernah
           cukup. */
        if (in_array($data['jenis'], MinersCuti::MEMOTONG_JATAH, true)) {
            $saldo = JatahCuti::hitung($p, $data['tahun']);

            if ($data['jumlah_hari'] > $saldo['sisa']) {
                return back()->withErrors(['jumlah_hari' =>
                    'Sisa jatah '.$saldo['tahun'].' tinggal '.$saldo['sisa'].' hari'
                    .($saldo['tertahan'] ? ' (termasuk '.$saldo['tertahan'].' hari yang masih menunggu tinjauan)' : '')
                    .', tidak cukup untuk '.$data['jumlah_hari'].' hari.']);
            }
        }

        MinersCuti::create($data);

        Jejak::write('Ajukan cuti', $p->nama.' — '.$data['jumlah_hari'].' hari', 'miners');

        return back()->with('ok', 'Pengajuan cuti dibuat sebagai draf.');
    }

    public function cutiDestroy(MinersCuti $cuti)
    {
        abort_unless($cuti->dapatDiubah(), 422,
            'Cuti yang sudah diajukan tidak dapat dihapus.');

        $cuti->delete();

        return back()->with('ok', 'Pengajuan cuti dihapus.');
    }

    public function cutiAjukan(MinersCuti $cuti)
    {
        $cuti->ajukan();

        return back()->with('ok', 'Pengajuan cuti dikirim untuk ditinjau.');
    }

    public function cutiTinjau(Request $request, MinersCuti $cuti)
    {
        return $this->tinjau($request, $cuti, 'cuti', $cuti->paspor?->nama ?? '#'.$cuti->id);
    }

    /** Menetapkan jatah cuti seseorang untuk satu tahun. */
    public function cutiJatah(Request $request)
    {
        $data = $request->validate([
            'paspor_id' => ['required', new DalamPerusahaan('paspor')],
            'tahun'     => ['required', 'integer', 'min:2000', 'max:2100'],
            'jatah'     => ['required', 'integer', 'min:0', 'max:365'],
            'bawaan'    => ['nullable', 'integer', 'min:0', 'max:365'],
            'catatan'   => ['nullable', 'string', 'max:500'],
        ]);

        MinersCutiJatah::updateOrCreate(
            ['paspor_id' => $data['paspor_id'], 'tahun' => $data['tahun']],
            $data,
        );

        Jejak::write('Atur jatah cuti',
            Paspor::find($data['paspor_id'])?->nama.' — '.$data['tahun'], 'miners');

        return back()->with('ok', 'Jatah cuti ditetapkan.');
    }

    /* ═══════════ campaign ═══════════ */

    public function campaign(Request $request)
    {
        $baris = MinersCampaign::with(['pengaju', 'peninjau'])
            ->when($request->get('jenis'), fn ($q, $j) => $q->where('jenis', $j))
            ->orderByDesc('mulai')->get();

        return Inertia::render('Miners/Campaign', [
            'judul'    => 'Miners — Campaign Keselamatan',
            'subjudul' => 'Poster, artikel, video, dan toolbox — beserta masa tayangnya',
        ] + $this->bersama() + [
            'saring'   => ['jenis' => $request->get('jenis')],

            'baris' => $baris->map(fn (MinersCampaign $c) => [
                'id'        => $c->id,
                'judul'     => $c->judul,
                'jenis'     => $c->jenis,
                'tema'      => $c->tema,
                'mulai'     => $c->mulai?->toDateString(),
                'selesai'   => $c->selesai?->toDateString(),
                'sasaran'   => $c->sasaran,
                'ringkasan' => $c->ringkasan,
                'jangkauan' => $c->jangkauan,

                'tayang'        => $c->sedangTayang(),
                'status'        => $c->status,
                'statusLabel'   => Alur::LABEL[$c->status] ?? $c->status,
                'dapatDiubah'   => $c->dapatDiubah(),
                'dapatDitinjau' => $c->dapatDitinjauOleh($request->user()),
                'alasanTolak'   => $c->alasan_tolak,
            ])->values(),

            'ringkas' => [
                'tayang'   => $baris->filter(fn ($c) => $c->sedangTayang())->count(),
                'menunggu' => $baris->where('status', Alur::DIAJUKAN)->count(),

                /* Jangkauan hanya dijumlah dari yang MENGISINYA. Kosong
                   diperlakukan sebagai "belum diketahui", bukan nol —
                   menjumlahkannya sebagai nol membuat total jangkauan
                   terlihat rendah dan tak seorang pun tahu apakah
                   sebabnya campaign yang lemah atau pencatatan yang
                   belum lengkap. */
                'jangkauan' => (int) $baris->whereNotNull('jangkauan')->sum('jangkauan'),
                'tanpaJangkauan' => $baris->whereNull('jangkauan')
                    ->filter(fn ($c) => $c->sudahDisetujui())->count(),
            ],
        ]);
    }

    public function campaignStore(Request $request)
    {
        $c = MinersCampaign::create($this->pemilik($this->aturanCampaign($request)));

        Jejak::write('Buat campaign', $c->judul, 'miners');

        return back()->with('ok', 'Campaign dibuat sebagai draf.');
    }

    public function campaignUpdate(Request $request, MinersCampaign $campaign)
    {
        abort_unless($campaign->dapatDiubah(), 422,
            'Campaign yang sudah diajukan tidak dapat diubah.');

        $campaign->update($this->aturanCampaign($request));

        return back()->with('ok', 'Campaign diperbarui.');
    }

    /** Mencatat jangkauan — boleh setelah disetujui, sebab baru diketahui sesudah tayang. */
    public function campaignJangkauan(Request $request, MinersCampaign $campaign)
    {
        $campaign->update($request->validate([
            'jangkauan' => ['required', 'integer', 'min:0', 'max:1000000'],
        ]));

        return back()->with('ok', 'Jangkauan tercatat.');
    }

    public function campaignDestroy(MinersCampaign $campaign)
    {
        abort_unless($campaign->dapatDiubah(), 422,
            'Campaign yang sudah diajukan tidak dapat dihapus.');

        $campaign->delete();

        return back()->with('ok', 'Campaign dihapus.');
    }

    public function campaignAjukan(MinersCampaign $campaign)
    {
        $campaign->ajukan();

        return back()->with('ok', 'Campaign dikirim untuk ditinjau.');
    }

    public function campaignTinjau(Request $request, MinersCampaign $campaign)
    {
        return $this->tinjau($request, $campaign, 'campaign', $campaign->judul);
    }

    /** @return array<string,mixed> */
    private function aturanCampaign(Request $request): array
    {
        return $request->validate([
            'judul'     => ['required', 'string', 'max:200'],
            'jenis'     => ['required', Rule::in(MinersCampaign::JENIS)],
            'tema'      => ['nullable', 'string', 'max:120'],
            'mulai'     => ['required', 'date'],
            'selesai'   => ['nullable', 'date', 'after_or_equal:mulai'],
            'sasaran'   => ['nullable', 'string', 'max:150'],
            'ringkasan' => ['nullable', 'string', 'max:1000'],
            'isi'       => ['nullable', 'string', 'max:20000'],
        ]);
    }

    /* ═══════════ riwayat per tahap ═══════════ */

    /**
     * Satu halaman per tahap, menyilang seluruh pekerja.
     *
     * Sebelumnya induksi, Mine Permit, Mine License, dan kompetensi
     * hanya dapat dilihat dengan membuka orangnya satu per satu. Itu
     * membuat pertanyaan yang paling sering diajukan tidak terjawab
     * sama sekali: "mana saja Mine Permit yang menunggu keputusan saya",
     * "berapa induksi yang jatuh tempo bulan ini". Pertanyaan semacam
     * itu menyilang orang, bukan menyusuri satu orang.
     *
     * Urutan menunya mengikuti urutan alurnya — MCU, induksi, Mine
     * Permit, Mine License, Authority — supaya bilah samping itu
     * sendiri yang mengajarkan urutannya, tanpa seorang pun perlu
     * membaca petunjuk.
     */
    public function riwayat(Request $request)
    {
        /* Diambil dari defaults() rutenya, bukan dari segmen jalur:
           keempatnya rute tersendiri supaya dapat dipanggil tanpa
           parameter — lihat catatan di routes/web.php. */
        $tahap = (string) $request->route()->defaults['tahap'];

        $daftar = match ($tahap) {
            'induksi'      => $this->riwayatInduksi(),
            'mine-permit'  => $this->riwayatKartu(AlurMiner::KARTU_PERMIT, $request),
            'mine-license' => $this->riwayatKartu(AlurMiner::KARTU_LICENSE, $request),
            'authority'    => $this->riwayatKompetensi(),
            default        => abort(404),
        };

        return Inertia::render('Miners/Riwayat', [
            'judul'    => 'Miners — '.$daftar['judul'],
            'subjudul' => $daftar['subjudul'],
        ] + $this->bersama() + [
            'tahap'    => $tahap,
            'kolom'    => $daftar['kolom'],
            'baris'    => $daftar['baris'],
            'ringkas'  => $daftar['ringkas'],
            'pemantauan' => $daftar['pemantauan'] ?? null,
        ]);
    }

    /** @return array<string,mixed> */
    private function riwayatInduksi(): array
    {
        $baris = PasporInduksi::with('paspor')
            ->orderByDesc('tanggal')->get();

        return [
            'judul'    => 'Riwayat Induksi',
            'subjudul' => 'Induksi keselamatan seluruh pekerja — dicatat setelah hasil MCU menyatakan layak',
            'kolom'    => ['Nama', 'Jenis', 'Tanggal', 'Berlaku sampai', 'Nilai', 'Hasil'],
            'baris'    => $baris->map(fn (PasporInduksi $i) => [
                'id'       => $i->id,
                'pasporId' => $i->paspor_id,
                'nama'     => $i->paspor?->nama,
                'jabatan'  => $i->paspor?->jabatan,
                'nomor'    => $i->nomor_registrasi,
                'sel'      => [
                    $i->paspor?->nama, $i->jenis,
                    $i->tanggal?->toDateString(),
                    $i->tgl_expired?->toDateString(),
                    $i->nilai === null ? '—' : (string) $i->nilai,
                    $i->hasil,
                ],
                'keadaan'    => $i->keadaan(),
                'keterangan' => $i->keterangan(),

                /* Induksi tidak berjalur persetujuan sendiri: ia
                   diselenggarakan OHSE dan dicatat sesudah selesai.
                   Yang menentukan sah atau tidak adalah hasilnya. */
                'baik' => $i->lulus(),
            ])->values(),
            'ringkas' => [
                ['Total induksi', $baris->count(), 'netral'],
                ['Lulus', $baris->filter(fn ($i) => $i->lulus())->count(), 'baik'],
                ['Belum lulus', $baris->reject(fn ($i) => $i->lulus())->count(), 'gawat'],
                ['Segera habis', $baris->filter(fn ($i) => in_array($i->keadaan(),
                    [Authority::KRITIS, Authority::SEGERA], true))->count(), 'ingat'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    private function riwayatKartu(string $jenis, Request $request): array
    {
        /* `paspor.mcu` ikut dimuat: keadaan kartu dihitung atas tanggal
           EFEKTIF, dan bagi Mine Permit tanggal itu dibatasi MCU
           terakhir orangnya. Tanpa dimuat di sini, satu kueri tambahan
           per baris — dan daftar ini memang berisi seluruh kartu. */
        $baris = PasporKartu::with(['paspor.mcu', 'paraf', 'peninjau'])
            ->where('jenis', $jenis)
            ->orderByDesc('tgl_terbit')->orderByDesc('id')->get();

        $permit = $jenis === AlurMiner::KARTU_PERMIT;

        return [
            'judul'    => $permit ? 'Riwayat Mine Permit' : 'Riwayat Mine License',
            'subjudul' => $permit
                ? 'Izin masuk area tambang — terbit sesudah MCU dan induksi, diverifikasi OHSE'
                : 'Izin mengemudi di area tambang (A2B) — tambahan di atas Mine Permit',
            'kolom' => ['Nama', 'Nomor', 'Sebab', $permit ? 'Area' : 'Golongan', 'Terbit', 'Berlaku sampai'],
            'baris' => $baris->map(fn (PasporKartu $k) => [
                'id'       => $k->id,
                'pasporId' => $k->paspor_id,
                'nama'     => $k->paspor?->nama,
                'jabatan'  => $k->paspor?->jabatan,
                'nomor'    => $k->nomor,
                'sel'      => [
                    $k->paspor?->nama, $k->nomor ?: '—', $k->sebab_terbit,
                    ($permit ? $k->area : $k->golongan) ?: '—',
                    $k->tgl_terbit?->toDateString(),

                    /* Tanggal EFEKTIF, bukan yang tercetak. Kolomnya
                       bertanya "berlaku sampai kapan", dan jawabannya
                       bagi kartu yang dibatasi MCU atau SIM bukan
                       tanggal yang tertulis padanya. Sebabnya disebut
                       di kolom keadaan. */
                    $k->expiredEfektif()?->toDateString(),
                ],
                'keadaan'    => $k->keadaan(),
                'keterangan' => $k->keterangan(),
                'baik'       => $k->sudahDisetujui(),

                'status'        => $k->status,
                'statusLabel'   => Alur::LABEL[$k->status] ?? $k->status,
                'dapatDitinjau' => $k->dapatDitinjauOleh($request->user()),
                'tertinggal'    => $k->parafTertinggal(),

                /* Hanya yang sudah terbit yang dapat dicetak. */
                'cetak' => $permit && $k->sudahDisetujui()
                    ? route('miners.permit.cetak', [$k->paspor_id, $k]) : null,
            ])->values(),
            'ringkas' => [
                ['Total', $baris->count(), 'netral'],
                ['Terbit', $baris->filter(fn ($k) => $k->sudahDisetujui())->count(), 'baik'],
                ['Menunggu OHSE', $baris->where('status', Alur::DIAJUKAN)->count(), 'ingat'],
                ['Segera habis', $baris->filter(fn ($k) => $k->sudahDisetujui()
                    && in_array($k->keadaan(), [Authority::KRITIS, Authority::SEGERA], true))->count(), 'serius'],
            ],

            /* Berapa yang bermasalah dan milik siapa — pertanyaan yang
               selalu menyusul daftar ini, dan yang selama ini menuntut
               pindah halaman untuk menjawabnya. Dihitung atas tanggal
               EFEKTIF, sehingga permit yang MCU-nya sudah habis terhitung
               habis di sini meski tanggal cetaknya masih panjang. */
            'pemantauan' => PemantauanBerkas::untukDaftar($this->orangPemantauan(), $jenis),
        ];
    }

    /**
     * Orang beserta berkasnya, untuk ringkasan pemantauan.
     *
     * Dimuat sekaligus dengan relasinya. Tanpa `with()` ringkasan yang
     * hanya menghitung jumlah akan menembak satu kueri per orang untuk
     * kartunya dan satu lagi untuk MCU-nya — pada seratus pekerja itu
     * dua ratus kueri untuk sebuah angka.
     *
     * @return \Illuminate\Support\Collection<int,Paspor>
     */
    private function orangPemantauan(): \Illuminate\Support\Collection
    {
        return Paspor::with(['kartu', 'mcu', 'company'])->orderBy('nama')->get();
    }

    /** @return array<string,mixed> */
    private function riwayatKompetensi(): array
    {
        $baris = PasporSertifikat::with('paspor')
            ->orderByDesc('tgl_terbit')->orderByDesc('id')->get();

        return [
            'judul'    => 'Riwayat Authority',
            'subjudul' => 'Sertifikat kompetensi dan kewenangan — POP, POM, POU, dan kewenangan teknis lainnya',
            'kolom'    => ['Nama', 'Kompetensi', 'Lembaga', 'Nomor', 'Terbit', 'Berlaku sampai'],
            'baris'    => $baris->map(fn (PasporSertifikat $s) => [
                'id'       => $s->id,
                'pasporId' => $s->paspor_id,
                'nama'     => $s->paspor?->nama,
                'jabatan'  => $s->paspor?->jabatan,
                'nomor'    => $s->nomor,
                'sel'      => [
                    $s->paspor?->nama, $s->nama, $s->lembaga ?: '—', $s->nomor ?: '—',
                    $s->tgl_terbit?->toDateString(),
                    $s->tgl_expired?->toDateString() ?: 'tanpa tanggal',
                ],
                'keadaan'    => $s->keadaan(),
                'keterangan' => $s->keterangan(),
                'baik'       => Authority::sisaHari($s->tgl_expired) >= 0 || $s->tgl_expired === null,
            ])->values(),
            'ringkas' => [
                ['Total sertifikat', $baris->count(), 'netral'],
                ['Kadaluarsa', $baris->filter(
                    fn ($s) => $s->tgl_expired && Authority::sisaHari($s->tgl_expired) < 0)->count(), 'gawat'],
                ['Segera habis', $baris->filter(fn ($s) => in_array($s->keadaan(),
                    [Authority::KRITIS, Authority::SEGERA], true))->count(), 'ingat'],
                ['Tanpa tanggal', $baris->whereNull('tgl_expired')->count(), 'netral'],
            ],
        ];
    }

    /* ═══════════ cetak Mine Permit ═══════════ */

    /**
     * Lembar Mine Permit yang dibawa orangnya ke gerbang.
     *
     * HANYA YANG SUDAH TERBIT yang dapat dicetak. Kartu yang masih draf
     * atau menunggu keputusan tidak boleh keluar sebagai lembar
     * bercetak: begitu tercetak ia tidak dapat dibedakan dari yang sah
     * oleh petugas gerbang, dan seluruh alur persetujuan yang
     * mendahuluinya menjadi tidak ada gunanya.
     *
     * Masa berlaku MCU dan induksi ikut tercetak, bukan hanya masa
     * berlaku kartunya. Ketiganya harus berlaku bersamaan, dan lembar
     * yang hanya menyebut satu di antaranya menyembunyikan dua sebab
     * lain seseorang dapat ditahan.
     */
    public function cetakPermit(Paspor $paspor, PasporKartu $kartu)
    {
        abort_unless($kartu->paspor_id === $paspor->id, 404);

        abort_unless($kartu->sudahDisetujui(), 403,
            'Mine Permit yang belum disetujui tidak dapat dicetak.');

        $paspor->load(['mcu', 'induksi', 'kartu', 'sertifikat', 'company']);

        $m = $paspor->mcuTerakhir();
        $i = $paspor->induksiBerlaku();

        return Inertia::render('Print/MinePermit', [
            'dok' => KopDokumen::untuk('mine-permit', $this->perusahaanKop()),

            'orang' => [
                'nama'       => $paspor->nama,
                'nik'        => $paspor->nik,
                'jabatan'    => $paspor->jabatan,
                'departemen' => $paspor->departemen,
                'register'   => $paspor->nomor_register,
                'klasifikasi' => $paspor->labelKlasifikasi(),
                'perusahaan' => $paspor->company?->name,
            ],

            'kartu' => [
                'jenis'      => $kartu->jenis,
                'nomor'      => $kartu->nomor,
                'sebab'      => $kartu->sebab_terbit,
                'golongan'   => $kartu->golongan,
                'area'       => $kartu->area,
                'tglTerbit'  => $kartu->tgl_terbit?->toDateString(),
                'tglExpired' => $kartu->tgl_expired?->toDateString(),
                'keterangan' => $kartu->keterangan(),
                'peninjau'   => $kartu->peninjau?->name,
                'ditinjauPada' => $kartu->ditinjau_pada?->toDateString(),
            ],

            /* Dasar penerbitannya ikut tercetak. Lembar izin yang tidak
               menyebut dasarnya tidak dapat diperiksa ulang siapa pun
               tanpa membuka sistem. */
            'dasar' => [
                'mcu' => $m ? [
                    'tanggal'    => $m->tgl_periksa?->toDateString(),
                    'hasil'      => $m->hasil,
                    'tglExpired' => $m->tgl_expired?->toDateString(),
                    'keterangan' => $m->keterangan(),
                    'penyelenggara' => $m->penyelenggara,
                ] : null,
                'induksi' => $i ? [
                    'jenis'      => $i->jenis,
                    'tanggal'    => $i->tanggal?->toDateString(),
                    'tglExpired' => $i->tgl_expired?->toDateString(),
                    'keterangan' => $i->keterangan(),
                    'pemberi'    => $i->pemberi,
                ] : null,
            ],

            'kembali' => route('miners.show', $paspor),
        ]);
    }

    /* ═══════════ paraf bertahap ═══════════ */

    /**
     * Membubuhkan paraf pada satu tahap.
     *
     * Paraf TIDAK mengubah status apa pun. Ia hanya mencatat siapa sudah
     * melihat, dan tidak menahan maupun mempercepat keputusan OHSE.
     */
    public function parafKartu(Request $request, Paspor $paspor, PasporKartu $kartu)
    {
        abort_unless($kartu->paspor_id === $paspor->id, 404);

        return $this->bubuhkan($request, $kartu, $paspor->nama);
    }

    public function parafMcu(Request $request, McuPengajuan $pengajuan)
    {
        return $this->bubuhkan($request, $pengajuan,
            $pengajuan->nomor_register ?? '#'.$pengajuan->id);
    }

    private function bubuhkan(Request $request, $model, string $sebutan)
    {
        $data = $request->validate([
            'tahap'   => ['required', Rule::in(Tahap::kode())],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $model->bubuhkanParaf($data['tahap'], $request->user(), $data['catatan'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['paraf' => $e->getMessage()]);
        }

        Jejak::write('Paraf '.Tahap::label($data['tahap']), $sebutan, 'miners');

        return back()->with('ok', 'Paraf '.Tahap::label($data['tahap']).' dibubuhkan.');
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

        Jejak::write(ucfirst($data['aksi']).' '.$apa, $sebutan, 'miners');

        return back()->with('ok', ucfirst($apa).' '.$data['aksi'].'.');
    }

    /* ═══════════ pendukung ═══════════ */

    /** @return array<string,mixed> */
    /**
     * Prop yang dipakai seluruh halaman modul ini.
     *
     * PERHATIKAN URUTANNYA saat dipanggil. `bersama() + [...]` memakai
     * nilai dari operan KIRI bila kuncinya bertabrakan, sehingga judul
     * di sebelah kanan tidak pernah sampai ke layar. Empat halaman —
     * Field Break, Cuti Tahunan, Campaign, dan Riwayat — sempat menyebut
     * dirinya "Miners — Kelayakan Kerja" karena itu, tanpa satu galat
     * pun: judulnya dikirim, hanya tidak dipakai.
     *
     * Halaman yang berjudul sendiri menulis judulnya SEBELUM pemanggilan
     * ini: `['judul' => ...] + $this->bersama() + [...]`.
     */
    private function bersama(): array
    {
        return [
            'judul'    => 'Miners — Kelayakan Kerja',
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
                'tahap'        => Tahap::RANTAI,
                'sayaPenentu'  => Tahap::penentu(auth()->user()),
                'jenisFieldBreak' => MinersFieldBreak::JENIS,
                'jenisCuti'       => MinersCuti::JENIS,
                'jenisCampaign'   => MinersCampaign::JENIS,
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
            'berkasSim' => $k->berkas_sim,
            'pengalamanKerja' => $k->pengalaman_kerja,
            'berkasInduksi' => $k->berkas_induksi,
            'berkasDdt' => $k->berkas_ddt,
            'emailAtasan' => $k->email_atasan,

            'status'        => $k->status,
            'statusLabel'   => Alur::LABEL[$k->status] ?? $k->status,
            'dapatDiubah'   => $k->dapatDiubah(),
            'dapatDitinjau' => $k->dapatDitinjauOleh(auth()->user()),
            'sebabTakTinjau' => Tahap::sebabTakDapatMemutuskan(
                auth()->user(), $k->status, $k->diajukan_oleh),
            'alasanTolak'   => $k->alasan_tolak,
            'syaratKurang'  => $k->syaratKurang(),
            'berlaku'       => $k->sudahDisetujui(),

            'rantai'      => $k->rantaiTahap(),
            'tertinggal'  => $k->parafTertinggal(),
            'dapatParaf'  => $k->menungguTinjauan(),

            /* Tercetak pada kartunya sendiri. */
            'golonganDarah' => $k->golongan_darah,
            'telepon'       => $k->telepon,
            'kontakDarurat' => $k->kontak_darurat,

            /* Lampiran syarat Mine Permit — keempatnya diperiksa sebelum
               permit terbit, jadi kekosongannya harus terlihat. */
            'lampiran' => [
                'ktp'        => $k->berkas_ktp,
                'permohonan' => $k->berkas_permohonan,
                'spdk'       => $k->berkas_spdk,
                'dept'       => $k->berkas_dept,
                'lotto'      => $k->berkas_lotto,
                'blasting'   => $k->berkas_blasting,
            ],

            /* Unit SIMPER, masing-masing dengan nilai dan berkas ujinya
               sendiri. Kosong pada kartu masuk area — kosong yang benar,
               bukan yang terlupa. */
            'unit' => $k->unit->map(fn (PasporKartuUnit $u) => $u->toView())->values(),
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
