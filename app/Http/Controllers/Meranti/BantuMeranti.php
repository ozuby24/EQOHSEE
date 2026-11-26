<?php

namespace App\Http\Controllers\Meranti;

use App\Http\Controllers\Energi\BantuEnergi;
use App\Models\Meranti\Log;
use App\Support\Meranti\Katalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Bantu bersama controller MERANTI.
 *
 * Perusahaan aktif mengikuti aplikasi lain (milik pengguna; administrator
 * memilihnya lewat ?perusahaan=). Izin mengikuti empat peran MERANTI —
 * Data Master, EMC, Crew, Manager — lewat gerbang meranti.*.
 */
trait BantuMeranti
{
    use BantuEnergi;

    /** Kueri model MERANTI, dibatasi ke perusahaan aktif bila ada. */
    protected function kueri(string $kelas, ?int $c): Builder
    {
        return $kelas::query()->when($c, fn ($q) => $q->where((new $kelas)->getTable().'.company_id', $c));
    }

    /** Perusahaan tempat baris BARU disimpan. */
    protected function perusahaanSimpan(Request $request): ?int
    {
        $u = $request->user();

        return $u?->isAdmin() ? ($this->perusahaanAktif($request) ?? $u->company_id) : $u?->company_id;
    }

    /** @return array{tambah: bool, ubah: bool, hapus: bool, master: bool} */
    protected function izin(?array $modul = null): array
    {
        $master = Gate::allows('meranti.master');
        $hanyaMaster = (bool) ($modul['master'] ?? false);

        return [
            'tambah' => $hanyaMaster ? $master : Gate::allows('meranti.tambah'),
            'ubah'   => $hanyaMaster ? $master : Gate::allows('meranti.ubah'),
            'hapus'  => $hanyaMaster ? $master : Gate::allows('meranti.hapus'),
            'master' => $master,
        ];
    }

    protected function catat(Request $request, ?int $c, string $aksi, string $modul, string $detail = ''): void
    {
        Log::create([
            'company_id' => $c, 'user_id' => $request->user()?->id, 'waktu' => now(),
            'pengguna' => mb_substr((string) $request->user()?->name, 0, 100) ?: null,
            'aksi' => $aksi, 'modul' => $modul, 'detail' => mb_substr($detail, 0, 5000) ?: null,
        ]);
    }

    /** Daftar modul per kelompok untuk navigasi di dalam halaman. */
    protected function navModul(): array
    {
        $grup = [];
        foreach (Katalog::semua() as $k => $m) {
            $grup[$m['grup']][] = ['kunci' => $k, 'judul' => $m['judul'], 'slug' => $m['slug'], 'ikon' => $m['ikon']];
        }

        return $grup;
    }
}
