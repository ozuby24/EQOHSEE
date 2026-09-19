<?php

namespace App\Support;

use App\Rules\TurnstileSah;
use Illuminate\Support\Facades\{Http, Log};

/**
 * Verifikasi Cloudflare Turnstile.
 *
 * Menahan pengisian formulir masuk secara otomatis — skrip yang mencoba
 * ribuan pasangan surel dan sandi hasil kebocoran situs lain. Pembatas
 * laju menahan KECEPATANNYA; yang ditahan di sini adalah pelakunya,
 * sebelum satu pun percobaan menyentuh basis data.
 *
 * ── Tiga pintu, satu pasang kunci ──
 *
 * Dipasang pada masuk, daftar, dan lupa sandi. Ketiganya menerima
 * kiriman dari orang yang belum dikenal, dan masing-masing punya
 * penyalahgunaannya sendiri: menebak sandi pada yang pertama, membuat
 * akun massal pada yang kedua, dan pada yang ketiga — yang paling
 * mudah terlewat — memakai server ini sebagai pengirim surel ke alamat
 * siapa pun yang diketik penyerang, berkali-kali, dengan nama kita
 * pada bagian pengirimnya.
 *
 * Pintu keempat, penyetelan ulang sandi lewat tautan, sengaja
 * dibiarkan: tautannya sendiri sudah membuktikan penerimanya memegang
 * kotak surat yang dituju, dan kotak verifikasi di sana hanya
 * menambah satu rintangan pada orang yang sudah terbukti berhak.
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
     * Penanda pintu, ikut ditandatangani Cloudflare ke dalam tokennya.
     *
     * Tanpa ini sebuah token sah untuk SEMUA pintu. Halaman masuk
     * terbuka untuk siapa saja, jadi penyerang dapat memanen token dari
     * sana dengan peramban sungguhan — satu per satu, gratis — lalu
     * memakainya pada pintu daftar atau lupa sandi yang sedang ia
     * banjiri lewat skrip. Verifikasinya tetap menjawab success, karena
     * tokennya memang sah; ia hanya sah untuk pintu yang lain.
     *
     * Nilainya dibatasi Cloudflare: paling panjang 32 huruf, hanya
     * a-z A-Z 0-9 _ dan -.
     */
    public const TINDAKAN = [
        'masuk'      => 'masuk',
        'daftar'     => 'daftar',
        'lupa-sandi' => 'lupa-sandi',
    ];

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

    /**
     * Aturan validasi untuk kolom tokennya.
     *
     * Ditulis sekali di sini, bukan disalin ke tiap formulir. Yang
     * disalin akan berbeda pada suatu hari — satu formulir memakai
     * `nullable` "sementara", lalu tetap begitu — dan perbedaan itu
     * tidak menimbulkan galat, hanya satu pintu yang penjaganya sudah
     * lama pulang.
     *
     * Kosong ketika fiturnya mati: `required` pada kolom yang widget-nya
     * tidak pernah digambar menolak SETIAP kiriman, dengan pesan yang
     * menyebut kolom yang tidak terlihat di layar mana pun.
     *
     * @return array<int, mixed>
     */
    public static function aturan(string $tindakan): array
    {
        return self::aktif() ? ['required', new TurnstileSah($tindakan)] : [];
    }

    /**
     * Daftar nama inang yang boleh menerbitkan token.
     *
     * Kosong berarti tidak diperiksa, dan itu bawaannya — dengan sengaja.
     * Isi yang salah di sini tidak menghasilkan peringatan melainkan
     * penolakan atas SETIAP kiriman dari situs yang benar, dan yang
     * pertama menyadarinya adalah orang yang tidak bisa masuk.
     *
     * Lapisan ini juga bukan yang pertama: Cloudflare sudah mengikat
     * kunci situs ke domain yang didaftarkan di dasbornya, dan menolak
     * menggambar widget di domain lain. Yang ditambahkan di sini adalah
     * jaring kedua, untuk hari ketika sebuah domain ikut ditambahkan di
     * dasbor tanpa sepengetahuan yang memasang ini.
     *
     * @return array<int, string>
     */
    public static function inang(): array
    {
        $daftar = array_filter(array_map(
            'trim',
            explode(',', (string) config('turnstile.inang'))
        ));

        return array_values($daftar);
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
     * @param  string|null  $token     isi kolom cf-turnstile-response
     * @param  string|null  $ip        alamat pengirimnya, untuk pemeriksaan silang
     * @param  string|null  $tindakan  pintu yang seharusnya menerbitkan token ini
     */
    public static function sah(?string $token, ?string $ip = null, ?string $tindakan = null): bool
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

            if (! (bool) $jawab->json('success', false)) return false;

            /* Sesudah success, dua hal masih perlu dicocokkan. Keduanya
               diperiksa DI SINI dan bukan di pemanggilnya, supaya tidak
               ada pintu yang memeriksa success saja lalu lupa sisanya. */

            if ($tindakan !== null) {
                $dijawab = $jawab->json('action');

                if ($dijawab !== $tindakan) {
                    return self::ditolak('tindakan tidak cocok', [
                        'diminta'  => $tindakan,
                        'dijawab'  => $dijawab,
                    ]);
                }
            }

            $inang = self::inang();

            if ($inang !== []) {
                $dijawab = (string) $jawab->json('hostname');

                if (! in_array($dijawab, $inang, true)) {
                    return self::ditolak('inang tidak terdaftar', [
                        'dijawab'   => $dijawab,
                        'terdaftar' => $inang,
                    ]);
                }
            }

            return true;
        } catch (\Throwable $e) {
            return self::saatGagal($e->getMessage());
        }
    }

    /**
     * Token yang sah, tetapi bukan untuk permintaan ini.
     *
     * Dicatat, tidak seperti token yang memang palsu. Token palsu adalah
     * derau sehari-hari; token SAH yang datang ke pintu yang salah
     * berarti seseorang sedang memindahkannya dengan sengaja, dan itu
     * satu-satunya tanda yang akan pernah ada.
     *
     * @param  array<string, mixed>  $rinci
     */
    private static function ditolak(string $sebab, array $rinci = []): bool
    {
        Log::warning('Turnstile menolak token yang sah', ['sebab' => $sebab] + $rinci);

        return false;
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
