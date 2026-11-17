<?php

namespace Tests\Feature;

use App\Models\{Company, WaterLog, WaterSump};
use App\Support\{Cuaca, KondisiSitus};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Perkiraan cuaca otomatis dari koordinat situs.
 *
 * HTTP-nya dipalsukan seluruhnya. Uji yang benar-benar menghubungi
 * Open-Meteo akan gagal di mesin tanpa jaringan, gagal saat layanannya
 * sedang padam, dan angkanya berubah tiap jam — tiga cara berbeda untuk
 * membuat uji ini ditandai lewati, dan bersamanya hilang penjagaan atas
 * hal yang sungguh berbahaya di sini: koordinat yang meleset.
 */
class CuacaOtomatisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function perusahaan(string $lokasi): Company
    {
        return Company::create(['name' => 'PT Situs Uji', 'code' => 'PSU', 'location' => $lokasi]);
    }

    private function palsu(array $geocode, float $mm = 12.5): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response($geocode),
            'api.open-meteo.com/*'           => Http::response(['current' => ['precipitation' => $mm]]),
        ]);
    }

    /* ---------- yang paling berbahaya: koordinat yang meleset ---------- */

    /**
     * Hasil geocode di LUAR Indonesia tidak boleh dipakai.
     *
     * Terukur pada geocoder yang sesungguhnya, dengan nama kabupaten
     * yang benar-benar dipakai perusahaan tambang: "Berau" memulangkan
     * Dili di Timor-Leste, dan "Kutai" memulangkan Chittagong di
     * Bangladesh. Keduanya di Kalimantan Timur.
     *
     * Yang membuatnya berbahaya bukan kesalahannya melainkan bentuknya:
     * angka hujan dari Timor-Leste tetap terlihat masuk akal, tergambar
     * rapi pada lencana yang sama, dan tidak ada satu pun tanda bahwa ia
     * milik tempat lain.
     */
    public function test_tempat_di_luar_indonesia_ditolak(): void
    {
        $this->palsu(['results' => [
            ['name' => 'Dili', 'country_code' => 'TL', 'latitude' => -8.3, 'longitude' => 125.56],
        ]]);

        $this->assertNull(Cuaca::koordinat($this->perusahaan('Berau')),
            'Koordinat di luar Indonesia dipakai — cuaca negara lain tergambar sebagai cuaca tambangnya.');
    }

    public function test_hasil_indonesia_dipilih_meski_bukan_yang_pertama(): void
    {
        $this->palsu(['results' => [
            ['name' => 'Dili',     'country_code' => 'TL', 'latitude' => -8.3,   'longitude' => 125.56],
            ['name' => 'Sangatta', 'country_code' => 'ID', 'latitude' => 0.5049, 'longitude' => 117.5279,
             'admin1' => 'East Kalimantan'],
        ]]);

        $titik = Cuaca::koordinat($this->perusahaan('Sangatta'));

        $this->assertSame(0.5049, $titik['lat']);
        $this->assertStringContainsString('Sangatta', $titik['tempat']);
        $this->assertFalse($titik['kasar']);
    }

    /* ---------- nama provinsi ---------- */

    /**
     * Provinsi diselesaikan TANPA menyentuh geocoder.
     *
     * Geocoder Open-Meteo hanya mengindeks tempat berpenduduk, sehingga
     * "Kalimantan Timur" tidak pernah ketemu di sana — dan itu justru
     * isi kolom lokasi yang paling lazim dipakai perusahaan tambang.
     */
    public function test_nama_provinsi_memakai_titik_tengahnya_dan_ditandai_kasar(): void
    {
        Http::fake([
            'geocoding-api.open-meteo.com/*' => Http::response(['results' => []]),
            'api.open-meteo.com/*'           => Http::response(['current' => ['precipitation' => 0]]),
        ]);

        $titik = Cuaca::koordinat($this->perusahaan('Kalimantan Timur'));

        $this->assertNotNull($titik, 'Nama provinsi tidak dikenali — lencananya tidak pernah muncul.');
        $this->assertSame('Kalimantan Timur', $titik['tempat']);
        $this->assertTrue($titik['kasar'],
            'Titik tengah provinsi seluas 129.000 km² harus ditandai kasar, '
            .'bukan disamakan dengan koordinat pit.');

        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'geocoding-api'));
    }

    public function test_provinsi_dikenali_di_dalam_sebutan_yang_lebih_panjang(): void
    {
        $this->palsu(['results' => []]);

        $titik = Cuaca::koordinat($this->perusahaan('Site Sangatta, Kalimantan Timur'));

        $this->assertSame('Kalimantan Timur', $titik['tempat']);
    }

    /* ---------- catatan situs selalu menang ---------- */

    /**
     * Yang terukur alat sendiri mengalahkan perkiraan.
     *
     * Keduanya tergambar sebagai angka milimeter pada lencana yang sama.
     * Yang satu bacaan alat di lokasi — dasar yang sah untuk
     * menghentikan pekerjaan di lereng; yang lain perkiraan dari titik
     * yang bisa berjarak puluhan kilometer.
     */
    public function test_catatan_situs_mengalahkan_perkiraan(): void
    {
        $this->palsu(['results' => [
            ['name' => 'Sangatta', 'country_code' => 'ID', 'latitude' => 0.5, 'longitude' => 117.5],
        ]], mm: 99.0);

        $c = $this->perusahaan('Sangatta');

        $sump = WaterSump::create([
            'company_id' => $c->id, 'kode' => 'KP-1', 'nama' => 'Kolam 1', 'jenis' => 'settling',
        ]);
        WaterLog::create([
            'company_id' => $c->id, 'water_sump_id' => $sump->id,
            'tanggal' => \App\Support\Waktu::kini()->toDateString(), 'curah_hujan_mm' => 3.2,
        ]);

        $cuaca = KondisiSitus::cuaca($c->fresh());

        $this->assertSame(3.2, $cuaca['hujanMm'], 'Perkiraan menimpa angka yang terukur di situs.');
        $this->assertSame('situs', $cuaca['sumber']);
    }

    public function test_tanpa_catatan_situs_perkiraan_yang_dipakai_dan_disebut(): void
    {
        $this->palsu(['results' => [
            ['name' => 'Sangatta', 'country_code' => 'ID', 'latitude' => 0.5, 'longitude' => 117.5],
        ]], mm: 62.5);

        $cuaca = KondisiSitus::cuaca($this->perusahaan('Sangatta'));

        $this->assertSame(62.5, $cuaca['hujanMm']);
        $this->assertSame('Hujan Lebat', $cuaca['label']);
        $this->assertSame('perkiraan', $cuaca['sumber'],
            'Perkiraan tidak menyebut dirinya perkiraan — ia terbaca sebagai bacaan alat.');
    }

    /* ---------- jaringan dan pengaturan ---------- */

    /**
     * Jaringan yang putus tidak boleh merobohkan halaman.
     *
     * Aplikasi ini dipakai di site tambang, tempat jaringan ke luar
     * memang sering tidak ada. Lencana yang hilang dapat diterima;
     * halaman yang gagal dimuat karena lencana tidak.
     */
    public function test_jaringan_gagal_hanya_menghilangkan_lencana(): void
    {
        Http::fake(fn () => throw new \RuntimeException('jaringan putus'));

        $this->assertNull(Cuaca::untuk($this->perusahaan('Sangatta')));
    }

    public function test_dapat_dimatikan_lewat_pengaturan(): void
    {
        config(['cuaca.aktif' => false]);
        Http::fake();

        $this->assertNull(Cuaca::untuk($this->perusahaan('Kalimantan Timur')));
        Http::assertNothingSent();
    }

    /**
     * Diambil SEKALI lalu disimpan.
     *
     * Lencana ini digambar pada halaman awal tiap modul. Tanpa
     * singgahan, tiap pembukaan halaman menjadi satu permintaan ke luar
     * dari server yang melayani banyak orang sekaligus.
     */
    public function test_permintaan_ke_luar_tidak_diulang_untuk_perusahaan_yang_sama(): void
    {
        $this->palsu(['results' => [
            ['name' => 'Sangatta', 'country_code' => 'ID', 'latitude' => 0.5, 'longitude' => 117.5],
        ]]);

        $c = $this->perusahaan('Sangatta');

        Cuaca::untuk($c);
        Cuaca::untuk($c->fresh());
        Cuaca::untuk($c->fresh());

        Http::assertSentCount(2);   // sekali geocode, sekali cuaca
    }
}
