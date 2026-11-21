<?php

namespace App\Support\Energi;

use App\Models\{ActivityLog, EnergyEquipment, EnergyFuelLog, EnergyFuelRecon, EnergyOtherLog,
                EnergyPowerLog, EnergyProduction};
use App\Models\Energi\{Koreksi, Laporan, Pengaturan, Periode};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Alur data harian dan kunci periode bulanan (FSD §7).
 *
 *   draf → diajukan → terverifikasi → terkunci
 *               ↘ ditolak (beralasan) → diajukan lagi
 *
 * Periode hanya dapat dikunci bila empat syaratnya terpenuhi (§7.2), dan
 * sesudah terkunci perubahan hanya lewat koreksi yang disetujui — koreksi
 * itu menandai setiap laporan terbit yang memakai periodenya `perlu_revisi`.
 */
final class AlurPeriode
{
    /** Model catatan harian yang ikut alur, berlabel. */
    public const MODEL = [
        'bbm'      => [EnergyFuelLog::class,    'Solar per unit'],
        'listrik'  => [EnergyPowerLog::class,   'Listrik'],
        'lain'     => [EnergyOtherLog::class,   'Gas & energi lain'],
        'produksi' => [EnergyProduction::class, 'Produksi'],
        'rekon'    => [EnergyFuelRecon::class,  'Rekonsiliasi BBM'],
    ];

    /** Kueri satu jenis catatan pada satu perusahaan & bulan, TANPA scope — perusahaannya disebut tegas. */
    public static function kueri(string $jenis, ?int $companyId, Carbon $mulai, Carbon $sampai)
    {
        [$kelas] = self::MODEL[$jenis];

        $q = $kelas::withoutGlobalScopes()->whereBetween('tanggal', Inventaris::sela($mulai, $sampai));

        if ($kelas === EnergyFuelLog::class) {
            return $q->whereIn('equipment_id', EnergyEquipment::withoutGlobalScopes()
                ->where(fn ($e) => $companyId === null ? $e->whereNull('company_id') : $e->where('company_id', $companyId))
                ->select('id'));
        }

        return $q->where(fn ($w) => $companyId === null ? $w->whereNull('company_id') : $w->where('company_id', $companyId));
    }

    /**
     * Kelengkapan data: unit-hari dan hari produksi yang terisi atas yang
     * semestinya, sampai hari ini bila bulannya masih berjalan.
     */
    public static function kelengkapan(?int $companyId, Carbon $mulai, Carbon $sampai): array
    {
        $akhir = $sampai->copy()->min(now()->startOfDay());
        $hari = $akhir->lt($mulai) ? 0 : (int) $mulai->diffInDays($akhir) + 1;

        $unit = EnergyEquipment::withoutGlobalScopes()->where('aktif', true)
            ->where(fn ($e) => $companyId === null ? $e->whereNull('company_id') : $e->where('company_id', $companyId))
            ->count();

        $unitHari = $hari > 0 ? self::kueri('bbm', $companyId, $mulai, $akhir)->toBase()->select('equipment_id', 'tanggal')->distinct()->get()->count() : 0;
        $prodHari = $hari > 0 ? self::kueri('produksi', $companyId, $mulai, $akhir)->distinct()->count('tanggal') : 0;

        $harap = $unit * $hari + $hari;
        $terisi = min($unitHari, $unit * $hari) + min($prodHari, $hari);

        return [
            'hari' => $hari, 'unit_aktif' => $unit, 'unit_hari' => $unitHari, 'hari_produksi' => $prodHari,
            'persen' => $harap > 0 ? $terisi / $harap * 100 : null,
        ];
    }

    /**
     * Syarat kunci periode (FSD §7.2), masing-masing ✔/✖ beserta alasannya.
     *
     * @return array{boleh:bool,syarat:list<array{kode:string,label:string,ok:bool,rincian:string}>,kelengkapan:array,inventaris:array}
     */
    public static function syaratKunci(Periode $p): array
    {
        $mulai = $p->mulai();
        $sampai = $p->sampai();
        $atur = Pengaturan::untuk($p->company_id);

        $leng = self::kelengkapan($p->company_id, $mulai, $sampai);

        $belum = 0;
        foreach (array_keys(self::MODEL) as $j) {
            $belum += self::kueri($j, $p->company_id, $mulai, $sampai)->whereIn('status', ['draf', 'diajukan', 'ditolak'])->count();
        }

        $inv = Inventaris::hitung($mulai, $sampai, ['company_id' => $p->company_id]);
        $kurang = collect($inv['baris'])->filter(fn ($b) => $b['kurang'])->count();

        $rekon = self::kueri('rekon', $p->company_id, $mulai, $sampai)->count();

        $s = [
            ['kode' => 'kelengkapan', 'label' => 'Kelengkapan data ≥ '.number_format($atur->ambang_kelengkapan_persen, 0).'%',
             'ok' => $leng['persen'] !== null && $leng['persen'] >= $atur->ambang_kelengkapan_persen,
             'rincian' => $leng['persen'] === null ? 'Belum ada hari operasi yang dapat dinilai.' : number_format($leng['persen'], 1, ',', '.').'% terisi'],
            ['kode' => 'verifikasi', 'label' => 'Tidak ada baris draf/diajukan/ditolak',
             'ok' => $belum === 0, 'rincian' => $belum === 0 ? 'Seluruh baris terverifikasi.' : "{$belum} baris belum terverifikasi."],
            ['kode' => 'faktor', 'label' => 'Tidak ada baris "faktor tidak tersedia"',
             'ok' => $kurang === 0, 'rincian' => $kurang === 0 ? 'Seluruh baris dapat dihitung.' : "{$kurang} kelompok baris kekurangan faktor."],
            ['kode' => 'rekonsiliasi', 'label' => 'Rekonsiliasi BBM bulan ini terisi',
             'ok' => $rekon > 0, 'rincian' => $rekon > 0 ? "{$rekon} catatan rekonsiliasi." : 'Belum ada rekonsiliasi tangki.'],
        ];

        return [
            'boleh' => collect($s)->every(fn ($x) => $x['ok']),
            'syarat' => $s, 'kelengkapan' => $leng,
            'inventaris' => Inventaris::ringkas($inv['baris']),
        ];
    }

    /** Kunci periode: seluruh baris bulan itu menjadi `terkunci`. */
    public static function kunci(Periode $p, int $userId): void
    {
        DB::transaction(function () use ($p, $userId) {
            foreach (array_keys(self::MODEL) as $j) {
                self::kueri($j, $p->company_id, $p->mulai(), $p->sampai())->update(['status' => 'terkunci']);
            }

            $leng = self::kelengkapan($p->company_id, $p->mulai(), $p->sampai());

            $p->forceFill([
                'status' => 'terkunci', 'dikunci_oleh' => $userId, 'dikunci_pada' => now(),
                'kelengkapan_persen' => $leng['persen'],
            ])->save();
        });

        ActivityLog::write('Kunci periode energi', $p->label(), 'energi');
    }

    /**
     * Setujui koreksi: nilai baru diterapkan pada baris terkunci, dan setiap
     * laporan terbit yang mencakup periode itu ditandai perlu revisi.
     */
    public static function setujuiKoreksi(Koreksi $k, int $userId, ?string $catatan = null): void
    {
        DB::transaction(function () use ($k, $userId, $catatan) {
            $kelas = $k->subjek_type;
            $baris = $kelas::withoutGlobalScopes()->findOrFail($k->subjek_id);

            $kelas::lewatKoreksi(function () use ($baris, $k) {
                $baris->fill($k->nilai_baru)->save();
            });

            $k->forceFill(['status' => 'disetujui', 'diputus_oleh' => $userId, 'diputus_pada' => now(),
                           'catatan_putusan' => $catatan])->save();

            $p = $k->periode;
            Laporan::withoutGlobalScopes()
                ->where(fn ($q) => $p->company_id === null ? $q->whereNull('company_id') : $q->where('company_id', $p->company_id))
                ->whereIn('status', ['terbit', 'disetujui', 'diajukan'])
                ->whereDate('periode_mulai', '<=', $p->sampai()->toDateString())
                ->whereDate('periode_sampai', '>=', $p->mulai()->toDateString())
                ->update(['status' => 'perlu_revisi']);
        });

        ActivityLog::write('Setujui koreksi energi', class_basename($k->subjek_type).' #'.$k->subjek_id, 'energi');
    }

    /** Kolom yang boleh dikoreksi per jenis — kolom kuantitas, bukan kolom alur. */
    public const KOLOM_KOREKSI = [
        EnergyFuelLog::class    => ['hm', 'liter', 'idle_jam', 'jarak_km', 'ton', 'bcm', 'persen_fame'],
        EnergyPowerLog::class   => ['kwh', 'puncak_kw', 'jam_operasi', 'liter_genset'],
        EnergyOtherLog::class   => ['jumlah'],
        EnergyProduction::class => ['ton', 'bcm', 'stripping_ratio', 'jarak_angkut_km'],
        EnergyFuelRecon::class  => ['stok_awal_liter', 'disalurkan_liter', 'stok_akhir_liter'],
    ];
}
