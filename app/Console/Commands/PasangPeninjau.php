<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Akun peninjau Google Play.
 *
 * Aplikasi yang wajib masuk tidak dapat ditinjau tanpa akun: peninjau
 * hanya akan melihat halaman masuk lalu menolak dengan alasan "tidak
 * dapat mengakses aplikasi". Play Console meminta satu akun beserta
 * sandinya di bagian App access.
 *
 * Yang dibuat di sini SENGAJA bukan administrator: peninjau tidak perlu
 * — dan tidak boleh — melihat data seluruh perusahaan. Ia pengawas pada
 * perusahaan contoh, cukup untuk mencoba mode lapangan seutuhnya: lapor
 * bahaya, P2H termasuk lepas tahan, izin kerja, dan modul web.
 *
 * Sandinya acak dan dicetak SEKALI. Menjalankan ulang perintah ini
 * membuat sandi baru — pakai itu sesudah masa peninjauan selesai supaya
 * sandi yang pernah ditempel di Play Console berhenti berlaku.
 */
class PasangPeninjau extends Command
{
    protected $signature = 'peninjau:pasang
        {--surel=peninjau.play@eqohsee.id : Alamat surel akun peninjau}
        {--perusahaan=CDI : Kode perusahaan contoh tempat akun peninjau diletakkan}
        {--nonaktif : Nonaktifkan akun peninjau (sesudah peninjauan selesai)}';

    protected $description = 'Buat atau setel ulang akun peninjau Google Play dengan sandi acak.';

    public function handle(): int
    {
        $surel = strtolower(trim((string) $this->option('surel')));

        if ($this->option('nonaktif')) {
            $u = User::where('email', $surel)->first();
            if (!$u) {
                $this->warn("Akun {$surel} tidak ada.");
                return self::SUCCESS;
            }
            $u->forceFill(['active' => false, 'password' => Hash::make(Str::random(48))])->save();
            $this->info("Akun {$surel} dinonaktifkan dan sandinya diacak.");
            return self::SUCCESS;
        }

        $kode = strtoupper((string) $this->option('perusahaan'));
        $c = Company::where('code', $kode)->first();

        if (!$c) {
            $this->error("Perusahaan berkode {$kode} tidak ada. Jalankan `php artisan demo:pasang --hanya={$kode}` lebih dulu, atau pilih --perusahaan lain.");
            return self::FAILURE;
        }

        /* Huruf dan angka saja, tanpa tanda baca: sandinya akan ditempel
           ke kolom Play Console dan diketik ulang peninjau di ponsel —
           tanda baca adalah sumber salah ketik paling sering. 20 karakter
           alfanumerik tetap jauh di atas yang dapat ditebak. */
        $sandi = Str::password(20, symbols: false);

        $u = User::firstOrNew(['email' => $surel]);
        $u->fill([
            'name'       => 'Peninjau Aplikasi',
            'position'   => 'Pengawas K3',
            'department' => 'HSE',
            'company_id' => $c->id,
            'is_admin'   => false,
            'lms_role'   => 'ktt',
            'active'     => true,
        ]);
        $u->forceFill([
            'password'             => Hash::make($sandi),
            'email_verified_at'    => $u->email_verified_at ?? now(),
            'dua_faktor_rahasia'   => null,
            'dua_faktor_pemulihan' => null,
            'dua_faktor_aktif_at'  => null,
        ])->save();

        $this->newLine();
        $this->line('  Akun peninjau Google Play — tempel di Play Console → App content → App access');
        $this->newLine();
        $this->line("  Surel     : {$surel}");
        $this->line("  Sandi     : {$sandi}");
        $this->line("  Perusahaan: {$c->name} ({$c->code})");
        $this->newLine();
        $this->line('  Sandi ini hanya dicetak sekali. Sesudah peninjauan selesai jalankan:');
        $this->line("  php artisan peninjau:pasang --nonaktif --surel={$surel}");
        $this->newLine();

        return self::SUCCESS;
    }
}
