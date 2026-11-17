<?php

namespace App\Support\Hr;

use App\Models\Hr\{Cuti, JenisCuti, Roster, SaldoCuti};
use App\Models\Miners\Pekerja;
use App\Support\Waktu;
use Illuminate\Support\Carbon;

/**
 * Aturan cuti — berapa hari terpotong, berapa sisanya, dan sejak kapan
 * haknya timbul.
 *
 * TIGA HAL DI SINI YANG TIDAK DAPAT DIKERJAKAN SPREADSHEET, dan
 * ketiganya berasal dari roster yang memang tersimpan di aplikasi yang
 * sama:
 *
 *   1. HARI YANG TERPOTONG DIHITUNG DARI ROSTER, bukan dari kalender.
 *      Pada pola 14:7, cuti tiga hari yang jatuh pada periode off-site
 *      tidak memakan satu pun hari kerja. Dihitung dari kalender,
 *      seorang pekerja FIFO kehilangan seluruh dua belas hari cuti
 *      tahunannya dalam satu periode libur yang memang haknya.
 *
 *   2. PENGAJUAN YANG MASIH MENUNGGU IKUT DIPERHITUNGKAN. Saldo
 *      dipotong saat disetujui — tetapi dua pengajuan yang sama-sama
 *      menunggu dapat disetujui berdua dan saldonya menjadi minus
 *      tanpa satu galat pun.
 *
 *   3. HAK TIMBUL SESUDAH DUA BELAS BULAN, bukan sejak hari pertama
 *      (UU 13/2003 pasal 79 ayat 2 huruf c). Diberikan sejak hari
 *      pertama, kesalahannya baru terlihat ketika seseorang resign
 *      dengan sisa cuti yang harus diuangkan.
 */
final class KebijakanCuti
{
    /** Bulan masa kerja sebelum hak cuti tahunan timbul. */
    public const BULAN_HAK_TAHUNAN = 12;

    /**
     * Hari kerja yang terpotong oleh sebuah rentang cuti.
     *
     * ROSTER YANG MENENTUKAN, bukan kalender. Hari yang rosternya
     * menyatakan `kerja` terpotong; hari libur, cuti, sakit, dan izin
     * yang sudah tercatat tidak.
     *
     * HARI TANPA BARIS ROSTER IKUT TERHITUNG, dan itu keputusan yang
     * disengaja. Roster bulan depan belum tentu sudah disusun, dan
     * memperlakukan hari yang belum terjadwal sebagai libur berarti
     * cuti yang diajukan jauh-jauh hari tidak memotong saldo sama
     * sekali — seluruh cuti tahunan dapat diambil dengan mengajukannya
     * sebelum rosternya dibuat.
     *
     * @return array{hari:int,kalender:int,tanggal:list<string>}
     */
    public static function hariTerpotong(Pekerja $p, Carbon $mulai, Carbon $selesai): array
    {
        $mulai   = $mulai->copy()->startOfDay();
        $selesai = $selesai->copy()->startOfDay();

        if ($selesai->lt($mulai)) return ['hari' => 0, 'kalender' => 0, 'tanggal' => []];

        $roster = Roster::withoutGlobalScopes()
            ->where('pekerja_id', $p->id)
            ->antara($mulai->toDateString(), $selesai->toDateString())
            ->get()
            ->keyBy(fn (Roster $r) => $r->tanggal->toDateString());

        $tanggal  = [];
        $kalender = 0;

        for ($t = $mulai->copy(); $t->lte($selesai); $t->addDay()) {
            $kalender++;

            $hari = $t->toDateString();
            $r    = $roster->get($hari);

            if ($r && ! $r->bekerja()) continue;

            $tanggal[] = $hari;
        }

        return ['hari' => count($tanggal), 'kalender' => $kalender, 'tanggal' => $tanggal];
    }

    /**
     * Hak cuti tahunan seseorang pada sebuah tahun.
     *
     * NOL SEBELUM DUA BELAS BULAN BEKERJA. Pasal 79 menyebut haknya
     * timbul sesudah bekerja dua belas bulan terus-menerus; yang belum
     * genap belum punya hak yang dapat dipotong.
     *
     * TANPA TANGGAL MASUK, HAKNYA JUGA NOL — bukan penuh. Data lama
     * yang tanggal masuknya kosong akan memberi hak penuh kepada
     * siapa pun yang barisnya belum lengkap, dan kesalahan itu baru
     * terlihat pada pembayaran sisa cuti saat seseorang berhenti.
     */
    public static function hakTahunan(Pekerja $p, JenisCuti $jenis, int $tahun): int
    {
        if (! $jenis->akrual) return (int) ($jenis->hari ?? 0);

        $masuk = $p->tanggal_masuk;

        if (! $masuk) return 0;

        /* Diukur sampai AKHIR tahun yang ditanyakan: seseorang yang
           genap dua belas bulan pada bulan Oktober berhak atas cuti
           tahun itu juga. */
        $akhir = Carbon::create($tahun, 12, 31, 0, 0, 0, Waktu::zona());

        $masuk = Carbon::parse($masuk->format('Y-m-d'), Waktu::zona());

        if ($masuk->gt($akhir)) return 0;

        $bulan = (int) floor($masuk->diffInMonths($akhir));

        return $bulan >= self::BULAN_HAK_TAHUNAN ? (int) ($jenis->hari ?? 0) : 0;
    }

    /**
     * Pastikan baris saldo ada, lalu pulangkan.
     *
     * Haknya diisi HANYA saat barisnya lahir. Diperbarui tiap kali
     * dibaca, hak tahun lalu ikut berubah begitu kebijakannya diubah —
     * dan angka yang menjadi dasar pembayaran sisa cuti tidak dapat
     * ditunjukkan lagi asalnya.
     */
    public static function saldo(Pekerja $p, JenisCuti $jenis, int $tahun): SaldoCuti
    {
        $ada = SaldoCuti::withoutGlobalScopes()
            ->where('pekerja_id', $p->id)
            ->where('jenis_cuti_id', $jenis->id)
            ->where('tahun', $tahun)
            ->first();

        if ($ada) return $ada;

        return SaldoCuti::withoutGlobalScopes()->create([
            'company_id'    => $p->company_id,
            'pekerja_id'    => $p->id,
            'jenis_cuti_id' => $jenis->id,
            'tahun'         => $tahun,
            'hak'           => self::hakTahunan($p, $jenis, $tahun),
            'carry_over'    => self::carryOver($p, $jenis, $tahun),
        ]);
    }

    /**
     * Sisa tahun lalu yang boleh dibawa, dibatasi kebijakannya.
     *
     * Dihitung dari baris tahun lalu yang SUDAH ADA saja. Dihitung
     * mundur tanpa batas, membuka saldo tahun 2030 akan melahirkan
     * baris untuk setiap tahun sejak orangnya masuk kerja.
     */
    private static function carryOver(Pekerja $p, JenisCuti $jenis, int $tahun): int
    {
        $maks = (int) ($jenis->carry_over_maks ?? 0);

        if ($maks <= 0) return 0;

        $lalu = SaldoCuti::withoutGlobalScopes()
            ->where('pekerja_id', $p->id)
            ->where('jenis_cuti_id', $jenis->id)
            ->where('tahun', $tahun - 1)
            ->first();

        if (! $lalu) return 0;

        return max(0, min($maks, $lalu->sisa()));
    }

    /**
     * Ringkasan saldo seseorang, termasuk yang masih menunggu.
     *
     * @return array{hak:int,carry_over:int,terpakai:int,tertunda:int,sisa:int,tersedia:int}
     */
    public static function ringkas(Pekerja $p, JenisCuti $jenis, int $tahun): array
    {
        $saldo = self::saldo($p, $jenis, $tahun);

        /* Pengajuan yang masih menunggu, dibatasi pada tahun yang sama.
           Diambil seluruhnya, cuti Desember yang menyeberang ke Januari
           akan terhitung dua kali — sekali di tiap tahun. */
        $tertunda = (int) Cuti::withoutGlobalScopes()
            ->where('pekerja_id', $p->id)
            ->where('jenis_cuti_id', $jenis->id)
            ->menunggu()
            ->whereYear('mulai', $tahun)
            ->sum('hari');

        $sisa = $saldo->sisa();

        return [
            'hak'        => $saldo->hak,
            'carry_over' => $saldo->carry_over,
            'terpakai'   => $saldo->terpakai,
            'tertunda'   => $tertunda,
            'sisa'       => $sisa,

            /* Yang benar-benar dapat diajukan lagi: sisa dikurangi yang
               sudah antre. Inilah angka yang diperiksa, bukan `sisa`. */
            'tersedia'   => $sisa - $tertunda,
        ];
    }

    /**
     * Bolehkah pengajuan ini diajukan.
     *
     * @return string|null alasan penolakan, atau null bila boleh
     */
    public static function periksa(Pekerja $p, JenisCuti $jenis, Carbon $mulai, Carbon $selesai, ?int $abaikanId = null): ?string
    {
        if ($selesai->lt($mulai)) return 'Tanggal selesai mendahului tanggal mulai.';

        /* Tumpang tindih dengan pengajuan lain yang masih hidup.
           Dibiarkan, satu hari dapat dipotong dua kali dari saldo dan
           dua baris roster berebut hari yang sama. */
        $bentrok = Cuti::withoutGlobalScopes()
            ->where('pekerja_id', $p->id)
            ->whereIn('status', ['menunggu', 'disetujui'])
            ->when($abaikanId, fn ($q) => $q->where('id', '<>', $abaikanId))
            ->bersinggungan($mulai->toDateString(), $selesai->toDateString())
            ->first();

        if ($bentrok) {
            return 'Bertumpang tindih dengan pengajuan '.$bentrok->mulai->toDateString()
                .' – '.$bentrok->selesai->toDateString().' yang '.Cuti::STATUS[$bentrok->status].'.';
        }

        $n = self::hariTerpotong($p, $mulai, $selesai);

        if ($n['hari'] === 0) {
            return 'Tidak ada satu pun hari kerja pada rentang itu — seluruhnya sudah libur menurut roster.';
        }

        if (! $jenis->potong_saldo) return null;

        $sisa = self::ringkas($p, $jenis, (int) $mulai->format('Y'))['tersedia'];

        if ($n['hari'] > $sisa) {
            return 'Saldo tidak cukup: butuh '.$n['hari'].' hari, tersedia '.$sisa.' hari'
                .' (sudah memperhitungkan pengajuan yang masih menunggu).';
        }

        return null;
    }
}
