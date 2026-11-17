<?php

namespace Tests\Feature;

use App\Support\Alur;
use App\Support\RelPengajuan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Rel pengajuan — satu tempat yang menjawab "giliran siapa, apa yang kurang".
 *
 * Yang diuji di sini keadaan tiap simpul dan isi daftar penahannya.
 * Keduanya menentukan apa yang dibaca orang saat memutuskan apakah
 * sebuah berkas masih perlu dikerjakan — dan kesalahan pada keduanya
 * tidak pernah menimbulkan galat, hanya berkas yang ditunggu selamanya
 * karena layarnya menyebut giliran yang salah.
 */
class RelPengajuanTest extends TestCase
{
    /** @return list<array<string,mixed>> */
    private function rantai(array $sudahParaf = []): array
    {
        return [
            ['kode' => 'atasan',     'label' => 'Atasan langsung',  'terang' => 'Membenarkan pekerjaan',
             'keadaan' => in_array('atasan', $sudahParaf, true) ? 'paraf' : 'menunggu'],
            ['kode' => 'departemen', 'label' => 'Kepala departemen', 'terang' => 'Mengetahui',
             'keadaan' => in_array('departemen', $sudahParaf, true) ? 'paraf' : 'menunggu'],
            ['kode' => 'ohse',       'label' => 'OHSE',              'terang' => 'Memutuskan',
             'keadaan' => in_array('ohse', $sudahParaf, true) ? 'paraf' : 'menunggu'],
        ];
    }

    private function simpul(array $hasil, string $kunci): array
    {
        foreach ($hasil['rel'] as $t) if ($t['kunci'] === $kunci) return $t;

        $this->fail("Simpul {$kunci} tidak ada pada rel.");
    }

    /* ═══════════ 1 · draf ═══════════ */

    #[Test]
    public function draf_menahan_pengajuan_selama_syaratnya_kurang(): void
    {
        $h = RelPengajuan::bangun(Alur::DRAF, $this->rantai(), ['Form SPDK belum ada.']);

        $this->assertTrue($this->simpul($h, 'draf')['kini']);
        $this->assertSame(['Form SPDK belum ada.'], $h['kurang']);

        $this->assertFalse($h['bolehAjukan'],
            'Tombol Ajukan muncul padahal syaratnya kurang — penolakannya baru '
            .'terlihat sesudah tombolnya ditekan.');
    }

    #[Test]
    public function draf_yang_lengkap_boleh_diajukan(): void
    {
        $h = RelPengajuan::bangun(Alur::DRAF, $this->rantai(), []);

        $this->assertTrue($h['bolehAjukan']);
        $this->assertSame([], $h['kurang']);
    }

    /* ═══════════ 2 · sudah diajukan ═══════════ */

    /**
     * Sesudah dikirim, yang menahan BUKAN LAGI berkasnya melainkan mejanya.
     *
     * Inilah cacat yang paling merugikan pada bentuk sebelumnya: kotak
     * "Belum dapat diajukan" hanya muncul selama kartunya masih dapat
     * diubah, sehingga pengajuan yang sudah dikirim tidak menyebut apa
     * pun tentang giliran siapa sekarang — dan yang menunggunya
     * menyimpulkan berkasnya hilang.
     */
    #[Test]
    public function pengajuan_terkirim_menyebut_giliran_siapa_sekarang(): void
    {
        $h = RelPengajuan::bangun(Alur::DIAJUKAN, $this->rantai(['atasan']), []);

        $this->assertStringContainsString('Kepala departemen', $h['tugas'],
            'Rel tidak menyebut meja yang sedang menahan.');

        $this->assertNotEmpty($h['kurang'],
            'Pengajuan terkirim tidak menyebut apa pun yang menahannya.');

        $gabung = implode(' ', $h['kurang']);
        $this->assertStringContainsString('Kepala departemen — giliran sekarang', $gabung);
        $this->assertStringContainsString('OHSE', $gabung);

        /* Yang sudah memaraf tidak ikut disebut sebagai penahan. */
        $this->assertStringNotContainsString('Atasan langsung', $gabung);
    }

    #[Test]
    public function simpul_yang_sudah_diparaf_terhitung_lewat(): void
    {
        $h = RelPengajuan::bangun(Alur::DIAJUKAN, $this->rantai(['atasan']), []);

        $this->assertTrue($this->simpul($h, 'atasan')['lewat']);
        $this->assertTrue($this->simpul($h, 'departemen')['kini']);
        $this->assertTrue($this->simpul($h, 'ohse')['nanti']);

        /* Hanya SATU simpul boleh menjadi "kini". Dua simpul menyala
           bersamaan membuat dua meja sama-sama mengira gilirannya. */
        $this->assertCount(1, array_filter($h['rel'], fn ($t) => $t['kini']));
    }

    #[Test]
    public function draf_tidak_pernah_menyalakan_simpul_paraf(): void
    {
        $h = RelPengajuan::bangun(Alur::DRAF, $this->rantai(), []);

        $this->assertFalse($this->simpul($h, 'atasan')['kini'],
            'Kartu yang masih draf menyalakan meja paraf — mejanya akan menunggu '
            .'berkas yang belum dikirim siapa pun.');
    }

    /* ═══════════ 3 · sudah diputus ═══════════ */

    /**
     * Sesudah disetujui, tidak satu pun simpul tetap "menunggu".
     *
     * Pengajuan yang sudah diputus tidak lagi menunggu siapa pun, dan
     * simpul yang tetap tergambar menunggu pada berkas yang sudah terbit
     * membuat orang mencari paraf yang tidak akan pernah datang.
     */
    #[Test]
    public function disetujui_tidak_menyisakan_simpul_menunggu(): void
    {
        /* Sengaja: OHSE menyetujui tanpa paraf departemen lebih dulu. */
        $h = RelPengajuan::bangun(Alur::DISETUJUI, $this->rantai(['atasan']), []);

        $this->assertTrue($this->simpul($h, 'terbit')['kini']);
        $this->assertSame([], $h['kurang']);
        $this->assertTrue($h['selesai']);

        foreach (['atasan', 'departemen', 'ohse'] as $k) {
            $this->assertTrue($this->simpul($h, $k)['lewat'],
                "Simpul {$k} masih menunggu padahal pengajuannya sudah disetujui.");
        }

        $this->assertFalse($h['bolehAjukan']);
    }

    #[Test]
    public function ditolak_menyebut_alasannya_bukan_daftar_syarat(): void
    {
        $h = RelPengajuan::bangun(
            Alur::DITOLAK, $this->rantai(['atasan']),
            ['Form SPDK belum ada.'], 'Golongan kendaraan tidak sesuai jabatannya.');

        $this->assertTrue($h['ditolak']);
        $this->assertSame(['Ditolak: Golongan kendaraan tidak sesuai jabatannya.'], $h['kurang'],
            'Yang ditampilkan pada pengajuan ditolak seharusnya alasannya, '
            .'bukan daftar syarat yang sudah tidak relevan.');

        $this->assertFalse($h['bolehAjukan']);
    }

    /** Penolakan tanpa alasan tercatat tetap disebut, bukan dibiarkan kosong. */
    #[Test]
    public function ditolak_tanpa_alasan_tetap_menyebutkan_sesuatu(): void
    {
        $h = RelPengajuan::bangun(Alur::DITOLAK, $this->rantai(), [], null);

        $this->assertNotEmpty($h['kurang']);
        $this->assertStringContainsString('tanpa alasan', $h['kurang'][0]);
    }

    /* ═══════════ 4 · bentuk relnya ═══════════ */

    #[Test]
    public function rel_selalu_diapit_draf_dan_terbit(): void
    {
        $h = RelPengajuan::bangun(Alur::DRAF, $this->rantai(), []);

        $kunci = array_column($h['rel'], 'kunci');

        $this->assertSame('draf', $kunci[0]);
        $this->assertSame('terbit', $kunci[count($kunci) - 1]);
        $this->assertSame(count($this->rantai()) + 2, count($kunci));
    }

    /** Tiap simpul tepat satu keadaan — lewat, kini, atau nanti. */
    #[Test]
    public function tiap_simpul_tepat_satu_keadaan(): void
    {
        foreach ([Alur::DRAF, Alur::DIAJUKAN, Alur::DISETUJUI, Alur::DITOLAK] as $status) {
            $h = RelPengajuan::bangun($status, $this->rantai(['atasan']), []);

            foreach ($h['rel'] as $t) {
                $this->assertSame(1, (int) $t['lewat'] + (int) $t['kini'] + (int) $t['nanti'],
                    "Simpul {$t['kunci']} pada status {$status} punya keadaan ganda atau kosong.");
            }
        }
    }

    /** Label tahap terakhir dapat disesuaikan modulnya. */
    #[Test]
    public function label_tahap_terakhir_mengikuti_modulnya(): void
    {
        $h = RelPengajuan::bangun(Alur::DRAF, $this->rantai(), [], null, 'Hasil masuk');

        $this->assertSame('Hasil masuk', $this->simpul($h, 'terbit')['label']);
    }
}
