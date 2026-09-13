<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data acuan checklist prakualifikasi SMKP: 17 kategori, 126 pertanyaan.
 *
 * Diisi lewat migrasi, bukan penyemai. Ini bukan data contoh melainkan
 * daftar pertanyaan resmi yang menjadi dasar seluruh perhitungan skor —
 * tanpa isinya, halaman Persyaratan terbuka dengan formulir kosong dan
 * skor 0% yang terlihat sah. Penyemai harus dipanggil terpisah dan yang
 * lupa memanggilnya tidak mendapat galat apa pun.
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
        $data = json_decode(
            file_get_contents(resource_path('data/pjp/checklist-smkp.json')),
            true,
        );

        $now = now();
        $idKategori = [];

        foreach ($data['categories'] as $kategori) {
            $idKategori[$kategori['kode']] = DB::table('smkp_checklist_categories')
                ->where('kode', $kategori['kode'])->value('id')
                ?? DB::table('smkp_checklist_categories')->insertGetId([
                    'kode'       => $kategori['kode'],
                    'nama'       => $kategori['nama'],
                    'bobot'      => $kategori['bobot'],
                    'urutan'     => $kategori['urutan'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
        }

        foreach ($data['items'] as $item) {
            $kategoriId = $idKategori[$item['kategori_kode']];

            /*
             * Dikenali dari (kategori, urutan), bukan dari teks
             * pertanyaannya. Sembilan pertanyaan memuat baris baru di
             * dalamnya, dan pencocokan teks pada kolom TEXT lintas
             * SQLite/MySQL/PostgreSQL berbeda perlakuannya terhadap
             * spasi — yang gagal cocok akan disisipkan lagi.
             */
            $ada = DB::table('smkp_checklist_items')
                ->where('smkp_checklist_category_id', $kategoriId)
                ->where('urutan', $item['urutan'])
                ->exists();

            if ($ada) continue;

            DB::table('smkp_checklist_items')->insert([
                'smkp_checklist_category_id' => $kategoriId,
                'grup_kode'  => $item['grup_kode'],
                'grup_nama'  => $item['grup_nama'],
                'nomor'      => $item['nomor'],
                'pertanyaan' => $item['pertanyaan'],
                'petunjuk'   => $item['petunjuk'],
                'bobot'      => $item['bobot'],
                'urutan'     => $item['urutan'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
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
