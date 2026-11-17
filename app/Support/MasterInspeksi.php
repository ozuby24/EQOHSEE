<?php

namespace App\Support;

/**
 * Pustaka baku daftar periksa inspeksi.
 *
 * ── Kenapa pustaka, bukan diketik ulang tiap kali ──
 *
 * Inspeksi yang butirnya disusun sendiri oleh tiap pengawas menghasilkan
 * dua lembar yang tidak dapat dibandingkan: yang satu memeriksa tekanan
 * APAR, yang lain hanya "APAR ada". Rekapitulasi bulanannya lalu
 * menjumlahkan dua hal yang berbeda, dan tren yang dibacanya di rapat
 * bukan tren apa pun. Butir yang sama, dipakai berulang, adalah
 * satu-satunya cara angka inspeksi punya arti dari bulan ke bulan.
 *
 * ── Kenapa tiap butir menyebut acuannya ──
 *
 * Yang membedakan daftar periksa dari daftar keinginan adalah dasarnya.
 * Pengawas area yang diminta memperbaiki sesuatu berhak tahu aturan mana
 * yang menuntutnya, dan auditor yang datang enam bulan kemudian membaca
 * kolom itu lebih dulu sebelum membaca temuannya. Butir tanpa acuan
 * tetap boleh ada — tidak semua hal baik diatur undang-undang — tetapi
 * yang punya acuan harus menyebutnya.
 *
 * ── Kenapa ada Inspeksi Kantor di aplikasi pertambangan ──
 *
 * Karena kecelakaan kerja tidak berhenti di pagar pit. Kantor site
 * punya panel listrik, jalur evakuasi, APAR, dan kotak P3K yang tunduk
 * pada aturan yang sama, dan justru di sanalah pemeriksaannya paling
 * sering terlewat — perhatian seluruh organisasi tertuju ke lapangan,
 * sementara satu-satunya ruangan yang ditempati orang delapan jam
 * sehari tanpa APD tidak pernah diperiksa siapa pun.
 *
 * ACUAN yang disebut di bawah:
 *   UU 1/1970                     Keselamatan Kerja
 *   Permenaker 5/2018             K3 Lingkungan Kerja
 *   Permenaker 12/2015            K3 Listrik di Tempat Kerja
 *   Permenaker 4/1980             Syarat pemasangan & pemeliharaan APAR
 *   Kepmenaker 186/1999           Unit Penanggulangan Kebakaran
 *   Permenakertrans 15/2008       P3K di Tempat Kerja
 *   Permenkes 48/2016             Standar K3 Perkantoran
 *   Kepmen ESDM 1827 K/2018       Kaidah teknik pertambangan yang baik
 */
final class MasterInspeksi
{
    /* Nama acuan ditulis sekali di sini lalu dirujuk, bukan diketik
       ulang pada tiap butir: satu salah ketik pada salah satu dari
       lima puluh baris menghasilkan "acuan" yang tidak pernah cocok
       ketika auditor mencarinya. */
    private const UU_KK      = 'UU No. 1 Tahun 1970';
    private const LINGKUNGAN = 'Permenaker No. 5 Tahun 2018';
    private const LISTRIK    = 'Permenaker No. 12 Tahun 2015';
    private const APAR       = 'Permenaker No. 4 Tahun 1980';
    private const KEBAKARAN  = 'Kepmenaker No. 186 Tahun 1999';
    private const P3K        = 'Permenakertrans No. 15 Tahun 2008';
    private const KANTOR     = 'Permenkes No. 48 Tahun 2016';
    private const MINERBA    = 'Kepmen ESDM 1827 K/30/MEM/2018';

    /**
     * Seluruh template baku beserta butirnya.
     *
     * Bentuk tiap butir: [kelompok, uraian, acuan|null, risiko, temuan?].
     *
     * Unsur kelima OPSIONAL: kalimat temuan yang khas bagi butir itu,
     * dipakai data contoh ketika butirnya ditandai tidak sesuai. Ia ada
     * karena lembar contoh yang seluruh temuannya berbunyi "tidak
     * memenuhi acuan saat diperiksa" tidak memperlihatkan apa pun
     * tentang gunanya kolom itu — dan itulah kolom yang paling menentukan
     * apakah lembarnya dapat ditindaklanjuti orang lain.
     *
     * URAIANNYA DITULIS SEBAGAI KEADAAN YANG BENAR, bukan sebagai
     * pertanyaan atau perintah. "APAR bertekanan cukup" dapat dijawab
     * Sesuai / Tidak Sesuai tanpa ragu; "Periksa APAR" tidak dapat —
     * yang mengisinya harus menebak apakah "Sesuai" berarti sudah
     * diperiksa atau hasilnya baik, dan dua pengawas akan menebak
     * berbeda.
     *
     * @var list<array{0:string,1:string,2:string,3:string,4:list<array{0:string,1:string,2:string|null,3:string}>}>
     */
    public const PUSTAKA = [

        /* ═════════ KANTOR ═════════ */
        [
            'Inspeksi K3 Perkantoran', 'Bulanan', 'Kantor',
            'Pemeriksaan bulanan kantor site: kelistrikan, proteksi kebakaran, '
            .'jalur evakuasi, ergonomi, P3K, sanitasi, dan kesiapsiagaan.',
            [
                ['Instalasi Listrik', 'Panel listrik tertutup, berlabel, dan bebas barang tersimpan di depannya', self::LISTRIK, 'Tinggi', 'Dua dus arsip tersimpan menutupi panel di koridor lantai 1.'],
                ['Instalasi Listrik', 'Tidak ada sambungan bertumpuk pada satu stop kontak', self::LISTRIK, 'Sedang', 'Satu stop kontak ruang kerja memikul empat colokan bertumpuk.'],
                ['Instalasi Listrik', 'Kabel tidak melintas jalur kaki dan tidak terjepit perabot', self::LINGKUNGAN, 'Sedang', 'Kabel jaringan melintas jalur kaki di depan meja resepsionis.'],
                ['Instalasi Listrik', 'Penerangan bidang kerja memadai untuk pekerjaan baca-tulis', self::LINGKUNGAN, 'Rendah', 'Dua lampu ruang arsip mati sehingga meja baca remang.'],

                ['Proteksi Kebakaran', 'APAR terpasang pada jarak jangkau dan tidak terhalang barang', self::APAR, 'Tinggi', 'APAR dekat pantry terhalang lemari dokumen.'],
                ['Proteksi Kebakaran', 'Tekanan APAR pada zona hijau, segel utuh, selang tidak retak', self::APAR, 'Tinggi', 'Satu APAR lantai 2 jarumnya di zona merah.'],
                ['Proteksi Kebakaran', 'Kartu pemeriksaan APAR terisi untuk bulan berjalan', self::APAR, 'Sedang', 'Kartu pemeriksaan tiga APAR kosong sejak dua bulan lalu.'],
                ['Proteksi Kebakaran', 'Detektor asap dan alarm berbunyi saat diuji', self::KEBAKARAN, 'Tinggi', 'Alarm sayap timur tidak berbunyi saat diuji.'],
                ['Proteksi Kebakaran', 'Regu penanggulangan kebakaran ditetapkan dan namanya terpasang', self::KEBAKARAN, 'Sedang', 'Susunan regu masih memuat dua nama yang sudah pindah tugas.'],

                ['Jalur Evakuasi', 'Jalur evakuasi bebas hambatan di seluruh panjangnya', self::KEBAKARAN, 'Tinggi', 'Kursi tidak terpakai menyempitkan jalur evakuasi lantai 2.'],
                ['Jalur Evakuasi', 'Pintu darurat dapat dibuka dari dalam tanpa kunci', self::KEBAKARAN, 'Tinggi', 'Pintu darurat sisi belakang tergembok dari dalam.'],
                ['Jalur Evakuasi', 'Rambu arah evakuasi terbaca dan tetap menyala saat listrik padam', self::KEBAKARAN, 'Tinggi', 'Dua rambu evakuasi tidak menyala saat daya diputus untuk uji.'],
                ['Jalur Evakuasi', 'Denah evakuasi terpasang di tiap lantai dan sesuai keadaan sekarang', self::KANTOR, 'Rendah', 'Denah lantai 2 masih menggambarkan tata ruang sebelum renovasi.'],
                ['Jalur Evakuasi', 'Titik kumpul ditandai dan cukup menampung seluruh penghuni', self::KANTOR, 'Sedang', 'Papan titik kumpul pudar dan tertutup parkir kendaraan.'],

                ['Ergonomi & Tata Ruang', 'Kursi kerja dapat disetel tinggi dan bersandaran punggung', self::KANTOR, 'Rendah', 'Empat kursi di ruang administrasi tuas penyetelnya rusak.'],
                ['Ergonomi & Tata Ruang', 'Layar monitor sejajar mata dan bebas pantulan silau', self::KANTOR, 'Rendah', 'Tiga meja menghadap jendela tanpa tirai sehingga layar menyilaukan.'],
                ['Ergonomi & Tata Ruang', 'Lemari dan rak tinggi diangkur ke dinding', self::LINGKUNGAN, 'Sedang', 'Dua lemari arsip setinggi dua meter belum diangkur.'],
                ['Ergonomi & Tata Ruang', 'Lantai rata, tidak licin, dan bebas kabel atau barang tergeletak', self::LINGKUNGAN, 'Sedang', 'Ubin pecah di depan ruang rapat berpotensi tersandung.'],

                ['P3K & Kesehatan Kerja', 'Kotak P3K tersedia sesuai jumlah pekerja dan mudah dijangkau', self::P3K, 'Tinggi', 'Hanya satu kotak P3K untuk dua lantai berpenghuni 48 orang.'],
                ['P3K & Kesehatan Kerja', 'Isi kotak P3K lengkap dan belum kedaluwarsa', self::P3K, 'Sedang', 'Kasa steril dan povidon iodin telah lewat tanggal kedaluwarsa.'],
                ['P3K & Kesehatan Kerja', 'Petugas P3K bersertifikat ada pada tiap jam kerja', self::P3K, 'Sedang', 'Tidak ada petugas P3K bersertifikat pada gilir sore.'],

                ['Kebersihan & Sanitasi', 'Toilet bersih, berair, dan terpisah menurut jenis kelamin', self::KANTOR, 'Sedang', 'Satu toilet lantai 1 tidak berair sejak tiga hari lalu.'],
                ['Kebersihan & Sanitasi', 'Air minum tersedia dan layak konsumsi', self::KANTOR, 'Sedang', 'Dispenser pantry belum diganti galonnya sejak pagi.'],
                ['Kebersihan & Sanitasi', 'Pengudaraan ruang kerja memadai dan tidak pengap', self::LINGKUNGAN, 'Rendah', 'Ruang arsip tanpa sirkulasi dan terasa pengap.'],
                ['Kebersihan & Sanitasi', 'Sampah dipilah dan diangkut secara berkala', self::KANTOR, 'Rendah', 'Sampah organik dan anorganik masih tercampur di satu bak.'],

                ['Kesiapsiagaan', 'Nomor telepon darurat terpasang di tempat yang terlihat', self::KANTOR, 'Rendah', 'Daftar nomor darurat masih memuat nomor klinik yang sudah berubah.'],
                ['Kesiapsiagaan', 'Latihan evakuasi terakhir terdokumentasi dan belum melewati jadwal', self::KEBAKARAN, 'Sedang', 'Latihan evakuasi terakhir 14 bulan lalu, melewati jadwal tahunan.'],
                ['Kesiapsiagaan', 'Tamu tercatat dan memakai tanda pengenal selama di area', null, 'Rendah', 'Dua tamu tercatat di buku tetapi tidak memakai tanda pengenal.'],
            ],
        ],

        /* ═════════ JALAN ANGKUT ═════════ */
        [
            'Inspeksi Harian Jalan Angkut', 'Harian', 'Jalan Tambang',
            'Pemeriksaan harian jalan hauling sebelum gilir pertama: badan jalan, '
            .'tanggul, drainase, rambu, dan pengendalian debu.',
            [
                ['Badan Jalan', 'Lebar jalan minimal tiga setengah kali lebar unit terbesar', self::MINERBA, 'Tinggi'],
                ['Badan Jalan', 'Permukaan jalan rata, tanpa lubang atau alur bekas roda yang dalam', self::MINERBA, 'Sedang'],
                ['Badan Jalan', 'Kemiringan tanjakan tidak melampaui rancangan jalan', self::MINERBA, 'Tinggi'],
                ['Tanggul Pengaman', 'Tanggul pengaman menerus setinggi setengah diameter ban unit terbesar', self::MINERBA, 'Tinggi'],
                ['Tanggul Pengaman', 'Tanggul tidak tergerus dan tidak terpotong jalan masuk liar', self::MINERBA, 'Tinggi'],
                ['Drainase', 'Saluran samping tidak tersumbat dan air mengalir keluar badan jalan', null, 'Sedang'],
                ['Drainase', 'Tidak ada genangan pada badan jalan setelah hujan', null, 'Sedang'],
                ['Rambu & Penerangan', 'Rambu batas kecepatan dan peringatan terbaca dari jarak aman', self::MINERBA, 'Rendah'],
                ['Rambu & Penerangan', 'Penerangan simpang dan area timbang menyala pada gilir malam', self::LINGKUNGAN, 'Sedang'],
                ['Pengendalian Debu', 'Penyiraman jalan berjalan sesuai jadwal dan jarak pandang memadai', null, 'Sedang'],
            ],
        ],

        /* ═════════ ALAT BERAT ═════════ */
        [
            'Inspeksi Mingguan Alat Berat', 'Mingguan', 'Peralatan',
            'Pemeriksaan mingguan unit produksi: sistem keselamatan, rem, ban, '
            .'kebocoran, alat pemadam, dan kelengkapan kabin.',
            [
                ['Sistem Keselamatan', 'Sabuk pengaman terpasang, tidak sobek, dan penguncinya berfungsi', self::UU_KK, 'Tinggi'],
                ['Sistem Keselamatan', 'Alarm mundur berbunyi dan terdengar di atas bising sekitar', self::MINERBA, 'Tinggi'],
                ['Sistem Keselamatan', 'Lampu utama, lampu rem, dan lampu putar berfungsi', self::MINERBA, 'Tinggi'],
                ['Sistem Keselamatan', 'Struktur pelindung guling (ROPS/FOPS) utuh tanpa retak atau las ulang', self::MINERBA, 'Tinggi'],
                ['Rem & Kemudi', 'Rem utama dan rem parkir menahan unit bermuatan pada tanjakan uji', self::MINERBA, 'Tinggi'],
                ['Rem & Kemudi', 'Kemudi tidak bermain berlebihan dan kembali sendiri', null, 'Sedang'],
                ['Ban & Undercarriage', 'Tekanan dan kondisi ban sesuai, tanpa sobek sampai benang', null, 'Sedang'],
                ['Ban & Undercarriage', 'Baut roda lengkap dan kencang', null, 'Tinggi'],
                ['Kebocoran & Pelumasan', 'Tidak ada kebocoran oli, bahan bakar, atau hidrolik', null, 'Sedang'],
                ['Kebocoran & Pelumasan', 'Tinggi muka oli mesin, hidrolik, dan pendingin pada batas aman', null, 'Rendah'],
                ['Kelengkapan Kabin', 'APAR di unit bertekanan cukup dan mudah dijangkau dari kursi', self::APAR, 'Tinggi'],
                ['Kelengkapan Kabin', 'Kaca dan spion bersih, utuh, dan disetel benar', null, 'Sedang'],
                ['Kelengkapan Kabin', 'Ganjal roda tersedia di unit', null, 'Sedang'],
            ],
        ],

        /* ═════════ GUDANG BAHAN PELEDAK ═════════ */
        [
            'Inspeksi Bulanan Gudang Bahan Peledak', 'Bulanan', 'Gudang Handak',
            'Pemeriksaan bulanan gudang handak: keamanan, tata simpan, administrasi, '
            .'proteksi kebakaran, dan penyalur petir.',
            [
                ['Keamanan', 'Pagar, gembok, dan penerangan keliling berfungsi', self::MINERBA, 'Tinggi'],
                ['Keamanan', 'Penjagaan berjalan dan buku tamu terisi', self::MINERBA, 'Sedang'],
                ['Tata Simpan', 'Detonator dan bahan peledak tersimpan terpisah sesuai jarak aman', self::MINERBA, 'Tinggi'],
                ['Tata Simpan', 'Tumpukan tidak melebihi tinggi maksimum dan tidak menyentuh dinding', self::MINERBA, 'Sedang'],
                ['Administrasi', 'Kartu persediaan cocok dengan hitungan fisik', self::MINERBA, 'Tinggi'],
                ['Administrasi', 'Izin gudang masih berlaku dan terpasang', self::MINERBA, 'Tinggi'],
                ['Proteksi Kebakaran', 'APAR bertekanan cukup dan belum kedaluwarsa', self::APAR, 'Tinggi'],
                ['Proteksi Kebakaran', 'Area sekeliling gudang bebas rumput kering dan bahan mudah terbakar', null, 'Sedang'],
                ['Penyalur Petir', 'Hasil uji tahanan pembumian penyalur petir masih berlaku', self::MINERBA, 'Tinggi'],
            ],
        ],
    ];

    /** Jumlah butir seluruh pustaka — dipakai uji dan ringkasan pemasangan. */
    public static function jumlahButir(): int
    {
        return array_sum(array_map(fn (array $t) => count($t[4]), self::PUSTAKA));
    }

    /**
     * Peta uraian butir => kalimat temuan khasnya.
     *
     * Dicari menurut URAIAN, bukan menurut nomor urut: butir yang
     * disisipkan di tengah pustaka menggeser seluruh nomor sesudahnya,
     * dan temuan yang tergeser menempel pada butir yang salah — lembar
     * yang tetap terbaca masuk akal sehingga keliruannya tidak
     * ketahuan.
     *
     * @return array<string,string>
     */
    public static function temuanKhas(): array
    {
        $peta = [];

        foreach (self::PUSTAKA as $t) {
            foreach ($t[4] as $b) {
                if (isset($b[4]) && $b[4] !== '') $peta[$b[1]] = $b[4];
            }
        }

        return $peta;
    }

    /** Satu template menurut namanya, atau null bila tidak ada. */
    public static function menurutNama(string $nama): ?array
    {
        foreach (self::PUSTAKA as $t) {
            if ($t[0] === $nama) return $t;
        }

        return null;
    }
}
