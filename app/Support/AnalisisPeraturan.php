<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Analisis otomatis peraturan, memakai mesin yang terpasang di Pusat
 * Kendali (App\Support\Ai) — sambungan yang sama dengan Bantuan dan
 * Diagnosa, sehingga fitur ini ikut berpindah bila pemasangnya berganti.
 *
 * ── Tidak menyebut mesinnya ──
 *
 * Penyedia dan model yang dipakai urusan pemasang, bukan pemakai. Pesan
 * yang dipulangkan ke halaman selalu netral ("analisis otomatis sedang
 * sibuk"), dan rincian galat penyedia — yang sering menyebut nama
 * penyedia, model, bahkan proyeknya — hanya dicatat di log server.
 *
 * ── Kecil-kecil, supaya tidak pernah melampaui batas waktu ──
 *
 * - `butir()`       sepuluh butir sekali jalan: rangkuman, kewajiban atau
 *                   bukan, dan usulan penerapan.
 * - `identitas()`   jenis, nomor, judul, tanggal, instansi, aspek, ruang
 *                   lingkup, dan rangkuman satu peraturan.
 * - `bacaGambar()`  menyalin teks halaman hasil pindaian yang dikirim
 *                   peramban sebagai JPEG — bukan PDF utuh. PDF utuh
 *                   berarti seluruh halamannya dihitung ulang pada setiap
 *                   permintaan, dan batas laju penyedia habis dalam
 *                   beberapa permintaan saja.
 * - `bacaHalaman()` jalan cadangan untuk PDF yang tidak dapat dibaca di
 *                   peramban: berkasnya diunggah dan dibaca per halaman.
 *
 * Tiap permintaan diulang sendiri bila penyedianya sedang sibuk (429,
 * 5xx, 529) — galat yang hampir selalu sembuh dalam hitungan detik.
 *
 * Seluruh hasilnya USULAN, disajikan untuk diperiksa dan disunting, dan
 * subjeknya tersimpan berstatus Draf.
 */
final class AnalisisPeraturan
{
    /** Butir per permintaan. */
    public const PER_GILIRAN = 10;

    /** Halaman per permintaan baca. */
    public const HALAMAN_PER_BACA = 2;

    /** Batas ukuran PDF yang dikirim untuk dibaca (base64 menambah ±33%). */
    public const MAKS_PDF_BYTE = 14 * 1024 * 1024;

    /** Batas satu gambar halaman, dalam aksara base64 (±3 MB). */
    public const MAKS_GAMBAR = 4_000_000;

    private const JEDA  = 75;
    private const ULANG = 2;

    public const PESAN_SIBUK = 'Analisis otomatis sedang sibuk atau tidak dapat dihubungi. Butirnya dapat diulang sebentar lagi.';
    public const PESAN_BENTUK = 'Hasil analisis otomatis tidak dapat dibaca. Butirnya dapat diulang.';

    /* ═══════════════ rangkuman butir ═══════════════ */

    private const PERAN_BUTIR = <<<'TXT'
    Anda membantu petugas K3, lingkungan, dan kepatuhan perusahaan tambang di Indonesia
    menyusun register identifikasi dan evaluasi pemenuhan peraturan perundangan.

    Untuk SETIAP butir (pasal/ayat) yang diberikan, tentukan:
    - "kewajiban": true bila butir itu menetapkan kewajiban, larangan, atau persyaratan yang
      harus dipenuhi PERUSAHAAN (pemegang izin/IUP/IUPK/IUJP, pengusaha, pengurus, badan usaha,
      atau kepala teknik tambang). false untuk definisi, ruang lingkup, tugas atau kewenangan
      pemerintah/menteri/inspektur, ketentuan peralihan, dan ketentuan penutup.
    - "rangkuman": satu kalimat bahasa Indonesia, maksimal 30 kata, yang menyebut isi butir
      itu. Bila kewajiban, tulis dalam bentuk aktif: siapa wajib melakukan apa.
    - "penerapan": bentuk pemenuhan yang LAZIM dikerjakan perusahaan tambang untuk butir itu
      (dokumen, prosedur, rekaman, atau kegiatan yang dapat diperiksa auditor), maksimal 25
      kata. Ini USULAN, bukan pernyataan bahwa sudah dikerjakan. Kosongkan bila kewajiban false.

    Aturan:
    - Jangan mengarang isi yang tidak ada pada butirnya.
    - Jawab HANYA dengan JSON objek: {"butir":[{"no":<nomor>,"kewajiban":<true|false>,
      "rangkuman":"...","penerapan":"..."}]} — satu objek untuk setiap nomor yang diberikan,
      memakai nomor yang sama persis. Jangan menggabung atau melewatkan butir.
    TXT;

    /**
     * @param  list<array{no:int,penunjuk:string,isi:string}>  $butir
     * @return array{ok:bool,butir:list<array{no:int,kewajiban:?bool,rangkuman:string,penerapan:string}>,hilang:list<int>,pesan:?string}
     */
    public static function butir(array $butir, ?string $kegiatan = null): array
    {
        $nomor = array_map(fn ($b) => (int) $b['no'], $butir);

        $daftar = implode("\n", array_map(
            fn ($b) => $b['no'].'. ['.$b['penunjuk'].'] '.mb_substr(trim($b['isi']), 0, 1500),
            $butir,
        ));

        $tanya = 'Kegiatan perusahaan: '.(trim((string) $kegiatan) ?: 'pertambangan (tidak dirinci)')
            ."\n\nButir:\n".$daftar;

        $jawab = Ai::jawab([['peran' => 'pengguna', 'isi' => $tanya]], self::PERAN_BUTIR, 1, [
            'maksToken' => 8192, 'jeda' => self::JEDA, 'json' => true, 'ulang' => self::ULANG,
        ]);

        if (!$jawab['ok']) {
            self::catat('butir', $jawab);
            return ['ok' => false, 'butir' => [], 'hilang' => $nomor, 'pesan' => self::PESAN_SIBUK];
        }

        $data = Ai::uraiJson($jawab['isi']);
        $larik = is_array($data['butir'] ?? null) ? $data['butir'] : (is_array($data) && array_is_list($data) ? $data : null);

        if (!is_array($larik)) {
            self::catat('butir', ['galat' => 'bukan JSON: '.mb_substr($jawab['isi'], 0, 200)]);
            return ['ok' => false, 'butir' => [], 'hilang' => $nomor, 'pesan' => self::PESAN_BENTUK];
        }

        /* Dipasangkan menurut NOMOR, bukan urutan. Model yang melewatkan
           satu butir tidak lagi menggeser rangkuman pasal 7 ke pasal 9;
           yang terlewat disebut, dan dapat diulang sendiri. */
        $out = [];
        foreach ($larik as $r) {
            if (!is_array($r)) continue;
            $no = (int) ($r['no'] ?? 0);
            if (!in_array($no, $nomor, true) || isset($out[$no])) continue;

            $kw = $r['kewajiban'] ?? null;
            $out[$no] = [
                'no'        => $no,
                'kewajiban' => is_bool($kw) ? $kw : (is_string($kw) ? in_array(mb_strtolower($kw), ['true', 'ya', 'yes'], true) : null),
                'rangkuman' => mb_substr(trim((string) ($r['rangkuman'] ?? '')), 0, 3000),
                'penerapan' => mb_substr(trim((string) ($r['penerapan'] ?? '')), 0, 3000),
            ];
        }

        $hilang = array_values(array_diff($nomor, array_keys($out)));

        return [
            'ok'     => $out !== [],
            'butir'  => array_values($out),
            'hilang' => $hilang,
            'pesan'  => $hilang ? count($hilang).' butir belum teranalisis dan dapat diulang.' : null,
        ];
    }

    /* ═══════════════ identitas peraturan ═══════════════ */

    private const PERAN_IDENTITAS = <<<'TXT'
    Anda membantu mengisi identitas satu peraturan perundangan Indonesia untuk register
    kepatuhan perusahaan tambang. Anda diberi bagian awal dan akhir naskahnya, serta isian
    yang sudah terbaca otomatis (boleh kosong atau keliru).

    Jawab HANYA dengan JSON objek berkunci:
    - "jenis": salah satu persis dari daftar jenis yang diberikan, atau "" bila tidak jelas.
    - "nomor": bentuk singkat lazim, mis. "Permen ESDM Nomor 26 Tahun 2018",
      "PP Nomor 50 Tahun 2012", "UU Nomor 2 Tahun 2025", "Kepmen ESDM Nomor 1827 K/30/MEM/2018".
      Kosongkan bila nomornya TIDAK tertulis pada naskah — jangan menebak.
    - "judul": judul resmi sesudah kata "tentang", huruf kapital di awal kata utama.
    - "tanggal_terbit": tanggal ditetapkan/disahkan, format YYYY-MM-DD, atau "" bila tidak tertulis.
    - "instansi": instansi penerbit, mis. "Kementerian Energi dan Sumber Daya Mineral".
    - "aspek": satu kunci dari daftar aspek yang diberikan yang paling sesuai, atau "".
    - "ruang_lingkup": maksimal 40 kata, apa dan siapa yang diatur.
    - "rangkuman": maksimal 80 kata, isi pokok dan kewajiban utama bagi perusahaan.
    TXT;

    /**
     * @param  array<string,string>  $awal  hasil PemecahPeraturan::identitas()
     * @return array{ok:bool,identitas:array<string,string>,pesan:?string}
     */
    public static function identitas(string $naskah, array $awal = []): array
    {
        $naskah = PemecahPeraturan::rapikan($naskah);
        $potongan = mb_strlen($naskah) > 7000
            ? mb_substr($naskah, 0, 5000)."\n[…]\n".mb_substr($naskah, -2000)
            : $naskah;

        $aspek = implode(', ', array_map(fn ($a) => $a.' ('.Kepatuhan::namaAspek($a).')', Kepatuhan::ASPEK));

        $tanya = 'Daftar jenis: '.implode('; ', Kepatuhan::JENIS)
            ."\nDaftar aspek: ".$aspek
            ."\nIsian terbaca otomatis: ".json_encode($awal, JSON_UNESCAPED_UNICODE)
            ."\n\nNaskah:\n".$potongan;

        $jawab = Ai::jawab([['peran' => 'pengguna', 'isi' => $tanya]], self::PERAN_IDENTITAS, 1, [
            'maksToken' => 4096, 'jeda' => self::JEDA, 'json' => true, 'ulang' => self::ULANG,
        ]);

        if (!$jawab['ok']) {
            self::catat('identitas', $jawab);
            return ['ok' => false, 'identitas' => [], 'pesan' => self::PESAN_SIBUK];
        }

        $d = Ai::uraiJson($jawab['isi']);
        if (!is_array($d)) {
            self::catat('identitas', ['galat' => 'bukan JSON: '.mb_substr($jawab['isi'], 0, 200)]);
            return ['ok' => false, 'identitas' => [], 'pesan' => self::PESAN_BENTUK];
        }

        $teks = fn (string $k, int $maks) => mb_substr(trim((string) ($d[$k] ?? '')), 0, $maks);

        $tanggal = $teks('tanggal_terbit', 10);
        if (!preg_match('~^(\d{4})-(\d{2})-(\d{2})$~', $tanggal, $m) || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $tanggal = '';
        }

        return [
            'ok' => true,
            'identitas' => [
                'jenis'          => in_array($d['jenis'] ?? '', Kepatuhan::JENIS, true) ? $d['jenis'] : '',
                'nomor'          => $teks('nomor', 300),
                'judul'          => $teks('judul', 500),
                'tanggal_terbit' => $tanggal,
                'instansi'       => $teks('instansi', 200),
                'aspek'          => in_array($d['aspek'] ?? '', Kepatuhan::ASPEK, true) ? $d['aspek'] : '',
                'ruang_lingkup'  => $teks('ruang_lingkup', 2000),
                'rangkuman'      => $teks('rangkuman', 5000),
            ],
            'pesan' => null,
        ];
    }

    /* ═══════════════ membaca halaman hasil pindaian ═══════════════ */

    private const PERAN_BACA = <<<'TXT'
    Anda menyalin teks dari halaman hasil pindaian sebuah peraturan perundangan Indonesia.
    Salin teksnya APA ADANYA — jangan meringkas, jangan memperbaiki isi, jangan menambah.
    Pertahankan susunan naskah: "Pasal N" pada barisnya sendiri, ayat "(1)", "(2)" di awal
    baris, dan butir "a.", "b." di awal baris. Abaikan nomor halaman, gambar lambang,
    stempel, dan tanda tangan; tulis nama pejabat penanda tangan bila tertulis.
    Jawab HANYA dengan JSON objek: {"halaman":[{"no":<nomor halaman>,"teks":"..."}]}.
    TXT;

    /**
     * Salin teks halaman yang dikirim sebagai gambar.
     *
     * @param  list<array{no:int,data:string}>  $halaman  JPEG base64
     * @return array{ok:bool,halaman:array<int,string>,pesan:?string}
     */
    public static function bacaGambar(array $halaman): array
    {
        $nomor = array_map(fn ($h) => (int) $h['no'], $halaman);

        $tanya = 'Salin teks '.(count($nomor) === 1
            ? 'halaman '.$nomor[0].' (gambar terlampir).'
            : 'halaman '.implode(' dan ', $nomor).' (gambar terlampir, berurutan), masing-masing halaman terpisah.');

        $jawab = Ai::jawab([['peran' => 'pengguna', 'isi' => $tanya]], self::PERAN_BACA, 1, [
            'maksToken' => 12000, 'jeda' => self::JEDA, 'json' => true, 'ulang' => self::ULANG,
            'lampiran'  => array_map(fn ($h) => ['mime' => 'image/jpeg', 'data' => $h['data'],
                                                 'nama' => 'halaman-'.$h['no'].'.jpg'], $halaman),
        ]);

        return self::uraiHalaman($jawab, $nomor);
    }

    /**
     * Jalan cadangan: salin teks halaman $dari..$sampai dari PDF di $jalur.
     *
     * @return array{ok:bool,halaman:array<int,string>,pesan:?string}
     */
    public static function bacaHalaman(string $jalur, int $dari, int $sampai): array
    {
        $isi = @file_get_contents($jalur);

        if ($isi === false) {
            return ['ok' => false, 'halaman' => [], 'pesan' => 'Berkas sementara tidak ditemukan — unggah ulang berkasnya.'];
        }
        if (strlen($isi) > self::MAKS_PDF_BYTE) {
            return ['ok' => false, 'halaman' => [],
                    'pesan' => 'PDF lebih besar dari '.(self::MAKS_PDF_BYTE / 1024 / 1024).' MB. Tempelkan teksnya.'];
        }

        $tanya = $dari === $sampai
            ? "Salin teks halaman {$dari} dari PDF terlampir."
            : "Salin teks halaman {$dari} sampai {$sampai} dari PDF terlampir, masing-masing halaman terpisah.";

        $jawab = Ai::jawab([['peran' => 'pengguna', 'isi' => $tanya]], self::PERAN_BACA, 1, [
            'maksToken' => 12000, 'jeda' => self::JEDA, 'json' => true, 'ulang' => self::ULANG,
            'lampiran'  => [['mime' => 'application/pdf', 'data' => base64_encode($isi), 'nama' => 'peraturan.pdf']],
        ]);

        return self::uraiHalaman($jawab, range($dari, $sampai));
    }

    /** @return array{ok:bool,halaman:array<int,string>,pesan:?string} */
    private static function uraiHalaman(array $jawab, array $nomor): array
    {
        if (!$jawab['ok']) {
            self::catat('baca', $jawab);
            return ['ok' => false, 'halaman' => [], 'pesan' => self::PESAN_SIBUK];
        }

        $d = Ai::uraiJson($jawab['isi']);
        if (!is_array($d['halaman'] ?? null)) {
            self::catat('baca', ['galat' => 'bukan JSON: '.mb_substr($jawab['isi'], 0, 200)]);
            return ['ok' => false, 'halaman' => [], 'pesan' => self::PESAN_BENTUK];
        }

        $out = [];
        foreach ($d['halaman'] as $h) {
            if (!is_array($h)) continue;
            $no = (int) ($h['no'] ?? 0);
            if (in_array($no, $nomor, true)) {
                $out[$no] = trim(PemecahPeraturan::bersihkanHalaman((string) ($h['teks'] ?? '')));
            }
        }

        /* Satu gambar tanpa nomor yang cocok: jawabannya tetap milik
           halaman itu — sebagian model menomori halaman gambar mulai 1. */
        if (!$out && count($nomor) === 1 && count($d['halaman']) === 1 && is_array($d['halaman'][0] ?? null)) {
            $out[$nomor[0]] = trim(PemecahPeraturan::bersihkanHalaman((string) ($d['halaman'][0]['teks'] ?? '')));
        }

        return ['ok' => $out !== [], 'halaman' => $out,
                'pesan' => $out ? null : 'Halaman itu tidak dapat dibaca. Tempelkan teksnya di kotak teks.'];
    }

    /**
     * Rincian kegagalan hanya untuk log server.
     *
     * Pesan penyedia sering menyebut nama penyedia, model, dan proyeknya;
     * yang dikirim ke halaman selalu pesan netral.
     */
    private static function catat(string $langkah, array $jawab): void
    {
        Log::warning('Analisis peraturan gagal', [
            'langkah' => $langkah,
            'galat'   => $jawab['galat'] ?? null,
            'isi'     => isset($jawab['isi']) ? mb_substr((string) $jawab['isi'], 0, 200) : null,
        ]);
    }
}
