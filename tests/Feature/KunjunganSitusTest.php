<?php

namespace Tests\Feature;

use App\Http\Middleware\CatatPengunjung;
use App\Models\User;
use App\Support\{Modules, Pillars, RingkasPlatform};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, DB, Schema};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Angka di kaca kiri-bawah halaman masuk: pengguna, pengunjung situs,
 * modul (berikut sebarannya per pilar), dan aspek OHSE.
 *
 * Angka di halaman pertama yang dilihat calon pelanggan harus benar:
 * pengunjung dihitung sekali per peramban per hari, bukan per muat
 * halaman; perayap, pratinjau tautan, dan skrip tidak ikut; dan tidak
 * ada data pribadi yang disimpan untuk menghitungnya.
 */
class KunjunganSitusTest extends TestCase
{
    use RefreshDatabase;

    private const CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

    /** Tajuk navigasi dokumen yang dikirim setiap peramban modern. */
    private const PERAMBAN = [
        'User-Agent'     => self::CHROME,
        'Sec-Fetch-Dest' => 'document',
        'Sec-Fetch-Mode' => 'navigate',
    ];

    private function jumlah(): int
    {
        return (int) DB::table('kunjungan_harian')->sum('pengunjung');
    }

    public function test_peramban_dihitung_sekali_sehari_dan_langsung_melihat_hitungannya(): void
    {
        $hariIni = now()->toDateString();

        // Pengunjung pertama tidak disambut "0 pengunjung": ia dicatat
        // sebelum halamannya digambar.
        $this->withHeaders(self::PERAMBAN)->get('/login')
            ->assertOk()
            ->assertCookie(CatatPengunjung::KUKI, $hariIni)
            ->assertInertia(fn ($p) => $p
                ->where('ringkasPlatform.pengunjung', 1)
                ->where('ringkasPlatform.tren.6', 1));

        // Muat ulang dan pindah halaman di hari yang sama: kukinya terbawa.
        $this->withHeaders(self::PERAMBAN)->withCookie(CatatPengunjung::KUKI, $hariIni)->get('/login')->assertOk();
        $this->withHeaders(self::PERAMBAN)->withCookie(CatatPengunjung::KUKI, $hariIni)->get('/register')->assertOk();

        $this->assertSame(1, $this->jumlah());
    }

    public function test_peramban_yang_sama_dihitung_lagi_esok_hari(): void
    {
        $kemarin = now()->subDay()->toDateString();

        $this->withHeaders(self::PERAMBAN)->withCookie(CatatPengunjung::KUKI, $kemarin)->get('/login')
            ->assertOk()
            ->assertCookie(CatatPengunjung::KUKI, now()->toDateString());

        $this->assertSame(1, $this->jumlah());
    }

    public function test_kunjungan_inertia_dihitung(): void
    {
        // Hanya aplikasi ini sendiri, di peramban, yang mengirim X-Inertia.
        $this->withHeaders(['User-Agent' => self::CHROME, 'X-Inertia' => 'true'])->get('/login');

        $this->assertSame(1, $this->jumlah());
    }

    /** @return array<string, array{0: array<string, string>}> */
    public static function bukanKunjunganPeramban(): array
    {
        $p = self::PERAMBAN;

        return [
            'perayap mesin pencari'       => [['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'] + $p],
            'pratinjau tautan WhatsApp'   => [['User-Agent' => 'WhatsApp/2.24.21.0 A'] + $p],
            'pratinjau tautan LinkedIn'   => [['User-Agent' => 'LinkedInBot/1.0 (compatible; Mozilla/5.0)'] + $p],
            'pemantau ketersediaan'       => [['User-Agent' => 'Mozilla/5.0 (compatible; UptimeRobot/2.0; http://www.uptimerobot.com/)'] + $p],
            'peramban tanpa kepala'       => [['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/131.0.0.0 Safari/537.36'] + $p],
            'skrip curl'                  => [['User-Agent' => 'curl/8.5.0'] + $p],
            'agen kosong'                 => [['User-Agent' => ''] + $p],
            'skrip menyamar tanpa Sec-Fetch' => [['User-Agent' => self::CHROME]],
            'gambar, bukan dokumen'       => [['Sec-Fetch-Dest' => 'image', 'Sec-Fetch-Mode' => 'no-cors'] + $p],
            'muatan awal (prefetch)'      => [['Sec-Purpose' => 'prefetch'] + $p],
            'muatan awal Inertia'         => [['User-Agent' => self::CHROME, 'X-Inertia' => 'true', 'Purpose' => 'prefetch']],
        ];
    }

    /** @param array<string, string> $tajuk */
    #[DataProvider('bukanKunjunganPeramban')]
    public function test_yang_bukan_kunjungan_peramban_tidak_dihitung(array $tajuk): void
    {
        $jawab = $this->withHeaders($tajuk)->get('/login');

        $this->assertSame(0, $this->jumlah());
        $jawab->assertCookieMissing(CatatPengunjung::KUKI);
    }

    public function test_kiriman_formulir_tidak_dihitung(): void
    {
        $this->withHeaders(self::PERAMBAN)->post('/login', ['email' => 'tidak@ada.test', 'password' => 'salah']);

        $this->assertSame(0, $this->jumlah());
    }

    /**
     * Anonim sejak rancangan: tabelnya hanya tanggal dan bilangan.
     * Kolom baru (IP, agen, akun) di sini berarti data pribadi diproses
     * untuk sebuah angka statistik — dan Kebijakan Privasi harus diubah.
     */
    public function test_tidak_ada_data_pribadi_yang_disimpan(): void
    {
        $kolom = Schema::getColumnListing('kunjungan_harian');
        sort($kolom);

        $this->assertSame(['created_at', 'id', 'pengunjung', 'tanggal', 'updated_at'], $kolom);

        foreach (['kebijakan-privasi', 'privacy-policy'] as $berkas) {
            $this->assertStringContainsString(CatatPengunjung::KUKI,
                file_get_contents(resource_path("views/hukum/{$berkas}.blade.php")),
                "Kebijakan privasi ({$berkas}) tidak menyebut kuki penghitung pengunjung.");
        }
    }

    public function test_gagal_mencatat_tidak_menggagalkan_halaman(): void
    {
        Schema::drop('kunjungan_harian');

        $this->withHeaders(self::PERAMBAN)->get('/login')
            ->assertOk()
            ->assertCookieMissing(CatatPengunjung::KUKI)
            ->assertInertia(fn ($p) => $p->where('ringkasPlatform.pengunjung', 0));
    }

    public function test_ringkasan_dihitung_dari_data_nyata(): void
    {
        $aktifSebelum = User::query()->where('active', true)->count();
        User::factory()->count(3)->create(['active' => true]);
        User::factory()->create(['active' => false]);

        DB::table('kunjungan_harian')->insert([
            ['tanggal' => now()->subDays(30)->toDateString(), 'pengunjung' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['tanggal' => now()->subDays(6)->toDateString(),  'pengunjung' => 5,  'created_at' => now(), 'updated_at' => now()],
            ['tanggal' => now()->toDateString(),              'pengunjung' => 2,  'created_at' => now(), 'updated_at' => now()],
        ]);

        $r = RingkasPlatform::hitung();

        $this->assertSame($aktifSebelum + 3, $r['pengguna'], 'Akun nonaktif ikut terhitung sebagai pengguna.');
        $this->assertSame(17, $r['pengunjung'], 'Jumlah pengunjung bukan jumlah seluruh hari.');
        $this->assertSame([5, 0, 0, 0, 0, 0, 2], $r['tren'], 'Grafik mini bukan tujuh hari terakhir, lama ke baru.');
        $this->assertSame(count(Modules::all()), $r['modul']);
        $this->assertSame(count(Pillars::all()), $r['aspek']);

        // Pita sebaran modul: jumlahnya sama dengan jumlah modul, terbanyak lebih dulu.
        $jumlah = array_column($r['modulPerPilar'], 'jumlah');
        $this->assertSame($r['modul'], array_sum($jumlah));
        $urut = $jumlah;
        rsort($urut);
        $this->assertSame($urut, $jumlah);
        foreach ($r['modulPerPilar'] as $m) {
            $this->assertArrayHasKey($m['kunci'], Pillars::all(), "Modul berpilar tidak dikenal: {$m['kunci']}.");
        }
    }

    /**
     * Banyaknya perusahaan pelanggan tidak diumumkan: properti halaman
     * terbaca siapa pun di sumber halaman masuk.
     */
    public function test_jumlah_perusahaan_pelanggan_tidak_dikirim_ke_tamu(): void
    {
        $this->get('/login')->assertOk()->assertInertia(fn ($p) => $p
            ->has('ringkasPlatform.modulPerPilar')
            ->missing('ringkasPlatform.perusahaan'));
    }

    public function test_pengunjung_baru_menyegarkan_ringkasan_tersimpan(): void
    {
        Cache::put(RingkasPlatform::KUNCI_CACHE, RingkasPlatform::hitung(), now()->addMinutes(10));

        $this->withHeaders(self::PERAMBAN)->get('/login')
            ->assertInertia(fn ($p) => $p->where('ringkasPlatform.pengunjung', 1));
    }
}
