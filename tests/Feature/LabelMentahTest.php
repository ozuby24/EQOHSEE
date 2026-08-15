<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Kunci internal tidak boleh bocor ke layar sebagai label.
 *
 * Bentuk yang dijaga:
 *
 *     v-for="(value, key) in props.c"
 *     <p>{{ String(key).replaceAll('_', ' ') }}</p>
 *
 * Yang terjadi di layar: `pgTot` tampil sebagai "PGTOT", dan `byStat` —
 * yang isinya objek, bukan angka — tercetak utuh sebagai JSON di tengah
 * kartu. Halamannya tetap terbuka, tidak ada galat, tidak ada
 * peringatan; hanya bentuk dalam hitungan yang dipertontonkan kepada
 * orang yang mengira sedang membaca angka.
 *
 * Uji sisi server tidak dapat menangkapnya: propnya memang terkirim
 * benar. Yang salah cara menggambarnya, dan itu hanya terlihat oleh
 * mata — atau oleh uji seperti ini.
 *
 * Dua tempat pernah melakukannya sekaligus, Keselamatan Operasi dan
 * SMKP, dan keduanya luput bertahun-tahun karena halamannya memang
 * tidak pernah merender apa pun. Begitu halamannya hidup, keduanya
 * langsung terlihat.
 *
 * Yang SAH dan sengaja tidak dilarang: mengulang `form.errors`. Di sana
 * yang digambar adalah pesannya, bukan kuncinya — kuncinya hanya
 * dipakai sebagai :key, dan pesan validasi memang sudah kalimat.
 */
class LabelMentahTest extends TestCase
{
    /**
     * Yang sudah ada sebelum penjaga ini dipasang.
     *
     * Bukan pemutihan. Ketiganya memang mengulang kunci, tetapi kuncinya
     * satuan yang masih terbaca orang tambang — `kwh`, `gj`, `tco2e`,
     * `liter`, `l_ton` — bukan bentuk dalam seperti `byStat` yang
     * tercetak sebagai JSON. Beda kelas, dan memperbaikinya menuntut
     * penamaan tiap angka satu per satu, yaitu pekerjaan domain, bukan
     * penggantian mekanis.
     *
     * Didaftarkan tegas supaya dua hal terjadi sekaligus: utangnya
     * terlihat di dalam kode, dan kejadian BARU tetap ditolak.
     */
    private const DIKETAHUI = [
        'Energi/Halaman.vue: v-for="(value, key) in props.m"',
        'Energi/Halaman.vue: v-for="(value, key) in props.recon"',
        'Energi/Halaman.vue: v-for="(value, key) in props.total"',
        'Engineering/Halaman.vue: v-for="(v, key) in props.e"',
        'Engineering/Halaman.vue: v-for="(v, key) in props.h"',
    ];

    /**
     * Objek yang kuncinya memang DATA, bukan nama bidang.
     *
     * `perArea` berkunci nama area, `ambang` berkunci kode parameter gas
     * (o2, lel, co, h2s). Di sana kuncinya justru satu-satunya keterangan
     * yang benar — menggantinya dengan daftar tetap malah membuat area
     * atau parameter baru hilang dari tampilan tanpa ada yang tahu.
     */
    private const BERKUNCI_DATA = [
        'Energi/Halaman.vue: v-for="(row, key) in props.perArea"',
        'Print/Izin.vue: v-for="(a, kode) in props.ambang"',
    ];

    /** Berkas Vue tanpa komentar blok, supaya penjelasan tidak ikut tertangkap. */
    private function kode(string $berkas): string
    {
        $isi = file_get_contents($berkas);

        return preg_replace('#/\*.*?\*/#s', '', $isi) ?? $isi;
    }

    /** @return list<string> */
    private function halaman(): array
    {
        $keluar = [];

        foreach (['js/Pages', 'js/Components'] as $sub) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator(resource_path($sub))
            );

            foreach ($it as $f) {
                if (!$f->isFile() || $f->getExtension() !== 'vue') continue;
                if (str_contains($f->getFilename(), '.bak-')) continue;

                $keluar[] = $f->getPathname();
            }
        }

        return $keluar;
    }

    public function test_kunci_objek_server_tidak_dipakai_sebagai_label(): void
    {
        $langgar = [];

        foreach ($this->halaman() as $berkas) {
            $kode = $this->kode($berkas);

            /* Mengulang objek yang datang dari server — props.x, atau
               apa pun selain daftar galat formulir — sambil mengambil
               kuncinya. Kunci itu bentuk dalam, bukan bahasa. */
            preg_match_all(
                '/v-for="\(\s*[\w$]+\s*,\s*(\w+)\s*\)\s+in\s+(props\.[\w.?]+)"/',
                $kode, $c, PREG_SET_ORDER
            );

            foreach ($c as $m) {
                [$penuh, $kunci, $sumber] = $m;

                if (str_ends_with($sumber, '.errors')) continue;

                // Kuncinya digambar sebagai teks di suatu tempat?
                $digambar = preg_match(
                    '/\{\{[^}]*\b'.preg_quote($kunci, '/').'\b[^}]*\}\}/',
                    $kode
                );

                if ($digambar) {
                    $langgar[] = basename(dirname($berkas)).'/'.basename($berkas).': '.$penuh;
                }
            }
        }

        sort($langgar);

        $baru = array_values(array_diff($langgar, self::DIKETAHUI, self::BERKUNCI_DATA));

        $this->assertSame([], $baru,
            "Kunci objek dari server digambar sebagai label. Sebutkan kartunya satu per "
            ."satu di script, jangan diulang dari kuncinya:\n  ".implode("\n  ", $baru));
    }

    /**
     * Daftar utang tidak boleh menyimpan yang sudah lunas.
     *
     * Tanpa uji ini, baris yang sudah diperbaiki tetap tinggal di
     * DIKETAHUI selamanya, dan daftarnya perlahan berubah dari catatan
     * utang menjadi hiasan yang tidak lagi menggambarkan apa pun.
     */
    public function test_daftar_diketahui_tidak_memuat_yang_sudah_diperbaiki(): void
    {
        $ada = [];

        foreach ($this->halaman() as $berkas) {
            preg_match_all(
                '/v-for="\(\s*[\w$]+\s*,\s*\w+\s*\)\s+in\s+props\.[\w.?]+"/',
                $this->kode($berkas), $c
            );

            foreach ($c[0] as $penuh) {
                $ada[] = basename(dirname($berkas)).'/'.basename($berkas).': '.$penuh;
            }
        }

        $basi = array_values(array_diff(
            array_merge(self::DIKETAHUI, self::BERKUNCI_DATA),
            $ada
        ));

        $this->assertSame([], $basi,
            "Baris berikut sudah tidak ada di kode, keluarkan dari daftarnya:\n  "
            .implode("\n  ", $basi));
    }
}
