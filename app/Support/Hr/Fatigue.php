<?php

namespace App\Support\Hr;

use App\Models\Hr\Roster;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Batas waktu kerja, dan pelanggaran yang ditemukannya.
 *
 * ACUAN — Kepmenakertrans KEP.234/MEN/2003 tentang waktu kerja dan
 * istirahat pada sektor usaha energi dan sumber daya mineral pada
 * daerah tertentu. Empat batasnya:
 *
 *   · paling lama 11 jam sehari
 *   · paling banyak 154 jam dalam 14 hari
 *   · paling lama 14 hari kerja berturut-turut
 *   · sesudahnya istirahat sekurang-kurangnya 5 hari
 *
 * ditambah batas umum 40 jam seminggu menurut UU 13/2003 jo. UU 6/2023
 * — yang justru DILAMPAUI pola 14:7 berjam 11, dan karena itulah
 * sektor ESDM punya pengecualiannya sendiri. Batas mingguan tidak
 * dipakai memblokir; ia hanya dicatat, sebab memblokirnya berarti
 * menolak seluruh pola roster yang sah.
 *
 * MEMERIKSA JENDELA, BUKAN SATU HARI. Tiga dari empat batas di atas
 * hanya dapat dilanggar oleh RANGKAIAN hari, bukan oleh satu hari mana
 * pun. Pemeriksaan yang menilai tiap baris sendirian akan meloloskan
 * roster yang tiap harinya sah tetapi rangkaiannya dua puluh satu hari
 * berturut-turut — dan itu bentuk pelanggaran yang paling sering
 * terjadi, sebab ia lahir dari menyambung dua periode yang
 * masing-masing benar.
 *
 * TIDAK MEMBLOKIR SENDIRI. Kelas ini menemukan dan menjelaskan; yang
 * memutuskan menolak atau meneruskan adalah pemanggilnya. Pemisahan itu
 * disengaja — sebagian pelanggaran memang harus dapat diterbitkan
 * dengan persetujuan, dan pemeriksa yang langsung melempar tidak
 * meninggalkan ruang bagi keputusan itu.
 */
final class Fatigue
{
    public const MAKS_JAM_HARI     = 11;
    public const MAKS_JAM_14_HARI  = 154;
    public const MAKS_HARI_BERUNTUN = 14;
    public const MIN_HARI_ISTIRAHAT = 5;

    /**
     * Panjang periode kerja yang membuat aturan istirahat 5 hari berlaku.
     *
     * ATURAN ITU MELEKAT PADA REZIM PERIODE PANJANG, bukan pada setiap
     * jeda. Kepmenakertrans 234/2003 mengatur waktu kerja sektor ESDM
     * di DAERAH TERTENTU — pola yang memusatkan hari kerja lalu
     * memulangkan pekerjanya — dan istirahat lima hari adalah imbangan
     * bagi periode kerja terpusat itu.
     *
     * Diterapkan ke tiap jeda, pola kantor 5:2 ikut terjaring: dua hari
     * akhir pekan lebih pendek daripada lima, sehingga JADWAL KANTOR
     * BIASA DITOLAK SEBAGAI PELANGGARAN. Itu bukan pengetatan melainkan
     * kekeliruan — dan yang menerimanya adalah orang yang mencoba
     * menerbitkan roster staf administrasi.
     *
     * Enam hari dipilih sebagai batasnya karena itu batas umum
     * UU 13/2003: enam hari kerja lalu sehari istirahat mingguan. Yang
     * melampauinya sudah bukan pola mingguan biasa, dan di situlah
     * rezim periode panjang dimulai.
     */
    public const AMBANG_PERIODE_PANJANG = 6;

    /** Batas umum UU 13/2003 — dicatat, tidak memblokir. */
    public const MAKS_JAM_MINGGU = 40;

    public const PELANGGARAN = [
        'jam_harian'   => 'Melebihi 11 jam sehari',
        'jam_14_hari'  => 'Melebihi 154 jam dalam 14 hari',
        'hari_beruntun'=> 'Lebih dari 14 hari kerja berturut-turut',
        'istirahat'    => 'Istirahat kurang dari 5 hari sesudah periode kerja panjang',
        'jam_minggu'   => 'Melebihi 40 jam seminggu',
    ];

    /** Yang MENOLAK penerbitan; sisanya hanya diberitahukan. */
    public const MENOLAK = ['jam_harian', 'jam_14_hari', 'hari_beruntun', 'istirahat'];

    /**
     * Periksa rangkaian roster satu orang.
     *
     * @param  Collection<int,Roster>|iterable<Roster>  $baris  seluruh
     *         baris orang itu, terurut menaik menurut tanggal
     * @return list<array{jenis:string,label:string,tanggal:string,nilai:int,batas:int,menolak:bool}>
     */
    public static function periksa(iterable $baris): array
    {
        $urut = collect($baris)
            ->filter(fn (Roster $r) => $r->tanggal !== null)
            ->sortBy(fn (Roster $r) => $r->tanggal->toDateString())
            ->values();

        $temuan = [];

        foreach ([
            self::jamHarian($urut),
            self::jamDuaMingguan($urut),
            self::beruntunDanIstirahat($urut),
            self::jamMingguan($urut),
        ] as $kelompok) {
            foreach ($kelompok as $t) $temuan[] = $t;
        }

        usort($temuan, fn (array $a, array $b) => $a['tanggal'] <=> $b['tanggal']);

        return $temuan;
    }

    /** Ada pelanggaran yang menolak penerbitan. */
    public static function ditolak(array $temuan): bool
    {
        foreach ($temuan as $t) {
            if ($t['menolak']) return true;
        }

        return false;
    }

    /* ═══════════════════ tiap batas ═══════════════════ */

    /** @return list<array<string,mixed>> */
    private static function jamHarian(Collection $urut): array
    {
        $temuan = [];

        foreach ($urut as $r) {
            if (! $r->bekerja() || $r->jam <= self::MAKS_JAM_HARI) continue;

            $temuan[] = self::temuan('jam_harian', $r->tanggal, $r->jam, self::MAKS_JAM_HARI);
        }

        return $temuan;
    }

    /**
     * Jendela geser 14 hari.
     *
     * DIGESER PER HARI, bukan dipotong per dua minggu kalender.
     * Dipotong, rangkaian yang melanggar tepat di perbatasan dua
     * potongan tidak pernah terlihat — dan perbatasan itu jatuh di
     * tengah tiap periode kerja 14 hari, tempat pelanggarannya justru
     * paling mungkin.
     *
     * @return list<array<string,mixed>>
     */
    private static function jamDuaMingguan(Collection $urut): array
    {
        if ($urut->isEmpty()) return [];

        $jam = [];
        foreach ($urut as $r) {
            $jam[$r->tanggal->toDateString()] = ($jam[$r->tanggal->toDateString()] ?? 0)
                + ($r->bekerja() ? $r->jam : 0);
        }

        $awal   = $urut->first()->tanggal->copy()->startOfDay();
        $akhir  = $urut->last()->tanggal->copy()->startOfDay();
        $temuan = [];

        /* Satu temuan per rangkaian, bukan satu per hari. Digeser per
           hari tanpa penjagaan ini, satu periode kerja yang melanggar
           menghasilkan belasan baris peringatan yang isinya sama — dan
           daftar sepanjang itu berhenti dibaca orang. */
        $sedangMelanggar = false;

        for ($t = $awal->copy(); $t->lte($akhir); $t->addDay()) {
            $total = 0;

            for ($i = 0; $i < 14; $i++) {
                $total += $jam[$t->copy()->addDays($i)->toDateString()] ?? 0;
            }

            if ($total > self::MAKS_JAM_14_HARI) {
                if (! $sedangMelanggar) {
                    $temuan[] = self::temuan('jam_14_hari', $t, $total, self::MAKS_JAM_14_HARI);
                    $sedangMelanggar = true;
                }
            } else {
                $sedangMelanggar = false;
            }
        }

        return $temuan;
    }

    /**
     * Hari kerja berturut-turut dan istirahat sesudahnya.
     *
     * Keduanya dihitung dalam satu lintasan, sebab keduanya membaca
     * rangkaian yang sama: panjang periode kerja, lalu panjang jeda
     * yang mengikutinya. Dipisah menjadi dua lintasan, keduanya harus
     * sepakat tentang di mana sebuah periode berakhir — dan yang
     * tidak sepakat memulangkan pelanggaran yang tidak ada.
     *
     * HARI YANG TIDAK ADA BARISNYA DIANGGAP LIBUR, dan itu bukan
     * tebakan: roster memang hanya diterbitkan untuk rentang tertentu,
     * dan memperlakukan hari di luarnya sebagai kerja akan menyambung
     * dua periode yang sebenarnya terpisah berbulan-bulan.
     *
     * @return list<array<string,mixed>>
     */
    private static function beruntunDanIstirahat(Collection $urut): array
    {
        if ($urut->isEmpty()) return [];

        $kerja = [];
        foreach ($urut as $r) {
            $kerja[$r->tanggal->toDateString()] = $r->bekerja();
        }

        $awal  = $urut->first()->tanggal->copy()->startOfDay();
        $akhir = $urut->last()->tanggal->copy()->startOfDay();

        $temuan   = [];
        $beruntun = 0;
        $mulaiRun = null;

        /* Panjang periode kerja yang baru saja berakhir, menunggu
           jedanya dihitung. Null berarti belum ada periode yang
           selesai — jeda di AWAL rentang bukan istirahat yang kurang,
           melainkan hari-hari sebelum rosternya dimulai. */
        $menunggu = null;
        $jeda     = 0;

        for ($t = $awal->copy(); $t->lte($akhir); $t->addDay()) {
            $hari = $t->toDateString();

            if ($kerja[$hari] ?? false) {
                /* Istirahat hanya diperiksa sesudah periode kerja yang
                   memang panjang — lihat AMBANG_PERIODE_PANJANG. Tanpa
                   penjagaan itu, tiap akhir pekan pola 5:2 dilaporkan
                   sebagai pelanggaran yang menolak penerbitan. */
                if ($menunggu !== null
                    && $menunggu > self::AMBANG_PERIODE_PANJANG
                    && $jeda < self::MIN_HARI_ISTIRAHAT) {
                    $temuan[] = self::temuan('istirahat', $t, $jeda, self::MIN_HARI_ISTIRAHAT);
                }

                $menunggu = null;
                $jeda     = 0;

                if ($beruntun === 0) $mulaiRun = $t->copy();
                $beruntun++;

                if ($beruntun === self::MAKS_HARI_BERUNTUN + 1) {
                    $temuan[] = self::temuan('hari_beruntun', $mulaiRun, $beruntun, self::MAKS_HARI_BERUNTUN);
                }

                continue;
            }

            if ($beruntun > 0) {
                $menunggu = $beruntun;
                $beruntun = 0;
                $jeda     = 0;
            }

            if ($menunggu !== null) $jeda++;
        }

        return $temuan;
    }

    /**
     * Batas 40 jam seminggu — DICATAT, TIDAK MEMBLOKIR.
     *
     * Pola 14:7 berjam 11 menghasilkan 154 jam per 21 hari, setara
     * 51,3 jam seminggu. Memblokirnya berarti menolak seluruh pola
     * roster tambang yang sah; batas inilah yang justru dikecualikan
     * Kepmenakertrans 234/2003 bagi sektor ESDM di daerah tertentu.
     * Tetap ditampilkan karena pengecualiannya bersyarat, dan yang
     * menyusun roster berhak tahu ia sedang berada di dalamnya.
     *
     * @return list<array<string,mixed>>
     */
    private static function jamMingguan(Collection $urut): array
    {
        $perMinggu = [];

        foreach ($urut as $r) {
            if (! $r->bekerja()) continue;

            $senin = $r->tanggal->copy()->startOfWeek()->toDateString();
            $perMinggu[$senin] = ($perMinggu[$senin] ?? 0) + $r->jam;
        }

        $temuan = [];

        foreach ($perMinggu as $senin => $jam) {
            if ($jam <= self::MAKS_JAM_MINGGU) continue;

            $temuan[] = self::temuan('jam_minggu', Carbon::parse($senin), $jam, self::MAKS_JAM_MINGGU);
        }

        return $temuan;
    }

    /** @return array<string,mixed> */
    private static function temuan(string $jenis, Carbon $tanggal, int $nilai, int $batas): array
    {
        return [
            'jenis'   => $jenis,
            'label'   => self::PELANGGARAN[$jenis] ?? $jenis,
            'tanggal' => $tanggal->toDateString(),
            'nilai'   => $nilai,
            'batas'   => $batas,
            'menolak' => in_array($jenis, self::MENOLAK, true),
        ];
    }
}
