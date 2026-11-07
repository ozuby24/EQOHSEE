<?php

namespace App\Support\Miners;

use App\Models\User;

/**
 * Siapa boleh mengubah apa pada modul Miners — ditegakkan di server.
 *
 * Sebelum ini isian hasil MCU, nilai induksi, suntingan permit dan
 * SIMPER, serta baris unit tidak punya penjagaan peran sama sekali:
 * siapa pun yang masuk dan dapat melihat dokumennya dapat mengubahnya
 * dengan mengirim permintaannya langsung. Aturannya mengikuti Safe Track:
 *
 *   · KELOLA — "admin" Safe Track: administrator aplikasi, atau HSE pada
 *     perusahaan IUP (bukan mitra). Menyunting permit dan SIMPER yang
 *     sudah berjalan, mencabut, master data, dan alur persetujuan.
 *   · UBAH DRAF — pengajunya, selama berkasnya belum dikirim atau sedang
 *     dikembalikan/ditolak. Sesudah dikirim, isinya milik penyetuju.
 *   · PENYETUJU LANGKAH — penilaian medis hanya oleh dokter yang
 *     ditugasi pada surat MCU itu; penilaian induksi oleh HSE yang
 *     ditugasi (`bolehNilai` Safe Track).
 */
final class AksesMiners
{
    /** "Admin" Safe Track: administrator, atau HSE perusahaan IUP. */
    public static function kelola(?User $u): bool
    {
        if (! $u) return false;
        if ($u->isAdmin()) return true;

        return $u->isOhse() && $u->company_id && ! $u->company?->parent_id && ! $u->company?->isJasa();
    }

    /** Pengajunya, selama berkas belum dikirim atau sedang dikembalikan. */
    public static function ubahDraf(object $dokumen, ?User $u): bool
    {
        if (! $u) return false;
        if ($u->isAdmin()) return true;

        $milik = ! $dokumen->user_id || (int) $dokumen->user_id === (int) $u->getKey();

        return $milik && in_array($dokumen->status, ['draf', 'ditolak'], true);
    }

    /** Orang yang ditugasi pada langkah berperan ini di dokumen tersebut. */
    public static function penyetujuLangkah(object $dokumen, ?User $u, string $peran): bool
    {
        if (! $u) return false;
        if ($u->isAdmin()) return true;

        $dokumen->loadMissing('alur');

        return $dokumen->alur->contains(fn ($a) => $a->peran === $peran
            && (int) $a->ditugaskan_user_id === (int) $u->getKey());
    }

    /** Hentikan permintaan dengan 403 bila syaratnya tidak terpenuhi. */
    public static function tegakkan(bool $boleh, string $pesan = 'Anda tidak berwenang mengubah berkas ini.'): void
    {
        abort_unless($boleh, 403, $pesan);
    }
}
