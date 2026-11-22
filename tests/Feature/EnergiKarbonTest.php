<?php

namespace Tests\Feature;

use App\Models\{Company, EnergyBaseline, EnergyEquipment, EnergyFuelLog, EnergyFuelRecon, EnergyPowerLog,
                EnergyProduction, TindakLanjut, User};
use App\Models\Energi\{Faktor, GrkBatas, Koreksi, Laporan, Periode, Personel};
use App\Support\Energi\{Ambang, AlurPeriode, Anomali, Enpi, Inventaris, Kalkulator, KendaliDireksi, PaketFaktor,
                        Pelaporan, RegistriFaktor};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Manajemen Energi & Karbon (PRD/FSD Energi-Karbon §16).
 *
 * Yang diuji terutama yang tidak terlihat di layar: angka rumus terhadap
 * contoh FSD, pemilihan faktor menurut tanggal & prioritas, batas
 * perusahaan pada seluruh jalur baru, dan bahwa laporan yang sudah terbit
 * tidak berubah ketika faktor diperbarui.
 */
class EnergiKarbonTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;
    private Company $b;

    protected function setUp(): void
    {
        parent::setUp();

        PaketFaktor::pasang();
        $this->a = Company::create(['name' => 'PT Tambang A', 'doc_no_prefix' => 'TA']);
        $this->b = Company::create(['name' => 'PT Tambang B', 'doc_no_prefix' => 'TB']);
    }

    private function orang(Company $c, ?string $peran = null, array $lain = []): User
    {
        return User::factory()->create(['company_id' => $c->id, 'energi_role' => $peran, 'email_verified_at' => now()] + $lain);
    }

    private function unit(Company $c, string $kode = 'HD-01'): EnergyEquipment
    {
        return EnergyEquipment::withoutGlobalScopes()->create(['company_id' => $c->id, 'kode' => $kode, 'nama' => 'Dump truck', 'kategori' => 'hauling']);
    }

    /* ═══════════ rumus (FSD §16.1 KalkulatorTest) ═══════════ */

    public function test_seribu_liter_solar_b0_sesuai_contoh_fsd(): void
    {
        $h = Kalkulator::hitung('solar', 1000, '2026-08-01', ['persen_fame' => 0, 'kategori_ipcc' => 'stasioner']);

        $this->assertEqualsWithDelta(36.12, $h['gj'], 0.0001);
        $this->assertEqualsWithDelta(2.6765, $h['co2_fosil_t'], 0.0005);
        $this->assertSame(0.0, $h['co2_bio_t']);
        // CH₄ 3 kg/TJ, N₂O 0,6 kg/TJ (stasioner) dengan GWP AR5 28 & 265.
        $this->assertEqualsWithDelta(2.676492 + 36.12 * 3 / 1e6 * 28 + 36.12 * 0.6 / 1e6 * 265, $h['co2e_t'], 1e-6);
        $this->assertSame([], $h['kurang']);
    }

    public function test_campuran_fame_memisahkan_co2_biogenik(): void
    {
        $b0 = Kalkulator::hitung('solar', 1000, '2026-08-01', ['persen_fame' => 0]);
        $b40 = Kalkulator::hitung('solar', 1000, '2026-08-01', ['persen_fame' => 40]);
        $bawaan = Kalkulator::hitung('solar', 1000, '2026-08-01');   // B50 sejak 1 Juli 2026

        $this->assertLessThan($b0['co2_fosil_t'], $b40['co2_fosil_t']);
        $this->assertGreaterThan(0, $b40['co2_bio_t']);
        $this->assertEqualsWithDelta(0.6 * 0.84 * 43.0, $b40['co2_fosil_t'] / 74100 * 1e6 * 1000 / 1000, 0.001);
        $this->assertSame(50.0, $bawaan['persen_fame']);
        $this->assertLessThan($b40['co2_fosil_t'], $bawaan['co2_fosil_t']);
    }

    public function test_campuran_mengikuti_tanggal_aktivitas(): void
    {
        $this->assertSame(40.0, Kalkulator::hitung('solar', 100, '2026-06-30')['persen_fame']);
        $this->assertSame(50.0, Kalkulator::hitung('solar', 100, '2026-07-01')['persen_fame']);
    }

    public function test_faktor_tidak_tersedia_bernilai_null_bukan_nol(): void
    {
        Faktor::withoutGlobalScopes()->where('kode_energi', 'lpg')->where('parameter', 'ef_co2')->update(['status' => 'ditarik']);
        RegistriFaktor::lupakan();

        $h = Kalkulator::hitung('lpg', 100, '2026-08-01');

        $this->assertNull($h['co2e_t']);
        $this->assertContains('lpg/ef_co2', $h['kurang']);
    }

    public function test_toe_dan_listrik_scope_2(): void
    {
        $this->assertEqualsWithDelta(1.0, Kalkulator::toe(41.868, '2026-01-01'), 1e-9);

        $l = Kalkulator::hitung('listrik_pln', 10_000, '2026-08-01');
        $this->assertSame(2, $l['scope']);
        $this->assertEqualsWithDelta(36.0, $l['gj'], 1e-9);
        $this->assertEqualsWithDelta(8.7, $l['co2e_t'], 1e-9);

        $plts = Kalkulator::hitung('plts', 5_000, '2026-08-01');
        $this->assertSame(0.0, $plts['co2e_t']);
    }

    /* ═══════════ registri faktor (FSD §6.1) ═══════════ */

    public function test_prioritas_pemilihan_faktor(): void
    {
        $dasar = ['kode_energi' => 'solar', 'parameter' => 'densitas', 'satuan' => 'kg/L', 'berlaku_mulai' => '2026-01-01',
                  'tier' => 2, 'sumber_dokumen' => 'uji', 'status' => 'aktif'];

        Faktor::withoutGlobalScopes()->create($dasar + ['company_id' => $this->a->id, 'nilai' => 0.85]);
        Faktor::withoutGlobalScopes()->create($dasar + ['company_id' => $this->a->id, 'pemasok' => 'PT X', 'nilai' => 0.83]);
        Faktor::withoutGlobalScopes()->create($dasar + ['company_id' => $this->b->id, 'nilai' => 0.90]);
        RegistriFaktor::lupakan();

        $this->assertSame(0.83, RegistriFaktor::nilai('solar', 'densitas', '2026-08-01', ['company_id' => $this->a->id, 'pemasok' => 'PT X']));
        $this->assertSame(0.85, RegistriFaktor::nilai('solar', 'densitas', '2026-08-01', ['company_id' => $this->a->id]));
        // Perusahaan tanpa faktor sendiri memakai faktor bersama — bukan milik perusahaan lain.
        $c = Company::create(['name' => 'PT C']);
        $this->assertSame(0.84, RegistriFaktor::nilai('solar', 'densitas', '2026-08-01', ['company_id' => $c->id]));
        // Sebelum berlaku: kembali ke faktor bersama.
        $this->assertSame(0.84, RegistriFaktor::nilai('solar', 'densitas', '2025-12-31', ['company_id' => $this->a->id]));
    }

    public function test_versi_baru_disetujui_orang_lain_dan_menutup_versi_lama(): void
    {
        $pengaju = $this->orang($this->a, 'petugas_grk');
        $penyetuju = $this->orang($this->a, 'petugas_grk');

        $this->actingAs($pengaju)->post(route('karbon.faktor.simpan'), [
            'kode_energi' => 'listrik_pln', 'parameter' => 'ef_grid', 'wilayah' => null, 'nilai' => 0.79, 'satuan' => 'tCO2e/MWh',
            'berlaku_mulai' => '2026-09-01', 'tier' => 2, 'sumber_dokumen' => 'Kepmen ESDM (uji)',
        ])->assertSessionHasNoErrors();

        $f = Faktor::where('company_id', $this->a->id)->where('parameter', 'ef_grid')->firstOrFail();
        $this->assertSame('draf', $f->status);

        // Pengaju tidak menyetujui faktornya sendiri.
        $this->actingAs($pengaju)->post(route('karbon.faktor.setujui', $f))->assertForbidden();

        $this->actingAs($penyetuju)->post(route('karbon.faktor.setujui', $f))->assertSessionHasNoErrors();
        $this->assertSame('aktif', $f->fresh()->status);

        RegistriFaktor::lupakan();
        $this->assertSame(0.87, RegistriFaktor::nilai('listrik_pln', 'ef_grid', '2026-08-31', ['company_id' => $this->a->id]));
        $this->assertSame(0.79, RegistriFaktor::nilai('listrik_pln', 'ef_grid', '2026-09-01', ['company_id' => $this->a->id]));
    }

    public function test_faktor_tanpa_sumber_ditolak_dan_peran_lain_tidak_boleh(): void
    {
        $this->actingAs($this->orang($this->a, 'petugas_grk'))->post(route('karbon.faktor.simpan'), [
            'kode_energi' => 'solar', 'parameter' => 'ncv', 'nilai' => 43, 'satuan' => 'MJ/kg', 'berlaku_mulai' => '2026-01-01', 'tier' => 1,
        ])->assertSessionHasErrors('sumber_dokumen');

        $this->actingAs($this->orang($this->a, 'operator'))->post(route('karbon.faktor.simpan'), [
            'kode_energi' => 'solar', 'parameter' => 'ncv', 'nilai' => 43, 'satuan' => 'MJ/kg', 'berlaku_mulai' => '2026-01-01',
            'tier' => 1, 'sumber_dokumen' => 'x',
        ])->assertForbidden();
    }

    /* ═══════════ ambang, EnPI, anomali ═══════════ */

    public function test_ambang_tepat_batas_berarti_wajib(): void
    {
        $this->assertTrue(Ambang::wajib(4000.0, 4000.0));
        $this->assertFalse(Ambang::wajib(3999.9, 4000.0));
    }

    public function test_regresi_enb_memulihkan_koefisien(): void
    {
        $x = $y = [];
        foreach (range(1, 12) as $i) {
            $t = 1000 * $i; $b = 300 * (($i * 7) % 5 + 1);
            $x[] = ['ton' => $t, 'bcm' => $b];
            $y[] = 120 + 0.25 * $t + 0.6 * $b;
        }
        $m = Enpi::regresi($y, $x);

        $this->assertTrue($m['sah']);
        $this->assertEqualsWithDelta(0.25, $m['koefisien']['ton'], 1e-6);
        $this->assertEqualsWithDelta(0.6, $m['koefisien']['bcm'], 1e-6);
        $this->assertEqualsWithDelta(1.0, $m['r2'], 1e-9);
        $this->assertTrue($m['diterima']);

        $this->assertFalse(Enpi::regresi([1, 2], [['ton' => 1], ['ton' => 2]])['sah'], 'Titik terlalu sedikit harus ditolak.');
    }

    public function test_anomali_z_robust_dan_hm_lebih_24_jam(): void
    {
        $u = $this->unit($this->a);
        foreach (range(1, 20) as $i) {
            EnergyFuelLog::withoutGlobalScopes()->create(['equipment_id' => $u->id, 'tanggal' => Carbon::create(2026, 8, $i),
                'liter' => $i === 15 ? 900 : 400 + ($i % 3) * 5, 'hm' => $i === 18 ? 30 : 10]);
        }

        $temuan = collect(Anomali::bbm(Carbon::create(2026, 8, 20), 30, $this->a->id));

        $this->assertTrue($temuan->contains(fn ($t) => $t['jenis'] === 'lonjakan' && $t['tanggal'] === '2026-08-15'));
        $this->assertTrue($temuan->contains(fn ($t) => $t['jenis'] === 'hm' && $t['tanggal'] === '2026-08-18'));
    }

    /* ═══════════ inventarisasi & batas perusahaan ═══════════ */

    public function test_inventaris_terbatas_perusahaan_pengguna(): void
    {
        foreach ([$this->a, $this->b] as $i => $c) {
            $u = $this->unit($c, 'HD-'.$c->id);
            EnergyFuelLog::withoutGlobalScopes()->create(['equipment_id' => $u->id, 'tanggal' => '2026-08-05', 'liter' => 1000 * ($i + 1), 'hm' => 10]);
        }

        $this->actingAs($this->orang($this->a));
        $r = Inventaris::ringkas(Inventaris::hitung('2026-08-01', '2026-08-31')['baris']);

        $this->assertEqualsWithDelta(1000.0, $r['liter_solar'], 1e-9, 'Solar perusahaan lain ikut terhitung.');
    }

    public function test_laporan_perusahaan_lain_tidak_dapat_dibuka(): void
    {
        $l = Laporan::withoutGlobalScopes()->create(['company_id' => $this->b->id, 'templat' => 'GRK_PU', 'tahun' => 2026,
            'periode_mulai' => '2026-01-01', 'periode_sampai' => '2026-12-31', 'judul' => 'Rahasia B', 'snapshot' => []]);

        $this->actingAs($this->orang($this->a, 'petugas_grk'))->get(route('energi.lapor.show', $l))->assertNotFound();
        $this->actingAs($this->orang($this->a, 'petugas_grk'))->get(route('energi.lapor.unduh', [$l, 'json']))->assertNotFound();
    }

    /* ═══════════ alur verifikasi, kunci, koreksi (FSD §7) ═══════════ */

    private function bulanLengkap(Company $c, int $bulan = 8): EnergyEquipment
    {
        $u = $this->unit($c, 'HD-'.$c->id.'-'.$bulan);
        $akhir = Carbon::create(2026, $bulan, 1)->endOfMonth()->day;
        foreach (range(1, $akhir) as $d) {
            $t = Carbon::create(2026, $bulan, $d)->toDateString();
            EnergyFuelLog::withoutGlobalScopes()->create(['equipment_id' => $u->id, 'tanggal' => $t, 'liter' => 400, 'hm' => 10, 'status' => 'terverifikasi']);
            EnergyProduction::withoutGlobalScopes()->create(['company_id' => $c->id, 'tanggal' => $t, 'ton' => 5000, 'bcm' => 3000, 'status' => 'terverifikasi']);
        }
        EnergyFuelRecon::withoutGlobalScopes()->create(['company_id' => $c->id, 'tanggal' => Carbon::create(2026, $bulan, $akhir)->toDateString(),
            'stok_awal_liter' => 20000, 'disalurkan_liter' => 12000, 'stok_akhir_liter' => 19500, 'status' => 'terverifikasi']);

        return $u;
    }

    public function test_kunci_ditolak_bila_masih_ada_baris_diajukan(): void
    {
        $this->bulanLengkap($this->a);
        $log = EnergyFuelLog::withoutGlobalScopes()->first();
        $log->forceFill(['status' => 'diajukan'])->saveQuietly();

        $p = Periode::untuk($this->a->id, 2026, 8);
        $s = AlurPeriode::syaratKunci($p);

        $this->assertFalse($s['boleh']);
        $this->assertFalse(collect($s['syarat'])->firstWhere('kode', 'verifikasi')['ok']);

        $this->actingAs($this->orang($this->a, 'manajer_energi'))->post(route('energi.periode.kunci', $p))->assertSessionHasErrors('periode');
        $this->assertSame('terbuka', $p->fresh()->status);
    }

    public function test_alur_kunci_lalu_koreksi_menandai_laporan_perlu_revisi(): void
    {
        $this->bulanLengkap($this->a);
        $manajer = $this->orang($this->a, 'manajer_energi');
        $verifikator = $this->orang($this->a, 'verifikator');

        $p = Periode::untuk($this->a->id, 2026, 8);
        $this->actingAs($manajer)->post(route('energi.periode.kunci', $p))->assertSessionHasNoErrors();
        $this->assertSame('terkunci', $p->fresh()->status);

        $log = EnergyFuelLog::withoutGlobalScopes()->orderBy('id')->first();
        $this->assertSame('terkunci', $log->status);

        // Baris terkunci tidak dapat diubah lewat input biasa.
        $this->actingAs($manajer)->post(route('energi.input.fuel'), [
            'equipment_id' => $log->equipment_id, 'tanggal' => $log->tanggal->toDateString(), 'hm' => 10, 'liter' => 999,
            'idle_jam' => 0, 'jarak_km' => 0, 'ton' => 0, 'bcm' => 0,
        ])->assertSessionHasErrors('tanggal');
        $this->assertEqualsWithDelta(400.0, $log->fresh()->liter, 1e-9);

        $l = Laporan::withoutGlobalScopes()->create(['company_id' => $this->a->id, 'templat' => 'KINERJA_BLN', 'tahun' => 2026,
            'periode_mulai' => '2026-08-01', 'periode_sampai' => '2026-08-31', 'judul' => 'Agustus', 'status' => 'terbit', 'snapshot' => []]);

        $this->actingAs($verifikator)->post(route('energi.periode.koreksi'), [
            'jenis' => 'bbm', 'id' => $log->id, 'nilai' => ['liter' => 380], 'alasan' => 'Nota terbaca dua kali.',
        ])->assertSessionHasNoErrors();
        $k = Koreksi::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($manajer)->post(route('energi.periode.koreksi.putus', $k), ['putusan' => 'setujui'])->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(380.0, $log->fresh()->liter, 1e-9);
        $this->assertSame('terkunci', $log->fresh()->status);
        $this->assertSame('perlu_revisi', $l->fresh()->status);
        $this->assertSame(['liter' => 400], array_map('intval', $k->fresh()->nilai_lama));
    }

    public function test_perubahan_tercatat_di_jejak(): void
    {
        $u = $this->unit($this->a);
        $this->actingAs($this->orang($this->a, 'operator'));

        $log = EnergyFuelLog::create(['equipment_id' => $u->id, 'tanggal' => '2026-08-05', 'liter' => 100, 'hm' => 5]);
        $log->update(['liter' => 120]);

        $jejak = \App\Models\Energi\Jejak::withoutGlobalScopes()->where('subjek_id', $log->id)->orderBy('id')->get();
        $this->assertSame(['buat', 'ubah'], $jejak->pluck('aksi')->all());
        $this->assertEquals([100, 120], $jejak[1]->perubahan['liter']);
        $this->assertSame($this->a->id, $jejak[1]->company_id);
    }

    /* ═══════════ pelaporan & snapshot (FSD §7.3–7.4) ═══════════ */

    public function test_laporan_terbit_tidak_berubah_saat_faktor_diperbarui(): void
    {
        $this->bulanLengkap($this->a);
        GrkBatas::withoutGlobalScopes()->create(['company_id' => $this->a->id, 'tahun' => 2026, 'pendekatan' => 'kendali_operasional',
            'disetujui_pada' => now()]);
        foreach (range(1, 12) as $b) {
            if ($b === 8) continue;
            Periode::withoutGlobalScopes()->create(['company_id' => $this->a->id, 'tahun' => 2026, 'bulan' => $b, 'status' => 'terkunci']);
        }
        AlurPeriode::kunci(Periode::untuk($this->a->id, 2026, 8), $this->orang($this->a)->id);

        $penyusun = $this->orang($this->a, 'petugas_grk');
        $penyetuju = $this->orang($this->a, 'manajer_energi');

        $this->actingAs($penyusun)->post(route('energi.lapor.susun', 'GRK_PU'), ['tahun' => 2026])->assertRedirect();
        $l = Laporan::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($penyusun)->post(route('energi.lapor.ajukan', $l));
        $this->actingAs($penyusun)->post(route('energi.lapor.setujui', $l))->assertForbidden();
        $this->actingAs($penyetuju)->post(route('energi.lapor.setujui', $l));
        $this->actingAs($penyetuju)->post(route('energi.lapor.terbitkan', $l))->assertSessionHasNoErrors();

        $l->refresh();
        $this->assertSame('terbit', $l->status);
        $this->assertTrue($l->utuh());
        $total = $l->snapshot['ringkas']['s1'];
        $this->assertGreaterThan(0, $total);

        // Faktor EF CO₂ solar diganti sesudah terbit.
        Faktor::withoutGlobalScopes()->where('kode_energi', 'solar')->where('parameter', 'ef_co2')->update(['nilai' => 90000]);
        RegistriFaktor::lupakan();

        $l->refresh();
        $this->assertSame($total, $l->snapshot['ringkas']['s1']);
        $this->assertTrue($l->utuh(), 'Hash snapshot harus tetap cocok.');

        $baru = Inventaris::ringkas(Inventaris::hitung('2026-01-01', '2026-12-31', ['company_id' => $this->a->id])['baris'])['s1'];
        $this->assertGreaterThan($total, $baru, 'Tampilan operasional memang memakai faktor baru.');
    }

    public function test_laporan_resmi_tidak_terbit_dari_periode_terbuka_atau_tanpa_manajer_bersertifikat(): void
    {
        $this->bulanLengkap($this->a);
        $snap = Pelaporan::susun('ESDM_ME', $this->a->id, Carbon::create(2026, 1, 1), Carbon::create(2026, 12, 31));
        $v = Pelaporan::validasi('ESDM_ME', $snap, $this->a->id);

        $this->assertNotEmpty(array_filter($v['blokir'], fn ($b) => str_contains($b, 'Periode belum terkunci')));
        $this->assertNotEmpty(array_filter($v['blokir'], fn ($b) => str_contains($b, 'Manajer Energi bersertifikat')));

        Personel::withoutGlobalScopes()->create(['company_id' => $this->a->id, 'nama' => 'ME', 'peran' => 'manajer_energi',
            'nomor_sertifikat' => 'X-1', 'berlaku_sampai' => '2027-12-31', 'aktif' => true]);
        $snap = Pelaporan::susun('ESDM_ME', $this->a->id, Carbon::create(2026, 1, 1), Carbon::create(2026, 12, 31));
        $this->assertEmpty(array_filter(Pelaporan::validasi('ESDM_ME', $snap, $this->a->id)['blokir'], fn ($b) => str_contains($b, 'Manajer Energi')));
    }

    public function test_unduh_xlsx_csv_json(): void
    {
        $this->bulanLengkap($this->a);
        $u = $this->orang($this->a, 'petugas_grk');
        $this->actingAs($u)->post(route('energi.lapor.susun', 'KINERJA_BLN'), ['tahun' => 2026, 'bulan' => 8]);
        $l = Laporan::withoutGlobalScopes()->firstOrFail();

        $this->actingAs($u)->get(route('energi.lapor.unduh', [$l, 'json']))->assertOk()->assertJsonPath('laporan.templat', 'KINERJA_BLN');
        $this->actingAs($u)->get(route('energi.lapor.unduh', [$l, 'csv']))->assertOk();
        $this->actingAs($u)->get(route('energi.lapor.unduh', [$l, 'xlsx']))->assertOk();
        $this->actingAs($u)->get(route('energi.lapor.show', $l))->assertOk();
    }

    /* ═══════════ dasbor BOD (FSD §9.4) ═══════════ */

    public function test_kartu_emisi_bod_sama_dengan_laporan_grk(): void
    {
        $this->bulanLengkap($this->a);
        EnergyPowerLog::withoutGlobalScopes()->create(['company_id' => $this->a->id, 'tanggal' => '2026-08-10', 'area' => 'camp',
            'sumber' => 'pln', 'kwh' => 12_000]);
        $bod = $this->orang($this->a, 'bod');
        $this->actingAs($bod);

        $k = KendaliDireksi::kartu(Carbon::create(2026, 8, 1), Carbon::create(2026, 8, 31), ['company_id' => $this->a->id]);
        $snap = Pelaporan::susun('GRK_PU', $this->a->id, Carbon::create(2026, 8, 1), Carbon::create(2026, 8, 31));

        $emisi = collect($k['kartu'])->firstWhere('kode', 'emisi')['nilai'];
        $this->assertEqualsWithDelta($snap['ringkas']['total'], $emisi, 1e-9);
        $this->assertEqualsWithDelta($snap['ringkas']['s1'] + $snap['ringkas']['s2_lb'], $emisi, 1e-9);

        /* CH₄ & N₂O pada tabel per gas ikut disetarakan dengan GWP, bukan dibiarkan kosong. */
        $ch4 = collect($snap['per_gas'])->firstWhere('gas', 'CH₄');
        $this->assertGreaterThan(0, $ch4['massa_t']);
        $this->assertEqualsWithDelta($ch4['massa_t'] * \App\Support\Energi\Kalkulator::gwp('ch4', 'AR5', '2026-12-31'), $ch4['co2e_t'], 1e-9);

        $this->get(route('energi.bod.index', ['periode' => 'kustom', 'dari' => '2026-08-01', 'sampai' => '2026-08-31']))->assertOk();
    }

    public function test_instruksi_bod_menjadi_tindak_lanjut_dan_bod_tidak_menginput(): void
    {
        $bod = $this->orang($this->a, 'bod');

        $this->actingAs($bod)->post(route('energi.bod.instruksi'), [
            'judul' => 'Turunkan idle', 'isi' => 'Idle armada di atas 15%.', 'prioritas' => 'tinggi',
            'pic' => 'Superintendent Plant', 'tenggat' => now()->addDays(14)->toDateString(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $t = TindakLanjut::withoutGlobalScopes()->firstOrFail();
        $this->assertSame('energi', $t->modul);
        $this->assertSame('instruksi_bod', $t->kategori);
        $this->assertSame($this->a->id, $t->company_id);

        $u = $this->unit($this->a);
        $this->actingAs($bod)->post(route('energi.input.fuel'), ['equipment_id' => $u->id, 'tanggal' => '2026-08-05',
            'hm' => 1, 'liter' => 1, 'idle_jam' => 0, 'jarak_km' => 0, 'ton' => 0, 'bcm' => 0])->assertForbidden();
    }

    public function test_dasbor_bod_tertutup_bagi_pengguna_tanpa_peran(): void
    {
        $this->actingAs($this->orang($this->a))->get(route('energi.bod.index'))->assertForbidden();
        $this->actingAs($this->orang($this->a, 'operator'))->get(route('energi.bod.index'))->assertForbidden();
        $this->actingAs($this->orang($this->a, 'keuangan'))->get(route('energi.bod.index'))->assertOk();
    }

    public function test_bod_menyetujui_target_tahunan(): void
    {
        $b = EnergyBaseline::withoutGlobalScopes()->create(['company_id' => $this->a->id, 'tahun' => 2027,
            'baseline_gj_ton' => 0.03, 'target_gj_ton' => 0.028, 'status' => 'usulan']);

        $this->actingAs($this->orang($this->a, 'manajer_energi'))->post(route('energi.bod.target', $b), ['putusan' => 'setujui'])->assertForbidden();
        $this->actingAs($this->orang($this->a, 'bod'))->post(route('energi.bod.target', $b), ['putusan' => 'setujui'])->assertSessionHasNoErrors();
        $this->assertSame('disetujui_bod', $b->fresh()->status);
    }

    /* ═══════════ API & input baru ═══════════ */

    public function test_kalkulator_api_sama_dengan_mesin_server(): void
    {
        $this->actingAs($this->orang($this->a));

        $r = $this->postJson(route('energi.api.kalkulator'), ['tanggal' => '2026-08-01',
            'butir' => [['kode' => 'solar', 'jumlah' => 1000], ['kode' => 'listrik_pln', 'jumlah' => 5000]]])->assertOk()->json();

        $solar = Kalkulator::hitung('solar', 1000, '2026-08-01', ['company_id' => $this->a->id]);
        $this->assertEqualsWithDelta($solar['co2e_t'] + 4.35, $r['total']['co2e_t'], 1e-9);
        $this->assertEqualsWithDelta($solar['gj'] + 18.0, $r['total']['gj'], 1e-9);
    }

    public function test_input_gas_dan_energi_lain(): void
    {
        $this->actingAs($this->orang($this->a));

        $this->post(route('energi.input.other'), ['tanggal' => '2026-08-05', 'jenis' => 'lpg', 'jumlah' => 120, 'sumber_data' => 'nota'])
            ->assertSessionHasNoErrors();
        $this->post(route('energi.input.other'), ['tanggal' => '2026-08-05', 'jenis' => 'uranium', 'jumlah' => 1, 'sumber_data' => 'nota'])
            ->assertSessionHasErrors('jenis');

        $log = \App\Models\EnergyOtherLog::withoutGlobalScopes()->firstOrFail();
        $this->assertSame(['lpg', 'kg', 'diajukan', $this->a->id], [$log->jenis, $log->satuan, $log->status, $log->company_id]);
    }

    public function test_peluang_selesai_mensyaratkan_mv(): void
    {
        $this->actingAs($this->orang($this->a));
        $o = \App\Models\EnergyOpportunity::create(['judul' => 'LED', 'status' => 'berjalan', 'hemat_kwh' => 1000]);

        $this->put(route('energi.hemat.ubah', $o), ['status' => 'selesai'])->assertSessionHasErrors('status');
        $this->put(route('energi.hemat.ubah', $o), ['status' => 'selesai', 'metode_mv' => 'B', 'hemat_terverifikasi_kwh' => 900])
            ->assertSessionHasNoErrors();
        $this->assertTrue($o->fresh()->terverifikasi());
    }

    public function test_status_bawaan_formulir_peluang_sah(): void
    {
        // Cacat D1: formulir lama mengirim 'diusulkan', yang tidak ada di daftar sah.
        $this->assertContains('usulan', \App\Support\Energi::STATUS_PELUANG);
        $this->assertNotContains('diusulkan', \App\Support\Energi::STATUS_PELUANG);
        $vue = file_get_contents(resource_path('js/Components/Energi/ModePenghematan.vue'));
        $this->assertStringNotContainsString("'diusulkan'", $vue);
    }

    /* ═══════════ halaman baru ═══════════ */

    public function test_seluruh_halaman_baru_terbuka(): void
    {
        $c = Company::create(['name' => 'PT Contoh Energi', 'doc_no_prefix' => 'CE', 'demo' => true]);
        $admin = User::factory()->create(['is_admin' => true, 'company_id' => $c->id, 'email_verified_at' => now()]);
        User::factory()->create(['company_id' => $c->id]);
        \App\Support\DataContoh::muat($c, $admin);

        $this->actingAs($admin);
        foreach (['karbon.index', 'karbon.faktor.index', 'karbon.grk', 'karbon.batas', 'karbon.s3', 'karbon.mitigasi',
                  'energi.me.index', 'energi.me.personel', 'energi.me.program', 'energi.me.audit', 'energi.me.tinjauan',
                  'energi.enpi', 'energi.seu', 'energi.periode.index', 'energi.lapor.index', 'energi.kalender',
                  'energi.bod.index', 'energi.bod.layar', 'energi.kalkulator', 'energi.input', 'energi.master'] as $rute) {
            $this->get(route($rute))->assertOk();
        }

        $audit = \App\Models\Energi\Audit::withoutGlobalScopes()->firstOrFail();
        $this->get(route('energi.me.audit.show', $audit))->assertOk();
        $laporan = Laporan::withoutGlobalScopes()->firstOrFail();
        $this->get(route('energi.lapor.show', $laporan))->assertOk();
        $this->getJson(route('energi.bod.drill', ['tingkat' => 'perusahaan', 'kunci' => 'grup']))->assertOk();
        $this->postJson(route('energi.bod.simulasi'), ['produksi' => 10, 'pln' => 50, 'genset' => 20, 'plts' => 30])->assertOk()
            ->assertJsonStructure(['dasar' => ['gj', 'tco2e', 'rupiah'], 'skenario', 'selisih']);
    }
}
