<?php

namespace App\Support;

/**
 * Delapan aspek EQOHSEE.
 *
 * Tujuh mengikuti ejaan namanya — Energy, Quality, Occupational Health,
 * Hygiene, Safety, Environment, Engineering — dan satu lagi, Konservasi
 * Minerba, berdiri di luar akronim karena merupakan kewajiban tersendiri
 * dalam kaidah teknik pertambangan yang baik.
 *
 * Berkas ini adalah SUMBER TUNGGAL identitas pilar. Sebelumnya warna pilar
 * ditulis ulang di tiga tempat (registry ini, layouts/guest, dan halaman
 * pilar) dengan nilai yang berbeda-beda, sehingga satu pilar tampil dengan
 * warna berlainan tergantung halaman. Seluruh tampilan kini membaca dari sini.
 *
 * Tiap pilar membawa: palet tiga tingkat (deep/base/light) untuk gradien,
 * ringkasan, cakupan kerja, dan modul yang menopangnya.
 */
class Pillars
{
    /** @return array<string,array<string,mixed>> */
    public static function all(): array
    {
        return [
            'energy' => [
                'nama'  => 'Energy',
                'ket'   => 'Optimasi energi berkelanjutan',
                'deep'  => '#B85C00', 'warna' => '#F57C00', 'light' => '#FF9800',
                'ikon'  => 'bolt',
                'ringkas' => 'Mengelola konsumsi dan keandalan energi di seluruh rantai operasi tambang — dari pembangkit, distribusi, hingga alat berat di lapangan.',
                'cakupan' => [
                    ['Efisiensi bahan bakar alat berat', 'Pemantauan konsumsi per unit dan per ton produksi.'],
                    ['Keandalan kelistrikan',            'Kelayakan instalasi, proteksi, dan jadwal pemeliharaan.'],
                    ['Jejak emisi operasi',              'Perhitungan emisi dari pemakaian energi.'],
                ],
                'modul' => ['Energy Performance Center', 'Keselamatan Operasi (KO)', 'ISO & Dokumen'],
            ],

            'quality' => [
                'nama'  => 'Quality',
                'ket'   => 'Mutu di setiap pekerjaan',
                'deep'  => '#1B7FA8', 'warna' => '#29ABE2', 'light' => '#5FC5EE',
                'ikon'  => 'gem',
                'ringkas' => 'Menjaga hasil kerja tetap konsisten terhadap standar — lewat prosedur yang terkendali, kompetensi terverifikasi, dan pemeriksaan berkala.',
                'cakupan' => [
                    ['Kendali dokumen & revisi', 'Satu versi berlaku, riwayat perubahan terlacak.'],
                    ['Kompetensi terverifikasi',  'Sertifikat digital ber-barcode yang dapat diperiksa publik.'],
                    ['Audit & tinjauan berkala',  'Temuan ditindaklanjuti sampai tuntas, bukan sekadar dicatat.'],
                ],
                'modul' => ['ISO & Dokumen', 'LMS — Learning Center', 'SMKP Audit'],
            ],

            'occhealth' => [
                'nama'  => 'Occupational Health',
                'ket'   => 'Lindungi kesehatan kerja',
                'deep'  => '#16883F', 'warna' => '#22C55E', 'light' => '#4ADE80',
                'ikon'  => 'health',
                'ringkas' => 'Menjaga pekerja pulang dalam keadaan sehat — mengendalikan pajanan di lingkungan kerja sebelum menjadi penyakit akibat kerja.',
                'cakupan' => [
                    ['Pengukuran faktor bahaya', 'Debu, bising, getaran, pencahayaan, dan bahan kimia.'],
                    ['Pemeriksaan kesehatan',     'Awal, berkala, dan khusus sesuai risiko jabatan.'],
                    ['Kebugaran kerja',           'Kelayakan bekerja pada tugas berisiko tinggi.'],
                ],
                'modul' => ['Hazard Report & Inspeksi', 'LMS — Learning Center'],
            ],

            'hygiene' => [
                'nama'  => 'Hygiene',
                'ket'   => 'Kendalikan pajanan di tempat kerja',
                'deep'  => '#B85C00', 'warna' => '#F57C00', 'light' => '#FF9800',
                'ikon'  => 'droplet',
                'ringkas' => 'Higiene industri: mengukur pajanan di lingkungan kerja lalu menekannya pada sumbernya, sebelum tubuh pekerja yang menanggung.',
                'cakupan' => [
                    ['Pengukuran pajanan',      'Debu respirabel, bising, getaran, iklim kerja, pencahayaan, dan bahan kimia.'],
                    ['Nilai ambang batas',      'Hasil ukur dibandingkan NAB, yang melampaui wajib dikendalikan.'],
                    ['Hierarki pengendalian',   'Eliminasi dan rekayasa lebih dulu; alat pelindung diri jalan terakhir.'],
                ],
                'modul' => ['Hazard Report & Inspeksi', 'ISO & Dokumen'],
            ],

            'safety' => [
                'nama'  => 'Safety',
                'ket'   => 'Zero compromise, zero tolerance',
                'deep'  => '#1B7FA8', 'warna' => '#29ABE2', 'light' => '#5FC5EE',
                'ikon'  => 'shield',
                'ringkas' => 'Inti dari seluruh kerangka: mengenali bahaya lebih dulu, mengendalikannya berjenjang, dan memastikan tindak lanjut benar-benar menutup risiko.',
                'cakupan' => [
                    ['Pelaporan bahaya lapangan', 'Siapa pun boleh melapor, setiap laporan punya pemilik tindak lanjut.'],
                    ['Inspeksi terjadwal',        'Parameter per jenis inspeksi, temuan naik jadi tindakan.'],
                    ['Tingkat kematangan',        '194 item penilaian, dari Dasar sampai Resilient.'],
                ],
                'modul' => ['Hazard Report & Inspeksi', 'Safety Maturity Level', 'SMKP Audit'],
            ],

            'environment' => [
                'nama'  => 'Environment',
                'ket'   => 'Jaga alam untuk masa depan',
                'deep'  => '#16883F', 'warna' => '#22C55E', 'light' => '#4ADE80',
                'ikon'  => 'leaf',
                'ringkas' => 'Menekan dampak operasi terhadap lingkungan sekitar tambang, dari pengelolaan limbah hingga pemulihan lahan pascatambang.',
                'cakupan' => [
                    ['Pengelolaan limbah B3', 'Identifikasi, penyimpanan, dan penanganan sesuai ketentuan.'],
                    ['Kualitas air & udara',  'Pemantauan titik penaatan secara berkala.'],
                    ['Reklamasi lahan',       'Rencana dan realisasi pemulihan area terganggu.'],
                ],
                'modul' => ['Sistem Informasi Gudang & Penyimpanan', 'ISO & Dokumen', 'SMKP Audit'],
            ],

            'engineering' => [
                'nama'  => 'Engineering',
                'ket'   => 'Solusi andal & efisien',
                'deep'  => '#B85C00', 'warna' => '#F57C00', 'light' => '#FF9800',
                'ikon'  => 'gear',
                'ringkas' => 'Memastikan sarana, prasarana, instalasi, dan peralatan tambang layak dioperasikan — dan tetap layak sepanjang umur pakainya.',
                'cakupan' => [
                    ['Kelayakan objek',        'Sertifikasi, masa berlaku, dan status operasi tiap unit.'],
                    ['Perawatan terencana',    'Jadwal preventif dengan pengingat jatuh tempo.'],
                    ['Kajian teknis & pengaman','Analisis kelayakan dan kelengkapan alat pengaman.'],
                ],
                'modul' => ['Keselamatan Operasi (KO)'],
            ],

            // Di luar akronim: kewajiban tersendiri pada kaidah teknik
            // pertambangan yang baik, bukan turunan dari huruf mana pun.
            'konservasi' => [
                'nama'  => 'Konservasi Minerba',
                'ket'   => 'Cadangan dipakai seoptimalnya',
                'deep'  => '#1B7FA8', 'warna' => '#29ABE2', 'light' => '#5FC5EE',
                'ikon'  => 'layers',
                'ringkas' => 'Memastikan mineral dan batubara terambil seoptimal mungkin dan tidak terbuang percuma — termasuk yang berkadar rendah dan mineral ikutannya.',
                'cakupan' => [
                    ['Recovery penambangan',   'Perbandingan cadangan tergali terhadap cadangan tersedia.'],
                    ['Pengelolaan kadar rendah','Batubara dan bijih kadar rendah disimpan, bukan dibuang.'],
                    ['Neraca sumber daya',     'Pencatatan cadangan, produksi, dan sisa umur tambang.'],
                ],
                'modul' => ['ISO & Dokumen', 'SMKP Audit'],
            ],
        ];
    }

    /** Ambil satu pilar. */
    public static function get(string $slug): ?array
    {
        return static::all()[$slug] ?? null;
    }

    /** Warna dasar sebuah pilar (fallback: brand ink). */
    public static function color(string $slug): string
    {
        return static::all()[$slug]['warna'] ?? '#123049';
    }

    /** Gradien siap pakai untuk latar kartu dan ikon. */
    public static function gradient(string $slug): string
    {
        $p = static::get($slug);
        return $p
            ? "linear-gradient(135deg,{$p['deep']} 0%,{$p['warna']} 55%,{$p['light']} 100%)"
            : 'linear-gradient(135deg,#0E2B44 0%,#158D99 100%)';
    }

    /** Urutan slug: tujuh mengikuti ejaan E-Q-O-H-S-E-E, lalu Konservasi. */
    public static function slugs(): array
    {
        return array_keys(static::all());
    }

    /**
     * Pilar utama sebuah modul.
     *
     * Dibaca dari registry modul, bukan ditebak dari potongan nama seperti
     * sebelumnya — pencocokan teks itu meleset (LMS terbaca Safety) dan
     * menaruh kebenaran yang sama di dua tempat.
     */
    public static function forModule(string $modul): string
    {
        foreach (Modules::all() as $m) {
            if ($m['nama'] === $modul) return $m['pilar'] ?? 'engineering';
        }
        return 'engineering';
    }
}
