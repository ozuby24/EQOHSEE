<?php

namespace Tests\Feature;

use App\Models\{Company, MinersCuti, MinersCutiJatah, Paspor, User, WaterLog, WaterSump};
use App\Support\JatahCuti;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Jumlah kueri sebuah halaman tidak boleh tumbuh mengikuti jumlah barisnya.
 *
 * Inilah bentuk uji yang benar untuk N+1, dan bukan "jumlah kuerinya
 * kurang dari sekian". Ambang berupa angka tetap menua dengan cara yang
 * buruk: ia lulus selama datanya masih sedikit — yaitu selama masalahnya
 * belum terasa — lalu mulai gagal karena hal-hal yang sama sekali tidak
 * berhubungan, sampai akhirnya dinaikkan supaya berhenti mengganggu.
 *
 * Yang ditegaskan di sini adalah BENTUK pertumbuhannya. Halaman dengan
 * sepuluh baris dan halaman dengan empat puluh baris harus memakai jumlah
 * kueri yang kira-kira sama. Selisih kecil dibiarkan — halaman memang
 * memuat hal lain yang berubah-ubah — tetapi selisih yang mengikuti
 * jumlah baris berarti ada relasi yang diambil satu per satu.
 *
 * Yang ditangkapnya, terukur sebelum diperbaiki: halaman penirisan
 * mengeluarkan 44 kueri, 28 di antaranya `select * from users where id
 * = ?` — dua per baris, untuk nama pengaju dan nama peninjau yang
 * tercetak di tiap baris.
 */
class KueriTidakTumbuhTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $pengguna;
    private WaterSump $kolam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->c = Company::create(['name' => 'PT Uji Kueri']);
        $this->pengguna = User::factory()->create([
            'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);
        $this->kolam = WaterSump::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'kode' => 'SUMP-1', 'nama' => 'Kolam Uji',
            'jenis' => WaterSump::JENIS[0], 'status' => WaterSump::STATUS[0],
        ]);
    }

    private function catatan(int $jumlah, int $mulai = 0): void
    {
        for ($i = 0; $i < $jumlah; $i++) {
            $baris = WaterLog::withoutGlobalScopes()->create([
                'company_id'    => $this->c->id,
                'user_id'       => $this->pengguna->id,
                'water_sump_id' => $this->kolam->id,
                'tanggal'       => now()->subDays($mulai + $i)->toDateString(),
                'volume_m3'     => 100 + $i,
            ]);

            /* Pengaju dan peninjau DIISI — lewat pembaruan, sebab
               bootDitinjau memaksa tiap baris lahir sebagai draf tanpa
               keduanya, apa pun isi payload-nya.

               Pengisiannya bukan hiasan: baris tanpa pengaju tidak pernah
               memicu pengambilan relasinya, sehingga uji yang memakai
               baris kosong lulus atas halaman yang justru masih cacat. */
            $baris->forceFill([
                'diajukan_oleh' => $this->pengguna->id,
                'ditinjau_oleh' => $this->pengguna->id,
            ])->saveQuietly();
        }
    }

    private function kueri(string $alamat): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($this->pengguna)->get($alamat)->assertOk();

        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    }

    public function test_halaman_penirisan_tidak_tumbuh_bersama_jumlah_catatan(): void
    {
        $this->catatan(5);
        $sedikit = $this->kueri('/penirisan');

        $this->catatan(25, 5);
        $banyak = $this->kueri('/penirisan');

        $this->assertSame(30, WaterLog::withoutGlobalScopes()->count());

        $this->assertLessThanOrEqual($sedikit + 2, $banyak,
            "Halaman penirisan memakai $sedikit kueri untuk 5 catatan dan $banyak untuk 30. "
            .'Ada relasi yang diambil satu per satu.');
    }

    /**
     * Dan relasi peninjaunya benar-benar ikut termuat.
     *
     * Uji di atas juga lulus bila nama pengaju berhenti ditampilkan sama
     * sekali — jumlah kuerinya memang datar, tetapi karena halamannya
     * kehilangan isi. Yang ini menjaga sisi itu.
     */
    public function test_nama_pengaju_tetap_tampil(): void
    {
        $this->catatan(3);

        $props = $this->actingAs($this->pengguna)->get('/penirisan')
            ->assertOk()->viewData('page')['props'];

        $catatan = collect($props['catatan'] ?? $props['log'] ?? []);

        $this->assertNotEmpty($catatan, 'Halaman tidak memuat satu pun catatan.');

        $adaNama = $catatan->contains(fn ($c) => !empty($c['alur']['pengaju'] ?? null));

        $this->assertTrue($adaNama,
            'Nama pengaju hilang dari halaman; jumlah kueri datar karena isinya berkurang.');
    }

    /**
     * Halaman cuti tidak boleh tumbuh mengikuti jumlah pegawai.
     *
     * Saldo dihitung untuk SETIAP orang — itu memang disengaja, sebab
     * yang jatahnya masih utuh justru yang paling sering ditanyakan
     * menjelang akhir tahun. Yang keliru bukan cakupannya melainkan
     * caranya: `JatahCuti::hitung()` dipanggil sekali per orang, dan
     * tiap panggilan membawa dua kueri sendiri.
     *
     * Terukur sebelum diperbaiki, pada empat puluh orang: 177 kueri
     * untuk satu halaman, 160 di antaranya dua pola yang sama berulang.
     * Sesudahnya 17, dan datar. Bentuk inilah yang penting — halaman
     * yang memburuk lurus mengikuti jumlah pegawai adalah halaman yang
     * memburuk persis ketika perusahaannya bertumbuh.
     */
    public function test_halaman_cuti_tidak_tumbuh_bersama_jumlah_pegawai(): void
    {
        $this->pegawai(5);
        $sedikit = $this->kueri('/miners/cuti');

        $this->pegawai(35, 5);
        $banyak = $this->kueri('/miners/cuti');

        $this->assertSame(40, Paspor::withoutGlobalScopes()->count());

        $this->assertLessThanOrEqual($sedikit + 2, $banyak,
            "Halaman cuti memakai $sedikit kueri untuk 5 pegawai dan $banyak untuk 40. "
            .'Saldo dihitung satu per satu.');
    }

    /**
     * Dan saldonya tetap angka yang sama.
     *
     * Uji di atas juga lulus bila saldonya berhenti dihitung sama
     * sekali. Yang ini mengunci sisi itu: hasil perhitungan sekaligus
     * harus identik dengan perhitungan satu per satu, orang per orang.
     */
    public function test_saldo_sekaligus_sama_dengan_satu_per_satu(): void
    {
        $this->pegawai(6);
        $orang = Paspor::withoutGlobalScopes()->get();

        $sekaligus = JatahCuti::hitungBanyak($orang, (int) now()->year);

        foreach ($orang as $p) {
            $this->assertSame(
                JatahCuti::hitung($p, (int) now()->year),
                $sekaligus[$p->getKey()],
                "Saldo {$p->nama} berbeda antara perhitungan sekaligus dan satu per satu.",
            );
        }

        $this->assertNotEmpty(array_filter($sekaligus, fn ($s) => $s['terpakai'] > 0),
            'Tidak ada satu pun cuti terpakai pada data uji; angkanya tidak benar-benar diuji.');
    }

    /** Pegawai beserta jatah dan cuti yang benar-benar memotong jatahnya. */
    private function pegawai(int $jumlah, int $mulai = 0): void
    {
        for ($i = 0; $i < $jumlah; $i++) {
            $n = $mulai + $i;

            $p = Paspor::withoutGlobalScopes()->create([
                'company_id' => $this->c->id,
                'nama'       => 'Pegawai '.$n,
                'nik'        => str_pad((string) (3201 + $n), 16, '0'),
                'jabatan'    => 'Operator',
            ]);

            MinersCutiJatah::withoutGlobalScopes()->create([
                'company_id' => $this->c->id,
                'paspor_id'  => $p->id, 'tahun' => (int) now()->year,
                'jatah'      => 12, 'bawaan' => 2,
            ]);

            $cuti = MinersCuti::withoutGlobalScopes()->create([
                'company_id' => $this->c->id,
                'paspor_id'  => $p->id, 'tahun' => (int) now()->year,
                'jenis'      => MinersCuti::JENIS[0],
                'mulai'      => now()->subDays(10)->toDateString(),
                'selesai'    => now()->subDays(8)->toDateString(),
                'jumlah_hari' => 3,
            ]);

            /* Disetujui lewat pembaruan diam: alurnya memaksa tiap baris
               lahir sebagai draf, dan draf tidak memotong jatah — baris
               kosong membuat uji ini lulus atas halaman yang masih cacat. */
            $cuti->forceFill(['status' => \App\Support\Alur::DISETUJUI])->saveQuietly();
        }
    }

    /**
     * Dan tidak tumbuh mengikuti jumlah KOLAM, bukan hanya catatan.
     *
     * Uji catatan di atas memakai satu kolam saja, jadi ia datar
     * sekalipun tiap kolam mengambil relasinya sendiri-sendiri. Persis
     * itu yang tersembunyi di sana: `WaterSump::toView()` membaca
     * `$this->company?->name`, dan `company` tidak ikut dimuat di muka —
     * satu kueri `select * from companies where id = ?` per kolam.
     *
     * Terukur pada enam belas kolam: 33 kueri, 16 di antaranya pola itu.
     * Sesudahnya 18, dan datar.
     */
    public function test_halaman_penirisan_tidak_tumbuh_bersama_jumlah_kolam(): void
    {
        $this->catatan(3);
        $sedikit = $this->kueri('/penirisan');

        $this->kolamLain(15);
        $banyak = $this->kueri('/penirisan');

        $this->assertSame(16, WaterSump::withoutGlobalScopes()->count());

        $this->assertLessThanOrEqual($sedikit + 2, $banyak,
            "Halaman penirisan memakai $sedikit kueri untuk 1 kolam dan $banyak untuk 16. "
            .'Ada relasi kolam yang diambil satu per satu.');
    }

    /** Dan nama perusahaannya benar-benar ikut terkirim. */
    public function test_nama_perusahaan_kolam_tetap_tampil(): void
    {
        $this->catatan(1);

        $props = $this->actingAs($this->pengguna)->get('/penirisan')
            ->assertOk()->viewData('page')['props'];

        $kolam = collect($props['kolam'] ?? []);

        $this->assertNotEmpty($kolam, 'Halaman tidak memuat satu pun kolam.');
        $this->assertSame('PT Uji Kueri', $kolam->first()['perusahaan'] ?? null,
            'Nama perusahaan hilang dari kolam; jumlah kueri datar karena isinya berkurang.');
    }

    private function kolamLain(int $jumlah): void
    {
        for ($i = 0; $i < $jumlah; $i++) {
            WaterSump::withoutGlobalScopes()->create([
                'company_id' => $this->c->id,
                'kode'   => 'SUMP-'.($i + 2),
                'nama'   => 'Kolam '.($i + 2),
                'jenis'  => WaterSump::JENIS[0],
                'status' => WaterSump::STATUS[0],
            ]);
        }
    }
}
