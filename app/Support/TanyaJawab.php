<?php

namespace App\Support;

/**
 * Enam pertanyaan yang memang ditanyakan sebelum membeli.
 *
 * ── SATU DAFTAR, DIBACA DUA KALI ──
 *
 * Daftar ini digambar sebagai akordeon di halaman depan DAN diterbitkan
 * sebagai data terstruktur FAQPage di kepala halamannya. Keduanya wajib
 * berisi hal yang sama persis — dan bukan sekadar demi kerapian:
 *
 * Google menjatuhkan sanksi manual atas data terstruktur yang tidak
 * sesuai dengan isi yang terlihat. Disalin ke dua tempat, cepat atau
 * lambat satu jawaban diperbarui dan satunya tidak; yang tertinggal
 * bukan sekadar usang, melainkan menjadi pernyataan kepada mesin
 * pencari tentang halaman yang tidak pernah berkata begitu.
 *
 * Karena itu daftarnya tinggal di sini, dan keduanya membacanya.
 *
 * ── JAWABAN SINYAL SENGAJA MENGAKU ──
 *
 * EQOHSEE menuntut koneksi; tidak ada penyimpanan luring maupun antrean
 * sinkronisasi di dalamnya. Menuliskannya sebagai "siap dipakai tanpa
 * sinyal" akan menjadi janji yang runtuh pada hari pertama pemakaian di
 * site — pada produk keselamatan, tepat saat orang paling bergantung
 * padanya.
 */
class TanyaJawab
{
    /**
     * @return array<int, array{t: string, j: string}>
     */
    public static function semua(): array
    {
        return [
            ['t' => 'Apakah seluruh modulnya harus diambil sekaligus?',
             'j' => 'Tidak. Modulnya dapat dibeli satuan dan dinyalakan bertahap — modul yang '
                  . 'ditambahkan kemudian tetap berbagi data perusahaan, pengguna, dan peran '
                  . 'yang sama, jadi tidak ada data yang perlu dipindahkan.'],
            ['t' => 'Apakah EQOHSEE menjamin perusahaan lulus audit SMKP?',
             'j' => 'Tidak, dan tidak ada perangkat lunak yang dapat menjaminnya. EQOHSEE '
                  . 'adalah alat bantu menyusun dan menyimpan bukti penerapan SMKP. Kewajiban '
                  . 'hukum serta hasil penilaiannya tetap berada pada perusahaan dan KTT.'],
            ['t' => 'Bagaimana kalau site tidak ada sinyal?',
             'j' => 'EQOHSEE berjalan di peramban dan menuntut koneksi saat data dikirim; '
                  . 'belum ada perekaman luring. Halamannya dibuat ringan agar tetap terbuka '
                  . 'pada jaringan site yang lambat, tetapi pengisian di titik tanpa sinyal '
                  . 'sama sekali masih perlu diulang ketika kembali terhubung.'],
            ['t' => 'Apakah perlu dipasang di server sendiri?',
             'j' => 'Tidak. Cukup peramban — dari kantor pusat maupun dari site. Pemasangan '
                  . 'di server sendiri dapat dibicarakan terpisah bila kebijakan TI menuntutnya.'],
            ['t' => 'Bagaimana data antar-perusahaan dipisahkan?',
             'j' => 'Setiap catatan terikat pada perusahaan pemiliknya dan disaring di server '
                  . 'pada setiap permintaan, bukan disembunyikan di tampilan. Peran pengguna '
                  . 'menentukan lebih lanjut apa yang boleh dibuka dan diubah.'],
            ['t' => 'Apakah harganya terbuka?',
             'j' => 'Ya. Harga paket menyeluruh dan harga tiap aplikasi satuan tercantum di '
                  . 'katalog, lengkap dengan masa berlakunya. Pemesanannya tidak menuntut akun '
                  . 'dan pembayarannya lewat QRIS.'],
        ];
    }

    /**
     * Daftar yang sama, dalam bentuk schema.org FAQPage.
     *
     * Tidak ada teks yang ditulis ulang di sini — hanya bentuknya yang
     * berubah, sehingga tidak ada cara bagi keduanya untuk berbeda.
     */
    public static function dataTerstruktur(): array
    {
        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => array_map(fn (array $x): array => [
                '@type' => 'Question',
                'name'  => $x['t'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => $x['j'],
                ],
            ], self::semua()),
        ];
    }
}
