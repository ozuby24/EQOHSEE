<?php

namespace Tests\Feature;

use App\Models\{Company, Paspor, PasporKartu, PasporKartuUnit, PasporSertifikat, User};
use App\Support\{Alur, AlurMiner, Authority, Berkas, LampiranMiners};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Attachment Simper dan Attachment Sertifikasi — berkasnya, bukan namanya.
 *
 * DUA LUBANG YANG BENTUKNYA SAMA, dan keduanya ditutup di sini.
 *
 *   SIMPER. Tiap unit yang diujikan punya empat berkas — rambu, teori,
 *   praktik, evaluasi — dan keempatnya tersimpan sebagai kolom teks
 *   berisi "jalur berkas" yang tidak pernah dapat dibuka. Layarnya pun
 *   hanya menghitungnya: "2/4 berkas". Angka itu tidak memberi tahu
 *   siapa pun berkas mana yang kurang, dan tidak dapat dibedakan dari
 *   angka yang salah.
 *
 *   SERTIFIKAT. Kolom `berkas` ada di `paspor_sertifikat` sejak tabelnya
 *   lahir dan tidak pernah dapat diisi — aturan validasinya tidak
 *   menyebutkannya, formulirnya tidak punya medan. Yang tersimpan hanya
 *   NAMA sertifikatnya, dan nama sertifikat tidak dapat dibedakan dari
 *   sertifikat yang tidak pernah ada. Padahal sertifikat kompetensi
 *   adalah dasar seseorang boleh menjadi pengawas operasional, dan
 *   auditor yang memintanya meminta lembarnya.
 *
 * Yang diuji di sini karena itu bukan tata letaknya melainkan tiga hal
 * yang membuat tata letak itu ada gunanya: berkasnya sungguh tersimpan,
 * sungguh dapat dibuka, dan TIDAK dapat dibuka lintas perusahaan.
 */
class LampiranSimperAuthorityTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $admin;
    private Paspor $p;

    protected function setUp(): void
    {
        parent::setUp();

        $this->c = Company::create(['name' => 'PT Uji Simper', 'doc_no_prefix' => 'USM']);

        $this->admin = User::factory()->create([
            'is_admin' => true, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);

        $this->p = Paspor::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'nama' => 'Rusdi',
            'nik' => '6407162711830002', 'jabatan' => 'Operator',
        ]);

        $this->actingAs($this->admin);
    }

    private function kartu(array $ganti = []): PasporKartu
    {
        return PasporKartu::withoutGlobalScopes()->create($ganti + [
            'paspor_id' => $this->p->id,
            'jenis' => AlurMiner::KARTU_LICENSE,
            'sebab_terbit' => 'Terbit',
        ]);
    }

    private function unggah(string $kolom, string $nama = 'uji.pdf'): string
    {
        return $this->post('/miners/lampiran', [
            'kolom'  => $kolom,
            'berkas' => UploadedFile::fake()->create($nama, 20, 'application/pdf'),
        ])->json('jalur');
    }

    /* ═══════════ 1 · katalog dan kode jenisnya ═══════════ */

    /**
     * Tiap berkas uji unit punya kode jenisnya sendiri.
     *
     * Bila dua di antaranya berbagi satu kode, membuka lembar teori akan
     * menyajikan lembar praktik — dan keduanya tampak benar, sebab
     * keduanya PDF ujian dengan kop yang sama.
     */
    #[Test]
    public function tiap_berkas_uji_unit_punya_jenis_sajian_sendiri(): void
    {
        $kode = array_map(
            fn ($k) => LampiranMiners::jenisUnit($k), array_keys(LampiranMiners::UNIT));

        $this->assertSame(count($kode), count(array_unique($kode)),
            'Dua berkas uji unit berbagi satu kode jenis.');

        $tersaji = Berkas::tersaji();

        foreach ($kode as $j) {
            $this->assertArrayHasKey($j, $tersaji,
                "Jenis {$j} tidak terdaftar, jadi berkasnya tidak dapat dibuka.");

            $this->assertSame(PasporKartuUnit::class, $tersaji[$j][0],
                "Jenis {$j} menunjuk model yang salah.");
        }
    }

    /**
     * Kode unit tidak boleh bertabrakan dengan kode lampiran kartu.
     *
     * `berkas_rambu` pada unit dan lampiran kartu berejaan mirip; tanpa
     * awalan yang membedakan, salah satunya akan menimpa yang lain di
     * dalam daftar tersaji — dan yang tertimpa hilang tanpa galat.
     */
    #[Test]
    public function kode_unit_tidak_bertabrakan_dengan_kode_lampiran_kartu(): void
    {
        $unit  = array_map(fn ($k) => LampiranMiners::jenisUnit($k), array_keys(LampiranMiners::UNIT));
        $kartu = array_map(fn ($k) => Berkas::jenisLampiran($k), LampiranMiners::kolom());

        $this->assertEmpty(array_intersect($unit, $kartu),
            'Kode berkas unit bertabrakan dengan kode lampiran kartu: '
            .implode(', ', array_intersect($unit, $kartu)));
    }

    #[Test]
    public function sertifikat_terdaftar_menunjuk_kolom_berkasnya(): void
    {
        $tersaji = Berkas::tersaji();

        $this->assertArrayHasKey('srt', $tersaji);
        $this->assertSame(PasporSertifikat::class, $tersaji['srt'][0]);
        $this->assertSame('berkas', $tersaji['srt'][1]);
    }

    /* ═══════════ 2 · unggahan sungguhan ═══════════ */

    #[Test]
    public function pintu_unggahan_menerima_kolom_unit_dan_sertifikat(): void
    {
        Storage::fake(Berkas::TERTUTUP);

        foreach (['berkas_rambu', 'berkas_teori', 'hasil_praktek', 'evaluasi', 'berkas'] as $kolom) {
            $r = $this->post('/miners/lampiran', [
                'kolom'  => $kolom,
                'berkas' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
            ]);

            $r->assertOk();
            Storage::disk(Berkas::TERTUTUP)->assertExists($r->json('jalur'));
        }
    }

    /**
     * Daftar putihnya tetap daftar putih.
     *
     * Melebarkannya untuk menampung dua katalog baru mudah berubah
     * menjadi tidak memeriksa apa pun — dan pintu unggahan yang tidak
     * memeriksa kolomnya menerima nama kolom apa saja.
     */
    #[Test]
    public function pintu_unggahan_tetap_menolak_kolom_di_luar_katalog(): void
    {
        Storage::fake(Berkas::TERTUTUP);

        $this->post('/miners/lampiran', [
            'kolom'  => 'nilai_praktek',      // kolom nyata, tetapi bukan berkas
            'berkas' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('kolom');
    }

    #[Test]
    public function berkas_uji_unit_yang_tersimpan_dapat_dibuka(): void
    {
        Storage::fake(Berkas::TERTUTUP);
        $k = $this->kartu();

        $jalur = $this->unggah('hasil_praktek', 'Praktek Rusdi.pdf');

        $this->post("/miners/{$this->p->id}/kartu/{$k->id}/unit", [
            'jenis_unit' => 'EXCAVATOR', 'hasil_praktek' => $jalur,
        ])->assertSessionHasNoErrors();

        $u = $k->fresh()->unit()->first();

        $this->assertSame($jalur, $u->hasil_praktek);

        $this->get(route('berkas.sajikan', [
            'jenis' => LampiranMiners::jenisUnit('hasil_praktek'), 'baris' => $u->id,
        ]))->assertOk();
    }

    #[Test]
    public function sertifikat_menyimpan_berkasnya_dan_dapat_dibuka(): void
    {
        Storage::fake(Berkas::TERTUTUP);

        $jalur = $this->unggah('berkas', 'POP Rusdi.pdf');

        $this->post("/miners/{$this->p->id}/sertifikat", [
            'nama' => 'Pengawas Operasional Pratama', 'berkas' => $jalur,
        ])->assertSessionHasNoErrors();

        $s = $this->p->sertifikat()->first();

        $this->assertSame($jalur, $s->berkas,
            'Berkas sertifikat tidak tersimpan — kolomnya tetap tak terjangkau.');

        $this->get(route('berkas.sajikan', ['jenis' => 'srt', 'baris' => $s->id]))->assertOk();
    }

    /* ═══════════ 3 · batas perusahaan ═══════════ */

    /**
     * Berkas milik perusahaan lain TIDAK DAPAT DITEMUKAN.
     *
     * Bukan 403 melainkan 404, dan bedanya bermakna: 403 mengakui bahwa
     * barisnya ada. Yang menegakkannya bukan pemeriksaan yang ditulis
     * ulang di controller melainkan scope pada modelnya — dan uji ini
     * ada untuk memastikan model barunya memang memakainya.
     */
    #[Test]
    public function berkas_unit_perusahaan_lain_tidak_dapat_dibuka(): void
    {
        Storage::fake(Berkas::TERTUTUP);
        $k = $this->kartu();

        $jalur = $this->unggah('berkas_teori');

        $this->post("/miners/{$this->p->id}/kartu/{$k->id}/unit", [
            'jenis_unit' => 'DUMP TRUCK', 'berkas_teori' => $jalur,
        ]);

        $u = $k->fresh()->unit()->first();

        $lain = Company::create(['name' => 'PT Sebelah', 'doc_no_prefix' => 'SBL']);
        $orangLain = User::factory()->create([
            'company_id' => $lain->id, 'email_verified_at' => now(),
        ]);

        $this->actingAs($orangLain)
            ->get(route('berkas.sajikan', [
                'jenis' => LampiranMiners::jenisUnit('berkas_teori'), 'baris' => $u->id,
            ]))
            ->assertNotFound();
    }

    #[Test]
    public function berkas_sertifikat_perusahaan_lain_tidak_dapat_dibuka(): void
    {
        Storage::fake(Berkas::TERTUTUP);

        $jalur = $this->unggah('berkas');

        $this->post("/miners/{$this->p->id}/sertifikat", ['nama' => 'POP', 'berkas' => $jalur]);

        $s = $this->p->sertifikat()->first();

        $lain = Company::create(['name' => 'PT Seberang', 'doc_no_prefix' => 'SBR']);
        $orangLain = User::factory()->create([
            'company_id' => $lain->id, 'email_verified_at' => now(),
        ]);

        $this->actingAs($orangLain)
            ->get(route('berkas.sajikan', ['jenis' => 'srt', 'baris' => $s->id]))
            ->assertNotFound();
    }

    /* ═══════════ 4 · yang wajib disebut di muka ═══════════ */

    #[Test]
    public function berkas_uji_wajib_yang_belum_ada_disebutkan(): void
    {
        $k = $this->kartu();
        $u = $k->unit()->create(['jenis_unit' => 'EXCAVATOR', 'berkas_rambu' => 'a.pdf']);

        $kurang = LampiranMiners::unitKurang($u->fresh());

        $this->assertContains('Berkas Teori', $kurang);
        $this->assertContains('Hasil Praktek', $kurang);

        /* Yang sudah ada tidak ikut disebut. */
        $this->assertNotContains('Berkas Rambu', $kurang);

        /* Evaluasi Test TIDAK wajib — di D'Best pun ia yang tidak
           bertanda bintang. Daftar yang memuat semuanya membuat yang
           benar-benar menahan tidak terlihat. */
        $this->assertNotContains('Evaluasi Test', $kurang);
    }

    /**
     * Unit yang tertulis tanpa lembar ujinya MENAHAN pengajuan.
     *
     * Barisnya membuat kartu itu terbaca sebagai kewenangan yang sudah
     * diuji, dan pengawas di gerbang tidak punya cara membedakan unit
     * yang lembar ujinya ada dari yang tidak.
     */
    #[Test]
    public function unit_tanpa_lembar_uji_menahan_pengajuan_license(): void
    {
        $this->siapkanSyaratLicense();

        $lisensi = $this->kartu([
            'golongan' => 'Alat Berat', 'sim_polisi' => 'SIM-B2-1',
            'sim_polisi_expired' => now()->addYear(), 'berkas_ddt' => 'ddt.pdf',
        ]);

        $lisensi->unit()->create(['jenis_unit' => 'EXCAVATOR']);

        $kurang = implode(' ', $lisensi->fresh()->syaratKurang());

        $this->assertStringContainsString('EXCAVATOR', $kurang);
        $this->assertStringContainsString('Berkas Teori', $kurang);

        $this->post(route('miners.kartu.ajukan', [$this->p, $lisensi]))
            ->assertSessionHasErrors('kartu');

        $this->assertSame(Alur::DRAF, $lisensi->refresh()->status);
    }

    /**
     * Lisensi yang BELUM menyebut unit tidak dihalangi oleh syarat ini.
     *
     * Kekosongan terlihat kosong; ia keadaan yang sah dan sudah dijaga
     * syarat lain. Menghalanginya di sini akan membuat setiap lisensi
     * harus lengkap unitnya sebelum boleh diajukan sama sekali — dan
     * yang tidak dapat diajukan diurus di luar sistem.
     */
    #[Test]
    public function license_tanpa_unit_tidak_dihalangi_berkas_uji(): void
    {
        $this->siapkanSyaratLicense();

        $lisensi = $this->kartu([
            'golongan' => 'Alat Berat', 'sim_polisi' => 'SIM-B2-1',
            'sim_polisi_expired' => now()->addYear(), 'berkas_ddt' => 'ddt.pdf',
        ]);

        $this->assertSame([], $lisensi->fresh()->syaratKurang(),
            'Lisensi tanpa unit dihalangi syarat berkas uji.');

        $this->post(route('miners.kartu.ajukan', [$this->p, $lisensi]))->assertRedirect();
        $this->assertSame(Alur::DIAJUKAN, $lisensi->refresh()->status);
    }

    /** Lengkap berkas ujinya — lisensinya boleh diajukan. */
    #[Test]
    public function unit_yang_lengkap_lembar_ujinya_tidak_menahan(): void
    {
        $this->siapkanSyaratLicense();

        $lisensi = $this->kartu([
            'golongan' => 'Alat Berat', 'sim_polisi' => 'SIM-B2-1',
            'sim_polisi_expired' => now()->addYear(), 'berkas_ddt' => 'ddt.pdf',
        ]);

        $lisensi->unit()->create([
            'jenis_unit' => 'EXCAVATOR',
            'berkas_rambu' => 'a.pdf', 'berkas_teori' => 'b.pdf', 'hasil_praktek' => 'c.pdf',
        ]);

        $this->assertSame([], $lisensi->fresh()->syaratKurang(),
            'Unit yang lembar ujinya lengkap masih menahan pengajuan.');
    }

    /* ═══════════ 5 · yang sampai ke layar ═══════════ */

    #[Test]
    public function halaman_rincian_membawa_berkas_uji_tiap_unit(): void
    {
        Storage::fake(Berkas::TERTUTUP);
        $k = $this->kartu();

        $jalur = $this->unggah('berkas_rambu');

        $this->post("/miners/{$this->p->id}/kartu/{$k->id}/unit", [
            'jenis_unit' => 'EXCAVATOR', 'berkas_rambu' => $jalur,
        ]);

        $props = $this->get("/miners/{$this->p->id}")->viewData('page')['props'];
        $baris = collect($props['kartu'])->firstWhere('id', $k->id);
        $unit  = $baris['unit'][0];

        $this->assertCount(count(LampiranMiners::UNIT), $unit['berkas']);

        $rambu = collect($unit['berkas'])->firstWhere('kolom', 'berkas_rambu');

        $this->assertTrue($rambu['ada']);

        /* Yang dikirim harus ALAMAT MEMBUKANYA, bukan jalur simpannya.
           Keduanya sama-sama string tak kosong — memeriksa "tidak null"
           saja tidak membedakan keduanya, dan bentuk lama justru
           mengirim jalur simpan yang tampak benar di prop dan tidak
           dapat ditekan di layar. */
        $this->assertNotNull($rambu['url'], 'Berkas rambu tidak membawa alamat membukanya.');

        $this->assertStringContainsString(
            route('berkas.sajikan', [
                'jenis' => LampiranMiners::jenisUnit('berkas_rambu'), 'baris' => $unit['id'],
            ]),
            $rambu['url'],
            'Yang terkirim bukan alamat sajian — mungkin jalur simpannya yang bocor.');

        $this->get($rambu['url'])->assertOk();

        $teori = collect($unit['berkas'])->firstWhere('kolom', 'berkas_teori');

        $this->assertFalse($teori['ada']);
        $this->assertNull($teori['url']);

        $this->assertContains('Berkas Teori', $unit['berkasKurang']);
    }

    #[Test]
    public function halaman_rincian_membawa_alamat_berkas_sertifikat(): void
    {
        Storage::fake(Berkas::TERTUTUP);

        $jalur = $this->unggah('berkas');

        $this->post("/miners/{$this->p->id}/sertifikat", ['nama' => 'POP', 'berkas' => $jalur]);
        $this->post("/miners/{$this->p->id}/sertifikat", ['nama' => 'Ahli K3 Umum']);

        $props = $this->get("/miners/{$this->p->id}")->viewData('page')['props'];

        $berlembar = collect($props['sertifikat'])->firstWhere('nama', 'POP');
        $telanjang = collect($props['sertifikat'])->firstWhere('nama', 'Ahli K3 Umum');

        $this->assertNull($telanjang['berkas'],
            'Sertifikat tanpa berkas membawa alamat yang akan memulangkan 404.');

        /* Yang dikirim harus ALAMAT MEMBUKANYA, bukan jalur simpannya.
           Keduanya sama-sama string tak kosong, jadi memeriksa "ada
           isinya" saja tidak membedakan keduanya — dan jalur simpan yang
           bocor ke layar bukan hanya tidak dapat ditekan, ia juga
           menyebutkan susunan folder di dalam server. */
        $this->assertNotNull($berlembar['berkas'],
            'Sertifikat yang berkasnya ada tidak membawa alamat membukanya.');

        $this->assertStringContainsString(
            route('berkas.sajikan', ['jenis' => 'srt', 'baris' => $berlembar['id']]),
            $berlembar['berkas'],
            'Yang terkirim bukan alamat sajian — mungkin jalur simpannya yang bocor.');

        $this->get($berlembar['berkas'])->assertOk();
    }

    /**
     * Katalog medan unggah ikut terkirim, dan datang dari katalog yang sama.
     *
     * Layar yang menyusun medannya sendiri adalah salinan kedua dari
     * daftar lampiran — dan salinan kedua sudah pernah berselisih di
     * modul ini: `berkas_lotto` ada di basis data dan di aturan validasi
     * tanpa punya medan sama sekali.
     */
    #[Test]
    public function halaman_rincian_membawa_katalog_medan_unggah(): void
    {
        $props = $this->get("/miners/{$this->p->id}")->viewData('page')['props'];

        $this->assertSame(
            array_keys(LampiranMiners::UNIT),
            array_column($props['opsi']['berkasUnit'], 'kolom'),
            'Katalog berkas unit di layar berbeda dari katalog yang memeriksanya.');

        $this->assertSame(
            array_keys(LampiranMiners::SERTIFIKAT),
            array_column($props['opsi']['berkasSertifikat'], 'kolom'));

        $this->assertSame(Authority::AUTHORITY_UNIT, $props['opsi']['authorityUnit']);
        $this->assertArrayHasKey('unit', $props['opsi']);
    }

    /**
     * Hanya SIMPER yang menyebut unit.
     *
     * Kartu masuk area tidak menyebut unit sama sekali; menggambarkan
     * tabel kosong di sana membuat pembacanya mengira ada yang belum
     * diisi, lalu mengisinya — dan unit yang tercantum pada Mine Permit
     * adalah kewenangan mengemudi yang tidak pernah ditinjau siapa pun.
     */
    #[Test]
    public function hanya_mine_license_yang_menyebut_unit(): void
    {
        $lisensi = $this->kartu();
        $permit  = $this->kartu(['jenis' => AlurMiner::KARTU_PERMIT]);
        $tamu    = $this->kartu(['jenis' => AlurMiner::KARTU_VISITOR]);

        $props = $this->get("/miners/{$this->p->id}")->viewData('page')['props'];
        $baris = collect($props['kartu'])->keyBy('id');

        $this->assertTrue($baris[$lisensi->id]['punyaUnit']);
        $this->assertFalse($baris[$permit->id]['punyaUnit']);
        $this->assertFalse($baris[$tamu->id]['punyaUnit']);
    }

    /** Syarat lain SIMPER, supaya yang diuji hanya berkas ujinya. */
    private function siapkanSyaratLicense(): void
    {
        $this->p->mcu()->create([
            'tgl_periksa' => now(), 'tgl_expired' => now()->addYear(), 'hasil' => 'Fit',
        ]);

        $this->p->induksi()->create([
            'jenis' => 'Awal', 'tanggal' => now(), 'tgl_expired' => now()->addYear(),
            'hasil' => 'Lulus',
        ]);

        $permit = $this->kartu([
            'jenis' => AlurMiner::KARTU_PERMIT, 'tgl_expired' => now()->addYear(),
        ]);

        PasporKartu::whereKey($permit->id)->update(['status' => Alur::DISETUJUI]);
    }
}
