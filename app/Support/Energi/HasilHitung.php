<?php

namespace App\Support\Energi;

use App\Models\Energi\Faktor;

/**
 * Wadah hasil satu hitungan — mencatat faktor yang dipakai dan yang kurang.
 *
 * Dipakai Kalkulator saja.
 */
final class HasilHitung
{
    public int $scope = 1;
    public ?float $gj = null;
    public ?float $co2Fosil = null;
    public ?float $co2Bio = null;
    public ?float $ch4 = null;
    public ?float $n2o = null;
    public ?float $lainCo2e = 0.0;
    public ?float $co2eMb = null;
    public ?float $rupiah = null;
    public float $persenFame = 0.0;
    public ?float $ketidakpastianFaktor = null;
    public string $gwpSet;

    /** @var array<int,array> */
    public array $dipakai = [];

    /** @var list<string> */
    public array $kurang = [];

    /** @var list<string> */
    public array $catatan = [];

    public function __construct(
        public readonly string $kode,
        public readonly float $jumlah,
        public readonly \DateTimeInterface|string $tanggal,
        public readonly array $k,
    ) {
        $this->gwpSet = strtoupper($k['gwp'] ?? 'AR5');
    }

    /** Nilai faktor; bila tidak ada dan $wajib, dicatat sebagai kekurangan. */
    public function faktor(string $kode, string $param, array $tambah = [], bool $wajib = true): ?float
    {
        $f = $this->cari($kode, $param, $tambah);

        if ($f === null) {
            if ($wajib) $this->kurang[] = $kode.'/'.$param;

            return null;
        }

        $this->dipakai[$f->id] = $f->ringkas();

        return $f->nilai;
    }

    public function ketidakpastian(string $kode, string $param, array $tambah = []): ?float
    {
        return $this->cari($kode, $param, $tambah)?->ketidakpastian_persen;
    }

    private function cari(string $kode, string $param, array $tambah): ?Faktor
    {
        $k = array_merge([
            'company_id' => $this->k['company_id'] ?? null,
            'pemasok'    => $this->k['pemasok'] ?? null,
            'wilayah'    => null,
            'kategori_ipcc' => null,
        ], $tambah);

        return RegistriFaktor::ambil($kode, $param, $this->tanggal, $k);
    }

    public function keluaran(): array
    {
        $set = 'gwp_'.strtolower($this->gwpSet);
        $gwpCh4 = $this->ch4 > 0 ? $this->faktor('ch4', $set) : 0.0;
        $gwpN2o = $this->n2o > 0 ? $this->faktor('n2o', $set) : 0.0;

        $bagian = [
            $this->co2Fosil,
            ($this->ch4 !== null && $gwpCh4 !== null) ? $this->ch4 * $gwpCh4 : null,
            ($this->n2o !== null && $gwpN2o !== null) ? $this->n2o * $gwpN2o : null,
            $this->lainCo2e,
        ];
        $co2e = in_array(null, $bagian, true) ? null : array_sum($bagian);

        $uAkt = Katalog::KETIDAKPASTIAN_AKTIVITAS[$this->k['sumber_data'] ?? 'nota'] ?? 5.0;
        $u = $this->ketidakpastianFaktor !== null
            ? sqrt($uAkt ** 2 + $this->ketidakpastianFaktor ** 2)
            : null;

        return [
            'kode'         => $this->kode,
            'jumlah'       => $this->jumlah,
            'satuan'       => Katalog::satuan($this->kode),
            'scope'        => $this->scope,
            'kategori_ipcc'=> $this->k['kategori_ipcc'] ?? null,
            'gj'           => $this->gj,
            'co2_fosil_t'  => $this->co2Fosil,
            'co2_bio_t'    => $this->co2Bio,
            'ch4_t'        => $this->ch4,
            'n2o_t'        => $this->n2o,
            'lain_co2e_t'  => $this->lainCo2e,
            'co2e_t'       => $co2e,
            'co2e_mb_t'    => $this->scope === 2 ? $this->co2eMb : $co2e,
            'rupiah'       => $this->rupiah,
            'persen_fame'  => $this->persenFame,
            'gwp'          => $this->gwpSet,
            'ketidakpastian_persen' => $u,
            'faktor'       => $this->dipakai,
            'kurang'       => array_values(array_unique($this->kurang)),
            'catatan'      => array_values(array_unique($this->catatan)),
        ];
    }
}
