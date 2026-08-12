<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Perintah uji pengiriman surel.
 *
 * Gunanya membedakan tiga hal yang dari luar tampak sama: surel gagal
 * dikirim, surel terkirim tetapi masuk spam, dan pengantarnya memang
 * bukan pengirim sungguhan.
 */
class TesSurelTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengantar_log_dilaporkan_gagal_bukan_berhasil(): void
    {
        // Pengantar 'log' selalu "berhasil" bagi Laravel. Melaporkannya
        // sebagai sukses membuat orang menyangka SMTP-nya sudah benar
        // dan mencari masalahnya di tempat yang salah.
        config(['mail.default' => 'log']);

        $this->artisan('eqohsee:tes-surel', ['email' => 'uji@contoh.test'])
             ->expectsOutputToContain('bukan pengirim sungguhan')
             ->assertFailed();
    }

    public function test_pengiriman_yang_berhasil_dilaporkan_berhasil(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);

        $this->artisan('eqohsee:tes-surel', ['email' => 'uji@contoh.test'])
             ->assertSuccessful();
    }

    public function test_galat_penyedia_ditampilkan_apa_adanya(): void
    {
        // Pesannya justru yang paling berguna: "Authentication failed"
        // dan "Connection timed out" menuntun ke perbaikan yang berbeda.
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
        ]);

        $this->artisan('eqohsee:tes-surel', ['email' => 'uji@contoh.test'])
             ->expectsOutputToContain('GAGAL')
             ->assertFailed();
    }
}
