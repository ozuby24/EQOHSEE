<?php

namespace Tests\Feature;

use App\Models\{SmkpAudit, SmkpFinding};
use App\Support\{Smkp, SmkpTahap};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/** Alur audit SMKP dari pembuatan periode sampai laporan. */
class SmkpAlurTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanGrup();
    }

    private function buat(): SmkpAudit
    {
        $this->actingAs($this->auditor($this->induk))->post('/smkp', [
            'company_id' => $this->pjp->id, 'tahun' => 2026, 'status' => 'berjalan', 'ketua_auditor' => 'Ketua Uji',
        ])->assertRedirect();

        return SmkpAudit::withoutGlobalScopes()->firstOrFail();
    }

    private function tuntaskanTahapSatu(SmkpAudit $a): void
    {
        $kecukupan = [];
        foreach (Smkp::elemen() as $e) $kecukupan[$e['kode']] = ['status' => SmkpTahap::LENGKAP, 'ket' => ''];
        $this->post("/smkp/{$a->id}/tahap-1", ['kecukupan' => $kecukupan])->assertSessionHasNoErrors();

        $this->post("/smkp/{$a->id}/rencana", [
            'tujuan' => 'Menilai penerapan SMKP', 'kriteria' => 'Kepdirjen 185.K/37.04/DJB/2019', 'ruang_lingkup' => 'Seluruh kegiatan tambang',
            'tanggal_mulai' => '2026-10-20', 'tanggal_selesai' => '2026-10-22',
            'susunan' => [['tanggal' => '2026-10-20', 'waktu' => '08.00', 'kegiatan' => 'Rapat pembukaan', 'auditi' => 'KTT', 'auditor' => 'Tim']],
            'tugas' => [['nama' => 'Ketua Uji', 'peran' => 'Ketua', 'registrasi' => 'REG-1', 'lingkup' => 'Elemen I–VII']],
            'sumberdaya' => 'Kendaraan, APD', 'metode' => 'Wawancara, observasi, tinjauan dokumen',
            // Auditi perusahaan jasa: PJO turut mengesahkan.
            'pengesahan' => ['ktt' => ['nama' => 'KTT Uji'], 'pjo' => ['nama' => 'PJO Uji'], 'ketua' => ['nama' => 'Ketua Uji']],
        ])->assertSessionHasNoErrors();
    }

    public function test_satu_periode_per_perusahaan_per_tahun(): void
    {
        $this->buat();
        $this->post('/smkp', ['company_id' => $this->pjp->id, 'tahun' => 2026, 'status' => 'draft'])->assertSessionHasErrors('tahun');
        $this->post('/smkp', ['company_id' => $this->pjp->id, 'tahun' => 2027, 'status' => 'draft'])->assertRedirect();
    }

    public function test_audit_lapangan_tertahan_sampai_tahap_satu_tuntas(): void
    {
        $a = $this->buat();
        $this->assertSame(SmkpTahap::PERMULAAN, (int) $a->tahap);

        $this->post("/smkp/{$a->id}/tahap", ['tahap' => SmkpTahap::LAPANGAN])->assertSessionHasErrors('tahap');

        // Peserta rapat pembukaan boleh dicatat, tetapi tidak melompati Tahap I.
        $this->post("/smkp/{$a->id}/rapat", ['rapat' => 'pembukaan', 'nama' => 'Peserta Awal'])->assertSessionHasNoErrors();
        $this->assertSame(SmkpTahap::PERMULAAN, (int) $a->fresh()->tahap);

        $this->tuntaskanTahapSatu($a);
        $this->assertTrue($a->fresh()->siapTahapDua());

        $this->post("/smkp/{$a->id}/tahap", ['tahap' => SmkpTahap::LAPANGAN])->assertSessionHasNoErrors();
        $this->assertSame(SmkpTahap::LAPANGAN, (int) $a->fresh()->tahap);

        $this->post("/smkp/{$a->id}/tahap", ['tahap' => SmkpTahap::PELAPORAN])->assertSessionHasNoErrors();
        $this->assertSame('selesai', $a->fresh()->status);
    }

    public function test_penilaian_dijepit_ke_rentang_dan_na_diterima(): void
    {
        $a = $this->buat();
        $this->post("/smkp/{$a->id}/elemen/I", ['k' => [
            'I.1' => ['v' => 999, 'bukti' => 'Kebijakan K3 rev.2'],
            'I.2' => ['v' => -3],
            'I.3' => ['v' => 'N/A'],
            'I.4' => ['v' => 'sembarang'],
            'X.9' => ['v' => 4],
        ]])->assertSessionHasNoErrors();

        $h = $a->fresh()->hasil;
        $this->assertSame(4, $h['I.1']['v']);
        $this->assertSame('Kebijakan K3 rev.2', $h['I.1']['bukti']);
        $this->assertSame(0, $h['I.2']['v']);
        $this->assertSame(Smkp::NA, $h['I.3']['v']);
        $this->assertArrayNotHasKey('I.4', $h);
        $this->assertArrayNotHasKey('X.9', $h);

        // Mengosongkan nilai mengembalikan butir ke "belum dinilai".
        $this->post("/smkp/{$a->id}/elemen/I", ['k' => ['I.1' => ['v' => '']]]);
        $this->assertArrayNotHasKey('I.1', $a->fresh()->hasil);
    }

    public function test_elemen_tak_dikenal_404(): void
    {
        $a = $this->buat();
        $this->get("/smkp/{$a->id}/elemen/IX")->assertNotFound();
    }

    public function test_temuan_diangkat_sekali_dan_tampil_di_laporan(): void
    {
        $a = $this->buat();
        $this->post("/smkp/{$a->id}/elemen/I", ['k' => ['I.1' => ['v' => 0], 'I.2' => ['v' => 2], 'I.3' => ['v' => 3]]]);

        $this->post("/smkp/{$a->id}/temuan/angkat")->assertSessionHas('ok');
        $this->assertSame(2, SmkpFinding::where('audit_id', $a->id)->count());

        $this->post("/smkp/{$a->id}/temuan/angkat");
        $this->assertSame(2, SmkpFinding::where('audit_id', $a->id)->count(), 'Temuan yang sudah diangkat tidak berlipat.');

        $this->get("/smkp/{$a->id}/laporan")->assertOk()->assertInertia(fn (Inertia $p) => $p
            ->component('Print/Smkp/LaporanAudit')
            ->has('temuan', 2)
            ->where('temuan.0.jenis', 'mayor')
            ->where('hitung', ['kritikal' => 0, 'mayor' => 1, 'minor' => 1])
            ->has('elemen', 7)
            ->where('dok.nomor', 'LAP/JU/OHSE/001'));
    }

    public function test_temuan_audit_lain_tidak_dapat_diubah_lewat_audit_ini(): void
    {
        $a = $this->buat();
        $b = SmkpAudit::withoutGlobalScopes()->create(['company_id' => $this->induk->id, 'tahun' => 2026, 'status' => 'berjalan', 'tahap' => 1, 'hasil' => []]);
        $t = $b->findings()->create(['kode_kriteria' => 'I.1', 'jenis' => 'mayor', 'uraian' => 'Uji', 'status' => 'Open']);

        $this->put("/smkp/{$a->id}/temuan/{$t->id}", ['status' => 'Closed', 'uraian' => 'diubah'])->assertNotFound();
        $this->delete("/smkp/{$a->id}/temuan/{$t->id}")->assertNotFound();
        $this->assertDatabaseHas('smkp_findings', ['id' => $t->id, 'status' => 'Open']);
    }

    public function test_dokumen_cetak_memakai_kop_terkendali(): void
    {
        $a = $this->buat();
        foreach ([
            "/smkp/{$a->id}/berita-acara" => 'FRM/JU/OHSE/001',
            "/smkp/{$a->id}/rencana/cetak" => 'FRM/JU/OHSE/002',
            "/smkp/{$a->id}/daftar-hadir/penutupan" => 'FRM/JU/OHSE/003',
        ] as $url => $nomor) {
            // Kop bawaan EQOHSEE (KopDokumen::DIVISI / DEPARTEMEN), nomor Support\Nomor.
            $this->get($url)->assertOk()->assertInertia(fn (Inertia $p) => $p->where('dok.nomor', $nomor)->where('dok.perusahaan', 'PT Jasa Uji')
                ->where('dok.divisi', \App\Support\KopDokumen::DIVISI)->where('dok.departemen', \App\Support\KopDokumen::DEPARTEMEN));
        }
        $this->get("/smkp/{$a->id}/daftar-hadir/sembarang")->assertNotFound();
    }


}
