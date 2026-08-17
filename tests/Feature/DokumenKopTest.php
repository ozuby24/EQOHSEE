<?php

namespace Tests\Feature;

use App\Models\{Company, SmkpAudit, User};
use App\Support\KopDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Kop dokumen terkendali pada berkas cetak audit.
 *
 * Bentuk kop diambil dari berkas audit PT Cemerlang Asa Mandiri 2025:
 * nomor dokumen berprefiks perusahaan, tanggal terbit dan setuju, nomor
 * revisi dua digit, serta nomor halaman yang benar pada tiap lembar.
 */
class DokumenKopTest extends TestCase
{
    use RefreshDatabase;

    private function perusahaan(array $atribut = []): Company
    {
        return Company::create(array_merge([
            'name'          => 'PT Cemerlang Asa Mandiri',
            'doc_no_prefix' => 'CAM',
            'izin_type'     => 'Izin Usaha Pertambangan',
            'commodity'     => 'Batubara',
            'doc_terbit'    => '2023-09-01',
            'doc_setuju'    => '2025-05-31',
            'doc_revisi'    => 1,
        ], $atribut));
    }

    private function audit(?Company $c = null, array $atribut = []): SmkpAudit
    {
        return SmkpAudit::create(array_merge([
            'tahun'      => 2026,
            'status'     => 'draft',
            'company_id' => $c?->id,
        ], $atribut));
    }

    private function masuk(?Company $c = null): void
    {
        /* Yang membuka berkas audit adalah orang di perusahaan itu; batas
           data per perusahaan menolak siapa pun di luarnya. */
        $this->actingAs(User::factory()->create(['company_id' => $c?->id]));
    }

    /* ---------- nomor dokumen ---------- */

    public function test_nomor_dokumen_menggabungkan_prefiks_dan_kode_formulir(): void
    {
        // Berkas acuan PT CAM: Berita Acara bernomor CAM-OHSE-IV.067h.
        $d = KopDokumen::untuk('berita-acara', $this->perusahaan());

        $this->assertSame('FRM/CAM/OHSE/001', $d['nomor']);
    }

    public function test_tiap_formulir_punya_kode_sendiri(): void
    {
        $c = $this->perusahaan();

        $nomor = [];
        foreach (array_keys(KopDokumen::daftar()) as $k) {
            $nomor[] = KopDokumen::untuk($k, $c)['nomor'];
        }

        $this->assertSame($nomor, array_unique($nomor), 'Dua formulir tidak boleh bernomor sama.');
    }

    /**
     * prefiksDari tetap ada, tetapi kini hanya sebagai SARAN.
     *
     * Dahulu ia dipakai langsung sebagai nomor ketika perusahaan belum
     * menetapkan prefiksnya. Sekarang ia hanya mengisi contoh pada
     * borang perusahaan — lihat test_nomor_kosong_bila_prefiks_belum_ditetapkan
     * untuk alasan perubahannya.
     */
    public function test_prefiks_disarankan_dari_nama_perusahaan(): void
    {
        $this->assertSame('CAM', KopDokumen::prefiksDari('PT Cemerlang Asa Mandiri'));
        $this->assertSame('GBU', KopDokumen::prefiksDari('PT Gunung Bara Utama'));
        $this->assertSame('EQ',  KopDokumen::prefiksDari(''));
        $this->assertSame('EQ',  KopDokumen::prefiksDari('PT'));
    }

    public function test_kop_tetap_terbentuk_tanpa_perusahaan(): void
    {
        // Audit lintas perusahaan tidak punya auditi tunggal. Kopnya
        // tetap harus terbentuk — yang kosong hanya nomornya.
        $d = KopDokumen::untuk('rencana-audit', null);

        $this->assertSame('', $d['nomor']);
        $this->assertSame(KopDokumen::DIVISI, $d['divisi']);
        $this->assertSame('00', $d['revisi']);
        $this->assertNotSame('', $d['judul'], 'Judul formulir ikut hilang bersama nomornya.');
    }

    /**
     * Nomor dokumen KOSONG bila prefiksnya belum ditetapkan.
     *
     * Sebelumnya dikarang dari inisial nama perusahaan, dan itu keliru
     * justru karena hasilnya meyakinkan: "PT Gunung Bara Utama"
     * menjadi GBU-OHSE-IV.059, terbaca persis seperti nomor sungguhan.
     * Lembar itu keluar sebagai dokumen terkendali dan diserahkan
     * kepada auditor, membawa nomor yang tidak ada pada daftar induk
     * perusahaan itu — dan bertabrakan dengan penomoran mereka sendiri.
     *
     * Kolom kosong terlihat sebagai pekerjaan yang belum selesai, dan
     * memang begitulah keadaannya. Nomor yang salah terlihat sebagai
     * pekerjaan yang sudah selesai.
     */
    public function test_nomor_kosong_bila_prefiks_belum_ditetapkan(): void
    {
        $tanpa = $this->perusahaan(['name' => 'PT Gunung Bara Utama', 'doc_no_prefix' => null]);

        $this->assertSame('', KopDokumen::untuk('rencana-audit', $tanpa)['nomor'],
            'Nomor dokumen dikarang dari nama perusahaan.');

        // Yang MENETAPKAN prefiksnya tetap bernomor penuh.
        $dengan = $this->perusahaan(['name' => 'PT Gunung Bara Utama', 'doc_no_prefix' => 'GBU']);

        $this->assertSame('FRM/GBU/OHSE/002', KopDokumen::untuk('rencana-audit', $dengan)['nomor']);
    }

    public function test_revisi_ditulis_dua_digit(): void
    {
        $this->assertSame('01', KopDokumen::untuk('berita-acara', $this->perusahaan())['revisi']);
        $this->assertSame('00', KopDokumen::untuk('berita-acara', $this->perusahaan(['doc_revisi' => 0]))['revisi']);
        $this->assertSame('12', KopDokumen::untuk('berita-acara', $this->perusahaan(['doc_revisi' => 12]))['revisi']);
    }

    public function test_divisi_perusahaan_mengalahkan_bawaan(): void
    {
        $d = KopDokumen::untuk('berita-acara', $this->perusahaan(['divisi' => 'HSE Korporat']));

        $this->assertSame('HSE Korporat', $d['divisi']);
        $this->assertSame(KopDokumen::DEPARTEMEN, $d['departemen'], 'Yang tidak diisi tetap memakai bawaan.');
    }

    /* ---------- kop pada berkas cetak ---------- */

    public function test_berkas_cetak_memuat_kop_lengkap(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        foreach ([
            ['smkp.berita-acara',  'berita',  'FRM/CAM/OHSE/001'],
            ['smkp.rencana.cetak', 'rencana', 'FRM/CAM/OHSE/002'],
            ['smkp.laporan',       'laporan', 'LAP/CAM/OHSE/001'],
        ] as [$rute, $mode, $nomor]) {
            $this->get(route($rute, $a))->assertOk()->assertInertia(
                fn (AssertableInertia $p) => $p->component('Print/Smkp')
                    ->where('mode', $mode)
                    ->where('dok.nomor', $nomor)
                    ->where('dok.divisi', 'Occupational Health, Safety and Environment, External')
                    ->where('dok.departemen', KopDokumen::DEPARTEMEN)
                    ->has('dok.terbit')->has('dok.setuju')
            );
        }
    }

    public function test_daftar_hadir_memuat_kop(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        $this->get(route('smkp.hadir.cetak', [$a, 'pembukaan']))
            ->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Print/Smkp')->where('mode', 'hadir')
                ->where('dok.nomor', 'FRM/CAM/OHSE/003')->where('totalLembar', 1));
    }

    /* ---------- penomoran halaman ---------- */

    public function test_rencana_audit_bernomor_tiga_lembar(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        $this->get(route('smkp.rencana.cetak', $a))->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->component('Print/Smkp')->where('totalLembar', 3)
        );
    }

    public function test_berita_acara_bernomor_empat_lembar(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        $this->get(route('smkp.berita-acara', $a))->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->component('Print/Smkp')->where('totalLembar', 4)
        );
    }

    public function test_daftar_hadir_bertambah_lembar_mengikuti_jumlah_peserta(): void
    {
        // Nomor halaman harus ikut isinya; daftar 20 orang tidak muat satu lembar.
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        $this->get(route('smkp.hadir.cetak', [$a, 'pembukaan']))->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->where('totalLembar', 1)
        );

        for ($i = 1; $i <= 20; $i++) {
            $a->attendees()->create(['rapat' => 'pembukaan', 'nama' => 'Peserta '.$i]);
        }

        $this->get(route('smkp.hadir.cetak', [$a, 'pembukaan']))->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->where('totalLembar', 2)
        );
    }

    public function test_penomoran_peserta_berlanjut_antar_lembar(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);
        for ($i = 1; $i <= 20; $i++) {
            $a->attendees()->create(['rapat' => 'pembukaan', 'nama' => 'Peserta '.$i]);
        }

        // Lembar kedua dimulai dari peserta ke-17, bukan mengulang dari 1.
        $this->get(route('smkp.hadir.cetak', [$a, 'pembukaan']))->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->where('totalLembar', 2)->where('hadir.15.nama', 'Peserta 16')->where('hadir.16.nama', 'Peserta 17')
        );
    }

    public function test_laporan_bertambah_lembar_mengikuti_jumlah_temuan(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        /* DUA lembar tetap — ringkasan nilai lalu pelaksanaan audit dan tim
           auditornya — sebelum daftar temuan mulai berlembar sendiri tiap
           enam baris. Yang dijaga uji ini pertumbuhannya, bukan angka
           tetapnya: penomoran "Halaman 2 dari 2" pada berkas terkendali
           tidak boleh menyebut lembar yang tidak ada. */
        $this->get(route('smkp.laporan', $a))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->where('totalLembar', 3));

        for ($i = 1; $i <= 8; $i++) {
            $a->findings()->create([
                'kode_kriteria' => 'I.'.$i, 'jenis' => 'minor',
                'uraian' => 'Temuan '.$i, 'status' => 'Open',
            ]);
        }

        $this->get(route('smkp.laporan', $a))->assertOk()->assertInertia(fn (AssertableInertia $p) => $p->where('totalLembar', 4));
    }

    public function test_lembar_terakhir_tidak_memaksa_halaman_baru(): void
    {
        // Kelas pemutus halaman pada lembar terakhir akan menyisakan satu
        // halaman kosong di akhir cetakan.
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        $this->get(route('smkp.rencana.cetak', $a))->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->component('Print/Smkp')->where('totalLembar', 3)
        );
    }
}
