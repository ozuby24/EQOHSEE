<?php

namespace App\Support\Miners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Daftar awal bersama bagi modul Miners.
 *
 * SELURUHNYA TANPA PEMILIK (company_id NULL), dan itu yang membuatnya
 * berguna. Baris tanpa pemilik terlihat oleh setiap perusahaan — lihat
 * App\Models\Scopes\MilikPerusahaan — jadi tambang yang baru dipasang
 * langsung punya daftar departemen, lokasi kerja, golongan unit, jenis
 * permit, dan hasil MCU tanpa seorang pun mengetiknya lebih dulu.
 * Begitu perusahaan menyusun daftarnya sendiri, keduanya tampil
 * berdampingan dan yang ini dapat dinonaktifkan.
 *
 * TANPA LANGKAH INI MODULNYA TIDAK DAPAT DIPAKAI SAMA SEKALI, dan
 * kegagalannya diam: formulir MCU terbuka dengan daftar hasil yang
 * kosong, formulir permit terbuka tanpa satu jenis permit pun, dan
 * penyimpanannya gagal di tingkat basis data. Yang terlihat pengguna
 * hanyalah galat 500 tanpa sebab.
 *
 * IDEMPOTEN, DAN TIDAK PERNAH MENGHAPUS.
 *
 * Itu bukan kerapian. `mnr_pekerja.departemen_id`, `mnr_mcu_orang`,
 * `mnr_permit.tipe_permit_id`, dan `mnr_simper_unit.kendaraan_id`
 * semuanya menunjuk ke daftar di bawah ini. Penyemai yang mengosongkan
 * tabelnya lebih dahulu akan memutus rujukan ribuan dokumen yang sudah
 * terbit — dan kartu yang dicetak kemudian kehilangan nama golongan
 * unitnya tanpa satu pun galat. Perintah `miners:pasang` dijalankan
 * ulang pada TIAP penerapan, jadi kemungkinannya bukan hipotetis.
 *
 * KUNCI PENGENALNYA KOLOM `kunci`, BUKAN NAMANYA.
 *
 * Itu perbedaan yang menentukan, dan ia ditemukan oleh ujinya sendiri.
 * Dikenali lewat nama, satu penggantian nama saja melahirkan kembar:
 * departemen "HSE" yang disunting menjadi "HSE & Lingkungan" tidak lagi
 * ditemukan pada pemasangan berikutnya, sehingga "HSE" DITAMBAHKAN
 * KEMBALI — dan daftar pilih memuat keduanya. Dokumen pun tersebar di
 * antara dua baris yang seharusnya satu, dan laporan per departemen
 * menghitungnya terpisah. Tidak ada galat di mana pun.
 *
 * `kunci` diturunkan dari nama menurut SOP dan tidak pernah berubah
 * sesudahnya. Baris yang dibuat perusahaan sendiri berkunci NULL:
 * ia bukan milik daftar ini dan tidak boleh ikut diperbarui.
 *
 * SATU JEMBATAN DISEDIAKAN, dan hanya satu: baris tanpa kunci yang
 * NAMANYA persis sama dengan salah satu nama SOP diangkat menjadi baris
 * SOP — kuncinya distempelkan, isinya tidak diganti. Tanpa itu,
 * pemasangan pertama pada basis data yang departemennya sudah diketik
 * tangan akan menggandakan seluruh daftarnya.
 *
 * Pencocokan nama pada jembatan itu TIDAK PEDULI HURUF BESAR-KECIL:
 * "produksi" dan "Produksi" adalah departemen yang sama.
 *
 * YANG SUDAH ADA HANYA DIPERBARUI PADA KOLOM ACUAN, tidak pada
 * namanya. Kelas SIMPOL dan kewajiban SIO memang berasal dari SOP dan
 * harus ikut berubah bila SOP direvisi; nama yang sudah dipakai ratusan
 * dokumen tidak boleh berganti hanya karena perintah ini dijalankan
 * lagi.
 */
final class MasterMiners
{
    /**
     * Pasang seluruh daftar awal. Aman dijalankan berulang.
     *
     * @return array<string,int> nama tabel => jumlah baris sesudahnya
     */
    public static function pasang(): array
    {
        self::departemen();
        self::jabatan();
        self::blok();
        self::kendaraan();
        self::tipePermit();
        self::hasilMcu();

        $tabel = [
            'mnr_departemen', 'mnr_jabatan', 'mnr_blok', 'mnr_kendaraan',
            'mnr_sub_kendaraan', 'mnr_jenis_unit', 'mnr_tipe_permit',
            'mnr_kategori_permit', 'mnr_hasil_mcu',
        ];

        $hasil = [];
        foreach ($tabel as $t) {
            $hasil[$t] = (int) DB::table($t)->count();
        }

        return $hasil;
    }

    /* ═══════════════════ daftar ═══════════════════ */

    private static function departemen(): void
    {
        foreach (Acuan::DEPARTEMEN as $i => $d) {
            self::baris('mnr_departemen', $d['nama'], [
                'kode'   => $d['kode'],
                'urutan' => ($i + 1) * 10,
            ]);
        }
    }

    private static function jabatan(): void
    {
        foreach (Acuan::JABATAN as $i => $nama) {
            self::baris('mnr_jabatan', $nama, ['urutan' => ($i + 1) * 10]);
        }
    }

    private static function blok(): void
    {
        foreach (Acuan::LOKASI_KERJA as $i => $nama) {
            self::baris('mnr_blok', $nama, ['urutan' => ($i + 1) * 10]);
        }
    }

    /**
     * Golongan unit beserta rincian dan daftar ratanya.
     *
     * Tiga tabel sekaligus, dan itu disengaja: `mnr_jenis_unit` adalah
     * daftar RATA yang dipakai pada baris unit SIMPER, sedangkan
     * `mnr_kendaraan` + `mnr_sub_kendaraan` adalah pohon golongan yang
     * dipakai untuk mencocokkan kelas SIM. Keduanya diisi dari satu
     * sumber supaya tidak ada nama unit yang hidup di salah satu tetapi
     * tidak di yang lain.
     */
    private static function kendaraan(): void
    {
        $rata = [];

        foreach (Acuan::GOLONGAN_UNIT as $i => $g) {
            $id = self::baris('mnr_kendaraan', $g['nama'], [
                'kode'         => $g['kode'],
                'kelas_simpol' => $g['simpol'],
                'wajib_sio'    => $g['sio'],
                'urutan'       => ($i + 1) * 10,
            ]);

            $rincian = $g['rincian'] ?? [];

            foreach ($rincian as $r) {
                self::anak('mnr_sub_kendaraan', 'kendaraan_id', $id, $r);
            }

            /* Golongan tanpa rincian MASUK KE DAFTAR RATA ATAS NAMANYA
               SENDIRI. Light Vehicle bukan payung bagi unit lain — ia
               unitnya. Dilewatkan, daftar unit SIMPER kehilangan
               golongan yang justru paling sering dipilih. */
            foreach ($rincian === [] ? [$g['nama']] : $rincian as $nama) {
                $rata[] = $nama;
            }
        }

        foreach ($rata as $i => $nama) {
            self::baris('mnr_jenis_unit', $nama, ['urutan' => ($i + 1) * 10]);
        }
    }

    private static function tipePermit(): void
    {
        foreach (array_values(Acuan::JENIS_PERMIT) as $i => $t) {
            $id = self::baris('mnr_tipe_permit', $t['label'], [
                'hari_berlaku' => $t['hari'],
                'urutan'       => ($i + 1) * 10,
            ]);

            foreach ($t['kategori'] as $k) {
                self::anak('mnr_kategori_permit', 'tipe_permit_id', $id, $k);
            }
        }
    }

    private static function hasilMcu(): void
    {
        foreach (Acuan::HASIL_MCU as $i => $h) {
            self::baris('mnr_hasil_mcu', $h['nama'], [
                'nilai'  => $h['nilai'],
                'layak'  => $h['layak'],
                'urutan' => ($i + 1) * 10,
            ]);
        }
    }

    /* ═══════════════════ penyimpanan ═══════════════════ */

    /**
     * Satu baris daftar awal bersama — tambah bila belum ada,
     * perbarui kolom acuannya bila sudah.
     *
     * @param  array<string,mixed>  $kolom
     * @return int  id barisnya
     */
    private static function baris(string $tabel, string $nama, array $kolom): int
    {
        return self::simpan($tabel, $nama, $kolom, ['company_id' => null], ['aktif' => true]);
    }

    /** Baris anak — kuncinya tetap, tetapi berlaku di dalam induknya saja. */
    private static function anak(string $tabel, string $kolomInduk, int $induk, string $nama): int
    {
        /* Tanpa pemilik, sama seperti induknya: rincian golongan unit
           dan kategori izin khusus berasal dari SOP, bukan dari salah
           satu tambang. Disebut tegas — baris acuan yang lahir dengan
           pemilik hanya terlihat oleh satu perusahaan, dan yang lain
           mendapati daftar pilihnya kosong tanpa satu galat pun. */
        return self::simpan($tabel, $nama, [], [$kolomInduk => $induk, 'company_id' => null], []);
    }

    /**
     * Tambah atau perbarui satu baris acuan, dikenali lewat `kunci`.
     *
     * @param  array<string,mixed>  $kolom     kolom yang BOLEH diperbarui
     * @param  array<string,mixed>  $lingkup   penyaring + isian baris baru
     * @param  array<string,mixed>  $bawaan    hanya dipakai saat baris lahir
     */
    private static function simpan(string $tabel, string $nama, array $kolom, array $lingkup, array $bawaan): int
    {
        $kunci = Str::slug($nama);
        $saat  = now();

        $cari = fn () => DB::table($tabel)->where($lingkup);

        $ada = $cari()->where('kunci', $kunci)->first();

        /* Jembatan sekali jalan: baris yang sudah ada sejak sebelum
           kolom `kunci` dipakai — atau yang diketik tangan dengan nama
           yang sama persis — diangkat menjadi baris acuan alih-alih
           digandakan. Sesudah kuncinya terstempel, ia ditemukan lewat
           jalur di atas dan namanya bebas diubah. */
        if (! $ada) {
            $ada = $cari()->whereNull('kunci')
                ->whereRaw('LOWER(nama) = ?', [mb_strtolower($nama)])
                ->first();
        }

        if ($ada) {
            /* Namanya TIDAK ikut diperbarui: yang tersimpan mungkin
               sudah disunting perusahaan yang memakainya, dan nama
               itulah yang tercetak pada kartu yang sudah terbit. Yang
               diperbarui hanya kolom yang berasal dari SOP. */
            DB::table($tabel)->where('id', $ada->id)
                ->update($kolom + ['kunci' => $kunci, 'updated_at' => $saat]);

            return (int) $ada->id;
        }

        return (int) DB::table($tabel)->insertGetId($kolom + $lingkup + $bawaan + [
            'kunci'      => $kunci,
            'nama'       => $nama,
            'created_at' => $saat,
            'updated_at' => $saat,
        ]);
    }
}
