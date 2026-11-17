<?php

namespace App\Support\Investigasi;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Kamus penyebab SCAT — 252 butir berbahasa Indonesia, berkode hierarkis.
 *
 * ── MENGAPA DARI BERKAS JSON, BUKAN DARI LARIK PHP ──
 *
 * Kamusnya panjang dan isinya bukan kode: ia daftar istilah yang
 * ditinjau orang K3, bukan pemrogram. Menaruhnya sebagai larik PHP
 * berarti tiap penambahan istilah menjadi penyuntingan berkas kode,
 * dengan risiko koma yang salah menjatuhkan seluruh aplikasi. Sebagai
 * JSON, ia dapat ditinjau, dibandingkan, dan bahkan diganti seluruhnya
 * tanpa menyentuh satu baris pun kode.
 *
 * ── KODE HIERARKIS, DAN MENGAPA ITU PENTING ──
 *
 *   5.x     tindakan tidak aman
 *   6.x     kondisi tidak aman
 *   7.x.y   faktor pribadi
 *   8.x.y   faktor pekerjaan
 *   9.x.y   lack of control
 *
 * Kamus SCAT klasik ILCI yang dipakai sebelumnya hanya 44 butir dan
 * TIDAK punya bagian 9 sama sekali — padahal justru bagian itu yang
 * menyebut kegagalan sistem, dan tanpanya tiap investigasi berakhir
 * pada "operator kurang hati-hati". Lebih buruk lagi, kodenya dibentuk
 * dari tiga huruf pertama nama kategori, sehingga `faktor_pribadi` dan
 * `faktor_pekerjaan` sama-sama menjadi SCAT-FAK-nn dan saling menimpa:
 * dari 44 baris yang dimaksudkan, yang benar-benar tersimpan hanya 37.
 * Tujuh butir hilang diam-diam, tanpa galat.
 */
final class KamusScat
{
    public const BERKAS = 'database/data/scat-taksonomi.json';

    /** Nama kategori per bagian — dipakai mengelompokkan di layar. */
    public const BAGIAN = [
        '5' => 'Tindakan Tidak Aman',
        '6' => 'Kondisi Tidak Aman',
        '7' => 'Faktor Pribadi',
        '8' => 'Faktor Pekerjaan',
        '9' => 'Lack of Control',
    ];

    /**
     * Baca berkasnya menjadi daftar rata siap simpan.
     *
     * @return list<array{kode:string,label:string,kategori:string,tingkat:int,urutan:int}>
     */
    public static function baca(): array
    {
        $jalur = base_path(self::BERKAS);

        if (! is_file($jalur)) {
            throw new RuntimeException('Kamus SCAT tidak ditemukan di '.self::BERKAS);
        }

        $isi = json_decode((string) file_get_contents($jalur), true);

        if (! is_array($isi) || ! isset($isi['kategori'])) {
            throw new RuntimeException('Kamus SCAT tidak terbaca sebagai JSON yang benar.');
        }

        $out = [];
        $n   = 1;

        foreach ($isi['kategori'] as $kat) {
            $bagian   = (string) $kat['bagian'];
            $kategori = self::BAGIAN[$bagian] ?? (string) $kat['label'];

            /* Dua bentuk, dan keduanya memang ada di kamusnya: bagian 5
               dan 6 datar, bagian 7–9 bergrup. Memaksakan satu bentuk
               berarti membuang judul grup — dan judul grup itulah yang
               membuat 86 butir bagian 9 dapat dibaca sama sekali. */
            if (($kat['tipe'] ?? 'flat') === 'flat') {
                foreach ($kat['items'] as $b) {
                    $out[] = [
                        'kode'     => (string) $b['kode'],
                        'label'    => (string) $b['teks'],
                        'kategori' => $kategori,
                        'tingkat'  => (int) $bagian,
                        'urutan'   => $n++,
                        'definisi' => null,
                    ];
                }

                continue;
            }

            foreach ($kat['grup'] as $grup) {
                foreach ($grup['items'] as $b) {
                    $out[] = [
                        'kode'     => (string) $b['kode'],
                        'label'    => (string) $b['teks'],
                        'kategori' => $kategori,
                        'tingkat'  => (int) $bagian,
                        'urutan'   => $n++,

                        /* Judul grupnya disimpan sebagai definisi, bukan
                           dibuang. Butir "7.1.2 Kisaran pergerakan tubuh
                           yang terbatas" hampir tak bermakna sendirian;
                           dengan judul grupnya — "Kemampuan Fisik/Mental
                           Tidak Memadai" — barulah jelas ia bicara apa. */
                        'definisi' => (string) $grup['judul'],
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * Pasang kamusnya. Aman dijalankan berulang.
     *
     * Yang TIDAK dilakukan: menghapus butir yang tidak lagi ada di
     * berkas. Butir yang sudah pernah dipilih investigator menjadi dasar
     * temuan dan tindakan perbaikan; menghapusnya karena kamusnya
     * diperbarui berarti membatalkan analisis yang sudah dikerjakan
     * orang — dan pada berkas yang dapat diminta Inspektur Tambang,
     * penyebab yang hilang tanpa jejak tidak dapat dijelaskan.
     *
     * @return int jumlah butir SCAT sesudah pemasangan
     */
    public static function pasang(): int
    {
        foreach (self::baca() as $b) {
            DB::table('inv_taksonomi')->updateOrInsert(
                ['metode' => 'scat', 'kode' => $b['kode']],
                [
                    'kategori'   => $b['kategori'],
                    'label'      => $b['label'],
                    'tingkat'    => $b['tingkat'],
                    'definisi'   => $b['definisi'],
                    'urutan'     => $b['urutan'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }

        return DB::table('inv_taksonomi')->where('metode', 'scat')->count();
    }
}
