<?php

namespace App\Support;

/**
 * Neraca air kolam tambang: hujan masuk, pompa keluar, sisa waktu.
 *
 * Pertanyaan yang dijawab: kalau hujan sebesar ini turun pada daerah
 * tangkapan seluas ini, berapa lama sampai kolamnya melimpah, dan
 * kapasitas pompa berapa yang diperlukan supaya tidak.
 *
 * Satu perubahan satuan menjadi sumber kekeliruan yang paling sering:
 * satu milimeter hujan di atas satu hektare adalah SEPULUH meter kubik,
 * bukan satu. Kekeliruan sepuluh kali lipat itu tidak menimbulkan galat
 * apa pun — ia hanya menghasilkan angka jam yang tetap terlihat masuk
 * akal, sampai kolamnya benar-benar melimpah pada hujan yang menurut
 * hitungan masih aman.
 *
 * Koefisien limpasan menyatakan bagian hujan yang benar-benar mengalir
 * ke kolam; sisanya meresap dan menguap. Pada area tambang yang tanahnya
 * padat dan terbuka nilainya tinggi — jauh lebih tinggi daripada hutan
 * di sekitarnya — dan memakai angka hutan pada bukaan tambang membuat
 * seluruh perkiraan terlalu optimis.
 */
final class NeracaAir
{
    /** Meter kubik per hektare untuk setiap milimeter hujan. */
    public const M3_PER_HA_PER_MM = 10.0;

    /**
     * Koefisien limpasan bawaan untuk bukaan tambang.
     *
     * Dipakai hanya bila kolamnya belum menetapkan nilainya sendiri.
     * Angka bawaan yang terlalu rendah membuat perkiraan tampak aman
     * pada hujan yang sebenarnya sudah melampaui kapasitas.
     */
    public const KOEFISIEN_BAWAAN = 0.8;

    public function __construct(
        private readonly float $kapasitasM3,
        private readonly float $volumeSekarangM3,
        private readonly float $luasTangkapanHa,
        private readonly float $koefisienLimpasan = self::KOEFISIEN_BAWAAN,
    ) {}

    /** Volume limpasan dari sebuah kejadian hujan, meter kubik. */
    public function limpasan(float $hujanMm): float
    {
        return max(0.0, $hujanMm) * $this->luasTangkapanHa
            * self::M3_PER_HA_PER_MM * $this->koefisienLimpasan;
    }

    public function ruangKosong(): float
    {
        return max(0.0, $this->kapasitasM3 - $this->volumeSekarangM3);
    }

    public function terisiPersen(): float
    {
        return $this->kapasitasM3 > 0
            ? min(100.0, max(0.0, $this->volumeSekarangM3 / $this->kapasitasM3 * 100))
            : 0.0;
    }

    /**
     * Hujan terbesar yang masih tertampung tanpa memompa, milimeter.
     *
     * Angka inilah yang paling langsung dipakai di lapangan: ia dapat
     * dibandingkan begitu saja dengan ramalan cuaca.
     */
    public function hujanTertampungMm(): float
    {
        $penyebut = $this->luasTangkapanHa * self::M3_PER_HA_PER_MM * $this->koefisienLimpasan;

        return $penyebut > 0 ? round($this->ruangKosong() / $penyebut, 1) : 0.0;
    }

    /**
     * Jam sampai melimpah pada laju masuk dan keluar tertentu.
     *
     * Mengembalikan null bila pompanya mengimbangi atau melebihi laju
     * masuk — kolam yang tidak akan melimpah tidak punya "waktu sampai
     * melimpah", dan menuliskannya nol berarti sebaliknya: melimpah
     * sekarang juga.
     */
    public function jamSampaiLimpah(float $masukM3PerJam, float $keluarM3PerJam): ?float
    {
        $bersih = $masukM3PerJam - $keluarM3PerJam;

        if ($bersih <= 0) return null;

        return round($this->ruangKosong() / $bersih, 2);
    }

    /**
     * Jam sampai kolam kosong bila pemompaan diteruskan.
     *
     * Null bila pompanya tidak mengejar laju masuk; kolam yang terus
     * naik tidak punya waktu pengosongan.
     */
    public function jamSampaiKosong(float $masukM3PerJam, float $keluarM3PerJam): ?float
    {
        $bersih = $keluarM3PerJam - $masukM3PerJam;

        if ($bersih <= 0) return null;

        return round($this->volumeSekarangM3 / $bersih, 2);
    }

    /**
     * Kapasitas pompa yang diperlukan agar sebuah kejadian hujan habis
     * dipompa dalam jangka waktu tertentu, meter kubik per jam.
     *
     * Ruang kosong yang tersedia ikut dikurangkan: sebagian hujan boleh
     * ditampung, dan hanya kelebihannya yang wajib dipompa. Mengabaikan
     * ruang kosong menghasilkan kebutuhan pompa yang jauh lebih besar
     * daripada yang sebenarnya perlu dibeli.
     */
    public function pompaDibutuhkan(float $hujanMm, float $dalamJam): float
    {
        if ($dalamJam <= 0) return 0.0;

        $kelebihan = max(0.0, $this->limpasan($hujanMm) - $this->ruangKosong());

        return round($kelebihan / $dalamJam, 2);
    }

    /**
     * Apakah sebuah kejadian hujan akan melampaui kolam.
     *
     * Dihitung tanpa pemompaan: pertanyaannya adalah apakah kolamnya
     * sanggup menahan sendiri, sebab pompa dapat mati justru ketika
     * hujan paling deras.
     */
    public function akanLimpah(float $hujanMm): bool
    {
        return $this->limpasan($hujanMm) > $this->ruangKosong();
    }

    public function toArray(float $hujanRencanaMm = 50.0): array
    {
        return [
            'kapasitas'        => round($this->kapasitasM3, 2),
            'volume'           => round($this->volumeSekarangM3, 2),
            'ruangKosong'      => round($this->ruangKosong(), 2),
            'terisiPersen'     => round($this->terisiPersen(), 1),
            'luasTangkapan'    => $this->luasTangkapanHa,
            'koefisien'        => $this->koefisienLimpasan,
            'hujanTertampung'  => $this->hujanTertampungMm(),
            'hujanRencana'     => $hujanRencanaMm,
            'limpasanRencana'  => round($this->limpasan($hujanRencanaMm), 2),
            'akanLimpah'       => $this->akanLimpah($hujanRencanaMm),
        ];
    }
}
