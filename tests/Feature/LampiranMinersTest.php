<?php

namespace Tests\Feature;

use App\Models\{Company, Paspor, PasporKartu, User};
use App\Support\{AlurMiner, Berkas, LampiranMiners, NomorRegister};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Lampiran syarat: diunggah, bukan diketik. Nomor register: terbit sendiri.
 *
 * ── MENGAPA UNGGAHAN ──
 *
 * Seluruh lampiran ini lahir sebagai `string max:255` dengan petunjuk
 * "jalur berkas": yang mengisinya mengetik nama berkas, dan berkasnya
 * sendiri tidak pernah ikut. Yang tersimpan bukan bukti melainkan
 * PERNYATAAN bahwa buktinya ada di suatu tempat — tidak dapat dibuka,
 * tidak dapat diperiksa, tidak dapat dibedakan dari salah ketik.
 *
 * Paling merugikan pada Mine Permit: keempat lampiran wajibnya
 * diperiksa SEBELUM permit terbit, dan yang memeriksanya harus
 * mencarinya di folder bersama.
 *
 * ── MENGAPA NOMOR TERBIT SENDIRI ──
 *
 * Nomor yang diketik tangan gagal dua cara yang sama-sama sunyi:
 * TABRAKAN (dua orang menulis nomor yang sama pada hari yang sama) dan
 * KEKOSONGAN (kolomnya opsional, jadi surat tersimpan tanpa nomor dan
 * tidak dapat dirujuk, dicari, maupun ditagih).
 */
class LampiranMinersTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $admin;
    private Paspor $p;

    protected function setUp(): void
    {
        parent::setUp();

        $this->c = Company::create([
            'name' => 'PT Uji Lampiran', 'doc_no_prefix' => 'CAM',
        ]);

        $this->admin = User::factory()->create([
            'is_admin' => true, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);

        $this->p = Paspor::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'nama' => 'Petrus',
            'nik' => '6407162711830001', 'jabatan' => 'Security Officer',
        ]);

        $this->actingAs($this->admin);
    }

    private function kartu(array $ganti = []): PasporKartu
    {
        return PasporKartu::withoutGlobalScopes()->create($ganti + [
            'paspor_id' => $this->p->id,
            'jenis' => AlurMiner::KARTU_PERMIT,
            'sebab_terbit' => 'Terbit',
        ]);
    }

    /* ═══════════ 1 · nomor register otomatis ═══════════ */

    #[Test]
    public function nomor_kartu_terbit_sendiri_bergaya_dbest(): void
    {
        $k = $this->kartu(['tgl_terbit' => '2026-04-27']);

        /* MKI.20260427002755 → {KODE}.{YYYYMMDD}{id 6 digit} */
        $this->assertMatchesRegularExpression(
            '/^CAM\.20260427\d{6}$/', $k->refresh()->nomor,
            'Nomor kartu tidak mengikuti bentuk D\'Best {KODE}.{YYYYMMDD}{id}.'
        );
    }

    #[Test]
    public function nomor_yang_diketik_tangan_tidak_ditimpa(): void
    {
        $k = $this->kartu(['nomor' => 'WARISAN/001']);

        $this->assertSame('WARISAN/001', $k->refresh()->nomor,
            'Nomor yang sudah ada ditimpa — kartu yang sudah tersebar '
            .'mendapat nomor kedua, dan yang memegang cetakan lamanya '
            .'tidak menemukannya lagi.');
    }

    #[Test]
    public function dua_kartu_tidak_pernah_bernomor_sama(): void
    {
        /* Berpijak pada ID baris, bukan pada hitungan baris. Menghitung
           akan memberi nomor yang sama kepada dua permintaan bersamaan,
           dan nomor yang SUDAH DIPAKAI setelah satu baris dihapus. */
        $a = $this->kartu(['tgl_terbit' => '2026-04-27'])->refresh();
        $b = $this->kartu(['tgl_terbit' => '2026-04-27'])->refresh();

        $this->assertNotSame($a->nomor, $b->nomor);

        $a->delete();
        $c = $this->kartu(['tgl_terbit' => '2026-04-27'])->refresh();

        $this->assertNotSame($b->nomor, $c->nomor);
    }

    #[Test]
    public function bentuk_nomor_berawalan_mengikuti_dbest(): void
    {
        /* IND000996 · SIMPER-002039 · AUTHORITY-00175 */
        $this->assertSame('IND000996', NomorRegister::berawalan('IND', 996));
        $this->assertSame('SIMPER-002039', NomorRegister::berawalan('SIMPER-', 2039));
        $this->assertSame('AUTHORITY-00175', NomorRegister::berawalan('AUTHORITY-', 175, 5));
    }

    #[Test]
    public function kartu_tanpa_kode_perusahaan_tetap_bernomor(): void
    {
        $lain = Company::create(['name' => 'PT Tanpa Kode']);
        $p = Paspor::withoutGlobalScopes()->create([
            'company_id' => $lain->id, 'nama' => 'Tanpa', 'nik' => '1', 'jabatan' => 'Helper',
        ]);

        $k = PasporKartu::withoutGlobalScopes()->create([
            'paspor_id' => $p->id, 'jenis' => AlurMiner::KARTU_PERMIT,
            'sebab_terbit' => 'Terbit', 'tgl_terbit' => '2026-04-27',
        ]);

        $this->assertNotEmpty($k->refresh()->nomor,
            'Kartu milik perusahaan tanpa kode dokumen tidak bernomor sama sekali.');
    }

    /* ═══════════ 2 · lampiran benar-benar diunggah ═══════════ */

    #[Test]
    public function lampiran_diunggah_dan_tersimpan_di_disk(): void
    {
        Storage::fake(Berkas::TERTUTUP);

        $r = $this->post('/miners/lampiran', [
            'kolom'  => 'berkas_spdk',
            'berkas' => UploadedFile::fake()->create('SPDK Petrus.pdf', 30, 'application/pdf'),
        ]);

        $r->assertOk();
        $jalur = $r->json('jalur');

        $this->assertNotEmpty($jalur);
        Storage::disk(Berkas::TERTUTUP)->assertExists($jalur);
        $this->assertSame('SPDK Petrus.pdf', $r->json('nama'));
    }

    #[Test]
    public function kolom_di_luar_katalog_ditolak(): void
    {
        Storage::fake(Berkas::TERTUTUP);

        $this->post('/miners/lampiran', [
            'kolom'  => 'status',            // bukan lampiran
            'berkas' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('kolom');
    }

    #[Test]
    public function jenis_berkas_berbahaya_ditolak(): void
    {
        Storage::fake(Berkas::TERTUTUP);

        $this->post('/miners/lampiran', [
            'kolom'  => 'berkas_spdk',
            'berkas' => UploadedFile::fake()->create('jahat.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('berkas');
    }

    #[Test]
    public function lampiran_melekat_ke_kartunya(): void
    {
        Storage::fake(Berkas::TERTUTUP);
        $k = $this->kartu();

        $jalur = $this->post('/miners/lampiran', [
            'kolom'  => 'berkas_spdk',
            'berkas' => UploadedFile::fake()->create('SPDK.pdf', 30, 'application/pdf'),
        ])->json('jalur');

        $this->put("/miners/{$this->p->id}/kartu/{$k->id}/lampiran",
            ['berkas_spdk' => $jalur])->assertSessionHasNoErrors();

        $this->assertSame($jalur, $k->refresh()->berkas_spdk);
    }

    /**
     * Rute lampiran TIDAK boleh menjadi jalan pintas mengubah kartu.
     *
     * Ia menerima kiriman sebagian — justru karena itu ia harus menolak
     * kolom di luar lampiran. Bila tidak, satu PUT bermuatan
     * `{"status":"disetujui"}` menerbitkan kartu tanpa melewati
     * peninjauan sama sekali.
     */
    #[Test]
    public function rute_lampiran_tidak_dapat_mengubah_kolom_lain(): void
    {
        $k = $this->kartu();

        $this->put("/miners/{$this->p->id}/kartu/{$k->id}/lampiran", [
            'berkas_spdk' => 'miners/lampiran/x.pdf',
            'jenis'       => AlurMiner::KARTU_LICENSE,
            'status'      => 'disetujui',
        ]);

        $k->refresh();

        $this->assertSame(AlurMiner::KARTU_PERMIT, $k->jenis,
            'Rute lampiran mengubah jenis kartu.');
        $this->assertNotSame('disetujui', $k->status,
            'Rute lampiran menerbitkan kartu tanpa peninjauan.');
    }

    #[Test]
    public function kartu_yang_sudah_diajukan_tidak_dapat_ditambahi_lampiran(): void
    {
        $k = $this->kartu();
        $k->ajukan();

        $this->put("/miners/{$this->p->id}/kartu/{$k->id}/lampiran",
            ['berkas_spdk' => 'miners/lampiran/x.pdf'])
            ->assertStatus(422);
    }

    /* ═══════════ 3 · lampiran dapat dibuka ═══════════ */

    #[Test]
    public function lampiran_yang_tersimpan_dapat_dibuka(): void
    {
        Storage::fake(Berkas::TERTUTUP);
        $k = $this->kartu();

        $jalur = $this->post('/miners/lampiran', [
            'kolom'  => 'berkas_ktp',
            'berkas' => UploadedFile::fake()->create('KTP.jpg', 20, 'image/jpeg'),
        ])->json('jalur');

        $this->put("/miners/{$this->p->id}/kartu/{$k->id}/lampiran", ['berkas_ktp' => $jalur]);

        $this->get(route('berkas.sajikan', [
            'jenis' => Berkas::jenisLampiran('berkas_ktp'), 'baris' => $k->id,
        ]))->assertOk();
    }

    #[Test]
    public function tiap_kolom_lampiran_punya_jenis_sajian_sendiri(): void
    {
        /* Bila dua lampiran berbagi satu kode jenis, membuka yang satu
           akan menyajikan berkas yang lain — dan keduanya tampak
           benar. */
        $kode = array_map(
            fn ($k) => Berkas::jenisLampiran($k), LampiranMiners::kolom());

        $this->assertSame(count($kode), count(array_unique($kode)));

        foreach ($kode as $j) {
            $this->assertArrayHasKey($j, Berkas::tersaji(),
                "Jenis {$j} tidak terdaftar, jadi lampirannya tidak dapat dibuka.");
        }
    }

    /* ═══════════ 4 · yang wajib disebut di muka ═══════════ */

    #[Test]
    public function lampiran_wajib_yang_belum_ada_disebutkan(): void
    {
        $k = $this->kartu();

        $kurang = LampiranMiners::kurang($k);

        $this->assertContains('Form SPDK', $kurang);
        $this->assertContains('KTP', $kurang);

        /* Yang TIDAK wajib tidak ikut disebut — daftar yang memuat
           semuanya membuat yang benar-benar menahan tidak terlihat. */
        $this->assertNotContains('Lotto / Sertifikat Welder', $kurang);
    }

    /**
     * Kartu tamu tidak dituntut lampiran permit.
     *
     * Syarat kartu tamu memang lebih ringan — ia tidak menuntut MCU
     * sama sekali. Menuntutnya melengkapi SPDK dan form departemen
     * membuat kartu tamu tidak pernah dapat terbit, dan yang
     * mengurusnya tidak akan menemukan sebabnya: seluruh medannya ada,
     * hanya saja tidak satu pun relevan baginya.
     */
    #[Test]
    public function kartu_tamu_hanya_dituntut_ktp(): void
    {
        $tamu = $this->kartu(['jenis' => 'Visitor']);

        $kurang = LampiranMiners::kurang($tamu);

        $this->assertSame(['KTP'], $kurang,
            'Kartu tamu dituntut lampiran di luar KTP: '.implode(', ', $kurang));
    }

    #[Test]
    public function lampiran_khusus_license_tidak_dituntut_pada_permit(): void
    {
        $permit = $this->kartu(['jenis' => AlurMiner::KARTU_PERMIT]);

        $this->assertNotContains('Berkas SIM kepolisian', LampiranMiners::kurang($permit),
            'Mine Permit menuntut berkas SIM — padahal permit bukan izin mengemudi.');

        $license = $this->kartu(['jenis' => AlurMiner::KARTU_LICENSE]);

        $this->assertContains('Berkas SIM kepolisian', LampiranMiners::kurang($license));
    }

    #[Test]
    public function halaman_rincian_membawa_lampiran_dan_kekurangannya(): void
    {
        $k = $this->kartu();

        $props = $this->get("/miners/{$this->p->id}")->viewData('page')['props'];
        $baris = collect($props['kartu'])->firstWhere('id', $k->id);

        $this->assertNotNull($baris, 'Kartu tidak ada pada prop halaman rincian.');
        $this->assertNotEmpty($baris['lampiran']);
        $this->assertNotEmpty($baris['lampiranKurang']);

        $satu = collect($baris['lampiran'])->firstWhere('kolom', 'berkas_spdk');

        $this->assertNotNull($satu,
            'Lampiran berkas_spdk tidak ikut terkirim. Kolom yang ada: '
            .implode(', ', array_column($baris['lampiran'], 'kolom')));

        $this->assertTrue($satu['wajib']);
        $this->assertFalse($satu['ada']);
        $this->assertNull($satu['url']);
    }
}
