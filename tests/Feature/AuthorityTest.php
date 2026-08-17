<?php

namespace Tests\Feature;

use App\Models\{Company, KompetensiJenis, Paspor, User};
use App\Support\{Authority, MasterKompetensi};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Authority — berkas kelayakan kerja.
 *
 * Yang diuji di sini bukan tampilan halamannya melainkan jawaban atas
 * pertanyaan gerbang: boleh atau tidak orang ini bekerja hari ini.
 * Jawaban itu dipakai menahan orang di pintu masuk tambang, jadi
 * salahnya punya dua bentuk yang sama buruknya — menahan orang yang
 * sebenarnya berhak, dan meloloskan orang yang MCU-nya sudah habis.
 */
class AuthorityTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;

    protected function setUp(): void
    {
        parent::setUp();

        $this->c = Company::create(['name' => 'PT Uji Authority', 'doc_no_prefix' => 'UA']);

        $this->actingAs(User::factory()->create([
            'is_admin' => true, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]));
    }

    private function orang(string $nama = 'Budi'): Paspor
    {
        return Paspor::create(['company_id' => $this->c->id, 'nama' => $nama]);
    }

    /* ═══════════ ambang masa berlaku ═══════════ */

    /**
     * Empat tingkat, dan batasnya persis.
     *
     * Diuji tepat di batasnya, bukan di tengah pita: kesalahan
     * perbandingan (< versus <=) hanya muncul pada nilai batas, dan uji
     * yang memakai 45 hari untuk pita 31–90 tidak akan pernah
     * menemukannya.
     */
    public function test_ambang_keadaan_tepat_di_batasnya(): void
    {
        $kini = Carbon::parse('2026-08-17');

        $pada = fn (int $hari) => Authority::keadaan(
            $kini->copy()->addDays($hari)->toDateString(), $kini);

        $this->assertSame(Authority::AMAN,      $pada(181));
        $this->assertSame(Authority::PERHATIAN, $pada(180));
        $this->assertSame(Authority::PERHATIAN, $pada(91));
        $this->assertSame(Authority::SEGERA,    $pada(90));
        $this->assertSame(Authority::SEGERA,    $pada(31));
        $this->assertSame(Authority::KRITIS,    $pada(30));
        $this->assertSame(Authority::KRITIS,    $pada(0));
        $this->assertSame(Authority::KRITIS,    $pada(-1));
        $this->assertSame(Authority::KRITIS,    $pada(-400));
    }

    /**
     * Tanpa tanggal BUKAN aman.
     *
     * "Tidak diketahui kapan habis" dan "masih lama habisnya" adalah dua
     * keadaan yang berlawanan artinya. Menyamakannya membuat orang yang
     * datanya belum lengkap tampil sebagai orang yang paling siap — dan
     * daftar yang diurutkan menurut kemendesakan menaruhnya paling
     * bawah, tempat ia tidak akan pernah dilihat.
     */
    public function test_tanpa_tanggal_bukan_aman(): void
    {
        $this->assertSame(Authority::TAK_BERTANGGAL, Authority::keadaan(null));
        $this->assertSame(Authority::TAK_BERTANGGAL, Authority::keadaan(''));

        $this->assertNull(Authority::sisaHari(null));
        $this->assertSame('tanggal belum diisi', Authority::keterangan(null));
    }

    /**
     * Sisa hari tidak berubah sepanjang hari.
     *
     * Dihitung dari awal hari kedua sisinya. Tanpa itu, sertifikat yang
     * habis hari ini memulangkan pecahan hari yang membulat berbeda
     * tergantung jam berapa halamannya dibuka.
     */
    public function test_sisa_hari_tidak_bergantung_jam(): void
    {
        $target = '2026-08-27';

        $pagi  = Authority::sisaHari($target, Carbon::parse('2026-08-17 06:00'));
        $malam = Authority::sisaHari($target, Carbon::parse('2026-08-17 23:45'));

        $this->assertSame(10, $pagi);
        $this->assertSame($pagi, $malam);
    }

    public function test_keterangan_menyebut_lewat_bukan_angka_minus(): void
    {
        $kini = Carbon::parse('2026-08-17');

        $this->assertSame('lewat 14 hari',
            Authority::keterangan($kini->copy()->subDays(14)->toDateString(), $kini));
        $this->assertSame('habis hari ini',
            Authority::keterangan($kini->toDateString(), $kini));
        $this->assertSame('30 hari lagi',
            Authority::keterangan($kini->copy()->addDays(30)->toDateString(), $kini));
    }

    /* ═══════════ kelayakan kerja ═══════════ */

    public function test_lengkap_dan_berlaku_berarti_boleh_bekerja(): void
    {
        $p = $this->orang();

        $p->mcu()->create([
            'tgl_periksa' => now()->subMonths(2), 'tgl_expired' => now()->addMonths(10),
            'hasil' => 'Fit',
        ]);
        $p->kartu()->create(['jenis' => 'ID Card', 'tgl_expired' => now()->addMonths(6)]);

        $this->assertTrue($p->refresh()->kelayakan()['layak']);
    }

    /**
     * Tiap sebab ketidaklayakan disebut, bukan hanya vonisnya.
     *
     * "Tidak layak" tanpa sebab memaksa pengawas membuka tiga halaman
     * untuk mencarinya sendiri, di gerbang, sambil antrean memanjang.
     */
    public function test_setiap_sebab_tidak_layak_disebut(): void
    {
        $kasus = [
            'MCU kadaluarsa' => [
                ['tgl_periksa' => now()->subYear(), 'tgl_expired' => now()->subDay(), 'hasil' => 'Fit'],
                ['jenis' => 'ID Card', 'tgl_expired' => now()->addYear()],
            ],
            'hasil MCU Unfit' => [
                ['tgl_periksa' => now(), 'tgl_expired' => now()->addYear(), 'hasil' => 'Unfit'],
                ['jenis' => 'ID Card', 'tgl_expired' => now()->addYear()],
            ],
            'kartu masuk kadaluarsa' => [
                ['tgl_periksa' => now(), 'tgl_expired' => now()->addYear(), 'hasil' => 'Fit'],
                ['jenis' => 'ID Card', 'tgl_expired' => now()->subDay()],
            ],
        ];

        foreach ($kasus as $harusDisebut => [$mcu, $kartu]) {
            $p = $this->orang('Orang '.$harusDisebut);
            $p->mcu()->create($mcu);
            $p->kartu()->create($kartu);

            $hasil = $p->refresh()->kelayakan();

            $this->assertFalse($hasil['layak'], "Seharusnya tidak layak: {$harusDisebut}");
            $this->assertContains($harusDisebut, $hasil['sebab'],
                'Sebabnya tidak disebut, hanya vonisnya: '.implode(', ', $hasil['sebab']));
        }
    }

    /**
     * "Fit With Note" tetap boleh bekerja.
     *
     * Pembatasannya dicatat dan ditampilkan, tetapi ia bukan penolakan
     * masuk — memperlakukannya sebagai penolakan menahan orang yang
     * sesungguhnya berhak bekerja, hanya dengan batasan pekerjaan.
     */
    public function test_fit_with_note_tetap_boleh_bekerja(): void
    {
        $p = $this->orang();

        $p->mcu()->create([
            'tgl_periksa' => now(), 'tgl_expired' => now()->addYear(),
            'hasil' => 'Fit With Note', 'pembatasan' => 'Tidak boleh bekerja di ketinggian.',
        ]);
        $p->kartu()->create(['jenis' => 'ID Card', 'tgl_expired' => now()->addYear()]);

        $this->assertTrue($p->refresh()->kelayakan()['layak']);
    }

    /**
     * Berkas yang belum ada sama sekali bukan berkas yang lolos.
     *
     * Orang tanpa satu pun catatan MCU adalah orang yang belum
     * diperiksa, dan itu bukan alasan untuk membolehkannya masuk.
     */
    public function test_tanpa_mcu_dan_kartu_tidak_boleh_bekerja(): void
    {
        $hasil = $this->orang()->kelayakan();

        $this->assertFalse($hasil['layak']);
        $this->assertContains('MCU belum ada', $hasil['sebab']);
        $this->assertContains('kartu masuk belum ada', $hasil['sebab']);
    }

    /**
     * MCU TERAKHIR yang dipakai, bukan MCU yang kebetulan masih berlaku.
     *
     * Bila pemeriksaan terbaru menyatakan Unfit, MCU lama yang masa
     * berlakunya belum habis tidak boleh menyelamatkannya.
     */
    public function test_mcu_terakhir_yang_menentukan_bukan_yang_masih_berlaku(): void
    {
        $p = $this->orang();

        $p->mcu()->create([
            'tgl_periksa' => now()->subMonths(6), 'tgl_expired' => now()->addMonths(6),
            'hasil' => 'Fit',
        ]);
        $p->mcu()->create([
            'tgl_periksa' => now()->subDay(), 'tgl_expired' => now()->addYear(),
            'hasil' => 'Unfit',
        ]);
        $p->kartu()->create(['jenis' => 'ID Card', 'tgl_expired' => now()->addYear()]);

        $hasil = $p->refresh()->kelayakan();

        $this->assertFalse($hasil['layak'], 'MCU lama yang masih berlaku menutupi hasil Unfit terbaru.');
        $this->assertContains('hasil MCU Unfit', $hasil['sebab']);
    }

    /* ═══════════ satu orang, banyak sertifikat ═══════════ */

    /**
     * Inilah pokok perubahannya terhadap ko_personnel.
     *
     * Seorang pengawas dapat memegang POP yang berlaku sampai 2028 dan
     * Ahli K3 Kebakaran yang habis bulan depan. Satu tanggal untuk
     * keduanya membuat salah satunya selalu salah.
     */
    public function test_satu_orang_banyak_sertifikat_dengan_masa_berlaku_sendiri(): void
    {
        $p = $this->orang();

        $p->sertifikat()->create(['nama' => 'POP',              'tgl_expired' => now()->addYears(2)]);
        $p->sertifikat()->create(['nama' => 'Ahli K3 Kebakaran','tgl_expired' => now()->addDays(20)]);
        $p->sertifikat()->create(['nama' => 'Petugas P3K',      'tgl_expired' => null]);

        $p->refresh()->load(['sertifikat', 'mcu', 'kartu']);

        $this->assertCount(3, $p->sertifikat);

        $keadaan = $p->sertifikat->mapWithKeys(fn ($s) => [$s->nama => $s->keadaan()]);

        $this->assertSame(Authority::AMAN,            $keadaan['POP']);
        $this->assertSame(Authority::KRITIS,          $keadaan['Ahli K3 Kebakaran']);
        $this->assertSame(Authority::TAK_BERTANGGAL,  $keadaan['Petugas P3K']);
    }

    /**
     * Keadaan seseorang adalah yang TERBURUK di antara berkasnya.
     *
     * Orang yang MCU-nya aman tetapi satu sertifikatnya kadaluarsa
     * adalah orang yang perlu ditindak; daftar yang menampilkan keadaan
     * terbaiknya akan menyembunyikan itu.
     */
    public function test_keadaan_orang_diambil_dari_berkas_terburuknya(): void
    {
        $p = $this->orang();

        $p->mcu()->create(['tgl_periksa' => now(), 'tgl_expired' => now()->addYears(2), 'hasil' => 'Fit']);
        $p->kartu()->create(['jenis' => 'ID Card', 'tgl_expired' => now()->addYears(2)]);
        $p->sertifikat()->create(['nama' => 'POP', 'tgl_expired' => now()->subDays(5)]);

        $p->refresh()->load(['sertifikat', 'mcu', 'kartu']);

        $this->assertSame(Authority::KRITIS, $p->keadaanTerburuk());
    }

    /* ═══════════ master kompetensi ═══════════ */

    public function test_master_51_kompetensi_tertanam_dan_aman_diulang(): void
    {
        MasterKompetensi::tanam();
        $pertama = KompetensiJenis::withoutGlobalScopes()->count();

        MasterKompetensi::tanam();

        $this->assertSame(51, $pertama);
        $this->assertSame(51, KompetensiJenis::withoutGlobalScopes()->count(),
            'Menanam dua kali menggandakan masternya.');
    }

    /**
     * Master nasional terlihat oleh setiap perusahaan.
     *
     * Ia ber-company_id NULL, dan scope MilikPerusahaan memperlakukan
     * NULL sebagai milik bersama — pola yang sama dipakai kursus dan
     * template inspeksi.
     */
    public function test_master_terlihat_lintas_perusahaan(): void
    {
        MasterKompetensi::tanam();

        $lain = Company::create(['name' => 'PT Lain', 'doc_no_prefix' => 'PL']);
        $this->actingAs(User::factory()->create(['company_id' => $lain->id]));

        $this->assertSame(51, KompetensiJenis::aktif()->count());
    }

    /* ═══════════ batas perusahaan ═══════════ */

    public function test_paspor_perusahaan_lain_tidak_terbaca(): void
    {
        $this->orang('Milik A');

        /* Sebagai pengguna BIASA. Administrator EQOHSEE memang
           menjangkau seluruh perusahaan — itu keputusan yang sudah
           dibuat pada MilikPerusahaan — jadi menguji batasnya lewat
           admin akan selalu gagal, dan memperbaikinya akan merusak
           kemampuan admin melihat lintas perusahaan. */
        $this->actingAs(User::factory()->create([
            'is_admin' => false, 'company_id' => $this->c->id,
        ]));

        $lain = Company::create(['name' => 'PT Lain', 'doc_no_prefix' => 'PL']);
        Paspor::withoutGlobalScopes()->create(['company_id' => $lain->id, 'nama' => 'Milik B']);

        $this->assertSame(['Milik A'], Paspor::pluck('nama')->all());
    }

    /**
     * Sertifikat, MCU, dan kartu tidak punya company_id sendiri — batas
     * perusahaannya diwarisi dari paspornya. Yang diuji: warisan itu
     * benar-benar berlaku, bukan hanya dideklarasikan.
     */
    public function test_berkas_anak_ikut_batas_perusahaan_induknya(): void
    {
        $this->orang('Milik A')->sertifikat()->create(['nama' => 'POP A']);

        $lain = Company::create(['name' => 'PT Lain', 'doc_no_prefix' => 'PL']);
        $pB = Paspor::withoutGlobalScopes()->create(['company_id' => $lain->id, 'nama' => 'Milik B']);
        $pB->sertifikat()->create(['nama' => 'POP B']);

        $this->actingAs(User::factory()->create([
            'is_admin' => false, 'company_id' => $this->c->id,
        ]));

        $this->assertSame(['POP A'],
            \App\Models\PasporSertifikat::pluck('nama')->all());
    }

    /* ═══════════ halaman ═══════════ */

    public function test_halaman_daftar_terbuka_dan_membawa_ringkasannya(): void
    {
        $p = $this->orang();
        $p->mcu()->create(['tgl_periksa' => now(), 'tgl_expired' => now()->subDay(), 'hasil' => 'Fit']);

        $this->get(route('authority.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Authority/Halaman')
                ->where('ringkas.orang', 1)
                ->where('ringkas.takLayak', 1));
    }

    public function test_halaman_rincian_terbuka(): void
    {
        $p = $this->orang('Rahmat');

        $this->get(route('authority.show', $p))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Authority/Halaman')
                ->where('p.nama', 'Rahmat'));
    }

    public function test_sertifikat_dapat_ditambahkan_lewat_halaman(): void
    {
        $p = $this->orang();

        $this->post(route('authority.sertifikat.simpan', $p), [
            'nama' => 'Pengawas Operasional Pertama (POP)',
            'lembaga' => 'BNSP',
            'tgl_expired' => now()->addYear()->toDateString(),
        ])->assertRedirect();

        $this->assertSame(1, $p->refresh()->sertifikat()->count());
    }
}
