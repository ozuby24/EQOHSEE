<?php

namespace Tests\Feature;

use App\Models\{Company, KompetensiJenis, McuPengajuan, Paspor, PasporKartu, User};
use App\Support\{Alur, Authority, MasterKompetensi, Tahap};
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

    /**
     * Induksi yang lulus dan masih berlaku.
     *
     * Dipakai oleh uji yang pokoknya BUKAN induksi. Sejak induksi
     * menjadi syarat, orang tanpa induksi selalu tidak layak — sehingga
     * uji tentang MCU yang tidak memasangnya akan lulus karena sebab
     * yang salah, dan tetap lulus seandainya pemeriksaan MCU-nya dicabut
     * seluruhnya.
     */
    private function induksi(Paspor $p, ?string $expired = null): void
    {
        $p->induksi()->create([
            'jenis'       => 'Awal',
            'tanggal'     => now()->subMonths(2),
            'tgl_expired' => $expired ?? now()->addYear(),
            'hasil'       => 'Lulus',
        ]);
    }

    /**
     * Kartu yang benar-benar berlaku — dibuat lalu disetujui.
     *
     * Statusnya dipasang lewat pembaruan langsung, bukan lewat
     * ajukan()+setujui(). Alurnya menolak peninjau yang sama dengan
     * pengajunya, dan uji ini hanya bertindak sebagai satu orang. Yang
     * memeriksa alurnya sendiri adalah uji tersendiri di bawah, dengan
     * dua pengguna sungguhan — di sini kartunya cuma perlu ada dan sah.
     */
    private function kartu(Paspor $p, array $atribut): PasporKartu
    {
        $k = $p->kartu()->create($atribut);

        PasporKartu::whereKey($k->id)->update(['status' => Alur::DISETUJUI]);

        return $k->refresh();
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

        $this->induksi($p);
        $p->mcu()->create([
            'tgl_periksa' => now()->subMonths(2), 'tgl_expired' => now()->addMonths(10),
            'hasil' => 'Fit',
        ]);
        $this->kartu($p, ['jenis' => 'ID Card', 'tgl_expired' => now()->addMonths(6)]);

        $hasil = $p->refresh()->kelayakan();

        $this->assertTrue($hasil['layak'], implode(' · ', $hasil['sebab']));
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
            $this->induksi($p);
            $p->mcu()->create($mcu);
            $this->kartu($p, $kartu);

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

        $this->induksi($p);
        $p->mcu()->create([
            'tgl_periksa' => now(), 'tgl_expired' => now()->addYear(),
            'hasil' => 'Fit With Note', 'pembatasan' => 'Tidak boleh bekerja di ketinggian.',
        ]);
        $this->kartu($p, ['jenis' => 'ID Card', 'tgl_expired' => now()->addYear()]);

        $hasil = $p->refresh()->kelayakan();

        $this->assertTrue($hasil['layak'], implode(' · ', $hasil['sebab']));
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
        $this->assertContains('induksi belum ada', $hasil['sebab']);
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
        $this->induksi($p);
        $this->kartu($p, ['jenis' => 'ID Card', 'tgl_expired' => now()->addYear()]);

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

    /* ═══════════ induksi ═══════════ */

    /**
     * Induksi yang habis menahan orang, walau MCU dan kartunya sempurna.
     *
     * Uji ini punya KONTROL: orang kedua identik dalam segala hal kecuali
     * tanggal induksinya. Tanpa kontrol itu, "tidak layak" dapat berarti
     * induksinya memang habis, atau berarti pemasangan datanya gagal dan
     * seluruh orang tidak layak — dan keduanya terbaca sama.
     */
    public function test_induksi_kadaluarsa_menahan_walau_sisanya_lengkap(): void
    {
        $lengkap = function (string $nama, string $induksiExpired): Paspor {
            $p = $this->orang($nama);
            $this->induksi($p, $induksiExpired);
            $p->mcu()->create([
                'tgl_periksa' => now()->subMonth(), 'tgl_expired' => now()->addYear(),
                'hasil' => 'Fit',
            ]);
            $this->kartu($p, ['jenis' => 'ID Card', 'tgl_expired' => now()->addYear()]);

            return $p->refresh();
        };

        $habis = $lengkap('Induksi habis', now()->subDay()->toDateString());
        $sah   = $lengkap('Induksi sah',   now()->addYear()->toDateString());

        $this->assertTrue($sah->kelayakan()['layak'],
            'Kontrolnya ikut gagal — yang diuji bukan induksinya: '
            .implode(' · ', $sah->kelayakan()['sebab']));

        $this->assertFalse($habis->kelayakan()['layak']);
        $this->assertContains('induksi kadaluarsa', $habis->kelayakan()['sebab']);
    }

    /**
     * Induksi yang TIDAK LULUS tidak menggugurkan yang lulus sebelumnya.
     *
     * Berbeda dari MCU, dan sengaja: hasil MCU terbaru adalah keadaan
     * kesehatan orangnya sekarang, sedangkan induksi yang tidak lulus
     * hanyalah percobaan yang belum berhasil. Induksi lulus sebelumnya
     * masih sah sampai masa berlakunya habis.
     */
    public function test_induksi_tidak_lulus_tidak_menggugurkan_yang_masih_berlaku(): void
    {
        $p = $this->orang();

        $this->induksi($p);                       // lulus, berlaku setahun
        $p->induksi()->create([                   // percobaan penyegaran, gagal
            'jenis' => 'Penyegaran', 'tanggal' => now(),
            'tgl_expired' => now()->addYear(), 'hasil' => 'Tidak Lulus',
        ]);

        $p->mcu()->create([
            'tgl_periksa' => now(), 'tgl_expired' => now()->addYear(), 'hasil' => 'Fit',
        ]);
        $this->kartu($p, ['jenis' => 'ID Card', 'tgl_expired' => now()->addYear()]);

        $hasil = $p->refresh()->kelayakan();

        $this->assertTrue($hasil['layak'], implode(' · ', $hasil['sebab']));
    }

    /* ═══════════ kartu: draf bukan kartu ═══════════ */

    /**
     * Kartu yang belum disetujui tidak meloloskan siapa pun.
     *
     * Ini pokok alurnya. Bila draf ikut meloloskan, alur persetujuannya
     * tidak menahan apa pun — siapa pun dapat meloloskan dirinya sendiri
     * hanya dengan mengisi formulir, dan tombol setujui menjadi hiasan.
     */
    public function test_kartu_draf_tidak_meloloskan(): void
    {
        $p = $this->orang();
        $this->induksi($p);
        $p->mcu()->create([
            'tgl_periksa' => now(), 'tgl_expired' => now()->addYear(), 'hasil' => 'Fit',
        ]);

        /* Sengaja TIDAK lewat helper: yang diuji justru kartu yang belum
           disetujui. */
        $p->kartu()->create(['jenis' => 'ID Card', 'tgl_expired' => now()->addYear()]);

        $hasil = $p->refresh()->kelayakan();

        $this->assertFalse($hasil['layak'], 'Kartu draf meloloskan orang di gerbang.');
        $this->assertContains('kartu masuk masih draf', $hasil['sebab']);

        /* Kontrol: kartu yang sama, disetujui, memang meloloskan. Tanpa
           ini, uji di atas tetap hijau seandainya orangnya tertahan oleh
           sebab lain sama sekali. */
        PasporKartu::whereKey($p->kartu()->first()->id)->update(['status' => Alur::DISETUJUI]);

        $this->assertTrue($p->refresh()->kelayakan()['layak']);
    }

    /**
     * SIMPER tidak dapat diajukan tanpa berkas syaratnya.
     *
     * Syaratnya diperiksa saat MENGAJUKAN, bukan saat menyimpan draf —
     * draf memang boleh setengah jadi.
     */
    public function test_simper_tanpa_berkas_syarat_tidak_dapat_diajukan(): void
    {
        $p = $this->orang();

        $k = $p->kartu()->create([
            'jenis' => 'SIMPER', 'golongan' => 'Alat Berat',
            'tgl_expired' => now()->addYear(),
        ]);

        $this->assertNotEmpty($k->syaratKurang(),
            'SIMPER tanpa SIM kepolisian dan DDT dianggap sudah lengkap.');

        $this->post(route('authority.kartu.ajukan', [$p, $k]))
            ->assertSessionHasErrors('kartu');

        $this->assertSame(Alur::DRAF, $k->refresh()->status);

        /* Dilengkapi, lalu berhasil diajukan — memastikan penolakan di
           atas berasal dari syaratnya, bukan dari rute yang memang selalu
           menolak. */
        $k->update([
            'berkas_induksi' => 'induksi/budi.pdf',
            'sim_polisi'     => 'SIM-B2-000001',
            'sim_polisi_expired' => now()->addYear(),
            'berkas_ddt'     => 'ddt/budi.pdf',
        ]);

        $this->post(route('authority.kartu.ajukan', [$p, $k]))->assertRedirect();

        $this->assertSame(Alur::DIAJUKAN, $k->refresh()->status);
    }

    /**
     * Kartu masuk biasa TIDAK menuntut syarat SIMPER.
     *
     * Menuntutnya akan menahan seluruh pekerja non-pengemudi oleh syarat
     * yang tidak berlaku bagi mereka — kegagalan yang terlihat seperti
     * ketegasan.
     */
    public function test_kartu_biasa_hanya_menuntut_bukti_induksi(): void
    {
        $p = $this->orang();

        $k = $p->kartu()->create([
            'jenis' => 'ID Card', 'tgl_expired' => now()->addYear(),
            'berkas_induksi' => 'induksi/budi.pdf',
        ]);

        $this->assertSame([], $k->syaratKurang());
    }

    /* ═══════════ pengajuan MCU per rombongan ═══════════ */

    /**
     * Menjadwalkan MCU berikutnya tidak menggugurkan MCU yang sekarang.
     *
     * Baris pengajuan lahir saat suratnya dikirim, berhari-hari sebelum
     * orangnya diperiksa, dan hasilnya masih kosong. Bila ia dihitung
     * sebagai MCU terakhir, setiap pekerja yang namanya baru dimasukkan
     * ke surat seketika berubah menjadi "MCU belum ada".
     */
    public function test_nama_dalam_pengajuan_tidak_menggugurkan_mcu_yang_sah(): void
    {
        $p = $this->orang();
        $this->induksi($p);
        $this->kartu($p, ['jenis' => 'ID Card', 'tgl_expired' => now()->addYear()]);

        $p->mcu()->create([
            'tgl_periksa' => now()->subMonths(3), 'tgl_expired' => now()->addMonths(9),
            'hasil' => 'Fit',
        ]);

        $this->assertTrue($p->refresh()->kelayakan()['layak'], 'Kontrol gagal sebelum diuji.');

        $pengajuan = McuPengajuan::create([
            'company_id' => $this->c->id, 'tanggal' => now(), 'jenis' => 'Berkala',
        ]);

        $this->post(route('authority.mcu.nama.tambah', $pengajuan), ['paspor_id' => $p->id])
            ->assertRedirect();

        $hasil = $p->refresh()->kelayakan();

        $this->assertTrue($hasil['layak'],
            'Menjadwalkan MCU berikutnya justru menggugurkan MCU yang masih sah: '
            .implode(' · ', $hasil['sebab']));
    }

    /**
     * Hasil yang belum kembali tersimpan KOSONG, bukan "Fit".
     *
     * Kolomnya dulu NOT NULL dengan nilai awal 'Fit'. Dengan pengajuan
     * rombongan, itu berarti setiap nama yang belum diperiksa terbaca
     * sehat — dinyatakan layak oleh sistem tanpa seorang dokter pun
     * melihatnya.
     */
    public function test_nama_yang_belum_diperiksa_hasilnya_kosong(): void
    {
        $p = $this->orang();

        $pengajuan = McuPengajuan::create([
            'company_id' => $this->c->id, 'tanggal' => now(), 'jenis' => 'Berkala',
        ]);

        $this->post(route('authority.mcu.nama.tambah', $pengajuan), ['paspor_id' => $p->id]);

        $this->assertNull($pengajuan->refresh()->hasil->first()->hasil);
        $this->assertSame(1, $pengajuan->belumKembali());
    }

    /** Satu orang tidak masuk dua kali ke surat yang sama. */
    public function test_nama_tidak_dapat_masuk_dua_kali(): void
    {
        $p = $this->orang();

        $pengajuan = McuPengajuan::create([
            'company_id' => $this->c->id, 'tanggal' => now(), 'jenis' => 'Berkala',
        ]);

        $this->post(route('authority.mcu.nama.tambah', $pengajuan), ['paspor_id' => $p->id]);
        $this->post(route('authority.mcu.nama.tambah', $pengajuan), ['paspor_id' => $p->id])
            ->assertSessionHasErrors('paspor_id');

        $this->assertSame(1, $pengajuan->refresh()->hasil->count());
    }

    /** Surat tanpa satu nama pun tidak dapat dikirim. */
    public function test_pengajuan_kosong_tidak_dapat_diajukan(): void
    {
        $pengajuan = McuPengajuan::create([
            'company_id' => $this->c->id, 'tanggal' => now(), 'jenis' => 'Berkala',
        ]);

        $this->post(route('authority.mcu.ajukan', $pengajuan))->assertSessionHasErrors('mcu');

        $this->assertSame(Alur::DRAF, $pengajuan->refresh()->status);
    }

    /**
     * Status tidak dapat disebut sendiri oleh pengirim datanya.
     *
     * Sekalipun 'status' => 'disetujui' ikut dikirim dalam payload,
     * barisnya lahir sebagai draf.
     */
    public function test_status_pengajuan_tidak_dapat_diisi_lewat_formulir(): void
    {
        $this->post(route('authority.mcu.store'), [
            'tanggal' => now()->toDateString(),
            'jenis'   => 'Berkala',
            'status'  => Alur::DISETUJUI,
        ])->assertRedirect();

        $this->assertSame(Alur::DRAF, McuPengajuan::first()->status);
    }

    /**
     * Peninjau bukan pengaju — diuji dengan dua pengguna sungguhan.
     *
     * Uji lain memasang status lewat pembaruan langsung supaya pokoknya
     * tetap pada kelayakan; yang memeriksa alurnya sendiri adalah uji
     * ini.
     */
    public function test_pengaju_tidak_dapat_menyetujui_pengajuannya_sendiri(): void
    {
        $pengaju = User::factory()->create([
            'is_admin' => true, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);
        $peninjau = User::factory()->create([
            'is_admin' => true, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);

        $p = $this->orang();

        $this->actingAs($pengaju);

        $pengajuan = McuPengajuan::create([
            'company_id' => $this->c->id, 'tanggal' => now(), 'jenis' => 'Berkala',
        ]);
        $pengajuan->hasil()->create(['paspor_id' => $p->id, 'tgl_periksa' => now()]);

        $this->post(route('authority.mcu.ajukan', $pengajuan))->assertRedirect();

        $this->post(route('authority.mcu.tinjau', $pengajuan), ['aksi' => 'setujui'])
            ->assertSessionHasErrors('alur');

        $this->assertSame(Alur::DIAJUKAN, $pengajuan->refresh()->status);

        /* Kontrol: orang lain memang dapat menyetujuinya. Tanpa ini,
           penolakan di atas dapat berarti rutenya rusak seluruhnya. */
        $this->actingAs($peninjau);

        $this->post(route('authority.mcu.tinjau', $pengajuan), ['aksi' => 'setujui'])
            ->assertRedirect();

        $this->assertSame(Alur::DISETUJUI, $pengajuan->refresh()->status);
    }

    /**
     * Rujukan medis tanpa tanggal tindak lanjut terhitung TERTUNGGAK.
     *
     * Rujukan yang tidak pernah ditagih adalah catatan yang sudah
     * lengkap di berkas dan tidak pernah terjadi di kenyataan.
     */
    public function test_rujukan_tanpa_tanggal_terhitung_tertunggak(): void
    {
        $p = $this->orang();

        $tanpaTanggal = $p->mcu()->create([
            'tgl_periksa' => now(), 'tgl_expired' => now()->addYear(),
            'hasil' => 'Fit With Note', 'rujukan' => 'Poli Jantung',
        ]);
        $masihWaktu = $p->mcu()->create([
            'tgl_periksa' => now(), 'tgl_expired' => now()->addYear(),
            'hasil' => 'Fit With Note', 'rujukan' => 'Poli Mata',
            'outstanding' => now()->addMonth(),
        ]);
        $tanpaRujukan = $p->mcu()->create([
            'tgl_periksa' => now(), 'tgl_expired' => now()->addYear(), 'hasil' => 'Fit',
        ]);

        $this->assertTrue($tanpaTanggal->rujukanTertunggak());
        $this->assertFalse($masihWaktu->rujukanTertunggak());
        $this->assertFalse($tanpaRujukan->rujukanTertunggak());
    }

    public function test_halaman_pengajuan_mcu_terbuka(): void
    {
        $this->get(route('authority.mcu.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Authority/Halaman')->where('mode', 'mcu'));
    }

    public function test_dasbor_terbuka(): void
    {
        $this->get(route('authority.dasbor'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Authority/Dasbor'));
    }

    /* ═══════════ paraf bertingkat ═══════════ */

    /** Pengajuan MCU yang sudah dikirim, siap diparaf/diputus. */
    private function pengajuanDiajukan(User $pengaju): McuPengajuan
    {
        $p = $this->orang();

        $this->actingAs($pengaju);

        $m = McuPengajuan::create([
            'company_id' => $this->c->id, 'tanggal' => now(), 'jenis' => 'Berkala',
        ]);
        $m->hasil()->create(['paspor_id' => $p->id, 'tgl_periksa' => now()]);
        $m->ajukan();

        return $m->refresh();
    }

    private function pengguna(array $atribut = []): User
    {
        return User::factory()->create($atribut + [
            'company_id' => $this->c->id, 'email_verified_at' => now(), 'is_admin' => false,
        ]);
    }

    /**
     * PARAF TIDAK MENERBITKAN. Inti dari "bertingkat secara visual".
     *
     * Seluruh tahap paraf terisi, dan statusnya tetap menunggu — kartu
     * tidak terbit, orangnya tidak lolos gerbang. Bila uji ini gagal,
     * rantai yang tampak bertingkat sesungguhnya memberi wewenang
     * kepada meja yang menurut pemakainya tidak punya wewenang.
     */
    public function test_paraf_lengkap_tidak_menerbitkan_apa_pun(): void
    {
        $pengaju = $this->pengguna();
        $m = $this->pengajuanDiajukan($pengaju);

        $atasan = $this->pengguna();
        $this->actingAs($atasan);

        foreach ([Tahap::ATASAN, Tahap::DEPARTEMEN] as $tahap) {
            $this->post(route('authority.mcu.paraf', $m), ['tahap' => $tahap])->assertRedirect();
        }

        $m->refresh()->load('paraf');

        $this->assertCount(2, $m->paraf, 'Parafnya sendiri tidak tersimpan.');
        $this->assertSame([], $m->parafTertinggal());

        $this->assertSame(Alur::DIAJUKAN, $m->status,
            'Paraf lengkap ikut menyetujui — meja sebelum OHSE punya wewenang menerbitkan.');
    }

    /**
     * PARAF TIDAK MENAHAN. Sisi lain dari aturan yang sama.
     *
     * OHSE memutuskan tanpa satu paraf pun. Bila ini gagal, meja
     * sebelumnya punya kuasa memveto — dan pengajuan mandek di meja yang
     * orangnya sedang cuti.
     */
    public function test_ohse_memutuskan_walau_belum_ada_paraf(): void
    {
        $pengaju = $this->pengguna();
        $m = $this->pengajuanDiajukan($pengaju);

        $this->assertNotEmpty($m->parafTertinggal(), 'Kontrol gagal: parafnya ternyata sudah ada.');

        $ohse = $this->pengguna(['ohse_role' => 'ohse']);
        $this->actingAs($ohse);

        $this->post(route('authority.mcu.tinjau', $m), ['aksi' => 'setujui'])->assertRedirect();

        $this->assertSame(Alur::DISETUJUI, $m->refresh()->status);
    }

    /**
     * Yang bukan OHSE tidak memutuskan, sebanyak apa pun parafnya.
     *
     * Diuji dengan KTT — peran yang di seluruh modul lain berhak
     * meninjau. Di sini ia sengaja tidak, dan tanpa uji ini penyempitan
     * wewenangnya akan pelan-pelan hilang saat seseorang menyeragamkan
     * penjaga tinjauan antar modul.
     */
    public function test_ktt_bukan_penentu_di_modul_ini(): void
    {
        $pengaju = $this->pengguna();
        $m = $this->pengajuanDiajukan($pengaju);

        $ktt = $this->pengguna(['lms_role' => 'ktt']);
        $this->actingAs($ktt);

        $this->assertFalse($m->dapatDitinjauOleh($ktt));

        $this->post(route('authority.mcu.tinjau', $m), ['aksi' => 'setujui'])
            ->assertSessionHasErrors('alur');

        $this->assertSame(Alur::DIAJUKAN, $m->refresh()->status);

        /* Kontrol: orang OHSE memang bisa. */
        $this->actingAs($this->pengguna(['ohse_role' => 'ohse']));

        $this->post(route('authority.mcu.tinjau', $m), ['aksi' => 'setujui'])->assertRedirect();

        $this->assertSame(Alur::DISETUJUI, $m->refresh()->status);
    }

    /** Pengaju tidak memaraf pengajuannya sendiri. */
    public function test_pengaju_tidak_dapat_memaraf_sendiri(): void
    {
        $pengaju = $this->pengguna();
        $m = $this->pengajuanDiajukan($pengaju);

        $this->actingAs($pengaju);

        $this->post(route('authority.mcu.paraf', $m), ['tahap' => Tahap::ATASAN])
            ->assertSessionHasErrors('paraf');

        $this->assertCount(0, $m->refresh()->paraf);
    }

    /** Tahap penentu diputus, bukan diparaf. */
    public function test_tahap_ohse_tidak_dapat_diparaf(): void
    {
        $pengaju = $this->pengguna();
        $m = $this->pengajuanDiajukan($pengaju);

        $this->actingAs($this->pengguna(['ohse_role' => 'ohse']));

        $this->post(route('authority.mcu.paraf', $m), ['tahap' => Tahap::OHSE])
            ->assertSessionHasErrors('paraf');

        $this->assertCount(0, $m->refresh()->paraf);
    }

    /** Paraf ganda pada tahap yang sama tidak melahirkan baris kedua. */
    public function test_paraf_dua_kali_tetap_satu_baris(): void
    {
        $pengaju = $this->pengguna();
        $m = $this->pengajuanDiajukan($pengaju);

        $this->actingAs($this->pengguna());

        $this->post(route('authority.mcu.paraf', $m), ['tahap' => Tahap::ATASAN]);
        $this->post(route('authority.mcu.paraf', $m), ['tahap' => Tahap::ATASAN]);

        $this->assertCount(1, $m->refresh()->paraf);
    }

    /**
     * Rantai menggambar tahap penentu dari STATUS, bukan dari tabel paraf.
     *
     * Bila ia digambar dari sumber yang sama dengan tahap lain, ia akan
     * selamanya tampak "menunggu" walaupun kartunya sudah terbit.
     */
    public function test_rantai_menggambar_tahap_penentu_dari_status(): void
    {
        $pengaju = $this->pengguna();
        $m = $this->pengajuanDiajukan($pengaju);

        $ohse = $this->pengguna(['ohse_role' => 'ohse']);
        $this->actingAs($ohse);
        $m->setujui($ohse);

        $penentu = collect($m->refresh()->rantaiTahap())->firstWhere('penentu', true);

        $this->assertSame(Tahap::OHSE, $penentu['kode']);
        $this->assertSame(Alur::DISETUJUI, $penentu['keadaan']);
    }

    /** Paraf ikut terbuang bersama subjeknya, tidak tertinggal yatim. */
    public function test_paraf_terbuang_bersama_pengajuannya(): void
    {
        $pengaju = $this->pengguna();
        $m = $this->pengajuanDiajukan($pengaju);

        $this->actingAs($this->pengguna());
        $m->bubuhkanParaf(Tahap::ATASAN);

        $this->assertSame(1, \App\Models\PersetujuanParaf::count());

        $m->tarik();
        $m->delete();

        $this->assertSame(0, \App\Models\PersetujuanParaf::count());
    }
}
