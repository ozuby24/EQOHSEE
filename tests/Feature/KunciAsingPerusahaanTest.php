<?php

namespace Tests\Feature;

use App\Models\{Company, GeoBacaan, GeoLereng, User, WaterLog, WaterSump};
use App\Rules\DalamPerusahaan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Kunci asing tidak boleh menunjuk milik perusahaan lain.
 *
 * `exists:tabel,id` bawaan hanya bertanya "adakah barisnya", tidak
 * pernah "bolehkah orang ini menyentuhnya". Pada pemasangan yang
 * dipakai beberapa perusahaan kedua pertanyaan itu berbeda jauh.
 *
 * Terbukti sebelum aturan DalamPerusahaan ada: pengguna perusahaan A
 * mengirim water_sump_id milik B, dan catatan airnya tersimpan —
 * barisnya sendiri benar tercatat milik A, tetapi menggantung pada
 * sump milik B.
 *
 * Yang rusak BUKAN kerahasiaan. Scope pembacaan tetap menahan nama
 * sump B agar tidak muncul di halaman A, dan itu sudah diperiksa. Yang
 * rusak keutuhan datanya — dan justru itu yang lebih sulit ditemukan:
 * barisnya tidak hilang, tidak melempar galat, tidak terlihat salah di
 * mana pun. Ia hanya membuat rekapitulasi menyebut angka yang tidak
 * dapat dijelaskan siapa pun enam bulan kemudian.
 *
 * Tiap uji serangan di sini didampingi uji KENDALI dengan induk milik
 * sendiri. Tanpa itu, "ditolak" tidak dapat dibedakan dari "payload
 * ujinya memang salah" — dan versi pertama uji ini persis begitu:
 * ketiganya tampak aman, padahal tak satu pun pernah tersimpan.
 */
class KunciAsingPerusahaanTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;
    private Company $b;
    private User $ua;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a  = Company::create(['name' => 'PT A']);
        $this->b  = Company::create(['name' => 'PT B']);
        $this->ua = User::factory()->create([
            'company_id' => $this->a->id, 'is_admin' => false, 'email_verified_at' => now(),
        ]);
    }

    /** @return array{0:WaterSump,1:WaterSump} */
    private function sump(): array
    {
        return [
            WaterSump::withoutGlobalScopes()->create(['company_id' => $this->a->id, 'kode' => 'SA', 'nama' => 'Sump A']),
            WaterSump::withoutGlobalScopes()->create(['company_id' => $this->b->id, 'kode' => 'SB', 'nama' => 'Sump B']),
        ];
    }

    /** @return array<string,mixed> */
    private function isiAir(WaterSump $s): array
    {
        return [
            'water_sump_id' => $s->id, 'tanggal' => now()->toDateString(),
            'curah_hujan_mm' => 10, 'volume_m3' => 500,
            'debit_masuk_m3' => 50, 'debit_keluar_m3' => 40, 'jam_pompa' => 4,
        ];
    }

    /* ═══════════ penirisan ═══════════ */

    public function test_catatan_air_tidak_dapat_ditempelkan_ke_sump_perusahaan_lain(): void
    {
        [$sumpA, $sumpB] = $this->sump();

        // KENDALI: induk sendiri harus berhasil, kalau tidak uji ini hampa.
        $this->actingAs($this->ua)->post(route('air.catatan.simpan'), $this->isiAir($sumpA));

        $this->assertTrue(
            WaterLog::withoutGlobalScopes()->where('water_sump_id', $sumpA->id)->exists(),
            'Catatan pada sump sendiri pun tidak tersimpan — payload uji ini salah, '
            .'dan bagian serangannya tidak membuktikan apa pun.');

        // SERANGAN
        $this->actingAs($this->ua)->post(route('air.catatan.simpan'), $this->isiAir($sumpB));

        $this->assertFalse(
            WaterLog::withoutGlobalScopes()->where('water_sump_id', $sumpB->id)->exists(),
            'Catatan air menggantung pada sump milik perusahaan lain.');
    }

    /* ═══════════ geoteknik ═══════════ */

    public function test_bacaan_tidak_dapat_ditempelkan_ke_lereng_perusahaan_lain(): void
    {
        $lerA = GeoLereng::withoutGlobalScopes()->create(['company_id' => $this->a->id, 'kode' => 'LA', 'nama' => 'Lereng A']);
        $lerB = GeoLereng::withoutGlobalScopes()->create(['company_id' => $this->b->id, 'kode' => 'LB', 'nama' => 'Lereng B']);

        $isi = fn ($l) => ['geo_lereng_id' => $l->id, 'tanggal' => now()->toDateString(), 'perpindahan_mm' => 3];

        $this->actingAs($this->ua)->post(route('geoteknik.bacaan.simpan'), $isi($lerA));
        $this->assertTrue(GeoBacaan::withoutGlobalScopes()->where('geo_lereng_id', $lerA->id)->exists(),
            'Bacaan pada lereng sendiri pun gagal — payload uji ini salah.');

        $this->actingAs($this->ua)->post(route('geoteknik.bacaan.simpan'), $isi($lerB));
        $this->assertFalse(GeoBacaan::withoutGlobalScopes()->where('geo_lereng_id', $lerB->id)->exists(),
            'Bacaan geoteknik menggantung pada lereng milik perusahaan lain.');
    }

    /* ═══════════ aturannya sendiri ═══════════ */

    public function test_aturan_menerima_milik_sendiri_menolak_milik_orang_lain(): void
    {
        [$sumpA, $sumpB] = $this->sump();

        $this->actingAs($this->ua);

        $periksa = fn ($id) => Validator::make(
            ['x' => $id], ['x' => [new DalamPerusahaan('water_sumps')]],
        )->passes();

        $this->assertTrue($periksa($sumpA->id));
        $this->assertFalse($periksa($sumpB->id));
    }

    /**
     * Baris tanpa perusahaan diterima.
     *
     * Mengikuti arti yang sudah dipakai scope MilikPerusahaan: yang
     * belum dimiliki siapa pun adalah milik bersama. Menolaknya akan
     * membuat seluruh data yang dibuat sebelum penempatan perusahaan
     * ada mendadak tidak dapat dirujuk lagi.
     */
    public function test_baris_tanpa_perusahaan_diterima(): void
    {
        $bersama = WaterSump::withoutGlobalScopes()->create(['company_id' => null, 'kode' => 'S0', 'nama' => 'Sump lama']);

        $this->actingAs($this->ua);

        $this->assertTrue(Validator::make(
            ['x' => $bersama->id], ['x' => [new DalamPerusahaan('water_sumps')]],
        )->passes(), 'Data lama tanpa perusahaan mendadak tidak dapat dirujuk.');
    }

    /** Administrator menjangkau seluruh perusahaan, seperti di tempat lain. */
    public function test_administrator_boleh_merujuk_perusahaan_mana_pun(): void
    {
        [, $sumpB] = $this->sump();

        $this->actingAs(User::factory()->create(['is_admin' => true, 'company_id' => $this->a->id]));

        $this->assertTrue(Validator::make(
            ['x' => $sumpB->id], ['x' => [new DalamPerusahaan('water_sumps')]],
        )->passes(), 'Administrator kehilangan jangkauan lintas perusahaan.');
    }

    /**
     * Pesan penolakannya tidak boleh membedakan "tidak ada" dari "milik
     * orang lain".
     *
     * Membedakannya memberi tahu penebak bahwa nomor itu ADA — cukup
     * untuk memetakan berapa banyak sump yang dimiliki perusahaan lain
     * hanya dengan mencoba nomor berurutan.
     */
    public function test_pesan_tidak_membocorkan_keberadaan_baris(): void
    {
        [, $sumpB] = $this->sump();

        $this->actingAs($this->ua);

        $pesan = fn ($id) => Validator::make(
            ['x' => $id], ['x' => [new DalamPerusahaan('water_sumps')]],
        )->errors()->first('x');

        $this->assertSame($pesan(999999), $pesan($sumpB->id),
            'Pesan berbeda antara baris yang tidak ada dan baris milik perusahaan lain.');
    }
}
