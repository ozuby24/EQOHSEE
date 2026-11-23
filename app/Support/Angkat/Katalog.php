<?php

namespace App\Support\Angkat;

/**
 * Pustaka acuan Lifting & Rigging Management.
 *
 * Angka di sini adalah angka standar, bukan selera: faktor keamanan
 * sling ASME B30.9, persentase beban uji Permenaker 8/2020 dan ASME
 * B30.5, daya dukung izin tanah tambang, batas angin pabrikan crane.
 * Bila perusahaan memakai angka yang lebih ketat, ubah di sini — satu
 * tempat, dan seluruh kalkulator, riksa uji, serta kajian ikut berubah.
 */
final class Katalog
{
    /** Pesawat angkat. kategori menentukan cara kapasitas dibaca (kurva beban vs datar). */
    public const ALAT = [
        'mobile_50t' => ['label' => 'Mobile crane all-terrain 50 t', 'kategori' => 'mobile', 'kapasitas_t' => 50, 'berat_t' => 36, 'boom_m' => 40, 'angin_kmh' => 32, 'daf' => 1.10, 'hook_t' => 0.65],
        'mobile_100t' => ['label' => 'Mobile crane all-terrain 100 t', 'kategori' => 'mobile', 'kapasitas_t' => 100, 'berat_t' => 60, 'boom_m' => 52, 'angin_kmh' => 32, 'daf' => 1.10, 'hook_t' => 1.10],
        'mobile_250t' => ['label' => 'Mobile crane all-terrain 250 t', 'kategori' => 'mobile', 'kapasitas_t' => 250, 'berat_t' => 84, 'boom_m' => 70, 'angin_kmh' => 32, 'daf' => 1.10, 'hook_t' => 1.85],
        'crawler_150t' => ['label' => 'Crawler crane lattice boom 150 t', 'kategori' => 'crawler', 'kapasitas_t' => 150, 'berat_t' => 115, 'boom_m' => 65, 'angin_kmh' => 36, 'daf' => 1.10, 'hook_t' => 1.60],
        'overhead_40t' => ['label' => 'Overhead crane workshop 40 t', 'kategori' => 'overhead', 'kapasitas_t' => 40, 'berat_t' => 0, 'boom_m' => 24, 'angin_kmh' => 99, 'daf' => 1.05, 'hook_t' => 0.45],
        'hiab_15t' => ['label' => 'Truck-mounted crane (HIAB) 15 t', 'kategori' => 'truk', 'kapasitas_t' => 15, 'berat_t' => 18, 'boom_m' => 16, 'angin_kmh' => 30, 'daf' => 1.12, 'hook_t' => 0.25],
        'tandem_2x100t' => ['label' => 'Pengangkatan tandem 2 × 100 t', 'kategori' => 'tandem', 'kapasitas_t' => 200, 'berat_t' => 120, 'boom_m' => 52, 'angin_kmh' => 28, 'daf' => 1.25, 'hook_t' => 2.20],
        'hoist_10t' => ['label' => 'Chain block / lever hoist 10 t', 'kategori' => 'hoist', 'kapasitas_t' => 10, 'berat_t' => 0, 'boom_m' => 12, 'angin_kmh' => 40, 'daf' => 1.05, 'hook_t' => 0.12],
    ];

    /** Alat bantu angkat: faktor keamanan minimum, beban uji, standar, dan butir pemeriksaan visual. */
    public const GEAR = [
        'wire_rope' => ['label' => 'Wire rope sling', 'sf' => 5.0, 'uji' => 'wire_rope', 'standar' => 'ASME B30.9 · SNI 0076'],
        'rantai' => ['label' => 'Alloy chain sling G80/G100', 'sf' => 4.0, 'uji' => 'rantai', 'standar' => 'ASME B30.9 · EN 818'],
        'webbing' => ['label' => 'Webbing / round sling poliester', 'sf' => 7.0, 'uji' => 'webbing', 'standar' => 'ASME B30.9 · EN 1492'],
        'shackle' => ['label' => 'Shackle', 'sf' => 5.0, 'uji' => 'shackle', 'standar' => 'ASME B30.26'],
        'spreader' => ['label' => 'Spreader / lifting beam', 'sf' => 3.0, 'uji' => 'spreader', 'standar' => 'ASME B30.20 · BTH-1'],
    ];

    /** Beban uji (proof load) dan masa berlaku menurut kategori — Permenaker 8/2020 & ASME B30. */
    public const UJI = [
        'crane' => ['label' => 'Pesawat angkat (crane/hoist)', 'statis' => 125, 'dinamis' => 110, 'tahan' => 10, 'bulan' => 12, 'defleksi_mm' => 0.0],
        'wire_rope' => ['label' => 'Wire rope sling', 'statis' => 200, 'dinamis' => 0, 'tahan' => 5, 'bulan' => 6, 'defleksi_mm' => 0.0],
        'rantai' => ['label' => 'Alloy chain sling', 'statis' => 200, 'dinamis' => 0, 'tahan' => 5, 'bulan' => 6, 'defleksi_mm' => 0.0],
        'webbing' => ['label' => 'Webbing / round sling', 'statis' => 200, 'dinamis' => 0, 'tahan' => 5, 'bulan' => 6, 'defleksi_mm' => 0.0],
        'shackle' => ['label' => 'Shackle', 'statis' => 200, 'dinamis' => 0, 'tahan' => 5, 'bulan' => 6, 'defleksi_mm' => 0.0],
        'spreader' => ['label' => 'Spreader / lifting beam', 'statis' => 125, 'dinamis' => 0, 'tahan' => 10, 'bulan' => 6, 'defleksi_mm' => 2.0],
    ];

    /**
     * Batas ukur yang langsung menolak alat (ASME B30.5/B30.9/B30.26).
     * berlaku: kategori uji tempat ukuran itu dinilai.
     */
    public const UKUR = [
        'keausan_pct' => ['label' => 'Keausan / pengurangan diameter', 'satuan' => '%', 'batas' => 10, 'berlaku' => ['wire_rope', 'rantai', 'shackle', 'crane']],
        'kawat_putus' => ['label' => 'Kawat putus dalam satu lay', 'satuan' => 'helai', 'batas' => 5, 'berlaku' => ['wire_rope', 'crane']],
        'elongasi_pct' => ['label' => 'Pemuluran rantai', 'satuan' => '%', 'batas' => 3, 'berlaku' => ['rantai']],
        'bukaan_kait_pct' => ['label' => 'Pertambahan bukaan kait', 'satuan' => '%', 'batas' => 5, 'berlaku' => ['crane', 'rantai']],
        'defleksi_mm' => ['label' => 'Deformasi permanen sesudah uji beban', 'satuan' => 'mm', 'batas' => 0, 'berlaku' => ['crane', 'wire_rope', 'rantai', 'webbing', 'shackle', 'spreader']],
    ];

    /** Butir pemeriksaan visual & fungsi per kategori uji. kritis = gagal berarti tidak layak digunakan. */
    public const BUTIR = [
        'crane' => [
            ['kode' => 'CR-01', 'label' => 'Dokumen: SUKET lama, manual operasi, load chart terpasang di kabin', 'kritis' => false],
            ['kode' => 'CR-02', 'label' => 'Struktur boom, jib, dan sambungan las bebas retak & deformasi', 'kritis' => true],
            ['kode' => 'CR-03', 'label' => 'Wire rope hoist: kawat putus, birdcage, kink, korosi', 'kritis' => true],
            ['kode' => 'CR-04', 'label' => 'Kait: kunci pengaman (safety latch), retak, bukaan mulut', 'kritis' => true],
            ['kode' => 'CR-05', 'label' => 'Rem hoist & slewing menahan beban uji tanpa slip', 'kritis' => true],
            ['kode' => 'CR-06', 'label' => 'Load moment indicator (LMI) & anti-two-block (ATB) berfungsi', 'kritis' => true],
            ['kode' => 'CR-07', 'label' => 'Outrigger, pad, dan sistem hidraulik tanpa kebocoran', 'kritis' => false],
            ['kode' => 'CR-08', 'label' => 'Limit switch hoist, luffing, dan slewing', 'kritis' => false],
            ['kode' => 'CR-09', 'label' => 'Alarm mundur, lampu, klakson, dan APAR kabin', 'kritis' => false],
        ],
        'wire_rope' => [
            ['kode' => 'WR-01', 'label' => 'Tag identitas & WLL terbaca', 'kritis' => true],
            ['kode' => 'WR-02', 'label' => 'Kawat putus, birdcage, kink, atau inti keluar', 'kritis' => true],
            ['kode' => 'WR-03', 'label' => 'Ferrule / splice tidak retak dan tidak bergeser', 'kritis' => true],
            ['kode' => 'WR-04', 'label' => 'Thimble & mata sling tidak penyok', 'kritis' => false],
            ['kode' => 'WR-05', 'label' => 'Korosi, terbakar panas, atau terkena las', 'kritis' => false],
        ],
        'rantai' => [
            ['kode' => 'CH-01', 'label' => 'Tag grade & WLL terbaca', 'kritis' => true],
            ['kode' => 'CH-02', 'label' => 'Mata rantai retak, bengkok, atau terpelintir', 'kritis' => true],
            ['kode' => 'CH-03', 'label' => 'Master link & kait dengan kunci pengaman', 'kritis' => true],
            ['kode' => 'CH-04', 'label' => 'Bekas las, takik, atau percikan las', 'kritis' => false],
        ],
        'webbing' => [
            ['kode' => 'WB-01', 'label' => 'Label WLL terjahit dan terbaca', 'kritis' => true],
            ['kode' => 'WB-02', 'label' => 'Benang penanda merah (red core) tidak terlihat', 'kritis' => true],
            ['kode' => 'WB-03', 'label' => 'Sobek, terpotong, atau jahitan lepas', 'kritis' => true],
            ['kode' => 'WB-04', 'label' => 'Terbakar, meleleh, atau terkena bahan kimia', 'kritis' => false],
        ],
        'shackle' => [
            ['kode' => 'SH-01', 'label' => 'Marka WLL & grade tercetak di badan', 'kritis' => true],
            ['kode' => 'SH-02', 'label' => 'Badan tidak melebar, bengkok, atau retak', 'kritis' => true],
            ['kode' => 'SH-03', 'label' => 'Pin asli, ulir baik, cotter pin terpasang', 'kritis' => true],
            ['kode' => 'SH-04', 'label' => 'Keausan di bow dan pin', 'kritis' => false],
        ],
        'spreader' => [
            ['kode' => 'SB-01', 'label' => 'Pelat nama: WLL, berat sendiri, nomor gambar', 'kritis' => true],
            ['kode' => 'SB-02', 'label' => 'Las lifting lug bebas retak (sesudah NDT)', 'kritis' => true],
            ['kode' => 'SB-03', 'label' => 'Lubang pin tidak lonjong', 'kritis' => true],
            ['kode' => 'SB-04', 'label' => 'Lendutan balok dalam batas gambar', 'kritis' => false],
        ],
    ];

    public const JENIS_RIKSA = [
        'pertama' => 'Riksa uji pertama',
        'berkala' => 'Riksa uji berkala',
        'khusus' => 'Riksa uji khusus (pasca perbaikan/insiden)',
        'triwulan' => 'Pemeriksaan visual triwulan',
    ];

    public const NDT = ['MPI' => 'Magnetic particle (MPI)', 'PT' => 'Penetrant (PT)', 'UT' => 'Ultrasonik (UT)', 'VISUAL' => 'Visual saja'];

    /** Warna triwulan alat bantu angkat. Merah dipakai khusus untuk karantina. */
    public const WARNA_TRIWULAN = [1 => 'PUTIH', 2 => 'HIJAU', 3 => 'BIRU', 4 => 'KUNING'];

    public const HITCH = [
        'bridle' => ['label' => 'Bridle multi-kaki', 'faktor' => 1.00],
        'vertikal' => ['label' => 'Vertikal lurus', 'faktor' => 1.00],
        'choker' => ['label' => 'Choker (jerat)', 'faktor' => 0.75],
        'basket' => ['label' => 'Basket (keranjang)', 'faktor' => 2.00],
    ];

    /** Daya dukung izin landasan crane (kN/m²). */
    public const TANAH = [
        'beton' => ['label' => 'Lantai beton bertulang workshop', 'izin' => 350],
        'bench' => ['label' => 'Pit bench terpadatkan / batuan keras', 'izin' => 200],
        'haul_road' => ['label' => 'Haul road gravel terpadatkan', 'izin' => 140],
        'timbunan' => ['label' => 'Tanah timbunan / overburden lunak', 'izin' => 75],
    ];

    /** Jenis beban: regulasi acuan dan kendali wajib yang ikut tercetak di rencana. */
    public const BEBAN = [
        'komponen_alat_berat' => ['label' => 'Komponen alat berat (engine, final drive, vessel)',
            'regulasi' => ['Permenaker 8/2020', 'Kepmen ESDM 1827/2018', 'ASME B30.5 & B30.9', 'Spesifikasi lifting lug OEM'],
            'kendali' => ['Verifikasi titik berat (CoG) dan uji angkat 10 cm sebelum diangkat penuh', 'Shackle sesuai lifting lug OEM dan pelindung sudut']],
        'crusher' => ['label' => 'Mesin crusher / CHPP / pabrik pengolahan',
            'regulasi' => ['Permenaker 8/2020', 'Kepmen ESDM 1827/2018', 'Kepdirjen Minerba 185/2019', 'ASME B30.5'],
            'kendali' => ['Isolasi energi (LOTO) penuh pada crusher dan konveyor', 'Verifikasi ruang bebas atap struktur dan outrigger mat ganda']],
        'struktur_panjang' => ['label' => 'Struktur baja / boom ekskavator / pipa panjang',
            'regulasi' => ['Permenaker 8/2020', 'ASME B30.9 & B30.20', 'BS 7121'],
            'kendali' => ['Spreader beam untuk menjaga sudut sling ≥ 60°', 'Minimal dua tag line dan pantau kecepatan angin']],
        'tangki' => ['label' => 'Bejana tekan / tangki BBM / silo',
            'regulasi' => ['Permenaker 8/2020', 'Permenaker 37/2016', 'Kepmen ESDM 1827/2018', 'API RP 2D'],
            'kendali' => ['Tangki kosong, bebas gas (LEL 0%), dan sudah dikuras', 'Tailing crane untuk manuver tegak (upending)']],
        'trafo' => ['label' => 'Trafo daya / switchgear / beban sensitif',
            'regulasi' => ['Permenaker 8/2020', 'PUIL & jarak aman SUTT', 'Kepmen ESDM 1827/2018'],
            'kendali' => ['Kemiringan beban maksimum 2° dan bushing dilindungi', 'Jarak aman ≥ 6 m dari saluran bertegangan dan crane dibumikan']],
        'man_basket' => ['label' => 'Pengangkatan personel (man-basket)',
            'regulasi' => ['Permenaker 8/2020', 'ASME B30.23', 'OSHA 1926.1431', 'Izin khusus KTT'],
            'kendali' => ['Utilisasi crane WAJIB ≤ 50% kapasitas', 'Uji coba 125% tanpa orang, ATB aktif, harness 100% terkait']],
    ];

    public const KLASIFIKASI = [
        'rutin' => ['label' => 'Rutin', 'ket' => 'Utilisasi < 50%, beban < 10 t, rigging sederhana', 'persetujuan' => 'Pengawas operasional'],
        'rekayasa' => ['label' => 'Rekayasa', 'ket' => 'Utilisasi 50–75%, 10–20 t, CoG asimetris, atau blind lift', 'persetujuan' => 'Pengawas operasional + lifting engineer'],
        'kritis' => ['label' => 'Kritis', 'ket' => 'Utilisasi > 75%, ≥ 20 t, tandem, dekat SUTT, di atas fasilitas operasi, atau man-basket', 'persetujuan' => 'Kepala Teknik Tambang (KTT)'],
    ];

    public const STATUS = [
        'draf' => 'Draf', 'diajukan' => 'Menunggu persetujuan', 'disetujui' => 'Disetujui',
        'ditolak' => 'Ditolak', 'selesai' => 'Selesai dilaksanakan', 'batal' => 'Dibatalkan',
    ];

    public const AREA = ['Workshop alat berat', 'Pit penambangan', 'CHPP / pabrik pengolahan', 'Pelabuhan & jetty', 'Power house & gardu', 'Tangki BBM & fuel station', 'Mess & fasilitas umum'];

    /**
     * Enam pilar kajian teknis pertambangan. Butir 'oto' dinilai sistem
     * dari hasil simulasi & register; selebihnya dinilai peninjau.
     */
    public const PILAR = [
        'P1' => ['nama' => 'Geoteknik & landasan', 'butir' => [
            ['kode' => 'P1-1', 'label' => 'Tekanan outrigger ≤ daya dukung izin tanah', 'oto' => true],
            ['kode' => 'P1-2', 'label' => 'Kemiringan landasan ≤ 1%', 'oto' => true],
            ['kode' => 'P1-3', 'label' => 'Luas mat outrigger ≥ luas minimum', 'oto' => true],
            ['kode' => 'P1-4', 'label' => 'Daya dukung diverifikasi geoteknik (DCP/plate load) bila di timbunan', 'oto' => false],
        ]],
        'P2' => ['nama' => 'Kelayakan PAA & alat bantu', 'butir' => [
            ['kode' => 'P2-1', 'label' => 'SUKET riksa uji crane berlaku pada hari pengangkatan', 'oto' => true],
            ['kode' => 'P2-2', 'label' => 'Crane tidak berstatus karantina', 'oto' => true],
            ['kode' => 'P2-3', 'label' => 'Seluruh alat bantu angkat layak & masa uji berlaku', 'oto' => true],
            ['kode' => 'P2-4', 'label' => 'LMI & anti-two-block diuji fungsi sebelum mulai', 'oto' => false],
        ]],
        'P3' => ['nama' => 'Rekayasa pengangkatan & rigging', 'butir' => [
            ['kode' => 'P3-1', 'label' => 'Utilisasi crane dalam batas klasifikasi', 'oto' => true],
            ['kode' => 'P3-2', 'label' => 'Sudut sling ≥ 45° (dianjurkan ≥ 60°)', 'oto' => true],
            ['kode' => 'P3-3', 'label' => 'Tegangan kaki sling & shackle ≤ WLL', 'oto' => true],
            ['kode' => 'P3-4', 'label' => 'Ruang bebas (headroom) ujung boom cukup', 'oto' => true],
            ['kode' => 'P3-5', 'label' => 'Simulasi 4 fase tanpa fase gagal', 'oto' => true],
        ]],
        'P4' => ['nama' => 'Lingkungan tambang', 'butir' => [
            ['kode' => 'P4-1', 'label' => 'Kecepatan angin ≤ batas pabrikan crane', 'oto' => true],
            ['kode' => 'P4-2', 'label' => 'Jarak ke SUTT/saluran bertegangan ≥ 6 m', 'oto' => true],
            ['kode' => 'P4-3', 'label' => 'Koordinasi jadwal peledakan & radius aman', 'oto' => false],
            ['kode' => 'P4-4', 'label' => 'Lalu lintas hauling dialihkan / ditutup', 'oto' => false],
        ]],
        'P5' => ['nama' => 'Kompetensi personel', 'butir' => [
            ['kode' => 'P5-1', 'label' => 'Operator ber-SIO sesuai kelas crane', 'oto' => true],
            ['kode' => 'P5-2', 'label' => 'Rigger bersertifikat', 'oto' => true],
            ['kode' => 'P5-3', 'label' => 'Signalman ditunjuk dan dikenali', 'oto' => true],
            ['kode' => 'P5-4', 'label' => 'Pengawas operasional (POP/POM) hadir', 'oto' => true],
        ]],
        'P6' => ['nama' => 'IBPR/JSA & tanggap darurat', 'butir' => [
            ['kode' => 'P6-1', 'label' => 'JSA pengangkatan disusun dan ditandatangani', 'oto' => false],
            ['kode' => 'P6-2', 'label' => 'Toolbox meeting dilakukan sebelum mulai', 'oto' => false],
            ['kode' => 'P6-3', 'label' => 'Barikade & zona eksklusi di bawah beban', 'oto' => false],
            ['kode' => 'P6-4', 'label' => 'Rencana tanggap darurat & penyelamatan tersedia', 'oto' => false],
        ]],
    ];

    public static function pilihan(): array
    {
        $daftar = fn (array $a, string $kunci = 'label') => collect($a)->map(fn ($v, $k) => ['nilai' => $k, 'label' => is_array($v) ? $v[$kunci] : $v])->values()->all();

        return [
            'alat' => collect(self::ALAT)->map(fn ($v, $k) => ['nilai' => $k] + $v)->values()->all(),
            'gear' => $daftar(self::GEAR),
            'hitch' => collect(self::HITCH)->map(fn ($v, $k) => ['nilai' => $k] + $v)->values()->all(),
            'tanah' => collect(self::TANAH)->map(fn ($v, $k) => ['nilai' => $k] + $v)->values()->all(),
            'beban' => $daftar(self::BEBAN),
            'klasifikasi' => collect(self::KLASIFIKASI)->map(fn ($v, $k) => ['nilai' => $k] + $v)->values()->all(),
            'status' => $daftar(self::STATUS),
            'jenisRiksa' => $daftar(self::JENIS_RIKSA),
            'ndt' => $daftar(self::NDT),
            'area' => self::AREA,
        ];
    }

    /** Warna triwulan untuk tanggal tertentu. */
    public static function warnaTriwulan(\DateTimeInterface $t): string
    {
        return self::WARNA_TRIWULAN[(int) ceil(((int) $t->format('n')) / 3)];
    }
}
