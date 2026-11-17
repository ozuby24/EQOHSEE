<?php

namespace Tests\Feature;

use App\Models\Hr\AbsensiJejak;
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Waktu berzona tampilan tidak boleh langsung masuk ke kolom TIMESTAMP.
 *
 * Eloquent menyimpan ANGKA JAM PADA JAM DINDING, bukan saatnya:
 * `Model::fromDateTime()` memanggil `format()` atas Carbon yang
 * diberikan tanpa memindahkan zonanya lebih dahulu. Carbon berzona
 * WITA pukul 07.02 karena itu tersimpan sebagai untaian "07:02:00" pada
 * kolom yang dibaca sebagai UTC — dan dibaca kembali menjadi pukul
 * 15.02.
 *
 * KEGAGALANNYA SENYAP SEMPURNA. Tidak ada galat, barisnya tersimpan,
 * layarnya menggambar, dan seluruhnya meleset delapan jam: pindaian
 * pukul tujuh pagi tercatat terlambat 540 menit, tap pulang pukul empat
 * sore jatuh ke hari berikutnya sehingga orangnya tercatat "belum tap
 * pulang" selamanya, dan jam kerjanya nol.
 *
 * Terjadi dua kali. Pertama pada `bertindak_pada` alur Miners dan
 * `updated_at` penerbitan roster — keduanya hanya membuat jam yang
 * ditampilkan salah, jadi tidak ada yang menyadarinya. Kedua pada
 * seluruh modul absensi, yang justru menghitung sesuatu dari jam itu,
 * dan di situ barulah terlihat.
 *
 * BATAS UJI INI JUJUR: ia menangkap BENTUK yang paling sering ditulis —
 * `'kolom' => Waktu::kini()` di dalam larik atribut — bukan setiap cara
 * sebuah Carbon dapat sampai ke basis data. Sebuah variabel berzona
 * WITA yang dioper lewat beberapa lapis tetap lolos. Yang menjaga
 * bagian itu adalah uji fungsional yang membandingkan jam yang ditulis
 * dengan jam yang terbaca; lihat AbsensiTest.
 */
class ZonaSimpanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Kolom DATE justru menyimpan tanggal SETEMPAT.
     *
     * Di sana yang benar adalah jam dinding WITA tengah malam, dan
     * itulah yang dihasilkan Waktu::tanggal(). Perbedaannya bukan
     * kelalaian melainkan arti kolomnya — lihat KolomTanggalTest untuk
     * sisi yang satunya.
     */
    private const DIKECUALIKAN = ['tanggal', 'tanggal_bayar', 'mulai', 'selesai'];

    /** @return list<string> */
    private function berkas(): array
    {
        $out = [];

        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path(), RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iter as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') $out[] = $f->getPathname();
        }

        sort($out);

        return $out;
    }

    #[Test]
    public function test_tidak_ada_waktu_kini_yang_langsung_masuk_ke_atribut(): void
    {
        $temuan = [];

        foreach ($this->berkas() as $path) {
            foreach (file($path) as $no => $baris) {
                /* Hanya bentuk telanjang: `'kolom' => Waktu::kini(),`.
                   Yang berantai — `Waktu::kini()->toDateString()` —
                   sengaja dilewati, sebab ia menghasilkan untaian, bukan
                   Carbon, dan untaian tidak terkena cacat ini. */
                if (! preg_match("/'(\\w+)'\\s*=>\\s*Waktu::kini\\(\\)\\s*[,\\]\\)]/", $baris, $cocok)) {
                    continue;
                }

                if (in_array($cocok[1], self::DIKECUALIKAN, true)) continue;

                $temuan[] = str_replace(base_path().'/', '', $path).':'.($no + 1)
                    .' — '.trim($baris);
            }
        }

        $this->assertSame([], $temuan, implode("\n", array_merge(
            ['Waktu berzona tampilan masuk langsung ke atribut model.'],
            ['Pakai Waktu::kiniSimpan() untuk kolom TIMESTAMP,'],
            ['atau Waktu::tanggal() bila kolomnya bertipe DATE:'],
            $temuan,
        )));
    }

    #[Test]
    public function test_waktu_simpan_memindahkan_zonanya(): void
    {
        $wita = \Illuminate\Support\Carbon::parse('2026-09-12 07:02:00', Waktu::zona());

        $this->assertSame('2026-09-11 23:02:00', Waktu::simpan($wita)->toDateTimeString());
        $this->assertNull(Waktu::simpan(null));

        /* kiniSimpan() dan simpan(kini()) harus menjawab saat yang sama;
           dua jalan yang berbeda jawabannya adalah cacat ini sendiri,
           hanya berpindah tempat. */
        $this->assertLessThan(2, abs(Waktu::kiniSimpan()->diffInSeconds(Waktu::simpan(Waktu::kini()))));
    }

    #[Test]
    public function test_yang_ditulis_terbaca_kembali_pada_jam_yang_sama(): void
    {
        /* Bulat-bulat lewat basis data, sebab di situlah cacatnya
           muncul — bukan di dalam Carbon. */
        $wita = \Illuminate\Support\Carbon::parse('2026-09-12 07:02:00', Waktu::zona());

        $p = \App\Models\Miners\Pekerja::withoutGlobalScopes()->create([
            'nama' => 'Uji Zona', 'status' => 'aktif',
        ]);

        $j = AbsensiJejak::withoutGlobalScopes()->create([
            'pekerja_id' => $p->id,
            'terjadi'    => Waktu::simpan($wita),
            'arah'       => 'masuk',
            'kunci'      => 'zona-'.uniqid(),
        ]);

        $this->assertSame('07:02', Waktu::lokal($j->fresh()->terjadi)->format('H:i'));
    }
}
