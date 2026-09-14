<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Pengerasan nginx: pembatas laju, batas koneksi, dan timeout.
 *
 * Diuji sebagai TEKS, bukan dengan menjalankan nginx. Uji yang menuntut
 * nginx terpasang akan gagal di mesin pengembang dan di CI, lalu
 * ditandai lewati — dan bersamanya hilang penjagaan atas berkas yang
 * justru paling jarang dibaca ulang orang.
 *
 * Yang dijaga bukan angkanya melainkan KEBERADAANNYA. Angka boleh
 * disetel ulang sesuai beban; yang tidak boleh adalah pembatasnya lenyap
 * tanpa ada yang menyadarinya — dan lenyapnya tidak menimbulkan galat
 * apa pun, sebab situs tanpa pembatas laju berjalan normal sampai hari
 * seseorang membanjirinya.
 *
 * `nginx -t` di deploy.sh menangkap salah sintaks; ia tidak menangkap
 * aturan yang tidak ditulis sama sekali.
 */
class PengerasanNginxTest extends TestCase
{
    /** @return list<string> kedua vhost, keduanya dipakai bergantian. */
    private function vhost(): array
    {
        return [
            'nginx-eqohsee.conf'     => file_get_contents(base_path('deploy/nginx-eqohsee.conf')),
            'nginx-eqohsee-ssl.conf' => file_get_contents(base_path('deploy/nginx-eqohsee-ssl.conf')),
        ];
    }

    /**
     * Zonanya berdiri di berkas terpisah, dan itu bukan kerapian.
     *
     * `limit_req_zone` hanya sah di konteks http. Ditaruh di dalam blok
     * server pada vhost, nginx menolak SELURUH konfigurasinya — bukan
     * hanya aturan itu — dan situsnya tidak menyala sama sekali.
     */
    public function test_zona_pembatas_ada_di_konteks_http(): void
    {
        $zona = file_get_contents(base_path('deploy/nginx-eqohsee-limits.conf'));

        $this->assertStringContainsString('limit_req_zone', $zona);
        $this->assertStringContainsString('limit_conn_zone', $zona);

        foreach ($this->vhost() as $nama => $isi) {
            $this->assertStringNotContainsString('limit_req_zone', $isi,
                "{$nama} memuat limit_req_zone. Direktif itu hanya sah di konteks http; "
                ."di dalam blok server ia membuat nginx menolak seluruh konfigurasinya.");
        }
    }

    public function test_kedua_vhost_membatasi_laju_dan_koneksi(): void
    {
        foreach ($this->vhost() as $nama => $isi) {
            $this->assertStringContainsString('limit_req zone=eq_umum', $isi,
                "{$nama} tidak lagi membatasi laju permintaan.");

            $this->assertStringContainsString('limit_req zone=eq_masuk', $isi,
                "{$nama} tidak lagi membatasi halaman masuk secara khusus — penebak sandi "
                ."kembali ditolak oleh Laravel, yang berarti tiap tebakan tetap "
                ."menghabiskan satu proses PHP.");

            $this->assertStringContainsString('limit_conn eq_konek', $isi,
                "{$nama} tidak lagi membatasi koneksi serentak per alamat.");
        }
    }

    /**
     * Timeout menahan koneksi yang sengaja diperlambat.
     *
     * Slowloris mengirim tajuknya sebit demi sebit dan tidak pernah
     * menyelesaikan permintaannya. Permintaan yang tidak pernah utuh
     * tidak pernah dihitung pembatas laju, sementara tiap koneksi
     * menahan satu slot pekerja. Bawaan nginx 60 detik memberi
     * penyerangnya semenit per koneksi.
     */
    public function test_kedua_vhost_memasang_timeout_penahan_slowloris(): void
    {
        foreach ($this->vhost() as $nama => $isi) {
            foreach (['client_header_timeout', 'client_body_timeout', 'send_timeout'] as $aturan) {
                $this->assertStringContainsString($aturan, $isi, "{$nama} kehilangan {$aturan}.");
            }
        }
    }

    /**
     * Tantangan ACME harus tetap menang atas aturan mana pun.
     *
     * Ia dilayani lewat HTTP polos, termasuk saat perpanjangan otomatis.
     * Tertutup aturan lain, sertifikatnya gagal diperpanjang — dan
     * kegagalan itu baru terlihat sembilan puluh hari kemudian, ketika
     * sertifikatnya kedaluwarsa dan seluruh peramban menolak membuka
     * situsnya.
     */
    public function test_tantangan_acme_tetap_dilayani(): void
    {
        foreach ($this->vhost() as $nama => $isi) {
            $this->assertStringContainsString('location ^~ /.well-known/acme-challenge/', $isi,
                "{$nama} tidak lagi melayani tantangan ACME dengan prefiks ^~, "
                ."sehingga aturan regex di bawahnya dapat menutupinya.");
        }
    }

    /** Berkas unggahan tidak pernah dijalankan sebagai PHP. */
    public function test_berkas_unggahan_tidak_dapat_dijalankan(): void
    {
        foreach ($this->vhost() as $nama => $isi) {
            $this->assertStringContainsString('location ^~ /storage/', $isi,
                "{$nama}: /storage/ tidak lagi dilindungi prefiks ^~, sehingga blok "
                ."`location ~ \\.php\$` dapat menjalankan berkas yang diunggah orang.");
        }
    }
}
