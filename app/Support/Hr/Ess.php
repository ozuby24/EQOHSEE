<?php

namespace App\Support\Hr;

use App\Models\Miners\Pekerja;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Batas data pada layanan mandiri: hanya baris SAYA.
 *
 * BATAS INI BERBEDA JENIS DARI BATAS PERUSAHAAN, dan itu sebabnya ia
 * tidak dititipkan ke MilikPerusahaan. Batas perusahaan menjawab "data
 * ini milik perusahaan mana"; batas di sini menjawab "baris ini milik
 * siapa". Seorang pekerja dan atasannya berada di perusahaan yang sama
 * — lingkup perusahaan meloloskan keduanya — sedangkan slip gaji yang
 * satu tidak pernah boleh terbaca yang lain.
 *
 * SATU TEMPAT, DAN SELURUH HALAMAN MELEWATINYA. Ditulis ulang di tiap
 * controller, cepat atau lambat ada satu kueri yang terlewat; dan yang
 * terlewat tidak menimbulkan galat, hanya baris orang lain yang
 * diam-diam ikut terbaca — dengan namanya tercetak di atasnya, pada
 * halaman yang berjudul "Slip Gaji Saya".
 *
 * MENYARING ADALAH LANGKAH TERAKHIR, BUKAN PERTAMA. Setiap penyaring
 * di sini memakai `withoutGlobalScopes()` lalu memasang kembali batas
 * perusahaan DAN batas orang dengan tangan. Membiarkan lingkup global
 * bekerja lalu menambahkan `where pekerja_id` di atasnya tampak lebih
 * ringkas, tetapi ia bergantung pada lingkup global yang tetap
 * terpasang — dan lingkup itu memang dilepas di beberapa tempat.
 */
final class Ess
{
    /**
     * Pekerja yang dimaksud sebuah akun, atau null.
     *
     * Null punya arti yang sah dan sering: sebagian besar akun aplikasi
     * — admin, auditor, pengawas kantor — memang bukan pekerja tambang
     * mana pun. Halaman layanan mandiri menyebutkannya apa adanya
     * alih-alih menggambar layar kosong yang terbaca seperti data
     * hilang.
     *
     * Keunikannya ditegakkan basis data (mnr_pekerja.user_id unique),
     * sehingga `first()` di sini tidak pernah memilih sembarang dari
     * beberapa kemungkinan.
     */
    public static function pekerja(?User $u = null): ?Pekerja
    {
        $u ??= auth()->user();

        if (! $u) return null;

        return Pekerja::withoutGlobalScopes()
            ->where('user_id', $u->id)
            ->first();
    }

    /** Apakah akun ini punya halaman layanan mandiri yang berisi. */
    public static function ada(?User $u = null): bool
    {
        return self::pekerja($u) !== null;
    }

    /**
     * Saring sebuah kueri menjadi milik seorang pekerja saja.
     *
     * Kedua batas dipasang bersama dan tidak dapat dipisahkan:
     * `pekerja_id` saja akan meloloskan baris berperusahaan lain yang
     * kebetulan berid sama sesudah pemulihan data, dan `company_id`
     * saja meloloskan seluruh rekan sekerjanya.
     *
     * @template T of Builder
     * @param  T  $q
     * @return T
     */
    public static function milik(Builder $q, Pekerja $p): Builder
    {
        return $q->withoutGlobalScopes()
            ->where($q->getModel()->getTable().'.company_id', $p->company_id)
            ->where($q->getModel()->getTable().'.pekerja_id', $p->id);
    }

    /**
     * Ambil satu baris milik seorang pekerja, atau null.
     *
     * DIPAKAI SEBAGAI GANTI `find()` PADA SETIAP HALAMAN RINCIAN.
     * `find($id)` menemukan baris siapa pun, dan id pada alamat adalah
     * hal termudah yang dapat diubah seseorang: mengetik satu angka
     * lain pada bilah alamat tidak menuntut alat apa pun, tidak
     * meninggalkan jejak yang mencurigakan, dan berhasil.
     */
    public static function satu(Builder $q, Pekerja $p, int|string $id): ?object
    {
        return self::milik($q, $p)->whereKey($id)->first();
    }
}
