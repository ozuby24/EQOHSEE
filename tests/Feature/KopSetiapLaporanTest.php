<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use App\Support\KopDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Setiap lembar cetak membawa kop perusahaan yang mencetaknya.
 *
 * Dijaga dari DUA sisi, sebab kegagalan di sisi mana pun tidak
 * menimbulkan galat dan tidak terlihat sampai lembarnya tercetak:
 *
 *   sisi server — tiap rute cetak mengirim prop `dok`;
 *   sisi tampilan — tiap halaman Print/ memasang komponen KopCetak.
 *
 * Menjaga satu sisi saja tidak cukup. Halaman yang menerima `dok` lalu
 * tidak menggambarnya tampak benar dari server, dan halaman yang
 * memasang kop tanpa menerima `dok` tampak benar dari berkas Vue-nya.
 * Keduanya pernah terjadi sekaligus: lima belas halaman menerima
 * `dok.logo` bertahun-tahun dan tidak satu pun menggambarnya.
 *
 * Sejarah yang membuat uji ini ada — kopnya dahulu DISALIN ke tiap
 * halaman, dan dari lima belas: sepuluh punya kop penuh, tiga hanya
 * separuhnya (nama perusahaan tanpa nomor dokumen, tanggal, maupun
 * revisi), dan dua tidak punya kop sama sekali.
 */
class KopSetiapLaporanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Halaman cetak yang sengaja TIDAK berkop.
     *
     * Alasannya ditulis di sebelah tiap nama — pengecualian tanpa
     * alasan adalah cara daftar semacam ini pelan-pelan kehilangan
     * artinya. Daftar ini juga sengaja disebut satu per satu, bukan
     * dicocokkan dengan pola nama: halaman berikutnya yang seharusnya
     * berkop tidak boleh lolos hanya karena namanya kebetulan mirip.
     *
     * @var array<string,string>
     */
    private const TANPA_KOP = [
        /* Kartu identitas ukuran KTP, bukan lembar A4. Kop dokumen
           terkendali — nomor dokumen, revisi, tanggal terbit — memakan
           sepertiga muka kartu yang lebarnya 85,6 mm, dan kartu itu
           sendiri sudah menyandang identitas penerbitnya pada kepalanya:
           nama perusahaan, jenis kartu, dan nomor kartunya. */
        'KartuTambang' => 'kartu identitas fisik, kopnya menyatu pada kepala kartu',
    ];

    /* ═══════════ sisi tampilan ═══════════ */

    public function test_setiap_halaman_cetak_memasang_kop(): void
    {
        $tanpa = [];

        foreach (glob(resource_path('js/Pages/Print/*.vue')) as $berkas) {
            $nama = basename($berkas, '.vue');

            if (array_key_exists($nama, self::TANPA_KOP)) continue;

            if (!str_contains((string) file_get_contents($berkas), 'KopCetak')) {
                $tanpa[] = $nama;
            }
        }

        sort($tanpa);

        $this->assertSame([], $tanpa,
            "Halaman cetak berikut tidak memasang KopCetak:\n  ".implode("\n  ", $tanpa)
            ."\nLembar yang keluar tanpa kop tidak dapat dipakai sebagai dokumen terkendali.");
    }

    /**
     * Kopnya berasal dari SATU komponen, bukan disalin ulang.
     *
     * Penyalinanlah yang dahulu melahirkan tiga bentuk kop berbeda
     * tanpa ada yang memutuskannya. Tabel kop yang muncul kembali di
     * dalam halaman adalah tanda pola itu kembali.
     */
    public function test_kop_tidak_disalin_ke_dalam_halaman(): void
    {
        $salinan = [];

        foreach (glob(resource_path('js/Pages/Print/*.vue')) as $berkas) {
            $isi = (string) file_get_contents($berkas);

            if (str_contains($isi, 'No. Dokumen') || str_contains($isi, 'Tgl Penerbitan')) {
                $salinan[] = basename($berkas, '.vue');
            }
        }

        sort($salinan);

        $this->assertSame([], $salinan,
            "Kop disalin lagi ke dalam halaman: ".implode(', ', $salinan)
            .". Pakai <KopCetak :dok=\"props.dok\" /> — kop yang disalin akan berbeda-beda "
            ."tanpa ada yang memutuskannya.");
    }

    /* ═══════════ sisi server ═══════════ */

    /**
     * Tiap rute cetak mengirim `dok`.
     *
     * Dibuka sungguhan, bukan dibaca dari kode: prop yang dihitung
     * tetapi tidak jadi terkirim — karena cabang if, karena pengecualian
     * yang tertelan — hanya ketahuan dari tanggapannya.
     */
    public function test_setiap_rute_cetak_mengirim_dok(): void
    {
        $c = Company::create([
            'name' => 'PT Uji Kop', 'doc_no_prefix' => 'UK', 'doc_revisi' => 2,
        ]);

        $this->actingAs(User::factory()->create([
            'is_admin' => true, 'company_id' => $c->id, 'email_verified_at' => now(),
        ]));

        $tanpaDok = [];
        $diuji    = 0;

        foreach (Route::getRoutes() as $rute) {
            $nama = $rute->getName();

            if (!$nama || !in_array('GET', $rute->methods(), true)) continue;
            if (str_contains($rute->uri(), '{')) continue;
            if (!preg_match('/\.(cetak|ekspor\.cetak)$/', $nama)) continue;

            $diuji++;

            $r = $this->get('/'.ltrim($rute->uri(), '/'));

            if ($r->getStatusCode() >= 400) {
                $tanpaDok[] = $nama.' — HTTP '.$r->getStatusCode();
                continue;
            }

            $props = $r->viewData('page')['props'] ?? [];

            if (!isset($props['dok'])) {
                $tanpaDok[] = $nama.' — tanpa prop dok';
                continue;
            }

            /* Nomornya harus mengikuti skema JENIS/PERUSAHAAN/DEPT/URUT
               dan menyebut perusahaan yang sedang mencetak — bukan
               perusahaan lain, dan bukan nomor karangan. */
            $bagian = \App\Support\Nomor::urai($props['dok']['nomor'] ?? null);

            if ($bagian === null) {
                $tanpaDok[] = $nama.' — nomor "'.($props['dok']['nomor'] ?? '').'" tidak mengikuti skema';
            } elseif ($bagian['perusahaan'] !== 'UK') {
                $tanpaDok[] = $nama.' — nomor "'.$props['dok']['nomor'].'" bukan milik PT Uji Kop';
            }
        }

        $this->assertGreaterThan(8, $diuji, 'Terlalu sedikit rute cetak teruji — penyaringnya keliru.');

        $this->assertSame([], $tanpaDok,
            "Rute cetak berikut tidak membawa kop perusahaan yang benar:\n  ".implode("\n  ", $tanpaDok));
    }

    /* ═══════════ logo ═══════════ */

    /**
     * Logo dikirim sebagai ALAMAT, bukan jalur simpanan.
     *
     * Jalur mentah membuat <img src="logos/x.png"> menunjuk ke tempat
     * yang tidak ada, dan gambar yang gagal dimuat tidak menimbulkan
     * galat apa pun — hanya kotak kosong pada lembar yang sudah
     * terlanjur dicetak.
     */
    public function test_logo_dikirim_sebagai_alamat_penuh(): void
    {
        $c = Company::create(['name' => 'PT Berlogo', 'logo' => 'logos/uji.png']);

        $dok = KopDokumen::untuk('laporan-air', $c);

        $this->assertNotNull($dok['logo']);
        $this->assertStringStartsWith('http', $dok['logo'],
            'Logo dikirim sebagai jalur simpanan; halaman cetak tidak akan dapat menggambarnya.');
        $this->assertStringContainsString('logos/uji.png', $dok['logo']);
    }

    public function test_tanpa_logo_tetap_null_bukan_alamat_kosong(): void
    {
        $c = Company::create(['name' => 'PT Tanpa Logo']);

        $this->assertNull(KopDokumen::untuk('laporan-air', $c)['logo'],
            'Alamat yang menunjuk ke berkas kosong menghasilkan gambar rusak, bukan kop tanpa logo.');
    }
}
