<?php

namespace App\Models\Scopes;

use App\Support\LingkupLintas;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Batas data per perusahaan.
 *
 * Dipasang sebagai global scope, bukan sebagai baris `where` di tiap
 * controller. Batas yang ditulis ulang di tiap tempat pemakaian akan
 * tertinggal cepat atau lambat — pada ekspor, pada hitungan statistik,
 * pada halaman baru yang ditulis enam bulan kemudian — dan yang
 * tertinggal tidak menimbulkan galat, hanya data perusahaan lain yang
 * diam-diam ikut terbaca. Sebagai global scope, yang perlu diingat
 * justru kebalikannya: melepas batas harus ditulis dengan tegas
 * (`withoutGlobalScope`), sehingga terlihat saat ditinjau.
 *
 * Administrator EQOHSEE menjangkau seluruh perusahaan; penyaringan
 * `?perusahaan=` yang sudah ada di beberapa modul tetap bekerja karena
 * scope ini tidak ikut campur bagi admin.
 *
 * Baris tanpa perusahaan (company_id NULL) terlihat oleh semua orang.
 * Itu bukan kelonggaran, melainkan arti kolomnya: baris yang belum
 * dimiliki perusahaan mana pun — dokumen induk, standar bersama, dan
 * seluruh data yang dibuat sebelum penempatan perusahaan ada — bukan
 * milik pihak lain yang harus disembunyikan.
 *
 * Menyaringnya sebagai `company_id = <milik saya>` saja pernah membuat
 * modul Energi, Gudang, dan Dokumen tampak KOSONG bagi setiap pengguna
 * yang sudah ditempatkan di sebuah perusahaan, sebab seluruh barisnya
 * masih NULL. Kegagalannya diam: tidak ada galat, hanya daftar kosong
 * yang terlihat seperti "memang belum ada datanya".
 *
 * Yang tetap dijaga adalah yang sebenarnya berbahaya: baris milik
 * perusahaan LAIN tidak pernah terlihat.
 *
 * SATU PENGECUALIAN, DAN HANYA SEPANJANG GARIS KETURUNAN
 *
 * Pemegang IUP dan mitra IUJP di bawahnya bukan dua perusahaan yang
 * tidak berhubungan: yang satu bertanggung jawab atas keselamatan
 * pekerjaan yang dikerjakan yang lain, dan yang lain wajib mematuhi
 * prosedur yang diterbitkan yang satu. Beberapa tabel karena itu
 * menembus batas — TETAPI hanya ke induk sendiri dan anak sendiri,
 * tidak pernah menyamping antar sesama IUJP, dan hanya tabel yang
 * disebut tegas pada App\Support\LingkupLintas.
 *
 * Perhatikan bentuk pelebarannya di bawah: ia menyebut `parent_id`
 * perusahaan saya dan daftar anak perusahaan saya. Perusahaan saudara —
 * IUJP lain di bawah induk yang sama — tidak berada di salah satu pun
 * dari keduanya, jadi ia tidak pernah ikut terbuka.
 */
class MilikPerusahaan implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $u = auth()->user();

        /* Tanpa pengguna: perintah konsol, antrean, penyemai, migrasi.
           Menyaring di situ akan membuat pekerjaan terjadwal diam-diam
           memproses sebagian data saja — kegagalan yang jauh lebih sulit
           dilacak daripada kebocoran yang sedang dicegah di sini. */
        if (!$u || $u->isAdmin()) return;

        $tabel = $model->getTable();
        $kolom = $tabel.'.company_id';

        $builder->where(function (Builder $q) use ($kolom, $tabel, $u) {
            $q->whereNull($kolom);

            if (!$u->company_id) return;

            $q->orWhere($kolom, $u->company_id);

            $saya = $u->company;
            if (!$saya) return;

            /* Ke atas: baris induk saya, bila tabelnya memang dibuka
               ke anak. Hanya SATU induk — bukan seluruh leluhur, dan
               bukan saudara. */
            if ($saya->parent_id && LingkupLintas::keAnak($tabel)) {
                $q->orWhere($kolom, $saya->parent_id);
            }

            /* Ke bawah: baris anak-anak saya, bila tabelnya memang
               dibuka ke induk. Diambil sebagai subkueri id, bukan
               dimuat ke memori: sebuah IUP dapat menaungi puluhan
               mitra. */
            if (LingkupLintas::keInduk($tabel)) {
                $q->orWhereIn($kolom, \App\Models\Company::query()
                    ->where('parent_id', $u->company_id)->select('id'));
            }
        });
    }
}
