<?php

namespace App\Http\Middleware;

use App\Support\RingkasPlatform;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menghitung pengunjung situs: satu peramban dihitung sekali per hari.
 *
 * ── ANONIM SEJAK DIRANCANG ──
 *
 * Yang disimpan di server hanya `kunjungan_harian(tanggal, pengunjung)`.
 * Tanda "sudah dihitung hari ini" bukan alamat IP atau sidik peramban,
 * melainkan kuki fungsional `eq_kunjungan` berisi TANGGAL — di peramban
 * itu sendiri, terenkripsi seperti kuki aplikasi lain. Tidak ada yang
 * dapat ditelusuri balik ke seseorang, jadi tidak ada data pribadi yang
 * diproses untuk angka ini (Kebijakan Privasi bagian 1).
 *
 * Alamat IP sengaja TIDAK dipakai untuk menyaring hitungan ganda: satu
 * kantor tambang keluar ke internet lewat satu alamat, dan puluhan
 * karyawannya akan terhitung sebagai satu orang.
 *
 * ── YANG DIHITUNG ──
 *
 * Hanya kunjungan halaman oleh peramban: GET yang berupa navigasi dokumen
 * (`Sec-Fetch-Dest: document`, dikirim setiap peramban modern) atau
 * kunjungan Inertia (hanya dikirim oleh aplikasi ini sendiri di peramban).
 * Bukan kiriman formulir, berkas, JSON, pemuatan awal yang diminta peramban
 * (prefetch), dan bukan perayap mesin pencari, pratinjau tautan
 * WhatsApp/LinkedIn, pemantau ketersediaan, atau skrip — semuanya akan
 * menggelembungkan angka yang ditampilkan di halaman masuk. Skrip yang
 * menyamar sebagai peramban hampir tidak pernah mengirim tajuk Sec-Fetch.
 *
 * ── DIHITUNG SEBELUM HALAMANNYA DIGAMBAR ──
 *
 * Pengunjung baru dicatat lebih dulu dan ringkasan platform yang tersimpan
 * dihapus, baru halamannya digambar — jadi halaman masuk tidak pernah
 * menyambut pengunjung pertama dengan "0 pengunjung": hitungannya sendiri
 * sudah termasuk.
 *
 * Gagal mencatat tidak boleh menggagalkan halaman: angka statistik tidak
 * sepenting halaman yang sedang diminta.
 */
class CatatPengunjung
{
    public const KUKI = 'eq_kunjungan';

    /** Penanda agen yang bukan manusia di peramban. */
    private const BUKAN_ORANG = '/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|linkedin|monitor|uptime|pingdom|headless|lighthouse|curl|wget|python|java\/|okhttp|go-http|axios|postman/i';

    public function handle(Request $request, Closure $next): Response
    {
        $tercatat = $this->catat($request);

        $jawab = $next($request);

        // Berlaku sampai lewat tengah malam besok: tanggal di dalamnya yang
        // menentukan, umur kuki hanya batas atas. Lewat headers->setCookie,
        // bukan withCookie(): tanggapan unduhan/stream Symfony tidak punya
        // withCookie().
        if ($tercatat !== null) {
            $jawab->headers->setCookie(cookie(self::KUKI, $tercatat, 60 * 48, '/', null, null, true, false, 'lax'));
        }

        return $jawab;
    }

    /** Tanggal yang dicatat untuk kunjungan ini, atau null bila tidak dihitung. */
    private function catat(Request $request): ?string
    {
        if (!$this->layakDihitung($request)) {
            return null;
        }

        $hari = now()->toDateString();

        if ($request->cookie(self::KUKI) === $hari) {
            return null;
        }

        try {
            DB::table('kunjungan_harian')->insertOrIgnore([
                'tanggal' => $hari, 'pengunjung' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('kunjungan_harian')->where('tanggal', $hari)->increment('pengunjung', 1, ['updated_at' => now()]);
            Cache::forget(RingkasPlatform::KUNCI_CACHE);
        } catch (\Throwable) {
            return null;
        }

        return $hari;
    }

    private function layakDihitung(Request $request): bool
    {
        if (!$request->isMethod('GET')) {
            return false;
        }

        $tujuan = strtolower((string) ($request->header('Sec-Purpose') ?? $request->header('Purpose') ?? ''));
        if (str_contains($tujuan, 'prefetch') || str_contains($tujuan, 'prerender')) {
            return false;
        }

        $agen = trim((string) $request->userAgent());
        if ($agen === '' || preg_match(self::BUKAN_ORANG, $agen)) {
            return false;
        }

        // Kunjungan Inertia: hanya dikirim aplikasi ini sendiri, di peramban.
        if ($request->header('X-Inertia')) {
            return true;
        }

        // Navigasi dokumen oleh peramban — bukan gambar, skrip, iframe, atau fetch.
        return strtolower((string) $request->header('Sec-Fetch-Dest')) === 'document'
            && strtolower((string) $request->header('Sec-Fetch-Mode')) === 'navigate';
    }
}
