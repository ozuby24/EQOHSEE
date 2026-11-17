<?php

namespace App\Support;

use App\Models\{AksesAudit, User};

/**
 * Aturan Akses Audit per perusahaan — port DBEST #16 (Sistem\AksesAudit).
 *
 * Perusahaan yang punya penugasan memakai daftarnya: hanya auditor yang
 * ditugaskan (dan administrator) yang mengaudit; hanya pengunggah yang
 * ditugaskan (dan auditor/administrator) yang mengunggah bukti dan menjawab
 * temuan. Perusahaan tanpa penugasan memakai aturan bawaan EQOHSEE — peran
 * audit akun dan jangkauan grup perusahaannya — sehingga memasang modul ini
 * tidak mengubah apa pun sampai administrator mulai menugaskan.
 *
 * Auditor yang ditugaskan untuk perusahaan di luar grupnya ikut mendapat
 * jangkauan atas perusahaan itu (User::jangkauan).
 */
final class AturanAksesAudit
{
    /** Ingatan per permintaan HTTP (bukan statis): penugasan yang baru diubah langsung berlaku. */
    private static function ingat(string $kunci, callable $hitung): mixed
    {
        $r = request();
        $memo = (array) $r->attributes->get('aturanAksesAudit', []);
        if (!array_key_exists($kunci, $memo)) {
            $memo[$kunci] = $hitung();
            $r->attributes->set('aturanAksesAudit', $memo);
        }

        return $memo[$kunci];
    }

    /** @return array{auditor: list<int>, pengunggah: list<int>} */
    public static function daftar(int $companyId): array
    {
        return self::ingat('p'.$companyId, function () use ($companyId) {
            $baris = AksesAudit::where('company_id', $companyId)->get(['user_id', 'peran']);

            return [
                'auditor'    => $baris->where('peran', AksesAudit::AUDITOR)->pluck('user_id')->map(fn ($i) => (int) $i)->values()->all(),
                'pengunggah' => $baris->where('peran', AksesAudit::PENGUNGGAH)->pluck('user_id')->map(fn ($i) => (int) $i)->values()->all(),
            ];
        });
    }

    /** Perusahaan tempat akun ini ditugaskan (peran apa pun) — perluasan jangkauan. @return list<int> */
    public static function tambahan(User $u): array
    {
        return self::ingat('u'.$u->id, fn () => AksesAudit::where('user_id', $u->id)->pluck('company_id')
            ->map(fn ($i) => (int) $i)->unique()->values()->all());
    }

    /** Boleh mengaudit (menilai, verifikasi, mengisi berkas audit) perusahaan ini. */
    public static function bolehAudit(?User $u, ?int $companyId): bool
    {
        if (!$u) return false;
        if ($u->isAdmin()) return true;
        if (!$u->bisaAudit()) return false;
        if (!$companyId) return true;

        $auditor = self::daftar($companyId)['auditor'];

        return !$auditor || in_array((int) $u->id, $auditor, true);
    }

    /** Boleh mengunggah bukti / menjawab temuan bagi perusahaan ini. */
    public static function bolehUnggah(?User $u, ?int $companyId): bool
    {
        if (!$u) return false;
        if ($u->isAdmin() || !$companyId) return true;
        if ($u->bisaAudit()) return self::bolehAudit($u, $companyId);

        $pengunggah = self::daftar($companyId)['pengunggah'];

        return !$pengunggah || in_array((int) $u->id, $pengunggah, true);
    }
}
