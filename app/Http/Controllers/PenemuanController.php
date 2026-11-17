<?php

namespace App\Http\Controllers;

use App\Support\{Menu, Seo};
use Illuminate\Http\Response;

/**
 * robots.txt dan sitemap.xml.
 *
 * Keduanya dibangkitkan, bukan ditulis tangan, dan alasannya sama untuk
 * keduanya: daftar yang ditulis tangan menua diam-diam. Modul baru
 * ditambahkan, alamatnya tidak pernah masuk ke robots.txt, dan tidak ada
 * yang memberi tahu siapa pun — sampai isi halaman yang seharusnya di
 * balik login muncul di hasil pencarian.
 *
 * Sumbernya Menu::PETA_ALAMAT, tabel yang sudah dipakai menentukan modul
 * mana yang sedang aktif di bilah samping. Menambahkan modul berarti
 * menambah satu baris di sana, dan baris itu kini sekaligus menutup
 * alamatnya dari perayap.
 */
class PenemuanController extends Controller
{
    public function robots(): Response
    {
        $baris = [
            '# EQOHSEE — '.config('app.url'),
            '#',
            '# Alamat modul dibangkitkan dari Menu::PETA_ALAMAT, sumber yang',
            '# sama dengan bilah samping. Modul baru tertutup dengan',
            '# sendirinya, tanpa perlu diingat siapa pun.',
            '',
            'User-agent: *',
        ];

        /* Berkas gambar, gaya, dan skrip TIDAK ditutup. Google menggambar
           halaman sebelum menilainya; halaman yang tidak dapat mengambil
           CSS-nya dinilai sebagai halaman yang rusak. */
        foreach (['/build/', '/media/', '/brand/', '/storage/'] as $boleh) {
            $baris[] = 'Allow: '.$boleh;
        }

        $baris[] = '';

        foreach (self::jalurModul() as $jalur) {
            $baris[] = 'Disallow: '.$jalur;
        }

        /* /verifikasi/ SENGAJA tidak ditutup di sini, meski halamannya
           menyebut nama orang.

           Menutupnya di robots.txt justru MELEMAHKAN perlindungannya:
           perayap yang dilarang mengambil halaman tidak akan pernah
           melihat <meta name="robots" content="noindex"> di dalamnya, dan
           alamat yang tidak dapat dibaca tetap boleh muncul di hasil
           pencarian sebagai alamat telanjang bila ada yang menautkannya.
           Dibiarkan terbaca, noindex-nya terbaca pula, dan halamannya
           dikeluarkan sepenuhnya. */

        $baris[] = '';
        $baris[] = 'Sitemap: '.rtrim((string) config('app.url'), '/').'/sitemap.xml';

        return response(implode("\n", $baris)."\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function sitemap(): Response
    {
        $dasar = rtrim((string) config('app.url'), '/');

        /* Hanya halaman yang memang boleh diindeks. Sitemap yang memuat
           alamat ber-noindex mengirim dua pesan yang saling bertentangan
           ke mesin pencari, dan yang dilaporkannya kembali adalah
           peringatan — bukan halaman yang terindeks. */
        $alamat = [];
        foreach (Seo::rutePublik() as $rute) {
            $alamat[] = $dasar.'/'.ltrim(str_replace($dasar, '', route($rute, [], false)), '/');
        }

        $isi  = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $isi .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach (array_unique($alamat) as $a) {
            $isi .= '  <url><loc>'.e(rtrim($a, '/') ?: $dasar).'</loc>'
                   .'<changefreq>weekly</changefreq></url>'."\n";
        }

        $isi .= '</urlset>'."\n";

        return response($isi, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * Awalan alamat tiap modul, dari tabel yang sama dengan bilah samping.
     *
     * @return list<string>
     */
    private static function jalurModul(): array
    {
        $jalur = [];

        foreach (Menu::petaAlamat() as [$pola,]) {
            foreach ($pola as $p) {
                /* 'personalia*' menjadi '/personalia'. Awalan sudah cukup:
                   robots.txt mencocokkan awalan, bukan pola. */
                $bersih = '/'.trim(rtrim($p, '*'), '/');
                if ($bersih !== '/' && !in_array($bersih, $jalur, true)) $jalur[] = $bersih;
            }
        }

        /* Alamat di luar tabel modul yang juga tidak berguna dirayapi. */
        foreach (['/dashboard', '/profile', '/akun', '/temuan', '/berkas', '/pesan',
                  '/courses', '/certificates', '/learn', '/kuis', '/evaluasi', '/bantuan'] as $lain) {
            if (!in_array($lain, $jalur, true)) $jalur[] = $lain;
        }

        sort($jalur);

        return $jalur;
    }
}
