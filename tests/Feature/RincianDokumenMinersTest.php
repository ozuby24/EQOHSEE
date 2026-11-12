<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Miners\{Induksi, InduksiOrang, Mcu, McuOrang, Pekerja, Permit, PermitBerkas, Simper, Skema};
use App\Models\User;
use App\Notifications\AlurMinersBerpindah;
use App\Support\Berkas;
use App\Support\Miners\{Acuan, Jalur, Kelengkapan};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Halaman rincian dokumen Miners, dan surel yang menyertainya.
 *
 * Susunannya mengikuti Safe Track — halaman sendiri per pengajuan
 * dengan tiga bagian tetap: rincian, lampiran, persetujuan — sedangkan
 * tampilannya tetap memakai bahasa visual EQOHSEE.
 *
 * Yang dijaga di sini dua hal yang sama-sama tidak memulangkan galat
 * bila salah:
 *
 * 1. BATAS PERUSAHAAN. Halaman rincian membuka satu dokumen dengan
 *    nomornya di alamat. Nomor dapat ditebak; batasnya karena itu harus
 *    ditegakkan, bukan diandaikan.
 * 2. SIAPA YANG DISURATI. Surel yang dikirim ke semua orang dibaca
 *    tidak oleh siapa pun, dan surel yang dikirim ke perusahaan lain
 *    membocorkan nomor dokumen beserta nama pemegangnya.
 */
class RincianDokumenMinersTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;

    protected function setUp(): void
    {
        parent::setUp();

        $this->c = Company::create(['name' => 'PT Uji Rincian', 'code' => 'PUR']);
    }

    private function pengguna(array $x = []): User
    {
        return User::factory()->create(array_merge([
            'is_admin' => false, 'company_id' => $this->c->id,
        ], $x));
    }

    /**
     * Penyetuju bernama untuk IUP uji — skema Safe Track.
     *
     * @return array{ohse:User,ktt:User,dokter:User}
     */
    private function skema(): array
    {
        $o = [
            'ohse'   => $this->pengguna(['ohse_role' => 'ohse']),
            'ktt'    => $this->pengguna(['lms_role' => 'ktt']),
            'dokter' => $this->pengguna(['ohse_role' => 'paramedis']),
        ];

        Skema::create([
            'company_id'     => $this->c->id,
            'ohse_user_id'   => $o['ohse']->id,
            'ktt_user_id'    => $o['ktt']->id,
            'dokter_user_id' => $o['dokter']->id,
        ]);

        return $o;
    }

    private function pekerja(?Company $c = null): Pekerja
    {
        return Pekerja::withoutGlobalScopes()->create([
            'company_id' => ($c ?? $this->c)->id,
            'nama' => 'Pekerja Uji', 'nik' => 'NIK-'.uniqid(), 'status' => 'aktif',
        ]);
    }

    private function mcu(?Company $c = null): Mcu
    {
        $surat = Mcu::withoutGlobalScopes()->create([
            'company_id' => ($c ?? $this->c)->id,
            'no_registrasi' => 'MCU-UJI-1',
            'tanggal' => now()->startOfDay(), 'kepada' => 'Klinik Uji', 'status' => 'diajukan',
        ]);

        McuOrang::create([
            'mcu_id' => $surat->id, 'pekerja_id' => $this->pekerja($c)->id,
            'nama' => 'Pekerja Uji', 'aktif' => true,
        ]);

        return $surat;
    }

    private function permit(?Company $c = null): Permit
    {
        return Permit::withoutGlobalScopes()->create([
            'company_id' => ($c ?? $this->c)->id,
            'pekerja_id' => $this->pekerja($c)->id,
            'no_registrasi' => 'MP-UJI-1',
            'tanggal' => now()->startOfDay(), 'status' => 'draf',
        ]);
    }

    /**
     * Lengkapi lampiran wajib SOP.
     *
     * Jalur menahan langkah PJO selama daftar SOP belum penuh. Uji yang
     * menguji hal LAIN — surel, alur, ajukan ulang — harus melewati
     * syarat itu lebih dahulu; kalau tidak, yang diujinya diam-diam
     * berubah menjadi gerbang kelengkapan.
     */
    private function lengkapiBerkas(Permit $p): Permit
    {
        foreach (Acuan::berkasWajib(Kelengkapan::kunci($p) ?? 'permit_baru') as $label) {
            PermitBerkas::create([
                'permit_id' => $p->id,
                'jenis'     => Str::slug($label),
                'berkas'    => 'miners/permit/'.Str::slug($label).'.pdf',
            ]);
        }

        return $p->load('berkas', 'tipe');
    }

    /* ══════════════ halaman rincian ══════════════ */

    /** Keempat jenis dokumen punya halaman rincian yang terbuka. */
    public function test_keempat_jenis_punya_halaman_rincian(): void
    {
        $this->actingAs($this->pengguna(['is_admin' => true]));

        $induksi = Induksi::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'no_registrasi' => 'IND-UJI-1',
            'tanggal' => now()->startOfDay(), 'status' => 'diajukan',
        ]);
        InduksiOrang::create(['induksi_id' => $induksi->id, 'pekerja_id' => $this->pekerja()->id]);

        $permit = $this->permit();
        $simper = Simper::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'pekerja_id' => $permit->pekerja_id,
            'permit_id' => $permit->id, 'no_simper' => 'SP-UJI-1',
            'tanggal' => now()->startOfDay(),
            'kelas' => array_key_first(Simper::KELAS), 'status' => 'diajukan',
        ]);

        /* MCU, induksi, dan Mine Permit punya halaman rinciannya sendiri
           — susunan layar detail Safe Track masing-masing; SIMPER masih
           memakai halaman rincian bersama. Keempatnya membawa alur. */
        $alamat = [
            "/miners/mcu/{$this->mcu()->id}"    => ['Miners/McuRincian', 'mcu'],
            "/miners/induksi/{$induksi->id}"    => ['Miners/InduksiRincian', 'induksi'],
            "/miners/permit/{$permit->id}"      => ['Miners/PermitRincian', 'permit'],
            "/miners/simper/{$simper->id}"      => ['Miners/SimperRincian', 'simper'],
        ];

        foreach ($alamat as $a => [$komponen, $isi]) {
            $this->get($a)
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $p) => $p
                    ->component($komponen)
                    ->has($isi)
                    ->has('alur'));
        }
    }

    /**
     * Dokumen perusahaan lain tidak dapat dibuka, dan jawabannya 404.
     *
     * 404 dan BUKAN 403, sebab 403 mengakui bahwa dokumennya ada — dan
     * pada alamat yang nomornya dapat ditebak, pengakuan itu sendiri
     * sudah keterangan.
     */
    public function test_dokumen_perusahaan_lain_tidak_ditemukan(): void
    {
        $lain = Company::create(['name' => 'PT Sebelah', 'code' => 'PSB']);
        $milikLain = $this->permit($lain);

        $this->actingAs($this->pengguna());

        $this->get("/miners/permit/{$milikLain->id}")->assertNotFound();
    }

    /**
     * Masa berlaku Mine Permit di layar rincian MENGIKUTI MCU-nya —
     * aturan Safe Track — beserta sumbernya; kunci manual dibaca dari
     * kolomnya sendiri.
     */
    public function test_masa_berlaku_rincian_mengikuti_mcu(): void
    {
        $this->actingAs($this->pengguna(['is_admin' => true]));

        $permit = $this->permit();

        $orang = McuOrang::create([
            'mcu_id' => $this->mcu()->id, 'pekerja_id' => $permit->pekerja_id,
            'nama' => 'Pekerja Uji', 'aktif' => true,
            'tanggal_periksa' => now()->subMonths(11)->toDateString(),
            'berlaku_sampai'  => now()->addMonth()->toDateString(),
        ]);
        $permit->forceFill(['mcu_orang_id' => $orang->id, 'sumber_berlaku' => 'mcu',
            'berlaku_sampai' => now()->addYear()->toDateString()])->save();

        $props = fn () => $this->get("/miners/permit/{$permit->id}")->viewData('page')['props']['permit'];

        $this->assertSame(now()->addMonth()->toDateString(), $props()['berlaku'],
            'Kartu yang mengikuti MCU masih membaca salinan tanggalnya yang basi.');
        $this->assertSame('ikut MCU', $props()['sumber']);

        $permit->forceFill(['sumber_berlaku' => 'manual'])->save();

        $this->assertSame(now()->addYear()->toDateString(), $props()['berlaku']);
        $this->assertSame('dikunci manual', $props()['sumber']);
    }

    /**
     * Tombol hanya digambar bagi yang gilirannya tiba.
     *
     * Penjagaan sebenarnya tetap di Jalur — tombol yang disembunyikan
     * masih dapat dikirim permintaannya — tetapi menggambar tombol yang
     * pasti ditolak hanya membuat orang menekannya lalu bertanya-tanya.
     *
     * Sebelum dikirim, yang punya tombol adalah PENGAJUNYA ("Kirim").
     * Sesudah dikirim, hanya HSE yang DITUGASI pada skema IUP — bukan
     * setiap pemegang peran OHSE.
     */
    public function test_boleh_tindak_hanya_bagi_pemegang_giliran(): void
    {
        $o = $this->skema();

        $pengaju = $this->pengguna();
        $permit  = $this->lengkapiBerkas($this->permit());
        $permit->forceFill(['user_id' => $pengaju->id])->save();

        $props = fn () => $this->get("/miners/permit/{$permit->id}")->viewData('page')['props'];

        $this->actingAs($pengaju);
        $this->assertTrue($props()['bolehKirim'], 'Pengaju kehilangan tombol Kirim pada drafnya sendiri.');
        $this->assertFalse($props()['bolehTindak']);

        $this->actingAs($o['ohse']);
        $this->assertFalse($props()['bolehTindak'], 'HSE dapat menindak berkas yang belum dikirim.');

        $this->assertNull(Jalur::kirim($permit->fresh(), $pengaju));

        $this->actingAs($o['ohse']);
        $this->assertTrue($props()['bolehTindak'], 'HSE yang ditugasi kehilangan tombolnya.');

        $this->actingAs($this->pengguna(['ohse_role' => 'ohse']));
        $this->assertFalse($props()['bolehTindak'], 'OHSE yang tidak ditugasi mendapat tombol.');

        $this->actingAs($pengaju);
        $this->assertFalse($props()['bolehTindak'], 'Pengaju dapat menyetujui pengajuannya sendiri.');
        $this->assertFalse($props()['bolehKirim'], 'Berkas yang sudah dikirim dapat dikirim lagi.');
    }

    /* ══════════════ surel alur ══════════════ */

    /** Yang disurati hanya penyetuju yang DITUGASI pada giliran berikutnya. */
    public function test_hanya_pemegang_giliran_berikutnya_disurati(): void
    {
        Notification::fake();

        $o = $this->skema();
        $ohseLain = $this->pengguna(['ohse_role' => 'ohse']);
        $biasa    = $this->pengguna();

        $permit = $this->lengkapiBerkas($this->permit());

        /* Pengaju mengirim; giliran berikutnya HSE yang ditugasi. */
        $this->assertNull(Jalur::kirim($permit, $this->pengguna(['is_admin' => true])));

        Notification::assertSentTo($o['ohse'], AlurMinersBerpindah::class);
        Notification::assertNotSentTo($ohseLain, AlurMinersBerpindah::class);
        Notification::assertNotSentTo($o['ktt'], AlurMinersBerpindah::class);
        Notification::assertNotSentTo($biasa, AlurMinersBerpindah::class);
    }

    /**
     * OHSE perusahaan LAIN tidak ikut disurati.
     *
     * Isi surelnya menyebut nomor dokumen beserta nama pemegangnya.
     */
    public function test_peran_perusahaan_lain_tidak_disurati(): void
    {
        Notification::fake();

        $lain = Company::create(['name' => 'PT Sebelah', 'code' => 'PSB']);
        $ohseLain = User::factory()->create([
            'is_admin' => false, 'company_id' => $lain->id, 'ohse_role' => 'ohse',
        ]);
        $o = $this->skema();

        $permit = $this->lengkapiBerkas($this->permit());

        Jalur::kirim($permit, $this->pengguna(['is_admin' => true]));

        Notification::assertSentTo($o['ohse'], AlurMinersBerpindah::class);
        Notification::assertNotSentTo($ohseLain, AlurMinersBerpindah::class);
    }

    /** Yang dikembalikan menyurati PENGAJUNYA, bukan peran berikutnya. */
    public function test_dikembalikan_menyurati_pengajunya(): void
    {
        $o = $this->skema();
        $pengaju = $this->pengguna();

        $permit = $this->lengkapiBerkas($this->permit());
        $permit->forceFill(['user_id' => $pengaju->id])->save();
        Jalur::kirim($permit, $pengaju);

        Notification::fake();

        Jalur::kembalikan($permit->fresh(), $o['ohse'], 'SPDK belum ditandatangani.');

        Notification::assertSentTo($pengaju, AlurMinersBerpindah::class);
        Notification::assertNotSentTo($o['ktt'], AlurMinersBerpindah::class);
    }

    /**
     * Surel yang gagal tidak menggagalkan persetujuannya — dan hal itu
     * DIKATAKAN, bukan cuma dicatat di log.
     */
    public function test_surel_gagal_tidak_membatalkan_persetujuan(): void
    {
        $this->skema();
        $permit = $this->lengkapiBerkas($this->permit());

        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1',
                'mail.mailers.smtp.port' => 1]);

        $galat = Jalur::kirim($permit, $this->pengguna(['is_admin' => true]));

        $this->assertNull($galat, 'Pengiriman gagal hanya karena surelnya tidak terkirim.');
        $this->assertStringContainsString('SUDAH tercatat', (string) Jalur::pesanSurel());

        $permit->load('alur');
        $this->assertSame('setuju', $permit->alur->firstWhere('peran', 'pengaju')->keadaan,
            'Langkah pengirimannya tidak tersimpan.');
    }

    /** Giliran tanpa penerima mengatakan "penerima tidak ditemukan". */
    public function test_penerima_tidak_ditemukan_dikatakan(): void
    {
        Notification::fake();

        $o = $this->skema();
        $permit = $this->lengkapiBerkas($this->permit());

        /* Akun HSE yang ditugasi kehilangan alamat surelnya. */
        $o['ohse']->forceFill(['email' => ''])->save();

        $this->assertNull(Jalur::kirim($permit, $this->pengguna(['is_admin' => true])));
        $this->assertStringContainsString('penerima langkah berikutnya tidak ditemukan', (string) Jalur::pesanSurel());
    }

    /* ══════════════ lampiran pada halaman rincian ══════════════ */

    /** Lampiran MCU ikut pada baris orangnya, dengan keadaan dan alamatnya. */
    public function test_lampiran_mcu_ikut_pada_barisnya(): void
    {
        $this->actingAs($this->pengguna(['is_admin' => true]));

        $surat = $this->mcu();
        $orang = $surat->orang()->first();
        $orang->forceFill(['berkas_hasil' => 'miners/mcu/ada.pdf'])->save();

        $props = $this->get("/miners/mcu/{$surat->id}")->viewData('page')['props'];
        $berkas = $props['orang'][0]['berkas'];

        $this->assertTrue($berkas['hasil']['ada']);
        $this->assertNotNull($berkas['hasil']['url']);
        $this->assertFalse($berkas['rujukan']['ada'], 'Lampiran yang belum ada dilaporkan ada.');
        $this->assertNull($berkas['rujukan']['url']);
    }

    /** Pengguna tanpa hak melihat lampiran medis sebagai "ada tanpa alamat". */
    public function test_lampiran_medis_terjaga_pada_halaman_rincian(): void
    {
        $surat = $this->mcu();
        $orang = $surat->orang()->first();
        $orang->forceFill(['berkas_hasil' => 'miners/mcu/ada.pdf'])->save();

        /* Pengajunya sendiri — rincian MCU tidak lagi terbuka bagi setiap
           pengguna perusahaan (AksesMiners::lihatDokumen). */
        $pengaju = $this->pengguna();
        $surat->forceFill(['user_id' => $pengaju->id])->save();
        $this->actingAs($pengaju);

        $props = $this->get("/miners/mcu/{$surat->id}")->viewData('page')['props'];
        $berkas = $props['orang'][0]['berkas'];

        $this->assertTrue($berkas['hasil']['ada'],
            'Berkasnya ada; yang tidak boleh hanya membukanya.');
        $this->assertNull($berkas['hasil']['url'],
            'Alamat lampiran medis bocor ke pengguna tanpa hak.');
    }

    /** Jenis dokumen yang tidak dikenal memulangkan 404, bukan galat. */
    public function test_jenis_tak_dikenal_memulangkan_404(): void
    {
        $this->actingAs($this->pengguna(['is_admin' => true]));

        $this->get('/miners/mcu/999999')->assertNotFound();
    }

    /* ══════════════ ajukan ulang ══════════════ */

    /**
     * Pengajuan yang dikembalikan dapat diajukan ulang, dan alurnya
     * diulang DARI AWAL — resubmit Safe Track.
     */
    public function test_yang_dikembalikan_dapat_diajukan_ulang(): void
    {
        $o = $this->skema();
        $pengaju = $this->pengguna();
        $permit  = $this->lengkapiBerkas($this->permit());
        $permit->forceFill(['user_id' => $pengaju->id])->save();

        Jalur::kirim($permit, $pengaju);
        Jalur::setujui($permit->fresh(), $o['ohse']);
        Jalur::kembalikan($permit->fresh(), $o['ktt'], 'SPDK belum ditandatangani.');

        $permit->refresh()->load('alur');
        $this->assertSame('draf', $permit->status);

        $this->assertNull(Jalur::ajukanUlang($permit, $pengaju));

        $permit->refresh()->load('alur');

        $this->assertSame('ohse', $permit->status);
        $this->assertSame('setuju', $permit->alur->firstWhere('peran', 'pengaju')->keadaan);
        $this->assertTrue($permit->alur->where('peran', '!=', 'pengaju')->every(fn ($a) => $a->keadaan === 'menunggu'),
            'Alurnya tidak diulang dari awal.');
        $this->assertTrue($permit->alur->every(fn ($a) => $a->catatan === null),
            'Catatan pengembalian lama masih menempel pada alur yang baru.');
    }

    /**
     * Yang DITOLAK pun dapat diajukan ulang — perilaku Safe Track.
     *
     * Safe Track tidak punya penolakan yang final: reject menyetel status
     * "reject" dan pengajunya dapat memperbaiki lalu resubmit. Yang
     * membedakannya dari "dikembalikan" adalah statusnya yang tetap
     * tercatat sebagai penolakan sampai dikirim ulang.
     */
    public function test_yang_ditolak_dapat_diajukan_ulang(): void
    {
        $o = $this->skema();
        $pengaju = $this->pengguna();
        $permit  = $this->lengkapiBerkas($this->permit());
        $permit->forceFill(['user_id' => $pengaju->id])->save();

        Jalur::kirim($permit, $pengaju);
        Jalur::tolak($permit->fresh(), $o['ohse'], 'Tidak memenuhi syarat.');
        $this->assertSame('ditolak', $permit->refresh()->status);

        /* Selama ditolak, tidak ada yang dapat menyetujuinya. */
        $this->assertNotNull(Jalur::setujui($permit->fresh(), $o['ohse']));

        $this->assertNull(Jalur::ajukanUlang($permit->fresh(), $pengaju));
        $this->assertSame('ohse', $permit->refresh()->status);
    }

    /** Hanya pengaju (atau admin) yang dapat mengajukan ulang. */
    public function test_hanya_pengaju_yang_dapat_mengajukan_ulang(): void
    {
        $o = $this->skema();
        $pengaju = $this->pengguna();
        $permit  = $this->lengkapiBerkas($this->permit());
        $permit->forceFill(['user_id' => $pengaju->id])->save();

        Jalur::kirim($permit, $pengaju);
        Jalur::kembalikan($permit->fresh(), $o['ohse'], 'Kurang.');
        $permit->refresh()->load('alur');

        $this->assertNotNull(Jalur::ajukanUlang($permit, $o['ohse']),
            'Peninjau dapat mengajukan ulang berkas yang bukan miliknya.');
    }

    /* ══════════════ lampiran wajib SOP ══════════════ */

    /**
     * Berkas tidak dapat DIKIRIM selama lampiran wajibnya kurang.
     *
     * Ditegakkan pada tombol Kirim — gerbang "Sending" Safe Track —
     * sehingga yang terhalang adalah orang yang memegang berkasnya dan
     * dapat memperbaikinya, bukan HSE.
     */
    public function test_tidak_dapat_dikirim_ke_ohse_saat_lampiran_kurang(): void
    {
        $this->skema();
        $permit = $this->permit();
        $permit->terbitkanAlur();
        $permit->load('alur');

        $alasan = Jalur::kirim($permit, $this->pengguna(['is_admin' => true]));

        $this->assertNotNull($alasan, 'Kartu tanpa lampiran wajib tetap naik ke OHSE.');
        $this->assertStringContainsString('lampiran wajib', (string) $alasan);

        /* Langkah pertamanya tetap menunggu — tidak ada yang bergerak. */
        $this->assertSame('menunggu',
            $permit->refresh()->load('alur')->alur->firstWhere('peran', 'pengaju')->keadaan);
    }

    /** Lampiran lengkap membuka kirimannya. */
    public function test_lampiran_lengkap_membuka_kiriman_ke_ohse(): void
    {
        $this->skema();
        $permit = $this->lengkapiBerkas($this->permit());

        $this->assertNull(Jalur::kirim($permit, $this->pengguna(['is_admin' => true])),
            'Kartu berlampiran lengkap tetap tertahan.');
    }

    /** Tanpa penyetuju pada skema IUP, berkas tidak dapat dikirim — dan pesannya menyebut menunya. */
    public function test_tanpa_skema_berkas_tidak_dapat_dikirim(): void
    {
        $permit = $this->lengkapiBerkas($this->permit());

        $alasan = (string) Jalur::kirim($permit, $this->pengguna(['is_admin' => true]));

        $this->assertStringContainsString('belum lengkap', $alasan);
        $this->assertStringContainsString('Alur Persetujuan', $alasan);
        $this->assertSame('draf', $permit->fresh()->status);
    }

    /**
     * Mengembalikan TETAP boleh meski lampirannya ternyata kurang.
     *
     * Justru itulah tindakan yang tepat bagi berkas yang tidak lengkap.
     */
    public function test_mengembalikan_tetap_boleh_saat_lampiran_kurang(): void
    {
        $o = $this->skema();
        $permit = $this->lengkapiBerkas($this->permit());
        Jalur::kirim($permit, $this->pengguna(['is_admin' => true]));

        /* Satu lampiran ternyata kosong sesudah dikirim. */
        $permit->berkas()->first()->update(['berkas' => null]);

        $this->assertNull(
            Jalur::kembalikan($permit->fresh(), $o['ohse'], 'Lengkapi SPDK.'),
            'Berkas yang kurang tidak dapat dikembalikan sama sekali.'
        );

        $this->assertTrue($permit->refresh()->load('alur')->dikembalikan());
    }

    /**
     * Lampiran Mine Permit mengikuti Safe Track: SPDK wajib, Induksi
     * Khusus dan LOTO opsional — apa pun tipe permitnya.
     */
    public function test_lampiran_permit_mengikuti_safe_track(): void
    {
        $permit = $this->permit();
        $permit->load('berkas');

        $daftar = \App\Support\Miners\Kelengkapan::daftar($permit);

        $this->assertSame(['spdk', 'induksi-khusus', 'loto'], array_column($daftar, 'kunci'));
        $this->assertSame([true, false, false], array_column($daftar, 'wajib'));
        $this->assertSame(['SPDK'], \App\Support\Miners\Kelengkapan::kurang($permit));
    }

    /** Lampiran yang tercatat tanpa berkas dihitung BELUM ada. */
    public function test_baris_lampiran_tanpa_berkas_dihitung_kurang(): void
    {
        $permit = $this->permit();

        \App\Models\Miners\PermitBerkas::create([
            'permit_id' => $permit->id,
            'jenis'     => 'spdk',
            'berkas'    => null,
        ]);
        $permit->load('tipe', 'berkas');

        $this->assertContains('SPDK', \App\Support\Miners\Kelengkapan::kurang($permit),
            'Baris kosong dihitung sebagai lampiran yang sudah ada.');

        /* SPDK lama dari daftar SOP tetap terhitung. */
        \App\Models\Miners\PermitBerkas::create([
            'permit_id' => $permit->id,
            'jenis'     => 'form-spdk-bertanda-tangan-pemohon',
            'berkas'    => 'miners/permit/spdk.pdf',
        ]);
        $permit->load('berkas');

        $this->assertSame([], \App\Support\Miners\Kelengkapan::kurang($permit));
    }

    /** Daftar periksa SOP sampai ke layar rincian. */
    public function test_daftar_wajib_sampai_ke_layar(): void
    {
        $this->actingAs($this->pengguna(['is_admin' => true]));
        $permit = $this->permit();

        $props = $this->get("/miners/permit/{$permit->id}")->viewData('page')['props'];

        $this->assertSame(['spdk', 'induksi-khusus', 'loto'], array_column($props['lampiran'], 'kunci'));
        $this->assertArrayHasKey('ada', $props['lampiran'][0]);
        $this->assertSame(['SPDK'], $props['kurang']);
    }
}
