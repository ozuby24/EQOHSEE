<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use App\Support\DataContoh;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

/**
 * Setiap tabel yang menyimpan data lapangan ikut terisi data contoh.
 *
 * Tombol "muat data contoh" ada supaya orang dapat memeriksa apakah
 * alur dan angkanya sudah benar. Modul yang tetap kosong sesudah
 * tombolnya ditekan tidak dapat diperiksa — sambil terlihat seolah
 * sudah, sebab halamannya terbuka tanpa galat dan hanya menampilkan
 * nol. Nol yang berarti "belum ada data" dan nol yang berarti "tidak
 * ada temuan" digambar dengan cara yang persis sama.
 *
 * Yang pernah terjadi karena tidak ada uji ini: registri alat KO terisi
 * enam baris sementara lima tabel anaknya kosong, sehingga indeks KO —
 * rerata lima sub-elemen — dihitung dari satu sub-elemen saja dan tetap
 * memulangkan persentase yang tampak wajar. Modul Energi punya lima
 * alat tanpa satu pun catatan pemakaian. Modul perawatan tidak punya
 * satu perintah kerja pun, dan ketaatan PM-nya karena itu 100% dari
 * pembagi nol.
 *
 * Daftar pengecualiannya sengaja pendek dan beralasan. Tabel yang tidak
 * berisi data lapangan tidak perlu dicontohkan; sisanya perlu.
 */
class CakupanDataContohTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Tabel yang memang bukan data lapangan.
     *
     * Semuanya milik kerangka kerja atau terisi sendiri saat dipakai.
     * Yang masuk sini harus punya alasan di sebelahnya — pengecualian
     * tanpa alasan adalah cara daftar semacam ini pelan-pelan
     * kehilangan artinya.
     *
     * @var array<string,string> tabel => alasan
     */
    private const BUKAN_DATA_LAPANGAN = [
        'migrations'             => 'catatan migrasi',
        'password_reset_tokens'  => 'terisi saat orang lupa sandi',
        'sessions'               => 'terisi saat orang masuk',
        'cache'                  => 'terisi sendiri',
        'cache_locks'            => 'terisi sendiri',
        'jobs'                   => 'antrean',
        'job_batches'            => 'antrean',
        'failed_jobs'            => 'antrean yang gagal — kosong justru yang diharapkan',
        'personal_access_tokens' => 'token API',
        'activity_log'           => 'tabel lama; yang dipakai activity_logs',

        'users'        => 'pengguna dibuat pemasangnya, bukan data contoh',
        'companies'    => 'perusahaannya sendiri',
        'app_settings' => 'pengaturan, bukan data lapangan',
        'activity_logs'=> 'jejak perbuatan; terisi sendiri saat dipakai',
        'jejak_akses'  => 'jejak masuk; terisi sendiri saat dipakai',
    ];

    private function muat(): Company
    {
        $c = Company::create([
            'name' => 'PT Cemerlang Asa Mandiri', 'doc_no_prefix' => 'CAM', 'demo' => true,
        ]);
        $admin = User::factory()->create(['is_admin' => true, 'company_id' => $c->id]);
        User::factory()->create(['company_id' => $c->id]);

        DataContoh::muat($c, $admin);

        return $c;
    }

    /** @return list<string> nama tabel apa adanya, tanpa awalan skema */
    private function tabel(): array
    {
        return array_map(
            fn ($t) => str_contains($t, '.') ? substr($t, strrpos($t, '.') + 1) : $t,
            Schema::getTableListing(),
        );
    }

    public function test_setiap_tabel_data_lapangan_terisi(): void
    {
        $this->muat();

        $kosong = [];

        foreach ($this->tabel() as $t) {
            if (isset(self::BUKAN_DATA_LAPANGAN[$t])) continue;

            if (DB::table($t)->count() === 0) $kosong[] = $t;
        }

        sort($kosong);

        $this->assertSame([], $kosong,
            "Tabel berikut tetap kosong sesudah data contoh dimuat:\n  ".implode("\n  ", $kosong)
            ."\nHalamannya akan menampilkan nol, dan nol yang berarti \"belum ada data\" tidak "
            ."dapat dibedakan dari nol yang berarti \"tidak ada temuan\". Isi di DataContoh, "
            ."atau daftarkan alasannya pada BUKAN_DATA_LAPANGAN.");
    }

    /**
     * Yang dimuat, terhapus lagi seluruhnya.
     *
     * Pasangan yang harus dijaga bersama: tabel baru yang diisi tetapi
     * lupa didaftarkan pada URUTAN_HAPUS akan menumpuk barisnya setiap
     * kali tombol muat ulang ditekan — dan penumpukan itu tidak terlihat
     * sampai angkanya sudah salah berkali lipat.
     */
    public function test_yang_dimuat_terhapus_seluruhnya(): void
    {
        $c = $this->muat();

        $sebelum = [];
        foreach ($this->tabel() as $t) {
            if (isset(self::BUKAN_DATA_LAPANGAN[$t])) continue;
            $sebelum[$t] = DB::table($t)->count();
        }

        DataContoh::buang($c);

        $sisa = [];
        foreach ($sebelum as $t => $n) {
            $kini = DB::table($t)->count();
            if ($kini > 0) $sisa[] = "{$t}: {$kini} dari {$n}";
        }

        sort($sisa);

        $this->assertSame([], $sisa,
            "Baris berikut tertinggal sesudah data contoh dibuang:\n  ".implode("\n  ", $sisa)
            ."\nTiap kali tombol muat ulang ditekan, sisa ini bertambah — dan angka modulnya "
            ."ikut naik tanpa ada yang menambah data.");
    }

    /**
     * Memuat dua kali tidak menggandakan isinya.
     *
     * Ini yang sesungguhnya dirasakan orang: tombolnya ditekan dua
     * kali, lalu setiap angka menjadi dua kali lipat.
     */
    public function test_muat_dua_kali_tidak_menggandakan(): void
    {
        $c = $this->muat();

        $pertama = [];
        foreach ($this->tabel() as $t) {
            if (isset(self::BUKAN_DATA_LAPANGAN[$t])) continue;
            $pertama[$t] = DB::table($t)->count();
        }

        DataContoh::muat($c, User::where('is_admin', true)->first());

        $ganda = [];
        foreach ($pertama as $t => $n) {
            $kini = DB::table($t)->count();
            if ($kini !== $n) $ganda[] = "{$t}: {$n} → {$kini}";
        }

        sort($ganda);

        $this->assertSame([], $ganda,
            "Jumlah baris berubah sesudah dimuat ulang:\n  ".implode("\n  ", $ganda));
    }
}
