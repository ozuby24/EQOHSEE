<?php

namespace Tests\Feature;

use App\Models\Pembelian\{Item, Pesanan, Produk};
use App\Models\User;
use App\Support\{Menu, Modules, Qris};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Etalase jual publik, dan daftar harganya.
 *
 * ── APA YANG DIJAGA DI SINI, DAN MENGAPA ──
 *
 * Etalase terbuka tanpa login karena pembelinya belum punya akun. Itu
 * membuat tiga kegagalan mungkin terjadi tanpa satu pun galat:
 *
 * Butir tak aktif ikut terpesan. Harga nol menjadi tagihan nol yang
 * "dibayar", lisensinya terbit, dan tidak ada uang yang pernah masuk.
 *
 * Pesanan publik dilempar ke layar di balik login. Yang muncul sesudah
 * menekan "beli" bukan halaman tagihan melainkan halaman masuk — dan
 * pembeli yang tidak punya akun berhenti di situ, selamanya.
 *
 * Pemesanan tanpa batas laju. Satu skrip menerbitkan ribuan tagihan
 * semalaman; tidak satu pun berisi uang, tetapi tagihan sungguhan
 * tenggelam di baliknya dan nomor registernya melompat ribuan angka.
 */
class EtalasePublikTest extends TestCase
{
    use RefreshDatabase;

    private function produk(int $harga, bool $aktif = true, array $x = []): Produk
    {
        static $n = 0;
        $n++;

        return Produk::create($x + [
            'kode' => 'E'.$n, 'nama' => 'Etalase '.$n, 'jenis' => Produk::APLIKASI,
            'harga' => $harga, 'masa_bulan' => 12, 'aktif' => $aktif,
        ]);
    }

    private function isi(array $lebih = []): array
    {
        return $lebih + ['pembeli_nama' => 'Calon Pembeli'];
    }

    /* ═══════════════════ terbuka tanpa login ═══════════════════ */

    public function test_etalase_terbuka_tanpa_login(): void
    {
        $this->produk(9_000_000);

        $this->get('/katalog')->assertOk();
    }

    /**
     * Kartunya digambar dari daftar modul, bukan dari baris produk.
     *
     * Digambar dari produk, katalog yang harganya belum ditetapkan
     * tampil sebagai halaman kosong — dan halaman kosong terbaca sebagai
     * perusahaan yang tidak punya apa-apa untuk dijual, bukan sebagai
     * harga yang belum diumumkan.
     */
    public function test_seluruh_aplikasi_tampil_meski_belum_ada_yang_berharga(): void
    {
        $this->assertSame(0, Produk::count(), 'Ujinya dimulai dari katalog kosong.');

        $props = $this->get('/katalog')->assertOk()->viewData('page')['props'];

        $this->assertCount(count(Modules::perKunciMenu()), $props['aplikasi'],
            'Jumlah kartu etalase tidak sama dengan jumlah modul yang dijual.');

        $this->assertFalse($props['adaHarga']);

        foreach ($props['aplikasi'] as $a) {
            $this->assertNull($a['id'], "Kartu {$a['nama']} punya id padahal produknya belum ada.");
            $this->assertNotEmpty($a['ket'], "Kartu {$a['nama']} tampil tanpa keterangan jual.");
            $this->assertNotEmpty($a['ikon'], "Kartu {$a['nama']} tampil tanpa ikon.");
        }
    }

    /** Harga menempel pada kartunya lewat modul_kunci, bukan lewat nama. */
    public function test_harga_menempel_pada_kartu_modulnya(): void
    {
        $kunci = array_key_first(Modules::perKunciMenu());

        $this->produk(7_250_000, true, ['modul_kunci' => $kunci]);

        $props = $this->get('/katalog')->assertOk()->viewData('page')['props'];

        $cocok = collect($props['aplikasi'])->firstWhere('harga', 7_250_000);

        $this->assertNotNull($cocok, 'Harga produk tidak menempel pada satu kartu pun.');
        $this->assertNotNull($cocok['id']);

        $lain = collect($props['aplikasi'])->where('id', null);
        $this->assertCount(count($props['aplikasi']) - 1, $lain,
            'Satu harga menempel pada lebih dari satu kartu.');
    }

    /* ═══════════════════ gambar dan vektor ═══════════════════ */

    /**
     * Tiap kartu aspek punya bahan untuk digambar.
     *
     * Kartu aspek boleh tidak berfoto — berkasnya memang belum tentu
     * ada, dan layar menggambar gradien beserta ikon pilarnya sebagai
     * ganti. Yang TIDAK boleh hilang nama ikonnya: tanpa itu kartunya
     * tergambar sebagai bidang warna polos tanpa satu bentuk pun, dan
     * kegagalannya diam — tidak ada galat, hanya delapan kotak yang
     * tidak dapat dibedakan.
     */
    public function test_setiap_kartu_aspek_punya_ikon_dan_jumlahnya(): void
    {
        $props = $this->get('/katalog')->assertOk()->viewData('page')['props'];

        $this->assertNotEmpty($props['pilar'], 'Etalase tidak menggambar satu aspek pun.');

        foreach ($props['pilar'] as $slug => $w) {
            $this->assertNotEmpty($w['ikon'], "Aspek {$slug} dikirim tanpa nama ikon.");
            $this->assertNotEmpty($w['nama'], "Aspek {$slug} dikirim tanpa nama.");

            foreach (['warna', 'deep', 'light'] as $k) {
                $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $w[$k],
                    "Warna '{$k}' aspek {$slug} bukan hex 6 digit — gradiennya tidak akan terbentuk.");
            }

            $this->assertGreaterThan(0, $w['jumlah'],
                "Aspek {$slug} tampil sebagai kartu saring padahal tidak menaungi satu aplikasi pun — "
                .'mengkliknya mengosongkan seluruh grid, dan yang membacanya menduga katalognya habis.');
        }
    }

    /** Jumlah pada kartu aspek harus sama dengan yang benar-benar tersaring. */
    public function test_jumlah_aspek_sama_dengan_kartu_yang_tersaring(): void
    {
        $props = $this->get('/katalog')->assertOk()->viewData('page')['props'];

        foreach ($props['pilar'] as $slug => $w) {
            $nyata = collect($props['aplikasi'])->where('pilar', $slug)->count();

            $this->assertSame($nyata, $w['jumlah'],
                "Aspek {$slug} menjanjikan {$w['jumlah']} aplikasi tetapi menyaring {$nyata}.");
        }
    }

    /**
     * Latar hero dikirim, dan alamatnya benar-benar menunjuk berkas.
     *
     * Media::url() mengembalikan null bila berkasnya belum ditaruh, dan
     * layar memakai null itu untuk menggambar gradien sebagai ganti.
     * Yang tidak boleh terjadi kebalikannya: alamat terkirim untuk
     * berkas yang tidak ada, yang tergambar sebagai bingkai gambar
     * rusak — di halaman jual lebih buruk daripada tidak ada gambar
     * sama sekali.
     */
    public function test_latar_yang_dikirim_benar_benar_ada_berkasnya(): void
    {
        $props = $this->get('/katalog')->assertOk()->viewData('page')['props'];

        $this->assertArrayHasKey('latar', $props);

        $alamat = array_filter([
            $props['latar']['video'] ?? null,
            $props['latar']['poster'] ?? null,
            $props['latar']['paket'] ?? null,
            ...collect($props['pilar'])->pluck('foto')->all(),
        ]);

        foreach ($alamat as $url) {
            $jalur = public_path(parse_url((string) $url, PHP_URL_PATH) ?? '');

            $this->assertFileExists($jalur,
                "Etalase mengirim alamat {$url} untuk berkas yang tidak ada.");
        }
    }

    /**
     * Berkasnya tidak ada: yang dikirim NULL, bukan alamat yang mengarah
     * ke ketiadaan.
     *
     * Uji di atas tidak dapat menangkap ini sendirian — seluruh berkas
     * galeri kebetulan memang ada, sehingga alamat yang dikarang pun
     * tetap menunjuk berkas nyata. Di sini akar medianya dialihkan ke
     * map kosong, sehingga cabang yang memeriksa keberadaan berkas
     * benar-benar terpakai.
     *
     * Yang dijaga bukan kerapian: alamat untuk berkas yang tidak ada
     * tergambar sebagai bingkai gambar rusak, dan bingkai rusak di
     * halaman jual lebih buruk daripada tidak ada gambar sama sekali.
     */
    public function test_berkas_yang_tidak_ada_dikirim_sebagai_null(): void
    {
        config(['media.akar' => 'media-yang-tidak-pernah-ada']);

        $props = $this->get('/katalog')->assertOk()->viewData('page')['props'];

        $this->assertNull($props['latar']['video'], 'Alamat video dikirim untuk berkas yang tidak ada.');
        $this->assertNull($props['latar']['poster'], 'Alamat poster dikirim untuk berkas yang tidak ada.');
        $this->assertNull($props['latar']['paket'], 'Alamat latar paket dikirim untuk berkas yang tidak ada.');

        foreach ($props['pilar'] as $slug => $w) {
            $this->assertNull($w['foto'],
                "Aspek {$slug} mengirim alamat foto untuk berkas yang tidak ada.");
        }
    }

    /* ═══════════════════ pesanan publik ═══════════════════ */

    public function test_pesanan_publik_berakhir_di_halaman_bayar_bukan_halaman_masuk(): void
    {
        $p = $this->produk(5_000_000);

        $r = $this->post('/katalog/pesan', $this->isi(['produk' => [$p->id => 1]]));

        $pesanan = Pesanan::firstOrFail();

        $r->assertRedirect('/bayar/'.$pesanan->token);

        /* Ujungnya benar-benar terbuka tanpa login — bukan hanya
           alamatnya yang benar. */
        $this->get('/bayar/'.$pesanan->token)->assertOk();
    }

    public function test_harga_dibaca_dari_basis_data_bukan_dari_kiriman(): void
    {
        $p = $this->produk(5_000_000);

        $this->post('/katalog/pesan', $this->isi([
            'produk' => [$p->id => 2],

            /* Dikarang oleh pengirimnya. Harus diabaikan sepenuhnya. */
            'harga'  => 1,
            'total'  => 1,
        ]));

        $pesanan = Pesanan::firstOrFail();

        $this->assertSame(10_000_000, (int) $pesanan->total,
            'Total tagihan mengikuti angka yang dikirim pembeli.');
        $this->assertSame(5_000_000, (int) Item::firstOrFail()->harga);
    }

    /**
     * Butir tak aktif tidak ikut terpesan.
     *
     * Yang tidak aktif berharga nol; ikut terpesan, ia menjadi tagihan
     * nol rupiah yang dapat "dibayar" dan lisensinya terbit tanpa satu
     * rupiah pun masuk — dan angka nol itu tidak terlihat janggal di
     * layar mana pun, sebab nol memang yang tersimpan.
     */
    public function test_butir_tak_aktif_tidak_ikut_terpesan(): void
    {
        $hidup = $this->produk(5_000_000);
        $mati  = $this->produk(0, false);

        $this->post('/katalog/pesan', $this->isi([
            'produk' => [$hidup->id => 1, $mati->id => 1],
        ]));

        $pesanan = Pesanan::firstOrFail();

        $this->assertSame(1, $pesanan->items()->count());
        $this->assertNull($pesanan->items()->where('produk_id', $mati->id)->first(),
            'Produk tak aktif ikut masuk ke tagihan.');
    }

    /** Seluruhnya tak aktif: tidak ada tagihan yang tertinggal sama sekali. */
    public function test_pesanan_yang_seluruh_butirnya_mati_tidak_menyisakan_tagihan(): void
    {
        $mati = $this->produk(0, false);

        $this->post('/katalog/pesan', $this->isi(['produk' => [$mati->id => 1]]))
            ->assertSessionHasErrors('produk');

        $this->assertSame(0, Pesanan::count(),
            'Tagihan kosong tertinggal di daftar penjual.');
    }

    public function test_pesanan_publik_tanpa_nama_ditolak(): void
    {
        $p = $this->produk(5_000_000);

        $this->post('/katalog/pesan', ['produk' => [$p->id => 1]])
            ->assertSessionHasErrors('pembeli_nama');

        $this->assertSame(0, Pesanan::count());
    }

    /**
     * Laju pemesanannya dibatasi.
     *
     * Diperiksa pada RUTENYA, bukan dengan menembakkan tujuh permintaan:
     * yang dijaga adalah keberadaan penjagaannya, dan uji yang benar-benar
     * memicu 429 akan ikut mewarisi keadaan penghitung antar uji.
     */
    public function test_pemesanan_publik_dibatasi_lajunya(): void
    {
        $rute = collect(Route::getRoutes())->first(
            fn ($r) => $r->getName() === 'katalog.pesan');

        $this->assertNotNull($rute);

        $throttle = collect($rute->gatherMiddleware())
            ->first(fn ($m) => is_string($m) && str_starts_with($m, 'throttle'));

        $this->assertNotNull($throttle,
            'Pemesanan publik tidak dibatasi lajunya — satu skrip dapat '
            .'menerbitkan ribuan tagihan semalaman.');
    }

    /* ═══════════════════ daftar harga — hanya admin ═══════════════════ */

    public function test_daftar_harga_tertutup_bagi_yang_bukan_admin(): void
    {
        $p = $this->produk(5_000_000);

        $this->actingAs(User::factory()->create(['is_admin' => false, 'email_verified_at' => now()]));

        $this->get('/pembelian/produk')->assertForbidden();

        $this->put('/pembelian/produk/'.$p->id, [
            'harga' => 1, 'masa_bulan' => 12, 'aktif' => true,
        ])->assertForbidden();

        $this->assertSame(5_000_000, (int) $p->fresh()->harga,
            'Bukan admin berhasil mengubah harga.');
    }

    public function test_admin_dapat_mengubah_harga(): void
    {
        $p = $this->produk(5_000_000);

        $this->actingAs(User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]));

        $this->put('/pembelian/produk/'.$p->id, [
            'harga' => 12_500_000, 'masa_bulan' => 24, 'aktif' => true,
        ])->assertSessionHasNoErrors();

        $p->refresh();

        $this->assertSame(12_500_000, (int) $p->harga);
        $this->assertSame(24, (int) $p->masa_bulan);
        $this->assertTrue($p->aktif);
    }

    /**
     * Nol tidak dapat diaktifkan.
     *
     * Butir aktif berharga nol dapat dipesan, ditagihkan, "dibayar", dan
     * lisensinya terbit tanpa satu rupiah pun masuk — dan tidak satu pun
     * angka yang terlihat janggal, sebab nol itu memang yang tersimpan.
     */
    public function test_butir_berharga_nol_tidak_dapat_diaktifkan(): void
    {
        $p = $this->produk(0, false);

        $this->actingAs(User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]));

        $this->put('/pembelian/produk/'.$p->id, [
            'harga' => 0, 'masa_bulan' => 12, 'aktif' => true,
        ])->assertSessionHasNoErrors();

        $this->assertFalse($p->fresh()->aktif,
            'Butir berharga nol berhasil diaktifkan — ia dapat terjual seharga nol.');
    }

    public function test_harga_minus_ditolak(): void
    {
        $p = $this->produk(5_000_000);

        $this->actingAs(User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]));

        $this->put('/pembelian/produk/'.$p->id, [
            'harga' => -1, 'masa_bulan' => 12, 'aktif' => true,
        ])->assertSessionHasErrors('harga');

        $this->assertSame(5_000_000, (int) $p->fresh()->harga);
    }

    /* ═══════════════════ katalog terpasang ═══════════════════ */

    /**
     * Tiap butir jual punya kalimat jualnya.
     *
     * Kartu tanpa keterangan bukan galat — ia hanya kartu yang tidak
     * memberi tahu apa pun tentang apa yang dibeli, dan itu persis
     * bentuk kegagalan yang lolos seluruh pemeriksaan lain.
     */
    public function test_setiap_butir_katalog_punya_keterangan_jual(): void
    {
        $this->artisan('pembelian:katalog')->assertSuccessful();

        $tanpa = Produk::whereNull('keterangan')->orWhere('keterangan', '')->pluck('kode')->all();

        $this->assertSame([], $tanpa,
            'Butir katalog berikut terpasang tanpa keterangan jual: '.implode(', ', $tanpa));
    }

    /** Pembelian sendiri tidak dijual: ia lorong menuju pembayaran, bukan barang. */
    public function test_modul_pembelian_tidak_ikut_dijual(): void
    {
        $this->artisan('pembelian:katalog')->assertSuccessful();

        foreach (['APP-PEMBELIAN', 'APP-DASBOR', 'APP-ADMIN', 'APP-PERSONALIA'] as $kode) {
            $this->assertNull(Produk::where('kode', $kode)->first(),
                "Butir {$kode} terpasang di katalog padahal bukan barang yang dijual.");
        }
    }

    /**
     * Modul yang sudah tidak ada berhenti dijual.
     *
     * Kebalikan dari pemasangan, dan yang lebih berbahaya: modul yang
     * dibuang dari menu tetap muncul di katalog dengan harga terpasang,
     * sehingga orang membayar sesuatu yang tidak ada lagi.
     */
    public function test_produk_usang_yang_belum_pernah_dipesan_dibuang(): void
    {
        Produk::create([
            'kode' => 'APP-SUDAH-TIADA', 'nama' => 'Modul Yang Dibuang',
            'jenis' => Produk::APLIKASI, 'harga' => 9_000_000, 'masa_bulan' => 12, 'aktif' => true,
        ]);

        $this->artisan('pembelian:katalog')->assertSuccessful();

        $this->assertNull(Produk::where('kode', 'APP-SUDAH-TIADA')->first(),
            'Produk yang modulnya sudah tidak ada tetap terjual.');
    }

    /**
     * Yang pernah dipesan hanya dinonaktifkan.
     *
     * Menghapusnya membuat tagihan yang sudah terbit kehilangan rujukan
     * barangnya — dan tagihan tanpa nama barang tidak dapat
     * dipertanggungjawabkan kepada pembelinya.
     */
    public function test_produk_usang_yang_pernah_dipesan_dipertahankan(): void
    {
        $p = Produk::create([
            'kode' => 'APP-SUDAH-TIADA-2', 'nama' => 'Modul Yang Dibuang',
            'jenis' => Produk::APLIKASI, 'harga' => 9_000_000, 'masa_bulan' => 12, 'aktif' => true,
        ]);

        $pesanan = Pesanan::create(['pembeli_nama' => 'Pembeli Lama']);

        Item::create([
            'pesanan_id' => $pesanan->id, 'produk_id' => $p->id, 'nama' => $p->nama,
            'harga' => $p->harga, 'masa_bulan' => 12, 'jumlah' => 1, 'subtotal' => $p->harga,
        ]);

        $this->artisan('pembelian:katalog')->assertSuccessful();

        $p->refresh();

        $this->assertNotNull($p, 'Produk yang pernah dipesan ikut terhapus.');
        $this->assertFalse($p->aktif, 'Produk usang tetap dijual.');
    }

    /* ═══════════════════ QRIS bawaan ═══════════════════ */

    /**
     * QRIS bawaan benar-benar terpakai ketika .env-nya kosong.
     *
     * `env('QRIS_STATIS', $bawaan)` TIDAK cukup: baris "QRIS_STATIS="
     * tanpa isi — persis yang tertulis di .env.example — membuat env()
     * mengembalikan untai kosong, bukan nilai bawaannya. Halaman bayar
     * lalu menyebut "QRIS belum tersedia" pada pemasangan yang sudah
     * lengkap, dan tidak ada satu pun galat yang memberi tahu.
     */
    public function test_qris_bawaan_terpakai_saat_env_kosong(): void
    {
        $q = (string) config('pembelian.qris_statis');

        $this->assertNotSame('', $q,
            'QRIS bawaan tidak terpakai — halaman bayar akan menyebut "QRIS belum tersedia".');
        $this->assertTrue(Qris::sah($q), 'QRIS bawaan tidak lolos pemeriksaan CRC.');
        $this->assertSame('EQOHSEE WEB DEVELOPER', Qris::merchant($q));
    }

    /** Nominal tagihan benar-benar tersisip, dan hasilnya tetap sah. */
    public function test_nominal_tersisip_ke_qris_bawaan(): void
    {
        $q = (string) config('pembelian.qris_statis');

        foreach ([1, 5_500_000, 84_500_000] as $n) {
            $d = Qris::dinamis($q, $n);

            $this->assertNotNull($d, "QRIS bernominal {$n} gagal disusun.");
            $this->assertTrue(Qris::sah($d), "QRIS bernominal {$n} tidak lolos CRC.");
            $this->assertSame((string) $n, Qris::nominal($d));
            $this->assertSame('EQOHSEE WEB DEVELOPER', Qris::merchant($d),
                'Merchant berubah saat nominalnya disisipkan.');
        }
    }

    /* ═══════════════════ halaman depan tidak ikut jatuh ═══════════════════ */

    /**
     * Harga hilang, halaman depan tetap berdiri.
     *
     * Sebelum ada bagian harga, halaman depan tidak menyentuh basis data
     * sama sekali — dan itu sifat yang berharga, bukan kebetulan. Satu
     * tabel yang belum termigrasi sesudah pemasangan, atau basis data
     * yang sedang tersendat, tanpa penjagaan ini cukup untuk membuat
     * satu-satunya halaman yang dilihat calon pembeli menjadi galat 500.
     *
     * Tabelnya benar-benar dibuang, bukan disamarkan: yang dijaga adalah
     * perilakunya ketika membaca produk memang tidak mungkin.
     */
    public function test_halaman_depan_tetap_terbuka_saat_tabel_produk_tidak_ada(): void
    {
        Schema::drop('beli_produk');

        $props = $this->get('/')->assertOk()->viewData('page')['props'];

        $this->assertNull($props['jual']['paket']);
        $this->assertNull($props['jual']['termurah']);
        $this->assertSame(0, $props['jual']['jumlah']);
    }

    /** Ketika tabelnya ada dan berisi, angkanya memang tampil. */
    public function test_halaman_depan_menampilkan_harga_paket_dan_termurah(): void
    {
        Produk::create([
            'kode' => 'WEBSITE', 'nama' => 'Paket Menyeluruh', 'jenis' => Produk::WEBSITE,
            'harga' => 75_000_000, 'masa_bulan' => 12, 'aktif' => true, 'urutan' => 0,
        ]);

        /* Urutannya SEBELUM paket, dan sengaja. Tanpa penyaringan jenis,
           baris inilah yang terambil sebagai "paket menyeluruh" — dan
           yang tercetak di halaman depan menjadi harga satu aplikasi
           dengan kalimat "seluruh aplikasi di dalamnya". */
        $this->produk(9_500_000, true, ['urutan' => -10]);
        $this->produk(5_500_000);

        /* Tak aktif: tidak boleh ikut dihitung. */
        $this->produk(1_000_000, false);

        /* AKTIF tetapi berharga nol. Baris seperti ini memang mungkin
           ada — pembelian:katalog memasang butir baru berharga nol, dan
           keaktifannya milik pemakainya — dan tanpa penyaringan harga,
           halaman depan menjanjikan "mulai Rp 0". */
        $this->produk(0, true);

        $props = $this->get('/')->assertOk()->viewData('page')['props'];

        $this->assertSame(75_000_000, $props['jual']['paket']['harga'],
            'Harga paket menyeluruh tidak diambil dari butir berjenis website.');
        $this->assertSame('Paket Menyeluruh', $props['jual']['paket']['nama']);
        $this->assertSame(5_500_000, $props['jual']['termurah'],
            'Harga termurah salah — nol atau butir tak aktif ikut terhitung.');
        $this->assertSame(2, $props['jual']['jumlah']);
    }

    /* ═══════════════════ modul dan menu tetap sejalan ═══════════════════ */

    /**
     * Tiap modul yang dijual punya kalimat jual di Modules.
     *
     * Modul baru yang ditambahkan ke Menu tetapi lupa dimasukkan ke
     * Modules tidak menimbulkan galat: kartunya tetap tergambar, hanya
     * tanpa satu kalimat pun yang menjelaskan apa yang dibeli.
     */
    public function test_setiap_modul_yang_dijual_punya_kalimat_jual(): void
    {
        $tanpaKalimat = [];

        foreach (Menu::all() as $kunci => $m) {
            if (in_array($kunci, ['dasbor', 'admin', 'personalia', 'pembelian'], true)) continue;

            if (! isset(Modules::perKunciMenu()[$kunci])) {
                $tanpaKalimat[] = $kunci.' ('.$m['label'].')';
            }
        }

        $this->assertSame([], $tanpaKalimat,
            'Modul berikut dijual tetapi tidak punya kalimat jual di Modules: '
            .implode(', ', $tanpaKalimat));
    }
}
