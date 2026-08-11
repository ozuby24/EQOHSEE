<?php

namespace App\Support;

/**
 * Daftar rute yang dirender Inertia (Vue), bukan Blade.
 *
 * Ini menentukan bentuk tautan yang digambar sisi Vue: <Link> Inertia
 * untuk sesama halaman Inertia, dan <a href> biasa untuk halaman Blade.
 *
 * Bukan sekadar soal cepat-lambat — ini soal jalan atau tidak.
 * Bertentangan dengan dugaan yang wajar, <Link> TIDAK jatuh ke navigasi
 * peramban biasa ketika tanggapannya bukan Inertia: ia mengirim
 * permintaan ber-header X-Inertia, menerima HTML utuh, lalu menampilkan
 * modal galat dan tetap diam di halaman yang sama. Sempat terjadi:
 * seluruh bilah samping memakai <Link>, dan dari halaman Vue tidak ada
 * satu pun menu yang bisa diklik — satu-satunya jalan keluar adalah
 * tombol back peramban.
 *
 * Daftar ini harus tumbuh setiap kali satu halaman dipindah ke Inertia.
 * Yang menjaganya bukan disiplin melainkan uji: ada uji yang memanggil
 * tiap rute dan membandingkan jenis tanggapannya dengan daftar ini,
 * sehingga daftar yang tertinggal maupun kelebihan sama-sama ketahuan.
 */
final class RuteInertia
{
    /** Nama rute yang mengembalikan Inertia::render(). */
    public const NAMA = [
        'tpkkp.index',
        'tpkkp.assess',
        'tpkkp.rekap',
        'tpkkp.matriks',
        'tpkkp.summary',
        'tpkkp.hasil',
        'tpkkp.metode',
        'tpkkp.tentang',
        'tpkkp.visual',
        'tpkkp.rubrik',
        'tpkkp.jadwal',
        'tpkkp.profile',
        'tpkkp.program',
        'tpkkp.sampling',
        'tpkkp.sampel',
        'tpkkp.roster',
        'tpkkp.data',

        // Satu aksi, dua nama rute: bilah samping memakai tpkkp.kuesioner
        // dan chip PTPKKP memakai kuesioner.admin. Keduanya harus ada di
        // sini, kalau tidak salah satunya menggambar <a href> biasa dan
        // memuat ulang halaman penuh tanpa alasan yang tampak.
        'tpkkp.kuesioner',
        'kuesioner.admin',
    ];

    public static function ada(?string $rute): bool
    {
        return $rute !== null && in_array($rute, self::NAMA, true);
    }
}
