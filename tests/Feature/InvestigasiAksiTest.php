<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Investigasi\{Bukti, Insiden, Investigasi, Taksonomi, Tindakan};
use App\Models\User;
use App\Support\Berkas;
use App\Support\Investigasi\{MasterInvestigasi, NomorInvestigasi};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Aksi tulis pada ruang kerja investigasi.
 *
 * Yang diuji di sini bukan formulirnya melainkan PENJAGAANNYA. Berkas
 * investigasi kecelakaan dapat diminta Inspektur Tambang, dan tiga
 * kelemahan di bawah sama-sama tidak menimbulkan galat sama sekali:
 * bukti yang dihapus sesudah kesimpulan ditulis, berkas tertutup yang
 * diam-diam berubah isinya, dan tindakan perbaikan yang diverifikasi
 * oleh orang yang mengerjakannya sendiri.
 */
class InvestigasiAksiTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;
    private User $admin;
    private Investigasi $inv;

    protected function setUp(): void
    {
        parent::setUp();

        MasterInvestigasi::pasang();

        $this->c = Company::create(['name' => 'PT Uji Aksi', 'doc_no_prefix' => 'UAK']);

        $this->admin = User::factory()->create([
            'is_admin' => true, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);

        $insiden = Insiden::withoutGlobalScopes()->create([
            'company_id' => $this->c->id,
            'no_insiden' => NomorInvestigasi::terbitkan(NomorInvestigasi::INSIDEN),
            'judul' => 'Unit menabrak tanggul', 'tanggal_kejadian' => '2026-08-19',
            'level_investigasi' => 'L3',
        ]);

        $this->inv = Investigasi::withoutGlobalScopes()->create([
            'no_investigasi' => NomorInvestigasi::terbitkan(NomorInvestigasi::INVESTIGASI),
            'insiden_id' => $insiden->id,
        ]);

        $this->actingAs($this->admin);
    }

    private function jalur(string $sisa = ''): string
    {
        return '/investigasi/berkas/'.$this->inv->id.($sisa ? '/'.$sisa : '');
    }

    private function bukti(array $ganti = []): Bukti
    {
        return $this->inv->bukti()->create($ganti + [
            'no_bukti' => NomorInvestigasi::terbitkan(NomorInvestigasi::BUKTI),
            'jenis' => 'foto', 'judul' => 'Foto posisi akhir unit',
        ]);
    }

    /* ═══════════ 1 · bukti ═══════════ */

    #[Test]
    public function bukti_terunggah_disidik_jarinya(): void
    {
        Storage::fake(Berkas::TERTUTUP);

        $this->post($this->jalur('bukti'), [
            'jenis' => 'foto', 'judul' => 'Foto bekas ban',
            'berkas' => UploadedFile::fake()->create('ban.jpg', 40, 'image/jpeg'),
        ])->assertSessionHasNoErrors();

        $b = $this->inv->bukti()->latest('id')->first();

        $this->assertNotNull($b->berkas);
        $this->assertNotNull($b->sha256, 'Bukti tersimpan tanpa sidik jari — penggantian isinya tidak akan terlihat.');
        $this->assertSame(64, strlen($b->sha256));
        Storage::disk(Berkas::TERTUTUP)->assertExists($b->berkas);
    }

    /**
     * Bukti yang sudah dikunci TIDAK dapat dihapus.
     *
     * Inilah seluruh gunanya kunci itu. Pada berkas yang dapat diminta
     * Inspektur Tambang, kemampuan menghapus bukti sesudah kesimpulan
     * ditulis adalah lubang yang tidak dapat dijelaskan kepada siapa pun.
     */
    #[Test]
    public function bukti_terkunci_tidak_dapat_dihapus(): void
    {
        $b = $this->bukti();

        $this->post($this->jalur("bukti/{$b->id}/kunci"))->assertSessionHasNoErrors();

        $b->refresh();
        $this->assertTrue($b->dikunci);
        $this->assertNotNull($b->dikunci_pada);
        $this->assertSame($this->admin->id, $b->dikunci_oleh);

        $this->postJson($this->jalur("bukti/{$b->id}/hapus"))->assertStatus(422);

        $this->assertNotNull(Bukti::withoutGlobalScopes()->find($b->id),
            'Bukti terkunci berhasil dihapus.');
    }

    #[Test]
    public function bukti_belum_terkunci_masih_dapat_dihapus(): void
    {
        $b = $this->bukti();

        $this->post($this->jalur("bukti/{$b->id}/hapus"))->assertSessionHasNoErrors();

        $this->assertNull(Bukti::withoutGlobalScopes()->find($b->id));
    }

    /** Bukti milik investigasi lain tidak dapat disentuh dari sini. */
    #[Test]
    public function bukti_investigasi_lain_tidak_dapat_dikunci(): void
    {
        $lain = Investigasi::withoutGlobalScopes()->create([
            'no_investigasi' => NomorInvestigasi::terbitkan(NomorInvestigasi::INVESTIGASI),
            'insiden_id' => $this->inv->insiden_id,
        ]);

        $b = $lain->bukti()->create([
            'no_bukti' => NomorInvestigasi::terbitkan(NomorInvestigasi::BUKTI),
            'jenis' => 'foto', 'judul' => 'Bukti berkas lain',
        ]);

        $this->post($this->jalur("bukti/{$b->id}/kunci"))->assertNotFound();

        $this->assertFalse((bool) $b->refresh()->dikunci);
    }

    /* ═══════════ 2 · berkas tertutup ═══════════ */

    /**
     * Investigasi yang sudah DITUTUP tidak menerima perubahan apa pun.
     *
     * Diuji atas banyak rute sekaligus, bukan atas satu: penjagaannya
     * memang satu tempat, tetapi yang membuatnya benar adalah bahwa
     * SETIAP rute melewatinya — dan rute yang lupa memanggilnya tidak
     * menimbulkan galat, hanya berkas tertutup yang diam-diam berubah
     * isinya sesudah ditandatangani.
     */
    #[Test]
    public function berkas_tertutup_menolak_seluruh_perubahan(): void
    {
        $b = $this->bukti();
        $this->inv->update(['status' => 'ditutup', 'ditutup_pada' => now()]);

        $rute = [
            ['keterangan',                ['tujuan' => 'diubah diam-diam']],
            ['tim',                       ['user_id' => $this->admin->id]],
            ['kronologi',                 ['peristiwa' => 'peristiwa baru']],
            ['bukti',                     ['jenis' => 'foto', 'judul' => 'bukti susulan']],
            ["bukti/{$b->id}/kunci",      []],
            ["bukti/{$b->id}/hapus",      []],
            ['akar',                      ['uraian' => 'akar susulan']],
            ['temuan',                    ['uraian' => 'temuan susulan']],
            ['tahap/maju',                []],
            ['tahap/mundur',              []],
        ];

        foreach ($rute as [$sisa, $isi]) {
            $this->postJson($this->jalur($sisa), $isi)->assertStatus(422,
                "Rute {$sisa} menerima perubahan pada investigasi yang sudah ditutup.");
        }

        /* Dan tidak satu pun kiriman itu meninggalkan jejak. Memeriksa
           status HTTP saja tidak cukup: rute yang menolak SESUDAH
           menulis tetap memulangkan 422. */
        $segar = $this->inv->fresh();

        $this->assertSame(1, $segar->bukti()->count(), 'Bukti bertambah pada berkas tertutup.');
        $this->assertSame(0, $segar->tim()->count());
        $this->assertSame(0, $segar->kronologi()->count());
        $this->assertSame(0, $segar->akar()->count());
        $this->assertSame(0, $segar->temuan()->count());
        $this->assertSame('perencanaan', $segar->tahap, 'Tahap bergeser pada berkas tertutup.');
        $this->assertFalse((bool) $segar->bukti()->first()->dikunci);
    }

    /**
     * Menerbitkan pembelajaran TETAP boleh pada berkas tertutup.
     *
     * Satu-satunya yang boleh, dan itu disengaja: yang dibaca site lain
     * adalah paragraf itu, dan menutup berkasnya lebih dahulu adalah
     * urutan yang wajar. Memaksa pembelajaran terbit sebelum penutupan
     * berarti ia ditulis sebelum kesimpulannya matang.
     */
    #[Test]
    public function pembelajaran_tetap_boleh_terbit_pada_berkas_tertutup(): void
    {
        $this->inv->update(['status' => 'ditutup', 'ditutup_pada' => now()]);

        $this->post($this->jalur('pembelajaran'), [
            'judul' => 'Interval perawatan rem mengikuti gradien jalan',
            'ringkasan' => 'Kampas aus melewati batas karena jadwalnya memakai jam kerja standar.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $this->inv->pembelajaran()->count());
    }

    /* ═══════════ 3 · verifikasi tindakan ═══════════ */

    /**
     * Pelaksana tidak dapat memverifikasi tindakannya sendiri.
     *
     * "Selesai" dinyatakan pelaksananya; "diverifikasi" dinyatakan orang
     * lain. Menyatukan keduanya berarti pelaksana memeriksa pekerjaannya
     * sendiri — dan verifikasi semacam itu tidak pernah menemukan apa pun.
     */
    #[Test]
    public function pelaksana_tidak_dapat_memverifikasi_tindakannya_sendiri(): void
    {
        $t = $this->temuanDenganTindakan(picId: $this->admin->id);

        $this->post($this->jalur("tindakan/{$t->id}/status"), ['status' => 'diverifikasi'])
            ->assertSessionHasErrors('tindakan');

        $this->assertSame('terbuka', $t->refresh()->status);

        /* Menyatakan SELESAI tetap boleh — itu memang haknya. */
        $this->post($this->jalur("tindakan/{$t->id}/status"), ['status' => 'selesai'])
            ->assertSessionHasNoErrors();

        $this->assertSame('selesai', $t->refresh()->status);
    }

    #[Test]
    public function orang_lain_boleh_memverifikasi(): void
    {
        $pic = User::factory()->create(['company_id' => $this->c->id, 'email_verified_at' => now()]);
        $t = $this->temuanDenganTindakan(picId: $pic->id);

        $this->post($this->jalur("tindakan/{$t->id}/status"), [
            'status' => 'diverifikasi', 'efektif' => true,
            'catatan_verifikasi' => 'Diperiksa di lapangan.',
        ])->assertSessionHasNoErrors();

        $t->refresh();

        $this->assertSame('diverifikasi', $t->status);
        $this->assertSame($this->admin->id, $t->diverifikasi_oleh);
        $this->assertTrue($t->efektif);
    }

    private function temuanDenganTindakan(?int $picId): Tindakan
    {
        $temuan = $this->inv->temuan()->create([
            'no_temuan' => NomorInvestigasi::terbitkan(NomorInvestigasi::TEMUAN),
            'uraian' => 'Jadwal perawatan rem tidak menyesuaikan gradien jalan.',
        ]);

        return $temuan->tindakan()->create([
            'no_tindakan' => NomorInvestigasi::terbitkan(NomorInvestigasi::TINDAKAN),
            'uraian' => 'Revisi jadwal perawatan.', 'pic_id' => $picId, 'status' => 'terbuka',
        ]);
    }

    /* ═══════════ 4 · tahap ═══════════ */

    /**
     * Syarat tahap ditegakkan di SERVER, bukan hanya dengan menyembunyikan
     * tombolnya.
     *
     * Tombol yang tersembunyi bukan penjagaan: kiriman POST yang disusun
     * tangan tidak pernah melihat layarnya.
     */
    #[Test]
    public function tahap_tidak_dapat_dimajukan_lewat_kiriman_langsung(): void
    {
        $this->post($this->jalur('tahap/maju'))->assertSessionHasErrors('tahap');

        $this->assertSame('perencanaan', $this->inv->fresh()->tahap,
            'Tahap maju meski syaratnya belum satu pun terpenuhi.');
    }

    #[Test]
    public function tahap_maju_setelah_syaratnya_lengkap(): void
    {
        $this->inv->update([
            'ketua_id' => $this->admin->id,
            'target_selesai' => '2026-09-30',
            'tujuan' => 'Menetapkan penyebab kegagalan pengereman.',
        ]);

        $this->post($this->jalur('tim'), ['user_id' => $this->admin->id, 'peran_tim' => 'Ketua'])
            ->assertSessionHasNoErrors();

        $this->post($this->jalur('tahap/maju'))->assertSessionHasNoErrors();

        $this->assertSame('pengumpulan', $this->inv->fresh()->tahap);
    }

    /** Menambah orang yang sama dua kali tidak menimbulkan galat. */
    #[Test]
    public function anggota_tim_ganda_tidak_menimbulkan_galat(): void
    {
        $isi = ['user_id' => $this->admin->id, 'peran_tim' => 'Ketua'];

        $this->post($this->jalur('tim'), $isi)->assertSessionHasNoErrors();
        $this->post($this->jalur('tim'), $isi)->assertSessionHasNoErrors();

        $this->assertSame(1, $this->inv->tim()->count());
    }

    /**
     * Mundur TIDAK menuntut syarat apa pun.
     *
     * Mundur dipakai justru ketika ada yang keliru. Menuntut kelengkapan
     * untuk mundur berarti berkas yang terlanjur maju tidak dapat
     * diperbaiki sama sekali.
     */
    #[Test]
    public function tahap_boleh_mundur_meski_syaratnya_kurang(): void
    {
        $this->inv->update(['tahap' => 'pengumpulan']);

        $this->post($this->jalur('tahap/mundur'))->assertSessionHasNoErrors();

        $this->assertSame('perencanaan', $this->inv->fresh()->tahap);
    }

    /* ═══════════ 5 · akar masalah dan buktinya ═══════════ */

    /**
     * Bukti yang ditaut disaring pada bukti MILIK investigasi ini.
     *
     * Tanpa penyaring itu, id bukti dari berkas lain dapat ditaut lewat
     * kiriman yang disusun tangan — dan akar masalah yang menunjuk bukti
     * berkas lain adalah persis jenis kekeliruan yang tidak akan pernah
     * ada yang menyadarinya.
     */
    #[Test]
    public function akar_hanya_dapat_menaut_bukti_investigasi_sendiri(): void
    {
        $milik = $this->bukti();

        $lain = Investigasi::withoutGlobalScopes()->create([
            'no_investigasi' => NomorInvestigasi::terbitkan(NomorInvestigasi::INVESTIGASI),
            'insiden_id' => $this->inv->insiden_id,
        ]);
        $asing = $lain->bukti()->create([
            'no_bukti' => NomorInvestigasi::terbitkan(NomorInvestigasi::BUKTI),
            'jenis' => 'foto', 'judul' => 'Bukti berkas lain',
        ]);

        $this->post($this->jalur('akar'), [
            'uraian' => 'Kampas rem aus melewati batas.',
            'bukti'  => [$milik->id, $asing->id],
        ])->assertSessionHasNoErrors();

        $akar = $this->inv->akar()->latest('id')->first();
        $taut = $akar->bukti()->pluck('inv_bukti.id')->all();

        $this->assertSame([$milik->id], $taut,
            'Akar masalah berhasil menaut bukti milik berkas investigasi lain.');
    }

    #[Test]
    public function akar_dapat_dikaitkan_ke_kamus_penyebab(): void
    {
        $butir = Taksonomi::where('metode', 'scat')->where('kode', '5.1')->first();

        $this->post($this->jalur('akar'), [
            'uraian' => 'Prosedur tidak diikuti.',
            'metode' => 'scat', 'taksonomi_id' => $butir->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($butir->id, $this->inv->akar()->latest('id')->first()->taksonomi_id);
    }

    /* ═══════════ 6 · penutupan ═══════════ */

    #[Test]
    public function penutupan_ditolak_selama_syaratnya_kurang(): void
    {
        $this->post($this->jalur('tutup'))->assertSessionHasErrors('tutup');

        $this->assertSame('berjalan', $this->inv->fresh()->status);
    }

    #[Test]
    public function berkas_tertutup_dapat_dibuka_kembali(): void
    {
        $this->inv->update(['status' => 'ditutup', 'ditutup_pada' => now()]);

        $this->post($this->jalur('buka-lagi'))->assertSessionHasNoErrors();

        $segar = $this->inv->fresh();

        $this->assertSame('berjalan', $segar->status);
        $this->assertNull($segar->ditutup_pada);
    }
}
