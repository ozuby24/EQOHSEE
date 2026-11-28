<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Kuki sesi kembar sesudah SESSION_DOMAIN diubah.
 *
 * Peramban menyimpan dua kuki bernama sama — khusus host (lama) dan untuk
 * seluruh domain (baru) — dan PHP hanya membaca yang pertama, yaitu yang
 * lama dan tidak pernah diperbarui lagi. Masuk berputar kembali ke halaman
 * masuk, dan masuk dengan Google berakhir InvalidStateException.
 */
class KukiSesiKembarTest extends TestCase
{
    private function dihapus($jawab, string $nama, ?string $domain): bool
    {
        return collect($jawab->headers->getCookies())
            ->contains(fn ($k) => $k->getName() === $nama && $k->getDomain() === $domain && $k->isCleared());
    }

    public function test_kembaran_khusus_host_dihapus_bila_domain_sesi_diisi(): void
    {
        config(['session.domain' => 'eqohsee.test']);
        $nama = config('session.cookie');

        $jawab = $this->withHeader('Cookie', "{$nama}=lama; {$nama}=baru")->get('/login');

        $this->assertTrue($this->dihapus($jawab, $nama, null), 'Kuki sesi lama yang khusus host tidak dihapus.');
        $this->assertFalse($this->dihapus($jawab, $nama, 'eqohsee.test'), 'Kuki sesi yang sedang dipakai ikut dihapus.');
    }

    public function test_kembaran_domain_induk_dihapus_bila_domain_sesi_kosong(): void
    {
        config(['session.domain' => null]);
        $nama = config('session.cookie');

        $jawab = $this->withHeader('Cookie', "{$nama}=lama; {$nama}=baru")->get('http://www.eqohsee.test/login');

        $this->assertTrue($this->dihapus($jawab, $nama, 'eqohsee.test'));
    }

    public function test_satu_kuki_sesi_tidak_disentuh(): void
    {
        config(['session.domain' => 'eqohsee.test']);
        $nama = config('session.cookie');

        $jawab = $this->withHeader('Cookie', "{$nama}=satu; eq_bahasa=id")->get('/login');

        $this->assertFalse($this->dihapus($jawab, $nama, null));
    }
}
