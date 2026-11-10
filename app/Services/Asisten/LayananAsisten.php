<?php

namespace App\Services\Asisten;

use App\Models\ActivityLog;

/**
 * Pintu masuk tugas asisten dari controller: memastikan asisten aktif,
 * meneruskan permintaan ke KlienAi, dan mencatat setiap permintaan ke log
 * aktivitas (modul "asisten") — tugas, model, dan pemakaian token saja;
 * isi prompt dan jawaban tidak pernah dicatat.
 */
final class LayananAsisten
{
    public const LABEL = [
        'butir'   => 'Penilaian butir SMKP',
        'temuan'  => 'Analisis temuan SMKP',
        'bukti'   => 'Analisis bukti audit',
        'rencana' => 'Ekstraksi metode & sampel Rencana Audit',
        'uji'     => 'Uji sambungan',
    ];

    public function __construct(private readonly KlienAi $klien) {}

    public static function aktif(): bool
    {
        return KonfigurasiAsisten::aktif();
    }

    public function pastikanAktif(): void
    {
        if (!self::aktif()) throw GagalAsisten::nonaktif();
    }

    /** @param string $sasaran penanda objek untuk log, mis. "Audit SMKP 2026 · butir II.2.1" */
    public function jalankan(Permintaan $p, string $sasaran): Jawaban
    {
        $this->pastikanAktif();

        try {
            $j = $this->klien->minta($p);
        } catch (GagalAsisten $e) {
            $this->catat($p->tugas, $sasaran, $e->model, $e->tokenMasuk, $e->tokenKeluar, $e->jenis);
            throw $e;
        }

        $this->catat($p->tugas, $sasaran, $j->model, $j->tokenMasuk, $j->tokenKeluar);

        return $j;
    }

    private function catat(string $tugas, string $sasaran, ?string $model, ?int $masuk, ?int $keluar, ?string $gagal = null): void
    {
        $bagian = [$sasaran, 'model '.($model ?: KonfigurasiAsisten::model(KonfigurasiAsisten::penyedia()))];
        if ($masuk !== null || $keluar !== null) {
            $bagian[] = 'token masuk '.number_format((int) $masuk, 0, ',', '.').' · keluar '.number_format((int) $keluar, 0, ',', '.');
        }
        if ($gagal !== null) $bagian[] = 'gagal: '.$gagal;

        ActivityLog::write('Asisten AI · '.(self::LABEL[$tugas] ?? $tugas), implode(' · ', $bagian), 'asisten');
    }
}
