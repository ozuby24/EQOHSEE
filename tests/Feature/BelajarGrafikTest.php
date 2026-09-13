<?php

namespace Tests\Feature;

use App\Models\{Certificate, Course, Enrollment, Module, ModuleCompletion, Quiz,
    QuizAttempt, User};
use App\Support\BelajarGrafik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Grafik dasbor pembelajaran.
 *
 * Yang dijaga bukan rupanya melainkan hal-hal yang bila salah tidak
 * menimbulkan galat apa pun: hari kosong yang hilang dari deret, kunci
 * warna yang tidak dikenal, dan angka milik orang lain yang bocor ke
 * dasbor pribadi seseorang.
 */
class BelajarGrafikTest extends TestCase
{
    use RefreshDatabase;

    private function peserta(array $atribut = []): User
    {
        return User::factory()->create($atribut + ['email_verified_at' => now()]);
    }

    private function kursus(string $judul = 'Kursus Uji'): Course
    {
        return Course::create(['title' => $judul, 'category' => 'Keselamatan']);
    }

    /**
     * Hari tanpa kegiatan tetap ada di deretnya, bernilai nol.
     *
     * Deret yang melompati hari sepi menyambung dua titik berjauhan
     * menjadi satu garis landai: berhentinya belajar selama sepekan
     * terbaca sebagai penurunan bertahap, bukan sebagai berhenti.
     */
    public function test_hari_kosong_tetap_disebut_sebagai_nol(): void
    {
        $u = $this->peserta();
        $c = $this->kursus();
        $m = Module::create(['course_id' => $c->id, 'title' => 'M1', 'order_index' => 1]);

        $kini = Carbon::parse('2026-03-20 09:00:00');

        ModuleCompletion::create(['user_id' => $u->id, 'module_id' => $m->id])
            ->forceFill(['created_at' => $kini->copy()->subDays(3)])->save();

        $d = BelajarGrafik::kegiatan($u->id, $kini, 7);

        $this->assertCount(7, $d['label']);
        $this->assertCount(7, $d['modul']);
        $this->assertSame([0, 0, 0, 1, 0, 0, 0], $d['modul']);
    }

    /** Rentang yang diminta menentukan panjang deretnya, bukan datanya. */
    public function test_panjang_deret_mengikuti_rentang(): void
    {
        $u = $this->peserta();

        foreach (BelajarGrafik::RENTANG as $hari) {
            $this->assertCount($hari,
                BelajarGrafik::kegiatan($u->id, Carbon::parse('2026-03-20'), $hari)['label'],
                "Rentang $hari hari tidak menghasilkan $hari titik.");
        }
    }

    /**
     * Deret kegiatan adalah milik ORANG ITU SAJA.
     *
     * Dasbor ini pribadi. Kebocoran di sini tidak menimbulkan galat dan
     * tidak terlihat pada layar mana pun — grafiknya sekadar
     * memperlihatkan orang lain sedang rajin.
     */
    public function test_kegiatan_orang_lain_tidak_ikut_terhitung(): void
    {
        $aku  = $this->peserta();
        $dia  = $this->peserta();
        $c    = $this->kursus();
        $m    = Module::create(['course_id' => $c->id, 'title' => 'M1', 'order_index' => 1]);
        $kini = Carbon::parse('2026-03-20 09:00:00');

        ModuleCompletion::create(['user_id' => $dia->id, 'module_id' => $m->id]);
        QuizAttempt::create(['user_id' => $dia->id,
            'quiz_id' => Quiz::create(['course_id' => $c->id, 'title' => 'K', 'pass_score' => 70])->id,
            'score' => 90, 'passed' => true]);

        $d = BelajarGrafik::kegiatan($aku->id, $kini, 30);

        $this->assertSame(0, array_sum($d['modul']));
        $this->assertSame(0, array_sum($d['uji']));
    }

    /**
     * "Belum diikuti" adalah irisan tersendiri, bukan kekosongan.
     *
     * Tanpa irisan itu, donatnya menggambarkan peserta yang telah
     * menyelesaikan segalanya padahal ia baru mendaftar satu dari enam.
     */
    public function test_status_menghitung_kursus_yang_belum_diikuti(): void
    {
        $u = $this->peserta();
        $a = $this->kursus('A'); $this->kursus('B'); $this->kursus('C');

        Enrollment::create(['user_id' => $u->id, 'course_id' => $a->id,
            'progress' => 100, 'status' => 'finished']);

        $s = collect(BelajarGrafik::statusKursus(
            Enrollment::where('user_id', $u->id)->get(), Course::count()))->keyBy('label');

        $this->assertSame(1, $s['Selesai']['nilai']);
        $this->assertSame(0, $s['Berjalan']['nilai']);
        $this->assertSame(2, $s['Belum diikuti']['nilai']);
    }

    /**
     * Ejaan status yang dipakai APLIKASI, bukan yang pernah ditulis
     * data contoh.
     *
     * LearnController menulis 'finished'; data contoh sempat menulis
     * 'completed', dan tidak satu baris kode pun membacanya. Barisnya
     * tetap tersimpan, kursusnya tetap tampil, dan hanya angkanya yang
     * salah: "Kursus Selesai 0" pada peserta yang sudah tuntas.
     */
    public function test_status_membaca_ejaan_finished(): void
    {
        $u = $this->peserta();
        $c = $this->kursus();

        Enrollment::create(['user_id' => $u->id, 'course_id' => $c->id,
            'progress' => 100, 'status' => 'finished']);

        $s = collect(BelajarGrafik::statusKursus(
            Enrollment::where('user_id', $u->id)->get(), 1))->keyBy('label');

        $this->assertSame(1, $s['Selesai']['nilai'],
            "Ejaan status pendaftaran berubah; seluruh penyaring 'finished' di "
            .'CertificateController, EvaluationController, dan DashboardController ikut buta.');
    }

    /** Kemajuan diurut dari yang paling tertinggal — itu yang harus dikerjakan dulu. */
    public function test_kemajuan_diurut_dari_yang_paling_tertinggal(): void
    {
        $u = $this->peserta();

        foreach ([['A', 90], ['B', 10], ['C', 50]] as [$judul, $maju]) {
            Enrollment::create(['user_id' => $u->id, 'course_id' => $this->kursus($judul)->id,
                'progress' => $maju, 'status' => 'ongoing']);
        }

        $baris = BelajarGrafik::kemajuanKursus(
            Enrollment::with('course')->where('user_id', $u->id)->get());

        $this->assertSame(['B', 'C', 'A'], array_column($baris, 'label'));
        $this->assertSame([10, 50, 90], array_column($baris, 'nilai'));
        $this->assertNotEmpty($baris[0]['url'], 'Barisnya harus dapat ditelusuri ke kursusnya.');
    }

    /** Pita nilai yang kosong tetap disebut, supaya sebarannya terbaca utuh. */
    public function test_pita_nilai_kosong_tetap_disebut(): void
    {
        $u = $this->peserta();
        $c = $this->kursus();
        $q = Quiz::create(['course_id' => $c->id, 'title' => 'K', 'pass_score' => 70]);

        QuizAttempt::create(['user_id' => $u->id, 'quiz_id' => $q->id, 'score' => 95, 'passed' => true]);

        $pita = collect(BelajarGrafik::sebaranNilai($u->id))->keyBy('label');

        $this->assertCount(5, $pita);
        $this->assertSame(1, $pita['90–100']['nilai']);
        $this->assertSame(0, $pita['Di bawah 60']['nilai']);
    }

    /**
     * Kursus tanpa satu pun pendaftar TIDAK dihitung 0% selesai.
     *
     * "0% selesai" di sana menuduh kursusnya, padahal yang terjadi
     * tidak ada yang mendaftar.
     */
    public function test_penyelesaian_melewati_kursus_tanpa_pendaftar(): void
    {
        $a = $this->kursus('Berpendaftar');
        $this->kursus('Sepi');

        $u = $this->peserta();
        Enrollment::create(['user_id' => $u->id, 'course_id' => $a->id,
            'progress' => 100, 'status' => 'finished']);

        $baris = BelajarGrafik::penyelesaianKursus();

        $this->assertSame(['Berpendaftar'], array_column($baris, 'label'));
        $this->assertSame(100, $baris[0]['nilai']);
    }

    /* ═══════ halaman ═══════ */

    public function test_dasbor_mengirim_grafiknya(): void
    {
        $this->actingAs($this->peserta());

        $this->get(route('dashboard'))->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->component('Dashboard')
                ->where('hari', BelajarGrafik::RENTANG_BAWAAN)
                ->where('opsiHari', BelajarGrafik::RENTANG)
                ->has('grafik.kegiatan.label')
                ->has('grafik.kemajuan')
                ->has('grafik.status')
                ->has('grafik.nilai')
                ->has('grafik.sertifikat'));
    }

    /** Rentang di luar daftar dipulangkan ke bawaannya, bukan dipakai apa adanya. */
    public function test_rentang_asing_jatuh_ke_bawaan(): void
    {
        $this->actingAs($this->peserta());

        $this->get(route('dashboard', ['hari' => 100000]))->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->where('hari', BelajarGrafik::RENTANG_BAWAAN)
                ->count('grafik.kegiatan.label', BelajarGrafik::RENTANG_BAWAAN));
    }

    /**
     * Angka SELURUH PESERTA hanya untuk yang bertugas melihat orang lain.
     *
     * Peserta biasa tidak menerima kuncinya sama sekali — bukan menerima
     * larik kosong: larik kosong tetap menggambar kartunya, dan kartu
     * kosong yang hanya muncul bagi sebagian orang terbaca sebagai
     * halaman yang rusak.
     */
    public function test_penyelesaian_seluruh_peserta_hanya_bagi_pelatih_dan_admin(): void
    {
        $this->actingAs($this->peserta(['is_admin' => false, 'lms_role' => 'peserta']));

        $this->get(route('dashboard'))->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->where('grafik.penyelesaian', null));

        $this->actingAs($this->peserta(['is_admin' => true]));

        $this->get(route('dashboard'))->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->has('grafik.penyelesaian'));
    }

    /**
     * Sertifikat dikelompokkan per bulan tanpa bulan yang terlewat
     * maupun terhitung dua kali.
     *
     * `subMonths($i)` dari tanggal hari ini meluap pada tanggal 29–31,
     * sehingga satu bulan terhitung dua kali dan bulan lain tidak pernah
     * muncul — grafiknya tetap enam batang, dua berlabel sama.
     */
    public function test_sertifikat_bulanan_tidak_meluap_di_akhir_bulan(): void
    {
        $u = $this->peserta();
        $c = $this->kursus();
        $kini = Carbon::parse('2026-03-31 10:00:00');

        Certificate::create([
            'user_id' => $u->id, 'course_id' => $c->id,
            'certificate_number' => 'SRT-001', 'recipient_name' => $u->name,
            'course_title' => $c->title, 'issued_at' => Carbon::parse('2026-02-15'),
            'verification_code' => 'ABC123', 'template' => 'default',
        ]);

        $baris = BelajarGrafik::sertifikatBulanan($u->id, $kini);

        $this->assertCount(6, $baris);
        $this->assertSame(6, count(array_unique(array_column($baris, 'label'))),
            'Ada bulan yang tercetak dua kali — deret bulannya meluap.');
        $this->assertSame(1, array_sum(array_column($baris, 'nilai')));
    }
}
