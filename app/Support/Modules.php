<?php

namespace App\Support;

/**
 * Katalog modul EQOHSEE.
 *
 * Dibuat sebagai kelas (bukan partial Blade) karena @include punya scope
 * terpisah — variabel dari partial tidak terbawa ke view induk.
 *
 * Kunci 'pilar' menyatakan pilar utama yang ditopang modul ini. Sebelumnya
 * pilar ditebak dari pencocokan potongan nama di Pillars::forModule(), yang
 * meleset — LMS terbaca sebagai Safety padahal registry pilar mendaftarkannya
 * di bawah Quality. Sebuah modul memang dapat menopang beberapa pilar
 * sekaligus; yang dicatat di sini adalah pilar utamanya.
 *
 * Kunci 'rute' menautkan modul ke halaman utamanya. Modul berstatus
 * 'segera' sengaja tidak punya rute; tampilan memakai ketiadaan rute itu
 * untuk menentukan kartu mana yang dapat diklik, alih-alih memeriksa
 * status di dua tempat berbeda.
 */
class Modules
{
    /**
     * Modul jual per KUNCI MENU.
     *
     * Katalog jual dan halaman depan menceritakan aplikasi yang sama;
     * keduanya harus mengambil kalimatnya dari daftar ini, bukan dari
     * salinan masing-masing. Pencocokannya lewat NAMA RUTE — bukan peta
     * nama yang ditulis tangan, sebab peta tangan basi diam-diam: satu
     * modul berganti nama, petanya tidak, dan yang terjadi bukan galat
     * melainkan kartu jual tanpa keterangan sama sekali.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function perKunciMenu(): array
    {
        $rute = [];

        foreach (Menu::all() as $kunci => $m) {
            foreach ($m['groups'] as $grup) {
                foreach ($grup as $butir) $rute[$butir[1]] = $kunci;
            }
        }

        $per = [];

        foreach (self::all() as $modul) {
            $kunci = $rute[$modul['rute'] ?? ''] ?? null;

            if ($kunci !== null) $per[$kunci] = $modul;
        }

        return $per;
    }

    public static function all(): array
    {
        return [
            ['nama' => 'LMS — Learning Center', 'status' => 'aktif', 'pilar' => 'quality', 'rute' => 'courses.index',
             'ket'  => 'Pelatihan, kuis, evaluasi SOP, dan sertifikat digital ber-barcode.',
             'ikon' => 'M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.42A12 12 0 0112 21a12 12 0 01-6.16-10.42L12 14z'],

            /* Tepat sesudah LMS: keduanya berbicara tentang orang yang
               sama. LMS menerbitkan sertifikat pelatihan internal;
               Authority menyimpan seluruh berkas kelayakan kerjanya dan
               menjawab pertanyaan gerbang — boleh atau tidak orang ini
               bekerja hari ini. */
            ['nama' => 'Authority — Kelayakan Kerja', 'status' => 'aktif', 'pilar' => 'occhealth',
             'rute' => 'miners.index',
             'ket'  => 'Kompetensi, MCU, dan kartu masuk tambang dalam satu berkas per orang, dengan pengingat masa berlaku berjenjang.',
             'ikon' => 'M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 14l2 2 4-4'],

            /* Tepat sesudah Authority, dengan alasan yang sama: Authority
               menjawab "boleh atau tidak orang ini bekerja", Roster
               menjawab "kapan ia seharusnya bekerja". Yang kedua
               menolak menjadwalkan orang yang berkasnya tidak berlaku
               pada TANGGAL YANG DIRENCANAKAN — bukan pada hari
               penyusunnya membuka layar. */
            ['nama' => 'Roster & Shift', 'status' => 'aktif', 'pilar' => 'occhealth',
             'rute' => 'roster.index',
             'ket'  => 'Pola kerja bergilir 14:7, 10:2 minggu, dan seterusnya — dengan validasi batas jam kerja Kepmenakertrans 234/2003 dan blokir dari berkas kelayakan yang habis.',
             'ikon' => 'M8 7V3m8 4V3M3 11h18M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z'],

            ['nama' => 'Safety Maturity Level', 'status' => 'aktif', 'pilar' => 'safety', 'rute' => 'tpkkp.index',
             'ket'  => 'Penilaian tingkat kematangan keselamatan: 194 item · 24 parameter · 4 indikator, lengkap Kalkulator Slovin.',
             'ikon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],

            ['nama' => 'Hazard Report & Inspeksi', 'status' => 'aktif', 'pilar' => 'occhealth', 'rute' => 'hazard.index',
             'ket'  => 'Pelaporan bahaya lapangan dan inspeksi rutin dengan tindak lanjut berjenjang, KPI per jabatan, dan ekspor siap cetak.',
             'ikon' => 'M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z'],

            ['nama' => 'Keselamatan Operasi (KO)', 'status' => 'aktif', 'pilar' => 'engineering', 'rute' => 'ko.index',
             'ket'  => 'Kelayakan objek, jadwal perawatan, alat pengaman, kajian teknis, dan tenaga teknis bersertifikat.',
             'ikon' => 'M2.5 18.2a1 1 0 0 0 1 1h17a1 1 0 0 0 1-1v-1.7a1 1 0 0 0-1-1h-17a1 1 0 0 0-1 1zM10 10.2V5.4a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v4.8M4.6 15.5v-3.3a5.6 5.6 0 0 1 5.4-5.6M14 6.6a5.6 5.6 0 0 1 5.4 5.6v3.3'],

            /* Berdiri sendiri, bukan bagian Hazard Report. Hazard mencatat
               bahaya sebelum ada yang celaka; Investigasi bekerja setelah
               kejadian, dengan alur, bukti, dan kesimpulan yang berbeda —
               menggabungkan keduanya dalam satu kartu jual membuat pembeli
               mengira sudah memiliki yang sebenarnya belum dibeli. */
            ['nama' => 'Investigasi Insiden', 'status' => 'aktif', 'pilar' => 'safety',
             'rute' => 'investigasi.dasbor',
             'ket'  => 'Register insiden, analisis SCAT tiga lapis, wawancara terarah, hierarki kendali, dan matriks risiko — dari laporan awal sampai tindakan perbaikan yang tuntas.',
             'ikon' => 'M21 21l-5.2-5.2M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z'],

            /* Dijual terpisah dari SMKP Audit, dan pemisahannya bukan
               soal harga. Yang satu menilai sistem keselamatan pembeli
               sendiri; yang ini menilai mitra yang dipekerjakannya.
               Menggabungkan keduanya dalam satu kartu membuat pembeli
               mengira sudah memiliki yang sebenarnya belum dibeli. */
            ['nama' => 'Pemantauan Perusahaan Jasa (PJP)', 'status' => 'aktif', 'pilar' => 'safety',
             'rute' => 'pjp.dasbor',
             'ket'  => 'Prakualifikasi SMKP 17 kategori dan 126 butir, dokumen berkala beserta ketepatan waktunya, dan evaluasi kinerja semesteran tiap mitra kerja — satu berkas yang siap diminta Inspektur Tambang.',
             'ikon' => 'M4 21h9M6 21V5a1 1 0 0 1 1-1h5a1 1 0 0 1 1 1v6M9 8h1M9 11.5h1M14.5 17.5l2.2 2.2 4.3-4.3'],

            ['nama' => 'SMKP Audit', 'status' => 'aktif', 'pilar' => 'safety', 'rute' => 'smkp.index',
             'ket'  => 'Audit 7 elemen SMKP Minerba sesuai Kepdirjen 185.K/2019: penilaian per kriteria, temuan berjenjang, dan laporan siap cetak.',
             'ikon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],

            ['nama' => 'Sistem Informasi Gudang & Penyimpanan', 'status' => 'aktif', 'pilar' => 'environment',
             'rute' => 'gudang.index',
             'ket'  => 'Register B3, material, dan APD: mutasi keluar masuk, stok opname, pantangan penyimpanan, dan laporan persediaan.',
             'ikon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],

            ['nama' => 'Energy Performance Center', 'status' => 'aktif', 'pilar' => 'energy',
             'rute' => 'energi.index',
             'ket'  => 'Konsumsi bahan bakar dan listrik, intensitas energi, baseline dan sasaran, peluang penghematan, serta jejak karbon.',
             'ikon' => 'M13 2 4 14h7l-1 8 10-13h-7l0-7Z'],

            ['nama' => 'Konservasi Minerba', 'status' => 'aktif', 'pilar' => 'environment',
             'rute' => 'konservasi.index',
             'ket'  => 'Pemantauan produksi, recovery, kehilangan material, dilusi, stockpile, mineral ikutan, dan tindak lanjut perbaikan.',
             'ikon' => 'M12.8 2.6a2 2 0 0 0-1.6 0L2.6 6.5a1 1 0 0 0 0 1.8l8.6 3.9a2 2 0 0 0 1.6 0l8.6-3.9a1 1 0 0 0 0-1.8ZM2 12.4a1 1 0 0 0 .6.9l8.6 3.9a2 2 0 0 0 1.6 0l8.6-3.9a1 1 0 0 0 .6-.9M2 17.2a1 1 0 0 0 .6.9l8.6 3.9a2 2 0 0 0 1.6 0l8.6-3.9a1 1 0 0 0 .6-.9'],

            ['nama' => 'Mining Engineering Hub', 'status' => 'aktif', 'pilar' => 'engineering',
             'rute' => 'meh.index',
             'ket'  => 'Dashboard engineering: produksi, energi, armada, pemeliharaan, KPI, dan alat hitung teknis.',
             'ikon' => 'M3.5 3.5h6.5v8H3.5zM14 3.5h6.5v5H14zM14 12.5h6.5v8H14zM3.5 16h6.5v4.5H3.5z'],

            ['nama' => 'Mine Operations & GIS', 'status' => 'aktif', 'pilar' => 'engineering',
             'rute' => 'operasi.index',
             'ket'  => 'Control Tower operasi: target dan realisasi produksi, OB, strip ratio, delay, ramalan akhir periode, serta peta dan luas area tambang.',
             'ikon' => 'm3 6.5 6-3 6 3 6-3v14l-6 3-6-3-6 3zM9 3.5v14M15 6.5v14'],

            ['nama' => 'Water & Dewatering', 'status' => 'aktif', 'pilar' => 'environment',
             'rute' => 'air.index',
             'ket'  => 'Curah hujan, level kolam, debit dan pompa, kualitas air, serta perkiraan luapan dalam milimeter hujan yang masih sanggup ditahan.',
             'ikon' => 'M12 2.7s5.5 6 5.5 9.8a5.5 5.5 0 1 1-11 0C6.5 8.7 12 2.7 12 2.7Z'],

            ['nama' => 'Maintenance & Reliability', 'status' => 'aktif', 'pilar' => 'engineering',
             'rute' => 'maintenance.index',
             'ket'  => 'Perintah kerja, MTBF dan MTTR, ketersediaan armada, kepatuhan perawatan berkala, tunggakan pekerjaan, dan biaya per jam serta per ton.',
             'ikon' => 'M14.6 6.3a1 1 0 0 0 0 1.4l1.7 1.7a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9l-3.8 3.8Z'],

            ['nama' => 'Lingkungan & Reklamasi', 'status' => 'aktif', 'pilar' => 'environment',
             'rute' => 'lingkungan.index',
             'ket'  => 'Neraca lahan terganggu dan direklamasi, tahapan berjenjang per petak, tingkat tumbuh revegetasi, kecukupan jaminan, dan pemantauan baku mutu yang dapat disesuaikan.',
             'ikon' => 'M20 4c0 9-5.5 13-11 13a5 5 0 0 1-1.6-.3C6 15.6 5 13.4 5 11 5 6.6 10 4 20 4ZM4 20c2.5-4.5 6-7.5 11-9.5'],

            ['nama' => 'Drill & Blast', 'status' => 'aktif', 'pilar' => 'engineering',
             'rute' => 'peledakan.index',
             'ket'  => 'Rancangan peledakan, powder factor, perkiraan getaran dengan tetapan situs yang dikalibrasi sendiri, isi maksimum per tundaan, radius lemparan, dan fragmentasi.',
             'ikon' => 'M12 2.5 9.5 9 3 11.5 9.5 14l2.5 6.5 2.5-6.5 6.5-2.5L14.5 9Z'],

            ['nama' => 'Dispatch & Hauling', 'status' => 'aktif', 'pilar' => 'engineering',
             'rute' => 'angkutan.index',
             'ket'  => 'Keseimbangan armada, rincian waktu edar, antrean di muka gali, kepatuhan muatan 10/10/20, dan tonase yang hilang karena menunggu.',
             'ikon' => 'M2.5 16.5V7a1 1 0 0 1 1-1h9v10.5m0 0h-9m9 0h2m6.5 0h-2m2 0V12l-2.5-3.5H15m6 8a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm-12.5 0a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z'],

            ['nama' => 'Pengendalian Biaya', 'status' => 'aktif', 'pilar' => 'engineering',
             'rute' => 'biaya.index',
             'ket'  => 'Anggaran RKAB terhadap realisasi, biaya per ton dan per BCM, selisih yang dipecah menjadi bagian volume dan bagian tarif, serta proyeksi akhir tahun.',
             'ikon' => 'M12 2.5v19M15.5 7.2c-.6-1.4-2-2.2-3.7-2.2-2.2 0-3.9 1.2-3.9 3s1.5 2.6 4 3.2c2.7.6 4.3 1.5 4.3 3.4 0 2-1.8 3.3-4.2 3.3-2 0-3.5-.9-4.1-2.4'],

            ['nama' => 'Izin Kerja Aman', 'status' => 'aktif', 'pilar' => 'safety',
             'rute' => 'izin.index',
             'ket'  => 'Izin kerja panas, ruang terbatas, ketinggian, dan lainnya: daftar periksa yang dapat disusun sendiri, uji gas beserta kesegarannya, penutupan izin, dan penyaring dua izin yang tidak boleh berbarengan di satu lokasi.',
             'ikon' => 'M9 12.5l2 2 4.5-4.5M8.5 4.5h7a1 1 0 0 1 1 1v1h1.5a2 2 0 0 1 2 2v10.5a2 2 0 0 1-2 2h-13a2 2 0 0 1-2-2V8.5a2 2 0 0 1 2-2H7.5v-1a1 1 0 0 1 1-1Z'],

            ['nama' => 'Kestabilan Lereng', 'status' => 'aktif', 'pilar' => 'engineering',
             'rute' => 'geoteknik.index',
             'ket'  => 'Pemantauan gerakan lereng, kebalikan laju, penyimpangan geometri terbangun, dan acuan kajian geoteknik — alat bantu keputusan, bukan pengganti penilaian tenaga kompeten.',
             'ikon' => 'M2.5 19.5h19L15 8l-3.2 5.2L9.4 9.8ZM9.4 9.8 5.6 4.5 2.5 9'],

            ['nama' => 'ISO & Dokumen', 'status' => 'aktif', 'pilar' => 'quality', 'rute' => 'dokumen.index',
             'ket'  => 'Register dokumen terkendali: nomor revisi, masa berlaku, riwayat perubahan, dan pengingat peninjauan berkala.',
             'ikon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.59a1 1 0 01.7.29l4.42 4.42a1 1 0 01.29.7V19a2 2 0 01-2 2z'],
        ];
    }
}
