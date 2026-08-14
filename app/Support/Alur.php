<?php

namespace App\Support;

use App\Models\User;

/**
 * Alur baku data lapangan: draf → diajukan → disetujui atau ditolak.
 *
 * Sebelum ini setiap modul memiliki daftar statusnya sendiri —
 * Operasi memakai draft/terverifikasi/disetujui, Konservasi memakai
 * draft/diverifikasi/terbit — dan keduanya hanya berupa teks yang
 * ikut tervalidasi saat penyimpanan. Akibatnya status dapat disebut
 * langsung oleh pengirim datanya: operator lapangan menyimpan angka
 * produksinya sendiri sebagai "disetujui", tanpa seorang pun meninjau,
 * dan tidak ada galat apa pun yang menandainya. Angka yang belum
 * ditinjau lalu ikut terhitung di KPI dan ikut terbawa ke laporan.
 *
 * Yang dijaga kelas ini ada tiga, dan ketiganya gagal secara diam-diam
 * bila diserahkan ke masing-masing modul:
 *
 * 1. Status tidak pernah berasal dari isian formulir. Ia hanya berubah
 *    lewat perpindahan yang tercatat di TRANSISI.
 * 2. Pengaju bukan peninjau. Seseorang tidak menyetujui pekerjaannya
 *    sendiri, sekalipun ia memegang hak meninjau.
 * 3. Yang sudah disetujui terkunci. Mengubahnya berarti mengubah angka
 *    yang sudah masuk laporan tanpa jejak; jalannya adalah menolak
 *    lebih dulu, atau membuat data pembetulan.
 *
 * Peninjau adalah administrator atau Kepala Teknik Tambang. KTT dipilih
 * bukan karena kebetulan tersedia di data pengguna, melainkan karena
 * pada pertambangan Indonesia dialah yang bertanggung jawab atas
 * kebenaran angka operasional yang dilaporkan.
 */
final class Alur
{
    public const DRAF      = 'draf';
    public const DIAJUKAN  = 'diajukan';
    public const DISETUJUI = 'disetujui';
    public const DITOLAK   = 'ditolak';

    public const SEMUA = [self::DRAF, self::DIAJUKAN, self::DISETUJUI, self::DITOLAK];

    /**
     * Perpindahan yang diizinkan, dari → ke.
     *
     * Ditolak kembali ke draf, bukan langsung ke diajukan: yang ditolak
     * perlu diperbaiki lebih dulu, dan pengajuan ulang harus menjadi
     * tindakan yang disengaja.
     */
    public const TRANSISI = [
        self::DRAF      => [self::DIAJUKAN],
        self::DIAJUKAN  => [self::DISETUJUI, self::DITOLAK, self::DRAF],
        self::DITOLAK   => [self::DRAF],
        self::DISETUJUI => [],
    ];

    /** Label yang ditampilkan kepada pengguna. */
    public const LABEL = [
        self::DRAF      => 'Draf',
        self::DIAJUKAN  => 'Menunggu tinjauan',
        self::DISETUJUI => 'Disetujui',
        self::DITOLAK   => 'Ditolak',
    ];

    /**
     * Hanya yang disetujui boleh masuk hitungan KPI dan laporan.
     *
     * Dipakai sebagai satu-satunya sumber jawaban atas pertanyaan
     * "angka mana yang boleh dihitung", supaya modul yang ditulis
     * kemudian tidak diam-diam ikut menghitung draf orang lain.
     */
    public static function terhitung(): array
    {
        return [self::DISETUJUI];
    }

    public static function bolehPindah(string $dari, string $ke): bool
    {
        return in_array($ke, self::TRANSISI[$dari] ?? [], true);
    }

    /** Hak meninjau: administrator atau Kepala Teknik Tambang. */
    public static function peninjau(?User $u): bool
    {
        return (bool) $u?->isAdmin() || (bool) $u?->isKtt();
    }
}
