<?php

namespace Tests\Feature;

use App\Models\{Company, Paspor, PasporMcu, User};
use App\Support\{Authority, Berkas};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Catatan MCU: setiap kolom yang disimpan harus dapat diisi dan terlihat.
 *
 * Tujuh dari sebelas kolom `paspor_mcu` dahulu tidak dapat dijangkau
 * sama sekali. Tiga di antaranya divalidasi controller tetapi tidak
 * punya medan di layar; empat lagi tidak ada di keduanya. Kolom yang
 * ada tetapi tidak dapat diisi bukan sekadar fitur yang belum jadi —
 * ia berbohong dua kali:
 *
 *   Kepada yang MEMBACA. Halaman riwayat MCU menghitung "rujukan
 *   tertunggak" sebagai salah satu dari empat angka ringkasannya, dan
 *   angka itu selamanya nol. Nol tidak terbaca sebagai "belum dapat
 *   diisi" melainkan sebagai "tidak ada yang tertunggak".
 *
 *   Kepada yang MENGISI. Ia melihat kolomnya pada ekspor dan pada
 *   basis data, menyimpulkan datanya ada di suatu tempat, lalu mencari
 *   di mana medannya — dan tidak menemukannya karena memang tidak ada.
 */
class KelengkapanMcuTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $admin;
    private Paspor $p;

    protected function setUp(): void
    {
        parent::setUp();

        $this->c = Company::create(['name' => 'PT Uji MCU', 'doc_no_prefix' => 'UM']);

        $this->admin = User::factory()->create([
            'is_admin' => true, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);

        $this->p = Paspor::withoutGlobalScopes()->create([
            'company_id' => $this->c->id,
            'nama' => 'Budi Santoso', 'nik' => '1234', 'jabatan' => 'Operator',
        ]);

        $this->actingAs($this->admin);
    }

    /** Isian lengkap satu catatan MCU. */
    private function isian(array $ganti = []): array
    {
        return $ganti + [
            'tgl_periksa'   => '2026-01-10',
            'tgl_expired'   => '2027-01-10',
            'nomor'         => 'MCU/2026/0001',
            'penyelenggara' => 'Klinik Pratama Sehat Tambang',
            'jenis'         => 'Berkala',
            'hasil'         => 'Fit With Note',
            'level_risiko'  => Authority::RISIKO_TINGGI,
            'pembatasan'    => 'Tidak untuk kerja ketinggian',
            'rujukan'       => 'Rujukan Sp.PD',
            'outstanding'   => '2026-02-10',
            'usia'          => 41,
            'mcu_berikutnya' => '2026-12-10',
            'status_verifikasi'  => Authority::MCU_TERVERIFIKASI,
            'catatan_kontraktor' => 'Berkas asli menyusul.',
            'remarks'            => 'Kontrol ulang 3 bulan.',
        ];
    }

    /* ═══════════ 1 · seluruh kolom dapat diisi ═══════════ */

    #[Test]
    public function seluruh_kolom_catatan_mcu_dapat_diisi_dari_formulir(): void
    {
        Storage::fake(Berkas::TERTUTUP);

        /* Kedua berkasnya ikut diunggah, bukan dilewatkan dari
           pemeriksaan. Kolom berkas yang dikecualikan dari uji
           kelengkapan adalah kolom yang tidak pernah diuji sama
           sekali — dan justru kolom berkas yang paling mudah
           tertinggal saat aturan validasinya ditulis. */
        $this->post("/miners/{$this->p->id}/mcu", $this->isian([
            'berkas' => UploadedFile::fake()->create('hasil.pdf', 20, 'application/pdf'),
            'berkas_rujukan' => UploadedFile::fake()->create('rujukan.pdf', 20, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $m = PasporMcu::withoutGlobalScopes()->firstOrFail();

        /* Dibandingkan KOLOM PER KOLOM terhadap $fillable, bukan
           terhadap daftar yang saya ketik di sini. Daftar yang diketik
           tangan akan tertinggal begitu ada kolom baru — dan kolom baru
           yang tidak dapat dijangkau adalah persis kegagalan yang uji
           ini ada untuk menghentikannya. */
        $lewat = [];

        foreach ($m->getFillable() as $kolom) {
            /* Kolom penghubung, bukan isian. */
            if (in_array($kolom, ['paspor_id', 'mcu_pengajuan_id'], true)) continue;

            if (blank($m->{$kolom})) $lewat[] = $kolom;
        }

        sort($lewat);

        $this->assertSame([], $lewat,
            "Kolom paspor_mcu berikut tidak terisi walau formulirnya lengkap — \n"
            ."artinya kolom itu tidak dapat dijangkau dari mana pun:\n  "
            .implode("\n  ", $lewat));
    }

    #[Test]
    public function rujukan_tanpa_tanggal_terhitung_tertunggak(): void
    {
        $this->post("/miners/{$this->p->id}/mcu",
            $this->isian(['rujukan' => 'Rujukan Sp.JP', 'outstanding' => null]));

        $m = PasporMcu::withoutGlobalScopes()->firstOrFail();

        $this->assertTrue($m->rujukanTertunggak(),
            'Rujukan tanpa tanggal tindak lanjut tidak terhitung tertunggak — '
            .'rujukan yang tidak pernah ditagih adalah catatan yang lengkap di '
            .'berkas dan tidak pernah terjadi di kenyataan.');
    }

    #[Test]
    public function ringkasan_riwayat_menghitung_rujukan_tertunggak(): void
    {
        $this->post("/miners/{$this->p->id}/mcu",
            $this->isian(['outstanding' => '2020-01-01']));   // sudah lewat

        $props = $this->get('/miners/riwayat/mcu')->viewData('page')['props'];

        $angka = collect($props['ringkas'])->firstWhere(0, 'Rujukan tertunggak');

        $this->assertNotNull($angka, 'Ringkasan tidak lagi menyebut rujukan tertunggak.');
        $this->assertSame(1, $angka[1],
            'Rujukan yang batasnya sudah lewat tidak terhitung pada ringkasan.');
    }

    /* ═══════════ 2 · level risiko ═══════════ */

    #[Test]
    public function level_risiko_terpisah_dari_hasil(): void
    {
        /* Layak bekerja TETAPI dekat ke batasnya — inilah yang tidak
           dapat disimpulkan dari hasilnya sendiri. */
        $this->post("/miners/{$this->p->id}/mcu",
            $this->isian(['hasil' => 'Fit', 'level_risiko' => Authority::RISIKO_TINGGI]));

        $m = PasporMcu::withoutGlobalScopes()->firstOrFail();

        $this->assertTrue($m->hasilLayak());
        $this->assertTrue($m->risikoPerluPerhatian());
    }

    #[Test]
    public function unfit_tidak_dihitung_sebagai_risiko_perlu_perhatian(): void
    {
        /* Yang sudah Unfit bukan orang yang perlu diawasi melainkan
           orang yang sudah dihentikan. Satu angka yang mencampur
           keduanya tidak dapat ditindak dengan satu cara yang sama. */
        $this->post("/miners/{$this->p->id}/mcu",
            $this->isian(['hasil' => 'Unfit', 'level_risiko' => Authority::RISIKO_TINGGI]));

        $m = PasporMcu::withoutGlobalScopes()->firstOrFail();

        $this->assertFalse($m->risikoPerluPerhatian(),
            'Pekerja Unfit ikut terhitung "risiko tinggi" — angka itu '
            .'mencampur yang perlu diawasi dengan yang sudah dihentikan.');
    }

    #[Test]
    public function level_risiko_asing_ditolak(): void
    {
        $this->post("/miners/{$this->p->id}/mcu",
            $this->isian(['level_risiko' => 'Sangat Tinggi Sekali']))
            ->assertSessionHasErrors('level_risiko');
    }

    #[Test]
    public function daftar_riwayat_menampilkan_level_risiko(): void
    {
        $this->post("/miners/{$this->p->id}/mcu",
            $this->isian(['level_risiko' => Authority::RISIKO_SEDANG]));

        $props = $this->get('/miners/riwayat/mcu')->viewData('page')['props'];

        $this->assertContains('Risiko', $props['kolom'],
            'Daftar riwayat MCU tidak punya kolom risiko.');

        $i = array_search('Risiko', $props['kolom'], true);

        $this->assertSame(Authority::RISIKO_SEDANG, $props['baris'][0]['sel'][$i]);
    }

    /* ═══════════ 3 · surat MCU terjaga ═══════════ */

    #[Test]
    public function surat_mcu_tersimpan_saat_diunggah(): void
    {
        Storage::fake(Berkas::TERTUTUP);

        $this->post("/miners/{$this->p->id}/mcu", $this->isian([
            'berkas' => UploadedFile::fake()->create('mcu-budi.pdf', 40, 'application/pdf'),
        ]))->assertSessionHasNoErrors();

        $m = PasporMcu::withoutGlobalScopes()->firstOrFail();

        $this->assertNotNull($m->berkas, 'Surat MCU tidak tersimpan.');
        Storage::disk(Berkas::TERTUTUP)->assertExists($m->berkas);
    }

    /**
     * Suratnya hanya terbuka bagi yang membacanya sebagai pekerjaannya.
     *
     * `paspor_mcu` sengaja hanya menyimpan KESIMPULAN kelayakan kerja —
     * rincian medis punya aturan kerahasiaannya sendiri. Surat dari
     * klinik justru memuat rincian itu; membiarkannya terbuka bagi
     * semua yang dapat melihat barisnya membatalkan prinsip itu lewat
     * pintu belakang: kolomnya bersih, lampirannya yang membocorkan.
     */
    #[Test]
    public function surat_mcu_tidak_dapat_dibuka_pengguna_biasa(): void
    {
        Storage::fake(Berkas::TERTUTUP);

        $this->post("/miners/{$this->p->id}/mcu", $this->isian([
            'berkas' => UploadedFile::fake()->create('mcu-budi.pdf', 40, 'application/pdf'),
        ]));

        $m = PasporMcu::withoutGlobalScopes()->firstOrFail();

        $biasa = User::factory()->create([
            'is_admin' => false, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);

        $this->actingAs($biasa)
            ->get(route('berkas.sajikan', ['jenis' => 'mcu', 'baris' => $m->id]))
            ->assertForbidden();
    }

    #[Test]
    public function surat_mcu_dapat_dibuka_paramedis_dan_ohse(): void
    {
        Storage::fake(Berkas::TERTUTUP);

        $this->post("/miners/{$this->p->id}/mcu", $this->isian([
            'berkas' => UploadedFile::fake()->create('mcu-budi.pdf', 40, 'application/pdf'),
        ]));

        $m = PasporMcu::withoutGlobalScopes()->firstOrFail();

        foreach ([['ohse_role' => 'paramedis'], ['ohse_role' => 'ohse']] as $peran) {
            $u = User::factory()->create($peran + [
                'is_admin' => false, 'company_id' => $this->c->id, 'email_verified_at' => now(),
            ]);

            $this->actingAs($u)
                ->get(route('berkas.sajikan', ['jenis' => 'mcu', 'baris' => $m->id]))
                ->assertOk();
        }
    }

    /**
     * Yang tidak berhak diberi tahu suratnya ADA, bukan dibiarkan
     * mengira belum diunggah.
     */
    #[Test]
    public function pengguna_biasa_melihat_bahwa_suratnya_terjaga(): void
    {
        Storage::fake(Berkas::TERTUTUP);

        $this->post("/miners/{$this->p->id}/mcu", $this->isian([
            'berkas' => UploadedFile::fake()->create('mcu-budi.pdf', 40, 'application/pdf'),
        ]));

        $biasa = User::factory()->create([
            'is_admin' => false, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);

        $props = $this->actingAs($biasa)
            ->get("/miners/{$this->p->id}")->viewData('page')['props'];

        $mcu = $props['mcu'][0];

        $this->assertNull($mcu['berkas'],
            'Alamat surat MCU terkirim kepada yang tidak berhak membukanya.');
        $this->assertTrue($mcu['berkasTerjaga'],
            'Yang tidak berhak tidak diberi tahu bahwa suratnya ada — ia akan '
            .'mengira berkasnya belum diunggah dan menagihnya berulang kali.');
    }

    /**
     * Surat rujukan terjaga sama ketatnya dengan surat MCU.
     *
     * Ia menyebut ke poli mana orangnya dirujuk — jantung, paru, jiwa —
     * dan itu diagnosis dengan cara lain. Menjaga surat MCU lalu
     * membiarkan surat rujukannya terbuka menutup pintu depan sambil
     * meninggalkan pintu samping.
     */
    #[Test]
    public function surat_rujukan_terjaga_seperti_surat_mcu(): void
    {
        Storage::fake(Berkas::TERTUTUP);

        $this->post("/miners/{$this->p->id}/mcu", $this->isian([
            'berkas_rujukan' => UploadedFile::fake()->create('rujukan.pdf', 20, 'application/pdf'),
        ]));

        $m = PasporMcu::withoutGlobalScopes()->firstOrFail();
        $this->assertNotNull($m->berkas_rujukan, 'Surat rujukan tidak tersimpan.');

        $biasa = User::factory()->create([
            'is_admin' => false, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);

        $this->actingAs($biasa)
            ->get(route('berkas.sajikan', ['jenis' => 'mcr', 'baris' => $m->id]))
            ->assertForbidden();

        $paramedis = User::factory()->create([
            'is_admin' => false, 'ohse_role' => 'paramedis',
            'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);

        $this->actingAs($paramedis)
            ->get(route('berkas.sajikan', ['jenis' => 'mcr', 'baris' => $m->id]))
            ->assertOk();
    }

    #[Test]
    public function berkas_lain_tidak_ikut_terjaga(): void
    {
        /* GERBANG hanya menyebut MCU. Bila suatu saat ia melebar tanpa
           sengaja, foto bahaya dan tanda tangan akan ikut tertutup dan
           halaman-halaman lain berlubang tanpa galat apa pun. */
        /* Yang MEMANG dijaga: surat MCU dan surat rujukannya. Keduanya
           memuat rincian medis — yang kedua menyebut ke poli mana
           orangnya dirujuk, dan itu diagnosis dengan cara lain. */
        $dijaga = ['mcu', 'mcr'];

        $this->assertSame($dijaga, array_keys(Berkas::GERBANG),
            'Daftar berkas terjaga berubah tanpa ujinya ikut ditinjau.');

        foreach (array_keys(Berkas::tersaji()) as $jenis) {
            if (in_array($jenis, $dijaga, true)) continue;

            $this->assertTrue(Berkas::bolehMembuka(null, $jenis),
                "Jenis berkas \"{$jenis}\" ikut terjaga gerbang MCU.");
        }
    }
}
