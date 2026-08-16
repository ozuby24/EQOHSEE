<?php

namespace App\Support;

use App\Models\{AngkutAlat, AngkutMuatan, AngkutRegu, BiayaAkun, BiayaAnggaran, BiayaRealisasi,
                Company, Document, DocumentIso, DocumentRevision, EnergyEquipment,
                EnergyFuelLog, GeoBacaan, GeoInstrumen, GeoLereng, GudangBarang, GudangLokasi,
                GudangMutasi, HazardReport, Inspection, InspectionInspector, InspectionItem,
                IzinAmbang, IzinGas, IzinKerja, IzinPeriksa, IzinSyarat,
                KoAction, KoInspection, KoObject, KoReview, KoSafeguard,
                LedakHasil, LedakRencana, LedakTitik, LedakUkur, LingkunganArea,
                LingkunganPantau, LingkunganParameter, MineOperationalRecord,
                MineOperationalTarget, News, Procedure, ReklamasiKemajuan, SmkpAttendee, SmkpAudit,
                SmkpFinding, SopEvaluation, SopEvaluationAttempt,
                SopEvaluationQuestion,
                Certificate, Course, Enrollment, InspectionTemplate, Material, Module,
                PostTrainingEvaluation, Quiz, QuizAttempt, QuizQuestion,
                TindakLanjut, User, WaterLog, WaterSump, WaterSumpPump, WorkOrder};
use Illuminate\Support\Carbon;

/**
 * Pemuat data contoh untuk satu perusahaan.
 *
 * Gunanya satu: membuat setiap modul terisi sekaligus, supaya angka
 * turunannya dapat diperiksa apakah masuk akal. Halaman kosong tidak
 * pernah salah — dan justru karena itu ia tidak pernah membuktikan apa
 * pun tentang benar-tidaknya hitungan di belakangnya.
 *
 * Yang paling penting di kelas ini bukan pengisiannya melainkan
 * PENGHAPUSANNYA. Memuat ulang berarti membuang seluruh data perusahaan
 * itu lebih dulu, dan itu tidak dapat dibatalkan. Tiga lapis yang
 * memisahkannya dari kehilangan data sungguhan:
 *
 * 1. Hanya perusahaan bertanda `demo` yang dapat dimuati. Penandaan itu
 *    tindakan tersendiri yang harus disengaja lebih dulu; tombol yang
 *    ditekan pada perusahaan yang salah tidak akan menemukan sasaran.
 *
 * 2. Penghapusan selalu menyebut company_id secara tegas, satu tabel
 *    satu perintah. Tidak ada truncate, tidak ada delete tanpa where —
 *    keduanya bekerja sempurna sampai satu kali dijalankan di tempat
 *    yang salah.
 *
 * 3. Scope perusahaan sengaja DILEPAS saat menghapus. Terdengar
 *    berlawanan, tetapi justru itu yang membuatnya tepat: dengan scope
 *    terpasang, administrator melihat seluruh perusahaan dan
 *    penghapusannya akan ikut melebar; tanpa scope, satu-satunya
 *    penyaring yang tersisa adalah company_id yang ditulis tegas di
 *    baris itu juga.
 *
 * Angkanya sengaja tidak bulat dan tidak sempurna: ada bulan yang
 * melampaui anggaran, lereng yang lajunya naik, pompa yang rusak, izin
 * yang lewat waktu belum ditutup. Data contoh yang seluruhnya rapi tidak
 * pernah menunjukkan apakah peringatannya bekerja — dan peringatan yang
 * tidak pernah menyala tidak dapat dibedakan dari peringatan yang rusak.
 */
final class DataContoh
{
    /**
     * Tabel yang dibersihkan, anak lebih dulu daripada induknya.
     *
     * Urutannya bukan selera: kunci asingnya nyata, dan menghapus induk
     * lebih dulu membuat penghapusannya gagal di tengah jalan — separuh
     * terbuang, separuh tertinggal.
     */
    private const URUTAN_HAPUS = [
        AngkutMuatan::class, AngkutRegu::class, AngkutAlat::class,
        BiayaRealisasi::class, BiayaAnggaran::class, BiayaAkun::class,
        IzinGas::class, IzinPeriksa::class, IzinKerja::class,
        IzinAmbang::class, IzinSyarat::class,
        LedakUkur::class, LedakHasil::class, LedakRencana::class, LedakTitik::class,
        GeoBacaan::class, GeoInstrumen::class, GeoLereng::class,
        ReklamasiKemajuan::class, LingkunganPantau::class,
        LingkunganArea::class, LingkunganParameter::class,
        WaterLog::class, WaterSumpPump::class, WaterSump::class,
        GudangMutasi::class, GudangBarang::class, GudangLokasi::class,
        MineOperationalRecord::class, MineOperationalTarget::class,
        TindakLanjut::class,

        /* Modul yang ditambahkan belakangan. Urutannya tetap aturan yang
           sama — anak lebih dulu — dan di sini aturan itu lebih berbahaya
           daripada di atas: ko_objects dirujuk oleh enam tabel, dua di
           antaranya (water_sump_pumps, angkut_alats) sudah dibuang lebih
           dulu di daftar atas. KoObject karena itu harus berada paling
           belakang, sesudah seluruh perujuknya. */
        InspectionItem::class, InspectionInspector::class, Inspection::class,
        SmkpFinding::class, SmkpAttendee::class, SmkpAudit::class,
        DocumentRevision::class, DocumentIso::class, Document::class,
        HazardReport::class,
        EnergyFuelLog::class, EnergyEquipment::class,
        KoSafeguard::class, KoInspection::class, KoAction::class, KoReview::class,
        WorkOrder::class,
        KoObject::class,

        /* Prosedur dan berita baru dapat masuk ke sini sesudah keduanya
           melekat perusahaan; sebelum itu penghapusnya tidak punya
           company_id untuk disebut, dan barisnya akan menumpuk tiap
           kali tombolnya ditekan.

           Procedure paling belakang di antara ketiganya: Document
           menunjuk procedure_id, dan Document dibuang lebih dulu di
           atas. SopEvaluation menunjuk Procedure pula, jadi ia dan
           anak-anaknya mendahuluinya. */
        SopEvaluationAttempt::class, SopEvaluationQuestion::class, SopEvaluation::class,
        Procedure::class,
        News::class,

        /* LMS. Sertifikat, pendaftaran, percobaan, dan evaluasi pelatihan
           menggantung pada kursus, jadi semuanya dibuang lebih dulu.
           Kursus, kuis, dan template inspeksi TIDAK punya company_id —
           keduanya pustaka bersama — dan disaring lewat penanda `demo`
           sebagai gantinya. */
        Certificate::class, PostTrainingEvaluation::class,
        QuizAttempt::class, Enrollment::class,
        QuizQuestion::class, Quiz::class,
        Material::class, Module::class, Course::class,
        InspectionTemplate::class,
    ];

    /** @var list<string> hal yang perlu diketahui pemanggilnya */
    private array $catatan = [];

    /** @var array<string,int> */
    private array $dibuat = [];

    private function __construct(
        private readonly Company $c,
        private readonly ?User $pengaju,
        private readonly ?User $peninjau,
        private readonly Carbon $kini,
    ) {}

    /**
     * Muat ulang data contoh satu perusahaan.
     *
     * @param  User|null  $peninjau  yang menekan tombolnya; dialah yang
     *                               menyetujui baris-barisnya, sebab data
     *                               contoh yang berhenti sebagai draf
     *                               tidak masuk satu pun hitungan KPI.
     * @return array{dihapus:int,dibuat:array<string,int>,catatan:list<string>}
     *
     * @throws \RuntimeException bila perusahaan itu bukan perusahaan contoh
     */
    public static function muat(Company $c, ?User $peninjau = null): array
    {
        if (!$c->demo) {
            throw new \RuntimeException(
                'Perusahaan "'.$c->name.'" bukan perusahaan contoh. '
                .'Tandai dulu sebagai perusahaan contoh bila datanya memang boleh dibuang.'
            );
        }

        /* Pengaju harus orang perusahaan itu sendiri, dan bukan
           peninjaunya: alurnya menolak orang yang menyetujui
           pekerjaannya sendiri, dan penolakan itu memang benar. */
        $pengaju = User::withoutGlobalScopes()
            ->where('company_id', $c->id)
            ->when($peninjau, fn ($q) => $q->where('id', '!=', $peninjau->getKey()))
            ->orderBy('id')->first();

        $diri = new self($c, $pengaju, $peninjau, Carbon::now());

        $dihapus = $diri->bersihkan();
        $diri->periksaKesiapan();
        $diri->isi();

        return [
            'dihapus' => $dihapus,
            'dibuat'  => $diri->dibuat,
            'catatan' => $diri->catatan,
        ];
    }

    /**
     * Buang seluruh data contoh satu perusahaan, tanpa mengisinya lagi.
     *
     * Dipisahkan dari `muat()` karena keduanya menjawab kebutuhan yang
     * berbeda. `muat()` untuk menyegarkan: buang lalu isi, dan modulnya
     * kembali berisi. Yang ini untuk MENGOSONGKAN — dipakai ketika
     * pemeriksaan sudah selesai dan pemasangannya hendak dipakai
     * sungguhan.
     *
     * Tanpa pemisahan ini, satu-satunya jalan mengosongkan adalah
     * memuat ulang lalu menghapus barisnya satu per satu lewat tiap
     * modul — dan pada dua ratus empat puluh baris di tujuh belas
     * modul, itu bukan jalan yang akan ditempuh siapa pun. Data contoh
     * karena itu akan tertinggal, lalu ikut terhitung sebagai data
     * sungguhan pada laporan pertama yang dicetak.
     *
     * Tiga lapis pengamannya sama persis dengan `muat()`: hanya
     * perusahaan bertanda `demo`, penghapusan selalu menyebut
     * company_id, dan scope dilepas supaya company_id itulah
     * satu-satunya penyaring.
     *
     * @return array{dihapus:int,rincian:array<string,int>}
     *
     * @throws \RuntimeException bila perusahaan itu bukan perusahaan contoh
     */
    public static function buang(Company $c): array
    {
        if (!$c->demo) {
            throw new \RuntimeException(
                'Perusahaan "'.$c->name.'" bukan perusahaan contoh. '
                .'Tandai dulu sebagai perusahaan contoh bila datanya memang boleh dibuang.'
            );
        }

        /* Rinciannya dihitung SEBELUM dihapus — sesudahnya semuanya nol,
           dan yang menekan tombolnya tidak akan pernah tahu apa yang
           barusan hilang. */
        $rincian = self::rincianIsi($c);

        $diri = new self($c, null, null, Carbon::now());

        return ['dihapus' => $diri->bersihkan(), 'rincian' => $rincian];
    }

    /** Hitung baris yang akan terhapus, tanpa menghapus apa pun. */
    public static function hitungIsi(Company $c): int
    {
        return array_sum(self::rincianIsi($c));
    }

    /**
     * Rincian per tabel dari apa yang akan terhapus.
     *
     * Angka inilah yang diperlihatkan sebelum tombol yang tidak dapat
     * dibatalkan ditekan, dan rinciannya lebih berguna daripada
     * jumlahnya: satu tabel yang isinya jauh di luar dugaan adalah
     * tanda bahwa sasarannya salah.
     *
     * @return array<string,int> nama tabel => jumlah baris, yang kosong dibuang
     */
    public static function rincianIsi(Company $c): array
    {
        $rincian = [];

        foreach (self::URUTAN_HAPUS as $kelas) {
            $n = self::kueri($kelas, $c)->count();

            if ($n > 0) $rincian[(new $kelas)->getTable()] = $n;
        }

        return $rincian;
    }

    /* ═══════════ penghapusan ═══════════ */

    private function bersihkan(): int
    {
        $n = 0;
        foreach (self::URUTAN_HAPUS as $kelas) {
            $n += self::kueri($kelas, $this->c)->delete();
        }

        return $n;
    }

    /**
     * Kueri satu tabel yang dibatasi tegas pada satu perusahaan.
     *
     * Anak yang tidak punya company_id sendiri disaring lewat induknya —
     * kolom yang tidak ada tidak dapat dijadikan penyaring, dan
     * menghapus tanpa penyaring adalah cara termudah kehilangan data
     * perusahaan lain.
     *
     * Yang tidak dikenali sengaja tidak menghapus apa pun. Itu membuat
     * tabel baru yang lupa didaftarkan meninggalkan baris yatim — yang
     * terlihat, dapat diperbaiki, dan jauh lebih ringan akibatnya
     * daripada tebakan yang salah tentang siapa pemiliknya.
     */
    private static function kueri(string $kelas, Company $c)
    {
        $q = $kelas::withoutGlobalScopes();
        $model = new $kelas;

        if ($model->getConnection()->getSchemaBuilder()
                  ->hasColumn($model->getTable(), 'company_id')) {
            return $q->where('company_id', $c->id);
        }

        return match ($kelas) {
            WaterSumpPump::class => $q->whereIn('water_sump_id',
                WaterSump::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),
            GeoInstrumen::class => $q->whereIn('geo_lereng_id',
                GeoLereng::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            InspectionItem::class, InspectionInspector::class => $q->whereIn('inspection_id',
                Inspection::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            SmkpFinding::class, SmkpAttendee::class => $q->whereIn('audit_id',
                SmkpAudit::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            DocumentRevision::class, DocumentIso::class => $q->whereIn('document_id',
                Document::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            EnergyFuelLog::class => $q->whereIn('equipment_id',
                EnergyEquipment::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            KoSafeguard::class, KoInspection::class, KoAction::class, KoReview::class
                => $q->whereIn('ko_object_id',
                    KoObject::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            SopEvaluation::class => $q->whereIn('procedure_id',
                Procedure::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            SopEvaluationQuestion::class => $q->whereIn('evaluation_id',
                SopEvaluation::withoutGlobalScopes()->whereIn('procedure_id',
                    Procedure::withoutGlobalScopes()->where('company_id', $c->id)->select('id')
                )->select('id')),

            SopEvaluationAttempt::class => $q->whereIn('evaluation_id',
                SopEvaluation::withoutGlobalScopes()->whereIn('procedure_id',
                    Procedure::withoutGlobalScopes()->where('company_id', $c->id)->select('id')
                )->select('id')),

            /* Pustaka bersama: tidak punya company_id, jadi penyaringnya
               adalah perusahaan contoh yang MEMBUATNYA. Kursus sungguhan
               bawaannya null dan karena itu tidak pernah tersentuh; dan
               dua perusahaan contoh tidak saling membuang pustaka. */
            Course::class, Quiz::class, InspectionTemplate::class
                => $q->where('demo_company_id', $c->id),

            Module::class, Material::class => $q->whereIn('course_id',
                Course::withoutGlobalScopes()->where('demo_company_id', $c->id)->select('id')),

            QuizQuestion::class => $q->whereIn('quiz_id',
                Quiz::withoutGlobalScopes()->where('demo_company_id', $c->id)->select('id')),

            Enrollment::class, PostTrainingEvaluation::class => $q->whereIn('course_id',
                Course::withoutGlobalScopes()->where('demo_company_id', $c->id)->select('id')),

            QuizAttempt::class => $q->whereIn('quiz_id',
                Quiz::withoutGlobalScopes()->where('demo_company_id', $c->id)->select('id')),

            default => $q->whereRaw('1 = 0'),
        };
    }

    /* ═══════════ pengisian ═══════════ */

    /**
     * Peringatkan lebih dulu bila barisnya tidak akan dapat disetujui.
     *
     * Tanpa ini kegagalannya berbentuk halaman yang terisi penuh dengan
     * seluruh indikatornya nol — bentuk kegagalan yang paling mudah
     * disalahartikan sebagai hitungan yang rusak.
     */
    private function periksaKesiapan(): void
    {
        if (!$this->pengaju) {
            $this->catatan[] = 'Perusahaan ini belum punya pengguna, sehingga datanya '
                .'tersimpan sebagai draf dan belum masuk hitungan KPI. Tambahkan satu '
                .'pengguna pada perusahaan ini, lalu muat ulang.';

            return;
        }

        if (!Alur::peninjau($this->peninjau)) {
            $this->catatan[] = 'Pemuatan dijalankan tanpa peninjau berwenang, sehingga '
                .'datanya tersimpan sebagai draf dan belum masuk hitungan KPI.';
        }
    }

    private function isi(): void
    {
        $this->dibuat = array_filter([
            'Operasi'    => $this->operasi(),
            'Gudang'     => $this->gudang(),
            'Air'        => $this->air(),
            'Geoteknik'  => $this->geoteknik(),
            'Lingkungan' => $this->lingkungan(),
            'Peledakan'  => $this->peledakan(),
            'Angkutan'   => $this->angkutan(),
            'Biaya'      => $this->biaya(),
            'Izin kerja' => $this->izin(),

            /* Modul di bawah ini sebelumnya tidak pernah terisi sama
               sekali. Akibatnya bukan sekadar halaman kosong: tombol
               "muat data contoh" ada supaya orang dapat memeriksa
               apakah datanya sudah benar, dan modul yang tetap kosong
               sesudah tombolnya ditekan tidak dapat diperiksa — sambil
               terlihat seolah sudah. Ketahuan ketika 38 rute rincian
               tidak dapat diuji karena tidak ada satu pun baris yang
               dapat dibuka. */
            'Dokumen'   => $this->dokumen(),
            'Bahaya'    => $this->bahaya(),
            'Inspeksi'  => $this->inspeksi(),
            'KO'        => $this->ko(),
            'Energi'    => $this->energiAlat(),
            'SMKP'      => $this->smkp(),
            'Prosedur'  => $this->prosedur(),
            'Berita'    => $this->berita(),
            'LMS'       => $this->lms(),
        ]);
    }

    /* ─────────── operasi ─────────── */

    private function operasi(): int
    {
        $n = 0;

        foreach (range(1, 12) as $b) {
            $this->baru(MineOperationalTarget::class, [
                'tahun' => $this->kini->year, 'bulan' => $b,
                'target_produksi_ton' => 120_000, 'target_overburden_bcm' => 960_000,
                'target_strip_ratio' => 8, 'target_jarak_km' => 3.4,
            ]);
            $n++;
        }

        /* Bulan-bulan yang sudah lewat: satu catatan sebulan, cukup
           untuk menggambar kecenderungan tahunannya. Produksinya
           berbeda tiap bulan — capaian yang kebetulan pas 100% pada
           semua bulan tidak membuktikan bahwa pembaginya benar. */
        $lalu = [104_800, 111_200, 98_600, 117_400, 108_100, 113_900,
                 121_300, 96_400, 109_700, 114_600, 102_900];

        /* `for`, bukan range(): range(1, 0) di PHP menghasilkan [1, 0]
           yang menurun, bukan senarai kosong. Pada bulan Januari itu
           berarti satu catatan dibuat dengan bulan 0 — yang berguling
           menjadi Desember tahun sebelumnya. */
        for ($b = 1; $b < $this->kini->month; $b++) {
            $r = $this->baru(MineOperationalRecord::class, [
                'user_id' => $this->pengaju?->getKey(),
                /* Carbon::create, bukan setMonth()->setDay(): dijalankan
                   pada tanggal 31, setMonth(2) berguling ke Maret lebih
                   dulu, dan setDay(15) sesudahnya sudah terlambat —
                   catatan Februari diam-diam tercatat sebagai Maret. */
                'tanggal' => Carbon::create($this->kini->year, $b, 15)->toDateString(),
                'shift' => 'siang', 'pit' => 'Pit Utara', 'area' => 'Blok '.$b,
                'material' => 'batubara', 'produksi_ton' => $lalu[($b - 1) % count($lalu)],
                'overburden_bcm' => round($lalu[($b - 1) % count($lalu)] * 8.6),
                'jarak_angkut_km' => 3.4, 'jumlah_truk' => 18, 'jumlah_excavator' => 4,
                'jam_operasi' => 9, 'jam_delay' => 1,
            ]);
            $this->setujui($r);
            $n++;
        }

        /* Bulan berjalan diisi per hari, dua shift, sampai kemarin.
           Halaman operasi memakai bulan berjalan sebagai periode
           bawaannya: data yang hanya ada di bulan-bulan lalu membuat
           seluruh indikatornya nol pada tampilan pertama — persis
           bentuk kegagalan yang hendak dihindari data contoh.

           Sampai KEMARIN, bukan sampai hari ini: shift hari berjalan
           memang belum sepatutnya dilaporkan, dan kelengkapan yang
           menuntutnya adalah kelengkapan yang salah hitung. */
        /* Sampai kemarin — kecuali pada tanggal 1, yang tidak punya
           kemarin di bulan ini.

           Di situ pilihannya dua-duanya cacat: mengisi hari berjalan
           membuat kelengkapan shift sedikit terlalu optimis, sementara
           tidak mengisi apa pun membuat seluruh modul Operasi kosong —
           dan pada 1 Januari kosongnya bahkan mencakup seluruh tahun,
           sebab tidak ada satu pun bulan lewat untuk diisi.

           Yang dipilih yang pertama. Halaman kosong tidak pernah salah,
           dan justru karena itu ia tidak membuktikan apa pun — padahal
           membuktikan itulah satu-satunya alasan data contoh ada. */
        $hariIni = (int) $this->kini->day;
        $sampai  = max(1, $hariIni - 1);

        for ($h = 1; $h <= $sampai; $h++) {
            foreach (['siang', 'malam'] as $j => $shift) {
                // Satu hari libur tiap pekan, dan shift malam lebih
                // rendah — keduanya wajar, dan keduanya membuat
                // rata-ratanya bukan angka datar.
                if ($h % 7 === 0) continue;

                $dasar = $shift === 'siang' ? 2_150 : 1_760;
                $ton = $dasar + (($h * 37 + $j * 53) % 420) - 210;

                $r = $this->baru(MineOperationalRecord::class, [
                    'user_id' => $this->pengaju?->getKey(),
                    'tanggal' => $this->kini->copy()->setDay($h)->toDateString(),
                    'shift' => $shift, 'pit' => 'Pit Utara',
                    'area' => 'Blok '.$this->kini->month, 'material' => 'batubara',
                    'produksi_ton' => $ton, 'overburden_bcm' => round($ton * 8.2),
                    'jarak_angkut_km' => 3.4, 'jumlah_truk' => 18, 'jumlah_excavator' => 4,
                    'jam_operasi' => $shift === 'siang' ? 10 : 9,
                    'jam_delay' => $shift === 'siang' ? 1 : 1.6,
                ]);
                $this->setujui($r);
                $n++;
            }
        }

        return $n;
    }

    /* ─────────── gudang ─────────── */

    private function gudang(): int
    {
        $umum = $this->baru(GudangLokasi::class, [
            'kode' => 'GD-01', 'nama' => 'Gudang Utama', 'jenis' => 'umum',
            'lokasi' => 'Workshop Pit Utara', 'penanggung_jawab' => 'Petugas Gudang',
        ]);
        $b3 = $this->baru(GudangLokasi::class, [
            'kode' => 'GD-B3', 'nama' => 'Gudang B3', 'jenis' => 'b3',
            'lokasi' => 'Belakang workshop', 'penanggung_jawab' => 'Petugas B3',
            'berventilasi' => true, 'tahan_api' => true, 'ada_tanggul' => true,
            'ada_apar' => true, 'ada_eyewash' => true,
        ]);
        $n = 2;

        /* Barang terakhir sengaja tersisa di bawah stok minimumnya —
           tanpa satu pun barang menipis, peringatan stok tidak pernah
           menyala dan tidak dapat dibedakan dari peringatan yang mati. */
        $barang = [
            ['B3-001', 'Solar industri',       'b3',       'liter',  'mudah_menyala', $b3,   600, 420],
            ['B3-002', 'Oli bekas',            'b3',       'liter',  'berbahaya_air', $b3,   300, 180],
            ['APD-001', 'Helm keselamatan',    'apd',      'unit',   null,            $umum, 240, 90],
            ['APD-002', 'Sepatu safety',       'apd',      'pasang', null,            $umum, 200, 140],
            ['MTR-001', 'Ban OTR 27.00R49',    'material', 'unit',   null,            $umum, 24,  22],
        ];

        foreach ($barang as [$kode, $nama, $kat, $satuan, $kelas, $lokasi, $masuk, $keluar]) {
            $x = $this->baru(GudangBarang::class, [
                'lokasi_id' => $lokasi->id, 'kode' => $kode, 'nama' => $nama,
                'kategori' => $kat, 'satuan' => $satuan, 'kelas_b3' => $kelas,
                'wajib_msds' => $kat === 'b3', 'msds' => $kat === 'b3',
                'stok_min' => $kat === 'material' ? 4 : 50, 'aktif' => true,
            ]);
            $n++;

            $this->baru(GudangMutasi::class, [
                'barang_id' => $x->id, 'jenis' => 'masuk', 'jumlah' => $masuk,
                'nomor' => 'MSK/'.$kode, 'pihak' => 'Pemasok contoh',
                'tanggal' => $this->kini->copy()->subDays(28)->toDateString(),
                'user_id' => $this->pengaju?->getKey(),
            ]);
            $this->baru(GudangMutasi::class, [
                'barang_id' => $x->id, 'jenis' => 'keluar', 'jumlah' => $keluar,
                'nomor' => 'KLR/'.$kode, 'pihak' => 'Regu Perawatan',
                'tanggal' => $this->kini->copy()->subDays(5)->toDateString(),
                'user_id' => $this->pengaju?->getKey(),
            ]);
            $n += 2;
        }

        return $n;
    }

    /* ─────────── penirisan ─────────── */

    private function air(): int
    {
        $kolam = $this->baru(WaterSump::class, [
            'user_id' => $this->pengaju?->getKey(),
            'kode' => 'SMP-01', 'nama' => 'Kolam Pit Utara', 'jenis' => 'sump',
            'lokasi' => 'Dasar Pit Utara', 'kapasitas_m3' => 45_000,
            'luas_tangkapan_ha' => 62, 'koefisien_limpasan' => 0.8,
            'elevasi_luapan_m' => 12.5, 'status' => 'aktif',
            'pembersihan_terakhir' => $this->kini->copy()->subDays(96)->toDateString(),
            'interval_bersih_hari' => 90,
        ]);

        // Satu pompa rusak: kapasitas pemompaan turun separuh, dan
        // itulah keadaan yang membuat neraca airnya menarik dibaca.
        $this->baru(WaterSumpPump::class, ['water_sump_id' => $kolam->id, 'nama' => 'Pompa A',
                                           'kapasitas_m3_jam' => 450, 'status' => 'jalan']);
        $this->baru(WaterSumpPump::class, ['water_sump_id' => $kolam->id, 'nama' => 'Pompa B',
                                           'kapasitas_m3_jam' => 450, 'status' => 'rusak']);
        $n = 3;

        /* Empat belas hari sampai KEMARIN, dengan satu hari hujan lebat
           di tengahnya. Hari itu debit masuknya melampaui yang mampu
           dipompa dan volumenya naik — persis keadaan yang perlu
           terbaca. */
        $hujan = [4, 0, 12, 0, 0, 38, 96, 22, 6, 0, 0, 14, 3, 0];

        $volume = 18_000;
        foreach ($hujan as $i => $mm) {
            $masuk  = round($mm * 62 * 0.8 * 10);          // mm × ha × C × 10 = m³
            $keluar = min($masuk + 1_500, 450 * 16);
            $volume = max(0, min(45_000, $volume + $masuk - $keluar));

            $sampel = $i % 4 === 0;

            $x = $this->baru(WaterLog::class, [
                'user_id' => $this->pengaju?->getKey(), 'water_sump_id' => $kolam->id,
                'tanggal' => $this->kini->copy()->subDays(count($hujan) - $i)->toDateString(),
                'curah_hujan_mm' => $mm, 'level_m' => round($volume / 4_500, 2),
                'volume_m3' => $volume, 'debit_masuk_m3' => $masuk, 'debit_keluar_m3' => $keluar,
                'jam_pompa' => round($keluar / 450, 1), 'energi_kwh' => round($keluar / 450 * 132),
                // Hari terberat sengaja melampaui baku mutu TSS.
                'ph' => $sampel ? 7.1 : null,
                'tss_mgl' => $sampel ? ($mm > 50 ? 268 : 84) : null,
                'fe_mgl' => $sampel ? 2.4 : null,
                'mn_mgl' => $sampel ? 1.1 : null,
            ]);
            $this->setujui($x);
            $n++;
        }

        return $n;
    }

    /* ─────────── geoteknik ─────────── */

    private function geoteknik(): int
    {
        $lereng = $this->baru(GeoLereng::class, [
            'user_id' => $this->pengaju?->getKey(),
            'kode' => 'HW-01', 'nama' => 'Highwall Sisi Timur', 'jenis' => 'highwall',
            'lokasi' => 'Pit Utara', 'litologi' => 'Batulempung berselang batupasir',
            'tinggi_rencana_m' => 48, 'sudut_rencana_deg' => 42,
            'tinggi_jenjang_rencana_m' => 8, 'lebar_berm_rencana_m' => 5,
            'tinggi_aktual_m' => 51, 'sudut_aktual_deg' => 45,
            'tinggi_jenjang_aktual_m' => 8.6, 'lebar_berm_aktual_m' => 4.2,
            'fk_rencana' => 1.3, 'ppa_rencana_persen' => 15,
            'kajian_oleh' => 'Kajian geoteknik contoh',
            'kajian_tanggal' => $this->kini->copy()->subDays(210)->toDateString(),
            'interval_kajian_hari' => 180,
            'ambang_waspada_mm_hari' => 5, 'ambang_siaga_mm_hari' => 15,
            'ambang_awas_mm_hari' => 30, 'status' => 'aktif',
        ]);

        $alat = $this->baru(GeoInstrumen::class, [
            'geo_lereng_id' => $lereng->id, 'kode' => 'PRISM-01',
            'jenis' => 'prisma', 'status' => 'siap', 'elevasi_m' => 78,
            'kalibrasi_terakhir' => $this->kini->copy()->subDays(40)->toDateString(),
        ]);
        $n = 2;

        /* Perpindahan yang LAJUNYA meningkat. Bacaan yang bertambah rata
           hanya membuktikan penjumlahan; yang membuktikan pembacaan
           kebalikan lajunya adalah percepatan. */
        foreach ([0, 3, 7, 13, 22, 36, 58] as $i => $mm) {
            $r = $this->baru(GeoBacaan::class, [
                'user_id' => $this->pengaju?->getKey(),
                'geo_lereng_id' => $lereng->id, 'geo_instrumen_id' => $alat->id,
                'tanggal' => $this->kini->copy()->subDays(7 - $i)->toDateString(),
                'perpindahan_mm' => $mm, 'retakan_mm' => round($mm * 0.4, 1),
                'muka_air_m' => 6.2 - $i * 0.1,
                'curah_hujan_mm' => [4, 0, 12, 0, 38, 96, 22][$i],
                'ada_gejala' => $mm >= 22,
                'gejala' => $mm >= 22 ? 'Retakan tarik memanjang di crest' : null,
            ]);
            $this->setujui($r);
            $n++;
        }

        return $n;
    }

    /* ─────────── lingkungan ─────────── */

    private function lingkungan(): int
    {
        $n = 0;
        $area = [];

        foreach ([['PTK-01', 'timbunan', 12.5, 'penataan',   3],
                  ['PTK-02', 'timbunan', 8.0,  'revegetasi', 2],
                  ['PTK-03', 'bukaan',   5.5,  'selesai',    4]] as [$kode, $jenis, $ha, $tahap, $umur]) {
            $a = $this->baru(LingkunganArea::class, [
                'user_id' => $this->pengaju?->getKey(),
                'kode' => $kode, 'nama' => 'Petak '.$kode, 'jenis' => $jenis,
                'luas_ha' => $ha, 'tahap' => $tahap,
                'tanggal_buka' => $this->kini->copy()->subYears($umur)->toDateString(),
                'tanggal_selesai_tambang' => $this->kini->copy()->subYears($umur - 1)->toDateString(),
                'rencana_selesai_reklamasi' => $this->kini->copy()->addYear()->toDateString(),
                'pohon_rencana' => (int) round($ha * 625),
            ]);
            $area[] = [$a, $tahap, $ha];
            $n++;
        }

        foreach ($area as [$a, $tahap, $ha]) {
            if ($tahap === 'penataan') continue;   // belum ada tanaman

            $r = $this->baru(ReklamasiKemajuan::class, [
                'user_id' => $this->pengaju?->getKey(), 'lingkungan_area_id' => $a->id,
                'tanggal' => $this->kini->copy()->subDays(45)->toDateString(),
                'tahap' => $tahap, 'luas_ha' => $ha,
                'pohon_ditanam' => (int) round($ha * 625 * 0.82),
                'tingkat_tumbuh_persen' => $tahap === 'selesai' ? 88 : 76,
            ]);
            $this->setujui($r);
            $n++;
        }

        $param = [];
        foreach ([['PH', 'Derajat keasaman', 'air', '-',    6.0,  9.0],
                  ['TSS', 'Padatan tersuspensi', 'air', 'mg/L', null, 200.0],
                  ['FE',  'Besi terlarut',   'air', 'mg/L', null, 7.0],
                  ['MN',  'Mangan terlarut', 'air', 'mg/L', null, 4.0],
                  ['DEBU', 'Debu total',     'udara', 'µg/Nm³', null, 230.0]] as [$kode, $nama, $media, $sat, $min, $maks]) {
            $param[$kode] = $this->baru(LingkunganParameter::class, [
                'kode' => $kode, 'nama' => $nama, 'media' => $media, 'satuan' => $sat,
                'batas_min' => $min, 'batas_maks' => $maks,
                'acuan' => 'Baku mutu contoh — sesuaikan dengan izin lingkungan',
                'aktif' => true,
            ]);
            $n++;
        }

        /* Satu hasil uji yang MELAMPAUI baku mutu. Tanpa itu, halaman
           pemantauan tampak sehat sempurna dan tidak menunjukkan apa
           yang terjadi ketika sesuatu terlampaui. */
        foreach ([['PH', 7.2, false], ['TSS', 268.0, true], ['FE', 2.4, false],
                  ['MN', 1.1, false], ['DEBU', 96.0, false]] as [$kode, $nilai, $lewat]) {
            $x = $this->baru(LingkunganPantau::class, [
                'user_id' => $this->pengaju?->getKey(),
                'lingkungan_parameter_id' => $param[$kode]->id,
                'titik' => 'Outlet Settling Pond SP-01',
                'tanggal' => $this->kini->copy()->subDays(8)->toDateString(),
                'nilai' => $nilai, 'laboratorium' => 'Lab terakreditasi contoh',
                'catatan' => $lewat ? 'Diambil sehari setelah hujan lebat.' : null,
            ]);
            $this->setujui($x);
            $n++;
        }

        return $n;
    }

    /* ─────────── peledakan ─────────── */

    private function peledakan(): int
    {
        $titik = $this->baru(LedakTitik::class, [
            'kode' => 'RMH-01', 'nama' => 'Permukiman Sungai Bening',
            'jenis' => 'permukiman', 'lokasi' => '640 m dari batas pit',
            'ppv_ambang_mm_s' => 5, 'acuan_ambang' => 'Izin lingkungan contoh',
            'aktif' => true,
        ]);
        $n = 1;

        foreach ([[6, 40, 60.0], [13, 44, 66.0]] as [$lalu, $lubang, $isi]) {
            $r = $this->baru(LedakRencana::class, [
                'user_id' => $this->pengaju?->getKey(),
                'kode' => 'BL-CTH-'.$lalu, 'lokasi' => 'Pit Utara',
                'tanggal_rencana' => $this->kini->copy()->subDays($lalu)->toDateString(),
                'jenis_batuan' => 'Batupasir sedang', 'faktor_batuan' => 7,
                'diameter_lubang_mm' => 150, 'burden_m' => 4, 'spasi_m' => 5,
                'kedalaman_m' => 11.2, 'subdrill_m' => 1.2, 'stemming_m' => 3.2,
                'tinggi_jenjang_m' => 10, 'jumlah_lubang' => $lubang,
                'pola' => 'selang-seling', 'bahan_peledak' => 'ANFO',
                'kekuatan_relatif' => 100, 'isi_per_lubang_kg' => $isi,
                'isi_per_tunda_kg' => $isi,
            ]);
            $this->setujui($r);
            $n++;

            $h = $this->baru(LedakHasil::class, [
                'user_id' => $this->pengaju?->getKey(), 'ledak_rencana_id' => $r->id,
                'waktu_ledak' => $this->kini->copy()->subDays($lalu)->setTime(12, 5),
                'volume_bcm' => $lubang * 4 * 5 * 10,
                'ada_misfire' => false, 'ada_flyrock' => false,
                'backbreak_m' => 1.4, 'bongkah_persen' => 6,
            ]);
            $this->setujui($h);

            /* Peledakan kedua melampaui ambang PPV di permukiman. Itu
               satu-satunya cara membuktikan bahwa pembandingnya bekerja
               — data yang selalu di bawah ambang tidak membuktikan
               apa pun tentang ambangnya. */
            $this->baru(LedakUkur::class, [
                'ledak_rencana_id' => $r->id, 'ledak_titik_id' => $titik->id,
                'jarak_m' => 640, 'ppv_mm_s' => $lalu === 6 ? 3.1 : 6.4,
                'frekuensi_hz' => 18, 'airblast_db' => $lalu === 6 ? 118 : 127,
                'alat_ukur' => 'Seismograf contoh',
            ]);
            $n += 2;
        }

        return $n;
    }

    /* ─────────── angkutan ─────────── */

    private function angkutan(): int
    {
        $ex = $this->baru(AngkutAlat::class, [
            'kode' => 'EX-01', 'nama' => 'Excavator PC1250', 'kelas' => 'alat-muat',
            'tipe' => 'PC1250-8', 'kapasitas_bucket_m3' => 6.7, 'faktor_isi' => 0.85,
            'aktif' => true,
        ]);
        $truk = $this->baru(AngkutAlat::class, [
            'kode' => 'DT-01', 'nama' => 'Dump Truck HD785', 'kelas' => 'truk',
            'tipe' => 'HD785-7', 'kapasitas_ton' => 91, 'aktif' => true,
        ]);
        $n = 2;

        /* Satu regu kekurangan truk dan satu kelebihan, supaya
           pembacaan match factor punya kedua sisinya. Regu kedua
           antreannya panjang — dan justru itu yang harus TIDAK ikut
           memperbaiki match factor-nya. */
        foreach ([['RG-CTH-01', 4, 2], ['RG-CTH-02', 9, 9]] as [$kode, $jumlah, $antre]) {
            $r = $this->baru(AngkutRegu::class, [
                'user_id' => $this->pengaju?->getKey(), 'alat_muat_id' => $ex->id,
                'kode' => $kode, 'tanggal' => $this->kini->copy()->subDays(3)->toDateString(),
                'shift' => '1', 'pit' => 'Pit Utara', 'tujuan' => 'Disposal Barat',
                'material' => 'overburden', 'jumlah_alat_muat' => 1, 'jumlah_truk' => $jumlah,
                'jarak_km' => 3.4, 'waktu_muat_menit' => 4, 'waktu_angkut_menit' => 8,
                'waktu_tumpah_menit' => 2, 'waktu_kembali_menit' => 6,
                'waktu_antre_menit' => $antre, 'ritase' => 86 * $jumlah / 4,
                'tonase' => round(86 * $jumlah / 4 * 89), 'jam_kerja' => 9, 'jam_delay' => 1,
                'batas_kecepatan_kmh' => 40,
            ]);
            $this->setujui($r);
            $n++;

            /* Satu muatan di atas 120% kapasitas. Kaidah 10/10/20 tidak
               dapat diperiksa pada data yang seluruhnya patuh. */
            foreach ([88, 90, 87, 115] as $i => $ton) {
                $this->baru(AngkutMuatan::class, [
                    'angkut_regu_id' => $r->id, 'angkut_alat_id' => $truk->id,
                    'rit_ke' => $i + 1, 'muatan_ton' => $ton,
                    'waktu_timbang' => $this->kini->copy()->subDays(3)->setTime(8 + $i, 20),
                    'sumber' => 'Jembatan timbang',
                ]);
                $n++;
            }
        }

        return $n;
    }

    /* ─────────── biaya ─────────── */

    private function biaya(): int
    {
        $n = 0;

        /* Serapan per bulan sengaja berbeda antar akun: solar melampaui
           pagunya, ban jauh di bawah. Varians yang seragam tidak
           menunjukkan apakah pemecahannya benar-benar memecah. */
        $akun = [
            ['BB-01', 'Solar alat berat', 'bahan-bakar', 'liter', 14_500_000_000, 1_000_000, 0.098, 0.092],
            ['BN-01', 'Ban OTR',          'ban',         'ban',    3_200_000_000,       160, 0.061, 0.058],
            ['SC-01', 'Suku cadang alat', 'suku-cadang', null,     5_400_000_000,      null, 0.088, null],
            ['UP-01', 'Upah operator',    'upah',        null,     4_800_000_000,      null, 0.083, null],
        ];

        foreach ($akun as [$kode, $nama, $kel, $sat, $pagu, $kuan, $porsiRp, $porsiKuan]) {
            $a = $this->baru(BiayaAkun::class, [
                'kode' => $kode, 'nama' => $nama, 'kelompok' => $kel,
                'jenis' => $kel === 'upah' ? 'tetap' : 'variabel',
                'satuan' => $sat, 'aktif' => true,
            ]);
            $this->baru(BiayaAnggaran::class, [
                'biaya_akun_id' => $a->id, 'tahun' => $this->kini->year,
                'pusat_biaya' => 'penambangan', 'nilai_rp' => $pagu,
                'kuantitas_rencana' => $kuan,
            ]);
            $n += 2;

            foreach (range(1, 6) as $b) {
                // Sedikit naik-turun tiap bulan supaya grafiknya bergerak.
                $goyang = 1 + (($b % 3) - 1) * 0.06;

                $r = $this->baru(BiayaRealisasi::class, [
                    'user_id' => $this->pengaju?->getKey(), 'biaya_akun_id' => $a->id,
                    'tahun' => $this->kini->year, 'bulan' => $b, 'pusat_biaya' => 'penambangan',
                    'nilai_rp' => round($pagu * $porsiRp * $goyang, 2),
                    'kuantitas' => $porsiKuan ? round($kuan * $porsiKuan * $goyang, 3) : null,
                ]);
                $this->setujui($r);
                $n++;
            }
        }

        return $n;
    }

    /* ─────────── izin kerja ─────────── */

    private function izin(): int
    {
        $n = 0;

        foreach ([['o2', 19.5, 23.5, '%'], ['lel', null, 10.0, '%LEL'],
                  ['co', null, 25.0, 'ppm'], ['h2s', null, 10.0, 'ppm']] as [$p, $min, $maks, $sat]) {
            $this->baru(IzinAmbang::class, [
                'parameter' => $p, 'batas_min' => $min, 'batas_maks' => $maks,
                'satuan' => $sat, 'acuan' => 'Prosedur ruang terbatas contoh',
            ]);
            $n++;
        }

        /* Ketujuh jenis izin diberi daftar periksa, bukan dua.
           Jenis yang daftar periksanya kosong dapat diterbitkan tanpa
           satu pun syarat wajib yang menghalanginya — dan itu bukan
           ketidaksempurnaan yang menarik untuk ditunjukkan, hanya
           kekosongan. Isinya contoh; tiap situs menyusun sendiri dari
           prosedurnya. */
        $syarat = [
            'panas' => ['APAR tersedia dan berfungsi',
                        'Fire watcher ditunjuk dan berada di tempat',
                        'Bahan mudah menyala disingkirkan radius 11 m',
                        'Uji gas dilakukan sebelum pekerjaan dimulai'],
            'ruang-terbatas' => ['Isolasi energi terpasang dan terkunci',
                                 'Ventilasi paksa berjalan',
                                 'Petugas jaga lubang masuk ditunjuk',
                                 'Rencana penyelamatan disiapkan dan diuji'],
            'ketinggian' => ['Harness dan lanyard diperiksa layak',
                             'Titik angkur diperiksa dan dicatat',
                             'Area bawah dibarikade'],
            'penggalian' => ['Utilitas bawah tanah dipetakan',
                             'Dinding galian ditopang atau dilandaikan',
                             'Jalan keluar tersedia tiap 7,5 m'],
            'listrik' => ['LOTO terpasang dan diverifikasi nol tegangan',
                          'APD listrik sesuai kelas tegangan',
                          'Pekerja bersertifikat kelistrikan'],
            'pengangkatan' => ['Sertifikat alat angkat masih berlaku',
                               'Rigger dan operator bersertifikat',
                               'Radius ayun dibarikade dan bebas orang',
                               'Beban dan radius diperiksa terhadap tabel muat'],
            'radiografi' => ['Batas radiasi diukur dan dibarikade',
                             'Petugas Proteksi Radiasi hadir',
                             'Sumber dihitung sebelum dan sesudah'],
        ];

        $urut = 0;
        foreach ($syarat as $jenis => $daftar) {
            foreach ($daftar as $teks) {
                $this->baru(IzinSyarat::class, [
                    'jenis' => $jenis, 'urutan' => ++$urut, 'teks' => $teks,
                    'wajib' => true, 'aktif' => true,
                ]);
                $n++;
            }
        }

        /* Dua izin, dan keduanya punya alasan berbeda untuk ada:

           - Izin panas yang MASIH berlaku, lengkap dengan uji gasnya —
             memperlihatkan bentuk izin yang benar.
           - Izin ketinggian yang sudah LEWAT waktunya dan belum
             ditutup. Itu peringatan terpenting modul ini, dan ia hanya
             dapat diperiksa bila datanya benar-benar ada. */
        $n += $this->satuIzin(
            'IK-CTH-01', 'panas', 'Pengelasan chute CV-02', 'Conveyor CV-02',
            $this->kini->copy()->subHours(1), $this->kini->copy()->addHours(6), true, true,
        );

        $n += $this->satuIzin(
            'IK-CTH-02', 'ketinggian', 'Penggantian idler atas', 'Conveyor CV-03',
            $this->kini->copy()->subDay()->setTime(8, 0),
            $this->kini->copy()->subDay()->setTime(17, 0), false, true,
        );

        return $n;
    }

    private function satuIzin(string $nomor, string $jenis, string $uraian, string $lokasi,
                              Carbon $mulai, Carbon $selesai, bool $ujiGas, bool $terbit): int
    {
        $izin = $this->baru(IzinKerja::class, [
            'user_id' => $this->pengaju?->getKey(), 'nomor' => $nomor, 'jenis' => $jenis,
            'lokasi' => $lokasi, 'uraian' => $uraian, 'pelaksana' => 'Regu Mekanik',
            'jumlah_pekerja' => 5, 'pengawas_lapangan' => 'Pengawas Contoh',
            'mulai' => $mulai, 'selesai' => $selesai,
        ]);
        $n = 1;

        foreach (IzinSyarat::withoutGlobalScopes()->where('company_id', $this->c->id)
                     ->where('jenis', $jenis)->orderBy('urutan')->get() as $s) {
            $this->baru(IzinPeriksa::class, [
                'izin_kerja_id' => $izin->id, 'izin_syarat_id' => $s->id,
                'teks' => $s->teks, 'wajib' => true, 'terpenuhi' => true,
            ]);
            $n++;
        }

        if ($ujiGas) {
            $this->baru(IzinGas::class, [
                'izin_kerja_id' => $izin->id,
                'waktu_uji' => $mulai->copy()->subMinutes(20),
                'o2' => 20.8, 'lel' => 0, 'co' => 2, 'h2s' => 0,
                'alat' => 'Multigas detector contoh', 'petugas' => 'Petugas Gas Contoh',
            ]);
            $n++;
        }

        if ($terbit) $this->setujui($izin);

        return $n;
    }

    /* ═══════════ perkakas ═══════════ */

    /**
     * Buat satu baris milik perusahaan contoh ini.
     *
     * company_id ditulis tegas, bukan diserahkan kepada
     * BerpemilikPerusahaan: pemuat ini dijalankan oleh administrator,
     * yang perusahaannya belum tentu — dan biasanya bukan — perusahaan
     * yang sedang dimuati.
     */
    private function baru(string $kelas, array $isi)
    {
        $model = new $kelas;

        if ($model->getConnection()->getSchemaBuilder()
                  ->hasColumn($model->getTable(), 'company_id')) {
            $isi = ['company_id' => $this->c->id] + $isi;
        }

        return $kelas::withoutGlobalScopes()->create($isi);
    }

    /* ─────────── dokumen terkendali ─────────── */

    /**
     * Piramida dokumen: kebijakan di puncak, rekaman di dasar.
     *
     * Nomor revisinya sengaja tidak semuanya 0. Daftar induk dokumen
     * yang setiap barisnya revisi 0 tidak dapat dipakai memeriksa
     * apakah kolom revisi benar-benar terbaca.
     */
    private function dokumen(): int
    {
        $n = 0;

        $daftar = [
            ['Kebijakan',       'K3L-KEB-01', 'Kebijakan Keselamatan dan Kesehatan Kerja', 'Umum',     'berlaku',   2],
            ['Manual',          'K3L-MAN-01', 'Manual Sistem Manajemen Keselamatan',       'Internal', 'berlaku',   1],
            ['Prosedur',        'K3L-PRO-01', 'Prosedur Izin Kerja Khusus',                'Internal', 'berlaku',   3],
            ['Prosedur',        'K3L-PRO-02', 'Prosedur Investigasi Kecelakaan',           'Internal', 'berlaku',   1],
            ['Instruksi Kerja', 'K3L-IK-01',  'Instruksi Kerja Pemeriksaan Sump Harian',   'Internal', 'berlaku',   0],
            ['Instruksi Kerja', 'K3L-IK-02',  'Instruksi Kerja Pengisian Bahan Peledak',   'Rahasia',  'berlaku',   2],
            ['Formulir',        'K3L-FRM-01', 'Formulir Inspeksi Jalan Tambang',           'Umum',     'berlaku',   0],
            ['Rekaman',         'K3L-REK-01', 'Rekaman Pelatihan Tanggap Darurat',         'Internal', 'draft',     0],
            ['Prosedur',        'K3L-PRO-03', 'Prosedur Pengelolaan Limbah B3',            'Internal', 'kadaluarsa',4],
        ];

        foreach ($daftar as [$jenis, $kode, $judul, $klas, $status, $rev]) {
            $terbit = $this->kini->copy()->subMonths(6 + $rev);

            $this->baru(Document::class, [
                'kode'            => $kode,
                'judul'           => $judul,
                'jenis'           => $jenis,
                'klasifikasi'     => $klas,
                'departemen'      => 'HSE',
                'revisi'          => $rev,
                'status'          => $status,
                'tanggal_terbit'  => $terbit->toDateString(),
                'tanggal_berlaku' => $terbit->copy()->addWeek()->toDateString(),

                /* Yang kadaluarsa tanggal tinjaunya memang sudah lewat —
                   itulah yang membuatnya kadaluarsa, dan halaman daftar
                   induk menghitungnya dari sini, bukan dari statusnya. */
                'tanggal_tinjau'  => $status === 'kadaluarsa'
                    ? $this->kini->copy()->subMonth()->toDateString()
                    : $terbit->copy()->addYear()->toDateString(),

                'ringkasan'       => 'Dokumen contoh untuk memeriksa tampilan dan penomoran.',
                'user_id'         => $this->pengaju?->id,
                'disetujui_oleh'  => $status === 'berlaku' ? ($this->peninjau?->name) : null,
            ]);
            $n++;
        }

        return $n;
    }

    /* ─────────── laporan bahaya ─────────── */

    /**
     * Laporan bahaya dengan tiga status sekaligus.
     *
     * Sengaja tidak semuanya Open. Halaman bahaya menghitung waktu
     * penutupan dan jumlah yang tertunda; bila seluruh contohnya
     * berstatus sama, kedua angka itu tidak pernah terbukti benar.
     */
    private function bahaya(): int
    {
        $n = 0;

        $daftar = [
            ['Unsafe Condition', 'Tinggi', 'Open',        'Tanggul jalan hauling KM 4 tergerus hujan',        'Rekayasa'],
            ['Unsafe Action',    'Sedang', 'In Progress', 'Operator tidak memakai sabuk pengaman di kabin',   'Administratif'],
            ['Near Miss',        'Tinggi', 'Closed',      'Batu jatuh dari bak dump truck di simpang timbang','Rekayasa'],
            ['Unsafe Condition', 'Rendah', 'Closed',      'Lampu penerangan front loading mati satu titik',   'Rekayasa'],
            ['Bahaya Lingkungan','Sedang', 'Open',        'Ceceran oli di area workshop belum ditampung',     'Administratif'],
            ['Unsafe Action & Unsafe Condition', 'Tinggi', 'In Progress',
             'Pengisian bahan bakar dilakukan saat mesin hidup',                                              'Eliminasi'],
        ];

        foreach ($daftar as $i => [$kategori, $risiko, $status, $uraian, $hirarki]) {
            $tanggal = $this->kini->copy()->subDays(3 + $i * 5);

            $b = $this->baru(HazardReport::class, [
                'kode'          => 'HZ-'.$tanggal->format('ym').'-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'user_id'       => $this->pengaju?->id,
                'pelapor_nama'  => $this->pengaju?->name ?? 'Pengawas Lapangan',
                'pelapor_departemen' => 'Produksi',
                'pelapor_jabatan'    => 'Pengawas',
                'tanggal'       => $tanggal->toDateString(),
                'waktu'         => '09:'.str_pad((string) (10 + $i * 7), 2, '0', STR_PAD_LEFT),
                'lokasi'        => ['Pit Utara', 'Jalan Hauling KM 4', 'Workshop', 'Disposal Selatan'][$i % 4],
                'risiko'        => $risiko,
                'kategori'      => $kategori,
                'deskripsi'     => $uraian,
                'hirarki'       => $hirarki,
                'rekomendasi'   => 'Perbaikan dijadwalkan dan diawasi pengawas area.',
                'status'        => $status,
            ]);

            /* Yang sudah ditutup harus punya penutup dan waktunya.
               Tanpa keduanya, halaman menghitung waktu penutupan dari
               nilai kosong dan memulangkan angka yang tidak masuk akal. */
            if ($status === 'Closed') {
                $b->forceFill([
                    'closed_by'         => $this->peninjau?->id ?? $this->pengaju?->id,
                    'closed_at'         => $tanggal->copy()->addDays(4),
                    'catatan_penutupan' => 'Perbaikan selesai dan diperiksa ulang di lapangan.',
                ])->saveQuietly();
            }

            $n++;
        }

        return $n;
    }

    /* ─────────── inspeksi ─────────── */

    /**
     * Inspeksi tanpa template.
     *
     * `template_id` sengaja dibiarkan kosong: tabel template TIDAK
     * punya kolom perusahaan, jadi ia milik bersama seluruh pemasangan.
     * Membuat template dari sini berarti membuat baris yang tidak dapat
     * dibuang oleh penghapus data contoh — penghapus itu bekerja
     * dengan menyebut company_id, dan baris tanpa perusahaan akan
     * tertinggal menumpuk setiap kali tombolnya ditekan.
     */
    private function inspeksi(): int
    {
        $n = 0;

        $daftar = [
            ['Harian',   'Inspeksi Jalan Angkut Pagi',     'Jalan Hauling KM 0–6', 'Selesai'],
            ['Harian',   'Inspeksi Front Loading',         'Pit Utara',            'Selesai'],
            ['Mingguan', 'Inspeksi Tanggul dan Drainase',  'Pit Selatan',          'Berjalan'],
            ['Bulanan',  'Inspeksi Gudang Bahan Peledak',  'Gudang Handak',        'Berjalan'],
            ['Khusus',   'Inspeksi Pasca Hujan Deras',     'Disposal Selatan',     'Selesai'],
        ];

        foreach ($daftar as $i => [$jenis, $judul, $lokasi, $status]) {
            $this->baru(Inspection::class, [
                'kode'        => 'INS-'.$this->kini->format('ym').'-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'template_id' => null,
                'user_id'     => $this->pengaju?->id,
                'judul'       => $judul,
                'jenis'       => $jenis,
                'lokasi'      => $lokasi,
                'tanggal'     => $this->kini->copy()->subDays($i * 3)->toDateString(),
                'pelaksana'   => $this->pengaju?->name ?? 'Pengawas Lapangan',
                'status'      => $status,
                'catatan'     => 'Inspeksi contoh untuk memeriksa tampilan dan rekapitulasi.',
            ]);
            $n++;
        }

        return $n;
    }

    /* ─────────── kelayakan operasi ─────────── */

    private function ko(): int
    {
        $n = 0;

        $daftar = [
            ['KO-SAR-001', 'Jembatan Timbang 60 Ton',    'Sarana',    'Tinggi', 'Aktif',     2],
            ['KO-PRA-001', 'Tanggul Kolam Pengendap 3',  'Prasarana', 'Tinggi', 'Aktif',     1],
            ['KO-INS-001', 'Instalasi Listrik Workshop', 'Instalasi', 'Sedang', 'Aktif',     3],
            ['KO-PER-001', 'Crane Workshop 10 Ton',      'Peralatan', 'Tinggi', 'Standby',   1],
            ['KO-PER-002', 'Genset 500 kVA',             'Peralatan', 'Sedang', 'Breakdown', 2],
            ['KO-SAR-002', 'Tangki Bahan Bakar 50 kL',   'Sarana',    'Tinggi', 'Aktif',     2],
        ];

        foreach ($daftar as $i => [$kode, $nama, $kategori, $kritis, $operasi, $interval]) {
            $sertifikasi = $this->kini->copy()->subMonths(6 + $i);

            $this->baru(KoObject::class, [
                'kode'            => $kode,
                'nama'            => $nama,
                'kategori'        => $kategori,
                'lokasi'          => ['Area Timbang', 'Pit Selatan', 'Workshop', 'Fuel Station'][$i % 4],
                'kritikalitas'    => $kritis,
                'status_operasi'  => $operasi,
                'tgl_sertifikasi' => $sertifikasi->toDateString(),
                'interval_tahun'  => $interval,
                'no_sertifikat'   => 'SER/'.$sertifikasi->format('Y').'/'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'lembaga_uji'     => 'Balai Pengujian Peralatan',
                'pm_terakhir'     => $this->kini->copy()->subMonths(2)->toDateString(),
                'pm_berikutnya'   => $this->kini->copy()->addMonth()->toDateString(),
            ]);
            $n++;
        }

        return $n;
    }

    /* ─────────── alat energi ─────────── */

    private function energiAlat(): int
    {
        $n = 0;

        /* Kategorinya adalah KUNCI dari Energi::KATEGORI, bukan
           labelnya — halaman rekap memvalidasi dengan
           Rule::in(array_keys(...)), dan label yang tersimpan di sini
           akan lolos penyimpanan lalu hilang dari setiap
           pengelompokan tanpa menimbulkan galat. */
        $daftar = [
            ['EQ-HD-001', 'Dump Truck HD465-7',   'Komatsu', 'hauling',   552, 55.0],
            ['EQ-HD-002', 'Dump Truck HD465-7',   'Komatsu', 'hauling',   552, 55.0],
            ['EQ-EX-001', 'Excavator PC2000',     'Komatsu', 'excavator', 960, null],
            ['EQ-DZ-001', 'Bulldozer D375A',      'Komatsu', 'dozer',     610, null],
            ['EQ-GR-001', 'Motor Grader GD825',   'Komatsu', 'support',   280, null],
        ];

        $kategoriSah = array_keys(Energi::KATEGORI);

        foreach ($daftar as [$kode, $nama, $merek, $kategori, $hp, $payload]) {
            $this->baru(EnergyEquipment::class, [
                'kode'        => $kode,
                'nama'        => $nama,
                'merek'       => $merek,

                /* Kategori yang tidak dikenal membuat halaman rekap
                   memulangkan kelompok kosong tanpa galat. Bila daftar
                   kuncinya berubah, dipakai yang pertama supaya barisnya
                   tetap terhitung, bukan menghilang diam-diam. */
                'kategori'    => in_array($kategori, $kategoriSah, true)
                    ? $kategori
                    : ($kategoriSah[0] ?? $kategori),

                'daya_hp'     => $hp,
                'payload_ton' => $payload,
                'aktif'       => true,
            ]);
            $n++;
        }

        return $n;
    }

    /* ─────────── SMKP ─────────── */

    /**
     * Satu audit SMKP per tahun berjalan dan satu tahun sebelumnya.
     *
     * Dua, bukan satu: halaman SMKP membandingkan nilai antar tahun,
     * dan perbandingan dengan satu titik data tidak pernah salah — juga
     * tidak pernah benar.
     */
    private function smkp(): int
    {
        $n = 0;

        foreach ([['selesai', 1], ['berjalan', 0]] as [$status, $mundur]) {
            $tahun = $this->kini->year - $mundur;

            $this->baru(SmkpAudit::class, [
                'tahun'           => $tahun,
                'judul'           => 'Audit Internal SMKP Minerba '.$tahun,
                'status'          => $status,
                'tahap'           => $status === 'selesai' ? 3 : 1,
                'tanggal_mulai'   => Carbon::create($tahun, 3, 1)->toDateString(),
                'tanggal_selesai' => $status === 'selesai'
                    ? Carbon::create($tahun, 3, 14)->toDateString()
                    : null,
                'ketua_auditor'   => $this->peninjau?->name ?? 'Ketua Auditor Internal',
                'user_id'         => $this->pengaju?->id,
            ]);
            $n++;
        }

        return $n;
    }

    /* ─────────── prosedur & evaluasi SOP ─────────── */

    /**
     * Prosedur berikut satu evaluasi SOP beserta soalnya.
     *
     * Soalnya diisi sungguhan, bukan sekadar satu baris judul. Halaman
     * evaluasi menghitung nilai lulus dari jumlah soal, dan evaluasi
     * tanpa soal memulangkan pembagian dengan nol — bentuk kegagalan
     * yang hanya muncul ketika ada yang benar-benar mengerjakannya,
     * yaitu justru bukan saat diperiksa.
     */
    private function prosedur(): int
    {
        $n = 0;

        $daftar = [
            ['SOP-01', 'Penanganan Bahan Bakar di Area Tambang', 'Operasional'],
            ['SOP-02', 'Pengoperasian Alat Angkut di Jalan Hauling', 'Operasional'],
            ['SOP-03', 'Tanggap Darurat Kebakaran Workshop', 'Keselamatan'],
            ['SOP-04', 'Pemeriksaan Harian Kolam Pengendap', 'Lingkungan'],
        ];

        foreach ($daftar as $i => [$kode, $judul, $kategori]) {
            $p = $this->baru(Procedure::class, [
                'code'        => $kode,
                'title'       => $judul,
                'category'    => $kategori,
                'description' => 'Prosedur contoh untuk memeriksa tampilan dan penomoran.',
                'position'    => $i + 1,
            ]);
            $n++;

            // Satu evaluasi pada dua prosedur pertama saja — supaya
            // halaman daftar memperlihatkan keduanya: yang punya
            // evaluasi dan yang belum.
            if ($i > 1) continue;

            $ev = SopEvaluation::withoutGlobalScopes()->create([
                'procedure_id'     => $p->id,
                'title'            => 'Evaluasi '.$judul,
                'description'      => 'Evaluasi contoh.',
                'passing_score'    => 70,
                'duration_minutes' => 15,
                'is_active'        => true,
                'position'         => 1,
            ]);
            $n++;

            $soal = [
                ['Apa langkah pertama sebelum mengisi bahan bakar?',
                 ['Matikan mesin', 'Nyalakan mesin', 'Biarkan idle', 'Panggil rekan'], 0],
                ['Berapa jarak aman minimum dari sumber api?',
                 ['1 meter', '5 meter', '10 meter', 'Tidak diatur'], 2],
                ['Siapa yang berwenang menghentikan pekerjaan tidak aman?',
                 ['Hanya KTT', 'Hanya pengawas', 'Setiap pekerja', 'Hanya kontraktor'], 2],
            ];

            foreach ($soal as $k => [$tanya, $pilihan, $benar]) {
                SopEvaluationQuestion::withoutGlobalScopes()->create([
                    'evaluation_id' => $ev->id,
                    'question'      => $tanya,
                    'options'       => $pilihan,
                    'correct_index' => $benar,
                    'order_index'   => $k + 1,
                ]);
                $n++;
            }

            /* Satu percobaan pengerjaan, supaya halaman hasil dan
               rekapitulasi kelulusan punya sesuatu untuk dihitung. */
            if ($this->pengaju) {
                SopEvaluationAttempt::withoutGlobalScopes()->create([
                    'user_id'       => $this->pengaju->id,
                    'evaluation_id' => $ev->id,
                    'procedure_id'  => $p->id,
                    'score'         => 67,
                    'total'         => count($soal),
                    'correct'       => 2,
                    'passed'        => false,
                    'answers'       => [0, 1, 2],
                ]);
                $n++;
            }
        }

        return $n;
    }

    /* ─────────── berita ─────────── */

    /**
     * Pengumuman, sebagian sudah terbit dan satu masih terjadwal.
     *
     * Yang terjadwal sengaja ada: halaman depan menyaring berdasarkan
     * tanggal terbit, dan penyaring itu tidak pernah terbukti bekerja
     * bila seluruh contohnya sudah lewat tanggalnya.
     */
    private function berita(): int
    {
        $n = 0;

        $daftar = [
            ['Sosialisasi Prosedur Izin Kerja Khusus yang Baru', -21],
            ['Hasil Audit Internal SMKP Triwulan Ini', -12],
            ['Jadwal Pemeriksaan Kesehatan Berkala Pekerja Shift Malam', -4],
            ['Simulasi Tanggap Darurat Bulan Depan', 9],
        ];

        foreach ($daftar as [$judul, $geser]) {
            $this->baru(News::class, [
                'title'        => $judul,
                'content'      => "Pengumuman contoh untuk memeriksa tampilan halaman berita.\n\n"
                    ."Isinya sengaja beberapa paragraf agar potongan ringkasnya pada daftar "
                    ."benar-benar terpotong, bukan tampak utuh karena kebetulan pendek.",
                'published_at' => $this->kini->copy()->addDays($geser)->toDateString(),
            ]);
            $n++;
        }

        return $n;
    }

    /* ─────────── LMS ─────────── */

    /**
     * Satu kursus utuh: modul, materi, kuis bersoal, pendaftaran,
     * percobaan, sertifikat, dan evaluasi pasca-pelatihan.
     *
     * Dibuat UTUH, bukan sekadar satu baris kursus, sebab yang hendak
     * diperiksa adalah alurnya: mendaftar → belajar → mengerjakan kuis
     * → lulus → menerima sertifikat → dievaluasi. Kursus tanpa
     * pendaftaran hanya membuktikan halaman daftarnya tergambar.
     *
     * Kursus, kuis, dan template inspeksi menyebut `demo_company_id`
     * karena ketiganya pustaka bersama dan tidak punya company_id yang
     * dapat dipakai penghapus. Kolom itu TIDAK menyaring pembacaan —
     * pustakanya tetap terlihat semua orang — ia hanya menjawab
     * "dibuang bersama perusahaan contoh mana". Sertifikat punya
     * company_id sendiri dan mengikuti jalur biasa.
     */
    private function lms(): int
    {
        if (!$this->pengaju) {
            $this->catatan[] = 'Data contoh LMS dilewati: perusahaan ini belum punya pengguna '
                .'yang dapat didaftarkan sebagai peserta.';

            return 0;
        }

        $n = 0;

        $kursus = Course::withoutGlobalScopes()->create([
            'title'            => 'Keselamatan Kerja Tambang Dasar',
            'description'      => 'Kursus contoh: pengenalan bahaya, APD, dan tanggap darurat.',
            'category'         => 'Keselamatan',
            'cert_template'    => 'default',
            'auto_certificate' => true,
            'require_code'     => false,
            'require_evaluation' => false,
            'demo_company_id'  => $this->c->id,
        ]);
        $n++;

        foreach ([
            ['Pengenalan Bahaya Tambang', 'Jenis bahaya di area tambang terbuka.'],
            ['Alat Pelindung Diri',       'Pemilihan dan pemakaian APD sesuai pekerjaan.'],
            ['Tanggap Darurat',           'Langkah pertama saat kecelakaan dan kebakaran.'],
        ] as $i => [$judul, $uraian]) {
            $modul = Module::withoutGlobalScopes()->create([
                'course_id' => $kursus->id, 'title' => $judul,
                'description' => $uraian, 'order_index' => $i + 1,
            ]);
            $n++;

            Material::withoutGlobalScopes()->create([
                'course_id'   => $kursus->id,
                'module_id'   => $modul->id,
                'title'       => 'Materi '.$judul,
                'description' => 'Materi contoh untuk memeriksa tampilan halaman belajar.',
                'type'        => 'teks',
                'content'     => 'Isi materi contoh. Beberapa paragraf agar halaman belajar '
                    ."benar-benar punya sesuatu untuk digulir.\n\n"
                    .'Bahaya di area tambang tidak selalu terlihat; yang paling sering '
                    .'melukai justru yang sudah biasa dilewati setiap hari.',
                'order_index' => 1,
            ]);
            $n++;
        }

        $kuis = Quiz::withoutGlobalScopes()->create([
            'course_id' => $kursus->id, 'title' => 'Kuis Keselamatan Kerja Tambang Dasar',
            'pass_score' => 70, 'demo_company_id' => $this->c->id,
        ]);
        $n++;

        $soal = [
            ['Apa yang pertama dilakukan saat melihat rekan tertimpa material?',
             ['Menolong sendiri', 'Amankan lokasi lalu panggil bantuan', 'Memotret kejadian', 'Melapor besok'], 1],
            ['APD wajib di area front loading adalah?',
             ['Helm dan rompi saja', 'Helm, rompi, sepatu, dan kacamata', 'Sepatu saja', 'Tidak wajib'], 1],
            ['Siapa yang berwenang menghentikan pekerjaan tidak aman?',
             ['Hanya KTT', 'Hanya pengawas', 'Setiap pekerja', 'Hanya kontraktor'], 2],
        ];

        foreach ($soal as $i => [$tanya, $pilihan, $benar]) {
            QuizQuestion::withoutGlobalScopes()->create([
                'quiz_id' => $kuis->id, 'question' => $tanya, 'options' => $pilihan,
                'correct_index' => $benar, 'order_index' => $i + 1,
            ]);
            $n++;
        }

        /* Peserta: pengaju dan peninjau, supaya daftar pesertanya tidak
           berisi satu nama saja dan rekapitulasinya punya sebaran. */
        $peserta = array_values(array_filter([$this->pengaju, $this->peninjau]));

        foreach ($peserta as $i => $orang) {
            $lulus = $i === 0;

            Enrollment::withoutGlobalScopes()->create([
                'user_id' => $orang->id, 'course_id' => $kursus->id,
                'progress' => $lulus ? 100 : 60,
                'status'   => $lulus ? 'completed' : 'in_progress',
            ]);
            $n++;

            QuizAttempt::withoutGlobalScopes()->create([
                'user_id' => $orang->id, 'quiz_id' => $kuis->id,
                'score' => $lulus ? 100 : 67, 'passed' => $lulus,
            ]);
            $n++;

            if (!$lulus) continue;

            /* Hanya yang lulus yang bersertifikat — kalau semuanya
               bersertifikat, aturan "sertifikat menyusul kelulusan"
               tidak pernah terbukti berlaku. */
            $this->baru(Certificate::class, [
                'user_id'           => $orang->id,
                'course_id'         => $kursus->id,
                'certificate_number' => 'SERT/'.$this->kini->format('Y').'/'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'recipient_name'    => $orang->name,
                'course_title'      => $kursus->title,
                'final_score'       => 100,
                'issued_at'         => $this->kini->copy()->subDays(7),
                'verification_code' => strtoupper(substr(md5($kursus->id.'-'.$orang->id), 0, 10)),
                'template'          => 'default',
            ]);
            $n++;

            PostTrainingEvaluation::withoutGlobalScopes()->create([
                'user_id'         => $orang->id,
                'course_id'       => $kursus->id,
                'trainer_id'      => $this->peninjau?->id,
                'trainer_name'    => $this->peninjau?->name,
                'knowledge_score' => 85, 'skill_score' => 80,
                'attitude_score'  => 90, 'safety_score' => 88,
                'overall_score'   => 86,
                'recommendation'  => 'Layak bekerja mandiri dengan pengawasan berkala.',
                'strengths'       => 'Disiplin memakai APD dan aktif melaporkan bahaya.',
                'improvements'    => 'Perlu latihan tambahan pada prosedur tanggap darurat.',
            ]);
            $n++;
        }

        /* Template inspeksi — dipakai modul Inspeksi, dan ditandai demo
           dengan alasan yang sama seperti kursus. */
        foreach ([
            ['Inspeksi Harian Jalan Angkut', 'Harian', 'Jalan tambang'],
            ['Inspeksi Mingguan Alat Berat', 'Mingguan', 'Peralatan'],
        ] as [$nama, $jenis, $kategori]) {
            InspectionTemplate::withoutGlobalScopes()->create([
                'nama' => $nama, 'jenis' => $jenis, 'kategori' => $kategori,
                'deskripsi' => 'Template contoh untuk memeriksa alur inspeksi.',
                'is_active' => true, 'demo_company_id' => $this->c->id,
            ]);
            $n++;
        }

        return $n;
    }

    /**
     * Bawa satu baris melewati alur tinjauannya.
     *
     * Data contoh yang seluruhnya berhenti sebagai draf tidak masuk satu
     * pun hitungan KPI — halamannya terisi, tetapi seluruh indikatornya
     * nol, dan itu terbaca sebagai hitungan yang rusak.
     *
     * Kegagalannya dicatat, bukan ditelan. Pemuat yang diam-diam
     * meninggalkan seluruh barisnya sebagai draf adalah persis bentuk
     * kesalahan yang paling sulit ditemukan dari halaman jadinya.
     */
    private function setujui($baris): void
    {
        if (!$this->pengaju || !Alur::peninjau($this->peninjau)) return;

        try {
            $baris->ajukan($this->pengaju);
            $baris->setujui($this->peninjau);
        } catch (\Throwable $e) {
            $kunci = class_basename($baris).': '.$e->getMessage();

            if (!in_array($kunci, $this->catatan, true)) $this->catatan[] = $kunci;
        }
    }
}
