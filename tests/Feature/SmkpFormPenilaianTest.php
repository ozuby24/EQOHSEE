<?php

namespace Tests\Feature;

use App\Models\{Company, SmkpAudit, User};
use App\Support\{Smkp, SmkpRubrik};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Form Penilaian Audit — seluruh kriteria dalam satu lembar.
 *
 * Yang dijaga di sini bukan tampilannya melainkan PEMBEDAAN yang
 * menjadi alasan lembar ini dibuat: belum dinilai, dinilai nol, dan
 * tidak berlaku adalah tiga keadaan berbeda. Menyamakan salah satu
 * pasangannya membuat seluruh audit terbaca keliru — audit yang baru
 * dibuka tampak gagal, atau butir yang gagal tampak belum diperiksa.
 */
class SmkpFormPenilaianTest extends TestCase
{
    use RefreshDatabase;

    private SmkpAudit $audit;

    protected function setUp(): void
    {
        parent::setUp();

        $c = Company::create(['name' => 'PT Uji Penilaian', 'doc_no_prefix' => 'UP']);

        $this->actingAs(User::factory()->create([
            'is_admin' => true, 'company_id' => $c->id, 'email_verified_at' => now(),
        ]));

        $this->audit = SmkpAudit::create([
            'company_id' => $c->id, 'tahun' => 2026, 'status' => 'draft',
            'judul' => 'Audit Internal SMKP 2026', 'ketua_auditor' => 'Ir. Bambang',
            'hasil' => [],
        ]);
    }

    private function props(): array
    {
        return $this->get(route('smkp.penilaian', $this->audit))
            ->assertOk()->viewData('page')['props'];
    }

    /** Butir pertama sebuah elemen beserta nilai maksimumnya. */
    private function butirPertama(string $elemen): array
    {
        foreach (Smkp::butir() as $b) {
            if ($b['elemen'] === $elemen) return $b;
        }

        $this->fail("Elemen {$elemen} tidak punya butir.");
    }

    /* ═══════════ jangkauan ═══════════ */

    public function test_formulir_memuat_seluruh_butir_tujuh_elemen(): void
    {
        $props = $this->props();

        $this->assertCount(count(Smkp::elemen()), $props['elemen']);

        $jumlah = 0;
        foreach ($props['elemen'] as $e) {
            foreach ($e['sub'] as $s) $jumlah += count($s['butir']);
        }

        /* Butir yang hilang dari lembar tidak dapat dibedakan antara
           "tidak berlaku" dan "terlewat dinilai" — dan pembedaan itu
           justru yang dicari orang yang membuka halaman ini. */
        $this->assertSame(Smkp::jumlahButir(), $jumlah,
            'Form penilaian tidak memuat seluruh butir kriteria.');
    }

    public function test_pintasan_menu_menyalurkan_ke_audit_berjalan(): void
    {
        /* Menu samping tidak tahu periode mana yang sedang dikerjakan;
           tanpa penyalur ini butirnya hanya dapat dicapai dari halaman
           ringkasan, dan itu berarti tidak dapat dicapai dari mana pun. */
        $this->get(route('smkp.ke.penilaian'))
            ->assertRedirect(route('smkp.penilaian', $this->audit));
    }

    public function test_butir_penilaian_ada_di_bilah_samping_setelah_rencana_audit(): void
    {
        $tahap = collect(\App\Support\Menu::modul('smkp')['groups']['Tahap Audit'])
            ->pluck(1)->values()->all();

        $this->assertSame(
            ['smkp.ke.tahap1', 'smkp.ke.rencana', 'smkp.ke.penilaian', 'smkp.ke.rapat', 'smkp.ke.temuan'],
            $tahap,
            'Form Penilaian harus berdiri sesudah Permulaan Audit dan Rencana Audit.'
        );
    }

    /* ═══════════ tiga keadaan yang tidak boleh disamakan ═══════════ */

    public function test_butir_belum_dinilai_bukan_ketidaksesuaian(): void
    {
        $props = $this->props();

        $keadaan = [];
        foreach ($props['elemen'] as $e) {
            foreach ($e['sub'] as $s) {
                foreach ($s['butir'] as $b) $keadaan[$b['keadaan']] = true;
            }
        }

        /* Audit yang baru dibuka belum diperiksa sama sekali. Bila
           "belum dinilai" dihitung sebagai capaian nol, seluruh lembar
           menyala merah dan angka mayornya masuk ke rekapitulasi
           sebagai temuan yang tidak pernah ada. */
        $this->assertSame(['belum' => true], $keadaan,
            'Audit kosong tidak boleh menampilkan butir sebagai ketidaksesuaian.');

        $this->assertSame(Smkp::jumlahButir(), $props['ringkas']['belum']);
        $this->assertSame(0, $props['ringkas']['mayor']);
    }

    public function test_nilai_nol_terbaca_mayor_bukan_belum_dinilai(): void
    {
        $b = $this->butirPertama('I');
        $this->audit->update(['hasil' => [$b['kode'] => ['v' => 0]]]);

        $props = $this->props();

        $this->assertSame('mayor', $this->cariButir($props, $b['kode'])['keadaan'],
            'Nol berarti sudah diperiksa dan tidak memenuhi — bukan belum diperiksa.');
        $this->assertSame(1, $props['ringkas']['mayor']);
        $this->assertSame(Smkp::jumlahButir() - 1, $props['ringkas']['belum']);
    }

    public function test_butir_tidak_berlaku_keluar_dari_hitungan_kesesuaian(): void
    {
        $b = $this->butirPertama('I');
        $this->audit->update(['hasil' => [$b['kode'] => ['v' => Smkp::NA]]]);

        $props = $this->props();

        $this->assertSame('na', $this->cariButir($props, $b['kode'])['keadaan']);
        $this->assertSame(1, $props['ringkas']['na']);
        $this->assertSame(0, $props['ringkas']['mayor'],
            'Butir di luar lingkup tidak boleh dihitung sebagai ketidaksesuaian.');
    }

    public function test_nilai_penuh_terbaca_kesesuaian_dan_separuh_terbaca_minor(): void
    {
        $b = $this->butirPertama('I');                       // maks 4
        $this->audit->update(['hasil' => [$b['kode'] => ['v' => $b['maks']]]]);
        $this->assertSame('kesesuaian', $this->cariButir($this->props(), $b['kode'])['keadaan']);

        $this->audit->update(['hasil' => [$b['kode'] => ['v' => (int) ceil($b['maks'] / 2)]]]);
        $this->assertSame('minor', $this->cariButir($this->props(), $b['kode'])['keadaan'],
            'Capaian 50% s.d. kurang dari 100% adalah ketidaksesuaian minor.');
    }

    /* ═══════════ kategori sub-elemen ═══════════ */

    public function test_sub_elemen_belum_dinilai_tidak_dilabeli_mayor(): void
    {
        foreach ($this->props()['elemen'] as $e) {
            foreach ($e['sub'] as $s) {
                /* Sebelum ada yang dinilai, capaian sub-elemen selalu nol
                   — bukan karena gagal melainkan karena pembilangnya
                   masih kosong. Melabelinya "Mayor" mengarang temuan. */
                $this->assertNull($s['kategori'],
                    "Sub-elemen {$s['kode']} dilabeli kategori padahal belum dinilai.");
            }
        }
    }

    public function test_kategori_sub_elemen_muncul_setelah_ada_yang_dinilai(): void
    {
        $b = $this->butirPertama('I');
        $this->audit->update(['hasil' => [$b['kode'] => ['v' => $b['maks']]]]);

        $sub = collect($this->props()['elemen'])
            ->firstWhere('kode', 'I')['sub'];

        $this->assertNotNull(
            collect($sub)->firstWhere('kode', $b['sub'])['kategori'],
            'Sub-elemen yang sudah dinilai harus membawa kategorinya.'
        );
    }

    /* ═══════════ ambang dikirim, bukan disalin ═══════════ */

    public function test_ambang_keadaan_bersumber_dari_berkas_acuan(): void
    {
        $keadaan = collect($this->props()['keadaan'])->keyBy('kode');

        foreach (Smkp::kategori() as $k) {
            $this->assertSame((float) $k['min'], $keadaan[$k['kode']]['min'],
                "Ambang {$k['kode']} di layar berbeda dari berkas acuan.");
            $this->assertSame($k['warna'], $keadaan[$k['kode']]['warna']);
        }

        /* Belum dinilai dan tidak berlaku bukan kategori bernilai —
           keduanya harus tak berambang, supaya penyaring di peramban
           tidak pernah mencocokkan angka dengannya. */
        $this->assertNull($keadaan['belum']['min']);
        $this->assertNull($keadaan['na']['min']);
    }

    /* ═══════════ rubrik penilaian ═══════════ */

    public function test_setiap_butir_punya_bunyi_rubrik_dari_lampiran(): void
    {
        $kosong = [];

        foreach (Smkp::butir() as $b) {
            if (!SmkpRubrik::ada($b['kode'])) $kosong[] = $b['kode'];
        }

        /* Tanpa rubrik, angka 0..maks ditafsirkan sendiri oleh tiap
           auditor — dan dua auditor yang memeriksa bukti yang sama lalu
           memberi 2 dan 4 menghasilkan tingkat penerapan berbeda untuk
           perusahaan yang sama. */
        $this->assertSame([], $kosong,
            'Butir tanpa bunyi rubrik: '.implode(', ', $kosong));
    }

    public function test_tangga_rubrik_berhenti_di_nilai_maksimum_butir(): void
    {
        foreach (Smkp::butir() as $b) {
            $t = SmkpRubrik::tangga($b['kode'], (int) $b['maks']);

            $this->assertSame(
                range((int) $b['maks'], 0),
                array_column($t, 'nilai'),
                "Tangga butir {$b['kode']} tidak turun dari maksimum ke nol."
            );
        }
    }

    public function test_enam_butir_yang_rubriknya_tidak_lengkap_tetap_bernilai_maksimum_penuh(): void
    {
        foreach (SmkpRubrik::TANGGA_TAK_LENGKAP as $kode) {
            $b = collect(Smkp::butir())->firstWhere('kode', $kode);
            $this->assertNotNull($b, "Butir {$kode} tidak ada pada acuan.");

            $t = collect(SmkpRubrik::tangga($kode, (int) $b['maks']));

            /* Lampiran tidak berbunyi sampai puncak tangganya. Yang TIDAK
               boleh terjadi: nilai maksimum diturunkan agar rapi. Itu
               mengubah pembagi, dan dengan itu mengubah skor akhir yang
               dilaporkan ke inspektur tambang. */
            $this->assertTrue($t->contains(fn ($a) => !$a['ada']),
                "Butir {$kode} seharusnya punya tingkat tanpa bunyi rubrik.");
            $this->assertSame((int) $b['maks'], $t->max('nilai'),
                "Nilai maksimum butir {$kode} tidak boleh diturunkan mengikuti rubriknya.");
        }

        $this->assertSame(
            Smkp::jumlahButir() - count(SmkpRubrik::TANGGA_TAK_LENGKAP),
            SmkpRubrik::jumlahLengkap()
        );
    }

    public function test_tiap_anak_tangga_membawa_kategori_temuannya(): void
    {
        $t = collect(SmkpRubrik::tangga('I.1', 4))->keyBy('nilai');

        /* Akibat sebuah angka terhadap kategori adalah bagian dari
           keputusan memilih angka itu. Ambangnya dari berkas acuan, bukan
           ditulis ulang di sini. */
        $this->assertSame('kesesuaian', $t[4]['kategori']['kode']);   // 100%
        $this->assertSame('minor',      $t[3]['kategori']['kode']);   // 75%
        $this->assertSame('minor',      $t[2]['kategori']['kode']);   // 50%
        $this->assertSame('mayor',      $t[1]['kategori']['kode']);   // 25%
        $this->assertSame('mayor',      $t[0]['kategori']['kode']);   // 0%
    }

    public function test_bunyi_rubrik_diambil_terpisah_dan_hanya_yang_diminta(): void
    {
        $isi = $this->getJson(route('smkp.rubrik', ['butir' => 'I.1,I.2,BUKAN.ADA']))
            ->assertOk()->json('tangga');

        $this->assertSame(['I.1', 'I.2'], array_keys($isi),
            'Kode yang tidak ada pada acuan tidak boleh dilayani.');
        $this->assertNotSame('', $isi['I.1'][0]['ket']);
    }

    public function test_halaman_penilaian_tidak_ikut_membawa_bunyi_rubrik(): void
    {
        $props = $this->props();

        $this->assertSame(
            ['skala', 'sumber', 'lengkap', 'total', 'alamat'],
            array_keys($props['rubrik'])
        );

        /* Seluruh bunyi rubrik 260 ribu aksara. Ikut pada tiap pembukaan
           halaman, ia menggandakan berat muatan demi teks yang paling
           banyak dibaca beberapa butir. */
        $this->assertLessThan(
            60_000,
            strlen(json_encode($props)),
            'Muatan halaman penilaian membengkak — bunyi rubrik ikut terkirim.'
        );
    }

    /* ═══════════ prasyarat: terlihat, bukan mengunci ═══════════ */

    public function test_prasyarat_ditampilkan_tanpa_menutup_formulir(): void
    {
        $props = $this->props();

        $this->assertSame(['berita', 'rencana-cetak'], collect($props['prasyarat'])->pluck('kunci')->all());

        foreach ($props['prasyarat'] as $p) {
            $this->assertFalse($p['selesai'], 'Audit kosong belum menyelesaikan prasyarat apa pun.');
        }

        /* Halamannya tetap terbuka. Auditor lazim membaca kriteria lebih
           dulu untuk menyiapkan sampel, dan mengunci lembar ini sampai
           dua berkas selesai justru menghalangi pekerjaan yang sah. */
        $this->assertNotEmpty($props['elemen']);
    }

    /* ═══════════ penyimpanan ═══════════ */

    public function test_menyimpan_seluruh_elemen_sekaligus(): void
    {
        $a = $this->butirPertama('I');
        $b = $this->butirPertama('VII');

        $this->post(route('smkp.penilaian.simpan', $this->audit), ['k' => [
            $a['kode'] => ['v' => (string) $a['maks'], 'ket' => 'Kebijakan ditandatangani KTT', 'bukti' => 'Dok-01'],
            $b['kode'] => ['v' => '0', 'ket' => '', 'bukti' => ''],
        ]])->assertRedirect(route('smkp.penilaian', $this->audit));

        $hasil = $this->audit->fresh()->hasil;

        $this->assertSame($a['maks'], $hasil[$a['kode']]['v']);
        $this->assertSame('Kebijakan ditandatangani KTT', $hasil[$a['kode']]['ket']);
        $this->assertSame(0, $hasil[$b['kode']]['v'],
            'Nol harus tersimpan sebagai nilai, bukan terhapus seperti isian kosong.');
    }

    public function test_nilai_di_luar_rentang_dijepit_ke_maksimum_butir(): void
    {
        $b = $this->butirPertama('I');

        $this->post(route('smkp.penilaian.simpan', $this->audit), [
            'k' => [$b['kode'] => ['v' => '999']],
        ]);

        /* Formulir yang dikirim langsung tidak boleh menaikkan capaian
           melebihi maksimum butirnya — skor akhir dipakai menentukan
           tingkat penerapan yang dilaporkan ke inspektur tambang. */
        $this->assertSame($b['maks'], $this->audit->fresh()->hasil[$b['kode']]['v']);
    }

    public function test_isian_dikosongkan_mengembalikan_butir_ke_belum_dinilai(): void
    {
        $b = $this->butirPertama('I');
        $this->audit->update(['hasil' => [$b['kode'] => ['v' => 2, 'ket' => 'lama']]]);

        $this->post(route('smkp.penilaian.simpan', $this->audit), [
            'k' => [$b['kode'] => ['v' => '', 'ket' => '', 'bukti' => '']],
        ]);

        $this->assertArrayNotHasKey($b['kode'], $this->audit->fresh()->hasil,
            'Butir yang dikosongkan harus hilang dari hasil, bukan tersimpan sebagai nol.');
    }

    public function test_formulir_per_elemen_tidak_menghapus_nilai_elemen_lain(): void
    {
        $a = $this->butirPertama('I');
        $b = $this->butirPertama('VII');

        $this->audit->update(['hasil' => [
            $a['kode'] => ['v' => 2], $b['kode'] => ['v' => 2],
        ]]);

        /* Kiriman elemen I membawa kode elemen VII sekaligus — bisa
           karena formulir satu lembar dibuka di tab lain, bisa karena
           kiriman yang dirakit sendiri. Batas elemen harus ditegakkan
           di server: tanpa itu, menyimpan satu elemen diam-diam
           mengosongkan penilaian elemen lain yang sudah selesai. */
        $this->post(route('smkp.nilai.simpan', [$this->audit, 'I']), ['k' => [
            $a['kode'] => ['v' => '3'],
            $b['kode'] => ['v' => ''],
        ]]);

        $hasil = $this->audit->fresh()->hasil;

        $this->assertSame(3, $hasil[$a['kode']]['v']);
        $this->assertSame(2, $hasil[$b['kode']]['v'],
            'Menyimpan elemen I tidak boleh menyentuh butir elemen VII.');
    }

    public function test_menyimpan_menaikkan_status_audit_dari_draf(): void
    {
        $b = $this->butirPertama('I');

        $this->assertSame('draft', $this->audit->status);

        $this->post(route('smkp.penilaian.simpan', $this->audit), [
            'k' => [$b['kode'] => ['v' => '1']],
        ]);

        $this->assertSame('berjalan', $this->audit->fresh()->status);
    }

    /* ═══════════ bantuan ═══════════ */

    private function cariButir(array $props, string $kode): array
    {
        foreach ($props['elemen'] as $e) {
            foreach ($e['sub'] as $s) {
                foreach ($s['butir'] as $b) {
                    if ($b['kode'] === $kode) return $b;
                }
            }
        }

        $this->fail("Butir {$kode} tidak ada di formulir.");
    }
}
