<?php

namespace Tests\Feature;

use App\Models\{Company, HazardReport, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batas data per perusahaan.
 *
 * Yang diuji di sini bukan tampilan sebuah halaman melainkan janji yang
 * mendasarinya: satu perusahaan tidak dapat membaca data perusahaan lain,
 * termasuk lewat jalan yang tidak lewat halaman indeks — penyaring yang
 * disebut sendiri di alamat, hitungan statistik, dan pengambilan satu
 * baris berdasarkan id.
 */
class BatasPerusahaanTest extends TestCase
{
    use RefreshDatabase;

    private static int $n = 0;

    private function perusahaan(): Company
    {
        $i = ++self::$n;

        return Company::create(['name' => "PT Uji Batas {$i}", 'code' => "PUB{$i}"]);
    }

    private function laporan(?Company $c, string $deskripsi): HazardReport
    {
        return HazardReport::create([
            'kode'         => 'HR-'.str_pad((string) ++self::$n, 4, '0', STR_PAD_LEFT),
            'company_id'   => $c?->id,
            'pelapor_nama' => 'Pelapor Uji',
            'tanggal'    => now()->toDateString(),
            'lokasi'     => 'Area uji',
            'risiko'     => 'Tinggi',
            'kategori'   => 'Unsafe Condition',
            'deskripsi'  => $deskripsi,
            'status'     => 'Open',
        ]);
    }

    public function test_pengguna_tidak_melihat_laporan_perusahaan_lain(): void
    {
        $a = $this->perusahaan();
        $b = $this->perusahaan();

        $this->laporan($a, 'Milik perusahaan A');
        $this->laporan($b, 'Milik perusahaan B');

        $this->actingAs(User::factory()->create(['is_admin' => false, 'company_id' => $a->id]));

        $terlihat = HazardReport::pluck('deskripsi')->all();

        $this->assertSame(['Milik perusahaan A'], $terlihat);
    }

    public function test_penyaring_perusahaan_di_alamat_tidak_menembus_batas(): void
    {
        // Sebelum ada batas ini, ?perusahaan= hanyalah penyaring: siapa pun
        // dapat menyebut id perusahaan lain dan membacanya.
        $a = $this->perusahaan();
        $b = $this->perusahaan();

        $this->laporan($a, 'Milik perusahaan A');
        $this->laporan($b, 'Milik perusahaan B');

        $this->actingAs(User::factory()->create(['is_admin' => false, 'company_id' => $a->id]));

        $isi = $this->get('/hazard?perusahaan='.$b->id)->assertOk()->getContent();

        $this->assertStringNotContainsString('Milik perusahaan B', $isi);
    }

    public function test_hitungan_statistik_ikut_terbatas(): void
    {
        // Angka ringkas dihitung dengan kueri tersendiri, bukan dari daftar
        // yang tampak. Batas yang hanya dipasang pada daftar akan tetap
        // membocorkan jumlahnya.
        $a = $this->perusahaan();
        $b = $this->perusahaan();

        $this->laporan($a, 'A1');
        $this->laporan($b, 'B1');
        $this->laporan($b, 'B2');

        $this->actingAs(User::factory()->create(['is_admin' => false, 'company_id' => $a->id]));

        $this->assertSame(1, HazardReport::count());
    }

    public function test_satu_baris_milik_perusahaan_lain_tidak_dapat_diambil(): void
    {
        $a = $this->perusahaan();
        $b = $this->perusahaan();

        $milikB = $this->laporan($b, 'Milik perusahaan B');

        $this->actingAs(User::factory()->create(['is_admin' => false, 'company_id' => $a->id]));

        $this->assertNull(HazardReport::find($milikB->id));
    }

    public function test_data_tanpa_perusahaan_tetap_terlihat_oleh_semua(): void
    {
        /* Baris tanpa perusahaan bukan milik pihak lain yang harus
           disembunyikan — itu dokumen induk, standar bersama, dan seluruh
           data yang dibuat sebelum penempatan perusahaan ada.

           Menyaringnya sebagai "company_id = milik saya" saja pernah
           membuat modul Energi, Gudang, dan Dokumen tampak KOSONG bagi
           setiap pengguna yang sudah ditempatkan di sebuah perusahaan,
           sebab seluruh barisnya masih NULL. Kegagalannya diam: tidak ada
           galat, hanya daftar kosong yang terlihat seperti belum ada data. */
        $a = $this->perusahaan();
        $b = $this->perusahaan();

        $this->laporan(null, 'Milik bersama');
        $this->laporan($a, 'Milik perusahaan A');
        $this->laporan($b, 'Milik perusahaan B');

        $this->actingAs(User::factory()->create(['is_admin' => false, 'company_id' => $a->id]));

        $terlihat = HazardReport::pluck('deskripsi')->sort()->values()->all();

        $this->assertSame(['Milik bersama', 'Milik perusahaan A'], $terlihat);
    }

    public function test_administrator_tetap_menjangkau_seluruh_perusahaan(): void
    {
        $a = $this->perusahaan();
        $b = $this->perusahaan();

        $this->laporan($a, 'Milik perusahaan A');
        $this->laporan($b, 'Milik perusahaan B');

        $this->actingAs(User::factory()->create(['is_admin' => true, 'company_id' => $a->id]));

        $this->assertSame(2, HazardReport::count());
    }

    public function test_pengguna_tanpa_perusahaan_hanya_melihat_data_tanpa_perusahaan(): void
    {
        $a = $this->perusahaan();

        $this->laporan($a, 'Milik perusahaan A');
        $this->laporan(null, 'Tanpa perusahaan');

        $this->actingAs(User::factory()->create(['is_admin' => false, 'company_id' => null]));

        $this->assertSame(['Tanpa perusahaan'], HazardReport::pluck('deskripsi')->all());
    }

    /* ══════════════ kepemilikan saat data dibuat ══════════════ */

    public function test_data_baru_mewarisi_perusahaan_pembuatnya(): void
    {
        /* Batas per perusahaan hanya bekerja bila barisnya bertuan. Gudang
           tidak pernah menyebut company_id sama sekali, dan Energi,
           Dokumen, serta Inspeksi mengambilnya dari isian yang boleh
           dikosongkan — sehingga data baru terus lahir tanpa pemilik dan
           tetap terlihat oleh semua perusahaan. */
        $a = $this->perusahaan();
        $this->actingAs(User::factory()->create(['is_admin' => false, 'company_id' => $a->id]));

        $h = $this->laporan(null, 'Dibuat tanpa menyebut perusahaan');

        $this->assertSame($a->id, $h->company_id);
    }

    public function test_perusahaan_yang_disebut_tegas_tidak_ditimpa(): void
    {
        // Administrator yang membuatkan data untuk perusahaan lain, atau
        // sengaja membiarkannya milik bersama, tetap berlaku.
        $a = $this->perusahaan();
        $b = $this->perusahaan();

        $this->actingAs(User::factory()->create(['is_admin' => true, 'company_id' => $a->id]));

        $h = $this->laporan($b, 'Dibuatkan untuk perusahaan lain');

        $this->assertSame($b->id, $h->company_id);
    }

    public function test_tanpa_pengguna_batas_tidak_dipasang(): void
    {
        // Perintah konsol, antrean, dan penyemai berjalan tanpa pengguna.
        // Menyaring di situ membuat pekerjaan terjadwal diam-diam memproses
        // sebagian data saja.
        $this->laporan($this->perusahaan(), 'A');
        $this->laporan($this->perusahaan(), 'B');

        $this->assertGuest();
        $this->assertSame(2, HazardReport::count());
    }
}
