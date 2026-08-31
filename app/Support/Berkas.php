<?php

namespace App\Support;

use App\Models\Investigasi\Bukti as BuktiInvestigasi;
use App\Models\{Document, GudangBarang, HazardReport, InspectionItem, PasporKartu, PasporKartuUnit, PasporMcu, PasporSertifikat, Signatory, SmkpFinding};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Tempat berkas unggahan disimpan, dan siapa yang boleh membacanya.
 *
 * Sebelum berkas ini ada, SEMBILAN tempat unggahan memakai disk `public`
 * tanpa dibeda-bedakan, dan `storage:link` membuat seluruhnya terbaca
 * lewat eqohsee.id/storage/… tanpa login sama sekali. Yang ikut terbuka
 * di situ bukan berkas sepele: dokumen terkendali, foto insiden, lembar
 * MSDS, dan GAMBAR TANDA TANGAN para penanggung jawab — gambar yang
 * dicetak pada tiap sertifikat dan tiap lembar laporan sebagai bukti
 * persetujuan.
 *
 * Yang membuatnya sulit terlihat: pintu depannya sudah dijaga. Dokumen
 * punya `DocumentController::unduh` yang memeriksa hak akses, dan
 * pemeriksaan itu benar. Hanya saja berkasnya SEKALIGUS tergeletak di
 * jalur statis yang dilayani Nginx sendiri, sehingga penjagaannya dapat
 * dilewati tanpa perlu menembusnya — cukup dengan tidak melewatinya.
 *
 * Pembedaannya bukan soal berapa rahasia isinya melainkan soal siapa
 * yang harus dapat melihatnya tanpa masuk:
 *
 *   TERBUKA    logo perusahaan, sampul kursus, foto profil. Tampil di
 *              halaman pendaratan, di kop surat, di lembar cetak yang
 *              dibuka peramban tanpa sesi. Menjaganya berarti merusak
 *              halaman yang memang dimaksudkan terbuka.
 *
 *   TERTUTUP   dokumen, tanda tangan, foto bahaya, foto inspeksi, MSDS.
 *              Seluruhnya hanya tampil di halaman yang menuntut login,
 *              jadi menutupnya tidak menghilangkan apa pun.
 *
 * Yang tertutup disajikan lewat satu rute yang mengikat MODELNYA, bukan
 * jalurnya. Itu penting: nama berkas buatan Laravel memang acak dan
 * praktis tak tertebak, tetapi tak tertebak bukan terlarang. Dengan
 * mengikat modelnya, batas perusahaan ditegakkan oleh scope yang sudah
 * dipakai di seluruh aplikasi — bukan oleh panjangnya nama berkas.
 */
final class Berkas
{
    /** Disk untuk berkas yang harus dapat dibaca tanpa login. */
    public const TERBUKA = 'public';

    /** Disk untuk berkas yang hanya boleh dibaca lewat rute berjaga. */
    public const TERTUTUP = 'local';

    /**
     * jenis => [kelas model, atribut, berupa daftar?]
     *
     * Kuncinya muncul di URL, jadi ia sengaja pendek dan tidak menyebut
     * nama tabel: alamat berkas tidak perlu memberi tahu siapa pun
     * bagaimana basis datanya disusun.
     */
    public const TERSAJI = [
        'dok' => [Document::class,       'berkas',    false],
        'ttd' => [Signatory::class,      'signature', false],
        'hzd' => [HazardReport::class,   'foto',              true],
        'hzt' => [HazardReport::class,   'foto_tindaklanjut', true],
        'ins' => [InspectionItem::class, 'foto',      true],
        'sds' => [GudangBarang::class,   'msds',      false],

        /* Bukti temuan SMKP punya DUA jalur pada satu baris — sebelum
           dan sesudah perbaikan — jadi keduanya terdaftar terpisah.
           Menyatukannya sebagai daftar akan menyembunyikan bahwa
           urutannya bermakna: kotak kiri selalu "sebelum". */
        'smo' => [SmkpFinding::class,    'foto_open',   false],
        'smc' => [SmkpFinding::class,    'foto_closed', false],

        /* Surat MCU. Terjaga lebih ketat daripada yang lain — lihat
           GERBANG di bawah. */
        'mcu' => [PasporMcu::class,      'berkas',      false],

        /* Surat rujukan medis. Terjaga sama ketatnya dengan surat MCU —
           ia menyebut ke poli mana orangnya dirujuk, dan itu rincian
           medis. */
        'mcr' => [PasporMcu::class,      'berkas_rujukan', false],
    ];

    /**
     * Seluruh jenis yang dapat disajikan, termasuk lampiran kartu.
     *
     * Lampiran kartu TIDAK ditulis satu per satu di TERSAJI, melainkan
     * dibangkitkan dari App\Support\LampiranMiners. Daftar lampiran
     * sudah pernah berselisih antara tiga tempat — aturan validasi,
     * medan di layar, dan kolom di basis data — dan `berkas_lotto`
     * sempat ada di dua yang pertama tanpa punya medan sama sekali.
     * Menyalinnya ke tempat KEEMPAT hanya menambah peluang selisih.
     *
     * @return array<string,array{0:class-string,1:string,2:bool}>
     */
    public static function tersaji(): array
    {
        $out = self::TERSAJI;

        foreach (array_keys(LampiranMiners::KARTU) as $kolom) {
            $out[self::jenisLampiran($kolom)] = [PasporKartu::class, $kolom, false];
        }

        /* Berkas uji per unit SIMPER melekat pada barisnya sendiri,
           bukan pada kartunya — satu kartu dapat menyebut lima unit,
           masing-masing dengan lembar rambu, teori, dan praktiknya
           sendiri. Batas perusahaannya dijaga BerindukPerusahaan pada
           PasporKartuUnit, yang menyaring lewat kartunya. */
        foreach (array_keys(LampiranMiners::UNIT) as $kolom) {
            $out[LampiranMiners::jenisUnit($kolom)] = [PasporKartuUnit::class, $kolom, false];
        }

        /* Berkas sertifikat kompetensi — satu berkas per sertifikat,
           jadi satu jenis saja dan tidak perlu dibangkitkan berulang. */
        $out['srt'] = [PasporSertifikat::class, 'berkas', false];

        /* Bukti investigasi. Batas perusahaannya dijaga
           BerindukPerusahaan pada Bukti, yang menyaring lewat
           investigasi lalu insidennya. */
        $out['evd'] = [BuktiInvestigasi::class, 'berkas', false];

        return $out;
    }

    /**
     * Kode jenis bagi satu kolom lampiran kartu.
     *
     * Berawalan `kar-` supaya tidak pernah bertabrakan dengan jenis
     * yang ditulis tangan di TERSAJI, dan supaya terbaca asalnya saat
     * muncul di alamat.
     */
    public static function jenisLampiran(string $kolom): string
    {
        return 'kar-'.str_replace('berkas_', '', $kolom);
    }

    /**
     * Siapa yang boleh MEMBUKA berkas satu jenis, di luar batas perusahaan.
     *
     * Kosong berarti siapa pun yang dapat melihat barisnya dapat
     * membuka berkasnya, dan bagi hampir semua jenis itu benar: foto
     * bahaya, foto inspeksi, dan tanda tangan memang dimaksudkan
     * terlihat oleh yang membuka halamannya.
     *
     * SURAT MCU TIDAK. Yang disimpan `paspor_mcu` sengaja hanya
     * KESIMPULAN kelayakan kerjanya — "Fit", "Fit With Note" — sebab
     * rincian medis punya aturan kerahasiaannya sendiri dan tidak boleh
     * terbaca oleh setiap admin HSE yang membuka daftar pekerja. Surat
     * dari klinik justru memuat rincian itu: diagnosis, hasil
     * laboratorium, riwayat. Mengunggahnya tanpa penjagaan berarti
     * membatalkan prinsip itu lewat pintu belakang — kolomnya bersih,
     * lampirannya yang membocorkan.
     *
     * Karena itu suratnya hanya terbuka bagi yang memang membacanya
     * sebagai bagian pekerjaannya: paramedis, tim OHSE, dan
     * administrator.
     *
     * @var array<string,list<string>> jenis => nama helper peran pada User
     */
    public const GERBANG = [
        'mcu' => ['isAdmin', 'isOhse', 'isParamedis'],
        'mcr' => ['isAdmin', 'isOhse', 'isParamedis'],
    ];

    /** Pengguna ini boleh membuka berkas jenis itu? */
    public static function bolehMembuka(?object $u, string $jenis): bool
    {
        $peran = self::GERBANG[$jenis] ?? null;

        if ($peran === null) return true;      // jenis yang memang tidak dijaga
        if (!$u) return false;

        foreach ($peran as $cek) {
            if (method_exists($u, $cek) && $u->{$cek}()) return true;
        }

        return false;
    }

    /** Folder tempat tiap jenis disimpan, dipakai pemindah berkas lama. */
    public const FOLDER_TERTUTUP = ['dokumen', 'signatures', 'hazard', 'inspeksi', 'gudang/msds'];

    /**
     * Aturan bagi unggahan gambar.
     *
     * SVG sengaja TIDAK ada di sini, dan ketiadaannya bukan soal selera
     * format. SVG adalah XML yang boleh memuat <script>, dan peramban
     * menjalankannya ketika berkasnya dibuka langsung sebagai alamat.
     * Karena ia disajikan dari domain yang sama dengan aplikasinya,
     * skrip itu berjalan DI DALAM asal yang sama — dengan akses ke
     * kuki sesi siapa pun yang membukanya. Logo perusahaan adalah
     * tempat paling nyaman untuk menaruhnya: ia dipasang sekali oleh
     * satu orang, lalu tergambar di halaman semua orang.
     */
    public const ATURAN_GAMBAR = ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];

    /**
     * Jenis berkas yang boleh diunggah sebagai dokumen.
     *
     * Sebelumnya aturannya hanya `file` dengan batas 20 MB — tanpa satu
     * pun batasan jenis. Berkas .php yang terunggah ke disk publik
     * berada di bawah akar web lewat storage:link, dan blok `location ~
     * \.php$` pada Nginx tidak membedakan berkas aplikasi dari berkas
     * unggahan: ia menjalankan keduanya.
     */
    public const ATURAN_DOKUMEN = [
        'file',
        'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,jpg,jpeg,png,webp,zip',
        'max:20480',
    ];

    /**
     * Akhiran yang tidak pernah boleh tersimpan, apa pun aturannya.
     *
     * Lapis terakhir, bukan lapis pertama. Yang menjaga sungguh-sungguh
     * adalah aturan validasi di atas dan penolakan Nginx menjalankan apa
     * pun di bawah /storage/. Tetapi keduanya dipasang per tempat
     * unggahan dan per berkas konfigurasi, sedangkan tempat unggahan
     * berikutnya akan ditulis oleh orang yang tidak membaca keduanya.
     */
    private const AKHIRAN_TERLARANG = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phps', 'phar',
        'htaccess', 'htpasswd', 'cgi', 'pl', 'py', 'sh', 'exe', 'dll',
    ];

    /**
     * Simpan unggahan tertutup dan kembalikan jalurnya.
     *
     * Mengembalikan null bila tidak ada berkas atau berkasnya cacat,
     * supaya pemanggilnya tidak perlu memeriksa dua hal berbeda.
     */
    public static function simpan(?UploadedFile $f, string $folder): ?string
    {
        if (!$f || !$f->isValid()) return null;

        $akhiran = strtolower($f->getClientOriginalExtension());
        if (in_array($akhiran, self::AKHIRAN_TERLARANG, true)) return null;

        return $f->store($folder, self::TERTUTUP);
    }

    /** Simpan beberapa unggahan tertutup sekaligus. */
    public static function simpanBanyak(mixed $berkas, string $folder): array
    {
        $out = [];
        foreach ((array) $berkas as $f) {
            if ($j = self::simpan($f instanceof UploadedFile ? $f : null, $folder)) $out[] = $j;
        }
        return $out;
    }

    /** Buang berkas tertutup bila ada. Aman dipanggil dengan null. */
    public static function buang(?string $jalur): void
    {
        if ($jalur) Storage::disk(self::TERTUTUP)->delete($jalur);
    }

    /**
     * Alamat untuk menampilkan berkas tertutup milik satu baris.
     *
     * `$indeks` dipakai bagi atribut yang berupa daftar foto. Null bagi
     * yang tunggal.
     */
    public static function url(?object $baris, string $jenis, ?int $indeks = null): ?string
    {
        if (!$baris) return null;

        [, $atribut, $daftar] = self::tersaji()[$jenis]
            ?? throw new \InvalidArgumentException("Jenis berkas tidak dikenal: $jenis");

        $nilai = $baris->{$atribut};

        if ($daftar) {
            $nilai = (array) $nilai;
            if (!isset($nilai[$indeks ?? 0])) return null;
        } elseif (!$nilai) {
            return null;
        }

        return route('berkas.sajikan', array_filter([
            'jenis' => $jenis,
            'baris' => $baris->getKey(),
            'i'     => $daftar ? ($indeks ?? 0) : null,
        ], fn ($v) => $v !== null));
    }

    /** Seluruh alamat foto satu baris, bagi atribut yang berupa daftar. */
    public static function daftarUrl(?object $baris, string $jenis): array
    {
        if (!$baris) return [];

        [, $atribut] = self::tersaji()[$jenis];

        return array_values(array_filter(array_map(
            fn ($i) => self::url($baris, $jenis, $i),
            array_keys((array) ($baris->{$atribut} ?: [])),
        )));
    }

    /**
     * Alamat berkas TERBUKA — logo, sampul, foto profil.
     *
     * Dibungkus di sini bukan demi kerapian melainkan supaya seluruh
     * pemakaian disk publik terkumpul di satu berkas. Selama
     * `asset('storage/'.$x)` tersebar di dua puluh lima tempat, tempat
     * ke dua puluh enam akan ditulis dengan menyalin salah satunya —
     * dan yang tersalin bisa jadi yang seharusnya tertutup.
     */
    public static function terbuka(?string $jalur): ?string
    {
        return $jalur ? asset('storage/'.ltrim($jalur, '/')) : null;
    }
}
