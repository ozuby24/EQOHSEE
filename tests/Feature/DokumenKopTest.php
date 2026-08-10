<?php

namespace Tests\Feature;

use App\Models\{Company, SmkpAudit, User};
use App\Support\KopDokumen;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function masuk(): void
    {
        $this->actingAs(User::factory()->create());
    }

    /* ---------- nomor dokumen ---------- */

    public function test_nomor_dokumen_menggabungkan_prefiks_dan_kode_formulir(): void
    {
        // Berkas acuan PT CAM: Berita Acara bernomor CAM-OHSE-IV.067h.
        $d = KopDokumen::untuk('berita-acara', $this->perusahaan());

        $this->assertSame('CAM-OHSE-IV.067h', $d['nomor']);
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

    public function test_prefiks_diturunkan_dari_nama_saat_belum_ditetapkan(): void
    {
        // Perusahaan yang baru didaftarkan belum tentu punya prefiks; berkas
        // cetaknya tetap harus bernomor terbaca, bukan berawalan tanda hubung.
        $this->assertSame('CAM', KopDokumen::prefiksDari('PT Cemerlang Asa Mandiri'));
        $this->assertSame('GBU', KopDokumen::prefiksDari('PT Gunung Bara Utama'));
        $this->assertSame('EQ',  KopDokumen::prefiksDari(''));
        $this->assertSame('EQ',  KopDokumen::prefiksDari('PT'));
    }

    public function test_kop_tetap_terbentuk_tanpa_perusahaan(): void
    {
        // Audit lintas perusahaan tidak punya auditi tunggal.
        $d = KopDokumen::untuk('rencana-audit', null);

        $this->assertSame('EQ-OHSE-IV.059', $d['nomor']);
        $this->assertSame(KopDokumen::DIVISI, $d['divisi']);
        $this->assertSame('00', $d['revisi']);
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
        $this->masuk();
        $a = $this->audit($this->perusahaan());

        foreach ([
            ['smkp.berita-acara',  ['nomor' => 'CAM-OHSE-IV.067h']],
            ['smkp.rencana.cetak', ['nomor' => 'CAM-OHSE-IV.059']],
            ['smkp.laporan',       ['nomor' => 'CAM-OHSE-IV.067']],
        ] as [$rute, $harap]) {
            $this->get(route($rute, $a))
                ->assertOk()
                ->assertSee('No. Dokumen')
                ->assertSee('Tgl Penerbitan')
                ->assertSee('Tgl Persetujuan')
                ->assertSee('No. Revisi')
                ->assertSee('Halaman')
                ->assertSee('Divisi')
                ->assertSee('Departemen')
                ->assertSee($harap['nomor'])
                ->assertSee('01 September 2023')      // tanggal penerbitan
                ->assertSee('31 Mei 2025');           // tanggal persetujuan
        }
    }

    public function test_daftar_hadir_memuat_kop(): void
    {
        $this->masuk();
        $a = $this->audit($this->perusahaan());

        $this->get(route('smkp.hadir.cetak', [$a, 'pembukaan']))
            ->assertOk()
            ->assertSee('CAM-OHSE-IV.067g')
            ->assertSee('Halaman');
    }

    /* ---------- penomoran halaman ---------- */

    public function test_rencana_audit_bernomor_tiga_lembar(): void
    {
        $this->masuk();
        $a = $this->audit($this->perusahaan());

        $res = $this->get(route('smkp.rencana.cetak', $a))->assertOk();

        foreach (['1 dari 3', '2 dari 3', '3 dari 3'] as $h) {
            $res->assertSee($h);
        }
        $this->assertSame(3, substr_count($res->getContent(), 'class="lembar '), 'Harus tiga lembar.');
    }

    public function test_berita_acara_bernomor_empat_lembar(): void
    {
        $this->masuk();
        $a = $this->audit($this->perusahaan());

        $res = $this->get(route('smkp.berita-acara', $a))->assertOk();

        foreach (['1 dari 4', '2 dari 4', '3 dari 4', '4 dari 4'] as $h) {
            $res->assertSee($h);
        }
    }

    public function test_daftar_hadir_bertambah_lembar_mengikuti_jumlah_peserta(): void
    {
        // Nomor halaman harus ikut isinya; daftar 20 orang tidak muat satu lembar.
        $this->masuk();
        $a = $this->audit($this->perusahaan());

        $this->get(route('smkp.hadir.cetak', [$a, 'pembukaan']))
            ->assertOk()
            ->assertSee('1 dari 1');

        for ($i = 1; $i <= 20; $i++) {
            $a->attendees()->create(['rapat' => 'pembukaan', 'nama' => 'Peserta '.$i]);
        }

        $res = $this->get(route('smkp.hadir.cetak', [$a, 'pembukaan']))->assertOk();
        $res->assertSee('1 dari 2')->assertSee('2 dari 2');
    }

    public function test_penomoran_peserta_berlanjut_antar_lembar(): void
    {
        $this->masuk();
        $a = $this->audit($this->perusahaan());
        for ($i = 1; $i <= 20; $i++) {
            $a->attendees()->create(['rapat' => 'pembukaan', 'nama' => 'Peserta '.$i]);
        }

        // Lembar kedua dimulai dari peserta ke-17, bukan mengulang dari 1.
        $this->get(route('smkp.hadir.cetak', [$a, 'pembukaan']))
            ->assertOk()
            ->assertSeeInOrder(['1 dari 2', 'Peserta 16', '2 dari 2', 'Peserta 17'], false);
    }

    public function test_laporan_bertambah_lembar_mengikuti_jumlah_temuan(): void
    {
        $this->masuk();
        $a = $this->audit($this->perusahaan());

        $this->get(route('smkp.laporan', $a))->assertOk()->assertSee('1 dari 2');

        for ($i = 1; $i <= 8; $i++) {
            $a->findings()->create([
                'kode_kriteria' => 'I.'.$i, 'jenis' => 'minor',
                'uraian' => 'Temuan '.$i, 'status' => 'Open',
            ]);
        }

        $this->get(route('smkp.laporan', $a))->assertOk()->assertSee('1 dari 3')->assertSee('3 dari 3');
    }

    public function test_lembar_terakhir_tidak_memaksa_halaman_baru(): void
    {
        // Kelas pemutus halaman pada lembar terakhir akan menyisakan satu
        // halaman kosong di akhir cetakan.
        $this->masuk();
        $a = $this->audit($this->perusahaan());

        $isi = $this->get(route('smkp.rencana.cetak', $a))->getContent();
        $akhir = strrpos($isi, 'class="lembar ');

        $this->assertStringNotContainsString(
            'lembar-putus',
            substr($isi, $akhir, 60),
            'Lembar terakhir tidak boleh berkelas lembar-putus.'
        );
    }
}
