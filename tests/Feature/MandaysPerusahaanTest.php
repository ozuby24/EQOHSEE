<?php

namespace Tests\Feature;

use App\Models\{Company, SmkpAudit, User};
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

    /* ═══════ Audit mewarisi profil perusahaannya ═══════ */

    private function audit(Company $c, array $permulaan = []): SmkpAudit
    {
        return SmkpAudit::create([
            'company_id' => $c->id, 'tahun' => 2026, 'judul' => 'Audit Uji',
            'status' => 'draft', 'tahap' => 1, 'permulaan' => $permulaan,
        ]);
    }

    /**
     * Audit yang Tahap I-nya belum dibuka mewarisi profil perusahaannya.
     *
     * Ini yang dulu diam-diam salah. Audit baru menyimpan permulaan
     * kosong, `SmkpTahap::mandays` membaca nol pekerja dan jatuh ke
     * baris terkecil tabel, dan Rencana Audit tercetak menagih TIGA
     * hari untuk perusahaan berpekerja 460 kelas Tinggi — yang
     * seharusnya enam belas. Tidak ada galat: nol adalah angka yang
     * sah, tiga hari adalah hasil yang sah, dan lembarnya keluar
     * tertandatangani.
     */
    public function test_audit_tanpa_isian_mewarisi_pekerja_dan_kelas_perusahaan(): void
    {
        $c = Company::create([
            'name' => 'PT Warisan Profil', 'workers_employee' => 120,
            'workers_sub' => 340, 'risk_class' => 'Tinggi',
        ]);

        $m = $this->audit($c)->mandays();

        // 460 pekerja, kelas Tinggi: baris 426–625 menagih 16 hari.
        $this->assertSame(460, $m['pekerja']);
        $this->assertSame('Tinggi', $m['kelas']);
        $this->assertSame(16, $m['dasar']);
    }

    /**
     * Kelas risiko pun diwarisi, bukan jatuh ke 'Tinggi' bawaan.
     *
     * Cadangan yang berhenti di jumlah pekerja saja menagih kelas
     * tertinggi kepada tambang batuan kelas Rendah — 40 pekerja
     * kelas Rendah menuntut 4 hari, kelas Tinggi menuntut 6.
     */
    public function test_kelas_risiko_perusahaan_ikut_diwarisi(): void
    {
        $c = Company::create([
            'name' => 'PT Batu Kelas Rendah', 'workers_employee' => 18,
            'workers_sub' => 22, 'risk_class' => 'Rendah',
        ]);

        $m = $this->audit($c)->mandays();

        $this->assertSame(40, $m['pekerja']);
        $this->assertSame('Rendah', $m['kelas']);
        $this->assertSame(4, $m['dasar']);
    }

    /**
     * CADANGAN, BUKAN PENIMPA.
     *
     * Lingkup audit dapat memang lebih sempit daripada perusahaannya —
     * satu site dari tiga. Angka yang sudah diketik auditor menang, dan
     * profil tidak boleh menariknya kembali diam-diam pada pemuatan
     * halaman berikutnya.
     */
    public function test_isian_auditor_mengalahkan_profil_perusahaan(): void
    {
        $c = Company::create([
            'name' => 'PT Tiga Site', 'workers_employee' => 500,
            'workers_sub' => 400, 'risk_class' => 'Tinggi',
        ]);

        $m = $this->audit($c, ['jumlah_pekerja' => 40, 'kelas_risiko' => 'Rendah'])->mandays();

        $this->assertSame(40, $m['pekerja']);
        $this->assertSame('Rendah', $m['kelas']);
        $this->assertSame(4, $m['dasar']);
    }

    /**
     * Perusahaan yang profilnya sendiri kosong tidak menjadi galat.
     *
     * Ia memang belum tahu jumlah pekerjanya; yang benar adalah
     * memulangkan baris terkecil apa adanya, bukan melempar.
     */
    public function test_profil_kosong_tetap_memulangkan_hitungan(): void
    {
        $c = Company::create(['name' => 'PT Belum Didata']);

        $m = $this->audit($c)->mandays();

        $this->assertSame(0, $m['pekerja']);
        $this->assertIsInt($m['dasar']);
    }
}
