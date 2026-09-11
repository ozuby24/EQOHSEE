<?php

namespace Tests\Feature;

use App\Models\{Company, SmkpAudit, SmkpFinding, User};
use App\Support\{Smkp, SmkpBanding};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Membandingkan audit SMKP antar tahun.
 *
 * Yang dijaga di sini bukan "apakah selisihnya benar" melainkan
 * pembedaan yang paling mudah hilang saat kodenya disederhanakan —
 * dan ketiganya berakhir pada angka yang dibaca rapat tinjauan
 * manajemen sebagai bukti membaik atau memburuk:
 *
 *   capaian bukan poin      pembagi berubah antar tahun
 *   belum dinilai bukan nol audit setengah jalan bukan audit anjlok
 *   tetap rendah bukan tetap sub-elemen yang rusak dua tahun berturut
 */
class SmkpBandingTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $u;

    protected function setUp(): void
    {
        parent::setUp();

        $this->c = Company::create(['name' => 'PT Uji Banding']);
        $this->u = User::factory()->create([
            'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);
    }

    private function audit(int $tahun, array $hasil = [], ?Company $c = null): SmkpAudit
    {
        return SmkpAudit::withoutGlobalScopes()->create([
            'company_id' => ($c ?? $this->c)->id,
            'tahun' => $tahun, 'status' => 'selesai', 'hasil' => $hasil,
        ]);
    }

    /** Sub-elemen berincian pertama pada acuan, beserta elemennya. */
    private function subBerincian(): array
    {
        foreach (Smkp::elemen() as $e) {
            foreach ($e['sub'] as $s) {
                if (! empty($s['subsub'])) return [$e, $s];
            }
        }

        $this->fail('Tidak ada sub-elemen berincian pada acuan.');
    }

    /** @param  callable(array):int|string  $nilai */
    private function isi(array $sub, callable $nilai): array
    {
        $hasil = [];
        foreach (Smkp::butirSub($sub) as $b) $hasil[$b['kode']] = ['v' => $nilai($b)];

        return $hasil;
    }

    /* ═══════════ pembanding ═══════════ */

    #[Test]
    public function pembanding_adalah_tahun_terbesar_yang_lebih_kecil(): void
    {
        /* Yang dicari bukan `tahun - 1`. Perusahaan yang melewatkan satu
           periode tetap punya pembanding, dan menuntut tahun persis
           sebelumnya membuat pembandingnya hilang tanpa satu pun tanda
           bahwa sesungguhnya ada. */
        $this->audit(2022);
        $lalu = $this->audit(2023);
        $kini = $this->audit(2026);

        $this->assertSame($lalu->id, SmkpBanding::sebelumnya($kini)?->id);
    }

    #[Test]
    public function pembanding_tidak_pernah_menyeberang_perusahaan(): void
    {
        $lain = Company::create(['name' => 'PT Lain']);
        $this->audit(2025, [], $lain);

        $kini = $this->audit(2026);

        $this->assertNull(SmkpBanding::sebelumnya($kini),
            'Audit perusahaan lain dipakai sebagai pembanding — grafiknya akan menggambar dua perusahaan sebagai satu garis.');
    }

    #[Test]
    public function audit_pertama_tidak_punya_pembanding(): void
    {
        $b = SmkpBanding::untuk($this->audit(2026));

        $this->assertFalse($b['ada']);
        $this->assertNull($b['tahun']);
        $this->assertNull($b['akhir']['selisih']);
        $this->assertSame([], SmkpBanding::konsistensi($b)['turun']);
    }

    /* ═══════════ selisih ═══════════ */

    #[Test]
    public function yang_dibandingkan_capaian_bukan_poin(): void
    {
        /* Butir yang tahun lalu N/A menjadi berlaku tahun ini — tambang
           membuka bagian bawah tanah. Poin mutlaknya NAIK sementara
           capaiannya TETAP; membandingkan poin melaporkan perbaikan
           yang sesungguhnya perluasan lingkup. */
        [, $sub] = $this->subBerincian();
        $butir   = Smkp::butirSub($sub);

        $lalu = $this->isi($sub, fn ($b) => (int) $b['maks']);
        $lalu[$butir[0]['kode']] = ['v' => Smkp::NA];

        $kini = $this->isi($sub, fn ($b) => (int) $b['maks']);

        $this->audit(2025, $lalu);
        $b = SmkpBanding::untuk($this->audit(2026, $kini));

        $this->assertSame(100.0, $b['sub'][$sub['kode']]['lalu']);
        $this->assertSame(100.0, $b['sub'][$sub['kode']]['kini']);
        $this->assertSame(0.0, $b['sub'][$sub['kode']]['selisih'],
            'Perluasan lingkup terbaca sebagai perubahan capaian.');
    }

    #[Test]
    public function butir_yang_belum_dinilai_tahun_ini_tidak_terbaca_sebagai_penurunan(): void
    {
        /* Audit yang baru berjalan sepertiga akan tampak anjlok bila
           "belum dinilai" disamakan dengan nol. Arahnya harus null —
           tak dapat dibandingkan — bukan 'turun'. */
        [, $sub] = $this->subBerincian();
        $butir   = Smkp::butirSub($sub);

        $this->audit(2025, $this->isi($sub, fn ($b) => (int) $b['maks']));
        $b = SmkpBanding::untuk($this->audit(2026, []));

        $this->assertNull($b['butir'][$butir[0]['kode']]['arah']);
        $this->assertNull($b['butir'][$butir[0]['kode']]['selisih']);
    }

    #[Test]
    public function arah_butir_dibaca_naik_turun_dan_tetap(): void
    {
        [, $sub] = $this->subBerincian();
        $butir   = Smkp::butirSub($sub);

        $this->audit(2025, [
            $butir[0]['kode'] => ['v' => 1],
            $butir[1]['kode'] => ['v' => (int) $butir[1]['maks']],
            $butir[2]['kode'] => ['v' => 1],
        ]);

        $b = SmkpBanding::untuk($this->audit(2026, [
            $butir[0]['kode'] => ['v' => (int) $butir[0]['maks']],
            $butir[1]['kode'] => ['v' => 0],
            $butir[2]['kode'] => ['v' => 1],
        ]));

        $this->assertSame('naik',  $b['butir'][$butir[0]['kode']]['arah']);
        $this->assertSame('turun', $b['butir'][$butir[1]['kode']]['arah']);
        $this->assertSame('tetap', $b['butir'][$butir[2]['kode']]['arah']);
    }

    /* ═══════════ konsistensi ═══════════ */

    #[Test]
    public function bertahan_rendah_dipisahkan_dari_bertahan_baik(): void
    {
        /* Keduanya "tidak berubah", dan menyamakannya di layar membuat
           yang rusak tidak pernah terlihat: selisihnya nol, jadi setiap
           tampilan yang mengurutkan menurut perubahan menaruhnya di
           tengah. */
        $elemen = Smkp::elemen();

        $buruk = null; $baik = null;
        foreach ($elemen as $e) {
            foreach ($e['sub'] as $s) {
                if ($buruk === null) { $buruk = $s; continue; }
                if ($baik === null)  { $baik  = $s; break 2; }
            }
        }

        $isi = fn (array $s, int|string $v) => $this->isi($s, fn ($b) => $v === 'maks' ? (int) $b['maks'] : $v);

        $hasil = $isi($buruk, 0) + $isi($baik, 'maks');

        $this->audit(2025, $hasil);
        $b = SmkpBanding::untuk($this->audit(2026, $hasil));

        $k = SmkpBanding::konsistensi($b);

        $this->assertContains($buruk['kode'], array_column($k['tetap_rendah'], 'kode'));
        $this->assertContains($baik['kode'],  array_column($k['tetap_baik'], 'kode'));
        $this->assertNotContains($buruk['kode'], array_column($k['tetap_baik'], 'kode'));
    }

    #[Test]
    public function yang_turun_diurutkan_paling_parah_dahulu(): void
    {
        $sub = [];
        foreach (Smkp::elemen() as $e) {
            foreach ($e['sub'] as $s) {
                if (empty($s['subsub'])) continue;
                $sub[] = $s;
                if (count($sub) === 2) break 2;
            }
        }

        $this->assertCount(2, $sub, 'Butuh dua sub-elemen berincian.');

        $lalu = $this->isi($sub[0], fn ($b) => (int) $b['maks'])
              + $this->isi($sub[1], fn ($b) => (int) $b['maks']);

        /* Yang pertama jatuh ke nol, yang kedua turun separuh. */
        $kini = $this->isi($sub[0], fn ($b) => 0)
              + $this->isi($sub[1], fn ($b) => (int) floor($b['maks'] / 2));

        $this->audit(2025, $lalu);
        $k = SmkpBanding::konsistensi(SmkpBanding::untuk($this->audit(2026, $kini)));

        $this->assertSame($sub[0]['kode'], $k['turun'][0]['kode'],
            'Penurunan terparah tidak berada di puncak daftar.');
        $this->assertLessThan(0, $k['turun'][0]['selisih']);
    }

    /* ═══════════ dasbor ═══════════ */

    #[Test]
    public function dasbor_menggambar_seluruh_riwayat_urut_tahun_menaik(): void
    {
        [, $sub] = $this->subBerincian();

        $this->audit(2024, $this->isi($sub, fn ($b) => 0));
        $this->audit(2025, $this->isi($sub, fn ($b) => 1));
        $this->audit(2026, $this->isi($sub, fn ($b) => (int) $b['maks']));

        $props = $this->actingAs($this->u)->get('/smkp/dasbor')
            ->assertOk()->viewData('page')['props'];

        $this->assertSame(['2024', '2025', '2026'], $props['tahun']);
        $this->assertCount(3, $props['akhir']);
        $this->assertCount(3, $props['periode']);
        $this->assertSame(2026, $props['terbaru']['tahun']);
        $this->assertTrue($props['banding']['ada']);
        $this->assertSame(2025, $props['banding']['tahun']);
    }

    #[Test]
    public function tiap_elemen_punya_deret_sepanjang_jumlah_tahun(): void
    {
        /* Kerangka deret disusun dari acuan, bukan diisi sambil
           menelusuri riwayat. Diisi sambil jalan, elemen yang seluruh
           butirnya N/A tidak pernah muncul sama sekali dan grafik tren
           kehilangan barisnya tanpa satu pun tanda. */
        $this->audit(2025);
        $this->audit(2026);

        $props = $this->actingAs($this->u)->get('/smkp/dasbor')
            ->assertOk()->viewData('page')['props'];

        $this->assertCount(count(Smkp::elemen()), $props['elemen']);

        foreach ($props['elemen'] as $e) {
            $this->assertCount(2, $e['nilai'], "Deret elemen {$e['kode']} tidak sepanjang jumlah tahun.");
            $this->assertNotSame('', $e['nama']);
        }
    }

    #[Test]
    public function elemen_yang_seluruhnya_na_digambar_putus_bukan_nol(): void
    {
        /* Grafik garis menggambar lubang data sebagai PUTUS. Digambar
           nol, ia menyatakan elemennya jatuh ke nol pada tahun itu —
           penurunan yang tidak pernah terjadi. */
        $e = Smkp::elemen()[0];

        $hasil = [];
        foreach ($e['sub'] as $s) {
            foreach (Smkp::butirSub($s) as $b) $hasil[$b['kode']] = ['v' => Smkp::NA];
        }

        $this->audit(2026, $hasil);

        $props = $this->actingAs($this->u)->get('/smkp/dasbor')
            ->assertOk()->viewData('page')['props'];

        $baris = collect($props['elemen'])->firstWhere('kode', $e['kode']);

        $this->assertSame([null], $baris['nilai']);
    }

    #[Test]
    public function temuan_terhitung_baik_yang_berkode_pendek_maupun_berlabel_penuh(): void
    {
        /* Kolom `jenis` menyimpan DUA BENTUK: `angkatTemuan` menulis
           kode pendek, baris lama dan pemuat data contoh menulis
           labelnya penuh. Dicocokkan dengan salah satunya saja, rekap
           menghitung nol untuk separuh barisnya — tanpa satu galat.
           Terjadi sungguhan: dasbor melaporkan "0 mayor" atas audit yang
           tabelnya berisi belasan temuan. */
        $a = $this->audit(2026);

        foreach ([
            'mayor', 'Ketidaksesuaian Mayor',
            'minor', 'Ketidaksesuaian Minor',
            'Observasi',
        ] as $jenis) {
            SmkpFinding::create([
                'audit_id' => $a->id, 'kode_kriteria' => 'I.1',
                'jenis' => $jenis, 'uraian' => 'x', 'status' => 'Open',
            ]);
        }

        $props = $this->actingAs($this->u)->get('/smkp/dasbor')
            ->assertOk()->viewData('page')['props'];

        $p = $props['periode'][0];

        $this->assertSame(2, $p['mayor'], 'Bentuk kode pendek dan label penuh tidak dihitung sama.');
        $this->assertSame(2, $p['minor']);
        $this->assertSame(1, $p['obs']);
    }

    #[Test]
    public function temuan_tiap_tahun_dihitung_dengan_satu_kueri_berkelompok(): void
    {
        /* Halaman ini menggambar seluruh riwayat. Satu kueri per tahun
           berarti ongkosnya tumbuh setiap kali perusahaan menyelesaikan
           satu audit lagi — pada halaman yang justru dibuka paling
           sering. */
        /* Diukur pada permintaan KEDUA, bukan yang pertama. Permintaan
           pertama sebuah sesi menyisipkan barisnya alih-alih
           memperbaruinya, dan selisih satu kueri itu cukup untuk
           membuat perbandingan di bawah berbunyi tanpa ada yang salah
           pada halamannya. */
        $ukur = function (): int {
            $this->actingAs($this->u)->get('/smkp/dasbor')->assertOk();

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->u)->get('/smkp/dasbor')->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        foreach ([2021, 2022] as $t) {
            $a = $this->audit($t);
            SmkpFinding::create([
                'audit_id' => $a->id, 'kode_kriteria' => 'I.1',
                'jenis' => 'Mayor', 'uraian' => 'x', 'status' => 'Open',
            ]);
        }

        $dua = $ukur();

        foreach ([2023, 2024, 2025, 2026] as $t) {
            $a = $this->audit($t);
            SmkpFinding::create([
                'audit_id' => $a->id, 'kode_kriteria' => 'I.1',
                'jenis' => 'Minor', 'uraian' => 'x', 'status' => 'Open',
            ]);
        }

        $enam = $ukur();

        /* Yang dijaga BENTUKNYA, bukan angkanya: menambah empat tahun
           tidak boleh menambah kueri. Dipatok pada satu angka, uji ini
           akan berbunyi pada tiap perubahan yang tidak berbahaya —
           lalu dilonggarkan orang berikutnya, dan penjagaannya hilang. */
        $this->assertLessThanOrEqual($dua, $enam,
            "Dasbor enam tahun memakai {$enam} kueri, dua tahun memakai {$dua}. Ongkosnya tumbuh per tahun.");
    }

    #[Test]
    public function dasbor_perusahaan_lain_tidak_terbaca_pengguna_biasa(): void
    {
        $lain = Company::create(['name' => 'PT Lain']);
        $this->audit(2026, [], $lain);
        $this->audit(2025);

        /* `?perusahaan=` dari yang bukan admin diabaikan, bukan ditolak:
           scope sudah menjaga datanya, dan menolak dengan galat hanya
           memberi tahu bahwa perusahaan itu ada. */
        $props = $this->actingAs($this->u)
            ->get('/smkp/dasbor?perusahaan='.$lain->id)
            ->assertOk()->viewData('page')['props'];

        $this->assertSame($this->c->id, $props['perusahaan']['id']);
        $this->assertSame(['2025'], $props['tahun']);
        $this->assertSame([], $props['pilihan']);
    }
}
