<?php

namespace App\Support\Miners;

use App\Models\Company;
use App\Models\Miners\Pekerja;
use App\Notifications\PemberitahuanMiners;
use App\Support\Waktu;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Pengingat masa berlaku — `Alert:MasaBerlaku` Safe Track (07.25 WIB).
 *
 * Safe Track mengirim SATU surel rekap ke kotak HSE (MAIL_OHSE, tembusan
 * MAIL_CC) berisi dokumen MCU, Induksi/Mine Permit, dan SIMPER yang
 * berakhir dalam 60 hari — dan mengirim ulang rekap yang sama SETIAP
 * hari selama dokumennya masih di jendela itu. Di sini penerimanya sama
 * (Penyetuju::kotakOhse per IUP, tembusan `miners.surel.tembusan`),
 * jendelanya sama (60 hari), tetapi tiap dokumen diingatkan sekali per
 * AMBANG: saat masuk 60 hari, saat masuk 30 hari, dan saat lewat. Surel
 * yang sama setiap pagi selama dua bulan diabaikan orang; tiga surel
 * yang masing-masing berarti tidak.
 *
 * Idempoten: catatannya di `mnr_pengingat` berkunci unik (jenis,
 * dokumen, tanggal berakhir, ambang). Dijalankan dua kali sehari tidak
 * mengirim apa-apa yang kedua kalinya. Dokumen yang diperpanjang
 * mendapat tanggal berakhir baru, sehingga jadwalnya mulai lagi.
 * Dokumen yang ditemukan pertama kali sudah dekat (mis. sisa 10 hari)
 * langsung masuk ambang 30, bukan disusul ambang 60 yang sudah lewat.
 */
final class PengingatMasaBerlaku
{
    /** Ambang (hari sebelum berakhir), terjauh lebih dulu; 0 = sudah lewat. */
    public const AMBANG = [60, 30, 0];

    public const LABEL_AMBANG = [60 => '≤ 60 hari', 30 => '≤ 30 hari', 0 => 'Lewat masa berlaku'];

    /** Ambang yang sedang berlaku bagi sisa hari ini, atau null bila di luar jendela. */
    public static function ambang(?int $sisa): ?int
    {
        return match (true) {
            $sisa === null              => null,
            $sisa < 0                   => 0,
            $sisa <= Zona::BATAS_KRITIS => 30,
            $sisa <= Zona::BATAS_WASPADA => 60,
            default                     => null,
        };
    }

    /**
     * Dokumen yang jatuh tempo diingatkan hari ini, dikelompokkan per IUP.
     *
     * @return Collection<int,array{iup:?int, butir:Collection<int,array<string,mixed>>}>
     */
    public function jatuhTempo(): Collection
    {
        /* Ditarik tanpa lingkup perusahaan: perintah terjadwal tidak punya
           pengguna masuk, dan pengingat ini memang untuk seluruh IUP. */
        $orang = Pekerja::withoutGlobalScopes()->with(array_merge(Keadaan::relasi(), [
            'company:id,name,parent_id', 'mcu.mcu:id,no_registrasi',
        ]))->get();

        $sudah = DB::table('mnr_pengingat')->get(['jenis', 'dokumen_id', 'berakhir', 'ambang'])
            ->map(fn ($r) => $r->jenis.'|'.$r->dokumen_id.'|'.substr((string) $r->berakhir, 0, 10).'|'.$r->ambang)
            ->flip();

        $parent = $orang->pluck('company')->filter()->mapWithKeys(fn (Company $c) => [$c->id => $c->parent_id ?: $c->id]);

        return MonitoringMiners::baris($orang)
            ->filter(fn ($b) => $b['aktif'] && $b['dokumen_id'] && $b['berakhir'] !== null)
            ->map(fn ($b) => $b + ['ambang' => self::ambang($b['sisa'])])
            ->filter(fn ($b) => $b['ambang'] !== null
                && ! isset($sudah[$b['jenis'].'|'.$b['dokumen_id'].'|'.$b['berakhir'].'|'.$b['ambang']]))
            ->groupBy(fn ($b) => (int) ($parent[$b['company_id']] ?? 0))
            ->map(fn (Collection $butir, int $iup) => ['iup' => $iup ?: null, 'butir' => $butir->sortBy('sisa')->values()])
            ->values();
    }

    /**
     * Kirim dan catat. Tanpa penerima, kelompoknya dilewati TANPA dicatat
     * — supaya terkirim begitu alamatnya diisi.
     *
     * @return array{surel:int, dokumen:int, tanpaPenerima:list<string>, gagal:list<string>}
     */
    public function kirim(bool $ujiCoba = false): array
    {
        $hasil = ['surel' => 0, 'dokumen' => 0, 'tanpaPenerima' => [], 'gagal' => []];
        $cc    = Penyetuju::pecahEmail((string) config('miners.surel.tembusan'));

        foreach ($this->jatuhTempo() as $g) {
            $nama   = $g['iup'] ? (string) Company::query()->whereKey($g['iup'])->value('name') : '(tanpa IUP)';
            $kepada = Penyetuju::kotakOhse($g['iup']);

            if ($kepada === []) {
                $hasil['tanpaPenerima'][] = $nama;
                continue;
            }
            if ($ujiCoba) {
                $hasil['surel']++;
                $hasil['dokumen'] += $g['butir']->count();
                continue;
            }

            try {
                Notification::route('mail', $kepada)->notify(new PemberitahuanMiners(
                    'Pengingat masa berlaku dokumen Miners — '.$nama,
                    $this->baris($g['butir']),
                    route('miners.kedaluwarsa', [], false),
                    'Buka Monitoring Masa Berlaku',
                    array_values(array_diff($cc, $kepada)),
                ));
            } catch (Throwable $e) {
                report($e);
                $hasil['gagal'][] = $nama.': '.mb_substr($e->getMessage(), 0, 200);
                continue;
            }

            $this->catat($g['butir'], $g['iup'], implode(', ', $kepada));
            $hasil['surel']++;
            $hasil['dokumen'] += $g['butir']->count();
        }

        return $hasil;
    }

    /** @param Collection<int,array<string,mixed>> $butir */
    private function catat(Collection $butir, ?int $iup, string $penerima): void
    {
        $kini = now();
        DB::table('mnr_pengingat')->insertOrIgnore($butir->map(fn ($b) => [
            'jenis' => $b['jenis'], 'dokumen_id' => $b['dokumen_id'], 'berakhir' => $b['berakhir'],
            'ambang' => $b['ambang'], 'company_id' => $iup, 'penerima' => $penerima,
            'dikirim_pada' => $kini, 'created_at' => $kini, 'updated_at' => $kini,
        ])->all());
    }

    /**
     * Isi surel: ringkasan per ambang lalu satu baris per dokumen.
     *
     * @param  Collection<int,array<string,mixed>>  $butir
     * @return list<string>
     */
    private function baris(Collection $butir): array
    {
        $keluar = ['Dokumen berikut berakhir dalam '.Zona::BATAS_WASPADA.' hari atau sudah lewat (per '
            .Waktu::kini()->translatedFormat('d M Y').'):'];

        foreach ($butir->groupBy('ambang')->sortKeys() as $ambang => $isi) {
            $keluar[] = '— '.self::LABEL_AMBANG[$ambang].' ('.$isi->count().' dokumen)';
            foreach ($isi as $b) {
                $sisa = $b['sisa'] < 0 ? 'lewat '.abs($b['sisa']).' hari' : $b['sisa'].' hari lagi';
                $keluar[] = sprintf('• %s — %s%s · %s · No %s · berakhir %s (%s)',
                    MonitoringMiners::JENIS[$b['jenis']], $b['nama'], $b['noid'] ? ' ('.$b['noid'].')' : '',
                    $b['perusahaan'] ?? '-', $b['nomor'] ?? '-',
                    \Illuminate\Support\Carbon::parse($b['berakhir'])->format('d-m-Y'), $sisa);
            }
        }

        $keluar[] = 'Dokumen yang sudah diperpanjang tidak lagi muncul. Tiap dokumen diingatkan sekali pada tiap ambang (60 hari, 30 hari, lewat).';

        return $keluar;
    }
}
