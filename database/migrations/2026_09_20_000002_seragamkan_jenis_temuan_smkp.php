<?php

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
            $kode = self::kodeJenis($b->jenis);

            if ($kode === (string) $b->jenis) continue;

            DB::table('smkp_findings')->where('id', $b->id)->update(['jenis' => $kode]);
        }
    }

    /**
     * Kode jenis dari isian lama: kode atau label Mayor/Minor, selain
     * itu 'obs'.
     *
     * Ditulis di sini, bukan dipanggil dari App\Support\Smkp: mesin
     * hitung SMKP sudah diganti (porting Aurorasys) dan tidak lagi
     * mengenal 'obs'. Migrasi yang sudah berjalan tidak boleh bergantung
     * pada kode yang berubah sesudahnya.
     */
    private static function kodeJenis(?string $jenis): string
    {
        $j = trim((string) $jenis);

        foreach (['mayor' => 'Ketidaksesuaian Mayor', 'minor' => 'Ketidaksesuaian Minor'] as $kode => $label) {
            if (strcasecmp($j, $kode) === 0 || strcasecmp($j, $label) === 0) return $kode;
        }

        return 'obs';
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
