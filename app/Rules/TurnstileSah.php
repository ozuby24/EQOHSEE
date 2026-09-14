<?php

namespace App\Rules;

use App\Support\Turnstile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Aturan validasi untuk token Turnstile.
 *
 * Dibungkus sebagai aturan, bukan middleware, supaya kegagalannya muncul
 * seperti kesalahan isian biasa — di halaman yang sama, dengan formulir
 * yang masih terisi. Middleware akan memulangkan 403 atau halaman galat,
 * dan yang dibaca orangnya adalah "situsnya rusak", bukan "coba sekali
 * lagi".
 */
class TurnstileSah implements ValidationRule
{
    public function validate(string $atribut, mixed $nilai, Closure $gagal): void
    {
        if (Turnstile::sah(is_string($nilai) ? $nilai : null, request()->ip())) {
            return;
        }

        /* Pesannya sengaja tidak menyebut Cloudflare maupun "token".
           Yang membacanya sedang mencoba masuk, bukan sedang memperbaiki
           integrasi — dan istilah teknis pada pesan galat membuat orang
           berhenti mencoba, lalu menelepon. */
        $gagal('Verifikasi keamanan gagal. Muat ulang halaman lalu coba lagi.');
    }
}
