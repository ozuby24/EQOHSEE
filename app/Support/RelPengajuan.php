<?php

namespace App\Support;

/**
 * Rel satu pengajuan — dari draf sampai terbit, beserta apa yang menahan.
 *
 * ── MASALAH YANG DIPECAHKANNYA ──
 *
 * Keterangan tentang sebuah pengajuan sebelumnya terserak di tiga
 * tempat pada satu kartu yang sama: lencana status di pojok kanan,
 * kotak kuning "Belum dapat diajukan" di tengah, dan tombol "Ajukan"
 * jauh di bawah rantai paraf. Ketiganya menjawab satu pertanyaan yang
 * sama — "sekarang giliran siapa, dan apa yang kurang" — dan yang
 * membacanya harus menyusunnya sendiri dari tiga sudut layar.
 *
 * Akibatnya bukan sekadar tidak rapi. Kotak kuningnya hanya muncul
 * ketika kartunya masih dapat diubah, sehingga pengajuan yang sudah
 * dikirim tidak menyebutkan apa pun tentang giliran siapa sekarang —
 * dan yang menunggunya menyimpulkan berkasnya hilang.
 *
 * ── MENGAPA DIBANGUN DI SERVER ──
 *
 * Keadaan tiap simpul dihitung dari status pengajuan DAN dari rantai
 * parafnya sekaligus. Menghitungnya di sisi Vue berarti menyalin aturan
 * "paraf mana yang sudah lewat" ke tempat kedua — dan tempat kedua itu
 * akan berselisih pada hari rantainya berubah, tanpa satu pun galat.
 */
final class RelPengajuan
{
    /**
     * Susun rel dari status dan rantai parafnya.
     *
     * @param  string  $status  status alur pengajuan
     * @param  list<array<string,mixed>>  $rantai  keluaran rantaiTahap()
     * @param  list<string>  $kurang  syarat yang belum terpenuhi
     * @param  ?string  $alasanTolak
     * @param  string  $labelTerbit  sebutan tahap terakhir — "Terbit" bagi
     *                               kartu, "Hasil masuk" bagi MCU
     * @return array<string,mixed>
     */
    public static function bangun(
        string $status,
        array $rantai,
        array $kurang = [],
        ?string $alasanTolak = null,
        string $labelTerbit = 'Terbit',
    ): array {
        $ditolak   = $status === Alur::DITOLAK;
        $disetujui = $status === Alur::DISETUJUI;
        $draf      = $status === Alur::DRAF;

        /* Simpul paraf yang sedang ditunggu: yang PERTAMA belum diparaf.
           Dicari dari rantainya, bukan dari status — status hanya
           mengatakan "sedang diajukan", tidak mengatakan di meja siapa. */
        $menunggu = null;

        foreach ($rantai as $t) {
            if (($t['keadaan'] ?? null) !== 'paraf') { $menunggu = $t['kode'] ?? null; break; }
        }

        $rel = [];

        $rel[] = [
            'kunci' => 'draf',
            'label' => 'Draf',
            'tugas' => 'Lengkapi berkas dan lampirannya, lalu ajukan.',
            'lewat' => ! $draf,
            'kini'  => $draf,
        ];

        foreach ($rantai as $t) {
            $sudah = ($t['keadaan'] ?? null) === 'paraf';

            $rel[] = [
                'kunci' => (string) ($t['kode'] ?? ''),
                'label' => (string) ($t['label'] ?? ''),
                'tugas' => (string) ($t['terang'] ?? ''),

                /* Sesudah disetujui SELURUH simpul terhitung lewat, meski
                   ada yang tidak sempat diparaf. Pengajuan yang sudah
                   diputus tidak lagi menunggu siapa pun, dan simpul yang
                   tetap tergambar "menunggu" pada berkas yang sudah
                   terbit membuat orang mencari paraf yang tidak akan
                   pernah datang. */
                'lewat' => $sudah || $disetujui,
                'kini'  => ! $disetujui && ! $draf && ! $ditolak && ($t['kode'] ?? null) === $menunggu,
            ];
        }

        $rel[] = [
            'kunci' => 'terbit',
            'label' => $labelTerbit,
            'tugas' => 'Berlaku dan dapat dipakai.',
            'lewat' => false,
            'kini'  => $disetujui,
        ];

        foreach ($rel as $i => $t) {
            $rel[$i]['nanti'] = ! $t['lewat'] && ! $t['kini'];
        }

        return [
            'rel'    => $rel,
            'tugas'  => self::tugas($status, $rantai, $menunggu),
            'kurang' => self::kurang($status, $kurang, $rantai, $menunggu, $alasanTolak),

            /* Boleh diajukan hanya bila masih draf DAN tidak ada syarat
               yang menahan. Dihitung di sini, bukan di layar: layar yang
               menghitungnya sendiri sudah pernah menampilkan tombol
               Ajukan pada kartu yang syaratnya kurang, dan penolakannya
               baru muncul sesudah tombolnya ditekan. */
            'bolehAjukan' => $status === Alur::DRAF && $kurang === [],
            'ditolak'     => $ditolak,
            'selesai'     => $disetujui,
        ];
    }

    /** Apa yang dikerjakan sekarang, dalam satu kalimat. */
    private static function tugas(string $status, array $rantai, ?string $menunggu): string
    {
        if ($status === Alur::DRAF)      return 'Lengkapi berkas dan lampirannya, lalu ajukan.';
        if ($status === Alur::DITOLAK)   return 'Perbaiki yang disebut alasan penolakan, lalu ajukan ulang.';
        if ($status === Alur::DISETUJUI) return 'Sudah disetujui dan berlaku.';

        foreach ($rantai as $t) {
            if (($t['kode'] ?? null) === $menunggu) {
                return 'Menunggu paraf '.($t['label'] ?? '—').'.';
            }
        }

        return 'Menunggu peninjauan.';
    }

    /**
     * Apa yang menahan sekarang.
     *
     * Berbeda isinya menurut tahapnya, dan itu perbaikan atas keadaan
     * sebelumnya: daftar syarat hanya berarti selama berkasnya masih
     * disusun. Sesudah dikirim, yang menahan bukan lagi berkasnya
     * melainkan meja yang belum memarafnya — dan itulah yang perlu
     * disebut, bukan kekosongan.
     *
     * @return list<string>
     */
    private static function kurang(
        string $status, array $syarat, array $rantai, ?string $menunggu, ?string $alasanTolak,
    ): array {
        if ($status === Alur::DITOLAK) {
            return [$alasanTolak ? 'Ditolak: '.$alasanTolak : 'Ditolak tanpa alasan tercatat.'];
        }

        if ($status === Alur::DRAF) return array_values($syarat);

        if ($status === Alur::DISETUJUI) return [];

        $sisa = [];

        foreach ($rantai as $t) {
            if (($t['keadaan'] ?? null) === 'paraf') continue;

            $sisa[] = ($t['label'] ?? '—')
                .(($t['kode'] ?? null) === $menunggu ? ' — giliran sekarang' : '');
        }

        return $sisa ? ['Belum diparaf: '.implode(', ', $sisa).'.'] : [];
    }
}
