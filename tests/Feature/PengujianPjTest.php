<?php

namespace Tests\Feature;

use App\Models\{Company, TpkkpAssessment, TpkkpPengujian, User};
use App\Support\{Tpkkp, TpkkpUji};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pengujian (metode PJ) — kuis kesadaran risiko keselamatan pertambangan.
 *
 * Yang dijaga di berkas ini adalah hal-hal yang, bila runtuh, runtuh
 * TANPA GALAT: nilai tetap keluar, halaman tetap tergambar, dan
 * penilaian tetap terisi — hanya angkanya yang tidak berarti apa-apa.
 *
 * 1. KUNCI JAWABAN TIDAK IKUT KE PERAMBAN. Acuan yang saya ikuti
 *    memeriksa jawaban di sisi klien, sehingga seluruh kunci ada di
 *    dalam berkas yang dapat dibuka lewat View Source. Kalau itu
 *    terulang di sini, tidak ada yang gagal — nilai justru naik, dan
 *    naiknya terbaca sebagai "kesadaran keselamatan membaik".
 *
 * 2. BATAS WAKTU DITEGAKKAN SERVER. Pencacah di layar dapat dihentikan
 *    dari konsol peramban dengan satu baris. Bila hanya ia yang
 *    menegakkan, batas 90 detik itu hiasan.
 *
 * 3. SATU ORANG SATU KALI. Tanpanya, satu orang yang mengulang sepuluh
 *    kali sampai nilainya bagus menggeser rerata seluruh peserta.
 *
 * 4. PEMBULATAN SETENGAH KE BAWAH, seragam dengan seluruh rerata lain
 *    di modul ini. Tingkat 2,5 yang dibulatkan naik menjadi 3 berarti
 *    menyatakan kematangan yang belum benar-benar dicapai.
 */
class PengujianPjTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $admin;
    private string $token = 'uji-token-pengujian';

    protected function setUp(): void
    {
        parent::setUp();

        $this->c = Company::create(['name' => 'PT Uji Pengujian', 'doc_no_prefix' => 'UP']);

        $this->admin = User::factory()->create([
            'is_admin' => true, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);

        TpkkpAssessment::create([
            'company_id' => $this->c->id,
            'tahun'  => now()->year,
            'judul'  => 'Penilaian uji',
            'status' => 'draf',
            'profil' => ['tokens' => [$this->c->id => $this->token]],
        ]);
    }

    /* ═══════════ bantu ═══════════ */

    private array $identitas = [
        'nama'       => 'Budi Santoso',
        'nrp'        => '2021000',
        'jabatan'    => 'Operator Dump Truck',
        'dept'       => 'Mining / Operation / Produksi',
        'perusahaan' => 'PT Uji Pengujian',
    ];

    /** Sampai di soal pertama, pencacah waktu berjalan. */
    private function mulaiKuis(array $ganti = []): void
    {
        $this->post("/uji/{$this->token}/siap", $ganti + $this->identitas)
            ->assertRedirect("/uji/{$this->token}/aturan");

        $this->post("/uji/{$this->token}/jalan")
            ->assertRedirect("/uji/{$this->token}/soal");
    }

    private function sesi(): array
    {
        return (array) session('uji.'.$this->token, []);
    }

    /**
     * Kerjakan seluruh soal.
     *
     * $benar: berapa soal pertama yang dijawab BENAR; sisanya dijawab
     * dengan pilihan yang pasti salah.
     */
    private function kerjakan(int $benar): void
    {
        $jumlah = TpkkpUji::jumlahSoal();

        for ($i = 0; $i < $jumlah; $i++) {
            $sesi  = $this->sesi();
            $kunci = TpkkpUji::kunciKe($sesi['set'], $sesi['idx']);
            $soal  = TpkkpUji::soalKe($sesi['set'], $sesi['idx']);

            $pilih = $i < $benar ? $kunci : ($kunci + 1) % count($soal['pilihan']);

            $this->post("/uji/{$this->token}/jawab", ['nomor' => $i + 1, 'pilih' => $pilih]);
        }
    }

    /* ═══════════ 1 · kunci jawaban tidak ikut ke peramban ═══════════ */

    #[Test]
    public function halaman_soal_tidak_membawa_kunci_jawaban(): void
    {
        $this->mulaiKuis();

        $r = $this->get("/uji/{$this->token}/soal");
        $r->assertOk();

        $props = $r->viewData('page')['props'];

        /* Daftar prop DITULIS TEGAS, bukan sekadar "tidak ada kunci di
           antara nama prop yang saya duga". Prop baru yang membocorkan
           kunci hampir pasti bernama sesuatu yang tidak terpikir
           sekarang; yang dapat dijaga adalah bahwa TIDAK ADA prop lain
           selain yang disebut di sini. */
        $boleh = ['token', 'nomor', 'jumlahSoal', 'pertanyaan', 'pilihan', 'detikPerSoal', 'sisaDetik'];

        /* Prop bersama seluruh halaman (tema, kilat, pengumuman, …)
           diambil dari middleware-nya, bukan disalin ke sini. Salinan
           daftar itu akan basi diam-diam: prop bersama yang baru
           ditambahkan membuat uji ini gagal pada halaman yang sama
           sekali tidak bersalah. */
        $bersama = array_keys(
            app(\App\Http\Middleware\HandleInertiaRequests::class)->share(request())
        );

        $tambahan = array_diff(array_keys($props), $boleh, $bersama, ['errors']);

        $this->assertSame([], array_values($tambahan),
            'Halaman soal membawa prop di luar yang diizinkan: '.implode(', ', $tambahan));

        /* Bank soalnya juga tidak ikut. Halaman yang mengirim seluruh
           bank tidak perlu menyebut kunci untuk membocorkannya: pada
           bank ini jawaban benar SELALU elemen pertama, jadi bank yang
           terkirim adalah kunci yang terkirim. */
        $isi     = $r->getContent();
        $terkini = $props['pertanyaan'];

        foreach (TpkkpUji::bank() as $butir) {
            if ($butir['q'] === $terkini) continue;

            $this->assertStringNotContainsString($butir['q'], $isi,
                'Soal lain ikut terkirim — bank soalnya terbaca dari halaman ini.');
        }
    }

    #[Test]
    public function pilihan_yang_dikirim_tidak_selalu_berurutan_bank(): void
    {
        /* Diperiksa lewat HTTP, bukan lewat TpkkpUji::susun() saja:
           yang membocorkan kunci adalah apa yang benar-benar sampai ke
           peramban, dan pengacakan yang benar di dalam kelas bantu
           tidak menolong bila controller-nya mengirim urutan bank. */
        $bankOrder = 0;

        for ($i = 0; $i < 12; $i++) {
            $this->flushSession();
            $this->mulaiKuis();

            $props = $this->get("/uji/{$this->token}/soal")->viewData('page')['props'];
            $sesi  = $this->sesi();
            $butir = TpkkpUji::bank()[$sesi['set'][0]['s']];

            if ($props['pilihan'] === $butir['a']) $bankOrder++;
        }

        $this->assertLessThan(12, $bankOrder,
            'Pilihan selalu sampai ke peramban dalam urutan bank — jawaban benar selalu A.');
    }

    #[Test]
    public function posisi_jawaban_benar_berpindah_antar_peserta(): void
    {
        $posisi = [];

        for ($i = 0; $i < 25; $i++) {
            $set = TpkkpUji::susun();
            $posisi[] = TpkkpUji::kunciKe($set, 0);
        }

        $this->assertGreaterThan(1, count(array_unique($posisi)),
            'Posisi jawaban benar tidak pernah berpindah — pengacakan pilihan tidak bekerja.');
    }

    #[Test]
    public function soal_berbeda_antar_peserta(): void
    {
        $a = array_column(TpkkpUji::susun(), 's');
        $b = array_column(TpkkpUji::susun(), 's');

        $this->assertNotSame($a, $b, 'Dua sesi mendapat susunan soal yang persis sama.');
        $this->assertCount(TpkkpUji::jumlahSoal(), $a);
        $this->assertSame(count($a), count(array_unique($a)), 'Ada soal yang muncul dua kali.');
    }

    /* ═══════════ 2 · penilaian di sisi server ═══════════ */

    #[Test]
    public function seluruh_jawaban_benar_menghasilkan_tingkat_tertinggi(): void
    {
        $this->mulaiKuis();
        $this->kerjakan(TpkkpUji::jumlahSoal());

        $row = TpkkpPengujian::withoutGlobalScopes()->first();

        $this->assertNotNull($row);
        $this->assertSame(TpkkpUji::jumlahSoal(), $row->benar);
        $this->assertSame(5, $row->tingkat);
    }

    #[Test]
    public function seluruh_jawaban_salah_menghasilkan_tingkat_terendah(): void
    {
        $this->mulaiKuis();
        $this->kerjakan(0);

        $row = TpkkpPengujian::withoutGlobalScopes()->first();

        $this->assertSame(0, $row->benar);
        $this->assertSame(1, $row->tingkat);
    }

    #[Test]
    public function nilai_tidak_pernah_ditampilkan_kepada_peserta(): void
    {
        $this->mulaiKuis();
        $this->kerjakan(TpkkpUji::jumlahSoal());

        $r = $this->get("/uji/{$this->token}/selesai");
        $r->assertOk();

        $props = $r->viewData('page')['props'];

        foreach (['benar', 'total', 'skor', 'skor_pct', 'tingkat', 'nilai'] as $k) {
            $this->assertArrayNotHasKey($k, $props,
                "Layar penutup membawa prop \"{$k}\" — nilai peserta tidak boleh ditampilkan.");
        }

        $this->assertStringNotContainsString('15 / 15', $r->getContent());
    }

    /* ═══════════ 3 · batas waktu ditegakkan server ═══════════ */

    #[Test]
    public function jawaban_yang_lewat_batas_waktu_tidak_dihitung(): void
    {
        $this->mulaiKuis();

        $sesi  = $this->sesi();
        $kunci = TpkkpUji::kunciKe($sesi['set'], 0);

        /* Lewat batas, di luar kelonggaran jaringan. */
        $this->travel(TpkkpUji::detikPerSoal() + TpkkpUji::KELONGGARAN + 5)->seconds();

        $this->post("/uji/{$this->token}/jawab", ['nomor' => 1, 'pilih' => $kunci])
            ->assertRedirect("/uji/{$this->token}/soal");

        $this->assertNull($this->sesi()['jawab'][0],
            'Jawaban yang tiba lewat batas waktu tetap tercatat — batasnya tidak ditegakkan server.');
    }

    #[Test]
    public function jawaban_yang_masih_dalam_kelonggaran_tetap_dihitung(): void
    {
        $this->mulaiKuis();

        $sesi  = $this->sesi();
        $kunci = TpkkpUji::kunciKe($sesi['set'], 0);

        $this->travel(TpkkpUji::detikPerSoal() + 1)->seconds();   // telat sedikit, jaringan lambat

        $this->post("/uji/{$this->token}/jawab", ['nomor' => 1, 'pilih' => $kunci]);

        $this->assertSame($kunci, $this->sesi()['jawab'][0],
            'Jawaban dalam batas kelonggaran ikut dibuang — jaringan lambat dihukum.');
    }

    #[Test]
    public function memuat_ulang_halaman_soal_tidak_memperpanjang_waktu(): void
    {
        $this->mulaiKuis();

        $this->travel(30)->seconds();

        $props = $this->get("/uji/{$this->token}/soal")->viewData('page')['props'];

        $this->assertEqualsWithDelta(
            TpkkpUji::detikPerSoal() - 30, $props['sisaDetik'], 2,
            'Sisa waktu dihitung ulang dari awal saat halaman dimuat — memuat ulang menambah waktu.'
        );
    }

    /* ═══════════ 4 · satu arah ═══════════ */

    #[Test]
    public function kiriman_untuk_soal_yang_sudah_lewat_diabaikan(): void
    {
        $this->mulaiKuis();

        $sesi = $this->sesi();
        $this->post("/uji/{$this->token}/jawab", ['nomor' => 1, 'pilih' => 0]);
        $this->assertSame(1, $this->sesi()['idx']);

        /* Kiriman ulang untuk soal 1 — tombol ditekan dua kali. */
        $this->post("/uji/{$this->token}/jawab", ['nomor' => 1, 'pilih' => 2]);

        $this->assertSame(1, $this->sesi()['idx'],
            'Kiriman ulang untuk soal yang sudah lewat ikut memajukan nomor soal.');
        $this->assertSame(0, $this->sesi()['jawab'][0],
            'Jawaban soal yang sudah lewat masih dapat diubah.');
    }

    #[Test]
    public function halaman_soal_hanya_memberi_soal_yang_sedang_dikerjakan(): void
    {
        $this->mulaiKuis();

        $sesi     = $this->sesi();
        $berikut  = TpkkpUji::soalKe($sesi['set'], 1)['teks'];

        $r = $this->get("/uji/{$this->token}/soal");

        $this->assertSame(1, $r->viewData('page')['props']['nomor']);
        $this->assertStringNotContainsString($berikut, $r->getContent(),
            'Soal berikutnya sudah terbaca sebelum soal ini dijawab.');
    }

    /* ═══════════ 5 · satu orang satu kali ═══════════ */

    #[Test]
    public function orang_yang_sama_tidak_dapat_mengerjakan_dua_kali(): void
    {
        $this->mulaiKuis();
        $this->kerjakan(TpkkpUji::jumlahSoal());

        $this->assertSame(1, TpkkpPengujian::withoutGlobalScopes()->count());

        /* Coba lagi dari awal, identitas sama. */
        $this->post("/uji/{$this->token}/siap", $this->identitas);
        $this->get("/uji/{$this->token}/aturan")
            ->assertRedirect("/uji/{$this->token}/selesai");

        $this->post("/uji/{$this->token}/jalan")
            ->assertRedirect("/uji/{$this->token}/selesai");

        $this->assertSame(1, TpkkpPengujian::withoutGlobalScopes()->count(),
            'Orang yang sama berhasil mengerjakan dua kali.');
    }

    #[Test]
    public function nama_yang_berbeda_ejaan_dihitung_orang_yang_sama(): void
    {
        $a = TpkkpUji::kunciIdentitas($this->identitas);
        $b = TpkkpUji::kunciIdentitas(['nama' => '  budi   SANTOSO '] + $this->identitas);

        $this->assertSame($a, $b);
    }

    /* ═══════════ 6 · pita konversi ═══════════ */

    #[Test]
    public function pita_konversi_nilai_ke_tingkat(): void
    {
        $this->assertSame(1, TpkkpUji::tingkatDari(0.0));
        $this->assertSame(1, TpkkpUji::tingkatDari(0.399));
        $this->assertSame(2, TpkkpUji::tingkatDari(0.40));
        $this->assertSame(2, TpkkpUji::tingkatDari(0.549));
        $this->assertSame(3, TpkkpUji::tingkatDari(0.55));
        $this->assertSame(4, TpkkpUji::tingkatDari(0.70));
        $this->assertSame(5, TpkkpUji::tingkatDari(0.85));
        $this->assertSame(5, TpkkpUji::tingkatDari(1.0));
        $this->assertNull(TpkkpUji::tingkatDari(null));
    }

    /* ═══════════ 7 · rerata seluruh peserta ═══════════ */

    #[Test]
    public function rerata_tingkat_dibulatkan_setengah_ke_bawah(): void
    {
        $this->buatHasil([2, 3]);           // rerata 2,5

        $ringkas = TpkkpUji::ringkas(TpkkpPengujian::withoutGlobalScopes()->get());

        $this->assertSame(2.5, $ringkas['rerataTingkat']);
        $this->assertSame(2, $ringkas['tingkat'],
            'Rerata 2,5 dibulatkan naik — kematangan yang belum dicapai dinyatakan tercapai.');
    }

    #[Test]
    public function rerata_diambil_dari_tingkat_peserta_bukan_dari_persentase(): void
    {
        /* Dua peserta ekstrem: 15/15 (tingkat 5) dan 0/15 (tingkat 1).
           Rerata TINGKAT = 3. Rerata PERSENTASE = 50% yang jatuh ke
           tingkat 2 — pitanya tidak linear, jadi keduanya berbeda. */
        $this->buatHasil([5, 1], [1.0, 0.0]);

        $ringkas = TpkkpUji::ringkas(TpkkpPengujian::withoutGlobalScopes()->get());

        $this->assertSame(3, $ringkas['tingkat'],
            'Rerata diambil dari persentase gabungan, bukan dari tingkat tiap peserta.');
    }

    #[Test]
    public function sebaran_menyebut_tingkat_yang_kosong(): void
    {
        $this->buatHasil([5, 5]);

        $sebaran = TpkkpUji::ringkas(TpkkpPengujian::withoutGlobalScopes()->get())['sebaran'];

        $this->assertSame([1, 2, 3, 4, 5], array_keys($sebaran),
            'Tingkat tanpa peserta hilang dari sebaran — grafiknya kehilangan batang nol.');
        $this->assertSame(0, $sebaran[1]);
        $this->assertSame(2, $sebaran[5]);
    }

    #[Test]
    public function ringkas_tanpa_peserta_tidak_mengarang_tingkat(): void
    {
        $r = TpkkpUji::ringkas([]);

        $this->assertSame(0, $r['peserta']);
        $this->assertNull($r['tingkat']);
        $this->assertNull($r['rerataPct']);
    }

    /* ═══════════ 8 · penerapan ke skor PJ ═══════════ */

    #[Test]
    public function terapkan_menulis_tingkat_gabungan_ke_skor_pj(): void
    {
        $this->buatHasil([4, 4]);

        $this->actingAs($this->admin)
            ->withSession(['tpkkp_company' => $this->c->id])
            ->post('/pengujian/terapkan', ['company_id' => $this->c->id])
            ->assertRedirect();

        $scores = TpkkpAssessment::forYear(now()->year)->scores;

        $this->assertSame(4, $scores['PJ']['1.1.1']['v']);
        $this->assertStringContainsString('2 peserta', $scores['PJ']['1.1.1']['ket']);
    }

    #[Test]
    public function terapkan_hanya_menyentuh_item_bermetode_pj(): void
    {
        $this->buatHasil([4]);

        $this->actingAs($this->admin)->post('/pengujian/terapkan', ['company_id' => $this->c->id]);

        $scores = TpkkpAssessment::forYear(now()->year)->scores;

        foreach (array_keys($scores['PJ'] ?? []) as $kode) {
            $item = Tpkkp::itemByCode($kode);

            $this->assertNotNull($item, "Kode {$kode} tidak ada pada instrumen.");
            $this->assertContains('PJ', $item['methods'],
                "Item {$kode} tidak memakai metode PJ tetapi ikut ditulis.");
        }

        $this->assertArrayNotHasKey('KS', $scores ?? [],
            'Penerapan pengujian ikut menyentuh metode lain.');
    }

    #[Test]
    public function terapkan_tanpa_peserta_tidak_mengubah_skor(): void
    {
        $a = TpkkpAssessment::forYear(now()->year);
        $a->update(['scores' => ['PJ' => ['1.1.1' => ['v' => 3, 'e' => [], 'ket' => 'diisi tangan']]]]);

        $this->actingAs($this->admin)->post('/pengujian/terapkan', ['company_id' => $this->c->id]);

        $this->assertSame(3, TpkkpAssessment::forYear(now()->year)->scores['PJ']['1.1.1']['v'],
            'Penerapan tanpa satu pun peserta menimpa nilai yang sudah diisi tangan.');
    }

    #[Test]
    public function bukan_admin_tidak_boleh_menerapkan(): void
    {
        $this->buatHasil([4]);

        $biasa = User::factory()->create([
            'is_admin' => false, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);

        $this->actingAs($biasa)
            ->post('/pengujian/terapkan', ['company_id' => $this->c->id])
            ->assertForbidden();
    }

    /* ═══════════ 9 · halaman admin ═══════════ */

    #[Test]
    public function halaman_admin_menyebut_peserta_dan_tautan(): void
    {
        $this->buatHasil([4, 2]);

        $r = $this->actingAs($this->admin)->get('/tpkkp/pengujian');
        $r->assertOk();

        $props = $r->viewData('page')['props'];

        $this->assertSame('Tpkkp/Pengujian', $r->viewData('page')['component']);
        $this->assertCount(2, $props['peserta']);
        $this->assertStringContainsString("/uji/{$this->token}", $props['urlPublik']);
        $this->assertSame(3, $props['ringkas']['tingkat']);   // rerata 3,0
    }

    #[Test]
    public function tautan_pengujian_sama_dengan_tautan_kuesioner(): void
    {
        $props = $this->actingAs($this->admin)->get('/tpkkp/pengujian')
            ->viewData('page')['props'];

        $kues = $this->actingAs($this->admin)->get('/tpkkp/kuesioner')
            ->viewData('page')['props'];

        $this->assertStringContainsString($this->token, $props['urlPublik']);
        $this->assertStringContainsString($this->token, $kues['urlPublik']);
    }

    #[Test]
    public function tautan_yang_sudah_diganti_tidak_berlaku(): void
    {
        $this->get('/uji/token-yang-tidak-pernah-ada')->assertNotFound();
    }

    /* ═══════════ bantu ═══════════ */

    /**
     * @param  list<int>  $tingkat
     * @param  list<float>|null  $pct
     */
    private function buatHasil(array $tingkat, ?array $pct = null): void
    {
        foreach ($tingkat as $i => $t) {
            TpkkpPengujian::create([
                'company_id' => $this->c->id,
                'nama'       => 'Peserta '.($i + 1),
                'jabatan'    => 'Operator',
                'dept'       => 'Produksi',
                'perusahaan' => $this->c->name,
                'kunci_identitas' => 'peserta-'.$i,
                'benar'      => (int) round(($pct[$i] ?? 0.5) * TpkkpUji::jumlahSoal()),
                'total'      => TpkkpUji::jumlahSoal(),
                'skor_pct'   => $pct[$i] ?? 0.5,
                'tingkat'    => $t,
                'ts'         => now(),
            ]);
        }
    }
}
