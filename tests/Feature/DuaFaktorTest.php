<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\DuaFaktorTantanganController as Tantangan;
use App\Models\{Company, User};
use App\Support\{DuaFaktor, Keamanan, Totp};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifikasi dua langkah, dari menyalakan sampai masuk dengannya.
 *
 * Yang dijaga di sini dua hal yang saling menarik ke arah berlawanan:
 * lapisan ini harus benar-benar menahan orang yang memegang sandi yang
 * benar, DAN tidak boleh mengunci pemiliknya sendiri di luar. Yang
 * kedua lebih sering terlupa, dan akibatnya lebih mahal: fitur keamanan
 * yang mengunci pemiliknya berhenti dipakai siapa pun.
 */
class DuaFaktorTest extends TestCase
{
    use RefreshDatabase;

    private const SANDI = 'sandi uji yang panjang';

    private function pengguna(): User
    {
        $c = Company::create(['name' => 'PT Uji Dua Faktor', 'code' => 'UDF']);

        return User::factory()->create([
            'email'             => 'penjaga@contoh.test',
            'password'          => self::SANDI,
            'company_id'        => $c->id,
            'email_verified_at' => now(),
        ]);
    }

    /** Menyalakan lapisan ini sampai benar-benar aktif. */
    private function nyalakan(User $u): array
    {
        $rahasia   = DuaFaktor::mulai($u);
        $pemulihan = DuaFaktor::sahkan($u, Totp::kode($rahasia));

        $this->assertNotNull($pemulihan);

        /* Satu langkah waktu dimajukan.
         *
         * Kode yang barusan dipakai untuk MENYAHKAN penyiapan sudah
         * tercatat sebagai terpakai — itu memang maksudnya. Tanpa
         * memajukan jam, uji di bawahnya akan memakai kode yang sama
         * lagi dan ditolak sebagai pengulangan, lalu tampak seperti
         * "kode yang benar tidak diterima" padahal yang bekerja justru
         * penjagaan yang dikehendaki. */
        $this->travel(Totp::LANGKAH)->seconds();

        return [$rahasia, $pemulihan];
    }

    /* ═══════════ menyalakan ═══════════ */

    /**
     * Rahasia yang tersimpan TIDAK menyalakan apa pun.
     *
     * Penjagaan terpenting di berkas ini. Kalau menyimpan rahasia sudah
     * cukup, orang yang memindai QR lalu menutup halaman karena ada
     * panggilan radio akan terkunci di luar pada percobaan masuk
     * berikutnya — oleh fitur yang ia sendiri tidak yakin sudah ia
     * pasang, dan yang tidak dapat ia matikan karena untuk mematikannya
     * ia harus masuk.
     */
    public function test_memindai_saja_belum_menyalakan(): void
    {
        $u = $this->pengguna();

        DuaFaktor::mulai($u);

        $this->assertFalse(DuaFaktor::menyala($u->refresh()),
            'Dua faktor menyala hanya karena rahasianya tersimpan.');

        $this->post('/login', ['email' => $u->email, 'password' => self::SANDI])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_kode_salah_tidak_menyalakan(): void
    {
        $u = $this->pengguna();
        DuaFaktor::mulai($u);

        $this->assertNull(DuaFaktor::sahkan($u->refresh(), '000000'));
        $this->assertFalse(DuaFaktor::menyala($u->refresh()));
    }

    public function test_menyalakan_menerbitkan_kode_pemulihan(): void
    {
        $u = $this->pengguna();
        [, $pemulihan] = $this->nyalakan($u);

        $this->assertCount(DuaFaktor::JUMLAH_PEMULIHAN, $pemulihan);
        $this->assertCount(DuaFaktor::JUMLAH_PEMULIHAN, array_unique($pemulihan));
        $this->assertTrue(DuaFaktor::menyala($u->refresh()));
    }

    /**
     * Rahasia baru pada akun yang sudah menyala ditolak.
     *
     * Ia akan mematikan aplikasi autentikator yang sudah terpasang tanpa
     * satu pun peringatan, dan pemiliknya baru tahu pada percobaan masuk
     * berikutnya.
     */
    public function test_tidak_bisa_menyiapkan_rahasia_baru_saat_sudah_menyala(): void
    {
        $u = $this->pengguna();
        $this->nyalakan($u);

        $this->expectException(\LogicException::class);

        DuaFaktor::mulai($u->refresh());
    }

    /* ═══════════ masuk dengan dua faktor ═══════════ */

    public function test_sandi_benar_belum_memasukkan_siapa_pun(): void
    {
        $u = $this->pengguna();
        $this->nyalakan($u);

        $this->post('/login', ['email' => $u->email, 'password' => self::SANDI])
            ->assertRedirect(route('dua-faktor.tantangan'));

        $this->assertGuest();
    }

    /**
     * Sandi yang benar TIDAK tercatat sebagai "masuk".
     *
     * Jejak akses dibaca orang justru ketika ia curiga akunnya dipakai
     * orang lain. Baris "Masuk" untuk sesi yang tidak pernah terjadi
     * membuat halaman itu memberi tahu hal yang tidak benar, tepat pada
     * saat ia paling dipercaya.
     */
    public function test_setengah_masuk_tidak_tercatat_sebagai_masuk(): void
    {
        $u = $this->pengguna();
        $this->nyalakan($u);

        $this->post('/login', ['email' => $u->email, 'password' => self::SANDI]);

        $this->assertDatabaseMissing('activity_log', [
            'user_id' => $u->getKey(),
            'action'    => Keamanan::MASUK,
        ]);

        $this->assertNull($u->refresh()->masuk_terakhir_at,
            'masuk_terakhir_at diperbarui padahal orangnya belum masuk.');
    }

    public function test_kode_benar_memasukkan(): void
    {
        $u = $this->pengguna();
        [$rahasia] = $this->nyalakan($u);

        $this->post('/login', ['email' => $u->email, 'password' => self::SANDI]);

        $this->post('/dua-faktor', ['kode' => Totp::kode($rahasia)])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($u);
    }

    public function test_kode_salah_tidak_memasukkan(): void
    {
        $u = $this->pengguna();
        $this->nyalakan($u);

        $this->post('/login', ['email' => $u->email, 'password' => self::SANDI]);

        $this->post('/dua-faktor', ['kode' => '000000'])
            ->assertSessionHasErrors('kode');

        $this->assertGuest();
    }

    /**
     * Kode yang sudah terpakai tidak berlaku lagi.
     *
     * Satu kode hidup sampai satu setengah menit. Tanpa penjagaan ini,
     * kode yang terbaca dari balik bahu — atau dari layar yang terlanjur
     * dibagikan saat rapat daring — dapat dipakai lagi selama sisa
     * umurnya.
     */
    public function test_kode_yang_sudah_dipakai_tidak_bisa_dipakai_lagi(): void
    {
        $u = $this->pengguna();
        [$rahasia] = $this->nyalakan($u);
        $kode = Totp::kode($rahasia);

        $this->post('/login', ['email' => $u->email, 'password' => self::SANDI]);
        $this->post('/dua-faktor', ['kode' => $kode]);
        $this->assertAuthenticatedAs($u);

        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', ['email' => $u->email, 'password' => self::SANDI]);
        $this->post('/dua-faktor', ['kode' => $kode])->assertSessionHasErrors('kode');

        $this->assertGuest();
    }

    /**
     * Halaman kode tidak memberi akses ke apa pun.
     *
     * Setengah-masuk hanya catatan bahwa sandi sebuah akun pernah benar
     * di peramban ini. Ia tidak boleh membuka satu halaman pun.
     */
    public function test_setengah_masuk_tidak_membuka_halaman_apa_pun(): void
    {
        $u = $this->pengguna();
        $this->nyalakan($u);

        $this->post('/login', ['email' => $u->email, 'password' => self::SANDI]);

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->get('/akun/dua-faktor')->assertRedirect(route('login'));
    }

    public function test_tantangan_tanpa_setengah_masuk_dikembalikan_ke_login(): void
    {
        $this->get(route('dua-faktor.tantangan'))->assertRedirect(route('login'));
    }

    /**
     * Setengah-masuk kedaluwarsa.
     *
     * Peramban di komputer bersama menyimpannya sampai ada yang
     * membukanya lagi; tanpa batas waktu, cukup satu kode untuk masuk
     * sebagai orang yang sudah lama pulang.
     */
    public function test_setengah_masuk_kedaluwarsa(): void
    {
        $u = $this->pengguna();
        [$rahasia] = $this->nyalakan($u);

        $this->post('/login', ['email' => $u->email, 'password' => self::SANDI]);

        $this->travel(Tantangan::UMUR + 10)->seconds();

        $this->post('/dua-faktor', ['kode' => Totp::kode($rahasia)])
            ->assertSessionHasErrors('kode');

        $this->assertGuest();
    }

    /**
     * Enam angka tanpa pembatas laju bukan pengaman.
     *
     * Sejuta kemungkinan terdengar banyak sampai dihitung: satu kode
     * hidup sampai satu setengah menit, dan penebak yang tidak dibatasi
     * menghabiskan sejuta tebakan jauh lebih cepat daripada itu. Yang
     * ditahan harus setengah-masuk-nya sendiri — penebaknya sudah
     * memegang sandinya.
     */
    public function test_tebakan_kode_dibatasi(): void
    {
        $u = $this->pengguna();
        $this->nyalakan($u);

        $this->post('/login', ['email' => $u->email, 'password' => self::SANDI]);

        for ($i = 0; $i < Tantangan::TEBAKAN_PER_MENIT; $i++) {
            $this->post('/dua-faktor', ['kode' => '00000'.$i])
                ->assertSessionHasErrors('kode');
        }

        $this->post('/dua-faktor', ['kode' => '000009'])
            ->assertSessionHasErrors('kode');

        $this->assertStringContainsString('Terlalu banyak percobaan',
            session('errors')->first('kode'),
            'Tebakan keenam tidak ditahan; pembatasnya tidak menahan apa pun.');

        $this->assertGuest();
    }

    /* ═══════════ kode pemulihan ═══════════ */

    public function test_kode_pemulihan_dapat_dipakai_masuk(): void
    {
        $u = $this->pengguna();
        [, $pemulihan] = $this->nyalakan($u);

        $this->post('/login', ['email' => $u->email, 'password' => self::SANDI]);

        $this->post('/dua-faktor', ['kode' => $pemulihan[0]])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($u);
    }

    /**
     * Kode pemulihan sekali pakai — dibuang, bukan ditandai.
     *
     * Yang masih dapat dipakai dua kali bukan jalan pulang melainkan
     * sandi kedua yang tidak pernah kedaluwarsa.
     */
    public function test_kode_pemulihan_hangus_sesudah_dipakai(): void
    {
        $u = $this->pengguna();
        [, $pemulihan] = $this->nyalakan($u);

        $this->post('/login', ['email' => $u->email, 'password' => self::SANDI]);
        $this->post('/dua-faktor', ['kode' => $pemulihan[0]]);
        $this->post('/logout');

        $this->assertCount(DuaFaktor::JUMLAH_PEMULIHAN - 1,
            $u->refresh()->dua_faktor_pemulihan);

        $this->post('/login', ['email' => $u->email, 'password' => self::SANDI]);
        $this->post('/dua-faktor', ['kode' => $pemulihan[0]])->assertSessionHasErrors('kode');

        $this->assertGuest();
    }

    public function test_kode_pemulihan_diterima_apa_pun_gaya_ketiknya(): void
    {
        $u = $this->pengguna();
        [, $pemulihan] = $this->nyalakan($u);

        $gaya = strtoupper(str_replace('-', ' ', $pemulihan[0]));

        $this->post('/login', ['email' => $u->email, 'password' => self::SANDI]);
        $this->post('/dua-faktor', ['kode' => $gaya]);

        $this->assertAuthenticatedAs($u);
    }

    /* ═══════════ mematikan ═══════════ */

    public function test_mematikan_menuntut_sandi(): void
    {
        $u = $this->pengguna();
        $this->nyalakan($u);

        $this->actingAs($u)->delete('/akun/dua-faktor', ['password' => 'sandi yang salah sekali'])
            ->assertSessionHasErrors('password');

        $this->assertTrue(DuaFaktor::menyala($u->refresh()));

        $this->actingAs($u)->delete('/akun/dua-faktor', ['password' => self::SANDI]);

        $this->assertFalse(DuaFaktor::menyala($u->refresh()));
    }

    /**
     * Mematikan membuang rahasianya, bukan menyimpannya.
     *
     * Rahasia yang tertinggal pada akun yang sudah mematikan fiturnya
     * adalah rahasia yang tidak dijaga siapa pun dan tidak diketahui
     * pemiliknya masih ada.
     */
    public function test_mematikan_membuang_rahasianya(): void
    {
        $u = $this->pengguna();
        $this->nyalakan($u);

        $this->actingAs($u)->delete('/akun/dua-faktor', ['password' => self::SANDI]);

        $u->refresh();

        $this->assertNull($u->dua_faktor_rahasia);
        $this->assertNull($u->dua_faktor_pemulihan);
        $this->assertNull($u->dua_faktor_langkah);
    }

    public function test_menyalakan_dan_mematikan_tercatat(): void
    {
        $u = $this->pengguna();
        $this->nyalakan($u);

        $this->actingAs($u)->delete('/akun/dua-faktor', ['password' => self::SANDI]);

        $this->assertDatabaseHas('activity_log', [
            'user_id' => $u->getKey(),
            'action'    => Keamanan::DF_MATI,
        ]);
    }

    /* ═══════════ yang sampai ke peramban ═══════════ */

    /**
     * Rahasianya tidak pernah bocor lewat prop halaman.
     *
     * Satu $request->user() yang lolos ke prop Inertia membocorkan
     * rahasia yang membuat seluruh lapisan ini tidak berarti apa-apa —
     * tanpa gejala apa pun, karena situsnya tetap bekerja persis sama.
     */
    public function test_rahasia_tidak_bocor_ke_halaman_lain(): void
    {
        $u = $this->pengguna();
        [$rahasia] = $this->nyalakan($u);

        foreach (['/dashboard', '/akun/perangkat', '/profile'] as $alamat) {
            $isi = $this->actingAs($u)->get($alamat)->getContent();

            $this->assertStringNotContainsString($rahasia, $isi,
                "Rahasia dua faktor tergambar di {$alamat}.");
        }
    }

    public function test_halaman_penyiapan_tidak_lagi_mengirim_rahasia_sesudah_menyala(): void
    {
        $u = $this->pengguna();
        [$rahasia] = $this->nyalakan($u);

        $isi = $this->actingAs($u)->get('/akun/dua-faktor')->getContent();

        $this->assertStringNotContainsString($rahasia, $isi,
            'Rahasia masih dikirim ke peramban sesudah lapisannya menyala; '
            .'setiap kemunculan adalah satu kesempatan lagi untuk terbaca dari layar.');
    }

    public function test_kode_pemulihan_tidak_terkirim_pada_muat_ulang_biasa(): void
    {
        $u = $this->pengguna();
        [, $pemulihan] = $this->nyalakan($u);

        $isi = $this->actingAs($u)->get('/akun/dua-faktor')->getContent();

        $this->assertStringNotContainsString($pemulihan[0], $isi);
    }
}
