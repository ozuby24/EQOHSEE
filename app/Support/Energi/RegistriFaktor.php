<?php

namespace App\Support\Energi;

use App\Models\Energi\Faktor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Memilih faktor yang berlaku untuk satu tanggal aktivitas (FSD §6.1).
 *
 * Urutan prioritas, dari yang paling spesifik:
 *   1. faktor perusahaan + pemasok (Tier 2/3)
 *   2. faktor perusahaan tanpa pemasok
 *   3. faktor bersama dengan wilayah (sistem grid / provinsi) yang cocok
 *   4. faktor bersama tanpa wilayah (bawaan IPCC/nasional)
 *   5. tidak ada → null. TIDAK ADA cadangan diam-diam ke angka di kode:
 *      pemanggil wajib menandai baris itu "faktor tidak tersedia".
 *
 * Dibaca tanpa scope perusahaan, lalu disaring TEGAS ke perusahaan pemilik
 * data yang sedang dihitung: faktor perusahaan A tidak pernah dipakai untuk
 * data perusahaan B, dan BOD grup yang membaca beberapa perusahaan sekaligus
 * tetap memakai faktor milik masing-masing.
 *
 * Isinya dihafal per permintaan (instans scoped di kontainer), sehingga
 * seribu baris BBM tidak menjadi seribu kueri.
 */
final class RegistriFaktor
{
    /** @var array<string,Collection<int,Faktor>> */
    private array $memo = [];

    private ?bool $terpasang = null;

    public static function inst(): self
    {
        return app(self::class);
    }

    /** Lupakan hafalan — dipanggil setiap kali faktor disimpan atau dihapus. */
    public static function lupakan(): void
    {
        if (app()->resolved(self::class)) {
            $i = app(self::class);
            $i->memo = [];
            $i->terpasang = null;
        }
    }

    /**
     * Apakah paket faktor bersama sudah dipasang (`php artisan energi:pasang`)?
     *
     * Hanya fasad lama `App\Support\Energi` yang memakai jawaban ini, untuk
     * kembali ke konstanta warisan pada pemasangan yang belum pernah
     * menjalankan perintahnya (FSD §14 M5). Mesin inventarisasi GRK tidak.
     */
    public static function terpasang(): bool
    {
        $i = self::inst();

        return $i->terpasang ??= Faktor::withoutGlobalScopes()
            ->whereNull('company_id')->where('status', 'aktif')
            ->where('kode_energi', 'solar')->where('parameter', 'ncv')->exists();
    }

    /**
     * @param array{company_id?:?int,wilayah?:?string,pemasok?:?string,kategori_ipcc?:?string} $k
     */
    public static function ambil(string $kode, string $parameter, \DateTimeInterface|string $tanggal, array $k = []): ?Faktor
    {
        $t = Carbon::parse($tanggal)->toDateString();

        $calon = self::inst()->baris($kode, $parameter)->filter(function (Faktor $f) use ($t, $k) {
            if ($f->berlaku_mulai->toDateString() > $t) return false;
            if ($f->berlaku_sampai && $f->berlaku_sampai->toDateString() < $t) return false;

            if ($f->company_id !== null && (int) $f->company_id !== (int) ($k['company_id'] ?? 0)) return false;
            if ($f->pemasok !== null && $f->pemasok !== ($k['pemasok'] ?? null)) return false;
            if ($f->wilayah !== null && $f->wilayah !== ($k['wilayah'] ?? null)) return false;
            if ($f->kategori_ipcc !== null && $f->kategori_ipcc !== ($k['kategori_ipcc'] ?? null)) return false;

            return true;
        });

        if ($calon->isEmpty()) return null;

        return $calon->sortByDesc(fn (Faktor $f) => sprintf('%02d|%s',
            ($f->company_id !== null ? 8 : 0) + ($f->pemasok !== null ? 4 : 0)
            + ($f->wilayah !== null ? 2 : 0) + ($f->kategori_ipcc !== null ? 1 : 0),
            $f->berlaku_mulai->toDateString(),
        ))->first();
    }

    public static function nilai(string $kode, string $parameter, \DateTimeInterface|string $tanggal, array $k = []): ?float
    {
        return self::ambil($kode, $parameter, $tanggal, $k)?->nilai;
    }

    /**
     * Apakah sebuah versi bertumpang-tindih dengan versi lain berkunci sama?
     *
     * Dua versi aktif yang sama-sama berlaku pada satu tanggal membuat
     * pemilihan bergantung pada urutan, bukan pada keputusan siapa pun.
     */
    public static function tumpangTindih(Faktor $f): ?Faktor
    {
        $mulai  = $f->berlaku_mulai->toDateString();
        $sampai = $f->berlaku_sampai?->toDateString() ?? '9999-12-31';

        return Faktor::withoutGlobalScopes()
            ->where('id', '!=', $f->id ?? 0)
            ->where('status', 'aktif')
            ->where('kode_energi', $f->kode_energi)->where('parameter', $f->parameter)
            ->where(fn ($q) => $f->company_id === null ? $q->whereNull('company_id') : $q->where('company_id', $f->company_id))
            ->where(fn ($q) => $f->kategori_ipcc === null ? $q->whereNull('kategori_ipcc') : $q->where('kategori_ipcc', $f->kategori_ipcc))
            ->where(fn ($q) => $f->wilayah === null ? $q->whereNull('wilayah') : $q->where('wilayah', $f->wilayah))
            ->where(fn ($q) => $f->pemasok === null ? $q->whereNull('pemasok') : $q->where('pemasok', $f->pemasok))
            ->whereDate('berlaku_mulai', '<=', $sampai)
            ->where(fn ($q) => $q->whereNull('berlaku_sampai')->orWhereDate('berlaku_sampai', '>=', $mulai))
            ->first();
    }

    /**
     * Sisipkan satu versi (biasanya draf) seolah aktif — untuk "uji dampak"
     * sebelum persetujuan. Hanya hidup sampai `lupakan()` dipanggil.
     */
    public static function sisipkan(Faktor $f): void
    {
        $i = self::inst();
        $kunci = $f->kode_energi.'|'.$f->parameter;
        $i->memo[$kunci] = $i->baris($f->kode_energi, $f->parameter)->reject(fn ($x) => $x->id === $f->id)
            ->map(function ($x) use ($f) {
                $sama = $x->company_id === $f->company_id && $x->kategori_ipcc === $f->kategori_ipcc
                     && $x->wilayah === $f->wilayah && $x->pemasok === $f->pemasok;

                /* Versi lama berkunci sama ditutup sehari sebelum draf berlaku,
                   persis seperti yang akan terjadi bila draf itu disetujui. */
                if ($sama && $x->berlaku_sampai === null && $x->berlaku_mulai->lt($f->berlaku_mulai)) {
                    $x = clone $x;
                    $x->berlaku_sampai = $f->berlaku_mulai->copy()->subDay();
                }

                return $x;
            })->push($f)->values();
    }

    /** @return Collection<int,Faktor> */
    private function baris(string $kode, string $parameter): Collection
    {
        return $this->memo[$kode.'|'.$parameter] ??= Faktor::withoutGlobalScopes()
            ->where('kode_energi', $kode)->where('parameter', $parameter)
            ->where('status', 'aktif')->get();
    }
}
