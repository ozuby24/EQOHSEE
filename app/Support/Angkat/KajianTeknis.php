<?php

namespace App\Support\Angkat;

use App\Models\Angkat\{Alat, Gear, Kajian, Rencana};
use Illuminate\Support\Collection;

/**
 * Kajian teknis pertambangan 6 pilar untuk satu rencana pengangkatan
 * (Kepmen ESDM 1827 K/30/MEM/2018 lampiran pesawat angkat & angkut).
 *
 * Butir yang bisa dibuktikan dari angka — tekanan tanah, SUKET, utilisasi,
 * sudut sling, angin, jarak SUTT, nama & sertifikat personel — dinilai
 * sistem setiap kali rencana atau registernya berubah. Butir yang hanya
 * bisa dibuktikan di lapangan — JSA, toolbox meeting, koordinasi
 * peledakan — dinilai peninjau, dan yang belum dinilai dihitung nol:
 * kajian tidak pernah "memenuhi" karena lupa diisi.
 */
final class KajianTeknis
{
    /** Butir otomatis yang, bila tidak terpenuhi, membuat kajian TIDAK MEMENUHI. */
    public const PENGHENTI = ['P1-1', 'P2-1', 'P2-2', 'P2-3', 'P3-1', 'P3-3', 'P3-4', 'P3-5', 'P4-1', 'P4-2', 'P5-1'];

    private const NILAI = ['penuh' => 1.0, 'sebagian' => 0.5, 'tidak' => 0.0];

    /**
     * @param  Collection<int,Gear>  $gear
     * @param  array<string,string>  $manual  kode butir → penuh|sebagian|tidak
     */
    public static function nilai(array $hasil, array $orang, ?Alat $alat, Collection $gear, array $manual, ?\DateTimeInterface $jadwal = null): array
    {
        $rig = $hasil['rigging'];
        $tetap = in_array($hasil['alat']['kategori'], ['overhead', 'hoist'], true);
        $hari = $jadwal ? \Illuminate\Support\Carbon::instance($jadwal) : now();
        $suketOk = ! $alat || ($alat->suket_berlaku && $alat->suket_berlaku->endOfDay()->gte($hari));
        $gearTidak = $gear->reject(fn (Gear $g) => $g->bolehDipakai() && (! $g->uji_berikut || $g->uji_berikut->endOfDay()->gte($hari)));
        $sudut = (float) $rig['sudut_efektif'];
        $util = (float) $hasil['utilisasi_pct'];

        $oto = [
            'P1-1' => [$hasil['cek']['tanah'] ? (! $tetap && $hasil['tekanan_tanah'] > $hasil['izin_tanah'] * 0.85 ? 'sebagian' : 'penuh') : 'tidak',
                $tetap ? 'Crane terpasang tetap — tanpa outrigger' : sprintf('%s / %s kN/m²', self::f($hasil['tekanan_tanah']), self::f($hasil['izin_tanah']))],
            'P1-2' => [$hasil['cek']['miring'] ? 'penuh' : 'tidak', $tetap ? 'Tidak berlaku' : 'Kemiringan '.self::f($hasil['masukan']['kemiringan_pct']).'%'],
            'P1-3' => [$tetap || (float) $hasil['masukan']['luas_mat_m2'] >= $hasil['mat_min_m2'] ? 'penuh' : 'tidak',
                $tetap ? 'Tidak berlaku' : sprintf('Mat %s m² · minimum %s m²', self::f($hasil['masukan']['luas_mat_m2']), self::f($hasil['mat_min_m2']))],
            'P2-1' => [$alat ? ($suketOk ? 'penuh' : 'tidak') : 'sebagian',
                $alat ? ($alat->suket_berlaku ? 'SUKET berlaku s.d. '.$alat->suket_berlaku->translatedFormat('d M Y') : 'Belum ada SUKET') : 'Crane belum dipilih dari register'],
            'P2-2' => [$alat ? ($alat->status === 'karantina' ? 'tidak' : 'penuh') : 'sebagian', $alat ? $alat->kode.' · '.$alat->nama : 'Crane belum dipilih dari register'],
            'P2-3' => [$gear->isEmpty() ? 'sebagian' : ($gearTidak->isEmpty() ? 'penuh' : 'tidak'),
                $gear->isEmpty() ? 'Alat bantu belum dipilih dari register'
                    : ($gearTidak->isEmpty() ? $gear->count().' alat bantu layak & berlaku' : 'Tidak layak: '.$gearTidak->pluck('kode')->implode(', '))],
            'P3-1' => [$hasil['cek']['crane'] ? ($util > 75 ? 'sebagian' : 'penuh') : 'tidak', sprintf('Utilisasi %s%% · batas %s%%', self::f($util), self::f($hasil['batas_util']))],
            'P3-2' => [$sudut >= 60 ? 'penuh' : ($sudut >= 45 ? 'sebagian' : 'tidak'), 'Sudut efektif '.self::f($sudut).'°'],
            'P3-3' => [max($rig['util_sling'], $rig['util_shackle']) <= 100 ? 'penuh' : 'tidak',
                sprintf('Sling %s%% · shackle %s%% WLL', self::f($rig['util_sling']), self::f($rig['util_shackle']))],
            'P3-4' => [$hasil['cek']['ruang'] ? 'penuh' : 'tidak', sprintf('Ujung %s m · perlu %s m', self::f($hasil['tinggi_ujung_m']), self::f($hasil['tinggi_perlu_m']))],
            'P3-5' => [$hasil['putusan'] === 'nogo' ? 'tidak' : ($hasil['putusan'] === 'bersyarat' ? 'sebagian' : 'penuh'), 'Skor simulasi '.$hasil['skor']],
            'P4-1' => [$hasil['cek']['angin'] ? 'penuh' : 'tidak', sprintf('%s km/jam · batas %s', self::f($hasil['masukan']['angin_kmh']), self::f($hasil['alat']['angin_maks']))],
            'P4-2' => [$hasil['cek']['sutt'] ? ($hasil['masukan']['jarak_sutt_m'] < 10 ? 'sebagian' : 'penuh') : 'tidak', 'Jarak '.self::f($hasil['masukan']['jarak_sutt_m']).' m'],
            'P5-1' => [filled($orang['operator_nama'] ?? null) && filled($orang['operator_sio'] ?? null) ? 'penuh' : 'tidak',
                trim(($orang['operator_nama'] ?? '—').' · SIO '.($orang['operator_sio'] ?? '—'))],
            'P5-2' => [filled($orang['rigger_nama'] ?? null) && filled($orang['rigger_sertifikat'] ?? null) ? 'penuh' : (filled($orang['rigger_nama'] ?? null) ? 'sebagian' : 'tidak'),
                trim(($orang['rigger_nama'] ?? '—').' · '.($orang['rigger_sertifikat'] ?? 'tanpa sertifikat'))],
            'P5-3' => [filled($orang['signalman_nama'] ?? null) ? 'penuh' : 'tidak', $orang['signalman_nama'] ?? 'Belum ditunjuk'],
            'P5-4' => [filled($orang['pengawas_nama'] ?? null) ? 'penuh' : 'tidak', $orang['pengawas_nama'] ?? 'Belum ditunjuk'],
        ];

        $pilar = [];
        $penghenti = [];
        $belum = 0;
        foreach (Katalog::PILAR as $kode => $p) {
            $butir = [];
            $jumlah = 0.0;
            foreach ($p['butir'] as $b) {
                if ($b['oto']) {
                    [$status, $bukti] = $oto[$b['kode']];
                } else {
                    $status = $manual[$b['kode']] ?? 'belum';
                    $bukti = $status === 'belum' ? 'Belum dinilai peninjau' : 'Dinilai peninjau';
                    if ($status === 'belum') $belum++;
                }
                if ($status === 'tidak' && in_array($b['kode'], self::PENGHENTI, true)) $penghenti[] = $b['kode'].' '.$b['label'];
                $jumlah += self::NILAI[$status] ?? 0.0;
                $butir[] = $b + ['status' => $status, 'bukti' => $bukti];
            }
            $pilar[] = ['kode' => $kode, 'nama' => $p['nama'], 'skor' => round($jumlah / count($p['butir']) * 100, 1), 'butir' => $butir];
        }

        $skor = round(collect($pilar)->avg('skor'), 1);
        $adaTidak = collect($pilar)->flatMap(fn ($p) => $p['butir'])->contains('status', 'tidak');
        $putusan = $penghenti ? 'tidak_memenuhi' : ($skor >= 90 && ! $adaTidak && ! $belum ? 'memenuhi' : 'bersyarat');

        $terlemah = collect($pilar)->sortBy('skor')->first();
        $kesimpulan = match ($putusan) {
            'tidak_memenuhi' => 'Tidak memenuhi: '.implode('; ', array_slice($penghenti, 0, 3)).'. Rencana tidak dapat diajukan sebelum butir ini diperbaiki.',
            'memenuhi' => 'Memenuhi seluruh pilar (skor '.self::f($skor).'). Pengangkatan layak dilaksanakan sesuai rencana dan kendali wajib.',
            default => 'Bersyarat (skor '.self::f($skor).')'.($belum ? ', '.$belum.' butir belum dinilai' : '').'. Pilar terlemah: '.$terlemah['nama'].' ('.self::f($terlemah['skor']).').',
        };

        return ['pilar' => $pilar, 'skor' => $skor, 'putusan' => $putusan, 'kesimpulan' => $kesimpulan, 'penghenti' => $penghenti, 'belum' => $belum];
    }

    /** Membuat/memperbarui kajian satu rencana dari keadaan register saat ini. */
    public static function kaji(Rencana $r, ?array $manual = null, ?string $peninjau = null): Kajian
    {
        $k = Kajian::withoutGlobalScopes()->firstOrNew(['rencana_id' => $r->id]);
        $manual ??= $k->manual ?? [];
        $alat = $r->alat_id ? Alat::withoutGlobalScopes()->find($r->alat_id) : null;
        $gear = Gear::withoutGlobalScopes()->whereIn('id', $r->gear ?? [])->get();
        $orang = $r->only(['operator_nama', 'operator_sio', 'rigger_nama', 'rigger_sertifikat', 'signalman_nama', 'pengawas_nama']);
        $n = self::nilai($r->hasil, $orang, $alat, $gear, $manual, $r->jadwal_at);

        $k->fill([
            'company_id' => $r->company_id, 'kode' => $k->kode ?: 'KTP-'.$r->kode,
            'pilar' => $n['pilar'], 'manual' => $manual, 'skor' => $n['skor'], 'putusan' => $n['putusan'],
            'kesimpulan' => $n['kesimpulan'], 'peninjau' => $peninjau ?? $k->peninjau,
            'ditinjau_at' => $peninjau ? now() : $k->ditinjau_at,
        ])->save();

        return $k;
    }

    private static function f(mixed $x): string
    {
        return rtrim(rtrim(number_format((float) $x, 1, ',', '.'), '0'), ',');
    }
}
