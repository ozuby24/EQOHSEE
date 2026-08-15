<?php

namespace Tests\Feature;

use App\Models\{MinerbaConservationRecord, TindakLanjut, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Konservasi disamakan dengan Operasi: alur tinjauan dapat dijalankan
 * dari halaman, tautannya memakai penanda, dan peringatannya menyebutkan
 * tindakan.
 *
 * Sebelumnya modul ini memiliki alur di sisi server tanpa satu pun tombol
 * untuk menjalankannya — keadaan yang paling membingungkan bagi pemakai,
 * sebab datanya tidak pernah dapat keluar dari draf dan angkanya tidak
 * pernah muncul di KPI, tanpa penjelasan apa pun di layar.
 */
class KonservasiAlurTest extends TestCase
{
    use RefreshDatabase;

    private function props(User $u): array
    {
        return $this->actingAs($u)->get(route('konservasi.index'))
            ->assertOk()->viewData('page')['props'];
    }

    /** Sama persis dengan helper `untuk()` di Konservasi/Halaman.vue. */
    private function untuk(string $pola, int|string $id): string
    {
        return str_replace('__ID__', (string) $id, $pola);
    }

    private function buat(): MinerbaConservationRecord
    {
        return MinerbaConservationRecord::create([
            'periode' => now()->startOfYear()->toDateString(), 'lokasi' => 'Pit A',
            'komoditas' => 'Batubara', 'satuan' => 'ton',
            'target_produksi' => 1000, 'produksi_aktual' => 900, 'material_digali' => 1200,
            'recovery_percent' => 75, 'kehilangan_material' => 50, 'dilusi' => 30,
            'stok_akhir' => 200,
        ]);
    }

    public function test_setiap_tautan_ber_id_memakai_penanda(): void
    {
        $tautan = $this->props(User::factory()->create())['tautan'];

        foreach (['recordUbah', 'recordHapus', 'recordAjukan', 'recordSetujui', 'recordTolak', 'actionUbah', 'actionHapus'] as $kunci) {
            $this->assertArrayHasKey($kunci, $tautan);
            $this->assertStringContainsString('__ID__', $tautan[$kunci],
                "Tautan '{$kunci}' harus memakai penanda __ID__.");
        }
    }

    public function test_alur_dapat_dijalankan_lewat_tautan_halaman(): void
    {
        $operator = User::factory()->create();
        $ktt = User::factory()->create(['lms_role' => 'ktt']);
        $rec = $this->buat();

        $tautan = $this->props($operator)['tautan'];

        $this->actingAs($operator)
            ->post($this->untuk($tautan['recordAjukan'], $rec->id))
            ->assertSessionHasNoErrors();
        $this->assertSame('diajukan', $rec->fresh()->status);

        $this->actingAs($ktt)
            ->post($this->untuk($tautan['recordSetujui'], $rec->id))
            ->assertSessionHasNoErrors();
        $this->assertSame('disetujui', $rec->fresh()->status);
    }

    public function test_tautan_hapus_benar_benar_menghapus(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $rec = $this->buat();

        $url = $this->untuk($this->props($admin)['tautan']['recordHapus'], $rec->id);

        $this->actingAs($admin)->delete($url)->assertRedirect();
        $this->assertDatabaseMissing('konservasi_minerba_records', ['id' => $rec->id]);
    }

    public function test_tautan_ubah_status_tindak_lanjut_bekerja(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $action = TindakLanjut::create([
            'modul' => 'konservasi', 'judul' => 'Perbaiki kendali kadar',
            'kategori' => 'recovery', 'prioritas' => 'tinggi', 'status' => 'rencana',
        ]);

        $url = $this->untuk($this->props($admin)['tautan']['actionUbah'], $action->id);

        $this->actingAs($admin)->put($url, ['status' => 'selesai'])->assertRedirect();
        $this->assertSame('selesai', $action->fresh()->status);
    }

    /* ---------- peringatan ---------- */

    public function test_data_yang_belum_diajukan_memunculkan_peringatan(): void
    {
        $this->buat();

        $kode = collect($this->props(User::factory()->create())['alerts'])->pluck('kode');

        $this->assertTrue($kode->contains('masih-draf'));
        $this->assertTrue($kode->contains('tanpa-data'),
            'Selama belum ada yang disetujui, KPI-nya memang kosong dan itu harus dikatakan.');
    }

    public function test_recovery_rendah_memunculkan_peringatan(): void
    {
        $ktt = User::factory()->create(['lms_role' => 'ktt']);
        $rec = $this->buat();          // 900 dari 1.200 tergali = 75 %
        $rec->ajukan(User::factory()->create());
        $rec->setujui($ktt);

        $kode = collect($this->props($ktt)['alerts'])->pluck('kode');

        $this->assertTrue($kode->contains('recovery-rendah'));
        $this->assertFalse($kode->contains('tanpa-data'));
    }

    public function test_setiap_peringatan_menyebutkan_tindakan(): void
    {
        $this->buat();

        foreach ($this->props(User::factory()->create())['alerts'] as $a) {
            $this->assertArrayHasKey('kode', $a);
            $this->assertNotEmpty($a['saran'] ?? null,
                "Peringatan '{$a['kode']}' tidak menyebutkan apa yang harus dikerjakan.");
        }
    }
}
