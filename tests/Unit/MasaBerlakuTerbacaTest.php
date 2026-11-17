<?php

namespace Tests\Unit;

use App\Support\MasaBerlakuTerbaca;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pembacaan masa berlaku dari berkas SIM yang diunggah.
 *
 * Yang diuji di sini bukan seberapa sering ia berhasil melainkan
 * seberapa aman ia gagal. Pembaca yang mengusulkan tanggal salah lebih
 * berbahaya daripada pembaca yang tidak mengusulkan apa-apa: kolom
 * kosong terlihat belum diisi, sedangkan tanggal keliru terbaca sebagai
 * sudah diperiksa — dan yang memakainya di gerbang tidak punya cara
 * mengetahui bedanya.
 */
class MasaBerlakuTerbacaTest extends TestCase
{
    private function kini(): Carbon
    {
        return Carbon::create(2026, 8, 19);
    }

    #[Test]
    public function membaca_tanggal_dari_nama_berkas(): void
    {
        foreach ([
            'SIM_B2_Budi_31-12-2028.jpg'  => '2028-12-31',
            'sim-2029-03-15.pdf'          => '2029-03-15',
            'SIM20301231.png'             => '2030-12-31',
            'SIM B2 15.06.2027 scan.jpeg' => '2027-06-15',
        ] as $nama => $harap) {
            $hasil = MasaBerlakuTerbaca::dariNama($nama, $this->kini());

            $this->assertNotNull($hasil, "Tidak terbaca dari \"{$nama}\".");
            $this->assertSame($harap, $hasil['tanggal'], "Salah baca dari \"{$nama}\".");
            $this->assertSame('nama berkas', $hasil['sumber']);
        }
    }

    /**
     * Angka yang kebetulan berbentuk tanggal tidak diusulkan.
     *
     * Nomor SIM, nomor register, dan tanggal lahir semuanya berupa
     * deretan angka. Yang membedakannya dari masa berlaku hanya
     * kewajarannya sebagai tanggal di masa depan dekat.
     */
    #[Test]
    public function menolak_tanggal_yang_tidak_masuk_akal(): void
    {
        foreach ([
            'SIM_Budi_lahir_12-05-1988.jpg',   // tanggal lahir
            'SIM_2045-01-01.pdf',              // terlalu jauh ke depan
            'SIM_1999-12-31.pdf',              // terlalu jauh ke belakang
            'SIM_2028-13-45.pdf',              // bukan tanggal yang ada
        ] as $nama) {
            $this->assertNull(
                MasaBerlakuTerbaca::dariNama($nama, $this->kini()),
                "\"{$nama}\" seharusnya tidak diusulkan sama sekali.",
            );
        }
    }

    /**
     * Yang dapat tertukar hari dan bulannya ditandai TIDAK pasti.
     *
     * 31-12 hanya dapat dibaca satu cara; 01-02 dapat dibaca dua.
     * Keduanya tetap diusulkan — dokumen Indonesia menulis hari lebih
     * dulu — tetapi yang kedua harus dapat diminta diperiksa orangnya.
     */
    #[Test]
    public function menandai_tanggal_yang_dapat_tertukar(): void
    {
        $pasti = MasaBerlakuTerbaca::dariNama('SIM_31-12-2028.jpg', $this->kini());
        $ragu  = MasaBerlakuTerbaca::dariNama('SIM_01-02-2028.jpg', $this->kini());

        $this->assertTrue($pasti['pasti'], '31-12 tidak dapat tertukar.');
        $this->assertSame('2028-02-01', $ragu['tanggal'], 'Hari ditulis lebih dulu.');
        $this->assertFalse($ragu['pasti'], '01-02 dapat terbaca dua cara dan harus ditandai.');
    }

    /**
     * Nama berkas tanpa tanggal tidak menghasilkan apa pun.
     *
     * Ini keadaan yang paling sering terjadi di lapangan, dan karena itu
     * yang paling penting untuk tidak menghasilkan tebakan.
     */
    #[Test]
    public function nama_tanpa_tanggal_tidak_menghasilkan_usulan(): void
    {
        foreach (['SIM Budi.jpg', 'scan001.pdf', 'WhatsApp Image.jpeg'] as $nama) {
            $this->assertNull(MasaBerlakuTerbaca::dariNama($nama, $this->kini()));
        }
    }

    /**
     * Berkas gambar tidak dibaca isinya, dan itu bukan galat.
     *
     * Hasil pindaian tidak punya lapisan teks. Yang tidak boleh terjadi
     * adalah unggahannya gagal karena pembacaannya gagal.
     */
    #[Test]
    public function gambar_tidak_membuat_pembacaan_gagal(): void
    {
        $berkas = UploadedFile::fake()->image('scan.jpg');

        $this->assertNull(MasaBerlakuTerbaca::dariUnggahan($berkas, $this->kini()));
    }

    /**
     * Baris yang menyebut "berlaku" didahulukan atas baris lain.
     *
     * Sebuah SIM memuat beberapa tanggal — terbit, lahir, berlaku.
     * Mengambil yang pertama ditemukan berarti mengambil tanggal lahir
     * hampir setiap kali.
     */
    #[Test]
    public function mendahulukan_baris_yang_menyebut_masa_berlaku(): void
    {
        $pdf = $this->pdfBerteks([
            'SURAT IZIN MENGEMUDI',
            'Tanggal terbit 01-03-2026',
            'Berlaku s/d 28-02-2031',
        ]);

        if ($pdf === null) {
            $this->markTestSkipped('pdftotext tidak tersedia di mesin ini.');
        }

        $hasil = MasaBerlakuTerbaca::dariUnggahan($pdf, $this->kini());

        $this->assertNotNull($hasil, 'Teks PDF tidak terbaca sama sekali.');
        $this->assertSame('2031-02-28', $hasil['tanggal'],
            'Tanggal terbit terambil, bukan tanggal berlakunya.');
        $this->assertStringContainsString('Berlaku', $hasil['petikan'],
            'Petikannya harus memperlihatkan baris yang dipakai, supaya dapat diperiksa.');
    }

    /**
     * PDF sederhana berisi baris teks, dirakit tanpa pustaka tambahan.
     *
     * Cukup untuk `pdftotext`; tidak berpura-pura menjadi PDF yang sah
     * bagi pembaca lain.
     */
    private function pdfBerteks(array $baris): ?UploadedFile
    {
        exec('command -v pdftotext', $_, $kode);
        if ($kode !== 0) return null;

        $isi = '';
        foreach ($baris as $i => $b) {
            $isi .= sprintf("BT /F1 12 Tf 40 %d Td (%s) Tj ET\n", 760 - $i * 20, addcslashes($b, '()\\'));
        }

        $objek = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] "
                ."/Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n",
            "4 0 obj\n<< /Length ".strlen($isi)." >>\nstream\n{$isi}endstream\nendobj\n",
            "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $ofs = [];

        foreach ($objek as $o) {
            $ofs[] = strlen($pdf);
            $pdf .= $o;
        }

        $awalXref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objek) + 1)."\n0000000000 65535 f \n";
        foreach ($ofs as $o) $pdf .= sprintf("%010d 00000 n \n", $o);
        $pdf .= "trailer\n<< /Size ".(count($objek) + 1)." /Root 1 0 R >>\nstartxref\n{$awalXref}\n%%EOF";

        $jalur = tempnam(sys_get_temp_dir(), 'sim').'.pdf';
        file_put_contents($jalur, $pdf);

        return new UploadedFile($jalur, 'sim.pdf', 'application/pdf', null, true);
    }
}
