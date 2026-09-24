<?php

namespace Tests\Feature;

use App\Models\{Company, EnvAudit, EnvAuditScore, User};
use App\Support\AuditLingkungan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Audit Kinerja Pengelolaan dan Pemantauan Lingkungan.
 *
 * Yang dijaga di sini bukan tampilannya melainkan ANGKANYA. Skor audit
 * ini menentukan predikat penghargaan dan peringkat warna yang dikirim
 * ke pemegang IUP — dan angka yang salah tidak memulangkan galat apa
 * pun, ia hanya memulangkan penghargaan kepada mitra yang tidak
 * berhak, atau menahannya dari mitra yang berhak.
 *
 * Tiga hal yang paling mudah rusak tanpa ketahuan:
 *
 *   · Kode kriteria yang kembar — nilai satu kriteria menimpa nilai
 *     kriteria lain, diam-diam, dan skornya tetap tampil wajar.
 *   · Bobot yang tidak berjumlah satu — persentase pemenuhan berhenti
 *     dapat mencapai seratus, atau justru melewatinya.
 *   · Syarat nilai penuh pada bagian A dan B yang tidak ditegakkan —
 *     predikat terbit untuk mitra yang administrasinya belum lengkap.
 */
class AuditLingkunganTest extends TestCase
{
    use RefreshDatabase;

    private static int $n = 0;

    /** Jumlah nilai maksimum tiap bagian menurut berkas acuan CAM. */
    private const MAKS = ['a' => 21, 'b' => 450, 'c' => 27, 'd' => 21, 'e' => 48, 'f' => 36];

    private function perusahaan(): Company
    {
        $i = ++self::$n;

        return Company::create(['name' => "PT Uji Lingkungan {$i}", 'code' => "PL{$i}"]);
    }

    private function masuk(?Company $c = null, bool $admin = true): User
    {
        $u = User::factory()->create(['is_admin' => $admin, 'company_id' => $c?->id]);
        $this->actingAs($u);

        return $u;
    }

    private function audit(Company $c, array $x = []): EnvAudit
    {
        return EnvAudit::withoutGlobalScopes()->create(array_merge([
            'company_id' => $c->id,
            'kode'       => 'AKL-2026-'.sprintf('%03d', ++self::$n),
            'tahun'      => 2026,
            'judul'      => 'Audit Kinerja Pengelolaan Lingkungan 2026',
        ], $x));
    }

    /** Seluruh kriteria bernilai sama, sebagai peta kode → nilai. */
    private function semua(int $nilai): array
    {
        $out = [];

        foreach (AuditLingkungan::bagian() as $kunci => $_) {
            foreach (AuditLingkungan::kriteria($kunci) as $k) {
                $out[$k['kode']] = $nilai;
            }
        }

        return $out;
    }

    /* ══════════════ master kriteria ══════════════ */

    /**
     * Tidak satu pun kode kriteria kembar.
     *
     * Nilai disimpan menurut kode. Dua kriteria berkode sama berarti
     * yang kedua menimpa yang pertama pada `updateOrCreate` — tanpa
     * galat, tanpa tanda di layar, dan dengan skor akhir yang tetap
     * tampil masuk akal. Berkas acuan CAM sendiri memuat dua nomor
     * kembar (bagian B subbagian VI kelompok 2, dan bagian D kelompok 1
     * huruf b), jadi ini bukan kemungkinan teoretis.
     */
    public function test_kode_kriteria_tidak_kembar(): void
    {
        $semua = [];

        foreach (AuditLingkungan::bagian() as $kunci => $_) {
            foreach (AuditLingkungan::kriteria($kunci) as $k) {
                $this->assertNotSame('', trim($k['kode']), "Bagian {$kunci} punya kriteria tanpa kode.");
                $semua[] = $k['kode'];
            }
        }

        $kembar = array_keys(array_filter(array_count_values($semua), fn ($n) => $n > 1));

        $this->assertSame([], $kembar,
            'Kode kriteria kembar: '.implode(', ', $kembar).
            ' — nilai yang satu akan menimpa nilai yang lain.');

        $this->assertSame(201, count($semua),
            'Jumlah kriteria berubah dari 201; periksa resources/data/audit/lingkungan.json.');
    }

    /**
     * Nilai maksimum tiap bagian sama persis dengan berkas acuannya.
     *
     * Ini pemeriksaan terhadap PENGAMBILAN datanya, bukan terhadap
     * hitungannya: satu baris yang tidak terbaca saat ekstraksi
     * menurunkan maksimum bagiannya tiga poin, dan seluruh persentase
     * di atasnya berubah tanpa satu pun tanda.
     */
    public function test_nilai_maksimum_tiap_bagian_sesuai_acuan(): void
    {
        foreach (self::MAKS as $kunci => $maks) {
            $this->assertSame($maks, AuditLingkungan::jumlahKriteria($kunci) * 3,
                "Maksimum bagian ".strtoupper($kunci)." bukan {$maks}.");

            $this->assertSame($maks, (int) AuditLingkungan::satu($kunci)['maks'],
                "Maksimum tercatat bagian ".strtoupper($kunci)." tidak sama dengan jumlah kriterianya × 3.");
        }
    }

    /**
     * Tiap kriteria punya uraian, kecuali yang memang kosong di acuan.
     *
     * Berkas CAM memuat satu baris bernilai tetapi tanpa teks (bagian B
     * kelompok 3 huruf e). Ia DIPERTAHANKAN supaya maksimum bagiannya
     * tetap 450, dan ditandai `kosong` supaya yang mengisinya tahu
     * teksnya harus dilengkapi sendiri — bukan dihapus diam-diam.
     */
    public function test_kriteria_tanpa_uraian_ditandai_bukan_dibuang(): void
    {
        $kosong = 0;

        foreach (AuditLingkungan::bagian() as $kunci => $_) {
            foreach (AuditLingkungan::kriteria($kunci) as $k) {
                if ($k['kosong']) { $kosong++; continue; }

                $this->assertNotSame('', trim((string) $k['uraian']),
                    "Kriteria {$k['kode']} tanpa uraian dan tanpa tanda `kosong`.");
            }
        }

        $this->assertSame(1, $kosong,
            'Jumlah kriteria bertanda kosong berubah; periksa berkas acuannya.');
    }

    /** Bobot keenam bagian berjumlah tepat satu. */
    public function test_bobot_berjumlah_satu(): void
    {
        $jumlah = array_sum(array_map(
            fn ($b) => (float) $b['bobot'],
            AuditLingkungan::bagian(),
        ));

        $this->assertEqualsWithDelta(1.0, $jumlah, 0.0001,
            'Bobot bagian tidak berjumlah 1 — persentase pemenuhan tidak lagi dapat mencapai 100.');
    }

    /* ══════════════ hitungan skor ══════════════ */

    /** Seluruh kriteria bernilai tiga menghasilkan 100, ADITAMA, EMAS. */
    public function test_nilai_penuh_menghasilkan_seratus_aditama_emas(): void
    {
        $h = AuditLingkungan::hitung($this->semua(3));

        $this->assertEqualsWithDelta(100.0, $h['pemenuhan'], 0.01);
        $this->assertEqualsWithDelta(100.0, $h['akhir'], 0.01);
        $this->assertSame(0, $h['belum']);
        $this->assertTrue($h['penuhWajib']);
        $this->assertSame('ADITAMA', $h['predikat']['nama']);
        $this->assertNull($h['predikat']['alasan']);
        $this->assertSame('EMAS', $h['peringkat']['nama']);
    }

    /** Seluruh kriteria bernilai nol menghasilkan 0 dan HITAM tanpa predikat. */
    public function test_nilai_nol_menghasilkan_hitam_tanpa_predikat(): void
    {
        $h = AuditLingkungan::hitung($this->semua(0));

        $this->assertEqualsWithDelta(0.0, $h['pemenuhan'], 0.01);
        $this->assertNull($h['predikat']['nama']);
        $this->assertSame('HITAM', $h['peringkat']['nama']);
    }

    /**
     * Kehilangan SATU poin di bagian wajib menahan predikatnya.
     *
     * Ini inti syarat minimal instrumen ini, dan satu-satunya tempat
     * yang membedakan predikat dari peringkat. Nilainya tetap 99,95 —
     * jauh di atas ambang ADITAMA — tetapi predikatnya tidak terbit,
     * sementara peringkat warnanya tetap EMAS.
     */
    public function test_satu_poin_kurang_di_bagian_wajib_menahan_predikat(): void
    {
        $nilai = $this->semua(3);
        $kode  = AuditLingkungan::kriteria('a')[0]['kode'];
        $nilai[$kode] = 2;

        $h = AuditLingkungan::hitung($nilai);

        $this->assertFalse($h['penuhWajib']);
        $this->assertNull($h['predikat']['nama'], 'Predikat terbit padahal bagian A tidak penuh.');
        $this->assertStringContainsString('PENUH', $h['predikat']['alasan'],
            'Alasan tertahannya predikat tidak menyebut syarat nilai penuh.');

        $this->assertGreaterThan(99.0, $h['akhir']);
        $this->assertSame('EMAS', $h['peringkat']['nama'],
            'Peringkat warna ikut tertahan padahal ia tidak bersyarat nilai penuh.');
    }

    /**
     * Kehilangan poin di bagian TIDAK wajib tidak menahan predikatnya.
     *
     * Pasangan dari uji di atas. Tanpa ini, syarat nilai penuh yang
     * keliru dipasang pada keenam bagian akan tetap lolos.
     */
    public function test_kurang_di_bagian_tidak_wajib_tetap_berpredikat(): void
    {
        $nilai = $this->semua(3);
        $nilai[AuditLingkungan::kriteria('f')[0]['kode']] = 0;

        $h = AuditLingkungan::hitung($nilai);

        $this->assertTrue($h['penuhWajib']);
        $this->assertSame('ADITAMA', $h['predikat']['nama']);
    }

    /**
     * Nilai pengurang dipotong dari skor AKHIR, bukan dari bagiannya.
     *
     * Dipotong dari bagiannya, ia akan tertimbang lebih dulu — dan
     * pengurang lima poin berubah menjadi tiga, atau menjadi nol koma
     * dua lima, tergantung bagian mana yang dipotong.
     */
    public function test_pengurang_memotong_skor_akhir_saja(): void
    {
        $h = AuditLingkungan::hitung($this->semua(3), ['kecelakaan', 'sanksi-abai', 'pea-3']);

        $this->assertEqualsWithDelta(100.0, $h['pemenuhan'], 0.01,
            'Persentase pemenuhan ikut terpotong; pengurang seharusnya hanya menyentuh skor akhir.');
        $this->assertSame(11, $h['pengurang']);
        $this->assertEqualsWithDelta(89.0, $h['akhir'], 0.01);
        $this->assertSame('UTAMA', $h['predikat']['nama'],
            'Predikat tidak turun meski skor akhir jatuh di bawah 90.');

        $this->assertSame(['kecelakaan', 'sanksi-abai', 'pea-3'],
            array_column($h['rincianKurang'], 'kunci'));
    }

    /** Pengurang yang tidak dikenal tidak memotong apa pun. */
    public function test_pengurang_asing_diabaikan(): void
    {
        $h = AuditLingkungan::hitung($this->semua(3), ['entah-apa']);

        $this->assertSame(0, $h['pengurang']);
        $this->assertEqualsWithDelta(100.0, $h['akhir'], 0.01);
    }

    /** Skor akhir tidak pernah negatif. */
    public function test_skor_akhir_tidak_negatif(): void
    {
        $h = AuditLingkungan::hitung($this->semua(0), array_keys(AuditLingkungan::PENGURANG));

        $this->assertSame(0.0, $h['akhir']);
        $this->assertSame('HITAM', $h['peringkat']['nama']);
    }

    /**
     * Kriteria yang belum dinilai dihitung nol, tetapi DILAPORKAN.
     *
     * Berbeda dari modul Pemenuhan, di sini nol memang arti yang benar
     * untuk "belum ada bukti". Yang berbahaya adalah skor sementara
     * yang dibaca sebagai skor akhir — jadi jumlah yang belum diisi
     * harus ikut terbawa keluar.
     */
    public function test_kriteria_belum_dinilai_dihitung_nol_dan_dilaporkan(): void
    {
        $nilai = $this->semua(3);
        $kode  = AuditLingkungan::kriteria('f')[0]['kode'];
        unset($nilai[$kode]);

        $h = AuditLingkungan::hitung($nilai);

        $this->assertSame(1, $h['belum']);
        $this->assertSame(201, $h['kriteria']);
        $this->assertSame(1, $h['bagian']['f']['belum']);
        $this->assertLessThan(100.0, $h['akhir']);
    }

    /** Nilai null setara dengan belum dinilai, bukan nol yang disengaja. */
    public function test_nilai_null_terhitung_belum_dinilai(): void
    {
        $nilai = $this->semua(3);
        $nilai[AuditLingkungan::kriteria('f')[0]['kode']] = null;

        $this->assertSame(1, AuditLingkungan::hitung($nilai)['belum']);
    }

    /* ══════════════ dua kolom nilai ══════════════ */

    /**
     * Yang menentukan skor adalah kolom VERIFIKASI, bukan kolom nilai.
     *
     * Disatukan menjadi satu kolom, penilaian mandiri mitra langsung
     * menjadi skor resminya, dan audit berubah menjadi formulir isian
     * mandiri yang ditandatangani auditor.
     */
    public function test_skor_dihitung_dari_verifikasi_bukan_penilaian_mandiri(): void
    {
        $c = $this->perusahaan();
        $a = $this->audit($c);

        foreach (AuditLingkungan::bagian() as $kunci => $_) {
            foreach (AuditLingkungan::kriteria($kunci) as $k) {
                EnvAuditScore::create([
                    'audit_id'   => $a->id,
                    'kode'       => $k['kode'],
                    'nilai'      => 3,     // mitra menilai dirinya sempurna
                    'verifikasi' => 1,     // auditor menemukan lain
                ]);
            }
        }

        $h = $a->fresh()->skor();

        $this->assertEqualsWithDelta(33.33, $h['pemenuhan'], 0.05,
            'Skor mengikuti penilaian mandiri mitra, bukan hasil verifikasi auditor.');
        $this->assertNull($h['predikat']['nama']);
    }

    /* ══════════════ penyimpanan nilai ══════════════ */

    /** Nilai satu bagian tersimpan dalam satu kiriman. */
    public function test_menyimpan_nilai_satu_bagian(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        $kirim = [];
        foreach (AuditLingkungan::kriteria('a') as $k) {
            $kirim[$k['kode']] = ['nilai' => 2, 'verifikasi' => 3, 'keterangan' => 'Terverifikasi.'];
        }

        $this->post("/audit-lingkungan/{$a->id}/nilai", ['bagian' => 'a', 'nilai' => $kirim])
             ->assertRedirect();

        $this->assertSame(7, EnvAuditScore::where('audit_id', $a->id)->count());
        $this->assertSame(21, (int) EnvAuditScore::where('audit_id', $a->id)->sum('verifikasi'));
        $this->assertTrue($a->fresh()->skor()['bagian']['a']['penuh']);
    }

    /**
     * Kode dari bagian LAIN pada kiriman satu bagian dilewati.
     *
     * Kiriman yang membawa kode asing akan menaruh nilai pada kriteria
     * yang tidak pernah tampil di layar bagian itu — dan nilai itu ikut
     * menentukan skor akhir tanpa pernah dapat ditinjau siapa pun.
     */
    public function test_kode_asing_pada_kiriman_bagian_dilewati(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        $sah   = AuditLingkungan::kriteria('a')[0]['kode'];
        $asing = AuditLingkungan::kriteria('b')[0]['kode'];

        $this->post("/audit-lingkungan/{$a->id}/nilai", ['bagian' => 'a', 'nilai' => [
            $sah   => ['nilai' => 3, 'verifikasi' => 3],
            $asing => ['nilai' => 3, 'verifikasi' => 3],
        ]])->assertRedirect();

        $this->assertDatabaseHas('env_audit_scores', ['audit_id' => $a->id, 'kode' => $sah]);
        $this->assertDatabaseMissing('env_audit_scores', ['audit_id' => $a->id, 'kode' => $asing]);
    }

    /** Nilai di luar tangga nol sampai tiga ditolak. */
    public function test_nilai_di_luar_tangga_ditolak(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        $kode = AuditLingkungan::kriteria('a')[0]['kode'];

        $this->post("/audit-lingkungan/{$a->id}/nilai", ['bagian' => 'a', 'nilai' => [
            $kode => ['nilai' => 4, 'verifikasi' => 3],
        ]])->assertSessionHasErrors();

        $this->assertDatabaseCount('env_audit_scores', 0);
    }

    /** Menyimpan ulang memperbarui baris yang sama, bukan menambahnya. */
    public function test_menyimpan_ulang_tidak_menggandakan_baris(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        $kode = AuditLingkungan::kriteria('a')[0]['kode'];

        foreach ([1, 3] as $v) {
            $this->post("/audit-lingkungan/{$a->id}/nilai", [
                'bagian' => 'a', 'nilai' => [$kode => ['nilai' => $v, 'verifikasi' => $v]],
            ])->assertRedirect();
        }

        $this->assertSame(1, EnvAuditScore::where('audit_id', $a->id)->count());
        $this->assertSame(3, EnvAuditScore::where('audit_id', $a->id)->first()->verifikasi);
    }

    /** Nilai pengurang tersimpan dan langsung memotong skornya. */
    public function test_pengurang_tersimpan_dari_layar(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        $this->post("/audit-lingkungan/{$a->id}/pengurang", ['pengurang' => ['sanksi-tindak']])
             ->assertRedirect();

        $this->assertSame(['sanksi-tindak'], $a->fresh()->pengurang);
        $this->assertSame(1, $a->fresh()->skor()['pengurang']);
    }

    /* ══════════════ batas perusahaan dan akses ══════════════ */

    /** Audit perusahaan lain tidak dapat dibuka. */
    public function test_audit_perusahaan_lain_tidak_ditemukan(): void
    {
        $milik = $this->audit($this->perusahaan());

        /* Pengguna biasa, bukan administrator: administrator memang
           menjangkau seluruh perusahaan selama ia belum memilih salah
           satu di bilah atas, dan mengujinya dengan administrator
           hanya akan menguji kelonggaran itu. */
        $this->masuk($this->perusahaan(), admin: false);

        $this->get("/audit-lingkungan/{$milik->id}")->assertNotFound();
        $this->get("/audit-lingkungan/{$milik->id}/bagian/a")->assertNotFound();
        $this->post("/audit-lingkungan/{$milik->id}/nilai", ['bagian' => 'a', 'nilai' => []])
             ->assertNotFound();
    }

    /** Berkas pendukung milik audit perusahaan lain tidak dapat disentuh. */
    public function test_berkas_kriteria_perusahaan_lain_tidak_ditemukan(): void
    {
        $milik = $this->audit($this->perusahaan());

        $skor = EnvAuditScore::create([
            'audit_id' => $milik->id,
            'kode'     => AuditLingkungan::kriteria('a')[0]['kode'],
        ]);

        $this->masuk($this->perusahaan(), admin: false);

        $this->post("/audit-lingkungan/berkas/{$skor->id}", [
            'berkas' => [\Illuminate\Http\UploadedFile::fake()->create('bukti.pdf', 12, 'application/pdf')],
        ])->assertNotFound();

        $this->assertNull($skor->fresh()->berkas);
    }

    /* ═══════════ dokumen bukti per kriteria ═══════════ */

    private function pdf(string $nama = 'bukti.pdf'): \Illuminate\Http\UploadedFile
    {
        return \Illuminate\Http\UploadedFile::fake()->create($nama, 12, 'application/pdf');
    }

    /** Bukti dapat diunggah pada kriteria yang BELUM pernah dinilai. */
    public function test_unggah_bukti_pada_kriteria_yang_belum_dinilai(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c, admin: false);
        $a = $this->audit($c);
        $kode = AuditLingkungan::kriteria('b')[0]['kode'];

        $this->post("/audit-lingkungan/{$a->id}/bukti/{$kode}", [
            'berkas' => [$this->pdf('Izin TPS LB3.pdf'), $this->pdf('Foto pintu air.pdf')],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $s = EnvAuditScore::where('audit_id', $a->id)->where('kode', $kode)->firstOrFail();
        $this->assertCount(2, $s->berkas);
        $this->assertSame(['Izin TPS LB3.pdf', 'Foto pintu air.pdf'], $s->berkas_nama);
        foreach ($s->berkas as $j) \Illuminate\Support\Facades\Storage::disk(\App\Support\Berkas::TERTUTUP)->assertExists($j);

        // Nilainya tetap kosong: mengunggah bukti bukan menilai.
        $this->assertNull($s->verifikasi);

        $this->get("/audit-lingkungan/{$a->id}/bagian/b")->assertInertia(fn (AssertableInertia $p) => $p
            ->where('susun.0.kelompok.0.butir.0.bukti.0.nama', 'Izin TPS LB3.pdf')
            ->where('susun.0.kelompok.0.butir.0.bukti.1.nama', 'Foto pintu air.pdf')
            ->where('susun.0.kelompok.0.butir.0.urlBukti', route('audit-lingkungan.bukti', [$a, $kode]))
            ->where('maksBukti', \App\Http\Controllers\AuditLingkunganController::MAKS_BUKTI));
    }

    /** Kode yang bukan kriteria ditolak dan tidak dibuatkan baris. */
    public function test_unggah_bukti_kode_asing_ditolak(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c, admin: false);
        $a = $this->audit($c);

        $this->post("/audit-lingkungan/{$a->id}/bukti/z.9.9", ['berkas' => [$this->pdf()]])->assertNotFound();
        $this->assertSame(0, EnvAuditScore::where('audit_id', $a->id)->count());
    }

    /** Batas bukti per kriteria dijaga, termasuk yang sudah ada. */
    public function test_batas_bukti_per_kriteria(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c, admin: false);
        $a = $this->audit($c);
        $kode = AuditLingkungan::kriteria('a')[0]['kode'];
        $maks = \App\Http\Controllers\AuditLingkunganController::MAKS_BUKTI;

        $this->post("/audit-lingkungan/{$a->id}/bukti/{$kode}", [
            'berkas' => array_map(fn ($i) => $this->pdf("b{$i}.pdf"), range(1, $maks - 1)),
        ])->assertSessionHasNoErrors();

        $this->post("/audit-lingkungan/{$a->id}/bukti/{$kode}", [
            'berkas' => [$this->pdf('lebih1.pdf'), $this->pdf('lebih2.pdf')],
        ])->assertSessionHasErrors('berkas');

        $this->assertCount($maks - 1, EnvAuditScore::where('audit_id', $a->id)->where('kode', $kode)->first()->berkas);
    }

    /** Jenis berkas berbahaya ditolak. */
    public function test_unggah_bukti_menolak_berkas_berbahaya(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c, admin: false);
        $a = $this->audit($c);
        $kode = AuditLingkungan::kriteria('a')[0]['kode'];

        $this->post("/audit-lingkungan/{$a->id}/bukti/{$kode}", [
            'berkas' => [\Illuminate\Http\UploadedFile::fake()->create('skrip.php', 1, 'application/x-php')],
        ])->assertSessionHasErrors();

        $this->assertNull(EnvAuditScore::where('audit_id', $a->id)->where('kode', $kode)->first()?->berkas);
    }

    /** Menghapus satu bukti membuang berkasnya dan menjaga nama tetap sejajar. */
    public function test_hapus_bukti(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c, admin: false);
        $a = $this->audit($c);
        $kode = AuditLingkungan::kriteria('a')[0]['kode'];

        $this->post("/audit-lingkungan/{$a->id}/bukti/{$kode}", [
            'berkas' => [$this->pdf('satu.pdf'), $this->pdf('dua.pdf'), $this->pdf('tiga.pdf')],
        ]);
        $s = EnvAuditScore::where('audit_id', $a->id)->where('kode', $kode)->first();
        $dibuang = $s->berkas[1];

        $this->post("/audit-lingkungan/{$a->id}/bukti/{$kode}/hapus", ['indeks' => 1])->assertRedirect();

        $s->refresh();
        $this->assertSame(['satu.pdf', 'tiga.pdf'], $s->berkas_nama);
        $this->assertCount(2, $s->berkas);
        \Illuminate\Support\Facades\Storage::disk(\App\Support\Berkas::TERTUTUP)->assertMissing($dibuang);

        $this->post("/audit-lingkungan/{$a->id}/bukti/{$kode}/hapus", ['indeks' => 9])->assertNotFound();
    }

    /** Bukti pada audit perusahaan lain tidak dapat ditambah maupun dihapus. */
    public function test_bukti_audit_perusahaan_lain_tidak_ditemukan(): void
    {
        $milik = $this->audit($this->perusahaan());
        $kode  = AuditLingkungan::kriteria('a')[0]['kode'];
        EnvAuditScore::create(['audit_id' => $milik->id, 'kode' => $kode, 'berkas' => ['audit-lingkungan/x.pdf']]);

        $this->masuk($this->perusahaan(), admin: false);

        $this->post("/audit-lingkungan/{$milik->id}/bukti/{$kode}", ['berkas' => [$this->pdf()]])->assertNotFound();
        $this->post("/audit-lingkungan/{$milik->id}/bukti/{$kode}/hapus", ['indeks' => 0])->assertNotFound();
        $this->assertSame(['audit-lingkungan/x.pdf'],
            EnvAuditScore::where('audit_id', $milik->id)->first()->berkas);
    }

    /** Tamu tidak dapat membuka daftar auditnya. */
    public function test_tamu_tidak_dapat_membuka_daftar(): void
    {
        $this->get('/audit-lingkungan')->assertRedirect('/login');
    }

    /** Menghapus audit hanya untuk admin. */
    public function test_hapus_hanya_untuk_admin(): void
    {
        $c = $this->perusahaan();
        $a = $this->audit($c);

        $this->masuk($c, admin: false);
        $this->delete("/audit-lingkungan/{$a->id}")->assertForbidden();
        $this->assertDatabaseHas('env_audits', ['id' => $a->id]);

        $this->masuk($c);
        $this->delete("/audit-lingkungan/{$a->id}")->assertRedirect();
        $this->assertDatabaseMissing('env_audits', ['id' => $a->id]);
    }

    /** Nilainya ikut terbuang bersama auditnya. */
    public function test_nilai_ikut_terbuang_bersama_auditnya(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        EnvAuditScore::create(['audit_id' => $a->id, 'kode' => 'a.1.a', 'verifikasi' => 3]);

        $this->delete("/audit-lingkungan/{$a->id}")->assertRedirect();

        $this->assertDatabaseCount('env_audit_scores', 0);
    }

    /* ══════════════ halaman ══════════════ */

    /** Halaman bagian menyajikan seluruh kriteria bagian itu saja. */
    public function test_halaman_bagian_menyajikan_kriterianya(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        $this->get("/audit-lingkungan/{$a->id}/bagian/c")
             ->assertOk()
             ->assertInertia(fn (AssertableInertia $p) => $p
                 ->component('AuditLingkungan/Bagian')
                 ->where('kini', 'c')
                 ->where('skorBagian.kriteria', 9)
                 ->has('susun'));
    }

    /** Bagian yang tidak dikenal tidak membuka halaman kosong. */
    public function test_bagian_tidak_dikenal_tidak_ditemukan(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        $this->get("/audit-lingkungan/{$a->id}/bagian/z")->assertNotFound();
    }

    /** Lembar cetak memuat keenam bagian beserta seluruh kriterianya. */
    public function test_lembar_cetak_memuat_seluruh_kriterianya(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        $this->get("/audit-lingkungan/{$a->id}/lembar")
             ->assertOk()
             ->assertInertia(fn (AssertableInertia $p) => $p
                 ->component('Print/AuditLingkunganLembar')
                 ->has('bagian', 6));
    }

    /** Nomor audit berurut dalam tahunnya. */
    public function test_nomor_audit_berurut(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);

        $this->post('/audit-lingkungan', [
            'judul' => 'Audit pertama', 'tahun' => 2026, 'company_id' => $c->id,
        ])->assertRedirect();

        $this->post('/audit-lingkungan', [
            'judul' => 'Audit kedua', 'tahun' => 2026, 'company_id' => $c->id,
        ])->assertRedirect();

        $kode = EnvAudit::withoutGlobalScopes()->where('company_id', $c->id)
            ->orderBy('id')->pluck('kode')->all();

        $this->assertSame(['AKL-2026-001', 'AKL-2026-002'], $kode);
    }

    /* ══════════════ profil perusahaan ══════════════ */

    /**
     * Profil dipulangkan berlabel, bukan sebagai nama kunci mentah.
     *
     * Dipulangkan apa adanya, layar ikhtisar menampilkan
     * "karyawanNonStaff" — dan `text-transform: capitalize` tidak dapat
     * memperbaikinya, sebab yang salah bukan huruf besar-kecilnya
     * melainkan tidak adanya spasi.
     */
    public function test_profil_dipulangkan_berlabel_dan_hanya_yang_terisi(): void
    {
        $out = AuditLingkungan::profil([
            'karyawanNonStaff' => '206',
            'alamat'           => 'Jl. Hauling KM 12',
            'telepon'          => '   ',
            'kontak2'          => null,
        ]);

        $this->assertSame(['alamat', 'karyawanNonStaff'], array_column($out, 'kunci'),
            'Profil tidak berurut menurut daftar medannya, atau memuat medan yang kosong.');

        $this->assertSame('Karyawan non-staff', $out[1]['label']);
        $this->assertSame('206', $out[1]['nilai']);

        $this->assertSame([], AuditLingkungan::profil(null));
    }

    /* ══════════════ kiriman yang ditolak ══════════════ */

    /**
     * Keterangan yang melampaui batas MEMBATALKAN seluruh kiriman bagian.
     *
     * Ini perilaku yang benar — separuh bagian tersimpan lebih buruk
     * daripada tidak sama sekali — tetapi ia harus TERLIHAT. Sebelumnya
     * tidak: halaman bagian tidak punya satu pun tempat menggambar
     * galat, kiriman yang ditolak memulangkan 302, layar tidak berubah
     * sedikit pun, dan yang mengisinya menyimpulkan nilainya sudah
     * tersimpan. Pada lembar berisi seratus lima puluh kriteria, itu
     * berarti satu hari kerja auditor yang hilang tanpa satu pun tanda.
     */
    public function test_keterangan_terlalu_panjang_membatalkan_kiriman(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        $kode = AuditLingkungan::kriteria('a')[0]['kode'];
        $lain = AuditLingkungan::kriteria('a')[1]['kode'];

        $this->post(route('audit-lingkungan.nilai', $a), [
            'bagian' => 'a',
            'nilai'  => [
                $kode => ['nilai' => 3, 'verifikasi' => 3,
                          'keterangan' => str_repeat('x', AuditLingkungan::MAKS_KETERANGAN + 1)],
                $lain => ['nilai' => 3, 'verifikasi' => 3],
            ],
        ])->assertSessionHasErrors("nilai.{$kode}.keterangan");

        /* TIDAK SATU PUN tersimpan — termasuk kriteria yang sah. */
        $this->assertSame(0, EnvAuditScore::withoutGlobalScopes()->where('audit_id', $a->id)->count(),
            'Sebagian nilai tersimpan meski kirimannya ditolak.');
    }

    /**
     * Pesan galatnya menyebut KODE KRITERIANYA.
     *
     * "nilai.3.b.keterangan" memaksa yang mengisinya menghitung sendiri
     * baris keberapa yang dimaksud di antara seratus lima puluh, dan
     * hampir selalu salah hitung.
     */
    public function test_pesan_galat_menyebut_kode_kriteria(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        $kode = AuditLingkungan::kriteria('a')[0]['kode'];

        $this->post(route('audit-lingkungan.nilai', $a), [
            'bagian' => 'a',
            'nilai'  => [$kode => ['keterangan' => str_repeat('x', AuditLingkungan::MAKS_KETERANGAN + 1)]],
        ])->assertSessionHasErrors("nilai.{$kode}.keterangan");

        /* Yang diperiksa ISI pesannya, bukan keberadaan kuncinya —
           kuncinya tidak pernah dibaca orang, pesannyalah yang dibaca.
           Dibaca lewat kedua bentuk yang mungkin dipulangkan sesi,
           sebab bentuknya berbeda antara ViewErrorBag hidup dan yang
           sudah tersimpan sebagai larik. */
        $bag = session('errors');
        $kunci = "nilai.{$kode}.keterangan";

        $pesan = is_object($bag)
            ? $bag->getBag('default')->first($kunci)
            : ($bag['default']['messages'][$kunci][0] ?? '');

        $this->assertStringContainsString("kriteria {$kode}", $pesan,
            "Pesan galat tidak menyebut kriteria mana: {$pesan}");
    }

    /**
     * Batas keterangan dikirim ke layar, tidak ditulis ulang di sana.
     *
     * Dua angka yang mengatur hal yang sama pada dua sisi adalah cara
     * paling pasti membuat layar menerima apa yang server tolak.
     */
    public function test_batas_keterangan_dikirim_ke_layar(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        $this->get(route('audit-lingkungan.bagian', [$a, 'a']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('maksKeterangan', AuditLingkungan::MAKS_KETERANGAN));
    }

    /** Keterangan tepat pada batasnya diterima. */
    public function test_keterangan_tepat_pada_batas_diterima(): void
    {
        $c = $this->perusahaan();
        $this->masuk($c);
        $a = $this->audit($c);

        $kode = AuditLingkungan::kriteria('a')[0]['kode'];

        $this->post(route('audit-lingkungan.nilai', $a), [
            'bagian' => 'a',
            'nilai'  => [$kode => ['nilai' => 3, 'verifikasi' => 3,
                                   'keterangan' => str_repeat('x', AuditLingkungan::MAKS_KETERANGAN)]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(AuditLingkungan::MAKS_KETERANGAN, strlen(
            (string) EnvAuditScore::withoutGlobalScopes()->where('audit_id', $a->id)->first()?->keterangan
        ));
    }

    /** Halaman bagian punya tempat menggambar galat kirimannya. */
    public function test_halaman_bagian_punya_tampilan_galat(): void
    {
        $vue = file_get_contents(resource_path('js/Pages/AuditLingkungan/Bagian.vue'));

        $this->assertStringContainsString('onError', $vue,
            'Kiriman yang ditolak tidak ditangkap sama sekali.');
        $this->assertStringContainsString('TIDAK tersimpan', $vue,
            'Tidak ada spanduk yang menyatakan bagiannya tidak tersimpan.');
        $this->assertStringContainsString('preserveState', $vue,
            'Isian hilang ketika kirimannya ditolak.');
        $this->assertStringContainsString('maksKeterangan', $vue,
            'Medan keterangan tidak dibatasi sepanjang batas server.');
    }
}
