<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\Pjp\DaftarPeriksaSmkp;
use Illuminate\Support\Facades\DB;

/**
 * Data acuan checklist prakualifikasi SMKP: 17 kategori, 126 pertanyaan.
 *
 * Ditanam lewat migrasi supaya pemasangan BARU langsung punya daftarnya.
 * Ini bukan data contoh melainkan daftar pertanyaan resmi yang menjadi
 * dasar seluruh perhitungan skor — tanpa isinya, halaman Persyaratan
 * terbuka dengan formulir kosong dan skor 0% yang terlihat sah.
 *
 * Migrasi saja TIDAK cukup, dan itu sebabnya `pjp:pasang` tetap ada dan
 * dipanggil tiap deploy. Migrasi berjalan sekali seumur hidup basis
 * data; sesudah tercatat, checklist-smkp.json yang direvisi tidak akan
 * pernah sampai ke produksi. Keduanya memanggil kelas yang sama.
 *
 * Idempoten: baris yang sudah ada tidak digandakan. Migrasi ini pernah
 * dijalankan pada basis data yang sudah membawa tabelnya (mis. hasil
 * impor SQL), dan penyisipan buta di sana melahirkan 34 kategori dan 252
 * pertanyaan — dengan skor yang tetap "masuk akal" karena bobotnya ikut
 * berlipat dua.
 *
 * Bobot kategori A–P berjumlah 178. LEGALITAS (4 item) berdiri di luar
 * jumlah itu: ia gerbang wajib lulus/tidak, bukan bagian dari skor.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * Isinya disusun App\Support\Pjp\DaftarPeriksaSmkp, bukan di
         * sini — kelas yang sama dipanggil `pjp:pasang` tiap deploy.
         *
         * Dua salinan logika penyemaian akan berselisih cepat atau
         * lambat, dan selisihnya tidak menimbulkan galat: yang satu
         * menanam bentuk lama, yang lain bentuk baru, dan basis data
         * mana yang mendapat yang mana bergantung pada kapan ia dibuat.
         */
        DaftarPeriksaSmkp::pasang();
    }

    public function down(): void
    {
        /*
         * delete(), bukan truncate(). MySQL dan MariaDB menolak
         * TRUNCATE pada tabel yang dirujuk kunci asing (galat 1701),
         * sehingga rollback berhenti di tengah jalan — terbukti pada
         * aplikasi asal.
         */
        DB::table('smkp_checklist_items')->delete();
        DB::table('smkp_checklist_categories')->delete();
    }
};
