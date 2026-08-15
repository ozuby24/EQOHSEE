<?php

namespace Tests\Unit;

use App\Support\NeracaLahan;
use App\Support\Reklamasi;
use PHPUnit\Framework\TestCase;

class NeracaLahanTest extends TestCase
{
    private function petak(float $luas, string $tahap, ?int $nganggur): array
    {
        return ['luas_ha' => $luas, 'tahap' => $tahap, 'menganggur_hari' => $nganggur];
    }

    /* ---------- tahapan berurutan ---------- */

    public function test_tahapan_lebih_jauh_mencakup_tahapan_sebelumnya(): void
    {
        // Petak yang sudah direvegetasi pasti sudah ditata dan
        // ditebari tanah pucuk lebih dulu.
        $this->assertTrue(Reklamasi::sudahMencapai('revegetasi', 'penataan'));
        $this->assertTrue(Reklamasi::sudahMencapai('revegetasi', 'topsoil'));
        $this->assertFalse(Reklamasi::sudahMencapai('penataan', 'revegetasi'));
    }

    public function test_melompat_maju_lebih_dari_satu_tahapan_ditolak(): void
    {
        // Tanah pucuk di atas lahan yang belum ditata tergerus pada
        // hujan pertama; melompatinya bukan percepatan.
        $this->assertTrue(Reklamasi::bolehKe('belum', 'penataan'));
        $this->assertFalse(Reklamasi::bolehKe('belum', 'revegetasi'));
        $this->assertFalse(Reklamasi::bolehKe('penataan', 'pemeliharaan'));
    }

    public function test_mundur_selalu_diizinkan(): void
    {
        // Revegetasi yang gagal memang harus dikembalikan; menolaknya
        // memaksa orang mencatat keberhasilan yang tidak terjadi.
        $this->assertTrue(Reklamasi::bolehKe('revegetasi', 'penataan'));
        $this->assertTrue(Reklamasi::bolehKe('pemeliharaan', 'belum'));
    }

    /* ---------- neraca ---------- */

    public function test_luas_selesai_tidak_menghitung_petak_yang_sama_berkali_kali(): void
    {
        // Jebakan utama modul ini: satu petak 10 ha yang sudah
        // direvegetasi pernah terhitung sebagai 10 ditata + 10 topsoil
        // + 10 revegetasi = 30 ha, melampaui luas bukaannya sendiri.
        $n = NeracaLahan::susun([$this->petak(10, 'revegetasi', 100)]);

        $this->assertSame(10.0, $n['terganggu']);
        $this->assertSame(10.0, $n['tunggakan']);
        $this->assertSame(0.0, $n['selesai']);
    }

    public function test_lahan_yang_masih_ditambang_bukan_tunggakan(): void
    {
        // menganggur_hari null = masih ditambang.
        $n = NeracaLahan::susun([
            $this->petak(50, 'belum', null),
            $this->petak(10, 'belum', 400),
        ]);

        $this->assertSame(60.0, $n['terganggu']);
        $this->assertSame(50.0, $n['aktif']);
        $this->assertSame(10.0, $n['tunggakan']);
    }

    public function test_persen_selesai_dihitung_terhadap_lahan_yang_sudah_jatuh_tempo(): void
    {
        // Tambang yang baru membuka pit besar tidak boleh langsung
        // tampak tertinggal jauh: bukaan aktif belum wajib direklamasi.
        $n = NeracaLahan::susun([
            $this->petak(90, 'belum', null),     // masih ditambang
            $this->petak(5,  'selesai', 500),
            $this->petak(5,  'belum', 500),
        ]);

        $this->assertSame(10.0, $n['wajibReklamasi']);
        $this->assertSame(50.0, $n['persenSelesai']);
    }

    public function test_lahan_menganggur_melewati_batas_dihitung_telat(): void
    {
        $n = NeracaLahan::susun([
            $this->petak(4, 'belum', 200),
            $this->petak(6, 'penataan', 500),
        ], tunggakanWajarHari: 365);

        $this->assertSame(6.0, $n['telat']);
        $this->assertSame(500, $n['umurTerlama']);
    }

    public function test_batas_tunggakan_dapat_disesuaikan(): void
    {
        // Aturan pelaksanaannya berganti dari waktu ke waktu; angkanya
        // tidak boleh terkunci di dalam kode.
        $petak = [$this->petak(6, 'belum', 200)];

        $this->assertSame(0.0, NeracaLahan::susun($petak, 365)['telat']);
        $this->assertSame(6.0, NeracaLahan::susun($petak, 90)['telat']);
    }

    public function test_petak_selesai_tidak_ikut_tunggakan_walau_lama_menganggur(): void
    {
        $n = NeracaLahan::susun([$this->petak(8, 'dilepas', 2000)]);

        $this->assertSame(8.0, $n['selesai']);
        $this->assertSame(0.0, $n['tunggakan']);
        $this->assertSame(0.0, $n['telat']);
    }

    public function test_menunggu_dan_berjalan_dipisahkan(): void
    {
        // Keduanya tunggakan, tetapi yang satu ditagih ke perencana dan
        // yang lain ke pelaksana.
        $n = NeracaLahan::susun([
            $this->petak(3, 'belum', 100),
            $this->petak(7, 'revegetasi', 100),
        ]);

        $this->assertSame(3.0, $n['menunggu']);
        $this->assertSame(7.0, $n['berjalan']);
        $this->assertSame(10.0, $n['tunggakan']);
    }

    /* ---------- nisbah ---------- */

    public function test_nisbah_di_bawah_satu_berarti_tunggakan_bertambah(): void
    {
        $this->assertSame(0.25, NeracaLahan::nisbah(50, 200));
        $this->assertSame(1.5, NeracaLahan::nisbah(30, 20));
    }

    public function test_nisbah_tanpa_bukaan_tidak_dipaksa_jadi_angka(): void
    {
        // Membaginya nol menghasilkan galat; memaksanya nol menyatakan
        // reklamasinya buruk padahal tidak ada yang dibuka.
        $this->assertNull(NeracaLahan::nisbah(10, 0));
    }

    /* ---------- proyeksi ---------- */

    public function test_tunggakan_tidak_akan_habis_bila_laju_bersihnya_nol(): void
    {
        $this->assertNull(NeracaLahan::tahunMenutupTunggakan(100, 0));
        $this->assertNull(NeracaLahan::tahunMenutupTunggakan(100, -5));
    }

    public function test_tanpa_tunggakan_hasilnya_nol_tahun(): void
    {
        $this->assertSame(0.0, NeracaLahan::tahunMenutupTunggakan(0, 10));
    }

    public function test_proyeksi_membagi_tunggakan_dengan_laju_bersih(): void
    {
        $this->assertSame(5.0, NeracaLahan::tahunMenutupTunggakan(100, 20));
    }

    /* ---------- jaminan ---------- */

    public function test_kekurangan_jaminan_dinyatakan_sebagai_selisih(): void
    {
        $j = NeracaLahan::jaminan(ditempatkan: 800_000_000, luasBelumHa: 50, biayaPerHa: 30_000_000);

        $this->assertSame(1_500_000_000.0, $j['butuh']);
        $this->assertSame(-700_000_000.0, $j['selisih']);
        $this->assertFalse($j['cukup']);
    }

    public function test_jaminan_yang_cukup_ditandai_cukup(): void
    {
        $j = NeracaLahan::jaminan(ditempatkan: 2_000_000_000, luasBelumHa: 50, biayaPerHa: 30_000_000);

        $this->assertTrue($j['cukup']);
        $this->assertGreaterThan(100, $j['persen']);
    }

    public function test_tanpa_lahan_tersisa_jaminan_apa_pun_dianggap_cukup(): void
    {
        $j = NeracaLahan::jaminan(ditempatkan: 0, luasBelumHa: 0, biayaPerHa: 30_000_000);

        $this->assertTrue($j['cukup']);
        $this->assertNull($j['persen']);
    }
}
