<?php

namespace App\Http\Controllers\Energi;

use App\Http\Controllers\Controller;
use App\Support\Energi\{Inventaris, Kalkulator, Katalog, RegistriFaktor};
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * API internal Energi & Karbon (sesi web, JSON — FSD §11.1).
 *
 * Kalkulator di peramban memanggil ini, bukan menyimpan faktornya sendiri:
 * dua salinan faktor — satu di server, satu di Vue — pasti berbeda suatu
 * hari, dan angka kalkulator yang tidak sama dengan laporan lebih buruk
 * daripada tidak ada kalkulator (cacat D2).
 */
class ApiController extends Controller
{
    use BantuEnergi;

    public function kalkulator(Request $request)
    {
        $d = $request->validate([
            'tanggal' => ['nullable', 'date'],
            'butir'   => ['required', 'array', 'min:1', 'max:30'],
            'butir.*.kode'   => ['required', Rule::in(array_keys(Katalog::ENERGI))],
            'butir.*.jumlah' => ['required', 'numeric', 'min:0', 'max:1000000000000'],
            'butir.*.kategori_ipcc' => ['nullable', Rule::in(array_keys(Katalog::KATEGORI_IPCC))],
            'butir.*.persen_fame'   => ['nullable', 'numeric', 'between:0,100'],
            'butir.*.harga'  => ['nullable', 'numeric', 'min:0'],
            'ton'     => ['nullable', 'numeric', 'min:0'],
            'wilayah' => ['nullable', 'string', 'max:30'],
        ]);

        $tgl = Carbon::parse($d['tanggal'] ?? now())->startOfDay();
        $c = $this->perusahaanAktif($request);
        $gwp = \App\Models\Energi\Pengaturan::untuk($c)->gwp_set;

        $hasil = [];
        $t = ['gj' => 0.0, 'co2e_t' => 0.0, 'co2_bio_t' => 0.0, 'rupiah' => 0.0, 'kurang' => []];
        foreach ($d['butir'] as $b) {
            $h = Kalkulator::hitung($b['kode'], (float) $b['jumlah'], $tgl, array_filter([
                'company_id' => $c, 'gwp' => $gwp, 'wilayah' => $d['wilayah'] ?? null,
                'kategori_ipcc' => $b['kategori_ipcc'] ?? null, 'persen_fame' => $b['persen_fame'] ?? null,
            ], fn ($v) => $v !== null));

            // Harga isian pengguna menggantikan harga registri hanya untuk hitungan ini.
            if (isset($b['harga'])) $h['rupiah'] = (float) $b['jumlah'] * (float) $b['harga'];

            $hasil[] = $h + ['label' => Katalog::label($b['kode'])];
            $t['gj'] += (float) $h['gj'];
            $t['co2e_t'] += (float) $h['co2e_t'];
            $t['co2_bio_t'] += (float) $h['co2_bio_t'];
            $t['rupiah'] += (float) $h['rupiah'];
            $t['kurang'] = array_values(array_unique(array_merge($t['kurang'], $h['kurang'])));
        }

        $t['toe'] = Kalkulator::toe($t['gj'], $tgl);
        $t['intensitas'] = !empty($d['ton']) ? $t['gj'] / (float) $d['ton'] : null;
        $t['intensitas_emisi'] = !empty($d['ton']) ? $t['co2e_t'] / (float) $d['ton'] : null;

        return response()->json(['tanggal' => $tgl->toDateString(), 'gwp' => $gwp, 'butir' => $hasil, 'total' => $t]);
    }

    public function faktor(Request $request)
    {
        $d = $request->validate([
            'kode' => ['required', 'string', 'max:40'], 'parameter' => ['required', 'string', 'max:30'],
            'tanggal' => ['nullable', 'date'], 'wilayah' => ['nullable', 'string', 'max:30'],
            'kategori_ipcc' => ['nullable', 'string', 'max:30'],
        ]);

        $f = RegistriFaktor::ambil($d['kode'], $d['parameter'], $d['tanggal'] ?? now(), [
            'company_id' => $this->perusahaanAktif($request), 'wilayah' => $d['wilayah'] ?? null,
            'kategori_ipcc' => $d['kategori_ipcc'] ?? null]);

        return response()->json(['faktor' => $f?->ringkas()]);
    }

    public function agregat(Request $request)
    {
        [$dari, $sampai] = $this->rentangTanggal($request, now()->startOfYear(), now()->startOfDay());
        $d = $request->validate(['group_by' => ['nullable', Rule::in(['bulan', 'kode', 'scope', 'site'])]]);
        $c = $this->perusahaanAktif($request);

        $baris = Inventaris::hitung($dari, $sampai, $c ? ['company_id' => $c] : [])['baris'];
        $kunci = match ($d['group_by'] ?? 'bulan') {
            'kode'  => fn ($b) => $b['kode'],
            'scope' => fn ($b) => 'S'.$b['scope'],
            'site'  => fn ($b) => (string) ($b['site_id'] ?? 0),
            default => fn ($b) => $b['bulan'],
        };

        return response()->json(['dari' => $dari->toDateString(), 'sampai' => $sampai->toDateString(),
            'kelompok' => array_map(fn ($r) => array_intersect_key($r, array_flip(['gj', 'toe', 's1', 's2_lb', 's2_mb', 's3', 'total', 'rupiah', 'co2_bio'])),
                Inventaris::kelompok($baris, $kunci))]);
    }
}
