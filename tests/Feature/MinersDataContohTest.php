<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Miners\{InduksiOrang, McuOrang, Permit, Simper, SubBlok, SubKendaraan};
use App\Models\User;
use App\Support\DataContoh;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Data contoh Miners — yang diuji adalah PERBEDAANNYA, bukan jumlahnya.
 *
 * Tombol "muat data contoh" ada supaya orang dapat memeriksa apakah
 * alur dan angkanya sudah benar. Rantai MCU → Mine Permit → SIMPER
 * hanya dapat diperiksa pada sambungannya — kartu yang gugur karena
 * MCU-nya habis, SIMPER yang gugur karena SIMPOL-nya habis, permit
 * tamu yang berumur tujuh hari — dan data contoh yang seluruhnya sah
 * tidak memperlihatkan satu pun dari itu. Ia hanya memperlihatkan
 * bahwa halamannya terbuka.
 *
 * Maka yang dijaga di sini adalah bahwa keadaan-keadaan itu MASIH ADA.
 * Sunting satu tanggal di DataContoh dan seluruhnya dapat rata menjadi
 * sah tanpa satu galat pun — dan yang hilang bukan barisnya melainkan
 * kemampuan memeriksa layarnya.
 */
class MinersDataContohTest extends TestCase
{
    use RefreshDatabase;

    private Company $contoh;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contoh = Company::create(['name' => 'PT Contoh Miners', 'demo' => true]);
        User::factory()->create(['company_id' => $this->contoh->id]);

        DataContoh::muat($this->contoh->fresh(), User::factory()->create(['is_admin' => true]));
    }

    /* ═══════════ rantai dokumen ═══════════ */

    #[Test]
    public function test_tiap_permit_menyimpan_mcu_dan_induksi_yang_mendasarinya(): void
    {
        $permit = Permit::withoutGlobalScopes()->get();

        $this->assertNotEmpty($permit);

        foreach ($permit as $p) {
            $this->assertNotNull(
                $p->mcu_orang_id,
                'Permit tanpa rujukan MCU tidak dapat menjawab "hasil MCU mana yang menjadi dasar kartu ini".',
            );
            $this->assertNotNull($p->induksi_orang_id);
        }
    }

    #[Test]
    public function test_tiap_simper_menempel_pada_permit_dan_pekerja_yang_sama(): void
    {
        $simper = Simper::withoutGlobalScopes()->with('permit')->get();

        $this->assertNotEmpty($simper);

        foreach ($simper as $s) {
            $this->assertNotNull($s->permit);
            $this->assertSame(
                $s->pekerja_id,
                $s->permit->pekerja_id,
                'SIMPER dan permitnya menunjuk orang yang berbeda.',
            );
        }
    }

    /* ═══════════ keadaan yang harus tetap berbeda ═══════════ */

    #[Test]
    public function test_ada_kartu_yang_gugur_karena_mcu_habis_lebih_dahulu(): void
    {
        $gugur = Permit::withoutGlobalScopes()->with('mcuOrang')->get()
            ->filter(fn (Permit $p) => $p->gugurKarenaMcu());

        $this->assertNotEmpty(
            $gugur,
            'Tidak ada satu pun kartu yang gugur karena MCU. Aturan SOP "MCU ulang lewat tenggat → '
            .'permit dicabut tanpa toleransi" tidak dapat diperiksa di layar mana pun.',
        );

        /* Dan tanggalnya sendiri BELUM lewat — justru itu intinya.
           Kartu yang tanggalnya sudah lewat gugur karena alasan yang
           biasa, dan tidak memperlihatkan apa-apa tentang MCU. */
        foreach ($gugur as $p) {
            $this->assertTrue(
                $p->berlaku_sampai->gte(now()->startOfDay()),
                'Kartu yang gugur karena MCU seharusnya tanggalnya sendiri belum lewat.',
            );
        }
    }

    #[Test]
    public function test_ada_mcu_yang_masuk_ambang_peringatan_dan_ada_yang_belum(): void
    {
        $mcu = McuOrang::all();

        $this->assertNotEmpty($mcu->filter(fn (McuOrang $m) => $m->mendekatiHabis()),
            'Tidak ada MCU yang mendekati habis; ambang peringatan dua minggu tidak terlihat di layar mana pun.');

        $this->assertNotEmpty($mcu->filter(fn (McuOrang $m) => $m->kedaluwarsa()),
            'Tidak ada MCU yang sudah lewat.');

        $this->assertNotEmpty($mcu->filter(fn (McuOrang $m) => ! $m->kedaluwarsa() && ! $m->mendekatiHabis()),
            'Seluruh MCU bermasalah; tidak ada pembanding yang sehat.');
    }

    #[Test]
    public function test_ada_simper_yang_masa_berlakunya_ditentukan_simpol(): void
    {
        $sebab = Simper::withoutGlobalScopes()->with(['permit.mcuOrang'])->get()
            ->map(fn (Simper $s) => $s->penyebabHabis());

        $this->assertContains(
            'simpol',
            $sebab->all(),
            'Tidak ada SIMPER yang dihentikan SIMPOL-nya. Aturan SOP "SIMPOL habis → SIMPER otomatis '
            .'tidak berlaku" tidak dapat diperiksa — dan SIMPOL yang pendek pada kartu yang permitnya '
            .'sudah lebih pendek lagi akan selalu kalah, sehingga aturannya tidak pernah terlihat.',
        );

        $this->assertContains('permit', $sebab->all());
    }

    #[Test]
    public function test_permit_tamu_masih_berada_dalam_jendela_tujuh_hari(): void
    {
        $tamu = Permit::withoutGlobalScopes()->with('tipe')->get()
            ->first(fn (Permit $p) => $p->tipe?->hari_berlaku !== null);

        $this->assertNotNull($tamu, 'Tidak ada permit berjangka hari; jenis Visitor/Temporary tidak terlihat.');

        $this->assertSame('tipe', $tamu->sumber_berlaku);

        $this->assertGreaterThanOrEqual(
            0,
            $tamu->sisaHari(),
            'Permit tamu sudah kedaluwarsa sejak dimuat, sehingga jendela tujuh harinya tidak pernah tergambar.',
        );
        $this->assertLessThanOrEqual((int) $tamu->tipe->hari_berlaku, $tamu->sisaHari());
    }

    #[Test]
    public function test_ada_peserta_induksi_yang_belum_lulus(): void
    {
        $orang = InduksiOrang::all();

        $this->assertNotEmpty($orang->filter(fn (InduksiOrang $i) => ! $i->lulus()),
            'Seluruh peserta induksi lulus; layar remidi tidak punya satu baris pun.');

        $this->assertNotEmpty($orang->filter(fn (InduksiOrang $i) => $i->lulus()));

        /* Catatannya harus cocok dengan keadaannya. Catatan "lulus pada
           percobaan kedua" pada baris yang berstatus remidi adalah
           keterangan yang membantah datanya sendiri — dan yang dibaca
           orang adalah catatannya. */
        foreach ($orang->filter(fn (InduksiOrang $i) => $i->percobaan > 1) as $i) {
            $this->assertStringNotContainsStringIgnoringCase('lulus', (string) $i->catatan);
        }
    }

    #[Test]
    public function test_warna_kartu_cocok_dengan_ada_tidaknya_simper(): void
    {
        $permit = Permit::withoutGlobalScopes()->with('simper')->get();

        $putih = $permit->filter(fn (Permit $p) => $p->kode_warna === 'putih');
        $merah = $permit->filter(fn (Permit $p) => $p->kode_warna === 'merah');

        $this->assertNotEmpty($putih, 'Tidak ada kartu putih — pemegang Mine Permit tanpa SIMPER.');
        $this->assertNotEmpty($merah, 'Tidak ada kartu merah — operator unit A2B.');

        /* ARTINYA yang diperiksa, bukan sekadar adanya nama warnanya di
           kolom. Putih berarti "tidak mengendarai unit" menurut
           004-SPM-006 butir 7–8; kartu putih yang pemiliknya memegang
           SIMPER adalah pernyataan yang salah di hadapan petugas pos —
           dan memeriksa daftar warna saja tidak pernah menangkapnya,
           sebab warnanya memang ada, hanya pada orang yang keliru. */
        foreach ($putih as $p) {
            $this->assertCount(
                0,
                $p->simper,
                'Kartu putih pada pemegang SIMPER: petugas pos membacanya sebagai "tidak mengendarai unit".',
            );
        }

        foreach ($merah as $p) {
            $this->assertNotEmpty(
                $p->simper,
                'Kartu merah tanpa satu pun SIMPER: kewenangan A2B yang tidak pernah diberikan.',
            );
        }
    }

    #[Test]
    public function test_sertifikat_berjam_tetap_terjaring_daftar_akan_habis(): void
    {
        /* Baris yang TERLANJUR tersimpan dengan jam — ditulis sebelum
           tanggalnya dipangkas, atau oleh impor dari luar. MySQL
           memangkasnya di tingkat kolom, SQLite tidak, jadi keadaan ini
           hanya ada di sebagian pemasangan. Justru karena itu ia harus
           tetap terjaring: daftar "akan habis" yang melewatkannya
           menyembunyikan sertifikat yang paling mendesak diperbarui,
           dan hanya pada sebagian pemasangan — kegagalan yang tidak
           dapat ditiru orang yang melaporkannya.

           Ditulis lewat query builder, bukan lewat model: modelnya
           justru yang memangkas, sehingga menulis lewat sana tidak akan
           pernah menghasilkan keadaan yang hendak diuji. */
        $pekerja = \App\Models\Miners\Pekerja::withoutGlobalScopes()->first();

        DB::table('mnr_kompetensi')->insert([
            'company_id'     => $this->contoh->id,
            'pekerja_id'     => $pekerja->id,
            'nama'           => 'Sertifikat Warisan',
            'berlaku_sampai' => now()->startOfDay()->addDays(30)->format('Y-m-d').' 22:15:09',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $ketemu = \App\Models\Miners\Kompetensi::withoutGlobalScopes()
            ->akanHabis()->pluck('nama');

        $this->assertContains(
            'Sertifikat Warisan',
            $ketemu->all(),
            'Sertifikat yang habis tepat pada hari ke-30 terlewat karena jam tersimpannya melewati tengah malam.',
        );
    }

    /* ═══════════ alur ═══════════ */

    #[Test]
    public function test_tiap_dokumen_punya_alur_selengkap_langkahnya(): void
    {
        foreach ([
            'permit' => Permit::class,
            'simper' => Simper::class,
        ] as $jenis => $kelas) {
            foreach ($kelas::withoutGlobalScopes()->with('alur')->get() as $d) {
                $this->assertCount(
                    count(\App\Models\Miners\Alur::LANGKAH[$jenis]),
                    $d->alur,
                    class_basename($kelas).' #'.$d->id.' punya alur yang tidak selengkap langkahnya.',
                );
            }
        }
    }

    #[Test]
    public function test_tiap_antrean_punya_setidaknya_satu_dokumen_yang_menunggu(): void
    {
        /* DIPERIKSA PER JENIS DOKUMEN, bukan sebagai satu hitungan.
           Satu hitungan menyeluruh tetap menjawab "ada yang menunggu"
           selama satu jenis saja masih punya antrean — sehingga
           seluruh Mine Permit dapat dibuat tuntas tanpa satu pun uji
           yang berubah, dan layar "Outstanding Mine Permit" tampil
           kosong pada data contoh yang justru dibuat untuk
           memeriksanya. */
        foreach (['mcu', 'induksi', 'permit', 'ajuan'] as $jenis) {
            $this->assertGreaterThan(
                0,
                DB::table('mnr_alur')->where('dokumen', $jenis)->where('keadaan', 'menunggu')->count(),
                "Tidak ada {$jenis} yang menunggu tindakan; layar antreannya kosong dan tidak dapat diperiksa.",
            );

            $this->assertGreaterThan(
                0,
                DB::table('mnr_alur')->where('dokumen', $jenis)->where('keadaan', 'setuju')->count(),
                "Tidak ada {$jenis} yang sudah disetujui; tidak ada pembanding bagi yang menunggu.",
            );
        }
    }

    #[Test]
    public function test_langkah_yang_sudah_ditindak_mencatat_siapa_dan_kapan(): void
    {
        $tuntas = DB::table('mnr_alur')->where('keadaan', 'setuju')->get();

        $this->assertNotEmpty($tuntas);

        foreach ($tuntas as $l) {
            $this->assertNotNull($l->user_id, 'Langkah disetujui tanpa siapa yang menyetujuinya.');
            $this->assertNotNull($l->bertindak_pada);
        }

        foreach (DB::table('mnr_alur')->where('keadaan', 'menunggu')->get() as $l) {
            $this->assertNull($l->user_id, 'Langkah yang masih menunggu sudah tercatat penindaknya.');
            $this->assertNull($l->bertindak_pada);
        }
    }

    /* ═══════════ batas perusahaan ═══════════ */

    #[Test]
    public function test_daftar_milik_perusahaan_sendiri_tidak_menumpang_induk_bersama(): void
    {
        /* Sub-blok dan rincian golongan yang dibuat sebuah perusahaan
           harus berkolom pemilik sendiri. Menumpang induknya, batasnya
           tidak membatasi apa pun: induknya justru yang TIDAK punya
           pemilik, sebab ia acuan bersama. */
        foreach (SubBlok::withoutGlobalScopes()->get() as $s) {
            $this->assertSame($this->contoh->id, $s->company_id);
        }

        /* Rincian golongan acuan tetap tanpa pemilik — ia berasal dari
           SOP, bukan dari salah satu tambang. */
        foreach (SubKendaraan::withoutGlobalScopes()->get() as $s) {
            $this->assertNull($s->company_id);
        }
    }

    #[Test]
    public function test_dimuat_ulang_tidak_menumpuk(): void
    {
        $sebelum = $this->hitung();

        DataContoh::muat($this->contoh->fresh(), User::factory()->create(['is_admin' => true]));

        $this->assertSame($sebelum, $this->hitung());
    }

    #[Test]
    public function test_langkah_alur_ikut_terbuang_saat_dimuat_ulang(): void
    {
        /* Alur berelasi polimorfik lewat sepasang kolom, jadi tidak ada
           kaskade kunci asing yang membuangnya bersama dokumennya.
           Dilewatkan pada pembuangan, tiap penekanan tombol
           meninggalkan satu rombongan langkah yatim — dan jumlahnya
           bertambah tiap kali tanpa satu galat pun. */
        $sebelum = DB::table('mnr_alur')->count();
        $this->assertGreaterThan(0, $sebelum);

        DataContoh::muat($this->contoh->fresh(), User::factory()->create(['is_admin' => true]));

        $this->assertSame($sebelum, DB::table('mnr_alur')->count());

        $yatim = DB::table('mnr_alur')
            ->whereNotIn('dokumen_id', DB::table('mnr_permit')->select('id'))
            ->where('dokumen', 'permit')
            ->count();

        $this->assertSame(0, $yatim, 'Ada langkah persetujuan yang menunjuk permit yang sudah tidak ada.');
    }

    /** @return array<string,int> */
    private function hitung(): array
    {
        $n = [];

        foreach (['mnr_pekerja', 'mnr_mcu', 'mnr_mcu_orang', 'mnr_mcu_rujukan',
                  'mnr_induksi', 'mnr_induksi_orang', 'mnr_permit', 'mnr_permit_berkas',
                  'mnr_simper', 'mnr_simper_unit', 'mnr_simper_ajuan', 'mnr_simper_ajuan_unit',
                  'mnr_alur', 'mnr_sub_blok', 'mnr_pjo', 'mnr_subkontraktor'] as $t) {
            $n[$t] = (int) DB::table($t)->count();
        }

        return $n;
    }
}
