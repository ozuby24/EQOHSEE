<?php

namespace App\Support;

use Illuminate\Support\Facades\{Http, Log};

/**
 * Verifikasi Cloudflare Turnstile.
 *
 * Menahan pengisian formulir masuk secara otomatis — skrip yang mencoba
 * ribuan pasangan surel dan sandi hasil kebocoran situs lain. Pembatas
 * laju menahan KECEPATANNYA; yang ditahan di sini adalah pelakunya,
 * sebelum satu pun percobaan menyentuh basis data.
 *
 * Berbeda dari captcha bergambar, Turnstile umumnya tidak meminta
 * pemakainya mengerjakan apa pun: ia menilai perilaku peramban dan
 * lewat sendiri. Itu penting di sini — orang yang hendak melaporkan
 * bahaya dari lapangan tidak seharusnya diminta memilih gambar lampu
 * lalu lintas lebih dulu.
 *
 * ── Tokennya sekali pakai ──
 *
 * Cloudflare menolak token yang sudah pernah ditukar. Artinya sandi yang
 * salah sekali pun sudah menghabiskan tokennya, dan percobaan berikutnya
 * WAJIB memakai token baru. Sisi Vue karena itu menyetel ulang widget-nya
 * setiap kali halaman masuk memulangkan galat; tanpa itu, percobaan kedua
 * selalu gagal dengan alasan yang tidak ada hubungannya dengan sandinya.
 */
class Turnstile
{
    /** Nama kolom yang dikirim widget Cloudflare. Namanya ditentukan mereka. */
    public const KOLOM = 'cf-turnstile-response';

    /**
     * Menyala hanya bila KEDUA kuncinya ada.
     *
     * Satu kunci saja tidak cukup dan tidak boleh dianggap cukup: kunci
     * situs tanpa rahasianya menggambar kotak verifikasi yang jawabannya
     * tidak pernah dapat diperiksa, sedangkan rahasia tanpa kunci situs
     * menuntut jawaban dari kotak yang tidak pernah digambar — dan yang
     * kedua mengunci seluruh orang di luar.
     */
    public static function aktif(): bool
    {
        return self::kunciSitus() !== null && self::rahasia() !== null;
    }

    public static function kunciSitus(): ?string
    {
        $k = trim((string) config('turnstile.situs'));

        return $k === '' ? null : $k;
    }

    private static function rahasia(): ?string
    {
        $k = trim((string) config('turnstile.rahasia'));

        return $k === '' ? null : $k;
    }

    /**
     * Menukar token widget ke Cloudflare.
     *
     * @param  string|null  $token  isi kolom cf-turnstile-response
     * @param  string|null  $ip     alamat pengirimnya, untuk pemeriksaan silang
     */
    public static function sah(?string $token, ?string $ip = null): bool
    {
        if (! self::aktif()) return true;

        /* Token kosong tidak perlu diantar ke Cloudflare. Ia pasti
           ditolak, dan mengantarnya berarti tiap kiriman kosong — yang
           justru paling murah dibuat penyerang — membangkitkan satu
           permintaan keluar dari server kita sendiri. */
        if ($token === null || trim($token) === '') return false;

        try {
            $jawab = Http::asForm()
                ->timeout((int) config('turnstile.jeda_detik', 5))
                ->post((string) config('turnstile.url'), array_filter([
                    'secret'   => self::rahasia(),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));

            if ($jawab->failed()) return self::saatGagal('balasan '.$jawab->status());

            return (bool) $jawab->json('success', false);
        } catch (\Throwable $e) {
            return self::saatGagal($e->getMessage());
        }
    }

    /**
     * Keputusan ketika Cloudflare tidak dapat dihubungi.
     *
     * Selalu dicatat, apa pun keputusannya. Tanpa catatan, 'lolos'
     * berubah menjadi fitur yang mati diam-diam: kuncinya terpasang,
     * halaman masuk tampak normal, dan tidak ada satu pun tanda bahwa
     * verifikasinya sudah berminggu-minggu tidak benar-benar berjalan.
     */
    private static function saatGagal(string $sebab): bool
    {
        $lolos = config('turnstile.saat_gagal', 'lolos') !== 'tolak';

        Log::warning('Turnstile tidak dapat dihubungi', [
            'sebab'     => $sebab,
            'keputusan' => $lolos ? 'diteruskan' : 'ditolak',
        ]);

        return $lolos;
    }
}
