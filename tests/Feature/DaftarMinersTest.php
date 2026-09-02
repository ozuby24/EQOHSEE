<?php

namespace Tests\Feature;

use App\Models\{Company, McuPengajuan, Paspor, PasporKartu, PasporKartuUnit, User};
use App\Support\{Alur, AlurMiner, Tahap};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Sembilan daftar menyilang orang.
 *
 * ── YANG DIJAGA, DAN MENGAPA ──
 *
 * Ketiga jenis kegagalan di bawah tidak menimbulkan satu pun galat, dan
 * ketiganya membuat orang mengambil keputusan dari layar yang salah.
 *
 * PERTAMA, Outstanding yang memperlihatkan antrean ORANG LAIN. Daftar
 * ini menjawab "apa yang menunggu keputusan saya"; bila penyaringnya
 * lepas, yang tampil adalah seluruh antrean dan pembacanya menyimpulkan
 * ada belasan berkas menunggu dirinya padahal tidak satu pun.
 *
 * KEDUA, daftar cetak yang memuat kartu belum terbit. Kartu draf yang
 * ikut tercetak bersama setumpuk kartu sah adalah kartu tidak sah yang
 * dibawa orang ke gerbang — dan tidak ada satu pun tanda di kertasnya
 * yang membedakannya.
 *
 * KETIGA, sel tabel yang berisi larik. `parafTertinggal()` memulangkan
 * larik nama tahap; ditaruh apa adanya ia tergambar sebagai
 * "[object Array]" — bukan galat, hanya kolom yang tidak berarti
 * apa-apa bagi pembacanya. Sudah pernah terjadi persis begitu.
 */
class DaftarMinersTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;

    protected function setUp(): void
    {
        parent::setUp();

        $this->c = Company::create(['name' => 'PT Uji Daftar', 'code' => 'UDF']);
    }

    private function pengguna(array $x = []): User
    {
        return User::factory()->create($x + [
            'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);
    }

    private function orang(array $x = []): Paspor
    {
        return Paspor::withoutGlobalScopes()->create($x + [
            'company_id' => $this->c->id,
            'nama' => 'Pekerja Uji', 'nik' => 'NIK'.random_int(1000, 9999),
            'jabatan' => 'Operator', 'status' => 'aktif',
        ]);
    }

    /**
     * `status` DISETEL LEWAT update(), bukan lewat create().
     *
     * Kolom itu sengaja tidak dapat diisi massal — penjagaan supaya
     * sebuah formulir tidak dapat menyebut statusnya sendiri. Fixture
     * yang menitipkannya ke create() diam-diam menghasilkan kartu draf,
     * dan uji yang bergantung padanya lulus atau gagal karena alasan
     * yang sama sekali lain dari yang tertulis di namanya.
     */
    private function kartu(Paspor $p, string $status = Alur::DRAF, array $x = []): PasporKartu
    {
        $k = PasporKartu::withoutGlobalScopes()->create($x + [
            'paspor_id' => $p->id,
            'jenis' => AlurMiner::KARTU_PERMIT,
            'sebab_terbit' => 'Terbit',
        ]);

        PasporKartu::withoutGlobalScopes()->whereKey($k->id)->update(['status' => $status]);

        return $k->refresh();
    }

    private function jalur(string $j): string
    {
        return '/miners/daftar/'.$j;
    }

    /** @return list<array<int,string>> baris tabel, sudah jadi larik sel */
    private function baris(string $j): array
    {
        return $this->get($this->jalur($j))->assertOk()
            ->viewData('page')['props']['baris'];
    }

    /* ═══════════ kesembilannya benar-benar terbuka ═══════════ */

    /**
     * Diuji satu per satu, bukan lewat satu rute berparameter.
     *
     * Empat di antaranya sempat memulangkan 500 karena type hint yang
     * salah namespace — `?User` tanpa import terbaca sebagai
     * `App\Http\Controllers\User`. Uji yang hanya memanggil satu jenis
     * akan lulus dan meninggalkan delapan lainnya tidak pernah dijalankan.
     */
    #[Test]
    public function kesembilan_daftar_terbuka_dan_memakai_komponen_yang_sama(): void
    {
        $this->actingAs($this->pengguna(['is_admin' => true]));

        foreach ([
            'outstanding-mcu', 'outstanding-permit', 'outstanding-simper', 'outstanding-induksi',
            'penambahan-unit', 'upgrade-simper', 'perpanjangan', 'rujukan', 'cetak-kartu',
        ] as $j) {
            $props = $this->get($this->jalur($j))->assertOk()
                ->assertInertia(fn ($p) => $p->component('Miners/Riwayat'))
                ->viewData('page')['props'];

            $this->assertNotEmpty($props['kolom'], "Daftar {$j} tidak punya kolom.");
            $this->assertNotEmpty($props['judul'], "Daftar {$j} tidak berjudul.");
        }
    }

    #[Test]
    public function jenis_yang_tidak_dikenali_memulangkan_404(): void
    {
        $this->actingAs($this->pengguna(['is_admin' => true]));

        $this->get('/miners/daftar/bukan-jenis')->assertNotFound();
    }

    /* ═══════════ Outstanding menyaring pada MEJA SAYA ═══════════ */

    /**
     * Yang bukan peninjau melihat daftar KOSONG, bukan seluruh antrean.
     *
     * Ini penjagaan terpentingnya. Tanpa penyaring, seorang operator
     * membuka Outstanding dan melihat belasan berkas menunggu dirinya
     * — padahal tidak satu pun boleh ia putuskan.
     */
    #[Test]
    public function outstanding_kosong_bagi_yang_bukan_peninjau(): void
    {
        $p = $this->orang();

        $pengaju = $this->pengguna(['is_admin' => false]);

        $m = McuPengajuan::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'user_id' => $pengaju->id,
            'judul' => 'MCU berkala', 'tanggal' => now()->toDateString(),
        ]);
        $m->ajukan($pengaju);

        /* Peninjaunya ORANG LAIN. Pengaju tidak boleh memutuskan
           pengajuannya sendiri — itu aturan Tahap, dan memakai satu
           orang untuk kedua peran membuat uji ini menguji aturan yang
           salah. */
        $this->actingAs($this->pengguna(['is_admin' => true]));

        $this->assertCount(1, $this->baris('outstanding-mcu'),
            'Peninjau tidak melihat pengajuan yang menunggunya.');

        /* Orang lain yang bukan peninjau: daftarnya kosong. */
        $this->actingAs($this->pengguna(['is_admin' => false, 'ohse_role' => null]));

        $this->assertCount(0, $this->baris('outstanding-mcu'),
            'Antrean orang lain bocor ke pengguna yang bukan peninjau.');
    }

    /**
     * Sel tabel tidak boleh berisi larik.
     *
     * `parafTertinggal()` memulangkan larik nama tahap. Ditaruh mentah,
     * yang tergambar "[object Array]" — tanpa galat, dan tanpa arti.
     */
    #[Test]
    public function sel_tabel_seluruhnya_berupa_teks(): void
    {
        $pengaju = $this->pengguna(['is_admin' => false]);

        $m = McuPengajuan::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'user_id' => $pengaju->id,
            'judul' => 'MCU berkala', 'tanggal' => now()->toDateString(),
        ]);
        $m->ajukan($pengaju);

        $this->actingAs($this->pengguna(['is_admin' => true]));

        $baris = $this->baris('outstanding-mcu');
        $this->assertNotEmpty($baris, 'Tidak ada baris untuk diperiksa — ujinya jadi hampa.');

        foreach ($baris as $b) {
            foreach ($b['sel'] as $i => $sel) {
                $this->assertIsNotArray($sel,
                    "Sel ke-{$i} berisi larik — di layar ia tergambar sebagai [object Array].");
                $this->assertIsNotObject($sel, "Sel ke-{$i} berisi objek.");
            }
        }

        /* Dan ISINYA memang nama tahapnya, bukan sekadar bukan-larik.
           Rantai MCU punya dua tahap paraf bukan-penentu — Paramedis dan
           Kepala Teknik Tambang — dan keduanya masih kosong di sini.
           Tanpa pemeriksaan ini, sel yang selalu berbunyi "siap
           diputuskan" pun lulus, dan kolomnya berhenti memberi tahu
           siapa yang sebenarnya ditunggu. */
        $this->assertStringContainsString('Paramedis', end($baris[0]['sel']),
            'Kolom paraf tidak menyebut tahap mana yang masih ditunggu.');
        $this->assertStringContainsString('Kepala Teknik Tambang', end($baris[0]['sel']),
            'Hanya satu tahap yang disebut — sisanya hilang saat lariknya dirangkai.');
    }

    /* ═══════════ Cetak Kartu hanya memuat yang sudah terbit ═══════════ */

    /**
     * Kartu draf dan kartu yang masih ditinjau TIDAK boleh masuk.
     *
     * Kartu belum sah yang ikut tercetak bersama setumpuk kartu sah
     * tidak dapat dibedakan lagi di kertas.
     */
    #[Test]
    public function cetak_kartu_menolak_yang_belum_terbit(): void
    {
        $this->actingAs($this->pengguna(['is_admin' => true]));

        $p = $this->orang();

        $this->kartu($p, Alur::DRAF,      ['nomor' => 'DRAF-1']);
        $this->kartu($p, Alur::DIAJUKAN,  ['nomor' => 'TINJAU-1']);
        $this->kartu($p, Alur::DISETUJUI, ['nomor' => 'TERBIT-1']);

        $nomor = array_column(array_map(fn ($b) => ['n' => $b['nomor']], $this->baris('cetak-kartu')), 'n');

        $this->assertSame(['TERBIT-1'], $nomor,
            'Daftar cetak memuat kartu yang belum terbit: '.implode(', ', $nomor));
    }

    /** Tiap baris cetak membawa alamat cetaknya sendiri. */
    #[Test]
    public function tiap_baris_cetak_membawa_alamatnya(): void
    {
        $this->actingAs($this->pengguna(['is_admin' => true]));

        $p = $this->orang();
        $k = $this->kartu($p, Alur::DISETUJUI, ['nomor' => 'TERBIT-1']);

        $baris = $this->baris('cetak-kartu');

        $this->assertCount(1, $baris);
        $this->assertStringContainsString('/miners/'.$p->id.'/kartu/'.$k->id.'/cetak',
            $baris[0]['cetak'], 'Baris cetak tidak menunjuk lembar cetaknya.');
    }

    /* ═══════════ SIMPER Lanjutan menyaring pada sebab terbitnya ═══════════ */

    #[Test]
    public function perpanjangan_dan_upgrade_terpisah_menurut_sebab_terbitnya(): void
    {
        $this->actingAs($this->pengguna(['is_admin' => true]));

        $p = $this->orang();

        $this->kartu($p, Alur::DRAF, ['nomor' => 'BARU',    'sebab_terbit' => 'Terbit']);
        $this->kartu($p, Alur::DRAF, ['nomor' => 'PANJANG', 'sebab_terbit' => 'Perpanjangan']);
        $this->kartu($p, Alur::DRAF, ['nomor' => 'NAIK',    'sebab_terbit' => 'Peningkatan golongan']);

        $this->assertSame(['PANJANG'], array_column($this->baris('perpanjangan'), 'nomor'));
        $this->assertSame(['NAIK'], array_column($this->baris('upgrade-simper'), 'nomor'));
    }

    /* ═══════════ Penambahan Unit: satu baris per unit ═══════════ */

    /**
     * Satu SIMPER dengan tiga unit menghasilkan TIGA baris, bukan satu.
     *
     * Diringkas menjadi satu baris per kartu, unit yang lembar ujinya
     * belum lengkap tidak pernah terlihat — dan justru itu yang dicari
     * orang yang membuka halaman ini.
     */
    #[Test]
    public function penambahan_unit_satu_baris_per_unit(): void
    {
        $this->actingAs($this->pengguna(['is_admin' => true]));

        $p = $this->orang();
        $k = $this->kartu($p, Alur::DRAF, ['jenis' => AlurMiner::KARTU_LICENSE, 'nomor' => 'ML-1']);

        foreach ([[80, 80], [50, 90], [null, null]] as [$p2h, $praktek]) {
            PasporKartuUnit::withoutGlobalScopes()->create([
                'paspor_kartu_id' => $k->id,
                'jenis_unit'      => 'EXCAVATOR',
                'authority'       => 'T',
                'nilai_p2h'       => $p2h,
                'nilai_praktek'   => $praktek,
            ]);
        }

        $baris = $this->baris('penambahan-unit');

        $this->assertCount(3, $baris, 'Unitnya diringkas menjadi satu baris per kartu.');

        /* Kolom uji menyebut mana yang belum lulus — angka ringkas tanpa
           kolomnya hanya memberi tahu bahwa ada masalah. */
        $uji = array_map(fn ($b) => end($b['sel']), $baris);
        sort($uji);

        $this->assertSame(['belum lulus', 'belum lulus', 'lulus'], $uji);
    }

    /* ═══════════ menu menunjuk rute yang benar-benar ada ═══════════ */

    /**
     * Tiap entri menu Miners dapat dibuka.
     *
     * Entri yang menunjuk rute yang belum ada memulangkan galat saat
     * ditekan, dan satu entri galat membuat seluruh menunya berhenti
     * dipercaya.
     */
    #[Test]
    public function tiap_entri_menu_miners_terbuka(): void
    {
        $this->actingAs($this->pengguna(['is_admin' => true]));

        $modul = \App\Support\Menu::all()['miners'];

        $jumlah = 0;

        foreach ($modul['groups'] as $butir) {
            foreach ($butir as [$label, $rute, $pola]) {
                $this->get(route($rute))->assertOk();
                $jumlah++;
            }
        }

        $this->assertGreaterThanOrEqual(16, $jumlah,
            'Menu Miners menyusut — susunan Project1 memuat lebih dari itu.');
    }
}
