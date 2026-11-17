<?php

namespace App\Support\Hr;

use App\Models\Hr\{Regu, Roster};
use App\Models\Miners\Pekerja;
use App\Support\Waktu;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menyusun roster satu regu untuk satu rentang tanggal.
 *
 * MENGHITUNG DULU, MENYIMPAN KEMUDIAN. Baseline datang dari pola dan
 * tanggal jangkar regunya; yang disimpan adalah hasilnya, supaya tukar
 * jaga dan cuti yang disunting sesudahnya tidak hilang saat layarnya
 * dimuat ulang.
 *
 * TIDAK MENIMPA YANG SUDAH DISUNTING. Penyusunan ulang sebuah bulan
 * hanya menyentuh baris yang belum diterbitkan; yang sudah terbit
 * dibiarkan apa adanya. Tanpa penjagaan itu, menekan "susun ulang"
 * karena satu orang pindah regu akan menghapus seluruh cuti yang sudah
 * disetujui sebulan itu — dan tidak ada satu pun galat yang
 * menandainya, sebab barisnya memang tergantikan dengan benar.
 */
final class Penyusun
{
    /**
     * Susun baseline satu regu.
     *
     * @return array{dibuat:int,diperbarui:int,dilewati:int,halangan:int}
     */
    public static function susun(Regu $regu, Carbon $dari, Carbon $sampai, ?int $olehUserId = null): array
    {
        $pola = $regu->pola;

        if (! $pola) {
            return ['dibuat' => 0, 'diperbarui' => 0, 'dilewati' => 0, 'halangan' => 0];
        }

        $dari   = $dari->copy()->startOfDay();
        $sampai = $sampai->copy()->startOfDay();

        $n = ['dibuat' => 0, 'diperbarui' => 0, 'dilewati' => 0, 'halangan' => 0];

        /* Anggota dimuat SEKALI beserta seluruh berkasnya, bukan per
           tanggal. Dimuat per tanggal, menyusun sebulan untuk satu regu
           berisi tiga puluh orang membaca basis data seribu kali lebih
           banyak untuk jawaban yang sama persis. */
        $anggota = $regu->anggota()
            ->with(['pekerja' => fn ($q) => $q->withoutGlobalScopes()->with(Kelayakan::relasi())])
            ->get();

        if ($anggota->isEmpty()) return $n;

        /* Yang sudah ada dibaca sekali di muka — satu kueri, bukan satu
           per baris. */
        $ada = Roster::withoutGlobalScopes()
            ->whereIn('pekerja_id', $anggota->pluck('pekerja_id'))
            ->antara($dari->toDateString(), $sampai->toDateString())
            ->get()
            ->keyBy(fn (Roster $r) => $r->pekerja_id.'|'.$r->tanggal->toDateString());

        DB::transaction(function () use ($regu, $pola, $anggota, $ada, $dari, $sampai, $olehUserId, &$n) {
            for ($t = $dari->copy(); $t->lte($sampai); $t->addDay()) {
                $bekerja = $regu->bekerjaPada($t);
                $shift   = $regu->shiftPada($t);
                $hari    = $t->toDateString();

                foreach ($anggota as $a) {
                    /* Keanggotaan bertanggal: yang belum masuk atau
                       sudah keluar regu pada tanggal itu dilewati, bukan
                       dijadwalkan libur. Dijadwalkan libur, ia tampil
                       pada kalender regu yang bukan lagi regunya.
                       
                       DIBANDINGKAN SEBAGAI UNTAIAN TANGGAL, bukan
                       sebagai saat: `$a->mulai` dibaca dari basis data
                       dan ditafsirkan pada zona aplikasi, sedangkan
                       `$t` berzona WITA. Keduanya menyebut hari yang
                       sama tetapi berselisih delapan jam — dan
                       `gt()` menjawab menurut selisih itu, sehingga
                       anggota yang ditambahkan "mulai hari ini"
                       kehilangan hari pertamanya tanpa satu galat pun. */
                    if ($a->mulai && $a->mulai->format('Y-m-d') > $hari) continue;
                    if ($a->selesai && $a->selesai->format('Y-m-d') < $hari) continue;

                    $kunci = $a->pekerja_id.'|'.$hari;
                    $lama  = $ada->get($kunci);

                    /* BARIS YANG LAHIR DARI CUTI IKUT DILEWATI,
                       bukan hanya yang sudah terbit. Tanpa penjagaan
                       ini, menekan "susun ulang" karena satu orang
                       pindah regu menghapus seluruh cuti yang sudah
                       disetujui bulan itu — dan tidak ada satu galat
                       pun yang menandainya, sebab barisnya memang
                       tergantikan dengan benar oleh baseline. */
                    if ($lama && ($lama->terbit || $lama->cuti_id)) { $n['dilewati']++; continue; }

                    $p = $a->pekerja;

                    $halangan = null;

                    if ($bekerja && $p) {
                        $periksa  = Kelayakan::periksa($p, $t);
                        $halangan = $periksa['halangan'];

                        if ($halangan !== null) $n['halangan']++;
                    }

                    $isi = [
                        'company_id'     => $regu->company_id,
                        'regu_id'        => $regu->id,
                        'pola_roster_id' => $pola->id,
                        'blok_id'        => $regu->blok_id,
                        'keadaan'        => $bekerja ? 'kerja' : 'libur',
                        'shift'          => $bekerja ? $shift : null,
                        'jam'            => $bekerja ? $pola->jam : 0,
                        'halangan'       => $halangan,
                        'user_id'        => $olehUserId,
                    ];

                    if ($lama) {
                        $lama->forceFill($isi)->save();
                        $n['diperbarui']++;
                        continue;
                    }

                    Roster::withoutGlobalScopes()->create($isi + [
                        'pekerja_id' => $a->pekerja_id,
                        'tanggal'    => $t->copy(),
                    ]);

                    $n['dibuat']++;
                }
            }
        });

        return $n;
    }

    /**
     * Terbitkan roster satu regu untuk satu rentang.
     *
     * MEMERIKSA DULU, MENERBITKAN KEMUDIAN — dan pemeriksaannya per
     * ORANG, bukan per regu. Batas fatigue melekat pada orangnya:
     * seorang yang dipinjamkan ke regu lain di tengah periode kerjanya
     * dapat melampaui empat belas hari berturut-turut meski tidak satu
     * pun regunya melanggar sendirian.
     *
     * @return array{terbit:int,ditolak:list<array<string,mixed>>}
     */
    public static function terbitkan(Regu $regu, Carbon $dari, Carbon $sampai): array
    {
        $dari   = $dari->copy()->startOfDay();
        $sampai = $sampai->copy()->startOfDay();

        $pekerja = $regu->anggota()->pluck('pekerja_id');

        $ditolak = [];

        foreach ($pekerja as $id) {
            /* Jendelanya DILEBARKAN ke belakang dan ke depan. Batas
               "14 hari berturut-turut" dan "istirahat 5 hari" hanya
               dapat dilihat dari rangkaian yang melintasi tepi rentang
               yang sedang diterbitkan — diperiksa apa adanya, periode
               kerja yang bersambung dari bulan lalu tampak baru dimulai
               tanggal satu. */
            $baris = Roster::withoutGlobalScopes()
                ->where('pekerja_id', $id)
                ->antara(
                    $dari->copy()->subDays(Fatigue::MAKS_HARI_BERUNTUN + Fatigue::MIN_HARI_ISTIRAHAT)->toDateString(),
                    $sampai->copy()->addDays(Fatigue::MAKS_HARI_BERUNTUN)->toDateString(),
                )
                ->orderBy('tanggal')
                ->get();

            $temuan = Fatigue::periksa($baris);

            foreach ($temuan as $t) {
                if (! $t['menolak']) continue;

                $ditolak[] = $t + ['pekerja_id' => $id];
            }
        }

        if ($ditolak !== []) return ['terbit' => 0, 'ditolak' => $ditolak];

        $terbit = Roster::withoutGlobalScopes()
            ->where('regu_id', $regu->id)
            ->antara($dari->toDateString(), $sampai->toDateString())
            /* UTC, bukan WITA. Eloquent menyimpan jam dinding Carbon
               yang diberikan tanpa memindahkan zonanya — dan `updated_at`
               berzona WITA tersimpan delapan jam di masa depan, sehingga
               "diterbitkan pukul berapa" terbaca salah pada tiap baris
               yang pernah diterbitkan. Lihat Waktu::simpan(). */
            ->update(['terbit' => true, 'updated_at' => Waktu::kiniSimpan()]);

        return ['terbit' => $terbit, 'ditolak' => []];
    }
}
