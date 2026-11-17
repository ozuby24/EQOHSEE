<?php

namespace Tests\Feature;

use App\Models\{ComplianceSubject, Company, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\TestCase;

/**
 * Unduhan Register Pemenuhan (.xlsx).
 *
 * Berkas ini keluar dari aplikasi dan masuk ke rapat, ke pemegang IUP,
 * dan ke auditor eksternal. Begitu ia terkirim, tidak ada lagi layar
 * yang dapat memperbaikinya — dan yang menerimanya tidak punya cara
 * mengetahui bahwa ada baris yang hilang atau angka yang berbeda arti.
 *
 * Yang dijaga di sini karena itu bukan rupanya melainkan KELENGKAPAN
 * dan ARTI-nya: seluruh kewajiban yang dipilih ikut terbawa, milik
 * perusahaan lain tidak, dan "belum dinilai" tidak pernah menjelma
 * menjadi nol persen.
 */
class RegisterKepatuhanTest extends TestCase
{
    use RefreshDatabase;

    private static int $n = 0;

    private function perusahaan(): Company
    {
        $i = ++self::$n;

        return Company::create(['name' => "PT Uji Ekspor {$i}", 'code' => "PE{$i}"]);
    }

    private function masuk(?Company $c = null, bool $admin = true): User
    {
        $u = User::factory()->create(['is_admin' => $admin, 'company_id' => $c?->id]);
        $this->actingAs($u);

        return $u;
    }

    /** @param array<int,array{0:string,1:string|null}> $butir [penunjuk, status] */
    private function subjek(?Company $c, array $butir = [], array $x = []): ComplianceSubject
    {
        $s = ComplianceSubject::withoutGlobalScopes()->create(array_merge([
            'company_id' => $c?->id,
            'sumber'     => 'Peraturan',
            'kode'       => 'S'.(++self::$n),
            'jenis'      => 'Undang-Undang',
            'nomor'      => 'Undang-Undang Nomor '.self::$n.' Tahun 1970',
            'judul'      => 'Keselamatan Kerja',
            'instansi'   => 'Pemerintah Republik Indonesia',
            'aspek'      => 'safety',
            'tahun'      => 2026,
            'status'     => 'Tetap',
        ], $x));

        foreach ($butir as $i => [$penunjuk, $status]) {
            $s->points()->create([
                'penunjuk'    => $penunjuk,
                'rangkuman'   => 'Kewajiban '.$penunjuk,
                'status'      => $status,
                'order_index' => $i + 1,
            ]);
        }

        return $s;
    }

    /**
     * Unduh registernya, lalu buka berkasnya.
     *
     * Ditulis ke berkas sementara karena PhpSpreadsheet membaca dari
     * jalur, bukan dari untai bita — dan itu sekalian membuktikan bahwa
     * yang terkirim memang zip xlsx yang sah, bukan halaman galat yang
     * kebetulan berlabel xlsx.
     */
    private function buka(array $parameter = []): Spreadsheet
    {
        $jawab = $this->get(route('kepatuhan.ekspor', $parameter + ['tahun' => 2026]));
        $jawab->assertOk();
        $jawab->assertHeader('content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $jalur = tempnam(sys_get_temp_dir(), 'kpt').'.xlsx';
        file_put_contents($jalur, $jawab->streamedContent());

        $buku = IOFactory::load($jalur);
        @unlink($jalur);

        return $buku;
    }

    /**
     * Baris data pada lembar register, dari baris pertama sesudah judul.
     *
     * @return array<int,array{no:int|string,nomor:string,penunjuk:string,status:string}>
     */
    private function barisData(Spreadsheet $buku): array
    {
        $l = $buku->getSheetByName('Register Pemenuhan');
        $out = [];

        for ($b = 11; $b < 500; $b++) {
            $no = $l->getCell("A{$b}")->getValue();
            if ($no === null || $no === '') break;

            $out[] = [
                'no'       => $no,
                'nomor'    => (string) $l->getCell("D{$b}")->getValue(),
                'penunjuk' => (string) $l->getCell("G{$b}")->getValue(),
                'status'   => (string) $l->getCell("J{$b}")->getValue(),
            ];
        }

        return $out;
    }

    /* ══════════════ siapa yang boleh mengunduhnya ══════════════ */

    public function test_tamu_tidak_dapat_mengunduh_register(): void
    {
        $this->get(route('kepatuhan.ekspor'))->assertRedirect(route('login'));
    }

    /**
     * Kewajiban perusahaan lain tidak ikut ke dalam berkasnya.
     *
     * Kebocoran lintas perusahaan di layar masih dapat ditutup dengan
     * menutup layarnya. Kebocoran di dalam berkas yang sudah dikirim
     * lewat surel tidak dapat ditarik kembali.
     */
    public function test_perusahaan_lain_tidak_ikut_terbawa(): void
    {
        $tetangga = $this->perusahaan();
        $this->subjek($tetangga, [['Pasal 9', 'Comply']],
            ['nomor' => 'Peraturan Tetangga Nomor 99']);

        $saya = $this->perusahaan();
        $this->subjek($saya, [['Pasal 3', 'Comply']], ['nomor' => 'Peraturan Saya Nomor 1']);

        $this->masuk($saya, admin: false);

        $nomor = array_column($this->barisData($this->buka()), 'nomor');

        $this->assertContains('Peraturan Saya Nomor 1', $nomor);
        $this->assertNotContains('Peraturan Tetangga Nomor 99', $nomor);
    }

    /* ══════════════ kelengkapan ══════════════ */

    /** Satu baris per BUTIR, bukan per peraturan. */
    public function test_satu_baris_per_butir(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->subjek($c, [
            ['Pasal 3 Ayat (1)', 'Comply'],
            ['Pasal 9 Ayat (1)', 'Not Comply'],
            ['Pasal 16', 'N/A'],
        ]);

        $baris = $this->barisData($this->buka());

        $this->assertCount(3, $baris);
        $this->assertSame(
            ['Pasal 3 Ayat (1)', 'Pasal 9 Ayat (1)', 'Pasal 16'],
            array_column($baris, 'penunjuk'),
        );
        $this->assertSame(['Comply', 'Not Comply', 'N/A'], array_column($baris, 'status'));
    }

    /**
     * Peraturan yang belum dirinci tetap mendapat barisnya.
     *
     * Dilewati, ia hilang tanpa jejak dari lembar yang seharusnya
     * memperlihatkan seluruh kewajiban — dan yang membacanya
     * menyimpulkan peraturan itu memang belum diidentifikasi.
     */
    public function test_kewajiban_tanpa_butir_tetap_mendapat_barisnya(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->subjek($c, [], ['nomor' => 'Permen ESDM Nomor 26 Tahun 2018']);

        $baris = $this->barisData($this->buka());

        $this->assertCount(1, $baris);
        $this->assertSame('Permen ESDM Nomor 26 Tahun 2018', $baris[0]['nomor']);
        $this->assertStringContainsString('belum dirinci', $baris[0]['penunjuk']);
    }

    /** Butir yang belum dinilai ditulis tegas, bukan dibiarkan kosong. */
    public function test_butir_belum_dinilai_ditulis_tegas(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->subjek($c, [['Pasal 3', null]]);

        $this->assertSame('Belum dinilai', $this->barisData($this->buka())[0]['status']);
    }

    /** Naskah berstatus Draf ditandai di kolom nomornya. */
    public function test_draf_ditandai_di_berkasnya(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->subjek($c, [['Pasal 3', null]], [
            'status' => 'Draf', 'dari_ai' => true, 'nomor' => 'Permen LHK Nomor 6 Tahun 2021',
        ]);

        $this->assertStringContainsString('DRAF', $this->barisData($this->buka())[0]['nomor']);
    }

    /* ══════════════ saringan ══════════════ */

    /** Tahun yang dipilih memotong; tahun lain tidak ikut. */
    public function test_hanya_tahun_yang_dipilih_yang_terbawa(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->subjek($c, [['Pasal 3', 'Comply']], ['tahun' => 2026, 'nomor' => 'Peraturan 2026']);
        $this->subjek($c, [['Pasal 3', 'Comply']], ['tahun' => 2025, 'nomor' => 'Peraturan 2025']);

        $nomor = array_column($this->barisData($this->buka()), 'nomor');

        $this->assertSame(['Peraturan 2026'], $nomor);
    }

    /** Saringan aspek memotong sesuai pilihan. */
    public function test_saringan_aspek_memotong(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->subjek($c, [['Pasal 3', 'Comply']], ['aspek' => 'safety', 'nomor' => 'Peraturan K3']);
        $this->subjek($c, [['Pasal 3', 'Comply']], ['aspek' => 'environment', 'nomor' => 'Peraturan LH']);

        $nomor = array_column($this->barisData($this->buka(['aspek' => 'environment'])), 'nomor');

        $this->assertSame(['Peraturan LH'], $nomor);
    }

    /**
     * Kotak cari di layar TIDAK ikut memotong berkasnya.
     *
     * Layar menjawab "apa yang sedang saya lihat"; berkas ini menjawab
     * "apa yang saya serahkan". Kotak cari yang kebetulan masih terisi
     * akan diam-diam memotong lembar yang diserahkan — dan yang
     * menerimanya tidak punya cara mengetahui bahwa ada yang hilang.
     */
    public function test_kotak_cari_layar_tidak_memotong_berkasnya(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->subjek($c, [['Pasal 3', 'Comply']], ['nomor' => 'Peraturan Alfa']);
        $this->subjek($c, [['Pasal 3', 'Comply']], ['nomor' => 'Peraturan Beta']);

        $nomor = array_column(
            $this->barisData($this->buka(['cari' => 'Alfa', 'punya' => 'Not Comply'])),
            'nomor',
        );

        sort($nomor);
        $this->assertSame(['Peraturan Alfa', 'Peraturan Beta'], $nomor);
    }

    /* ══════════════ arti angkanya ══════════════ */

    /** Lembar rekap ada, dan barisnya menutup dengan SELURUHNYA. */
    public function test_lembar_rekap_menyertai_registernya(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->subjek($c, [['Pasal 3', 'Comply'], ['Pasal 9', 'Not Comply']], ['aspek' => 'safety']);
        $this->subjek($c, [['Pasal 220', 'Comply']], ['aspek' => 'environment']);

        $l = $this->buka()->getSheetByName('Rekap per Aspek');

        $this->assertNotNull($l, 'Berkasnya tidak punya lembar rekap.');

        $aspek = [];
        for ($b = 4; $b < 30; $b++) {
            $nama = (string) $l->getCell("A{$b}")->getValue();
            if ($nama === '') break;
            $aspek[$nama] = (string) $l->getCell("H{$b}")->getValue();
        }

        $this->assertArrayHasKey('SELURUHNYA', $aspek);
        $this->assertSame('66.7%', $aspek['SELURUHNYA'],
            'Persentase seluruhnya bukan 2 comply dari 3 yang dinilai.');
    }

    /**
     * Belum dinilai TIDAK ditulis sebagai nol persen.
     *
     * Nol berarti "seluruhnya tidak comply" — pernyataan yang jauh
     * lebih keras, dan salah. Register yang baru diterbitkan dan belum
     * disentuh siapa pun akan terbaca sebagai kegagalan total.
     */
    public function test_belum_dinilai_bukan_nol_persen(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->subjek($c, [['Pasal 3', null], ['Pasal 9', null]]);

        $l = $this->buka()->getSheetByName('Rekap per Aspek');

        $seluruh = null;
        for ($b = 4; $b < 30; $b++) {
            if ((string) $l->getCell("A{$b}")->getValue() === 'SELURUHNYA') {
                $seluruh = (string) $l->getCell("H{$b}")->getValue();
                break;
            }
        }

        $this->assertNotNull($seluruh);
        $this->assertStringContainsString('belum dinilai', $seluruh);
        $this->assertStringNotContainsString('0.0%', $seluruh);
    }

    /**
     * Naskah Draf tidak ikut menentukan angka rekapnya.
     *
     * Sama seperti di dasbor dan di rekap bulanan. Ikut dihitung di
     * berkas tetapi tidak di layar, dua angka yang seharusnya sama
     * berselisih — dan yang membandingkannya tidak punya cara
     * mengetahui mana yang benar.
     */
    public function test_draf_tidak_ikut_menentukan_angka_rekapnya(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->subjek($c, [['Pasal 3', 'Comply']]);
        $this->subjek($c, [['Pasal 1', 'Not Comply'], ['Pasal 2', 'Not Comply']],
            ['status' => 'Draf']);

        $l = $this->buka()->getSheetByName('Rekap per Aspek');

        for ($b = 4; $b < 30; $b++) {
            if ((string) $l->getCell("A{$b}")->getValue() !== 'SELURUHNYA') continue;

            $this->assertSame('100.0%', (string) $l->getCell("H{$b}")->getValue(),
                'Butir naskah Draf ikut terhitung di lembar rekapnya.');
            $this->assertSame(1, (int) $l->getCell("C{$b}")->getValue(),
                'Total butir pada rekap ikut menghitung naskah Draf.');

            return;
        }

        $this->fail('Baris SELURUHNYA tidak ditemukan pada lembar rekap.');
    }

    /* ══════════════ kop ══════════════ */

    /** Berkasnya berkop terkendali, bukan tabel telanjang. */
    public function test_berkop_dokumen_terkendali(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $this->subjek($c, [['Pasal 3', 'Comply']]);

        $l = $this->buka()->getSheetByName('Register Pemenuhan');

        $this->assertStringContainsString('EVALUASI PEMENUHAN',
            (string) $l->getCell('C2')->getValue());
        $this->assertStringContainsString($c->name, (string) $l->getCell('C6')->getValue());
        $this->assertNotSame(': ', (string) $l->getCell('C4')->getValue(),
            'Kop tanpa nomor dokumen — lembar terkendali tanpa nomor tidak dapat dirujuk.');

        /* Seluruh kop berada di dalam kolom A–D — kolom yang diulang di
           tepi kiri tiap halaman mendatar. Yang lebih lebar daripada itu
           terpotong di batas pengulangannya, dan halaman kedua berjudul
           setengah kalimat. */
        foreach (range(1, 8) as $b) {
            foreach (['E', 'G', 'J', 'N'] as $kolom) {
                $this->assertSame('', (string) $l->getCell("{$kolom}{$b}")->getValue(),
                    "Kop menjangkau kolom {$kolom} baris {$b}; ia akan terbelah saat dicetak.");
            }
        }
    }

    /** Nama berkasnya menyebut tahun evaluasinya. */
    public function test_nama_berkas_menyebut_tahunnya(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $this->subjek($c, [['Pasal 3', 'Comply']]);

        $this->get(route('kepatuhan.ekspor', ['tahun' => 2026]))
             ->assertOk()
             ->assertDownload();

        $nama = $this->get(route('kepatuhan.ekspor', ['tahun' => 2026]))
            ->headers->get('content-disposition');

        $this->assertStringContainsString('2026', (string) $nama);
        $this->assertStringContainsString('.xlsx', (string) $nama);
    }
}
