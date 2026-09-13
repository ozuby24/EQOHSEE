<?php

namespace App\Support;

use App\Models\SmkpAudit;

/**
 * Laporan Audit Internal SMKP — bagian naratif dan penomoran temuannya.
 *
 * Acuan: berkas Laporan Audit sungguhan (PT Indo Sejahtera Manunggal Site
 * PT Multi Harapan Utama, 2023), yang menempuh urutan:
 *
 *   I   Latar belakang, beserta dasar hukumnya
 *   II  Gambaran umum auditi — domisili & legalitas, kegiatan, peralatan,
 *       tenaga kerja, lalu ringkasan penerapan tiap elemen
 *   III Lingkup audit
 *   IV  Pelaksanaan audit dan tim auditor
 *   V   Ringkasan dan penilaian — praktik terbaik, ketidaksesuaian
 *       menurut kategori, tingkat pencapaian
 *   VI  Lampiran dan distribusi laporan
 *
 * Tanpa bagian naratif itu, "39,14%" adalah angka tanpa perusahaan di
 * belakangnya: pembacanya tidak dapat mengetahui berapa pekerja yang
 * diaudit, kegiatan apa yang dijalankan, atau mengapa sebuah elemen
 * memperoleh nilai serendah itu.
 */
final class SmkpLaporan
{
    /**
     * Lampiran wajib laporan audit.
     *
     * Diambil apa adanya dari daftar LAMPIRAN – LAMPIRAN pada berkas
     * acuan. Menjadi isian awal, bukan daftar mati: auditi yang bukan
     * perusahaan jasa pertambangan tidak punya butir terakhir.
     */
    public const LAMPIRAN = [
        'Formulir Kriteria Audit',
        'Formulir Kesesuaian Kriteria Audit',
        'Formulir Ketidaksesuaian dan Tindak Lanjut Audit',
        'Formulir Rencana Tindak Lanjut Audit',
        'Formulir Rekapitulasi Ketidaksesuaian',
        'Daftar Hadir Peserta Pertemuan Pembukaan Audit',
        'Daftar Hadir Peserta Pertemuan Penutupan Audit',
        'Respon Perusahaan Terhadap Pelaksanaan Audit',
        'Hasil Audit Pemegang IUJP yang bekerja pada pemegang IUP',
    ];

    /** Distribusi laporan menurut berkas acuan. */
    public const DISTRIBUSI = [
        'Pimpinan perusahaan auditi (asli)',
        'Penanggung Jawab Operasional auditi (salinan)',
        'Kepala Teknik Tambang (salinan)',
        'Tim Audit (salinan)',
    ];

    /**
     * Dasar hukum penerapan dan penilaian SMKP Minerba.
     *
     * Dipisahkan sebagai daftar, bukan satu paragraf: laporan menyebutkan
     * keempatnya satu per satu, dan nomor peraturan yang tenggelam di
     * tengah kalimat panjang adalah nomor yang tidak dapat diperiksa
     * pembacanya.
     */
    public const DASAR_HUKUM = [
        'Peraturan Menteri ESDM Nomor 26 Tahun 2018 tentang Pelaksanaan Kaidah Pertambangan '
        .'yang Baik dan Pengawasan Pertambangan Mineral dan Batubara',
        'Keputusan Menteri ESDM Nomor 1827.K/30/MEM/2018 tentang Pedoman Pelaksanaan Kaidah '
        .'Teknik Pertambangan yang Baik, Lampiran IV',
        'Keputusan Direktur Jenderal Mineral dan Batubara Nomor 185.K/37.04/DJB/2019 tentang '
        .'Petunjuk Teknis Pelaksanaan Keselamatan Pertambangan dan Pelaksanaan, Penilaian, dan '
        .'Pelaporan Sistem Manajemen Keselamatan Pertambangan',
        'Surat Edaran Direktur Teknik dan Lingkungan Mineral dan Batubara/Kepala Inspektur Tambang '
        .'Nomor B-7/MB.07/DBT.KP/2022 tentang Auditor Internal SMKP',
    ];

    /** Ruas naratif laporan, beserta penjelasan singkat isiannya. */
    public static function ruas(): array
    {
        return [
            'domisili' => [
                'judul' => 'Domisili dan Legalitas',
                'ket'   => 'Bentuk badan usaha, pimpinan, alamat kantor pusat dan kantor site, '
                          .'serta nomor izin usaha (IUP/IUPK/IUJP).',
            ],
            'kegiatan' => [
                'judul' => 'Kegiatan Perusahaan',
                'ket'   => 'Kegiatan yang dijalankan auditi pada periode audit, beserta metode '
                          .'dan target produksinya.',
            ],
            'penerapan' => [
                'judul' => 'Penerapan SMKP secara Umum',
                'ket'   => 'Sejauh mana auditi sudah menyesuaikan sistemnya dengan Kepdirjen 185.K.',
            ],
            'lingkup' => [
                'judul' => 'Lingkup Audit',
                'ket'   => 'Area dan kegiatan yang benar-benar dikunjungi tim audit. '
                          .'Dibiarkan kosong, ruang lingkup pada Rencana Audit yang dipakai.',
            ],
            'kesimpulan' => [
                'judul' => 'Kesimpulan Audit',
                'ket'   => 'Simpulan tim audit atas tingkat penerapan SMKP auditi.',
            ],
        ];
    }

    /**
     * Awalan nomor temuan, misalnya "ISM" pada ISM-MYR-01.
     *
     * SUMBERNYA SATU: kolom doc_no_prefix perusahaan, yang sudah dipakai
     * Formulir Rekapitulasi Ketidaksesuaian untuk menomori temuan yang
     * SAMA. Awalan kedua yang diturunkan sendiri di sini akan membuat
     * satu temuan bernomor ISM-MYR-01 pada rekapitulasi dan TPJ-MYR-01
     * pada laporan — dan keduanya beredar ke pihak yang sama.
     *
     * Bila kolomnya kosong, huruf awal tiap kata nama perusahaan dipakai.
     * "PT" dan "CV" dibuang: awalan "PTISM" tidak menunjuk siapa pun.
     */
    public static function awalan(?SmkpAudit $audit): string
    {
        $c = $audit?->company;

        foreach ([$c->doc_no_prefix ?? '', $c->code ?? ''] as $calon) {
            $k = mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim((string) $calon)));
            if ($k !== '') return $k;
        }

        $nama = trim((string) ($c->name ?? ''));
        if ($nama === '') return 'NC';

        $huruf = '';
        foreach (preg_split('/\s+/', $nama) as $kata) {
            $k = preg_replace('/[^A-Za-z]/', '', $kata);
            if ($k === '' || in_array(mb_strtoupper($k), ['PT', 'CV', 'UD', 'TBK'], true)) continue;
            $huruf .= mb_strtoupper(mb_substr($k, 0, 1));
        }

        return $huruf !== '' ? $huruf : 'NC';
    }

    /**
     * Kategori yang dicetak laporan, urut seperti berkas acuan.
     *
     * KRITIKAL TIDAK PERNAH DIHASILKAN RUBRIK. Penilaian berbasis poin
     * hanya melahirkan Kesesuaian, Minor, dan Mayor; kritikal adalah
     * peningkatan yang ditetapkan auditor atas pertimbangannya sendiri.
     * Bagiannya tetap dicetak — laporan acuan pun menuliskan "Kategori
     * Kritikal: Tidak ada" — sebab bagian yang hilang tidak dapat
     * dibedakan dari bagian yang nihil.
     *
     * Singkatannya TIDAK ditulis ulang di sini: ia milik Smkp, tempat
     * Formulir Rekapitulasi juga membacanya.
     */
    public const KATEGORI = ['kritikal', 'mayor', 'minor'];

    /**
     * Peta id temuan → kode ketidaksesuaian, dihitung SEKALI.
     *
     * Tiga berkas menyebut temuan yang sama: Laporan Audit mengurutkannya
     * menurut kategori, Formulir Rekapitulasi menurut urutan kriteria,
     * Formulir Rencana Tindak Lanjut menurut tenggat. Menomori masing-
     * masing dari urutannya sendiri melahirkan tiga kode berbeda bagi
     * satu temuan — dan ketiganya beredar ke pihak yang sama.
     *
     * Karena itu penomoran dihitung satu kali, dalam URUTAN KRITERIA, dan
     * ketiga berkas membacanya. Urutan kriteria yang dipilih bukan
     * sembarang: ia tidak berubah ketika sebuah temuan ditutup atau
     * tenggatnya digeser, sehingga "temuan MYR-03" pada risalah rapat
     * penutupan menunjuk temuan yang sama minggu depan.
     *
     * @return array<int,string>
     */
    public static function petaNomor(SmkpAudit $audit): array
    {
        $urutan = Smkp::urutanKriteria();
        $awalan = self::awalan($audit);

        $temuan = $audit->findings()->get()
            ->sortBy(fn ($t) => $urutan[$t->kode_kriteria] ?? PHP_INT_MAX)
            ->values();

        $per = [];
        $out = [];

        foreach ($temuan as $t) {
            $jenis = Smkp::kodeJenis($t->jenis);
            $per[$jenis] = ($per[$jenis] ?? 0) + 1;

            $out[$t->id] = Smkp::nomorTemuan($jenis, $per[$jenis], $awalan);
        }

        return $out;
    }

    /**
     * Temuan dikelompokkan menurut kategori, bernomor urut di dalamnya.
     *
     * Laporan acuan menomori demikian — ISM-MYR-01 sampai ISM-MYR-28,
     * lalu ISM-MIN-01 sampai ISM-MIN-14 — dan bukan satu deret lurus.
     * Nomor yang berlanjut lintas kategori membuat "ketidaksesuaian
     * nomor 30" tidak dapat dibaca sebagai mayor atau minor tanpa membuka
     * tabelnya.
     *
     * @return array<string,array{label:string,singkat:string,baris:list<array<string,mixed>>}>
     */
    public static function temuanPerKategori(SmkpAudit $audit): array
    {
        $peta = self::petaNomor($audit);
        $out  = [];

        foreach (self::KATEGORI as $kunci) {
            $out[$kunci] = [
                'label'   => ucfirst($kunci),
                'singkat' => Smkp::SINGKAT_JENIS[$kunci],
                'baris'   => [],
            ];
        }

        $temuan = $audit->findings()
            ->orderByRaw(Smkp::urutJenisSql())
            ->orderBy('kode_kriteria')
            ->orderBy('id')
            ->get();

        foreach ($temuan as $t) {
            /* Kolom jenis hanya berisi kode — model menyeragamkannya pada
               penulisan. Yang bukan ketidaksesuaian (observasi) TIDAK ikut
               didaftar: laporan acuan menghitung ketidaksesuaian saja, dan
               observasi yang ikut terhitung menaikkan angka "minor" atas
               catatan yang tidak menuntut tindakan perbaikan apa pun. */
            $kunci = Smkp::kodeJenis($t->jenis);

            if (!isset($out[$kunci])) continue;

            $out[$kunci]['baris'][] = [
                'id'       => $t->id,
                // Dibaca dari peta yang sama dengan ketiga berkas lainnya.
                'nomor'    => $peta[$t->id] ?? '',
                'kode'     => (string) ($t->kode_kriteria ?? ''),
                'uraian'   => (string) ($t->uraian ?? ''),
                'kategori' => $out[$kunci]['label'],
                'status'   => (string) ($t->status ?? ''),
                'target'   => $t->target_selesai?->format('Y-m-d'),
            ];
        }

        return $out;
    }

    /**
     * Informasi Praktik Terbaik — butir yang capaiannya penuh.
     *
     * Laporan acuan mencantumkannya per elemen, sebelum daftar
     * ketidaksesuaian. Laporan yang hanya memuat kekurangan dibaca auditi
     * sebagai daftar tuduhan, dan yang sudah berjalan baik kehilangan
     * satu-satunya tempat ia tercatat.
     *
     * @return list<array{elemen:string,elemenNama:string,kode:string,nama:string,ket:string}>
     */
    public static function praktikTerbaik(SmkpAudit $audit): array
    {
        $hasil = (array) ($audit->hasil ?? []);
        $out   = [];

        foreach (Smkp::elemen() as $e) {
            foreach ($e['sub'] ?? [] as $s) {
                foreach (Smkp::butirSub($s) as $b) {
                    $kode = $b['kode'];
                    $v    = Smkp::nilaiButir($hasil, $kode);
                    $maks = (int) ($b['maks'] ?? 0);

                    if ($v === null || $v === Smkp::NA || $maks <= 0) continue;
                    if ((int) $v < $maks) continue;

                    $out[] = [
                        'elemen'     => $e['kode'],
                        'elemenNama' => $e['nama'],
                        'kode'       => $kode,
                        'nama'       => (string) ($b['nama'] ?? ''),
                        'ket'        => (string) ($hasil[$kode]['ket'] ?? ''),
                    ];
                }
            }
        }

        return $out;
    }

    /**
     * Isian laporan yang tersimpan, dilengkapi bawaannya.
     *
     * Lampiran dan distribusi punya daftar bawaan; yang lain kosong.
     * Membedakan "belum diisi" dari "sengaja dikosongkan" tidak mungkin
     * di sini, jadi daftar yang pernah disimpan — termasuk yang kosong —
     * selalu menang atas bawaannya.
     */
    public static function isi(?array $laporan): array
    {
        $l = $laporan ?? [];

        $out = [];
        foreach (array_keys(self::ruas()) as $k) {
            $out[$k] = (string) ($l[$k] ?? '');
        }

        $out['peralatan']  = array_values(array_filter(
            (array) ($l['peralatan'] ?? []),
            fn ($b) => trim((string) ($b['jenis'] ?? '')) !== '',
        ));
        $out['lampiran']   = array_key_exists('lampiran', $l)   ? array_values((array) $l['lampiran'])   : self::LAMPIRAN;
        $out['distribusi'] = array_key_exists('distribusi', $l) ? array_values((array) $l['distribusi']) : self::DISTRIBUSI;

        /* Ringkasan penerapan tiap elemen, satu paragraf per elemen. */
        $out['elemen'] = [];
        foreach (Smkp::elemen() as $e) {
            $out['elemen'][$e['kode']] = (string) (($l['elemen'] ?? [])[$e['kode']] ?? '');
        }

        return $out;
    }

    /**
     * Sejauh mana bagian naratif laporan terisi.
     *
     * @return array{total:int,terisi:int,kurang:list<string>,lengkap:bool}
     */
    public static function rekap(?array $laporan): array
    {
        $isi    = self::isi($laporan);
        $kurang = [];

        foreach (self::ruas() as $k => $r) {
            // Lingkup boleh kosong: ruang lingkup Rencana Audit yang dipakai.
            if ($k === 'lingkup') continue;
            if (trim($isi[$k]) === '') $kurang[] = $r['judul'];
        }

        $elemenKosong = 0;
        foreach ($isi['elemen'] as $teks) {
            if (trim($teks) === '') $elemenKosong++;
        }

        if ($elemenKosong > 0) {
            $kurang[] = 'Ringkasan penerapan '.$elemenKosong.' elemen';
        }

        $total = count(self::ruas()) - 1 + 1;   // ruas naratif (tanpa lingkup) + ringkasan elemen

        return [
            'total'   => $total,
            'terisi'  => $total - count($kurang),
            'kurang'  => $kurang,
            'lengkap' => $kurang === [],
        ];
    }
}
