<?php

namespace App\Http\Controllers\Miners\Concerns;

use App\Support\Miners\Jalur;
use Illuminate\Http\Request;

/**
 * Tindakan alur yang dipakai bersama controller dokumen Miners.
 *
 * Satu pintu, sama dengan MinersDokumenController::tindak(): pemeriksaan
 * "bukan giliran Anda" dan "pengaju tidak menyetujui pengajuannya
 * sendiri" ada di Jalur, dan yang tertinggal di salah satu controller
 * tidak menimbulkan galat — hanya dokumen yang disahkan orang yang salah.
 */
trait TindakAlur
{
    protected function tindakAlur(Request $r, object $dokumen, string $sebutan)
    {
        $data = $r->validate([
            'keadaan' => ['required', 'in:setuju,tolak,dikembalikan'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $salah = Jalur::tindak($dokumen, $r->user(), $data['keadaan'], $data['catatan'] ?? null);

        if ($salah !== null) {
            return back()->withErrors(['keadaan' => $salah]);
        }

        return $this->balas($sebutan.' — '.match ($data['keadaan']) {
            'setuju' => 'disetujui.',
            'tolak'  => 'ditolak.',
            default  => 'dikembalikan ke pengaju.',
        });
    }

    /**
     * Pesan sukses beserta peringatan pemberitahuan — st_sukses dan
     * st_gagal Safe Track, dua pesan, bukan satu kalimat panjang.
     */
    protected function balas(string $sukses, ?string $galat = null)
    {
        $balas = back()->with('sukses', $sukses);

        $galat ??= Jalur::pesanSurel();

        return $galat ? $balas->with('galat', $galat) : $balas;
    }

    /**
     * Buang medan berkas dari data tervalidasi — lihat
     * MinersDokumenController::tanpaBerkas().
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected static function tanpaBerkas(array $data): array
    {
        foreach (array_keys($data) as $k) {
            if (str_starts_with($k, 'berkas') || in_array($k, ['foto', 'ktp', 'permohonan'], true)) unset($data[$k]);
        }

        return $data;
    }

    /**
     * Kirim satu pemberitahuan, usaha-terbaik — kegagalannya dikatakan,
     * tidak menjatuhkan pekerjaan yang sudah tersimpan.
     *
     * @return string|null kalimat kegagalan untuk layar
     */
    protected function surel(callable $kirim): ?string
    {
        try {
            $kirim();

            return null;
        } catch (\Throwable $e) {
            report($e);

            return 'Pekerjaan Anda SUDAH tercatat. Namun pemberitahuan surel tidak terkirim '
                .'(server surel tidak dapat dihubungi). Tidak perlu mengulang; beri tahu penerimanya '
                .'secara terpisah bila mendesak.';
        }
    }
}
