<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Miners\{Alur, HasilMcu, Induksi, InduksiOrang, JenisUnit, Kendaraan,
    Mcu, McuOrang, Pekerja, Permit, Simper, SimperAjuan, SimperUnit, TipePermit};
use App\Models\User;
use App\Support\Miners\{Jalur, MasterMiners};
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Alur persetujuan dan aturan SOP modul Miners.
 *
 * Yang diuji di sini adalah aturan yang HANYA berlaku bila ditegakkan
 * di server. Tombol dapat disembunyikan; permintaannya tetap dapat
 * dikirim. Tiga di antaranya justru yang paling sering dilanggar pada
 * aplikasi asalnya:
 *
 *   · Mine Permit terbit tanpa pengesahan KTT
 *   · Pengaju menyetujui pengajuannya sendiri
 *   · Unit ditambahkan ke SIMPER yang kelas SIM-nya tidak cocok
 */
class MinersAlurTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $pengaju;
    private User $ohse;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        MasterMiners::pasang();

        $this->c       = Company::create(['name' => 'PT Uji Miners']);
        $this->pengaju = User::factory()->create(['company_id' => $this->c->id]);
        $this->ohse    = User::factory()->create(['company_id' => $this->c->id, 'ohse_role' => 'ohse']);
        $this->admin   = User::factory()->create(['company_id' => $this->c->id, 'is_admin' => true]);
    }

    private function pekerja(array $ganti = []): Pekerja
    {
        return Pekerja::withoutGlobalScopes()->create($ganti + [
            'company_id' => $this->c->id,
            'nama'       => 'Operator Uji',
            'status'     => 'aktif',
        ]);
    }

    private function permit(array $ganti = []): Permit
    {
        $p = Permit::withoutGlobalScopes()->create($ganti + [
            'company_id' => $this->c->id,
            'pekerja_id' => $this->pekerja()->id,
            'tanggal'    => Waktu::kini()->startOfDay(),
            'user_id'    => $this->pengaju->id,
        ]);

        $p->terbitkanAlur();

        return $p;
    }

    /* ═══════════ halaman terbuka ═══════════ */

    #[Test]
    public function test_tiap_halaman_miners_terbuka(): void
    {
        $this->actingAs($this->admin);

        foreach ([
            '/miners/dasbor', '/miners', '/miners/kedaluwarsa',
            '/miners/mcu', '/miners/induksi', '/miners/permit', '/miners/simper',
            '/miners/riwayat/mcu', '/miners/riwayat/mine-permit', '/miners/riwayat/authority',
            '/miners/daftar/outstanding-permit', '/miners/daftar/perpanjangan',
        ] as $jalur) {
            $this->get($jalur)->assertOk();
        }
    }

    #[Test]
    public function test_halaman_rincian_pekerja_terbuka(): void
    {
        $this->actingAs($this->admin);

        $this->get('/miners/'.$this->pekerja()->id)->assertOk();
    }

    /* ═══════════ alur ═══════════ */

    #[Test]
    public function test_mine_permit_menempuh_tiga_langkah_termasuk_ktt(): void
    {
        $p = $this->permit();

        /* Perbedaan paling penting dari Project1: di sana Mine Permit
           terbit sesudah OHSE saja, tanpa pengesahan Kepala Teknik
           Tambang — orang yang justru bertanggung jawab atas kartu itu
           di hadapan Inspektur Tambang. */
        $this->assertSame(['pjo', 'ohse', 'ktt'], $p->alur->pluck('peran')->all());
    }

    #[Test]
    public function test_permit_tidak_terbit_sebelum_seluruh_langkah_disetujui(): void
    {
        $p = $this->permit();

        Jalur::setujui($p, $this->admin);
        $this->assertNotSame('terbit', $p->fresh()->status, 'Terbit sesudah satu langkah saja.');

        Jalur::setujui($p->fresh(), $this->admin);
        $this->assertNotSame('terbit', $p->fresh()->status, 'Terbit sebelum langkah KTT.');

        Jalur::setujui($p->fresh(), $this->admin);
        $this->assertSame('terbit', $p->fresh()->status);
    }

    #[Test]
    public function test_pengaju_tidak_dapat_menyetujui_pengajuannya_sendiri(): void
    {
        $p = $this->permit();

        $salah = Jalur::setujui($p, $this->pengaju);

        $this->assertNotNull($salah, 'Pengaju berhasil menyetujui pengajuannya sendiri.');
        $this->assertSame('menunggu', $p->fresh()->alur->first()->keadaan);
    }

    #[Test]
    public function test_penolakan_mengembalikan_status_ke_ditolak(): void
    {
        $p = $this->permit();

        Jalur::tolak($p, $this->ohse, 'Lampiran kurang.');

        $this->assertSame('ditolak', $p->fresh()->status);
        $this->assertSame('Lampiran kurang.', $p->fresh()->alur->first()->catatan);
    }

    #[Test]
    public function test_dikembalikan_menjadikannya_draf_lagi(): void
    {
        $p = $this->permit();

        Jalur::kembalikan($p, $this->ohse);

        $this->assertSame('draf', $p->fresh()->status);
    }

    #[Test]
    public function test_mcu_selesai_bukan_terbit(): void
    {
        /* MCU menghasilkan hasil pemeriksaan, bukan kartu. Menyebutnya
           "terbit" membuat daftar kartu berlaku ikut menghitungnya. */
        $m = Mcu::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'tanggal' => Waktu::kini()->startOfDay(),
            'kepada' => 'Klinik', 'user_id' => $this->pengaju->id,
        ]);
        $m->terbitkanAlur();

        foreach (range(1, 3) as $_) Jalur::setujui($m->fresh(), $this->admin);

        $this->assertSame('selesai', $m->fresh()->status);
    }

    #[Test]
    public function test_alur_mcu_lewat_dokter_lebih_dahulu(): void
    {
        $m = Mcu::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'tanggal' => Waktu::kini()->startOfDay(), 'kepada' => 'Klinik',
        ]);
        $m->terbitkanAlur();

        $this->assertSame(['pjo', 'dokter', 'ohse'], $m->alur->pluck('peran')->all());
    }

    /* ═══════════ aturan SOP ═══════════ */

    #[Test]
    public function test_unit_ditolak_bila_kelas_sim_tidak_cocok(): void
    {
        $this->actingAs($this->admin);

        $permit = $this->permit(['status' => 'terbit']);

        $simper = Simper::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'permit_id' => $permit->id,
            'pekerja_id' => $permit->pekerja_id, 'tanggal' => Waktu::kini()->startOfDay(),
            'kelas' => 'F', 'jenis_simpol' => 'A',
        ]);

        /* Dump Truck menuntut SIM B2 Umum menurut matriks SOP; kartu ini
           tercatat SIM A. Pemeriksaannya HARUS di server — yang hanya
           ada di layar dapat dilewati dengan mengirim permintaannya
           langsung, dan yang lolos adalah operator dump truck ber-SIM A. */
        $dt = Kendaraan::withoutGlobalScopes()->where('kunci', 'dump-truck')->firstOrFail();

        $this->post("/miners/simper/{$simper->id}/unit", ['kendaraan_id' => $dt->id])
            ->assertSessionHasErrors('kendaraan_id');

        $this->assertSame(0, SimperUnit::query()->where('simper_id', $simper->id)->count());
    }

    #[Test]
    public function test_unit_diterima_bila_kelas_sim_cocok(): void
    {
        $this->actingAs($this->admin);

        $permit = $this->permit(['status' => 'terbit']);

        $simper = Simper::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'permit_id' => $permit->id,
            'pekerja_id' => $permit->pekerja_id, 'tanggal' => Waktu::kini()->startOfDay(),
            'kelas' => 'F', 'jenis_simpol' => 'B2 Umum',
        ]);

        $dt = Kendaraan::withoutGlobalScopes()->where('kunci', 'dump-truck')->firstOrFail();

        $this->post("/miners/simper/{$simper->id}/unit", ['kendaraan_id' => $dt->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, SimperUnit::query()->where('simper_id', $simper->id)->count());
    }

    #[Test]
    public function test_perpanjangan_ditolak_sebelum_jendelanya_terbuka(): void
    {
        $this->actingAs($this->admin);

        $permit = $this->permit(['status' => 'terbit', 'berlaku_sampai' => Waktu::kini()->addYears(2)]);

        $simper = Simper::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'permit_id' => $permit->id,
            'pekerja_id' => $permit->pekerja_id, 'tanggal' => Waktu::kini()->startOfDay(),
            'kelas' => 'F', 'berlaku_sampai' => Waktu::kini()->addYears(2)->startOfDay(),
        ]);

        /* SOP membuka jendelanya sebulan sebelum berakhir. Di Project1
           ambang itu hanya mewarnai layar pemantauan dan tidak pernah
           membatasi apa pun, sehingga antrean OHSE terisi berkas yang
           belum waktunya sepanjang tahun. */
        $this->post("/miners/simper/{$simper->id}/ajuan", [
            'jenis' => 'perpanjangan', 'tanggal' => Waktu::kini()->toDateString(),
        ])->assertSessionHasErrors('jenis');

        $this->assertSame(0, SimperAjuan::query()->where('simper_id', $simper->id)->count());
    }

    #[Test]
    public function test_perpanjangan_diterima_di_dalam_jendelanya(): void
    {
        $this->actingAs($this->admin);

        $permit = $this->permit(['status' => 'terbit', 'berlaku_sampai' => Waktu::kini()->addDays(10)]);

        $simper = Simper::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'permit_id' => $permit->id,
            'pekerja_id' => $permit->pekerja_id, 'tanggal' => Waktu::kini()->startOfDay(),
            'kelas' => 'F', 'berlaku_sampai' => Waktu::kini()->addDays(10)->startOfDay(),
        ]);

        $this->post("/miners/simper/{$simper->id}/ajuan", [
            'jenis' => 'perpanjangan', 'tanggal' => Waktu::kini()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, SimperAjuan::query()->where('simper_id', $simper->id)->count());
    }

    #[Test]
    public function test_permit_melekatkan_mcu_dan_induksi_terakhir_saat_dibuat(): void
    {
        $this->actingAs($this->admin);

        $pk = $this->pekerja();

        $surat = Mcu::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'tanggal' => Waktu::kini()->startOfDay(), 'kepada' => 'Klinik',
        ]);

        $mcu = McuOrang::create([
            'mcu_id' => $surat->id, 'pekerja_id' => $pk->id, 'nama' => $pk->nama,
            'tanggal_periksa' => Waktu::kini()->subMonths(2)->startOfDay(),
            'berlaku_sampai'  => Waktu::kini()->addMonths(10)->startOfDay(),
            'aktif' => true,
        ]);

        $ind = Induksi::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'tanggal' => Waktu::kini()->startOfDay(),
        ]);

        $io = InduksiOrang::create([
            'induksi_id' => $ind->id, 'pekerja_id' => $pk->id,
            'tanggal_induksi' => Waktu::kini()->startOfDay(), 'nilai' => 90,
            'percobaan' => 1, 'status' => 'lulus',
        ]);

        $this->post('/miners/permit', [
            'pekerja_id' => $pk->id,
            'tanggal'    => Waktu::kini()->toDateString(),
        ])->assertSessionHasNoErrors();

        $p = Permit::withoutGlobalScopes()->where('pekerja_id', $pk->id)->firstOrFail();

        /* Dicari ulang saat dibaca, kartu tahun lalu akan menunjuk MCU
           tahun ini begitu yang baru terbit — dan pertanyaan "hasil MCU
           mana yang menjadi dasar kartu ini" kehilangan jawabannya
           justru pada kartu yang sedang dipersoalkan. */
        $this->assertSame($mcu->id, $p->mcu_orang_id);
        $this->assertSame($io->id, $p->induksi_orang_id);
    }

    #[Test]
    public function test_masa_berlaku_permit_mengikuti_jenisnya(): void
    {
        $this->actingAs($this->admin);

        $visitor = TipePermit::withoutGlobalScopes()->where('kunci', 'visitor-permit')->firstOrFail();

        $this->post('/miners/permit', [
            'pekerja_id'     => $this->pekerja()->id,
            'tanggal'        => '2026-03-15',
            'tipe_permit_id' => $visitor->id,
        ])->assertSessionHasNoErrors();

        $p = Permit::withoutGlobalScopes()->latest('id')->firstOrFail();

        $this->assertSame('2026-03-22', $p->berlaku_sampai->toDateString());
        $this->assertSame('tipe', $p->sumber_berlaku);
    }

    /* ═══════════ otorisasi ═══════════ */

    #[Test]
    public function test_bukan_admin_tidak_dapat_menghapus(): void
    {
        $this->actingAs($this->ohse);

        $p = $this->permit();

        $this->delete('/miners/permit/'.$p->id)->assertForbidden();
        $this->assertDatabaseHas('mnr_permit', ['id' => $p->id]);
    }

    #[Test]
    public function test_menghapus_permit_ikut_membuang_langkah_alurnya(): void
    {
        $this->actingAs($this->admin);

        $p = $this->permit();

        $this->assertGreaterThan(0, Alur::query()->where('dokumen', 'permit')->where('dokumen_id', $p->id)->count());

        $this->delete('/miners/permit/'.$p->id)->assertRedirect();

        /* Alur berelasi polimorfik lewat sepasang kolom, jadi tidak ada
           kunci asing yang membuangnya berkaskade. Dilewatkan, id yang
           kelak dipakai ulang membuat langkah lama muncul kembali pada
           dokumen yang sama sekali lain. */
        $this->assertSame(0,
            Alur::query()->where('dokumen', 'permit')->where('dokumen_id', $p->id)->count(),
            'Langkah alur tertinggal sebagai baris yatim sesudah permitnya dihapus.');
    }

    #[Test]
    public function test_peran_dibaca_dari_jabatan_yang_sudah_ada(): void
    {
        $this->assertSame(['ohse'], Jalur::peran($this->ohse));
        $this->assertSame(['pjo'], Jalur::peran($this->pengaju));
        $this->assertSame(array_keys(Alur::PERAN), Jalur::peran($this->admin));
        $this->assertSame([], Jalur::peran(null));
    }
}
