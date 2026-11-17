<?php

namespace Tests\Feature;

use App\Models\{Company, GeoInstrumen, GeoLereng, User, WaterSump, WaterSumpPump};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batas perusahaan bagi baris yang pemiliknya ada pada induknya.
 *
 * Daftar tidak pernah bocor — daftar selalu digambar lewat induknya,
 * jadi induknya sudah menyaring. Yang bocor adalah ROUTE yang mengikat
 * anaknya langsung: `PUT /geoteknik/instrumen/{instrumen}` tidak pernah
 * menyentuh lerengnya sama sekali, dan controller-nya tidak memeriksa
 * apa pun sebelum menyimpan.
 *
 * Yang dapat diubah lewat celah itu bukan data sepele. Status instrumen
 * geoteknik dan status pompa penirisan adalah dua hal yang dibaca orang
 * untuk memutuskan apakah suatu tempat masih aman dimasuki.
 */
class BatasIndukTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;
    private Company $b;
    private User $orangA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Company::create(['name' => 'PT Alpha']);
        $this->b = Company::create(['name' => 'PT Beta']);
        $this->orangA = User::factory()->create(['company_id' => $this->a->id]);
    }

    private function instrumenMilik(Company $c): GeoInstrumen
    {
        $lereng = GeoLereng::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'kode' => 'LRG-'.$c->id, 'nama' => 'Lereng '.$c->name,
            'tinggi_m' => 40, 'sudut_derajat' => 38,
        ]);

        return GeoInstrumen::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'geo_lereng_id' => $lereng->id,
            'kode' => 'PRISM-'.$c->id, 'jenis' => 'prisma', 'status' => 'aktif',
        ]);
    }

    private function pompaMilik(Company $c): WaterSumpPump
    {
        $kolam = WaterSump::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'kode' => 'SMP-'.$c->id, 'nama' => 'Kolam '.$c->name,
        ]);

        return WaterSumpPump::withoutGlobalScopes()->create([
            'water_sump_id' => $kolam->id, 'nama' => 'Pompa 1',
            'kapasitas_m3_jam' => 250, 'status' => 'jalan',
        ]);
    }

    /* ---------- instrumen geoteknik ---------- */

    public function test_status_instrumen_perusahaan_lain_tidak_dapat_diubah(): void
    {
        $milikB = $this->instrumenMilik($this->b);

        $this->actingAs($this->orangA)
            ->put(route('geoteknik.instrumen.ubah', $milikB), ['status' => 'rusak'])
            ->assertNotFound();

        $this->assertSame('aktif', GeoInstrumen::withoutGlobalScopes()->find($milikB->id)->status);
    }

    public function test_status_instrumen_sendiri_tetap_dapat_diubah(): void
    {
        $milikA = $this->instrumenMilik($this->a);

        $this->actingAs($this->orangA)
            ->put(route('geoteknik.instrumen.ubah', $milikA), ['status' => 'rusak'])
            ->assertSessionHasNoErrors();

        $this->assertSame('rusak', GeoInstrumen::withoutGlobalScopes()->find($milikA->id)->status);
    }

    /* ---------- pompa penirisan ---------- */

    public function test_status_pompa_perusahaan_lain_tidak_dapat_diubah(): void
    {
        $milikB = $this->pompaMilik($this->b);

        $this->actingAs($this->orangA)
            ->put(route('air.pompa.ubah', $milikB), ['status' => 'rusak'])
            ->assertNotFound();

        $this->assertSame('jalan', WaterSumpPump::withoutGlobalScopes()->find($milikB->id)->status);
    }

    public function test_status_pompa_sendiri_tetap_dapat_diubah(): void
    {
        $milikA = $this->pompaMilik($this->a);

        $this->actingAs($this->orangA)
            ->put(route('air.pompa.ubah', $milikA), ['status' => 'rusak'])
            ->assertSessionHasNoErrors();

        $this->assertSame('rusak', WaterSumpPump::withoutGlobalScopes()->find($milikA->id)->status);
    }

    /* ---------- administrator ---------- */

    public function test_administrator_tetap_menjangkau_anak_seluruh_perusahaan(): void
    {
        $milikB = $this->instrumenMilik($this->b);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->put(route('geoteknik.instrumen.ubah', $milikB), ['status' => 'rusak'])
            ->assertSessionHasNoErrors();

        $this->assertSame('rusak', GeoInstrumen::withoutGlobalScopes()->find($milikB->id)->status);
    }

    /* ---------- syarat yang dipegang trait-nya ---------- */

    /**
     * BerindukPerusahaan menyaring dengan satu whereHas, tanpa
     * kelonggaran bagi anak tanpa induk — dan itu hanya benar selama
     * anak tanpa induk memang tidak dapat ada.
     *
     * Uji ini yang memegang pernyataan itu. Begitu ada tabel baru
     * memakai trait ini dengan kunci induk yang boleh NULL, barisnya
     * akan hilang dari tampilan tanpa galat apa pun — dan yang
     * menemukannya adalah uji ini, bukan pengguna yang datanya raib.
     *
     * Kelonggaran itu tidak dapat sekadar ditambahkan kembali:
     * percobaan `whereHas OR doesntHave` membuat penjaganya bernilai
     * benar untuk setiap baris. Bila suatu saat memang diperlukan,
     * jalannya lewat kolom kuncinya yang NULL, bukan lewat doesntHave.
     */
    public function test_kunci_induk_tidak_boleh_null(): void
    {
        $langgar = [];

        /* Ikut menelusuri anak folder. Sebelumnya hanya Models/*.php
           yang dibaca, sehingga seluruh model di bawah Models/Investigasi
           dan Models/Pjp — yang justru paling banyak memakai trait ini —
           tidak pernah diperiksa sama sekali. Penjaga yang melewati
           sebagian besar yang harus dijaganya tetap hijau, dan
           kehijauannya yang membuatnya tidak pernah diperiksa ulang. */
        $semua = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Models'))
        );

        foreach ($semua as $f) {
            if (!$f->isFile() || $f->getExtension() !== 'php') continue;

            $berkas = $f->getPathname();
            $isi = file_get_contents($berkas);
            if (!str_contains($isi, 'use BerindukPerusahaan;')) continue;

            $ruang = str_replace('/', '\\', trim(
                str_replace(app_path('Models'), '', dirname($berkas)), '/'
            ));

            $kelas = 'App\\Models\\'.($ruang ? $ruang.'\\' : '').basename($berkas, '.php');

            /* Trait-nya sendiri menyebut `use BerindukPerusahaan;` di
               dalam contoh pemakaian pada komentarnya. class_exists()
               memulangkan false bagi trait, jadi satu baris ini cukup —
               tanpa perlu mengecualikan folder Concerns dengan nama. */
            if (!class_exists($kelas)) continue;

            $model = new $kelas;

            $ref = new \ReflectionClass($kelas);
            $prop = $ref->getProperty('indukPerusahaan');
            $prop->setAccessible(true);
            $relasi = $prop->getValue();

            $tabel = $model->getTable();
            $kunci = $model->{$relasi}()->getForeignKeyName();

            /* Dibaca lewat Schema, bukan PRAGMA. PRAGMA hanya ada di
               SQLite; uji ini karena itu dahulu meledak begitu suitenya
               dijalankan pada penggerak yang sungguhnya dipakai server. */
            $kolom = collect(\Illuminate\Support\Facades\Schema::getColumns($tabel))
                ->firstWhere('name', $kunci);

            if ($kolom && ($kolom['nullable'] ?? false)) {
                $langgar[] = "{$tabel}.{$kunci}";
            }
        }

        $this->assertSame([], $langgar,
            'Kunci induk yang boleh NULL membuat barisnya hilang dari tampilan tanpa galat: '
            .implode(', ', $langgar));
    }

    /* ---------- daftar ---------- */

    public function test_daftar_instrumen_hanya_memuat_milik_sendiri(): void
    {
        $this->instrumenMilik($this->a);
        $this->instrumenMilik($this->b);

        $this->actingAs($this->orangA);

        $kode = GeoInstrumen::pluck('kode')->all();

        $this->assertContains('PRISM-'.$this->a->id, $kode);
        $this->assertNotContains('PRISM-'.$this->b->id, $kode);
    }
}
