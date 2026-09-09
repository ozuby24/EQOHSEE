<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Judul, uraian, dan tautan berbagi — digambar SERVER, bukan peramban.
 *
 * Aplikasi ini memakai Inertia tanpa SSR, jadi seluruh <title> dipasang
 * oleh JavaScript sesudah halaman tiba. Bagi orang, itu tidak terasa
 * sama sekali. Bagi apa pun yang TIDAK menjalankan JavaScript, halaman
 * ini datang tanpa judul, tanpa uraian, tanpa gambar:
 *
 *   — Pratinjau tautan di WhatsApp, LinkedIn, dan Telegram. Tak satu pun
 *     menjalankan JavaScript. Selama ini setiap orang yang membagikan
 *     eqohsee.id membagikan sebuah alamat telanjang: tanpa nama, tanpa
 *     keterangan, tanpa gambar. Bagi produk yang menyebar dari mulut ke
 *     mulut antar-perusahaan, di situlah kesan pertamanya dibentuk —
 *     dan selama ini kesan itu kosong.
 *
 *   — Mesin pencari. Google memang menjalankan JavaScript, tetapi tidak
 *     seketika dan tidak selalu; judul yang datang belakangan bersaing
 *     dengan judul kosong yang sudah lebih dulu tercatat.
 *
 * Kegagalannya sunyi sempurna: halamannya 200, di layar judulnya benar,
 * dan satu-satunya cara melihatnya adalah membuka sumber halaman sebelum
 * JavaScript berjalan — atau membagikan tautannya lalu memperhatikan apa
 * yang muncul.
 *
 * Judul dari <Head> di sisi Vue TETAP dipakai: sesudah halaman hidup, ia
 * menimpa yang dipasang di sini, dan itu memang yang diinginkan — judul
 * per halaman yang berubah saat berpindah tanpa memuat ulang. Yang
 * dipasang di sini adalah judul yang berlaku SEBELUM itu.
 */
final class Seo
{
    public const NAMA = 'EQOHSEE';

    /**
     * Halaman yang boleh diindeks, beserta judul dan uraiannya.
     *
     * Daftar putih, bukan daftar hitam. Yang tidak tersebut di sini
     * TIDAK diindeks — dan itu keputusan yang disengaja: aplikasi ini
     * punya lebih dari dua ratus alamat, hampir seluruhnya di balik
     * login. Daftar hitam menuntut seseorang mengingat menutup setiap
     * alamat baru, dan alamat yang terlupa tidak menimbulkan gejala apa
     * pun sampai isinya muncul di hasil pencarian.
     *
     * nama rute => [judul, uraian]
     */
    private const PUBLIK = [
        'beranda' => [
            'Platform Terpadu Keselamatan Pertambangan',
            'Satu platform untuk pembelajaran K3, penilaian kinerja keselamatan, '
            .'audit SMKP, prosedur, dan sertifikasi di industri pertambangan Indonesia.',
        ],
        /* Etalase jual. Halaman yang paling ingin ditemukan orang yang
           belum kenal EQOHSEE — dan satu-satunya yang menyebut harga,
           yang memang dicari orang lebih dulu daripada daftar fitur. */
        'katalog.publik' => [
            'Harga dan Paket',
            'Beli platform EQOHSEE sebagai paket menyeluruh atau aplikasi satuan: '
            .'LMS, kelayakan kerja, hazard report, investigasi insiden, SMKP, hingga '
            .'kendali biaya. Pemesanan tanpa akun, pembayaran QRIS.',
        ],
        'login' => [
            'Masuk',
            'Masuk ke akun EQOHSEE untuk mengelola keselamatan, kesehatan kerja, '
            .'dan lingkungan di site Anda.',
        ],
        'register' => [
            'Daftar',
            'Buat akun EQOHSEE untuk perusahaan tambang Anda.',
        ],
        'password.request' => [
            'Lupa Sandi',
            'Setel ulang sandi akun EQOHSEE Anda.',
        ],
    ];

    /**
     * Halaman yang terbuka tanpa login TETAPI tidak boleh diindeks.
     *
     * Verifikasi sertifikat sengaja dapat dibuka siapa pun — itulah
     * gunanya: pemberi kerja memeriksa keaslian sertifikat tanpa perlu
     * akun. Tetapi halamannya menyebut NAMA ORANG beserta nomor
     * sertifikat dan nama perusahaannya, dan alamat itu berbentuk kode
     * yang dapat ditelusuri. Dibiarkan terindeks, ia menjadi direktori
     * karyawan yang dapat dicari — akibat yang tidak pernah dimaksudkan
     * oleh siapa pun yang membuat fitur pemeriksaan keaslian.
     */
    private const TERBUKA_TAK_TERINDEKS = ['certificates.verify', 'kuesioner.isi', 'kuesioner.kirim'];

    /**
     * Nama rute yang boleh diindeks — dipakai membangun sitemap.
     *
     * @return list<string>
     */
    public static function rutePublik(): array
    {
        return array_keys(self::PUBLIK);
    }

    /**
     * Susun seluruh keterangan untuk satu permintaan.
     *
     * @return array{judul:string, uraian:string, kanonik:string, gambar:string, indeks:bool}
     */
    public static function untuk(Request $r): array
    {
        $rute = $r->route()?->getName();

        [$judul, $uraian] = self::PUBLIK[$rute] ?? [null, null];

        $indeks = isset(self::PUBLIK[$rute]);

        return [
            'judul'   => $judul === null
                ? self::NAMA
                : ($rute === 'beranda' ? self::NAMA.' — '.$judul : $judul.' — '.self::NAMA),
            'uraian'  => $uraian ?? self::PUBLIK['beranda'][1],
            'kanonik' => self::kanonik($r),
            'gambar'  => Aset::v('brand/og-eqohsee.jpg'),
            'indeks'  => $indeks,
        ];
    }

    /**
     * Alamat kanonik: tanpa kueri, dan selalu dari APP_URL.
     *
     * Tanpa keduanya, satu halaman yang sama dapat tercatat sebagai
     * beberapa alamat berbeda — dengan www dan tanpa www, http dan
     * https, dengan dan tanpa parameter penelusuran kampanye. Mesin
     * pencari membagi nilai satu halaman itu ke antara salinan-salinannya,
     * dan tak satu pun dari salinan itu sekuat halaman aslinya.
     */
    private static function kanonik(Request $r): string
    {
        $dasar = rtrim((string) config('app.url'), '/');
        $jalur = '/'.ltrim($r->path() === '/' ? '' : $r->path(), '/');

        return $dasar.rtrim($jalur, '/') ?: $dasar;
    }

    /**
     * Data terstruktur untuk halaman pendaratan.
     *
     * Yang dinyatakan di sini hanya yang BENAR dan dapat diperiksa:
     * nama, alamat, keterangan, dan logo. Sengaja tanpa aggregateRating,
     * tanpa jumlah pengguna, tanpa harga.
     *
     * Bukan karena kehati-hatian berlebihan, melainkan karena penanda
     * semacam itu harus sesuai dengan yang benar-benar tampak di halaman.
     * Yang tidak sesuai bukan sekadar diabaikan — Google memberi sanksi
     * manual atas data terstruktur yang menyesatkan, dan sanksi itu jauh
     * lebih mahal daripada bintang yang tidak pernah dipasang.
     */
    public static function dataTerstruktur(): string
    {
        $dasar = rtrim((string) config('app.url'), '/');

        return json_encode([
            '@context'    => 'https://schema.org',
            '@type'       => 'SoftwareApplication',
            'name'        => self::NAMA,
            'applicationCategory' => 'BusinessApplication',
            'operatingSystem'     => 'Web',
            'url'         => $dasar,
            'description' => self::PUBLIK['beranda'][1],
            'inLanguage'  => 'id-ID',
            'image'       => $dasar.'/brand/og-eqohsee.jpg',
            'publisher'   => [
                '@type' => 'Organization',
                'name'  => self::NAMA,
                'url'   => $dasar,
                'logo'  => [
                    '@type' => 'ImageObject',
                    'url'   => $dasar.'/brand/eqohsee-mark.png',
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** Apakah alamat ini terbuka tanpa login tetapi tidak boleh diindeks. */
    public static function terbukaTanpaIndeks(?string $rute): bool
    {
        return in_array($rute, self::TERBUKA_TAK_TERINDEKS, true);
    }
}
