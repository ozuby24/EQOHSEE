<?php

namespace App\Support;

/**
 * Daftar rute yang dirender Inertia (Vue), bukan Blade.
 *
 * Ini menentukan bentuk tautan yang digambar sisi Vue: <Link> Inertia
 * untuk sesama halaman Inertia, dan <a href> biasa untuk halaman Blade.
 *
 * Bukan sekadar soal cepat-lambat — ini soal jalan atau tidak.
 * Bertentangan dengan dugaan yang wajar, <Link> TIDAK jatuh ke navigasi
 * peramban biasa ketika tanggapannya bukan Inertia: ia mengirim
 * permintaan ber-header X-Inertia, menerima HTML utuh, lalu menampilkan
 * modal galat dan tetap diam di halaman yang sama. Sempat terjadi:
 * seluruh bilah samping memakai <Link>, dan dari halaman Vue tidak ada
 * satu pun menu yang bisa diklik — satu-satunya jalan keluar adalah
 * tombol back peramban.
 *
 * Daftar ini harus tumbuh setiap kali satu halaman dipindah ke Inertia.
 * Yang menjaganya bukan disiplin melainkan uji: ada uji yang memanggil
 * tiap rute dan membandingkan jenis tanggapannya dengan daftar ini,
 * sehingga daftar yang tertinggal maupun kelebihan sama-sama ketahuan.
 */
final class RuteInertia
{
    /** Nama rute yang mengembalikan Inertia::render(). */
    public const NAMA = [
        'pilar',
        'dashboard',
        'password.confirm',

        'tpkkp.index',
        'tpkkp.assess',
        'tpkkp.rekap',
        'tpkkp.matriks',
        'tpkkp.summary',
        'tpkkp.hasil',
        'tpkkp.metode',
        'tpkkp.tentang',
        'tpkkp.visual',
        'tpkkp.rubrik',
        'tpkkp.jadwal',
        'tpkkp.profile',
        'tpkkp.program',
        'tpkkp.sampling',
        'tpkkp.sampel',
        'tpkkp.roster',
        'tpkkp.data',

        // Satu aksi, dua nama rute: bilah samping memakai tpkkp.kuesioner
        // dan chip PTPKKP memakai kuesioner.admin. Keduanya harus ada di
        // sini, kalau tidak salah satunya menggambar <a href> biasa dan
        // memuat ulang halaman penuh tanpa alasan yang tampak.
        'tpkkp.kuesioner',
        'kuesioner.admin',

        /* Bantuan */
        'bantuan.index',
        'bantuan.masuk',

        /* Pesan */
        'pesan.index',

        /* Hazard */
        'hazard.index',
        'hazard.create',
        'hazard.analytics',
        'hazard.evaluasi',
        'hazard.pengingat',

        /* Inspeksi */
        'inspeksi.index',
        'inspeksi.create',
        'inspeksi.kpi',
        'inspeksi.template.index',
        'inspeksi.template.create',

        /* LMS */
        'news.index',
        'news.create',
        'procedures.index',
        'procedures.create',
        'signatories.index',
        'certificates.index',
        'evaluations.index',
        'evaluations.create',
        'courses.index',
        'courses.create',
        'sop.index',

        /* Admin */
        'admin.system',
        'admin.users.index',
        'admin.users.create',
        'admin.companies.index',
        'admin.companies.create',

        /* Dokumen & ISO */
        'dokumen.index',
        'dokumen.create',
        'dokumen.piramida',
        'dokumen.daftar-induk',
        'iso.index',

        /* Berkas cetak HSE */
        'hazard.ekspor.cetak',
        'inspeksi.ekspor.cetak',

        /* Energy Performance */
        'energi.index',
        'energi.input',
        'energi.konsumsi',
        'energi.fuel',
        'energi.listrik',
        'energi.equipment',
        'energi.kpi',
        'energi.baseline',
        'energi.hemat',
        'energi.karbon',
        'energi.kalkulator',
        'energi.laporan',
        'energi.master',

        /* Konservasi Minerba */
        'konservasi.index',
        'konservasi.data',
        'konservasi.laporan',
        'konservasi.cetak',
        'operasi.index',
        'operasi.data',
        'operasi.target',
        'operasi.gis',
        'operasi.cetak',

        /* Pemeliharaan & Keandalan */
        'maintenance.index',
        'maintenance.order',
        'maintenance.armada',
        'maintenance.cetak',

        /* Air & Penirisan */
        'air.index',
        'air.catatan',
        'air.kolam',
        'air.cetak',

        /* Kestabilan Lereng */
        'geoteknik.index',
        'geoteknik.bacaan',
        'geoteknik.lereng',
        'geoteknik.cetak',

        /* Mining Engineering Hub */
        'meh.index',
        'meh.monitor',
        'meh.energy',
        'meh.fleet',
        'meh.equipment',
        'meh.maintenance',
        'meh.hse',
        'meh.kpi',
        'meh.tools',
        'meh.regulations',

        /* Audit SMKP */
        'smkp.index',
        'smkp.create',
        'smkp.acuan',

        /* Keselamatan Operasi */
        'ko.index',
        'ko.register',
        'ko.create',
        'ko.kelayakan',
        'ko.perawatan',
        'ko.pengaman',
        'ko.kajian',
        'ko.tenaga',
        'ko.tindak',
        'ko.pengaturan',

        /* Gudang */
        'gudang.index',
        'gudang.barang',
        'gudang.barang.baru',
        'gudang.mutasi',
        'gudang.opname',
        'gudang.lokasi',
        'gudang.b3',
        'gudang.laporan',

        /* Personalia */
        'personalia.index',
        'personalia.perusahaan',
        'personalia.direktori',

        /* Profil akun */
        'profile.edit',
    ];

    public static function ada(?string $rute): bool
    {
        return $rute !== null && in_array($rute, self::NAMA, true);
    }
}
