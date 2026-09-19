<?php

namespace Tests\Feature;

use App\Support\{Dasbor, Menu};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ringkasan tiap modul pada dasbor.
 *
 * Bagian ini ada karena panel "hal yang menuntut tindakan" hanya
 * menampilkan yang nilainya bukan nol. Akibatnya dasbor diam sama sekali
 * tentang modul yang sedang bersih — dan modul yang diam tidak dapat
 * dibedakan dari modul yang memang tidak pernah ada di dasbor.
 *
 * Keduanya terlihat sama: tidak ada. Begitulah dua modul, LMS dan
 * Personalia, luput selama berbulan-bulan tanpa ada yang menyadarinya.
 */
class RingkasanModulTest extends TestCase
{
    /* Uji terakhir memanggil Dasbor::modul(), yang menyentuh seluruh
       tabel modul. Yang lain murni hitungan dan tidak butuh basis data. */
    use RefreshDatabase;

    /** @return list<array<string,mixed>> */
    private function ubin(): array
    {
        return [
            ['modul' => 'hris', 'nama' => 'Roster Terhalang', 'nilai' => 150,
             'total' => 300, 'rute' => 'hris.index', 'nada' => 'gawat'],
            ['modul' => 'hris', 'nama' => 'Absensi Tak Cocok', 'nilai' => 30,
             'total' => 300, 'rute' => 'hris.index', 'nada' => 'ingat'],
            ['modul' => 'energi', 'nama' => 'Energi', 'nilai' => 420,
             'total' => 420, 'rute' => 'energi.index', 'nada' => 'kabar'],
            ['modul' => 'ko', 'nama' => 'Keselamatan Operasi', 'nilai' => 0,
             'total' => 36, 'rute' => 'ko.index', 'nada' => 'gawat'],
        ];
    }

    private function cari(string $modul): array
    {
        return collect(Dasbor::ringkasanModul($this->ubin()))->firstWhere('modul', $modul) ?? [];
    }

    /**
     * Hitungan yang sekadar KABAR tidak dijumlahkan sebagai tunggakan.
     *
     * Nada 'kabar' menandai "Catatan tersimpan" — empat ratus dua puluh
     * catatan energi yang sehat. Dijumlahkan bersama yang lain, modul itu
     * tampil "420" tepat di sebelah HRIS "181", dan keduanya terbaca
     * sebagai hal yang sama. Padahal yang satu catatan yang baik-baik
     * saja, yang lain hari kerja yang terhalang.
     */
    public function test_hitungan_kabar_tidak_dihitung_sebagai_tunggakan(): void
    {
        $energi = $this->cari('energi');

        $this->assertSame(0, $energi['perlu'],
            'Catatan tersimpan ikut dijumlahkan sebagai tunggakan.');

        $this->assertSame(420, $energi['kabar'],
            'Angka kabarnya ikut hilang; yang salah hanya tempatnya, bukan angkanya.');

        $this->assertSame('kabar', $energi['nada']);
    }

    public function test_tunggakan_dijumlahkan_dari_seluruh_ubin_modulnya(): void
    {
        $hris = $this->cari('hris');

        $this->assertSame(180, $hris['perlu']);
        $this->assertCount(2, $hris['butir']);
    }

    /**
     * Nada modul dinaikkan hanya oleh ubin yang nilainya BUKAN nol.
     *
     * Ubin "gawat" berangka nol berarti tidak ada yang gawat. Menandai
     * modulnya merah karena ubin itu sekadar ADA akan mewarnai dasbor
     * merah sepanjang waktu — dan warna yang selalu merah berhenti
     * dibaca sebagai peringatan.
     */
    public function test_ubin_gawat_bernilai_nol_tidak_memerahkan_modulnya(): void
    {
        $ko = $this->cari('ko');

        $this->assertSame(0, $ko['perlu']);
        $this->assertSame('baik', $ko['nada'],
            'Modul tanpa tunggakan ditandai gawat hanya karena ubinnya bernada gawat.');
    }

    public function test_yang_paling_menuntut_berada_di_depan(): void
    {
        $urut = collect(Dasbor::ringkasanModul($this->ubin()))->pluck('modul')->all();

        $this->assertSame('hris', $urut[0],
            'Modul yang paling menuntut tidak berada di urutan pertama.');
    }

    /**
     * Butir yang menuntut tindakan dibaca lebih dulu daripada yang
     * sekadar kabar, seberapa pun kecil angkanya.
     */
    public function test_butir_yang_menuntut_tindakan_di_atas_yang_sekadar_kabar(): void
    {
        $ubin = $this->ubin();
        $ubin[] = ['modul' => 'energi', 'nama' => 'Ambang Terlampaui', 'nilai' => 2,
                   'total' => 420, 'rute' => 'energi.index', 'nada' => 'gawat'];

        $energi = collect(Dasbor::ringkasanModul($ubin))->firstWhere('modul', 'energi');

        $this->assertSame('Ambang Terlampaui', $energi['butir'][0]['nama'],
            'Empat ratus catatan sehat terbaca lebih dulu daripada dua ambang yang terlampaui.');
    }

    /**
     * SETIAP modul punya tempat di dasbor.
     *
     * Penjagaan yang paling penting di berkas ini: LMS dan Personalia
     * tidak punya satu ubin pun selama berbulan-bulan, dan tidak ada yang
     * menyadarinya karena ketiadaan tidak menimbulkan galat.
     */
    public function test_setiap_modul_punya_ubin_di_dasbor(): void
    {
        $pengguna = \App\Models\User::factory()->make(['is_admin' => true]);

        $punya = collect(Dasbor::modul($pengguna))->pluck('modul')->unique();

        /* Dasbor tidak meringkas dirinya sendiri, dan Admin bukan modul
           kerja melainkan pengaturan. */
        $wajib = collect(Menu::all())->keys()
            ->reject(fn ($k) => in_array($k, ['dasbor', 'admin'], true));

        $hilang = $wajib->diff($punya)->values()->all();

        $this->assertSame([], $hilang,
            'Modul ini tidak punya satu ubin pun di dasbor, sehingga ringkasan situs '
            .'diam sama sekali tentangnya: '.implode(', ', $hilang).'. Diam bukan berarti aman.');
    }
}
