<?php

namespace Tests\Concerns;

use App\Models\{Company, User};

/**
 * Grup perusahaan kecil untuk pengujian modul audit SMKP (porting
 * Aurorasys): induk (pemegang IUP) + PJP-nya + sister company.
 *
 * Penyesuaian EQOHSEE: peran auditor/auditi ada di kolom `audit_role`
 * ('auditor' / 'company'), akun dari factory sudah terverifikasi, dan
 * nomor kop dokumen disusun dari `doc_no_prefix` (Support\Nomor) — di sini
 * disamakan dengan kode perusahaan. EQOHSEE tidak mengenal super
 * administrator terpisah; superadmin() memulangkan administrator biasa.
 */
trait DataGrup
{
    protected Company $induk;
    protected Company $pjp;
    protected Company $sister;

    protected function siapkanGrup(): void
    {
        $this->induk  = Company::create(['name' => 'PT Induk Uji', 'code' => 'IU', 'doc_no_prefix' => 'IU', 'kategori' => 'induk', 'izin_type' => 'IUP', 'commodity' => 'Batu Kapur']);
        $this->pjp    = Company::create(['name' => 'PT Jasa Uji', 'code' => 'JU', 'doc_no_prefix' => 'JU', 'kategori' => 'pjp', 'parent_id' => $this->induk->id, 'izin_type' => 'IUJP']);
        $this->sister = Company::create(['name' => 'PT Saudara Uji', 'code' => 'SU', 'doc_no_prefix' => 'SU', 'kategori' => 'sister', 'izin_type' => 'IUP']);
    }

    protected function superadmin(): User
    {
        return $this->admin();
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function auditor(?Company $c = null): User
    {
        return User::factory()->auditor(($c ?? $this->induk)->id)->create();
    }

    protected function auditi(?Company $c = null): User
    {
        return User::factory()->auditi($c?->id)->create();
    }
}
