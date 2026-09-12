<?php

use App\Http\Controllers\InvestigasiController;
use App\Http\Controllers\MinersController;
use App\Http\Controllers\MinersDokumenController;
use App\Http\Controllers\Hris\AbsensiController;
use App\Http\Controllers\Hris\CutiController;
use App\Http\Controllers\Hris\LemburController;
use App\Http\Controllers\Hris\HrisController;
use App\Http\Controllers\Hris\RosterController;
use App\Http\Controllers\DasborController;
use App\Http\Controllers\PembelianController;
use App\Http\Controllers\PjpController;
use App\Http\Controllers\{
    CertificateController, CourseController, DashboardController, EvaluationController,
    LearnController, NewsController, PersonaliaController, ProcedureController, ProfileController,
    QuizController, SopController, LandingController
};
use App\Http\Controllers\{CourseContentController, DocumentController, EvaluasiTemuanController, HazardController,
    HazardExportController, InspectionController, InspectionTemplateController,
    BlastingController, CostController, DispatchController, PermitController, EnergyController, EngineeringController, EnvironmentController, GeotechnicalController, GudangController, IsoController, KoController, KonservasiController, MaintenanceController, WaterController, KuesionerController, PengujianController, MineOperationsController, SignatoryController, SmkpController,
    TpkkpController, TpkkpLanjutController};
use App\Http\Controllers\{BantuanController, BerkasController, ChatController, TemuanController};
use App\Http\Controllers\Admin\{AiController, CompanyController, KeamananController, PemilikController, SystemController, UserController};
use App\Http\Controllers\PerangkatSayaController;
use Illuminate\Support\Facades\Route;

/* ============ PENEMUAN (publik) ============

   robots.txt dan sitemap.xml dibangkitkan, bukan berupa berkas statis di
   public/. Berkas statis menua diam-diam: modul baru ditambahkan,
   alamatnya tidak pernah masuk ke robots.txt, dan tidak ada yang memberi
   tahu siapa pun. Lihat PenemuanController. */
Route::get('robots.txt', [\App\Http\Controllers\PenemuanController::class, 'robots']);
Route::get('sitemap.xml', [\App\Http\Controllers\PenemuanController::class, 'sitemap']);

/* ============ VERIFIKASI SERTIFIKAT (publik) ============ */
Route::get('verifikasi/{kode}', [\App\Http\Controllers\CertificateController::class,'verify'])->name('certificates.verify');

/* ============ KUESIONER PUBLIK (tanpa login) ============ */
Route::get('q/{token}',              [KuesionerController::class,'pilih'])->name('kuesioner.pilih');
Route::get('q/{token}/selesai',      [KuesionerController::class,'selesai'])->name('kuesioner.selesai');
Route::get('q/{token}/{cat}',        [KuesionerController::class,'form'])->name('kuesioner.form');
Route::post('q/{token}/{cat}',       [KuesionerController::class,'submit'])->name('kuesioner.submit');

/* ============ PENGUJIAN PUBLIK — kuis metode PJ (tanpa login) ============
 *
 * Alurnya dijalankan server langkah demi langkah, bukan satu halaman
 * JavaScript: soal berikutnya baru ada setelah yang sekarang dijawab,
 * dan batas waktunya disimpan di server. Lihat PengujianController.
 */
Route::get('uji/{token}',            [PengujianController::class,'mulai'])->name('pengujian.mulai');
Route::post('uji/{token}/siap',      [PengujianController::class,'siap'])->name('pengujian.siap');
Route::get('uji/{token}/aturan',     [PengujianController::class,'aturan'])->name('pengujian.aturan');
Route::post('uji/{token}/jalan',     [PengujianController::class,'jalan'])->name('pengujian.jalan');
Route::get('uji/{token}/soal',       [PengujianController::class,'soal'])->name('pengujian.soal');
Route::post('uji/{token}/jawab',     [PengujianController::class,'jawab'])->name('pengujian.jawab');
Route::get('uji/{token}/selesai',    [PengujianController::class,'selesai'])->name('pengujian.selesai');

/* Halaman bayar. TERBUKA TANPA LOGIN, dan itu memang keperluannya:
   pembelinya calon pelanggan yang belum punya akun. Yang menjaganya
   token acak 48 karakter pada alamatnya — sama seperti kuesioner dan
   pengujian di atas.

   Tidak memakai id pesanan. Id berurutan berarti mengganti angkanya
   menampilkan tagihan orang lain lengkap dengan nama, telepon, dan
   nilainya, pada halaman yang memang tidak meminta login. */
Route::get('bayar/{token}',        [PembelianController::class, 'bayar'])->name('pembelian.bayar');
Route::post('bayar/{token}/bukti', [PembelianController::class, 'unggahBukti'])->name('pembelian.bukti');

/* ============ ETALASE JUAL (publik) ============

   Yang membacanya calon pembeli yang belum punya akun; di balik login
   ia hanya terbaca oleh orang yang sudah membeli.

   Pemesanannya dibatasi lajunya. Tanpa itu satu skrip dapat menerbitkan
   ribuan tagihan semalaman — tidak satu pun berisi uang, tetapi tagihan
   yang sungguhan tenggelam di baliknya, dan nomor registernya melompat
   ribuan angka. Enam per menit per alamat: cukup longgar untuk orang
   yang salah pilih lalu memesan ulang, cukup ketat untuk skrip. */
Route::get('katalog', [PembelianController::class, 'publik'])->name('katalog.publik');
Route::post('katalog/pesan', [PembelianController::class, 'pesanPublik'])
    ->middleware('throttle:6,1')->name('katalog.pesan');

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : app(LandingController::class)->index())->name('beranda');

/* 'verified' dipasang di sini, bukan per rute: halaman yang lupa
   memakainya tidak menimbulkan galat apa pun — ia hanya diam-diam
   terbuka bagi akun yang emailnya belum terbukti dimiliki pendaftarnya.
   Halaman verifikasi sendiri berada di routes/auth.php, di luar grup ini,
   supaya tidak menghalangi jalan menuju dirinya sendiri. */
Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /* Dasbor menyeluruh, TERPISAH dari dasbor pembelajaran di atas.
       Yang satu menjawab pertanyaan seorang peserta tentang kursusnya;
       yang ini menjawab pertanyaan seorang pengawas tentang situsnya.
       Menggabungkannya membuat angka kursus dan angka izin kerja
       berebut tempat yang sama, dan yang kalah selalu yang tidak
       sedang dicari orangnya. */
    Route::get('/dasbor', [DasborController::class, 'index'])->name('dasbor');

    /* Register temuan lintas modul. Berdiri di luar modul mana pun karena
       ia justru menyatukan kelimanya — menaruhnya di dalam salah satu modul
       akan menyiratkan bahwa isinya hanya milik modul itu. */
    Route::get('/temuan', [TemuanController::class, 'index'])->name('temuan.index');

    /* Menetapkan penanggung jawab dan tenggat dari register. Sumbernya
       dibatasi ke lima yang dikenal — alamat yang menerima nama kelas
       sembarang dari luar adalah cara paling mudah membuat pengguna
       menyentuh model yang tidak dimaksudkan. */
    Route::post('/temuan/{sumber}/{id}/tugaskan', [TemuanController::class, 'tugaskan'])
        ->whereIn('sumber', ['tindak-lanjut', 'smkp', 'ko', 'hazard', 'inspeksi'])
        ->whereNumber('id')
        ->name('temuan.tugaskan');

    /* ---- Berkas tertutup ----

       Satu pintu bagi dokumen, tanda tangan, foto bahaya, foto inspeksi,
       dan MSDS. Sebelumnya kelimanya tergeletak di disk publik, dan
       `storage:link` menjadikannya terbaca dari eqohsee.id/storage/…
       tanpa login — termasuk gambar tanda tangan yang dicetak pada tiap
       sertifikat sebagai bukti persetujuan.

       Berada di dalam grup ini, jadi 'auth' dan 'verified' berlaku
       tanpa perlu disebut. Batas perusahaannya ditegakkan oleh scope
       modelnya sendiri; lihat BerkasController. */
    Route::get('berkas/{jenis}/{baris}/{i?}', [BerkasController::class, 'sajikan'])
        ->whereIn('jenis', array_keys(\App\Support\Berkas::tersaji()))
        ->whereNumber('baris')->whereNumber('i')
        ->name('berkas.sajikan');

    Route::get('berkas/{jenis}/{baris}/unduh/{i?}', [BerkasController::class, 'unduh'])
        ->whereIn('jenis', array_keys(\App\Support\Berkas::tersaji()))
        ->whereNumber('baris')->whereNumber('i')
        ->name('berkas.unduh');

    /* ---- Kursus ---- */
    /* Rute admin didaftarkan LEBIH DULU: 'courses/create' harus dicoba
       sebelum 'courses/{course}', kalau tidak "create" tertangkap sebagai
       id kursus, pengikatan modelnya gagal, dan tombol Tambah Kursus
       berujung 404. Urutan ini tidak terlihat pada `route:list` — daftar
       itu diurutkan menurut abjad, bukan menurut urutan pendaftaran. */
    Route::resource('courses', CourseController::class)->except(['index', 'show'])->middleware('can:admin');
    Route::resource('courses', CourseController::class)->only(['index', 'show']);

    /* ---- Belajar ---- */
    Route::post('courses/{course}/enroll',  [LearnController::class, 'enroll'])->name('courses.enroll');
    Route::get('learn/{course}',            [LearnController::class, 'show'])->name('learn.show');
    Route::post('modules/{module}/complete',[LearnController::class, 'complete'])->name('modules.complete');
    Route::post('notes/{module}',           [LearnController::class, 'saveNote'])->name('notes.save');

    /* ---- Kuis (dinilai di server) ---- */
    Route::get('quizzes/{quiz}',        [QuizController::class, 'show'])->name('quizzes.show');
    Route::post('quizzes/{quiz}/submit',[QuizController::class, 'submit'])->name('quizzes.submit');

    /* Hasil punya alamatnya sendiri. Menggambarnya langsung dari POST
       membuat halaman itu tidak dapat dimuat ulang maupun ditautkan:
       peramban akan mengirim ulang jawabannya, dan percobaan kedua
       tercatat tanpa ada yang benar-benar mengerjakannya lagi. */
    Route::get('quizzes/{quiz}/hasil/{attempt}', [QuizController::class, 'hasil'])->name('quizzes.result');

    /* ---- Prosedur ---- */
    Route::resource('procedures', ProcedureController::class)->only(['index']);
    Route::resource('procedures', ProcedureController::class)->except(['index','show'])->middleware('can:admin');

    /* ---- Evaluasi SOP (kunci jawaban tidak dikirim ke klien) ---- */
    Route::get('sop',                     [SopController::class, 'index'])->name('sop.index');
    Route::get('sop/{evaluation}',        [SopController::class, 'show'])->name('sop.show');
    Route::post('sop/{evaluation}/grade', [SopController::class, 'grade'])->name('sop.grade');
    Route::get('sop/{evaluation}/hasil/{attempt}', [SopController::class, 'hasil'])->name('sop.result');

    /* ---- Sertifikat ---- */
    Route::get('certificates',               [CertificateController::class, 'index'])->name('certificates.index');
    Route::post('certificates/{course}',     [CertificateController::class, 'store'])->name('certificates.store');
    Route::get('certificates/{certificate}', [CertificateController::class, 'show'])->name('certificates.show');

    /* ---- Berita ----

       Rute admin didaftarkan lebih dulu. Kalau tidak, 'news/{news}'
       menangkap 'news/create' sebagai id berita, pengikatan modelnya
       gagal, dan tombol "Berita Baru" berujung 404 — bukan galat izin
       yang menjelaskan apa pun. */
    Route::resource('news', NewsController::class)->except(['index','show'])->middleware('can:admin');
    Route::resource('news', NewsController::class)->only(['index','show']);

    /* ---- Evaluasi Pasca-Pelatihan (oleh trainer) ---- */
    Route::get('evaluations',              [EvaluationController::class, 'index'])->name('evaluations.index');
    Route::get('evaluations/{evaluation}', [EvaluationController::class, 'show'])->name('evaluations.show');
    Route::middleware('can:trainer')->group(function () {
        Route::get('evaluations-create',          [EvaluationController::class, 'create'])->name('evaluations.create');
        Route::post('evaluations',                [EvaluationController::class, 'store'])->name('evaluations.store');
        Route::get('evaluations/{evaluation}/edit',[EvaluationController::class, 'edit'])->name('evaluations.edit');
        Route::put('evaluations/{evaluation}',    [EvaluationController::class, 'update'])->name('evaluations.update');
        Route::delete('evaluations/{evaluation}', [EvaluationController::class, 'destroy'])->name('evaluations.destroy');
    });

    /* ---- Kelola konten kursus (admin) ---- */
    Route::middleware('can:admin')->group(function () {
        Route::get('courses/{course}/manage',      [CourseContentController::class,'manage'])->name('manage.course');
        Route::post('courses/{course}/modules',    [CourseContentController::class,'storeModule'])->name('manage.module.store');
        Route::put('modules/{module}',             [CourseContentController::class,'updateModule'])->name('manage.module.update');
        Route::delete('modules/{module}',          [CourseContentController::class,'destroyModule'])->name('manage.module.destroy');
        Route::post('modules/{module}/materials',  [CourseContentController::class,'storeMaterial'])->name('manage.material.store');
        Route::delete('materials/{material}',      [CourseContentController::class,'destroyMaterial'])->name('manage.material.destroy');
        Route::post('courses/{course}/quizzes',    [CourseContentController::class,'storeQuiz'])->name('manage.quiz.store');
        Route::delete('quizzes/{quiz}',            [CourseContentController::class,'destroyQuiz'])->name('manage.quiz.destroy');
        Route::post('quizzes/{quiz}/questions',    [CourseContentController::class,'storeQuestion'])->name('manage.question.store');
        Route::delete('questions/{question}',      [CourseContentController::class,'destroyQuestion'])->name('manage.question.destroy');

        Route::get('signatories',               [SignatoryController::class,'index'])->name('signatories.index');
        Route::post('signatories',              [SignatoryController::class,'store'])->name('signatories.store');
        Route::put('signatories/{signatory}',   [SignatoryController::class,'update'])->name('signatories.update');
        Route::delete('signatories/{signatory}',[SignatoryController::class,'destroy'])->name('signatories.destroy');
    });


    /* ================= WEBSITE #1b — Authority: kelayakan kerja =================

       Diletakkan tepat sesudah LMS: keduanya berbicara tentang orang yang
       sama. LMS menerbitkan sertifikat pelatihan internal; Authority
       menyimpan seluruh berkas kelayakan kerjanya — kompetensi, MCU, dan
       kartu masuk tambang — dan menjawab satu pertanyaan yang ditanyakan
       setiap pagi di gerbang: boleh atau tidak orang ini bekerja hari ini. */
    /* ================= MINERS =================
     *
     * MCU → Mine Permit → SIMPER, satu rantai berurutan. Rutenya
     * mengikuti urutan itu, bukan abjad: bilah samping adalah tempat
     * orang belajar urutan sebuah proses tanpa membaca petunjuk.
     */
    Route::prefix('miners')->name('miners.')->group(function () {
        Route::get('dasbor', [MinersController::class, 'dasbor'])->name('dasbor');

        /* Seluruh rute berkata-tetap didaftarkan SEBELUM rute
           ber-{pekerja}. `miners/mcu` cocok pula dengan pola
           `miners/{pekerja}`, dan yang terdaftar lebih dahulu yang
           menang — terbalik, halaman MCU akan mencari pekerja bernomor
           "mcu" lalu memulangkan 404 yang membingungkan. */

        Route::prefix('mcu')->name('mcu.')->group(function () {
            Route::get('/',  [MinersDokumenController::class, 'mcuIndex'])->name('index');
            Route::post('/', [MinersDokumenController::class, 'mcuStore'])->name('store');

            Route::put('{mcu}',    [MinersDokumenController::class, 'mcuUpdate'])->name('update');
            Route::delete('{mcu}', [MinersDokumenController::class, 'mcuDestroy'])
                ->middleware('can:admin')->name('destroy');

            Route::post('{mcu}/orang',          [MinersDokumenController::class, 'mcuTambahOrang'])->name('orang.tambah');
            Route::delete('{mcu}/orang/{orang}', [MinersDokumenController::class, 'mcuHapusOrang'])->name('orang.hapus');
            Route::post('{mcu}/orang/{orang}/hasil',   [MinersDokumenController::class, 'mcuHasil'])->name('hasil');
            Route::post('{mcu}/orang/{orang}/rujukan', [MinersDokumenController::class, 'mcuRujukan'])->name('rujukan');
            Route::post('{mcu}/tindak',         [MinersDokumenController::class, 'mcuTindak'])->name('tindak');
        });

        Route::prefix('induksi')->name('induksi.')->group(function () {
            Route::get('/',  [MinersDokumenController::class, 'induksiIndex'])->name('index');
            Route::post('/', [MinersDokumenController::class, 'induksiStore'])->name('store');

            Route::put('{induksi}',    [MinersDokumenController::class, 'induksiUpdate'])->name('update');
            Route::delete('{induksi}', [MinersDokumenController::class, 'induksiDestroy'])
                ->middleware('can:admin')->name('destroy');

            Route::post('{induksi}/orang',           [MinersDokumenController::class, 'induksiTambahOrang'])->name('orang.tambah');
            Route::delete('{induksi}/orang/{orang}', [MinersDokumenController::class, 'induksiHapusOrang'])->name('orang.hapus');
            Route::post('{induksi}/orang/{orang}/nilai', [MinersDokumenController::class, 'induksiNilai'])->name('nilai');
            Route::post('{induksi}/tindak',          [MinersDokumenController::class, 'induksiTindak'])->name('tindak');
        });

        Route::prefix('permit')->name('permit.')->group(function () {
            Route::get('/',  [MinersDokumenController::class, 'permitIndex'])->name('index');
            Route::post('/', [MinersDokumenController::class, 'permitStore'])->name('store');

            Route::put('{permit}',    [MinersDokumenController::class, 'permitUpdate'])->name('update');
            Route::delete('{permit}', [MinersDokumenController::class, 'permitDestroy'])
                ->middleware('can:admin')->name('destroy');

            Route::post('{permit}/berkas', [MinersDokumenController::class, 'permitBerkas'])->name('berkas');
            Route::post('{permit}/cabut',  [MinersDokumenController::class, 'permitCabut'])->name('cabut');
            Route::post('{permit}/tindak', [MinersDokumenController::class, 'permitTindak'])->name('tindak');
        });

        Route::prefix('simper')->name('simper.')->group(function () {
            Route::get('/',  [MinersDokumenController::class, 'simperIndex'])->name('index');
            Route::post('/', [MinersDokumenController::class, 'simperStore'])->name('store');

            Route::put('{simper}',    [MinersDokumenController::class, 'simperUpdate'])->name('update');
            Route::delete('{simper}', [MinersDokumenController::class, 'simperDestroy'])
                ->middleware('can:admin')->name('destroy');

            Route::post('{simper}/unit',          [MinersDokumenController::class, 'simperUnit'])->name('unit.tambah');
            Route::put('{simper}/unit/{unit}',    [MinersDokumenController::class, 'simperUnitUbah'])->name('unit.ubah');
            Route::delete('{simper}/unit/{unit}', [MinersDokumenController::class, 'simperUnitHapus'])
                ->middleware('can:admin')->name('unit.hapus');

            Route::post('{simper}/tindak', [MinersDokumenController::class, 'simperTindak'])->name('tindak');
            Route::post('{simper}/ajuan',  [MinersDokumenController::class, 'ajuanStore'])->name('ajuan.store');
        });

        Route::prefix('ajuan')->name('ajuan.')->group(function () {
            Route::post('{ajuan}/unit',   [MinersDokumenController::class, 'ajuanUnit'])->name('unit');
            Route::post('{ajuan}/tindak', [MinersDokumenController::class, 'ajuanTindak'])->name('tindak');
            Route::delete('{ajuan}',      [MinersDokumenController::class, 'ajuanDestroy'])
                ->middleware('can:admin')->name('destroy');
        });

        /* Riwayat per tahap, urut mengikuti alurnya.
         *
         * Lima nama rute tersendiri, bukan satu rute berparameter.
         * Sebabnya bukan gaya: RuteInertiaTest memanggil SETIAP nama
         * rute terdaftar tanpa parameter untuk memastikan halamannya
         * benar-benar Inertia. Satu rute berparameter memaksa uji itu
         * menyimpan daftar parameter contoh — dan daftar semacam itu
         * adalah tempat pertama yang tertinggal saat rutenya berubah. */
        foreach (['mcu', 'induksi', 'mine-permit', 'mine-license', 'authority'] as $tahap) {
            Route::get('riwayat/'.$tahap, [MinersController::class, 'riwayat'])
                ->defaults('tahap', $tahap)
                ->name('riwayat.'.$tahap);
        }

        /* Daftar menyilang orang — antrean di meja saya, SIMPER
           lanjutan, rujukan, dan kartu siap cetak. Satu nama rute per
           jenis, dengan alasan yang sama seperti riwayat di atas. */
        foreach ([
            'outstanding-mcu', 'outstanding-permit', 'outstanding-simper', 'outstanding-induksi',
            'penambahan-unit', 'upgrade-simper', 'perpanjangan',
            'rujukan', 'cetak-kartu',
        ] as $jenis) {
            Route::get('daftar/'.$jenis, [MinersController::class, 'daftar'])
                ->defaults('jenis', $jenis)
                ->name('daftar.'.$jenis);
        }

        /* Pemantauan masa berlaku — halaman tersendiri, sebab
           pertanyaannya berbeda dari daftar orang: bukan "siapa saja
           pekerja kita" melainkan "siapa yang hari ini tidak boleh
           masuk". */
        Route::get('kedaluwarsa', [MinersController::class, 'kedaluwarsa'])->name('kedaluwarsa');

        Route::get('/',  [MinersController::class, 'index'])->name('index');
        Route::post('/', [MinersController::class, 'store'])->name('store');

        Route::get('{pekerja}',    [MinersController::class, 'show'])->name('show');
        Route::put('{pekerja}',    [MinersController::class, 'update'])->name('update');
        Route::delete('{pekerja}', [MinersController::class, 'destroy'])
            ->middleware('can:admin')->name('destroy');
    });


    /* ================= HRIS =================
     *
     * SATU MODUL, BUKAN SATU PER FITUR. Roster menjawab kapan
     * seseorang seharusnya bekerja; absensi menjawab apakah ia
     * benar-benar bekerja. Keduanya membaca daftar orang yang sama,
     * dijalankan bagian yang sama, dan saling merujuk pada tiap
     * layarnya — dipisah menjadi dua modul bilah samping, yang
     * mengurusnya berpindah-pindah antar dua tempat untuk satu
     * pekerjaan.
     *
     * Dan yang berikutnya masih banyak: cuti, lembur, kontrak PKWT,
     * penggajian. Tiap-tiapnya sebagai modul tersendiri akan
     * menambahkan satu baris lagi ke bilah samping yang sudah berisi
     * dua puluh delapan — sampai tidak ada lagi yang dapat menemukan
     * apa pun di sana. Sebagai grup di dalam HRIS, bilahnya tidak
     * bertambah panjang sama sekali.
     *
     * TETAP TERPISAH DARI MINERS, dan itu disengaja. Miners menjawab
     * "BOLEH atau tidak orang ini bekerja" menurut Kepmen ESDM 1827 —
     * MCU, induksi, Mine Permit, SIMPER — dan yang membacanya
     * paramedis, OHSE, dan KTT. HRIS menjawab "KAPAN dan APAKAH ia
     * bekerja", dan yang membacanya bagian personalia serta pengawas
     * pos jaga. Dilebur, satu daftar berkas K3 yang diminta Inspektur
     * Tambang harus dicari lewat layar penggajian.
     */
    Route::prefix('hris')->name('hris.')->group(function () {

        /* ---- Roster & shift ---- */
        Route::prefix('roster')->name('roster.')->group(function () {
            /* Rute berkata-tetap didaftarkan lebih dahulu, sebab `pola`
               dan `kebutuhan` cocok pula dengan pola berparameter di
               bawahnya. */
            Route::get('pola',      [RosterController::class, 'pola'])->name('pola');
            Route::get('kebutuhan', [RosterController::class, 'kebutuhan'])->name('kebutuhan');

            Route::post('pola',          [RosterController::class, 'polaSimpan'])->name('pola.simpan');
            Route::put('pola/{pola}',    [RosterController::class, 'polaUbah'])->name('pola.ubah');
            Route::delete('pola/{pola}', [RosterController::class, 'polaHapus'])
                ->middleware('can:admin')->name('pola.hapus');

            Route::post('regu',          [RosterController::class, 'reguSimpan'])->name('regu.simpan');
            Route::put('regu/{regu}',    [RosterController::class, 'reguUbah'])->name('regu.ubah');
            Route::delete('regu/{regu}', [RosterController::class, 'reguHapus'])
                ->middleware('can:admin')->name('regu.hapus');

            Route::post('regu/{regu}/anggota',             [RosterController::class, 'anggotaTambah'])->name('anggota.tambah');
            Route::delete('regu/{regu}/anggota/{anggota}', [RosterController::class, 'anggotaHapus'])->name('anggota.hapus');

            Route::post('regu/{regu}/susun',     [RosterController::class, 'susun'])->name('susun');
            Route::post('regu/{regu}/terbitkan', [RosterController::class, 'terbitkan'])->name('terbitkan');

            Route::post('kebutuhan',               [RosterController::class, 'kebutuhanSimpan'])->name('kebutuhan.simpan');
            Route::delete('kebutuhan/{kebutuhan}', [RosterController::class, 'kebutuhanHapus'])
                ->middleware('can:admin')->name('kebutuhan.hapus');

            Route::put('{roster}', [RosterController::class, 'ubah'])->name('ubah');

            Route::get('/', [RosterController::class, 'index'])->name('index');
        });

        /* ---- Absensi ---- */
        Route::prefix('absensi')->name('absensi.')->group(function () {
            Route::get('rekap', [AbsensiController::class, 'rekap'])->name('rekap');
            Route::get('mesin', [AbsensiController::class, 'mesin'])->name('mesin');

            Route::post('mesin',               [AbsensiController::class, 'mesinSimpan'])->name('mesin.simpan');
            Route::put('mesin/{mesin}',        [AbsensiController::class, 'mesinUbah'])->name('mesin.ubah');
            Route::post('mesin/{mesin}/token', [AbsensiController::class, 'mesinToken'])
                ->middleware('can:admin')->name('mesin.token');
            Route::delete('mesin/{mesin}',     [AbsensiController::class, 'mesinHapus'])
                ->middleware('can:admin')->name('mesin.hapus');

            Route::post('catat',        [AbsensiController::class, 'catat'])->name('catat');
            Route::post('rekonsiliasi', [AbsensiController::class, 'rekonsiliasi'])->name('rekonsiliasi');

            Route::put('{absensi}', [AbsensiController::class, 'koreksi'])->name('koreksi');

            Route::get('/', [AbsensiController::class, 'index'])->name('index');
        });

        /* ---- Cuti & izin ---- */
        Route::prefix('cuti')->name('cuti.')->group(function () {
            Route::get('saldo', [CutiController::class, 'saldo'])->name('saldo');

            Route::post('/', [CutiController::class, 'store'])->name('simpan');

            /* Tindakan atas pengajuan orang lain. Penjagaan "yang
               mengajukan tidak boleh menyetujui sendiri" ada di
               JalurCuti, bukan di middleware — middleware tidak tahu
               siapa yang mengajukan baris ini. */
            Route::post('{cuti}/setujui',  [CutiController::class, 'setujui'])->name('setujui');
            Route::post('{cuti}/tolak',    [CutiController::class, 'tolak'])->name('tolak');
            Route::post('{cuti}/teruskan', [CutiController::class, 'teruskan'])->name('teruskan');
            Route::post('{cuti}/batalkan', [CutiController::class, 'batalkan'])->name('batalkan');

            Route::get('/', [CutiController::class, 'index'])->name('index');
        });

        /* ---- Lembur (SPL) ---- */
        Route::prefix('lembur')->name('lembur.')->group(function () {
            Route::get('upah',  [LemburController::class, 'upah'])->name('upah');
            Route::post('upah', [LemburController::class, 'upahSimpan'])->name('upah.simpan');

            Route::post('usulkan', [LemburController::class, 'usulkan'])->name('usulkan');

            Route::post('{lembur}/setujui',  [LemburController::class, 'setujui'])->name('setujui');
            Route::post('{lembur}/tolak',    [LemburController::class, 'tolak'])->name('tolak');
            Route::post('{lembur}/batalkan', [LemburController::class, 'batalkan'])->name('batalkan');

            Route::get('/', [LemburController::class, 'index'])->name('index');
        });

        Route::get('/', [HrisController::class, 'index'])->name('index');
    });
    /* ================= INVESTIGASI KECELAKAAN =================
     *
     * Modul tersendiri, TERPISAH dari Miners. Miners menjawab "boleh
     * atau tidak orang ini bekerja hari ini"; modul ini menjawab
     * "mengapa kejadian ini terjadi dan apa yang membuatnya tidak
     * terulang". Keduanya dibaca orang berbeda pada waktu berbeda, dan
     * menyelipkan yang kedua ke dalam yang pertama membuat berkas
     * investigasi hanya dapat ditemukan lewat halaman seorang pekerja —
     * padahal yang dicari selalu kejadiannya, bukan orangnya.
     */
    /* ================= Pembelian & lisensi ================= */
    Route::prefix('pembelian')->name('pembelian.')->group(function () {
        Route::get('/',        [PembelianController::class, 'katalog'])->name('katalog');
        Route::post('pesan',   [PembelianController::class, 'simpan'])->name('simpan');

        /* 'tagihan' didaftarkan SEBELUM 'tagihan/{pesanan}': tanpa itu
           kata "tagihan" terbaca sebagai nomor pesanan. */
        /* Daftar harga. Sebelum 'tagihan/{pesanan}' karena alasan yang
           sama: kata yang bukan angka tidak boleh terbaca sebagai nomor. */
        Route::get('produk',   [PembelianController::class, 'produk'])->name('produk');
        Route::put('produk/{produk}', [PembelianController::class, 'simpanProduk'])
            ->name('produk.simpan');

        Route::get('tagihan',  [PembelianController::class, 'daftar'])->name('daftar');
        Route::get('tagihan/{pesanan}', [PembelianController::class, 'tagihan'])
            ->whereNumber('pesanan')->name('tagihan');

        /* Ketiganya mengubah keadaan uang, jadi POST — bukan tautan GET
           yang dapat terpicu prefetch peramban tanpa disentuh siapa pun.
           Kewenangannya diperiksa di dalam controller supaya pesan
           penolakannya dapat menyebut alasannya. */
        Route::post('tagihan/{pesanan}/verifikasi', [PembelianController::class, 'verifikasi'])->name('verifikasi');
        Route::post('tagihan/{pesanan}/tolak',      [PembelianController::class, 'tolak'])->name('tolak');
        Route::post('tagihan/{pesanan}/batal',      [PembelianController::class, 'batal'])->name('batal');
    });

    Route::prefix('investigasi')->name('investigasi.')->group(function () {
        Route::get('/', [InvestigasiController::class, 'dasbor'])->name('dasbor');

        /* Register insiden dan formulir laporannya didaftarkan SEBELUM
           rute ber-{insiden}: `insiden/baru` cocok pula dengan pola
           `insiden/{insiden}`, dan yang terdaftar lebih dahulu yang
           menang. Terbalik, halaman formulir akan mencari insiden
           bernomor "baru" dan memulangkan 404 yang membingungkan. */
        Route::get('insiden',       [InvestigasiController::class, 'insiden'])->name('insiden');
        Route::get('insiden/baru',  [InvestigasiController::class, 'insidenBaru'])->name('insiden.baru');
        Route::post('insiden',      [InvestigasiController::class, 'insidenSimpan'])->name('insiden.simpan');

        Route::get('insiden/{insiden}', [InvestigasiController::class, 'insidenDetail'])
            ->whereNumber('insiden')->name('insiden.detail');

        Route::get('insiden/{insiden}/triase',  [InvestigasiController::class, 'triase'])
            ->whereNumber('insiden')->name('triase');
        Route::post('insiden/{insiden}/triase', [InvestigasiController::class, 'triaseSimpan'])
            ->whereNumber('insiden')->name('triase.simpan');

        /* Membuka investigasi memakai POST, bukan tautan GET: aksinya
           membuat baris baru, dan tautan GET dapat terpicu prefetch
           peramban tanpa pengguna menyentuh apa pun. */
        Route::post('insiden/{insiden}/buka', [InvestigasiController::class, 'investigasiBuka'])
            ->whereNumber('insiden')->name('buka');

        Route::get('berkas', [InvestigasiController::class, 'investigasi'])->name('daftar');

        Route::get('berkas/{investigasi}', [InvestigasiController::class, 'detail'])
            ->whereNumber('investigasi')->name('detail');

        /* ── aksi tulis pada ruang kerja ──
         *
         * SELURUHNYA POST, termasuk penghapusan dan pemajuan tahap.
         * Tautan GET yang menghapus bukti atau memajukan tahap dapat
         * terpicu prefetch peramban tanpa pengguna menyentuh apa pun,
         * dan pada berkas yang dapat diminta Inspektur Tambang, satu
         * penghapusan yang tidak disengaja tidak dapat dijelaskan
         * kepada siapa pun. */
        Route::prefix('berkas/{investigasi}')->whereNumber('investigasi')->group(function () {
            Route::post('keterangan', [InvestigasiController::class, 'simpanKeterangan'])->name('keterangan');

            Route::post('tim',                 [InvestigasiController::class, 'timTambah'])->name('tim.tambah');
            Route::post('tim/{tim}/hapus',     [InvestigasiController::class, 'timHapus'])->name('tim.hapus');

            Route::post('kronologi',                     [InvestigasiController::class, 'kronologiTambah'])->name('kronologi.tambah');
            Route::post('kronologi/{kronologi}/hapus',   [InvestigasiController::class, 'kronologiHapus'])->name('kronologi.hapus');

            Route::post('bukti',                 [InvestigasiController::class, 'buktiTambah'])->name('bukti.tambah');
            Route::post('bukti/{bukti}/kunci',   [InvestigasiController::class, 'buktiKunci'])->name('bukti.kunci');
            Route::post('bukti/{bukti}/hapus',   [InvestigasiController::class, 'buktiHapus'])->name('bukti.hapus');

            Route::post('akar',               [InvestigasiController::class, 'akarTambah'])->name('akar.tambah');
            Route::post('akar/{akar}/hapus',  [InvestigasiController::class, 'akarHapus'])->name('akar.hapus');

            Route::post('temuan',                          [InvestigasiController::class, 'temuanTambah'])->name('temuan.tambah');
            Route::post('temuan/{temuan}/hapus',           [InvestigasiController::class, 'temuanHapus'])->name('temuan.hapus');
            Route::post('temuan/{temuan}/tindakan',        [InvestigasiController::class, 'tindakanTambah'])->name('tindakan.tambah');
            Route::post('tindakan/{tindakan}/status',      [InvestigasiController::class, 'tindakanStatus'])->name('tindakan.status');
            Route::post('tindakan/{tindakan}/hapus',       [InvestigasiController::class, 'tindakanHapus'])->name('tindakan.hapus');

            Route::post('tahap/maju',   [InvestigasiController::class, 'tahapMaju'])->name('tahap.maju');
            Route::post('tahap/mundur', [InvestigasiController::class, 'tahapMundur'])->name('tahap.mundur');
            Route::post('tutup',        [InvestigasiController::class, 'tutup'])->name('tutup');
            Route::post('buka-lagi',    [InvestigasiController::class, 'bukaLagi'])->name('bukaLagi');

            Route::post('pembelajaran', [InvestigasiController::class, 'pembelajaranTambah'])->name('pembelajaran');

            /* Analisis SCAT dan wawancara punya LAYARNYA SENDIRI.
               Keduanya memang bagian dari berkas yang sama, tetapi
               masing-masing menampung katalog panjang — 252 butir
               penyebab dan 46 pertanyaan — dan menempelkannya ke ruang
               kerja membuat halaman yang sudah sepuluh blok menjadi
               tidak terbaca. Alamatnya tetap di bawah berkasnya supaya
               kepemilikan dan lingkup perusahaannya diperiksa di satu
               tempat yang sama. */
            Route::get('analisis',  [InvestigasiController::class, 'analisis'])->name('analisis');
            Route::post('scat',                  [InvestigasiController::class, 'scatPilih'])->name('scat.pilih');
            Route::post('scat/{pilihan}/hapus',  [InvestigasiController::class, 'scatHapus'])->name('scat.hapus');
            Route::post('scat/catatan',          [InvestigasiController::class, 'scatCatatan'])->name('scat.catatan');

            Route::get('wawancara', [InvestigasiController::class, 'wawancara'])->name('wawancara');
            Route::post('wawancara',                       [InvestigasiController::class, 'wawancaraTambah'])->name('wawancara.tambah');
            Route::post('wawancara/{wawancara}/jawab',     [InvestigasiController::class, 'wawancaraJawab'])->name('wawancara.jawab');
            Route::post('wawancara/{wawancara}/hapus',     [InvestigasiController::class, 'wawancaraHapus'])->name('wawancara.hapus');
        });
    });

    /* ================= PJP — PERUSAHAAN JASA PERTAMBANGAN ================= */
    /*
     * Seluruhnya di dalam grup auth+verified di atas, dan itu perubahan
     * yang disengaja terhadap kit asalnya: di sana modul ini berdiri
     * sendiri tanpa satu pun lapis autentikasi, sehingga daftar mitra,
     * dokumen laporannya, dan nilai evaluasinya terbuka bagi siapa saja
     * yang tahu alamatnya.
     */
    Route::prefix('pjp')->name('pjp.')->group(function () {
        Route::get('/', [PjpController::class, 'dasbor'])->name('dasbor');

        /* Yang beralamat tetap didaftarkan SEBELUM rute ber-{pjp}:
           `pjp/baru` cocok pula dengan pola `pjp/{pjp}`, dan yang
           terdaftar lebih dahulu yang menang. Terbalik, halaman formulir
           akan mencari mitra bernomor "baru" dan memulangkan 404 yang
           membingungkan. Pola {pjp} juga dibatasi angka, supaya pola itu
           tidak pernah menelan alamat baru yang ditulis orang kemudian. */
        Route::get('daftar',  [PjpController::class, 'index'])->name('index');
        Route::get('csv',     [PjpController::class, 'csv'])->name('csv');
        Route::get('cetak',   [PjpController::class, 'cetak'])->name('cetak');
        Route::get('baru',    [PjpController::class, 'baru'])->name('baru');
        Route::post('baru',   [PjpController::class, 'simpan'])->name('simpan');

        Route::prefix('{pjp}')->whereNumber('pjp')->group(function () {
            Route::get('/',        [PjpController::class, 'rincian'])->name('rincian');
            Route::get('ubah',     [PjpController::class, 'sunting'])->name('sunting');
            Route::put('/',        [PjpController::class, 'perbarui'])->name('perbarui');

            Route::post('laporan',             [PjpController::class, 'laporanSimpan'])->name('laporan.simpan');
            Route::patch('laporan/{laporan}',  [PjpController::class, 'laporanNilai'])->name('laporan.nilai');

            Route::post('evaluasi',              [PjpController::class, 'evaluasiSimpan'])->name('evaluasi.simpan');

            /* PENGHAPUSAN HANYA UNTUK ADMINISTRATOR, sama seperti modul
               lain di berkas ini.

               Bukan sekadar keseragaman. Yang dihapus di sini adalah
               bukti pemantauan yang dapat diminta Inspektur Tambang:
               dokumen laporan mitra, nilai evaluasinya, dan — pada rute
               pertama — seluruh berkas mitra sekaligus, beserta jawaban
               daftar periksa yang mungkin disusun berbulan-bulan.
               Layarnya memang menyembunyikan tombolnya dari yang bukan
               admin, tetapi penjagaan yang hanya ada di peramban
               dilewati satu permintaan yang disusun tangan. */
            Route::middleware('can:admin')->group(function () {
                Route::delete('/',                   [PjpController::class, 'hapus'])->name('hapus');
                Route::delete('laporan/{laporan}',   [PjpController::class, 'laporanHapus'])->name('laporan.hapus');
                Route::delete('evaluasi/{evaluasi}', [PjpController::class, 'evaluasiHapus'])->name('evaluasi.hapus');
            });

            Route::get('checklist',  [PjpController::class, 'checklist'])->name('checklist');
            Route::post('checklist', [PjpController::class, 'checklistSimpan'])->name('checklist.simpan');
        });
    });

    /* ================= WEBSITE #2 — SafeMine TPKKP ================= */
        /* ================= KO / SPIP ================= */
        Route::prefix('ko')->name('ko.')->group(function () {
            Route::get('/',                    [KoController::class,'index'])->name('index');
            Route::get('register',             [KoController::class,'register'])->name('register');
            Route::get('objek/baru',           [KoController::class,'create'])->name('create');
            Route::post('objek',               [KoController::class,'store'])->name('store');
            Route::get('objek/{objek}',        [KoController::class,'show'])->name('show');
            Route::get('objek/{objek}/edit',   [KoController::class,'edit'])->name('edit');
            Route::put('objek/{objek}',        [KoController::class,'update'])->name('update');
            Route::delete('objek/{objek}',     [KoController::class,'destroy'])->name('destroy');

            Route::get('kelayakan',            [KoController::class,'kelayakan'])->name('kelayakan');

            /* Daftar acuan jenis unit SPIP — dipelihara pemakainya. */
            Route::get('unit',                 [KoController::class,'unit'])->name('unit');
            Route::post('unit',                [KoController::class,'simpanUnit'])->name('unit.simpan');
            Route::put('unit/{unit}',          [KoController::class,'ubahUnit'])->name('unit.ubah');
            Route::delete('unit/{unit}',       [KoController::class,'hapusUnit'])->name('unit.hapus');

            /* Uji kelayakan — riwayat, bukan satu tanggal yang ditimpa. */
            Route::get('uji',                  [KoController::class,'uji'])->name('uji');
            Route::post('uji',                 [KoController::class,'simpanUji'])->name('uji.simpan');
            Route::delete('uji/{uji}',         [KoController::class,'hapusUji'])->name('uji.hapus');
            Route::post('uji/{uji}/ajukan',    [KoController::class,'ajukanUji'])->name('uji.ajukan');
            Route::post('uji/{uji}/tinjau',    [KoController::class,'tinjauUji'])->name('uji.tinjau');

            Route::get('perawatan',            [KoController::class,'perawatan'])->name('perawatan');
            Route::post('perawatan/{objek}',   [KoController::class,'catatPm'])->name('perawatan.catat');

            Route::get('pengaman',             [KoController::class,'pengaman'])->name('pengaman');
            Route::post('pengaman/{objek}',    [KoController::class,'simpanPengaman'])->name('pengaman.simpan');
            Route::delete('pengaman/{objek}/{pengaman}', [KoController::class,'hapusPengaman'])->name('pengaman.hapus');

            Route::get('kajian',               [KoController::class,'kajian'])->name('kajian');
            Route::post('kajian',              [KoController::class,'simpanKajian'])->name('kajian.simpan');
            Route::delete('kajian/{kajian}',   [KoController::class,'hapusKajian'])->name('kajian.hapus');

            Route::get('tenaga',               [KoController::class,'tenaga'])->name('tenaga');
            Route::post('tenaga',              [KoController::class,'simpanTenaga'])->name('tenaga.simpan');
            Route::delete('tenaga/{tenaga}',   [KoController::class,'hapusTenaga'])->name('tenaga.hapus');

            Route::get('tindak',               [KoController::class,'tindak'])->name('tindak');
            Route::post('tindak/tarik',        [KoController::class,'tarikPeringatan'])->name('tindak.tarik');
            Route::post('tindak',              [KoController::class,'simpanTindak'])->name('tindak.simpan');
            Route::delete('tindak/{tindak}',   [KoController::class,'hapusTindak'])->name('tindak.hapus');

            Route::get('pengaturan',           [KoController::class,'pengaturan'])->name('pengaturan');
            Route::post('pengaturan',          [KoController::class,'simpanPengaturan'])->name('pengaturan.simpan');
        });

    Route::prefix('tpkkp')->name('tpkkp.')->group(function () {
        Route::get('/',           [TpkkpController::class,'index'])->name('index');
        Route::get('profil',      [TpkkpController::class,'profile'])->name('profile');
        Route::post('profil',     [TpkkpController::class,'saveProfile'])->name('profile.save');
        Route::get('penilaian',   [TpkkpController::class,'assess'])->name('assess');
        Route::post('penilaian',  [TpkkpController::class,'saveAssess'])->name('assess.save');
        Route::get('rekap',       [TpkkpController::class,'rekap'])->name('rekap');
        Route::get('visual',      [TpkkpController::class,'visual'])->name('visual');
        Route::get('program',     [TpkkpController::class,'program'])->name('program');
        Route::get('sampling',    [TpkkpController::class,'sampling'])->name('sampling');
        Route::post('sampling',   [TpkkpController::class,'saveSampling'])->name('sampling.save');
        Route::post('program',           [TpkkpController::class,'storeProgram'])->name('program.store');
        Route::put('program/{id}/status', [TpkkpController::class,'updateProgramStatus'])->name('program.status');
        Route::delete('program/{id}',    [TpkkpController::class,'destroyProgram'])->name('program.destroy');
        Route::get('kuesioner',          [KuesionerController::class,'admin'])->name('kuesioner');
        Route::get('pengujian',          [PengujianController::class,'admin'])->name('pengujian');
        Route::get('metode',      [TpkkpController::class,'metode'])->name('metode');
        Route::get('tentang',     [TpkkpController::class,'tentang'])->name('tentang');

        /* --- halaman lanjutan (paket 2) --- */
        Route::get('matriks',  [TpkkpLanjutController::class,'matriks'])->name('matriks');
        Route::get('summary',  [TpkkpLanjutController::class,'summary'])->name('summary');
        Route::get('hasil',    [TpkkpLanjutController::class,'hasil'])->name('hasil');
        Route::get('rubrik',   [TpkkpLanjutController::class,'rubrik'])->name('rubrik');
        Route::get('jadwal',   [TpkkpLanjutController::class,'jadwal'])->name('jadwal');
        Route::post('jadwal',  [TpkkpLanjutController::class,'saveJadwal'])->name('jadwal.save');
        Route::get('sampel',   [TpkkpLanjutController::class,'sampel'])->name('sampel');
        /* --- paket 4 --- */
        Route::get('roster',       [TpkkpLanjutController::class,'roster'])->name('roster');
        Route::post('roster',      [TpkkpLanjutController::class,'saveRoster'])->name('roster.save');
        Route::get('data',         [TpkkpLanjutController::class,'data'])->name('data');
        Route::get('data/ekspor',  [TpkkpLanjutController::class,'ekspor'])->name('data.ekspor');
        Route::post('data/impor',  [TpkkpLanjutController::class,'impor'])->name('data.impor');
        Route::post('data/reset',  [TpkkpLanjutController::class,'reset'])->name('data.reset');
    });

    Route::post('kuesioner/token',           [KuesionerController::class,'resetToken'])->name('kuesioner.token.reset');
    Route::post('kuesioner/tarik',           [KuesionerController::class,'tarikKs'])->name('kuesioner.tarik');
    Route::delete('kuesioner/{response}',    [KuesionerController::class,'destroyResponse'])->middleware('can:admin')->name('kuesioner.response.destroy');
    Route::get('kuesioner',                  [KuesionerController::class,'admin'])->name('kuesioner.admin');

    Route::get('pengujian',                  [PengujianController::class,'admin'])->name('pengujian.admin');
    Route::post('pengujian/terapkan',        [PengujianController::class,'terapkan'])->name('pengujian.terapkan');
    Route::delete('pengujian/{peserta}',     [PengujianController::class,'destroyPeserta'])->middleware('can:admin')->name('pengujian.peserta.destroy');

    /* ================= WEBSITE #5 — ISO & Dokumen ================= */
    Route::prefix('dokumen')->name('dokumen.')->group(function () {
        Route::get('/',                [DocumentController::class,'index'])->name('index');
        Route::get('baru',             [DocumentController::class,'create'])->name('create');
        Route::post('/',               [DocumentController::class,'store'])->name('store');
        Route::get('{dokumen}',        [DocumentController::class,'show'])->name('show');
        Route::get('{dokumen}/ubah',   [DocumentController::class,'edit'])->name('edit');
        Route::put('{dokumen}',        [DocumentController::class,'update'])->name('update');
        Route::delete('{dokumen}',     [DocumentController::class,'destroy'])->middleware('can:admin')->name('destroy');
        Route::post('{dokumen}/revisi',[DocumentController::class,'revisi'])->name('revisi');
        Route::get('{dokumen}/unduh',  [DocumentController::class,'unduh'])->name('unduh');
    });

    // Struktur dokumen — ditaruh di luar prefix agar tidak tertangkap {dokumen}.
    Route::get('struktur-dokumen', [DocumentController::class,'piramida'])->name('dokumen.piramida');
    Route::get('daftar-induk',     [DocumentController::class,'daftarInduk'])->name('dokumen.daftar-induk');

    /* ================= WEBSITE #7 — Mining Engineering Hub =================
       Halaman acuan rekayasa. Alamatnya dipertahankan seperti saat masih
       berupa berkas statis supaya tautan yang sudah beredar tetap sampai. */
    Route::prefix('mining-engineering-hub')->name('meh.')->group(function () {
        Route::get('/',            [EngineeringController::class,'index'])->name('index');
        Route::get('monitor',      [EngineeringController::class,'monitor'])->name('monitor');
        Route::get('energy',       [EngineeringController::class,'energy'])->name('energy');
        Route::get('fleet',        [EngineeringController::class,'fleet'])->name('fleet');
        Route::get('equipment',    [EngineeringController::class,'equipment'])->name('equipment');
        Route::get('maintenance',  [EngineeringController::class,'maintenance'])->name('maintenance');
        Route::get('hse',          [EngineeringController::class,'hse'])->name('hse');
        Route::get('kpi',          [EngineeringController::class,'kpi'])->name('kpi');
        Route::get('tools',        [EngineeringController::class,'tools'])->name('tools');
        Route::get('regulations',  [EngineeringController::class,'regulations'])->name('regulations');
    });

    /* ================= WEBSITE #6 — Energy Performance Center ================= */
    Route::prefix('energi')->name('energi.')->group(function () {
        Route::get('/',            [EnergyController::class,'index'])->name('index');
        Route::get('input',        [EnergyController::class,'input'])->name('input');
        Route::post('input/produksi', [EnergyController::class,'simpanProduksi'])->name('input.production');
        Route::post('input/fuel', [EnergyController::class,'simpanFuel'])->name('input.fuel');
        Route::post('input/listrik', [EnergyController::class,'simpanListrik'])->name('input.power');
        Route::post('input/rekonsiliasi', [EnergyController::class,'simpanRekonsiliasi'])->name('input.recon');
        Route::get('konsumsi',     [EnergyController::class,'konsumsi'])->name('konsumsi');
        Route::get('bahan-bakar',  [EnergyController::class,'fuel'])->name('fuel');
        Route::get('listrik',      [EnergyController::class,'listrik'])->name('listrik');

        Route::get('alat',         [EnergyController::class,'equipment'])->name('equipment');
        Route::get('alat/{unit}',  [EnergyController::class,'equipmentShow'])->name('equipment.show');

        Route::get('kpi',          [EnergyController::class,'kpi'])->name('kpi');
        Route::get('baseline',     [EnergyController::class,'baseline'])->name('baseline');
        Route::post('baseline',    [EnergyController::class,'simpanBaseline'])->name('baseline.simpan');

        Route::get('penghematan',  [EnergyController::class,'hemat'])->name('hemat');
        Route::post('penghematan', [EnergyController::class,'simpanPeluang'])->name('hemat.simpan');
        Route::put('penghematan/{peluang}',   [EnergyController::class,'ubahPeluang'])->name('hemat.ubah');
        Route::delete('penghematan/{peluang}',[EnergyController::class,'hapusPeluang'])->middleware('can:admin')->name('hemat.hapus');

        Route::get('karbon',       [EnergyController::class,'karbon'])->name('karbon');
        Route::get('kalkulator',   [EnergyController::class,'kalkulator'])->name('kalkulator');
        Route::get('laporan',      [EnergyController::class,'laporan'])->name('laporan');

        Route::get('data-induk',   [EnergyController::class,'master'])->name('master');
        Route::post('data-induk',  [EnergyController::class,'simpanUnit'])->name('master.simpan');
        Route::delete('data-induk/{unit}', [EnergyController::class,'hapusUnit'])->middleware('can:admin')->name('master.hapus');
    });

    /* ============ Pengelolaan Air & Penirisan ============
       Pompanya memakai registri Keselamatan Operasi; yang didaftarkan
       di sini adalah kolam beserta daerah tangkapan airnya. */
    Route::prefix('penirisan')->name('air.')->group(function () {
        Route::get('/',        [WaterController::class, 'index'])->name('index');
        Route::get('catatan',  [WaterController::class, 'catatan'])->name('catatan');
        Route::get('kolam',    [WaterController::class, 'kolam'])->name('kolam');
        Route::get('cetak',    [WaterController::class, 'cetak'])->name('cetak');

        Route::post('kolam',            [WaterController::class, 'simpanKolam'])->name('kolam.simpan');
        Route::delete('kolam/{sump}',   [WaterController::class, 'hapusKolam'])->middleware('can:admin')->name('kolam.hapus');
        Route::post('kolam/{sump}/pompa', [WaterController::class, 'simpanPompa'])->name('pompa.simpan');
        Route::put('pompa/{pompa}',     [WaterController::class, 'ubahPompa'])->name('pompa.ubah');

        Route::post('catatan',              [WaterController::class, 'simpanCatatan'])->name('catatan.simpan');
        Route::delete('catatan/{catatan}',  [WaterController::class, 'hapusCatatan'])->middleware('can:admin')->name('catatan.hapus');
        Route::post('catatan/{catatan}/ajukan',  [WaterController::class, 'ajukan'])->name('ajukan');
        Route::post('catatan/{catatan}/setujui', [WaterController::class, 'setujui'])->name('setujui');
        Route::post('catatan/{catatan}/tolak',   [WaterController::class, 'tolak'])->name('tolak');

        Route::post('tindak',         [WaterController::class, 'simpanTindakLanjut'])->name('tindak.simpan');
        Route::put('tindak/{tindak}', [WaterController::class, 'ubahTindakLanjut'])->name('tindak.ubah');
    });

    /* ============ Pengelolaan Lingkungan & Reklamasi ============
       Tahapan petak berpindah hanya lewat laporan kemajuan yang
       disetujui; baku mutunya berupa data, bukan tetapan di dalam kode. */
    Route::prefix('lingkungan')->name('lingkungan.')->group(function () {
        Route::get('/',           [EnvironmentController::class, 'index'])->name('index');
        Route::get('lahan',       [EnvironmentController::class, 'lahan'])->name('lahan');
        Route::get('pemantauan',  [EnvironmentController::class, 'pemantauan'])->name('pemantauan');
        Route::get('baku-mutu',   [EnvironmentController::class, 'baku'])->name('baku');
        Route::get('cetak',       [EnvironmentController::class, 'cetak'])->name('cetak');

        Route::post('area',          [EnvironmentController::class, 'simpanArea'])->name('area.simpan');
        Route::put('area/{area}',    [EnvironmentController::class, 'ubahArea'])->name('area.ubah');
        Route::delete('area/{area}', [EnvironmentController::class, 'hapusArea'])->middleware('can:admin')->name('area.hapus');

        Route::post('kemajuan',              [EnvironmentController::class, 'simpanKemajuan'])->name('kemajuan.simpan');
        Route::delete('kemajuan/{kemajuan}', [EnvironmentController::class, 'hapusKemajuan'])->middleware('can:admin')->name('kemajuan.hapus');
        Route::post('kemajuan/{kemajuan}/ajukan',  [EnvironmentController::class, 'ajukanKemajuan'])->name('kemajuan.ajukan');
        Route::post('kemajuan/{kemajuan}/setujui', [EnvironmentController::class, 'setujuiKemajuan'])->name('kemajuan.setujui');
        Route::post('kemajuan/{kemajuan}/tolak',   [EnvironmentController::class, 'tolakKemajuan'])->name('kemajuan.tolak');

        Route::post('baku-mutu',               [EnvironmentController::class, 'simpanParameter'])->name('parameter.simpan');
        Route::delete('baku-mutu/{parameter}', [EnvironmentController::class, 'hapusParameter'])->middleware('can:admin')->name('parameter.hapus');

        Route::post('pantau',            [EnvironmentController::class, 'simpanPantau'])->name('pantau.simpan');
        Route::delete('pantau/{pantau}', [EnvironmentController::class, 'hapusPantau'])->middleware('can:admin')->name('pantau.hapus');
        Route::post('pantau/{pantau}/ajukan',  [EnvironmentController::class, 'ajukanPantau'])->name('pantau.ajukan');
        Route::post('pantau/{pantau}/setujui', [EnvironmentController::class, 'setujuiPantau'])->name('pantau.setujui');
        Route::post('pantau/{pantau}/tolak',   [EnvironmentController::class, 'tolakPantau'])->name('pantau.tolak');

        Route::post('tindak',         [EnvironmentController::class, 'simpanTindakLanjut'])->name('tindak.simpan');
        Route::put('tindak/{tindak}', [EnvironmentController::class, 'ubahTindakLanjut'])->name('tindak.ubah');
    });

    /* ============ Pengeboran & Peledakan ============
       Satu-satunya alur yang persetujuannya mendahului pekerjaannya:
       rencana yang disetujui berarti boleh diledakkan, dan hasil hanya
       dapat dicatat pada rencana yang izinnya sudah keluar. */
    Route::prefix('peledakan')->name('peledakan.')->group(function () {
        Route::get('/',        [BlastingController::class, 'index'])->name('index');
        Route::get('rencana',  [BlastingController::class, 'rencana'])->name('rencana');
        Route::get('titik',    [BlastingController::class, 'titik'])->name('titik');
        Route::get('getaran',  [BlastingController::class, 'getaran'])->name('getaran');
        Route::get('cetak',    [BlastingController::class, 'cetak'])->name('cetak');

        Route::post('titik',           [BlastingController::class, 'simpanTitik'])->name('titik.simpan');
        Route::delete('titik/{titik}', [BlastingController::class, 'hapusTitik'])->middleware('can:admin')->name('titik.hapus');

        Route::post('rencana',             [BlastingController::class, 'simpanRencana'])->name('rencana.simpan');
        Route::delete('rencana/{rencana}', [BlastingController::class, 'hapusRencana'])->middleware('can:admin')->name('rencana.hapus');
        Route::post('rencana/{rencana}/ajukan',  [BlastingController::class, 'ajukanRencana'])->name('rencana.ajukan');
        Route::post('rencana/{rencana}/setujui', [BlastingController::class, 'setujuiRencana'])->name('rencana.setujui');
        Route::post('rencana/{rencana}/tolak',   [BlastingController::class, 'tolakRencana'])->name('rencana.tolak');

        Route::post('rencana/{rencana}/hasil', [BlastingController::class, 'simpanHasil'])->name('hasil.simpan');
        Route::post('hasil/{hasil}/ajukan',    [BlastingController::class, 'ajukanHasil'])->name('hasil.ajukan');
        Route::post('hasil/{hasil}/setujui',   [BlastingController::class, 'setujuiHasil'])->name('hasil.setujui');
        Route::post('hasil/{hasil}/tolak',     [BlastingController::class, 'tolakHasil'])->name('hasil.tolak');

        Route::post('rencana/{rencana}/ukur', [BlastingController::class, 'simpanUkur'])->name('ukur.simpan');
        Route::delete('ukur/{ukur}',          [BlastingController::class, 'hapusUkur'])->middleware('can:admin')->name('ukur.hapus');

        Route::post('tindak',         [BlastingController::class, 'simpanTindakLanjut'])->name('tindak.simpan');
        Route::put('tindak/{tindak}', [BlastingController::class, 'ubahTindakLanjut'])->name('tindak.ubah');
    });

    /* ============ Dispatch & Pengangkutan ============
       Mencatat bagaimana tonase terangkut, bukan berapa — tonase di sini
       adalah bagian dari tonase pit pada Mine Operations, bukan
       tambahannya. Penimbangan sengaja berdiri di luar alur tinjauan:
       ia pembacaan alat, bukan pendapat. */
    Route::prefix('angkutan')->name('angkutan.')->group(function () {
        Route::get('/',       [DispatchController::class, 'index'])->name('index');
        Route::get('regu',    [DispatchController::class, 'regu'])->name('regu');
        Route::get('armada',  [DispatchController::class, 'armada'])->name('armada');
        Route::get('muatan',  [DispatchController::class, 'muatan'])->name('muatan');
        Route::get('cetak',   [DispatchController::class, 'cetak'])->name('cetak');

        Route::post('armada',         [DispatchController::class, 'simpanAlat'])->name('alat.simpan');
        Route::delete('armada/{alat}', [DispatchController::class, 'hapusAlat'])->middleware('can:admin')->name('alat.hapus');

        Route::post('regu',           [DispatchController::class, 'simpanRegu'])->name('regu.simpan');
        Route::delete('regu/{regu}',  [DispatchController::class, 'hapusRegu'])->middleware('can:admin')->name('regu.hapus');
        Route::post('regu/{regu}/ajukan',  [DispatchController::class, 'ajukan'])->name('ajukan');
        Route::post('regu/{regu}/setujui', [DispatchController::class, 'setujui'])->name('setujui');
        Route::post('regu/{regu}/tolak',   [DispatchController::class, 'tolak'])->name('tolak');

        Route::post('regu/{regu}/muatan',  [DispatchController::class, 'simpanMuatan'])->name('muatan.simpan');
        Route::delete('muatan/{muatan}',   [DispatchController::class, 'hapusMuatan'])->middleware('can:admin')->name('muatan.hapus');

        Route::post('tindak',         [DispatchController::class, 'simpanTindakLanjut'])->name('tindak.simpan');
        Route::put('tindak/{tindak}', [DispatchController::class, 'ubahTindakLanjut'])->name('tindak.ubah');
    });

    /* ============ Pengendalian Biaya Operasi ============
       Akuntansi manajemen untuk mengendalikan operasi, bukan pembukuan.
       Denominator produksinya diambil dari Mine Operations yang sudah
       disetujui — tonase tidak pernah diketik ulang di sini. */
    Route::prefix('biaya')->name('biaya.')->group(function () {
        Route::get('/',          [CostController::class, 'index'])->name('index');
        Route::get('realisasi',  [CostController::class, 'realisasi'])->name('realisasi');
        Route::get('anggaran',   [CostController::class, 'anggaran'])->name('anggaran');
        Route::get('bagan-akun', [CostController::class, 'akun'])->name('akun');
        Route::get('cetak',      [CostController::class, 'cetak'])->name('cetak');

        Route::post('bagan-akun',        [CostController::class, 'simpanAkun'])->name('akun.simpan');
        Route::delete('bagan-akun/{akun}', [CostController::class, 'hapusAkun'])->middleware('can:admin')->name('akun.hapus');

        Route::post('anggaran',              [CostController::class, 'simpanAnggaran'])->name('anggaran.simpan');
        Route::delete('anggaran/{anggaran}', [CostController::class, 'hapusAnggaran'])->middleware('can:admin')->name('anggaran.hapus');

        Route::post('realisasi',               [CostController::class, 'simpanRealisasi'])->name('realisasi.simpan');
        Route::delete('realisasi/{realisasi}', [CostController::class, 'hapusRealisasi'])->middleware('can:admin')->name('realisasi.hapus');
        Route::post('realisasi/{realisasi}/ajukan',  [CostController::class, 'ajukan'])->name('ajukan');
        Route::post('realisasi/{realisasi}/setujui', [CostController::class, 'setujui'])->name('setujui');
        Route::post('realisasi/{realisasi}/tolak',   [CostController::class, 'tolak'])->name('tolak');

        Route::post('tindak',         [CostController::class, 'simpanTindakLanjut'])->name('tindak.simpan');
        Route::put('tindak/{tindak}', [CostController::class, 'ubahTindakLanjut'])->name('tindak.ubah');
    });

    /* ============ Izin Kerja Aman ============
       Persetujuan di sini ADALAH izinnya, dan izinnya kedaluwarsa.
       Syarat wajib yang belum terpenuhi serta uji gas yang basi
       MENGHALANGI penerbitan, bukan sekadar memperingatkan. */
    Route::prefix('izin-kerja')->name('izin.')->group(function () {
        Route::get('/',        [PermitController::class, 'index'])->name('index');
        Route::get('daftar',   [PermitController::class, 'daftar'])->name('daftar');
        Route::get('syarat',   [PermitController::class, 'syarat'])->name('syarat');
        Route::get('ambang-gas', [PermitController::class, 'ambang'])->name('ambang');
        Route::get('cetak',    [PermitController::class, 'cetak'])->name('cetak');

        Route::post('syarat',            [PermitController::class, 'simpanSyarat'])->name('syarat.simpan');
        Route::delete('syarat/{syarat}', [PermitController::class, 'hapusSyarat'])->middleware('can:admin')->name('syarat.hapus');

        Route::post('ambang-gas',            [PermitController::class, 'simpanAmbang'])->name('ambang.simpan');
        Route::delete('ambang-gas/{ambang}', [PermitController::class, 'hapusAmbang'])->middleware('can:admin')->name('ambang.hapus');

        Route::post('/',           [PermitController::class, 'simpanIzin'])->name('simpan');
        Route::delete('{izin}',    [PermitController::class, 'hapusIzin'])->middleware('can:admin')->name('hapus');
        Route::post('{izin}/ajukan',    [PermitController::class, 'ajukan'])->name('ajukan');
        Route::post('{izin}/terbitkan', [PermitController::class, 'terbitkan'])->name('terbitkan');
        Route::post('{izin}/tolak',     [PermitController::class, 'tolak'])->name('tolak');
        Route::post('{izin}/tutup',     [PermitController::class, 'tutup'])->name('tutup');

        Route::put('periksa/{periksa}', [PermitController::class, 'ubahPeriksa'])->name('periksa.ubah');
        Route::post('{izin}/gas',       [PermitController::class, 'simpanGas'])->name('gas.simpan');
        Route::delete('gas/{gas}',      [PermitController::class, 'hapusGas'])->middleware('can:admin')->name('gas.hapus');

        Route::post('tindak',         [PermitController::class, 'simpanTindakLanjut'])->name('tindak.simpan');
        Route::put('tindak/{tindak}', [PermitController::class, 'ubahTindakLanjut'])->name('tindak.ubah');
    });

    /* ============ Pemantauan Kestabilan Lereng ============
       Alat bantu keputusan, bukan pengganti penilaian geoteknik oleh
       tenaga kompeten: yang dicatat pengamatan lapangan dan acuan dari
       kajian yang sudah ada, bukan kesimpulan tentang kestabilannya. */
    Route::prefix('geoteknik')->name('geoteknik.')->group(function () {
        Route::get('/',       [GeotechnicalController::class, 'index'])->name('index');
        Route::get('bacaan',  [GeotechnicalController::class, 'bacaan'])->name('bacaan');
        Route::get('lereng',  [GeotechnicalController::class, 'lereng'])->name('lereng');
        Route::get('cetak',   [GeotechnicalController::class, 'cetak'])->name('cetak');

        Route::post('lereng',           [GeotechnicalController::class, 'simpanLereng'])->name('lereng.simpan');
        Route::put('lereng/{lereng}',   [GeotechnicalController::class, 'ubahLereng'])->name('lereng.ubah');
        Route::delete('lereng/{lereng}', [GeotechnicalController::class, 'hapusLereng'])->middleware('can:admin')->name('lereng.hapus');

        Route::post('lereng/{lereng}/instrumen', [GeotechnicalController::class, 'simpanInstrumen'])->name('instrumen.simpan');
        Route::put('instrumen/{instrumen}',      [GeotechnicalController::class, 'ubahInstrumen'])->name('instrumen.ubah');

        Route::post('bacaan',             [GeotechnicalController::class, 'simpanBacaan'])->name('bacaan.simpan');
        Route::delete('bacaan/{bacaan}',  [GeotechnicalController::class, 'hapusBacaan'])->middleware('can:admin')->name('bacaan.hapus');
        Route::post('bacaan/{bacaan}/ajukan',  [GeotechnicalController::class, 'ajukan'])->name('ajukan');
        Route::post('bacaan/{bacaan}/setujui', [GeotechnicalController::class, 'setujui'])->name('setujui');
        Route::post('bacaan/{bacaan}/tolak',   [GeotechnicalController::class, 'tolak'])->name('tolak');

        Route::post('tindak',         [GeotechnicalController::class, 'simpanTindakLanjut'])->name('tindak.simpan');
        Route::put('tindak/{tindak}', [GeotechnicalController::class, 'ubahTindakLanjut'])->name('tindak.ubah');
    });

    /* ============ Pusat Pemeliharaan & Keandalan ============
       Alatnya memakai registri Keselamatan Operasi; yang ditambahkan di
       sini adalah catatan gangguan dan perbaikannya. */
    Route::prefix('pemeliharaan')->name('maintenance.')->group(function () {
        Route::get('/',       [MaintenanceController::class, 'index'])->name('index');
        Route::get('order',   [MaintenanceController::class, 'order'])->name('order');
        Route::get('armada',  [MaintenanceController::class, 'armada'])->name('armada');

        Route::post('order',                 [MaintenanceController::class, 'simpan'])->name('simpan');
        Route::put('order/{order}/status',   [MaintenanceController::class, 'ubahStatus'])->name('status');
        Route::post('order/{order}/part',    [MaintenanceController::class, 'simpanPart'])->name('part');
        Route::delete('order/{order}',       [MaintenanceController::class, 'hapus'])->middleware('can:admin')->name('hapus');

        /* Verifikasi penutupan. Hak diperiksa di dalam model supaya
           aturan "penutup bukan pemverifikasi" berlaku juga bagi
           pemanggil selain rute ini. */
        Route::post('order/{order}/verifikasi',       [MaintenanceController::class, 'verifikasi'])->name('verifikasi');
        Route::post('order/{order}/batal-verifikasi', [MaintenanceController::class, 'batalVerifikasi'])->middleware('can:admin')->name('batalVerifikasi');

        Route::post('tindak',         [MaintenanceController::class, 'simpanTindakLanjut'])->name('tindak.simpan');
        Route::put('tindak/{tindak}', [MaintenanceController::class, 'ubahTindakLanjut'])->name('tindak.ubah');

        Route::get('cetak', [MaintenanceController::class, 'cetak'])->name('cetak');
    });

    /* ================= WEBSITE #8 - Konservasi Minerba ================= */
    Route::prefix('konservasi')->name('konservasi.')->group(function () {
        Route::get('/', [KonservasiController::class, 'index'])->name('index');
        Route::get('data', [KonservasiController::class, 'data'])->name('data');
        Route::get('laporan', [KonservasiController::class, 'laporan'])->name('laporan');
        Route::get('cetak',   [KonservasiController::class, 'cetak'])->name('cetak');

        Route::post('records', [KonservasiController::class, 'simpanRecord'])->name('record.simpan');
        Route::put('records/{record}', [KonservasiController::class, 'ubahRecord'])->name('record.ubah');
        Route::delete('records/{record}', [KonservasiController::class, 'hapusRecord'])->middleware('can:admin')->name('record.hapus');

        /* Alur tinjauan. Hak meninjau diperiksa di dalam model — bukan di
           sini — supaya aturan "pengaju bukan peninjau" berlaku juga bagi
           pemanggil selain rute ini, seperti perintah artisan dan antrean. */
        Route::post('records/{record}/ajukan',  [KonservasiController::class, 'ajukanRecord'])->name('record.ajukan');
        Route::post('records/{record}/setujui', [KonservasiController::class, 'setujuiRecord'])->name('record.setujui');
        Route::post('records/{record}/tolak',   [KonservasiController::class, 'tolakRecord'])->name('record.tolak');

        Route::post('actions', [KonservasiController::class, 'simpanAction'])->name('action.simpan');
        Route::put('actions/{action}', [KonservasiController::class, 'ubahAction'])->name('action.ubah');
        Route::delete('actions/{action}', [KonservasiController::class, 'hapusAction'])->middleware('can:admin')->name('action.hapus');
    });

    /* ================= WEBSITE #9 — Operasi Tambang & peta GIS ================= */
    Route::prefix('operasi-tambang')->name('operasi.')->group(function () {
        Route::get('/', [MineOperationsController::class, 'index'])->name('index');
        Route::get('data', [MineOperationsController::class, 'data'])->name('data');
        Route::get('target', [MineOperationsController::class, 'target'])->name('target');
        Route::get('gis', [MineOperationsController::class, 'gis'])->name('gis');
        Route::get('cetak', [MineOperationsController::class, 'cetak'])->name('cetak');
        Route::post('records', [MineOperationsController::class, 'simpanRecord'])->name('record.simpan');
        Route::delete('records/{record}', [MineOperationsController::class, 'hapusRecord'])->middleware('can:admin')->name('record.hapus');

        Route::post('records/{record}/ajukan',  [MineOperationsController::class, 'ajukanRecord'])->name('record.ajukan');
        Route::post('records/{record}/setujui', [MineOperationsController::class, 'setujuiRecord'])->name('record.setujui');
        Route::post('records/{record}/tolak',   [MineOperationsController::class, 'tolakRecord'])->name('record.tolak');

        /* Tindak lanjut. Tabelnya dipakai bersama seluruh modul; yang
           membedakan hanya saringan 'modul' di dalam controller. */
        Route::post('tindak',            [MineOperationsController::class, 'simpanTindakLanjut'])->name('tindak.simpan');
        Route::put('tindak/{tindak}',    [MineOperationsController::class, 'ubahTindakLanjut'])->name('tindak.ubah');
        Route::delete('tindak/{tindak}', [MineOperationsController::class, 'hapusTindakLanjut'])->middleware('can:admin')->name('tindak.hapus');
        Route::post('targets', [MineOperationsController::class, 'simpanTarget'])->name('target.simpan');
        Route::post('layers', [MineOperationsController::class, 'simpanLayer'])->name('layer.simpan');
        Route::delete('layers/{layer}', [MineOperationsController::class, 'hapusLayer'])->middleware('can:admin')->name('layer.hapus');
    });

    /* ================= WEBSITE #5b — ISO: pemenuhan klausul ================= */
    Route::prefix('iso')->name('iso.')->group(function () {
        Route::get('/',                 [IsoController::class,'index'])->name('index');
        Route::get('{standar}',         [IsoController::class,'show'])->name('show');
        Route::get('{standar}/cetak',   [IsoController::class,'cetak'])->name('cetak');
    });

    /* ================= WEBSITE #4 — Audit SMKP Minerba ================= */
    Route::prefix('smkp')->name('smkp.')->group(function () {
        /* Dasbor dulu, daftar periode kemudian. Yang dibuka manajemen
           bukan "audit mana yang ada" melainkan "apakah kami membaik" —
           dan pertanyaan kedua tidak terjawab oleh daftar. */
        Route::get('dasbor',           [SmkpController::class,'dasbor'])->name('dasbor');
        Route::get('/',                [SmkpController::class,'index'])->name('index');
        Route::get('buat',             [SmkpController::class,'create'])->name('create');
        Route::post('/',               [SmkpController::class,'store'])->name('store');
        Route::get('acuan',            [SmkpController::class,'acuan'])->name('acuan');

        // Bunyi rubrik butir — teks peraturan, diambil terpisah dari halaman
        // penilaian supaya 260 ribu aksara tidak ikut tiap kali dibuka.
        Route::get('rubrik',           [SmkpController::class,'rubrik'])->name('rubrik');

        // Pintasan menu samping: tanpa parameter, disalurkan ke audit berjalan.
        foreach ([
            'tahap1' => 'tahap-1', 'rencana' => 'rencana', 'penilaian' => 'penilaian',
            'rapat' => 'rapat', 'temuan' => 'temuan',
            'berita' => 'berita-acara', 'rencana-cetak' => 'laporan-rencana', 'laporan' => 'laporan-audit',

            /* Lima keluaran audit yang menyusul. */
            'kriteria' => 'kriteria', 'rekap-nc' => 'rekap-nc', 'respon' => 'respon',
            'rencana-tindak' => 'rencana-tindak', 'nc-tindak' => 'nc-tindak',
            'ofi' => 'ofi',
        ] as $bagian => $ruas) {
            Route::get("lanjut/{$ruas}", [SmkpController::class,'lanjut'])
                ->defaults('bagian', $bagian)->name('ke.'.$bagian);
        }

        Route::get('{smkp}',           [SmkpController::class,'show'])->name('show');
        Route::get('{smkp}/ubah',      [SmkpController::class,'edit'])->name('edit');
        Route::put('{smkp}',           [SmkpController::class,'update'])->name('update');
        Route::delete('{smkp}',        [SmkpController::class,'destroy'])->middleware('can:admin')->name('destroy');

        Route::get('{smkp}/laporan',   [SmkpController::class,'laporan'])->name('laporan');

        /* Lima keluaran audit yang sebelumnya belum ada, plus ekspor
           formulir kriteria ke CSV. Nomornya mengikuti urutan berkas
           audit, bukan urutan pembuatannya di sini. */
        Route::get('{smkp}/kriteria',        [SmkpController::class,'kriteria'])->name('kriteria');
        Route::get('{smkp}/kriteria/ekspor', [SmkpController::class,'kriteriaEkspor'])->name('kriteria.ekspor');
        Route::get('{smkp}/rekap-nc',        [SmkpController::class,'rekapNc'])->name('rekapNc');
        Route::get('{smkp}/respon',          [SmkpController::class,'responManajemen'])->name('respon');
        Route::get('{smkp}/rencana-tindak',  [SmkpController::class,'rencanaTindak'])->name('rencanaTindak');
        Route::get('{smkp}/nc-tindak',       [SmkpController::class,'ncTindak'])->name('ncTindak');
        Route::put('{smkp}/temuan/{temuan}/respon',
            [SmkpController::class,'simpanRespon'])->name('temuan.respon');
        Route::post('{smkp}/tahap',    [SmkpController::class,'ubahTahap'])->name('tahap');

        // Tahap I — permulaan audit, peninjauan dokumen, persiapan lapangan
        Route::get('{smkp}/tahap-1',       [SmkpController::class,'tahap1'])->name('tahap1');
        Route::post('{smkp}/tahap-1',      [SmkpController::class,'simpanTahap1'])->name('tahap1.simpan');

        /* Hitung ulang hari kerja audit tanpa menyimpan apa pun, supaya
           kartunya mengikuti isian yang sedang diketik. Rumusnya satu dan
           tetap di server; yang dikirim ke sini hanya isiannya. */
        Route::post('{smkp}/tahap-1/mandays', [SmkpController::class,'hitungMandays'])->name('tahap1.mandays');
        Route::get('{smkp}/berita-acara',  [SmkpController::class,'beritaAcara'])->name('berita-acara');

        // Rencana Audit — sembilan komponen wajib, plus laporannya
        Route::get('{smkp}/rencana',       [SmkpController::class,'rencana'])->name('rencana');
        Route::post('{smkp}/rencana',      [SmkpController::class,'simpanRencana'])->name('rencana.simpan');
        Route::get('{smkp}/rencana/cetak', [SmkpController::class,'rencanaCetak'])->name('rencana.cetak');

        // Tahap II — rapat pembukaan & penutupan
        Route::get('{smkp}/rapat',                [SmkpController::class,'rapat'])->name('rapat');
        Route::post('{smkp}/rapat',               [SmkpController::class,'simpanHadir'])->name('rapat.simpan');
        Route::delete('{smkp}/rapat/{hadir}',     [SmkpController::class,'hapusHadir'])->name('rapat.hapus');
        Route::get('{smkp}/daftar-hadir/{rapat}', [SmkpController::class,'daftarHadir'])->name('hadir.cetak');

        // Temuan / tindakan perbaikan
        Route::get('{smkp}/temuan',            [SmkpController::class,'temuan'])->name('temuan');
        Route::post('{smkp}/temuan/angkat',    [SmkpController::class,'angkatTemuan'])->name('temuan.angkat');
        Route::put('{smkp}/temuan/{temuan}',   [SmkpController::class,'simpanTemuan'])->name('temuan.simpan');
        Route::delete('{smkp}/temuan/{temuan}',[SmkpController::class,'hapusTemuan'])->name('temuan.hapus');

        // Form Penilaian Audit — tujuh elemen sekaligus, untuk memeriksa
        // kesesuaian tiap parameter tanpa berpindah halaman per elemen.
        Route::get('{smkp}/penilaian',  [SmkpController::class,'penilaian'])->name('penilaian');
        Route::post('{smkp}/penilaian', [SmkpController::class,'simpanPenilaian'])->name('penilaian.simpan');

        /* Berkas bukti per butir kriteria. Batas 10 MB ditegakkan
           App\Support\Berkas::ATURAN_BUKTI, dan berkasnya disimpan pada
           disk tertutup — bukti audit hanya tampil di halaman yang
           menuntut login, jadi menutupnya tidak menghilangkan apa pun. */
        Route::post('{smkp}/bukti',           [SmkpController::class,'buktiUnggah'])->name('bukti.unggah');
        Route::delete('{smkp}/bukti/{bukti}', [SmkpController::class,'buktiHapus'])
            ->middleware('can:admin')->name('bukti.hapus');

        /* Peluang perbaikan atas butir yang capaiannya PENUH. Terpisah
           dari temuan: yang di sini tidak menurunkan nilai apa pun dan
           tidak wajib ditindaklanjuti. */
        Route::get('{smkp}/ofi',            [SmkpController::class,'ofi'])->name('ofi');
        Route::post('{smkp}/ofi',           [SmkpController::class,'ofiSimpan'])->name('ofi.simpan');
        Route::delete('{smkp}/ofi/{ofi}',   [SmkpController::class,'ofiHapus'])
            ->middleware('can:admin')->name('ofi.hapus');
        Route::get('{smkp}/ofi/cetak',      [SmkpController::class,'ofiCetak'])->name('ofi.cetak');
        Route::get('{smkp}/ofi/ekspor',     [SmkpController::class,'ofiEkspor'])->name('ofi.ekspor');

        // Formulir penilaian per elemen — ditaruh terakhir agar tidak menyerobot rute di atas
        Route::get('{smkp}/elemen/{elemen}',  [SmkpController::class,'nilai'])->name('nilai');
        Route::post('{smkp}/elemen/{elemen}', [SmkpController::class,'simpanNilai'])->name('nilai.simpan');
    });

    /* ================= WEBSITE #3 — Hazard Report & Inspeksi ================= */
    Route::prefix('hazard')->name('hazard.')->group(function () {
        Route::get('/',                  [HazardController::class,'index'])->name('index');
        Route::get('buat',               [HazardController::class,'create'])->name('create');
        Route::post('/',                 [HazardController::class,'store'])->name('store');
        Route::get('analitik',           [HazardController::class,'analytics'])->name('analytics');
        Route::get('evaluasi',           [EvaluasiTemuanController::class,'index'])->name('evaluasi');
        Route::get('pengingat',          [HazardExportController::class,'pengingat'])->name('pengingat');
        Route::get('ekspor/csv',         [HazardExportController::class,'hazardCsv'])->name('ekspor.csv');
        Route::get('ekspor/cetak',       [HazardExportController::class,'hazardCetak'])->name('ekspor.cetak');
        Route::get('{hazard}',           [HazardController::class,'show'])->name('show');
        Route::post('{hazard}/tindak',   [HazardController::class,'follow'])->name('follow');
        Route::delete('{hazard}',        [HazardController::class,'destroy'])->middleware('can:admin')->name('destroy');
    });

    Route::prefix('inspeksi')->name('inspeksi.')->group(function () {
        // Jenis inspeksi (template + parameter)
        Route::prefix('jenis')->name('template.')->group(function () {
            Route::get('/',                 [InspectionTemplateController::class,'index'])->name('index');
            Route::get('buat',              [InspectionTemplateController::class,'create'])->name('create');
            Route::post('/',                [InspectionTemplateController::class,'store'])->name('store');
            Route::get('{template}/kelola', [InspectionTemplateController::class,'edit'])->name('edit');
            Route::put('{template}',        [InspectionTemplateController::class,'update'])->name('update');
            Route::delete('{template}',     [InspectionTemplateController::class,'destroy'])->middleware('can:admin')->name('destroy');
            Route::post('{template}/param', [InspectionTemplateController::class,'storeItem'])->name('item.store');
            Route::post('{template}/salin', [InspectionTemplateController::class,'salin'])->name('salin');
        });
        Route::delete('param/{item}', [InspectionTemplateController::class,'destroyItem'])->middleware('can:admin')->name('template.item.destroy');

        // KPI inspeksi
        Route::get('kpi', [InspectionController::class,'kpi'])->name('kpi');
        Route::get('ekspor/csv',   [HazardExportController::class,'inspeksiCsv'])->name('ekspor.csv');
        Route::get('ekspor/cetak', [HazardExportController::class,'inspeksiCetak'])->name('ekspor.cetak');

        // Pelaksanaan
        Route::get('/',                   [InspectionController::class,'index'])->name('index');
        Route::get('buat',                [InspectionController::class,'create'])->name('create');
        Route::post('/',                  [InspectionController::class,'store'])->name('store');
        Route::get('{inspeksi}',          [InspectionController::class,'show'])->name('show');
        Route::get('{inspeksi}/ubah',     [InspectionController::class,'edit'])->name('edit');
        Route::put('{inspeksi}',          [InspectionController::class,'update'])->name('update');
        Route::delete('{inspeksi}',       [InspectionController::class,'destroy'])->middleware('can:admin')->name('destroy');

        // Inspektur
        Route::post('{inspeksi}/inspektur',  [InspectionController::class,'addInspector'])->name('inspector.store');
        Route::delete('inspektur/{inspector}',[InspectionController::class,'removeInspector'])->middleware('can:admin')->name('inspector.destroy');

        // Item pemeriksaan
        Route::post('{inspeksi}/items',   [InspectionController::class,'saveItems'])->name('items.save');
        Route::post('{inspeksi}/item',    [InspectionController::class,'storeItem'])->name('item.store');
        Route::delete('item/{item}',      [InspectionController::class,'destroyItem'])->middleware('can:admin')->name('item.destroy');
        Route::post('item/{item}/angkat', [InspectionController::class,'angkat'])->name('item.angkat');
    });

    /* ---- Panel Admin ---- */
    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
        Route::resource('companies', CompanyController::class)->except(['show']);
        Route::get('system',        [SystemController::class, 'index'])->name('system');
        Route::delete('system/logs',[SystemController::class, 'clearLogs'])->name('system.logs.clear');
        Route::post('system/maintenance/{aksi}', [SystemController::class,'maintenance'])->name('system.maintenance');

        Route::get('system/diagnosa',           [SystemController::class,'diagnosa'])->name('system.diagnosa');

        /* Integrasi AI. Kunci API dimasukkan lewat halaman ini, bukan
           lewat .env di server — yang paling berkepentingan menyalakan
           asisten justru yang paling tidak punya akses SSH. */
        Route::get('ai',            [AiController::class,'index'])->name('ai');
        Route::post('ai',           [AiController::class,'simpan'])->name('ai.simpan');
        Route::post('ai/uji',       [AiController::class,'uji'])->name('ai.uji');
        Route::delete('ai/kunci',   [AiController::class,'hapus'])->name('ai.hapus');
        Route::post('ai/diagnosa',  [AiController::class,'tanyaDiagnosa'])->name('ai.diagnosa');
        Route::post('system/perbaiki/{aksi}',   [SystemController::class,'perbaiki'])->name('system.perbaiki');

        // Data contoh. Penandaan dan pemuatan sengaja dua rute terpisah:
        // menandai perusahaan sebagai perusahaan contoh harus menjadi
        // tindakan tersendiri yang disengaja, bukan efek samping dari
        // menekan tombol muat.
        Route::post('system/demo/{company}/tandai', [SystemController::class,'tandaiContoh'])
            ->name('system.demo.tandai');
        Route::post('system/demo/{company}/muat',   [SystemController::class,'muatContoh'])
            ->name('system.demo.muat');

        /* Terpisah dari muat: yang satu menyegarkan, yang ini
           mengosongkan. Data contoh bertahan sampai ini ditekan. */
        Route::delete('system/demo/{company}',      [SystemController::class,'hapusContoh'])
            ->name('system.demo.hapus');

        /* Kendali keamanan. Terpisah dari Diagnosa: diagnosa menjawab
           "apakah pemasangannya benar", halaman ini menjawab "apa yang
           sedang terjadi padanya sekarang". */
        Route::get('keamanan',              [KeamananController::class,'index'])->name('keamanan');
        Route::delete('keamanan/sesi',      [KeamananController::class,'putusSesi'])->name('keamanan.sesi.putus');
        Route::post('keamanan/sesi/bersih', [KeamananController::class,'bersihSesi'])->name('keamanan.sesi.bersih');
        Route::post('keamanan/jejak/pangkas',[KeamananController::class,'pangkasJejak'])->name('keamanan.jejak.pangkas');

        /* Penetapan pemilik bagi prosedur dan berita lama yang belum
           bertuan — akibat yang disengaja dari migrasi tanpa backfill. */
        Route::get('pemilik',           [PemilikController::class,'index'])->name('pemilik');
        Route::post('pemilik/tetapkan', [PemilikController::class,'tetapkan'])->name('pemilik.tetapkan');
    });

    /* ---- Perangkat saya ----
       Sengaja di luar grup admin: memutus perangkat yang mencurigakan
       adalah tindakan pertama pemilik akun, bukan tindakan yang harus
       ia mintakan lebih dulu kepada orang lain. */
    Route::get('akun/perangkat',        [PerangkatSayaController::class,'index'])->name('keamanan.perangkat');
    Route::delete('akun/perangkat',     [PerangkatSayaController::class,'putus'])->name('keamanan.perangkat.putus');
    Route::post('akun/perangkat/lain',  [PerangkatSayaController::class,'putusLain'])->name('keamanan.perangkat.putus-lain');

    /* ---- Gudang & Penyimpanan ---- */
    Route::prefix('gudang')->name('gudang.')->group(function () {
        Route::get('/',          [GudangController::class, 'index'])->name('index');
        Route::get('barang',     [GudangController::class, 'barang'])->name('barang');
        Route::get('mutasi',     [GudangController::class, 'mutasi'])->name('mutasi');
        Route::get('opname',     [GudangController::class, 'opname'])->name('opname');
        Route::get('lokasi',     [GudangController::class, 'lokasi'])->name('lokasi');
        Route::get('b3',         [GudangController::class, 'b3'])->name('b3');
        Route::get('laporan',    [GudangController::class, 'laporan'])->name('laporan');

        /* Pencatatan dibatasi admin dan petugas gudang. Rute 'baru'
           didaftarkan sebelum '{barang}' — kalau tidak, "baru" tertangkap
           sebagai id barang dan formulirnya berujung 404. */
        Route::middleware('can:admin')->group(function () {
            Route::get('barang/baru',          [GudangController::class, 'barangForm'])->name('barang.baru');
            Route::post('barang',              [GudangController::class, 'barangSimpan'])->name('barang.simpan');
            Route::get('barang/{barang}/edit', [GudangController::class, 'barangForm'])->name('barang.edit');
            Route::put('barang/{barang}',      [GudangController::class, 'barangSimpan'])->name('barang.ubah');
            Route::delete('barang/{barang}',   [GudangController::class, 'barangHapus'])->name('barang.hapus');

            Route::post('mutasi',              [GudangController::class, 'mutasiSimpan'])->name('mutasi.simpan');
            Route::post('opname',              [GudangController::class, 'opnameSimpan'])->name('opname.simpan');
            Route::post('lokasi',              [GudangController::class, 'lokasiSimpan'])->name('lokasi.simpan');
            Route::put('lokasi/{lokasi}',      [GudangController::class, 'lokasiSimpan'])->name('lokasi.ubah');
        });
    });

    /* ---- Personalia ---- */
    Route::prefix('personalia')->name('personalia.')->group(function () {
        Route::get('/',            [PersonaliaController::class, 'index'])->name('index');
        Route::post('/',           [PersonaliaController::class, 'simpanProfil'])->name('simpan');
        Route::delete('avatar',    [PersonaliaController::class, 'hapusAvatar'])->name('avatar.hapus');
        Route::post('tema',        [PersonaliaController::class, 'tema'])->name('tema');
        Route::get('perusahaan',   [PersonaliaController::class, 'perusahaan'])->name('perusahaan');
        Route::post('perusahaan',  [PersonaliaController::class, 'simpanPerusahaan'])->name('perusahaan.simpan');
        Route::delete('perusahaan/logo', [PersonaliaController::class, 'hapusLogo'])->name('logo.hapus');
        Route::post('perusahaan/baru',   [PersonaliaController::class, 'tambahPerusahaan'])->name('perusahaan.tambah');
        Route::get('direktori',    [PersonaliaController::class, 'direktori'])->name('direktori');
        Route::post('direktori/{pengguna}/perusahaan',
            [PersonaliaController::class, 'tetapkanPerusahaan'])->name('direktori.perusahaan');
    });

    /* ---- Pesan (chat langsung + grup perusahaan) ---- */
    Route::prefix('pesan')->name('pesan.')->group(function () {
        Route::get('/',      [ChatController::class, 'index'])->name('index');
        Route::post('mulai', [ChatController::class, 'mulai'])->name('mulai');
        Route::post('{percakapan}', [ChatController::class, 'kirim'])->name('kirim');
    });

    /* ---- Bantuan (asisten AI + admin) ---- */
    Route::prefix('bantuan')->name('bantuan.')->group(function () {
        Route::get('/',       [BantuanController::class, 'index'])->name('index');
        Route::post('/',      [BantuanController::class, 'kirim'])->name('kirim');
        Route::get('masuk',   [BantuanController::class, 'masuk'])->name('masuk');
        Route::post('{percakapan}/balas',   [BantuanController::class, 'balas'])->name('balas');
        Route::post('{percakapan}/selesai', [BantuanController::class, 'selesai'])->name('selesai');
    });

    /* ---- Profil (bawaan Breeze) ---- */
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

require __DIR__.'/pilar.php';
