<?php

namespace App\Support\Hr;

use App\Models\Hr\{Cuti, Roster, SaldoCuti};
use App\Models\User;
use App\Support\Waktu;
use Illuminate\Support\Facades\DB;

/**
 * Menyetujui, menolak, dan membatalkan cuti — beserta akibatnya pada
 * roster dan saldo.
 *
 * INILAH YANG MEMBUAT MODUL CUTI BUKAN FORMULIR. Persetujuan tidak
 * sekadar mengubah satu kolom status: ia memotong saldo, menuliskan
 * hari-harinya ke kalender regu, dan menandai baris roster itu supaya
 * penyusunan ulang tidak menghapusnya. Ketiganya harus terjadi
 * bersama-sama atau tidak sama sekali — karena itu di dalam satu
 * transaksi.
 *
 * Tanpa tulis-balik ke roster, seorang yang cutinya disetujui tetap
 * tercatat dijadwalkan kerja pada kalender regunya, lalu tercatat
 * mangkir pada layar absensi — dan pengawas pos jaga menelusuri
 * ketidakhadiran yang sudah disetujui atasannya sendiri seminggu
 * sebelumnya.
 *
 * YANG MENYETUJUI TIDAK BOLEH ORANG YANG MENGAJUKAN. Ditegakkan di
 * sini, bukan diserahkan pada layar: tombolnya dapat disembunyikan,
 * tetapi permintaannya tetap dapat dikirim.
 */
final class JalurCuti
{
    /**
     * Setujui sebuah pengajuan.
     *
     * @return string|null alasan penolakan, atau null bila berhasil
     */
    public static function setujui(Cuti $cuti, User $oleh, ?string $catatan = null): ?string
    {
        if (! $cuti->menunggu()) return 'Pengajuan ini sudah '.Cuti::STATUS[$cuti->status].'.';

        if (self::pengaju($cuti, $oleh)) {
            return 'Yang mengajukan tidak dapat menyetujui pengajuannya sendiri.';
        }

        if ($cuti->perlu_jenjang && ! $oleh->isKtt() && ! $oleh->isAdmin()) {
            return 'Pengajuan ini diteruskan ke jenjang di atasnya dan menunggu KTT.';
        }

        /* Diperiksa ULANG di sini, bukan hanya saat diajukan. Di antara
           pengajuan dan persetujuan, roster dapat berubah dan pengajuan
           lain dapat disetujui lebih dahulu — dan saldo yang cukup
           kemarin belum tentu cukup hari ini. */
        $alasan = KebijakanCuti::periksa(
            $cuti->pekerja,
            $cuti->jenis,
            $cuti->mulai,
            $cuti->selesai,
            $cuti->id,
        );

        if ($alasan !== null) return $alasan;

        DB::transaction(function () use ($cuti, $oleh, $catatan) {
            $n = self::tulisKeRoster($cuti);

            /* Hari yang dipotong dihitung ULANG dari roster saat
               disetujui, bukan dipakai apa adanya dari saat diajukan.
               Roster berubah di antara keduanya — regu dipindah, pola
               disunting — dan angka yang lama memotong saldo untuk hari
               yang ternyata sudah libur. */
            $cuti->forceFill([
                'hari'           => $n['hari'],
                'kalender'       => $n['kalender'],
                'status'         => 'disetujui',
                'ditindak_oleh'  => $oleh->id,
                'ditindak_pada'  => Waktu::kiniSimpan(),
                'catatan_tindak' => $catatan,
            ])->save();

            self::potongSaldo($cuti, $n['hari']);
        });

        return null;
    }

    public static function tolak(Cuti $cuti, User $oleh, ?string $catatan = null): ?string
    {
        if (! $cuti->menunggu()) return 'Pengajuan ini sudah '.Cuti::STATUS[$cuti->status].'.';

        if (self::pengaju($cuti, $oleh)) {
            return 'Yang mengajukan tidak dapat menolak pengajuannya sendiri.';
        }

        $cuti->forceFill([
            'status'         => 'ditolak',
            'ditindak_oleh'  => $oleh->id,
            'ditindak_pada'  => Waktu::kiniSimpan(),
            'catatan_tindak' => $catatan,
        ])->save();

        return null;
    }

    /**
     * Teruskan ke jenjang di atasnya.
     *
     * Mock PRD menyebutnya "Teruskan ke Superintendent": atasan
     * langsung boleh menyetujui yang biasa, tetapi yang berdampak pada
     * manpower site diteruskan. Statusnya TIDAK berubah — pengajuannya
     * tetap menunggu, hanya siapa yang berwenang menindaknya yang
     * bergeser.
     */
    public static function teruskan(Cuti $cuti, User $oleh, ?string $catatan = null): ?string
    {
        if (! $cuti->menunggu()) return 'Pengajuan ini sudah '.Cuti::STATUS[$cuti->status].'.';

        if ($cuti->perlu_jenjang) return 'Pengajuan ini sudah berada di jenjang teratas.';

        $cuti->forceFill([
            'perlu_jenjang'  => true,
            'catatan_tindak' => $catatan,
        ])->save();

        return null;
    }

    /**
     * Batalkan cuti yang sudah disetujui.
     *
     * MENGEMBALIKAN PERSIS BARIS YANG DIUBAHNYA, ditemukan lewat
     * `cuti_id` pada baris rosternya. Dikembalikan dengan mencari
     * setiap baris yang kebetulan berkeadaan sama, pembatalan satu
     * cuti akan menghapus cuti orang lain yang bertetangga tanggalnya —
     * dan tidak ada satu galat pun yang menandainya.
     */
    public static function batalkan(Cuti $cuti, User $oleh, ?string $catatan = null): ?string
    {
        if (! in_array($cuti->status, ['menunggu', 'disetujui'], true)) {
            return 'Pengajuan ini sudah '.Cuti::STATUS[$cuti->status].'.';
        }

        $disetujui = $cuti->status === 'disetujui';

        DB::transaction(function () use ($cuti, $oleh, $catatan, $disetujui) {
            if ($disetujui) {
                self::kembalikanRoster($cuti);
                self::potongSaldo($cuti, -$cuti->hari);
            }

            $cuti->forceFill([
                'status'         => 'dibatalkan',
                'ditindak_oleh'  => $oleh->id,
                'ditindak_pada'  => Waktu::kiniSimpan(),
                'catatan_tindak' => $catatan,
            ])->save();
        });

        return null;
    }

    /* ═══════════════════ akibat pada roster ═══════════════════ */

    /**
     * Tuliskan hari-harinya ke kalender regu.
     *
     * HANYA HARI YANG MEMANG KERJA yang diubah. Hari libur tidak
     * disentuh: mengubahnya menjadi "cuti" membuat periode off-site
     * pola 14:7 tampak sebagai cuti tahunan sepanjang tujuh hari, dan
     * rekap ketidakhadiran site kehilangan artinya.
     *
     * BARIS YANG SUDAH TERBIT IKUT DIUBAH, dan itu disengaja.
     * Persetujuan cuti adalah keputusan yang LEBIH BARU daripada
     * penerbitan roster; dibiarkan, orangnya tetap tercatat dijadwalkan
     * kerja pada hari ia sudah resmi cuti. Yang dijaga adalah
     * tandanya — `terbit` tidak dicabut, sehingga baris itu tetap tidak
     * tersusun ulang oleh baseline.
     *
     * @return array{hari:int,kalender:int}
     */
    private static function tulisKeRoster(Cuti $cuti): array
    {
        $n = KebijakanCuti::hariTerpotong($cuti->pekerja, $cuti->mulai, $cuti->selesai);

        if ($n['tanggal'] === []) return ['hari' => 0, 'kalender' => $n['kalender']];

        $keadaan = $cuti->jenis->keadaan_roster ?: 'cuti';

        $ada = Roster::withoutGlobalScopes()
            ->where('pekerja_id', $cuti->pekerja_id)
            ->antara($cuti->mulai->toDateString(), $cuti->selesai->toDateString())
            ->get()
            ->keyBy(fn (Roster $r) => $r->tanggal->toDateString());

        foreach ($n['tanggal'] as $hari) {
            $r = $ada->get($hari);

            if ($r) {
                $r->forceFill(['keadaan' => $keadaan, 'shift' => null, 'jam' => 0, 'cuti_id' => $cuti->id])->save();

                continue;
            }

            /* Hari yang belum punya baris roster TETAP DICATAT. Cuti
               yang diajukan untuk bulan yang rosternya belum disusun
               akan hilang begitu baseline dibuat — dan penyusunnya
               menjadwalkan orang yang sudah resmi cuti. */
            Roster::withoutGlobalScopes()->create([
                'company_id' => $cuti->company_id,
                'pekerja_id' => $cuti->pekerja_id,
                'tanggal'    => \Illuminate\Support\Carbon::parse($hari, \App\Support\Waktu::zona())->startOfDay(),
                'keadaan'    => $keadaan,
                'shift'      => null,
                'jam'        => 0,
                'cuti_id'    => $cuti->id,
            ]);
        }

        return ['hari' => count($n['tanggal']), 'kalender' => $n['kalender']];
    }

    /**
     * Kembalikan baris roster yang diubah cuti ini.
     *
     * Baris yang LAHIR dari cutinya dibuang; baris yang sudah ada
     * sebelumnya dikembalikan menjadi hari kerja. Keduanya dibedakan
     * lewat jamnya: baris yang lahir dari cuti tidak punya pola, jadi
     * tidak ada jam yang dapat dikembalikan kepadanya.
     */
    private static function kembalikanRoster(Cuti $cuti): void
    {
        foreach (Roster::withoutGlobalScopes()->where('cuti_id', $cuti->id)->get() as $r) {
            if ($r->pola_roster_id === null && $r->regu_id === null) {
                $r->delete();

                continue;
            }

            $r->forceFill([
                'keadaan' => 'kerja',
                'shift'   => $r->regu?->shiftPada($r->tanggal) ?? 'siang',
                'jam'     => $r->pola?->jam ?? 0,
                'cuti_id' => null,
            ])->save();
        }
    }

    /**
     * Potong (atau kembalikan) saldo, hanya bila jenisnya memotong.
     *
     * Cuti sakit dan izin khusus TIDAK memotong saldo tahunan: pasal
     * 93 menyebut upahnya tetap dibayar, bukan cuti tahunannya yang
     * dipakai. Dipotong, seorang yang ayahnya meninggal kehilangan dua
     * hari cuti tahunannya.
     */
    private static function potongSaldo(Cuti $cuti, int $hari): void
    {
        if (! $cuti->jenis->potong_saldo || $hari === 0) return;

        $saldo = KebijakanCuti::saldo($cuti->pekerja, $cuti->jenis, (int) $cuti->mulai->format('Y'));

        /* Tidak pernah turun di bawah nol. Pembatalan berulang atas
           baris yang sama akan menaikkan saldo tanpa batas bila
           angkanya tidak dijaga di sini. */
        $saldo->forceFill(['terpakai' => max(0, $saldo->terpakai + $hari)])->save();
    }

    private static function pengaju(Cuti $cuti, User $oleh): bool
    {
        if ($cuti->diajukan_oleh && (int) $cuti->diajukan_oleh === (int) $oleh->id) return true;

        /* Pekerja yang tertaut ke akun itu juga dihitung sebagai
           pengajunya, meski barisnya dibuat admin atas namanya. */
        return $cuti->pekerja?->user_id !== null
            && (int) $cuti->pekerja->user_id === (int) $oleh->id;
    }
}
