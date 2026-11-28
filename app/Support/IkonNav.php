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
        'awan'        => 'M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z',
        'daun'        => 'M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10ZM2 21c0-3 1.85-5.36 5.08-6',
        'armada'      => 'M3 16.5V8a1 1 0 0 1 1-1h9.5v9.5M13.5 10.5H17l3 3.5v2.5M6.2 19.7a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm11 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z',
        'alat'        => 'M14.6 6.4a4 4 0 1 0 5.1 5.1l-9.5 9.5-2.8.7.7-2.8ZM4 4l4 4',
        'tukar'       => 'M4 8.5h13l-3.2-3.2M20 15.5H7l3.2 3.2',
        'timbang'     => 'M12 4.5v15M7.5 6.2h9M4 19.5h16M6 6.8 3 13.2h6ZM18 6.8l-3 6.4h6Z',
        'obrolan'     => 'M4 5.5h16a1 1 0 0 1 1 1V15a1 1 0 0 1-1 1H9l-4 3.5V16H4a1 1 0 0 1-1-1V6.5a1 1 0 0 1 1-1Z',
        // Siluet tambahan supaya bilah yang DILIPAT tetap dapat dibaca:
        // di sana tidak ada label, dan enam butir berikon "≡" yang sama
        // (MCU, Mine Permit, SIMPER, SPIP …) tidak dapat dibedakan sama sekali.
        'kesehatan'   => 'M12 20.2s-7.2-4.4-7.2-10.1A4.1 4.1 0 0 1 12 7.3a4.1 4.1 0 0 1 7.2 2.8c0 5.7-7.2 10.1-7.2 10.1ZM7.5 12h2.2l1.3-2.4 2 4.4 1.3-2h2.2',
        'kartu'       => 'M4.5 5.5h15a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1h-15a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1Zm4 8.2a2.1 2.1 0 1 0 0-4.2 2.1 2.1 0 0 0 0 4.2ZM5.6 16.5a3 3 0 0 1 5.8 0M14 10h4M14 13.5h3',
        'kemudi'      => 'M12 3.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17Zm0 6.4a2.1 2.1 0 1 0 0 4.2 2.1 2.1 0 0 0 0-4.2ZM3.8 10.8l6.2 1.2M14 12l6.2-1.2M12 14.1v6.3',
        'centang'     => 'M10 6.5h10M10 12h10M10 17.5h10M3.8 6.5l1.4 1.4 2.4-2.6M3.8 12l1.4 1.4 2.4-2.6M3.8 17.5l1.4 1.4 2.4-2.6',
        'jam'         => 'M12 3.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17ZM12 7.5V12l3.2 2',
        'uang'        => 'M3.5 6.5h17a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1h-17a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1Zm8.5 3a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5ZM6 9.5v5M18 9.5v5',
        'riwayat'     => 'M4.2 12a7.8 7.8 0 1 0 2.3-5.5M4 4.5v4h4M12 8v4.2l2.8 1.6',
        'lup'         => 'M10.5 4a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13Zm4.8 11.3L20 20',
        'pena'        => 'M4 20h4.2L19.3 8.9a2.6 2.6 0 0 0-3.7-3.7L4.5 16.3ZM13.8 7l3.6 3.6',
        'sasaran'     => 'M12 3.5a8.5 8.5 0 1 0 0 17 8.5 8.5 0 0 0 0-17Zm0 4.2a4.3 4.3 0 1 0 0 8.6 4.3 4.3 0 0 0 0-8.6Zm0 3.3a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z',
        'radar'       => 'M12 12l6.2-6.2M12 3.5A8.5 8.5 0 1 0 20.5 12M12 7.7A4.3 4.3 0 1 0 16.3 12',
        'peta'        => 'M3.5 6.5 9 4.2l6 2.3 5.5-2.3v13.3L15 19.8l-6-2.3-5.5 2.3ZM9 4.2v13.3M15 6.5v13.3',
        'tetes'       => 'M12 3.5s-6.2 6.8-6.2 10.9a6.2 6.2 0 0 0 12.4 0c0-4.1-6.2-10.9-6.2-10.9Z',
        'ledak'       => 'M12 2.8v3.4M12 17.8v3.4M2.8 12h3.4M17.8 12h3.4M5.5 5.5l2.4 2.4M16.1 16.1l2.4 2.4M5.5 18.5l2.4-2.4M16.1 7.9l2.4-2.4M12 8.7a3.3 3.3 0 1 0 0 6.6 3.3 3.3 0 0 0 0-6.6Z',
        'gelombang'   => 'M2.5 12h3.2l2.1-5.5 3.4 11 3.2-13 2.2 7.5h4.9',
        'jari'        => 'M8.2 19.6c.8-1.8 1.3-4 1.3-6.6a2.5 2.5 0 0 1 5 0c0 3-.5 5.5-1.5 7.6M5.2 15.8c.4-1 .6-2.1.6-3.3a6.2 6.2 0 0 1 9.6-5.2M18.1 9.9c.1.8.2 1.6.2 2.5 0 2.4-.4 4.6-1.1 6.5',
        'tag'         => 'M3.5 12.6V4.5a1 1 0 0 1 1-1h8.1l8.4 8.4a1.4 1.4 0 0 1 0 2l-6.6 6.6a1.4 1.4 0 0 1-2 0ZM8.2 8.2h.01',
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
        'carbon' => 'awan', 'karbon' => 'awan', 'emisi' => 'awan', 'emission' => 'awan', 'scope' => 'awan',
        'mitigasi' => 'daun', 'dekarbonisasi' => 'daun',
        'studi lca' => 'daun', 'faktor dampak' => 'buku',
        'daftar & peta' => 'kotak', 'daftarkan & hitung' => 'kalkulator',
        'rencana pengangkatan' => 'dokumen', 'simulasi' => 'kalkulator', 'riksa uji' => 'evaluasi',
        'peta petak' => 'kotak', 'kewajiban ppkh' => 'perisai', 'daftarkan lokasi' => 'dokumen',
        'penilaian & bast' => 'evaluasi', 'regulasi & pedoman' => 'buku',
        // MERANTI — Reklamasi & Nursery.
        'dashboard analitik' => 'dashboard', 'peta spasial' => 'kotak', 'data peta' => 'kotak',
        'bibit' => 'daun', 'tanaman' => 'daun', 'penanaman' => 'daun', 'progres' => 'grafik', 'pencapaian' => 'grafik',
        'target blok' => 'nilai', 'arsip' => 'dokumen', 'reklamasi' => 'daun', 'penilaian keberhasilan' => 'nilai',
        'jaminan' => 'perisai', 'surat' => 'dokumen', 'pengajuan dana' => 'timbang', 'berita acara' => 'dokumen',
        'periode' => 'kalender', 'project' => 'kotak', 'pelaksana' => 'orang', 'vendor' => 'orang',
        'peran' => 'orang', 'log aktivitas' => 'buku', 'google sheets' => 'tukar', 'material' => 'kotak',
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

        // Observasi operator (FROP).
        'observasi' => 'evaluasi', 'operator' => 'orang', 'coaching' => 'obrolan',
        'impor' => 'tukar', 'panduan' => 'buku',

        // Miners — label bilah samping Safe Track.
        'induction' => 'kursus', 'authority' => 'sertifikat', 'manpower' => 'orang',
        'unit' => 'armada', 'type unit' => 'armada', 'sub unit' => 'armada',
        'jabatan' => 'orang', 'departemen' => 'gedung', 'pjo' => 'orang',
        'subkontraktor' => 'gedung', 'alur persetujuan' => 'tukar',
        'perpanjangan' => 'kalender', 'masa berlaku' => 'kalender',
        'cetak kartu' => 'sertifikat', 'rujukan' => 'dokumen',

        // Berkas kelayakan Miners dan HRIS.
        'mcu' => 'kesehatan', 'medical' => 'kesehatan', 'kesehatan' => 'kesehatan',
        'mine permit' => 'kartu', 'permit' => 'kartu', 'izin' => 'kartu', 'perizinan' => 'kartu',
        'simper' => 'kemudi', 'spip' => 'dokumen',
        'riwayat' => 'riwayat', 'field break' => 'kalender', 'cuti' => 'kalender',
        'pola & regu' => 'kalender', 'regu' => 'orang', 'regu angkut' => 'armada',
        'pemantauan' => 'grafik', 'pemantauan harian' => 'jam', 'kehadiran' => 'jam',
        'lembur' => 'jam', 'perintah lembur' => 'jam', 'mesin lapangan' => 'jari',
        'pengajuan' => 'tukar', 'persetujuan' => 'centang',
        'upah' => 'uang', 'gaji' => 'uang', 'slip' => 'uang', 'acuan pajak' => 'kalkulator',
        'pkwt' => 'dokumen', 'kontrak' => 'dokumen',

        // Ringkasan dan dasbor tiap modul.
        'ringkasan' => 'dashboard', 'dasbor' => 'dashboard', 'dasbor pemenuhan' => 'grafik',
        'control tower' => 'radar',

        // Insiden, kepatuhan, dan acuan.
        'insiden' => 'bahaya', 'investigasi' => 'lup',
        'persyaratan' => 'centang', 'pengujian' => 'evaluasi',
        'kriteria' => 'buku', 'acuan' => 'buku', 'faktor' => 'buku', 'baku mutu' => 'buku',
        'daftar induk' => 'buku', 'pustaka daftar periksa' => 'buku', 'bagan akun' => 'buku',
        'klausul' => 'centang', 'pemenuhan' => 'centang', 'periksa' => 'centang', 'rekap' => 'nilai',

        // Energi, karbon, operasi, air, dan peledakan.
        'input' => 'pena', 'catatan' => 'pena', 'electricity' => 'energi',
        'baseline' => 'sasaran', 'target' => 'sasaran', 'enpi' => 'grafik',
        'kewajiban' => 'perisai', 'monev' => 'evaluasi', 'tinjauan' => 'evaluasi',
        'tahun dasar' => 'kalender', 'gis' => 'peta', 'layer' => 'peta',
        'kolam' => 'tetes', 'pompa' => 'tetes', 'peledakan' => 'ledak',
        'terlindung' => 'perisai', 'getaran' => 'gelombang', 'penimbangan' => 'timbang',
        'ambang' => 'bahaya', 'perintah kerja' => 'alat',

        // Uang: biaya dan pembelian.
        'realisasi' => 'uang', 'anggaran' => 'uang', 'tagihan' => 'uang',
        'harga' => 'tag', 'katalog' => 'kotak',
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
