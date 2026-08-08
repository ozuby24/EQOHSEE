<?php

namespace App\Support;

/**
 * Enam pilar EQOHSEE — Energy, Quality, Occupational Health,
 * Safety, Environment, Engineering.
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
                'deep'  => '#14497D', 'warna' => '#1F6FB8', 'light' => '#4E9BDD',
                'ikon'  => 'bolt',
                'ringkas' => 'Mengelola konsumsi dan keandalan energi di seluruh rantai operasi tambang — dari pembangkit, distribusi, hingga alat berat di lapangan.',
                'cakupan' => [
                    ['Efisiensi bahan bakar alat berat', 'Pemantauan konsumsi per unit dan per ton produksi.'],
                    ['Keandalan kelistrikan',            'Kelayakan instalasi, proteksi, dan jadwal pemeliharaan.'],
                    ['Jejak emisi operasi',              'Perhitungan emisi dari pemakaian energi.'],
                ],
                'modul' => ['Keselamatan Operasi (KO)', 'ISO & Dokumen'],
            ],

            'quality' => [
                'nama'  => 'Quality',
                'ket'   => 'Mutu di setiap pekerjaan',
                'deep'  => '#1B6E99', 'warna' => '#2FA3DE', 'light' => '#69C2EC',
                'ikon'  => 'droplet',
                'ringkas' => 'Menjaga hasil kerja tetap konsisten terhadap standar — lewat prosedur yang terkendali, kompetensi terverifikasi, dan pemeriksaan berkala.',
                'cakupan' => [
                    ['Kendali dokumen & revisi', 'Satu versi berlaku, riwayat perubahan terlacak.'],
                    ['Kompetensi terverifikasi',  'Sertifikat digital ber-barcode yang dapat diperiksa publik.'],
                    ['Audit & tinjauan berkala',  'Temuan ditindaklanjuti sampai tuntas, bukan sekadar dicatat.'],
                ],
                'modul' => ['ISO & Dokumen', 'LMS — Learning Center', 'Audit SMKP'],
            ],

            'occhealth' => [
                'nama'  => 'Occupational Health',
                'ket'   => 'Lindungi kesehatan kerja',
                'deep'  => '#B45F0C', 'warna' => '#F08A22', 'light' => '#F8B268',
                'ikon'  => 'health',
                'ringkas' => 'Menjaga pekerja pulang dalam keadaan sehat — mengendalikan pajanan di lingkungan kerja sebelum menjadi penyakit akibat kerja.',
                'cakupan' => [
                    ['Pengukuran faktor bahaya', 'Debu, bising, getaran, pencahayaan, dan bahan kimia.'],
                    ['Pemeriksaan kesehatan',     'Awal, berkala, dan khusus sesuai risiko jabatan.'],
                    ['Kebugaran kerja',           'Kelayakan bekerja pada tugas berisiko tinggi.'],
                ],
                'modul' => ['Hazard Report & Inspeksi', 'LMS — Learning Center'],
            ],

            'safety' => [
                'nama'  => 'Safety',
                'ket'   => 'Zero compromise, zero tolerance',
                'deep'  => '#0C6F68', 'warna' => '#12897F', 'light' => '#1FB3A6',
                'ikon'  => 'shield',
                'ringkas' => 'Inti dari seluruh kerangka: mengenali bahaya lebih dulu, mengendalikannya berjenjang, dan memastikan tindak lanjut benar-benar menutup risiko.',
                'cakupan' => [
                    ['Pelaporan bahaya lapangan', 'Siapa pun boleh melapor, setiap laporan punya pemilik tindak lanjut.'],
                    ['Inspeksi terjadwal',        'Parameter per jenis inspeksi, temuan naik jadi tindakan.'],
                    ['Tingkat kematangan',        '194 item penilaian, dari Dasar sampai Resilient.'],
                ],
                'modul' => ['Hazard Report & Inspeksi', 'Safety Maturity Level', 'Audit SMKP'],
            ],

            'environment' => [
                'nama'  => 'Environment',
                'ket'   => 'Jaga alam untuk masa depan',
                'deep'  => '#3D7F26', 'warna' => '#5EAE38', 'light' => '#8ACC6B',
                'ikon'  => 'leaf',
                'ringkas' => 'Menekan dampak operasi terhadap lingkungan sekitar tambang, dari pengelolaan limbah hingga pemulihan lahan pascatambang.',
                'cakupan' => [
                    ['Pengelolaan limbah B3', 'Identifikasi, penyimpanan, dan penanganan sesuai ketentuan.'],
                    ['Kualitas air & udara',  'Pemantauan titik penaatan secara berkala.'],
                    ['Reklamasi lahan',       'Rencana dan realisasi pemulihan area terganggu.'],
                ],
                'modul' => ['ISO & Dokumen', 'Audit SMKP'],
            ],

            'engineering' => [
                'nama'  => 'Engineering',
                'ket'   => 'Solusi andal & efisien',
                'deep'  => '#1B7F8A', 'warna' => '#2CB0BC', 'light' => '#5FCFD8',
                'ikon'  => 'gear',
                'ringkas' => 'Memastikan sarana, prasarana, instalasi, dan peralatan tambang layak dioperasikan — dan tetap layak sepanjang umur pakainya.',
                'cakupan' => [
                    ['Kelayakan objek',        'Sertifikasi, masa berlaku, dan status operasi tiap unit.'],
                    ['Perawatan terencana',    'Jadwal preventif dengan pengingat jatuh tempo.'],
                    ['Kajian teknis & pengaman','Analisis kelayakan dan kelengkapan alat pengaman.'],
                ],
                'modul' => ['Keselamatan Operasi (KO)'],
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

    /** Urutan slug sesuai ejaan E-Q-O-H-S-E-E pada wordmark. */
    public static function slugs(): array
    {
        return array_keys(static::all());
    }

    /**
     * Petakan nama modul (dari App\Support\Modules) ke slug pilar.
     * Urutan pencocokan penting: pola yang lebih khusus diperiksa lebih dulu.
     */
    public static function forModule(string $modul): string
    {
        return match (true) {
            str_contains($modul, 'Hazard')                      => 'occhealth',
            str_contains($modul, 'ISO')                         => 'quality',
            str_contains($modul, 'Gudang')
                || str_contains($modul, 'SIGAP')                => 'environment',
            str_contains($modul, 'Keselamatan Operasi')
                || str_contains($modul, '(KO)')                 => 'engineering',
            str_contains($modul, 'Learning')
                || str_contains($modul, 'Maturity')
                || str_contains($modul, 'SMKP')                 => 'safety',
            default                                             => 'engineering',
        };
    }
}
