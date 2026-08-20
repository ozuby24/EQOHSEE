<?php

use App\Http\Controllers\MinersController;
use App\Http\Controllers\DasborController;
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
        ->whereIn('jenis', array_keys(\App\Support\Berkas::TERSAJI))
        ->whereNumber('baris')->whereNumber('i')
        ->name('berkas.sajikan');

    Route::get('berkas/{jenis}/{baris}/unduh/{i?}', [BerkasController::class, 'unduh'])
        ->whereIn('jenis', array_keys(\App\Support\Berkas::TERSAJI))
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
    Route::prefix('miners')->name('miners.')->group(function () {
        Route::get('/',                [MinersController::class,'index'])->name('index');
        Route::get('dasbor',           [MinersController::class,'dasbor'])->name('dasbor');
        Route::post('/',               [MinersController::class,'store'])->name('store');

        /* Pengajuan MCU didaftarkan SEBELUM {paspor}, dan urutannya
           bukan gaya penulisan: `authority/mcu` cocok dengan pola
           `authority/{paspor}` juga, jadi yang terdaftar lebih dahulu
           yang menang. Terbalik, halaman pengajuan akan mencari paspor
           bernomor "mcu" dan memulangkan 404 yang membingungkan. */
        Route::prefix('mcu')->name('mcu.')->group(function () {
            Route::get('/',   [MinersController::class,'mcuIndex'])->name('index');
            Route::post('/',  [MinersController::class,'mcuStore'])->name('store');

            Route::put('{pengajuan}',    [MinersController::class,'mcuUpdate'])->name('update');
            Route::delete('{pengajuan}', [MinersController::class,'mcuDestroy'])->name('destroy');

            Route::post('{pengajuan}/nama',       [MinersController::class,'mcuTambahNama'])->name('nama.tambah');
            Route::delete('{pengajuan}/nama/{mcu}', [MinersController::class,'mcuHapusNama'])->name('nama.hapus');
            Route::put('{pengajuan}/hasil/{mcu}', [MinersController::class,'mcuIsiHasil'])->name('hasil');

            Route::post('{pengajuan}/ajukan', [MinersController::class,'ajukanMcu'])->name('ajukan');
            Route::post('{pengajuan}/tinjau', [MinersController::class,'tinjauMcu'])->name('tinjau');
            Route::post('{pengajuan}/paraf',  [MinersController::class,'parafMcu'])->name('paraf');
        });

        /* Field break, cuti, dan campaign didaftarkan SEBELUM {paspor},
           sebab semuanya cocok dengan pola `miners/{paspor}` juga —
           yang terdaftar lebih dahulu yang menang. */
        Route::prefix('field-break')->name('fieldBreak.')->group(function () {
            Route::get('/',   [MinersController::class,'fieldBreak'])->name('index');
            Route::post('/',  [MinersController::class,'fieldBreakStore'])->name('store');
            Route::put('{fieldBreak}',    [MinersController::class,'fieldBreakUpdate'])->name('update');
            Route::delete('{fieldBreak}', [MinersController::class,'fieldBreakDestroy'])->name('destroy');
            Route::post('{fieldBreak}/kembali', [MinersController::class,'fieldBreakKembali'])->name('kembali');
            Route::post('{fieldBreak}/ajukan',  [MinersController::class,'fieldBreakAjukan'])->name('ajukan');
            Route::post('{fieldBreak}/tinjau',  [MinersController::class,'fieldBreakTinjau'])->name('tinjau');
        });

        Route::prefix('cuti')->name('cuti.')->group(function () {
            Route::get('/',  [MinersController::class,'cuti'])->name('index');
            Route::post('/', [MinersController::class,'cutiStore'])->name('store');
            Route::post('jatah', [MinersController::class,'cutiJatah'])->name('jatah');
            Route::delete('{cuti}',       [MinersController::class,'cutiDestroy'])->name('destroy');
            Route::post('{cuti}/ajukan',  [MinersController::class,'cutiAjukan'])->name('ajukan');
            Route::post('{cuti}/tinjau',  [MinersController::class,'cutiTinjau'])->name('tinjau');
        });

        Route::prefix('campaign')->name('campaign.')->group(function () {
            Route::get('/',  [MinersController::class,'campaign'])->name('index');
            Route::post('/', [MinersController::class,'campaignStore'])->name('store');
            Route::put('{campaign}',    [MinersController::class,'campaignUpdate'])->name('update');
            Route::delete('{campaign}', [MinersController::class,'campaignDestroy'])->name('destroy');
            Route::post('{campaign}/jangkauan', [MinersController::class,'campaignJangkauan'])->name('jangkauan');
            Route::post('{campaign}/ajukan',    [MinersController::class,'campaignAjukan'])->name('ajukan');
            Route::post('{campaign}/tinjau',    [MinersController::class,'campaignTinjau'])->name('tinjau');
        });

        /* Riwayat per tahap, urut mengikuti alurnya. Didaftarkan
           sebelum {paspor} — `miners/riwayat/...` cocok pula dengan
           pola itu.

           Empat nama rute tersendiri, bukan satu rute berparameter.
           Sebabnya bukan gaya: RuteInertiaTest memanggil SETIAP nama
           rute terdaftar tanpa parameter untuk memastikan halamannya
           benar-benar Inertia. Satu rute berparameter memaksa uji itu
           menyimpan daftar parameter contoh — dan daftar semacam itu
           adalah tempat pertama yang tertinggal saat rutenya berubah. */
        foreach (['mcu', 'induksi', 'mine-permit', 'mine-license', 'authority'] as $tahap) {
            Route::get('riwayat/'.$tahap, [MinersController::class, 'riwayat'])
                ->defaults('tahap', $tahap)
                ->name('riwayat.'.$tahap);
        }

        /* Pemantauan masa berlaku kartu — halaman tersendiri, sebab
           pertanyaannya berbeda dari daftar orang: bukan "siapa saja
           pekerja kita" melainkan "siapa yang hari ini tidak boleh
           masuk". Didaftarkan SEBELUM rute ber-{paspor} supaya
           "kedaluwarsa" tidak terbaca sebagai nomor paspor. */
        Route::get('kedaluwarsa', [MinersController::class,'kedaluwarsa'])->name('kedaluwarsa');

        /* Unggah berkas SIM lalu baca masa berlakunya. Menjawab JSON,
           bukan Inertia: pemanggilnya sebuah kolom pada formulir yang
           sedang diisi, dan memuat ulang halamannya akan membuang
           seluruh isian lain yang belum tersimpan. */
        Route::post('sim', [MinersController::class,'unggahSim'])->name('sim.unggah');

        Route::get('{paspor}',         [MinersController::class,'show'])->name('show');
        Route::put('{paspor}',         [MinersController::class,'update'])->name('update');
        Route::delete('{paspor}',      [MinersController::class,'destroy'])
            ->middleware('can:admin')->name('destroy');

        Route::post('{paspor}/sertifikat',              [MinersController::class,'simpanSertifikat'])->name('sertifikat.simpan');
        Route::delete('{paspor}/sertifikat/{sertifikat}', [MinersController::class,'hapusSertifikat'])->name('sertifikat.hapus');

        /* MCU yang dicatat LANGSUNG pada orangnya, tanpa surat pengajuan:
           pekerja baru dan pemeriksaan khusus. Namanya sengaja dibedakan
           dari miners.mcu.* di atas — keduanya menyimpan hasil MCU,
           tetapi yang satu bagian dari rombongan yang disetujui bersama
           dan yang satu berdiri sendiri. */
        Route::post('{paspor}/mcu',        [MinersController::class,'simpanMcu'])->name('mcuLangsung.simpan');
        Route::delete('{paspor}/mcu/{mcu}', [MinersController::class,'hapusMcu'])->name('mcuLangsung.hapus');

        Route::post('{paspor}/kartu',          [MinersController::class,'simpanKartu'])->name('kartu.simpan');
        Route::put('{paspor}/kartu/{kartu}',    [MinersController::class,'ubahKartu'])->name('kartu.ubah');
        Route::delete('{paspor}/kartu/{kartu}', [MinersController::class,'hapusKartu'])->name('kartu.hapus');
        Route::post('{paspor}/kartu/{kartu}/ajukan', [MinersController::class,'ajukanKartu'])->name('kartu.ajukan');
        Route::post('{paspor}/kartu/{kartu}/tinjau', [MinersController::class,'tinjauKartu'])->name('kartu.tinjau');
        Route::post('{paspor}/kartu/{kartu}/paraf',  [MinersController::class,'parafKartu'])->name('kartu.paraf');
        Route::get('{paspor}/kartu/{kartu}/cetak',   [MinersController::class,'cetakPermit'])->name('permit.cetak');

        /* Unit SIMPER — satu baris per unit yang boleh dikemudikan,
           masing-masing dengan nilai dan berkas ujinya sendiri.
           Penghapusan menuntut admin, sama seperti penghapusan merusak
           lainnya di aplikasi ini. */
        Route::post('{paspor}/kartu/{kartu}/unit',        [MinersController::class,'simpanUnitKartu'])->name('kartu.unit.simpan');
        Route::put('{paspor}/kartu/{kartu}/unit/{unit}',  [MinersController::class,'ubahUnitKartu'])->name('kartu.unit.ubah');
        Route::delete('{paspor}/kartu/{kartu}/unit/{unit}', [MinersController::class,'hapusUnitKartu'])->middleware('can:admin')->name('kartu.unit.hapus');

        Route::post('{paspor}/induksi',              [MinersController::class,'simpanInduksi'])->name('induksi.simpan');
        Route::delete('{paspor}/induksi/{induksi}',  [MinersController::class,'hapusInduksi'])->name('induksi.hapus');
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
