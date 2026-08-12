<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Jalan keluar dari sisi server ketika surel tidak sampai.
 *
 * Kode verifikasi disimpan dalam bentuk tercacah, jadi kode yang sudah
 * terkirim tidak bisa dibaca ulang dari basis data — bahkan oleh
 * administrator. Yang bisa dilakukan hanya dua: menerbitkan kode baru
 * lalu membacakannya, atau menandai surelnya terverifikasi langsung.
 *
 * Keduanya ada di sini supaya tidak ada yang tergoda menurunkan
 * pencacahan kodenya hanya agar bisa mengintip.
 */
class VerifikasiEmailPengguna extends Command
{
    protected $signature = 'eqohsee:verifikasi
                            {email : Alamat surel penggunanya}
                            {--kode : Terbitkan kode baru dan tampilkan, jangan langsung verifikasi}';

    protected $description = 'Verifikasi surel pengguna, atau terbitkan kode baru yang dapat dibacakan';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));
        $u = User::where('email', $email)->first();

        if (!$u) {
            $this->error("Pengguna dengan surel {$email} tidak ada.");

            return self::FAILURE;
        }

        if ($this->option('kode')) {
            $kode = $u->buatKodeVerifikasi();

            $this->newLine();
            $this->line("  Kode untuk <options=bold>{$u->email}</>: <fg=bright-yellow;options=bold>{$kode}</>");
            $this->line('  Berlaku '.User::KODE_BERLAKU.' menit sejak sekarang.');
            $this->newLine();

            return self::SUCCESS;
        }

        if ($u->hasVerifiedEmail()) {
            $this->info("Surel {$u->email} memang sudah terverifikasi.");

            return self::SUCCESS;
        }

        $u->markEmailAsVerified();
        $this->info("Surel {$u->email} ditandai terverifikasi.");

        return self::SUCCESS;
    }
}
