<?php

namespace Tests\Feature;

use App\Models\{Company, EnergyBaseline, EnergyEquipment, EnergyFuelLog, EnergyProduction,
                GudangBarang, GudangMutasi, HazardReport, Procedure, SmkpAudit, SopEvaluation,
                SopEvaluationAttempt, User};
use App\Support\{Hazard, Kesesuaian, Smkp};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kesesuaian isi, penilaian, dan evaluasi tiap modul.
 *
 * Seperti DiagnosaTest, yang diuji bukan bahwa pemeriksaannya berjalan
 * melainkan bahwa ia MENEMUKAN. Pemeriksaan yang selalu menjawab "aman"
 * lulus setiap uji yang sekadar memanggilnya, dan itu bentuk kegagalan
 * yang paling mahal: orang berhenti waspada justru di tempat yang
 * seharusnya diawasi.
 *
 * Karena itu tiap pemeriksaan penting diuji dua arah — keadaan sehat
 * harus terbaca aman, keadaan rusak harus terbaca rusak.
 */
class KesesuaianTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string,array<string,mixed>> hasil, dikunci per judul */
    private function periksa(?Company $c = null): array
    {
        $h = [];
        foreach (Kesesuaian::jalankan($c) as $x) $h[$x['judul']] = $x;

        return $h;
    }

    private function perusahaan(): Company
    {
        return Company::create(['name' => 'PT Uji Kesesuaian']);
    }

    /* ═══════════ modul kosong ═══════════ */

    public function test_modul_kosong_dinyatakan_belum_dapat_dinilai_bukan_aman(): void
    {
        $h = $this->periksa();

        $kosong = array_filter($h, fn ($x) => $x['nilai'] === 'kosong');
        $this->assertNotEmpty($kosong, 'Basis kosong seharusnya menghasilkan baris "kosong".');

        foreach ($kosong as $x) {
            $this->assertSame(
                Kesesuaian::TAK_TAHU, $x['keadaan'],
                'Modul kosong tidak boleh dinyatakan aman — ia belum membuktikan apa pun.'
            );
        }
    }

    /* ═══════════ Gudang — stok negatif ═══════════ */

    private function barang(Company $c): GudangBarang
    {
        return GudangBarang::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'kode' => 'BRG-1', 'nama' => 'Sarung tangan',
            'kategori' => 'apd', 'satuan' => 'pasang',
        ]);
    }

    public function test_stok_wajar_terbaca_aman(): void
    {
        $c = $this->perusahaan();
        $b = $this->barang($c);

        GudangMutasi::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'barang_id' => $b->id,
            'jenis' => 'masuk', 'tanggal' => '2026-01-05', 'jumlah' => 10,
        ]);

        $h = $this->periksa($c);
        $this->assertSame(Kesesuaian::AMAN, $h['Tidak ada stok bernilai negatif']['keadaan']);
    }

    public function test_stok_negatif_tertangkap_sebagai_gawat(): void
    {
        $c = $this->perusahaan();
        $b = $this->barang($c);

        // Keluar lebih banyak daripada yang pernah masuk — mustahil secara fisik.
        GudangMutasi::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'barang_id' => $b->id,
            'jenis' => 'masuk', 'tanggal' => '2026-01-05', 'jumlah' => 3,
        ]);
        GudangMutasi::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'barang_id' => $b->id,
            'jenis' => 'keluar', 'tanggal' => '2026-01-06', 'jumlah' => 8,
        ]);

        $x = $this->periksa($c)['Tidak ada stok bernilai negatif'];

        $this->assertSame(Kesesuaian::GAWAT, $x['keadaan']);
        $this->assertStringContainsString('BRG-1', $x['uraian']);
    }

    /* ═══════════ Energi — baseline terbalik ═══════════ */

    private function unit(Company $c): EnergyEquipment
    {
        return EnergyEquipment::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'kode' => 'HD-'.fake()->unique()->numberBetween(10, 99),
            'nama' => 'Dump Truck', 'kategori' => 'hauling',
        ]);
    }

    private function energiDasar(Company $c): void
    {
        EnergyFuelLog::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'equipment_id' => $this->unit($c)->id,
            'tanggal' => '2026-01-05', 'liter' => 400, 'hm' => 10,
        ]);
        EnergyProduction::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'tanggal' => '2026-01-05', 'ton' => 500,
        ]);
    }

    public function test_baseline_menurun_terbaca_aman(): void
    {
        $c = $this->perusahaan();
        $this->energiDasar($c);

        EnergyBaseline::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'tahun' => 2026,
            'baseline_gj_ton' => 0.12, 'target_gj_ton' => 0.10,
        ]);

        $h = $this->periksa($c);
        $this->assertSame(
            Kesesuaian::AMAN,
            $h['Target baseline lebih rendah daripada baselinenya']['keadaan']
        );
    }

    public function test_target_baseline_lebih_boros_tertangkap(): void
    {
        $c = $this->perusahaan();
        $this->energiDasar($c);

        // Ditulis langsung ke basis data: controllernya memang menolak ini,
        // dan yang sedang diuji justru apakah baris semacam ini ketahuan
        // bila sempat masuk lewat jalan lain — impor, penyemai, atau SQL.
        EnergyBaseline::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'tahun' => 2026,
            'baseline_gj_ton' => 0.10, 'target_gj_ton' => 0.20,
        ]);

        $x = $this->periksa($c)['Target baseline lebih rendah daripada baselinenya'];
        $this->assertSame(Kesesuaian::GAWAT, $x['keadaan']);
    }

    public function test_pemakaian_tanpa_produksi_diperingatkan(): void
    {
        $c = $this->perusahaan();

        EnergyFuelLog::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'equipment_id' => $this->unit($c)->id,
            'tanggal' => '2026-01-05', 'liter' => 400, 'hm' => 10,
        ]);

        $x = $this->periksa($c)['Ada pemakaian tetapi produksi nol'];

        $this->assertSame(Kesesuaian::PERHATIAN, $x['keadaan']);
        $this->assertStringContainsString('nol', $x['uraian']);
    }

    /* ═══════════ SMKP — skor yang belum layak dibaca ═══════════ */

    public function test_audit_yang_baru_terisi_sedikit_tidak_dibaca_sebagai_capaian(): void
    {
        $c = $this->perusahaan();

        // Satu butir dinilai saja dari seratusan yang berlaku. Kuncinya 'v',
        // sesuai yang dibaca Smkp::nilaiButir — kunci lain akan terbaca
        // sebagai belum dinilai, sehingga ujinya lulus tanpa benar-benar
        // menguji butir yang terisi.
        $butir = Smkp::butir();

        SmkpAudit::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'tahun' => 2026, 'status' => 'draf',
            'hasil' => [$butir[0]['kode'] => ['v' => $butir[0]['maks']]],
        ]);

        $x = $this->periksa($c)['Skor belum layak dibaca sebagai capaian'];

        $this->assertSame(Kesesuaian::PERHATIAN, $x['keadaan']);
        $this->assertStringContainsString('terisi', $x['nilai']);
    }

    public function test_skor_smkp_tetap_dilaporkan_dalam_rentang_sah(): void
    {
        $c = $this->perusahaan();

        SmkpAudit::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'tahun' => 2026, 'status' => 'draf', 'hasil' => [],
        ]);

        $h = $this->periksa($c);
        $this->assertSame(Kesesuaian::AMAN, $h['Skor akhir dalam rentang 0–100']['keadaan']);
        $this->assertSame(Kesesuaian::AMAN, $h['Nilai tidak melampaui nilai maksimum']['keadaan']);
    }

    /* ═══════════ Hazard — kosakata status ═══════════ */

    private function hazard(Company $c, string $status): void
    {
        HazardReport::withoutGlobalScopes()->create([
            'company_id' => $c->id,
            'kode'       => 'HZ-'.fake()->unique()->numberBetween(100, 999),
            'tanggal'    => '2026-01-05',
            'lokasi'     => 'Pit Utara',
            'risiko'     => 'Tinggi',
            'deskripsi'   => 'Uji kosakata status',
            'pelapor_nama'=> 'Pelapor Uji',
            'status'     => $status,
        ]);
    }

    public function test_status_hazard_yang_dikenal_terbaca_aman(): void
    {
        $c = $this->perusahaan();
        $this->hazard($c, Hazard::STATUS[0]);

        $x = $this->periksa($c)['Status laporan memakai kosakata yang dikenal'];
        $this->assertSame(Kesesuaian::AMAN, $x['keadaan']);
    }

    public function test_status_hazard_di_luar_daftar_tertangkap(): void
    {
        $c = $this->perusahaan();

        // Padanan Indonesia — tersimpan rapi, tetapi tidak cocok dengan
        // saringan status mana pun maupun corong KPI-nya.
        $this->hazard($c, 'terbuka');

        $x = $this->periksa($c)['Status laporan memakai kosakata yang dikenal'];

        $this->assertSame(Kesesuaian::GAWAT, $x['keadaan']);
        $this->assertStringContainsString('terbuka', $x['nilai']);
    }

    /* ═══════════ Evaluasi — skor vs jawaban ═══════════ */

    private function percobaan(array $isi): void
    {
        $u = User::factory()->create();
        $p = Procedure::create(['code' => 'SOP-1', 'title' => 'Prosedur uji']);
        $e = SopEvaluation::create(['title' => 'Uji', 'procedure_id' => $p->id]);

        SopEvaluationAttempt::create([
            'user_id'       => $u->id,
            'evaluation_id' => $e->id,
            'score'         => $isi['score'],
            'total'         => $isi['total'],
            'correct'       => $isi['correct'],
            'passed'        => true,
            'answers'       => [],
        ]);
    }

    public function test_skor_kuis_yang_cocok_terbaca_aman(): void
    {
        $this->percobaan(['score' => 80, 'total' => 10, 'correct' => 8]);

        $x = $this->periksa()['Skor cocok dengan jawaban benar per jumlah soal'];
        $this->assertSame(Kesesuaian::AMAN, $x['keadaan']);
    }

    public function test_skor_kuis_yang_tidak_cocok_tertangkap(): void
    {
        // Delapan dari sepuluh benar, tetapi skornya tersimpan 100.
        $this->percobaan(['score' => 100, 'total' => 10, 'correct' => 8]);

        $x = $this->periksa()['Skor cocok dengan jawaban benar per jumlah soal'];
        $this->assertSame(Kesesuaian::GAWAT, $x['keadaan']);
    }

    public function test_jawaban_benar_melebihi_jumlah_soal_tertangkap(): void
    {
        $this->percobaan(['score' => 100, 'total' => 5, 'correct' => 9]);

        $x = $this->periksa()['Skor cocok dengan jawaban benar per jumlah soal'];
        $this->assertSame(Kesesuaian::GAWAT, $x['keadaan']);
    }

    /* ═══════════ ketahanan ═══════════ */

    public function test_satu_modul_yang_meledak_tidak_mematikan_sisanya(): void
    {
        // Tidak ada cara sederhana meruntuhkan satu modul dari luar, jadi
        // yang dijamin di sini adalah bentuk keluarannya: seluruh baris
        // selalu membawa keadaan yang dikenal, sehingga halaman yang
        // menggambarnya tidak pernah menerima nilai yang tak terduga.
        foreach (Kesesuaian::jalankan() as $x) {
            $this->assertContains($x['keadaan'], [
                Kesesuaian::AMAN, Kesesuaian::PERHATIAN,
                Kesesuaian::GAWAT, Kesesuaian::TAK_TAHU,
            ]);
            foreach (['kelompok', 'judul', 'nilai', 'uraian', 'tindakan'] as $k) {
                $this->assertArrayHasKey($k, $x);
                $this->assertNotSame('', (string) $x[$k], "Kunci '$k' tidak boleh kosong.");
            }
        }
    }

    public function test_ringkasan_menghitung_tiap_keadaan(): void
    {
        $r = Kesesuaian::ringkas(Kesesuaian::jalankan());

        $this->assertSame(
            array_keys($r),
            [Kesesuaian::GAWAT, Kesesuaian::PERHATIAN, Kesesuaian::TAK_TAHU, Kesesuaian::AMAN]
        );
        $this->assertSame(count(Kesesuaian::jalankan()), array_sum($r));
    }
}
