<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use App\Models\Angkat\{Alat, Gear, Jejak, Kajian, Rencana, Riksa};
use App\Support\Angkat\{KajianTeknis, Katalog, RiksaUji, Simulasi};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Lifting & Rigging Management.
 *
 * Yang paling dijaga: rumus rigging dan simulasi mengikuti spesifikasi
 * (ASME B30.9, batas 85/75/50%), riksa uji yang gagal langsung
 * mengarantina alatnya, alat yang tidak layak digunakan tidak dapat
 * masuk ke rencana, dan rencana tidak dapat disetujui pembuatnya sendiri
 * atau diajukan selagi NO-GO.
 */
class AngkatTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $u;
    private User $pengawas;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 08:00:00');
        $this->c = Company::create(['name' => 'Tambang Uji']);
        $this->u = User::factory()->create(['company_id' => $this->c->id]);
        $this->pengawas = User::factory()->create(['company_id' => $this->c->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function alat(array $ganti = []): Alat
    {
        return Alat::create(array_merge([
            'company_id' => $this->c->id, 'kode' => 'CRN-MOB-01', 'nama' => 'Liebherr LTM 1100', 'jenis' => 'mobile_100t',
            'kapasitas_t' => 100, 'berat_crane_t' => 60, 'boom_maks_m' => 52, 'berat_hook_t' => 1.1,
            'suket_nomor' => 'SUKET-01', 'suket_berlaku' => '2027-06-01', 'status' => 'layak',
        ], $ganti));
    }

    private function gear(array $ganti = []): Gear
    {
        return Gear::create(array_merge([
            'company_id' => $this->c->id, 'kode' => 'WRS-28-014', 'nama' => 'Wire rope sling Ø28', 'jenis' => 'wire_rope',
            'wll_t' => 13.5, 'sf' => 5, 'jumlah_kaki' => 2, 'uji_terakhir' => '2026-09-01', 'uji_berikut' => '2027-03-01', 'status' => 'layak',
        ], $ganti));
    }

    private function rencana(array $ganti = []): array
    {
        return array_merge(Simulasi::BAWAAN, [
            'judul' => 'Penggantian engine HD785', 'lokasi' => 'Workshop bay 3', 'area' => 'Workshop alat berat',
            'jadwal_at' => '2026-11-25 08:00', 'gear' => [],
            'operator_nama' => 'Sutrisno', 'operator_sio' => 'SIO-01', 'rigger_nama' => 'Ferdinan', 'rigger_sertifikat' => 'BNSP-01',
            'signalman_nama' => 'Dimas', 'pengawas_nama' => 'Hendra (POP)',
        ], $ganti);
    }

    private function riksaLulus(string $kategori): array
    {
        return ['pemeriksa' => 'Ir. Hendrawan', 'skp_nomor' => 'SKP-1', 'jenis' => 'berkala', 'ndt_metode' => 'MPI', 'ndt_hasil' => 'tanpa_indikasi',
                'periksa' => array_fill_keys(array_column(Katalog::BUTIR[$kategori], 'kode'), 'lulus')];
    }

    /* ─────────── rumus ─────────── */

    public function test_rigging_mengikuti_asme_b30_9(): void
    {
        $r = Simulasi::rigging(['jumlah_kaki' => 2, 'sudut_sling' => 60, 'hitch' => 'bridle', 'wll_sling_t' => 12, 'wll_shackle_t' => 17], 10);
        $this->assertEqualsWithDelta(5.77, $r['tegangan_maks_t'], 0.01);   // 10 / (2 × sin 60°)
        $this->assertEqualsWithDelta(1.155, $r['lam'], 0.001);
        $this->assertEqualsWithDelta(2.89, $r['tekan_horizontal_t'], 0.01); // T × cos 60°
        $this->assertSame('lulus', $r['status']);

        // Empat kaki kaku ditanggung tiga.
        $r4 = Simulasi::rigging(['jumlah_kaki' => 4, 'sudut_sling' => 60, 'wll_sling_t' => 12, 'wll_shackle_t' => 17], 10);
        $this->assertSame(3, $r4['kaki_efektif']);
        $this->assertEqualsWithDelta(3.85, $r4['tegangan_maks_t'], 0.01);

        // Titik berat bergeser 20%: sisi berat 1,4× rata-rata.
        $rc = Simulasi::rigging(['jumlah_kaki' => 2, 'sudut_sling' => 60, 'cog_offset_pct' => 20, 'wll_sling_t' => 12, 'wll_shackle_t' => 17], 10);
        $this->assertEqualsWithDelta(5.77 * 1.4, $rc['tegangan_maks_t'], 0.02);

        // Spreader membuat kaki tegak, tanpa gaya tekan.
        $rs = Simulasi::rigging(['jumlah_kaki' => 2, 'sudut_sling' => 45, 'spreader' => true, 'wll_sling_t' => 12, 'wll_shackle_t' => 17], 10);
        $this->assertEqualsWithDelta(5.0, $rs['tegangan_maks_t'], 0.01);
        $this->assertSame(0.0, $rs['tekan_horizontal_t']);

        // Sudut < 30° ditolak; WLL terlampaui ditolak.
        $this->assertSame('gagal', Simulasi::rigging(['jumlah_kaki' => 2, 'sudut_sling' => 25, 'wll_sling_t' => 50, 'wll_shackle_t' => 50], 10)['status']);
        $this->assertSame('gagal', Simulasi::rigging(['jumlah_kaki' => 2, 'sudut_sling' => 60, 'wll_sling_t' => 4, 'wll_shackle_t' => 17], 10)['status']);
    }

    public function test_simulasi_beban_kotor_kapasitas_dan_utilisasi(): void
    {
        $h = Simulasi::hitung(Simulasi::BAWAAN);

        $this->assertEqualsWithDelta((14.5 + 1.1 + 1.10) * 1.10, $h['berat_kotor_t'], 0.01);
        $this->assertSame(Simulasi::kapasitas(100, 'mobile', 12, 28), $h['kapasitas_t']);
        $this->assertEqualsWithDelta($h['berat_kotor_t'] / $h['kapasitas_t'] * 100, $h['utilisasi_pct'], 0.1);
        $this->assertSame('rekayasa', $h['klasifikasi']);                    // beban ≥ 10 t
        $this->assertCount(4, $h['fase']);
        $this->assertNotEmpty($h['kurva']);
        $this->assertNotNull($h['radius_aman_m']);
        $this->assertGreaterThan($h['radius_m'], $h['radius_aman_m']);

        // Kapasitas turun terhadap radius; tandem diturunkan 25%.
        $this->assertGreaterThan(Simulasi::kapasitas(100, 'mobile', 20, 28), Simulasi::kapasitas(100, 'mobile', 10, 28));
        $this->assertEqualsWithDelta(Simulasi::kapasitas(200, 'mobile', 12, 30) * 0.75, Simulasi::kapasitas(200, 'tandem', 12, 30), 0.02);

        // Kapasitas crane di register menimpa katalog.
        $kecil = Simulasi::hitung(Simulasi::BAWAAN, ['kapasitas_t' => 80]);
        $this->assertLessThan($h['kapasitas_t'], $kecil['kapasitas_t']);
    }

    public function test_batas_utilisasi_man_basket_dan_kritis(): void
    {
        // Man-basket dibatasi 50%: utilisasi ~60% NO-GO walau di bawah 85%.
        $mb = Simulasi::hitung(['kategori_beban' => 'man_basket', 'jenis_alat' => 'mobile_50t', 'berat_bersih_t' => 12, 'berat_rigging_t' => 0.5, 'radius_m' => 12, 'boom_m' => 28]);
        $this->assertSame(50.0, $mb['batas_util']);
        $this->assertGreaterThan(50, $mb['utilisasi_pct']);
        $this->assertSame('nogo', $mb['putusan']);
        $this->assertSame('kritis', $mb['klasifikasi']);

        // Dekat SUTT dan melintas di atas fasilitas → kritis, walau ringan.
        $s = Simulasi::hitung(['berat_bersih_t' => 3, 'jarak_sutt_m' => 8, 'radius_m' => 8, 'boom_m' => 20]);
        $this->assertSame('kritis', $s['klasifikasi']);
        $this->assertSame('bersyarat', $s['putusan']);

        // Ringan, dekat, jauh dari SUTT → rutin & GO.
        $r = Simulasi::hitung(['berat_bersih_t' => 3, 'berat_rigging_t' => 0.3, 'radius_m' => 8, 'boom_m' => 20, 'jarak_sutt_m' => 30]);
        $this->assertSame('rutin', $r['klasifikasi']);
        $this->assertSame('go', $r['putusan']);
    }

    public function test_landasan_timbunan_lunak_menggagalkan_fase_slewing(): void
    {
        $h = Simulasi::hitung(['tanah' => 'timbunan', 'luas_mat_m2' => 2]);
        $this->assertFalse($h['cek']['tanah']);
        $this->assertSame('gagal', $h['fase'][2]['status']);
        $this->assertSame('nogo', $h['putusan']);
        $this->assertGreaterThan(2, $h['mat_min_m2']);
    }

    /* ─────────── riksa uji ─────────── */

    public function test_beban_uji_mengikuti_permenaker_dan_asme(): void
    {
        $sling = RiksaUji::beban('wire_rope', 10);
        $this->assertSame(200, $sling['statis_pct']);
        $this->assertSame(20.0, $sling['beban_statis_t']);
        $this->assertSame(6, $sling['bulan']);

        $crane = RiksaUji::beban('crane', 100);
        $this->assertSame(125.0, $crane['beban_statis_t']);
        $this->assertSame(110.0, $crane['beban_dinamis_t']);
        $this->assertSame(12, $crane['bulan']);
    }

    public function test_riksa_gagal_mengarantina_dan_lulus_memulihkan(): void
    {
        $g = $this->gear();

        $gagal = RiksaUji::catat($g, $this->riksaLulus('wire_rope') + ['ukur' => ['kawat_putus' => 7]], $this->u->id);
        $this->assertSame('tidak_layak', $gagal->putusan);
        $this->assertStringContainsString('Kawat putus', $gagal->alasan);
        $g->refresh();
        $this->assertSame('karantina', $g->statusEfektif());
        $this->assertSame('MERAH', $g->kode_warna);
        $this->assertFalse($g->bolehDipakai());

        $lulus = RiksaUji::catat($g, array_merge($this->riksaLulus('wire_rope'), ['jenis' => 'khusus']), $this->u->id);
        $this->assertSame('layak', $lulus->putusan);
        $g->refresh();
        $this->assertSame('layak', $g->statusEfektif());
        $this->assertSame('KUNING', $g->kode_warna);                         // November = triwulan IV
        $this->assertSame('2027-05-20', $g->uji_berikut->toDateString());   // 6 bulan

        // Butir kritis gagal dan NDT cacat juga menolak.
        $this->assertSame('tidak_layak', RiksaUji::nilai(['kategori_uji' => 'shackle', 'periksa' => ['SH-03' => 'gagal']])['putusan']);
        $this->assertSame('tidak_layak', RiksaUji::nilai(['kategori_uji' => 'shackle', 'periksa' => [], 'ndt_hasil' => 'ada_cacat'])['putusan']);
        $this->assertSame('layak', RiksaUji::nilai(['kategori_uji' => 'shackle', 'periksa' => ['SH-04' => 'gagal']])['putusan']);
        $this->assertSame('tidak_layak', RiksaUji::nilai(['kategori_uji' => 'rantai', 'periksa' => [], 'ukur' => ['elongasi_pct' => 3.4]])['putusan']);
    }

    public function test_riksa_crane_memperbarui_suket_dan_kedaluwarsa_terbaca_dari_tanggal(): void
    {
        $a = $this->alat(['suket_berlaku' => '2026-11-15']);
        $this->assertSame('kedaluwarsa', $a->statusEfektif());
        $this->assertFalse($a->bolehDipakai());

        $this->actingAs($this->u)->post('/pengangkatan/riksa-uji', ['objek' => 'alat', 'id' => $a->id] + $this->riksaLulus('crane'))
            ->assertRedirect()->assertSessionHas('ok');
        $a->refresh();
        $this->assertSame('layak', $a->statusEfektif());
        $this->assertSame('2027-11-20', $a->suket_berlaku->toDateString());
        $r = Riksa::firstOrFail();
        $this->assertSame(125, $r->uji_statis_pct);
        $this->assertSame(110, $r->uji_dinamis_pct);
        $this->assertStringStartsWith('RKS-PAA/2026/', $r->nomor);

        $this->alat(['kode' => 'OHC-01', 'jenis' => 'overhead_40t', 'suket_berlaku' => '2026-12-10']);
        $this->assertSame('segera', Alat::where('kode', 'OHC-01')->first()->statusEfektif());
    }

    public function test_riksa_wajib_menilai_seluruh_butir(): void
    {
        $g = $this->gear();
        $isi = $this->riksaLulus('wire_rope');
        unset($isi['periksa']['WR-02']);
        $this->actingAs($this->u)->post('/pengangkatan/riksa-uji', ['objek' => 'gear', 'id' => $g->id] + $isi)->assertSessionHasErrors('periksa');
        $this->assertSame(0, Riksa::count());
    }

    /* ─────────── rencana & alur ─────────── */

    public function test_rencana_tersimpan_dengan_simulasi_kajian_dan_jejak(): void
    {
        $a = $this->alat();
        $g = $this->gear();
        $this->actingAs($this->u)->post('/pengangkatan/rencana', $this->rencana(['alat_id' => $a->id, 'gear' => [$g->id], 'wll_sling_t' => 13.5]))
            ->assertRedirect();

        $r = Rencana::firstOrFail();
        $this->assertSame('LP-2026-001', $r->kode);
        $this->assertSame('draf', $r->status);
        $this->assertSame($this->c->id, $r->company_id);
        $this->assertSame('mobile_100t', $r->jenis_alat);
        $this->assertSame([$g->id], $r->gear);
        $this->assertSame($r->hasil['utilisasi_pct'], $r->utilisasi_pct);
        $k = Kajian::where('rencana_id', $r->id)->firstOrFail();
        $this->assertCount(6, $k->pilar);
        $this->assertSame('penuh', collect($k->pilar[1]['butir'])->firstWhere('kode', 'P2-1')['status']);
        $this->assertSame('penuh', collect($k->pilar[1]['butir'])->firstWhere('kode', 'P2-3')['status']);
        $this->assertSame(1, Jejak::where('rencana_id', $r->id)->count());
    }

    public function test_alat_tidak_layak_tidak_dapat_masuk_rencana(): void
    {
        $karantina = $this->gear(['kode' => 'CHN-01', 'jenis' => 'rantai', 'status' => 'karantina']);
        $kedaluwarsa = $this->gear(['kode' => 'WEB-01', 'jenis' => 'webbing', 'uji_berikut' => '2026-10-01']);
        $crane = $this->alat(['status' => 'karantina']);

        $this->actingAs($this->u)->post('/pengangkatan/rencana', $this->rencana(['gear' => [$karantina->id]]))->assertSessionHasErrors('gear');
        $this->actingAs($this->u)->post('/pengangkatan/rencana', $this->rencana(['gear' => [$kedaluwarsa->id]]))->assertSessionHasErrors('gear');
        $this->actingAs($this->u)->post('/pengangkatan/rencana', $this->rencana(['alat_id' => $crane->id]))->assertSessionHasErrors('alat_id');
        $this->assertSame(0, Rencana::count());

        // Daftar pilihan di form pun tidak memuatnya.
        $this->actingAs($this->u)->get('/pengangkatan/rencana/baru')->assertInertia(fn (Assert $p) => $p
            ->component('Angkat/Rencana')->has('register.gear', 0)->has('register.alat', 0)
            ->where('register.gearTolak', 2)->where('register.alatTolak', 1));
    }

    public function test_alur_ajukan_setujui_selesai_dengan_empat_mata(): void
    {
        $this->actingAs($this->u)->post('/pengangkatan/rencana', $this->rencana(['berat_bersih_t' => 4, 'radius_m' => 8, 'boom_m' => 20]))->assertRedirect();
        $r = Rencana::firstOrFail();
        $this->assertSame('rutin', $r->klasifikasi);

        $this->actingAs($this->u)->post("/pengangkatan/rencana/{$r->id}/status", ['aksi' => 'ajukan'])->assertSessionHasNoErrors();
        $this->assertSame('diajukan', $r->fresh()->status);

        // Pembuat tidak boleh menyetujui rencananya sendiri.
        $this->actingAs($this->u)->post("/pengangkatan/rencana/{$r->id}/status", ['aksi' => 'setujui'])->assertSessionHasErrors('aksi');
        $this->assertSame('diajukan', $r->fresh()->status);

        // Tolak wajib beralasan.
        $this->actingAs($this->pengawas)->post("/pengangkatan/rencana/{$r->id}/status", ['aksi' => 'tolak'])->assertSessionHasErrors('catatan');

        $this->actingAs($this->pengawas)->post("/pengangkatan/rencana/{$r->id}/status", ['aksi' => 'setujui', 'catatan' => 'Area eksklusi dipasang'])->assertSessionHasNoErrors();
        $r->refresh();
        $this->assertSame('disetujui', $r->status);
        $this->assertSame($this->pengawas->name, $r->disetujui_oleh);
        $this->assertFalse($r->terbuka());
        $this->actingAs($this->u)->put("/pengangkatan/rencana/{$r->id}", $this->rencana())->assertForbidden();

        $this->actingAs($this->u)->post("/pengangkatan/rencana/{$r->id}/status", ['aksi' => 'selesai'])->assertSessionHasNoErrors();
        $this->assertSame('selesai', $r->fresh()->status);
        $this->assertSame(['dibuat', 'ajukan', 'setujui', 'selesai'], Jejak::where('rencana_id', $r->id)->orderBy('id')->pluck('aksi')->all());
    }

    public function test_nogo_dan_kritis_tanpa_kajian_lapangan_tidak_dapat_diajukan(): void
    {
        $this->actingAs($this->u)->post('/pengangkatan/rencana', $this->rencana(['berat_bersih_t' => 40, 'radius_m' => 22, 'boom_m' => 40]))->assertRedirect();
        $nogo = Rencana::firstOrFail();
        $this->assertSame('nogo', $nogo->putusan);
        $this->actingAs($this->u)->post("/pengangkatan/rencana/{$nogo->id}/status", ['aksi' => 'ajukan'])->assertSessionHasErrors('aksi');
        $this->assertSame('draf', $nogo->fresh()->status);

        // Kritis karena SUTT: perlu seluruh butir lapangan dinilai.
        $this->actingAs($this->u)->post('/pengangkatan/rencana', $this->rencana(['berat_bersih_t' => 4, 'radius_m' => 8, 'boom_m' => 20, 'jarak_sutt_m' => 8]))->assertRedirect();
        $kritis = Rencana::latest('id')->firstOrFail();
        $this->assertSame('kritis', $kritis->klasifikasi);
        $this->actingAs($this->u)->post("/pengangkatan/rencana/{$kritis->id}/status", ['aksi' => 'ajukan'])->assertSessionHasErrors('aksi');

        $manual = collect(Katalog::PILAR)->flatMap(fn ($p) => $p['butir'])->where('oto', false)->pluck('kode')->mapWithKeys(fn ($k) => [$k => 'penuh'])->all();
        $this->actingAs($this->u)->post("/pengangkatan/rencana/{$kritis->id}/kajian", ['manual' => $manual])->assertSessionHasNoErrors();
        $this->actingAs($this->u)->post("/pengangkatan/rencana/{$kritis->id}/status", ['aksi' => 'ajukan'])->assertSessionHasNoErrors();
        $this->assertSame('diajukan', $kritis->fresh()->status);

        $this->actingAs($this->pengawas)->post("/pengangkatan/rencana/{$kritis->id}/status", ['aksi' => 'setujui'])->assertSessionHasNoErrors();
        $this->assertStringEndsWith('(KTT)', $kritis->fresh()->disetujui_oleh);
    }

    public function test_kajian_menghitung_ulang_ketika_alat_bantu_dikarantina(): void
    {
        $g = $this->gear();
        $this->actingAs($this->u)->post('/pengangkatan/rencana', $this->rencana(['gear' => [$g->id]]))->assertRedirect();
        $r = Rencana::firstOrFail();
        $this->assertNotSame('tidak_memenuhi', $r->kajian->putusan);

        RiksaUji::catat($g, array_merge($this->riksaLulus('wire_rope'), ['ndt_hasil' => 'ada_cacat']));
        $k = KajianTeknis::kaji($r->fresh());
        $this->assertSame('tidak_memenuhi', $k->putusan);
        $this->assertSame('tidak', collect($k->pilar[1]['butir'])->firstWhere('kode', 'P2-3')['status']);
    }

    public function test_pratinjau_hitung_dan_riksa(): void
    {
        $this->actingAs($this->u)->postJson('/pengangkatan/rencana/hitung', ['berat_bersih_t' => 12, 'radius_m' => 14, 'boom_m' => 32])
            ->assertOk()->assertJsonPath('hasil.berat_bersih_t', 12)->assertJsonStructure(['hasil' => ['kurva', 'fase', 'rigging'], 'kajian' => ['skor', 'putusan', 'pilar']]);

        $this->actingAs($this->u)->postJson('/pengangkatan/riksa-uji/pratinjau', ['kategori_uji' => 'webbing', 'periksa' => ['WB-02' => 'gagal']])
            ->assertOk()->assertJsonPath('putusan', 'tidak_layak');
    }

    public function test_batas_perusahaan(): void
    {
        $lain = Company::create(['name' => 'Tambang Lain']);
        $orang = User::factory()->create(['company_id' => $lain->id]);
        $this->actingAs($this->u)->post('/pengangkatan/rencana', $this->rencana())->assertRedirect();
        $r = Rencana::firstOrFail();
        $g = $this->gear();

        $this->actingAs($orang)->get("/pengangkatan/rencana/{$r->id}")->assertNotFound();
        $this->actingAs($orang)->post("/pengangkatan/rencana/{$r->id}/status", ['aksi' => 'setujui'])->assertNotFound();
        $this->actingAs($orang)->post('/pengangkatan/rencana', $this->rencana(['gear' => [$g->id]]))->assertSessionHasErrors('gear');
    }

    public function test_seluruh_halaman_terbuka(): void
    {
        $a = $this->alat();
        $g = $this->gear();
        $riksa = RiksaUji::catat($g, $this->riksaLulus('wire_rope'), $this->u->id);
        $this->actingAs($this->u)->post('/pengangkatan/rencana', $this->rencana(['alat_id' => $a->id, 'gear' => [$g->id]]))->assertRedirect();
        $r = Rencana::firstOrFail();

        foreach ([
            '/pengangkatan' => 'Angkat/Dasbor', '/pengangkatan/rencana' => 'Angkat/Daftar', '/pengangkatan/rencana/baru' => 'Angkat/Rencana',
            "/pengangkatan/rencana/{$r->id}" => 'Angkat/Rincian', "/pengangkatan/rencana/{$r->id}/ubah" => 'Angkat/Rencana',
            '/pengangkatan/peralatan' => 'Angkat/Peralatan', '/pengangkatan/riksa-uji' => 'Angkat/Riksa',
            '/pengangkatan/riksa-uji/baru' => 'Angkat/FormRiksa', "/pengangkatan/riksa-uji/baru?objek=gear&id={$g->id}" => 'Angkat/FormRiksa',
            "/pengangkatan/riksa-uji/{$riksa->id}" => 'Angkat/LaporanRiksa',
        ] as $url => $komponen) {
            $this->actingAs($this->u)->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->component($komponen));
        }
        $this->actingAs($this->u)->get('/pengangkatan/rencana/ekspor/csv')->assertOk();
    }
}
