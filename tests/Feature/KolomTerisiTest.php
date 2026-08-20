<?php

namespace Tests\Feature;

use App\Models\{Paspor, PasporInduksi, PasporKartu, PasporKartuUnit, PasporMcu};
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kolom yang ada di tabel harus dapat diisi lewat modelnya.
 *
 * Kegagalan yang dijaga di sini benar-benar terjadi dan bertahan
 * melewati beberapa penyebaran: migrasi menambah kolom, formulir dan
 * data contoh mengirim nilainya, dan `$fillable` tidak ikut diperbarui.
 * Eloquent MEMBUANG atribut yang tidak fillable tanpa satu pun galat —
 * baris tersimpan, halaman terbuka, dan enam belas kolom berisi null
 * selamanya.
 *
 * Yang membuatnya sulit terlihat: tampilannya benar. Lampiran syarat
 * permit tampil sebagai enam tanda "—" yang terbaca sebagai "belum
 * diunggah", bukan sebagai "tidak pernah tersimpan".
 *
 * Karena itu yang diuji BUKAN satu kolom tertentu melainkan
 * kesepadanan tabel dengan modelnya, untuk seluruh model rantai Miners.
 */
class KolomTerisiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Kolom yang memang tidak boleh diisi massal.
     *
     * Kunci, cap waktu, dan medan alur tinjauan: yang terakhir sengaja
     * di luar $fillable supaya status tidak dapat dilompati lewat
     * request — kartu terbit lewat alur, bukan lewat create().
     */
    private const BUKAN_ISIAN = [
        'id', 'created_at', 'updated_at',
        'status', 'diajukan_oleh', 'diajukan_pada',
        'ditinjau_oleh', 'ditinjau_pada', 'alasan_tolak',
    ];

    /** @return list<array{0:class-string<Model>,1:string}> */
    public static function model(): array
    {
        return [
            'paspor'            => [Paspor::class, 'paspor'],
            'MCU'               => [PasporMcu::class, 'paspor_mcu'],
            'induksi'           => [PasporInduksi::class, 'paspor_induksi'],
            'kartu masuk'       => [PasporKartu::class, 'paspor_kartu'],
            'unit SIMPER'       => [PasporKartuUnit::class, 'paspor_kartu_unit'],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('model')]
    public function tiap_kolom_tabel_dapat_diisi_lewat_modelnya(string $kelas, string $tabel): void
    {
        $kolom = Schema::getColumnListing($tabel);
        $fill  = (new $kelas)->getFillable();

        $hilang = array_values(array_diff($kolom, $fill, self::BUKAN_ISIAN));

        $this->assertSame([], $hilang, implode("\n", [
            "Tabel {$tabel} punya kolom yang tidak ada di \$fillable {$kelas}:",
            '  '.implode(', ', $hilang),
            '',
            'Eloquent membuang atribut yang tidak fillable TANPA GALAT: barisnya',
            'tersimpan, halamannya terbuka, dan kolomnya berisi null selamanya.',
            'Tambahkan ke $fillable, atau ke BUKAN_ISIAN bila memang tidak boleh',
            'diisi massal.',
        ]));
    }

    /**
     * Data contoh benar-benar mengisi kolom yang ditulisnya.
     *
     * Penjaga di atas memeriksa bentuk kodenya; yang ini memeriksa
     * hasilnya. Keduanya perlu: `$fillable` yang lengkap tetap tidak
     * menjamin nilainya sampai bila pemanggilnya salah nama kolom.
     */
    #[Test]
    public function data_contoh_mengisi_rantai_berkas(): void
    {
        $c = \App\Models\Company::create([
            'name' => 'PT Uji Rantai', 'doc_no_prefix' => 'UR', 'demo' => true,
        ]);

        \App\Support\DataContoh::muat($c);

        $kartu = PasporKartu::withoutGlobalScopes()
            ->whereIn('jenis', ['Mine Permit', 'Mine License'])->get();

        $this->assertNotEmpty($kartu, 'Data contoh tidak membuat satu pun kartu.');

        $this->assertGreaterThan(0,
            $kartu->whereNotNull('paspor_mcu_id')->count(),
            'Tidak ada kartu contoh yang menyebut MCU dasarnya — rantainya tidak tersimpan.');

        $this->assertGreaterThan(0,
            $kartu->whereNotNull('golongan_darah')->count(),
            'Golongan darah tidak tersimpan; kartunya tidak dapat dicetak lengkap.');

        $this->assertGreaterThan(0,
            $kartu->whereNotNull('berkas_ktp')->count(),
            'Lampiran KTP tidak tersimpan.');

        $this->assertGreaterThan(0,
            PasporMcu::withoutGlobalScopes()->whereNotNull('status_verifikasi')->count(),
            'Status verifikasi MCU tidak tersimpan.');
    }
}
