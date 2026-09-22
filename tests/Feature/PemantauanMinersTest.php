<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Miners\{Mcu, McuOrang, Pekerja, Permit, Simper};
use App\Models\User;
use App\Support\Authority;
use App\Support\Miners\PemantauanMiners as PM;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Pemantauan berkas kelayakan di atas data Miners.
 *
 * Yang dijaga di sini pertanyaan gerbang: siapa yang hari ini tidak
 * boleh masuk. Angka yang salah di sini tidak memulangkan galat — ia
 * meloloskan orang yang berkasnya sudah habis, atau menahan orang yang
 * berkasnya sebenarnya masih berlaku.
 *
 * Ditambah satu hal yang menjadi alasan kelas ini ada sama sekali:
 * dasbor dan modul Miners harus menyebut angka tenaga kerja yang SAMA.
 * Sebelumnya tidak — dasbor membaca tabel `paspor` lama dan menyebut
 * 35, modul Miners membaca `mnr_pekerja` dan menyebut 42, pada dua
 * layar yang dibuka berurutan.
 */
class PemantauanMinersTest extends TestCase
{
    use RefreshDatabase;

    private static int $n = 0;

    private Company $c;

    protected function setUp(): void
    {
        parent::setUp();

        $this->c = Company::create(['name' => 'PT Uji Pantau', 'code' => 'PPT']);
    }

    private function masuk(): User
    {
        $u = User::factory()->create(['is_admin' => true, 'company_id' => $this->c->id]);
        $this->actingAs($u);

        return $u;
    }

    private function pekerja(array $x = []): Pekerja
    {
        $i = ++self::$n;

        return Pekerja::withoutGlobalScopes()->create(array_merge([
            'company_id' => $this->c->id,
            'nama'       => "Pekerja {$i}",
            'nik'        => "NIK{$i}",
            'status'     => 'aktif',
        ], $x));
    }

    private function mcu(Pekerja $p, ?string $habis, array $x = []): McuOrang
    {
        $surat = Mcu::withoutGlobalScopes()->create([
            'company_id' => $this->c->id,
            'tanggal'    => now()->startOfDay(),
            'kepada'     => 'Klinik',
            'status'     => 'selesai',
        ]);

        return McuOrang::create(array_merge([
            'mcu_id'          => $surat->id,
            'pekerja_id'      => $p->id,
            'nama'            => $p->nama,
            'tanggal_periksa' => now()->startOfDay()->subDays(10),
            'berlaku_sampai'  => $habis,
            'aktif'           => true,
        ], $x));
    }

    private function permit(Pekerja $p, ?McuOrang $m, ?string $habis, string $status = 'terbit'): Permit
    {
        return Permit::withoutGlobalScopes()->create([
            'company_id'    => $this->c->id,
            'pekerja_id'    => $p->id,
            'mcu_orang_id'  => $m?->id,
            'no_registrasi' => 'MP/'.(++self::$n),
            'tanggal'       => now()->startOfDay()->subDays(5),
            'berlaku_sampai'=> $habis,
            'status'        => $status,
        ]);
    }

    /** @return array{0:array<int,array<string,mixed>>,1:array<string,mixed>} */
    private function jalankan(): array
    {
        $baris = PM::baris(PM::muat());

        return [$baris, PM::ringkas($baris)];
    }

    /* ══════════════ jumlah orang ══════════════ */

    /**
     * Manpower menghitung ORANG, bukan berkas.
     *
     * Satu orang memegang MCU, Mine Permit, dan kerap SIMPER pula.
     * Menghitung barisnya membuat tiga pekerja terbaca sebagai sembilan
     * tenaga kerja — dan angka itu dipakai memperkirakan mandays audit.
     */
    public function test_manpower_menghitung_orang_bukan_berkas(): void
    {
        $this->masuk();

        foreach (range(1, 3) as $_) {
            $p = $this->pekerja();
            $m = $this->mcu($p, now()->addYear()->toDateString());
            $this->permit($p, $m, now()->addMonths(6)->toDateString());
        }

        [$baris, $r] = $this->jalankan();

        $this->assertSame(6, count($baris), 'Tiga orang × dua berkas seharusnya enam baris.');
        $this->assertSame(3, $r['manpower']);
        $this->assertSame(3, $r['manpowerAktif']);
    }

    /** Orang yang sudah keluar terhitung manpower tetapi bukan yang aktif. */
    public function test_orang_nonaktif_terpisah_dari_yang_aktif(): void
    {
        $this->masuk();

        $a = $this->pekerja();
        $this->mcu($a, now()->addYear()->toDateString());

        $b = $this->pekerja(['status' => 'resign']);
        $this->mcu($b, now()->addYear()->toDateString());

        [, $r] = $this->jalankan();

        $this->assertSame(2, $r['manpower']);
        $this->assertSame(1, $r['manpowerAktif']);
        $this->assertSame(1, $r['manpowerNonaktif']);
    }

    /** Pekerja tanpa satu berkas pun tidak menghasilkan baris. */
    public function test_pekerja_tanpa_berkas_tidak_berbaris(): void
    {
        $this->masuk();
        $this->pekerja();

        [$baris, $r] = $this->jalankan();

        $this->assertSame([], $baris);
        $this->assertSame(0, $r['manpower']);
    }

    /* ══════════════ MCU ══════════════ */

    /**
     * MCU dihitung dari yang TERAKHIR saja.
     *
     * Pemeriksaan tahun lalu memang sudah habis masa berlakunya.
     * Menghitung seluruh riwayatnya membuat orang yang paling rajin MCU
     * tampak paling bermasalah — persis kebalikan dari yang sebenarnya.
     */
    public function test_hanya_mcu_terakhir_yang_dipantau(): void
    {
        $this->masuk();
        $p = $this->pekerja();

        $this->mcu($p, now()->subYear()->toDateString(), [
            'tanggal_periksa' => now()->subYears(2)->startOfDay(),
        ]);
        $this->mcu($p, now()->addYear()->toDateString(), [
            'tanggal_periksa' => now()->subDays(3)->startOfDay(),
        ]);

        [$baris, $r] = $this->jalankan();

        $this->assertCount(1, $baris, 'Seluruh riwayat MCU ikut terpantau.');
        $this->assertSame(PM::MCU, $baris[0]['jenis']);
        $this->assertSame(Authority::PANJANG, $baris[0]['keadaan']);
        $this->assertSame(0, $r['habis']);
    }

    /* ══════════════ kartu yang belum terbit ══════════════ */

    /**
     * Draf dan kartu yang dicabut bukan izin.
     *
     * Menghitungnya sebagai kartu aktif membuat jumlah pemegang izin
     * lebih besar daripada yang sebenarnya boleh masuk — dan selisih
     * itu justru pada orang-orang yang berkasnya belum beres.
     */
    public function test_kartu_selain_terbit_tidak_dipantau(): void
    {
        $this->masuk();
        $p = $this->pekerja();
        $m = $this->mcu($p, now()->addYear()->toDateString());

        foreach (['draf', 'diajukan', 'ditolak', 'dicabut'] as $status) {
            $this->permit($p, $m, now()->addMonths(6)->toDateString(), $status);
        }

        [$baris] = $this->jalankan();

        $this->assertCount(1, $baris, 'Kartu yang belum terbit ikut terpantau.');
        $this->assertSame(PM::MCU, $baris[0]['jenis']);
    }

    /* ══════════════ tanggal efektif ══════════════ */

    /**
     * Permit tidak hidup lebih lama daripada MCU yang mendasarinya.
     *
     * Memantau tanggal yang tercetak membuat kartu yang MCU-nya sudah
     * habis tetap terhitung "panjang", dan orangnya lolos gerbang dengan
     * kartu yang secara resmi masih berlaku tetapi secara medis tidak
     * lagi berdasar.
     */
    public function test_permit_dibatasi_mcu_yang_mendasarinya(): void
    {
        $this->masuk();
        $p = $this->pekerja();

        // MCU sudah habis kemarin; permit tercetak sampai tahun depan.
        $m = $this->mcu($p, now()->subDay()->toDateString());
        $this->permit($p, $m, now()->addYear()->toDateString());

        [$baris] = $this->jalankan();

        $permit = collect($baris)->firstWhere('jenis', PM::PERMIT);

        $this->assertNotNull($permit);
        $this->assertSame(Authority::HABIS, $permit['keadaan'],
            'Permit terhitung masih berlaku padahal MCU dasarnya sudah habis.');
        $this->assertTrue($permit['dibatasiDasar']);
        $this->assertSame(now()->addYear()->toDateString(), $permit['tglTercetak'],
            'Tanggal tercetak ikut berubah; keduanya harus tetap terlihat.');
        $this->assertNotSame($permit['tglTercetak'], $permit['tglEfektif']);
    }

    /* ══════════════ urutan dan ringkasan ══════════════ */

    /** Yang paling mendesak berada di baris teratas. */
    public function test_baris_berurut_dari_yang_paling_mendesak(): void
    {
        $this->masuk();

        $this->mcu($this->pekerja(), now()->addYears(2)->toDateString());   // panjang
        $this->mcu($this->pekerja(), now()->subDays(3)->toDateString());    // habis
        $this->mcu($this->pekerja(), now()->addDays(10)->toDateString());   // mendesak

        [$baris] = $this->jalankan();

        $this->assertSame(
            [Authority::HABIS, Authority::MENDESAK, Authority::PANJANG],
            array_column($baris, 'keadaan'),
        );
    }

    /** `aktif` menghitung yang masih berlaku hari ini, termasuk yang mendesak. */
    public function test_ringkas_memisahkan_habis_dari_mendekati(): void
    {
        $this->masuk();

        $this->mcu($this->pekerja(), now()->subDay()->toDateString());      // habis
        $this->mcu($this->pekerja(), now()->addDays(10)->toDateString());   // mendesak
        $this->mcu($this->pekerja(), now()->addDays(45)->toDateString());   // dekat
        $this->mcu($this->pekerja(), now()->addYears(2)->toDateString());   // panjang

        [, $r] = $this->jalankan();

        $this->assertSame(4, $r['total']);
        $this->assertSame(1, $r['habis']);
        $this->assertSame(3, $r['aktif'], 'Yang tinggal tiga hari tetap sah hari ini.');
        $this->assertSame(2, $r['mendekati'], 'Mendesak dan dekat digabung.');
        $this->assertSame(1, $r['perKeadaan'][Authority::MENDESAK]);
        $this->assertSame(1, $r['perKeadaan'][Authority::DEKAT]);
    }

    /* ══════════════ batas perusahaan ══════════════ */

    /** Pekerja perusahaan lain tidak ikut terpantau. */
    public function test_perusahaan_lain_tidak_ikut(): void
    {
        $tetangga = Company::create(['name' => 'PT Tetangga', 'code' => 'TTG']);

        $lain = Pekerja::withoutGlobalScopes()->create([
            'company_id' => $tetangga->id, 'nama' => 'Orang Tetangga',
            'nik' => 'NIKX', 'status' => 'aktif',
        ]);
        McuOrang::create([
            'mcu_id' => Mcu::withoutGlobalScopes()->create([
                'company_id' => $tetangga->id, 'tanggal' => now()->startOfDay(),
                'kepada' => 'Klinik', 'status' => 'selesai',
            ])->id,
            'pekerja_id' => $lain->id, 'nama' => $lain->nama,
            'tanggal_periksa' => now()->subDays(5)->startOfDay(),
            'berlaku_sampai'  => now()->addYear()->toDateString(),
            'aktif' => true,
        ]);

        $this->actingAs(User::factory()->create([
            'is_admin' => false, 'company_id' => $this->c->id,
        ]));

        $this->mcu($this->pekerja(['nama' => 'Orang Saya']), now()->addYear()->toDateString());

        [$baris, $r] = $this->jalankan();

        $this->assertSame(1, $r['manpower']);
        $this->assertSame(['Orang Saya'], array_unique(array_column($baris, 'nama')));
    }

    /* ══════════════ dasbor sepakat dengan Miners ══════════════ */

    /**
     * Ubin tenaga kerja dasbor menyebut angka yang SAMA dengan Miners.
     *
     * Inilah alasan kelas ini ada. Sebelumnya dasbor membaca tabel
     * `paspor` lama sementara modul Miners membaca `mnr_pekerja`, dan
     * keduanya berisi — sehingga dua layar yang dibuka berurutan
     * menyebut jumlah tenaga kerja yang berbeda untuk situs yang sama.
     */
    public function test_dasbor_sepakat_dengan_miners(): void
    {
        $this->masuk();

        foreach (range(1, 4) as $_) {
            $p = $this->pekerja();
            $m = $this->mcu($p, now()->addYear()->toDateString());
            $this->permit($p, $m, now()->addMonths(6)->toDateString());
        }

        /* Satu pekerja TANPA berkas apa pun. Ia tetap pekerja terdaftar
           bagi Miners; yang diperiksa di sini bahwa dasbor tidak
           menghitungnya sebagai pemegang berkas. */
        $this->pekerja();

        $miners = Pekerja::count();

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(function (AssertableInertia $p) use ($miners) {
                $ringkas = $p->toArray()['props']['ringkas'];

                $this->assertSame(4, $ringkas['manpower'],
                    'Dasbor menghitung pemegang berkas dengan cara yang lain.');
                $this->assertSame($miners - 1, $ringkas['manpower'],
                    'Selisihnya bukan hanya pekerja tanpa berkas.');
            });
    }

    /** Dasbor tidak lagi menyentuh tabel paspor lama. */
    public function test_dasbor_tidak_lagi_membaca_paspor(): void
    {
        $sumber = file_get_contents(app_path('Http/Controllers/DasborController.php'));

        $this->assertStringNotContainsString('PemantauanBerkas', $sumber);
        $this->assertStringNotContainsString('Paspor::', $sumber);
    }
}
