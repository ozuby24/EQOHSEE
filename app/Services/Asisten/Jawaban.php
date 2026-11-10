<?php

namespace App\Services\Asisten;

/** Jawaban model yang sudah diurai dari JSON, beserta pemakaian tokennya. */
final class Jawaban
{
    /**
     * @param array<string,mixed> $data JSON keluaran model (sesuai skema permintaan)
     * @param string $model model yang benar-benar melayani — bisa model cadangan bila terjadi fallback
     */
    public function __construct(
        public readonly array $data,
        public readonly string $model,
        public readonly ?int $tokenMasuk = null,
        public readonly ?int $tokenKeluar = null,
    ) {}
}
