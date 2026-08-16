<?php

/**
 * Pengaturan kendali keamanan.
 *
 * Semuanya lewat .env supaya tidak ada nilai keamanan yang hanya dapat
 * diubah dengan menyunting kode dan mendeploy ulang. Pemasangan yang
 * berbeda punya toleransi yang berbeda, dan pengaturan yang terkunci di
 * dalam kode akhirnya diakali dengan cara yang lebih buruk daripada
 * pengaturan yang longgar.
 */
return [

    /*
     * HSTS — memaksa peramban tetap di https.
     *
     * Bawaannya MATI, meneruskan keputusan yang tertulis pada berkas
     * nginx. Nyalakan hanya setelah beberapa deploy membuktikan blok
     * 443-nya bertahan: selama masa berlakunya, peramban yang pernah
     * melihat tajuk ini akan MENOLAK membuka situs lewat http, jadi
     * https yang putus berarti situs yang tidak dapat dibuka sama
     * sekali — bukan situs yang sekadar tidak terenkripsi.
     */
    'hsts'      => env('KEAMANAN_HSTS', false),
    'hsts_umur' => (int) env('KEAMANAN_HSTS_UMUR', 31536000),   // setahun

    /*
     * Berapa lama jejak keamanan disimpan.
     *
     * Jejak yang terlalu pendek tidak menjawab pertanyaan yang baru
     * muncul sebulan kemudian; jejak yang tidak pernah dipangkas
     * tumbuh sampai tabelnya menjadi masalah tersendiri. Sembilan
     * puluh hari cukup untuk menelusuri satu insiden dari awal.
     */
    'simpan_jejak_hari' => (int) env('KEAMANAN_SIMPAN_JEJAK_HARI', 90),

    /*
     * Ambang tekanan percobaan masuk gagal dalam satu jam.
     *
     * Dipisah dari kode supaya pemasangan dengan banyak pengguna
     * lapangan — yang wajar sering salah ketik — dapat menaikkannya
     * tanpa mematikan peringatannya sama sekali.
     */
    'ambang_gagal_perhatian' => (int) env('KEAMANAN_AMBANG_PERHATIAN', 8),
    'ambang_gagal_gawat'     => (int) env('KEAMANAN_AMBANG_GAWAT', 20),

];
