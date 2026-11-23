<?php

namespace App\Support\Angkat;

use App\Models\Angkat\{Alat, Gear, Riksa};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Riksa uji pesawat angkat dan alat bantu angkat (Permenaker 8/2020,
 * ASME B30.5/B30.9/B30.26).
 *
 * Putusannya tidak dipilih pemeriksa — dihitung dari yang diperiksanya:
 * satu butir kritis gagal, NDT menemukan cacat, deformasi permanen
 * sesudah uji beban, atau satu ukuran melewati batas tolak ASME, dan
 * alatnya TIDAK LAYAK DIGUNAKAN. Pada saat itu juga alat dikarantina
 * (warna merah) sehingga tidak dapat dipilih di rencana pengangkatan
 * mana pun sampai riksa uji berikutnya meluluskannya.
 */
final class RiksaUji
{
    /** Beban uji statis/dinamis, lama penahanan, dan masa berlaku untuk SWL tertentu. */
    public static function beban(string $kategori, float $swl): array
    {
        $s = Katalog::UJI[$kategori] ?? Katalog::UJI['wire_rope'];

        return [
            'statis_pct' => $s['statis'], 'beban_statis_t' => round($swl * $s['statis'] / 100, 2),
            'dinamis_pct' => $s['dinamis'], 'beban_dinamis_t' => round($swl * $s['dinamis'] / 100, 2),
            'tahan_menit' => $s['tahan'], 'bulan' => $s['bulan'], 'defleksi_batas_mm' => $s['defleksi_mm'],
        ];
    }

    /**
     * Menilai temuan tanpa menyimpan — dipakai pratinjau form dan catat().
     *
     * @param  array{kategori_uji:string, periksa:array<string,string>, ukur?:array<string,float|null>, ndt_metode?:string, ndt_hasil?:string}  $d
     */
    public static function nilai(array $d): array
    {
        $kategori = $d['kategori_uji'];
        $butir = collect(Katalog::BUTIR[$kategori] ?? [])->map(fn ($b) => $b + ['status' => $d['periksa'][$b['kode']] ?? 'lulus'])->all();
        $alasan = [];

        foreach ($butir as $b) {
            if ($b['status'] === 'gagal' && $b['kritis']) $alasan[] = $b['kode'].' gagal: '.$b['label'].'.';
        }
        if (($d['ndt_hasil'] ?? 'tanpa_indikasi') === 'ada_cacat') {
            $alasan[] = 'NDT ('.($d['ndt_metode'] ?? 'MPI').') menemukan indikasi retak/cacat material.';
        }

        $batasDefleksi = (float) (Katalog::UJI[$kategori]['defleksi_mm'] ?? 0);
        foreach (Katalog::UKUR as $k => $u) {
            if (! in_array($kategori, $u['berlaku'], true)) continue;
            $v = $d['ukur'][$k] ?? null;
            if ($v === null || $v === '') continue;
            $batas = $k === 'defleksi_mm' ? $batasDefleksi : $u['batas'];
            if ((float) $v > $batas) {
                $alasan[] = sprintf('%s %s %s melewati batas %s %s.', $u['label'], self::f($v), $u['satuan'], self::f($batas), $u['satuan']);
            }
        }

        $minor = collect($butir)->where('status', 'gagal')->where('kritis', false)->count();

        return [
            'butir' => $butir,
            'putusan' => $alasan ? 'tidak_layak' : 'layak',
            'alasan' => $alasan,
            'minor' => $minor,
        ];
    }

    /** Menyimpan laporan dan menyelaraskan status, warna, serta tanggal uji objeknya. */
    public static function catat(Alat|Gear $objek, array $d, ?int $userId = null, ?Carbon $tanggal = null): Riksa
    {
        $tanggal ??= now();
        $alat = $objek instanceof Alat;
        $kategori = $alat ? 'crane' : (Katalog::GEAR[$objek->jenis]['uji'] ?? 'wire_rope');
        $swl = (float) ($d['swl_t'] ?? ($alat ? $objek->kapasitas_t : $objek->wll_t));
        $b = self::beban($kategori, $swl);
        $n = self::nilai(['kategori_uji' => $kategori] + $d);
        $layak = $n['putusan'] === 'layak';
        $triwulan = ($d['jenis'] ?? 'berkala') === 'triwulan';
        $berlaku = $layak ? $tanggal->copy()->addMonths($triwulan ? 3 : $b['bulan']) : null;
        $warna = $layak ? Katalog::warnaTriwulan($tanggal) : 'MERAH';

        $catatan = $n['alasan'] ? implode(' ', $n['alasan']) : ($n['minor'] ? $n['minor'].' butir minor perlu perbaikan sebelum uji berikutnya.' : null);
        if (! empty($d['catatan'])) $catatan = trim(($catatan ? $catatan.' ' : '').$d['catatan']);

        return DB::transaction(function () use ($objek, $alat, $kategori, $swl, $b, $n, $d, $userId, $tanggal, $berlaku, $warna, $catatan, $layak, $triwulan) {
            $r = Riksa::create([
                'company_id' => $objek->company_id, 'user_id' => $userId,
                'alat_id' => $alat ? $objek->id : null, 'gear_id' => $alat ? null : $objek->id,
                'nomor' => self::nomor($objek->company_id, $tanggal),
                'objek' => $alat ? 'alat' : 'gear', 'kategori_uji' => $kategori, 'jenis' => $d['jenis'] ?? 'berkala',
                'pemeriksa' => $d['pemeriksa'], 'skp_nomor' => $d['skp_nomor'], 'pjk3' => $d['pjk3'] ?? null,
                'swl_t' => $swl,
                'uji_statis_pct' => $triwulan ? 0 : $b['statis_pct'], 'beban_statis_t' => $triwulan ? 0 : $b['beban_statis_t'],
                'uji_dinamis_pct' => $triwulan ? 0 : $b['dinamis_pct'], 'beban_dinamis_t' => $triwulan ? 0 : $b['beban_dinamis_t'],
                'tahan_menit' => $triwulan ? 0 : $b['tahan_menit'],
                'ukur' => array_filter($d['ukur'] ?? [], fn ($v) => $v !== null && $v !== ''),
                'ndt_metode' => $d['ndt_metode'] ?? 'MPI', 'ndt_hasil' => $d['ndt_hasil'] ?? 'tanpa_indikasi',
                'butir' => $n['butir'], 'putusan' => $n['putusan'], 'alasan' => $catatan,
                'kode_warna' => $warna, 'diperiksa_at' => $tanggal->toDateString(), 'berlaku_sampai' => $berlaku?->toDateString(),
            ]);

            if ($alat) {
                $objek->update($layak
                    ? ['status' => 'layak', 'suket_nomor' => $triwulan ? $objek->suket_nomor : 'SUKET/'.$r->nomor,
                       'suket_terbit' => $triwulan ? $objek->suket_terbit : $tanggal->toDateString(),
                       'suket_berlaku' => $triwulan ? $objek->suket_berlaku : $berlaku->toDateString()]
                    : ['status' => 'karantina']);
            } else {
                $objek->update($layak
                    ? ['status' => 'layak', 'kode_warna' => $warna, 'uji_terakhir' => $tanggal->toDateString(),
                       'uji_berikut' => $triwulan && $objek->uji_berikut ? min($objek->uji_berikut, $berlaku)->toDateString() : $berlaku->toDateString()]
                    : ['status' => 'karantina', 'kode_warna' => 'MERAH', 'uji_terakhir' => $tanggal->toDateString()]);
            }

            return $r;
        });
    }

    public static function nomor(?int $company, Carbon $t): string
    {
        $n = Riksa::withoutGlobalScopes()->where('company_id', $company)->whereYear('diperiksa_at', $t->year)->count() + 1;
        do {
            $nomor = sprintf('RKS-PAA/%s/%04d', $t->format('Y'), $n++);
        } while (Riksa::withoutGlobalScopes()->where('company_id', $company)->where('nomor', $nomor)->exists());

        return $nomor;
    }

    private static function f(mixed $x): string
    {
        return rtrim(rtrim(number_format((float) $x, 2, ',', '.'), '0'), ',');
    }
}
