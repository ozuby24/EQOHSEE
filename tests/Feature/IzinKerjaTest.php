<?php

namespace Tests\Feature;

use App\Models\{Company, IzinAmbang, IzinGas, IzinKerja, IzinPeriksa, IzinSyarat, TindakLanjut, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Izin kerja aman.
 *
 * Persetujuan di sini ADALAH izinnya, dan yang paling penting dijaga
 * ada empat:
 *
 * 1. Izin tidak dapat diterbitkan selama syarat wajib belum terpenuhi.
 *    Izin yang terbit di atas daftar periksa kosong adalah tanda tangan,
 *    bukan pemeriksaan — dan berkasnya nanti terbaca seolah
 *    pemeriksaannya pernah dilakukan.
 *
 * 2. Uji gas yang basi menghalangi penerbitan. Kadar gas berubah dalam
 *    hitungan menit; angka yang benar tiga jam lalu tidak menyatakan apa
 *    pun tentang keadaan sekarang.
 *
 * 3. Izin lewat waktu yang belum ditutup diperingatkan. Selama belum
 *    ditutup, area itu tercatat masih di bawah izin.
 *
 * 4. Dua izin yang tidak boleh berbarengan di satu lokasi terdeteksi.
 */
class IzinKerjaTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private User $pemohon;
    private User $penerbit;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-08-15 10:00:00');

        $this->company = Company::create(['name' => 'Tambang Uji']);
        $this->pemohon = User::factory()->create(['company_id' => $this->company->id]);
        $this->penerbit = User::factory()->create(['company_id' => $this->company->id, 'lms_role' => 'ktt']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function syarat(string $jenis = 'panas', bool $wajib = true, string $teks = 'APAR tersedia di lokasi'): IzinSyarat
    {
        return IzinSyarat::create([
            'company_id' => $this->company->id, 'jenis' => $jenis,
            'teks' => $teks, 'urutan' => 1, 'wajib' => $wajib, 'aktif' => true,
        ]);
    }

    private function izin(array $ganti = []): IzinKerja
    {
        return IzinKerja::create(array_merge([
            'company_id' => $this->company->id, 'user_id' => $this->pemohon->id,
            'nomor' => 'IK-001', 'jenis' => 'ketinggian', 'lokasi' => 'Workshop A',
            'uraian' => 'Perbaikan atap gudang', 'pelaksana' => 'Regu Mekanik',
            'jumlah_pekerja' => 4, 'pengawas_lapangan' => 'Budi',
            'mulai' => '2026-08-15 08:00', 'selesai' => '2026-08-15 17:00',
        ], $ganti));
    }

    /** Izin lewat jalur controller, sehingga daftar periksanya ikut tersalin. */
    private function ajukanLewatFormulir(array $ganti = []): IzinKerja
    {
        $this->actingAs($this->pemohon)->post(route('izin.simpan'), array_merge([
            'nomor' => 'IK-100', 'jenis' => 'panas', 'lokasi' => 'Workshop A',
            'uraian' => 'Pengelasan rangka', 'mulai' => '2026-08-15 08:00',
            'selesai' => '2026-08-15 17:00',
        ], $ganti))->assertSessionHasNoErrors();

        return IzinKerja::where('nomor', $ganti['nomor'] ?? 'IK-100')->firstOrFail();
    }

    private function gas(IzinKerja $i, array $ganti = []): IzinGas
    {
        return IzinGas::create(array_merge([
            'company_id' => $this->company->id, 'izin_kerja_id' => $i->id,
            'waktu_uji' => '2026-08-15 09:30', 'o2' => 20.9, 'lel' => 0, 'co' => 2, 'h2s' => 0,
            'alat' => 'Multi-gas detector', 'petugas' => 'Sari',
        ], $ganti));
    }

    private function lengkapiPeriksa(IzinKerja $i): void
    {
        foreach ($i->periksa()->get() as $p) {
            $this->actingAs($this->pemohon)->put(route('izin.periksa.ubah', $p), ['terpenuhi' => true]);
        }
    }

    /* ---------- syarat wajib menghalangi penerbitan ---------- */

    public function test_izin_tidak_terbit_selama_syarat_wajib_belum_terpenuhi(): void
    {
        $this->syarat('ketinggian');
        $i = $this->ajukanLewatFormulir(['nomor' => 'IK-101', 'jenis' => 'ketinggian']);

        $this->actingAs($this->pemohon)->post(route('izin.ajukan', $i));
        $this->actingAs($this->penerbit)->post(route('izin.terbitkan', $i))
            ->assertSessionHasErrors('alur');

        $this->assertSame('diajukan', $i->fresh()->status);
    }

    public function test_izin_terbit_setelah_syarat_wajib_terpenuhi(): void
    {
        $this->syarat('ketinggian');
        $i = $this->ajukanLewatFormulir(['nomor' => 'IK-102', 'jenis' => 'ketinggian']);
        $this->lengkapiPeriksa($i);

        $this->actingAs($this->pemohon)->post(route('izin.ajukan', $i));
        $this->actingAs($this->penerbit)->post(route('izin.terbitkan', $i))
            ->assertSessionHasNoErrors();

        $this->assertSame('disetujui', $i->fresh()->status);
    }

    public function test_syarat_tidak_wajib_tidak_menghalangi(): void
    {
        $this->syarat('ketinggian', false, 'Foto lokasi dilampirkan');
        $i = $this->ajukanLewatFormulir(['nomor' => 'IK-103', 'jenis' => 'ketinggian']);

        $this->actingAs($this->pemohon)->post(route('izin.ajukan', $i));
        $this->actingAs($this->penerbit)->post(route('izin.terbitkan', $i))
            ->assertSessionHasNoErrors();

        $this->assertSame('disetujui', $i->fresh()->status);
    }

    public function test_daftar_periksa_tersalin_bukan_dirujuk(): void
    {
        // Daftar syarat berubah seiring waktu; izin yang sudah terbit
        // harus tetap terbaca dengan syarat yang berlaku saat itu.
        $s = $this->syarat('ketinggian', true, 'Harness diperiksa');
        $i = $this->ajukanLewatFormulir(['nomor' => 'IK-104', 'jenis' => 'ketinggian']);

        $this->assertSame('Harness diperiksa', $i->periksa()->first()->teks);

        $s->update(['teks' => 'Harness DAN lanyard diperiksa']);

        $this->assertSame('Harness diperiksa', $i->fresh()->periksa()->first()->teks);
    }

    public function test_daftar_periksa_terkunci_setelah_izin_terbit(): void
    {
        $this->syarat('ketinggian');
        $i = $this->ajukanLewatFormulir(['nomor' => 'IK-105', 'jenis' => 'ketinggian']);
        $this->lengkapiPeriksa($i);
        $this->actingAs($this->pemohon)->post(route('izin.ajukan', $i));
        $this->actingAs($this->penerbit)->post(route('izin.terbitkan', $i));

        $p = $i->periksa()->first();
        $this->actingAs($this->pemohon)->put(route('izin.periksa.ubah', $p), ['terpenuhi' => false])
            ->assertSessionHasErrors('alur');

        $this->assertTrue($p->fresh()->terpenuhi);
    }

    /* ---------- uji gas ---------- */

    public function test_izin_panas_tidak_terbit_tanpa_uji_gas(): void
    {
        $i = $this->ajukanLewatFormulir(['nomor' => 'IK-110', 'jenis' => 'panas']);

        $this->actingAs($this->pemohon)->post(route('izin.ajukan', $i));
        $this->actingAs($this->penerbit)->post(route('izin.terbitkan', $i))
            ->assertSessionHasErrors('alur');

        $this->assertSame('diajukan', $i->fresh()->status);
    }

    public function test_izin_panas_tidak_terbit_dengan_uji_gas_basi(): void
    {
        $i = $this->ajukanLewatFormulir(['nomor' => 'IK-111', 'jenis' => 'panas']);
        $this->gas($i, ['waktu_uji' => '2026-08-15 06:00']);   // 4 jam lalu

        $this->actingAs($this->pemohon)->post(route('izin.ajukan', $i));
        $this->actingAs($this->penerbit)->post(route('izin.terbitkan', $i))
            ->assertSessionHasErrors('alur');
    }

    public function test_izin_panas_tidak_terbit_dengan_bacaan_di_luar_ambang(): void
    {
        $i = $this->ajukanLewatFormulir(['nomor' => 'IK-112', 'jenis' => 'panas']);
        $this->gas($i, ['lel' => 18.0]);

        $this->actingAs($this->pemohon)->post(route('izin.ajukan', $i));
        $this->actingAs($this->penerbit)->post(route('izin.terbitkan', $i))
            ->assertSessionHasErrors('alur');
    }

    public function test_izin_panas_tidak_terbit_bila_ada_parameter_yang_belum_diukur(): void
    {
        // Parameter yang tidak diukur bukan parameter yang aman.
        $i = $this->ajukanLewatFormulir(['nomor' => 'IK-113', 'jenis' => 'panas']);
        $this->gas($i, ['h2s' => null]);

        $this->actingAs($this->pemohon)->post(route('izin.ajukan', $i));
        $this->actingAs($this->penerbit)->post(route('izin.terbitkan', $i))
            ->assertSessionHasErrors('alur');
    }

    public function test_izin_panas_terbit_dengan_uji_gas_segar_dan_lulus(): void
    {
        $i = $this->ajukanLewatFormulir(['nomor' => 'IK-114', 'jenis' => 'panas']);
        $this->gas($i);

        $this->actingAs($this->pemohon)->post(route('izin.ajukan', $i));
        $this->actingAs($this->penerbit)->post(route('izin.terbitkan', $i))
            ->assertSessionHasNoErrors();

        $this->assertSame('disetujui', $i->fresh()->status);
    }

    public function test_uji_gas_masa_depan_ditolak(): void
    {
        $i = $this->ajukanLewatFormulir(['nomor' => 'IK-115', 'jenis' => 'panas']);

        $this->actingAs($this->pemohon)->post(route('izin.gas.simpan', $i), [
            'waktu_uji' => '2026-08-15 14:00', 'o2' => 20.9, 'lel' => 0, 'co' => 0, 'h2s' => 0,
        ])->assertSessionHasErrors('waktu_uji');

        $this->assertSame(0, IzinGas::count());
    }

    public function test_uji_gas_tetap_dapat_ditambahkan_setelah_izin_terbit(): void
    {
        // Pengukuran ulang di tengah pekerjaan justru yang diharapkan.
        $i = $this->ajukanLewatFormulir(['nomor' => 'IK-116', 'jenis' => 'panas']);
        $this->gas($i);
        $this->actingAs($this->pemohon)->post(route('izin.ajukan', $i));
        $this->actingAs($this->penerbit)->post(route('izin.terbitkan', $i));

        $this->actingAs($this->pemohon)->post(route('izin.gas.simpan', $i), [
            'waktu_uji' => '2026-08-15 09:55', 'o2' => 20.8, 'lel' => 1, 'co' => 3, 'h2s' => 0,
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, IzinGas::count());
    }

    public function test_ambang_situs_menggantikan_bawaan(): void
    {
        IzinAmbang::create([
            'company_id' => $this->company->id, 'parameter' => 'lel',
            'batas_maks' => 5.0, 'satuan' => '%LEL', 'acuan' => 'Prosedur ruang terbatas',
        ]);

        $i = $this->ajukanLewatFormulir(['nomor' => 'IK-117', 'jenis' => 'panas']);
        $this->gas($i, ['lel' => 8.0]);   // lulus bawaan (10), gagal situs (5)

        $this->actingAs($this->pemohon)->post(route('izin.ajukan', $i));
        $this->actingAs($this->penerbit)->post(route('izin.terbitkan', $i))
            ->assertSessionHasErrors('alur');
    }

    /* ---------- penutupan ---------- */

    public function test_izin_lewat_waktu_belum_ditutup_diperingatkan(): void
    {
        $this->syarat('ketinggian');
        $i = $this->ajukanLewatFormulir([
            'nomor' => 'IK-120', 'jenis' => 'ketinggian',
            'mulai' => '2026-08-14 08:00', 'selesai' => '2026-08-14 17:00',
        ]);
        $this->lengkapiPeriksa($i);
        $this->actingAs($this->pemohon)->post(route('izin.ajukan', $i));
        $this->actingAs($this->penerbit)->post(route('izin.terbitkan', $i));

        $this->actingAs($this->pemohon)->get(route('izin.index', ['dari' => '2026-08-01', 'sampai' => '2026-08-31']))
            ->assertInertia(fn (Assert $p) => $p
                ->where('ringkas.belumTutup', 1)
                ->where('alerts', fn ($x) => collect($x)->contains(
                    fn ($q) => str_contains($q['kode'], 'belum-ditutup') && $q['level'] === 'tinggi'
                )));
    }

    public function test_izin_yang_sudah_ditutup_tidak_lagi_diperingatkan(): void
    {
        $this->syarat('ketinggian');
        $i = $this->ajukanLewatFormulir([
            'nomor' => 'IK-121', 'jenis' => 'ketinggian',
            'mulai' => '2026-08-14 08:00', 'selesai' => '2026-08-14 17:00',
        ]);
        $this->lengkapiPeriksa($i);
        $this->actingAs($this->pemohon)->post(route('izin.ajukan', $i));
        $this->actingAs($this->penerbit)->post(route('izin.terbitkan', $i));

        $this->actingAs($this->penerbit)->post(route('izin.tutup', $i), [
            'catatan_penutupan' => 'Pekerjaan selesai, area bersih, seluruh orang keluar.',
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->pemohon)->get(route('izin.index', ['dari' => '2026-08-01', 'sampai' => '2026-08-31']))
            ->assertInertia(fn (Assert $p) => $p
                ->where('ringkas.belumTutup', 0)
                ->where('ringkas.ditutup', 1));
    }

    public function test_izin_yang_belum_terbit_tidak_dapat_ditutup(): void
    {
        $i = $this->izin();

        $this->actingAs($this->penerbit)->post(route('izin.tutup', $i), [
            'catatan_penutupan' => 'Dicoba menutup sebelum terbit.',
        ])->assertSessionHasErrors('alur');

        $this->assertNull($i->fresh()->ditutup_pada);
    }

    public function test_penutupan_menuntut_catatan(): void
    {
        $this->syarat('ketinggian');
        $i = $this->ajukanLewatFormulir(['nomor' => 'IK-122', 'jenis' => 'ketinggian']);
        $this->lengkapiPeriksa($i);
        $this->actingAs($this->pemohon)->post(route('izin.ajukan', $i));
        $this->actingAs($this->penerbit)->post(route('izin.terbitkan', $i));

        $this->actingAs($this->penerbit)->post(route('izin.tutup', $i), ['catatan_penutupan' => ''])
            ->assertSessionHasErrors('catatan_penutupan');
    }

    public function test_isi_izin_tetap_terkunci_setelah_terbit(): void
    {
        // Pengecualian penutupan tidak boleh melebar menjadi izin untuk
        // menyunting isinya.
        $this->syarat('ketinggian');
        $i = $this->ajukanLewatFormulir(['nomor' => 'IK-123', 'jenis' => 'ketinggian']);
        $this->lengkapiPeriksa($i);
        $this->actingAs($this->pemohon)->post(route('izin.ajukan', $i));
        $this->actingAs($this->penerbit)->post(route('izin.terbitkan', $i));

        $this->expectException(\RuntimeException::class);
        $i->fresh()->update(['lokasi' => 'Workshop B']);
    }

    public function test_izin_yang_sudah_ditutup_tidak_dapat_ditutup_ulang(): void
    {
        $this->syarat('ketinggian');
        $i = $this->ajukanLewatFormulir(['nomor' => 'IK-124', 'jenis' => 'ketinggian']);
        $this->lengkapiPeriksa($i);
        $this->actingAs($this->pemohon)->post(route('izin.ajukan', $i));
        $this->actingAs($this->penerbit)->post(route('izin.terbitkan', $i));

        $this->actingAs($this->penerbit)->post(route('izin.tutup', $i), [
            'catatan_penutupan' => 'Pekerjaan selesai, area bersih.',
        ])->assertSessionHasNoErrors();

        $pertama = $i->fresh()->catatan_penutupan;

        $this->actingAs($this->penerbit)->post(route('izin.tutup', $i), [
            'catatan_penutupan' => 'Dicoba menimpa catatan penutupan.',
        ])->assertSessionHasErrors('alur');

        $this->assertSame($pertama, $i->fresh()->catatan_penutupan);
    }

    /* ---------- kebentrokan ---------- */

    public function test_izin_panas_dan_ruang_terbatas_berbarengan_di_lokasi_sama_terdeteksi(): void
    {
        foreach ([['IK-130', 'panas'], ['IK-131', 'ruang-terbatas']] as [$no, $jenis]) {
            $i = $this->ajukanLewatFormulir([
                'nomor' => $no, 'jenis' => $jenis, 'lokasi' => 'Tangki Solar 2',
                'mulai' => '2026-08-15 08:00', 'selesai' => '2026-08-15 16:00',
            ]);
            $this->gas($i);
            $this->actingAs($this->pemohon)->post(route('izin.ajukan', $i));
            $this->actingAs($this->penerbit)->post(route('izin.terbitkan', $i));
        }

        $this->actingAs($this->pemohon)->get(route('izin.index', ['dari' => '2026-08-01', 'sampai' => '2026-08-31']))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($x) => collect($x)->contains(
                    fn ($q) => str_contains($q['kode'], 'bentrok') && $q['level'] === 'tinggi'
                )));
    }

    public function test_izin_bentrok_di_lokasi_berbeda_tidak_ditandai(): void
    {
        foreach ([['IK-132', 'panas', 'Tangki Solar 2'], ['IK-133', 'ruang-terbatas', 'Tangki Solar 5']] as [$no, $jenis, $lok]) {
            $i = $this->ajukanLewatFormulir([
                'nomor' => $no, 'jenis' => $jenis, 'lokasi' => $lok,
                'mulai' => '2026-08-15 08:00', 'selesai' => '2026-08-15 16:00',
            ]);
            $this->gas($i);
            $this->actingAs($this->pemohon)->post(route('izin.ajukan', $i));
            $this->actingAs($this->penerbit)->post(route('izin.terbitkan', $i));
        }

        $this->actingAs($this->pemohon)->get(route('izin.index', ['dari' => '2026-08-01', 'sampai' => '2026-08-31']))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($x) => collect($x)->doesntContain(
                    fn ($q) => str_contains($q['kode'], 'bentrok')
                )));
    }

    public function test_dua_draf_yang_bentrok_belum_ditandai(): void
    {
        // Dua draf yang bertabrakan belum menjadi kejadian; menandainya
        // lebih dulu hanya melatih orang mengabaikan peringatan.
        foreach ([['IK-134', 'panas'], ['IK-135', 'ruang-terbatas']] as [$no, $jenis]) {
            $this->ajukanLewatFormulir([
                'nomor' => $no, 'jenis' => $jenis, 'lokasi' => 'Tangki Solar 2',
                'mulai' => '2026-08-15 08:00', 'selesai' => '2026-08-15 16:00',
            ]);
        }

        $this->actingAs($this->pemohon)->get(route('izin.index', ['dari' => '2026-08-01', 'sampai' => '2026-08-31']))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($x) => collect($x)->doesntContain(
                    fn ($q) => str_contains($q['kode'], 'bentrok')
                )));
    }

    /* ---------- alur ---------- */

    public function test_pemohon_tidak_boleh_menerbitkan_izinnya_sendiri(): void
    {
        $i = $this->ajukanLewatFormulir(['nomor' => 'IK-140', 'jenis' => 'ketinggian']);

        $this->actingAs($this->pemohon)->post(route('izin.ajukan', $i));
        $this->actingAs($this->pemohon)->post(route('izin.terbitkan', $i))
            ->assertSessionHasErrors('alur');

        $this->assertSame('diajukan', $i->fresh()->status);
    }

    public function test_izin_baru_selalu_lahir_sebagai_draf(): void
    {
        $this->actingAs($this->pemohon)->post(route('izin.simpan'), [
            'nomor' => 'IK-141', 'jenis' => 'ketinggian', 'lokasi' => 'Workshop A',
            'uraian' => 'Perbaikan atap', 'mulai' => '2026-08-15 08:00', 'selesai' => '2026-08-15 17:00',
            'status' => 'disetujui',    // dicoba dititipkan lewat isian
        ])->assertSessionHasNoErrors();

        $this->assertSame('draf', IzinKerja::where('nomor', 'IK-141')->first()->status);
    }

    public function test_waktu_selesai_harus_setelah_waktu_mulai(): void
    {
        $this->actingAs($this->pemohon)->post(route('izin.simpan'), [
            'nomor' => 'IK-142', 'jenis' => 'ketinggian', 'lokasi' => 'Workshop A',
            'uraian' => 'Perbaikan atap', 'mulai' => '2026-08-15 17:00', 'selesai' => '2026-08-15 08:00',
        ])->assertSessionHasErrors('selesai');

        $this->assertSame(0, IzinKerja::count());
    }

    public function test_izin_yang_sudah_terbit_tidak_dapat_dihapus(): void
    {
        $admin = User::factory()->create(['company_id' => $this->company->id, 'is_admin' => true]);
        $i = $this->ajukanLewatFormulir(['nomor' => 'IK-143', 'jenis' => 'ketinggian']);
        $this->actingAs($this->pemohon)->post(route('izin.ajukan', $i));
        $this->actingAs($this->penerbit)->post(route('izin.terbitkan', $i));

        $this->actingAs($admin)->delete(route('izin.hapus', $i))->assertSessionHasErrors('alur');

        $this->assertSame(1, IzinKerja::count());
    }

    /* ---------- kelengkapan acuan ---------- */

    public function test_ambang_gas_bawaan_dinyatakan(): void
    {
        $this->actingAs($this->pemohon)->get(route('izin.ambang'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('ambang.o2.ditetapkan', false)
                ->where('alerts', fn ($x) => collect($x)->contains(
                    fn ($q) => $q['kode'] === 'ambang-gas-bawaan'
                )));
    }

    public function test_jenis_tanpa_daftar_periksa_diperingatkan(): void
    {
        $this->actingAs($this->pemohon)->get(route('izin.syarat'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('alerts', fn ($x) => collect($x)->contains(
                    fn ($q) => $q['kode'] === 'jenis-tanpa-syarat'
                )));
    }

    /* ---------- halaman ---------- */

    public function test_seluruh_halaman_terbuka(): void
    {
        $this->syarat();
        $this->izin();

        foreach (['index', 'daftar', 'syarat', 'ambang', 'cetak'] as $rute) {
            $this->actingAs($this->pemohon)->get(route("izin.{$rute}"))->assertOk();
        }
    }

    public function test_laporan_memakai_kop_dokumen_terkendali(): void
    {
        $this->actingAs($this->pemohon)->get(route('izin.cetak'))
            ->assertInertia(fn (Assert $p) => $p
                ->component('Print/Izin')
                ->where('dok.nomor', 'TU-OHSE-IV.121'));
    }

    /* ---------- pemisahan antar perusahaan ---------- */

    public function test_izin_perusahaan_lain_tidak_terlihat(): void
    {
        $lain = Company::create(['name' => 'Tambang Lain']);
        $this->izin(['company_id' => $lain->id, 'nomor' => 'IK-LAIN']);
        $this->izin();

        $this->actingAs($this->pemohon)->get(route('izin.daftar', ['dari' => '2026-08-01', 'sampai' => '2026-08-31']))
            ->assertInertia(fn (Assert $p) => $p
                ->where('izin', fn ($x) => collect($x)->pluck('nomor')->all() === ['IK-001']));
    }

    /* ---------- tindak lanjut ---------- */

    public function test_tindak_lanjut_menutup_lingkaran_peringatan(): void
    {
        $this->actingAs($this->pemohon)->post(route('izin.tindak.simpan'), [
            'kode_pemicu' => 'ambang-gas-bawaan',
            'judul' => 'Salin ambang gas dari prosedur ruang terbatas',
            'prioritas' => 'sedang',
        ])->assertSessionHasNoErrors();

        $this->assertSame('izin', TindakLanjut::first()->modul);

        $this->actingAs($this->pemohon)->get(route('izin.index'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('kodeDitangani', fn ($k) => collect($k)->contains('ambang-gas-bawaan')));
    }
}
