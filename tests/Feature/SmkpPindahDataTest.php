<?php

namespace Tests\Feature;

use App\Models\{SmkpAudit, SmkpFinding};
use App\Support\SmkpTahap;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Tests\TestCase;

/**
 * Pemindahan data audit SMKP lama EQOHSEE ke bentuk modul Aurorasys
 * (2026_11_07_000002_skema_smkp_aurorasys + 2026_11_07_000003_pindah_data_smkp_aurorasys).
 *
 * Basis data uji sudah termigrasi penuh; kedua migrasi dibalik lebih dulu
 * agar skema lama kembali, diisi data berbentuk lama, lalu dijalankan
 * ulang. Yang dijaga: setiap isian berpindah ke padanannya, yang tidak
 * berpadanan tersimpan di arsip, dan down() memulihkan semuanya.
 */
class SmkpPindahDataTest extends TestCase
{
    use RefreshDatabase;

    private object $skema;
    private object $pindah;

    protected function setUp(): void
    {
        parent::setUp();
        $this->skema  = require database_path('migrations/2026_11_07_000002_skema_smkp_aurorasys.php');
        $this->pindah = require database_path('migrations/2026_11_07_000003_pindah_data_smkp_aurorasys.php');

        $this->pindah->down();
        $this->skema->down();
    }

    /** @return array{a:int,b:int,admin:int,u1:int,u2:int,u3:int,audit:int,yatim:int,tMayor:int,tTutup:int,tBuka:int,tObs:int} */
    private function dataLama(): array
    {
        $kini = now()->toDateTimeString();
        $a = DB::table('companies')->insertGetId(['name' => 'PT Contoh Satu', 'code' => 'ABC', 'kategori' => 'induk', 'risk_class' => 'Menengah', 'created_at' => $kini, 'updated_at' => $kini]);
        $b = DB::table('companies')->insertGetId(['name' => 'PT Contoh Dua', 'code' => 'CDE', 'kategori' => 'induk', 'risk_class' => 'Tinggi', 'created_at' => $kini, 'updated_at' => $kini]);

        $user = fn (array $x) => DB::table('users')->insertGetId($x + ['name' => 'Pengguna', 'email' => uniqid().'@contoh.test', 'password' => 'x', 'created_at' => $kini, 'updated_at' => $kini]);
        $admin = $user(['is_admin' => true, 'audit_role' => null]);
        $u1 = $user(['is_admin' => false, 'audit_role' => null, 'company_id' => $b]);
        $u2 = $user(['is_admin' => false, 'audit_role' => 'company', 'company_id' => $a]);
        $u3 = $user(['is_admin' => false, 'audit_role' => 'auditor', 'company_id' => $a]);

        $audit = DB::table('smkp_audits')->insertGetId([
            'company_id' => $a, 'tahun' => 2025, 'judul' => 'Audit Lama', 'status' => 'selesai', 'tahap' => 3, 'user_id' => $u3,
            'hasil' => json_encode([
                'I.1'      => ['v' => 4, 'ket' => '', 'bukti' => ''],
                'III.2.1'  => ['v' => 3, 'ket' => 'SK ada', 'bukti' => ''],
                'III.12.4' => ['v' => 4, 'ket' => '', 'bukti' => ''],
                'IV.5.1'   => ['v' => 'N/A', 'ket' => '', 'bukti' => ''],
                'III.12.1' => ['v' => 4, 'ket' => '', 'bukti' => ''],
                'III.12.3' => ['v' => 2, 'ket' => '', 'bukti' => ''],
                'IV.3.10'  => ['v' => 4, 'ket' => '', 'bukti' => ''],
                'III.13'   => ['v' => 4, 'ket' => '', 'bukti' => ''],
            ]),
            'permulaan' => json_encode([
                'tanggal_kontak' => '2025-02-01', 'tim' => ['Ani', 'Budi'], 'jumlah_pekerja' => 50, 'kelas_risiko' => 'Menengah',
                'faktor' => ['jarak' => true, 'kptk' => true], 'nasional' => ['fr' => '1', 'sr' => '2'], 'pengurang' => ['terintegrasi' => true],
                'kelayakan' => ['kerjasama' => 'Baik'], 'kesimpulan' => 'Layak',
            ]),
            'kinerja' => json_encode(['fr' => '5', 'sr' => '10', 'kptk' => '1']),
            'rencana' => json_encode([
                'tujuan' => 'Menilai SMKP', 'tugas' => [['nama' => 'Budi', 'registrasi' => 'R2', 'lingkup' => 'II']],
                'pengesahan' => ['ketua' => ['nama' => 'Ani'], 'ptl' => ['nama' => 'Pak PTL', 'jabatan' => 'PTL']],
            ]),
            'sampel' => json_encode([
                'II.2.1' => ['na' => false, 'dokumen' => "Dok A\nDok B", 'wawancara' => 'KTT'],
                'II.2.2' => ['na' => false, 'observasi' => 'Area pit'],
                'III.1'  => ['na' => true, 'ket' => 'Tidak ada tambang bawah tanah'],
            ]),
            'laporan' => json_encode([
                'domisili' => 'Jakarta', 'kegiatan' => 'Penambangan', 'lingkup' => "Pit A\nPit B",
                'peralatan' => [['jenis' => 'Dump truck', 'jumlah' => '3']], 'distribusi' => ['KTT', 'Direksi'],
                'kesimpulan' => 'Penerapan baik', 'elemen' => ['I' => 'Kebijakan ditinjau'], 'lampiran' => ['Daftar hadir'],
            ]),
            'created_at' => $kini, 'updated_at' => $kini,
        ]);
        $yatim = DB::table('smkp_audits')->insertGetId(['company_id' => null, 'tahun' => 2026, 'status' => 'draft', 'tahap' => 1, 'user_id' => $u1, 'created_at' => $kini, 'updated_at' => $kini]);

        $temuan = fn (array $x) => DB::table('smkp_findings')->insertGetId($x + ['audit_id' => $audit, 'uraian' => 'Uraian', 'status' => 'Open', 'created_at' => $kini, 'updated_at' => $kini]);
        $tMayor = $temuan(['kode_kriteria' => 'II.2', 'jenis' => 'mayor', 'respon_diterima' => true, 'respon_manajemen' => 'Diterima, dijadwalkan', 'respon_oleh' => 'Direktur']);
        $tTutup = $temuan(['kode_kriteria' => 'II.2.1', 'jenis' => 'minor', 'status' => 'Closed', 'tindakan' => 'Diperbaiki', 'foto_closed' => "smkp/{$audit}/foto-tutup.jpg", 'tanggal_selesai' => '2025-05-01']);
        $tBuka = $temuan(['kode_kriteria' => 'I.1', 'jenis' => 'minor', 'foto_open' => "smkp/{$audit}/foto-buka.jpg"]);
        $tObs = $temuan(['kode_kriteria' => 'V.1', 'jenis' => 'obs', 'uraian' => 'Papan informasi tertutup']);

        DB::table('smkp_ofi')->insert(['audit_id' => $audit, 'kode' => 'I.2', 'lingkup' => 'sub', 'uraian' => 'Peluang otomasi', 'saran' => 'Pakai pengingat',
            'penanggung_jawab' => 'KTT', 'target' => '2026-01-13', 'status' => 'terbuka', 'created_at' => $kini, 'updated_at' => $kini]);
        DB::table('smkp_bukti')->insert(['audit_id' => $audit, 'kode' => 'I.1', 'file_path' => "smkp/{$audit}/sop.pdf", 'file_name' => 'sop.pdf',
            'file_size' => 8, 'mime' => 'application/pdf', 'catatan' => 'SOP Kebijakan', 'user_id' => $u3, 'created_at' => $kini, 'updated_at' => $kini]);
        DB::table('smkp_attendees')->insert(['audit_id' => $audit, 'rapat' => 'pembukaan', 'nama' => 'KTT', 'tanda_tangan' => 'signatures/ttd.png', 'created_at' => $kini, 'updated_at' => $kini]);

        Storage::disk('local')->put("smkp/{$audit}/sop.pdf", '%PDF-1.4');
        Storage::disk('local')->put("smkp/{$audit}/foto-tutup.jpg", 'jpeg');
        Storage::disk('local')->put("smkp/{$audit}/foto-buka.jpg", 'jpeg');

        return compact('a', 'b', 'admin', 'u1', 'u2', 'u3', 'audit', 'yatim', 'tMayor', 'tTutup', 'tBuka', 'tObs');
    }

    private function jalankan(): void
    {
        $this->skema->up();
        $this->pindah->up();
    }

    public function test_data_lama_berpindah_ke_bentuk_aurorasys_tanpa_hilang(): void
    {
        $d = $this->dataLama();
        $this->jalankan();

        /* ── peran & perusahaan ── */
        $peran = DB::table('users')->pluck('audit_role', 'id');
        $this->assertSame('auditor', $peran[$d['u1']], 'Pengguna yang dulu dapat menyunting SMKP tidak terkunci.');
        $this->assertSame('company', $peran[$d['u2']], 'Peran Perusahaan yang ditetapkan administrator tetap auditi.');
        $this->assertNull($peran[$d['admin']], 'Administrator tetap administrator.');
        $this->assertSame('Sedang', DB::table('companies')->where('id', $d['a'])->value('risk_class'));

        /* ── audit ── */
        $a = SmkpAudit::withoutGlobalScopes()->findOrFail($d['audit']);
        $this->assertSame([3, 'selesai'], [$a->tahap, $a->status]);

        $h = $a->hasil;
        $this->assertSame(4, $h['III.2.1']['maks_lama']);
        $this->assertSame(4, $h['III.12.4']['maks_lama']);
        $this->assertArrayNotHasKey('maks_lama', $h['IV.5.1'], 'N/A tidak berskala.');
        $this->assertArrayNotHasKey('maks_lama', $h['III.13'], 'III.13 sudah bermaksimum 4 di EQOHSEE.');
        $this->assertArrayNotHasKey('maks_lama', $h['I.1']);
        $this->assertSame([1, 2, 4], [$h['III.12.1']['koreksi'], $h['III.12.3']['koreksi'], $h['IV.3.10']['koreksi']]);
        $this->assertSame('SK ada', $h['III.2.1']['ket']);

        // Hari kerja lama: tabel 50 pekerja kelas Menengah = 7, + 3 penambah (jarak; kecelakaan dan
        // PAK dihitung dari kinerja) − 1 pengurang = 9 mandays, dua auditor → 4,5 hari.
        $p = $a->permulaan;
        $this->assertSame(['9', '2', '0', 'Sedang'], [$p['mandays_dasar'], $p['jumlah_auditor'], $p['penyesuaian'], $p['kelas_risiko']]);
        $this->assertSame(['jarak' => true, 'metode' => false, 'pengolahan' => false, 'kecelakaan' => true, 'penyakit' => false, 'berbahaya' => false, 'pak' => true], $p['faktor']);
        $this->assertArrayNotHasKey('tim', $p);
        $this->assertArrayNotHasKey('nasional', $p);
        $this->assertSame('Layak', $p['kesimpulan']);
        $this->assertEquals(4.5, $a->mandays()['total']);
        $this->assertEquals(['per_auditor' => 4.5, 'total' => 4.5, 'tahap1' => 0.45, 'tahap2' => 4.05, 'aturan' => 'eqohsee'],
            array_intersect_key($a->mandays_tetap, array_flip(['per_auditor', 'total', 'tahap1', 'tahap2', 'aturan'])));
        $this->assertEquals(4.5, SmkpTahap::mandays($p)['total'], 'Dihitung ulang dengan aturan Aurorasys, angkanya sama.');

        $r = $a->rencana;
        $this->assertSame([
            ['nama' => 'Ani', 'peran' => 'Lead Auditor', 'registrasi' => '', 'lingkup' => ''],
            ['nama' => 'Budi', 'peran' => 'Auditor', 'registrasi' => 'R2', 'lingkup' => 'II'],
        ], $r['tugas']);
        $this->assertArrayNotHasKey('ptl', $r['pengesahan']);
        $this->assertSame('Pak PTL', $r['pengesahan']['ktt']['nama'], 'Pengesah PTL mengisi KTT yang kosong.');
        $this->assertSame('Tinjauan Dokumen dan Rekaman, Wawancara, Observasi', $r['metode_sampel']['II.2']['metode']);
        $this->assertSame("II.2.1: Dok A\nII.2.1: Dok B; II.2.1: KTT; II.2.2: Area pit", $r['metode_sampel']['II.2']['sampel']);
        $this->assertStringContainsString('III.1 — ', $r['pengecualian']);
        $this->assertStringContainsString('Tidak ada tambang bawah tanah', $r['pengecualian']);

        $l = $a->laporan;
        $this->assertSame(['Jakarta', 'Penambangan', ['Pit A', 'Pit B'], "KTT\nDireksi"], [$l['legalitas'], $l['kegiatan'], $l['area'], $l['distribusi']]);
        $this->assertSame(['jenis' => 'Dump truck', 'tipe' => '', 'produktivitas' => '', 'jumlah' => '3'], $l['peralatan'][0]);
        $this->assertArrayNotHasKey('kesimpulan', $l);

        // OFI dan observasi menjadi Formulir Rekomendasi & OFI.
        $rek = collect($a->rekomendasi);
        $this->assertSame(2, $rek->count());
        $ofi = $rek->firstWhere('sub', 'I.2');
        $this->assertSame('I', $ofi['elemen']);
        $this->assertStringContainsString("Peluang otomasi\nSaran: Pakai pengingat\nPenanggung jawab: KTT\nTarget: 13 Januari 2026\nStatus: Terbuka", $ofi['uraian']);
        $this->assertStringStartsWith('Observasi: Papan informasi tertutup', $rek->firstWhere('sub', 'V.1')['uraian']);

        /* ── temuan ── */
        $this->assertNull(SmkpFinding::withoutGlobalScopes()->find($d['tObs']), 'Observasi bukan ketidaksesuaian.');
        $nomor = SmkpFinding::withoutGlobalScopes()->where('audit_id', $d['audit'])->pluck('nomor', 'id');
        $this->assertSame('ABC-MYR-01-2025', $nomor[$d['tMayor']]);
        $this->assertSame('ABC-MNR-01-2025', $nomor[$d['tBuka']], 'Minor urut acuan: I.1 sebelum II.2.1.');
        $this->assertSame('ABC-MNR-02-2025', $nomor[$d['tTutup']]);

        $tl = DB::table('smkp_tindak_lanjut')->where('finding_id', $d['tTutup'])->first();
        $lampiran = json_decode($tl->lampiran, true)[0];
        $this->assertSame("tindak-lanjut/{$d['audit']}/{$d['tTutup']}/foto-tutup.jpg", $lampiran['path']);
        $this->assertSame('Diperbaiki', $tl->tindakan);
        Storage::disk('local')->assertExists($lampiran['path']);
        Storage::disk('local')->assertExists("smkp/{$d['audit']}/foto-tutup.jpg");   // asli tidak dihapus

        $buka = DB::table('smkp_bukti')->where('audit_id', $d['audit'])->where('kode', 'I.1')->where('path', 'like', '%foto-buka.jpg')->first();
        $this->assertSame('diterima', $buka->status);
        $this->assertStringStartsWith("bukti-audit/{$d['audit']}/", $buka->path);

        /* ── bukti ── */
        $b = DB::table('smkp_bukti')->where('nama', 'sop.pdf')->first();
        $this->assertSame(["bukti-audit/{$d['audit']}/sop.pdf", 'SOP Kebijakan', 'diterima', $d['u3']],
            [$b->path, $b->keterangan, $b->status, (int) $b->diverifikasi_oleh]);
        Storage::disk('local')->assertExists($b->path);

        /* ── audit tanpa perusahaan: perusahaan pembuatnya ── */
        $this->assertSame($d['b'], (int) DB::table('smkp_audits')->where('id', $d['yatim'])->value('company_id'));

        /* ── arsip yang tidak berpadanan ── */
        $arsip = fn (string $tabel, int $id) => json_decode((string) DB::table('smkp_arsip_eqohsee')
            ->where('tabel', $tabel)->where('baris_id', $id)->where('jenis', 'asli')->value('tanpa_padanan'), true);
        $this->assertSame('Diterima, dijadwalkan', $arsip('smkp_findings', $d['tMayor'])['respon_manajemen']);
        $sisa = $arsip('smkp_audits', $d['audit']);
        $this->assertSame(['fr' => '1', 'sr' => '2'], $sisa['permulaan']['nasional']);
        $this->assertSame(['terintegrasi' => true], $sisa['permulaan']['pengurang']);
        $this->assertSame('Pak PTL', $sisa['rencana']['pengesahan_ptl']['nama']);
        $this->assertSame('Penerapan baik', $sisa['laporan']['kesimpulan']);
        $this->assertSame(['Daftar hadir'], $sisa['laporan']['lampiran']);
        $this->assertSame(9, $sisa['permulaan']['hari_kerja_lama']['total']);
        $this->assertTrue(DB::table('smkp_arsip_eqohsee')->where('tabel', 'smkp_attendees')->where('tanpa_padanan', 'like', '%signatures/ttd.png%')->exists());

        /* ── kolom & tabel lama dibuang; nomor unik per audit ── */
        $this->assertFalse(Schema::hasColumn('smkp_audits', 'sampel'));
        $this->assertFalse(Schema::hasColumn('smkp_findings', 'respon_manajemen'));
        $this->assertFalse(Schema::hasColumn('smkp_findings', 'foto_closed'));
        $this->assertFalse(Schema::hasColumn('smkp_attendees', 'tanda_tangan'));
        $this->assertFalse(Schema::hasTable('smkp_ofi'));

        $this->expectException(QueryException::class);
        DB::table('smkp_findings')->insert(['audit_id' => $d['audit'], 'nomor' => 'ABC-MYR-01-2025', 'kode_kriteria' => 'I.3', 'jenis' => 'minor', 'uraian' => 'x', 'status' => 'Open']);
    }

    public function test_dijalankan_ulang_tidak_mengubah_apa_pun(): void
    {
        $this->dataLama();
        $this->jalankan();
        $sebelum = [DB::table('smkp_audits')->get()->toArray(), DB::table('smkp_findings')->get()->toArray(), DB::table('smkp_arsip_eqohsee')->count()];

        $this->pindah->up();

        $this->assertEquals($sebelum, [DB::table('smkp_audits')->get()->toArray(), DB::table('smkp_findings')->get()->toArray(), DB::table('smkp_arsip_eqohsee')->count()]);
    }

    /**
     * Skema sesudah rollback sama dengan sebelum porting: indeks gabungan
     * lama (audit_id, status/rapat/kode) tidak ikut dibuang, smkp_ofi
     * dibuat ulang lengkap dengan indeksnya, dan tidak ada indeks tambahan.
     * (Diperiksa pula pada MariaDB terhadap salinan basis data pra-porting.)
     */
    public function test_down_memulihkan_skema_lama_persis(): void
    {
        $potret = fn () => collect(['smkp_audits', 'smkp_findings', 'smkp_attendees', 'smkp_bukti', 'smkp_ofi'])
            ->mapWithKeys(fn ($t) => [$t => [
                collect(Schema::getColumns($t))->map(fn ($c) => $c['name'].':'.$c['type'].($c['nullable'] ? '?' : ''))->sort()->values()->all(),
                collect(Schema::getIndexes($t))->map(fn ($i) => implode(',', $i['columns']).($i['unique'] ? '!' : ''))->sort()->values()->all(),
            ]])->all();
        $lama = $potret();

        foreach ([['smkp_findings', 'audit_id,status'], ['smkp_attendees', 'audit_id,rapat'], ['smkp_bukti', 'audit_id,kode'], ['smkp_ofi', 'kode'], ['smkp_ofi', 'status'], ['smkp_ofi', 'audit_id,kode!']] as [$t, $i]) {
            $this->assertContains($i, $lama[$t][1], "Indeks {$t}({$i}) skema lama.");
        }

        $this->dataLama();
        $this->jalankan();
        $this->pindah->down();
        $this->skema->down();

        $this->assertEquals($lama, $potret());
    }

    public function test_down_memulihkan_data_lama(): void
    {
        $d = $this->dataLama();
        $asli = [
            'audit'   => (array) DB::table('smkp_audits')->where('id', $d['audit'])->first(),
            'temuan'  => DB::table('smkp_findings')->orderBy('id')->get()->map(fn ($x) => (array) $x)->all(),
            'ofi'     => DB::table('smkp_ofi')->get()->map(fn ($x) => (array) $x)->all(),
            'bukti'   => DB::table('smkp_bukti')->get()->map(fn ($x) => (array) $x)->all(),
            'hadir'   => DB::table('smkp_attendees')->get()->map(fn ($x) => (array) $x)->all(),
            'peran'   => DB::table('users')->pluck('audit_role', 'id')->all(),
            'risiko'  => DB::table('companies')->pluck('risk_class', 'id')->all(),
        ];
        $this->jalankan();

        $this->pindah->down();
        $this->skema->down();

        $this->assertEquals($asli['audit'], (array) DB::table('smkp_audits')->where('id', $d['audit'])->first());
        $this->assertEquals($asli['temuan'], DB::table('smkp_findings')->orderBy('id')->get()->map(fn ($x) => (array) $x)->all());
        $this->assertEquals($asli['ofi'], DB::table('smkp_ofi')->get()->map(fn ($x) => (array) $x)->all());
        $this->assertEquals($asli['bukti'], DB::table('smkp_bukti')->get()->map(fn ($x) => (array) $x)->all());
        $this->assertEquals($asli['hadir'], DB::table('smkp_attendees')->get()->map(fn ($x) => (array) $x)->all());
        $this->assertEquals($asli['peran'], DB::table('users')->pluck('audit_role', 'id')->all());
        $this->assertEquals($asli['risiko'], DB::table('companies')->pluck('risk_class', 'id')->all());
        Storage::disk('local')->assertMissing("tindak-lanjut/{$d['audit']}/{$d['tTutup']}/foto-tutup.jpg");
        Storage::disk('local')->assertExists("smkp/{$d['audit']}/foto-tutup.jpg");
    }
}
