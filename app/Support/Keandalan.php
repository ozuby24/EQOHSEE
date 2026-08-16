<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Ukuran keandalan armada: MTBF, MTTR, ketersediaan, dan kepatuhan PM.
 *
 * Angka-angka ini gampang keliru dengan cara yang tidak pernah terlihat
 * salah. Dua kekeliruan yang paling sering, dan yang dijaga di sini:
 *
 * 1. MTTR dan waktu henti disamakan. MTTR adalah lama perbaikannya —
 *    sejak montir mulai sampai alat kembali jalan. Waktu henti adalah
 *    sejak alat berhenti berproduksi sampai jalan lagi, termasuk
 *    menunggu suku cadang, menunggu montir, dan menunggu giliran derek.
 *    Keduanya dilaporkan terpisah, sebab keduanya menuntut perbaikan
 *    yang berbeda: MTTR panjang berarti pekerjaannya sulit, waktu
 *    tunggu panjang berarti persiapannya yang kurang. Menggabungkannya
 *    membuat bengkel disalahkan atas gudang yang kosong.
 *
 * 2. MTBF dihitung dari lamanya periode, bukan dari waktu alat benar-
 *    benar jalan. Armada dua puluh unit yang setengahnya rusak sebulan
 *    penuh akan terlihat andal bila penyebutnya lamanya kalender.
 *
 * Seluruh masukan berupa jam. Pemanggil yang mengubah stempel waktu
 * menjadi jam, bukan kelas ini, sebab hanya pemanggil yang tahu jam
 * kerja mana yang berlaku di site-nya.
 */
final class Keandalan
{
    /**
     * @param float $jamTersedia total jam alat seharusnya dapat bekerja
     *                           (jumlah unit × jam dalam periode)
     * @param float $jamHenti    total jam alat tidak dapat bekerja
     * @param int   $kegagalan   jumlah gangguan korektif pada periode
     * @param float $jamPerbaikan total jam pengerjaan perbaikan
     */
    public function __construct(
        private readonly float $jamTersedia,
        private readonly float $jamHenti,
        private readonly int $kegagalan,
        private readonly float $jamPerbaikan,
    ) {}

    /** Jam alat benar-benar dapat bekerja. */
    public function jamJalan(): float
    {
        return max(0.0, $this->jamTersedia - $this->jamHenti);
    }

    /**
     * Ketersediaan: bagian waktu alat siap bekerja.
     *
     * Tanpa jam tersedia sama sekali hasilnya nol, bukan seratus.
     * Armada yang belum pernah didaftarkan bukanlah armada yang
     * sempurna tersedia.
     */
    public function ketersediaan(): float
    {
        return $this->jamTersedia > 0
            ? $this->jamJalan() / $this->jamTersedia * 100
            : 0.0;
    }

    /**
     * MTBF — rata-rata jam jalan antara dua kegagalan.
     *
     * Penyebutnya jam jalan, bukan lamanya periode: armada yang
     * separuhnya mati sebulan penuh tidak boleh terlihat andal.
     * Tanpa kegagalan sama sekali nilainya null, bukan nol — nol
     * berarti "rusak terus-menerus", kebalikan dari maksudnya.
     */
    public function mtbf(): ?float
    {
        return $this->kegagalan > 0 ? $this->jamJalan() / $this->kegagalan : null;
    }

    /** MTTR — rata-rata lama pengerjaan perbaikan. */
    public function mttr(): ?float
    {
        return $this->kegagalan > 0 ? $this->jamPerbaikan / $this->kegagalan : null;
    }

    /** Rata-rata waktu henti, termasuk menunggu. */
    public function waktuHentiRata(): ?float
    {
        return $this->kegagalan > 0 ? $this->jamHenti / $this->kegagalan : null;
    }

    /**
     * Jam yang habis menunggu, bukan mengerjakan.
     *
     * Selisih inilah yang menunjuk ke gudang dan penjadwalan alih-alih
     * ke bengkel. Ia sering jauh lebih besar daripada jam perbaikannya
     * sendiri, dan hampir tidak pernah dilaporkan karena kedua angkanya
     * biasa digabung.
     */
    public function jamMenunggu(): float
    {
        return max(0.0, $this->jamHenti - $this->jamPerbaikan);
    }

    public function porsiMenunggu(): float
    {
        return $this->jamHenti > 0 ? $this->jamMenunggu() / $this->jamHenti * 100 : 0.0;
    }

    public function toArray(): array
    {
        return [
            'jamTersedia'     => round($this->jamTersedia, 2),
            'jamJalan'        => round($this->jamJalan(), 2),
            'jamHenti'        => round($this->jamHenti, 2),
            'jamPerbaikan'    => round($this->jamPerbaikan, 2),
            'jamMenunggu'     => round($this->jamMenunggu(), 2),
            'porsiMenunggu'   => round($this->porsiMenunggu(), 1),
            'kegagalan'       => $this->kegagalan,
            'ketersediaan'    => round($this->ketersediaan(), 2),
            'mtbf'            => $this->mtbf() !== null ? round($this->mtbf(), 2) : null,
            'mttr'            => $this->mttr() !== null ? round($this->mttr(), 2) : null,
            'waktuHentiRata'  => $this->waktuHentiRata() !== null ? round($this->waktuHentiRata(), 2) : null,
        ];
    }

    /**
     * Kepatuhan perawatan berkala.
     *
     * Mengikuti kaidah yang sudah dipakai modul Keselamatan Operasi:
     * mencatat PM memajukan `pm_berikutnya`, sehingga sebuah alat
     * dikatakan terlewat bila tanggal itu sudah lampau dan belum
     * dimajukan. Memeriksanya dengan membandingkan `pm_terakhir`
     * terhadap `pm_berikutnya` akan menandai seluruh armada terlambat,
     * sebab PM yang baru saja dikerjakan memang selalu berjadwal
     * berikutnya di masa depan.
     *
     * Yang tidak dapat dijawab dari data ini adalah apakah sebuah PM
     * dahulu dikerjakan tepat waktu: memajukan tanggal menghapus jejak
     * keterlambatannya. Karena itu yang dilaporkan adalah keadaan saat
     * ini, bukan riwayat kepatuhan — menyebutnya riwayat berarti
     * menjanjikan sesuatu yang angkanya tidak mengandungnya.
     *
     * @param Collection<int,object> $objek benda yang punya pm_berikutnya
     */
    public static function kepatuhanPm(Collection $objek, \DateTimeInterface $sampai): array
    {
        $berjadwal = $objek->filter(fn ($o) => $o->pm_berikutnya !== null);

        $terlambat = $berjadwal->filter(
            fn ($o) => \Illuminate\Support\Carbon::parse($o->pm_berikutnya)->startOfDay()
                ->lt(\Illuminate\Support\Carbon::parse($sampai)->startOfDay())
        );

        $n = $berjadwal->count();
        $telat = $terlambat->count();

        return [
            'berjadwal' => $n,
            'terlambat' => $telat,
            'patuh'     => $n - $telat,
            /* NULL, bukan 100, ketika tidak ada satu pun alat berjadwal.
               Nol dibagi nol bukan kepatuhan sempurna — ia ketiadaan
               ukuran, dan keduanya harus terbaca berbeda. "Kepatuhan PM
               100%" pada armada yang belum punya satu pun alat terdaftar
               adalah angka yang dapat ditangkap layar lalu masuk laporan
               kepatuhan; "—" tidak dapat.

               Alasannya sudah tertulis pada catatan di bawah — alat tanpa
               jadwal bukan alat yang patuh maupun tidak patuh — dan
               nilai 100 di sini justru melanggarnya. */
            'persen'    => $n > 0 ? round(($n - $telat) / $n * 100, 1) : null,

            // Dilaporkan terpisah, tidak dilebur ke persentase. Alat tanpa
            // jadwal bukan alat yang patuh maupun tidak patuh — ia alat
            // yang belum diputuskan, dan itu keadaan tersendiri yang
            // hilang begitu ia ikut dibagi.
            'tanpaJadwal' => $objek->count() - $n,
        ];
    }
}
