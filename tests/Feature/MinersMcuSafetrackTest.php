<?php

namespace Tests\Feature;

use App\Models\Miners\{HasilMcu, Mcu, McuOrang, McuRujukan, Pekerja, Permit};
use App\Models\User;
use App\Notifications\PemberitahuanMiners;
use App\Support\Berkas;
use App\Support\Miners\{Jalur, SinkronMasaBerlaku, StempelValidasi};
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Concerns\SiapanMiners;
use Tests\TestCase;

/**
 * MCU mengikuti Safe Track — skenario tests/mcu-manpower.js dan aturan
 * mcusController / mcu_detailsController / rujukan_formsController.
 */
class MinersMcuSafetrackTest extends TestCase
{
    use RefreshDatabase;
    use SiapanMiners;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkan();
    }

    /* ═══════════ surat & manpower ═══════════ */

    public function test_surat_baru_bernomor_dan_milik_mitra_pembuatnya(): void
    {
        $mcu = $this->suratMcu();

        $this->assertSame('MCU00'.$mcu->id, $mcu->no_registrasi);
        $this->assertSame($this->mitra->id, (int) $mcu->company_id);
        $this->assertSame($this->iup->id, (int) $mcu->iup_id, 'IUP penerbit tidak dibaca dari induk mitra.');

        /* Mitra tidak dapat menulis MCU atas nama IUP — perusahaanKaryawan(). */
        $this->actingAs($this->pengaju)->post('/miners/mcu', [
            'tanggal' => '2026-03-10', 'kepada' => 'Dokter', 'company_id' => $this->iup->id,
        ]);
        $this->assertSame($this->mitra->id, (int) Mcu::withoutGlobalScopes()->latest('id')->value('company_id'));
    }

    public function test_tambah_manpower_membuat_dan_menautkan_pekerja_menurut_nik(): void
    {
        $mcu = $this->suratMcu();

        $this->post("/miners/mcu/{$mcu->id}/orang", $this->isianManpower(['nama' => 'Budi Uji', 'nik' => '6401000000000001']))
            ->assertSessionHasNoErrors();

        $o = McuOrang::withoutGlobalScopes()->where('mcu_id', $mcu->id)->firstOrFail();
        $pk = Pekerja::withoutGlobalScopes()->findOrFail($o->pekerja_id);

        $this->assertSame('6401000000000001', $pk->nik);
        $this->assertSame($this->mitra->id, (int) $pk->company_id);
        $this->assertSame('MCU-'.substr('6401000000000001', -10), $o->no_id, 'Nomor induk sementara bukan MCU-<10 angka NIK>.');
        $this->assertSame(HasilMcu::belumDinilai(), (int) $o->hasil_id, 'Manpower baru tidak "Belum Dinilai".');
        $this->assertSame('2027-03-10', $o->berlaku_sampai->toDateString(), 'Masa berlaku bawaan bukan tanggal MCU + 1 tahun.');
        $this->assertNotNull($o->berkas_hasil);

        /* Manpower kedua pada surat yang sama. */
        $this->post("/miners/mcu/{$mcu->id}/orang", $this->isianManpower())->assertSessionHasNoErrors();
        $this->assertSame(2, McuOrang::withoutGlobalScopes()->where('mcu_id', $mcu->id)->count());

        /* NIK yang sama pada surat yang sama ditolak. */
        $this->post("/miners/mcu/{$mcu->id}/orang", $this->isianManpower(['nik' => '6401000000000001']))
            ->assertSessionHasErrors('nik');

        /* NIK yang sama pada surat lain menautkan manpower yang sama. */
        $kedua = $this->suratMcu();
        $this->post("/miners/mcu/{$kedua->id}/orang", $this->isianManpower(['nik' => '6401000000000001']))->assertSessionHasNoErrors();
        $this->assertSame($pk->id, (int) McuOrang::withoutGlobalScopes()->where('mcu_id', $kedua->id)->value('pekerja_id'));
        $this->assertSame(1, Pekerja::withoutGlobalScopes()->where('nik', '6401000000000001')->count());
    }

    public function test_tambah_manpower_tanpa_berkas_hasil_ditolak_tanpa_menyimpan(): void
    {
        $mcu = $this->suratMcu();

        $isian = $this->isianManpower();
        unset($isian['berkas_hasil']);

        $this->post("/miners/mcu/{$mcu->id}/orang", $isian)->assertSessionHasErrors('berkas_hasil');
        $this->assertSame(0, McuOrang::withoutGlobalScopes()->where('mcu_id', $mcu->id)->count());
    }

    public function test_nik_dengan_mine_permit_aktif_ditolak(): void
    {
        $permit = $this->permitTerbit();
        $nik = Pekerja::withoutGlobalScopes()->find($permit->pekerja_id)->nik;

        $mcu = $this->suratMcu();

        $this->post("/miners/mcu/{$mcu->id}/orang", $this->isianManpower(['nik' => $nik]))
            ->assertSessionHasErrors(['nik' => 'Karyawan dengan NIK ini sudah punya Mine Permit/SIMPER aktif. Gunakan menu Perpanjangan, bukan tambah MCU, supaya datanya tetap tersambung ke dokumen yang sudah ada.']);

        /* Kartu yang sudah dinonaktifkan tidak menahan MCU baru. */
        $permit->update(['aktif' => false]);
        $this->post("/miners/mcu/{$mcu->id}/orang", $this->isianManpower(['nik' => $nik]))->assertSessionHasNoErrors();
    }

    public function test_hanya_pengaju_draf_yang_menambah_manpower(): void
    {
        $mcu = $this->suratMcu();

        $this->actingAs(User::factory()->create(['company_id' => $this->mitra->id]))
            ->post("/miners/mcu/{$mcu->id}/orang", $this->isianManpower())->assertForbidden();
    }

    /* ═══════════ persetujuan dokter ═══════════ */

    public function test_kirim_menuntut_manpower(): void
    {
        $mcu = $this->suratMcu();

        $this->post("/miners/mcu/{$mcu->id}/kirim")->assertSessionHasErrors('kirim');

        $this->post("/miners/mcu/{$mcu->id}/orang", $this->isianManpower())->assertSessionHasNoErrors();
        $this->post("/miners/mcu/{$mcu->id}/kirim")->assertSessionHasNoErrors();

        $this->assertSame('diperiksa', $mcu->fresh()->status);
    }

    public function test_belum_dinilai_menahan_persetujuan_dan_dokter_memilih_masa_berlaku(): void
    {
        $mcu = $this->suratMcu();
        $this->post("/miners/mcu/{$mcu->id}/orang", $this->isianManpower(['tanggal_periksa' => '2026-03-10']));
        $this->post("/miners/mcu/{$mcu->id}/orang", $this->isianManpower(['tanggal_periksa' => '2026-03-12']));
        $this->post("/miners/mcu/{$mcu->id}/kirim")->assertSessionHasNoErrors();

        [$a, $b] = McuOrang::withoutGlobalScopes()->where('mcu_id', $mcu->id)->orderBy('id')->get()->all();

        /* Pengaju tidak menilai. */
        $this->post("/miners/mcu/{$mcu->id}/orang/{$a->id}/hasil", ['hasil_id' => $this->hasil('Fit')])->assertForbidden();

        /* Dokter menyetujui tanpa memilih masa berlaku: ditolak. */
        $this->actingAs($this->dokter)->post("/miners/mcu/{$mcu->id}/tindak", ['keadaan' => 'setuju'])
            ->assertSessionHasErrors('bulan_berlaku');

        /* Masih ada "Belum Dinilai": ditahan, dengan alasannya. */
        $this->post("/miners/mcu/{$mcu->id}/orang/{$a->id}/hasil", ['hasil_id' => $this->hasil('Fit')])->assertSessionHasNoErrors();
        $this->post("/miners/mcu/{$mcu->id}/tindak", ['keadaan' => 'setuju', 'bulan_berlaku' => 6])
            ->assertSessionHasErrors(['keadaan' => '1 manpower belum ditetapkan hasil MCU-nya. Tetapkan hasil tiap nama lebih dulu; sesudah semuanya terisi, surat ini dapat disetujui.']);
        $this->assertSame('diperiksa', $mcu->fresh()->status);

        $this->post("/miners/mcu/{$mcu->id}/orang/{$b->id}/hasil", ['hasil_id' => $this->hasil('Fit With Note')]);
        $this->post("/miners/mcu/{$mcu->id}/tindak", ['keadaan' => 'setuju', 'bulan_berlaku' => 6])->assertSessionHasNoErrors();

        $this->assertSame('selesai', $mcu->fresh()->status);
        $this->assertSame(6, (int) $mcu->fresh()->bulan_berlaku);
        $this->assertSame('2026-09-10', $a->fresh()->berlaku_sampai->toDateString());
        $this->assertSame('2026-09-12', $b->fresh()->berlaku_sampai->toDateString());
        $this->assertSame(6, (int) $b->fresh()->bulan_berlaku);
    }

    public function test_bulan_berlaku_hanya_3_6_atau_12(): void
    {
        $mcu = $this->suratMcu();
        $this->post("/miners/mcu/{$mcu->id}/orang", $this->isianManpower());
        $this->post("/miners/mcu/{$mcu->id}/kirim");

        $this->actingAs($this->dokter)->post("/miners/mcu/{$mcu->id}/tindak", ['keadaan' => 'setuju', 'bulan_berlaku' => 24])
            ->assertSessionHasErrors('bulan_berlaku');
    }

    public function test_ditolak_lalu_diajukan_ulang_mengembalikan_hasil_belum_dinilai(): void
    {
        $mcu = $this->suratMcu();
        $this->post("/miners/mcu/{$mcu->id}/orang", $this->isianManpower());
        $this->post("/miners/mcu/{$mcu->id}/kirim");
        $o = McuOrang::withoutGlobalScopes()->where('mcu_id', $mcu->id)->firstOrFail();

        $this->actingAs($this->dokter)->post("/miners/mcu/{$mcu->id}/orang/{$o->id}/hasil", ['hasil_id' => $this->hasil('Unfit')]);
        $this->post("/miners/mcu/{$mcu->id}/tindak", ['keadaan' => 'tolak'])->assertSessionHasErrors('keadaan');
        $this->post("/miners/mcu/{$mcu->id}/tindak", ['keadaan' => 'tolak', 'catatan' => 'Berkas hasil buram.'])->assertSessionHasNoErrors();
        $this->assertSame('ditolak', $mcu->fresh()->status);

        $this->actingAs($this->pengaju)->post("/miners/mcu/{$mcu->id}/ajukan-ulang")->assertSessionHasNoErrors();

        $this->assertSame('diperiksa', $mcu->fresh()->status);
        $this->assertSame(HasilMcu::belumDinilai(), (int) $o->fresh()->hasil_id, 'Hasil lama terbawa ke pengajuan ulang.');
    }

    /* ═══════════ kembalikan per orang ═══════════ */

    public function test_kembalikan_satu_nama_untuk_diperbaiki(): void
    {
        Notification::fake();

        $mcu = $this->suratMcu();
        $this->post("/miners/mcu/{$mcu->id}/orang", $this->isianManpower(['nama' => 'Salah Unggah']));
        $this->post("/miners/mcu/{$mcu->id}/orang", $this->isianManpower(['nama' => 'Benar']));
        $this->post("/miners/mcu/{$mcu->id}/kirim");
        [$salah, $benar] = McuOrang::withoutGlobalScopes()->where('mcu_id', $mcu->id)->orderBy('id')->get()->all();

        /* Pengaju belum boleh menyunting sesudah dikirim. */
        $this->put("/miners/mcu/{$mcu->id}/orang/{$salah->id}", $this->isianManpower(['nama' => 'X']))->assertForbidden();

        /* Hanya yang sedang giliran. */
        $this->actingAs($this->ohse)->post("/miners/mcu/{$mcu->id}/orang/{$salah->id}/perbaiki", ['catatan_perbaikan' => 'x'])->assertForbidden();

        $this->actingAs($this->dokter)->post("/miners/mcu/{$mcu->id}/orang/{$salah->id}/perbaiki", ['catatan_perbaikan' => ''])
            ->assertSessionHasErrors('catatan_perbaikan');
        $this->post("/miners/mcu/{$mcu->id}/orang/{$salah->id}/perbaiki", ['catatan_perbaikan' => 'Berkas hasil tertukar.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Berkas hasil tertukar.', $salah->fresh()->catatan_perbaikan);
        $this->assertNull($benar->fresh()->catatan_perbaikan, 'Nama lain ikut tertunda.');
        $this->assertSame('diperiksa', $mcu->fresh()->status, 'Seluruh surat ikut dikembalikan.');
        $this->assertSame(HasilMcu::belumDinilai(), (int) $salah->fresh()->hasil_id, 'Hasil medis disentuh.');
        Notification::assertSentTo($this->pengaju, PemberitahuanMiners::class);

        /* Pengaju memperbaiki barisnya; tandanya hilang. */
        $this->actingAs($this->pengaju)
            ->put("/miners/mcu/{$mcu->id}/orang/{$salah->id}", $this->isianManpower(['nama' => 'Salah Unggah', 'nik' => $salah->nik]))
            ->assertSessionHasNoErrors();
        $this->assertNull($salah->fresh()->catatan_perbaikan);
    }

    /* ═══════════ tanggal surat ═══════════ */

    public function test_tanggal_surat_berubah_menggeser_masa_berlaku_bawaan_saja(): void
    {
        $mcu = $this->suratMcu();
        $this->post("/miners/mcu/{$mcu->id}/orang", $this->isianManpower(['tanggal_periksa' => '2026-03-10']));
        $this->post("/miners/mcu/{$mcu->id}/orang", $this->isianManpower(['tanggal_periksa' => '2026-03-10']));
        [$bawaan, $khusus] = McuOrang::withoutGlobalScopes()->where('mcu_id', $mcu->id)->orderBy('id')->get()->all();
        $khusus->update(['berlaku_sampai' => '2026-09-10']);

        $this->put("/miners/mcu/{$mcu->id}", ['tanggal' => '2026-03-15', 'kepada' => 'Dokter Uji'])->assertSessionHasNoErrors();

        $this->assertSame('2027-03-15', $bawaan->fresh()->berlaku_sampai->toDateString());
        $this->assertSame('2026-09-10', $khusus->fresh()->berlaku_sampai->toDateString(), 'Masa berlaku yang ditetapkan tersendiri ikut tertimpa.');
    }

    /* ═══════════ rujukan ═══════════ */

    public function test_rujukan_dibuat_dokter_menyurati_ohse_dan_suratnya_tercetak(): void
    {
        Notification::fake();
        config(['miners.surel.ohse' => 'ohse@contoh.test']);

        $o = $this->mcuSelesai($this->mitra, 'Rujukan');
        $mcu = $o->mcu;

        $isi = ['tanggal_surat' => '2026-03-20', 'dokter' => 'Sp.PD', 'rumah_sakit' => 'RS Uji', 'poliklinik' => 'Penyakit Dalam',
                'diagnosis_awal' => 'Hipertensi', 'keterangan' => 'Lab lengkap'];

        $this->actingAs($this->pengaju)->post("/miners/mcu/{$mcu->id}/orang/{$o->id}/rujukan", $isi)->assertForbidden();

        $this->actingAs($this->dokter)->post("/miners/mcu/{$mcu->id}/orang/{$o->id}/rujukan", $isi)->assertSessionHasNoErrors();

        $j = McuRujukan::withoutGlobalScopes()->where('mcu_orang_id', $o->id)->firstOrFail();
        $this->assertSame($this->dokter->id, (int) $j->user_id);

        Notification::assertSentOnDemand(PemberitahuanMiners::class,
            fn ($n, $kanal, AnonymousNotifiable $t) => in_array('ohse@contoh.test', (array) $t->routes['mail'], true));

        /* Surat rujukan adalah isi medis: pemberi kerja pasien (pengaju
           MCU yang bukan paramedis/OHSE) tidak lagi membukanya; dokternya
           membukanya. Kopnya formulir terkendali IUP penerbit — nomornya
           dari pengaturan dokumennya. */
        $this->iup->forceFill(['doc_no_prefix' => 'TBU'])->save();

        $this->actingAs($this->pengaju)->get("/miners/mcu/rujukan/{$j->id}/surat")->assertForbidden();

        $this->actingAs($this->dokter)->get("/miners/mcu/rujukan/{$j->id}/surat")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Print/SuratRujukan')
                ->where('s.nama', $o->nama)->where('s.kop.nomor', 'FRM/TBU/OHSE/026'));

        /* Daftar rujukan memuat orang yang hasilnya "Rujukan". */
        $this->actingAs($this->admin)->get('/miners/daftar/rujukan')->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('baris.0.id', $o->id));
    }

    /* ═══════════ sertifikat & stempel ═══════════ */

    public function test_stempel_validasi_dihitung_dari_datanya(): void
    {
        $sah = $this->mcuSelesai($this->mitra, 'Fit');
        $this->assertSame(StempelValidasi::SAH, StempelValidasi::untuk($sah)['keadaan']);

        $lewat = $this->mcuSelesai($this->mitra, 'Fit', Waktu::kini()->subDay()->toDateString());
        $this->assertSame(StempelValidasi::KEDALUWARSA, StempelValidasi::untuk($lewat)['keadaan']);

        $unfit = $this->mcuSelesai($this->mitra, 'Unfit');
        $this->assertSame(StempelValidasi::TIDAK_LAYAK, StempelValidasi::untuk($unfit)['keadaan']);

        $draf = $this->mcuSelesai($this->mitra, 'Fit');
        $draf->mcu->forceFill(['status' => 'diperiksa'])->save();
        $this->assertSame(StempelValidasi::BELUM, StempelValidasi::untuk($draf->fresh())['keadaan']);
    }

    public function test_sertifikat_mcu_mengikuti_iup_penerbit(): void
    {
        $o = $this->mcuSelesai($this->mitra, 'Fit');
        $o->mcu->forceFill(['iup_id' => $this->iup->id])->save();
        $this->iup->forceFill(['doc_no_prefix' => 'TBU'])->save();

        /* Sertifikat memuat hasil dan catatan dokter — dibuka HSE IUP, bukan
           pengaju yang bukan pembaca isi medis (MinersAksesDataTest). */
        $this->actingAs($this->ohse)->get("/miners/mcu/orang/{$o->id}/sertifikat")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Print/SertifikatMcu')
                ->where('s.boleh', true)
                ->where('s.kop.nomor', 'FRM/TBU/OHSE/025')
                ->where('s.kop.judul', 'MEDICAL CHECK UP CERTIFICATE')
                ->where('s.centang', 'fit')
                ->where('s.stempel.judul', 'TERVALIDASI')
                ->where('s.dokter', $this->dokter->name));
    }

    /* ═══════════ sinkron harian ═══════════ */

    public function test_sinkron_menandai_mcu_dan_permit_kedaluwarsa(): void
    {
        $o = $this->mcuSelesai($this->mitra, 'Fit', Waktu::kini()->subDays(3)->toDateString());
        $p = $this->permitTerbit($o);

        $this->artisan('miners:sinkron')->assertSuccessful();

        $this->assertSame('kedaluwarsa', $o->mcu->fresh()->status);
        $this->assertSame('kedaluwarsa', $p->fresh()->status);

        /* MCU diperpanjang: keduanya kembali. */
        $o->update(['berlaku_sampai' => Waktu::kini()->addYear()->toDateString()]);
        SinkronMasaBerlaku::jalankan();

        $this->assertSame('selesai', $o->mcu->fresh()->status);
        $this->assertSame('terbit', $p->fresh()->status);
        $this->assertSame(Waktu::kini()->addYear()->toDateString(), $p->fresh()->berlaku_sampai->toDateString());
    }

    public function test_dijadwalkan_setiap_pagi(): void
    {
        $jadwal = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'miners:sinkron'));

        $this->assertNotNull($jadwal, 'miners:sinkron tidak dijadwalkan.');
        $this->assertSame('55 6 * * *', $jadwal->expression);
    }

    /* ═══════════ laporan & layar ═══════════ */

    public function test_laporan_excel_dan_layar_daftar(): void
    {
        $mcu = $this->suratMcu();
        $this->post("/miners/mcu/{$mcu->id}/orang", $this->isianManpower());

        $this->get('/miners/mcu/ekspor')->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->get('/miners/mcu')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Miners/Mcu')->where('mode', 'register')->where('kartu.manpower', 1)
            ->where('baris.data.0.nomor', $mcu->no_registrasi));

        $this->actingAs($this->admin)->get('/miners/riwayat/mcu')->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Miners/Mcu')->where('mode', 'riwayat'));

        $this->actingAs($this->pengaju)->get("/miners/mcu/{$mcu->id}")->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Miners/McuRincian')
                ->where('bolehTambah', true)->where('orang.0.bolehNilai', false));
    }

    /* ═══════════ tinjauan harian ═══════════ */

    public function test_tinjauan_harian_mcu_menurut_tanggal_pengajuan(): void
    {
        $mcu = $this->suratMcu();
        $this->post("/miners/mcu/{$mcu->id}/orang", $this->isianManpower(['nama' => 'Hari Ini']));
        $this->post("/miners/mcu/{$mcu->id}/kirim");

        $this->actingAs($this->admin)->get('/miners/dasbor')->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('tinjauan.tanggal', Waktu::kini()->toDateString())
                ->where('tinjauan.diajukan', 1)->where('tinjauan.menunggu', 1)
                ->where('tinjauan.orang.0.nama', 'Hari Ini'));

        $this->get('/miners/dasbor?tgl_tinjau='.Waktu::kini()->subDays(3)->toDateString())
            ->assertInertia(fn (AssertableInertia $p) => $p->where('tinjauan.diajukan', 0));
    }

    /* ═══════════ berkas ═══════════ */

    public function test_berkas_rujukan_manpower_dijaga_seketat_berkas_medis(): void
    {
        $this->assertSame(['isAdmin', 'isOhse', 'isParamedis'], Berkas::GERBANG['mnr']);
        $this->assertFalse(Berkas::bolehMembuka($this->pengaju, 'mnr'));
        $this->assertTrue(Berkas::bolehMembuka($this->dokter, 'mnr'));
    }
}
