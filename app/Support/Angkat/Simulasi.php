<?php

namespace App\Support\Angkat;

/**
 * Kalkulator rigging dan simulasi pengangkatan 4 fase.
 *
 * Diporting dari RiggingCalculatorService + LiftSimulationService
 * LiftGuard, dengan tiga perubahan yang disengaja:
 *  - tegangan sling dihitung dari berat bersih × DAF, bukan berat bersih
 *    saja — sling ikut menanggung hentakan saat beban lepas landas;
 *  - kapasitas dibaca dari crane yang dipilih di register bila ada, bukan
 *    hanya dari katalog, sehingga crane 100 t yang SUKET-nya diturunkan
 *    menjadi 80 t dihitung sebagai 80 t;
 *  - hasilnya membawa kurva beban, radius maksimum aman, dan berat bersih
 *    maksimum pada radius itu — tiga angka yang ditanyakan pengawas di
 *    lapangan ketika rencana harus digeser.
 *
 * Semua fungsi murni: tidak menyentuh basis data, bisa dipanggil dari
 * pratinjau form, penyimpanan, data contoh, maupun uji.
 */
final class Simulasi
{
    /** Ukuran WLL sling/shackle niaga yang umum tersedia (ton). */
    private const UKURAN_WLL = [2, 3.25, 4.75, 6.5, 8.5, 9.5, 12, 13.5, 17, 25, 35, 55, 85, 120, 150];

    /** Masukan bawaan — juga dipakai form "rencana baru". */
    public const BAWAAN = [
        'jenis_alat' => 'mobile_100t', 'kategori_beban' => 'komponen_alat_berat', 'tanah' => 'bench',
        'berat_bersih_t' => 14.5, 'berat_rigging_t' => 1.1, 'daf' => null,
        'radius_m' => 12, 'boom_m' => 28, 'tinggi_rintangan_m' => 4.5, 'tinggi_beban_m' => 3, 'tinggi_rigging_m' => 3.5,
        'angin_kmh' => 22, 'luas_mat_m2' => 4, 'kemiringan_pct' => 0.5, 'jarak_sutt_m' => 25,
        'jumlah_kaki' => 4, 'sudut_sling' => 60, 'jenis_sling' => 'wire_rope', 'hitch' => 'bridle', 'cog_offset_pct' => 0,
        'wll_sling_t' => 12, 'wll_shackle_t' => 17, 'spreader' => false, 'blind_lift' => false, 'di_atas_fasilitas' => false,
    ];

    /**
     * Tegangan kaki sling, gaya tekan horizontal, dan rekomendasi WLL
     * (ASME B30.9 & B30.26).
     */
    public static function rigging(array $m, float $bebanT): array
    {
        $kaki = max(1, min(4, (int) ($m['jumlah_kaki'] ?? 2)));
        $sudut = max(15.0, min(90.0, (float) ($m['sudut_sling'] ?? 60)));
        $hitch = (float) (Katalog::HITCH[$m['hitch'] ?? 'bridle']['faktor'] ?? 1.0);
        $cog = max(0.0, min(35.0, (float) ($m['cog_offset_pct'] ?? 0)));
        $wllSling = max(0.5, (float) ($m['wll_sling_t'] ?? 12));
        $wllShackle = max(0.5, (float) ($m['wll_shackle_t'] ?? 17));
        $spreader = (bool) ($m['spreader'] ?? false);
        $sf = (float) (Katalog::GEAR[$m['jenis_sling'] ?? 'wire_rope']['sf'] ?? 5.0);

        // Beban kaku dengan empat kaki praktis ditanggung tiga kaki, tiga kaki oleh dua (ASME B30.9).
        $efektif = [1 => 1, 2 => 2, 3 => 2, 4 => 3][$kaki];
        $sudutEf = ($spreader || $kaki === 1) ? 90.0 : $sudut;
        $rad = deg2rad($sudutEf);
        $sin = max(0.15, sin($rad));

        $berat = 0.5 + $cog / 100;
        $ringan = 0.5 - $cog / 100;
        if ($kaki === 1) {
            $maks = $bebanT / $hitch;
            $min = $maks;
        } else {
            $dasar = $bebanT / ($efektif * $sin * $hitch);
            $maks = $dasar * $berat / 0.5;
            $min = $dasar * $ringan / 0.5;
        }

        $tekan = ($spreader || $kaki === 1) ? 0.0 : round($maks * cos($rad), 2);
        $utilSling = round($maks / $wllSling * 100, 1);
        $utilShackle = round($maks / $wllShackle * 100, 1);
        $rekomendasi = collect(self::UKURAN_WLL)->first(fn ($u) => $u >= $maks * 1.15) ?? ceil($maks * 1.2);

        $catatan = [];
        $status = 'lulus';
        if ($sudutEf < 30) {
            $status = 'gagal';
            $catatan[] = 'Sudut sling di bawah 30° tidak diizinkan (ASME B30.9): tegangan lebih dari dua kali lipat. Pakai sling lebih panjang atau spreader beam.';
        } elseif ($sudutEf < 45) {
            $status = 'waspada';
            $catatan[] = 'Sudut sling di bawah 45°: naikkan ke ≥ 60° untuk menekan gaya tekan horizontal.';
        }
        if ($utilSling > 100) {
            $status = 'gagal';
            $catatan[] = sprintf('Tegangan kaki sling %s t melampaui WLL sling %s t — naikkan WLL ke minimal %s t.', self::f($maks), self::f($wllSling), self::f($rekomendasi));
        } elseif ($utilSling > 90 && $status === 'lulus') {
            $status = 'waspada';
            $catatan[] = 'Utilisasi sling di atas 90% WLL — sisakan cadangan untuk hentakan.';
        }
        if ($utilShackle > 100) {
            $status = 'gagal';
            $catatan[] = sprintf('Beban shackle %s t melampaui WLL shackle %s t.', self::f($maks), self::f($wllShackle));
        }
        if ($cog >= 10) {
            $catatan[] = 'Titik berat bergeser '.self::f($cog).'%: kaki di sisi berat menanggung '.self::f($berat / 0.5 * 100).'% beban rata-rata.';
        }

        return [
            'kaki' => $kaki, 'kaki_efektif' => $efektif, 'sudut_efektif' => round($sudutEf, 1),
            'lam' => round(1 / $sin, 3), 'faktor_hitch' => $hitch, 'sf' => $sf,
            'beban_t' => round($bebanT, 2),
            'tegangan_maks_t' => round($maks, 2), 'tegangan_min_t' => round($min, 2), 'tegangan_maks_kn' => round($maks * 9.81, 1),
            'tekan_horizontal_t' => $tekan,
            'util_sling' => $utilSling, 'util_shackle' => $utilShackle,
            'wll_sling_t' => $wllSling, 'wll_shackle_t' => $wllShackle,
            'wll_rekomendasi_t' => $rekomendasi, 'mbl_perlu_t' => round($maks * $sf, 1),
            'status' => $status, 'catatan' => $catatan,
        ];
    }

    /**
     * Simulasi lengkap. $alat (opsional) menimpa angka katalog dengan
     * angka crane yang terdaftar: kapasitas_t, berat_t, boom_m, hook_t.
     */
    public static function hitung(array $masukan, ?array $alat = null): array
    {
        $m = array_merge(self::BAWAAN, array_filter($masukan, fn ($v) => $v !== null && $v !== ''));
        $kat = Katalog::ALAT[$m['jenis_alat']] ?? Katalog::ALAT['mobile_100t'];
        $spek = array_merge(['kapasitas_t' => $kat['kapasitas_t'], 'berat_t' => $kat['berat_t'], 'boom_m' => $kat['boom_m'], 'hook_t' => $kat['hook_t']],
            array_filter($alat ?? [], fn ($v) => $v !== null && (float) $v > 0));
        $kategori = $kat['kategori'];
        $tetap = in_array($kategori, ['overhead', 'hoist'], true);
        $beban = (string) $m['kategori_beban'];
        $tanah = Katalog::TANAH[$m['tanah']] ?? Katalog::TANAH['bench'];

        $bersih = max(0.1, (float) $m['berat_bersih_t']);
        $rigging = max(0.0, (float) $m['berat_rigging_t']);
        $hook = (float) $spek['hook_t'];
        $daf = (float) ($masukan['daf'] ?? null ?: $kat['daf']);
        $radius = max(3.0, (float) $m['radius_m']);
        $boom = max($radius + 2.0, min((float) $spek['boom_m'], (float) $m['boom_m']));
        $angin = max(0.0, (float) $m['angin_kmh']);
        $mat = max(0.5, (float) $m['luas_mat_m2']);
        $miring = max(0.0, (float) $m['kemiringan_pct']);
        $sutt = max(0.0, (float) $m['jarak_sutt_m']);

        // 1. Beban kotor di kait.
        $kotor = round(($bersih + $rigging + $hook) * $daf, 2);

        // 2. Kapasitas pada radius & panjang boom ini, dan batas utilisasi.
        $kapasitas = self::kapasitas($spek['kapasitas_t'], $kategori, $radius, $boom);
        $util = round($kotor / max(0.1, $kapasitas) * 100, 1);
        $batas = $beban === 'man_basket' ? 50.0 : ($kategori === 'tandem' ? 75.0 : 85.0);

        // 3. Geometri boom dan ruang bebas.
        $sudutBoom = $tetap ? 90.0 : round(rad2deg(acos(min(0.98, $radius / $boom))), 1);
        $ujung = $tetap ? round($boom, 2) : round(2.4 + $boom * sin(deg2rad($sudutBoom)), 2);
        $perlu = round((float) $m['tinggi_rintangan_m'] + (float) $m['tinggi_beban_m'] + (float) $m['tinggi_rigging_m'] + 2.0, 2);
        $ruangOk = $ujung >= $perlu;

        // 4. Tekanan outrigger ke landasan tambang.
        $gaya = $tetap ? 0.0 : round((0.35 * (float) $spek['berat_t'] + 0.75 * $kotor) * 9.81, 1);
        $tekanan = $tetap ? 0.0 : round($gaya / $mat, 1);
        $izin = (float) $tanah['izin'];
        $matMin = $tetap ? 0.0 : round($gaya / max(10.0, $izin), 2);
        $tanahOk = $tetap || $tekanan <= $izin;

        // 5. Rigging dengan beban dinamis.
        $rig = self::rigging($m, $bersih * $daf);

        // 6. Klasifikasi.
        $alasanKritis = array_values(array_filter([
            $util > 75 ? 'utilisasi crane '.self::f($util).'%' : null,
            $bersih >= 20 ? 'beban '.self::f($bersih).' t' : null,
            $kategori === 'tandem' ? 'pengangkatan tandem' : null,
            $beban === 'man_basket' ? 'mengangkat personel' : null,
            $sutt < 10 ? 'jarak SUTT '.self::f($sutt).' m' : null,
            ! empty($m['di_atas_fasilitas']) ? 'melintas di atas fasilitas yang beroperasi' : null,
        ]));
        $rekayasa = $util >= 50 || $bersih >= 10 || (float) $m['cog_offset_pct'] >= 10 || ! empty($m['blind_lift']);
        $klasifikasi = $alasanKritis ? 'kritis' : ($rekayasa ? 'rekayasa' : 'rutin');

        $craneOk = $util <= $batas;
        $anginOk = $angin <= (float) $kat['angin_kmh'];
        $miringOk = $tetap || $miring <= 1.0;
        $suttOk = $sutt >= 6.0;

        // 7. Empat fase.
        $fase = [
            ['fase' => 1, 'nama' => 'Pre-tension & lepas landas', 'ikon' => 'link-2',
             'status' => $rig['status'],
             'rinci' => sprintf('Tegangan kaki maks %s t (%s%% WLL) · sudut %s°', self::f($rig['tegangan_maks_t']), self::f($rig['util_sling']), self::f($rig['sudut_efektif']))],
            ['fase' => 2, 'nama' => 'Hoist vertikal & ruang bebas', 'ikon' => 'arrow-up-from-line',
             'status' => (! $ruangOk || ! $craneOk) ? 'gagal' : ($util > 75 ? 'waspada' : 'lulus'),
             'rinci' => sprintf('Ujung boom %s m vs perlu %s m · utilisasi %s%% (batas %s%%)', self::f($ujung), self::f($perlu), self::f($util), self::f($batas))],
            ['fase' => 3, 'nama' => 'Slewing & stabilitas landasan', 'ikon' => 'rotate-3d',
             'status' => (! $tanahOk || ! $anginOk || ! $miringOk) ? 'gagal' : ((! $tetap && $tekanan > $izin * 0.85) || $angin > $kat['angin_kmh'] * 0.85 ? 'waspada' : 'lulus'),
             'rinci' => $tetap ? sprintf('Crane terpasang tetap · angin %s km/jam', self::f($angin))
                 : sprintf('Outrigger %s / %s kN/m² · angin %s km/jam · kemiringan %s%%', self::f($tekanan), self::f($izin), self::f($angin), self::f($miring))],
            ['fase' => 4, 'nama' => 'Penurunan & penempatan', 'ikon' => 'crosshair',
             'status' => (! $suttOk || ! $craneOk) ? 'gagal' : ($sutt < 10 ? 'waspada' : 'lulus'),
             'rinci' => sprintf('Jarak SUTT %s m (min. 6 m) · cadangan kapasitas %s%%', self::f($sutt), self::f(max(0, 100 - $util)))],
        ];
        $gagal = count(array_filter($fase, fn ($f) => $f['status'] === 'gagal'));
        $waspada = count(array_filter($fase, fn ($f) => $f['status'] === 'waspada'));
        $skor = max(0, 100 - $gagal * 28 - $waspada * 8);
        $putusan = $gagal ? 'nogo' : ($klasifikasi === 'kritis' || $waspada ? 'bersyarat' : 'go');

        // 8. Angka bantu untuk menggeser rencana.
        $radiusAman = null;
        for ($r = 3.0; $r <= $boom * 0.98; $r += 0.5) {
            if ($kotor <= self::kapasitas($spek['kapasitas_t'], $kategori, $r, $boom) * $batas / 100) $radiusAman = $r;
            else break;
        }
        $bersihMaks = round(max(0, $kapasitas * $batas / 100 / $daf - $rigging - $hook), 2);

        $kurva = [];
        $ujungR = $tetap ? 12 : (int) floor($boom * 0.95);
        for ($r = 3; $r <= max(4, $ujungR); $r++) {
            $kurva[] = ['r' => $r, 'kap' => self::kapasitas($spek['kapasitas_t'], $kategori, $r, $boom)];
        }

        return [
            'masukan' => $m,
            'alat' => ['jenis' => $m['jenis_alat'], 'label' => $kat['label'], 'kategori' => $kategori] + $spek + ['angin_maks' => $kat['angin_kmh']],
            'daf' => $daf, 'hook_t' => $hook,
            'berat_bersih_t' => $bersih, 'berat_kotor_t' => $kotor,
            'kapasitas_t' => $kapasitas, 'utilisasi_pct' => $util, 'batas_util' => $batas,
            'radius_m' => $radius, 'boom_m' => $boom, 'sudut_boom' => $sudutBoom,
            'tinggi_ujung_m' => $ujung, 'tinggi_perlu_m' => $perlu, 'tinggi_rintangan_m' => (float) $m['tinggi_rintangan_m'],
            'gaya_outrigger_kn' => $gaya, 'tekanan_tanah' => $tekanan, 'izin_tanah' => $izin, 'mat_min_m2' => $matMin,
            'tanah' => $tanah['label'],
            'rigging' => $rig,
            'klasifikasi' => $klasifikasi, 'alasan_kritis' => $alasanKritis,
            'fase' => $fase, 'skor' => $skor, 'putusan' => $putusan,
            'radius_aman_m' => $radiusAman, 'bersih_maks_t' => $bersihMaks,
            'kurva' => $kurva,
            'cek' => ['crane' => $craneOk, 'angin' => $anginOk, 'miring' => $miringOk, 'sutt' => $suttOk, 'ruang' => $ruangOk, 'tanah' => $tanahOk],
        ];
    }

    /**
     * Kapasitas terpasang pada radius & boom — pendekatan kurva load
     * chart (turun terhadap radius, dipotong untuk boom panjang). Crane
     * tetap dan hoist datar. Tandem diturunkan 25% (ASME B30.5/BS 7121).
     */
    public static function kapasitas(float $maks, string $kategori, float $radius, float $boom): float
    {
        if (in_array($kategori, ['overhead', 'hoist'], true)) return round($maks, 2);

        $faktorR = min(1.0, 5.2 / max(3.0, $radius));
        $potongBoom = 1.0 - max(0.0, ($boom - 16.0) / 160.0);
        $kap = max(2.0, $maks * pow($faktorR, 0.88) * $potongBoom);
        if ($kategori === 'tandem') $kap *= 0.75;

        return round($kap, 2);
    }

    private static function f(float|int|null $x): string
    {
        return rtrim(rtrim(number_format((float) $x, 2, ',', '.'), '0'), ',');
    }
}
