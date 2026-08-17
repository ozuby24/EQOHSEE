<?php

namespace Tests\Feature;

use App\Models\{BiayaAkun, Company, Document, GudangLokasi, HazardReport,
                Inspection, Paspor, Procedure, User};
use App\Support\LingkupLintas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Batas data sepanjang garis IUP → IUJP.
 *
 * Pemegang IUP dan mitra IUJP di bawahnya bukan dua perusahaan yang
 * tidak berhubungan, jadi beberapa tabel sengaja menembus batasnya.
 * Berkas ini menjaga bahwa yang menembus HANYA yang dimaksudkan, dan
 * hanya ke arah yang dimaksudkan.
 *
 * SUSUNAN UJINYA
 *
 *          IUP INDUK
 *          ├── IUJP A   (mitra tambang)
 *          └── IUJP B   (mitra hauling)
 *      IUP LAIN         (tidak berhubungan sama sekali)
 *
 * Yang paling penting dijaga di sini adalah arah MENYAMPING: IUJP A dan
 * IUJP B adalah dua perusahaan yang bersaing memperebutkan pekerjaan
 * yang sama. Membuka laporan bahaya atau daftar kompetensi salah
 * satunya kepada yang lain bukan kelonggaran administratif melainkan
 * menyerahkan data komersial pesaingnya.
 *
 * TIAP UJI SERANGAN PUNYA KENDALI. Tanpa itu, "tidak terlihat" dapat
 * berarti "penyaringnya bekerja" ATAU "barisnya memang tidak pernah
 * tersimpan" — dan keduanya menghasilkan uji hijau yang sama. Kesalahan
 * itu sudah pernah terjadi pada berkas uji lain di repo ini.
 */
class LingkupIupIujpTest extends TestCase
{
    use RefreshDatabase;

    private Company $iup;
    private Company $a;
    private Company $b;
    private Company $luar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->iup  = Company::create(['name' => 'PT IUP Induk', 'doc_no_prefix' => 'IUP',
                                       'izin_type' => 'IUP']);
        $this->a    = Company::create(['name' => 'PT Mitra A', 'doc_no_prefix' => 'MTA',
                                       'izin_type' => 'IUJP', 'parent_id' => $this->iup->id]);
        $this->b    = Company::create(['name' => 'PT Mitra B', 'doc_no_prefix' => 'MTB',
                                       'izin_type' => 'IUJP', 'parent_id' => $this->iup->id]);
        $this->luar = Company::create(['name' => 'PT Tak Berhubungan', 'doc_no_prefix' => 'TBH',
                                       'izin_type' => 'IUP']);
    }

    /** Masuk sebagai pengguna BIASA — admin memang menjangkau semuanya. */
    private function masuk(Company $c): void
    {
        $this->actingAs(User::factory()->create([
            'is_admin' => false, 'company_id' => $c->id, 'email_verified_at' => now(),
        ]));
    }

    private function bahaya(Company $c, string $uraian): void
    {
        HazardReport::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'kode' => 'HZ-'.$c->id.'-'.substr(md5($uraian), 0, 5),
            'pelapor_nama' => 'Pelapor', 'tanggal' => now()->toDateString(),
            'lokasi' => 'Area', 'risiko' => 'Tinggi', 'kategori' => 'Unsafe Condition',
            'deskripsi' => $uraian, 'status' => 'Open',
        ]);
    }

    private function prosedur(Company $c, string $judul): void
    {
        Procedure::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'code' => 'SOP-'.$c->id, 'title' => $judul,
        ]);
    }

    /* ═══════════ ke bawah: IUP membaca kinerja mitranya ═══════════ */

    public function test_iup_melihat_laporan_bahaya_mitranya(): void
    {
        $this->bahaya($this->iup, 'Milik IUP');
        $this->bahaya($this->a,   'Milik Mitra A');
        $this->bahaya($this->b,   'Milik Mitra B');
        $this->bahaya($this->luar,'Milik luar');

        $this->masuk($this->iup);

        $terlihat = HazardReport::pluck('deskripsi')->sort()->values()->all();

        $this->assertSame(['Milik IUP', 'Milik Mitra A', 'Milik Mitra B'], $terlihat,
            'IUP harus melihat kinerja seluruh mitranya, dan hanya mitranya.');
    }

    public function test_iup_melihat_inspeksi_dan_kompetensi_mitranya(): void
    {
        Inspection::withoutGlobalScopes()->create([
            'company_id' => $this->a->id, 'kode' => 'INS-A', 'judul' => 'Inspeksi Mitra A',
            'tanggal' => now()->toDateString(), 'status' => 'Selesai',
        ]);
        Paspor::withoutGlobalScopes()->create([
            'company_id' => $this->a->id, 'nama' => 'Pekerja Mitra A',
        ]);

        $this->masuk($this->iup);

        $this->assertSame(['Inspeksi Mitra A'], Inspection::pluck('judul')->all());
        $this->assertSame(['Pekerja Mitra A'],  Paspor::pluck('nama')->all());
    }

    /* ═══════════ ke atas: mitra membaca acuan induknya ═══════════ */

    public function test_mitra_melihat_prosedur_dan_dokumen_induknya(): void
    {
        $this->prosedur($this->iup, 'Prosedur Induk');
        $this->prosedur($this->a,   'Prosedur Mitra A');

        Document::withoutGlobalScopes()->create([
            'company_id' => $this->iup->id, 'kode' => 'DOK-IUP',
            'judul' => 'Dokumen Induk', 'jenis' => 'Prosedur', 'status' => 'berlaku',
        ]);

        $this->masuk($this->a);

        $this->assertSame(['Prosedur Induk', 'Prosedur Mitra A'],
            Procedure::pluck('title')->sort()->values()->all());
        $this->assertSame(['Dokumen Induk'], Document::pluck('judul')->all());
    }

    /* ═══════════ menyamping: TIDAK PERNAH ═══════════ */

    /**
     * Inilah yang paling penting dijaga.
     *
     * Dua kontraktor di bawah satu IUP bersaing memperebutkan pekerjaan
     * yang sama. Kendalinya ada di baris terakhir: kalau Mitra A bahkan
     * tidak melihat laporannya sendiri, "tidak melihat milik B" tidak
     * membuktikan apa pun.
     */
    public function test_mitra_tidak_melihat_data_sesama_mitra(): void
    {
        $this->bahaya($this->a, 'Milik Mitra A');
        $this->bahaya($this->b, 'Milik Mitra B');

        $this->masuk($this->a);

        $terlihat = HazardReport::pluck('deskripsi')->all();

        $this->assertNotContains('Milik Mitra B', $terlihat,
            'Mitra melihat laporan bahaya pesaingnya di bawah induk yang sama.');

        // KENDALI: penyaringnya memang bekerja, bukan tabelnya yang kosong.
        $this->assertContains('Milik Mitra A', $terlihat,
            'Kendali gagal: Mitra A tidak melihat laporannya sendiri, '
            .'jadi uji di atas tidak membuktikan apa pun.');
    }

    public function test_mitra_tidak_melihat_prosedur_sesama_mitra(): void
    {
        $this->prosedur($this->a, 'Prosedur Mitra A');
        $this->prosedur($this->b, 'Prosedur Mitra B');

        $this->masuk($this->a);

        $terlihat = Procedure::pluck('title')->all();

        $this->assertNotContains('Prosedur Mitra B', $terlihat);
        $this->assertContains('Prosedur Mitra A', $terlihat, 'Kendali gagal.');
    }

    /* ═══════════ arahnya satu, bukan dua ═══════════ */

    /**
     * Prosedur dibuka KE BAWAH saja: mitra membaca punya induk, induk
     * TIDAK otomatis membaca punya mitra.
     *
     * Kalau keduanya dibuka, daftar prosedur pemegang IUP akan tercampur
     * prosedur internal setiap kontraktornya — dan daftar induk dokumen
     * yang diperiksa auditor berisi dokumen milik perusahaan lain.
     */
    public function test_induk_tidak_ikut_melihat_prosedur_mitranya(): void
    {
        $this->prosedur($this->iup, 'Prosedur Induk');
        $this->prosedur($this->a,   'Prosedur Mitra A');

        $this->masuk($this->iup);

        $terlihat = Procedure::pluck('title')->all();

        $this->assertNotContains('Prosedur Mitra A', $terlihat,
            'Prosedur dibuka dua arah; daftar induk dokumen akan tercampur milik mitra.');
        $this->assertContains('Prosedur Induk', $terlihat, 'Kendali gagal.');
    }

    /**
     * Laporan bahaya dibuka KE ATAS saja: induk membaca punya mitra,
     * mitra TIDAK membaca punya induk.
     */
    public function test_mitra_tidak_ikut_melihat_laporan_bahaya_induknya(): void
    {
        $this->bahaya($this->iup, 'Milik IUP');
        $this->bahaya($this->a,   'Milik Mitra A');

        $this->masuk($this->a);

        $terlihat = HazardReport::pluck('deskripsi')->all();

        $this->assertNotContains('Milik IUP', $terlihat);
        $this->assertContains('Milik Mitra A', $terlihat, 'Kendali gagal.');
    }

    /* ═══════════ yang tidak didaftarkan tetap tertutup ═══════════ */

    /**
     * Biaya dan gudang TIDAK menembus batas ke arah mana pun.
     *
     * Yang diuji bukan sekadar "belum didaftarkan" melainkan bahwa
     * pelebarannya memang per tabel, bukan berlaku menyeluruh begitu
     * hubungan induk-anak ada.
     */
    public function test_biaya_dan_gudang_tidak_menembus_batas(): void
    {
        foreach ([$this->iup, $this->a] as $c) {
            BiayaAkun::withoutGlobalScopes()->create([
                'company_id' => $c->id, 'kode' => 'AK-'.$c->id, 'nama' => 'Akun '.$c->name,
            ]);
            GudangLokasi::withoutGlobalScopes()->create([
                'company_id' => $c->id, 'kode' => 'GD-'.$c->id, 'nama' => 'Gudang '.$c->name,
            ]);
        }

        // Induk tidak melihat milik mitra.
        $this->masuk($this->iup);
        $this->assertSame(['Akun PT IUP Induk'],   BiayaAkun::pluck('nama')->all());
        $this->assertSame(['Gudang PT IUP Induk'], GudangLokasi::pluck('nama')->all());

        // Mitra tidak melihat milik induk.
        $this->masuk($this->a);
        $this->assertSame(['Akun PT Mitra A'],   BiayaAkun::pluck('nama')->all());
        $this->assertSame(['Gudang PT Mitra A'], GudangLokasi::pluck('nama')->all());
    }

    /* ═══════════ perusahaan tak berhubungan ═══════════ */

    public function test_perusahaan_luar_tidak_melihat_apa_pun_dari_keluarga_iup(): void
    {
        $this->bahaya($this->iup, 'Milik IUP');
        $this->bahaya($this->a,   'Milik Mitra A');
        $this->bahaya($this->luar,'Milik luar');

        $this->prosedur($this->iup, 'Prosedur Induk');

        $this->masuk($this->luar);

        $this->assertSame(['Milik luar'], HazardReport::pluck('deskripsi')->all());
        $this->assertSame([], Procedure::pluck('title')->all());
    }

    /**
     * Pelebaran berhenti pada SATU tingkat.
     *
     * Cucu tidak membaca kakeknya, dan kakek tidak membaca cucunya.
     * Rantai yang tidak berbatas membuat satu perusahaan puncak
     * membaca seluruh pemasangan hanya dengan menambah tingkat.
     */
    public function test_pelebaran_hanya_satu_tingkat(): void
    {
        $cucu = Company::create([
            'name' => 'PT Sub Mitra', 'doc_no_prefix' => 'SBM',
            'izin_type' => 'IUJP', 'parent_id' => $this->a->id,
        ]);

        $this->prosedur($this->iup, 'Prosedur Induk');
        $this->bahaya($cucu, 'Milik cucu');
        $this->bahaya($this->a, 'Milik Mitra A');

        // Cucu tidak membaca prosedur kakeknya.
        $this->masuk($cucu);
        $this->assertSame([], Procedure::pluck('title')->all(),
            'Cucu membaca prosedur kakeknya; rantainya tidak berbatas.');

        // Kakek tidak membaca laporan cucunya.
        $this->masuk($this->iup);
        $this->assertNotContains('Milik cucu', HazardReport::pluck('deskripsi')->all(),
            'Kakek membaca laporan cucunya; rantainya tidak berbatas.');
    }

    /* ═══════════ daftarnya sendiri ═══════════ */

    /**
     * Daftar tabel yang menembus batas sengaja PENDEK.
     *
     * Uji ini bukan tentang benar-salahnya isi daftar, melainkan agar
     * penambahannya tidak terjadi diam-diam: tabel yang bertambah di
     * sini membuka data lintas perusahaan, dan itu keputusan yang harus
     * diambil sadar — bukan akibat sampingan menambah modul.
     */
    public function test_daftar_tabel_lintas_perusahaan_tetap_sempit(): void
    {
        $this->assertSame(
            ['hazard_reports', 'inspections', 'paspor'],
            LingkupLintas::KE_INDUK);

        $this->assertSame(['procedures', 'documents'], LingkupLintas::KE_ANAK);

        /* Tidak ada tabel yang dibuka DUA arah sekaligus. Yang dibuka
           dua arah menghapus perbedaan antara induk dan anak, dan
           dengan itu menghapus alasan daftar ini dipisah. */
        $this->assertSame([],
            array_intersect(LingkupLintas::KE_INDUK, LingkupLintas::KE_ANAK));
    }
}
