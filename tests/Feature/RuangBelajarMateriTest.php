<?php

namespace Tests\Feature;

use App\Models\{Course, Enrollment, Material, MaterialCompletion, MaterialDiscussion,
                Module, ModuleCompletion, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman materi: ikhtisar, kurikulum, progres, dan tanya jawabnya.
 *
 * Yang dijaga di sini terutama tiga hal yang tidak menimbulkan galat
 * bila lepas:
 *
 *   1. Materi milik kursus lain tidak terbuka lewat alamat kursus ini.
 *      Pengikatan model Laravel memuat keduanya terpisah dan tidak
 *      pernah memeriksa hubungannya sendiri.
 *   2. Kursus berkode tetap tertutup lewat alamat MATERINYA. Penjagaan
 *      yang hanya dipasang di halaman kursus meninggalkan pintu samping
 *      yang terbuka lebar.
 *   3. Progres kursus tetap dihitung dari MODUL. Menghitungnya ulang
 *      dari materi mengubah angka pada sertifikat yang sudah terbit —
 *      tanpa satu pun galat, dan tanpa cara menjelaskannya kepada yang
 *      memegangnya.
 */
class RuangBelajarMateriTest extends TestCase
{
    use RefreshDatabase;

    private User $orang;
    private Course $kursus;
    private Module $modul;
    private Material $satu;
    private Material $dua;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orang = User::factory()->create(['email_verified_at' => now()]);

        $this->kursus = Course::create(['title' => 'Bekerja di Ketinggian']);

        $this->modul = $this->kursus->modules()->create([
            'title' => 'Dasar', 'order_index' => 1,
        ]);

        $this->satu = $this->materi('Pengenalan APD', 1);
        $this->dua  = $this->materi('Praktik pemasangan', 2);

        Enrollment::create([
            'user_id' => $this->orang->id, 'course_id' => $this->kursus->id,
            'progress' => 0, 'status' => 'ongoing',
        ]);
    }

    private function materi(string $judul, int $urut, array $lain = []): Material
    {
        return Material::create($lain + [
            'course_id' => $this->kursus->id,
            'module_id' => $this->modul->id,
            'title'     => $judul,
            'type'      => 'document',
            'order_index' => $urut,
        ]);
    }

    private function alamat(Material $m, ?Course $c = null): string
    {
        return route('learn.materi', [
            'course' => ($c ?? $this->kursus)->id, 'material' => $m->id,
        ]);
    }

    private function props(Material $m): array
    {
        return $this->actingAs($this->orang)->get($this->alamat($m))
            ->assertOk()->viewData('page')['props'];
    }

    /* ---------- ikhtisar ---------- */

    public function test_halaman_materi_membawa_ikhtisarnya(): void
    {
        $m = $this->materi('Inspeksi harness', 3, [
            'description'      => 'Cara memeriksa harness sebelum dipakai.',
            'outcomes'         => ['Mengenali jahitan yang sobek', 'Membaca label masa pakai'],
            'prerequisite'     => 'Baca SOP-07 lebih dulu.',
            'duration_minutes' => 25,
        ]);

        $p = $this->props($m)['materi'];

        $this->assertSame('Cara memeriksa harness sebelum dipakai.', $p['keterangan']);
        $this->assertSame(['Mengenali jahitan yang sobek', 'Membaca label masa pakai'], $p['hasil']);
        $this->assertSame('Baca SOP-07 lebih dulu.', $p['prasyarat']);
        $this->assertSame('25 menit', $p['durasi']);
    }

    public function test_materi_tanpa_ikhtisar_tidak_menjatuhkan_halamannya(): void
    {
        /* Seluruh materi yang sudah ada dibuat sebelum kolom ini ada.
           Halaman yang menuntut ketiganya terisi akan 500 pada setiap
           kursus yang sudah berjalan. */
        $p = $this->props($this->satu)['materi'];

        $this->assertSame([], $p['hasil']);
        $this->assertNull($p['prasyarat']);
        $this->assertNull($p['durasi']);
    }

    public function test_nomor_materi_dihitung_melintasi_modul(): void
    {
        /* "Materi 3 dari 4" harus menghitung seluruh kursus, bukan satu
           modul. Peserta membaca kursus sebagai satu rangkaian; nomor
           yang berulang dari satu di tiap modul membuat tombol
           "berikutnya" tampak melompat mundur. */
        $modulDua = $this->kursus->modules()->create(['title' => 'Lanjutan', 'order_index' => 2]);

        $tiga = Material::create([
            'course_id' => $this->kursus->id, 'module_id' => $modulDua->id,
            'title' => 'Penyelamatan', 'order_index' => 1,
        ]);

        $p = $this->props($tiga)['materi'];

        $this->assertSame(3, $p['nomor']);
        $this->assertSame(3, $p['dari']);
    }

    public function test_tombol_sebelum_dan_sesudah_melintasi_batas_modul(): void
    {
        $modulDua = $this->kursus->modules()->create(['title' => 'Lanjutan', 'order_index' => 2]);

        $tiga = Material::create([
            'course_id' => $this->kursus->id, 'module_id' => $modulDua->id,
            'title' => 'Penyelamatan', 'order_index' => 1,
        ]);

        $p = $this->props($this->dua)['jelajah'];

        $this->assertSame('Pengenalan APD', $p['sebelum']['judul']);
        $this->assertSame('Penyelamatan', $p['sesudah']['judul'],
            'Materi terakhir sebuah modul harus menunjuk materi pertama modul berikutnya.');

        $this->assertNull($this->props($tiga)['jelajah']['sesudah']);
    }

    /* ---------- kurikulum ---------- */

    public function test_kurikulum_menandai_materi_yang_sedang_dibuka(): void
    {
        $p = $this->props($this->dua)['kurikulum'];

        $kini = collect($p)->flatMap(fn ($m) => $m['materi'])->where('kini', true);

        $this->assertCount(1, $kini, 'Tepat satu materi boleh bertanda sedang dibuka.');
        $this->assertSame($this->dua->id, $kini->first()['id']);
    }

    public function test_kurikulum_membawa_penanda_selesai_per_materi(): void
    {
        MaterialCompletion::create(['user_id' => $this->orang->id, 'material_id' => $this->satu->id]);

        $materi = collect($this->props($this->dua)['kurikulum'])->flatMap(fn ($m) => $m['materi']);

        $this->assertTrue($materi->firstWhere('id', $this->satu->id)['selesai']);
        $this->assertFalse($materi->firstWhere('id', $this->dua->id)['selesai']);
    }

    /* ---------- progres ---------- */

    public function test_menandai_seluruh_materi_menyelesaikan_modulnya(): void
    {
        $this->actingAs($this->orang)->post(route('materials.complete', $this->satu));

        $this->assertDatabaseMissing('module_completions', [
            'user_id' => $this->orang->id, 'module_id' => $this->modul->id,
        ]);

        $this->actingAs($this->orang)->post(route('materials.complete', $this->dua));

        $this->assertDatabaseHas('module_completions', [
            'user_id' => $this->orang->id, 'module_id' => $this->modul->id,
        ]);
    }

    public function test_progres_kursus_tetap_dihitung_dari_modul(): void
    {
        /* Kursus dua modul: satu bermateri, satu kosong. Menuntaskan
           seluruh materi modul pertama harus menghasilkan 50%, bukan
           100% — sekalipun 'seluruh materi kursus' memang sudah tuntas.
           Sertifikat yang sudah terbit menyebut angka rumus lama. */
        $this->kursus->modules()->create(['title' => 'Modul kosong', 'order_index' => 2]);

        $this->actingAs($this->orang)->post(route('materials.complete', $this->satu));
        $this->actingAs($this->orang)->post(route('materials.complete', $this->dua));

        $this->assertSame(50, (int) Enrollment::where('user_id', $this->orang->id)
            ->where('course_id', $this->kursus->id)->value('progress'));
    }

    public function test_menuntaskan_materi_tidak_menyentuh_modul_lain(): void
    {
        /* Namanya sengaja menyebut apa yang BENAR-BENAR diperiksa.
         *
         * Semula uji ini bernama "modul tanpa materi tidak menyelesaikan
         * dirinya sendiri" dan tidak pernah menguji hal itu: penjagaan
         * isEmpty() di rapikanModul() hanya terjangkau lewat modul milik
         * materi yang baru ditandai, dan modul itu menurut definisinya
         * punya sekurangnya satu materi. Menghapus penjagaan itu tidak
         * menggagalkan satu pun uji — dan nama yang berbohong lebih
         * buruk daripada penjagaan yang tidak teruji. */
        $lain = $this->kursus->modules()->create(['title' => 'Belum diisi', 'order_index' => 2]);

        $this->actingAs($this->orang)->post(route('materials.complete', $this->satu));

        $this->assertDatabaseMissing('module_completions', [
            'user_id' => $this->orang->id, 'module_id' => $lain->id,
        ]);
    }

    public function test_menandai_materi_dua_kali_tidak_menggandakan_barisnya(): void
    {
        foreach (range(1, 3) as $ke) {
            $this->actingAs($this->orang)->post(route('materials.complete', $this->satu))
                ->assertRedirect();
        }

        $this->assertSame(1, MaterialCompletion::where('material_id', $this->satu->id)->count());
    }

    public function test_tombol_modul_selesai_yang_lama_tetap_bekerja(): void
    {
        // Modul tanpa materi tidak punya jalan lain untuk diselesaikan.
        $kosong = $this->kursus->modules()->create(['title' => 'Bacaan luar', 'order_index' => 2]);

        $this->actingAs($this->orang)->post(route('modules.complete', $kosong))->assertRedirect();

        $this->assertDatabaseHas('module_completions', [
            'user_id' => $this->orang->id, 'module_id' => $kosong->id,
        ]);
    }

    /* ---------- penjagaan ---------- */

    public function test_materi_kursus_lain_tidak_terbuka_lewat_alamat_kursus_ini(): void
    {
        $lain = Course::create(['title' => 'Kursus lain']);
        $modulLain = $lain->modules()->create(['title' => 'M', 'order_index' => 1]);

        $materiLain = Material::create([
            'course_id' => $lain->id, 'module_id' => $modulLain->id,
            'title' => 'Materi milik kursus lain', 'order_index' => 1,
        ]);

        $this->actingAs($this->orang)
            ->get(route('learn.materi', ['course' => $this->kursus->id, 'material' => $materiLain->id]))
            ->assertNotFound();
    }

    public function test_kursus_berkode_tetap_tertutup_lewat_alamat_materinya(): void
    {
        /* Pintu samping. Penjagaan yang hanya dipasang di halaman kursus
           meninggalkan alamat materi terbuka lebar — dan alamat itulah
           yang akan disalin ke grup pesan. */
        $terkunci = Course::create(['title' => 'Berkode', 'require_code' => true, 'access_code' => 'RAHASIA']);
        $m = $terkunci->modules()->create(['title' => 'M', 'order_index' => 1]);

        $materi = Material::create([
            'course_id' => $terkunci->id, 'module_id' => $m->id,
            'title' => 'Isi berbayar', 'order_index' => 1,
        ]);

        $p = $this->actingAs($this->orang)
            ->get(route('learn.materi', ['course' => $terkunci->id, 'material' => $materi->id]))
            ->assertOk()->viewData('page');

        $this->assertSame('Belajar/Kode', $p['component'],
            'Kursus berkode harus meminta kodenya, bukan menampilkan materinya.');

        $this->assertDatabaseMissing('enrollments', [
            'user_id' => $this->orang->id, 'course_id' => $terkunci->id,
        ]);
    }

    /* ---------- tanya jawab ---------- */

    public function test_pertanyaan_dan_jawabannya_tersusun_sebagai_utas(): void
    {
        $this->actingAs($this->orang)->post(route('materials.tanya', $this->satu), [
            'body' => 'Apakah harness lama masih boleh dipakai?',
        ])->assertRedirect();

        $tanya = MaterialDiscussion::whereNull('parent_id')->firstOrFail();

        $trainer = User::factory()->create(['email_verified_at' => now(), 'lms_role' => 'trainer']);

        $this->actingAs($trainer)->post(route('materials.tanya', $this->satu), [
            'body' => 'Tidak, bila label masa pakainya sudah lewat.',
            'parent_id' => $tanya->id,
        ])->assertRedirect();

        $p = $this->props($this->satu)['tanya'];

        $this->assertCount(1, $p, 'Jawaban tidak boleh muncul sebagai pertanyaan tersendiri.');
        $this->assertCount(1, $p[0]['jawaban']);
        $this->assertSame('Tidak, bila label masa pakainya sudah lewat.', $p[0]['jawaban'][0]['isi']);
    }

    public function test_jawaban_tidak_dapat_ditempelkan_ke_utas_materi_lain(): void
    {
        $this->actingAs($this->orang)->post(route('materials.tanya', $this->satu), [
            'body' => 'Pertanyaan pada materi satu',
        ]);

        $tanya = MaterialDiscussion::firstOrFail();

        // Nomor utas yang benar, tetapi materinya berbeda.
        $this->actingAs($this->orang)->post(route('materials.tanya', $this->dua), [
            'body' => 'Menyelinap', 'parent_id' => $tanya->id,
        ])->assertNotFound();
    }

    public function test_balasan_atas_balasan_ditolak(): void
    {
        /* Satu tingkat saja. Utas berlapis pada materi pelatihan berubah
           menjadi percakapan yang tidak terbaca, dan yang dicari orang
           di sini adalah jawaban. */
        $this->actingAs($this->orang)->post(route('materials.tanya', $this->satu), ['body' => 'Tanya']);
        $tanya = MaterialDiscussion::firstOrFail();

        $this->actingAs($this->orang)->post(route('materials.tanya', $this->satu), [
            'body' => 'Jawab', 'parent_id' => $tanya->id,
        ]);
        $jawab = MaterialDiscussion::whereNotNull('parent_id')->firstOrFail();

        $this->actingAs($this->orang)->post(route('materials.tanya', $this->satu), [
            'body' => 'Balas jawaban', 'parent_id' => $jawab->id,
        ])->assertNotFound();
    }

    public function test_pertanyaan_orang_lain_tidak_dapat_dihapus_sembarang_peserta(): void
    {
        $this->actingAs($this->orang)->post(route('materials.tanya', $this->satu), ['body' => 'Tanya']);
        $tanya = MaterialDiscussion::firstOrFail();

        $orangLain = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($orangLain)->delete(route('materials.tanya.hapus', $tanya))
            ->assertForbidden();

        $this->assertDatabaseHas('material_discussions', ['id' => $tanya->id]);
    }

    public function test_penulisnya_dan_trainer_boleh_menghapus(): void
    {
        $this->actingAs($this->orang)->post(route('materials.tanya', $this->satu), ['body' => 'Tanya saya']);
        $milikku = MaterialDiscussion::firstOrFail();

        $this->actingAs($this->orang)->delete(route('materials.tanya.hapus', $milikku))->assertRedirect();
        $this->assertDatabaseMissing('material_discussions', ['id' => $milikku->id]);

        $lain = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($lain)->post(route('materials.tanya', $this->satu), ['body' => 'Tanya orang lain']);
        $punyaLain = MaterialDiscussion::firstOrFail();

        $trainer = User::factory()->create(['email_verified_at' => now(), 'lms_role' => 'trainer']);
        $this->actingAs($trainer)->delete(route('materials.tanya.hapus', $punyaLain))->assertRedirect();
        $this->assertDatabaseMissing('material_discussions', ['id' => $punyaLain->id]);
    }

    public function test_menghapus_pertanyaan_ikut_membuang_jawabannya(): void
    {
        // Jawaban tanpa pertanyaan adalah kalimat menggantung yang tidak
        // dapat dipahami siapa pun yang membacanya kemudian.
        $this->actingAs($this->orang)->post(route('materials.tanya', $this->satu), ['body' => 'Tanya']);
        $tanya = MaterialDiscussion::firstOrFail();

        $this->actingAs($this->orang)->post(route('materials.tanya', $this->satu), [
            'body' => 'Jawab', 'parent_id' => $tanya->id,
        ]);

        $this->actingAs($this->orang)->delete(route('materials.tanya.hapus', $tanya));

        $this->assertSame(0, MaterialDiscussion::count());
    }

    /* ---------- catatan ---------- */

    public function test_halaman_materi_membawa_catatan_modulnya_dan_menyebut_modulnya(): void
    {
        /* Catatan melekat pada MODUL. Halaman yang tidak menyebutkannya
           membuat orang mengira catatannya hilang ketika ia membuka
           materi lain di modul yang sama dan menemukan tulisannya
           sendiri di sana. */
        $this->actingAs($this->orang)->post(route('notes.save', $this->modul), [
            'content' => 'Ingat: cek label masa pakai.',
        ]);

        $p = $this->props($this->dua)['catatan'];

        $this->assertSame('Ingat: cek label masa pakai.', $p['isi']);
        $this->assertSame('Dasar', $p['modul']);
    }

    /* ---------- sisi pengelola ---------- */

    public function test_pengelola_menyimpan_ikhtisar_sebagai_larik(): void
    {
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_admin' => true]);

        $this->actingAs($admin)->put(route('manage.material.update', $this->satu), [
            'title'            => 'Pengenalan APD',
            'type'             => 'video',
            'outcomes'         => "Mengenali jenis APD\n\n  Memilih ukuran yang benar  \n",
            'duration_minutes' => 20,
        ])->assertRedirect();

        $this->satu->refresh();

        // Baris kosong dan spasi menggantung dibuang DI SERVER, bukan di Vue.
        $this->assertSame(['Mengenali jenis APD', 'Memilih ukuran yang benar'], $this->satu->outcomes);
        $this->assertSame(20, $this->satu->duration_minutes);
    }

    public function test_ikhtisar_kosong_disimpan_sebagai_null_bukan_larik_kosong(): void
    {
        /* Keduanya terbaca sama di layar, tetapi [] pada kolom JSON
           membuat whereNull('outcomes') — cara paling wajar mencari
           materi yang belum diisi — melewatkan seluruhnya. */
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_admin' => true]);

        /* assertRedirect() WAJIB di sini, dan bukan kerapian.
           Tanpa itu permintaan yang 500 meninggalkan barisnya tidak
           tersentuh — outcomes-nya memang tetap null — dan kedua
           pemeriksaan di bawah lolos tanpa menguji apa pun. Persis itu
           yang terjadi: medan durasi yang tidak diisi menjatuhkan
           penyimpanan seluruh ikhtisar, dan uji ini tetap hijau. */
        $this->actingAs($admin)->put(route('manage.material.update', $this->satu), [
            'title' => 'Pengenalan APD', 'outcomes' => "\n  \n",
        ])->assertRedirect();

        $this->assertNull($this->satu->fresh()->outcomes);
        $this->assertSame(1, Material::whereNull('outcomes')->where('id', $this->satu->id)->count());
    }

    public function test_menyimpan_ikhtisar_tanpa_mengisi_durasi_tidak_menjatuhkan_permintaannya(): void
    {
        /* validate() hanya mengembalikan kunci yang MEMANG ada pada
           permintaannya, dan medan durasi yang dikosongkan tidak ikut
           terkirim sama sekali. Membacanya dengan `?:` menjatuhkan
           penyimpanan SELURUH ikhtisar — bukan hanya durasinya — pada
           kejadian yang paling wajar terjadi. */
        $admin = User::factory()->create(['email_verified_at' => now(), 'is_admin' => true]);

        $this->actingAs($admin)->put(route('manage.material.update', $this->satu), [
            'title'    => 'Judul baru',
            'outcomes' => 'Satu butir saja',
        ])->assertRedirect();

        $this->satu->refresh();

        $this->assertSame('Judul baru', $this->satu->title);
        $this->assertSame(['Satu butir saja'], $this->satu->outcomes);
        $this->assertNull($this->satu->duration_minutes);
    }

    public function test_bukan_admin_tidak_dapat_mengatur_ikhtisar(): void
    {
        $this->actingAs($this->orang)->get(route('manage.material.edit', $this->satu))
            ->assertForbidden();

        $this->actingAs($this->orang)->put(route('manage.material.update', $this->satu), [
            'title' => 'Diubah diam-diam',
        ])->assertForbidden();

        $this->assertSame('Pengenalan APD', $this->satu->fresh()->title);
    }
}
