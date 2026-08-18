<?php

namespace App\Support;

use App\Models\{Document, GudangBarang, HazardReport, InspectionItem, Signatory, SmkpFinding};
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
    ];

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

        [, $atribut, $daftar] = self::TERSAJI[$jenis]
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

        [, $atribut] = self::TERSAJI[$jenis];

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
