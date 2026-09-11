<?php

namespace App\Models\Miners\Concerns;

use App\Models\Miners\Alur;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dokumen yang menempuh alur persetujuan.
 *
 * Dipakai MCU, Induksi, Mine Permit, SIMPER, dan pengajuan lanjutan —
 * kelimanya menempuh alur yang bentuknya sama dan hanya berbeda
 * langkahnya. Ditulis sebagai lima salinan, perubahan alur dikerjakan
 * lima kali dan yang tertinggal berhenti pada langkah yang salah tanpa
 * satu galat pun. Itu persis yang terjadi di Project1 dengan sembilan
 * tabel alurnya.
 *
 * Kelas pemakainya menyebut jenisnya lewat `$jenisDokumen`.
 */
trait PunyaAlur
{
    public function alur(): HasMany
    {
        return $this->hasMany(Alur::class, 'dokumen_id')
            ->where('dokumen', static::$jenisDokumen)
            ->orderBy('urutan');
    }

    /**
     * Terbitkan seluruh langkah sekaligus, sesuai jenisnya.
     *
     * SELURUHNYA sekaligus, bukan satu per satu saat gilirannya tiba.
     * Yang mengajukan berhak melihat berapa langkah lagi berkasnya harus
     * melewati sebelum terbit, dan langkah yang baru lahir saat
     * gilirannya tiba membuat layar menjawab "satu langkah lagi" pada
     * tiap langkah sampai langkah terakhir.
     */
    public function terbitkanAlur(): void
    {
        if ($this->alur()->exists()) return;

        foreach (Alur::LANGKAH[static::$jenisDokumen] ?? [] as $i => $peran) {
            $this->alur()->create([
                'dokumen' => static::$jenisDokumen,
                'urutan'  => $i + 1,
                'peran'   => $peran,
                'keadaan' => 'menunggu',
            ]);
        }
    }

    /** Langkah yang sedang menunggu tindakan, atau null bila tuntas. */
    public function langkahBerjalan(): ?Alur
    {
        return $this->alur->firstWhere('keadaan', 'menunggu');
    }

    /** Seluruh langkah sudah disetujui. */
    public function alurTuntas(): bool
    {
        $alur = $this->alur;

        return $alur->isNotEmpty() && $alur->every(fn (Alur $a) => $a->keadaan === 'setuju');
    }

    /** Ada langkah yang menolak atau mengembalikan. */
    public function alurTertahan(): bool
    {
        return $this->alur->contains(fn (Alur $a) => in_array($a->keadaan, ['tolak', 'dikembalikan'], true));
    }
}
