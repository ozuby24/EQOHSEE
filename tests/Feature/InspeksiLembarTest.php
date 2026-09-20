<?php

namespace Tests\Feature;

use App\Models\{Company, Inspection, InspectionInspector, InspectionItem,
                InspectionTemplate, InspectionTemplateItem, User};
use App\Support\MasterInspeksi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Daftar periksa baku dan lembar inspeksi yang ditandatangani.
 */
class InspeksiLembarTest extends TestCase
{
    use RefreshDatabase;

    private static int $n = 0;

    private function perusahaan(): Company
    {
        $i = ++self::$n;

        return Company::create(['name' => "PT Uji {$i}", 'code' => "PU{$i}"]);
    }

    private function masuk(?Company $c = null): User
    {
        $u = User::factory()->create(['is_admin' => true, 'company_id' => $c?->id]);
        $this->actingAs($u);

        return $u;
    }

    /** Satu inspeksi berbutir, dengan kondisi yang ditentukan pemanggil. */
    private function inspeksi(Company $c, array $kondisi, array $x = []): Inspection
    {
        $i = Inspection::create(array_merge([
            'kode'       => 'INS-'.str_pad((string) ++self::$n, 4, '0', STR_PAD_LEFT),
            'company_id' => $c->id,
            'judul'      => 'Inspeksi Uji',
            'jenis'      => 'Bulanan',
            'lokasi'     => 'Kantor Uji',
            'tanggal'    => now()->toDateString(),
            'status'     => 'Selesai',
        ], $x));

        foreach ($kondisi as $j => [$kelompok, $k]) {
            InspectionItem::create([
                'inspection_id' => $i->id,
                'kelompok'      => $kelompok,
                'uraian'        => "Butir uji ke-".($j + 1),
                'risiko'        => 'Sedang',
                'kondisi'       => $k,
                'order_index'   => $j + 1,
            ]);
        }

        return $i;
    }

    /* ══════════════ pustaka baku ══════════════ */

    /**
     * Tidak ada template tanpa butir — di pustaka maupun di data contoh.
     *
     * Template kosong tidak menimbulkan galat apa pun: ia tampil di
     * daftar pilihan seperti template lain, dan yang memilihnya mendapat
     * inspeksi yang terbuka rapi dengan nol baris untuk diisi. Yang
     * mengalaminya menyimpulkan modul inspeksinya rusak, bukan
     * templatenya yang memang kosong.
     */
    public function test_tidak_ada_template_baku_tanpa_butir(): void
    {
        foreach (MasterInspeksi::PUSTAKA as $t) {
            $this->assertNotEmpty($t[4], "Template baku '{$t[0]}' tidak punya satu butir pun.");
        }
    }

    public function test_pustaka_memuat_inspeksi_kantor_yang_berkelompok(): void
    {
        $kantor = MasterInspeksi::menurutNama('Inspeksi K3 Perkantoran');

        $this->assertNotNull($kantor, 'Inspeksi K3 Perkantoran tidak ada di pustaka baku.');

        $kelompok = array_unique(array_column($kantor[4], 0));

        $this->assertGreaterThanOrEqual(20, count($kantor[4]),
            'Daftar periksa kantor terlalu pendek untuk dipakai auditor.');
        $this->assertGreaterThanOrEqual(5, count($kelompok),
            'Butirnya tidak dikelompokkan; lembar tanpa kelompok dibaca sebagai satu daftar panjang.');
    }

    /**
     * Acuan yang disebut harus benar-benar dirujuk, bukan diketik lepas.
     *
     * Acuan ditulis sebagai tetapan lalu dirujuk. Yang diketik langsung
     * pada barisnya lolos tanpa galat tetapi berbeda satu spasi atau
     * satu titik dari yang lain — dan auditor yang menyaring menurut
     * acuan tidak akan menemukannya.
     */
    public function test_acuan_butir_konsisten_bentuknya(): void
    {
        $bentuk = [];

        foreach (MasterInspeksi::PUSTAKA as $t) {
            foreach ($t[4] as $b) {
                if ($b[2] !== null) $bentuk[$b[2]] = true;
            }
        }

        foreach (array_keys($bentuk) as $acuan) {
            $this->assertMatchesRegularExpression(
                '/^(UU|Permenaker|Permenakertrans|Permenkes|Kepmenaker|Kepmen ESDM)\b/',
                $acuan,
                "Acuan '{$acuan}' tidak berbentuk sebutan peraturan yang dikenali.",
            );
        }
    }

    /* ══════════════ lembar ══════════════ */

    public function test_lembar_terbit_dengan_kop_identitas_dan_kelompok(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $i = $this->inspeksi($c, [
            ['Instalasi Listrik', 'Sesuai'],
            ['Instalasi Listrik', 'Tidak Sesuai'],
            ['Jalur Evakuasi',    'Sesuai'],
        ]);

        $this->get(route('inspeksi.lembar', $i))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Print/InspeksiLembar')
                ->has('dok')
                ->where('i.kode', $i->kode)
                ->has('kelompok', 2)
                ->where('kelompok.0.nama', 'Instalasi Listrik')
                ->has('kelompok.0.butir', 2)
                ->where('kelompok.1.nama', 'Jalur Evakuasi'));
    }

    public function test_rekap_lembar_menjumlah_seluruh_butirnya(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $i = $this->inspeksi($c, [
            ['A', 'Sesuai'], ['A', 'Sesuai'], ['A', 'Sesuai'],
            ['B', 'Tidak Sesuai'],
            ['B', 'N/A'], ['B', 'N/A'],
        ]);

        $this->get(route('inspeksi.lembar', $i))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('rekap.total', 6)
                ->where('rekap.sesuai', 3)
                ->where('rekap.tidakSesuai', 1)
                ->where('rekap.na', 2)
                ->where('rekap.belum', 0)
                ->etc());
    }

    /**
     * Butir yang sudah dinilai dan butir yang belum TIDAK disatukan.
     *
     * Keduanya berbeda arti sejauh-jauhnya: "tidak berlaku" adalah
     * keputusan pemeriksa, "belum dinilai" adalah pekerjaan yang belum
     * selesai. Lembar yang menghitung keduanya sebagai satu memulangkan
     * inspeksi setengah jadi sebagai inspeksi lengkap.
     */
    public function test_butir_belum_dinilai_terhitung_terpisah_dari_na(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $i = $this->inspeksi($c, [['A', 'Sesuai'], ['A', 'N/A'], ['A', null]]);

        $this->get(route('inspeksi.lembar', $i))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('rekap.na', 1)
                ->where('rekap.belum', 1)
                ->etc());
    }

    public function test_pemeriksa_ikut_ke_lembar_untuk_blok_tanda_tangan(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $i = $this->inspeksi($c, [['A', 'Sesuai']]);

        InspectionInspector::create([
            'inspection_id' => $i->id, 'nama' => 'Budi', 'jabatan' => 'Pengawas', 'peran' => 'Ketua',
        ]);
        InspectionInspector::create([
            'inspection_id' => $i->id, 'nama' => 'Sari', 'jabatan' => 'Safety Officer', 'peran' => 'Anggota',
        ]);

        $this->get(route('inspeksi.lembar', $i))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('pemeriksa', 2)
                ->where('pemeriksa.0.nama', 'Budi')
                ->where('pemeriksa.1.peran', 'Anggota')
                ->etc());
    }

    public function test_perusahaan_lain_tidak_dapat_membuka_lembarnya(): void
    {
        $milikku  = $this->perusahaan();
        $tetangga = $this->perusahaan();

        $i = $this->inspeksi($tetangga, [['A', 'Sesuai']]);

        $this->actingAs(User::factory()->create(['is_admin' => false, 'company_id' => $milikku->id]));

        $this->get(route('inspeksi.lembar', $i))->assertNotFound();
    }

    public function test_tamu_tidak_dapat_membuka_lembarnya(): void
    {
        $c = $this->perusahaan();
        $i = $this->inspeksi($c, [['A', 'Sesuai']]);

        $this->get(route('inspeksi.lembar', $i))->assertRedirect(route('login'));
    }

    /* ══════════════ inspeksi dari template ══════════════ */

    /**
     * Membuat inspeksi dari template menyalin SELURUH butirnya.
     *
     * Ini janji utama modulnya: pengawas memilih jenis inspeksi dan
     * daftar periksanya sudah ada. Penyalinan yang diam-diam berhenti
     * di tengah menghasilkan lembar yang tampak lengkap tetapi kehilangan
     * butir — dan yang hilang tidak dapat diketahui dari lembar itu
     * sendiri.
     */
    public function test_inspeksi_dari_template_menyalin_seluruh_butirnya(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $t = InspectionTemplate::create([
            'nama' => 'Template Uji', 'jenis' => 'Bulanan', 'kategori' => 'Kantor', 'is_active' => true,
        ]);

        foreach (['Listrik', 'Kebakaran', 'Evakuasi'] as $j => $kelompok) {
            InspectionTemplateItem::create([
                'template_id' => $t->id, 'kelompok' => $kelompok,
                'uraian' => "Butir {$kelompok}", 'risiko_default' => 'Tinggi', 'order_index' => $j + 1,
            ]);
        }

        $this->post(route('inspeksi.store'), [
            'judul' => 'Inspeksi Baru', 'template_id' => $t->id, 'company_id' => $c->id,
            'tanggal' => now()->toDateString(), 'lokasi' => 'Kantor', 'jenis' => 'Bulanan',
        ])->assertRedirect();

        $baru = Inspection::where('judul', 'Inspeksi Baru')->firstOrFail();

        $this->assertSame(3, $baru->items()->count(),
            'Butir template tidak tersalin seluruhnya ke inspeksi baru.');
        $this->assertSame(['Listrik', 'Kebakaran', 'Evakuasi'],
            $baru->items()->orderBy('order_index')->pluck('kelompok')->all());
    }
}
