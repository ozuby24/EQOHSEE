<?php

namespace App\Support;

/**
 * Bentuk permintaan dan jawaban tiap penyedia AI.
 *
 * Dipisahkan dari pemanggilnya karena yang berbeda antar penyedia hanya
 * tiga hal — alamat, cara kunci dititipkan, dan bentuk badan
 * permintaannya — sementara yang dibutuhkan aplikasi selalu sama: satu
 * daftar giliran percakapan masuk, satu teks jawaban keluar.
 *
 * Tiga penyedia didukung, dan pilihannya diserahkan kepada pemasang:
 * kunci API dibayar sendiri oleh yang memakainya, dan memaksakan satu
 * vendor berarti memaksakan tagihan, wilayah data, dan syarat layanan
 * yang belum tentu boleh mereka terima.
 *
 * Yang TIDAK ada di sini dan sengaja tidak ada: pemilihan model
 * otomatis, percobaan ulang ke penyedia lain, dan penyimpanan jawaban.
 * Ketiganya membuat tagihan orang lain naik tanpa mereka putuskan.
 */
final class AiPenyedia
{
    public const ANTHROPIC = 'anthropic';
    public const OPENAI    = 'openai';
    public const GEMINI    = 'gemini';

    /**
     * @return array<string,array{nama:string,model:string,alamat:string,
     *                            kunciDari:string,contohModel:list<string>}>
     */
    public static function semua(): array
    {
        return [
            self::ANTHROPIC => [
                'nama'   => 'Anthropic (Claude)',
                'model'  => 'claude-sonnet-5',
                'alamat' => 'https://api.anthropic.com/v1',
                'kunciDari' => 'https://console.anthropic.com/settings/keys',
                'contohModel' => ['claude-opus-5', 'claude-sonnet-5', 'claude-haiku-4-5-20251001'],
            ],
            self::OPENAI => [
                'nama'   => 'OpenAI',
                'model'  => 'gpt-4.1',
                'alamat' => 'https://api.openai.com/v1',
                'kunciDari' => 'https://platform.openai.com/api-keys',
                'contohModel' => ['gpt-4.1', 'gpt-4.1-mini', 'gpt-4o'],
            ],
            self::GEMINI => [
                'nama'   => 'Google Gemini',
                'model'  => 'gemini-flash-latest',
                'alamat' => 'https://generativelanguage.googleapis.com/v1beta',
                'kunciDari' => 'https://aistudio.google.com/apikey',
                'contohModel' => ['gemini-flash-latest', 'gemini-pro-latest'],
            ],
        ];
    }

    public static function ada(?string $kode): bool
    {
        return $kode !== null && array_key_exists($kode, self::semua());
    }

    /** @return array<string,mixed> */
    public static function satu(string $kode): array
    {
        return self::semua()[$kode]
            ?? throw new \InvalidArgumentException("Penyedia AI tidak dikenal: {$kode}");
    }

    /**
     * Alamat lengkap, tajuk, dan badan satu permintaan.
     *
     * @param  list<array{peran:string,isi:string}>  $riwayat  terlama lebih dulu
     * @return array{url:string,tajuk:array<string,string>,badan:array<string,mixed>}
     */
    public static function permintaan(
        string $kode, string $kunci, string $model, string $peranSistem,
        array $riwayat, int $maksToken,
    ): array {
        $p = self::satu($kode);
        $alamat = rtrim($p['alamat'], '/');

        return match ($kode) {
            self::ANTHROPIC => [
                'url' => "{$alamat}/messages",
                'tajuk' => [
                    'x-api-key' => $kunci,
                    /* Versi API disebut tegas. Anthropic menuntutnya, dan
                       tanpa itu permintaannya ditolak sebelum sampai ke
                       model — galat yang mudah disalahartikan sebagai
                       kunci yang salah. */
                    'anthropic-version' => '2023-06-01',
                ],
                'badan' => [
                    'model'      => $model,
                    'max_tokens' => $maksToken,
                    'system'     => $peranSistem,
                    'messages'   => array_map(fn ($g) => [
                        'role'    => $g['peran'] === 'pengguna' ? 'user' : 'assistant',
                        'content' => $g['isi'],
                    ], $riwayat),
                ],
            ],

            self::OPENAI => [
                'url' => "{$alamat}/chat/completions",
                'tajuk' => ['Authorization' => 'Bearer '.$kunci],
                'badan' => [
                    'model' => $model,
                    /* Peran sistem dikirim sebagai giliran pertama, bukan
                       bidang tersendiri — itu bentuk yang dipakai
                       chat/completions. */
                    'messages' => array_merge(
                        [['role' => 'system', 'content' => $peranSistem]],
                        array_map(fn ($g) => [
                            'role'    => $g['peran'] === 'pengguna' ? 'user' : 'assistant',
                            'content' => $g['isi'],
                        ], $riwayat),
                    ),
                ] + self::batasTokenOpenAi($model, $maksToken),
            ],

            self::GEMINI => [
                'url' => "{$alamat}/models/{$model}:generateContent",
                'tajuk' => ['x-goog-api-key' => $kunci],
                'badan' => [
                    'systemInstruction' => ['parts' => [['text' => $peranSistem]]],
                    'contents' => array_map(fn ($g) => [
                        // Gemini hanya mengenal 'user' dan 'model'.
                        'role'  => $g['peran'] === 'pengguna' ? 'user' : 'model',
                        'parts' => [['text' => $g['isi']]],
                    ], $riwayat),
                    'generationConfig' => ['maxOutputTokens' => $maksToken],
                ],
            ],
        };
    }

    /**
     * Nama bidang batas token pada OpenAI berbeda antar keluarga model.
     *
     * Model penalar (o1, o3, dan seterusnya) menolak `max_tokens` dan
     * menuntut `max_completion_tokens`; model sebelumnya sebaliknya.
     * Ditebak dari nama modelnya, dan tebakan itu memang dapat meleset
     * pada model yang belum ada saat ini ditulis — karena itu galat
     * penyedia ditampilkan apa adanya kepada administrator, supaya
     * salahnya terbaca alih-alih tersembunyi.
     *
     * @return array<string,int>
     */
    private static function batasTokenOpenAi(string $model, int $maksToken): array
    {
        $penalar = (bool) preg_match('/^(o\d|gpt-5)/i', $model);

        return $penalar
            ? ['max_completion_tokens' => $maksToken]
            : ['max_tokens' => $maksToken];
    }

    /**
     * Ambil teks jawaban dari badan tanggapan.
     *
     * @param  array<string,mixed>  $json
     */
    public static function jawaban(string $kode, array $json): string
    {
        $teks = match ($kode) {
            self::ANTHROPIC => implode('', array_map(
                fn ($b) => is_array($b) && ($b['type'] ?? '') === 'text' ? ($b['text'] ?? '') : '',
                (array) ($json['content'] ?? []),
            )),

            self::OPENAI => (string) data_get($json, 'choices.0.message.content', ''),

            self::GEMINI => implode('', array_map(
                fn ($b) => is_array($b) ? ($b['text'] ?? '') : '',
                (array) data_get($json, 'candidates.0.content.parts', []),
            )),
        };

        return trim($teks);
    }

    /** Sebab berhentinya, untuk menjelaskan jawaban yang kosong. */
    public static function alasanBerhenti(string $kode, array $json): ?string
    {
        return match ($kode) {
            self::ANTHROPIC => data_get($json, 'stop_reason'),
            self::OPENAI    => data_get($json, 'choices.0.finish_reason'),
            self::GEMINI    => data_get($json, 'candidates.0.finishReason'),
        };
    }

    /** Pesan galat penyedia, untuk ditunjukkan kepada administrator. */
    public static function galat(string $kode, array $json): ?string
    {
        return match ($kode) {
            self::ANTHROPIC => data_get($json, 'error.message'),
            self::OPENAI    => data_get($json, 'error.message'),
            self::GEMINI    => data_get($json, 'error.message'),
        };
    }
}
