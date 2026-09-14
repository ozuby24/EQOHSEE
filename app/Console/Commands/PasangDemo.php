<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use App\Support\DataContoh;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pasang beberapa perusahaan contoh sekaligus, beserta isinya.
 *
 * SATU perusahaan contoh tidak cukup untuk memeriksa apa pun yang
 * membedakan perusahaan. Hitungan hari kerja audit SMKP membaca jumlah
 * pekerja dan kelas risikonya; nomor dokumen membaca prefiksnya; batas
 * data membaca company_id-nya. Dengan satu baris saja, ketiganya
 * terlihat benar justru karena tidak ada pembandingnya: kolom tabel
 * mandays yang salah tetap menghasilkan angka, prefiks yang tertukar
 * tetap mencetak nomor, dan kebocoran antar perusahaan tidak mungkin
 * terlihat sebab tidak ada perusahaan lain yang datanya dapat bocor.
 *
 * Karena itu profil di bawah sengaja berjauhan satu sama lain — 40
 * pekerja sampai 900, ketiga kelas risiko terpakai, komoditas dan
 * jenis izin berbeda — dan bukan lima salinan dengan nama berbeda.
 *
 * Perintah ini AMAN DIULANG. Ia menandai tiap perusahaannya sebagai
 * perusahaan contoh lebih dulu, dan DataContoh::muat() membuang isi
 * lama sebelum mengisi ulang; menjalankannya dua kali menghasilkan
 * keadaan yang sama, bukan data berganda.
 *
 * Yang TIDAK dilakukannya: menyentuh perusahaan yang tidak ada dalam
 * daftar di bawah. Penghapusan di dalam DataContoh selalu menyebut
 * company_id secara tegas, dan company_id itu berasal dari baris yang
 * dibuat perintah ini sendiri.
 */
class PasangDemo extends Command
{
    protected $signature = 'demo:pasang
        {--hanya= : Kode perusahaan yang dikerjakan saja, dipisah koma (mis. CDI,ABG)}
        {--tanpa-isi : Buat perusahaan dan penggunanya saja, tanpa memuat data contohnya}
        {--kosongkan : Buang data contohnya, tanpa mengisi ulang}
        {--hapus : Buang data contohnya, akun-akunnya, DAN perusahaannya}
        {--paksa : Jangan bertanya lebih dulu pada --hapus}';

    protected $description = 'Pasang beberapa perusahaan contoh berprofil berbeda beserta pengguna dan data contohnya.';

    /** Kata sandi seluruh akun contoh. Sengaja seragam dan sengaja lemah:
     *  akun ini hanya ada pada pemasangan contoh. */
    private const SANDI = 'rahasia123';

    /**
     * Profil perusahaan contoh.
     *
     * `risiko` HARUS salah satu dari SmkpTahap::kelasRisiko() —
     * 'Tinggi', 'Menengah', 'Rendah'. Ejaan yang meleset sedikit saja
     * ("Sedang") membuat perusahaannya jatuh ke kolom cadangan tabel
     * mandays dan ditagih hari kerja kelas tertinggi tanpa peringatan.
     */
    private const PROFIL = [
        [
            'kode' => 'CDI', 'nama' => 'PT Citra Dayak Indah',
            'izin' => 'IUP Operasi Produksi', 'komoditas' => 'Batubara',
            'lokasi' => 'Kabupaten Barito Utara, Kalimantan Tengah',
            'alamat' => 'Jalan Ahmad Yani KM 8, Muara Teweh, Kalimantan Tengah 73811',
            'risiko' => 'Tinggi', 'karyawan' => 120, 'jasa' => 340,
            'ktt' => 'Ir. Budi Santoso', 'pjo' => 'Rahmat Hidayat, S.T.',
            'prefiks' => 'CDI', 'dept' => 'OHSE',
        ],
        [
            'kode' => 'BMU', 'nama' => 'PT Borneo Mandiri Utama',
            'izin' => 'IUP Operasi Produksi', 'komoditas' => 'Nikel',
            'lokasi' => 'Kabupaten Kutai Timur, Kalimantan Timur',
            'alamat' => 'Jalan Poros Sangatta–Bengalon KM 14, Sangatta, Kalimantan Timur 75683',
            'risiko' => 'Menengah', 'karyawan' => 85, 'jasa' => 210,
            'ktt' => 'Ir. Slamet Riyadi', 'pjo' => 'Dewi Kartika, S.T.',
            'prefiks' => 'BMU', 'dept' => 'OHSE',
        ],
        [
            'kode' => 'SNP', 'nama' => 'PT Sulawesi Nikel Persada',
            'izin' => 'IUPK Operasi Produksi', 'komoditas' => 'Nikel',
            'lokasi' => 'Kabupaten Konawe Utara, Sulawesi Tenggara',
            'alamat' => 'Desa Molawe, Kecamatan Molawe, Konawe Utara, Sulawesi Tenggara 93353',
            'risiko' => 'Tinggi', 'karyawan' => 260, 'jasa' => 640,
            'ktt' => 'Ir. Haryanto Wibowo, M.T.', 'pjo' => 'Andi Mappatoba, S.T.',
            'prefiks' => 'SNP', 'dept' => 'OHSE',
        ],
        [
            'kode' => 'HBS', 'nama' => 'PT Halmahera Bumi Sejahtera',
            'izin' => 'IUP Operasi Produksi', 'komoditas' => 'Emas',
            'lokasi' => 'Kabupaten Halmahera Tengah, Maluku Utara',
            'alamat' => 'Desa Lelilef Sawai, Weda Tengah, Halmahera Tengah, Maluku Utara 97853',
            'risiko' => 'Menengah', 'karyawan' => 45, 'jasa' => 95,
            'ktt' => 'Ir. Yosef Tanamal', 'pjo' => 'Nurul Fadhilah, S.T.',
            'prefiks' => 'HBS', 'dept' => 'OHSE',
        ],
        [
            'kode' => 'ABG', 'nama' => 'PT Andalas Batu Gamping',
            'izin' => 'IUP Operasi Produksi Batuan', 'komoditas' => 'Batu Gamping',
            'lokasi' => 'Kabupaten Lima Puluh Kota, Sumatera Barat',
            'alamat' => 'Jorong Koto Tuo, Harau, Lima Puluh Kota, Sumatera Barat 26271',
            'risiko' => 'Rendah', 'karyawan' => 18, 'jasa' => 22,
            'ktt' => 'Ir. Zulkifli Hasan', 'pjo' => 'Rina Marlina, S.T.',
            'prefiks' => 'ABG', 'dept' => 'OHSE',
        ],
    ];

    public function handle(): int
    {
        $hanya = array_filter(array_map(
            static fn ($s) => strtoupper(trim($s)),
            explode(',', (string) $this->option('hanya'))
        ));

        $profil = $hanya === []
            ? self::PROFIL
            : array_values(array_filter(self::PROFIL, static fn ($p) => in_array($p['kode'], $hanya, true)));

        if ($profil === []) {
            $this->error('Tidak ada profil yang cocok dengan --hanya. Kode yang tersedia: '
                .implode(', ', array_column(self::PROFIL, 'kode')).'.');

            return self::FAILURE;
        }

        $ditolak = 0;

        foreach ($profil as $p) {
            $c = $this->perusahaan($p);

            if ($c === null) {
                $ditolak++;
                $this->line('');
                $this->warn(sprintf('%s  (%s) DILEWATI', $p['nama'], $p['kode']));
                $this->line(sprintf('  %-14s kode %s sudah dipakai perusahaan yang BUKAN perusahaan contoh.',
                    'alasan', $p['kode']));
                $this->line(sprintf('  %-14s pakai --hanya untuk melewati kode ini, atau ganti kodenya lebih dulu.',
                    ''));

                continue;
            }

            [$ktt, $pjo] = $this->pengguna($c, $p);

            $this->line('');
            $this->info($c->name.'  ('.$p['kode'].')');
            $this->line(sprintf('  %-14s %s, kelas %s, %d pekerja',
                'profil', $p['komoditas'], $p['risiko'], $p['karyawan'] + $p['jasa']));
            $this->line(sprintf('  %-14s %s / %s', 'akun', $ktt->email, $pjo->email));

            if ($this->option('hapus')) {
                $this->hapus($c, $p);

                continue;
            }

            if ($this->option('kosongkan')) {
                $hasil = DB::transaction(fn () => DataContoh::buang($c));
                $this->line(sprintf('  %-14s %d baris dibuang', 'kosong', $hasil['dihapus']));

                continue;
            }

            if ($this->option('tanpa-isi')) {
                $this->line(sprintf('  %-14s dilewati (--tanpa-isi)', 'isi'));

                continue;
            }

            $hasil = DB::transaction(fn () => DataContoh::muat($c, $ktt));

            $this->line(sprintf('  %-14s %d baris lama dibuang, %d baris dibuat di %d modul',
                'isi', $hasil['dihapus'], array_sum($hasil['dibuat']), count($hasil['dibuat'])));

            foreach ($hasil['catatan'] as $catatan) {
                $this->warn('  catatan       '.$catatan);
            }
        }

        $this->line('');

        if ($ditolak) {
            $this->warn("{$ditolak} profil dilewati karena kodenya dipakai perusahaan sungguhan.");
        }

        $this->info('Selesai. Kata sandi seluruh akun contoh: '.self::SANDI);

        return self::SUCCESS;
    }

    /**
     * Perusahaannya, dikunci pada KODE dan bukan pada namanya.
     *
     * Nama perusahaan adalah kolom yang paling mungkin disunting orang
     * setelah pemasangan; dikunci padanya, menjalankan ulang perintah
     * ini akan membuat perusahaan KEDUA di samping yang sudah ada,
     * lengkap dengan data contoh berganda yang keduanya terlihat sah.
     */
    /**
     * Perusahaan contohnya, atau NULL bila kodenya sudah dipakai
     * perusahaan sungguhan.
     *
     * Barisnya dicari menurut `code`, dan kode itu tidak dijamin milik
     * data contoh. Tanpa penjagaan di bawah, menjalankan perintah ini
     * pada pemasangan yang dipakai sungguhan — yang perusahaannya
     * kebetulan berkode CDI, BMU, SNP, HBS, atau ABG — akan MENIMPA
     * namanya, lokasinya, komoditasnya, kelas risikonya, jumlah
     * pekerjanya, prefiks dokumennya, KTT dan PJO-nya, lalu menandainya
     * sebagai perusahaan contoh dan mengisinya dengan data karangan.
     *
     * Tidak ada galat yang muncul dari itu. Yang terlihat hanyalah
     * perusahaan yang mendadak berganti identitas — dan sesudah
     * ditandai contoh, ia ikut terhapus oleh `--hapus`.
     */
    private function perusahaan(array $p): ?Company
    {
        $c = Company::withoutGlobalScopes()->firstOrNew(['code' => $p['kode']]);

        if ($c->exists && !$c->demo) return null;

        $c->fill([
            'name'             => $p['nama'],
            'demo'             => true,
            'izin_type'        => $p['izin'],
            'commodity'        => $p['komoditas'],
            'location'         => $p['lokasi'],
            'address'          => $p['alamat'],
            'risk_class'       => $p['risiko'],
            'workers_employee' => $p['karyawan'],
            'workers_sub'      => $p['jasa'],
            'ktt'              => $p['ktt'],
            'pjo'              => $p['pjo'],
            'pic_name'         => $p['pjo'],
            'pic_email'        => 'pjo.'.strtolower($p['kode']).'@contoh.test',
            'pic_phone'        => '0811-'.random_int(1000, 9999).'-'.random_int(1000, 9999),
            'doc_no_prefix'    => $p['prefiks'],
            'dept_kode'        => $p['dept'],
            'divisi'           => \App\Support\KopDokumen::DIVISI,
            'departemen'       => \App\Support\KopDokumen::DEPARTEMEN,
            'doc_terbit'       => now()->subYear()->startOfMonth()->toDateString(),
            'doc_setuju'       => now()->subYear()->startOfMonth()->addDays(6)->toDateString(),
            'doc_revisi'       => 1,
            'parent'           => $c->parent ?? '',
        ])->save();

        return $c;
    }

    /**
     * Dua akun per perusahaan: satu yang mengajukan, satu yang meninjau.
     *
     * Keduanya harus ADA DAN BERBEDA. Alur persetujuan menolak orang
     * yang menyetujui pekerjaannya sendiri — penolakan yang memang
     * benar — sehingga perusahaan berakun tunggal hanya menghasilkan
     * draf, dan seluruh angka KPI-nya nol tanpa satu pun galat.
     *
     * @return array{0:User,1:User}
     */
    /**
     * Buang perusahaan contoh beserta isinya DAN akun-akunnya.
     *
     * `--kosongkan` hanya mengosongkan isinya; perusahaannya dan kedua
     * akunnya tetap tinggal. Itu memang yang dimaui saat menyiapkan
     * ulang pratinjau, tetapi bukan saat membersihkan setelahnya —
     * terutama di pemasangan yang dipakai sungguhan, tempat lima
     * perusahaan karangan menumpuk di pemilih perusahaan dan sepuluh
     * akun berkata sandi seragam yang lemah tetap dapat masuk.
     *
     * DUA PENJAGA, dan keduanya harus terpenuhi bersama: kodenya ada
     * dalam PROFIL di berkas ini, DAN barisnya bertanda perusahaan
     * contoh. Yang pertama saja tidak cukup — perusahaan sungguhan boleh
     * saja kebetulan berkode "CDI".
     */
    private function hapus(Company $c, array $p): void
    {
        if (!$c->demo) {
            $this->warn(sprintf('  %-14s BUKAN perusahaan contoh — tidak disentuh.', 'hapus'));

            return;
        }

        if (Company::count() <= 1) {
            $this->warn(sprintf('  %-14s perusahaan terakhir, dibiarkan supaya aplikasinya tidak kehilangan semuanya.', 'hapus'));

            return;
        }

        if (!$this->option('paksa')
            && !$this->confirm("Hapus {$c->name} beserta isinya dan kedua akunnya?", false)) {
            $this->line(sprintf('  %-14s dilewati', 'hapus'));

            return;
        }

        DB::transaction(function () use ($c) {
            $dibuang = DataContoh::buang($c)['dihapus'];

            /* SELURUH pengguna perusahaan ini, bukan hanya KTT dan PJO.
               Data contoh Miners menerbitkan enam akun pekerja lagi per
               perusahaan, dan `DataContoh::buang()` sengaja tidak
               menyentuh pengguna — tabel users memang berada di luar
               jangkauannya.

               Id-nya dikumpulkan SEBELUM perusahaannya dihapus. Sesudah
               itu company_id mereka menjadi NULL, sehingga tidak ada lagi
               cara menghubungkan mereka dengan perusahaan mana pun —
               terukur: dua perusahaan contoh yang dihapus meninggalkan
               dua belas akun pekerja tanpa perusahaan, seluruhnya masih
               aktif dan masih dapat masuk dengan sandi contoh yang
               seragam dan lemah.

               Akunnya DIHAPUS, bukan dilepas seperti pada penghapusan
               perusahaan biasa: yang dilepas tetap dapat masuk. */
            $id   = User::where('company_id', $c->id)->pluck('id');
            $akun = User::whereIn('id', $id)->delete();

            $c->delete();

            $this->line(sprintf('  %-14s %d baris, %d akun, dan perusahaannya dihapus', 'hapus', $dibuang, $akun));
        });
    }

    private function pengguna(Company $c, array $p): array
    {
        $kode = strtolower($p['kode']);

        $ktt = $this->akun('ktt.'.$kode.'@contoh.test', [
            'name'       => $p['ktt'],
            'company_id' => $c->id,
            'position'   => 'Kepala Teknik Tambang',
            'department' => 'Operasi Tambang',
            'lms_role'   => 'ktt',
            'active'     => true,
        ]);

        $pjo = $this->akun('pjo.'.$kode.'@contoh.test', [
            'name'       => $p['pjo'],
            'company_id' => $c->id,
            'position'   => 'Penanggung Jawab Operasional',
            'department' => 'Occupational Health, Safety and Environment',
            'lms_role'   => 'peserta',
            'active'     => true,
        ]);

        return [$ktt, $pjo];
    }

    /**
     * Satu akun.
     *
     * KATA SANDINYA TIDAK DITIMPA pada akun yang sudah ada. Perintah
     * ini dijalankan ulang tiap kali data contohnya disegarkan, dan
     * menimpa sandi setiap kali berarti sandi yang diganti orang
     * kembali ke bawaannya diam-diam — pada akun yang, di pemasangan
     * demo yang terbuka ke jaringan, tidak lagi sekadar akun contoh.
     */
    private function akun(string $surel, array $atribut): User
    {
        $u = User::withoutGlobalScopes()->firstOrNew(['email' => $surel]);

        if (!$u->exists) {
            $u->password          = self::SANDI;
            $u->email_verified_at = now();
        }

        $u->fill($atribut)->save();

        return $u;
    }
}
