<?php

namespace Tests\Feature;

use App\Models\Miners\{Induksi, InduksiOrang, Pekerja, Permit, PermitBerkas};
use App\Models\User;
use App\Support\Berkas;
use App\Support\Hr\Kelayakan;
use App\Support\Miners\{Jalur, Keadaan, LokasiInduksi};
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Concerns\SiapanMiners;
use Tests\TestCase;

/**
 * Induksi dan Mine Permit mengikuti Safe Track — skenario
 * tests/alur-karyawan.js dan tests/iup-kontraktor.js.
 */
class MinersIndukPermitSafetrackTest extends TestCase
{
    use RefreshDatabase;
    use SiapanMiners;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkan();
    }

    private function pengajuanInduksi(): Induksi
    {
        $this->actingAs($this->pengaju)->post('/miners/induksi', [
            'tanggal' => Waktu::kini()->toDateString(), 'perihal' => 'Permohonan Induksi Karyawan Baru',
            'berkas_permohonan' => $this->dokumenPdf('permohonan.pdf'),
        ])->assertSessionHasNoErrors();

        return Induksi::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    /* ═══════════ induksi ═══════════ */

    public function test_pengajuan_induksi_bernomor_dan_berberkas(): void
    {
        $ind = $this->pengajuanInduksi();

        $this->assertSame('IND000'.$ind->id, $ind->no_registrasi);
        $this->assertNotNull($ind->berkas_permohonan);
        $this->assertSame($this->iup->id, (int) $ind->iup_id);

        $this->post('/miners/induksi', ['tanggal' => Waktu::kini()->toDateString(), 'perihal' => 'X'])
            ->assertSessionHasErrors('berkas_permohonan');
    }

    public function test_calon_peserta_hanya_mcu_disetujui_layak_dan_belum_terdaftar(): void
    {
        $fit     = $this->mcuSelesai($this->mitra, 'Fit');
        $catatan = $this->mcuSelesai($this->mitra, 'Fit With Note');
        $unfit   = $this->mcuSelesai($this->mitra, 'Unfit');
        $belum   = $this->mcuSelesai($this->mitra, 'Belum Dinilai');
        $draf    = $this->mcuSelesai($this->mitra, 'Fit');
        $draf->mcu->forceFill(['status' => 'diperiksa'])->save();

        $ind = $this->pengajuanInduksi();

        $calon = fn () => collect($this->get("/miners/induksi/{$ind->id}")->viewData('page')['props']['calon'])->pluck('id')->all();

        $this->assertContains($fit->id, $calon());
        $this->assertContains($catatan->id, $calon());
        $this->assertNotContains($unfit->id, $calon(), 'Karyawan Unfit ditawarkan.');
        $this->assertNotContains($belum->id, $calon(), 'Karyawan belum dinilai ditawarkan.');
        $this->assertNotContains($draf->id, $calon(), 'Karyawan yang MCU-nya belum disetujui ditawarkan.');

        $this->post("/miners/induksi/{$ind->id}/orang", ['mcu_orang_id' => $fit->id])->assertSessionHasNoErrors();
        $this->assertNotContains($fit->id, $calon(), 'Yang sudah terdaftar ditawarkan lagi.');

        /* Server menolak pendaftaran ganda dan calon yang tidak layak. */
        $this->post("/miners/induksi/{$ind->id}/orang", ['mcu_orang_id' => $fit->id])
            ->assertSessionHasErrors(['mcu_orang_id' => 'Karyawan "'.$fit->nama.'" sudah pernah didaftarkan induksi pada pengajuan ini.']);
        $this->post("/miners/induksi/{$ind->id}/orang", ['mcu_orang_id' => $unfit->id])->assertSessionHasErrors('mcu_orang_id');

        $this->assertSame(1, InduksiOrang::withoutGlobalScopes()->where('induksi_id', $ind->id)->count());
    }

    public function test_daftar_calon_kosong_menyebut_sebabnya(): void
    {
        $this->mcuSelesai($this->mitra, 'Unfit');

        $ind = $this->pengajuanInduksi();

        $this->get("/miners/induksi/{$ind->id}")->assertInertia(fn (AssertableInertia $p) => $p
            ->where('calon', [])
            ->where('sebabKosong', fn ($s) => collect($s)->contains(fn ($k) => str_contains($k, 'bukan Fit / Fit With Note'))));
    }

    public function test_jadwal_dan_kelulusan_diisi_hse_dan_alur_berhenti_di_hse(): void
    {
        $o = $this->mcuSelesai($this->mitra, 'Fit');
        $ind = $this->pengajuanInduksi();
        $this->post("/miners/induksi/{$ind->id}/orang", ['mcu_orang_id' => $o->id]);
        $peserta = InduksiOrang::withoutGlobalScopes()->where('induksi_id', $ind->id)->firstOrFail();

        $this->post("/miners/induksi/{$ind->id}/kirim")->assertSessionHasNoErrors();
        $this->assertSame(['pengaju', 'ohse'], $ind->fresh()->alur->pluck('peran')->all(), 'Alur induksi bukan dua langkah.');

        $isi = ['peserta' => [['id' => $peserta->id, 'tanggal_induksi' => '2026-03-20', 'lokasi' => 'Office PT Tambang Uji', 'status' => 'lulus']]];

        $this->post("/miners/induksi/{$ind->id}/jadwal", $isi)->assertForbidden();
        $this->actingAs($this->ohse)->post("/miners/induksi/{$ind->id}/jadwal", $isi)->assertSessionHasNoErrors();

        $peserta->refresh();
        $this->assertSame('2026-03-20', $peserta->tanggal_induksi->toDateString());
        $this->assertSame('Office PT Tambang Uji', $peserta->lokasi);
        $this->assertSame('lulus', $peserta->status);

        $this->post("/miners/induksi/{$ind->id}/tindak", ['keadaan' => 'setuju'])->assertSessionHasNoErrors();
        $this->assertSame('selesai', $ind->fresh()->status, 'Induksi menunggu langkah ketiga yang tidak ada.');
    }

    public function test_lokasi_induksi_kantor_iup_penyelenggara(): void
    {
        $this->actingAs($this->pengaju);

        $this->assertSame(['Office PT Tambang Uji'], LokasiInduksi::pilihan($this->iup->id));
        $this->assertContains('Sport Hall', LokasiInduksi::pilihan($this->iup->id, 'Sport Hall'), 'Nilai lama tidak ditawarkan lagi.');
    }

    public function test_sertifikat_induksi_hanya_sesudah_disetujui_dan_tidak_gagal(): void
    {
        $o = $this->mcuSelesai($this->mitra, 'Fit');

        $belum = $this->induksiSelesai($o, 'lulus', 'ohse');
        $this->actingAs($this->pengaju)->get("/miners/induksi/peserta/{$belum->id}/sertifikat")
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Print/SertifikatInduksi')->where('boleh', false));

        $gagal = $this->induksiSelesai($this->mcuSelesai($this->mitra, 'Fit'), 'gagal');
        $this->get("/miners/induksi/peserta/{$gagal->id}/sertifikat")
            ->assertInertia(fn (AssertableInertia $p) => $p->where('boleh', false)
                ->where('alasan', fn ($a) => str_contains($a, 'TIDAK LULUS')));

        $lulus = $this->induksiSelesai($this->mcuSelesai($this->mitra, 'Fit'));
        $bulan = $lulus->tanggal_induksi->format('m/Y');

        $this->get("/miners/induksi/peserta/{$lulus->id}/sertifikat")
            ->assertInertia(fn (AssertableInertia $p) => $p->where('boleh', true)
                ->where('s.nomor', sprintf('IND/TBU/%04d/%s', $lulus->id, $bulan))
                ->where('s.namaIup', 'PT TAMBANG UJI')
                ->where('s.perusahaan', 'PT MITRA UJI')
                ->where('s.qr', fn ($q) => str_contains($q, 'ID'.$lulus->id.'.')));
    }

    public function test_keadaan_induksi_mengikuti_persetujuan_dan_kelulusan(): void
    {
        $o = $this->mcuSelesai($this->mitra, 'Fit');

        $this->assertSame(Keadaan::BELUM, Keadaan::induksi($this->induksiSelesai($o, 'lulus', 'ohse')));
        $this->assertSame(Keadaan::BELUM, Keadaan::induksi($this->induksiSelesai($o, 'gagal')));
        $this->assertSame(Keadaan::BERLAKU, Keadaan::induksi($this->induksiSelesai($o, 'lulus')));
        $this->assertSame(Keadaan::BERLAKU, Keadaan::induksi($this->induksiSelesai($o, 'belum')),
            'Peserta yang belum dinilai pada pendaftaran yang disetujui diloloskan Safe Track.');
    }

    /* ═══════════ Mine Permit ═══════════ */

    public function test_calon_permit_induksi_disetujui_tidak_gagal_belum_berkartu(): void
    {
        $lulus = $this->induksiSelesai($this->mcuSelesai($this->mitra, 'Fit'));
        $gagal = $this->induksiSelesai($this->mcuSelesai($this->mitra, 'Fit'), 'gagal');
        $draf  = $this->induksiSelesai($this->mcuSelesai($this->mitra, 'Fit'), 'lulus', 'ohse');

        $calon = fn () => collect($this->actingAs($this->pengaju)->get('/miners/permit')->viewData('page')['props']['calon'])->pluck('id')->all();

        $this->assertContains($lulus->id, $calon());
        $this->assertNotContains($gagal->id, $calon(), 'Yang Tidak Lulus ditawarkan.');
        $this->assertNotContains($draf->id, $calon(), 'Induksi yang belum disetujui ditawarkan.');

        $this->post('/miners/permit', $this->isianPermit($lulus))->assertSessionHasNoErrors();
        $this->assertNotContains($lulus->id, $calon(), 'Yang kartunya sudah dibuat ditawarkan lagi.');
    }

    public function test_permit_baru_bernomor_ikut_mcu_dan_dimiliki_pemberi_kerja(): void
    {
        $o  = $this->mcuSelesai($this->mitra, 'Fit', '2027-02-01');
        $io = $this->induksiSelesai($o);

        $this->actingAs($this->pengaju)->post('/miners/permit', $this->isianPermit($io))->assertSessionHasNoErrors();

        $p = Permit::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->assertSame('TBU.MTU.'.$p->tanggal->format('Ymd').'00'.$p->id, $p->no_registrasi);
        $this->assertSame($p->no_registrasi, Pekerja::withoutGlobalScopes()->find($p->pekerja_id)->no_induk, 'Nomor induk tidak sama dengan nomor Mine Permit.');
        $this->assertSame('2027-02-01', $p->berlaku_sampai->toDateString());
        $this->assertSame('mcu', $p->sumber_berlaku);
        $this->assertSame($this->mitra->id, (int) $p->company_id);
        $this->assertSame($this->iup->id, (int) $p->iup_id);
        $this->assertNotNull($p->foto);
        $this->assertNotNull($p->berkas_ktp);
        $this->assertSame('1990-05-01', Pekerja::withoutGlobalScopes()->find($p->pekerja_id)->tanggal_lahir->toDateString());
    }

    public function test_karyawan_iup_tanpa_contractor_bernomor_lebih_pendek(): void
    {
        $o  = $this->mcuSelesai($this->iup, 'Fit');
        $io = $this->induksiSelesai($o);

        $this->actingAs($this->ohse)->post('/miners/permit', $this->isianPermit($io, ['kontraktor_id' => $this->mitra->id]))
            ->assertSessionHasErrors(['kontraktor_id' => 'Karyawan ini tercatat sebagai karyawan PT Tambang Uji (IUP), jadi Contractor harus dikosongkan.']);

        $this->post('/miners/permit', $this->isianPermit($io, ['kontraktor_id' => '']))->assertSessionHasNoErrors();

        $p = Permit::withoutGlobalScopes()->latest('id')->firstOrFail();
        $this->assertSame('TBU.'.$p->tanggal->format('Ymd').'00'.$p->id, $p->no_registrasi);
        $this->assertNull($p->kontraktor_id);
    }

    public function test_pasangan_iup_dan_contractor_diperiksa(): void
    {
        $io = $this->induksiSelesai($this->mcuSelesai($this->mitra, 'Fit'));
        $this->actingAs($this->admin);

        /* Perusahaan harus IUP. */
        $this->post('/miners/permit', $this->isianPermit($io, ['iup_id' => $this->mitra->id]))->assertSessionHasErrors('iup_id');

        /* Contractor wajib bagi karyawan mitra. */
        $this->post('/miners/permit', $this->isianPermit($io, ['kontraktor_id' => '']))->assertSessionHasErrors('kontraktor_id');

        /* Contractor harus perusahaan karyawannya. */
        $lain = \App\Models\Company::create(['name' => 'PT Mitra Lain', 'code' => 'MLN', 'izin_type' => 'IUJP', 'parent_id' => $this->iup->id]);
        $this->post('/miners/permit', $this->isianPermit($io, ['kontraktor_id' => $lain->id]))->assertSessionHasErrors('kontraktor_id');

        /* Contractor harus mitra IUP yang dipilih. */
        $iupLain = \App\Models\Company::create(['name' => 'PT Tambang Lain', 'code' => 'TBL', 'izin_type' => 'IUP']);
        $this->post('/miners/permit', $this->isianPermit($io, ['iup_id' => $iupLain->id]))->assertSessionHasErrors('kontraktor_id');

        $this->assertSame(0, Permit::withoutGlobalScopes()->count());
    }

    public function test_kirim_menuntut_spdk_dan_alur_pengaju_hse_ktt(): void
    {
        $io = $this->induksiSelesai($this->mcuSelesai($this->mitra, 'Fit'));
        $this->actingAs($this->pengaju)->post('/miners/permit', $this->isianPermit($io));
        $p = Permit::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->post("/miners/permit/{$p->id}/kirim")->assertSessionHasErrors('kirim');

        /* Induksi Khusus saja tidak cukup; SPDK wajib. */
        $this->post("/miners/permit/{$p->id}/berkas", ['induksi-khusus' => $this->dokumenPdf()])->assertSessionHasNoErrors();
        $this->post("/miners/permit/{$p->id}/kirim")->assertSessionHasErrors('kirim');

        $this->post("/miners/permit/{$p->id}/berkas", ['spdk' => $this->dokumenPdf('spdk.pdf')])->assertSessionHasNoErrors();
        $this->post("/miners/permit/{$p->id}/kirim")->assertSessionHasNoErrors();

        $this->assertSame(['pengaju', 'ohse', 'ktt'], $p->fresh()->alur->pluck('peran')->all());
        $this->actingAs($this->ohse)->post("/miners/permit/{$p->id}/tindak", ['keadaan' => 'setuju'])->assertSessionHasNoErrors();
        $this->assertSame('ktt', $p->fresh()->status);
        $this->actingAs($this->ktt)->post("/miners/permit/{$p->id}/tindak", ['keadaan' => 'setuju'])->assertSessionHasNoErrors();
        $this->assertSame('terbit', $p->fresh()->status);
    }

    public function test_edit_hanya_admin_foto_oleh_pemiliknya(): void
    {
        $p = $this->permitTerbit();

        $this->actingAs($this->pengaju)->put("/miners/permit/{$p->id}", [
            'iup_id' => $this->iup->id, 'kontraktor_id' => $this->mitra->id, 'jenis_kartu' => 'Blasting', 'departemen_id' => $this->departemen(),
        ])->assertForbidden();

        $this->actingAs($this->ohse)->put("/miners/permit/{$p->id}", [
            'iup_id' => $this->iup->id, 'kontraktor_id' => $this->mitra->id, 'jenis_kartu' => 'Blasting', 'departemen_id' => $this->departemen(),
        ])->assertSessionHasNoErrors();

        $this->assertSame('Blasting', $p->fresh()->jenis_kartu);
        $this->assertSame('FULL', $p->fresh()->cakupan_area, 'Cakupan area yang tidak dikirim tertimpa.');
        $this->assertStringStartsWith('TBU.MTU.', $p->fresh()->no_registrasi);

        /* Foto: pemiliknya boleh, orang lain tidak, hanya JPG/PNG. */
        $this->actingAs(User::factory()->create(['company_id' => $this->mitra->id]))
            ->post("/miners/permit/{$p->id}/foto", ['foto' => UploadedFile::fake()->image('a.jpg')])->assertForbidden();
        $this->actingAs($this->pengaju)
            ->post("/miners/permit/{$p->id}/foto", ['foto' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('foto');
        $this->post("/miners/permit/{$p->id}/foto", ['foto' => UploadedFile::fake()->image('baru.png')])->assertSessionHasNoErrors();
        $this->assertNotNull($p->fresh()->foto);
    }

    public function test_nonaktif_beralasan_oleh_admin(): void
    {
        $p = $this->permitTerbit();

        $this->actingAs($this->pengaju)->post("/miners/permit/{$p->id}/status", ['aktif' => 0, 'alasan' => 'x'])->assertForbidden();

        $this->actingAs($this->ohse)->post("/miners/permit/{$p->id}/status", ['aktif' => 0, 'alasan' => 'Kartu hilang'])->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertFalse($p->aktif);
        $this->assertSame('Kartu hilang', $p->alasan_nonaktif);
        $this->assertSame('terbit', $p->status, 'Status alur ikut diubah.');
        $this->assertSame(Keadaan::HABIS, Keadaan::permit($p));

        $this->post("/miners/permit/{$p->id}/status", ['aktif' => 1]);
        $this->assertNull($p->fresh()->alasan_nonaktif);
    }

    public function test_masa_berlaku_ikut_mcu_kunci_manual_dan_sinkron(): void
    {
        $o = $this->mcuSelesai($this->mitra, 'Fit', '2027-04-01');
        $p = $this->permitTerbit($o);

        $this->actingAs($this->ohse)->post("/miners/permit/{$p->id}/masa-berlaku", ['sumber' => 'manual', 'tanggal' => '2027-12-31'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2027-12-31', $p->fresh()->habisEfektif()->toDateString());

        /* Sinkron tidak menyentuh kunci manual. */
        $o->update(['berlaku_sampai' => '2027-06-01']);
        $this->post('/miners/permit/sinkron')->assertSessionHasNoErrors();
        $this->assertSame('2027-12-31', $p->fresh()->berlaku_sampai->toDateString());

        $this->post("/miners/permit/{$p->id}/masa-berlaku", ['sumber' => 'mcu'])->assertSessionHasNoErrors();
        $this->assertSame('2027-06-01', $p->fresh()->berlaku_sampai->toDateString());
        $this->assertSame('mcu', $p->fresh()->sumber_berlaku);

        $this->actingAs($this->pengaju)->post("/miners/permit/{$p->id}/masa-berlaku", ['sumber' => 'mcu'])->assertForbidden();
    }

    public function test_kelayakan_hr_membaca_permit_yang_ikut_mcu(): void
    {
        $o = $this->mcuSelesai($this->mitra, 'Fit', Waktu::kini()->addDays(10)->toDateString());
        $p = $this->permitTerbit($o);

        /* Tanggal tersimpan masih lama (belum disinkron malam ini); yang
           dibaca tetap tanggal MCU-nya. */
        $p->forceFill(['berlaku_sampai' => Waktu::kini()->addYear()->toDateString()])->save();

        $pk = Pekerja::withoutGlobalScopes()->with(Kelayakan::relasi())->find($p->pekerja_id);

        $this->assertNull(Kelayakan::periksa($pk, Waktu::kini())['halangan']);
        $this->assertSame(Keadaan::HABIS, Kelayakan::periksa($pk, Waktu::kini()->addDays(20))['keadaan']['permit'],
            'Mine Permit masih terbaca berlaku sesudah MCU-nya habis.');

        /* Kartu yang dikunci manual dibaca dari kuncinya. */
        $p->update(['sumber_berlaku' => 'manual']);
        $pk = Pekerja::withoutGlobalScopes()->with(Kelayakan::relasi())->find($p->pekerja_id);
        $this->assertSame(Keadaan::BERLAKU, Kelayakan::periksa($pk, Waktu::kini()->addDays(20))['keadaan']['permit']);
        $this->assertSame('mcu', Kelayakan::periksa($pk, Waktu::kini()->addDays(20))['halangan']);
    }

    public function test_layar_permit_dan_berkasnya(): void
    {
        $p = $this->permitTerbit();
        $this->lampiranSpdk($p);

        $this->actingAs($this->admin)->get('/miners/riwayat/mine-permit')->assertOk()
            ->assertInertia(fn (AssertableInertia $h) => $h->component('Miners/Permit')->where('mode', 'riwayat')
                ->where('ringkas.total', 1)->where('rekap.0.perusahaan', 'PT Mitra Uji'));

        $this->get('/miners/permit/ekspor?mode=riwayat')->assertOk();

        $this->get("/miners/permit/{$p->id}")->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Miners/PermitRincian')->where('permit.sumber', 'ikut MCU')->where('kurang', []));

        $this->assertSame(['isAdmin', 'isOhse'], Berkas::GERBANG['mnt']);
        $this->assertArrayNotHasKey('mnf', Berkas::GERBANG, 'Foto kartu tidak perlu gerbang peran.');
        $this->assertFalse(Berkas::bolehMembuka($this->pengaju, 'mnt'));
    }
}
