<?php

namespace App\Console\Commands;

use App\Support\MasukPihakKetiga;
use Illuminate\Console\Command;

/**
 * Periksa pemasangan Masuk/Daftar dengan Google & Microsoft dari sisi
 * server: alamat kembali (redirect URI) yang PERSIS dikirim — dan karena
 * itu harus terdaftar di konsol penyedia — serta setelan kuki sesi yang
 * pernah memutus alurnya.
 *
 * Dua galat yang ditangani di sini terlihat di layar penyedia, bukan di
 * aplikasi, jadi tidak ada yang dapat ditangkap dari kode:
 *  - "Error 400: redirect_uri_mismatch" — alamat di konsol berbeda satu
 *    huruf pun (http/https, www, garis miring penutup) dari yang dikirim.
 *  - Kembali dari Google tetapi "waktu masuk habis atau terputus" — kuki
 *    sesi/state tidak terbawa (SameSite strict, SESSION_DOMAIN yang tidak
 *    cocok dengan host, APP_URL bukan alamat situs).
 */
class PeriksaSso extends Command
{
    protected $signature = 'eqohsee:periksa-sso';

    protected $description = 'Periksa pemasangan masuk/daftar dengan Google & Microsoft: alamat kembali yang harus didaftarkan dan setelan kuki sesi';

    public function handle(): int
    {
        $masalah = 0;
        $akar = rtrim((string) config('app.url'), '/');
        $hostApp = strtolower((string) parse_url($akar, PHP_URL_HOST));

        $this->newLine();
        $this->line('  APP_URL : <options=bold>'.($akar ?: '(kosong)').'</>');
        if (!str_starts_with($akar, 'https://')) {
            $this->warn('  ! APP_URL bukan https:// — Google menolak alamat kembali http untuk situs publik.');
            $masalah++;
        }
        if (in_array($hostApp, ['', 'localhost', '127.0.0.1'], true)) {
            $this->warn('  ! APP_URL belum berisi alamat situs (mis. https://eqohsee.id).');
            $masalah++;
        }

        foreach (MasukPihakKetiga::PENYEDIA as $penyedia) {
            $nama = MasukPihakKetiga::nama($penyedia);
            $kunci = strtoupper($penyedia);
            $this->newLine();
            $this->line("  <options=bold>{$nama}</>");

            if (!MasukPihakKetiga::aktif($penyedia)) {
                $this->line("    Belum dipasang: {$kunci}_CLIENT_ID dan {$kunci}_CLIENT_SECRET kosong — tombolnya tidak tampil.");
                continue;
            }

            $alamat = MasukPihakKetiga::alamatKembali($penyedia);
            $this->line('    Alamat kembali yang dikirim (redirect URI):');
            $this->line("      <fg=green>{$alamat}</>");
            $this->line("    Daftarkan PERSIS alamat ini di konsol {$nama} ("
                .($penyedia === 'google' ? 'Credentials → OAuth client → Authorized redirect URIs' : 'App registrations → Authentication → Redirect URIs').').');

            $lain = array_values(array_diff(MasukPihakKetiga::alamatDiterima($penyedia), [$alamat]));
            if ($lain !== []) {
                $this->line('    Alamat yang juga dilayani aplikasi (boleh ikut didaftarkan):');
                foreach ($lain as $l) {
                    $this->line("      {$l}");
                }
            }

            $hostKembali = strtolower((string) parse_url($alamat, PHP_URL_HOST));
            if ($hostApp !== '' && $hostKembali !== $hostApp) {
                $this->warn("  ! Host alamat kembali ({$hostKembali}) berbeda dari APP_URL ({$hostApp}): kuki yang dibuat saat berangkat tidak ikut kembali.");
                $masalah++;
            }

            $jalur = (string) parse_url($alamat, PHP_URL_PATH);
            if (!in_array($jalur, ["/masuk/{$penyedia}/kembali", "/auth/{$penyedia}/callback"], true)) {
                $this->warn("  ! {$kunci}_REDIRECT_URI menunjuk {$jalur}, yang tidak dilayani aplikasi. Kosongkan, atau pakai /masuk/{$penyedia}/kembali.");
                $masalah++;
            }
        }

        $this->newLine();
        $this->line('  <options=bold>Kuki sesi</>');
        $domain = config('session.domain');
        $sameSite = strtolower((string) config('session.same_site'));
        $this->line('    SESSION_DRIVER    : '.config('session.driver'));
        $this->line('    SESSION_DOMAIN    : '.($domain ?: '(kosong — khusus host)'));
        $this->line('    SESSION_SECURE    : '.(config('session.secure') ? 'ya' : 'tidak'));
        $this->line('    SESSION_SAME_SITE : '.($sameSite ?: '(kosong)'));

        if ($sameSite === 'strict') {
            $this->warn('  ! SameSite strict: kuki sesi tidak terkirim saat kembali dari Google. Pakai lax.');
            $masalah++;
        }

        if ($domain) {
            $d = ltrim(strtolower((string) $domain), '.');
            if ($hostApp !== '' && $hostApp !== $d && !str_ends_with($hostApp, '.'.$d)) {
                $this->warn("  ! SESSION_DOMAIN ({$d}) tidak mencakup host APP_URL ({$hostApp}): peramban menolak kuki sesinya.");
                $masalah++;
            }
            $this->line('    Catatan: sesudah SESSION_DOMAIN diubah, kuki sesi lama yang kembar dihapus otomatis pada kunjungan berikutnya.');
        }

        if (config('session.secure') && str_starts_with($akar, 'http://')) {
            $this->warn('  ! SESSION_SECURE_COOKIE=true tetapi situs http://: kuki sesi tidak pernah dikirim.');
            $masalah++;
        }

        $this->newLine();
        if ($masalah === 0) {
            $this->info('  Tidak ada masalah setelan. Bila Google masih menolak, samakan alamat di konsolnya dengan alamat hijau di atas.');
        } else {
            $this->error("  {$masalah} hal perlu dibetulkan (tanda ! di atas). Sesudah mengubah .env: php artisan config:cache");
        }
        $this->newLine();

        return $masalah === 0 ? self::SUCCESS : self::FAILURE;
    }
}
