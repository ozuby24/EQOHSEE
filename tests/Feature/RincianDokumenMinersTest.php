<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Miners\{Induksi, InduksiOrang, Mcu, McuOrang, Pekerja, Permit, PermitBerkas, Simper};
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
            'tanggal' => now()->startOfDay(), 'status' => 'diajukan',
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

        $alamat = [
            "/miners/mcu/{$this->mcu()->id}",
            "/miners/induksi/{$induksi->id}",
            "/miners/permit/{$permit->id}",
            "/miners/simper/{$simper->id}",
        ];

        foreach ($alamat as $a) {
            $this->get($a)
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $p) => $p
                    ->component('Miners/Dokumen')
                    ->has('rincian')
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
     * Rincian permit menyebut tanggal efektif HANYA bila ia berbeda.
     *
     * Selalu ditampilkan, baris itu menjadi baris yang diabaikan.
     * Ditampilkan hanya saat berbeda, ia justru menjawab pertanyaan
     * yang muncul — kenapa kartu yang tertulis berlaku sampai Desember
     * sudah tidak berlaku hari ini.
     */
    public function test_tanggal_efektif_muncul_hanya_bila_berbeda(): void
    {
        $this->actingAs($this->pengguna(['is_admin' => true]));

        $permit = $this->permit();
        $permit->forceFill(['berlaku_sampai' => now()->addYear()->toDateString()])->save();

        $label = fn (string $url) => collect(
            $this->get($url)->viewData('page')['props']['rincian'] ?? []
        )->pluck('label')->all();

        $this->assertNotContains('Efektif habis', $label("/miners/permit/{$permit->id}"),
            'Tanggal efektif tampil padahal sama dengan yang tercetak.');

        /* MCU yang habis lebih dahulu memotong masa berlaku kartunya. */
        $orang = McuOrang::create([
            'mcu_id' => $this->mcu()->id, 'pekerja_id' => $permit->pekerja_id,
            'nama' => 'Pekerja Uji', 'aktif' => true,
            'tanggal_periksa' => now()->subMonths(11)->toDateString(),
            'berlaku_sampai'  => now()->addMonth()->toDateString(),
        ]);
        $permit->forceFill(['mcu_orang_id' => $orang->id])->save();

        $this->assertContains('Efektif habis', $label("/miners/permit/{$permit->id}"),
            'Kartu yang dipotong MCU tidak menyebutkan tanggal efektifnya.');
    }

    /**
     * Tombol tindakan hanya digambar bagi yang gilirannya tiba.
     *
     * Penjagaan sebenarnya tetap di Jalur — tombol yang disembunyikan
     * masih dapat dikirim permintaannya — tetapi menggambar tombol yang
     * pasti ditolak hanya membuat orang menekannya lalu bertanya-tanya.
     *
     * Pengguna TANPA peran khusus memang memegang peran 'pjo', dan itu
     * disengaja: memulangkan daftar kosong akan membuat mitra kerja
     * kehilangan tombol untuk mengajukan apa pun. Yang dijaga di sini
     * karena itu bukan "tanpa peran berarti tidak boleh", melainkan
     * dua hal yang benar-benar membedakan.
     */
    public function test_boleh_tindak_hanya_bagi_pemegang_giliran(): void
    {
        $permit = $this->permit();

        $ambil = fn () => $this->get("/miners/permit/{$permit->id}")
            ->viewData('page')['props']['bolehTindak'];

        /* Langkah pertama permit adalah PJO, dan pengguna tanpa peran
           khusus memegangnya. */
        $this->actingAs($this->pengguna());
        $this->assertTrue($ambil(), 'PJO kehilangan tombol pada gilirannya sendiri.');

        /* OHSE baru giliran kedua — belum boleh. */
        $this->actingAs($this->pengguna(['ohse_role' => 'ohse']));
        $this->assertFalse($ambil(), 'OHSE dapat menindak sebelum gilirannya tiba.');

        /* Yang MENGAJUKAN tidak boleh menyetujui pengajuannya sendiri,
           meski ia memegang peran yang sedang ditunggu. Inilah yang
           diperiksa auditor pada dokumen yang dibawa ke gerbang. */
        $pengaju = $this->pengguna();
        $permit->forceFill(['user_id' => $pengaju->id])->save();

        $this->actingAs($pengaju);
        $this->assertFalse($ambil(), 'Pengaju dapat menyetujui pengajuannya sendiri.');
    }

    /* ══════════════ surel alur ══════════════ */

    /** Giliran yang tiba disurati, dan hanya pemegang peran itu. */
    public function test_hanya_pemegang_giliran_berikutnya_disurati(): void
    {
        Notification::fake();

        $permit = $this->lengkapiBerkas($this->permit());
        $permit->terbitkanAlur();
        $permit->load('alur');

        $ohse  = $this->pengguna(['ohse_role' => 'ohse']);
        $ktt   = $this->pengguna(['lms_role' => 'ktt']);
        $biasa = $this->pengguna();

        /* PJO menyetujui; giliran berikutnya OHSE. */
        $this->assertNull(Jalur::setujui($permit, $this->pengguna(['is_admin' => true])));

        Notification::assertSentTo($ohse, AlurMinersBerpindah::class);
        Notification::assertNotSentTo($ktt, AlurMinersBerpindah::class);
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
        $ohseKita = $this->pengguna(['ohse_role' => 'ohse']);

        $permit = $this->lengkapiBerkas($this->permit());
        $permit->terbitkanAlur();
        $permit->load('alur');

        Jalur::setujui($permit, $this->pengguna(['is_admin' => true]));

        Notification::assertSentTo($ohseKita, AlurMinersBerpindah::class);
        Notification::assertNotSentTo($ohseLain, AlurMinersBerpindah::class);
    }

    /** Yang dikembalikan menyurati PENGAJUNYA, bukan peran berikutnya. */
    public function test_dikembalikan_menyurati_pengajunya(): void
    {
        Notification::fake();

        $pengaju = $this->pengguna();
        $ohse    = $this->pengguna(['ohse_role' => 'ohse']);

        $permit = $this->permit();
        $permit->forceFill(['user_id' => $pengaju->id])->save();
        $permit->terbitkanAlur();
        $permit->load('alur');

        Jalur::kembalikan($permit, $this->pengguna(['is_admin' => true]), 'SPDK belum ditandatangani.');

        Notification::assertSentTo($pengaju, AlurMinersBerpindah::class);
        Notification::assertNotSentTo($ohse, AlurMinersBerpindah::class);
    }

    /**
     * Surel yang gagal tidak menggagalkan persetujuannya.
     *
     * SMTP yang mati tidak boleh menghentikan penerbitan Mine Permit di
     * gerbang. Diperiksa dengan mailer yang memang dipastikan meledak.
     */
    public function test_surel_gagal_tidak_membatalkan_persetujuan(): void
    {
        $permit = $this->lengkapiBerkas($this->permit());
        $permit->terbitkanAlur();
        $permit->load('alur');

        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1',
                'mail.mailers.smtp.port' => 1]);

        $this->pengguna(['ohse_role' => 'ohse']);

        $galat = Jalur::setujui($permit, $this->pengguna(['is_admin' => true]));

        $this->assertNull($galat, 'Persetujuan gagal hanya karena surelnya tidak terkirim.');

        $permit->load('alur');
        $this->assertSame('setuju', $permit->alur->firstWhere('peran', 'pjo')->keadaan,
            'Langkah persetujuannya tidak tersimpan.');
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
        $berkas = collect($props['orang'][0]['berkas'])->keyBy('jenis');

        $this->assertTrue($berkas['mnh']['ada']);
        $this->assertNotNull($berkas['mnh']['url']);
        $this->assertFalse($berkas['mnk']['ada'], 'Lampiran yang belum ada dilaporkan ada.');
        $this->assertNull($berkas['mnk']['url']);
    }

    /** Pengguna tanpa hak melihat lampiran medis sebagai "ada tanpa alamat". */
    public function test_lampiran_medis_terjaga_pada_halaman_rincian(): void
    {
        $surat = $this->mcu();
        $orang = $surat->orang()->first();
        $orang->forceFill(['berkas_hasil' => 'miners/mcu/ada.pdf'])->save();

        $this->actingAs($this->pengguna());

        $props = $this->get("/miners/mcu/{$surat->id}")->viewData('page')['props'];
        $berkas = collect($props['orang'][0]['berkas'])->keyBy('jenis');

        $this->assertTrue($berkas['mnh']['ada'],
            'Berkasnya ada; yang tidak boleh hanya membukanya.');
        $this->assertNull($berkas['mnh']['url'],
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
     * diulang DARI AWAL.
     *
     * Bukan dilanjutkan dari langkah yang mengembalikannya: berkas yang
     * sudah diperbaiki adalah berkas yang berbeda dari yang pernah
     * dilihat langkah-langkah sebelumnya, dan membiarkan persetujuan
     * lama tetap berlaku berarti PJO menyetujui satu berkas lalu OHSE
     * menerima berkas yang lain dengan persetujuan yang sama.
     */
    public function test_yang_dikembalikan_dapat_diajukan_ulang(): void
    {
        $pengaju = $this->pengguna();
        $permit  = $this->permit();
        $permit->forceFill(['user_id' => $pengaju->id])->save();
        $permit->terbitkanAlur();
        $permit->load('alur');

        Jalur::setujui($permit, $this->pengguna(['is_admin' => true]));
        Jalur::kembalikan($permit, $this->pengguna(['is_admin' => true]), 'SPDK belum ditandatangani.');

        $permit->refresh()->load('alur');
        $this->assertSame('draf', $permit->status);

        $this->assertNull(Jalur::ajukanUlang($permit, $pengaju));

        $permit->refresh()->load('alur');

        $this->assertSame('diajukan', $permit->status);
        $this->assertTrue($permit->alur->every(fn ($a) => $a->keadaan === 'menunggu'),
            'Alurnya tidak diulang dari awal.');
        $this->assertTrue($permit->alur->every(fn ($a) => $a->catatan === null),
            'Catatan pengembalian lama masih menempel pada alur yang baru.');
    }

    /**
     * Yang DITOLAK tidak dapat diajukan ulang.
     *
     * Ditolak dan dikembalikan bukan dua kata untuk satu hal. Membiarkan
     * yang ditolak diajukan ulang lewat pintu ini membuat keputusan
     * menolak tidak berarti apa-apa — cukup ditekan sekali lagi.
     */
    public function test_yang_ditolak_tidak_dapat_diajukan_ulang(): void
    {
        $pengaju = $this->pengguna();
        $permit  = $this->permit();
        $permit->forceFill(['user_id' => $pengaju->id])->save();
        $permit->terbitkanAlur();
        $permit->load('alur');

        Jalur::tolak($permit, $this->pengguna(['is_admin' => true]), 'Tidak memenuhi syarat.');
        $permit->refresh()->load('alur');

        $this->assertNotNull(Jalur::ajukanUlang($permit, $pengaju),
            'Pengajuan yang ditolak dapat dihidupkan lagi.');
        $this->assertSame('ditolak', $permit->refresh()->status);
    }

    /** Hanya pengaju (atau admin) yang dapat mengajukan ulang. */
    public function test_hanya_pengaju_yang_dapat_mengajukan_ulang(): void
    {
        $pengaju = $this->pengguna();
        $permit  = $this->permit();
        $permit->forceFill(['user_id' => $pengaju->id])->save();
        $permit->terbitkanAlur();
        $permit->load('alur');

        Jalur::kembalikan($permit, $this->pengguna(['is_admin' => true]), 'Kurang.');
        $permit->refresh()->load('alur');

        $this->assertNotNull(Jalur::ajukanUlang($permit, $this->pengguna(['ohse_role' => 'ohse'])),
            'Peninjau dapat mengajukan ulang berkas yang bukan miliknya.');
    }

    /* ══════════════ lampiran wajib SOP ══════════════ */

    /**
     * Berkas tidak dapat DIKIRIM ke OHSE selama lampiran wajib kurang.
     *
     * Ditegakkan pada langkah PJO, bukan pada langkah OHSE, dan
     * pembedaan itu menentukan siapa yang terhalang: lampirannya
     * diunggah mitra kerja, bukan OHSE. Ditegakkan di OHSE, yang
     * terhalang adalah orang yang tidak dapat memperbaikinya.
     *
     * Di Project1 pengajuan tetap dapat naik dengan lampiran kurang, dan
     * akibatnya persis yang dikeluhkan pemakainya — berkas bolak-balik
     * antara mitra dan OHSE berhari-hari.
     */
    public function test_tidak_dapat_dikirim_ke_ohse_saat_lampiran_kurang(): void
    {
        $permit = $this->permit();
        $permit->terbitkanAlur();
        $permit->load('alur');

        $alasan = Jalur::setujui($permit, $this->pengguna(['is_admin' => true]));

        $this->assertNotNull($alasan, 'Kartu tanpa lampiran wajib tetap naik ke OHSE.');
        $this->assertStringContainsString('Lampiran wajib', (string) $alasan);

        /* Langkah pertamanya tetap menunggu — tidak ada yang bergerak. */
        $this->assertSame('menunggu',
            $permit->refresh()->load('alur')->alur->firstWhere('peran', 'pjo')->keadaan);
    }

    /** Lampiran lengkap membuka langkah pertamanya. */
    public function test_lampiran_lengkap_membuka_kiriman_ke_ohse(): void
    {
        $permit = $this->lengkapiBerkas($this->permit());
        $permit->terbitkanAlur();
        $permit->load('alur');

        $this->assertNull(Jalur::setujui($permit, $this->pengguna(['is_admin' => true])),
            'Kartu berlampiran lengkap tetap tertahan.');
    }

    /**
     * Menolak dan mengembalikan TETAP boleh meski lampirannya kurang.
     *
     * Justru itulah tindakan yang tepat bagi berkas yang tidak lengkap.
     * Ikut ditahan, OHSE tidak punya cara menyampaikan apa pun.
     */
    public function test_mengembalikan_tetap_boleh_saat_lampiran_kurang(): void
    {
        $permit = $this->permit();
        $permit->terbitkanAlur();
        $permit->load('alur');

        $this->assertNull(
            Jalur::kembalikan($permit, $this->pengguna(['ohse_role' => 'ohse']), 'Lengkapi SPDK.'),
            'Berkas yang kurang tidak dapat dikembalikan sama sekali.'
        );

        $this->assertTrue($permit->refresh()->load('alur')->dikembalikan());
    }

    /** Visitor Permit memakai daftar TIGA lampiran, bukan sembilan. */
    public function test_visitor_permit_memakai_daftarnya_sendiri(): void
    {
        $tipe = \App\Models\Miners\TipePermit::withoutGlobalScopes()
            ->firstOrCreate(['nama' => 'Visitor Permit'], ['aktif' => true]);

        $permit = $this->permit();
        $permit->forceFill(['tipe_permit_id' => $tipe->id])->save();
        $permit->load('tipe');

        $daftar = \App\Support\Miners\Kelengkapan::daftar($permit);

        $this->assertCount(count(\App\Support\Miners\Acuan::berkasWajib('permit_visitor')), $daftar);
        $this->assertLessThan(
            count(\App\Support\Miners\Acuan::berkasWajib('permit_baru')),
            count($daftar),
            'Tamu ditagih daftar Full Permit.'
        );
    }

    /** Lampiran yang tercatat tanpa berkas dihitung BELUM ada. */
    public function test_baris_lampiran_tanpa_berkas_dihitung_kurang(): void
    {
        $permit = $this->permit();

        $wajib = \App\Support\Miners\Acuan::berkasWajib('permit_baru')[0];

        \App\Models\Miners\PermitBerkas::create([
            'permit_id' => $permit->id,
            'jenis'     => \Illuminate\Support\Str::slug($wajib),
            'berkas'    => null,
        ]);
        $permit->load('tipe', 'berkas');

        $kurang = \App\Support\Miners\Kelengkapan::kurang($permit);

        $this->assertContains($wajib, $kurang,
            'Baris kosong dihitung sebagai lampiran yang sudah ada.');
    }

    /** Daftar periksa SOP sampai ke layar rincian. */
    public function test_daftar_wajib_sampai_ke_layar(): void
    {
        $this->actingAs($this->pengguna(['is_admin' => true]));
        $permit = $this->permit();

        $props = $this->get("/miners/permit/{$permit->id}")->viewData('page')['props'];

        $this->assertNotEmpty($props['wajib']);
        $this->assertArrayHasKey('ada', $props['wajib'][0]);
    }
}
