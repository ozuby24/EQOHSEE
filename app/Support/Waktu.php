<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Waktu tampilan untuk pengguna.
 *
 * Penyimpanan tetap UTC — `config('app.timezone')` sengaja tidak diubah.
 * Mengubahnya berarti seluruh baris yang sudah tersimpan terbaca bergeser
 * beberapa jam: tanggal audit, jam kejadian bahaya, dan waktu terbit
 * dokumen ikut melenceng tanpa ada yang menyunting apa pun. Yang diubah
 * hanyalah cara menampilkannya.
 *
 * Zona tampilan diatur lewat WAKTU_ZONA. Bawaannya Asia/Makassar (WITA),
 * zona lokasi tambang yang memakai sistem ini.
 */
final class Waktu
{
    public static function zona(): string
    {
        return (string) config('waktu.zona', 'Asia/Makassar');
    }

    /** Waktu sekarang menurut zona tampilan. */
    public static function kini(): Carbon
    {
        return Carbon::now(self::zona());
    }

/**
     * Sebuah waktu disiapkan untuk DISIMPAN — selalu UTC.
     *
     * Eloquent menyimpan ANGKA JAM PADA JAM DINDING, bukan saatnya.
     * `Model::fromDateTime()` memanggil `format()` atas Carbon yang
     * diberikan dan tidak memindahkan zonanya lebih dahulu — sehingga
     * Carbon berzona WITA pukul 07.02 tersimpan sebagai untaian
     * "07:02:00" pada kolom yang dibaca sebagai UTC. Dibaca kembali,
     * ia menjadi pukul 15.02 WITA.
     *
     * TIDAK ADA GALAT SATU PUN. Barisnya tersimpan, layarnya menggambar,
     * dan seluruhnya meleset delapan jam: pindaian pukul tujuh pagi
     * tercatat terlambat 540 menit, tap pulang pukul empat sore jatuh
     * ke hari berikutnya sehingga orangnya tercatat "belum tap pulang"
     * selamanya, dan jam kerjanya nol. Terjadi sungguhan — seluruh data
     * contoh absensi yang pertama dibangkitkan begitu.
     *
     * Bedakan dengan tanggal(): kolom DATE memang menyimpan tanggal
     * setempat, jadi di sana yang benar justru jam dinding WITA tengah
     * malam. Yang di sini untuk kolom TIMESTAMP, yang menyimpan saat.
     */
    public static function simpan(\DateTimeInterface|string|null $waktu): ?Carbon
    {
        if ($waktu === null || $waktu === '') return null;

        return Carbon::parse($waktu)->utc();
    }

    /** Waktu sekarang, siap disimpan. */
    public static function kiniSimpan(): Carbon
    {
        return Carbon::now('UTC');
    }

    /** Sebuah waktu dipindah ke zona tampilan; null tetap null. */
    public static function lokal($waktu): ?Carbon
    {
        if ($waktu === null || $waktu === '') return null;

        return Carbon::parse($waktu)->setTimezone(self::zona());
    }

    /**
     * Sapaan menurut jam setempat.
     *
     * Sempat dihitung dari jam server yang berjalan UTC, sehingga pukul
     * 18.00 WITA disapa "Selamat pagi" — salah delapan jam, dan justru
     * bagian halaman yang paling pertama dibaca orang.
     */
    /**
     * Tanggal WITA tengah malam dari isian formulir.
     *
     * Cast `date` Laravel memangkas jam saat DIBACA, tidak saat
     * DITULIS — sehingga "2026-09-02T14:30" yang lolos aturan `date`
     * tersimpan berikut jamnya pada kolom bertipe DATE. MySQL
     * memangkasnya di tingkat kolom, SQLite tidak, dan kueri rentang
     * karena itu menjawab BERBEDA di server dan di mesin penguji.
     * Lihat KolomTanggalTest.
     */
    public static function tanggal(string|\DateTimeInterface|null $nilai): ?Carbon
    {
        if ($nilai === null || $nilai === '') return null;

        return Carbon::parse($nilai, self::zona())->startOfDay();
    }

    public static function sapaan(?Carbon $saat = null): string
    {
        $jam = (int) ($saat ?? self::kini())->format('G');

        return match (true) {
            $jam < 11 => 'Selamat pagi',
            $jam < 15 => 'Selamat siang',
            $jam < 19 => 'Selamat sore',
            default   => 'Selamat malam',
        };
    }

    /** Singkatan zona waktu Indonesia: WIB, WITA, atau WIT. */
    public static function singkatan(): string
    {
        return match (self::zona()) {
            'Asia/Jakarta'  => 'WIB',
            'Asia/Makassar' => 'WITA',
            'Asia/Jayapura' => 'WIT',
            default         => self::kini()->format('T'),
        };
    }
}
