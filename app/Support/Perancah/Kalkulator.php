<?php

namespace App\Support\Perancah;

/**
 * Bill of materials, beban, faktor keamanan, rasio H/B, dan tekanan
 * tumpuan satu perancah.
 *
 * Rumusnya mengikuti FSD ScaffGuard §2: jumlah bay dan lift dari
 * dimensi, komponen dari bay × lift, beban mati dari panjang pipa dan
 * bobot komponen, beban hidup dari kelas beban (dek tambahan dihitung
 * 50%), beban kaki dengan faktor sebaran tak merata 1,25, lalu faktor
 * keamanan = kapasitas tiang ÷ beban kaki.
 *
 * Fungsi murni: tidak membaca basis data, sehingga pratinjau di form
 * dan hitungan yang tersimpan dijamin sama.
 */
final class Kalkulator
{
    /**
     * @param  array<string,mixed>  $s  spesifikasi: panjang_m, lebar_m, tinggi_m, jumlah_dek, jenis, kelas_beban, ada_outrigger
     * @return array<string,mixed>
     */
    public static function hitung(array $s): array
    {
        $p = max(1.0, (float) ($s['panjang_m'] ?? 4));
        $l = max(0.6, (float) ($s['lebar_m'] ?? 1.2));
        $t = max(1.5, (float) ($s['tinggi_m'] ?? 6));
        $dek = max(1, (int) ($s['jumlah_dek'] ?? 1));
        $jenis = Katalog::JENIS[$s['jenis'] ?? 'ringlock'] ?? Katalog::JENIS['ringlock'];
        $swl = Katalog::swl((string) ($s['kelas_beban'] ?? 'sedang'));
        $outrigger = (bool) ($s['ada_outrigger'] ?? false);

        $bayM = $jenis['bay_m'];
        $bay = max(1, (int) ceil($p / $bayM));
        $lift = max(1, (int) ceil($t / Katalog::TINGGI_LIFT_M));
        $standard = ($bay + 1) * 2;
        $ledger = $bay * 2 * ($lift + 1);
        $transom = ($bay + 1) * ($lift + 1) + ($bay * 2 * $dek);
        $bracing = (int) (ceil($bay / 2) * $lift * 2);
        $plankPerBay = max(2, (int) ceil($l / 0.25));
        $plank = $plankPerBay * $bay * $dek;
        $base = $standard;
        $coupler = ($ledger + $transom + $bracing) * 2;
        $tangga = max(1, (int) ceil($t / 6.0));

        // Rasio H/B: outrigger menambah lebar alas efektif 1,2 m.
        $alas = min($p, $l) + ($outrigger ? 1.2 : 0.0);
        $hb = round($t / max(0.5, $alas), 2);
        $ikatanMin = $hb > $jenis['hb_maks'] ? (int) (ceil($t / 4.0) * ceil($p / 6.0)) : 0;

        $pipa = ($standard * ($t + 1.1)) + ($ledger * $bayM) + ($transom * $l) + ($bracing * 2.7);
        $mati = round($pipa * Katalog::BERAT_PIPA_KG_M + $plank * Katalog::BERAT_PLANK_KG
            + $coupler * Katalog::BERAT_COUPLER_KG + $base * Katalog::BERAT_BASE_KG, 2);

        $luas = round($p * $l, 2);
        $hidup = round($luas * $swl * (1.0 + 0.5 * max(0, $dek - 1)), 2);

        $kaki = round(((($mati + $hidup) * 9.81) / (1000.0 * $standard)) * 1.25, 3);
        $sf = $kaki > 0 ? round($jenis['kapasitas_kn'] / $kaki, 2) : 99.0;
        $tekanan = round($kaki / Katalog::LUAS_SOLE_M2, 2);

        return [
            'bay' => $bay, 'lift' => $lift,
            'standard' => $standard, 'ledger' => $ledger, 'transom' => $transom, 'bracing' => $bracing,
            'plank' => $plank, 'base_plate' => $base, 'coupler' => $coupler, 'tangga' => $tangga,
            'ikatan_minimum' => $ikatanMin,
            'luas_dek_m2' => $luas,
            'beban_mati_kg' => $mati, 'beban_hidup_kg' => $hidup,
            'beban_kaki_kn' => $kaki, 'kapasitas_kn' => $jenis['kapasitas_kn'],
            'faktor_keamanan' => $sf, 'rasio_hb' => $hb, 'hb_maks' => $jenis['hb_maks'],
            'tekanan_tanah_kn_m2' => $tekanan,
            'panjang_pipa_m' => round($pipa, 1),
            'swl_kg_m2' => $swl,
            'bom' => [
                ['butir' => 'Standard (tiang vertikal Ø48,3 mm)', 'jumlah' => $standard, 'satuan' => 'batang'],
                ['butir' => 'Ledger (horizontal memanjang)',       'jumlah' => $ledger,   'satuan' => 'batang'],
                ['butir' => 'Transom (penumpu plank)',             'jumlah' => $transom,  'satuan' => 'batang'],
                ['butir' => 'Bracing diagonal',                    'jumlah' => $bracing,  'satuan' => 'batang'],
                ['butir' => 'Steel plank anti-slip',               'jumlah' => $plank,    'satuan' => 'lembar'],
                ['butir' => 'Base plate & sole board',             'jumlah' => $base,     'satuan' => 'set'],
                ['butir' => 'Coupler siku / swivel',               'jumlah' => $coupler,  'satuan' => 'buah'],
                ['butir' => 'Tangga akses & pintu jebak',          'jumlah' => $tangga,   'satuan' => 'unit'],
            ],
        ];
    }
}
