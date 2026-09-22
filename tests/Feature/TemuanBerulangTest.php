<?php

namespace Tests\Feature;

use App\Models\{Company, HazardReport, Inspection, InspectionItem, User};
use App\Support\TemuanBerulang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Analisa kecenderungan temuan berulang.
 *
 * Angka yang paling berarti di halaman ini KAMBUH: temuan yang kembali
 * sesudah dinyatakan selesai. Ia berarti perbaikannya tidak bertahan —
 * pertanyaan yang berbeda sama sekali dari "berapa banyak temuan kita",
 * dan satu-satunya angka di sini yang menuduh sesuatu.
 *
 * Karena ia menuduh, ia harus benar. Angka kambuh yang membengkak
 * membuat tim yang sungguh-sungguh menangani temuannya berkali-kali
 * tampak sama buruknya dengan tim yang membiarkannya, dan sesudah dua
 * tiga kali begitu tidak ada lagi yang mempercayai halamannya.
 */
class TemuanBerulangTest extends TestCase
{
    use RefreshDatabase;

    private static int $n = 0;

    private function perusahaan(): Company
    {
        $i = ++self::$n;

        return Company::create(['name' => "PT Uji Berulang {$i}", 'code' => "PB{$i}"]);
    }

    private function masuk(?Company $c = null): User
    {
        $u = User::factory()->create(['is_admin' => true, 'company_id' => $c?->id]);
        $this->actingAs($u);

        return $u;
    }

    private function hazard(Company $c, array $x = []): HazardReport
    {
        return HazardReport::withoutGlobalScopes()->create(array_merge([
            'kode'            => 'HR-'.str_pad((string) ++self::$n, 4, '0', STR_PAD_LEFT),
            'company_id'      => $c->id,
            'pelapor_nama'    => 'Pelapor Uji',
            'pelapor_jabatan' => 'Supervisor',
            'tanggal'         => now()->toDateString(),
            'lokasi'          => 'Pit Utara',
            'risiko'          => 'Sedang',
            'kategori'        => 'Unsafe Condition',
            'deskripsi'       => 'Temuan uji coba.',
            'hirarki'         => 'Administratif',
            'rekomendasi'     => 'Diperbaiki pengawas area.',
            'status'          => 'Open',
        ], $x));
    }

    private function butir(Company $c, string $tanggal, array $x = []): InspectionItem
    {
        $ins = Inspection::withoutGlobalScopes()->create([
            'kode'       => 'INS-'.str_pad((string) ++self::$n, 4, '0', STR_PAD_LEFT),
            'company_id' => $c->id,
            'judul'      => 'Inspeksi uji',
            'jenis'      => 'Umum',
            'lokasi'     => $x['lokasi'] ?? 'Workshop',
            'tanggal'    => $tanggal,
            'status'     => 'Selesai',
        ]);

        return InspectionItem::create([
            'inspection_id' => $ins->id,
            'uraian'        => $x['uraian'] ?? 'APAR bertekanan cukup',
            'kondisi'       => 'Tidak Sesuai',
            'risiko'        => $x['risiko'] ?? 'Sedang',
            'tindakan'      => $x['tindakan'] ?? null,
            'order_index'   => 1,
        ]);
    }

    private function mesin(): TemuanBerulang
    {
        return new TemuanBerulang(
            HazardReport::withoutGlobalScopes()->get(),
            InspectionItem::withoutGlobalScopes()->with('inspection')
                ->where('kondisi', 'Tidak Sesuai')->get(),
        );
    }

    /* ══════════════ pengelompokan ══════════════ */

    /** Muncul sekali saja bukan temuan berulang. */
    public function test_temuan_tunggal_bukan_kelompok_berulang(): void
    {
        $c = $this->perusahaan();
        $this->hazard($c);

        $this->assertSame([], $this->mesin()->kelompok());
    }

    /** Lokasi dan perihal yang sama, dua kali, menjadi satu kelompok. */
    public function test_lokasi_dan_perihal_sama_menjadi_satu_kelompok(): void
    {
        $c = $this->perusahaan();

        $this->hazard($c, ['tanggal' => now()->subDays(40)->toDateString()]);
        $this->hazard($c, ['tanggal' => now()->subDays(5)->toDateString()]);

        $k = $this->mesin()->kelompok();

        $this->assertCount(1, $k);
        $this->assertSame(2, $k[0]['jumlah']);
        $this->assertSame('Pit Utara', $k[0]['lokasi']);
        $this->assertSame('Unsafe Condition', $k[0]['perihal']);
    }

    /** Lokasi berbeda tidak disatukan, meski perihalnya sama. */
    public function test_lokasi_berbeda_tidak_disatukan(): void
    {
        $c = $this->perusahaan();

        $this->hazard($c, ['lokasi' => 'Pit Utara']);
        $this->hazard($c, ['lokasi' => 'Pit Selatan']);

        $this->assertSame([], $this->mesin()->kelompok(),
            'Dua lokasi berbeda disatukan menjadi satu kelompok berulang.');
    }

    /** Beda huruf besar-kecil pada lokasi tetap satu kelompok. */
    public function test_beda_huruf_besar_kecil_tetap_satu_kelompok(): void
    {
        $c = $this->perusahaan();

        $this->hazard($c, ['lokasi' => 'Pit Utara']);
        $this->hazard($c, ['lokasi' => 'PIT UTARA']);

        $this->assertCount(1, $this->mesin()->kelompok());
    }

    /**
     * Lokasi bernama sama di dua PERUSAHAAN tetap dua kelompok.
     *
     * Tujuh perusahaan yang masing-masing punya "Kantor Site — Lantai 1
     * dan 2" dan diinspeksi sekali akan, tanpa pemisahan ini,
     * dilaporkan sebagai satu temuan yang berulang tujuh kali dalam dua
     * hari. Tidak satu pun di antaranya sebenarnya berulang — dan yang
     * membacanya mengirim orang memeriksa masalah yang tidak ada.
     */
    public function test_lokasi_senama_di_dua_perusahaan_tidak_disatukan(): void
    {
        $a = $this->perusahaan();
        $b = $this->perusahaan();

        $this->hazard($a, ['lokasi' => 'Kantor Site', 'tanggal' => now()->subDays(3)->toDateString()]);
        $this->hazard($b, ['lokasi' => 'Kantor Site', 'tanggal' => now()->subDay()->toDateString()]);

        $this->assertSame([], $this->mesin()->kelompok(),
            'Dua perusahaan berbeda menyatu menjadi satu kelompok berulang palsu.');
    }

    /** Kelompok membawa nama perusahaannya. */
    public function test_kelompok_membawa_nama_perusahaan(): void
    {
        $c = $this->perusahaan();

        $this->hazard($c, ['tanggal' => now()->subDays(40)->toDateString()]);
        $this->hazard($c, ['tanggal' => now()->subDays(5)->toDateString()]);

        $this->assertSame($c->name, $this->mesin()->kelompok()[0]['perusahaan']);
    }

    /** Hazard dan inspeksi tidak dicampur dalam satu kelompok. */
    public function test_hazard_dan_inspeksi_tidak_dicampur(): void
    {
        $c = $this->perusahaan();

        $this->hazard($c, ['lokasi' => 'Workshop', 'kategori' => 'APAR bertekanan cukup']);
        $this->butir($c, now()->subDays(5)->toDateString(), ['lokasi' => 'Workshop']);

        $this->assertSame([], $this->mesin()->kelompok(),
            'Temuan hazard dan butir inspeksi menyatu; keduanya tidak sebanding.');
    }

    /* ══════════════ kambuh ══════════════ */

    /** Muncul lagi sesudah ditutup dihitung kambuh. */
    public function test_muncul_lagi_sesudah_ditutup_terhitung_kambuh(): void
    {
        $c = $this->perusahaan();

        $this->hazard($c, [
            'tanggal'   => now()->subDays(60)->toDateString(),
            'status'    => 'Closed',
            'closed_at' => now()->subDays(50),
        ]);
        $this->hazard($c, ['tanggal' => now()->subDays(10)->toDateString()]);

        $this->assertSame(1, $this->mesin()->kelompok()[0]['kambuh']);
    }

    /** Muncul lagi sementara yang lama BELUM ditutup bukan kambuh. */
    public function test_muncul_lagi_tanpa_penutupan_bukan_kambuh(): void
    {
        $c = $this->perusahaan();

        $this->hazard($c, ['tanggal' => now()->subDays(60)->toDateString(), 'status' => 'Open']);
        $this->hazard($c, ['tanggal' => now()->subDays(10)->toDateString(), 'status' => 'Open']);

        $this->assertSame(0, $this->mesin()->kelompok()[0]['kambuh'],
            'Temuan yang belum pernah ditangani dilaporkan sebagai perbaikan yang gagal.');
    }

    /**
     * Satu penutupan diikuti lima kemunculan adalah SATU kambuh.
     *
     * Inilah yang membedakan "perbaikan yang gagal" dari "temuan yang
     * dibiarkan". Dihitung enam, angka kambuh membengkak justru pada
     * kelompok yang paling terbengkalai, dan kelompok yang ditangani
     * berulang kali dengan sungguh-sungguh tampak sama buruknya.
     */
    public function test_satu_penutupan_lalu_banyak_kemunculan_hanya_satu_kambuh(): void
    {
        $c = $this->perusahaan();

        $this->hazard($c, [
            'tanggal'   => now()->subDays(90)->toDateString(),
            'status'    => 'Closed',
            'closed_at' => now()->subDays(85),
        ]);

        foreach ([70, 50, 30, 20, 10] as $lalu) {
            $this->hazard($c, ['tanggal' => now()->subDays($lalu)->toDateString(), 'status' => 'Open']);
        }

        $k = $this->mesin()->kelompok()[0];

        $this->assertSame(6, $k['jumlah']);
        $this->assertSame(1, $k['kambuh'],
            'Lima kemunculan yang belum tertangani terhitung sebagai lima perbaikan gagal.');
    }

    /** Ditutup lalu muncul lagi, berulang tiga kali, adalah tiga kambuh. */
    public function test_tiga_daur_tutup_muncul_adalah_tiga_kambuh(): void
    {
        $c = $this->perusahaan();

        foreach ([[100, 95], [80, 75], [60, 55]] as [$muncul, $tutup]) {
            $this->hazard($c, [
                'tanggal'   => now()->subDays($muncul)->toDateString(),
                'status'    => 'Closed',
                'closed_at' => now()->subDays($tutup),
            ]);
        }

        $this->hazard($c, ['tanggal' => now()->subDays(10)->toDateString()]);

        $this->assertSame(3, $this->mesin()->kelompok()[0]['kambuh']);
    }

    /**
     * Butir inspeksi TANPA tindakan tercatat tidak melahirkan kambuh.
     *
     * Menganggapnya selesai hanya karena inspeksinya sudah lewat akan
     * melaporkan perbaikan gagal yang tidak pernah terjadi — tidak ada
     * perbaikan apa pun yang dicatat.
     */
    public function test_butir_inspeksi_tanpa_tindakan_bukan_kambuh(): void
    {
        $c = $this->perusahaan();

        $this->butir($c, now()->subDays(40)->toDateString(), ['tindakan' => null]);
        $this->butir($c, now()->subDays(5)->toDateString(), ['tindakan' => null]);

        $k = $this->mesin()->kelompok()[0];

        $this->assertSame(2, $k['jumlah']);
        $this->assertSame(0, $k['kambuh']);
    }

    /** Butir inspeksi dengan tindakan tercatat lalu muncul lagi adalah kambuh. */
    public function test_butir_inspeksi_bertindakan_lalu_muncul_lagi_adalah_kambuh(): void
    {
        $c = $this->perusahaan();

        $this->butir($c, now()->subDays(40)->toDateString(), ['tindakan' => 'Sudah diisi ulang.']);
        $this->butir($c, now()->subDays(5)->toDateString(), ['tindakan' => null]);

        $this->assertSame(1, $this->mesin()->kelompok()[0]['kambuh']);
    }

    /* ══════════════ urutan dan pola ══════════════ */

    /**
     * Yang kambuh berada di atas yang sekadar banyak.
     *
     * Lima temuan sejenis pada satu bulan boleh jadi satu sapuan
     * inspeksi; satu temuan yang kembali sesudah ditangani adalah
     * perbaikan yang gagal. Yang kedua lebih mendesak meski angkanya
     * lebih kecil.
     */
    public function test_kelompok_berkambuh_berada_di_atas(): void
    {
        $c = $this->perusahaan();

        // Banyak, tetapi tidak pernah ditangani: 5 kemunculan, 0 kambuh.
        foreach ([50, 40, 30, 20, 10] as $lalu) {
            $this->hazard($c, [
                'lokasi'   => 'Pit Ramai',
                'tanggal'  => now()->subDays($lalu)->toDateString(),
                'status'   => 'Open',
            ]);
        }

        // Sedikit, tetapi kambuh sekali.
        $this->hazard($c, [
            'lokasi'    => 'Pit Kambuh',
            'tanggal'   => now()->subDays(60)->toDateString(),
            'status'    => 'Closed',
            'closed_at' => now()->subDays(55),
        ]);
        $this->hazard($c, ['lokasi' => 'Pit Kambuh', 'tanggal' => now()->subDays(5)->toDateString()]);

        $k = $this->mesin()->kelompok();

        $this->assertSame('Pit Kambuh', $k[0]['lokasi'],
            'Kelompok berjumlah besar menyalip kelompok yang perbaikannya gagal.');
        $this->assertSame('Pit Ramai', $k[1]['lokasi']);
    }

    /** Garis bulanan selalu selebar jendela peninjauan, termasuk bulan kosong. */
    public function test_garis_bulanan_selalu_dua_belas_titik(): void
    {
        $c = $this->perusahaan();

        $this->hazard($c, ['tanggal' => now()->subDays(40)->toDateString()]);
        $this->hazard($c, ['tanggal' => now()->subDays(5)->toDateString()]);

        $g = $this->mesin()->kelompok()[0];

        $this->assertCount(TemuanBerulang::BULAN, $g['bulanan']);
        $this->assertSame($g['jumlah'], array_sum(array_column($g['bulanan'], 'nilai')));
    }

    /** Rentang adalah jarak hari dari kemunculan pertama ke terakhir. */
    public function test_rentang_dihitung_dari_pertama_ke_terakhir(): void
    {
        $c = $this->perusahaan();

        $this->hazard($c, ['tanggal' => now()->subDays(30)->toDateString()]);
        $this->hazard($c, ['tanggal' => now()->subDays(20)->toDateString()]);
        $this->hazard($c, ['tanggal' => now()->subDays(10)->toDateString()]);

        $this->assertSame(20, $this->mesin()->kelompok()[0]['rentang']);
    }

    /** Bulan berbeda dihitung, bukan jumlah kemunculan. */
    public function test_bulan_berbeda_dihitung_terpisah_dari_jumlah(): void
    {
        $c = $this->perusahaan();

        // Tiga kali pada hari yang sama — satu bulan saja.
        foreach (range(1, 3) as $_) {
            $this->hazard($c, ['tanggal' => now()->startOfMonth()->addDay()->toDateString()]);
        }

        $g = $this->mesin()->kelompok()[0];

        $this->assertSame(3, $g['jumlah']);
        $this->assertSame(1, $g['bulan'],
            'Tiga kemunculan pada satu hari terbaca sebagai tiga bulan berbeda.');
    }

    /* ══════════════ hirarki dan sorotan ══════════════ */

    /** Butir inspeksi tidak menyumbang hirarki — kolomnya memang kosong. */
    public function test_butir_inspeksi_tidak_menyumbang_hirarki(): void
    {
        $c = $this->perusahaan();

        $this->butir($c, now()->subDays(40)->toDateString());
        $this->butir($c, now()->subDays(5)->toDateString());

        $m = $this->mesin();
        $h = $m->hirarki($m->kelompok());

        $this->assertSame(0, $h['total']);
        $this->assertSame([], $h['potong']);
    }

    /** Pengendalian lemah pada temuan berulang disebut di sorotan. */
    public function test_sorotan_menyebut_pengendalian_lemah(): void
    {
        $c = $this->perusahaan();

        foreach ([60, 40, 20] as $lalu) {
            $this->hazard($c, [
                'tanggal' => now()->subDays($lalu)->toDateString(),
                'hirarki' => 'Administratif',
            ]);
        }

        $m = $this->mesin();
        $k = $m->kelompok();
        $teks = implode(' ', array_column($m->insight($k, $m->hirarki($k)), 'teks'));

        $this->assertStringContainsString('administratif atau APD', $teks);
    }

    /** Pengendalian kuat tidak melahirkan kalimat itu. */
    public function test_pengendalian_kuat_tidak_disebut_lemah(): void
    {
        $c = $this->perusahaan();

        foreach ([60, 40, 20] as $lalu) {
            $this->hazard($c, [
                'tanggal' => now()->subDays($lalu)->toDateString(),
                'hirarki' => 'Rekayasa',
            ]);
        }

        $m = $this->mesin();
        $k = $m->kelompok();
        $teks = implode(' ', array_column($m->insight($k, $m->hirarki($k)), 'teks'));

        $this->assertStringNotContainsString('administratif atau APD', $teks);
    }

    /** Tanpa kelompok berulang, sorotan mengatakannya apa adanya. */
    public function test_tanpa_kelompok_sorotan_mengaku_kosong(): void
    {
        $m = $this->mesin();
        $s = $m->insight([], $m->hirarki([]));

        $this->assertCount(1, $s);
        $this->assertSame('baik', $s[0]['nada']);
        $this->assertStringContainsString('Tidak ada temuan yang berulang', $s[0]['teks']);
    }

    /* ══════════════ halaman ══════════════ */

    public function test_halaman_menyajikan_kartu_sorotan_dan_kelompok(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->hazard($c, ['tanggal' => now()->subDays(40)->toDateString()]);
        $this->hazard($c, ['tanggal' => now()->subDays(5)->toDateString()]);

        $this->get(route('hazard.berulang'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Hazard/Berulang')
                ->has('kartu', 4)
                ->has('sorotan')
                ->has('kelompok', 1)
                ->has('hirarki')
                ->where('bulanTinjau', TemuanBerulang::BULAN)
                ->etc());
    }

    /** Saringan sumber memotong sesuai pilihan. */
    public function test_saringan_sumber_memotong(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->hazard($c, ['tanggal' => now()->subDays(40)->toDateString()]);
        $this->hazard($c, ['tanggal' => now()->subDays(5)->toDateString()]);
        $this->butir($c, now()->subDays(40)->toDateString());
        $this->butir($c, now()->subDays(5)->toDateString());

        $this->get(route('hazard.berulang', ['sumber' => 'Hazard']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('kelompok', 1)
                ->where('kelompok.0.sumber', 'Hazard')
                ->etc());
    }

    /**
     * Temuan lebih lama daripada jendela peninjauan tidak ikut.
     *
     * Tanpa batas, kelompok yang terakhir muncul tiga tahun lalu tetap
     * menempati baris teratas — dan yang membacanya mengejar sesuatu
     * yang sudah lama selesai.
     */
    public function test_temuan_di_luar_jendela_tidak_ikut(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->hazard($c, ['tanggal' => now()->subMonths(30)->toDateString()]);
        $this->hazard($c, ['tanggal' => now()->subMonths(28)->toDateString()]);

        $this->get(route('hazard.berulang'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->has('kelompok', 0)->etc());
    }

    /** Perusahaan lain tidak ikut terbaca. */
    public function test_perusahaan_lain_tidak_ikut(): void
    {
        $tetangga = $this->perusahaan();
        $this->hazard($tetangga, ['lokasi' => 'Pit Tetangga', 'tanggal' => now()->subDays(40)->toDateString()]);
        $this->hazard($tetangga, ['lokasi' => 'Pit Tetangga', 'tanggal' => now()->subDays(5)->toDateString()]);

        $saya = $this->perusahaan();
        $this->actingAs(User::factory()->create(['is_admin' => false, 'company_id' => $saya->id]));

        $this->get(route('hazard.berulang'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->has('kelompok', 0)->etc());
    }

    public function test_tamu_tidak_dapat_membuka(): void
    {
        $this->get(route('hazard.berulang'))->assertRedirect(route('login'));
    }
}
