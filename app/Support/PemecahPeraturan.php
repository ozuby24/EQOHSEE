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
 * AI dipakai untuk yang memang memerlukan penalaran: meringkas
 * kewajiban satu pasal menjadi satu kalimat, dan mengusulkan bentuk
 * penerapan yang lazim. Keduanya disajikan untuk DIPERIKSA, bukan
 * disimpan langsung — lihat `usul()`.
 *
 * ── Apa yang tidak dikerjakan ──
 *
 * PDF hasil pindaian tidak dapat dibaca: isinya gambar, bukan teks.
 * Berkas semacam itu memulangkan naskah kosong, dan yang mengunggahnya
 * diberi tahu agar menempelkan teksnya sendiri — bukan dibiarkan
 * menunggu hasil yang tidak akan pernah datang.
 */
final class PemecahPeraturan
{
    /** Batas wajar satu unggahan, supaya satu berkas tidak menghabiskan memori. */
    public const MAKS_AKSARA = 400_000;

    /** Paling banyak sekian butir diusulkan dari satu naskah. */
    public const MAKS_BUTIR = 120;

    /**
     * Ambil naskah dari berkas unggahan.
     *
     * @return array{teks:string,catatan:?string}
     */
    public static function dariBerkas(UploadedFile $berkas): array
    {
        $ext = strtolower($berkas->getClientOriginalExtension());

        $teks = match ($ext) {
            'txt', 'md'  => (string) file_get_contents($berkas->getRealPath()),
            'docx'       => self::dariDocx($berkas->getRealPath()),
            'pdf'        => self::dariPdf($berkas->getRealPath()),
            default      => '',
        };

        $teks = self::rapikan($teks);

        if ($teks === '') {
            return ['teks' => '', 'catatan' => match ($ext) {
                'pdf'  => 'Berkas PDF-nya tidak memuat teks — kemungkinan hasil pindaian. '
                         .'Tempelkan naskahnya pada kotak teks.',
                'docx', 'txt', 'md' => 'Berkas terbaca tetapi isinya kosong.',
                default => 'Jenis berkas '.($ext ?: 'ini').' belum dapat dibaca. '
                          .'Pakai .pdf, .docx, atau .txt — atau tempelkan naskahnya.',
            }];
        }

        return ['teks' => $teks, 'catatan' => null];
    }

    private static function dariPdf(string $jalur): string
    {
        try {
            return (new \Smalot\PdfParser\Parser())->parseFile($jalur)->getText();
        } catch (\Throwable $e) {
            Log::warning('PDF peraturan gagal dibaca', ['galat' => $e->getMessage()]);

            return '';
        }
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
        $teks = str_replace(["\r\n", "\r"], "\n", $teks);
        $teks = preg_replace('~[ \t]+~u', ' ', $teks) ?? $teks;
        $teks = preg_replace('~\n{3,}~', "\n\n", $teks) ?? $teks;

        return trim(mb_substr($teks, 0, self::MAKS_AKSARA));
    }

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
     * @return array<int,array{penunjuk:string,isi:string}>
     */
    public static function pecah(string $naskah): array
    {
        $naskah = self::rapikan($naskah);

        if ($naskah === '') return [];

        /* Potong pada tiap "Pasal N" yang berdiri di awal baris.
           `Pasal` di tengah kalimat — "sebagaimana dimaksud dalam Pasal
           5" — bukan judul bagian dan tidak boleh memotong naskah di
           situ; itu akan memecah satu pasal menjadi selusin serpihan. */
        $bagian = preg_split(
            '~^\s*(Pasal\s+\d+[A-Za-z]?)\s*$~mu',
            $naskah,
            -1,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
        ) ?: [];

        $butir = [];
        $pasal = null;

        foreach ($bagian as $potong) {
            $potong = trim($potong);
            if ($potong === '') continue;

            if (preg_match('~^Pasal\s+\d+[A-Za-z]?$~u', $potong)) {
                $pasal = preg_replace('~\s+~', ' ', $potong);
                continue;
            }

            if ($pasal === null) continue;   // pembukaan, menimbang, mengingat

            foreach (self::ayat($pasal, $potong) as $b) {
                $butir[] = $b;
                if (count($butir) >= self::MAKS_BUTIR) return $butir;
            }
        }

        return $butir;
    }

    /**
     * Pecah isi satu pasal menjadi ayat-ayatnya.
     *
     * @return array<int,array{penunjuk:string,isi:string}>
     */
    private static function ayat(string $pasal, string $isi): array
    {
        $potong = preg_split('~(?:^|\n)\s*\((\d+)\)\s*~u', $isi, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];

        // Tanpa penanda ayat: pasalnya satu butir utuh.
        if (count($potong) < 3) {
            $teks = self::ringkasIsi($isi);

            return $teks === '' ? [] : [['penunjuk' => $pasal, 'isi' => $teks]];
        }

        $out = [];

        for ($i = 1; $i < count($potong); $i += 2) {
            $teks = self::ringkasIsi($potong[$i + 1] ?? '');
            if ($teks === '') continue;

            $out[] = ['penunjuk' => $pasal.' Ayat ('.$potong[$i].')', 'isi' => $teks];
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

    /**
     * Usulkan rangkuman dan bentuk penerapan untuk tiap butir.
     *
     * Dipakai bila AI aktif. Tanpa AI, butirnya tetap terusul lengkap —
     * hanya kolom rangkumannya berisi kutipan naskah dan kolom
     * penerapannya kosong, yang memang tugas penilainya untuk mengisi.
     *
     * Hasilnya SELALU disajikan untuk diperiksa dan dicentang sebelum
     * disimpan, dan subjeknya tersimpan berstatus Draf: rangkuman mesin
     * salah dengan cara yang meyakinkan, dan angka pemenuhan yang
     * separuhnya berasal dari pasal yang belum pernah dibaca manusia
     * lebih buruk daripada tidak ada angka.
     *
     * @param  array<int,array{penunjuk:string,isi:string}>  $butir
     * @return array<int,array{penunjuk:string,rangkuman:string,penerapan:string}>
     */
    public static function usul(array $butir, ?string $kegiatan = null): array
    {
        $polos = array_map(fn ($b) => [
            'penunjuk'  => $b['penunjuk'],
            'rangkuman' => $b['isi'],
            'penerapan' => '',
        ], $butir);

        if (!$butir || !Ai::aktif()) return $polos;

        $daftar = implode("\n", array_map(
            fn ($b, $i) => ($i + 1).'. ['.$b['penunjuk'].'] '.mb_substr($b['isi'], 0, 600),
            $butir, array_keys($butir),
        ));

        $peran = <<<'TXT'
        Kamu membantu petugas K3 dan lingkungan menyusun register evaluasi pemenuhan
        peraturan perundangan Indonesia. Untuk tiap butir yang diberikan, tuliskan:
        - "rangkuman": satu kalimat bahasa Indonesia yang menyebutkan KEWAJIBAN yang
          diatur butir itu, dalam bentuk aktif, maksimal 30 kata.
        - "penerapan": bentuk pemenuhan yang LAZIM dikerjakan perusahaan untuk butir
          itu, maksimal 25 kata. Ini usulan, BUKAN pernyataan bahwa hal itu sudah
          dikerjakan. Kosongkan bila butirnya tidak menetapkan kewajiban bagi
          perusahaan (mis. ketentuan peralihan atau definisi).
        Jawab HANYA dengan larik JSON, satu objek per butir, berurutan sama persis
        dengan urutan yang diberikan, tiap objek berkunci "rangkuman" dan "penerapan".
        Jangan menambah, menggabung, atau melewatkan butir.
        TXT;

        $tanya = "Kegiatan perusahaan: ".($kegiatan ?: 'tidak disebutkan')."\n\nButir:\n".$daftar;

        $jawab = Ai::jawab([['peran' => 'pengguna', 'isi' => $tanya]], $peran, 1);

        if (!($jawab['ok'] ?? false)) return $polos;

        $isi = trim((string) ($jawab['isi'] ?? ''));
        $isi = preg_replace('~^```(?:json)?|```$~m', '', $isi) ?? $isi;

        $urai = json_decode(trim($isi), true);

        /* Jumlahnya HARUS sama. Jawaban yang lebih pendek berarti model
           menggabung atau melewatkan butir, dan memasangkannya menurut
           urutan akan menempelkan rangkuman pasal 7 ke pasal 9. Lebih
           baik memulangkan kutipan naskahnya apa adanya. */
        if (!is_array($urai) || count($urai) !== count($butir)) return $polos;

        $out = [];

        foreach ($butir as $i => $b) {
            $r = is_array($urai[$i] ?? null) ? $urai[$i] : [];

            $out[] = [
                'penunjuk'  => $b['penunjuk'],
                'rangkuman' => trim((string) ($r['rangkuman'] ?? '')) ?: $b['isi'],
                'penerapan' => trim((string) ($r['penerapan'] ?? '')),
            ];
        }

        return $out;
    }
}
