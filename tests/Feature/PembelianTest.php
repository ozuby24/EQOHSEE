<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Pembelian\{Lisensi, Pembayaran, Pesanan, Produk};
use App\Models\User;
use App\Support\{Pembelian, Qris};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Pembelian website dan aplikasi di dalamnya.
 *
 * ── MENGAPA PENJAGAANNYA LEBIH RAPAT DARIPADA MODUL LAIN ──
 *
 * Yang salah di sini berupa uang, dan uang tidak dapat diurungkan
 * dengan menyunting satu baris. Empat kegagalan di bawah tidak
 * menimbulkan satu pun galat:
 *
 * Harga yang datang dari layar. Sekali diterima, memesan seluruh
 * katalog seharga nol rupiah cukup dengan menyunting satu medan
 * tersembunyi — dan tagihannya terlihat wajar sepenuhnya di layar
 * admin, sebab nol itu memang yang tersimpan.
 *
 * Status yang dapat diisi massal. Pembeli menyatakan tagihannya sendiri
 * lunas, lisensinya terbit, dan tidak ada uang yang pernah masuk.
 *
 * Tautan bayar ber-id. Mengganti angka pada alamat menampilkan tagihan
 * orang lain lengkap dengan nama, telepon, dan nilainya — pada halaman
 * yang memang tidak meminta login.
 *
 * QRIS bernominal keliru. Ini yang paling berbahaya: ia dibayar tanpa
 * seorang pun membacanya lagi.
 */
class PembelianTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;

    protected function setUp(): void
    {
        parent::setUp();

        $this->c = Company::create(['name' => 'PT Uji Beli', 'code' => 'UBL']);
    }

    private function pengguna(bool $admin = false): User
    {
        return User::factory()->create([
            'company_id' => $this->c->id, 'is_admin' => $admin, 'email_verified_at' => now(),
        ]);
    }

    private function produk(int $harga, array $x = []): Produk
    {
        static $n = 0;
        $n++;

        return Produk::create($x + [
            'kode' => 'P'.$n, 'nama' => 'Produk '.$n, 'jenis' => Produk::APLIKASI,
            'harga' => $harga, 'masa_bulan' => 12, 'aktif' => true,
        ]);
    }

    /** QRIS statis contoh yang bentuknya sah — bukan kode merchant sungguhan. */
    private function qrisContoh(): string
    {
        $tlv = fn (string $t, string $v) => $t.str_pad((string) strlen($v), 2, '0', STR_PAD_LEFT).$v;

        $isi = $tlv('00', '01').$tlv('01', '11')
            .$tlv('26', $tlv('00', 'ID.CO.QRIS.WWW').$tlv('01', '936000914123456789'))
            .$tlv('52', '5411').$tlv('53', '360').$tlv('58', 'ID')
            .$tlv('59', 'EQOHSEE UJI').$tlv('60', 'SAMARINDA');

        return $isi.'6304'.Qris::crc16($isi.'6304');
    }

    /* ═══════════════════ harga tidak datang dari layar ═══════════════════ */

    /**
     * Penjagaan terpenting di seluruh modul ini.
     *
     * Kiriman menyertakan `harga` dan `total` yang dikarang. Keduanya
     * harus DIABAIKAN sepenuhnya; yang tersimpan harus harga katalog.
     */
    #[Test]
    public function harga_dan_total_diabaikan_bila_dikirim_dari_layar(): void
    {
        $this->actingAs($this->pengguna(true));

        $p = $this->produk(2_500_000);

        $this->post('/pembelian/pesan', [
            'pembeli_nama' => 'Calon Pembeli',
            'produk'       => [$p->id => 1],

            /* Medan yang disusupkan. Tidak satu pun boleh berpengaruh. */
            'total'    => 0,
            'harga'    => 0,
            'status'   => Pesanan::LUNAS,
        ])->assertSessionHasNoErrors();

        $pesanan = Pesanan::latest('id')->firstOrFail();

        $this->assertSame(2_500_000, $pesanan->total,
            'Total diambil dari kiriman, bukan dihitung dari harga katalog.');
        $this->assertSame(2_500_000, $pesanan->items()->first()->harga);
        $this->assertNotSame(Pesanan::LUNAS, $pesanan->status,
            'Status dapat disebut sendiri lewat formulir.');
    }

    /**
     * Penjagaan LAPIS KEDUA, diuji langsung pada modelnya.
     *
     * Uji di atas melewati controller, dan controller sudah menyaring
     * kiriman lewat validasi — sehingga `$fillable` tidak pernah
     * tersentuh sama sekali. Terbukti begitu: menjadikan `status` dan
     * `total` dapat diisi massal tidak menggagalkan satu pun uji.
     *
     * Padahal lapis itulah yang menahan jalur lain — perintah artisan,
     * seeder, atau controller baru yang meneruskan `$request->all()`.
     * Karena itu ia diuji di tempatnya sendiri.
     */
    #[Test]
    public function status_dan_total_tidak_dapat_diisi_massal(): void
    {
        $p = new Pesanan([
            'pembeli_nama' => 'A',
            'status' => Pesanan::LUNAS,
            'total'  => 99_000_000,
            'token'  => 'token-karangan',
        ]);

        $this->assertNotSame(Pesanan::LUNAS, $p->status,
            'status dapat diisi massal — pembeli dapat menyatakan tagihannya sendiri lunas.');
        $this->assertNotSame(99_000_000, $p->total,
            'total dapat diisi massal — nilai tagihan dapat ditentukan dari luar.');
        $this->assertNotSame('token-karangan', $p->token,
            'token dapat diisi massal — tautan bayar dapat ditentukan penyerang.');
    }

    /**
     * Harga disalin, bukan dirujuk.
     *
     * Menaikkan harga daftar sesudah tagihan terkirim tidak boleh
     * mengubah nilai tagihan itu — termasuk yang sudah dibayar, yang
     * akan berubah menjadi kurang bayar tanpa seorang pun berbuat apa
     * pun.
     */
    #[Test]
    public function harga_tagihan_tidak_ikut_berubah_saat_katalog_naik(): void
    {
        $this->actingAs($this->pengguna(true));

        $p = $this->produk(1_000_000);
        $pesanan = Pembelian::buat(['pembeli_nama' => 'A'], [$p->id => 2]);

        $this->assertSame(2_000_000, $pesanan->total);

        $p->update(['harga' => 9_000_000]);

        $this->assertSame(2_000_000, $pesanan->refresh()->total,
            'Tagihan lama ikut berubah saat harga katalog dinaikkan.');
        $this->assertSame(1_000_000, $pesanan->items()->first()->harga);
    }

    /** Total dihitung ulang saat barisnya berubah. */
    #[Test]
    public function total_dihitung_ulang_dari_barisnya(): void
    {
        $a = $this->produk(300_000);
        $b = $this->produk(700_000);

        $pesanan = Pembelian::buat(['pembeli_nama' => 'A'], [$a->id => 1, $b->id => 2]);

        $this->assertSame(300_000 + 1_400_000, $pesanan->total);

        $pesanan->items()->where('produk_id', $b->id)->delete();
        $pesanan->hitungUlang();

        $this->assertSame(300_000, $pesanan->refresh()->total);
    }

    /** Produk tak aktif tidak dapat dipesan. */
    #[Test]
    public function produk_tidak_aktif_dilewati(): void
    {
        $aktif = $this->produk(500_000);
        $mati  = $this->produk(500_000, ['aktif' => false]);

        $pesanan = Pembelian::buat(['pembeli_nama' => 'A'], [$aktif->id => 1, $mati->id => 1]);

        $this->assertSame(1, $pesanan->items()->count());
        $this->assertSame(500_000, $pesanan->total);
    }

    /** Tagihan tanpa baris tidak dapat dikirim — nol rupiah tidak boleh "dibayar". */
    #[Test]
    public function tagihan_kosong_tidak_dapat_dikirim(): void
    {
        $pesanan = Pembelian::buat(['pembeli_nama' => 'A'], []);

        $this->assertFalse(Pembelian::kirim($pesanan));
        $this->assertSame(Pesanan::DRAF, $pesanan->refresh()->status);
    }

    /* ═══════════════════ tautan bayar ═══════════════════ */

    /**
     * Halaman bayar dicari lewat TOKEN, dan id tidak dapat dipakai.
     */
    #[Test]
    public function halaman_bayar_tidak_dapat_dibuka_lewat_id(): void
    {
        $p = $this->produk(100_000);
        $pesanan = Pembelian::buat(['pembeli_nama' => 'A'], [$p->id => 1]);
        Pembelian::kirim($pesanan);

        /* Token yang benar: terbuka, tanpa login. */
        $this->get('/bayar/'.$pesanan->token)->assertOk();

        /* Id, dan token karangan: keduanya tidak ditemukan. */
        $this->get('/bayar/'.$pesanan->id)->assertNotFound();
        $this->get('/bayar/token-karangan')->assertNotFound();
    }

    /** Tokennya panjang dan acak — dua pesanan tidak pernah sama. */
    #[Test]
    public function token_acak_dan_tidak_berurutan(): void
    {
        $a = Pembelian::buat(['pembeli_nama' => 'A'], []);
        $b = Pembelian::buat(['pembeli_nama' => 'B'], []);

        $this->assertNotSame($a->token, $b->token);
        $this->assertGreaterThanOrEqual(32, strlen($a->token),
            'Token terlalu pendek untuk menjaga halaman tanpa login.');
    }

    /* ═══════════════════ bukti bayar tidak membuat lunas ═══════════════════ */

    #[Test]
    public function mengunggah_bukti_tidak_menjadikan_lunas(): void
    {
        Storage::fake('local');

        $p = $this->produk(750_000);
        $pesanan = Pembelian::buat(['pembeli_nama' => 'A'], [$p->id => 1]);
        Pembelian::kirim($pesanan);

        $this->post('/bayar/'.$pesanan->token.'/bukti', [
            'metode' => 'qris', 'atas_nama' => 'Pembeli',
            'tanggal_bayar' => now()->toDateString(),
            'bukti' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertSessionHasNoErrors();

        $pesanan->refresh();

        $this->assertSame(Pesanan::MENUNGGU_VERIFIKASI, $pesanan->status,
            'Unggahan gambar langsung menjadikan tagihan lunas.');
        $this->assertSame(0, $pesanan->lisensi()->count(),
            'Lisensi terbit tanpa seorang pun memverifikasi pembayarannya.');

        /* Nilainya diambil dari tagihan, bukan dari isian. */
        $this->assertSame(750_000, $pesanan->pembayaran()->first()->jumlah);
    }

    /** Bukan admin tidak dapat menyatakan lunas. */
    #[Test]
    public function hanya_admin_yang_dapat_menyatakan_lunas(): void
    {
        $p = $this->produk(500_000);
        $pesanan = Pembelian::buat(['pembeli_nama' => 'A'], [$p->id => 1]);
        Pembelian::kirim($pesanan);

        $this->actingAs($this->pengguna(false));
        $this->post('/pembelian/tagihan/'.$pesanan->id.'/verifikasi')->assertForbidden();

        $this->assertSame(0, $pesanan->refresh()->lisensi()->count());
    }

    /** Verifikasi menerbitkan lisensi — sekali saja, meski ditekan dua kali. */
    #[Test]
    public function verifikasi_menerbitkan_lisensi_dan_tidak_menggandakannya(): void
    {
        $admin = $this->pengguna(true);
        $this->actingAs($admin);

        $a = $this->produk(500_000);
        $b = $this->produk(300_000, ['masa_bulan' => 0]);

        $pesanan = Pembelian::buat(['pembeli_nama' => 'A', 'company_id' => $this->c->id],
            [$a->id => 1, $b->id => 1]);
        Pembelian::kirim($pesanan);

        $this->post('/pembelian/tagihan/'.$pesanan->id.'/verifikasi')->assertSessionHasNoErrors();

        $this->assertSame(Pesanan::LUNAS, $pesanan->refresh()->status);
        $this->assertSame(2, $pesanan->lisensi()->count());

        /* Ditekan lagi lewat HTTP: ditolak controller. */
        $this->post('/pembelian/tagihan/'.$pesanan->id.'/verifikasi')
            ->assertSessionHasErrors('status');

        /* Dan penerbitnya dipanggil LANGSUNG, melewati controller.
           Tanpa ini, penjagaan di dalam terbitkanLisensi() tidak pernah
           tersentuh — controller sudah menolak lebih dulu, sehingga
           membuang penyaringnya tidak menggagalkan satu pun uji.
           Terbukti begitu saat diuji-mutasi.

           Jalur langsung ini bukan mengada-ada: penerbitnya publik, dan
           tombol "terbitkan ulang" yang wajar ditambahkan kelak akan
           memanggilnya persis seperti ini. */
        $baru = Pembelian::terbitkanLisensi($pesanan->refresh());

        $this->assertSame(0, $baru, 'Penerbit lisensi menerbitkan ulang yang sudah ada.');
        $this->assertSame(2, $pesanan->refresh()->lisensi()->count(),
            'Lisensi tergandakan saat penerbitnya dipanggil dua kali.');

        /* Kuncinya tidak boleh kembar. */
        $kunci = $pesanan->lisensi()->pluck('kunci');
        $this->assertSame($kunci->count(), $kunci->unique()->count(),
            'Dua lisensi berbagi kunci yang sama.');

        /* Masa 0 bulan berarti tanpa batas — null, bukan tanggal jauh. */
        $selamanya = Lisensi::where('produk_id', $b->id)->firstOrFail();
        $this->assertNull($selamanya->berakhir);
        $this->assertTrue($selamanya->berlaku());
    }

    /** Tagihan lunas tidak dapat dibatalkan — lisensinya sudah terbit. */
    #[Test]
    public function tagihan_lunas_tidak_dapat_dibatalkan(): void
    {
        $admin = $this->pengguna(true);
        $this->actingAs($admin);

        $p = $this->produk(100_000);
        $pesanan = Pembelian::buat(['pembeli_nama' => 'A'], [$p->id => 1]);
        Pembelian::kirim($pesanan);
        Pembelian::tandaiLunas($pesanan, $admin);

        $this->post('/pembelian/tagihan/'.$pesanan->id.'/batal')->assertSessionHasErrors('status');

        $this->assertSame(Pesanan::LUNAS, $pesanan->refresh()->status);
    }

    /** Tagihan kedaluwarsa menolak bukti baru. */
    #[Test]
    public function tagihan_kedaluwarsa_menolak_bukti(): void
    {
        Storage::fake('local');

        $p = $this->produk(100_000);
        $pesanan = Pembelian::buat(['pembeli_nama' => 'A'], [$p->id => 1]);
        Pembelian::kirim($pesanan);

        $pesanan->forceFill(['kedaluwarsa_pada' => now()->subHour()])->save();

        $this->assertSame(Pesanan::KEDALUWARSA, $pesanan->refresh()->keadaan(),
            'Keadaan yang ditampilkan tidak memperhitungkan waktu.');

        $this->post('/bayar/'.$pesanan->token.'/bukti', [
            'metode' => 'qris', 'atas_nama' => 'X',
            'tanggal_bayar' => now()->toDateString(),
            'bukti' => UploadedFile::fake()->image('b.jpg'),
        ])->assertStatus(422);
    }

    /* ═══════════════════ QRIS ═══════════════════ */

    #[Test]
    public function qris_dinamis_memuat_nominal_tagihan(): void
    {
        config(['pembelian.qris_statis' => $this->qrisContoh()]);

        $p = $this->produk(1_234_567);
        $pesanan = Pembelian::buat(['pembeli_nama' => 'A'], [$p->id => 1]);

        $tujuan = Pembelian::tujuanBayar($pesanan);

        $this->assertTrue($tujuan['qris']['ada']);
        $this->assertTrue($tujuan['qris']['dinamis']);
        $this->assertTrue(Qris::sah($tujuan['qris']['payload']),
            'Kode dinamis yang dihasilkan tidak lolos pemeriksaan CRC-nya sendiri.');

        $this->assertSame('1234567', Qris::nominal($tujuan['qris']['payload']),
            'Nominal di dalam kode tidak sama dengan nilai tagihan.');

        $this->assertSame('EQOHSEE UJI', $tujuan['qris']['merchant']);
    }

    /**
     * Konfigurasi kosong menyebut SEBABNYA, bukan diam.
     *
     * Layar yang hanya menerima "tidak ada QR" menggambar kotak kosong,
     * dan yang membacanya menyimpulkan aplikasinya rusak alih-alih
     * konfigurasinya belum diisi.
     */
    #[Test]
    public function qris_belum_diatur_menyebutkan_sebabnya(): void
    {
        config(['pembelian.qris_statis' => '']);

        $pesanan = Pembelian::buat(['pembeli_nama' => 'A'], []);
        $tujuan  = Pembelian::tujuanBayar($pesanan);

        $this->assertFalse($tujuan['qris']['ada']);
        $this->assertStringContainsString('QRIS_STATIS', $tujuan['qris']['sebab']);
    }

    /** Kode yang terpotong ditolak, dan sebabnya disebut. */
    #[Test]
    public function qris_cacat_ditolak_bukan_diteruskan(): void
    {
        config(['pembelian.qris_statis' => substr($this->qrisContoh(), 0, -3)]);

        $pesanan = Pembelian::buat(['pembeli_nama' => 'A'], []);
        $tujuan  = Pembelian::tujuanBayar($pesanan);

        $this->assertFalse($tujuan['qris']['ada'],
            'Kode QRIS cacat diteruskan ke pembeli sebagai QR yang tidak dapat dipindai.');
        $this->assertStringContainsString('CRC', $tujuan['qris']['sebab']);
    }

    /**
     * CRC dihitung dengan varian yang benar.
     *
     * Ketiga varian CRC16 lain menghasilkan angka yang sama-sama
     * terlihat masuk akal, dan yang salah baru ketahuan saat kodenya
     * dipindai orang. Nilai acuannya dihitung dari spesifikasi
     * CRC16/CCITT-FALSE, bukan dari keluaran kelas ini sendiri —
     * membandingkan sesuatu dengan dirinya sendiri selalu lulus.
     */
    #[Test]
    public function crc16_memakai_varian_ccitt_false(): void
    {
        $this->assertSame('29B1', Qris::crc16('123456789'));
        $this->assertSame('FFFF', Qris::crc16(''));
    }

    /** Nominal nol atau negatif tidak boleh menghasilkan kode dinamis. */
    #[Test]
    public function nominal_tidak_wajar_ditolak(): void
    {
        $statis = $this->qrisContoh();

        $this->assertNull(Qris::dinamis($statis, 0));
        $this->assertNull(Qris::dinamis($statis, -5000));
    }

    /* ═══════════════════ berkas bukti ═══════════════════ */

    /**
     * Bukti bayar hanya dapat dibuka admin.
     *
     * Ia tangkapan layar mutasi rekening: memuat nomor rekening
     * pengirim, dan kerap saldonya.
     */
    #[Test]
    public function bukti_bayar_hanya_terbuka_bagi_admin(): void
    {
        $this->assertTrue(\App\Support\Berkas::bolehMembuka($this->pengguna(true), 'bkt'));
        $this->assertFalse(\App\Support\Berkas::bolehMembuka($this->pengguna(false), 'bkt'));
        $this->assertFalse(\App\Support\Berkas::bolehMembuka(null, 'bkt'));
    }

    /** Berkas selain gambar dan PDF ditolak. */
    #[Test]
    public function bukti_selain_gambar_dan_pdf_ditolak(): void
    {
        Storage::fake('local');

        $p = $this->produk(100_000);
        $pesanan = Pembelian::buat(['pembeli_nama' => 'A'], [$p->id => 1]);
        Pembelian::kirim($pesanan);

        $this->post('/bayar/'.$pesanan->token.'/bukti', [
            'metode' => 'qris', 'atas_nama' => 'X',
            'tanggal_bayar' => now()->toDateString(),
            'bukti' => UploadedFile::fake()->create('jahat.php', 8, 'text/php'),
        ])->assertSessionHasErrors('bukti');

        $this->assertSame(0, $pesanan->refresh()->pembayaran()->count());
    }

    /* ═══════════════════ katalog ═══════════════════ */

    /** Perintah pemasang tidak menimpa harga yang sudah diisi. */
    #[Test]
    public function memasang_katalog_ulang_tidak_menghapus_harga(): void
    {
        $this->artisan('pembelian:katalog')->assertSuccessful();

        $p = Produk::where('kode', 'WEBSITE')->firstOrFail();
        $p->update(['harga' => 15_000_000, 'aktif' => true]);

        $this->artisan('pembelian:katalog')->assertSuccessful();

        $p->refresh();

        $this->assertSame(15_000_000, $p->harga,
            'Menjalankan ulang pemasang mengembalikan harga ke nol.');
        $this->assertTrue($p->aktif, 'Menjalankan ulang pemasang mematikan katalog.');
    }

    /** Butir baru lahir tidak aktif dan berharga nol — tidak dapat terjual tanpa sengaja. */
    #[Test]
    public function butir_baru_belum_aktif(): void
    {
        $this->artisan('pembelian:katalog')->assertSuccessful();

        $this->assertGreaterThan(0, Produk::count());
        $this->assertSame(0, Produk::where('aktif', true)->count(),
            'Katalog terpasang langsung aktif dengan harga nol.');
    }
}
