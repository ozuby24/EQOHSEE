<?php

namespace App\Models\Miners;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Dasar bagi seluruh daftar master Miners.
 *
 * Kesamaannya nyata, bukan dicari-cari: sembilan daftar di bawah modul
 * ini sama-sama berupa nama beserta urutan dan penanda aktif, sama-sama
 * milik satu perusahaan, dan sama-sama dibaca sebagai daftar pilih.
 * Menulis kesembilannya sebagai sembilan kelas yang isinya sama persis
 * berarti sembilan tempat yang harus diubah bersama setiap kali
 * penyaringnya bergeser — dan yang tertinggal adalah daftar yang paling
 * jarang dibuka.
 *
 * BARIS TANPA PEMILIK ADALAH DAFTAR AWAL BERSAMA. Perusahaan yang belum
 * menyusun daftarnya sendiri memakai yang itu; begitu ia menambahkan
 * barisnya sendiri, keduanya tampil berdampingan. Itu perilaku scope
 * MilikPerusahaan, bukan tambahan di sini — lihat komentarnya.
 */
#[ScopedBy(MilikPerusahaan::class)]
abstract class Master extends Model
{
    use BerpemilikPerusahaan;

    protected $guarded = ['id'];

    /**
     * Tabel ini punya kolom `urutan`. Tidak semuanya: mnr_pjo dan
     * mnr_subkontraktor sejak awal hanya nama dan penanda aktif.
     *
     * Di SQLite `ORDER BY "urutan"` atas kolom yang tidak ada lolos diam-
     * diam — kutip ganda yang tidak menunjuk kolom dibaca sebagai teks
     * tetap — tetapi PostgreSQL dan MySQL menolaknya, dan daftar pilihnya
     * gagal termuat. Turunan yang tidak berkolom itu menyetelnya false.
     */
    protected bool $punyaUrutan = true;

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    /**
     * Hanya yang masih dipakai, urut tampilan lalu nama.
     *
     * Urutannya DUA TINGKAT dan itu disengaja: `urutan` menempatkan
     * butir yang paling sering dipilih di atas, dan nama menjaga sisanya
     * tetap pada tempat yang sama di antara dua penggambaran. Diurut
     * menurut `urutan` saja, seluruh baris berurutan sama — bawaannya
     * 100 — dan daftarnya berpindah-pindah mengikuti urutan id.
     */
    public function scopeTerpakai(Builder $q): Builder
    {
        return $q->where(fn (Builder $w) => $w->where('aktif', true)->orWhereNull('aktif'))
            ->when($this->punyaUrutan, fn (Builder $b) => $b->orderBy('urutan'))
            ->orderBy('nama');
    }

    /** @return array<int,string> id => nama, siap menjadi daftar pilih. */
    public static function pilihan(): array
    {
        return static::query()->terpakai()->pluck('nama', 'id')->all();
    }
}
