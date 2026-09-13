<?php

namespace App\Support\Pjp;

use Illuminate\Support\Facades\DB;

/**
 * Daftar periksa prakualifikasi SMKP: 17 kategori, 126 pertanyaan.
 *
 * Isinya lampiran Kepdirjen Minerba 185/2019 — daftar pertanyaan resmi
 * yang menjadi dasar seluruh perhitungan skor. Tanpa isinya, halaman
 * Persyaratan terbuka dengan formulir kosong dan skor 0% yang terlihat
 * sah bagi setiap mitra.
 *
 * Dipanggil dari DUA tempat, dan itu sebabnya ia berdiri sendiri di
 * sini alih-alih ditulis di salah satunya:
 *
 *   migrasi      — supaya pemasangan baru langsung punya daftarnya;
 *   `pjp:pasang` — supaya daftar yang DIREVISI sampai ke server.
 *
 * Yang kedua tidak dapat digantikan yang pertama. Migrasi berjalan
 * sekali seumur hidup basis data; begitu ia tercatat, perubahan pada
 * checklist-smkp.json tidak akan pernah sampai ke produksi, dan
 * servernya berjalan dengan daftar pertanyaan versi lama tanpa satu pun
 * tanda. Perintahnya dipanggil tiap deploy — lihat deploy/deploy.sh,
 * yang memperlakukan seluruh master data dengan cara yang sama.
 *
 * ATURAN YANG TIDAK BOLEH DILANGGAR: memperbarui, tidak pernah
 * menghapus.
 *
 * `smkp_checklist_answers` menunjuk butirnya lewat kunci asing yang
 * cascade on delete. Penyemai yang mengosongkan tabelnya lebih dahulu —
 * pola yang lazim dan terlihat rapi — akan menghapus seluruh jawaban
 * daftar periksa setiap mitra, pekerjaan berbulan-bulan, tanpa satu
 * galat pun. Yang tersisa hanyalah daftar periksa yang kembali kosong
 * dan skor yang kembali nol.
 */
final class DaftarPeriksaSmkp
{
    /** Berkas acuannya, satu-satunya sumber isi daftar ini. */
    public const BERKAS = 'data/pjp/checklist-smkp.json';

    /**
     * Menanam atau memperbarui seluruh daftar.
     *
     * @return array{kategori:int, butir:int, baru:int, diperbarui:int}
     */
    public static function pasang(): array
    {
        $data = json_decode(file_get_contents(resource_path(self::BERKAS)), true);

        $now  = now();
        $baru = 0;
        $ubah = 0;

        $idKategori = [];

        foreach ($data['categories'] as $kategori) {
            /* Dikenali dari `kode` — penanda yang dipakai regulasinya
               sendiri (LEGALITAS, A…P) dan tidak pernah berubah. Nama,
               bobot, dan urutannya boleh direvisi, dan revisi itulah
               yang perlu sampai. */
            $id = DB::table('smkp_checklist_categories')
                ->where('kode', $kategori['kode'])->value('id');

            if ($id === null) {
                $id = DB::table('smkp_checklist_categories')->insertGetId([
                    'kode'       => $kategori['kode'],
                    'nama'       => $kategori['nama'],
                    'bobot'      => $kategori['bobot'],
                    'urutan'     => $kategori['urutan'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $baru++;
            } else {
                $ubah += DB::table('smkp_checklist_categories')->where('id', $id)->update([
                    'nama'       => $kategori['nama'],
                    'bobot'      => $kategori['bobot'],
                    'urutan'     => $kategori['urutan'],
                    'updated_at' => $now,
                ]);
            }

            $idKategori[$kategori['kode']] = $id;
        }

        foreach ($data['items'] as $item) {
            $kategoriId = $idKategori[$item['kategori_kode']] ?? null;

            /* Butir yang kategorinya tidak dikenal DILEWATI, bukan
               dipasang ke kategori mana pun yang kebetulan ada: butir
               yang menggantung di kategori salah ikut terhitung ke
               dalam bobot kategori itu, dan skornya tetap keluar
               sebagai angka yang masuk akal. */
            if ($kategoriId === null) continue;

            /* Dikenali dari (kategori, urutan), bukan dari teks
               pertanyaannya. Sembilan pertanyaan memuat baris baru di
               dalamnya, dan pencocokan teks pada kolom TEXT lintas
               SQLite/MySQL/PostgreSQL berbeda perlakuannya terhadap
               spasi — yang gagal cocok akan disisipkan lagi sebagai
               butir kembar, dan bobotnya terhitung dua kali. */
            $id = DB::table('smkp_checklist_items')
                ->where('smkp_checklist_category_id', $kategoriId)
                ->where('urutan', $item['urutan'])
                ->value('id');

            $isi = [
                'grup_kode'  => $item['grup_kode'],
                'grup_nama'  => $item['grup_nama'],
                'nomor'      => $item['nomor'],
                'pertanyaan' => $item['pertanyaan'],
                'petunjuk'   => $item['petunjuk'],
                'bobot'      => $item['bobot'],
                'updated_at' => $now,
            ];

            if ($id === null) {
                DB::table('smkp_checklist_items')->insert($isi + [
                    'smkp_checklist_category_id' => $kategoriId,
                    'urutan'                     => $item['urutan'],
                    'created_at'                 => $now,
                ]);
                $baru++;
            } else {
                $ubah += DB::table('smkp_checklist_items')->where('id', $id)->update($isi);
            }
        }

        return [
            'kategori'   => DB::table('smkp_checklist_categories')->count(),
            'butir'      => DB::table('smkp_checklist_items')->count(),
            'baru'       => $baru,
            'diperbarui' => $ubah,
        ];
    }
}
