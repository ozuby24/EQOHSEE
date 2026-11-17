<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SmkpAudit;
use App\Models\User;
use App\Support\Smkp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Kesesuaian data kriteria audit dengan Lampiran II Kepdirjen Minerba
 * 185.K/37.04/DJB/2019: nilai maksimum tiap butir mengikuti kriteria
 * penilaiannya (hal. 412-599), dan nilai skala lama ditandai untuk dinilai ulang.
 */
class SmkpKepdirjenTest extends TestCase
{
    use RefreshDatabase;

    private function butir(string $kode): array
    {
        foreach (Smkp::butir() as $b) if ($b['kode'] === $kode) return $b;
        $this->fail("Butir {$kode} tidak ada.");
    }

    public function test_nilai_maksimum_butir_yang_dikoreksi_sesuai_kepdirjen(): void
    {
        // Masukan auditor: III.2.x bernilai maksimal 2, bukan 4; III.12 disesuaikan.
        // Daftar Koreksi dan Klarifikasi Lampiran II no. 5–12: II.2.2/II.2.3/II.2.5 = 3/2/3,
        // III.12.5 = 3, III.13 = 4, IV.1.2 = 3, IV.3.2 = 2, IV.4.5 = 4.
        $harap = [
            'II.2.2' => 3, 'II.2.3' => 2, 'II.2.5' => 3,
            'III.2.1' => 2, 'III.2.2' => 2, 'III.2.3' => 2,
            'III.12.1' => 4, 'III.12.2' => 3, 'III.12.3' => 3, 'III.12.4' => 3, 'III.12.5' => 3,
            'III.13' => 4, 'IV.1.2' => 3, 'IV.3.2' => 2, 'IV.4.5' => 4, 'IV.5.1' => 2, 'VII.5' => 1,
        ];
        foreach ($harap as $kode => $maks) {
            $this->assertSame($maks, $this->butir($kode)['maks'], "Nilai maksimal {$kode} meleset.");
        }
    }

    public function test_total_nilai_elemen_dan_keseluruhan(): void
    {
        $harap = ['I' => 19, 'II' => 29, 'III' => 68, 'IV' => 138, 'V' => 60, 'VI' => 12, 'VII' => 13];
        foreach (Smkp::elemen() as $e) {
            $this->assertSame($harap[$e['kode']], Smkp::maksElemen($e), "Nilai maksimal elemen {$e['kode']} meleset.");
        }
        $this->assertSame(339, Smkp::totalNilai());
        $this->assertSame(339, array_sum($harap));
        $this->assertSame(100, Smkp::jumlahButir());
    }

    public function test_tiap_butir_punya_kriteria_untuk_setiap_nilai_sampai_maksimum(): void
    {
        // Nilai maksimum = nilai tertinggi yang punya deskripsi; tiap angka 0..maks
        // harus dapat dibuktikan dengan kriteria tertulis.
        foreach (Smkp::butir() as $b) {
            $r = Smkp::rubrik($b['kode']);
            $this->assertNotNull($r, "Butir {$b['kode']} tanpa kriteria penilaian.");
            $this->assertGreaterThanOrEqual(412, $r['hal']);
            $this->assertLessThanOrEqual(599, $r['hal']);

            foreach ($r['varian'] ?? [$r] as $v) {
                $this->assertSame(range(0, $b['maks']), array_column($v['nilai'], 'n'), "Tingkat nilai {$b['kode']} tidak runtut.");
                foreach ($v['nilai'] as $t) {
                    $this->assertGreaterThan(40, mb_strlen($t['teks']), "Deskripsi nilai {$t['n']} butir {$b['kode']} kosong.");
                }
            }
        }
    }

    public function test_butir_dua_versi_untuk_pemegang_iup_dan_iujp(): void
    {
        foreach (['II.5', 'III.3'] as $kode) {
            $this->assertSame(['iup', 'iujp'], array_column(Smkp::rubrik($kode)['varian'], 'untuk'), "{$kode} harus punya versi IUP dan IUJP.");
        }
        // PJO hanya berlaku bagi pemegang IUJP: versi IUP punya kondisi N/A.
        $this->assertNotEmpty(Smkp::rubrik('III.3')['varian'][0]['na']);
        $this->assertNotEmpty(Smkp::rubrik('III.2.2')['na'], 'Kepala Tambang Bawah Tanah dapat N/A.');
    }

    public function test_rujukan_halaman_kriteria_menunjuk_halaman_awal_butir(): void
    {
        // Nomor "-NNN-" pada sumber adalah kepala halaman: III.2.1 mulai di hal. 430,
        // II.2.1 di hal. 419, VII.6 di hal. 599 (hal. 600 sudah format laporan).
        $this->assertSame(430, Smkp::rubrik('III.2.1')['hal']);
        $this->assertSame(419, Smkp::rubrik('II.2.1')['hal']);
        $this->assertSame(599, Smkp::rubrik('VII.6')['hal']);
        // Versi IUP butir PJO tidak berlaku bagi IPR (berbeda dengan II.5).
        $this->assertStringNotContainsString('IPR', Smkp::rubrik('III.3')['varian'][0]['label']);
        $this->assertStringContainsString('IPR', Smkp::rubrik('II.5')['varian'][0]['label']);
    }

    private function subIII2(): array
    {
        return collect(collect(Smkp::elemen())->firstWhere('kode', 'III')['sub'])->firstWhere('kode', 'III.2');
    }

    public function test_nilai_skala_lama_disetarakan_tanpa_mengubah_kategori(): void
    {
        // Skala lama 0-4: III.2 = (3+2)/8 = 62,5% → minor. Setelah maksimum menjadi 2,
        // nilai yang sama disetarakan 1,5 dan 1 dari 2 — capaian tetap 62,5%.
        $h = [
            'III.2.1' => ['v' => 3, 'maks_lama' => 4],
            'III.2.2' => ['v' => 2, 'maks_lama' => 4],
            'III.2.3' => ['v' => 'N/A'],
        ];
        $r = Smkp::rekapSub($this->subIII2(), $h);
        $this->assertSame(2.5, $r['nilai']);
        $this->assertSame(4, $r['maks']);
        $this->assertSame(0.625, $r['capaian']);
        $this->assertSame('minor', $r['kategori']['kode'], 'Temuan minor tidak boleh hilang hanya karena maksimumnya dikoreksi.');
        $this->assertSame(1.5, $r['butir']['III.2.1'], 'Formulir cetak menampilkan nilai setaranya.');

        $u = Smkp::perluDinilaiUlang($h);
        $this->assertSame(['III.2.1', 'III.2.2'], array_keys($u));
        $this->assertSame(['v' => 3.0, 'maks' => 2, 'maks_lama' => 4, 'koreksi' => null, 'setara' => 1.5, 'elemen' => 'III'], $u['III.2.1']);
    }

    public function test_nilai_di_atas_maksimum_tanpa_penanda_dijepit_dan_ditandai(): void
    {
        $h = ['III.2.1' => ['v' => 4], 'III.2.2' => ['v' => 'N/A'], 'III.2.3' => ['v' => 'N/A']];
        $r = Smkp::rekapSub($this->subIII2(), $h);
        $this->assertSame(2.0, $r['nilai']);
        $this->assertSame(['III.2.1' => ['v' => 4.0, 'maks' => 2, 'maks_lama' => null, 'koreksi' => null, 'setara' => 2.0, 'elemen' => 'III']], Smkp::perluDinilaiUlang($h));
    }

    public function test_iii13_bernilai_maksimal_empat_dengan_lima_deskripsi(): void
    {
        $r = Smkp::rubrik('III.13');
        $this->assertSame([0, 1, 2, 3, 4], array_column($r['nilai'], 'n'));
        $this->assertStringContainsString('menjadi masukan dalam peningkatan penerapan', $r['nilai'][4]['teks']);
        $this->assertStringNotContainsString('menjadi masukan dalam peningkatan', $r['nilai'][3]['teks']);
    }

    public function test_teks_kriteria_mengikuti_daftar_koreksi(): void
    {
        $teks = fn (string $kode, int $n) => collect(Smkp::rubrik($kode)['nilai'])->firstWhere('n', $n)['teks'];

        // No. 1: Nilai 4 III.12.1 butir (2) "telah memastikan"; Nilai 3 tetap "belum".
        $this->assertStringContainsString('(2) KTT atau PTL telah memastikan bahwa Pekerja', $teks('III.12.1', 4));
        $this->assertStringContainsString('(2) KTT atau PTL belum memastikan bahwa Pekerja', $teks('III.12.1', 3));
        // No. 2: Nilai 3 III.12.3 butir (3) "telah memenuhi tata waktu"; Nilai 2 tetap "belum".
        $this->assertStringContainsString('(3) penyampaian laporan telah memenuhi tata waktu', $teks('III.12.3', 3));
        $this->assertStringContainsString('(3) penyampaian laporan belum memenuhi tata waktu', $teks('III.12.3', 2));
        // No. 4: Nilai 4 IV.3.10 butir (3) "sesuai batas waktu pelaporan".
        $this->assertStringContainsString('dan sesuai batas waktu pelaporan yang ditetapkan', $teks('IV.3.10', 4));
        $this->assertStringNotContainsString('belum pada batas waktu', $teks('IV.3.10', 4));

        // Setelah koreksi, tidak ada dua tingkat nilai dengan deskripsi yang sama persis.
        foreach (Smkp::butir() as $b) {
            foreach (Smkp::rubrik($b['kode'])['varian'] ?? [Smkp::rubrik($b['kode'])] as $v) {
                $t = array_column($v['nilai'], 'teks');
                $this->assertSame(count($t), count(array_unique($t)), "Deskripsi tingkat {$b['kode']} kembar.");
            }
        }
    }

    public function test_penanda_koreksi_bertahan_sampai_auditor_memilih_nilai(): void
    {
        [$u, $a] = $this->auditUji();
        $a->update(['hasil' => ['III.12.1' => ['v' => 4, 'koreksi' => 1, 'ket' => '', 'bukti' => '']]]);

        // Hanya catatan yang berubah: penanda tetap.
        $this->actingAs($u)->post("/smkp/{$a->id}/elemen/III", ['k' => [
            'III.12.1' => ['v' => '4', 'ket' => 'cek pemahaman pekerja', 'bukti' => ''],
        ]])->assertRedirect();
        $this->assertSame(['v' => 4, 'koreksi' => 1, 'ket' => 'cek pemahaman pekerja', 'bukti' => ''], $a->fresh()->hasil['III.12.1']);

        // Auditor memilih nilai (meski sama): penanda dicabut.
        $this->actingAs($u)->post("/smkp/{$a->id}/elemen/III", ['k' => [
            'III.12.1' => ['v' => '4', 'ket' => 'cek pemahaman pekerja', 'bukti' => '', 'ulang' => true],
        ]])->assertRedirect();
        $this->assertSame(['v' => 4, 'ket' => 'cek pemahaman pekerja', 'bukti' => ''], $a->fresh()->hasil['III.12.1']);
        $this->assertSame([], Smkp::perluDinilaiUlang($a->fresh()->hasil));
    }

    private function auditUji(string $kategori = 'induk'): array
    {
        $c = Company::create(['code' => 'K'.strtoupper(substr($kategori, 0, 2)), 'name' => 'PT Uji '.$kategori, 'kategori' => $kategori, 'active' => true]);
        $u = User::factory()->create(['audit_role' => 'auditor', 'company_id' => $c->id, 'active' => true]);
        $a = SmkpAudit::create([
            'company_id' => $c->id, 'judul' => 'Audit Uji', 'tahun' => 2026, 'status' => 'berjalan',
            'hasil' => [
                'III.2.1' => ['v' => 4, 'ket' => '', 'bukti' => 'SK KTT'],
                'III.2.2' => ['v' => 2, 'maks_lama' => 4, 'ket' => '', 'bukti' => ''],
                'III.2.3' => ['v' => 'N/A', 'ket' => '', 'bukti' => ''],
            ],
        ]);
        return [$u, $a];
    }

    public function test_halaman_penilaian_dan_ringkasan_menandai_butir_yang_perlu_dinilai_ulang(): void
    {
        [$u, $a] = $this->auditUji();

        $this->actingAs($u)->get("/smkp/{$a->id}/elemen/III")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('sub.1.kode', 'III.2')
            ->where('sub.1.butir.0.maks', 2)
            ->where('sub.1.butir.0.v', '4')
            ->where('sub.1.butir.0.ulang.maks_lama', null)
            ->where('sub.1.butir.1.ulang.maks_lama', 4)
            ->where('sub.1.butir.1.ulang.setara', 1)
            ->where('sub.1.butir.2.ulang', null)
            ->where('sub.1.butir.0.rubrik.hal', 430)
            ->has('sub.1.butir.0.rubrik.nilai', 3)
            ->where('semua.2.ulang', 2)
            ->where('audit.perusahaan.kodeKategori', 'induk'));

        $this->actingAs($u)->get("/smkp/{$a->id}")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('ulang.0.kode', 'III.2.1')
            ->where('ulang.1.kode', 'III.2.2')
            ->where('ulang.1.maks_lama', 4)
            ->where('ulang.0.elemen', 'III')
            ->where('elemen.2.ulang', 2));
    }

    public function test_mengubah_catatan_saja_tidak_menghapus_tanda_nilai_ulang(): void
    {
        [$u, $a] = $this->auditUji();

        // Formulir mengirim seluruh baris yang berubah, termasuk nilai lama yang masih tampil.
        $this->actingAs($u)->post("/smkp/{$a->id}/elemen/III", ['k' => [
            'III.2.1' => ['v' => '4', 'ket' => 'catatan baru', 'bukti' => 'SK KTT'],
            'III.2.2' => ['v' => '2', 'ket' => 'cek SK KTBT', 'bukti' => '', 'ulang' => false],
        ]])->assertRedirect();

        $h = $a->fresh()->hasil;
        $this->assertSame(4, $h['III.2.1']['v'], 'Nilai lama tidak boleh tersimpan diam-diam sebagai nilai penuh.');
        $this->assertSame('catatan baru', $h['III.2.1']['ket']);
        $this->assertSame(['v' => 2, 'maks_lama' => 4, 'ket' => 'cek SK KTBT', 'bukti' => ''], $h['III.2.2']);
        $this->assertSame(['III.2.1', 'III.2.2'], array_keys(Smkp::perluDinilaiUlang($h)));
    }

    public function test_memilih_nilai_menilai_ulang_meski_angkanya_sama(): void
    {
        [$u, $a] = $this->auditUji();

        $this->actingAs($u)->post("/smkp/{$a->id}/elemen/III", ['k' => [
            'III.2.1' => ['v' => '1', 'ket' => '', 'bukti' => 'SK KTT', 'ulang' => true],
            // Auditor sengaja memilih 2 pada skala baru — sama dengan angka lamanya.
            'III.2.2' => ['v' => '2', 'ket' => '', 'bukti' => '', 'ulang' => true],
        ]])->assertRedirect();

        $h = $a->fresh()->hasil;
        $this->assertSame(['v' => 1, 'ket' => '', 'bukti' => 'SK KTT'], $h['III.2.1']);
        $this->assertSame(['v' => 2, 'ket' => '', 'bukti' => ''], $h['III.2.2']);
        $this->assertSame([], Smkp::perluDinilaiUlang($h));
    }

    public function test_auditi_pjp_memakai_versi_iujp(): void
    {
        [$u, $a] = $this->auditUji('pjp');

        $this->actingAs($u)->get("/smkp/{$a->id}/elemen/III")->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('audit.perusahaan.kodeKategori', 'pjp')
            ->where('audit.perusahaan.kategori', fn ($v) => $v !== 'pjp'));
    }

    public function test_acuan_menyertakan_kriteria_dan_catatan_selisih(): void
    {
        $u = User::factory()->create(['is_admin' => true, 'active' => true]);

        $this->actingAs($u)->get('/smkp/acuan')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('ringkas.maks', 339)
            ->where('ringkas.butir', 100)
            ->has('meta.selisih', 8)
            ->where('elemen.2.sub.1.butir.0.kode', 'III.2.1')
            ->has('elemen.2.sub.1.butir.0.rubrik.nilai', 3));
    }
}
