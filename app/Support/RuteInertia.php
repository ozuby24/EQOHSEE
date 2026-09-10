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
        'dasbor',
        'temuan.index',
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

        // Sama untuk Pengujian: bilah PTPKKP memakai tpkkp.pengujian,
        // tautan lain memakai pengujian.admin.
        'tpkkp.pengujian',
        'pengujian.admin',

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
        'admin.system.diagnosa',
        'admin.keamanan',
        'admin.pemilik',
        'admin.ai',
        'keamanan.perangkat',
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

        /* Lingkungan & Reklamasi */
        'lingkungan.index',
        'lingkungan.lahan',
        'lingkungan.pemantauan',
        'lingkungan.baku',
        'lingkungan.cetak',

        /* Pengeboran & Peledakan */
        'peledakan.index',
        'peledakan.rencana',
        'peledakan.titik',
        'peledakan.getaran',
        'peledakan.cetak',

        /* Dispatch & Pengangkutan */
        'angkutan.index',
        'angkutan.regu',
        'angkutan.armada',
        'angkutan.muatan',
        'angkutan.cetak',

        /* Pengendalian Biaya */
        'biaya.index',
        'biaya.realisasi',
        'biaya.anggaran',
        'biaya.akun',
        'biaya.cetak',

        /* Izin Kerja Aman */
        'izin.index',
        'izin.daftar',
        'izin.syarat',
        'izin.ambang',
        'izin.cetak',

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
        'investigasi.dasbor',
        'investigasi.insiden',
        'investigasi.insiden.baru',
        'investigasi.daftar',

        /* Pemantauan perusahaan jasa. Hanya yang tanpa parameter yang
           disebut: uji di Tests\Feature\RuteInertiaTest memanggil tiap
           nama di sini tanpa argumen, dan rute ber-{pjp} tidak dapat
           dibangun begitu. Tautan ke halaman rinci ditulis langsung
           sebagai <Link href="/pjp/…"> di sisi Vue. */
        'pjp.dasbor',
        'pjp.index',
        'pjp.baru',
        'pjp.cetak',

        'miners.index',
        'miners.dasbor',
        'miners.kedaluwarsa',
        'miners.mcu.index',
        'miners.induksi.index',
        'miners.riwayat.mcu',
        'miners.riwayat.induksi',
        'miners.riwayat.mine-permit',
        'miners.riwayat.mine-license',
        'miners.riwayat.authority',

        /* Daftar menyilang orang. Kesembilannya memakai komponen
           Miners/Riwayat yang sama, dan seluruhnya Inertia. */
        'miners.daftar.outstanding-mcu',
        'miners.daftar.outstanding-permit',
        'miners.daftar.outstanding-simper',
        'miners.daftar.outstanding-induksi',
        'miners.daftar.penambahan-unit',
        'miners.daftar.upgrade-simper',
        'miners.daftar.perpanjangan',
        'miners.daftar.rujukan',
        'miners.daftar.cetak-kartu',

        /* Pembelian. Halaman bayar TIDAK didaftarkan di sini: ia
           berparameter token dan berada di luar grup auth, sedangkan
           daftar ini dipanggil tanpa parameter oleh RuteInertiaTest. */
        'pembelian.katalog',
        'pembelian.produk',
        'pembelian.daftar',

        /* Etalase publik. Di luar grup auth, jadi uji "lupa didaftarkan"
           tidak akan menemukannya sendiri — ia hanya memeriksa rute
           ber-middleware auth. Didaftarkan tangan supaya <Link> dari
           halaman depan benar-benar berpindah. */
        'katalog.publik',
        'ko.index',
        'ko.register',
        'ko.create',
        'ko.kelayakan',
        'ko.unit',
        'ko.uji',
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
