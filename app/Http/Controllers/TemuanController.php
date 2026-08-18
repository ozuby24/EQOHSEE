<?php

namespace App\Http\Controllers;

use App\Support\Temuan;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Register temuan seluruh modul dalam satu halaman.
 *
 * Halaman ini menjawab satu pertanyaan yang selama ini tidak dapat
 * dijawab tanpa membuka lima modul satu per satu: apa saja yang masih
 * terbuka di seluruh site, dan mana yang sudah lewat tenggat.
 *
 * Penyaringnya sengaja hanya tiga — terbuka, terlambat, tak bertuan —
 * dan ketiganya menjawab pertanyaan yang berbeda. "Terlambat" menagih
 * yang sudah dijanjikan; "tak bertuan" menagih yang belum pernah
 * dijanjikan siapa pun. Yang kedua lebih mudah terlewat justru karena
 * ia tidak pernah tampak merah di modul asalnya.
 */
class TemuanController extends Controller
{
    public function index(Request $request)
    {
        /* Register dibaca dalam batas perusahaan pengguna. Administrator
           EQOHSEE tidak dibatasi — sama seperti scope MilikPerusahaan,
           supaya keduanya tidak pernah menyebut jumlah yang berbeda. */
        $pengguna = $request->user();
        $batas    = ($pengguna && !$pengguna->isAdmin()) ? $pengguna->company : null;

        $semua  = Temuan::semua($batas);
        $saring = $request->string('saring')->toString() ?: 'terbuka';

        $terlihat = match ($saring) {
            'terlambat'   => array_values(array_filter($semua, fn ($t) => $t['terbuka'] && $t['terlambat'])),
            'tak-bertuan' => array_values(array_filter($semua, fn ($t) => $t['terbuka'] && !$t['bertuan'])),
            'semua'       => $semua,
            default       => array_values(array_filter($semua, fn ($t) => $t['terbuka'])),
        };

        return Inertia::render('Temuan/Register', [
            'judul'    => 'Register Temuan',
            'subjudul' => 'Seluruh temuan dan tindak lanjut dari semua modul',

            'temuan'   => $terlihat,
            'ringkas'  => Temuan::ringkas($semua),
            'perModul' => array_map(
                fn ($modul, $jumlah) => ['modul' => $modul, 'jumlah' => $jumlah],
                array_keys(Temuan::perModul($semua)),
                array_values(Temuan::perModul($semua)),
            ),

            'saring' => $saring,
            'opsi'   => [
                ['nilai' => 'terbuka',     'label' => 'Masih terbuka'],
                ['nilai' => 'terlambat',   'label' => 'Lewat tenggat'],
                ['nilai' => 'tak-bertuan', 'label' => 'Tanpa penanggung jawab'],
                ['nilai' => 'semua',       'label' => 'Semua'],
            ],

            'tautan' => ['register' => route('temuan.index')],
        ]);
    }
}
