<?php

namespace Eqohsee\SmkpAudit\Tests;

use Eqohsee\SmkpAudit\Support\Kop;
use PHPUnit\Framework\TestCase;

/**
 * Penomoran dokumen terkendali.
 *
 * Nomor berkas audit tersusun dari prefiks perusahaan dan kode formulir —
 * CAM-OHSE-IV.067h. Perusahaan yang belum menetapkan prefiksnya tetap harus
 * menghasilkan nomor yang terbaca: berkas cetak tidak boleh gagal hanya
 * karena data induk belum lengkap.
 */
class KopTest extends TestCase
{
    public function test_prefiks_diturunkan_dari_inisial_nama_perusahaan(): void
    {
        $this->assertSame('CAM', Kop::prefiksDari('PT Cemerlang Asa Mandiri'));
        $this->assertSame('GBU', Kop::prefiksDari('PT Gunung Bara Utama'));
    }

    public function test_bentuk_badan_usaha_tidak_ikut_menjadi_huruf_prefiks(): void
    {
        // "PT", "CV", "Tbk", dan "Persero" ada pada hampir setiap nama; ikut
        // menghitungnya membuat seluruh perusahaan berprefiks sama.
        $this->assertSame('SM', Kop::prefiksDari('CV Sinar Mas'));
        $this->assertSame('BA', Kop::prefiksDari('PT Bukit Asam Tbk'));
    }

    public function test_prefiks_dipotong_empat_huruf(): void
    {
        $this->assertSame('SNMK', Kop::prefiksDari('PT Sumber Nikel Mineral Kalimantan Timur'));
    }

    public function test_nama_kosong_tetap_menghasilkan_prefiks(): void
    {
        $this->assertSame('EQ', Kop::prefiksDari(null));
        $this->assertSame('EQ', Kop::prefiksDari('PT'));
    }

    public function test_kode_formulir_mengikuti_dokumen_acuan(): void
    {
        $daftar = Kop::daftar();

        $this->assertSame('OHSE-IV.067h', $daftar['berita-acara']['kode']);
        $this->assertSame('OHSE-IV.059',  $daftar['rencana-audit']['kode']);
        $this->assertSame('OHSE-IV.067g', $daftar['daftar-hadir']['kode']);
        $this->assertSame('OHSE-IV.067',  $daftar['laporan-audit']['kode']);
    }
}
