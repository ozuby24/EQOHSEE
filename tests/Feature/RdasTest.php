<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use App\Models\Rdas\{Blok, Kegiatan, Kejadian, Laporan, Pantau, Penilaian, Petak, Ppkh};
use App\Support\Rdas\{Katalog, Kepatuhan};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Rehabilitasi DAS.
 *
 * Yang paling dijaga: kewajiban 1:1 dan batas ½N / ½N + 1, fase yang
 * diturunkan dari tanggal tanam (bukan dipilih), rumus Petak Ukur, blok
 * yang belum memenuhi prasyarat tidak dapat diajukan penilaian, serah
 * terima bertahap, kebakaran tidak dapat ditutup tanpa laporan & exit
 * strategy, dan verifikasi kegiatan empat mata.
 */
class RdasTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $u;
    private User $petugas;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-24 08:00:00');
        $this->c = Company::create(['name' => 'Tambang Uji']);
        $this->u = User::factory()->create(['company_id' => $this->c->id]);
        $this->petugas = User::factory()->create(['company_id' => $this->c->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ppkh(array $ganti = []): Ppkh
    {
        $this->actingAs($this->u)->post('/rehab-das/kewajiban', array_merge([
            'nomor_sk' => 'SK.482/MENLHK/SETJEN/PLA.0/6/2022', 'tanggal_sk' => '2022-06-15', 'jangka_tahun' => 10, 'tujuan' => 'komersial',
            'kegiatan' => 'Operasi produksi batubara', 'provinsi' => 'Kalimantan Timur', 'kabupaten' => 'Kutai Kartanegara', 'bpdas' => 'BPDAS Mahakam Berau',
            'luas_ppkh_ha' => 1300, 'luas_pak_ha' => 1250, 'rasio' => 1,
        ], $ganti))->assertRedirect();

        return Ppkh::latest('id')->firstOrFail();
    }

    private function blok(Ppkh $p, array $ganti = []): Blok
    {
        $this->actingAs($this->u)->post('/rehab-das/blok', array_merge([
            'ppkh_id' => $p->id, 'nama' => 'Hulu Belayan', 'sk_penlok_nomor' => 'SK.119/PDASRH/2023', 'sk_penlok_tanggal' => '2023-01-10',
            'das' => 'DAS Mahakam', 'sub_das' => 'Sub-DAS Belayan', 'fungsi_kawasan' => 'HL', 'ekosistem' => 'daratan', 'luas_ha' => 40, 'kerapatan' => 625,
            'komposisi' => [['jenis' => 'meranti', 'persen' => 30], ['jenis' => 'ulin', 'persen' => 20], ['jenis' => 'sengon', 'persen' => 30], ['jenis' => 'durian', 'persen' => 20]],
            'rkp_nomor' => 'RKP-01/2023', 'rkp_disahkan_at' => '2023-05-02', 'tanggal_tanam' => '2023-09-15', 'kontraktor' => 'PT Rimba Lestari', 'anggaran_rp' => 1_000_000_000,
        ], $ganti))->assertRedirect();

        return Blok::latest('id')->firstOrFail();
    }

    /** Blok yang memenuhi seluruh prasyarat: dua petak berpeta, PU 84%, geotag 100%. */
    private function blokLayak(): Blok
    {
        $b = $this->blok($this->ppkh());
        foreach (['P01', 'P02'] as $k) {
            $this->actingAs($this->u)->post("/rehab-das/blok/{$b->id}/petak", ['kode' => $k, 'luas_ha' => 20, 'poligon' => "-0.45, 116.80\n-0.45, 116.81\n-0.46, 116.81\n-0.46, 116.80"])->assertSessionHasNoErrors();
        }
        foreach (Petak::where('blok_id', $b->id)->get() as $p) {
            $this->actingAs($this->u)->post("/rehab-das/petak/{$p->id}/pantau", ['tanggal' => '2026-10-20', 'periode' => 'penilaian', 'jumlah_pu' => 10,
                'diamati' => 625, 'sehat' => 510, 'merana' => 15, 'pengamat' => 'Andi'])->assertSessionHasNoErrors();
        }
        $this->actingAs($this->u)->post("/rehab-das/blok/{$b->id}/geotag", ['geotag_total' => 25000, 'geotag_duplikat' => 0])->assertSessionHasNoErrors();

        return $b->fresh();
    }

    /* ─────────── rumus ─────────── */

    public function test_kewajiban_satu_banding_satu_dan_batas_setengah_n(): void
    {
        $p = $this->ppkh();
        $this->assertSame(1250.0, $p->kewajiban_ha);                          // 1 : 1 luas PAK
        $this->assertSame('2027-06-15', $p->batas_selesai->toDateString());   // ½ × 10 tahun
        $this->assertSame('2032-06-15', $p->berlaku_sampai->toDateString());
        $this->assertSame('PPKH-01', $p->kode);

        // ≤ 5 tahun: ½N + 1 → 2,5 + 1 = 3,5 tahun.
        $this->assertSame('2025-12-15', Kepatuhan::batasSelesai('2022-06-15', 5)->toDateString());
        $p2 = $this->ppkh(['nomor_sk' => 'SK.2', 'luas_pak_ha' => 400, 'rasio' => 1.1]);
        $this->assertSame(440.0, $p2->kewajiban_ha);

        // Jadwal laporan semesteran terbentuk sejak tahun SK.
        $this->assertTrue(Laporan::where('ppkh_id', $p->id)->where('tahun', 2022)->where('semester', 2)->exists());
        $this->assertSame('2027-01-31', Kepatuhan::batasLaporan(2026, 2)->toDateString());
    }

    public function test_rumus_petak_ukur(): void
    {
        $h = Kepatuhan::pantau(1000, 780, 60, null, 625, 20, 10);
        $this->assertSame(84.0, $h['persen_tumbuh']);
        $this->assertSame(487.5, $h['sehat_ha']);                              // 780/1000 × 625
        $this->assertSame('berhasil', $h['status']);
        $this->assertSame(160, $h['mati']);
        $this->assertSame(10, $h['pu_minimum']);                               // 20 ha × 5% ÷ 0,1 ha
        $this->assertTrue($h['intensitas_cukup']);
        $this->assertFalse($h['sehat_cukup']);

        $w = Kepatuhan::pantau(1000, 720, 50);
        $this->assertSame('peringatan', $w['status']);
        $s = Kepatuhan::pantau(1000, 600, 50, null, 625, 10);
        $this->assertSame('sulam', $s['status']);
        $this->assertSame((int) ceil((625 - 406.3) * 10), $s['kebutuhan_sulam']);   // hidup/ha 406,25 → 406,3
    }

    public function test_fase_diturunkan_dari_tanggal_tanam(): void
    {
        $p = $this->ppkh();
        $this->assertSame('penetapan', $this->blok($p, ['rkp_nomor' => null, 'rkp_disahkan_at' => null, 'tanggal_tanam' => null])->fase);
        $this->assertSame('rkp', $this->blok($p, ['tanggal_tanam' => null])->fase);
        $this->assertSame('p0', $this->blok($p, ['tanggal_tanam' => '2026-03-01'])->fase);
        $this->assertSame('p1', $this->blok($p, ['tanggal_tanam' => '2025-06-01'])->fase);
        $this->assertSame('p2', $this->blok($p, ['tanggal_tanam' => '2024-06-01'])->fase);
        $this->assertSame('siap_nilai', $this->blok($p, ['tanggal_tanam' => '2023-09-15'])->fase);
        $this->assertSame('ditetapkan', $p->fresh()->status_lokasi);
    }

    public function test_komposisi_lokal_dan_cepat_tumbuh(): void
    {
        $ok = Kepatuhan::komposisi([['jenis' => 'meranti', 'persen' => 40], ['jenis' => 'sengon', 'persen' => 40], ['jenis' => 'durian', 'persen' => 20]]);
        $this->assertTrue($ok['lokal_cukup'] && $ok['cepat_wajar'] && $ok['genap']);
        $buruk = Kepatuhan::komposisi([['jenis' => 'meranti', 'persen' => 20], ['jenis' => 'sengon', 'persen' => 70], ['jenis' => 'durian', 'persen' => 10]]);
        $this->assertFalse($buruk['lokal_cukup']);
        $this->assertFalse($buruk['cepat_wajar']);
    }

    /* ─────────── petak & Petak Ukur ─────────── */

    public function test_poligon_geojson_dan_daftar_koordinat_terbaca(): void
    {
        $b = $this->blok($this->ppkh());
        $geo = json_encode(['type' => 'Polygon', 'coordinates' => [[[116.80, -0.45], [116.81, -0.45], [116.81, -0.46], [116.80, -0.46], [116.80, -0.45]]]]);
        $this->actingAs($this->u)->post("/rehab-das/blok/{$b->id}/petak", ['kode' => 'P01', 'luas_ha' => 20, 'poligon' => $geo])->assertSessionHasNoErrors();
        $p = Petak::where('kode', 'P01')->firstOrFail();
        $this->assertCount(4, $p->poligon);                                    // titik penutup dibuang
        $this->assertSame([-0.45, 116.8], $p->poligon[0]);
        $this->assertEqualsWithDelta(-0.455, $p->lat, 0.0001);

        $this->actingAs($this->u)->post("/rehab-das/blok/{$b->id}/petak", ['kode' => 'P02', 'luas_ha' => 20, 'poligon' => 'bukan poligon'])->assertSessionHasErrors('poligon');
        $this->actingAs($this->u)->post("/rehab-das/blok/{$b->id}/petak", ['kode' => 'P01', 'luas_ha' => 5])->assertSessionHasErrors('kode');
    }

    public function test_petak_ukur_menyalin_hasil_ke_petak(): void
    {
        $b = $this->blok($this->ppkh());
        $this->actingAs($this->u)->post("/rehab-das/blok/{$b->id}/petak", ['kode' => 'P01', 'luas_ha' => 20, 'lat' => -0.45, 'lng' => 116.8]);
        $p = Petak::firstOrFail();
        $this->actingAs($this->u)->post("/rehab-das/petak/{$p->id}/pantau", ['tanggal' => '2026-10-20', 'periode' => 'p2', 'jumlah_pu' => 10,
            'diamati' => 625, 'sehat' => 400, 'merana' => 40, 'pengamat' => 'Andi'])->assertRedirect()->assertSessionHas('galat');
        $p->refresh();
        $this->assertSame(70.4, $p->persen_tumbuh);
        $this->assertSame('sulam', $p->status_tumbuh);
        $pu = Pantau::firstOrFail();
        $this->assertSame('PU-2026-0001', $pu->kode);
        $this->assertGreaterThan(0, $pu->kebutuhan_sulam);

        // Sehat + merana tidak boleh melebihi diamati.
        $this->actingAs($this->u)->post("/rehab-das/petak/{$p->id}/pantau", ['tanggal' => '2026-10-21', 'periode' => 'p2', 'jumlah_pu' => 10,
            'diamati' => 100, 'sehat' => 90, 'merana' => 20, 'pengamat' => 'Andi'])->assertSessionHasErrors('diamati');
    }

    /* ─────────── kelayakan & penilaian ─────────── */

    public function test_kelayakan_membaca_sepuluh_prasyarat(): void
    {
        $b = $this->blokLayak();
        $k = Kepatuhan::kelayakan($b);
        $this->assertTrue($k['layak'], implode('; ', $k['penghenti']));
        $this->assertSame(10, $k['jumlah']);

        // Geotag duplikat menahan pengajuan.
        $b->update(['geotag_duplikat' => 120]);
        $this->assertContains('Geotagging 100% tanpa duplikasi', Kepatuhan::kelayakan($b->fresh())['penghenti']);
        $b->update(['geotag_duplikat' => 0]);

        // Kebakaran yang belum ditangani menahan pengajuan.
        $this->actingAs($this->u)->post("/rehab-das/kewajiban/{$b->ppkh_id}/kejadian", ['jenis' => 'kebakaran', 'tanggal' => '2026-11-01', 'blok_id' => $b->id,
            'luas_terdampak_ha' => 4, 'uraian' => 'Kebakaran permukaan'])->assertSessionHasNoErrors();
        $this->assertContains('Tidak ada gangguan yang belum ditangani', Kepatuhan::kelayakan($b->fresh())['penghenti']);
    }

    public function test_blok_belum_layak_tidak_dapat_diajukan(): void
    {
        $b = $this->blok($this->ppkh(), ['tanggal_tanam' => '2025-06-01']);
        $this->actingAs($this->u)->post("/rehab-das/blok/{$b->id}/penilaian", ['luas_diajukan_ha' => 40])->assertSessionHasErrors('luas_diajukan_ha');
        $this->assertSame(0, Penilaian::count());
    }

    public function test_alur_penilaian_sampai_bast_bertahap(): void
    {
        $b = $this->blokLayak();
        $this->actingAs($this->u)->post("/rehab-das/blok/{$b->id}/penilaian", ['luas_diajukan_ha' => 25])->assertSessionHasNoErrors();
        $p = Penilaian::firstOrFail();
        $this->assertSame('diajukan', $p->status);
        $this->assertSame('penilaian', $b->fresh()->fase);
        $this->assertCount(10, $p->prasyarat);

        // Tidak boleh dua permohonan berjalan sekaligus.
        $this->actingAs($this->u)->post("/rehab-das/blok/{$b->id}/penilaian", ['luas_diajukan_ha' => 10])->assertSessionHasErrors('luas_diajukan_ha');

        $this->actingAs($this->u)->post("/rehab-das/penilaian/{$p->id}", ['aksi' => 'bast', 'bast_nomor' => 'X', 'bast_at' => '2026-11-20'])->assertSessionHasErrors('aksi');
        $this->actingAs($this->u)->post("/rehab-das/penilaian/{$p->id}", ['aksi' => 'tim', 'sk_tim_nomor' => 'SK.11/TKTRH', 'sk_tim_at' => '2026-11-01', 'ketua_tim' => 'Kepala BPDAS'])->assertSessionHasNoErrors();
        $this->actingAs($this->u)->post("/rehab-das/penilaian/{$p->id}", ['aksi' => 'hasil', 'persen_tumbuh' => 83, 'sehat_ha' => 520, 'luas_berhasil_ha' => 25])->assertSessionHasNoErrors();
        $this->assertSame('berhasil', $p->fresh()->status);
        $this->actingAs($this->u)->post("/rehab-das/penilaian/{$p->id}", ['aksi' => 'bast', 'bast_nomor' => 'BAST.07/PDASRH/11/2026', 'bast_at' => '2026-11-20'])->assertSessionHasNoErrors();
        $this->assertSame('serah_terima', $p->fresh()->status);

        // Baru 25 dari 40 ha: blok belum serah terima, tahap II boleh diajukan untuk sisanya.
        $this->assertNotSame('serah_terima', $b->fresh()->fase);
        $this->actingAs($this->u)->post("/rehab-das/blok/{$b->id}/penilaian", ['luas_diajukan_ha' => 20])->assertSessionHasErrors('luas_diajukan_ha');   // > sisa 15 ha
        $this->actingAs($this->u)->post("/rehab-das/blok/{$b->id}/penilaian", ['luas_diajukan_ha' => 15])->assertSessionHasNoErrors();
        $p2 = Penilaian::latest('id')->firstOrFail();
        $this->assertSame(2, $p2->tahap);
        $this->actingAs($this->u)->post("/rehab-das/penilaian/{$p2->id}", ['aksi' => 'tim', 'sk_tim_nomor' => 'SK.12', 'sk_tim_at' => '2026-11-21', 'ketua_tim' => 'Kepala BPDAS']);
        $this->actingAs($this->u)->post("/rehab-das/penilaian/{$p2->id}", ['aksi' => 'hasil', 'persen_tumbuh' => 81, 'sehat_ha' => 505, 'luas_berhasil_ha' => 15]);
        $this->actingAs($this->u)->post("/rehab-das/penilaian/{$p2->id}", ['aksi' => 'bast', 'bast_nomor' => 'BAST.08', 'bast_at' => '2026-11-23']);
        $this->assertSame('serah_terima', $b->fresh()->fase);
    }

    public function test_hasil_tim_di_bawah_ambang_tidak_berhasil(): void
    {
        $b = $this->blokLayak();
        $this->actingAs($this->u)->post("/rehab-das/blok/{$b->id}/penilaian", ['luas_diajukan_ha' => 40]);
        $p = Penilaian::firstOrFail();
        $this->actingAs($this->u)->post("/rehab-das/penilaian/{$p->id}", ['aksi' => 'tim', 'sk_tim_nomor' => 'SK.1', 'sk_tim_at' => '2026-11-01', 'ketua_tim' => 'Ka BPDAS']);
        $this->actingAs($this->u)->post("/rehab-das/penilaian/{$p->id}", ['aksi' => 'hasil', 'persen_tumbuh' => 71, 'sehat_ha' => 430, 'luas_berhasil_ha' => 40])->assertSessionHas('galat');
        $p->refresh();
        $this->assertSame('tidak_berhasil', $p->status);
        $this->assertSame(0.0, $p->luas_berhasil_ha);
        $this->assertSame('siap_nilai', $b->fresh()->fase);                    // kembali ke pemeliharaan lanjutan
    }

    /* ─────────── kegiatan, kejadian, laporan ─────────── */

    public function test_verifikasi_kegiatan_empat_mata(): void
    {
        $b = $this->blok($this->ppkh());
        $this->actingAs($this->u)->post("/rehab-das/blok/{$b->id}/petak", ['kode' => 'P01', 'luas_ha' => 20, 'lat' => -0.45, 'lng' => 116.8]);
        $petak = Petak::firstOrFail();
        $this->actingAs($this->u)->post("/rehab-das/blok/{$b->id}/kegiatan", ['tanggal' => '2026-11-20', 'jenis' => 'penyulaman', 'petak_id' => $petak->id,
            'luas_ha' => 3, 'bibit' => 900, 'hok' => 12, 'biaya_rp' => 8_550_000])->assertSessionHasNoErrors();
        $k = Kegiatan::firstOrFail();
        $this->assertSame('diajukan', $k->status);
        $this->assertSame(strtoupper(Katalog::KEGIATAN['penyulaman']['fase']), $k->fase);   // blok di luar P0–P2 → fase dari jenis kegiatan

        $this->actingAs($this->u)->post("/rehab-das/kegiatan/{$k->id}/verifikasi", ['aksi' => 'terima'])->assertSessionHasErrors('aksi');
        $this->actingAs($this->petugas)->post("/rehab-das/kegiatan/{$k->id}/verifikasi", ['aksi' => 'tolak'])->assertSessionHasErrors('catatan');
        $this->actingAs($this->petugas)->post("/rehab-das/kegiatan/{$k->id}/verifikasi", ['aksi' => 'terima'])->assertSessionHasNoErrors();
        $this->assertSame('diverifikasi', $k->fresh()->status);
        $this->assertSame(900, $petak->fresh()->bibit_sulam);
    }

    public function test_kebakaran_ditutup_setelah_dilaporkan_dan_exit_strategy(): void
    {
        $p = $this->ppkh();
        $this->actingAs($this->u)->post("/rehab-das/kewajiban/{$p->id}/kejadian", ['jenis' => 'kebakaran', 'tanggal' => '2026-09-01', 'luas_terdampak_ha' => 12, 'uraian' => 'Karhutla'])
            ->assertSessionHasNoErrors();
        $k = Kejadian::firstOrFail();
        $this->assertSame('terbuka', $k->status);
        $this->actingAs($this->u)->post("/rehab-das/kejadian/{$k->id}", ['status' => 'selesai'])->assertSessionHasErrors('status');
        $this->actingAs($this->u)->post("/rehab-das/kejadian/{$k->id}", ['status' => 'selesai', 'strategi' => 'parsial', 'dilaporkan_at' => '2026-09-03'])->assertSessionHasNoErrors();
        $this->assertSame('selesai', $k->fresh()->status);

        // Teguran adalah sanksi tahap 1 dan terbaca di ringkasan.
        $this->actingAs($this->u)->post("/rehab-das/kewajiban/{$p->id}/kejadian", ['jenis' => 'teguran_1', 'tanggal' => '2026-10-01', 'uraian' => 'Teguran I'])->assertSessionHasNoErrors();
        $this->assertSame(1, Kepatuhan::ringkasPpkh($p->fresh())['sanksi_tahap']);
    }

    public function test_laporan_terlambat_dan_tercatat(): void
    {
        $p = $this->ppkh();
        $l = Laporan::where('ppkh_id', $p->id)->where('tahun', 2026)->where('semester', 1)->firstOrFail();
        $this->assertSame('terlambat', $l->keadaan());
        $this->actingAs($this->u)->post("/rehab-das/laporan/{$l->id}", ['dikirim_at' => '2026-07-20', 'tanda_terima' => 'TT-01'])->assertSessionHasNoErrors();
        $this->assertSame('tepat', $l->fresh()->keadaan());
    }

    /* ─────────── batas & halaman ─────────── */

    public function test_batas_perusahaan(): void
    {
        $b = $this->blok($this->ppkh());
        $lain = User::factory()->create(['company_id' => Company::create(['name' => 'Lain'])->id]);
        $this->actingAs($lain)->get("/rehab-das/blok/{$b->id}")->assertNotFound();
        $this->actingAs($lain)->get("/rehab-das/kewajiban/{$b->ppkh_id}")->assertNotFound();
        $this->actingAs($lain)->post('/rehab-das/blok', ['ppkh_id' => $b->ppkh_id, 'nama' => 'X', 'das' => 'Y', 'fungsi_kawasan' => 'HL', 'ekosistem' => 'daratan',
            'luas_ha' => 5, 'kerapatan' => 625])->assertNotFound();
    }

    public function test_seluruh_halaman_terbuka(): void
    {
        $b = $this->blokLayak();
        $petak = Petak::firstOrFail();
        foreach ([
            '/rehab-das' => 'Rdas/Dasbor', '/rehab-das/peta' => 'Rdas/Peta', '/rehab-das/kewajiban' => 'Rdas/Kewajiban',
            "/rehab-das/kewajiban/{$b->ppkh_id}" => 'Rdas/Ppkh', '/rehab-das/blok/baru' => 'Rdas/FormBlok', "/rehab-das/blok/{$b->id}" => 'Rdas/Blok',
            "/rehab-das/blok/{$b->id}/ubah" => 'Rdas/FormBlok', "/rehab-das/petak/{$petak->id}/pantau" => 'Rdas/Pantau',
            '/rehab-das/penilaian' => 'Rdas/Penilaian', '/rehab-das/acuan' => 'Rdas/Acuan',
        ] as $url => $komponen) {
            // Prop halaman tidak boleh menimpa prop bersama `pengguna` — tanpa itu
            // kepala halaman menampilkan avatar "?" dan "Semua perusahaan".
            $this->actingAs($this->u)->get($url)->assertOk()->assertInertia(fn (Assert $p) => $p->component($komponen)
                ->where('pengguna.nama', $this->u->name));
        }
        $this->actingAs($this->u)->get('/rehab-das/ekspor/geojson')->assertOk()->assertJsonPath('features.0.geometry.type', 'Polygon');
        $this->actingAs($this->u)->get('/rehab-das/ekspor/csv')->assertOk();
        $this->actingAs($this->u)->postJson('/rehab-das/pantau/hitung', ['diamati' => 100, 'sehat' => 70, 'merana' => 5])->assertOk()->assertJsonPath('status', 'peringatan');
    }
}
