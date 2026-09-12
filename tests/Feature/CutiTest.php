<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Hr\{Cuti, JenisCuti, PolaRoster, Roster, SaldoCuti};
use App\Models\Miners\Pekerja;
use App\Models\User;
use App\Support\Hr\{JalurCuti, KebijakanCuti, MasterCuti, MasterRoster, Penyusun};
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Cuti & izin.
 *
 * Empat aturan di sini tidak dapat diperiksa dengan mata pada layar,
 * dan keempatnya mahal bila salah:
 *
 *   · HARI YANG TERPOTONG DIHITUNG DARI ROSTER, bukan kalender. Pada
 *     pola 14:7, cuti yang jatuh pada periode off-site tidak memakan
 *     satu pun hari kerja — dihitung dari kalender, seorang pekerja
 *     FIFO kehilangan seluruh dua belas hari cuti tahunannya dalam
 *     satu periode libur yang memang haknya.
 *
 *   · IZIN KHUSUS TIDAK MEMOTONG SALDO TAHUNAN. Pasal 93 ayat (4)
 *     menyebutnya upah yang tetap dibayar; dipotong, seorang yang
 *     ayahnya meninggal kehilangan dua hari cuti tahunannya.
 *
 *   · CUTI YANG DISETUJUI MENULIS KE ROSTER, dan penyusunan ulang
 *     baseline tidak boleh menghapusnya.
 *
 *   · HAK TIMBUL SESUDAH DUA BELAS BULAN (pasal 79). Diberikan sejak
 *     hari pertama, kesalahannya baru terlihat ketika seseorang resign
 *     dengan sisa cuti yang harus diuangkan.
 */
class CutiTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $pengaju;
    private User $atasan;
    private User $ktt;
    private Carbon $hari;

    protected function setUp(): void
    {
        parent::setUp();

        MasterRoster::pasang();
        MasterCuti::pasang();

        $this->c       = Company::create(['name' => 'PT Uji Cuti']);
        $this->pengaju = User::factory()->create(['company_id' => $this->c->id]);
        $this->atasan  = User::factory()->create(['company_id' => $this->c->id, 'is_admin' => true]);
        $this->ktt     = User::factory()->create(['company_id' => $this->c->id, 'lms_role' => 'ktt']);

        $this->hari = Waktu::kini()->startOfDay();
    }

    /* ═══════════════════ perkakas ═══════════════════ */

    private function pekerja(?Carbon $masuk = null): Pekerja
    {
        return Pekerja::withoutGlobalScopes()->create([
            'company_id'    => $this->c->id,
            'nama'          => 'Operator '.uniqid(),
            'no_registrasi' => 'REG-'.uniqid(),
            'status'        => 'aktif',
            'tanggal_masuk' => ($masuk ?? $this->hari->copy()->subYears(3))->copy()->startOfDay(),
        ]);
    }

    private function jenis(string $kunci): JenisCuti
    {
        return JenisCuti::withoutGlobalScopes()->where('kunci', $kunci)->firstOrFail();
    }

    private function roster(Pekerja $p, Carbon $t, string $keadaan = 'kerja'): Roster
    {
        return Roster::withoutGlobalScopes()->create([
            'company_id'     => $this->c->id,
            'pekerja_id'     => $p->id,
            'pola_roster_id' => PolaRoster::withoutGlobalScopes()->where('kode', '14:7')->value('id'),
            'tanggal'        => $t->copy()->startOfDay(),
            'keadaan'        => $keadaan,
            'shift'          => $keadaan === 'kerja' ? 'siang' : null,
            'jam'            => $keadaan === 'kerja' ? 11 : 0,
        ]);
    }

    private function ajukan(Pekerja $p, JenisCuti $j, Carbon $mulai, int $lama = 1): Cuti
    {
        $selesai = $mulai->copy()->addDays($lama - 1);
        $n = KebijakanCuti::hariTerpotong($p, $mulai, $selesai);

        return Cuti::withoutGlobalScopes()->create([
            'company_id'    => $this->c->id,
            'pekerja_id'    => $p->id,
            'jenis_cuti_id' => $j->id,
            'mulai'         => $mulai->copy()->startOfDay(),
            'selesai'       => $selesai->copy()->startOfDay(),
            'hari'          => $n['hari'],
            'kalender'      => $n['kalender'],
            'status'        => 'menunggu',
            'diajukan_oleh' => $this->pengaju->id,
        ]);
    }

    /* ═══════════════════ hari yang terpotong ═══════════════════ */

    #[Test]
    public function test_hari_libur_menurut_roster_tidak_memotong_saldo(): void
    {
        $p = $this->pekerja();

        /* Lima hari kalender: dua kerja, tiga libur. */
        $this->roster($p, $this->hari, 'kerja');
        $this->roster($p, $this->hari->copy()->addDay(), 'kerja');
        $this->roster($p, $this->hari->copy()->addDays(2), 'libur');
        $this->roster($p, $this->hari->copy()->addDays(3), 'libur');
        $this->roster($p, $this->hari->copy()->addDays(4), 'libur');

        $n = KebijakanCuti::hariTerpotong($p, $this->hari, $this->hari->copy()->addDays(4));

        $this->assertSame(2, $n['hari'], 'Hari libur ikut terpotong dari saldo.');
        $this->assertSame(5, $n['kalender']);
    }

    #[Test]
    public function test_hari_yang_belum_punya_roster_tetap_terhitung(): void
    {
        /* Roster bulan depan belum tentu sudah disusun. Diperlakukan
           sebagai libur, seluruh cuti tahunan dapat diambil dengan
           mengajukannya sebelum rosternya dibuat. */
        $p = $this->pekerja();

        $n = KebijakanCuti::hariTerpotong(
            $p,
            $this->hari->copy()->addDays(60),
            $this->hari->copy()->addDays(62),
        );

        $this->assertSame(3, $n['hari']);
    }

    /* ═══════════════════ saldo ═══════════════════ */

    #[Test]
    public function test_hak_tahunan_belum_timbul_sebelum_dua_belas_bulan(): void
    {
        $baru = $this->pekerja($this->hari->copy()->subMonths(5));
        $lama = $this->pekerja($this->hari->copy()->subYears(2));

        $tahun = (int) $this->hari->format('Y');
        $j     = $this->jenis('tahunan');

        $this->assertSame(0,  KebijakanCuti::hakTahunan($baru, $j, $tahun));
        $this->assertSame(12, KebijakanCuti::hakTahunan($lama, $j, $tahun));
    }

    #[Test]
    public function test_tanpa_tanggal_masuk_haknya_nol_bukan_penuh(): void
    {
        /* Data lama yang tanggal masuknya kosong akan memberi hak penuh
           kepada siapa pun yang barisnya belum lengkap — dan kesalahan
           itu baru terlihat pada pembayaran sisa cuti saat seseorang
           berhenti. */
        $p = $this->pekerja();
        $p->forceFill(['tanggal_masuk' => null])->save();

        $this->assertSame(0, KebijakanCuti::hakTahunan($p->fresh(), $this->jenis('tahunan'), (int) $this->hari->format('Y')));
    }

    #[Test]
    public function test_pengajuan_yang_menunggu_ikut_mengurangi_yang_dapat_diajukan(): void
    {
        /* Dua pengajuan yang sama-sama menunggu dapat disetujui berdua
           dan saldonya menjadi minus tanpa satu galat pun. */
        $p = $this->pekerja();
        $j = $this->jenis('tahunan');

        $this->ajukan($p, $j, $this->hari->copy()->addDays(30), 4);

        $r = KebijakanCuti::ringkas($p, $j, (int) $this->hari->format('Y'));

        $this->assertSame(12, $r['sisa'], 'Saldo terpotong sebelum disetujui.');
        $this->assertSame(4,  $r['tertunda']);
        $this->assertSame(8,  $r['tersedia']);
    }

    #[Test]
    public function test_saldo_tidak_cukup_menolak_pengajuan(): void
    {
        $p = $this->pekerja();
        $j = $this->jenis('tahunan');

        $this->ajukan($p, $j, $this->hari->copy()->addDays(30), 10);

        $alasan = KebijakanCuti::periksa($p, $j, $this->hari->copy()->addDays(60), $this->hari->copy()->addDays(64));

        $this->assertNotNull($alasan);
        $this->assertStringContainsString('Saldo tidak cukup', $alasan);
    }

    #[Test]
    public function test_izin_khusus_tidak_memotong_saldo_tahunan(): void
    {
        $p = $this->pekerja();

        $c = $this->ajukan($p, $this->jenis('duka-inti'), $this->hari->copy()->addDays(3), 2);

        $this->assertNull(JalurCuti::setujui($c, $this->atasan));

        $sisa = KebijakanCuti::ringkas($p, $this->jenis('tahunan'), (int) $this->hari->format('Y'));

        $this->assertSame(12, $sisa['sisa'], 'Izin khusus pasal 93 memakan cuti tahunan.');
        $this->assertSame(0,  $sisa['terpakai']);
    }

    #[Test]
    public function test_cuti_tahunan_memotong_saldo_saat_disetujui(): void
    {
        $p = $this->pekerja();
        $j = $this->jenis('tahunan');

        $c = $this->ajukan($p, $j, $this->hari->copy()->addDays(3), 3);

        $this->assertSame(12, KebijakanCuti::ringkas($p, $j, (int) $this->hari->format('Y'))['sisa']);

        $this->assertNull(JalurCuti::setujui($c, $this->atasan));

        $this->assertSame(9, KebijakanCuti::ringkas($p, $j, (int) $this->hari->format('Y'))['sisa']);
    }

    /* ═══════════════════ alur ═══════════════════ */

    #[Test]
    public function test_pengaju_tidak_dapat_menyetujui_pengajuannya_sendiri(): void
    {
        $p = $this->pekerja();
        $c = $this->ajukan($p, $this->jenis('tahunan'), $this->hari->copy()->addDays(3), 2);

        $this->assertNotNull(JalurCuti::setujui($c, $this->pengaju));
        $this->assertSame('menunggu', $c->fresh()->status);
    }

    #[Test]
    public function test_pekerja_yang_tertaut_akun_juga_terhitung_pengaju(): void
    {
        /* Barisnya dibuat admin atas namanya, tetapi yang cuti tetap
           dia — dan dia tidak boleh menekan tombol setujui atas dirinya
           sendiri. */
        $p = $this->pekerja();
        $p->forceFill(['user_id' => $this->ktt->id])->save();

        $c = $this->ajukan($p->fresh(), $this->jenis('tahunan'), $this->hari->copy()->addDays(3), 2);
        $c->forceFill(['diajukan_oleh' => $this->atasan->id])->save();

        $this->assertNotNull(JalurCuti::setujui($c->fresh(), $this->ktt));
    }

    #[Test]
    public function test_yang_diteruskan_hanya_dapat_ditindak_jenjang_di_atasnya(): void
    {
        $p = $this->pekerja();
        $c = $this->ajukan($p, $this->jenis('tahunan'), $this->hari->copy()->addDays(3), 2);

        $biasa = User::factory()->create(['company_id' => $this->c->id]);

        $this->assertNull(JalurCuti::teruskan($c, $biasa));
        $this->assertTrue((bool) $c->fresh()->perlu_jenjang);

        $this->assertNotNull(JalurCuti::setujui($c->fresh(), $biasa));
        $this->assertNull(JalurCuti::setujui($c->fresh(), $this->ktt));
    }

    #[Test]
    public function test_pengajuan_yang_sudah_ditindak_tidak_dapat_ditindak_lagi(): void
    {
        $p = $this->pekerja();
        $j = $this->jenis('tahunan');
        $c = $this->ajukan($p, $j, $this->hari->copy()->addDays(3), 2);

        $this->assertNull(JalurCuti::setujui($c, $this->atasan));
        $this->assertNotNull(JalurCuti::setujui($c->fresh(), $this->atasan));

        /* Dan saldonya tidak terpotong dua kali. */
        $this->assertSame(10, KebijakanCuti::ringkas($p, $j, (int) $this->hari->format('Y'))['sisa']);
    }

    #[Test]
    public function test_pengajuan_yang_ditolak_tidak_dapat_disetujui_belakangan(): void
    {
        /* Pengajuan yang DITOLAK tidak menyentuh roster sama sekali,
           sehingga seluruh pemeriksaan kelayakan masih akan lolos bila
           dijalankan ulang. Yang menahannya hanyalah penjagaan status
           itu sendiri — dan penjagaan itu yang diuji di sini.
           Diperiksa lewat pengajuan yang sudah disetujui, uji lulus
           karena alasan yang lain: hari-harinya sudah menjadi cuti pada
           roster, jadi tidak ada lagi hari kerja yang dapat dipotong.
           Ditemukan uji mutasi. */
        $p = $this->pekerja();
        $j = $this->jenis('tahunan');
        $c = $this->ajukan($p, $j, $this->hari->copy()->addDays(3), 2);

        $this->assertNull(JalurCuti::tolak($c, $this->atasan, 'Regu sedang tipis.'));

        $this->assertNotNull(JalurCuti::setujui($c->fresh(), $this->atasan));

        $this->assertSame('ditolak', $c->fresh()->status);
        $this->assertSame(12, KebijakanCuti::ringkas($p, $j, (int) $this->hari->format('Y'))['sisa']);
        $this->assertSame(0, Roster::withoutGlobalScopes()->where('cuti_id', $c->id)->count());
    }

    #[Test]
    public function test_rentang_yang_bertumpang_tindih_ditolak(): void
    {
        $p = $this->pekerja();
        $j = $this->jenis('tahunan');

        $this->ajukan($p, $j, $this->hari->copy()->addDays(10), 3);

        $alasan = KebijakanCuti::periksa($p, $j, $this->hari->copy()->addDays(12), $this->hari->copy()->addDays(14));

        $this->assertNotNull($alasan);
        $this->assertStringContainsString('tumpang tindih', $alasan);
    }

    /* ═══════════════════ akibat pada roster ═══════════════════ */

    #[Test]
    public function test_cuti_yang_disetujui_menulis_ke_roster(): void
    {
        $p = $this->pekerja();

        for ($i = 0; $i < 3; $i++) $this->roster($p, $this->hari->copy()->addDays($i));

        $c = $this->ajukan($p, $this->jenis('tahunan'), $this->hari, 3);

        $this->assertNull(JalurCuti::setujui($c, $this->atasan));

        $baris = Roster::withoutGlobalScopes()->where('pekerja_id', $p->id)->get();

        $this->assertCount(3, $baris);

        foreach ($baris as $r) {
            $this->assertSame('cuti', $r->keadaan);
            $this->assertSame($c->id, $r->cuti_id);
            $this->assertSame(0, $r->jam);
        }
    }

    #[Test]
    public function test_hari_libur_tidak_ikut_berubah_menjadi_cuti(): void
    {
        /* Mengubahnya membuat periode off-site pola 14:7 tampak sebagai
           cuti tahunan sepanjang tujuh hari, dan rekap ketidakhadiran
           site kehilangan artinya. */
        $p = $this->pekerja();

        $this->roster($p, $this->hari, 'kerja');
        $this->roster($p, $this->hari->copy()->addDay(), 'libur');

        $c = $this->ajukan($p, $this->jenis('tahunan'), $this->hari, 2);

        $this->assertNull(JalurCuti::setujui($c, $this->atasan));

        $libur = Roster::withoutGlobalScopes()->where('pekerja_id', $p->id)
            ->antara($this->hari->copy()->addDay()->toDateString(), $this->hari->copy()->addDay()->toDateString())
            ->first();

        $this->assertSame('libur', $libur->keadaan);
        $this->assertNull($libur->cuti_id);
    }

    #[Test]
    public function test_hari_yang_belum_punya_roster_dibuatkan_barisnya(): void
    {
        /* Cuti yang diajukan untuk bulan yang rosternya belum disusun
           akan hilang begitu baseline dibuat — dan penyusunnya
           menjadwalkan orang yang sudah resmi cuti. */
        $p = $this->pekerja();

        $c = $this->ajukan($p, $this->jenis('tahunan'), $this->hari->copy()->addDays(40), 2);

        $this->assertNull(JalurCuti::setujui($c, $this->atasan));

        $this->assertSame(2, Roster::withoutGlobalScopes()->where('cuti_id', $c->id)->count());
    }

    #[Test]
    public function test_penyusunan_ulang_tidak_menghapus_cuti_yang_disetujui(): void
    {
        /* Menekan "susun ulang" karena satu orang pindah regu akan
           menghapus seluruh cuti yang sudah disetujui bulan itu — dan
           tidak ada satu galat pun yang menandainya, sebab barisnya
           memang tergantikan dengan benar oleh baseline. */
        $pola = PolaRoster::withoutGlobalScopes()->where('kode', '14:7')->firstOrFail();

        $regu = \App\Models\Hr\Regu::withoutGlobalScopes()->create([
            'company_id'     => $this->c->id,
            'pola_roster_id' => $pola->id,
            'nama'           => 'Regu Uji',
            'mulai'          => $this->hari->copy()->startOfDay(),
            'shift'          => 'siang',
        ]);

        $p = $this->pekerja();

        \App\Models\Hr\ReguAnggota::withoutGlobalScopes()->create([
            'regu_id'    => $regu->id,
            'pekerja_id' => $p->id,
            'mulai'      => $this->hari->copy()->startOfDay(),
        ]);

        Penyusun::susun($regu, $this->hari, $this->hari->copy()->addDays(6));

        $c = $this->ajukan($p, $this->jenis('tahunan'), $this->hari->copy()->addDays(2), 2);

        $this->assertNull(JalurCuti::setujui($c, $this->atasan));

        Penyusun::susun($regu, $this->hari, $this->hari->copy()->addDays(6));

        $baris = Roster::withoutGlobalScopes()->where('cuti_id', $c->id)->get();

        $this->assertCount(2, $baris, 'Baris cuti hilang sesudah penyusunan ulang.');

        /* KEADAANNYA yang diperiksa, bukan sekadar tandanya. Penyusun
           memperbarui baris yang ada lewat forceFill dan tidak
           menyentuh `cuti_id` — sehingga baris yang ditimpanya TETAP
           menunjuk ke cutinya sementara keadaannya sudah kembali
           "kerja". Diperiksa dari tandanya saja, uji ini lulus atas
           kode yang justru menghapus cutinya. Ditemukan uji mutasi. */
        foreach ($baris as $r) {
            $this->assertSame('cuti', $r->keadaan, 'Penyusunan ulang mengembalikan hari cuti menjadi hari kerja.');
            $this->assertSame(0, $r->jam);
        }
    }

    #[Test]
    public function test_pembatalan_mengembalikan_roster_dan_saldo(): void
    {
        $p = $this->pekerja();
        $j = $this->jenis('tahunan');

        for ($i = 0; $i < 2; $i++) $this->roster($p, $this->hari->copy()->addDays($i));

        $c = $this->ajukan($p, $j, $this->hari, 2);

        $this->assertNull(JalurCuti::setujui($c, $this->atasan));
        $this->assertSame(10, KebijakanCuti::ringkas($p, $j, (int) $this->hari->format('Y'))['sisa']);

        $this->assertNull(JalurCuti::batalkan($c->fresh(), $this->atasan, 'Tiket batal.'));

        $this->assertSame(12, KebijakanCuti::ringkas($p, $j, (int) $this->hari->format('Y'))['sisa']);

        foreach (Roster::withoutGlobalScopes()->where('pekerja_id', $p->id)->get() as $r) {
            $this->assertSame('kerja', $r->keadaan);
            $this->assertNull($r->cuti_id);
        }
    }

    #[Test]
    public function test_pembatalan_membuang_baris_roster_yang_lahir_dari_cutinya(): void
    {
        /* Baris yang LAHIR dari cutinya tidak punya pola dan tidak
           punya regu — dikembalikan menjadi "kerja", ia menjadi hari
           kerja hantu yang tidak pernah dijadwalkan siapa pun. */
        $p = $this->pekerja();

        $c = $this->ajukan($p, $this->jenis('tahunan'), $this->hari->copy()->addDays(40), 2);

        $this->assertNull(JalurCuti::setujui($c, $this->atasan));
        $this->assertNull(JalurCuti::batalkan($c->fresh(), $this->atasan, 'Batal.'));

        $this->assertSame(0, Roster::withoutGlobalScopes()->where('pekerja_id', $p->id)->count());
    }

    #[Test]
    public function test_pembatalan_berulang_tidak_menaikkan_saldo(): void
    {
        $p = $this->pekerja();
        $j = $this->jenis('tahunan');

        $c = $this->ajukan($p, $j, $this->hari->copy()->addDays(3), 2);

        $this->assertNull(JalurCuti::setujui($c, $this->atasan));
        $this->assertNull(JalurCuti::batalkan($c->fresh(), $this->atasan));
        $this->assertNotNull(JalurCuti::batalkan($c->fresh(), $this->atasan));

        $this->assertSame(12, KebijakanCuti::ringkas($p, $j, (int) $this->hari->format('Y'))['sisa']);
    }

    #[Test]
    public function test_saldo_tidak_pernah_turun_di_bawah_nol(): void
    {
        /* Keadaan yang benar-benar terjadi: bagian personalia
           memperbaiki saldo seseorang secara manual, lalu cuti yang
           sudah disetujui dibatalkan. Pengembaliannya lebih besar
           daripada yang tersisa tercatat, dan tanpa penjagaan ini
           kolom `terpakai` menjadi negatif — yang terbaca sebagai
           saldo lebih besar daripada haknya. */
        $p = $this->pekerja();
        $j = $this->jenis('tahunan');

        $c = $this->ajukan($p, $j, $this->hari->copy()->addDays(3), 3);
        $this->assertNull(JalurCuti::setujui($c, $this->atasan));

        $saldo = SaldoCuti::withoutGlobalScopes()->where('pekerja_id', $p->id)->firstOrFail();
        $this->assertSame(3, $saldo->terpakai);

        /* Koreksi manual: dua hari dihapus dari catatan pemakaian. */
        $saldo->forceFill(['terpakai' => 1])->save();

        $this->assertNull(JalurCuti::batalkan($c->fresh(), $this->atasan, 'Tiket batal.'));

        $this->assertSame(0, $saldo->fresh()->terpakai, 'Pemakaian cuti tercatat negatif.');
        $this->assertSame(12, $saldo->fresh()->sisa());
    }

    /* ═══════════════════ layar ═══════════════════ */

    #[Test]
    public function test_tiap_halaman_cuti_terbuka(): void
    {
        $this->actingAs($this->atasan);

        foreach (['/hris/cuti', '/hris/cuti/saldo'] as $alamat) {
            $this->get($alamat)->assertOk();
        }
    }

    #[Test]
    public function test_pengajuan_lewat_layar_menghitung_hari_dari_roster(): void
    {
        $p = $this->pekerja();

        $this->roster($p, $this->hari->copy()->addDays(3), 'kerja');
        $this->roster($p, $this->hari->copy()->addDays(4), 'libur');
        $this->roster($p, $this->hari->copy()->addDays(5), 'kerja');

        $this->actingAs($this->atasan)->post('/hris/cuti', [
            'pekerja_id'    => $p->id,
            'jenis_cuti_id' => $this->jenis('tahunan')->id,
            'mulai'         => $this->hari->copy()->addDays(3)->toDateString(),
            'selesai'       => $this->hari->copy()->addDays(5)->toDateString(),
            'alasan'        => 'Keperluan keluarga.',
        ])->assertRedirect();

        $c = Cuti::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(2, $c->hari);
        $this->assertSame(3, $c->kalender);
    }

    #[Test]
    public function test_jenis_yang_menuntut_bukti_menolak_pengajuan_tanpa_lampiran(): void
    {
        $p = $this->pekerja();

        $this->actingAs($this->atasan)->post('/hris/cuti', [
            'pekerja_id'    => $p->id,
            'jenis_cuti_id' => $this->jenis('sakit')->id,
            'mulai'         => $this->hari->copy()->addDays(3)->toDateString(),
            'selesai'       => $this->hari->copy()->addDays(4)->toDateString(),
        ])->assertSessionHasErrors('bukti');

        $this->assertSame(0, Cuti::withoutGlobalScopes()->count());
    }

    #[Test]
    public function test_bukti_cuti_dijaga_seketat_surat_mcu(): void
    {
        /* Bukti cuti sakit ADALAH surat dokter, dan surat dokter
           menyebut diagnosisnya — data pribadi spesifik menurut UU PDP
           27/2022. */
        $this->assertArrayHasKey('cti', \App\Support\Berkas::tersaji());

        $biasa = User::factory()->create(['company_id' => $this->c->id]);

        $this->assertFalse(\App\Support\Berkas::bolehMembuka($biasa, 'cti'));
        $this->assertTrue(\App\Support\Berkas::bolehMembuka($this->atasan, 'cti'));
    }
}
