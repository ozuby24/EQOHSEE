<?php

namespace App\Http\Controllers;

use App\Support\Perusahaan;
use Illuminate\Http\Request;

/**
 * Berpindah perusahaan yang sedang dilihat.
 *
 * Satu tindakan, dan seluruh penjagaannya ada di Perusahaan::pilih —
 * bukan di sini. Controller yang ikut memeriksa peran akan berselisih
 * dengan lingkup datanya cepat atau lambat, dan yang berselisih diam
 * adalah yang paling berbahaya.
 */
class PerusahaanDilihatController extends Controller
{
    public function __invoke(Request $r)
    {
        $data = $r->validate(['perusahaan' => ['nullable', 'integer']]);

        $galat = Perusahaan::pilih(
            isset($data['perusahaan']) ? (int) $data['perusahaan'] : null,
            $r->user(),
        );

        return $galat === null
            ? back()->with('sukses', 'Perusahaan yang dilihat diganti.')
            : back()->withErrors(['perusahaan' => $galat]);
    }
}
