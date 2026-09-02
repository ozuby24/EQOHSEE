<?php

namespace App\Http\Controllers;

use App\Models\{Company, InduksiPengajuan, KoUnitMaster, KompetensiJenis, McuPengajuan,
    Paspor, PasporInduksi, PasporKartuUnit,
    PasporKartu, PasporMcu, PasporSertifikat};
use App\Rules\DalamPerusahaan;
use App\Models\ActivityLog as Jejak;
use App\Support\Alur;
use App\Support\AlurMiner;
use App\Support\Authority;
use App\Support\Berkas;
use App\Support\MasaBerlakuTerbaca;
use App\Support\PemantauanBerkas;
use App\Support\RelPengajuan;
use App\Support\KopDokumen;
use App\Support\LampiranMiners;
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
        /* Rantai berkasnya ikut dimuat: tiap kartu menyebut MCU,
           induksi, dan kartu yang mendasarinya, dan tanpa dimuat di sini
           itu tiga kueri tambahan per kartu. */
        $paspor->load([
            'sertifikat.jenis', 'mcu.pengajuan', 'kartu.paraf', 'kartu.unit.unitMaster',
            'kartu.mcuDasar', 'kartu.induksiDasar', 'kartu.kartuDasar',
            'induksi.mcuDasar', 'user', 'company',
        ]);

        return Inertia::render('Miners/Halaman', $this->bersama() + [
            'mode'   => 'rincian',
            'p'      => $this->baris($paspor) + [
                'departemen'    => $paspor->departemen,

                /* Nama perusahaannya. Kartu Detail sudah menyebutnya
                   sejak lama dan selama itu selalu tergambar "—":
                   medannya ada di layar, isinya tidak pernah dikirim.
                   Dilekatkan di sini, bukan di baris(), sebab daftar
                   orang tidak memuat relasi company-nya — menambahkannya
                   di sana berarti satu kueri per baris. */
                'perusahaan'    => $paspor->company?->name,

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

                /* Alamat membuka berkasnya, bukan nama berkasnya. Nama
                   berkas tidak dapat diperiksa oleh yang membacanya —
                   yang memeriksanya perlu membukanya. */
                'berkas'  => Berkas::url($s, 'srt'),
            ])->values(),
            'mcu' => $paspor->mcu->map(fn (PasporMcu $m) => [
                'id' => $m->id, 'tglPeriksa' => $m->tgl_periksa?->toDateString(),
                'tglExpired' => $m->tgl_expired?->toDateString(),
                'penyelenggara' => $m->penyelenggara, 'jenis' => $m->jenis,
                'nomor' => $m->nomor,
                'hasil' => $m->hasil, 'pembatasan' => $m->pembatasan,
                'levelRisiko' => $m->level_risiko,
                'risikoPerhatian' => $m->risikoPerluPerhatian(),
                'nomor' => $m->nomor,
                'penyelenggara' => $m->penyelenggara,
                /* Alamatnya hanya dikirim kepada yang boleh membukanya.
                   Mengirimnya kepada semua orang lalu menolak di ujung
                   menghasilkan tautan yang tampak ada lalu memulangkan
                   403 — dan yang menekannya menyimpulkan sistemnya
                   rusak, bukan bahwa ia tidak berhak. */
                'berkas' => Berkas::bolehMembuka(auth()->user(), 'mcu')
                    ? Berkas::url($m, 'mcu') : null,
                'berkasTerjaga' => (bool) $m->berkas
                    && !Berkas::bolehMembuka(auth()->user(), 'mcu'),
                'catatanKontraktor' => $m->catatan_kontraktor,
                'remarks' => $m->remarks,
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

        $orang = Paspor::with(['sertifikat', 'mcu', 'kartu', 'induksi'])->get();

        $takLayak = $orang->filter(fn (Paspor $p) => !$p->kelayakan()['layak']);

        /* Yang menunggu keputusan SAYA — bukan yang menunggu keputusan
           siapa pun. Bagi yang bukan OHSE angkanya nol, dan itu jawaban
           yang benar: pengajuan itu memang bukan urusannya. */
        $mcuMenunggu = McuPengajuan::with(['pengaju'])->menunggu()->get()
            ->filter(fn (McuPengajuan $m) => $m->dapatDitinjauOleh($u));

        $kartuMenunggu = PasporKartu::with('paspor')->menunggu()->get()
            ->filter(fn (PasporKartu $k) => $k->dapatDitinjauOleh($u));

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

        return Inertia::render('Miners/Halaman', [
            'judul'    => 'Miners — Pengajuan MCU',
            'subjudul' => 'Surat permintaan pemeriksaan ke klinik — satu surat, banyak nama',
        ] + $this->bersama() + [
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

                /* Rel pengajuan — sama seperti pada kartu.
                   Syaratnya satu: surat harus punya nama.

                   Tombol Ajukan pada layar memang sudah dijaga oleh
                   jumlah namanya, jadi yang ditambahkan di sini BUKAN
                   penjagaan melainkan sebabnya: layar lama menyembunyikan
                   tombolnya tanpa mengatakan mengapa, dan tombol yang
                   hilang tanpa keterangan sama membingungkannya dengan
                   tombol yang ditolak sesudah ditekan. */
                'alur' => RelPengajuan::bangun(
                    status: $m->status,
                    rantai: $m->rantaiTahap(),
                    kurang: $m->hasil->isEmpty()
                        ? ['Belum ada satu nama pun pada surat ini.'] : [],
                    alasanTolak: $m->alasan_tolak,
                    labelTerbit: 'Hasil masuk',
                ),

                'jumlah'       => $m->hasil->count(),
                'belumKembali' => $m->belumKembali(),
                /* Susunan medan MENGIKUTI "Manpower Table" D'Best:
                   nama, NIK, umur, tanggal MCU, noid, jabatan,
                   departemen, status, level risiko, lalu dua berkas.
                   Yang dibaca dari orangnya (NIK, noid, jabatan,
                   departemen) ikut dikirim supaya barisnya dapat
                   digambar utuh tanpa membuka berkas orangnya. */
                'nama' => $m->hasil->map(fn (PasporMcu $h) => [
                    'id'         => $h->id,
                    'pasporId'   => $h->paspor_id,
                    'nama'       => $h->paspor?->nama,
                    'nik'        => $h->paspor?->nik,
                    'noid'       => $h->paspor?->nomor_register,
                    'jabatan'    => $h->paspor?->jabatan,
                    'departemen' => $h->paspor?->departemen,

                    'usia'       => $h->usia,
                    'hasil'      => $h->hasil,
                    'levelRisiko' => $h->level_risiko,
                    'statusVerifikasi' => $h->status_verifikasi,
                    'nomor'      => $h->nomor,
                    'pembatasan' => $h->pembatasan,
                    'catatanKontraktor' => $h->catatan_kontraktor,

                    'tglPeriksa' => $h->tgl_periksa?->toDateString(),
                    'tglExpired' => $h->tgl_expired?->toDateString(),
                    'mcuBerikutnya' => $h->mcu_berikutnya?->toDateString(),
                    'rujukan'    => $h->rujukan,
                    'outstanding' => $h->outstanding?->toDateString(),
                    'tertunggak' => $h->rujukanTertunggak(),

                    /* Berkasnya hanya dialamatkan kepada yang boleh
                       membukanya — lihat Berkas::GERBANG. */
                    'berkas' => Berkas::bolehMembuka(auth()->user(), 'mcu')
                        ? Berkas::url($h, 'mcu') : null,
                    'berkasRujukan' => Berkas::bolehMembuka(auth()->user(), 'mcr')
                        ? Berkas::url($h, 'mcr') : null,
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

        /* Aturan yang SAMA dengan pencatatan langsung — lihat
           aturanMcu(). Jenis pemeriksaannya sudah ditentukan saat nama
           dimasukkan ke pengajuan, jadi tidak diwajibkan lagi di sini. */
        $data = $request->validate($this->aturanMcu(wajibJenis: false));

        if ($jalur = $this->simpanBerkasMcu($request)) $data['berkas'] = $jalur;
        if ($jalur = $this->simpanBerkasMcu($request, 'berkas_rujukan')) $data['berkas_rujukan'] = $jalur;

        /* Yang dikosongkan penilai TIDAK menghapus isi lama. Formulir
           balasan klinik hanya memuat sebagian medan; mengirimkan sisanya
           sebagai string kosong akan menghapus nomor surat dan catatan
           kontraktor yang sudah diisi pada langkah sebelumnya. */
        $mcu->update(array_filter($data, fn ($v) => $v !== null && $v !== ''));

        Jejak::write('Isi hasil MCU', $mcu->paspor?->nama.' — '.$mcu->hasil, 'miners');

        return back()->with('ok', 'Hasil MCU tersimpan.');
    }

    /* ═══════════ PENGAJUAN INDUKSI ═══════════
     *
     * Kembar dengan pengajuan MCU di atas, dan kembarnya disengaja:
     * keduanya surat berisi daftar nama yang melewati persetujuan lalu
     * diisi hasilnya satu per satu. Yang berbeda hanya rantai
     * persetujuannya — induksi diselenggarakan OHSE sendiri, jadi
     * berhenti di sana.
     */

    public function induksiIndex(Request $request)
    {
        $pengajuan = InduksiPengajuan::with(['hasil.paspor', 'pengaju', 'peninjau', 'paraf'])
            ->when($request->get('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('tanggal')->orderByDesc('id')
            ->get();

        return Inertia::render('Miners/Halaman', [
            'judul'    => 'Miners — Pengajuan Induksi',
            'subjudul' => 'Kelas induksi keselamatan — satu kelas, banyak peserta',
        ] + $this->bersama() + [
            'mode'   => 'induksi',
            'saring' => ['status' => $request->get('status')],
            'pengajuan' => $pengajuan->map(fn (InduksiPengajuan $m) => [
                'id'      => $m->id,
                'nomor'   => $m->nomor_register,
                'tanggal' => $m->tanggal?->toDateString(),
                'kepada'  => $m->lokasi,
                'judul'   => $m->judul,
                'jenis'   => $m->jenis,
                'catatan' => $m->catatan,
                'pelaksanaan' => $m->tgl_pelaksanaan?->toDateString(),

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

                /* Rel pengajuan — sama seperti pada kartu.
                   Syaratnya satu: surat harus punya nama.

                   Tombol Ajukan pada layar memang sudah dijaga oleh
                   jumlah namanya, jadi yang ditambahkan di sini BUKAN
                   penjagaan melainkan sebabnya: layar lama menyembunyikan
                   tombolnya tanpa mengatakan mengapa, dan tombol yang
                   hilang tanpa keterangan sama membingungkannya dengan
                   tombol yang ditolak sesudah ditekan. */
                'alur' => RelPengajuan::bangun(
                    status: $m->status,
                    rantai: $m->rantaiTahap(),
                    kurang: $m->hasil->isEmpty()
                        ? ['Belum ada satu nama pun pada surat ini.'] : [],
                    alasanTolak: $m->alasan_tolak,
                    labelTerbit: 'Hasil masuk',
                ),

                'jumlah'       => $m->hasil->count(),
                'belumKembali' => $m->belumDinilai(),
                'nama' => $m->hasil->map(fn (PasporInduksi $h) => [
                    'id'         => $h->id,
                    'pasporId'   => $h->paspor_id,
                    'nama'       => $h->paspor?->nama,
                    'hasil'      => $h->hasil,
                    'nilai'      => $h->nilai,
                    'tglPeriksa' => $h->tanggal?->toDateString(),
                    'tglExpired' => $h->tgl_expired?->toDateString(),
                ])->values(),
            ])->values(),

            'ringkasMcu' => [
                'total'    => $pengajuan->count(),
                'menunggu' => $pengajuan->where('status', Alur::DIAJUKAN)->count(),
                'belumKembali' => $pengajuan->sum(fn (InduksiPengajuan $m) => $m->belumDinilai()),
            ],

            'pemantauan' => PemantauanBerkas::untukDaftar(
                $this->orangPemantauan(), PemantauanBerkas::MCU),
        ]);
    }

    /** @return array<string,mixed> */
    private function aturanPengajuanInduksi(): array
    {
        return [
            'nomor_register'  => ['nullable', 'string', 'max:60'],
            'tanggal'         => ['required', 'date'],
            'judul'           => ['nullable', 'string', 'max:200'],
            'jenis'           => ['required', Rule::in(Authority::JENIS_INDUKSI)],
            'lokasi'          => ['nullable', 'string', 'max:150'],
            'tgl_pelaksanaan' => ['nullable', 'date'],
            'catatan'         => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function induksiStore(Request $request)
    {
        $data = $this->pemilik($request->validate(
            ['company_id' => ['nullable', 'exists:companies,id']] + $this->aturanPengajuanInduksi()
        ));

        $data['user_id'] = $request->user()?->getKey();

        $m = InduksiPengajuan::create($data);

        Jejak::write('Buat pengajuan induksi', $m->nomor_register ?? '#'.$m->id, 'miners');

        return back()->with('ok', 'Pengajuan induksi dibuat sebagai draf.');
    }

    public function induksiUpdate(Request $request, InduksiPengajuan $pengajuan)
    {
        abort_unless($pengajuan->dapatDiubah(), 422,
            'Pengajuan yang sudah diajukan tidak dapat diubah.');

        $pengajuan->update($request->validate($this->aturanPengajuanInduksi()));

        Jejak::write('Ubah pengajuan induksi', $pengajuan->nomor_register ?? '#'.$pengajuan->id, 'miners');

        return back()->with('ok', 'Pengajuan diperbarui.');
    }

    public function induksiDestroy(InduksiPengajuan $pengajuan)
    {
        abort_unless($pengajuan->dapatDiubah(), 422,
            'Pengajuan yang sudah diajukan tidak dapat dihapus.');

        $nomor = $pengajuan->nomor_register ?? '#'.$pengajuan->id;
        $pengajuan->delete();

        Jejak::write('Hapus pengajuan induksi', $nomor, 'miners');

        return redirect()->route('miners.induksi.index')->with('ok', 'Pengajuan dihapus.');
    }

    /**
     * Menambahkan satu nama ke dalam kelas induksi.
     *
     * SYARAT MCU-nya diperiksa DI SINI, bukan hanya saat hasilnya diisi.
     * Menginduksi orang yang ternyata Unfit adalah setengah hari kelas
     * yang terbuang — dan yang lebih buruk, namanya tercatat di daftar
     * hadir sehingga di layar ia tampak lebih siap daripada sebenarnya.
     * Menahannya pada saat pendaftaran menghemat kelasnya, bukan hanya
     * catatannya.
     */
    public function induksiTambahNama(Request $request, InduksiPengajuan $pengajuan)
    {
        abort_unless($pengajuan->dapatDiubah(), 422,
            'Nama hanya dapat ditambahkan selagi pengajuan masih draf.');

        $data = $request->validate([
            'paspor_id' => ['required', new DalamPerusahaan('paspor')],
        ]);

        if ($pengajuan->hasil()->where('paspor_id', $data['paspor_id'])->exists()) {
            return back()->withErrors(['paspor_id' => 'Nama ini sudah ada dalam pengajuan.']);
        }

        $paspor = Paspor::with(['mcu', 'induksi', 'kartu'])->findOrFail($data['paspor_id']);

        if ($sebab = AlurMiner::halanganInduksi($paspor)) {
            return back()->withErrors(['paspor_id' => $paspor->nama.': '.$sebab]);
        }

        /* MCU yang MENDASARINYA ikut dicatat, bukan hanya diperiksa lalu
           dilupakan. Enam bulan kemudian pertanyaannya bukan "apakah
           waktu itu ia layak" melainkan "atas dasar apa" — dan tanpa
           tautannya, jawabannya harus ditebak dari tanggal. */
        $dasar = $paspor->mcu
            ->filter(fn ($m) => $m->hasilLayak())
            ->sortByDesc('tgl_periksa')->first();

        $pengajuan->hasil()->create([
            'paspor_id'     => $data['paspor_id'],
            'jenis'         => $pengajuan->jenis,
            'tanggal'       => $pengajuan->tgl_pelaksanaan?->toDateString()
                ?? $pengajuan->tanggal?->toDateString(),
            'lokasi'        => $pengajuan->lokasi,
            'paspor_mcu_id' => $dasar?->id,
        ]);

        return back()->with('ok', 'Nama ditambahkan ke pengajuan.');
    }

    public function induksiHapusNama(InduksiPengajuan $pengajuan, PasporInduksi $induksi)
    {
        abort_unless($induksi->induksi_pengajuan_id === $pengajuan->id, 404);
        abort_unless($pengajuan->dapatDiubah(), 422,
            'Nama hanya dapat dihapus selagi pengajuan masih draf.');

        $induksi->delete();

        return back()->with('ok', 'Nama dikeluarkan dari pengajuan.');
    }

    /** Mengisi hasil kelas yang sudah berjalan, per nama. */
    public function induksiIsiHasil(Request $request, InduksiPengajuan $pengajuan, PasporInduksi $induksi)
    {
        abort_unless($induksi->induksi_pengajuan_id === $pengajuan->id, 404);

        $data = $request->validate([
            'tanggal'     => ['required', 'date'],
            'tgl_expired' => ['nullable', 'date', 'after_or_equal:tanggal'],
            'nomor_registrasi' => ['nullable', 'string', 'max:60'],
            'hasil'       => ['required', Rule::in(Authority::HASIL_INDUKSI)],
            'nilai'       => ['nullable', 'integer', 'min:0', 'max:100'],
            'pemberi'     => ['nullable', 'string', 'max:150'],
            'catatan'     => ['nullable', 'string', 'max:1000'],
        ]);

        $induksi->update(array_filter($data, fn ($v) => $v !== null && $v !== ''));

        Jejak::write('Isi hasil induksi', $induksi->paspor?->nama.' — '.$induksi->hasil, 'miners');

        return back()->with('ok', 'Hasil induksi tersimpan.');
    }

    public function parafInduksi(Request $request, InduksiPengajuan $pengajuan)
    {
        return $this->bubuhkan($request, $pengajuan,
            $pengajuan->nomor_register ?? '#'.$pengajuan->id);
    }

    public function ajukanInduksiPengajuan(InduksiPengajuan $pengajuan)
    {
        /* Kelas tanpa satu peserta pun tidak dikirim. Sama alasannya
           dengan surat MCU kosong: yang ditinjau peninjaunya adalah
           daftar namanya, dan daftar kosong tidak dapat ditinjau —
           hanya disetujui secara upacara. */
        if ($pengajuan->hasil()->doesntExist()) {
            return back()->withErrors([
                'induksi' => 'Pengajuan tanpa satu nama pun tidak dapat dikirim.',
            ]);
        }

        $pengajuan->ajukan();

        Jejak::write('Ajukan induksi', $pengajuan->nomor_register ?? '#'.$pengajuan->id, 'miners');

        return back()->with('ok', 'Pengajuan induksi dikirim untuk ditinjau.');
    }

    public function tinjauInduksiPengajuan(Request $request, InduksiPengajuan $pengajuan)
    {
        return $this->tinjau($request, $pengajuan, 'pengajuan induksi',
            $pengajuan->nomor_register ?? '#'.$pengajuan->id);
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
            'berkas_permohonan' => ['nullable', 'string', 'max:255'],

            /* MCU yang mendasari induksi ini — dibatasi milik orang yang
               sama, sebab induksi yang berdiri di atas MCU orang lain
               bukan salah ketik melainkan catatan yang tidak berdasar. */
            'paspor_mcu_id' => ['nullable', 'integer', Rule::exists('paspor_mcu', 'id')
                ->where('paspor_id', $paspor->id)],
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

            /* Berkas sertifikatnya. Kolomnya sudah ada sejak tabel ini
               lahir dan selama itu tidak pernah dapat diisi — tidak di
               aturan ini, tidak pula di formulirnya. Yang tersimpan
               karena itu hanya NAMA sertifikatnya, dan nama sertifikat
               tidak dapat dibedakan dari sertifikat yang tidak pernah
               ada. Jalurnya datang dari POST /miners/lampiran, yang
               sudah menyimpan berkasnya ke disk tertutup. */
            'berkas'      => ['nullable', 'string', 'max:255'],
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

    /**
     * Catat satu hasil MCU langsung pada berkas orangnya.
     *
     * SELURUH kolom yang disimpan tabelnya dapat diisi dari sini, dan
     * itu perbaikan atas keadaan sebelumnya: tujuh dari sebelas kolom
     * `paspor_mcu` tidak dapat dijangkau sama sekali. Tiga di antaranya
     * (`penyelenggara`, `catatan_kontraktor`, `remarks`) divalidasi di
     * sini tetapi tidak punya medan di layar; empat lagi (`nomor`,
     * `rujukan`, `outstanding`, `berkas`) tidak ada di keduanya.
     *
     * Yang paling merugikan `rujukan` dan `outstanding`. Model ini punya
     * `rujukanTertunggak()` dan halaman riwayat menghitungnya sebagai
     * salah satu dari empat angka ringkasan — angka yang selamanya nol,
     * sebab satu-satunya jalan mengisi rujukan adalah alur balasan
     * klinik. Ringkasan yang selalu nol tidak terbaca sebagai "belum
     * dapat diisi", melainkan sebagai "tidak ada yang tertunggak".
     */
    public function simpanMcu(Request $request, Paspor $paspor)
    {
        $data = $request->validate($this->aturanMcu());

        $data['berkas']         = $this->simpanBerkasMcu($request) ?? null;
        $data['berkas_rujukan'] = $this->simpanBerkasMcu($request, 'berkas_rujukan') ?? null;

        $paspor->mcu()->create(array_filter(
            $data, fn ($v) => $v !== null && $v !== ''
        ));

        Jejak::write('Catat MCU', $paspor->nama.' — '.$data['hasil'], 'miners');

        return back()->with('ok', 'Hasil MCU tersimpan.');
    }

    /**
     * Aturan satu catatan MCU.
     *
     * Dipakai bersama oleh pencatatan langsung dan pengisian hasil yang
     * kembali dari klinik. Dua daftar aturan terpisah untuk satu tabel
     * yang sama adalah persis bagaimana `rujukan` bisa ada di satu jalan
     * dan hilang di jalan lain tanpa ada yang menyadarinya.
     *
     * @return array<string,mixed>
     */
    private function aturanMcu(bool $wajibJenis = true): array
    {
        return [
            'tgl_periksa'   => ['required', 'date'],
            'tgl_expired'   => ['nullable', 'date', 'after_or_equal:tgl_periksa'],
            'nomor'         => ['nullable', 'string', 'max:80'],
            'penyelenggara' => ['nullable', 'string', 'max:150'],
            'jenis'         => [$wajibJenis ? 'required' : 'nullable', Rule::in(Authority::JENIS_MCU)],
            'hasil'         => ['required', Rule::in(Authority::HASIL_MCU)],
            'pembatasan'    => ['nullable', 'string', 'max:500'],

            /* Seberapa dekat ke batas kelayakan — terpisah dari hasilnya.
               Lihat Authority::LEVEL_RISIKO. */
            'level_risiko'  => ['nullable', Rule::in(Authority::LEVEL_RISIKO)],

            /* Rujukan medis dan tanggal tindak lanjutnya. Rujukan tanpa
               tanggal terhitung TERTUNGGAK, bukan diabaikan — lihat
               PasporMcu::rujukanTertunggak(). */
            'rujukan'     => ['nullable', 'string', 'max:200'],
            'outstanding' => ['nullable', 'date'],

            /* Dibaca dari D'Best. `usia` disimpan apa adanya, bukan
               dihitung dari tanggal lahir: yang tercetak pada surat MCU
               adalah usia saat pemeriksaan. */
            'usia'           => ['nullable', 'integer', 'min:15', 'max:80'],
            'mcu_berikutnya' => ['nullable', 'date', 'after_or_equal:tgl_periksa'],

            /* Verifikasi berkas, BUKAN penilaian ulang hasil medisnya. */
            'status_verifikasi'  => ['nullable', Rule::in(Authority::STATUS_MCU)],
            'catatan_kontraktor' => ['nullable', 'string', 'max:1000'],
            'remarks'            => ['nullable', 'string', 'max:1000'],

            'berkas' => ['nullable', 'file', 'max:8192', 'mimes:pdf,jpg,jpeg,png,webp'],

            /* Surat rujukannya sendiri — pada D'Best inilah yang
               diunggah paramedis. `rujukan` di atas tetap keterangan
               bebas: yang satu menjawab "dirujuk ke mana", yang ini
               "mana suratnya". */
            'berkas_rujukan' => ['nullable', 'file', 'max:8192', 'mimes:pdf,jpg,jpeg,png,webp'],
        ];
    }

    /**
     * Simpan surat MCU-nya bila ada yang diunggah.
     *
     * Suratnya yang menjadi bukti; tanpa berkas, seluruh kolom di
     * atasnya hanyalah pengetikan yang tidak dapat diperiksa siapa pun.
     * Tetap boleh kosong — hasil yang masuk lewat telepon dari klinik
     * lebih baik tercatat hari ini daripada menunggu suratnya seminggu.
     */
    private function simpanBerkasMcu(Request $request, string $medan = 'berkas'): ?string
    {
        return $request->hasFile($medan)
            ? Berkas::simpan($request->file($medan), 'miners/mcu')
            : null;
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
            'jenis_sim'          => ['nullable', Rule::in(Authority::JENIS_SIM)],
            'berkas_sim'         => ['nullable', 'string', 'max:255'],
            'sim_polisi_expired' => ['nullable', 'date'],

            /* Tercetak pada kartunya, dibaca dari D'Best. */
            'tgl_lahir'     => ['nullable', 'date'],
            'foto'          => ['nullable', 'string', 'max:255'],
            'subkontraktor' => ['nullable', 'string', 'max:150'],

            /* Rantai berkas: atas dasar apa kartu ini diterbitkan.
               Dibatasi milik orang yang sama — kartu yang berdiri di
               atas MCU orang lain bukan kekeliruan pengetikan melainkan
               izin yang tidak berdasar. */
            'paspor_mcu_id'     => ['nullable', 'integer', Rule::exists('paspor_mcu', 'id')
                ->where('paspor_id', $request->route('paspor')?->id ?? 0)],
            'paspor_induksi_id' => ['nullable', 'integer', Rule::exists('paspor_induksi', 'id')
                ->where('paspor_id', $request->route('paspor')?->id ?? 0)],
            'kartu_dasar_id'    => ['nullable', 'integer', Rule::exists('paspor_kartu', 'id')
                ->where('paspor_id', $request->route('paspor')?->id ?? 0)],
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

    /**
     * Lekatkan satu lampiran yang sudah terunggah ke kartunya.
     *
     * Rute TERSENDIRI, bukan menumpang ubahKartu(). Aturan ubahKartu
     * menuntut seluruh medan kartu — jenis, sebab terbit, tanggal —
     * sehingga kiriman yang hanya membawa satu kolom lampiran akan
     * ditolak validasinya. Menyiasatinya dengan melonggarkan aturan
     * ubahKartu berarti seluruh medan kartu menjadi opsional pada
     * SEMUA jalan masuk, dan kartu tanpa jenis dapat tersimpan.
     *
     * Hanya kolom lampiran yang boleh berubah lewat sini, dan hanya
     * selagi kartunya masih dapat diubah: menambah bukti pada kartu
     * yang sudah disetujui berarti mengubah dasar keputusan yang sudah
     * diambil tanpa melewati peninjauan yang menyetujuinya.
     */
    public function lekatkanLampiran(Request $request, Paspor $paspor, PasporKartu $kartu)
    {
        abort_unless($kartu->paspor_id === $paspor->id, 404);
        abort_unless($kartu->dapatDiubah(), 422,
            'Kartu yang sudah diajukan tidak dapat ditambahi lampiran.');

        $aturan = [];
        foreach (LampiranMiners::kolom() as $kolom) {
            $aturan[$kolom] = ['nullable', 'string', 'max:255'];
        }

        $data = array_filter($request->validate($aturan),
            fn ($v) => $v !== null && $v !== '');

        abort_if(empty($data), 422, 'Tidak ada lampiran yang dikirim.');

        $kartu->update($data);

        Jejak::write('Unggah lampiran kartu',
            $paspor->nama.' — '.implode(', ', array_keys($data)), 'miners');

        return back()->with('ok', 'Lampiran tersimpan.');
    }

    /**
     * Unggah satu lampiran syarat, lalu pulangkan alamatnya.
     *
     * SATU jalur untuk seluruh lampiran, bukan satu endpoint per
     * dokumen. Kolomnya disebut pemanggil dan divalidasi terhadap
     * katalog di App\Support\LampiranMiners — jadi lampiran baru cukup
     * ditambahkan di katalog itu, dan tidak ada rute yang harus
     * diingat untuk ikut ditambah.
     *
     * Berkasnya disimpan ke disk TERTUTUP dan alamat yang dipulangkan
     * adalah JALURNYA, bukan URL publik: yang membukanya nanti tetap
     * harus melewati BerkasController beserta batas perusahaannya.
     */
    public function unggahLampiran(Request $request)
    {
        $request->validate([
            'kolom'  => ['required', 'string', Rule::in(LampiranMiners::kolomUnggah())],
            'berkas' => ['required', 'file', 'max:8192', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $jalur = Berkas::simpan($request->file('berkas'), 'miners/lampiran');

        if (!$jalur) {
            return response()->json(['pesan' => 'Berkas tidak dapat disimpan.'], 422);
        }

        return response()->json([
            'jalur' => $jalur,
            'nama'  => $request->file('berkas')->getClientOriginalName(),
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
           beberapa jalur, sehingga SIMPER lolos karena Mine
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

    /* ═══════════ riwayat per tahap ═══════════ */

    /**
     * Satu halaman per tahap, menyilang seluruh pekerja.
     *
     * Sebelumnya induksi, Mine Permit, SIMPER, dan kompetensi
     * hanya dapat dilihat dengan membuka orangnya satu per satu. Itu
     * membuat pertanyaan yang paling sering diajukan tidak terjawab
     * sama sekali: "mana saja Mine Permit yang menunggu keputusan saya",
     * "berapa induksi yang jatuh tempo bulan ini". Pertanyaan semacam
     * itu menyilang orang, bukan menyusuri satu orang.
     *
     * Urutan menunya mengikuti urutan alurnya — MCU, induksi, Mine
     * Permit, SIMPER, Authority — supaya bilah samping itu
     * sendiri yang mengajarkan urutannya, tanpa seorang pun perlu
     * membaca petunjuk.
     */
    public function riwayat(Request $request)
    {
        /* Diambil dari defaults() rutenya, bukan dari segmen jalur:
           keempatnya rute tersendiri supaya dapat dipanggil tanpa
           parameter — lihat catatan di routes/web.php. */
        $tahap = (string) $request->route()->defaults['tahap'];

        /* Zona masa berlaku dari alamat — kartu zona di atas daftar
           menyaringnya. Zona tak dikenal diabaikan, bukan ditolak:
           tautan lama tetap memperlihatkan seluruh daftar, bukan daftar
           kosong yang terbaca sebagai "tidak ada data". */
        $zona = $request->get('zona');

        $daftar = match ($tahap) {
            'mcu'          => $this->riwayatMcu($zona),
            'induksi'      => $this->riwayatInduksi(),
            'mine-permit'  => $this->riwayatKartu(AlurMiner::KARTU_PERMIT, $request, $zona),
            'mine-license' => $this->riwayatKartu(AlurMiner::KARTU_LICENSE, $request, $zona),
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
            'zona'       => $zona,
            'rute'       => '/miners/riwayat/'.$tahap,
        ]);
    }

    /** @return array<string,mixed> */
    /**
     * Daftar induk MCU — satu baris per pemeriksaan, bukan per surat.
     *
     * Halaman /miners/mcu berisi PENGAJUAN: surat yang dikirim ke
     * klinik, satu surat memuat banyak nama. Yang tidak dijawabnya
     * pertanyaan yang justru paling sering diajukan — dari seluruh
     * pekerja, siapa saja yang MCU-nya masih berlaku hari ini, dan apa
     * hasilnya.
     *
     * Susunan kolomnya mengikuti daftar man-power D'Best: orangnya
     * lebih dulu, hasilnya, lalu masa berlakunya.
     */
    private function riwayatMcu(?string $zona = null): array
    {
        $baris = PasporMcu::with(['paspor.company', 'pengajuan'])
            ->orderByDesc('tgl_periksa')->orderByDesc('id')->get();

        if (in_array($zona, PemantauanBerkas::ZONA, true)) {
            $baris = $baris->filter(function (PasporMcu $m) use ($zona) {
                $keadaan = Authority::keadaanKartu($m->tgl_expired);

                return (PemantauanBerkas::ZONA[$keadaan] ?? 'kosong') === $zona;
            })->values();
        }

        return [
            'judul'    => 'Riwayat MCU',
            'subjudul' => 'Pemeriksaan kesehatan seluruh pekerja — hasil dan masa berlakunya',
            'kolom'    => ['No. Registrasi', 'Tanggal', 'NIK', 'Nama', 'Perusahaan',
                           'Jenis', 'Hasil', 'Risiko', 'Berlaku sampai', 'Verifikasi'],

            'baris' => $baris->map(fn (PasporMcu $m) => [
                'id'       => $m->id,
                'pasporId' => $m->paspor_id,
                'nama'     => $m->paspor?->nama,
                'jabatan'  => $m->paspor?->jabatan,
                'nomor'    => $m->nomor,
                'sel'      => [
                    $m->nomor ?: ($m->pengajuan?->nomor_register ?: '—'),
                    $m->tgl_periksa?->toDateString(),
                    $m->paspor?->nik ?: '—',
                    $m->paspor?->nama,
                    $m->paspor?->company?->name ?: '—',
                    $m->jenis ?: '—',
                    $m->hasil ?: 'Belum ada hasil',
                    $m->level_risiko ?: '—',
                    $m->tgl_expired?->toDateString() ?: '—',
                    $m->status_verifikasi ?: 'Belum diperiksa',
                ],
                'keadaan'    => $m->keadaan(),
                'keterangan' => $m->keterangan(),
                'sisaHari'   => Authority::sisaHari($m->tgl_expired),

                /* "Baik" berarti BOLEH BEKERJA, dan itu menuntut dua hal
                   sekaligus: hasilnya meloloskan, dan berkasnya sudah
                   diverifikasi. Berkas yang baru diunggah kontraktor
                   belum menjadi dasar apa pun. */
                'baik' => $m->hasilLayak() && $m->terverifikasi(),
            ])->values(),

            'ringkas' => [
                ['Total pemeriksaan', $baris->count(), 'netral'],
                ['Layak bekerja', $baris->filter(fn ($m) => $m->hasilLayak())->count(), 'baik'],
                ['Belum diverifikasi', $baris->reject(fn ($m) => $m->terverifikasi())->count(), 'ingat'],

                /* Layak bekerja TETAPI berisiko tinggi — bukan gabungan
                   "semua yang berisiko tinggi". Yang sudah Unfit bukan
                   orang yang perlu diawasi melainkan orang yang sudah
                   dihentikan, dan satu angka yang mencampur keduanya
                   tidak dapat ditindak dengan satu cara yang sama. */
                ['Risiko tinggi', $baris->filter(fn ($m) => $m->risikoPerluPerhatian())->count(), 'ingat'],

                ['Rujukan tertunggak', $baris->filter(fn ($m) => $m->rujukanTertunggak())->count(), 'gawat'],
            ],

            'pemantauan' => PemantauanBerkas::untukDaftar(
                $this->orangPemantauan(), PemantauanBerkas::MCU),
        ];
    }

    private function riwayatInduksi(): array
    {
        $baris = PasporInduksi::with(['paspor.company'])
            ->orderByDesc('tanggal')->get();

        return [
            'judul'    => 'Riwayat Induksi',
            'subjudul' => 'Induksi keselamatan seluruh pekerja — dicatat setelah hasil MCU menyatakan layak',

            /* Kolomnya mengikuti daftar induk D'Best: nomor registrasi,
               orangnya, lalu di mana dan kapan induksinya diberikan.
               Lokasi disebut karena induksi berlaku per area — yang
               diinduksi di workshop belum tentu boleh masuk pit. */
            'kolom'    => ['No. Registrasi', 'Tanggal', 'Nama', 'Perusahaan',
                           'Jenis', 'Lokasi', 'Berlaku sampai', 'Hasil'],
            'baris'    => $baris->map(fn (PasporInduksi $i) => [
                'id'       => $i->id,
                'pasporId' => $i->paspor_id,
                'nama'     => $i->paspor?->nama,
                'jabatan'  => $i->paspor?->jabatan,
                'nomor'    => $i->nomor_registrasi,
                'sel'      => [
                    $i->nomor_registrasi ?: '—',
                    $i->tanggal?->toDateString(),
                    $i->paspor?->nama,
                    $i->paspor?->company?->name ?: '—',
                    $i->jenis,
                    $i->lokasi ?: '—',
                    $i->tgl_expired?->toDateString() ?: '—',
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
    private function riwayatKartu(string $jenis, Request $request, ?string $zona = null): array
    {
        /* `paspor.mcu` ikut dimuat: keadaan kartu dihitung atas tanggal
           EFEKTIF, dan bagi Mine Permit tanggal itu dibatasi MCU
           terakhir orangnya. Tanpa dimuat di sini, satu kueri tambahan
           per baris — dan daftar ini memang berisi seluruh kartu. */
        $baris = PasporKartu::with(['paspor.mcu', 'paspor.company', 'paraf', 'peninjau'])
            ->where('jenis', $jenis)
            ->orderByDesc('tgl_terbit')->orderByDesc('id')->get();

        $permit = $jenis === AlurMiner::KARTU_PERMIT;

        /* Disaring menurut zona TANGGAL EFEKTIF, bukan tanggal cetak:
           kartu yang MCU-nya sudah habis harus muncul di zona "expired"
           meski tanggal pada kartunya masih panjang. Itu memang zona
           yang benar — orangnya tidak boleh masuk hari ini. */
        /* Zona yang tidak dikenal DIABAIKAN, bukan menyaring habis.
           Tautan lama yang menyebut zona yang sudah dihapus akan
           terbaca sebagai "tidak ada data" — kesimpulan yang jauh lebih
           berat daripada "penyaringnya tidak dikenali". */
        if (in_array($zona, PemantauanBerkas::ZONA, true)) {
            $baris = $baris->filter(function (PasporKartu $k) use ($zona) {
                $keadaan = Authority::keadaanKartu($k->expiredEfektif());

                return (PemantauanBerkas::ZONA[$keadaan] ?? 'kosong') === $zona;
            })->values();
        }

        return [
            'judul'    => $permit ? 'Riwayat Mine Permit' : 'Riwayat SIMPER',
            'subjudul' => $permit
                ? 'Izin masuk area tambang — terbit sesudah MCU dan induksi, diverifikasi OHSE'
                : 'Izin mengemudi di area tambang (A2B) — tambahan di atas Mine Permit',
            /* Kolomnya mengikuti daftar induk D'Best: yang dicari orang
               di daftar ini bukan rincian kartunya melainkan ORANGNYA —
               siapa, dari perusahaan mana, jabatan apa. Rincian kartu
               dibuka dari barisnya. */
            'kolom' => $permit
                ? ['No. Registrasi', 'Terbit', 'NIK', 'Nama', 'Jabatan', 'Perusahaan', 'Berlaku sampai']
                : ['No. SIMPER', 'Terbit', 'NIK', 'Nama', 'Jabatan', 'Perusahaan', 'Jenis SIM', 'SIM berlaku'],

            'baris' => $baris->map(fn (PasporKartu $k) => [
                'id'       => $k->id,
                'pasporId' => $k->paspor_id,
                'nama'     => $k->paspor?->nama,
                'jabatan'  => $k->paspor?->jabatan,
                'nomor'    => $k->nomor,
                'sel'      => $permit ? [
                    $k->nomor ?: '—',
                    $k->tgl_terbit?->toDateString(),
                    $k->paspor?->nik ?: '—',
                    $k->paspor?->nama,
                    $k->paspor?->jabatan ?: '—',
                    $k->paspor?->company?->name ?: '—',

                    /* Tanggal EFEKTIF, bukan yang tercetak. Kolomnya
                       bertanya "berlaku sampai kapan", dan jawabannya
                       bagi kartu yang dibatasi MCU atau SIM bukan
                       tanggal yang tertulis padanya. Sebabnya disebut
                       di kolom keadaan. */
                    $k->expiredEfektif()?->toDateString(),
                ] : [
                    $k->nomor ?: '—',
                    $k->tgl_terbit?->toDateString(),
                    $k->paspor?->nik ?: '—',
                    $k->paspor?->nama,
                    $k->paspor?->jabatan ?: '—',
                    $k->paspor?->company?->name ?: '—',

                    /* SIMPER dinilai atas SIM kepolisiannya: kelasnya
                       menentukan unit apa yang boleh dikemudikan, dan
                       masa berlakunya membatasi masa berlaku SIMPER-nya.
                       Keduanya tidak terbaca dari nomor kartunya. */
                    $k->jenis_sim ?: '—',
                    $k->sim_polisi_expired?->toDateString() ?: '—',
                ],
                'keadaan'    => $k->keadaan(),
                'keterangan' => $k->keterangan(),
                'baik'       => $k->sudahDisetujui(),

                /* Untuk pita masa berlaku: sisa harinya, dari berkas apa
                   tanggalnya berasal, dan tanggal yang tercetak pada
                   kartunya bila berbeda. Tanpa ketiganya, pitanya hanya
                   mengulang tanggal yang sudah ada di kolomnya. */
                'sisaHari'    => Authority::sisaHari($k->expiredEfektif()),
                'namaDasar'   => $k->dibatasiDasar() ? $k->namaDasar() : null,
                'tglTercetak' => $k->dibatasiDasar() ? $k->tgl_expired?->toDateString() : null,

                'status'        => $k->status,
                'statusLabel'   => Alur::LABEL[$k->status] ?? $k->status,
                'dapatDitinjau' => $k->dapatDitinjauOleh($request->user()),
                'tertinggal'    => $k->parafTertinggal(),

                /* Hanya yang sudah terbit yang dapat dicetak. */
                /* Kedua jenis kartu dapat dicetak: SIMPER pun benda
                   fisik yang dibawa ke gerbang, dan sisi belakangnya
                   justru yang menyebutkan unit apa saja yang boleh
                   dikemudikan. */
                'cetak' => $k->sudahDisetujui()
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
    /**
     * Kartu tambang siap cetak — Mine Permit maupun SIMPER.
     *
     * BERBENTUK KARTU, bukan lembar A4. Yang dibawa orangnya ke gerbang
     * adalah benda yang muat di saku dan dapat ditunjukkan sambil
     * berjalan; lembar A4 dilipat empat, basah, lalu berhenti dibawa.
     *
     * Dua sisi, dan keduanya perlu. Muka menjawab "siapa ini dan sampai
     * kapan"; belakang menjawab "boleh apa" — daftar unit beserta kelas
     * kewenangannya bagi SIMPER, dan syarat serta kontak darurat bagi
     * permit. Menjejalkan keduanya ke satu sisi menghasilkan huruf yang
     * tidak terbaca di bawah matahari.
     */
    public function cetakPermit(Paspor $paspor, PasporKartu $kartu)
    {
        abort_unless($kartu->paspor_id === $paspor->id, 404);

        abort_unless($kartu->sudahDisetujui(), 403,
            'Kartu yang belum disetujui tidak dapat dicetak.');

        $paspor->load(['mcu', 'induksi', 'kartu', 'sertifikat', 'company']);
        $kartu->load('unit.unitMaster');

        $m = $paspor->mcuTerakhir();
        $i = $paspor->induksiBerlaku();

        return Inertia::render('Print/KartuTambang', [
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

            /* Yang tercetak PADA kartunya sendiri — tidak satu pun
               dapat diambil dari tabel lain saat kartunya dicetak. */
            'kartuCetak' => [
                'jenis'         => $kartu->jenis,
                'nomor'         => $kartu->nomor,
                'foto'          => $kartu->foto,
                'tglLahir'      => $kartu->tgl_lahir?->toDateString(),
                'golonganDarah' => $kartu->golongan_darah,
                'telepon'       => $kartu->telepon,
                'kontakDarurat' => $kartu->kontak_darurat,
                'subkontraktor' => $kartu->subkontraktor,
                'area'          => $kartu->area,
                'golongan'      => $kartu->golongan,

                /* Khusus SIMPER: kelas SIM kepolisian dan masa
                   berlakunya membatasi kartunya, jadi tercetak di
                   sisinya sendiri. */
                'jenisSim'    => $kartu->jenis_sim,
                'simPolisi'   => $kartu->sim_polisi,
                'simExpired'  => $kartu->sim_polisi_expired?->toDateString(),

                /* Kewenangan per unit — inti sisi belakang SIMPER.
                   Kartu yang menyebut "Excavator" tanpa merinci tipe
                   membolehkan orang mengemudikan unit yang tidak pernah
                   diujikan kepadanya. */
                'unit' => $kartu->unit->map(fn (PasporKartuUnit $u) => [
                    'authority' => $u->authority,
                    'unit'      => $u->namaUnit(),
                    'typeMerk'  => $u->type_merk,
                    'lulus'     => $u->lulus(),
                ])->values(),
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
                'statusMcu'    => Authority::STATUS_MCU,
                'levelRisiko'  => Authority::LEVEL_RISIKO,
                'jenisSim'     => Authority::JENIS_SIM,
                'sebabKartu'   => Authority::SEBAB_KARTU,
                'jenisInduksi' => Authority::JENIS_INDUKSI,
                'hasilInduksi' => Authority::HASIL_INDUKSI,
                'statusAlur'   => Alur::LABEL,
                'tahap'        => Tahap::RANTAI,
                'sayaPenentu'  => Tahap::penentu(auth()->user()),
                'kompetensi'   => KompetensiJenis::aktif()->orderBy('urutan')
                    ->get(['id', 'nama', 'lembaga']),

                /* Master unit, untuk memilih unit apa yang diujikan saat
                   menyusun lampiran SIMPER. Tidak diwajibkan dipakai —
                   unit sewa dan unit subkontraktor kerap belum
                   terdaftar, dan menolak barisnya berarti orang yang
                   sudah diuji tidak dapat dicatat sama sekali. */
                'unit' => KoUnitMaster::where('aktif', true)
                    ->orderBy('unit')->get(['id', 'unit', 'kategori']),

                /* Kewenangan mengemudi seperti di D'Best: satu huruf
                   per golongan unit yang tercetak pada kartunya. */
                'authorityUnit' => Authority::AUTHORITY_UNIT,

                /* Katalog berkas uji unit — dipakai layar menyusun medan
                   unggah baris BARU, yang belum punya berkas apa pun.
                   Dibangkitkan dari katalog yang sama dengan yang
                   dipakai memeriksa kelengkapannya, sehingga medan di
                   layar tidak dapat berselisih dengan yang diperiksa. */
                'berkasUnit' => LampiranMiners::unitUntukLayar(null),

                /* Katalog berkas sertifikat, dengan alasan yang sama. */
                'berkasSertifikat' => LampiranMiners::sertifikatUntukLayar(null),

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

            /* ── LAMPIRAN SYARAT ──
             *
             * Tiap lampiran beserta keadaannya dan alamat membukanya —
             * bukan sekadar nama berkasnya. Bentuk lama menyimpan
             * jalurnya sebagai teks, jadi yang tergambar di layar hanya
             * pernyataan bahwa buktinya ada di suatu tempat: tidak dapat
             * dibuka, tidak dapat diperiksa, dan tidak dapat dibedakan
             * dari salah ketik.
             *
             * `lampiranKurang` menyebut yang WAJIB tetapi belum ada.
             * Permit yang terbit tanpa SPDK adalah izin masuk area
             * tambang yang syaratnya tidak pernah diperiksa — dan
             * sesudah terbit, tidak ada yang kembali memeriksanya. */
            'lampiran'       => LampiranMiners::untukLayar($k),
            'lampiranKurang' => LampiranMiners::kurang($k),

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

            /* Rel pengajuan: status, apa yang menahan, dan boleh-tidaknya
               diajukan — dihitung SEKALI di sini alih-alih disusun ulang
               dari tiga sudut layar oleh yang membacanya. */
            'alur' => RelPengajuan::bangun(
                status: $k->status,
                rantai: $k->rantaiTahap(),
                kurang: $k->syaratKurang(),
                alasanTolak: $k->alasan_tolak,
                labelTerbit: 'Terbit',
            ),

            /* Tercetak pada kartunya sendiri. */
            'golonganDarah' => $k->golongan_darah,
            'telepon'       => $k->telepon,
            'kontakDarurat' => $k->kontak_darurat,
            'tglLahir'      => $k->tgl_lahir?->toDateString(),
            'foto'          => $k->foto,
            'subkontraktor' => $k->subkontraktor,
            'jenisSim'      => $k->jenis_sim,

            /* ── rantai berkas ──
               Atas dasar apa kartu ini diterbitkan. Ditulis sekali pada
               penerbitan dan tidak ikut berpindah saat MCU baru datang;
               `dasarUsang` menandai bahwa orangnya sudah punya MCU yang
               lebih baru daripada yang dipakai menerbitkan kartu ini —
               bukan galat, tetapi hal yang perlu dilihat. */
            'dasar' => [
                'mcu' => $k->mcuDasar ? [
                    'id'      => $k->mcuDasar->id,
                    'tanggal' => $k->mcuDasar->tgl_periksa?->toDateString(),
                    'expired' => $k->mcuDasar->tgl_expired?->toDateString(),
                    'hasil'   => $k->mcuDasar->hasil,
                ] : null,

                'induksi' => $k->induksiDasar ? [
                    'id'      => $k->induksiDasar->id,
                    'tanggal' => $k->induksiDasar->tanggal?->toDateString(),
                    'expired' => $k->induksiDasar->tgl_expired?->toDateString(),
                    'jenis'   => $k->induksiDasar->jenis,
                ] : null,

                'kartu' => $k->kartuDasar ? [
                    'id'    => $k->kartuDasar->id,
                    'jenis' => $k->kartuDasar->jenis,
                    'nomor' => $k->kartuDasar->nomor,
                ] : null,

                'usang' => $k->dasarUsang(),
            ],

            /* Lampiran syarat Mine Permit — keempatnya diperiksa sebelum
               permit terbit, jadi kekosongannya harus terlihat. */
            /* Unit SIMPER, masing-masing dengan nilai dan berkas ujinya
               sendiri. Kosong pada kartu masuk area — kosong yang benar,
               bukan yang terlupa. */
            'unit' => $k->unit->map(fn (PasporKartuUnit $u) => $u->toView())->values(),

            /* Jenis kartu mana yang MEMANG menyebut unit — dijawab di
               sini, bukan dengan membandingkan teks jenisnya di layar.
               Perbandingan teks di layar adalah salinan kedua dari
               aturan yang tinggal di AlurMiner, dan salinan kedua akan
               tertinggal pada hari jenis kartunya bertambah. */
            'punyaUnit' => $k->jenis === AlurMiner::KARTU_LICENSE,
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
                'sisaHari'   => Authority::sisaHari($m->tgl_expired),
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
