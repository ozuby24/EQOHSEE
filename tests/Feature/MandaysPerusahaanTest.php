<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use App\Support\SmkpTahap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Hari kerja audit SMKP dimulai di profil perusahaan.
 *
 * Jumlah pekerja dan kelas risiko adalah sifat perusahaan, bukan sifat
 * sebuah audit. Keduanya sudah diisi pada formulir perusahaan, tetapi
 * akibatnya — berapa hari kerja yang dituntut audit SMKP — dulu baru
 * muncul berhalaman-halaman kemudian, di Tahap I audit, tempat angkanya
 * harus diketik ulang.
 *
 * Dua tempat mengetik satu angka yang sama adalah dua angka yang dapat
 * berselisih. Profil mencatat 1.098 pekerja, Tahap I menyebut 150, dan
 * hari kerja yang ditagih auditor meleset satu kelas penuh — tanpa satu
 * pun galat, sebab keduanya sah menurut sistem.
 */
class MandaysPerusahaanTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
        $this->actingAs($u);
        return $u;
    }

    public function test_formulir_perusahaan_membawa_dasar_hari_kerja_tersimpan(): void
    {
        $this->admin();

        $c = Company::create([
            'name' => 'PT Uji Mandays', 'workers_employee' => 60,
            'workers_sub' => 65, 'risk_class' => 'Tinggi',
        ]);

        $this->get(route('admin.companies.edit', $c))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Admin/Perusahaan/Form')
                // 125 pekerja, kelas Tinggi: baris 86–125 menagih 11 hari.
                ->where('mandays.pekerja', 125)
                ->where('mandays.kelas', 'Tinggi')
                ->where('mandays.dasar', 11)
                ->where('mandays.rentang', '86–125')
                ->has('tautan.mandays'));
    }

    public function test_penghitung_menjumlahkan_pekerja_perusahaan_dan_jasa(): void
    {
        $this->admin();

        $r = $this->postJson(route('admin.companies.mandays'), [
            'workers_employee' => 60,
            'workers_sub'      => 65,
            'risk_class'       => 'Tinggi',
        ])->assertOk();

        $this->assertSame(125, $r->json('pekerja'),
            'Pekerja jasa pertambangan ikut diaudit, jadi ikut menghitung.');
        $this->assertSame(11, $r->json('dasar'));
    }

    /** Dan jawabannya datang dari rumus yang sama dengan yang dipakai audit. */
    public function test_penghitung_memakai_tabel_yang_sama_dengan_audit(): void
    {
        $this->admin();

        foreach ([[0, 'Tinggi'], [15, 'Tinggi'], [15, 'Rendah'], [4000, 'Menengah']] as [$n, $kelas]) {
            $r = $this->postJson(route('admin.companies.mandays'), [
                'workers_employee' => $n, 'workers_sub' => 0, 'risk_class' => $kelas,
            ])->assertOk();

            $this->assertSame(
                SmkpTahap::mandays(['jumlah_pekerja' => $n, 'kelas_risiko' => $kelas])['dasar'],
                $r->json('dasar'),
                "Selisih pada $n pekerja kelas $kelas.",
            );
        }
    }

    /**
     * Kelas risiko di luar daftar ditolak, bukan diam-diam jatuh ke Tinggi.
     *
     * Dialihkan, bukan 422: aplikasi ini hanya merender galat sebagai JSON
     * untuk alamat api/* (bootstrap/app.php). Yang dijaga di sini bukan
     * bentuk jawabannya melainkan bahwa "Sedang" tidak pernah dihitung —
     * ia akan jatuh ke kolom cadangan dan menagih hari kelas tertinggi
     * tanpa satu pun peringatan.
     */
    public function test_kelas_risiko_asing_ditolak(): void
    {
        $this->admin();

        $this->post(route('admin.companies.mandays'), [
            'workers_employee' => 10, 'risk_class' => 'Sedang',
        ])->assertSessionHasErrors('risk_class');
    }

    /** Penghitungnya tertutup bagi yang bukan admin. */
    public function test_penghitung_hanya_untuk_admin(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false, 'email_verified_at' => now()]));

        $this->postJson(route('admin.companies.mandays'), [
            'workers_employee' => 10, 'risk_class' => 'Tinggi',
        ])->assertForbidden();
    }

    /**
     * 'companies/mandays' tidak boleh tertangkap 'companies/{company}'.
     *
     * Urutan pendaftaran rute yang terbalik membuat Laravel mencari
     * perusahaan bernama "mandays" dan memulangkan 404 — atau, lebih
     * buruk, menjalankan edit() atas model kosong.
     */
    public function test_alamat_penghitung_tidak_tertelan_rute_perusahaan(): void
    {
        $this->admin();

        $this->postJson(route('admin.companies.mandays'), [
            'workers_employee' => 1, 'risk_class' => 'Tinggi',
        ])->assertOk()->assertJsonStructure(['pekerja', 'kelas', 'rentang', 'dasar']);
    }
}
