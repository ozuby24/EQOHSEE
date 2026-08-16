<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Paksa kepemilikan perusahaan pada data yang akan disimpan.
     *
     * Beberapa modul menerima `company_id` dari borang, sebab
     * administrator EQOHSEE memang perlu membuatkan data atas nama
     * perusahaan mana pun. Bagi yang BUKAN administrator, nilai itu
     * tidak boleh dipercaya: ia datang dari peramban, dan borang yang
     * tidak menampilkan kolomnya sama sekali tetap dapat dikirimi
     * kolom itu.
     *
     * Dua penjaga yang sudah ada tidak menutup celah ini, dan keduanya
     * memang bukan untuk itu:
     *
     * - Scope MilikPerusahaan menjaga PEMBACAAN. Baris yang tertanam di
     *   perusahaan lain justru menjadi tidak terlihat oleh yang
     *   menanamnya — dan tetap terlihat oleh korbannya.
     * - BerpemilikPerusahaan sengaja TIDAK menimpa company_id yang
     *   sudah disebut tegas. Itu perilaku yang benar bagi pemuat data
     *   contoh dan perintah konsol, dan justru karena itu ia tidak
     *   dapat merangkap sebagai penjaga di sini.
     *
     * Terbukti dapat dieksploitasi sebelum berkas ini ada: pengguna
     * biasa perusahaan A mengirim company_id perusahaan B ke
     * penyimpanan Dokumen dan Inspeksi, dan barisnya benar-benar
     * tersimpan sebagai milik B. Akibatnya bukan sekadar data nyasar —
     * daftar induk dokumen terkendali milik B bertambah satu prosedur
     * yang tidak pernah dibuat siapa pun di sana, dan pada dokumen
     * terkendali itulah audit eksternal bersandar.
     *
     * Dipasang di kelas dasar, bukan disalin ke tiap controller.
     * Sebelumnya memang disalin — sepuluh kali, dengan dua ejaan yang
     * berbeda — dan sembilan controller lain tidak kebagian. Penjaga
     * yang harus diingat untuk disalin adalah penjaga yang akan
     * terlewat pada controller berikutnya yang ditulis orang.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function pemilik(array $data): array
    {
        if (!auth()->user()?->isAdmin()) {
            $data['company_id'] = auth()->user()?->company_id;
        }

        return $data;
    }
}
