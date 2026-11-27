<?php

namespace Tests\Feature;

use App\Models\SmkpAudit;
use App\Support\{Smkp, SmkpKesesuaian};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/**
 * Formulir Kesesuaian otomatis dari penilaian dan OFI dari kesesuaian
 * (port DBEST #23).
 */
class SmkpKesesuaianTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanGrup();
    }

    private function butir(string $kode): array
    {
        return collect(Smkp::butir())->firstWhere('kode', $kode);
    }

    /** I.1 penuh, I.2 separuh, II.2.1 penuh, III.2.1 penuh, III.5 N/A. */
    private function audit(array $tambah = []): SmkpAudit
    {
        $hasil = [
            'I.1'     => ['v' => $this->butir('I.1')['maks'], 'ket' => 'Kebijakan ditandatangani direktur.', 'bukti' => 'Dok. KEB-K3-01'],
            'I.2'     => ['v' => intdiv((int) $this->butir('I.2')['maks'], 2) ?: 1],
            'II.2.1'  => ['v' => $this->butir('II.2.1')['maks']],
            'III.2.1' => ['v' => $this->butir('III.2.1')['maks']],
            'III.5'   => ['v' => 'N/A'],
        ];

        return SmkpAudit::withoutGlobalScopes()->create([
            'company_id' => $this->induk->id, 'tahun' => 2026, 'status' => 'berjalan', 'tahap' => 2,
            'ketua_auditor' => 'Ketua Uji', 'hasil' => $hasil,
        ] + $tambah)->load('company');
    }

    public function test_hanya_butir_bernilai_penuh_yang_menjadi_uraian(): void
    {
        $a = $this->audit();
        $kode = array_column(SmkpKesesuaian::otomatis($a), 'kode');

        $this->assertSame(['I.1', 'II.2.1', 'III.2.1'], $kode, 'Butir separuh dan N/A bukan kesesuaian; urut Lampiran II.');

        $i1 = SmkpKesesuaian::otomatis($a)[0];
        $this->assertSame(['I', 'I.1', ''], [$i1['elemen'], $i1['sub'], $i1['subsub']]);
        $this->assertSame('Kebijakan ditandatangani direktur.', $i1['catatan']);
        $this->assertSame('Dok. KEB-K3-01', $i1['bukti']);
        $this->assertFalse($i1['diubah']);

        $ii = SmkpKesesuaian::otomatis($a)[1];
        $this->assertSame(['II', 'II.2', 'II.2.1'], [$ii['elemen'], $ii['sub'], $ii['subsub']]);
    }

    /** Subjek kalimat kriteria tertinggi diganti nama perusahaan. */
    public function test_uraian_bawaan_atas_nama_perusahaan(): void
    {
        $pt = $this->induk->name;

        $this->assertStringStartsWith("{$pt} telah ", SmkpKesesuaian::bawaan($this->butir('I.1'), $pt));
        // "Terdapat bukti yang menunjukkan telah terdapat KTT …" → "PT X telah memiliki KTT …".
        $this->assertStringStartsWith("{$pt} telah memiliki KTT", SmkpKesesuaian::bawaan($this->butir('III.2.1'), $pt));
        // "Terdapat bukti yang menunjukkan: a) …" → "PT X telah memenuhi kondisi berikut:\na) …".
        $this->assertStringStartsWith("{$pt} telah memenuhi kondisi berikut:\n", SmkpKesesuaian::bawaan($this->butir('III.5'), $pt));

        // "$1" dan "\" pada nama perusahaan bukan rujukan kelompok.
        $this->assertStringStartsWith('PT $1 \\ Jaya telah memiliki KTT', SmkpKesesuaian::bawaan($this->butir('III.2.1'), 'PT $1 \\ Jaya'));
        $this->assertStringStartsWith('Perusahaan telah ', SmkpKesesuaian::bawaan($this->butir('I.1'), '  '));
    }

    public function test_daftar_dan_ofi_mengikuti_suntingan(): void
    {
        $a = $this->audit(['kesesuaian' => [
            ['kode' => 'I.1', 'otomatis' => true, 'keterangan' => 'Kebijakan K3 disahkan direksi dan dikomunikasikan.', 'keluar' => false, 'ofi' => true, 'justifikasi' => 'Sosialisasi ke kontraktor dapat diperluas.'],
            ['kode' => 'II.2.1', 'otomatis' => true, 'keterangan' => '', 'keluar' => true, 'ofi' => false, 'justifikasi' => ''],
            ['kode' => 'III.2.1', 'otomatis' => true, 'keterangan' => '', 'keluar' => false, 'ofi' => true, 'justifikasi' => ''],
            // Uraian tambahan diisi manual.
            ['elemen' => 'VII', 'sub' => 'VII.1', 'subsub' => '', 'keterangan' => 'Tinjauan manajemen per semester.'],
            ['elemen' => 'I', 'sub' => 'I.2', 'subsub' => '', 'keterangan' => 'Tujuan K3 terukur.'],
        ], 'rekomendasi' => [
            ['elemen' => 'V', 'sub' => 'V.1', 'subsub' => '', 'uraian' => 'Digitalisasi izin kerja.'],
        ]]);

        $d = SmkpKesesuaian::daftar($a);
        $this->assertSame(['I.1', 'I.2', 'III.2.1', 'VII.1'], array_map(fn ($r) => $r['subsub'] ?: $r['sub'], $d), 'II.2.1 dikeluarkan; urut Lampiran II.');
        $this->assertSame('Kebijakan K3 disahkan direksi dan dikomunikasikan.', $d[0]['keterangan']);
        $this->assertSame('I.1', $d[0]['kode']);
        $this->assertNull($d[1]['kode'], 'Uraian tambahan tidak berkode butir.');

        $o = SmkpKesesuaian::ofi($a);
        $this->assertSame(['I.1', 'V.1'], array_column($o, 'sub'), 'OFI III.2.1 tanpa justifikasi tidak tercetak.');
        $this->assertSame(['kesesuaian', 'manual'], array_column($o, 'dari'));
        $this->assertSame('Sosialisasi ke kontraktor dapat diperluas.', $o[0]['uraian']);
    }

    public function test_uraian_tambahan_berkode_sama_menggantikan_uraian_otomatis(): void
    {
        $a = $this->audit(['kesesuaian' => [
            ['elemen' => 'II', 'sub' => 'II.2', 'subsub' => 'II.2.1', 'keterangan' => 'Uraian manual IBPR.'],
        ]]);

        $ii = collect(SmkpKesesuaian::otomatis($a))->firstWhere('kode', 'II.2.1');
        $this->assertTrue($ii['manual']);

        $d = collect(SmkpKesesuaian::daftar($a))->where('subsub', 'II.2.1')->values();
        $this->assertCount(1, $d);
        $this->assertSame('Uraian manual IBPR.', $d[0]['keterangan']);
    }

    public function test_simpan_formulir_menyimpan_suntingan_seperlunya(): void
    {
        $a = $this->audit();
        $bawaan = collect(SmkpKesesuaian::otomatis($a))->keyBy('kode');

        $this->actingAs($this->auditor($this->induk))->post("/smkp/{$a->id}/formulir", [
            'otomatis' => [
                ['kode' => 'I.1', 'masuk' => true, 'keterangan' => $bawaan['I.1']['bawaan'], 'ofi' => false, 'justifikasi' => ''],   // tidak berubah → tidak disimpan
                ['kode' => 'II.2.1', 'masuk' => false, 'keterangan' => $bawaan['II.2.1']['bawaan'], 'ofi' => false, 'justifikasi' => ''],
                ['kode' => 'III.2.1', 'masuk' => true, 'keterangan' => "KTT disahkan KaIT.\r\nSK terbaru 2026.", 'ofi' => true, 'justifikasi' => ''],
                ['kode' => 'IV.1.1', 'masuk' => false, 'keterangan' => 'Bukan kesesuaian — diabaikan.'],
            ],
            'kesesuaian'  => [['elemen' => 'VII', 'sub' => 'VII.1', 'keterangan' => 'Tinjauan manajemen per semester.']],
            'rekomendasi' => [],
        ])->assertRedirect()->assertSessionHas('ok', fn ($m) => str_contains($m, 'OFI III.2.1 belum berisi justifikasi'));

        $k = $a->fresh()->kesesuaian;
        $this->assertSame([
            ['kode' => 'II.2.1', 'otomatis' => true, 'keterangan' => '', 'keluar' => true, 'ofi' => false, 'justifikasi' => ''],
            ['kode' => 'III.2.1', 'otomatis' => true, 'keterangan' => "KTT disahkan KaIT.\nSK terbaru 2026.", 'keluar' => false, 'ofi' => true, 'justifikasi' => ''],
            ['elemen' => 'VII', 'sub' => 'VII.1', 'subsub' => '', 'keterangan' => 'Tinjauan manajemen per semester.'],
        ], $k);

        // Suntingan butir yang tidak dikirim tetap dipertahankan; membatalkan suntingan menghapusnya.
        $this->post("/smkp/{$a->id}/formulir", [
            'otomatis' => [['kode' => 'III.2.1', 'masuk' => true, 'keterangan' => '', 'ofi' => false, 'justifikasi' => '']],
            'kesesuaian' => [], 'rekomendasi' => [],
        ])->assertSessionHas('ok', 'Formulir kesesuaian dan rekomendasi tersimpan.');
        $this->assertSame([['kode' => 'II.2.1', 'otomatis' => true, 'keterangan' => '', 'keluar' => true, 'ofi' => false, 'justifikasi' => '']], $a->fresh()->kesesuaian);

        // Isian tambahan tidak dapat menyusupkan penanda otomatis.
        $this->post("/smkp/{$a->id}/formulir", [
            'kesesuaian' => [['elemen' => 'I', 'sub' => 'I.1', 'keterangan' => 'Manual', 'otomatis' => true, 'kode' => 'I.1']],
        ]);
        $this->assertSame(['elemen' => 'I', 'sub' => 'I.1', 'subsub' => '', 'keterangan' => 'Manual'], $a->fresh()->kesesuaian[1]);
    }

    public function test_halaman_cetak_dan_laporan_memakai_daftar_otomatis(): void
    {
        $a = $this->audit(['kesesuaian' => [
            ['kode' => 'I.1', 'otomatis' => true, 'keterangan' => '', 'keluar' => false, 'ofi' => true, 'justifikasi' => 'Sosialisasi dapat diperluas.'],
        ]]);
        $u = $this->auditor($this->induk);

        $this->actingAs($u)->get("/smkp/{$a->id}/formulir")->assertInertia(fn (Inertia $p) => $p->component('Smkp/Formulir')
            ->has('otomatis', 3)
            ->where('otomatis.0.kode', 'I.1')->where('otomatis.0.ofi', true)
            ->where('ringkas.kesesuaian', 3)->where('ringkas.kesesuaianNilai', 3)->where('ringkas.rekomendasi', 1)
            ->where('namaPerusahaan', $this->induk->name));

        $this->get("/smkp/{$a->id}/formulir/kesesuaian/cetak")->assertInertia(fn (Inertia $p) => $p
            ->has('baris', 3)
            ->where('baris.0.sub', 'I.1')->where('baris.0.isi', fn ($t) => str_starts_with($t, $this->induk->name.' telah '))
            ->where('baris.2.subsub', 'III.2.1'));

        $this->get("/smkp/{$a->id}/formulir/rekomendasi/cetak")->assertInertia(fn (Inertia $p) => $p
            ->has('baris', 1)->where('baris.0.isi', 'Sosialisasi dapat diperluas.'));

        $this->get("/smkp/{$a->id}/laporan")->assertInertia(fn (Inertia $p) => $p
            ->has('praktik', 3)->where('praktik.0.kriteria', fn ($t) => str_starts_with($t, 'Sub Elemen I.1 – '))
            ->where('praktik.1.kriteria', fn ($t) => str_starts_with($t, 'Sub Sub Elemen II.2.1 – ')));
    }

    public function test_isian_otomatis_divalidasi(): void
    {
        $a = $this->audit();
        $this->actingAs($this->auditor($this->induk))->post("/smkp/{$a->id}/formulir", [
            'otomatis' => [['masuk' => 'ya', 'keterangan' => str_repeat('x', 3001)]],
        ])->assertSessionHasErrors(['otomatis.0.kode', 'otomatis.0.masuk', 'otomatis.0.keterangan']);
    }
}
