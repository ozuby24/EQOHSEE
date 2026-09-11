<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Hr\{PolaRoster, Regu, ReguAnggota, Roster};
use App\Models\Miners\{Induksi, InduksiOrang, Mcu, McuOrang, Pekerja, Permit};
use App\Models\User;
use App\Support\Hr\{Fatigue, Kelayakan, MasterRoster, Penyusun};
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Batas waktu kerja dan blokir kelayakan.
 *
 * Yang diuji di sini adalah aturan yang HANYA berarti bila ditegakkan
 * server: penyusun roster dapat mengirim rentang apa pun, dan tombol
 * yang disembunyikan tetap dapat ditekan lewat permintaan langsung.
 *
 * Dua di antaranya mudah salah ke arah yang berlawanan, dan keduanya
 * diuji dari dua sisi:
 *
 *   · terlalu longgar — roster 21 hari berturut-turut lolos karena
 *     tiap harinya sendiri sah
 *   · terlalu ketat — pola kantor 5:2 ditolak karena akhir pekannya
 *     lebih pendek daripada lima hari
 */
class RosterFatigueTest extends TestCase
{
    use RefreshDatabase;

    private Company $c;

    protected function setUp(): void
    {
        parent::setUp();

        MasterRoster::pasang();

        $this->c = Company::create(['name' => 'PT Uji Roster']);
    }

    /** Rangkaian roster satu orang dari untaian: K = kerja, titik = libur. */
    private function deret(string $pola, int $jam = 11, ?Carbon $mulai = null): array
    {
        $mulai ??= Carbon::create(2026, 3, 1);
        $p = Pekerja::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'nama' => 'Uji '.uniqid(), 'status' => 'aktif',
        ]);

        $baris = [];

        foreach (str_split($pola) as $i => $huruf) {
            $kerja = $huruf === 'K';

            $baris[] = Roster::withoutGlobalScopes()->create([
                'company_id' => $this->c->id,
                'pekerja_id' => $p->id,
                'tanggal'    => $mulai->copy()->addDays($i),
                'keadaan'    => $kerja ? 'kerja' : 'libur',
                'jam'        => $kerja ? $jam : 0,
            ]);
        }

        return $baris;
    }

    /** @return list<string> jenis pelanggaran yang ditemukan */
    private function jenis(array $baris): array
    {
        return array_values(array_unique(array_column(Fatigue::periksa($baris), 'jenis')));
    }

    /* ═══════════ batas yang menolak ═══════════ */

    #[Test]
    public function test_lebih_dari_sebelas_jam_sehari_ditolak(): void
    {
        $this->assertContains('jam_harian', $this->jenis($this->deret('KKK', 12)));
        $this->assertNotContains('jam_harian', $this->jenis($this->deret('KKK', 11)));
    }

    #[Test]
    public function test_lebih_dari_empat_belas_hari_beruntun_ditolak(): void
    {
        /* Empat belas hari PERSIS masih sah — itu justru pola 14:7 yang
           dipakai seluruh tambang. Lima belas melanggar. */
        $this->assertNotContains('hari_beruntun', $this->jenis($this->deret(str_repeat('K', 14).'.......')));
        $this->assertContains('hari_beruntun', $this->jenis($this->deret(str_repeat('K', 15).'.......')));
    }

    #[Test]
    public function test_rangkaian_yang_tiap_harinya_sah_tetap_tertangkap(): void
    {
        /* Dua puluh satu hari berturut-turut, tiap harinya 11 jam —
           tiap BARIS sah sendirian. Pemeriksaan yang menilai baris satu
           per satu meloloskannya, dan itu bentuk pelanggaran yang
           paling sering terjadi: ia lahir dari menyambung dua periode
           yang masing-masing benar. */
        $temuan = $this->jenis($this->deret(str_repeat('K', 21)));

        $this->assertContains('hari_beruntun', $temuan);
        $this->assertNotContains('jam_harian', $temuan, 'Tiap harinya memang 11 jam — sah sendirian.');
    }

    #[Test]
    public function test_lebih_dari_seratus_lima_puluh_empat_jam_per_empat_belas_hari_ditolak(): void
    {
        /* 14 × 11 = 154, tepat pada batasnya. */
        $this->assertNotContains('jam_14_hari', $this->jenis($this->deret(str_repeat('K', 14), 11)));

        /* Jendelanya DIGESER PER HARI: rangkaian yang melanggar tepat di
           perbatasan dua potongan dua-mingguan tidak pernah terlihat
           bila dipotong per kalender. */
        $temuan = $this->jenis($this->deret('.......'.str_repeat('K', 14).'.......', 11));
        $this->assertNotContains('jam_14_hari', $temuan);

        /* DAN DARI SISI YANG MELANGGAR. Empat belas hari berjam 12
           menghasilkan 168 jam — di atas batas.
           
           Rangkaian ini melanggar DUA batas sekaligus, dan memang
           tidak dapat dibuat melanggar 154 saja: di bawah batas 11 jam
           dan 14 hari, jumlah tertingginya persis 14 × 11 = 154. Yang
           diuji karena itu bukan "hanya batas ini yang tertangkap"
           melainkan "batas ini IKUT tertangkap" — sebab batas 154
           adalah yang menahan bila salah satu batas lain kelak
           dilonggarkan. */
        $temuan = $this->jenis($this->deret(str_repeat('K', 14), 12));

        $this->assertContains('jam_14_hari', $temuan,
            'Batas 154 jam per 14 hari tidak tertangkap — jendelanya tidak menjumlahkan empat belas hari.');
        $this->assertContains('jam_harian', $temuan);
    }

    #[Test]
    public function test_istirahat_kurang_dari_lima_hari_sesudah_periode_panjang_ditolak(): void
    {
        $temuan = $this->jenis($this->deret(str_repeat('K', 14).'...'.str_repeat('K', 5)));

        $this->assertContains('istirahat', $temuan);
    }

    #[Test]
    public function test_pola_kantor_lima_dua_tidak_ditolak(): void
    {
        /* JEBAKAN YANG SUDAH PERNAH TERJADI. Aturan istirahat lima hari
           melekat pada rezim periode kerja panjang, bukan pada tiap
           jeda. Diterapkan ke semua, akhir pekan dua hari pada pola
           kantor biasa dilaporkan sebagai pelanggaran yang MENOLAK
           penerbitan — dan yang menerimanya adalah orang yang mencoba
           menerbitkan roster staf administrasi. */
        $baris  = $this->deret(str_repeat('KKKKK..', 6), 8);
        $temuan = Fatigue::periksa($baris);

        $this->assertFalse(
            Fatigue::ditolak($temuan),
            'Pola kantor 5:2 ditolak — aturan istirahat lima hari diterapkan ke tiap jeda.',
        );
    }

    #[Test]
    public function test_pola_empat_belas_tujuh_tidak_ditolak(): void
    {
        $baris  = $this->deret(str_repeat(str_repeat('K', 14).str_repeat('.', 7), 3), 11);
        $temuan = Fatigue::periksa($baris);

        $this->assertFalse(Fatigue::ditolak($temuan), 'Pola 14:7 berjam 11 seharusnya sah.');

        /* Tetapi batas 40 jam seminggu memang dilampaui — dan itu
           DICATAT, bukan diblokir. Justru batas itu yang dikecualikan
           Kepmenakertrans 234/2003. */
        $this->assertContains('jam_minggu', $this->jenis($baris));
    }

    #[Test]
    public function test_batas_mingguan_tidak_pernah_menolak(): void
    {
        foreach (Fatigue::periksa($this->deret(str_repeat('K', 7), 11)) as $t) {
            if ($t['jenis'] !== 'jam_minggu') continue;

            $this->assertFalse($t['menolak'], 'Batas 40 jam seminggu memblokir pola tambang yang sah.');
        }
    }

    #[Test]
    public function test_roster_yang_bersih_tidak_menghasilkan_temuan_yang_menolak(): void
    {
        $this->assertFalse(Fatigue::ditolak(Fatigue::periksa($this->deret('KKKKK..KKKKK..', 8))));
    }

    /* ═══════════ siklus ═══════════ */

    #[Test]
    public function test_satuan_minggu_dihitung_sebagai_tujuh_hari(): void
    {
        $pola = PolaRoster::withoutGlobalScopes()->where('kunci', '10-2')->first()
            ?? PolaRoster::withoutGlobalScopes()->where('kode', '10:2')->first();

        $this->assertNotNull($pola);

        /* Ditebak dari besar angkanya, pola 10:2 minggu dihitung sebagai
           sepuluh HARI — dan pekerjanya dipulangkan tujuh puluh hari
           terlalu cepat, dengan tiket yang sudah terbit. */
        $this->assertSame(70, $pola->hariKerja());
        $this->assertSame(14, $pola->hariLibur());
        $this->assertSame(84, $pola->siklus());
    }

    #[Test]
    public function test_pola_mingguan_berlibur_mingguan_tidak_melanggar(): void
    {
        $pola = PolaRoster::withoutGlobalScopes()->where('kode', '10:2')->firstOrFail();

        /* SEPULUH MINGGU BUKAN TUJUH PULUH HARI TANPA JEDA. Dimodelkan
           begitu, pola yang dipakai sungguhan di lapangan ditandai
           melanggar batas empat belas hari pada TIAP siklusnya — dan
           yang pertama dilihat orang saat membuka modulnya adalah empat
           baris merah dari enam pola bawaan. */
        $this->assertSame(70, $pola->hariKerja(), 'Panjang periodenya tetap sepuluh minggu.');
        $this->assertSame(6, $pola->maksBeruntun(), 'Paling lama enam hari berturut-turut.');
        $this->assertLessThanOrEqual(Fatigue::MAKS_HARI_BERUNTUN, $pola->maksBeruntun());

        /* Jamnya dihitung dari hari kerja BERSIH: hari libur mingguan
           tidak menyumbang jam. */
        $this->assertSame(60, $pola->hariKerjaBersih());
        $this->assertSame(60 * $pola->jam, $pola->jamPerSiklus());
    }

    #[Test]
    public function test_libur_mingguan_benar_benar_muncul_di_roster(): void
    {
        $pola = PolaRoster::withoutGlobalScopes()->where('kode', '10:2')->firstOrFail();

        $mulai = Carbon::create(2026, 3, 1);

        $regu = Regu::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'pola_roster_id' => $pola->id,
            'nama' => 'A', 'mulai' => $mulai,
        ]);

        /* Enam hari kerja lalu satu hari libur, berulang — bukan tujuh
           hari kerja. Perhitungan pola yang benar tetapi penyusun yang
           mengabaikannya menghasilkan roster yang melanggar sementara
           halaman polanya menyatakan "Sesuai". */
        $keadaan = [];

        for ($i = 0; $i < 14; $i++) {
            $keadaan[] = $regu->bekerjaPada($mulai->copy()->addDays($i)) ? 'K' : '.';
        }

        $this->assertSame('KKKKKK.KKKKKK.', implode('', $keadaan));
    }

    #[Test]
    public function test_regu_berjangkar_berselisih_saling_mengisi(): void
    {
        $pola = PolaRoster::withoutGlobalScopes()->where('kode', '14:7')->firstOrFail();

        $mulai = Carbon::create(2026, 3, 1);

        $a = Regu::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'pola_roster_id' => $pola->id,
            'nama' => 'A', 'mulai' => $mulai,
        ]);

        $b = Regu::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'pola_roster_id' => $pola->id,
            'nama' => 'B', 'mulai' => $mulai->copy()->addDays(14),
        ]);

        /* Hari ke-15 sampai ke-21: A libur. B baru memulai siklusnya
           pada hari ke-15, jadi ia bekerja. Berjangkar sama, keduanya
           libur bersamaan dan site kosong — kesalahan yang baru
           ketahuan setelah tiketnya terbit. */
        $hari15 = $mulai->copy()->addDays(14);

        $this->assertFalse($a->bekerjaPada($hari15), 'Regu A seharusnya libur pada hari ke-15.');
        $this->assertTrue($b->bekerjaPada($hari15), 'Regu B seharusnya bekerja pada hari ke-15.');
    }

    #[Test]
    public function test_tanggal_sebelum_jangkar_tidak_jatuh_ke_indeks_negatif(): void
    {
        $pola = PolaRoster::withoutGlobalScopes()->where('kode', '14:7')->firstOrFail();

        $regu = Regu::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'pola_roster_id' => $pola->id,
            'nama' => 'A', 'mulai' => Carbon::create(2026, 3, 1),
        ]);

        /* Roster kerap disusun mundur untuk merekonsiliasi absensi bulan
           lalu, dan modulo negatif PHP memulangkan bilangan negatif —
           sehingga seluruh hari sebelum jangkar terbaca "libur". */
        $sebelum = Carbon::create(2026, 2, 25);

        $this->assertGreaterThanOrEqual(0, $regu->hariSiklus($sebelum));
        $this->assertLessThan($pola->siklus(), $regu->hariSiklus($sebelum));
    }

    /* ═══════════ blokir kelayakan ═══════════ */

    private function pekerjaLengkap(Carbon $habisMcu): Pekerja
    {
        $p = Pekerja::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'nama' => 'Operator '.uniqid(), 'status' => 'aktif',
        ]);

        $surat = Mcu::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'tanggal' => Waktu::kini()->startOfDay(), 'kepada' => 'Klinik',
        ]);

        McuOrang::create([
            'mcu_id' => $surat->id, 'pekerja_id' => $p->id, 'nama' => $p->nama,
            'tanggal_periksa' => Waktu::kini()->startOfDay(),
            'berlaku_sampai'  => $habisMcu->copy()->startOfDay(),
            'aktif' => true,
        ]);

        $ind = Induksi::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'tanggal' => Waktu::kini()->startOfDay(),
        ]);

        InduksiOrang::create([
            'induksi_id' => $ind->id, 'pekerja_id' => $p->id,
            'tanggal_induksi' => Waktu::kini()->startOfDay(),
            'nilai' => 90, 'percobaan' => 1, 'status' => 'lulus',
            'berlaku_sampai' => Waktu::kini()->addYear()->startOfDay(),
        ]);

        Permit::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'pekerja_id' => $p->id,
            'tanggal' => Waktu::kini()->startOfDay(), 'status' => 'terbit',
            'berlaku_sampai' => Waktu::kini()->addYear()->startOfDay(),
        ]);

        return $p->fresh();
    }

    #[Test]
    public function test_berkas_yang_habis_menghalangi_penjadwalan(): void
    {
        $p = $this->pekerjaLengkap(Waktu::kini()->addDays(10));
        $p->load(Kelayakan::relasi());

        $this->assertNull(Kelayakan::periksa($p, Waktu::kini())['halangan']);

        /* DINILAI PADA TANGGAL YANG DIRENCANAKAN. MCU yang masih
           berlaku hari ini habis di tengah periode kerja bulan depan;
           dinilai terhadap hari ini saja, penyusunnya melihat seluruh
           baris hijau lalu menerbitkan jadwal yang separuhnya tidak
           boleh dijalankan. */
        $this->assertSame('mcu', Kelayakan::periksa($p, Waktu::kini()->addDays(20))['halangan']);
    }

    #[Test]
    public function test_pekerja_nonaktif_tidak_dapat_dijadwalkan(): void
    {
        $p = $this->pekerjaLengkap(Waktu::kini()->addYear());
        $p->update(['status' => 'resign']);
        $p->load(Kelayakan::relasi());

        $hasil = Kelayakan::periksa($p->fresh(), Waktu::kini());

        $this->assertSame('nonaktif', $hasil['halangan']);
        $this->assertSame('Pekerja sudah tidak aktif', $hasil['label']);
    }

    #[Test]
    public function test_simper_tidak_menghalangi(): void
    {
        /* Tidak semua pekerja mengemudikan unit. Admin logistik tanpa
           SIMPER tetap boleh masuk selama Mine Permit-nya berlaku;
           memblokirnya berarti menolak setiap orang yang bukan
           operator. */
        $p = $this->pekerjaLengkap(Waktu::kini()->addYear());
        $p->load(Kelayakan::relasi());

        $hasil = Kelayakan::periksa($p, Waktu::kini());

        $this->assertNull($hasil['halangan']);
        $this->assertSame('belum', $hasil['keadaan']['simper']);
    }

    #[Test]
    public function test_penyusunan_menandai_hari_yang_terhalang(): void
    {
        $pola = PolaRoster::withoutGlobalScopes()->where('kode', '14:7')->firstOrFail();

        $regu = Regu::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'pola_roster_id' => $pola->id,
            'nama' => 'A', 'mulai' => Waktu::kini()->startOfDay(),
        ]);

        $p = $this->pekerjaLengkap(Waktu::kini()->addDays(5));

        ReguAnggota::create([
            'regu_id' => $regu->id, 'pekerja_id' => $p->id,
            'mulai' => Waktu::kini()->startOfDay(),
        ]);

        Penyusun::susun($regu, Waktu::kini(), Waktu::kini()->addDays(13));

        $terhalang = Roster::withoutGlobalScopes()->where('pekerja_id', $p->id)
            ->whereNotNull('halangan')->count();

        $this->assertGreaterThan(0, $terhalang,
            'MCU habis di tengah periode kerja tetapi tidak satu hari pun ditandai terhalang.');

        /* Hari-hari awal, ketika MCU masih berlaku, TIDAK tertandai —
           menandai seluruh periode karena satu hari bermasalah akan
           membuang jadwal yang sebenarnya sah. */
        $bersih = Roster::withoutGlobalScopes()->where('pekerja_id', $p->id)
            ->whereNull('halangan')->bekerja()->count();

        $this->assertGreaterThan(0, $bersih);
    }

    /* ═══════════ penerbitan ═══════════ */

    #[Test]
    public function test_penerbitan_ditolak_bila_ada_pelanggaran(): void
    {
        $pola = PolaRoster::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'kode' => '21:2', 'nama' => 'Melanggar',
            'kerja' => 21, 'libur' => 2, 'satuan' => 'hari', 'jam' => 11, 'shift' => 'siang',
        ]);

        $regu = Regu::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'pola_roster_id' => $pola->id,
            'nama' => 'A', 'mulai' => Waktu::kini()->startOfDay(),
        ]);

        $p = $this->pekerjaLengkap(Waktu::kini()->addYears(2));

        ReguAnggota::create([
            'regu_id' => $regu->id, 'pekerja_id' => $p->id, 'mulai' => Waktu::kini()->startOfDay(),
        ]);

        Penyusun::susun($regu, Waktu::kini(), Waktu::kini()->addDays(22));

        $hasil = Penyusun::terbitkan($regu, Waktu::kini(), Waktu::kini()->addDays(22));

        $this->assertSame(0, $hasil['terbit']);
        $this->assertNotEmpty($hasil['ditolak']);
        $this->assertSame(0, Roster::withoutGlobalScopes()->where('terbit', true)->count());
    }

    #[Test]
    public function test_penerbitan_diterima_bila_bersih(): void
    {
        $pola = PolaRoster::withoutGlobalScopes()->where('kode', '14:7')->firstOrFail();

        $regu = Regu::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'pola_roster_id' => $pola->id,
            'nama' => 'A', 'mulai' => Waktu::kini()->startOfDay(),
        ]);

        $p = $this->pekerjaLengkap(Waktu::kini()->addYears(2));

        ReguAnggota::create([
            'regu_id' => $regu->id, 'pekerja_id' => $p->id, 'mulai' => Waktu::kini()->startOfDay(),
        ]);

        Penyusun::susun($regu, Waktu::kini(), Waktu::kini()->addDays(20));

        $hasil = Penyusun::terbitkan($regu, Waktu::kini(), Waktu::kini()->addDays(20));

        $this->assertSame([], $hasil['ditolak']);
        $this->assertGreaterThan(0, $hasil['terbit']);
    }

    #[Test]
    public function test_penyusunan_ulang_tidak_menimpa_yang_sudah_terbit(): void
    {
        $pola = PolaRoster::withoutGlobalScopes()->where('kode', '14:7')->firstOrFail();

        $regu = Regu::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'pola_roster_id' => $pola->id,
            'nama' => 'A', 'mulai' => Waktu::kini()->startOfDay(),
        ]);

        $p = $this->pekerjaLengkap(Waktu::kini()->addYears(2));

        ReguAnggota::create([
            'regu_id' => $regu->id, 'pekerja_id' => $p->id, 'mulai' => Waktu::kini()->startOfDay(),
        ]);

        Penyusun::susun($regu, Waktu::kini(), Waktu::kini()->addDays(10));

        /* Satu hari disunting menjadi cuti, lalu diterbitkan. */
        $satu = Roster::withoutGlobalScopes()->where('pekerja_id', $p->id)->orderBy('tanggal')->first();
        $satu->update(['keadaan' => 'cuti', 'jam' => 0, 'terbit' => true]);

        Penyusun::susun($regu, Waktu::kini(), Waktu::kini()->addDays(10));

        /* Tanpa penjagaan ini, menekan "susun ulang" karena satu orang
           pindah regu akan menghapus seluruh cuti yang sudah disetujui
           sebulan itu — tanpa satu galat pun, sebab barisnya memang
           tergantikan dengan benar. */
        $this->assertSame('cuti', $satu->fresh()->keadaan);
    }

    #[Test]
    public function test_hari_terakhir_rentang_ikut_terjaring(): void
    {
        $pola = PolaRoster::withoutGlobalScopes()->where('kode', '14:7')->firstOrFail();

        $regu = Regu::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'pola_roster_id' => $pola->id,
            'nama' => 'A', 'mulai' => Waktu::kini()->startOfDay(),
        ]);

        $p = $this->pekerjaLengkap(Waktu::kini()->addYears(2));

        ReguAnggota::create([
            'regu_id' => $regu->id, 'pekerja_id' => $p->id, 'mulai' => Waktu::kini()->startOfDay(),
        ]);

        $sampai = Waktu::kini()->addDays(5);

        Penyusun::susun($regu, Waktu::kini(), $sampai);

        /* TEPI ATASNYA yang paling mudah salah. Kolom DATE menyimpan
           "2026-09-22 00:00:00", dan sebagai perbandingan teks
           "2026-09-22 00:00:00" <= "2026-09-22" bernilai SALAH — yang
           kiri lebih panjang, karena itu lebih besar. Hari terakhir
           tidak pernah terjaring, lalu penyusunan ulang menyisipkannya
           lagi dan batasan unik melemparkan galat 500 pada tombol yang
           baru saja berhasil ditekan sekali. */
        $this->assertDatabaseHas('hr_roster', [
            'pekerja_id' => $p->id,
            'tanggal'    => $sampai->copy()->startOfDay()->toDateTimeString(),
        ]);

        $terjaring = Roster::withoutGlobalScopes()
            ->antara(Waktu::kini()->toDateString(), $sampai->toDateString())
            ->count();

        $this->assertSame(6, $terjaring, 'Rentang enam hari tidak terjaring seluruhnya.');
    }

    #[Test]
    public function test_penyusunan_ulang_tidak_menggandakan(): void
    {
        $pola = PolaRoster::withoutGlobalScopes()->where('kode', '14:7')->firstOrFail();

        $regu = Regu::withoutGlobalScopes()->create([
            'company_id' => $this->c->id, 'pola_roster_id' => $pola->id,
            'nama' => 'A', 'mulai' => Waktu::kini()->startOfDay(),
        ]);

        $p = $this->pekerjaLengkap(Waktu::kini()->addYears(2));

        ReguAnggota::create([
            'regu_id' => $regu->id, 'pekerja_id' => $p->id, 'mulai' => Waktu::kini()->startOfDay(),
        ]);

        Penyusun::susun($regu, Waktu::kini(), Waktu::kini()->addDays(20));
        $pertama = Roster::withoutGlobalScopes()->count();

        Penyusun::susun($regu, Waktu::kini(), Waktu::kini()->addDays(20));

        $this->assertSame($pertama, Roster::withoutGlobalScopes()->count());
    }

    /* ═══════════ halaman ═══════════ */

    #[Test]
    public function test_halaman_roster_terbuka(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        foreach (['/roster', '/roster/pola', '/roster/kebutuhan'] as $jalur) {
            $this->get($jalur)->assertOk();
        }
    }

    #[Test]
    public function test_bukan_admin_tidak_dapat_menghapus_pola(): void
    {
        $this->actingAs(User::factory()->create(['company_id' => $this->c->id]));

        $pola = PolaRoster::withoutGlobalScopes()->where('kode', '14:7')->firstOrFail();

        $this->delete('/roster/pola/'.$pola->id)->assertForbidden();
        $this->assertDatabaseHas('hr_pola_roster', ['id' => $pola->id]);
    }
}
