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
    'qris_statis' => env('QRIS_STATIS', ''),

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

];
