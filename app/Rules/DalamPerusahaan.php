<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Kunci asing harus menunjuk baris yang boleh disentuh pengirimnya.
 *
 * Aturan `exists:tabel,id` bawaan hanya bertanya "adakah barisnya",
 * tidak pernah "bolehkah orang ini menyentuhnya". Pada pemasangan yang
 * dipakai beberapa perusahaan, kedua pertanyaan itu berbeda jauh, dan
 * yang kedua tidak pernah ditanyakan siapa pun.
 *
 * Terbukti sebelum berkas ini ada: pengguna perusahaan A mengirim
 * `water_sump_id` milik perusahaan B, dan catatan airnya tersimpan —
 * barisnya sendiri benar tercatat sebagai milik A, tetapi menggantung
 * pada sump milik B.
 *
 * Yang rusak karenanya bukan kerahasiaan: scope pembacaan tetap
 * menahan nama sump B agar tidak muncul di halaman A. Yang rusak
 * adalah KEUTUHAN — dan justru itu yang lebih sulit ditemukan. Barisnya
 * tidak hilang, tidak melempar galat, dan tidak terlihat salah di mana
 * pun; ia hanya membuat rekapitulasi menyebut angka yang tidak dapat
 * dijelaskan siapa pun enam bulan kemudian, ketika tidak ada lagi yang
 * ingat bahwa baris itu pernah dibuat.
 *
 * Baris tanpa perusahaan (company_id NULL) diterima. Itu mengikuti arti
 * yang sudah dipakai scope MilikPerusahaan: yang belum dimiliki
 * perusahaan mana pun adalah milik bersama — standar, dokumen induk,
 * dan seluruh data yang dibuat sebelum penempatan perusahaan ada.
 *
 * Administrator diterima apa pun perusahaannya, sebab ia memang
 * mengelola lintas perusahaan.
 */
class DalamPerusahaan implements ValidationRule
{
    public function __construct(
        private readonly string $tabel,
        private readonly string $kunci = 'id',
    ) {}

    public function validate(string $atribut, mixed $nilai, Closure $gagal): void
    {
        if (blank($nilai)) return;   // 'nullable' yang memutuskan, bukan aturan ini

        $pengguna = auth()->user();

        /* Tanpa pengguna — perintah konsol, penyemai, antrean — aturan
           ini tidak punya perusahaan untuk dibandingkan. Membiarkannya
           lewat sengaja: menolak di situ akan membuat pekerjaan
           terjadwal gagal pada data yang sah. */
        if (!$pengguna || $pengguna->isAdmin()) {
            $this->pastikanAda($nilai, $gagal, $atribut);

            return;
        }

        $baris = DB::table($this->tabel)->where($this->kunci, $nilai)->first();

        if (!$baris) {
            $gagal('Data yang dipilih pada :attribute tidak ditemukan.');

            return;
        }

        $milik = $baris->company_id ?? null;

        if ($milik !== null && (int) $milik !== (int) $pengguna->company_id) {
            /* Pesannya sengaja sama dengan pesan "tidak ditemukan".
               Membedakan keduanya akan memberi tahu penebak bahwa
               nomor itu ADA dan hanya milik orang lain — cukup untuk
               memetakan berapa banyak sump, lereng, atau gudang yang
               dipunyai perusahaan lain hanya dengan mencoba nomor. */
            $gagal('Data yang dipilih pada :attribute tidak ditemukan.');
        }
    }

    private function pastikanAda(mixed $nilai, Closure $gagal, string $atribut): void
    {
        if (!DB::table($this->tabel)->where($this->kunci, $nilai)->exists()) {
            $gagal('Data yang dipilih pada :attribute tidak ditemukan.');
        }
    }
}
