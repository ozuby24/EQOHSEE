<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Miners\{Alur, Induksi, InduksiOrang, Mcu, McuOrang, Pekerja, Permit, PermitBerkas,
    Simper, SimperAjuan, SimperAjuanUnit, SimperUnit, Skema};
use App\Models\User;
use App\Notifications\AlurMinersBerpindah;
use App\Support\Miners\{Acuan, Jalur, Kelengkapan, MasterMiners, TugasMiners};
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Penyetuju bernama per IUP — perilaku Safe Track.
 *
 * Yang dijaga di sini:
 *
 *   · berkas MITRA (IUJP) disetujui HSE/KTT milik IUP induknya, bukan
 *     OHSE mitra itu sendiri (cacat 3 peta porting);
 *   · surel pergi ke yang ditugasi, penerima tambahan, dan tembusannya;
 *   · layar Alur Persetujuan hanya bagi admin dan HSE IUP, dan menyimpan
 *     skema memindahkan langkah yang sedang menunggu;
 *   · lonceng tugas, penanda bilah samping, dan antrean "Perlu Approval
 *     Saya / Semua" menghitung hal yang sama;
 *   · rantai pengajuan lanjutan berbeda menurut jenisnya;
 *   · penilaian medis dan nilai induksi hanya oleh yang ditugasi.
 */
class MinersPenyetujuTest extends TestCase
{
    use RefreshDatabase;

    private Company $iup;
    private Company $mitra;
    private User $ohse;
    private User $ktt;
    private User $dokter;
    private User $pengajuMitra;

    protected function setUp(): void
    {
        parent::setUp();

        MasterMiners::pasang();

        $this->iup   = Company::create(['name' => 'PT IUP Uji', 'code' => 'IUP', 'izin_type' => 'IUP Operasi Produksi']);
        $this->mitra = Company::create(['name' => 'PT Mitra Uji', 'code' => 'MTR', 'izin_type' => 'IUJP', 'parent_id' => $this->iup->id]);

        $this->ohse   = User::factory()->create(['company_id' => $this->iup->id, 'ohse_role' => 'ohse']);
        $this->ktt    = User::factory()->create(['company_id' => $this->iup->id, 'lms_role' => 'ktt']);
        $this->dokter = User::factory()->create(['company_id' => $this->iup->id, 'ohse_role' => 'paramedis']);
        $this->pengajuMitra = User::factory()->create(['company_id' => $this->mitra->id]);

        Skema::create([
            'company_id'     => $this->iup->id,
            'dokter_user_id' => $this->dokter->id,
            'ohse_user_id'   => $this->ohse->id,
            'ktt_user_id'    => $this->ktt->id,
            'ohse_to'        => 'hse.kedua@contoh.test',
            'ohse_cc'        => 'tembusan.hse@contoh.test',
        ]);
    }

    private function pekerja(Company $c): Pekerja
    {
        return Pekerja::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'nama' => 'Operator '.Str::random(4), 'status' => 'aktif',
        ]);
    }

    /** Permit buatan mitra, lewat jalur aplikasi (pembuatnya sedang masuk). */
    private function permitMitra(): Permit
    {
        $this->actingAs($this->pengajuMitra);

        $p = Permit::create([
            'pekerja_id' => $this->pekerja($this->mitra)->id,
            'tanggal'    => Waktu::kini()->startOfDay(),
            'user_id'    => $this->pengajuMitra->id,
        ]);
        $p->terbitkanAlur();

        foreach (Acuan::berkasWajib(Kelengkapan::kunci($p) ?? 'permit_baru') as $label) {
            PermitBerkas::create(['permit_id' => $p->id, 'jenis' => Str::slug($label), 'berkas' => 'x/'.Str::slug($label).'.pdf']);
        }

        return $p->fresh();
    }

    /* ═══════════ IUP penerbit ═══════════ */

    public function test_berkas_mitra_disetujui_penyetuju_iup_induknya(): void
    {
        Notification::fake();

        $ohseMitra = User::factory()->create(['company_id' => $this->mitra->id, 'ohse_role' => 'ohse']);

        $p = $this->permitMitra();

        $this->assertSame($this->mitra->id, (int) $p->company_id, 'Pemilik baris tetap mitranya.');
        $this->assertSame($this->iup->id, (int) $p->iup_id, 'IUP penerbit tidak terbaca dari induk mitra.');
        $this->assertSame($this->ohse->id, (int) $p->alur->firstWhere('peran', 'ohse')->ditugaskan_user_id);

        $this->assertNull(Jalur::kirim($p, $this->pengajuMitra), 'Mitra tidak dapat mengirim berkasnya sendiri.');

        Notification::assertSentTo($this->ohse, AlurMinersBerpindah::class);
        Notification::assertNotSentTo($ohseMitra, AlurMinersBerpindah::class);
    }

    public function test_surel_kirim_menyertakan_penerima_tambahan_dan_tembusan(): void
    {
        Notification::fake();

        $p = $this->permitMitra();
        Jalur::kirim($p, $this->pengajuMitra);

        Notification::assertSentTo($this->ohse, AlurMinersBerpindah::class, function ($n) {
            $cc = array_map(fn ($x) => is_array($x) ? $x[0] : $x, $n->toMail($this->ohse)->cc);

            return in_array('tembusan.hse@contoh.test', $cc, true);
        });

        /* "Penerima tambahan" ikut menerima seperti penerima utama. */
        Notification::assertSentOnDemand(AlurMinersBerpindah::class,
            fn ($n, $kanal, AnonymousNotifiable $tujuan) => ($tujuan->routes['mail'] ?? null) === 'hse.kedua@contoh.test');
    }

    public function test_iup_tanpa_skema_memakai_alur_umum(): void
    {
        $lain = Company::create(['name' => 'PT IUP Lain', 'code' => 'LAIN']);
        $ohseUmum = User::factory()->create(['company_id' => $lain->id, 'ohse_role' => 'ohse']);

        Skema::create(['company_id' => null, 'ohse_user_id' => $ohseUmum->id]);

        $induksi = Induksi::withoutGlobalScopes()->create([
            'company_id' => $lain->id, 'tanggal' => Waktu::kini()->startOfDay(),
        ]);
        $induksi->terbitkanAlur();

        $this->assertSame($ohseUmum->id, (int) $induksi->alur->firstWhere('peran', 'ohse')->ditugaskan_user_id);
    }

    /* ═══════════ layar Alur Persetujuan ═══════════ */

    public function test_layar_alur_hanya_bagi_admin_dan_hse_iup(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->get('/miners/alur-persetujuan')->assertOk()
            ->assertInertia(fn (AssertableInertia $h) => $h->component('Miners/AlurPersetujuan')->has('kartu'));

        $this->actingAs($this->ohse)->get('/miners/alur-persetujuan')->assertOk()
            ->assertInertia(fn (AssertableInertia $h) => $h->has('kartu', 1));

        $this->actingAs($this->pengajuMitra)->get('/miners/alur-persetujuan')->assertForbidden();

        $ohseMitra = User::factory()->create(['company_id' => $this->mitra->id, 'ohse_role' => 'ohse']);
        $this->actingAs($ohseMitra)->get('/miners/alur-persetujuan')->assertForbidden();
    }

    public function test_hse_iup_tidak_dapat_mengatur_iup_lain(): void
    {
        $lain = Company::create(['name' => 'PT IUP Lain', 'code' => 'LAIN']);

        $this->actingAs($this->ohse)
            ->put("/miners/alur-persetujuan/{$lain->id}", ['ohse_user_id' => $this->ohse->id])
            ->assertForbidden();
    }

    public function test_alamat_surel_tidak_sah_ditolak(): void
    {
        $this->actingAs($this->ohse)
            ->put("/miners/alur-persetujuan/{$this->iup->id}", [
                'ohse_user_id' => $this->ohse->id,
                'ohse_cc'      => 'benar@contoh.test, salah-ketik',
            ])->assertSessionHasErrors('ohse_cc');
    }

    public function test_penyetuju_harus_orang_iup_atau_mitranya(): void
    {
        $asing = User::factory()->create(['company_id' => Company::create(['name' => 'PT Asing'])->id]);

        $this->actingAs($this->ohse)
            ->put("/miners/alur-persetujuan/{$this->iup->id}", ['ktt_user_id' => $asing->id])
            ->assertSessionHasErrors('ktt_user_id');
    }

    public function test_mengganti_penyetuju_memindahkan_langkah_yang_menunggu(): void
    {
        $p = $this->permitMitra();
        Jalur::kirim($p, $this->pengajuMitra);

        $ohseBaru = User::factory()->create(['company_id' => $this->iup->id, 'ohse_role' => 'ohse']);

        $this->actingAs($this->ohse)
            ->put("/miners/alur-persetujuan/{$this->iup->id}", [
                'dokter_user_id' => $this->dokter->id,
                'ohse_user_id'   => $ohseBaru->id,
                'ktt_user_id'    => $this->ktt->id,
            ])->assertSessionHasNoErrors();

        $this->assertSame($ohseBaru->id, (int) $p->fresh()->alur->firstWhere('peran', 'ohse')->ditugaskan_user_id,
            'Langkah yang menunggu tertinggal pada penyetuju lama.');

        /* Yang sudah ditindak tidak disentuh — itu riwayat. */
        $this->assertSame($this->pengajuMitra->id, (int) $p->fresh()->alur->firstWhere('peran', 'pengaju')->ditugaskan_user_id);
    }

    /* ═══════════ lonceng dan antrean ═══════════ */

    public function test_lonceng_dan_antrean_menghitung_yang_menunggu_saya(): void
    {
        $p = $this->permitMitra();

        /* Draf belum dihitung siapa pun. */
        $this->assertSame(0, TugasMiners::hitung($this->ohse)['permit']);

        Jalur::kirim($p, $this->pengajuMitra);
        request()->attributes->remove('tugas-miners:'.$this->ohse->id);

        $this->assertSame(1, TugasMiners::hitung($this->ohse)['permit']);
        $this->assertSame(0, TugasMiners::hitung($this->ktt)['permit']);

        $this->actingAs($this->ohse)->get('/miners/daftar/outstanding-permit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $h) => $h
                ->component('Miners/Antrean')
                ->where('pilih.aktif', 'saya')
                ->where('pilih.saya', 1)
                ->has('baris', 1)
                ->has('tugasMiners', 1)
                ->where('tugasMiners.0.kunci', 'permit'));

        /* KTT belum giliran: "Perlu Approval Saya" kosong, "Semua"
           memperlihatkannya. */
        $this->actingAs($this->ktt)->get('/miners/daftar/outstanding-permit')
            ->assertInertia(fn (AssertableInertia $h) => $h->has('baris', 0)->where('pilih.saya', 0));

        $this->actingAs($this->ktt)->get('/miners/daftar/outstanding-permit?semua=1')
            ->assertInertia(fn (AssertableInertia $h) => $h->where('pilih.aktif', 'semua')->has('baris', 1));
    }

    public function test_penanda_bilah_samping_sama_dengan_lonceng(): void
    {
        $p = $this->permitMitra();
        Jalur::kirim($p, $this->pengajuMitra);

        $menu = $this->actingAs($this->ohse)->get('/miners/dasbor')->viewData('page')['props']['menu'];

        $butir = collect($menu['grup'])->firstWhere('nama', 'Outstanding')['butir'];
        $permit = collect($butir)->firstWhere('label', 'Mine Permit');

        $this->assertSame(1, $permit['lencana']);
    }

    /* ═══════════ rantai pengajuan lanjutan ═══════════ */

    public function test_rantai_pengajuan_lanjutan_menurut_jenisnya(): void
    {
        $this->actingAs($this->ohse);

        $permit = Permit::create(['pekerja_id' => $this->pekerja($this->iup)->id, 'tanggal' => Waktu::kini()->startOfDay(), 'status' => 'terbit']);
        $simper = Simper::create(['permit_id' => $permit->id, 'pekerja_id' => $permit->pekerja_id,
            'tanggal' => Waktu::kini()->startOfDay(), 'kelas' => 'F', 'status' => 'terbit']);

        foreach (['penambahan' => ['pengaju', 'ohse', 'ktt'],
                  'upgrade' => ['pengaju', 'ohse'],
                  'perpanjangan' => ['pengaju', 'dokter', 'ohse', 'ktt']] as $jenis => $rantai) {
            $a = SimperAjuan::create(['simper_id' => $simper->id, 'jenis' => $jenis, 'tanggal' => Waktu::kini()->startOfDay()]);
            $a->terbitkanAlur();

            $this->assertSame($rantai, $a->alur->pluck('peran')->all(), "Rantai {$jenis} keliru.");
            $this->assertSame($this->iup->id, (int) $a->iup_id);
        }
    }

    public function test_penambahan_yang_tuntas_memasukkan_unitnya_ke_kartu(): void
    {
        /* Sebelumnya status akhir pengajuan lanjutan ditulis "terbit",
           sedangkan penggabungan unit menunggu "selesai" — unitnya tidak
           pernah masuk ke kartu. */
        $this->actingAs($this->pengajuMitra);

        $permit = Permit::create(['pekerja_id' => $this->pekerja($this->mitra)->id, 'tanggal' => Waktu::kini()->startOfDay(), 'status' => 'terbit']);
        $simper = Simper::create(['permit_id' => $permit->id, 'pekerja_id' => $permit->pekerja_id,
            'tanggal' => Waktu::kini()->startOfDay(), 'kelas' => 'F', 'status' => 'terbit', 'user_id' => $this->pengajuMitra->id]);

        $a = SimperAjuan::create(['simper_id' => $simper->id, 'jenis' => 'penambahan',
            'tanggal' => Waktu::kini()->startOfDay(), 'user_id' => $this->pengajuMitra->id]);
        $a->terbitkanAlur();
        SimperAjuanUnit::create(['ajuan_id' => $a->id, 'kewenangan' => 'operator', 'nilai_p2h' => 90]);

        $this->post("/miners/ajuan/{$a->id}/kirim")->assertSessionHasNoErrors();

        $this->actingAs($this->ohse)->post("/miners/ajuan/{$a->id}/tindak", ['keadaan' => 'setuju'])->assertSessionHasNoErrors();
        $this->actingAs($this->ktt)->post("/miners/ajuan/{$a->id}/tindak", ['keadaan' => 'setuju'])->assertSessionHasNoErrors();

        $this->assertSame('selesai', $a->fresh()->status);
        $this->assertSame(1, SimperUnit::withoutGlobalScopes()->where('simper_id', $simper->id)->where('asal', 'penambahan')->count());
    }

    /* ═══════════ penjagaan server ═══════════ */

    public function test_hasil_mcu_hanya_ditetapkan_dokter_yang_ditugasi(): void
    {
        $this->actingAs($this->pengajuMitra);

        $mcu = Mcu::create(['tanggal' => Waktu::kini()->startOfDay(), 'kepada' => 'Klinik', 'user_id' => $this->pengajuMitra->id]);
        $mcu->terbitkanAlur();
        $orang = McuOrang::create(['mcu_id' => $mcu->id, 'pekerja_id' => $this->pekerja($this->mitra)->id, 'nama' => 'X']);
        $hasil = \App\Models\Miners\HasilMcu::withoutGlobalScopes()->where('layak', true)->firstOrFail();

        /* Pengaju boleh melengkapi tanggal pada drafnya, tidak hasilnya. */
        $this->post("/miners/mcu/{$mcu->id}/orang/{$orang->id}/hasil", ['hasil_id' => $hasil->id])
            ->assertSessionHasErrors('hasil_id');
        $this->post("/miners/mcu/{$mcu->id}/orang/{$orang->id}/hasil", ['tanggal_periksa' => Waktu::kini()->toDateString()])
            ->assertSessionHasNoErrors();

        /* Orang lain yang bukan dokter surat ini ditolak sama sekali. */
        $this->actingAs($this->ktt)
            ->post("/miners/mcu/{$mcu->id}/orang/{$orang->id}/hasil", ['hasil_id' => $hasil->id])
            ->assertForbidden();

        $this->actingAs($this->dokter)
            ->post("/miners/mcu/{$mcu->id}/orang/{$orang->id}/hasil", ['hasil_id' => $hasil->id])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($hasil->id, (int) $orang->fresh()->hasil_id);
    }

    public function test_nilai_induksi_hanya_oleh_hse_yang_ditugasi(): void
    {
        $this->actingAs($this->pengajuMitra);

        $induksi = Induksi::create(['tanggal' => Waktu::kini()->startOfDay(), 'user_id' => $this->pengajuMitra->id]);
        $induksi->terbitkanAlur();
        $orang = InduksiOrang::create(['induksi_id' => $induksi->id, 'pekerja_id' => $this->pekerja($this->mitra)->id]);

        $this->post("/miners/induksi/{$induksi->id}/orang/{$orang->id}/nilai", ['nilai' => 90])->assertForbidden();

        $this->actingAs($this->ohse)
            ->post("/miners/induksi/{$induksi->id}/orang/{$orang->id}/nilai", ['nilai' => 90])
            ->assertSessionHasNoErrors();
    }

    public function test_permit_yang_sudah_dikirim_tidak_dapat_disunting_pengaju(): void
    {
        $p = $this->permitMitra();
        Jalur::kirim($p, $this->pengajuMitra);

        $this->actingAs($this->pengajuMitra)
            ->put("/miners/permit/{$p->id}", ['pekerja_id' => $p->pekerja_id, 'tanggal' => Waktu::kini()->toDateString()])
            ->assertForbidden();
    }

    public function test_kirim_lewat_rute_hanya_oleh_pengajunya(): void
    {
        $p = $this->permitMitra();

        $this->actingAs(User::factory()->create(['company_id' => $this->mitra->id]))
            ->post("/miners/permit/{$p->id}/kirim")
            ->assertSessionHasErrors('kirim');

        $this->actingAs($this->pengajuMitra)
            ->post("/miners/permit/{$p->id}/kirim")
            ->assertSessionHasNoErrors();

        $this->assertSame('ohse', $p->fresh()->status);
    }

    public function test_rincian_menampilkan_posisi_dan_kolom_persetujuan(): void
    {
        $p = $this->permitMitra();
        Jalur::kirim($p, $this->pengajuMitra);

        $this->actingAs($this->ohse)->get("/miners/permit/{$p->id}")
            ->assertInertia(fn (AssertableInertia $h) => $h
                ->where('posisi.nada', 'proses')
                ->where('alur.1.sebutan', 'Verifikasi')
                ->where('alur.1.label', 'HSE IUP')
                ->where('alur.0.keadaanLabel', 'Diajukan')
                ->where('bolehTindak', true));
    }
}
