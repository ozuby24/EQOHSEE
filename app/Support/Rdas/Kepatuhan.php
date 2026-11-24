<?php

namespace App\Support\Rdas;

use App\Models\Rdas\{Blok, Kejadian, Laporan, Pantau, Penilaian, Petak, Ppkh};
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Hitungan kepatuhan Rehabilitasi DAS.
 *
 * Semua putusan di modul ini diturunkan dari angka dan tanggal, bukan
 * dipilih orang: fase blok dari tanggal tanam, status tumbuh dari Petak
 * Ukur, kelayakan penilaian dari sembilan prasyarat, batas waktu dari
 * jangka PPKH. Pengguna mengisi apa yang terjadi di lapangan; sistem
 * yang mengatakan apa artinya.
 */
final class Kepatuhan
{
    /**
     * Hasil satu Petak Ukur.
     *
     * Persen tumbuh = (sehat + merana) / diamati. Batang sehat per hektar
     * = proporsi sehat × kerapatan rancangan. Kebutuhan sulam dihitung
     * dari batang mati pada kerapatan rancangan, hanya bila persen tumbuh
     * belum mencapai ambang aman.
     */
    public static function pantau(int $diamati, int $sehat, int $merana, ?int $mati = null, int $kerapatan = Katalog::KERAPATAN, float $luasPetak = 0, int $jumlahPu = 0, float $luasPu = Katalog::LUAS_PU_HA): array
    {
        $diamati = max(0, $diamati);
        $hidup = min($diamati, max(0, $sehat) + max(0, $merana));
        $mati ??= max(0, $diamati - $hidup);
        $tumbuh = $diamati ? round($hidup / $diamati * 100, 2) : 0.0;
        $sehatHa = $diamati ? round($sehat / $diamati * $kerapatan, 1) : 0.0;
        $hidupHa = $diamati ? round($hidup / $diamati * $kerapatan, 1) : 0.0;
        $status = $tumbuh >= Katalog::TUMBUH_AMAN ? 'berhasil' : ($tumbuh >= Katalog::TUMBUH_MIN ? 'peringatan' : 'sulam');
        $sulam = $status === 'berhasil' ? 0 : (int) ceil(max(0, $kerapatan - $hidupHa) * $luasPetak);
        $puMin = $luasPetak > 0 ? (int) max(1, ceil($luasPetak * Katalog::IS_PCT / 100 / $luasPu)) : 0;
        $intensitas = $luasPetak > 0 ? round($jumlahPu * $luasPu / $luasPetak * 100, 2) : 0.0;

        return [
            'diamati' => $diamati, 'sehat' => max(0, $sehat), 'merana' => max(0, $merana), 'mati' => $mati,
            'persen_tumbuh' => $tumbuh, 'sehat_ha' => $sehatHa, 'hidup_ha' => $hidupHa,
            'status' => $status, 'kebutuhan_sulam' => $sulam,
            'pu_minimum' => $puMin, 'intensitas_pct' => $intensitas, 'intensitas_cukup' => $jumlahPu >= $puMin,
            'sehat_cukup' => $sehatHa >= Katalog::SEHAT_HA_MIN,
        ];
    }

    /**
     * Batas waktu penyelesaian kewajiban (Permen LHK P.59/2019):
     * ½ N untuk jangka PPKH di atas 5 tahun, ½ N + 1 tahun untuk jangka
     * 5 tahun atau kurang, dihitung sejak tanggal SK.
     */
    public static function batasSelesai(Carbon|string $tanggalSk, int $jangkaTahun): Carbon
    {
        $t = Carbon::parse($tanggalSk);
        $bulan = (int) round($jangkaTahun * 12 / 2) + ($jangkaTahun > 5 ? 0 : 12);

        return $t->copy()->addMonths($bulan);
    }

    /** Fase blok dari tanggal tanam dan penilaian — tidak pernah dipilih tangan. */
    public static function faseBlok(Blok $b, ?Penilaian $p = null, ?Carbon $hari = null): string
    {
        $hari ??= now();
        $p ??= $b->relationLoaded('penilaianTerakhir') ? $b->penilaianTerakhir : Penilaian::withoutGlobalScopes()->where('blok_id', $b->id)->latest('id')->first();

        if ($p && $p->status === 'serah_terima') {
            // Serah terima bertahap: blok selesai bila seluruh tahap BAST menutup luasnya.
            $bast = $b->relationLoaded('penilaian')
                ? $b->penilaian->where('status', 'serah_terima')->sum('luas_berhasil_ha')
                : Penilaian::withoutGlobalScopes()->where('blok_id', $b->id)->where('status', 'serah_terima')->sum('luas_berhasil_ha');
            if ((float) $bast >= (float) $b->luas_ha - 0.01) return 'serah_terima';
        }
        if ($p && in_array($p->status, Penilaian::AKTIF, true)) return 'penilaian';
        if ($b->tanggal_tanam) {
            $bulan = $b->tanggal_tanam->diffInMonths($hari, false);
            return match (true) {
                $bulan < 12 => 'p0',
                $bulan < 24 => 'p1',
                $bulan < Katalog::USIA_MIN_BULAN => 'p2',
                default => 'siap_nilai',
            };
        }
        if ($b->rkp_disahkan_at) return 'rkp';

        return 'penetapan';
    }

    /** Proporsi komposisi menurut kategori dan kesesuaiannya (lokal ≥ 40%, cepat tumbuh ≤ 60%). */
    public static function komposisi(?array $komposisi): array
    {
        $per = ['lokal' => 0.0, 'cepat' => 0.0, 'mpts' => 0.0];
        $total = 0.0;
        foreach ($komposisi ?? [] as $k) {
            $kat = Katalog::JENIS[$k['jenis'] ?? '']['kategori'] ?? 'lokal';
            $per[$kat] += (float) ($k['persen'] ?? 0);
            $total += (float) ($k['persen'] ?? 0);
        }

        return $per + [
            'total' => round($total, 1),
            'lokal_cukup' => $per['lokal'] >= Katalog::LOKAL_MIN_PCT,
            'cepat_wajar' => $per['cepat'] <= Katalog::CEPAT_MAKS_PCT,
            'genap' => abs($total - 100) < 0.6,
        ];
    }

    /** Rata-rata tertimbang luas dari petak yang sudah dipantau. */
    public static function rataPetak(Collection $petak, string $kolom): ?float
    {
        $ada = $petak->filter(fn (Petak $p) => $p->{$kolom} !== null);
        $luas = $ada->sum('luas_ha');
        if (! $ada->count() || $luas <= 0) return null;

        return round($ada->sum(fn (Petak $p) => $p->{$kolom} * $p->luas_ha) / $luas, 2);
    }

    /**
     * Prasyarat mengajukan penilaian keberhasilan — sembilan butir, setiap
     * butir dengan buktinya. `wajib` = penghenti; selebihnya catatan.
     */
    public static function kelayakan(Blok $b): array
    {
        $petak = $b->relationLoaded('petak') ? $b->petak : Petak::withoutGlobalScopes()->where('blok_id', $b->id)->get();
        $usia = $b->usiaBulan();
        $tumbuh = self::rataPetak($petak, 'persen_tumbuh');
        $sehat = self::rataPetak($petak, 'sehat_ha');
        $belumPantau = $petak->whereNull('persen_tumbuh')->count();
        $tanpaPeta = $petak->filter(fn (Petak $p) => empty($p->poligon) && ($p->lat === null || $p->lng === null))->count();
        $targetBatang = (int) round($b->luas_ha * $b->kerapatan);
        $geotagUnik = max(0, $b->geotag_total - $b->geotag_duplikat);
        $geotagPct = $targetBatang ? round($geotagUnik / $targetBatang * 100, 1) : 0.0;
        $komp = self::komposisi($b->komposisi);
        $lapTerlambat = Laporan::withoutGlobalScopes()->where('ppkh_id', $b->ppkh_id)->whereNull('dikirim_at')->whereDate('batas', '<', now())->count();
        $kebakaran = Kejadian::withoutGlobalScopes()->where('ppkh_id', $b->ppkh_id)->where(fn ($q) => $q->where('blok_id', $b->id)->orWhereNull('blok_id'))
            ->whereIn('jenis', ['kebakaran', 'banjir', 'perambahan'])->where('status', '!=', 'selesai')->count();

        $cek = [
            ['kode' => 'K1', 'label' => 'SK Penetapan Lokasi', 'ok' => filled($b->sk_penlok_nomor), 'wajib' => true, 'bukti' => $b->sk_penlok_nomor ?? 'Belum ada SK Penlok'],
            ['kode' => 'K2', 'label' => 'Rencana Kegiatan Penanaman disahkan', 'ok' => (bool) $b->rkp_disahkan_at, 'wajib' => true, 'bukti' => $b->rkp_nomor ?? 'RKP belum disahkan'],
            ['kode' => 'K3', 'label' => 'Usia tanaman ≥ 36 bulan', 'ok' => $usia !== null && $usia >= Katalog::USIA_MIN_BULAN, 'wajib' => true,
             'bukti' => $usia === null ? 'Belum ditanam' : $usia.' bulan sejak P0 '.$b->tanggal_tanam->translatedFormat('d M Y')],
            ['kode' => 'K4', 'label' => 'Persen tumbuh ≥ 75%', 'ok' => $tumbuh !== null && $tumbuh >= Katalog::TUMBUH_MIN && ! $belumPantau, 'wajib' => true,
             'bukti' => $tumbuh === null ? 'Belum ada Petak Ukur' : number_format($tumbuh, 1, ',', '.').'% rata-rata tertimbang'.($belumPantau ? " · {$belumPantau} petak belum dipantau" : '')],
            ['kode' => 'K5', 'label' => 'Batang sehat ≥ 500/ha', 'ok' => $sehat !== null && $sehat >= Katalog::SEHAT_HA_MIN, 'wajib' => true,
             'bukti' => $sehat === null ? 'Belum ada Petak Ukur' : number_format($sehat, 0, ',', '.').' batang/ha'],
            ['kode' => 'K6', 'label' => 'Geotagging 100% tanpa duplikasi', 'ok' => $geotagPct >= 100 && $b->geotag_duplikat === 0, 'wajib' => true,
             'bukti' => number_format($geotagUnik, 0, ',', '.').' dari '.number_format($targetBatang, 0, ',', '.').' batang ('.number_format($geotagPct, 1, ',', '.').'%)'.($b->geotag_duplikat ? ' · '.number_format($b->geotag_duplikat, 0, ',', '.').' duplikat' : '')],
            ['kode' => 'K7', 'label' => 'Seluruh petak berpeta (poligon/koordinat)', 'ok' => $petak->isNotEmpty() && ! $tanpaPeta, 'wajib' => true,
             'bukti' => $petak->isEmpty() ? 'Belum ada petak' : ($tanpaPeta ? "{$tanpaPeta} petak tanpa peta" : $petak->count().' petak berpeta')],
            ['kode' => 'K8', 'label' => 'Tidak ada gangguan yang belum ditangani', 'ok' => $kebakaran === 0, 'wajib' => true,
             'bukti' => $kebakaran ? "{$kebakaran} kejadian terbuka — laporkan ke BPDAS dan pilih exit strategy" : 'Tidak ada'],
            ['kode' => 'K9', 'label' => 'Laporan semesteran tertib', 'ok' => $lapTerlambat === 0, 'wajib' => false,
             'bukti' => $lapTerlambat ? "{$lapTerlambat} laporan lewat batas" : 'Tidak ada yang terlambat'],
            ['kode' => 'K10', 'label' => 'Komposisi: lokal ≥ 40%, cepat tumbuh ≤ 60%', 'ok' => $komp['lokal_cukup'] && $komp['cepat_wajar'], 'wajib' => false,
             'bukti' => 'Lokal '.round($komp['lokal']).'% · cepat tumbuh '.round($komp['cepat']).'% · MPTS '.round($komp['mpts']).'%'],
        ];
        $penghenti = array_values(array_filter($cek, fn ($c) => $c['wajib'] && ! $c['ok']));

        return [
            'layak' => ! $penghenti, 'cek' => $cek,
            'terpenuhi' => count(array_filter($cek, fn ($c) => $c['ok'])), 'jumlah' => count($cek),
            'persen_tumbuh' => $tumbuh, 'sehat_ha' => $sehat, 'usia_bulan' => $usia, 'geotag_pct' => $geotagPct,
            'penghenti' => array_map(fn ($c) => $c['label'], $penghenti),
        ];
    }

    /** Menyalin PU terakhir ke petak dan menurunkan ulang fase blok. */
    public static function sinkronPetak(Petak $p): void
    {
        $akhir = Pantau::withoutGlobalScopes()->where('petak_id', $p->id)->orderByDesc('tanggal')->orderByDesc('id')->first();
        $p->forceFill([
            'persen_tumbuh' => $akhir?->persen_tumbuh, 'sehat_ha' => $akhir?->sehat_ha,
            'status_tumbuh' => $akhir?->status, 'dipantau_at' => $akhir?->tanggal,
        ])->save();
    }

    public static function sinkronFase(Blok $b): string
    {
        $fase = self::faseBlok($b);
        if ($b->fase !== $fase) {
            Blok::withoutGlobalScopes()->whereKey($b->id)->update(['fase' => $fase]);
            $b->fase = $fase;
        }

        return $fase;
    }

    /**
     * Ringkasan kewajiban satu PPKH: corong kewajiban → ditetapkan →
     * ditanam → diserahterimakan, sisa waktu, dan sanksi tertinggi yang
     * pernah diterima.
     */
    public static function ringkasPpkh(Ppkh $p, ?Collection $blok = null): array
    {
        $blok ??= Blok::withoutGlobalScopes()->where('ppkh_id', $p->id)->with('penilaian')->get();
        $ditetapkan = $blok->filter(fn ($b) => filled($b->sk_penlok_nomor))->sum('luas_ha');
        $ditanam = $blok->filter(fn ($b) => $b->tanggal_tanam !== null)->sum('luas_ha');
        $bast = $blok->sum(fn ($b) => $b->penilaian->where('status', 'serah_terima')->sum('luas_berhasil_ha'));
        $sanksi = Kejadian::withoutGlobalScopes()->where('ppkh_id', $p->id)->get()
            ->map(fn ($k) => Katalog::KEJADIAN[$k->jenis]['tahap'] ?? 0)->max() ?? 0;
        $kew = max(0.01, $p->kewajiban_ha);

        return [
            'kewajiban' => round($p->kewajiban_ha, 2),
            'ditetapkan' => round($ditetapkan, 2), 'ditanam' => round($ditanam, 2), 'bast' => round($bast, 2),
            'ditetapkan_pct' => round(min(100, $ditetapkan / $kew * 100), 1),
            'ditanam_pct' => round(min(100, $ditanam / $kew * 100), 1),
            'bast_pct' => round(min(100, $bast / $kew * 100), 1),
            'belum_ditetapkan' => round(max(0, $p->kewajiban_ha - $ditetapkan), 2),
            'sisa_hari' => $p->sisaHari(),
            'waktu' => $p->sisaHari() < 0 ? 'lewat' : ($p->sisaHari() <= Katalog::PERINGATAN_HARI ? 'mendesak' : 'aman'),
            'sanksi_tahap' => (int) $sanksi,
        ];
    }

    /** Jadwal laporan semesteran: semester I paling lambat 31 Juli, semester II 31 Januari tahun berikut. */
    public static function batasLaporan(int $tahun, int $semester): Carbon
    {
        return $semester === 1 ? Carbon::create($tahun, 7, 31) : Carbon::create($tahun + 1, 1, 31);
    }
}
