<?php

namespace App\Support;


/**
 * Asisten AI untuk halaman Bantuan — Google Gemini.
 *
 * Gemini dipakai karena punya kuota gratis; OpenAI tidak menyediakan tingkat
 * gratis untuk API-nya. Pemanggilannya lewat HTTP biasa, tanpa paket tambahan:
 * satu endpoint, satu bentuk badan permintaan, dan tidak ada yang perlu
 * dipasang ulang di server saat menerbitkan.
 *
 * Tanpa kunci API, asistennya mati dan mengatakannya terus terang. Itu pilihan
 * yang disengaja: kotak bantuan yang menjawab dengan tebakan lebih berbahaya
 * daripada kotak bantuan yang mengaku belum aktif, sebab yang ditanyakan orang
 * di sini menyangkut keselamatan kerja.
 */
final class AsistenAI
{
    /** Petunjuk sistem — menjaga jawaban tetap pada lingkup EQOHSEE. */
    public const PERAN = <<<'TEKS'
    Anda asisten bantuan aplikasi EQOHSEE, platform Keselamatan dan Kesehatan
    Kerja (K3/HSE) untuk pertambangan Indonesia. Jawab dalam bahasa Indonesia
    yang ringkas dan jelas.

    Lingkup Anda: cara memakai fitur EQOHSEE, istilah K3 pertambangan, dan
    acuan seperti Kepdirjen 185.K/37.04/DJB/2019 tentang SMKP Minerba.

    Aturan yang tidak boleh dilanggar:
    - Jangan mengarang isi peraturan, nomor pasal, atau angka ambang. Bila tidak
      yakin, katakan tidak yakin dan sarankan menghubungi admin.
    - Jangan memberi nasihat medis, hukum, atau keputusan yang menyangkut
      keselamatan seseorang saat itu juga. Untuk keadaan darurat, arahkan ke
      prosedur tanggap darurat perusahaan dan Kepala Teknik Tambang.
    - Anda tidak dapat melihat data perusahaan pemakai. Jangan berpura-pura
      membacanya; jelaskan di menu mana angkanya bisa dilihat sendiri.
    TEKS;

    public static function aktif(): bool
    {
        return Ai::aktif();
    }

    /**
     * Menjawab satu giliran percakapan.
     *
     * Pemanggilannya kini lewat Ai, yang menangani ketiga penyedia dan
     * membaca kunci dari Pusat Kendali maupun .env. Yang tinggal di
     * kelas ini hanya PERAN — batas lingkup jawaban untuk halaman
     * Bantuan, yang memang urusan halaman itu sendiri dan bukan urusan
     * lapisan sambungan.
     *
     * @param  array<int,array{peran:string,isi:string}>  $riwayat  Terlama lebih dulu.
     * @return array{ok:bool, isi:string}
     */
    public static function jawab(array $riwayat): array
    {
        $h = Ai::jawab($riwayat, self::PERAN, (int) config('bantuan.ai.riwayat', 12));

        // Galat penyedia sengaja tidak diteruskan ke halaman Bantuan:
        // yang membukanya pengguna biasa, dan isi galat penyedia kadang
        // memuat nama proyek atau potongan kunci.
        return ['ok' => $h['ok'], 'isi' => $h['isi']];
    }
}
