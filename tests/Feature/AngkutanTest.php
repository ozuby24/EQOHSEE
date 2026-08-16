<?php

namespace Tests\Feature;

use App\Models\{AngkutAlat, AngkutMuatan, AngkutRegu, Company, TindakLanjut, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Dispatch dan Pengangkutan.
 *
 * Tiga hal yang paling penting dijaga di sini:
 *
 * 1. Muatan berlebih memicu peringatan tanpa menunggu tinjauan. Ia
 *    pembacaan alat, bukan pendapat, dan akibatnya jatuh pada rem truk
 *    yang menuruni jalan angkut hari ini.
 *
 * 2. Match factor memakai waktu edar TANPA antre. Memakai waktu edar
 *    nyata membuat armada yang kelebihan truk terbaca seimbang, dan
 *    kekeliruan itu tidak menimbulkan galat apa pun — hanya angka yang
 *    tenang pada armada yang boros.
 *
 * 3. Angka turunan hanya dihitung dari catatan yang sudah disetujui.
 */
class AngkutanTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $pengawas;
    private User $ktt;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-15 08:00:00');

        $this->company = Company::create(['name' => 'Tambang Uji', 'doc_no_prefix' => 'TU']);
        $this->pengawas = User::factory()->create(['company_id' => $this->company->id]);
        $this->ktt = User::factory()->create(['company_id' => $this->company->id, 'lms_role' => 'ktt']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function truk(array $ganti = []): AngkutAlat
    {
        return AngkutAlat::create(array_merge([
            'company_id' => $this->company->id, 'kode' => 'DT-001', 'nama' => 'Dump Truck 1',
            'kelas' => 'truk', 'tipe' => 'HD785-7', 'kapasitas_ton' => 91, 'aktif' => true,
        ], $ganti));
    }

    private function alatMuat(array $ganti = []): AngkutAlat
    {
        return AngkutAlat::create(array_merge([
            'company_id' => $this->company->id, 'kode' => 'EX-001', 'nama' => 'Excavator 1',
            'kelas' => 'alat-muat', 'tipe' => 'PC2000-8', 'kapasitas_bucket_m3' => 12, 'aktif' => true,
        ], $ganti));
    }

    /** Regu seimbang: 5 truk, muat 4 menit, edar produktif 20 menit → MF 1,0. */
    private function regu(array $ganti = []): AngkutRegu
    {
        return AngkutRegu::create(array_merge([
            'company_id' => $this->company->id, 'kode' => 'RG-01',
            'tanggal' => '2026-08-10', 'shift' => '1', 'pit' => 'Pit Utara',
            'material' => 'overburden', 'jumlah_alat_muat' => 1, 'jumlah_truk' => 5,
            'jarak_km' => 3.4,
            'waktu_muat_menit' => 4, 'waktu_angkut_menit' => 8,
            'waktu_tumpah_menit' => 2, 'waktu_kembali_menit' => 6, 'waktu_antre_menit' => 2,
            'ritase' => 90, 'tonase' => 8010, 'jam_kerja' => 9, 'jam_delay' => 1,
        ], $ganti));
    }

    private function setujui(AngkutRegu $r): void
    {
        $this->actingAs($this->pengawas)->post(route('angkutan.ajukan', $r));
        $this->actingAs($this->ktt)->post(route('angkutan.setujui', $r));
    }

    /* ---------- muatan berlebih tidak menunggu tinjauan ---------- */

    public function test_muatan_di_atas_120_persen_memicu_peringatan_tanpa_tinjauan(): void
    {
        $truk = $this->truk();
        $regu = $this->regu();   // sengaja dibiarkan draf

        AngkutMuatan::create([
            'company_id' => $this->company->id, 'angkut_regu_id' => $regu->id,
            'angkut_alat_id' => $truk->id, 'rit_ke' => 3, 'muatan_ton' => 115,
        ]);

        $this->actingAs($this->pengawas)->get(route('angkutan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->contains(
                    fn ($x) => str_contains($x['kode'], 'muatan-puncak') && $x['level'] === 'tinggi'
                )));
    }

    public function test_muatan_dalam_batas_tidak_memicu_peringatan_puncak(): void
    {
        $truk = $this->truk();
        $regu = $this->regu();

        AngkutMuatan::create([
            'company_id' => $this->company->id, 'angkut_regu_id' => $regu->id,
            'angkut_alat_id' => $truk->id, 'muatan_ton' => 88,
        ]);

        $this->actingAs($this->pengawas)->get(route('angkutan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->doesntContain(
                    fn ($x) => str_contains($x['kode'], 'muatan-puncak')
                )));
    }

    public function test_penimbangan_boleh_ditambahkan_pada_regu_yang_sudah_disetujui(): void
    {
        $truk = $this->truk();
        $regu = $this->regu();
        $this->setujui($regu);

        // Penimbangan bukan bagian dari angka yang ditinjau, melainkan
        // pembacaan alat yang menilai angka itu — sehingga penguncian
        // baris yang sudah disetujui tidak boleh ikut menutupnya.
        $this->actingAs($this->pengawas)->post(route('angkutan.muatan.simpan', $regu), [
            'angkut_alat_id' => $truk->id, 'muatan_ton' => 92,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, AngkutMuatan::count());
    }

    /* ---------- match factor tidak menghitung antre ---------- */

    public function test_match_factor_memakai_waktu_edar_tanpa_antre(): void
    {
        // 5 truk, muat 4 menit, edar produktif 20 menit → 1,0.
        // Bila antre 8 menit ikut dijumlahkan, angkanya menjadi 0,71 dan
        // regu yang seimbang terbaca kekurangan truk.
        $regu = $this->regu(['waktu_antre_menit' => 8]);

        $this->assertSame(1.0, $regu->matchFactor());
        $this->assertSame(28.0, $regu->waktuEdarNyata());
    }

    public function test_kelebihan_truk_terbaca_sebagai_peringatan(): void
    {
        $regu = $this->regu(['jumlah_truk' => 8]);   // MF 1,6
        $this->setujui($regu);

        $this->actingAs($this->pengawas)->get(route('angkutan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->contains(
                    fn ($x) => str_contains($x['kode'], 'match-factor')
                )));
    }

    public function test_regu_seimbang_tidak_memicu_peringatan_keseimbangan(): void
    {
        $regu = $this->regu();
        $this->setujui($regu);

        $this->actingAs($this->pengawas)->get(route('angkutan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->doesntContain(
                    fn ($x) => str_contains($x['kode'], 'match-factor')
                )));
    }

    /* ---------- hanya yang disetujui masuk hitungan ---------- */

    public function test_draf_tidak_masuk_ringkasan(): void
    {
        $this->regu();

        $this->actingAs($this->pengawas)->get(route('angkutan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('ringkas.regu', 1)
                ->where('ringkas.disetujui', 0)
                ->where('ringkas.ritase', 0)
                ->where('ringkas.tonase', 0));
    }

    public function test_yang_disetujui_masuk_ringkasan(): void
    {
        $regu = $this->regu();
        $this->setujui($regu);

        $this->actingAs($this->pengawas)->get(route('angkutan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('ringkas.disetujui', 1)
                ->where('ringkas.ritase', 90)
                ->where('ringkas.tonase', 8010));
    }

    public function test_draf_tidak_memicu_peringatan_keseimbangan(): void
    {
        // Angka turunan berasal dari waktu yang dilaporkan sendiri; satu
        // salah ketik sudah cukup melahirkan peringatan tanpa kejadian.
        $this->regu(['jumlah_truk' => 8]);

        $this->actingAs($this->pengawas)->get(route('angkutan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->doesntContain(
                    fn ($x) => str_contains($x['kode'], 'match-factor')
                )));
    }

    /* ---------- alur persetujuan ---------- */

    public function test_pengaju_tidak_boleh_menyetujui_catatannya_sendiri(): void
    {
        $regu = $this->regu();

        $this->actingAs($this->pengawas)->post(route('angkutan.ajukan', $regu));
        $this->actingAs($this->pengawas)->post(route('angkutan.setujui', $regu))
            ->assertSessionHasErrors('alur');

        $this->assertSame('diajukan', $regu->fresh()->status);
    }

    public function test_catatan_baru_selalu_lahir_sebagai_draf(): void
    {
        $this->actingAs($this->pengawas)->post(route('angkutan.regu.simpan'), [
            'kode' => 'RG-99', 'tanggal' => '2026-08-11', 'shift' => '1',
            'material' => 'batubara', 'jumlah_alat_muat' => 1, 'jumlah_truk' => 5,
            'waktu_muat_menit' => 4, 'waktu_angkut_menit' => 8,
            'waktu_tumpah_menit' => 2, 'waktu_kembali_menit' => 6,
            'ritase' => 10, 'tonase' => 900, 'jam_kerja' => 8,
            'status' => 'disetujui',       // dicoba dititipkan lewat isian
        ])->assertSessionHasNoErrors();

        $this->assertSame('draf', AngkutRegu::where('kode', 'RG-99')->first()->status);
    }

    public function test_catatan_disetujui_tidak_dapat_dihapus(): void
    {
        $admin = User::factory()->create(['company_id' => $this->company->id, 'is_admin' => true]);
        $regu = $this->regu();
        $this->setujui($regu);

        $this->actingAs($admin)->delete(route('angkutan.regu.hapus', $regu))
            ->assertSessionHasErrors('alur');

        $this->assertSame(1, AngkutRegu::count());
    }

    /* ---------- penjagaan isian ---------- */

    public function test_jam_kerja_dan_delay_tidak_boleh_melebihi_sehari(): void
    {
        $this->actingAs($this->pengawas)->post(route('angkutan.regu.simpan'), [
            'kode' => 'RG-98', 'tanggal' => '2026-08-11', 'shift' => '1',
            'material' => 'overburden', 'jumlah_alat_muat' => 1, 'jumlah_truk' => 5,
            'waktu_muat_menit' => 4, 'waktu_angkut_menit' => 8,
            'waktu_tumpah_menit' => 2, 'waktu_kembali_menit' => 6,
            'ritase' => 10, 'tonase' => 900, 'jam_kerja' => 20, 'jam_delay' => 8,
        ])->assertSessionHasErrors('jam_kerja');

        $this->assertSame(0, AngkutRegu::count());
    }

    public function test_truk_tidak_boleh_ditunjuk_sebagai_alat_muat(): void
    {
        // Menunjuk truk sebagai alat muat menghasilkan match factor yang
        // terbaca wajar dan tidak bermakna apa pun.
        $truk = $this->truk();

        $this->actingAs($this->pengawas)->post(route('angkutan.regu.simpan'), [
            'kode' => 'RG-97', 'tanggal' => '2026-08-11', 'shift' => '1',
            'material' => 'overburden', 'alat_muat_id' => $truk->id,
            'jumlah_alat_muat' => 1, 'jumlah_truk' => 5,
            'waktu_muat_menit' => 4, 'waktu_angkut_menit' => 8,
            'waktu_tumpah_menit' => 2, 'waktu_kembali_menit' => 6,
            'ritase' => 10, 'tonase' => 900, 'jam_kerja' => 8,
        ])->assertSessionHasErrors('alat_muat_id');

        $this->assertSame(0, AngkutRegu::count());
    }

    public function test_alat_muat_yang_benar_diterima(): void
    {
        $ex = $this->alatMuat();

        $this->actingAs($this->pengawas)->post(route('angkutan.regu.simpan'), [
            'kode' => 'RG-96', 'tanggal' => '2026-08-11', 'shift' => '1',
            'material' => 'overburden', 'alat_muat_id' => $ex->id,
            'jumlah_alat_muat' => 1, 'jumlah_truk' => 5,
            'waktu_muat_menit' => 4, 'waktu_angkut_menit' => 8,
            'waktu_tumpah_menit' => 2, 'waktu_kembali_menit' => 6,
            'ritase' => 10, 'tonase' => 900, 'jam_kerja' => 8,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, AngkutRegu::count());
    }

    /* ---------- kapasitas nominal ---------- */

    public function test_truk_tanpa_kapasitas_nominal_diperingatkan(): void
    {
        $this->truk(['kapasitas_ton' => null]);

        $this->actingAs($this->pengawas)->get(route('angkutan.armada'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->contains(
                    fn ($x) => $x['kode'] === 'kapasitas-kosong' && $x['level'] === 'tinggi'
                )));
    }

    public function test_muatan_pada_truk_tanpa_kapasitas_tidak_dinilai(): void
    {
        $truk = $this->truk(['kapasitas_ton' => null]);
        $regu = $this->regu();

        AngkutMuatan::create([
            'company_id' => $this->company->id, 'angkut_regu_id' => $regu->id,
            'angkut_alat_id' => $truk->id, 'muatan_ton' => 200,
        ]);

        $this->actingAs($this->pengawas)->get(route('angkutan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('kepatuhan.n', 0)
                ->where('kepatuhan.patuh', null));
    }

    /* ---------- kecepatan ---------- */

    public function test_batas_kecepatan_kosong_tidak_dinilai(): void
    {
        $regu = $this->regu(['batas_kecepatan_kmh' => null]);
        $this->setujui($regu);

        $this->actingAs($this->pengawas)->get(route('angkutan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('ringkas.lampauiKecepatan', 0)
                ->where('alerts', fn ($a) => collect($a)->contains(
                    fn ($x) => $x['kode'] === 'batas-kecepatan-kosong'
                )));
    }

    public function test_kecepatan_di_atas_batas_diperingatkan(): void
    {
        // 3,4 km sekali jalan, 8 + 6 menit → 29,1 km/jam terhadap batas 25.
        $regu = $this->regu(['batas_kecepatan_kmh' => 25]);
        $this->setujui($regu);

        $this->actingAs($this->pengawas)->get(route('angkutan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('ringkas.lampauiKecepatan', 1)
                ->where('alerts', fn ($a) => collect($a)->contains(
                    fn ($x) => str_contains($x['kode'], 'kecepatan-') && $x['level'] === 'tinggi'
                )));
    }

    /* ---------- antre ---------- */

    public function test_antre_berlebih_diperingatkan_beserta_tonase_hilangnya(): void
    {
        // 12 dari 32 menit = 37,5%, jauh di atas batas wajar 15%.
        $regu = $this->regu(['waktu_antre_menit' => 12]);
        $this->setujui($regu);

        $this->actingAs($this->pengawas)->get(route('angkutan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->contains(
                    fn ($x) => str_contains($x['kode'], 'antre-')
                ))
                ->where('ringkas.hilangAntre', fn ($v) => $v > 0));
    }

    /* ---------- halaman ---------- */

    public function test_seluruh_halaman_terbuka(): void
    {
        $this->truk();
        $this->alatMuat();
        $this->regu();

        foreach (['index', 'regu', 'armada', 'muatan', 'cetak'] as $rute) {
            $this->actingAs($this->pengawas)->get(route("angkutan.{$rute}"))->assertOk();
        }
    }

    public function test_laporan_memakai_kop_dokumen_terkendali(): void
    {
        $this->actingAs($this->pengawas)->get(route('angkutan.cetak'))
            ->assertInertia(fn (Assert $p) => $p
                ->component('Print/Angkutan')
                ->where('dok.nomor', 'TU-OHSE-V.101'));
    }

    /* ---------- pemisahan antar perusahaan ---------- */

    public function test_catatan_perusahaan_lain_tidak_terlihat(): void
    {
        $lain = Company::create(['name' => 'Tambang Lain']);
        $this->regu(['company_id' => $lain->id, 'kode' => 'RG-LAIN']);
        $this->regu();

        $this->actingAs($this->pengawas)->get(route('angkutan.regu'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('regu', fn ($r) => collect($r)->pluck('kode')->all() === ['RG-01']));
    }

    /* ---------- tindak lanjut ---------- */

    public function test_tindak_lanjut_menutup_lingkaran_peringatan(): void
    {
        $regu = $this->regu(['jumlah_truk' => 8]);
        $this->setujui($regu);

        $this->actingAs($this->pengawas)->post(route('angkutan.tindak.simpan'), [
            'kode_pemicu' => 'match-factor-'.$regu->id,
            'judul' => 'Kurangi tiga truk dari RG-01',
            'prioritas' => 'sedang',
        ])->assertSessionHasNoErrors();

        $this->assertSame('angkutan', TindakLanjut::first()->modul);

        $this->actingAs($this->pengawas)->get(route('angkutan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('kodeDitangani', fn ($k) => collect($k)->contains('match-factor-'.$regu->id)));
    }
}
