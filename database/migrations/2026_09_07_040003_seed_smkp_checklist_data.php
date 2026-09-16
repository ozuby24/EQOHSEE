<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Isi data referensi kategori & pertanyaan checklist prakualifikasi
     * SMKP. Ini data acuan tetap (bukan contoh/dummy), jadi diisi lewat
     * migrasi supaya otomatis ada setelah `php artisan migrate` tanpa
     * langkah `db:seed` tambahan.
     */
    public function up(): void
    {
        $data = json_decode(
            file_get_contents(database_path('seeders/data/smkp-checklist.json')),
            associative: true,
        );

        $now = now();
        $categoryIds = [];

        foreach ($data['categories'] as $category) {
            $categoryIds[$category['kode']] = DB::table('smkp_checklist_categories')->insertGetId([
                'kode' => $category['kode'],
                'nama' => $category['nama'],
                'bobot' => $category['bobot'],
                'urutan' => $category['urutan'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($data['items'] as $item) {
            DB::table('smkp_checklist_items')->insert([
                'smkp_checklist_category_id' => $categoryIds[$item['kategori_kode']],
                'grup_kode' => $item['grup_kode'],
                'grup_nama' => $item['grup_nama'],
                'nomor' => $item['nomor'],
                'pertanyaan' => $item['pertanyaan'],
                'petunjuk' => $item['petunjuk'],
                'bobot' => $item['bobot'],
                'urutan' => $item['urutan'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('smkp_checklist_items')->truncate();
        DB::table('smkp_checklist_categories')->truncate();
    }
};
