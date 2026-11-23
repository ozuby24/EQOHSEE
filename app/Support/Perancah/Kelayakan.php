<?php

namespace App\Support\Perancah;

/**
 * Kajian kelayakan teknis lima kriteria (FSD ScaffGuard §2.4).
 *
 * 1. Faktor keamanan struktur ≥ 4,0                     — kritis
 * 2. Rasio H/B dan jumlah ikatan                         — kritis
 * 3. Daya dukung tumpuan (> 85% izin = bersyarat)         — kritis bila > 100%
 * 4. Angin & desain khusus (> 15 m / kantilever = P.E.)   — kritis bila angin > 60 km/j
 * 5. Proteksi jatuh (≥ 1,8 m wajib guardrail + toe board) — kritis
 *
 * Satu kegagalan kritis = TIDAK LAYAK; tanpa kritis tetapi ada
 * peringatan = LAYAK BERSYARAT; selebihnya LAYAK. Skornya 100 dikurangi
 * 25 per kegagalan kritis dan 10 per peringatan.
 */
final class Kelayakan
{
    /**
     * @param  array<string,mixed>  $s  spesifikasi unit
     * @param  array<string,mixed>  $h  hasil Kalkulator::hitung
     * @return array{putusan:string,skor:int,perlu_pe:bool,kritis:int,peringatan:int,kriteria:list<array<string,string>>,rekomendasi:list<string>}
     */
    public static function nilai(array $s, array $h): array
    {
        $jenisKunci = (string) ($s['jenis'] ?? 'ringlock');
        $jenis = Katalog::JENIS[$jenisKunci] ?? Katalog::JENIS['ringlock'];
        $tanah = Katalog::TANAH[$s['jenis_tanah'] ?? 'beton'] ?? Katalog::TANAH['beton'];
        $tinggi = (float) ($s['tinggi_m'] ?? 0);
        $ikatan = (int) ($s['jumlah_ikatan'] ?? 0);
        $angin = (float) ($s['angin_kmh'] ?? 0);
        $guard = (bool) ($s['ada_guardrail'] ?? true);
        $toe = (bool) ($s['ada_toeboard'] ?? true);

        $kriteria = [];
        $saran = [];
        $kritis = 0;
        $warn = 0;

        // 1. Faktor keamanan
        $sfOk = $h['faktor_keamanan'] >= Katalog::SF_MIN;
        if (!$sfOk) {
            $kritis++;
            $saran[] = 'Faktor keamanan '.self::a($h['faktor_keamanan']).'× di bawah '.self::a(Katalog::SF_MIN)
                .'×: pakai sistem bertiang lebih kuat, kurangi kelas beban, atau batasi jumlah dek yang dipakai bersamaan.';
        }
        $kriteria[] = self::baris('K-01', 'Faktor keamanan struktur', self::a($h['faktor_keamanan']).'×',
            '≥ '.self::a(Katalog::SF_MIN).'×', $sfOk ? 'lulus' : 'gagal');

        // 2. Stabilitas H/B & ikatan
        $perluIkat = $h['rasio_hb'] > $jenis['hb_maks'];
        $ikatOk = !$perluIkat || $ikatan >= $h['ikatan_minimum'];
        if (!$ikatOk) {
            $kritis++;
            $saran[] = 'Rasio H/B '.self::a($h['rasio_hb']).':1 melewati batas berdiri bebas '.self::a($jenis['hb_maks'])
                .':1 — pasang sedikitnya '.$h['ikatan_minimum'].' titik ikatan ke struktur permanen, atau outrigger.';
        }
        $kriteria[] = self::baris('K-02', 'Stabilitas rasio H/B & ikatan',
            self::a($h['rasio_hb']).':1 · '.$ikatan.' ikatan',
            $perluIkat ? 'min. '.$h['ikatan_minimum'].' ikatan' : '≤ '.self::a($jenis['hb_maks']).':1 bebas',
            $ikatOk ? 'lulus' : 'gagal');

        // 3. Daya dukung tumpuan
        $rasio = $tanah['izin'] > 0 ? $h['tekanan_tanah_kn_m2'] / $tanah['izin'] : 99;
        if ($rasio > 1.0) {
            $st3 = 'gagal'; $kritis++;
            $saran[] = 'Tekanan tumpuan '.self::a($h['tekanan_tanah_kn_m2']).' kN/m² melampaui daya dukung '
                .self::a($tanah['izin']).' kN/m² — pakai pelat sebar baja atau perkuat tumpuannya.';
        } elseif ($rasio > 0.85) {
            $st3 = 'peringatan'; $warn++;
            $saran[] = 'Tekanan tumpuan di atas 85% daya dukung: pakai sole board ganda atau steel pad.';
        } else {
            $st3 = 'lulus';
        }
        $kriteria[] = self::baris('K-03', 'Daya dukung tumpuan', self::a($h['tekanan_tanah_kn_m2']).' kN/m²',
            '≤ '.self::a($tanah['izin']).' kN/m²', $st3);

        // 4. Angin & desain khusus
        $pe = $tinggi > 15.0 || $jenisKunci === 'kantilever';
        if ($angin > 60.0) {
            $st4 = 'gagal'; $kritis++;
            $saran[] = 'Angin '.self::a($angin).' km/jam melewati batas operasi 60 km/jam — hentikan pekerjaan.';
        } elseif ($angin > 45.0 || $pe) {
            $st4 = 'peringatan'; $warn++;
            $saran[] = $pe
                ? 'Tinggi > 15 m atau kantilever: gambar dan hitungan struktur wajib disahkan insinyur profesional.'
                : 'Angin '.self::a($angin).' km/jam: lepas terpal/sheeting dan pantau anemometer tiap jam.';
        } else {
            $st4 = 'lulus';
        }
        $kriteria[] = self::baris('K-04', 'Beban angin & desain khusus',
            self::a($angin).' km/jam · T '.self::a($tinggi).' m', '≤ 45 km/jam · T ≤ 15 m', $st4);

        // 5. Proteksi jatuh
        $jatuhOk = $tinggi < 1.8 || ($guard && $toe);
        if (!$jatuhOk) {
            $kritis++;
            $saran[] = 'Kerja ≥ 1,8 m wajib top rail, mid rail, dan toe board 150 mm di seluruh sisi terbuka.';
        }
        $kriteria[] = self::baris('K-05', 'Proteksi jatuh',
            ($guard ? 'Guardrail ada' : 'Tanpa guardrail').' · '.($toe ? 'toe board ada' : 'tanpa toe board'),
            'Lengkap bila ≥ 1,8 m', $jatuhOk ? 'lulus' : 'gagal');

        return [
            'putusan' => $kritis > 0 ? 'tidak_layak' : ($warn > 0 ? 'bersyarat' : 'layak'),
            'skor' => max(0, 100 - $kritis * 25 - $warn * 10),
            'perlu_pe' => $pe,
            'kritis' => $kritis,
            'peringatan' => $warn,
            'kriteria' => $kriteria,
            'rekomendasi' => $saran,
        ];
    }

    private static function baris(string $kode, string $nama, string $aktual, string $batas, string $status): array
    {
        return compact('kode', 'nama', 'aktual', 'batas', 'status');
    }

    /** Angka gaya Indonesia, tanpa nol berlebih. */
    private static function a(float|int $x): string
    {
        $s = number_format((float) $x, 2, ',', '.');
        return rtrim(rtrim($s, '0'), ',');
    }
}
