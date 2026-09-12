<?php

namespace App\Support\Hr;

use App\Models\Hr\{Absensi, Cuti, Lembur, PeriodeGaji, SlipGaji, Upah};
use App\Models\Miners\Pekerja;
use App\Models\User;
use App\Support\Waktu;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menjalankan satu periode gaji.
 *
 * MENARIK DARI APA YANG SUDAH TERCATAT, bukan dari isian tersendiri.
 * Roster menyebut siapa yang dijadwalkan, absensi menyebut berapa hari
 * ia benar-benar di site, lembur menyebut jam yang sudah disetujui,
 * dan upah menyebut dasarnya. Payroll yang mengetik ulang keempatnya
 * akan berselisih dengan keempatnya cepat atau lambat — dan yang
 * berselisih adalah angka di rekening pekerja.
 *
 * TUNJANGAN SITE DIHITUNG DARI HARI YANG BENAR-BENAR TERCATAT, bukan
 * dipukul rata sebulan. Pekerja FIFO berada di site empat belas hari
 * dari dua puluh satu; tunjangan bulanan yang rata membayar tujuh hari
 * yang ia habiskan di kampung halamannya — dan pada site berisi tiga
 * ratus orang, selisih itu ratusan juta setahun.
 *
 * PERIODE TERKUNCI TIDAK DAPAT DIHITUNG ULANG, dan penjagaannya ada di
 * sini — bukan pada tombolnya. Tombol dapat disembunyikan; permintaan
 * tetap dapat dikirim.
 */
final class Penggajian
{
    /**
     * Hitung seluruh slip satu periode.
     *
     * @return array{dibuat:int,diperbarui:int,bruto:float,pph21:float,neto:float}|string
     *         untaian bila ditolak
     */
    public static function hitung(PeriodeGaji $periode, ?User $oleh = null): array|string
    {
        if ($periode->terkunci()) {
            return 'Periode '.$periode->label().' sudah terkunci dan tidak dapat dihitung ulang.';
        }

        [$dari, $sampai] = self::rentang($periode);

        $pekerja = Pekerja::withoutGlobalScopes()
            ->where('company_id', $periode->company_id)
            ->where('status', 'aktif')
            ->with('jabatan')
            ->orderBy('nama')
            ->get();

        if ($pekerja->isEmpty()) {
            return 'Belum ada pekerja aktif pada perusahaan ini.';
        }

        $ada = SlipGaji::withoutGlobalScopes()
            ->where('periode_id', $periode->id)
            ->get()
            ->keyBy('pekerja_id');

        $n = ['dibuat' => 0, 'diperbarui' => 0, 'bruto' => 0.0, 'pph21' => 0.0, 'neto' => 0.0];

        DB::transaction(function () use ($periode, $pekerja, $ada, $dari, $sampai, $oleh, &$n) {
            foreach ($pekerja as $p) {
                $isi = self::slip($periode, $p, $dari, $sampai);

                $n['bruto'] += $isi['bruto'];
                $n['pph21'] += $isi['pph21'];
                $n['neto']  += $isi['neto'];

                if ($lama = $ada->get($p->id)) {
                    $lama->forceFill($isi)->save();
                    $n['diperbarui']++;

                    continue;
                }

                SlipGaji::withoutGlobalScopes()->create($isi + [
                    'company_id' => $periode->company_id,
                    'periode_id' => $periode->id,
                    'pekerja_id' => $p->id,
                ]);

                $n['dibuat']++;
            }

            $periode->forceFill([
                'status'        => 'terhitung',
                'dihitung_pada' => Waktu::kiniSimpan(),
                'dihitung_oleh' => $oleh?->id,
            ])->save();
        });

        $n['bruto'] = round($n['bruto'], 2);
        $n['pph21'] = round($n['pph21'], 2);
        $n['neto']  = round($n['neto'], 2);

        return $n;
    }

    /**
     * Kunci periode — sesudah ini angkanya menjadi dasar pembayaran.
     *
     * DITAHAN SELAMA MASIH ADA ACUAN PAJAK YANG BELUM DIVERIFIKASI.
     * Perhitungannya boleh dilihat sejak awal; yang ditahan adalah saat
     * angka berhenti menjadi pratinjau. Tabel tarif yang diketik dari
     * ingatan menghasilkan angka yang tampak sama meyakinkannya dengan
     * tabel yang sudah dicocokkan dengan naskah peraturannya — dan
     * satu-satunya yang membedakan keduanya adalah seseorang yang
     * benar-benar memeriksanya.
     *
     * @return string|null alasan penolakan, atau null bila berhasil
     */
    public static function kunci(PeriodeGaji $periode, User $oleh): ?string
    {
        if ($periode->terkunci()) return 'Periode ini sudah terkunci.';

        if ($periode->status !== 'terhitung') {
            return 'Periode ini belum dihitung; tidak ada yang dapat dikunci.';
        }

        $belum = self::acuanBelumTerverifikasi();

        if ($belum !== []) {
            return 'Acuan berikut belum diverifikasi terhadap naskah peraturannya: '
                .implode(', ', $belum).'. Periksa dan tandai lebih dahulu di halaman acuan pajak.';
        }

        if (! SlipGaji::withoutGlobalScopes()->where('periode_id', $periode->id)->exists()) {
            return 'Periode ini belum berisi satu slip pun.';
        }

        $periode->forceFill([
            'status'       => 'terkunci',
            'dikunci_pada' => Waktu::kiniSimpan(),
            'dikunci_oleh' => $oleh->id,
        ])->save();

        return null;
    }

    /** @return list<string> nama acuan yang belum diverifikasi */
    public static function acuanBelumTerverifikasi(): array
    {
        return DB::table('pay_acuan')->where('terverifikasi', false)->pluck('nama')->all();
    }

    /* ═══════════════════ satu slip ═══════════════════ */

    /**
     * Susun seluruh komponen slip satu orang.
     *
     * @return array<string,mixed>
     */
    private static function slip(PeriodeGaji $periode, Pekerja $p, Carbon $dari, Carbon $sampai): array
    {
        /* Upah yang berlaku pada AKHIR periode, bukan hari ini. Dipakai
           upah hari ini, menghitung ulang gaji bulan lalu memakai upah
           yang baru naik bulan ini. */
        $upah = Upah::pada($p, $sampai);

        $pokok      = (float) ($upah?->pokok ?? 0);
        $tetap      = (float) ($upah?->tunjangan_tetap ?? 0);
        $tidakTetap = (float) ($upah?->tunjangan_tidak_tetap ?? 0);
        $siteHarian = (float) ($upah?->tunjangan_site_harian ?? 0);

        /* Hari on-site: keadaan absensi yang berarti orangnya
           benar-benar bekerja. Dihitung dari roster, hari yang
           dijadwalkan tetapi kosong ikut terbayar tunjangannya. */
        $hariSite = (int) Absensi::withoutGlobalScopes()
            ->where('pekerja_id', $p->id)
            ->antara($dari->toDateString(), $sampai->toDateString())
            ->bekerja()
            ->count();

        $lembur = Lembur::withoutGlobalScopes()
            ->where('pekerja_id', $p->id)
            ->antara($dari->toDateString(), $sampai->toDateString())
            ->where('status', 'disetujui')
            ->get();

        $upahLembur = round((float) $lembur->sum('nilai'), 2);
        $jamLembur  = round((float) $lembur->sum('jam'), 2);

        $tunjanganSite = round($siteHarian * $hariSite, 2);

        /* Iuran dihitung atas UPAH TETAP — pokok ditambah tunjangan
           tetap — bukan atas bruto yang memuat lembur. Dihitung dari
           bruto, iuran seseorang naik turun tiap bulan mengikuti
           lemburnya. */
        $risiko = 'sangat_tinggi';

        $bpjs = Bpjs::hitung($pokok + $tetap, 0, $risiko);

        $brutoPajak = $pokok + $tetap + $tidakTetap + $tunjanganSite + $upahLembur
            + Bpjs::menambahBruto($bpjs['rincian']);

        $status = $p->status_ptkp;

        /* Iuran JHT dan JP yang dibayar PEKERJA mengurangi penghasilan
           neto pada rekonsiliasi tahunan. */
        $iuranPekerja = (float) ($bpjs['rincian']['jht']['karyawan'] ?? 0)
            + (float) ($bpjs['rincian']['jp']['karyawan'] ?? 0);

        $pajak = $periode->rekonsiliasi()
            ? self::pajakDesember($periode, $p, $brutoPajak, $iuranPekerja, $status)
            : self::pajakBulanan($brutoPajak, $status);

        $neto = round(
            $pokok + $tetap + $tidakTetap + $tunjanganSite + $upahLembur
                - $bpjs['karyawan'] - $pajak['pph21'],
            2,
        );

        return [
            'pokok'                 => $pokok,
            'tunjangan_tetap'       => $tetap,
            'tunjangan_tidak_tetap' => $tidakTetap,
            'tunjangan_site'        => $tunjanganSite,
            'hari_site'             => $hariSite,
            'lembur'                => $upahLembur,
            'lembur_jam'            => $jamLembur,
            'bpjs_perusahaan'       => $bpjs['perusahaan'],
            'bruto'                 => round($brutoPajak, 2),
            'bpjs_karyawan'         => $bpjs['karyawan'],
            'pph21'                 => $pajak['pph21'],
            'potongan_lain'         => 0,
            'neto'                  => $neto,
            'status_ptkp'           => $status,
            'ter_kategori'          => $pajak['kategori'] ?? null,
            'ter_tarif'             => $pajak['tarif'] ?? null,
            'rincian'               => [
                'bpjs'  => $bpjs['rincian'],
                'pajak' => $pajak['rincian'],
                'site'  => ['harian' => $siteHarian, 'hari' => $hariSite],
            ],
        ];
    }

    /** @return array{pph21:float,kategori:string,tarif:float,rincian:array<string,mixed>} */
    private static function pajakBulanan(float $bruto, ?string $status): array
    {
        $t = Pajak::ter($bruto, $status);

        return [
            'pph21'    => $t['pajak'],
            'kategori' => $t['kategori'],
            'tarif'    => $t['tarif'],
            'rincian'  => ['cara' => 'ter'] + $t,
        ];
    }

    /**
     * Rekonsiliasi Desember — pajak setahun dikurangi yang sudah
     * dipotong Januari sampai November.
     *
     * @return array{pph21:float,kategori:?string,tarif:?float,rincian:array<string,mixed>}
     */
    private static function pajakDesember(PeriodeGaji $periode, Pekerja $p, float $brutoDesember, float $iuranDesember, ?string $status): array
    {
        /* Yang sudah tercatat Januari sampai November pada perusahaan
           dan tahun yang sama. Diambil dari seluruh tahun, slip Desember
           yang dihitung ulang akan menghitung dirinya sendiri sebagai
           "sudah dipotong". */
        $lalu = SlipGaji::withoutGlobalScopes()
            ->where('pekerja_id', $p->id)
            ->whereHas('periode', fn ($q) => $q
                ->where('company_id', $periode->company_id)
                ->where('tahun', $periode->tahun)
                ->where('bulan', '<', $periode->bulan))
            ->get();

        $brutoSetahun = (float) $lalu->sum('bruto') + $brutoDesember;
        $sudahPotong  = (float) $lalu->sum('pph21');

        /* Iuran pekerja sebelas bulan diambil dari rinciannya, bukan
           ditaksir dari brutonya. */
        $iuranSetahun = $iuranDesember;

        foreach ($lalu as $s) {
            $r = $s->rincian['bpjs'] ?? [];

            $iuranSetahun += (float) ($r['jht']['karyawan'] ?? 0) + (float) ($r['jp']['karyawan'] ?? 0);
        }

        $hasil = Pajak::tahunan($brutoSetahun, $iuranSetahun, $status, $sudahPotong);

        return [
            'pph21'    => $hasil['pajak'],
            'kategori' => null,
            'tarif'    => null,
            'rincian'  => ['cara' => 'progresif'] + $hasil,
        ];
    }

    /** @return array{0:Carbon,1:Carbon} */
    public static function rentang(PeriodeGaji $periode): array
    {
        $dari = Carbon::create($periode->tahun, $periode->bulan, 1, 0, 0, 0, Waktu::zona());

        return [$dari, $dari->copy()->endOfMonth()->startOfDay()];
    }
}
