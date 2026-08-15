<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Uji pengiriman surel tanpa mendaftarkan akun baru.
 *
 * Tanpa ini, satu-satunya cara mengetahui SMTP sudah benar adalah
 * mendaftar lalu menunggu — dan ketika tidak ada yang datang, tidak ada
 * yang bisa dibedakan antara sandi salah, porta tertutup, atau surelnya
 * mendarat di folder spam. Perintah ini menampilkan galat penyedia apa
 * adanya.
 */
class TesSurel extends Command
{
    protected $signature = 'eqohsee:tes-surel {email : Alamat tujuan uji coba}';

    protected $description = 'Kirim satu surel percobaan dan tampilkan hasilnya apa adanya';

    public function handle(): int
    {
        $tujuan = trim((string) $this->argument('email'));

        $this->newLine();
        $this->line('  Pengantar : <options=bold>'.config('mail.default').'</>');

        if (config('mail.default') === 'smtp') {
            $this->line('  Host      : '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port'));
            $this->line('  Skema     : '.(config('mail.mailers.smtp.scheme') ?: '(kosong — Symfony menebak dari porta)'));
            $this->line('  Pengguna  : '.config('mail.mailers.smtp.username'));
        }

        $this->line('  Dari      : '.config('mail.from.address'));
        $this->line('  Ke        : '.$tujuan);
        $this->newLine();

        if (in_array(config('mail.default'), ['log', 'array', 'null'], true)) {
            $this->warn('  Pengantarnya bukan pengirim sungguhan — tidak ada surel yang akan keluar.');
            $this->line('  Setel MAIL_MAILER=smtp di .env, lalu jalankan: php artisan config:cache');
            $this->newLine();

            return self::FAILURE;
        }

        try {
            Mail::raw(
                "Ini surel percobaan dari EQOHSEE.\n\n"
                ."Kalau Anda menerimanya, pengiriman surel sudah berjalan dan kode verifikasi\n"
                ."akan sampai dengan cara yang sama.\n\n"
                .'Dikirim '.now()->format('d M Y H:i:s T').'.',
                fn ($m) => $m->to($tujuan)->subject('Uji pengiriman surel EQOHSEE'),
            );
        } catch (\Throwable $e) {
            $this->error('  GAGAL — '.$e->getMessage());
            $this->newLine();
            $this->line('  Yang paling sering menjadi sebabnya:');
            $this->line('   · "Connection timed out" — porta keluar diblokir penyedia VPS,');
            $this->line('     bukan salah sandi. Uji dulu: nc -zv '
                        .config('mail.mailers.smtp.host').' '.config('mail.mailers.smtp.port'));
            $this->line('   · "Authentication failed" — sandi salah, atau penyedia meminta sandi aplikasi.');
            $this->line('   · "Sender address rejected" — MAIL_FROM_ADDRESS bukan alamat milik akun SMTP-nya.');
            $this->line('   · .env sudah diubah tetapi belum: php artisan config:cache');
            $this->newLine();
            $this->line('  MAIL_SCHEME boleh dibiarkan null: pada porta 465 Symfony memakai ssl://');
            $this->line('  dan pada 587 memakai STARTTLS, keduanya tanpa perlu disetel.');
            $this->newLine();

            return self::FAILURE;
        }

        $this->info('  Terkirim tanpa galat.');
        $this->line('  Periksa kotak masuk DAN folder spam di '.$tujuan.'.');
        $this->newLine();

        return self::SUCCESS;
    }
}
