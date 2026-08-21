<?php

namespace App\Support;

/**
 * Nomor register pengajuan — terbit sendiri, tidak diketik.
 *
 * D'Best menerbitkannya otomatis dan bentuknya berbeda per jenis:
 *
 *   Mine Permit   MKI.20260427002755   kode perusahaan · tanggal · id
 *   Mine License  PST.20260316002509   (bentuk sama dengan permit)
 *   Induksi       IND000996            awalan · id
 *   Simper        SIMPER-002039        awalan · id
 *   Authority     AUTHORITY-00175      awalan · id
 *   MCU           MCU/EQ/2026/001      (bentuk EQOHSEE, dipertahankan)
 *
 * ── MENGAPA BUKAN DIKETIK ──
 *
 * Nomor yang diketik tangan gagal dengan dua cara yang sama-sama sunyi.
 * Yang pertama TABRAKAN: dua orang membuat pengajuan pada hari yang
 * sama, keduanya menulis nomor urut yang sama, dan yang mencarinya
 * kemudian menemukan dua surat berbeda dengan satu nomor. Yang kedua
 * KEKOSONGAN: kolomnya opsional, jadi pengajuan tersimpan tanpa nomor
 * sama sekali — dan surat tanpa nomor tidak dapat dirujuk pada surat
 * lain, tidak dapat dicari, dan tidak dapat ditagih.
 *
 * Nomor di sini berpijak pada ID BARIS, bukan pada hitungan baris.
 * Menghitung ("berapa pengajuan tahun ini, tambah satu") memberi nomor
 * yang sama kepada dua permintaan yang datang bersamaan, dan memberi
 * nomor yang SUDAH DIPAKAI setelah satu baris dihapus.
 */
final class NomorRegister
{
    /**
     * Nomor bergaya D'Best: {AWALAN}{id berpad}.
     *
     * @param  string  $awalan   IND, SIMPER-, AUTHORITY-
     * @param  int     $lebar    berapa digit id-nya
     */
    public static function berawalan(string $awalan, int|string $id, int $lebar = 6): string
    {
        return $awalan.str_pad((string) $id, $lebar, '0', STR_PAD_LEFT);
    }

    /**
     * Nomor kartu bergaya D'Best: {KODE}.{YYYYMMDD}{id berpad}.
     *
     * Kode perusahaannya diambil dari `doc_no_prefix` — kolom yang
     * memang sudah dipakai seluruh penomoran dokumen di aplikasi ini,
     * jadi nomor kartunya sejalan dengan nomor dokumen lain milik
     * perusahaan yang sama.
     */
    public static function kartu(?string $kodePerusahaan, \DateTimeInterface|string|null $tanggal, int|string $id): string
    {
        $kode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $kodePerusahaan)) ?: 'EQ';

        $t = $tanggal instanceof \DateTimeInterface
            ? $tanggal
            : (filled($tanggal) ? new \DateTimeImmutable((string) $tanggal) : new \DateTimeImmutable());

        return $kode.'.'.$t->format('Ymd').str_pad((string) $id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Terbitkan nomor pada satu baris yang BELUM punya, lalu simpan.
     *
     * Diam bila nomornya sudah ada. Menomori ulang surat yang sudah
     * tersebar berarti dua nomor untuk satu surat — dan yang memegang
     * cetakan lamanya tidak akan menemukannya lagi.
     */
    public static function terbitkan(object $baris, string $kolom, callable $bentuk): void
    {
        if (filled($baris->{$kolom})) return;

        $baris->{$kolom} = $bentuk($baris);
        $baris->saveQuietly();
    }
}
