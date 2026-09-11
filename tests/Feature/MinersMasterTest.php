<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Miners\{Blok, Departemen, HasilMcu, Jabatan, JenisUnit, Kendaraan, Master, Permit, TipePermit};
use App\Models\Miners\{InduksiOrang, McuOrang, Simper, SimperAjuan};
use App\Support\Miners\Acuan;
use App\Support\Miners\MasterMiners;
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Daftar awal bersama Miners, dan acuan SOP yang menjadi sumbernya.
 *
 * `miners:pasang` dijalankan pada TIAP penerapan, terhadap basis data
 * yang sudah memuat ribuan dokumen terbit yang menunjuk ke daftar ini.
 * Yang diuji karena itu bukan "apakah pemasangannya berhasil" —
 * sekali jalan hampir selalu berhasil — melainkan apa yang terjadi
 * pada pemasangan KEDUA, KETIGA, dan pada pemasangan yang menemukan
 * daftar yang sudah disunting perusahaan yang memakainya.
 */
class MinersMasterTest extends TestCase
{
    use RefreshDatabase;

    /* ═══════════════ pemasangan ═══════════════ */

    #[Test]
    public function test_memasang_seluruh_daftar_sesuai_acuan(): void
    {
        $hasil = MasterMiners::pasang();

        $this->assertSame(count(Acuan::DEPARTEMEN), $hasil['mnr_departemen']);
        $this->assertSame(count(Acuan::JABATAN), $hasil['mnr_jabatan']);
        $this->assertSame(count(Acuan::LOKASI_KERJA), $hasil['mnr_blok']);
        $this->assertSame(count(Acuan::GOLONGAN_UNIT), $hasil['mnr_kendaraan']);
        $this->assertSame(count(Acuan::JENIS_PERMIT), $hasil['mnr_tipe_permit']);
        $this->assertSame(count(Acuan::HASIL_MCU), $hasil['mnr_hasil_mcu']);

        $kategori = array_sum(array_map(
            fn (array $t) => count($t['kategori']),
            Acuan::JENIS_PERMIT,
        ));
        $this->assertSame($kategori, $hasil['mnr_kategori_permit']);
    }

    #[Test]
    public function test_seluruhnya_tanpa_pemilik_supaya_terlihat_tiap_perusahaan(): void
    {
        MasterMiners::pasang();

        foreach (['mnr_departemen', 'mnr_jabatan', 'mnr_blok', 'mnr_kendaraan',
                  'mnr_jenis_unit', 'mnr_tipe_permit', 'mnr_hasil_mcu'] as $t) {
            $this->assertSame(
                0,
                (int) DB::table($t)->whereNotNull('company_id')->count(),
                "{$t} memuat baris bermilik; daftar awal harus tanpa pemilik supaya terlihat tiap perusahaan.",
            );
        }
    }

    #[Test]
    public function test_dijalankan_berulang_tidak_menggandakan(): void
    {
        $pertama = MasterMiners::pasang();
        $kedua   = MasterMiners::pasang();
        $ketiga  = MasterMiners::pasang();

        $this->assertSame($pertama, $kedua);
        $this->assertSame($pertama, $ketiga);
    }

    #[Test]
    public function test_tidak_pernah_menghapus_baris_milik_perusahaan(): void
    {
        MasterMiners::pasang();

        $c = Company::create(['name' => 'PT Uji Miners']);

        $milik = Departemen::withoutGlobalScopes()->create([
            'company_id' => $c->id,
            'nama'       => 'Peledakan',
        ]);

        MasterMiners::pasang();

        $this->assertDatabaseHas('mnr_departemen', [
            'id'      => $milik->id,
            'nama'    => 'Peledakan',
            'company_id' => $c->id,
        ]);
    }

    #[Test]
    public function test_nama_yang_sudah_disunting_tidak_ditimpa(): void
    {
        MasterMiners::pasang();

        $id = DB::table('mnr_departemen')->whereRaw('LOWER(nama) = ?', ['hse'])->value('id');
        DB::table('mnr_departemen')->where('id', $id)->update(['nama' => 'HSE & Lingkungan']);

        MasterMiners::pasang();

        $this->assertSame(
            'HSE & Lingkungan',
            DB::table('mnr_departemen')->where('id', $id)->value('nama'),
            'Nama yang sudah disunting perusahaan ditimpa kembali oleh pemasangan ulang.',
        );

        /* Dan tidak lahir baris "HSE" yang kedua: pencocokannya
           memang lewat nama, jadi nama yang berubah dapat melahirkan
           kembar — itu yang diuji di sini, bukan sekadar tidak
           ditimpa. */
        $this->assertSame(count(Acuan::DEPARTEMEN), (int) DB::table('mnr_departemen')->count());
    }

    #[Test]
    public function test_kolom_acuan_ikut_diperbarui_pada_pemasangan_ulang(): void
    {
        MasterMiners::pasang();

        $id = DB::table('mnr_kendaraan')->whereRaw('LOWER(nama) = ?', ['crane truck'])->value('id');

        /* Revisi SOP yang tertinggal ditiru: kelas SIM dan kewajiban
           SIO dibuat salah, lalu pemasangan diulang. */
        DB::table('mnr_kendaraan')->where('id', $id)
            ->update(['kelas_simpol' => 'A', 'wajib_sio' => false]);

        MasterMiners::pasang();

        $baris = DB::table('mnr_kendaraan')->where('id', $id)->first();

        $this->assertSame('B2 Umum', $baris->kelas_simpol);
        $this->assertSame(1, (int) $baris->wajib_sio);
    }

    #[Test]
    public function test_baris_lama_senama_diangkat_bukan_digandakan(): void
    {
        /* Basis data yang departemennya sudah diketik tangan sebelum
           perintah ini ada. Tanpa jembatan, pemasangan pertama
           menggandakan seluruh daftarnya. */
        DB::table('mnr_departemen')->insert([
            'company_id' => null, 'kunci' => null, 'nama' => 'Produksi', 'aktif' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        MasterMiners::pasang();

        $baris = DB::table('mnr_departemen')->whereRaw('LOWER(nama) = ?', ['produksi'])->get();

        $this->assertCount(1, $baris, 'Baris lama senama digandakan alih-alih diangkat.');
        $this->assertSame('produksi', $baris[0]->kunci, 'Kuncinya tidak distempelkan, jadi jembatannya terpakai berulang kali.');
        $this->assertSame('PRD', $baris[0]->kode);
    }

    #[Test]
    public function test_baris_milik_perusahaan_tidak_pernah_diangkat(): void
    {
        $c = Company::create(['name' => 'PT Uji Miners']);

        $milik = Departemen::withoutGlobalScopes()->create([
            'company_id' => $c->id,
            'nama'       => 'Produksi',
        ]);

        MasterMiners::pasang();

        $milik->refresh();

        /* Baris perusahaan yang kebetulan senama BUKAN baris acuan.
           Diangkat, ia akan ikut diperbarui tiap deploy — dan kolom
           SOP menimpa apa pun yang diisi pemiliknya. */
        $this->assertNull($milik->kunci);
        $this->assertSame($c->id, $milik->company_id);

        /* Dan barisnya sendiri tetap lahir, tanpa pemilik. */
        $this->assertDatabaseHas('mnr_departemen', [
            'kunci' => 'produksi', 'company_id' => null,
        ]);
    }

    #[Test]
    public function test_pencocokan_tidak_peduli_huruf_besar_kecil(): void
    {
        DB::table('mnr_departemen')->insert([
            'company_id' => null, 'nama' => 'produksi', 'aktif' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        MasterMiners::pasang();

        $this->assertSame(
            1,
            (int) DB::table('mnr_departemen')->whereRaw('LOWER(nama) = ?', ['produksi'])->count(),
            '"produksi" dan "Produksi" melahirkan dua baris kembar pada daftar pilih.',
        );
    }

    #[Test]
    public function test_golongan_tanpa_rincian_tetap_masuk_daftar_unit(): void
    {
        MasterMiners::pasang();

        /* Light Vehicle bukan payung bagi unit lain — ia unitnya
           sendiri, dan justru yang paling sering dipilih. Dilewatkan,
           daftar unit SIMPER kehilangan golongan terbanyak. */
        $this->assertDatabaseHas('mnr_jenis_unit', ['nama' => 'Light Vehicle']);

        /* Sebaliknya, yang PUNYA rincian masuk lewat rinciannya, bukan
           lewat nama payungnya: tidak ada operator ber-SIMPER
           "Alat Berat", yang ada ber-SIMPER Excavator. */
        $this->assertDatabaseMissing('mnr_jenis_unit', ['nama' => 'Alat Berat']);
        $this->assertDatabaseHas('mnr_jenis_unit', ['nama' => 'Excavator']);
    }

    /* ═══════════════ daftar pilih ═══════════════ */

    /**
     * Tiap daftar master harus benar-benar dapat dibaca sebagai pilihan.
     *
     * Ini menjaga jebakan yang sudah pernah terjadi: `Master::terpakai()`
     * menyaring kolom `aktif`, dan dua tabel dibuat tanpa kolom itu.
     * MySQL menolaknya sebagai "Unknown column" — galat 500 di hadapan
     * pengguna — sedangkan SQLite memperlakukan `"aktif"` sebagai
     * untaian teks biasa, sehingga kuerinya BERHASIL dan menjawab nol
     * baris. Yang kedua itu yang berbahaya: daftar pilihnya kosong,
     * ujinya hijau, dan tidak ada satu pun galat yang menunjuk ke
     * sebabnya.
     *
     * Karena itu yang dipindai adalah SELURUH turunan Master di dalam
     * direktori, bukan daftar kelas yang ditulis tangan di sini —
     * daftar tulisan tangan akan tertinggal pada turunan berikutnya,
     * dan turunan berikutnya itulah yang akan mengulangi jebakannya.
     */
    #[Test]
    public function test_tiap_daftar_master_dapat_dibaca_sebagai_pilihan(): void
    {
        MasterMiners::pasang();

        $turunan = $this->turunanMaster();

        $this->assertGreaterThanOrEqual(9, count($turunan), 'Pemindaian turunan Master tidak menemukan apa pun.');

        foreach ($turunan as $kelas) {
            try {
                $kelas::pilihan();
            } catch (\Throwable $e) {
                $this->fail(class_basename($kelas).'::pilihan() gagal: '.$e->getMessage());
            }
        }
    }

    #[Test]
    public function test_daftar_yang_dipasang_benar_benar_terbaca_isinya(): void
    {
        MasterMiners::pasang();

        foreach ([
            Departemen::class => count(Acuan::DEPARTEMEN),
            Jabatan::class    => count(Acuan::JABATAN),
            Blok::class       => count(Acuan::LOKASI_KERJA),
            Kendaraan::class  => count(Acuan::GOLONGAN_UNIT),
            TipePermit::class => count(Acuan::JENIS_PERMIT),
            HasilMcu::class   => count(Acuan::HASIL_MCU),
        ] as $kelas => $jumlah) {
            $this->assertCount(
                $jumlah,
                $kelas::pilihan(),
                class_basename($kelas).'::pilihan() menjawab daftar yang tidak lengkap.',
            );
        }

        $this->assertNotEmpty(JenisUnit::pilihan());
    }

    #[Test]
    public function test_yang_dinonaktifkan_hilang_dari_daftar_pilih(): void
    {
        MasterMiners::pasang();

        $sebelum = count(Blok::pilihan());

        DB::table('mnr_blok')->whereRaw('LOWER(nama) = ?', ['nursery'])->update(['aktif' => false]);

        $this->assertCount($sebelum - 1, Blok::pilihan());
    }

    /* ═══════════════ acuan ═══════════════ */

    #[Test]
    public function test_matriks_simpol_hanya_memakai_kelas_yang_dikenali(): void
    {
        foreach (Acuan::GOLONGAN_UNIT as $g) {
            if ($g['simpol'] === null) continue;

            $this->assertContains(
                $g['simpol'],
                Acuan::KELAS_SIMPOL,
                "Golongan {$g['nama']} menuntut kelas SIM '{$g['simpol']}' yang tidak ada pada daftar kelas.",
            );
        }
    }

    #[Test]
    public function test_tiap_golongan_berwarna_kartu_yang_dikenali(): void
    {
        foreach (Acuan::GOLONGAN_UNIT as $g) {
            $this->assertArrayHasKey(
                $g['warna'],
                Acuan::WARNA_KARTU,
                "Golongan {$g['nama']} berwarna '{$g['warna']}' yang tidak ada pada daftar warna kartu.",
            );
        }
    }

    #[Test]
    public function test_kewajiban_sio_dan_kelas_sim_terbaca_dari_nama_golongan(): void
    {
        $this->assertSame('B2 Umum', Acuan::simpol('Crane Truck'));
        $this->assertSame('A', Acuan::simpol('Light Vehicle'));
        $this->assertNull(Acuan::simpol('Forklift'));
        $this->assertNull(Acuan::simpol('Kapal Tunda'));

        $this->assertTrue(Acuan::wajibSio('Crane Truck'));
        $this->assertTrue(Acuan::wajibSio('crane truck'), 'Pencocokan golongan peduli huruf besar-kecil.');
        $this->assertFalse(Acuan::wajibSio('Dump Truck'));
        $this->assertFalse(Acuan::wajibSio('Kapal Tunda'));
    }

    #[Test]
    public function test_batas_usia_menebak_ke_arah_yang_lebih_ketat(): void
    {
        $this->assertSame(['min' => 18, 'maks' => 50], Acuan::batasUsia('Light Vehicle'));
        $this->assertSame(['min' => 18, 'maks' => 50], Acuan::batasUsia('Bus'));

        $this->assertSame(['min' => 21, 'maks' => 50], Acuan::batasUsia('Dump Truck'));
        $this->assertSame(['min' => 21, 'maks' => 50], Acuan::batasUsia('Alat Berat'));

        /* Golongan yang belum berkelas SIM, dan golongan yang tidak
           dikenali sama sekali, ikut batas alat berat. Menebak ke arah
           yang lebih longgar akan meloloskan operator berusia 18 tahun
           ke atas dump truck. */
        $this->assertSame(['min' => 21, 'maks' => 50], Acuan::batasUsia('Forklift'));
        $this->assertSame(['min' => 21, 'maks' => 50], Acuan::batasUsia('Kapal Tunda'));
        $this->assertSame(['min' => 21, 'maks' => 50], Acuan::batasUsia(null));
    }

    #[Test]
    public function test_warna_kartu_mengikuti_kewenangan_tertinggi(): void
    {
        $this->assertSame('putih', Acuan::warnaTertinggi([]), 'Tanpa unit, kartu harus putih — "tidak mengendarai unit".');
        $this->assertSame('hijau', Acuan::warnaTertinggi(['Light Vehicle']));
        $this->assertSame('biru', Acuan::warnaTertinggi(['Water Truck', 'Light Vehicle']));
        $this->assertSame('merah', Acuan::warnaTertinggi(['Light Vehicle', 'Water Truck', 'Dump Truck']));

        /* Nama yang tidak dikenali DILEWATI, tidak dijadikan merah.
           `array_search` menjawab false bila tak ketemu dan (int) false
           adalah 0 — indeks kewenangan tertinggi. */
        $this->assertSame('putih', Acuan::warnaTertinggi(['Kapal Tunda']));
        $this->assertSame('hijau', Acuan::warnaTertinggi(['Kapal Tunda', 'Light Vehicle']));
    }

    #[Test]
    public function test_tiap_jenis_pengajuan_punya_daftar_berkas_wajib(): void
    {
        foreach (['permit_baru', 'permit_perpanjangan', 'permit_visitor',
                  'simper_baru', 'simper_perpanjangan', 'simper_penambahan'] as $jenis) {
            $this->assertNotEmpty(
                Acuan::berkasWajib($jenis),
                "Jenis pengajuan {$jenis} tidak punya daftar lampiran wajib.",
            );
        }

        $this->assertSame([], Acuan::berkasWajib('tidak_ada'));
    }

    #[Test]
    public function test_alur_memuat_pengesahan_ktt(): void
    {
        $peran = array_column(Acuan::ALUR, 'peran');

        /* Perbedaan paling penting dari Project1: di sana Mine Permit
           terbit sesudah OHSE saja, tanpa pengesahan Kepala Teknik
           Tambang — orang yang justru bertanggung jawab atas kartu itu
           di hadapan Inspektur Tambang. */
        $this->assertContains('ktt', $peran);
        $this->assertContains('dokter', $peran);
        $this->assertContains('ohse', $peran);
        $this->assertContains('pjo', $peran);
    }

    /* ═══════════════ tidak ada angka kembar ═══════════════ */

    /**
     * Angka SOP di model harus benar-benar berasal dari Acuan.
     *
     * Diuji sebagai nilai, bukan sekadar dibaca dari kodenya: yang
     * berbahaya bukan konstanta yang diketik ulang melainkan konstanta
     * yang diketik ulang BERBEDA, dan perbedaan itu tidak menimbulkan
     * galat — hanya dua layar yang menghitung masa berlaku tidak sama
     * untuk orang yang sama.
     */
    #[Test]
    public function test_angka_sop_pada_model_sama_dengan_acuan(): void
    {
        $this->assertSame(Acuan::MASA['mcu_bulan'], McuOrang::BULAN_BERLAKU);
        $this->assertSame(Acuan::MASA['peringatan_mcu_hari'], McuOrang::HARI_PERINGATAN);
        $this->assertSame(Acuan::MASA['induksi_bulan'], InduksiOrang::BULAN_BERLAKU);
        $this->assertSame(Acuan::MASA['buka_perpanjangan_hari'], SimperAjuan::HARI_BOLEH_PERPANJANG);
        $this->assertSame(Acuan::POST_TEST['nilai_lulus'], InduksiOrang::NILAI_LULUS);
        $this->assertSame(Acuan::POST_TEST['maks_ujian'], InduksiOrang::MAKS_PERCOBAAN);
        $this->assertSame(Acuan::KELAS_SIMPOL, Simper::JENIS_SIMPOL);
        $this->assertSame(array_keys(Acuan::KELAS_SIMPER), array_keys(Simper::KELAS));
        $this->assertSame(array_keys(Acuan::ZONA_AKSES), array_keys(Permit::CAKUPAN));
        $this->assertSame(array_keys(Acuan::WARNA_KARTU), array_keys(Permit::WARNA));
    }

    #[Test]
    public function test_remidi_dua_kali_berarti_tiga_kali_ujian(): void
    {
        $this->assertSame(
            Acuan::POST_TEST['maks_remidi'] + 1,
            Acuan::POST_TEST['maks_ujian'],
            'Jumlah ujian harus satu lebih banyak daripada jumlah remidi — percobaan pertama bukan remidi.',
        );
    }

    /* ═══════════════ masa berlaku permit ═══════════════ */

    #[Test]
    public function test_masa_berlaku_permit_mengikuti_tipenya(): void
    {
        MasterMiners::pasang();

        $terbit = Waktu::kini()->setDate(2026, 3, 15);

        $tahunan = TipePermit::withoutGlobalScopes()->whereRaw('LOWER(nama) = ?', ['full permit'])->first();
        [$habis, $asal] = Permit::hitungBerlaku($tahunan, $terbit);
        $this->assertSame('2026-12-31', $habis->toDateString(), 'Full Permit harus habis 31 Desember tahun terbit, bukan mengikuti MCU.');
        $this->assertSame('tahunan', $asal);

        $visitor = TipePermit::withoutGlobalScopes()->whereRaw('LOWER(nama) = ?', ['visitor permit'])->first();
        [$habis, $asal] = Permit::hitungBerlaku($visitor, $terbit);
        $this->assertSame('2026-03-22', $habis->toDateString());
        $this->assertSame('tipe', $asal);

        $sementara = TipePermit::withoutGlobalScopes()->whereRaw('LOWER(nama) = ?', ['temporary permit'])->first();
        [$habis] = Permit::hitungBerlaku($sementara, $terbit);
        $this->assertSame('2026-04-14', $habis->toDateString());
    }

    #[Test]
    public function test_tipe_berhari_nol_tidak_jatuh_ke_aturan_tahunan(): void
    {
        MasterMiners::pasang();

        $sehari = TipePermit::withoutGlobalScopes()->create([
            'nama' => 'Izin Sehari', 'hari_berlaku' => 0,
        ]);

        [$habis, $asal] = Permit::hitungBerlaku($sehari, Waktu::kini()->setDate(2026, 3, 15));

        $this->assertSame('2026-03-15', $habis->toDateString(), 'Tipe berhari nol diam-diam berlaku sampai 31 Desember.');
        $this->assertSame('tipe', $asal);
    }

    #[Test]
    public function test_tanpa_tipe_mengikuti_aturan_tahunan(): void
    {
        [$habis, $asal] = Permit::hitungBerlaku(null, Waktu::kini()->setDate(2026, 7, 1));

        $this->assertSame('2026-12-31', $habis->toDateString());
        $this->assertSame('tahunan', $asal);
    }

    /* ═══════════════ perkakas ═══════════════ */

    /** @return list<class-string<Master>> */
    private function turunanMaster(): array
    {
        $kelas = [];

        $berkas = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path('Models/Miners'), RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($berkas as $b) {
            if ($b->getExtension() !== 'php') continue;

            $nama = 'App\\Models\\Miners\\'.str_replace(
                ['/', '.php'],
                ['\\', ''],
                ltrim(str_replace(app_path('Models/Miners'), '', $b->getPathname()), '/'),
            );

            if (! class_exists($nama)) continue;

            $r = new \ReflectionClass($nama);

            if ($r->isAbstract() || ! $r->isSubclassOf(Master::class)) continue;

            $kelas[] = $nama;
        }

        return $kelas;
    }
}
