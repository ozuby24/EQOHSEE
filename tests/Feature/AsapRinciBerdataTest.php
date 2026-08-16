<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use App\Support\DataContoh;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RuteSatu;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Uji asap halaman RINCIAN, dengan data contoh terpasang.
 *
 * AsapRuteTest sudah menjaga halaman daftar, tetapi dua celah tertinggal
 * di sana, dan keduanya justru menutupi jenis cacat yang paling sering
 * lolos:
 *
 * 1. Ia berjalan di atas basis data KOSONG. Halaman yang meledak hanya
 *    ketika ada barisnya — kolom yang tidak ditetapkan tipenya, relasi
 *    yang tidak dimuat, pembagian dengan nol — lewat tanpa tersentuh.
 *    Persis begitu /akun/perangkat memulangkan 500 bagi setiap pengguna
 *    sungguhan sementara seluruh berkas ujinya hijau.
 *
 * 2. Ia MELEWATI setiap rute berparameter. Itu berarti tidak satu pun
 *    halaman rincian — tempat sebagian besar kueri dan sebagian besar
 *    relasi berada — pernah dibuka oleh uji mana pun.
 *
 * Berkas ini menutup keduanya: memuat data contoh lebih dulu, lalu
 * membuka tiap rute rincian dengan id baris yang benar-benar ada.
 *
 * Nilai parameternya diambil dari TANDA TANGAN aksi controller, bukan
 * ditebak dari nama parameternya. Nama parameter di sini berbahasa
 * Indonesia — {objek}, {dokumen}, {standar} — sedangkan nama modelnya
 * tidak, jadi penebakan berdasarkan nama melewatkan hampir semuanya
 * sambil tetap terlihat hijau.
 */
class AsapRinciBerdataTest extends TestCase
{
    use RefreshDatabase;

    /** Bukan halaman, atau mengubah keadaan. */
    private const LEWATI = [
        'logout', 'sanctum.csrf-cookie', 'ignition.healthCheck', 'storage.local',
    ];

    /**
     * Berapa rute rincian yang paling sedikit harus benar-benar terbuka.
     *
     * Ada di sini supaya uji ini tidak dapat merosot diam-diam menjadi
     * uji yang tidak menguji apa pun: satu perubahan pada pemuat data
     * contoh dapat membuat setiap parameter gagal diisi, dan tanpa
     * ambang ini hasilnya tetap hijau dengan nol halaman dibuka.
     *
     * Angkanya di bawah jumlah yang sekarang lulus, bukan sama dengan,
     * supaya penambahan rute tidak menuntut angka ini ikut disunting
     * setiap kali.
     */
    private const MINIMAL_RINCIAN = 20;

    /** @var list<string> model yang tidak punya satu pun baris contoh */
    private array $tanpaData = [];

    public function test_halaman_rincian_terbuka_dengan_data_contoh(): void
    {
        [$admin] = $this->perusahaanBerisi();

        $this->actingAs($admin);

        $gagal  = [];
        $dibuka = 0;

        foreach (Route::getRoutes() as $rute) {
            if (!$this->rutePantasDiuji($rute)) continue;
            if (!str_contains($rute->uri(), '{')) continue;

            $uri = $this->isiParameter($rute);
            if ($uri === null) continue;

            $dibuka++;

            try {
                $status = $this->get('/'.ltrim($uri, '/'))->getStatusCode();
            } catch (\Throwable $e) {
                $gagal[] = "{$rute->getName()} ({$uri}) — lempar: ".mb_substr($e->getMessage(), 0, 200);
                continue;
            }

            if ($status >= 500) {
                $gagal[] = "{$rute->getName()} ({$uri}) — HTTP {$status}";
            }
        }

        $this->assertGreaterThanOrEqual(self::MINIMAL_RINCIAN, $dibuka,
            "Hanya {$dibuka} halaman rincian yang dapat dibuka — di bawah ambang.\n"
            ."Hampir selalu berarti pemuat data contoh berhenti mengisi sesuatu, bukan\n"
            ."berarti rutenya berkurang. Model tanpa baris contoh: "
            .implode(', ', array_unique($this->tanpaData)));

        $this->assertSame([], $gagal,
            "Halaman rincian berikut gagal dibuka padahal datanya ada:\n  ".implode("\n  ", $gagal));
    }

    /**
     * Halaman daftar pun diuji ulang — kali ini DENGAN isi.
     *
     * Bukan pengulangan AsapRuteTest: yang diuji di sana adalah halaman
     * kosong. Rekapitulasi, pembagian rata-rata, dan pengelompokan baru
     * benar-benar berjalan ketika ada barisnya, dan itulah yang membuat
     * halaman daftar meledak di produksi tetapi tidak di uji.
     */
    public function test_halaman_daftar_terbuka_ketika_ada_isinya(): void
    {
        [$admin] = $this->perusahaanBerisi();

        $this->actingAs($admin);

        $gagal = [];

        foreach (Route::getRoutes() as $rute) {
            if (!$this->rutePantasDiuji($rute)) continue;
            if (str_contains($rute->uri(), '{')) continue;

            try {
                $status = $this->get('/'.ltrim($rute->uri(), '/'))->getStatusCode();
            } catch (\Throwable $e) {
                $gagal[] = "{$rute->getName()} — lempar: ".mb_substr($e->getMessage(), 0, 200);
                continue;
            }

            if ($status >= 500) $gagal[] = "{$rute->getName()} ({$rute->uri()}) — HTTP {$status}";
        }

        $this->assertSame([], $gagal,
            "Halaman berikut meledak justru ketika ada datanya:\n  ".implode("\n  ", $gagal));
    }

    /* ═══════════ perkakas ═══════════ */

    /** @return array{0:User,1:Company} */
    private function perusahaanBerisi(): array
    {
        $c = Company::create(['name' => 'PT Contoh Asap', 'demo' => true]);

        $admin = User::factory()->create([
            'is_admin'          => true,
            'company_id'        => $c->id,
            'email_verified_at' => now(),
        ]);

        /* Pengguna kedua bukan hiasan: pemuat menolak memakai peninjau
           sebagai pengaju, jadi tanpa orang kedua seluruh barisnya
           berhenti sebagai draf dan halaman rincian yang menuntut
           status disetujui tidak akan pernah terbuka. */
        User::factory()->create(['company_id' => $c->id, 'email_verified_at' => now()]);

        DataContoh::muat($c, $admin);

        return [$admin->fresh(), $c];
    }

    private function rutePantasDiuji(RuteSatu $rute): bool
    {
        if (!in_array('GET', $rute->methods(), true)) return false;

        $nama = $rute->getName();

        if ($nama === null || in_array($nama, self::LEWATI, true)) return false;

        return in_array('auth', $rute->gatherMiddleware(), true);
    }

    /**
     * Ganti tiap parameter rute dengan kunci baris yang nyata.
     *
     * Memulangkan null bila ada satu saja parameter yang tidak dapat
     * diisi — separuh alamat yang terisi menghasilkan 404 yang akan
     * dilaporkan sebagai kegagalan padahal bukan.
     */
    private function isiParameter(RuteSatu $rute): ?string
    {
        $uri = $rute->uri();

        preg_match_all('/\{(\w+)\??\}/', $uri, $cocok);

        foreach ($cocok[1] as $par) {
            $nilai = $this->kunciNyata($rute, $par);

            if ($nilai === null) return null;

            $uri = preg_replace('/\{'.preg_quote($par, '/').'\??\}/', (string) $nilai, $uri, 1);
        }

        return $uri;
    }

    private function kunciNyata(RuteSatu $rute, string $par): int|string|null
    {
        foreach ($rute->signatureParameters() as $sp) {
            if ($sp->getName() !== $par) continue;

            $tipe = $sp->getType();

            if (!$tipe instanceof \ReflectionNamedType || $tipe->isBuiltin()) return null;

            $kelas = $tipe->getName();

            if (!is_subclass_of($kelas, Model::class)) return null;

            /* Tanpa scope perusahaan: uji ini berlaku sebagai
               administrator, dan yang hendak dibuktikan adalah
               halamannya tidak meledak — bukan siapa boleh melihat apa.
               Batas antar perusahaan dijaga BatasPerusahaanTest. */
            $baris = $kelas::query()->withoutGlobalScopes()->first();

            if (!$baris) {
                $this->tanpaData[] = class_basename($kelas);

                return null;
            }

            return $baris->{$rute->bindingFieldFor($par) ?: $baris->getRouteKeyName()};
        }

        return null;
    }
}
