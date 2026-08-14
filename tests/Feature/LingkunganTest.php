<?php

namespace Tests\Feature;

use App\Models\{Company, LingkunganArea, LingkunganPantau, LingkunganParameter, ReklamasiKemajuan, TindakLanjut, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Pengelolaan Lingkungan dan Reklamasi.
 *
 * Yang paling penting diuji: tahapan reklamasi tidak boleh melompat,
 * tahapan petak hanya berpindah setelah kemajuannya disetujui, dan
 * pelanggaran baku mutu ikut berubah ketika ambangnya diubah — sebab
 * ambang itu memang berganti mengikuti peraturan yang berlaku.
 */
class LingkunganTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $pengawas;
    private User $ktt;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-31 08:00:00');

        $this->company = Company::create(['name' => 'Tambang Uji']);
        $this->pengawas = User::factory()->create(['company_id' => $this->company->id]);
        $this->ktt = User::factory()->create(['company_id' => $this->company->id, 'lms_role' => 'ktt']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function petak(array $ganti = []): LingkunganArea
    {
        return LingkunganArea::create(array_merge([
            'company_id' => $this->company->id, 'kode' => 'PIT-A', 'nama' => 'Bukaan Pit A',
            'jenis' => 'bukaan', 'luas_ha' => 20, 'tahap' => 'belum',
            'tanggal_buka' => '2026-01-10',
        ], $ganti));
    }

    private function parameter(array $ganti = []): LingkunganParameter
    {
        return LingkunganParameter::create(array_merge([
            'company_id' => $this->company->id, 'kode' => 'TSS', 'nama' => 'Total Suspended Solid',
            'media' => 'air', 'satuan' => 'mg/L', 'batas_maks' => 400,
            'acuan' => 'Baku mutu air limbah tambang', 'aktif' => true,
        ], $ganti));
    }

    /* ---------- tahapan berjenjang ---------- */

    public function test_melompati_tahapan_ditolak(): void
    {
        // Tanah pucuk di atas lahan yang belum ditata tergerus pada
        // hujan pertama; melompatinya bukan percepatan.
        $p = $this->petak();

        $this->actingAs($this->pengawas)->post(route('lingkungan.kemajuan.simpan'), [
            'lingkungan_area_id' => $p->id, 'tanggal' => '2026-08-01',
            'tahap' => 'revegetasi', 'luas_ha' => 5,
        ])->assertSessionHasErrors('tahap');

        $this->assertSame(0, ReklamasiKemajuan::count());
    }

    public function test_tahapan_berikutnya_yang_wajar_diterima(): void
    {
        $p = $this->petak();

        $this->actingAs($this->pengawas)->post(route('lingkungan.kemajuan.simpan'), [
            'lingkungan_area_id' => $p->id, 'tanggal' => '2026-08-01',
            'tahap' => 'penataan', 'luas_ha' => 5,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, ReklamasiKemajuan::count());
    }

    public function test_luas_kemajuan_tidak_boleh_melebihi_luas_petaknya(): void
    {
        $p = $this->petak(['luas_ha' => 10]);

        $this->actingAs($this->pengawas)->post(route('lingkungan.kemajuan.simpan'), [
            'lingkungan_area_id' => $p->id, 'tanggal' => '2026-08-01',
            'tahap' => 'penataan', 'luas_ha' => 15,
        ])->assertSessionHasErrors('luas_ha');
    }

    /* ---------- tahapan berpindah hanya setelah disetujui ---------- */

    public function test_kemajuan_draf_tidak_menggeser_tahapan_petak(): void
    {
        // Neraca lahan inilah yang masuk ke laporan kepada inspektur
        // tambang; ia tidak boleh bergeser sebelum ada yang meninjau.
        $p = $this->petak();

        $this->actingAs($this->pengawas)->post(route('lingkungan.kemajuan.simpan'), [
            'lingkungan_area_id' => $p->id, 'tanggal' => '2026-08-01',
            'tahap' => 'penataan', 'luas_ha' => 5,
        ]);

        $this->assertSame('belum', $p->fresh()->tahap);
    }

    public function test_tahapan_petak_berpindah_setelah_kemajuan_disetujui(): void
    {
        $p = $this->petak();
        $k = ReklamasiKemajuan::create([
            'company_id' => $this->company->id, 'lingkungan_area_id' => $p->id,
            'tanggal' => '2026-08-01', 'tahap' => 'penataan', 'luas_ha' => 5,
        ]);

        $this->actingAs($this->pengawas)->post(route('lingkungan.kemajuan.ajukan', $k));
        $this->actingAs($this->ktt)->post(route('lingkungan.kemajuan.setujui', $k));

        $this->assertSame('penataan', $p->fresh()->tahap);
    }

    public function test_kemajuan_lama_tidak_menarik_mundur_petak_yang_sudah_lebih_jauh(): void
    {
        // Kemajuan yang disetujui belakangan tetapi tahapannya lebih
        // awal tidak boleh memundurkan petaknya.
        $p = $this->petak(['tahap' => 'revegetasi']);
        $k = ReklamasiKemajuan::create([
            'company_id' => $this->company->id, 'lingkungan_area_id' => $p->id,
            'tanggal' => '2026-07-01', 'tahap' => 'penataan', 'luas_ha' => 5,
        ]);

        $this->actingAs($this->pengawas)->post(route('lingkungan.kemajuan.ajukan', $k));
        $this->actingAs($this->ktt)->post(route('lingkungan.kemajuan.setujui', $k));

        $this->assertSame('revegetasi', $p->fresh()->tahap);
    }

    public function test_pengaju_tidak_boleh_menyetujui_kemajuannya_sendiri(): void
    {
        $p = $this->petak();
        $k = ReklamasiKemajuan::create([
            'company_id' => $this->company->id, 'lingkungan_area_id' => $p->id,
            'tanggal' => '2026-08-01', 'tahap' => 'penataan', 'luas_ha' => 5,
        ]);

        $this->actingAs($this->pengawas)->post(route('lingkungan.kemajuan.ajukan', $k));
        $this->actingAs($this->pengawas)->post(route('lingkungan.kemajuan.setujui', $k))
            ->assertSessionHasErrors('alur');

        $this->assertSame('belum', $p->fresh()->tahap);
    }

    /* ---------- neraca ---------- */

    public function test_petak_yang_masih_ditambang_tidak_dihitung_tunggakan(): void
    {
        $this->petak(['kode' => 'A', 'luas_ha' => 50, 'tanggal_selesai_tambang' => null]);
        $this->petak(['kode' => 'B', 'luas_ha' => 10, 'tanggal_selesai_tambang' => '2026-01-01']);

        $this->actingAs($this->pengawas)->get(route('lingkungan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('neraca.terganggu', 60)
                ->where('neraca.aktif', 50)
                ->where('neraca.tunggakan', 10)
            );
    }

    public function test_petak_menganggur_lama_diperingatkan(): void
    {
        $this->petak(['tanggal_selesai_tambang' => '2024-01-01']);

        $this->actingAs($this->pengawas)->get(route('lingkungan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->contains(
                    fn ($x) => $x['kode'] === 'lahan-menganggur-telat'
                ))
            );
    }

    public function test_petak_yang_sudah_selesai_berhenti_menua(): void
    {
        // Jamnya dihentikan pada saat tuntas, bukan terus berjalan.
        $p = $this->petak(['tahap' => 'selesai', 'tanggal_selesai_tambang' => '2020-01-01']);

        $this->assertNull($p->mengangurHari());
    }

    /* ---------- revegetasi ---------- */

    public function test_tingkat_tumbuh_di_bawah_ambang_diperingatkan(): void
    {
        $p = $this->petak(['tahap' => 'revegetasi', 'tanggal_selesai_tambang' => '2026-06-01']);
        $k = ReklamasiKemajuan::create([
            'company_id' => $this->company->id, 'lingkungan_area_id' => $p->id,
            'tanggal' => '2026-08-01', 'tahap' => 'revegetasi', 'luas_ha' => 10,
            'tingkat_tumbuh_persen' => 62,
        ]);
        $k->forceFill(['status' => 'disetujui'])->save();

        $this->actingAs($this->pengawas)->get(route('lingkungan.index'))
            ->assertInertia(fn (Assert $pr) => $pr
                ->where('alerts', fn ($a) => collect($a)->contains(
                    fn ($x) => str_starts_with($x['kode'], 'tumbuh-kurang-')
                ))
            );
    }

    /* ---------- baku mutu yang dapat disesuaikan ---------- */

    public function test_pelanggaran_dinilai_terhadap_ambang_yang_berlaku_saat_dibaca(): void
    {
        // Pokok dari "configurable, tidak mengunci satu versi regulasi":
        // baris lama tidak boleh membeku pada penilaian ambang lamanya.
        $par = $this->parameter(['batas_maks' => 400]);
        $x = LingkunganPantau::create([
            'company_id' => $this->company->id, 'lingkungan_parameter_id' => $par->id,
            'titik' => 'Outlet 1', 'tanggal' => '2026-08-10', 'nilai' => 300,
        ]);

        $this->assertFalse($x->fresh()->melanggar());

        // Izin diperketat menjadi 200 — hasil uji yang sama kini melanggar.
        $par->update(['batas_maks' => 200]);

        $this->assertTrue($x->fresh()->melanggar());
    }

    public function test_parameter_berbatas_bawah_melanggar_ketika_nilainya_kurang(): void
    {
        // Oksigen terlarut hanya punya batas bawah; pH punya keduanya.
        $do = $this->parameter(['kode' => 'DO', 'nama' => 'Oksigen terlarut', 'batas_maks' => null, 'batas_min' => 4]);

        $this->assertTrue($do->melanggar(2.5));
        $this->assertFalse($do->melanggar(6.0));
    }

    public function test_parameter_tanpa_ambang_sama_sekali_ditolak(): void
    {
        // Tanpa ambang, hasil ujinya tidak dapat dinilai taat atau tidak,
        // dan titik penaatannya tampak taat tanpa ada yang dibandingkan.
        $this->actingAs($this->pengawas)->post(route('lingkungan.parameter.simpan'), [
            'kode' => 'XX', 'nama' => 'Tanpa ambang', 'media' => 'air',
        ])->assertSessionHasErrors('batas_maks');
    }

    public function test_pelanggaran_baku_mutu_diperingatkan_beserta_dasar_hukumnya(): void
    {
        $par = $this->parameter(['batas_maks' => 100, 'acuan' => 'Permen LHK 5/2022']);
        $x = LingkunganPantau::create([
            'company_id' => $this->company->id, 'lingkungan_parameter_id' => $par->id,
            'titik' => 'Outlet 1', 'tanggal' => '2026-08-10', 'nilai' => 350,
        ]);
        $x->forceFill(['status' => 'disetujui'])->save();

        $this->actingAs($this->pengawas)->get(route('lingkungan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->contains(
                    fn ($y) => str_starts_with($y['kode'], 'baku-mutu-')
                        && str_contains($y['ket'], 'Permen LHK 5/2022')
                ))
            );
    }

    public function test_hasil_uji_belum_ditinjau_tidak_memicu_peringatan_baku_mutu(): void
    {
        $par = $this->parameter(['batas_maks' => 100]);
        LingkunganPantau::create([
            'company_id' => $this->company->id, 'lingkungan_parameter_id' => $par->id,
            'titik' => 'Outlet 1', 'tanggal' => '2026-08-10', 'nilai' => 999,
        ]);

        $this->actingAs($this->pengawas)->get(route('lingkungan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($a) => collect($a)->every(
                    fn ($y) => !str_starts_with($y['kode'], 'baku-mutu-')
                ))
            );
    }

    /* ---------- halaman & laporan ---------- */

    public function test_halaman_memuat_neraca_dan_petak(): void
    {
        $this->petak();

        $this->actingAs($this->pengawas)->get(route('lingkungan.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->component('Lingkungan/Halaman')
                ->has('area', 1)
                ->has('neraca.terganggu')
                ->has('tautan.cetak')
            );
    }

    public function test_laporan_hanya_memuat_kemajuan_yang_disetujui(): void
    {
        $p = $this->petak();
        $sah = ReklamasiKemajuan::create([
            'company_id' => $this->company->id, 'lingkungan_area_id' => $p->id,
            'tanggal' => '2026-08-01', 'tahap' => 'penataan', 'luas_ha' => 5,
        ]);
        $sah->forceFill(['status' => 'disetujui'])->save();

        ReklamasiKemajuan::create([
            'company_id' => $this->company->id, 'lingkungan_area_id' => $p->id,
            'tanggal' => '2026-08-05', 'tahap' => 'penataan', 'luas_ha' => 3,
        ]);

        $this->actingAs($this->ktt)->get(route('lingkungan.cetak'))
            ->assertInertia(fn (Assert $pr) => $pr
                ->component('Print/Lingkungan')
                ->where('dasar.disetujui', 1)
                ->where('dasar.belumDitinjau', 1)
                ->has('dok.nomor')
            );
    }

    public function test_tautan_hapus_yang_dikirim_halaman_benar_benar_bekerja(): void
    {
        $p = $this->petak();
        $k = ReklamasiKemajuan::create([
            'company_id' => $this->company->id, 'lingkungan_area_id' => $p->id,
            'tanggal' => '2026-08-01', 'tahap' => 'penataan', 'luas_ha' => 5,
        ]);

        $admin = User::factory()->create(['company_id' => $this->company->id, 'is_admin' => true]);

        $pola = null;
        $this->actingAs($admin)->get(route('lingkungan.lahan'))
            ->assertInertia(function (Assert $pr) use (&$pola) {
                $pola = $pr->toArray()['props']['tautan']['kemajuanHapus'];
            });

        $this->assertNotNull($pola);
        $this->actingAs($admin)->delete(str_replace('__ID__', (string) $k->id, $pola))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, ReklamasiKemajuan::count());
    }

    public function test_tindak_lanjut_tercatat_pada_modulnya_sendiri(): void
    {
        $this->actingAs($this->pengawas)->post(route('lingkungan.tindak.simpan'), [
            'judul' => 'Penyulaman blok revegetasi B', 'prioritas' => 'tinggi',
        ])->assertSessionHasNoErrors();

        $this->assertSame('lingkungan', TindakLanjut::first()->modul);
    }

    public function test_perusahaan_lain_tidak_melihat_petak_orang(): void
    {
        $this->petak();

        $lain = Company::create(['name' => 'Tambang Lain']);
        $orangLain = User::factory()->create(['company_id' => $lain->id]);

        $this->actingAs($orangLain)->get(route('lingkungan.index'))
            ->assertInertia(fn (Assert $p) => $p->has('area', 0));
    }
}
