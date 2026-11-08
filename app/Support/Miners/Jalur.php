<?php

namespace App\Support\Miners;

use App\Models\Miners\Alur;
use App\Models\User;
use App\Notifications\AlurMinersBerpindah;
use App\Support\Waktu;
use Illuminate\Support\Facades\Notification;

/**
 * Menjalankan alur persetujuan satu dokumen Miners — perilaku Safe Track.
 *
 * SATU TEMPAT UNTUK SELURUH JENIS DOKUMEN. Safe Track menuliskannya
 * berulang di tiap controller, dan salinannya menyimpang: persetujuan
 * HSE pernah menutup dokumen sebelum KTT menandatanganinya. Aturannya
 * ditiru, salinannya tidak.
 *
 * ATURAN YANG DITEGAKKAN DI SINI, BUKAN DI LAYAR (tombol dapat
 * disembunyikan, permintaannya tetap dapat dikirim):
 *
 *   · KIRIM hanya oleh pengajunya (atau admin), dan hanya bila syarat
 *     kirimnya terpenuhi serta penyetuju IUP-nya sudah ditentukan.
 *   · SETUJU / TOLAK / KEMBALIKAN hanya oleh orang yang DITUGASI pada
 *     langkah yang sedang giliran — `flow.users_id === Auth::id()` Safe
 *     Track. Admin boleh bertindak atas namanya.
 *   · Pengaju tidak menyetujui pengajuannya sendiri (kecuali admin).
 *   · TOLAK dan KEMBALIKAN menuntut alasan. Keduanya mengembalikan
 *     berkas kepada pengajunya, dan keduanya dapat dikirim ulang —
 *     Safe Track tidak punya penolakan yang final; yang membedakan
 *     hanyalah statusnya: "ditolak" tetap tercatat sebagai penolakan,
 *     "dikembalikan" kembali menjadi draf untuk dilengkapi.
 *
 * PEMBERITAHUAN BERSIFAT USAHA-TERBAIK. Pekerjaannya sudah tercatat
 * sebelum surel dikirim; yang gagal hanya pemberitahuannya, dan itu
 * DIKATAKAN lewat pesanSurel() — terutama bila penerimanya memang tidak
 * ada, keadaan yang dapat diperbaiki orang yang sedang menatap layar.
 */
final class Jalur
{
    /** Pesan pemberitahuan dari tindakan terakhir, atau null bila beres. */
    private static ?string $pesanSurel = null;

    public static function pesanSurel(): ?string
    {
        return self::$pesanSurel;
    }

    /* ═══════════════════ tindakan ═══════════════════ */

    /**
     * Kirim berkas ke penyetuju pertama — langkah pengaju disetujui.
     *
     * Dipakai pula untuk MENGIRIM ULANG berkas yang ditolak atau
     * dikembalikan: alurnya diulang dari awal.
     *
     * @return string|null alasan penolakan, atau null bila berhasil
     */
    public static function kirim(object $dokumen, User $oleh): ?string
    {
        self::$pesanSurel = null;

        $dokumen->terbitkanAlur();
        $dokumen->load('alur');

        if (! self::milikPengaju($dokumen, $oleh)) {
            return 'Hanya pengaju yang dapat mengirim berkas ini.';
        }

        if ($dokumen->alurTuntas()) {
            return 'Seluruh tahap sudah disetujui; tidak ada yang perlu dikirim.';
        }

        $berjalan = $dokumen->langkahBerjalan();

        if ($berjalan !== null && $berjalan->peran !== 'pengaju') {
            return 'Berkas ini sudah dikirim dan sedang menunggu '
                .self::labelLangkah($dokumen, $berjalan).'.';
        }

        if (($kurang = self::syaratKirim($dokumen)) !== []) {
            return 'Belum dapat dikirim: '.implode('; ', array_slice($kurang, 0, 3))
                .(count($kurang) > 3 ? ', dan '.(count($kurang) - 3).' lagi.' : '.');
        }

        $iup = $dokumen->iup_id ? (int) $dokumen->iup_id : null;

        if ($pesan = Penyetuju::pesanBelumSiap($iup, $dokumen->rantaiAlur())) {
            return $pesan;
        }

        /* Berkas yang pernah berjalan diulang dari awal; berkas yang baru
           pertama kali dikirim cukup dibaca ulang penyetujunya. */
        if ($dokumen->alur->contains(fn (Alur $a) => $a->keadaan !== 'menunggu')) {
            /* Dokumen yang punya pekerjaan tambahan saat diulang — MCU
               mengembalikan seluruh hasilnya ke "Belum Dinilai". */
            if (method_exists($dokumen, 'saatDiulang')) $dokumen->saatDiulang();

            $dokumen->ulangAlur();
        } else {
            $dokumen->tugaskanUlang();
            $dokumen->load('alur');
        }

        $dokumen->langkahPengaju()?->update([
            'keadaan'            => 'setuju',
            'user_id'            => $oleh->getKey(),
            'ditugaskan_user_id' => $dokumen->user_id ?? $oleh->getKey(),
            'bertindak_pada'     => Waktu::kiniSimpan(),
            'catatan'            => null,
        ]);

        $dokumen->load('alur');
        $dokumen->forceFill(['status' => self::status($dokumen)])->save();

        self::beriTahu($dokumen, 'setuju', $oleh, null);

        return null;
    }

    /**
     * Kirim ulang berkas yang ditolak atau dikembalikan.
     *
     * @return string|null
     */
    public static function ajukanUlang(object $dokumen, User $oleh): ?string
    {
        $dokumen->terbitkanAlur();
        $dokumen->load('alur');

        if (! $dokumen->alurTertahan() && $dokumen->sudahDikirim()) {
            return 'Hanya pengajuan yang ditolak atau dikembalikan yang dapat diajukan ulang.';
        }

        return self::kirim($dokumen, $oleh);
    }

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
        self::$pesanSurel = null;

        $dokumen->terbitkanAlur();
        $dokumen->load('alur');

        $langkah = $dokumen->langkahBerjalan();

        if ($langkah === null) {
            return $dokumen->alurTertahan()
                ? 'Berkas ini sudah dikembalikan kepada pengajunya; menunggu dikirim ulang.'
                : 'Tidak ada langkah yang menunggu tindakan.';
        }

        /* Langkah pertama milik pengaju, dan tindakannya adalah KIRIM. */
        if ($langkah->peran === 'pengaju') {
            return $keadaan === 'setuju'
                ? self::kirim($dokumen, $oleh)
                : 'Berkas ini belum dikirim pengajunya.';
        }

        if ($salah = self::bukanGiliran($dokumen, $langkah, $oleh)) {
            return $salah;
        }

        $catatan = trim((string) $catatan) ?: null;

        if ($keadaan !== 'setuju' && $catatan === null) {
            return $keadaan === 'tolak'
                ? 'Alasan penolakan wajib diisi.'
                : 'Alasan pengembalian wajib diisi.';
        }

        /* Syarat tambahan dokumen sebelum langkahnya disetujui — mis.
           MCU yang masih punya nama "Belum Dinilai". Dokumen yang tidak
           menyatakannya tidak punya syarat tambahan. */
        if ($keadaan === 'setuju' && method_exists($dokumen, 'syaratSetuju')
            && ($kurang = $dokumen->syaratSetuju($langkah)) !== []) {
            return implode(' ', $kurang);
        }

        $langkah->update([
            'keadaan'        => $keadaan,
            'user_id'        => $oleh->getKey(),
            /* UTC, bukan WITA — lihat Waktu::simpan(). */
            'bertindak_pada' => Waktu::kiniSimpan(),
            'catatan'        => $catatan,
        ]);

        $dokumen->load('alur');
        $dokumen->forceFill(['status' => self::status($dokumen)])->save();

        /* Pekerjaan tambahan sesudah SATU langkah disetujui — mis.
           perpanjangan menulis balik MCU begitu dokter menyetujuinya. */
        if ($keadaan === 'setuju' && method_exists($dokumen, 'setelahLangkahDisetujui')) {
            $dokumen->setelahLangkahDisetujui($langkah, $oleh);
        }

        if ($keadaan === 'setuju' && $dokumen->alurTuntas() && method_exists($dokumen, 'setelahAlurTuntas')) {
            $dokumen->setelahAlurTuntas($oleh);
        }

        self::beriTahu($dokumen, $keadaan, $oleh, $catatan);

        return null;
    }

    /* ═══════════════════ hak ═══════════════════ */

    /** Pengguna ini boleh menekan "Kirim" / "Ajukan Ulang". */
    public static function bolehKirim(object $dokumen, ?User $u): bool
    {
        if (! $u || ! self::milikPengaju($dokumen, $u) || $dokumen->alurTuntas()) return false;

        $berjalan = $dokumen->langkahBerjalan();

        return $berjalan === null ? $dokumen->alurTertahan() : $berjalan->peran === 'pengaju';
    }

    /** Pengguna ini boleh menyetujui / menolak / mengembalikan sekarang. */
    public static function bolehTindak(object $dokumen, ?User $u): bool
    {
        if (! $u) return false;

        $langkah = $dokumen->langkahBerjalan();

        return $langkah !== null && $langkah->peran !== 'pengaju'
            && self::bukanGiliran($dokumen, $langkah, $u) === null;
    }

    /**
     * Alasan pengguna ini TIDAK boleh menindak langkah ini, atau null.
     *
     * Hanya yang ditugasi — penyetuju bernama pada skema IUP. Admin
     * boleh bertindak atas namanya, supaya antrean yang tersendat
     * karena orangnya berhalangan tetap dapat bergerak; namanya
     * tercatat sebagai yang menindak, terpisah dari yang ditugasi.
     */
    private static function bukanGiliran(object $dokumen, Alur $langkah, User $u): ?string
    {
        if ($u->isAdmin()) return null;

        if ($dokumen->user_id && (int) $dokumen->user_id === (int) $u->getKey()) {
            return 'Pengaju tidak dapat menyetujui pengajuannya sendiri.';
        }

        if (! $langkah->ditugaskan_user_id) {
            return 'Penyetuju langkah '.(Alur::PERAN[$langkah->peran] ?? $langkah->peran)
                .' belum ditentukan. Atur di Miners → Alur Persetujuan.';
        }

        if ((int) $langkah->ditugaskan_user_id !== (int) $u->getKey()) {
            return 'Persetujuan ini bukan giliran Anda.';
        }

        return null;
    }

    private static function milikPengaju(object $dokumen, User $u): bool
    {
        return $u->isAdmin() || ! $dokumen->user_id || (int) $dokumen->user_id === (int) $u->getKey();
    }

    /**
     * Yang masih kurang sebelum berkas boleh dikirim — gerbang tombol
     * "Sending" Safe Track: MCU ≥1 manpower, induksi ≥1 peserta, SIMPER
     * dan penambahan unit ≥1 unit, Mine Permit lampiran wajibnya.
     *
     * @return list<string>
     */
    public static function syaratKirim(object $dokumen): array
    {
        if (method_exists($dokumen, 'syaratKirim')) {
            return $dokumen->syaratKirim();
        }

        return match ($dokumen::jenisDokumen()) {
            'mcu'     => $dokumen->orang()->exists() ? [] : ['belum ada satu pun nama manpower'],
            'induksi' => $dokumen->orang()->exists() ? [] : ['belum ada satu pun peserta'],
            'simper'  => $dokumen->unit()->exists() ? [] : ['belum ada satu pun unit'],
            'permit'  => array_map(fn ($l) => 'lampiran wajib "'.$l.'" belum diunggah', Kelengkapan::kurang($dokumen)),
            default   => [],
        };
    }

    /* ═══════════════════ status ═══════════════════ */

    /**
     * Status dokumen menurut alurnya.
     *
     * DITURUNKAN DARI ALURNYA, bukan disimpan terpisah oleh tiap
     * pemanggil — status yang ditulis sendiri-sendiri akan menyimpang,
     * dan yang menyimpang adalah dokumen yang tampil "terbit" sementara
     * satu langkahnya masih menunggu.
     */
    public static function status(object $dokumen): string
    {
        if ($dokumen->ditolakAlur())   return 'ditolak';
        if ($dokumen->dikembalikan())  return 'draf';
        if (! $dokumen->sudahDikirim()) return 'draf';

        if ($dokumen->alurTuntas()) {
            /* MCU, induksi, dan pengajuan lanjutan menghasilkan hasil
               pemeriksaan atau perubahan kartu, bukan kartu baru;
               menyebutnya terbit membuat daftar kartu berlaku ikut
               menghitungnya. */
            return in_array($dokumen::jenisDokumen(), ['permit', 'simper'], true) ? 'terbit' : 'selesai';
        }

        return match ($dokumen->langkahBerjalan()?->peran) {
            'dokter' => 'diperiksa',
            'ohse'   => 'ohse',
            'ktt'    => 'ktt',
            default  => 'diajukan',
        };
    }

    /**
     * Kalimat "sedang nyangkut di mana" — posisiSaatIni Safe Track.
     *
     * @return array{pesan:string,nada:string}
     */
    public static function posisi(object $dokumen): array
    {
        $alur = $dokumen->alur;

        if ($alur->isEmpty()) {
            return ['pesan' => 'Alur persetujuan belum tersusun.', 'nada' => 'tunggu'];
        }

        if ($t = $alur->firstWhere('keadaan', 'tolak')) {
            return ['pesan' => 'Ditolak oleh '.self::labelLangkah($dokumen, $t)
                .'. Pengaju dapat memperbaiki lalu mengajukan ulang.', 'nada' => 'tolak'];
        }

        if ($k = $alur->firstWhere('keadaan', 'dikembalikan')) {
            return ['pesan' => 'Dikembalikan oleh '.self::labelLangkah($dokumen, $k)
                .'. Menunggu pengaju melengkapi dan mengirim ulang.', 'nada' => 'kembali'];
        }

        if ($dokumen->alurTuntas()) {
            return ['pesan' => 'Seluruh tahap sudah disetujui.', 'nada' => 'setuju'];
        }

        $l = $dokumen->langkahBerjalan();

        if ($l === null || $l->peran === 'pengaju') {
            return ['pesan' => 'Belum dikirim. Pengaju menekan "Kirim" untuk memulai persetujuan.', 'nada' => 'tunggu'];
        }

        $nama = $l->ditugaskan?->name;

        return [
            'pesan' => 'Sedang menunggu tindakan dari '.self::labelLangkah($dokumen, $l)
                .($nama ? ' ('.$nama.')' : ' — penyetuju belum ditentukan').'.',
            'nada'  => 'proses',
        ];
    }

    /** "HSE PT X" untuk satu langkah. */
    public static function labelLangkah(object $dokumen, Alur $l): string
    {
        return Penyetuju::labelPeran(
            $l->peran,
            $dokumen->iup_id ? (int) $dokumen->iup_id : null,
            $l->peran === 'pengaju' ? $dokumen->user : null,
        );
    }

    /* ═══════════════════ pemberitahuan ═══════════════════ */

    /**
     * Surati pihak yang perlu tahu bahwa alurnya berpindah.
     *
     *   · ditolak / dikembalikan → pengajunya (alasannya ikut)
     *   · berlanjut              → yang ditugasi langkah berikutnya,
     *                              penerima tambahan ("to"), dan tembusan
     *   · tuntas                 → pengajunya
     */
    private static function beriTahu(object $dokumen, string $keadaan, User $oleh, ?string $catatan): void
    {
        $iup = $dokumen->iup_id ? (int) $dokumen->iup_id : null;

        $buat = fn (string $k, array $cc = []) => new AlurMinersBerpindah(
            $dokumen::jenisDokumen(),
            (int) $dokumen->getKey(),
            self::nomor($dokumen),
            $k,
            $oleh->name,
            $catatan,
            $cc,
            RincianDokumen::tautan($dokumen),
        );

        $tembusan = Penyetuju::pecahEmail((string) config('miners.surel.tembusan'));

        try {
            if (in_array($keadaan, ['tolak', 'dikembalikan'], true) || $dokumen->alurTuntas()) {
                $pengaju = self::pengaju($dokumen);

                if (! $pengaju?->email) {
                    self::$pesanSurel = self::penerimaHilang();

                    return;
                }

                $pengaju->notify($buat($dokumen->alurTuntas() ? 'setuju' : $keadaan, $tembusan));

                return;
            }

            $berikut = $dokumen->langkahBerjalan();
            if ($berikut === null) return;

            $tugas = $berikut->ditugaskan_user_id ? User::find($berikut->ditugaskan_user_id) : null;
            $to    = Penyetuju::penerima($iup, $berikut->peran, 'to');
            $cc    = array_values(array_diff(
                array_unique(array_merge(Penyetuju::penerima($iup, $berikut->peran, 'cc'), $tembusan)),
                array_merge($to, [mb_strtolower((string) $tugas?->email)]),
            ));

            if ($tugas?->email) {
                $tugas->notify($buat('menunggu', $cc));
            }

            foreach (array_diff($to, [mb_strtolower((string) $tugas?->email)]) as $alamat) {
                Notification::route('mail', $alamat)->notify($buat('menunggu', $cc));
            }

            if (! $tugas?->email) {
                self::$pesanSurel = self::penerimaHilang();
            }
        } catch (\Throwable $e) {
            report($e);

            self::$pesanSurel = 'Pekerjaan Anda SUDAH tercatat. Namun pemberitahuan surel tidak terkirim '
                .'(server surel tidak dapat dihubungi). Tidak perlu mengulang; beri tahu penerimanya '
                .'secara terpisah bila mendesak.';
        }
    }

    /** Kalimat ketika penerima pemberitahuan memang tidak ada — penerimaHilang() Safe Track. */
    public static function penerimaHilang(): string
    {
        return 'Pekerjaan Anda SUDAH tercatat. Namun pemberitahuan tidak terkirim: penerima langkah '
            .'berikutnya tidak ditemukan pada alur dokumen ini. Periksa Miners → Alur Persetujuan, '
            .'lalu beri tahu penerimanya secara terpisah bila mendesak.';
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
     * Peran seorang pengguna pada alur Miners, dibaca dari jabatannya.
     *
     * BUKAN lagi penentu siapa yang boleh menyetujui — itu kini
     * penyetuju bernama pada skema IUP. Tetap dipakai layar untuk
     * memilih tampilan (meja peninjau atau meja pengaju) dan sebagai
     * saran saat menyusun skema.
     *
     * @return list<string>
     */
    public static function peran(?User $u): array
    {
        if (! $u) return [];

        if ($u->isAdmin()) return array_keys(Alur::PERAN);

        $peran = [];

        if ($u->isParamedis()) $peran[] = 'dokter';
        if ($u->isOhse())      $peran[] = 'ohse';
        if ($u->isKtt())       $peran[] = 'ktt';

        return $peran === [] ? ['pengaju'] : $peran;
    }
}
