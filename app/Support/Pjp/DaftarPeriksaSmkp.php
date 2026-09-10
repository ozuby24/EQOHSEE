<?php

namespace App\Support\Pjp;

use App\Models\Pjp\SmkpKategori;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Daftar periksa prakualifikasi SMKP bagi perusahaan jasa pertambangan.
 *
 * 17 kategori dan 126 butir, salinan lampiran Kepdirjen Minerba
 * 185/2019 — data acuan, bukan karangan sendiri dan bukan contoh.
 * Ditaruh di kelas ini alih-alih di dalam migrasi karena dipakai dua
 * kali: saat pemasangan pertama, dan saat lampirannya direvisi, tanpa
 * perlu migrasi baru.
 *
 * PEMASANGANNYA IDEMPOTEN, DAN TIDAK PERNAH MENGHAPUS.
 *
 * Itu bukan kerapian. `pjp_smkp_jawaban.item_id` menunjuk ke butirnya,
 * dan kolomnya cascade on delete: satu penyemai yang membersihkan
 * tabelnya lebih dahulu akan menghapus SELURUH jawaban daftar periksa
 * setiap mitra — pekerjaan berbulan-bulan — dan yang tersisa di layar
 * hanyalah daftar periksa yang kembali kosong tanpa satu pun galat.
 * Perintah `pjp:pasang` dijalankan ulang pada tiap penerapan, jadi
 * kemungkinannya bukan hipotetis.
 *
 * KUNCI PENGENAL BUTIRNYA (kategori, urutan), BUKAN pertanyaannya.
 *
 * `nomor` tidak dapat dipakai: penomorannya berulang di tiap grup, dan
 * 49 dari 126 butir memakai nomor yang sudah dipakai butir lain dalam
 * kategori yang sama. Teks pertanyaannya juga tidak: perbaikan salah
 * ketik akan melahirkan butir baru dan meninggalkan jawaban lama
 * menggantung pada butir yang tak lagi ditampilkan.
 *
 * Akibatnya satu: REVISI LAMPIRAN HARUS MENAMBAH DI BELAKANG, jangan
 * menyisipkan lalu menggeser urutan. Menggesernya membuat jawaban lama
 * berpindah ke pertanyaan lain — nilainya tetap, pertanyaannya berbeda,
 * dan tidak ada yang menandainya.
 */
final class DaftarPeriksaSmkp
{
    public const BERKAS = 'database/data/pjp-smkp.json';

    /**
     * Baca berkasnya.
     *
     * @return array{kategori:list<array<string,mixed>>,butir:list<array<string,mixed>>}
     */
    public static function baca(): array
    {
        $jalur = base_path(self::BERKAS);

        if (! is_file($jalur)) {
            throw new RuntimeException('Daftar periksa SMKP tidak ditemukan di '.self::BERKAS);
        }

        $isi = json_decode((string) file_get_contents($jalur), true);

        if (! is_array($isi) || ! isset($isi['categories'], $isi['items'])) {
            throw new RuntimeException('Daftar periksa SMKP tidak terbaca sebagai JSON yang benar.');
        }

        $kategori = array_map(fn (array $k) => [
            'kode'   => (string) $k['kode'],
            'nama'   => (string) $k['nama'],
            'bobot'  => (int) $k['bobot'],
            'urutan' => (int) $k['urutan'],
        ], $isi['categories']);

        $butir = array_map(fn (array $b) => [
            'kategori_kode' => (string) $b['kategori_kode'],

            /* Kosong disimpan sebagai NULL, bukan sebagai string
               kosong. Butir kategori LEGALITAS memang tidak bergrup,
               dan judul grup berupa "" akan digambar sebagai baris
               judul kosong di antara pertanyaannya. */
            'grup_kode'  => self::atauNull($b['grup_kode'] ?? null),
            'grup_nama'  => self::atauNull($b['grup_nama'] ?? null),
            'nomor'      => (int) $b['nomor'],
            'pertanyaan' => (string) $b['pertanyaan'],
            'petunjuk'   => self::atauNull($b['petunjuk'] ?? null),
            'bobot'      => (int) $b['bobot'],
            'urutan'     => (int) $b['urutan'],
        ], $isi['items']);

        return ['kategori' => $kategori, 'butir' => $butir];
    }

    /**
     * Pasang daftar periksanya. Aman dijalankan berulang.
     *
     * @return array<string,int> nama tabel => jumlah baris sesudahnya
     */
    public static function pasang(): array
    {
        ['kategori' => $kategori, 'butir' => $butir] = self::baca();

        $saat = now();
        $id   = [];

        foreach ($kategori as $k) {
            DB::table('pjp_smkp_kategori')->updateOrInsert(
                ['kode' => $k['kode']],
                array_diff_key($k, ['kode' => null]) + ['updated_at' => $saat, 'created_at' => $saat],
            );

            $id[$k['kode']] = (int) DB::table('pjp_smkp_kategori')
                ->where('kode', $k['kode'])->value('id');
        }

        foreach ($butir as $b) {
            $kode = $b['kategori_kode'];

            if (! isset($id[$kode])) {
                throw new RuntimeException("Butir menyebut kategori yang tidak ada: {$kode}.");
            }

            $cari = ['kategori_id' => $id[$kode], 'urutan' => $b['urutan']];

            DB::table('pjp_smkp_item')->updateOrInsert(
                $cari,
                array_diff_key($b, ['kategori_kode' => null, 'urutan' => null])
                    + ['updated_at' => $saat, 'created_at' => $saat],
            );
        }

        /* Ingatan sepanjang permintaan pada SmkpKategori dibuang: kalau
           skor sempat dihitung sebelum pemasangan ini — pada uji, atau
           pada perintah yang mengerjakan keduanya — ia memegang daftar
           kategori yang sudah tidak berlaku. */
        SmkpKategori::lupakanIngatan();

        return [
            'pjp_smkp_kategori' => (int) DB::table('pjp_smkp_kategori')->count(),
            'pjp_smkp_item'     => (int) DB::table('pjp_smkp_item')->count(),
        ];
    }

    private static function atauNull(mixed $nilai): ?string
    {
        $nilai = trim((string) ($nilai ?? ''));

        return $nilai === '' ? null : $nilai;
    }
}
