<?php

namespace App\Support;

/**
 * Ikon bilah samping — dipetakan dari label menu.
 *
 * Terpisah dari App\Support\Ikon meski sama-sama pustaka ikon: yang itu
 * melayani kartu statistik dan mengembalikan atribut `d` saja, yang ini
 * melayani daftar nav dan mengembalikan elemen <svg> utuh. Menyatukannya
 * berarti satu kelas dengan dua bentuk keluaran dan dua peta label yang
 * kebetulan berbagi beberapa kata — lebih mudah salah panggil daripada
 * dicari.
 *
 * Berdiri sebagai kelas, bukan closure di dalam sebuah partial: `@include`
 * tidak mengembalikan variabel yang dibuat di dalamnya ke view pemanggil,
 * jadi fungsi yang didefinisikan di sana selalu null di tata letak — dan
 * ikonnya hilang tanpa satu pun galat muncul.
 *
 * Tiap ikon digambar pada kanvas 24×24 dengan ketebalan garis yang sama
 * supaya tampak satu keluarga, tetapi siluetnya sengaja dibuat berjauhan:
 * pada daftar sepuluh butir, yang dipindai mata lebih dulu adalah bentuk
 * luarnya, bukan rinciannya. Sepuluh persegi berbeda isi terasa seragam
 * dan justru memperlambat pembacaan.
 */
final class IkonNav
{
    public const JALUR = [
        'dashboard'   => 'M4 10.5 12 4l8 6.5V19a1 1 0 0 1-1 1h-4v-6h-6v6H5a1 1 0 0 1-1-1Z',
        'kursus'      => 'M12 3.5 2.8 8 12 12.5 21.2 8Zm-5.6 6.4v4.4c0 1.6 2.5 2.9 5.6 2.9s5.6-1.3 5.6-2.9V9.9M20 9v5',
        'prosedur'    => 'M12 3.2 19 6v5.4c0 4-2.8 7.7-7 8.9-4.2-1.2-7-4.9-7-8.9V6Zm-2.6 8.6 1.9 1.9 3.6-3.8',
        'evaluasi'    => 'M8 4.5H6.5a1.5 1.5 0 0 0-1.5 1.5v13a1.5 1.5 0 0 0 1.5 1.5h11a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H16M9 3h6v3H9Zm-.5 9.5 2 2 4-4.2',
        'sertifikat'  => 'M12 3.2a4.6 4.6 0 1 0 0 9.2 4.6 4.6 0 0 0 0-9.2Zm-3.6 8.6L7 20.8l5-2.6 5 2.6-1.4-9',
        'berita'      => 'M4 6.5h11a1 1 0 0 1 1 1v10a2 2 0 0 0 2 2H6a2 2 0 0 1-2-2Zm12 2h3a1 1 0 0 1 1 1v8a2 2 0 0 1-2 2M7 10h5M7 13h5M7 16h3',
        'nilai'       => 'M4.5 20h15M7.5 20v-6.5M12 20V7.5M16.5 20v-9.5',
        'grafik'      => 'M4 20h16M6.5 16.5l4-4.5 3.2 2.8L20 7.5',
        'kalender'    => 'M5 6.5h14a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1ZM8 4v4m8-4v4M4 11h16M8.5 14.5h2m3 0h2m-7 3h2m3 0h2',
        'faq'         => 'M12 3.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17Zm-2.1 6.2a2.2 2.2 0 0 1 4.2.8c0 1.5-2.1 1.9-2.1 3.3M12 17h.01',
        'orang'       => 'M12 12.2a4.1 4.1 0 1 0 0-8.2 4.1 4.1 0 0 0 0 8.2Zm-7.5 8c0-3.5 3.4-5.6 7.5-5.6s7.5 2.1 7.5 5.6',
        'kalkulator'  => 'M6.5 3h11a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1h-11a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm1.5 3.5h8V9h-8ZM9 12.5h.01M12 12.5h.01M15 12.5h.01M9 16h.01M12 16h.01M15 16v2.5',
        'bahaya'      => 'M12 9.3v4.2m0 3.3h.01M10.4 4 2.5 17.8A1.8 1.8 0 0 0 4.1 20.5h15.8a1.8 1.8 0 0 0 1.6-2.7L13.6 4a1.8 1.8 0 0 0-3.2 0Z',
        'perisai'     => 'M12 3.2 19.2 6.4v5.2c0 4.3-3 8.3-7.2 9.6-4.2-1.3-7.2-5.3-7.2-9.6V6.4Z',
        'kunci'       => 'M7.5 10.5V8a4.5 4.5 0 0 1 9 0v2.5M6 10.5h12a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-8.5a1 1 0 0 1 1-1Zm6 4.2v2.6',
        'gedung'      => 'M4 20.5V4.5a1 1 0 0 1 1-1h7a1 1 0 0 1 1 1v5h6a1 1 0 0 1 1 1v10ZM7 7.5h3M7 11h3M7 14.5h3M16 13h1.5M16 16.5h1.5',
        'lonceng'     => 'M12 3.2a5.3 5.3 0 0 0-5.3 5.3v3.7l-1.9 3.1h14.4l-1.9-3.1V8.5A5.3 5.3 0 0 0 12 3.2ZM9.9 18.4a2.2 2.2 0 0 0 4.2 0',
        'kotak'       => 'M12 3.2 4 7.4v9.2l8 4.2 8-4.2V7.4Zm0 0v18M4 7.4l8 4.2 8-4.2',
        'gerigi'      => 'M12 9.2a2.8 2.8 0 1 0 0 5.6 2.8 2.8 0 0 0 0-5.6Zm7.6 4.1.1-1.3-.1-1.3 1.7-1.3-1.4-2.4-2 .8-2.2-1.3-.3-2.1h-2.8l-.3 2.1-2.2 1.3-2-.8-1.4 2.4 1.7 1.3-.1 1.3.1 1.3-1.7 1.3 1.4 2.4 2-.8 2.2 1.3.3 2.1h2.8l.3-2.1 2.2-1.3 2 .8 1.4-2.4Z',
        'dokumen'     => 'M6 3.5h7.5L19 9v11.5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-16a1 1 0 0 1 1-1Zm7.5 0V9H19M8.5 13h7M8.5 16.5h4.5',
        'buku'        => 'M5 4.5A1.5 1.5 0 0 1 6.5 3H18a1 1 0 0 1 1 1v13.5H6.5A1.5 1.5 0 0 0 5 19Zm0 14.5A1.5 1.5 0 0 0 6.5 20.5H19M8.5 7h7M8.5 10.5h5',
        'energi'      => 'M13.2 3 5.6 13.8h5.1l-.9 7.2 7.6-10.8h-5.1Z',
        'armada'      => 'M3 16.5V8a1 1 0 0 1 1-1h9.5v9.5M13.5 10.5H17l3 3.5v2.5M6.2 19.7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z',
        'alat'        => 'M14.6 6.4a4 4 0 1 0 5.1 5.1l-9.5 9.5-2.8.7.7-2.8ZM4 4l4 4',
        'tukar'       => 'M4 8.5h13l-3.2-3.2M20 15.5H7l3.2 3.2',
        'timbang'     => 'M12 4.5v15M7.5 6.2h9M4 19.5h16M6 6.8 3 13.2h6ZM18 6.8l-3 6.4h6Z',
        'obrolan'     => 'M4 5.5h16a1 1 0 0 1 1 1V15a1 1 0 0 1-1 1H9l-4 3.5V16H4a1 1 0 0 1-1-1V6.5a1 1 0 0 1 1-1Z',
        'default'     => 'M5 12h14M5 7h9M5 17h9',
    ];

    /**
     * Kata kunci label → nama ikon.
     *
     * Kunci terpanjang yang cocok menang, sehingga urutan penulisan di sini
     * tidak memengaruhi hasil: "Evaluasi SOP" tidak boleh kalah oleh "sop"
     * hanya karena kebetulan ditulis belakangan.
     */
    private const PETA = [
        'dashboard' => 'dashboard', 'beranda' => 'dashboard',
        'kursus' => 'kursus', 'pelatihan' => 'kursus', 'learning' => 'kursus',
        'prosedur & sop' => 'prosedur', 'prosedur' => 'prosedur',
        'evaluasi sop' => 'evaluasi', 'evaluasi' => 'nilai',
        'sertifikat' => 'sertifikat', 'penanda tangan' => 'sertifikat',
        'berita' => 'berita', 'pengumuman' => 'berita',
        'kalender' => 'kalender', 'jadwal' => 'kalender',
        'faq' => 'faq', 'bantuan' => 'faq',
        'rekapitulasi' => 'nilai', 'formulir' => 'nilai', 'penilaian' => 'nilai',
        'kuesioner' => 'evaluasi', 'visualisasi' => 'grafik', 'analitik' => 'grafik',
        'kpi' => 'grafik', 'summary' => 'grafik', 'hasil' => 'grafik',
        'program' => 'nilai', 'profil' => 'orang', 'tenaga' => 'orang',
        'pengguna' => 'orang', 'peserta' => 'orang', 'mitra' => 'orang',
        'slovin' => 'kalkulator', 'kalkulator' => 'kalkulator',
        'metode' => 'kotak', 'matriks' => 'kotak', 'register' => 'kotak',
        'jenis' => 'kotak', 'data' => 'kotak', 'master' => 'kotak',
        // Lebih panjang daripada 'data', jadi menang atas ikon kotak.
        'data diri' => 'orang', 'data perusahaan' => 'gedung',
        'direktori' => 'orang', 'personalia' => 'orang',
        'laporan' => 'dokumen', 'monitor' => 'bahaya', 'hazard' => 'bahaya',
        'temuan' => 'bahaya', 'risiko' => 'bahaya',
        'inspeksi' => 'evaluasi', 'audit' => 'evaluasi', 'tindak' => 'evaluasi',
        'pengingat' => 'lonceng', 'notifikasi' => 'lonceng',
        'kelayakan' => 'perisai', 'pengaman' => 'perisai', 'keselamatan' => 'perisai',
        'hse' => 'perisai', 'smkp' => 'perisai',
        'perawatan' => 'gerigi', 'maintenance' => 'gerigi',
        'pengaturan' => 'gerigi', 'pusat kendali' => 'gerigi',
        'perusahaan' => 'gedung',
        'regulasi' => 'buku', 'instrumen' => 'buku', 'rubrik' => 'buku',
        'tentang' => 'buku', 'standard' => 'buku', 'dokumen' => 'buku',
        'energy' => 'energi', 'energi' => 'energi', 'listrik' => 'energi',
        'fleet' => 'armada', 'armada' => 'armada', 'equipment' => 'armada',
        'bahan bakar' => 'armada', 'fuel' => 'armada',
        'tools' => 'alat', 'alat' => 'alat',
        'kajian' => 'buku', 'buat' => 'dokumen', 'baru' => 'dokumen',
        'pesan' => 'obrolan', 'chat' => 'obrolan', 'obrolan' => 'obrolan',

        // Gudang & Penyimpanan.
        'barang' => 'kotak', 'persediaan' => 'kotak', 'stok' => 'kotak',
        'lokasi' => 'gedung', 'gudang' => 'gedung', 'penyimpanan' => 'gedung',
        'mutasi' => 'tukar', 'keluar masuk' => 'tukar',
        'opname' => 'timbang', 'stok opname' => 'timbang',
        'b3' => 'bahaya', 'register b3' => 'bahaya',
        'laporan stok' => 'dokumen',
    ];

    /** Nama ikon untuk sebuah label menu. */
    public static function nama(string $label): string
    {
        $l = mb_strtolower(trim($label));

        $kunci = 'default';
        $panjang = 0;

        foreach (self::PETA as $cari => $ikon) {
            if (mb_strlen($cari) > $panjang && str_contains($l, $cari)) {
                $kunci = $ikon;
                $panjang = mb_strlen($cari);
            }
        }

        return isset(self::JALUR[$kunci]) ? $kunci : 'default';
    }

    /** Elemen <svg> siap pakai untuk sebuah label menu. */
    public static function svg(string $label, string $kelas = 'eq-navico'): string
    {
        return '<svg class="'.e($kelas).'" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
             .' stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
             .'<path d="'.self::JALUR[self::nama($label)].'"/></svg>';
    }
}
