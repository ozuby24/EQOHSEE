<?php

namespace App\Http\Controllers\Energi;

use App\Models\Company;
use App\Support\Perusahaan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Bantu bersama controller Energi & Karbon.
 *
 * Halaman tata kelola — periode, personel, audit, batas GRK, laporan —
 * selalu milik SATU perusahaan. Perusahaannya: milik pengguna; bagi
 * administrator, yang dipilih lewat `?perusahaan=` atau pemilih di bilah
 * atas. Pengguna biasa tidak dapat menunjuk perusahaan lain lewat alamat.
 */
trait BantuEnergi
{
    protected function perusahaanAktif(Request $request): ?int
    {
        $u = $request->user();

        if ($u?->isAdmin()) {
            $diminta = $request->integer('perusahaan') ?: null;
            if ($diminta && Company::withoutGlobalScopes()->whereKey($diminta)->exists()) return $diminta;

            return Perusahaan::terpilih($u) ?? $u->company_id;
        }

        return $u?->company_id;
    }

    /** Pilihan perusahaan untuk administrator; kosong bagi pengguna biasa. */
    protected function pilihanPerusahaan(Request $request): array
    {
        if (!$request->user()?->isAdmin()) return [];

        return Company::withoutGlobalScopes()->orderBy('name')->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'nama' => $c->name])->all();
    }

    /** @return array{0:Carbon,1:Carbon} rentang tanggal; bawaan bulan berjalan. */
    protected function rentangTanggal(Request $request, ?Carbon $dariBawaan = null, ?Carbon $sampaiBawaan = null): array
    {
        $dari   = $request->date('dari')   ?: ($dariBawaan ?? now()->startOfMonth());
        $sampai = $request->date('sampai') ?: ($sampaiBawaan ?? now()->endOfMonth());

        return $dari->lessThanOrEqualTo($sampai)
            ? [$dari->copy()->startOfDay(), $sampai->copy()->startOfDay()]
            : [$sampai->copy()->startOfDay(), $dari->copy()->startOfDay()];
    }

    protected function namaPerusahaan(?int $id): string
    {
        return $id ? (Company::withoutGlobalScopes()->find($id)?->name ?? 'Perusahaan') : 'Tanpa perusahaan';
    }
}
