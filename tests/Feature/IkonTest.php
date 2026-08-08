<?php

namespace Tests\Feature;

use App\Support\Ikon;
use Tests\TestCase;

class IkonTest extends TestCase
{
    public function test_label_dikenal_mendapat_ikon_khusus(): void
    {
        $this->assertNotSame(Ikon::BAWAAN, Ikon::untuk('Pengguna'));
        $this->assertNotSame(Ikon::BAWAAN, Ikon::untuk('Kursus'));
        $this->assertNotSame(Ikon::BAWAAN, Ikon::untuk('Sertifikat'));
    }

    public function test_pencocokan_tidak_peduli_besar_kecil_huruf(): void
    {
        $this->assertSame(Ikon::untuk('Pengguna'), Ikon::untuk('PENGGUNA'));
        $this->assertSame(Ikon::untuk('Pengguna'), Ikon::untuk('  pengguna  '));
    }

    public function test_pencocokan_sebagian_menangani_varian_label(): void
    {
        // Satu kunci "percobaan" melayani kedua label ini.
        $this->assertSame(Ikon::untuk('Percobaan kuis'), Ikon::untuk('Percobaan SOP'));
        $this->assertNotSame(Ikon::BAWAAN, Ikon::untuk('Percobaan kuis'));
    }

    public function test_label_tak_dikenal_jatuh_ke_ikon_bawaan(): void
    {
        $this->assertSame(Ikon::BAWAAN, Ikon::untuk('Sesuatu Yang Belum Ada'));
    }

    /** Setiap label statistik yang benar-benar dipakai harus punya ikon sendiri. */
    public function test_seluruh_label_statistik_nyata_punya_ikon(): void
    {
        $label = [
            'Pengguna','Perusahaan','Log aktivitas',
            'Kursus','Modul','Materi','Kuis','Pendaftaran','Percobaan kuis',
            'Sertifikat','Penanda tangan','Evaluasi trainer','Prosedur',
            'Evaluasi SOP','Percobaan SOP','Berita',
            'Penilaian','Responden kuesioner','Sudah dinilai',
        ];

        $tanpaIkon = array_values(array_filter($label, fn($l) => Ikon::untuk($l) === Ikon::BAWAAN));

        $this->assertSame([], $tanpaIkon,
            'Label berikut masih memakai ikon bawaan: ' . implode(', ', $tanpaIkon));
    }

    public function test_gambar_ikon_berupa_jalur_svg_yang_wajar(): void
    {
        foreach (['Pengguna','Kursus','Penilaian','Audit'] as $l) {
            $d = Ikon::untuk($l);
            $this->assertMatchesRegularExpression('/^[Mm]\s?-?\d/', $d,
                "Ikon untuk {$l} tidak diawali perintah moveto.");
        }
    }
}
