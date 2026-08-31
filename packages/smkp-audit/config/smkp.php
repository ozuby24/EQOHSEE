<?php

use Eqohsee\SmkpAudit\Adapters\JejakDiam;
use Eqohsee\SmkpAudit\Adapters\PerusahaanDariPengguna;

/**
 * Setelan modul Audit SMKP.
 *
 * Seluruh titik sambung ke aplikasi induk ditulis sebagai NAMA KELAS, bukan
 * closure. Alasannya sederhana dan mahal bila dilupakan: `php artisan
 * config:cache` menuliskan berkas ini lewat var_export, dan closure tidak
 * dapat ditulis ulang — aplikasi yang meng-cache konfigurasinya akan gagal
 * booting dengan galat yang tidak menyebut modul ini sama sekali.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Rute
    |--------------------------------------------------------------------------
    | Nama rute dipakai apa adanya oleh controller dan berkas cetak; mengubah
    | `nama` berarti mengubah keduanya. `awalan` dikirim ke Vue sebagai dasar
    | URL, jadi awalan yang berbeda tetap bekerja tanpa menyunting Vue.
    */
    'rute' => [
        'daftar'     => true,          // false bila rutenya ingin ditulis sendiri
        'awalan'     => 'smkp',
        'nama'       => 'smkp.',
        'middleware' => ['web', 'auth'],

        // Middleware tambahan untuk penghapusan periode audit. Kosongkan bila
        // aplikasi tidak punya gate 'admin'.
        'middleware_hapus' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Halaman Inertia
    |--------------------------------------------------------------------------
    | Nama komponen yang diterbitkan ke resources/js aplikasi induk.
    */
    'inertia' => [
        'halaman' => 'Smkp/Halaman',
        'cetak'   => 'Print/Smkp',
    ],

    /*
    |--------------------------------------------------------------------------
    | Berkas acuan kriteria
    |--------------------------------------------------------------------------
    | Kosong = memakai salinan bawaan paket. Isi dengan path lengkap bila
    | acuannya diterbitkan dan disunting sendiri, misalnya:
    | resource_path('data/smkp/elemen.json').
    */
    'acuan' => null,

    /*
    |--------------------------------------------------------------------------
    | Model aplikasi induk
    |--------------------------------------------------------------------------
    | `perusahaan` boleh null pada aplikasi satu perusahaan — kolom
    | company_id tetap ada tetapi tidak pernah diisi maupun disaring.
    */
    'model' => [
        'perusahaan' => null,          // mis. App\Models\Company::class
        'pengguna'   => null,          // mis. App\Models\User::class
    ],

    /*
    |--------------------------------------------------------------------------
    | Batas data per perusahaan
    |--------------------------------------------------------------------------
    | `penyaring` menjawab dua hal: perusahaan mana yang sedang aktif, dan
    | siapa yang boleh melintasinya. Ganti dengan kelas sendiri bila aplikasi
    | induk menyimpan penempatan perusahaan di tempat lain.
    */
    'perusahaan' => [
        'penyaring' => PerusahaanDariPengguna::class,

        // Kolom perusahaan pada tabel modul SELALU `company_id`; yang dapat
        // disesuaikan adalah ke mana ia menunjuk dan dari mana perusahaan
        // aktif dibaca. Membuat nama kolomnya ikut dapat disetel hanya
        // setengah bekerja — formulir Vue dan $fillable tetap menyebut
        // `company_id`, dan ketidakcocokannya hilang diam-diam sebagai isian
        // yang tidak pernah tersimpan.
        'atribut'   => 'company_id',   // atribut pada model pengguna
        'nama'      => 'name',         // kolom nama perusahaan
        'tabel'     => 'companies',    // tabel rujukan kunci asing
        'admin'     => 'isAdmin',      // metode pengguna yang menjawab "boleh lintas"
    ],

    /*
    |--------------------------------------------------------------------------
    | Jejak aktivitas
    |--------------------------------------------------------------------------
    | Bawaannya diam. Ganti dengan adaptor sendiri untuk menyambungkannya ke
    | tabel log aplikasi induk — lihat README bagian "Jejak aktivitas".
    */
    'jejak' => JejakDiam::class,

    /*
    |--------------------------------------------------------------------------
    | Migrasi
    |--------------------------------------------------------------------------
    | true  = migrasi paket ikut dijalankan langsung dari paket.
    | false = pakai salinan yang sudah diterbitkan ke database/migrations.
    */
    'migrasi' => true,

    /*
    |--------------------------------------------------------------------------
    | Tabel pengguna
    |--------------------------------------------------------------------------
    | Dipakai migrasi untuk kunci asing user_id. Kosongkan (null) bila tidak
    | ingin ada kunci asing sama sekali.
    */
    'tabel_pengguna' => 'users',

    /*
    |--------------------------------------------------------------------------
    | Kop dokumen terkendali
    |--------------------------------------------------------------------------
    | Berkas audit terbit sebagai dokumen terkendali: tiap lembar membawa
    | nomor dokumen, tanggal terbit, tanggal persetujuan, revisi, dan nomor
    | halaman. `atribut` memetakan isi kop ke kolom model perusahaan aplikasi
    | induk; atribut yang tidak ada diabaikan tanpa galat.
    */
    'kop' => [
        'divisi'     => 'Occupational Health, Safety and Environment, External',
        'departemen' => 'Occupational Health, Safety and Environment',
        'prefiks'    => null,          // prefiks tetap bila tanpa model perusahaan
        'perusahaan' => 'Perusahaan',  // nama cadangan pada kop

        'atribut' => [
            'nama'       => 'name',
            'prefiks'    => 'doc_no_prefix',
            'terbit'     => 'doc_terbit',
            'setuju'     => 'doc_setuju',
            'revisi'     => 'doc_revisi',
            'divisi'     => 'divisi',
            'departemen' => 'departemen',
            'logo'       => 'logo',
        ],

        // Awalan URL berkas logo; hasilnya dipakai apa adanya oleh <img src>.
        'logo_disk' => 'storage/',
    ],
];
