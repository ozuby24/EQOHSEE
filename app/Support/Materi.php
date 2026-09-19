<?php

namespace App\Support;

use App\Models\Material;

/**
 * Apa yang diketahui aplikasi tentang satu jenis materi.
 *
 * Sebelum berkas ini ada, `type` hanya tali teks bebas yang tergambar
 * apa adanya sebagai lencana — "pptx", "video", "pdf" — dengan huruf
 * kecil dan tanpa satu pun petunjuk bagaimana membukanya. Nama kolom
 * basis data yang bocor ke layar.
 *
 * Yang dikumpulkan di sini: nama yang pantas dibaca, ikon, nada warna,
 * dan satu keputusan yang tidak boleh tersebar — jenis mana yang boleh
 * disematkan ke dalam halaman.
 */
final class Materi
{
    /**
     * jenis => [label, kunci ikon, nada].
     *
     * Kunci ikonnya nama yang sudah dikenal IkonStat, bukan nama baru.
     * Ikon yang tidak ada di sana tergambar sebagai ruang kosong, dan
     * ruang kosong berukuran ikon tampak sebagai gambar yang gagal
     * dimuat, bukan sebagai jenis yang belum berikon.
     */
    public const JENIS = [
        'video'    => ['Video',      'video', 'biru'],
        'pdf'      => ['PDF',        'materi', 'merah'],
        'pptx'     => ['Presentasi', 'slaid', 'jingga'],
        'document' => ['Dokumen',    'materi', 'abu'],
        'file'     => ['Berkas',     'materi', 'abu'],
    ];

    /**
     * Inang yang boleh disematkan, beserta cara menyusun alamat sematnya.
     *
     * DAFTAR PUTIH, bukan daftar hitam, dan alamatnya DISUSUN ULANG di
     * sini alih-alih diteruskan apa adanya.
     *
     * Alasannya bukan kehati-hatian umum. `materials.url` diisi lewat
     * halaman kelola, dan apa pun yang diisikan di situ akan berakhir
     * sebagai atribut `src` sebuah <iframe> di halaman setiap peserta.
     * Sebuah alamat `javascript:` di situ berjalan di dalam asal yang
     * sama dengan aplikasinya; sebuah halaman luar yang dimuat penuh
     * dapat menggambar formulir masuk palsu yang tampak berada di dalam
     * EQOHSEE. Menyusun ulang alamatnya dari ID yang sudah disaring
     * membuat keduanya tidak mungkin: yang tidak cocok polanya tidak
     * pernah menjadi iframe sama sekali, melainkan kartu tautan biasa
     * yang membuka tab baru — tempat orang dapat melihat alamat aslinya.
     */
    private const SEMAT = [
        'youtube.com'     => 'yt',
        'www.youtube.com' => 'yt',
        'm.youtube.com'   => 'yt',
        'youtu.be'        => 'yt-pendek',
        'vimeo.com'       => 'vimeo',
        'www.vimeo.com'   => 'vimeo',
        'player.vimeo.com'=> 'vimeo-pemutar',
    ];

    /** @return array{0:string,1:string,2:string} */
    public static function jenis(?string $t): array
    {
        return self::JENIS[strtolower((string) $t)] ?? self::JENIS['file'];
    }

    public static function label(?string $t): string { return self::jenis($t)[0]; }

    public static function ikon(?string $t): string { return self::jenis($t)[1]; }

    public static function nada(?string $t): string { return self::jenis($t)[2]; }

    /**
     * Alamat semat bagi satu URL, atau null bila ia tidak boleh disemat.
     *
     * null BUKAN kegagalan. Ia jawaban yang benar bagi sebagian besar
     * tautan, dan halamannya memang menggambar kartu tautan untuk itu.
     */
    public static function semat(?string $url): ?string
    {
        if (!$url) return null;

        $bagian = parse_url($url);

        /* Skema diperiksa lebih dulu dan hanya http/https yang lolos.
           parse_url('javascript:alert(1)') menghasilkan skema
           'javascript' tanpa host sama sekali — pemeriksaan yang hanya
           melihat host akan meloloskannya sebagai "bukan inang
           terdaftar" dan berhenti di situ, yang kebetulan benar; tetapi
           urutan yang bergantung pada kebetulan adalah urutan yang akan
           salah pada perubahan berikutnya. */
        if (!in_array(strtolower($bagian['scheme'] ?? ''), ['http', 'https'], true)) return null;

        $inang = strtolower($bagian['host'] ?? '');
        $cara  = self::SEMAT[$inang] ?? null;

        if ($cara === null) return null;

        $jalur = trim($bagian['path'] ?? '', '/');
        parse_str($bagian['query'] ?? '', $kueri);

        $id = match ($cara) {
            'yt'            => (string) ($kueri['v'] ?? ''),
            'yt-pendek'     => $jalur,
            'vimeo'         => $jalur,
            'vimeo-pemutar' => str_replace('video/', '', $jalur),
            default         => '',
        };

        /* Hanya huruf, angka, garis bawah, dan tanda hubung. Satu
           tanda kutip atau satu garis miring di sini sudah cukup untuk
           keluar dari alamat yang sedang disusun. */
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id)) return null;

        return str_starts_with($cara, 'yt')
            ? "https://www.youtube-nocookie.com/embed/{$id}"
            : "https://player.vimeo.com/video/{$id}";
    }

    /**
     * Durasi yang terbaca manusia, atau null bila tidak diisi.
     *
     * Mengembalikan null, bukan "0 menit". Materi yang belum diisi
     * durasinya bukan materi nol menit, dan halamannya melewatkan
     * baris itu daripada berbohong dengan angka.
     */
    public static function durasi(?int $menit): ?string
    {
        if (!$menit || $menit < 1) return null;

        if ($menit < 60) return "{$menit} menit";

        $jam  = intdiv($menit, 60);
        $sisa = $menit % 60;

        return $sisa ? "{$jam} jam {$sisa} menit" : "{$jam} jam";
    }

    /**
     * Apa saja yang ada di dalam satu materi — untuk daftar "Isi materi ini".
     *
     * Disusun dari kolom yang memang terisi, bukan dari daftar tetap.
     * Daftar tetap yang menyebut "Video · SOP · Kuis" pada materi yang
     * hanya punya satu di antaranya menjanjikan dua hal yang tidak ada.
     *
     * @return list<array{jenis:string,label:string,ikon:string,ket:string}>
     */
    public static function isi(Material $m): array
    {
        $out = [];

        if ($m->url) {
            [$label, $ikon] = self::jenis($m->type);
            $out[] = [
                'jenis' => strtolower((string) ($m->type ?: 'file')),
                'label' => $label.' utama',
                'ikon'  => $ikon,
                'ket'   => self::semat($m->url) ? 'Diputar di halaman ini' : 'Terbuka di tab baru',
            ];
        }

        if (trim((string) $m->content) !== '') {
            $out[] = [
                'jenis' => 'teks',
                'label' => 'Bacaan',
                'ikon'  => 'materi',
                'ket'   => 'Dibaca langsung di halaman ini',
            ];
        }

        if ($m->sop_url) {
            $out[] = [
                'jenis' => 'sop',
                'label' => 'SOP terkait',
                'ikon'  => 'sop',
                'ket'   => 'Prosedur yang mendasari materi ini',
            ];
        }

        foreach ($m->lampiran as $l) {
            $out[] = [
                'jenis' => 'lampiran',
                'label' => $l->title,
                'ikon'  => 'materi',
                'ket'   => 'Lampiran',
            ];
        }

        return $out;
    }
}
