<?php

namespace App\Support;

use Illuminate\Support\Facades\{Crypt, DB, Http, Log};

/**
 * Sambungan ke penyedia AI, dengan kunci milik pemasangnya sendiri.
 *
 * Sebelumnya kunci hanya dapat diisi lewat `.env` di server. Pada
 * pemasangan ini itu berarti menyalakan asisten menuntut akses SSH —
 * sehingga yang paling berkepentingan justru yang paling tidak dapat
 * melakukannya. Kuncinya kini dapat dimasukkan dari Pusat Kendali.
 *
 * Yang dijaga di sekitar kunci itu:
 *
 * - Disimpan TERENKRIPSI, memakai kunci aplikasi. Sebuah kunci API
 *   adalah alat bayar: yang memegangnya dapat membelanjakan tagihan
 *   orang lain sampai batas kuotanya. Menyimpannya sebagai teks biasa
 *   berarti satu kebocoran basis data — cadangan yang tersalin, satu
 *   kueri yang bocor — cukup untuk itu.
 * - TIDAK PERNAH dikirim balik ke peramban. Halaman pengaturannya hanya
 *   mengatakan sudah terpasang atau belum, beserta empat huruf
 *   terakhirnya supaya orang tahu kunci yang mana.
 * - Disimpan per penyedia, sehingga berpindah penyedia tidak membuang
 *   kunci yang sudah dimasukkan sebelumnya.
 *
 * `.env` tetap dihormati sebagai cadangan: pemasangan yang memang
 * mengelola rahasianya lewat berkas tidak perlu berubah. Yang dari
 * basis data menang, sebab itulah yang baru saja diketik orang, dan
 * pengaturan yang diam-diam dikalahkan berkas adalah pengaturan yang
 * membuat orang mengira dirinya salah ketik.
 */
final class Ai
{
    private const K_PENYEDIA = 'ai_penyedia';
    private const K_MODEL    = 'ai_model';
    private const K_MAKS     = 'ai_maks_token';
    private const K_KUNCI    = 'ai_kunci_';        // + kode penyedia

    /** Batas bawaan, cukup untuk jawaban bantuan tanpa menjadi esai. */
    public const MAKS_TOKEN = 900;

    /* ═══════════ pengaturan ═══════════ */

    public static function penyedia(): string
    {
        $p = self::baca(self::K_PENYEDIA) ?: env('AI_PENYEDIA');

        return AiPenyedia::ada($p) ? $p : AiPenyedia::GEMINI;
    }

    public static function model(): string
    {
        $m = self::baca(self::K_MODEL);

        if (filled($m)) return $m;

        // Cadangan lama: GEMINI_MODEL sudah dipakai pemasangan yang ada.
        if (self::penyedia() === AiPenyedia::GEMINI && filled($e = config('bantuan.ai.model'))) {
            return $e;
        }

        return AiPenyedia::satu(self::penyedia())['model'];
    }

    public static function maksToken(): int
    {
        $n = (int) (self::baca(self::K_MAKS) ?: 0);

        return $n > 0 ? min($n, 4000) : self::MAKS_TOKEN;
    }

    /**
     * Kunci penyedia yang sedang dipakai — hanya untuk dipakai di sisi
     * server. Jangan pernah dimasukkan ke prop halaman.
     */
    public static function kunci(?string $penyedia = null): ?string
    {
        $p = $penyedia ?: self::penyedia();
        $tersimpan = self::baca(self::K_KUNCI.$p);

        if (filled($tersimpan)) {
            try {
                return Crypt::decryptString($tersimpan);
            } catch (\Throwable $e) {
                /* Kunci aplikasi berganti sesudah kuncinya disimpan.
                   Diperlakukan sebagai belum terpasang, bukan dilempar:
                   halaman pengaturannya harus tetap dapat dibuka justru
                   untuk memasukkannya kembali. */
                Log::warning('Kunci AI tidak dapat dibaca; APP_KEY mungkin berganti.', [
                    'penyedia' => $p,
                ]);

                return null;
            }
        }

        // Cadangan .env, hanya untuk Gemini — itu satu-satunya yang
        // pernah punya nama variabel sebelum halaman ini ada.
        return $p === AiPenyedia::GEMINI ? (config('bantuan.ai.kunci') ?: null) : null;
    }

    public static function aktif(): bool
    {
        return filled(self::kunci());
    }

    /** Apakah penyedia tertentu sudah punya kunci tersimpan. */
    public static function punyaKunci(string $penyedia): bool
    {
        return filled(self::kunci($penyedia));
    }

    /**
     * Empat huruf terakhir kunci, untuk membedakan kunci mana yang
     * terpasang tanpa memperlihatkan isinya.
     */
    public static function ekorKunci(string $penyedia): ?string
    {
        $k = self::kunci($penyedia);

        return filled($k) ? str_repeat('•', 6).substr($k, -4) : null;
    }

    /** Apakah kuncinya berasal dari .env, bukan dari halaman pengaturan. */
    public static function dariBerkas(string $penyedia): bool
    {
        return blank(self::baca(self::K_KUNCI.$penyedia))
            && $penyedia === AiPenyedia::GEMINI
            && filled(config('bantuan.ai.kunci'));
    }

    public static function simpanKunci(string $penyedia, string $kunci): void
    {
        AiPenyedia::satu($penyedia);   // menolak penyedia yang tidak dikenal

        self::tulis(self::K_KUNCI.$penyedia, Crypt::encryptString(trim($kunci)));
    }

    public static function hapusKunci(string $penyedia): void
    {
        DB::table('app_settings')->where('key', self::K_KUNCI.$penyedia)->delete();
    }

    public static function simpanPengaturan(string $penyedia, ?string $model, ?int $maksToken): void
    {
        AiPenyedia::satu($penyedia);

        self::tulis(self::K_PENYEDIA, $penyedia);
        self::tulis(self::K_MODEL, filled($model) ? trim($model) : AiPenyedia::satu($penyedia)['model']);

        if ($maksToken) self::tulis(self::K_MAKS, (string) max(100, min($maksToken, 4000)));
    }

    /* ═══════════ pemanggilan ═══════════ */

    /**
     * Menjawab satu giliran percakapan.
     *
     * `$opsi` untuk pekerjaan analisis yang lebih berat daripada kotak
     * bantuan — tanpanya perilakunya sama persis seperti sebelumnya:
     *
     * - maksToken: batas keluaran untuk permintaan ini saja. Batas pada
     *   Pusat Kendali (bawaan 900) dirancang untuk jawaban bantuan; satu
     *   larik JSON berisi selusin rangkuman pasal sudah melampauinya, dan
     *   model yang "berpikir" dulu (Gemini 2.5) menghabiskan sebagian
     *   batas itu sebelum menulis satu huruf pun. Hasilnya jawaban
     *   terpotong atau kosong — persis yang tercatat di log sebagai
     *   "AI memulangkan jawaban kosong · MAX_TOKENS".
     * - jeda: batas waktu dalam detik.
     * - json: minta jawaban JSON murni bila penyedianya mendukung.
     * - lampiran: berkas untuk giliran terakhir, list<{mime,data,nama}>
     *   dengan data base64 — dipakai membaca halaman PDF hasil pindaian.
     *
     * @param  list<array{peran:string,isi:string}>  $riwayat  terlama lebih dulu
     * @param  array{maksToken?:int,jeda?:int,json?:bool,lampiran?:list<array{mime:string,data:string,nama?:string}>}  $opsi
     * @return array{ok:bool,isi:string,galat?:string}
     */
    public static function jawab(array $riwayat, string $peranSistem, int $maksRiwayat = 12, array $opsi = []): array
    {
        $penyedia = self::penyedia();
        $kunci    = self::kunci();

        if (blank($kunci)) {
            return ['ok' => false, 'isi' => 'Asisten AI belum diaktifkan pada pemasangan ini.'];
        }

        $riwayat = array_values(array_slice($riwayat, -max(1, $maksRiwayat)));

        if (!$riwayat || $riwayat[array_key_last($riwayat)]['peran'] !== 'pengguna') {
            return ['ok' => false, 'isi' => 'Tidak ada pertanyaan yang perlu dijawab.'];
        }

        $minta = AiPenyedia::permintaan(
            $penyedia, $kunci, self::model(), $peranSistem, $riwayat,
            (int) ($opsi['maksToken'] ?? self::maksToken()),
            ['json' => (bool) ($opsi['json'] ?? false), 'lampiran' => $opsi['lampiran'] ?? []],
        );

        try {
            $r = Http::timeout((int) ($opsi['jeda'] ?? config('bantuan.ai.jeda', 30)))
                ->withHeaders($minta['tajuk'])
                ->asJson()
                ->post($minta['url'], $minta['badan']);
        } catch (\Throwable $e) {
            Log::warning('AI gagal dihubungi', ['penyedia' => $penyedia, 'galat' => $e->getMessage()]);

            return [
                'ok'    => false,
                'isi'   => 'Asisten AI tidak dapat dihubungi. Coba lagi, atau kirim pertanyaan ini ke admin.',
                'galat' => self::samarkan($e->getMessage(), $kunci),
            ];
        }

        $json = (array) $r->json();

        if ($r->failed()) {
            $pesan = AiPenyedia::galat($penyedia, $json) ?: $r->body();

            Log::warning('AI menolak permintaan', [
                'penyedia' => $penyedia, 'status' => $r->status(), 'galat' => $pesan,
            ]);

            return [
                'ok'    => false,
                'isi'   => 'Asisten AI sedang tidak bisa menjawab. Kirim pertanyaan ini ke admin agar ditindaklanjuti.',
                'galat' => $r->status().' · '.self::samarkan((string) $pesan, $kunci),
            ];
        }

        $teks = AiPenyedia::jawaban($penyedia, $json);
        $alasan = AiPenyedia::alasanBerhenti($penyedia, $json);

        /* Jawaban yang BERHENTI karena batas token bukan jawaban utuh.
           Untuk jawaban JSON itu berarti larik yang terpotong di tengah
           — tidak dapat diurai, dan tanpa tanda ini kegagalannya
           terbaca sebagai "model menjawab ngawur". */
        if ($teks !== '' && ($opsi['json'] ?? false) && AiPenyedia::terpotong($penyedia, $alasan)) {
            Log::warning('AI memulangkan jawaban terpotong', ['penyedia' => $penyedia, 'alasan' => $alasan]);

            return ['ok' => false, 'isi' => 'Jawaban AI terpotong karena batas panjang jawaban.',
                    'galat' => 'terpotong · '.$alasan];
        }

        if ($teks === '') {

            Log::warning('AI memulangkan jawaban kosong', ['penyedia' => $penyedia, 'alasan' => $alasan]);

            return [
                'ok'    => false,
                'isi'   => 'Asisten AI tidak memberi jawaban untuk pertanyaan ini. Silakan kirim ke admin.',
                'galat' => 'kosong'.($alasan ? ' · '.$alasan : ''),
            ];
        }

        return ['ok' => true, 'isi' => $teks];
    }

    /**
     * Uji satu kunci dengan pertanyaan sependek mungkin.
     *
     * Diuji dengan kunci yang DIBERIKAN, bukan yang tersimpan, supaya
     * kunci baru dapat diperiksa sebelum menimpa yang lama — kunci
     * salah ketik yang langsung tersimpan mematikan asisten sampai ada
     * yang menyadarinya.
     *
     * @return array{ok:bool,pesan:string}
     */
    public static function uji(string $penyedia, string $kunci, ?string $model = null): array
    {
        $model = filled($model) ? $model : AiPenyedia::satu($penyedia)['model'];

        $minta = AiPenyedia::permintaan(
            $penyedia, $kunci, $model,
            'Jawab dengan satu kata saja: OK',
            [['peran' => 'pengguna', 'isi' => 'Balas: OK']],
            32,
        );

        try {
            $r = Http::timeout(20)->withHeaders($minta['tajuk'])->asJson()
                ->post($minta['url'], $minta['badan']);
        } catch (\Throwable $e) {
            /* TIDAK SAMPAI ke penyedianya — beda sebab dengan ditolak,
               dan beda pula penanganannya. Nama host disebut supaya
               dapat diuji langsung dari server:

                   curl -sS -o /dev/null -w '%{http_code}\n' https://<host>

               Terjadi sungguhan: satu pemasangan dapat menghubungi
               Gemini tetapi tidak Anthropic maupun OpenAI, dan tanpa
               pembedaan ini keduanya terbaca sebagai "kunci salah". */
            $host = parse_url(AiPenyedia::satu($penyedia)['alamat'], PHP_URL_HOST) ?: '?';

            return ['ok' => false, 'pesan' =>
                'Tidak sampai ke '.AiPenyedia::satu($penyedia)['nama'].' ('.$host.'). '
                .'Ini kegagalan JARINGAN, bukan kunci — server ini tidak dapat menjangkau '
                .'alamat itu. Periksa firewall keluar atau DNS di server, lalu coba: '
                .'curl -sS -o /dev/null -w \'%{http_code}\' https://'.$host
                .'  ·  Rincian: '.self::samarkan($e->getMessage(), $kunci)];
        }

        $json = (array) $r->json();

        if ($r->failed()) {
            $pesan = AiPenyedia::galat($penyedia, $json) ?: $r->body();

            /* Galat penyedia ditampilkan apa adanya kepada administrator.
               Dialah yang sedang memeriksa kuncinya sendiri, dan
               "gagal" tanpa sebab memaksanya menebak antara kunci
               salah, kuota habis, dan nama model yang tidak ada.
               Kuncinya sendiri disamarkan lebih dulu — pesan galat
               sebagian penyedia mengutip kembali kunci yang dikirim. */
            /* SAMPAI, lalu ditolak. Statusnya membedakan sebabnya:
               401/403 kunci, 404 model tidak dikenal, 429 kuota. */
            $sebab = match (true) {
                in_array($r->status(), [401, 403], true) => 'Kuncinya ditolak — salah, dicabut, atau belum aktif.',
                $r->status() === 404 => 'Model "'.$model.'" tidak dikenal penyedia ini. '
                    .'Coba nama model lain dari daftar contoh.',
                $r->status() === 429 => 'Kuota atau laju permintaan terlampaui.',
                $r->status() >= 500  => 'Penyedianya sedang bermasalah, bukan pemasangan ini.',
                default              => 'Permintaannya ditolak.',
            };

            return ['ok' => false, 'pesan' => 'Ditolak ('.$r->status().'). '.$sebab
                .'  ·  Kata penyedianya: '.self::samarkan((string) $pesan, $kunci)];
        }

        $teks = AiPenyedia::jawaban($penyedia, $json);

        return $teks === ''
            ? ['ok' => false, 'pesan' => 'Terhubung, tetapi jawabannya kosong. Periksa nama modelnya.']
            : ['ok' => true, 'pesan' => 'Terhubung. Model '.$model.' menjawab: '.mb_substr($teks, 0, 60)];
    }

    /* ═══════════ perkakas ═══════════ */

    /** Nama penyedia dan model yang sedang dipakai, untuk ditampilkan. */
    public static function label(): string
    {
        return AiPenyedia::satu(self::penyedia())['nama'].' · '.self::model();
    }

    /**
     * Urai jawaban JSON dari model.
     *
     * Model kerap membungkus JSON-nya dengan pagar ```json, atau
     * mendahuluinya dengan satu kalimat pengantar meskipun diminta
     * tidak. Diambil dari kurung pembuka pertama sampai kurung penutup
     * terakhir, lalu diurai; null bila tetap tidak dapat diurai.
     */
    public static function uraiJson(string $teks): ?array
    {
        $teks = trim(preg_replace('~^```(?:json)?\s*|\s*```$~m', '', trim($teks)) ?? $teks);

        $data = json_decode($teks, true);
        if (is_array($data)) return $data;

        $a = strcspn($teks, '[{');
        $z = max(strrpos($teks, ']') ?: -1, strrpos($teks, '}') ?: -1);

        if ($a >= strlen($teks) || $z <= $a) return null;

        $data = json_decode(substr($teks, $a, $z - $a + 1), true);

        return is_array($data) ? $data : null;
    }

    /**
     * Buang kunci dari teks apa pun sebelum ia ditampilkan atau dicatat.
     *
     * Umum, sebab yang perlu disamarkan bukan hanya jawaban penyedia:
     * pesan galat basis data menyertakan nilai parameternya, dan
     * parameter itulah kuncinya.
     */
    public static function samarkan(string $teks, ?string $kunci): string
    {
        if ($kunci === null || strlen($kunci) < 8) return $teks;

        return str_replace([$kunci, trim($kunci)], '[kunci disamarkan]', $teks);
    }

    private static function baca(string $kunci): ?string
    {
        return DB::table('app_settings')->where('key', $kunci)->value('value');
    }

    private static function tulis(string $kunci, string $nilai): void
    {
        DB::table('app_settings')->updateOrInsert(
            ['key' => $kunci],
            ['value' => $nilai, 'updated_at' => now(), 'created_at' => now()],
        );
    }
}
