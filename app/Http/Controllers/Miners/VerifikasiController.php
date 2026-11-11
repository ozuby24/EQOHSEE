<?php

namespace App\Http\Controllers\Miners;

use App\Http\Controllers\Controller;
use App\Support\Miners\Kartu\RincianVerifikasi;
use App\Support\Miners\Verifikasi;
use Inertia\Inertia;

/**
 * Verifikasi publik kartu dan sertifikat Miners — `GET v/{kode}` Safe Track
 * (VerifikasiKartu + RincianVerifikasi). Tanpa login, dibatasi 30 per
 * menit; kode bertanda tangan HMAC (Verifikasi::kode) sehingga nomor urut
 * tidak dapat ditebak untuk membaca kartu orang lain.
 */
class VerifikasiController extends Controller
{
    public function kartu(string $kode)
    {
        $r = RincianVerifikasi::dariKode($kode);

        $res = Inertia::render('Verifikasi/Kartu', [
            'kode'  => mb_substr($kode, 0, 40),
            'sah'   => $r !== null,
            'r'     => $r,
            'jenis' => Verifikasi::baca($kode)['jenis'] ?? null,
            'diperiksa' => \App\Support\Waktu::kini()->format('d-m-Y H:i'),
        ])->toResponse(request());

        $res->setStatusCode($r ? 200 : 404);
        $res->headers->set('Cache-Control', 'no-store, private');
        $res->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $res;
    }
}
