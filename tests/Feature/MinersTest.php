<?php

namespace Tests\Feature;

use App\Models\{Company, KompetensiJenis, McuPengajuan, MinersCampaign, MinersCuti,
    MinersCutiJatah, MinersFieldBreak, Paspor, PasporKartu, User};
use App\Support\{Alur, AlurMiner, Authority, JatahCuti, MasterKompetensi, Tahap};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Miners — berkas kelayakan kerja.
 *
 * Yang diuji di sini bukan tampilan halamannya melainkan jawaban atas
 * pertanyaan gerbang: boleh atau tidak orang ini bekerja hari ini.
 * Jawaban itu dipakai menahan orang di pintu masuk tambang, jadi
 * salahnya punya dua bentuk yang sama buruknya — menahan orang yang
 * sebenarnya berhak, dan meloloskan orang yang MCU-nya sudah habis.
 */
class MinersTest extends TestCase
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

        $this->get(route('miners.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Miners/Halaman')
                ->where('ringkas.orang', 1)
                ->where('ringkas.takLayak', 1));
    }

    public function test_halaman_rincian_terbuka(): void
    {
        $p = $this->orang('Rahmat');

        $this->get(route('miners.show', $p))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Miners/Halaman')
                ->where('p.nama', 'Rahmat'));
    }

    public function test_sertifikat_dapat_ditambahkan_lewat_halaman(): void
    {
        $p = $this->orang();

        $this->post(route('miners.sertifikat.simpan', $p), [
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
     * MINE PERMIT TIDAK DAPAT DIAJUKAN SEBELUM MCU DAN INDUKSI.
     *
     * Inilah inti perbaikan alurnya. Sebelumnya syaratnya diperiksa
     * dengan melihat apakah medan `berkas_induksi` terisi — sebuah teks
     * yang diketik tangan — sehingga siapa pun dapat mengetik apa saja
     * dan permitnya lolos, sementara induksi yang sesungguhnya tercatat
     * di tabelnya sendiri tidak pernah dilihat.
     */
    public function test_mine_permit_tertahan_sebelum_mcu_dan_induksi(): void
    {
        $p = $this->orang();

        $k = $p->kartu()->create([
            'jenis' => AlurMiner::KARTU_PERMIT, 'tgl_expired' => now()->addYear(),

            /* Diisi sekadarnya — dulu inilah yang meloloskannya. */
            'berkas_induksi' => 'apa-saja.pdf',
        ]);

        $this->post(route('miners.kartu.ajukan', [$p, $k]))->assertSessionHasErrors('kartu');
        $this->assertSame(Alur::DRAF, $k->refresh()->status);

        /* MCU saja belum cukup. */
        $p->mcu()->create([
            'tgl_periksa' => now(), 'tgl_expired' => now()->addYear(), 'hasil' => 'Fit',
        ]);

        $this->post(route('miners.kartu.ajukan', [$p, $k]))->assertSessionHasErrors('kartu');
        $this->assertSame(Alur::DRAF, $k->refresh()->status);

        /* Dengan induksinya, baru boleh. */
        $this->induksi($p);

        $this->post(route('miners.kartu.ajukan', [$p, $k]))->assertRedirect();
        $this->assertSame(Alur::DIAJUKAN, $k->refresh()->status);
    }

    /**
     * Induksi tidak dapat dicatat sebelum hasil MCU kembali dan layak.
     *
     * Menginduksi orang yang ternyata Unfit adalah setengah hari kelas
     * yang terbuang — dan yang lebih buruk, induksinya tercatat sehingga
     * di layar ia tampak lebih siap daripada sebenarnya.
     */
    public function test_induksi_tertahan_sebelum_mcu_layak(): void
    {
        $p = $this->orang();

        $isi = fn () => $this->post(route('miners.induksi.simpan', $p), [
            'jenis' => 'Awal', 'tanggal' => now()->toDateString(),
            'tgl_expired' => now()->addYear()->toDateString(), 'hasil' => 'Lulus',
        ]);

        /* Tanpa MCU sama sekali. */
        $isi()->assertSessionHasErrors('induksi');
        $this->assertSame(0, $p->induksi()->count());

        /* MCU ada tetapi hasilnya belum kembali. */
        $m = $p->mcu()->create(['tgl_periksa' => now(), 'hasil' => null]);
        $isi()->assertSessionHasErrors('induksi');

        /* Hasilnya kembali, tetapi Unfit. */
        $m->update(['hasil' => 'Unfit', 'tgl_expired' => now()->addYear()]);
        $isi()->assertSessionHasErrors('induksi');
        $this->assertSame(0, $p->induksi()->count());

        /* Layak — barulah boleh. */
        $m->update(['hasil' => 'Fit']);
        $isi()->assertRedirect();

        $this->assertSame(1, $p->refresh()->induksi()->count());
    }

    /**
     * MINE LICENSE MENUNTUT MINE PERMIT YANG SUDAH TERBIT.
     *
     * Seseorang dapat memenuhi MCU dan induksi tetapi permitnya masih
     * menunggu keputusan OHSE. Menerbitkan izin mengemudi baginya
     * berarti mengizinkan mengemudi di area yang ia sendiri belum boleh
     * masuki.
     */
    public function test_mine_license_menuntut_mine_permit_terbit(): void
    {
        $p = $this->orang();
        $this->induksi($p);
        $p->mcu()->create([
            'tgl_periksa' => now(), 'tgl_expired' => now()->addYear(), 'hasil' => 'Fit',
        ]);

        $lisensi = $p->kartu()->create([
            'jenis' => AlurMiner::KARTU_LICENSE, 'golongan' => 'Alat Berat',
            'tgl_expired' => now()->addYear(),
            'sim_polisi' => 'SIM-B2-000001',
            'sim_polisi_expired' => now()->addYear(),
            'berkas_ddt' => 'ddt.pdf',
        ]);

        /* Dokumen pengemudinya lengkap, tetapi permitnya belum ada. */
        $this->post(route('miners.kartu.ajukan', [$p, $lisensi]))->assertSessionHasErrors('kartu');
        $this->assertSame(Alur::DRAF, $lisensi->refresh()->status);

        /* Permit yang masih DIAJUKAN pun belum cukup. */
        $permit = $this->kartu($p, [
            'jenis' => AlurMiner::KARTU_PERMIT, 'tgl_expired' => now()->addYear(),
        ]);
        PasporKartu::whereKey($permit->id)->update(['status' => Alur::DIAJUKAN]);

        $this->post(route('miners.kartu.ajukan', [$p, $lisensi]))->assertSessionHasErrors('kartu');

        /* Permit terbit — barulah lisensinya boleh diajukan. */
        PasporKartu::whereKey($permit->id)->update(['status' => Alur::DISETUJUI]);

        $this->post(route('miners.kartu.ajukan', [$p, $lisensi]))->assertRedirect();
        $this->assertSame(Alur::DIAJUKAN, $lisensi->refresh()->status);
    }

    /** Dokumen pengemudi yang kurang disebut satu per satu. */
    public function test_mine_license_menyebut_dokumen_yang_kurang(): void
    {
        $p = $this->orang();
        $this->induksi($p);
        $p->mcu()->create([
            'tgl_periksa' => now(), 'tgl_expired' => now()->addYear(), 'hasil' => 'Fit',
        ]);
        $this->kartu($p, ['jenis' => AlurMiner::KARTU_PERMIT, 'tgl_expired' => now()->addYear()]);

        $lisensi = $p->kartu()->create([
            'jenis' => AlurMiner::KARTU_LICENSE, 'tgl_expired' => now()->addYear(),
        ]);

        $kurang = $lisensi->refresh()->syaratKurang();

        $gabung = implode(' ', $kurang);

        $this->assertStringContainsString('SIM kepolisian', $gabung);
        $this->assertStringContainsString('defensive driving', $gabung);
        $this->assertStringContainsString('Golongan', $gabung);
    }

    /**
     * Kartu tamu menuntut induksi, TIDAK menuntut MCU.
     *
     * Tamu tidak bekerja; ia berkunjung dan pergi hari itu juga.
     * Menuntutnya MCU berarti tidak ada tamu yang pernah dapat masuk —
     * dan yang terjadi berikutnya adalah orang masuk tanpa kartu.
     */
    public function test_kartu_tamu_menuntut_induksi_bukan_mcu(): void
    {
        $p = $this->orang('Tamu');

        $k = $p->kartu()->create([
            'jenis' => AlurMiner::KARTU_VISITOR, 'tgl_expired' => now()->addDays(3),
        ]);

        $this->assertNotEmpty($k->refresh()->syaratKurang(), 'Tamu tanpa induksi ikut lolos.');

        $p->induksi()->create([
            'jenis' => 'Tamu', 'tanggal' => now(),
            'tgl_expired' => now()->addDays(7), 'hasil' => 'Lulus',
        ]);

        /* Tanpa satu pun catatan MCU. */
        $this->assertSame(0, $p->mcu()->count());
        $this->assertSame([], $p->refresh()->kartu->firstWhere('id', $k->id)->syaratKurang());
    }

    /* ═══════════ tahapan yang tergambar ═══════════ */

    /**
     * Tahap yang terkunci menyebutkan sebabnya.
     *
     * "Tidak bisa diklik" tanpa alasan adalah bentuk kegagalan yang
     * paling sering membuat orang mencari jalan lain — biasanya di luar
     * sistem.
     */
    public function test_tahapan_menyebut_sebab_terkuncinya(): void
    {
        $p = $this->orang();
        $p->load(['mcu', 'induksi', 'kartu']);

        $tahap = collect(AlurMiner::tahapan($p))->keyBy('kode');

        $this->assertSame(AlurMiner::SIAP,     $tahap[AlurMiner::MCU]['keadaan']);
        $this->assertSame(AlurMiner::TERKUNCI, $tahap[AlurMiner::INDUKSI]['keadaan']);
        $this->assertNotEmpty($tahap[AlurMiner::INDUKSI]['sebab']);
        $this->assertSame(AlurMiner::TERKUNCI, $tahap[AlurMiner::PERMIT]['keadaan']);
        $this->assertSame(AlurMiner::TERKUNCI, $tahap[AlurMiner::LICENSE]['keadaan']);
    }

    /** Tahapan berpindah selesai mengikuti kelengkapan berkasnya. */
    public function test_tahapan_berpindah_selesai_berurutan(): void
    {
        $p = $this->orang();

        $p->mcu()->create([
            'tgl_periksa' => now(), 'tgl_expired' => now()->addYear(), 'hasil' => 'Fit',
        ]);
        $this->induksi($p);
        $this->kartu($p, ['jenis' => AlurMiner::KARTU_PERMIT, 'tgl_expired' => now()->addYear()]);

        $p->refresh()->load(['mcu', 'induksi', 'kartu']);

        $tahap = collect(AlurMiner::tahapan($p))->keyBy('kode');

        $this->assertSame(AlurMiner::SELESAI, $tahap[AlurMiner::MCU]['keadaan']);
        $this->assertSame(AlurMiner::SELESAI, $tahap[AlurMiner::INDUKSI]['keadaan']);
        $this->assertSame(AlurMiner::SELESAI, $tahap[AlurMiner::PERMIT]['keadaan']);

        /* Mine License terbuka, tetapi opsional — dan orang ini tidak
           terhitung tertahan karena belum punya. */
        $this->assertSame(AlurMiner::SIAP, $tahap[AlurMiner::LICENSE]['keadaan']);
        $this->assertNull(AlurMiner::tahapSekarang($p));
    }

    /* ═══════════ cetak Mine Permit ═══════════ */

    /**
     * Hanya permit yang SUDAH TERBIT yang dapat dicetak.
     *
     * Lembar bercetak tidak dapat dibedakan dari yang sah oleh petugas
     * gerbang, jadi mencetak yang belum disetujui membuat seluruh alur
     * persetujuan yang mendahuluinya tidak ada gunanya.
     */
    public function test_mine_permit_draf_tidak_dapat_dicetak(): void
    {
        $p = $this->orang();

        $k = $p->kartu()->create([
            'jenis' => AlurMiner::KARTU_PERMIT, 'tgl_expired' => now()->addYear(),
        ]);

        $this->get(route('miners.permit.cetak', [$p, $k]))->assertForbidden();

        PasporKartu::whereKey($k->id)->update(['status' => Alur::DISETUJUI]);

        $this->get(route('miners.permit.cetak', [$p, $k]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Print/MinePermit'));
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

        $this->post(route('miners.mcu.nama.tambah', $pengajuan), ['paspor_id' => $p->id])
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

        $this->post(route('miners.mcu.nama.tambah', $pengajuan), ['paspor_id' => $p->id]);

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

        $this->post(route('miners.mcu.nama.tambah', $pengajuan), ['paspor_id' => $p->id]);
        $this->post(route('miners.mcu.nama.tambah', $pengajuan), ['paspor_id' => $p->id])
            ->assertSessionHasErrors('paspor_id');

        $this->assertSame(1, $pengajuan->refresh()->hasil->count());
    }

    /** Surat tanpa satu nama pun tidak dapat dikirim. */
    public function test_pengajuan_kosong_tidak_dapat_diajukan(): void
    {
        $pengajuan = McuPengajuan::create([
            'company_id' => $this->c->id, 'tanggal' => now(), 'jenis' => 'Berkala',
        ]);

        $this->post(route('miners.mcu.ajukan', $pengajuan))->assertSessionHasErrors('mcu');

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
        $this->post(route('miners.mcu.store'), [
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

        $this->post(route('miners.mcu.ajukan', $pengajuan))->assertRedirect();

        $this->post(route('miners.mcu.tinjau', $pengajuan), ['aksi' => 'setujui'])
            ->assertSessionHasErrors('alur');

        $this->assertSame(Alur::DIAJUKAN, $pengajuan->refresh()->status);

        /* Kontrol: orang lain memang dapat menyetujuinya. Tanpa ini,
           penolakan di atas dapat berarti rutenya rusak seluruhnya. */
        $this->actingAs($peninjau);

        $this->post(route('miners.mcu.tinjau', $pengajuan), ['aksi' => 'setujui'])
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
        $this->get(route('miners.mcu.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Miners/Halaman')->where('mode', 'mcu'));
    }

    /**
     * Keempat halaman riwayat terbuka, dan urutannya utuh.
     *
     * Urutan bilah sampingnya — MCU, induksi, Mine Permit, Mine License,
     * Authority — adalah tempat orang belajar urutan prosesnya tanpa
     * membaca petunjuk. Satu halaman yang hilang memutus pelajaran itu.
     */
    public function test_empat_halaman_riwayat_terbuka(): void
    {
        foreach (['induksi', 'mine-permit', 'mine-license', 'authority'] as $tahap) {
            $this->get(route('miners.riwayat.'.$tahap))
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('Miners/Riwayat')
                    ->where('tahap', $tahap));
        }
    }

    public function test_dasbor_terbuka(): void
    {
        $this->get(route('miners.dasbor'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Miners/Dasbor'));
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

        /* Rantai MCU: paramedis dan KTT. Rantai kartu (atasan, kepala
           departemen) sengaja TIDAK berlaku di sini. */
        foreach ([Tahap::PARAMEDIS, Tahap::KTT] as $tahap) {
            $this->post(route('miners.mcu.paraf', $m), ['tahap' => $tahap])->assertRedirect();
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

        $this->post(route('miners.mcu.tinjau', $m), ['aksi' => 'setujui'])->assertRedirect();

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

        $this->post(route('miners.mcu.tinjau', $m), ['aksi' => 'setujui'])
            ->assertSessionHasErrors('alur');

        $this->assertSame(Alur::DIAJUKAN, $m->refresh()->status);

        /* Kontrol: orang OHSE memang bisa. */
        $this->actingAs($this->pengguna(['ohse_role' => 'ohse']));

        $this->post(route('miners.mcu.tinjau', $m), ['aksi' => 'setujui'])->assertRedirect();

        $this->assertSame(Alur::DISETUJUI, $m->refresh()->status);
    }

    /* ═══════════ kebuntuan persetujuan ═══════════ */

    /**
     * Sebab tombol Setujui tidak ada SELALU dapat disebut.
     *
     * Ini kegagalan yang benar-benar dilaporkan: tombolnya hilang, dan
     * tanpa keterangan apa pun hal itu terbaca sebagai sistem yang
     * rusak. Yang diuji di sini bukan aturannya — aturannya sudah diuji
     * di atas — melainkan bahwa setiap penolakan punya kalimatnya.
     */
    public function test_setiap_penolakan_memutuskan_punya_sebab(): void
    {
        $pengaju = $this->pengguna();
        $m = $this->pengajuanDiajukan($pengaju);

        /* Bukan OHSE sama sekali. */
        $biasa = $this->pengguna();
        $this->assertStringContainsString('tim OHSE',
            Tahap::sebabTakDapatMemutuskan($biasa, $m->status, $m->diajukan_oleh) ?? '');

        /* OHSE, tetapi dialah pengajunya. */
        $pengajuOhse = $this->pengguna(['ohse_role' => 'ohse']);
        $this->actingAs($pengajuOhse);
        $sendiri = McuPengajuan::create([
            'company_id' => $this->c->id, 'tanggal' => now(), 'jenis' => 'Berkala',
        ]);
        $sendiri->hasil()->create(['paspor_id' => $this->orang('X')->id, 'tgl_periksa' => now()]);
        $sendiri->ajukan();

        $this->assertStringContainsString('pengajunya sendiri',
            Tahap::sebabTakDapatMemutuskan($pengajuOhse, $sendiri->status, $sendiri->diajukan_oleh) ?? '');

        /* Masih draf. */
        $draf = McuPengajuan::create([
            'company_id' => $this->c->id, 'tanggal' => now(), 'jenis' => 'Berkala',
        ]);
        $this->assertStringContainsString('draf',
            Tahap::sebabTakDapatMemutuskan($pengajuOhse, $draf->status, $draf->diajukan_oleh) ?? '');

        /* Yang memang berhak tidak mendapat sebab apa pun — kontrol,
           supaya uji di atas tidak dapat lulus dengan memulangkan
           kalimat untuk semua orang. */
        $ohse = $this->pengguna(['ohse_role' => 'ohse']);
        $this->assertNull(
            Tahap::sebabTakDapatMemutuskan($ohse, $m->status, $m->diajukan_oleh));
    }

    /**
     * KTT yang sudah ada tidak kehilangan haknya saat wewenang dipersempit.
     *
     * Wewenangnya dipersempit dari "admin atau KTT" menjadi "admin atau
     * OHSE", sementara kolom ohse_role lahir kosong. Tanpa pemindahan
     * ini, KTT kehilangan haknya, tidak ada yang mendapat hak OHSE, dan
     * seluruh pengajuan tertahan tanpa seorang pun dapat memutuskannya.
     */
    public function test_ktt_lama_dipindahkan_menjadi_ohse(): void
    {
        $ktt = User::factory()->create([
            'is_admin' => false, 'lms_role' => 'ktt', 'ohse_role' => null,
            'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);

        /* Migrasinya sudah berjalan sebelum uji ini; yang diperiksa
           adalah bahwa pengguna KTT BARU pun tetap tertolak — pemindahan
           itu sekali, bukan aturan tetap. Yang lama dijamin migrasinya,
           dan itu diuji dengan menjalankan ulang perintahnya. */
        $this->assertFalse(Tahap::penentu($ktt->refresh()));

        \Illuminate\Support\Facades\DB::table('users')
            ->where('lms_role', 'ktt')->whereNull('ohse_role')
            ->update(['ohse_role' => 'ohse']);

        $this->assertTrue(Tahap::penentu($ktt->refresh()),
            'KTT lama tidak ikut terbawa menjadi OHSE — seluruh pengajuan akan tertahan.');
    }

    /** Pengaju tidak memaraf pengajuannya sendiri. */
    public function test_pengaju_tidak_dapat_memaraf_sendiri(): void
    {
        $pengaju = $this->pengguna();
        $m = $this->pengajuanDiajukan($pengaju);

        $this->actingAs($pengaju);

        $this->post(route('miners.mcu.paraf', $m), ['tahap' => Tahap::PARAMEDIS])
            ->assertSessionHasErrors('paraf');

        $this->assertCount(0, $m->refresh()->paraf);
    }

    /**
     * Rantai kartu tidak berlaku pada MCU.
     *
     * Keduanya memakai tabel paraf yang sama, jadi tanpa penjagaan ini
     * sebuah pengajuan MCU dapat mengumpulkan paraf "atasan langsung"
     * yang tidak pernah ada di rantainya — dan rantai yang digambar
     * layar tidak akan pernah menampilkannya, sehingga parafnya
     * tersimpan tanpa seorang pun melihatnya.
     */
    public function test_tahap_di_luar_rantai_modulnya_ditolak(): void
    {
        $pengaju = $this->pengguna();
        $m = $this->pengajuanDiajukan($pengaju);

        $this->actingAs($this->pengguna());

        $this->post(route('miners.mcu.paraf', $m), ['tahap' => Tahap::ATASAN])
            ->assertSessionHasErrors('paraf');

        $this->assertCount(0, $m->refresh()->paraf);
    }

    /** Tahap penentu diputus, bukan diparaf. */
    public function test_tahap_ohse_tidak_dapat_diparaf(): void
    {
        $pengaju = $this->pengguna();
        $m = $this->pengajuanDiajukan($pengaju);

        $this->actingAs($this->pengguna(['ohse_role' => 'ohse']));

        $this->post(route('miners.mcu.paraf', $m), ['tahap' => Tahap::OHSE])
            ->assertSessionHasErrors('paraf');

        $this->assertCount(0, $m->refresh()->paraf);
    }

    /** Paraf ganda pada tahap yang sama tidak melahirkan baris kedua. */
    public function test_paraf_dua_kali_tetap_satu_baris(): void
    {
        $pengaju = $this->pengguna();
        $m = $this->pengajuanDiajukan($pengaju);

        $this->actingAs($this->pengguna());

        $this->post(route('miners.mcu.paraf', $m), ['tahap' => Tahap::PARAMEDIS]);
        $this->post(route('miners.mcu.paraf', $m), ['tahap' => Tahap::PARAMEDIS]);

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

    /* ═══════════ field break & cuti: kehadiran, bukan kelayakan ═══════════ */

    /**
     * Orang yang sedang field break TETAP LAYAK BEKERJA.
     *
     * Ia hanya sedang tidak di sini. Bila keadaan ini bocor ke dalam
     * kelayakan(), daftar "tidak boleh bekerja" akan berisi puluhan nama
     * yang tidak bermasalah sama sekali — dan daftar semacam itu
     * berhenti dibaca dalam seminggu, membawa serta nama-nama yang
     * benar-benar bermasalah.
     */
    public function test_field_break_tidak_membuat_orang_tidak_layak(): void
    {
        $p = $this->orang();
        $this->induksi($p);
        $p->mcu()->create([
            'tgl_periksa' => now(), 'tgl_expired' => now()->addYear(), 'hasil' => 'Fit',
        ]);
        $this->kartu($p, ['jenis' => 'ID Card', 'tgl_expired' => now()->addYear()]);

        $fb = MinersFieldBreak::create([
            'paspor_id' => $p->id, 'pola' => '8:2', 'jenis' => 'Roster',
            'mulai' => now()->subDays(3), 'selesai' => now()->addDays(10),
        ]);
        MinersFieldBreak::whereKey($fb->id)->update(['status' => Alur::DISETUJUI]);

        $p->refresh()->load(['fieldBreak', 'cuti']);

        $this->assertTrue($p->kehadiran()['pergi'], 'Kehadirannya tidak terbaca sebagai pergi.');
        $this->assertTrue($p->kelayakan()['layak'],
            'Field break bocor ke kelayakan: '.implode(' · ', $p->kelayakan()['sebab']));
    }

    /**
     * Yang dipanggil balik lebih awal SUDAH ADA di lokasi.
     *
     * Tanggal selesainya belum tiba, tetapi ia sudah kembali — dan
     * daftar kehadiran yang masih menyebutnya pergi akan dipakai membagi
     * pekerjaan kepada orang lain, padahal ia siap bekerja.
     */
    public function test_kembali_lebih_awal_tidak_lagi_terhitung_pergi(): void
    {
        $p = $this->orang();

        $fb = MinersFieldBreak::create([
            'paspor_id' => $p->id, 'jenis' => 'Roster',
            'mulai' => now()->subDays(10), 'selesai' => now()->addDays(10),
        ]);
        MinersFieldBreak::whereKey($fb->id)->update(['status' => Alur::DISETUJUI]);

        $fb->refresh();
        $this->assertTrue($fb->sedangPergi(), 'Kontrol gagal: belum terbaca pergi sejak awal.');

        $fb->update(['kembali_aktual' => now()->subDay()]);

        $this->assertFalse($fb->refresh()->sedangPergi());
    }

    /** Kepulangan boleh dicatat SESUDAH disetujui — ia kejadian, bukan suntingan. */
    public function test_tanggal_kembali_dapat_dicatat_setelah_disetujui(): void
    {
        $p = $this->orang();

        $fb = MinersFieldBreak::create([
            'paspor_id' => $p->id, 'jenis' => 'Roster',
            'mulai' => now()->subDays(20), 'selesai' => now()->subDays(5),
        ]);
        MinersFieldBreak::whereKey($fb->id)->update(['status' => Alur::DISETUJUI]);

        $this->post(route('miners.fieldBreak.kembali', $fb->refresh()), [
            'kembali_aktual' => now()->subDays(2)->toDateString(),
        ])->assertRedirect();

        $this->assertSame(3, $fb->refresh()->telat());
    }

    /* ═══════════ jatah cuti ═══════════ */

    /**
     * Cuti yang MASIH MENUNGGU ikut memotong sisa.
     *
     * Tanpa ini seseorang dapat mengajukan lima cuti sekaligus yang
     * masing-masing tampak muat dalam sisa jatahnya, lalu kelimanya
     * disetujui satu per satu dan jatahnya minus — tanpa satu pun
     * langkah yang keliru.
     */
    public function test_cuti_yang_menunggu_ikut_memotong_sisa(): void
    {
        $p = $this->orang();

        MinersCutiJatah::create(['paspor_id' => $p->id, 'tahun' => (int) now()->year, 'jatah' => 10]);

        $this->assertSame(10, JatahCuti::hitung($p)['sisa']);

        $c = MinersCuti::create([
            'paspor_id' => $p->id, 'tahun' => (int) now()->year, 'jenis' => 'Tahunan',
            'mulai' => now()->addDays(10), 'selesai' => now()->addDays(13), 'jumlah_hari' => 4,
        ]);

        /* Masih draf — belum membebani apa pun. */
        $this->assertSame(10, JatahCuti::hitung($p)['sisa']);

        $c->ajukan();

        $saldo = JatahCuti::hitung($p);

        $this->assertSame(4, $saldo['tertahan']);
        $this->assertSame(0, $saldo['terpakai'], 'Yang menunggu terhitung sebagai sudah terpakai.');
        $this->assertSame(6, $saldo['sisa']);
    }

    /** Hanya cuti tahunan yang memotong jatah; sakit tidak. */
    public function test_cuti_sakit_tidak_memotong_jatah(): void
    {
        $p = $this->orang();

        MinersCutiJatah::create(['paspor_id' => $p->id, 'tahun' => (int) now()->year, 'jatah' => 12]);

        $sakit = MinersCuti::create([
            'paspor_id' => $p->id, 'tahun' => (int) now()->year, 'jenis' => 'Sakit',
            'mulai' => now(), 'selesai' => now()->addDays(4), 'jumlah_hari' => 5,
        ]);
        $sakit->ajukan();

        $this->assertSame(12, JatahCuti::hitung($p)['sisa'],
            'Cuti sakit memotong jatah tahunan — orang dihukum karena jatuh sakit.');
    }

    /**
     * Pengajuan melebihi sisa ditolak SAAT DRAF DIBUAT, bukan saat ditinjau.
     *
     * Menolak di akhir berarti pengaju sudah menyusun rencana, memberi
     * tahu keluarganya, dan menunggu berhari-hari sebelum diberi tahu
     * bahwa jatahnya memang tidak pernah cukup.
     */
    public function test_cuti_melebihi_sisa_ditolak_sejak_awal(): void
    {
        $p = $this->orang();

        MinersCutiJatah::create(['paspor_id' => $p->id, 'tahun' => (int) now()->year, 'jatah' => 3]);

        $this->post(route('miners.cuti.store'), [
            'paspor_id' => $p->id, 'jenis' => 'Tahunan',
            'mulai' => now()->addDays(10)->toDateString(),
            'selesai' => now()->addDays(14)->toDateString(),
        ])->assertSessionHasErrors('jumlah_hari');

        $this->assertSame(0, MinersCuti::count());

        /* Kontrol: yang muat memang diterima — supaya penolakan di atas
           tidak dapat berarti rutenya menolak segalanya. */
        $this->post(route('miners.cuti.store'), [
            'paspor_id' => $p->id, 'jenis' => 'Tahunan',
            'mulai' => now()->addDays(10)->toDateString(),
            'selesai' => now()->addDays(12)->toDateString(),
        ])->assertRedirect();

        $this->assertSame(1, MinersCuti::count());
    }

    /** Jumlah hari dihitung inklusif — cuti sehari adalah satu hari, bukan nol. */
    public function test_jumlah_hari_cuti_inklusif(): void
    {
        $this->assertSame(1, MinersCuti::hariKalender('2026-08-17', '2026-08-17'));
        $this->assertSame(5, MinersCuti::hariKalender('2026-08-17', '2026-08-21'));
    }

    /**
     * Jumlah hari yang tersimpan tidak berubah walau tanggalnya bergeser.
     *
     * Yang menentukan potongan jatah adalah keputusan saat cuti
     * disetujui, bukan selisih tanggal yang dihitung ulang tiap dibaca.
     */
    public function test_jumlah_hari_tersimpan_bukan_dihitung_ulang(): void
    {
        $p = $this->orang();

        /* Lima hari kalender, tetapi hanya tiga yang dipotong — dua di
           antaranya hari libur. */
        $c = MinersCuti::create([
            'paspor_id' => $p->id, 'tahun' => (int) now()->year, 'jenis' => 'Tahunan',
            'mulai' => now()->addDays(10), 'selesai' => now()->addDays(14), 'jumlah_hari' => 3,
        ]);

        $this->assertSame(3, $c->refresh()->jumlah_hari);
        $this->assertSame(5, MinersCuti::hariKalender($c->mulai, $c->selesai));
    }

    /* ═══════════ campaign ═══════════ */

    /** Tanpa tanggal selesai berarti berjalan terus, bukan sudah berakhir. */
    public function test_campaign_tanpa_tanggal_selesai_tetap_tayang(): void
    {
        $c = MinersCampaign::create([
            'company_id' => $this->c->id, 'judul' => 'Wajib APD',
            'jenis' => 'Spanduk', 'mulai' => now()->subYear(),
        ]);
        MinersCampaign::whereKey($c->id)->update(['status' => Alur::DISETUJUI]);

        $this->assertTrue($c->refresh()->sedangTayang());
    }

    /** Campaign yang belum disetujui tidak tayang, walau tanggalnya sudah lewat. */
    public function test_campaign_draf_tidak_tayang(): void
    {
        $c = MinersCampaign::create([
            'company_id' => $this->c->id, 'judul' => 'Belum disetujui',
            'jenis' => 'Poster', 'mulai' => now()->subMonth(), 'selesai' => now()->addMonth(),
        ]);

        $this->assertFalse($c->refresh()->sedangTayang());
    }

    /**
     * Jangkauan boleh dicatat setelah disetujui.
     *
     * Berapa orang yang menerima baru diketahui SESUDAH campaign
     * berjalan; mengunci angkanya pada saat persetujuan berarti angka
     * itu selamanya kosong.
     */
    public function test_jangkauan_dapat_dicatat_setelah_disetujui(): void
    {
        $c = MinersCampaign::create([
            'company_id' => $this->c->id, 'judul' => 'Bulan K3',
            'jenis' => 'Poster', 'mulai' => now()->subMonth(),
        ]);
        MinersCampaign::whereKey($c->id)->update(['status' => Alur::DISETUJUI]);

        $this->post(route('miners.campaign.jangkauan', $c->refresh()), ['jangkauan' => 240])
            ->assertRedirect();

        $this->assertSame(240, $c->refresh()->jangkauan);
    }

    /** Ketiga halaman baru terbuka. */
    public function test_halaman_kehadiran_dan_campaign_terbuka(): void
    {
        $this->get(route('miners.fieldBreak.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Miners/FieldBreak'));

        $this->get(route('miners.cuti.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Miners/Cuti'));

        $this->get(route('miners.campaign.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Miners/Campaign'));
    }

    /** Paraf ikut terbuang bersama subjeknya, tidak tertinggal yatim. */
    public function test_paraf_terbuang_bersama_pengajuannya(): void
    {
        $pengaju = $this->pengguna();
        $m = $this->pengajuanDiajukan($pengaju);

        $this->actingAs($this->pengguna());
        $m->bubuhkanParaf(Tahap::PARAMEDIS);

        $this->assertSame(1, \App\Models\PersetujuanParaf::count());

        $m->tarik();
        $m->delete();

        $this->assertSame(0, \App\Models\PersetujuanParaf::count());
    }

    /* ═══════════ unit SIMPER ═══════════ */

    /**
     * SIMPER dinilai PER UNIT, bukan per orang.
     *
     * Seorang operator dapat lulus untuk Excavator PC 200 dan belum
     * lulus untuk PC 500 — dua baris, satu kartu. Disimpan sebagai satu
     * baris per kartu, kartu yang menyebut "Excavator" membolehkan orang
     * mengemudikan unit yang tidak pernah diujikan kepadanya, dan tidak
     * ada satu pun catatan yang menunjukkan itu terjadi.
     */
    public function test_satu_kartu_menampung_banyak_unit_dengan_nilai_sendiri(): void
    {
        $p = $this->orang();
        $k = $p->kartu()->create(['jenis' => AlurMiner::KARTU_LICENSE, 'sebab_terbit' => Authority::SEBAB_KARTU[0]]);

        $this->post(route('miners.kartu.unit.simpan', [$p, $k]), [
            'authority' => 'F', 'jenis_unit' => 'EXCAVATOR', 'type_merk' => 'Komatsu PC 200',
            'nilai_p2h' => 82, 'nilai_praktek' => 82,
        ])->assertSessionHasNoErrors();

        $this->post(route('miners.kartu.unit.simpan', [$p, $k]), [
            'authority' => 'T', 'jenis_unit' => 'EXCAVATOR', 'type_merk' => 'Komatsu PC 500',
            'nilai_p2h' => 60, 'nilai_praktek' => 55,
        ])->assertSessionHasNoErrors();

        $unit = $k->fresh()->unit()->orderBy('id')->get();

        $this->assertCount(2, $unit);
        $this->assertTrue($unit[0]->lulus(), 'PC 200 dengan 82/82 seharusnya lulus.');
        $this->assertFalse($unit[1]->lulus(), 'PC 500 dengan 60/55 seharusnya belum lulus.');
        $this->assertSame('Komatsu PC 200', $unit[0]->type_merk);
    }

    /**
     * Nilai yang belum diisi bukan nilai nol.
     *
     * Kekosongan berarti BELUM DIUJI; nol berarti diuji dan gagal.
     * Memperlakukan keduanya sama membuat unit yang belum pernah
     * diujikan terbaca seolah sudah dinilai — persis cara kartu terbit
     * tanpa dasar.
     */
    public function test_unit_tanpa_nilai_tidak_dianggap_lulus(): void
    {
        $p = $this->orang();
        $k = $p->kartu()->create(['jenis' => AlurMiner::KARTU_LICENSE, 'sebab_terbit' => Authority::SEBAB_KARTU[0]]);

        $this->post(route('miners.kartu.unit.simpan', [$p, $k]), [
            'jenis_unit' => 'DUMP TRUCK', 'type_merk' => 'HD785',
        ])->assertSessionHasNoErrors();

        $u = $k->fresh()->unit()->first();

        $this->assertNull($u->nilai_p2h);
        $this->assertFalse($u->lulus(), 'Unit tanpa nilai tidak boleh terbaca lulus.');
    }

    /**
     * Unit tidak boleh ditambahkan pada kartu yang sudah diajukan.
     *
     * Menambah unit berarti memperluas kewenangan mengemudi. Dilakukan
     * setelah kartunya diajukan, perluasan itu tidak pernah melewati
     * peninjauan yang menyetujuinya — dan yang tercetak pada kartunya
     * lebih banyak daripada yang pernah diperiksa siapa pun.
     */
    public function test_unit_tidak_dapat_ditambah_setelah_kartu_diajukan(): void
    {
        $p = $this->orang();
        $k = $this->kartu($p, ['jenis' => AlurMiner::KARTU_LICENSE, 'sebab_terbit' => Authority::SEBAB_KARTU[0]]);

        $this->postJson(route('miners.kartu.unit.simpan', [$p, $k]), [
            'jenis_unit' => 'EXCAVATOR', 'nilai_p2h' => 90, 'nilai_praktek' => 90,
        ])->assertStatus(422);

        $this->assertSame(0, $k->fresh()->unit()->count());
    }

    /** Dan nilai di luar 0..100 ditolak. */
    public function test_nilai_unit_di_luar_rentang_ditolak(): void
    {
        $p = $this->orang();
        $k = $p->kartu()->create(['jenis' => AlurMiner::KARTU_LICENSE, 'sebab_terbit' => Authority::SEBAB_KARTU[0]]);

        $this->post(route('miners.kartu.unit.simpan', [$p, $k]), [
            'jenis_unit' => 'EXCAVATOR', 'nilai_p2h' => 120,
        ])->assertSessionHasErrors('nilai_p2h');
    }

    /* ═══════════ pita kedaluwarsa kartu ═══════════ */

    /**
     * Empat pita, dan batasnya persis.
     *
     * Diuji tepat di batasnya, bukan di tengah pita: kesalahan
     * perbandingan (< versus <=) hanya muncul pada nilai batas.
     *
     * "Habis" dipisahkan dari "mendesak", tidak digabung seperti pita
     * MCU dan sertifikat. Pada berkas yang punya antrean, "lewat 3 hari"
     * dan "tinggal 3 hari" sama-sama berarti segera urus. Pada kartu,
     * yang pertama berarti orangnya tidak boleh berada di area tambang
     * hari ini — tindakan yang lain sama sekali.
     */
    public function test_pita_kedaluwarsa_kartu_tepat_di_batasnya(): void
    {
        $kini = Carbon::parse('2026-01-01');

        foreach ([
            [-1,  Authority::HABIS],
            [0,   Authority::MENDESAK],
            [30,  Authority::MENDESAK],
            [31,  Authority::DEKAT],
            [60,  Authority::DEKAT],
            [61,  Authority::PANJANG],
        ] as [$hari, $harus]) {
            $this->assertSame($harus,
                Authority::keadaanKartu($kini->copy()->addDays($hari), $kini),
                "Sisa {$hari} hari seharusnya '{$harus}'.");
        }

        $this->assertSame(Authority::TAK_BERTANGGAL, Authority::keadaanKartu(null, $kini));
    }

    /* ═══════════ masa berlaku turunan ═══════════ */

    /**
     * Mine Permit tidak dapat hidup lebih lama daripada MCU-nya.
     *
     * Kartu berlaku sampai Desember yang berpijak pada MCU yang habis
     * Agustus sudah tidak sah pada bulan September — meskipun tanggal
     * yang tercetak padanya mengatakan sebaliknya, dan meskipun tidak
     * ada satu pun galat yang muncul.
     */
    public function test_permit_dibatasi_masa_berlaku_mcu(): void
    {
        $p = $this->orang();

        $p->mcu()->create([
            'tgl_periksa' => now()->subMonths(2),
            'tgl_expired' => now()->addDays(20),
            'hasil'       => 'Fit',
        ]);

        $k = $p->kartu()->create([
            'jenis'        => AlurMiner::KARTU_PERMIT,
            'sebab_terbit' => Authority::SEBAB_KARTU[0],
            'tgl_expired'  => now()->addDays(300),
        ]);

        $k->setRelation('paspor', $p->fresh()->load('mcu'));

        $this->assertTrue($k->dibatasiDasar(),
            'MCU habis lebih dulu, jadi kartunya dibatasi MCU.');
        $this->assertSame(now()->addDays(20)->toDateString(),
            $k->expiredEfektif()?->toDateString());
        $this->assertSame(Authority::MENDESAK, $k->keadaanKartu(),
            'Yang dipantau tanggal efektifnya, bukan yang tercetak.');
        $this->assertSame('MCU', $k->namaDasar());
    }

    /** SIMPER dibatasi SIM kepolisian, dengan cara yang sama. */
    public function test_simper_dibatasi_masa_berlaku_sim(): void
    {
        $p = $this->orang();

        $k = $p->kartu()->create([
            'jenis'              => AlurMiner::KARTU_LICENSE,
            'sebab_terbit'       => Authority::SEBAB_KARTU[0],
            'tgl_expired'        => now()->addDays(300),
            'sim_polisi'         => 'SIM-B2-000001',
            'sim_polisi_expired' => now()->addDays(45),
        ]);

        $this->assertTrue($k->dibatasiDasar());
        $this->assertSame(Authority::DEKAT, $k->keadaanKartu());
        $this->assertSame('SIM kepolisian', $k->namaDasar());
    }

    /**
     * Dasar yang lebih panjang TIDAK memperpanjang kartunya.
     *
     * Pembatasannya satu arah. MCU yang berlaku tiga tahun tidak membuat
     * permit setahun ikut berlaku tiga tahun — yang menentukan tetap
     * yang mana pun habis lebih dulu.
     */
    public function test_dasar_yang_lebih_panjang_tidak_memperpanjang_kartu(): void
    {
        $p = $this->orang();

        $p->mcu()->create([
            'tgl_periksa' => now()->subMonth(),
            'tgl_expired' => now()->addDays(900),
            'hasil'       => 'Fit',
        ]);

        $k = $p->kartu()->create([
            'jenis'        => AlurMiner::KARTU_PERMIT,
            'sebab_terbit' => Authority::SEBAB_KARTU[0],
            'tgl_expired'  => now()->addDays(10),
        ]);

        $k->setRelation('paspor', $p->fresh()->load('mcu'));

        $this->assertFalse($k->dibatasiDasar());
        $this->assertSame(now()->addDays(10)->toDateString(),
            $k->expiredEfektif()?->toDateString());
    }

    /** Visitor tidak berpijak pada apa pun, jadi tidak dibatasi. */
    public function test_visitor_tidak_punya_dasar(): void
    {
        $p = $this->orang();

        $k = $p->kartu()->create([
            'jenis'        => AlurMiner::KARTU_VISITOR,
            'sebab_terbit' => Authority::SEBAB_KARTU[0],
            'tgl_expired'  => now()->addDays(5),
        ]);

        $this->assertNull($k->expiredDasar());
        $this->assertNull($k->namaDasar());
        $this->assertFalse($k->dibatasiDasar());
        $this->assertSame(now()->addDays(5)->toDateString(),
            $k->expiredEfektif()?->toDateString());
    }
    /**
     * Tiap halaman modul menyebut namanya sendiri di bilah atas.
     *
     * Judulnya dikirim lewat prop, dan prop itu digabung dengan prop
     * bersama modul memakai operator `+` — yang memakai nilai dari
     * operan KIRI bila kuncinya bertabrakan. Ditulis dengan urutan
     * terbalik, judul halaman hilang tanpa jejak: tidak ada galat,
     * tidak ada uji yang berubah warna, hanya empat halaman berbeda
     * yang semuanya menyebut diri "Miners — Kelayakan Kerja".
     *
     * Karena itu yang diuji di sini bukan adanya prop judul, melainkan
     * BERBEDANYA judul antarhalaman.
     */
    public function test_tiap_halaman_miners_berjudul_sendiri(): void
    {
        $judul = [];

        foreach ([
            'miners.index', 'miners.fieldBreak.index', 'miners.cuti.index',
            'miners.campaign.index', 'miners.kedaluwarsa',
            'miners.riwayat.mine-permit', 'miners.riwayat.induksi',
        ] as $rute) {
            $this->get(route($rute))
                ->assertOk()
                ->assertInertia(function (Assert $h) use (&$judul, $rute) {
                    $h->has('judul');
                    $judul[$rute] = $h->toArray()['props']['judul'];
                });
        }

        $this->assertSame(
            count($judul),
            count(array_unique($judul)),
            "Ada halaman Miners yang memakai judul halaman lain:\n"
            .json_encode($judul, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ."\nJudul halaman harus ditulis SEBELUM bersama(): ['judul' => ...] + \$this->bersama().",
        );
    }

}
