<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * Memecah naskah peraturan menjadi butir pasal dan ayat.
 *
 * ── Kenapa tidak diserahkan seluruhnya kepada AI ──
 *
 * Yang dikerjakan di sini deterministik: memisahkan naskah menurut
 * penanda "Pasal 12", "(1)", dan "huruf a" adalah pekerjaan tata
 * bahasa, bukan pekerjaan penalaran. Menyerahkannya ke model bahasa
 * berarti tiga hal sekaligus: pemasangan tanpa kunci API kehilangan
 * seluruh fiturnya, satu peraturan panjang menghabiskan token yang
 * mahal untuk pekerjaan yang dapat dikerjakan sebuah ungkapan reguler,
 * dan — yang paling merugikan — model dapat MELEWATKAN pasal tanpa
 * mengatakan apa pun. Pasal yang hilang dari register tidak dapat
 * diketahui dari register itu sendiri.
 *
 * AI dipakai untuk yang memang memerlukan penalaran atau penglihatan:
 * meringkas kewajiban, menilai apakah sebuah butir mewajibkan perusahaan,
 * mengusulkan bentuk penerapan, dan membaca halaman PDF yang berupa
 * gambar. Lihat App\Support\AnalisisPeraturan.
 *
 * ── Yang diuji terhadap berkas JDIH yang sebenarnya ──
 *
 * Permen ESDM 26/2018 dari jdih.esdm.go.id: halaman 1 (judul dan nomor)
 * dan halaman 46 (Pasal 61 dan penetapan) berupa GAMBAR di dalam PDF yang
 * selebihnya berteks. Nomor halaman "- 12 -" dan judul BAB/Bagian/
 * Paragraf ikut menempel pada ayat terakhir pasal sebelumnya, dan naskah
 * itu memuat lebih dari seratus dua puluh ayat — batas lama, yang
 * memotong sisanya tanpa satu pun pemberitahuan.
 */
final class PemecahPeraturan
{
    /** Batas wajar satu unggahan, supaya satu berkas tidak menghabiskan memori. */
    public const MAKS_AKSARA = 400_000;

    /**
     * Paling banyak sekian butir diusulkan dari satu naskah.
     *
     * Bila naskahnya lebih panjang, jumlah yang terpotong DISEBUT — lihat
     * KepatuhanController::rangkum. Kewajiban yang hilang diam-diam dari
     * register tidak dapat diketahui dari register itu sendiri.
     */
    public const MAKS_BUTIR = 500;

    /** Halaman PDF yang teksnya kurang dari ini dianggap gambar. */
    private const HALAMAN_KOSONG = 40;

    /**
     * Ambil naskah dari berkas unggahan.
     *
     * Untuk PDF, teksnya dibaca PER HALAMAN supaya halaman yang berupa
     * gambar dapat disebut nomornya — dan dibaca AI bila tersedia —
     * alih-alih hilang tanpa jejak di antara halaman yang terbaca.
     *
     * @return array{teks:string,catatan:?string,halaman:?int,perHalaman:?list<string>,halamanGambar:list<int>}
     */
    public static function dariBerkas(UploadedFile $berkas): array
    {
        $ext = strtolower($berkas->getClientOriginalExtension());
        $perHalaman = null;

        $teks = match ($ext) {
            'txt', 'md' => (string) file_get_contents($berkas->getRealPath()),
            'docx'      => self::dariDocx($berkas->getRealPath()),
            'pdf'       => implode("\n\n", $perHalaman = self::halamanPdf($berkas->getRealPath())),
            default     => '',
        };

        $teks = self::rapikan($teks);

        $gambar = [];
        foreach ($perHalaman ?? [] as $i => $h) {
            if (mb_strlen(trim($h)) < self::HALAMAN_KOSONG) $gambar[] = $i + 1;
        }

        $hasil = [
            'teks'          => $teks,
            'catatan'       => null,
            'halaman'       => $perHalaman === null ? null : count($perHalaman),
            'perHalaman'    => $perHalaman,
            'halamanGambar' => $gambar,
        ];

        if ($teks === '') {
            $hasil['catatan'] = match ($ext) {
                'pdf'  => $perHalaman
                    ? 'Seluruh '.count($perHalaman).' halaman PDF-nya berupa gambar (hasil pindaian), bukan teks.'
                    : 'Berkas PDF-nya tidak dapat dibaca — mungkin rusak atau terkunci sandi.',
                'docx', 'txt', 'md' => 'Berkas terbaca tetapi isinya kosong.',
                default => 'Jenis berkas '.($ext ?: 'ini').' belum dapat dibaca. '
                          .'Pakai .pdf, .docx, atau .txt — atau tempelkan naskahnya.',
            };
        } elseif ($gambar) {
            $hasil['catatan'] = 'Halaman '.self::daftarHalaman($gambar).' berupa gambar, bukan teks — isinya '
                .'(sering kali judul, nomor, pasal terakhir, dan tanggal penetapan) tidak ikut terbaca.';
        }

        return $hasil;
    }

    /** "1, 3–5, 46" dari [1,3,4,5,46]. */
    public static function daftarHalaman(array $h): string
    {
        sort($h);
        $out = [];
        for ($i = 0; $i < count($h); $i++) {
            $a = $h[$i];
            while ($i + 1 < count($h) && $h[$i + 1] === $h[$i] + 1) $i++;
            $out[] = $a === $h[$i] ? (string) $a : $a.'–'.$h[$i];
        }

        return implode(', ', $out);
    }

    /** @return list<string> teks tiap halaman; [] bila berkasnya tidak terbaca */
    private static function halamanPdf(string $jalur): array
    {
        try {
            $pdf = (new \Smalot\PdfParser\Parser())->parseFile($jalur);

            return array_map(function ($hal) {
                try {
                    return self::bersihkanHalaman((string) $hal->getText());
                } catch (\Throwable) {
                    return '';
                }
            }, array_values($pdf->getPages()));
        } catch (\Throwable $e) {
            Log::warning('PDF peraturan gagal dibaca', ['galat' => $e->getMessage()]);

            return [];
        }
    }

    /** Jumlah halaman sebuah PDF, atau null bila tidak terbaca. */
    public static function jumlahHalaman(string $jalur): ?int
    {
        try {
            return count((new \Smalot\PdfParser\Parser())->parseFile($jalur)->getPages());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Buang kepala dan kaki halaman yang bukan bagian naskah.
     *
     * Nomor halaman "- 12 -" dan kepala Berita Negara "2018, No.595 -12-"
     * berada di tengah kalimat bila sebuah ayat melintasi halaman; tanpa
     * dibuang, ayatnya terbaca "wajib - 12 - menyusun".
     */
    public static function bersihkanHalaman(string $teks): string
    {
        $buang = [
            '~^\s*-\s*\d{1,4}\s*-\s*$~mu',                                  // - 12 -
            '~^\s*\d{4}\s*,\s*No\.?\s*\d+[^\n]{0,20}$~mu',                 // 2018, No.595 -12-
            '~^\s*(?:www\.|https?://)\S+\s*$~mu',                            // www.peraturan.go.id
            '~^\s*(?:jdih|peraturan)\.[a-z.]+\.go\.id\s*$~mui',
        ];

        return (string) preg_replace($buang, '', $teks);
    }

    /**
     * .docx dibaca tanpa pustaka tambahan: ia sebuah zip berisi XML.
     *
     * `<w:p>` dijadikan baris baru sebelum tag dibuang, supaya "Pasal 3"
     * dan ayat di bawahnya tidak menyatu menjadi satu paragraf panjang
     * yang tidak dapat dipecah lagi.
     */
    private static function dariDocx(string $jalur): string
    {
        $zip = new \ZipArchive();

        if ($zip->open($jalur) !== true) return '';

        $xml = $zip->getFromName('word/document.xml') ?: '';
        $zip->close();

        if ($xml === '') return '';

        $xml = preg_replace('~</w:p>~', "\n", $xml);
        $xml = preg_replace('~<w:tab[^>]*/>~', "\t", $xml);

        return html_entity_decode(strip_tags((string) $xml), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    public static function rapikan(string $teks): string
    {
        $teks = str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $teks);
        $teks = preg_replace('~[ \t]+~u', ' ', $teks) ?? $teks;
        $teks = preg_replace('~ *\n *~u', "\n", $teks) ?? $teks;
        $teks = preg_replace('~\n{3,}~', "\n\n", $teks) ?? $teks;

        return trim(mb_substr($teks, 0, self::MAKS_AKSARA));
    }

    /** Judul bagian yang berdiri di antara dua pasal. */
    private const JUDUL_BAGIAN = '~^(?:BAB\s+[IVXLCDM]+|Bagian\s+Ke[a-z]+|Paragraf\s+\d+)\s*$~mu';

    /** Batas akhir batang tubuh: penjelasan, lampiran, dan penetapan. */
    private const AKHIR_BATANG_TUBUH = '~^(?:PENJELASAN(?:\s+ATAS)?|LAMPIRAN(?:\s+[IVXLC]+)?)\s*$~mu';

    /**
     * Pecah naskah menjadi butir: satu pasal, atau satu ayat bila
     * pasalnya berayat.
     *
     * Ayat dipecah TERPISAH dari pasalnya, bukan digabung, karena
     * penilaian pemenuhan terjadi per ayat: sebuah pasal kerap memuat
     * satu ayat yang sudah dipenuhi dan satu lagi yang belum, dan
     * penilaian tunggal atas keduanya tidak dapat menyatakan mana yang
     * mana.
     *
     * Memulangkan SELURUH butir. Pembatasan jumlahnya urusan pemanggil,
     * yang wajib menyebut berapa yang terpotong.
     *
     * @return array<int,array{penunjuk:string,isi:string}>
     */
    public static function pecah(string $naskah): array
    {
        $naskah = self::rapikan(self::bersihkanHalaman($naskah));

        if ($naskah === '') return [];

        /* Penjelasan mengulang "Pasal 1 — Cukup jelas." untuk tiap pasal,
           dan lampiran bukan batang tubuh. Keduanya dipotong di sini;
           tanpa itu setiap pasal muncul dua kali di register. */
        if (preg_match(self::AKHIR_BATANG_TUBUH, $naskah, $m, PREG_OFFSET_CAPTURE)
            && preg_match('~^\s*Pasal\s+\d~mu', substr($naskah, 0, $m[0][1]))) {
            $naskah = substr($naskah, 0, $m[0][1]);
        }

        /* Potong pada tiap "Pasal N" yang berdiri sendiri di satu baris.
           `Pasal` di tengah kalimat — "sebagaimana dimaksud dalam Pasal
           5" — bukan judul dan tidak boleh memotong naskah di situ; itu
           akan memecah satu pasal menjadi selusin serpihan. Angka Romawi
           dipakai peraturan perubahan ("Pasal I", "Pasal II"). */
        $bagian = preg_split(
            '~^\s*(Pasal\s+(?:\d+[A-Za-z]?|[IVXLC]+))\s*$~mu',
            $naskah,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        ) ?: [];

        $butir = [];
        $pasal = null;

        foreach ($bagian as $potong) {
            $potong = trim($potong);
            if ($potong === '') continue;

            if (preg_match('~^Pasal\s+(?:\d+[A-Za-z]?|[IVXLC]+)$~u', $potong)) {
                $pasal = preg_replace('~\s+~', ' ', $potong);
                continue;
            }

            if ($pasal === null) continue;   // pembukaan, menimbang, mengingat

            foreach (self::ayat($pasal, self::isiPasal($potong)) as $b) {
                $butir[] = $b;
            }
        }

        return $butir;
    }

    /**
     * Isi satu pasal tanpa apa pun yang menempel sesudahnya.
     *
     * Judul BAB, Bagian, dan Paragraf selalu berada DI ANTARA dua pasal,
     * jadi yang berada sesudah judul pertama bukan milik pasal ini.
     * Begitu pula blok penetapan sesudah pasal terakhir.
     */
    private static function isiPasal(string $isi): string
    {
        foreach ([self::JUDUL_BAGIAN, '~^\s*Ditetapkan\s+di\b~mu'] as $pola) {
            if (preg_match($pola, $isi, $m, PREG_OFFSET_CAPTURE)) {
                $isi = substr($isi, 0, $m[0][1]);
            }
        }

        return trim($isi);
    }

    /**
     * Pecah isi satu pasal menjadi ayat-ayatnya.
     *
     * Penanda "(2)" di awal baris belum tentu ayat: rujukan yang
     * terlipat — "sebagaimana dimaksud pada ayat\n(2) huruf a" — juga
     * dimulai dari sana. Penanda diterima sebagai ayat hanya bila
     * nomornya MELANJUTKAN urutan dan baris sebelumnya tidak berakhir
     * dengan kata yang menuntut lanjutan ("ayat", "dan", "pada").
     *
     * @return array<int,array{penunjuk:string,isi:string}>
     */
    private static function ayat(string $pasal, string $isi): array
    {
        /* Di awal baris, atau sesudah akhir kalimat bila ekstraksi PDF
           menyatukan dua baris: "…yang baik. (2) Kaidah…". */
        preg_match_all('~(?:^|\n|(?<=[.;:]) )[ \t]*\((\d+)([a-z]?)\)[ \t]*~u', $isi, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);

        $potong = [];
        $harap  = 1;

        foreach ($m as $c) {
            $no    = (int) $c[1][0];
            $huruf = $c[2][0];
            $awal  = $c[0][1];

            $sebelum = rtrim(substr($isi, 0, $awal));
            $rujukan = (bool) preg_match('~\b(?:ayat|pada|dan|atau|dalam|dengan)$~iu', $sebelum);

            $berurut = $huruf === '' ? $no === $harap : $no === $harap - 1;

            if ($rujukan || !$berurut) continue;

            $potong[] = ['no' => $no.$huruf, 'awal' => $awal, 'isiMulai' => $awal + strlen($c[0][0])];
            if ($huruf === '') $harap = $no + 1;
        }

        // Tanpa penanda ayat: pasalnya satu butir utuh.
        if (!$potong) {
            $teks = self::ringkasIsi($isi);

            return $teks === '' ? [] : [['penunjuk' => $pasal, 'isi' => $teks]];
        }

        $out = [];

        foreach ($potong as $i => $p) {
            $ujung = $potong[$i + 1]['awal'] ?? strlen($isi);
            $teks  = self::ringkasIsi(substr($isi, $p['isiMulai'], $ujung - $p['isiMulai']));
            if ($teks === '') continue;

            $out[] = ['penunjuk' => $pasal.' Ayat ('.$p['no'].')', 'isi' => $teks];
        }

        return $out;
    }

    /**
     * Rapikan isi satu butir menjadi satu blok yang dapat dibaca.
     *
     * Dipotong pada 1.200 aksara: yang tersimpan adalah RANGKUMAN
     * kewajibannya, bukan salinan naskah peraturannya. Register yang
     * memuat naskah utuh menjadi salinan peraturan yang buruk sekaligus
     * register yang tidak dapat dibaca sekilas — dan naskah aslinya
     * memang sudah dilampirkan sebagai berkas.
     */
    private static function ringkasIsi(string $teks): string
    {
        $teks = trim(preg_replace('~\s+~u', ' ', $teks) ?? $teks);

        if ($teks === '') return '';

        if (mb_strlen($teks) <= 1200) return $teks;

        return rtrim(mb_substr($teks, 0, 1200), " ,;").'…';
    }

    /* ═══════════════ identitas peraturan ═══════════════ */

    /** Kepala naskah → jenis pada Kepatuhan::JENIS, dari yang paling khusus. */
    private const JENIS_KEPALA = [
        'KEPUTUSAN DIREKTUR JENDERAL' => 'Keputusan Direktur Jenderal',
        'PERATURAN DIREKTUR JENDERAL' => 'Keputusan Direktur Jenderal',
        'PERATURAN PEMERINTAH'        => 'Peraturan Pemerintah',
        'PERATURAN PRESIDEN'          => 'Peraturan Presiden',
        'PERATURAN MENTERI'           => 'Peraturan Menteri',
        'KEPUTUSAN MENTERI'           => 'Keputusan Menteri',
        'PERATURAN DAERAH'            => 'Peraturan Daerah',
        'UNDANG-UNDANG'               => 'Undang-Undang',
    ];

    /** Singkatan lazim untuk kolom Nomor, mis. "Permen ESDM Nomor 26 Tahun 2018". */
    private const SINGKAT = [
        'Undang-Undang'               => 'UU',
        'Peraturan Pemerintah'        => 'PP',
        'Peraturan Presiden'          => 'Perpres',
        'Peraturan Menteri'           => 'Permen',
        'Keputusan Menteri'           => 'Kepmen',
        'Keputusan Direktur Jenderal' => 'Kepdirjen',
        'Peraturan Daerah'            => 'Perda',
    ];

    private const KEMENTERIAN_SINGKAT = [
        'ENERGI DAN SUMBER DAYA MINERAL' => 'ESDM',
        'KETENAGAKERJAAN'                => 'Naker',
        'TENAGA KERJA DAN TRANSMIGRASI'  => 'Nakertrans',
        'TENAGA KERJA'                   => 'Naker',
        'LINGKUNGAN HIDUP DAN KEHUTANAN' => 'LHK',
        'LINGKUNGAN HIDUP'               => 'LH',
        'KESEHATAN'                      => 'Kes',
        'PERHUBUNGAN'                    => 'Hub',
        'PEKERJAAN UMUM DAN PERUMAHAN RAKYAT' => 'PUPR',
    ];

    private const BULAN = [
        'januari' => 1, 'februari' => 2, 'pebruari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5,
        'juni' => 6, 'juli' => 7, 'agustus' => 8, 'september' => 9, 'oktober' => 10,
        'november' => 11, 'nopember' => 11, 'desember' => 12,
    ];

    /**
     * Identitas peraturan dari kepala dan penutup naskahnya.
     *
     * Hanya yang tertulis jelas yang diisi; selebihnya dibiarkan kosong
     * untuk diisi orang atau AI. Tebakan yang salah pada kolom Nomor
     * lebih merugikan daripada kolom kosong: register memakai nomor itu
     * sebagai identitas, dan nomor yang keliru tampak sama meyakinkannya
     * dengan yang benar.
     *
     * @return array{jenis:string,nomor:string,judul:string,tanggal_terbit:string,instansi:string}
     */
    public static function identitas(string $naskah): array
    {
        $teks = self::rapikan($naskah);
        $datar = preg_replace('~\s+~u', ' ', $teks) ?? $teks;
        $kepala = mb_substr($datar, 0, 4000);

        $out = ['jenis' => '', 'nomor' => '', 'judul' => '', 'tanggal_terbit' => '', 'instansi' => ''];

        /* Jenis: dari kepala naskah, atau dari kalimat "Menetapkan :"
           yang selalu mengulang jenis dan judulnya — satu-satunya tempat
           yang tersisa bila halaman pertamanya berupa gambar. */
        /* Urutannya penting. Daftar "Mengingat" di halaman pertama menyebut
           peraturan lain dengan huruf biasa — "Peraturan Pemerintah Nomor
           55 Tahun 2010" — dan pencarian yang tidak peka huruf besar akan
           menjadikan Permen itu sebuah PP. Yang dibaca hanya HURUF BESAR:
           kalimat "Menetapkan :" lebih dulu, lalu awal kepala naskah. */
        $menetapkan = preg_match('~Menetapkan\s*:\s*(.{10,600}?)(?:\.\s|BAB\s+I\b|Pasal\s+1\b)~u', $datar, $mt) ? $mt[1] : '';
        $awalKepala = mb_substr($kepala, 0, 600);
        $sumberJenis = trim($menetapkan.' '.$awalKepala);

        foreach ([$menetapkan, $awalKepala] as $sumber) {
            foreach (self::JENIS_KEPALA as $kunci => $jenis) {
                if ($sumber !== '' && mb_strpos($sumber, $kunci) !== false) { $out['jenis'] = $jenis; break 2; }
            }
        }

        /* Kementerian, untuk instansi dan singkatan nomor. */
        $kementerian = null;
        if (preg_match('~(?:PERATURAN|KEPUTUSAN)\s+MENTERI\s+(.{3,80}?)\s+(?:REPUBLIK\s+INDONESIA\s+)?(?:NOMOR|TENTANG)\b~u', $sumberJenis, $mk)) {
            $kementerian = trim($mk[1]);
        }

        if (preg_match('~\bNOMOR\s*:?\s*([0-9][0-9A-Za-z./\-]*(?:\s+TAHUN\s+\d{4})?)~u', $kepala, $mn)) {
            $nomor = preg_replace('~\s+~', ' ', trim($mn[1]));
            $nomor = preg_replace('~\s+TAHUN\s+~iu', ' Tahun ', $nomor);
            $awalan = self::SINGKAT[$out['jenis']] ?? '';
            if ($awalan && $kementerian) {
                foreach (self::KEMENTERIAN_SINGKAT as $nama => $singkat) {
                    if (mb_stripos($kementerian, $nama) !== false) { $awalan .= ' '.$singkat; break; }
                }
            }
            $out['nomor'] = trim($awalan.' Nomor '.$nomor);
        }

        /* Judul: sesudah TENTANG sampai "DENGAN RAHMAT" pada kepala, atau
           dari kalimat "Menetapkan :". */
        if (preg_match('~\bTENTANG\s+(.{5,400}?)\s+DENGAN\s+RAHMAT~u', $kepala, $mj)) {
            $out['judul'] = self::judulRapi($mj[1]);
        } elseif (isset($mt[1]) && preg_match('~\bTENTANG\s+(.{5,400})$~u', trim($mt[1]), $mj)) {
            $out['judul'] = self::judulRapi($mj[1]);
        }

        if (preg_match('~Ditetapkan\s+di\s+\S+(?:\s+\S+)?\s+pada\s+tanggal\s+(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})~iu', $datar, $md)) {
            $bl = self::BULAN[mb_strtolower($md[2])] ?? null;
            if ($bl && checkdate($bl, (int) $md[1], (int) $md[3])) {
                $out['tanggal_terbit'] = sprintf('%04d-%02d-%02d', $md[3], $bl, $md[1]);
            }
        }

        $out['instansi'] = match (true) {
            $kementerian !== null                        => 'Kementerian '.self::judulRapi($kementerian),
            $out['jenis'] === 'Keputusan Direktur Jenderal' && preg_match('~DIREKTUR\s+JENDERAL\s+(.{3,80}?)\s+(?:NOMOR|TENTANG)\b~u', $kepala, $mdj)
                                                         => 'Direktorat Jenderal '.self::judulRapi($mdj[1]),
            in_array($out['jenis'], ['Undang-Undang'], true) => 'DPR RI dan Presiden',
            in_array($out['jenis'], ['Peraturan Pemerintah', 'Peraturan Presiden'], true) => 'Presiden Republik Indonesia',
            default                                      => '',
        };

        return $out;
    }

    /** "PELAKSANAAN KAIDAH PERTAMBANGAN YANG BAIK" → "Pelaksanaan Kaidah Pertambangan yang Baik". */
    private static function judulRapi(string $t): string
    {
        $t = trim(preg_replace('~\s+~u', ' ', $t) ?? $t, " .,;:");

        if ($t !== mb_strtoupper($t)) return $t;   // sudah bukan huruf besar semua

        $kecil = ['dan', 'atau', 'yang', 'di', 'ke', 'dari', 'pada', 'untuk', 'dalam', 'atas', 'serta', 'dengan', 'bagi', 'oleh', 'tentang'];
        $kata = explode(' ', mb_strtolower($t));

        foreach ($kata as $i => $k) {
            if ($i > 0 && in_array($k, $kecil, true)) continue;
            $kata[$i] = mb_strtoupper(mb_substr($k, 0, 1)).mb_substr($k, 1);
        }

        return implode(' ', $kata);
    }
}
