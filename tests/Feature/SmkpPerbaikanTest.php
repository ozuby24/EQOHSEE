<?php

namespace Tests\Feature;

use App\Models\{ActivityLog, SmkpAudit, SmkpFinding};
use App\Support\{Smkp, SmkpTahap};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Inertia;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/**
 * Regresi temuan audit SMKP: isian rekan tertimpa, nomor KTS ganda, Mayor
 * palsu dari penilaian sebagian, ambang tingkat, dasbor, tanggal penutupan,
 * status selesai, pengesahan PJO, jejak aktivitas, dan angka berkoma.
 */
class SmkpPerbaikanTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanGrup();
    }

    private function buat(array $isi = []): SmkpAudit
    {
        $this->actingAs($this->auditor($this->induk))->post('/smkp', $isi + [
            'company_id' => $this->induk->id, 'tahun' => 2026, 'status' => 'berjalan', 'ketua_auditor' => 'Ketua Uji',
        ])->assertSessionHasNoErrors();

        return SmkpAudit::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    private function rencana(array $pengesahan): array
    {
        return [
            'tujuan' => 'Menilai penerapan SMKP', 'kriteria' => 'Kepdirjen 185.K/37.04/DJB/2019', 'ruang_lingkup' => 'Seluruh kegiatan tambang',
            'tanggal_mulai' => '2026-10-20', 'tanggal_selesai' => '2026-10-22',
            'susunan' => [['tanggal' => '2026-10-20', 'waktu' => '08.00', 'kegiatan' => 'Rapat pembukaan', 'auditi' => 'KTT', 'auditor' => 'Tim']],
            'tugas' => [['nama' => 'Ketua Uji', 'peran' => 'Ketua', 'registrasi' => 'REG-1', 'lingkup' => 'Elemen I–VII']],
            'sumberdaya' => 'Kendaraan, APD', 'metode' => 'Wawancara, observasi, tinjauan dokumen',
            'pengesahan' => $pengesahan,
        ];
    }

    /* ---------- 0. isian rekan tidak tertimpa ---------- */

    public function test_simpan_elemen_tidak_menimpa_isian_rekan_yang_tersimpan_lebih_dulu(): void
    {
        $a = $this->buat();
        $this->post("/smkp/{$a->id}/elemen/II", ['k' => ['II.1' => ['v' => 3]]])->assertSessionHas('ok');

        // Rekan menyimpan elemen I sesaat setelah permintaan ini memuat audit.
        $sekali = false;
        SmkpAudit::retrieved(function (SmkpAudit $m) use (&$sekali, $a) {
            if ($sekali || $m->id !== $a->id) return;
            $sekali = true;
            $h = json_decode(DB::table('smkp_audits')->where('id', $a->id)->value('hasil'), true);
            $h['I.1'] = ['v' => 4, 'ket' => 'rekan', 'bukti' => ''];
            DB::table('smkp_audits')->where('id', $a->id)->update(['hasil' => json_encode($h)]);
        });

        $this->post("/smkp/{$a->id}/elemen/II", ['k' => ['II.3' => ['v' => 2]]])->assertSessionHas('ok');
        $h = $a->fresh()->hasil;
        $this->assertTrue($sekali);
        $this->assertSame(4, $h['I.1']['v'] ?? null, 'Isian rekan pada elemen lain tidak boleh hilang.');
        $this->assertSame([3, 2], [$h['II.1']['v'], $h['II.3']['v']]);
    }

    public function test_simpan_hanya_menyentuh_butir_yang_dikirim(): void
    {
        $a = $this->buat();
        $this->post("/smkp/{$a->id}/elemen/I", ['k' => ['I.1' => ['v' => 4, 'ket' => 'A']]]);
        $this->post("/smkp/{$a->id}/elemen/I", ['k' => ['I.2' => ['v' => 2]]]);   // halaman rekan yang lama

        $h = $a->fresh()->hasil;
        $this->assertSame([4, 'A', 2], [$h['I.1']['v'], $h['I.1']['ket'], $h['I.2']['v']]);
    }

    /* ---------- 1 & 5. nomor KTS unik, angkat tidak ganda ---------- */

    public function test_nomor_kts_tidak_berulang_setelah_temuan_dihapus(): void
    {
        $a = $this->buat();
        $this->post("/smkp/{$a->id}/elemen/I", ['k' => ['I.1' => ['v' => 1], 'I.2' => ['v' => 2]]]);
        $this->post("/smkp/{$a->id}/temuan/angkat")->assertSessionHas('ok');
        $pertama = SmkpFinding::where('audit_id', $a->id)->orderBy('id')->first();
        $this->delete("/smkp/{$a->id}/temuan/{$pertama->id}")->assertSessionHas('ok');

        $this->post("/smkp/{$a->id}/elemen/I", ['k' => ['I.3' => ['v' => 1]]]);
        $this->post("/smkp/{$a->id}/temuan/angkat")->assertSessionHas('ok');

        $nomor = SmkpFinding::where('audit_id', $a->id)->pluck('nomor')->all();
        $this->assertCount(3, $nomor);
        $this->assertSame($nomor, array_unique($nomor), 'Nomor KTS harus unik: '.implode(', ', $nomor));

        // Nomor yang ditetapkan auditor pun tidak boleh menyamai temuan lain.
        [$x, $y] = SmkpFinding::where('audit_id', $a->id)->orderBy('id')->get()->all();
        $this->put("/smkp/{$a->id}/temuan/{$y->id}", ['nomor' => $x->nomor, 'status' => 'Open'])->assertSessionHasErrors('nomor');

        // Angkat ulang tidak menggandakan kriteria yang sama.
        $this->post("/smkp/{$a->id}/temuan/angkat")->assertSessionHas('ok', 'Tidak ada temuan baru.');
        $this->assertSame(3, SmkpFinding::where('audit_id', $a->id)->count());
    }

    public function test_nomor_temuan_tidak_dapat_dikosongkan(): void
    {
        $a = $this->buat();
        $this->post("/smkp/{$a->id}/elemen/I", ['k' => ['I.1' => ['v' => 1]]]);
        $this->post("/smkp/{$a->id}/temuan/angkat");
        $f = SmkpFinding::where('audit_id', $a->id)->firstOrFail();

        $this->put("/smkp/{$a->id}/temuan/{$f->id}", ['nomor' => '', 'status' => 'Open'])->assertSessionHasNoErrors();
        $this->assertSame('IU-MYR-01-2026', $f->fresh()->nomor);
    }

    public function test_nomor_temuan_berkode_perusahaan_per_kategori_urut_sub_elemen(): void
    {
        $a = $this->buat();
        $this->post("/smkp/{$a->id}/elemen/I", ['k' => ['I.4' => ['v' => 0], 'I.1' => ['v' => 0], 'I.2' => ['v' => 2]]])->assertSessionHasNoErrors();
        $this->post("/smkp/{$a->id}/temuan/angkat")->assertSessionHasNoErrors();
        $nomor = fn () => SmkpFinding::where('audit_id', $a->id)->orderBy('id')->pluck('nomor', 'kode_kriteria')->all();
        $this->assertSame(['I.1' => 'IU-MYR-01-2026', 'I.4' => 'IU-MYR-02-2026', 'I.2' => 'IU-MNR-01-2026'], $nomor(), 'Mayor lalu Minor, urut sub-elemen.');

        // Daftar temuan & formulir: Mayor lalu Minor, urut sub-elemen.
        $this->get("/smkp/{$a->id}/temuan")->assertInertia(fn (Inertia $p) => $p
            ->where('temuan', fn ($t) => collect($t)->pluck('kode')->all() === ['I.1', 'I.4', 'I.2']));

        // Nomor yang diketik manual disusun ulang sesuai urutan.
        $i1 = SmkpFinding::where('audit_id', $a->id)->where('kode_kriteria', 'I.1')->first();
        $this->put("/smkp/{$a->id}/temuan/{$i1->id}", ['nomor' => 'IU-MYR-02-2026x', 'status' => 'Open'])->assertSessionHasNoErrors();
        $this->post("/smkp/{$a->id}/temuan/nomor-ulang")->assertSessionHasNoErrors();
        $this->assertSame(['I.1' => 'IU-MYR-01-2026', 'I.4' => 'IU-MYR-02-2026', 'I.2' => 'IU-MNR-01-2026'], $nomor());

        // Perusahaan jasa pertambangan pun memakai kodenya sendiri.
        $this->pjp->update(['code' => 'NJA']);
        $b = $this->buat(['company_id' => $this->pjp->id]);
        $this->post("/smkp/{$b->id}/elemen/I", ['k' => ['I.1' => ['v' => 0]]])->assertSessionHasNoErrors();
        $this->post("/smkp/{$b->id}/temuan/angkat")->assertSessionHasNoErrors();
        $this->assertSame(['NJA-MYR-01-2026'], SmkpFinding::where('audit_id', $b->id)->pluck('nomor')->all());

        // Auditi tidak dapat menyusun ulang nomor.
        $this->actingAs($this->auditi($this->induk))->post("/smkp/{$a->id}/temuan/nomor-ulang")->assertForbidden();
    }

    public function test_divisi_dan_departemen_temuan_tampil_kembali_di_halaman_temuan(): void
    {
        $a = $this->buat();
        $this->post("/smkp/{$a->id}/elemen/I", ['k' => ['I.1' => ['v' => 1]]]);
        $this->post("/smkp/{$a->id}/temuan/angkat");
        $f = SmkpFinding::where('audit_id', $a->id)->firstOrFail();

        $this->put("/smkp/{$a->id}/temuan/{$f->id}", ['status' => 'Open', 'divisi' => 'Divisi Tambang Utara', 'departemen' => 'Produksi'])->assertSessionHasNoErrors();
        $this->get("/smkp/{$a->id}/temuan")->assertInertia(fn (Inertia $p) => $p
            ->where('temuan.0.divisi', 'Divisi Tambang Utara')
            ->where('temuan.0.departemen', 'Produksi'));
    }

    public function test_tanggal_mulai_harus_di_sekitar_tahun_periode(): void
    {
        $u = $this->auditor($this->induk);
        $this->actingAs($u)->post('/smkp', ['company_id' => $this->induk->id, 'tahun' => 2033, 'status' => 'draft', 'tanggal_mulai' => '2026-05-10'])
            ->assertSessionHasErrors('tanggal_mulai');
        $this->actingAs($u)->post('/smkp', ['company_id' => $this->induk->id, 'tahun' => 2026, 'status' => 'draft', 'tanggal_mulai' => '2027-01-12'])
            ->assertSessionHasNoErrors();
    }

    /* ---------- 2. penilaian sebagian bukan Mayor ---------- */

    public function test_sub_elemen_yang_baru_sebagian_dinilai_belum_menjadi_temuan(): void
    {
        // II.2 berisi lima butir; dua dinilai penuh, tiga belum.
        $h = ['II.2.1' => ['v' => 4], 'II.2.2' => ['v' => 3]];
        $this->assertNotContains('II.2', array_column(Smkp::temuan($h), 'kode'), 'Butir yang belum dinilai bukan nol capaian temuan.');
        $this->assertSame(['mayor' => 0, 'minor' => 0], Smkp::hitungTemuan($h));

        $this->assertSame([], array_values(array_filter(array_column(Smkp::temuan($h), 'kode'), fn ($k) => str_starts_with($k, 'II.2'))),
            'Rincian pun menunggu: Mayor atau Minor ditentukan capaian sub-elemen.');

        // Tuntas dinilai, sub-elemen Minor: rincian yang belum penuh menjadi temuan Minor.
        $h += ['II.2.3' => ['v' => 2], 'II.2.4' => ['v' => 3], 'II.2.5' => ['v' => 0]];
        $t = array_filter(Smkp::temuan($h), fn ($x) => str_starts_with($x['kode'], 'II.2'));
        $this->assertContains('II.2.5', array_column($t, 'kode'));
        $this->assertSame(['minor'], array_values(array_unique(array_column($t, 'jenis'))));
    }

    public function test_laporan_dan_formulir_menutup_uraian_dengan_referensi_kriteria(): void
    {
        $a = $this->buat();
        foreach ([['I.4', 'mayor', 'Komunikasi Kebijakan'], ['IV.3.4', 'minor', 'Program fatigue belum ada'], ['I.1', 'mayor', 'Kebijakan belum ditinjau']] as [$k, $j, $u]) {
            $a->findings()->create(['kode_kriteria' => $k, 'jenis' => $j, 'uraian' => $u, 'status' => 'Open']);
        }
        $rincian = 'tidak sesuai dengan Sub Elemen IV.3 Pelaksanaan Pengelolaan Kesehatan Kerja, Sub-sub Elemen IV.3.4 Pengelolaan Kelelahan Kerja (Fatigue).';

        // Laporan: urut kriteria acuan; uraian ditutup "Hal ini tidak sesuai dengan …" tanpa label
        // "Referensi"; uraian yang hanya mengulang nama kriteria tidak diulang.
        $this->get("/smkp/{$a->id}/laporan")->assertInertia(fn (Inertia $p) => $p
            ->where('temuan', fn ($t) => collect($t)->pluck('kode')->all() === ['I.1', 'I.4', 'IV.3.4'])
            ->where('temuan.1.uraian', 'Tidak sesuai dengan Sub Elemen I.4 Komunikasi Kebijakan.')
            ->where('temuan.2.uraian', 'Program fatigue belum ada. Hal ini '.$rincian));

        $cari = fn ($t, $k) => collect($t)->firstWhere('kode', $k);
        $this->get("/smkp/{$a->id}/formulir/ketidaksesuaian/cetak")->assertInertia(fn (Inertia $p) => $p
            ->where('temuan', fn ($t) => $cari($t, 'IV.3.4')['sub'] === 'IV.3 — Pelaksanaan Pengelolaan Kesehatan Kerja'
                && $cari($t, 'IV.3.4')['subsub'] === 'IV.3.4 — Pengelolaan Kelelahan Kerja (Fatigue)' && $cari($t, 'I.4')['subsub'] === ''));
        $this->get("/smkp/{$a->id}/formulir/rekapitulasi/cetak")->assertInertia(fn (Inertia $p) => $p
            ->where('temuan', fn ($t) => $cari($t, 'I.4')['pernyataan'] === 'Tidak sesuai dengan Sub Elemen I.4 Komunikasi Kebijakan.'
                && $cari($t, 'I.1')['pernyataan'] === 'Kebijakan belum ditinjau. Hal ini tidak sesuai dengan Sub Elemen I.1 Penyusunan Kebijakan.'));

        // Ringkasan: ketidaksesuaian yang belum diangkat terhitung.
        $this->post("/smkp/{$a->id}/elemen/I", ['k' => ['I.2' => ['v' => 0]]])->assertSessionHasNoErrors();
        $this->get("/smkp/{$a->id}")->assertInertia(fn (Inertia $p) => $p->where('hitung.belum', 1)->where('hitung.diangkat', 3));
    }

    public function test_temuan_rincian_yang_terlanjur_mayor_dikoreksi_saat_dinilai_ulang(): void
    {
        $a = $this->buat();
        $nilai = ['III.12.1' => 4, 'III.12.2' => 0, 'III.12.3' => 3, 'III.12.4' => 3, 'III.12.5' => 3];
        $k = array_map(fn ($v) => ['v' => $v], $nilai);
        // CAR lama dari aturan sebelumnya: rincian dicatat Mayor.
        $car = $a->findings()->create(['kode_kriteria' => 'III.12.2', 'jenis' => 'mayor', 'uraian' => 'Uji', 'status' => 'Open']);

        $this->post("/smkp/{$a->id}/elemen/III", ['k' => $k])->assertSessionHasNoErrors();
        $this->assertSame('minor', $car->fresh()->jenis, 'Rincian hanya mengenal Minor.');

        // Seluruh rincian 0: sub-elemen Mayor → satu usulan Mayor berkode III.12.
        $this->post("/smkp/{$a->id}/elemen/III", ['k' => array_map(fn () => ['v' => 0], $nilai)])->assertSessionHasNoErrors();
        $this->get("/smkp/{$a->id}/temuan")->assertInertia(fn (Inertia $p) => $p
            ->where('usulan', fn ($u) => collect($u)->where('kode', 'III.12')->pluck('jenis')->all() === ['mayor']
                && collect($u)->filter(fn ($x) => str_starts_with($x['kode'], 'III.12.'))->isEmpty()));
    }

    public function test_jenis_temuan_mengikuti_nilai_terbaru(): void
    {
        $a = $this->buat();
        $this->post("/smkp/{$a->id}/elemen/I", ['k' => ['I.1' => ['v' => 1], 'I.2' => ['v' => 1], 'I.4' => ['v' => 1]]]);
        $this->post("/smkp/{$a->id}/temuan/angkat");
        $f = fn (string $kode) => SmkpFinding::where('audit_id', $a->id)->where('kode_kriteria', $kode)->first();
        $this->assertSame('mayor', $f('I.1')->jenis);

        // Kategori yang ditetapkan auditor (Kritikal) dan temuan yang sudah ditutup tidak diubah.
        $this->put("/smkp/{$a->id}/temuan/{$f('I.2')->id}", ['jenis' => 'kritikal', 'status' => 'Open'])->assertSessionHasNoErrors();
        $this->put("/smkp/{$a->id}/temuan/{$f('I.4')->id}", ['status' => 'Closed', 'tanggal_selesai' => now()->toDateString()])->assertSessionHasNoErrors();

        $this->post("/smkp/{$a->id}/elemen/I", ['k' => ['I.1' => ['v' => 3], 'I.2' => ['v' => 3], 'I.4' => ['v' => 3]]]);
        $this->assertSame(['minor', 'kritikal', 'mayor'], [$f('I.1')->jenis, $f('I.2')->jenis, $f('I.4')->jenis]);

        // Laporan menghitung dari temuan yang benar-benar diangkat.
        $this->get("/smkp/{$a->id}/laporan")->assertInertia(fn (Inertia $p) => $p
            ->where('hitung.kritikal', 1)->where('hitung.mayor', 1)->where('hitung.minor', 1));
    }

    /* ---------- 3. ambang tingkat mengikuti angka yang tampil ---------- */

    public function test_tingkat_mengikuti_nilai_yang_ditampilkan(): void
    {
        $h = [];
        foreach (Smkp::butir() as $b) $h[$b['kode']] = ['v' => $b['maks']];
        $kurang = ['I.1' => 2, 'II.1' => 3, 'II.2.2' => 1, 'II.3' => 1, 'II.4' => 1, 'III.2.3' => 1, 'III.7' => 1, 'III.8' => 1, 'III.9' => 1,
            'III.12.1' => 2, 'III.12.4' => 0, 'IV.2.4' => 1, 'IV.2.9' => 3, 'IV.2.10' => 3, 'IV.3.2' => 1, 'IV.3.4' => 1, 'IV.4.5' => 3,
            'IV.8.1' => 1, 'IV.8.3' => 0, 'V.1.4' => 2, 'V.2' => 3, 'V.3' => 2, 'V.5.2' => 2, 'V.5.5' => 2, 'VII.4' => 1, 'VII.5' => 0];
        foreach ($kurang as $k => $v) $h[$k] = ['v' => $v];

        $r = Smkp::rekap($h);
        $this->assertSame(85.0, $r['skor'], 'Nilai mentah 84,9967 tampil 85,00.');
        $this->assertSame('Baik', $r['tingkat']['label'], 'Tingkat harus sesuai angka yang tampil.');
    }

    public function test_audit_yang_belum_dinilai_tidak_berlabel_perlu_perhatian_serius(): void
    {
        $this->assertSame('Belum dinilai', Smkp::rekap([])['tingkat']['label']);
    }

    /* ---------- 4 & 8. dasbor ---------- */

    public function test_tanggal_penutupan_temuan_tidak_di_masa_depan_atau_sebelum_audit(): void
    {
        $a = $this->buat(['tanggal_mulai' => '2026-03-01', 'tanggal_selesai' => '2026-03-05']);
        $this->post("/smkp/{$a->id}/elemen/I", ['k' => ['I.1' => ['v' => 1]]]);
        $this->post("/smkp/{$a->id}/temuan/angkat");
        $f = SmkpFinding::where('audit_id', $a->id)->firstOrFail();

        // Lusa selalu melewati "hari ini" di zona mana pun di Indonesia.
        $this->put("/smkp/{$a->id}/temuan/{$f->id}", ['status' => 'Closed', 'tanggal_selesai' => now()->addDays(2)->toDateString()])->assertSessionHasErrors('tanggal_selesai');
        $this->put("/smkp/{$a->id}/temuan/{$f->id}", ['status' => 'Closed', 'tanggal_selesai' => '2026-02-01'])->assertSessionHasErrors('tanggal_selesai');
        $this->assertSame('Open', $f->fresh()->status);

        $this->put("/smkp/{$a->id}/temuan/{$f->id}", ['status' => 'Closed', 'tanggal_selesai' => '2026-03-20'])->assertSessionHasNoErrors();
        $this->assertSame('2026-03-20', $f->fresh()->tanggal_selesai->toDateString());
    }

    public function test_tanggal_penutupan_mengikuti_hari_ini_waktu_indonesia_timur(): void
    {
        $a = $this->buat(['tanggal_mulai' => '2026-03-01']);
        $this->post("/smkp/{$a->id}/elemen/I", ['k' => ['I.1' => ['v' => 1]]]);
        $this->post("/smkp/{$a->id}/temuan/angkat");
        $f = SmkpFinding::where('audit_id', $a->id)->firstOrFail();

        // 23.30 WIB = 01.30 WIT keesokan harinya: auditor di Papua menutup temuan bertanggal hari itu.
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-19 23:30', 'Asia/Jakarta'));
        $this->put("/smkp/{$a->id}/temuan/{$f->id}", ['status' => 'Closed', 'tanggal_selesai' => '2026-10-20'])->assertSessionHasNoErrors();
        $this->put("/smkp/{$a->id}/temuan/{$f->id}", ['status' => 'Closed', 'tanggal_selesai' => '2026-10-21'])->assertSessionHasErrors('tanggal_selesai');
    }

    public function test_membuka_kembali_temuan_tidak_terhalang_tanggal_lama(): void
    {
        $a = $this->buat(['tanggal_mulai' => '2026-03-01']);
        $this->post("/smkp/{$a->id}/elemen/I", ['k' => ['I.1' => ['v' => 1]]]);
        $this->post("/smkp/{$a->id}/temuan/angkat");
        $f = SmkpFinding::where('audit_id', $a->id)->firstOrFail();
        $f->forceFill(['status' => 'Closed', 'tanggal_selesai' => '2030-01-01'])->save();   // data lama tak sah

        // Formulir mengirim ulang tanggal lama (kolomnya nonaktif) saat status diubah.
        $this->put("/smkp/{$a->id}/temuan/{$f->id}", ['status' => 'In Progress', 'tanggal_selesai' => '2030-01-01'])->assertSessionHasNoErrors();
        $this->assertSame(['In Progress', null], [$f->fresh()->status, $f->fresh()->tanggal_selesai]);
    }

    /* ---------- 7. status selesai hanya lewat tahap Pelaporan ---------- */

    public function test_status_selesai_hanya_lewat_tahap_pelaporan(): void
    {
        $u = $this->auditor($this->induk);
        $this->actingAs($u)->post('/smkp', ['company_id' => $this->induk->id, 'tahun' => 2026, 'status' => 'selesai'])->assertSessionHasErrors('status');

        $a = $this->buat();
        $this->put("/smkp/{$a->id}", ['company_id' => $this->induk->id, 'tahun' => 2026, 'status' => 'selesai'])->assertSessionHasErrors('status');
        $this->assertSame('berjalan', $a->fresh()->status);

        // Keluar dari Pelaporan: status kembali berjalan.
        $a->forceFill(['tahap' => SmkpTahap::PELAPORAN, 'status' => 'selesai'])->save();
        $this->put("/smkp/{$a->id}", ['company_id' => $this->induk->id, 'tahun' => 2026, 'status' => 'selesai'])->assertSessionHasNoErrors();
        $this->post("/smkp/{$a->id}/tahap", ['tahap' => SmkpTahap::PERMULAAN])->assertSessionHasNoErrors();
        $this->assertSame(['berjalan', SmkpTahap::PERMULAAN], [$a->fresh()->status, (int) $a->fresh()->tahap]);
    }

    /* ---------- 9. pengesahan PJO untuk perusahaan jasa ---------- */

    public function test_rencana_audit_perusahaan_jasa_wajib_disahkan_pjo(): void
    {
        $a = $this->buat(['company_id' => $this->pjp->id]);
        $this->post("/smkp/{$a->id}/rencana", $this->rencana(['ktt' => ['nama' => 'KTT Uji'], 'ketua' => ['nama' => 'Ketua Uji']]))->assertSessionHasNoErrors();
        $r = $a->fresh()->rekapRencana();
        $this->assertFalse($r['terisi']['pengesahan'], 'PJO wajib bagi perusahaan jasa pertambangan.');

        $this->post("/smkp/{$a->id}/rencana", $this->rencana(['ktt' => ['nama' => 'KTT Uji'], 'pjo' => ['nama' => 'PJO Uji'], 'ketua' => ['nama' => 'Ketua Uji']]));
        $this->assertTrue($a->fresh()->rekapRencana()['terisi']['pengesahan']);

        // Pemegang izin tidak memerlukan PJO.
        $b = $this->buat();
        $this->post("/smkp/{$b->id}/rencana", $this->rencana(['ktt' => ['nama' => 'KTT Uji'], 'ketua' => ['nama' => 'Ketua Uji']]));
        $this->assertTrue($b->fresh()->rekapRencana()['terisi']['pengesahan']);

        $this->get("/smkp/{$a->id}/rencana")->assertInertia(fn (Inertia $p) => $p->where('pengesah.pjo.wajib', true));
        $this->get("/smkp/{$b->id}/rencana")->assertInertia(fn (Inertia $p) => $p->where('pengesah.pjo.wajib', false));
    }

    /* ---------- 10. jejak aktivitas ---------- */

    public function test_perubahan_temuan_dan_daftar_hadir_tercatat(): void
    {
        $a = $this->buat();
        $this->post("/smkp/{$a->id}/elemen/I", ['k' => ['I.1' => ['v' => 1]]]);
        $this->post("/smkp/{$a->id}/temuan/angkat");
        $f = SmkpFinding::where('audit_id', $a->id)->firstOrFail();
        $this->post("/smkp/{$a->id}/rapat", ['rapat' => 'pembukaan', 'nama' => 'Peserta Uji']);
        $hadir = $a->attendees()->firstOrFail();

        $this->put("/smkp/{$a->id}/temuan/{$f->id}", ['status' => 'In Progress']);
        $this->delete("/smkp/{$a->id}/rapat/{$hadir->id}");
        $this->delete("/smkp/{$a->id}/temuan/{$f->id}");

        $aksi = ActivityLog::pluck('action')->all();
        foreach (['Ubah temuan SMKP', 'Tambah peserta rapat', 'Hapus peserta rapat', 'Hapus temuan SMKP'] as $x) {
            $this->assertContains($x, $aksi);
        }
        $this->assertTrue(ActivityLog::where('action', 'Ubah temuan SMKP')->where('detail', 'like', '%Open → In Progress%')->exists());
    }

    /* ---------- 11. angka berkoma & pesan berbahasa Indonesia ---------- */

    public function test_tahap_satu_menerima_angka_berkoma(): void
    {
        $a = $this->buat();
        $this->post("/smkp/{$a->id}/tahap-1", ['permulaan' => ['mandays_dasar' => '12,5', 'penyesuaian' => '0,5', 'jumlah_auditor' => '2', 'jumlah_pekerja' => '1.250', 'faktor' => ['jarak' => true]]])
            ->assertSessionHasNoErrors();

        $p = $a->fresh()->permulaan;
        $this->assertSame(['12.5', '0.5', '1250'], [$p['mandays_dasar'], $p['penyesuaian'], $p['jumlah_pekerja']]);
        $this->assertEquals(6.75, $a->fresh()->mandays()['total']);
    }

    /* ---------- faktor penyesuaian hari kerja: paling banyak 10% ---------- */

    public function test_faktor_penyesuaian_paling_banyak_sepuluh_persen_bukan_sehari_per_kondisi(): void
    {
        $semua = array_fill_keys(array_keys(SmkpTahap::faktorPenyesuaian()), true);
        $satu  = ['pengolahan' => true];

        // 20 hari / 2 auditor = 10 hari per auditor.
        $md = SmkpTahap::mandays(['mandays_dasar' => '20', 'jumlah_auditor' => '2', 'faktor' => $semua, 'penyesuaian' => '']);
        $this->assertSame([7, 1.0, 1.0, 11.0], [$md['kondisi'], $md['maks_penyesuaian'], $md['penyesuaian'], $md['total']], 'Tujuh kondisi tetap paling banyak 10%, bukan 7 hari.');
        $this->assertEquals(10.0, $md['persen_penyesuaian']);

        $md = SmkpTahap::mandays(['mandays_dasar' => '20', 'jumlah_auditor' => '2', 'faktor' => $satu, 'penyesuaian' => '']);
        $this->assertSame([1, 1.0, 11.0], [$md['kondisi'], $md['penyesuaian'], $md['total']], 'Satu kondisi sudah membuka tambahan 10%.');

        $md = SmkpTahap::mandays(['mandays_dasar' => '20', 'jumlah_auditor' => '2', 'faktor' => [], 'penyesuaian' => '']);
        $this->assertSame([0, 0.0, 10.0], [$md['kondisi'], $md['penyesuaian'], $md['total']], 'Tanpa kondisi tidak ada tambahan.');

        // Auditor boleh lebih kecil; isian lama di atas batas dipangkas.
        $this->assertSame(0.5, SmkpTahap::mandays(['mandays_dasar' => '20', 'jumlah_auditor' => '2', 'faktor' => $satu, 'penyesuaian' => '0.5'])['penyesuaian']);
        $this->assertSame(1.0, SmkpTahap::mandays(['mandays_dasar' => '20', 'jumlah_auditor' => '2', 'faktor' => $semua, 'penyesuaian' => '7'])['penyesuaian']);
        $this->assertSame(0.0, SmkpTahap::mandays(['mandays_dasar' => '20', 'jumlah_auditor' => '2', 'faktor' => [], 'penyesuaian' => '2'])['penyesuaian']);
    }

    public function test_simpan_tahap_satu_menolak_penyesuaian_di_atas_sepuluh_persen(): void
    {
        $a = $this->buat();
        $isi = ['mandays_dasar' => '20', 'jumlah_auditor' => '2'];

        $this->post("/smkp/{$a->id}/tahap-1", ['permulaan' => $isi + ['faktor' => ['jarak' => true, 'metode' => true], 'penyesuaian' => '2']])
            ->assertSessionHasErrors(['permulaan.penyesuaian' => 'Faktor penyesuaian paling banyak 10% dari hari kerja per auditor: 1,00 hari.']);
        $this->post("/smkp/{$a->id}/tahap-1", ['permulaan' => $isi + ['penyesuaian' => '1']])
            ->assertSessionHasErrors('permulaan.penyesuaian');
        $this->post("/smkp/{$a->id}/tahap-1", ['permulaan' => $isi + ['faktor' => ['jarak' => true, 'metode' => true], 'penyesuaian' => '1']])
            ->assertSessionHasNoErrors();
        $this->assertEquals(11.0, $a->fresh()->mandays()['total']);

        // Berita acara menyebut persentasenya.
        $this->get("/smkp/{$a->id}/berita-acara")->assertInertia(fn (Inertia $p) => $p
            ->where('mandays.penyesuaian', 1)->where('mandays.persen_penyesuaian', 10)->where('mandays.kondisi', 2));
    }

    public function test_isian_penyesuaian_lama_di_atas_batas_tidak_menghalangi_simpan_tahap_satu(): void
    {
        $a = $this->buat();
        // Tersimpan dengan aturan lama (sehari per kondisi).
        $a->forceFill(['permulaan' => ['mandays_dasar' => '20', 'jumlah_auditor' => '2', 'penyesuaian' => '2',
            'faktor' => ['jarak' => true, 'metode' => true]]])->save();

        $this->get("/smkp/{$a->id}/tahap-1")->assertInertia(fn (Inertia $p) => $p->where('isian.permulaan.penyesuaian', '1'));

        // Formulir dikirim apa adanya (isian sudah dipangkas) — tidak ditolak.
        $this->post("/smkp/{$a->id}/tahap-1", ['permulaan' => ['mandays_dasar' => '20', 'jumlah_auditor' => '2', 'penyesuaian' => '1',
            'faktor' => ['jarak' => true, 'metode' => true]], 'kecukupan' => ['I' => ['status' => SmkpTahap::LENGKAP]]])->assertSessionHasNoErrors();
        $this->assertSame(SmkpTahap::LENGKAP, $a->fresh()->kecukupan['I']['status']);

        // Tanpa kondisi, isian lama tidak ditampilkan.
        $a->forceFill(['permulaan' => ['mandays_dasar' => '20', 'jumlah_auditor' => '2', 'penyesuaian' => '2']])->save();
        $this->get("/smkp/{$a->id}/tahap-1")->assertInertia(fn (Inertia $p) => $p->where('isian.permulaan.penyesuaian', ''));
    }

    public function test_perusahaan_di_luar_jangkauan_tidak_terbaca_lewat_pesan_validasi(): void
    {
        SmkpAudit::create(['company_id' => $this->sister->id, 'tahun' => 2026, 'status' => 'berjalan', 'tahap' => 1, 'hasil' => []]);
        $u = $this->auditor($this->induk);

        // Periode yang ada, periode yang tidak ada, dan perusahaan fiktif: jawabannya sama.
        foreach ([[$this->sister->id, 2026], [$this->sister->id, 2025], [999999, 2026]] as [$cid, $th]) {
            $this->actingAs($u)->post('/smkp', ['company_id' => $cid, 'tahun' => $th, 'status' => 'draft'])->assertForbidden();
        }
    }

    public function test_masuk_pelaporan_membekukan_hari_kerja(): void
    {
        $a = $this->buat();
        $kecukupan = [];
        foreach (Smkp::elemen() as $e) $kecukupan[$e['kode']] = ['status' => SmkpTahap::LENGKAP, 'ket' => ''];
        $this->post("/smkp/{$a->id}/tahap-1", ['kecukupan' => $kecukupan, 'permulaan' => ['mandays_dasar' => '20', 'jumlah_auditor' => '2', 'faktor' => ['jarak' => true]]])->assertSessionHasNoErrors();
        $this->post("/smkp/{$a->id}/rencana", $this->rencana(['ktt' => ['nama' => 'KTT Uji'], 'ketua' => ['nama' => 'Ketua Uji']]))->assertSessionHasNoErrors();
        $this->post("/smkp/{$a->id}/tahap", ['tahap' => SmkpTahap::PELAPORAN])->assertSessionHasNoErrors();
        $this->assertEquals(11.0, $a->fresh()->mandays_tetap['total']);

        // Isian Tahap I diubah setelah selesai: berkas tetap memakai angka yang dibekukan.
        $this->post("/smkp/{$a->id}/tahap-1", ['kecukupan' => $kecukupan, 'permulaan' => ['mandays_dasar' => '40', 'jumlah_auditor' => '2', 'faktor' => ['jarak' => true]]])->assertSessionHasNoErrors();
        $this->get("/smkp/{$a->id}/berita-acara")->assertInertia(fn (Inertia $p) => $p->where('mandays.total', 11));
        $this->get("/smkp/{$a->id}/tahap-1")->assertInertia(fn (Inertia $p) => $p->where('hariTetap.total', 11));
    }

    public function test_pesan_validasi_berbahasa_indonesia(): void
    {
        $a = $this->buat();
        $this->post("/smkp/{$a->id}/tahap-1", ['permulaan' => ['mandays_dasar' => 'banyak']])
            ->assertSessionHasErrors(['permulaan.mandays_dasar' => 'Kolom mandays dasar harus berupa angka.']);
    }
}
