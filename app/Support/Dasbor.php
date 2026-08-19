<?php

namespace App\Support;

use App\Models\{
    AngkutMuatan, BiayaRealisasi, Document, GeoBacaan, GudangBarang,
    HazardReport, Inspection, IzinKerja, KoObject, LedakRencana,
    LingkunganPantau, MineOperationalRecord, MinerbaConservationRecord,
    Paspor, PasporKartu, Procedure, SmkpAudit, SmkpFinding,
    TpkkpAssessment, WaterLog, WorkOrder, EnergyFuelLog
};
use Illuminate\Support\Carbon;

/**
 * Keadaan tiap modul, untuk dasbor.
 *
 * SATU ANGKA PER MODUL, DAN ANGKA ITU SELALU "BERAPA YANG PERLU
 * DIKERJAKAN". Bukan berapa banyak datanya. Dasbor yang menampilkan
 * "1.482 catatan angkutan" memberitahu bahwa modulnya dipakai, dan
 * tidak memberitahu apa pun yang dapat ditindaklanjuti; yang membuka
 * dasbor pagi hari menanyakan sesuatu yang lain — apa yang harus saya
 * urus hari ini.
 *
 * NOL ADALAH JAWABAN YANG BAIK, dan karena itu ditampilkan hijau, bukan
 * disembunyikan. Modul yang menghilang saat semuanya beres membuat
 * orang tidak dapat membedakan "tidak ada masalah" dari "tidak ada
 * datanya" — dan kedua keadaan itu menuntut tindakan yang berbeda.
 *
 * TIAP ANGKA PUNYA ATURAN YANG TERTULIS. Aturannya sengaja sederhana
 * dan dapat diperiksa dari satu baris kueri; ambang yang rumit di
 * dasbor adalah ambang yang tidak dipercaya orang, sebab tidak ada yang
 * dapat memeriksanya tanpa membaca kode.
 */
final class Dasbor
{
    /** Ambang "sudah dekat" untuk berkas berjangka, sama dengan kartu. */
    private const DEKAT_HARI = 30;

    /**
     * Keadaan seluruh modul yang boleh dilihat pengguna ini.
     *
     * Disaring lewat Menu::untuk() — modul yang tidak ada di bilah
     * sampingnya tidak muncul di dasbornya. Ubin yang mengarah ke
     * halaman yang menolak membukanya lebih buruk daripada ubin yang
     * tidak ada.
     *
     * @return list<array<string,mixed>>
     */
    public static function modul($pengguna): array
    {
        $boleh = array_keys(Menu::untuk($pengguna));

        $menu = Menu::all();

        return array_values(array_map(
            /* Ikonnya diambil dari bilah samping modul itu sendiri,
               bukan dari daftar ikon tersendiri. Ubin yang memakai ikon
               berbeda dari ikon sampingnya memaksa orang mempelajari dua
               lambang untuk satu modul — dan daftar ikon kedua adalah
               tempat pertama yang tertinggal saat modulnya berubah. */
            fn (array $m) => $m + ['ikon' => $menu[$m['modul']]['icon'] ?? null],
            array_values(array_filter(
                [...self::semua(), ...self::beralur()],
                fn (array $m) => in_array($m['modul'], $boleh, true),
            )),
        ));
    }

    /**
     * Ubin per modul.
     *
     * `nilai` selalu jumlah yang perlu dikerjakan, `total` jumlah
     * seluruhnya sebagai pembanding, dan `nada` menentukan warnanya:
     * gawat bila menuntut hari ini, ingat bila menuntut minggu ini,
     * baik bila tidak ada.
     *
     * @return list<array<string,mixed>>
     */
    private static function semua(): array
    {
        $kini = Carbon::now();

        return [
            /* ═══ orang ═══ */
            [
                'modul' => 'miners', 'nama' => 'Masa Berlaku Berkas',
                'ket'   => 'MCU, permit, atau SIMPER sudah lewat',
                'nilai' => self::berkasHabis(),
                'total' => Paspor::count(),
                'rute'  => 'miners.kedaluwarsa', 'nada' => 'gawat',
            ],
            [
                'modul' => 'miners', 'nama' => 'Kartu Menunggu Tinjauan',
                'ket'   => 'Diajukan, belum diputus',
                'nilai' => PasporKartu::where('status', Alur::DIAJUKAN)->count(),
                'total' => PasporKartu::count(),
                'rute'  => 'miners.riwayat.mine-permit', 'nada' => 'ingat',
            ],

            /* ═══ bahaya dan inspeksi ═══ */
            [
                'modul' => 'hazrep', 'nama' => 'Hazard Report',
                'ket'   => 'Laporan bahaya belum ditutup',
                'nilai' => HazardReport::where('status', '<>', 'Closed')->count(),
                'total' => HazardReport::count(),
                'rute'  => 'hazard.index', 'nada' => 'gawat',
            ],
            [
                'modul' => 'hazrep', 'nama' => 'Inspeksi',
                'ket'   => 'Inspeksi masih berjalan',
                'nilai' => Inspection::where('status', '<>', 'Selesai')->count(),
                'total' => Inspection::count(),
                'rute'  => 'inspeksi.index', 'nada' => 'ingat',
            ],

            /* ═══ keselamatan operasi ═══ */
            [
                'modul' => 'ko', 'nama' => 'Keselamatan Operasi',
                'ket'   => 'Objek lewat jadwal perawatan',
                'nilai' => KoObject::whereNotNull('pm_berikutnya')
                    ->whereDate('pm_berikutnya', '<', $kini)->count(),
                'total' => KoObject::count(),
                'rute'  => 'ko.index', 'nada' => 'gawat',
            ],

            /* ═══ sistem manajemen ═══ */
            [
                'modul' => 'smkp', 'nama' => 'Temuan SMKP',
                'ket'   => 'Temuan audit belum ditutup',
                'nilai' => SmkpFinding::where('status', '<>', 'Closed')->count(),
                'total' => SmkpFinding::count(),
                'rute'  => 'smkp.index', 'nada' => 'ingat',
            ],
            [
                'modul' => 'tpkkp', 'nama' => 'Safety Maturity',
                'ket'   => 'Penilaian PTPKKP tercatat',
                'nilai' => TpkkpAssessment::count(),
                'total' => TpkkpAssessment::count(),
                'rute'  => 'tpkkp.index', 'nada' => 'kabar',
            ],
            [
                'modul' => 'dokumen', 'nama' => 'ISO & Dokumen',
                'ket'   => 'Lewat masa tinjau',
                'nilai' => Document::where('status', 'berlaku')->whereNotNull('tanggal_tinjau')
                    ->whereDate('tanggal_tinjau', '<', $kini)->count(),
                'total' => Document::count(),
                'rute'  => 'dokumen.index', 'nada' => 'ingat',
            ],

            /* ═══ izin kerja ═══
               Izin yang jam selesainya sudah lewat tetapi belum ditutup.
               Bukan izin yang "masih aktif" — izin aktif memang sedang
               dipakai orang; yang berbahaya izin yang pekerjaannya sudah
               usai sementara area masih tercatat terisolasi. */
            [
                'modul' => 'izin', 'nama' => 'Izin Kerja',
                'ket'   => 'Lewat jam selesai, belum ditutup',
                'nilai' => IzinKerja::whereNull('ditutup_pada')
                    ->whereNotNull('selesai')->where('selesai', '<', $kini)->count(),
                'total' => IzinKerja::count(),
                'rute'  => 'izin.index', 'nada' => 'gawat',
            ],

            /* ═══ lingkungan ═══
               Dihitung saat dibaca, terhadap ambang yang berlaku
               sekarang: baku mutu berubah, dan nilai yang tersimpan
               tidak ikut berubah bersamanya. */
            [
                'modul' => 'lingkungan', 'nama' => 'Lingkungan',
                'ket'   => 'Hasil pantau melampaui baku mutu',
                'nilai' => self::pantauMelanggar(),
                'total' => LingkunganPantau::count(),
                'rute'  => 'lingkungan.index', 'nada' => 'gawat',
            ],

            /* ═══ logistik ═══ */
            [
                'modul' => 'gudang', 'nama' => 'Gudang',
                'ket'   => 'Barang habis atau menipis',
                'nilai' => self::stokTipis(),
                'total' => GudangBarang::count(),
                'rute'  => 'gudang.barang', 'nada' => 'ingat',
            ],

            /* ═══ pemeliharaan ═══ */
            [
                'modul' => 'maintenance', 'nama' => 'Pemeliharaan',
                'ket'   => 'Work order belum selesai',
                'nilai' => WorkOrder::where('status', '<>', 'selesai')->count(),
                'total' => WorkOrder::count(),
                'rute'  => 'maintenance.index', 'nada' => 'ingat',
            ],
        ];
    }

    /**
     * Modul yang catatannya menempuh alur tinjauan yang sama.
     *
     * Kesembilan modul ini tidak punya "temuan" atau "jatuh tempo"
     * sendiri; yang menuntut tindakan di sana selalu bentuk yang sama —
     * catatan yang sudah diajukan dan menunggu diputus, dan draf yang
     * belum diajukan sama sekali. Menyatukannya bukan penyeragaman demi
     * kerapian: itu memang satu pekerjaan yang sama, dikerjakan orang
     * yang sama, dari satu tempat.
     *
     * Menampilkan jumlah CATATAN sebagai gantinya — "1.482 penimbangan"
     * — memberitahu bahwa modulnya dipakai dan tidak memberitahu apa pun
     * yang dapat ditindaklanjuti.
     *
     * Nilai keempat menyatakan apakah catatannya menempuh alur tinjauan.
     * Ditulis di sini, bukan ditanyakan ke skema saat dasbor dibuka:
     * pertanyaan itu satu kueri per modul untuk sesuatu yang tidak
     * pernah berubah di antara dua penyebaran.
     *
     * @return array<string,array{0:class-string,1:string,2:string,3:bool}>
     */
    private const BERALUR = [
        'operasi'    => [MineOperationalRecord::class,     'Operasi Tambang',    'operasi.index',     true],
        'peledakan'  => [LedakRencana::class,              'Peledakan',          'peledakan.index',   true],
        'air'        => [WaterLog::class,                  'Penirisan Tambang',  'air.index',         true],
        'konservasi' => [MinerbaConservationRecord::class, 'Konservasi Minerba', 'konservasi.index',  true],
        'geoteknik'  => [GeoBacaan::class,                 'Geoteknik',          'geoteknik.index',   true],
        'biaya'      => [BiayaRealisasi::class,            'Biaya Operasi',      'biaya.index',       true],

        /* Ketiganya tercatat langsung tanpa persetujuan. Yang dapat
           dikatakan tentangnya hanyalah seberapa baru datanya — dan itu
           dikatakan apa adanya, bukan disamarkan sebagai angka yang
           seolah menuntut tindakan. */
        'angkutan'   => [AngkutMuatan::class,              'Angkutan',           'angkutan.muatan',   false],
        'energi'     => [EnergyFuelLog::class,             'Energi',             'energi.index',      false],
        'meh'        => [Procedure::class,                 'Mining Engineering', 'meh.index',         false],
    ];

    /** @return list<array<string,mixed>> */
    private static function beralur(): array
    {
        $keluar = [];

        foreach (self::BERALUR as $modul => [$kelas, $nama, $rute, $beralur]) {
            $jumlah = $kelas::count();

            $keluar[] = [
                'modul' => $modul, 'nama' => $nama,
                'ket'   => $beralur ? 'Menunggu tinjauan' : 'Catatan tersimpan',
                'nilai' => $beralur ? $kelas::where('status', Alur::DIAJUKAN)->count() : $jumlah,
                'total' => $jumlah,
                'rute'  => $rute,
                'nada'  => $beralur ? 'ingat' : 'kabar',
            ];
        }

        return $keluar;
    }

    /**
     * Hasil pantau lingkungan yang melampaui baku mutu.
     *
     * Ambangnya dibaca dari parameternya, bukan dari kolom tersimpan:
     * baku mutu berubah, dan pelanggaran yang dihitung saat menyimpan
     * akan tetap menyebut angka lama selamanya.
     */
    private static function pantauMelanggar(): int
    {
        return LingkunganPantau::with('parameter')->get()
            ->filter(fn (LingkunganPantau $p) => $p->melanggar())
            ->count();
    }

    /**
     * Berapa berkas kelayakan yang masa berlakunya SUDAH lewat.
     *
     * Memakai tanggal efektif — permit yang MCU-nya habis ikut terhitung
     * meski tanggal cetaknya masih panjang. Itu memang angka yang
     * dipakai di gerbang.
     */
    private static function berkasHabis(): int
    {
        $orang = Paspor::with(['kartu', 'mcu', 'company'])->get();
        $baris = PemantauanBerkas::baris($orang);

        return PemantauanBerkas::ringkas($baris)['habis'];
    }

    /**
     * Barang yang stoknya habis atau di bawah stok minimum.
     *
     * Saldonya dihitung dari mutasinya, bukan disimpan sebagai kolom —
     * karena itu mutasinya dimuat sekaligus. Tanpa `with('mutasi')`,
     * satu kueri per barang; pada gudang dengan seribu jenis barang itu
     * seribu kueri untuk sebuah angka di dasbor.
     *
     * `statusStok()` memulangkan larik berisi kode, nama, dan nada —
     * bukan sebuah string. Membandingkannya langsung dengan daftar kode
     * selalu memulangkan salah, dan angkanya nol selamanya tanpa satu
     * pun galat.
     */
    private static function stokTipis(): int
    {
        return GudangBarang::with('mutasi')->get()
            ->filter(fn (GudangBarang $b) => in_array(
                Gudang::statusStok($b)['kode'], ['habis', 'menipis'], true))
            ->count();
    }

    /** Berapa hari lagi dianggap "sudah dekat". */
    public static function ambangDekat(): int
    {
        return self::DEKAT_HARI;
    }
}
