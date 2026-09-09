<?php

/**
 * Tujuan pembayaran.
 *
 * ── SELURUHNYA DARI .env, TIDAK SATU PUN DITULIS DI SINI ──
 *
 * Nomor rekening dan kode QRIS adalah data milik pemiliknya, bukan
 * bagian dari kode program. Ditulis langsung di berkas ini, keduanya
 * ikut masuk ke riwayat git dan ikut terbaca siapa pun yang menerima
 * salinan repositori — termasuk salinan yang dibagikan untuk keperluan
 * lain sama sekali.
 *
 * Nilai bawaannya sengaja KOSONG, bukan contoh yang terlihat masuk
 * akal. Nomor rekening contoh yang lupa diganti adalah nomor rekening
 * orang lain yang menerima pembayaran pelanggan Anda, dan tidak ada
 * satu pun galat yang akan memberitahukannya. Kosong membuat layarnya
 * menyebut "belum diatur" — keadaan yang tidak mungkin disalahpahami.
 */
/*
 * Kode QRIS merchant EQOHSEE, hasil pemindaian kode cetak resminya.
 *
 * ── MENGAPA DITULIS DI SINI, BUKAN HANYA DI .env ──
 *
 * Kode QRIS STATIS memang dirancang untuk dipajang: ia dicetak pada
 * stiker dan ditempel di meja kasir supaya siapa pun dapat
 * memindainya. Uang yang masuk lewatnya selalu menuju merchant yang
 * sama, jadi mengetahuinya tidak memberi keuntungan apa pun kepada
 * yang bukan pemiliknya.
 *
 * Ditulis di sini supaya pemasangan di server tidak menyisakan satu
 * langkah manual yang mudah terlewat — dan halaman bayar yang kehilangan
 * QRIS-nya karena satu baris .env lupa disalin adalah halaman yang
 * gagal menerima uang tanpa satu pun galat.
 *
 * .env tetap menang bila diisi: mengganti merchant tidak menuntut
 * penerbitan ulang kode program.
 *
 * Diverifikasi: CRC16 lolos, merchant "EQOHSEE WEB DEVELOPER",
 * NMID ID1026589758840, acquirer SpeedCash, mata uang 360 (IDR).
 */
$qrisEqohsee = '00020101021126760024ID.CO.SPEEDCASH.MERCHANT01189360081530004971620215ID10260049716220303UKE'
    .'51440014ID.CO.QRIS.WWW0215ID10265897588400303UKE5204597853033605802ID5921EQOHSEE WEB DEVELOPER'
    .'6008SIDOARJO61056125362410509S436776370117202609091219019290703A0163045F65';

return [

    /*
     * Kode QRIS STATIS milik merchant, disalin apa adanya dari bank
     * atau penyedia pembayaran. Panjangnya biasanya 200-400 karakter
     * dan diawali "00020101".
     *
     * Aplikasi menyisipkan nominal tagihan ke dalamnya sehingga pembeli
     * tidak perlu mengetik angka. Bila kodenya tidak lolos pemeriksaan
     * CRC — biasanya karena terpotong saat disalin — yang ditampilkan
     * kode aslinya beserta nominal tertulis, bukan kode olahan yang
     * tidak dapat dipindai.
     */
    /* `?:` bukan argumen kedua env(). Baris "QRIS_STATIS=" tanpa isi —
       persis yang tertulis di .env.example — membuat env() mengembalikan
       untai kosong, bukan nilai bawaannya, sehingga kode di atas tidak
       pernah terpakai dan halaman bayar menyebut "QRIS belum tersedia"
       pada pemasangan yang sebenarnya sudah lengkap. Terbukti begitu:
       tagihan pertama yang dicoba tidak memunculkan satu pun kode.

       Dengan `?:`, yang kosong dianggap belum diisi. Mengisinya tetap
       menang, dan itu memang seluruh gunanya. */
    'qris_statis' => env('QRIS_STATIS') ?: $qrisEqohsee,

    /*
     * Rekening bank tujuan, untuk pembeli yang memilih transfer biasa.
     * Ketiganya ditampilkan berdampingan; yang kosong tidak ditampilkan
     * sama sekali.
     */
    'bank' => [
        'nama'      => env('BANK_NAMA', 'Bank Mandiri'),
        'rekening'  => env('BANK_REKENING', ''),
        'atas_nama' => env('BANK_ATAS_NAMA', ''),
    ],

    /*
     * Berapa jam tagihan berlaku sebelum kedaluwarsa.
     *
     * Tagihan tanpa batas waktu menumpuk sebagai "menunggu bayar"
     * selamanya, dan daftar yang isinya ratusan baris mati berhenti
     * dibaca — membawa serta tagihan yang benar-benar masih hidup.
     */
    'kedaluwarsa_jam' => (int) env('PEMBELIAN_KEDALUWARSA_JAM', 48),

    /*
     * Cara menghubungi penjual, untuk yang belum berharga di katalog
     * dan untuk penawaran khusus.
     *
     * Kosong seperti nomor rekening di atas, dan karena alasan yang
     * sama: nomor telepon dan alamat surel adalah data pribadi
     * pemiliknya. Halaman katalog memakai kekosongan ini untuk memutuskan
     * apakah tombol "Minta penawaran" digambar sama sekali — jadi yang
     * terjadi bila belum diisi bukan tautan mati, melainkan tombol yang
     * memang tidak ada.
     */
    'kontak' => [
        'whatsapp' => env('PEMBELIAN_WHATSAPP', ''),
        'email'    => env('PEMBELIAN_EMAIL', ''),
    ],

];
