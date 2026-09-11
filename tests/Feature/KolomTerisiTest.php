<?php

namespace Tests\Feature;

use App\Models\Miners\{InduksiOrang, McuOrang, Pekerja, Permit, Simper, SimperUnit};
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
            'pekerja'           => [Pekerja::class, 'mnr_pekerja'],
            'MCU'               => [McuOrang::class, 'mnr_mcu_orang'],
            'induksi'           => [InduksiOrang::class, 'mnr_induksi_orang'],
            'Mine Permit'       => [Permit::class, 'mnr_permit'],
            'SIMPER'            => [Simper::class, 'mnr_simper'],
            'unit SIMPER'       => [SimperUnit::class, 'mnr_simper_unit'],
        ];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('model')]
    public function tiap_kolom_tabel_dapat_diisi_lewat_modelnya(string $kelas, string $tabel): void
    {
        $kolom = Schema::getColumnListing($tabel);
        $m     = new $kelas;

        /* DUA CARA MENYATAKAN HAL YANG SAMA, dan keduanya sah.
         *
         * `$fillable` menyebut yang BOLEH diisi; `$guarded` menyebut
         * yang TIDAK boleh, dan sisanya boleh. Memeriksa `$fillable`
         * saja membuat tiap model ber-`$guarded` gagal dengan menyebut
         * SELURUH kolomnya "hilang" — laporan yang menuduh kesalahan
         * yang tidak ada, pada model yang justru lebih longgar.
         *
         * Yang dijaga tetap satu: tidak ada kolom yang diam-diam
         * dibuang Eloquent. Bagi model ber-`$guarded`, itu berarti
         * kolomnya tidak boleh ikut terdaftar sebagai terlarang. */
        $fill    = $m->getFillable();
        $guarded = $m->getGuarded();

        $hilang = $fill === []
            ? array_values(array_intersect($kolom, $guarded))
            : array_values(array_diff($kolom, $fill, self::BUKAN_ISIAN));

        /* `$guarded = ['*']` menutup semuanya — itu sama saja dengan
           `$fillable` kosong, dan memang harus gagal. */
        if ($fill === [] && in_array('*', $guarded, true)) {
            $hilang = array_values(array_diff($kolom, self::BUKAN_ISIAN));
        }

        $hilang = array_values(array_diff($hilang, self::BUKAN_ISIAN));

        $this->assertSame([], $hilang, implode("\n", [
            "Tabel {$tabel} punya kolom yang tidak dapat diisi lewat {$kelas}:",
            '  '.implode(', ', $hilang),
            '',
            'Eloquent membuang atribut yang tidak fillable TANPA GALAT: barisnya',
            'tersimpan, halamannya terbuka, dan kolomnya berisi null selamanya.',
            'Tambahkan ke $fillable — atau keluarkan dari $guarded — atau ke',
            'BUKAN_ISIAN bila memang tidak boleh diisi massal.',
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

        $kartu = Permit::withoutGlobalScopes()->get();

        $this->assertNotEmpty($kartu, 'Data contoh tidak membuat satu pun Mine Permit.');

        $this->assertGreaterThan(0,
            $kartu->whereNotNull('mcu_orang_id')->count(),
            'Tidak ada kartu contoh yang menyebut MCU dasarnya — rantainya tidak tersimpan, '
            .'dan pertanyaan "hasil MCU mana yang menjadi dasar kartu ini" kehilangan jawabannya.');

        $this->assertGreaterThan(0,
            $kartu->whereNotNull('induksi_orang_id')->count(),
            'Tidak ada kartu contoh yang menyebut induksi dasarnya.');

        $this->assertGreaterThan(0,
            Pekerja::withoutGlobalScopes()->whereNotNull('gol_darah')->count(),
            'Golongan darah tidak tersimpan; kartunya tidak dapat dicetak lengkap.');

        $this->assertGreaterThan(0,
            McuOrang::query()->whereNotNull('hasil_id')->count(),
            'Hasil MCU tidak tersimpan.');

        $this->assertGreaterThan(0,
            Simper::withoutGlobalScopes()->whereNotNull('permit_id')->count(),
            'SIMPER contoh tidak menempel pada Mine Permit mana pun.');
    }
}
