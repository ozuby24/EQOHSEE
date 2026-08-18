<?php

namespace Tests\Feature;

use App\Models\{Company, HazardReport, Inspection, InspectionItem, KoAction, KoObject,
                SmkpAudit, SmkpFinding, TindakLanjut, User};
use App\Support\{DataContoh, Hazard, Temuan};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Register temuan lintas modul.
 *
 * Yang diuji di sini bukan bahwa registernya berjalan melainkan bahwa ia
 * benar-benar MENYATUKAN: lima sumber dengan lima bentuk kolom yang
 * berbeda harus terbaca sebagai satu daftar, dan temuan yang tidak
 * ditangani siapa pun harus dapat dipisahkan dari yang sedang berjalan.
 *
 * Register yang hanya memuat satu sumber lulus setiap uji yang sekadar
 * memanggilnya — dan itu justru bentuk kegagalan yang paling mungkin di
 * sini, sebab satu sumber yang diam-diam gagal dibaca tidak menimbulkan
 * galat apa pun, hanya daftar yang lebih pendek.
 */
class TemuanTest extends TestCase
{
    use RefreshDatabase;

    private function perusahaan(): Company
    {
        return Company::create(['name' => 'PT Uji Temuan']);
    }

    /* ─────────── pembuat tiap sumber ─────────── */

    private function tindakLanjut(Company $c, array $isi = []): TindakLanjut
    {
        return TindakLanjut::withoutGlobalScopes()->create(array_merge([
            'company_id'       => $c->id,
            'modul'            => 'air',
            'judul'            => 'Ganti pompa',
            'prioritas'        => 'sedang',
            'status'           => 'berjalan',
            'penanggung_jawab' => 'Pengawas',
            'target_selesai'   => now()->addDays(7)->toDateString(),
        ], $isi));
    }

    private function temuanSmkp(Company $c, array $isi = []): SmkpFinding
    {
        $audit = SmkpAudit::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'tahun' => 2026, 'status' => 'berjalan', 'hasil' => [],
        ]);

        return SmkpFinding::create(array_merge([
            'audit_id'         => $audit->id,
            'kode_kriteria'    => 'SMKP-1.1',
            'jenis'            => 'Major',
            'uraian'           => 'Prosedur isolasi energi belum ditetapkan',
            'status'           => 'Open',
            'penanggung_jawab' => 'KTT',
            'target_selesai'   => now()->addDays(5)->toDateString(),
        ], $isi));
    }

    private function aksiKo(Company $c, array $isi = []): KoAction
    {
        $objek = KoObject::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'kode' => 'KO-1', 'nama' => 'Crane',
            'kategori' => 'Peralatan',
        ]);

        return KoAction::create(array_merge([
            'ko_object_id' => $objek->id,
            'sumber'       => 'Inspeksi uji',
            'uraian'       => 'Sertifikat lewat masa berlaku',
            'prioritas'    => 'Tinggi',
            'status'       => 'Berjalan',
            'pic_nama'     => 'Kepala Workshop',
            'target_tgl'   => now()->addDays(3)->toDateString(),
        ], $isi));
    }

    private function hazard(Company $c, array $isi = []): HazardReport
    {
        return HazardReport::withoutGlobalScopes()->create(array_merge([
            'company_id'   => $c->id,
            'kode'         => 'HZ-'.fake()->unique()->numberBetween(100, 999),
            'pelapor_nama' => 'Pelapor',
            'tanggal'      => now()->subDays(3)->toDateString(),
            'lokasi'       => 'Pit',
            'risiko'       => 'Tinggi',
            'deskripsi'    => 'Tanggul ambles',
            'status'       => Hazard::STATUS[0],
        ], $isi));
    }

    private function butirInspeksi(Company $c, array $isi = []): InspectionItem
    {
        $i = Inspection::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'kode' => 'INS-'.fake()->unique()->numberBetween(100, 999),
            'judul' => 'Inspeksi jalan', 'jenis' => 'harian',
            'tanggal' => now()->subDays(2)->toDateString(), 'status' => 'selesai',
        ]);

        return InspectionItem::create(array_merge([
            'inspection_id' => $i->id,
            'uraian'  => 'Tanggul pengaman',
            'kondisi' => Hazard::KONDISI[1],
            'risiko'  => 'Tinggi',
            'temuan'  => 'Tanggul hanya sepertiga diameter ban',
        ], $isi));
    }

    /* ─────────── penyatuan ─────────── */

    public function test_kelima_sumber_terbaca_dalam_satu_daftar(): void
    {
        $c = $this->perusahaan();

        $this->tindakLanjut($c);
        $this->temuanSmkp($c);
        $this->aksiKo($c);
        $this->hazard($c);
        $this->butirInspeksi($c);

        $modul = collect(Temuan::semua($c))->pluck('modul')->unique();

        foreach (['air', 'audit-smkp', 'keselamatan-operasi', 'hazard', 'inspeksi'] as $m) {
            $this->assertTrue($modul->contains($m), "Sumber '$m' tidak terbaca register.");
        }
    }

    public function test_setiap_baris_membawa_bentuk_yang_sama(): void
    {
        $c = $this->perusahaan();
        $this->tindakLanjut($c);
        $this->hazard($c);

        foreach (Temuan::semua($c) as $t) {
            foreach ([
                'sumber', 'modul', 'kode', 'judul', 'prioritas', 'status',
                'terbuka', 'bertuan', 'terlambat', 'hariTerlambat',
            ] as $k) {
                $this->assertArrayHasKey($k, $t, "Kunci '$k' hilang dari baris register.");
            }
        }
    }

    /* ─────────── tak bertuan ─────────── */

    public function test_temuan_tanpa_pemilik_dan_tenggat_ditandai_tak_bertuan(): void
    {
        $c = $this->perusahaan();
        $this->hazard($c);

        $t = collect(Temuan::semua($c))->firstWhere('modul', 'hazard');

        $this->assertFalse($t['bertuan'],
            'Laporan bahaya tidak punya kolom penanggung jawab maupun tenggat sama sekali.');
    }

    public function test_pemilik_tanpa_tenggat_belum_dianggap_bertuan(): void
    {
        $c = $this->perusahaan();

        // Nama tanpa tanggal tidak pernah jatuh tempo, jadi tidak pernah ditagih.
        $this->tindakLanjut($c, ['penanggung_jawab' => 'Pengawas', 'target_selesai' => null]);

        $t = collect(Temuan::semua($c))->firstWhere('modul', 'air');
        $this->assertFalse($t['bertuan']);
    }

    public function test_tenggat_tanpa_pemilik_belum_dianggap_bertuan(): void
    {
        $c = $this->perusahaan();
        $this->tindakLanjut($c, ['penanggung_jawab' => null]);

        $t = collect(Temuan::semua($c))->firstWhere('modul', 'air');
        $this->assertFalse($t['bertuan']);
    }

    /* ─────────── keterlambatan ─────────── */

    public function test_yang_lewat_tenggat_ditandai_terlambat_beserta_harinya(): void
    {
        $c = $this->perusahaan();
        $this->tindakLanjut($c, ['target_selesai' => now()->subDays(9)->toDateString()]);

        $t = collect(Temuan::semua($c))->firstWhere('modul', 'air');

        $this->assertTrue($t['terlambat']);
        $this->assertSame(9, $t['hariTerlambat']);
    }

    public function test_yang_sudah_selesai_tidak_pernah_terlambat(): void
    {
        $c = $this->perusahaan();
        $this->tindakLanjut($c, [
            'status' => 'selesai',
            'target_selesai' => now()->subDays(30)->toDateString(),
        ]);

        $t = collect(Temuan::semua($c))->firstWhere('modul', 'air');

        $this->assertFalse($t['terbuka']);
        $this->assertFalse($t['terlambat'],
            'Yang sudah selesai tidak menuntut apa pun, sekalipun dahulu lewat tenggat.');
    }

    /* ─────────── urutan ─────────── */

    public function test_yang_terlambat_berada_di_atas_yang_belum(): void
    {
        $c = $this->perusahaan();

        $this->tindakLanjut($c, ['modul' => 'biaya', 'target_selesai' => now()->addDays(30)->toDateString()]);
        $this->tindakLanjut($c, ['modul' => 'air',   'target_selesai' => now()->subDays(5)->toDateString()]);

        $urut = collect(Temuan::semua($c))->pluck('modul')->all();

        $this->assertSame('air', $urut[0], 'Yang terlambat harus berada paling atas.');
    }

    public function test_yang_selesai_turun_ke_bawah(): void
    {
        $c = $this->perusahaan();

        $this->tindakLanjut($c, ['modul' => 'geoteknik', 'status' => 'selesai']);
        $this->tindakLanjut($c, ['modul' => 'air']);

        $urut = collect(Temuan::semua($c))->pluck('modul')->all();

        $this->assertSame('geoteknik', end($urut));
    }

    /* ─────────── ringkasan ─────────── */

    public function test_ringkasan_memisahkan_terlambat_dari_tak_bertuan(): void
    {
        $c = $this->perusahaan();

        $this->tindakLanjut($c, ['target_selesai' => now()->subDays(4)->toDateString()]); // terlambat, bertuan
        $this->hazard($c);                                                                // tak bertuan
        $this->tindakLanjut($c, ['modul' => 'biaya', 'status' => 'selesai']);              // selesai

        $r = Temuan::ringkas(Temuan::semua($c));

        $this->assertSame(3, $r['semua']);
        $this->assertSame(2, $r['terbuka']);
        $this->assertSame(1, $r['terlambat']);
        $this->assertSame(1, $r['takBertuan']);
        $this->assertSame(1, $r['selesai']);
    }

    /* ─────────── batas perusahaan ─────────── */

    public function test_temuan_perusahaan_lain_tidak_ikut_terbaca(): void
    {
        $milikku = $this->perusahaan();
        $orang   = Company::create(['name' => 'PT Sebelah']);

        $this->tindakLanjut($orang, ['judul' => 'Milik perusahaan lain']);
        $this->temuanSmkp($orang, ['uraian' => 'Temuan perusahaan lain']);
        $this->aksiKo($orang, ['uraian' => 'Aksi perusahaan lain']);
        $this->hazard($orang, ['deskripsi' => 'Bahaya perusahaan lain']);
        $this->butirInspeksi($orang, ['temuan' => 'Butir perusahaan lain']);

        $this->assertSame([], Temuan::semua($milikku),
            'Register lintas modul tidak boleh menembus batas perusahaan.');
    }

    /* ─────────── inspeksi yang sudah dinaikkan ─────────── */

    public function test_butir_yang_sudah_dinaikkan_jadi_bahaya_tidak_dihitung_dua_kali(): void
    {
        $c = $this->perusahaan();
        $h = $this->hazard($c);

        $this->butirInspeksi($c, ['hazard_report_id' => $h->id]);

        $modul = collect(Temuan::semua($c))->pluck('modul');

        $this->assertTrue($modul->contains('hazard'));
        $this->assertFalse($modul->contains('inspeksi'),
            'Butir yang sudah menjadi laporan bahaya sudah terwakili olehnya.');
    }

    public function test_butir_sesuai_bukan_temuan(): void
    {
        $c = $this->perusahaan();
        $this->butirInspeksi($c, ['kondisi' => Hazard::KONDISI[0], 'temuan' => null]);

        $this->assertSame([], Temuan::semua($c));
    }

    public function test_bahaya_yang_sudah_ditutup_bukan_lagi_temuan_terbuka(): void
    {
        $c = $this->perusahaan();
        $this->hazard($c, ['status' => Hazard::STATUS[2]]);

        $this->assertSame([], Temuan::semua($c));
    }

    /* ─────────── data contoh ─────────── */

    /**
     * Data contoh harus menghidupi registernya, bukan sekadar mengisi
     * modulnya masing-masing.
     *
     * Uji-uji di atas membangun tiap sumber dengan tangan, jadi seluruhnya
     * lulus bahkan bila pemuat data contoh tidak pernah menyentuh satu pun
     * dari kelimanya. Padahal register kosong adalah persis yang dilihat
     * orang saat pertama kali membuka halaman ini pada basis data contoh —
     * dan register kosong terbaca sebagai fitur yang rusak, bukan sebagai
     * site yang bersih.
     */
    public function test_data_contoh_mengisi_registernya(): void
    {
        $c = Company::create(['name' => 'PT Contoh Temuan', 'demo' => true]);
        User::factory()->create(['company_id' => $c->id]);

        DataContoh::muat($c->fresh(), User::factory()->create(['is_admin' => true]));

        $semua = Temuan::semua($c);
        $this->assertNotEmpty($semua, 'Data contoh tidak menghasilkan satu pun temuan.');

        /* Tiap sumber disebut sendiri-sendiri. Satu penegasan atas
           jumlah total akan tetap lulus bila empat sumber terbaca dan
           satu diam-diam kosong — dan sumber yang kosong tidak
           menimbulkan galat apa pun, hanya daftar yang lebih pendek. */
        $sumber = array_unique(array_column($semua, 'sumber'));
        foreach (['tindak-lanjut', 'smkp', 'ko', 'hazard', 'inspeksi'] as $s) {
            $this->assertContains($s, $sumber, "Data contoh tidak mengisi sumber '$s'.");
        }
    }

    /**
     * Dan salah satunya harus tak bertuan.
     *
     * Kolom `bertuan` adalah alasan register ini dibangun. Bila seluruh
     * data contoh rapi — berpenanggung jawab, bertenggat — kolom itu
     * tidak pernah memperlihatkan apa yang membuatnya perlu ada, dan
     * saringan "tak bertuan" selamanya menampilkan halaman kosong pada
     * satu-satunya basis data tempat orang belajar memakai fiturnya.
     */
    public function test_data_contoh_menyertakan_temuan_tak_bertuan(): void
    {
        $c = Company::create(['name' => 'PT Contoh Yatim', 'demo' => true]);
        User::factory()->create(['company_id' => $c->id]);

        DataContoh::muat($c->fresh(), User::factory()->create(['is_admin' => true]));

        $takBertuan = array_filter(Temuan::semua($c), fn ($t) => !$t['bertuan'] && $t['terbuka']);

        $this->assertNotEmpty($takBertuan,
            'Seluruh temuan contoh bertuan, sehingga saringan "tak bertuan" tidak dapat dibuktikan.');
    }
}
