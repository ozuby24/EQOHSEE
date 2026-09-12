<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Hr\{Absensi, Lembur, PolaRoster, Roster, Upah};
use App\Models\Miners\{Jabatan, Pekerja};
use App\Models\User;
use App\Support\Hr\{JalurLembur, MasterRoster, UpahLembur};
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Lembur (SPL) menurut PP 35/2021.
 *
 * PERHITUNGANNYA DIUJI TERHADAP ANGKA YANG DIHITUNG TANGAN, bukan
 * terhadap keluaran kodenya sendiri. Uji yang membandingkan hasil
 * dengan hasil akan lulus atas rumus yang salah selama salahnya
 * konsisten — dan risiko kepatuhannya tinggi: yang menyengketakan upah
 * lembur menyebut pasalnya, dan jawaban sistem harus dapat disandingkan
 * dengannya baris demi baris.
 *
 * Dua hal di sini sengaja BERBEDA dari penyederhanaan yang lazim, dan
 * keduanya merugikan pekerja bila disederhanakan:
 *
 *   · Dasar 75% TIDAK berlaku hanya karena ada tunjangan tidak tetap.
 *     Pasal 32 ayat (3) menuntut pokok + tunjangan tetap KURANG DARI
 *     75% keseluruhan upah.
 *
 *   · Batas 4 jam sehari TIDAK berlaku pada hari libur — pasal 29
 *     ayat (2) menyebutnya tegas.
 */
class LemburTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $pengaju;
    private User $atasan;
    private Carbon $hari;

    protected function setUp(): void
    {
        parent::setUp();

        MasterRoster::pasang();

        $this->c       = Company::create(['name' => 'PT Uji Lembur']);
        $this->pengaju = User::factory()->create(['company_id' => $this->c->id]);
        $this->atasan  = User::factory()->create(['company_id' => $this->c->id, 'is_admin' => true]);

        $this->hari = Waktu::kini()->startOfDay()->subDays(5);
    }

    /* ═══════════════════ rumus ═══════════════════ */

    #[Test]
    public function test_upah_sejam_adalah_satu_per_seratus_tujuh_puluh_tiga(): void
    {
        /* Pasal 32 ayat (1). */
        $this->assertSame(173, UpahLembur::PEMBAGI_JAM);
        $this->assertEqualsWithDelta(5_000_000 / 173, UpahLembur::upahSejam(5_000_000), 0.01);
    }

    #[Test]
    public function test_lembur_hari_kerja_memakai_faktor_satu_setengah_lalu_dua(): void
    {
        /* Pasal 31 ayat (1): jam pertama 1,5x, jam kedua dan
           seterusnya 2x. Tiga jam = (1 x 1,5) + (2 x 2) = 5,5 x upah
           sejam. */
        $h = UpahLembur::hitung(3, 5_000_000, 0, 0, 'kerja');

        $this->assertEqualsWithDelta(5.5 * (5_000_000 / 173), $h['nilai'], 1);

        $this->assertSame(1.0, $h['rincian'][0]['jam']);
        $this->assertSame(1.5, $h['rincian'][0]['faktor']);
        $this->assertSame(2.0, $h['rincian'][1]['jam']);
        $this->assertSame(2.0, $h['rincian'][1]['faktor']);
    }

    #[Test]
    public function test_satu_jam_lembur_tidak_pernah_dihitung_dua_kali_lipat(): void
    {
        /* Jam PERTAMA saja yang 1,5x. Dibulatkan menjadi seluruhnya 2x,
           tiap lembur satu jam dibayar sepertiga lebih mahal dari yang
           diatur. */
        $h = UpahLembur::hitung(1, 5_000_000, 0, 0, 'kerja');

        $this->assertCount(1, $h['rincian']);
        $this->assertEqualsWithDelta(1.5 * (5_000_000 / 173), $h['nilai'], 1);
    }

    #[Test]
    public function test_hari_libur_pola_enam_hari_kerja(): void
    {
        /* Pasal 31 ayat (2) huruf a: jam ke-1 s.d. ke-7 2x, jam ke-8
           3x, jam ke-9 dan ke-10 4x. Sepuluh jam = 14 + 3 + 8 = 25. */
        $h = UpahLembur::hitung(10, 5_000_000, 0, 0, 'libur', 6);

        $this->assertEqualsWithDelta(25 * (5_000_000 / 173), $h['nilai'], 1);

        $this->assertSame([7.0, 1.0, 2.0], array_column($h['rincian'], 'jam'));
        $this->assertSame([2.0, 3.0, 4.0], array_column($h['rincian'], 'faktor'));
    }

    #[Test]
    public function test_hari_libur_pola_lima_hari_kerja(): void
    {
        /* Pasal 31 ayat (2) huruf b: jam ke-1 s.d. ke-8 2x, jam ke-9
           3x, jam ke-10 dan ke-11 4x. Sebelas jam = 16 + 3 + 8 = 27. */
        $h = UpahLembur::hitung(11, 5_000_000, 0, 0, 'libur', 5);

        $this->assertEqualsWithDelta(27 * (5_000_000 / 173), $h['nilai'], 1);
        $this->assertSame([8.0, 1.0, 2.0], array_column($h['rincian'], 'jam'));
    }

    #[Test]
    public function test_jam_yang_melampaui_tabel_tetap_dihitung(): void
    {
        /* Pada tingkat terakhir, seluruh sisanya masuk. Dipotong pada
           batas tabel, pekerja yang lembur dua belas jam dibayar
           sepuluh — dan jamnya hilang tanpa jejak. */
        $h = UpahLembur::hitung(12, 5_000_000, 0, 0, 'libur', 6);

        $this->assertSame(12.0, array_sum(array_column($h['rincian'], 'jam')));
        $this->assertEqualsWithDelta((14 + 3 + 16) * (5_000_000 / 173), $h['nilai'], 1);
    }

    /* ═══════════════════ dasar 100% atau 75% ═══════════════════ */

    #[Test]
    public function test_tanpa_tunjangan_tidak_tetap_dasarnya_penuh(): void
    {
        /* Pasal 32 ayat (2). */
        $d = UpahLembur::dasar(4_000_000, 1_000_000, 0);

        $this->assertSame(100, $d['persen']);
        $this->assertEqualsWithDelta(5_000_000, $d['upah'], 0.01);
    }

    #[Test]
    public function test_tunjangan_tidak_tetap_kecil_tidak_menurunkan_dasarnya(): void
    {
        /* INI YANG PALING SERING DISEDERHANAKAN KELIRU. Pokok +
           tunjangan tetap 4.500.000 dari 5.000.000 adalah 90% —
           di atas ambang 75%, jadi dasarnya tetap penuh atas 4.500.000.
           Disederhanakan menjadi "ada tunjangan tidak tetap maka 75%",
           orang ini kehilangan seperenam upah lemburnya tiap bulan. */
        $d = UpahLembur::dasar(4_000_000, 500_000, 500_000);

        $this->assertSame(100, $d['persen']);
        $this->assertEqualsWithDelta(4_500_000, $d['upah'], 0.01);
    }

    #[Test]
    public function test_tunjangan_tidak_tetap_besar_menurunkan_dasarnya(): void
    {
        /* Pasal 32 ayat (3): pokok + tunjangan tetap 3.200.000 dari
           5.000.000 adalah 64% — di bawah 75%, jadi dasarnya 75% dari
           keseluruhan = 3.750.000. */
        $d = UpahLembur::dasar(3_000_000, 200_000, 1_800_000);

        $this->assertSame(75, $d['persen']);
        $this->assertEqualsWithDelta(3_750_000, $d['upah'], 0.01);
    }

    #[Test]
    public function test_upah_kosong_tidak_membagi_dengan_nol(): void
    {
        $h = UpahLembur::hitung(3, 0, 0, 0, 'kerja');

        $this->assertSame(0.0, $h['nilai']);
        $this->assertSame(0.0, $h['upah_sejam']);
    }

    /* ═══════════════════ batas jam ═══════════════════ */

    #[Test]
    public function test_lebih_dari_empat_jam_sehari_ditandai(): void
    {
        $this->assertNull(UpahLembur::melebihi(4, 'kerja'));
        $this->assertNotNull(UpahLembur::melebihi(4.5, 'kerja'));
    }

    #[Test]
    public function test_batas_harian_tidak_berlaku_pada_hari_libur(): void
    {
        /* Pasal 29 ayat (2) menyebutnya tegas: batas 4 jam dan 18 jam
           "tidak termasuk waktu kerja lembur pada waktu istirahat
           mingguan dan/atau hari libur resmi". Diberlakukan pula,
           seluruh lembur hari Minggu ditandai melanggar. */
        $this->assertNull(UpahLembur::melebihi(10, 'libur'));
    }

    #[Test]
    public function test_batas_mingguan_tidak_menghitung_hari_libur(): void
    {
        $baris = [
            ['jam' => 4, 'jenis_hari' => 'kerja'],
            ['jam' => 4, 'jenis_hari' => 'kerja'],
            ['jam' => 4, 'jenis_hari' => 'kerja'],
            ['jam' => 4, 'jenis_hari' => 'kerja'],
            ['jam' => 10, 'jenis_hari' => 'libur'],
        ];

        $this->assertNull(UpahLembur::melebihiMinggu($baris), '16 jam hari kerja ditandai melanggar.');

        $baris[] = ['jam' => 3, 'jenis_hari' => 'kerja'];

        $this->assertNotNull(UpahLembur::melebihiMinggu($baris));
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

    private function upah(Pekerja $p, float $pokok = 5_000_000, float $tetap = 0, float $tidakTetap = 0): Upah
    {
        return Upah::withoutGlobalScopes()->create([
            'company_id'            => $this->c->id,
            'pekerja_id'            => $p->id,
            'berlaku_mulai'         => $this->hari->copy()->subYear(),
            'pokok'                 => $pokok,
            'tunjangan_tetap'       => $tetap,
            'tunjangan_tidak_tetap' => $tidakTetap,
        ]);
    }

    private function hadir(Pekerja $p, float $jam, string $keadaan = 'hadir', ?int $jadwal = 11): Absensi
    {
        $roster = null;

        if ($jadwal !== null) {
            $roster = Roster::withoutGlobalScopes()->create([
                'company_id'     => $this->c->id,
                'pekerja_id'     => $p->id,
                'pola_roster_id' => PolaRoster::withoutGlobalScopes()->where('kode', '14:7')->value('id'),
                'tanggal'        => $this->hari->copy(),
                'keadaan'        => $keadaan === 'luar_roster' ? 'libur' : 'kerja',
                'shift'          => $keadaan === 'luar_roster' ? null : 'siang',
                'jam'            => $keadaan === 'luar_roster' ? 0 : $jadwal,
            ]);
        }

        return Absensi::withoutGlobalScopes()->create([
            'company_id' => $this->c->id,
            'pekerja_id' => $p->id,
            'roster_id'  => $roster?->id,
            'tanggal'    => $this->hari->copy(),
            'keadaan'    => $keadaan,
            'jam'        => $jam,
        ]);
    }

    private function usulkan(Pekerja $p): array
    {
        return JalurLembur::usulkan([$p->id], $this->hari->copy(), $this->hari->copy(), $this->pengaju->id);
    }

    /* ═══════════════════ usulan dari absensi ═══════════════════ */

    #[Test]
    public function test_lembur_diusulkan_dari_selisih_jam_terhadap_jadwal(): void
    {
        $p = $this->pekerja();
        $this->upah($p);
        $this->hadir($p, 14, 'hadir', 11);

        $this->assertSame(1, $this->usulkan($p)['dibuat']);

        $l = Lembur::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(3.0, $l->jam, 'Selisih 14 jam tercatat terhadap 11 jam jadwal.');
        $this->assertSame('kerja', $l->jenis_hari);
    }

    #[Test]
    public function test_hari_di_luar_roster_seluruh_jamnya_lembur_hari_libur(): void
    {
        /* Tidak ada jadwal yang dapat dikurangkan — itulah arti "di
           luar roster". Dikurangi jadwal yang nol pun hasilnya sama,
           tetapi JENIS HARINYA yang menentukan tabel faktornya. */
        $p = $this->pekerja();
        $this->upah($p);
        $this->hadir($p, 8, 'luar_roster');

        $this->assertSame(1, $this->usulkan($p)['dibuat']);

        $l = Lembur::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(8.0, $l->jam);
        $this->assertSame('libur', $l->jenis_hari);
        $this->assertEqualsWithDelta((7 * 2 + 1 * 3) * (5_000_000 / 173), $l->nilai, 1);
    }

    #[Test]
    public function test_selisih_di_bawah_ambang_tidak_diusulkan(): void
    {
        /* Tap yang terlambat tiga menit akan melahirkan usulan lembur
           tiga menit pada tiap orang tiap hari — ratusan baris yang
           tidak seorang pun bermaksud mengajukannya. */
        $p = $this->pekerja();
        $this->upah($p);
        $this->hadir($p, 11.2, 'hadir', 11);

        $this->assertSame(0, $this->usulkan($p)['dibuat']);
    }

    #[Test]
    public function test_jabatan_yang_dikecualikan_tidak_diusulkan(): void
    {
        /* PP 35/2021 pasal 27 ayat (4). */
        $jab = Jabatan::withoutGlobalScopes()->create([
            'nama' => 'Site Manager', 'kecuali_lembur' => true,
        ]);

        $p = $this->pekerja(['jabatan_id' => $jab->id]);
        $this->upah($p);
        $this->hadir($p, 15, 'hadir', 11);

        $this->assertSame(0, $this->usulkan($p)['dibuat']);
    }

    #[Test]
    public function test_tanpa_upah_tercatat_usulannya_tetap_dibuat_bernilai_nol(): void
    {
        /* Dilewati diam-diam, lembur yang benar-benar dikerjakan hilang
           tanpa jejak hanya karena barisnya belum diisi bagian
           personalia. */
        $p = $this->pekerja();
        $this->hadir($p, 14, 'hadir', 11);

        $n = $this->usulkan($p);

        $this->assertSame(1, $n['dibuat']);
        $this->assertSame(1, $n['tanpa_upah']);

        $l = Lembur::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(3.0, $l->jam);
        $this->assertSame(0.0, $l->nilai);
    }

    #[Test]
    public function test_usulan_berulang_tidak_menggandakan(): void
    {
        $p = $this->pekerja();
        $this->upah($p);
        $this->hadir($p, 14, 'hadir', 11);

        $this->usulkan($p);
        $kedua = $this->usulkan($p);

        $this->assertSame(0, $kedua['dibuat']);
        $this->assertSame(1, $kedua['dilewati']);
        $this->assertSame(1, Lembur::withoutGlobalScopes()->count());
    }

    /* ═══════════════════ alur ═══════════════════ */

    #[Test]
    public function test_persetujuan_menghitung_ulang_dengan_upah_yang_berlaku(): void
    {
        /* Di antara usulan dan persetujuan, upah orang itu dapat
           diperbaiki bagian personalia — dan angka yang lama membayar
           lembur dengan upah yang sudah diketahui salah. */
        $p = $this->pekerja();
        $this->upah($p, 5_000_000);
        $this->hadir($p, 14, 'hadir', 11);

        $this->usulkan($p);

        $l = Lembur::withoutGlobalScopes()->firstOrFail();
        $this->assertEqualsWithDelta(5.5 * (5_000_000 / 173), $l->nilai, 1);

        /* Koreksi upah, berlaku sejak sebelum tanggal lemburnya. */
        Upah::withoutGlobalScopes()->where('pekerja_id', $p->id)
            ->update(['pokok' => 8_000_000]);

        $this->assertNull(JalurLembur::setujui($l->fresh(), $this->atasan));

        $this->assertEqualsWithDelta(5.5 * (8_000_000 / 173), $l->fresh()->nilai, 1);
    }

    #[Test]
    public function test_pengaju_tidak_dapat_menyetujui_sendiri(): void
    {
        $p = $this->pekerja();
        $this->upah($p);
        $this->hadir($p, 14, 'hadir', 11);
        $this->usulkan($p);

        $l = Lembur::withoutGlobalScopes()->firstOrFail();

        $this->assertNotNull(JalurLembur::setujui($l, $this->pengaju));
        $this->assertSame('menunggu', $l->fresh()->status);
    }

    #[Test]
    public function test_jabatan_yang_dikecualikan_ditolak_saat_disetujui(): void
    {
        /* Lapis kedua: jabatannya dapat berubah SESUDAH usulannya
           lahir, dan yang menyetujui tidak selalu memeriksanya. */
        $p = $this->pekerja();
        $this->upah($p);
        $this->hadir($p, 14, 'hadir', 11);
        $this->usulkan($p);

        $jab = Jabatan::withoutGlobalScopes()->create([
            'nama' => 'Kepala Teknik', 'kecuali_lembur' => true,
        ]);

        $p->forceFill(['jabatan_id' => $jab->id])->save();

        $l = Lembur::withoutGlobalScopes()->firstOrFail();

        $this->assertNotNull(JalurLembur::setujui($l, $this->atasan));
        $this->assertSame('menunggu', $l->fresh()->status);
    }

    #[Test]
    public function test_penolakan_dan_pembatalan_menolkan_nilainya(): void
    {
        /* Nilainya tetap tersimpan, rekap upah lembur bulan itu
           menjumlahkan perintah yang justru ditolak. */
        $p = $this->pekerja();
        $this->upah($p);
        $this->hadir($p, 14, 'hadir', 11);
        $this->usulkan($p);

        $l = Lembur::withoutGlobalScopes()->firstOrFail();

        $this->assertNull(JalurLembur::tolak($l, $this->atasan, 'Tidak diperintahkan.'));

        $this->assertSame('ditolak', $l->fresh()->status);
        $this->assertSame(0.0, $l->fresh()->nilai);
    }

    #[Test]
    public function test_yang_sudah_ditindak_tidak_dapat_ditindak_lagi(): void
    {
        $p = $this->pekerja();
        $this->upah($p);
        $this->hadir($p, 14, 'hadir', 11);
        $this->usulkan($p);

        $l = Lembur::withoutGlobalScopes()->firstOrFail();

        $this->assertNull(JalurLembur::tolak($l, $this->atasan, 'Tidak diperintahkan.'));
        $this->assertNotNull(JalurLembur::setujui($l->fresh(), $this->atasan));
        $this->assertSame('ditolak', $l->fresh()->status);
    }

    #[Test]
    public function test_peringatan_menyebut_batas_yang_dilampaui(): void
    {
        $p = $this->pekerja();
        $this->upah($p);
        $this->hadir($p, 17, 'hadir', 11);
        $this->usulkan($p);

        $l = Lembur::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(6.0, $l->jam);

        $w = JalurLembur::peringatan($l);

        $this->assertNotEmpty($w);
        $this->assertStringContainsString('4 jam sehari', implode(' ', $w));
    }

    #[Test]
    public function test_upah_belum_tercatat_ikut_diperingatkan(): void
    {
        $p = $this->pekerja();
        $this->hadir($p, 14, 'hadir', 11);
        $this->usulkan($p);

        $w = JalurLembur::peringatan(Lembur::withoutGlobalScopes()->firstOrFail());

        $this->assertStringContainsString('Upah pekerja ini belum tercatat', implode(' ', $w));
    }

    /* ═══════════════════ upah bertanggal ═══════════════════ */

    #[Test]
    public function test_upah_yang_berlaku_adalah_yang_terakhir_sebelum_tanggalnya(): void
    {
        /* Upah naik, dan lembur bulan lalu harus tetap terhitung dengan
           upah yang berlaku saat itu. */
        $p = $this->pekerja();

        $this->upah($p, 5_000_000);

        Upah::withoutGlobalScopes()->create([
            'company_id'    => $this->c->id,
            'pekerja_id'    => $p->id,
            'berlaku_mulai' => $this->hari->copy()->addDays(3),
            'pokok'         => 9_000_000,
        ]);

        $this->assertSame(5_000_000.0, Upah::pada($p, $this->hari)->pokok);
        $this->assertSame(9_000_000.0, Upah::pada($p, $this->hari->copy()->addDays(5))->pokok);
    }

    #[Test]
    public function test_upah_yang_berlaku_hari_itu_juga_sudah_terbaca(): void
    {
        /* Dibandingkan sebagai saat terhadap Carbon berzona WITA, upah
           yang berlaku mulai hari ini belum terbaca sampai pukul
           delapan pagi. */
        $p = $this->pekerja();

        Upah::withoutGlobalScopes()->create([
            'company_id'    => $this->c->id,
            'pekerja_id'    => $p->id,
            'berlaku_mulai' => $this->hari->copy(),
            'pokok'         => 7_000_000,
        ]);

        $this->assertSame(7_000_000.0, Upah::pada($p, $this->hari)?->pokok);
    }

    /* ═══════════════════ layar ═══════════════════ */

    #[Test]
    public function test_tiap_halaman_lembur_terbuka(): void
    {
        $this->actingAs($this->atasan);

        foreach (['/hris/lembur', '/hris/lembur/upah'] as $alamat) {
            $this->get($alamat)->assertOk();
        }
    }

    #[Test]
    public function test_upah_disimpan_lewat_layar_tanpa_menimpa_riwayatnya(): void
    {
        $p = $this->pekerja();

        $this->actingAs($this->atasan)->post('/hris/lembur/upah', [
            'pekerja_id'    => $p->id,
            'berlaku_mulai' => $this->hari->copy()->subMonths(6)->toDateString(),
            'pokok'         => 5_000_000,
        ])->assertRedirect();

        $this->actingAs($this->atasan)->post('/hris/lembur/upah', [
            'pekerja_id'    => $p->id,
            'berlaku_mulai' => $this->hari->copy()->toDateString(),
            'pokok'         => 6_000_000,
        ])->assertRedirect();

        $this->assertSame(2, Upah::withoutGlobalScopes()->where('pekerja_id', $p->id)->count(),
            'Baris upah lama tertimpa; riwayat upahnya hilang.');
    }
}
