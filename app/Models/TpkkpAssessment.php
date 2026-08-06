<?php

namespace App\Models;

use App\Support\Tpkkp;
use Illuminate\Database\Eloquent\Model;

class TpkkpAssessment extends Model
{
    protected $table = 'tpkkp_assessments';

    protected $fillable = [
        'tahun', 'judul', 'status',
        'scores', 'roster', 'profil', 'tim', 'programs', 'jadwal', 'sampling',
    ];

    protected function casts(): array
    {
        return [
            'scores'   => 'array',
            'roster'   => 'array',
            'profil'   => 'array',
            'tim'      => 'array',
            'programs' => 'array',
            'jadwal'   => 'array',
            'sampling' => 'array',
        ];
    }

    /** Penilaian tahun berjalan; dibuat berisi benih instrumen bila belum ada. */
    public static function forYear(int $tahun): self
    {
        return static::firstOrCreate(
            ['tahun' => $tahun],
            [
                'judul'    => 'Penilaian Kinerja Keselamatan Pertambangan ' . $tahun,
                'status'   => 'draft',
                'scores'   => [],
                'roster'   => static::rosterSeed(),
                'profil'   => ['periode' => $tahun],
                'tim'      => [],
                'programs' => static::programSeed(),
                'jadwal'   => static::jadwalSeed(),
                'sampling' => static::samplingSeed(),
            ]
        );
    }

    /** Entitas bawaan tiap metode, disalin dari instrumen agar bisa diubah admin. */
    public static function rosterSeed(): array
    {
        $out = [];
        foreach (Tpkkp::methods() as $k => $m) $out[$k] = $m['entities'] ?? [];
        return $out;
    }

    public static function programSeed(): array
    {
        $out = [];
        foreach (Tpkkp::programSeed() as $i => $p) {
            $out[] = [
                'id'       => 'benih' . $i,
                'param'    => $p['param']   ?? '',
                'opsi'     => $p['opsi']    ?? '',
                'durasi'   => $p['durasi']  ?? '',
                'sasaran'  => $p['sasaran'] ?? '',
                'target'   => $p['target']  ?? '',
                'remarks'  => $p['remarks'] ?? '',
                'status'   => 'Rencana',
                'progress' => 0,
            ];
        }
        return $out;
    }

    public static function jadwalSeed(): array
    {
        $out = [];
        foreach (Tpkkp::schedule() as $s) $out[] = $s + ['done' => false];
        return $out;
    }

    public static function samplingSeed(): array
    {
        $ref = Tpkkp::samplingRef();
        return [
            'populasi' => $ref['population'] ?? ['Management' => 0, 'Employee' => 0],
            'e'        => 0.05,
            'aktual'   => [],
        ];
    }

    /** Entitas metode tertentu — roster penilaian, jatuh balik ke instrumen. */
    public function entitiesOf(string $method): array
    {
        $r = $this->roster[$method] ?? null;
        return is_array($r) && $r ? $r : (Tpkkp::methods()[$method]['entities'] ?? []);
    }

    /** Nilai satu sel: metode × item × entitas (null bila metode tanpa entitas). */
    public function cell(string $method, string $code, ?string $entity = null)
    {
        $rec = $this->scores[$method][$code] ?? null;
        if (!$rec) return null;
        return $entity === null ? ($rec['v'] ?? null) : ($rec['e'][$entity] ?? null);
    }

    public function ket(string $method, string $code): string
    {
        return (string) ($this->scores[$method][$code]['ket'] ?? '');
    }
}
