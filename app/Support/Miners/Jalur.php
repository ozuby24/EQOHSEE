<?php

namespace App\Support\Miners;

use App\Models\Miners\Alur;
use App\Models\User;
use App\Support\Waktu;

/**
 * Menjalankan alur persetujuan satu dokumen Miners.
 *
 * SATU TEMPAT UNTUK LIMA JENIS DOKUMEN. MCU, induksi, Mine Permit,
 * SIMPER, dan pengajuan lanjutan menempuh alur yang bentuknya sama dan
 * hanya berbeda daftar langkahnya. Project1 menuliskannya sembilan
 * kali — sembilan tabel alur dengan sembilan controller — dan akibatnya
 * persis yang dapat diduga: langkah pengesahan KTT ada pada jalur
 * SIMPER tetapi tidak pernah ditambahkan pada jalur Mine Permit, dan
 * tidak ada satu galat pun yang menandainya selama bertahun-tahun.
 *
 * YANG MENYETUJUI TIDAK BOLEH ORANG YANG MENGAJUKAN. Ditegakkan di
 * sini, bukan diserahkan pada layar: tombolnya dapat disembunyikan,
 * tetapi permintaannya tetap dapat dikirim.
 */
final class Jalur
{
    /**
     * Setujui langkah yang sedang berjalan.
     *
     * @return string|null alasan penolakan, atau null bila berhasil
     */
    public static function setujui(object $dokumen, User $oleh, ?string $catatan = null): ?string
    {
        return self::tindak($dokumen, $oleh, 'setuju', $catatan);
    }

    public static function tolak(object $dokumen, User $oleh, ?string $catatan = null): ?string
    {
        return self::tindak($dokumen, $oleh, 'tolak', $catatan);
    }

    public static function kembalikan(object $dokumen, User $oleh, ?string $catatan = null): ?string
    {
        return self::tindak($dokumen, $oleh, 'dikembalikan', $catatan);
    }

    /**
     * @param  'setuju'|'tolak'|'dikembalikan'  $keadaan
     * @return string|null alasan penolakan, atau null bila berhasil
     */
    public static function tindak(object $dokumen, User $oleh, string $keadaan, ?string $catatan = null): ?string
    {
        $dokumen->terbitkanAlur();
        $dokumen->load('alur');

        $langkah = $dokumen->langkahBerjalan();

        if ($langkah === null) {
            return 'Tidak ada langkah yang menunggu tindakan.';
        }

        /* Yang mengajukan tidak boleh menyetujui pengajuannya sendiri.
           Ditegakkan di sini, bukan di layar — tombol yang
           disembunyikan tetap dapat dikirim permintaannya, dan
           persetujuan sendiri pada dokumen yang dibawa ke gerbang
           adalah persis yang diperiksa auditor. */
        if (! $oleh->isAdmin() && $dokumen->user_id && (int) $dokumen->user_id === (int) $oleh->getKey()) {
            return 'Pengaju tidak dapat menyetujui pengajuannya sendiri.';
        }

        $langkah->update([
            'keadaan'        => $keadaan,
            'user_id'        => $oleh->getKey(),
            'bertindak_pada' => Waktu::kini(),
            'catatan'        => $catatan,
        ]);

        $dokumen->load('alur');
        $dokumen->forceFill(['status' => self::status($dokumen, $keadaan)])->save();

        return null;
    }

    /**
     * Status dokumen sesudah sebuah langkah ditindak.
     *
     * DITURUNKAN DARI ALURNYA, bukan disimpan terpisah. Status yang
     * ditulis sendiri oleh tiap pemanggil akan menyimpang dari alurnya
     * cepat atau lambat — dan yang menyimpang adalah dokumen yang
     * tampil "terbit" di layar sementara satu langkahnya masih
     * menunggu.
     */
    private static function status(object $dokumen, string $keadaan): string
    {
        if ($keadaan === 'tolak')        return 'ditolak';
        if ($keadaan === 'dikembalikan') return 'draf';

        if ($dokumen->alurTuntas()) {
            /* MCU dan induksi TIDAK "terbit" — keduanya menghasilkan
               hasil pemeriksaan, bukan kartu. Menyebutnya terbit membuat
               daftar kartu berlaku ikut menghitungnya. */
            return in_array($dokumen::jenisDokumen(), ['mcu', 'induksi'], true) ? 'selesai' : 'terbit';
        }

        $berikut = $dokumen->langkahBerjalan();

        return match ($berikut?->peran) {
            'dokter' => 'diperiksa',
            'ohse'   => 'ohse',
            'ktt'    => 'ktt',
            default  => 'diajukan',
        };
    }

    /**
     * Peran seorang pengguna pada alur Miners.
     *
     * Dibaca dari jabatannya di aplikasi, bukan dari daftar tersendiri:
     * daftar kedua akan menyimpang dari yang pertama, dan yang
     * menyimpang adalah orang yang kehilangan antreannya tanpa tahu
     * sebabnya.
     *
     * @return list<string>
     */
    public static function peran(?User $u): array
    {
        if (! $u) return [];

        if ($u->isAdmin()) return array_keys(Alur::PERAN);

        $peran = [];

        /* Dibaca dari peran yang SUDAH ada di aplikasi, bukan dari
           daftar izin tersendiri. Daftar kedua akan menyimpang dari
           yang pertama, dan yang menyimpang adalah orang yang
           kehilangan antreannya tanpa tahu sebabnya. */
        if ($u->isParamedis()) $peran[] = 'dokter';
        if ($u->isOhse())      $peran[] = 'ohse';
        if ($u->isKtt())       $peran[] = 'ktt';

        /* Tanpa satu peran pun, yang tersisa adalah peran pengaju.
           Memulangkan daftar kosong membuat layar menyembunyikan
           SELURUH tombol — termasuk tombol mengajukan — sehingga mitra
           kerja tidak dapat mengajukan apa pun. */
        return $peran === [] ? ['pjo'] : $peran;
    }
}
