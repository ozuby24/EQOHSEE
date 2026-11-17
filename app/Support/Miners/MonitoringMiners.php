<?php

namespace App\Support\Miners;

use App\Models\Miners\Induksi;
use App\Models\Miners\InduksiOrang;
use App\Models\Miners\Mcu;
use App\Models\Miners\McuOrang;
use App\Models\Miners\Pekerja;
use App\Models\Miners\Permit;
use App\Models\Miners\Simper;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Monitoring Masa Berlaku — MasaBerlakuController@monitoring dan
 * MasaBerlakuService Safe Track, di atas tabel mnr_*.
 *
 * Satu baris per DOKUMEN, dengan zona dari App\Support\Miners\Zona —
 * ambang yang sama dengan kartu zona di Manpower, Mine Permit, dan
 * SIMPER. Layar monitoring, unduhan Excel-nya, dan pengingat surel
 * membaca baris yang sama, supaya angka di layar, di berkas, dan di
 * kotak masuk HSE tidak pernah berselisih.
 *
 *   · MCU     — MCU TERAKHIR yang aktif per pekerja; pekerja tanpa MCU
 *               tercatat "belum diisi" (sama dengan daftar Manpower).
 *               Zona dihitung atas karyawan AKTIF saja (Safe Track:
 *               "Angka MCU hanya menghitung karyawan yang masih aktif").
 *   · Induksi — induksi lulus terakhir per pekerja.
 *   · Mine Permit / SIMPER — setiap kartu, tanggal EFEKTIF-nya
 *               (habisEfektif), zona atas kartu yang aktif.
 *
 * Dimuat SEKALI dengan Keadaan::relasi() — tidak ada kueri per baris.
 */
final class MonitoringMiners
{
    public const JENIS = [
        'mcu'     => 'MCU',
        'induksi' => 'Induksi',
        'permit'  => 'Mine Permit',
        'simper'  => 'SIMPER',
    ];

    /** Keterangan di bawah judul tiap kartu status — Safe Track. */
    public const KETERANGAN = [
        'mcu'     => 'karyawan aktif',
        'induksi' => 'sertifikat induksi lulus',
        'permit'  => 'mengikuti MCU',
        'simper'  => 'mengikuti SIM kepolisian',
    ];

    /** @return Collection<int,Pekerja> */
    public static function muat(): Collection
    {
        return Pekerja::with(array_merge(Keadaan::relasi(), [
            'company:id,name', 'mcu.mcu:id,no_registrasi',
        ]))->orderBy('nama')->get();
    }

    /**
     * @param  Collection<int,Pekerja>|null  $orang
     * @return Collection<int,array<string,mixed>>
     */
    public static function baris(?Collection $orang = null): Collection
    {
        $orang ??= self::muat();
        $keluar = [];

        foreach ($orang as $p) {
            /** @var McuOrang|null $m */
            $m = $p->mcu->first(fn (McuOrang $x) => (bool) $x->aktif);
            $keluar[] = self::satu($p, 'mcu', $m?->id, $m?->mcu?->no_registrasi ?? $m?->no_id,
                $m?->berlaku_sampai, $p->aktif(), $m ? route('miners.mcu.rincian', $m->mcu_id) : null);

            /** @var InduksiOrang|null $i */
            if ($i = $p->induksi->first(fn (InduksiOrang $x) => $x->status === 'lulus')) {
                $keluar[] = self::satu($p, 'induksi', $i->id, $i->induksi?->no_registrasi,
                    $i->berlaku_sampai, $p->aktif(), route('miners.induksi.rincian', $i->induksi_id));
            }

            foreach ($p->permit as $k) {
                /** @var Permit $k */
                $keluar[] = self::satu($p, 'permit', $k->id, $k->no_registrasi,
                    $k->habisEfektif(), (bool) $k->aktif, route('miners.permit.rincian', $k->id));
            }

            foreach ($p->simper as $s) {
                /** @var Simper $s */
                $keluar[] = self::satu($p, 'simper', $s->id, $s->no_simper,
                    $s->habisEfektif(), (bool) $s->aktif, route('miners.simper.rincian', $s->id));
            }
        }

        return collect($keluar);
    }

    /**
     * Baris yang boleh dilihat pengguna ini.
     *
     * Pengelola (admin, HSE IUP): seluruhnya. Pengguna lain: per modul,
     * hanya baris yang dokumennya lolos AksesMiners::saringDokumen —
     * pengajunya, pendaftar pekerjanya, penyetuju langkahnya, dan untuk
     * MCU paramedis. Baris "belum ada dokumen" (MCU kosong) hanya bagi
     * yang boleh melihat pekerjanya (saringPekerja). Ringkasan, rekap
     * per perusahaan, dan hitungan zona dihitung atas hasil saringan
     * ini, sehingga angkanya tidak menyebut lebih dari yang terlihat.
     *
     * @param  Collection<int,array<string,mixed>>  $baris
     * @return Collection<int,array<string,mixed>>
     */
    public static function terlihat(Collection $baris, ?User $u): Collection
    {
        if (AksesMiners::kelola($u)) return $baris;

        $id = fn ($q) => $q->pluck('id')->flip();

        $boleh = [
            'mcu'     => $id(McuOrang::withoutGlobalScopes()
                ->whereIn('mcu_id', AksesMiners::saringDokumen(Mcu::query(), $u, 'mcu')->select('id'))),
            'induksi' => $id(InduksiOrang::withoutGlobalScopes()
                ->whereIn('induksi_id', AksesMiners::saringDokumen(Induksi::query(), $u, 'induksi')->select('id'))),
            'permit'  => $id(AksesMiners::saringDokumen(Permit::query(), $u, 'permit')),
            'simper'  => $id(AksesMiners::saringDokumen(Simper::query(), $u, 'simper')),
        ];
        $pekerja = $id(AksesMiners::saringPekerja(Pekerja::query(), $u));

        return $baris->filter(fn (array $b) => $b['dokumen_id'] === null
            ? isset($pekerja[$b['pekerja_id']])
            : isset($boleh[$b['jenis']][$b['dokumen_id']]))->values();
    }

    /** @return array<string,mixed> */
    private static function satu(Pekerja $p, string $jenis, ?int $id, ?string $nomor, mixed $habis, bool $aktif, ?string $tautan): array
    {
        $sisa = Zona::sisa($habis);

        return [
            'kunci'      => $jenis.'-'.($id ?? 'p'.$p->id),
            'jenis'      => $jenis,
            'dokumen_id' => $id,
            'pekerja_id' => $p->id,
            'nama'       => $p->nama,
            'noid'       => $p->no_induk ?: $p->nik,
            'company_id' => $p->company_id,
            'perusahaan' => $p->company?->name,
            'nomor'      => $nomor,
            'berakhir'   => $habis?->toDateString(),
            'sisa'       => $sisa,
            'zona'       => Zona::dariSisa($sisa),
            'aktif'      => $aktif,
            'tautan'     => $tautan,
        ];
    }

    /**
     * Ringkasan zona per jenis — kartu "Status Masa Berlaku". Dokumen
     * nonaktif tidak masuk zona mana pun (Safe Track).
     *
     * @param  Collection<int,array<string,mixed>>  $baris
     * @return array<string,array<string,int>>
     */
    public static function ringkas(Collection $baris): array
    {
        $keluar = [];
        foreach (array_keys(self::JENIS) as $j) {
            $keluar[$j] = Zona::ringkas($baris->where('jenis', $j)->where('aktif', true)->pluck('zona'));
        }

        return $keluar;
    }

    /**
     * Rekap per perusahaan per jenis. MCU membawa kolom aktif/nonaktif
     * (Manpower MCU per Perusahaan); zonanya tetap atas yang aktif.
     *
     * @param  Collection<int,array<string,mixed>>  $baris
     * @return array<string,list<array<string,mixed>>>
     */
    public static function rekap(Collection $baris): array
    {
        $keluar = [];
        foreach (array_keys(self::JENIS) as $j) {
            $isi = $baris->where('jenis', $j);
            if ($j === 'mcu') {
                $rekap = Zona::rekapPerusahaan($isi->all(), true);
                /* Kolom zona hanya menghitung yang aktif; yang nonaktif
                   terbaca di kolom NON-AKTIF saja. */
                $nonaktif = $isi->where('aktif', false)->groupBy(fn ($b) => $b['perusahaan'] ?: '(tanpa perusahaan)');
                foreach ($rekap as &$r) {
                    foreach ($nonaktif[$r['perusahaan']] ?? [] as $b) $r[$b['zona']]--;
                }
                unset($r);
                $keluar[$j] = $rekap;
            } else {
                $keluar[$j] = Zona::rekapPerusahaan($isi->where('aktif', true)->all());
            }
        }

        return $keluar;
    }

    /**
     * "Perlu Tindak Lanjut" — sudah lewat atau kurang dari 30 hari,
     * paling mendesak lebih dulu (palingMendesak() Safe Track).
     *
     * @param  Collection<int,array<string,mixed>>  $baris
     * @return Collection<int,array<string,mixed>>
     */
    public static function mendesak(Collection $baris): Collection
    {
        return $baris->where('aktif', true)
            ->whereIn('zona', ['expired', 'kritis'])
            ->sortBy(fn ($b) => sprintf('%012d|%s', $b['sisa'] + 1_000_000, mb_strtolower((string) $b['nama'])))
            ->values();
    }
}
