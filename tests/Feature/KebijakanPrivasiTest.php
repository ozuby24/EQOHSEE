<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Halaman kebijakan privasi.
 *
 * Google Play menyimpan SATU alamat halaman ini dan memeriksanya dari
 * perangkat peninjau yang tidak punya akun di sini. Begitu halamannya
 * mengalihkan ke /login, peninjauan gagal — dan pesannya tidak
 * menyinggung sebabnya sama sekali, sehingga yang membacanya akan
 * mencari di Play Console, bukan di berkas rute.
 *
 * Cacat itu tidak menimbulkan galat apa pun di sisi kami: halamannya
 * tetap ada, tetap tergambar bagi yang sudah masuk, dan seluruh uji
 * lain tetap hijau. Satu-satunya yang menyadarinya adalah orang di
 * Google yang menolak aplikasinya beberapa hari kemudian.
 */
class KebijakanPrivasiTest extends TestCase
{
    /** @return array<int, array{0: string}> */
    public static function alamat(): array
    {
        return [['/kebijakan-privasi'], ['/privacy-policy']];
    }

    /* #[DataProvider], bukan @dataProvider pada docblock: PHPUnit 12
       mengabaikan bentuk docblock-nya tanpa peringatan, dan ujinya
       dijalankan TANPA argumen — gagal karena kekurangan parameter,
       bukan karena halamannya bermasalah. */
    #[DataProvider('alamat')]
    public function test_terbuka_tanpa_login(string $alamat): void
    {
        $ini = $this->get($alamat);

        $ini->assertOk();
        $ini->assertDontSee('login', false);
    }

    #[DataProvider('alamat')]
    public function test_tidak_mengalihkan_tamu(string $alamat): void
    {
        /* assertOk() saja tidak cukup: pengalihan memulangkan 302, yang
           memang bukan 200 — tetapi uji yang hanya memeriksa "bukan
           galat" akan meloloskannya. Diperiksa langsung. */
        $this->get($alamat)->assertStatus(200);
    }

    public function test_menyebut_data_yang_benar_benar_dikumpulkan(): void
    {
        /* Kebijakan yang diam soal lokasi dan foto sementara aplikasinya
           menyimpan keduanya bukan sekadar tidak lengkap — ia tidak
           cocok dengan formulir Data Safety, dan ketidakcocokan itu
           dapat berujung penangguhan aplikasi, bukan penolakan biasa.
           hr_absensi_jejak menyimpan lat, lng, dan berkas_swafoto. */
        $ini = $this->get('/kebijakan-privasi');

        foreach (['Lokasi presisi', 'Swafoto', 'biometrik', 'kesehatan'] as $wajib) {
            $ini->assertSee($wajib, false);
        }
    }

    public function test_menyebut_siapa_pengendali_datanya(): void
    {
        /* EQOHSEE prosesor, perusahaan penggunanya pengendali. Tanpa ini
           seorang pekerja akan mengirim permintaan penghapusan ke kami,
           dan kami memang tidak berwenang mengabulkannya. */
        $this->get('/kebijakan-privasi')
            ->assertSee('Pengendali Data', false)
            ->assertSee('Prosesor Data', false);
    }

    public function test_memuat_cara_menghubungi(): void
    {
        /* Play menolak kebijakan yang tidak memuat kontak penerbitnya. */
        $this->get('/kebijakan-privasi')->assertSee(config('hukum.surel'), false);
    }

    public function test_kedua_bahasa_saling_menunjuk(): void
    {
        $this->get('/kebijakan-privasi')->assertSee('/privacy-policy', false);
        $this->get('/privacy-policy')->assertSee('/kebijakan-privasi', false);
    }
}
