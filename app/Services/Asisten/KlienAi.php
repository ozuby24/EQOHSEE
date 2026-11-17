<?php

namespace App\Services\Asisten;

/**
 * Gerbang ke model AI. Satu-satunya tempat aplikasi berbicara dengan
 * penyedia model — ranah audit (prompt, skema, perapian hasil) tidak
 * tahu-menahu soal SDK, dan pengujian cukup memasang tiruan antarmuka ini.
 */
interface KlienAi
{
    /**
     * Kirim satu permintaan berkeluaran JSON terstruktur.
     *
     * @throws GagalAsisten bila model tidak dapat dijangkau, menolak,
     *                      jawabannya terpotong, atau tidak dapat dibaca
     */
    public function minta(Permintaan $permintaan): Jawaban;
}
