<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Support\Cuaca;
use Illuminate\Console\Command;

/**
 * Menghangatkan singgahan cuaca tiap perusahaan.
 *
 * Tanpa perintah ini fiturnya tetap berjalan — singgahannya terisi oleh
 * permintaan halaman yang pertama kali menemukannya kosong. Persoalannya
 * SIAPA yang membayar: permintaan itu ikut menunggu jaringan ke luar,
 * dan lencana cuaca digambar pada halaman awal tiap modul, tepat di
 * jalur yang ditunggu orang. Di jaringan site tambang tunggu empat detik
 * itu terasa, dan yang menanggungnya selalu orang yang kebetulan membuka
 * halaman lebih dulu.
 *
 * Dijadwalkan tiap setengah jam, sepadan dengan umur singgahannya,
 * sehingga yang membuka halaman selalu menemukannya sudah terisi.
 */
class SegarkanCuaca extends Command
{
    protected $signature = 'cuaca:segarkan';

    protected $description = 'Ambil ulang perkiraan cuaca tiap perusahaan ke dalam singgahan.';

    public function handle(): int
    {
        if (!config('cuaca.aktif', true)) {
            $this->info('Perkiraan cuaca otomatis dimatikan (cuaca.aktif).');

            return self::SUCCESS;
        }

        $terisi = 0;
        $kosong = 0;

        foreach (Company::query()->cursor() as $perusahaan) {
            $hasil = Cuaca::untuk($perusahaan);

            if ($hasil === null) {
                $kosong++;
                $this->line(sprintf('  %-28s koordinatnya tidak ditemukan', mb_strimwidth($perusahaan->name, 0, 28)));
                continue;
            }

            $terisi++;
            $this->line(sprintf('  %-28s %5.1f mm  %s%s',
                mb_strimwidth($perusahaan->name, 0, 28),
                $hasil['hujanMm'],
                $hasil['tempat'],
                $hasil['kasar'] ? '  (perkiraan wilayah)' : ''));
        }

        $this->info("Selesai. {$terisi} terisi, {$kosong} tanpa koordinat.");

        return self::SUCCESS;
    }
}
