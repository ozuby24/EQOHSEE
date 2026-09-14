<?php

namespace App\Http\Controllers;

use App\Support\Tur;
use Illuminate\Http\{JsonResponse, Request};

/**
 * Pengenalan situs — isinya, dan penanda selesainya.
 *
 * ── Kenapa isinya diambil terpisah, bukan ikut dibagikan ──
 *
 * Langkah pengenalan beserta delapan pilar dan seluruh modulnya berbobot
 * sekitar sembilan setengah kilobita. Dititipkan pada prop bersama, ia
 * ikut terkirim pada SETIAP pembukaan halaman oleh akun yang belum
 * menyelesaikannya — termasuk saat orangnya sedang mengisi formulir dan
 * sama sekali tidak sedang melihat pengenalan apa pun.
 *
 * Yang dibagikan karena itu hanya satu boolean. Isinya diambil sekali,
 * ketika pengenalannya benar-benar dibuka. Bedanya bukan teoretis:
 * halaman Form Penilaian Audit sudah berada lima bita di bawah ambang
 * muatannya sendiri, dan sembilan kilobita tambahan pada tiap halaman
 * akan menjatuhkannya.
 */
class TurController extends Controller
{
    public function isi(Request $request): JsonResponse
    {
        return response()->json([
            'versi'   => Tur::VERSI,
            'langkah' => Tur::langkah($request->user()),
        ]);
    }

    /**
     * Menandai pengenalannya selesai.
     *
     * Ditulis hanya bila memang belum pernah ditandai. Menekan "Selesai"
     * dua kali — atau membuka ulang pengenalan lalu menutupnya lagi —
     * jangan sampai memundurkan tanggalnya, sebab tanggal itulah satu-
     * satunya jawaban atas "kapan orang ini pertama kali dikenalkan".
     */
    public function selesai(Request $request)
    {
        $u = $request->user();

        if ($u && $u->tur_selesai_pada === null) {
            $u->tur_selesai_pada = now();
            $u->save();
        }

        /* 204: penutupan pengenalan tidak mengubah apa pun di halaman
           yang sedang dibuka. Mengembalikan redirect akan membuat Inertia
           memuat ulang seluruh halamannya — dan yang terlihat pengguna
           adalah situs yang berkedip tepat setelah ia menekan Selesai.

           Karena 204 BUKAN jawaban Inertia, sisi peramban memanggilnya
           dengan fetch biasa, bukan lewat router Inertia. Dipanggil lewat
           router, jawaban tanpa tajuk X-Inertia dianggapnya halaman galat
           dan ia membuka dialog galat selayar penuh — persis di hadapan
           orang yang baru saja menekan "Lewati pengenalan". Lihat
           resources/js/Components/TurSelamatDatang.vue. */
        return response()->noContent();
    }
}
