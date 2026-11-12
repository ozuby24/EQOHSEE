<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Miners\{McuRujukan, Pekerja, PermitPerpanjangan, SimperAjuan};
use App\Models\User;
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\SiapanMiners;
use Tests\TestCase;

/**
 * Batas baca dan ubah data pribadi dan medis modul Miners.
 *
 * Lingkup perusahaan sudah dijaga global scope; uji ini menjaga batas DI
 * DALAM satu perusahaan: pengguna biasa tidak membaca atau mengubah data
 * orang lain hanya karena perusahaannya sama.
 */
class MinersAksesDataTest extends TestCase
{
    use RefreshDatabase;
    use SiapanMiners;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkan();
    }

    /** Pengguna biasa di mitra: bukan pengaju, bukan penyetuju. */
    private function biasa(): User
    {
        return User::factory()->create(['company_id' => $this->mitra->id]);
    }

    /** Pengguna perusahaan lain sama sekali. */
    private function asing(): User
    {
        return User::factory()->create(['company_id' => Company::create(['name' => 'PT Asing '.Str::random(4)])->id]);
    }

    private function pekerja(?User $pemilik = null): Pekerja
    {
        return Pekerja::withoutGlobalScopes()->create([
            'company_id' => $this->mitra->id, 'nama' => 'Pekerja '.Str::random(4),
            'nik' => (string) random_int(1000000000000000, 9999999999999999), 'status' => 'aktif',
            'user_id' => $pemilik?->id,
        ]);
    }

    /* ═══════════ 1 · ubah manpower ═══════════ */

    #[Test]
    public function manpower_hanya_diubah_pengelola_atau_pendaftarnya(): void
    {
        $pemilik = $this->biasa();
        $pk = $this->pekerja($pemilik);

        $this->actingAs($this->biasa())->put('/miners/'.$pk->id, ['nama' => 'Diubah Orang Lain'])->assertForbidden();
        $this->actingAs($this->pengaju)->put('/miners/'.$pk->id, ['nama' => 'Diubah Pengaju'])->assertForbidden();
        $this->actingAs($this->asing())->put('/miners/'.$pk->id, ['nama' => 'Diubah Asing'])->assertNotFound();
        $this->assertSame($pk->nama, $pk->fresh()->nama);

        $this->actingAs($pemilik)->put('/miners/'.$pk->id, ['nama' => 'Oleh Pendaftar'])->assertSessionHasNoErrors();
        $this->assertSame('Oleh Pendaftar', $pk->fresh()->nama);

        $this->actingAs($this->ohse)->put('/miners/'.$pk->id, ['nama' => 'Oleh HSE'])->assertSessionHasNoErrors();
        $this->assertSame('Oleh HSE', $pk->fresh()->nama);
    }

    /* ═══════════ 2 · daftar rujukan ═══════════ */

    #[Test]
    public function daftar_rujukan_hanya_bagi_admin_ohse_dan_paramedis(): void
    {
        $o = $this->mcuSelesai($this->mitra, 'Rujukan');

        foreach ([$this->biasa(), $this->pengaju, $this->ktt] as $u) {
            $this->actingAs($u)->get('/miners/daftar/rujukan')->assertForbidden();
        }

        foreach ([$this->admin, $this->ohse, $this->dokter] as $u) {
            $this->actingAs($u)->get('/miners/daftar/rujukan')->assertOk()
                ->assertInertia(fn (Assert $p) => $p->where('baris.0.id', $o->id));
        }

        /* Menunya ikut hilang bagi yang pasti ditolak. */
        $this->assertFalse($this->diMenu($this->pengaju, 'miners/daftar/rujukan'));
        $this->assertTrue($this->diMenu($this->dokter, 'miners/daftar/rujukan'));
    }

    /* ═══════════ 3 · rincian, sertifikat, surat, isi medis, SIM ═══════════ */

    #[Test]
    public function rincian_dokumen_hanya_bagi_pengaju_pendaftar_penyetuju_dan_pengelola(): void
    {
        $pendaftar = $this->biasa();
        $o  = $this->mcuSelesai($this->mitra, 'Fit', null, $this->pekerja($pendaftar));
        $s  = $this->simperTerbit($this->permitTerbit($o));
        $p  = $s->permit;
        $io = $p->induksiOrang;
        $a  = SimperAjuan::create(['simper_id' => $s->id, 'jenis' => 'penambahan', 'tanggal' => Waktu::kini()->startOfDay(),
            'user_id' => $this->pengaju->id]);
        $x  = PermitPerpanjangan::withoutGlobalScopes()->create(['company_id' => $p->company_id, 'iup_id' => $p->iup_id,
            'permit_id' => $p->id, 'tanggal' => Waktu::kini()->startOfDay(), 'user_id' => $this->pengaju->id]);
        foreach ([$o->mcu, $io->induksi, $p, $s, $a, $x] as $d) $d->terbitkanAlur();

        $rincian = ['/miners/mcu/'.$o->mcu_id, '/miners/induksi/'.$io->induksi_id, '/miners/permit/'.$p->id,
            '/miners/simper/'.$s->id, '/miners/simper-lanjutan/'.$a->id, '/miners/perpanjangan-permit/'.$x->id,
            '/miners/induksi/peserta/'.$io->id.'/sertifikat'];
        $sertifikatMcu = '/miners/mcu/orang/'.$o->id.'/sertifikat';

        $biasa = $this->biasa();
        foreach ([...$rincian, $sertifikatMcu] as $url) $this->actingAs($biasa)->get($url)->assertForbidden();
        $this->actingAs($this->asing())->get('/miners/permit/'.$p->id)->assertNotFound();

        foreach ([...$rincian, $sertifikatMcu] as $url) $this->actingAs($this->ohse)->get($url)->assertOk();
        foreach ($rincian as $url) $this->actingAs($this->pengaju)->get($url)->assertOk();

        /* Sertifikat MCU menyatakan hasil dan catatan dokter: pengaju yang
           bukan pembaca isi medis tidak membukanya; paramedis membukanya. */
        $this->actingAs($this->pengaju)->get($sertifikatMcu)->assertForbidden();
        $this->actingAs($this->dokter)->get($sertifikatMcu)->assertOk();

        foreach (['/miners/mcu/'.$o->mcu_id, '/miners/permit/'.$p->id, '/miners/simper/'.$s->id] as $url) {
            $this->actingAs($pendaftar)->get($url)->assertOk();
        }

        /* KTT ditugasi pada Mine Permit, tidak pada MCU-nya. */
        $this->actingAs($this->ktt)->get('/miners/permit/'.$p->id)->assertOk();
        $this->actingAs($this->ktt)->get('/miners/mcu/'.$o->mcu_id)->assertForbidden();
        $this->actingAs($this->dokter)->get('/miners/mcu/'.$o->mcu_id)->assertOk();
    }

    #[Test]
    public function isi_medis_tidak_dikirim_kepada_yang_bukan_pembaca_medis(): void
    {
        $o = $this->mcuSelesai($this->mitra, 'Fit With Note');
        $o->forceFill(['catatan' => 'Tidak untuk shift malam.'])->save();
        $j = McuRujukan::create(['mcu_orang_id' => $o->id, 'tanggal_surat' => '2026-03-20', 'dokter' => 'dr. Spesialis',
            'poliklinik' => 'Penyakit Dalam', 'rumah_sakit' => 'RS Uji', 'user_id' => $this->dokter->id]);
        $p = $this->permitTerbit($o);

        $mcu = '/miners/mcu/'.$o->mcu_id;
        $this->actingAs($this->pengaju)->get($mcu)->assertOk()
            ->assertInertia(fn (Assert $pg) => $pg->where('medis', false)
                ->where('orang.0.hasil', null)->where('orang.0.hasil_id', null)->where('orang.0.layak', null)
                ->where('orang.0.catatan', null)->where('orang.0.rujukan', [])->where('orang.0.stempel', null)
                ->where('orang.0.cetak', null)->where('orang.0.berkas.rujukan.ada', false));
        $orang = json_encode($this->actingAs($this->pengaju)->get($mcu)->viewData('page')['props']['orang']);
        foreach (['Fit With Note', 'shift malam', 'Penyakit Dalam', 'rujukan/'.$j->id] as $t) {
            $this->assertStringNotContainsString($t, $orang);
        }

        $this->actingAs($this->dokter)->get($mcu)->assertOk()
            ->assertInertia(fn (Assert $pg) => $pg->where('orang.0.hasil', 'Fit With Note')
                ->where('orang.0.catatan', 'Tidak untuk shift malam.')
                ->where('orang.0.rujukan.0.surat', route('miners.mcu.rujukan.surat', $j->id)));

        /* Surat rujukan: bukan lagi setiap orang perusahaan pemberi kerja. */
        $this->actingAs($this->pengaju)->get('/miners/mcu/rujukan/'.$j->id.'/surat')->assertForbidden();
        $this->actingAs($this->biasa())->get('/miners/mcu/rujukan/'.$j->id.'/surat')->assertForbidden();
        $this->actingAs($this->dokter)->get('/miners/mcu/rujukan/'.$j->id.'/surat')->assertOk();
        $this->actingAs($this->ohse)->get('/miners/mcu/rujukan/'.$j->id.'/surat')->assertOk();

        /* Rincian Mine Permit dan rincian pekerja memuat hasil MCU yang sama. */
        $this->actingAs($this->pengaju)->get('/miners/permit/'.$p->id)->assertOk()
            ->assertInertia(fn (Assert $pg) => $pg->where('permit.mcuHasil', null)->where('permit.sertifikatMcu', null));
        $this->actingAs($this->ohse)->get('/miners/permit/'.$p->id)->assertOk()
            ->assertInertia(fn (Assert $pg) => $pg->where('permit.mcuHasil', 'Fit With Note'));
        $this->actingAs($this->pengaju)->get('/miners/'.$o->pekerja_id)->assertOk()
            ->assertInertia(fn (Assert $pg) => $pg->where('mcu.0.hasil', null)->where('mcu.0.rujukan', 0));
        $this->actingAs($this->ohse)->get('/miners/'.$o->pekerja_id)->assertOk()
            ->assertInertia(fn (Assert $pg) => $pg->where('mcu.0.hasil', 'Fit With Note')->where('mcu.0.rujukan', 1));
    }

    #[Test]
    public function nomor_sim_tersamar_bagi_yang_tidak_boleh_membuka_pindaiannya(): void
    {
        $s = $this->simperTerbit();   // no_simpol SIM-LAMA-1
        $samar = '••••••MA-1';

        $this->actingAs($this->pengaju)->get('/miners/simper/'.$s->id)->assertOk()
            ->assertInertia(fn (Assert $pg) => $pg->where('simper.noSim', $samar));
        $this->actingAs($this->pengaju)->get('/miners/'.$s->pekerja_id)->assertOk()
            ->assertInertia(fn (Assert $pg) => $pg->where('simper.0.noSim', $samar));
        $this->actingAs($this->ohse)->get('/miners/simper/'.$s->id)->assertOk()
            ->assertInertia(fn (Assert $pg) => $pg->where('simper.noSim', 'SIM-LAMA-1'));

        $a = SimperAjuan::create(['simper_id' => $s->id, 'jenis' => 'perpanjangan', 'tanggal' => Waktu::kini()->startOfDay(),
            'no_simpol_baru' => 'SIM-BARU-22', 'user_id' => $this->pengaju->id]);
        $this->actingAs($this->pengaju)->get('/miners/simper-lanjutan/'.$a->id)->assertOk()
            ->assertInertia(fn (Assert $pg) => $pg->where('ajuan.simLama', $samar)->where('ajuan.simBaru', '•••••••U-22'));
        $this->actingAs($this->pengaju)->get('/miners/daftar/upgrade-simper')->assertOk()
            ->assertInertia(fn (Assert $pg) => $pg->where('calon.0.simpol', null));

        /* Formulir ubah yang dikirim balik dengan samarannya tidak menimpa nomor aslinya. */
        $s->forceFill(['status' => 'draf'])->save();
        $this->actingAs($this->pengaju)->put('/miners/simper/'.$s->id, ['no_simpol' => $samar])->assertSessionHasNoErrors();
        $this->assertSame('SIM-LAMA-1', $s->fresh()->no_simpol);
    }

    #[Test]
    public function history_dan_unduhannya_hanya_memuat_dokumen_yang_boleh_dibuka(): void
    {
        $p = $this->permitTerbit();     // MCU, induksi, permit: diajukan $this->pengaju
        $s = $this->simperTerbit($p);
        foreach ([$p, $s] as $d) $d->terbitkanAlur();
        $lain = $this->permitTerbit();
        $lain->mcuOrang->mcu->forceFill(['user_id' => $this->ohse->id])->save();
        $lain->forceFill(['user_id' => $this->ohse->id])->save();

        $biasa = $this->biasa();
        foreach (['mcu', 'induksi', 'mine-permit', 'mine-license'] as $tahap) {
            $this->actingAs($biasa)->get('/miners/riwayat/'.$tahap)->assertOk()
                ->assertInertia(fn (Assert $pg) => $pg->where('baris.total', 0));
        }
        foreach (['mcu', 'induksi', 'permit', 'simper'] as $m) {
            $this->assertCount(1, $this->lembar("/miners/{$m}/ekspor?mode=riwayat", $biasa), "Unduhan {$m} riwayat bocor.");
        }

        /* Pengaju melihat miliknya, bukan surat yang diajukan HSE. */
        $this->actingAs($this->pengaju)->get('/miners/riwayat/mcu')->assertOk()
            ->assertInertia(fn (Assert $pg) => $pg->where('baris.total', 1));
        $this->actingAs($this->pengaju)->get('/miners/riwayat/mine-permit')->assertOk()
            ->assertInertia(fn (Assert $pg) => $pg->where('baris.total', 1)->where('baris.data.0.id', $p->id));

        /* Penyetuju yang ditugasi melihat dokumen yang ditugaskan kepadanya. */
        $this->actingAs($this->ktt)->get('/miners/riwayat/mine-permit')->assertOk()
            ->assertInertia(fn (Assert $pg) => $pg->where('baris.total', 1)->where('baris.data.0.id', $p->id));

        /* Pengelola dan paramedis (MCU) seluruhnya. */
        $this->actingAs($this->ohse)->get('/miners/riwayat/mine-permit')->assertOk()
            ->assertInertia(fn (Assert $pg) => $pg->where('baris.total', 2));
        $this->actingAs($this->dokter)->get('/miners/riwayat/mcu')->assertOk()
            ->assertInertia(fn (Assert $pg) => $pg->where('baris.total', 2));
    }

    /* ═══════════ 4 · daftar dan unduhan Manpower ═══════════ */

    #[Test]
    public function manpower_dan_unduhannya_hanya_memuat_pekerja_milik_pengguna(): void
    {
        $pendaftar = $this->biasa();
        $didaftar  = $this->pekerja($pendaftar);                       // didaftarkan $pendaftar
        $diajukan  = $this->mcuSelesai($this->mitra, 'Fit With Note');  // MCU-nya diajukan $this->pengaju
        $lain      = $this->pekerja();                                   // tidak berkaitan dengan siapa pun

        $nama = fn (User $u) => collect($this->actingAs($u)->get('/miners')->assertOk()
            ->viewData('page')['props']['baris']['data'])->pluck('nama')->sort()->values()->all();

        $this->assertSame([], $nama($this->biasa()));
        $this->assertSame([$didaftar->nama], $nama($pendaftar));
        $this->assertSame([$diajukan->nama], $nama($this->pengaju));
        $this->assertSame(collect([$didaftar->nama, $diajukan->nama, $lain->nama])->sort()->values()->all(), $nama($this->ohse));

        /* Penyetuju yang ditugasi pada MCU-nya — dokter skema. */
        $diajukan->mcu->terbitkanAlur();
        $dokterBiasa = User::factory()->create(['company_id' => $this->iup->id]);
        \App\Models\Miners\Alur::query()->where('dokumen', 'mcu')->where('dokumen_id', $diajukan->mcu_id)
            ->where('peran', 'dokter')->update(['ditugaskan_user_id' => $dokterBiasa->id]);
        $this->assertSame([$diajukan->nama], $nama($dokterBiasa));

        /* Unduhan: hanya barisnya sendiri; hasil MCU hanya bagi pembaca medis. */
        $isi = $this->lembar('/miners/ekspor', $this->pengaju);
        $this->assertCount(2, $isi);
        $this->assertSame($diajukan->nama, $isi[1][1]);
        $this->assertNull($isi[1][7], 'Hasil MCU terunduh oleh yang bukan pembaca isi medis.');
        $this->assertCount(1, $this->lembar('/miners/ekspor', $this->biasa()));
        $this->assertSame('Fit With Note', collect($this->lembar('/miners/ekspor', $this->ohse))->firstWhere(1, $diajukan->nama)[7]);
        $this->assertSame('Fit With Note', $this->lembar('/miners/ekspor', $dokterBiasa)[1][7]);

        /* Pensil ubah hanya bagi yang boleh mengubah (pendaftar, pengelola). */
        $this->actingAs($this->pengaju)->get('/miners')
            ->assertInertia(fn (Assert $pg) => $pg->where('baris.data.0.isian', null)->where('baris.data.0.hasil', null));
        $this->actingAs($pendaftar)->get('/miners')
            ->assertInertia(fn (Assert $pg) => $pg->where('baris.data.0.isian.nik', $didaftar->nik));
    }

    #[Test]
    public function pemilih_karyawan_pengaju_tetap_bekerja(): void
    {
        /* Calon Mine Permit dan SIMPER tetap daftar perusahaan — bukan
           daftar Manpower yang kini tersaring per pengguna. */
        $io = $this->induksiSelesai($this->mcuSelesai($this->mitra));
        $biasa = $this->biasa();

        $this->actingAs($biasa)->get('/miners/permit')->assertOk()
            ->assertInertia(fn (Assert $pg) => $pg->where('calon.0.id', $io->id));
        $this->actingAs($biasa)->get('/miners/simper')->assertOk()
            ->assertInertia(fn (Assert $pg) => $pg->has('calon'));
    }

    /* ═══════════ 5 · cetak kartu ═══════════ */

    #[Test]
    public function kartu_hanya_dicetak_admin_atau_hse_iup(): void
    {
        $p = $this->permitTerbit();   // milik $this->pengaju
        $alamat = ['/miners/daftar/cetak-kartu', '/miners/kartu/'.$p->id, '/miners/kartu/lembar?id[]='.$p->id];

        foreach ([$this->pengaju, $this->biasa(), $this->ktt] as $u) {
            foreach ($alamat as $url) $this->actingAs($u)->get($url)->assertForbidden();
        }

        $hseLain = User::factory()->create(['company_id' => Company::create(['name' => 'PT IUP Lain'])->id, 'ohse_role' => 'ohse']);
        $this->actingAs($hseLain)->get('/miners/kartu/'.$p->id)->assertNotFound();

        foreach ([$this->ohse, $this->admin] as $u) {
            foreach ($alamat as $url) $this->actingAs($u)->get($url)->assertOk();
        }

        $this->assertFalse($this->diMenu($this->pengaju, 'miners/daftar/cetak-kartu'));
        $this->assertTrue($this->diMenu($this->ohse, 'miners/daftar/cetak-kartu'));
    }

    /* ═══════════ 6 · IUP dan Contractor Mine Permit ═══════════ */

    #[Test]
    public function iup_dan_contractor_permit_hanya_dari_jangkauan_pengguna(): void
    {
        $iupLain   = Company::create(['name' => 'PT IUP Lain', 'code' => 'IUL', 'izin_type' => 'IUP Operasi Produksi']);
        $mitraLain = Company::create(['name' => 'PT Mitra Lain', 'code' => 'MTL', 'izin_type' => 'IUJP', 'parent_id' => $iupLain->id]);

        $io = $this->induksiSelesai($this->mcuSelesai($this->mitra));
        $this->actingAs($this->pengaju)->post('/miners/permit', $this->isianPermit($io, ['iup_id' => $iupLain->id]))
            ->assertSessionHasErrors(['iup_id' => 'Perusahaan (IUP) di luar jangkauan Anda.']);
        $this->actingAs($this->pengaju)->post('/miners/permit', $this->isianPermit($io, ['kontraktor_id' => $mitraLain->id]))
            ->assertSessionHasErrors(['kontraktor_id' => 'Contractor di luar jangkauan Anda.']);
        $this->assertSame(0, \App\Models\Miners\Permit::withoutGlobalScopes()->count());

        /* Akun mitra tetap memilih IUP induknya sendiri. */
        $this->actingAs($this->pengaju)->post('/miners/permit', $this->isianPermit($io))->assertSessionHasNoErrors();

        $p = $this->permitTerbit();
        $this->actingAs($this->ohse)->put('/miners/permit/'.$p->id, ['iup_id' => $iupLain->id, 'jenis_kartu' => 'Mine Permit',
            'departemen_id' => $this->departemen()])->assertSessionHasErrors(['iup_id' => 'Perusahaan (IUP) di luar jangkauan Anda.']);
        $this->assertSame($this->iup->id, (int) $p->fresh()->iup_id);
        $this->actingAs($this->ohse)->put('/miners/permit/'.$p->id, ['iup_id' => $this->iup->id, 'kontraktor_id' => $this->mitra->id,
            'jenis_kartu' => 'Mine Permit', 'departemen_id' => $this->departemen()])->assertSessionHasNoErrors();
    }

    /* ═══════════ 7 · NIK ganda pada impor data lama ═══════════ */

    #[Test]
    public function nik_ganda_data_lama_diperiksa_dalam_jangkauan_pengimpor_saja(): void
    {
        $asing = Company::create(['name' => 'PT Tambang Asing', 'code' => 'TAS']);
        Pekerja::withoutGlobalScopes()->create(['company_id' => $asing->id, 'nama' => 'Orang Asing', 'nik' => '7777', 'status' => 'aktif']);
        Pekerja::withoutGlobalScopes()->create(['company_id' => $this->mitra->id, 'nama' => 'Orang Mitra', 'nik' => '8888', 'status' => 'aktif']);

        $b = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $b->getActiveSheet()->fromArray(\App\Support\Miners\DataLama::KOLOM['permit'], null, 'A1');
        foreach (['7777', '8888'] as $i => $nik) {
            foreach ([$nik, 'Pekerja '.$nik, 'Operator', 'Produksi', 'PT Mitra Uji', 'O', '0800', '2026-01-15', '2030-01-15', 'FULL', ''] as $k => $v) {
                $b->getActiveSheet()->setCellValueExplicit([$k + 1, $i + 2], $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
        }
        $jalur = tempnam(sys_get_temp_dir(), 'dl').'.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($b))->save($jalur);

        $this->actingAs($this->ohse)->post('/miners/data-lama/permit', [
            'berkas' => new \Illuminate\Http\UploadedFile($jalur, 'data.xlsx', null, null, true), 'pratinjau' => '1',
        ])->assertSessionHas('dataLama', function ($h) {
            $gagal = collect($h['gagal'])->pluck('sebab')->implode(' ');

            /* NIK di perusahaan lain tidak lagi menolak — dan tidak disebut;
               NIK di jangkauan sendiri tetap dilewati. */
            return count($h['berhasil']) === 1 && count($h['gagal']) === 1
                && str_contains($gagal, 'sudah ada dalam jangkauan') && ! str_contains($gagal, 'Asing');
        });
    }

    /** @return list<list<mixed>> lembar pertama unduhan Excel */
    private function lembar(string $url, User $oleh): array
    {
        $r = $this->actingAs($oleh)->get($url)->assertOk();
        $jalur = tempnam(sys_get_temp_dir(), 'ek').'.xlsx';
        file_put_contents($jalur, $r->streamedContent());

        return \PhpOffice\PhpSpreadsheet\IOFactory::load($jalur)->getActiveSheet()->toArray(null, false, false, false);
    }

    /** Apakah bilah samping Miners pengguna ini memuat alamat itu. */
    private function diMenu(User $u, string $alamat): bool
    {
        $grup = $this->actingAs($u)->get('/miners/dasbor')->viewData('page')['props']['menu']['grup'];

        return collect($grup)->flatMap(fn ($g) => $g['butir'])->contains(fn ($b) => str_ends_with($b['url'], $alamat));
    }
}
