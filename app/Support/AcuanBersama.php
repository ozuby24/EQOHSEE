<?php

namespace App\Support;

/**
 * Tabel yang baris tanpa perusahaannya adalah DAFTAR ACUAN BERSAMA.
 *
 * Batas antar perusahaan menganggap company_id NULL sebagai "milik
 * bersama". Untuk data lapangan itu tanda pemilik yang hilang; untuk
 * tabel di bawah ini justru rancangannya: daftar awal yang ditanam
 * perintah pemasangan saat deploy (miners:pasang, roster:pasang,
 * energi:pasang, lca:pasang) atau oleh pemuat data contoh, berlaku bagi
 * setiap perusahaan, dan perusahaan tetap boleh menambah barisnya
 * sendiri di sampingnya.
 *
 * Tanpa daftar ini, Pemeriksaan Mandiri menghitung ratusan baris acuan
 * itu sebagai "Baris tanpa perusahaan" berstatus GAWAT pada setiap
 * pemasangan — dan peringatan yang selalu merah mengajari pembacanya
 * mengabaikan warna merah, tepat ketika suatu hari yang merah adalah
 * data lapangan yang benar-benar kehilangan pemiliknya.
 *
 * Menambah tabel di sini adalah pernyataan bahwa barisnya yang tanpa
 * perusahaan BUKAN rahasia siapa pun. Uji AcuanBersamaTest menjalankan
 * seluruh perintah pemasangan dan menuntut setiap tabel yang mereka isi
 * tanpa perusahaan tercantum di sini — sehingga daftar awal baru tidak
 * dapat lahir tanpa disebut, dan data lapangan tidak dapat diam-diam
 * ikut dikecualikan.
 */
final class AcuanBersama
{
    /** @var array<string,string> tabel => asal daftar awalnya */
    public const TABEL = [
        // Energi & karbon, LCA
        'energi_faktor'       => 'faktor energi & emisi (energi:pasang)',
        'lca_faktor'          => 'pustaka faktor dampak LCA (lca:pasang)',

        // Kompetensi & unit — master nasional
        'kompetensi_jenis'    => 'jenis kompetensi nasional (MasterKompetensi)',
        'ko_unit_master'      => 'master unit SPIP (MasterUnitSpip)',

        // Personalia
        'hr_pola_roster'      => 'pola roster (roster:pasang)',
        'hr_jenis_cuti'       => 'jenis cuti menurut peraturan (roster:pasang)',

        // Authority / Miners
        'mnr_departemen'      => 'master Miners (miners:pasang)',
        'mnr_jabatan'         => 'master Miners (miners:pasang)',
        'mnr_blok'            => 'lokasi kerja acuan (miners:pasang)',
        'mnr_kendaraan'       => 'golongan kendaraan (miners:pasang)',
        'mnr_sub_kendaraan'   => 'rincian golongan kendaraan (miners:pasang)',
        'mnr_jenis_unit'      => 'jenis unit SIMPER (miners:pasang)',
        'mnr_tipe_permit'     => 'jenis izin khusus (miners:pasang)',
        'mnr_kategori_permit' => 'kategori izin khusus (miners:pasang)',
        'mnr_hasil_mcu'       => 'hasil MCU (miners:pasang)',
        'mnr_skema'           => 'alur penyetuju umum, cadangan bagi IUP tanpa alur sendiri',
    ];

    public static function termasuk(string $tabel): bool
    {
        return array_key_exists($tabel, self::TABEL);
    }
}
