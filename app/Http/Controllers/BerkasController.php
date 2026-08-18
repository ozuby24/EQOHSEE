<?php

namespace App\Http\Controllers;

use App\Support\Berkas;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Satu pintu bagi seluruh berkas tertutup.
 *
 * Yang menegakkan batas perusahaan di sini bukan pemeriksaan yang
 * ditulis ulang, melainkan `findOrFail` pada modelnya: seluruh model
 * yang terdaftar memakai MilikPerusahaan atau BerindukPerusahaan,
 * sehingga baris milik perusahaan lain memang TIDAK DAPAT DITEMUKAN oleh
 * pengguna ini. Jawabannya karena itu 404, bukan 403 — dan itu memang
 * lebih tepat: 403 mengakui bahwa barisnya ada.
 *
 * Menulis pemeriksaannya sendiri di sini akan melahirkan salinan kedua
 * dari aturan kepemilikan, dan salinan kedua adalah cara paling pasti
 * membuat keduanya berselisih pada suatu hari — biasanya pada modul yang
 * paling jarang dibuka.
 */
class BerkasController extends Controller
{
    public function sajikan(string $jenis, int $baris, ?int $i = null): StreamedResponse
    {
        [$kelas, $atribut, $daftar] = Berkas::TERSAJI[$jenis] ?? abort(404);

        $model = $kelas::findOrFail($baris);
        $nilai = $model->{$atribut};

        $jalur = $daftar ? (((array) $nilai)[$i ?? 0] ?? null) : $nilai;

        abort_if(!$jalur, 404, 'Berkas tidak ditemukan.');

        $disk = Storage::disk(Berkas::TERTUTUP);

        /* Berkas lama masih di disk terbuka sampai perintah pemindah
           dijalankan. Selama itu ia tetap disajikan dari sini, sehingga
           halaman tidak berlubang di tengah pemasangan — dan yang
           membacanya tetap harus melewati penjagaan ini. */
        if (!$disk->exists($jalur)) {
            $lama = Storage::disk(Berkas::TERBUKA);
            abort_if(!$lama->exists($jalur), 404, 'Berkas tidak ditemukan.');
            $disk = $lama;
        }

        /* Ditampilkan di tempat (inline), bukan diunduh: foto bahaya dan
           tanda tangan muncul di dalam halaman lewat <img>, dan unduhan
           paksa membuat gambarnya gagal digambar sama sekali.

           Nama berkasnya TIDAK diambil dari isian pengguna — Laravel
           sudah menggantinya dengan nama acak saat disimpan — sehingga
           tidak ada jalan menyelipkan tanda kutip ke dalam header. */
        return $disk->response($jalur);
    }

    /** Unduh, bukan tampilkan. Dipakai dokumen dan MSDS. */
    public function unduh(string $jenis, int $baris, ?int $i = null): StreamedResponse
    {
        [$kelas, $atribut, $daftar] = Berkas::TERSAJI[$jenis] ?? abort(404);

        $model = $kelas::findOrFail($baris);
        $nilai = $model->{$atribut};
        $jalur = $daftar ? (((array) $nilai)[$i ?? 0] ?? null) : $nilai;

        abort_if(!$jalur, 404, 'Berkas tidak ditemukan.');

        $disk = Storage::disk(Berkas::TERTUTUP);
        if (!$disk->exists($jalur)) {
            $disk = Storage::disk(Berkas::TERBUKA);
            abort_if(!$disk->exists($jalur), 404, 'Berkas tidak ditemukan.');
        }

        return $disk->download($jalur);
    }
}
