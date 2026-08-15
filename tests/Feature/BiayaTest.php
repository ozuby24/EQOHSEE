<?php

namespace Tests\Feature;

use App\Models\{BiayaAkun, BiayaAnggaran, BiayaRealisasi, Company,
                MineOperationalRecord, MineOperationalTarget, TindakLanjut, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Pengendalian biaya operasi.
 *
 * Tiga hal yang paling penting dijaga:
 *
 * 1. Hanya realisasi yang sudah disetujui yang dihitung. Biaya yang
 *    belum tercatat membuat setiap indikator membaik sekaligus — serapan
 *    turun, biaya per ton turun, selisih berbalik menghemat — dan tidak
 *    satu pun angka tampak ganjil.
 *
 * 2. Denominator produksi datang dari Mine Operations yang disetujui,
 *    tidak diketik ulang. Tanpa catatan yang disetujui, biaya per ton
 *    kosong, bukan nol.
 *
 * 3. Selisih dipecah menjadi bagian volume dan bagian tarif, dan
 *    keduanya berjumlah tepat selisih totalnya.
 */
class BiayaTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $pengendali;
    private User $ktt;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-15 08:00:00');

        $this->company = Company::create(['name' => 'Tambang Uji']);
        $this->pengendali = User::factory()->create(['company_id' => $this->company->id]);
        $this->ktt = User::factory()->create(['company_id' => $this->company->id, 'lms_role' => 'ktt']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function akun(array $ganti = []): BiayaAkun
    {
        return BiayaAkun::create(array_merge([
            'company_id' => $this->company->id, 'kode' => 'BB-01', 'nama' => 'Solar alat berat',
            'kelompok' => 'bahan-bakar', 'jenis' => 'variabel', 'satuan' => 'liter', 'aktif' => true,
        ], $ganti));
    }

    private function anggaran(BiayaAkun $a, array $ganti = []): BiayaAnggaran
    {
        return BiayaAnggaran::create(array_merge([
            'company_id' => $this->company->id, 'biaya_akun_id' => $a->id, 'tahun' => 2026,
            'pusat_biaya' => 'penambangan', 'nilai_rp' => 1_000_000_000, 'kuantitas_rencana' => 68_965.5,
        ], $ganti));
    }

    private function realisasi(BiayaAkun $a, array $ganti = []): BiayaRealisasi
    {
        return BiayaRealisasi::create(array_merge([
            'company_id' => $this->company->id, 'user_id' => $this->pengendali->id,
            'biaya_akun_id' => $a->id, 'tahun' => 2026, 'bulan' => 1,
            'pusat_biaya' => 'penambangan', 'nilai_rp' => 100_000_000, 'kuantitas' => 6_500,
        ], $ganti));
    }

    private function setujui(BiayaRealisasi $r): void
    {
        $this->actingAs($this->pengendali)->post(route('biaya.ajukan', $r));
        $this->actingAs($this->ktt)->post(route('biaya.setujui', $r));
    }

    /** Produksi nyata yang sudah disetujui pada 2026. */
    private function produksiNyata(float $ton = 120_000, float $bcm = 960_000): void
    {
        $r = MineOperationalRecord::create([
            'company_id' => $this->company->id, 'user_id' => $this->pengendali->id,
            'tanggal' => '2026-03-10', 'shift' => 'siang', 'pit' => 'Pit Utara',
            'produksi_ton' => $ton, 'overburden_bcm' => $bcm, 'jam_operasi' => 9,
        ]);

        $r->ajukan($this->pengendali);
        $r->setujui($this->ktt);
    }

    private function targetProduksi(float $ton = 100_000, float $bcm = 800_000): void
    {
        MineOperationalTarget::create([
            'company_id' => $this->company->id, 'tahun' => 2026, 'bulan' => 1,
            'target_produksi_ton' => $ton, 'target_overburden_bcm' => $bcm,
        ]);
    }

    /* ---------- hanya yang disetujui dihitung ---------- */

    public function test_realisasi_draf_tidak_masuk_hitungan(): void
    {
        $a = $this->akun();
        $this->anggaran($a);
        $this->realisasi($a);

        $this->actingAs($this->pengendali)->get(route('biaya.index', ['tahun' => 2026]))
            ->assertInertia(fn (Assert $p) => $p
                ->where('total.realisasi', 0)
                ->where('menunggu', 0)
                ->where('total.anggaran', 1000000000));
    }

    public function test_realisasi_disetujui_masuk_hitungan(): void
    {
        $a = $this->akun();
        $this->anggaran($a);
        $this->setujui($this->realisasi($a));

        $this->actingAs($this->pengendali)->get(route('biaya.index', ['tahun' => 2026]))
            ->assertInertia(fn (Assert $p) => $p
                ->where('total.realisasi', 100000000)
                ->where('total.serapan', 10)
                ->where('bulanTerisi', 1));
    }

    public function test_yang_menunggu_tinjauan_dihitung_dan_diperingatkan(): void
    {
        $a = $this->akun();
        $this->anggaran($a);
        $r = $this->realisasi($a);
        $this->actingAs($this->pengendali)->post(route('biaya.ajukan', $r));

        $this->actingAs($this->pengendali)->get(route('biaya.index', ['tahun' => 2026]))
            ->assertInertia(fn (Assert $p) => $p
                ->where('menunggu', 1)
                ->where('total.realisasi', 0)
                ->where('alerts', fn ($x) => collect($x)->contains(
                    fn ($q) => $q['kode'] === 'menunggu-tinjauan'
                )));
    }

    /* ---------- denominator dari Mine Operations ---------- */

    public function test_tanpa_produksi_disetujui_biaya_per_ton_kosong_bukan_nol(): void
    {
        $a = $this->akun();
        $this->anggaran($a);
        $this->setujui($this->realisasi($a));

        $this->actingAs($this->pengendali)->get(route('biaya.index', ['tahun' => 2026]))
            ->assertInertia(fn (Assert $p) => $p
                ->where('total.perTon', null)
                ->where('produksi.adaData', false)
                ->where('alerts', fn ($x) => collect($x)->contains(
                    fn ($q) => $q['kode'] === 'produksi-belum-disetujui' && $q['level'] === 'tinggi'
                )));
    }

    public function test_produksi_diambil_dari_mine_operations_yang_disetujui(): void
    {
        $a = $this->akun();
        $this->anggaran($a);
        $this->setujui($this->realisasi($a, ['nilai_rp' => 600_000_000]));
        $this->produksiNyata(120_000, 960_000);
        $this->targetProduksi(100_000, 800_000);

        $this->actingAs($this->pengendali)->get(route('biaya.index', ['tahun' => 2026]))
            ->assertInertia(fn (Assert $p) => $p
                ->where('produksi.tonNyata', 120000)
                ->where('produksi.kemajuan', 120)
                ->where('total.perTon', 5000));
    }

    public function test_produksi_yang_belum_disetujui_tidak_menjadi_pembagi(): void
    {
        $a = $this->akun();
        $this->anggaran($a);
        $this->setujui($this->realisasi($a));

        // Catatan shift ada, tetapi masih draf.
        MineOperationalRecord::create([
            'company_id' => $this->company->id, 'tanggal' => '2026-03-10', 'shift' => 'siang',
            'produksi_ton' => 120_000, 'overburden_bcm' => 960_000,
        ]);

        $this->actingAs($this->pengendali)->get(route('biaya.index', ['tahun' => 2026]))
            ->assertInertia(fn (Assert $p) => $p
                ->where('produksi.tonNyata', 0)
                ->where('total.perTon', null));
    }

    /* ---------- pemecahan selisih ---------- */

    public function test_selisih_terpecah_menjadi_volume_dan_tarif(): void
    {
        $a = $this->akun();
        $this->anggaran($a);                                   // pagu 1 M
        $this->setujui($this->realisasi($a, ['nilai_rp' => 1_260_000_000]));
        $this->produksiNyata(120_000, 960_000);
        $this->targetProduksi(100_000, 800_000);

        $this->actingAs($this->pengendali)->get(route('biaya.index', ['tahun' => 2026]))
            ->assertInertia(fn (Assert $p) => $p
                ->where('total.varians.volume', 200000000)
                ->where('total.varians.tarif', 60000000)
                ->where('total.varians.total', 260000000));
    }

    public function test_serapan_dibandingkan_dengan_kemajuan_produksi(): void
    {
        $a = $this->akun();
        $this->anggaran($a);
        // 60% pagu terserap, produksi baru 45% dari target.
        $this->setujui($this->realisasi($a, ['nilai_rp' => 600_000_000]));
        $this->produksiNyata(45_000, 360_000);
        $this->targetProduksi(100_000, 800_000);

        $this->actingAs($this->pengendali)->get(route('biaya.index', ['tahun' => 2026]))
            ->assertInertia(fn (Assert $p) => $p
                ->where('bacaSerapan.kelas', 'mendahului')
                ->where('alerts', fn ($x) => collect($x)->contains(
                    fn ($q) => $q['kode'] === 'serapan-mendahului' && $q['level'] === 'tinggi'
                )));
    }

    public function test_nisbah_yang_bergeser_jauh_diperingatkan(): void
    {
        $a = $this->akun();
        $this->anggaran($a);
        $this->setujui($this->realisasi($a));
        $this->produksiNyata(100_000, 1_000_000);   // SR 10
        $this->targetProduksi(100_000, 800_000);    // SR 8 → bergeser 25%

        $this->actingAs($this->pengendali)->get(route('biaya.index', ['tahun' => 2026]))
            ->assertInertia(fn (Assert $p) => $p
                ->where('produksi.selisihSr', 25)
                ->where('alerts', fn ($x) => collect($x)->contains(
                    fn ($q) => $q['kode'] === 'nisbah-bergeser'
                )));
    }

    /* ---------- kelengkapan ---------- */

    public function test_tahun_tanpa_anggaran_diperingatkan(): void
    {
        $this->actingAs($this->pengendali)->get(route('biaya.index', ['tahun' => 2026]))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($x) => collect($x)->contains(
                    fn ($q) => $q['kode'] === 'anggaran-kosong' && $q['level'] === 'tinggi'
                )));
    }

    public function test_akun_berbiaya_tanpa_pagu_diperingatkan(): void
    {
        $a = $this->akun();
        $b = $this->akun(['kode' => 'BN-01', 'nama' => 'Ban', 'kelompok' => 'ban', 'satuan' => 'ban']);
        $this->anggaran($a);
        $this->setujui($this->realisasi($a));
        $this->setujui($this->realisasi($b, ['nilai_rp' => 50_000_000, 'kuantitas' => 12, 'bulan' => 2]));

        $this->actingAs($this->pengendali)->get(route('biaya.index', ['tahun' => 2026]))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($x) => collect($x)->contains(
                    fn ($q) => $q['kode'] === 'akun-tanpa-anggaran'
                               && str_contains($q['saran'], 'BN-01')
                )));
    }

    /* ---------- alur persetujuan ---------- */

    public function test_pengaju_tidak_boleh_menyetujui_catatannya_sendiri(): void
    {
        $a = $this->akun();
        $r = $this->realisasi($a);

        $this->actingAs($this->pengendali)->post(route('biaya.ajukan', $r));
        $this->actingAs($this->pengendali)->post(route('biaya.setujui', $r))
            ->assertSessionHasErrors('alur');

        $this->assertSame('diajukan', $r->fresh()->status);
    }

    public function test_realisasi_disetujui_tidak_dapat_ditimpa_lewat_simpan(): void
    {
        // Tanpa penjagaan ini, satu pengiriman ulang formulir mengubah
        // angka yang sudah masuk laporan tanpa jejak.
        $a = $this->akun();
        $r = $this->realisasi($a);
        $this->setujui($r);

        $this->actingAs($this->pengendali)->post(route('biaya.realisasi.simpan'), [
            'biaya_akun_id' => $a->id, 'tahun' => 2026, 'bulan' => 1,
            'pusat_biaya' => 'penambangan', 'nilai_rp' => 1, 'kuantitas' => 1,
        ])->assertSessionHasErrors('alur');

        $this->assertSame(100_000_000.0, $r->fresh()->nilai_rp);
    }

    public function test_realisasi_disetujui_tidak_dapat_dihapus(): void
    {
        $admin = User::factory()->create(['company_id' => $this->company->id, 'is_admin' => true]);
        $a = $this->akun();
        $r = $this->realisasi($a);
        $this->setujui($r);

        $this->actingAs($admin)->delete(route('biaya.realisasi.hapus', $r))
            ->assertSessionHasErrors('alur');

        $this->assertSame(1, BiayaRealisasi::count());
    }

    public function test_realisasi_baru_selalu_lahir_sebagai_draf(): void
    {
        $a = $this->akun();

        $this->actingAs($this->pengendali)->post(route('biaya.realisasi.simpan'), [
            'biaya_akun_id' => $a->id, 'tahun' => 2026, 'bulan' => 5,
            'pusat_biaya' => 'penambangan', 'nilai_rp' => 25_000_000, 'kuantitas' => 1_600,
            'status' => 'disetujui',        // dicoba dititipkan lewat isian
        ])->assertSessionHasNoErrors();

        $this->assertSame('draf', BiayaRealisasi::where('bulan', 5)->first()->status);
    }

    /* ---------- penjagaan isian ---------- */

    public function test_kuantitas_ditolak_pada_akun_tanpa_satuan(): void
    {
        // "Harga per apa" tidak dapat dijawab siapa pun, dan angkanya
        // ikut terbawa ke pemecahan harga terhadap pemakaian.
        $a = $this->akun(['kode' => 'UP-01', 'nama' => 'Upah borongan', 'kelompok' => 'upah', 'satuan' => null]);

        $this->actingAs($this->pengendali)->post(route('biaya.realisasi.simpan'), [
            'biaya_akun_id' => $a->id, 'tahun' => 2026, 'bulan' => 3,
            'pusat_biaya' => 'penambangan', 'nilai_rp' => 40_000_000, 'kuantitas' => 12,
        ])->assertSessionHasErrors('kuantitas');

        $this->assertSame(0, BiayaRealisasi::count());
    }

    public function test_akun_tanpa_satuan_tetap_dapat_dicatat_tanpa_kuantitas(): void
    {
        $a = $this->akun(['kode' => 'UP-01', 'nama' => 'Upah borongan', 'kelompok' => 'upah', 'satuan' => null]);

        $this->actingAs($this->pengendali)->post(route('biaya.realisasi.simpan'), [
            'biaya_akun_id' => $a->id, 'tahun' => 2026, 'bulan' => 3,
            'pusat_biaya' => 'penambangan', 'nilai_rp' => 40_000_000,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, BiayaRealisasi::count());
    }

    /* ---------- harga terhadap pemakaian ---------- */

    public function test_akun_bersatuan_memisahkan_harga_dari_pemakaian(): void
    {
        $a = $this->akun();
        // Rencana 1 M / 68.965,5 L ≈ 14.500/L. Nyata 1,1 M / 70.000 L ≈ 15.714/L.
        $this->anggaran($a);
        $this->setujui($this->realisasi($a, ['nilai_rp' => 1_100_000_000, 'kuantitas' => 70_000]));
        $this->produksiNyata();
        $this->targetProduksi();

        // Rencana 0,690 L/ton; nyata 0,583 L/ton pada tonase 20% di atas
        // rencana. Harganya naik — itu di luar kendali pit — tetapi
        // pemakaian per ton justru membaik, dan pemecahan ini yang
        // memisahkan keduanya.
        $this->actingAs($this->pengendali)->get(route('biaya.index', ['tahun' => 2026]))
            ->assertInertia(fn (Assert $p) => $p
                ->where('akun.0.hargaPakai.dapatDipecah', true)
                ->where('akun.0.hargaPakai.harga', fn ($v) => $v > 0)
                ->where('akun.0.hargaPakai.pakai', fn ($v) => $v < 0));
    }

    public function test_harga_dan_pemakaian_berjumlah_tepat_selisih_tarif(): void
    {
        // Pemecahan kedua menjelaskan sisa yang ditinggalkan pemecahan
        // pertama. Bila kuantitas rencana tidak ikut diluweskan, kedua
        // angka di layar berdiri di atas dasar berbeda dan tidak akan
        // pernah bertemu — dan pembacanya menyimpulkan salah satunya
        // keliru, padahal yang keliru adalah membandingkannya.
        $a = $this->akun();
        $this->anggaran($a);
        $this->setujui($this->realisasi($a, ['nilai_rp' => 1_100_000_000, 'kuantitas' => 70_000]));
        $this->produksiNyata(120_000, 960_000);
        $this->targetProduksi(100_000, 800_000);

        $p = $this->actingAs($this->pengendali)->get(route('biaya.index', ['tahun' => 2026]))
            ->viewData('page')['props']['akun'][0];

        $this->assertTrue($p['hargaPakai']['dapatDipecah']);
        $this->assertEqualsWithDelta(
            $p['varians']['tarif'],
            $p['hargaPakai']['harga'] + $p['hargaPakai']['pakai'],
            1.0,
        );
    }

    /* ---------- halaman ---------- */

    public function test_seluruh_halaman_terbuka(): void
    {
        $a = $this->akun();
        $this->anggaran($a);
        $this->realisasi($a);

        foreach (['index', 'realisasi', 'anggaran', 'akun', 'cetak'] as $rute) {
            $this->actingAs($this->pengendali)->get(route("biaya.{$rute}", ['tahun' => 2026]))->assertOk();
        }
    }

    public function test_laporan_memakai_kop_dokumen_terkendali(): void
    {
        $this->actingAs($this->pengendali)->get(route('biaya.cetak', ['tahun' => 2026]))
            ->assertInertia(fn (Assert $p) => $p
                ->component('Print/Biaya')
                ->where('dok.nomor', 'TU-OHSE-V.111'));
    }

    /* ---------- pemisahan antar perusahaan ---------- */

    public function test_biaya_perusahaan_lain_tidak_terlihat(): void
    {
        $lain = Company::create(['name' => 'Tambang Lain']);
        $this->akun(['company_id' => $lain->id, 'kode' => 'LAIN-01']);
        $this->akun();

        $this->actingAs($this->pengendali)->get(route('biaya.akun', ['tahun' => 2026]))
            ->assertInertia(fn (Assert $p) => $p
                ->where('daftarAkun', fn ($x) => collect($x)->pluck('kode')->all() === ['BB-01']));
    }

    /* ---------- tindak lanjut ---------- */

    public function test_tindak_lanjut_menutup_lingkaran_peringatan(): void
    {
        $this->actingAs($this->pengendali)->post(route('biaya.tindak.simpan'), [
            'kode_pemicu' => 'anggaran-kosong',
            'judul' => 'Salin pagu RKAB 2026',
            'prioritas' => 'tinggi',
        ])->assertSessionHasNoErrors();

        $this->assertSame('biaya', TindakLanjut::first()->modul);

        $this->actingAs($this->pengendali)->get(route('biaya.index', ['tahun' => 2026]))
            ->assertInertia(fn (Assert $p) => $p
                ->where('kodeDitangani', fn ($k) => collect($k)->contains('anggaran-kosong')));
    }
}
