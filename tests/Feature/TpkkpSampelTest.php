<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Tpkkp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * PTPKKP — Rencana Sampel (Inertia).
 *
 * Tabelnya berasal dari instrumen dan tidak pernah berubah antar
 * kunjungan, jadi seluruh metode dikirim sekaligus dan tabnya berpindah
 * tanpa menyentuh server. Yang dijaga di sini: tidak ada metode yang
 * hilang dari kiriman, dan pembulatannya tetap per kolom.
 */
class TpkkpSampelTest extends TestCase
{
    use RefreshDatabase;

    private function masuk(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    private function props(string $kueri = ''): array
    {
        return $this->get('/tpkkp/sampel' . $kueri)->assertOk()->viewData('page')['props'];
    }

    public function test_halaman_dirender_inertia_bukan_blade(): void
    {
        $this->masuk();

        $this->get('/tpkkp/sampel')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Tpkkp/Sampel')
            ->has('judul')->has('subjudul')->has('picker')
            ->has('populasi.management')->has('populasi.employee')->has('populasi.total')
            ->has('metode')->has('metodeAwal'));
    }

    /**
     * Seluruh metode acuan ikut terkirim.
     *
     * Tabnya digambar dari daftar ini; metode yang tersaring keluar tidak
     * meninggalkan jejak apa pun di layar — tabnya sekadar tidak ada, dan
     * tidak ada yang menandai bahwa dulu pernah ada.
     */
    public function test_seluruh_metode_acuan_terkirim(): void
    {
        $this->masuk();

        $ref = Tpkkp::samplingRef();
        $harap = array_values(array_filter(
            array_keys($ref),
            fn ($k) => !in_array($k, ['population', 'companies'], true) && is_array($ref[$k] ?? null)
        ));

        $this->assertSame($harap, array_column($this->props()['metode'], 'kode'));
        $this->assertNotEmpty($harap);
    }

    public function test_setiap_metode_memuat_seluruh_perusahaan(): void
    {
        $this->masuk();

        $co = Tpkkp::samplingRef()['companies'] ?? [];

        foreach ($this->props()['metode'] as $m) {
            $this->assertSame(array_values($co), array_column($m['baris'], 'perusahaan'),
                "Metode {$m['kode']} tidak memuat seluruh perusahaan.");
        }
    }

    /**
     * Jumlah baris = pembulatan tiap kolom, bukan pembulatan jumlahnya.
     *
     * Kedua cara itu berbeda hasilnya, dan yang dipakai lapangan adalah
     * per kolom: orang tidak bisa dikirim setengah ke satu kelompok.
     */
    public function test_jumlah_baris_membulatkan_tiap_kolom_lebih_dulu(): void
    {
        $this->masuk();

        foreach ($this->props()['metode'] as $m) {
            foreach ($m['baris'] as $r) {
                if ($r['jumlah'] === null) {
                    $this->assertNull($r['mgm']);
                    $this->assertNull($r['emp']);
                    continue;
                }

                $this->assertSame((int) $r['mgm'] + (int) $r['emp'], $r['jumlah'],
                    "Metode {$m['kode']} baris {$r['perusahaan']}: jumlah tidak sama dengan penjumlahan kolomnya.");
            }
        }
    }

    /**
     * Baris total benar-benar menjumlahkan kolom di atasnya.
     *
     * Instrumen menyimpan totalnya sendiri dalam pecahan, dan versi
     * sebelumnya menampilkan angka itu di bawah kolom yang isinya baris
     * terbulat — untuk PJ: 6 di baris total, 13 kalau kolomnya dijumlah.
     * Total yang tidak sama dengan kolomnya bukan pembulatan, melainkan
     * angka yang salah dibaca siapa pun yang memeriksanya.
     */
    public function test_baris_total_menjumlahkan_kolom_di_atasnya(): void
    {
        $this->masuk();

        foreach ($this->props()['metode'] as $m) {
            foreach (['mgm', 'emp', 'jumlah'] as $k) {
                $jumlah = array_sum(array_map(fn ($r) => (int) $r[$k], $m['baris']));

                $this->assertSame($jumlah ?: null, $m['total'][$k],
                    "Metode {$m['kode']} kolom {$k}: baris total tidak sama dengan penjumlahan kolomnya.");
            }
        }
    }

    public function test_total_acuan_instrumen_ikut_dikirim_apa_adanya(): void
    {
        // Angka pecahan instrumen tetap ditampilkan sebagai keterangan,
        // supaya selisihnya terhadap baris terbulat bisa ditelusuri.
        $this->masuk();

        $ref = Tpkkp::samplingRef();

        foreach ($this->props()['metode'] as $m) {
            $blok = $ref[$m['kode']];

            if (!isset($blok['totalMgm']) && !isset($blok['totalEmp'])) {
                $this->assertNull($m['acuan']);
                continue;
            }

            $this->assertEqualsWithDelta($blok['totalMgm'] ?? null, $m['acuan']['mgm'], 1e-9);
            $this->assertEqualsWithDelta($blok['totalEmp'] ?? null, $m['acuan']['emp'], 1e-9);
        }
    }

    /**
     * Nol dibedakan dari "tidak dialokasikan".
     *
     * Menuliskan yang tidak dialokasikan sebagai 0 membuatnya tampak
     * seperti kekurangan yang harus dikejar, padahal memang tidak diminta.
     */
    public function test_alokasi_kosong_dikirim_null_bukan_nol(): void
    {
        $this->masuk();

        $adaNull = false;
        foreach ($this->props()['metode'] as $m) {
            foreach ($m['baris'] as $r) {
                foreach (['mgm', 'emp', 'jumlah'] as $k) {
                    $this->assertNotSame(0, $r[$k],
                        "Metode {$m['kode']}: alokasi kosong terkirim sebagai 0, bukan null.");
                    if ($r[$k] === null) $adaNull = true;
                }
            }
        }

        $this->assertTrue($adaNull, 'Tidak ada satu pun alokasi kosong — ujinya tidak menguji apa pun.');
    }

    /* ══════════════ tab awal ══════════════ */

    public function test_metode_awal_mengikuti_kueri_yang_sah(): void
    {
        $this->masuk();

        $daftar = array_column($this->props()['metode'], 'kode');
        $pilih  = $daftar[count($daftar) - 1];

        $this->assertSame($pilih, $this->props("?m={$pilih}")['metodeAwal']);
    }

    public function test_metode_awal_jatuh_ke_yang_pertama_bila_kuerinya_ngawur(): void
    {
        $this->masuk();

        $p = $this->props('?m=population');

        $this->assertSame($p['metode'][0]['kode'], $p['metodeAwal']);

        $p = $this->props('?m=ENTAH');
        $this->assertSame($p['metode'][0]['kode'], $p['metodeAwal']);
    }

    public function test_tamu_tidak_dapat_membuka(): void
    {
        $this->get('/tpkkp/sampel')->assertRedirect(route('login'));
    }
}
