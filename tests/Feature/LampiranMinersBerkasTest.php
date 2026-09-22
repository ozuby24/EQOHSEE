<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Miners\{Induksi, InduksiOrang, Mcu, McuOrang, McuRujukan, Pekerja,
    Permit, PermitBerkas, Simper};
use App\Models\User;
use App\Support\Berkas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Lampiran modul Miners dapat dibuka kembali.
 *
 * Kedelapan jenis lampiran Miners sudah lama diunggah dan disimpan di
 * disk tertutup, tetapi tidak pernah didaftarkan di Berkas::TERSAJI.
 * Akibatnya tidak ada satu pun alamat yang dapat menyajikannya:
 * berkasnya masuk, tersimpan, lalu tidak dapat dibuka lagi oleh siapa
 * pun — termasuk oleh yang mengunggahnya. Layar Mine Permit bahkan
 * menampilkan centang "ada" bagi lampiran yang tidak dapat ia buka.
 *
 * Yang dijaga di sini karena itu BUKAN sekadar isi daftarnya melainkan
 * perjalanan penuhnya: unggah, lalu buka kembali lewat alamatnya.
 * Daftar yang benar tetapi tanpa rute yang bekerja adalah keadaan yang
 * baru saja diperbaiki.
 *
 * Ditambah gerbangnya: surat dari klinik memuat diagnosis dan hasil
 * laboratorium, sedangkan kolom di basis data sengaja hanya menyimpan
 * kesimpulan kelayakannya. Lampiran yang terbuka bagi setiap pengguna
 * membatalkan pemisahan itu lewat pintu belakang.
 */
class LampiranMinersBerkasTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Berkas::TERTUTUP);

        $this->c = Company::create(['name' => 'PT Uji Lampiran', 'code' => 'PUL']);
    }

    private function pengguna(array $x = []): User
    {
        return User::factory()->create(array_merge([
            'is_admin' => false, 'company_id' => $this->c->id,
        ], $x));
    }

    private function pekerja(): Pekerja
    {
        return Pekerja::withoutGlobalScopes()->create([
            'company_id' => $this->c->id,
            'nama' => 'Pekerja Uji', 'nik' => 'NIK-1', 'status' => 'aktif',
        ]);
    }

    private function mcuOrang(): McuOrang
    {
        $surat = Mcu::withoutGlobalScopes()->create([
            'company_id' => $this->c->id,
            'tanggal' => now()->startOfDay(), 'kepada' => 'Klinik', 'status' => 'selesai',
        ]);

        return McuOrang::create([
            'mcu_id' => $surat->id, 'pekerja_id' => $this->pekerja()->id,
            'nama' => 'Pekerja Uji', 'aktif' => true,
        ]);
    }

    /* ═══════════ daftar dan gerbangnya ═══════════ */

    /**
     * Kedelapan jenis Miners terdaftar dan menunjuk kolom yang ADA.
     *
     * Kolomnya diperiksa lewat skema, bukan dipercaya dari daftarnya:
     * jenis yang menunjuk kolom salah eja tidak memulangkan galat saat
     * didaftarkan — ia baru gagal ketika seseorang mencoba membuka
     * berkasnya, yaitu saat auditor memintanya.
     */
    public function test_jenis_miners_terdaftar_dan_kolomnya_ada(): void
    {
        $tersaji = Berkas::tersaji();

        foreach (['mnh', 'mnk', 'mnz', 'mnj', 'mns', 'mnd', 'mnp', 'mnl'] as $jenis) {
            $this->assertArrayHasKey($jenis, $tersaji, "Jenis {$jenis} tidak terdaftar.");

            [$kelas, $kolom] = $tersaji[$jenis];
            $model = new $kelas();

            $this->assertTrue(
                \Schema::hasColumn($model->getTable(), $kolom),
                "Jenis {$jenis} menunjuk kolom {$model->getTable()}.{$kolom} yang tidak ada."
            );
        }
    }

    /**
     * Tiap model yang didaftarkan punya batas perusahaan.
     *
     * BerkasController tidak menulis pemeriksaan kepemilikannya sendiri
     * — ia bersandar pada findOrFail di bawah scope modelnya. Model
     * tanpa batas perusahaan karena itu membuat berkasnya terbaca lintas
     * perusahaan tanpa satu pun baris kode yang tampak salah.
     */
    public function test_model_miners_yang_didaftarkan_berbatas_perusahaan(): void
    {
        $tersaji = Berkas::tersaji();

        foreach (['mnh', 'mnk', 'mnz', 'mnj', 'mns', 'mnd', 'mnp', 'mnl'] as $jenis) {
            $kelas = $tersaji[$jenis][0];
            $model = new $kelas();

            $berbatas = in_array(\App\Models\Concerns\BerindukPerusahaan::class,
                    class_uses_recursive($kelas), true)
                || $model->hasGlobalScope(\App\Models\Scopes\MilikPerusahaan::class);

            $this->assertTrue($berbatas,
                "{$kelas} tidak punya batas perusahaan; berkasnya akan terbaca lintas perusahaan.");
        }
    }

    /** Lampiran medis Miners dijaga seketat surat MCU lama. */
    public function test_lampiran_medis_miners_dijaga(): void
    {
        $biasa     = $this->pengguna();
        $paramedis = $this->pengguna(['ohse_role' => 'paramedis']);
        $ohse      = $this->pengguna(['ohse_role' => 'ohse']);
        $admin     = $this->pengguna(['is_admin' => true]);

        foreach (['mnh', 'mnk', 'mnz', 'mnj', 'mnl'] as $jenis) {
            $this->assertFalse(Berkas::bolehMembuka($biasa, $jenis),
                "Jenis {$jenis} terbuka bagi pengguna biasa.");

            foreach ([$paramedis, $ohse, $admin] as $u) {
                $this->assertTrue(Berkas::bolehMembuka($u, $jenis));
            }
        }
    }

    /**
     * SIM kepolisian dijaga, tetapi TIDAK sampai paramedis.
     *
     * Ia dokumen identitas — memuat NIK dan alamat rumah — bukan
     * dokumen medis. Menyamakan gerbangnya dengan surat MCU akan
     * memberi paramedis akses ke data yang bukan urusannya, dan
     * gerbang yang terlalu longgar sama merugikannya dengan yang
     * terlalu ketat.
     */
    public function test_sim_polisi_dijaga_tanpa_paramedis(): void
    {
        $this->assertFalse(Berkas::bolehMembuka($this->pengguna(), 'mnp'));
        $this->assertFalse(Berkas::bolehMembuka($this->pengguna(['ohse_role' => 'paramedis']), 'mnp'));
        $this->assertTrue(Berkas::bolehMembuka($this->pengguna(['ohse_role' => 'ohse']), 'mnp'));
        $this->assertTrue(Berkas::bolehMembuka($this->pengguna(['is_admin' => true]), 'mnp'));
    }

    /** Sertifikat dan daftar hadir induksi memang tidak dijaga peran. */
    public function test_lampiran_induksi_tidak_dijaga(): void
    {
        foreach (['mns', 'mnd'] as $jenis) {
            $this->assertTrue(Berkas::bolehMembuka($this->pengguna(), $jenis));
        }
    }

    /* ═══════════ perjalanan penuh: unggah lalu buka ═══════════ */

    /**
     * Surat hasil MCU yang diunggah dapat dibuka kembali.
     *
     * Inilah yang dahulu MUSTAHIL: berkasnya tersimpan, dan tidak ada
     * satu pun alamat yang dapat menyajikannya.
     */
    public function test_surat_mcu_yang_diunggah_dapat_dibuka_kembali(): void
    {
        $orang = $this->mcuOrang();

        $this->actingAs($this->pengguna(['is_admin' => true]))
            ->post("/miners/mcu/{$orang->mcu_id}/orang/{$orang->id}/hasil", [
                'tanggal_periksa' => now()->toDateString(),
                'berkas_hasil'    => UploadedFile::fake()->create('mcu.pdf', 12, 'application/pdf'),
            ])->assertRedirect();

        $orang->refresh();

        /* Kolomnya memuat JALUR, bukan objek unggahan. Pembedaan itu
           tidak sia-sia: aturan validasi yang ditambahkan bersama
           perbaikan ini membuat berkasnya ikut ke data tervalidasi, dan
           pada `$data + [...]` ruas kiri yang menang — objek unggahan
           akan menimpa jalurnya tanpa satu pun galat. */
        $this->assertIsString($orang->berkas_hasil);
        Storage::disk(Berkas::TERTUTUP)->assertExists($orang->berkas_hasil);

        $this->get("/berkas/mnh/{$orang->id}")->assertOk();
    }

    /** Pengguna biasa ditolak membuka surat MCU yang sama. */
    public function test_pengguna_biasa_ditolak_membuka_surat_mcu(): void
    {
        $orang = $this->mcuOrang();

        $this->actingAs($this->pengguna(['is_admin' => true]))
            ->post("/miners/mcu/{$orang->mcu_id}/orang/{$orang->id}/hasil", [
                'tanggal_periksa' => now()->toDateString(),
                'berkas_hasil'    => UploadedFile::fake()->create('mcu.pdf', 12, 'application/pdf'),
            ]);

        $this->actingAs($this->pengguna())
            ->get("/berkas/mnh/{$orang->id}")
            ->assertForbidden();
    }

    /** Berkas berakhiran .php ditolak sebelum tersimpan. */
    public function test_unggahan_yang_dapat_dieksekusi_ditolak(): void
    {
        $orang = $this->mcuOrang();

        $this->actingAs($this->pengguna(['is_admin' => true]))
            ->post("/miners/mcu/{$orang->mcu_id}/orang/{$orang->id}/hasil", [
                'tanggal_periksa' => now()->toDateString(),
                'berkas_hasil'    => UploadedFile::fake()->create('jahat.php', 4),
            ])->assertSessionHasErrors('berkas_hasil');

        $this->assertNull($orang->refresh()->berkas_hasil);
    }

    /**
     * Unggahan yang melampaui batas ditolak.
     *
     * Sebelum perbaikan ini ketiga medan MCU tidak divalidasi sama
     * sekali: Berkas::simpan() dipanggil atas berkas mentah. Ia memang
     * menolak akhiran yang dapat dieksekusi, tetapi tidak membatasi
     * ukuran maupun jenis.
     */
    public function test_unggahan_terlalu_besar_ditolak(): void
    {
        $orang = $this->mcuOrang();

        $this->actingAs($this->pengguna(['is_admin' => true]))
            ->post("/miners/mcu/{$orang->mcu_id}/orang/{$orang->id}/hasil", [
                'tanggal_periksa' => now()->toDateString(),
                'berkas_hasil'    => UploadedFile::fake()
                    ->create('besar.pdf', Berkas::MAKS_DOKUMEN_KB + 1024, 'application/pdf'),
            ])->assertSessionHasErrors('berkas_hasil');

        $this->assertNull($orang->refresh()->berkas_hasil);
    }

    /* ═══════════ keadaan lampiran bagi layar ═══════════ */

    /**
     * `ada` dan `url` dipisah supaya layar tidak berbohong.
     *
     * Digabung menjadi satu url yang null, layar tidak dapat lagi
     * membedakan "belum diunggah" dari "tidak boleh Anda buka" — yang
     * pertama menuntut tindakan, yang kedua tidak.
     */
    public function test_ada_dan_url_dipisah(): void
    {
        $orang = $this->mcuOrang();

        $kosong = Berkas::lampiran($this->pengguna(['is_admin' => true]), $orang, 'mnh');
        $this->assertFalse($kosong['ada']);
        $this->assertNull($kosong['url']);

        $orang->update(['berkas_hasil' => 'miners/mcu/ada.pdf']);

        $terjaga = Berkas::lampiran($this->pengguna(), $orang, 'mnh');
        $this->assertTrue($terjaga['ada'], 'Berkasnya ada, hanya tidak boleh dibuka pengguna ini.');
        $this->assertNull($terjaga['url']);

        $boleh = Berkas::lampiran($this->pengguna(['is_admin' => true]), $orang, 'mnh');
        $this->assertTrue($boleh['ada']);
        $this->assertNotNull($boleh['url']);
    }

    /** Lampiran permit tidak lagi menampilkan centang tanpa alamat. */
    public function test_lampiran_permit_membawa_alamatnya(): void
    {
        $p = Permit::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'pekerja_id' => $this->pekerja()->id,
            'no_registrasi' => 'MP/1', 'tanggal' => now()->startOfDay(), 'status' => 'terbit',
        ]);

        $b = PermitBerkas::create([
            'permit_id' => $p->id, 'jenis' => 'SIO', 'berkas' => 'miners/permit/sio.pdf',
        ]);

        $l = Berkas::lampiran($this->pengguna(['is_admin' => true]), $b, 'mnl');

        $this->assertTrue($l['ada']);
        $this->assertNotNull($l['url'], 'Centang "ada" tanpa alamat adalah keadaan yang diperbaiki.');
    }

    /**
     * Setiap jenis Miners punya penyaji yang bekerja.
     *
     * Diperiksa dengan benar-benar memanggil alamatnya, bukan dengan
     * memeriksa daftarnya: daftar yang benar tetapi salah menyebut nama
     * kolom tetap lolos pemeriksaan daftar, dan baru gagal saat
     * berkasnya diminta.
     */
    public function test_tiap_jenis_miners_dapat_disajikan(): void
    {
        $admin = $this->pengguna(['is_admin' => true]);
        $orang = $this->mcuOrang();

        $induksi = Induksi::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'tanggal' => now()->startOfDay(), 'status' => 'selesai',
        ]);
        $indOrang = InduksiOrang::create([
            'induksi_id' => $induksi->id, 'pekerja_id' => $orang->pekerja_id,
        ]);

        $permit = Permit::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'pekerja_id' => $orang->pekerja_id,
            'no_registrasi' => 'MP/2', 'tanggal' => now()->startOfDay(), 'status' => 'terbit',
        ]);

        $baris = [
            'mnh' => [$orang, 'berkas_hasil'],
            'mnk' => [$orang, 'berkas_rekomendasi'],
            'mnz' => [$orang, 'berkas_napza'],
            'mnj' => [McuRujukan::create([
                'mcu_orang_id' => $orang->id, 'tanggal_surat' => now()->startOfDay(),
                'dokter' => 'dr. Uji',
            ]), 'berkas'],
            'mns' => [$indOrang, 'berkas_sertifikat'],
            'mnd' => [$indOrang, 'berkas_hadir'],
            'mnp' => [Simper::withoutGlobalScopes()->create([
                'company_id' => $this->c->id, 'pekerja_id' => $orang->pekerja_id,
                'permit_id' => $permit->id, 'tanggal' => now()->startOfDay(),
                'kelas' => array_key_first(Simper::KELAS), 'status' => 'terbit',
            ]), 'berkas_simpol'],
            'mnl' => [PermitBerkas::create([
                'permit_id' => $permit->id, 'jenis' => 'SIO',
            ]), 'berkas'],
        ];

        foreach ($baris as $jenis => [$model, $kolom]) {
            $jalur = "miners/uji/{$jenis}.pdf";
            Storage::disk(Berkas::TERTUTUP)->put($jalur, 'isi');
            $model->forceFill([$kolom => $jalur])->save();

            $this->actingAs($admin)
                ->get("/berkas/{$jenis}/{$model->id}")
                ->assertOk();
        }
    }
}
