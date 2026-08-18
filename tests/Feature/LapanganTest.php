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
     * Lantai sasaran sentuh masih terpasang, dan masih 44px.
     *
     * WCAG 2.2 AA (2.5.8) menuntut 24x24 sebagai batas terendah, dan batas
     * itu ditulis untuk jari telanjang di ruangan tenang. Yang dipilih di
     * sini 44px — angka yang dianjurkan pedoman antarmuka sentuh Apple dan
     * Google, dan yang sepadan dengan keadaan sebenarnya: jari bersarung
     * tangan, di atas alat yang bergetar.
     *
     * Ditegaskan pada 44, bukan pada 24. Menegaskan batas WCAG akan
     * membiarkan angkanya turun diam-diam ke 24 dan tetap hijau — padahal
     * turun dari 44 adalah keputusan desain yang pantas disengaja, bukan
     * pergeseran yang berlalu tanpa disadari.
     *
     * Harganya sudah diukur, bukan dikira-kira: seluruh 22 halaman daftar
     * bertambah tinggi 2,9% dibanding 28px, yang terberat 10%. Murah
     * karena sebagian besar kendali sudah cukup tinggi lewat padding-nya
     * sendiri; yang tumbuh hanya yang memang terlalu kecil.
     */
    public function test_lantai_sasaran_sentuh_masih_ada(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('pointer: coarse', $css,
            'Aturan sasaran sentuh hilang; tombol kembali sekecil teksnya di perangkat sentuh.');

        preg_match('/min-height:\s*(\d+)px/', $css, $m);

        $this->assertGreaterThanOrEqual(44, (int) ($m[1] ?? 0),
            'Lantai sasaran sentuh turun di bawah 44px. Batas WCAG memang 24px, '
            .'tetapi 44 dipilih dengan sengaja untuk pemakaian bersarung tangan — '
            .'turunkan hanya bila itu memang keputusannya, lalu perbarui uji ini.');
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
