<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use App\Support\{Dasbor, Menu};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Route};
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Dasbor yang mencakup seluruh modul di bilah samping.
 *
 * Sebelumnya enam modul terpilih yang muncul, dan lima belas sisanya
 * hanya terlihat oleh orang yang sudah tahu harus membukanya. Yang tidak
 * tahu tidak akan pernah tahu: tidak ada galat, tidak ada tanda, hanya
 * modul yang tidak pernah dibuka siapa pun sampai auditor menanyakannya.
 *
 * Karena itu yang dijaga di sini BUKAN bentuk tampilannya melainkan
 * cakupannya — tiap modul yang punya menu harus punya ubinnya, dan tiap
 * ubin harus mengarah ke halaman yang benar-benar ada.
 */
class DasborTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;

    protected function setUp(): void
    {
        parent::setUp();

        $this->c = Company::create(['name' => 'PT Uji Dasbor', 'doc_no_prefix' => 'UD']);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'is_admin' => true, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);
    }

    /**
     * Tiap ubin mengarah ke rute yang ada.
     *
     * Nama rute yang salah ketik tidak menimbulkan galat sampai ada yang
     * membuka dasbornya — dan saat itu terjadi, seluruh halaman gagal,
     * bukan hanya satu ubinnya.
     */
    #[Test]
    public function tiap_ubin_mengarah_ke_rute_yang_ada(): void
    {
        $this->actingAs($u = $this->admin());

        $hilang = [];

        foreach (Dasbor::modul($u) as $m) {
            if (!Route::has($m['rute'])) $hilang[] = "{$m['nama']} → {$m['rute']}";
        }

        $this->assertSame([], $hilang,
            "Ubin dasbor menunjuk rute yang tidak terdaftar:\n- ".implode("\n- ", $hilang));
    }

    /**
     * Tiap modul bermenu punya ubinnya sendiri.
     *
     * Kecuali dua yang memang bukan pekerjaan: Personalia berisi data
     * diri, dan Learning Center sudah punya seluruh panel belajarnya
     * sendiri di halaman yang sama. Keduanya disebut namanya di sini
     * supaya modul KETIGA yang tertinggal tidak ikut lolos diam-diam.
     */
    #[Test]
    public function tiap_modul_bilah_samping_punya_ubin(): void
    {
        $this->actingAs($u = $this->admin());

        $berubin = array_unique(array_column(Dasbor::modul($u), 'modul'));
        $bermenu = array_keys(Menu::untuk($u));

        /* Dasbor sendiri tidak berubin: ia halaman yang MEMUAT ubinnya. */
        $kecuali = ['dasbor', 'personalia', 'lms', 'admin'];
        $kurang  = array_diff($bermenu, $berubin, $kecuali);

        $this->assertSame([], array_values($kurang),
            'Modul ini ada di bilah samping tetapi tidak punya ubin di dasbor: '
            .implode(', ', $kurang));
    }

    /**
     * Tidak ada ubin untuk modul yang tidak boleh dilihat penggunanya.
     *
     * Ubin yang mengarah ke halaman yang menolak membukanya lebih buruk
     * daripada ubin yang tidak ada: ia menjanjikan sesuatu yang tidak
     * dapat ditepati, dan yang mengkliknya menyangka dirinya salah.
     */
    #[Test]
    public function ubin_hanya_untuk_modul_yang_boleh_dilihat(): void
    {
        $biasa = User::factory()->create([
            'is_admin' => false, 'company_id' => $this->c->id, 'email_verified_at' => now(),
        ]);

        $this->actingAs($biasa);

        $boleh = array_keys(Menu::untuk($biasa));

        foreach (Dasbor::modul($biasa) as $m) {
            $this->assertContains($m['modul'], $boleh,
                "Ubin \"{$m['nama']}\" muncul untuk pengguna yang modulnya tidak ada di bilah sampingnya.");
        }
    }

    /**
     * Tiap ubin membawa ikon modulnya sendiri.
     *
     * Ikonnya diambil dari bilah samping, sehingga ubin dan menu memakai
     * lambang yang sama. Ubin tanpa ikon merender kotak kosong — tidak
     * ada galat, hanya deretan kotak abu yang membuat dasbor tampak
     * rusak setengah jadi.
     */
    #[Test]
    public function tiap_ubin_membawa_ikon(): void
    {
        $this->actingAs($u = $this->admin());

        foreach (Dasbor::modul($u) as $m) {
            $this->assertNotEmpty($m['ikon'],
                "Ubin \"{$m['nama']}\" tidak punya ikon; modul \"{$m['modul']}\" mungkin salah nama.");
        }
    }

    /** Dasbor benar-benar mengirimkan ubinnya ke tampilan. */
    #[Test]
    public function halaman_dasbor_mengirim_ubin_modul(): void
    {
        $this->actingAs($this->admin());

        $this->get(route('dasbor'))
            ->assertOk()
            ->assertInertia(fn (Assert $h) => $h
                ->has('modul', fn (Assert $m) => $m
                    ->has('0.nama')->has('0.nilai')->has('0.url')
                    ->has('0.nada')->has('0.warna')->has('0.ikon')
                    ->etc())
                ->etc());
    }

    /**
     * Jumlah kueri tidak tumbuh bersama jumlah data.
     *
     * Tiga ubin menghitungnya di PHP karena aturannya tidak dapat
     * ditulis sebagai satu kueri — masa berlaku efektif, saldo stok dari
     * mutasinya, dan pelanggaran baku mutu terhadap ambang yang berlaku
     * sekarang. Ketiganya rawan memuat relasinya satu per satu, dan
     * dasbor adalah halaman yang dibuka paling sering.
     */
    #[Test]
    public function kueri_dasbor_tidak_tumbuh_bersama_data(): void
    {
        $this->actingAs($u = $this->admin());

        $buat = function (int $n) {
            for ($i = 0; $i < $n; $i++) {
                $p = \App\Models\Paspor::create([
                    'company_id' => $this->c->id, 'nama' => 'Dasbor'.uniqid(),
                    'nik' => 'DB'.uniqid(), 'jabatan' => 'Operator', 'status' => 'aktif',
                ]);

                $p->mcu()->create([
                    'tgl_periksa' => now(), 'tgl_expired' => now()->addYear(), 'hasil' => 'Fit',
                ]);

                $k = $p->kartu()->create([
                    'jenis' => \App\Support\AlurMiner::KARTU_PERMIT, 'nomor' => 'DB/'.uniqid(),
                    'tgl_terbit' => now(), 'tgl_expired' => now()->addYear(),
                ]);

                \App\Models\PasporKartu::whereKey($k->id)
                    ->update(['status' => \App\Support\Alur::DISETUJUI]);

                \App\Models\GudangBarang::create([
                    'company_id' => $this->c->id, 'kode' => 'BR'.uniqid(),
                    'nama' => 'Barang '.$i, 'satuan' => 'pcs', 'stok_min' => 5,
                    'kategori' => 'material',
                ]);
            }
        };

        $hitung = function () use ($u): int {
            DB::flushQueryLog();
            DB::enableQueryLog();

            Dasbor::modul($u);

            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $buat(2);
        $kecil = $hitung();

        $buat(8);
        $besar = $hitung();

        $this->assertSame($kecil, $besar,
            "Jumlah kueri dasbor tumbuh bersama datanya ({$kecil} lalu {$besar}): "
            .'ada relasi yang dimuat satu per satu di dalam perulangan.');
    }
}
