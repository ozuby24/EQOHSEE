<?php

namespace App\Support\Miners;

use App\Models\Miners\{InduksiOrang, Kompetensi, McuOrang, Permit, Simper};
use Illuminate\Support\Carbon;

/**
 * Keadaan satu berkas: berlaku, mendekati habis, habis, atau belum ada.
 *
 * SATU TANGGA UNTUK LIMA DOKUMEN. MCU, induksi, Mine Permit, SIMPER,
 * dan sertifikat kompetensi sama-sama ditanya pertanyaan yang sama di
 * gerbang — "hari ini masih berlaku atau tidak" — dan kelimanya
 * sebelumnya menjawabnya dengan cabang `if` masing-masing. Lima
 * salinan satu aturan berarti ambang yang digeser di satu tempat
 * meninggalkan empat yang lain, dan yang tertinggal tidak menimbulkan
 * galat: hanya satu jenis berkas yang diam-diam berhenti diperingatkan.
 *
 * "BELUM" BUKAN "HABIS", dan pembedaan itu yang paling mudah hilang.
 * Orang yang belum pernah MCU dan orang yang MCU-nya kedaluwarsa
 * sama-sama tidak boleh masuk hari ini, tetapi yang harus dikerjakan
 * berbeda: yang satu didaftarkan, yang lain dijadwalkan ulang.
 * Digabung menjadi satu warna merah, daftar kerja OHSE kehilangan
 * perbedaan itu dan keduanya ditangani dengan cara yang salah separuh
 * waktu.
 *
 * "TANPA BATAS" JUGA BUKAN "BERLAKU SELAMANYA" — ia berarti dokumennya
 * memang tidak bermasa berlaku, seperti sertifikat sistem ISO.
 * Diperlakukan sebagai habis hari ini, ia muncul pada daftar yang harus
 * diperbarui tiap hari, selamanya.
 */
final class Keadaan
{
    public const BERLAKU   = 'berlaku';
    public const MENDEKATI = 'mendekati';
    public const HABIS     = 'habis';
    public const BELUM     = 'belum';

    /** Urutan dari yang paling mendesak — dipakai menentukan yang terburuk. */
    public const URUTAN = [self::HABIS, self::BELUM, self::MENDEKATI, self::BERLAKU];

    public const LABEL = [
        self::BERLAKU   => 'Berlaku',
        self::MENDEKATI => 'Segera habis',
        self::HABIS     => 'Habis',
        self::BELUM     => 'Belum ada',
    ];

    /** Warna keadaan, sepadan dengan KEADAAN pada resources/js/Grafik/warna.ts. */
    public const NADA = [
        self::BERLAKU   => 'baik',
        self::MENDEKATI => 'ingat',
        self::HABIS     => 'gawat',
        self::BELUM     => 'netral',
    ];

    /**
     * Relasi pekerja yang dibaca keempat keadaan di bawah — MCU, induksi,
     * Mine Permit, SIMPER — untuk dimuat SEKALI JALAN.
     *
     * Satu daftar untuk semua pemuat massal (dasbor, roster, pemantauan):
     * `induksi.induksi` dibaca `InduksiOrang::sah()`, dan
     * `perpanjanganBerjalan` dibaca `Permit::habisEfektif()` saat tanggal
     * kartu berbeda dari MCU-nya. Tanpa keduanya tiap baris menambah satu
     * kueri, dan dasbor adalah halaman yang paling sering dibuka.
     *
     * @return list<string>
     */
    public static function relasi(): array
    {
        return [
            'mcu', 'induksi.induksi',
            'permit.mcuOrang', 'permit.perpanjanganBerjalan',
            'simper.permit.mcuOrang', 'simper.permit.perpanjanganBerjalan',
        ];
    }

    /**
     * Keadaan sebuah tanggal habis.
     *
     * @param  int  $ambang  berapa hari sebelum habis mulai diperingatkan
     */
    public static function dariTanggal(?Carbon $habis, int $ambang = 30, ?Carbon $pada = null): string
    {
        if ($habis === null) return self::BELUM;

        /* Dinilai pada TANGGAL YANG DIMINTA, bukan selalu hari ini.
           Roster disusun untuk bulan depan, dan berkas yang masih
           berlaku hari ini dapat habis di tengah periode kerja yang
           sedang direncanakan. Dinilai terhadap hari ini saja,
           penyusunnya mendapati seluruh baris hijau lalu menerbitkan
           jadwal yang separuhnya tidak boleh dijalankan. */
        $sisa = \App\Support\Waktu::sisaHari($habis, $pada);

        if ($sisa < 0)        return self::HABIS;
        if ($sisa <= $ambang) return self::MENDEKATI;

        return self::BERLAKU;
    }

    /**
     * Yang paling mendesak di antara beberapa keadaan.
     *
     * Kosong berarti BELUM, bukan BERLAKU. Orang tanpa satu berkas pun
     * bukan orang yang berkasnya lengkap — dan memulangkan "berlaku"
     * bagi daftar kosong adalah cara tercepat meloloskan orang yang
     * belum pernah didaftarkan.
     *
     * @param  iterable<string>  $keadaan
     */
    public static function terburuk(iterable $keadaan): string
    {
        foreach (self::URUTAN as $k) {
            foreach ($keadaan as $satu) {
                if ($satu === $k) return $k;
            }
        }

        return self::BELUM;
    }

    /* ═══════════ per dokumen ═══════════ */

    public static function mcu(?McuOrang $m, ?Carbon $pada = null): string
    {
        return $m === null
            ? self::BELUM
            : self::dariTanggal($m->berlaku_sampai, McuOrang::HARI_PERINGATAN, $pada);
    }

    /**
     * Keadaan sebuah induksi — aturan Safe Track.
     *
     * Sudah diinduksi bila pendaftarannya DISETUJUI dan pesertanya tidak
     * dinyatakan Tidak Lulus (InduksiOrang::sah). Induksi tidak punya
     * masa berlaku: sertifikatnya berlaku selama orangnya bekerja di area
     * operasional IUP penyelenggaranya. Tanggal `berlaku_sampai` lama
     * (aturan SOP sebelumnya) tetap tersimpan, tetapi tidak lagi
     * menentukan apa pun.
     *
     * YANG TIDAK LULUS ADALAH "BELUM", BUKAN "HABIS": yang harus
     * dikerjakan adalah menjadwalkan induksi ulang, bukan memperpanjang
     * sesuatu yang tidak pernah berlaku.
     */
    public static function induksi(?InduksiOrang $i, ?Carbon $pada = null): string
    {
        if ($i === null || ! $i->sah()) return self::BELUM;

        return self::BERLAKU;
    }

    /**
     * Keadaan sebuah Mine Permit — aturan Safe Track.
     *
     * Masa berlakunya MENGIKUTI MCU (atau tanggal yang dikunci admin /
     * hasil perpanjangan) — dibaca lewat `habisEfektif()`, yang membaca
     * tanggal MCU langsung bagi kartu yang mengikutinya.
     *
     * Yang belum terbit bukan yang habis: berkasnya sedang berjalan, dan
     * yang harus dikerjakan adalah menagih peninjaunya. Kartu yang
     * DINONAKTIFKAN admin (dicabut, hilang) tidak lagi berlaku apa pun
     * tanggalnya.
     */
    public static function permit(?Permit $p, ?Carbon $pada = null): string
    {
        if ($p === null) return self::BELUM;

        if (! in_array($p->status, Permit::TERBIT, true)) return self::BELUM;

        if (! $p->aktif) return self::HABIS;

        return self::dariTanggal($p->habisEfektif(), 30, $pada);
    }

    public static function simper(?Simper $s, ?Carbon $pada = null): string
    {
        if ($s === null) return self::BELUM;
        if ($s->status !== 'terbit') return self::BELUM;

        return self::dariTanggal($s->habisEfektif(), 30, $pada);
    }

    public static function kompetensi(?Kompetensi $k, ?Carbon $pada = null): string
    {
        if ($k === null) return self::BELUM;

        /* Tanpa tanggal habis = tidak bermasa berlaku, bukan habis.
           Sertifikat sistem seperti ISO memang begitu. */
        if ($k->berlaku_sampai === null) return self::BERLAKU;

        return self::dariTanggal($k->berlaku_sampai, Kompetensi::HARI_PERINGATAN, $pada);
    }
}
