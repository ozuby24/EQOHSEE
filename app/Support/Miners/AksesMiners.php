<?php

namespace App\Support\Miners;

use App\Models\Miners\{Alur, InduksiOrang, McuOrang, Pekerja, Permit, Simper};
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
    public static function lihatPekerja(Pekerja $p, ?User $u): bool
    {
        if (! $u) return false;
        if (self::kelola($u)) return true;

        return self::saringPekerja(Pekerja::withoutGlobalScopes()->whereKey($p->getKey()), $u)->exists();
    }

    /**
     * Aturan lihatPekerja dalam bentuk kueri — untuk daftar Manpower dan
     * unduhannya: yang ia daftarkan, yang MCU/Mine Permit/SIMPER-nya ia
     * ajukan, dan yang salah satu dokumen itu ditugaskan kepadanya.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $q  kueri atas mnr_pekerja
     */
    public static function saringPekerja($q, ?User $u)
    {
        if (self::kelola($u)) return $q;

        $id = (int) $u?->getKey();

        $langkah = fn (string $jenis) => Alur::query()->where('dokumen', $jenis)->where('ditugaskan_user_id', $id)->select('dokumen_id');
        $milik   = fn (string $kelas, string $jenis) => $kelas::withoutGlobalScopes()
            ->where(fn ($w) => $w->where('user_id', $id)->orWhereIn('id', $langkah($jenis)));

        return $q->where(fn ($w) => $w->where($q->qualifyColumn('user_id'), $id)
            ->orWhereIn($q->qualifyColumn('id'), McuOrang::withoutGlobalScopes()
                ->whereIn('mcu_id', $milik(\App\Models\Miners\Mcu::class, 'mcu')->select('id'))->select('pekerja_id'))
            ->orWhereIn($q->qualifyColumn('id'), $milik(Permit::class, 'permit')->select('pekerja_id'))
            ->orWhereIn($q->qualifyColumn('id'), $milik(Simper::class, 'simper')->select('pekerja_id')));
    }

    /**
     * Boleh membuka rincian sebuah dokumen Miners — MCU, induksi, Mine
     * Permit, SIMPER, ajuan SIMPER lanjutan, perpanjangan Mine Permit —
     * beserta sertifikat dan suratnya.
     *
     * Lingkup perusahaan dijaga global scope saat dokumennya dimuat; di
     * dalamnya berlaku aturan yang sama dengan lihatPekerja: pengelola
     * seluruhnya, pengguna lain hanya pengajunya, pendaftar pekerjanya,
     * penyetuju yang ditugasi (atau yang sudah menindak) salah satu
     * langkahnya, dan — khusus MCU — paramedis.
     */
    public static function lihatDokumen(object $dokumen, ?User $u): bool
    {
        if (! $u) return false;
        if (self::kelola($u)) return true;

        return self::saringDokumen($dokumen::withoutGlobalScopes()->whereKey($dokumen->getKey()), $u, $dokumen::jenisDokumen())
            ->exists();
    }

    /**
     * Aturan lihatDokumen dalam bentuk kueri — untuk History dan
     * unduhannya, supaya daftar memuat tepat dokumen yang boleh dibuka.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $q  kueri atas tabel dokumen jenis $jenis
     */
    public static function saringDokumen($q, ?User $u, string $jenis)
    {
        if (self::kelola($u)) return $q;
        if ($jenis === 'mcu' && $u?->isParamedis()) return $q;

        $id = (int) $u?->getKey();

        $langkah = Alur::query()->where('dokumen', $jenis)
            ->where(fn ($w) => $w->where('ditugaskan_user_id', $id)->orWhere('user_id', $id))->select('dokumen_id');

        /* Pekerja yang ia daftarkan, dan jalan dari dokumen ke pekerjanya. */
        $milik = Pekerja::withoutGlobalScopes()->where('user_id', $id)->select('id');

        [$kolom, $pekerjanya] = match ($jenis) {
            'mcu'     => ['id', McuOrang::withoutGlobalScopes()->whereIn('pekerja_id', $milik)->select('mcu_id')],
            'induksi' => ['id', InduksiOrang::withoutGlobalScopes()->whereIn('pekerja_id', $milik)->select('induksi_id')],
            'ajuan'   => ['simper_id', Simper::withoutGlobalScopes()->whereIn('pekerja_id', $milik)->select('id')],
            'perpanjangan_permit' => ['permit_id', Permit::withoutGlobalScopes()->whereIn('pekerja_id', $milik)->select('id')],
            default   => ['pekerja_id', $milik],   // permit, simper
        };

        return $q->where(fn ($w) => $w->where($q->qualifyColumn('user_id'), $id)
            ->orWhereIn($q->qualifyColumn('id'), $langkah)
            ->orWhereIn($q->qualifyColumn($kolom), $pekerjanya));
    }

    /**
     * Nomor SIM pada props: utuh bagi yang boleh membuka pindaian SIM-nya
     * (gerbang berkas yang sama, Berkas::GERBANG), tersamar bagi yang lain.
     */
    public static function noSim(?string $nomor, ?User $u, string $berkas = 'mnp'): ?string
    {
        return \App\Support\Berkas::bolehMembuka($u, $berkas) ? $nomor : Kartu\RincianVerifikasi::samarkan($nomor);
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
