<?php

namespace Eqohsee\SmkpAudit\Tests;

use Eqohsee\SmkpAudit\Support\Smkp;
use Eqohsee\SmkpAudit\Support\SmkpTahap;
use PHPUnit\Framework\TestCase;

/**
 * Dua tahap audit SMKP dan Rencana Audit.
 *
 * Acuan: Lampiran II Kepdirjen 185.K/37.04/DJB/2019, dibaca lewat berkas audit
 * PT Gunung Bara Utama 2023 — angka pembanding pada uji mandays diambil dari
 * Berita Acara Tahap I dokumen tersebut.
 *
 * Seperti SmkpTest, uji ini tidak membangkitkan aplikasi Laravel. Yang diuji
 * adalah daftar acuan dan hitungannya; alur HTTP-nya diuji di aplikasi yang
 * memasang modul ini, sebab di situlah rute, basis data, dan pengguna berada.
 */
class SmkpTahapTest extends TestCase
{
    /** Rencana Audit yang memenuhi kesembilan komponen. */
    private function rencanaLengkap(): array
    {
        return [
            'tujuan'          => 'Menilai penerapan SMKP Minerba.',
            'kriteria'        => 'Kepdirjen 185.K/37.04/DJB/2019.',
            'ruang_lingkup'   => 'Seluruh wilayah IUP.',
            'tanggal_mulai'   => '2026-03-02',
            'tanggal_selesai' => '2026-03-06',
            'susunan'         => [['tanggal' => '2026-03-02', 'kegiatan' => 'Rapat pembukaan']],
            'tugas'           => [['nama' => 'Lucky', 'peran' => 'Ketua Tim Auditor']],
            'sumberdaya'      => 'Kendaraan, APD, ruang rapat.',
            'metode'          => 'Wawancara, tinjauan dokumen, observasi lapangan.',
            'pengesahan'      => ['ktt' => ['nama' => 'Budi'], 'ketua' => ['nama' => 'Lucky']],
        ];
    }

    /* ---------- Rencana Audit ---------- */

    public function test_rencana_audit_menuntut_sembilan_komponen(): void
    {
        // Kepdirjen menyebut sembilan butir; kalau daftarnya menyusut,
        // laporan yang dihasilkan berhenti sah.
        $this->assertCount(9, SmkpTahap::komponenRencana());
    }

    public function test_rencana_kosong_belum_lengkap_dan_menyebut_yang_kurang(): void
    {
        $r = SmkpTahap::rekapRencana([]);

        $this->assertFalse($r['lengkap']);
        $this->assertSame(0, $r['jumlah']);
        $this->assertCount(9, $r['kurang'], 'Seluruh komponen harus disebut sebagai kekurangan.');
    }

    public function test_rencana_terisi_penuh_dinyatakan_lengkap(): void
    {
        $r = SmkpTahap::rekapRencana($this->rencanaLengkap());

        $this->assertTrue($r['lengkap']);
        $this->assertSame(9, $r['jumlah']);
        $this->assertSame([], $r['kurang']);
    }

    public function test_tanggal_setengah_terisi_belum_menghitung_sebagai_komponen(): void
    {
        $d = $this->rencanaLengkap();
        unset($d['tanggal_selesai']);

        $this->assertFalse(SmkpTahap::rekapRencana($d)['terisi']['tanggal']);
    }

    public function test_baris_tabel_kosong_tidak_dianggap_isi(): void
    {
        // Formulir selalu mengirim satu baris kosong; itu tidak boleh membuat
        // susunan kegiatan tampak sudah ditetapkan.
        $d = $this->rencanaLengkap();
        $d['susunan'] = [['tanggal' => '', 'waktu' => '', 'kegiatan' => '', 'auditi' => '', 'auditor' => '']];

        $this->assertFalse(SmkpTahap::rekapRencana($d)['terisi']['susunan']);
    }

    public function test_pengesahan_pjo_tidak_wajib_bagi_pemegang_iup(): void
    {
        // PJO hanya diperlukan bila auditi perusahaan jasa pertambangan.
        $d = $this->rencanaLengkap();
        $this->assertTrue(SmkpTahap::rekapRencana($d)['terisi']['pengesahan']);

        unset($d['pengesahan']['ktt']);
        $this->assertFalse(SmkpTahap::rekapRencana($d)['terisi']['pengesahan'], 'KTT tetap wajib.');
    }

    /* ---------- kecukupan dokumentasi ---------- */

    public function test_kecukupan_yang_belum_ditinjau_tidak_dihitung_lengkap(): void
    {
        $r = SmkpTahap::rekapKecukupan(['I' => ['status' => SmkpTahap::LENGKAP]]);

        $this->assertSame(1, $r['lengkap']);
        $this->assertSame(6, $r['belum']);
        $this->assertFalse($r['siap']);
    }

    public function test_kecukupan_penuh_membuka_tahap_dua(): void
    {
        $k = [];
        foreach (Smkp::elemen() as $e) $k[$e['kode']] = ['status' => SmkpTahap::LENGKAP, 'ket' => ''];

        $r = SmkpTahap::rekapKecukupan($k);
        $this->assertSame(7, $r['lengkap']);
        $this->assertTrue($r['siap']);
    }

    /* ---------- hari kerja audit ---------- */

    public function test_mandays_mengikuti_berita_acara_pt_gbu_2023(): void
    {
        // Berita Acara Tahap I PT GBU 2023: 16 hari / 2 auditor = 8 hari,
        // penyesuaian 0 hari, total 8 hari, Tahap I 0,8 dan Tahap II 7,2.
        $m = SmkpTahap::mandays(['mandays_dasar' => 16, 'jumlah_auditor' => 2, 'penyesuaian' => 0]);

        $this->assertSame(8.0, $m['per_auditor']);
        $this->assertSame(8.0, $m['total']);
        $this->assertSame(0.8, $m['tahap1']);
        $this->assertSame(7.2, $m['tahap2']);
    }

    public function test_penyesuaian_diusulkan_dari_kondisi_yang_terpenuhi(): void
    {
        $m = SmkpTahap::mandays([
            'mandays_dasar'  => 10,
            'jumlah_auditor' => 2,
            'faktor'         => ['jarak' => true, 'pengolahan' => true],
        ]);

        $this->assertSame(2, $m['usul_penyesuaian']);
        $this->assertSame(7.0, $m['total'], 'Usulan dipakai bila auditor tidak menetapkan angka sendiri.');
    }

    public function test_penetapan_auditor_mengalahkan_usulan(): void
    {
        $m = SmkpTahap::mandays([
            'mandays_dasar'  => 10,
            'jumlah_auditor' => 2,
            'faktor'         => ['jarak' => true, 'pengolahan' => true],
            'penyesuaian'    => 1,
        ]);

        $this->assertSame(1.0, $m['penyesuaian']);
        $this->assertSame(6.0, $m['total']);
    }

    public function test_pembagi_auditor_tidak_pernah_nol(): void
    {
        $m = SmkpTahap::mandays(['mandays_dasar' => 8, 'jumlah_auditor' => 0]);
        $this->assertSame(8.0, $m['per_auditor']);
    }

    /* ---------- alur kerja ---------- */

    public function test_alur_membagi_audit_menjadi_empat_babak(): void
    {
        $alur = SmkpTahap::alur();

        $this->assertSame(['permulaan','rencana','lapangan','pelaporan'], array_keys($alur));
        foreach ($alur as $kunci => $babak) {
            $this->assertNotEmpty($babak['langkah'], "Babak {$kunci} tanpa langkah.");
            $this->assertNotEmpty($babak['ket'],     "Babak {$kunci} tanpa penjelasan.");
        }
    }

    public function test_tiap_langkah_menjelaskan_dirinya_dan_menyebut_rutenya(): void
    {
        // Menu yang hanya berisi judul tidak memberi tahu auditor apa yang
        // harus dikerjakan; keterangan tiap langkah karena itu wajib ada.
        //
        // Keberadaan rutenya sendiri diuji di aplikasi yang memasangnya —
        // Route::has() menuntut aplikasi yang sudah bangkit. Yang dijaga di
        // sini adalah sesuatu yang tetap dapat salah tanpa aplikasi: langkah
        // yang lupa menyebut ke mana ia menuju.
        foreach (SmkpTahap::alur() as $babak) {
            foreach ($babak['langkah'] as $l) {
                $this->assertNotEmpty($l['ket'],  "Langkah {$l['kunci']} tanpa keterangan.");
                $this->assertNotEmpty($l['rute'], "Langkah {$l['kunci']} tanpa rute.");
                $this->assertContains($l['jenis'], ['kerja','cetak']);
            }
        }
    }

    public function test_tahap_dua_menuntut_kecukupan_dan_rencana_sekaligus(): void
    {
        // Inilah yang membedakan dua tahap dari sekadar dua menu: rencana
        // lengkap saja tidak cukup selama dokumentasinya belum ditinjau.
        $rencana = SmkpTahap::rekapRencana($this->rencanaLengkap());
        $kurang  = SmkpTahap::rekapKecukupan([]);

        $this->assertTrue($rencana['lengkap']);
        $this->assertFalse($kurang['siap']);
    }

    public function test_label_kecukupan_menyebut_yang_belum_ditinjau(): void
    {
        $this->assertSame('Lengkap',        SmkpTahap::labelKecukupan(SmkpTahap::LENGKAP));
        $this->assertSame('Tidak Lengkap',  SmkpTahap::labelKecukupan(SmkpTahap::TIDAK_LENGKAP));
        $this->assertSame('Belum ditinjau', SmkpTahap::labelKecukupan(null));
    }

    public function test_rapat_hanya_pembukaan_dan_penutupan(): void
    {
        $this->assertSame(['pembukaan','penutupan'], array_keys(SmkpTahap::rapat()));
        $this->assertSame('Rapat Pembukaan', SmkpTahap::labelRapat('pembukaan'));
    }
}
