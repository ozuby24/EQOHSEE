<?php

namespace Tests\Feature;

use App\Models\{Company, HazardReport, User};
use App\Support\AnalitikBahaya;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Analitik & KPI Hazard Report.
 *
 * Angka di halaman ini dibawa ke rapat bulanan dan dipakai menagih
 * orang. Yang salah tidak memulangkan galat apa pun — hanya angka yang
 * tampak masuk akal, dan orang yang ditagih atas pekerjaan yang
 * sebenarnya sudah ia kerjakan.
 *
 * Dua hal yang paling mudah rusak diam-diam:
 *
 *   · Pengelompokan orang. Satu orang terpecah menjadi tiga membuat
 *     targetnya tiga kali lipat sementara laporannya tetap; dua orang
 *     menyatu membuat capaian keduanya separuh.
 *   · "Belum ada" yang terbaca sebagai "nol". Rata-rata penutupan nol
 *     hari adalah capaian TERBAIK yang mungkin, dan menampilkannya
 *     untuk perusahaan yang belum menutup apa pun membalik artinya.
 */
class AnalitikBahayaTest extends TestCase
{
    use RefreshDatabase;

    private static int $n = 0;

    private function perusahaan(): Company
    {
        $i = ++self::$n;

        return Company::create(['name' => "PT Uji Analitik {$i}", 'code' => "PA{$i}"]);
    }

    private function masuk(?Company $c = null): User
    {
        $u = User::factory()->create(['is_admin' => true, 'company_id' => $c?->id]);
        $this->actingAs($u);

        return $u;
    }

    private function laporan(Company $c, array $x = []): HazardReport
    {
        return HazardReport::withoutGlobalScopes()->create(array_merge([
            'kode'            => 'HR-'.str_pad((string) ++self::$n, 4, '0', STR_PAD_LEFT),
            'company_id'      => $c->id,
            'pelapor_nama'    => 'Pelapor Uji',
            'pelapor_jabatan' => 'Supervisor',
            'tanggal'         => now()->toDateString(),
            'lokasi'          => 'Area uji',
            'risiko'          => 'Sedang',
            'kategori'        => 'Unsafe Condition',
            'deskripsi'       => 'Temuan uji coba.',
            'hirarki'         => 'Rekayasa',
            'rekomendasi'     => 'Diperbaiki pengawas area.',
            'status'          => 'Open',
        ], $x));
    }

    /** Mesin analitik atas laporan yang baru saja dibuat. */
    private function mesin(int $bulanAktif = 1): AnalitikBahaya
    {
        return new AnalitikBahaya(
            HazardReport::withoutGlobalScopes()->with(['user', 'company'])->get(),
            $bulanAktif,
        );
    }

    /* ══════════════ pengelompokan orang ══════════════ */

    /** Satu orang yang menulis namanya berbeda-beda tetap satu orang. */
    public function test_nama_yang_ditulis_berbeda_tetap_satu_orang(): void
    {
        $c = $this->perusahaan();

        foreach (['Budi Santoso', 'budi santoso', 'Budi  Santoso'] as $nama) {
            $this->laporan($c, ['pelapor_nama' => $nama]);
        }

        $orang = $this->mesin()->perOrang();

        $this->assertCount(1, $orang, 'Satu orang terpecah menjadi beberapa; targetnya ikut berlipat.');
        $this->assertSame(3, reset($orang)['aktual']);
    }

    /**
     * Nama yang sama di DUA perusahaan tetap dua orang.
     *
     * Kebalikan dari uji di atas, dan sama pentingnya. Disatukan,
     * laporan keduanya menumpuk pada satu baris dan capaian
     * masing-masing menjadi separuh dari yang sebenarnya — pada layar
     * administrator yang justru melihat seluruh perusahaan.
     */
    public function test_nama_sama_di_dua_perusahaan_tetap_dua_orang(): void
    {
        $a = $this->perusahaan();
        $b = $this->perusahaan();

        $this->laporan($a, ['pelapor_nama' => 'Sudarmin']);
        $this->laporan($b, ['pelapor_nama' => 'Sudarmin']);

        $orang = $this->mesin()->perOrang();

        $this->assertCount(2, $orang, 'Dua orang berbeda di dua perusahaan menyatu menjadi satu.');

        $perusahaan = array_column($orang, 'perusahaan');
        sort($perusahaan);
        $this->assertSame([$a->name, $b->name], $perusahaan);
    }

    /** Target dikalikan jumlah bulan yang berisi laporan. */
    public function test_target_dikali_bulan_aktif(): void
    {
        $c = $this->perusahaan();
        $this->laporan($c, ['pelapor_jabatan' => 'Supervisor']);   // target 4/bulan

        $satu = $this->mesin(1)->perOrang();
        $tiga = $this->mesin(3)->perOrang();

        $this->assertSame(4,  reset($satu)['target']);
        $this->assertSame(12, reset($tiga)['target']);
    }

    /** Golongan paling tertinggal berada di baris teratas. */
    public function test_golongan_paling_tertinggal_di_atas(): void
    {
        $c = $this->perusahaan();

        // Manager: target 1, satu laporan → 100%
        $this->laporan($c, ['pelapor_nama' => 'Bu Manajer', 'pelapor_jabatan' => 'Manager']);
        // Supervisor: target 4, satu laporan → 25%
        $this->laporan($c, ['pelapor_nama' => 'Pak Supervisor', 'pelapor_jabatan' => 'Supervisor']);

        $m = $this->mesin();
        $gol = $m->perGolongan($m->perOrang());

        $this->assertSame(['Supervisor', 'Manager'], array_column($gol, 'nama'),
            'Tabel capaian dibaca dari atas; yang perlu ditindaklanjuti harus di sana.');
        $this->assertSame(25, $gol[0]['pct']);
        $this->assertSame(100, $gol[1]['pct']);
    }

    /* ══════════════ enam kartu ══════════════ */

    public function test_enam_kartu_lengkap_dan_berurut(): void
    {
        $c = $this->perusahaan();
        $this->laporan($c);

        $m = $this->mesin();
        $kartu = $m->kartu($m->perOrang());

        $this->assertSame(
            ['total', 'capaian', 'orang', 'tinggi', 'tutup', 'lama'],
            array_column($kartu, 'kunci'),
        );

        foreach ($kartu as $k) {
            $this->assertNotSame('', trim($k['label']));
            $this->assertNotSame('', trim($k['ket']));
            $this->assertContains($k['nada'], ['baik', 'perhatian', 'bahaya', 'netral']);
        }
    }

    /**
     * Rata-rata penutupan adalah "—" saat belum ada yang ditutup.
     *
     * Nol hari berarti "ditutup pada hari yang sama" — capaian terbaik
     * yang mungkin. Ditampilkan untuk perusahaan yang belum menutup apa
     * pun, ia membalik arti angkanya sepenuhnya.
     */
    public function test_rata_penutupan_kosong_bukan_nol(): void
    {
        $c = $this->perusahaan();
        $this->laporan($c, ['status' => 'Open']);

        $m = $this->mesin();

        $this->assertNull($m->rataHariPenutupan());

        $lama = collect($m->kartu($m->perOrang()))->firstWhere('kunci', 'lama');

        $this->assertSame('—', $lama['nilai']);
        $this->assertStringContainsString('belum ada', $lama['ket']);
    }

    /** Rata-rata penutupan dihitung dari tanggal temuan ke tanggal penutupan. */
    public function test_rata_penutupan_dihitung_benar(): void
    {
        $c = $this->perusahaan();

        $this->laporan($c, [
            'tanggal'   => now()->subDays(10)->toDateString(),
            'status'    => 'Closed',
            'closed_at' => now()->subDays(6),
        ]);
        $this->laporan($c, [
            'tanggal'   => now()->subDays(8)->toDateString(),
            'status'    => 'Closed',
            'closed_at' => now()->subDays(2),
        ]);

        // 4 hari dan 6 hari → 5,0
        $this->assertSame(5.0, $this->mesin()->rataHariPenutupan());
    }

    /** Laporan yang belum ditutup tidak ikut menarik rata-rata ke bawah. */
    public function test_laporan_terbuka_tidak_ikut_rata_penutupan(): void
    {
        $c = $this->perusahaan();

        $this->laporan($c, [
            'tanggal'   => now()->subDays(10)->toDateString(),
            'status'    => 'Closed',
            'closed_at' => now()->subDays(6),
        ]);
        $this->laporan($c, ['tanggal' => now()->subDays(60)->toDateString(), 'status' => 'Open']);

        $this->assertSame(4.0, $this->mesin()->rataHariPenutupan());
    }

    /* ══════════════ kalimat sorotan ══════════════ */

    /** Tanpa laporan, satu kalimat saja — dan ia mengaku tidak tahu apa-apa. */
    public function test_tanpa_laporan_sorotan_mengaku_kosong(): void
    {
        $s = $this->mesin()->insight([], [], []);

        $this->assertCount(1, $s);
        $this->assertStringContainsString('Belum ada laporan', $s[0]['teks']);
    }

    /** Tenggat terlewat yang belum ditutup disebut. */
    public function test_sorotan_menyebut_tenggat_terlewat(): void
    {
        $c = $this->perusahaan();

        $this->laporan($c, [
            'batas_akhir' => now()->subDays(5)->toDateString(),
            'status'      => 'Open',
        ]);

        $m = $this->mesin();
        $teks = implode(' ', array_column($m->insight($m->perOrang(), [], []), 'teks'));

        $this->assertStringContainsString('melewati batas akhir', $teks);
    }

    /**
     * Tenggat terlewat yang SUDAH ditutup tidak disebut.
     *
     * Temuan yang ditutup pada hari kesepuluh dari tenggat tujuh hari
     * memang terlambat, tetapi ia sudah selesai — dan sorotan yang
     * memuatnya menyuruh orang mengerjakan sesuatu yang sudah
     * dikerjakan.
     */
    public function test_tenggat_terlewat_yang_sudah_ditutup_tidak_disebut(): void
    {
        $c = $this->perusahaan();

        $this->laporan($c, [
            'batas_akhir' => now()->subDays(5)->toDateString(),
            'status'      => 'Closed',
            'closed_at'   => now()->subDay(),
        ]);

        $m = $this->mesin();
        $teks = implode(' ', array_column($m->insight($m->perOrang(), [], []), 'teks'));

        $this->assertStringNotContainsString('melewati batas akhir', $teks);
    }

    /** Pemusatan risiko tinggi disebut hanya bila memang terpusat. */
    public function test_sorotan_menyebut_pemusatan_risiko_tinggi(): void
    {
        $c = $this->perusahaan();

        foreach (range(1, 4) as $_) {
            $this->laporan($c, ['risiko' => 'Tinggi', 'lokasi' => 'Pit Utara']);
        }

        $m = $this->mesin();
        $teks = implode(' ', array_column($m->insight($m->perOrang(), [], []), 'teks'));

        $this->assertStringContainsString('Pit Utara', $teks);
        $this->assertStringContainsString('terpusat', $teks);
    }

    /** Risiko tinggi yang menyebar merata tidak melahirkan kalimat itu. */
    public function test_risiko_tinggi_yang_merata_tidak_disebut_terpusat(): void
    {
        $c = $this->perusahaan();

        foreach (['Pit A', 'Pit B', 'Pit C', 'Pit D'] as $lokasi) {
            $this->laporan($c, ['risiko' => 'Tinggi', 'lokasi' => $lokasi]);
        }

        $m = $this->mesin();
        $teks = implode(' ', array_column($m->insight($m->perOrang(), [], []), 'teks'));

        $this->assertStringNotContainsString('terpusat di satu lokasi', $teks);
    }

    /** Keadaan yang memang baik pun dikatakan — sekali. */
    public function test_keadaan_baik_tetap_mendapat_satu_kalimat(): void
    {
        $c = $this->perusahaan();
        $this->laporan($c, ['pelapor_jabatan' => 'Manager', 'risiko' => 'Rendah']);

        $m = $this->mesin();
        $orang = $m->perOrang();
        $s = $m->insight($orang, $m->perGolongan($orang), []);

        $this->assertCount(1, $s);
        $this->assertSame('baik', $s[0]['nada']);
    }

    /* ══════════════ donat sebaran ══════════════ */

    /**
     * Urutan potongan DITETAPKAN, tidak mengikuti besar kecilnya.
     *
     * Diurut menurut jumlah, potongan merah berpindah tempat setiap
     * kali datanya berubah — dan warna yang berpindah tempat berhenti
     * berarti apa pun antar bulan.
     */
    public function test_urutan_potongan_donat_tetap(): void
    {
        $c = $this->perusahaan();

        $this->laporan($c, ['risiko' => 'Tinggi']);
        foreach (range(1, 5) as $_) $this->laporan($c, ['risiko' => 'Rendah']);

        $potong = $this->mesin()->donat('risiko', [
            'Rendah' => '#2563EB', 'Sedang' => '#CA9A04', 'Tinggi' => '#DC2626',
        ]);

        $this->assertSame(['Rendah', 'Tinggi'], array_column($potong, 'label'),
            'Potongan diurut menurut jumlahnya, bukan menurut urutan yang ditetapkan.');
    }

    /** Nilai yang tidak dikenal tetap muncul, di belakang. */
    public function test_nilai_asing_tetap_muncul_di_belakang(): void
    {
        $c = $this->perusahaan();

        $this->laporan($c, ['status' => 'Open']);
        $this->laporan($c, ['status' => 'Ditangguhkan']);   // status lama yang tak dikenal

        $potong = $this->mesin()->donat('status', [
            'Closed' => '#16A34A', 'In Progress' => '#CA9A04', 'Open' => '#DC2626',
        ]);

        $this->assertSame(['Open', 'Ditangguhkan'], array_column($potong, 'label'),
            'Nilai yang tak dikenal hilang diam-diam dari sebarannya.');
        $this->assertSame(50.0, $potong[1]['pct']);
    }

    /* ══════════════ halaman ══════════════ */

    public function test_halaman_menyajikan_kartu_sorotan_donat_dan_tren(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $this->laporan($c);

        $this->get(route('hazard.analytics'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Hazard/Analitik')
                ->has('kartu', 6)
                ->has('sorotan')
                ->has('donat', 2)
                ->has('tren', 12)
                ->has('teratas')
                ->has('pelapor')
                ->etc());
    }

    /**
     * Tiap batang tren membawa ketiga pita risiko, meski bernilai nol.
     *
     * Dibangun hanya dari yang terisi, urutan warnanya berubah dari
     * bulan ke bulan — dan batang bertumpuk yang warnanya berpindah
     * tempat tidak dapat dibaca sebagai perbandingan antar bulan.
     */
    public function test_tiap_batang_tren_membawa_ketiga_pita(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $this->laporan($c, ['risiko' => 'Tinggi']);

        $this->get(route('hazard.analytics'))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $p) {
                $tren = $p->toArray()['props']['tren'];

                $this->assertCount(12, $tren);

                foreach ($tren as $t) {
                    $this->assertSame(['Tinggi', 'Sedang', 'Rendah'],
                        array_column($t['tumpuk'], 'label'));
                    $this->assertSame($t['nilai'], array_sum(array_column($t['tumpuk'], 'nilai')),
                        'Jumlah pita bertumpuk tidak sama dengan tinggi batangnya.');
                }
            });
    }

    /**
     * Tren digambar dengan SATU kueri, bukan dua belas.
     *
     * Sebelumnya dua belas COUNT berurutan — dua belas perjalanan ke
     * basis data untuk menggambar satu grafik. Kembalinya tidak
     * memulangkan galat apa pun, hanya halaman yang berhenti sejenak
     * setiap kali dibuka.
     */
    public function test_tren_hanya_satu_kueri(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $this->laporan($c);

        DB::enableQueryLog();
        $this->get(route('hazard.analytics'))->assertOk();
        $kueri = DB::getQueryLog();
        DB::disableQueryLog();

        $tren = array_filter($kueri, fn ($q) => str_contains($q['query'], 'risiko')
            && str_contains($q['query'], 'group by'));

        $this->assertCount(1, $tren,
            'Tren dibangun lebih dari satu kueri; lihat HazardController::trenBahaya().');
    }

    /* ══════════════ unduhan statistik ══════════════ */

    public function test_tamu_tidak_dapat_mengunduh_statistik(): void
    {
        $this->get(route('hazard.analitik.ekspor'))->assertRedirect(route('login'));
    }

    public function test_statistik_terbit_sebagai_csv_bertiga_bagian(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $this->laporan($c, ['pelapor_nama' => 'Pak Uji', 'pelapor_jabatan' => 'Supervisor']);

        $jawab = $this->get(route('hazard.analitik.ekspor'));
        $jawab->assertOk();

        $isi = $jawab->streamedContent();

        $this->assertStringContainsString('Ringkas;', $isi);
        $this->assertStringContainsString('Golongan;', $isi);
        $this->assertStringContainsString('Pelapor;', $isi);
        $this->assertStringContainsString('Pak Uji', $isi);
        $this->assertStringContainsString($c->name, $isi);
    }

    /** Laporan perusahaan lain tidak ikut ke dalam statistiknya. */
    public function test_perusahaan_lain_tidak_ikut_ke_statistik(): void
    {
        $tetangga = $this->perusahaan();
        $this->laporan($tetangga, ['pelapor_nama' => 'Orang Tetangga']);

        $saya = $this->perusahaan();
        $this->laporan($saya, ['pelapor_nama' => 'Orang Saya']);

        $this->actingAs(User::factory()->create(['is_admin' => false, 'company_id' => $saya->id]));

        $isi = $this->get(route('hazard.analitik.ekspor'))->assertOk()->streamedContent();

        $this->assertStringContainsString('Orang Saya', $isi);
        $this->assertStringNotContainsString('Orang Tetangga', $isi);
    }
}
