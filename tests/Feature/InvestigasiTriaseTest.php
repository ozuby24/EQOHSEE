<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Investigasi\Insiden;
use App\Models\Investigasi\Investigasi;
use App\Models\Investigasi\KlasifikasiRegulasi;
use App\Models\Investigasi\Taksonomi;
use App\Models\User;
use App\Support\Investigasi\{KamusScat, MasterInvestigasi, NomorInvestigasi, TahapInvestigasi, Triase};
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Triase dan alur tahap — dua keputusan yang menentukan sisa alurnya.
 *
 * Yang diuji di sini bukan tampilannya melainkan angka dan syaratnya:
 * skor risiko, level investigasi, panjang jalur tahap, dan apa yang
 * menahan sebuah berkas maju. Keempatnya menentukan seberapa dalam
 * sebuah kecelakaan diselidiki — dan kesalahan pada keempatnya tidak
 * pernah menimbulkan galat, hanya investigasi yang terlalu dangkal.
 */
class InvestigasiTriaseTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        MasterInvestigasi::pasang();

        $this->c = Company::create(['name' => 'PT Uji Investigasi', 'doc_no_prefix' => 'UIN']);

        $this->admin = User::factory()->create([
            'is_admin' => true, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);

        $this->actingAs($this->admin);
    }

    private function insiden(array $ganti = []): Insiden
    {
        return Insiden::withoutGlobalScopes()->create($ganti + [
            'company_id'       => $this->c->id,
            'no_insiden'       => NomorInvestigasi::terbitkan(NomorInvestigasi::INSIDEN),
            'judul'            => 'Unit menabrak tanggul',
            'tanggal_kejadian' => '2026-08-19',
            'waktu_kejadian'   => '14:20:00',
        ]);
    }

    private function investigasi(?string $level = 'L3'): Investigasi
    {
        $ins = $this->insiden(['level_investigasi' => $level]);

        return Investigasi::withoutGlobalScopes()->create([
            'no_investigasi' => NomorInvestigasi::terbitkan(NomorInvestigasi::INVESTIGASI),
            'insiden_id'     => $ins->id,
        ]);
    }

    /* ═══════════ 1 · matriks risiko ═══════════ */

    /**
     * Matriks terisi utuh, dan levelnya naik bersama skornya.
     *
     * Diuji tepat di batas tiap pita, bukan di tengahnya: kesalahan
     * perbandingan (>= versus >) hanya muncul pada nilai batas, dan uji
     * yang memakai skor 10 untuk pita 9–14 tidak akan menemukannya.
     */
    #[Test]
    public function matriks_lima_kali_lima_terisi_penuh(): void
    {
        $this->assertSame(25, \App\Models\Investigasi\MatriksRisiko::count());

        $batas = [
            [1, 1, 1,  'rendah', 'L1'],   // 1
            [1, 3, 3,  'rendah', 'L1'],   // 3  — batas atas rendah
            [2, 2, 4,  'sedang', 'L2'],   // 4  — batas bawah sedang
            [2, 4, 8,  'sedang', 'L2'],   // 8  — batas atas sedang
            [3, 3, 9,  'tinggi', 'L3'],   // 9  — batas bawah tinggi
            [2, 7, 0,  null,     null],   // keparahan di luar 1..5
            [3, 4, 12, 'tinggi', 'L3'],   // 12 — batas atas tinggi
            [4, 4, 16, 'kritis', 'L4'],   // 16 — kritis
        ];

        foreach ($batas as [$k, $p, $skor, $pita, $level]) {
            $h = Triase::hitung($k, $p);

            if ($pita === null) {
                $this->assertNull($h, "Kemungkinan {$k} × keparahan {$p} seharusnya ditolak.");
                continue;
            }

            $this->assertSame($skor,  $h['skor'],  "Skor {$k}×{$p} salah.");
            $this->assertSame($pita,  $h['pita'],  "Pita {$k}×{$p} salah.");
            $this->assertSame($level, $h['level'], "Level {$k}×{$p} salah.");
        }
    }

    /**
     * KEPARAHAN 5 SELALU L4, berapa pun kemungkinannya.
     *
     * Kejadian fatal yang "kecil kemungkinannya" tetap menuntut
     * investigasi penuh — itulah seluruh alasan investigasi kejadian
     * berpotensi tinggi ada. Tanpa pengecualian ini, kemungkinan 1 ×
     * keparahan 5 berskor 5 dan jatuh ke L2.
     */
    #[Test]
    public function keparahan_katastropik_selalu_l4(): void
    {
        for ($k = 1; $k <= 5; $k++) {
            $h = Triase::hitung($k, 5);

            $this->assertSame('L4', $h['level'],
                "Kemungkinan {$k} × keparahan 5 tidak menjadi L4 — skornya ".$h['skor'].'.');
            $this->assertSame('kritis', $h['pita']);
        }
    }

    /**
     * Keparahan POTENSIAL yang dipakai bila lebih tinggi.
     *
     * Unit yang lepas kendali lalu berhenti satu meter dari pekerja
     * tidak melukai siapa pun — keparahan nyatanya 1. Potensinya fatal.
     * Memakai yang nyata saja membuang justru pelajaran yang paling
     * mahal.
     */
    #[Test]
    public function keparahan_potensial_dipakai_bila_lebih_tinggi(): void
    {
        $nyata = Triase::hitung(2, 1);
        $this->assertSame('L1', $nyata['level']);

        $potensi = Triase::hitung(2, 1, 5);

        $this->assertSame('L4', $potensi['level'],
            'Keparahan potensial diabaikan — kejadian berpotensi fatal turun ke L1.');
        $this->assertSame(5, $potensi['keparahanDipakai']);
    }

    /** Potensial yang LEBIH RENDAH tidak menurunkan keparahan nyata. */
    #[Test]
    public function keparahan_potensial_tidak_menurunkan_yang_nyata(): void
    {
        $h = Triase::hitung(3, 4, 1);

        $this->assertSame(4, $h['keparahanDipakai'],
            'Keparahan potensial yang lebih rendah malah menurunkan keparahan nyata.');
    }

    /* ═══════════ 2 · tenggat regulasi ═══════════ */

    /**
     * Tenggat dihitung dari WAKTU KEJADIAN, bukan waktu pelaporan.
     *
     * Dihitung dari pelaporan, keterlambatan melapor akan memperpanjang
     * tenggatnya sendiri — dan tidak ada satu pun laporan yang akan
     * pernah terlambat.
     */
    #[Test]
    public function tenggat_dihitung_dari_waktu_kejadian(): void
    {
        $reg = KlasifikasiRegulasi::where('kode', 'kecelakaan_tambang')->first();

        $ins = $this->insiden();

        /* Dilaporkan tiga hari sesudah kejadian — dan itu justru
           keadaan yang membuat perbedaannya terlihat. */
        $ins->dilaporkan_pada = '2026-08-22 09:00:00';
        $ins->save();

        Triase::terapkan($ins, [
            'kemungkinan' => 3, 'keparahan' => 3,
            'klasifikasi_regulasi_id' => $reg->id,
        ]);

        $ins->refresh();

        $this->assertTrue($ins->wajib_lapor_kait);
        $this->assertSame('2026-08-20 14:20:00', $ins->tenggat_lapor->format('Y-m-d H:i:s'),
            'Tenggat lapor tidak 24 jam sesudah waktu kejadian.');
        $this->assertSame('2026-08-21 14:20:00', $ins->tenggat_selidik->format('Y-m-d H:i:s'),
            'Tenggat selidik tidak 48 jam sesudah waktu kejadian.');
    }

    /**
     * Wajib lapor mengikuti KLASIFIKASI REGULASI, bukan skor risiko.
     *
     * Kejadian berbahaya tanpa korban satu pun tetap wajib dilaporkan,
     * dan skornya bisa saja rendah. Menebaknya dari skor akan membuat
     * kewajiban hukum bergantung pada penilaian yang subjektif.
     */
    #[Test]
    public function wajib_lapor_mengikuti_klasifikasi_bukan_skor(): void
    {
        $berbahaya = KlasifikasiRegulasi::where('kode', 'kejadian_berbahaya')->first();
        $bukan     = KlasifikasiRegulasi::where('kode', 'bukan_kecelakaan_tambang')->first();

        /* Skor serendah mungkin, tetapi klasifikasinya wajib lapor. */
        $a = $this->insiden();
        Triase::terapkan($a, ['kemungkinan' => 1, 'keparahan' => 1, 'klasifikasi_regulasi_id' => $berbahaya->id]);

        $this->assertTrue($a->refresh()->wajib_lapor_kait,
            'Kejadian berbahaya berskor rendah tidak ditandai wajib lapor.');
        $this->assertNotNull($a->tenggat_lapor);

        /* Skor setinggi mungkin, tetapi klasifikasinya bukan. */
        $b = $this->insiden();
        Triase::terapkan($b, ['kemungkinan' => 5, 'keparahan' => 4, 'klasifikasi_regulasi_id' => $bukan->id]);

        $this->assertFalse($b->refresh()->wajib_lapor_kait,
            'Kejadian berskor tinggi ditandai wajib lapor padahal klasifikasinya bukan.');
        $this->assertNull($b->tenggat_lapor);
    }

    /** Lima kriteria disebut satu per satu, bukan sebagai satu kesimpulan. */
    #[Test]
    public function kriteria_yang_tidak_terpenuhi_disebut_satu_per_satu(): void
    {
        $ins = $this->insiden();

        Triase::terapkan($ins, [
            'kemungkinan' => 3, 'keparahan' => 3,
            'k1_benar_terjadi' => 1, 'k2_mencederai_pekerja' => 1,
            'k3_akibat_kegiatan' => 1, 'k4_jam_kerja' => 0, 'k5_wilayah_usaha' => 1,
        ]);

        $kurang = $ins->refresh()->kriteriaKurang();

        $this->assertCount(1, $kurang);
        $this->assertStringContainsString('jam kerja', $kurang[0]);
        $this->assertFalse($ins->memenuhiKecelakaanTambang());
    }

    /* ═══════════ 3 · panjang jalur tahap ═══════════ */

    /**
     * Jalurnya mengikuti level, dan L1 memang lebih pendek.
     *
     * Enam tahap dengan syarat penuh untuk pekerja yang lecet siku
     * menghasilkan bukan investigasi yang lebih baik melainkan berkas L1
     * yang tidak pernah ditutup sama sekali.
     */
    #[Test]
    public function jalur_tahap_memendek_pada_level_ringan(): void
    {
        $this->assertCount(4, TahapInvestigasi::jalur('L1'));
        $this->assertCount(5, TahapInvestigasi::jalur('L2'));
        $this->assertCount(6, TahapInvestigasi::jalur('L3'));
        $this->assertCount(6, TahapInvestigasi::jalur('L4'));

        $this->assertFalse(TahapInvestigasi::dilalui('analisis', 'L1'));
        $this->assertFalse(TahapInvestigasi::dilalui('verifikasi', 'L1'));
        $this->assertTrue(TahapInvestigasi::dilalui('analisis', 'L2'));
        $this->assertFalse(TahapInvestigasi::dilalui('verifikasi', 'L2'));
    }

    /**
     * Level yang belum diketahui memakai jalur PENUH.
     *
     * Melewatkan tahap hanya boleh terjadi kalau levelnya memang sudah
     * diketahui. Menganggap yang belum ditriase sebagai L1 berarti
     * kecelakaan fatal yang triasenya belum sempat diisi diselidiki
     * dengan jalur teringan.
     */
    #[Test]
    public function level_yang_belum_diketahui_memakai_jalur_penuh(): void
    {
        $this->assertCount(6, TahapInvestigasi::jalur(null));
        $this->assertCount(6, TahapInvestigasi::jalur('LX'));
    }

    /**
     * Tiap jalur adalah himpunan bagian dari urutan penuh.
     *
     * Jalur pendek tidak boleh memperkenalkan nama tahap baru: nilai
     * kolom `tahap` harus selalu ada di daftar yang dikenali, atau
     * layarnya akan menggambar rel tanpa satu pun bulatan menyala.
     */
    #[Test]
    public function jalur_pendek_tidak_memperkenalkan_tahap_baru(): void
    {
        $penuh = array_keys(TahapInvestigasi::URUTAN);

        foreach (TahapInvestigasi::JALUR as $level => $jalur) {
            $this->assertSame(
                array_values(array_intersect($penuh, $jalur)), $jalur,
                "Jalur {$level} tidak mengikuti urutan penuh atau memuat tahap asing.");
        }
    }

    /**
     * Berkas yang levelnya DITURUNKAN tidak tersangkut.
     *
     * Berkas L3 yang sedang di tahap Verifikasi lalu ditriase ulang
     * menjadi L1 berada pada tahap yang tidak ada di jalurnya sendiri.
     * Tanpa penanganan ini ia tidak punya tahap berikutnya dan tidak
     * akan pernah dapat ditutup.
     */
    #[Test]
    public function berkas_yang_levelnya_diturunkan_tetap_punya_tahap_berikutnya(): void
    {
        $this->assertSame('penutupan', TahapInvestigasi::berikutnya('verifikasi', 'L1'),
            'Berkas yang turun level tersangkut di tahap yang bukan jalurnya.');

        /* Dan syaratnya tidak menahannya: tahap di luar jalur tidak
           menuntut apa pun, sebab pekerjaannya sudah dititipkan ke
           tahap lain. */
        $inv = $this->investigasi('L1');
        $inv->update(['tahap' => 'verifikasi']);

        $this->assertSame([], TahapInvestigasi::yangKurang($inv->fresh()));
    }

    /* ═══════════ 4 · syarat tiap tahap ═══════════ */

    #[Test]
    public function tahap_perencanaan_menyebut_apa_yang_kurang(): void
    {
        $inv = $this->investigasi('L3');

        $kurang = implode(' ', TahapInvestigasi::yangKurang($inv));

        $this->assertStringContainsString('Ketua investigasi', $kurang);
        $this->assertStringContainsString('Target selesai', $kurang);
        $this->assertStringContainsString('Tujuan', $kurang);
        $this->assertStringContainsString('Tim investigasi', $kurang);

        $this->assertFalse(TahapInvestigasi::bolehMaju($inv));
    }

    /**
     * Tim TIDAK diwajibkan pada jalur pendek.
     *
     * Insiden ringan diselidiki pengawas yang bersangkutan sendiri, dan
     * memaksanya menambah anggota tim hanya menghasilkan nama
     * asal-asalan di berkas.
     */
    #[Test]
    public function tim_tidak_diwajibkan_pada_jalur_pendek(): void
    {
        $inv = $this->investigasi('L1');

        $inv->update([
            'ketua_id' => $this->admin->id,
            'target_selesai' => '2026-09-30',
            'tujuan' => 'Menetapkan penyebab terpeleset di tangga workshop.',
        ]);

        $this->assertSame([], TahapInvestigasi::yangKurang($inv->fresh()),
            'Jalur pendek masih menuntut anggota tim.');
        $this->assertTrue(TahapInvestigasi::bolehMaju($inv->fresh()));
    }

    /**
     * Akar masalah tetap wajib di L1 — hanya pindah tempat.
     *
     * Yang dilewati jalur pendek adalah TAHAPNYA, bukan isinya. Tidak
     * satu pun syarat penutupan hilang; akar masalah diminta pada tahap
     * Rencana Aksi alih-alih lewat layar analisis tersendiri.
     */
    #[Test]
    public function akar_masalah_tetap_wajib_pada_jalur_pendek(): void
    {
        $inv = $this->investigasi('L1');
        $inv->update(['tahap' => 'rencana_aksi']);

        $kurang = implode(' ', TahapInvestigasi::yangKurang($inv->fresh()));

        $this->assertStringContainsString('Akar masalah', $kurang,
            'Jalur pendek melewatkan akar masalah sama sekali.');
        $this->assertStringContainsString('5 Why', $kurang);
    }

    /* ═══════════ 5 · kamus dan idempotensi ═══════════ */

    #[Test]
    public function kamus_scat_terpasang_utuh_dua_ratus_lima_puluh_dua_butir(): void
    {
        $this->assertSame(252, Taksonomi::where('metode', 'scat')->count());

        /* Bagian 9 — Lack of Control — adalah yang paling mudah hilang:
           kamus SCAT klasik ILCI tidak punya bagian itu sama sekali, dan
           tanpanya tiap investigasi berakhir pada "operator kurang
           hati-hati" alih-alih pada kegagalan sistem. */
        $this->assertGreaterThan(0,
            Taksonomi::where('metode', 'scat')->where('kode', 'like', '9.%')->count(),
            'Bagian 9 (Lack of Control) tidak ada di kamus.');

        /* Tujuh butir faktor pribadi pernah hilang diam-diam karena dua
           kategori memperoleh kode yang sama dan saling menimpa. Kode
           yang unik adalah yang mencegahnya terulang.

           Dibandingkan KETAT, dan itu bukan kerewelan. `unique()` tanpa
           argumen kedua membandingkan longgar, dan PHP membaca dua
           string yang sama-sama numerik sebagai angka: '5.1' == '5.10'
           bernilai benar, begitu pula '5.2' == '5.20' dan '6.1' ==
           '6.10'. Tiga pasang itu benar-benar ada di kamus ini, jadi
           perbandingan longgar melaporkan tiga butir "berulang" yang
           sebenarnya berbeda — uji yang menuduh cacat yang tidak ada. */
        $kode = Taksonomi::where('metode', 'scat')->pluck('kode');

        $this->assertSame($kode->count(), $kode->unique(null, true)->count(),
            'Ada kode SCAT yang berulang — butir yang tertimpa hilang tanpa galat.');

        /* Dan pasangan yang mudah tertukar itu memang keduanya ada. */
        foreach (['5.1', '5.10', '5.2', '5.20', '6.1', '6.10'] as $k) {
            $this->assertTrue($kode->contains($k), "Butir SCAT {$k} tidak tersimpan.");
        }
    }

    /**
     * Pemasangan berulang tidak menggandakan.
     *
     * Perintahnya akan dijalankan lagi setiap pemasangan ulang dan tiap
     * pembaruan. Seeder yang menggandakan membuat kamus 252 butir
     * menjadi 504 — lalu rekap "penyebab terbanyak" menghitung tiap
     * penyebab dua kali, dan angka itu tidak pernah terlihat salah.
     */
    #[Test]
    public function pemasangan_berulang_tidak_menggandakan(): void
    {
        $sebelum = [
            'taksonomi' => Taksonomi::count(),
            'matriks'   => \App\Models\Investigasi\MatriksRisiko::count(),
            'cedera'    => \App\Models\Investigasi\KlasifikasiCedera::count(),
        ];

        MasterInvestigasi::pasang();
        MasterInvestigasi::pasang();

        $this->assertSame($sebelum['taksonomi'], Taksonomi::count());
        $this->assertSame($sebelum['matriks'], \App\Models\Investigasi\MatriksRisiko::count());
        $this->assertSame($sebelum['cedera'], \App\Models\Investigasi\KlasifikasiCedera::count());
    }

    /* ═══════════ 6 · nomor ═══════════ */

    /**
     * Nomor insiden dan nomor investigasi berjalan TERPISAH.
     *
     * Keduanya dirujuk di dokumen berbeda oleh orang berbeda, dan satu
     * deret untuk dua benda membuat "sudah sampai mana INC-2026-0011"
     * menjadi pertanyaan yang punya dua jawaban.
     */
    #[Test]
    public function nomor_insiden_dan_investigasi_berjalan_terpisah(): void
    {
        $a = NomorInvestigasi::terbitkan(NomorInvestigasi::INSIDEN, 2026);
        $b = NomorInvestigasi::terbitkan(NomorInvestigasi::INSIDEN, 2026);
        $c = NomorInvestigasi::terbitkan(NomorInvestigasi::INVESTIGASI, 2026);

        $this->assertSame('INC-2026-0001', $a);
        $this->assertSame('INC-2026-0002', $b);
        $this->assertSame('INV-2026-0001', $c,
            'Nomor investigasi ikut melanjutkan deret insiden.');
    }

    /** Deretnya mulai dari satu lagi tiap tahun. */
    #[Test]
    public function deret_nomor_mulai_ulang_tiap_tahun(): void
    {
        NomorInvestigasi::terbitkan(NomorInvestigasi::INSIDEN, 2026);
        NomorInvestigasi::terbitkan(NomorInvestigasi::INSIDEN, 2026);

        $this->assertSame('INC-2027-0001', NomorInvestigasi::terbitkan(NomorInvestigasi::INSIDEN, 2027));
    }
}
