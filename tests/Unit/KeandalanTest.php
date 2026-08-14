<?php

namespace Tests\Unit;

use App\Support\Keandalan;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Ukuran keandalan armada.
 *
 * Angka-angka ini keliru dengan cara yang tidak pernah terlihat salah,
 * jadi yang diuji adalah persis titik-titik kelirunya.
 */
class KeandalanTest extends TestCase
{
    /** Sebulan, 20 unit, 24 jam sehari. */
    private function armada(float $henti, int $gagal, float $perbaikan): Keandalan
    {
        return new Keandalan(20 * 30 * 24, $henti, $gagal, $perbaikan);
    }

    public function test_ketersediaan_dan_jam_jalan(): void
    {
        $k = $this->armada(1440, 10, 800);   // 1.440 dari 14.400 jam

        $this->assertSame(12960.0, $k->jamJalan());
        $this->assertEqualsWithDelta(90.0, $k->ketersediaan(), 0.01);
    }

    public function test_mtbf_memakai_jam_jalan_bukan_lamanya_periode(): void
    {
        // Armada yang separuhnya mati sebulan penuh: 7.200 jam henti.
        // Dengan penyebut jam kalender, MTBF akan terlihat 14.400/10 =
        // 1.440 jam; dengan jam jalan yang benar, 720 jam.
        $k = $this->armada(7200, 10, 5000);

        $this->assertSame(720.0, $k->mtbf());
    }

    public function test_tanpa_kegagalan_mtbf_null_bukan_nol(): void
    {
        $k = $this->armada(0, 0, 0);

        $this->assertNull($k->mtbf(), 'Nol berarti rusak terus-menerus, kebalikan dari maksudnya.');
        $this->assertNull($k->mttr());
        $this->assertSame(100.0, $k->ketersediaan());
    }

    public function test_mttr_dan_waktu_henti_dibedakan(): void
    {
        // Sepuluh gangguan: 800 jam dikerjakan, 1.440 jam alat berhenti.
        // Selisih 640 jam habis menunggu — suku cadang, montir, giliran.
        $k = $this->armada(1440, 10, 800);

        $this->assertSame(80.0, $k->mttr(), 'MTTR adalah lama pengerjaannya.');
        $this->assertSame(144.0, $k->waktuHentiRata(), 'Waktu henti termasuk menunggu.');
        $this->assertSame(640.0, $k->jamMenunggu());
        $this->assertEqualsWithDelta(44.4, $k->porsiMenunggu(), 0.1);
    }

    public function test_menunggu_tidak_pernah_negatif(): void
    {
        // Data kotor: jam perbaikan tercatat lebih besar daripada jam
        // henti, misalnya dua montir bekerja serentak dan jamnya
        // dijumlahkan. Angkanya tidak boleh menjadi negatif dan
        // menular ke persentase.
        $k = $this->armada(100, 5, 400);

        $this->assertSame(0.0, $k->jamMenunggu());
        $this->assertSame(0.0, $k->porsiMenunggu());
    }

    public function test_armada_kosong_bukan_seratus_persen_tersedia(): void
    {
        $k = new Keandalan(0, 0, 0, 0);

        $this->assertSame(0.0, $k->ketersediaan(),
            'Armada yang belum didaftarkan bukan armada yang sempurna tersedia.');
    }

    public function test_henti_melebihi_tersedia_tidak_membuat_jam_jalan_negatif(): void
    {
        $k = new Keandalan(100, 250, 3, 200);

        $this->assertSame(0.0, $k->jamJalan());
        $this->assertSame(0.0, $k->ketersediaan());
    }

    /* ---------- kepatuhan PM ---------- */

    private function objek(?string $berikutnya, ?string $terakhir): object
    {
        return (object) [
            'pm_berikutnya' => $berikutnya ? Carbon::parse($berikutnya) : null,
            'pm_terakhir'   => $terakhir ? Carbon::parse($terakhir) : null,
        ];
    }

    public function test_alat_yang_jadwalnya_belum_lewat_dihitung_patuh(): void
    {
        $hasil = Keandalan::kepatuhanPm(collect([
            $this->objek('2026-09-10', '2026-08-09'),
        ]), Carbon::parse('2026-08-31'));

        $this->assertSame(1, $hasil['patuh']);
        $this->assertSame(100.0, $hasil['persen']);
    }

    public function test_jadwal_yang_sudah_lewat_dihitung_terlambat(): void
    {
        $hasil = Keandalan::kepatuhanPm(collect([
            $this->objek('2026-08-10', null),
            $this->objek('2026-08-15', '2026-07-01'),
        ]), Carbon::parse('2026-08-31'));

        $this->assertSame(2, $hasil['terlambat']);
        $this->assertSame(0.0, $hasil['persen']);
    }

    public function test_pm_yang_baru_dikerjakan_tidak_salah_dibaca_terlambat(): void
    {
        // Mencatat PM memajukan pm_berikutnya, sehingga pm_terakhir
        // memang selalu lebih awal. Memeriksanya dengan membandingkan
        // keduanya akan menandai seluruh armada terlambat.
        $hasil = Keandalan::kepatuhanPm(collect([
            $this->objek('2026-11-30', '2026-08-30'),
        ]), Carbon::parse('2026-08-31'));

        $this->assertSame(0, $hasil['terlambat']);
    }

    public function test_alat_tanpa_jadwal_pm_dilaporkan_terpisah(): void
    {
        $hasil = Keandalan::kepatuhanPm(collect([
            $this->objek('2026-09-10', '2026-08-09'),
            $this->objek(null, null),
            $this->objek(null, null),
        ]), Carbon::parse('2026-08-31'));

        $this->assertSame(1, $hasil['berjadwal']);
        $this->assertSame(2, $hasil['tanpaJadwal'],
            'Alat tanpa jadwal tidak boleh menaikkan maupun menurunkan kepatuhan diam-diam.');
        $this->assertSame(100.0, $hasil['persen']);
    }
}
