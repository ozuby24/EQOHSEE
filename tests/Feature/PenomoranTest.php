<?php

namespace Tests\Feature;

use App\Models\{Certificate, Company, Document, HazardReport, Inspection, Procedure, User};
use App\Support\{DataContoh, KopDokumen, Nomor};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penomoran dokumen: JENIS/PERUSAHAAN/DEPARTEMEN/URUT.
 *
 *     FRM/CAM/OHSE/001
 *
 * Satu bentuk untuk seluruh modul. Sebelumnya tiap modul menyusun
 * nomornya sendiri — GDM/2026-08/0001 di gudang, CAM-OHSE-IV.067h pada
 * kop, HZ-2608-001 pada laporan bahaya — dan tak satu pun dapat
 * dicocokkan dengan yang lain pada daftar induk. Bentuk yang
 * berbeda-beda bukan sekadar tidak rapi: daftar induk adalah yang
 * diperiksa auditor, dan nomor yang tidak sepola membuat dokumen yang
 * sama tampak berasal dari dua sistem.
 */
class PenomoranTest extends TestCase
{
    use RefreshDatabase;

    private function cam(array $tambahan = []): Company
    {
        return Company::create($tambahan + [
            'name' => 'PT Cemerlang Asa Mandiri', 'doc_no_prefix' => 'CAM',
        ]);
    }

    /* ═══════════ bentuk ═══════════ */

    public function test_bentuk_nomor_sesuai_skema(): void
    {
        $c = $this->cam();

        $this->assertSame('FRM/CAM/OHSE/001', Nomor::susun('Formulir', $c, 1));
        $this->assertSame('SOP/CAM/OHSE/012', Nomor::susun('Prosedur', $c, 12));
        $this->assertSame('IK/CAM/OHSE/003',  Nomor::susun('Instruksi Kerja', $c, 3));
        $this->assertSame('KEB/CAM/OHSE/001', Nomor::susun('Kebijakan', $c, 1));
        $this->assertSame('LAP/CAM/OHSE/007', Nomor::susun('Laporan', $c, 7));
    }

    /** Singkatan departemen dapat diganti perusahaan; kosong berarti OHSE. */
    public function test_departemen_mengikuti_perusahaan(): void
    {
        $this->assertSame('FRM/CAM/OHSE/001',
            Nomor::susun('Formulir', $this->cam(), 1));

        $this->assertSame('FRM/CAM/ENG/001',
            Nomor::susun('Formulir', $this->cam(['dept_kode' => 'ENG']), 1));
    }

    /**
     * Perusahaan tanpa singkatan menghasilkan nomor KOSONG.
     *
     * Bukan "FRM//OHSE/001", yang terlihat seperti kerusakan sistem,
     * dan bukan singkatan yang dikarang dari namanya, yang terbaca
     * seperti nomor sungguhan lalu bertabrakan dengan penomoran
     * perusahaan itu sendiri di daftar induknya.
     */
    public function test_tanpa_singkatan_perusahaan_nomornya_kosong(): void
    {
        $c = Company::create(['name' => 'PT Belum Diatur']);

        $this->assertSame('', Nomor::susun('Formulir', $c, 1));
        $this->assertSame('', KopDokumen::untuk('daftar-induk', $c)['nomor']);
    }

    public function test_urai_mengembalikan_bagiannya(): void
    {
        $this->assertSame(
            ['jenis' => 'FRM', 'perusahaan' => 'CAM', 'departemen' => 'OHSE', 'urut' => 1],
            Nomor::urai('FRM/CAM/OHSE/001'),
        );

        $this->assertNull(Nomor::urai('CAM-OHSE-IV.067h'), 'Bentuk lama diterima sebagai sah.');
        $this->assertNull(Nomor::urai(null));
    }

    /* ═══════════ urutan ═══════════ */

    /**
     * Urutan dibaca dari nomor yang SUDAH ADA, bukan dari jumlah baris.
     *
     * Menghitung baris membuat nomor terpakai ulang begitu ada yang
     * dihapus — dan dokumen terkendali yang bernomor sama dengan
     * dokumen yang pernah ditarik adalah persis yang dicari auditor.
     */
    public function test_urutan_tidak_terpakai_ulang_sesudah_penghapusan(): void
    {
        $c = $this->cam();

        foreach ([1, 2, 3] as $i) {
            Document::withoutGlobalScopes()->create([
                'company_id' => $c->id, 'kode' => Nomor::susun('Formulir', $c, $i),
                'judul' => 'Dokumen '.$i, 'jenis' => 'Formulir', 'status' => 'berlaku',
            ]);
        }

        Document::withoutGlobalScopes()->where('kode', 'FRM/CAM/OHSE/002')->delete();

        $this->assertSame('FRM/CAM/OHSE/004',
            Nomor::berikut('documents', 'kode', 'Formulir', $c),
            'Nomor bekas dokumen yang dihapus dipakai ulang.');
    }

    /**
     * Urutan berjalan per perusahaan, bukan se-pemasangan.
     *
     * FRM/CAM/OHSE/001 dan FRM/GBU/OHSE/001 keduanya sah.
     */
    public function test_urutan_berjalan_terpisah_tiap_perusahaan(): void
    {
        $cam = $this->cam();
        $gbu = Company::create(['name' => 'PT Gunung Bara Utama', 'doc_no_prefix' => 'GBU']);

        Document::withoutGlobalScopes()->create([
            'company_id' => $cam->id, 'kode' => 'FRM/CAM/OHSE/001',
            'judul' => 'Milik CAM', 'jenis' => 'Formulir', 'status' => 'berlaku',
        ]);

        $this->assertSame('FRM/GBU/OHSE/001',
            Nomor::berikut('documents', 'kode', 'Formulir', $gbu),
            'Nomor perusahaan lain ikut menggeser urutan.');
    }

    /* ═══════════ dipakai seluruh modul ═══════════ */

    /**
     * Data contoh memakai skema yang sama di setiap modul, dan
     * kodenya DISUSUN dari perusahaannya — bukan ditulis tetap.
     *
     * Kode tetap akan memperlihatkan penomoran milik perusahaan lain
     * kepada yang sedang memeriksanya, dan yang diperiksa justru
     * apakah penomorannya sudah benar.
     */
    public function test_data_contoh_seluruh_modul_sepola(): void
    {
        $c = $this->cam(['demo' => true]);
        $admin = User::factory()->create(['is_admin' => true, 'company_id' => $c->id]);
        User::factory()->create(['company_id' => $c->id]);

        DataContoh::muat($c, $admin);

        $periksa = [
            'documents'      => [Document::class, 'kode'],
            'procedures'     => [Procedure::class, 'code'],
            'hazard_reports' => [HazardReport::class, 'kode'],
            'inspections'    => [Inspection::class, 'kode'],
            'certificates'   => [Certificate::class, 'certificate_number'],
        ];

        foreach ($periksa as $nama => [$kelas, $kolom]) {
            $nomor = $kelas::withoutGlobalScopes()->pluck($kolom)->filter()->all();

            $this->assertNotEmpty($nomor, "Modul {$nama} tidak punya baris bernomor.");

            foreach ($nomor as $n) {
                $bagian = Nomor::urai($n);

                $this->assertNotNull($bagian, "Nomor \"{$n}\" pada {$nama} tidak mengikuti skema.");
                $this->assertSame('CAM', $bagian['perusahaan'],
                    "Nomor \"{$n}\" memakai singkatan perusahaan lain.");
                $this->assertSame('OHSE', $bagian['departemen']);
            }
        }
    }

    /** Kop laporan ikut skema yang sama. */
    public function test_kop_laporan_sepola(): void
    {
        $c = $this->cam();

        foreach (['daftar-induk', 'rencana-audit', 'laporan-air', 'register-hazard'] as $kunci) {
            $nomor = KopDokumen::untuk($kunci, $c)['nomor'];

            $this->assertNotNull(Nomor::urai($nomor),
                "Kop \"{$kunci}\" bernomor \"{$nomor}\", tidak mengikuti skema.");
        }
    }

    /**
     * Nomor formulir baku TETAP, tidak berjalan tiap kali dicetak.
     *
     * Ini formulir yang diterbitkan berulang kali; nomor yang berubah
     * tiap pencetakan bukan nomor dokumen terkendali.
     */
    public function test_nomor_formulir_baku_tidak_berubah(): void
    {
        $c = $this->cam();

        $pertama = KopDokumen::untuk('daftar-induk', $c)['nomor'];
        $kedua   = KopDokumen::untuk('daftar-induk', $c)['nomor'];

        $this->assertSame($pertama, $kedua);
        $this->assertNotSame($pertama, KopDokumen::untuk('rencana-audit', $c)['nomor'],
            'Dua formulir berbeda bernomor sama.');
    }
}
