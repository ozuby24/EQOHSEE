<?php

namespace Tests\Feature;

use App\Models\{MineOperationalRecord, User};
use App\Support\Alur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alur tinjauan: draf → diajukan → disetujui atau ditolak.
 *
 * Yang diuji di sini bukan sekadar perpindahan status, melainkan tiga
 * hal yang sebelumnya gagal tanpa menimbulkan galat apa pun: status yang
 * dapat disebut sendiri oleh pengirim data, penyetujuan atas pekerjaan
 * sendiri, dan angka yang belum ditinjau tetapi sudah ikut terhitung.
 */
class AlurTinjauanTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        return User::factory()->create(['is_admin' => false]);
    }

    private function ktt(): User
    {
        return User::factory()->create(['is_admin' => false, 'lms_role' => 'ktt']);
    }

    private function isian(array $ganti = []): array
    {
        return array_merge([
            'tanggal' => '2026-08-14', 'shift' => 'siang', 'material' => 'Batubara',
            'produksi_ton' => 1000, 'overburden_bcm' => 5000, 'jarak_angkut_km' => 3,
            'jumlah_truk' => 10, 'jumlah_excavator' => 2,
            'jam_operasi' => 10, 'jam_delay' => 2,
        ], $ganti);
    }

    private function buat(User $u): MineOperationalRecord
    {
        $this->actingAs($u)->post(route('operasi.record.simpan'), $this->isian())
            ->assertSessionHasNoErrors();

        return MineOperationalRecord::withoutGlobalScopes()->latest('id')->firstOrFail();
    }

    /* ---------- celah yang ditutup ---------- */

    public function test_status_tidak_dapat_disebut_sendiri_lewat_isian(): void
    {
        $this->actingAs($this->operator())
            ->post(route('operasi.record.simpan'), $this->isian(['status' => Alur::DISETUJUI]));

        $rec = MineOperationalRecord::withoutGlobalScopes()->firstOrFail();

        $this->assertSame(Alur::DRAF, $rec->status,
            'Isian formulir tidak boleh dapat menentukan status.');
    }

    public function test_pengaju_tidak_dapat_menyetujui_pekerjaannya_sendiri(): void
    {
        // KTT memegang hak meninjau, tetapi di sini ia pengajunya sendiri.
        $ktt = $this->ktt();
        $rec = $this->buat($ktt);
        $rec->ajukan($ktt);

        $this->actingAs($ktt)
            ->post(route('operasi.record.setujui', $rec))
            ->assertSessionHasErrors('alur');

        $this->assertSame(Alur::DIAJUKAN, $rec->fresh()->status);
    }

    public function test_operator_biasa_tidak_dapat_menyetujui(): void
    {
        $rec = $this->buat($this->operator());
        $rec->ajukan();

        $this->actingAs($this->operator())
            ->post(route('operasi.record.setujui', $rec))
            ->assertSessionHasErrors('alur');

        $this->assertSame(Alur::DIAJUKAN, $rec->fresh()->status);
    }

    public function test_angka_yang_belum_disetujui_tidak_terhitung_di_kpi(): void
    {
        $this->buat($this->operator());   // tetap draf

        $props = $this->actingAs($this->operator())
            ->get(route('operasi.index'))->assertOk()
            ->viewData('page')['props'];

        $this->assertSame(0.0, (float) $props['ringkas']['produksi'],
            'Draf tidak boleh ikut terhitung.');
        $this->assertCount(1, $props['records'],
            'Tetapi draf harus tetap terlihat di daftar oleh pengajunya.');
    }

    public function test_angka_yang_sudah_disetujui_terhitung_di_kpi(): void
    {
        $rec = $this->buat($this->operator());
        $rec->ajukan();
        $rec->setujui($this->ktt());

        $props = $this->actingAs($this->operator())
            ->get(route('operasi.index'))->assertOk()
            ->viewData('page')['props'];

        $this->assertSame(1000.0, (float) $props['ringkas']['produksi']);
    }

    /* ---------- perpindahan ---------- */

    public function test_alur_penuh_dari_draf_sampai_disetujui(): void
    {
        $operator = $this->operator();
        $ktt = $this->ktt();
        $rec = $this->buat($operator);

        $this->assertSame(Alur::DRAF, $rec->status);

        $this->actingAs($operator)->post(route('operasi.record.ajukan', $rec))
            ->assertSessionHasNoErrors();
        $rec->refresh();
        $this->assertSame(Alur::DIAJUKAN, $rec->status);
        $this->assertSame($operator->id, $rec->diajukan_oleh);
        $this->assertNotNull($rec->diajukan_pada);

        $this->actingAs($ktt)->post(route('operasi.record.setujui', $rec))
            ->assertSessionHasNoErrors();
        $rec->refresh();
        $this->assertSame(Alur::DISETUJUI, $rec->status);
        $this->assertSame($ktt->id, $rec->ditinjau_oleh);
        $this->assertNotNull($rec->ditinjau_pada);
    }

    public function test_penolakan_wajib_menyertakan_alasan(): void
    {
        $rec = $this->buat($this->operator());
        $rec->ajukan();

        $this->actingAs($this->ktt())
            ->post(route('operasi.record.tolak', $rec), ['alasan_tolak' => ''])
            ->assertSessionHasErrors('alasan_tolak');

        $this->assertSame(Alur::DIAJUKAN, $rec->fresh()->status);
    }

    public function test_yang_ditolak_kembali_menjadi_draf_beserta_alasannya(): void
    {
        $rec = $this->buat($this->operator());
        $rec->ajukan();

        $this->actingAs($this->ktt())
            ->post(route('operasi.record.tolak', $rec), ['alasan_tolak' => 'Jam delay tidak sesuai laporan shift.'])
            ->assertSessionHasNoErrors();

        $rec->refresh();
        $this->assertSame(Alur::DITOLAK, $rec->status);
        $this->assertSame('Jam delay tidak sesuai laporan shift.', $rec->alasan_tolak);
        $this->assertTrue($rec->dapatDiubah(), 'Yang ditolak harus dapat diperbaiki.');
    }

    public function test_yang_sudah_disetujui_terkunci_dari_perubahan(): void
    {
        $rec = $this->buat($this->operator());
        $rec->ajukan();
        $rec->setujui($this->ktt());

        $this->expectException(\RuntimeException::class);
        $rec->update(['produksi_ton' => 999999]);
    }

    public function test_yang_sudah_disetujui_tidak_dapat_dihapus(): void
    {
        $rec = $this->buat($this->operator());
        $rec->ajukan();
        $rec->setujui($this->ktt());

        $this->actingAs(User::factory()->create(['is_admin' => true]))
            ->delete(route('operasi.record.hapus', $rec))
            ->assertSessionHasErrors('alur');

        $this->assertDatabaseHas('mine_operational_records', ['id' => $rec->id]);
    }

    public function test_perpindahan_yang_tidak_masuk_akal_ditolak(): void
    {
        $rec = $this->buat($this->operator());

        // Draf tidak dapat langsung disetujui tanpa diajukan lebih dulu.
        $this->expectException(\RuntimeException::class);
        $rec->setujui($this->ktt());
    }
}
