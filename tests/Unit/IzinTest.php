<?php

namespace Tests\Unit;

use App\Support\Izin;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class IzinTest extends TestCase
{
    private Carbon $kini;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kini = Carbon::parse('2026-08-15 10:00:00');
    }

    /* ═══════════ masa berlaku ═══════════ */

    #[Test]
    public function izin_sedang_berlaku(): void
    {
        $k = Izin::keadaanWaktu(
            Carbon::parse('2026-08-15 08:00'), Carbon::parse('2026-08-15 17:00'), $this->kini
        );

        $this->assertSame('berlaku', $k['kelas']);
        $this->assertSame(420, $k['sisaMenit']);
    }

    #[Test]
    public function izin_yang_belum_mulai(): void
    {
        $k = Izin::keadaanWaktu(
            Carbon::parse('2026-08-15 13:00'), Carbon::parse('2026-08-15 17:00'), $this->kini
        );

        $this->assertSame('belum-mulai', $k['kelas']);
    }

    #[Test]
    public function izin_yang_sudah_lewat_waktu(): void
    {
        // Keadaan waktu sengaja dipisah dari status alur: sebuah izin
        // dapat berstatus disetujui dan sekaligus sudah lewat, dan
        // justru gabungan itu yang berbahaya.
        $k = Izin::keadaanWaktu(
            Carbon::parse('2026-08-14 08:00'), Carbon::parse('2026-08-14 17:00'), $this->kini
        );

        $this->assertSame('lewat', $k['kelas']);
        $this->assertLessThan(0, $k['sisaMenit']);
    }

    #[Test]
    public function waktu_yang_belum_lengkap_tidak_ditebak(): void
    {
        $this->assertSame('tak-diketahui', Izin::keadaanWaktu(null, null, $this->kini)['kelas']);
        $this->assertSame('tak-diketahui',
            Izin::keadaanWaktu(Carbon::parse('2026-08-15 08:00'), null, $this->kini)['kelas']);
    }

    /* ═══════════ tumpang tindih ═══════════ */

    #[Test]
    public function dua_rentang_yang_bersinggungan_terdeteksi(): void
    {
        $this->assertTrue(Izin::tumpangTindih(
            Carbon::parse('2026-08-15 08:00'), Carbon::parse('2026-08-15 12:00'),
            Carbon::parse('2026-08-15 11:00'), Carbon::parse('2026-08-15 15:00'),
        ));
    }

    #[Test]
    public function rentang_yang_bersentuhan_ujung_bukan_tumpang_tindih(): void
    {
        // Satu izin berakhir tepat saat yang lain mulai adalah
        // pergantian giliran, bukan dua pekerjaan berbarengan.
        $this->assertFalse(Izin::tumpangTindih(
            Carbon::parse('2026-08-15 08:00'), Carbon::parse('2026-08-15 12:00'),
            Carbon::parse('2026-08-15 12:00'), Carbon::parse('2026-08-15 16:00'),
        ));
    }

    #[Test]
    public function rentang_yang_terpisah_bukan_tumpang_tindih(): void
    {
        $this->assertFalse(Izin::tumpangTindih(
            Carbon::parse('2026-08-15 08:00'), Carbon::parse('2026-08-15 10:00'),
            Carbon::parse('2026-08-15 13:00'), Carbon::parse('2026-08-15 16:00'),
        ));
    }

    #[Test]
    public function rentang_yang_belum_lengkap_tidak_dianggap_bentrok(): void
    {
        $this->assertFalse(Izin::tumpangTindih(
            Carbon::parse('2026-08-15 08:00'), null,
            Carbon::parse('2026-08-15 09:00'), Carbon::parse('2026-08-15 16:00'),
        ));
    }

    /* ═══════════ kebentrokan jenis ═══════════ */

    #[Test]
    public function pekerjaan_panas_bentrok_dengan_ruang_terbatas(): void
    {
        $this->assertTrue(Izin::bentrok('panas', 'ruang-terbatas'));
        $this->assertTrue(Izin::bentrok('ruang-terbatas', 'panas'));
        $this->assertStringContainsString('atmosfer', Izin::alasanBentrok('panas', 'ruang-terbatas'));
    }

    #[Test]
    public function pengangkatan_bentrok_dengan_jenis_apa_pun(): void
    {
        foreach (['ketinggian', 'penggalian', 'listrik', 'radiografi', 'panas'] as $j) {
            $this->assertTrue(Izin::bentrok('pengangkatan', $j), $j);
            $this->assertTrue(Izin::bentrok($j, 'pengangkatan'), $j);
        }

        $this->assertStringContainsString('tergantung', Izin::alasanBentrok('pengangkatan', 'ketinggian'));
    }

    #[Test]
    public function jenis_yang_tidak_terdaftar_tidak_dianggap_bentrok(): void
    {
        // Penyaring yang menandai terlalu banyak akan dimatikan orang,
        // dan penyaring yang dimatikan tidak menjaga apa pun.
        $this->assertFalse(Izin::bentrok('ketinggian', 'penggalian'));
        $this->assertFalse(Izin::bentrok('listrik', 'radiografi'));
        $this->assertFalse(Izin::bentrok('panas', 'panas'));
    }

    /* ═══════════ uji gas ═══════════ */

    #[Test]
    public function jenis_yang_wajib_uji_gas(): void
    {
        $this->assertTrue(Izin::perluUjiGas('panas'));
        $this->assertTrue(Izin::perluUjiGas('ruang-terbatas'));
        $this->assertFalse(Izin::perluUjiGas('ketinggian'));
    }

    #[Test]
    public function uji_yang_baru_diambil_masih_segar(): void
    {
        $this->assertTrue(Izin::ujiSegar(Carbon::parse('2026-08-15 09:30'), $this->kini));
        $this->assertSame(30, Izin::usiaUji(Carbon::parse('2026-08-15 09:30'), $this->kini));
    }

    #[Test]
    public function uji_yang_melewati_batas_umur_tidak_segar(): void
    {
        // Kadar gas berubah dalam hitungan menit; angka tiga jam lalu
        // tidak menyatakan apa pun tentang keadaan sekarang.
        $this->assertFalse(Izin::ujiSegar(Carbon::parse('2026-08-15 07:00'), $this->kini));
    }

    #[Test]
    public function batas_umur_dapat_diperpendek_situs(): void
    {
        $uji = Carbon::parse('2026-08-15 09:00');   // 60 menit lalu

        $this->assertTrue(Izin::ujiSegar($uji, $this->kini));
        $this->assertFalse(Izin::ujiSegar($uji, $this->kini, 30));
    }

    #[Test]
    public function uji_bertanggal_masa_depan_bukan_uji_yang_segar(): void
    {
        // Menerimanya sebagai segar membuka izin atas dasar angka yang
        // belum pernah diambil.
        $this->assertFalse(Izin::ujiSegar(Carbon::parse('2026-08-15 11:00'), $this->kini));
    }

    #[Test]
    public function tanpa_uji_tidak_ada_yang_segar(): void
    {
        $this->assertFalse(Izin::ujiSegar(null, $this->kini));
        $this->assertNull(Izin::usiaUji(null, $this->kini));
    }

    /* ═══════════ bacaan gas ═══════════ */

    #[Test]
    public function bacaan_dalam_ambang_dinyatakan_lulus(): void
    {
        $h = Izin::periksaGas(
            ['o2' => 20.9, 'lel' => 0.0, 'co' => 2.0, 'h2s' => 0.0],
            Izin::ambangBawaan(),
        );

        $this->assertTrue($h['lulus']);
        $this->assertTrue($h['lengkap']);
        $this->assertSame(['aman', 'aman', 'aman', 'aman'], array_column($h['rinci'], 'keadaan'));
    }

    #[Test]
    public function oksigen_terlalu_rendah_gagal(): void
    {
        $h = Izin::periksaGas(
            ['o2' => 18.0, 'lel' => 0.0, 'co' => 0.0, 'h2s' => 0.0],
            Izin::ambangBawaan(),
        );

        $this->assertFalse($h['lulus']);
        $this->assertSame('di-luar-ambang', $h['rinci'][0]['keadaan']);
    }

    #[Test]
    public function oksigen_terlalu_tinggi_juga_gagal(): void
    {
        // Atmosfer kaya oksigen membuat bahan yang biasanya sukar
        // terbakar menyala dengan mudah — ambangnya dua sisi.
        $h = Izin::periksaGas(
            ['o2' => 24.5, 'lel' => 0.0, 'co' => 0.0, 'h2s' => 0.0],
            Izin::ambangBawaan(),
        );

        $this->assertFalse($h['lulus']);
    }

    #[Test]
    public function gas_mudah_terbakar_di_atas_ambang_gagal(): void
    {
        $h = Izin::periksaGas(
            ['o2' => 20.9, 'lel' => 12.0, 'co' => 0.0, 'h2s' => 0.0],
            Izin::ambangBawaan(),
        );

        $this->assertFalse($h['lulus']);
    }

    #[Test]
    public function parameter_yang_tidak_diukur_bukan_parameter_yang_lulus(): void
    {
        // Formulir yang menandai semuanya hijau padahal H2S tidak pernah
        // diukur memberi rasa aman yang berasal dari ketiadaan datanya.
        $h = Izin::periksaGas(
            ['o2' => 20.9, 'lel' => 0.0, 'co' => 1.0],
            Izin::ambangBawaan(),
        );

        $this->assertTrue($h['lulus']);
        $this->assertFalse($h['lengkap']);
        $this->assertSame('tidak-diukur', $h['rinci'][3]['keadaan']);
        $this->assertNull($h['rinci'][3]['nilai']);
    }

    #[Test]
    public function ambang_situs_menggantikan_ambang_bawaan(): void
    {
        $ambang = Izin::ambangBawaan();
        $ambang['lel']['maks'] = 5.0;    // situs lebih ketat

        $bacaan = ['o2' => 20.9, 'lel' => 8.0, 'co' => 0.0, 'h2s' => 0.0];

        $this->assertTrue(Izin::periksaGas($bacaan, Izin::ambangBawaan())['lulus']);
        $this->assertFalse(Izin::periksaGas($bacaan, $ambang)['lulus']);
    }

    #[Test]
    public function nilai_tepat_di_ambang_masih_diterima(): void
    {
        $h = Izin::periksaGas(
            ['o2' => 19.5, 'lel' => 10.0, 'co' => 25.0, 'h2s' => 10.0],
            Izin::ambangBawaan(),
        );

        $this->assertTrue($h['lulus']);
    }
}
