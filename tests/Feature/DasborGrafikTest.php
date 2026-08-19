<?php

namespace Tests\Feature;

use App\Models\{Company, MineOperationalRecord, User};
use App\Support\DasborGrafik;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Deret angka untuk grafik dasbor.
 *
 * Yang dijaga di sini tiga aturan yang berlaku untuk seluruh grafiknya,
 * dan ketiganya jenis kesalahan yang tidak menimbulkan galat — hanya
 * gambar yang salah artinya, dibaca orang yang tidak punya cara
 * memeriksanya.
 */
class DasborGrafikTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;

    protected function setUp(): void
    {
        parent::setUp();

        $this->c = Company::create(['name' => 'PT Uji Grafik', 'doc_no_prefix' => 'UG']);

        $this->actingAs(User::factory()->create([
            'is_admin' => true, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]));
    }

    /**
     * Hari tanpa catatan tetap muncul sebagai nol.
     *
     * Grafik yang melompati hari kosong menyambung dua titik berjauhan
     * menjadi satu garis landai: tambang yang berhenti seminggu terbaca
     * sebagai penurunan bertahap, dan penurunan bertahap tidak membuat
     * siapa pun menelepon siapa pun.
     */
    #[Test]
    public function hari_tanpa_catatan_digambar_sebagai_nol(): void
    {
        $kini = Carbon::create(2026, 8, 19);

        MineOperationalRecord::create([
            'company_id' => $this->c->id, 'tanggal' => $kini->copy()->subDays(3),
            'shift' => 1, 'produksi_ton' => 500, 'overburden_bcm' => 2000,
        ]);

        $g = DasborGrafik::produksi($kini, 7);

        $this->assertCount(7, $g['label'], 'Rentang tujuh hari harus memberi tujuh titik.');
        $this->assertCount(7, $g['ton']);

        $this->assertSame(500.0, $g['ton'][3], 'Hari bercatatan salah tempat.');
        $this->assertSame(0.0, $g['ton'][0], 'Hari tanpa catatan harus nol, bukan hilang.');
        $this->assertSame(0.0, $g['ton'][6]);
    }

    /**
     * Nisbah kupas tanpa produksi adalah NULL, bukan nol.
     *
     * Nol berarti "tidak ada pengupasan"; tidak terdefinisi berarti
     * "tidak ada batubara untuk dibandingkan". Menggambar keduanya
     * sebagai nol membuat hari libur terbaca sebagai hari dengan
     * efisiensi sempurna.
     */
    #[Test]
    public function nisbah_tanpa_produksi_tidak_terdefinisi(): void
    {
        $kini = Carbon::create(2026, 8, 19);

        MineOperationalRecord::create([
            'company_id' => $this->c->id, 'tanggal' => $kini,
            'shift' => 1, 'produksi_ton' => 0, 'overburden_bcm' => 3000,
        ]);

        MineOperationalRecord::create([
            'company_id' => $this->c->id, 'tanggal' => $kini->copy()->subDay(),
            'shift' => 1, 'produksi_ton' => 100, 'overburden_bcm' => 800,
        ]);

        $g = DasborGrafik::produksi($kini, 2);

        $this->assertSame(8.0, $g['nisbah'][0], 'Nisbah 800/100 seharusnya 8.');
        $this->assertNull($g['nisbah'][1],
            'Nisbah tanpa produksi harus null — nol berarti tidak ada pengupasan sama sekali.');
    }

    /**
     * Tanpa data sama sekali, grafiknya tetap berbentuk.
     *
     * Pemasangan baru membuka dasbor pada hari pertama. Deret kosong
     * membuat komponen grafiknya menggambar sumbu tanpa titik — bukan
     * melempar galat yang mengosongkan seluruh halaman.
     */
    #[Test]
    public function tanpa_data_deretnya_tetap_utuh(): void
    {
        $kini = Carbon::create(2026, 8, 19);

        foreach ([
            DasborGrafik::produksi($kini, 7),
            DasborGrafik::angkutan($kini, 7),
            DasborGrafik::air($kini, 7),
            DasborGrafik::energi($kini, 7),
        ] as $g) {
            $this->assertCount(7, $g['label']);

            foreach ($g as $kunci => $deret) {
                $this->assertCount(7, $deret, "Deret \"{$kunci}\" tidak sepanjang labelnya.");
            }
        }

        $this->assertCount(6, DasborGrafik::hazardBulanan($kini)['label']);
        $this->assertCount(12, DasborGrafik::biayaBulanan($kini)['label']);
    }

    /**
     * Rentang yang diminta dari URL dibatasi daftar tertutup.
     *
     * Nilai bebas berarti seseorang dapat meminta seratus ribu hari dan
     * menunggu selamanya sambil mengunci basis datanya.
     */
    #[Test]
    public function rentang_di_luar_daftar_ditolak(): void
    {
        foreach ([99999, 0, -30, 13] as $nakal) {
            $this->get('/dasbor?hari='.$nakal)
                ->assertOk()
                ->assertInertia(fn ($h) => $h->where('hari', DasborGrafik::RENTANG_BAWAAN)->etc());
        }

        $this->get('/dasbor?hari=90')
            ->assertOk()
            ->assertInertia(fn ($h) => $h->where('hari', 90)->etc());
    }

    /**
     * Nilai kolom yang kosong disebut apa adanya, bukan dibuang.
     *
     * Kolom yang sering kosong adalah temuan tersendiri. Membuangnya
     * membuat grafiknya tampak paling rapi justru saat datanya paling
     * buruk.
     */
    #[Test]
    public function nilai_kosong_disebut_bukan_dibuang(): void
    {
        $h = \App\Models\HazardReport::create([
            'kode' => 'HZ-KOSONG', 'company_id' => $this->c->id,
            'pelapor_nama' => 'Penguji', 'tanggal' => now(), 'lokasi' => 'Pit',
            'deskripsi' => 'Uji', 'status' => 'Open',
        ]);

        /* Kolomnya bernilai bawaan saat dibuat lewat model; yang diuji
           di sini justru baris yang kolomnya memang kosong — kategori
           boleh tidak diisi, dan yang tidak diisi kerap justru yang
           paling perlu dilihat. */
        \App\Models\HazardReport::whereKey($h->id)->update(['kategori' => null]);

        $kategori = DasborGrafik::hazardKategori();

        $this->assertSame(
            ['Tidak diisi'],
            array_column($kategori, 'label'),
            'Baris tanpa kategori harus tetap terhitung dan diberi nama, bukan hilang.',
        );
    }
}
