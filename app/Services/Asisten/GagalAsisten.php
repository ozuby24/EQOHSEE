<?php

namespace App\Services\Asisten;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Kegagalan asisten yang sudah diterjemahkan menjadi pesan berbahasa
 * Indonesia beserta kode HTTP yang pantas. Dijawab sebagai JSON
 * {pesan, jenis} — galat penyedia (teks Inggris, isi respons mentah)
 * tidak pernah diteruskan ke peramban.
 */
final class GagalAsisten extends RuntimeException
{
    /**
     * @param int    $status     kode HTTP untuk peramban
     * @param string $jenis      penanda mesin: nonaktif | kunci | sibuk | waktu | koneksi | menolak | terpotong | rusak | permintaan | layanan
     * @param string|null $model model yang melayani, bila jawaban sempat diterima
     */
    public function __construct(
        string $pesan,
        public readonly int $status = 502,
        public readonly string $jenis = 'layanan',
        public readonly ?string $model = null,
        public readonly ?int $tokenMasuk = null,
        public readonly ?int $tokenKeluar = null,
    ) {
        parent::__construct($pesan);
    }

    public static function nonaktif(): self
    {
        return new self(
            'Asisten AI belum diaktifkan. Administrator perlu memasang kunci API penyedia AI di Pusat Kendali → Asisten AI.',
            503, 'nonaktif',
        );
    }

    /** @param string $pembuat nama penyedia untuk pesan, mis. "Anthropic", "OpenAI", "Google" */
    public static function kunci(string $pembuat = 'Anthropic'): self
    {
        return new self("Kunci API {$pembuat} tidak valid atau sudah dicabut. Minta administrator memeriksanya di Pusat Kendali → Asisten AI.", 502, 'kunci');
    }

    public static function izin(string $pembuat = 'Anthropic'): self
    {
        return new self("Kunci API {$pembuat} tidak berhak memakai model ini. Minta administrator memeriksa nama model dan akun {$pembuat} di Pusat Kendali → Asisten AI.", 502, 'kunci');
    }

    public static function sibuk(): self
    {
        return new self('Layanan AI sedang sibuk. Coba lagi beberapa saat lagi.', 503, 'sibuk');
    }

    public static function waktu(): self
    {
        return new self('Asisten AI tidak menjawab dalam batas waktu. Coba lagi beberapa saat lagi.', 504, 'waktu');
    }

    public static function koneksi(): self
    {
        return new self('Server tidak dapat terhubung ke layanan AI. Coba lagi; bila berulang, minta administrator memeriksa jaringan server.', 502, 'koneksi');
    }

    public static function permintaan(): self
    {
        return new self('Permintaan ke layanan AI ditolak karena konfigurasi (model, parameter, atau saldo akun). Minta administrator memeriksa Pusat Kendali → Asisten AI.', 502, 'permintaan');
    }

    public static function layanan(): self
    {
        return new self('Layanan AI mengalami gangguan. Coba lagi beberapa saat lagi.', 502, 'layanan');
    }

    /** Data belum cukup untuk dianalisis — permintaan tidak dikirim ke model. */
    public static function kosong(string $pesan): self
    {
        return new self($pesan, 422, 'kosong');
    }

    public static function menolak(?string $model = null, ?int $masuk = null, ?int $keluar = null): self
    {
        return new self('Asisten AI tidak dapat menjawab permintaan ini. Periksa kembali isian catatan atau uraian, lalu coba lagi.', 422, 'menolak', $model, $masuk, $keluar);
    }

    public static function terpotong(?string $model = null, ?int $masuk = null, ?int $keluar = null): self
    {
        return new self('Jawaban asisten terpotong sebelum selesai. Coba lagi; bila berulang, persingkat catatan atau minta administrator menaikkan ASISTEN_MAKS_TOKEN.', 502, 'terpotong', $model, $masuk, $keluar);
    }

    public static function rusak(?string $model = null, ?int $masuk = null, ?int $keluar = null): self
    {
        return new self('Jawaban asisten tidak dapat dibaca. Coba lagi.', 502, 'rusak', $model, $masuk, $keluar);
    }

    /**
     * Kegagalan yang sudah diterjemahkan bukan galat aplikasi: true menandai
     * sudah ditangani sehingga Laravel tidak menulis jejak tumpukannya ke log.
     * Catatan pemakaian ditulis LayananAsisten ke log aktivitas.
     */
    public function report(): bool
    {
        return true;
    }

    public function render(): JsonResponse
    {
        return response()->json(['pesan' => $this->getMessage(), 'jenis' => $this->jenis], $this->status);
    }
}
