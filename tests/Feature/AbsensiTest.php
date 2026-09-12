<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Hr\{Absensi, AbsensiJejak, MesinAbsensi, PolaRoster, Roster};
use App\Models\Miners\Pekerja;
use App\Models\User;
use App\Support\Hr\{MasterRoster, Rekonsiliasi};
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Absensi dan rekonsiliasinya terhadap roster.
 *
 * Tiga hal di sini tidak dapat diperiksa dengan mata pada layar, dan
 * ketiganya pernah salah:
 *
 *   · SHIFT MALAM MELEWATI TENGAH MALAM. Masuk pukul 19.00 tanggal 3
 *     dan pulang pukul 06.00 tanggal 4 adalah SATU hari kerja.
 *     Dikelompokkan menurut tanggal kalendernya, separuh site muncul
 *     sebagai dua baris janggal tiap hari.
 *
 *   · ZONA WAKTU SAAT DISIMPAN. Eloquent menyimpan jam dinding Carbon
 *     yang diberikan tanpa memindahkan zonanya. Seluruh modul ini
 *     sempat meleset delapan jam tanpa satu galat pun.
 *
 *   · KOREKSI MANUSIA TIDAK BOLEH TERTIMPA. Satu batch luring yang
 *     datang terlambat menghapus koreksi yang sudah disetujui
 *     pengawas — dan barisnya memang tertulis ulang dengan benar dari
 *     jejaknya, jadi tidak ada yang tampak rusak.
 */
class AbsensiTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $pengguna;
    private User $admin;

    /** Tanggal acuan, jauh di masa lalu supaya jendela shiftnya pasti tertutup. */
    private Carbon $hari;

    protected function setUp(): void
    {
        parent::setUp();

        MasterRoster::pasang();

        $this->c        = Company::create(['name' => 'PT Uji Absensi']);
        $this->pengguna = User::factory()->create(['company_id' => $this->c->id]);
        $this->admin    = User::factory()->create(['company_id' => $this->c->id, 'is_admin' => true]);

        $this->hari = Waktu::kini()->copy()->startOfDay()->subDays(10);
    }

    /* ═══════════════════ perkakas ═══════════════════ */

    private function pekerja(array $ganti = []): Pekerja
    {
        return Pekerja::withoutGlobalScopes()->create($ganti + [
            'company_id'    => $this->c->id,
            'nama'          => 'Operator '.uniqid(),
            'no_registrasi' => 'REG-'.uniqid(),
            'status'        => 'aktif',
        ]);
    }

    private function pola(string $kode = '14:7'): PolaRoster
    {
        return PolaRoster::withoutGlobalScopes()->where('kode', $kode)->firstOrFail();
    }

    private function roster(Pekerja $p, Carbon $tanggal, string $keadaan = 'kerja', string $shift = 'siang', ?PolaRoster $pola = null): Roster
    {
        $pola ??= $this->pola();

        return Roster::withoutGlobalScopes()->create([
            'company_id'     => $this->c->id,
            'pekerja_id'     => $p->id,
            'pola_roster_id' => $pola->id,
            'tanggal'        => $tanggal->copy()->startOfDay(),
            'keadaan'        => $keadaan,
            'shift'          => $keadaan === 'kerja' ? $shift : null,
            'jam'            => $keadaan === 'kerja' ? $pola->jam : 0,
        ]);
    }

    /** Satu pindaian, ditulis lewat jalan yang sama dengan yang dipakai aplikasi. */
    private function jejak(Pekerja $p, string $saat, string $arah, array $ganti = []): AbsensiJejak
    {
        return AbsensiJejak::withoutGlobalScopes()->create($ganti + [
            'company_id' => $this->c->id,
            'pekerja_id' => $p->id,
            'terjadi'    => Waktu::simpan(Carbon::parse($saat, Waktu::zona())),
            'arah'       => $arah,
            'kunci'      => 'uji-'.uniqid(),
        ]);
    }

    private function jalankan(Pekerja $p, ?Carbon $dari = null, ?Carbon $sampai = null): array
    {
        return Rekonsiliasi::jalankan(
            [$p->id],
            $dari   ?? $this->hari->copy()->subDay(),
            $sampai ?? $this->hari->copy()->addDay(),
        );
    }

    private function baris(Pekerja $p, Carbon $tanggal): ?Absensi
    {
        return Absensi::withoutGlobalScopes()
            ->where('pekerja_id', $p->id)
            ->antara($tanggal->toDateString(), $tanggal->toDateString())
            ->first();
    }

    /* ═══════════════════ zona waktu ═══════════════════ */

    #[Test]
    public function test_jam_pindaian_terbaca_kembali_pada_jam_yang_sama(): void
    {
        /* Inti dari cacat delapan jam: yang ditulis pukul 07.02 WITA
           harus terbaca kembali pukul 07.02 WITA, bukan 15.02. */
        $p   = $this->pekerja();
        $tgl = $this->hari->toDateString();

        $this->roster($p, $this->hari);
        $this->jejak($p, $tgl.' 07:02', 'masuk');
        $this->jejak($p, $tgl.' 17:30', 'keluar');

        $this->jalankan($p);

        $a = $this->baris($p, $this->hari);

        $this->assertNotNull($a);
        $this->assertSame('07:02', Waktu::lokal($a->masuk)->format('H:i'));
        $this->assertSame('17:30', Waktu::lokal($a->keluar)->format('H:i'));
    }

    /* ═══════════════════ shift malam ═══════════════════ */

    #[Test]
    public function test_shift_malam_yang_melewati_tengah_malam_menjadi_satu_hari(): void
    {
        $p   = $this->pekerja();
        $tgl = $this->hari->toDateString();
        $esok= $this->hari->copy()->addDay()->toDateString();

        /* Pola 14:7 memulai shift malam pukul 18.00 dan berjam 11,
           sehingga selesainya pukul 05.00 keesokan harinya. */
        $this->roster($p, $this->hari, 'kerja', 'malam');

        $this->jejak($p, $tgl.' 17:55', 'masuk');
        $this->jejak($p, $esok.' 05:12', 'keluar');

        $this->jalankan($p);

        $a = $this->baris($p, $this->hari);

        $this->assertNotNull($a, 'Shift malam kehilangan barisnya sendiri.');
        $this->assertSame('hadir', $a->keadaan);
        $this->assertEqualsWithDelta(11.28, (float) $a->jam, 0.02);

        /* Dan TIDAK melahirkan baris kedua keesokan harinya. */
        $this->assertNull($this->baris($p, $this->hari->copy()->addDay()));
    }

    #[Test]
    public function test_tap_pulang_pagi_tidak_direbut_shift_siang_hari_itu(): void
    {
        /* Malam tanggal X lalu siang tanggal X+1 — pergantian yang
           benar-benar terjadi. Tap pulang pukul 05.10 tanggal X+1
           JARAKNYA jauh lebih dekat ke shift siang yang mulai 06.00
           daripada ke shift malam yang mulai 18.00 kemarin. Diputuskan
           menurut kedekatan, jam kerja semalam hilang dan orangnya
           tercatat pulang sebelum masuk. */
        $p    = $this->pekerja();
        $tgl  = $this->hari->toDateString();
        $esok = $this->hari->copy()->addDay()->toDateString();

        $this->roster($p, $this->hari, 'kerja', 'malam');
        $this->roster($p, $this->hari->copy()->addDay(), 'kerja', 'siang');

        $this->jejak($p, $tgl.' 17:50', 'masuk');
        $this->jejak($p, $esok.' 05:10', 'keluar');
        $this->jejak($p, $esok.' 05:55', 'masuk');
        $this->jejak($p, $esok.' 17:20', 'keluar');

        $this->jalankan($p, $this->hari->copy()->subDay(), $this->hari->copy()->addDays(2));

        $malam = $this->baris($p, $this->hari);
        $siang = $this->baris($p, $this->hari->copy()->addDay());

        $this->assertSame('hadir', $malam?->keadaan);
        $this->assertSame('05:10', Waktu::lokal($malam->keluar)->format('H:i'));

        $this->assertSame('hadir', $siang?->keadaan);
        $this->assertSame('05:55', Waktu::lokal($siang->masuk)->format('H:i'));
    }

    /* ═══════════════════ keterlambatan ═══════════════════ */

    #[Test]
    public function test_terlambat_melewati_toleransi_ditandai_terlambat(): void
    {
        $tgl = $this->hari->toDateString();

        /* Pola 14:7: mulai 06.00, toleransi 15 menit. */
        $lewat = $this->pekerja();
        $this->roster($lewat, $this->hari);
        $this->jejak($lewat, $tgl.' 06:40', 'masuk');
        $this->jejak($lewat, $tgl.' 17:10', 'keluar');
        $this->jalankan($lewat);

        $a = $this->baris($lewat, $this->hari);
        $this->assertSame('terlambat', $a->keadaan);
        $this->assertSame(40, $a->telat_menit);

        /* Di DALAM toleransi tetap hadir — tetapi menitnya tetap
           tercatat. Dibulatkan menjadi nol, tidak ada yang dapat
           melihat bahwa satu regu selalu datang empat belas menit
           lewat. */
        $pas = $this->pekerja();
        $this->roster($pas, $this->hari);
        $this->jejak($pas, $tgl.' 06:14', 'masuk');
        $this->jejak($pas, $tgl.' 17:10', 'keluar');
        $this->jalankan($pas);

        $b = $this->baris($pas, $this->hari);
        $this->assertSame('hadir', $b->keadaan);
        $this->assertSame(14, $b->telat_menit);
    }

    #[Test]
    public function test_datang_lebih_awal_bukan_keterlambatan_negatif(): void
    {
        /* Disimpan bertanda, rekap sebulan saling meniadakan: yang
           datang setengah jam awal menghapus keterlambatan setengah jam
           rekannya, dan jumlahnya nol pada regu yang separuhnya
           terlambat tiap hari. */
        $p   = $this->pekerja();
        $tgl = $this->hari->toDateString();

        $this->roster($p, $this->hari);
        $this->jejak($p, $tgl.' 05:20', 'masuk');
        $this->jejak($p, $tgl.' 17:10', 'keluar');
        $this->jalankan($p);

        $this->assertSame(0, $this->baris($p, $this->hari)->telat_menit);
    }

    /* ═══════════════════ keadaan ═══════════════════ */

    #[Test]
    public function test_tanpa_tap_pulang_menjadi_belum_pulang_dan_telatnya_tetap(): void
    {
        $p   = $this->pekerja();
        $tgl = $this->hari->toDateString();

        $this->roster($p, $this->hari);
        $this->jejak($p, $tgl.' 06:50', 'masuk');
        $this->jalankan($p);

        $a = $this->baris($p, $this->hari);

        $this->assertSame('belum_pulang', $a->keadaan);
        $this->assertSame(50, $a->telat_menit, 'Keterlambatan hilang bersama tap pulang yang tidak ada.');
        $this->assertEquals(0.0, (float) $a->jam);
    }

    #[Test]
    public function test_satu_tap_tidak_menjadi_masuk_sekaligus_keluar(): void
    {
        /* Alat pos jaga yang salah setel mengirim seluruh tapnya
           bertanda "keluar". Satu-satunya tap hari itu tidak boleh
           menjadi jam masuk DAN jam keluar sekaligus — jam kerjanya
           akan tercatat nol pada orang yang bekerja sebelas jam. */
        $p   = $this->pekerja();
        $tgl = $this->hari->toDateString();

        $this->roster($p, $this->hari);
        $this->jejak($p, $tgl.' 06:05', 'keluar');
        $this->jalankan($p);

        $a = $this->baris($p, $this->hari);

        $this->assertSame('belum_pulang', $a->keadaan);
        $this->assertSame('06:05', Waktu::lokal($a->masuk)->format('H:i'));
        $this->assertNull($a->keluar);
    }

    #[Test]
    public function test_hari_kerja_tanpa_pindaian_menjadi_absen(): void
    {
        $p = $this->pekerja();

        $this->roster($p, $this->hari);
        $this->jalankan($p);

        $this->assertSame('absen', $this->baris($p, $this->hari)?->keadaan);
    }

    #[Test]
    public function test_hari_kerja_yang_shiftnya_belum_selesai_belum_dinyatakan_absen(): void
    {
        /* Ketidakhadiran adalah KESIMPULAN, dan kesimpulan itu tidak
           dapat diambil sebelum shiftnya berakhir. Tanpa penjagaan ini,
           roster bulan depan yang sudah tersusun tampil sebagai ratusan
           hari mangkir yang belum terjadi. */
        $p    = $this->pekerja();
        $esok = Waktu::kini()->copy()->startOfDay()->addDay();

        $this->roster($p, $esok);
        $this->jalankan($p, $esok->copy()->subDay(), $esok->copy());

        $this->assertNull($this->baris($p, $esok));
    }

    #[Test]
    public function test_hari_libur_yang_kosong_tidak_menghasilkan_baris(): void
    {
        /* Dibuatkan baris, ia lahir berkeadaan "absen" — dan tiap hari
           libur terjadwal tercatat sebagai mangkir. Terjadi sungguhan
           pada data contoh pertama: 41 dari 44 "absen" sebenarnya
           adalah libur yang diambil dengan benar. */
        $p = $this->pekerja();

        $this->roster($p, $this->hari, 'libur');
        $this->jalankan($p);

        $this->assertNull($this->baris($p, $this->hari));
    }

    #[Test]
    public function test_bekerja_pada_hari_libur_menjadi_luar_roster(): void
    {
        $p   = $this->pekerja();
        $tgl = $this->hari->toDateString();

        $this->roster($p, $this->hari, 'libur');
        $this->jejak($p, $tgl.' 07:05', 'masuk');
        $this->jejak($p, $tgl.' 13:25', 'keluar');
        $this->jalankan($p);

        $a = $this->baris($p, $this->hari);

        $this->assertSame('luar_roster', $a->keadaan);
        $this->assertNull($a->telat_menit, 'Hari libur tidak punya jam mulai yang dapat dilanggar.');
        $this->assertEqualsWithDelta(6.33, (float) $a->jam, 0.02);
    }

#[Test]
    public function test_bekerja_pada_hari_libur_tanpa_tap_pulang_tetap_luar_roster(): void
    {
        /* "Di luar roster" menang atas "belum tap pulang". Yang perlu
           diketahui lebih dahulu bukanlah bahwa orangnya lupa menempel
           saat pulang, melainkan bahwa ia bekerja pada hari liburnya:
           jamnya tidak masuk hitungan tunjangan site, ikut menghitung
           batas empat belas hari berturut-turut, dan hampir selalu
           berarti ada lembur yang belum diperintahkan. Ditandai "belum
           pulang", ketiganya hilang sekaligus. */
        $p   = $this->pekerja();
        $tgl = $this->hari->toDateString();

        $this->roster($p, $this->hari, 'libur');
        $this->jejak($p, $tgl.' 07:05', 'masuk');
        $this->jalankan($p);

        $this->assertSame('luar_roster', $this->baris($p, $this->hari)?->keadaan);
    }

    #[Test]
    public function test_koreksi_jam_pulang_sama_dengan_jam_masuk_dibuang(): void
    {
        /* Satu saat tidak dapat menjadi jam masuk sekaligus jam pulang.
           Digeser sehari seperti shift malam, ia mencatat shift dua
           puluh empat jam pada orang yang menempel satu kali. */
        $p   = $this->pekerja();
        $tgl = $this->hari->toDateString();

        $this->roster($p, $this->hari);
        $this->jejak($p, $tgl.' 06:05', 'masuk');
        $this->jalankan($p);

        $a = $this->baris($p, $this->hari);

        $this->actingAs($this->admin)
            ->put('/absensi/'.$a->id, [
                'masuk'  => '06:05',
                'keluar' => '06:05',
                'alasan' => 'Alat menempel dua kali pada saat yang sama.',
            ])->assertRedirect();

        $segar = $a->fresh();

        $this->assertNull($segar->keluar);
        $this->assertEquals(0.0, (float) $segar->jam);
        $this->assertSame('belum_pulang', $segar->keadaan);
    }

    #[Test]
    public function test_pindaian_tanpa_roster_sama_sekali_tetap_tercatat(): void
    {
        $p   = $this->pekerja();
        $tgl = $this->hari->toDateString();

        $this->jejak($p, $tgl.' 07:00', 'masuk');
        $this->jejak($p, $tgl.' 16:00', 'keluar');
        $this->jalankan($p);

        $this->assertSame('luar_roster', $this->baris($p, $this->hari)?->keadaan);
    }

    /* ═══════════════════ area ═══════════════════ */

    #[Test]
    public function test_satu_tap_di_luar_area_menjatuhkan_seluruh_harinya(): void
    {
        /* Diambil dari tap masuk saja, absen ponsel dari rumah pada jam
           pulang tidak pernah muncul di mana pun. */
        $p   = $this->pekerja();
        $tgl = $this->hari->toDateString();

        $this->roster($p, $this->hari);
        $this->jejak($p, $tgl.' 06:05', 'masuk', ['dalam_area' => true]);
        $this->jejak($p, $tgl.' 17:05', 'keluar', ['dalam_area' => false, 'sumber' => 'ponsel']);
        $this->jalankan($p);

        $a = $this->baris($p, $this->hari);

        $this->assertFalse((bool) $a->dalam_area);
        $this->assertSame('campuran', $a->sumber);
    }

    /* ═══════════════════ penyusunan ulang ═══════════════════ */

    #[Test]
    public function test_rekonsiliasi_ulang_tidak_menggandakan(): void
    {
        $p   = $this->pekerja();
        $tgl = $this->hari->toDateString();

        $this->roster($p, $this->hari);
        $this->jejak($p, $tgl.' 06:05', 'masuk');
        $this->jejak($p, $tgl.' 17:05', 'keluar');

        $this->jalankan($p);
        $this->jalankan($p);
        $this->jalankan($p);

        $this->assertSame(1, Absensi::withoutGlobalScopes()->where('pekerja_id', $p->id)->count());
    }

    #[Test]
    public function test_baris_yang_sudah_dikoreksi_tidak_ditimpa(): void
    {
        $p   = $this->pekerja();
        $tgl = $this->hari->toDateString();

        $this->roster($p, $this->hari);
        $this->jejak($p, $tgl.' 06:05', 'masuk');
        $this->jalankan($p);

        $a = $this->baris($p, $this->hari);
        $a->forceFill([
            'keluar'    => Waktu::simpan(Carbon::parse($tgl.' 17:00', Waktu::zona())),
            'jam'       => 10.92,
            'keadaan'   => 'hadir',
            'dikoreksi' => true,
        ])->save();

        /* Batch luring yang datang terlambat. */
        $this->jejak($p, $tgl.' 06:06', 'masuk');
        $this->jalankan($p);

        $segar = $this->baris($p, $this->hari);

        $this->assertSame('hadir', $segar->keadaan, 'Koreksi pengawas tertimpa jejak yang datang menyusul.');
        $this->assertEqualsWithDelta(10.92, (float) $segar->jam, 0.01);
    }

    /* ═══════════════════ endpoint mesin ═══════════════════ */

    private function mesin(array $ganti = []): array
    {
        $m = MesinAbsensi::withoutGlobalScopes()->create($ganti + [
            'company_id' => $this->c->id,
            'nama'       => 'Pos Uji',
            'nomor_seri' => 'SERI-'.uniqid(),
            'aktif'      => true,
        ]);

        return [$m, $m->terbitkanToken()];
    }

    private function kirim(MesinAbsensi $m, ?string $token, array $jejak): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/absensi', ['jejak' => $jejak], array_filter([
            'X-Mesin-Seri'  => $m->nomor_seri,
            'Authorization' => $token ? 'Bearer '.$token : null,
        ]));
    }

    private function peristiwa(Pekerja $p, string $saat, string $arah, string $kunci): array
    {
        return [
            'kunci'   => $kunci,
            'pekerja' => $p->no_registrasi,
            'terjadi' => Carbon::parse($saat, Waktu::zona())->toIso8601String(),
            'arah'    => $arah,
        ];
    }

    #[Test]
    public function test_kiriman_tanpa_token_ditolak(): void
    {
        [$m] = $this->mesin();
        $p   = $this->pekerja();

        $this->kirim($m, null, [$this->peristiwa($p, $this->hari->toDateString().' 06:00', 'masuk', 'k1')])
            ->assertStatus(401);

        $this->assertSame(0, AbsensiJejak::withoutGlobalScopes()->count());
    }

    #[Test]
    public function test_kiriman_bertoken_salah_ditolak(): void
    {
        [$m] = $this->mesin();
        $p   = $this->pekerja();

        $this->kirim($m, 'token-yang-salah', [$this->peristiwa($p, $this->hari->toDateString().' 06:00', 'masuk', 'k1')])
            ->assertStatus(401);

        $this->assertSame(0, AbsensiJejak::withoutGlobalScopes()->count());
    }

    #[Test]
    public function test_mesin_nonaktif_ditolak_dengan_pesan_yang_sama(): void
    {
        /* Dibedakan, siapa pun yang memegang alat curian dapat
           mengetahui apakah nomor serinya masih terdaftar. */
        [$mati, $tokenMati] = $this->mesin(['aktif' => false]);
        [$hantu] = $this->mesin(['nomor_seri' => 'TIDAK-ADA-'.uniqid()]);
        $p = $this->pekerja();

        $satu = $this->kirim($mati, $tokenMati, [$this->peristiwa($p, $this->hari->toDateString().' 06:00', 'masuk', 'k1')]);
        $dua  = $this->kirim($hantu, 'apa-saja', [$this->peristiwa($p, $this->hari->toDateString().' 06:00', 'masuk', 'k2')]);

        $satu->assertStatus(401);
        $dua->assertStatus(401);

        $this->assertSame(
            $satu->json('message'),
            $dua->json('message'),
            'Pesan yang berbeda memberi tahu penyerang bahwa nomor serinya masih terdaftar.',
        );
    }

    #[Test]
    public function test_kunci_yang_sama_dikirim_dua_kali_tidak_menggandakan(): void
    {
        [$m, $token] = $this->mesin();
        $p = $this->pekerja();
        $this->roster($p, $this->hari);

        $batch = [
            $this->peristiwa($p, $this->hari->toDateString().' 06:03', 'masuk', 'POS-1'),
            $this->peristiwa($p, $this->hari->toDateString().' 17:05', 'keluar', 'POS-2'),
        ];

        $this->kirim($m, $token, $batch)->assertOk()->assertJsonPath('diterima', 2);
        $this->kirim($m, $token, $batch)->assertOk()->assertJsonPath('terulang', 2);
        $this->kirim($m, $token, $batch)->assertOk()->assertJsonPath('diterima', 0);

        $this->assertSame(2, AbsensiJejak::withoutGlobalScopes()->count());
        $this->assertSame(1, Absensi::withoutGlobalScopes()->count());
    }

    #[Test]
    public function test_kunci_berulang_di_dalam_satu_batch_tidak_menggandakan(): void
    {
        /* Batasan unik akan menangkapnya sebagai galat 500 di tengah
           transaksi — dan alat yang menerima 500 mengirim ulang,
           selamanya. */
        [$m, $token] = $this->mesin();
        $p = $this->pekerja();

        $this->kirim($m, $token, [
            $this->peristiwa($p, $this->hari->toDateString().' 06:03', 'masuk', 'SAMA'),
            $this->peristiwa($p, $this->hari->toDateString().' 06:03', 'masuk', 'SAMA'),
        ])->assertOk()->assertJsonPath('diterima', 1)->assertJsonPath('terulang', 1);

        $this->assertSame(1, AbsensiJejak::withoutGlobalScopes()->count());
    }

    #[Test]
    public function test_mesin_tidak_dapat_menulis_absensi_perusahaan_lain(): void
    {
        /* Tanpa pengguna, global scope MilikPerusahaan tidak menyaring
           apa pun — batasnya harus dipasang tangan pada endpoint ini. */
        $lain = Company::create(['name' => 'PT Sebelah']);
        $asing = Pekerja::withoutGlobalScopes()->create([
            'company_id'    => $lain->id,
            'nama'          => 'Orang Sebelah',
            'no_registrasi' => 'REG-SEBELAH',
            'status'        => 'aktif',
        ]);

        [$m, $token] = $this->mesin();

        $this->kirim($m, $token, [$this->peristiwa($asing, $this->hari->toDateString().' 06:00', 'masuk', 'k1')])
            ->assertStatus(207)
            ->assertJsonPath('diterima', 0);

        $this->assertSame(0, AbsensiJejak::withoutGlobalScopes()->count());
    }

    #[Test]
    public function test_satu_baris_buruk_tidak_menggagalkan_seluruh_batch(): void
    {
        /* Alat mengirim ulang sampai berhasil; satu nomor induk yang
           tidak dikenal akan menggantung SELURUH hari itu selamanya. */
        [$m, $token] = $this->mesin();
        $p = $this->pekerja();

        $hasil = $this->kirim($m, $token, [
            $this->peristiwa($p, $this->hari->toDateString().' 06:03', 'masuk', 'BAIK'),
            [
                'kunci'   => 'BURUK',
                'pekerja' => 'NOMOR-YANG-TIDAK-ADA',
                'terjadi' => Carbon::parse($this->hari->toDateString().' 06:04', Waktu::zona())->toIso8601String(),
                'arah'    => 'masuk',
            ],
        ]);

        $hasil->assertStatus(207)
            ->assertJsonPath('diterima', 1)
            ->assertJsonPath('ditolak.0.kunci', 'BURUK');

        $this->assertSame(1, AbsensiJejak::withoutGlobalScopes()->count());
    }

    #[Test]
    public function test_kiriman_mesin_langsung_terekonsiliasi(): void
    {
        [$m, $token] = $this->mesin();
        $p = $this->pekerja();
        $this->roster($p, $this->hari);

        $this->kirim($m, $token, [
            $this->peristiwa($p, $this->hari->toDateString().' 06:05', 'masuk', 'A'),
            $this->peristiwa($p, $this->hari->toDateString().' 17:05', 'keluar', 'B'),
        ])->assertOk();

        $a = $this->baris($p, $this->hari);

        $this->assertSame('hadir', $a?->keadaan);
        $this->assertSame('06:05', Waktu::lokal($a->masuk)->format('H:i'));
    }

    #[Test]
    public function test_hash_token_tidak_pernah_terserialkan(): void
    {
        [$m] = $this->mesin();

        $this->assertArrayNotHasKey('token_hash', $m->fresh()->toArray());
        $this->assertStringNotContainsString('token_hash', $m->fresh()->toJson());
    }

    /* ═══════════════════ layar dan otorisasi ═══════════════════ */

    #[Test]
    public function test_tiap_halaman_absensi_terbuka(): void
    {
        $this->actingAs($this->admin);

        foreach (['/absensi', '/absensi/rekap', '/absensi/mesin'] as $alamat) {
            $this->get($alamat)->assertOk();
        }
    }

    #[Test]
    public function test_koreksi_menandai_barisnya_dan_menghitung_ulang(): void
    {
        $p   = $this->pekerja();
        $tgl = $this->hari->toDateString();

        $this->roster($p, $this->hari);
        $this->jejak($p, $tgl.' 06:05', 'masuk');
        $this->jalankan($p);

        $a = $this->baris($p, $this->hari);
        $this->assertSame('belum_pulang', $a->keadaan);

        $this->actingAs($this->admin)
            ->put('/absensi/'.$a->id, [
                'masuk'  => '06:05',
                'keluar' => '17:05',
                'alasan' => 'Mesin pos dua mati; jam dicatat pengawas shift.',
            ])->assertRedirect();

        $segar = $a->fresh();

        $this->assertTrue((bool) $segar->dikoreksi);
        $this->assertSame($this->admin->id, $segar->dikoreksi_oleh);
        $this->assertSame('hadir', $segar->keadaan);
        $this->assertEqualsWithDelta(11.0, (float) $segar->jam, 0.01);
    }

    #[Test]
    public function test_koreksi_jam_pulang_lebih_awal_dibaca_sebagai_lewat_tengah_malam(): void
    {
        /* Shift malam yang menyeberang tengah malam adalah keadaan
           biasa, bukan salah ketik. Dibiarkan pada hari yang sama,
           sebelas jam kerja tercatat menjadi minus tiga belas. */
        $p   = $this->pekerja();
        $tgl = $this->hari->toDateString();

        $this->roster($p, $this->hari, 'kerja', 'malam');
        $this->jejak($p, $tgl.' 17:58', 'masuk');
        $this->jalankan($p);

        $a = $this->baris($p, $this->hari);

        $this->actingAs($this->admin)
            ->put('/absensi/'.$a->id, [
                'masuk'  => '18:00',
                'keluar' => '05:00',
                'alasan' => 'Tap pulang tidak terbaca alat.',
            ])->assertRedirect();

        $this->assertEqualsWithDelta(11.0, (float) $a->fresh()->jam, 0.01);
    }

    #[Test]
    public function test_pencatatan_manual_tersimpan_sebagai_jejak_bersumber_manual(): void
    {
        /* Ditulis langsung ke catatan harian, catatan tangan pengawas
           tidak dapat dibedakan dari pindaian alat — dan pertanyaan
           auditor kehilangan jawabannya justru pada baris yang paling
           mungkin dipersoalkan. */
        $p = $this->pekerja();
        $this->roster($p, $this->hari);

        $this->actingAs($this->admin)->post('/absensi/catat', [
            'pekerja_id' => $p->id,
            'tanggal'    => $this->hari->toDateString(),
            'jam'        => '06:10',
            'arah'       => 'masuk',
        ])->assertRedirect();

        $j = AbsensiJejak::withoutGlobalScopes()->firstOrFail();

        $this->assertSame('manual', $j->sumber);
        $this->assertSame('06:10', Waktu::lokal($j->terjadi)->format('H:i'));
        $this->assertSame('belum_pulang', $this->baris($p, $this->hari)?->keadaan);
    }

    #[Test]
    public function test_bukan_admin_tidak_dapat_menghapus_mesin_atau_menerbitkan_token(): void
    {
        [$m] = $this->mesin();

        $this->actingAs($this->pengguna)->post('/absensi/mesin/'.$m->id.'/token')->assertForbidden();
        $this->actingAs($this->pengguna)->delete('/absensi/mesin/'.$m->id)->assertForbidden();

        $this->assertNotNull(MesinAbsensi::withoutGlobalScopes()->find($m->id));
    }

    #[Test]
    public function test_token_baru_menggugurkan_yang_lama(): void
    {
        [$m, $lama] = $this->mesin();

        $this->actingAs($this->admin)->post('/absensi/mesin/'.$m->id.'/token')->assertRedirect();

        $this->assertFalse($m->fresh()->tokenCocok($lama));
    }
}
