<?php

use App\Http\Controllers\{
    CertificateController, CourseController, DashboardController, EvaluationController,
    LearnController, NewsController, ProcedureController, ProfileController,
    QuizController, SopController
};
use App\Http\Controllers\{CourseContentController, DocumentController, EvaluasiTemuanController, HazardController,
    HazardExportController, InspectionController, InspectionTemplateController,
    EnergyController, IsoController, KoController, KuesionerController, SignatoryController, SmkpController,
    TpkkpController, TpkkpLanjutController};
use App\Http\Controllers\Admin\{CompanyController, SystemController, UserController};
use Illuminate\Support\Facades\Route;

/* ============ VERIFIKASI SERTIFIKAT (publik) ============ */
Route::get('verifikasi/{kode}', [\App\Http\Controllers\CertificateController::class,'verify'])->name('certificates.verify');

/* ============ KUESIONER PUBLIK (tanpa login) ============ */
Route::get('q/{token}',              [KuesionerController::class,'pilih'])->name('kuesioner.pilih');
Route::get('q/{token}/selesai',      [KuesionerController::class,'selesai'])->name('kuesioner.selesai');
Route::get('q/{token}/{cat}',        [KuesionerController::class,'form'])->name('kuesioner.form');
Route::post('q/{token}/{cat}',       [KuesionerController::class,'submit'])->name('kuesioner.submit');

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('landing'))->name('beranda');

Route::middleware('auth')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /* ---- Kursus ---- */
    Route::resource('courses', CourseController::class)->only(['index', 'show']);
    Route::resource('courses', CourseController::class)->except(['index', 'show'])->middleware('can:admin');

    /* ---- Belajar ---- */
    Route::post('courses/{course}/enroll',  [LearnController::class, 'enroll'])->name('courses.enroll');
    Route::get('learn/{course}',            [LearnController::class, 'show'])->name('learn.show');
    Route::post('modules/{module}/complete',[LearnController::class, 'complete'])->name('modules.complete');
    Route::post('notes/{module}',           [LearnController::class, 'saveNote'])->name('notes.save');

    /* ---- Kuis (dinilai di server) ---- */
    Route::get('quizzes/{quiz}',        [QuizController::class, 'show'])->name('quizzes.show');
    Route::post('quizzes/{quiz}/submit',[QuizController::class, 'submit'])->name('quizzes.submit');

    /* ---- Prosedur ---- */
    Route::resource('procedures', ProcedureController::class)->only(['index']);
    Route::resource('procedures', ProcedureController::class)->except(['index','show'])->middleware('can:admin');

    /* ---- Evaluasi SOP (kunci jawaban tidak dikirim ke klien) ---- */
    Route::get('sop',                     [SopController::class, 'index'])->name('sop.index');
    Route::get('sop/{evaluation}',        [SopController::class, 'show'])->name('sop.show');
    Route::post('sop/{evaluation}/grade', [SopController::class, 'grade'])->name('sop.grade');

    /* ---- Sertifikat ---- */
    Route::get('certificates',               [CertificateController::class, 'index'])->name('certificates.index');
    Route::post('certificates/{course}',     [CertificateController::class, 'store'])->name('certificates.store');
    Route::get('certificates/{certificate}', [CertificateController::class, 'show'])->name('certificates.show');

    /* ---- Berita ---- */
    Route::resource('news', NewsController::class)->only(['index','show']);
    Route::resource('news', NewsController::class)->except(['index','show'])->middleware('can:admin');

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
    Route::delete('kuesioner/{response}',    [KuesionerController::class,'destroyResponse'])->name('kuesioner.response.destroy');
    Route::get('kuesioner',                  [KuesionerController::class,'admin'])->name('kuesioner.admin');

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

    /* ================= WEBSITE #6 — Energy Performance Center ================= */
    Route::prefix('energi')->name('energi.')->group(function () {
        Route::get('/',            [EnergyController::class,'index'])->name('index');
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
        Route::delete('penghematan/{peluang}',[EnergyController::class,'hapusPeluang'])->name('hemat.hapus');

        Route::get('karbon',       [EnergyController::class,'karbon'])->name('karbon');
        Route::get('kalkulator',   [EnergyController::class,'kalkulator'])->name('kalkulator');
        Route::get('laporan',      [EnergyController::class,'laporan'])->name('laporan');

        Route::get('data-induk',   [EnergyController::class,'master'])->name('master');
        Route::post('data-induk',  [EnergyController::class,'simpanUnit'])->name('master.simpan');
        Route::delete('data-induk/{unit}', [EnergyController::class,'hapusUnit'])->name('master.hapus');
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

        // Pintasan menu samping: tanpa parameter, disalurkan ke audit berjalan.
        foreach ([
            'tahap1' => 'tahap-1', 'rencana' => 'rencana', 'rapat' => 'rapat', 'temuan' => 'temuan',
            'berita' => 'berita-acara', 'rencana-cetak' => 'laporan-rencana', 'laporan' => 'laporan-audit',
        ] as $bagian => $ruas) {
            Route::get("lanjut/{$ruas}", [SmkpController::class,'lanjut'])
                ->defaults('bagian', $bagian)->name('ke.'.$bagian);
        }

        Route::get('{smkp}',           [SmkpController::class,'show'])->name('show');
        Route::get('{smkp}/ubah',      [SmkpController::class,'edit'])->name('edit');
        Route::put('{smkp}',           [SmkpController::class,'update'])->name('update');
        Route::delete('{smkp}',        [SmkpController::class,'destroy'])->middleware('can:admin')->name('destroy');

        Route::get('{smkp}/laporan',   [SmkpController::class,'laporan'])->name('laporan');
        Route::post('{smkp}/tahap',    [SmkpController::class,'ubahTahap'])->name('tahap');

        // Tahap I — permulaan audit, peninjauan dokumen, persiapan lapangan
        Route::get('{smkp}/tahap-1',       [SmkpController::class,'tahap1'])->name('tahap1');
        Route::post('{smkp}/tahap-1',      [SmkpController::class,'simpanTahap1'])->name('tahap1.simpan');
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
            Route::delete('{template}',     [InspectionTemplateController::class,'destroy'])->name('destroy');
            Route::post('{template}/param', [InspectionTemplateController::class,'storeItem'])->name('item.store');
            Route::post('{template}/salin', [InspectionTemplateController::class,'salin'])->name('salin');
        });
        Route::delete('param/{item}', [InspectionTemplateController::class,'destroyItem'])->name('template.item.destroy');

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
        Route::delete('{inspeksi}',       [InspectionController::class,'destroy'])->name('destroy');

        // Inspektur
        Route::post('{inspeksi}/inspektur',  [InspectionController::class,'addInspector'])->name('inspector.store');
        Route::delete('inspektur/{inspector}',[InspectionController::class,'removeInspector'])->name('inspector.destroy');

        // Item pemeriksaan
        Route::post('{inspeksi}/items',   [InspectionController::class,'saveItems'])->name('items.save');
        Route::post('{inspeksi}/item',    [InspectionController::class,'storeItem'])->name('item.store');
        Route::delete('item/{item}',      [InspectionController::class,'destroyItem'])->name('item.destroy');
        Route::post('item/{item}/angkat', [InspectionController::class,'angkat'])->name('item.angkat');
    });

    /* ---- Panel Admin ---- */
    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
        Route::resource('companies', CompanyController::class)->except(['show']);
        Route::get('system',        [SystemController::class, 'index'])->name('system');
        Route::delete('system/logs',[SystemController::class, 'clearLogs'])->name('system.logs.clear');
        Route::post('system/maintenance/{aksi}', [SystemController::class,'maintenance'])->name('system.maintenance');
    });

    /* ---- Profil (bawaan Breeze) ---- */
    Route::get('/profile',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

require __DIR__.'/pilar.php';
