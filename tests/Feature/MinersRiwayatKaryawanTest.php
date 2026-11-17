<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use App\Models\Miners\{Pekerja, Pelanggaran};
use App\Support\Miners\RiwayatKaryawan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Inertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\SiapanMiners;
use Tests\TestCase;

/**
 * Miners → Riwayat Karyawan lintas perusahaan (port DBEST #22): satu orang
 * dikenali lewat NIK di seluruh grup IUP, alasan nonaktif, pelanggaran
 * dengan verifikasi, dan batasnya — tidak pernah lintas pemegang IUP.
 */
class MinersRiwayatKaryawanTest extends TestCase
{
    use RefreshDatabase;
    use SiapanMiners;

    private const NIK = '6472012808970003';

    private function pekerja(Company $c, string $nama, array $x = []): Pekerja
    {
        return Pekerja::withoutGlobalScopes()->create(['company_id' => $c->id, 'nama' => $nama, 'nik' => self::NIK] + $x);
    }

    #[Test]
    public function satu_orang_dikenali_lintas_perusahaan_dan_nik_lain_disamarkan(): void
    {
        $this->siapkan();
        $lama = $this->pekerja($this->mitra, 'Budi Santoso', ['status' => 'nonaktif', 'sebab_nonaktif' => 'phk_pelanggaran',
            'alasan_nonaktif' => 'Tidur di unit saat operasi', 'rekomendasi_rekrut' => 'tidak', 'tanggal_nonaktif' => '2026-03-01']);
        $this->pekerja($this->iup, 'Budi Santoso');

        // Pemegang IUP lain dengan NIK sama tidak pernah terbaca.
        $lain = Company::create(['name' => 'PT IUP Lain', 'code' => 'LIN', 'izin_type' => 'IUP Operasi Produksi']);
        $this->pekerja($lain, 'Budi Santoso');

        $this->actingAs($this->ohse)->get('/miners/riwayat-karyawan?q='.self::NIK)->assertOk()
            ->assertInertia(fn (Inertia $p) => $p->component('Miners/RiwayatKaryawan')
                ->has('orang', 1)
                ->where('orang.0.nik', self::NIK)            // pengelola melihat NIK utuh
                ->has('orang.0.baris', 2)
                ->where('orang.0.phkPelanggaran', true)
                ->where('orang.0.tidakDisarankan', true)
                ->where('orang.0.aktifDi', ['PT Tambang Uji']));

        // Mitra lain dalam grup yang sama: NIK karyawan perusahaan lain tersamar.
        $mitra2 = Company::create(['name' => 'PT Mitra Dua', 'code' => 'MD2', 'izin_type' => 'IUJP', 'parent_id' => $this->iup->id]);
        $orang = User::factory()->create(['company_id' => $mitra2->id]);
        $this->actingAs($orang)->get('/miners/riwayat-karyawan?q=Budi')->assertOk()
            ->assertInertia(fn (Inertia $p) => $p->where('orang.0.nik', '6472••••••••0003')
                ->where('orang.0.baris', fn ($b) => collect($b)->every(fn ($x) => $x['bolehKelola'] === false && $x['noInduk'] === '')));

        $this->assertSame('Budi Santoso', $lama->nama);
    }

    #[Test]
    public function nonaktif_wajib_beralasan_dan_hanya_pemilik_atau_pengelola(): void
    {
        $this->siapkan();
        $p = $this->pekerja($this->mitra, 'Andi', ['dibuat_oleh' => $this->pengaju->id]);

        // Pendaftar (perusahaan pemilik) boleh; keterangan wajib untuk mangkir.
        $this->actingAs($this->pengaju)->post("/miners/riwayat-karyawan/{$p->id}/status", ['aktif' => 0, 'sebab' => 'mangkir', 'tanggal' => '2026-10-01'])
            ->assertSessionHasErrors('keterangan');
        $this->post("/miners/riwayat-karyawan/{$p->id}/status", [
            'aktif' => 0, 'sebab' => 'mangkir', 'tanggal' => '2026-10-01', 'keterangan' => 'Tidak hadir 7 hari', 'rekomendasi' => 'pertimbangkan',
        ])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame(['nonaktif', 'mangkir', 'pertimbangkan'], [$p->status, $p->sebab_nonaktif, $p->rekomendasi_rekrut]);

        // Pengguna lain di perusahaan yang sama yang bukan HSE/pendaftar ditolak.
        $rekan = User::factory()->create(['company_id' => $this->mitra->id]);
        $this->actingAs($rekan)->post("/miners/riwayat-karyawan/{$p->id}/status", ['aktif' => 1])->assertForbidden();

        // Aktif kembali mengosongkan alasan.
        $this->actingAs($this->ohse)->post("/miners/riwayat-karyawan/{$p->id}/status", ['aktif' => 1])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame(['aktif', null, null], [$p->status, $p->sebab_nonaktif, $p->rekomendasi_rekrut]);
    }

    #[Test]
    public function pelanggaran_mitra_menunggu_verifikasi_sebelum_terlihat_perusahaan_lain(): void
    {
        $this->siapkan();
        $p = $this->pekerja($this->mitra, 'Citra', ['dibuat_oleh' => $this->pengaju->id]);
        $isian = ['tanggal' => '2026-10-10', 'kategori' => 'golden_rules', 'tingkat' => 'berat', 'sanksi' => 'sp3', 'uraian' => 'Melewati barikade tanpa izin'];

        $this->actingAs($this->pengaju)->post("/miners/riwayat-karyawan/{$p->id}/pelanggaran", $isian)->assertSessionHasNoErrors();
        $c = Pelanggaran::firstOrFail();
        $this->assertSame(Pelanggaran::DIAJUKAN, $c->status);
        $this->assertSame($this->mitra->id, $c->company_id);

        // Mitra lain belum melihatnya.
        $mitra2 = Company::create(['name' => 'PT Mitra Dua', 'code' => 'MD2', 'izin_type' => 'IUJP', 'parent_id' => $this->iup->id]);
        $lain = User::factory()->create(['company_id' => $mitra2->id]);
        $this->actingAs($lain)->get('/miners/riwayat-karyawan?q=Citra')
            ->assertInertia(fn (Inertia $q) => $q->where('orang.0.jumlahPelanggaran', 0)->has('orang.0.pelanggaran', 0));

        // Pengelola menolak (wajib alasan), pemilik memperbaiki → diajukan kembali.
        $this->actingAs($this->ohse)->post("/miners/riwayat-karyawan/pelanggaran/{$c->id}/verifikasi", ['keputusan' => 'ditolak'])
            ->assertSessionHasErrors('catatan');
        $this->post("/miners/riwayat-karyawan/pelanggaran/{$c->id}/verifikasi", ['keputusan' => 'ditolak', 'catatan' => 'Lampirkan nomor SP'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->pengaju)->put("/miners/riwayat-karyawan/pelanggaran/{$c->id}", $isian + ['no_dokumen' => 'SP3/MTU/X/2026'])
            ->assertSessionHasNoErrors();
        $this->assertSame(Pelanggaran::DIAJUKAN, $c->fresh()->status);

        // Diverifikasi → terlihat perusahaan lain, dan pemilik tak lagi dapat mengubahnya.
        $this->actingAs($this->ohse)->post("/miners/riwayat-karyawan/pelanggaran/{$c->id}/verifikasi", ['keputusan' => 'diverifikasi'])
            ->assertSessionHasNoErrors();
        $this->actingAs($lain)->get('/miners/riwayat-karyawan?q=Citra')
            ->assertInertia(fn (Inertia $q) => $q->where('orang.0.jumlahPelanggaran', 1)->where('orang.0.berat', 1)
                ->where('orang.0.sanksiTerberat', 'SP 3'));
        $this->actingAs($this->pengaju)->delete("/miners/riwayat-karyawan/pelanggaran/{$c->id}")->assertForbidden();
        $this->actingAs($lain)->post("/miners/riwayat-karyawan/pelanggaran/{$c->id}/verifikasi", ['keputusan' => 'ditolak', 'catatan' => 'x'])->assertForbidden();

        // Catatan pengelola langsung terverifikasi.
        $this->actingAs($this->ohse)->post("/miners/riwayat-karyawan/{$p->id}/pelanggaran", $isian)->assertSessionHasNoErrors();
        $this->assertSame(Pelanggaran::DIVERIFIKASI, Pelanggaran::latest('id')->first()->status);
    }

    #[Test]
    public function tab_nonaktif_dan_rekap_terbuka(): void
    {
        $this->siapkan();
        $this->pekerja($this->mitra, 'Dedi', ['status' => 'nonaktif', 'sebab_nonaktif' => 'resign', 'tanggal_nonaktif' => '2026-05-01']);
        $this->pekerja($this->mitra, 'Eko', ['nik' => '6472014507900001', 'status' => 'nonaktif', 'tanggal_nonaktif' => '2026-06-01']);

        $this->actingAs($this->admin)->get('/miners/riwayat-karyawan?tab=nonaktif')->assertOk()
            ->assertInertia(fn (Inertia $p) => $p->where('nonaktif.halaman.total', 2)
                ->where('nonaktif.jumlah.resign', 1)->where('nonaktif.jumlah.kosong', 1));
        $this->get('/miners/riwayat-karyawan?tab=nonaktif&sebab=resign')
            ->assertInertia(fn (Inertia $p) => $p->where('nonaktif.halaman.total', 1));
        $this->get('/miners/riwayat-karyawan?tab=pelanggaran')->assertOk()
            ->assertInertia(fn (Inertia $p) => $p->where('rekap.kartu.total', 0));
    }

    #[Test]
    public function aturan_nik(): void
    {
        $this->assertTrue(RiwayatKaryawan::nikValid('6472012808970003'));
        $this->assertTrue(RiwayatKaryawan::nikValid('6472016808970003'));   // perempuan: tanggal + 40
        $this->assertFalse(RiwayatKaryawan::nikValid('1111111111111111'));
        $this->assertFalse(RiwayatKaryawan::nikValid('6472013208970003')); // tanggal 32
        $this->assertFalse(RiwayatKaryawan::nikValid('647201'));
        $this->assertSame('6472••••••••0003', RiwayatKaryawan::samarNik('6472.0128-0897 0003'));
    }
}
