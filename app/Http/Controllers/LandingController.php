<?php

namespace App\Http\Controllers;

use App\Models\Pembelian\Produk;
use App\Support\{Media, Modules, Pillars, Smkp};
use Illuminate\Database\QueryException;
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

        /* ── HARGA UNTUK HALAMAN DEPAN ──

           Hanya dua angka: paketnya, dan yang termurah satuan. Halaman
           depan menjawab "berapa kira-kira", bukan "berapa tepatnya
           untuk tiap butir" — daftar harga lengkap ada di etalase, dan
           menyalinnya ke sini berarti dua tempat yang harus sama-sama
           diperbarui, yang berarti cepat atau lambat dua harga berbeda
           untuk satu barang yang sama.

           Nol dianggap belum berharga, bukan gratis: butir baru memang
           dipasang berharga nol oleh pembelian:katalog, dan "mulai Rp 0"
           di halaman depan adalah janji yang tidak dimaksudkan siapa
           pun. */
        $jual = $this->hargaJual();

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
            'jual' => $jual,
            'tahun' => now()->year,
        ]);
    }
    /**
     * Dua angka untuk bagian harga: paketnya, dan yang termurah satuan.
     *
     * ── HALAMAN DEPAN TIDAK BOLEH IKUT JATUH ──
     *
     * Sebelum ada bagian harga, halaman depan tidak menyentuh basis data
     * sama sekali — dan itu ternyata sifat yang berharga, bukan
     * kebetulan. Satu tabel yang belum termigrasi sesudah pemasangan,
     * atau basis data yang sedang tersendat, kini cukup untuk membuat
     * satu-satunya halaman yang dilihat calon pembeli menjadi galat 500.
     *
     * Karena itu kegagalannya ditangkap dan diperlakukan sebagai "harga
     * belum diumumkan" — keadaan yang layarnya memang sudah tahu cara
     * menggambarnya, lengkap dengan ajakan meminta penawaran. Yang
     * hilang hanya dua angka; yang tetap berdiri seluruh halamannya.
     *
     * @return array{paket: ?array{nama: string, harga: int, masa: string}, termurah: ?int, jumlah: int}
     */
    private function hargaJual(): array
    {
        $kosong = ['paket' => null, 'termurah' => null, 'jumlah' => 0];

        try {
            $aktif  = Produk::aktif()->where('harga', '>', 0);
            $paket  = (clone $aktif)->where('jenis', Produk::WEBSITE)->orderBy('urutan')->first();
            $satuan = (clone $aktif)->where('jenis', Produk::APLIKASI)->min('harga');

            return [
                'paket'    => $paket ? ['nama' => $paket->nama, 'harga' => $paket->harga,
                                        'masa' => $paket->masaBerlaku()] : null,
                'termurah' => $satuan ? (int) $satuan : null,
                'jumlah'   => (clone $aktif)->where('jenis', Produk::APLIKASI)->count(),
            ];
        } catch (QueryException $e) {
            report($e);

            return $kosong;
        }
    }
}
