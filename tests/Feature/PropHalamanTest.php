<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Penjaga penerimaan prop pada halaman Vue.
 *
 * Sepuluh halaman modul pernah merender kosong seluruhnya, dan tidak
 * satu pun uji yang ada menangkapnya. Sebabnya satu baris yang terbaca
 * benar:
 *
 *     defineProps<{ mode: string; [key: string]: any }>()
 *
 * Terbaca seolah "terima prop apa saja". Yang sebenarnya terjadi:
 * penyusun Vue tidak dapat menurunkan nama prop dari sebuah index
 * signature, sehingga hanya `mode` yang terdaftar. Seluruh prop lain
 * jatuh ke $attrs, dan karena halaman-halaman itu berakar jamak
 * (<Head> beserta pembungkusnya) atribut itu tidak tersangkut di mana
 * pun. Halaman terbuka, bilah samping tampil, judul benar — dan seluruh
 * isinya nol.
 *
 * Yang membuatnya bertahan lama justru bentuk pengujian yang dipakai:
 * assertInertia memeriksa prop yang DIKIRIM server, dan seluruhnya
 * memang benar. Yang salah adalah penerimaannya di sisi peramban, dan
 * di situ tidak ada uji sama sekali. Uji ini menutup celah itu dari
 * sisi berkasnya, sebab menjalankan peramban di CI tidak tersedia.
 */
class PropHalamanTest extends TestCase
{
    /** @return list<string> */
    private function halaman(): array
    {
        $dasar = resource_path('js/Pages');
        $keluar = [];

        $iter = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dasar));
        foreach ($iter as $berkas) {
            if ($berkas->isFile() && $berkas->getExtension() === 'vue') {
                $keluar[] = $berkas->getPathname();
            }
        }

        sort($keluar);

        return $keluar;
    }

    /**
     * Seluruh .vue di resources/js, bukan hanya di Pages.
     *
     * Tata letak dan komponen bersama membaca prop halaman dengan cara
     * yang sama, dan penjaga yang berhenti di batas folder melewatkannya
     * — GuestLayout.vue ketahuan persis begitu.
     *
     * @return list<string>
     */
    private function semuaVue(): array
    {
        $keluar = [];

        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js'), \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iter as $berkas) {
            if ($berkas->isFile() && $berkas->getExtension() === 'vue') $keluar[] = $berkas->getPathname();
        }

        sort($keluar);

        return $keluar;
    }

    private function pendek(string $jalur): string
    {
        return str_replace([resource_path('js/Pages').'/', resource_path('js').'/'], '', $jalur);
    }

    /**
     * Isi berkas tanpa komentar blok.
     *
     * Catatan yang menjelaskan jebakan ini menyebut bentuk yang salah
     * secara harfiah di dalam teksnya. Tanpa pembuangan komentar,
     * penjaga di bawah menuduh kesebelas halaman yang justru sudah
     * diperbaiki — dan penjaga yang tertipu oleh komentarnya sendiri
     * lebih buruk daripada tidak ada penjaga.
     */
    private function kode(string $jalur): string
    {
        return preg_replace('#/\*.*?\*/#s', '', file_get_contents($jalur));
    }

    #[Test]
    public function tidak_ada_halaman_yang_mendeklarasikan_prop_lewat_index_signature(): void
    {
        $langgar = [];

        foreach ($this->halaman() as $jalur) {
            // Hanya blok defineProps yang diperiksa; index signature di
            // tempat lain (mis. Record bebas pada state lokal) tidak ada
            // hubungannya dengan penerimaan prop.
            if (!preg_match_all('/defineProps<\{(.*?)\}>\(/s', $this->kode($jalur), $cocok)) continue;

            foreach ($cocok[1] as $badan) {
                if (preg_match('/\[\s*\w+\s*:\s*string\s*\]\s*:/', $badan)) {
                    $langgar[] = $this->pendek($jalur);
                    break;
                }
            }
        }

        $this->assertSame([], $langgar,
            "defineProps dengan index signature hanya mendaftarkan prop yang disebut namanya; "
            ."sisanya hilang diam-diam dan halaman merender kosong. Sebutkan tiap prop satu per satu, "
            ."atau ambil seluruhnya lewat propHalaman():\n- ".implode("\n- ", $langgar));
    }

    #[Test]
    public function halaman_yang_memakai_props_punya_sumber_propnya(): void
    {
        $langgar = [];

        foreach ($this->halaman() as $jalur) {
            $kode = $this->kode($jalur);

            if (!preg_match('/\bprops\.\w/', $kode)) continue;

            $punyaDefine = str_contains($kode, 'defineProps');
            $punyaUsePage = preg_match('/const\s+props\s*=\s*(usePage|propHalaman)/', $kode) === 1;

            if (!$punyaDefine && !$punyaUsePage) {
                $langgar[] = $this->pendek($jalur);
            }
        }

        $this->assertSame([], $langgar,
            "Halaman memakai props.* tetapi tidak pernah mengambilnya:\n- ".implode("\n- ", $langgar));
    }

    /**
     * Yang mengambil prop lewat usePage harus benar-benar mengimpornya.
     *
     * Bukan kemungkinan teoretis: saat perbaikan ini dikerjakan, empat
     * halaman berpindah ke usePage() tanpa impornya ikut ditambahkan.
     * Build tetap berhasil — Vite tidak menolak pengenal yang tidak
     * dikenal di dalam <script setup> — dan keempat halaman itu blank
     * putih di peramban dengan 'usePage is not defined' hanya di konsol.
     */
    #[Test]
    public function halaman_yang_memakai_use_page_mengimpornya(): void
    {
        $langgar = [];

        foreach ($this->semuaVue() as $jalur) {
            $kode = $this->kode($jalur);

            if (str_contains($kode, 'usePage')
                && !preg_match("/import\s*\{[^}]*\busePage\b[^}]*\}\s*from\s*'@inertiajs\/vue3'/", $kode)) {
                $langgar[] = $this->pendek($jalur);
            }

            /* Penggantinya dijaga sama ketatnya: kegagalannya sama —
               pengenal tak dikenal yang lolos build dan baru terlihat
               sebagai layar putih di peramban. */
            if (preg_match('/\bpropHalaman\s*\(/', $kode)
                && !preg_match("/import\s*\{[^}]*\bpropHalaman\b[^}]*\}\s*from\s*'[^']*halaman'/", $kode)) {
                $langgar[] = $this->pendek($jalur);
            }
        }

        $this->assertSame([], $langgar,
            "usePage/propHalaman dipakai tanpa diimpor — halaman akan blank putih:\n- "
            .implode("\n- ", $langgar));
    }

    /**
     * Tidak ada halaman yang MENYALIN prop ke sebuah konstanta.
     *
     * Bentuk yang dilarang pernah dipakai 21 halaman:
     *
     *     const props = usePage<any>().props as any;
     *
     * `usePage().props` mengembalikan objek prop yang berlaku SAAT ITU.
     * Menyalinnya bekerja selama Inertia memasang ulang komponennya tiap
     * berpindah, dan berhenti bekerja begitu komponennya dipertahankan
     * (`preserveState: true`, atau kembalinya galat validasi). Sesudah
     * itu halamannya membaca prop lama selamanya.
     *
     * Kegagalannya diam sepenuhnya, dan itulah sebabnya ia bertahan
     * lama. Penyaring mengubah URL, server mengirim daftar yang benar,
     * konsol bersih, tidak ada baris yang tampak salah — layarnya hanya
     * tetap memperlihatkan daftar sebelumnya. Terbukti di dua halaman:
     * mencari "zzzz" di /miners tetap memberi 13 baris padahal URL yang
     * sama bila dimuat langsung memberi 9; menyaring "sudah habis" di
     * /miners/kedaluwarsa tetap memberi 6 padahal seharusnya 2. Seluruh
     * uji sisi server hijau — yang salah bukan datanya, melainkan
     * pembacaannya.
     *
     * Yang dilarang penyalinannya, BUKAN pemakaian usePage(). Membaca
     * `halaman.props.x` di dalam sebuah computed tetap hidup; yang
     * mematikan hanya `.props` yang diambil sekali lalu disimpan.
     */
    #[Test]
    public function prop_halaman_tidak_disalin_ke_konstanta(): void
    {
        $langgar = [];

        foreach ($this->semuaVue() as $jalur) {
            if (preg_match('#\bconst\s+\w+\s*(?::[^=]+)?=\s*usePage\s*(?:<[^>]*>)?\s*\(\s*\)\s*\.props\b#',
                           $this->kode($jalur))) {
                $langgar[] = $this->pendek($jalur);
            }
        }

        $this->assertSame([], $langgar,
            "Prop halaman disalin ke sebuah konstanta. Salinan itu berhenti diperbarui begitu "
            ."Inertia mempertahankan komponennya, dan penyaring di halaman itu berhenti "
            ."berpengaruh tanpa galat apa pun. Pakai propHalaman() dari resources/js/halaman.ts:\n- "
            .implode("\n- ", $langgar));
    }

    /**
     * Perantaranya memang meneruskan pembacaan, bukan menyalin.
     *
     * Yang membuat propHalaman() benar hanya satu hal: `halaman.props`
     * dibaca DI DALAM penangkap `get`, tiap kali diakses. Bila suatu
     * saat seseorang memindahkannya ke luar demi menghemat satu
     * pencarian, kedua puluh dua halaman kembali ke keadaan semula — dan
     * tidak ada satu pun uji lain yang akan berubah warna.
     */
    #[Test]
    public function perantara_membaca_prop_saat_diakses(): void
    {
        $isi = file_get_contents(resource_path('js/halaman.ts'));

        $get = substr($isi, (int) strpos($isi, 'get:'));
        $get = substr($get, 0, (int) strpos($get, "\n"));

        $this->assertStringContainsString('halaman.props', $get,
            'Penangkap get di propHalaman() tidak lagi membaca halaman.props saat diakses; '
            .'prop halaman kembali menjadi salinan mati.');
    }
}
