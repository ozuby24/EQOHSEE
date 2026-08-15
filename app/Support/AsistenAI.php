<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asisten AI untuk halaman Bantuan — Google Gemini.
 *
 * Gemini dipakai karena punya kuota gratis; OpenAI tidak menyediakan tingkat
 * gratis untuk API-nya. Pemanggilannya lewat HTTP biasa, tanpa paket tambahan:
 * satu endpoint, satu bentuk badan permintaan, dan tidak ada yang perlu
 * dipasang ulang di server saat menerbitkan.
 *
 * Tanpa kunci API, asistennya mati dan mengatakannya terus terang. Itu pilihan
 * yang disengaja: kotak bantuan yang menjawab dengan tebakan lebih berbahaya
 * daripada kotak bantuan yang mengaku belum aktif, sebab yang ditanyakan orang
 * di sini menyangkut keselamatan kerja.
 */
final class AsistenAI
{
    /** Petunjuk sistem — menjaga jawaban tetap pada lingkup EQOHSEE. */
    public const PERAN = <<<'TEKS'
    Anda asisten bantuan aplikasi EQOHSEE, platform Keselamatan dan Kesehatan
    Kerja (K3/HSE) untuk pertambangan Indonesia. Jawab dalam bahasa Indonesia
    yang ringkas dan jelas.

    Lingkup Anda: cara memakai fitur EQOHSEE, istilah K3 pertambangan, dan
    acuan seperti Kepdirjen 185.K/37.04/DJB/2019 tentang SMKP Minerba.

    Aturan yang tidak boleh dilanggar:
    - Jangan mengarang isi peraturan, nomor pasal, atau angka ambang. Bila tidak
      yakin, katakan tidak yakin dan sarankan menghubungi admin.
    - Jangan memberi nasihat medis, hukum, atau keputusan yang menyangkut
      keselamatan seseorang saat itu juga. Untuk keadaan darurat, arahkan ke
      prosedur tanggap darurat perusahaan dan Kepala Teknik Tambang.
    - Anda tidak dapat melihat data perusahaan pemakai. Jangan berpura-pura
      membacanya; jelaskan di menu mana angkanya bisa dilihat sendiri.
    TEKS;

    public static function aktif(): bool
    {
        return filled(config('bantuan.ai.kunci'));
    }

    /**
     * Menjawab satu giliran percakapan.
     *
     * @param  array<int,array{peran:string,isi:string}>  $riwayat  Terlama lebih dulu.
     * @return array{ok:bool, isi:string}
     */
    public static function jawab(array $riwayat): array
    {
        if (!self::aktif()) {
            return ['ok' => false, 'isi' => 'Asisten AI belum diaktifkan pada pemasangan ini.'];
        }

        $isi = [];
        foreach (array_slice($riwayat, -max(1, (int) config('bantuan.ai.riwayat'))) as $p) {
            $isi[] = [
                // Gemini hanya mengenal 'user' dan 'model'; giliran admin
                // diperlakukan sebagai giliran model supaya urutannya tetap
                // berselang-seling dan tidak ditolak.
                'role'  => $p['peran'] === 'pengguna' ? 'user' : 'model',
                'parts' => [['text' => $p['isi']]],
            ];
        }

        if (!$isi || $isi[array_key_last($isi)]['role'] !== 'user') {
            return ['ok' => false, 'isi' => 'Tidak ada pertanyaan yang perlu dijawab.'];
        }

        $model  = config('bantuan.ai.model');
        $alamat = rtrim((string) config('bantuan.ai.alamat'), '/');

        try {
            $r = Http::timeout((int) config('bantuan.ai.jeda'))
                ->withHeaders(['x-goog-api-key' => config('bantuan.ai.kunci')])
                ->post("{$alamat}/models/{$model}:generateContent", [
                    'systemInstruction' => ['parts' => [['text' => self::PERAN]]],
                    'contents' => $isi,
                    'generationConfig' => [
                        'maxOutputTokens' => (int) config('bantuan.ai.maks_token'),
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('AsistenAI gagal dihubungi', ['galat' => $e->getMessage()]);

            return ['ok' => false, 'isi' => 'Asisten AI tidak dapat dihubungi. Coba lagi, atau kirim pertanyaan ini ke admin.'];
        }

        if ($r->failed()) {
            /*
             * Pesan galat Gemini disimpan apa adanya ke log — nama model yang
             * salah atau kuota habis terbaca persis di situ. Yang dilihat
             * pemakai tetap kalimat biasa: isi galat penyedia kadang memuat
             * potongan kunci atau nama proyek.
             */
            Log::warning('AsistenAI menolak permintaan', [
                'status' => $r->status(),
                'galat'  => $r->json('error.message') ?? $r->body(),
            ]);

            return ['ok' => false, 'isi' => 'Asisten AI sedang tidak bisa menjawab. Kirim pertanyaan ini ke admin agar ditindaklanjuti.'];
        }

        $teks = '';
        foreach ((array) $r->json('candidates.0.content.parts', []) as $bagian) {
            $teks .= $bagian['text'] ?? '';
        }

        $teks = trim($teks);

        if ($teks === '') {
            // Jawaban kosong terjadi ketika keluarannya terpotong batas token
            // atau tersaring penyedia. Diam-diam menampilkan gelembung kosong
            // membuat orang mengira aplikasinya rusak.
            Log::warning('AsistenAI memulangkan jawaban kosong', [
                'alasan' => $r->json('candidates.0.finishReason'),
            ]);

            return ['ok' => false, 'isi' => 'Asisten AI tidak memberi jawaban untuk pertanyaan ini. Silakan kirim ke admin.'];
        }

        return ['ok' => true, 'isi' => $teks];
    }
}
