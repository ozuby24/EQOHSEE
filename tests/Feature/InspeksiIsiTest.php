<?php

namespace Tests\Feature;

use App\Models\{Company, Inspection, InspectionItem, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Pengisian daftar periksa di lapangan.
 *
 * Yang diuji di sini bukan tampilannya melainkan dua hal yang membuat
 * pengisian selesai atau tidak: bukti foto yang tidak saling menimpa,
 * dan status yang benar-benar dapat disetel dari layar pengisian.
 */
class InspeksiIsiTest extends TestCase
{
    use RefreshDatabase;

    private static int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    private function perusahaan(): Company
    {
        $i = ++self::$n;

        return Company::create(['name' => "PT Uji Isi {$i}", 'code' => "PI{$i}"]);
    }

    private function masuk(Company $c): User
    {
        $u = User::factory()->create(['is_admin' => true, 'company_id' => $c->id]);
        $this->actingAs($u);

        return $u;
    }

    private function inspeksi(Company $c, string $status = 'Berjalan'): Inspection
    {
        return Inspection::create([
            'kode'       => 'INS-ISI-'.str_pad((string) ++self::$n, 4, '0', STR_PAD_LEFT),
            'company_id' => $c->id,
            'judul'      => 'Inspeksi Uji Isi',
            'jenis'      => 'Bulanan',
            'lokasi'     => 'Kantor Uji',
            'tanggal'    => now()->toDateString(),
            'status'     => $status,
        ]);
    }

    private function butir(Inspection $i): InspectionItem
    {
        return InspectionItem::create([
            'inspection_id' => $i->id,
            'kelompok'      => 'Instalasi Listrik',
            'uraian'        => 'Panel listrik tertutup dan berlabel',
            'risiko'        => 'Tinggi',
            'order_index'   => 1,
        ]);
    }

    private function gambar(string $nama): UploadedFile
    {
        return UploadedFile::fake()->image($nama, 60, 60);
    }

    /* ══════════════ foto bukti ══════════════ */

    /**
     * Unggahan kedua MENAMBAH, tidak menggantikan yang pertama.
     *
     * Satu ketidaksesuaian hampir selalu difoto lebih dari sekali: satu
     * dari jauh untuk menunjukkan tempatnya, satu dari dekat untuk
     * menunjukkan apa yang salah. Penyimpanan yang menimpa membuang
     * yang pertama tanpa bertanya dan tanpa memberi tahu — dan yang
     * mengunggahnya baru mengetahuinya saat lembar inspeksi dicetak,
     * berminggu-minggu kemudian, ketika fotonya sudah tidak dapat
     * diambil ulang.
     */
    public function test_foto_butir_bertambah_bukan_menggantikan(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $x = $this->butir($this->inspeksi($c));

        $this->post(route('inspeksi.item.foto', $x), ['foto' => [$this->gambar('jauh.jpg')]])
             ->assertRedirect();
        $this->post(route('inspeksi.item.foto', $x), ['foto' => [$this->gambar('dekat.jpg')]])
             ->assertRedirect();

        $this->assertCount(2, (array) $x->fresh()->foto,
            'Unggahan kedua menghapus foto yang pertama.');
    }

    public function test_foto_butir_mendarat_di_disk_tertutup(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $x = $this->butir($this->inspeksi($c));

        $this->post(route('inspeksi.item.foto', $x), ['foto' => [$this->gambar('bukti.jpg')]]);

        foreach ((array) $x->fresh()->foto as $jalur) {
            Storage::disk('local')->assertExists($jalur);
            Storage::disk('public')->assertMissing($jalur);
        }
    }

    public function test_berkas_bukan_gambar_ditolak_sebagai_foto_butir(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $x = $this->butir($this->inspeksi($c));

        $this->post(route('inspeksi.item.foto', $x), [
            'foto' => [UploadedFile::fake()->create('sisip.php', 1, 'text/plain')],
        ])->assertSessionHasErrors();

        $this->assertEmpty((array) $x->fresh()->foto,
            'Berkas bukan gambar tetap tersimpan pada butirnya.');
    }

    public function test_butir_perusahaan_lain_tidak_dapat_diunggahi_foto(): void
    {
        $tetangga = $this->perusahaan();
        $x = $this->butir($this->inspeksi($tetangga));

        $this->actingAs(User::factory()->create([
            'is_admin' => false, 'company_id' => $this->perusahaan()->id,
        ]));

        $this->post(route('inspeksi.item.foto', $x), ['foto' => [$this->gambar('curi.jpg')]])
             ->assertNotFound();

        $this->assertEmpty((array) $x->fresh()->foto);
    }

    public function test_tamu_tidak_dapat_mengunggah_foto_butir(): void
    {
        $c = $this->perusahaan();
        $x = $this->butir($this->inspeksi($c));

        $this->post(route('inspeksi.item.foto', $x), ['foto' => [$this->gambar('tamu.jpg')]])
             ->assertRedirect(route('login'));
    }

    public function test_halaman_isi_membawa_alamat_unggah_tiap_butir(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $i = $this->inspeksi($c);
        $x = $this->butir($i);

        $this->get(route('inspeksi.show', $i))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('item.0.urlFoto', route('inspeksi.item.foto', $x))
                ->etc());
    }

    /* ══════════════ status ══════════════ */

    /**
     * Pilihan status di layar adalah status yang sungguh tersimpan.
     *
     * Daftar pilihannya dulu menyebut 'Draft' sementara `store()`
     * menulis 'Berjalan'. Tidak ada galat sama sekali: inspeksi yang
     * berjalan menampilkan pemilih KOSONG karena nilainya tidak ada di
     * antara pilihannya, dan saringan "Draft" di halaman daftar
     * memulangkan nol baris selamanya. Keduanya terbaca sebagai data
     * yang hilang, bukan sebagai daftar pilihan yang salah.
     */
    public function test_pilihan_status_sama_dengan_yang_tersimpan(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $i = $this->inspeksi($c, 'Berjalan');

        $this->get(route('inspeksi.show', $i))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('opsi.status', Inspection::STATUS)
                ->where('i.status', 'Berjalan')
                ->etc());

        $this->assertContains('Berjalan', Inspection::STATUS,
            'Status yang ditulis saat inspeksi dibuat tidak ada di antara pilihannya.');
    }

    public function test_saringan_status_di_daftar_memakai_domain_yang_sama(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $this->inspeksi($c, 'Berjalan');

        $this->get(route('inspeksi.index', ['status' => 'Berjalan']))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('opsi.status', Inspection::STATUS)
                ->has('inspeksi', 1)
                ->etc());
    }

    public function test_status_dapat_diselesaikan_dari_layar_pengisian(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $i = $this->inspeksi($c, 'Berjalan');
        $x = $this->butir($i);

        $this->post(route('inspeksi.items.save', $i), [
            'status' => 'Selesai',
            'item'   => [$x->id => ['kondisi' => 'Sesuai', 'risiko' => '', 'temuan' => '', 'tindakan' => '']],
        ])->assertRedirect();

        $this->assertSame('Selesai', $i->fresh()->status);
        $this->assertSame('Sesuai', $x->fresh()->kondisi);
    }

    /**
     * Status di luar domainnya DITOLAK, bukan disimpan diam-diam.
     *
     * Baris penyimpanannya dulu menulis apa pun yang dikirim. Nilai
     * asing tidak menimbulkan galat: ia tersimpan, lencananya jatuh ke
     * cabang "belum selesai", dan saringan di halaman daftar berhenti
     * menemukan inspeksinya — tanpa satu pun pesan yang menjelaskan ke
     * mana perginya.
     */
    public function test_status_di_luar_domainnya_ditolak(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $i = $this->inspeksi($c, 'Berjalan');

        $this->post(route('inspeksi.items.save', $i), ['status' => 'Draft'])
             ->assertSessionHasErrors('status');

        $this->assertSame('Berjalan', $i->fresh()->status,
            'Status di luar domainnya tersimpan.');
    }
}
