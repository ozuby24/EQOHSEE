<?php

/*
 * Bahasa Inggris untuk halaman tamu (BahasaTamu): hanya nama kolom.
 *
 * Pesan validasinya sendiri berasal dari berkas bawaan Laravel — berkas
 * ini DIGABUNG dengan berkas bawaan itu (array_replace_recursive), bukan
 * menggantikannya. Tanpa nama-nama ini, pesannya menyebut nama kolom
 * mentah, misalnya "The cf-turnstile-response field is required".
 */
return [
    'attributes' => [
        'name'                  => 'full name',
        'email'                 => 'email',
        'password'              => 'password',
        'password_confirmation' => 'password confirmation',
        'company_id'            => 'company',
        'employee_id'           => 'employee ID',
        'department'            => 'department',
        'position'              => 'job title',
        'kode'                  => 'code',
        'cf-turnstile-response' => 'security check',
    ],
];
