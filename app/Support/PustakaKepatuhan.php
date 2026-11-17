<?php

namespace App\Support;

use App\Models\ComplianceSubject;

/**
 * Daftar periksa pemenuhan yang sudah jadi, siap dipakai.
 *
 * Menyusun register dari nol berarti mengetik tiga puluh sampai lima
 * puluh baris sebelum satu pun penilaian dapat dikerjakan — dan itulah
 * yang membuat modul evaluasi pemenuhan di banyak tempat berhenti di
 * baris keenam. Pustaka ini memindahkan pekerjaan itu ke sini sekali,
 * lalu membagikannya ke setiap perusahaan.
 *
 * ── Yang dimuat, dan yang tidak ──
 *
 * Yang dimuat: nomor klausul, judulnya, dan pertanyaan pemeriksaan yang
 * disusun sendiri. Yang TIDAK dimuat: teks persyaratan standar ISO,
 * yang berhak cipta — organisasi tetap perlu memegang salinan resminya,
 * dan pustaka ini indeks penelusuran, bukan penggantinya.
 *
 * ── Kenapa memakai mesin yang sama ──
 *
 * Gap analysis ISO 45001 dan matriks dokumen wajib SMKP tampak dua hal
 * berbeda, tetapi pertanyaannya sama persis: butir ini sudah dipenuhi
 * atau belum, dan kalau belum siapa yang mengerjakan sampai kapan.
 * Dibuatkan modul sendiri-sendiri, keduanya akan punya dasbor sendiri,
 * ekspor sendiri, dan perhitungan persentase sendiri yang perlahan
 * berbeda angkanya.
 */
final class PustakaKepatuhan
{
    /**
     * @return array<string,array{
     *   nama:string, sumber:string, jenis:string, nomor:string, judul:string,
     *   instansi:?string, aspek:?string, iso:?string, sumberAcuan:string,
     *   ket:string, butir:array<int,array{0:string,1:string}>
     * }>
     */
    public static function semua(): array
    {
        return [
            'gap-45001-2027' => [
                'nama'   => 'Gap Analysis Transisi ISO 45001:2027',
                'sumber' => 'ISO',
                'jenis'  => 'Standar Nasional / Internasional',
                'nomor'  => 'ISO 45001:2027 (rancangan)',
                'judul'  => 'Kesiapan Transisi Sistem Manajemen Keselamatan dan Kesehatan Kerja',
                'instansi' => 'International Organization for Standardization',
                'aspek'  => 'safety',
                'iso'    => '45001',
                'sumberAcuan' => 'Disusun mengikuti kerangka gap analysis transisi ISO 45001:2027 '
                                .'terbitan Standards Unlimited (form GA-45001-2027), dengan rumusan '
                                .'pertanyaan ditulis ulang dalam bahasa Indonesia.',
                'ket' => 'Enam bagian: empat area yang berubah pada revisi 2027, satu penguatan '
                        .'rantai pasok, dan satu verifikasi pemenuhan edisi 2018 yang berjalan. '
                        .'Edisi 2018 tetap acuan resmi sampai 2027 terbit; daftar ini menilai '
                        .'KESIAPAN, bukan kepatuhan terhadap standar yang belum berlaku.',
                'butir' => self::gap45001(),
            ],

            'dokumen-wajib-smkp' => [
                'nama'   => 'Matriks Dokumen Wajib SMKP Minerba vs ISO',
                'sumber' => 'Dokumen',
                'jenis'  => 'Persyaratan Lain',
                'nomor'  => 'Kepmen ESDM 1827.K/30/MEM/2018 — Lampiran IV',
                'judul'  => 'Dokumen dan Rekaman Wajib Penerapan SMKP Minerba, dipetakan ke klausul ISO',
                'instansi' => 'Kementerian ESDM',
                'aspek'  => 'safety',
                'iso'    => null,
                'sumberAcuan' => 'Kepmen ESDM 1827.K/30/MEM/2018 Lampiran IV dan Kepdirjen Minerba '
                                .'185.K/37.04/DJB/2019, dipetakan ke ISO 9001:2026, ISO 14001:2026, '
                                .'dan ISO 45001:2018.',
                'ket' => 'Tiga puluh empat dokumen dan rekaman wajib, dikelompokkan menurut tujuh '
                        .'elemen SMKP. Tiap butir menyebut klausul ISO yang dipenuhi dokumen yang '
                        .'sama, supaya satu sistem manajemen terintegrasi memenuhi ketiganya '
                        .'sekaligus alih-alih tiga tumpukan berkas terpisah.',
                'butir' => self::dokumenWajibSmkp(),
            ],
        ];
    }

    public static function satu(string $kunci): ?array
    {
        return self::semua()[$kunci] ?? null;
    }

    /**
     * Terbitkan satu daftar periksa sebagai subjek pemenuhan baru.
     *
     * Butirnya tersimpan TANPA status — belum dinilai, bukan N/A. Daftar
     * periksa yang lahir sudah bernilai adalah daftar periksa yang tidak
     * akan pernah dibaca.
     */
    public static function terbitkan(string $kunci, ?int $companyId, int $tahun, ?int $userId = null): ComplianceSubject
    {
        $p = self::satu($kunci);

        if (!$p) throw new \InvalidArgumentException("Daftar periksa '{$kunci}' tidak dikenal.");

        $s = ComplianceSubject::create([
            'company_id'    => $companyId,
            'user_id'       => $userId,
            'sumber'        => $p['sumber'],
            'kode'          => Kepatuhan::kodeBaru($p['aspek'], $companyId, $tahun),
            'jenis'         => $p['jenis'],
            'nomor'         => $p['nomor'],
            'judul'         => $p['judul'],
            'instansi'      => $p['instansi'],
            'aspek'         => $p['aspek'],
            'iso_kode'      => $p['iso'],
            'tahun'         => $tahun,
            'status'        => 'Tetap',
            'ruang_lingkup' => $p['ket'],
            'rangkuman'     => $p['sumberAcuan'],
        ]);

        foreach ($p['butir'] as $i => [$penunjuk, $uraian]) {
            $s->points()->create([
                'penunjuk'    => $penunjuk,
                'rangkuman'   => $uraian,
                'order_index' => $i + 1,
            ]);
        }

        return $s;
    }

    /* ══════════════ ISO 45001:2027 ══════════════ */

    /** @return array<int,array{0:string,1:string}> */
    private static function gap45001(): array
    {
        return [
            // 1 — kesehatan mental dan bahaya psikososial (baru)
            ['1.1 · Klausul 6.1', 'Bahaya psikososial — beban kerja berlebih, kelelahan mental, perundungan, kekerasan, dan tekanan target — ikut diidentifikasi dalam proses identifikasi bahaya yang sama dengan bahaya fisik, memakai metodologi terdokumentasi.'],
            ['1.2 · Klausul 6.1', 'Risiko psikososial dinilai dengan metodologi penilaian risiko yang sama: kemungkinan, keparahan, dan kendali yang sudah ada, menghasilkan peringkat risiko yang tercatat.'],
            ['1.3 · Klausul 8.1', 'Kendali atas risiko psikososial signifikan diterapkan pada AKARNYA — penataan ulang pekerjaan, pelatihan penyelia, saluran pelaporan, bantuan bagi pekerja — bukan hanya pada gejalanya, dan keefektifannya dipantau.'],
            ['1.4 · Klausul 7.4', 'Tersedia saluran bagi pekerja untuk melaporkan tekanan, perundungan, atau persoalan keselamatan psikologis, termasuk jalur anonim bila diperlukan, beserta bukti bahwa laporannya ditanggapi.'],
            ['1.5 · Klausul 9.1', 'Kinerja risiko psikososial dipantau dengan indikator yang ditetapkan — laporan hampir celaka, angka ketidakhadiran, survei kesejahteraan, kecenderungan insiden — dan dibahas pada tinjauan manajemen.'],

            // 2 — kerja jarak jauh dan hibrida (baru)
            ['2.1 · Klausul 6.1', 'Identifikasi bahaya diperluas ke lingkungan kerja jarak jauh dan kerja dari rumah: ergonomi, keterasingan, psikososial, dan prosedur darurat. "Tempat kerja" berarti di mana pun pekerjaan dilakukan.'],
            ['2.2 · Klausul 8.1', 'Tersedia proses penilaian mandiri stasiun kerja rumah — perkakas, pengetahuan, dan kerangka penilaiannya — beserta bukti bahwa hasilnya ditindaklanjuti. Perusahaan tidak diwajibkan memeriksa rumah pekerja.'],
            ['2.3 · Klausul 8.1', 'Prosedur tanggap darurat mencakup skenario pekerja jarak jauh: kontak darurat, pelaporan insiden, dan akses pertolongan pertama saat bekerja di luar lokasi perusahaan.'],
            ['2.4 · Klausul 7.4', 'Ada proses komunikasi dan pengawasan bagi pekerja jarak jauh untuk mencegah keterasingan: temu berkala terjadwal, jalur eskalasi yang jelas, dan pengaturan kerja jarak jauh yang terdokumentasi.'],
            ['2.5 · Klausul 6.1', 'Risiko yang lahir dari kerja digital ikut diidentifikasi: kelelahan layar, budaya selalu-terhubung, dan pelecehan lewat saluran digital.'],

            // 3 — ketahanan iklim (baru)
            ['3.1 · Klausul 4.1', 'Risiko K3 terkait iklim diidentifikasi dalam analisis konteks organisasi: panas ekstrem, banjir, kualitas udara, dan cuaca buruk yang memengaruhi pekerja.'],
            ['3.2 · Klausul 6.1', 'Bahaya terkait iklim dinilai dan dikendalikan: pengelolaan tekanan panas, perlindungan pekerja luar ruang, dan prosedur cuaca darurat.'],
            ['3.3 · Klausul 8.2', 'Kesiapsiagaan darurat memperhitungkan kejadian iklim — banjir, panas ekstrem, badai — beserta dampaknya terhadap keselamatan pekerja, terpadu dengan rencana kelangsungan usaha.'],

            // 4 — ESG dan akuntabilitas kepemimpinan (diperkuat)
            ['4.1 · Klausul 5.1', 'Manajemen puncak menunjukkan akuntabilitas K3 secara aktif dan terlihat — bukan sekadar kebijakan yang ditandatangani — dengan bukti keterlibatan langsung di lapangan.'],
            ['4.2 · Klausul 9.1', 'Indikator kinerja K3 ditetapkan dan dipantau dalam bentuk yang siap dilaporkan sebagai pengungkapan keberlanjutan: angka cedera, insiden psikososial, dan ukuran kesejahteraan.'],
            ['4.3 · Klausul 5.2', 'Kebijakan dan sasaran K3 terhubung dengan strategi keberlanjutan organisasi, bukan dikelola sebagai fungsi kepatuhan yang terpisah.'],

            // 5 — rantai pasok dan kontraktor (diperkuat)
            ['5.1 · Klausul 8.1', 'Lingkup sistem manajemen K3 secara tegas mencakup kontraktor, kegiatan yang dialihdayakan, dan mitra rantai pasok yang bekerja di atau untuk organisasi.'],
            ['5.2 · Klausul 8.4', 'Proses pengadaan menilai dan memverifikasi kinerja K3 kontraktor dan penyedia jasa — saat seleksi, selama pekerjaan berjalan, dan ditinjau berkala; kinerja yang buruk memicu tindakan.'],
            ['5.3 · Klausul 9.1', 'Kinerja K3 kontraktor di lokasi dipantau dan dicatat: insiden, hampir celaka, hasil observasi, dan kepatuhan terhadap persyaratan K3 perusahaan.'],

            // 6 — verifikasi pemenuhan edisi 2018 yang berjalan
            ['6.1 · Klausul 4.1', 'Konteks organisasi — isu internal dan eksternal — sudah diperbarui mencakup perubahan iklim, kecenderungan kerja jarak jauh, dan lanskap risiko psikososial.'],
            ['6.2 · Klausul 4.2', 'Kebutuhan dan harapan pihak berkepentingan terdokumentasi, dengan pekerja jarak jauh dikenali sebagai kelompok tersendiri yang punya kebutuhan berbeda.'],
            ['6.3 · Klausul 5.3', 'Peran, tanggung jawab, dan wewenang K3 terdokumentasi dan dikomunikasikan, termasuk untuk pengaturan kerja jarak jauh dan rantai akuntabilitas risiko psikososial.'],
            ['6.4 · Klausul 6.2', 'Sasaran K3 ditetapkan, terukur, dipantau, dan dikomunikasikan, serta selaras dengan sasaran keberlanjutan; sasaran psikososial dan kerja jarak jauh ikut di dalamnya.'],
            ['6.5 · Klausul 7.2', 'Kerangka kompetensi mencakup pelatihan identifikasi bahaya psikososial dan kewajiban K3 atas pekerja jarak jauh.'],
            ['6.6 · Klausul 8.1', 'Perencanaan dan pengendalian operasional mencakup manajemen perubahan untuk model kerja baru dan perkakas digital, disertai penilaian dampaknya terhadap K3.'],
            ['6.7 · Klausul 9.2', 'Program audit internal sudah diperbarui mencakup seluruh persyaratan baru sebelum audit transisi pertama dilakukan.'],
            ['6.8 · Klausul 9.3', 'Agenda tinjauan manajemen mencakup data kinerja psikososial, hasil K3 pekerja jarak jauh, dan tinjauan risiko iklim, dengan bukti pembahasannya.'],
            ['6.9 · Klausul 10.2', 'Proses investigasi insiden mencakup insiden psikososial, insiden kerja jarak jauh, dan kejadian terkait iklim, dengan analisis akar masalah yang sama untuk semuanya.'],
        ];
    }

    /* ══════════════ dokumen wajib SMKP ══════════════ */

    /** @return array<int,array{0:string,1:string}> */
    private static function dokumenWajibSmkp(): array
    {
        $b = [];

        /* [elemen, nomor, nama dokumen, jenis, klausul 9001, 14001, 45001, wajib?] */
        $daftar = [
            ['I',   1, 'Kebijakan Keselamatan Pertambangan yang ditandatangani manajemen puncak', 'Dokumen', '5.2', '5.2', '5.2', 'Wajib'],
            ['I',   2, 'Bukti komunikasi dan sosialisasi kebijakan', 'Rekaman', '7.3/7.4', '7.3/7.4', '7.3/7.4', 'Bukti'],
            ['I',   3, 'Bukti tinjauan berkala kebijakan', 'Rekaman', '9.3', '9.3', '9.3', 'Bukti'],

            ['II',  4, 'Penelaahan awal kondisi keselamatan pertambangan', 'Rekaman', '4.1/4.2', '4.1/4.2', '4.1/4.2', 'Bukti'],
            ['II',  5, 'IBPR / HIRADC — identifikasi bahaya, penilaian dan pengendalian risiko', 'Dokumen dan rekaman', '6.1', '6.1.1–6.1.2', '6.1.2', 'Wajib'],
            ['II',  6, 'Daftar dan evaluasi peraturan perundangan serta persyaratan lain', 'Dokumen dan rekaman', '—', '6.1.3', '6.1.3', 'Wajib'],
            ['II',  7, 'Tujuan, Sasaran, dan Program (TSP) Keselamatan Pertambangan', 'Dokumen', '6.2', '6.2', '6.2', 'Wajib'],
            ['II',  8, 'Rencana Kerja dan Anggaran Keselamatan Pertambangan, selaras siklus RKAB', 'Dokumen', '6.2/6.3', '6.2', '6.2', 'Bukti'],

            ['III', 9, 'Struktur organisasi beserta uraian tugas, tanggung jawab, dan wewenang', 'Dokumen', '5.3', '5.3', '5.3', 'Wajib'],
            ['III', 10, 'Penunjukan dan pengesahan KTT / PTL / KTBT', 'Rekaman', '5.1/5.3', '5.1', '5.1/5.3', 'Bukti'],
            ['III', 11, 'Sertifikat kompetensi POP/POM/POU dan juru ledak beserta bukti kompetensinya', 'Rekaman', '7.2', '7.2', '7.2', 'Wajib'],
            ['III', 12, 'Program dan rekaman pelatihan serta pendidikan', 'Dokumen dan rekaman', '7.2/7.3', '7.2/7.3', '7.2/7.3', 'Wajib'],
            ['III', 13, 'Struktur dan program Komite Keselamatan Pertambangan', 'Dokumen', '5.1', '5.1', '5.4', 'Bukti'],
            ['III', 14, 'Susunan dan program Tim Tanggap Darurat', 'Dokumen', '8.1', '8.2', '8.2', 'Bukti'],

            ['IV',  15, 'SOP dan instruksi kerja pengelolaan operasional', 'Dokumen', '8.1/8.5.1', '8.1', '8.1', 'Wajib'],
            ['IV',  16, 'Analisis keselamatan pekerjaan (JSA/JSEA) dan izin kerja (PTW)', 'Dokumen dan rekaman', '8.1', '8.1', '8.1.1/8.1.2', 'Bukti'],
            ['IV',  17, 'Program pengelolaan lingkungan kerja — debu, bising, pencahayaan', 'Dokumen dan rekaman', '—', '8.1', '8.1', 'Bukti'],
            ['IV',  18, 'Program pengelolaan kesehatan kerja — MCU, penyakit akibat kerja, kelelahan', 'Dokumen dan rekaman', '—', '—', '8.1', 'Bukti'],
            ['IV',  19, 'Prosedur Keselamatan Operasi: pemeliharaan sarana, instalasi, dan peralatan', 'Dokumen dan rekaman', '8.1/7.1.3', '8.1', '8.1', 'Bukti'],
            ['IV',  20, 'Prosedur pengelolaan bahan peledak dan peledakan serta bahan berbahaya', 'Dokumen dan rekaman', '8.1', '8.1', '8.1', 'Bukti'],
            ['IV',  21, 'Prosedur kesiapsiagaan dan tanggap darurat beserta rekaman simulasinya', 'Dokumen dan rekaman', '8.1', '8.2', '8.2', 'Wajib'],
            ['IV',  22, 'Prosedur dan rekaman pengelolaan kontraktor / perusahaan jasa (IUJP)', 'Dokumen dan rekaman', '8.4', '8.1', '8.1.4', 'Wajib'],

            ['V',   23, 'Program dan rekaman pemantauan serta pengukuran kinerja keselamatan', 'Rekaman', '9.1.1', '9.1.1', '9.1.1', 'Wajib'],
            ['V',   24, 'Laporan inspeksi keselamatan pertambangan, terjadwal dan tidak terjadwal', 'Rekaman', '9.1', '9.1.1', '9.1.1', 'Bukti'],
            ['V',   25, 'Rekaman evaluasi kepatuhan terhadap peraturan perundangan', 'Rekaman', '—', '9.1.2', '9.1.2', 'Wajib'],
            ['V',   26, 'Laporan penyelidikan kecelakaan, kejadian berbahaya, dan penyakit akibat kerja', 'Rekaman', '10.2', '10.2', '10.2', 'Wajib'],
            ['V',   27, 'Program audit dan laporan audit internal SMKP, dilaporkan ke KaIT', 'Dokumen dan rekaman', '9.2', '9.2', '9.2', 'Wajib'],
            ['V',   28, 'Rencana perbaikan dan tindak lanjut (CAPA) atas ketidaksesuaian', 'Rekaman', '10.2', '10.2', '10.2', 'Wajib'],

            ['VI',  29, 'Manual / Pedoman SMKP', 'Dokumen', 'tidak wajib', 'tidak wajib', 'tidak wajib', 'Wajib SMKP, opsional ISO'],
            ['VI',  30, 'Ruang lingkup sistem manajemen', 'Dokumen', '4.3', '4.3', '4.3', 'Wajib'],
            ['VI',  31, 'Prosedur pengendalian dokumen', 'Dokumen', '7.5.2/7.5.3', '7.5.2/7.5.3', '7.5.2/7.5.3', 'Wajib'],
            ['VI',  32, 'Prosedur pengendalian rekaman beserta Daftar Induk Dokumen', 'Dokumen dan rekaman', '7.5.3', '7.5.3', '7.5.3', 'Wajib'],

            ['VII', 33, 'Notulen atau laporan tinjauan manajemen', 'Rekaman', '9.3.3', '9.3', '9.3', 'Wajib'],
            ['VII', 34, 'Program peningkatan kinerja berkelanjutan', 'Dokumen dan rekaman', '10.1/10.3', '10.3', '10.3', 'Bukti'],
        ];

        foreach ($daftar as [$elemen, $no, $nama, $jenis, $q, $e, $k3, $wajib]) {
            $b[] = [
                'Elemen '.$elemen.' · No '.$no,
                $nama.' — '.$jenis.'. Klausul: ISO 9001 '.$q.', ISO 14001 '.$e.', ISO 45001 '.$k3.'. Status ISO: '.$wajib.'.',
            ];
        }

        return $b;
    }
}
