<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menyeragamkan nama kartu: Mine Permit dan Mine License.
 *
 * Sebelumnya jenisnya empat — "ID Card", "SIMPER", "Mine Permit", dan
 * "Visitor" — dan dua di antaranya menyebut hal yang sama dengan dua
 * nama. Itulah sebab utama alurnya membingungkan: tidak ada yang dapat
 * mengatakan apakah "ID Card" mendahului "Mine Permit" atau justru
 * menggantikannya, dan karena keduanya sama-sama boleh dibuat kapan
 * saja, keduanya memang dibuat.
 *
 * Yang sesungguhnya ada hanya dua izin, dan keduanya bertingkat:
 *
 *   Mine Permit    izin MASUK area tambang. Terbit sesudah MCU dan
 *                  induksi, diverifikasi OHSE, lalu dicetak.
 *
 *   Mine License   izin MENGEMUDI di area tambang. Tambahan di atas
 *                  Mine Permit, hanya bagi yang mengemudi, dan menuntut
 *                  SIM kepolisian serta sertifikat mengemudi defensif.
 *
 * "Visitor" tetap ada — ia bukan tingkat lain melainkan jalur lain:
 * tamu tidak bekerja, jadi tidak menuntut MCU, tetapi tetap menuntut
 * induksi.
 *
 * PEMETAAN DATA LAMA. "ID Card" menjadi Mine Permit dan "SIMPER"
 * menjadi Mine License. Keduanya penggantian NAMA, bukan perubahan
 * arti: yang tercatat sebagai ID Card memang kartu masuk area, dan
 * SIMPER memang izin mengemudi. Pembalikannya memulangkan nama
 * semula persis.
 */
return new class extends Migration
{
    private const PETA = [
        'ID Card' => 'Mine Permit',
        'SIMPER'  => 'Mine License',
    ];

    public function up(): void
    {
        foreach (self::PETA as $lama => $baru) {
            DB::table('paspor_kartu')->where('jenis', $lama)->update(['jenis' => $baru]);
        }
    }

    public function down(): void
    {
        /* Dibalik hanya bila memang belum ada baris ber-nama baru yang
           lahir SESUDAH pemutakhiran — dan itu tidak dapat dibedakan.
           Pembalikan karena itu mengembalikan seluruhnya, dan itu
           memang yang dimaksud: nama lama untuk arti yang sama. */
        foreach (self::PETA as $lama => $baru) {
            DB::table('paspor_kartu')->where('jenis', $baru)->update(['jenis' => $lama]);
        }
    }
};
