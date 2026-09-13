<?php

namespace Tests\Feature;

use App\Models\{Company, SmkpAudit, User};
use App\Support\{Smkp, SmkpLaporan};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Laporan Audit Internal SMKP — bagian naratif dan penomoran temuannya.
 *
 * Laporan yang dibaca inspektur tambang bukan hanya angka. Berkas acuan
 * menempuh urutan: latar belakang beserta dasar hukumnya, gambaran umum
 * auditi, ringkasan penerapan tiap elemen, lingkup audit, pelaksanaan
 * dan tim, lalu baru penilaiannya — dan ditutup lampiran serta
 * distribusi.
 *
 * Tanpa bagian itu, "39,14%" adalah angka tanpa perusahaan di
 * belakangnya: pembacanya tidak dapat mengetahui berapa pekerja yang
 * diaudit, kegiatan apa yang dijalankan, atau mengapa sebuah elemen
 * memperoleh nilai serendah itu.
 */
class SmkpLaporanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Masuk sebagai pengguna perusahaan tertentu.
     *
     * Batas perusahaan berlaku penuh di sini: audit milik perusahaan lain
     * — beserta temuannya — tidak terlihat sama sekali. Uji yang membuat
     * perusahaan sendiri lalu masuk tanpa menyebutkannya akan memperoleh
     * 404 dan daftar temuan kosong, bukan karena penomorannya rusak.
     */
    private function masuk(?Company $c = null): User
    {
        $u = User::factory()->create([
            'email_verified_at' => now(),
            'company_id'        => $c?->id,
        ]);
        $this->actingAs($u);
        return $u;
    }

    private function audit(array $atribut = []): SmkpAudit
    {
        return SmkpAudit::create(array_merge(['tahun' => 2026, 'status' => 'draft'], $atribut));
    }

    /* ═══════════ awalan nomor temuan ═══════════ */

    /**
     * Awalan datang dari doc_no_prefix — sumber yang sama dengan
     * Formulir Rekapitulasi Ketidaksesuaian.
     *
     * Awalan kedua yang diturunkan sendiri akan membuat satu temuan
     * bernomor ISM-MYR-01 pada rekapitulasi dan TPJ-MYR-01 pada laporan,
     * dan keduanya beredar ke pihak yang sama.
     */
    public function test_awalan_diambil_dari_prefiks_dokumen_perusahaan(): void
    {
        $c = Company::create([
            'name' => 'PT Indo Sejahtera Manunggal',
            'code' => 'xyz', 'doc_no_prefix' => 'ism',
        ]);
        $this->masuk($c);

        $this->assertSame('ISM', SmkpLaporan::awalan($this->audit(['company_id' => $c->id])));
    }

    public function test_tanpa_prefiks_dokumen_awalan_dari_kode(): void
    {
        $c = Company::create(['name' => 'PT Indo Sejahtera Manunggal', 'code' => 'ism']);
        $this->masuk($c);

        $this->assertSame('ISM', SmkpLaporan::awalan($this->audit(['company_id' => $c->id])));
    }

    /**
     * SATU TEMUAN, SATU KODE — pada keempat berkas yang menyebutnya.
     *
     * Laporan Audit mengurutkan temuan menurut kategori, Formulir
     * Rekapitulasi menurut urutan kriteria, Formulir Rencana Tindak
     * Lanjut menurut tenggat. Menomori masing-masing dari urutannya
     * sendiri melahirkan tiga kode berbeda bagi satu temuan — dan
     * ketiganya beredar ke pihak yang sama, yang lalu tidak dapat
     * mencocokkan "MYR-03" pada risalah dengan "MYR-01" pada formulir.
     */
    public function test_satu_temuan_satu_kode_pada_keempat_berkas(): void
    {
        $c = Company::create(['name' => 'PT Uji', 'doc_no_prefix' => 'UJI']);
        $this->masuk($c);
        $a = $this->audit(['company_id' => $c->id]);

        /* Sengaja disusun supaya ketiga urutan berbeda: yang kriterianya
           paling awal bertenggat paling akhir, dan kategorinya berselang. */
        foreach ([
            ['I.1',   'mayor', '2026-12-31'],
            ['II.1',  'minor', '2026-01-31'],
            ['III.1', 'mayor', '2026-06-30'],
            ['IV.1',  'minor', '2026-03-31'],
        ] as [$kode, $jenis, $tenggat]) {
            $a->findings()->create([
                'kode_kriteria' => $kode, 'jenis' => $jenis,
                'uraian' => 'Uji '.$kode, 'status' => 'Open',
                'target_selesai' => $tenggat,
            ]);
        }

        $a = $a->fresh();

        // 1. Peta bersama — urutan kriteria, bernomor per kategori.
        $peta = SmkpLaporan::petaNomor($a);

        $this->assertSame(
            ['UJI-MYR-01', 'UJI-MIN-01', 'UJI-MYR-02', 'UJI-MIN-02'],
            array_values($peta),
        );

        // 2. Laporan Audit.
        $laporan = [];
        foreach (SmkpLaporan::temuanPerKategori($a) as $g) {
            foreach ($g['baris'] as $b) $laporan[$b['id']] = $b['nomor'];
        }

        // 3. Formulir Rekapitulasi Ketidaksesuaian.
        $rekap = collect($this->get(route('smkp.rekapNc', $a))->assertOk()
            ->viewData('page')['props']['temuan'])
            ->pluck('kode_nc', 'id')->all();

        // 4. Formulir Rencana Tindak Lanjut.
        $rtl = collect($this->get(route('smkp.rencanaTindak', $a))->assertOk()
            ->viewData('page')['props']['temuan'])
            ->pluck('kode_nc', 'id')->all();

        foreach ($peta as $id => $kode) {
            $this->assertSame($kode, $laporan[$id] ?? null, "Laporan Audit, temuan $id.");
            $this->assertSame($kode, $rekap[$id]   ?? null, "Formulir Rekapitulasi, temuan $id.");
            $this->assertSame($kode, $rtl[$id]     ?? null, "Formulir Rencana Tindak Lanjut, temuan $id.");
        }
    }

    /**
     * Nomor tidak berubah ketika sebuah temuan ditutup atau tenggatnya digeser.
     *
     * "Temuan MYR-03" disebut dalam risalah rapat penutupan dan dalam
     * surat-menyurat sesudahnya. Nomor yang berpindah membuat risalah itu
     * menunjuk temuan lain minggu depan.
     */
    public function test_nomor_tidak_berpindah_saat_temuan_ditutup(): void
    {
        $c = Company::create(['name' => 'PT Uji', 'doc_no_prefix' => 'UJI']);
        $this->masuk($c);
        $a = $this->audit(['company_id' => $c->id]);

        foreach (['I.1', 'II.1', 'III.1'] as $kode) {
            $a->findings()->create([
                'kode_kriteria' => $kode, 'jenis' => 'mayor',
                'uraian' => 'Uji', 'status' => 'Open',
            ]);
        }

        $sebelum = SmkpLaporan::petaNomor($a->fresh());

        $a->findings()->where('kode_kriteria', 'II.1')
            ->update(['status' => 'Closed', 'target_selesai' => '2020-01-01']);

        $this->assertSame($sebelum, SmkpLaporan::petaNomor($a->fresh()));
    }

    /**
     * Tanpa kode, huruf awal tiap kata namanya — TANPA "PT".
     *
     * Awalan "PTISM" tidak menunjuk siapa pun: setiap perseroan terbatas
     * di Indonesia akan berbagi dua huruf pertamanya.
     */
    public function test_tanpa_kode_awalan_dari_inisial_tanpa_bentuk_badan_usaha(): void
    {
        $c = Company::create(['name' => 'PT Indo Sejahtera Manunggal']);
        $this->masuk($c);

        $this->assertSame('ISM', SmkpLaporan::awalan($this->audit(['company_id' => $c->id])));
    }

    public function test_tanpa_perusahaan_awalan_tetap_ada(): void
    {
        $this->masuk();

        $this->assertSame('NC', SmkpLaporan::awalan($this->audit()));
    }

    /* ═══════════ penomoran temuan ═══════════ */

    /**
     * Nomor berulang dari 01 pada tiap kategori, bukan satu deret lurus.
     *
     * Laporan acuan menomori ISM-MYR-01..28 lalu ISM-MIN-01..14. Nomor
     * yang berlanjut lintas kategori membuat "ketidaksesuaian nomor 30"
     * tidak dapat dibaca sebagai mayor atau minor tanpa membuka tabelnya.
     */
    public function test_nomor_temuan_berulang_pada_tiap_kategori(): void
    {
        $c = Company::create(['name' => 'PT Uji', 'code' => 'UJI']);
        $this->masuk($c);
        $a = $this->audit(['company_id' => $c->id]);

        foreach ([['mayor', 2], ['minor', 3], ['obs', 1]] as [$jenis, $n]) {
            for ($i = 0; $i < $n; $i++) {
                $a->findings()->create([
                    'kode_kriteria' => 'I.'.($i + 1), 'jenis' => $jenis,
                    'uraian' => 'Uji '.$jenis.' '.$i, 'status' => 'Open',
                ]);
            }
        }

        $k = SmkpLaporan::temuanPerKategori($a->fresh());

        $this->assertSame(['UJI-MYR-01', 'UJI-MYR-02'], array_column($k['mayor']['baris'], 'nomor'));
        $this->assertSame(['UJI-MIN-01', 'UJI-MIN-02', 'UJI-MIN-03'], array_column($k['minor']['baris'], 'nomor'));
    }

    /**
     * Observasi TIDAK ikut didaftar sebagai ketidaksesuaian.
     *
     * Laporan acuan menghitung ketidaksesuaian saja; observasi yang ikut
     * terhitung menaikkan angka "minor" atas catatan yang tidak menuntut
     * tindakan perbaikan apa pun.
     */
    public function test_observasi_tidak_ikut_daftar_ketidaksesuaian(): void
    {
        $this->masuk();
        $a = $this->audit();

        $a->findings()->create([
            'kode_kriteria' => 'I.1', 'jenis' => 'obs',
            'uraian' => 'Papan informasi tertutup material.', 'status' => 'Open',
        ]);

        $k = SmkpLaporan::temuanPerKategori($a->fresh());

        $this->assertSame([], $k['mayor']['baris']);
        $this->assertSame([], $k['minor']['baris']);
        $this->assertSame([], $k['kritikal']['baris']);
    }

    /**
     * Bagian Kritikal tetap ada walau nihil.
     *
     * Rubrik berbasis poin tidak pernah menghasilkannya — ia peningkatan
     * yang ditetapkan auditor. Bagian yang hilang tidak dapat dibedakan
     * dari bagian yang nihil, dan laporan acuan pun menuliskan "Kategori
     * Kritikal: Tidak ada".
     */
    public function test_bagian_kritikal_tetap_ada_walau_nihil(): void
    {
        $this->masuk();

        $k = SmkpLaporan::temuanPerKategori($this->audit());

        $this->assertArrayHasKey('kritikal', $k);
        $this->assertSame('KRT', $k['kritikal']['singkat']);
    }

    /* ═══════════ praktik terbaik ═══════════ */

    public function test_praktik_terbaik_hanya_butir_bernilai_penuh(): void
    {
        $this->masuk();

        // I.1 bernilai maksimum, I.2 separuh, I.3 N/A.
        $a = $this->audit(['hasil' => [
            'I.1' => ['v' => 4], 'I.2' => ['v' => 2], 'I.3' => ['v' => Smkp::NA],
        ]]);

        $kode = array_column(SmkpLaporan::praktikTerbaik($a), 'kode');

        $this->assertContains('I.1', $kode);
        $this->assertNotContains('I.2', $kode);
        $this->assertNotContains('I.3', $kode, 'Butir N/A bukan praktik terbaik — ia tidak dinilai.');
    }

    /* ═══════════ isian dan bawaannya ═══════════ */

    public function test_lampiran_dan_distribusi_punya_daftar_bawaan(): void
    {
        $isi = SmkpLaporan::isi(null);

        $this->assertSame(SmkpLaporan::LAMPIRAN, $isi['lampiran']);
        $this->assertSame(SmkpLaporan::DISTRIBUSI, $isi['distribusi']);
    }

    /**
     * Daftar yang pernah disimpan menang atas bawaannya — termasuk yang kosong.
     *
     * Menghapus seluruh lampiran adalah keputusan yang sah; memasangnya
     * kembali dari bawaan membuat laporan menyebutkan sembilan lampiran
     * yang tidak dilampirkan.
     */
    public function test_daftar_kosong_yang_disimpan_tidak_dikembalikan_ke_bawaan(): void
    {
        $isi = SmkpLaporan::isi(['lampiran' => [], 'distribusi' => []]);

        $this->assertSame([], $isi['lampiran']);
        $this->assertSame([], $isi['distribusi']);
    }

    /* ═══════════ halaman dan simpan ═══════════ */

    public function test_halaman_penyusunan_membawa_ruas_dan_bawaannya(): void
    {
        $this->masuk();
        $a = $this->audit();

        $this->get(route('smkp.laporan.susun', $a))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Smkp/Laporan')
                ->has('ruas')
                ->has('dasarHukum', count(SmkpLaporan::DASAR_HUKUM))
                ->has('bawaan.lampiran', count(SmkpLaporan::LAMPIRAN))
                ->has('tautan.simpan'));
    }

    public function test_narasi_tersimpan_dan_dibersihkan(): void
    {
        $this->masuk();
        $a = $this->audit();

        $this->post(route('smkp.laporan.simpan', $a), [
            'domisili'  => '  PT Uji berkedudukan di Samarinda.  ',
            'kegiatan'  => 'Eksplorasi pengeboran.',
            'penerapan' => 'Sedang menyesuaikan sistem.',
            'kesimpulan'=> 'Perlu perbaikan.',
            'elemen'    => ['I' => 'Kebijakan belum ditinjau.', 'ZZ' => 'kode karangan'],
            'peralatan' => [
                ['jenis' => ' Alat Bor ', 'jumlah' => '21'],
                ['jenis' => '',           'jumlah' => '9'],
            ],
            'lampiran'  => ['Formulir Kriteria Audit', '  '],
        ])->assertRedirect(route('smkp.laporan.susun', $a));

        $l = $a->fresh()->laporan;

        $this->assertSame('PT Uji berkedudukan di Samarinda.', $l['domisili']);
        $this->assertSame(['I' => 'Kebijakan belum ditinjau.'], $l['elemen'],
            'Kode elemen karangan tidak boleh menumpang di berkas audit.');
        $this->assertSame([['jenis' => 'Alat Bor', 'jumlah' => '21']], $l['peralatan']);
        $this->assertSame(['Formulir Kriteria Audit'], $l['lampiran']);
    }

    /* ═══════════ cetak ═══════════ */

    public function test_laporan_cetak_membawa_narasi_dan_kategori(): void
    {
        $c = Company::create(['name' => 'PT Uji', 'code' => 'UJI', 'workers_employee' => 60, 'workers_sub' => 65]);
        $this->masuk($c);
        $a = $this->audit(['company_id' => $c->id, 'laporan' => ['domisili' => 'Samarinda.']]);

        $a->findings()->create([
            'kode_kriteria' => 'I.1', 'jenis' => 'mayor',
            'uraian' => 'Kebijakan belum ditinjau.', 'status' => 'Open',
        ]);

        $this->get(route('smkp.laporan', $a))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('narasi.domisili', 'Samarinda.')
                ->has('dasarHukum', 4)
                ->has('barisTemuan', 1)
                ->where('barisTemuan.0.nomor', 'UJI-MYR-01')
                ->where('pekerja.total', 125)
                // 6 lembar tetap + 1 lembar temuan + 1 lembar penutup.
                ->where('totalLembar', 8));
    }

    /** Audit tanpa temuan tetap mencetak lembar daftarnya, bukan melompatinya. */
    public function test_tanpa_temuan_lembar_daftar_tetap_ada(): void
    {
        $this->masuk();
        $a = $this->audit();

        $this->get(route('smkp.laporan', $a))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('barisTemuan', 0)
                ->where('totalLembar', 8));
    }

    /* ═══════════ alur ═══════════ */

    public function test_penyusunan_laporan_muncul_sebagai_langkah_alur(): void
    {
        $langkah = collect(\App\Support\SmkpTahap::alur()['pelaporan']['langkah'])->pluck('kunci')->all();

        $this->assertSame('narasi', $langkah[0],
            'Penyusunan mendahului cetak — laporan yang dicetak sebelum disusun keluar tanpa isinya.');
    }
}
