<?php

namespace Tests\Feature;

use App\Http\Controllers\Hris\HrisController;
use App\Models\Company;
use App\Models\Hr\{Absensi, AbsensiJejak, MesinAbsensi, PolaRoster, Roster};
use App\Models\Miners\Pekerja;
use App\Models\User;
use App\Support\Hr\MasterRoster;
use App\Support\{Dasbor, Menu, Modules, RuteInertia, Waktu};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * HRIS sebagai SATU modul, dan ringkasannya.
 *
 * Yang dijaga di sini bukan tampilannya melainkan KESATUANNYA. Roster
 * dan absensi membaca daftar orang yang sama, dijalankan bagian yang
 * sama, dan saling merujuk pada tiap layarnya; dipecah kembali menjadi
 * dua modul bilah samping, yang mengurusnya berpindah-pindah antar dua
 * tempat untuk satu pekerjaan — dan bilah samping yang sudah berisi
 * dua puluh delapan modul bertambah panjang tiap kali satu fitur HRIS
 * berikutnya lahir.
 *
 * Pemecahan semacam itu tidak menimbulkan satu galat pun: tiap
 * layarnya tetap terbuka, tiap rutenya tetap menjawab. Karena itu ia
 * harus dijaga uji, bukan diingat orang.
 */
class HrisTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $admin;
    private Carbon $hari;

    protected function setUp(): void
    {
        parent::setUp();

        MasterRoster::pasang();

        $this->c     = Company::create(['name' => 'PT Uji HRIS']);
        $this->admin = User::factory()->create(['company_id' => $this->c->id, 'is_admin' => true]);
        $this->hari  = Waktu::kini()->startOfDay();
    }

    private function pekerja(string $nama = 'Operator Uji'): Pekerja
    {
        return Pekerja::withoutGlobalScopes()->create([
            'company_id'    => $this->c->id,
            'nama'          => $nama,
            'no_registrasi' => 'REG-'.uniqid(),
            'status'        => 'aktif',
        ]);
    }

    private function roster(Pekerja $p, Carbon $t, array $ganti = []): Roster
    {
        return Roster::withoutGlobalScopes()->create($ganti + [
            'company_id'     => $this->c->id,
            'pekerja_id'     => $p->id,
            'pola_roster_id' => PolaRoster::withoutGlobalScopes()->where('kode', '14:7')->value('id'),
            'tanggal'        => $t->copy()->startOfDay(),
            'keadaan'        => 'kerja',
            'shift'          => 'siang',
            'jam'            => 11,
        ]);
    }

    /* ═══════════════════ kesatuan modul ═══════════════════ */

    #[Test]
    public function test_hris_adalah_satu_modul_bilah_samping(): void
    {
        $modul = Menu::all();

        $this->assertArrayHasKey('hris', $modul);

        /* Bekas pemecahannya tidak boleh hidup kembali. */
        $this->assertArrayNotHasKey('roster', $modul, 'Roster kembali berdiri sebagai modul sendiri.');
        $this->assertArrayNotHasKey('absensi', $modul, 'Absensi kembali berdiri sebagai modul sendiri.');
    }

    #[Test]
    public function test_roster_dan_absensi_menjadi_grup_di_dalamnya(): void
    {
        /* Grupnya yang membuat penggabungan berarti: tanpa grup,
           tujuh butir berderet rata dan pembacanya kehilangan batas
           antara "menyusun jadwal" dan "memeriksa kehadiran". */
        $grup = Menu::all()['hris']['groups'];

        $this->assertArrayHasKey('Roster & Shift', $grup);
        $this->assertArrayHasKey('Absensi', $grup);

        $rute = collect($grup)->flatten(1)->pluck(1);

        foreach (['hris.index', 'hris.roster.index', 'hris.absensi.index'] as $nama) {
            $this->assertContains($nama, $rute->all());
        }
    }

    #[Test]
    public function test_seluruh_rute_hris_berada_di_bawah_satu_awalan(): void
    {
        /* Inilah yang benar-benar menjaga kesatuannya. Menu dapat
           dirapikan sementara rutenya tetap terpencar — dan yang
           berikutnya menambah fitur HRIS akan mengikuti rute yang ada,
           bukan menu yang ada. */
        $liar = [];

        foreach (Route::getRoutes() as $r) {
            $uri = $r->uri();

            if (! preg_match('#^(roster|absensi)(/|$)#', $uri)) continue;

            $liar[] = $r->methods()[0].' /'.$uri;
        }

        $this->assertSame([], $liar, "Rute HRIS berada di luar awalan /hris:\n".implode("\n", $liar));
    }

    #[Test]
    public function test_ubin_dasbor_hris_menunjuk_modul_yang_ada(): void
    {
        $semua = collect(Dasbor::modul($this->admin));

        /* DUA ubin, dan jumlahnya yang diperiksa — bukan sekadar
           adanya satu. Dasbor::modul() MENYARING ubin yang modulnya
           tidak ada di bilah samping pengguna, sehingga satu ubin yang
           salah label hilang sama sekali dari hasilnya: tidak ada galat,
           tidak ada baris merah, hanya satu angka yang berhenti muncul
           di dasbor. Diperiksa "lebih dari nol", sisanya yang masih
           benar menutupi yang hilang. */
        $this->assertGreaterThanOrEqual(2, $semua->where('modul', 'hris')->count(),
            'Ubin HRIS hilang dari dasbor — modulnya mungkin salah label.');

        /* Diperiksa dari arah RUTENYA, bukan dari modulnya. Diperiksa
           dari modulnya, satu ubin yang menunjuk modul bekas cukup
           menghilang diam-diam dari dasbor — sisanya masih bermodul
           'hris', hitungannya masih di atas nol, dan tidak ada yang
           tampak salah. */
        foreach ($semua as $u) {
            if (! str_starts_with((string) $u['rute'], 'hris.')) continue;

            $this->assertSame('hris', $u['modul'],
                "Ubin {$u['nama']} menunjuk rute HRIS tetapi bermodul {$u['modul']}.");
        }
    }

    #[Test]
    public function test_etalase_menyebut_hris_satu_kali(): void
    {
        $rute = collect(Modules::all())->pluck('rute')->filter();

        $this->assertSame(1, $rute->filter(fn ($r) => str_starts_with((string) $r, 'hris.'))->count());
        $this->assertContains('hris.index', $rute->all());
    }

    #[Test]
    public function test_ringkasan_terdaftar_sebagai_halaman_inertia(): void
    {
        $this->assertContains('hris.index', RuteInertia::NAMA);
    }

    /* ═══════════════════ isi ringkasan ═══════════════════ */

    #[Test]
    public function test_ringkasan_menghitung_jadwal_hari_ini(): void
    {
        $a = $this->pekerja('Siang Satu');
        $b = $this->pekerja('Malam Satu');
        $c = $this->pekerja('Libur Satu');

        $this->roster($a, $this->hari);
        $this->roster($b, $this->hari, ['shift' => 'malam']);
        $this->roster($c, $this->hari, ['keadaan' => 'libur', 'shift' => null, 'jam' => 0]);

        /* Satu hari kerja yang berkasnya menghalangi. */
        $this->roster($this->pekerja('Terhalang'), $this->hari, ['halangan' => 'mcu']);

        $props = $this->actingAs($this->admin)->get('/hris')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame(3, $props['jadwal']['kerja']);
        $this->assertSame(2, $props['jadwal']['siang']);
        $this->assertSame(1, $props['jadwal']['malam']);
        $this->assertSame(1, $props['jadwal']['libur']);
        $this->assertSame(1, $props['jadwal']['terhalang']);
    }

    #[Test]
    public function test_ringkasan_memisahkan_kehadiran_hari_ini_dari_kemarin(): void
    {
        /* Keduanya perlu, dan justru pemisahannya yang berarti: shift
           hari ini belum selesai, sehingga angkanya belum dapat
           dibandingkan dengan apa pun. Dilebur, separuh site yang masih
           bekerja tercampur ke dalam rekap yang terbaca sebagai
           kesimpulan. */
        $p = $this->pekerja();

        Absensi::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'pekerja_id' => $p->id,
            'tanggal' => $this->hari->copy(), 'keadaan' => 'belum_pulang', 'jam' => 0,
        ]);

        Absensi::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'pekerja_id' => $p->id,
            'tanggal' => $this->hari->copy()->subDay(), 'keadaan' => 'terlambat',
            'jam' => 10.5, 'telat_menit' => 22,
        ]);

        $props = $this->actingAs($this->admin)->get('/hris')->assertOk()->viewData('page')['props'];

        $this->assertSame(1, $props['hadir']['belum_pulang']);
        $this->assertSame(0, $props['hadir']['terlambat']);

        $this->assertSame(1, $props['kemarin']['terlambat']);
        $this->assertSame(22, $props['kemarin']['telat']);
        $this->assertSame(10.5, $props['kemarin']['jam']);
    }

    #[Test]
    public function test_ringkasan_menyebut_pelanggaran_batas_jam_kerja(): void
    {
        /* Lima belas hari berturut-turut melampaui batas empat belas
           hari Kepmenakertrans 234/2003. */
        $p = $this->pekerja('Beruntun');

        for ($i = 0; $i < 15; $i++) {
            $this->roster($p, $this->hari->copy()->addDays($i));
        }

        $props = $this->actingAs($this->admin)->get('/hris')->assertOk()->viewData('page')['props'];

        $this->assertGreaterThan(0, $props['fatigue']['jumlah']);
        $this->assertSame('Beruntun', $props['fatigue']['daftar'][0]['pekerja']);

        /* JENISNYA diperiksa, bukan sekadar adanya baris. Batas 40 jam
           seminggu dicatat tetapi TIDAK PERNAH menolak — sektor ESDM
           punya pengecualiannya sendiri — dan rangkaian lima belas hari
           ini melanggar keduanya sekaligus. Diperiksa dari jumlahnya
           saja, layar yang hanya menampilkan temuan tak-menolak lulus
           dengan angka yang sama persis. */
        $jenis = array_column($props['fatigue']['daftar'], 'jenis');

        $this->assertContains('hari_beruntun', $jenis);

        foreach ($jenis as $j) {
            $this->assertContains($j, \App\Support\Hr\Fatigue::MENOLAK,
                "Temuan '{$j}' tidak menolak penerbitan, jadi ia bukan pelanggaran.");
        }
    }

    #[Test]
    public function test_daftar_pelanggaran_dipotong_tetapi_jumlahnya_utuh(): void
    {
        /* Satu pola yang salah menghasilkan ratusan baris yang isinya
           sama dan menenggelamkan segalanya — cacat yang persis begitu
           pernah mengubur kalender roster. Yang dipotong daftarnya,
           bukan angkanya. */
        for ($o = 0; $o < 12; $o++) {
            $p = $this->pekerja('Orang '.$o);

            for ($i = 0; $i < 15; $i++) $this->roster($p, $this->hari->copy()->addDays($i));
        }

        $props = $this->actingAs($this->admin)->get('/hris')->assertOk()->viewData('page')['props'];

        $this->assertGreaterThanOrEqual(12, $props['fatigue']['jumlah']);
        $this->assertLessThanOrEqual(8, count($props['fatigue']['daftar']));
    }

    #[Test]
    public function test_mesin_yang_berhenti_menyapa_terhitung_diam(): void
    {
        /* Alat pos jaga yang mati menghasilkan layar absensi yang
           tampak baik-baik saja: seluruh regunya tercatat absen, dan
           angka itu terbaca sebagai mangkir massal, bukan sebagai alat
           rusak. */
        MesinAbsensi::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'nama' => 'Pos Hidup', 'nomor_seri' => 'H-1',
            'aktif' => true, 'terakhir_hubung' => Waktu::kiniSimpan()->subMinutes(5),
        ]);

        MesinAbsensi::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'nama' => 'Pos Mati', 'nomor_seri' => 'M-1',
            'aktif' => true,
            'terakhir_hubung' => Waktu::kiniSimpan()->subHours(HrisController::DIAM_JAM + 1),
        ]);

        /* Alat yang BELUM PERNAH menyapa ikut terhitung diam. Ia baru
           dipasang dan tokennya mungkin belum ditanam — dan justru itu
           keadaan yang perlu terlihat, sebab regu yang lewat pos itu
           tercatat absen sejak hari pertama. Diperlakukan sehat karena
           "belum ada datanya", alat yang tidak pernah tersambung sama
           sekali adalah satu-satunya yang luput dari pemantauan. */
        MesinAbsensi::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'nama' => 'Pos Baru', 'nomor_seri' => 'B-1',
            'aktif' => true, 'terakhir_hubung' => null,
        ]);

        /* Yang nonaktif TIDAK ikut terhitung diam: ia memang sengaja
           dimatikan, dan menghitungnya sebagai kerusakan membuat angka
           itu berhenti berarti. */
        MesinAbsensi::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'nama' => 'Pos Dicabut', 'nomor_seri' => 'D-1',
            'aktif' => false, 'terakhir_hubung' => null,
        ]);

        $props = $this->actingAs($this->admin)->get('/hris')->assertOk()->viewData('page')['props'];

        $this->assertSame(3, $props['mesin']['aktif']);
        $this->assertSame(2, $props['mesin']['diam']);

        $diam = array_column($props['mesin']['nama'], 'nama');

        $this->assertContains('Pos Mati', $diam);
        $this->assertContains('Pos Baru', $diam);
        $this->assertNotContains('Pos Dicabut', $diam);
    }

    #[Test]
    public function test_pindaian_sehari_terakhir_terhitung(): void
    {
        $p = $this->pekerja();

        AbsensiJejak::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'pekerja_id' => $p->id,
            'terjadi' => Waktu::kiniSimpan()->subHours(2), 'arah' => 'masuk', 'kunci' => 'baru',
        ]);

        AbsensiJejak::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'pekerja_id' => $p->id,
            'terjadi' => Waktu::kiniSimpan()->subDays(3), 'arah' => 'masuk', 'kunci' => 'lama',
        ]);

        $props = $this->actingAs($this->admin)->get('/hris')->assertOk()->viewData('page')['props'];

        $this->assertSame(1, $props['mesin']['jejak24']);
    }

    #[Test]
    public function test_hash_token_tidak_ikut_terkirim_ke_peramban(): void
    {
        $m = MesinAbsensi::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'nama' => 'Pos Jaga', 'nomor_seri' => 'T-1', 'aktif' => true,
        ]);

        $m->terbitkanToken();

        /* Ringkasan memuat kolom token_hash untuk menghitung berapa
           mesin yang sudah bertoken — tetapi yang dikirim ke peramban
           hanyalah ANGKANYA. */
        $isi = $this->actingAs($this->admin)->get('/hris')->assertOk()->getContent();

        $this->assertStringNotContainsString('token_hash', $isi);
        $this->assertStringNotContainsString((string) $m->token_hash, $isi);
    }

    #[Test]
    public function test_tiap_halaman_hris_terbuka(): void
    {
        $this->actingAs($this->admin);

        foreach ([
            '/hris',
            '/hris/roster', '/hris/roster/pola', '/hris/roster/kebutuhan',
            '/hris/absensi', '/hris/absensi/rekap', '/hris/absensi/mesin',
        ] as $alamat) {
            $this->get($alamat)->assertOk();
        }
    }
}
