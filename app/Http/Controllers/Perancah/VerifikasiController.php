<?php

namespace App\Http\Controllers\Perancah;

use App\Http\Controllers\Controller;
use App\Models\Perancah\{Tag, Unit};
use App\Support\Perancah\{Katalog, Penanda};
use Inertia\Inertia;

/**
 * Verifikasi scafftag dari QR, tanpa masuk.
 *
 * Yang memindai biasanya pekerja di kaki tangga perancah: pertanyaannya
 * satu — layak digunakan atau tidak. Jawabannya dibaca dari tag yang SEDANG
 * aktif, bukan dari tag yang dipindai: kartu fisik di holder bisa sudah
 * diganti merah sejak kemarin sementara yang tergantung masih hijau.
 *
 * Yang diperlihatkan sengaja sedikit — kode, lokasi, warna, SWL, masa
 * berlaku, syarat. Tidak ada nama orang dan tidak ada data perusahaan
 * lain; alamatnya memakai token acak, bukan kode tag yang berurutan.
 */
class VerifikasiController extends Controller
{
    public function __invoke(string $token)
    {
        $dipindai = Tag::withoutGlobalScopes()->where('token', $token)->first();
        $unit = $dipindai ? Unit::withoutGlobalScopes()->find($dipindai->unit_id) : null;
        $aktif = $unit ? Tag::withoutGlobalScopes()->where('unit_id', $unit->id)->where('aktif', true)->latest('id')->first() : null;

        $berlaku = $aktif && $aktif->id === $dipindai?->id;
        $warna = $unit?->status_tag === 'dibongkar' ? 'dibongkar'
            : ($aktif ? ($aktif->kedaluwarsa() ? 'merah' : $aktif->warna) : 'merah');

        return Inertia::render('Perancah/Verifikasi', [
            'catatanKaki' => 'Verifikasi scafftag EQOHSEE — yang ditampilkan selalu tag yang berlaku saat dipindai.',
            'ada' => (bool) $dipindai,
            'tag' => $dipindai ? [
                'kode' => $dipindai->kode, 'berlaku' => $berlaku,
                'warna' => $warna,
                'label' => $unit?->status_tag === 'dibongkar' ? 'PERANCAH SUDAH DIBONGKAR'
                    : ($aktif?->kedaluwarsa() ? Penanda::LABEL['kedaluwarsa'] : ($aktif?->label ?? Penanda::LABEL['merah'])),
                'kode_aktif' => $aktif?->kode,
                'swl' => $aktif?->swl_kg_m2, 'syarat' => $aktif?->syarat_khusus, 'alasan' => $aktif?->alasan_tolak,
                'terbit_at' => $aktif?->terbit_at?->toIso8601String(),
                'kedaluwarsa_at' => $aktif?->kedaluwarsa_at?->toIso8601String(),
                'unit' => $unit ? ['kode' => $unit->kode, 'judul' => $unit->judul, 'zona' => $unit->zona,
                    'dimensi' => $unit->dimensi(), 'jenis' => Katalog::JENIS[$unit->jenis]['label'] ?? $unit->jenis] : null,
            ] : null,
        ]);
    }
}
