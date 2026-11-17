<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use App\Support\DataContoh;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RuteTerdaftar;
use Illuminate\Support\Facades\{DB, Route};
use Tests\TestCase;

/**
 * Pengguna biasa tidak boleh menghapus apa pun.
 *
 * Yang diuji BUKAN bentuk kodenya melainkan akibatnya: setiap rute DELETE
 * dipanggil sungguhan sebagai pengguna tanpa peran, lalu barisnya
 * diperiksa — masih ada, atau sudah hilang. Uji yang membaca middleware
 * akan lulus atas rute yang middleware-nya benar tetapi tak pernah
 * berjalan, dan itu persis kegagalan yang ingin ditangkap di sini.
 *
 * Terukur sebelum diperbaiki: 54 rute menolak dengan benar, 8 meloloskan.
 * Seluruh modul Inspeksi (jenis, parameter, inspeksi, inspektur, item),
 * dua data induk Energi (unit dan peluang penghematan), dan respons
 * kuesioner TPKKP — semuanya dapat dihapus oleh siapa saja yang dapat
 * masuk. Layarnya memang menyembunyikan tombolnya dari non-admin, jadi
 * penjagaannya hanya ada di sisi peramban; satu permintaan yang disusun
 * tangan melewatinya seluruhnya.
 */
class OtorisasiHapusTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengguna_biasa_tidak_dapat_menghapus(): void
    {
        $c = Company::create(['name' => 'PT Otorisasi', 'demo' => true]);

        $peninjau = User::factory()->create([
            'company_id' => $c->id, 'email_verified_at' => now(), 'is_admin' => true,
        ]);
        DataContoh::muat($c, $peninjau);

        $biasa = User::factory()->create([
            'company_id' => $c->id, 'email_verified_at' => now(), 'is_admin' => false,
        ]);

        $terhapus = $diuji = 0;
        $bocor = [];

        foreach (Route::getRoutes() as $r) {
            if (!in_array('DELETE', $r->methods(), true)) continue;

            /* Akun sendiri sengaja dilewati: memutus perangkat dan menghapus
               akun sendiri memang hak pemiliknya. */
            if (preg_match('#^(logout|profile|akun|password)#', $r->uri())) continue;

            $terikat = $this->modelTerikat($r);
            if (!$terikat) continue;

            [$nama, $kelas] = $terikat;

            $baris = $kelas::withoutGlobalScopes()->where('company_id', $c->id)->first()
                  ?? $kelas::withoutGlobalScopes()->first();
            if (!$baris) continue;

            $jalur = preg_replace('/\{' . $nama . '\??\}/', (string) $baris->getKey(), $r->uri(), 1);
            if (str_contains($jalur, '{')) continue;

            $tabel = $baris->getTable();
            $kunci = $baris->getKey();
            $diuji++;

            try {
                $this->actingAs($biasa)->deleteJson('/' . ltrim($jalur, '/'));
            } catch (\Throwable $e) {
                // Yang menentukan keadaan barisnya, bukan responsnya.
            }

            if (!DB::table($tabel)->where('id', $kunci)->exists()) {
                $terhapus++;
                $bocor[] = $jalur . ' (' . class_basename($kelas) . ')';
            }
        }

        $this->assertGreaterThan(30, $diuji,
            'Terlalu sedikit rute terjangkau; ujinya tidak lagi membuktikan apa pun.');

        $this->assertSame([], $bocor,
            "Pengguna biasa berhasil menghapus:\n  " . implode("\n  ", $bocor));
    }

    /**
     * [nama parameter, kelas model] untuk rute yang mengikat model.
     *
     * Dibaca dari tanda tangan aksinya, bukan ditebak dari nama tabel —
     * tebakan menghasilkan 404 yang tidak membuktikan apa pun tentang izin.
     *
     * @return array{0:string,1:class-string}|null
     */
    private function modelTerikat(RuteTerdaftar $r): ?array
    {
        $aksi = $r->getActionName();
        if (!str_contains($aksi, '@')) return null;

        [$kelas, $metode] = explode('@', $aksi);
        if (!class_exists($kelas)) return null;

        try {
            $ref = new \ReflectionMethod($kelas, $metode);
        } catch (\Throwable $e) {
            return null;
        }

        foreach ($ref->getParameters() as $p) {
            $t = $p->getType();
            if (!$t instanceof \ReflectionNamedType || $t->isBuiltin()) continue;
            if (!is_subclass_of($t->getName(), Model::class)) continue;

            return [$p->getName(), $t->getName()];
        }

        return null;
    }
}
