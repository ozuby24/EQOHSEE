<?php

namespace Tests\Feature;

use App\Models\{Company, KoObject, KoUjiKelayakan, KoUnitMaster, User};
use App\Support\{Alur, MasterUnitSpip};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji kelayakan SPIP — riwayat, dan pengaruhnya pada status unit.
 *
 * Yang diuji di sini bukan tampilannya melainkan satu pertanyaan yang
 * dipakai memutuskan apakah sebuah alat boleh dioperasikan: sampai
 * kapan sertifikasinya berlaku. Salahnya punya dua bentuk yang
 * sama-sama berat — menghentikan alat yang sebenarnya sah, dan
 * membiarkan berjalan alat yang sertifikasinya sudah habis.
 */
class KoUjiKelayakanTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $ohse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->c = Company::create(['name' => 'PT Uji SPIP', 'doc_no_prefix' => 'US']);

        $this->ohse = User::factory()->create([
            'is_admin' => true, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);

        $this->actingAs($this->ohse);
    }

    private function unit(array $atribut = []): KoObject
    {
        return KoObject::create($atribut + [
            'company_id'     => $this->c->id,
            'kode'           => 'KO-'.fake()->unique()->numerify('###'),
            'nama'           => 'Crane Workshop',
            'kategori'       => 'Peralatan',
            'kritikalitas'   => 'Tinggi',
            'status_operasi' => 'Aktif',
            'interval_tahun' => 1,
        ]);
    }

    /** @return KoUjiKelayakan */
    private function uji(KoObject $o, array $atribut = []): KoUjiKelayakan
    {
        return KoUjiKelayakan::create($atribut + [
            'ko_object_id' => $o->id,
            'company_id'   => $o->company_id,
            'tgl_inspeksi' => now()->subMonth(),
            'tgl_expired'  => now()->addMonths(11),
            'hasil'        => 'Layak',
        ]);
    }

    /* ═══════════ daftar acuan ═══════════ */

    /**
     * Master jenis unit ditanam sebagai MILIK BERSAMA.
     *
     * Bila ia lahir bermilik satu perusahaan, perusahaan lain membuka
     * menu jenis unit yang kosong — tanpa galat, dan tanpa cara
     * menebak sebabnya.
     */
    public function test_master_jenis_unit_milik_bersama(): void
    {
        MasterUnitSpip::tanam();

        $this->assertGreaterThan(30, KoUnitMaster::withoutGlobalScopes()->count());

        $this->assertSame(0,
            KoUnitMaster::withoutGlobalScopes()->whereNotNull('company_id')->count(),
            'Ada baris master yang lahir bermilik perusahaan.');
    }

    /** Menanamnya dua kali tidak melahirkan baris kedua. */
    public function test_menanam_dua_kali_tidak_menggandakan(): void
    {
        MasterUnitSpip::tanam();
        $pertama = KoUnitMaster::withoutGlobalScopes()->count();

        MasterUnitSpip::tanam();

        $this->assertSame($pertama, KoUnitMaster::withoutGlobalScopes()->count());
    }

    /**
     * Jenis yang masih dipakai dinonaktifkan, bukan dihapus.
     *
     * Menghapusnya memutus tautan unit-unit yang memakainya menjadi
     * NULL — dan unit itu diam-diam kembali tanpa jenis, persis keadaan
     * yang hendak dihilangkan daftar ini.
     */
    public function test_jenis_yang_dipakai_dinonaktifkan_bukan_dihapus(): void
    {
        $jenis = KoUnitMaster::create([
            'company_id' => $this->c->id, 'kode' => 'DT',
            'unit' => 'Dump Truck', 'interval_tahun' => 1,
        ]);

        $o = $this->unit(['ko_unit_master_id' => $jenis->id]);

        $this->delete(route('ko.unit.hapus', $jenis))->assertRedirect();

        $this->assertNotNull($jenis->fresh(), 'Jenis yang masih dipakai ikut terhapus.');
        $this->assertFalse($jenis->fresh()->aktif);
        $this->assertSame($jenis->id, $o->fresh()->ko_unit_master_id,
            'Tautan unit ke jenisnya putus.');
    }

    /** Yang belum dipakai memang boleh dihapus. */
    public function test_jenis_yang_belum_dipakai_dapat_dihapus(): void
    {
        $jenis = KoUnitMaster::create([
            'company_id' => $this->c->id, 'kode' => 'XX',
            'unit' => 'Belum dipakai', 'interval_tahun' => 1,
        ]);

        $this->delete(route('ko.unit.hapus', $jenis))->assertRedirect();

        $this->assertNull($jenis->fresh());
    }

    /* ═══════════ riwayat, bukan satu tanggal ═══════════ */

    /**
     * Inilah pokok perubahannya.
     *
     * Dua uji pada unit yang sama sama-sama tersimpan. Pada bentuk lama
     * uji kedua menimpa tanggal sertifikasi dan yang pertama hilang —
     * dan ketika inspektur meminta bukti tiga tahun berturut-turut,
     * yang dapat ditunjukkan hanya yang terakhir.
     */
    public function test_dua_uji_pada_unit_yang_sama_sama_sama_tersimpan(): void
    {
        $o = $this->unit();

        $this->uji($o, ['tgl_inspeksi' => now()->subYears(2), 'tgl_expired' => now()->subYear()]);
        $this->uji($o, ['tgl_inspeksi' => now()->subMonth(),  'tgl_expired' => now()->addMonths(11)]);

        $this->assertSame(2, $o->uji()->count());
    }

    /**
     * Yang dipakai sebagai dasar adalah uji yang SUDAH DISETUJUI.
     *
     * Bila draf ikut dihitung, siapa pun dapat memperpanjang izin
     * operasi sebuah alat hanya dengan mengisi formulir.
     */
    public function test_uji_draf_bukan_dasar_sertifikasi(): void
    {
        $o = $this->unit();

        $this->uji($o);   // draf

        $this->assertNull($o->ujiTerakhir(), 'Uji draf dipakai sebagai uji terakhir.');
    }

    /* ═══════════ pengaruh pada unitnya ═══════════ */

    /**
     * Uji yang disetujui memperbarui sertifikasi unitnya.
     *
     * Tanpa penyatuan ini, mencatat uji baru tidak mengubah status unit
     * sama sekali — orang mengisi formulirnya, melihat statusnya tetap
     * "Kadaluarsa", dan menyimpulkan fiturnya rusak.
     */
    public function test_menyetujui_uji_memperbarui_sertifikasi_unit(): void
    {
        $o = $this->unit(['tgl_sertifikasi' => now()->subYears(3)]);

        $u = $this->uji($o, [
            'tgl_inspeksi' => now()->subDays(5),
            'tgl_expired'  => now()->addDays(360),
            'nomor'        => 'UK/2026/0001',
            'lembaga'      => 'Balai Pengujian',
        ]);
        $u->ajukan();

        $peninjau = User::factory()->create([
            'is_admin' => true, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);
        $this->actingAs($peninjau);

        $this->post(route('ko.uji.tinjau', $u), ['aksi' => 'setujui'])->assertRedirect();

        $o->refresh();

        $this->assertSame(now()->subDays(5)->toDateString(), $o->tgl_sertifikasi->toDateString());
        $this->assertSame('UK/2026/0001', $o->no_sertifikat);
        $this->assertSame('Balai Pengujian', $o->lembaga_uji);
    }

    /**
     * Hasil "Tidak Layak" TIDAK memperbarui sertifikasi.
     *
     * Memperbaruinya akan memperpanjang izin operasi alat yang baru saja
     * dinyatakan tidak layak — kegagalan yang paling berbahaya di
     * seluruh modul ini, sebab ia menghasilkan status hijau.
     */
    public function test_hasil_tidak_layak_tidak_memperbarui_sertifikasi(): void
    {
        $lama = now()->subYears(3);
        $o = $this->unit(['tgl_sertifikasi' => $lama]);

        $u = $this->uji($o, ['hasil' => 'Tidak Layak', 'tgl_inspeksi' => now()->subDay()]);
        $u->ajukan();

        $peninjau = User::factory()->create([
            'is_admin' => true, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);
        $this->actingAs($peninjau);

        $this->post(route('ko.uji.tinjau', $u), ['aksi' => 'setujui'])->assertRedirect();

        $this->assertSame($lama->toDateString(), $o->fresh()->tgl_sertifikasi->toDateString(),
            'Uji "Tidak Layak" justru memperpanjang sertifikasi unitnya.');
    }

    /**
     * Menyetujui uji LAMA yang tertunda tidak memundurkan sertifikasi.
     *
     * Berkas yang menyusul berbulan-bulan adalah hal biasa. Bila
     * persetujuannya menimpa begitu saja, uji yang lebih baru dan sudah
     * disetujui akan tergantikan oleh yang lebih tua.
     */
    public function test_menyetujui_uji_lama_tidak_memundurkan_sertifikasi(): void
    {
        $o = $this->unit();

        $lama = $this->uji($o, [
            'tgl_inspeksi' => now()->subYear(), 'tgl_expired' => now(),
        ]);

        $baru = $this->uji($o, [
            'tgl_inspeksi' => now()->subMonth(), 'tgl_expired' => now()->addMonths(11),
        ]);

        $peninjau = User::factory()->create([
            'is_admin' => true, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);

        /* Yang baru disetujui lebih dulu. */
        $baru->ajukan();
        $this->actingAs($peninjau);
        $this->post(route('ko.uji.tinjau', $baru), ['aksi' => 'setujui']);

        $this->assertSame(now()->subMonth()->toDateString(),
            $o->fresh()->tgl_sertifikasi->toDateString(), 'Kontrol gagal.');

        /* Lalu yang lama menyusul. */
        $this->actingAs($this->ohse);
        $lama->ajukan();
        $this->actingAs($peninjau);
        $this->post(route('ko.uji.tinjau', $lama), ['aksi' => 'setujui']);

        $this->assertSame(now()->subMonth()->toDateString(),
            $o->fresh()->tgl_sertifikasi->toDateString(),
            'Uji lama yang menyusul memundurkan sertifikasi unitnya.');
    }

    /* ═══════════ layak bersyarat ═══════════ */

    /**
     * "Layak Bersyarat" tanpa syarat tertulis ditolak.
     *
     * Ia izin operasi penuh yang menyamar sebagai izin bersyarat, dan
     * yang membacanya di lapangan tidak punya cara tahu apa yang harus
     * dijaga.
     */
    public function test_layak_bersyarat_wajib_menyebut_syaratnya(): void
    {
        $o = $this->unit();

        $this->post(route('ko.uji.simpan'), [
            'ko_object_id' => $o->id,
            'tgl_inspeksi' => now()->toDateString(),
            'hasil'        => 'Layak Bersyarat',
        ])->assertSessionHasErrors('syarat');

        $this->assertSame(0, KoUjiKelayakan::count());

        /* Kontrol: dengan syaratnya, diterima. */
        $this->post(route('ko.uji.simpan'), [
            'ko_object_id' => $o->id,
            'tgl_inspeksi' => now()->toDateString(),
            'hasil'        => 'Layak Bersyarat',
            'syarat'       => 'Beban maksimum dibatasi 50 ton.',
        ])->assertRedirect();

        $this->assertSame(1, KoUjiKelayakan::count());
    }

    /** "Layak Bersyarat" tetap terhitung lolos — ia bukan penolakan. */
    public function test_layak_bersyarat_terhitung_lolos(): void
    {
        $o = $this->unit();

        $u = $this->uji($o, ['hasil' => 'Layak Bersyarat', 'syarat' => 'Beban dibatasi.']);

        $this->assertTrue($u->lolos());
        $this->assertFalse($this->uji($o, ['hasil' => 'Tidak Layak'])->lolos());
    }

    /* ═══════════ pengisian otomatis ═══════════ */

    /**
     * Tanggal kadaluarsa diusulkan dari interval jenis unitnya.
     *
     * Uji tanpa tanggal habis tidak pernah muncul di daftar yang akan
     * jatuh tempo — dan alat yang tidak pernah muncul tidak pernah
     * diuji ulang.
     */
    public function test_tanggal_kadaluarsa_diisi_dari_interval_jenisnya(): void
    {
        $jenis = KoUnitMaster::create([
            'company_id' => $this->c->id, 'kode' => 'TNK',
            'unit' => 'Tangki Timbun', 'interval_tahun' => 3,
        ]);

        $o = $this->unit(['ko_unit_master_id' => $jenis->id, 'interval_tahun' => 1]);

        $this->post(route('ko.uji.simpan'), [
            'ko_object_id' => $o->id,
            'tgl_inspeksi' => '2026-01-10',
            'hasil'        => 'Layak',
        ])->assertRedirect();

        $this->assertSame('2029-01-10',
            KoUjiKelayakan::first()->tgl_expired->toDateString(),
            'Interval jenis unit tidak dipakai; jatuh ke interval objeknya.');
    }

    /**
     * Merk dan nomor seri DISALIN, bukan dirujuk.
     *
     * Unit yang kemudian dikoreksi datanya tidak boleh mengubah bunyi
     * sertifikat yang sudah terbit dan sudah diperiksa inspektur.
     */
    public function test_merk_dan_seri_disalin_bukan_dirujuk(): void
    {
        $o = $this->unit(['merk' => 'Kato', 'serial_number' => 'SN-001']);

        $this->post(route('ko.uji.simpan'), [
            'ko_object_id' => $o->id,
            'tgl_inspeksi' => now()->toDateString(),
            'hasil'        => 'Layak',
        ])->assertRedirect();

        $u = KoUjiKelayakan::first();

        $this->assertSame('Kato',   $u->merk);
        $this->assertSame('SN-001', $u->nomor_seri);

        $o->update(['merk' => 'Tadano', 'serial_number' => 'SN-999']);

        $this->assertSame('Kato',   $u->fresh()->merk,
            'Mengubah unit ikut mengubah sertifikat yang sudah terbit.');
        $this->assertSame('SN-001', $u->fresh()->nomor_seri);
    }

    /* ═══════════ selisih yang ditampilkan ═══════════ */

    /** Sertifikasi yang disunting tangan tanpa dasar uji terlihat. */
    public function test_selisih_sertifikasi_terdeteksi(): void
    {
        $o = $this->unit();

        $u = $this->uji($o, [
            'tgl_inspeksi' => now()->subMonth(), 'tgl_expired' => now()->addMonths(11),
        ]);
        KoUjiKelayakan::whereKey($u->id)->update(['status' => Alur::DISETUJUI]);

        $o->update([
            'tgl_sertifikasi' => now()->subMonth(),
            'interval_tahun'  => 1,
        ]);

        $this->assertFalse($o->fresh()->sertifikasiBerselisih(), 'Kontrol gagal: cocok pun terbaca selisih.');

        /* Disunting tangan menjadi jauh lebih panjang dari dasar ujinya. */
        $o->update(['interval_tahun' => 5]);

        $this->assertTrue($o->fresh()->sertifikasiBerselisih());
    }

    public function test_halaman_jenis_unit_dan_uji_terbuka(): void
    {
        $this->get(route('ko.unit'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Ko/Unit'));

        $this->get(route('ko.uji'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Ko/Uji'));
    }
}
