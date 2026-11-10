<?php

namespace App\Services\Asisten;

/** Satu permintaan ke model: instruksi sistem, isi tugas, skema JSON keluarannya, dan berkas lampiran (bila ada). */
final class Permintaan
{
    /**
     * @param string               $tugas  penanda tugas untuk catatan aktivitas (butir | temuan | program)
     * @param string               $sistem prompt sistem
     * @param string               $pesan  isi tugas beserta data yang diperlukan
     * @param array<string,mixed>  $skema  JSON Schema keluaran (structured outputs)
     * @param list<Lampiran>       $lampiran berkas yang dibaca model bersama pesan (lihat Lampiran::muat)
     */
    public function __construct(
        public readonly string $tugas,
        public readonly string $sistem,
        public readonly string $pesan,
        public readonly array $skema,
        public readonly array $lampiran = [],
    ) {}
}
