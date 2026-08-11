<?php

namespace Tests\Feature;

use App\Models\{Certificate, Course, Enrollment, Module, News, User};
use App\Support\{IkonNav, Kategori};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dashboard LMS.
 *
 * Dashboard kosong dan dashboard terisi adalah dua halaman yang berbeda:
 * yang kosong melewati hampir seluruh perulangannya, sehingga kesalahan
 * di dalam kartu kursus tidak pernah terpanggil. Berkas ini merender
 * keduanya — sebuah galat pernah lolos justru karena hanya keadaan
 * kosong yang pernah diuji.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(): User
    {
        return User::factory()->create(['name' => 'Febrian Ardiansyah']);
    }

    private function kursus(string $judul, string $kategori, int $modul = 3): Course
    {
        $c = Course::create([
            'title' => $judul, 'description' => 'Uraian singkat kursus untuk pengujian.',
            'category' => $kategori,
        ]);
        for ($i = 1; $i <= $modul; $i++) {
            Module::create(['course_id' => $c->id, 'title' => "Modul {$i}", 'order_index' => $i]);
        }
        return $c;
    }

    /* ---------- keadaan kosong ---------- */

    public function test_dashboard_terbuka_tanpa_data_sama_sekali(): void
    {
        $this->actingAs($this->pengguna());

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Belum ada kursus yang diikuti')
            ->assertDontSee('NaN');
    }

    public function test_kemajuan_tanpa_pendaftaran_bernilai_nol_bukan_seratus(): void
    {
        $this->actingAs($this->pengguna());

        // Membagi dengan nol lalu menampilkan 100% adalah kesalahan yang
        // paling meyakinkan bentuknya — angkanya tampak wajar.
        $this->get('/dashboard')->assertOk()->assertSee('0%');
    }

    /* ---------- keadaan terisi ---------- */

    public function test_dashboard_terbuka_dengan_kursus_yang_diikuti(): void
    {
        $u = $this->pengguna();
        $this->actingAs($u);

        $a = $this->kursus('Dasar Keselamatan Pertambangan', 'Wajib', 3);
        $b = $this->kursus('Keselamatan Berkendara', 'Operasional', 2);

        Enrollment::create(['user_id' => $u->id, 'course_id' => $a->id, 'progress' => 82, 'status' => 'ongoing']);
        Enrollment::create(['user_id' => $u->id, 'course_id' => $b->id, 'progress' => 100, 'status' => 'finished']);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Dasar Keselamatan Pertambangan')
            ->assertSee('Keselamatan Berkendara')
            ->assertSee('82%')          // kemajuan kursus pertama
            ->assertSee('3 Modul')      // jumlah modul, bukan daftar pintasan modul
            ->assertSee('Lanjut Belajar');
    }

    /**
     * Blok @php pada view berbagi ruang nama dengan seluruh berkas.
     *
     * Sebuah variabel bernama $modul di dalam kartu kursus pernah menimpa
     * $modul milik controller — daftar pintasan modul berubah menjadi
     * bilangan, dan halamannya galat. Uji ini merender keduanya sekaligus
     * supaya tabrakan seperti itu tidak dapat terulang diam-diam.
     */
    public function test_kartu_kursus_dan_pintasan_modul_tampil_berdampingan(): void
    {
        $u = $this->pengguna();
        $this->actingAs($u);

        $c = $this->kursus('Pengendalian Risiko Operasional', 'Wajib', 4);
        Enrollment::create(['user_id' => $u->id, 'course_id' => $c->id, 'progress' => 25, 'status' => 'ongoing']);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('4 Modul')          // dari kartu kursus
            ->assertSee('Modul Lainnya')    // judul daftar pintasan
            ->assertSee('Hazard Report');   // salah satu pintasannya
    }

    public function test_kemajuan_rata_rata_dihitung_dari_pendaftaran(): void
    {
        $u = $this->pengguna();
        $this->actingAs($u);

        foreach ([40, 60, 80] as $i => $p) {
            $c = $this->kursus("Kursus {$i}", 'Wajib', 1);
            Enrollment::create(['user_id' => $u->id, 'course_id' => $c->id, 'progress' => $p, 'status' => 'ongoing']);
        }

        // Rata-rata 40, 60, 80 adalah 60 — bukan jumlahnya, dan bukan
        // dibagi jumlah kursus yang tersedia.
        $this->get('/dashboard')->assertOk()->assertSee('60%');
    }

    public function test_kursus_selesai_dan_sertifikat_terhitung_terpisah(): void
    {
        $u = $this->pengguna();
        $this->actingAs($u);

        $a = $this->kursus('Kursus A', 'Wajib', 1);
        $b = $this->kursus('Kursus B', 'Operasional', 1);
        Enrollment::create(['user_id' => $u->id, 'course_id' => $a->id, 'progress' => 100, 'status' => 'finished']);
        Enrollment::create(['user_id' => $u->id, 'course_id' => $b->id, 'progress' => 30,  'status' => 'ongoing']);

        $halaman = $this->get('/dashboard')->assertOk();

        // Satu kursus selesai; sertifikat belum tentu ikut terbit, jadi
        // keduanya tidak boleh dihitung dari angka yang sama.
        $halaman->assertSee('Kursus Selesai');
        $halaman->assertSee('Sertifikat');
        $this->assertSame(0, Certificate::where('user_id', $u->id)->count());
    }

    /* ---------- kerangka halaman ---------- */

    public function test_bilah_samping_membawa_lambang_dan_taglinenya(): void
    {
        $this->actingAs($this->pengguna());

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Safety is Our Priority')
            ->assertSee('eqohsee-mark-white.svg', false);
    }

    public function test_bilah_atas_membawa_judul_dan_subjudul(): void
    {
        $this->actingAs($this->pengguna());

        // Subjudul menjelaskan halamannya; judul sendirian menyisakan
        // pertanyaan "dashboard apa".
        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Kelola pembelajaran dan tingkatkan kompetensi Anda');
    }

    public function test_lencana_lonceng_mengikuti_jumlah_pengumuman(): void
    {
        $this->actingAs($this->pengguna());

        // Yang diperiksa adalah lencananya, bukan sekadar nama kelasnya —
        // '.eq-lonceng-titik' juga tertulis di lembar gaya sebaris, jadi
        // namanya selalu ada di halaman entah lencananya tampil atau tidak.
        $this->get('/dashboard')->assertOk()->assertDontSee('<span class="eq-lonceng-titik">', false);

        News::create(['title' => 'Pelatihan wajib bulan ini', 'content' => 'Isi pengumuman.',
                      'published_at' => now()]);

        $this->get('/dashboard')->assertOk()->assertSee('<span class="eq-lonceng-titik">1</span>', false);
    }

    /**
     * Ikon nav harus benar-benar sampai ke halaman.
     *
     * Pustaka ikonnya pernah didefinisikan sebagai closure di dalam sebuah
     * partial yang disisipkan `@include`. Variabel yang dibuat di dalam
     * sisipan tidak kembali ke view pemanggil, jadi fungsinya selalu null
     * dan seluruh ikon menghilang — tanpa satu pun galat muncul.
     */
    public function test_menu_bilah_samping_membawa_ikon(): void
    {
        $this->actingAs($this->pengguna());

        $halaman = $this->get('/dashboard')->assertOk();

        $html = $halaman->getContent();
        $this->assertGreaterThanOrEqual(
            6, substr_count($html, 'class="eq-navico"'),
            'Setiap butir menu seharusnya membawa ikonnya sendiri.'
        );

        // Ikon Dashboard dan Kursus tidak boleh berupa gambar yang sama —
        // daftar yang seluruh ikonnya seragam sama saja dengan tanpa ikon.
        $this->assertStringContainsString(IkonNav::JALUR['dashboard'], $html);
        $this->assertStringContainsString(IkonNav::JALUR['kursus'], $html);
    }

    public function test_label_menu_dipetakan_ke_ikon_yang_masuk_akal(): void
    {
        // Kunci terpanjang menang, jadi 'Evaluasi SOP' tidak boleh jatuh ke
        // ikon 'evaluasi' yang lebih pendek dan berbeda maksudnya.
        $this->assertSame('dashboard',  IkonNav::nama('Dashboard'));
        $this->assertSame('evaluasi',   IkonNav::nama('Evaluasi SOP'));
        $this->assertSame('nilai',      IkonNav::nama('Evaluasi'));
        $this->assertSame('prosedur',   IkonNav::nama('Prosedur & SOP'));
        $this->assertSame('sertifikat', IkonNav::nama('Sertifikat'));
        $this->assertSame('default',    IkonNav::nama('Label Yang Tidak Dikenal'));
    }

    public function test_dua_kursus_berdampingan_tidak_memakai_foto_yang_sama(): void
    {
        $u = $this->pengguna();
        $this->actingAs($u);

        // Kategori di luar daftar pemetaan pernah semuanya jatuh ke satu foto
        // cadangan, sehingga katalog tampak seperti satu kursus yang diulang.
        foreach (['Kategori Asing A', 'Kategori Asing B'] as $i => $kat) {
            $c = $this->kursus("Kursus {$i}", $kat, 1);
            Enrollment::create(['user_id' => $u->id, 'course_id' => $c->id,
                                'progress' => 10, 'status' => 'ongoing']);
        }

        $html = $this->get('/dashboard')->assertOk()->getContent();

        // Hanya sampul di dalam kartu kursus yang dihitung: foto sambutan di
        // atas halaman berasal dari galeri yang sama, dan nama kelasnya juga
        // muncul di lembar gaya — keduanya ikut tertangkap kalau pencocokan
        // tidak dipatok ke markup kartunya.
        preg_match_all('#<div class="eq-kursus-gambar">\s*<img src="[^"]*/galeri/([a-z]+)\.jpg#',
                       $html, $cocok);

        $this->assertCount(2, $cocok[1], 'Kedua kartu kursus seharusnya bersampul.');
        $this->assertCount(2, array_unique($cocok[1]),
            'Dua kursus tanpa gambar sendiri seharusnya tidak berbagi satu foto cadangan.');
    }

    /* ---------- warna kategori ---------- */

    public function test_tiap_kategori_punya_warna_sendiri(): void
    {
        $this->assertNotSame(Kategori::nada('Wajib'), Kategori::nada('Operasional'));
        $this->assertNotSame(Kategori::nada('Keselamatan Kerja'), Kategori::nada('Tanggap Darurat'));

        foreach (['Wajib', 'Operasional', 'Lingkungan'] as $k) {
            $this->assertContains(Kategori::nada($k), Kategori::NADA);
        }
    }

    /**
     * Warna terikat pada nama kategori, bukan pada urutan tampilnya.
     *
     * Warna sempat dipilih dari indeks perulangan, sehingga "Wajib" tampil
     * jingga di satu halaman dan biru di halaman lain hanya karena
     * daftarnya terurut berbeda — dan warna yang berpindah-pindah tidak
     * dapat dipakai mengenali apa pun, yang justru satu-satunya gunanya.
     */
    public function test_warna_kategori_tidak_berubah_karena_urutan(): void
    {
        $sekali = Kategori::nada('Operasional');

        // Kategori lain yang muncul lebih dulu tidak boleh menggesernya.
        foreach (['Wajib', 'Lingkungan', 'Kesehatan Kerja'] as $lain) {
            Kategori::nada($lain);
        }

        $this->assertSame($sekali, Kategori::nada('Operasional'));
    }

    public function test_kategori_tak_dikenal_tetap_konsisten_warnanya(): void
    {
        // Kategori baru tidak perlu didaftarkan lebih dulu supaya warnanya
        // tetap sama setiap kali halaman dimuat.
        $a = Kategori::nada('Kategori Yang Belum Terdaftar');
        $this->assertContains($a, Kategori::NADA);
        $this->assertSame($a, Kategori::nada('Kategori Yang Belum Terdaftar'));
    }

    public function test_lencana_kartu_membawa_warna_kategorinya(): void
    {
        $u = $this->pengguna();
        $this->actingAs($u);

        $a = $this->kursus('Kursus Wajib', 'Wajib', 1);
        $b = $this->kursus('Kursus Operasional', 'Operasional', 1);
        Enrollment::create(['user_id' => $u->id, 'course_id' => $a->id, 'progress' => 10, 'status' => 'ongoing']);
        Enrollment::create(['user_id' => $u->id, 'course_id' => $b->id, 'progress' => 10, 'status' => 'ongoing']);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('k-'.Kategori::nada('Wajib'), false)
            ->assertSee('k-'.Kategori::nada('Operasional'), false);
    }

    /**
     * Aturan dasar lencana tidak boleh menetapkan latar sendiri.
     *
     * `.eq-kursus-lencana i` adalah kelas + elemen, jadi lebih kuat daripada
     * kelas warna tunggal seperti `.k-kuning`. Sebuah `background` di aturan
     * dasar menimpa seluruh warna kategori dan membuat setiap lencana tampil
     * dengan latar yang persis sama — halamannya tetap terbentuk, tidak ada
     * galat, dan warnanya hilang tanpa suara.
     */
    public function test_aturan_dasar_lencana_tidak_menimpa_warna_kategori(): void
    {
        $gaya = file_get_contents(resource_path('views/partials/eq-visual.blade.php'));

        preg_match('/\.eq-kursus-lencana i\{(.*?)\}/s', $gaya, $cocok);
        $this->assertNotEmpty($cocok, 'Aturan dasar lencana tidak ditemukan.');
        $this->assertStringNotContainsString('background', $cocok[1],
            'Aturan dasar lencana menetapkan latar dan akan menimpa warna kategori.');
    }

    public function test_kategori_kursus_dikelompokkan_beserta_jumlahnya(): void
    {
        $this->actingAs($this->pengguna());

        $this->kursus('Kursus A', 'Keselamatan Kerja', 1);
        $this->kursus('Kursus B', 'Keselamatan Kerja', 1);
        $this->kursus('Kursus C', 'Operasional', 1);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Keselamatan Kerja')
            ->assertSee('2 Kursus')
            ->assertSee('1 Kursus');
    }
}
