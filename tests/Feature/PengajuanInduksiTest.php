<?php

namespace Tests\Feature;

use App\Models\{Company, InduksiPengajuan, Paspor, PasporInduksi, PasporMcu, User};
use App\Support\{Alur, Authority, Tahap};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pengajuan induksi — satu kelas, banyak peserta.
 *
 * INDUKSI TIDAK DIDAFTARKAN PER ORANG, dan bentuk sebelumnya
 * memaksakannya begitu: satu-satunya jalan mencatat induksi adalah
 * membuka berkas seorang pekerja lalu mengisi formulir di sana. Satu
 * kelas berisi tiga puluh orang menjadi tiga puluh baris yang tidak
 * saling tahu — tidak ada tempat menyimpan nomor registrasinya, tidak
 * ada cara mengetahui siapa saja yang ikut kelas yang sama, dan
 * persetujuannya harus ditekan tiga puluh kali.
 *
 * Yang paling dijaga berkas ini: PENDAFTARAN BUKAN KELULUSAN. Peserta
 * yang namanya masuk daftar hadir belum tentu hadir, dan yang hadir
 * belum tentu lulus. Bila keduanya tertukar, orang mendapat Mine Permit
 * atas dasar kelas yang belum ia ikuti — dan tidak ada satu pun tanda
 * di layar yang membedakannya dari yang sudah lulus sungguhan.
 */
class PengajuanInduksiTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $admin;
    private User $ohse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->c = Company::create(['name' => 'PT Uji Induksi', 'doc_no_prefix' => 'UI']);

        $this->admin = User::factory()->create([
            'is_admin' => true, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);

        $this->ohse = User::factory()->create([
            'is_admin' => false, 'ohse_role' => 'ohse',
            'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);

        $this->actingAs($this->admin);
    }

    /** Pekerja yang MCU-nya sudah menyatakan layak. */
    private function pekerjaLayak(string $nama = 'Budi'): Paspor
    {
        $p = Paspor::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'nama' => $nama,
            'nik' => (string) random_int(1000, 9999), 'jabatan' => 'Operator',
        ]);

        PasporMcu::withoutGlobalScopes()->create([
            'paspor_id'   => $p->id,
            'tgl_periksa' => now()->subMonth()->toDateString(),
            'tgl_expired' => now()->addYear()->toDateString(),
            'jenis' => 'Berkala', 'hasil' => 'Fit',
        ]);

        return $p->refresh();
    }

    private function kelas(array $ganti = []): InduksiPengajuan
    {
        $this->post('/miners/induksi', $ganti + [
            'nomor_register'  => 'IND/2026/0001',
            'tanggal'         => now()->toDateString(),
            'judul'           => 'Induksi keselamatan awal',
            'jenis'           => 'Awal',
            'lokasi'          => 'Ruang Kelas Safety',
            'tgl_pelaksanaan' => now()->addWeek()->toDateString(),
        ])->assertSessionHasNoErrors();

        return InduksiPengajuan::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    /* ═══════════ 1 · surat berisi banyak nama ═══════════ */

    #[Test]
    public function satu_kelas_menampung_banyak_peserta(): void
    {
        $k = $this->kelas();

        foreach (['Budi', 'Sari', 'Tono'] as $nama) {
            $this->post("/miners/induksi/{$k->id}/nama",
                ['paspor_id' => $this->pekerjaLayak($nama)->id])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(3, $k->refresh()->hasil()->count());

        /* Nomor registrasi, lokasi, dan jadwalnya melekat pada KELASNYA,
           bukan diulang pada tiap peserta. */
        $this->assertSame('IND/2026/0001', $k->nomor_register);
        $this->assertSame('Ruang Kelas Safety', $k->lokasi);
    }

    #[Test]
    public function satu_orang_tidak_masuk_dua_kali_ke_kelas_yang_sama(): void
    {
        $k = $this->kelas();
        $p = $this->pekerjaLayak();

        $this->post("/miners/induksi/{$k->id}/nama", ['paspor_id' => $p->id]);
        $this->post("/miners/induksi/{$k->id}/nama", ['paspor_id' => $p->id])
            ->assertSessionHasErrors('paspor_id');

        $this->assertSame(1, $k->refresh()->hasil()->count());
    }

    /* ═══════════ 2 · pendaftaran bukan kelulusan ═══════════ */

    #[Test]
    public function peserta_yang_baru_terdaftar_belum_punya_hasil(): void
    {
        $k = $this->kelas();
        $p = $this->pekerjaLayak();

        $this->post("/miners/induksi/{$k->id}/nama", ['paspor_id' => $p->id]);

        $baris = PasporInduksi::withoutGlobalScopes()->firstOrFail();

        $this->assertNull($baris->hasil,
            'Peserta yang baru terdaftar sudah punya hasil — nilai bawaan itu '
            .'tidak dapat dibedakan dari hasil sungguhan begitu halamannya ditutup.');
        $this->assertFalse($baris->lulus());
    }

    /**
     * INI YANG PALING BERBAHAYA.
     *
     * Bila pendaftaran terbaca sebagai induksi yang sah, orang mendapat
     * Mine Permit atas dasar kelas yang belum ia ikuti.
     */
    #[Test]
    public function pendaftaran_tidak_membuat_orangnya_terinduksi(): void
    {
        $k = $this->kelas();
        $p = $this->pekerjaLayak();

        $this->post("/miners/induksi/{$k->id}/nama", ['paspor_id' => $p->id]);

        $this->assertNull($p->refresh()->induksiBerlaku(),
            'Peserta yang baru terdaftar sudah terhitung punya induksi berlaku — '
            .'ia dapat memperoleh Mine Permit atas dasar kelas yang belum diikutinya.');
    }

    #[Test]
    public function mengisi_hasil_membuatnya_terinduksi(): void
    {
        $k = $this->kelas();
        $p = $this->pekerjaLayak();

        $this->post("/miners/induksi/{$k->id}/nama", ['paspor_id' => $p->id]);

        $baris = PasporInduksi::withoutGlobalScopes()->firstOrFail();

        $this->put("/miners/induksi/{$k->id}/hasil/{$baris->id}", [
            'tanggal'     => now()->toDateString(),
            'tgl_expired' => now()->addYear()->toDateString(),
            'hasil'       => 'Lulus',
            'nilai'       => 88,
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($p->refresh()->induksiBerlaku());
        $this->assertSame(88, $baris->refresh()->nilai);
    }

    #[Test]
    public function belum_dinilai_terhitung_pada_ringkasan(): void
    {
        $k = $this->kelas();

        foreach (['Budi', 'Sari'] as $nama) {
            $this->post("/miners/induksi/{$k->id}/nama",
                ['paspor_id' => $this->pekerjaLayak($nama)->id]);
        }

        $this->assertSame(2, $k->refresh()->load('hasil')->belumDinilai());

        $props = $this->get('/miners/induksi')->viewData('page')['props'];

        $this->assertSame(2, $props['ringkasMcu']['belumKembali'],
            'Angka "hasil belum dinilai" tidak menghitung peserta yang baru terdaftar.');
    }

    /* ═══════════ 3 · syarat MCU ditegakkan saat mendaftar ═══════════ */

    /**
     * Ditahan saat PENDAFTARAN, bukan saat pengisian hasil.
     *
     * Menginduksi orang yang ternyata Unfit adalah setengah hari kelas
     * yang terbuang — dan yang lebih buruk, namanya tercatat di daftar
     * hadir sehingga di layar ia tampak lebih siap daripada sebenarnya.
     */
    #[Test]
    public function pekerja_tanpa_mcu_layak_tidak_dapat_didaftarkan(): void
    {
        $k = $this->kelas();

        $p = Paspor::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'nama' => 'Tanpa MCU',
            'nik' => '5555', 'jabatan' => 'Helper',
        ]);

        $this->post("/miners/induksi/{$k->id}/nama", ['paspor_id' => $p->id])
            ->assertSessionHasErrors('paspor_id');

        $this->assertSame(0, $k->refresh()->hasil()->count());
    }

    /**
     * MCU yang mendasarinya IKUT DICATAT, bukan hanya diperiksa lalu
     * dilupakan. Enam bulan kemudian pertanyaannya bukan "apakah waktu
     * itu ia layak" melainkan "atas dasar apa".
     */
    #[Test]
    public function mcu_yang_mendasari_ikut_tercatat(): void
    {
        $k = $this->kelas();
        $p = $this->pekerjaLayak();

        $this->post("/miners/induksi/{$k->id}/nama", ['paspor_id' => $p->id]);

        $baris = PasporInduksi::withoutGlobalScopes()->firstOrFail();

        $this->assertNotNull($baris->paspor_mcu_id,
            'Induksi tidak menyebut MCU yang mendasarinya — dasarnya harus '
            .'ditebak dari tanggal.');
        $this->assertSame(
            $p->mcu()->first()->id, $baris->paspor_mcu_id
        );
    }

    /* ═══════════ 4 · alur persetujuan ═══════════ */

    #[Test]
    public function kelas_kosong_tidak_dapat_diajukan(): void
    {
        $k = $this->kelas();

        $this->post("/miners/induksi/{$k->id}/ajukan")
            ->assertSessionHasErrors('induksi');

        $this->assertSame(Alur::DRAF, $k->refresh()->status);
    }

    #[Test]
    public function rantai_induksi_berhenti_di_ohse(): void
    {
        $k = $this->kelas();

        $kode = array_column($k->rantaiTahap(), 'kode');

        $this->assertSame([Tahap::OHSE], $kode,
            'Rantai induksi punya tahap selain OHSE. Induksi diselenggarakan '
            .'OHSE sendiri; meja tambahan hanya menambah tempat pengajuan tertahan.');
    }

    #[Test]
    public function hanya_ohse_yang_memutuskan(): void
    {
        $k = $this->kelas();
        $this->post("/miners/induksi/{$k->id}/nama",
            ['paspor_id' => $this->pekerjaLayak()->id]);
        $this->post("/miners/induksi/{$k->id}/ajukan");

        $this->assertSame(Alur::DIAJUKAN, $k->refresh()->status);

        $biasa = User::factory()->create([
            'is_admin' => false, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);

        $this->actingAs($biasa)
            ->post("/miners/induksi/{$k->id}/tinjau", ['aksi' => 'setujui']);

        $this->assertSame(Alur::DIAJUKAN, $k->refresh()->status,
            'Pengguna tanpa peran OHSE berhasil menyetujui kelas induksi.');

        $this->actingAs($this->ohse)
            ->post("/miners/induksi/{$k->id}/tinjau", ['aksi' => 'setujui']);

        $this->assertSame(Alur::DISETUJUI, $k->refresh()->status);
    }

    #[Test]
    public function kelas_yang_sudah_diajukan_tidak_dapat_diubah(): void
    {
        $k = $this->kelas();
        $this->post("/miners/induksi/{$k->id}/nama",
            ['paspor_id' => $this->pekerjaLayak()->id]);
        $this->post("/miners/induksi/{$k->id}/ajukan");

        $this->put("/miners/induksi/{$k->id}", [
            'tanggal' => now()->toDateString(), 'jenis' => 'Awal',
        ])->assertStatus(422);

        $this->post("/miners/induksi/{$k->id}/nama",
            ['paspor_id' => $this->pekerjaLayak('Sari')->id])->assertStatus(422);
    }

    /* ═══════════ 5 · halaman ═══════════ */

    #[Test]
    public function halaman_pengajuan_induksi_tergambar(): void
    {
        $k = $this->kelas();
        $this->post("/miners/induksi/{$k->id}/nama",
            ['paspor_id' => $this->pekerjaLayak()->id]);

        $r = $this->get('/miners/induksi');
        $r->assertOk();

        $props = $r->viewData('page')['props'];

        $this->assertSame('Miners/Halaman', $r->viewData('page')['component']);
        $this->assertSame('induksi', $props['mode']);
        $this->assertCount(1, $props['pengajuan']);
        $this->assertSame(1, $props['pengajuan'][0]['jumlah']);
    }

    /**
     * Bentuknya KEMBAR dengan pengajuan MCU, dan kembarnya dijaga.
     *
     * Satu komponen Vue melayani keduanya lewat prop `mode`. Prop yang
     * ada di satu halaman tetapi hilang di kembarannya membuat
     * komponennya menggambar kolom kosong tanpa galat apa pun.
     */
    #[Test]
    public function bentuk_prop_sama_dengan_pengajuan_mcu(): void
    {
        $k = $this->kelas();
        $this->post("/miners/induksi/{$k->id}/nama",
            ['paspor_id' => $this->pekerjaLayak()->id]);

        /* Kedua halaman harus sama-sama PUNYA baris untuk dibandingkan.
           Tanpa surat MCU, `pengajuan[0]` di sisi MCU adalah larik
           kosong dan perbandingannya lulus tanpa membandingkan apa pun —
           uji yang hampa persis pada hal yang ingin dijaganya. */
        $this->post('/miners/mcu', [
            'nomor_register' => 'MCU/2026/0001',
            'tanggal'        => now()->toDateString(),
            'kepada'         => 'Klinik Uji',
            'jenis'          => 'Berkala',
        ])->assertSessionHasNoErrors();

        $induksi = $this->get('/miners/induksi')->viewData('page')['props'];
        $mcu     = $this->get('/miners/mcu')->viewData('page')['props'];

        $this->assertNotEmpty($mcu['pengajuan'], 'Tidak ada surat MCU untuk dibandingkan.');
        $this->assertNotEmpty($induksi['pengajuan'], 'Tidak ada kelas induksi untuk dibandingkan.');

        $beda = array_diff(array_keys($mcu), array_keys($induksi));

        $this->assertSame([], array_values($beda),
            'Halaman pengajuan induksi kehilangan prop yang dipunyai pengajuan MCU: '
            .implode(', ', $beda));

        $this->assertSame(
            array_keys($mcu['pengajuan'][0]),
            array_keys(array_diff_key($induksi['pengajuan'][0], ['pelaksanaan' => 1])),
            'Bentuk satu baris pengajuan berbeda antara MCU dan induksi.'
        );
    }

    #[Test]
    public function menu_menyebut_pengajuan_induksi(): void
    {
        $ada = collect(\App\Support\Menu::all()['miners']['groups'])
            ->flatten(1)
            ->contains(fn ($b) => is_array($b) && ($b[1] ?? null) === 'miners.induksi.index');

        $this->assertTrue($ada, 'Pengajuan induksi tidak ada di bilah samping.');
    }
}
