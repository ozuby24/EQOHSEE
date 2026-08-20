<?php

namespace Tests\Feature;

use App\Models\{Company, TpkkpAssessment, TpkkpResponse, User};
use App\Support\TpkkpKuesioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Jabatan yang menentukan kuesioner, bukan respondennya.
 *
 * Halaman pembuka dahulu menyodorkan dua kartu dan meminta responden
 * memilih sendiri. Pekerja tambang berulang kali memilih "Pimpinan Unit
 * Kerja" — bukan karena lalai melainkan karena kartu pertama yang
 * terlihat memang itu, dan tidak ada apa pun di layar yang mengatakan
 * pilihan itu keliru.
 *
 * Akibatnya bukan sekadar data berantakan: jawaban pekerja masuk
 * sebagai persepsi PIMPINAN atas dirinya sendiri, sehingga nilai
 * kepemimpinan pada penilaian PTPKKP naik atau turun oleh orang yang
 * tidak memimpin siapa pun — dan tidak ada satu pun tanda bahwa itu
 * terjadi.
 *
 * Yang dijaga di sini penentuannya di SISI SERVER. Penyaringan di layar
 * hanya menuntun; alamat dapat disusun tangan, dan penjagaan yang hanya
 * ada di layar bukan penjagaan.
 */
class KuesionerJabatanTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private string $token = 'uji-token-kuesioner';

    protected function setUp(): void
    {
        parent::setUp();

        $this->c = Company::create(['name' => 'PT Uji Kuesioner', 'doc_no_prefix' => 'UK']);

        $admin = User::factory()->create([
            'is_admin' => true, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);

        TpkkpAssessment::create([
            'company_id' => $this->c->id,
            'tahun'  => now()->year,
            'judul'  => 'Penilaian uji',
            'status' => 'draf',
            'profil' => ['tokens' => [$this->c->id => $this->token]],
        ]);

        $this->actingAs($admin);
    }

    /* ═══════════ penentuan kategori ═══════════ */

    #[Test]
    public function jabatan_resmi_dipetakan_ke_kelompoknya(): void
    {
        foreach ([
            'Direktur'         => 'pimpinan',
            'General Manager'  => 'pimpinan',
            'Supervisor/Dokter' => 'pimpinan',
            'Foreman / Leading Hand / Group Leader/Officer/Paramedic' => 'pimpinan',
            'Operator'         => 'pekerja',
            'Driver'           => 'pekerja',
            'Admin'            => 'pekerja',
        ] as $jabatan => $harap) {
            $this->assertSame($harap, TpkkpKuesioner::kategoriUntukJabatan($jabatan),
                "Jabatan \"{$jabatan}\" masuk kelompok yang salah.");
        }
    }

    /**
     * Jabatan yang diketik bebas dikenali dari kata kuncinya.
     *
     * Data lama dan isian bebas tidak pernah persis sama dengan daftar
     * resmi. Menolaknya berarti seluruh respons lama kehilangan
     * kelompoknya.
     */
    #[Test]
    public function jabatan_bebas_dikenali_dari_kata_kunci(): void
    {
        foreach ([
            'Kepala Teknik Tambang' => 'pimpinan',
            'SUPERVISOR PRODUKSI'   => 'pimpinan',
            'pengawas operasional'  => 'pimpinan',
            'KTT'                   => 'pimpinan',
            'Tukang las'            => 'pekerja',
            'Helper workshop'       => 'pekerja',
        ] as $jabatan => $harap) {
            $this->assertSame($harap, TpkkpKuesioner::kategoriUntukJabatan($jabatan),
                "Jabatan bebas \"{$jabatan}\" salah kelompok.");
        }
    }

    /**
     * Jabatan tak dikenal jatuh ke "pekerja", bukan "pimpinan".
     *
     * Arah bawaannya disengaja. Yang jatuh ke kuesioner pekerja hanya
     * kehilangan sebagian pertanyaan; yang jatuh ke kuesioner pimpinan
     * MENCEMARI penilaian kepemimpinan dengan jawaban orang yang tidak
     * memimpin siapa pun.
     */
    #[Test]
    public function jabatan_tak_dikenal_jatuh_ke_pekerja(): void
    {
        $this->assertSame('pekerja', TpkkpKuesioner::kategoriUntukJabatan('Zzzz Qqqq'));
        $this->assertNull(TpkkpKuesioner::kategoriUntukJabatan(''),
            'Jabatan kosong belum dapat ditentukan kelompoknya — null, bukan tebakan.');
    }

    /* ═══════════ penegakan di sisi server ═══════════ */

    /**
     * Alamat yang menyebut kategori salah DILURUSKAN, bukan diikuti.
     *
     * Tautan lama yang beredar di grup pesan menyebut kategori langsung.
     * Menolaknya membuat orang berhenti mengisi; mengikutinya
     * mengembalikan bug-nya. Yang benar: dialihkan ke kuesioner yang
     * sesuai jabatannya.
     */
    #[Test]
    public function alamat_kategori_yang_keliru_dialihkan(): void
    {
        $this->get("/q/{$this->token}/pimpinan?jabatan=".urlencode('Operator'))
            ->assertRedirect();

        $this->get("/q/{$this->token}/pekerja?jabatan=".urlencode('Operator'))
            ->assertOk();
    }

    /**
     * Kiriman dengan kategori salah disimpan pada kategori yang BENAR.
     *
     * Ini penjagaan yang sesungguhnya. Layar boleh dilewati, alamat
     * boleh disusun tangan — yang menentukan isi basis data baris di
     * controller, bukan yang di peramban.
     */
    #[Test]
    public function kiriman_disimpan_pada_kategori_sesuai_jabatan(): void
    {
        $kode = TpkkpKuesioner::kodeSah('pekerja');

        $this->post("/q/{$this->token}/pimpinan", [
            'jabatan' => 'Operator',
            'answers' => [$kode[0] => 4],
        ])->assertRedirect();

        $r = TpkkpResponse::withoutGlobalScopes()->latest('id')->first();

        $this->assertNotNull($r, 'Respons tidak tersimpan sama sekali.');
        $this->assertSame('pekerja', $r->cat,
            'Jawaban operator tersimpan sebagai kuesioner pimpinan — '
            .'penilaian kepemimpinan tercemar oleh orang yang tidak memimpin siapa pun.');
    }

    /** Halaman pembuka memberi tahu kuesioner mana yang akan diisi. */
    #[Test]
    public function halaman_pembuka_membawa_kelompok_jabatan(): void
    {
        $this->get("/q/{$this->token}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Kuesioner/Mulai')
                ->has('kelompok.pimpinan.positions')
                ->has('kelompok.pekerja.positions')
                ->has('jumlahButir')
                ->etc());
    }
}
