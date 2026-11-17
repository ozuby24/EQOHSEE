<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Investigasi\{Insiden, Investigasi, Taksonomi};
use App\Models\User;
use App\Support\Investigasi\{KamusScat, MasterInvestigasi, MesinScat, NomorInvestigasi, PanduanWawancara};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Mesin saran SCAT dan panduan wawancara.
 *
 * ── APA YANG SEBENARNYA DIJAGA DI SINI ──
 *
 * Ketiganya gagal tanpa satu pun galat, dan ketiganya merusak berkas
 * yang dapat diminta Inspektur Tambang.
 *
 * PERTAMA, rantai yang putus. Kalau usulan lapis 3 tidak pernah muncul,
 * yang terjadi bukan pesan kesalahan melainkan investigasi yang berhenti
 * pada "operator kurang hati-hati" — sah menurut aplikasi, dan tidak
 * mencegah apa pun.
 *
 * KEDUA, kode grup yang tertukar. '9.1' dan '9.10' adalah dua grup yang
 * sama sekali berbeda, dan PHP menganggap '9.1' == '9.10' bernilai
 * benar karena keduanya terbaca sebagai angka. Satu pembandingan longgar
 * saja membuat usulan Pengembangan Karyawan muncul sebagai Operasi &
 * Pemeliharaan. Layarnya tetap rapi; isinya salah.
 *
 * KETIGA, pertanyaan yang diajukan kepada orang yang keliru. Menanyai
 * korban mengapa perusahaan tidak mengganti alat yang aus bukan sekadar
 * sia-sia — jawabannya akan tercatat sebagai pengakuan, dan berita acara
 * itu ikut masuk ke berkas.
 */
class MesinScatTest extends TestCase
{
    use RefreshDatabase;

    private Investigasi $inv;

    protected function setUp(): void
    {
        parent::setUp();

        MasterInvestigasi::pasang();

        $c = Company::create(['name' => 'PT Uji SCAT', 'doc_no_prefix' => 'USC']);

        $this->actingAs(User::factory()->create([
            'is_admin' => true, 'company_id' => $c->id, 'email_verified_at' => now(),
        ]));

        $insiden = Insiden::withoutGlobalScopes()->create([
            'company_id'        => $c->id,
            'no_insiden'        => NomorInvestigasi::terbitkan(NomorInvestigasi::INSIDEN),
            'judul'             => 'Operator terjepit saat mengganti ban',
            'tanggal_kejadian'  => '2026-08-19',
            'level_investigasi' => 'L3',
            'kronologi'         => 'Ban belakang dilepas tanpa mengganjal unit lebih dahulu.',
        ]);

        $this->inv = Investigasi::withoutGlobalScopes()->create([
            'no_investigasi' => NomorInvestigasi::terbitkan(NomorInvestigasi::INVESTIGASI),
            'insiden_id'     => $insiden->id,
        ]);
    }

    private function jalur(string $sisa = ''): string
    {
        return '/investigasi/berkas/'.$this->inv->id.($sisa ? '/'.$sisa : '');
    }

    private function butir(string $kode): Taksonomi
    {
        return Taksonomi::where('metode', 'scat')->where('kode', $kode)->firstOrFail();
    }

    /** Pilih sebuah butir lewat HTTP, sebagaimana layarnya melakukannya. */
    private function pilih(string $kode, bool $dariSaran = false): void
    {
        $this->post($this->jalur('scat'), [
            'taksonomi_id' => $this->butir($kode)->id,
            'dari_saran'   => $dariSaran,
        ])->assertSessionHasNoErrors();
    }

    private function saran(): array
    {
        return $this->get($this->jalur('analisis'))->assertOk()
            ->viewData('page')['props']['saran'];
    }

    /* ═══════════ lapis dan grup ═══════════ */

    #[Test]
    public function tiap_butir_kamus_jatuh_pada_lapis_yang_benar(): void
    {
        $per = [1 => 0, 2 => 0, 3 => 0, 0 => 0];

        foreach (KamusScat::baca() as $b) {
            $per[MesinScat::lapis($b['kode'])]++;
        }

        $this->assertSame(0, $per[0], 'Ada butir kamus yang tidak masuk lapis mana pun.');
        $this->assertSame(33, $per[1]);
        $this->assertSame(133, $per[2]);
        $this->assertSame(86, $per[3]);
    }

    /**
     * Jebakan pembandingan longgar, diuji terpisah karena ia sunyi.
     *
     * Bukan uji tautologis: baris pertama membuktikan bahwa jebakannya
     * memang ada di PHP versi ini, dan sisanya membuktikan bahwa
     * mesinnya tidak terjebak.
     */
    #[Test]
    public function kode_grup_berangka_desimal_tidak_tertukar(): void
    {
        $this->assertTrue('9.1' == '9.10',
            'Jebakannya hilang dari PHP versi ini — penjagaan di bawah boleh ditinjau ulang.');

        $this->assertSame('9.1',  MesinScat::grup('9.1.5'));
        $this->assertSame('9.10', MesinScat::grup('9.10.1'));
        $this->assertNotSame(MesinScat::grup('9.1.5'), MesinScat::grup('9.10.1'));

        /* Bagian 5 dan 6 datar: kodenya sendiri yang menjadi kuncinya,
           dan '5.1' tidak boleh runtuh menjadi satu dengan '5.10'. */
        $this->assertSame('5.1',  MesinScat::grup('5.1'));
        $this->assertSame('5.10', MesinScat::grup('5.10'));

        /* Dan kunci lariknya pun tetap terpisah. */
        $peta = MesinScat::PETA_DASAR;
        $this->assertNotSame($peta['5.1'], $peta['5.10']);
    }

    /**
     * Seluruh butir lapis 1 punya peta, dan tiap tujuannya benar-benar ada.
     *
     * Tujuan yang salah ketik tidak menimbulkan galat — ia hanya membuat
     * satu butir lapis 1 tidak pernah mengusulkan apa pun, dan yang
     * terlihat di layar cuma "belum ada usulan".
     */
    #[Test]
    public function peta_saran_menutup_seluruh_kamus(): void
    {
        $kode = array_column(KamusScat::baca(), 'kode');

        $l1 = array_values(array_filter($kode, fn ($k) => MesinScat::lapis($k) === 1));
        $this->assertSame([], array_values(array_diff($l1, array_keys(MesinScat::PETA_DASAR))),
            'Ada butir lapis 1 yang tidak pernah mengusulkan apa pun.');
        $this->assertSame([], array_values(array_diff(array_keys(MesinScat::PETA_DASAR), $l1)),
            'PETA_DASAR menyebut kode yang tidak ada di kamus.');

        $grup2 = array_values(array_unique(array_map(
            fn ($k) => MesinScat::grup($k),
            array_filter($kode, fn ($k) => MesinScat::lapis($k) === 2))));

        $this->assertSame([], array_values(array_diff($grup2, array_keys(MesinScat::PETA_KENDALI))),
            'Ada grup lapis 2 yang tidak pernah mengusulkan lack of control.');

        $semuaGrup = array_values(array_unique(array_map(fn ($k) => MesinScat::grup($k), $kode)));

        $tujuan = [];
        foreach (MesinScat::PETA_DASAR as $v)          $tujuan = array_merge($tujuan, $v);
        foreach (MesinScat::PETA_KENDALI as $k => $v)  $tujuan = array_merge($tujuan, [$k], $v);

        $this->assertSame([], array_values(array_diff(array_unique($tujuan), $semuaGrup)),
            'Peta saran menunjuk grup yang tidak ada di kamus.');
    }

    /* ═══════════ rantai tiga lapis ═══════════ */

    #[Test]
    public function pilihan_lapis_1_memunculkan_usulan_lapis_2_bukan_lapis_3(): void
    {
        $this->pilih('5.13');   // tidak menggunakan APD

        $saran = $this->saran();

        $this->assertNotEmpty($saran[2], 'Pilihan lapis 1 tidak memunculkan usulan lapis 2.');

        /* Lapis 3 masih kosong, dan itu disengaja: mengusulkannya dari
           USULAN lapis 2 — alih-alih dari pilihan yang sudah diambil —
           akan memunculkan hampir seluruh bagian 9 sekaligus. */
        $this->assertSame([], $saran[3],
            'Lapis 3 diusulkan sebelum ada satu pun butir lapis 2 yang dipilih.');

        foreach ($saran[2] as $s) {
            $this->assertSame(2, MesinScat::lapis($s['kode']));
            $this->assertNotEmpty($s['dari'], 'Usulan tanpa alasan tidak dapat dinilai siapa pun.');
        }
    }

    #[Test]
    public function pilihan_lapis_2_baru_memunculkan_usulan_lapis_3(): void
    {
        $this->pilih('5.13');
        $this->pilih('8.3.1');  // pembelian yang tidak memadai

        $saran = $this->saran();

        $this->assertNotEmpty($saran[3], 'Pilihan lapis 2 tidak memunculkan usulan lack of control.');

        $grup = array_values(array_unique(array_map(
            fn ($s) => MesinScat::grup($s['kode']), $saran[3])));
        sort($grup);

        /* 8.3 memetakan tepat ke Manajemen Risiko/Perubahan dan
           Manajemen Kontraktor/Material — bukan ke seluruh bagian 9. */
        $this->assertSame(['9.3', '9.8'], $grup,
            'Usulan lapis 3 melebar ke grup yang tidak dipetakan: '.implode(' ', $grup));
    }

    /**
     * Kasus yang persis menjadi alasan seluruh pembandingan di sini ketat.
     *
     * Grup 8.1 memetakan ke 9.1, 9.2, 9.4, dan 9.5 — TIDAK ke 9.10.
     * Dengan satu pembandingan longgar, '9.1' == '9.10' bernilai benar
     * dan seluruh grup Operasi & Pemeliharaan ikut terusul sebagai
     * Pengembangan Karyawan. Tidak ada galat, dan layarnya tetap rapi.
     */
    #[Test]
    public function grup_9_10_tidak_ikut_terusul_bersama_grup_9_1(): void
    {
        $this->pilih('5.1');
        $this->pilih('8.1.6');   // instruksi/pelatihan tidak memadai

        $grup = array_values(array_unique(array_map(
            fn ($s) => MesinScat::grup($s['kode']), $this->saran()[3])));
        sort($grup);

        $this->assertSame(['9.1', '9.2', '9.4', '9.5'], $grup,
            'Usulan lapis 3 memuat grup yang tidak dipetakan 8.1: '.implode(' ', $grup));

        $this->assertNotContains('9.10', $grup,
            'Grup 9.10 ikut terusul — kodenya dibandingkan secara longgar dengan 9.1.');
    }

    #[Test]
    public function usulan_tidak_mengulang_yang_sudah_dipilih(): void
    {
        $this->pilih('5.13');

        $sebelum = collect($this->saran()[2])->pluck('kode');
        $this->assertTrue($sebelum->contains('8.3.1'));

        $this->pilih('8.3.1', dariSaran: true);

        $sesudah = collect($this->saran()[2])->pluck('kode');
        $this->assertFalse($sesudah->contains('8.3.1'),
            'Butir yang sudah dipilih masih diusulkan lagi.');
    }

    /**
     * Usulan mempersempit — kalau tidak, ia bukan usulan.
     *
     * Angka pastinya sengaja tidak dipatok; yang dijaga adalah sifatnya.
     * Peta yang lama-kelamaan ditambahi sampai satu pilihan mengusulkan
     * separuh kamus tidak akan menimbulkan galat, hanya membuat layarnya
     * kembali menjadi daftar panjang yang dilewati orang.
     */
    #[Test]
    public function usulan_jauh_lebih_sempit_daripada_katalognya(): void
    {
        $this->pilih('5.13');
        $this->pilih('8.3.1');

        $props = $this->get($this->jalur('analisis'))->assertOk()->viewData('page')['props'];

        foreach ([2, 3] as $l) {
            $katalog = count($props['katalog'][$l]);
            $saran   = count($props['saran'][$l]);

            $this->assertGreaterThan(0, $saran);
            $this->assertLessThan($katalog / 2, $saran,
                "Usulan lapis {$l} mencakup {$saran} dari {$katalog} butir — terlalu lebar untuk disebut usulan.");
        }
    }

    #[Test]
    public function rantai_menyebut_akibat_lapis_yang_kosong(): void
    {
        $kosong = $this->get($this->jalur('analisis'))->assertOk()->viewData('page')['props']['rantai'];

        $this->assertFalse($kosong['lengkap']);
        $this->assertSame([1, 2, 3], $kosong['kurang']);
        $this->assertStringContainsString('titik awal', $kosong['pesan']);

        $this->pilih('5.13');
        $this->pilih('8.3.1');

        $duaLapis = $this->get($this->jalur('analisis'))->assertOk()->viewData('page')['props']['rantai'];

        $this->assertSame([3], $duaLapis['kurang']);
        $this->assertFalse($duaLapis['lengkap']);
        $this->assertStringContainsString('Lack of control', $duaLapis['pesan']);

        $this->pilih('9.8.1');

        $penuh = $this->get($this->jalur('analisis'))->assertOk()->viewData('page')['props']['rantai'];

        $this->assertTrue($penuh['lengkap']);
        $this->assertNull($penuh['pesan']);
        $this->assertSame([1, 1, 1], array_column($penuh['lapis'], 'jumlah'));
    }

    /* ═══════════ penjagaan aksi tulis ═══════════ */

    #[Test]
    public function pilihan_yang_ditandai_dari_saran_menyimpan_lapisnya(): void
    {
        $this->pilih('5.13');
        $this->pilih('8.3.1', dariSaran: true);

        $analisis = $this->inv->analisis()->where('metode', 'scat')->firstOrFail();

        $manual = $analisis->pilihan()->where('taksonomi_id', $this->butir('5.13')->id)->firstOrFail();
        $saran  = $analisis->pilihan()->where('taksonomi_id', $this->butir('8.3.1')->id)->firstOrFail();

        $this->assertFalse($manual->dari_saran);
        $this->assertNull($manual->lapis_saran);

        $this->assertTrue($saran->dari_saran);
        $this->assertSame(2, $saran->lapis_saran,
            'Lapis asal usulan tidak tercatat — pertanyaan "mesinnya menolong atau menyetir" '
            .'tidak akan dapat dijawab dengan angka.');
    }

    #[Test]
    public function memilih_butir_yang_sama_dua_kali_tidak_menggandakan_barisnya(): void
    {
        $this->pilih('5.13');
        $this->pilih('5.13');

        $analisis = $this->inv->analisis()->where('metode', 'scat')->firstOrFail();

        $this->assertSame(1, $analisis->pilihan()->count(),
            'Tombol yang ditekan dua kali melahirkan baris kembar.');
    }

    /** Kamus lain memakai tabel yang sama — butirnya tidak boleh nyasar ke sini. */
    #[Test]
    public function butir_di_luar_kamus_scat_ditolak(): void
    {
        $asing = Taksonomi::create([
            'metode' => 'icam', 'kode' => 'IC-1', 'label' => 'Butir ICAM',
            'kategori' => 'Uji', 'tingkat' => 1, 'urutan' => 1,
        ]);

        $this->post($this->jalur('scat'), ['taksonomi_id' => $asing->id])
            ->assertSessionHasErrors('taksonomi_id');

        $this->assertSame(0, $this->inv->analisis()->where('metode', 'scat')->first()?->pilihan()->count() ?? 0);
    }

    /**
     * Pilihan berkas lain tidak dapat dihapus dengan menebak angka.
     *
     * Tabel pilihan tidak punya kolom investigasi_id, jadi kepemilikannya
     * harus ditelusuri lewat analisisnya. Yang memercayai id dari alamat
     * menghapus analisis berkas orang lain tanpa jejak.
     */
    #[Test]
    public function pilihan_berkas_lain_tidak_dapat_dihapus(): void
    {
        $this->pilih('5.13');

        $lain = Investigasi::withoutGlobalScopes()->create([
            'no_investigasi' => NomorInvestigasi::terbitkan(NomorInvestigasi::INVESTIGASI),
            'insiden_id'     => $this->inv->insiden_id,
        ]);

        $milikku = $this->inv->analisis()->where('metode', 'scat')->firstOrFail()->pilihan()->firstOrFail();

        $this->post('/investigasi/berkas/'.$lain->id.'/scat/'.$milikku->id.'/hapus')
            ->assertNotFound();

        $this->assertDatabaseHas('inv_scat_pilihan', ['id' => $milikku->id]);
    }

    #[Test]
    public function berkas_yang_sudah_ditutup_tidak_menerima_pilihan_baru(): void
    {
        $this->inv->update(['status' => 'ditutup', 'tahap' => 'penutupan']);

        $this->post($this->jalur('scat'), ['taksonomi_id' => $this->butir('5.13')->id])
            ->assertStatus(422);
    }

    /* ═══════════ panduan wawancara ═══════════ */

    /**
     * Aturan yang paling penting di seluruh berkas ini.
     *
     * Korban dan saksi langsung hanya ditanyai sampai tingkat APD.
     * Pertanyaan Eliminasi, Substitusi, dan Rekayasa menyangkut
     * kewenangan yang tidak dimilikinya, dan satu-satunya jawaban yang
     * mungkin diberikannya terbaca sebagai pengakuan.
     */
    #[Test]
    public function korban_tidak_ditanyai_pengendalian_di_atas_apd(): void
    {
        foreach (['korban', 'saksi_langsung'] as $peran) {
            foreach (PanduanWawancara::untuk($peran) as $p) {
                if ($p['tingkat'] === null) continue;

                $this->assertSame(5, $p['tingkat'],
                    "Peran {$peran} ditanyai pengendalian tingkat {$p['tingkat']}: ".$p['pertanyaan']);
            }
        }

        /* Saksi tidak langsung tidak ditanyai pengendalian sama sekali. */
        $tingkat = array_filter(array_column(
            PanduanWawancara::untuk('saksi_tidak_langsung'), 'tingkat'));

        $this->assertSame([], array_values($tingkat));
    }

    #[Test]
    public function pengawas_dan_manajemen_ditanyai_seluruh_tingkat(): void
    {
        foreach (['pengawas', 'manajemen'] as $peran) {
            $tingkat = array_values(array_unique(array_filter(
                array_column(PanduanWawancara::untuk($peran), 'tingkat'))));
            sort($tingkat);

            $this->assertSame([1, 2, 3, 4, 5], $tingkat,
                "Peran {$peran} tidak ditanyai seluruh tingkat hierarki: ".implode(',', $tingkat));
        }
    }

    /**
     * Pertanyaan kontekstual hanya muncul bila kata kuncinya ada.
     *
     * Kalau penyaringnya mati, tidak ada galat — hanya daftar empat
     * puluh pertanyaan yang dibaca sekali lalu tidak pernah lagi.
     */
    #[Test]
    public function pertanyaan_kontekstual_ikut_kronologinya(): void
    {
        $tanpa = collect(PanduanWawancara::untuk('korban', 'Tidak ada apa-apa di sini.'))
            ->where('kontekstual', true);

        $this->assertCount(0, $tanpa,
            'Pertanyaan kontekstual muncul pada kronologi yang tidak memuat kata kuncinya.');

        /* Kata kunci diambil dari bank soalnya sendiri, bukan ditebak:
           yang diuji penyaringnya, bukan hafalan isi berkas JSON. */
        $berkunci = \App\Models\Investigasi\WawancaraPertanyaan::whereNotNull('kata_kunci')
            ->where('peran', 'korban')->first();

        if (! $berkunci) {
            $this->markTestSkipped('Bank soal tidak memuat pertanyaan kontekstual untuk korban.');
        }

        $kata = explode('|', $berkunci->kata_kunci)[0];

        $dengan = collect(PanduanWawancara::untuk('korban', 'Kejadiannya menyangkut '.$kata.' di lokasi.'))
            ->where('kontekstual', true);

        $this->assertGreaterThan(0, $dengan->count(),
            "Kata kunci '{$kata}' ada di kronologi tetapi pertanyaannya tidak muncul.");
    }

    /**
     * Layar wawancara membaca kronologi BERBUTIR maupun uraian insiden.
     *
     * Kronologi berbutir kosong sepenuhnya sah di L1. Panduan yang hanya
     * membaca kronologi berbutir akan menyembunyikan seluruh pertanyaan
     * kontekstual pada berkas yang justru paling perlu dituntun.
     */
    #[Test]
    public function panduan_membaca_uraian_insiden_saat_kronologi_masih_kosong(): void
    {
        $berkunci = \App\Models\Investigasi\WawancaraPertanyaan::whereNotNull('kata_kunci')
            ->where('peran', 'korban')->firstOrFail();

        $kata = explode('|', $berkunci->kata_kunci)[0];

        $this->inv->insiden->update(['kronologi' => 'Kejadiannya menyangkut '.$kata.'.']);

        $this->assertSame(0, $this->inv->kronologi()->count());

        $panduan = $this->get($this->jalur('wawancara').'?peran=korban')
            ->assertOk()->viewData('page')['props']['panduan'];

        $this->assertTrue(collect($panduan)->contains('kontekstual', true),
            'Pertanyaan kontekstual tidak muncul padahal kata kuncinya ada di uraian insiden.');
    }

    #[Test]
    public function berita_acara_menyalin_bunyi_pertanyaannya(): void
    {
        $this->post($this->jalur('wawancara'), [
            'narasumber' => 'Sdr. Budi', 'peran' => 'korban', 'tanggal' => '2026-08-20',
        ])->assertSessionHasNoErrors();

        $w = $this->inv->wawancara()->firstOrFail();

        $p = \App\Models\Investigasi\WawancaraPertanyaan::where('peran', 'korban')->firstOrFail();

        /* Bunyinya SENGAJA TIDAK SAMA dengan bank soalnya.
           Pewawancara menyesuaikan kalimatnya di lapangan — itu memang
           kolom yang dapat disunting. Mengirim bunyi yang persis sama
           dengan masternya membuat uji ini tidak dapat membedakan
           "menyimpan yang diketik" dari "membaca ulang dari master",
           dan keduanya kelihatan sama persis sampai ada yang menyunting
           kalimatnya. */
        $bunyiDiketik = 'Saat itu, apakah Bapak sempat mengganjal unitnya lebih dahulu?';
        $this->assertNotSame($p->pertanyaan, $bunyiDiketik);

        $this->post($this->jalur('wawancara/'.$w->id.'/jawab'), [
            'pertanyaan_id' => $p->id,
            'pertanyaan'    => $bunyiDiketik,
            'jawaban'       => 'Saya tidak sempat mengganjal unitnya.',
        ])->assertSessionHasNoErrors();

        $this->assertSame($bunyiDiketik, $w->jawaban()->firstOrFail()->pertanyaan_teks,
            'Yang tersimpan bukan kalimat yang diketik pewawancara melainkan bunyi dari bank soal.');

        /* Dan bank soalnya diperbaiki SETAHUN KEMUDIAN. Berita acara
           yang sudah ditandatangani tidak boleh ikut berubah bunyinya. */
        $p->update(['pertanyaan' => 'Bunyi yang sudah diperbaiki setahun kemudian.']);

        $this->assertSame($bunyiDiketik, $w->jawaban()->firstOrFail()->fresh()->pertanyaan_teks,
            'Bunyi pertanyaan pada berita acara ikut berubah ketika bank soalnya disunting.');
    }

    #[Test]
    public function wawancara_berkas_lain_tidak_dapat_dihapus(): void
    {
        $this->post($this->jalur('wawancara'), ['narasumber' => 'Sdr. Budi', 'peran' => 'korban']);

        $w = $this->inv->wawancara()->firstOrFail();

        $lain = Investigasi::withoutGlobalScopes()->create([
            'no_investigasi' => NomorInvestigasi::terbitkan(NomorInvestigasi::INVESTIGASI),
            'insiden_id'     => $this->inv->insiden_id,
        ]);

        $this->post('/investigasi/berkas/'.$lain->id.'/wawancara/'.$w->id.'/hapus')
            ->assertNotFound();

        $this->assertDatabaseHas('inv_wawancara', ['id' => $w->id]);
    }

    #[Test]
    public function peran_yang_tidak_dikenali_tidak_membuat_layarnya_kosong(): void
    {
        $props = $this->get($this->jalur('wawancara').'?peran=bukan-peran')
            ->assertOk()->viewData('page')['props'];

        $this->assertSame('korban', $props['peran'],
            'Peran asing tidak dikembalikan ke peran bawaan.');
        $this->assertNotEmpty($props['panduan']);
    }
}
