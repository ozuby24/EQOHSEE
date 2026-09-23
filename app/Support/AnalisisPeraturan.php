<?php

namespace App\Support;

/**
 * Analisis peraturan dengan AI yang terpasang di Pusat Kendali.
 *
 * Memakai sambungan yang SAMA dengan asisten Bantuan dan Diagnosa
 * (App\Support\Ai): penyedia, model, dan kuncinya diatur di satu tempat,
 * dan fitur ini ikut berpindah bila pemasangnya berganti penyedia.
 *
 * Tiga pekerjaan, masing-masing kecil supaya satu permintaan tidak
 * pernah melampaui batas waktu server (Nginx 120 detik):
 *
 * - `butir()`     selusin butir sekali jalan: rangkuman kewajiban, apakah
 *                 butir itu mewajibkan perusahaan, dan usulan penerapan.
 * - `identitas()` jenis, nomor, judul, tanggal, instansi, aspek, ruang
 *                 lingkup, dan rangkuman satu peraturan.
 * - `bacaHalaman()` menyalin teks halaman PDF yang berupa gambar.
 *
 * Seluruh hasilnya USULAN. Disajikan untuk diperiksa dan disunting, dan
 * subjeknya tersimpan berstatus Draf — rangkuman mesin salah dengan cara
 * yang meyakinkan.
 *
 * Sebelumnya seluruh butir dikirim dalam SATU permintaan dengan batas
 * keluaran 900 token. Seratus butir tidak pernah muat di situ: jawabannya
 * terpotong, JSON-nya tidak dapat diurai, dan fitur ini diam-diam jatuh
 * ke kutipan naskah — tanpa satu pun pesan yang menjelaskan mengapa AI
 * "tidak jalan".
 */
final class AnalisisPeraturan
{
    /** Butir per permintaan. */
    public const PER_GILIRAN = 12;

    /** Halaman gambar per permintaan baca. */
    public const HALAMAN_PER_BACA = 2;

    /** Batas ukuran PDF yang dikirim untuk dibaca (base64 menambah ±33%). */
    public const MAKS_PDF_BYTE = 14 * 1024 * 1024;

    private const JEDA = 100;

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
     * @return array{ok:bool,butir:list<array{no:int,kewajiban:?bool,rangkuman:string,penerapan:string}>,hilang:list<int>,pesan:?string,galat:?string}
     */
    public static function butir(array $butir, ?string $kegiatan = null): array
    {
        $daftar = implode("\n", array_map(
            fn ($b) => $b['no'].'. ['.$b['penunjuk'].'] '.mb_substr(trim($b['isi']), 0, 1500),
            $butir,
        ));

        $tanya = 'Kegiatan perusahaan: '.(trim((string) $kegiatan) ?: 'pertambangan (tidak dirinci)')
            ."\n\nButir:\n".$daftar;

        $jawab = Ai::jawab([['peran' => 'pengguna', 'isi' => $tanya]], self::PERAN_BUTIR, 1, [
            'maksToken' => 8192, 'jeda' => self::JEDA, 'json' => true,
        ]);

        $nomor = array_map(fn ($b) => (int) $b['no'], $butir);

        if (!$jawab['ok']) return self::gagal($jawab, $nomor);

        $data = Ai::uraiJson($jawab['isi']);
        $larik = is_array($data['butir'] ?? null) ? $data['butir'] : (array_is_list($data ?? []) ? $data : null);

        if (!is_array($larik)) {
            return self::gagal(['isi' => 'Jawaban AI tidak berbentuk JSON yang dapat dibaca.',
                                'galat' => mb_substr($jawab['isi'], 0, 200)], $nomor);
        }

        /* Dipasangkan menurut NOMOR, bukan urutan. Model yang melewatkan
           satu butir tidak lagi menggeser rangkuman pasal 7 ke pasal 9;
           yang terlewat disebut, dan dapat diulang sendiri. */
        $out = [];
        foreach ($larik as $r) {
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
            'pesan'  => $hilang ? count($hilang).' butir tidak dijawab AI dan dapat diulang.' : null,
            'galat'  => null,
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
      "PP Nomor 50 Tahun 2012", "Kepmen ESDM Nomor 1827 K/30/MEM/2018".
      Kosongkan bila nomornya TIDAK tertulis pada naskah — jangan menebak.
    - "judul": judul resmi sesudah kata "tentang", huruf kapital di awal kata utama.
    - "tanggal_terbit": tanggal ditetapkan, format YYYY-MM-DD, atau "" bila tidak tertulis.
    - "instansi": instansi penerbit, mis. "Kementerian Energi dan Sumber Daya Mineral".
    - "aspek": satu kunci dari daftar aspek yang diberikan yang paling sesuai, atau "".
    - "ruang_lingkup": maksimal 40 kata, apa dan siapa yang diatur.
    - "rangkuman": maksimal 80 kata, isi pokok dan kewajiban utama bagi perusahaan.
    TXT;

    /**
     * @param  array<string,string>  $awal  hasil PemecahPeraturan::identitas()
     * @return array{ok:bool,identitas:array<string,string>,pesan:?string,galat:?string}
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
            'maksToken' => 4096, 'jeda' => self::JEDA, 'json' => true,
        ]);

        if (!$jawab['ok']) {
            return ['ok' => false, 'identitas' => [], 'pesan' => $jawab['isi'], 'galat' => $jawab['galat'] ?? null];
        }

        $d = Ai::uraiJson($jawab['isi']);
        if (!is_array($d)) {
            return ['ok' => false, 'identitas' => [], 'pesan' => 'Jawaban AI tidak berbentuk JSON yang dapat dibaca.',
                    'galat' => mb_substr($jawab['isi'], 0, 200)];
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
            'galat' => null,
        ];
    }

    /* ═══════════════ membaca halaman gambar ═══════════════ */

    private const PERAN_BACA = <<<'TXT'
    Anda menyalin teks dari halaman PDF hasil pindaian sebuah peraturan perundangan Indonesia.
    Salin teksnya APA ADANYA — jangan meringkas, jangan memperbaiki isi, jangan menambah.
    Pertahankan susunan naskah: "Pasal N" pada barisnya sendiri, ayat "(1)", "(2)" di awal
    baris, dan butir "a.", "b." di awal baris. Abaikan nomor halaman, gambar lambang,
    stempel, dan tanda tangan; tulis nama pejabat penanda tangan bila tertulis.
    Jawab HANYA dengan JSON objek: {"halaman":[{"no":<nomor halaman>,"teks":"..."}]}.
    TXT;

    /**
     * Salin teks halaman $dari..$sampai dari PDF di $jalur.
     *
     * @return array{ok:bool,halaman:array<int,string>,pesan:?string,galat:?string}
     */
    public static function bacaHalaman(string $jalur, int $dari, int $sampai): array
    {
        $isi = @file_get_contents($jalur);

        if ($isi === false) {
            return ['ok' => false, 'halaman' => [], 'pesan' => 'Berkas sementara tidak ditemukan — unggah ulang berkasnya.', 'galat' => null];
        }
        if (strlen($isi) > self::MAKS_PDF_BYTE) {
            return ['ok' => false, 'halaman' => [],
                    'pesan' => 'PDF lebih besar dari '.(self::MAKS_PDF_BYTE / 1024 / 1024).' MB, terlalu besar untuk dibaca AI. Tempelkan teksnya.',
                    'galat' => null];
        }

        $tanya = $dari === $sampai
            ? "Salin teks halaman {$dari} dari PDF terlampir."
            : "Salin teks halaman {$dari} sampai {$sampai} dari PDF terlampir, masing-masing halaman terpisah.";

        $jawab = Ai::jawab([['peran' => 'pengguna', 'isi' => $tanya]], self::PERAN_BACA, 1, [
            'maksToken' => 12000, 'jeda' => self::JEDA, 'json' => true,
            'lampiran'  => [['mime' => 'application/pdf', 'data' => base64_encode($isi), 'nama' => 'peraturan.pdf']],
        ]);

        if (!$jawab['ok']) {
            return ['ok' => false, 'halaman' => [], 'pesan' => $jawab['isi'], 'galat' => $jawab['galat'] ?? null];
        }

        $d = Ai::uraiJson($jawab['isi']);
        $larik = is_array($d['halaman'] ?? null) ? $d['halaman'] : null;

        if ($larik === null) {
            return ['ok' => false, 'halaman' => [], 'pesan' => 'Jawaban AI tidak berbentuk JSON yang dapat dibaca.',
                    'galat' => mb_substr($jawab['isi'], 0, 200)];
        }

        $out = [];
        foreach ($larik as $h) {
            $no = (int) ($h['no'] ?? 0);
            if ($no >= $dari && $no <= $sampai) {
                $out[$no] = trim(PemecahPeraturan::bersihkanHalaman((string) ($h['teks'] ?? '')));
            }
        }

        return ['ok' => $out !== [], 'halaman' => $out,
                'pesan' => $out ? null : 'AI tidak memulangkan teks untuk halaman itu.', 'galat' => null];
    }

    /** @return array{ok:false,butir:array,hilang:list<int>,pesan:string,galat:?string} */
    private static function gagal(array $jawab, array $nomor): array
    {
        return ['ok' => false, 'butir' => [], 'hilang' => $nomor, 'pesan' => $jawab['isi'], 'galat' => $jawab['galat'] ?? null];
    }
}
