<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Penjagaan atas hal-hal yang hanya terlihat di layar ponsel.
 *
 * Aplikasi ini dipakai di site tambang, dan sebagian besar pemakaiannya
 * di lapangan terjadi lewat telepon genggam — sering dengan sarung
 * tangan. Cacat yang muncul hanya di sana tidak pernah terlihat oleh
 * siapa pun yang mengembangkannya di layar lebar, dan tidak satu pun
 * menimbulkan galat: tombol yang tulisannya keluar dari kotaknya tetap
 * dapat ditekan, kolom tabel yang terpotong tetap "ada", dan kendali
 * tanpa label tetap berfungsi bagi yang melihatnya.
 *
 * Yang diuji di sini adalah ATURANNYA di dalam kode, bukan hasil
 * rendernya — uji render menuntut peramban sungguhan, dan uji yang
 * menuntut peramban akan dilewati pada mesin yang tidak punya. Yang
 * ditegakkan: aturan CSS yang menjadi lantainya masih ada, dan tidak ada
 * kendali baru yang lahir tanpa label.
 *
 * Pengukuran yang melahirkan uji ini, di layar 393px sebelum diperbaiki:
 * 51 tombol dan 18 tautan di bawah 24px, 50 kendali tanpa label, satu
 * tombol saringan yang tulisannya terpotong, dan satu tabel yang empat
 * kolomnya tidak terjangkau.
 */
class LapanganTest extends TestCase
{
    /**
     * Bilah samping harus dapat digulir sampai butir terakhirnya.
     *
     * <nav class="flex-1 overflow-y-auto"> di dalam wadah flex TIDAK
     * menggulir tanpa `min-h-0`. Anak flex punya `min-height: auto`, yang
     * melarangnya menyusut di bawah tinggi ISINYA — jadi ia tumbuh
     * melewati wadahnya, dan `overflow-y-auto` tidak pernah punya sesuatu
     * untuk digulir.
     *
     * Terukur sebelum diperbaiki: pada laci ponsel, nav setinggi 1.464px
     * di dalam aside yang dibatasi tinggi layar, tidak dapat digulir sama
     * sekali. Sekitar sepertiga butir menu tidak terjangkau siapa pun.
     *
     * Cacat lamanya diam karena di layar lebar aside memang tumbuh bebas
     * dan halamanlah yang menggulir. Ia hanya menggigit pada laci ponsel,
     * yang posisinya `fixed` dan karena itu dibatasi tinggi layar.
     */
    public function test_bilah_samping_dapat_digulir(): void
    {
        $tata = file_get_contents(resource_path('js/Layouts/AppLayout.vue'));

        preg_match('/<nav class="([^"]*overflow-y-auto[^"]*)"/', $tata, $m);

        $this->assertNotEmpty($m[1] ?? '', 'Bilah samping tidak lagi punya nav yang menggulir.');

        $this->assertStringContainsString('min-h-0', $m[1],
            'nav bilah samping kehilangan min-h-0. Tanpa itu ia tumbuh melewati '
            .'wadahnya dan butir terbawah tidak terjangkau di laci ponsel.');
    }

    /**
     * TIDAK ADA aturan global yang menaikkan tinggi kendali.
     *
     * Aturan semacam itu pernah dipasang di sini — `min-height` pada
     * button dan a[href] di perangkat sentuh, demi memenuhi WCAG 2.2 AA
     * (2.5.8). Ia dibuang seluruhnya sesudah merusak tampilan tiga kali:
     *
     *   Menyentuh `display` merobohkan setiap `<a class="flex …">`
     *   menjadi inline; ikon bilah samping menumpuk di atas labelnya, di
     *   seluruh modul sekaligus.
     *
     *   `min-height` pada <a> merenggangkan baris daftar. Di halaman
     *   Miners, nama orang dan keterangan statusnya berhenti sebaris.
     *
     *   `min-height` pada seluruh tautan menu membuat daftar bilah samping
     *   melewati wadahnya, dan pada laci ponsel butir terbawah menjadi
     *   tak terjangkau.
     *
     * Sebabnya satu dan sama: di aplikasi ini <a> bukan hanya kendali
     * yang berdiri sendiri, ia juga ISI baris — nama di dalam daftar, kode
     * di dalam tabel. Aturan global tidak dapat membedakan keduanya, dan
     * kerusakannya selalu muncul di halaman yang tidak disebut dalam
     * perubahan itu.
     *
     * Memperbesar sasaran sentuh tetap layak dikerjakan — tetapi per
     * tempat, dengan menambah padding pada tombol yang memang terlalu
     * kecil, bukan lewat satu pemilih yang menyapu semuanya.
     */
    public function test_tidak_ada_aturan_tinggi_global(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringNotContainsString('pointer: coarse', $css,
            'Aturan tinggi global dipasang lagi. Baca alasannya di docblock uji ini '
            .'lebih dulu — ia sudah tiga kali merusak tampilan.');
    }

    /**
     * Tombol tidak boleh menyusut sampai lebih kecil daripada tulisannya.
     *
     * `.eq-btn-utama` memakai `flex:1`, yang berarti flex-basis nol —
     * tombolnya mulai dari lebar NOL lalu tumbuh dari sisa ruang. Di baris
     * sempit sisa ruangnya bisa lebih kecil daripada teksnya sendiri.
     * Terukur: tombol "Terapkan" menjadi 47px untuk teks yang butuh 85px,
     * dan tulisannya keluar dari kotak jingganya.
     */
    public function test_tombol_punya_lantai_lebar(): void
    {
        $gaya = file_get_contents(resource_path('views/partials/eq-visual.blade.php'));

        foreach (['eq-btn-utama', 'eq-btn-lain'] as $kelas) {
            $i = strpos($gaya, ".$kelas{");
            $this->assertNotFalse($i, "Kelas .$kelas hilang.");

            $blok = substr($gaya, $i, strpos($gaya, '}', $i) - $i);

            $this->assertStringContainsString('min-width:fit-content', $blok,
                ".$kelas dapat menyusut sampai tulisannya keluar dari kotaknya.");
        }
    }

    /**
     * Tidak ada kendali saringan yang lahir tanpa label.
     *
     * Dibatasi pada input tanggal dan select — keduanya tidak dapat
     * menjelaskan dirinya sendiri lewat isinya, berbeda dari kotak teks
     * yang biasanya punya placeholder. Halaman cetak dikecualikan: ia
     * dibaca di kertas, bukan dengan pembaca layar.
     */
    public function test_tidak_ada_kendali_tanpa_label(): void
    {
        $tanpa = [];

        foreach ($this->berkasVue() as $berkas) {
            $isi = file_get_contents($berkas);

            /* Kedalaman <label> dihitung dari awal berkas, bukan ditebak
               dari jendela beberapa ratus karakter sebelumnya.

               Percobaan pertama memakai jendela 400 karakter dan
               melaporkan 57 kendali "tanpa label" yang sebenarnya
               berlabel — <label> pada berkas ini kerap membentang jauh
               lebih panjang daripada itu, memuat teks bantuan dan
               beberapa baris atribut. Uji yang menuduh 57 tempat yang
               benar akan dimatikan orang pada hari pertama, dan
               bersamanya hilang pula penjagaan atas yang sungguh
               salah. */
            $dalamLabel = 0;
            $tanda = preg_split(
                '/(<label\b|<\/label>|<input\b[^>]*>|<select\b[^>]*>)/',
                $isi, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE,
            );

            foreach ($tanda as [$potong, $pos]) {
                if (str_starts_with($potong, '<label')) { $dalamLabel++; continue; }
                if (str_starts_with($potong, '</label>')) { $dalamLabel = max(0, $dalamLabel - 1); continue; }

                $adalahInput  = str_starts_with($potong, '<input');
                $adalahSelect = str_starts_with($potong, '<select');
                if (!$adalahInput && !$adalahSelect) continue;

                if ($dalamLabel > 0) continue;
                if ($adalahInput && !str_contains($potong, 'type="date"')) continue;
                if (preg_match('/aria-label|placeholder=|title=/', $potong)) continue;

                $baris = substr_count(substr($isi, 0, $pos), "\n") + 1;
                $tanpa[] = str_replace(base_path().'/', '', $berkas).':'.$baris;
            }
        }

        $this->assertSame([], $tanpa,
            "Kendali berikut tidak punya label — pembaca layar tidak menyebut apa pun:\n  "
            .implode("\n  ", $tanpa));
    }

    /**
     * Sasaran sentuh yang sudah dilebarkan tidak boleh menyusut lagi.
     *
     * Dua tempat ini menampung sebagian besar tautan aksi yang tadinya
     * di bawah 24px: kelas bersama `.eq-tautan` / `.eq-panel-lihat`, dan
     * legenda diagram donat yang dipakai ulang di tujuh halaman.
     *
     * Yang dijaga bukan angka pastinya, melainkan bahwa paddingnya masih
     * ada. Menghapusnya mengembalikan 36 temuan yang sudah ditutup, dan
     * tidak satu pun menimbulkan galat — tautan 17px tetap dapat diklik
     * oleh siapa pun yang memakai tetikus.
     *
     * Catatan tentang `margin-block` negatif yang menyertainya: itulah
     * yang membuat paddingnya tidak menggeser apa pun. Ia hanya bekerja
     * pada kotak sebaris dan inline-flex; pada elemen blok ia dapat
     * menciut bersama margin tetangganya, dan pernah menggeser tombol
     * "Kirim Pesan" 6px ke bawah persis karena itu.
     */
    public function test_sasaran_sentuh_tetap_lebar(): void
    {
        $css = file_get_contents(resource_path('views/partials/eq-visual.blade.php'));

        foreach (['.eq-tautan', '.eq-panel-lihat'] as $kelas) {
            $this->assertMatchesRegularExpression(
                '/' . preg_quote($kelas, '/') . '\{[^}]*padding-block:\s*\d/',
                $css,
                "{$kelas} kehilangan padding tegaknya — sasaran sentuhnya kembali di bawah 24px.",
            );
        }

        $donat = file_get_contents(resource_path('js/Grafik/Donat.vue'));

        $this->assertStringNotContainsString(
            'px-1 py-0.5',
            $donat,
            'Butir legenda donat kembali ke py-0.5; tingginya hanya 22px.',
        );
    }

    /** @return list<string> */
    private function berkasVue(): array
    {
        $out = [];

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('js'))
        );

        foreach ($it as $f) {
            if (!$f->isFile() || !str_ends_with($f->getFilename(), '.vue')) continue;
            if (str_contains($f->getPathname(), '/Print/')) continue;   // dibaca di kertas
            $out[] = $f->getPathname();
        }

        sort($out);

        return $out;
    }
}
