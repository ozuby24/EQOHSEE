<?php

namespace Tests\Unit;

use App\Support\Ekspor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Nomor Indonesia menjadi bentuk yang dimengerti wa.me.
 *
 * ── KENAPA INI PUNYA TES SENDIRI ──
 *
 * Kegagalannya tidak terlihat. wa.me/081214407991 tetap tertaut, tetap
 * membuka WhatsApp, dan tetap tidak menimbulkan satu pun galat di konsol
 * maupun di log — ia hanya menampilkan "nomor tidak valid" kepada orang
 * yang baru saja memutuskan untuk bertanya, pada tombol paling menonjol
 * di halaman depan. Tidak ada yang melaporkannya; yang terjadi hanya
 * tombol yang berhenti menghasilkan percakapan.
 *
 * Aturannya kini dipakai bersama oleh Company::waNumber(), halaman
 * depan, dan etalase. Satu aturan berarti satu tempat yang harus benar
 * — dan tempat itu diuji di sini.
 */
class NomorWaTest extends TestCase
{
    #[Test]
    public function nol_di_depan_menjadi_kode_negara(): void
    {
        // Bentuk yang paling sering diberikan orang saat diminta
        // nomornya, dan satu-satunya bentuk yang diam-diam rusak.
        $this->assertSame('6281234567890', Ekspor::nomorWa('081234567890'));
    }

    #[Test]
    public function tanda_baca_dan_spasi_dibuang(): void
    {
        foreach (['0812-3456-7890', '+62 812 3456 7890', '(0812) 3456 7890'] as $tulisan) {
            $this->assertSame('6281234567890', Ekspor::nomorWa($tulisan), $tulisan);
        }
    }

    #[Test]
    public function nomor_yang_sudah_benar_tidak_diubah(): void
    {
        // Menjalankannya dua kali harus memberi hasil yang sama: nilai
        // ini melewati normalisasi setiap kali halaman digambar.
        $sekali = Ekspor::nomorWa('6281234567890');
        $this->assertSame('6281234567890', $sekali);
        $this->assertSame($sekali, Ekspor::nomorWa($sekali));
    }

    #[Test]
    public function nomor_tanpa_awalan_apa_pun_diberi_kode_negara(): void
    {
        $this->assertSame('6281234567890', Ekspor::nomorWa('81234567890'));
    }

    #[Test]
    public function kosong_menjadi_null_bukan_string_kosong(): void
    {
        // Pembedaan yang menentukan: halaman depan dan etalase memakai
        // null untuk memutuskan TIDAK menggambar tombolnya sama sekali.
        // String kosong akan lolos sebagai nilai yang ada, dan yang
        // tergambar adalah tautan wa.me tanpa nomor — pemilih kontak
        // kosong yang terbaca sebagai aplikasi rusak.
        $this->assertNull(Ekspor::nomorWa(''));
        $this->assertNull(Ekspor::nomorWa(null));
        $this->assertNull(Ekspor::nomorWa('-'));
    }
}
