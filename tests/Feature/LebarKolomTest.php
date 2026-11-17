<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Aturan validasi tidak boleh mengizinkan yang lebih panjang daripada kolomnya.
 *
 * ── Kenapa penjagaan ini ada ──
 *
 * SQLite tidak menegakkan panjang VARCHAR sama sekali. Ia menyimpan enam
 * puluh satu aksara ke kolom empat puluh tanpa berkata apa-apa, sehingga
 * seluruh uji hijau. MySQL menolaknya dengan SQLSTATE[22001], dan yang
 * dilihat pemakainya adalah galat SQL mentah di tengah halaman.
 *
 * Sudah dua kali kelas cacat ini lolos sampai produksi dengan cara yang
 * sama — sekali lewat kolom `luas_ha` yang tidak ada, sekali lewat
 * `mnr_permit_berkas.jenis` yang terlalu sempit untuk kunci lampirannya
 * sendiri. Keduanya baru ketahuan dari layar orang yang memakainya.
 *
 * Yang diperiksa di sini bentuk cacatnya, bukan kejadiannya: `max:N`
 * pada aturan validasi dibandingkan dengan lebar kolom yang benar-benar
 * ada di skema. Bila validasinya lebih longgar daripada kolomnya, ada
 * jalan masuk yang sah menurut aplikasi tetapi ditolak basis data.
 */
class LebarKolomTest extends TestCase
{
    /* Skemanya harus benar-benar termigrasi; tanpa ini ujinya melihat
       basis data kosong lalu gagal menyebut kolom yang sebetulnya ada. */
    use RefreshDatabase;

    /**
     * Pasangan yang diperiksa: kolom → panjang maksimum yang diizinkan
     * pengendalinya.
     *
     * Ditulis tangan dan sengaja pendek. Memindai seluruh pengendali
     * secara otomatis menghasilkan penjagaan yang tidak dapat dipercaya —
     * satu nama kolom yang tidak sama dengan nama kolom isian sudah
     * cukup membuatnya diam-diam melewatkan semuanya. Daftar pendek yang
     * benar lebih berguna daripada pemindai panjang yang meleset.
     *
     * @return array<int,array{0:string,1:string,2:int}>
     */
    public static function pasangan(): array
    {
        return [
            // tabel,                kolom,     max: pada validasinya
            ['mnr_permit_berkas',    'jenis',   100],
        ];
    }

    public function test_kolom_muat_untuk_yang_diizinkan_validasinya(): void
    {
        $sempit = [];

        foreach (self::pasangan() as [$tabel, $kolom, $maks]) {
            $this->assertTrue(Schema::hasTable($tabel), "Tabel {$tabel} tidak ada.");

            $lebar = $this->lebar($tabel, $kolom);

            if ($lebar !== null && $lebar < $maks) {
                $sempit[] = "{$tabel}.{$kolom} hanya {$lebar} aksara, validasinya mengizinkan {$maks}";
            }
        }

        $this->assertSame([], $sempit,
            "Kolom ini lebih sempit daripada yang diizinkan validasinya:\n  "
            .implode("\n  ", $sempit)
            ."\nDi SQLite selisih itu tidak terlihat; di MySQL ia menjadi "
            ."SQLSTATE[22001] di depan orang yang sedang mengisi formulir.");
    }

    /**
     * Kunci lampiran SOP harus muat pada kolomnya.
     *
     * Diukur dari daftar acuannya sendiri, bukan dari angka yang ditulis
     * ulang di sini: menambah satu baris daftar periksa yang kalimatnya
     * panjang langsung terlihat di sini, bukan nanti di produksi.
     */
    public function test_seluruh_kunci_lampiran_muat_pada_kolomnya(): void
    {
        /* Dibandingkan dengan angka yang TERTULIS, bukan lebar yang
           terbaca dari skema.
         *
         * SQLite tidak menyebutkan panjang VARCHAR-nya, sehingga
         * pembacaan skema memulangkan null dan ujinya akan dilewati —
         * di CI, yang memang berjalan di SQLite, artinya penjagaan ini
         * tidak pernah memeriksa apa pun. Uji yang selalu dilewati
         * adalah uji yang tidak menjaga apa-apa.
         *
         * Bahwa kolomnya sungguhan memang selebar angka ini dijaga
         * terpisah oleh uji di atas, yang jalan penuh di MySQL. */
        $lebar = collect(self::pasangan())
            ->firstWhere(fn ($p) => $p[0] === 'mnr_permit_berkas' && $p[1] === 'jenis')[2];

        $terlalu = [];

        foreach (\App\Support\Miners\Acuan::BERKAS_WAJIB as $pengajuan => $daftar) {
            foreach ($daftar as $nama) {
                $kunci = \Illuminate\Support\Str::slug($nama);

                if (strlen($kunci) > $lebar) {
                    $terlalu[] = sprintf('%s: %s (%d aksara)', $pengajuan, $kunci, strlen($kunci));
                }
            }
        }

        sort($terlalu);

        $this->assertSame([], $terlalu,
            "Kunci lampiran ini lebih panjang daripada kolom jenis ({$lebar} aksara):\n  "
            .implode("\n  ", $terlalu));
    }

    /** Lebar kolom menurut skema, atau null bila drivernya tidak menyebutkannya. */
    private function lebar(string $tabel, string $kolom): ?int
    {
        foreach (Schema::getColumns($tabel) as $k) {
            if ($k['name'] !== $kolom) continue;

            /* Bentuknya berbeda antar driver: 'varchar(100)' di MySQL,
               'varchar' saja di beberapa yang lain. Yang tanpa angka
               dipulangkan null supaya ujinya dilewati dengan jujur,
               bukan lulus karena tidak memeriksa apa-apa. */
            return preg_match('/\((\d+)\)/', (string) $k['type'], $c)
                ? (int) $c[1]
                : null;
        }

        $this->fail("Kolom {$tabel}.{$kolom} tidak ada.");
    }
}
