<?php

use App\Support\Smkp;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menyeragamkan kolom jenis temuan SMKP menjadi kodenya.
 *
 * Aplikasi menulis 'mayor' dan 'minor'; sebagian baris lama menyimpan
 * "Ketidaksesuaian Mayor". Keduanya sah dibaca manusia dan tersimpan
 * tanpa galat — tetapi setiap hitungan yang memakai
 * `where('jenis','mayor')` menghasilkan nol atas tabel yang berisi
 * belasan temuan, dan seluruhnya jatuh ke keranjang "observasi".
 *
 * Terukur sebelum migrasi ini pada satu audit contoh: satu temuan mayor
 * dan satu minor terbaca "0 mayor · 0 minor · 2 observasi".
 *
 * Model SmkpFinding kini menyeragamkan pada penulisan; migrasi ini
 * membereskan yang sudah terlanjur tersimpan.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('smkp_findings')->select('id', 'jenis')->cursor() as $b) {
            $kode = Smkp::kodeJenis($b->jenis);

            if ($kode === (string) $b->jenis) continue;

            DB::table('smkp_findings')->where('id', $b->id)->update(['jenis' => $kode]);
        }
    }

    /**
     * Tidak dapat dibalik, dan tidak perlu.
     *
     * Label yang digantikan tidak membawa keterangan apa pun yang tidak
     * ada pada kodenya — memulangkannya hanya memasang kembali bug yang
     * dibereskan migrasi ini.
     */
    public function down(): void
    {
        //
    }
};
