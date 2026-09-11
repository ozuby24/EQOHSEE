<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use App\Support\DataContoh;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Kolom bertipe DATE tidak boleh menyimpan jam.
 *
 * KEGAGALANNYA BERBEDA ANTARA SERVER DAN MESIN PENGUJI, dan itu yang
 * membuatnya layak dijaga tersendiri.
 *
 * Cast `date` Laravel memangkas jam saat DIBACA, tidak saat DITULIS.
 * Carbon berjam 22:15 yang disimpan ke kolom DATE tertulis apa adanya
 * sebagai "2026-10-11 22:15:09". MySQL memangkasnya sendiri di tingkat
 * kolom; SQLite menyimpannya utuh. Akibatnya baris yang sama menjawab
 * BERBEDA pada kueri rentang:
 *
 *     whereBetween('berlaku_sampai', [hari ini, hari ini + 30 hari])
 *
 * melewatkan dokumen yang habis tepat pada hari ke-30 — sebab 22:15
 * lewat dari tengah malam hari itu — di mesin penguji, sementara di
 * server ia ikut terjaring. Ujinya hijau, layarnya salah. Dan yang
 * hilang dari layar adalah dokumen yang justru paling mendesak
 * diperbarui.
 *
 * Terjadi sungguhan: seluruh kolom tanggal modul Miners sempat
 * menyimpan jam dinding WITA, dan daftar "sertifikat akan habis"
 * memulangkan nol sementara satu sertifikat habis tiga puluh hari lagi.
 *
 * Yang dipindai SELURUH tabel, bukan satu modul. Aturannya berlaku bagi
 * semuanya, dan modul berikutnya yang mengulanginya adalah modul yang
 * belum ditulis siapa pun.
 */
class KolomTanggalTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_tidak_ada_kolom_tanggal_yang_menyimpan_jam(): void
    {
        $c = Company::create(['name' => 'PT Contoh Tanggal', 'demo' => true]);
        User::factory()->create(['company_id' => $c->id]);

        DataContoh::muat($c->fresh(), User::factory()->create(['is_admin' => true]));

        $salah = [];

        foreach (Schema::getTables() as $t) {
            $tabel = $t['name'];

            foreach (Schema::getColumns($tabel) as $kolom) {
                if ($kolom['type_name'] !== 'date') continue;

                $n = DB::table($tabel)
                    ->whereNotNull($kolom['name'])
                    ->where($kolom['name'], 'like', '% %')
                    ->where($kolom['name'], 'not like', '% 00:00:00')
                    ->count();

                if ($n > 0) $salah[] = $tabel.'.'.$kolom['name'].' ('.$n.' baris)';
            }
        }

        sort($salah);

        $this->assertSame(
            [],
            $salah,
            "Kolom DATE berikut menyimpan jam:\n ".implode("\n ", $salah)
            ."\nMySQL memangkasnya, SQLite tidak — sehingga kueri rentang menjawab berbeda di server "
            ."dan di mesin penguji. Simpan tanggalnya dengan startOfDay().",
        );
    }
}
