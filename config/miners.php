<?php

/*
 * Pengaturan modul Miners (kelayakan karyawan).
 *
 * Ditulis sebagai berkas pengaturan, bukan diketik di dalam kode, supaya
 * pemasangan yang berbeda dapat memakai alamat tembusannya sendiri tanpa
 * menyunting satu baris PHP pun.
 */
return [

    'surel' => [
        /* Tembusan tetap untuk SETIAP pemberitahuan alur — padanan
           MAIL_CC Safe Track. Boleh beberapa alamat, dipisah koma.
           Kosong berarti tanpa tembusan tetap; tembusan per peran tetap
           diatur pada layar Alur Persetujuan. */
        'tembusan' => env('MINERS_SUREL_TEMBUSAN', ''),

        /* Kotak surel tim OHSE — padanan MAIL_OHSE Safe Track. Menerima
           pemberitahuan rujukan MCU baru dan perpanjangan Mine Permit
           yang diajukan. Kosong berarti HSE yang ditunjuk pada skema IUP
           dokumennya (beserta penerima "to"-nya). */
        'ohse' => env('MINERS_SUREL_OHSE', ''),
    ],

];
