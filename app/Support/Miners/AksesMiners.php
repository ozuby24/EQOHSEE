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

    /**
     * Perusahaan pemberi kerja yang boleh dipilih pengguna ini —
     * `pilihanPerusahaan()` Safe Track. Akun mitra hanya perusahaannya
     * sendiri; akun IUP perusahaannya dan mitra-mitranya; admin seluruhnya
     * (atau yang sedang ia lihat).
     *
     * @return \Illuminate\Support\Collection<int,\App\Models\Company>
     */
    public static function perusahaan(?User $u)
    {
        $q = \App\Models\Company::query()->orderBy('name');

        if (! $u) return collect();

        if (! $u->isAdmin()) {
            $milik = $u->company;

            if ($milik?->parent_id || $milik?->isJasa()) return $q->whereKey($u->company_id)->get();

            return $q->where(fn ($w) => $w->whereKey($u->company_id)->orWhere('parent_id', $u->company_id))->get();
        }

        if ($lihat = \App\Support\Perusahaan::terpilih($u)) {
            $q->where(fn ($w) => $w->whereKey($lihat)->orWhere('parent_id', $lihat));
        }

        return $q->get();
    }

    /**
     * Boleh membuka halaman rincian seorang pekerja.
     *
     * Admin dan HSE perusahaan IUP: seluruh pekerja dalam jangkauannya
     * (lingkup perusahaan dijaga global scope). Pengguna biasa: hanya
     * data miliknya — pekerja yang ia daftarkan, atau yang MCU, Mine
     * Permit, atau SIMPER-nya ia ajukan (register "Saya" Safe Track) —
     * serta penyetuju yang ditugasi pada salah satu dokumennya.
     */
    public static function lihatPekerja(\App\Models\Miners\Pekerja $p, ?User $u): bool
    {
        if (! $u) return false;
        if (self::kelola($u)) return true;
        $id = (int) $u->getKey();
        if ((int) $p->user_id === $id) return true;

        $mcu    = \App\Models\Miners\McuOrang::withoutGlobalScopes()->where('pekerja_id', $p->id)->pluck('mcu_id');
        $permit = \App\Models\Miners\Permit::withoutGlobalScopes()->where('pekerja_id', $p->id)->get(['id', 'user_id']);
        $simper = \App\Models\Miners\Simper::withoutGlobalScopes()->where('pekerja_id', $p->id)->get(['id', 'user_id']);

        if ($permit->contains('user_id', $id) || $simper->contains('user_id', $id)) return true;
        if (\App\Models\Miners\Mcu::withoutGlobalScopes()->whereIn('id', $mcu)->where('user_id', $id)->exists()) return true;

        return \App\Models\Miners\Alur::query()->where('ditugaskan_user_id', $id)
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('dokumen', 'mcu')->whereIn('dokumen_id', $mcu))
                ->orWhere(fn ($w) => $w->where('dokumen', 'permit')->whereIn('dokumen_id', $permit->pluck('id')))
                ->orWhere(fn ($w) => $w->where('dokumen', 'simper')->whereIn('dokumen_id', $simper->pluck('id'))))
            ->exists();
    }

    /**
     * Boleh membaca isi medis — hasil/kategori MCU, catatan dokter,
     * rujukan beserta suratnya: administrator, tim OHSE, paramedis, dan
     * dokter yang ditugasi pada surat MCU itu. Peran sama dengan gerbang
     * berkas medis (Berkas::GERBANG 'mnh'), ditambah dokter suratnya.
     */
    public static function medis(?User $u, ?\App\Models\Miners\Mcu $mcu = null): bool
    {
        if (! $u) return false;
        if ($u->isAdmin() || $u->isOhse() || $u->isParamedis()) return true;

        return $mcu !== null && self::penyetujuLangkah($mcu, $u, 'dokter');
    }

    /** Hentikan permintaan dengan 403 bila syaratnya tidak terpenuhi. */
    public static function tegakkan(bool $boleh, string $pesan = 'Anda tidak berwenang mengubah berkas ini.'): void
    {
        abort_unless($boleh, 403, $pesan);
    }
}
