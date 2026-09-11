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

        /* Master 51 jenis kompetensi berasal dari SK Dirjen
           185.K/37.04/DJB/2019 — regulasi nasional, sama bagi setiap
           perusahaan. Ia ditanam sekali dan SENGAJA tidak ikut terbuang
           bersama data contoh: membuangnya akan menghapus daftar pilih
           yang dipakai seluruh perusahaan sungguhan pada pemasangan
           yang sama. */
        'kompetensi_jenis' => 'master nasional, bukan data contoh',

        /* Daftar acuan jenis unit SPIP. Berbeda dari kompetensi_jenis
           ia BUKAN daftar regulasi melainkan titik berangkat yang
           dipelihara pemakainya — tetapi alasan tidak membuangnya sama
           persis: baris milik bersama (company_id NULL) dipakai seluruh
           perusahaan pada pemasangan yang sama, dan membuangnya bersama
           data contoh satu perusahaan akan mengosongkan daftar pilih
           perusahaan lain. Jenis yang ditambahkan sebuah perusahaan
           sendiri memang bermilik, dan yang itu ikut terbuang. */
        'ko_unit_master' => 'acuan bersama, dipelihara pemakainya',

        /* Master modul Investigasi. Alasannya sama persis dengan
           kompetensi_jenis: isinya kerangka REGULASI — matriks risiko,
           klasifikasi cedera menurut Kepmen ESDM 1827/2018, klasifikasi
           kecelakaan menurut Kepdirjen 185/2019, hierarki pengendalian,
           dan kamus penyebab SCAT — yang berlaku sama bagi setiap
           perusahaan. Ia dipasang `investigasi:pasang`, bukan oleh data
           contoh, dan membuangnya bersama data contoh satu perusahaan
           akan melumpuhkan triase seluruh perusahaan lain pada
           pemasangan yang sama.

           Bahwa keenamnya benar-benar terisi tetap dijaga — bukan di
           sini melainkan di InvestigasiTriaseTest, yang memeriksa
           matriksnya 25 sel dan kamusnya 252 butir. */
        'inv_matriks_risiko'       => 'matriks regulasi, dipasang investigasi:pasang',
        'inv_klasifikasi_cedera'   => 'Kepmen ESDM 1827/2018, master nasional',
        'inv_klasifikasi_regulasi' => 'Kepdirjen Minerba 185/2019, master nasional',
        'inv_hierarki_kendali'     => 'hierarki pengendalian, master nasional',
        'inv_jenis_insiden'        => 'daftar acuan bersama',
        'inv_taksonomi'            => 'kamus penyebab SCAT/ICAM, master bersama',

        /* Katalog jual: daftar harga penjual, bukan data pelanggan. Ia
           dipasang perintah `pembelian:katalog` pada tiap deploy dan
           TIDAK dibuang bersama data contoh — membuangnya akan menghapus
           harga yang sudah ditetapkan orang. */
        /* Daftar awal bersama modul Miners. Alasannya sama persis
           dengan master Investigasi dan PJP di atas: isinya acuan SOP
           001-SPM-007 dan 007-SOP-OHSE — matriks SIMPOL per golongan
           unit, kewajiban SIO, masa berlaku tiap jenis permit, lokasi
           kerja berdasarkan risiko — yang berlaku sama bagi setiap
           tambang. Dipasang `miners:pasang`, bukan oleh data contoh.

           Membuangnya bersama data contoh satu perusahaan akan
           mengosongkan daftar pilih perusahaan lain pada pemasangan
           yang sama, dan akibatnya bukan sekadar daftar kosong:
           `mnr_pekerja`, `mnr_permit`, dan `mnr_simper_unit` menunjuk
           ke sini, sehingga kartu yang sudah terbit kehilangan nama
           golongan unitnya tanpa satu galat pun.

           Yang BUKAN acuan bersama tidak ikut ke sini: daftar
           subkontraktor, PJO, dan sub-blok disusun masing-masing
           perusahaan, jadi keduanya tetap wajib terisi data contoh. */
        'mnr_departemen'      => 'acuan bersama, dipasang miners:pasang',
        'mnr_jabatan'         => 'acuan bersama, dipasang miners:pasang',
        'mnr_blok'            => 'lokasi kerja 001-SPM-007, dipasang miners:pasang',
        'mnr_kendaraan'       => 'matriks SIMPOL 007-SOP-OHSE, dipasang miners:pasang',
        'mnr_sub_kendaraan'   => 'rincian golongan unit, dipasang miners:pasang',
        'mnr_jenis_unit'      => 'daftar unit acuan, dipasang miners:pasang',
        'mnr_tipe_permit'     => 'jenis permit 007-SOP-OHSE, dipasang miners:pasang',
        'mnr_kategori_permit' => 'kategori izin khusus, dipasang miners:pasang',
        'mnr_hasil_mcu'       => 'daftar hasil MCU acuan, dipasang miners:pasang',

        /* Pola roster awal bersama — 14:7, 10:2 minggu, dan seterusnya.
           Alasannya sama persis dengan master Miners di atas: ia daftar
           acuan tanpa pemilik, dipasang `roster:pasang`, dan
           `hr_regu.pola_roster_id` menunjuk ke sini. Membuangnya
           bersama data contoh satu perusahaan akan memutus seluruh regu
           perusahaan lain dari polanya — dan kalender rosternya kosong
           tanpa satu galat pun. */
        'hr_pola_roster' => 'pola acuan, dipasang roster:pasang',

        'beli_produk'              => 'katalog jual, master milik penjual',
        'inv_wawancara_pertanyaan' => 'bank soal wawancara, master bersama',

        /* Daftar periksa prakualifikasi SMKP modul PJP. Alasannya sama
           persis dengan master Investigasi di atas: isinya lampiran
           Kepdirjen Minerba 185/2019 — 17 kategori dan 126 butir — yang
           berlaku sama bagi setiap pemegang IUP. Dipasang `pjp:pasang`,
           bukan oleh data contoh.

           Membuangnya bersama data contoh satu perusahaan tidak sekadar
           mengosongkan daftar periksa perusahaan lain: jawaban mereka
           menunjuk butirnya lewat kunci asing yang cascade on delete,
           jadi pekerjaan berbulan-bulan ikut terhapus tanpa satu galat
           pun.

           Bahwa keduanya benar-benar terisi tetap dijaga — bukan di sini
           melainkan di PjpAksiTest, yang memeriksa 17 kategori, 126
           butir, dan bobot A–P berjumlah 178. */
        'pjp_smkp_kategori' => 'daftar periksa Kepdirjen 185/2019, dipasang pjp:pasang',
        'pjp_smkp_item'     => 'daftar periksa Kepdirjen 185/2019, dipasang pjp:pasang',

        /* Penghitung nomor. SENGAJA tidak ikut dibuang: mengosongkannya
           membuat deret nomor mulai dari satu lagi, sehingga INC-2026-0001
           terbit dua kali dalam tahun yang sama. Dua dokumen bernomor
           sama adalah persoalan yang baru ketahuan saat salah satunya
           dicari — dan pada berkas yang dapat diminta Inspektur Tambang,
           itu bukan persoalan kecil. */
        'inv_nomor_urut' => 'penghitung nomor; direset akan membuat nomor terpakai ulang',
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
