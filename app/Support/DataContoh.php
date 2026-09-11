<?php

namespace App\Support;

use App\Models\Investigasi\{
    AkarMasalah as InvAkar, Analisis as InvAnalisis, Bukti as InvBukti,
    Insiden as InvInsiden, InsidenOrang as InvInsidenOrang, Investigasi as InvInvestigasi,
    Jejak as InvJejak, Kronologi as InvKronologi, Lokasi as InvLokasi,
    Pembelajaran as InvPembelajaran, ScatPilihan as InvScatPilihan, Temuan as InvTemuan,
    Tim as InvTim, Tindakan as InvTindakan, Wawancara as InvWawancara,
    WawancaraJawaban as InvWawancaraJawaban
};
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Pembelian\{Item as ItemBeli, Lisensi as LisensiBeli,
    Pembayaran as PembayaranBeli, Pesanan as PesananBeli, Produk as ProdukBeli};
use App\Support\Investigasi\{MasterInvestigasi, MesinScat, NomorInvestigasi, Triase as InvTriase};
use App\Models\Miners\{Alur as MnrAlur, Blok as MnrBlok, Departemen as MnrDepartemen,
    HasilMcu as MnrHasilMcu, Induksi as MnrInduksi, InduksiOrang as MnrInduksiOrang,
    Jabatan as MnrJabatan, JenisUnit as MnrJenisUnit, KategoriPermit as MnrKategoriPermit,
    Kendaraan as MnrKendaraan, Kompetensi as MnrKompetensi, Mcu as MnrMcu, McuOrang as MnrMcuOrang, McuRujukan as MnrMcuRujukan,
    Pekerja as MnrPekerja, Permit as MnrPermit, PermitBerkas as MnrPermitBerkas, Pjo as MnrPjo,
    Simper as MnrSimper, SimperAjuan as MnrSimperAjuan, SimperAjuanUnit as MnrSimperAjuanUnit,
    SimperUnit as MnrSimperUnit, SubBlok as MnrSubBlok, Subkontraktor as MnrSubkontraktor,
    TipePermit as MnrTipePermit};
use App\Models\Pjp\{
    Evaluasi as PjpEvaluasi, Laporan as PjpLaporan, Pjp,
    SmkpItem as PjpSmkpItem, SmkpJawaban as PjpSmkpJawaban, SmkpKategori as PjpSmkpKategori,
};
use App\Support\Miners\Acuan;
use App\Support\Miners\MasterMiners;
use App\Support\Pjp\DaftarPeriksaSmkp;
use App\Support\Pembelian;
use App\Models\{AngkutAlat, AngkutMuatan, AngkutRegu, BiayaAkun, BiayaAnggaran, BiayaRealisasi,
                Company, Document, DocumentIso, DocumentRevision, EnergyEquipment,
                EnergyFuelLog, GeoBacaan, GeoInstrumen, GeoLereng, GudangBarang, GudangLokasi,
                GudangMutasi, HazardReport, Inspection, InspectionInspector, InspectionItem,
                IzinAmbang, IzinGas, IzinKerja, IzinPeriksa, IzinSyarat,
                KoAction, KoInspection, KoObject, KoReview, KoSafeguard,
                KoUjiKelayakan, KoUnitMaster,
                LedakHasil, LedakRencana, LedakTitik, LedakUkur, LingkunganArea,
                LingkunganPantau, LingkunganParameter, MineOperationalRecord,
                MineOperationalTarget, News, Procedure, ReklamasiKemajuan, SmkpAttendee, SmkpAudit, SmkpBukti, SmkpOfi,
                SmkpFinding, SopEvaluation, SopEvaluationAttempt,
                SopEvaluationQuestion,
                Certificate, Course, Enrollment, InspectionTemplate, InspectionTemplateItem,
                Material, Module, ModuleCompletion,
                PostTrainingEvaluation, Quiz, QuizAttempt, QuizQuestion,
                TindakLanjut, User, WaterLog, WaterSump, WaterSumpPump, WorkOrder, WorkOrderPart,
                EnergyBaseline, EnergyFuelRecon, EnergyOpportunity, EnergyOtherLog,
                EnergyPowerLog, EnergyProduction,
                KoPersonnel, KompetensiJenis, McuPengajuan, MineMapLayer,
                MinerbaConservationRecord, Note,
                Paspor, PasporInduksi, PasporKartu, PasporKartuUnit, PasporMcu, PasporSertifikat,
                Percakapan, PersetujuanParaf,
                Pesan, Signatory, TpkkpAssessment, TpkkpPengujian, TpkkpResponse, InduksiPengajuan};
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

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
        /* Bukti butir dan peluang perbaikan disebut SEBELUM auditnya:
           penghapusan berkaskade dikerjakan basis data dan tidak ikut
           terhitung pemanggilnya, sehingga jumlah "dibuang" tidak lagi
           sebanding dengan jumlah "dibuat" — dan pemeriksaan penumpukan
           bersandar pada perbandingan itu. */
        SmkpBukti::class, SmkpOfi::class,
        SmkpFinding::class, SmkpAttendee::class, SmkpAudit::class,
        DocumentRevision::class, DocumentIso::class, Document::class,
        HazardReport::class,

        /* Energi. Catatan pemakaian mendahului registri alatnya;
           EnergyFuelLog menunjuk equipment_id, sisanya berdiri sendiri
           dengan company_id masing-masing. */
        EnergyFuelLog::class, EnergyEquipment::class,
        EnergyProduction::class, EnergyPowerLog::class, EnergyOtherLog::class,
        EnergyFuelRecon::class, EnergyBaseline::class, EnergyOpportunity::class,

        /* KO. Suku cadang mendahului perintah kerjanya, dan
           KoInspection menunjuk ko_safeguard_id sekaligus
           ko_personnel_id — jadi keduanya dibuang sesudahnya. */
        WorkOrderPart::class, WorkOrder::class,
        KoInspection::class, KoAction::class, KoReview::class,
        KoSafeguard::class, KoPersonnel::class,

        /* Uji kelayakan menggantung pada objeknya, jadi dibuang lebih
           dulu. Master jenis unit TIDAK dibuang — ia acuan bersama
           seperti jenis kompetensi, bukan data contoh. */
        KoUjiKelayakan::class,
        KoObject::class,

        MinerbaConservationRecord::class,
        MineMapLayer::class,
        Signatory::class,

        /* Pesan mendahului percakapannya, dan peserta dilepas lewat
           relasi pivot — bukan model tersendiri. */
        Pesan::class, Percakapan::class,

        TpkkpResponse::class, TpkkpPengujian::class, TpkkpAssessment::class,
        Note::class,

        /* Authority. Keempat anaknya menggantung pada paspor; jenis
           kompetensi TIDAK dibuang — ia master nasional milik bersama,
           bukan data contoh.

           Surat pengajuan MCU dibuang SESUDAH hasilnya, sebab hasilnya
           menunjuk suratnya. Ia menyebut perusahaan sendiri, jadi tidak
           ikut terbawa penghapusan paspor — dan itu persis sebabnya ia
           harus disebut di sini: yang tidak disebut tidak menimbulkan
           galat, hanya dua baris yang bertambah tiap kali tombol muat
           ulang ditekan. */
        /* Paraf dibuang PALING DULU: ia menunjuk kartu dan pengajuan,
           dan penyaringnya bekerja lewat keduanya. Dibalik urutannya,
           penyaring itu tidak menemukan apa pun karena yang ditunjuknya
           sudah hilang — dan barisnya tertinggal tanpa galat. */
        PersetujuanParaf::class,

        /* Pembelian: anak lebih dulu. Lisensi dan pembayaran menunjuk
           pesanan, item menunjuk pesanan dan produk. */
        LisensiBeli::class, PembayaranBeli::class, ItemBeli::class,
        PesananBeli::class, ProdukBeli::class,
        PasporSertifikat::class, PasporMcu::class,
        PasporKartuUnit::class, PasporKartu::class,
        PasporInduksi::class, Paspor::class, McuPengajuan::class, InduksiPengajuan::class,

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
        ModuleCompletion::class, Material::class, Module::class, Course::class,
        InspectionTemplateItem::class, InspectionTemplate::class,

        /* Investigasi. Seluruh anaknya disebut satu per satu meski
           kunci asingnya sudah berkaskade, dan itu bukan pengulangan
           yang sia-sia: penghapusan berkaskade dikerjakan basis data
           dan TIDAK IKUT TERHITUNG oleh pemanggilnya, sehingga jumlah
           "dibuang" tidak lagi sebanding dengan jumlah "dibuat" — dan
           pemeriksaan penumpukan bersandar pada perbandingan itu.
           Kaskade juga bergantung pada penegakan kunci asing, yang
           tidak sama di setiap mesin.

           Urutannya anak lebih dulu. `inv_akar_bukti` tidak disebut:
           ia pivot tanpa model, terbuang bersama akar masalahnya, dan
           karena tidak pernah dihitung sebagai "dibuat" ia tidak
           membuat kedua sisi timpang.

           Master investigasi TIDAK dibuang. Matriks risiko, kamus SCAT,
           dan klasifikasi menurut Kepmen adalah kerangka regulasi yang
           berlaku sama bagi setiap perusahaan — membuangnya bersama
           data contoh satu perusahaan akan melumpuhkan triase seluruh
           perusahaan lain pada pemasangan yang sama. */
        InvWawancaraJawaban::class, InvWawancara::class,
        InvScatPilihan::class, InvAnalisis::class,
        InvTindakan::class, InvTemuan::class, InvAkar::class,
        InvPembelajaran::class, InvKronologi::class, InvBukti::class, InvTim::class,
        InvJejak::class, InvInvestigasi::class,
        InvInsidenOrang::class, InvInsiden::class, InvLokasi::class,

        /* PJP. Anaknya disebut satu per satu dengan alasan yang sama
           seperti Investigasi di atas: penghapusan berkaskade tidak ikut
           terhitung, dan pemeriksaan penumpukan bersandar pada
           perbandingan "dibuat" lawan "dibuang".

           Daftar periksanya — pjp_smkp_kategori dan pjp_smkp_item —
           TIDAK dibuang. Isinya lampiran Kepdirjen 185/2019 yang berlaku
           sama bagi setiap perusahaan, dan membuangnya bersama data
           contoh satu perusahaan akan mengosongkan daftar periksa
           seluruh perusahaan lain pada pemasangan yang sama — beserta
           jawaban mereka, yang menunjuk butirnya lewat kunci asing
           berkaskade. */
        PjpSmkpJawaban::class, PjpEvaluasi::class, PjpLaporan::class, Pjp::class,

        /* Miners. Urutannya ANAK LEBIH DULU, dan rantainya panjang:
           unit pengajuan → pengajuan → unit SIMPER → SIMPER → lampiran
           → permit → induksi → MCU → pekerja. Satu tabel yang lepas
           urutan akan tertahan kunci asingnya dan menghentikan seluruh
           pembuangan.

           `mnr_alur` disebut TERSENDIRI dan dibuang paling dahulu. Ia
           tidak punya kunci asing ke dokumennya — relasinya polimorfik
           lewat sepasang kolom (`dokumen`, `dokumen_id`) — sehingga
           tidak ada kaskade yang membuangnya. Dilewatkan, tiap
           penekanan tombol meninggalkan satu rombongan langkah
           persetujuan yatim yang menunjuk ke dokumen yang sudah tidak
           ada, dan jumlahnya bertambah tiap kali.

           Daftar awal bersamanya — departemen, jabatan, blok, golongan
           unit, jenis permit, hasil MCU — TIDAK dibuang, dengan alasan
           yang sama seperti master Investigasi dan PJP di atas: isinya
           acuan SOP yang berlaku sama bagi setiap tambang, dan
           membuangnya bersama data contoh satu perusahaan akan memutus
           rujukan kartu yang sudah terbit di perusahaan lain.

           Yang milik perusahaan sendiri — subkontraktor, PJO, sub-blok
           — memang ikut terbuang, dan memang harus. */
        MnrAlur::class,
        MnrSimperAjuanUnit::class, MnrSimperAjuan::class,
        MnrSimperUnit::class, MnrSimper::class,
        MnrPermitBerkas::class, MnrPermit::class,
        MnrInduksiOrang::class, MnrInduksi::class,
        MnrMcuRujukan::class, MnrMcuOrang::class, MnrMcu::class,
        MnrKompetensi::class,
        MnrPekerja::class, MnrSubBlok::class, MnrPjo::class, MnrSubkontraktor::class,
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
            $q = self::kueri($kelas, $this->c);

            /* Model bertanda hapus-lunak DIBUANG SUNGGUHAN di sini.
               `delete()` biasa hanya mengisi deleted_at, sehingga
               barisnya tetap ada — dan dua akibatnya sama-sama sunyi:
               kaskade kunci asing tidak pernah berjalan sehingga anaknya
               tertinggal sebagai baris yatim, dan tombol muat ulang
               menambah satu rombongan baru tiap kali ditekan tanpa
               membuang yang lama.

               Data contoh memang tidak punya alasan disimpan di kotak
               sampah: ia dibuat untuk dibuang. */
            $n += in_array(SoftDeletes::class, class_uses_recursive($kelas), true)
                ? $q->forceDelete()
                : $q->delete();
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
    /** Id seluruh insiden satu perusahaan — subkueri, bukan daftar id. */
    private static function idInsiden(Company $c)
    {
        return InvInsiden::withoutGlobalScopes()->where('company_id', $c->id)->select('id');
    }

    /** Id seluruh investigasi satu perusahaan, lewat insidennya. */
    private static function idInvestigasi(Company $c)
    {
        return InvInvestigasi::withoutGlobalScopes()
            ->whereIn('insiden_id', self::idInsiden($c))->select('id');
    }

    private static function kueri(string $kelas, Company $c)
    {
        $q = $kelas::withoutGlobalScopes();
        $model = new $kelas;

        if ($model->getConnection()->getSchemaBuilder()
                  ->hasColumn($model->getTable(), 'company_id')) {
            return $q->where('company_id', $c->id);
        }

        return match ($kelas) {
            /* ── Investigasi ──
               Tiga tingkat dari perusahaannya: anak → investigasi →
               insiden. Disaring lewat id investigasi, bukan lewat
               rangkaian whereIn bertumpuk yang ditulis ulang belasan
               kali — satu subkueri bernama membuat penyaringnya sama
               persis di tiap cabang, dan cabang yang berbeda sendiri
               adalah cara termudah satu tabel tertinggal tidak
               terhapus. */
            InvTim::class, InvBukti::class, InvKronologi::class, InvAnalisis::class,
            InvAkar::class, InvTemuan::class, InvPembelajaran::class, InvWawancara::class
                => $q->whereIn('investigasi_id', self::idInvestigasi($c)),

            InvInvestigasi::class, InvInsidenOrang::class, InvJejak::class
                => $q->whereIn('insiden_id', self::idInsiden($c)),

            InvScatPilihan::class => $q->whereIn('analisis_id',
                InvAnalisis::withoutGlobalScopes()
                    ->whereIn('investigasi_id', self::idInvestigasi($c))->select('id')),

            InvTindakan::class => $q->whereIn('temuan_id',
                InvTemuan::withoutGlobalScopes()
                    ->whereIn('investigasi_id', self::idInvestigasi($c))->select('id')),

            InvWawancaraJawaban::class => $q->whereIn('wawancara_id',
                InvWawancara::withoutGlobalScopes()
                    ->whereIn('investigasi_id', self::idInvestigasi($c))->select('id')),

            /* ── Miners ──
               Anaknya disaring lewat induknya yang berkolom
               company_id, sependek mungkin: unit SIMPER lewat
               SIMPER, bukan lewat permit lalu pekerja. */
            MnrMcuOrang::class => $q->whereIn('mcu_id',
                MnrMcu::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            MnrMcuRujukan::class => $q->whereIn('mcu_orang_id',
                MnrMcuOrang::query()->whereIn('mcu_id',
                    MnrMcu::withoutGlobalScopes()->where('company_id', $c->id)->select('id')
                )->select('id')),

            MnrInduksiOrang::class => $q->whereIn('induksi_id',
                MnrInduksi::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            MnrPermitBerkas::class => $q->whereIn('permit_id',
                MnrPermit::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            MnrSimperUnit::class, MnrSimperAjuan::class => $q->whereIn('simper_id',
                MnrSimper::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            MnrSimperAjuanUnit::class => $q->whereIn('ajuan_id',
                MnrSimperAjuan::query()->whereIn('simper_id',
                    MnrSimper::withoutGlobalScopes()->where('company_id', $c->id)->select('id')
                )->select('id')),

            /* Alur BERELASI POLIMORFIK lewat sepasang kolom, jadi ia
               tidak punya satu kunci asing pun yang dapat ditelusuri
               ke perusahaannya — dan tidak ada kaskade yang
               membuangnya bersama dokumennya.

               Disaring per jenis dokumen, seluruhnya digabung dengan
               `orWhere`. Yang kosong tetap disebut: jenis dokumen yang
               dilewatkan akan meninggalkan langkah persetujuan yatim
               yang bertambah tiap kali tombolnya ditekan. */
            MnrAlur::class => $q->where(function ($w) use ($c) {
                foreach ([
                    'mcu'     => MnrMcu::class,
                    'induksi' => MnrInduksi::class,
                    'permit'  => MnrPermit::class,
                    'simper'  => MnrSimper::class,
                ] as $jenis => $kelas) {
                    $w->orWhere(fn ($x) => $x->where('dokumen', $jenis)
                        ->whereIn('dokumen_id',
                            $kelas::withoutGlobalScopes()->where('company_id', $c->id)->select('id')));
                }

                $w->orWhere(fn ($x) => $x->where('dokumen', 'ajuan')
                    ->whereIn('dokumen_id',
                        MnrSimperAjuan::query()->whereIn('simper_id',
                            MnrSimper::withoutGlobalScopes()->where('company_id', $c->id)->select('id')
                        )->select('id')));
            }),

            /* PJP — satu tingkat: seluruh anaknya menempel pada
               mitranya, dan mitranya yang berkolom company_id. */
            PjpLaporan::class, PjpEvaluasi::class, PjpSmkpJawaban::class => $q->whereIn('pjp_id',
                Pjp::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            WaterSumpPump::class => $q->whereIn('water_sump_id',
                WaterSump::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),
            GeoInstrumen::class => $q->whereIn('geo_lereng_id',
                GeoLereng::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            /* Unit SIMPER menempel pada kartu, dan kartu pada paspor —
               dua tingkat. Disaring lewat kartunya, bukan lewat paspor,
               supaya penyaringnya sependek mungkin. */
            PasporKartuUnit::class => $q->whereIn('paspor_kartu_id',
                PasporKartu::withoutGlobalScopes()->whereIn('paspor_id',
                    Paspor::withoutGlobalScopes()->where('company_id', $c->id)->select('id')
                )->select('id')),

            InspectionItem::class, InspectionInspector::class => $q->whereIn('inspection_id',
                Inspection::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            SmkpFinding::class, SmkpAttendee::class, SmkpBukti::class, SmkpOfi::class
                => $q->whereIn('audit_id',
                    SmkpAudit::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            DocumentRevision::class, DocumentIso::class => $q->whereIn('document_id',
                Document::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            EnergyFuelLog::class => $q->whereIn('equipment_id',
                EnergyEquipment::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            KoSafeguard::class, KoInspection::class, KoAction::class, KoReview::class
                => $q->whereIn('ko_object_id',
                    KoObject::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            KoUjiKelayakan::class => $q->whereIn('ko_object_id',
                KoObject::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            WorkOrderPart::class => $q->whereIn('work_order_id',
                WorkOrder::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            /* Pesan menggantung pada percakapan; barisnya sendiri tidak
               menyebut perusahaan. Peserta bukan model — pivotnya ikut
               terbuang oleh cascadeOnDelete percakapan. */
            Pesan::class => $q->whereIn('percakapan_id',
                Percakapan::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            /* Keduanya tidak punya company_id sendiri; batasnya lewat
               pesanan. Tanpa cabang ini mereka tertinggal saat data
               contoh dibuang — dan bertambah tiap kali tombol muat
               ulang ditekan, tanpa satu pun galat. */
            ItemBeli::class, PembayaranBeli::class
                => $q->whereIn('pesanan_id',
                    PesananBeli::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            PasporSertifikat::class, PasporMcu::class, PasporKartu::class, PasporInduksi::class
                => $q->whereIn('paspor_id',
                    Paspor::withoutGlobalScopes()->where('company_id', $c->id)->select('id')),

            /* Paraf bersifat polimorfik, jadi penyaringnya dua cabang —
               satu per jenis subjek. Kolom subjek_type WAJIB ikut
               disebut di tiap cabang: tanpanya, whereIn atas id saja
               akan ikut menghapus paraf milik jenis subjek LAIN yang
               kebetulan bernomor sama. */
            PersetujuanParaf::class => $q->where(fn ($w) => $w
                ->where(fn ($x) => $x
                    ->where('subjek_type', PasporKartu::class)
                    ->whereIn('subjek_id', PasporKartu::withoutGlobalScopes()
                        ->whereIn('paspor_id', Paspor::withoutGlobalScopes()
                            ->where('company_id', $c->id)->select('id'))
                        ->select('id')))
                ->orWhere(fn ($x) => $x
                    ->where('subjek_type', McuPengajuan::class)
                    ->whereIn('subjek_id', McuPengajuan::withoutGlobalScopes()
                        ->where('company_id', $c->id)->select('id')))
                ->orWhere(fn ($x) => $x
                    ->where('subjek_type', InduksiPengajuan::class)
                    ->whereIn('subjek_id', InduksiPengajuan::withoutGlobalScopes()
                        ->where('company_id', $c->id)->select('id')))),

            /* Catatan belajar menggantung pada modul, dan modul pada
               kursus yang dibuat perusahaan contoh ini. */
            Note::class => $q->whereIn('module_id',
                Module::withoutGlobalScopes()->whereIn('course_id',
                    Course::withoutGlobalScopes()->where('demo_company_id', $c->id)->select('id')
                )->select('id')),

            InspectionTemplateItem::class => $q->whereIn('template_id',
                InspectionTemplate::withoutGlobalScopes()
                    ->where('demo_company_id', $c->id)->select('id')),

            /* Penyelesaian modul menggantung pada modul, dan modul pada
               kursus — jadi penyaringnya adalah kursus yang dibuat
               perusahaan contoh ini, bukan penggunanya. Menyaring lewat
               pengguna akan membuang penyelesaian pada kursus sungguhan
               yang kebetulan diikuti orang yang sama. */
            ModuleCompletion::class => $q->whereIn('module_id',
                Module::withoutGlobalScopes()->whereIn('course_id',
                    Course::withoutGlobalScopes()->where('demo_company_id', $c->id)->select('id')
                )->select('id')),

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

            /* Gelombang kedua, dari penelusuran tabel mana yang masih
               nol sesudah tombolnya ditekan. Empat modul di bawah ini
               tidak punya satu baris pun — dan tiga di antaranya
               (konservasi, tindak lanjut, penanda tangan) muncul pada
               laporan yang ditandatangani keluar. */
            'Konservasi'     => $this->konservasi(),
            'Tindak lanjut'  => $this->tindakLanjut(),
            'Penanda tangan' => $this->penandaTangan(),
            'Peta tambang'   => $this->peta(),
            'Authority'      => $this->authority(),
            'TPKKP'          => $this->tpkkp(),
            'Investigasi'    => $this->investigasi(),
            'PJP'            => $this->pjp(),

            /* Miners: MCU → Mine Permit → SIMPER. Satu rantai, dan
               yang paling perlu diperiksa orang justru sambungannya —
               kartu yang gugur karena MCU-nya habis, SIMPER yang gugur
               karena SIMPOL-nya habis. Keduanya sengaja ada di sini. */
            'Miners'         => $this->miners(),
            'Pembelian'      => $this->pembelian(),
            'Pesan'          => $this->pesan(),
            'Catatan'        => $this->catatan(),
        ]);
    }

    /**
     * Pembelian: katalog, satu tagihan lunas, satu yang masih menunggu.
     *
     * Dua tagihan, dan sengaja berbeda keadaannya. Data contoh yang
     * seluruhnya lunas tidak pernah memperlihatkan bagaimana layarnya
     * menampilkan tagihan yang menunggu — padahal itu keadaan yang
     * paling sering dibuka orang.
     *
     * Katalognya dipasang lewat perintah yang sama dengan yang dipakai
     * server, bukan disusun ulang di sini: dua penyusun untuk satu
     * daftar akan berbeda isinya cepat atau lambat, dan yang di sini
     * yang lebih dulu ketinggalan.
     */

    /* ─────────── Miners: MCU → Mine Permit → SIMPER ─────────── */

    /**
     * Satu rantai utuh, bukan tiga daftar yang kebetulan berdampingan.
     *
     * Ketiga dokumen Miners BERURUTAN — MCU menentukan Mine Permit, Mine
     * Permit menentukan SIMPER — dan yang paling perlu diperiksa orang
     * justru sambungannya: kartu yang gugur karena MCU-nya habis,
     * SIMPER yang gugur karena SIMPOL-nya habis, permit tamu yang hanya
     * berumur tujuh hari. Data contoh yang seluruhnya sah tidak pernah
     * memperlihatkan satu pun dari itu; ia hanya memperlihatkan bahwa
     * halamannya terbuka.
     *
     * Karena itu keenam orang di bawah sengaja BERBEDA KEADAANNYA, dan
     * perbedaannya dipilih dari apa yang ditanyakan layar pemantauan:
     *
     *   1 Sudarmin      semuanya sah — jalur yang benar, untuk pembanding
     *   2 Yulianto      MCU tinggal 10 hari — masuk ambang peringatan SOP
     *   3 Haryanto      MCU sudah lewat — kartunya gugur meski tanggalnya belum
     *   4 Bambang       mekanik, SIMPER R1 — kewenangan terbatas
     *   5 Ratna         Mine Permit saja, tanpa SIMPER — kartu putih
     *   6 Dwi Prasetyo  tamu, Visitor Permit tujuh hari
     *
     * Daftar awalnya dipasang lebih dulu, dan pemasangannya idempoten.
     * Tanpa itu, data contoh pada pemasangan yang belum pernah
     * menjalankan `miners:pasang` akan menghasilkan pekerja tanpa
     * departemen dan permit tanpa jenis — dan kegagalannya berhenti di
     * tingkat basis data, sebagai galat 500 tanpa sebab.
     */
    private function miners(): int
    {
        MasterMiners::pasang();

        $n = 0;

        /* TENGAH MALAM, dan itu bukan kerapian.
         *
         * Cast `date` Laravel memangkas jam saat DIBACA, tidak saat
         * DITULIS: Carbon berjam 22:15 tersimpan apa adanya sebagai
         * "2026-10-11 22:15:09" pada kolom bertipe DATE. MySQL memangkas
         * sendiri di tingkat kolom, SQLite tidak — sehingga baris yang
         * sama berperilaku BERBEDA di server dan di mesin penguji, dan
         * yang berbeda bukan tampilannya melainkan jawaban kueri
         * rentang: `whereBetween(hari ini, hari ini + 30)` melewatkan
         * sertifikat yang habis tepat pada hari ke-30, sebab 22:15 lewat
         * dari tengah malam hari itu. Ujinya hijau di sini, layarnya
         * salah di sana.
         *
         * Seluruh modul lain menyimpan 00:00:00 pada kolom tanggalnya;
         * yang di bawah ini mengikuti. `$this->kini` tetap dipakai apa
         * adanya untuk kolom WAKTU — `bertindak_pada` memang perlu
         * jamnya. */
        $hari = $this->kini->copy()->startOfDay();

        /* ── daftar milik perusahaan sendiri ── */

        $subkon = [];
        foreach ([['PT Karya Tambang Mandiri', 'KTM'], ['CV Sinar Jaya Teknik', 'SJT']] as [$nama, $kode]) {
            $subkon[] = MnrSubkontraktor::withoutGlobalScopes()->create([
                'company_id' => $this->c->id, 'nama' => $nama, 'kode' => $kode,
            ]);
            $n++;
        }

        foreach ([
            ['Ir. Bagas Wicaksono', 'Penanggung Jawab Operasional', 'pjo@contoh.test'],
            ['Andi Prasetyo, S.T.', 'Wakil PJO', 'wakil.pjo@contoh.test'],
        ] as [$nama, $jabatan, $surel]) {
            MnrPjo::withoutGlobalScopes()->create([
                'company_id' => $this->c->id, 'nama' => $nama,
                'jabatan' => $jabatan, 'email' => $surel,
            ]);
            $n++;
        }

        /* Sub-blok menempel pada blok acuan bersama — lokasi kerja
           001-SPM-007 — dan rinciannya memang milik masing-masing
           tambang: front dan bay tidak sama di dua tambang mana pun. */
        $blok = MnrBlok::withoutGlobalScopes()->whereNull('company_id')
            ->get()->keyBy('kunci');

        $subBlok = [];
        foreach ([
            ['pit', ['Front A', 'Front B']],
            ['workshop', ['Bay 1', 'Bay 2']],
        ] as [$kunci, $daftar]) {
            if (! isset($blok[$kunci])) continue;

            foreach ($daftar as $i => $nama) {
                $subBlok[$nama] = MnrSubBlok::withoutGlobalScopes()->create([
                    'company_id' => $this->c->id,
                    'blok_id'    => $blok[$kunci]->id,
                    'nama'       => $nama,
                    'urutan'     => ($i + 1) * 10,
                ]);
                $n++;
            }
        }

        $dep    = $this->minersMaster(MnrDepartemen::class);
        $jab    = $this->minersMaster(MnrJabatan::class);
        $unit   = $this->minersMaster(MnrJenisUnit::class);
        $golong = $this->minersMaster(MnrKendaraan::class);
        $tipe   = $this->minersMaster(MnrTipePermit::class);
        $hasil  = $this->minersMaster(MnrHasilMcu::class);

        /* ── orangnya ── */

        $orang = [
            ['Sudarmin',      '6371010101900001', 'produksi',    'driver-dt',          'PIT/Front A',    1990, 'O',  'karyawan'],
            ['Yulianto',      '6371010202880002', 'produksi',    'operator-excavator', 'PIT/Front B',    1988, 'B',  'karyawan'],
            ['Haryanto',      '6371010303920003', 'logistik',    'driver-lv',          'Main Office',    1992, 'A',  'kontrak'],
            ['Bambang Irawan','6371010404850004', 'plant',       'mekanik',            'Workshop/Bay 1', 1985, 'AB', 'karyawan'],
            ['Ratna Dewi',    '6371010505950005', 'logistik',    'admin-logistik',     'Main Office',    1995, 'O',  'karyawan'],
            ['Dwi Prasetyo',  '6371010606800006', 'hse',         'safety-officer',     'Main Office',    1980, 'B',  'tamu'],
        ];

        $pekerja = [];
        foreach ($orang as $i => [$nama, $nik, $kDep, $kJab, $lokasi, $lahir, $darah, $kerja]) {
            [$namaBlok, $namaSub] = array_pad(explode('/', $lokasi), 2, null);

            $pekerja[$nama] = MnrPekerja::withoutGlobalScopes()->create([
                'company_id'      => $this->c->id,
                'no_registrasi'   => sprintf('MNR-%s-%03d', $hari->format('Y'), $i + 1),
                'nama'            => $nama,
                'nik'             => $nik,
                'no_induk'        => sprintf('IBP-%04d', 1200 + $i),
                'tanggal_lahir'   => Carbon::create($lahir, ($i % 12) + 1, (($i * 3) % 27) + 1),
                'gol_darah'       => $darah,
                'telepon'         => '0812'.str_pad((string) (3300000 + $i), 7, '0', STR_PAD_LEFT),
                'telepon_darurat' => '0813'.str_pad((string) (4400000 + $i), 7, '0', STR_PAD_LEFT),
                'departemen_id'   => $dep[$kDep] ?? null,
                'jabatan_id'      => $jab[$kJab] ?? null,

                /* Dua dari enam dipekerjakan subkontraktor, bukan
                   seluruhnya atau tak seorang pun: penyaring "mitra
                   kerja" pada daftar pekerja hanya dapat diperiksa
                   kalau memang ada yang bermitra DAN ada yang tidak. */
                'subkontraktor_id' => $i % 3 === 1 ? $subkon[(int) ($i / 3)]->id : null,
                'blok_id'          => $blok->firstWhere('nama', $namaBlok)?->id,
                'sub_blok_id'      => $namaSub ? ($subBlok[$namaSub]->id ?? null) : null,
                'status_kerja'     => $kerja,
                'status'           => 'aktif',
                'user_id'          => $this->pengaju?->id,
            ]);
            $n++;
        }

        /* ── MCU ──
           Dua berkas: satu sudah selesai dan menjadi dasar seluruh
           kartu, satu masih di meja dokter. Yang kedua itu yang membuat
           antrean "menunggu pemeriksaan" dapat diperiksa; data contoh
           yang seluruh MCU-nya selesai tidak pernah menampilkannya. */

        $mcu = MnrMcu::withoutGlobalScopes()->create([
            'company_id'    => $this->c->id,
            'no_registrasi' => 'MCU-'.$hari->format('Y').'-001',
            'tanggal'       => $hari->copy()->subMonths(3),
            'kepada'        => 'Klinik Pratama Bhakti Medika',
            'perihal'       => 'Permohonan pemeriksaan kesehatan berkala karyawan tambang.',
            'status'        => 'selesai',
            'user_id'       => $this->pengaju?->id,
        ]);
        $n++;
        $n += $this->minersAlur($mcu, ['setuju', 'setuju', 'setuju']);

        /* Berapa bulan lalu MCU tiap orang dilakukan. Sengaja
           BUKAN satu angka: 11 bulan lalu berarti tinggal sebulan lagi,
           12 bulan 10 hari lalu berarti sudah lewat — dua keadaan yang
           justru paling perlu terlihat di layar pemantauan. */
        $umurMcu = [
            'Sudarmin'       => ['bulan' => 2,  'hasil' => 'fit'],
            'Yulianto'       => ['hari'  => 355, 'hasil' => 'fit'],
            'Haryanto'       => ['hari'  => 380, 'hasil' => 'fit'],
            'Bambang Irawan' => ['bulan' => 4,  'hasil' => 'fit-with-note'],
            'Ratna Dewi'     => ['bulan' => 1,  'hasil' => 'fit'],
            'Dwi Prasetyo'   => ['bulan' => 1,  'hasil' => 'fit'],
        ];

        $mcuOrang = [];
        foreach ($pekerja as $nama => $p) {
            $u = $umurMcu[$nama];

            $periksa = isset($u['bulan'])
                ? $hari->copy()->subMonths($u['bulan'])
                : $hari->copy()->subDays($u['hari']);

            $mcuOrang[$nama] = MnrMcuOrang::create([
                'mcu_id'             => $mcu->id,
                'pekerja_id'         => $p->id,
                'nama'               => $p->nama,
                'nik'                => $p->nik,
                'jabatan'            => MnrJabatan::withoutGlobalScopes()->find($p->jabatan_id)?->nama,
                'usia'               => $p->usia($periksa),
                'departemen_id'      => $p->departemen_id,
                'hasil_id'           => $hasil[$u['hasil']] ?? null,
                'tanggal_periksa'    => $periksa,
                'berlaku_sampai'     => $periksa->copy()->addMonths(MnrMcuOrang::BULAN_BERLAKU),
                'tanggal_berikut'    => $periksa->copy()->addMonths(MnrMcuOrang::BULAN_BERLAKU),
                'hasil_napza'        => 'negatif',
                'aktif'              => true,
            ]);
            $n++;
        }

        /* Satu rujukan, pada satu-satunya orang yang hasilnya "Fit With
           Note". Rujukan tanpa catatan medis apa pun tidak masuk akal,
           dan yang tidak masuk akal tidak dapat dipakai memeriksa
           apakah layarnya benar. */
        MnrMcuRujukan::create([
            'mcu_orang_id'    => $mcuOrang['Bambang Irawan']->id,
            'tanggal_surat'   => $mcuOrang['Bambang Irawan']->tanggal_periksa->copy()->addDays(3),
            'dokter'          => 'dr. Retno Wulandari, Sp.PD',
            'poliklinik'      => 'Penyakit Dalam',
            'rumah_sakit'     => 'RSUD Kabupaten',
            'diagnosis_awal'  => 'Tekanan darah 150/95 mmHg pada dua kali pengukuran.',
            'keterangan'      => 'Boleh bekerja dengan pemantauan tekanan darah tiap tiga bulan. Tidak untuk shift malam.',
        ]);
        $n++;

        $mcuJalan = MnrMcu::withoutGlobalScopes()->create([
            'company_id'    => $this->c->id,
            'no_registrasi' => 'MCU-'.$hari->format('Y').'-002',
            'tanggal'       => $hari->copy()->subDays(5),
            'kepada'        => 'Klinik Pratama Bhakti Medika',
            'perihal'       => 'Permohonan MCU pre-employment dua calon karyawan baru.',
            'status'        => 'diperiksa',
            'user_id'       => $this->pengaju?->id,
        ]);
        $n++;
        $n += $this->minersAlur($mcuJalan, ['setuju', 'menunggu', 'menunggu']);

        /* ── induksi ── */

        $induksi = MnrInduksi::withoutGlobalScopes()->create([
            'company_id'    => $this->c->id,
            'no_registrasi' => 'IND-'.$hari->format('Y').'-001',
            'tanggal'       => $hari->copy()->subMonths(3)->addDays(7),
            'perihal'       => 'Induksi keselamatan pertambangan bagi enam karyawan.',
            'status'        => 'selesai',
            'user_id'       => $this->pengaju?->id,
        ]);
        $n++;
        $n += $this->minersAlur($induksi, ['setuju', 'setuju']);

        /* Nilai post test sengaja MELINTASI ambang kelulusan, bukan
           seluruhnya di atasnya. Satu orang harus mengulang, dan
           layar remidi tidak dapat diperiksa tanpa itu. */
        $nilai = [
            'Sudarmin' => [88, 1], 'Yulianto' => [92, 1], 'Haryanto' => [76, 2],
            'Bambang Irawan' => [85, 1], 'Ratna Dewi' => [95, 1], 'Dwi Prasetyo' => [90, 1],
        ];

        $induksiOrang = [];
        foreach ($pekerja as $nama => $p) {
            [$angka, $percobaan] = $nilai[$nama];
            $tanggal = $induksi->tanggal->copy();

            $induksiOrang[$nama] = MnrInduksiOrang::create([
                'induksi_id'      => $induksi->id,
                'pekerja_id'      => $p->id,
                'mcu_orang_id'    => $mcuOrang[$nama]->id,
                'tanggal_induksi' => $tanggal,
                'lokasi'          => 'Ruang Induksi, Main Office',
                'nilai'           => $angka,
                'percobaan'       => $percobaan,
                'status'          => MnrInduksiOrang::statusDari($angka, $percobaan),
                'berlaku_sampai'  => $tanggal->copy()->addMonths(MnrInduksiOrang::BULAN_BERLAKU),
                'catatan'         => $percobaan > 1
                    ? 'Nilai '.$angka.' masih di bawah ambang '.MnrInduksiOrang::NILAI_LULUS
                        .'. Dijadwalkan mengulang sekali lagi.'
                    : null,
            ]);
            $n++;
        }

        $induksiJalan = MnrInduksi::withoutGlobalScopes()->create([
            'company_id'    => $this->c->id,
            'no_registrasi' => 'IND-'.$hari->format('Y').'-002',
            'tanggal'       => $hari->copy()->addDays(10),
            'perihal'       => 'Refresh induksi keselamatan menjelang perpanjangan tahunan.',
            'status'        => 'dijadwal',
            'user_id'       => $this->pengaju?->id,
        ]);
        $n++;
        $n += $this->minersAlur($induksiJalan, ['setuju', 'menunggu']);

        /* ── Mine Permit ──
           Masa berlakunya DIHITUNG dari tipenya lewat jalan yang sama
           dengan yang dipakai aplikasi, bukan diketik sebagai tanggal
           di sini: tanggal yang diketik akan diam-diam berbeda dari
           yang dihitung aplikasi, dan yang berbeda itulah yang
           dipercaya orang saat memeriksa layarnya. */

        $rencana = [
            ['Sudarmin',       'full-permit',      'umum',      'UNRESTRICTED', 'merah', 'terbit',   null],
            ['Yulianto',       'full-permit',      'umum',      'UNRESTRICTED', 'merah', 'terbit',   null],
            ['Haryanto',       'full-permit',      'umum',      'RESTRICTED',   'hijau', 'terbit',   null],
            ['Bambang Irawan', 'full-permit',      'umum',      'UNRESTRICTED', 'merah', 'terbit',   null],
            ['Ratna Dewi',     'full-permit',      'umum',      'RESTRICTED',   'putih', 'terbit',   null],
            ['Dwi Prasetyo',   'visitor-permit',   'kunjungan', 'RESTRICTED',   'putih', 'diajukan', null],
        ];

        $permit = [];
        foreach ($rencana as $i => [$nama, $kTipe, $kKategori, $zona, $warna, $status, $cabut]) {
            $p       = $pekerja[$nama];
            $tipeRow = MnrTipePermit::withoutGlobalScopes()->find($tipe[$kTipe] ?? 0);

            /* Permit tamu diterbitkan BARU-BARU INI, bukan tiga bulan
               lalu seperti sisanya. Umurnya hanya tujuh hari menurut
               SOP, jadi yang berumur tiga bulan sudah lama habis — dan
               permit tamu yang selalu tampil kedaluwarsa tidak
               memperlihatkan apa pun tentang bagaimana jendela tujuh
               hari itu digambar, yang justru satu-satunya alasan
               jenis ini ada. */
            $terbit = $tipeRow?->hari_berlaku === null
                ? $hari->copy()->subMonths(3)->addDays(14)
                : $hari->copy()->subDays(2);

            [$habis, $asal] = MnrPermit::hitungBerlaku($tipeRow, $terbit);

            $kategoriId = MnrKategoriPermit::where('tipe_permit_id', $tipeRow?->id)
                ->where('kunci', $kKategori)->value('id');

            $permit[$nama] = MnrPermit::withoutGlobalScopes()->create([
                'company_id'         => $this->c->id,
                'kontraktor_id'      => null,
                'pekerja_id'         => $p->id,
                'mcu_orang_id'       => $mcuOrang[$nama]->id,
                'induksi_orang_id'   => $induksiOrang[$nama]->id,
                'no_registrasi'      => sprintf('MP-%s-%03d', $terbit->format('Y'), $i + 1),
                'tanggal'            => $terbit,
                'tipe_permit_id'     => $tipeRow?->id,
                'kategori_permit_id' => $kategoriId,
                'cakupan_area'       => $zona,
                'kode_warna'         => $warna,
                'status'             => $status,
                'berlaku_sampai'     => $habis,
                'sumber_berlaku'     => $asal,
                'user_id'            => $this->pengaju?->id,
            ]);
            $n++;

            $n += $this->minersAlur(
                $permit[$nama],
                $status === 'terbit' ? ['setuju', 'setuju', 'setuju'] : ['setuju', 'menunggu', 'menunggu'],
            );
        }

        /* Lampiran wajib menurut SOP, pada satu permit saja. Seluruhnya
           pada keenamnya hanya menggandakan baris yang sama; satu yang
           lengkap cukup memperlihatkan bagaimana daftar periksa
           kelengkapan digambar. */
        foreach (Acuan::berkasWajib('permit_baru') as $j => $jenis) {
            MnrPermitBerkas::create([
                'permit_id' => $permit['Sudarmin']->id,
                'jenis'     => Str::slug($jenis),
                'berkas'    => null,
                'nomor'     => sprintf('LMP-%03d', $j + 1),
                'tanggal'   => $permit['Sudarmin']->tanggal,
                'catatan'   => $jenis,
            ]);
            $n++;
        }

        /* ── SIMPER ── */

        $kartu = [
            ['Sudarmin',       'F',  'Dump Truck', ['Dump Truck PS'],               'B2 Umum', 'operator', [90, 88, 92, 85]],
            ['Yulianto',       'F',  'Alat Berat', ['Excavator', 'Wheel Loader'],   'B2 Umum', 'operator', [92, 90, 88, 90]],
            ['Bambang Irawan', 'R1', 'Alat Berat', ['Excavator', 'Bulldozer'],      'B2 Umum', 'pengawas', [85, 80, 82, null]],
        ];

        $simper = [];
        foreach ($kartu as $i => [$nama, $kelas, $namaGolongan, $daftarUnit, $simpol, $wewenang, $skor]) {
            $terbit = $permit[$nama]->tanggal->copy()->addDays(21);

            /* SIMPOL Bambang habis LEBIH DAHULU daripada apa pun yang
               lain pada kartunya. SOP: "SIMPOL habis → SIMPER otomatis
               tidak berlaku", dan aturan itu tidak dapat diperiksa di
               layar mana pun tanpa satu kartu yang benar-benar begitu.

               DIA, BUKAN YULIANTO, dan pilihan itu bukan selera. Masa
               berlaku SIMPER adalah yang PALING AWAL di antara tiga —
               kartunya sendiri, permitnya, dan SIMPOL — sedangkan
               permit Yulianto sudah dipendekkan MCU-nya sampai sepuluh
               hari lagi. SIMPOL pendek di sana akan kalah oleh permit,
               dan `penyebabHabis()` menjawab "permit": aturan SIMPOL
               tidak pernah terlihat sekali pun. MCU Bambang masih lama,
               jadi pada kartunyalah SIMPOL benar-benar yang menentukan. */
            $simpolHabis = $nama === 'Bambang Irawan'
                ? $hari->copy()->addDays(20)
                : $hari->copy()->addYears(3);

            $simper[$nama] = MnrSimper::withoutGlobalScopes()->create([
                'company_id'            => $this->c->id,
                'permit_id'             => $permit[$nama]->id,
                'pekerja_id'            => $pekerja[$nama]->id,
                'no_simper'             => sprintf('SMP-%s-%03d', $terbit->format('Y'), $i + 1),
                'tanggal'               => $terbit,
                'kelas'                 => $kelas,
                'no_simpol'             => '7401'.str_pad((string) (120045 + $i), 6, '0', STR_PAD_LEFT),
                'jenis_simpol'          => $simpol,
                'simpol_berlaku_sampai' => $simpolHabis,
                'pengalaman_kerja'      => (5 + $i).' tahun',
                'status'                => 'terbit',
                'berlaku_sampai'        => $terbit->copy()->endOfYear()->startOfDay(),
                'sumber_berlaku'        => 'tahunan',
                'user_id'               => $this->pengaju?->id,
            ]);
            $n++;
            $n += $this->minersAlur($simper[$nama], ['setuju', 'setuju', 'setuju']);

            foreach ($daftarUnit as $namaUnit) {
                MnrSimperUnit::create([
                    'simper_id'     => $simper[$nama]->id,
                    'kendaraan_id'  => $golong[Str::slug($namaGolongan)] ?? null,
                    'jenis_unit_id' => $unit[Str::slug($namaUnit)] ?? null,
                    'kewenangan'    => $wewenang,
                    'nilai_p2h'     => $skor[0],
                    'nilai_praktek' => $skor[1],
                    'nilai_teori'   => $skor[2],
                    'nilai_rambu'   => $skor[3],
                    'asal'          => 'baru',
                ]);
                $n++;
            }
        }

        /* ── pengajuan lanjutan ──
           Dua jenis dari tiga, dan keduanya berbeda keadaan: satu sudah
           tuntas, satu masih menunggu KTT. Tanpa yang kedua, layar
           "menunggu pengesahan" tidak punya satu baris pun. */

        $tambah = MnrSimperAjuan::create([
            'simper_id'        => $simper['Sudarmin']->id,
            'jenis'            => 'penambahan',
            'no_registrasi'    => 'AJU-'.$hari->format('Y').'-001',
            'tanggal'          => $hari->copy()->subMonths(1),
            'pengalaman_kerja' => '6 tahun',
            'status'           => 'selesai',
            'user_id'          => $this->pengaju?->id,
        ]);
        $n++;
        $n += $this->minersAlur($tambah, ['setuju', 'setuju', 'setuju']);

        foreach (['Water Truck' => 'Water Truck', 'Fuel Truck' => 'Fuel Truck'] as $namaGolongan => $namaUnit) {
            MnrSimperAjuanUnit::create([
                'ajuan_id'      => $tambah->id,
                'kendaraan_id'  => $golong[Str::slug($namaGolongan)] ?? null,
                'jenis_unit_id' => $unit[Str::slug($namaUnit)] ?? null,
                'kewenangan'    => 'operator',
                'nilai_p2h'     => 88,
                'nilai_praktek' => 86,
                'nilai_teori'   => 90,
            ]);
            $n++;

            /* Unit yang sudah disetujui IKUT MASUK ke kartunya, bertanda
               asal "penambahan". Disimpan hanya pada pengajuannya,
               kartu yang unitnya bertambah tidak pernah menunjukkan
               unit barunya — dan yang dibaca petugas pos adalah kartu,
               bukan arsip pengajuan. */
            MnrSimperUnit::create([
                'simper_id'     => $simper['Sudarmin']->id,
                'kendaraan_id'  => $golong[Str::slug($namaGolongan)] ?? null,
                'jenis_unit_id' => $unit[Str::slug($namaUnit)] ?? null,
                'kewenangan'    => 'operator',
                'nilai_p2h'     => 88,
                'nilai_praktek' => 86,
                'nilai_teori'   => 90,
                'asal'          => 'penambahan',
            ]);
            $n++;
        }

        /* ── sertifikat kompetensi ──
           Tiga orang, dan masa berlakunya sengaja BERBEDA JAUH: satu
           masih lama, satu tinggal tiga minggu, satu sudah lewat.
           Masa berlaku melekat pada sertifikatnya, bukan pada orangnya
           — seorang pengawas dapat memegang POP sampai 2028 dan Ahli K3
           yang habis bulan depan — dan perbedaan itu tidak dapat
           diperiksa pada data contoh yang seluruhnya berlaku sampai
           tahun yang sama. */
        foreach ([
            ['Sudarmin',       'Pengawas Operasional Pertama (POP)', 'BNSP', 36],
            ['Yulianto',       'Petugas K3 Pertambangan',            'ESDM', 1],
            ['Bambang Irawan', 'AK3U BNSP',                          'BNSP', -2],
        ] as [$nama, $judul, $lembaga, $bulanLagi]) {
            $jenis = KompetensiJenis::withoutGlobalScopes()
                ->whereRaw('LOWER(nama) = ?', [mb_strtolower($judul)])->first();

            MnrKompetensi::withoutGlobalScopes()->create([
                'company_id'          => $this->c->id,
                'pekerja_id'          => $pekerja[$nama]->id,
                'kompetensi_jenis_id' => $jenis?->id,
                'nama'                => $jenis?->nama ?? $judul,
                'lembaga'             => $jenis?->lembaga ?? $lembaga,
                'nomor'               => 'SRT/'.strtoupper(Str::random(4)).'/'.$hari->format('Y'),
                'tanggal_terbit'      => $hari->copy()->addMonths($bulanLagi)->subYears(3),
                'berlaku_sampai'      => $hari->copy()->addMonths($bulanLagi),
                'user_id'             => $this->pengaju?->id,
            ]);
            $n++;
        }

        $perpanjang = MnrSimperAjuan::create([
            'simper_id'             => $simper['Yulianto']->id,
            'jenis'                 => 'perpanjangan',
            'no_registrasi'         => 'AJU-'.$hari->format('Y').'-002',
            'tanggal'               => $hari->copy()->subDays(4),
            'simpol_berlaku_sampai' => $hari->copy()->addYears(5),
            'pengalaman_kerja'      => '7 tahun',
            'status'                => 'ktt',
            'catatan'               => 'SIMPOL diperpanjang; menunggu pengesahan KTT.',
            'user_id'               => $this->pengaju?->id,
        ]);
        $n++;
        $n += $this->minersAlur($perpanjang, ['setuju', 'setuju', 'menunggu']);

        MnrSimperAjuanUnit::create([
            'ajuan_id'      => $perpanjang->id,
            'kendaraan_id'  => $golong['alat-berat'] ?? null,
            'jenis_unit_id' => $unit['excavator'] ?? null,
            'kewenangan'    => 'operator',
            'nilai_p2h'     => 94,
            'nilai_praktek' => 91,
            'nilai_teori'   => 89,
        ]);
        $n++;

        return $n;
    }

    /**
     * Daftar master Miners sebagai kunci => id.
     *
     * Dikunci lewat `kunci`, bukan lewat nama: nama boleh disunting
     * perusahaan yang memakainya — itu justru yang dijaga MasterMiners —
     * sehingga data contoh yang mencari lewat nama akan berhenti
     * menemukan apa pun pada pemasangan yang departemennya sudah
     * diganti namanya, dan pekerjanya lahir tanpa departemen.
     *
     * @param  class-string<\App\Models\Miners\Master>  $kelas
     * @return array<string,int>
     */
    private function minersMaster(string $kelas): array
    {
        return $kelas::withoutGlobalScopes()
            ->whereNotNull('kunci')
            ->pluck('id', 'kunci')
            ->all();
    }

    /**
     * Terbitkan alur sebuah dokumen lalu tetapkan keadaan tiap langkah.
     *
     * Alurnya dibuat lewat `terbitkanAlur()` — jalan yang sama dengan
     * yang dipakai aplikasi — bukan disusun ulang di sini. Dua penyusun
     * untuk satu aturan akan berbeda cepat atau lambat, dan yang di
     * sini yang lebih dulu ketinggalan: data contoh akan memperlihatkan
     * alur yang tidak pernah dilalui dokumen sungguhan.
     *
     * @param  list<string>  $keadaan  berurutan; yang lebih pendek
     *                                 membiarkan sisanya menunggu
     * @return int jumlah langkah yang lahir
     */
    private function minersAlur(object $dokumen, array $keadaan): int
    {
        $dokumen->terbitkanAlur();

        $langkah = $dokumen->alur()->orderBy('urutan')->get();

        foreach ($langkah as $i => $l) {
            $k = $keadaan[$i] ?? 'menunggu';

            $l->update([
                'keadaan'        => $k,
                'user_id'        => $k === 'menunggu' ? null : ($this->peninjau?->id ?? $this->pengaju?->id),

                /* Kolom WAKTU, bukan tanggal — jamnya memang disimpan,
                   sebab "siapa menyetujui pukul berapa" adalah yang
                   ditanyakan saat sebuah pengesahan dipersoalkan. */
                'bertindak_pada' => $k === 'menunggu' ? null : $this->kini->copy()->subDays(30 - $i),
            ]);
        }

        return $langkah->count();
    }

    /* ─────────── Pemantauan Perusahaan Jasa ─────────── */

    /**
     * Dua mitra yang keadaannya BERLAWANAN, bukan dua mitra yang sama.
     *
     * Yang satu patuh sepenuhnya, yang lain menunggak dan skornya jatuh.
     * Data contoh yang seluruhnya rapi tidak pernah memperlihatkan
     * bagaimana layarnya menandai mitra bermasalah — padahal daftar
     * "paling perlu perhatian" dan ubin "laporan bulanan menunggak"
     * adalah dua bagian yang paling sering dibuka, dan keduanya hanya
     * dapat diperiksa kalau memang ada yang bermasalah.
     *
     * Daftar periksanya diisi SELURUHNYA untuk keduanya, 126 butir
     * masing-masing. Mengisi sebagian akan membuat skornya bergantung
     * pada butir mana yang kebetulan terisi — dan angka yang tidak dapat
     * dihitung ulang dengan tangan adalah angka yang tidak dapat
     * diperiksa siapa pun.
     */
    private function pjp(): int
    {
        /* Daftar periksanya dipasang lebih dulu, dan pemasangannya
           idempoten. Tanpa ini, data contoh pada pemasangan yang belum
           pernah menjalankan `pjp:pasang` akan menghasilkan mitra tanpa
           satu pun jawaban — dan skornya 0% bagi semuanya, tanpa satu
           galat pun yang menandai bahwa masternya yang belum ada. */
        DaftarPeriksaSmkp::pasang();

        $n = 0;

        $butir = PjpSmkpItem::query()->with('kategori')->orderBy('urutan')->get();

        /* Dua mitra, dan nilai daftar periksanya ditentukan oleh satu
           angka: berapa dari setiap tiga butir yang dinilai penuh.
           Ditulis begitu supaya skornya dapat dihitung ulang dengan
           tangan dari angka itu saja. */
        foreach ([
            ['PT Karya Bumi Sejahtera', '0812345678901', 'Ir. Bagas Wicaksono', 'aktif',
             'tiap' => 1, 'catatan' => 'Kontrak hauling overburden Pit Selatan, berlaku sampai Desember 2026.'],
            ['PT Mitra Tambang Nusantara', '0898765432109', 'Andi Prasetyo, S.T.', 'perlu_tindak_lanjut',
             'tiap' => 3, 'catatan' => 'Laporan bulanan sering terlambat. Sudah dua kali diberi surat teguran.'],
        ] as $b) {
            [$nama, $nib, $pj, $status] = $b;

            $pjp = Pjp::withoutGlobalScopes()->create([
                'company_id'       => $this->c->id,
                'nama_perusahaan'  => $nama,
                'nib'              => $nib,
                'penanggung_jawab' => $pj,
                'alamat'           => 'Jl. Poros Tambang KM 12, Kutai Kartanegara, Kalimantan Timur',
                'status'           => $status,
                'catatan'          => $b['catatan'],
            ]);
            $n++;

            foreach ($butir as $i => $satu) {
                $legalitas = $satu->kategori?->kode === PjpSmkpKategori::LEGALITAS;
                $penuh     = $i % $b['tiap'] === 0;

                PjpSmkpJawaban::withoutGlobalScopes()->create([
                    'pjp_id'  => $pjp->id,
                    'item_id' => $satu->id,

                    /* Kategori LEGALITAS dijawab ada/tidak ada; A–P
                       dinilai 0–3. Mengisi keduanya dengan kolom yang
                       sama akan membuat salah satunya terbaca kosong di
                       layar tanpa satu galat pun. */
                    'jawaban' => $legalitas ? ($penuh ? 'ya' : 'tidak') : null,
                    'nilai'   => $legalitas ? null : ($penuh ? '3' : '1'),
                ]);
                $n++;
            }

            $n += $this->pjpLaporan($pjp, $b['tiap'] === 1);
            $n += $this->pjpEvaluasi($pjp, $b['tiap'] === 1);
        }

        return $n;
    }

    /**
     * Dokumen berkala satu mitra.
     *
     * Tanggal unggahnya ditulis lewat kueri, bukan lewat isian: kolom
     * stempel waktu diisi Eloquent sesudah penyimpanan dan menimpa apa
     * pun yang diberikan — sehingga seluruh dokumen contoh akan
     * bertanggal hari ini, dan tidak satu pun akan terbaca terlambat.
     */
    private function pjpLaporan(Pjp $pjp, bool $patuh): int
    {
        $n = 0;

        $tahun     = (int) $this->kini->format('Y');
        $bulanLalu = $this->kini->copy()->subMonth();

        foreach ([
            ['spip', 'Semester I '.$tahun, $bulanLalu, 2, 'sesuai',
             'Data SPIP 24 unit, lengkap dengan sertifikat kelayakan.'],

            ['tsp', (string) $tahun, $bulanLalu, 3, 'sesuai',
             'TSP '.$tahun.' memuat 11 sasaran dengan penanggung jawab.'],

            /* Laporan bulanan sengaja jatuh pada BULAN BERJALAN, bukan
               bulan lalu. Pjp::belumLaporanBulananBulanIni() menanyakan
               bulan ini; ditaruh di bulan lalu, kedua mitra sama-sama
               muncul menunggak — dan data contoh yang dimaksudkan
               memperlihatkan satu mitra patuh justru memperlihatkan
               dua-duanya lalai. */
            ['laporan_bulanan', $this->kini->translatedFormat('F Y'), $this->kini,
             $patuh ? 2 : 14, $patuh ? 'sesuai' : null,
             $patuh ? 'Jam kerja, statistik kecelakaan, dan realisasi program.'
                    : 'Terlambat 11 hari. Statistik jam kerja belum dilampirkan.'],
        ] as [$jenis, $periode, $bulan, $hari, $sesuai, $catatan]) {
            $saat = $bulan->copy()->startOfMonth()->addDays($hari - 1)->setTime(9, 15);

            $l = PjpLaporan::withoutGlobalScopes()->create([
                'pjp_id'    => $pjp->id,
                'jenis'     => $jenis,
                'periode'   => $periode,
                'catatan'   => $catatan,
                'kesesuaian_isi' => $sesuai,

                /* Berkasnya sengaja TIDAK ada di disk. Data contoh tidak
                   mengunggah apa pun, dan menaruh berkas palsu di disk
                   tertutup berarti data contoh meninggalkan berkas yang
                   tidak ikut terbuang saat data contohnya dibuang. Yang
                   membukanya memperoleh 404 dari rute penyaji — jawaban
                   yang benar bagi berkas yang memang tidak ada. */
                'file_path' => "pjp/{$pjp->id}/contoh-{$jenis}.pdf",
                'file_name' => str(PjpLaporan::JENIS[$jenis] ?? $jenis)->slug().'-'
                    .str($periode)->slug().'.pdf',
                'file_size' => 248_000,
            ]);

            PjpLaporan::withoutGlobalScopes()->where('id', $l->id)
                ->update(['created_at' => $saat, 'updated_at' => $saat]);

            $n++;
        }

        return $n;
    }

    /** Dua semester penilaian, supaya grafik trennya punya dua titik. */
    private function pjpEvaluasi(Pjp $pjp, bool $patuh): int
    {
        $tahun = (int) $this->kini->format('Y');
        $n = 0;

        foreach ([
            [$tahun - 1, 2, $patuh ? [88, 90, 86] : [62, 55, 60]],
            [$tahun,     1, $patuh ? [92, 94, 90] : [58, 48, 57]],
        ] as [$th, $sem, $skor]) {
            PjpEvaluasi::withoutGlobalScopes()->create([
                'pjp_id'   => $pjp->id,
                'tahun'    => $th,
                'semester' => $sem,
                'skor_teknis'                => $skor[0],
                'skor_keselamatan_kesehatan' => $skor[1],
                'skor_lingkungan'            => $skor[2],
                'catatan' => $patuh
                    ? 'Tidak ada temuan mayor. Program KPLH berjalan sesuai TSP.'
                    : 'Dua temuan mayor pada pengelolaan kelelahan dan tanggap darurat.',
            ]);
            $n++;
        }

        return $n;
    }

    private function pembelian(): int
    {
        /* Katalognya dipasang, tetapi TIDAK dihitung sebagai baris data
           contoh — ia master milik penjual, bukan data pelanggan, dan
           membuangnya bersama data contoh akan menghapus harga yang
           sudah ditetapkan orang. */
        \Illuminate\Support\Facades\Artisan::call('pembelian:katalog');

        $n = 0;

        /* Harga contoh. Perintah pemasangnya sengaja menaruh nol dan
           menonaktifkan — di sini diisi supaya katalognya dapat dilihat
           berisi. */
        ProdukBeli::where('kode', 'WEBSITE')->update(['harga' => 25_000_000, 'aktif' => true]);

        ProdukBeli::where('jenis', 'aplikasi')->orderBy('urutan')->take(8)->get()
            ->each(fn ($p) => $p->update(['harga' => 2_500_000, 'aktif' => true]));

        $paket = ProdukBeli::where('kode', 'WEBSITE')->first();
        $satuan = ProdukBeli::where('jenis', 'aplikasi')->where('aktif', true)->first();

        if (! $paket || ! $satuan) return $n;

        /* ── tagihan yang sudah lunas, lengkap dengan lisensinya ── */
        $lunas = Pembelian::buat([
            'company_id'         => $this->c->id,
            'pembeli_nama'       => 'Bpk. Hendra Wijaya',
            'pembeli_perusahaan' => $this->c->name,
            'pembeli_email'      => 'hendra@contoh.co.id',
            'pembeli_telepon'    => '0811-2233-4455',
        ], [$paket->id => 1], $this->peninjau);

        Pembelian::kirim($lunas);
        $n += 1 + $lunas->items()->count();

        PembayaranBeli::create([
            'pesanan_id' => $lunas->id,
            'metode'     => 'qris',
            'jumlah'     => $lunas->total,
            'atas_nama'  => 'Hendra Wijaya',
            /* startOfDay(): kolomnya bertipe DATE, dan cast `date`
               Laravel memangkas jam saat dibaca — tidak saat ditulis.
               Lihat KolomTanggalTest. */
            'tanggal_bayar' => $this->kini->copy()->subDays(9)->startOfDay(),
            'catatan'    => 'Dibayar lewat QRIS, satu kali penuh.',
        ]);
        $n++;

        if ($this->peninjau) {
            Pembelian::tandaiLunas($lunas, $this->peninjau);
            $n += $lunas->lisensi()->count();
        }

        /* ── tagihan yang masih menunggu pembayaran ── */
        /* Perusahaannya diisi, meski di kenyataan calon pelanggan kerap
           belum punya. Data contoh harus dapat dibuang seluruhnya, dan
           tagihan tanpa perusahaan tidak terjangkau pembuangnya —
           tertinggal, lalu menumpuk tiap kali tombol muat ulang
           ditekan. */
        $menunggu = Pembelian::buat([
            'company_id'         => $this->c->id,
            'pembeli_nama'       => 'Ibu Ratna Sari',
            'pembeli_perusahaan' => 'PT Bara Sejahtera',
            'pembeli_email'      => 'ratna@barasejahtera.co.id',
            'pembeli_telepon'    => '0812-9988-7766',
            'catatan'            => 'Minta penawaran tiga aplikasi lebih dulu.',
        ], [$satuan->id => 3], $this->peninjau);

        Pembelian::kirim($menunggu);
        $n += 1 + $menunggu->items()->count();

        return $n;
    }

    /* ─────────── Authority: kelayakan kerja ─────────── */

    /**
     * Berkas kelayakan kerja lima orang.
     *
     * Sebarannya sengaja tidak rapi, dan tiap ketidakrapiannya menguji
     * satu hal:
     *
     *   satu MCU kadaluarsa       → orangnya tidak boleh bekerja
     *   satu hasil Temporary Unfit → tidak boleh meski MCU masih berlaku
     *   satu kartu masuk habis     → tidak boleh meski MCU sehat
     *   satu sertifikat lewat      → orangnya tetap boleh masuk, tetapi
     *                                 tidak boleh mengerjakan pekerjaan
     *                                 yang menuntut sertifikat itu
     *   satu tanpa tanggal         → bukan aman, melainkan tidak diketahui
     *
     * Berkas yang seluruhnya berlaku tidak pernah menyalakan satu pun
     * peringatan, dan peringatan yang tidak pernah menyala tidak dapat
     * dibedakan dari peringatan yang rusak.
     */
    private function authority(): int
    {
        $n = 0;

        /* Jenis kompetensi ditanam bila pemasangannya belum punya —
           masternya milik bersama, jadi tidak ikut dibuang bersama data
           contoh. */
        if (KompetensiJenis::withoutGlobalScopes()->whereNull('company_id')->doesntExist()) {
            MasterKompetensi::tanam();
        }

        $jenis = KompetensiJenis::withoutGlobalScopes()
            ->whereNull('company_id')->pluck('id', 'nama');

        $orang = [
            ['Ir. Bambang Susilo',  '3201010101800001', 'Kepala Teknik Tambang',   'OHSE',      'PO'],
            ['Dewi Anggraini',      '3201010202850002', 'Manajer OHSE',            'OHSE',      'PO'],
            ['Rahmat Hidayat',      '3201010303900003', 'Operator Crane',          'Workshop',  'TTK'],
            ['Sri Wahyuni',         '3201010404880004', 'Pengawas Workshop',       'Workshop',  'PT'],
            ['Bayu Pratama',        '3201010505920005', 'Surveyor Tambang',        'Engineering','TTK'],
        ];

        /* [sertifikat, hari sampai kadaluarsa] · null = tanpa tanggal */
        $sertifikat = [
            0 => [['Pengawas Operasional Utama (POU)', 620], ['Implementasi SMKP', 200]],
            1 => [['Pengawas Operasional Madya (POM)', 410], ['Auditor SMKP', 75],
                  ['AK3U BNSP', -30]],
            2 => [['SIO Kelas 2 ( Surat Ijin Operator ) beban 25 - 50 Ton', 45],
                  ['Rigger', 150]],
            3 => [['Pengawas Operasional Pertama (POP)', 300], ['Petugas P3K', null]],
            4 => [['Juru Ukur', 520]],
        ];

        /* [hari kadaluarsa MCU, hasil, pembatasan] */
        $mcu = [
            0 => [210, 'Fit', null],
            1 => [140, 'Fit With Note', 'Pemeriksaan tekanan darah tiap enam bulan.'],
            2 => [-12, 'Fit', null],                                   // kadaluarsa
            3 => [95,  'Temporary Unfit', 'Cedera punggung; tidak boleh mengangkat beban.'],
            4 => [330, 'Fit', null],
        ];

        /* [jenis kartu, hari kadaluarsa, golongan, status alur]

           Statusnya sengaja bervariasi. Bila seluruhnya disetujui, layar
           tidak pernah memperlihatkan seperti apa kartu yang masih
           menunggu — dan alur persetujuannya tampak seperti hiasan yang
           tidak pernah menahan apa pun. */
        $kartu = [
            0 => [['Mine Permit', 400, null, Alur::DISETUJUI],
                  ['SIMPER', 180, 'LV', Alur::DISETUJUI]],
            1 => [['Mine Permit', 250, null, Alur::DISETUJUI]],
            2 => [['Mine Permit', 60, null, Alur::DISETUJUI],
                  ['SIMPER', 20, 'Alat Berat', Alur::DIAJUKAN]],
            3 => [['Mine Permit', 310, null, Alur::DISETUJUI]],
            4 => [['Mine Permit', -5, null, Alur::DISETUJUI]],         // kartu habis
        ];

        /* [hari kadaluarsa induksi, jenis, hasil, nilai]

           Orang ke-4 induksinya sudah lewat: satu-satunya sebab ia
           tertahan, sementara MCU dan kartunya aman. Tanpa satu contoh
           seperti itu, induksi tidak pernah terlihat benar-benar
           menentukan. */
        $induksi = [
            0 => [[500, 'Awal', 'Lulus', 92]],
            1 => [[280, 'Penyegaran', 'Lulus', 88]],
            2 => [[120, 'Awal', 'Lulus', 76]],
            3 => [[-40, 'Penyegaran', 'Lulus', 81]],                   // induksi kadaluarsa
            4 => [[60, 'Awal', 'Tidak Lulus', 58], [400, 'Awal', 'Lulus', 84]],
        ];

        foreach ($orang as $i => [$nama, $nik, $jabatan, $dept, $klas]) {
            $p = $this->baru(Paspor::class, [
                'user_id'        => $i === 0 ? $this->peninjau?->getKey()
                                  : ($i === 2 ? $this->pengaju?->getKey() : null),
                'nomor_register' => 'REG-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'nama'           => $nama,
                'nik'            => $nik,
                'jabatan'        => $jabatan,
                'departemen'     => $dept,
                'klasifikasi'    => $klas,
                'status'         => 'aktif',
                'tgl_bergabung'  => $this->kini->copy()->subYears(3 + $i)->toDateString(),
            ]);
            $n++;

            foreach ($sertifikat[$i] ?? [] as $j => [$namaSert, $hari]) {
                PasporSertifikat::withoutGlobalScopes()->create([
                    'paspor_id'           => $p->id,
                    'kompetensi_jenis_id' => $jenis[$namaSert] ?? null,
                    'nama'                => $namaSert,
                    'nomor'               => 'SRT/'.$this->kini->year.'/'
                        .str_pad((string) ($i * 10 + $j + 1), 4, '0', STR_PAD_LEFT),
                    'tgl_terbit'          => $this->kini->copy()->subYears(2)->toDateString(),
                    'tgl_expired'         => $hari === null
                        ? null : $this->kini->copy()->addDays($hari)->toDateString(),
                ]);
                $n++;
            }

            [$hariMcu, $hasil, $batas] = $mcu[$i];

            $barisMcu = PasporMcu::withoutGlobalScopes()->create([
                'paspor_id'     => $p->id,
                'tgl_periksa'   => $this->kini->copy()->addDays($hariMcu)->subYear()->toDateString(),
                'tgl_expired'   => $this->kini->copy()->addDays($hariMcu)->toDateString(),
                'penyelenggara' => 'Klinik Pratama Sehat Tambang',
                'jenis'         => 'Berkala',
                'hasil'         => $hasil,
                'pembatasan'    => $batas,

                /* Medan yang dibaca dari D'Best. Usia disimpan apa
                   adanya — yang tercetak pada suratnya adalah usia saat
                   pemeriksaan, dan menghitungnya ulang tahun depan
                   memberi angka yang berbeda dari suratnya. */
                'usia'           => 28 + $i * 3,
                'mcu_berikutnya' => $this->kini->copy()->addDays($hariMcu - 30)->toDateString(),

                /* Satu orang sengaja dibiarkan BELUM diverifikasi:
                   tanpa satu pun contohnya, tampilan "belum diperiksa"
                   tidak pernah benar-benar tergambar. */
                'status_verifikasi' => $i === 1
                    ? Authority::MCU_MENUNGGU : Authority::MCU_TERVERIFIKASI,
                'catatan_kontraktor' => $i === 1
                    ? 'Berkas menyusul dari klinik rujukan.' : null,

                /* Level risiko menyebar, tidak seragam. Kolom yang
                   seluruh barisnya berisi "Rendah" memperlihatkan
                   halaman yang BEKERJA tetapi tidak memperlihatkan apa
                   gunanya — yang dicari orang justru ekor atasnya.
                   Salah satunya sengaja Fit-tetapi-Tinggi: itulah baris
                   yang menjadi isi angka "Risiko tinggi" pada ringkasan. */
                'level_risiko' => [
                    Authority::RISIKO_RENDAH,
                    Authority::RISIKO_TINGGI,
                    Authority::RISIKO_SEDANG,
                ][$i % 3],

                /* Satu rujukan yang batasnya SUDAH LEWAT, supaya angka
                   "Rujukan tertunggak" pada ringkasan riwayat punya isi.
                   Sebelum medannya dapat dijangkau formulir, angka itu
                   selamanya nol — dan nol terbaca sebagai "tidak ada
                   yang tertunggak", bukan sebagai "belum dapat diisi". */
                'rujukan'     => $i === 1 ? 'Rujukan Sp.PD — tekanan darah' : null,
                'outstanding' => $i === 1
                    ? $this->kini->copy()->subDays(20)->toDateString() : null,

                'nomor' => 'MCU/'.$this->kini->year.'/'
                    .str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
            ]);
            $n++;

            foreach ($kartu[$i] as [$jenisKartu, $hariKartu, $gol, $statusKartu]) {
                $k = PasporKartu::withoutGlobalScopes()->create([
                    'paspor_id'   => $p->id,
                    'jenis'       => $jenisKartu,
                    'sebab_terbit' => 'Terbit',
                    'nomor'       => ($jenisKartu === 'SIMPER' ? 'ML/' : 'MP/')
                        .str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                    'tgl_terbit'  => $this->kini->copy()->addDays($hariKartu)->subYear()->toDateString(),
                    'tgl_expired' => $this->kini->copy()->addDays($hariKartu)->toDateString(),
                    'golongan'    => $gol,
                    'area'        => $gol ? 'Seluruh area tambang' : 'Area kantor dan workshop',

                    /* Berkas syarat. SIMPER menuntut ketiganya; kartu
                       masuk biasa hanya bukti induksinya. */
                    'berkas_induksi' => 'induksi/'.$p->nomor_register.'.pdf',
                    'sim_polisi'     => $gol ? 'SIM-B2-'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT) : null,
                    'sim_polisi_expired' => $gol
                        ? $this->kini->copy()->addDays($hariKartu + 200)->toDateString() : null,
                    'berkas_ddt'     => $gol ? 'ddt/'.$p->nomor_register.'.pdf' : null,
                    'email_atasan'   => 'atasan@eqohsee.id',

                    /* Tercetak pada kartunya sendiri. */
                    'golongan_darah' => ['A', 'B', 'O', 'AB'][$i % 4],
                    'telepon'        => '08123456'.str_pad((string) ($i + 10), 4, '0', STR_PAD_LEFT),
                    'kontak_darurat' => 'Keluarga · 08998877'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                    'tgl_lahir'      => $this->kini->copy()->subYears(28 + $i * 3)->toDateString(),
                    'subkontraktor'  => $i % 2 ? 'PT Mitra Karya Tambang' : null,
                    'jenis_sim'      => $gol ? 'B2 Umum' : null,

                    /* ── rantai berkas ──
                       Atas dasar apa kartu ini diterbitkan. Ditulis
                       sekali pada penerbitan; MCU baru yang datang
                       kemudian TIDAK menggesernya, dan selisih itulah
                       yang membuat "dasar usang" dapat terlihat. */
                    'paspor_mcu_id'  => $barisMcu->id,

                    /* Lampiran syarat permit. Sengaja TIDAK lengkap
                       semuanya: satu kartu dibiarkan tanpa SPDK supaya
                       tampilan "belum lengkap" benar-benar pernah
                       tergambar, bukan hanya ada di kode. */
                    'berkas_ktp'        => 'ktp/'.$p->nomor_register.'.pdf',
                    'berkas_permohonan' => 'permohonan/'.$p->nomor_register.'.pdf',
                    'berkas_spdk'       => $i === 0 ? null : 'spdk/'.$p->nomor_register.'.pdf',
                    'berkas_dept'       => $gol ? 'dept/'.$p->nomor_register.'.pdf' : null,
                    'berkas_lotto'      => $gol ? 'lotto/'.$p->nomor_register.'.pdf' : null,
                    'berkas_blasting'   => null,
                ]);
                $n++;

                /* Unit SIMPER — hanya pada SIMPER, dan sengaja dua
                   baris dengan hasil berbeda: satu lulus, satu belum.
                   Seluruhnya lulus tidak pernah memperlihatkan seperti apa
                   unit yang tertahan itu tampak di layar. */
                if ($jenisKartu === 'SIMPER') {
                    foreach ([
                        ['F', 'EXCAVATOR', 'Komatsu PC 200', 82, 82],
                        ['T', 'EXCAVATOR', 'Komatsu PC 500', 64, 58],
                    ] as [$auth, $unit, $merk, $p2h, $praktek]) {
                        PasporKartuUnit::withoutGlobalScopes()->create([
                            'paspor_kartu_id' => $k->id,
                            'authority'       => $auth,
                            'jenis_unit'      => $unit,
                            'type_merk'       => $merk,
                            'nilai_p2h'       => $p2h,
                            'nilai_praktek'   => $praktek,
                            'berkas_rambu'    => 'simper/rambu-'.$k->id.'.pdf',
                            'berkas_teori'    => 'simper/teori-'.$k->id.'.pdf',
                            'hasil_praktek'   => $p2h >= 70 ? 'simper/praktek-'.$k->id.'.pdf' : null,
                            'evaluasi'        => $p2h >= 70 ? 'simper/evaluasi-'.$k->id.'.pdf' : null,
                        ]);
                        $n++;
                    }
                }

                /* Status TIDAK dapat diisi lewat create(): trait Ditinjau
                   memaksa setiap baris baru lahir sebagai draf, tepat
                   supaya tidak ada jalan memasang "disetujui" tanpa
                   melewati alurnya. Data contoh memang perlu menembusnya,
                   dan menembusnya dengan pembaruan langsung ke basis data
                   membuat penembusan itu terlihat — bukan tersembunyi
                   sebagai kolom yang diam-diam boleh diisi. */
                if ($statusKartu !== Alur::DRAF) {
                    PasporKartu::withoutGlobalScopes()->whereKey($k->id)->update([
                        'status'        => $statusKartu,
                        'diajukan_oleh' => $this->pengaju?->getKey(),
                        'diajukan_pada' => $this->kini->copy()->subDays(20),
                        'ditinjau_oleh' => $statusKartu === Alur::DISETUJUI
                            ? $this->peninjau?->getKey() : null,
                        'ditinjau_pada' => $statusKartu === Alur::DISETUJUI
                            ? $this->kini->copy()->subDays(18) : null,
                    ]);

                    /* Yang sudah disetujui parafnya lengkap; yang masih
                       menunggu sengaja BARU SATU. Rantai yang seluruhnya
                       terisi tidak pernah memperlihatkan seperti apa
                       tampilan tahap yang tertinggal — padahal itulah
                       yang paling sering dilihat orang. */
                    $n += $this->paraf($k, $statusKartu === Alur::DISETUJUI
                        ? [Tahap::ATASAN, Tahap::DEPARTEMEN]
                        : [Tahap::ATASAN]);
                }
            }

            foreach ($induksi[$i] as $j => [$hariInduksi, $jenisInduksi, $hasilInduksi, $nilai]) {
                PasporInduksi::withoutGlobalScopes()->create([
                    'paspor_id'        => $p->id,
                    'nomor_registrasi' => 'IND/'.$this->kini->year.'/'
                        .str_pad((string) ($i * 10 + $j + 1), 4, '0', STR_PAD_LEFT),
                    'jenis'       => $jenisInduksi,
                    'tanggal'     => $this->kini->copy()->addDays($hariInduksi)->subYear()->toDateString(),
                    'tgl_expired' => $this->kini->copy()->addDays($hariInduksi)->toDateString(),
                    'pemberi'     => 'Departemen OHSE',
                    'lokasi'      => 'Ruang Induksi — Pos Utama',
                    'nilai'       => $nilai,
                    'hasil'       => $hasilInduksi,
                ]);
                $n++;
            }
        }

        return $n + $this->pengajuanMcu();
    }

    /**
     * Membubuhkan paraf contoh pada tahap-tahap yang disebut.
     *
     * Ditulis langsung ke tabelnya, tidak lewat bubuhkanParaf(): metode
     * itu menolak paraf dari pengajunya sendiri, dan data contoh hanya
     * punya dua pengguna. Penjagaannya tetap diuji — lihat MinersTest
     * — dan yang ditembus di sini terlihat sebagai penembusan, bukan
     * tersembunyi sebagai jalan yang ternyata memang terbuka.
     *
     * Memulangkan JUMLAH BARIS, bukan sekadar menandai sudah dipanggil.
     * Hitungan yang dilaporkan pemuat harus sama persis dengan yang
     * nanti terbuang; menghitung "sekali per panggilan" membuat selisih
     * yang muncul sebagai kegagalan uji muat-ulang di tempat yang jauh
     * dari sebabnya.
     *
     * @param  list<string>  $tahap
     */
    private function paraf(object $subjek, array $tahap): int
    {
        foreach ($tahap as $i => $t) {
            $subjek->paraf()->create([
                'tahap'   => $t,
                'user_id' => $this->peninjau?->getKey(),
                'nama'    => $this->peninjau?->name ?? 'Pengawas',
                'jabatan' => Tahap::label($t),
                'created_at' => $this->kini->copy()->subDays(19 - $i),
            ]);
        }

        return count($tahap);
    }

    /**
     * Dua surat pengajuan MCU: satu sudah kembali hasilnya, satu belum.
     *
     * Keduanya diperlukan. Yang sudah lengkap memperlihatkan bentuk
     * akhirnya; yang belum memperlihatkan keadaan yang sebenarnya paling
     * sering ditemui — surat yang sudah dikirim dan hasilnya ditunggu —
     * dan itulah keadaan yang layarnya harus bisa menampilkan dengan
     * jelas.
     */
    private function pengajuanMcu(): int
    {
        /* Disaring ke perusahaan ini SENDIRI. Tanpa itu, memuat data
           contoh untuk perusahaan kedua akan menyusun suratnya dari nama
           milik perusahaan pertama — kebocoran yang dibuat sendiri oleh
           penyemai, dan justru pada tabel yang paling diawasi. */
        $orang = Paspor::withoutGlobalScopes()
            ->where('company_id', $this->c->getKey())
            ->orderBy('id')->get();

        if ($orang->isEmpty()) return 0;

        $n = 0;

        /* [nomor, hari, kepada, jenis, status, sudah kembali, ambil dari, berapa]

           Nama yang diikutkan dipilih supaya TIDAK menimpa keadaan yang
           sengaja dibuat cacat di atas. Surat pertama yang hasilnya sudah
           kembali hanya memuat dua orang pertama; bila ia memuat orang
           ketiga, MCU-nya yang sengaja dibuat kadaluarsa akan tergantikan
           hasil baru yang sehat — dan satu-satunya contoh MCU kadaluarsa
           di seluruh data hilang tanpa ada yang menyadarinya. */
        $surat = [
            ['MCU/EQ/2026/001', -45, 'Klinik Pratama Sehat Tambang', 'Berkala', Alur::DISETUJUI, true,  0, 2],
            ['MCU/EQ/2026/002', -6,  'RS Umum Daerah Kabupaten',     'Khusus',  Alur::DIAJUKAN,  false, 2, 3],
        ];

        foreach ($surat as [$nomor, $hari, $kepada, $jenis, $status, $sudahKembali, $dari, $berapa]) {
            $p = McuPengajuan::withoutGlobalScopes()->create([
                'company_id'     => $this->c->getKey(),
                'user_id'        => $this->pengaju?->getKey(),
                'nomor_register' => $nomor,
                'tanggal'        => $this->kini->copy()->addDays($hari)->toDateString(),
                'kepada'         => $kepada,
                'judul'          => 'Permohonan pemeriksaan kesehatan '.strtolower($jenis).' pekerja',
                'jenis'          => $jenis,
            ]);
            $n++;

            McuPengajuan::withoutGlobalScopes()->whereKey($p->id)->update([
                'status'        => $status,
                'diajukan_oleh' => $this->pengaju?->getKey(),
                'diajukan_pada' => $this->kini->copy()->addDays($hari)->addDay(),
                'ditinjau_oleh' => $status === Alur::DISETUJUI ? $this->peninjau?->getKey() : null,
                'ditinjau_pada' => $status === Alur::DISETUJUI
                    ? $this->kini->copy()->addDays($hari)->addDays(2) : null,
            ]);

            /* Rantai MCU: paramedis lalu KTT — bukan rantai kartu. */
            $n += $this->paraf($p, $status === Alur::DISETUJUI
                ? [Tahap::PARAMEDIS, Tahap::KTT]
                : [Tahap::PARAMEDIS]);

            foreach ($orang->slice($dari, $berapa)->values() as $t => $o) {
                PasporMcu::withoutGlobalScopes()->create([
                    'paspor_id'        => $o->id,
                    'mcu_pengajuan_id' => $p->id,
                    'tgl_periksa'      => $this->kini->copy()->addDays($hari)->addDays(7)->toDateString(),
                    'penyelenggara'    => $kepada,
                    'jenis'            => $jenis,

                    /* Yang belum kembali BENAR-BENAR kosong hasilnya —
                       bukan diisi "Fit" sebagai nilai awal. Hasil yang
                       ditebak tidak dapat dibedakan dari hasil sungguhan
                       begitu halamannya ditutup. */
                    'hasil'       => $sudahKembali ? ($t === 1 ? 'Fit With Note' : 'Fit') : null,
                    'nomor'       => $sudahKembali ? str_replace('MCU/', 'HSL/', $nomor).'-'.($t + 1) : null,
                    'tgl_expired' => $sudahKembali
                        ? $this->kini->copy()->addDays($hari)->addDays(372)->toDateString() : null,
                    'rujukan'     => $sudahKembali && $t === 1 ? 'Poli Jantung — kontrol tekanan darah' : null,
                    'outstanding' => $sudahKembali && $t === 1
                        ? $this->kini->copy()->subDays(9)->toDateString() : null,  // tertunggak
                ]);
                $n++;
            }
        }

        return $n + $this->pengajuanInduksi($orang);
    }

    /**
     * Kelas induksi — kembar dengan surat pengajuan MCU di atas.
     *
     * Dua kelas: satu yang sudah selesai dan dinilai, satu yang masih
     * menunggu tinjauan dengan pesertanya belum dinilai. Yang kedua ada
     * supaya angka "hasil belum dinilai" pada ringkasan punya isi —
     * angka yang selalu nol tidak terbaca sebagai "belum dapat diisi"
     * melainkan sebagai "semua sudah selesai".
     *
     * @param  \Illuminate\Support\Collection  $orang
     */
    private function pengajuanInduksi($orang): int
    {
        if ($orang->isEmpty()) return 0;

        $n = 0;

        /* [nomor, hari, lokasi, jenis, status, sudah dinilai, ambil dari, berapa] */
        $kelas = [
            ['IND/EQ/2026/001', -40, 'Ruang Kelas Safety — Site A', 'Awal',       Alur::DISETUJUI, true,  0, 2],
            ['IND/EQ/2026/002', -4,  'Ruang Kelas Safety — Site A', 'Penyegaran', Alur::DIAJUKAN,  false, 1, 3],
        ];

        foreach ($kelas as [$nomor, $hari, $lokasi, $jenis, $status, $sudahDinilai, $dari, $berapa]) {
            $p = InduksiPengajuan::withoutGlobalScopes()->create([
                'company_id'      => $this->c->getKey(),
                'user_id'         => $this->pengaju?->getKey(),
                'nomor_register'  => $nomor,
                'tanggal'         => $this->kini->copy()->addDays($hari)->toDateString(),
                'judul'           => 'Induksi keselamatan '.strtolower($jenis).' pekerja',
                'jenis'           => $jenis,
                'lokasi'          => $lokasi,
                'tgl_pelaksanaan' => $this->kini->copy()->addDays($hari)->addDays(3)->toDateString(),
            ]);
            $n++;

            InduksiPengajuan::withoutGlobalScopes()->whereKey($p->id)->update([
                'status'        => $status,
                'diajukan_oleh' => $this->pengaju?->getKey(),
                'diajukan_pada' => $this->kini->copy()->addDays($hari)->addDay(),
                'ditinjau_oleh' => $status === Alur::DISETUJUI ? $this->peninjau?->getKey() : null,
                'ditinjau_pada' => $status === Alur::DISETUJUI
                    ? $this->kini->copy()->addDays($hari)->addDays(2) : null,
            ]);

            /* Rantai induksi BERHENTI DI OHSE — tidak ada tahap paraf
               sama sekali, sebab OHSE sendiri yang menyelenggarakannya. */
            foreach ($orang->slice($dari, $berapa)->values() as $t => $o) {
                PasporInduksi::withoutGlobalScopes()->create([
                    'paspor_id'            => $o->id,
                    'induksi_pengajuan_id' => $p->id,
                    'jenis'                => $jenis,
                    'tanggal'              => $this->kini->copy()->addDays($hari)->addDays(3)->toDateString(),
                    'lokasi'               => $lokasi,

                    /* Yang belum dinilai BENAR-BENAR kosong hasilnya. */
                    'hasil'       => $sudahDinilai ? ($t === 1 ? 'Mengulang' : 'Lulus') : null,
                    'nilai'       => $sudahDinilai ? ($t === 1 ? 62 : 88) : null,
                    'nomor_registrasi' => $sudahDinilai
                        ? str_replace('IND/', 'SRT/', $nomor).'-'.($t + 1) : null,
                    'tgl_expired' => $sudahDinilai
                        ? $this->kini->copy()->addDays($hari)->addDays(368)->toDateString() : null,
                    'pemberi'     => $sudahDinilai ? ($this->peninjau?->name ?? 'Tim OHSE') : null,
                ]);
                $n++;
            }
        }

        return $n;
    }

    /* ─────────── TPKKP ─────────── */

    /**
     * Penilaian kinerja keselamatan, nilainya dibangkitkan dari
     * instrumennya sendiri.
     *
     * Nilai TIDAK ditulis tetap. Instrumen TPKKP berisi ratusan item
     * dengan kode yang dapat berubah bila instrumennya diperbarui, dan
     * kode yang ditulis tetap akan diam-diam berhenti cocok — hasilnya
     * penilaian yang tampil sebagai nol pada seluruh parameter tanpa
     * satu pun galat. Dibangkitkan dari `Tpkkp::allItems()`, nilainya
     * ikut mengikuti instrumen yang sedang berlaku.
     *
     * Sebarannya sengaja tidak rata dan sengaja tidak lengkap: sebagian
     * item dibiarkan kosong. Penilaian yang seluruh itemnya terisi
     * penuh tidak memperlihatkan bagaimana halaman ini menandai yang
     * belum dikerjakan — dan itulah yang paling sering ditanyakan.
     */
    private function tpkkp(): int
    {
        $n = 0;

        try {
            $item   = Tpkkp::allItems();
            $metode = Tpkkp::methods();       // dipetakan menurut KODE, bukan berindeks
        } catch (\Throwable $e) {
            /* Instrumennya berkas, bukan tabel. Bila hilang, modul lain
               tidak boleh ikut gagal dimuat. */
            $this->catatan[] = 'TPKKP: instrumen tidak terbaca — '.$e->getMessage();

            return 0;
        }

        if (!$item || !$metode) return 0;

        foreach ([['selesai', 1], ['berjalan', 0]] as [$status, $mundur]) {
            $tahun  = $this->kini->year - $mundur;
            $scores = [];

            foreach ($item as $ii => $it) {
                /* Hanya metode yang MEMANG dipakai item itu. Menilai
                   dengan metode yang tidak disebut instrumen tidak
                   pernah terbaca — nilainya tersimpan, tidak terhitung,
                   dan tidak dapat dijelaskan asalnya. */
                foreach (($it['methods'] ?? []) as $mi => $m) {
                    /* Yang berjalan baru terisi sebagian — itulah yang
                       membedakannya dari yang sudah selesai, dan yang
                       memperlihatkan bagaimana item kosong ditandai. */
                    if ($status === 'berjalan' && ($ii + $mi) % 3 !== 0) continue;

                    $nilai = 2 + (($ii * 7 + $mi * 3) % 4);   // 2..5
                    $ent   = array_slice($metode[$m]['entities'] ?? [], 0, 2);

                    /* Metode berentitas dinilai PER ENTITAS; itulah yang
                       dirata-ratakan mesin hitungnya. Mengisi 'v' pada
                       metode berentitas menghasilkan angka yang benar
                       pada rekap keseluruhan tetapi nol pada rekap per
                       perusahaan. */
                    $scores[$m][$it['code']] = $ent
                        ? ['v' => null,
                           'e' => array_combine($ent, [$nilai, max(1, $nilai - 1)]),
                           'ket' => '']
                        : ['v' => $nilai, 'e' => [], 'ket' => ''];
                }
            }

            $this->baru(TpkkpAssessment::class, [
                'tahun'    => $tahun,
                'judul'    => 'Penilaian Kinerja Keselamatan Pertambangan '.$tahun,
                'status'   => $status,
                'scores'   => $scores,
                'roster'   => [],
                'profil'   => [
                    'organisasi' => $this->c->name,
                    'site'       => 'Site Utama',
                    'komoditas'  => 'Batubara',
                    'ktt'        => $this->peninjau?->name ?? 'Kepala Teknik Tambang',
                    'periode'    => (string) $tahun,
                ],
                'tim'      => [
                    ['nama' => $this->peninjau?->name ?? 'Kepala Teknik Tambang', 'peran' => 'Ketua'],
                    ['nama' => $this->pengaju?->name  ?? 'Pengawas Operasional',  'peran' => 'Anggota'],
                ],
                'programs' => Tpkkp::programSeed(),
                'jadwal'   => [],
                'sampling' => [],
            ]);
            $n++;
        }

        /* Tanggapan kuesioner dari lapangan; tiga kategori responden
           supaya rekap per kategori punya lebih dari satu batang. */
        foreach ([
            ['pekerja',   'Operator Dump Truck', 'Produksi'],
            ['pengawas',  'Pengawas Operasional', 'Produksi'],
            ['manajemen', 'Manajer OHSE',         'OHSE'],
        ] as $i => [$cat, $jabatan, $dept]) {
            $this->baru(TpkkpResponse::class, [
                'ext_id'     => 'RESP-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'cat'        => $cat,
                'nrp'        => '20'.str_pad((string) (140 + $i), 4, '0', STR_PAD_LEFT),
                'jabatan'    => $jabatan,
                'dept'       => $dept,
                'perusahaan' => $this->c->name,
                'answers'    => ['q1' => 4, 'q2' => 3 + ($i % 2), 'q3' => 5, 'q4' => 4],
                'ts'         => $this->kini->copy()->subDays(30 - $i * 4),
            ]);
            $n++;
        }

        $n += $this->tpkkpPengujian();

        return $n;
    }

    /**
     * Hasil pengujian (metode PJ) — enam peserta, sengaja tidak seragam.
     *
     * Nilainya menyebar dari 6/15 sampai 14/15 supaya sebaran tingkat di
     * halaman admin punya lebih dari satu batang. Sebaran yang rata —
     * enam peserta bertingkat sama — memperlihatkan halaman yang
     * BEKERJA tetapi tidak memperlihatkan apa gunanya: yang dicari
     * seorang penilai justru ekor bawahnya.
     *
     * Tingkatnya dihitung lewat TpkkpUji::tingkatDari, bukan ditulis
     * tetap. Pita konversinya ada di berkas instrumen dan dapat
     * berubah; angka tetap akan diam-diam berhenti cocok dengan pita
     * yang berlaku, dan data contoh yang tidak konsisten dengan
     * mesinnya sendiri lebih menyesatkan daripada tidak ada data.
     *
     * Satu peserta diberi `pindah_layar` di atas nol supaya kolom
     * indikasi itu punya isi — kolom yang seluruhnya nol tampak seperti
     * kolom yang tidak berfungsi.
     */
    private function tpkkpPengujian(): int
    {
        $total = TpkkpUji::jumlahSoal();
        $n     = 0;

        $peserta = [
            ['Suryanto',      'Operator Dump Truck',  'Mining / Operation / Produksi', 14, 0],
            ['Rahmat Hidayat','Mekanik',              'Plant',                         12, 0],
            ['Dewi Lestari',  'Admin',                'HRGA / HRO',                    10, 2],
            ['Bagus Prakoso', 'Crew / Helper',        'Mining / Operation / Produksi',  9, 0],
            ['Iwan Setiawan', 'Driver',               'CHF / Port',                     7, 1],
            ['Marta Sinaga',  'Security',             'HRGA / HRO',                     5, 0],
        ];

        foreach ($peserta as $i => [$nama, $jabatan, $dept, $benar, $pindah]) {
            $benar = min($benar, $total);
            $pct   = $total ? $benar / $total : 0.0;

            $mulai = $this->kini->copy()->subDays(12 - $i)->setTime(9 + $i % 6, 15);

            $this->baru(TpkkpPengujian::class, [
                'ext_id'       => 'UJI-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'nama'         => $nama,
                'nrp'          => '20'.str_pad((string) (210 + $i), 4, '0', STR_PAD_LEFT),
                'jabatan'      => $jabatan,
                'dept'         => $dept,
                'perusahaan'   => $this->c->name,
                'kunci_identitas' => TpkkpUji::kunciIdentitas([
                    'nama' => $nama, 'jabatan' => $jabatan,
                    'dept' => $dept, 'perusahaan' => $this->c->name,
                ]),
                'benar'        => $benar,
                'total'        => $total,
                'skor_pct'     => round($pct, 4),
                'tingkat'      => TpkkpUji::tingkatDari($pct),
                'durasi_detik' => 420 + $i * 55,
                'pindah_layar' => $pindah,
                'mulai'        => $mulai,
                'ts'           => $mulai->copy()->addSeconds(420 + $i * 55),
            ]);
            $n++;
        }

        return $n;
    }

    /* ─────────── pesan internal ─────────── */

    /**
     * Satu percakapan dengan pesan di kedua arah.
     *
     * Satu arah saja tidak cukup: halaman pesan membedakan gelembung
     * kiri dan kanan, menghitung yang belum terbaca, dan mengurutkan
     * percakapan menurut pesan terakhir. Ketiganya tidak dapat dilihat
     * dari percakapan yang hanya berisi pesan sendiri.
     */
    /* ─────────── Investigasi kecelakaan ─────────── */

    /**
     * Satu berkas investigasi yang lengkap dari ujung ke ujung.
     *
     * SATU, BUKAN LIMA, dan itu keputusan yang berbeda dari modul lain
     * di berkas ini. Modul lain diisi beberapa baris supaya sebaran dan
     * penyaringnya dapat diperiksa. Investigasi tidak diperiksa lewat
     * sebaran melainkan lewat RANTAINYA — bukti menyokong akar masalah,
     * akar melahirkan temuan, temuan melahirkan tindakan — dan rantai
     * itu hanya terlihat kalau satu berkas benar-benar utuh. Lima berkas
     * setengah jadi memenuhi tiap tabel dengan angka bukan-nol sambil
     * tidak memperlihatkan satu pun rantai yang tersambung.
     *
     * Levelnya sengaja L3. Di L1 dan L2 tahap Verifikasi dilewati,
     * sehingga tabel verifikasi tindakan tidak akan pernah terisi oleh
     * data contoh — dan bagian yang tidak pernah terisi adalah bagian
     * yang tidak pernah diperiksa siapa pun.
     */
    private function investigasi(): int
    {
        /* Masternya dipasang lebih dulu, dan pemasangannya idempoten.
           Tanpa ini, data contoh pada pemasangan yang belum pernah
           menjalankan `investigasi:pasang` akan menunjuk klasifikasi
           yang tidak ada — dan triasenya diam-diam tidak menghasilkan
           level apa pun. */
        MasterInvestigasi::pasang();

        $n = 0;

        $lokasi = InvLokasi::withoutGlobalScopes()->create([
            'company_id' => $this->c->id,
            'kode' => 'HR-KM4', 'nama' => 'Jalan Hauling KM 4',
            'area' => 'Pit Selatan', 'aktif' => true,
        ]);
        $n++;

        $kejadian = $this->kini->copy()->subDays(12)->setTime(14, 20);

        $insiden = InvInsiden::withoutGlobalScopes()->create([
            'company_id'       => $this->c->id,
            'no_insiden'       => NomorInvestigasi::terbitkan(NomorInvestigasi::INSIDEN, (int) $kejadian->format('Y')),
            'judul'            => 'Dump truck menabrak tanggul pengaman di jalan hauling KM 4',
            'tanggal_kejadian' => $kejadian->toDateString(),
            'waktu_kejadian'   => $kejadian->format('H:i:s'),
            'dilaporkan_pada'  => $kejadian->copy()->addHours(2),
            'lokasi_id'        => $lokasi->id,
            'lokasi_rinci'     => 'Tikungan menurun sesudah simpang workshop',
            'aktivitas'        => 'Hauling overburden dari Pit Selatan ke disposal',
            'jenis_insiden_id' => DB::table('inv_jenis_insiden')->where('kode', 'tabrakan')->value('id'),
            'klasifikasi_cedera_id' => DB::table('inv_klasifikasi_cedera')->where('kode', 'ringan')->value('id'),
            'pelapor_id'       => $this->pengaju?->id,
            'kronologi'        => 'Unit HD-785 keluar dari pit bermuatan penuh. Pada tikungan menurun '
                .'sesudah simpang workshop, pengemudi mengerem dan pedal terasa dalam. Unit tidak berhenti '
                .'pada jarak biasa dan menabrak tanggul pengaman sisi kiri, berhenti sekitar 12 meter dari '
                .'titik pengereman. Area tikungan gelap karena dua lampu penerangan mati sejak pekan lalu.',
            'tindakan_segera'  => 'Unit diamankan dan ditarik ke workshop. Jalur dialihkan lewat ramp lama. '
                .'Pengemudi diperiksa paramedis, lecet pada siku kiri.',

            /* Enam pemicu saran lapis 3 — dicentang pelapor di formulir,
               dan justru kolom inilah yang dulu tidak punya medan sama
               sekali sehingga lapis 3 tidak pernah menyala. */
            'p_shift_malam'      => true,
            'p_sop_tidak_ada'    => false,
            'p_belum_dilatih'    => false,
            'p_inspeksi_absen'   => true,
            'p_insiden_berulang' => true,
            'p_lembur_panjang'   => true,
        ]);
        $n++;

        $insiden->orang()->create([
            'nama' => 'Budi Santoso', 'jabatan' => 'Operator HD',
            'perusahaan' => $this->c->name, 'peran' => 'korban',
            'bagian_tubuh' => 'Siku kiri', 'rincian_cedera' => 'Lecet, dibalut di klinik site.',
            'hari_hilang' => 2,
        ]);
        $insiden->orang()->create([
            'nama' => 'Hendra Gunawan', 'jabatan' => 'Pengawas Hauling',
            'perusahaan' => $this->c->name, 'peran' => 'saksi',
        ]);
        $n += 2;

        /* Triase dijalankan lewat jalur yang sama dengan layarnya —
           bukan dengan menulis level langsung ke kolomnya. Menulisnya
           langsung membuat data contoh tetap benar meskipun rumus
           triasenya rusak, dan itu justru menyembunyikan kerusakannya. */
        InvTriase::terapkan($insiden, [
            'kemungkinan' => 3, 'keparahan' => 3, 'keparahan_potensial' => 4,
            'klasifikasi_regulasi_id' => DB::table('inv_klasifikasi_regulasi')
                ->where('kode', 'kecelakaan_tambang')->value('id'),
            'k1_benar_terjadi' => 1, 'k2_mencederai_pekerja' => 1,
            'k3_akibat_kegiatan' => 1, 'k4_jam_kerja' => 1, 'k5_wilayah_usaha' => 1,
        ]);

        $insiden->update([
            'status' => 'diselidiki',
            'dilaporkan_kait_pada' => $kejadian->copy()->addHours(6),
        ]);

        $inv = $insiden->investigasi()->create([
            'no_investigasi' => NomorInvestigasi::terbitkan(NomorInvestigasi::INVESTIGASI, (int) $kejadian->format('Y')),
            'ketua_id'       => $this->peninjau?->id ?? $this->pengaju?->id,
            'prioritas'      => 'tinggi',
            'target_selesai' => $kejadian->copy()->addDays(30)->toDateString(),
            'tahap'          => 'verifikasi',
            'tujuan'         => 'Menetapkan penyebab kegagalan pengereman dan mencegah terulangnya '
                .'pada unit sejenis.',
            'ruang_lingkup'  => 'Seluruh unit HD-785 yang melintasi jalan hauling bergradien.',
            'metode'         => 'ICAM, Bowtie, 5 Why, Kronologi, Analisis Penghalang',
        ]);
        $n++;

        if ($this->pengaju) {
            $inv->tim()->create(['user_id' => $this->pengaju->id, 'peran_tim' => 'Anggota']);
            $n++;
        }

        /* ── kronologi ── */
        $peristiwa = [
            ['-08:25', 'Unit keluar dari pit bermuatan penuh', 'Muatan tercatat 91 ton, dalam batas.', false],
            ['-08:10', 'Pemeriksaan awal (P2H) tidak mencatat keluhan rem', 'Lembar P2H pagi itu diisi tanpa catatan.', true],
            ['-00:01', 'Operator mengerem memasuki tikungan', 'Menurut pernyataan, pedal terasa dalam.', true],
            ['+00:00', 'Unit menabrak tanggul dan berhenti', 'Jarak henti sekitar 12 meter dari titik pengereman.', false],
        ];

        foreach ($peristiwa as $i => [$geser, $judul, $ket, $sebab]) {
            [$tanda, $jam] = [substr($geser, 0, 1), substr($geser, 1)];
            [$j, $m] = array_map('intval', explode(':', $jam));
            $waktu = $tanda === '-'
                ? $kejadian->copy()->subHours($j)->subMinutes($m)
                : $kejadian->copy()->addHours($j)->addMinutes($m);

            $inv->kronologi()->create([
                'waktu' => $waktu, 'peristiwa' => $judul, 'keterangan' => $ket,
                'penyebab' => $sebab, 'urutan' => $i + 1,
            ]);
            $n++;
        }

        /* ── bukti ── */
        $bukti = [];
        $daftarBukti = [
            ['foto',       'Foto posisi akhir unit dan bekas ban',          true],
            ['dokumen',    'Lembar P2H unit HD-785 tiga hari terakhir',     true],
            ['dokumen',    'Riwayat perawatan sistem rem HD-785',           false],
            ['pernyataan', 'Pernyataan operator Budi Santoso',              true],
            ['foto',       'Foto kondisi kampas rem setelah pembongkaran',  true],
        ];

        foreach ($daftarBukti as $i => [$jenis, $judul, $dikunci]) {
            $b = $inv->bukti()->create([
                'no_bukti' => NomorInvestigasi::terbitkan(NomorInvestigasi::BUKTI, (int) $kejadian->format('Y')),
                'jenis' => $jenis, 'judul' => $judul,
                'sumber' => $i === 3 ? 'Wawancara di klinik site' : 'Tim investigasi',
                'dikumpulkan_pada' => $kejadian->copy()->addDay()->toDateString(),
                'dikumpulkan_oleh' => $inv->ketua_id,

                /* Sidik jarinya dibuat dari judulnya supaya tetap sama
                   tiap kali data contoh dimuat ulang. Nilai acak akan
                   membuat dua pemuatan menghasilkan sidik jari berbeda
                   untuk berkas yang sama — persis keadaan yang seharusnya
                   menandakan berkasnya diganti. */
                'sha256' => hash('sha256', $judul),
                'dikunci' => $dikunci,
                'dikunci_pada' => $dikunci ? $kejadian->copy()->addDays(2) : null,
                'dikunci_oleh' => $dikunci ? $inv->ketua_id : null,
            ]);

            $bukti[] = $b;
            $n++;
        }

        /* ── analisis SCAT ── */
        $analisis = $inv->analisis()->create([
            'metode'  => 'scat',
            'catatan' => 'Dipilih dari 15 saran mesin atas 252 butir kamus; seluruhnya diverifikasi '
                .'ulang terhadap bukti sebelum dicentang.',
        ]);
        $n++;

        $butir = DB::table('inv_taksonomi')->where('metode', 'scat')
            ->whereIn('kode', ['6.12', '8.5.5', '9.5.7', '5.2'])
            ->pluck('id', 'kode');

        /* `dari_saran` mengikuti apa yang SUNGGUH DAPAT terjadi di layar.
           Lapis 1 dipilih sendiri dari keterangan saksi — mesinnya tidak
           pernah mengusulkannya — jadi menandainya sebagai hasil usulan
           membuat rekap "apakah mesinnya menolong atau justru menyetir"
           dijawab dengan angka yang tidak pernah ada kejadiannya.

           Lapisnya pun dihitung MesinScat, bukan ditulis tangan. Ditulis
           tangan, ia diam-diam melenceng begitu kamusnya bergeser. */
        foreach ($butir as $kode => $id) {
            $lapis = MesinScat::lapis($kode);

            $analisis->pilihan()->create([
                'taksonomi_id' => $id,
                'dari_saran'   => $lapis > 1,
                'lapis_saran'  => $lapis > 1 ? $lapis : null,
                'catatan_lapangan' => 'Dicocokkan dengan bukti '.$bukti[0]->no_bukti.'.',
            ]);
            $n++;
        }

        /* ── akar masalah, masing-masing bertaut ke buktinya ── */
        $akar = [];
        $daftarAkar = [
            ['Interval penggantian kampas rem tidak disesuaikan dengan kondisi jalan hauling yang menurun tajam.', '8.5.5', [2, 4]],
            ['Lembar P2H diisi tanpa pemeriksaan fisik yang sebenarnya.', null, [1, 3]],
            ['Pengawasan pengisian P2H tidak berjalan.', '9.5.7', [1]],
        ];

        foreach ($daftarAkar as $i => [$uraian, $kode, $idxBukti]) {
            $a = $inv->akar()->create([
                'uraian'  => $uraian,
                'metode'  => $kode ? 'scat' : '5why',
                'taksonomi_id' => $kode ? ($butir[$kode] ?? null) : null,
                'urutan'  => $i + 1,
            ]);

            /* Tiap akar disokong bukti. Akar tanpa bukti adalah pendapat,
               dan pendapat tidak bertahan di depan Inspektur Tambang —
               data contoh yang memperlihatkan akar tanpa bukti akan
               mengajarkan kebiasaan yang justru hendak dicegah. */
            $a->bukti()->attach(collect($idxBukti)->map(fn ($x) => $bukti[$x]->id)->all());

            $akar[] = $a;
            $n++;
        }

        /* ── temuan dan tindakan perbaikan ── */
        $hierarki = DB::table('inv_hierarki_kendali')->pluck('id', 'kode');

        $daftarTemuan = [
            [
                'Jadwal penggantian kampas rem memakai interval jam kerja standar pabrikan, tanpa '
                    .'penyesuaian terhadap jalan hauling bergradien di atas 8%.',
                'Susun ulang interval perawatan sistem rem berdasarkan gradien jalan dan jam kerja aktual.',
                'tinggi', 0,
                [
                    ['Revisi jadwal perawatan sistem rem seluruh unit HD berdasarkan gradien jalan', 'rekayasa', 'diverifikasi', -2],
                    ['Pasang alat pemantau suhu rem pada unit yang melintasi jalan bergradien tinggi', 'rekayasa', 'selesai', 18],
                ],
            ],
            [
                'Lembar P2H diisi tanpa pemeriksaan fisik. Tiga lembar terakhir tidak mencatat satu pun '
                    .'temuan meskipun kampas sudah aus melewati batas.',
                'Ubah cara verifikasi P2H sehingga tidak bergantung pada kejujuran pengisian sendiri.',
                'tinggi', 1,
                [
                    ['Pemeriksaan silang P2H oleh pengawas pada 20% unit setiap giliran kerja', 'administratif', 'diverifikasi', -5],
                    ['Penyegaran pelatihan P2H untuk seluruh operator HD', 'administratif', 'selesai', 9],
                ],
            ],
        ];

        foreach ($daftarTemuan as $i => [$uraian, $rekomendasi, $tingkat, $idxAkar, $tindakan]) {
            $t = $inv->temuan()->create([
                'no_temuan' => NomorInvestigasi::terbitkan(NomorInvestigasi::TEMUAN, (int) $kejadian->format('Y')),
                'akar_id'   => $akar[$idxAkar]->id,
                'uraian'    => $uraian,
                'rekomendasi' => $rekomendasi,
                'tingkat'   => $tingkat,
                'urutan'    => $i + 1,
            ]);
            $n++;

            foreach ($tindakan as [$isi, $kodeHierarki, $status, $geserTenggat]) {
                $sudahDiverifikasi = $status === 'diverifikasi';

                $t->tindakan()->create([
                    'no_tindakan' => NomorInvestigasi::terbitkan(NomorInvestigasi::TINDAKAN, (int) $kejadian->format('Y')),
                    'hierarki_id' => $hierarki[$kodeHierarki] ?? null,
                    'uraian'      => $isi,
                    'pic_id'      => $this->pengaju?->id,
                    'tenggat'     => $this->kini->copy()->addDays($geserTenggat)->toDateString(),
                    'status'      => $status,
                    'selesai_pada' => $this->kini->copy()->subDays(3)->toDateString(),
                    'diverifikasi_oleh' => $sudahDiverifikasi ? $inv->ketua_id : null,
                    'diverifikasi_pada' => $sudahDiverifikasi ? $this->kini->copy()->subDay()->toDateString() : null,
                    'catatan_verifikasi' => $sudahDiverifikasi
                        ? 'Diperiksa di lapangan; jadwal baru sudah berjalan pada dua unit contoh.' : null,
                    'efektif' => $sudahDiverifikasi ? true : null,
                ]);
                $n++;
            }
        }

        /* ── wawancara ── */
        $w = $inv->wawancara()->create([
            'narasumber' => 'Budi Santoso', 'jabatan' => 'Operator HD',
            'peran' => 'korban',
            'tanggal' => $kejadian->copy()->addDay()->toDateString(),
            'tempat' => 'Klinik site',
            'pewawancara_id' => $inv->ketua_id,
            'catatan' => 'Narasumber tenang, keterangan runtut.',
        ]);
        $n++;

        foreach ([
            ['Ceritakan kronologi kejadian dari sudut pandang Anda, dari sebelum sampai sesudah kejadian.',
             'Saya keluar pit sekitar pukul enam pagi. Di tikungan menurun saya mulai mengerem seperti '
                .'biasa, tetapi pedalnya terasa lebih dalam dan unit tidak melambat seperti biasanya.'],
            ['APD apa yang digunakan saat kejadian, dan apakah kondisinya baik & sesuai standar?',
             'Sabuk pengaman terpasang, helm dan sepatu lengkap. Sabuk yang menahan saya tidak terbentur setir.'],
        ] as [$tanya, $jawab]) {
            $w->jawaban()->create([
                'pertanyaan_id' => DB::table('inv_wawancara_pertanyaan')
                    ->where('pertanyaan', $tanya)->value('id'),
                'pertanyaan_teks' => $tanya,
                'jawaban' => $jawab,
            ]);
            $n++;
        }

        /* ── pembelajaran ── */
        $inv->pembelajaran()->create([
            'judul' => 'Interval perawatan rem harus mengikuti gradien jalan, bukan hanya jam kerja',
            'ringkasan' => 'Unit HD-785 menabrak tanggul pengaman karena kampas rem sudah aus melewati '
                .'batas, sementara jadwal penggantiannya memakai interval jam kerja standar pabrikan. '
                .'Jalan hauling bergradien di atas 8% mempercepat keausan jauh melebihi asumsi itu.',
            'pesan_kunci' => 'Periksa apakah jadwal perawatan rem di site Anda sudah memperhitungkan '
                .'gradien jalan. Bila belum, hitung ulang sebelum musim hujan.',
            'diterbitkan_pada' => $this->kini->toDateString(),
            'diterbitkan_oleh' => $inv->ketua_id,
        ]);
        $n++;

        /* ── jejak langkah ── */
        foreach ([
            ['Insiden dilaporkan', $insiden->no_insiden],
            ['Triase', 'L3 · skor '.$insiden->skor_risiko],
            ['Investigasi dibuka', $inv->no_investigasi],
            ['Bukti dikunci', '4 dari 5 berkas'],
            ['Tahap dimajukan', 'Analisis → Rencana Aksi'],
        ] as [$aksi, $ket]) {
            DB::table('inv_jejak')->insert([
                'investigasi_id' => $inv->id, 'insiden_id' => $insiden->id,
                'user_id' => $inv->ketua_id, 'aksi' => $aksi, 'keterangan' => $ket,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $n++;
        }

        return $n;
    }

    private function pesan(): int
    {
        if (!$this->pengaju || !$this->peninjau) return 0;

        $n = 0;

        /* Dibuat lewat jalan yang dipakai aplikasinya sendiri, bukan
           dengan menulis tabel pivotnya langsung. Percakapan langsung
           punya aturan "satu pasang, satu percakapan", dan data contoh
           yang melewati aturan itu akan menghasilkan keadaan yang tidak
           pernah dapat muncul dari pemakaian biasa. */
        $p = Percakapan::withoutGlobalScopes()
            ->where('jenis', 'langsung')->firstOr(fn () => tap(
                Percakapan::withoutGlobalScopes()->create([
                    'jenis'      => 'langsung',
                    'judul'      => 'Koordinasi tanggul KM 4',
                    'company_id' => $this->c->id,
                ]),
                fn ($baru) => $baru->peserta()->attach([
                    $this->pengaju->getKey(), $this->peninjau->getKey(),
                ]),
            ));
        $n++;

        $terakhir = null;

        foreach ([
            [$this->pengaju,  'Tanggul KM 4 tergerus sesudah hujan semalam. Sudah saya buatkan laporan bahayanya.', 180],
            [$this->peninjau, 'Terima kasih. Tutup dulu jalur itu sampai alat berat sampai.', 165],
            [$this->pengaju,  'Siap, rambu pengalihan sudah dipasang.', 150],
            [$this->peninjau, 'Perbaikan dijadwalkan besok pagi; tolong dipantau.', 20],
        ] as [$dari, $isi, $menitLalu]) {
            $terakhir = $this->kini->copy()->subMinutes($menitLalu);

            Pesan::withoutGlobalScopes()->create([
                'percakapan_id' => $p->id,
                'user_id'       => $dari->getKey(),
                'peran'         => 'pengguna',
                'isi'           => $isi,
                'created_at'    => $terakhir,
                'updated_at'    => $terakhir,
            ]);
            $n++;
        }

        /* Daftar percakapan diurutkan menurut kolom ini, bukan menurut
           pesan terakhirnya. Membiarkannya kosong menaruh percakapan
           yang baru saja ramai di dasar daftar. */
        $p->forceFill(['pesan_terakhir_at' => $terakhir])->saveQuietly();

        return $n;
    }

    /* ─────────── catatan belajar ─────────── */

    private function catatan(): int
    {
        if (!$this->pengaju) return 0;

        $n = 0;

        $modul = Module::withoutGlobalScopes()->whereIn('course_id',
            Course::withoutGlobalScopes()->where('demo_company_id', $this->c->id)->select('id')
        )->orderBy('order_index')->take(2)->get();

        foreach ($modul as $i => $m) {
            $this->baru(Note::class, [
                'user_id'   => $this->pengaju->getKey(),
                'module_id' => $m->id,
                'content'   => [
                    'Bagian pengendalian bahaya perlu dibaca ulang sebelum ujian.',
                    'Urutan pemakaian APD: helm, kacamata, rompi, sepatu.',
                ][$i],
            ]);
            $n++;
        }

        return $n;
    }

    /* ─────────── konservasi minerba ─────────── */

    /**
     * Recovery, kehilangan, dan dilusi per bulan.
     *
     * Tiga angka yang saling mengunci: recovery yang naik sementara
     * kehilangan ikut naik adalah tanda hitungannya keliru, dan itu
     * hanya terlihat bila ketiganya ada. Satu bulan sengaja berada di
     * bawah target supaya kolom selisihnya tidak selalu positif.
     */
    private function konservasi(): int
    {
        $n = 0;

        foreach ([5, 4, 3, 2, 1, 0] as $i => $mundur) {
            $bulan  = $this->kini->copy()->subMonths($mundur);
            $target = 82_000;

            /* Bulan keempat turun karena hujan — bulan yang seluruhnya
               memenuhi target tidak memperlihatkan apakah peringatannya
               menyala. */
            $aktual = $i === 3 ? 61_400 : $target + ($i % 2 ? 1_800 : -900);
            $digali = (int) round($aktual / 0.93);

            $this->baru(MinerbaConservationRecord::class, [
                'periode'             => $bulan->format('Y-m'),
                'lokasi'              => 'Pit Utara',
                'komoditas'           => 'Batubara',
                'satuan'              => 'ton',
                'target_produksi'     => $target,
                'produksi_aktual'     => $aktual,
                'material_digali'     => $digali,
                'recovery_percent'    => round($aktual / $digali * 100, 2),
                'kehilangan_material' => $digali - $aktual,
                'dilusi'              => round(4.2 + ($i % 3) * 0.6, 2),
                'stok_akhir'          => 12_500 + $i * 900,
                'mineral_ikutan'      => 'Tidak ada mineral ikutan bernilai ekonomis.',
                'catatan'             => $i === 3
                    ? 'Produksi turun; curah hujan tinggi sepanjang bulan.' : null,
                'user_id'             => $this->pengaju?->getKey(),
            ]);
            $n++;
        }

        return $n;
    }

    /* ─────────── tindak lanjut lintas modul ─────────── */

    /**
     * Papan tindak lanjut yang menyatukan temuan dari banyak modul.
     *
     * Justru inilah yang paling perlu berisi: halaman ini ada supaya
     * temuan tidak berhenti di modulnya masing-masing. Papan kosong
     * terbaca sebagai "tidak ada yang tertunda", yang merupakan
     * kesimpulan paling berbahaya yang dapat diambil dari layar kosong.
     */
    private function tindakLanjut(): int
    {
        $n = 0;

        /* Unsur terakhir tiap baris menyatakan apakah temuannya BERTUAN:
           punya penanggung jawab sekaligus tenggat. Satu baris sengaja
           dibiarkan tanpa keduanya.

           Bukan kelalaian menyusun contoh — justru keadaan itu yang
           paling perlu terlihat. Temuan tanpa tenggat tidak pernah
           terhitung terlambat, sebab tidak punya tanggal untuk dilewati,
           sehingga ia tidak menyalakan peringatan apa pun di modul mana
           pun, selamanya. Di daftar biasa ia tampak persis sama dengan
           temuan yang sedang ditangani. Bila data contoh hanya memuat
           baris yang rapi, kolom `bertuan` di register tidak pernah
           membuktikan apa-apa. */
        $daftar = [
            ['bahaya',    'HZ-001', 'Perbaiki tanggul jalan hauling KM 4',
             'Rekayasa', 'Tinggi', 'berjalan',  7, null, true],
            ['inspeksi',  'INS-003', 'Bersihkan saluran drainase Pit Selatan',
             'Perawatan', 'Sedang', 'terbuka',  -3, null, true],
            ['ko',        'KO-PER-002', 'Ganti pemutus arus utama genset',
             'Perbaikan', 'Tinggi', 'berjalan', 14, null, true],
            ['smkp',      'IV.2.1', 'Lengkapi rekaman inspeksi jalan angkut',
             'Administratif', 'Sedang', 'terbuka', -8, null, true],
            ['lingkungan','LK-002', 'Tambah titik pantau kualitas udara di camp',
             'Pemantauan', 'Rendah', 'selesai',  -20, -14, true],
            ['air',       'AIR-001', 'Perbaiki pompa sump 2 yang mati',
             'Perbaikan', 'Tinggi', 'selesai',  -30, -26, true],
            ['gudang',    'GD-004', 'Tata ulang penyimpanan oli bekas di gudang B',
             'Administratif', 'Sedang', 'terbuka', null, null, false],
        ];

        foreach ($daftar as [$modul, $pemicu, $judul, $kategori, $prioritas, $status, $target, $selesai, $bertuan]) {
            $this->baru(TindakLanjut::class, [
                'user_id'          => $this->pengaju?->getKey(),
                'modul'            => $modul,
                'kode_pemicu'      => $pemicu,
                'judul'            => $judul,
                'kategori'         => $kategori,
                'prioritas'        => $prioritas,
                'status'           => $status,
                'penanggung_jawab' => $bertuan
                    ? ($this->peninjau?->name ?? 'Kepala Teknik Tambang') : null,
                'target_selesai'   => $bertuan && $target !== null
                    ? $this->kini->copy()->addDays($target)->toDateString() : null,
                'selesai_pada'     => $selesai === null
                    ? null : $this->kini->copy()->addDays($selesai)->toDateString(),
                'uraian'           => 'Tindak lanjut contoh; asalnya disebut pada kolom modul dan pemicu.',
            ]);
            $n++;
        }

        return $n;
    }

    /* ─────────── penanda tangan laporan ─────────── */

    /**
     * Nama dan jabatan yang tercetak di kaki laporan.
     *
     * Tanpa ini setiap lembar keluar dengan kolom tanda tangan tanpa
     * nama di bawahnya — dan lembar semacam itu ditolak sebagai dokumen
     * terkendali, bukan sekadar terlihat belum jadi.
     */
    private function penandaTangan(): int
    {
        $n = 0;

        foreach ([
            ['Kepala Teknik Tambang',  true],
            ['Manajer OHSE',           true],
            ['Pengawas Operasional',   true],
            ['Kepala Teknik Tambang (pejabat lama)', false],
        ] as $i => [$jabatan, $aktif]) {
            $this->baru(Signatory::class, [
                'name'      => [$this->peninjau?->name ?? 'Ir. Bambang Susilo',
                                'Dewi Anggraini',
                                $this->pengaju?->name ?? 'Agus Setiawan',
                                'Hendra Kusuma'][$i],
                'title'     => $jabatan,
                'signature' => null,
                'is_active' => $aktif,
            ]);
            $n++;
        }

        return $n;
    }

    /* ─────────── peta tambang ─────────── */

    /**
     * Lapisan peta sederhana, cukup untuk membuktikan peta menggambar.
     *
     * Geometrinya sengaja kecil dan bulat: yang diuji adalah apakah
     * lapisannya terbaca, terwarnai, dan dapat dinyalakan-matikan —
     * bukan ketelitian koordinatnya.
     */
    private function peta(): int
    {
        $n = 0;

        $kotak = fn (float $x, float $y, float $s) => json_encode([
            'type' => 'FeatureCollection',
            'features' => [[
                'type' => 'Feature',
                'properties' => new \stdClass,
                'geometry' => [
                    'type' => 'Polygon',
                    'coordinates' => [[
                        [$x, $y], [$x + $s, $y], [$x + $s, $y + $s], [$x, $y + $s], [$x, $y],
                    ]],
                ],
            ]],
        ]);

        foreach ([
            ['Batas Pit Utara',      'pit',      '#B45309', 0.000, 0.000, 0.010],
            ['Disposal Barat',       'disposal', '#4D7C0F', 0.014, 0.002, 0.008],
            ['Kolam Pengendap 3',    'sump',     '#0E7490', 0.004, 0.014, 0.004],
            ['Jalan Hauling KM 0–6', 'jalan',    '#9333EA', 0.000, 0.020, 0.006],
        ] as [$nama, $tipe, $warna, $x, $y, $s]) {
            $this->baru(MineMapLayer::class, [
                'user_id'        => $this->pengaju?->getKey(),
                'nama'           => $nama,
                'tipe'           => $tipe,
                'geojson'        => $kotak($x, $y, $s),
                'warna'          => $warna,
                'status'         => 'aktif',
                'catatan'        => 'Lapisan contoh; koordinatnya bukan koordinat sungguhan.',
                'tanggal_survey' => $this->kini->copy()->subDays(21)->toDateString(),
                'sumber_survey'  => 'Survey topografi bulanan',
            ]);
            $n++;
        }

        return $n;
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
            /* Ritase adalah CACAHAN — berapa kali truk bolak-balik —
               jadi bulat, dan tonasenya diturunkan dari ritase yang
               sudah dibulatkan itu. Menghitung tonase dari pecahannya
               menghasilkan dua angka yang tidak dapat dicocokkan pada
               laporan yang sama. PostgreSQL menolak 193.5 pada kolom
               integer; SQLite menerimanya diam-diam. */
            $ritase = (int) round(86 * $jumlah / 4);

            $r = $this->baru(AngkutRegu::class, [
                'user_id' => $this->pengaju?->getKey(), 'alat_muat_id' => $ex->id,
                'kode' => $kode, 'tanggal' => $this->kini->copy()->subDays(3)->toDateString(),
                'shift' => '1', 'pit' => 'Pit Utara', 'tujuan' => 'Disposal Barat',
                'material' => 'overburden', 'jumlah_alat_muat' => 1, 'jumlah_truk' => $jumlah,
                'jarak_km' => 3.4, 'waktu_muat_menit' => 4, 'waktu_angkut_menit' => 8,
                'waktu_tumpah_menit' => 2, 'waktu_kembali_menit' => 6,
                'waktu_antre_menit' => $antre, 'ritase' => $ritase,
                'tonase' => $ritase * 89, 'jam_kerja' => 9, 'jam_delay' => 1,
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

        /* Kodenya DISUSUN dari perusahaannya, bukan ditulis tetap.
           Data contoh yang kodenya tetap akan memperlihatkan penomoran
           milik perusahaan lain kepada yang sedang memeriksanya — dan
           yang diperiksa justru apakah penomorannya sudah benar. */
        $daftar = [
            ['Kebijakan',       'Kebijakan Keselamatan dan Kesehatan Kerja', 'Umum',     'berlaku',   2],
            ['Manual',          'Manual Sistem Manajemen Keselamatan',       'Internal', 'berlaku',   1],
            ['Prosedur',        'Prosedur Izin Kerja Khusus',                'Internal', 'berlaku',   3],
            ['Prosedur',        'Prosedur Investigasi Kecelakaan',           'Internal', 'berlaku',   1],
            ['Instruksi Kerja', 'Instruksi Kerja Pemeriksaan Sump Harian',   'Internal', 'berlaku',   0],
            ['Instruksi Kerja', 'Instruksi Kerja Pengisian Bahan Peledak',   'Rahasia',  'berlaku',   2],
            ['Formulir',        'Formulir Inspeksi Jalan Tambang',           'Umum',     'berlaku',   0],
            ['Rekaman',         'Rekaman Pelatihan Tanggap Darurat',         'Internal', 'draft',     0],
            ['Prosedur',        'Prosedur Pengelolaan Limbah B3',            'Internal', 'kadaluarsa',4],
        ];

        $urut = [];

        foreach ($daftar as [$jenis, $judul, $klas, $status, $rev]) {
            $urut[$jenis] = ($urut[$jenis] ?? 0) + 1;
            $kode = Nomor::susun($jenis, $this->c, $urut[$jenis])
                ?: strtoupper(Nomor::jenis($jenis)).'-'.str_pad((string) $urut[$jenis], 3, '0', STR_PAD_LEFT);
            $terbit = $this->kini->copy()->subMonths(6 + $rev);

            $d = $this->baru(Document::class, [
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

            /* ── riwayat revisi ──

               Dokumen revisi 3 yang riwayatnya kosong tidak dapat
               dipakai membuktikan apa pun kepada auditor: yang
               ditanyakan adalah APA yang berubah pada tiap revisi, dan
               kolom angka saja tidak menjawabnya. Riwayatnya dibuat
               mundur dari revisi berjalan sampai revisi 0. */
            for ($r = $rev; $r >= 0; $r--) {
                $this->baru(DocumentRevision::class, [
                    'document_id'         => $d->id,
                    'revisi'              => $r,
                    'ringkasan_perubahan' => $r === 0
                        ? 'Terbitan pertama.'
                        : 'Penyesuaian isi mengikuti hasil tinjauan berkala.',
                    'tanggal'             => $terbit->copy()->subMonths(($rev - $r) * 8)->toDateString(),
                    'oleh'                => $this->peninjau?->name ?? 'Pengendali Dokumen',
                ]);
                $n++;
            }

            /* ── kaitan ke klausul standar ──

               Daftar induk yang tidak menyebut klausul memaksa auditor
               memetakannya sendiri, dan pemetaan yang dikerjakan
               auditor adalah pemetaan yang tidak pernah sama dua kali. */
            foreach ([
                'Kebijakan'       => [['SMKP', 'I.1'], ['ISO 45001', '5.2']],
                'Manual'          => [['SMKP', 'II.1']],
                'Prosedur'        => [['SMKP', 'III.2'], ['ISO 45001', '8.1']],
                'Instruksi Kerja' => [['SMKP', 'III.2']],
                'Formulir'        => [['SMKP', 'IV.2']],
                'Rekaman'         => [['SMKP', 'VI.1']],
            ][$jenis] ?? [] as [$standar, $klausul]) {
                $this->baru(DocumentIso::class, [
                    'document_id' => $d->id,
                    'standar'     => $standar,
                    'klausul'     => $klausul,
                ]);
                $n++;
            }
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
                'kode'          => Nomor::susun('Formulir', $this->c, 100 + $i + 1)
                    ?: 'HZ-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
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
     * Inspeksi lengkap dengan template, butir, dan pemeriksanya.
     *
     * Template TIDAK punya company_id — ia pustaka bersama — dan
     * karena itu ditandai `demo_company_id` supaya penghapus data
     * contoh tetap dapat menemukannya kembali tanpa menyentuh milik
     * perusahaan contoh yang lain.
     *
     * Butirnya diisi, bukan dibiarkan kosong. Inspeksi tanpa butir
     * tampil sebagai lembar yang sudah selesai dengan nol temuan —
     * bentuk yang tidak dapat dibedakan dari inspeksi yang memang
     * bersih, dan yang membuat seluruh rekapitulasi temuan menjadi nol
     * tanpa ada yang salah di layar.
     */
    private function inspeksi(): int
    {
        $n = 0;

        /* ── template dan butirnya ── */

        $template = [];

        $pustaka = [
            ['Inspeksi Harian Jalan Angkut', 'Harian', 'Jalan Tambang', [
                ['Badan jalan', 'Lebar jalan minimal 3,5 kali lebar alat terbesar', 'Kepmen 1827 K/2018', 'Tinggi'],
                ['Badan jalan', 'Superelevasi tikungan dan kemiringan memanjang', 'Kepmen 1827 K/2018', 'Sedang'],
                ['Tanggul',     'Tanggul pengaman setinggi setengah diameter ban terbesar', 'Kepmen 1827 K/2018', 'Tinggi'],
                ['Drainase',    'Saluran samping tidak tersumbat dan mengalir', null, 'Sedang'],
                ['Rambu',       'Rambu batas kecepatan dan peringatan terbaca', null, 'Rendah'],
            ]],
            ['Inspeksi Bulanan Gudang Bahan Peledak', 'Bulanan', 'Gudang Handak', [
                ['Keamanan',   'Pagar, gembok, dan penerangan keliling berfungsi', 'Kepmen 1827 K/2018', 'Tinggi'],
                ['Penyimpanan','Detonator dan bahan peledak terpisah sesuai jarak aman', 'Kepmen 1827 K/2018', 'Tinggi'],
                ['Administrasi','Kartu persediaan cocok dengan hitungan fisik', null, 'Sedang'],
                ['Kebakaran',  'APAR bertekanan cukup dan belum kedaluwarsa', null, 'Tinggi'],
            ]],
        ];

        foreach ($pustaka as [$nama, $jenis, $kategori, $butir]) {
            $t = InspectionTemplate::withoutGlobalScopes()->create([
                'demo_company_id' => $this->c->id,
                'nama'      => $nama,
                'jenis'     => $jenis,
                'kategori'  => $kategori,
                'deskripsi' => 'Template contoh; butirnya mengikuti acuan yang disebut di tiap baris.',
                'is_active' => true,
            ]);
            $n++;

            foreach ($butir as $j => [$kelompok, $uraian, $acuan, $risiko]) {
                InspectionTemplateItem::withoutGlobalScopes()->create([
                    'template_id'    => $t->id,
                    'kelompok'       => $kelompok,
                    'uraian'         => $uraian,
                    'acuan'          => $acuan,
                    'risiko_default' => $risiko,
                    'order_index'    => $j + 1,
                ]);
                $n++;
            }

            $template[$jenis] = $t;
        }

        /* ── pelaksanaannya ── */

        $daftar = [
            ['Harian',   'Inspeksi Jalan Angkut Pagi',     'Jalan Hauling KM 0–6', 'Selesai'],
            ['Harian',   'Inspeksi Front Loading',         'Pit Utara',            'Selesai'],
            ['Mingguan', 'Inspeksi Tanggul dan Drainase',  'Pit Selatan',          'Berjalan'],
            ['Bulanan',  'Inspeksi Gudang Bahan Peledak',  'Gudang Handak',        'Berjalan'],
            ['Khusus',   'Inspeksi Pasca Hujan Deras',     'Disposal Selatan',     'Selesai'],
        ];

        /* Tidak semua butir "Sesuai". Rekapitulasi temuan yang seluruh
           barisnya sesuai tidak pernah membuktikan penghitungnya
           bekerja — sama saja dengan tidak menghitung apa pun. */
        $kondisi = ['Sesuai', 'Sesuai', 'Tidak Sesuai', 'Sesuai', 'N/A'];

        foreach ($daftar as $i => [$jenis, $judul, $lokasi, $status]) {
            $t = $template[$jenis] ?? null;

            $ins = $this->baru(Inspection::class, [
                'kode'        => Nomor::susun('Formulir', $this->c, 200 + $i + 1)
                    ?: 'INS-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'template_id' => $t?->id,
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

            /* Butir diambil dari template bila ada; bila inspeksinya
               memang tanpa template, butirnya tetap ditulis sendiri —
               inspeksi kosong bukan keadaan yang perlu dicontohkan. */
            $butir = $t
                ? InspectionTemplateItem::withoutGlobalScopes()
                    ->where('template_id', $t->id)->orderBy('order_index')->get()
                    ->map(fn ($b) => [$b->id, $b->kelompok, $b->uraian, $b->acuan, $b->risiko_default])
                    ->all()
                : [
                    [null, 'Umum', 'Kondisi area sesudah hujan deras', null, 'Sedang'],
                    [null, 'Umum', 'Genangan pada jalan akses',        null, 'Sedang'],
                    [null, 'Lereng', 'Retakan baru pada muka lereng',  null, 'Tinggi'],
                ];

            foreach ($butir as $j => [$idButir, $kelompok, $uraian, $acuan, $risiko]) {
                $k = $kondisi[$j % count($kondisi)];

                InspectionItem::withoutGlobalScopes()->create([
                    'inspection_id'    => $ins->id,
                    'template_item_id' => $idButir,
                    'kelompok'         => $kelompok,
                    'uraian'           => $uraian,
                    'acuan'            => $acuan,
                    'kondisi'          => $k,
                    'risiko'           => $risiko,
                    'temuan'  => $k === 'Tidak Sesuai' ? 'Tidak memenuhi acuan saat diperiksa.' : null,
                    'tindakan'=> $k === 'Tidak Sesuai' ? 'Diperbaiki dan diperiksa ulang pengawas area.' : null,
                    'order_index'      => $j + 1,
                ]);
                $n++;
            }

            /* Dua pemeriksa, bukan satu: lembar inspeksi resmi
               ditandatangani pelaksana DAN pengawas, dan halaman
               cetaknya menyediakan dua kolom tanda tangan yang akan
               kosong sebelah bila hanya satu yang tercatat. */
            foreach ([
                [$this->pengaju,  'Pengawas Operasional', 'Pelaksana'],
                [$this->peninjau, 'Kepala Teknik Tambang', 'Pemeriksa'],
            ] as [$orang, $jabatan, $peran]) {
                if (!$orang) continue;

                InspectionInspector::withoutGlobalScopes()->create([
                    'inspection_id' => $ins->id,
                    'user_id'       => $orang->getKey(),
                    'nama'          => $orang->name,
                    'jabatan'       => $jabatan,
                    'peran'         => $peran,
                ]);
                $n++;
            }
        }

        return $n;
    }

    /* ─────────── kelayakan operasi ─────────── */

    private function ko(): int
    {
        $n = 0;

        /* Daftar acuan jenis unit ditanam bila pemasangannya belum
           punya — sama seperti jenis kompetensi, ia milik bersama dan
           tidak ikut dibuang bersama data contoh. */
        if (KoUnitMaster::withoutGlobalScopes()->whereNull('company_id')->doesntExist()) {
            MasterUnitSpip::tanam();
        }

        $jenisUnit = KoUnitMaster::withoutGlobalScopes()
            ->whereNull('company_id')->pluck('id', 'kode');

        /** @var array<string,KoObject> menurut kodenya, dipakai anak-anaknya */
        $objek = [];

        /* Kode jenis ditambahkan supaya unit contoh TERTAUT ke daftar
           acuan. Bila seluruhnya dibiarkan mengetik bebas, layar "belum
           tertaut" akan menunjukkan seratus persen dan penyeragamannya
           tampak tidak pernah berjalan. */
        $daftar = [
            ['KO-SAR-001', 'Jembatan Timbang 60 Ton',    'Sarana',    'Tinggi', 'Aktif',     2, 'JBT'],
            ['KO-PRA-001', 'Tanggul Kolam Pengendap 3',  'Prasarana', 'Tinggi', 'Aktif',     1, 'SETL'],
            ['KO-INS-001', 'Instalasi Listrik Workshop', 'Instalasi', 'Sedang', 'Aktif',     3, 'PNL'],
            ['KO-PER-001', 'Crane Workshop 10 Ton',      'Peralatan', 'Tinggi', 'Standby',   1, 'OHC'],
            ['KO-PER-002', 'Genset 500 kVA',             'Peralatan', 'Sedang', 'Breakdown', 2, 'GEN'],

            /* Satu unit sengaja TIDAK tertaut, supaya angka "belum
               tertaut" tidak nol dan layarnya memperlihatkan seperti apa
               keadaan yang perlu dibereskan. */
            ['KO-SAR-002', 'Tangki Bahan Bakar 50 kL',   'Sarana',    'Tinggi', 'Aktif',     2, null],
        ];

        foreach ($daftar as $i => [$kode, $nama, $kategori, $kritis, $operasi, $interval, $kodeJenis]) {
            $sertifikasi = $this->kini->copy()->subMonths(6 + $i);

            $o = $this->baru(KoObject::class, [
                'kode'            => $kode,
                'nama'            => $nama,
                'kategori'        => $kategori,
                'ko_unit_master_id' => $kodeJenis ? ($jenisUnit[$kodeJenis] ?? null) : null,
                'jenis'           => $kodeJenis,
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

            $objek[$kode] = $o;
        }

        return $n + $this->ujiKelayakan($objek) + $this->koRinci($objek);
    }

    /**
     * Riwayat uji kelayakan — dua tahun berturut-turut untuk sebagian.
     *
     * Dua uji pada unit yang sama memang pokoknya: bentuk lama hanya
     * menyimpan satu tanggal, sehingga uji tahun lalu selalu hilang.
     * Data contoh yang hanya berisi satu uji per unit tidak akan pernah
     * memperlihatkan bahwa masalah itu sudah diperbaiki.
     *
     * @param  array<string,KoObject>  $objek
     */
    private function ujiKelayakan(array $objek): int
    {
        $n = 0;

        /* [kode objek, bulan lalu, hasil, status, syarat] */
        $daftar = [
            ['KO-PER-001', 20, 'Layak', Alur::DISETUJUI, null],          // uji tahun lalu
            ['KO-PER-001', 8,  'Layak', Alur::DISETUJUI, null],          // uji terbaru
            ['KO-SAR-001', 6,  'Layak Bersyarat', Alur::DISETUJUI,
                'Beban maksimum dibatasi 50 ton sampai perbaikan load cell selesai.'],
            ['KO-INS-001', 10, 'Layak', Alur::DISETUJUI, null],
            ['KO-PER-002', 2,  'Tidak Layak', Alur::DISETUJUI, null],    // genset breakdown
            ['KO-PRA-001', 1,  'Layak', Alur::DIAJUKAN, null],           // menunggu tinjauan
            ['KO-SAR-002', 0,  'Layak', Alur::DRAF, null],
        ];

        foreach ($daftar as $i => [$kode, $bulan, $hasil, $status, $syarat]) {
            $o = $objek[$kode] ?? null;
            if (!$o) continue;

            $inspeksi = $this->kini->copy()->subMonths($bulan);

            $u = KoUjiKelayakan::withoutGlobalScopes()->create([
                'ko_object_id' => $o->id,
                'company_id'   => $o->company_id,
                'nomor'        => 'UK/'.$inspeksi->format('Y').'/'
                    .str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'merk'         => $o->merk,
                'nomor_seri'   => $o->serial_number,
                'tgl_inspeksi' => $inspeksi->toDateString(),
                'tgl_expired'  => $inspeksi->copy()->addYear()->toDateString(),
                'pemeriksa'    => ['Ir. Hartono', 'Sulaiman, S.T.', 'Nurhayati'][$i % 3],
                'lembaga'      => 'Balai Pengujian Peralatan',
                'lokasi_uji'   => $o->lokasi,
                'hasil'        => $hasil,
                'syarat'       => $syarat,
                'temuan'       => $hasil === 'Tidak Layak'
                    ? 'Kebocoran pendingin dan getaran berlebih pada bantalan.' : null,
                'rekomendasi'  => $hasil === 'Tidak Layak'
                    ? 'Hentikan operasi sampai perbaikan menyeluruh dan uji ulang.' : null,
            ]);
            $n++;

            if ($status !== Alur::DRAF) {
                KoUjiKelayakan::withoutGlobalScopes()->whereKey($u->id)->update([
                    'status'        => $status,
                    'diajukan_oleh' => $this->pengaju?->getKey(),
                    'diajukan_pada' => $inspeksi->copy()->addDays(2),
                    'ditinjau_oleh' => $status === Alur::DISETUJUI ? $this->peninjau?->getKey() : null,
                    'ditinjau_pada' => $status === Alur::DISETUJUI
                        ? $inspeksi->copy()->addDays(4) : null,
                ]);
            }
        }

        return $n;
    }

    /**
     * Isi modul KO selain registrinya: tenaga teknik, pengaman,
     * pemeriksaan, tindak lanjut, kajian, dan perintah kerja.
     *
     * Tanpa ini registrinya berdiri sendiri, dan indeks KO — rerata
     * lima sub-elemen — dihitung dari satu sub-elemen saja. Angka yang
     * keluar tetap berupa persentase yang tampak masuk akal, dan itulah
     * yang membuatnya berbahaya: tidak ada yang di layar mengatakan
     * empat sub-elemen lainnya tidak punya data sama sekali.
     *
     * @param  array<string,KoObject>  $objek  menurut kodenya
     */
    private function koRinci(array $objek): int
    {
        $n = 0;

        /* ── tenaga teknik ── */

        $tenaga = [];

        foreach ([
            ['Tenaga Teknik Pertambangan', 'Operator Crane',     'Lisensi K3 Pesawat Angkat', 8],
            ['Pengawas Operasional',       'Pengawas Workshop',  'POP',                       -2],
            ['Juru Ukur',                  'Surveyor Tambang',   'Sertifikat Juru Ukur',      20],
        ] as $i => [$jabatanSertifikasi, $jabatan, $sertifikasi, $bulan]) {
            /* Satu sudah lewat masa berlakunya. Daftar yang seluruhnya
               masih berlaku tidak pernah menyalakan peringatannya, dan
               peringatan yang tidak pernah menyala tidak dapat
               dibedakan dari peringatan yang rusak. */
            $tenaga[] = $this->baru(KoPersonnel::class, [
                'nama'           => ['Rahmat Hidayat', 'Sri Wahyuni', 'Bayu Pratama'][$i],
                'jabatan'        => $jabatan,
                'sertifikasi'    => $sertifikasi,
                'no_sertifikat'  => 'SRT/'.$this->kini->year.'/'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'tgl_kadaluarsa' => $this->kini->copy()->addMonths($bulan)->toDateString(),
                'user_id'        => $i === 0 ? $this->pengaju?->getKey() : null,
            ]);
            $n++;
        }

        /* ── pengaman, pemeriksaannya, dan tindak lanjutnya ── */

        $pengaman = [
            'KO-PER-001' => [
                ['Limit switch batas angkat', 'Trip pada 105% beban', 'Berfungsi'],
                ['Rem sekunder hoist',        'Tahan beban statis 125%', 'Perlu Perbaikan'],
            ],
            'KO-PER-002' => [
                ['Pemutus arus utama', '630 A', 'Tidak Berfungsi'],
                ['Alarm tekanan oli',  'Trip < 1,5 bar', 'Berfungsi'],
            ],
            'KO-INS-001' => [
                ['Pembumian panel workshop', 'Tahanan ≤ 5 ohm', 'Berfungsi'],
            ],
            'KO-SAR-002' => [
                ['Katup penutup darurat', 'Tutup penuh ≤ 15 detik', 'Berfungsi'],
                ['Bunding tangki',        'Tampung 110% isi tangki', 'Perlu Perbaikan'],
            ],
        ];

        foreach ($pengaman as $kode => $daftar) {
            $o = $objek[$kode] ?? null;
            if (!$o) continue;

            foreach ($daftar as $i => [$nama, $spek, $status]) {
                $periksa = $this->kini->copy()->subDays(20 + $i * 9);

                $p = KoSafeguard::withoutGlobalScopes()->create([
                    'ko_object_id' => $o->id,
                    'nama'         => $nama,
                    'spesifikasi'  => $spek,
                    'status'       => $status,
                    'tgl_periksa'  => $periksa->toDateString(),
                    'catatan'      => 'Diperiksa bersama pemeriksaan berkala alat.',
                ]);
                $n++;

                /* Pemeriksaan pengaman dicatat sebagai baris tersendiri
                   — itulah yang dibaca sub-elemen "pengaman diperiksa",
                   bukan kolom tgl_periksa pada pengamannya. */
                KoInspection::withoutGlobalScopes()->create([
                    'ko_object_id'    => $o->id,
                    'ko_safeguard_id' => $p->id,
                    'ko_personnel_id' => $tenaga[0]?->id,
                    'jenis'           => 'Pengaman',
                    'tanggal'         => $periksa->toDateString(),
                    'hasil'           => $status,
                    'nilai_ukur'      => $spek,
                    'berikutnya'      => $periksa->copy()->addMonths(3)->toDateString(),
                    'catatan'         => 'Pemeriksaan contoh.',
                    'user_id'         => $this->pengaju?->getKey(),
                ]);
                $n++;

                if ($status === 'Berfungsi') continue;

                /* Pengaman yang tidak berfungsi WAJIB punya tindak
                   lanjut. Temuan tanpa tindak lanjut adalah persis yang
                   dicari auditor, dan data contoh yang tidak
                   memperlihatkan pasangan itu tidak menguji alurnya. */
                KoAction::withoutGlobalScopes()->create([
                    'ko_object_id'    => $o->id,
                    'ko_safeguard_id' => $p->id,
                    'sumber'          => 'Pemeriksaan pengaman',
                    'uraian'          => $nama.' tidak memenuhi spesifikasi saat diperiksa.',
                    'prioritas'       => $status === 'Tidak Berfungsi' ? 'Tinggi' : 'Sedang',
                    'pic_user_id'     => $this->peninjau?->getKey(),
                    'pic_nama'        => $this->peninjau?->name ?? 'Kepala Workshop',
                    'target_tgl'      => $periksa->copy()->addDays(14)->toDateString(),
                    'status'          => $status === 'Tidak Berfungsi' ? 'Berjalan' : 'Selesai',
                    'tgl_selesai'     => $status === 'Tidak Berfungsi'
                        ? null : $periksa->copy()->addDays(9)->toDateString(),
                    'tindakan'        => $status === 'Tidak Berfungsi'
                        ? 'Suku cadang dipesan; alat distandbykan sampai perbaikan selesai.'
                        : 'Disetel ulang dan diuji beban.',
                ]);
                $n++;
            }
        }

        /* ── pemeriksaan berkala alatnya sendiri ── */

        foreach ($objek as $o) {
            $tgl = $this->kini->copy()->subMonths(2);

            KoInspection::withoutGlobalScopes()->create([
                'ko_object_id'    => $o->id,
                'ko_personnel_id' => $tenaga[1]?->id,
                'jenis'           => 'Berkala',
                'tanggal'         => $tgl->toDateString(),
                'hasil'           => $o->status_operasi === 'Breakdown' ? 'Tidak Layak' : 'Layak',
                'berikutnya'      => $tgl->copy()->addMonths(3)->toDateString(),
                'catatan'         => 'Pemeriksaan berkala contoh.',
                'user_id'         => $this->pengaju?->getKey(),
            ]);
            $n++;
        }

        /* ── kajian teknis ── */

        foreach ([
            ['KO-PRA-001', 'Kajian Teknis Tanggul Kolam Pengendap 3', 'Perubahan tinggi muka air', 'Dilaporkan'],
            ['KO-PER-002', 'Kajian Teknis Genset 500 kVA',            'Kerusakan berulang',        'Berjalan'],
        ] as $i => [$kode, $judul, $pemicu, $status]) {
            $o = $objek[$kode] ?? null;
            if (!$o) continue;

            $tgl = $this->kini->copy()->subDays(30 + $i * 12);

            KoReview::withoutGlobalScopes()->create([
                'ko_object_id'    => $o->id,
                'judul'           => $judul,
                'pemicu'          => $pemicu,
                'tanggal'         => $tgl->toDateString(),
                'oleh'            => $this->peninjau?->name ?? 'Tenaga Teknik Bersertifikat',
                'ko_personnel_id' => $tenaga[2]?->id,
                'status'          => $status,
                'tgl_lapor'       => $status === 'Dilaporkan'
                    ? $tgl->copy()->addDays(10)->toDateString() : null,
                'ringkasan'       => 'Kajian contoh; kesimpulannya menjadi dasar keputusan operasi.',
            ]);
            $n++;
        }

        return $n + $this->perintahKerja($objek);
    }

    /**
     * Perintah kerja perawatan, terhubung ke registri alat KO.
     *
     * Halaman perawatan menghitung ketaatan PM sebagai perbandingan
     * perintah kerja preventif yang selesai terhadap yang terbit. Tanpa
     * satu pun perintah kerja, pembaginya nol — dan halaman itu dahulu
     * menampilkannya sebagai 100% pada pemasangan yang belum punya satu
     * alat pun.
     *
     * @param  array<string,KoObject>  $objek
     */
    private function perintahKerja(array $objek): int
    {
        $n = 0;

        $daftar = [
            ['KO-PER-002', 'darurat',   'kritis', 'selesai',    'Genset mati mendadak saat beban puncak',
             'Filter solar tersumbat dan sensor tekanan oli lemah', 26, 20, 4_850_000.0,
             [['Filter solar', 2, 'pcs', 385_000.0], ['Sensor tekanan oli', 1, 'pcs', 1_240_000.0]]],

            ['KO-PER-001', 'korektif',  'tinggi', 'dikerjakan', 'Rem sekunder hoist selip saat uji beban',
             'Kampas rem aus melewati batas', 12, null, 2_100_000.0,
             [['Kampas rem hoist', 1, 'set', 1_850_000.0]]],

            ['KO-SAR-001', 'preventif', 'sedang', 'selesai',    'Kalibrasi berkala jembatan timbang',
             null, 34, 33, 3_500_000.0, []],

            ['KO-INS-001', 'preventif', 'sedang', 'selesai',    'Pengukuran tahanan pembumian panel workshop',
             null, 21, 21, 750_000.0, []],

            ['KO-SAR-002', 'preventif', 'tinggi', 'dibuka',     'Uji katup penutup darurat tangki bahan bakar',
             null, 2, null, 0.0, []],

            ['KO-PRA-001', 'prediktif', 'tinggi', 'dikerjakan', 'Pembacaan piezometer tanggul naik tiga minggu berturut',
             'Rembesan pada kaki tanggul sisi timur', 9, null, 0.0, []],
        ];

        foreach ($daftar as $i => [
            $kode, $jenis, $prioritas, $status, $gejala, $penyebab,
            $lapor, $tutup, $biaya, $suku,
        ]) {
            $o = $objek[$kode] ?? null;

            $dilaporkan = $this->kini->copy()->subDays($lapor);

            $wo = $this->baru(WorkOrder::class, [
                'user_id'         => $this->pengaju?->getKey(),
                'ko_object_id'    => $o?->id,
                'nomor'           => Nomor::susun('Formulir', $this->c, 300 + $i + 1)
                    ?: 'WO-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'jenis'           => $jenis,
                'prioritas'       => $prioritas,
                'status'          => $status,
                'gejala'          => $gejala,
                'penyebab'        => $penyebab,
                'tindakan'        => $status === 'selesai'
                    ? 'Diperbaiki, diuji fungsi, dan dikembalikan ke operasi.'
                    : ($status === 'dikerjakan' ? 'Perbaikan berjalan; alat distandbykan.' : null),
                'dilaporkan_pada' => $dilaporkan,
                'mulai_pada'      => $status === 'dibuka' ? null : $dilaporkan->copy()->addHours(6),
                'selesai_pada'    => $tutup === null ? null : $this->kini->copy()->subDays($tutup),
                'hm_saat_rusak'   => 12_400 + $i * 615,
                'biaya'           => $biaya,
            ]);
            $n++;

            if ($status === 'selesai') {
                $wo->forceFill([
                    'ditutup_oleh'       => $this->peninjau?->getKey() ?? $this->pengaju?->getKey(),
                    'diverifikasi_oleh'  => $this->peninjau?->getKey(),
                    'diverifikasi_pada'  => $this->kini->copy()->subDays(max(0, (int) $tutup - 1)),
                ])->saveQuietly();
            }

            foreach ($suku as [$nama, $jumlah, $satuan, $harga]) {
                WorkOrderPart::withoutGlobalScopes()->create([
                    'work_order_id' => $wo->id,
                    'nama'          => $nama,
                    'jumlah'        => $jumlah,
                    'satuan'        => $satuan,
                    'harga_satuan'  => $harga,
                ]);
                $n++;
            }
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

        /** @var array<string,EnergyEquipment> menurut kodenya */
        $alat = [];

        foreach ($daftar as [$kode, $nama, $merek, $kategori, $hp, $payload]) {
            $e = $this->baru(EnergyEquipment::class, [
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

            $alat[$kode] = $e;
        }

        return $n + $this->energiCatatan($alat);
    }

    /**
     * Pemakaian energi harian: solar per alat, listrik per area,
     * produksi pembaginya, rekonsiliasi tangki, garis dasar, dan
     * peluang penghematan.
     *
     * Registri alat saja tidak cukup. Intensitas energi adalah GJ per
     * ton, dan tanpa satu pun catatan pemakaian maupun produksi, kedua
     * sisi pecahannya nol — halaman rekapnya menggambar grafik kosong
     * yang tidak dapat dibedakan dari tambang yang sedang berhenti.
     *
     * @param  array<string,EnergyEquipment>  $alat  menurut kodenya
     */
    private function energiCatatan(array $alat): int
    {
        $n    = 0;
        $hari = 14;

        /* ── produksi harian ── */

        for ($i = $hari; $i >= 1; $i--) {
            $tgl = $this->kini->copy()->subDays($i);

            /* Akhir pekan lebih rendah, dan satu hari nyaris berhenti
               karena hujan. Deret yang rata tidak memperlihatkan apakah
               grafiknya benar-benar mengikuti datanya. */
            $faktor = $tgl->isSunday() ? 0.45 : ($i === 6 ? 0.2 : 1.0);

            $this->baru(EnergyProduction::class, [
                'tanggal' => $tgl->toDateString(),
                'ton'     => round(8_400 * $faktor),
                'bcm'     => round(6_100 * $faktor),
            ]);
            $n++;
        }

        /* ── solar per alat ──

           `hm` di sini adalah JAM OPERASI HARI ITU, bukan angka jam
           meter kumulatif. Halaman rekap menghitung liter per jam
           sebagai liter dibagi SUM(hm); diisi angka meter kumulatif
           (18.420 dan seterusnya), pembaginya menjadi ratusan ribu dan
           setiap alat dilaporkan 0,0 L/jam — daftar "alat paling haus"
           yang seluruh barisnya nol. */

        /* Liter per jam yang wajar menurut kelasnya; satu truk sengaja
           lebih boros supaya perbandingan antar alat punya pemenang dan
           pecundang. */
        $lph = ['EQ-HD-001' => 38.0, 'EQ-HD-002' => 46.5,
                'EQ-EX-001' => 62.0, 'EQ-DZ-001' => 41.0, 'EQ-GR-001' => 19.5];

        foreach ($alat as $kode => $e) {
            for ($i = $hari; $i >= 1; $i--) {
                $tgl = $this->kini->copy()->subDays($i);

                /* Hari Minggu TETAP dicatat, dengan jam yang lebih
                   pendek. Melewatinya sama sekali sementara produksi
                   hari itu tetap dicatat membuat intensitas energinya
                   0 GJ/ton — dan nol pada kolom intensitas terbaca
                   sebagai efisiensi sempurna, bukan sebagai pengukuran
                   yang tidak ada. */
                $jam = $tgl->isSunday() ? 4.5 : ($i === 6 ? 3.0 : 9.5);
                $idle = round($jam * ($kode === 'EQ-HD-002' ? 0.22 : 0.12), 1);

                $this->baru(EnergyFuelLog::class, [
                    'equipment_id' => $e->id,
                    'tanggal'      => $tgl->toDateString(),
                    'hm'           => $jam,
                    'liter'        => round($jam * $lph[$kode], 1),
                    'idle_jam'     => $idle,
                    /* Nol, bukan null: kolomnya NOT NULL berdefault 0.
                       Alat yang memang tidak mengangkut apa pun tercatat
                       0 ton — itu keadaan yang berbeda dari "tidak
                       diketahui", dan keduanya tidak boleh tertukar pada
                       pembagi intensitas energi. */
                    'jarak_km'     => str_starts_with($kode, 'EQ-HD') ? round($jam * 7.4, 1) : 0,
                    'ton'          => str_starts_with($kode, 'EQ-HD') ? round($jam * 96) : 0,
                    'bcm'          => $kode === 'EQ-EX-001' ? round($jam * 210) : 0,
                    'cycle_menit'  => $kode === 'EQ-EX-001' ? 0.6 : null,
                ]);
                $n++;
            }
        }

        /* ── listrik per area ── */

        foreach ([
            /* Solar genset 0 untuk yang bersumber PLN — kolomnya NOT
               NULL, dan 0 memang benar: area itu tidak membakar solar. */
            ['camp',     'pln',    2_450, 168.0, 24.0, 0.0],
            ['workshop', 'pln',    1_180,  96.0, 12.0, 0.0],
            ['office',   'pln',      420,  38.0, 10.0, 0.0],
            ['crusher',  'pln',    9_800, 720.0, 18.0, 0.0],
            ['pump',     'genset', 3_150, 240.0, 20.0, 780.0],
        ] as $j => [$area, $sumber, $kwh, $puncak, $jam, $liter]) {
            for ($i = 3; $i >= 1; $i--) {
                $this->baru(EnergyPowerLog::class, [
                    'tanggal'       => $this->kini->copy()->subDays($i)->toDateString(),
                    'area'          => $area,
                    'sumber'        => $sumber,
                    'kwh'           => $kwh + $j * 10 - $i * 25,
                    'puncak_kw'     => $puncak,
                    'jam_operasi'   => $jam,
                    'liter_genset'  => $liter,
                ]);
                $n++;
            }
        }

        /* ── energi lain ── */

        foreach ([
            ['LPG',      'kg', 240.0, 'Dapur mess karyawan'],
            ['Oli mesin','liter', 860.0, 'Pemakaian workshop bulan berjalan'],
        ] as [$jenis, $satuan, $jumlah, $ket]) {
            $this->baru(EnergyOtherLog::class, [
                'tanggal'     => $this->kini->copy()->subDays(5)->toDateString(),
                'jenis'       => $jenis,
                'satuan'      => $satuan,
                'jumlah'      => $jumlah,
                'keterangan'  => $ket,
            ]);
            $n++;
        }

        /* ── rekonsiliasi tangki ──

           Selisih antara yang disalurkan dan yang tercatat terpakai
           adalah satu-satunya cara kehilangan solar terlihat. Dibuat
           TIDAK nol dengan sengaja: rekonsiliasi yang selalu pas tidak
           membuktikan penghitungnya bekerja. */
        $stok = 48_000.0;

        for ($i = 3; $i >= 1; $i--) {
            $salur = 12_400.0 - $i * 220;
            $awal  = $stok;
            $stok  = $awal - $salur + 11_000;

            $this->baru(EnergyFuelRecon::class, [
                'tanggal'           => $this->kini->copy()->subDays($i)->toDateString(),
                'disalurkan_liter'  => $salur,
                'stok_awal_liter'   => $awal,
                'stok_akhir_liter'  => $stok,
                'catatan'           => $i === 2 ? 'Selisih diperiksa; dugaan penguapan dan sisa selang.' : null,
            ]);
            $n++;
        }

        /* ── garis dasar dan target ── */

        foreach ([[$this->kini->year - 1, 0.0246, 0.0240], [$this->kini->year, 0.0240, 0.0228]] as [$th, $dasar, $target]) {
            $this->baru(EnergyBaseline::class, [
                'tahun'           => $th,
                'baseline_gj_ton' => $dasar,
                'target_gj_ton'   => $target,
                'catatan'         => 'Garis dasar contoh; dihitung dari pemakaian tahun sebelumnya.',
            ]);
            $n++;
        }

        /* ── peluang penghematan ── */

        foreach ([
            /* Penghematan yang tidak berlaku dicatat 0, bukan kosong:
               peluang yang hanya menghemat listrik memang menghemat nol
               liter solar, dan rekapnya menjumlahkan kedua kolomnya. */
            ['Batasi idle truk hauling maksimal 10%',      'Pit',      'berjalan',  38_000.0,     0.0],
            ['Ganti lampu penerangan camp ke LED',          'camp',     'disetujui',      0.0, 46_000.0],
            ['Perbaiki penjadwalan pompa dewatering',       'pump',     'usulan',    12_500.0, 18_000.0],
            ['Pasang meter listrik terpisah tiap area',     'workshop', 'selesai',        0.0,  9_400.0],
        ] as $i => [$judul, $area, $status, $liter, $kwh]) {
            $this->baru(EnergyOpportunity::class, [
                'judul'             => $judul,
                'area'              => $area,
                'status'            => $status,
                'uraian'            => 'Peluang contoh; angkanya perkiraan setahun penuh.',
                'hemat_liter'       => $liter,
                'hemat_kwh'         => $kwh,
                'penanggung_jawab'  => $this->peninjau?->name ?? 'Kepala Teknik Tambang',
                'target_selesai'    => $this->kini->copy()->addMonths(2 + $i)->toDateString(),
                'user_id'           => $this->pengaju?->getKey(),
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

            $a = $this->baru(SmkpAudit::class, [
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

                /* NILAINYA IKUT DIISI, dan itu perbaikan bukan hiasan.
                   Sebelumnya kolom `hasil` dibiarkan kosong, sehingga
                   sesudah "muat data contoh" seluruh keluaran audit —
                   form penilaian, formulir kriteria, rekap
                   ketidaksesuaian, dasbor performa — tetap menampilkan
                   nol. Modulnya terlihat dipakai dari daftar periodenya
                   dan tidak dapat diperiksa sama sekali dari isinya. */
                'hasil'           => $this->smkpHasil($status === 'selesai'),
            ]);
            $n++;

            /* ── temuan ──

               Audit tanpa temuan adalah audit yang tidak dapat
               dibedakan dari audit yang belum dikerjakan. Ketiga
               statusnya dipakai sekaligus supaya rekap "terbuka /
               berjalan / tertutup" punya isi di ketiga kolomnya, dan
               satu temuan sengaja MELEWATI target tanggalnya —
               keterlambatan yang tidak pernah muncul di data contoh
               tidak membuktikan penghitung keterlambatannya bekerja. */
            $temuan = [
                ['I.1.1', 'Ketidaksesuaian Mayor',
                 'Kebijakan keselamatan belum ditinjau ulang dalam tiga tahun terakhir.',
                 'Tinjauan manajemen tidak dijadwalkan dalam program tahunan.',
                 'Closed', -60, -35],
                ['II.3.2', 'Ketidaksesuaian Minor',
                 'Sebagian identifikasi bahaya belum memuat pengendalian yang dapat diverifikasi.',
                 'Format IBPR lama masih dipakai di dua departemen.',
                 'In Progress', 20, null],
                ['IV.2.1', 'Ketidaksesuaian Minor',
                 'Rekaman inspeksi jalan angkut tidak lengkap pada dua bulan berjalan.',
                 'Pengawas belum mendapat pelatihan pengisian formulir baru.',
                 'Open', -8, null],
                ['V.1.4', 'Observasi',
                 'Papan informasi keselamatan di simpang timbang tertutup material.',
                 null, 'Closed', -25, -21],
            ];

            /* Tahun berjalan baru sampai tahap awal; temuannya belum
               semuanya terbit. Audit yang belum selesai tetapi sudah
               punya temuan lengkap adalah keadaan yang tidak mungkin. */
            foreach (array_slice($temuan, 0, $status === 'selesai' ? 4 : 2) as $t) {
                [$kode, $jenis, $uraian, $akar, $st, $targetHari, $selesaiHari] = $t;

                $this->baru(SmkpFinding::class, [
                    'audit_id'          => $a->id,
                    'kode_kriteria'     => $kode,
                    'jenis'             => $jenis,
                    'uraian'            => $uraian,
                    'akar_masalah'      => $akar,
                    'tindakan'          => $st === 'Open'
                        ? null : 'Perbaikan dijalankan dan buktinya dilampirkan.',
                    'penanggung_jawab'  => $this->peninjau?->name ?? 'Kepala Teknik Tambang',
                    'target_selesai'    => $this->kini->copy()->addDays($targetHari)->toDateString(),
                    'tanggal_selesai'   => $selesaiHari === null
                        ? null : $this->kini->copy()->addDays($selesaiHari)->toDateString(),
                    'status'            => $st,
                    'verifikasi'        => $st === 'Closed'
                        ? 'Diverifikasi ketua auditor; bukti memadai.' : null,
                ]);
                $n++;
            }

            /* ── peserta rapat ── */

            foreach ([
                ['pembukaan', 'Kepala Teknik Tambang',   'KTT'],
                ['pembukaan', 'Ketua Auditor Internal',  'Auditor'],
                ['penutupan', 'Kepala Teknik Tambang',   'KTT'],
                ['penutupan', 'Pengawas Operasional',    'Auditee'],
            ] as $j => [$rapat, $jabatan, $peran]) {
                if ($status !== 'selesai' && $rapat === 'penutupan') continue;

                $this->baru(SmkpAttendee::class, [
                    'audit_id'      => $a->id,
                    'rapat'         => $rapat,
                    'nama'          => [$this->peninjau?->name ?? 'Kepala Teknik Tambang',
                                        $this->pengaju?->name  ?? 'Auditor Internal'][$j % 2],
                    'jabatan'       => $jabatan,
                    'perusahaan'    => $this->c->name,
                    'tanda_tangan'  => null,
                ]);
                $n++;
            }

            $n += $this->smkpBukti($a);
            $n += $this->smkpOfi($a);
        }

        return $n;
    }

    /**
     * Nilai tiap butir kriteria, sebaran yang dapat dihitung ulang.
     *
     * DITENTUKAN DARI POSISI BUTIR, bukan diacak. Data contoh yang
     * berubah tiap kali dimuat membuat dua orang yang membandingkan
     * layarnya memperoleh angka berbeda, dan tidak ada cara mengetahui
     * mana yang salah. Polanya sengaja sederhana sehingga skornya dapat
     * diperiksa dengan tangan:
     *
     *   tiap butir ke-17  di luar lingkup perusahaan (N/A)
     *   tiap butir ke-7   jatuh — inilah yang melahirkan temuan
     *   sisanya           penuh atau hampir penuh
     *
     * Audit yang BELUM selesai hanya diisi sebagian: audit berjalan yang
     * seluruh butirnya sudah dinilai adalah keadaan yang tidak mungkin,
     * dan bilah kemajuan yang selalu penuh tidak membuktikan apa pun.
     *
     * @return array<string,array<string,mixed>>
     */
    private function smkpHasil(bool $tuntas): array
    {
        $hasil = [];
        $butir = Smkp::butir();
        $batas = $tuntas ? count($butir) : (int) round(count($butir) * 0.55);

        foreach ($butir as $i => $b) {
            if ($i >= $batas) break;                 // sisanya belum dinilai

            $maks = (int) $b['maks'];
            $urut = $i + 1;

            if ($urut % 17 === 0) {
                $hasil[$b['kode']] = ['v' => Smkp::NA];
                continue;
            }

            $nilai = match (true) {
                $urut % 7 === 0  => 0,                        // jatuh — calon temuan
                $urut % 5 === 0  => (int) floor($maks / 2),    // separuh
                $urut % 3 === 0  => max(0, $maks - 1),         // hampir penuh
                default          => $maks,                    // penuh
            };

            $hasil[$b['kode']] = [
                'v'     => $nilai,
                'ket'   => $nilai === 0
                    ? 'Bukti yang diminta tidak dapat ditunjukkan saat audit lapangan.'
                    : '',
                'bukti' => $nilai === 0 ? '' : 'Dokumen dan wawancara pemilik proses.',
            ];
        }

        return $hasil;
    }

    /**
     * Berkas bukti pada beberapa butir kriteria.
     *
     * BERKASNYA SENGAJA TIDAK ADA DI DISK. Data contoh tidak mengunggah
     * apa pun, dan menaruh berkas palsu di disk tertutup berarti data
     * contoh meninggalkan berkas yang tidak ikut terbuang saat data
     * contohnya dibuang. Yang membukanya memperoleh 404 dari rute
     * penyaji — jawaban yang benar bagi berkas yang memang tidak ada.
     *
     * Yang dibuktikan barisnya: bahwa lencana berkas tergambar pada
     * butirnya, bahwa hitungannya benar, dan bahwa penghapusannya ikut
     * terbawa saat auditnya dibuang.
     */
    private function smkpBukti(SmkpAudit $a): int
    {
        $n = 0;

        foreach ([
            ['I.1',    'SOP-HSE-001 Kebijakan Keselamatan rev.3',      'kebijakan-keselamatan-rev3.pdf', 412_000],
            ['I.1',    'Notulen tinjauan manajemen 12 Maret',          'notulen-tinjauan-manajemen.pdf',  188_000],
            ['II.2.1', 'Risalah komunikasi risiko lintas departemen',  'risalah-komunikasi-risiko.pdf',   264_000],
            ['III.1',  'Struktur organisasi KP dan surat penunjukan',  'struktur-organisasi-kp.pdf',      356_000],
            ['IV.2.1', 'Rekaman inspeksi jalan angkut dua bulan',      'inspeksi-jalan-angkut.xlsx',      144_000],
        ] as [$kode, $catatan, $berkas, $ukuran]) {
            $this->baru(SmkpBukti::class, [
                'audit_id'  => $a->id,
                'kode'      => $kode,
                'catatan'   => $catatan,
                'file_path' => "smkp/{$a->id}/contoh-".str($berkas)->slug().'.bin',
                'file_name' => $berkas,
                'file_size' => $ukuran,
                'mime'      => str_ends_with($berkas, '.pdf') ? 'application/pdf' : null,
                'user_id'   => $this->pengaju?->id,
            ]);
            $n++;
        }

        return $n;
    }

    /**
     * Peluang perbaikan atas butir yang capaiannya PENUH.
     *
     * Syaratnya sama dengan yang ditegakkan server, dan dipakai dari
     * sumber yang sama — bukan dikarang ulang di sini. Data contoh yang
     * mencatat peluang atas butir bernilai 40% akan membuat lembar OFI
     * berbunyi kebalikan dari keadaannya, dan yang membaca data contoh
     * biasanya sedang memutuskan apakah fiturnya bekerja.
     *
     * Kosong bila belum ada butir yang sempurna, dan itu benar: audit
     * yang belum dinilai memang belum punya peluang perbaikan.
     */
    private function smkpOfi(SmkpAudit $a): int
    {
        $uraian = [
            'Seluruh kriteria terpenuhi. Peninjauan masih dikerjakan manual tiap semester; '
            .'pengingat otomatis akan melepas ketergantungan pada satu orang.',

            'Sudah memenuhi seluruhnya. Cakupannya baru pada area produksi utama; '
            .'peluangnya memperluas ke area penunjang pada periode berikutnya.',

            'Terpenuhi penuh. Buktinya masih berupa berkas cetak; pemindaian ke sistem '
            .'akan mempercepat penelusuran saat audit eksternal.',
        ];

        $n = 0;

        foreach (array_slice(SmkpPeluang::berhak($a), 0, count($uraian)) as $i => $b) {
            $this->baru(SmkpOfi::class, [
                'audit_id'         => $a->id,
                'kode'             => $b['kode'],
                'lingkup'          => $b['lingkup'],
                'uraian'           => $uraian[$i],
                'saran'            => 'Tetapkan pemilik proses dan jadwal peninjauan tertulis.',
                'penanggung_jawab' => $this->peninjau?->name ?? 'Kepala Teknik Tambang',
                'target'           => $this->kini->copy()->addMonths(3 + $i)->toDateString(),
                'status'           => ['terbuka', 'ditindaklanjuti', 'terbuka'][$i],
                'user_id'          => $this->pengaju?->id,
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
            ['Penanganan Bahan Bakar di Area Tambang',     'Operasional'],
            ['Pengoperasian Alat Angkut di Jalan Hauling', 'Operasional'],
            ['Tanggap Darurat Kebakaran Workshop',         'Keselamatan'],
            ['Pemeriksaan Harian Kolam Pengendap',         'Lingkungan'],
        ];

        foreach ($daftar as $i => [$judul, $kategori]) {
            $kode = Nomor::susun('Prosedur', $this->c, $i + 1)
                ?: 'SOP-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
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

        /** @var list<Module> dipakai mencatat penyelesaian per peserta */
        $modulKursus = [];

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

            $modulKursus[] = $modul;
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

            /* Kemajuan belajar dicatat per modul, bukan hanya sebagai
               persen pada pendaftaran. Halaman belajar menandai modul
               mana yang sudah dilewati dari sini; tanpa barisnya,
               peserta yang kemajuannya 100% tetap melihat seluruh
               modulnya belum tercentang. */
            $selesai = $lulus ? $modulKursus : array_slice($modulKursus, 0, 2);

            foreach ($selesai as $m) {
                ModuleCompletion::withoutGlobalScopes()->create([
                    'user_id' => $orang->id, 'module_id' => $m->id,
                ]);
                $n++;
            }

            if (!$lulus) continue;

            /* Hanya yang lulus yang bersertifikat — kalau semuanya
               bersertifikat, aturan "sertifikat menyusul kelulusan"
               tidak pernah terbukti berlaku. */
            $this->baru(Certificate::class, [
                'user_id'           => $orang->id,
                'course_id'         => $kursus->id,
                'certificate_number' => Nomor::susun('Sertifikat', $this->c, $i + 1)
                    ?: 'SRT-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
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
