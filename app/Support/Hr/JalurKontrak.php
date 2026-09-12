<?php

namespace App\Support\Hr;

use App\Models\Hr\Kontrak;
use App\Models\Miners\Pekerja;
use App\Models\User;
use App\Support\Waktu;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menerbitkan, memperpanjang, dan mengakhiri kontrak.
 *
 * PERPANJANGAN MEMBUAT BARIS BARU YANG MENUNJUK INDUKNYA. Yang lama
 * ditutup sebagai "selesai" dan kompensasinya dihitung saat itu juga —
 * pasal 17 menyebut kompensasi jatuh tempo pada selesainya tiap
 * periode, bukan sekali di ujung rangkaian. Ditulis sebagai tanggal
 * selesai yang digeser, tidak ada satu pun periode yang pernah
 * "selesai", dan kompensasi yang seharusnya dibayar tiga kali tidak
 * pernah dibayar sama sekali.
 *
 * KONTRAK BERJALAN TIDAK DAPAT DISUNTING MENJADI JENIS LAIN. Mengubah
 * PKWT yang sudah berjalan menjadi PKWTT lewat formulir akan membuang
 * rantainya, dan bersamanya hilang bukti bahwa batas lima tahun pernah
 * terlampaui. Perubahan jenis hanya terjadi lewat `jadikanPkwtt`, yang
 * menyimpan alasannya.
 */
final class JalurKontrak
{
    /**
     * Terbitkan kontrak: draf menjadi berjalan.
     *
     * @return string|null alasan penolakan, atau null bila berhasil
     */
    public static function terbitkan(Kontrak $k, User $oleh): ?string
    {
        if ($k->status !== 'draft') {
            return 'Kontrak ini sudah '.Kontrak::STATUS[$k->status].'.';
        }

        if (($tabrak = self::tabrakan($k)) !== null) return $tabrak;

        $k->forceFill([
            'status'              => 'berjalan',
            'ditandatangani_pada' => $k->ditandatangani_pada ?? Waktu::kini()->toDateString(),
        ])->save();

        return null;
    }

    /**
     * Perpanjang kontrak berjalan dengan periode baru.
     *
     * Yang lama ditutup dan kompensasinya dihitung; yang baru menunjuk
     * yang lama sebagai induknya.
     *
     * @return array{0:?string,1:?Kontrak} [alasan penolakan, kontrak baru]
     */
    public static function perpanjang(Kontrak $lama, array $isi, User $oleh): array
    {
        if (! $lama->hidup()) {
            return ['Hanya kontrak yang sedang berjalan dapat diperpanjang.', null];
        }

        if (! $lama->pkwt()) {
            return ['PKWTT tidak diperpanjang — ia memang tidak berakhir.', null];
        }

        $mulai   = Carbon::parse($isi['mulai']);
        $selesai = isset($isi['selesai']) && $isi['selesai'] ? Carbon::parse($isi['selesai']) : null;

        if ($selesai !== null && $selesai->lt($mulai)) {
            return ['Tanggal selesai mendahului tanggal mulai.', null];
        }

        if ($lama->selesai !== null && $mulai->lt($lama->selesai)) {
            return ['Perpanjangan mulai sebelum kontrak sebelumnya berakhir.', null];
        }

        $baru = null;

        DB::transaction(function () use ($lama, $isi, $oleh, $mulai, $selesai, &$baru) {
            self::akhiri($lama, 'selesai', $lama->selesai ?? $mulai->copy()->subDay());

            $baru = Kontrak::create([
                'company_id'          => $lama->company_id,
                'pekerja_id'          => $lama->pekerja_id,
                'nomor'               => $isi['nomor'],
                'jenis'               => $lama->jenis,
                'alasan'              => $isi['alasan'] ?? $lama->alasan,
                'mulai'               => $mulai->toDateString(),
                'selesai'             => $selesai?->toDateString(),
                'batasan_selesai'     => $isi['batasan_selesai'] ?? $lama->batasan_selesai,
                'induk_id'            => $lama->id,
                'urutan'              => $lama->urutan + 1,
                'masa_percobaan_hari' => 0,
                'status'              => 'draft',
                'catatan'             => $isi['catatan'] ?? null,
                'dibuat_oleh'         => $oleh->id,
            ]);
        });

        return [null, $baru];
    }

    /**
     * Akhiri kontrak dan hitung uang kompensasinya.
     *
     * `diputus` berarti berakhir sebelum waktunya; pasal 17 tetap
     * mewajibkan kompensasi, sebesar masa yang SUDAH DIJALANI. Karena
     * itu tanggal berakhirnya dicatat lebih dulu — KontrakPkwt membaca
     * `kompensasi_dibayar_pada` sebagai akhir nyatanya, dan tanpa itu ia
     * jatuh kembali pada tanggal yang dijanjikan.
     */
    public static function akhiri(Kontrak $k, string $status, Carbon|string|null $pada = null): ?string
    {
        if (! in_array($status, ['selesai', 'diputus', 'jadi_pkwtt'], true)) {
            return 'Status akhir tidak dikenal.';
        }

        if (! $k->hidup() && $k->status !== 'draft') {
            return 'Kontrak ini sudah '.Kontrak::STATUS[$k->status].'.';
        }

        $tanggal = $pada === null
            ? Waktu::kini()->toDateString()
            : ($pada instanceof Carbon ? $pada->toDateString() : (string) $pada);

        $k->forceFill([
            'status'                  => $status,
            'kompensasi_dibayar_pada' => $status === 'diputus' ? $tanggal : $k->kompensasi_dibayar_pada,
        ])->save();

        $hasil = KontrakPkwt::kompensasi($k->fresh());

        $k->forceFill([
            'kompensasi_upah'          => $hasil['berhak'] ? $hasil['upah'] : null,
            'kompensasi_bulan'         => $hasil['bulan'] ?: null,
            'kompensasi_nilai'         => $hasil['berhak'] ? $hasil['nilai'] : null,
            'kompensasi_dihitung_pada' => Waktu::kiniSimpan(),
        ])->save();

        return null;
    }

    /**
     * Tandai bahwa kontraknya sudah berubah menjadi PKWTT demi hukum.
     *
     * TIDAK MENGUBAH JENIS BARIS LAMA. Baris PKWT-nya tetap berdiri
     * sebagaimana ia ditandatangani, dan yang berdiri di sampingnya
     * adalah PKWTT baru yang menyebut sejak kapan dan karena apa.
     * Ditimpa di tempat, catatan tentang pelanggarannya ikut terhapus —
     * dan yang tersisa hanyalah seorang karyawan tetap tanpa riwayat.
     *
     * @return array{0:?string,1:?Kontrak}
     */
    public static function jadikanPkwtt(Kontrak $k, string $sebab, User $oleh): array
    {
        if (! $k->pkwt()) return ['Kontrak ini memang sudah PKWTT.', null];

        if ($k->status === 'jadi_pkwtt') return ['Perubahan ini sudah dicatat.', null];

        $baru = null;

        DB::transaction(function () use ($k, $sebab, $oleh, &$baru) {
            $sejak = self::sejakPkwtt($k);

            self::akhiri($k, 'jadi_pkwtt', $sejak->copy()->subDay());

            $baru = Kontrak::create([
                'company_id'  => $k->company_id,
                'pekerja_id'  => $k->pekerja_id,
                'nomor'       => $k->nomor.'-PKWTT',
                'jenis'       => 'pkwtt',
                'mulai'       => $sejak->toDateString(),
                'induk_id'    => $k->id,
                'urutan'      => $k->urutan + 1,
                'status'      => 'berjalan',
                'catatan'     => $sebab,
                'dibuat_oleh' => $oleh->id,
            ]);
        });

        return [null, $baru];
    }

    /**
     * Sejak kapan hubungan kerjanya menjadi PKWTT.
     *
     * Bukan hari ini, melainkan hari aturan itu terlampaui — sebab hak
     * yang mengikutinya terhitung sejak saat itu, bukan sejak seseorang
     * memperhatikannya.
     */
    private static function sejakPkwtt(Kontrak $k): Carbon
    {
        if ($k->jenis === 'pkwt_harian') {
            $bulan = KontrakPkwt::bulanHarianTerlampaui($k);

            if (count($bulan) >= KontrakPkwt::HARIAN_BULAN_BERUNTUN) {
                // Bulan ketiga berturut-turut: saat itulah pasal 10 ayat
                // (3) terpenuhi, bukan pada bulan terakhir yang tercatat.
                $ketiga = $bulan[KontrakPkwt::HARIAN_BULAN_BERUNTUN - 1];

                return Carbon::parse($ketiga.'-01')->endOfMonth()->startOfDay();
            }
        }

        $rantai = $k->rantai()->filter(fn (Kontrak $r) => $r->pkwt())->values();
        $lewat  = 0.0;

        foreach ($rantai as $r) {
            $bulan = KontrakPkwt::bulan($r->mulai, KontrakPkwt::akhirNyata($r));

            if ($lewat + $bulan > KontrakPkwt::MAKS_BULAN) {
                $sisa = KontrakPkwt::MAKS_BULAN - $lewat;

                return KontrakPkwt::tambahBulan($r->mulai, $sisa);
            }

            $lewat += $bulan;
        }

        return Waktu::kini()->startOfDay();
    }

    /**
     * Dua kontrak hidup yang rentangnya bertumpang tindih.
     *
     * Seorang pekerja hanya punya satu hubungan kerja pada satu saat.
     * Dua kontrak berjalan sekaligus bukan sekadar data yang rapi-rapi
     * salah: masa kerjanya terhitung dua kali, dan batas lima tahun
     * tercapai dua kali lebih cepat daripada yang sebenarnya.
     */
    private static function tabrakan(Kontrak $k): ?string
    {
        $lain = Kontrak::withoutGlobalScopes()
            ->where('company_id', $k->company_id)
            ->where('pekerja_id', $k->pekerja_id)
            ->where('id', '!=', $k->id)
            ->hidup()
            ->get();

        foreach ($lain as $l) {
            $mulaiL   = $l->mulai;
            $selesaiL = $l->selesai;

            $sesudahL = $selesaiL !== null && $k->mulai->gt($selesaiL);
            $sebelumL = $k->selesai !== null && $k->selesai->lt($mulaiL);

            if ($sesudahL || $sebelumL) continue;

            return "Bertumpang tindih dengan kontrak {$l->nomor} yang masih berjalan.";
        }

        return null;
    }
}
