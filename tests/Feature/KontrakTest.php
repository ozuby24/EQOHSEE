<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Hr\{Absensi, Kontrak, Upah};
use App\Models\Miners\Pekerja;
use App\Models\User;
use App\Support\Hr\{JalurKontrak, KontrakPkwt};
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kontrak kerja PKWT menurut PP 35/2021.
 *
 * Empat aturan di sini tidak dapat dilihat pada layar, dan keempatnya
 * berakibat perubahan jenis kontrak — bukan denda:
 *
 *   · BATAS LIMA TAHUN BERLAKU ATAS RANTAINYA. Tiap kontrak
 *     sendiri-sendiri patuh, dan jumlahnya yang tidak; dua tambah dua
 *     tambah dua adalah tiga kontrak di bawah lima tahun dan satu
 *     hubungan kerja enam tahun yang sudah PKWTT sejak bulan keenam
 *     puluh satu.
 *
 *   · KOMPENSASI PROPORSIONAL PADA KEDUA ARAH, dengan satu ambang
 *     bawah yang tidak proporsional: di bawah satu bulan haknya nol,
 *     bukan sepersekian.
 *
 *   · DIPUTUS DI TENGAH TETAP BERHAK KOMPENSASI, sebesar masa yang
 *     DIJALANI — bukan yang dijanjikan.
 *
 *   · PKWT HARIAN BERUBAH MENJADI PKWTT lewat absensi nyata, bukan
 *     lewat niat pada roster.
 */
class KontrakTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $admin;
    private Carbon $hari;

    protected function setUp(): void
    {
        parent::setUp();

        $this->c     = Company::create(['name' => 'PT Uji Kontrak']);
        $this->admin = User::factory()->create(['company_id' => $this->c->id, 'is_admin' => true]);
        $this->hari  = Waktu::kini()->startOfDay();
    }

    /* ═══════════════════ perkakas ═══════════════════ */

    private function pekerja(): Pekerja
    {
        return Pekerja::withoutGlobalScopes()->create([
            'company_id'    => $this->c->id,
            'nama'          => 'Operator '.uniqid(),
            'no_registrasi' => 'REG-'.uniqid(),
            'status'        => 'aktif',
            'status_kerja'  => 'kontrak',
            'tanggal_masuk' => $this->hari->copy()->subYears(2),
        ]);
    }

    private function upah(Pekerja $p, float $pokok, float $tetap = 0, ?Carbon $sejak = null): Upah
    {
        return Upah::withoutGlobalScopes()->create([
            'company_id'      => $this->c->id,
            'pekerja_id'      => $p->id,
            'berlaku_mulai'   => ($sejak ?? $this->hari->copy()->subYears(5))->toDateString(),
            'pokok'           => $pokok,
            'tunjangan_tetap' => $tetap,
        ]);
    }

    private function kontrak(Pekerja $p, array $isi = []): Kontrak
    {
        return Kontrak::withoutGlobalScopes()->create(array_merge([
            'company_id' => $this->c->id,
            'pekerja_id' => $p->id,
            'nomor'      => 'PKWT-'.uniqid(),
            'jenis'      => 'pkwt_jangka',
            'alasan'     => 'tidak_lama',
            'mulai'      => $this->hari->copy()->subMonths(12)->toDateString(),
            'selesai'    => $this->hari->copy()->toDateString(),
            'status'     => 'berjalan',
        ], $isi));
    }

    private function hadir(Pekerja $p, Carbon $t): Absensi
    {
        return Absensi::withoutGlobalScopes()->create([
            'company_id' => $this->c->id,
            'pekerja_id' => $p->id,
            'tanggal'    => $t->copy()->startOfDay(),
            'keadaan'    => 'hadir',
            'jam'        => 8,
        ]);
    }

    /** @return list<string> kunci temuan */
    private function kunci(Kontrak $k): array
    {
        return array_column(KontrakPkwt::periksa($k->fresh()), 'kunci');
    }

    /* ═══════════════════ masa kerja ═══════════════════ */

    #[Test]
    public function test_masa_kerja_dihitung_inklusif_pada_kedua_ujungnya(): void
    {
        // 1 Januari sampai 31 Desember adalah dua belas bulan penuh.
        // Eksklusif, ia jatuh sedikit di bawahnya — dan selisih sehari
        // itu persis yang memindahkan kompensasi dari satu bulan upah
        // penuh menjadi kurang dari satu bulan.
        $bulan = KontrakPkwt::bulan(
            Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));

        $this->assertEqualsWithDelta(12.0, $bulan, 0.01,
            'Setahun penuh tidak terhitung dua belas bulan.');
    }

    #[Test]
    public function test_kontrak_tanpa_tanggal_selesai_bermasa_nol(): void
    {
        $this->assertSame(0.0, KontrakPkwt::bulan(Carbon::parse('2026-01-01'), null));
    }

    /* ═══════════════════ batas lima tahun ═══════════════════ */

    #[Test]
    public function test_rantai_yang_melewati_lima_tahun_ketahuan_meski_tiap_kontraknya_patuh(): void
    {
        $p = $this->pekerja();

        // Tiga kontrak dua tahunan: masing-masing jauh di bawah lima
        // tahun, jumlahnya enam tahun.
        $a = $this->kontrak($p, [
            'mulai'   => $this->hari->copy()->subYears(6)->toDateString(),
            'selesai' => $this->hari->copy()->subYears(4)->toDateString(),
            'status'  => 'selesai',
        ]);

        $b = $this->kontrak($p, [
            'mulai'    => $this->hari->copy()->subYears(4)->addDay()->toDateString(),
            'selesai'  => $this->hari->copy()->subYears(2)->toDateString(),
            'status'   => 'selesai',
            'induk_id' => $a->id,
            'urutan'   => 2,
        ]);

        $c = $this->kontrak($p, [
            'mulai'    => $this->hari->copy()->subYears(2)->addDay()->toDateString(),
            'selesai'  => $this->hari->copy()->toDateString(),
            'induk_id' => $b->id,
            'urutan'   => 3,
        ]);

        $this->assertGreaterThan(KontrakPkwt::MAKS_BULAN, KontrakPkwt::bulanRantai($c),
            'Rantai enam tahun tidak terhitung melewati enam puluh bulan.');

        $this->assertContains('lewat_lima_tahun', $this->kunci($c),
            'Rantai enam tahun lolos pemeriksaan batas lima tahun.');

        // Yang paling mudah salah: memeriksa hanya baris terakhir.
        // Kontrak ketiga sendirian hanya dua tahun.
        $this->assertNotContains('lewat_lima_tahun',
            array_column(KontrakPkwt::periksa($this->kontrak($this->pekerja())), 'kunci'),
            'Kontrak dua tahun tunggal justru ditandai melewati lima tahun.');
    }

    #[Test]
    public function test_rantai_tepat_lima_tahun_tidak_melanggar(): void
    {
        $p = $this->pekerja();

        $a = $this->kontrak($p, [
            'mulai'   => Carbon::parse('2021-01-01')->toDateString(),
            'selesai' => Carbon::parse('2023-06-30')->toDateString(),
            'status'  => 'selesai',
        ]);

        $b = $this->kontrak($p, [
            'mulai'    => Carbon::parse('2023-07-01')->toDateString(),
            'selesai'  => Carbon::parse('2025-12-31')->toDateString(),
            'induk_id' => $a->id,
            'urutan'   => 2,
        ]);

        $this->assertEqualsWithDelta(60.0, KontrakPkwt::bulanRantai($b), 0.1);

        $this->assertNotContains('lewat_lima_tahun', $this->kunci($b),
            'Rantai tepat lima tahun ditandai melanggar.');
    }

    /* ═══════════════════ uang kompensasi ═══════════════════ */

    #[Test]
    public function test_masa_kerja_setahun_berhak_satu_bulan_upah(): void
    {
        $p = $this->pekerja();
        $this->upah($p, 5_000_000, 1_000_000);

        $k = $this->kontrak($p, [
            'mulai'   => Carbon::parse('2025-01-01')->toDateString(),
            'selesai' => Carbon::parse('2025-12-31')->toDateString(),
        ]);

        $h = KontrakPkwt::kompensasi($k);

        $this->assertTrue($h['berhak'], $h['alasan'] ?? '');

        // Dasarnya pokok DITAMBAH tunjangan tetap — pasal 16 ayat (4).
        // Dihitung dari pokok saja, kompensasinya kurang seperenam.
        $this->assertSame(6_000_000.0, $h['upah'],
            'Dasar kompensasi bukan pokok ditambah tunjangan tetap.');

        $this->assertEqualsWithDelta(6_000_000.0, $h['nilai'], 5_000,
            'Masa kerja setahun tidak menghasilkan satu bulan upah.');
    }

    #[Test]
    public function test_masa_kerja_setengah_tahun_berhak_setengah_bulan_upah(): void
    {
        $p = $this->pekerja();
        $this->upah($p, 6_000_000);

        $k = $this->kontrak($p, [
            'mulai'   => Carbon::parse('2025-01-01')->toDateString(),
            'selesai' => Carbon::parse('2025-06-30')->toDateString(),
        ]);

        $h = KontrakPkwt::kompensasi($k);

        $this->assertEqualsWithDelta(3_000_000.0, $h['nilai'], 20_000,
            'Enam bulan tidak menghasilkan setengah bulan upah.');
    }

    #[Test]
    public function test_masa_kerja_di_bawah_satu_bulan_tidak_berhak_sama_sekali(): void
    {
        $p = $this->pekerja();
        $this->upah($p, 6_000_000);

        $k = $this->kontrak($p, [
            'mulai'   => Carbon::parse('2025-01-01')->toDateString(),
            'selesai' => Carbon::parse('2025-01-20')->toDateString(),
        ]);

        $h = KontrakPkwt::kompensasi($k);

        // Ambang inilah satu-satunya yang TIDAK proporsional. Dihitung
        // proporsional juga, dua puluh hari kerja menghasilkan tagihan
        // yang tidak ada dasarnya.
        $this->assertFalse($h['berhak'],
            'Masa kerja kurang dari sebulan tetap dihitung berhak.');

        $this->assertSame(0.0, $h['nilai']);
    }

    #[Test]
    public function test_masa_kerja_lebih_dari_setahun_dihitung_proporsional_juga(): void
    {
        $p = $this->pekerja();
        $this->upah($p, 6_000_000);

        $k = $this->kontrak($p, [
            'mulai'   => Carbon::parse('2024-01-01')->toDateString(),
            'selesai' => Carbon::parse('2025-06-30')->toDateString(),
        ]);

        // Delapan belas bulan = satu setengah bulan upah, bukan dipatok
        // satu bulan.
        $this->assertEqualsWithDelta(9_000_000.0, KontrakPkwt::kompensasi($k)['nilai'], 30_000,
            'Masa kerja di atas setahun dipatok pada satu bulan upah.');
    }

    #[Test]
    public function test_pkwtt_tidak_berhak_uang_kompensasi(): void
    {
        $p = $this->pekerja();
        $this->upah($p, 6_000_000);

        $k = $this->kontrak($p, ['jenis' => 'pkwtt', 'alasan' => null, 'selesai' => null]);

        $this->assertFalse(KontrakPkwt::kompensasi($k)['berhak']);
    }

    #[Test]
    public function test_upah_yang_dipakai_adalah_upah_pada_tanggal_berakhir(): void
    {
        $p = $this->pekerja();

        $this->upah($p, 5_000_000, 0, Carbon::parse('2025-01-01'));
        $this->upah($p, 8_000_000, 0, Carbon::parse('2025-07-01'));

        $k = $this->kontrak($p, [
            'mulai'   => Carbon::parse('2025-01-01')->toDateString(),
            'selesai' => Carbon::parse('2025-12-31')->toDateString(),
        ]);

        // Upah naik di tengah kontrak. Diambil dari tanggal tanda
        // tangan, pekerjanya kehilangan tiga juta yang menjadi haknya.
        $this->assertSame(8_000_000.0, KontrakPkwt::kompensasi($k)['upah'],
            'Kompensasi memakai upah lama, bukan upah pada tanggal berakhir.');
    }

    #[Test]
    public function test_upah_yang_belum_tercatat_tidak_ditebak_menjadi_nol(): void
    {
        $k = $this->kontrak($this->pekerja());

        $h = KontrakPkwt::kompensasi($k);

        // Nol yang berarti "tidak tahu" harus dapat dibedakan dari nol
        // yang berarti "memang nol".
        $this->assertFalse($h['berhak']);
        $this->assertStringContainsString('belum tercatat', (string) $h['alasan']);
    }

    #[Test]
    public function test_diputus_di_tengah_dikompensasi_sebesar_masa_yang_dijalani(): void
    {
        $p = $this->pekerja();
        $this->upah($p, 6_000_000);

        $k = $this->kontrak($p, [
            'mulai'   => Carbon::parse('2025-01-01')->toDateString(),
            'selesai' => Carbon::parse('2026-12-31')->toDateString(),
            'status'  => 'berjalan',
        ]);

        JalurKontrak::akhiri($k, 'diputus', Carbon::parse('2025-06-30'));

        $k->refresh();

        // Dihitung dari tanggal yang DIJANJIKAN, kompensasinya dua kali
        // lipat dari yang menjadi hak orangnya — pasal 17 menyebut masa
        // yang telah dijalankan.
        $this->assertEqualsWithDelta(3_000_000.0, (float) $k->kompensasi_nilai, 20_000,
            'Pemutusan di tengah dikompensasi menurut masa yang dijanjikan.');
    }

    /* ═══════════════════ syarat bentuk ═══════════════════ */

    #[Test]
    public function test_pkwt_jangka_waktu_tanpa_alasan_pasal_lima_ketahuan(): void
    {
        $k = $this->kontrak($this->pekerja(), ['alasan' => null]);

        $this->assertContains('tanpa_alasan', $this->kunci($k));
    }

    #[Test]
    public function test_alasan_yang_bukan_salah_satu_pasal_lima_tidak_dianggap_sah(): void
    {
        // Alasan bebas yang diketik orang terbaca sebagai terisi, dan
        // pemeriksaan "tidak kosong" meloloskannya.
        $k = $this->kontrak($this->pekerja(), ['alasan' => 'kebutuhan operasional']);

        $this->assertContains('tanpa_alasan', $this->kunci($k),
            'Alasan di luar daftar pasal 5 diterima sebagai sah.');
    }

    #[Test]
    public function test_pkwt_selesainya_pekerjaan_wajib_menyebut_batasan_selesainya(): void
    {
        $k = $this->kontrak($this->pekerja(), [
            'jenis'   => 'pkwt_selesai',
            'alasan'  => 'sekali_selesai',
            'selesai' => null,
        ]);

        $this->assertContains('tanpa_batasan', $this->kunci($k));

        $k->update(['batasan_selesai' => 'Pekerjaan selesai saat seluruh pit 3 selesai direklamasi.']);

        $this->assertNotContains('tanpa_batasan', $this->kunci($k));
    }

    #[Test]
    public function test_masa_percobaan_pada_pkwt_ketahuan(): void
    {
        $k = $this->kontrak($this->pekerja(), ['masa_percobaan_hari' => 30]);

        $this->assertContains('percobaan_pkwt', $this->kunci($k));
    }

    #[Test]
    public function test_masa_percobaan_pkwtt_di_atas_tiga_bulan_ketahuan(): void
    {
        $p = $this->pekerja();

        $patuh = $this->kontrak($p, [
            'jenis' => 'pkwtt', 'alasan' => null, 'selesai' => null,
            'masa_percobaan_hari' => 90,
        ]);

        $this->assertNotContains('percobaan_panjang', $this->kunci($patuh),
            'Masa percobaan tepat tiga bulan ditandai melanggar.');

        $langgar = $this->kontrak($this->pekerja(), [
            'jenis' => 'pkwtt', 'alasan' => null, 'selesai' => null,
            'masa_percobaan_hari' => 120,
        ]);

        $this->assertContains('percobaan_panjang', $this->kunci($langgar));
    }

    #[Test]
    public function test_kontrak_yang_lewat_tiga_hari_kerja_belum_dicatatkan_ketahuan(): void
    {
        $p = $this->pekerja();

        $baru = $this->kontrak($p, [
            'ditandatangani_pada' => $this->hari->copy()->subDay()->toDateString(),
        ]);

        $this->assertNotContains('belum_dicatatkan', $this->kunci($baru),
            'Kontrak yang ditandatangani kemarin sudah dianggap terlambat.');

        $lama = $this->kontrak($this->pekerja(), [
            'ditandatangani_pada' => $this->hari->copy()->subDays(30)->toDateString(),
        ]);

        $this->assertContains('belum_dicatatkan', $this->kunci($lama));

        $lama->update(['dicatatkan_pada' => $this->hari->copy()->subDays(28)->toDateString()]);

        $this->assertNotContains('belum_dicatatkan', $this->kunci($lama),
            'Kontrak yang sudah dicatatkan tetap ditandai.');
    }

    #[Test]
    public function test_hari_kerja_tidak_menghitung_sabtu_dan_minggu(): void
    {
        // Jumat ke Senin: satu hari kerja, bukan tiga.
        $this->assertSame(1, KontrakPkwt::hariKerjaAntara(
            Carbon::parse('2026-09-11'), Carbon::parse('2026-09-14')));
    }

    /* ═══════════════════ PKWT harian ═══════════════════ */

    #[Test]
    public function test_harian_yang_bekerja_21_hari_selama_tiga_bulan_berubah_menjadi_pkwtt(): void
    {
        $p = $this->pekerja();

        $k = $this->kontrak($p, [
            'jenis'   => 'pkwt_harian',
            'alasan'  => null,
            'mulai'   => Carbon::parse('2026-01-01')->toDateString(),
            'selesai' => Carbon::parse('2026-06-30')->toDateString(),
        ]);

        foreach (['2026-01', '2026-02', '2026-03'] as $bulan) {
            for ($h = 1; $h <= 22; $h++) {
                $this->hadir($p, Carbon::parse("{$bulan}-".str_pad((string) $h, 2, '0', STR_PAD_LEFT)));
            }
        }

        $this->assertSame(['2026-01', '2026-02', '2026-03'],
            KontrakPkwt::bulanHarianTerlampaui($k->fresh()));

        $this->assertContains('harian_jadi_pkwtt', $this->kunci($k));
    }

    #[Test]
    public function test_harian_yang_tepat_dua_puluh_hari_sebulan_tetap_pkwt(): void
    {
        $p = $this->pekerja();

        $k = $this->kontrak($p, [
            'jenis'   => 'pkwt_harian',
            'alasan'  => null,
            'mulai'   => Carbon::parse('2026-01-01')->toDateString(),
            'selesai' => Carbon::parse('2026-06-30')->toDateString(),
        ]);

        foreach (['2026-01', '2026-02', '2026-03'] as $bulan) {
            for ($h = 1; $h <= 20; $h++) {
                $this->hadir($p, Carbon::parse("{$bulan}-".str_pad((string) $h, 2, '0', STR_PAD_LEFT)));
            }
        }

        $this->assertSame([], KontrakPkwt::bulanHarianTerlampaui($k->fresh()),
            'Dua puluh hari sebulan sudah dihitung melewati batas.');

        $this->assertNotContains('harian_jadi_pkwtt', $this->kunci($k));
    }

    #[Test]
    public function test_tiga_bulan_yang_tidak_bersebelahan_bukan_berturut_turut(): void
    {
        $p = $this->pekerja();

        $k = $this->kontrak($p, [
            'jenis'   => 'pkwt_harian',
            'alasan'  => null,
            'mulai'   => Carbon::parse('2026-01-01')->toDateString(),
            'selesai' => Carbon::parse('2026-08-31')->toDateString(),
        ]);

        // Januari, Maret, Mei — tiga bulan ramai dengan bulan sepi di
        // antaranya. Bulan sepi tidak muncul sebagai baris sama sekali,
        // sehingga tanpa pemeriksaan kesebelahan ketiganya terbaca
        // beruntun dan seorang pekerja harian yang patuh dinyatakan
        // sudah menjadi karyawan tetap.
        foreach (['2026-01', '2026-03', '2026-05'] as $bulan) {
            for ($h = 1; $h <= 22; $h++) {
                $this->hadir($p, Carbon::parse("{$bulan}-".str_pad((string) $h, 2, '0', STR_PAD_LEFT)));
            }
        }

        $this->assertLessThan(KontrakPkwt::HARIAN_BULAN_BERUNTUN,
            count(KontrakPkwt::bulanHarianTerlampaui($k->fresh())),
            'Bulan berselang dianggap berturut-turut.');

        $this->assertNotContains('harian_jadi_pkwtt', $this->kunci($k));
    }

    #[Test]
    public function test_hari_absen_tidak_ikut_dihitung_sebagai_hari_bekerja(): void
    {
        $p = $this->pekerja();

        $k = $this->kontrak($p, [
            'jenis'   => 'pkwt_harian',
            'alasan'  => null,
            'mulai'   => Carbon::parse('2026-01-01')->toDateString(),
            'selesai' => Carbon::parse('2026-06-30')->toDateString(),
        ]);

        for ($h = 1; $h <= 25; $h++) {
            $t = Carbon::parse('2026-01-'.str_pad((string) $h, 2, '0', STR_PAD_LEFT));

            Absensi::withoutGlobalScopes()->create([
                'company_id' => $this->c->id,
                'pekerja_id' => $p->id,
                'tanggal'    => $t,
                'keadaan'    => $h <= 10 ? 'hadir' : 'absen',
                'jam'        => $h <= 10 ? 8 : 0,
            ]);
        }

        $this->assertSame([], KontrakPkwt::bulanHarianTerlampaui($k->fresh()),
            'Hari mangkir ikut terhitung sebagai hari bekerja.');
    }

    /* ═══════════════════ jalur ═══════════════════ */

    #[Test]
    public function test_perpanjangan_menutup_kontrak_lama_dan_menunjuk_induknya(): void
    {
        $p = $this->pekerja();
        $this->upah($p, 6_000_000);

        $lama = $this->kontrak($p, [
            'mulai'   => $this->hari->copy()->subYears(1)->toDateString(),
            'selesai' => $this->hari->copy()->toDateString(),
        ]);

        [$galat, $baru] = JalurKontrak::perpanjang($lama, [
            'nomor'   => 'PKWT-LANJUT-1',
            'mulai'   => $this->hari->copy()->addDay()->toDateString(),
            'selesai' => $this->hari->copy()->addYear()->toDateString(),
        ], $this->admin);

        $this->assertNull($galat);
        $this->assertNotNull($baru);

        $lama->refresh();

        $this->assertSame('selesai', $lama->status);
        $this->assertSame($lama->id, $baru->induk_id);
        $this->assertSame(2, $baru->urutan);

        // Pasal 17: kompensasi jatuh tempo pada selesainya periode
        // sebelum perpanjangan, bukan sekali di ujung rangkaian.
        $this->assertNotNull($lama->kompensasi_nilai,
            'Perpanjangan tidak menghitung kompensasi periode yang ditutupnya.');

        $this->assertGreaterThan(0, (float) $lama->kompensasi_nilai);
    }

    #[Test]
    public function test_perpanjangan_yang_mulai_sebelum_kontrak_lama_berakhir_ditolak(): void
    {
        $p = $this->pekerja();

        $lama = $this->kontrak($p, [
            'mulai'   => $this->hari->copy()->subYear()->toDateString(),
            'selesai' => $this->hari->copy()->addMonths(6)->toDateString(),
        ]);

        [$galat, $baru] = JalurKontrak::perpanjang($lama, [
            'nomor' => 'PKWT-TUMPANG',
            'mulai' => $this->hari->copy()->toDateString(),
        ], $this->admin);

        $this->assertNotNull($galat);
        $this->assertNull($baru);
    }

    #[Test]
    public function test_pkwtt_tidak_dapat_diperpanjang(): void
    {
        $k = $this->kontrak($this->pekerja(), [
            'jenis' => 'pkwtt', 'alasan' => null, 'selesai' => null,
        ]);

        [$galat, $baru] = JalurKontrak::perpanjang($k, [
            'nomor' => 'X', 'mulai' => $this->hari->toDateString(),
        ], $this->admin);

        $this->assertNotNull($galat);
        $this->assertNull($baru);
    }

    #[Test]
    public function test_dua_kontrak_berjalan_sekaligus_ditolak(): void
    {
        $p = $this->pekerja();

        $this->kontrak($p, [
            'mulai'   => $this->hari->copy()->subMonths(6)->toDateString(),
            'selesai' => $this->hari->copy()->addMonths(6)->toDateString(),
        ]);

        $kedua = $this->kontrak($p, [
            'mulai'   => $this->hari->copy()->toDateString(),
            'selesai' => $this->hari->copy()->addYear()->toDateString(),
            'status'  => 'draft',
        ]);

        // Dua hubungan kerja sekaligus membuat masa kerjanya terhitung
        // dua kali, dan batas lima tahun tercapai dua kali lebih cepat.
        $this->assertNotNull(JalurKontrak::terbitkan($kedua, $this->admin),
            'Kontrak kedua yang bertumpang tindih tetap diterbitkan.');

        $this->assertSame('draft', $kedua->fresh()->status);
    }

    #[Test]
    public function test_kontrak_berurutan_tanpa_tumpang_tindih_boleh_diterbitkan(): void
    {
        $p = $this->pekerja();

        $this->kontrak($p, [
            'mulai'   => $this->hari->copy()->subYear()->toDateString(),
            'selesai' => $this->hari->copy()->subDay()->toDateString(),
        ]);

        $kedua = $this->kontrak($p, [
            'mulai'   => $this->hari->copy()->toDateString(),
            'selesai' => $this->hari->copy()->addYear()->toDateString(),
            'status'  => 'draft',
        ]);

        $this->assertNull(JalurKontrak::terbitkan($kedua, $this->admin));
        $this->assertSame('berjalan', $kedua->fresh()->status);
    }

    #[Test]
    public function test_perubahan_menjadi_pkwtt_menyisakan_baris_pkwt_yang_lama(): void
    {
        $p = $this->pekerja();
        $this->upah($p, 6_000_000);

        $k = $this->kontrak($p, [
            'jenis'   => 'pkwt_harian',
            'alasan'  => null,
            'mulai'   => Carbon::parse('2026-01-01')->toDateString(),
            'selesai' => Carbon::parse('2026-12-31')->toDateString(),
        ]);

        foreach (['2026-01', '2026-02', '2026-03'] as $bulan) {
            for ($h = 1; $h <= 22; $h++) {
                $this->hadir($p, Carbon::parse("{$bulan}-".str_pad((string) $h, 2, '0', STR_PAD_LEFT)));
            }
        }

        [$galat, $baru] = JalurKontrak::jadikanPkwtt(
            $k->fresh(), 'Melewati 21 hari selama tiga bulan berturut-turut.', $this->admin);

        $this->assertNull($galat);

        $k->refresh();

        // Barisnya TETAP PKWT. Ditimpa menjadi PKWTT, bukti bahwa
        // aturannya pernah terlampaui ikut terhapus, dan yang tersisa
        // hanyalah seorang karyawan tetap tanpa riwayat.
        $this->assertSame('pkwt_harian', $k->jenis,
            'Baris PKWT lama ditimpa menjadi PKWTT.');

        $this->assertSame('jadi_pkwtt', $k->status);
        $this->assertSame('pkwtt', $baru->jenis);
        $this->assertSame($k->id, $baru->induk_id);

        // Sejak bulan ketiga terpenuhi, bukan sejak hari ini.
        $this->assertSame('2026-03-31', $baru->mulai->toDateString(),
            'PKWTT dimulai bukan pada saat aturannya terlampaui.');
    }

    #[Test]
    public function test_rantai_yang_menunjuk_dirinya_sendiri_tidak_menggantung(): void
    {
        $p = $this->pekerja();
        $k = $this->kontrak($p);

        // Tidak mungkin lewat antarmuka, pernah ada lewat impor data.
        Kontrak::withoutGlobalScopes()->where('id', $k->id)->update(['induk_id' => $k->id]);

        $this->assertSame($k->id, $k->fresh()->akar()->id,
            'Rantai yang menunjuk dirinya sendiri tidak berhenti.');
    }

    /* ═══════════════════ celah yang ditemukan uji mutasi ═══════════════════
     *
     * Sepuluh uji di bawah lahir dari satu putaran uji mutasi: seluruh
     * aturan di atas lulus, dan dua belas cacat yang dipasang sengaja
     * tetap lolos. Akarnya satu — SELURUH ujinya memakai rentang bulan
     * yang bulat dan angka yang jauh dari ambangnya, sehingga baik jalur
     * pecahan bulan maupun perbandingan di titik batas tidak pernah
     * benar-benar dijalankan.
     *
     * DUA mutasi memang tidak dapat dibunuh, dan tidak dipaksakan:
     *
     *   · Menghapus `subDay()` dari penghitungan bulan penuh menggeser
     *     hasilnya ke lengan pecahan, yang menambahkan kembali persis
     *     satu bulan. Keduanya menghasilkan angka yang sama untuk setiap
     *     masukan.
     *
     *   · Membuang penjepit rentang terbalik tidak mengubah apa pun,
     *     sebab `diffInDays` pada Carbon 3 bertanda: rentang yang
     *     terbalik menghasilkan sisa hari negatif yang tertangkap
     *     penjaga `$sisaHari <= 0` di bawahnya. Penjepitnya tetap
     *     dipertahankan — ia menyatakan maksudnya di tempat yang
     *     terbaca, dan ia menjadi satu-satunya yang menahan bila
     *     Carbon kembali mengembalikan selisih mutlak seperti versi
     *     sebelumnya.
     *
     * Uji yang membedakan keduanya hanya akan menguji bentuk kodenya,
     * bukan perilakunya.
     */

    #[Test]
    public function test_bulan_yang_tidak_utuh_dihitung_terhadap_panjang_bulan_itu_sendiri(): void
    {
        // 1 Februari sampai 15 Maret: satu bulan penuh, lalu 15 hari dari
        // bulan Maret yang panjangnya 31 hari.
        //
        // Inilah satu-satunya jalur yang memakai pecahan, dan sebelum uji
        // ini ada, tidak satu pun berkas uji melewatinya — rentang bulan
        // yang bulat berhenti di penjaga sebelumnya.
        $this->assertEqualsWithDelta(1 + 15 / 31, KontrakPkwt::bulan(
            Carbon::parse('2025-02-01'), Carbon::parse('2025-03-15')), 0.01,
            'Sisa hari tidak dibandingkan dengan panjang bulan berjalannya.');
    }

    #[Test]
    public function test_rentang_yang_terbalik_bermasa_nol(): void
    {
        $this->assertSame(0.0, KontrakPkwt::bulan(
            Carbon::parse('2025-06-01'), Carbon::parse('2025-01-01')),
            'Tanggal selesai yang mendahului tanggal mulai menghasilkan masa kerja.');
    }

    #[Test]
    public function test_rantai_lima_setengah_tahun_melanggar_batasnya(): void
    {
        $p = $this->pekerja();

        // 66 bulan: di atas enam puluh, dan jauh di bawah tujuh puluh
        // dua. Rangkaian enam tahun pada uji sebelumnya duduk tepat di
        // tepi, sehingga batas yang digeser ke tujuh puluh dua tetap
        // memunculkan temuan yang sama dan cacatnya tidak ketahuan.
        $a = $this->kontrak($p, [
            'mulai'   => '2020-01-01',
            'selesai' => '2022-12-31',
            'status'  => 'selesai',
        ]);

        $b = $this->kontrak($p, [
            'mulai'    => '2023-01-01',
            'selesai'  => '2025-06-30',
            'induk_id' => $a->id,
            'urutan'   => 2,
        ]);

        $this->assertEqualsWithDelta(66.0, KontrakPkwt::bulanRantai($b), 0.1);

        $this->assertContains('lewat_lima_tahun', $this->kunci($b));
    }

    #[Test]
    public function test_pkwtt_tidak_menambah_panjang_rantai_pkwt(): void
    {
        $p = $this->pekerja();

        $pkwt = $this->kontrak($p, [
            'mulai'   => '2024-01-01',
            'selesai' => '2024-12-31',
            'status'  => 'selesai',
        ]);

        // PKWTT yang terbawa dari impor data kerap punya tanggal akhir
        // yang seharusnya tidak ada. Ikut dijumlahkan, ia mendorong
        // rantainya melewati batas dan memunculkan pelanggaran yang tidak
        // pernah terjadi.
        $pkwtt = $this->kontrak($p, [
            'jenis'    => 'pkwtt',
            'alasan'   => null,
            'mulai'    => '2025-01-01',
            'selesai'  => '2029-12-31',
            'induk_id' => $pkwt->id,
            'urutan'   => 2,
        ]);

        $this->assertEqualsWithDelta(12.0, KontrakPkwt::bulanRantai($pkwtt), 0.1,
            'PKWTT ikut dijumlahkan ke dalam panjang rantai PKWT.');

        $this->assertNotContains('lewat_lima_tahun', $this->kunci($pkwtt));
    }

    #[Test]
    public function test_masa_kerja_tepat_satu_bulan_sudah_berhak_kompensasi(): void
    {
        $p = $this->pekerja();
        $this->upah($p, 6_000_000);

        $k = $this->kontrak($p, ['mulai' => '2025-03-01', 'selesai' => '2025-03-31']);

        $h = KontrakPkwt::kompensasi($k);

        // Pasal 15 menyebut "paling sedikit satu bulan". Tepat satu bulan
        // ada DI DALAM haknya, bukan di luarnya.
        $this->assertTrue($h['berhak'], $h['alasan'] ?? '');

        $this->assertEqualsWithDelta(500_000.0, $h['nilai'], 5_000);
    }

    #[Test]
    public function test_pkwtt_yang_punya_tanggal_akhir_tetap_tidak_berhak_kompensasi(): void
    {
        $p = $this->pekerja();
        $this->upah($p, 6_000_000);

        // Uji PKWTT sebelumnya memakai kontrak tanpa tanggal akhir,
        // sehingga yang menolaknya adalah penjaga "belum punya tanggal
        // berakhir" — bukan penjaga jenisnya. Keduanya menghasilkan
        // jawaban yang sama dan hanya satu yang sedang diuji.
        $k = $this->kontrak($p, [
            'jenis'   => 'pkwtt',
            'alasan'  => null,
            'mulai'   => '2025-01-01',
            'selesai' => '2025-12-31',
        ]);

        $h = KontrakPkwt::kompensasi($k);

        $this->assertFalse($h['berhak'], 'PKWTT dengan tanggal akhir dihitung berhak kompensasi.');
        $this->assertStringContainsString('PKWTT', (string) $h['alasan']);
    }

    #[Test]
    public function test_harian_tepat_21_hari_sebulan_sudah_melewati_batasnya(): void
    {
        $p = $this->pekerja();

        $k = $this->kontrak($p, [
            'jenis'   => 'pkwt_harian',
            'alasan'  => null,
            'mulai'   => '2026-01-01',
            'selesai' => '2026-06-30',
        ]);

        // Pasal 10 ayat (2) menyebut "kurang dari 21 hari". Dua puluh satu
        // hari sudah TIDAK kurang — ia di luar batasnya, bukan di
        // dalamnya.
        foreach (['2026-01', '2026-02', '2026-03'] as $bulan) {
            for ($h = 1; $h <= 21; $h++) {
                $this->hadir($p, Carbon::parse("{$bulan}-".str_pad((string) $h, 2, '0', STR_PAD_LEFT)));
            }
        }

        $this->assertSame(['2026-01', '2026-02', '2026-03'],
            KontrakPkwt::bulanHarianTerlampaui($k->fresh()),
            'Tepat 21 hari sebulan dianggap masih di bawah batas.');
    }

    #[Test]
    public function test_pkwtt_dimulai_pada_bulan_ketiga_meski_pelanggarannya_berlanjut(): void
    {
        $p = $this->pekerja();
        $this->upah($p, 6_000_000);

        $k = $this->kontrak($p, [
            'jenis'   => 'pkwt_harian',
            'alasan'  => null,
            'mulai'   => '2026-01-01',
            'selesai' => '2026-12-31',
        ]);

        // EMPAT bulan berturut-turut, bukan tiga. Diambil dari bulan
        // terakhir, hak yang mengikuti PKWTT terhitung sebulan lebih
        // lambat daripada seharusnya — dan uji bertiga bulan tidak dapat
        // membedakan keduanya sebab bulan ketiga memang bulan terakhir.
        foreach (['2026-01', '2026-02', '2026-03', '2026-04'] as $bulan) {
            for ($h = 1; $h <= 22; $h++) {
                $this->hadir($p, Carbon::parse("{$bulan}-".str_pad((string) $h, 2, '0', STR_PAD_LEFT)));
            }
        }

        [$galat, $baru] = JalurKontrak::jadikanPkwtt($k->fresh(), 'Melewati batas harian.', $this->admin);

        $this->assertNull($galat);

        $this->assertSame('2026-03-31', $baru->mulai->toDateString(),
            'PKWTT dimulai pada bulan terakhir, bukan pada bulan ketiga.');
    }

    #[Test]
    public function test_pkwtt_yang_berjalan_menghalangi_kontrak_kedua(): void
    {
        $p = $this->pekerja();

        // PKWTT tidak punya tanggal akhir. Diperlakukan sebagai "sudah
        // lewat", ia tidak pernah menghalangi apa pun — dan seorang
        // karyawan tetap dapat diberi PKWT di atas PKWTT-nya sendiri.
        $this->kontrak($p, [
            'jenis'   => 'pkwtt',
            'alasan'  => null,
            'mulai'   => $this->hari->copy()->subYears(3)->toDateString(),
            'selesai' => null,
        ]);

        $kedua = $this->kontrak($p, [
            'mulai'   => $this->hari->copy()->toDateString(),
            'selesai' => $this->hari->copy()->addYear()->toDateString(),
            'status'  => 'draft',
        ]);

        $this->assertNotNull(JalurKontrak::terbitkan($kedua, $this->admin),
            'PKWT diterbitkan di atas PKWTT yang masih berjalan.');
    }

    #[Test]
    public function test_kontrak_yang_berakhir_pada_hari_terakhir_jendela_ikut_terdaftar(): void
    {
        $p = $this->pekerja();

        $this->kontrak($p, [
            'mulai'   => $this->hari->copy()->subYear()->toDateString(),
            'selesai' => $this->hari->copy()->addDays(60)->toDateString(),
        ]);

        // Kolomnya bertipe DATE tetapi menyimpan "2026-11-11 00:00:00",
        // dan sebagai perbandingan teks nilai itu LEBIH BESAR daripada
        // "2026-11-11". Kontrak yang berakhir pada hari terakhir jendela
        // hilang dari daftar tanpa satu galat pun — yaitu kontrak yang
        // paling jauh dan paling mudah terlupakan.
        $daftar = KontrakPkwt::akanBerakhir($this->c->id, 60);

        $this->assertCount(1, $daftar,
            'Kontrak yang berakhir tepat pada hari terakhir jendela tidak terdaftar.');
    }
}
