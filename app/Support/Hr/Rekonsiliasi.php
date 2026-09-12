<?php

namespace App\Support\Hr;

use App\Models\Hr\{Absensi, AbsensiJejak, PolaRoster, Roster};
use App\Support\Waktu;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Menurunkan catatan harian dari jejak pindaian, lalu
 * merekonsiliasinya terhadap roster.
 *
 * SHIFT MALAM MELEWATI TENGAH MALAM, dan di situlah seluruh
 * kesulitannya. Seorang yang masuk pukul 19.00 tanggal 3 dan pulang
 * pukul 06.00 tanggal 4 bekerja SATU hari kerja, bukan dua. Dikelompokkan
 * menurut tanggal kalendernya begitu saja, ia tampil dua kali: tanggal 3
 * masuk tanpa pulang, tanggal 4 pulang tanpa masuk. Dua-duanya lalu
 * ditandai janggal, jam kerjanya tercatat nol pada kedua baris, dan
 * seluruh regu malam — yaitu separuh site — muncul sebagai pelanggaran
 * yang harus ditelusuri satu per satu tiap pagi.
 *
 * Karena itu tiap peristiwa dicocokkan ke JENDELA SHIFT-nya, bukan ke
 * tanggalnya. Jendela dibuka beberapa jam sebelum shift dimulai (bus
 * jemputan datang awal) dan ditutup beberapa jam sesudah seharusnya
 * selesai (lembur, antrean pulang, alat yang macet).
 *
 * PENCOCOKANNYA MURNI — tidak bergantung pada urutan pemrosesan.
 * Peristiwa yang jatuh pada dua jendela sekaligus diberikan kepada
 * shift yang SUDAH DIMULAI dan paling belakangan dimulai. Aturan itu
 * yang membuat tap pulang pukul 05.00 tetap menjadi milik shift malam
 * kemarin, bukan milik shift siang yang baru akan dimulai pukul 07.00
 * pagi itu — padahal jaraknya ke shift siang jauh lebih dekat.
 *
 * TIDAK PERNAH MENIMPA KOREKSI MANUSIA. Baris yang sudah dikoreksi
 * dibiarkan apa adanya, persis seperti Penyusun membiarkan roster yang
 * sudah terbit. Tanpa penjagaan itu, satu batch luring yang datang
 * terlambat menghapus seluruh koreksi jam masuk yang sudah disetujui
 * pengawas — dan tidak ada satu pun galat yang menandainya, sebab
 * barisnya memang tertulis ulang dengan benar dari jejaknya.
 */
final class Rekonsiliasi
{
    /**
     * Jendela dibuka sekian jam SEBELUM shift dimulai.
     *
     * Bus jemputan site berangkat jauh sebelum jam kerja, dan yang
     * turun lebih dahulu menempelkan kartunya begitu sampai. Dipatok
     * nol, tap pukul 06.40 untuk shift 07.00 jatuh di luar jendela dan
     * orangnya tercatat absen padahal datang lebih awal.
     */
    public const JENDELA_AWAL_JAM = 4;

    /**
     * Jendela ditutup sekian jam SESUDAH shift seharusnya selesai.
     *
     * Lembur, antrean pulang di pos, dan alat yang macet. Dipatok nol,
     * tap pulang yang terlambat sepuluh menit tidak terjaring dan
     * orangnya tercatat "belum tap pulang" selamanya.
     */
    public const JENDELA_AKHIR_JAM = 6;

    /**
     * Rekonsiliasi satu rentang tanggal.
     *
     * @param  Collection<int,\App\Models\Miners\Pekerja>|list<int>  $pekerja  pekerja atau id-nya
     * @return array{dibuat:int,diperbarui:int,dilewati:int,luar_roster:int,absen:int}
     */
    public static function jalankan(array|Collection $pekerja, Carbon $dari, Carbon $sampai, ?int $olehUserId = null): array
    {
        $id = $pekerja instanceof Collection
            ? $pekerja->map(fn ($p) => is_object($p) ? $p->id : (int) $p)->all()
            : array_map('intval', $pekerja);

        $n = ['dibuat' => 0, 'diperbarui' => 0, 'dilewati' => 0, 'luar_roster' => 0, 'absen' => 0];

        if ($id === []) return $n;

        $dari   = $dari->copy()->startOfDay();
        $sampai = $sampai->copy()->startOfDay();

        /* Jendelanya DILEBARKAN SEHARI ke kedua arah saat membaca.
           Shift malam tanggal terakhir baru selesai keesokan paginya,
           dan shift malam sehari sebelum rentang masih memakan jam-jam
           pertama hari pertama. Dibaca apa adanya, tap pulang di kedua
           tepi itu hilang dari pencocokan lalu jatuh menjadi
           "luar roster" pada hari yang bukan miliknya. */
        $bacaDari   = $dari->copy()->subDay();
        $bacaSampai = $sampai->copy()->addDay();

        $roster = Roster::withoutGlobalScopes()
            ->with('pola')
            ->whereIn('pekerja_id', $id)
            ->antara($bacaDari->toDateString(), $bacaSampai->toDateString())
            ->get()
            ->groupBy('pekerja_id');

        $jejak = AbsensiJejak::withoutGlobalScopes()
            ->with('mesin')
            ->whereIn('pekerja_id', $id)
            ->where('terjadi', '>=', $bacaDari->copy()->startOfDay()->utc()->toDateTimeString())
            ->where('terjadi', '<=', $bacaSampai->copy()->endOfDay()->utc()->toDateTimeString())
            ->orderBy('terjadi')
            ->get()
            ->groupBy('pekerja_id');

        $ada = Absensi::withoutGlobalScopes()
            ->whereIn('pekerja_id', $id)
            ->antara($dari->toDateString(), $sampai->toDateString())
            ->get()
            ->keyBy(fn (Absensi $a) => $a->pekerja_id.'|'.$a->tanggal->toDateString());

        DB::transaction(function () use ($id, $roster, $jejak, $ada, $dari, $sampai, $olehUserId, &$n) {
            foreach ($id as $pid) {
                $barisRoster = $roster->get($pid) ?? collect();
                $barisJejak  = $jejak->get($pid) ?? collect();

                $jendela = self::jendela($barisRoster);
                $milik   = self::bagikan($barisJejak, $jendela);

                $tutup = collect($jendela)->keyBy('hari');

                for ($t = $dari->copy(); $t->lte($sampai); $t->addDay()) {
                    $hari = $t->toDateString();

                    $r = $barisRoster->first(fn (Roster $x) => $x->tanggal->toDateString() === $hari);
                    $e = $milik[$hari] ?? collect();

                    /* HARI YANG MEMANG LIBUR DAN KOSONG TIDAK
                       MENGHASILKAN BARIS SAMA SEKALI — termasuk hari
                       tanpa baris roster.

                       Dibuatkan baris, ia lahir berkeadaan "absen",
                       sebab tak ada satu pindaian pun padanya. Maka
                       tiap hari libur terjadwal tercatat sebagai
                       mangkir: pada data contoh pertama, 41 dari 44
                       "absen" sesungguhnya adalah hari libur yang
                       diambil orangnya dengan benar. Rekap
                       ketidakhadiran site menjadi angka yang tidak
                       berarti apa-apa, dan yang membacanya menelusuri
                       mangkir yang tidak pernah terjadi. */
                    if ($e->isEmpty() && ! ($r?->bekerja() ?? false)) continue;

                    /* KETIDAKHADIRAN ADALAH KESIMPULAN, dan kesimpulan
                       itu tidak dapat diambil sebelum shiftnya berakhir.
                       Hari kerja yang jendelanya belum tertutup dan
                       belum berisi satu pindaian pun karena itu tidak
                       menghasilkan baris sama sekali.
                       
                       Tanpa penjagaan ini, layar pos jaga pukul enam
                       pagi menyatakan SELURUH regu malam yang shiftnya
                       baru mulai pukul tujuh belas nanti sebagai absen —
                       dan roster bulan depan yang sudah tersusun tampil
                       sebagai ratusan hari mangkir yang belum terjadi. */
                    if ($e->isEmpty() && ($w = $tutup->get($hari)) && $w['tutup']->gt(Waktu::kini())) {
                        continue;
                    }

                    $isi = self::simpulkan($r, $e, $t);

                    if ($isi['keadaan'] === 'luar_roster') $n['luar_roster']++;
                    if ($isi['keadaan'] === 'absen')       $n['absen']++;

                    $lama = $ada->get($pid.'|'.$hari);

                    if ($lama && $lama->dikoreksi) { $n['dilewati']++; continue; }

                    if ($lama) {
                        $lama->forceFill($isi)->save();
                        $n['diperbarui']++;
                        continue;
                    }

                    Absensi::withoutGlobalScopes()->create($isi + [
                        'pekerja_id' => $pid,
                        'tanggal'    => $t->copy(),
                    ]);

                    $n['dibuat']++;
                }
            }
        });

        return $n;
    }

    /**
     * Jendela shift tiap hari kerja seorang pekerja.
     *
     * @param  Collection<int,Roster>  $roster
     * @return list<array{hari:string,mulai:Carbon,buka:Carbon,tutup:Carbon}>
     */
    private static function jendela(Collection $roster): array
    {
        $out = [];

        foreach ($roster as $r) {
            if (! $r->bekerja()) continue;

            $hari = $r->tanggal->toDateString();
            $pola = $r->pola;

            /* Roster tanpa pola masih dapat dicocokkan — jam mulainya
               diambil dari bawaan. Dilewati, seorang yang rosternya
               disunting tangan kehilangan seluruh jendela shiftnya dan
               tiap tapnya jatuh menjadi "luar roster". */
            $mulaiJam = $pola
                ? $pola->mulaiShift($r->shift)
                : PolaRoster::MULAI_BAWAAN[$r->shift === 'malam' ? 'malam' : 'siang'];

            $mulai = Carbon::parse($hari.' '.$mulaiJam, Waktu::zona());

            $jam = (int) ($r->jam ?: ($pola->jam ?? 11));

            $out[] = [
                'hari'    => $hari,
                'mulai'   => $mulai,

                /* Akhir shift yang SEBENARNYA, bukan akhir jendelanya.
                   Keduanya dipakai untuk hal berbeda: jendela menentukan
                   peristiwa mana yang mungkin milik hari ini, rentang
                   shift menentukan milik SIAPA ketika dua hari
                   memperebutkannya. */
                'selesai' => $mulai->copy()->addHours($jam),

                'buka'    => $mulai->copy()->subHours(self::JENDELA_AWAL_JAM),
                'tutup'   => $mulai->copy()->addHours($jam + self::JENDELA_AKHIR_JAM),
            ];
        }

        return $out;
    }

    /**
     * Bagikan tiap peristiwa ke hari kerja pemiliknya.
     *
     * @param  Collection<int,AbsensiJejak>  $jejak
     * @param  list<array{hari:string,mulai:Carbon,buka:Carbon,tutup:Carbon}>  $jendela
     * @return array<string,Collection<int,AbsensiJejak>>
     */
    private static function bagikan(Collection $jejak, array $jendela): array
    {
        $out = [];

        foreach ($jejak as $j) {
            $saat = Waktu::lokal($j->terjadi);

            if (! $saat) continue;

            $hari = self::hariKerja($saat, $jendela) ?? $saat->toDateString();

            $out[$hari] ??= collect();
            $out[$hari]->push($j);
        }

        return $out;
    }

    /**
     * Hari kerja yang memiliki sebuah saat.
     *
     * DIPUTUSKAN MENURUT JARAK KE RENTANG SHIFTNYA, bukan ke jam
     * mulainya. Jendela dua hari berturut-turut memang bertindihan —
     * itu disengaja, sebab lembur dan datang awal harus tetap
     * terjaring — dan ketika keduanya memuat satu peristiwa yang sama,
     * yang menang adalah shift yang rentang kerjanya paling dekat.
     *
     * Bedanya nyata pada pergantian malam ke siang, yang benar-benar
     * terjadi di lapangan:
     *
     *   tap 05.10 — sepuluh menit sesudah shift malam berakhir pukul
     *   05.00, lima puluh menit sebelum shift siang mulai pukul 06.00.
     *   Milik shift malam kemarin.
     *
     *   tap 05.55 — lima puluh lima menit sesudah shift malam berakhir,
     *   lima menit sebelum shift siang mulai. Milik shift siang hari
     *   ini.
     *
     * Diputuskan menurut jam mulai, keduanya jatuh ke shift malam —
     * sebab shift itu memang sudah dimulai dan yang siang belum. Tap
     * masuk siang lalu ikut tertelan, dan orangnya tercatat "belum tap
     * pulang" pada hari ia bekerja sebelas jam penuh. Uji
     * test_tap_pulang_pagi_tidak_direbut_shift_siang_hari_itu yang
     * menemukannya.
     */
    private static function hariKerja(Carbon $saat, array $jendela): ?string
    {
        $pilih = null;
        $dekat = null;

        foreach ($jendela as $w) {
            if ($saat->lt($w['buka']) || $saat->gt($w['tutup'])) continue;

            $jarak = match (true) {
                $saat->lt($w['mulai'])   => $w['mulai']->diffInMinutes($saat, true),
                $saat->gt($w['selesai']) => $w['selesai']->diffInMinutes($saat, true),

                /* Di dalam rentang shiftnya sendiri — tidak ada yang
                   lebih dekat daripada ini. */
                default                  => 0.0,
            };

            /* Seri dimenangkan yang lebih dahulu mulai, supaya
               jawabannya tidak bergantung pada urutan baris roster
               terbaca dari basis data. */
            if ($dekat === null || $jarak < $dekat
                || ($jarak === $dekat && $w['mulai']->lt($pilih['mulai']))) {
                $dekat = $jarak;
                $pilih = $w;
            }
        }

        return $pilih['hari'] ?? null;
    }

    /**
     * Simpulkan satu baris harian dari roster dan peristiwanya.
     *
     * @param  Collection<int,AbsensiJejak>  $e
     * @return array<string,mixed>
     */
    private static function simpulkan(?Roster $r, Collection $e, Carbon $tanggal): array
    {
        $kerja = $r?->bekerja() ?? false;

        $masuk  = self::masuk($e);
        $keluar = self::keluar($e, $masuk);

        $jam = ($masuk && $keluar)
            ? round(max(0, $masuk->diffInMinutes($keluar)) / 60, 2)
            : 0;

        $telat = ($kerja && $masuk) ? self::telat($r, $tanggal, $masuk) : null;

        $toleransi = $kerja ? self::toleransi($r) : 0;

        return [
            'company_id'  => $r?->company_id ?? $e->first()?->company_id,
            'roster_id'   => $r?->id,
            'blok_id'     => $r?->blok_id ?? $e->first()?->mesin?->blok_id,
            /* UTC saat disimpan, WITA saat dihitung — lihat
               Waktu::simpan(). Disimpan apa adanya, tap pukul 07.02
               tercatat pukul 15.02 dan terlambat 540 menit. */
            'masuk'       => Waktu::simpan($masuk),
            'keluar'      => Waktu::simpan($keluar),
            'jam'         => $jam,
            'telat_menit' => $telat,
            'keadaan'     => self::keadaan($kerja, $masuk, $keluar, $telat, $toleransi),
            'sumber'      => self::sumber($e),
            'luring'      => $e->contains(fn (AbsensiJejak $j) => (bool) $j->luring),
            'dalam_area'  => self::dalamArea($e),
        ];
    }

    /**
     * Jam masuk: tap "masuk" paling awal.
     *
     * Bila tak ada satu pun yang bertanda masuk, peristiwa paling awal
     * dipakai apa adanya. Alat di pos jaga yang salah setel mengirim
     * seluruh tapnya bertanda "keluar", dan menolak membaca jam masuk
     * darinya berarti menghapus kehadiran seluruh regu yang lewat pos
     * itu.
     */
    private static function masuk(Collection $e): ?Carbon
    {
        if ($e->isEmpty()) return null;

        $m = $e->where('arah', 'masuk')->sortBy('terjadi')->first() ?? $e->sortBy('terjadi')->first();

        return Waktu::lokal($m->terjadi);
    }

    /**
     * Jam keluar: tap "keluar" paling akhir SESUDAH jam masuk.
     *
     * Syarat "sesudah" itu menjaga dari alat yang salah setel juga:
     * tanpa itu, satu-satunya tap hari itu menjadi jam masuk DAN jam
     * keluar sekaligus, dan jam kerjanya tercatat nol jam pada orang
     * yang bekerja sebelas jam.
     */
    private static function keluar(Collection $e, ?Carbon $masuk): ?Carbon
    {
        if ($e->isEmpty() || ! $masuk) return null;

        $k = $e->where('arah', 'keluar')
            ->filter(fn (AbsensiJejak $j) => Waktu::lokal($j->terjadi)?->gt($masuk))
            ->sortByDesc('terjadi')
            ->first();

        return $k ? Waktu::lokal($k->terjadi) : null;
    }

    /**
     * Keadaan hari itu.
     *
     * URUTANNYA DISENGAJA:
     *
     *   "luar roster" menang atas segalanya. Yang perlu diketahui lebih
     *   dahulu bukanlah bahwa orangnya terlambat, melainkan bahwa ia
     *   bekerja pada hari liburnya — jamnya tidak masuk hitungan
     *   tunjangan site, ikut menghitung batas empat belas hari
     *   berturut-turut, dan hampir selalu berarti ada lembur yang belum
     *   diperintahkan.
     *
     *   "belum tap pulang" menang atas "terlambat". Orang yang tidak
     *   tercatat keluar mungkin masih berada di area — dan pada
     *   keadaan darurat, daftar siapa yang belum keluar site adalah
     *   daftar yang pertama dicari. Keterlambatannya tidak hilang:
     *   telat_menit tetap tersimpan pada baris yang sama.
     */
    private static function keadaan(bool $kerja, ?Carbon $masuk, ?Carbon $keluar, ?int $telat, int $toleransi): string
    {
        /* Tak ada jam masuk berarti tak ada satu pun pindaian hari itu:
           masuk() memulangkan peristiwa paling awal kapan pun ada satu.
           Keduanya karena itu pertanyaan yang sama. */
        if (! $kerja) return $masuk === null ? 'absen' : 'luar_roster';

        if (! $masuk)  return 'absen';
        if (! $keluar) return 'belum_pulang';

        return ($telat !== null && $telat > $toleransi) ? 'terlambat' : 'hadir';
    }

    /** @param Collection<int,AbsensiJejak> $e */
    private static function sumber(Collection $e): ?string
    {
        $s = $e->pluck('sumber')->filter()->unique()->values();

        return match ($s->count()) {
            0       => null,
            1       => (string) $s->first(),
            default => 'campuran',
        };
    }

    /**
     * Apakah seluruh pindaian hari itu berada di dalam area.
     *
     * SATU TAP DI LUAR AREA MENJATUHKAN SELURUH HARINYA, dan memang
     * itu yang dimaksudkan: yang perlu diperiksa adalah harinya, bukan
     * tapnya. Diambil dari tap masuk saja, absen ponsel dari rumah pada
     * jam pulang tidak pernah muncul di mana pun.
     *
     * @param  Collection<int,AbsensiJejak>  $e
     */
    private static function dalamArea(Collection $e): ?bool
    {
        $nilai = $e->pluck('dalam_area')->reject(fn ($v) => $v === null);

        if ($nilai->isEmpty()) return null;

        return ! $nilai->contains(false);
    }

    /* ═══════════════════ koreksi manusia ═══════════════════ */

    /**
     * Nilai ulang sebuah baris atas jam yang dikoreksi manusia.
     *
     * MEMAKAI ATURAN YANG SAMA PERSIS dengan yang dipakai jejak mesin,
     * dan itu yang penting di sini. Ditulis terpisah, keterlambatan
     * hasil koreksi cepat atau lambat dihitung dengan toleransi yang
     * berbeda dari keterlambatan hasil pindaian — dua orang yang datang
     * pada menit yang sama lalu tercatat berbeda, dan yang satu
     * ditegur.
     *
     * @return array<string,mixed>
     */
    public static function koreksi(Absensi $a, ?Carbon $masuk, ?Carbon $keluar): array
    {
        $r     = $a->roster;
        $kerja = $r?->bekerja() ?? false;

        /* JAM PULANG YANG LEBIH AWAL DARIPADA JAM MASUK BERARTI SHIFT
           MALAM, bukan salah ketik — dan aturannya tinggal DI SINI
           saja, bukan di pemanggilnya. Dibaca apa adanya, sebelas jam
           kerja tercatat menjadi minus tiga belas; ditafsirkan di dua
           tempat, cepat atau lambat keduanya berbeda dan koreksi lewat
           layar tidak lagi sama dengan koreksi lewat perintah.

           Yang PERSIS SAMA dibuang: satu saat tidak dapat menjadi jam
           masuk sekaligus jam pulang, dan menggesernya sehari akan
           mencatat shift dua puluh empat jam. */
        if ($masuk && $keluar) {
            $keluar = match (true) {
                $keluar->eq($masuk) => null,
                $keluar->lt($masuk) => $keluar->copy()->addDay(),
                default             => $keluar,
            };
        }

        $jam = ($masuk && $keluar) ? round(max(0, $masuk->diffInMinutes($keluar)) / 60, 2) : 0;

        $telat = ($kerja && $masuk) ? self::telat($r, $a->tanggal, $masuk) : null;

        return [
            /* Disimpan sebagai UTC; dihitung di atas sebagai WITA.
               Keduanya perlu: selisih jam dihitung atas saat, tetapi
               nilai yang masuk ke kolom TIMESTAMP harus sudah dipindah
               zonanya — Eloquent menyimpan jam dindingnya apa adanya.
               Lihat Waktu::simpan(). */
            'masuk'       => Waktu::simpan($masuk),
            'keluar'      => Waktu::simpan($keluar),
            'jam'         => $jam,
            'telat_menit' => $telat,
            'keadaan'     => self::keadaan($kerja, $masuk, $keluar, $telat, $kerja ? self::toleransi($r) : 0),
        ];
    }

    /**
     * Menit keterlambatan terhadap jam mulai shift.
     *
     * DATANG LEBIH AWAL BUKAN KETERLAMBATAN NEGATIF. Disimpan
     * bertanda, rekap keterlambatan sebulan saling meniadakan: yang
     * datang setengah jam awal menghapus keterlambatan setengah jam
     * rekannya, dan jumlahnya nol pada regu yang separuhnya terlambat
     * tiap hari.
     */
    private static function telat(?Roster $r, Carbon $tanggal, Carbon $masuk): int
    {
        $pola = $r?->pola;

        $jamMulai = $pola
            ? $pola->mulaiShift($r->shift)
            : PolaRoster::MULAI_BAWAAN[$r?->shift === 'malam' ? 'malam' : 'siang'];

        $mulai = Carbon::parse($tanggal->toDateString().' '.$jamMulai, Waktu::zona());

        return max(0, (int) round($mulai->diffInMinutes($masuk, false)));
    }

    private static function toleransi(?Roster $r): int
    {
        return $r?->pola?->toleransi() ?? PolaRoster::TOLERANSI_BAWAAN;
    }
}
