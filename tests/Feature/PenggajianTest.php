<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Hr\{Absensi, Lembur, PeriodeGaji, PolaRoster, Roster, SlipGaji, Upah};
use App\Models\Miners\Pekerja;
use App\Models\User;
use App\Support\Hr\{Bpjs, MasterPajak, MasterRoster, Pajak, Penggajian};
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Penggajian, PPh 21 TER, dan BPJS.
 *
 * ANGKANYA DIUJI TERHADAP HITUNGAN TANGAN, bukan terhadap keluaran
 * kodenya sendiri. Uji yang membandingkan hasil dengan hasil akan
 * lulus atas rumus yang salah selama salahnya konsisten — dan di sini
 * risikonya paling tinggi dari seluruh modul: pajak yang kurang setor
 * berdenda, dan iuran BPJS yang kurang bayar baru ketahuan saat
 * diperiksa.
 *
 * TIGA HAL YANG PALING SERING KELIRU, dan ketiganya diuji dari dua
 * sisi:
 *
 *   · Plafon BPJS BERBEDA-BEDA per program — Kesehatan Rp12 juta, JP
 *     Rp11.086.300, sedangkan JHT/JKK/JKM tanpa plafon sama sekali.
 *   · Kategori TER ditentukan status PTKP, bukan besar penghasilan.
 *   · Desember memakai rekonsiliasi progresif, bukan TER — dan
 *     selisihnya boleh NEGATIF, yaitu dikembalikan kepada pekerja.
 */
class PenggajianTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $admin;
    private Carbon $hari;

    protected function setUp(): void
    {
        parent::setUp();

        MasterRoster::pasang();
        MasterPajak::pasang();

        $this->c     = Company::create(['name' => 'PT Uji Gaji']);
        $this->admin = User::factory()->create(['company_id' => $this->c->id, 'is_admin' => true]);
        $this->hari  = Waktu::kini()->startOfDay();
    }

    /* ═══════════════════ PTKP dan kategori ═══════════════════ */

    #[Test]
    public function test_ptkp_mengikuti_pmk_101_2016(): void
    {
        $this->assertSame(54_000_000, Pajak::ptkp('TK/0'));
        $this->assertSame(58_500_000, Pajak::ptkp('K/0'));
        $this->assertSame(58_500_000, Pajak::ptkp('TK/1'));
        $this->assertSame(72_000_000, Pajak::ptkp('K/3'));
    }

    #[Test]
    public function test_tanggungan_dibatasi_tiga(): void
    {
        /* Pasal 7 UU PPh: tanggungan paling banyak tiga orang.
           Dibiarkan tanpa batas, seorang dengan lima tanggungan
           mengurangi penghasilan kena pajaknya melebihi yang
           diperbolehkan — dan pajaknya kurang setor. */
        $this->assertSame(Pajak::ptkp('K/3'), Pajak::ptkp('K/9'));

        /* Dan statusnya TIDAK dibuang seluruhnya: "K/4" jelas menyebut
           seorang yang kawin. Ditolak mentah-mentah, satu angka yang
           salah ketik mengubah seorang kepala keluarga beranak empat
           menjadi lajang tanpa tanggungan — dan pajaknya dipotong jauh
           lebih besar daripada seharusnya. Ditemukan uji ini. */
        $this->assertSame('K/3', Pajak::normalkan('K/4'));
        $this->assertSame('C',   Pajak::kategori('K/4'));
        $this->assertSame('TK/0', Pajak::normalkan('entah'));
    }

    #[Test]
    public function test_kategori_ter_ditentukan_status_bukan_penghasilan(): void
    {
        $this->assertSame('A', Pajak::kategori('TK/0'));
        $this->assertSame('A', Pajak::kategori('K/0'));
        $this->assertSame('B', Pajak::kategori('K/2'));
        $this->assertSame('C', Pajak::kategori('K/3'));
    }

    #[Test]
    public function test_status_yang_tidak_dikenal_jatuh_ke_kategori_paling_cepat_memajaki(): void
    {
        /* Kekeliruan data berakibat LEBIH banyak dipotong, bukan
           kurang: kurang dipotong berarti kekurangan setor yang
           berdenda, lebih dipotong dikembalikan pada rekonsiliasi
           Desember. */
        $this->assertSame('A', Pajak::kategori(null));
        $this->assertSame('A', Pajak::kategori('entah'));

        $a = Pajak::ter(10_000_000, 'TK/0')['tarif'];
        $c = Pajak::ter(10_000_000, 'K/3')['tarif'];

        $this->assertGreaterThan($c, $a, 'Kategori A seharusnya memajaki lebih cepat daripada C.');
    }

    /* ═══════════════════ TER bulanan ═══════════════════ */

    #[Test]
    public function test_penghasilan_di_bawah_lapisan_pertama_tidak_berpajak(): void
    {
        $this->assertSame(0.0, Pajak::ter(5_000_000, 'TK/0')['tarif']);
        $this->assertSame(0.0, Pajak::ter(0, 'TK/0')['pajak']);
    }

    #[Test]
    public function test_lapisan_teratas_menang_atas_penghasilan_sebesar_apa_pun(): void
    {
        /* Lapisan yang batas atasnya null adalah yang terakhir. Diurut
           tanpa menjaganya di belakang, penghasilan dua miliar jatuh ke
           lapisan nol persen. */
        $t = Pajak::ter(2_000_000_000, 'TK/0');

        $this->assertSame(34.0, $t['tarif']);
        $this->assertSame(680_000_000.0, $t['pajak']);
    }

    #[Test]
    public function test_tarif_efektif_dikalikan_bruto(): void
    {
        $t = Pajak::ter(10_000_000, 'TK/0');

        $this->assertSame('A', $t['kategori']);
        $this->assertSame(2.0, $t['tarif']);
        $this->assertSame(200_000.0, $t['pajak']);
    }

    /* ═══════════════════ progresif Pasal 17 ═══════════════════ */

    #[Test]
    public function test_tarif_progresif_berlapis(): void
    {
        /* PKP 300 juta = 60jt x 5% + 190jt x 15% + 50jt x 25%
                        = 3jt + 28,5jt + 12,5jt = 44jt. */
        [$pajak, $lapis] = Pajak::progresif(300_000_000);

        $this->assertEqualsWithDelta(44_000_000, $pajak, 1);
        $this->assertSame([5.0, 15.0, 25.0], array_column($lapis, 'tarif'));
        $this->assertSame([60_000_000.0, 190_000_000.0, 50_000_000.0], array_column($lapis, 'dasar'));
    }

    #[Test]
    public function test_lapisan_tertinggi_tiga_puluh_lima_persen(): void
    {
        [$pajak] = Pajak::progresif(6_000_000_000);

        /* 60jt x 5% + 190jt x 15% + 250jt x 25% + 4.500jt x 30% + 1.000jt x 35% */
        $tangan = 60e6 * 0.05 + 190e6 * 0.15 + 250e6 * 0.25 + 4_500e6 * 0.30 + 1_000e6 * 0.35;

        $this->assertEqualsWithDelta($tangan, $pajak, 1);
    }

    #[Test]
    public function test_rekonsiliasi_tahunan_mengurangi_yang_sudah_dipotong(): void
    {
        /* Bruto setahun 240jt, TK/0, tanpa iuran.
           Biaya jabatan 5% = 12jt, dibatasi 6jt.
           Neto 234jt, PTKP 54jt, PKP 180jt.
           Pajak = 60jt x 5% + 120jt x 15% = 3jt + 18jt = 21jt. */
        $h = Pajak::tahunan(240_000_000, 0, 'TK/0', 15_000_000);

        $this->assertSame(6_000_000.0, $h['biaya_jabatan'], 'Biaya jabatan tidak dibatasi Rp6 juta setahun.');
        $this->assertSame(180_000_000.0, $h['pkp']);
        $this->assertEqualsWithDelta(21_000_000, $h['pajak_setahun'], 1);
        $this->assertEqualsWithDelta(6_000_000, $h['pajak'], 1);
    }

    #[Test]
    public function test_lebih_potong_dikembalikan_sebagai_angka_negatif(): void
    {
        /* Dijepit di nol, kelebihan potong sebelas bulan hilang begitu
           saja — dan pekerjanya tidak pernah tahu. */
        $h = Pajak::tahunan(240_000_000, 0, 'TK/0', 30_000_000);

        $this->assertLessThan(0, $h['pajak']);
    }

    #[Test]
    public function test_iuran_pekerja_mengurangi_penghasilan_neto(): void
    {
        /* Iuran JHT dan JP yang dibayar PEKERJA mengurangi penghasilan
           neto; yang dibayar pemberi kerja tidak. Disamakan — atau
           dilupakan sama sekali — penghasilan kena pajaknya terlalu
           besar dan pekerjanya membayar pajak atas uang yang tidak
           pernah ia terima. */
        $tanpa  = Pajak::tahunan(240_000_000, 0, 'TK/0', 0);
        $dengan = Pajak::tahunan(240_000_000, 7_200_000, 'TK/0', 0);

        $this->assertSame(7_200_000.0, $dengan['iuran']);
        $this->assertSame($tanpa['neto'] - 7_200_000, $dengan['neto']);

        /* Selisih PKP 7,2 juta pada lapisan 15% = pajak 1,08 juta lebih
           kecil. */
        $this->assertEqualsWithDelta(1_080_000, $tanpa['pajak_setahun'] - $dengan['pajak_setahun'], 1);
    }

    #[Test]
    public function test_penghasilan_di_bawah_ptkp_tidak_berpajak(): void
    {
        $h = Pajak::tahunan(50_000_000, 0, 'TK/0', 0);

        $this->assertSame(0.0, $h['pkp']);
        $this->assertSame(0.0, $h['pajak_setahun']);
    }

    /* ═══════════════════ BPJS ═══════════════════ */

    #[Test]
    public function test_plafon_berbeda_beda_per_program(): void
    {
        /* INI YANG PALING SERING KELIRU. Dipukul satu plafon untuk
           semuanya, iuran JHT seorang pengawas berupah lima belas juta
           dipotong seolah upahnya dua belas juta — dan saldo hari
           tuanya berkurang tiap bulan selama bertahun-tahun. */
        $b = Bpjs::hitung(15_000_000);

        $this->assertSame(12_000_000.0,  $b['rincian']['kesehatan']['dasar']);
        $this->assertSame(11_086_300.0,  $b['rincian']['jp']['dasar']);
        $this->assertSame(15_000_000.0,  $b['rincian']['jht']['dasar'], 'JHT tidak berplafon.');
        $this->assertSame(15_000_000.0,  $b['rincian']['jkk']['dasar'], 'JKK tidak berplafon.');
    }

    #[Test]
    public function test_batas_bawah_upah_minimum_diterapkan_pada_kesehatan(): void
    {
        /* Diabaikan, pekerja harian berupah di bawah UMP diiur terlalu
           kecil dan kepesertaannya bermasalah justru saat ia perlu
           berobat. */
        $b = Bpjs::hitung(2_000_000, 3_762_431);

        $this->assertSame(3_762_431.0, $b['rincian']['kesehatan']['dasar']);
        $this->assertSame(2_000_000.0, $b['rincian']['jht']['dasar'], 'Batas bawah bocor ke JHT.');
    }

    #[Test]
    public function test_tarif_iuran_sesuai_peraturan(): void
    {
        $b = Bpjs::hitung(10_000_000);

        $this->assertSame(400_000.0, $b['rincian']['kesehatan']['perusahaan']);  // 4%
        $this->assertSame(100_000.0, $b['rincian']['kesehatan']['karyawan']);    // 1%
        $this->assertSame(370_000.0, $b['rincian']['jht']['perusahaan']);        // 3,7%
        $this->assertSame(200_000.0, $b['rincian']['jht']['karyawan']);          // 2%
        $this->assertSame(174_000.0, $b['rincian']['jkk']['perusahaan']);        // 1,74%
        $this->assertSame(30_000.0,  $b['rincian']['jkm']['perusahaan']);        // 0,3%
    }

    #[Test]
    public function test_jkk_dan_jkm_tidak_memotong_pekerja(): void
    {
        $b = Bpjs::hitung(10_000_000);

        $this->assertSame(0.0, $b['rincian']['jkk']['karyawan']);
        $this->assertSame(0.0, $b['rincian']['jkm']['karyawan']);
    }

    #[Test]
    public function test_jkk_memakai_tarif_risiko_sangat_tinggi_bawaan(): void
    {
        /* Pertambangan berada pada risiko sangat tinggi. Dipasang pada
           risiko rendah, selisihnya satu setengah persen dari seluruh
           upah site — dan kekurangan bayar itu baru ketahuan saat BPJS
           memeriksa. */
        $this->assertSame('sangat_tinggi', Bpjs::JKK_BAWAAN);
        $this->assertSame(1.74, Bpjs::JKK_TARIF['sangat_tinggi']);

        $rendah = Bpjs::hitung(10_000_000, 0, 'sangat_rendah');

        $this->assertSame(24_000.0, $rendah['rincian']['jkk']['perusahaan']);
    }

    #[Test]
    public function test_hanya_sebagian_iuran_perusahaan_menambah_bruto_pajak(): void
    {
        /* JKK dan JKM adalah premi asuransi yang dibayar pemberi kerja
           bagi pekerja, dan karena itu objek PPh 21. JHT dan JP tidak —
           keduanya baru dikenakan pajak saat manfaatnya dibayarkan.
           Disamaratakan, bruto pajak meleset beberapa ratus ribu tiap
           bulan. */
        $b = Bpjs::hitung(10_000_000);

        $tangan = 400_000 + 174_000 + 30_000;   // kesehatan + jkk + jkm

        $this->assertSame((float) $tangan, Bpjs::menambahBruto($b['rincian']));
    }

    /* ═══════════════════ perkakas ═══════════════════ */

    private function pekerja(string $ptkp = 'TK/0'): Pekerja
    {
        return Pekerja::withoutGlobalScopes()->create([
            'company_id'    => $this->c->id,
            'nama'          => 'Operator '.uniqid(),
            'no_registrasi' => 'REG-'.uniqid(),
            'status'        => 'aktif',
            'status_ptkp'   => $ptkp,
        ]);
    }

    private function upah(Pekerja $p, float $pokok = 8_000_000, float $site = 200_000): Upah
    {
        return Upah::withoutGlobalScopes()->create([
            'company_id'            => $this->c->id,
            'pekerja_id'            => $p->id,
            'berlaku_mulai'         => $this->hari->copy()->subYears(2),
            'pokok'                 => $pokok,
            'tunjangan_site_harian' => $site,
        ]);
    }

    private function periode(?int $bulan = null, ?int $tahun = null): PeriodeGaji
    {
        return PeriodeGaji::withoutGlobalScopes()->create([
            'company_id' => $this->c->id,
            'tahun'      => $tahun ?? (int) $this->hari->format('Y'),
            'bulan'      => $bulan ?? (int) $this->hari->format('n'),
        ]);
    }

    private function hadir(Pekerja $p, Carbon $tanggal, float $jam = 11): Absensi
    {
        return Absensi::withoutGlobalScopes()->create([
            'company_id' => $this->c->id,
            'pekerja_id' => $p->id,
            'tanggal'    => $tanggal->copy()->startOfDay(),
            'keadaan'    => 'hadir',
            'jam'        => $jam,
        ]);
    }

    private function verifikasiSemua(): void
    {
        DB::table('pay_acuan')->update(['terverifikasi' => true]);
    }

    /* ═══════════════════ jalannya penggajian ═══════════════════ */

    #[Test]
    public function test_tunjangan_site_dihitung_dari_hari_yang_benar_benar_tercatat(): void
    {
        /* Pekerja FIFO berada di site empat belas hari dari dua puluh
           satu; tunjangan bulanan yang dipukul rata membayar tujuh hari
           yang ia habiskan di kampung halamannya. */
        $p = $this->pekerja();
        $this->upah($p, 8_000_000, 200_000);

        $periode = $this->periode();
        [$dari] = Penggajian::rentang($periode);

        for ($i = 0; $i < 5; $i++) $this->hadir($p, $dari->copy()->addDays($i));

        $this->assertIsArray(Penggajian::hitung($periode, $this->admin));

        $s = SlipGaji::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(5, $s->hari_site);
        $this->assertSame(1_000_000.0, $s->tunjangan_site);
    }

    #[Test]
    public function test_hanya_lembur_yang_sudah_disetujui_ikut_terbayar(): void
    {
        $p = $this->pekerja();
        $this->upah($p);

        $periode = $this->periode();
        [$dari] = Penggajian::rentang($periode);

        foreach ([['disetujui', 500_000], ['menunggu', 700_000], ['ditolak', 900_000]] as $i => [$status, $nilai]) {
            Lembur::withoutGlobalScopes()->create([
                'company_id' => $this->c->id,
                'pekerja_id' => $p->id,
                'tanggal'    => $dari->copy()->addDays($i),
                'jam'        => 2,
                'nilai'      => $nilai,
                'status'     => $status,
            ]);
        }

        Penggajian::hitung($periode, $this->admin);

        $this->assertSame(500_000.0, SlipGaji::withoutGlobalScopes()->firstOrFail()->lembur);
    }

    #[Test]
    public function test_iuran_dihitung_atas_upah_tetap_bukan_atas_bruto(): void
    {
        /* Dihitung dari bruto, iuran seseorang naik turun tiap bulan
           mengikuti lemburnya — dan saldo jaminan hari tuanya menjadi
           angka yang tidak dapat dijelaskan kepada siapa pun. */
        $p = $this->pekerja();
        $this->upah($p, 10_000_000, 0);

        $periode = $this->periode();
        [$dari] = Penggajian::rentang($periode);

        Lembur::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'pekerja_id' => $p->id,
            'tanggal' => $dari->copy(), 'jam' => 20, 'nilai' => 5_000_000, 'status' => 'disetujui',
        ]);

        Penggajian::hitung($periode, $this->admin);

        $s = SlipGaji::withoutGlobalScopes()->firstOrFail();

        /* Iuran karyawan atas 10 juta: kesehatan 1% + JHT 2% + JP 1% */
        $tangan = 100_000 + 200_000 + 100_000;

        $this->assertSame((float) $tangan, $s->bpjs_karyawan);
    }

    #[Test]
    public function test_bulan_biasa_memakai_ter_desember_memakai_progresif(): void
    {
        $p = $this->pekerja('TK/0');
        $this->upah($p, 8_000_000, 0);

        $biasa = $this->periode(6, 2026);
        Penggajian::hitung($biasa, $this->admin);

        $desember = $this->periode(12, 2026);
        Penggajian::hitung($desember, $this->admin);

        $sBiasa = SlipGaji::withoutGlobalScopes()->where('periode_id', $biasa->id)->firstOrFail();
        $sDes   = SlipGaji::withoutGlobalScopes()->where('periode_id', $desember->id)->firstOrFail();

        $this->assertSame('ter', $sBiasa->rincian['pajak']['cara']);
        $this->assertNotNull($sBiasa->ter_kategori);

        $this->assertSame('progresif', $sDes->rincian['pajak']['cara']);
        $this->assertNull($sDes->ter_kategori);
    }

    #[Test]
    public function test_rekonsiliasi_desember_memperhitungkan_slip_bulan_sebelumnya(): void
    {
        $p = $this->pekerja('TK/0');
        $this->upah($p, 8_000_000, 0);

        for ($b = 1; $b <= 11; $b++) Penggajian::hitung($this->periode($b, 2026), $this->admin);

        $desember = $this->periode(12, 2026);
        Penggajian::hitung($desember, $this->admin);

        $s = SlipGaji::withoutGlobalScopes()->where('periode_id', $desember->id)->firstOrFail();

        $sudah = (float) SlipGaji::withoutGlobalScopes()
            ->where('pekerja_id', $p->id)
            ->whereHas('periode', fn ($q) => $q->where('bulan', '<', 12))
            ->sum('pph21');

        $this->assertSame(round($sudah), round($s->rincian['pajak']['sudah']),
            'Rekonsiliasi Desember tidak memperhitungkan potongan Januari sampai November.');

        $this->assertGreaterThan(0, $s->rincian['pajak']['bruto']);
    }

    #[Test]
    public function test_desember_yang_dihitung_ulang_tidak_menghitung_dirinya_sendiri(): void
    {
        /* Slip Desember yang sudah ada akan ikut terjaring sebagai
           "sudah dipotong" bila bulannya tidak disaring — dan tiap
           penghitungan ulang mengurangi pajak setahun dengan potongan
           Desember yang justru sedang dihitung. Angkanya mengecil tiap
           kali tombolnya ditekan, tanpa satu galat pun. Ditemukan uji
           mutasi. */
        $p = $this->pekerja('TK/0');
        $this->upah($p, 20_000_000, 0);

        for ($b = 1; $b <= 11; $b++) Penggajian::hitung($this->periode($b, 2026), $this->admin);

        $desember = $this->periode(12, 2026);

        Penggajian::hitung($desember, $this->admin);
        $pertama = SlipGaji::withoutGlobalScopes()->where('periode_id', $desember->id)->firstOrFail();

        Penggajian::hitung($desember->fresh(), $this->admin);
        $kedua = SlipGaji::withoutGlobalScopes()->where('periode_id', $desember->id)->firstOrFail();

        $this->assertSame($pertama->pph21, $kedua->pph21,
            'Penghitungan ulang Desember mengubah pajaknya sendiri.');

        $this->assertSame(
            round($pertama->rincian['pajak']['sudah']),
            round($kedua->rincian['pajak']['sudah']),
        );
    }

    #[Test]
    public function test_menghitung_ulang_tidak_menggandakan_slip(): void
    {
        $p = $this->pekerja();
        $this->upah($p);

        $periode = $this->periode();

        Penggajian::hitung($periode, $this->admin);
        $kedua = Penggajian::hitung($periode->fresh(), $this->admin);

        $this->assertSame(0, $kedua['dibuat']);
        $this->assertSame(1, $kedua['diperbarui']);
        $this->assertSame(1, SlipGaji::withoutGlobalScopes()->count());
    }

    /* ═══════════════════ penguncian ═══════════════════ */

    #[Test]
    public function test_periode_tidak_dapat_dikunci_selama_acuan_belum_diverifikasi(): void
    {
        /* Tabel tarif yang diketik dari ingatan menghasilkan angka yang
           tampak sama meyakinkannya dengan tabel yang sudah dicocokkan
           dengan naskah peraturannya. */
        $p = $this->pekerja();
        $this->upah($p);

        $periode = $this->periode();
        Penggajian::hitung($periode, $this->admin);

        $alasan = Penggajian::kunci($periode->fresh(), $this->admin);

        $this->assertNotNull($alasan);
        $this->assertStringContainsString('belum diverifikasi', $alasan);
        $this->assertSame('terhitung', $periode->fresh()->status);
    }

    #[Test]
    public function test_acuan_terpasang_menyala_belum_terverifikasi(): void
    {
        /* Menandainya "sudah benar" sejak awal akan membuat
           ketidakpastian hilang dari pandangan tanpa pernah hilang dari
           datanya. */
        $this->assertNotEmpty(Penggajian::acuanBelumTerverifikasi());
        $this->assertSame(4, DB::table('pay_acuan')->where('terverifikasi', false)->count());
    }

    #[Test]
    public function test_pemasangan_ulang_tidak_mencabut_verifikasi_yang_sudah_dikerjakan(): void
    {
        $this->verifikasiSemua();

        MasterPajak::pasang();

        $this->assertSame([], Penggajian::acuanBelumTerverifikasi());
    }

    #[Test]
    public function test_periode_terkunci_tidak_dapat_dihitung_ulang(): void
    {
        $p = $this->pekerja();
        $this->upah($p);

        $periode = $this->periode();
        Penggajian::hitung($periode, $this->admin);

        $this->verifikasiSemua();

        $this->assertNull(Penggajian::kunci($periode->fresh(), $this->admin));
        $this->assertSame('terkunci', $periode->fresh()->status);

        $hasil = Penggajian::hitung($periode->fresh(), $this->admin);

        $this->assertIsString($hasil);
        $this->assertStringContainsString('terkunci', $hasil);
    }

    #[Test]
    public function test_periode_yang_belum_dihitung_tidak_dapat_dikunci(): void
    {
        $this->verifikasiSemua();

        $alasan = Penggajian::kunci($this->periode(), $this->admin);

        $this->assertNotNull($alasan);
        $this->assertStringContainsString('belum dihitung', $alasan);
    }

    /* ═══════════════════ layar ═══════════════════ */

    #[Test]
    public function test_tiap_halaman_gaji_terbuka(): void
    {
        $this->actingAs($this->admin);

        foreach (['/hris/gaji', '/hris/gaji/acuan'] as $alamat) {
            $this->get($alamat)->assertOk();
        }
    }

    #[Test]
    public function test_bukan_admin_tidak_dapat_menandai_acuan_atau_mengunci(): void
    {
        /* Tanda itulah yang membuka kunci periode gaji. Dibuka untuk
           semua, siapa pun dapat mencentangnya tanpa pernah membuka
           naskah peraturannya. */
        $biasa = User::factory()->create(['company_id' => $this->c->id]);

        $acuan = DB::table('pay_acuan')->first();

        $p = $this->pekerja();
        $this->upah($p);
        $periode = $this->periode();
        Penggajian::hitung($periode, $this->admin);

        $this->actingAs($biasa)
            ->post('/hris/gaji/acuan/'.$acuan->id, ['terverifikasi' => true])
            ->assertForbidden();

        $this->actingAs($biasa)->post('/hris/gaji/'.$periode->id.'/kunci')->assertForbidden();

        $this->assertFalse((bool) DB::table('pay_acuan')->where('id', $acuan->id)->value('terverifikasi'));
    }

    #[Test]
    public function test_menandai_acuan_mencatat_siapa_dan_kapan(): void
    {
        /* Tanda tanpa nama adalah tanda yang tidak dapat ditanyakan
           kepada siapa pun ketika angkanya ternyata keliru. */
        $acuan = DB::table('pay_acuan')->first();

        $this->actingAs($this->admin)
            ->post('/hris/gaji/acuan/'.$acuan->id, ['terverifikasi' => true])
            ->assertRedirect();

        $segar = DB::table('pay_acuan')->where('id', $acuan->id)->first();

        $this->assertTrue((bool) $segar->terverifikasi);
        $this->assertSame($this->admin->id, $segar->diverifikasi_oleh);
        $this->assertNotNull($segar->diverifikasi_pada);
    }

    #[Test]
    public function test_mencabut_tanda_membuang_nama_pemeriksanya(): void
    {
        $acuan = DB::table('pay_acuan')->first();

        $this->actingAs($this->admin)->post('/hris/gaji/acuan/'.$acuan->id, ['terverifikasi' => true]);
        $this->actingAs($this->admin)->post('/hris/gaji/acuan/'.$acuan->id, ['terverifikasi' => false]);

        $segar = DB::table('pay_acuan')->where('id', $acuan->id)->first();

        $this->assertFalse((bool) $segar->terverifikasi);
        $this->assertNull($segar->diverifikasi_oleh);
    }

    #[Test]
    public function test_periode_yang_sama_tidak_dapat_dibuat_dua_kali(): void
    {
        $this->actingAs($this->admin)->post('/hris/gaji', ['tahun' => 2026, 'bulan' => 7])->assertRedirect();
        $this->actingAs($this->admin)->post('/hris/gaji', ['tahun' => 2026, 'bulan' => 7])
            ->assertSessionHasErrors('bulan');

        $this->assertSame(1, PeriodeGaji::withoutGlobalScopes()->count());
    }
}
