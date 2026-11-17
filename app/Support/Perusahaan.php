<?php

namespace App\Support;

use App\Models\Company;
use App\Models\User;

/**
 * Perusahaan yang sedang dilihat seorang administrator.
 *
 * HANYA MENYEMPITKAN, TIDAK PERNAH MELEBARKAN. Administrator EQOHSEE
 * memang menjangkau seluruh perusahaan; yang dilakukan pemilih ini
 * adalah membatasinya pada satu — supaya ia melihat persis apa yang
 * dilihat pengguna perusahaan itu, bukan supaya ia melihat lebih
 * banyak.
 *
 * Bagi pengguna biasa, nilai di sesi TIDAK BERARTI APA-APA. Itu bukan
 * kerapian melainkan jantung keamanannya: sesi dapat diwarisi dari akun
 * admin yang tadinya masuk di peramban yang sama, dan pemeriksaan yang
 * hanya menyembunyikan tombolnya akan membiarkan nilai lama itu tetap
 * bekerja. Karena itu `terpilih()` menanyakan perannya lebih dahulu,
 * dan lingkup datanya menanyakannya sekali lagi.
 *
 * PERPINDAHAN TIDAK MENGUBAH KEPEMILIKAN BARIS BARU. Yang dibuat
 * seorang admin tetap tercatat atas `company_id` akunnya sendiri —
 * menaruhnya atas perusahaan yang sedang dilihat akan membuat data
 * lahir di tempat yang tidak pernah dipilih siapa pun secara sadar.
 */
final class Perusahaan
{
    public const KUNCI = 'perusahaan_dilihat';

    /**
     * Perusahaan yang sedang dilihat, atau null untuk seluruhnya.
     *
     * Null punya dua arti yang sengaja disatukan: administrator yang
     * belum memilih apa pun, dan administrator yang memilih "semua".
     * Keduanya berakibat sama — tidak ada penyempitan — sehingga
     * membedakannya hanya akan menambah keadaan tanpa menambah arti.
     */
    public static function terpilih(?User $u = null): ?int
    {
        $u ??= auth()->user();

        if (! $u || ! $u->isAdmin()) return null;

        $id = session(self::KUNCI);

        return $id ? (int) $id : null;
    }

    /**
     * Pilih perusahaan yang dilihat. Null mengembalikan ke seluruhnya.
     *
     * @return string|null alasan penolakan, atau null bila berhasil
     */
    public static function pilih(?int $id, ?User $u = null): ?string
    {
        $u ??= auth()->user();

        if (! $u || ! $u->isAdmin()) {
            return 'Hanya administrator yang dapat berpindah perusahaan.';
        }

        if ($id === null) {
            session()->forget(self::KUNCI);

            return null;
        }

        // Id yang tidak ada tidak ditolak diam-diam: disimpan apa
        // adanya, ia menyaring seluruh halaman menjadi kosong dan yang
        // terbaca adalah "belum ada data", bukan "pilihannya salah".
        if (! Company::withoutGlobalScopes()->whereKey($id)->exists()) {
            return 'Perusahaan tidak dikenal.';
        }

        session([self::KUNCI => $id]);

        return null;
    }

    /**
     * Daftar perusahaan yang boleh dipilih seseorang.
     *
     * Pengguna biasa memulangkan daftar KOSONG, bukan daftar berisi
     * satu. Daftar berisi satu akan menggambar pemilih yang dapat
     * dibuka, dan pemilih yang dapat dibuka tetapi tidak dapat
     * mengubah apa pun lebih membingungkan daripada label biasa.
     *
     * @return list<array{id:int,nama:string}>
     */
    public static function dapatDipilih(?User $u = null): array
    {
        $u ??= auth()->user();

        if (! $u || ! $u->isAdmin()) return [];

        return Company::withoutGlobalScopes()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Company $c) => ['id' => $c->id, 'nama' => $c->name])
            ->all();
    }

    /** Nama perusahaan yang sedang dilihat, untuk bilah atas. */
    public static function namaTerpilih(?User $u = null): ?string
    {
        $id = self::terpilih($u);

        return $id === null ? null
            : Company::withoutGlobalScopes()->whereKey($id)->value('name');
    }
}
