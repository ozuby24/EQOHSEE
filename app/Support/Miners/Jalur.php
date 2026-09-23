<?php

namespace App\Support\Miners;

use App\Models\Miners\Alur;
use App\Models\User;
use App\Notifications\AlurMinersBerpindah;
use App\Support\Waktu;
use Illuminate\Support\Collection;

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
     * Ajukan ulang pengajuan yang DIKEMBALIKAN.
     *
     * Sebelum ini, dokumen yang dikembalikan hanya turun statusnya
     * menjadi 'draf' dan berhenti di sana: alurnya tetap menyimpan
     * langkah 'dikembalikan', tidak ada langkah yang menunggu siapa
     * pun, dan tidak ada satu pun tindakan yang dapat menjalankannya
     * lagi. Yang memperbaiki berkasnya tidak punya cara memberitahu
     * bahwa ia sudah diperbaiki — ia menelepon OHSE, dan OHSE membuka
     * pengajuan baru. Safe Track menyebut tindakan ini resubmit.
     *
     * YANG DITOLAK TIDAK DAPAT DIAJUKAN ULANG. Ditolak dan
     * dikembalikan bukan dua kata untuk satu hal: yang dikembalikan
     * kurang lengkap dan diminta dilengkapi, yang ditolak sudah
     * diputus tidak memenuhi syarat. Membiarkan yang ditolak diajukan
     * ulang lewat pintu ini membuat keputusan menolak tidak berarti
     * apa-apa — cukup ditekan sekali lagi.
     *
     * @return string|null alasan penolakan, atau null bila berhasil
     */
    public static function ajukanUlang(object $dokumen, User $oleh): ?string
    {
        $dokumen->terbitkanAlur();
        $dokumen->load('alur');

        if (! $dokumen->dikembalikan()) {
            return 'Hanya pengajuan yang dikembalikan yang dapat diajukan ulang.';
        }

        /* Yang mengajukan ulang haruslah pihak pengaju, bukan peninjau
           yang mengembalikannya. Peninjau yang dapat mengajukan ulang
           sendiri berarti ia menyetujui berkas yang tidak pernah
           diperbaiki siapa pun. */
        if (! $oleh->isAdmin() && $dokumen->user_id && (int) $dokumen->user_id !== (int) $oleh->getKey()) {
            return 'Hanya pengaju yang dapat mengajukan ulang berkas ini.';
        }

        $dokumen->ulangAlur();
        $dokumen->forceFill(['status' => 'diajukan'])->save();

        self::beriTahu($dokumen, 'setuju', $oleh, null);

        return null;
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

        /* Kelengkapan lampiran ditegakkan pada langkah PJO — yaitu saat
           mitra kerja MENGIRIM berkasnya ke OHSE — bukan pada langkah
           OHSE.
           Pembedaan itu menentukan siapa yang terhalang. Ditegakkan di
           OHSE, yang terhalang adalah orang yang TIDAK DAPAT
           memperbaikinya: lampirannya diunggah mitra kerja, bukan OHSE,
           sehingga OHSE hanya dapat menatap tombol yang menolak ditekan.
           Ditegakkan di PJO, yang terhalang justru orang yang memegang
           berkasnya, dan pesan kurangnya langsung dapat ditindaklanjuti.
           Itu pula yang sudah lama dijanjikan Acuan::BERKAS_WAJIB:
           "tombol kirim ke OHSE baru terbuka setelah yang wajib
           terpenuhi".
           Menolak dan MENGEMBALIKAN tetap boleh meski kurang: itulah
           tindakan yang tepat bagi berkas yang tidak lengkap. */
        if ($keadaan === 'setuju' && $langkah->peran === 'pjo'
            && ($kurang = Kelengkapan::kurang($dokumen)) !== []) {
            return 'Lampiran wajib menurut SOP belum lengkap: '
                .implode('; ', array_slice($kurang, 0, 3))
                .(count($kurang) > 3 ? ', dan '.(count($kurang) - 3).' lagi.' : '.');
        }

        $langkah->update([
            'keadaan'        => $keadaan,
            'user_id'        => $oleh->getKey(),
            /* UTC, bukan WITA — lihat Waktu::simpan(). Disimpan apa
               adanya, jam persetujuan tiap langkah alur terbaca delapan
               jam lebih lambat daripada yang sebenarnya. */
            'bertindak_pada' => Waktu::kiniSimpan(),
            'catatan'        => $catatan,
        ]);

        $dokumen->load('alur');
        $dokumen->forceFill(['status' => self::status($dokumen, $keadaan)])->save();

        self::beriTahu($dokumen, $keadaan, $oleh, $catatan);

        return null;
    }

    /**
     * Surati pihak yang perlu tahu bahwa alurnya berpindah.
     *
     * DIBUNGKUS try/catch, dan itu bukan kemalasan: SMTP yang mati
     * membuat seluruh persetujuan gagal bila galatnya dibiarkan naik —
     * artinya satu server surel yang bermasalah menghentikan penerbitan
     * Mine Permit di gerbang. Persetujuannya sendiri sudah tersimpan
     * pada baris di atas; pemberitahuan yang gagal dicatat di log dan
     * tidak menarik apa pun ikut gagal.
     */
    private static function beriTahu(object $dokumen, string $keadaan, User $oleh, ?string $catatan): void
    {
        try {
            $pemberitahuan = fn (string $k) => new AlurMinersBerpindah(
                $dokumen::jenisDokumen(),
                (int) $dokumen->getKey(),
                self::nomor($dokumen),
                $k,
                $oleh->name,
                $catatan,
            );

            /* Ditolak atau dikembalikan: yang perlu tahu PENGAJUNYA,
               sebab dialah yang harus bertindak. Langkah berikutnya
               tidak ada, jadi tidak ada giliran yang tiba. */
            if (in_array($keadaan, ['tolak', 'dikembalikan'], true)) {
                self::pengaju($dokumen)?->notify($pemberitahuan($keadaan));

                return;
            }

            $berikut = $dokumen->langkahBerjalan();

            /* Masih ada giliran berikutnya: yang disurati pemegang peran
               itu saja. Dikirim ke semua orang, surel ini akan dibaca
               tidak oleh siapa pun. */
            if ($berikut !== null) {
                foreach (self::pemegangPeran($berikut->peran, $dokumen) as $u) {
                    $u->notify($pemberitahuan('menunggu'));
                }

                return;
            }

            /* Tidak ada lagi giliran — alurnya tuntas. Yang menunggu
               kabar ini pengajunya. */
            self::pengaju($dokumen)?->notify($pemberitahuan('setuju'));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function pengaju(object $dokumen): ?User
    {
        return $dokumen->user_id ? User::find($dokumen->user_id) : null;
    }

    private static function nomor(object $dokumen): ?string
    {
        return $dokumen->no_registrasi ?? $dokumen->no_simper ?? null;
    }

    /**
     * Pengguna yang memegang sebuah peran alur, dalam perusahaan dokumen ini.
     *
     * DIBATASI PERUSAHAAN. Tanpa batas itu, OHSE perusahaan lain ikut
     * disurati tiap kali sebuah pengajuan berpindah — dan isi surelnya
     * menyebut nomor dokumen serta nama pemegangnya.
     *
     * Admin TIDAK ikut disurati hanya karena ia admin. Jalur::peran()
     * memberi admin seluruh peran supaya ia dapat menolong bila
     * antreannya tersendat, tetapi memakai daftar yang sama di sini akan
     * menyurati setiap admin pada setiap perpindahan setiap dokumen.
     *
     * @return \Illuminate\Support\Collection<int,User>
     */
    private static function pemegangPeran(string $peran, object $dokumen): Collection
    {
        $kolom = match ($peran) {
            'dokter' => ['ohse_role', 'paramedis'],
            'ohse'   => ['ohse_role', 'ohse'],
            'ktt'    => ['lms_role', 'ktt'],
            default  => null,
        };

        /* Peran 'pjo' tidak punya kolomnya sendiri — ia peran sisa bagi
           yang tidak memegang peran lain. Yang dimaksud pada dokumen
           tertentu adalah pengajunya, dan itu yang disurati. */
        if ($kolom === null) {
            $p = self::pengaju($dokumen);

            return $p ? collect([$p]) : collect();
        }

        [$medan, $nilai] = $kolom;

        return User::query()
            ->where($medan, $nilai)
            ->when($dokumen->company_id, fn ($q, $c) => $q->where('company_id', $c))
            ->whereNotNull('email')
            ->get();
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
