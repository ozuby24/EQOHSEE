<?php

namespace Tests\Feature;

use App\Models\{SmkpAudit, User};
use App\Support\{Smkp, SmkpSampel, SmkpTahap};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Matriks Metode dan Sampel Audit — komponen ke-8 Rencana Audit.
 *
 * Yang diuji di sini dua hal yang keduanya pernah menjadi celah pada
 * berkas audit sungguhan:
 *
 *  1. Komponen ke-8 dulu satu kotak teks bebas. "Wawancara, tinjauan
 *     dokumen, observasi lapangan" memenuhi syarat "lengkap" tanpa
 *     memberi tahu auditor mana pun apa yang harus ia minta hari Senin.
 *
 *  2. Pengecualian ruang lingkup dulu ditetapkan dua kali — sekali di
 *     Rencana Audit sebagai kalimat, sekali lagi butir demi butir pada
 *     formulir penilaian. Yang terlewat pada penetapan kedua tetap ikut
 *     membagi nilai akhir dan menekan skor tanpa sebab.
 */
class SmkpSampelTest extends TestCase
{
    use RefreshDatabase;

    private function masuk(): User
    {
        $u = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($u);
        return $u;
    }

    private function audit(array $atribut = []): SmkpAudit
    {
        return SmkpAudit::create(array_merge(['tahun' => 2026, 'status' => 'draft'], $atribut));
    }

    /* ═══════════ daftar kriteria ═══════════ */

    /**
     * Yang didaftar RINCIANNYA, bukan induknya.
     *
     * Mendaftar keduanya membuat auditor mengisi sampel dua kali untuk hal
     * yang sama — dan formulir penilaian pun hanya menilai yang terdalam,
     * jadi baris induknya tidak pernah dibaca siapa pun.
     */
    public function test_sub_elemen_berincian_tidak_ikut_didaftar(): void
    {
        $kode = SmkpSampel::kodeSah();

        $this->assertContains('II.2.1', $kode, 'Rinciannya didaftar.');
        $this->assertNotContains('II.2', $kode, 'Induknya tidak.');

        $this->assertContains('II.1', $kode, 'Sub-elemen tanpa rincian tetap didaftar.');
    }

    /** Dan jumlahnya sama dengan jumlah butir yang dinilai formulir penilaian. */
    public function test_kriteria_sejajar_dengan_butir_yang_dinilai(): void
    {
        $butir = [];

        foreach (Smkp::elemen() as $e) {
            foreach ($e['sub'] as $s) {
                foreach (Smkp::butirSub($s) as $b) $butir[] = $b['kode'];
            }
        }

        $this->assertSame(
            $butir,
            SmkpSampel::kodeSah(),
            'Kriteria yang direncanakan sampelnya harus sama persis dengan butir yang dinilai; '
            .'selisihnya adalah butir yang dinilai tanpa pernah direncanakan cara pembuktiannya.',
        );
    }

    /* ═══════════ pembersihan kiriman ═══════════ */

    public function test_baris_kosong_dibuang_dan_kode_karangan_ditolak(): void
    {
        $hasil = SmkpSampel::bersihkan([
            'I.1'        => ['dokumen' => ' Dokumen Kebijakan ', 'wawancara' => '', 'observasi' => ''],
            'I.2'        => ['dokumen' => '', 'wawancara' => '', 'observasi' => ''],
            'I.3'        => ['na' => '1', 'ket' => ' tidak ada kapal keruk '],
            'ZZ.9'       => ['dokumen' => 'karangan'],
        ]);

        $this->assertSame(['I.1', 'I.3'], array_keys($hasil));
        $this->assertSame('Dokumen Kebijakan', $hasil['I.1']['dokumen']);
        $this->assertArrayNotHasKey('wawancara', $hasil['I.1'], 'Metode kosong tidak disimpan.');
        $this->assertTrue($hasil['I.3']['na']);
        $this->assertSame('tidak ada kapal keruk', $hasil['I.3']['ket']);
    }

    /* ═══════════ rekap ═══════════ */

    public function test_rekap_menghitung_terisi_dan_tidak_berlaku(): void
    {
        $r = SmkpSampel::rekap([
            'I.1' => ['dokumen' => 'Dokumen Kebijakan'],
            'I.2' => ['na' => true],
            'I.3' => ['dokumen' => '', 'wawancara' => ''],   // disimpan tapi hampa
        ]);

        $this->assertSame(count(SmkpSampel::kodeSah()), $r['total']);
        $this->assertSame(2, $r['terisi'], 'I.3 tidak punya satu pun metode.');
        $this->assertSame(1, $r['na']);
        $this->assertSame($r['total'] - 2, $r['kurang']);
        $this->assertFalse($r['lengkap']);
    }

    /* ═══════════ halaman & simpan ═══════════ */

    public function test_halaman_matriks_membawa_seluruh_kriteria(): void
    {
        $this->masuk();
        $a = $this->audit();

        $this->get(route('smkp.sampel', $a))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Smkp/Sampel')
                ->has('kriteria', count(SmkpSampel::kodeSah()))
                ->where('metode', SmkpSampel::METODE)
                ->has('tautan.simpan'));
    }

    public function test_matriks_tersimpan_dan_dibersihkan(): void
    {
        $this->masuk();
        $a = $this->audit();

        $this->post(route('smkp.sampel.simpan', $a), [
            'sampel' => [
                'I.1' => ['dokumen' => "Dokumen Risk profile\nDokumen Kebijakan", 'wawancara' => '3 orang komite'],
                'I.2' => ['dokumen' => '', 'wawancara' => '', 'observasi' => ''],
                'I.4' => ['na' => '1', 'ket' => 'tidak berlaku'],
            ],
        ])->assertRedirect(route('smkp.sampel', $a));

        $s = $a->fresh()->sampel;

        /* URUTAN ACUAN, bukan urutan kiriman. Validasi Laravel menyusun
           ulang kunci menurut urutan aturannya — yang punya `na` muncul
           lebih dulu — dan urutan itulah yang akan tercetak pada Rencana
           Audit. Tabel yang melompat dari I.4 ke I.1 tidak dapat dibaca
           sebagai daftar acuan. */
        $this->assertSame(['I.1', 'I.4'], array_keys($s));
        $this->assertSame('3 orang komite', $s['I.1']['wawancara']);
        $this->assertSame(['I.4'], $a->fresh()->dikecualikan());
    }

    /**
     * Komponen ke-8 Rencana Audit terpenuhi oleh matriksnya.
     *
     * Sebelumnya hanya kotak teks bebas yang dapat memenuhinya, sehingga
     * rencana yang matriksnya lengkap tetap terbaca kurang satu komponen
     * selama kalimat naratifnya kosong.
     */
    public function test_matriks_memenuhi_komponen_kedelapan(): void
    {
        $this->masuk();
        $a = $this->audit();

        $this->assertFalse($a->rekapRencana()['terisi']['metode']);

        $a->update(['sampel' => ['I.1' => ['dokumen' => 'Dokumen Kebijakan']]]);

        $this->assertTrue($a->fresh()->rekapRencana()['terisi']['metode']);
    }

    /** Dan kalimat naratifnya tetap diterima, bagi audit yang sudah berjalan. */
    public function test_kalimat_naratif_lama_tetap_memenuhi(): void
    {
        $this->masuk();
        $a = $this->audit(['rencana' => ['metode' => 'Wawancara, tinjauan dokumen, observasi.']]);

        $this->assertTrue($a->rekapRencana()['terisi']['metode']);
    }

    /* ═══════════ pengecualian mengalir ke penilaian ═══════════ */

    public function test_penilaian_menandai_butir_yang_dikecualikan(): void
    {
        $this->masuk();
        $a = $this->audit(['sampel' => ['I.4' => ['na' => true, 'ket' => 'tidak ada kapal keruk']]]);

        $this->get(route('smkp.penilaian', $a))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('pengecualian.total', 1)
                ->where('pengecualian.belum', ['I.4'])
                ->where('pengecualian.bentrok', [])
                ->where('pengecualian.terpakai', 0));
    }

    public function test_tombol_menuliskan_pengecualian_sebagai_na(): void
    {
        $this->masuk();
        $a = $this->audit(['sampel' => ['I.4' => ['na' => true, 'ket' => 'tidak ada kapal keruk']]]);

        $this->post(route('smkp.penilaian.kecuali', $a))->assertRedirect(route('smkp.penilaian', $a));

        $h = $a->fresh()->hasil;

        $this->assertSame(Smkp::NA, $h['I.4']['v']);
        $this->assertSame('tidak ada kapal keruk', $h['I.4']['ket'],
            'Alasan pengecualian ikut tertulis, bukan hilang di jalan.');
    }

    /**
     * BUTIR YANG SUDAH BERNILAI ANGKA TIDAK DISENTUH.
     *
     * Pertentangan antara "dikecualikan pada rencana" dan "sudah dinilai 3"
     * adalah pertentangan yang harus diselesaikan orang: mungkin rencananya
     * keliru, mungkin penilaiannya. Mesin yang memilih salah satunya
     * menghapus bukti bahwa pertentangan itu pernah ada — dan mengubah nilai
     * akhir audit tanpa siapa pun memintanya.
     */
    public function test_butir_yang_sudah_bernilai_angka_tidak_ditimpa(): void
    {
        $this->masuk();
        $a = $this->audit([
            'sampel' => ['I.4' => ['na' => true], 'I.5' => ['na' => true]],
            'hasil'  => ['I.4' => ['v' => 3, 'ket' => 'sudah dinilai auditor']],
        ]);

        $this->get(route('smkp.penilaian', $a))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('pengecualian.bentrok', ['I.4'])
                ->where('pengecualian.belum', ['I.5']));

        $this->post(route('smkp.penilaian.kecuali', $a));

        $h = $a->fresh()->hasil;

        $this->assertSame(3, $h['I.4']['v'], 'Nilai auditor tidak boleh ditimpa diam-diam.');
        $this->assertSame('sudah dinilai auditor', $h['I.4']['ket']);
        $this->assertSame(Smkp::NA, $h['I.5']['v'], 'Yang belum dinilai tetap ditandai.');
    }

    /** Menjalankannya dua kali tidak menambah apa pun. */
    public function test_penerapan_kedua_tidak_mengubah_apa_pun(): void
    {
        $this->masuk();
        $a = $this->audit(['sampel' => ['I.4' => ['na' => true]]]);

        $this->post(route('smkp.penilaian.kecuali', $a));
        $sesudah = $a->fresh()->hasil;

        $this->post(route('smkp.penilaian.kecuali', $a));

        $this->assertSame($sesudah, $a->fresh()->hasil);
    }

    /** Dan butir N/A benar-benar keluar dari pembagi nilai. */
    public function test_butir_na_keluar_dari_pembagi(): void
    {
        $this->masuk();
        $a = $this->audit();

        $sebelum = $a->rekap()['elemen']['I']['berlaku'];

        $a->update(['sampel' => ['I.4' => ['na' => true]]]);
        $this->post(route('smkp.penilaian.kecuali', $a));

        $sesudah = $a->fresh()->rekap()['elemen']['I']['berlaku'];

        $this->assertLessThan($sebelum, $sesudah,
            'Butir yang tidak berlaku tidak boleh ikut membagi capaian elemen.');
    }

    /* ═══════════ cetak ═══════════ */

    public function test_rencana_cetak_memuat_lembar_matriks(): void
    {
        $this->masuk();
        $a = $this->audit([
            'sampel' => [
                'I.1' => ['dokumen' => 'Dokumen Kebijakan KPLH', 'wawancara' => '3 orang komite'],
                'I.4' => ['na' => true, 'ket' => 'tidak berlaku'],
            ],
        ]);

        $this->get(route('smkp.rencana.cetak', $a))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('totalLembar', 4)
                ->has('matriks', 2)
                ->where('matriks.0.kode', 'I.1')
                ->has('matriks.0.metode', 2)
                ->where('matriks.1.na', true));
    }

    /** Tanpa matriks, lembarnya tidak dicetak kosong. */
    public function test_tanpa_matriks_lembar_keempat_tidak_ada(): void
    {
        $this->masuk();
        $a = $this->audit();

        $this->get(route('smkp.rencana.cetak', $a))->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('totalLembar', 3)
                ->has('matriks', 0));
    }

    /* ═══════════ alur ═══════════ */

    public function test_matriks_muncul_sebagai_langkah_alur(): void
    {
        $langkah = collect(SmkpTahap::alur()['rencana']['langkah'])->pluck('kunci')->all();

        $this->assertContains('matriks', $langkah);
    }
}
