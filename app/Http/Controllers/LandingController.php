<?php

namespace App\Http\Controllers;

use App\Support\{Media, Modules, Pillars, Smkp};
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class LandingController extends Controller
{
    public function index()
    {
        $modules = array_map(function (array $module): array {
            $slug = $module['pilar'] ?? Pillars::forModule($module['nama']);
            $pillar = Pillars::get($slug) ?? Pillars::get('engineering');
            $route = $module['rute'] ?? null;

            return $module + [
                'pilar' => $slug,
                'pilarNama' => $pillar['nama'],
                'pilarWarna' => $pillar['warna'],
                'pilarDeep' => $pillar['deep'],
                'url' => $route && Route::has($route) ? route($route) : null,
            ];
        }, Modules::all());

        return Inertia::render('Landing', [
            'hero' => [
                'video' => Media::heroVideo(),
                'poster' => Media::heroPoster(),
            ],
            'galeri' => array_map(fn (array $item): array => $item + [
                'gambarUrl' => Media::url($item['gambar']),
                'videoUrl' => Media::url($item['video'] ?? null),
            ], Media::galeriTerisi()),
            'klien' => Media::klien(),
            'standar' => [
                ['kode' => 'Kepdirjen 185.K/2019', 'ket' => 'Penerapan SMKP Minerba'],
                ['kode' => 'Permen ESDM 26/2018', 'ket' => 'Kaidah teknik pertambangan yang baik'],
                ['kode' => 'SNI ISO 45001', 'ket' => 'Keselamatan dan kesehatan kerja'],
                ['kode' => 'SNI ISO 14001', 'ket' => 'Manajemen lingkungan'],
                ['kode' => 'SNI ISO 50001', 'ket' => 'Manajemen energi'],
                ['kode' => 'SNI ISO 9001', 'ket' => 'Manajemen mutu'],
            ],
            'elemenSmkp' => Smkp::elemen(),
            'jumlahItem' => 194,
            'modul' => $modules,
            'pilar' => Pillars::all(),
            'fitur' => [
                ['judul' => 'Sertifikat ber-barcode', 'ket' => 'Terbit otomatis saat kursus selesai, empat ragam desain, berlogo perusahaan, dan dapat diverifikasi publik lewat pemindaian.'],
                ['judul' => 'Penilaian PTPKKP lengkap', 'ket' => '194 item pengukuran, bobot resmi per parameter, level Dasar hingga Resilient, plus rekapitulasi siap cetak.'],
                ['judul' => 'Kuesioner bebas akses', 'ket' => 'Sebar tautan ke pekerja dan pimpinan unit kerja tanpa perlu akun — hasil langsung terangkum per parameter.'],
                ['judul' => 'Evaluasi trainer', 'ket' => 'Peserta yang menyelesaikan kursus otomatis masuk daftar tunggu penilaian trainer pada empat aspek kompetensi.'],
                ['judul' => 'Kunci jawaban aman', 'ket' => 'Soal evaluasi SOP dinilai sepenuhnya di server — kunci jawaban tidak pernah dikirim ke perangkat peserta.'],
                ['judul' => 'Kendali penuh admin', 'ket' => 'Kelola perusahaan, pengguna, peran, penanda tangan, hingga pemeliharaan sistem dari satu pusat kendali.'],
            ],
            'alur' => [
                ['judul' => 'Daftarkan perusahaan', 'ket' => 'Lengkapi spesifikasi IUP/IUJP, KTT, PJO, dan jumlah tenaga kerja.'],
                ['judul' => 'Susun materi & prosedur', 'ket' => 'Buat kursus, modul, kuis, serta prosedur dan evaluasi SOP.'],
                ['judul' => 'Jalankan penilaian', 'ket' => 'Isi PTPKKP, sebar kuesioner, dan nilai kompetensi peserta.'],
                ['judul' => 'Terbitkan & tindak lanjut', 'ket' => 'Sertifikat terbit otomatis, program peningkatan tersusun dari hasil.'],
            ],
            'tahun' => now()->year,
        ]);
    }
}
