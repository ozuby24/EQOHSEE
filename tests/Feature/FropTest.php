<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use App\Models\Frop\{Coaching, Observasi};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Shared\Date as TanggalExcel;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * Observasi operator loader (FROP) di Learning Center.
 *
 * Yang dijaga: halaman terbuka, batas perusahaan, sesi tersimpan dengan
 * plan dan loading time yang benar, tindak lanjut temuan, coaching,
 * dan impor berkas kerja — termasuk isian ganjil yang memang ada di
 * berkas aslinya (loading "1:56" yang dibaca Excel sebagai jam, N
 * passing berupa teks, kolom yang bergeser).
 */
class FropTest extends TestCase
{
    use RefreshDatabase;

    private static int $n = 0;

    private function perusahaan(): Company
    {
        $i = ++self::$n;

        return Company::create(['name' => "PT Uji FROP {$i}", 'code' => "FR{$i}"]);
    }

    private function masuk(?Company $c = null, bool $admin = false): User
    {
        $u = User::factory()->create(['is_admin' => $admin, 'company_id' => $c?->id]);
        $this->actingAs($u);

        return $u;
    }

    private function sesi(Company $c, array $x = []): Observasi
    {
        return Observasi::withoutGlobalScopes()->create(array_merge([
            'company_id' => $c->id, 'tanggal' => '2026-07-02', 'shift' => 1, 'unit' => 'Ex 700',
            'operator' => 'Operator Satu', 'level' => 'average', 'plan_ct' => 28,
            'spotting' => 8, 'digging' => 10, 'swl' => 7, 'dump' => 3, 'swe' => 5,
            'target_pty' => 600, 'aktual_pty' => 570, 'status_ca' => 'Open',
        ], $x));
    }

    private function isian(array $x = []): array
    {
        return array_merge([
            'tanggal' => '2026-07-10', 'shift' => '1', 'jam_observasi' => '09.00 – 10.00',
            'unit' => 'Ex 699 / PC 1250', 'operator' => 'Operator Dua', 'level' => 'severe',
            'spotting' => '12', 'digging' => '13', 'swl' => '7', 'dump' => '3', 'swe' => '5',
            'plan_ct' => '', 'loading' => '1:45', 'n_passing' => '5-6', 'bucket_heap' => '0',
            'target_pty' => '600', 'aktual_pty' => '480', 'boulder' => '',
            'temuan' => 'Front undulating; Exc menggantung', 'status_ca' => 'Open',
        ], $x);
    }

    public function test_seluruh_halaman_terbuka(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $s = $this->sesi($c, ['temuan' => 'front undulating']);
        Coaching::withoutGlobalScopes()->create(['company_id' => $c->id, 'tanggal' => '2026-07-02',
            'operator' => 'Operator Satu', 'materi' => 'Teknik digging', 'status' => 'Open']);

        foreach (['frop.index', 'frop.create', 'frop.operator', 'frop.kpi', 'frop.tracker',
                  'frop.coaching', 'frop.impor', 'frop.panduan'] as $r) {
            $this->get(route($r))->assertOk();
        }
        $this->get(route('frop.show', $s))->assertOk();
        $this->get(route('frop.edit', $s))->assertOk();
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('frop.index'))->assertRedirect(route('login'));
    }

    public function test_daftar_menilai_ct_terhadap_plan_material(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        // CT 30 di material severe: ON TARGET (plan 31), bukan OVER terhadap 22.
        $this->sesi($c, ['level' => 'severe', 'plan_ct' => 31, 'digging' => 14, 'swl' => 8]);
        $this->sesi($c, ['operator' => 'Operator Tiga', 'digging' => 20]);

        $this->get(route('frop.index', ['bulan' => 'semua']))->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Frop/Daftar')
            ->where('ringkas.sesi', 2)
            ->where('ringkas.on', 1)
            ->where('daftar', fn ($d) => collect($d)->firstWhere('operator', 'Operator Satu')['rekomendasi']['kode'] === 'pertahankan'));
    }

    public function test_perusahaan_lain_tidak_terlihat(): void
    {
        $a = $this->perusahaan();
        $b = $this->perusahaan();
        $milikB = $this->sesi($b);
        $this->masuk($a);

        $this->get(route('frop.show', $milikB))->assertNotFound();
        $this->put(route('frop.ca', $milikB), ['status_ca' => 'Open'])->assertNotFound();
        /* Halaman yang kosong bagi perusahaan ini — sempat galat 500. */
        foreach (['frop.index', 'frop.operator', 'frop.kpi', 'frop.tracker', 'frop.create'] as $r) {
            $this->get(route($r))->assertOk();
        }
        $this->get(route('frop.index', ['bulan' => 'semua']))
            ->assertInertia(fn (AssertableInertia $p) => $p->where('ringkas.sesi', 0));
    }

    public function test_simpan_sesi_menurunkan_plan_dan_loading(): void
    {
        $c = $this->perusahaan();
        $u = $this->masuk($c);

        $this->post(route('frop.store'), $this->isian())->assertRedirect();

        $o = Observasi::firstOrFail();
        $this->assertSame($c->id, $o->company_id);
        $this->assertSame($u->id, $o->user_id);
        $this->assertSame(31.0, $o->plan_ct);        // mengikuti level severe
        $this->assertSame(105, $o->loading_detik);   // 1:45
        $this->assertFalse($o->bucket_heap);
        $this->assertNull($o->boulder);
        $this->assertNull($o->selesai_ca);
        $this->assertSame('5-6', $o->n_passing);
    }

    public function test_plan_manual_menang_atas_level(): void
    {
        $this->masuk($this->perusahaan());
        $this->post(route('frop.store'), $this->isian(['plan_ct' => '22']));

        $this->assertSame(22.0, Observasi::firstOrFail()->plan_ct);
    }

    public function test_validasi_sesi(): void
    {
        $this->masuk($this->perusahaan());

        $this->post(route('frop.store'), $this->isian(['digging' => '', 'loading' => '1:75', 'n_passing' => 'lima', 'level' => 'keras']))
            ->assertSessionHasErrors(['digging', 'loading', 'n_passing', 'level']);
        $this->assertSame(0, Observasi::count());
    }

    public function test_status_closed_mengisi_dan_membuka_kembali_mengosongkan_tanggal_selesai(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $s = $this->sesi($c, ['temuan' => 'boulder']);

        $this->put(route('frop.ca', $s), ['status_ca' => 'Closed'])->assertSessionHasErrors('selesai_ca');

        $this->put(route('frop.ca', $s), ['status_ca' => 'Closed', 'selesai_ca' => '2026-07-05', 'pic_ca' => 'GL A'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2026-07-05', $s->fresh()->selesai_ca->toDateString());

        $this->put(route('frop.ca', $s), ['status_ca' => 'In Progress', 'selesai_ca' => '2026-07-05']);
        $this->assertNull($s->fresh()->selesai_ca);
    }

    public function test_ubah_sesi_mempertahankan_tanggal_selesai_yang_ada(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $s = $this->sesi($c, ['status_ca' => 'Closed', 'selesai_ca' => '2026-07-03']);

        $this->put(route('frop.update', $s), $this->isian(['status_ca' => 'Closed']))->assertRedirect();

        $this->assertSame('2026-07-03', $s->fresh()->selesai_ca->toDateString());
    }

    public function test_hapus_hanya_admin(): void
    {
        $c = $this->perusahaan();
        $s = $this->sesi($c);

        $this->masuk($c);
        $this->delete(route('frop.destroy', $s))->assertForbidden();

        $this->masuk($c, true);
        $this->delete(route('frop.destroy', $s))->assertRedirect();
        $this->assertSame(0, Observasi::withoutGlobalScopes()->count());
    }

    public function test_rincian_membandingkan_dengan_sesi_operator_yang_sama(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $this->sesi($c, ['tanggal' => '2026-07-01', 'digging' => 12]);                                  // CT 27
        $this->sesi($c, ['tanggal' => '2026-07-02', 'operator' => 'Orang Lain', 'digging' => 5]);        // CT 20
        $kini = $this->sesi($c, ['tanggal' => '2026-07-03', 'digging' => 10]);                           // CT 25

        $this->get(route('frop.show', $kini))->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Frop/Rincian')
            ->has('riwayat', 2)
            ->where('riwayat.1.banding', -2)          // 25 − 27, bukan 25 − 20
            ->where('riwayat.1.kini', true)
            ->where('rekap.arah', 'membaik'));
    }

    public function test_performa_memuat_seluruh_operator_dan_nama_bervariasi_disatukan(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $this->sesi($c, ['operator' => 'Operator  Satu']);
        $this->sesi($c, ['operator' => 'operator satu', 'tanggal' => '2026-07-05']);
        $this->sesi($c, ['operator' => 'Operator Empat']);

        $this->get(route('frop.operator'))->assertInertia(fn (AssertableInertia $p) => $p
            ->has('daftar', 2)
            ->where('daftar', fn ($d) => collect($d)->pluck('total')->sort()->values()->all() === [1, 2]));
    }

    public function test_kpi_bulan(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $this->sesi($c, ['tanggal' => '2026-07-02']);
        $this->sesi($c, ['tanggal' => '2026-07-09', 'digging' => 20]);
        $this->sesi($c, ['tanggal' => '2026-08-01']);

        $this->get(route('frop.kpi', ['bulan' => '2026-07']))->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Frop/Kpi')
            ->where('total.sesi', 2)
            ->where('total.on_target', 0.5)
            ->has('pekan', 4));
    }

    public function test_coaching_dari_sesi_terisi_dan_tertaut(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $s = $this->sesi($c, ['level' => 'easy', 'plan_ct' => 24, 'digging' => 12]);   // OVER, digging > 10

        $this->get(route('frop.coaching', ['observasi' => $s->id]))->assertInertia(fn (AssertableInertia $p) => $p
            ->where('awal.operator', 'Operator Satu')
            ->where('awal.observasi_id', (string) $s->id)
            ->where('awal.materi', 'Teknik Digging – kurangi beban bucket'));

        $this->post(route('frop.coaching.store'), [
            'observasi_id' => $s->id, 'tanggal' => '2026-07-02', 'operator' => 'Operator Satu',
            'materi' => 'Teknik digging', 'status' => 'Open',
        ])->assertRedirect();

        $this->assertSame($s->id, Coaching::firstOrFail()->observasi_id);
    }

    public function test_coaching_tidak_dapat_merujuk_sesi_perusahaan_lain(): void
    {
        $lain = $this->sesi($this->perusahaan());
        $this->masuk($this->perusahaan());

        $this->post(route('frop.coaching.store'), [
            'observasi_id' => $lain->id, 'tanggal' => '2026-07-02', 'operator' => 'X', 'materi' => 'Y', 'status' => 'Open',
        ]);

        $this->assertNull(Coaching::firstOrFail()->observasi_id);
    }

    /* ═══════════════ impor ═══════════════ */

    /** Berkas kerja tiruan dengan bentuk yang sama seperti aslinya. */
    private function berkas(bool $geser = false): string
    {
        $x  = new Spreadsheet();
        $ws = $x->getActiveSheet()->setTitle('Akumulatif Observasi');

        $judul = ['No.', 'Tanggal', 'Shift', 'CN Unit', 'Operator', 'GL Front', 'Observer', "Kondisi\nMesin",
                  "Mode\nKerja", "Jenis\nMaterial", 'Material', "Metode\nPosisi", "Operating\nCondition",
                  "Metode\nLoading", "Tinggi\nJenjang (m)", "Lebar\nFront (m)", "Spotting\n(s)", "Digging\n(s)",
                  "SWL\n(s)", "Dump\n(s)", "SWE\n(s)", "Aktual CT\n(tanpa Spot)", "Plan CT\n(s)",
                  "Loading Time\n(mm:ss)", 'N Passing', "Bucket\nHeap", "Jam\nObservasi", "Target\nPTY (BCM)",
                  "Aktual\nPTY (BCM)", 'Catatan / Temuan', 'Corrective Action', 'Status CA', 'Verified By', 'Cuaca'];
        if ($geser) {
            // Kolom Operator dipindah ke depan CN Unit.
            [$judul[3], $judul[4]] = [$judul[4], $judul[3]];
        }

        $ws->setCellValue('A2', 'MONTHLY OBSERVASI (AKUMULATIF)');
        $ws->fromArray($judul, null, 'A5');

        $baris = [
            // loading sebagai durasi 89 detik
            [1, '2026-06-04', 1, 'Ex 700', 'Operator Satu', 'GL A', 'Obs A', 'High Perform', 'E (Economy)', 'Easy',
             'Soft Soil', 'Double Bench', 'Average', 'Side Loading', 1.5, 25, 13, 8.5, 6, 2.5, 4, '=R6+S6+T6+U6', 24,
             89 / 86400, 5, '✅', '09.00 – 10.00', 600, 520, 'Exc menggantung; front amblas', 'Himbau hauler', 'Closed', '=F6', 'Cerah'],
            // "1:56" yang tersimpan Excel sebagai 01:56:00, N passing teks, heap ❌
            [2, '2026-06-05', 1, 'Ex 699', 'Operator Dua', 'GL B', 'Obs A', 'Normal', 'P (Power)', 'Severe',
             'OB Non Blasting', 'Bench', 'Good', 'Side Loading', 3, 30, 9, 12, 6.4, 4, 5.3, '=R7+S7+T7+U7', 31,
             (1 * 3600 + 56 * 60) / 86400, '6', '❌', '10.00 – 11.00', 600, 416, 'front undulating', '', 'Open', '', 'Berawan'],
            // baris dengan jenis material salah ketik → ditolak
            [3, '2026-06-06', 1, 'Ex 701', 'Operator Tiga', '', '', '', '', 'Keras',
             '', '', '', '', null, null, 9, 10, 6, 3, 4, '', '', null, null, '', '', null, null, '', '', '', '', ''],
        ];

        foreach ($baris as $i => $b) {
            $b[1] = TanggalExcel::PHPToExcel(new \DateTime($b[1]));
            if ($geser) [$b[3], $b[4]] = [$b[4], $b[3]];
            $ws->fromArray($b, null, 'A'.(6 + $i), true);
        }
        $ws->getCell('Y7')->setValueExplicit('6', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $ws->getStyle('X6:X8')->getNumberFormat()->setFormatCode('[h]:mm:ss');

        $log = $x->createSheet()->setTitle('Coaching Log');
        $log->fromArray(['No.', "Tanggal\nCoaching", 'Nama Operator', 'CN Unit', 'Materi Coaching', "Respons\nOperator",
                         "PIC\n(Coach)", "Follow Up\n/ Action", "Target\nSelesai", "Status\nFollow Up"], null, 'A4');
        $log->fromArray([1, '05/06/26', 'Operator Dua', 'Ex 699', 'Teknik digging', 'Paham', 'Obs A', 'Monitor', '=B5', 'Closed'], null, 'A5');

        $jalur = tempnam(sys_get_temp_dir(), 'frop').'.xlsx';
        (new Xlsx($x))->save($jalur);

        return $jalur;
    }

    public function test_impor_berkas_kerja(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $f = new UploadedFile($this->berkas(), 'observasi.xlsx', null, null, true);

        $this->post(route('frop.impor.kirim'), ['berkas' => $f])->assertRedirect(route('frop.impor'));

        $h = session('hasilImpor');
        $this->assertSame(2, $h['observasi']);
        $this->assertSame(1, $h['coaching']);
        $this->assertCount(1, $h['ditolak']);
        $this->assertStringContainsString('Operator Tiga', $h['ditolak'][0]);

        $satu = Observasi::where('operator', 'Operator Satu')->firstOrFail();
        $dua  = Observasi::where('operator', 'Operator Dua')->firstOrFail();

        $this->assertSame($c->id, $satu->company_id);
        $this->assertSame('2026-06-04', $satu->tanggal->toDateString());
        $this->assertSame(89, $satu->loading_detik);
        $this->assertSame(116, $dua->loading_detik);              // 1:56, bukan 1 jam 56 menit
        $this->assertSame('6', $dua->n_passing);
        $this->assertTrue($satu->bucket_heap);
        $this->assertFalse($dua->bucket_heap);
        $this->assertSame(24.0, $satu->plan_ct);
        $this->assertSame(31.0, $dua->plan_ct);
        $this->assertSame('GL A', $satu->verified_by);             // hasil rumus =F6
        $this->assertSame('GL B', $dua->verified_by);              // kosong → GL Front
        $this->assertSame('Closed', $satu->status_ca);

        $co = Coaching::firstOrFail();
        $this->assertSame('2026-06-05', $co->tanggal->toDateString());
        $this->assertSame($dua->id, $co->observasi_id);
    }

    public function test_impor_ulang_tidak_menggandakan(): void
    {
        $this->masuk($this->perusahaan());
        $jalur = $this->berkas();

        $this->post(route('frop.impor.kirim'), ['berkas' => new UploadedFile($jalur, 'a.xlsx', null, null, true)]);
        $this->post(route('frop.impor.kirim'), ['berkas' => new UploadedFile($jalur, 'a.xlsx', null, null, true)]);

        $this->assertSame(3, session('hasilImpor')['dilewati']);
        $this->assertSame(2, Observasi::count());
        $this->assertSame(1, Coaching::count());
    }

    public function test_impor_membaca_kolom_menurut_judul(): void
    {
        $this->masuk($this->perusahaan());

        $this->post(route('frop.impor.kirim'), ['berkas' => new UploadedFile($this->berkas(true), 'b.xlsx', null, null, true)]);

        $this->assertSame('Ex 700', Observasi::where('operator', 'Operator Satu')->value('unit'));
    }

    public function test_impor_menolak_berkas_bukan_xlsx(): void
    {
        $this->masuk($this->perusahaan());

        $this->post(route('frop.impor.kirim'), ['berkas' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('berkas');
    }
}
