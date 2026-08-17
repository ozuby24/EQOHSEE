<?php

namespace Tests\Feature;

use App\Models\{Company, SmkpAudit, SmkpFinding, User};
use App\Support\Smkp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Delapan keluaran audit SMKP.
 *
 * Yang diuji di sini bukan bunyi tiap lembar melainkan bahwa
 * masing-masing terbuka dan membawa isi yang benar. Berkas audit
 * diserahkan sebagai satu bundel; satu lembar yang gagal terbuka
 * baru ketahuan saat bundelnya disusun — biasanya pada hari
 * penyerahan.
 */
class SmkpKeluaranTest extends TestCase
{
    use RefreshDatabase;

    private SmkpAudit $audit;

    protected function setUp(): void
    {
        parent::setUp();

        $c = Company::create(['name' => 'PT Uji Keluaran', 'doc_no_prefix' => 'UK']);

        $this->actingAs(User::factory()->create([
            'is_admin' => true, 'company_id' => $c->id, 'email_verified_at' => now(),
        ]));

        $this->audit = SmkpAudit::create([
            'company_id' => $c->id, 'tahun' => 2026, 'status' => 'berjalan',
            'judul' => 'Audit Internal SMKP 2026', 'ketua_auditor' => 'Ir. Bambang',
            'hasil' => [],
        ]);
    }

    private function temuan(array $atribut = []): SmkpFinding
    {
        return SmkpFinding::create($atribut + [
            'audit_id'      => $this->audit->id,
            'kode_kriteria' => 'III.2',
            'jenis'         => 'mayor',
            'uraian'        => 'Prosedur belum ditinjau dua tahun terakhir.',
            'status'        => 'Open',
        ]);
    }

    /* ═══════════ 1 · Formulir Kriteria Audit ═══════════ */

    public function test_formulir_kriteria_terbuka_dengan_seluruh_butir(): void
    {
        $props = $this->get(route('smkp.kriteria', $this->audit))
            ->assertOk()->viewData('page')['props'];

        /* SELURUH butir dicetak, bukan hanya yang bermasalah — butir
           yang hilang dari lembar tidak dapat dibedakan antara "tidak
           berlaku" dan "terlewat dinilai". */
        $this->assertSame(Smkp::jumlahButir(), count($props['baris']),
            'Formulir kriteria tidak memuat seluruh butir.');

        $satu = $props['baris'][0];

        foreach (['elemen', 'sub', 'kode', 'uraian', 'maks', 'nilai', 'keterangan'] as $k) {
            $this->assertArrayHasKey($k, $satu, "Kolom '{$k}' hilang dari formulir kriteria.");
        }
    }

    /**
     * Butir yang dikecualikan ditulis N/A, bukan nol.
     *
     * Nol berarti dinilai dan gagal; N/A berarti tidak berlaku. Keduanya
     * berlawanan, dan menyamakannya menurunkan skor perusahaan atas
     * butir yang memang tidak dapat berlaku baginya.
     */
    public function test_butir_dikecualikan_ditulis_na_bukan_nol(): void
    {
        $butir = Smkp::butir()[0];
        $kode  = $butir['kode'];

        /* Pengecualian disimpan sebagai NILAI butirnya, bukan penanda
           tersendiri — lihat Smkp::nilaiButir(). */
        $this->audit->update(['hasil' => [$kode => ['v' => Smkp::NA]]]);

        $baris = collect($this->get(route('smkp.kriteria', $this->audit))
            ->viewData('page')['props']['baris'])->firstWhere('kode', $kode);

        $this->assertSame(Smkp::NA, $baris['nilai']);
        $this->assertNotSame(0, $baris['nilai']);
    }

    /**
     * Lembar cetak dan berkas CSV disusun dari sumber yang SAMA.
     *
     * Dua daftar yang dibentuk terpisah cepat atau lambat berselisih,
     * dan yang membandingkan keduanya adalah auditor eksternal.
     */
    public function test_ekspor_csv_sejumlah_baris_dengan_lembar_cetaknya(): void
    {
        $lembar = $this->get(route('smkp.kriteria', $this->audit))
            ->viewData('page')['props']['baris'];

        $r = $this->get(route('smkp.kriteria.ekspor', $this->audit));

        $r->assertOk();
        $this->assertStringContainsString('text/csv', $r->headers->get('Content-Type'));

        $isi = $r->streamedContent();

        /* Baris judul + satu baris per butir. */
        $this->assertSame(count($lembar) + 1,
            count(array_filter(explode("\n", trim($isi)))),
            'Jumlah baris CSV berbeda dari lembar cetaknya.');
    }

    /* ═══════════ 2 · Rekapitulasi Ketidaksesuaian ═══════════ */

    public function test_rekap_ketidaksesuaian_menghitung_per_elemen(): void
    {
        $this->temuan(['kode_kriteria' => 'III.2', 'jenis' => 'mayor']);
        $this->temuan(['kode_kriteria' => 'III.5', 'jenis' => 'minor']);
        $this->temuan(['kode_kriteria' => 'IV.1', 'jenis' => 'minor', 'status' => SmkpFinding::TUTUP]);

        $props = $this->get(route('smkp.rekapNc', $this->audit))
            ->assertOk()->viewData('page')['props'];

        $this->assertSame(3, $props['ringkas']['total']);
        $this->assertSame(1, $props['ringkas']['mayor']);
        $this->assertSame(2, $props['ringkas']['minor']);
        $this->assertSame(1, $props['ringkas']['tertutup']);

        $elemen3 = collect($props['perElemen'])->firstWhere('kode', 'III');

        $this->assertSame(2, $elemen3['total'], 'Sebaran per elemen salah hitung.');
        $this->assertSame(1, $elemen3['mayor']);
    }

    /**
     * Sebaran dihitung dari kode kriterianya, bukan dari kolom tersendiri.
     *
     * Kode "3.2" sudah menyebut elemennya. Menyimpannya dua kali
     * melahirkan dua sumber kebenaran yang berselisih begitu satu temuan
     * dipindah kriterianya — dan uji ini memindahkannya.
     */
    public function test_sebaran_ikut_berpindah_saat_kriteria_diubah(): void
    {
        $t = $this->temuan(['kode_kriteria' => 'III.2']);

        $ambil = fn (string $kode) => collect(
            $this->get(route('smkp.rekapNc', $this->audit))->viewData('page')['props']['perElemen']
        )->firstWhere('kode', $kode)['total'];

        $this->assertSame(1, $ambil('III'));

        $t->update(['kode_kriteria' => 'V.1']);

        $this->assertSame(0, $ambil('III'));
        $this->assertSame(1, $ambil('V'));
    }

    /* ═══════════ 3 · Respon Manajemen ═══════════ */

    public function test_respon_manajemen_terbuka_dan_tersimpan(): void
    {
        $t = $this->temuan();

        $this->get(route('smkp.respon', $this->audit))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Print/SmkpRespon'));

        $this->put(route('smkp.temuan.respon', [$this->audit, $t]), [
            'respon_diterima'  => false,
            'respon_manajemen' => 'Prosedur sudah ditinjau, bukti terlampir.',
            'respon_oleh'      => 'Manajer OHSE',
            'respon_pada'      => '2026-08-17',
        ])->assertRedirect();

        $t->refresh();

        $this->assertFalse($t->respon_diterima);
        $this->assertSame('Manajer OHSE', $t->respon_oleh);
    }

    /**
     * Respon punya TIGA keadaan, bukan dua.
     *
     * Belum menjawab, menerima, menolak. Boolean biasa menjatuhkan
     * "belum menjawab" menjadi "menolak" — dan itu menuduh manajemen
     * atas sikap yang belum pernah mereka nyatakan.
     */
    public function test_belum_menjawab_berbeda_dari_menolak(): void
    {
        $t = $this->temuan();

        $this->assertNull($t->respon_diterima, 'Temuan baru sudah dianggap menolak.');

        $this->put(route('smkp.temuan.respon', [$this->audit, $t]), ['respon_diterima' => false]);

        $this->assertFalse($t->refresh()->respon_diterima);
        $this->assertNotNull($t->respon_diterima);
    }

    /* ═══════════ 4 · Rencana Tindak Lanjut ═══════════ */

    /**
     * Diurut menurut TENGGAT, bukan menurut beratnya.
     *
     * Sebuah observasi yang tenggatnya lusa lebih mendesak daripada
     * mayor yang tenggatnya tiga bulan lagi.
     */
    public function test_rencana_tindak_diurut_menurut_tenggat(): void
    {
        $this->temuan(['jenis' => 'mayor',  'kode_kriteria' => 'III.1',
                       'target_selesai' => now()->addMonths(3)]);
        $this->temuan(['jenis' => 'obs',    'kode_kriteria' => 'III.2',
                       'target_selesai' => now()->addDays(2)]);
        $this->temuan(['jenis' => 'minor',  'kode_kriteria' => 'III.3',
                       'target_selesai' => null]);

        $props = $this->get(route('smkp.rencanaTindak', $this->audit))
            ->assertOk()->viewData('page')['props'];

        $kode = array_column($props['temuan'], 'kode_kriteria');

        $this->assertSame(['III.2', 'III.1', 'III.3'], $kode,
            'Urutannya bukan menurut tenggat, atau yang tanpa tenggat tidak di bawah.');

        $this->assertSame(1, $props['ringkas']['tanpaTarget']);
    }

    /** Yang lewat tenggat ditandai; yang sudah selesai tidak. */
    public function test_lewat_tenggat_ditandai_kecuali_yang_sudah_selesai(): void
    {
        $this->temuan(['kode_kriteria' => 'III.1',
                       'target_selesai' => now()->subDays(5), 'status' => 'Open']);
        $this->temuan(['kode_kriteria' => 'III.2',
                       'target_selesai' => now()->subDays(5), 'status' => SmkpFinding::TUTUP]);

        $props = $this->get(route('smkp.rencanaTindak', $this->audit))
            ->viewData('page')['props'];

        $baris = collect($props['temuan'])->keyBy('kode_kriteria');

        $this->assertTrue($baris['III.1']['lewat']);
        $this->assertFalse($baris['III.2']['lewat'],
            'Temuan yang sudah selesai ikut ditandai lewat tenggat.');

        $this->assertSame(1, $props['ringkas']['lewat']);
    }

    /* ═══════════ 8 · Ketidaksesuaian & Tindak Lanjut ═══════════ */

    public function test_lembar_nc_tindak_terbuka_dengan_kedua_foto(): void
    {
        $this->temuan([
            'foto_open'   => 'smkp/open-1.jpg',
            'foto_closed' => 'smkp/closed-1.jpg',
            'verifikasi_oleh' => 'Ketua Auditor',
        ]);

        $props = $this->get(route('smkp.ncTindak', $this->audit))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Print/SmkpNcTindak'))
            ->viewData('page')['props'];

        $t = $props['temuan'][0];

        $this->assertSame('smkp/open-1.jpg',   $t['foto_open']);
        $this->assertSame('smkp/closed-1.jpg', $t['foto_closed']);
        $this->assertSame('Ketua Auditor',     $t['verifikasi_oleh']);
    }

    /** Lembar tetap terbuka walau belum ada satu temuan pun. */
    public function test_seluruh_keluaran_terbuka_tanpa_temuan(): void
    {
        foreach (['smkp.kriteria', 'smkp.rekapNc', 'smkp.respon',
                  'smkp.rencanaTindak', 'smkp.ncTindak'] as $rute) {
            $this->get(route($rute, $this->audit))
                ->assertOk("Keluaran '{$rute}' gagal terbuka tanpa temuan.");
        }
    }

    /**
     * Temuan milik audit LAIN tidak dapat dijawab lewat audit ini.
     *
     * Rutenya membawa dua id, dan tanpa pemeriksaan hubungan keduanya
     * siapa pun dapat menyunting respon temuan periode lain hanya dengan
     * menukar satu angka di alamat.
     */
    public function test_temuan_audit_lain_tidak_dapat_dijawab(): void
    {
        $lain = SmkpAudit::create([
            'company_id' => $this->audit->company_id, 'tahun' => 2027,
            'status' => 'draft', 'hasil' => [],
        ]);

        $t = SmkpFinding::create([
            'audit_id' => $lain->id, 'kode_kriteria' => 'III.2',
            'jenis' => 'minor', 'uraian' => 'Milik periode lain.', 'status' => 'Open',
        ]);

        $this->put(route('smkp.temuan.respon', [$this->audit, $t]), [
            'respon_manajemen' => 'Disusupkan.',
        ])->assertNotFound();

        $this->assertNull($t->refresh()->respon_manajemen);
    }
}
