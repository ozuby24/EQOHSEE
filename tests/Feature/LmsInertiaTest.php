<?php

namespace Tests\Feature;

use App\Models\{Course, Enrollment, News, PostTrainingEvaluation, Procedure, Signatory, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * LMS (Inertia) — berita, prosedur, penanda tangan, sertifikat, evaluasi.
 */
class LmsInertiaTest extends TestCase
{
    use RefreshDatabase;

    private function masuk(array $x = []): User
    {
        $u = User::factory()->create(array_merge(['is_admin' => true], $x));
        $this->actingAs($u);

        return $u;
    }

    private function props(string $rute, array $p = []): array
    {
        return $this->get(route($rute, $p))->assertOk()->viewData('page')['props'];
    }

    public function test_halaman_lms_dirender_inertia(): void
    {
        $this->masuk();

        $peta = [
            'news.index'          => 'Berita/Daftar',
            'news.create'         => 'Berita/Form',
            'procedures.index'    => 'Prosedur/Daftar',
            'procedures.create'   => 'Prosedur/Form',
            'signatories.index'   => 'PenandaTangan/Daftar',
            'certificates.index'  => 'Sertifikat/Daftar',
            'evaluations.index'   => 'Evaluasi/Daftar',
            'evaluations.create'  => 'Evaluasi/Form',
        ];

        foreach ($peta as $rute => $komponen) {
            $this->get(route($rute))->assertOk()->assertInertia(
                fn (AssertableInertia $p) => $p->component($komponen)->has('judul')->has('subjudul'),
            );
        }
    }

    /* ══════════════ berita ══════════════ */

    public function test_ringkasan_berita_dipotong_di_server(): void
    {
        // Ringkasan yang tergambar di daftar tetap dipotong di server.
        // Memotongnya dengan CSS berarti isi sepanjang apa pun tetap
        // dikirim hanya untuk menampilkan tiga baris.
        $this->masuk();
        News::create([
            'title' => 'Pengumuman Panjang',
            'content' => str_repeat('Kalimat panjang sekali. ', 60),
            'published_at' => now(),
        ]);

        $baris = $this->props('news.index')['berita'][0];

        $this->assertLessThan(240, strlen($baris['ringkasan']));
        $this->assertStringEndsWith('...', $baris['ringkasan']);
    }

    public function test_isi_berita_ikut_utuh_supaya_pop_out_tidak_perlu_mengambilnya(): void
    {
        /* Kebalikan dari aturan di atas, dan disengaja.
         *
         * Ringkasan dipotong karena hanya tiga barisnya yang tergambar;
         * isinya TIDAK, karena pop-out membukanya tanpa satu pun
         * permintaan jaringan. Pop-out yang masih harus mengambil isinya
         * gagal terbuka justru di sambungan site yang lambat — cacat
         * yang sama pernah mengubah pop-out pengenalan menjadi kotak
         * galat berulang. */
        $this->masuk();

        $isi = str_repeat('Kalimat panjang sekali. ', 60);

        News::create(['title' => 'Pengumuman Panjang', 'content' => $isi, 'published_at' => now()]);

        $baris = $this->props('news.index')['berita'][0];

        $this->assertSame($isi, $baris['isi']);
        $this->assertFalse($baris['terpotong']);
    }

    public function test_isi_yang_luar_biasa_panjang_dipenggal_dan_mengaku(): void
    {
        /* Halaman daftar membawa sepuluh baris sekaligus. Satu
         * pengumuman yang ditempeli seluruh naskah prosedur akan
         * membuatnya berat bagi SEMUA yang membukanya, termasuk yang
         * tidak membuka pengumuman itu.
         *
         * Yang dijaga di sini bukan pemenggalannya melainkan
         * pengakuannya: pengumuman yang kehilangan bagian akhirnya tanpa
         * ada yang tahu lebih buruk daripada yang mengaku terpotong. */
        $this->masuk();

        News::create([
            'title'        => 'Naskah panjang',
            'content'      => str_repeat('a', \App\Support\Pengumuman::BATAS_ISI + 500),
            'published_at' => now(),
        ]);

        $baris = $this->props('news.index')['berita'][0];

        $this->assertSame(\App\Support\Pengumuman::BATAS_ISI, mb_strlen($baris['isi']));
        $this->assertTrue($baris['terpotong'],
            'Isi yang dipenggal harus mengaku dipenggal, kalau tidak bagian akhirnya hilang diam-diam.');
    }

    public function test_bukan_admin_tidak_diberi_tombol_ubah_berita(): void
    {
        $this->masuk(['is_admin' => false]);
        News::create(['title' => 'Kabar', 'content' => 'isi', 'published_at' => now()]);

        $this->assertFalse($this->props('news.index')['bolehUbah']);
    }

    /* ══════════════ penanda tangan ══════════════ */

    public function test_penanda_tangan_aktif_diurutkan_lebih_dulu(): void
    {
        // Yang aktif dipakai otomatis saat sertifikat terbit, jadi ia
        // yang paling perlu terlihat tanpa menggulir.
        $this->masuk();
        Signatory::create(['name' => 'Zulfikar', 'title' => 'KTT', 'is_active' => true]);
        Signatory::create(['name' => 'Andi', 'title' => 'PJO', 'is_active' => false]);

        $nama = array_column($this->props('signatories.index')['penandaTangan'], 'nama');

        $this->assertSame(['Zulfikar', 'Andi'], $nama);
    }

    /* ══════════════ evaluasi ══════════════ */

    public function test_peserta_hanya_melihat_evaluasinya_sendiri(): void
    {
        $saya = $this->masuk(['is_admin' => false, 'lms_role' => 'trainee']);
        $lain = User::factory()->create();

        PostTrainingEvaluation::create($this->nilai(['user_id' => $saya->id]));
        PostTrainingEvaluation::create($this->nilai(['user_id' => $lain->id]));

        $p = $this->props('evaluations.index');

        $this->assertFalse($p['bolehMenilai']);
        $this->assertCount(1, $p['evaluasi']);
        $this->assertSame($saya->name, $p['evaluasi'][0]['peserta']);
    }

    public function test_formulir_evaluasi_membawa_peta_kursus_tiap_peserta(): void
    {
        // Tanpa peta ini trainer harus mencari di seluruh katalog, dan
        // salah pilih menghasilkan evaluasi yang menempel pada kursus
        // yang tidak pernah diikuti orangnya.
        $this->masuk();
        $peserta = User::factory()->create();
        $kursus  = Course::create(['title' => 'Dasar K3', 'slug' => 'dasar-k3']);
        Enrollment::create(['user_id' => $peserta->id, 'course_id' => $kursus->id, 'status' => 'finished']);

        $peta = (array) $this->props('evaluations.create')['kursusPeserta'];

        $this->assertArrayHasKey($peserta->id, $peta);
        $this->assertSame('Dasar K3', $peta[$peserta->id][0]['title']);
    }

    public function test_nilai_akhir_adalah_rerata_keempat_aspeknya(): void
    {
        $this->masuk();
        $peserta = User::factory()->create();

        $this->post(route('evaluations.store'), [
            'user_id' => $peserta->id,
            'knowledge_score' => 100, 'skill_score' => 80,
            'attitude_score' => 70,  'safety_score' => 90,
        ])->assertRedirect();

        $this->assertSame(85, PostTrainingEvaluation::first()->overall_score);
    }

    /* ══════════════ prosedur ══════════════ */

    public function test_prosedur_disaring_di_server(): void
    {
        $this->masuk();
        Procedure::create(['title' => 'Bekerja di Ketinggian', 'code' => 'SOP-01', 'position' => 1]);
        Procedure::create(['title' => 'Ruang Terbatas', 'code' => 'SOP-02', 'position' => 2]);

        $judul = array_column($this->props('procedures.index', ['q' => 'Ketinggian'])['prosedur'], 'judul');

        $this->assertSame(['Bekerja di Ketinggian'], $judul);
    }

    private function nilai(array $x = []): array
    {
        return array_merge([
            'knowledge_score' => 80, 'skill_score' => 80,
            'attitude_score'  => 80, 'safety_score' => 80,
            'overall_score'   => 80,
        ], $x);
    }
}
