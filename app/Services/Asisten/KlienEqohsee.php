<?php

namespace App\Services\Asisten;

use App\Support\{Ai, AiPenyedia};

/**
 * KlienAi di atas App\Support\Ai — satu-satunya jalan asisten audit SMKP
 * ke penyedia model.
 *
 * Aurorasys memakai keluaran JSON terstruktur (skema dikirim ke penyedia).
 * Sambungan EQOHSEE meminta "JSON murni" saja, jadi skemanya disertakan
 * di dalam instruksi sistem dan jawabannya diurai Ai::uraiJson(). Bentuk
 * yang meleset tetap aman: setiap tugas merapikan jawabannya sendiri
 * (Rapi::*) sebelum dikirim ke peramban.
 *
 * Lampiran: PDF dan gambar dikirim biner (base64) — Claude, ChatGPT, dan
 * Gemini membaca keduanya langsung. Teks hasil ekstraksi DOCX/XLSX/PPTX
 * ditambahkan ke pesan, berbingkai penanda awal/akhir berkas.
 */
final class KlienEqohsee implements KlienAi
{
    public function __construct(
        private readonly int $maksToken = 16000,
        private readonly int $jeda = 100,
        private readonly int $ulang = 2,
    ) {}

    public function minta(Permintaan $p): Jawaban
    {
        if (!Ai::aktif()) throw GagalAsisten::nonaktif();

        $biner = [];
        $teks = [];
        foreach ($p->lampiran as $l) {
            if (!$l instanceof Lampiran) continue;
            if ($l->biner()) {
                $biner[] = ['mime' => $l->mime, 'data' => $l->base64(), 'nama' => $l->nama];
            } elseif (trim((string) $l->teks) !== '') {
                $teks[] = $l->teksBerbingkai();
            }
        }

        $pesan = $p->pesan;
        if ($teks) $pesan .= "\n\nBerkas lampiran (teks hasil ekstraksi):\n".implode("\n\n", $teks);
        if ($biner) $pesan .= "\n\nBerkas terlampir: ".implode(', ', array_column($biner, 'nama')).'.';

        $sistem = $p->sistem."\n\n"
            .'Jawab HANYA dengan satu objek JSON (tanpa teks lain, tanpa pagar kode) yang sesuai JSON Schema berikut:'."\n"
            .json_encode($p->skema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $r = Ai::jawab([['peran' => 'pengguna', 'isi' => $pesan]], $sistem, 1, [
            'json'      => true,
            'lampiran'  => $biner,
            'maksToken' => $this->maksToken,
            'jeda'      => $this->jeda,
            'ulang'     => $this->ulang,
        ]);

        $model = Ai::model();

        if (!($r['ok'] ?? false)) {
            $galat = (string) ($r['galat'] ?? '');
            throw match (true) {
                !Ai::aktif()                              => GagalAsisten::nonaktif(),
                str_starts_with($galat, 'terpotong')      => GagalAsisten::terpotong($model),
                str_starts_with($galat, 'kosong')         => GagalAsisten::menolak($model),
                (bool) preg_match('/^(401|403)\b/', $galat) => GagalAsisten::kunci(self::pembuat()),
                (bool) preg_match('/^429\b/', $galat)     => GagalAsisten::sibuk(),
                (bool) preg_match('/^(400|404|409|413|422)\b/', $galat) => GagalAsisten::permintaan(),
                (bool) preg_match('/^(503|529)\b/', $galat) => GagalAsisten::sibuk(),
                (bool) preg_match('/^(408|504)\b/', $galat) => GagalAsisten::waktu(),
                $galat !== '' && !preg_match('/^\d{3}\b/', $galat) => GagalAsisten::koneksi(),
                default                                   => GagalAsisten::layanan(),
            };
        }

        $data = Ai::uraiJson((string) ($r['isi'] ?? ''));
        if (!is_array($data) || array_is_list($data)) throw GagalAsisten::rusak($model);

        return new Jawaban($data, $model);
    }

    private static function pembuat(): string
    {
        return match (Ai::penyedia()) {
            AiPenyedia::ANTHROPIC => 'Anthropic',
            AiPenyedia::OPENAI    => 'OpenAI',
            AiPenyedia::GEMINI    => 'Google',
            default               => 'penyedia AI',
        };
    }
}
