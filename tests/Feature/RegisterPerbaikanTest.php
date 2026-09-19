<?php

namespace Tests\Feature;

use App\Models\{Company, HazardReport, User};
use App\Support\Berkas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as SharedDrawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Tests\TestCase;

/**
 * Register Tindakan Perbaikan — lembar terkendali yang diunduh.
 *
 * Diuji dengan MEMBUKA berkas yang terbit, bukan dengan memeriksa bahwa
 * jawabannya 200. Register yang gagal disusun tetap membalas 200 dengan
 * badan yang rusak — aliran unduhan sudah terkirim sebelum galatnya
 * terjadi — dan berkas rusak yang lolos ke rapat tidak dapat dibedakan
 * dari berkas yang tidak pernah dibuat.
 */
class RegisterPerbaikanTest extends TestCase
{
    use RefreshDatabase;

    private static int $n = 0;

    private function perusahaan(array $x = []): Company
    {
        $i = ++self::$n;

        return Company::create(array_merge(['name' => "PT Uji {$i}", 'code' => "PU{$i}"], $x));
    }

    private function masuk(?Company $c = null): User
    {
        $u = User::factory()->create(['is_admin' => true, 'company_id' => $c?->id]);
        $this->actingAs($u);

        return $u;
    }

    private function laporan(Company $c, array $x = []): HazardReport
    {
        return HazardReport::create(array_merge([
            'kode'         => 'HR-'.str_pad((string) ++self::$n, 4, '0', STR_PAD_LEFT),
            'company_id'   => $c->id,
            'pelapor_nama' => 'Pelapor Uji',
            'tanggal'      => now()->toDateString(),
            'lokasi'       => 'Area uji',
            'risiko'       => 'Sedang',
            'kategori'     => 'Unsafe Condition',
            'deskripsi'    => 'Temuan uji coba.',
            'hirarki'      => 'Rekayasa',
            'rekomendasi'  => 'Diperbaiki pengawas area.',
            'status'       => 'Open',
        ], $x));
    }

    /**
     * Unduh registernya, lalu buka berkasnya.
     *
     * Berkas sementara dipakai karena PhpSpreadsheet membaca dari jalur,
     * bukan dari untai bita — dan menulisnya ke berkas sekalian menguji
     * bahwa yang terkirim memang zip yang sah, bukan HTML galat yang
     * kebetulan terkirim dengan tipe xlsx.
     */
    private function bukaRegister(array $parameter = []): Worksheet
    {
        $jawab = $this->get(route('hazard.register', $parameter));
        $jawab->assertOk();
        $jawab->assertHeader('content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $jalur = tempnam(sys_get_temp_dir(), 'reg').'.xlsx';
        file_put_contents($jalur, $jawab->streamedContent());

        $lembar = IOFactory::load($jalur)->getActiveSheet();
        @unlink($jalur);

        return $lembar;
    }

    /** Nilai kolom A pada seluruh baris data, dari baris pertama sesudah judul. */
    private function nomorBaris(Worksheet $l): array
    {
        $isi = [];

        for ($b = 8; $b < 400; $b++) {
            $nilai = $l->getCell("A{$b}")->getValue();
            if ($nilai === null || $nilai === '') break;
            $isi[] = ['baris' => $b, 'deskripsi' => (string) $l->getCell("B{$b}")->getValue()];
        }

        return $isi;
    }

    /* ══════════════ siapa yang boleh mengunduhnya ══════════════ */

    /**
     * Tamu tidak mendapat registernya.
     *
     * Satu berkas ini memuat SELURUH temuan satu perusahaan sekaligus —
     * uraian, lokasi, nama pelapor, dan fotonya — dalam satu permintaan
     * GET tanpa badan. Rute yang tanpa sengaja keluar dari grup
     * berpagarnya tidak akan ketahuan dari layar mana pun: halaman
     * monitornya tetap meminta masuk, dan hanya alamat unduhannya yang
     * terbuka.
     */
    public function test_tamu_tidak_dapat_mengunduh_register(): void
    {
        $c = $this->perusahaan();
        $this->laporan($c);

        $this->get(route('hazard.register'))->assertRedirect(route('login'));
        $this->get(route('hazard.register.jumlah'))->assertRedirect(route('login'));
    }

    /* ══════════════ berkasnya sendiri ══════════════ */

    public function test_register_terbit_sebagai_xlsx_yang_dapat_dibuka(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $this->laporan($c, ['deskripsi' => 'Tanggul tergerus']);

        $l = $this->bukaRegister();

        $this->assertSame('REGISTER TINDAKAN PERBAIKAN', $l->getCell('C2')->getValue());
        $this->assertSame('No', $l->getCell('A7')->getValue());
        $this->assertSame('Hirarki Pengendalian', $l->getCell('C7')->getValue());
        $this->assertSame('Gambar Temuan', $l->getCell('H7')->getValue());
        $this->assertSame('Gambar Perbaikan', $l->getCell('I7')->getValue());
        $this->assertSame('Tanggul tergerus', $l->getCell('B8')->getValue());
    }

    /**
     * Saringan monitor TIDAK ikut memotong registernya.
     *
     * Ini perilaku yang paling mudah hilang tanpa disadari: menyatukan
     * kedua penyaring membuat kodenya lebih pendek, seluruh ujinya tetap
     * hijau, dan satu-satunya akibatnya adalah lembar yang diserahkan ke
     * rapat kehilangan baris karena kotak cari di layar kebetulan masih
     * terisi.
     */
    public function test_kotak_cari_monitor_tidak_memotong_register(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->laporan($c, ['deskripsi' => 'Tanggul tergerus']);
        $this->laporan($c, ['deskripsi' => 'Lampu mati']);

        /* q dan status adalah saringan MILIK monitor. Keduanya dikirim
           bersama-sama supaya uji ini tidak lulus hanya karena salah
           satunya kebetulan tidak dikenali. */
        $l = $this->bukaRegister(['q' => 'tanggul', 'status' => 'Closed', 'risiko' => 'Tinggi']);

        $this->assertCount(2, $this->nomorBaris($l));
    }

    public function test_perusahaan_lain_tidak_ikut_ke_dalam_register(): void
    {
        $milikku = $this->perusahaan();
        $tetangga = $this->perusahaan();

        $this->laporan($milikku, ['deskripsi' => 'Punya sendiri']);
        $this->laporan($tetangga, ['deskripsi' => 'Punya tetangga']);

        /* Pengguna biasa, bukan admin: batas perusahaan dijalankan scope
           global, dan admin sengaja melihat seluruhnya. */
        $this->actingAs(User::factory()->create(['is_admin' => false, 'company_id' => $milikku->id]));

        $baris = $this->nomorBaris($this->bukaRegister());

        $this->assertCount(1, $baris);
        $this->assertSame('Punya sendiri', $baris[0]['deskripsi']);
    }

    public function test_jumlah_yang_dijanjikan_sama_dengan_baris_yang_terbit(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->laporan($c, ['lokasi' => 'Pit Utara']);
        $this->laporan($c, ['lokasi' => 'Pit Utara']);
        $this->laporan($c, ['lokasi' => 'Workshop']);

        $jumlah = $this->get(route('hazard.register.jumlah', ['lokasi' => 'Pit Utara']))
            ->assertOk()->json('jumlah');

        $this->assertSame(2, $jumlah);
        $this->assertCount($jumlah, $this->nomorBaris($this->bukaRegister(['lokasi' => 'Pit Utara'])));
    }

    public function test_urutan_yang_dipilih_benar_benar_mengubah_urutan_baris(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->laporan($c, ['deskripsi' => 'Rendah lebih dulu', 'risiko' => 'Rendah',
                            'tanggal' => now()->subDays(9)->toDateString()]);
        $this->laporan($c, ['deskripsi' => 'Tinggi belakangan', 'risiko' => 'Tinggi',
                            'tanggal' => now()->subDay()->toDateString()]);

        $perBulan = array_column($this->nomorBaris($this->bukaRegister(['urutan' => 'bulan'])), 'deskripsi');
        $perRisiko = array_column($this->nomorBaris($this->bukaRegister(['urutan' => 'risiko'])), 'deskripsi');

        $this->assertSame(['Rendah lebih dulu', 'Tinggi belakangan'], $perBulan);
        $this->assertSame(['Tinggi belakangan', 'Rendah lebih dulu'], $perRisiko);
    }

    /* ══════════════ gambar ══════════════ */

    /** Satu JPEG sungguhan pada diska tertutup, lalu jalurnya. */
    private function fotoUji(int $lebar = 1600, int $tinggi = 900): string
    {
        $g = imagecreatetruecolor($lebar, $tinggi);
        imagefilledrectangle($g, 0, 0, $lebar, $tinggi, imagecolorallocate($g, 180, 90, 40));

        ob_start();
        imagejpeg($g);
        $bita = (string) ob_get_clean();
        imagedestroy($g);

        $jalur = 'hazard/uji-'.(++self::$n).'.jpg';
        Storage::disk(Berkas::TERTUTUP)->put($jalur, $bita);

        return $jalur;
    }

    public function test_foto_tertanam_sebagai_gambar_bukan_sekadar_tautan(): void
    {
        Storage::fake(Berkas::TERTUTUP);

        $c = $this->perusahaan();
        $this->masuk($c);
        $this->laporan($c, ['foto' => [$this->fotoUji()]]);

        $l = $this->bukaRegister();

        $this->assertCount(1, $l->getDrawingCollection(),
            'Foto temuan seharusnya tertanam sebagai gambar di dalam berkasnya.');
        $this->assertSame('H8', $l->getDrawingCollection()[0]->getCoordinates());
    }

    /**
     * Gambar tidak boleh melebar keluar kolomnya.
     *
     * PhpSpreadsheet tidak memangkas apa pun: tinggi yang dipatok tanpa
     * lebarnya membuat foto membujur menjorok ke kolom sebelahnya dan
     * menimpa gambar perbaikan yang ada di sana. Cacatnya tidak terlihat
     * dari nilai sel mana pun — hanya dari ukuran gambarnya.
     */
    public function test_gambar_muat_di_dalam_kolom_dan_barisnya(): void
    {
        Storage::fake(Berkas::TERTUTUP);

        $c = $this->perusahaan();
        $this->masuk($c);

        /* Sengaja sangat membujur. Foto 16:9 pun sudah melebihi kolomnya
           bila hanya tingginya yang dipatok; yang ini membuat selisihnya
           tidak mungkin terlewat. */
        $this->laporan($c, ['foto' => [$this->fotoUji(2400, 600)]]);

        $l = $this->bukaRegister();
        $gambar = $l->getDrawingCollection()[0];

        $font = $l->getParent()->getDefaultStyle()->getFont();
        $lebarKolom = SharedDrawing::cellDimensionToPixels($l->getColumnDimension('H')->getWidth(), $font);
        $tinggiBaris = SharedDrawing::pointsToPixels($l->getRowDimension(8)->getRowHeight());

        $this->assertLessThanOrEqual($lebarKolom, $gambar->getWidth() + $gambar->getOffsetX(),
            'Gambar melebihi lebar kolomnya dan akan menimpa kolom di sebelahnya.');
        $this->assertLessThanOrEqual($tinggiBaris, $gambar->getHeight() + $gambar->getOffsetY(),
            'Gambar melebihi tinggi barisnya.');
    }

    /**
     * Jenis gambar yang didaftarkan harus sama dengan isinya.
     *
     * Bita JPEG yang dinamai .png dan dideklarasikan image/png di
     * [Content_Types].xml menghasilkan paket yang tidak sah. Ukurannya
     * tetap masuk akal dan berkasnya tetap terbuka di sebagian pembaca,
     * sehingga kekeliruannya hanya terlihat dari dalam zip-nya — dan
     * pembaca yang menolaknya menggambarkan sel kosong tanpa satu pun
     * pesan.
     */
    public function test_jenis_gambar_cocok_dengan_isi_bitanya(): void
    {
        Storage::fake(Berkas::TERTUTUP);

        $c = $this->perusahaan();
        $this->masuk($c);
        $this->laporan($c, ['foto' => [$this->fotoUji()]]);

        $jalur = tempnam(sys_get_temp_dir(), 'reg').'.xlsx';
        file_put_contents($jalur, $this->get(route('hazard.register'))->streamedContent());

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($jalur) === true, 'Berkasnya bukan zip yang sah.');

        $tipe = $zip->getFromName('[Content_Types].xml');
        $media = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nama = $zip->getNameIndex($i);
            if (str_starts_with($nama, 'xl/media/')) $media[$nama] = $zip->getFromIndex($i);
        }

        $zip->close();
        @unlink($jalur);

        $this->assertNotEmpty($media, 'Tidak ada gambar yang tertanam.');

        foreach ($media as $nama => $bita) {
            $sebenarnya = (string) (getimagesizefromstring($bita)['mime'] ?? '');
            $akhiran = pathinfo($nama, PATHINFO_EXTENSION);

            $this->assertSame('image/'.($akhiran === 'jpg' ? 'jpeg' : $akhiran), $sebenarnya,
                "Berkas {$nama} berisi {$sebenarnya}, tidak sesuai namanya.");
            $this->assertStringContainsString('Extension="'.$akhiran.'"', (string) $tipe,
                "Jenis {$akhiran} tidak didaftarkan di [Content_Types].xml.");
        }
    }

    /* ══════════════ rekap pelapor ══════════════ */

    /** Baris rekap pelapor: sesudah judul "Pelapor", sampai baris kosong. */
    private function rekap(Worksheet $l): array
    {
        $awal = null;

        for ($b = 8; $b < 400; $b++) {
            if ($l->getCell("A{$b}")->getValue() === 'Pelapor') { $awal = $b + 1; break; }
        }

        $this->assertNotNull($awal, 'Judul rekap pelapor tidak ditemukan.');

        $isi = [];

        for ($b = $awal; $b < 400; $b++) {
            $nama = $l->getCell("A{$b}")->getValue();
            if ($nama === null || $nama === '') break;

            $isi[(string) $nama] = [
                'jumlah'   => $l->getCell("C{$b}")->getValue(),
                'rincian'  => (string) $l->getCell("D{$b}")->getValue(),
                'tinggi'   => (string) $l->getCell("E{$b}")->getValue(),
                'telat'    => (string) $l->getCell("F{$b}")->getValue(),
                'persen'   => (string) $l->getCell("G{$b}")->getValue(),
                'area'     => (string) $l->getCell("H{$b}")->getValue(),
            ];
        }

        return $isi;
    }

    public function test_rekap_pelapor_merinci_status_risiko_dan_tenggat(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->laporan($c, ['pelapor_nama' => 'Budi', 'status' => 'Closed', 'risiko' => 'Tinggi',
                            'lokasi' => 'Pit Utara']);
        $this->laporan($c, ['pelapor_nama' => 'Budi', 'status' => 'Open', 'risiko' => 'Tinggi',
                            'lokasi' => 'Pit Utara', 'batas_akhir' => now()->subWeek()->toDateString()]);
        $this->laporan($c, ['pelapor_nama' => 'Budi', 'status' => 'In Progress', 'risiko' => 'Rendah',
                            'lokasi' => 'Workshop']);
        $this->laporan($c, ['pelapor_nama' => 'Sari', 'status' => 'Closed', 'risiko' => 'Sedang',
                            'lokasi' => 'Workshop']);

        $rekap = $this->rekap($this->bukaRegister());

        $this->assertSame(['Budi', 'Sari'], array_keys($rekap), 'Terbanyak melapor harus di atas.');

        $this->assertSame(3, $rekap['Budi']['jumlah']);
        $this->assertSame('Open 1 · Proses 1 · Closed 1', $rekap['Budi']['rincian']);
        $this->assertSame('2', $rekap['Budi']['tinggi']);
        $this->assertSame('1', $rekap['Budi']['telat']);
        $this->assertSame('33%', $rekap['Budi']['persen']);
        $this->assertSame('Pit Utara', $rekap['Budi']['area']);

        $this->assertSame(1, $rekap['Sari']['jumlah']);
        $this->assertSame('100%', $rekap['Sari']['persen']);

        /* Yang tidak punya temuan berisiko tinggi maupun yang lewat
           tenggat diberi tanda pisah, bukan angka nol. Nol pada kolom
           yang ditelusuri mata terbaca sebagai angka yang perlu
           diperiksa; kolom penuh nol membuat yang benar-benar berisi
           tenggelam di antaranya. */
        $this->assertSame('—', $rekap['Sari']['tinggi']);
        $this->assertSame('—', $rekap['Sari']['telat']);
    }

    /**
     * Nama yang ditulis berbeda-beda tetap satu orang.
     *
     * Dikelompokkan lewat NRP-nya. Tanpa itu satu orang tampil tiga kali
     * di rekap yang dibawa ke rapat, dan yang membacanya menyimpulkan
     * tiga orang melapor satu kali.
     */
    public function test_satu_orang_dengan_ejaan_berbeda_tidak_pecah_jadi_beberapa(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->laporan($c, ['pelapor_nama' => 'Budi Santoso', 'pelapor_nrp' => 'NRP-9']);
        $this->laporan($c, ['pelapor_nama' => 'budi santoso',  'pelapor_nrp' => 'NRP-9']);

        $rekap = $this->rekap($this->bukaRegister());

        $this->assertCount(1, $rekap);
        $this->assertSame(2, reset($rekap)['jumlah']);
    }

    /**
     * Ringkasan penyelesaian dan rekap pelapor harus sepakat.
     *
     * Keduanya dihitung terpisah dari kumpulan yang sama, dan selisih
     * di antara keduanya pada satu lembar yang sama menghabiskan waktu
     * rapat untuk mencari mana yang benar.
     */
    public function test_ringkasan_dan_rekap_menghitung_hal_yang_sama(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->laporan($c, ['pelapor_nama' => 'Budi', 'status' => 'Closed']);
        $this->laporan($c, ['pelapor_nama' => 'Sari', 'status' => 'Open',
                            'batas_akhir' => now()->subWeek()->toDateString()]);
        $this->laporan($c, ['pelapor_nama' => 'Sari', 'status' => 'Open',
                            'batas_akhir' => now()->addWeek()->toDateString()]);

        $l = $this->bukaRegister();

        $ringkas = '';
        for ($b = 8; $b < 400; $b++) {
            if ($l->getCell("A{$b}")->getValue() === 'RINGKASAN PENYELESAIAN') {
                $ringkas = (string) $l->getCell("D{$b}")->getValue();
                break;
            }
        }

        $this->assertStringContainsString('Closed: 1 dari 3 temuan', $ringkas);
        $this->assertStringContainsString('Lewat tenggat: 1 temuan', $ringkas);

        $rekap = $this->rekap($l);

        $this->assertSame(3, array_sum(array_column($rekap, 'jumlah')));
        $this->assertSame(1, array_sum(array_map(
            fn ($o) => $o['telat'] === '—' ? 0 : (int) $o['telat'],
            $rekap,
        )));
    }
}
